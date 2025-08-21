<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

$configFile = __DIR__ . '/../config.json';
$config = json_decode(file_get_contents($configFile), true);
$api_key = $config['openai_api_key'] ?? null;

try {
    echo "=== OpenAI Audio Translation Test ===\n\n";
    
    $provider = new OpenAIProvider([
        'api_key' => $api_key
    ]);

    echo "Step 0: Creating Test Audio File (german)\n";
    echo "----------------------------------------\n";
    
    // Create a german test audio file using TTS
    $testText = "Hallo, hiermit testen wir die Übersetzungsfunktion von OpenAI. Audio wird ins Englische übersetzt. Wir geben die Dateien und das zu verwendende Modell ein; aktuell ist nur Whisper-1 verfügbar. Ein optionaler Text dient zur Orientierung des Modells oder zur Fortsetzung eines vorherigen Audiosegments. Die Eingabeaufforderung sollte auf Englisch sein. Das Ausgabeformat kann in einer der folgenden Optionen gewählt werden: JSON, Text, SRT, Verbose_JSON oder VTT. Wir hoffen, dies funktioniert.";

    $speechResponse = $provider->speech($testText, ['model' => 'tts-1', 'voice' => 'alloy', 'response_format' => 'wav']);
    $speechResponse->saveFile('test_files/test_german_audio.wav');
    echo "Audio file created: test_files/test_german_audio.wav\n\n";

    echo str_repeat("=", 60) . "\n\n";
    
    // Test 1: Basic Translation (JSON response)
    echo "Test 1: Basic Translation (JSON Format)\n";
    echo "---------------------------------------\n";

    $testAudioFile = 'test_files/test_german_audio.wav';

    $response1 = $provider->translate($testAudioFile, ['model' => 'whisper-1']);

    echo "English Translation: " . $response1->getContent() . "\n";
    
    $metadata1 = $response1->getMetadata();
    echo "Model Used: " . $metadata1['model'] . "\n";
    echo "Response Format: " . $metadata1['response_format'] . "\n";
    
    echo str_repeat("-", 40) . "\n\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
