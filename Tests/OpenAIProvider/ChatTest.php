<?php

namespace Joomla\AI\Tests\OpenAIProvider;

use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\RateLimitException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\AI\Provider\OpenAIProvider;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

class ChatTest extends TestCase
{
    public function testSimpleChatCompletion()
    {
        // Test 1: Test chat method for successful completion

        $fakeChatResponseBody = json_encode([
            'id'      => 'chatcmpl-test',
            'object'  => 'chat.completion',
            'created' => time(),
            'model'   => 'gpt-4o-mini',
            'usage'   => ['prompt_tokens' => 5, 'completion_tokens' => 7, 'total_tokens' => 12],
            'choices' => [
                [
                    'index'         => 0,
                    'message'       => ['role' => 'assistant', 'content' => 'All good here!'],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $fakeModerationResponseBody = json_encode([
            'id'      => 'modr-test',
            'model'   => 'text-moderation-001',
            'results' => [
                [
                    'flagged'     => false,
                ],
            ],
        ]);

        $httpFactoryMock  = $this->createMock(HttpFactory::class);
        $httpClientMock   = $this->createMock(Http::class);

        $httpFactoryMock->method('getHttp')->willReturn($httpClientMock);

        $moderationResponse = new HttpResponse('php://memory', 200, ['Content-Type' => 'application/json']);
        $moderationResponse->getBody()->write($fakeModerationResponseBody);

        $chatResponse = new HttpResponse('php://memory', 200, ['Content-Type' => 'application/json']);
        $chatResponse->getBody()->write($fakeChatResponseBody);

        $httpClientMock->method('post')->willReturnOnConsecutiveCalls($moderationResponse, $chatResponse);

        $provider = new OpenAIProvider(['api_key' => 'test-api-key'], $httpFactoryMock);
        $response = $provider->chat('Hello! How are you?', ['model' => 'gpt-4o-mini']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('All good here!', $response->getContent());
        $metadata = $response->getMetadata();
        $this->assertArrayHasKey('model', $metadata);
        $this->assertArrayHasKey('usage', $metadata);
    }

    public function testChatRaisesProviderException()
    {
        // Test 2: Test chat method raises ProviderException on server error

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock  = $this->createMock(Http::class);
        $httpFactoryMock->method('getHttp')->with([])->willReturn($httpClientMock);

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-test',
            'model' => 'text-moderation-001',
            'results' => [['flagged' => false]],
        ]);

        $serverErrorResponse = $this->createJsonResponse([
            'error' => [
                'message' => 'Internal server error',
                'type'    => 'server_error',
            ],
        ], 500);

        $httpClientMock->method('post')->willReturnOnConsecutiveCalls($moderationResponse, $serverErrorResponse);

        $provider = new OpenAIProvider(['api_key' => 'test-api-key'], $httpFactoryMock);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Internal server error');
        $provider->chat('Hello!', ['model' => 'gpt-4o-mini']);
    }

    public function testChatRaisesAuthenticationException()
    {
        // Test 3: Test chat method raises AuthenticationException on unauthorized access

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock  = $this->createMock(Http::class);
        $httpFactoryMock->method('getHttp')->with([])->willReturn($httpClientMock);

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-test',
            'model' => 'text-moderation-001',
            'results' => [['flagged' => false]],
        ]);

        $unauthorizedResponse = $this->createJsonResponse([
            'error' => [
                'message' => 'Incorrect API key provided',
                'type'    => 'invalid_request_error',
                'code'    => 'invalid_api_key',
            ],
        ], 401);

        $httpClientMock->method('post')->willReturnOnConsecutiveCalls($moderationResponse, $unauthorizedResponse);

        $provider = new OpenAIProvider(['api_key' => 'bad-key'], $httpFactoryMock);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Incorrect API key provided');
        $provider->chat('Hello!', ['model' => 'gpt-4o-mini']);
    }

    public function testChatRaisesRateLimitException()
    {
        // Test 4: Test chat method raises RateLimitException on too many requests

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock  = $this->createMock(Http::class);
        $httpFactoryMock->method('getHttp')->with([])->willReturn($httpClientMock);

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-test',
            'model' => 'text-moderation-001',
            'results' => [['flagged' => false]],
        ]);

        $rateLimitResponse = $this->createJsonResponse([
            'error' => [
                'message' => 'Rate limit exceeded',
                'type'    => 'rate_limit_error',
            ],
        ], 429);

        $httpClientMock->method('post')->willReturnOnConsecutiveCalls($moderationResponse, $rateLimitResponse);

        $provider = new OpenAIProvider(['api_key' => 'test-api-key'], $httpFactoryMock);

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Rate limit exceeded');
        $provider->chat('Hello!', ['model' => 'gpt-4o-mini']);
    }

    public function testChatRaisesUnserializableResponse()
    {
        // Test 5: Test chat method raises UnserializableResponseException on invalid JSON

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock  = $this->createMock(Http::class);
        $httpFactoryMock->method('getHttp')->with([])->willReturn($httpClientMock);

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-test',
            'model' => 'text-moderation-001',
            'results' => [['flagged' => false]],
        ]);

        $invalidJson = new HttpResponse('php://memory', 200, ['Content-Type' => 'application/json']);
        $invalidJson->getBody()->write('{ not valid json');

        $httpClientMock->method('post')->willReturnOnConsecutiveCalls($moderationResponse, $invalidJson);

        $provider = new OpenAIProvider(['api_key' => 'test-api-key'], $httpFactoryMock);

        $this->expectException(UnserializableResponseException::class);
        $this->expectExceptionMessage('Syntax error');
        $provider->chat('Hello!', ['model' => 'gpt-4o-mini']);
    }

    public function testChatHandlesBase64ContentInChoices()
    {
        // Test 6: Test chat method handles base64 encoded content in choices metadata

        $rawAudio = 'fake-audio-bytes';
        $encodedAudio = base64_encode($rawAudio);

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-test',
            'model' => 'text-moderation-001',
            'results' => [['flagged' => false]],
        ]);

        $chatResponse = $this->createJsonResponse([
            'id' => 'chatcmpl-audio',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4o-mini',
            'usage' => [
                'prompt_tokens' => 9,
                'completion_tokens' => 12,
                'total_tokens' => 21,
            ],
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $encodedAudio,
                        'mime_type' => 'audio/wav',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock  = $this->createMock(Http::class);
        $httpFactoryMock->method('getHttp')->with([])->willReturn($httpClientMock);
        $httpClientMock->method('post')->willReturnOnConsecutiveCalls($moderationResponse, $chatResponse);

        $provider = new OpenAIProvider(['api_key' => 'test-api-key'], $httpFactoryMock);

        $response = $provider->chat('Please return audio as base64.', ['model' => 'gpt-4o-mini']);

        $this->assertSame($encodedAudio, $response->getContent());

        $metadata = $response->getMetadata();
        $this->assertSame('gpt-4o-mini', $metadata['model']);
        $this->assertSame($encodedAudio, $metadata['choices'][0]['message']['content']);
        $this->assertSame('audio/wav', $metadata['choices'][0]['message']['mime_type']);

        $decoded = base64_decode($response->getContent(), true);
        $this->assertNotFalse($decoded);
        $this->assertSame($rawAudio, $decoded);
    }

    private function createJsonResponse(array $payload, int $status = 200): HttpResponse
    {
        $response = new HttpResponse('php://memory', $status, ['Content-Type' => 'application/json']);
        $stream   = $response->getBody();
        $stream->write(json_encode($payload));

        return $response;
    }
}
