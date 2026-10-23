<?php

namespace Joomla\AI\Tests\GrokProvider;

use Joomla\AI\AI;
use Joomla\AI\AIFactory;
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Limit\Limit;
use Joomla\AI\Provider\GrokProvider;
use Joomla\AI\Tests\Stub\FakeStreamTransport;
use Joomla\AI\Tests\Stub\RoutingHttpTrait;
use PHPUnit\Framework\TestCase;

class GrokTest extends TestCase
{
    use RoutingHttpTrait;

    private const CHAT = 'POST https://api.x.ai/v1/chat/completions';
    private const IMAGES = 'POST https://api.x.ai/v1/images/generations';

    public function testChatSendsRequestAndNormalisesResponse(): void
    {
        $provider = $this->provider([self::CHAT => $this->completion('Hello from Grok!', 'stop', ['prompt_tokens' => 12, 'completion_tokens' => 9, 'total_tokens' => 21])]);

        $response = $provider->chat('Hello there', [
            'system' => 'Be brief.',
            'max_tokens' => 100,
            'temperature' => 0.2,
            'stop' => ['END'],
            'seed' => 7,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello from Grok!', $response->getContent());
        $this->assertSame('Grok', $response->getProvider());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertSame(['input_tokens' => 12, 'output_tokens' => 9, 'total_tokens' => 21], $response->getUsage());

        $this->assertSame('Bearer test-key', $this->requests[0]['headers']['Authorization']);
        $sent = $this->sentJson(0);
        $this->assertSame('grok-4.7', $sent['model']);
        $this->assertSame([
            ['role' => 'system', 'content' => 'Be brief.'],
            ['role' => 'user', 'content' => 'Hello there'],
        ], $sent['messages']);
        $this->assertSame(100, $sent['max_tokens']);
        $this->assertSame(0.2, $sent['temperature']);
        $this->assertSame(['END'], $sent['stop']);
        $this->assertSame(7, $sent['seed']);
    }

    public function testModelPrecedence(): void
    {
        $provider = $this->provider([self::CHAT => ['sequence' => [$this->completion('a'), $this->completion('b'), $this->completion('c')]]], ['model' => 'grok-4.3']);

        $provider->chat('one');
        $provider->setDefaultModel('grok-4.5');
        $provider->chat('two');
        $provider->chat('three', ['model' => 'grok-4.6']);

        $this->assertSame(['grok-4.3', 'grok-4.5', 'grok-4.6'], array_map(fn ($i) => $this->sentJson($i)['model'], [0, 1, 2]));
    }

    public function testLengthFinishReasonGives206(): void
    {
        $provider = $this->provider([self::CHAT => $this->completion('Truncated', 'length')]);

        $response = $provider->chat('Long answer please');

        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('length', $response->getFinishReason());
    }

    public function testErrors(): void
    {
        $provider = $this->provider([self::CHAT => ['sequence' => [
            $this->jsonResponse(['error' => ['message' => 'Incorrect API key provided']], 401),
            $this->jsonResponse(['error' => ['message' => 'Model not found', 'code' => 'model_not_found']], 404),
            ['id' => 'chatcmpl-broken'],
        ]]]);

        try {
            $provider->chat('Hi');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertStringContainsString('Incorrect API key provided', $e->getMessage());
        }

        try {
            $provider->chat('Hi');
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame(404, $e->getHttpStatusCode());
            $this->assertSame('model_not_found', $e->getProviderErrorCode());
        }

        $this->expectException(UnserializableResponseException::class);
        $this->expectExceptionMessage("Missing field 'choices.0'");
        $provider->chat('Hi');
    }

    public function testMissingApiKeyThrows(): void
    {
        $provider = new GrokProvider([], $this->routingHttpFactory([]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('xAI API key not configured. Provide the api_key option.');

        $provider->chat('Hi');
    }

    public function testStreaming(): void
    {
        $transport = new FakeStreamTransport([
            "data: {\"id\":\"c1\",\"model\":\"grok-4.7\",\"choices\":[{\"delta\":{\"role\":\"assistant\",\"reasoning_content\":\"thinking\"}}]}\n\n",
            "data: {\"choices\":[{\"delta\":{\"content\":\"Hel\"}}]}\n\ndata: {\"choices\":[{\"delta\":{\"con",
            "tent\":\"lo\"},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":5,\"completion_tokens\":2,\"total_tokens\":7}}\n\n",
            "data: [DONE]\n\n",
        ]);

        $provider = new GrokProvider(['api_key' => 'k', 'stream_transport' => $transport]);

        $received = [];
        $response = $provider->chat('Hi', ['stream' => true, 'on_chunk' => function (string $delta) use (&$received) {
            $received[] = $delta;
        }]);

        // Reasoning is not passed on as text
        $this->assertSame(['Hel', 'lo'], $received);
        $this->assertSame('Hello', $response->getContent());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertSame(7, $response->getUsage()['total_tokens']);

        $sent = $transport->requests[0];
        $this->assertSame('https://api.x.ai/v1/chat/completions', $sent['url']);
        $this->assertTrue($sent['body']['stream']);
        $this->assertArrayNotHasKey('stream_options', $sent['body']);
    }

    public function testToolCallsAndResults(): void
    {
        $provider = $this->provider([self::CHAT => [
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Paris"}']]],
                ],
                'finish_reason' => 'tool_calls',
            ]],
        ]]);

        $tools = [['name' => 'get_weather', 'description' => 'Weather', 'parameters' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]]]];
        $response = $provider->chat('Weather in Paris?', ['tools' => $tools, 'tool_choice' => 'auto']);

