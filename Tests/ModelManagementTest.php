<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

echo "Testing OpenAI Model Management...\n\n";

$configFile = __DIR__ . '/../config.json';
$config = json_decode(file_get_contents($configFile), true);
$api_key = $config['openai_api_key'] ?? null;

try {
    $provider = new OpenAIProvider([
        'api_key' => $api_key
    ]);
    
    echo "Provider created successfully\n";
    echo "Provider name: " . $provider->getName() . "\n\n";

    // Test 1: Get all available models
    echo "=== Test 1: Get Available Models ===\n";

    $availableModels = $provider->getAvailableModels();
    echo "Available models count: " . count($availableModels) . "\n";

    // Test 2: Get chat models
    echo "=== Test 2: Get Chat Models ===\n";

    $chatModels = $provider->getChatModels();
    echo "Chat models count: " . count($chatModels) . "\n";

    // Test 3: Get vision models
    echo "=== Test 3: Get Vision Models ===\n";

    $visionModels = $provider->getVisionModels();
    echo "Vision models count: " . count($visionModels) . "\n";

    // Test 4: Check if specific models are supported
    echo "=== Test 4: Model Support Check ===\n";

    $testModels = ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo', 'dall-e-3', 'nonexistent-model'];
    foreach ($testModels as $model) {
        $isSupported = $provider->isModelSupported($model);
        echo "Model '$model': " . ($isSupported ? "Supported" : "Not supported") . "\n";
    }
    echo "\n";

    // Test 5: Check model capabilities
    echo "=== Test 5: Model Capability Check ===\n";

    $capabilityTests = [
        ['gpt-4o-mini', 'chat'],
        ['gpt-4o-mini', 'vision'],
        ['dall-e-3', 'chat'],
        ['nonexistent-model', 'chat']
    ];
    foreach ($capabilityTests as [$model, $capability]) {
        $isCapable = $provider->isModelCapable($model, $capability);
        echo "Model '$model' can do '$capability': " . ($isCapable ? "Yes" : "No") . "\n";
    }
    echo "\n";

    // Test 6: Test model validation in actual chat request
    echo "=== Test 6: Model Validation in Chat Request ===\n";
    
    // Test with valid chat model
    try {
        echo "Testing chat with valid model (gpt-4o-mini)...\n";
        $response = $provider->chat("Say hello", ['model' => 'gpt-4o-mini']);
        echo "Response: " . $response->getContent() . "\n";
    } catch (Exception $e) {
        echo "Chat with gpt-4o-mini failed: " . $e->getMessage() . "\n\n";
    }

    // Test with invalid model for chat
    try {
        echo "Testing chat with invalid model (dall-e-3)...\n";
        $response = $provider->chat("Say hello", ['model' => 'dall-e-3']);
    } catch (Exception $e) {
        echo "Correctly caught error: " . $e->getMessage() . "\n\n";
    }

    // Test 7: Test model validation in vision request
    echo "=== Test 7: Model Validation in Vision Request ===\n";
    $imageUrl = "https://upload.wikimedia.org/wikipedia/commons/e/eb/Ash_Tree_-_geograph.org.uk_-_590710.jpg";
    
    // Test with valid vision model
    try {
        echo "Testing vision with valid model (gpt-4o-mini)...\n";
        $response = $provider->chatWithVision("What do you see?", $imageUrl);
        echo "Vision with gpt-4o-mini successful\n";
        echo "Response: " . $response->getContent() . "\n\n";
    } catch (Exception $e) {
        echo "Vision with gpt-4o-mini failed: " . $e->getMessage() . "\n\n";
    }

    // Test with invalid model for vision
    try {
        echo "Testing vision with invalid model (gpt-3.5-turbo)...\n";
        $response = $provider->chatWithVision("What do you see?", $imageUrl, ['model' => 'gpt-3.5-turbo']);
        echo "This should not succeed!\n\n";
    } catch (Exception $e) {
        echo "Correctly caught error: " . $e->getMessage() . "\n\n";
    }

    echo "=== All Model Management Tests Completed ===\n";
    
} catch (Exception $e) {
    echo "Test failed with error: " . $e->getMessage() . "\n";
}
