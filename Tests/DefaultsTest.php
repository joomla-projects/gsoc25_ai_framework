<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

$configFile = __DIR__ . '/../config.json';
$config = json_decode(file_get_contents($configFile), true);
$api_key = $config['openai_api_key'] ?? null;
$gpt_image_model_key = $config['gpt_image_model_key'];
$base_url = $config['openai_base_url'];

try {
    $provider = new OpenAIProvider([
        'api_key' => $api_key,
    ]);

    // ===========================================
    // Basic Chat Tests
    // ===========================================

    // Test 1: Basic Prompt Testing
    echo "Test 1: Basic Prompt Testing\n";
    echo str_repeat("-", 40) . "\n";

    $response1 = $provider->chat("Hi, Can you write a paragraph on the importance of AI in modern technology?");
    echo $response1->getContent();
    $response1->saveContentToFile('output/chat.txt');

    echo "\n" . str_repeat("=", 50) . "\n";
    
    // ===========================================
    // Image Generation Tests
    // ===========================================
    
    // Test 2: Basic Image Generation
    echo "Test 2: Basic Image Generation\n";
    echo str_repeat("-", 40) . "\n";

    $response2 = $provider->generateImage("Please generate an image for my blog post about my mount fuji hiking trip");
    $response2->saveContentToFile('output/mount_fuji.png');

    echo "\n" . str_repeat("=", 50) . "\n";

    // Test 3: DALL-E 3 with URL response
    echo "Test 3: DALL-E 3 with URL response...\n";
    echo str_repeat("-", 40) . "\n";

    $response3 = $provider->generateImage(
        "Can you generate an image of a slice of pizza riding a bicycle for my shopping website?", 
        [
            'model' => 'dall-e-2',
            'response_format' => 'url',
            'n' => 3,
        ]
    );
    $response3->saveContentToFile('output/thin_pizza.txt');

    echo "\n" . str_repeat("=", 50) . "\n\n";

    // ===========================================
    // Speech Generation Tests
    // ===========================================

    // Test 4: Basic Speech Generation
    echo "Test 4: Basic Speech Generation\n";
    echo str_repeat("-", 40) . "\n";

    $text = "Hello world! This is a test of the OpenAI text-to-speech capability.";
    $response4 = $provider->speech($text);
    $response4->saveContentToFile('output/speech_4.mp3');

    echo str_repeat("=", 50) . "\n\n";

    // ===========================================
    // Transcription Tests
    // ===========================================

    // Test 5: Basic Transcription
    echo "Test 5: Basic Transcription \n";
    echo str_repeat("-", 40) . "\n";
    
    $audioFile = 'test_files/test_audio.wav';
    $response5 = $provider->transcribe($audioFile);
    $response5->getContent();
    $response5->saveContentToFile('output/transcribed.txt');
    
    echo str_repeat("=", 50) . "\n\n";
    
    // ===========================================
    // Translation Tests
    // ===========================================

    // Test 6: Basic Translation
    echo "Test 6: Basic Translation \n";
    echo str_repeat("-", 40) . "\n";

    $testAudioFile = 'test_files/test_german_audio.wav';
    $response6 = $provider->translate($testAudioFile);
    echo $response6->getContent();
    $response6->saveContentToFile('output/translated.txt');

    echo "\n" . str_repeat("=", 50) . "\n\n";

    echo "=== All Tests Completed Successfully! ===\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
