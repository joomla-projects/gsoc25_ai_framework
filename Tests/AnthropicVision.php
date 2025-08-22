<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\AnthropicProvider;

echo "Testing Anthropic Vision API Calls...\n\n";

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

    // Test vision capability
    echo "Test: Vision with image description\n";
    echo str_repeat('-', 50) . "\n";

    // You can use a base64 image or URL
    $imageUrl = "https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Gfp-wisconsin-madison-the-nature-boardwalk.jpg/2560px-Gfp-wisconsin-madison-the-nature-boardwalk.jpg";
    
    $response = $provider->vision(
        "What do you see in this image? Describe it in detail.",
        $imageUrl,
        [
            'model' => 'claude-3-5-sonnet-20241022',
        ]
    );

    echo "Vision API call successful!\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "Provider: " . $response->getProvider() . "\n";
    echo "Status: " . $response->getStatusCode() . "\n";
    
    $metadata = $response->getMetadata();
    echo "Model used: " . ($metadata['model']) . "\n";
    echo "Input Tokens used: " . ($metadata['input_tokens']) . "\n";
    echo "Stop reason: " . ($metadata['stop_reason']) . "\n";
    echo "\n";

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "Anthropic Vision tests completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
