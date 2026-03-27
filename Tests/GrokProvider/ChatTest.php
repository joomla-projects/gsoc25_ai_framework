<?php

namespace Joomla\AI\Tests\GrokProvider;

use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\RateLimitException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Provider\GrokProvider;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

class ChatTest extends TestCase
{
    public function testChatReturnsSuccessfulResponse(): void
    {
        // Test 1: Test chat method for successful completion

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock = $this->createMock(Http::class);
        $httpFactoryMock->method('getHttp')->with([])->willReturn($httpClientMock);

        $chatResponse = $this->createJsonResponse([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'grok-3-mini-fast',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello from Grok!',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 12,
                'completion_tokens' => 9,
                'total_tokens' => 21,
            ],
        ]);

        $httpClientMock->expects($this->once())->method('post')->with(
            'https://api.x.ai/v1/chat/completions',
            $this->callback(function ($payload) {
                $decoded = json_decode($payload, true);
                $this->assertSame([['role' => 'user', 'content' => 'Hello there']], $decoded['messages']);
                return true;
            }),
            $this->callback(function ($headers) {
                $this->assertArrayHasKey('Authorization', $headers);
                $this->assertSame('Bearer test-api-key', $headers['Authorization']);
                return true;
            }),
            null
        )->willReturn($chatResponse);

        $provider = new GrokProvider(['api_key' => 'test-api-key'], $httpFactoryMock);
        $response = $provider->chat('Hello there');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello from Grok!', $response->getContent());

        $metadata = $response->getMetadata();
        $this->assertSame('grok-3-mini-fast', $metadata['model']);
        $this->assertSame('stop', $metadata['finish_reason']);
        $this->assertSame('Grok', $response->getProvider());
    }

    public function testChatStatusCodeReflectsLengthFinishReason(): void
    {
        // Test 2: Test chat method returns 206 status code when finish_reason is 'length'

        $provider = $this->createProviderWithResponses([
            $this->createJsonResponse([
                'id' => 'chatcmpl-limit',
                'object' => 'chat.completion',
                'created' => 1234567890,
                'model' => 'grok-3-mini-fast',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Truncated reply',
                        ],
                        'finish_reason' => 'length',
                    ],
                ],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 200, 'total_tokens' => 300],
            ]),
        ]);

        $response = $provider->chat('Continue until you hit the limit.');

        $this->assertSame(206, $response->getStatusCode());
        $metadata = $response->getMetadata();
        $this->assertSame('length', $metadata['finish_reason']);
    }

    public function testChatThrowsProviderExceptionWhenErrorReturned(): void
    {
        // Test 3: Test chat method raises ProviderException when API returns error

        $provider = $this->createProviderWithResponses([
            $this->createJsonResponse([
                'error' => [
                    'message' => 'Model overloaded',
                    'type' => 'server_error',
                ],
            ]),
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Model overloaded');

        $provider->chat('Hello?');
    }

    public function testChatThrowsProviderExceptionOnHttpFailure(): void
    {
        // Test 4: Test chat method raises ProviderException on HTTP 500 server error

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
        // Test 5: Test chat method raises RateLimitException on too many requests

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
        // Test 6: Test chat method raises AuthenticationException when API key is missing

        $httpFactoryMock = $this->createMock(HttpFactory::class);

        $provider = new GrokProvider([], $httpFactoryMock);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('xAI API key not configured. Set XAI_API_KEY environment variable or provide api_key option.');

        $provider->chat('Hi');
    }

    public function testChatThrowsUnserializableResponseExceptionForInvalidJson(): void
    {
        // Test 7: Test chat method raises UnserializableResponseException on invalid JSON

        $invalidJsonResponse = new HttpResponse('php://memory', 200, ['Content-Type' => 'application/json']);
        $invalidJsonResponse->getBody()->write('{invalid');

        $provider = $this->createProviderWithResponses([$invalidJsonResponse]);

        $this->expectException(UnserializableResponseException::class);
        $this->expectExceptionMessage('Syntax error');

        $provider->chat('Return malformed payload');
    }

    private function createProviderWithResponses(array $responses): GrokProvider
    {
        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock = $this->createMock(Http::class);

        $httpFactoryMock->method('getHttp')->with([])->willReturn($httpClientMock);

        $httpClientMock->method('post')->willReturnOnConsecutiveCalls(...$responses);

        return new GrokProvider(['api_key' => 'test-api-key'], $httpFactoryMock);
    }

    private function createJsonResponse(array $payload, int $status = 200): HttpResponse
    {
        $response = new HttpResponse('php://memory', $status, ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($payload));

        return $response;
    }
}
