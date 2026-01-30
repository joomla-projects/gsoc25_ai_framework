<?php

namespace Joomla\AI\Tests\OpenAIProvider;

use Joomla\AI\Provider\OpenAIProvider;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
    public function testChatUsesDefaultModelWhenNotProvided()
    {
        // Test 1: Test chat uses default model when no model option supplied

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-chat-default',
            'model' => 'omni-moderation-latest',
            'results' => [['flagged' => false]],
        ]);

        $chatResponse = $this->createJsonResponse([
            'id' => 'chat-default-model',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-3.5-turbo',
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 7, 'total_tokens' => 12],
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => 'Hello from default model'],
                'finish_reason' => 'stop',
            ]],
        ]);

        $provider = $this->createProviderWithPostResponses($moderationResponse, $chatResponse);
        $provider->setDefaultModel('gpt-3.5-turbo');

        $response = $provider->chat('Which model are you using?');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello from default model', $response->getContent());
        $this->assertSame('gpt-3.5-turbo', $response->getMetadata()['model']);
    }

    public function testChatOverridesDefaultModelWhenProvided()
    {
        // Test 2: Test chat overrides default model when explicit model provided

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-chat-override',
            'model' => 'omni-moderation-latest',
            'results' => [['flagged' => false]],
        ]);

        $chatResponse = $this->createJsonResponse([
            'id' => 'chat-override-model',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4o-mini',
            'usage' => ['prompt_tokens' => 6, 'completion_tokens' => 8, 'total_tokens' => 14],
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => 'Override acknowledged'],
                'finish_reason' => 'stop',
            ]],
        ]);

        $provider = $this->createProviderWithPostResponses($moderationResponse, $chatResponse);
        $provider->setDefaultModel('gpt-3.5-turbo');

        $response = $provider->chat('Use a different model please', ['model' => 'gpt-4o-mini']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Override acknowledged', $response->getContent());
        $this->assertSame('gpt-4o-mini', $response->getMetadata()['model']);
    }

    public function testGenerateImageUsesMethodDefaultAfterUnsetting()
    {
        // Test 3: Test generateImage uses method default after unsetting provider default

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-image-default',
            'model' => 'omni-moderation-latest',
            'results' => [['flagged' => false]],
        ]);

        $imageResponse = $this->createJsonResponse([
            'created' => time(),
            'model' => 'dall-e-2',
            'data' => [[
                'b64_json' => base64_encode('image-data'),
            ]],
        ]);

        $provider = $this->createProviderWithPostResponses($moderationResponse, $imageResponse);
        $provider->setDefaultModel('gpt-4o');
        $provider->unsetDefaultModel();

        $response = $provider->generateImage('A playful kitten in space');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('dall-e-2', $response->getMetadata()['model']);
        $this->assertSame(base64_encode('image-data'), $response->getContent());
    }

    private function createProviderWithPostResponses(HttpResponse ...$responses): OpenAIProvider
    {
        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock = $this->createMock(Http::class);

        $httpFactoryMock->method('getHttp')->willReturn($httpClientMock);

        if (!empty($responses)) {
            $httpClientMock->method('post')->willReturnOnConsecutiveCalls(...$responses);
        }

        return new OpenAIProvider(['api_key' => 'test-api-key'], $httpFactoryMock);
    }

    private function createJsonResponse(array $payload, int $status = 200): HttpResponse
    {
        $response = new HttpResponse('php://memory', $status, ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($payload));

        return $response;
    }
}
