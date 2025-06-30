<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

$api_key = 'xyz';

try {
    echo "=== OpenAI Audio Translation Test ===\n\n";
    
    $provider = new OpenAIProvider([
        'api_key' => $api_key
    ]);

    echo "Step 0: Creating Test Audio File (french)\n";
    echo "----------------------------------------\n";
    
    // Create a french test audio file using TTS
    $testText = "Bonjour le monde. Ceci est un test de la fonctionnalité de traduction OpenAI. Nous testons la conversion de parole en texte en anglais.";
    
    $speechResponse = $provider->speech($testText, 'tts-1', 'alloy', ['response_format' => 'wav']);
    $audioData = $speechResponse->getContent();
    file_put_contents('test_french_audio.wav', $audioData);
    echo "Audio file created: test_french_audio.wav\n\n";

    echo str_repeat("=", 60) . "\n\n";
    
    // Test 1: Basic Translation (JSON response)
    echo "Test 1: Basic Translation (JSON Format)\n";
    echo "---------------------------------------\n";

    $testAudioFile = 'test_french_audio.wav';

    $response1 = $provider->translate($testAudioFile, 'whisper-1');
    
    echo "English Translation: " . $response1->getContent() . "\n";
    
    $metadata1 = $response1->getMetadata();
    echo "Model Used: " . $metadata1['model'] . "\n";
    echo "Response Format: " . $metadata1['response_format'] . "\n";
    
    echo str_repeat("-", 40) . "\n\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
