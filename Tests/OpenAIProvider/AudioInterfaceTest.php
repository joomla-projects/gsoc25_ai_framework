<?php

namespace Joomla\AI\Tests\Provider\OpenAIProvider;

use Joomla\AI\AIFactory;
use PHPUnit\Framework\TestCase;

class AudioInterfaceTest extends TestCase
{
    protected $provider;
    protected $config;

    protected function setUp(): void
    {
        parent::setUp();

        $configFile = __DIR__ . '/../../config.json';
        $this->config = json_decode((string) file_get_contents($configFile), true);
        $apiKey = $this->config['openai_api_key'] ?? null;

        $this->provider = AIFactory::getAI('openai', ['api_key' => $apiKey]);
    }

    public function testBasicSpeechGeneration()
    {
        echo "Test 1: Basic Speech Generation\n";
        $text = "Hello world! This is a test of the OpenAI text-to-speech capability.";
        $options = ['model' => 'tts-1', 'voice' => 'alloy'];

        $response = $this->provider->speech($text, $options);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OpenAI', $response->getProvider());
        $this->assertNotEmpty($response->getContent(), 'Speech response content must not be empty');

        $meta = $response->getMetadata();
        $this->assertArrayHasKey('model', $meta);
        $this->assertArrayHasKey('voice', $meta);
        $this->assertEquals('alloy', $meta['voice']);
    }

    public function testDifferentVoiceAndWavFormat()
    {
        echo "Test 2: Different Voice and WAV Format\n";
        $text = "This is a WAV format test.";
        $options = ['model' => 'tts-1-hd', 'voice' => 'nova', 'response_format' => 'wav'];

        $response = $this->provider->speech($text, $options);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OpenAI', $response->getProvider());
        $this->assertNotEmpty($response->getContent());

        $meta = $response->getMetadata();
        $this->assertArrayHasKey('voice', $meta);
        $this->assertEquals('wav', $meta['format']);
        $this->assertEquals('nova', $meta['voice']);
    }

    public function testGpt4oMiniTtsWithInstructions()
    {
        echo "Test 3: GPT-4o-mini-tts with Instructions\n";
        $text = "Short instruction test for TTS model.";
        $options = [
            'model' => 'gpt-4o-mini-tts',
            'voice' => 'coral',
            'instructions' => 'Speak cheerfully.',
            'response_format' => 'mp3'
        ];

        $response = $this->provider->speech($text, $options);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OpenAI', $response->getProvider());
        $this->assertNotEmpty($response->getContent());

        $meta = $response->getMetadata();
        $this->assertArrayHasKey('model', $meta);
        $this->assertArrayHasKey('voice', $meta);
        $this->assertEquals('coral', $meta['voice']);
        $this->assertEquals('mp3', $meta['format']);
        $this->assertEquals('gpt-4o-mini-tts', $meta['model']);
        $this->assertArrayHasKey('instructions', $meta);
    }

    public function testHelperMethodsReturnArrays()
    {
        echo "Test 4: Helper Methods Return Arrays\n";
        $voices = $this->provider->getAvailableVoices();
        $this->assertIsArray($voices);

        if (method_exists($this->provider, 'getTTSModels')) {
            $models = $this->provider->getTTSModels();
            $this->assertIsArray($models);
        }

        if (method_exists($this->provider, 'getSupportedAudioFormats')) {
            $formats = $this->provider->getSupportedAudioFormats();
            $this->assertIsArray($formats);
        }

        if (method_exists($this->provider, 'getAvailableModels')) {
            $avail = $this->provider->getAvailableModels();
            $this->assertIsArray($avail);
        }
    }

    public function testAudioTranscription()
    {
        echo "Test 5: Audio Transcription in Multiple Formats\n";
        $audioFile = __DIR__ . '/../test_files/test_audio.wav';

        // Transcribe in multiple formats and assert basic expectations
        $formats = ['text', 'srt', 'vtt'];
        foreach ($formats as $format) {
            $options = ['model' => 'whisper-1', 'response_format' => $format];
            $response = $this->provider->transcribe($audioFile, $options);

            $this->assertEquals(200, $response->getStatusCode(), "Transcribe returned non-200 for format {$format}");
            $this->assertEquals('OpenAI', $response->getProvider(), "Unexpected provider for format {$format}");

            $meta = $response->getMetadata();
            $this->assertArrayHasKey('response_format', $meta, "Missing response_format metadata for {$format}");
            $this->assertNotEmpty($response->getContent(), "Empty transcription content for format {$format}");
        }
    }

    public function testCreateGermanAudioAndTranslate()
    {
        echo "Test 6: Create German Audio and Translate\n";

        $testText = "Hallo, hiermit testen wir die Übersetzungsfunktion von OpenAI. Audio wird ins Englische übersetzt. Wir geben die Dateien und das zu verwendende Modell ein; aktuell ist nur Whisper-1 verfügbar. Ein optionaler Text dient zur Orientierung des Modells oder zur Fortsetzung eines vorherigen Audiosegments. Die Eingabeaufforderung sollte auf Englisch sein. Das Ausgabeformat kann in einer der folgenden Optionen gewählt werden: JSON, Text, SRT, Verbose_JSON oder VTT. Wir hoffen, dies funktioniert.";
        $speechOptions = ['model' => 'tts-1', 'voice' => 'alloy', 'response_format' => 'wav'];

        $speechResponse = $this->provider->speech($testText, $speechOptions);
        $this->assertEquals(200, $speechResponse->getStatusCode());
        $this->assertEquals('OpenAI', $speechResponse->getProvider());
        $this->assertNotEmpty($speechResponse->getContent(), 'Speech response content must not be empty');

        $testDir = __DIR__ . '/../test_files';
        if (!is_dir($testDir)) {
            mkdir($testDir, 0777, true);
        }
        $audioFile = $testDir . '/test_german_audio.wav';

        if (method_exists($speechResponse, 'saveFile')) {
            $speechResponse->saveFile($audioFile);
            $this->assertFileExists($audioFile);
        } else {
            $written = file_put_contents($audioFile, $speechResponse->getContent());
            $this->assertNotFalse($written, 'Failed to write test audio file');
            $this->assertFileExists($audioFile);
        }

        $translateOptions = ['model' => 'whisper-1', 'response_format' => 'text'];
        $translateResponse = $this->provider->translate($audioFile, $translateOptions);

        $this->assertEquals(200, $translateResponse->getStatusCode());
        $this->assertEquals('OpenAI', $translateResponse->getProvider());
        $this->assertNotEmpty($translateResponse->getContent(), 'Translation content should not be empty');

        $meta = $translateResponse->getMetadata();
        $this->assertArrayHasKey('model', $meta);
        $this->assertArrayHasKey('response_format', $meta);
    }
}
