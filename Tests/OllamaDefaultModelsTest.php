<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OllamaProvider;

echo "Testing Real Ollama API Calls...\n\n";

try {
    // Create provider with your API key
    $provider = new OllamaProvider();
    
    echo "Provider created\n";
    echo "Provider name: " . $provider->getName() . "\n\n";

    // Set default model for all subsequent calls
    $provider->setDefaultModel('tinyllama');
    echo "Default model: " . $provider->getDefaultModel() . "\n\n";

    // Test 1: Simple prompt- Will use default model
    echo "Test 1: Simple prompt- Will use default model tinyllama\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->chat("Hello! How are you?");
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "\n";

    // Test 2: Multiple Response Choices  Will use default model
    echo "Test 2: Multiple Response Choices- Will use default model tinyllama\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->generate("Suggest a name for a movie based on pilots and astronauts");
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "\n";

    // Test 3: This will override the default and use llama model
    echo "Test 3: Test chat completions audio capability- Will override the default and use llama model\n";
    echo str_repeat('-', 50) . "\n";
    $response = $provider->chat("Write a few words on Joomla! in english.", [
        'model' => 'deepseek-r1:1.5b'
    ]);

    $metadata = $response->getMetadata();
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "\n";

    // Test 4: Simple prompt- Will use default model
    echo "Test 4: Simple prompt- Will use default model tinyllama because default model was not unset\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->chat("What is the capital of France?");
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "\n";

    // Unset default model
    $provider->unsetDefaultModel();
    echo "Default model unset\n\n";

    // Test 5: This will use the provider's default (tinyllama)
    echo "Test 5: Simple prompt- Will use use the provider's default (tinyllama) because default model was unset\n";
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
