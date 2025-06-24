<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

$api_key = 'xyz'; // Replace with your actual OpenAI API key

try {
    $provider = new OpenAIProvider([
        'api_key' => $api_key
    ]);

    $imagePath = 'fish.png';
    
    $variationOptions = [
        'model' => 'dall-e-2',
        'n' => 3,
        'size' => '512x512',
        'response_format' => 'url'
    ];
    
    echo "Testing image variation creation...\n";
    echo "Image path: " . $imagePath . "\n";
    echo "Options: " . json_encode($variationOptions, JSON_PRETTY_PRINT) . "\n\n";
    
    $response = $provider->createImageVariation($imagePath, $variationOptions);
    
    echo "Success!\n";
    echo "Provider: " . $response->getProvider() . "\n";
    echo "Status Code: " . $response->getStatusCode() . "\n\n";
    
    $metadata = $response->getMetadata();
    
    echo "Response Details:\n";
    echo "  Image Count: " . $metadata['image_count'] . "\n";
    echo "  Response Format: " . $metadata['response_format'] . "\n";
    echo "  Created: " . date('Y-m-d H:i:s', $metadata['created']) . "\n";
    
    if (isset($metadata['url_expires'])) {
        echo "  URL Expiry: " . $metadata['url_expires'] . "\n";
    }
    
    echo "\nGenerated Image Variations:\n";
    
    if ($metadata['response_format'] === 'url') {
        if ($metadata['image_count'] === 1) {
            echo "  Image URL: " . $response->getContent() . "\n";
        } else {
            $urls = json_decode($response->getContent(), true);
            foreach ($urls as $index => $url) {
                echo "  Image " . ($index + 1) . ": " . $url . "\n";
            }
        }
    } elseif ($metadata['response_format'] === 'b64_json') {
        echo "  Base64 data received (length: " . strlen($response->getContent()) . " characters)\n";
        if ($metadata['image_count'] > 1) {
            $base64Data = json_decode($response->getContent(), true);
            foreach ($base64Data as $index => $data) {
                echo "  Image " . ($index + 1) . " length: " . strlen($data) . " characters\n";
            }
        }
    }
    
    // echo "\nRaw Metadata:\n";
    // echo json_encode($metadata, JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}