<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Joomla\AI\Provider\OllamaProvider;

echo "Testing Ollama Provider Integration...\n\n";

try {

    // Create provider instance
    $provider = new OllamaProvider();
    echo "Provider created successfully\n";
    echo "Provider name: " . $provider->getName() . "\n\n";

    // Test 0: Pulll granite-embedding:30m model
    echo "Test 0: Pull granite-embedding:30m model\n";
    echo str_repeat('-', 50) . "\n";

    $modelName = 'granite-embedding:30m';
    $result = $provider->pullModel($modelName);

    // Test 1: Copying a new model
    echo "Test 1: Copying a new model (granite-embedding)\n";
    echo str_repeat('-', 50) . "\n";

    $modelToCopy = 'granite-embedding:30m';
    $newModelName = 'granite-embedding-copy:30m';

    $result = $provider->copyModel($modelToCopy, $newModelName);

    echo "\n";

    //Test 2: Deleting a model
    echo "Test 2: Deleting a model (granite-embedding)\n";
    echo str_repeat('-', 50) . "\n";

    $result = $provider->deleteModel($modelToCopy);

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "All Ollama Provider tests completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}