        $sent = $this->sentJson(0);
        $this->assertSame('function', $sent['tools'][0]['type']);
        $this->assertSame('get_weather', $sent['tools'][0]['function']['name']);
        $this->assertSame('auto', $sent['tool_choice']);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame([['id' => 'call_1', 'name' => 'get_weather', 'arguments' => ['city' => 'Paris']]], $response->getToolCalls());

        $messages = $provider->buildToolResultMessages($response, ['call_1' => ['temp_c' => 21]]);
        $this->assertSame('call_1', $messages[0]['tool_calls'][0]['id']);
        $this->assertSame(['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '{"temp_c":21}'], $messages[1]);
    }

    public function testToolsWithStreamingThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new GrokProvider(['api_key' => 'k']))->chat('Hi', ['stream' => true, 'tools' => [['name' => 'x']]]);
    }

    public function testResponseSchema(): void
    {
        $schema = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name'], 'additionalProperties' => false];
        $provider = $this->provider([self::CHAT => $this->completion('{"name":"Ada"}')]);

        $response = $provider->chat('Extract', ['response_schema' => ['name' => 'person', 'schema' => $schema]]);

        $this->assertSame(['type' => 'json_schema', 'json_schema' => ['name' => 'person', 'schema' => $schema, 'strict' => true]], $this->sentJson(0)['response_format']);
        $this->assertSame(['name' => 'Ada'], $response->getJson());
    }

    public function testVisionWithUrlAndDataUri(): void
    {
        $provider = $this->provider([self::CHAT => ['sequence' => [$this->completion('A cat'), $this->completion('A dog')]]]);

        $provider->vision('What is this?', 'https://example.com/cat.jpg', ['detail' => 'high']);
        $provider->vision('And this?', 'data:image/png;base64,' . base64_encode('png'), ['system' => 'Describe briefly.']);

        $first = $this->sentJson(0);
        $this->assertSame('grok-4.7', $first['model']);
        $this->assertSame([
            ['type' => 'text', 'text' => 'What is this?'],
            ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/cat.jpg', 'detail' => 'high']],
        ], $first['messages'][0]['content']);

        $second = $this->sentJson(1)['messages'];
        $this->assertSame('system', $second[0]['role']);
        $this->assertStringStartsWith('data:image/png;base64,', $second[1]['content'][1]['image_url']['url']);
    }

    public function testVisionRejectsUnsupportedImages(): void
    {
        $provider = $this->provider([]);

        foreach (['/local/file.png', 'data:image/gif;base64,R0lG', 'ftp://example.com/a.png'] as $image) {
            try {
                $provider->vision('What is this?', $image);
                $this->fail("Expected InvalidArgumentException for $image");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('http(s) URL or a JPEG/PNG base64 data URI', $e->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $provider->vision('What is this?', 'https://example.com/cat.jpg', ['detail' => 'ultra']);
    }

    public function testGenerateImage(): void
    {
        $provider = $this->provider([self::IMAGES => ['sequence' => [
            ['data' => [['b64_json' => base64_encode('img1')], ['b64_json' => base64_encode('img2')]]],
            ['data' => [['url' => 'https://imgen.x.ai/a.jpg', 'revised_prompt' => 'A red bicycle']]],
        ]]]);

        $response = $provider->generateImage('A bicycle', ['n' => 2, 'response_format' => 'b64_json']);

        $this->assertSame(['model' => 'grok-imagine-image-2.0', 'prompt' => 'A bicycle', 'n' => 2, 'response_format' => 'b64_json'], $this->sentJson(0));
        $this->assertSame('b64_json', $response->getMetadata()['response_format']);
        $this->assertSame([base64_encode('img1'), base64_encode('img2')], json_decode($response->getContent(), true));

        $provider->setDefaultModel('grok-4.7');
        $url = $provider->generateImage('A red bicycle');

        // The chat default model is not used for images
        $this->assertSame('grok-imagine-image-2.0', $this->sentJson(1)['model']);
        $this->assertSame('https://imgen.x.ai/a.jpg', $url->getContent());
    }

    public function testImageModelOptionAndValidation(): void
    {
        $provider = $this->provider([self::IMAGES => ['data' => [['url' => 'https://imgen.x.ai/a.jpg']]]], ['image_model' => 'grok-imagine-image']);

        $provider->generateImage('A cat');
        $this->assertSame('grok-imagine-image', $this->sentJson(0)['model']);

        try {
            $provider->generateImage('Too many', ['n' => 11]);
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('between 1 and 10', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $provider->generateImage('Bad format', ['response_format' => 'png']);
    }

    public function testImageEditingAndVariationsAreNotSupported(): void
    {
        $provider = $this->provider([]);

        try {
            $provider->editImage('a.png', 'Make it blue');
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
            $this->assertStringContainsString('Image editing is not supported', $e->getMessage());
        }

        $this->expectException(ProviderException::class);
        $provider->createImageVariation('a.png');
    }

    public function testModels(): void
    {
        $provider = $this->provider(['GET https://api.x.ai/v1/models' => ['data' => [['id' => 'grok-4.7'], ['id' => 'grok-imagine-image-2.0']]]]);

        $this->assertSame(['grok-4.7', 'grok-imagine-image-2.0'], $provider->getAvailableModels());
        $this->assertTrue($provider->isModelCapable('grok-4.7', 'chat'));
        $this->assertTrue($provider->isModelCapable('grok-4.3', 'vision'));
        $this->assertTrue($provider->isModelCapable('grok-imagine-image-2.0', 'image'));
        $this->assertFalse($provider->isModelCapable('grok-imagine-image-2.0', 'chat'));
        $this->assertFalse($provider->isModelCapable('grok-3', 'chat'));
        $this->assertContains('grok-4.7', $provider->getChatModels());
        $this->assertContains('grok-imagine-image-2.0', $provider->getImageModels());
    }

    public function testBaseUrlOverride(): void
    {
        $provider = new GrokProvider(['api_key' => 'k', 'base_url' => 'https://gateway.example.com/xai/v1/'], $this->routingHttpFactory([
            'POST https://gateway.example.com/xai/v1/chat/completions' => $this->completion('hi'),
        ]));

        $this->assertSame('hi', $provider->chat('Hello')->getContent());
    }

    public function testFactoryConversationAndLimits(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-grok-limit-' . uniqid();

        try {
            $limit = Limit::tokens(30, 60, $dir);
            $factory = new AIFactory($this->routingHttpFactory([self::CHAT => ['sequence' => [
                $this->completion('Hi Ada', 'stop', ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15]),
                $this->completion('You are Ada', 'stop', ['prompt_tokens' => 15, 'completion_tokens' => 5, 'total_tokens' => 20]),
            ]]]));

            $ai = $factory->getAI('grok', ['api_key' => 'k', 'limits' => [$limit]]);
            $this->assertInstanceOf(GrokProvider::class, $ai->getProvider());

            $chat = $ai->conversation(['system' => 'Remember names.']);
            $chat->send('I am Ada');
            $chat->send('Who am I?');

            $this->assertSame([
                ['role' => 'system', 'content' => 'Remember names.'],
                ['role' => 'user', 'content' => 'I am Ada'],
                ['role' => 'assistant', 'content' => 'Hi Ada'],
                ['role' => 'user', 'content' => 'Who am I?'],
            ], $this->sentJson(1)['messages']);

            $this->assertSame(35, $limit->getUsed());

            $this->expectException(\Joomla\AI\Exception\LimitExceededException::class);
            $chat->send('Blocked');
        } finally {
            array_map('unlink', glob($dir . '/*.json') ?: []);
            @rmdir($dir);
        }
    }

    private function provider(array $routes, array $options = []): GrokProvider
    {
        return new GrokProvider($options + ['api_key' => 'test-key', 'max_retries' => 0], $this->routingHttpFactory($routes));
    }

    private function completion(string $content, string $finishReason = 'stop', array $usage = []): array
    {
        return [
            'id' => 'chatcmpl-' . uniqid(),
            'object' => 'chat.completion',
            'model' => 'grok-4.7',
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => $finishReason]],
            'usage' => $usage,
        ];
    }
}
