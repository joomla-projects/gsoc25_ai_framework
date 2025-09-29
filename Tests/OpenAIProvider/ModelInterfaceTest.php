<?php

namespace Joomla\AI\Tests;

use Joomla\AI\Provider\OpenAIProvider;
use PHPUnit\Framework\TestCase;

class ModelInterfaceTest extends TestCase
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

        $this->provider->setDefaultModel('gpt-3.5-turbo');
    }

    // Test 1: Will use default model since ('gpt-3.5-turbo')
    // no model is specified in the options 
    // and the default model is set
    public function testSetDefaultModelOption()
    {
        echo "Test 1: Simple prompt- Will use default model gpt-3.5-turbo\n";
        $response = $this->provider->chat("Hello! Which model are you using?");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
        $this->assertEquals('OpenAI', $response->getProvider());

        $metadata = $response->getMetadata();
        $this->assertStringContainsString('gpt-3.5-turbo', $metadata['model']);
    }

    // Test 2: This will override the default model since 
    // model is specified in the options ('gpt-4o-audio-preview')
    public function testOverrideDefaultModel()
    {
        echo "Test 2: Test chat completions audio capability- Will override the default and use gpt-4o-audio-preview model\n";
        $response = $this->provider->chat(
            "Say a few words on Joomla! for about 10 seconds in english.",
            [
                'model' => 'gpt-4o-audio-preview',
                'modalities' => ['text', 'audio'],
                'audio' => [
                    'voice' => 'alloy',
                    'format' => 'wav'
                ],
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OpenAI', $response->getProvider());

        $metadata = $response->getMetadata();
        $this->assertStringContainsString('gpt-4o-audio-preview', $metadata['model']);

        if (!isset($metadata['choices'][0]['message']['audio']['data'])) {
            $this->markTestSkipped('No audio returned by provider; audio model may not be available.');
            return;
        }

        $audioDataB64 = $metadata['choices'][0]['message']['audio']['data'];
        $audioData = base64_decode($audioDataB64, true);

        $this->assertNotFalse($audioData, 'Audio base64 decode failed');

        $outDir = __DIR__ . '/output';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }
        $outFile = $outDir . '/default_models_audio.wav';
        $written = file_put_contents($outFile, $audioData);
        $this->assertNotFalse($written, 'Failed to write audio file to disk');
        $this->assertFileExists($outFile);
    }

    // Test 3: Will use default model for the next call again since ('gpt-3.5-turbo')
    // no model is specified in the options 
    // and the default model was never unset
    public function testDefaultModelAgain()
    {
        echo "Test 3: Simple prompt- Will use default model gpt-3.5-turbo because default model was not unset\n";
        $response = $this->provider->chat("What is the capital of France?");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
        $this->assertEquals('OpenAI', $response->getProvider());

        $metadata = $response->getMetadata();
        $this->assertStringContainsString('gpt-3.5-turbo', $metadata['model']);
    }

    // Test 4: Uses method's default 
    // (no model in options, 
    // no default model, 
    // no config model)
    public function testModelUnset()
    {
        $this->provider->unsetDefaultModel();
        echo "Default model unset\n\n";

        echo "Test 4: generateImage with method's default model (should use 'dall-e-2')\n";
        $response = $this->provider->generateImage("Generate an image of a dog playing chess.");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OpenAI', $response->getProvider());
        $this->assertNotEmpty($response->getContent());

        $metadata = $response->getMetadata();
        $this->assertEquals('dall-e-2', $metadata['model']);
    }
}
