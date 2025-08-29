<?php

require_once '../vendor/autoload.php';

use Joomla\AI\AIFactory;
use Joomla\AI\Exception\ProviderException;

$configFile = __DIR__ . '/../config.json';
$config = json_decode(file_get_contents($configFile), true);
$api_key = $config['openai_api_key'] ?? null;
$anthropic_api_key = $config['anthropic_api_key'] ?? null;

echo "=== AI Factory Test Suite ===\n\n";

// Test Case 1: Invalid Provider
echo "1. Testing invalid provider 'abcd':\n";
try {
    $options = [
        'api_key' => $anthropic_api_key
    ];
    
    $ai = AIFactory::getAI('abcd', $options);
    $response = $ai->chat("Hey");
    echo $response->getContent();

} catch (ProviderException $e) {
    echo "Caught expected exception: " . $e->getMessage() . "\n";
}
echo "\n";

// Test Case 2: Valid Provider Creation
echo "2. Testing valid provider creation (anthropic):\n";
try {
    $options = [
        'api_key' => $anthropic_api_key
    ];
    
    $ai = AIFactory::getAI('anthropic', $options);
    echo "Provider name: " . $ai->getProvider()->getName() . "\n";
    $response = $ai->chat("Hey");
    echo $response->getContent();
} catch (Exception $e) {
    echo "Failed to create Anthropic provider: " . $e->getMessage() . "\n";
}
echo "\n";

// Test Case 3: Non-existent Method Call
echo "3. Testing non-existent method call:\n";
try {
    $options = [
        'api_key' => $anthropic_api_key
    ];
    
    $ai = AIFactory::getAI('anthropic', $options);
    $response = $ai->nonExistentMethod("test");
    echo $response->getContent();
} catch (ProviderException $e) {
    echo "Caught expected Exception for non-existent method: " . $e->getMessage() . "\n";
}
echo "\n";

// Test Case 4: Available Providers
echo "4. Testing available providers:\n";
try {
    $availableProviders = AIFactory::getAvailableProviders();
    echo "Available providers: " . implode(', ', $availableProviders) . "\n";
    
    // Test each provider availability
    foreach ($availableProviders as $provider) {
        $isAvailable = AIFactory::isProviderAvailable($provider);
        echo "Provider '$provider' is available: " . ($isAvailable ? 'Yes' : 'No') . "\n";
    }
    
    // Test non-existent provider
    $isAvailable = AIFactory::isProviderAvailable('non-existent');
    echo "Provider 'non-existent' is available: " . ($isAvailable ? 'Yes' : 'No') . "\n";
} catch (Exception $e) {
    echo "Failed to get available providers: " . $e->getMessage() . "\n";
}
echo "\n";

// Test Case 5: Valid Method Call
echo "5. Testing valid method calls:\n";
try {
    $options = [
        'api_key' => $anthropic_api_key
    ];

    $ai = AIFactory::getAI('anthropic', $options);
    $response = $ai->chat("Hey");
    echo $response->getContent();
} catch (Exception $e) {
    echo "Test Failed: " . $e->getMessage() . "\n";
}

echo "\n=== Test Suite Complete ===\n";