<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

$configFile = __DIR__ . '/../config.json';
$config = json_decode(file_get_contents($configFile), true);
$api_key = $config['openai_api_key'] ?? null;

try {
    $provider = new OpenAIProvider([
        'api_key' => $api_key
    ]);

    echo "=== Testing OpenAI Image Editing Capability ===\n\n";

    // Test 1: Single Image Edit with DALL-E 2
    echo "Test 1: Single Image Edit with DALL-E 2\n";
    echo "----------------------------------------\n";
    
    $imagePath = 'test_files/dog_img.png';
    $maskImagePath = 'test_files/mask_dog_img.png'; // Mask image
    $prompt = "picture of a dog and a rabbit";

    $editOptions = [
        'model' => 'dall-e-2',
        'mask' => $maskImagePath,
        'response_format' => 'url'
    ];

    $response1 = $provider->editImage($imagePath, $prompt, $editOptions);

    echo "Success!\n";
    
    $metadata1 = $response1->getMetadata();
    echo "Image Count: " . $metadata1['image_count'] . "\n";
    echo "Response Format: " . $metadata1['response_format'] . "\n";
    
    echo "\nGenerated Images:\n";
    if ($metadata1['response_format'] === 'url') {
        if ($metadata1['image_count'] === 1) {
            echo "  Image URL: " . $response1->getContent() . "\n";
        } else {
            $urls = json_decode($response1->getContent(), true);
            foreach ($urls as $index => $url) {
                echo "  Image " . ($index + 1) . ": " . $url . "\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
    
    echo "\n=== All Tests Completed! ===\n";

    // Test b64_json response format

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
