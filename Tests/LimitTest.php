<?php

namespace Joomla\AI\Tests;

use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\LimitExceededException;
use Joomla\AI\Limit\Limit;
use Joomla\AI\Provider\AnthropicProvider;
use Joomla\AI\Provider\OpenAIProvider;
use Joomla\AI\Response\Response;
use Joomla\AI\Tests\Stub\FakeStreamTransport;
use Joomla\AI\Tests\Stub\RoutingHttpTrait;
use PHPUnit\Framework\TestCase;

class LimitTest extends TestCase
{
    use RoutingHttpTrait;

    private const ANTHROPIC = 'POST https://api.anthropic.com/v1/messages';

    private float $now = 1000.0;

    /** Directory for this test's usage files */
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-limit-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*.json') ?: []);
        @rmdir($this->dir);
    }

    public function testRequestLimitBlocksUntilTheWindowMoves(): void
    {
        $limit = Limit::requests(2, 60, $this->dir, clock: $this->clock());
        $provider = $this->anthropic([$this->reply(), $this->reply(), $this->reply()], ['limits' => [$limit]]);

        $provider->chat('one');
        $this->now += 10;
        $provider->chat('two');
        $this->now += 5;

        try {
            $provider->chat('three');
            $this->fail('Expected LimitExceededException');
        } catch (LimitExceededException $e) {
            // The first call (at 1000) leaves the 60s window at 1060; it is now 1015
            $this->assertEqualsWithDelta(45.0, $e->getRetryAfter(), 0.001);
            $this->assertSame(429, $e->getHttpStatusCode());
            $this->assertSame('LIMIT_EXCEEDED', $e->getProviderErrorCode());
            $this->assertSame(['type' => 'requests', 'key' => 'global', 'max' => 2, 'window' => 60, 'used' => 2], $e->getContext());
        }

        // The blocked call never reached the provider
        $this->assertCount(2, $this->requests);

        $this->now += 46;
        $provider->chat('four');
        $this->assertCount(3, $this->requests);
    }

    public function testTokenLimitCountsReportedUsage(): void
    {
        $limit = Limit::tokens(100, 3600, $this->dir, clock: $this->clock());
        $provider = $this->anthropic([$this->reply(50, 30), $this->reply(20, 10), $this->reply()], ['limits' => [$limit]]);

        $provider->chat('uses 80');
        $this->now += 100;
        // Still under the limit before sending, so this call is allowed and goes over it
        $provider->chat('uses 30');

        $this->assertSame(110, $limit->getUsed());

        try {
            $provider->chat('blocked');
            $this->fail('Expected LimitExceededException');
        } catch (LimitExceededException $e) {
            // Dropping the first 80 tokens (at 1000) is enough; they leave the window at 4600, now is 1100
            $this->assertEqualsWithDelta(3500.0, $e->getRetryAfter(), 0.001);
            $this->assertStringContainsString('100 tokens per 3600 seconds', $e->getMessage());
        }
    }

    public function testRetriesAreNotCountedTwice(): void
    {
        $limit = Limit::requests(1, 60, $this->dir, clock: $this->clock());
        $provider = $this->anthropic([
            $this->jsonResponse(['error' => ['message' => 'Overloaded']], 529, ['retry-after-ms' => '0']),
            $this->reply(),
        ], ['limits' => [$limit]]);

        $provider->chat('Hi');

        $this->assertCount(2, $this->requests);
        $this->assertSame(1, $limit->getUsed());
    }

    public function testModerationIsBlockedButNotCounted(): void
    {
        $limit = Limit::requests(1, 60, $this->dir, clock: $this->clock());
        $provider = new OpenAIProvider(['api_key' => 'k', 'limits' => [$limit]], $this->routingHttpFactory([
            'POST https://api.openai.com/v1/moderations' => ['results' => [['flagged' => false]]],
            'POST https://api.openai.com/v1/chat/completions' => ['choices' => [['message' => ['content' => 'hi'], 'finish_reason' => 'stop']]],
        ]));

        $provider->chat('Hello');
        $this->assertSame(1, $limit->getUsed());

        $this->expectException(LimitExceededException::class);

        try {
            $provider->chat('Again');
        } finally {
            // Blocked before the moderation request of the second call
            $this->assertCount(2, $this->requests);
        }
    }

    public function testKeysScopeLimitsPerUser(): void
    {
        $user = 'alice';
        $limit = Limit::requests(1, 60, $this->dir, key: function () use (&$user): string {
            return 'user:' . $user;
        }, clock: $this->clock());

        $provider = $this->anthropic([$this->reply(), $this->reply()], ['limits' => [$limit]]);

        $provider->chat('alice 1');

        $user = 'bob';
        $provider->chat('bob 1');

        $user = 'alice';
        try {
            $provider->chat('alice 2');
            $this->fail('Expected LimitExceededException');
        } catch (LimitExceededException $e) {
            $this->assertSame('user:alice', $e->getContext()['key']);
        }
    }

    public function testOneLimitCanBeSharedByProviders(): void
    {
        $limit = Limit::requests(2, 60, $this->dir, clock: $this->clock());

        $anthropic = $this->anthropic([$this->reply()]);
        $anthropic->addLimit($limit);

        $openai = new OpenAIProvider(['api_key' => 'k', 'moderate_input' => false], $this->routingHttpFactory([
            'POST https://api.openai.com/v1/chat/completions' => ['choices' => [['message' => ['content' => 'hi'], 'finish_reason' => 'stop']]],
        ]));
        $openai->addLimit($limit);

        $anthropic->chat('one');
        $openai->chat('two');

        $this->expectException(LimitExceededException::class);
        $openai->chat('three');
    }

    public function testStreamingIsCounted(): void
    {
        $limit = Limit::tokens(10, 60, $this->dir, clock: $this->clock());
        $transport = new FakeStreamTransport([
            "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"usage\":{\"input_tokens\":8,\"output_tokens\":1}}}\n\n",
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"hi\"}}\n\n",
            "event: message_delta\ndata: {\"type\":\"message_delta\",\"delta\":{\"stop_reason\":\"end_turn\"},\"usage\":{\"output_tokens\":4}}\n\n",
        ]);

        $provider = new AnthropicProvider(['api_key' => 'k', 'stream_transport' => $transport, 'limits' => [$limit]]);
        iterator_to_array($provider->chatStream('Hi'));

        $this->assertSame(12, $limit->getUsed());

        $this->expectException(LimitExceededException::class);
        iterator_to_array($provider->chatStream('Again'));
    }

    public function testBlockedRequestsAreLoggedToHooks(): void
    {
        $errors = [];
        $limit = Limit::requests(1, 60, $this->dir, clock: $this->clock());
        $provider = $this->anthropic([$this->reply()], [
            'limits' => [$limit],
            'hooks' => ['on_error' => function (array $request, \Throwable $e) use (&$errors) {
                $errors[] = get_class($e);
            }],
        ]);

        $provider->chat('one');

        try {
            $provider->chat('two');
        } catch (LimitExceededException $e) {
        }

        $this->assertSame([LimitExceededException::class], $errors);
    }

    public function testUsageIsSharedThroughTheDirectory(): void
    {
        // Two limit objects on the same directory, like two separate PHP requests
        $first = Limit::requests(2, 60, $this->dir, clock: $this->clock());
        $second = Limit::requests(2, 60, $this->dir, clock: $this->clock());

        $response = new Response('ok', 'Test');
        $first->record([], $response);
        $second->record([], $response);

        $this->assertSame(2, $first->getUsed());

        try {
            $first->check(['provider' => 'Test']);
            $this->fail('Expected LimitExceededException');
        } catch (LimitExceededException $e) {
            $this->assertSame('Test', $e->getProvider());
        }

        $this->now += 61;
        $second->check(['provider' => 'Test']);
        $this->assertSame(0, $second->getUsed());
    }

    public function testDefaultDirectoryIsTheSystemTempDirectory(): void
    {
        $key = 'test-' . uniqid();
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'joomla-ai-limits' . DIRECTORY_SEPARATOR
            . hash('sha256', 'joomla-ai:requests:60:' . $key) . '.json';

        try {
            $limit = Limit::requests(5, 60, key: $key);
            $limit->record([], new Response('ok', 'Test'));

            $this->assertFileExists($file);
            $this->assertSame(1, Limit::requests(5, 60, key: $key)->getUsed());
        } finally {
            @unlink($file);
        }
    }

    public function testInvalidLimitThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Limit::tokens(0, 60, $this->dir);
    }

    private function clock(): \Closure
    {
        return fn (): float => $this->now;
    }

    private function anthropic(array $responses, array $options = []): AnthropicProvider
    {
        return new AnthropicProvider($options + ['api_key' => 'k'], $this->routingHttpFactory([
            self::ANTHROPIC => ['sequence' => $responses],
        ]));
    }

    private function reply(int $input = 1, int $output = 1): array
    {
        return [
            'content' => [['type' => 'text', 'text' => 'ok']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => $input, 'output_tokens' => $output],
        ];
    }
}
