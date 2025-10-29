<?php

use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\RateLimitException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Provider\AnthropicProvider;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

class AnthropicChatTest extends TestCase
{
    public function testChatReturnsSuccessfulResponse(): void
    {
        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock = $this->createMock(Http::class);
        $httpFactoryMock->method('getHttp')->with([])->willReturn($httpClientMock);

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

        $provider = new AnthropicProvider(['api_key' => 'test-api-key'], $httpFactoryMock);
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
        $httpFactoryMock = $this->createMock(HttpFactory::class);

        $provider = new AnthropicProvider([], $httpFactoryMock);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Anthropic API key not configured. Set ANTHROPIC_API_KEY environment variable or provide api_key option.');

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

    private function createProviderWithResponses(array $responses): AnthropicProvider
    {
        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock = $this->createMock(Http::class);

        $httpFactoryMock->method('getHttp')->with([])->willReturn($httpClientMock);

        $httpClientMock->method('post')->willReturnOnConsecutiveCalls(...$responses);

        return new AnthropicProvider(['api_key' => 'test-api-key'], $httpFactoryMock);
    }

    private function createJsonResponse(array $payload, int $status = 200): HttpResponse
    {
        $response = new HttpResponse('php://memory', $status, ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($payload));

        return $response;
    }
}
