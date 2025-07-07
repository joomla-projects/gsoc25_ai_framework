<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

echo "Testing OpenAI Vision API Calls...\n\n";

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

    // Test 1: Vision with URL image
    echo "Test 1: Vision with image URL...\n";
    $imageUrl = "https://upload.wikimedia.org/wikipedia/commons/e/eb/Ash_Tree_-_geograph.org.uk_-_590710.jpg";
    
    $response = $provider->chatWithVision("What do you see in this image?", $imageUrl);

    echo "Vision API call successful!\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "Provider: " . $response->getProvider() . "\n";
    echo "Status: " . $response->getStatusCode() . "\n";
    
    $metadata = $response->getMetadata();
    if (!empty($metadata)) {
        echo "Model used: " . ($metadata['model']) . "\n";
        if (isset($metadata['usage'])) {
            echo "Tokens used: " . ($metadata['usage']['total_tokens']) . "\n";
        }
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // Test 2: Vision with specific model
    echo "Test 2: Vision with specific model (gpt-4o)...\n";
    $response = $provider->chatWithVision(
        "Describe the colors and mood of this image.", 
        $imageUrl,
        ['model' => 'gpt-4o', 'max_tokens' => 100]
    );

    echo "Vision API call successful!\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "Provider: " . $response->getProvider() . "\n";
    
    $metadata = $response->getMetadata();
    if (!empty($metadata)) {
        echo "Model used: " . ($metadata['model']) . "\n";
        if (isset($metadata['usage'])) {
            echo "Tokens used: " . ($metadata['usage']['total_tokens']) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "This might be due to:\n";
    echo "- Invalid API key\n";
    echo "- Model doesn't support vision\n";
    echo "- API quota exceeded\n";
}