<?php

namespace Joomla\AI\Tests;

use Joomla\AI\Provider\OpenAIProvider;
use PHPUnit\Framework\TestCase;

class ChatInterfaceTest extends TestCase
{
    protected $config;
    protected $api_key;
    protected $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $configFile   = __DIR__ . '/../config.json';
        $this->config = json_decode(file_get_contents($configFile), true);
        $this->api_key = $this->config['openai_api_key'] ?? null;

        $this->provider = new OpenAIProvider([
            'api_key' => $this->api_key
        ]);
    }

    public function testSimpleChatCompletion()
    {
        echo "Test 1: Test chat method with a simple prompt\n";
        $response = $this->provider->chat("Hello! How are you?");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
        $this->assertEquals('OpenAI', $response->getProvider());

        $metadata = $response->getMetadata();
        $this->assertArrayHasKey('model', $metadata);
        $this->assertArrayHasKey('usage', $metadata);
    }

    public function testMultipleResponseChoices()
    {
        echo "Test 2: Test Multiple Response Choices (n parameter)\n";
        $response = $this->provider->chat("Suggest a name for a movie based on pilots and astronauts", [
            'n' => 3,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
        $this->assertEquals('OpenAI', $response->getProvider());

        $metadata = $response->getMetadata();
        $this->assertArrayHasKey('model', $metadata);
        $this->assertTrue(isset($metadata['choices']) && is_array($metadata['choices']));
        $this->assertEquals(3, count($metadata['choices'])); // 3 choices returned

        // Ensure each returned choice has content if present
        foreach ($metadata['choices'] as $choice) {
            $this->assertArrayHasKey('message', $choice);
            $this->assertArrayHasKey('content', $choice['message'] ?? []);
        }
    }

    public function testChatCompletionsAudioCapability()
    {
        echo "Test 3: Test chat completions audio capability\n";
        $response = $this->provider->chat("Say a few words on Joomla! for about 30 seconds in english.", [
            'model' => 'gpt-4o-audio-preview',
            'modalities' => ['text', 'audio'],
            'audio' => [
                'voice' => 'alloy',
                'format' => 'wav'
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OpenAI', $response->getProvider());

        $metadata = $response->getMetadata();

        if (!isset($metadata['choices'][0]['message']['audio']['data'])) {
            $this->markTestSkipped('No audio data returned by provider; audio model may not be available.');
            return;
        }

        $audioDataB64 = $metadata['choices'][0]['message']['audio']['data'];
        $audioData = base64_decode($audioDataB64, true);

        $this->assertNotFalse($audioData, 'Audio base64 decode failed');
        $this->assertGreaterThan(0, strlen($audioData), 'Audio payload is empty');

        // Optionally save for inspection
        $outDir = __DIR__ . '/output';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }
       $outFile = $outDir . '/chat_completions_audio.wav';
        $written = file_put_contents($outFile, $audioData);
        $this->assertNotFalse($written, 'Failed to write audio file to disk');
        $this->assertFileExists($outFile);
    }

    public function testVisionWithImageURL()
    {
        echo "Test 4: Vision with image URL\n";
        $imageUrl = "https://upload.wikimedia.org/wikipedia/commons/e/eb/Ash_Tree_-_geograph.org.uk_-_590710.jpg";

        $response = $this->provider->vision("What do you see in this image?", $imageUrl);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(strlen($response->getBody()) > 10);
        $this->assertEquals('OpenAI', $response->getProvider());
        $this->assertArrayHasKey('model', $response->getMetadata());
        $this->assertArrayHasKey('usage', $response->getMetadata());
    }

    public function testVisionWithSpecificModel()
    {
        echo "Test 5: Vision with specific model (gpt-4o)\n";
        $imageUrl = "https://upload.wikimedia.org/wikipedia/commons/e/eb/Ash_Tree_-_geograph.org.uk_-_590710.jpg";

        $response = $this->provider->vision(
            "Describe the colors and mood of this image.",
            $imageUrl,
            ['model' => 'gpt-4o', 'max_tokens' => 100]
        );

        echo "Vision API call successful!\n";
        echo "Response: " . $response->getContent() . "\n";
        echo "Provider: " . $response->getProvider() . "\n";

        $metadata = $response->getMetadata();
        if (!empty($metadata)) {
            echo "Model used: " . ($metadata['model']) . "\n";
            if (isset($metadata['usage'])) {
                echo "Tokens used: " . ($metadata['usage']['total_tokens']) . "\n";
            }
        }

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(strlen($response->getBody()) > 10);
        $this->assertEquals('OpenAI', $response->getProvider());
        $this->assertArrayHasKey('model', $metadata);
        $this->assertArrayHasKey('usage', $metadata);
    }
}
