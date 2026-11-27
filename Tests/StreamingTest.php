<?php

namespace Joomla\AI\Tests;

use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\RateLimitException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Provider\AnthropicProvider;
use Joomla\AI\Provider\OllamaProvider;
use Joomla\AI\Provider\OpenAIProvider;
use Joomla\AI\Tests\Stub\FakeStreamTransport;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

class StreamingTest extends TestCase
{
    public function testOpenAIChatStreamYieldsDeltasAndReturnsFinalResponse(): void
    {
        // Events split mid-line to check buffering across chunks
        $transport = new FakeStreamTransport([
            "data: {\"id\":\"c1\",\"model\":\"gpt-4o-mini\",\"choices\":[{\"delta\":{\"role\":\"assistant\"}}]}\n\n",
            "data: {\"choices\":[{\"delta\":{\"content\":\"Hel\"}}]}\n\ndata: {\"choices\":[{\"delta\":{\"con",
            "tent\":\"lo\"}}]}\n\n",
            "data: {\"choices\":[{\"delta\":{},\"finish_reason\":\"stop\"}]}\n\n",
            "data: {\"choices\":[],\"usage\":{\"prompt_tokens\":4,\"completion_tokens\":2,\"total_tokens\":6}}\n\n",
            "data: [DONE]\n\n",
        ]);

        $provider = new OpenAIProvider(['api_key' => 'k', 'moderate_input' => false, 'stream_transport' => $transport]);

        $stream = $provider->chatStream('Hi', ['model' => 'gpt-4o-mini']);
        $deltas = iterator_to_array($stream, false);
        $response = $stream->getReturn();

        $this->assertSame(['Hel', 'lo'], $deltas);
        $this->assertSame('Hello', $response->getContent());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertSame(['input_tokens' => 4, 'output_tokens' => 2, 'total_tokens' => 6], $response->getUsage());
        $this->assertTrue($transport->requests[0]['body']['stream']);
        $this->assertSame(['include_usage' => true], $transport->requests[0]['body']['stream_options']);
    }

    public function testChatWithStreamOptionCallsOnChunk(): void
    {
        $transport = new FakeStreamTransport([
            "data: {\"choices\":[{\"delta\":{\"content\":\"A\"}}]}\n\n",
            "data: {\"choices\":[{\"delta\":{\"content\":\"B\"},\"finish_reason\":\"stop\"}]}\n\n",
            "data: [DONE]\n\n",
        ]);

        $provider = new OpenAIProvider(['api_key' => 'k', 'moderate_input' => false, 'stream_transport' => $transport]);

        $received = [];
        $response = $provider->chat('Hi', [
            'stream' => true,
            'on_chunk' => function (string $delta) use (&$received) {
                $received[] = $delta;
            },
        ]);

        $this->assertSame(['A', 'B'], $received);
        $this->assertSame('AB', $response->getContent());
    }

    public function testAnthropicChatStreamSkipsThinkingAndCollectsUsage(): void
    {
        $transport = new FakeStreamTransport([
            "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"id\":\"msg_1\",\"model\":\"claude-opus-5-5\",\"role\":\"assistant\",\"type\":\"message\",\"usage\":{\"input_tokens\":10,\"output_tokens\":1}}}\n\n",
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"index\":0,\"delta\":{\"type\":\"thinking_delta\",\"thinking\":\"hmm\"}}\n\n",
            "event: ping\ndata: {\"type\":\"ping\"}\n\n",
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"index\":1,\"delta\":{\"type\":\"text_delta\",\"text\":\"Hi \"}}\n\n",
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"index\":1,\"delta\":{\"type\":\"text_delta\",\"text\":\"there\"}}\n\n",
            "event: message_delta\ndata: {\"type\":\"message_delta\",\"delta\":{\"stop_reason\":\"end_turn\",\"stop_sequence\":null},\"usage\":{\"output_tokens\":7}}\n\n",
            "event: message_stop\ndata: {\"type\":\"message_stop\"}\n\n",
        ]);

        $provider = new AnthropicProvider(['api_key' => 'k', 'stream_transport' => $transport]);

        $stream = $provider->chatStream('Hello');
        $deltas = iterator_to_array($stream, false);
        $response = $stream->getReturn();

        $this->assertSame(['Hi ', 'there'], $deltas);
        $this->assertSame('Hi there', $response->getContent());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertSame(['input_tokens' => 10, 'output_tokens' => 7, 'total_tokens' => 17], $response->getUsage());
        $this->assertSame('msg_1', $response->getMetadata()['id']);
        $this->assertTrue($transport->requests[0]['body']['stream']);
    }

