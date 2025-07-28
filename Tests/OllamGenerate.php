<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OllamaProvider;

echo "Testing Ollama Generate API Calls...\n\n";

try {
    $provider = new OllamaProvider();
    
    echo "Provider created successfully\n";
    echo "Provider name: " . $provider->getName() . "\n\n";

    // Test 1: Simple prompt
    echo "Test 1: Simple prompt\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->generate("Write a short paragraph about artificial intelligence.");

    echo "API call successful!\n";
    echo "Response: " . "\n" . $response->getContent() . "\n\n";

    // Test 2: With streaming
    echo "Test 2: With streaming\n";
    echo str_repeat('-', 50) . "\n";
    
    $options = [
        'stream' => true
    ];
    
    $response = $provider->generate("Explain how neural networks work in 3-4 sentences.", $options);
    echo "Response: " . "\n" . $response->getContent() . "\n\n";

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "All Generate tests completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
