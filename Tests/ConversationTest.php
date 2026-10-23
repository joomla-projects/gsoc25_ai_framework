<?php

namespace Joomla\AI\Tests;

use Joomla\AI\AI;
use Joomla\AI\Conversation\Conversation;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Provider\AnthropicProvider;
use Joomla\AI\Provider\OllamaProvider;
use Joomla\AI\Provider\OpenAIProvider;
use Joomla\AI\Tests\Stub\FakeStreamTransport;
use Joomla\AI\Tests\Stub\RoutingHttpTrait;
use Joomla\AI\Tests\Stub\TestProvider;
use PHPUnit\Framework\TestCase;

class ConversationTest extends TestCase
{
    use RoutingHttpTrait;

    private const ANTHROPIC = 'POST https://api.anthropic.com/v1/messages';

    public function testAnthropicConversationSendsHistoryAndSystem(): void
    {
        $firstBlocks = [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
            ['type' => 'text', 'text' => 'Hi Ada!'],
        ];

        $ai = new AI(new AnthropicProvider(['api_key' => 'k'], $this->routingHttpFactory([
            self::ANTHROPIC => ['sequence' => [
                $this->claudeReply($firstBlocks, 10, 4),
                $this->claudeReply([['type' => 'text', 'text' => 'Your name is Ada.']], 20, 5),
            ]],
        ])));

        $conversation = $ai->conversation(['system' => 'Be friendly.', 'max_tokens' => 200]);

        $this->assertSame('Hi Ada!', $conversation->send('My name is Ada.')->getContent());
        $this->assertSame('Your name is Ada.', $conversation->send('What is my name?')->getContent());

        $second = $this->sentJson(1);
        $this->assertSame('Be friendly.', $second['system']);
        $this->assertSame(200, $second['max_tokens']);
        $this->assertSame([
            ['role' => 'user', 'content' => 'My name is Ada.'],
            // The assistant turn goes back exactly as returned, thinking block included
            ['role' => 'assistant', 'content' => $firstBlocks],
            ['role' => 'user', 'content' => 'What is my name?'],
        ], $second['messages']);

        $this->assertCount(4, $conversation->getMessages());
        $this->assertSame(['input_tokens' => 30, 'output_tokens' => 9, 'total_tokens' => 39], $conversation->getUsage());
    }

