<?php

namespace Joomla\AI\Tests\GroqProvider;

use Joomla\AI\AIFactory;
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\LimitExceededException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Limit\Limit;
use Joomla\AI\Provider\GroqProvider;
use Joomla\AI\Tests\Stub\FakeStreamTransport;
use Joomla\AI\Tests\Stub\RoutingHttpTrait;
use PHPUnit\Framework\TestCase;

class GroqTest extends TestCase
{
    use RoutingHttpTrait;

    private const BASE = 'https://api.groq.com/openai/v1';
    private const CHAT = 'POST ' . self::BASE . '/chat/completions';

    private ?string $audioFile = null;

    protected function tearDown(): void
    {
        if ($this->audioFile !== null) {
            @unlink($this->audioFile);
        }
    }

    public function testChatSendsRequestAndNormalisesResponse(): void
    {
        $provider = $this->provider([self::CHAT => $this->completion('Hello from Groq!', 'stop', [
            'queue_time' => 0.01,
            'prompt_tokens' => 12,
            'completion_tokens' => 9,
            'total_tokens' => 21,
        ])]);

        $response = $provider->chat('Hello there', [
            'system' => 'Be brief.',
            'max_tokens' => 100,
            'temperature' => 0.3,
            'stop' => ['END'],
            'seed' => 5,
            'reasoning_effort' => 'low',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello from Groq!', $response->getContent());
        $this->assertSame('Groq', $response->getProvider());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertSame(['input_tokens' => 12, 'output_tokens' => 9, 'total_tokens' => 21], $response->getUsage());

        $this->assertSame('Bearer test-key', $this->requests[0]['headers']['Authorization']);
        $this->assertSame([
            'model' => 'openai/gpt-oss-120b',
            'messages' => [
                ['role' => 'system', 'content' => 'Be brief.'],
                ['role' => 'user', 'content' => 'Hello there'],
            ],
            // max_tokens is sent under its current name
            'max_completion_tokens' => 100,
            'temperature' => 0.3,
            'seed' => 5,
            'stop' => ['END'],
            'reasoning_effort' => 'low',
        ], $this->sentJson(0));
    }

    public function testModelPrecedenceAndLengthFinish(): void
    {
        $provider = $this->provider([self::CHAT => ['sequence' => [
            $this->completion('a'),
            $this->completion('b', 'length'),
        ]]], ['model' => 'llama-3.3-70b-versatile']);

        $provider->chat('one');
        $provider->setDefaultModel('llama-3.1-8b-instant');
        $response = $provider->chat('two');

        $this->assertSame('llama-3.3-70b-versatile', $this->sentJson(0)['model']);
        $this->assertSame('llama-3.1-8b-instant', $this->sentJson(1)['model']);
        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('length', $response->getFinishReason());
    }

    public function testOptionsGroqRejectsAreCaughtEarly(): void
    {
        $provider = $this->provider([]);
        $schema = ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]];

        $cases = [
            'n > 1' => fn () => $provider->chat('Hi', ['n' => 2]),
            'schema with tools' => fn () => $provider->chat('Hi', ['response_schema' => $schema, 'tools' => [['name' => 'f']]]),
            'schema with streaming' => fn () => iterator_to_array($provider->chatStream('Hi', ['response_schema' => $schema])),
            'tools with streaming' => fn () => $provider->chat('Hi', ['stream' => true, 'tools' => [['name' => 'f']]]),
        ];

        foreach ($cases as $name => $call) {
            try {
                $call();
                $this->fail("Expected InvalidArgumentException for $name");
            } catch (InvalidArgumentException $e) {
            }
        }

        $this->assertSame([], $this->requests);
    }

