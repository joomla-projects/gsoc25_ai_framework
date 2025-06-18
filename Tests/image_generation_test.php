<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

echo "=== OpenAI Image Generation - Comprehensive Tests ===\n\n";

$api_key = 'xyz'; // Set your OpenAI API key here

try {
    $provider = new OpenAIProvider(['api_key' => $api_key]);
    
    echo "Provider: " . $provider->getName() . "\n\n";
    
    // ============================================
    // TEST 1: DALL-E 3 with Base64 (default)
    // ============================================
    
    echo "Test 1: DALL-E 3 with Base64 response (default)...\n";
    $response = $provider->generateImage(
        "A red apple on a white table", 
        ['model' => 'dall-e-3']
    );
    
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Provider: " . $response->getProvider() . "\n";
    $metadata = $response->getMetadata();
    echo "Response format: " . ($metadata['response_format'] ?? 'unknown') . "\n";
    echo "Format: " . ($metadata['format'] ?? 'unknown') . "\n";
    echo "Total images: " . ($metadata['total_images']) . "\n";

    if (isset($metadata['revised_prompt'])) {
        echo "Revised prompt: " . "\n";
    }
    
    $content = $response->getContent();
    if (strlen($content) > 0) {
        file_put_contents('test1_dalle3_base64.png', base64_decode($content));
        echo "Image saved as: test1_dalle3_base64.png\n";
    } else {
        echo "No base64 content received\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // ============================================
    // TEST 2: DALL-E 3 with URL response
    // ============================================
    
    echo "Test 2: DALL-E 3 with URL response...\n";
    $response = $provider->generateImage(
        "A blue ocean with waves", 
        [
            'model' => 'dall-e-3',
            'response_format' => 'url'
        ]
    );
        
    $metadata = $response->getMetadata();
    echo "Response format: " . ($metadata['response_format'] ?? 'unknown') . "\n";
    echo "Format: " . ($metadata['format'] ?? 'unknown') . "\n";

    if (isset($metadata['image_url'])) {
        echo "Image URL: " . $metadata['image_url'] . "\n";
        echo "URL expires: " . ($metadata['url_expires'] ?? 'unknown') . "\n";
    } else {
        echo "No image URL in metadata\n";
    }

    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // ============================================
    // TEST 3: DALL-E 2 with Base64
    // ============================================
    
    echo "Test 3: DALL-E 2 with Base64 response...\n";
    $response = $provider->generateImage(
        "A simple drawing of a house", 
        [
            'model' => 'dall-e-2',
        ]
    );
        
    $metadata = $response->getMetadata();
    echo "Model: " . ($metadata['model'] ?? 'unknown') . "\n"; // Not given as response
    echo "Response format: " . ($metadata['response_format'] ?? 'unknown') . "\n";
    
    $content = $response->getContent();
    if (strlen($content) > 0) {
        file_put_contents('test3_dalle2_base64.png', base64_decode($content));
        echo "Image saved as: test3_dalle2_base64.png\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // ============================================
    // TEST 4: DALL-E 2 with URL response
    // ============================================
    
    echo "Test 4: DALL-E 2 with URL response...\n";
    $response = $provider->generateImage(
        "A cartoon cat wearing sunglasses", 
        [
            'model' => 'dall-e-2',
            'response_format' => 'url',
        ]
    );
        
    $metadata = $response->getMetadata();
    echo "Response format: " . ($metadata['response_format'] ?? 'unknown') . "\n";

    if (isset($metadata['image_url'])) {
        echo "Image URL: " . $metadata['image_url'] . "\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";

    echo "ALL TESTS COMPLETED!\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
