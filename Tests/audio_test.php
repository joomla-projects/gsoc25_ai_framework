<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

$api_key = 'xyz'; // Replace with your actual OpenAI API key

try {
    $provider = new OpenAIProvider([
        'api_key' => $api_key
    ]);

    echo "=== Testing OpenAI Text-to-Speech Capability ===\n\n";

    // Test 1: Basic Speech Generation
    echo "Test 1: Basic Speech Generation\n";
    echo "-------------------------------\n";

    $text = "Hello world! This is a test of the OpenAI text-to-speech capability.";
    $model = 'tts-1';
    $voice = 'alloy';
    
    $response = $provider->speech($text, $model, $voice);
    
    echo "Provider: " . $response->getProvider() . "\n";

    $metadata = $response->getMetadata();
    echo "Model Used: " . $metadata['model'] . "\n";
    echo "Voice Used: " . $metadata['voice'] . "\n";
    echo "Audio Format: " . $metadata['format'] . "\n";
    echo "Content Type: " . $metadata['content_type'] . "\n";
    echo "Audio Size: " . $metadata['size_bytes'] . " bytes\n";
    
    // Save audio to file
    $audioData = $response->getContent();
    file_put_contents('speech_test_1.mp3', $audioData);
    echo "Audio saved as 'speech_test_1.mp3'\n\n";
    
    echo str_repeat("=", 50) . "\n\n";

    // Test 2: Different Voice and Format
    echo "Test 2: Different Voice and WAV Format\n";
    echo "--------------------------------------\n";
    
    $text2 = "Hello world! This is a test of the OpenAI text-to-speech capability. This is a test with a different voice and WAV format.";
    $model2 = 'tts-1-hd';
    $voice2 = 'nova';
    $options2 = [
        'response_format' => 'wav',
        'speed' => 1.2
    ];

    $response2 = $provider->speech($text2, $model2, $voice2, $options2);
    
    $metadata2 = $response2->getMetadata();
    echo "Model Used: " . $metadata2['model'] . "\n";
    echo "Voice Used: " . $metadata2['voice'] . "\n";
    echo "Audio Format: " . $metadata2['format'] . "\n";
    echo "Content Type: " . $metadata2['content_type'] . "\n";
    echo "Speed: " . $metadata2['speed'] . "\n";
    echo "Audio Size: " . $metadata2['size_bytes'] . " bytes\n";
    
    // Save WAV file
    $audioData2 = $response2->getContent();
    file_put_contents('speech_test_2.wav', $audioData2);
    echo "Audio saved as 'speech_test_2.wav'\n\n";
    
    echo str_repeat("=", 50) . "\n\n";

    // Test 3: GPT-4o-mini-tts Model with Instructions
    echo "Test 3: GPT-4o-mini-tts with Instructions\n";
    echo "----------------------------------------\n";
    
    $text3 = "Hello world! This is a test of the OpenAI text-to-speech capability. This is a test with GPT-4o-mini-tts model and specific instructions to speak in a cheerful and professional customer service tone in the voice coral.";
    $model3 = 'gpt-4o-mini-tts';
    $voice3 = 'coral';
    $options3 = [
        'instructions' => 'Speak in a cheerful and professional customer service tone.',
        'response_format' => 'mp3',
        'speed' => 0.9
    ];
    
    $response3 = $provider->speech($text3, $model3, $voice3, $options3);
    
    $metadata3 = $response3->getMetadata();
    echo "Model Used: " . $metadata3['model'] . "\n";
    echo "Voice Used: " . $metadata3['voice'] . "\n";
    echo "Audio Format: " . $metadata3['format'] . "\n";
    echo "Content Type: " . $metadata3['content_type'] . "\n";
    echo "Speed: " . $metadata3['speed'] . "\n";
    echo "Audio Size: " . $metadata3['size_bytes'] . " bytes\n";
    echo "Instructions Used: " . ($metadata3['instructions'] ?? 'None') . "\n";
    
    // Save audio file
    $audioData3 = $response3->getContent();
    file_put_contents('speech_test_3.mp3', $audioData3);
    echo "Audio saved as 'speech_test_3.mp3'\n\n";

    echo str_repeat("=", 50) . "\n\n";

    // Test 4: Test Helper Methods
    echo "Test 4: Helper Methods\n";
    echo "---------------------\n";
    
    $availableModels = $provider->getAvailableModels();
    echo "Available models: " . implode(", ", $availableModels) . "\n";
    
    echo "Available Voices:\n";
    $voices = $provider->getAvailableVoices();
    foreach ($voices as $voice) {
        echo "  - $voice\n";
    }
    echo "\n";
    
    echo "Available TTS Models:\n";
    $models = $provider->getTTSModels();
    foreach ($models as $model) {
        echo "  - $model\n";
    }
    echo "\n";
    
    echo "Supported Audio Formats:\n";
    $formats = $provider->getSupportedAudioFormats();
    foreach ($formats as $format) {
        echo "  - $format\n";
    }
    echo "\n";
    
    echo "=== All Tests Completed Successfully! ===\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