    public function testErrors(): void
    {
        $provider = $this->provider([self::CHAT => ['sequence' => [
            $this->jsonResponse(['error' => ['message' => 'Invalid API Key', 'type' => 'invalid_request_error', 'code' => 'invalid_api_key']], 401),
            $this->jsonResponse(['error' => ['message' => 'The model `nope` does not exist', 'type' => 'invalid_request_error', 'code' => 'model_not_found']], 404),
            ['id' => 'chatcmpl-broken'],
        ]]]);

        try {
            $provider->chat('Hi');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertStringContainsString('Invalid API Key', $e->getMessage());
        }

        try {
            $provider->chat('Hi', ['model' => 'nope']);
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame('model_not_found', $e->getProviderErrorCode());
        }

        try {
            $provider->chat('Hi');
            $this->fail('Expected UnserializableResponseException');
        } catch (UnserializableResponseException $e) {
        }

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Groq API key not configured. Provide the api_key option.');
        (new GroqProvider([], $this->routingHttpFactory([])))->chat('Hi');
    }

    public function testStreamingReadsUsageFromXGroq(): void
    {
        $transport = new FakeStreamTransport([
            "data: {\"id\":\"c1\",\"model\":\"openai/gpt-oss-120b\",\"choices\":[{\"index\":0,\"delta\":{\"role\":\"assistant\",\"reasoning\":\"hmm\"}}]}\n\n",
            "data: {\"choices\":[{\"index\":0,\"delta\":{\"content\":\"Hel\"}}]}\n\ndata: {\"choices\":[{\"index\":0,\"delta\":{\"con",
            "tent\":\"lo\"}}]}\n\n",
            "data: {\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"stop\"}],\"x_groq\":{\"id\":\"req_1\",\"usage\":{\"prompt_tokens\":6,\"completion_tokens\":2,\"total_tokens\":8}}}\n\n",
            "data: [DONE]\n\n",
        ]);

        $provider = new GroqProvider(['api_key' => 'k', 'stream_transport' => $transport]);

        $received = [];
        $response = $provider->chat('Hi', ['stream' => true, 'on_chunk' => function (string $delta) use (&$received) {
            $received[] = $delta;
        }]);

        $this->assertSame(['Hel', 'lo'], $received);
        $this->assertSame('Hello', $response->getContent());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertSame(['input_tokens' => 6, 'output_tokens' => 2, 'total_tokens' => 8], $response->getUsage());

        $this->assertSame(self::BASE . '/chat/completions', $transport->requests[0]['url']);
        $this->assertTrue($transport->requests[0]['body']['stream']);
        $this->assertArrayNotHasKey('stream_options', $transport->requests[0]['body']);
    }