    public function testOpenAIConversationPrependsSystemMessage(): void
    {
        $reply = fn (string $text) => ['choices' => [['message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']]];

        $ai = new AI(new OpenAIProvider(['api_key' => 'k', 'moderate_input' => false], $this->routingHttpFactory([
            'POST https://api.openai.com/v1/chat/completions' => ['sequence' => [$reply('Hello'), $reply('Bye')]],
        ])));

        $conversation = $ai->conversation(['system' => 'You are terse.']);
        $conversation->send('Hi');
        $conversation->send('Goodbye');

        $this->assertSame([
            ['role' => 'system', 'content' => 'You are terse.'],
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => 'Hello'],
            ['role' => 'user', 'content' => 'Goodbye'],
        ], $this->sentJson(1)['messages']);
    }

    public function testOllamaConversationPrependsSystemMessage(): void
    {
        $reply = fn (string $text) => ['message' => ['role' => 'assistant', 'content' => $text], 'done' => true];

        $ai = new AI(new OllamaProvider(['max_retries' => 0], $this->routingHttpFactory([
            'GET http://localhost:11434/api/tags' => ['models' => [['name' => 'llama3:latest']]],
            'POST http://localhost:11434/api/chat' => ['sequence' => [$reply('One'), $reply('Two')]],
        ])));

        $conversation = $ai->conversation(['system' => 'Count.', 'model' => 'llama3']);
        $conversation->send('Start');
        $conversation->send('Next');

        $chatRequests = array_values(array_filter($this->requests, fn ($r) => str_ends_with($r['url'], '/api/chat')));
        $this->assertSame([
            ['role' => 'system', 'content' => 'Count.'],
            ['role' => 'user', 'content' => 'Start'],
            ['role' => 'assistant', 'content' => 'One'],
            ['role' => 'user', 'content' => 'Next'],
        ], json_decode($chatRequests[1]['body'], true)['messages']);
    }

    public function testToolCallsInAConversation(): void
    {
        $toolUse = [
            ['type' => 'text', 'text' => 'Checking.'],
            ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_weather', 'input' => ['city' => 'Paris']],
        ];

        $ai = new AI(new AnthropicProvider(['api_key' => 'k'], $this->routingHttpFactory([
            self::ANTHROPIC => ['sequence' => [
                $this->claudeReply($toolUse, 10, 5, 'tool_use'),
                $this->claudeReply([['type' => 'text', 'text' => 'It is 21C in Paris.']], 15, 6),
            ]],
        ])));

        $tools = [['name' => 'get_weather', 'parameters' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]]]];
        $conversation = $ai->conversation(['tools' => $tools]);

        $response = $conversation->send('Weather in Paris?');

        $this->assertTrue($response->hasToolCalls());
        $this->assertTrue($conversation->hasPendingToolCalls());

        try {
            $conversation->send('Hello?');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('submitToolResults()', $e->getMessage());
        }

        $final = $conversation->submitToolResults(['toolu_1' => '21C']);

        $this->assertSame('It is 21C in Paris.', $final->getContent());
        $this->assertFalse($conversation->hasPendingToolCalls());

        $this->assertSame([
            ['role' => 'user', 'content' => 'Weather in Paris?'],
            ['role' => 'assistant', 'content' => $toolUse],
            ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'toolu_1', 'content' => '21C']]],
        ], $this->sentJson(1)['messages']);

        $this->assertCount(4, $conversation->getMessages());
    }

    public function testOpenAIToolResultsAreNotSentToModeration(): void
    {
        $moderation = ['results' => [['flagged' => false]]];

        $ai = new AI(new OpenAIProvider(['api_key' => 'k'], $this->routingHttpFactory([
            'POST https://api.openai.com/v1/moderations' => $moderation,
            'POST https://api.openai.com/v1/chat/completions' => ['sequence' => [
                ['choices' => [[
                    'message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
                        'id' => 'call_1',
                        'type' => 'function',
                        'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Paris"}'],
                    ]]],
                    'finish_reason' => 'tool_calls',
                ]]],
                ['choices' => [['message' => ['role' => 'assistant', 'content' => '21C'], 'finish_reason' => 'stop']]],
            ]],
        ])));

        $conversation = $ai->conversation(['tools' => [['name' => 'get_weather']]]);
        $conversation->send('Weather in Paris?');
        $conversation->submitToolResults(['call_1' => ['temp_c' => 21]]);

        $urls = array_column($this->requests, 'url');
        $this->assertSame(1, count(array_filter($urls, fn ($url) => str_ends_with($url, '/moderations'))));

        $last = json_decode(end($this->requests)['body'], true)['messages'];
        $this->assertSame('call_1', $last[1]['tool_calls'][0]['id']);
        $this->assertSame(['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '{"temp_c":21}'], $last[2]);
    }

    public function testSubmitToolResultsWithoutPendingCallsThrows(): void
    {
        $conversation = new Conversation(new AnthropicProvider(['api_key' => 'k']));

        $this->expectException(InvalidArgumentException::class);

        $conversation->submitToolResults(['x' => 'y']);
    }

    public function testMaxTurnsKeepsTheLastTurns(): void
    {
        $ai = new AI(new AnthropicProvider(['api_key' => 'k'], $this->routingHttpFactory([
            self::ANTHROPIC => ['sequence' => [
                $this->claudeReply([['type' => 'text', 'text' => 'A1']]),
                $this->claudeReply([['type' => 'text', 'text' => 'A2']]),
                $this->claudeReply([['type' => 'text', 'text' => 'A3']]),
            ]],
        ])));

        $conversation = $ai->conversation(['max_turns' => 2]);
        $conversation->send('Q1');
        $conversation->send('Q2');
        $conversation->send('Q3');

        $third = $this->sentJson(2);
        $this->assertSame(['Q2', 'A2', 'Q3'], array_map(
            fn ($m) => is_string($m['content']) ? $m['content'] : $m['content'][0]['text'],
            $third['messages']
        ));
        $this->assertArrayNotHasKey('max_turns', $third);
    }

    public function testStreamingAddsTheReplyToTheHistory(): void
    {
        $transport = new FakeStreamTransport([
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"Hel\"}}\n\n",
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"lo\"}}\n\n",
            "event: message_delta\ndata: {\"type\":\"message_delta\",\"delta\":{\"stop_reason\":\"end_turn\"},\"usage\":{\"output_tokens\":2}}\n\n",
        ]);

        $conversation = (new AI(new AnthropicProvider(['api_key' => 'k', 'stream_transport' => $transport])))->conversation();

        $stream = $conversation->stream('Hi');
        $this->assertSame(['Hel', 'lo'], iterator_to_array($stream, false));
        $this->assertSame('Hello', $stream->getReturn()->getContent());

        $this->assertSame([
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => 'Hello'],
        ], $conversation->getMessages());
        $this->assertSame([['role' => 'user', 'content' => 'Hi']], $transport->requests[0]['body']['messages']);
    }

    public function testSaveAndRestore(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'k'], $this->routingHttpFactory([
            self::ANTHROPIC => ['sequence' => [
                $this->claudeReply([['type' => 'text', 'text' => 'Noted.']], 5, 1),
                $this->claudeReply([['type' => 'text', 'text' => 'Blue.']], 9, 1),
            ]],
        ]));

        $conversation = new Conversation($provider, ['system' => 'Remember things.']);
        $conversation->send('My favourite colour is blue.');

        // Through JSON, as when stored in a session or database
        $stored = json_decode(json_encode($conversation), true);
        $this->assertSame('Anthropic', $stored['provider']);

        $restored = Conversation::fromArray($provider, $stored);
        $restored->send('What is my favourite colour?');

        $this->assertSame('Remember things.', $this->sentJson(1)['system']);
        $this->assertCount(3, $this->sentJson(1)['messages']);
        $this->assertSame(['input_tokens' => 14, 'output_tokens' => 2, 'total_tokens' => 16], $restored->getUsage());
    }

    public function testPerTurnOptionsAndClear(): void
    {
        $conversation = new Conversation(new AnthropicProvider(['api_key' => 'k'], $this->routingHttpFactory([
            self::ANTHROPIC => ['sequence' => [
                $this->claudeReply([['type' => 'text', 'text' => 'a']]),
                $this->claudeReply([['type' => 'text', 'text' => 'b']]),
            ]],
        ])), ['max_tokens' => 100]);

        $conversation->send('one', ['max_tokens' => 50]);
        $this->assertSame(50, $this->sentJson(0)['max_tokens']);

        $this->assertSame($conversation, $conversation->clear());
        $this->assertSame([], $conversation->getMessages());
        $this->assertSame(['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0], $conversation->getUsage());

        $conversation->send('two');
        $this->assertSame(100, $this->sentJson(1)['max_tokens']);
        $this->assertSame([['role' => 'user', 'content' => 'two']], $this->sentJson(1)['messages']);
    }

    public function testConversationNeedsAChatProvider(): void
    {
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Test does not support chat');

        (new AI(new TestProvider()))->conversation();
    }

    private function claudeReply(array $content, int $input = 1, int $output = 1, string $stopReason = 'end_turn'): array
    {
        return [
            'id' => 'msg_' . uniqid(),
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => $content,
            'stop_reason' => $stopReason,
            'usage' => ['input_tokens' => $input, 'output_tokens' => $output],
        ];
    }
}
