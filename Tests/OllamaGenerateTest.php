<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OllamaProvider;

echo "Testing Real Ollama API Calls...\n\n";

try {
    // Create provider with your API key
    $provider = new OllamaProvider();
    
    echo "Provider created with API key\n";
    echo "Provider name: " . $provider->getName() . "\n\n";

    // Test 1: Simple prompt
    echo "Test 1: Simple prompt\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->generate("Hello! How are you?", [
        'model' => 'phi4-mini:latest',
        'stream' => false
    ]);

    echo "API call successful!\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "Provider: " . $response->getProvider() . "\n";
    echo "Status: " . $response->getStatusCode() . "\n";
    
    $metadata = $response->getMetadata();
    if (!empty($metadata)) {
        echo "Model used: " . ($metadata['model']) . "\n";
        if (isset($metadata['usage'])) {
            echo "Input tokens: " . ($metadata['usage']['input_tokens'] ?? 'N/A') . "\n";
            echo "Output tokens: " . ($metadata['usage']['output_tokens'] ?? 'N/A') . "\n";
        }
    }
    echo "\n";

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "All Chat Completions API tests completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
