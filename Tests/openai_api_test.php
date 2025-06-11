<?php

require_once '../vendor/autoload.php';
// require_once '../src/ProviderInterface.php';
// require_once '../src/Response.php';
// require_once '../src/AbstractProvider.php';
// require_once '../src/Provider/OpenAIProvider.php';

use Joomla\AI\Provider\OpenAIProvider;

echo "Testing Real OpenAI API Calls...\n\n";

// Replace 'xyz' with your actual OpenAI API key
$api_key = 'xyz'; // Set your OpenAI API key here

try {
    // Create provider with your API key
    $provider = new OpenAIProvider([
        'api_key' => $api_key
    ]);
    
    echo "Provider created with API key\n";
    echo "Provider name: " . $provider->getName() . "\n\n";

    // To Do: Check if the provider is supported. Currently key set as env variables only
    // if (!OpenAIProvider::isSupported()) {
    //     throw new \Exception('OpenAI API is not supported or API key is missing.');
    // }

    // Test 1: Simple prompt
    echo "Test 1: Simple prompt...\n";
    $response = $provider->prompt("Hello, how are you?");

    echo "API call successful!\n";
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
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
