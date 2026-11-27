<?php

namespace Joomla\AI\Tests;

use Joomla\AI\Provider\AnthropicProvider;
use Joomla\AI\Provider\OllamaProvider;
use Joomla\AI\Provider\OpenAIProvider;
use Joomla\AI\Tests\Stub\FakeStreamTransport;
use Joomla\AI\Tests\Stub\RoutingHttpTrait;
use PHPUnit\Framework\TestCase;

class BaseUrlAndHttpOptionsTest extends TestCase
{
    use RoutingHttpTrait;

    public function testOpenAIUsesBaseUrlForAllEndpoints(): void
    {
        $base = 'https://proxy.example.com/openai/v1';

        $provider = new OpenAIProvider([
            'api_key' => 'k',
            'base_url' => $base . '/', // trailing slash is removed
            'moderate_input' => false,
        ], $this->routingHttpFactory([
            'POST ' . $base . '/chat/completions' => ['choices' => [['message' => ['content' => 'hi'], 'finish_reason' => 'stop']]],
            'GET ' . $base . '/models' => ['data' => [['id' => 'gpt-4o-mini']]],
            'POST ' . $base . '/embeddings' => ['data' => [['embedding' => [0.5], 'index' => 0]]],
        ]));

        $this->assertSame('hi', $provider->chat('Hello')->getContent());
        $this->assertSame(['gpt-4o-mini'], $provider->getAvailableModels());
        $this->assertSame([[0.5]], $provider->createEmbeddings('text', 'text-embedding-3-small')->getEmbeddings());

        $this->assertSame(
            [$base . '/chat/completions', $base . '/models', $base . '/embeddings'],
            array_column($this->requests, 'url')
        );
    }

    public function testAnthropicUsesBaseUrl(): void
    {
        $base = 'https://gateway.example.com/anthropic/v1';

        $provider = new AnthropicProvider(['api_key' => 'k', 'base_url' => $base . '/'], $this->routingHttpFactory([
            'POST ' . $base . '/messages' => ['content' => [['type' => 'text', 'text' => 'hi']], 'stop_reason' => 'end_turn'],
            'GET ' . $base . '/models?limit=1000' => ['data' => [['id' => 'claude-opus-5-5']]],
        ]));

        $this->assertSame('hi', $provider->chat('Hello')->getContent());
        $this->assertSame(['claude-opus-5-5'], $provider->getAvailableModels());
        $this->assertSame('k', $this->requests[0]['headers']['x-api-key']);
    }

    public function testOllamaUsesBaseUrl(): void
    {
        $base = 'http://gpu-box.local:11434';

        $provider = new OllamaProvider(['base_url' => $base . '/'], $this->routingHttpFactory([
            'GET ' . $base . '/api/tags' => ['models' => [['name' => 'llama3:latest']]],
            'POST ' . $base . '/api/chat' => ['message' => ['role' => 'assistant', 'content' => 'hi'], 'done' => true],
        ]));

        $this->assertSame('hi', $provider->chat('Hello', ['model' => 'llama3'])->getContent());

        foreach ($this->requests as $request) {
            $this->assertStringStartsWith($base . '/api/', $request['url']);
        }
    }

    public function testStreamingUsesBaseUrl(): void
    {
        $base = 'https://proxy.example.com/v1';
        $transport = new FakeStreamTransport(["data: {\"choices\":[{\"delta\":{\"content\":\"x\"}}]}\n\ndata: [DONE]\n\n"]);

        $provider = new OpenAIProvider([
            'api_key' => 'k',
            'base_url' => $base,
            'moderate_input' => false,
            'stream_transport' => $transport,
        ]);

        iterator_to_array($provider->chatStream('Hi'));

        $this->assertSame($base . '/chat/completions', $transport->requests[0]['url']);
    }

    public function testNoHttpOptionsByDefault(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'k'], $this->routingHttpFactory([
            'POST https://api.anthropic.com/v1/messages' => ['content' => [['type' => 'text', 'text' => 'hi']], 'stop_reason' => 'end_turn'],
        ]));

        $provider->chat('Hello');

        $this->assertSame([[]], $this->httpOptions);
    }

    public function testTimeoutAndHttpOptionsArePassedToHttpFactory(): void
    {
        $provider = new AnthropicProvider([
            'api_key' => 'k',
            'timeout' => 45,
            'http_options' => ['userAgent' => 'joomla-ai-test', 'transport.curl' => [CURLOPT_MAXREDIRS => 2]],
        ], $this->routingHttpFactory([
            'POST https://api.anthropic.com/v1/messages' => ['content' => [['type' => 'text', 'text' => 'hi']], 'stop_reason' => 'end_turn'],
        ]));

        $provider->chat('Hello');

        $this->assertSame([[
            'userAgent' => 'joomla-ai-test',
            'transport.curl' => [CURLOPT_MAXREDIRS => 2],
            'timeout' => 45,
        ]], $this->httpOptions);
    }
}
