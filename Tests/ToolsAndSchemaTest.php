<?php

namespace Joomla\AI\Tests;

use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Provider\AnthropicProvider;
use Joomla\AI\Provider\OllamaProvider;
use Joomla\AI\Provider\OpenAIProvider;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

class ToolsAndSchemaTest extends TestCase
{
    private const WEATHER_TOOL = [
        'name' => 'get_weather',
        'description' => 'Get the weather for a city',
        'parameters' => [
            'type' => 'object',
            'properties' => ['city' => ['type' => 'string']],
            'required' => ['city'],
            'additionalProperties' => false,
        ],
        'strict' => true,
    ];

    private const PERSON_SCHEMA = [
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string'], 'age' => ['type' => 'integer']],
        'required' => ['name', 'age'],
        'additionalProperties' => false,
    ];

    /** @var array<int, array> Decoded request bodies sent by the provider */
    private array $sent = [];

    /** Raw body of the last POST */
    private string $lastRawBody = '';

    public function testOpenAISendsToolsAndParsesToolCalls(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'k', 'moderate_input' => false], $this->httpFactory([
            [
                'id' => 'chatcmpl-1',
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_abc',
                            'type' => 'function',
                            'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Paris"}'],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 5, 'total_tokens' => 25],
            ],
        ]));

        $response = $provider->chat('Weather in Paris?', [
            'tools' => [self::WEATHER_TOOL],
            'tool_choice' => ['name' => 'get_weather'],
            'parallel_tool_calls' => false,
        ]);

        $this->assertSame([
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'description' => 'Get the weather for a city',
                'parameters' => self::WEATHER_TOOL['parameters'],
                'strict' => true,
            ],
        ], $this->sent[0]['tools'][0]);
        $this->assertSame(['type' => 'function', 'function' => ['name' => 'get_weather']], $this->sent[0]['tool_choice']);
        $this->assertFalse($this->sent[0]['parallel_tool_calls']);

        $this->assertTrue($response->hasToolCalls());
        $this->assertSame([['id' => 'call_abc', 'name' => 'get_weather', 'arguments' => ['city' => 'Paris']]], $response->getToolCalls());
        $this->assertSame('tool_calls', $response->getFinishReason());

        $messages = $provider->buildToolResultMessages($response, ['call_abc' => ['temp_c' => 21]]);

        $this->assertSame('assistant', $messages[0]['role']);
        $this->assertSame('call_abc', $messages[0]['tool_calls'][0]['id']);
        $this->assertSame(['role' => 'tool', 'tool_call_id' => 'call_abc', 'content' => '{"temp_c":21}'], $messages[1]);
    }

    public function testOpenAIInvalidToolArgumentsThrow(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'k', 'moderate_input' => false], $this->httpFactory([
            [
                'choices' => [[
                    'message' => ['role' => 'assistant', 'tool_calls' => [[
                        'id' => 'call_1',
                        'function' => ['name' => 'get_weather', 'arguments' => '{"city":'],
                    ]]],
                    'finish_reason' => 'tool_calls',
                ]],
            ],
        ]));

        $this->expectException(UnserializableResponseException::class);
        $this->expectExceptionMessage("Arguments for tool 'get_weather' are not valid JSON");

        $provider->chat('Weather?', ['tools' => [self::WEATHER_TOOL]]);
    }

    public function testOpenAIResponseSchema(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'k', 'moderate_input' => false], $this->httpFactory([
            [
                'choices' => [['message' => ['role' => 'assistant', 'content' => '{"name":"Ada","age":36}'], 'finish_reason' => 'stop']],
            ],
        ]));

        $response = $provider->chat('Extract the person', [
            'response_schema' => ['name' => 'person', 'schema' => self::PERSON_SCHEMA],
        ]);

        $this->assertSame([
            'type' => 'json_schema',
            'json_schema' => ['name' => 'person', 'schema' => self::PERSON_SCHEMA, 'strict' => true],
        ], $this->sent[0]['response_format']);
        $this->assertSame(['name' => 'Ada', 'age' => 36], $response->getJson());
    }

    public function testAnthropicSendsToolsAndParsesToolUse(): void
    {
        $contentBlocks = [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
            ['type' => 'text', 'text' => 'Let me check.'],
            ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_weather', 'input' => ['city' => 'Paris']],
        ];

        $provider = new AnthropicProvider(['api_key' => 'k'], $this->httpFactory([
            [
                'id' => 'msg_1',
                'type' => 'message',
                'role' => 'assistant',
                'model' => 'claude-opus-5-5',
                'content' => $contentBlocks,
                'stop_reason' => 'tool_use',
                'usage' => ['input_tokens' => 30, 'output_tokens' => 12],
            ],
        ]));

        $response = $provider->chat('Weather in Paris?', [
            'tools' => [self::WEATHER_TOOL],
            'parallel_tool_calls' => false,
        ]);

        $this->assertSame([
            'name' => 'get_weather',
            'description' => 'Get the weather for a city',
            'input_schema' => self::WEATHER_TOOL['parameters'],
            'strict' => true,
        ], $this->sent[0]['tools'][0]);
        $this->assertSame(['type' => 'auto', 'disable_parallel_tool_use' => true], $this->sent[0]['tool_choice']);

        $this->assertSame('Let me check.', $response->getContent());
        $this->assertSame('tool_calls', $response->getFinishReason());
        $this->assertSame([['id' => 'toolu_1', 'name' => 'get_weather', 'arguments' => ['city' => 'Paris']]], $response->getToolCalls());

        $messages = $provider->buildToolResultMessages($response, ['toolu_1' => '21C and sunny']);

        // The full assistant turn, thinking block included, goes back unchanged
        $this->assertSame(['role' => 'assistant', 'content' => $contentBlocks], $messages[0]);
        $this->assertSame([
            'role' => 'user',
            'content' => [['type' => 'tool_result', 'tool_use_id' => 'toolu_1', 'content' => '21C and sunny']],
        ], $messages[1]);
    }

    public function testAnthropicToolChoiceMapping(): void
    {
        $responses = array_fill(0, 3, [
            'content' => [['type' => 'text', 'text' => 'ok']],
            'stop_reason' => 'end_turn',
        ]);

        $provider = new AnthropicProvider(['api_key' => 'k'], $this->httpFactory($responses));

        $provider->chat('a', ['tools' => [self::WEATHER_TOOL], 'tool_choice' => 'required']);
        $provider->chat('b', ['tools' => [self::WEATHER_TOOL], 'tool_choice' => ['name' => 'get_weather']]);
        $provider->chat('c', ['tools' => [self::WEATHER_TOOL], 'tool_choice' => 'none']);

        $this->assertSame(['type' => 'any'], $this->sent[0]['tool_choice']);
        $this->assertSame(['type' => 'tool', 'name' => 'get_weather'], $this->sent[1]['tool_choice']);
        $this->assertSame(['type' => 'none'], $this->sent[2]['tool_choice']);
    }

    public function testAnthropicResponseSchemaUsesOutputConfig(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'k'], $this->httpFactory([
            ['content' => [['type' => 'text', 'text' => '{"name":"Ada","age":36}']], 'stop_reason' => 'end_turn'],
        ]));

        $response = $provider->chat('Extract the person', ['response_schema' => self::PERSON_SCHEMA]);

        $this->assertSame(['format' => ['type' => 'json_schema', 'schema' => self::PERSON_SCHEMA]], $this->sent[0]['output_config']);
        $this->assertSame(['name' => 'Ada', 'age' => 36], $response->getJson());
    }

    public function testOllamaSendsToolsAndParsesToolCalls(): void
    {
        $provider = new OllamaProvider([], $this->httpFactory([
            [
                'model' => 'llama3',
                'message' => [
                    'role' => 'assistant',
                    'content' => '',
                    'tool_calls' => [['function' => ['name' => 'get_weather', 'arguments' => ['city' => 'Paris']]]],
                ],
                'done' => true,
                'done_reason' => 'stop',
            ],
        ], ['models' => [['name' => 'llama3:latest']]]));

        $response = $provider->chat('Weather in Paris?', [
            'model' => 'llama3',
            'tools' => [self::WEATHER_TOOL],
            'response_schema' => self::PERSON_SCHEMA,
        ]);

        $this->assertSame('function', $this->sent[0]['tools'][0]['type']);
        $this->assertSame('get_weather', $this->sent[0]['tools'][0]['function']['name']);
        $this->assertSame(self::PERSON_SCHEMA, $this->sent[0]['format']);
        $this->assertSame([['id' => 'call_0', 'name' => 'get_weather', 'arguments' => ['city' => 'Paris']]], $response->getToolCalls());

        $messages = $provider->buildToolResultMessages($response, ['call_0' => '21C']);

        $this->assertSame('assistant', $messages[0]['role']);
        $this->assertSame(['role' => 'tool', 'tool_name' => 'get_weather', 'content' => '21C'], $messages[1]);
    }

    public function testMissingToolResultThrows(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'k'], $this->httpFactory([
            ['content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_weather', 'input' => []]], 'stop_reason' => 'tool_use'],
        ]));

        $response = $provider->chat('Weather?', ['tools' => [self::WEATHER_TOOL]]);

        $this->expectException(InvalidArgumentException::class);

        $provider->buildToolResultMessages($response, []);
    }

    public function testToolsWithStreamingThrow(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'k']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tools are not supported with streaming');

        $provider->chat('Weather?', ['tools' => [self::WEATHER_TOOL], 'stream' => true]);
    }

    public function testToolWithoutParametersEncodesEmptyObject(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'k', 'moderate_input' => false], $this->httpFactory([
            ['choices' => [['message' => ['role' => 'assistant', 'content' => 'hi'], 'finish_reason' => 'stop']]],
        ]));

        $provider->chat('Hi', ['tools' => [['name' => 'now', 'description' => 'Current time']]]);

        $this->assertStringContainsString('"properties":{}', $this->lastRawBody);
    }

    public function testGetJsonThrowsOnNonJsonContent(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'k', 'moderate_input' => false], $this->httpFactory([
            ['choices' => [['message' => ['role' => 'assistant', 'content' => 'not json'], 'finish_reason' => 'stop']]],
        ]));

        $response = $provider->chat('Hi');

        $this->expectException(UnserializableResponseException::class);

        $response->getJson();
    }

    /**
     * HTTP factory whose POSTs return the given JSON bodies in order and record what was sent.
     *
     * @param   array       $postResponses  Response bodies for POST requests
     * @param   array|null  $getResponse    Response body for GET requests (Ollama model list)
     */
    private function httpFactory(array $postResponses, ?array $getResponse = null): HttpFactory
    {
        $http = $this->createStub(Http::class);

        $http->method('post')->willReturnCallback(function (string $url, $body) use (&$postResponses) {
            $this->lastRawBody = (string) $body;
            $this->sent[] = json_decode((string) $body, true);

            return $this->jsonResponse(array_shift($postResponses));
        });

        $http->method('get')->willReturnCallback(fn () => $this->jsonResponse($getResponse ?? []));

        $factory = $this->createStub(HttpFactory::class);
        $factory->method('getHttp')->willReturn($http);

        return $factory;
    }

    private function jsonResponse(array $payload): HttpResponse
    {
        $response = new HttpResponse('php://memory', 200, ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($payload));
        $response->getBody()->rewind();

        return $response;
    }
}
