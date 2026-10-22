<?php

namespace Joomla\AI\Tests\OllamaProvider;

use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Provider\OllamaProvider;
use Joomla\AI\Tests\Stub\RoutingHttpTrait;
use PHPUnit\Framework\TestCase;

class OllamaTest extends TestCase
{
    use RoutingHttpTrait;

    private const BASE = 'http://localhost:11434';

    private const TAGS = ['models' => [['name' => 'llama3:latest'], ['name' => 'nomic-embed-text:latest']]];

    public function testChatReturnsContentAndNormalisedMetadata(): void
    {
        $provider = $this->provider([
            'POST ' . self::BASE . '/api/chat' => [
                'model' => 'llama3',
                'created_at' => '2026-10-22T10:00:00Z',
                'message' => ['role' => 'assistant', 'content' => 'Hello!'],
                'done' => true,
                'done_reason' => 'stop',
                'prompt_eval_count' => 7,
                'eval_count' => 3,
            ],
        ]);

        $response = $provider->chat('Hi', ['model' => 'llama3']);

        $this->assertSame('Hello!', $response->getContent());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertSame(['input_tokens' => 7, 'output_tokens' => 3, 'total_tokens' => 10], $response->getUsage());
        $this->assertSame('assistant', $response->getMetadata()['role']);

        $sent = $this->sentJson(count($this->requests) - 1);
        $this->assertSame([['role' => 'user', 'content' => 'Hi']], $sent['messages']);
        $this->assertFalse($sent['stream']);
    }

    public function testGenerateReturnsResponseText(): void
    {
        $provider = $this->provider([
            'POST ' . self::BASE . '/api/generate' => [
                'model' => 'llama3',
                'response' => 'Once upon a time',
                'done' => true,
                'done_reason' => 'length',
                'context' => [1, 2, 3],
            ],
        ]);

        $response = $provider->generate('Tell a story', ['model' => 'llama3', 'system' => 'Be brief']);

        $this->assertSame('Once upon a time', $response->getContent());
        $this->assertSame('length', $response->getFinishReason());
        $this->assertSame([1, 2, 3], $response->getMetadata()['context']);
        $this->assertSame('Be brief', $this->sentJson(count($this->requests) - 1)['system']);
    }

    public function testMissingModelIsPulledBeforeChat(): void
    {
        $provider = $this->provider([
            // No local models, so the requested one has to be pulled
            'GET ' . self::BASE . '/api/tags' => ['models' => []],
            'POST ' . self::BASE . '/api/pull' => $this->jsonResponse(
                "{\"status\":\"pulling manifest\"}\n{\"status\":\"success\"}\n"
            ),
            'POST ' . self::BASE . '/api/chat' => ['message' => ['role' => 'assistant', 'content' => 'hi'], 'done' => true],
        ]);

        $this->assertSame('hi', $provider->chat('Hi', ['model' => 'mistral'])->getContent());

        $pull = array_values(array_filter($this->requests, fn ($r) => str_ends_with($r['url'], '/api/pull')));
        $this->assertCount(1, $pull);
        $this->assertSame(['model' => 'mistral'], json_decode($pull[0]['body'], true));
    }

    public function testPullOfUnknownModelThrowsInvalidArgument(): void
    {
        $provider = $this->provider([
            'GET ' . self::BASE . '/api/tags' => ['models' => []],
            'POST ' . self::BASE . '/api/pull' => $this->jsonResponse("{\"error\":\"pull model manifest: file does not exist\"}\n"),
        ]);

        $this->expectException(InvalidArgumentException::class);

        $provider->pullModel('does-not-exist');
    }

    public function testListModels(): void
    {
        $provider = $this->provider([
            'GET ' . self::BASE . '/api/ps' => ['models' => [['name' => 'llama3:latest', 'size' => 1]]],
        ]);

        $this->assertSame(['llama3:latest', 'nomic-embed-text:latest'], $provider->getAvailableModels());
        $this->assertSame(['llama3:latest'], $provider->getRunningModels());
    }

    public function testHasModelTreatsMissingTagAsLatest(): void
    {
        $provider = $this->provider([
            'GET ' . self::BASE . '/api/tags' => ['models' => [
                ['name' => 'llama3:latest'],
                ['name' => 'mistral:7b'],
                ['name' => 'registry.local:5000/team/coder'],
            ]],
        ]);

        $this->assertTrue($provider->hasModel('llama3'));
        $this->assertTrue($provider->hasModel('llama3:latest'));
        $this->assertTrue($provider->hasModel('mistral:7b'));
        $this->assertFalse($provider->hasModel('mistral'));
        $this->assertFalse($provider->hasModel('mistral:latest'));
        // The ":5000" port is not a tag
        $this->assertTrue($provider->hasModel('registry.local:5000/team/coder:latest'));
        $this->assertFalse($provider->hasModel('gemma'));
    }

