<?php

namespace Joomla\AI\Tests\GeminiProvider;

use Joomla\AI\AI;
use Joomla\AI\AIFactory;
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\LimitExceededException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\QuotaExceededException;
use Joomla\AI\Exception\RateLimitException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Limit\Limit;
use Joomla\AI\Provider\GeminiProvider;
use Joomla\AI\Tests\Stub\FakeStreamTransport;
use Joomla\AI\Tests\Stub\RoutingHttpTrait;
use PHPUnit\Framework\TestCase;

class GeminiTest extends TestCase
{
    use RoutingHttpTrait;

    private const BASE = 'https://generativelanguage.googleapis.com/v1beta';
    private const INTERACTIONS = 'POST ' . self::BASE . '/interactions';

    /** @var float[] Delays passed to pause() */
    private array $pauses = [];

    public function testChatSendsInteractionAndNormalisesResponse(): void
    {
        $provider = $this->provider([self::INTERACTIONS => $this->interaction([
            ['type' => 'thought', 'signature' => 'sig-1'],
            ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'Hello '], ['type' => 'text', 'text' => 'from Gemini!']]],
        ], 'completed', ['total_input_tokens' => 12, 'total_output_tokens' => 9, 'total_thought_tokens' => 30, 'total_tokens' => 51])]);

        $response = $provider->chat('Hello there', [
            'system' => 'Be brief.',
            'max_tokens' => 100,
            'temperature' => 0.4,
            'top_p' => 0.9,
            'stop' => 'END',
            'seed' => 3,
            'thinking_level' => 'low',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello from Gemini!', $response->getContent());
        $this->assertSame('Gemini', $response->getProvider());
        $this->assertSame('stop', $response->getFinishReason());
        // Thought tokens are part of the total reported by Gemini
        $this->assertSame(['input_tokens' => 12, 'output_tokens' => 9, 'total_tokens' => 51], $response->getUsage());

        $this->assertSame('test-key', $this->requests[0]['headers']['x-goog-api-key']);
        $this->assertSame([
            'model' => 'gemini-3.8-flash',
            'input' => [['type' => 'user_input', 'content' => [['type' => 'text', 'text' => 'Hello there']]]],
            'store' => false,
            'system_instruction' => 'Be brief.',
            'generation_config' => [
                'temperature' => 0.4,
                'top_p' => 0.9,
                'seed' => 3,
                'max_output_tokens' => 100,
                'stop_sequences' => ['END'],
                'thinking_level' => 'low',
            ],
        ], $this->sentJson(0));
    }

    public function testIncompleteInteractionIsLength(): void
    {
        $provider = $this->provider([self::INTERACTIONS => $this->interaction([
            ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'Truncat']]],
        ], 'incomplete')]);

        $response = $provider->chat('Long answer');

        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('length', $response->getFinishReason());
    }

    public function testErrors(): void
    {
        $provider = $this->provider([self::INTERACTIONS => ['sequence' => [
            $this->jsonResponse(['error' => ['code' => 400, 'message' => 'API key not valid.', 'status' => 'INVALID_ARGUMENT']], 400),
            $this->jsonResponse(['error' => ['code' => 403, 'message' => 'Permission denied.', 'status' => 'PERMISSION_DENIED']], 403),
            $this->interaction([], 'failed', [], [['code' => 'internal', 'message' => 'Model crashed']]),
            ['id' => 'int-broken', 'status' => 'completed'],
        ]]]);

        try {
            $provider->chat('Hi');
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame(400, $e->getHttpStatusCode());
            $this->assertStringContainsString('API key not valid.', $e->getMessage());
        }

        try {
            $provider->chat('Hi');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
        }

        try {
            $provider->chat('Hi');
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
            $this->assertStringContainsString('Model crashed', $e->getMessage());
        }

        $this->expectException(UnserializableResponseException::class);
        $this->expectExceptionMessage("Missing field 'steps'");
        $provider->chat('Hi');
    }

    public function testMissingApiKeyAndInvalidOptions(): void
    {
        try {
            (new GeminiProvider([], $this->routingHttpFactory([])))->chat('Hi');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertStringContainsString('Gemini API key not configured', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->provider([])->chat('Hi', ['thinking_level' => 'extreme']);
    }

    public function testToolCallsAndResultsRoundTrip(): void
    {
        $steps = [
            ['type' => 'thought', 'signature' => 'sig-abc'],
            ['type' => 'function_call', 'id' => 'fc_1', 'name' => 'get_weather', 'arguments' => ['city' => 'Paris']],
        ];

        $provider = $this->provider([self::INTERACTIONS => ['sequence' => [
            $this->interaction($steps, 'requires_action'),
            $this->interaction([['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'It is 21C.']]]]),
        ]]]);

        $tools = [['name' => 'get_weather', 'description' => 'Weather', 'parameters' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]]]];
        $messages = [['role' => 'user', 'content' => 'Weather in Paris?']];

        $response = $provider->chat('', ['messages' => $messages, 'tools' => $tools, 'tool_choice' => ['name' => 'get_weather']]);

        $sent = $this->sentJson(0);
        $this->assertSame([['type' => 'function', 'name' => 'get_weather', 'description' => 'Weather', 'parameters' => $tools[0]['parameters']]], $sent['tools']);
        $this->assertSame(['allowed_tools' => ['mode' => 'any', 'tools' => ['get_weather']]], $sent['generation_config']['tool_choice']);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('tool_calls', $response->getFinishReason());
        $this->assertSame([['id' => 'fc_1', 'name' => 'get_weather', 'arguments' => ['city' => 'Paris']]], $response->getToolCalls());

        $followUp = $provider->buildToolResultMessages($response, ['fc_1' => ['temp_c' => 21]]);

        // The model's steps, thought signature included, go back unchanged
        $this->assertSame(['role' => 'assistant', 'content' => $steps], $followUp[0]);
        $this->assertSame([
            'role' => 'user',
            'content' => [['type' => 'function_result', 'call_id' => 'fc_1', 'name' => 'get_weather', 'result' => ['temp_c' => 21]]],
        ], $followUp[1]);

        $final = $provider->chat('', ['messages' => array_merge($messages, $followUp), 'tools' => $tools]);
        $this->assertSame('It is 21C.', $final->getContent());

        $this->assertSame([
            ['type' => 'user_input', 'content' => [['type' => 'text', 'text' => 'Weather in Paris?']]],
            ['type' => 'thought', 'signature' => 'sig-abc'],
            ['type' => 'function_call', 'id' => 'fc_1', 'name' => 'get_weather', 'arguments' => ['city' => 'Paris']],
            ['type' => 'function_result', 'call_id' => 'fc_1', 'name' => 'get_weather', 'result' => ['temp_c' => 21]],
        ], $this->sentJson(1)['input']);
    }

    public function testToolChoiceMappingAndStringResults(): void
    {
        $provider = $this->provider([self::INTERACTIONS => ['sequence' => [
            $this->interaction([['type' => 'function_call', 'id' => 'fc_2', 'name' => 'now', 'arguments' => []]]),
            $this->interaction([['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'ok']]]]),
        ]]]);

        $response = $provider->chat('Time?', ['tools' => [['name' => 'now']], 'tool_choice' => 'required']);
        $provider->chat('Hi', ['tools' => [['name' => 'now']], 'tool_choice' => 'none']);

        $this->assertSame('any', $this->sentJson(0)['generation_config']['tool_choice']);
        $this->assertSame('none', $this->sentJson(1)['generation_config']['tool_choice']);
        $this->assertSame('12:00', $provider->buildToolResultMessages($response, ['fc_2' => '12:00'])[1]['content'][0]['result']);

        $this->expectException(InvalidArgumentException::class);
        $provider->buildToolResultMessages($response, []);
    }

    public function testResponseSchema(): void
    {
        $schema = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']];
        $provider = $this->provider([self::INTERACTIONS => $this->interaction([
            ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => '{"name":"Ada"}']]],
        ])]);

        $response = $provider->chat('Extract', ['response_schema' => $schema]);

        $sent = $this->sentJson(0);
        $this->assertSame(['type' => 'text', 'mime_type' => 'application/json', 'schema' => $schema], $sent['response_format']);
        $this->assertSame('application/json', $sent['response_mime_type']);
        $this->assertSame(['name' => 'Ada'], $response->getJson());
    }

    public function testVision(): void
    {
        $reply = $this->interaction([['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'A cat']]]]);
        $provider = $this->provider([self::INTERACTIONS => ['sequence' => [$reply, $reply]]]);

        $provider->vision('What is this?', 'data:image/jpg;base64,' . base64_encode('jpeg-bytes'), ['resolution' => 'high', 'system' => 'Be brief.']);
        $provider->vision('And this?', 'https://example.com/photos/dog.webp');

        $first = $this->sentJson(0);
        $this->assertSame('Be brief.', $first['system_instruction']);
        $this->assertSame([[
            'type' => 'user_input',
            'content' => [
                ['type' => 'text', 'text' => 'What is this?'],
                ['type' => 'image', 'data' => base64_encode('jpeg-bytes'), 'mime_type' => 'image/jpeg', 'resolution' => 'high'],
            ],
        ]], $first['input']);

        $this->assertSame(['type' => 'image', 'uri' => 'https://example.com/photos/dog.webp', 'mime_type' => 'image/webp'], $this->sentJson(1)['input'][0]['content'][1]);
    }

    public function testVisionRejectsUnsupportedImages(): void
    {
        $provider = $this->provider([]);

        foreach (['/local/cat.png', 'data:image/svg+xml;base64,PHN2Zz4=', 'ftp://example.com/a.png'] as $image) {
            try {
                $provider->vision('What is this?', $image);
                $this->fail("Expected InvalidArgumentException for $image");
            } catch (InvalidArgumentException $e) {
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $provider->vision('What is this?', 'https://example.com/a.png', ['resolution' => 'max']);
    }

    public function testStreaming(): void
    {
        $transport = new FakeStreamTransport([
            "event: interaction.created\ndata: {\"event_type\":\"interaction.created\",\"interaction\":{\"id\":\"int_1\",\"status\":\"in_progress\",\"model\":\"gemini-3.8-flash\"}}\n\n",
            "data: {\"event_type\":\"step.start\",\"index\":0,\"step\":{\"type\":\"thought\"}}\n\n",
            "data: {\"event_type\":\"step.delta\",\"index\":0,\"delta\":{\"type\":\"thought_summary\",\"content\":{\"type\":\"text\",\"text\":\"Thinking\"}}}\n\n",
            "data: {\"event_type\":\"step.start\",\"index\":1,\"step\":{\"type\":\"model_output\"}}\n\ndata: {\"event_type\":\"step.delta\",\"index\":1,\"delta\":{\"type\":\"text\",\"te",
            "xt\":\"Hel\"}}\n\ndata: {\"event_type\":\"step.delta\",\"index\":1,\"delta\":{\"type\":\"text\",\"text\":\"lo\"}}\n\n",
            "data: {\"event_type\":\"step.stop\",\"index\":1}\n\n",
            "data: {\"event_type\":\"interaction.completed\",\"interaction\":{\"id\":\"int_1\",\"status\":\"completed\",\"usage\":{\"total_input_tokens\":5,\"total_output_tokens\":2,\"total_tokens\":7}}}\n\n",
        ]);

        $provider = new GeminiProvider(['api_key' => 'k', 'stream_transport' => $transport]);

        $stream = $provider->chatStream('Hi');
        $this->assertSame(['Hel', 'lo'], iterator_to_array($stream, false));

        $response = $stream->getReturn();
        $this->assertSame('Hello', $response->getContent());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertSame(['input_tokens' => 5, 'output_tokens' => 2, 'total_tokens' => 7], $response->getUsage());
        $this->assertSame('int_1', $response->getMetadata()['id']);

        $this->assertSame(self::BASE . '/interactions', $transport->requests[0]['url']);
        $this->assertTrue($transport->requests[0]['body']['stream']);
    }

    public function testStreamingErrorEvent(): void
    {
        $transport = new FakeStreamTransport(["data: {\"event_type\":\"error\",\"error\":{\"code\":\"resource_exhausted\",\"message\":\"Too much\"}}\n\n"]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Too much');

        iterator_to_array((new GeminiProvider(['api_key' => 'k', 'stream_transport' => $transport]))->chatStream('Hi'));
    }

    public function testGenerateImage(): void
    {
        $provider = $this->provider([self::INTERACTIONS => ['sequence' => [
            $this->interaction([
                ['type' => 'model_output', 'content' => [
                    ['type' => 'text', 'text' => 'Here is your image.'],
                    ['type' => 'image', 'data' => base64_encode('jpeg-1'), 'mime_type' => 'image/jpeg'],
                ]],
            ], 'completed', ['total_input_tokens' => 10, 'total_output_tokens' => 1290, 'total_tokens' => 1300]),
            $this->interaction([['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'I cannot draw that.']]]]),
        ]]]);

        $provider->setDefaultModel('gemini-3.8-flash');
        $response = $provider->generateImage('A Joomla logo made of clouds', ['aspect_ratio' => '16:9', 'image_size' => '2K']);

        $this->assertSame([
            'model' => 'gemini-nano-banana-2.1', // not the chat default model
            'input' => [['type' => 'text', 'text' => 'A Joomla logo made of clouds']],
            'response_format' => ['type' => 'image', 'aspect_ratio' => '16:9', 'image_size' => '2K'],
            'store' => false,
        ], $this->sentJson(0));

        $this->assertSame(base64_encode('jpeg-1'), $response->getContent());
        $this->assertSame('b64_json', $response->getMetadata()['response_format']);
        $this->assertSame('Here is your image.', $response->getMetadata()['text']);
        $this->assertSame(1300, $response->getUsage()['total_tokens']);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('No image was generated. The model said: I cannot draw that.');
        $provider->generateImage('Something not allowed');
    }

    public function testEditImageAndOptions(): void
    {
        $png = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gemini-edit-' . uniqid() . '.png';
        file_put_contents($png, 'png-bytes');

        try {
            $provider = $this->provider([self::INTERACTIONS => $this->interaction([
                ['type' => 'model_output', 'content' => [['type' => 'image', 'data' => base64_encode('edited'), 'mime_type' => 'image/jpeg']]],
            ])], ['image_model' => 'gemini-3.1-flash-lite-image']);

            $provider->editImage($png, 'Make the sky purple');

            $sent = $this->sentJson(0);
            $this->assertSame('gemini-3.1-flash-lite-image', $sent['model']);
            $this->assertSame([
                ['type' => 'text', 'text' => 'Make the sky purple'],
                ['type' => 'image', 'data' => base64_encode('png-bytes'), 'mime_type' => 'image/png'],
            ], $sent['input']);

            try {
                $provider->editImage(array_fill(0, 15, $png), 'Too many');
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('between 1 and 14', $e->getMessage());
            }

            try {
                $provider->generateImage('Bad size', ['image_size' => '8K']);
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $e) {
            }

            try {
                $provider->editImage('/missing/file.png', 'Nope');
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $e) {
            }

            $this->expectException(ProviderException::class);
            $provider->createImageVariation($png);
        } finally {
            @unlink($png);
        }
    }

    public function testEmbeddings(): void
    {
        $endpoint = 'POST ' . self::BASE . '/models/gemini-embedding-2:batchEmbedContents';
        $provider = $this->provider([$endpoint => ['sequence' => [
            ['embeddings' => [['values' => [0.1, 0.2]], ['values' => [0.3, 0.4]]]],
            ['embeddings' => [['values' => [0.5, 0.6]]]],
        ]]]);

        $response = $provider->createEmbeddings(['first', 'second'], 'gemini-embedding-2', ['dimensions' => 768]);

        $this->assertSame([[0.1, 0.2], [0.3, 0.4]], $response->getEmbeddings());
        $this->assertSame(['requests' => [
            ['model' => 'models/gemini-embedding-2', 'content' => ['parts' => [['text' => 'first']]], 'output_dimensionality' => 768],
            ['model' => 'models/gemini-embedding-2', 'content' => ['parts' => [['text' => 'second']]], 'output_dimensionality' => 768],
        ]], $this->sentJson(0));

        $single = $provider->createEmbeddings('only one', 'models/gemini-embedding-2');
        $this->assertSame([0.5, 0.6], $single->getEmbedding());
        $this->assertSame('[0.5,0.6]', $single->getContent());
    }

    public function testModels(): void
    {
        $provider = $this->provider(['GET ' . self::BASE . '/models?pageSize=1000' => ['models' => [
            ['name' => 'models/gemini-3.8-flash'],
            ['name' => 'models/gemini-embedding-2'],
        ]]]);

        $this->assertSame(['gemini-3.8-flash', 'gemini-embedding-2'], $provider->getAvailableModels());
        $this->assertTrue($provider->isModelCapable('gemini-3.8-flash', 'vision'));
        $this->assertTrue($provider->isModelCapable('gemini-nano-banana-2.1', 'image'));
        $this->assertFalse($provider->isModelCapable('gemini-embedding-2', 'chat'));
        $this->assertContains('gemini-embedding-2', $provider->getEmbeddingModels());
    }

    public function testRateLimitWithRetryInfoIsRetried(): void
    {
        $rateLimited = $this->jsonResponse(['error' => [
            'code' => 429,
            'message' => 'You exceeded your current quota, please check your plan and billing details.',
            'status' => 'RESOURCE_EXHAUSTED',
            'details' => [['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '2s']],
        ]], 429);

        $provider = $this->providerWithPauses([self::INTERACTIONS => ['sequence' => [
            $rateLimited,
            $this->interaction([['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'ok']]]]),
        ]]]);

        $this->assertSame('ok', $provider->chat('Hi')->getContent());
        $this->assertSame([2.0], $this->pauses);

        $exhausted = $this->providerWithPauses([self::INTERACTIONS => ['sequence' => [$rateLimited, $rateLimited]]], ['max_retries' => 1]);

        $this->expectException(RateLimitException::class);
        $exhausted->chat('Hi');
    }

    public function testQuotaWithoutRetryInfoIsNotRetried(): void
    {
        $provider = $this->providerWithPauses([self::INTERACTIONS => $this->jsonResponse(['error' => [
            'code' => 429,
            'message' => 'You exceeded your current quota, please check your plan and billing details.',
            'status' => 'RESOURCE_EXHAUSTED',
        ]], 429)]);

        try {
            $provider->chat('Hi');
            $this->fail('Expected QuotaExceededException');
        } catch (QuotaExceededException $e) {
        }

        $this->assertSame([], $this->pauses);
        $this->assertCount(1, $this->requests);
    }

    public function testConversationLimitsFactoryAndBaseUrl(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-gemini-limit-' . uniqid();
        $base = 'https://proxy.example.com/gemini/v1beta';

        try {
            $limit = Limit::requests(2, 60, $dir);
            $factory = new AIFactory($this->routingHttpFactory(['POST ' . $base . '/interactions' => ['sequence' => [
                $this->interaction([['type' => 'thought', 'signature' => 's1'], ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'Hi Ada']]]]),
                $this->interaction([['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'You are Ada']]]]),
            ]]]));

            $ai = $factory->getAI('gemini', ['api_key' => 'k', 'base_url' => $base . '/', 'limits' => [$limit]]);
            $this->assertInstanceOf(GeminiProvider::class, $ai->getProvider());

            $chat = $ai->conversation(['system' => 'Remember names.']);
            $chat->send('I am Ada');
            $this->assertSame('You are Ada', $chat->send('Who am I?')->getContent());

            $second = $this->sentJson(1);
            $this->assertSame('Remember names.', $second['system_instruction']);
            $this->assertSame([
                ['type' => 'user_input', 'content' => [['type' => 'text', 'text' => 'I am Ada']]],
                ['type' => 'thought', 'signature' => 's1'],
                ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'Hi Ada']]],
                ['type' => 'user_input', 'content' => [['type' => 'text', 'text' => 'Who am I?']]],
            ], $second['input']);

            $this->expectException(LimitExceededException::class);
            $chat->send('Third');
        } finally {
            array_map('unlink', glob($dir . '/*.json') ?: []);
            @rmdir($dir);
        }
    }

    private function provider(array $routes, array $options = []): GeminiProvider
    {
        return new GeminiProvider($options + ['api_key' => 'test-key', 'max_retries' => 0], $this->routingHttpFactory($routes));
    }

    /**
     * Provider that records retry delays instead of sleeping.
     */
    private function providerWithPauses(array $routes, array $options = []): GeminiProvider
    {
        return new class ($options + ['api_key' => 'test-key'], $this->routingHttpFactory($routes), $this->pauses) extends GeminiProvider {
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
    }

    private function interaction(array $steps, string $status = 'completed', array $usage = [], array $errors = []): array
    {
        $interaction = [
            'id' => 'int_' . uniqid(),
            'model' => 'gemini-3.8-flash',
            'status' => $status,
            'created' => '2026-10-23T10:00:00Z',
            'updated' => '2026-10-23T10:00:01Z',
            'steps' => $steps,
            'usage' => $usage,
        ];

        if ($errors !== []) {
            $interaction['errors'] = $errors;
        }

        return $interaction;
    }
}
