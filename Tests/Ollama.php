<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Joomla\AI\Provider\OllamaProvider;

echo "Testing Ollama Provider Integration...\n\n";

try {
    // Test 1: Check if Ollama is supported (server is running)
    echo "Test 1: Checking if Ollama is supported\n";
    echo str_repeat('-', 50) . "\n";
    
    $isSupported = OllamaProvider::isSupported();
    echo "Is Ollama supported? " . ($isSupported ? "Yes" : "No") . "\n\n";

    if (!$isSupported) {
        throw new Exception("Ollama server is not running. Please start it with 'ollama serve'");
    }

    // Create provider instance
    $provider = new OllamaProvider();
    echo "Provider created successfully\n";
    echo "Provider name: " . $provider->getName() . "\n\n";

    // Test 2: Get Available Models
    echo "Test 2: Getting available models\n";
    echo str_repeat('-', 50) . "\n";
    
    $models = $provider->getAvailableModels();
    echo "Available models:\n";
    foreach ($models as $model) {
        echo "- $model\n";
    }
    echo "\n";

    // Test 3: Pull a new model (llama2 if not present)
    echo "Test 3: Pulling a new model (granite-embedding)\n";
    echo str_repeat('-', 50) . "\n";
    
    $modelToPull = 'granite-embedding:30m';
    if (!in_array($modelToPull, $models)) {
        echo "Model $modelToPull not found locally. Pulling...\n";
        $result = $provider->pullModel($modelToPull);
        echo $result ? "Pull successful!\n" : "Pull failed!\n";
    } else {
        echo "Model $modelToPull is already available locally.\n";
    }
    echo "\n";

    // Test 4: Attempt to pull an already present model
    echo "Test 4: Attempting to pull an already present model\n";
    echo str_repeat('-', 50) . "\n";
    
    // Get first available model from the list
    if (!empty($models)) {
        $existingModel = $models[0];
        $result = $provider->pullModel($existingModel);
    } else {
        echo "No models available to test with.\n";
    }
    echo "\n";

    // Test 5: Attempt to pull a non-existent model
    echo "Test 5: Attempting to pull a non-existent model\n";
    echo str_repeat('-', 50) . "\n";
    
    $nonExistentModel = 'non-existent-model-123';
    try {
        echo "Attempting to pull non-existent model: $nonExistentModel\n";
        $result = $provider->pullModel($nonExistentModel);
        echo "Unexpected success!\n";
    } catch (Exception $e) {
        echo "Expected error occurred: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Final check of available models after all operations
    echo "Final check: Getting available models after operations\n";
    echo str_repeat('-', 50) . "\n";
    
    $finalModels = $provider->getAvailableModels();
    echo "Final available models:\n";
    foreach ($finalModels as $model) {
        echo "- $model\n";
    }
    echo "\n";

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "All Ollama Provider tests completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}