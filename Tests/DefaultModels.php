<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

echo "Testing Real OpenAI API Calls...\n\n";

$configFile = __DIR__ . '/../config.json';
$config = json_decode(file_get_contents($configFile), true);
$api_key = $config['openai_api_key'] ?? null;

try {
    // Create provider with your API key
    $provider = new OpenAIProvider([
        'api_key' => $api_key
    ]);
    
    echo "Provider created with API key\n";
    echo "Provider name: " . $provider->getName() . "\n\n";

    // To Do: Check if the provider is supported. Currently key set as env variables only
    // if (!OpenAIProvider::isSupported()) {
    //     throw new \Exception('OpenAI API is not supported or API key is missing.');
    // }

    // Set default model for all subsequent calls
    $provider->setDefaultModel('gpt-3.5-turbo');
    echo "Default model: " . $provider->getDefaultModel() . "\n\n";

    // Test 1: Simple prompt- Will use default model
    echo "Test 1: Simple prompt- Will use default model gpt-3.5-turbo\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->chat("Hello! How are you?");
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "\n";

    // Test 2: Multiple Response Choices  Will use default model
    echo "Test 2: Multiple Response Choices- Will use default model gpt-3.5-turbo\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->chat("Suggest a name for a movie based on pilots and astronauts");
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "\n";

    // Test 3: This will override the default and use gpt-4o-audio-preview model
    echo "Test 3: Test chat completions audio capability- Will override the default and use gpt-4o-audio-preview model\n";
    echo str_repeat('-', 50) . "\n";
    $response = $provider->chat("Say a few words on Joomla! for about 30 seconds in english.", [
        'model' => 'gpt-4o-audio-preview',
        'modalities' => ['text', 'audio'],
        'audio' => [
            'voice' => 'alloy',
            'format' => 'wav'
        ],
    ]);

    $metadata = $response->getMetadata();
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    $debugFile = "output/full_audio_response_structure.json";
    $fullStructure = [
        'response_class' => get_class($response),
        'content' => $response->getContent(),
        'status_code' => $response->getStatusCode(),
        'provider' => $response->getProvider(),
        'metadata' => $metadata
    ];
    file_put_contents($debugFile, json_encode($fullStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "Full response structure saved to: $debugFile\n";

    if (isset($metadata['choices'][0]['message']['audio']['data'])) {
        $audioData = $metadata['choices'][0]['message']['audio']['data'];
        $audioDatab64 = base64_decode($audioData, true);
        $audioFile = file_put_contents("output/chat_completions_audio.wav", $audioDatab64);
        echo "Audio file found and saved to: \"output/chat_completions_audio.wav\".\n";
    } else {
        echo "Audio file not found.\n";
    }
    echo "\n";

    // Test 4: Simple prompt- Will use default model
    echo "Test 4: Simple prompt- Will use default model gpt-3.5-turbo because default model was not unset\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->chat("What is the capital of France?");
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "\n";

    // Unset default model
    $provider->unsetDefaultModel();
    echo "Default model unset\n\n";

    // Test 5: This will use the provider's default (gpt-4o-mini)
    echo "Test 5: Simple prompt- Will use use the provider's default (gpt-4o-mini) because default model was unset\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->chat("What is the color of the sky?");
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "\n";

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "All Chat Completions API tests completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