    public function testCopyAndDeleteModel(): void
    {
        $provider = $this->provider([
            'POST ' . self::BASE . '/api/copy' => $this->jsonResponse(''),
            'DELETE ' . self::BASE . '/api/delete' => $this->jsonResponse(''),
        ]);

        $this->assertTrue($provider->copyModel('llama3', 'llama3-backup'));
        $this->assertTrue($provider->deleteModel('llama3-backup'));

        $copy = array_values(array_filter($this->requests, fn ($r) => str_ends_with($r['url'], '/api/copy')));
        $delete = array_values(array_filter($this->requests, fn ($r) => str_ends_with($r['url'], '/api/delete')));
        $this->assertSame(['source' => 'llama3', 'destination' => 'llama3-backup'], json_decode($copy[0]['body'], true));
        $this->assertSame(['model' => 'llama3-backup'], json_decode($delete[0]['body'], true));
    }

    public function testCopyAndDeleteOfMissingModelThrowInvalidArgument(): void
    {
        $notFound = $this->jsonResponse(['error' => "model 'ghost' not found"], 404);

        $provider = $this->provider([
            'POST ' . self::BASE . '/api/copy' => $notFound,
            'DELETE ' . self::BASE . '/api/delete' => $notFound,
        ]);

        try {
            $provider->copyModel('ghost', 'ghost2');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("'ghost'", $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $provider->deleteModel('ghost');
    }

    public function testVisionSendsBase64ImageWithoutDataUriPrefix(): void
    {
        $provider = $this->provider([
            'POST ' . self::BASE . '/api/chat' => ['message' => ['role' => 'assistant', 'content' => 'A cat'], 'done' => true],
        ]);

        $base64 = base64_encode('fake-image-bytes');
        $response = $provider->vision('What is this?', 'data:image/png;base64,' . $base64, ['model' => 'llama3']);

        $this->assertSame('A cat', $response->getContent());
        $this->assertSame([$base64], $this->sentJson(count($this->requests) - 1)['messages'][0]['images']);
    }

    public function testVisionRejectsUrls(): void
    {
        $provider = $this->provider([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URLs and file paths are not supported');

        $provider->vision('What is this?', 'https://example.com/cat.png', ['model' => 'llama3']);
    }

    public function testCreateEmbeddings(): void
    {
        $provider = $this->provider([
            'POST ' . self::BASE . '/api/embed' => [
                'model' => 'nomic-embed-text',
                'embeddings' => [[0.1, 0.2], [0.3, 0.4]],
                'prompt_eval_count' => 6,
            ],
        ]);

        $response = $provider->createEmbeddings(['a', 'b'], 'nomic-embed-text', ['dimensions' => 2, 'ignored' => true]);

        $this->assertSame([[0.1, 0.2], [0.3, 0.4]], $response->getEmbeddings());
        $this->assertSame(6, $response->getUsage()['input_tokens']);

        $sent = $this->sentJson(count($this->requests) - 1);
        $this->assertSame(['model' => 'nomic-embed-text', 'input' => ['a', 'b'], 'dimensions' => 2], $sent);
    }

    public function testGetEmbeddingModelsUsesCapabilities(): void
    {
        $provider = $this->provider([
            'POST ' . self::BASE . '/api/show' => ['sequence' => [
                ['capabilities' => ['completion', 'tools']],
                ['capabilities' => ['embedding']],
            ]],
        ]);

        $this->assertSame(['nomic-embed-text:latest'], $provider->getEmbeddingModels());
    }

    public function testServerNotRunningThrowsAuthenticationException(): void
    {
        $provider = new OllamaProvider(['max_retries' => 0], $this->routingHttpFactory([
            'GET ' . self::BASE . '/api/tags' => new \RuntimeException('Connection refused'),
        ]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Ollama server not running');

        $provider->chat('Hi', ['model' => 'llama3']);
    }

    public function testErrorInResponseBodyThrowsProviderException(): void
    {
        $provider = $this->provider([
            'POST ' . self::BASE . '/api/chat' => ['error' => 'model requires more system memory'],
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('model requires more system memory');

        $provider->chat('Hi', ['model' => 'llama3']);
    }

    public function testIsSupportedReturnsFalseWhenServerUnreachable(): void
    {
        // Port 9 (discard) is not an Ollama server
        $this->assertFalse(OllamaProvider::isSupported('http://127.0.0.1:9'));
    }

    /**
     * Provider whose /api/tags lists llama3 and nomic-embed-text, plus the given routes.
     */
    private function provider(array $routes): OllamaProvider
    {
        $routes += ['GET ' . self::BASE . '/api/tags' => self::TAGS];

        return new OllamaProvider(['max_retries' => 0], $this->routingHttpFactory($routes));
    }
}