    public function testAnthropicStreamErrorEventThrowsProviderException(): void
    {
        $transport = new FakeStreamTransport([
            "event: error\ndata: {\"type\":\"error\",\"error\":{\"type\":\"overloaded_error\",\"message\":\"Overloaded\"}}\n\n",
        ]);

        $provider = new AnthropicProvider(['api_key' => 'k', 'stream_transport' => $transport]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Overloaded');

        iterator_to_array($provider->chatStream('Hello'));
    }

    public function testStreamHttpErrorUsesStandardExceptionMapping(): void
    {
        $transport = new FakeStreamTransport(['{"error":{"message":"Rate limit exceeded","type":"rate_limit_error"}}'], 429);

        $provider = new AnthropicProvider(['api_key' => 'k', 'stream_transport' => $transport]);

        $this->expectException(RateLimitException::class);

        iterator_to_array($provider->chatStream('Hello'));
    }

    public function testInvalidStreamEventThrowsUnserializableResponseException(): void
    {
        $transport = new FakeStreamTransport(["data: {not json\n\n"]);

        $provider = new OpenAIProvider(['api_key' => 'k', 'moderate_input' => false, 'stream_transport' => $transport]);

        $this->expectException(UnserializableResponseException::class);

        iterator_to_array($provider->chatStream('Hi'));
    }

    public function testOllamaChatStreamReadsNdjson(): void
    {
        $transport = new FakeStreamTransport([
            "{\"model\":\"llama3\",\"message\":{\"role\":\"assistant\",\"content\":\"Hel\"},\"done\":false}\n{\"model\":\"llama3\",\"message\":{\"role\":\"assi",
            "stant\",\"content\":\"lo\"},\"done\":false}\n",
            "{\"model\":\"llama3\",\"message\":{\"role\":\"assistant\",\"content\":\"\"},\"done\":true,\"done_reason\":\"stop\",\"prompt_eval_count\":3,\"eval_count\":2}\n",
        ]);

        $provider = new OllamaProvider(['stream_transport' => $transport], $this->ollamaHttpFactory());

        $stream = $provider->chatStream('Hi', ['model' => 'llama3']);
        $deltas = iterator_to_array($stream, false);
        $response = $stream->getReturn();

        $this->assertSame(['Hel', 'lo'], $deltas);
        $this->assertSame('Hello', $response->getContent());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertSame(['input_tokens' => 3, 'output_tokens' => 2, 'total_tokens' => 5], $response->getUsage());
        $this->assertSame('http://localhost:11434/api/chat', $transport->requests[0]['url']);
    }

    /**
     * HTTP factory for the non-streaming Ollama calls (connection check and model list).
     */
    private function ollamaHttpFactory(): HttpFactory
    {
        $tags = new HttpResponse('php://memory', 200, ['Content-Type' => 'application/json']);
        $tags->getBody()->write(json_encode(['models' => [['name' => 'llama3:latest']]]));

        $http = $this->createStub(Http::class);
        $http->method('get')->willReturnCallback(function () use ($tags) {
            $tags->getBody()->rewind();

            return $tags;
        });

        $factory = $this->createStub(HttpFactory::class);
        $factory->method('getHttp')->willReturn($http);

        return $factory;
    }
}
