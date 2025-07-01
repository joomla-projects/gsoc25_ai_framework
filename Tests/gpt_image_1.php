<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

$api_key = 'xyz';
$base_url = 'Custom URL here'; // Set your custom base URL here

function saveBase64Image($base64Data, $filename) {
    $imageData = base64_decode($base64Data);
    file_put_contents($filename, $imageData);
    return filesize($filename);
}

try {
    echo "=== GPT-Image-1 Tests ===\n\n";
    
    // Configure provider with custom base URL
    $provider = new OpenAIProvider([
        'api_key' => $api_key,
        'base_url' => $base_url
    ]);

    // Test 1: Basic Image Generation
    echo "Test 1: Basic Image Generation\n";
    echo str_repeat("-", 40) . "\n";
    
    $response = $provider->generateImage(
        "A cute baby sea otter floating on its back in crystal clear water, photorealistic style",
        [
            'model' => 'gpt-image-1',
            'size' => '1024x1024'
        ]
    );
    
    echo "Image generation successful!\n\n";
    
    $metadata = $response->getMetadata();
    echo "Response Details:\n";
    echo "Model: " . ($metadata['model'] ?? 'unknown') . "\n";
    echo "Size: " . ($metadata['size'] ?? 'unknown') . "\n";
    echo "Format: " . ($metadata['response_format'] ?? 'base64') . "\n";
    echo "Provider: " . $response->getProvider() . "\n";
    
    $image = saveBase64Image($response->getContent(), 'test1_basic_sea_otter.png');
    echo "\nImage saved: test1_basic_sea_otter.png\n";

    echo "\n" . str_repeat("=", 50) . "\n";
    sleep(15); // Simulate some delay before next test

    // ====================================================================
    // TEST 2: Single Image Edit
    // ====================================================================
    echo "Test 2: Single Image Editing\n";
    echo str_repeat("-", 40) . "\n";
    
    $editResponse1 = $provider->editImage(
        'fish.png',
        'Change the colour of the fish to green',
        [
            'model' => 'gpt-image-1',
            'output_format' => 'png',
        ]
    );
    
    $metadata1 = $editResponse1->getMetadata();
    echo "Single image edit successful!\n";
    echo "Model: " . ($metadata1['model'] ?? 'unknown') . "\n";
    echo "Output format: " . ($metadata1['output_format'] ?? 'unknown') . "\n";
    
    $edit1 = saveBase64Image($editResponse1->getContent(), 'edited_fish.png');
    echo "Saved: edited_fish.png ({$edit1} bytes)\n";

    echo "\n" . str_repeat("=", 50) . "\n";
    sleep(15); // Simulate some delay before next test
    
    // ====================================================================
    // TEST 3: Edit with Transparency
    // ====================================================================
    echo "Test 3: Editing with Transparent Background\n";
    echo str_repeat("-", 40) . "\n";
    
    $transparentResponse = $provider->editImage(
        'fish.png',
        'Extract the main subject and remove the background, creating a clean isolated object suitable for logos',
        [
            'model' => 'gpt-image-1',
            'background' => 'transparent',
            'output_format' => 'png',
            'size' => '1024x1024'
        ]
    );
    
    $transparentImage = saveBase64Image($transparentResponse->getContent(), 'edited_transparent.png');
    echo "Transparent background edit successful!\n";
    echo "Saved: edited_transparent.png ({$transparentImage} bytes)\n\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}