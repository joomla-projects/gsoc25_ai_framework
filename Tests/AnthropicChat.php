<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\AnthropicProvider;

echo "Testing Real Anthropic API Calls...\n\n";

$configFile = __DIR__ . '/../config.json';
$config = json_decode(file_get_contents($configFile), true);
$api_key = $config['anthropic_api_key'] ?? null;

try {
    // Create provider with your API key
    $provider = new AnthropicProvider([
        'api_key' => $api_key
    ]);
    
    echo "Provider created with API key\n";
    echo "Provider name: " . $provider->getName() . "\n\n";

    // Test 1: Simple prompt
    echo "Test 1: Simple prompt\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->chat("Hello! How are you?");

    echo "API call successful!\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "Provider: " . $response->getProvider() . "\n";
    echo "Status: " . $response->getStatusCode() . "\n";
    
    $metadata = $response->getMetadata();
    echo "Model used: " . ($metadata['model']) . "\n";
    echo "Input Tokens used: " . ($metadata['input_tokens']) . "\n";
    echo "Output Tokens used: " . ($metadata['output_tokens']) . "\n";
    echo "\n";

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "All Messages endpoint tests completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
