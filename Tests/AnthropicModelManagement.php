<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\AnthropicProvider;

echo "=== Anthropic Provider Comprehensive Test ===\n\n";

$configFile = __DIR__ . '/../config.json';
$config = json_decode(file_get_contents($configFile), true);
$api_key = $config['anthropic_api_key'] ?? null;

try {
    // Create provider
    $provider = new AnthropicProvider([
        'api_key' => $api_key
    ]);
    
    echo "Provider: " . $provider->getName() . "\n";
    echo "Provider created successfully!\n\n";

    // Test 1: Get all available models
    echo "=== Test 1: Get Available Models ===\n";

    $availableModels = $provider->getAvailableModels();
    echo "Available Models: " . implode(', ', $availableModels) . "\n";
    echo "Total: " . count($availableModels) . " models\n";

    echo "\n" . str_repeat("=", 60) . "\n\n";

    // Test 2: Test with a known model ID (if different from available models)
    echo "=== Test 2: Get Known Model (claude-3-haiku-20240307) ===\n";
    
    try {
        $validModelResponse = $provider->getModel('claude-3-haiku-20240307');
        
        echo "Model Information:\n";
        echo $validModelResponse->getContent() . "\n";

    } catch (Exception $e) {
        echo "Error getting known model: " . $e->getMessage() . "\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n\n";

    // Test 3: Test with invalid model ID (error handling)
    echo "=== Test 3: Error Handling - Invalid Model ID ===\n";
    
    try {
        $invalidModelResponse = $provider->getModel('invalid-model-12345');
        echo "Unexpected success with invalid model\n";
    } catch (Exception $e) {
        echo "Expected error caught: " . $e->getMessage() . "\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n\n";

    // Test 4: Basic Chat with saveFile
    echo "=== Test 4: Basic Chat with saveFile ===\n";
    echo str_repeat("-", 50) . "\n";

    $chatResponse = $provider->chat(
        "Hi",
        [
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 150
        ]
    );

    echo "Chat Response:\n";
    echo $chatResponse->getContent() . "\n";
    echo "Status: " . $chatResponse->getStatusCode() . "\n";
    echo "Provider: " . $chatResponse->getProvider() . "\n";
    
    $metadata = $chatResponse->getMetadata();
    echo "Model: " . $metadata['model'] . "\n";
    echo "Input Tokens: " . $metadata['input_tokens'] . "\n";
    echo "Output Tokens: " . $metadata['output_tokens'] . "\n";
    echo "Stop Reason: " . $metadata['stop_reason'] . "\n";

    echo "\n" . str_repeat("=", 60) . "\n\n";

    // Test 5: Vision Analysis
    echo "=== Test 5: Vision Analysis ===\n";
    echo str_repeat("-", 50) . "\n";

    $testImage = "test_files/fish.png";
    $visionResponse = $provider->vision(
        "What do you see in this image? Describe in one line.",
        $testImage,
    );

    echo "Vision Response:\n";
    echo $visionResponse->getContent() . "\n";
    echo "Status: " . $visionResponse->getStatusCode() . "\n";
    
    $visionMetadata = $visionResponse->getMetadata();
    echo "Model: " . $visionMetadata['model'] . "\n";
    echo "Input Tokens: " . $visionMetadata['input_tokens'] . "\n";
    echo "Output Tokens: " . $visionMetadata['output_tokens'] . "\n";

    echo "\n" . str_repeat("=", 60) . "\n\n";

    // Test 6: Advanced Chat with Options
    echo "=== Test 6: Advanced Chat with Options ===\n";
    echo str_repeat("-", 50) . "\n";

    $advancedResponse = $provider->chat(
        "What is the full form of AI?",
        [
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 200,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'stop_sequences' => ['\n\n\n']
        ]
    );

    echo "Advanced Chat Response:\n";
    echo $advancedResponse->getContent() . "\n";
    
    $advancedMetadata = $advancedResponse->getMetadata();
    echo "Model: " . $advancedMetadata['model'] . "\n";
    echo "Input Tokens: " . $advancedMetadata['input_tokens'] . "\n";
    echo "Output Tokens: " . $advancedMetadata['output_tokens'] . "\n";
    echo "Stop Reason: " . $advancedMetadata['stop_reason'] . "\n";

    echo "\n" . str_repeat("=", 60) . "\n\n";

    echo "\nAll Anthropic tests completed successfully!\n";

} catch (Exception $e) {
    echo "Test failed with error: " . $e->getMessage() . "\n";
}
