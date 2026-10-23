<?php

namespace Joomla\AI\Tests\AnthropicProvider;

use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\RateLimitException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Provider\AnthropicProvider;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

class ChatTest extends TestCase
{
    public function testChatReturnsSuccessfulResponse(): void
    {
        $httpFactoryMock = $this->createStub(HttpFactory::class);
        $httpClientMock = $this->createMock(Http::class);
        $httpFactoryMock->method('getHttp')->willReturn($httpClientMock);

        $chatResponse = $this->createJsonResponse([
            'id' => 'msg_123',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-3-haiku-20240307',
            'content' => [
                ['type' => 'text', 'text' => 'Hello from Claude!'],
            ],
            'usage' => [
                'input_tokens' => 12,
                'output_tokens' => 9,
            ],
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
        ]);

        $httpClientMock->expects($this->once())->method('post')->with(
            'https://api.anthropic.com/v1/messages',
            $this->callback(function ($payload) {
                $decoded = json_decode($payload, true);
                $this->assertSame([['role' => 'user', 'content' => 'Hello there']], $decoded['messages']);
                return true;
            }),
            $this->callback(function ($headers) {
                $this->assertArrayHasKey('x-api-key', $headers);
                $this->assertSame('test-api-key', $headers['x-api-key']);
                return true;
            }),
            null
        )->willReturn($chatResponse);

        $provider = new AnthropicProvider(['api_key' => 'test-api-key', 'max_retries' => 0], $httpFactoryMock);
        $response = $provider->chat('Hello there');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello from Claude!', $response->getContent());

        $metadata = $response->getMetadata();
        $this->assertSame('claude-3-haiku-20240307', $metadata['model']);
        $this->assertSame('end_turn', $metadata['stop_reason']);
        $this->assertSame('Anthropic', $response->getProvider());
    }

    public function testChatStatusCodeReflectsMaxTokensStopReason(): void
    {
        $provider = $this->createProviderWithResponses([
            $this->createJsonResponse([
                'id' => 'msg_limit',
                'type' => 'message',
                'role' => 'assistant',
                'model' => 'claude-3-sonnet',
                'content' => [['type' => 'text', 'text' => 'Truncated reply']],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 200],
                'stop_reason' => 'max_tokens',
                'stop_sequence' => null,
            ]),
        ]);

        $response = $provider->chat('Continue until you hit the limit.');

        $this->assertSame(429, $response->getStatusCode());
        $metadata = $response->getMetadata();
        $this->assertSame('max_tokens', $metadata['stop_reason']);
    }

    public function testChatThrowsProviderExceptionWhenErrorReturned(): void
    {
        $provider = $this->createProviderWithResponses([
            $this->createJsonResponse([
                'error' => [
                    'message' => 'Model overloaded',
                    'type' => 'overloaded_error',
                ],
            ]),
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Model overloaded');

        $provider->chat('Hello?');
    }

    public function testChatThrowsProviderExceptionOnHttpFailure(): void
    {
        $provider = $this->createProviderWithResponses([
            $this->createJsonResponse([
                'message' => 'Internal server error',
            ], 500),
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Internal server error');

        $provider->chat('Trigger failure');
    }

    public function testChatThrowsRateLimitExceptionWhenRateLimited(): void
    {
        $provider = $this->createProviderWithResponses([
            $this->createJsonResponse([
                'message' => 'Rate limit exceeded',
            ], 429),
        ]);

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $provider->chat('Spam request');
    }

    public function testChatThrowsAuthenticationExceptionWhenApiKeyMissing(): void
    {
        $httpFactoryMock = $this->createStub(HttpFactory::class);

        $provider = new AnthropicProvider([], $httpFactoryMock);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Anthropic API key not configured. Provide the api_key option.');

        $provider->chat('Hi');
    }

    public function testChatThrowsUnserializableResponseExceptionForInvalidJson(): void
    {
        $invalidJsonResponse = new HttpResponse('php://memory', 200, ['Content-Type' => 'application/json']);
        $invalidJsonResponse->getBody()->write('{invalid');

        $provider = $this->createProviderWithResponses([$invalidJsonResponse]);

        $this->expectException(UnserializableResponseException::class);
        $this->expectExceptionMessage('Syntax error');

        $provider->chat('Return malformed payload');
    }

    public function testChatThrowsUnserializableResponseExceptionWhenContentMissing(): void
    {
        $provider = $this->createProviderWithResponses([
            $this->createJsonResponse([
                'id' => 'msg_broken',
                'type' => 'message',
            ]),
        ]);

        $this->expectException(UnserializableResponseException::class);
        $this->expectExceptionMessage("Missing field 'content'");

        $provider->chat('Hello?');
    }

    public function testChatJoinsTextBlocksAndSkipsThinking(): void
    {
        $provider = $this->createProviderWithResponses([
            $this->createJsonResponse([
                'id' => 'msg_thinking',
                'type' => 'message',
                'role' => 'assistant',
                'model' => 'claude-opus-5-5',
                'content' => [
                    ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
                    ['type' => 'text', 'text' => 'Hello '],
                    ['type' => 'text', 'text' => 'there'],
                ],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 7],
                'stop_reason' => 'end_turn',
            ]),
        ]);

        $response = $provider->chat('Hi');

        $this->assertSame('Hello there', $response->getContent());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertSame(['input_tokens' => 5, 'output_tokens' => 7, 'total_tokens' => 12], $response->getUsage());
    }

    private function createProviderWithResponses(array $responses): AnthropicProvider
    {
        $httpFactoryMock = $this->createStub(HttpFactory::class);
        $httpClientMock = $this->createStub(Http::class);

        $httpFactoryMock->method('getHttp')->willReturn($httpClientMock);

        $httpClientMock->method('post')->willReturnOnConsecutiveCalls(...$responses);

        return new AnthropicProvider(['api_key' => 'test-api-key', 'max_retries' => 0], $httpFactoryMock);
    }

    private function createJsonResponse(array $payload, int $status = 200): HttpResponse
    {
        $response = new HttpResponse('php://memory', $status, ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($payload));

        return $response;
    }
}