    public function testToolCalls(): void
    {
        $provider = $this->provider([self::CHAT => [
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'tool_calls' => [['id' => 'call_9', 'type' => 'function', 'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Oslo"}']]],
                ],
                'finish_reason' => 'tool_calls',
            ]],
        ]]);

        $tools = [['name' => 'get_weather', 'parameters' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]]]];
        $response = $provider->chat('Weather in Oslo?', ['tools' => $tools, 'tool_choice' => 'required', 'parallel_tool_calls' => false]);

        $sent = $this->sentJson(0);
        $this->assertSame('get_weather', $sent['tools'][0]['function']['name']);
        $this->assertSame('required', $sent['tool_choice']);
        $this->assertFalse($sent['parallel_tool_calls']);

        $this->assertSame([['id' => 'call_9', 'name' => 'get_weather', 'arguments' => ['city' => 'Oslo']]], $response->getToolCalls());

        $messages = $provider->buildToolResultMessages($response, ['call_9' => '3C']);
        $this->assertSame(['role' => 'tool', 'tool_call_id' => 'call_9', 'content' => '3C'], $messages[1]);
    }

    public function testResponseSchema(): void
    {
        $schema = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name'], 'additionalProperties' => false];
        $provider = $this->provider([self::CHAT => $this->completion('{"name":"Ada"}')]);

        $response = $provider->chat('Extract', ['response_schema' => ['name' => 'person', 'schema' => $schema, 'strict' => false]]);

        $this->assertSame(['type' => 'json_schema', 'json_schema' => ['name' => 'person', 'schema' => $schema, 'strict' => false]], $this->sentJson(0)['response_format']);
        $this->assertSame(['name' => 'Ada'], $response->getJson());
    }

    public function testVisionNeedsAModel(): void
    {
        $provider = $this->provider([self::CHAT => $this->completion('A cat')], ['vision_model' => 'some/vision-model']);

        $provider->vision('What is this?', 'data:image/png;base64,' . base64_encode('png'));

        $sent = $this->sentJson(0);
        $this->assertSame('some/vision-model', $sent['model']);
        $this->assertSame('image_url', $sent['messages'][0]['content'][1]['type']);

        try {
            $this->provider([])->vision('What is this?', 'https://example.com/cat.png');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('no default vision model', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $provider->vision('What is this?', '/local/cat.png');
    }

    public function testTranscription(): void
    {
        $this->audioFile = $this->createAudioFile('wav');

        $provider = $this->provider(['POST ' . self::BASE . '/audio/transcriptions' => [
            'text' => 'Hello Joomla.',
            'language' => 'English',
            'duration' => 1.5,
            'segments' => [['id' => 0, 'start' => 0.0, 'end' => 1.5, 'text' => 'Hello Joomla.']],
            'x_groq' => ['id' => 'req_2', 'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 4, 'total_tokens' => 4]],
        ]]);

        $response = $provider->transcribe($this->audioFile, [
            'language' => 'en',
            'response_format' => 'verbose_json',
            'timestamp_granularities' => ['segment', 'word'],
        ]);

        $this->assertSame('Hello Joomla.', $response->getContent());
        $this->assertSame(1.5, $response->getMetadata()['duration']);
        $this->assertCount(1, $response->getMetadata()['segments']);
        $this->assertSame(4, $response->getUsage()['total_tokens']);

        $body = (string) $this->requests[0]['body'];
        $this->assertStringContainsString("name=\"model\"\r\n\r\nwhisper-large-v3-turbo\r\n", $body);
        $this->assertStringContainsString("name=\"language\"\r\n\r\nen\r\n", $body);
        $this->assertStringContainsString('filename="' . basename($this->audioFile) . '"', $body);
        $this->assertStringContainsString('Content-Type: audio/wav', $body);
        // Lists are sent as repeated name[] fields
        $this->assertStringContainsString("name=\"timestamp_granularities[]\"\r\n\r\nsegment\r\n", $body);
        $this->assertStringContainsString("name=\"timestamp_granularities[]\"\r\n\r\nword\r\n", $body);
        $this->assertStringStartsWith('multipart/form-data; boundary=', $this->requests[0]['headers']['Content-Type']);
    }

    public function testTranslationAndTextFormat(): void
    {
        $this->audioFile = $this->createAudioFile('mp3');

        $provider = $this->provider(['POST ' . self::BASE . '/audio/translations' => $this->jsonResponse("Good morning.\n")]);

        $response = $provider->translate($this->audioFile, ['response_format' => 'text', 'language' => 'de']);

        $this->assertSame('Good morning.', $response->getContent());

        $body = (string) $this->requests[0]['body'];
        $this->assertStringContainsString("name=\"model\"\r\n\r\nwhisper-large-v3\r\n", $body);
        // Translation always produces English, so no language is sent
        $this->assertStringNotContainsString('name="language"', $body);
    }

    public function testAudioValidation(): void
    {
        $provider = $this->provider([]);
        $this->audioFile = $this->createAudioFile('wav');

        $cases = [
            'missing file' => fn () => $provider->transcribe('/missing/audio.wav'),
            'srt not supported' => fn () => $provider->transcribe($this->audioFile, ['response_format' => 'srt']),
            'timestamps need verbose_json' => fn () => $provider->transcribe($this->audioFile, ['timestamp_granularities' => ['word']]),
        ];

        foreach ($cases as $name => $call) {
            try {
                $call();
                $this->fail("Expected InvalidArgumentException for $name");
            } catch (InvalidArgumentException $e) {
            }
        }

        $text = $this->createAudioFile('txt');

        try {
            $this->expectException(InvalidArgumentException::class);
            $provider->transcribe($text);
        } finally {
            @unlink($text);
        }
    }

    public function testModels(): void
    {
        $provider = $this->provider(['GET ' . self::BASE . '/models' => ['object' => 'list', 'data' => [
            ['id' => 'openai/gpt-oss-120b', 'active' => true],
            ['id' => 'whisper-large-v3', 'active' => true],
            ['id' => 'old-model', 'active' => false],
        ]]]);

        $this->assertSame(['openai/gpt-oss-120b', 'whisper-large-v3'], $provider->getAvailableModels());
        $this->assertTrue($provider->isModelCapable('openai/gpt-oss-20b', 'json_schema'));
        $this->assertFalse($provider->isModelCapable('llama-3.1-8b-instant', 'json_schema'));
        $this->assertTrue($provider->isModelCapable('whisper-large-v3', 'translation'));
        $this->assertFalse($provider->isModelCapable('whisper-large-v3-turbo', 'translation'));
        $this->assertContains('llama-3.3-70b-versatile', $provider->getChatModels());
        $this->assertContains('whisper-large-v3-turbo', $provider->getTranscriptionModels());
    }

    public function testConversationLimitsFactoryAndBaseUrl(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-groq-limit-' . uniqid();
        $base = 'https://proxy.example.com/groq/v1';

        try {
            $limit = Limit::tokens(30, 60, $dir);
            $factory = new AIFactory($this->routingHttpFactory(['POST ' . $base . '/chat/completions' => ['sequence' => [
                $this->completion('Hi Ada', 'stop', ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15]),
                $this->completion('You are Ada', 'stop', ['prompt_tokens' => 15, 'completion_tokens' => 5, 'total_tokens' => 20]),
            ]]]));

            $ai = $factory->getAI('groq', ['api_key' => 'k', 'base_url' => $base . '/', 'limits' => [$limit]]);
            $this->assertInstanceOf(GroqProvider::class, $ai->getProvider());

            $chat = $ai->conversation(['system' => 'Remember names.', 'model' => 'llama-3.3-70b-versatile']);
            $chat->send('I am Ada');
            $chat->send('Who am I?');

            $this->assertSame([
                ['role' => 'system', 'content' => 'Remember names.'],
                ['role' => 'user', 'content' => 'I am Ada'],
                ['role' => 'assistant', 'content' => 'Hi Ada'],
                ['role' => 'user', 'content' => 'Who am I?'],
            ], $this->sentJson(1)['messages']);
            $this->assertSame(35, $limit->getUsed());

            $this->expectException(LimitExceededException::class);
            $chat->send('Blocked');
        } finally {
            array_map('unlink', glob($dir . '/*.json') ?: []);
            @rmdir($dir);
        }
    }

    private function provider(array $routes, array $options = []): GroqProvider
    {
        return new GroqProvider($options + ['api_key' => 'test-key', 'max_retries' => 0], $this->routingHttpFactory($routes));
    }

    private function completion(string $content, string $finishReason = 'stop', array $usage = []): array
    {
        return [
            'id' => 'chatcmpl-' . uniqid(),
            'object' => 'chat.completion',
            'model' => 'openai/gpt-oss-120b',
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => $finishReason]],
            'usage' => $usage,
            'x_groq' => ['id' => 'req_' . uniqid()],
        ];
    }

    private function createAudioFile(string $extension): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'groq-audio-' . uniqid() . '.' . $extension;
        file_put_contents($path, 'fake-audio');

        return $path;
    }
}
