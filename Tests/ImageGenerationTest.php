<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

echo "=== OpenAI Image Generation - Comprehensive Tests ===\n\n";

$configFile = __DIR__ . '/../config.json';
$config = json_decode(file_get_contents($configFile), true);
$api_key = $config['openai_api_key'] ?? null;

function saveBase64Image($base64Data, $filename) {
    $imageData = base64_decode($base64Data);
    file_put_contents($filename, $imageData);
    return filesize($filename);
}

try {
    $provider = new OpenAIProvider(['api_key' => $api_key]);
    
    echo "Provider: " . $provider->getName() . "\n\n";
    
    // ============================================
    // TEST 1: DALL-E 3 with Base64
    // ============================================
    
    echo "Test 1: DALL-E 3 with Base64 response...\n";
    $response = $provider->generateImage(
        "A red apple on a white table", 
        [
            'model' => 'dall-e-3',
            'response_format' => 'b64_json',
        ]
    );
    
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Provider: " . $response->getProvider() . "\n";
    $metadata = $response->getMetadata();

    if (isset($metadata['revised_prompt'])) {
        echo "Revised prompt: " . "\n";
    }

    echo "Response format: " . ($metadata['response_format'] ?? 'unknown') . "\n";
    if ($metadata['response_format'] === 'url') {
        echo "  Image URL: " . $response->getContent() . "\n";
    } elseif ($metadata['response_format'] === 'b64_json') {
        echo "Base64 data received. \n";
        
        saveBase64Image($response->getContent(), 'output/test1_dalle3_base64.png');
        echo "Image saved as: output/test1_dalle3_base64.png \n";
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
    if ($metadata['response_format'] === 'url') {
        echo "  Image URL: " . $response->getContent() . "\n";
    } elseif ($metadata['response_format'] === 'b64_json') {
        echo "Base64 data received. \n";
        
        saveBase64Image($response->getContent(), 'output/test2_dalle3_base64.png');
        echo "Image saved as: output/test2_dalle3_base64.png \n";
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
            'response_format' => 'b64_json',
            'n' => 2
        ]
    );
        
    $metadata = $response->getMetadata();
    echo "Model: " . ($metadata['model'] ?? 'unknown') . "\n"; // Not given as response
    echo "Response format: " . ($metadata['response_format'] ?? 'unknown') . "\n";
    
    $content = $response->getContent();
    echo "Response format: " . ($metadata['response_format'] ?? 'unknown') . "\n";
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
        echo "Base64 data received. \n";
        
        if ($metadata['image_count'] === 1) {
            $fileSize = saveBase64Image($response->getContent(), 'output/test3_dalle2_base64.png');
            echo "  Image saved as: output/test3_dalle2_base64.png (Size: {$fileSize} bytes)\n";
        } else {
            $base64Data = json_decode($response->getContent(), true);
            foreach ($base64Data as $index => $data) {
                $filename = 'output/test3_dalle2_base64_' . ($index + 1) . '.png';
                $fileSize = saveBase64Image($data, $filename);
                echo "  Image " . ($index + 1) . " saved as: {$filename} (Size: {$fileSize} bytes)\n";
            }
        }
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
        echo "Base64 data received. \n";
        
        if ($metadata['image_count'] === 1) {
            $fileSize = saveBase64Image($response->getContent(), 'output/test4_dalle2_base64.png');
            echo "  Image saved as: output/test4_dalle2_base64.png (Size: {$fileSize} bytes)\n";
        } else {
            $base64Data = json_decode($response->getContent(), true);
            foreach ($base64Data as $index => $data) {
                $filename = 'output/test4_dalle2_base64_' . ($index + 1) . '.png';
                $fileSize = saveBase64Image($data, $filename);
                echo "  Image " . ($index + 1) . " saved as: {$filename} (Size: {$fileSize} bytes)\n";
            }
        }
    }

    echo "\n" . str_repeat("-", 50) . "\n\n";

    echo "ALL TESTS COMPLETED!\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
