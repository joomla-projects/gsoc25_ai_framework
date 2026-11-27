<?php

namespace Joomla\AI\Tests;

use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Response\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-response-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->dir);
    }

    public function testSavesSingleBase64ImageAndCreatesDirectory(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=');
        $response = new Response(base64_encode($png), 'OpenAI', ['response_format' => 'b64_json', 'image_count' => 1]);

        $file = $this->dir . '/nested/image.png';
        $saved = $response->saveFile($file);

        $this->assertSame([$file], $saved);
        $this->assertSame($png, file_get_contents($file));
    }

    public function testSavesMultipleBase64ImagesWithNumberedNames(): void
    {
        $content = json_encode([base64_encode('first'), base64_encode('second')]);
        $response = new Response($content, 'OpenAI', ['response_format' => 'b64_json', 'image_count' => 2]);

        $saved = $response->saveFile($this->dir . '/cat.webp');

        $this->assertCount(2, $saved);
        $this->assertSame('first', file_get_contents($this->dir . DIRECTORY_SEPARATOR . 'cat_1.webp'));
        $this->assertSame('second', file_get_contents($this->dir . DIRECTORY_SEPARATOR . 'cat_2.webp'));
    }

    public function testSavesImageUrls(): void
    {
        $single = new Response('https://example.com/a.png', 'OpenAI', ['response_format' => 'url', 'image_count' => 1]);
        $single->saveFile($this->dir . '/single.txt');

        $multiple = new Response(json_encode(['https://example.com/a.png', 'https://example.com/b.png']), 'OpenAI', [
            'response_format' => 'url',
            'image_count' => 2,
        ]);
        $multiple->saveFile($this->dir . '/multiple.txt');

        $this->assertSame('  Image URL: https://example.com/a.png', file_get_contents($this->dir . '/single.txt'));
        $this->assertSame(
            '  Image 1: https://example.com/a.png' . PHP_EOL . '  Image 2: https://example.com/b.png',
            file_get_contents($this->dir . '/multiple.txt')
        );
    }

    public function testSavesBinaryAudioUnchanged(): void
    {
        $audio = "ID3\x00\x01binary\xff";
        $response = new Response($audio, 'OpenAI', ['data_type' => 'binary_audio']);

        $this->assertSame([$this->dir . '/speech.mp3'], $response->saveFile($this->dir . '/speech.mp3'));
        $this->assertSame($audio, file_get_contents($this->dir . '/speech.mp3'));
    }

    public function testSavesPlainText(): void
    {
        $response = new Response('Hello world', 'Anthropic');

        $response->saveFile($this->dir . '/reply.txt');

        $this->assertSame('Hello world', file_get_contents($this->dir . '/reply.txt'));
    }

    public function testEmptyContentIsNotSaved(): void
    {
        $response = new Response('', 'Anthropic');

        $this->assertFalse($response->saveFile($this->dir . '/empty.txt'));
        $this->assertFileDoesNotExist($this->dir . '/empty.txt');
    }

    public function testAccessorsAndNormalisedMetadata(): void
    {
        $response = new Response('{"a":1}', 'OpenAI', [
            'input_tokens' => 3,
            'output_tokens' => 4,
            'total_tokens' => 7,
            'finish_reason' => 'stop',
            'tool_calls' => [['id' => 'c1', 'name' => 'f', 'arguments' => []]],
            'embeddings' => [[0.1, 0.2]],
        ], 206);

        $this->assertSame('OpenAI', $response->getProvider());
        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame(['a' => 1], $response->getJson());
        $this->assertSame(['input_tokens' => 3, 'output_tokens' => 4, 'total_tokens' => 7], $response->getUsage());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertTrue($response->hasToolCalls());
        $this->assertSame([0.1, 0.2], $response->getEmbedding());
    }

    public function testDefaultsWhenMetadataMissing(): void
    {
        $response = new Response('text', 'Ollama');

        $this->assertSame(['input_tokens' => null, 'output_tokens' => null, 'total_tokens' => null], $response->getUsage());
        $this->assertNull($response->getFinishReason());
        $this->assertSame([], $response->getToolCalls());
        $this->assertSame([], $response->getEmbeddings());
        $this->assertSame([], $response->getEmbedding());

        $this->expectException(UnserializableResponseException::class);
        $response->getJson();
    }
}
