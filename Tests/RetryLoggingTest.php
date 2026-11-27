<?php

namespace Joomla\AI\Tests;

use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\QuotaExceededException;
use Joomla\AI\Exception\RateLimitException;
use Joomla\AI\Http\StreamTransportInterface;
use Joomla\AI\Provider\AnthropicProvider;
use Joomla\AI\Tests\Stub\FakeStreamTransport;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class RetryLoggingTest extends TestCase
{
    /** @var float[] Delays passed to pause() */
    private array $pauses = [];

    public function testRetriesRateLimitHonouringRetryAfter(): void
    {
        [$provider, $calls] = $this->provider([
            $this->response(429, ['error' => ['message' => 'Rate limit exceeded']], ['Retry-After' => '3']),
            $this->response(200, $this->okBody()),
        ]);

        $response = $provider->chat('Hi');

        $this->assertSame('ok', $response->getContent());
        $this->assertSame(2, $calls->count);
        $this->assertSame([3.0], $this->pauses);
    }

    public function testRateLimitThrowsRateLimitExceptionWhenRetriesRunOut(): void
    {
        [$provider, $calls] = $this->provider([
            $this->response(429, ['error' => ['message' => 'Rate limit exceeded']], ['Retry-After' => '1']),
            $this->response(429, ['error' => ['message' => 'Rate limit exceeded']], ['Retry-After' => '1']),
        ], ['max_retries' => 1]);

        try {
            $provider->chat('Hi');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->getHttpStatusCode());
            $this->assertStringContainsString('Rate limit exceeded', $e->getMessage());
        }

        $this->assertSame(2, $calls->count);
        $this->assertSame([1.0], $this->pauses);
    }

    public function testAny429WithoutQuotaWordingIsARateLimit(): void
    {
        [$provider] = $this->provider([
            $this->response(429, ['error' => ['message' => 'Please slow down']]),
        ], ['max_retries' => 0]);

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Please slow down');

        $provider->chat('Hi');
    }

    public function testRetryAfterMsAndHttpDate(): void
    {
        [$provider] = $this->provider([
            $this->response(529, ['error' => ['message' => 'Overloaded']], ['retry-after-ms' => '250']),
            $this->response(503, ['error' => ['message' => 'Unavailable']], ['Retry-After' => gmdate('D, d M Y H:i:s', time() + 120) . ' GMT']),
            $this->response(200, $this->okBody()),
        ], ['retry_max_delay' => 10]);

        $provider->chat('Hi');

        $this->assertSame(0.25, $this->pauses[0]);
        // HTTP date two minutes ahead, capped by retry_max_delay
        $this->assertSame(10.0, $this->pauses[1]);
    }

    public function testExponentialBackoffWithoutRetryAfterAndGivesUp(): void
    {
        [$provider, $calls] = $this->provider([
            $this->response(500, ['error' => ['message' => 'Internal error']]),
            $this->response(502, ['error' => ['message' => 'Bad gateway']]),
            $this->response(500, ['error' => ['message' => 'Still broken']]),
        ], ['max_retries' => 2, 'retry_base_delay' => 1]);

        try {
            $provider->chat('Hi');
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
            $this->assertStringContainsString('Still broken', $e->getMessage());
        }

        $this->assertSame(3, $calls->count);
        $this->assertCount(2, $this->pauses);
        // base * 2^attempt with 0.8-1.0 jitter
        $this->assertGreaterThanOrEqual(0.8, $this->pauses[0]);
        $this->assertLessThanOrEqual(1.0, $this->pauses[0]);
        $this->assertGreaterThanOrEqual(1.6, $this->pauses[1]);
        $this->assertLessThanOrEqual(2.0, $this->pauses[1]);
    }

    public function testQuotaErrorsAndClientErrorsAreNotRetried(): void
    {
        [$provider, $calls] = $this->provider([
            $this->response(429, ['error' => ['message' => 'You exceeded your current quota']]),
        ]);

        try {
            $provider->chat('Hi');
            $this->fail('Expected QuotaExceededException');
        } catch (QuotaExceededException $e) {
        }

        [$provider400, $calls400] = $this->provider([
            $this->response(400, ['error' => ['message' => 'Bad request']]),
        ]);

        try {
            $provider400->chat('Hi');
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
        }

        $this->assertSame(1, $calls->count);
        $this->assertSame(1, $calls400->count);
        $this->assertSame([], $this->pauses);
    }

    public function testTransportErrorIsRetried(): void
    {
        [$provider, $calls] = $this->provider([
            new \RuntimeException('Connection reset'),
            $this->response(200, $this->okBody()),
        ]);

        $this->assertSame('ok', $provider->chat('Hi')->getContent());
        $this->assertSame(2, $calls->count);
    }

    public function testStreamingRetriesBeforeFirstChunk(): void
    {
        $transports = [
            new FakeStreamTransport(['{"error":{"message":"Overloaded"}}'], 529),
            new FakeStreamTransport([
                "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"hi\"}}\n\n",
            ]),
        ];

        $transport = new class ($transports) implements StreamTransportInterface {
            public function __construct(private array $transports)
            {
            }

            public function stream(string $method, string $url, string $body, array $headers = [], ?int $timeout = null): \Generator
            {
                return yield from array_shift($this->transports)->stream($method, $url, $body, $headers, $timeout);
            }
        };

        [$provider] = $this->provider([], ['stream_transport' => $transport]);

        $stream = $provider->chatStream('Hi');

        $this->assertSame(['hi'], iterator_to_array($stream, false));
        $this->assertCount(1, $this->pauses);
    }

    public function testStreamingDoesNotRetryAfterOutputStarted(): void
    {
        $attempts = 0;

        $transport = new class ($attempts) implements StreamTransportInterface {
            public function __construct(private int &$attempts)
            {
            }

            public function stream(string $method, string $url, string $body, array $headers = [], ?int $timeout = null): \Generator
            {
                $this->attempts++;

                yield "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"partial\"}}\n\n";

                throw new \RuntimeException('Connection reset');
            }
        };

        [$provider] = $this->provider([], ['stream_transport' => $transport]);

        $received = [];

        try {
            foreach ($provider->chatStream('Hi') as $delta) {
                $received[] = $delta;
            }
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('Connection reset', $e->getMessage());
        }

        $this->assertSame(['partial'], $received);
        $this->assertSame(1, $attempts);
        $this->assertSame([], $this->pauses);
    }

    public function testLoggerAndHooks(): void
    {
        $logger = new class () extends AbstractLogger {
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $replacements = [];
                foreach ($context as $key => $value) {
                    $replacements['{' . $key . '}'] = is_scalar($value) ? (string) $value : '';
                }

                $this->records[] = [$level, strtr((string) $message, $replacements)];
            }
        };

        $events = [];

        [$provider] = $this->provider([
            $this->response(429, ['error' => ['message' => 'Rate limit exceeded']], ['Retry-After' => '1']),
            $this->response(200, $this->okBody()),
        ], [
            'logger' => $logger,
            'hooks' => ['before_request' => function (array $request) use (&$events) {
                $events[] = 'before:' . $request['attempt'];
            }],
        ]);

        $provider->addHook('after_response', function (array $request, $response) use (&$events) {
            $events[] = 'after:' . $response->getStatusCode();
        });
        $provider->addHook('on_retry', function (array $request, float $delay) use (&$events) {
            $events[] = 'retry:' . $delay;
        });

        $provider->chat('Hi');

        $this->assertSame(['before:0', 'after:429', 'retry:1', 'before:1', 'after:200'], $events);

        $this->assertSame(['debug', 'info', 'warning', 'debug', 'info'], array_column($logger->records, 0));
        $this->assertStringStartsWith('Anthropic POST https://api.anthropic.com/v1/messages returned 200 in ', $logger->records[4][1]);

        // The API key never reaches the logs
        foreach ($logger->records as [, $message]) {
            $this->assertStringNotContainsString('secret-key', $message);
        }
    }

    public function testOnErrorHookReceivesFinalException(): void
    {
        $errors = [];

        [$provider] = $this->provider([
            $this->response(401, ['error' => ['message' => 'Invalid API key']]),
        ], ['hooks' => ['on_error' => function (array $request, \Throwable $e) use (&$errors) {
            $errors[] = get_class($e);
        }]]);

        try {
            $provider->chat('Hi');
        } catch (\Exception $e) {
        }

        $this->assertSame([AuthenticationException::class], $errors);
    }

    /**
     * Anthropic provider whose POSTs return (or throw) the given items in order, with pause() recorded.
     *
     * @return  array{0: AnthropicProvider, 1: object}  The provider and a call counter
     */
    private function provider(array $responses, array $options = []): array
    {
        $calls = new \stdClass();
        $calls->count = 0;

        $http = $this->createStub(Http::class);
        $http->method('post')->willReturnCallback(function () use (&$responses, $calls) {
            $calls->count++;
            $next = array_shift($responses);

            if ($next instanceof \Throwable) {
                throw $next;
            }

            return $next;
        });

        $factory = $this->createStub(HttpFactory::class);
        $factory->method('getHttp')->willReturn($http);

        $provider = new class ($options + ['api_key' => 'secret-key'], $factory, $this->pauses) extends AnthropicProvider {
            private array $pausesRef;

            public function __construct($options, $factory, array &$pauses)
            {
                parent::__construct($options, $factory);
                $this->pausesRef = &$pauses;
            }

            protected function pause(float $seconds): void
            {
                $this->pausesRef[] = $seconds;
            }
        };

        return [$provider, $calls];
    }

    private function okBody(): array
    {
        return ['content' => [['type' => 'text', 'text' => 'ok']], 'stop_reason' => 'end_turn'];
    }

    private function response(int $status, array $body, array $headers = []): HttpResponse
    {
        $response = new HttpResponse('php://memory', $status, $headers + ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($body));
        $response->getBody()->rewind();

        return $response;
    }
}
