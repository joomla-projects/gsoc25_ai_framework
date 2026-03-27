<?php

namespace Joomla\AI\Tests\GrokProvider;

use Joomla\AI\Provider\GrokProvider;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
    public function testChatUsesDefaultModelWhenNotProvided(): void
    {
        // Test 1: Test chat uses default model when no model option supplied

        $chatResponse = $this->createJsonResponse([
            'id' => 'chat-default',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'grok-3',
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 7, 'total_tokens' => 12],
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => 'Hello from default model'],
                'finish_reason' => 'stop',
            ]],
        ]);

        $provider = $this->createProviderWithPostResponses($chatResponse);
        $provider->setDefaultModel('grok-3');

        $response = $provider->chat('Which model are you using?');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello from default model', $response->getContent());
        $this->assertSame('grok-3', $response->getMetadata()['model']);
    }

    public function testChatOverridesDefaultModelWhenProvided(): void
    {
        // Test 2: Test chat overrides default model when explicit model provided

        $chatResponse = $this->createJsonResponse([
            'id' => 'chat-override',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'grok-3-fast',
            'usage' => ['prompt_tokens' => 6, 'completion_tokens' => 8, 'total_tokens' => 14],
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => 'Override acknowledged'],
                'finish_reason' => 'stop',
            ]],
        ]);

        $provider = $this->createProviderWithPostResponses($chatResponse);
        $provider->setDefaultModel('grok-3-mini-fast');

        $response = $provider->chat('Use a different model', ['model' => 'grok-3-fast']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Override acknowledged', $response->getContent());
        $this->assertSame('grok-3-fast', $response->getMetadata()['model']);
    }

    public function testUnsetDefaultModelRevertsToProviderDefault(): void
    {
        // Test 3: Test unsetDefaultModel reverts to provider default model

        $chatResponse = $this->createJsonResponse([
            'id' => 'chat-unset',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'grok-3-mini-fast',
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5, 'total_tokens' => 10],
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => 'Back to default'],
                'finish_reason' => 'stop',
            ]],
        ]);

        $provider = $this->createProviderWithPostResponses($chatResponse);
        $provider->setDefaultModel('grok-3');
        $provider->unsetDefaultModel();

        $this->assertNull($provider->getDefaultModel());

        $response = $provider->chat('Hello');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testIsModelCapableReturnsCorrectResults(): void
    {
        // Test 4: Test isModelCapable returns correct results for each capability type

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $provider = new GrokProvider(['api_key' => 'test-api-key'], $httpFactoryMock);

        $this->assertTrue($provider->isModelCapable('grok-3', 'chat'));
        $this->assertTrue($provider->isModelCapable('grok-3-fast', 'chat'));
        $this->assertTrue($provider->isModelCapable('grok-2-vision', 'vision'));
        $this->assertTrue($provider->isModelCapable('grok-2-image', 'image'));
        $this->assertTrue($provider->isModelCapable('v1', 'embedding'));

        $this->assertFalse($provider->isModelCapable('grok-3', 'image'));
        $this->assertFalse($provider->isModelCapable('grok-2-image', 'chat'));
        $this->assertFalse($provider->isModelCapable('grok-3', 'nonexistent'));
    }

    public function testGetModelListsMethods(): void
    {
        // Test 5: Test model list methods return non-empty arrays with expected models

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $provider = new GrokProvider(['api_key' => 'test-api-key'], $httpFactoryMock);

        $this->assertNotEmpty($provider->getChatModels());
        $this->assertNotEmpty($provider->getVisionModels());
        $this->assertNotEmpty($provider->getImageModels());
        $this->assertNotEmpty($provider->getEmbeddingModels());

        $this->assertContains('grok-3', $provider->getChatModels());
        $this->assertContains('grok-2-vision', $provider->getVisionModels());
        $this->assertContains('grok-2-image', $provider->getImageModels());
        $this->assertContains('v1', $provider->getEmbeddingModels());
    }

    private function createProviderWithPostResponses(HttpResponse ...$responses): GrokProvider
    {
        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock = $this->createMock(Http::class);

        $httpFactoryMock->method('getHttp')->willReturn($httpClientMock);

        if (!empty($responses)) {
            $httpClientMock->method('post')->willReturnOnConsecutiveCalls(...$responses);
        }

        return new GrokProvider(['api_key' => 'test-api-key'], $httpFactoryMock);
    }

    private function createJsonResponse(array $payload, int $status = 200): HttpResponse
    {
        $response = new HttpResponse('php://memory', $status, ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($payload));

        return $response;
    }
}
