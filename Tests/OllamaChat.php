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

    $response = $provider->chat("Can you write a short poem on Joomla focusing on its features and benefits?");

    echo "API call successful!\n";
    echo "Response: " . "\n" . $response->getContent() . "\n";
    
    // $metadata = $response->getMetadata();
    // if (!empty($metadata)) {
    //     echo "Model used: " . ($metadata['model'] ?? 'N/A') . "\n";
    //     echo "Total duration: " . ($metadata['total_duration'] ?? 'N/A') . " ns\n";
    //     echo "Eval count: " . ($metadata['eval_count'] ?? 'N/A') . "\n";
    //     if (isset($metadata['usage'])) {
    //         echo "Input tokens: " . ($metadata['usage']['input_tokens'] ?? 'N/A') . "\n";
    //         echo "Output tokens: " . ($metadata['usage']['output_tokens'] ?? 'N/A') . "\n";
    //     }
    // }
    // echo "\n";

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "All Chat tests completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}