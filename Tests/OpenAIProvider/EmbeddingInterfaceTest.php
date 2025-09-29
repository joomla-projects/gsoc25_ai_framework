<?php

namespace Joomla\AI\Tests\Provider\OpenAIProvider;

use Joomla\AI\AIFactory;
use PHPUnit\Framework\TestCase;

class EmbeddingInterfaceTest extends TestCase
{
    protected $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $configFile = __DIR__ . '/../../config.json';
        $config = json_decode((string) file_get_contents($configFile), true);
        $apiKey = $config['openai_api_key'] ?? null;

        $this->provider = AIFactory::getAI('openai', ['api_key' => $apiKey]);
    }

    public function testSingleTextEmbedding()
    {
        echo "Test 1: Single Text Embedding\n";
        $text = 'The quick brown fox jumps over the lazy dog';
        $response = $this->provider->createEmbeddings($text, 'text-embedding-ada-002');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OpenAI', $response->getProvider());
        $this->assertNotEmpty($response->getContent(), 'Response content should not be empty');

        $content = $response->getContent();
        $decoded = is_string($content) ? json_decode($content, true) : $content;

        $this->assertIsArray($decoded, 'Decoded response must be an array');
        $count = isset($decoded['data']) && is_array($decoded['data']) ? count($decoded['data']) : (array_values($decoded) === $decoded ? count($decoded) : 0);
        $this->assertGreaterThanOrEqual(1, $count, 'Expected at least one embedding item in the response');
    }

    public function testMultipleTextEmbeddings()
    {
        echo "Test 2: Multiple Text Embeddings\n";
        $texts = [
            "I love programming in PHP",
            "Python is great for machine learning",
            "JavaScript runs in the browser",
            "Cats are wonderful pets"
        ];

        $response = $this->provider->createEmbeddings($texts, 'text-embedding-ada-002');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OpenAI', $response->getProvider());
        $this->assertNotEmpty($response->getContent());

        $content = $response->getContent();
        $decoded = is_string($content) ? json_decode($content, true) : $content;

        $this->assertIsArray($decoded, 'Decoded response must be an array');

        $count = isset($decoded['data']) && is_array($decoded['data']) ? count($decoded['data']) : (array_values($decoded) === $decoded ? count($decoded) : 0);
        $this->assertGreaterThanOrEqual(count($texts), $count, 'Did not receive expected number of embedding items');
    }
}
