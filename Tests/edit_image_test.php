<?php
require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

echo "=== OpenAI Image Editing Test ===\n\n";

$api_key = 'xyz'; // Replace with your actual API key

try {
    $provider = new OpenAIProvider(['api_key' => $api_key]);
    echo "Provider: " . $provider->getName() . "\n\n";
    
    function createTestImage($filename) {
        if (file_exists($filename)) {
            return;
        }else{
            echo "Create a test image: {$filename}\n";
        }
    }
    
    // ============================================
    // TEST A: Simple text edit with file path (DALL-E 2)
    // ============================================
    
    echo "TEST 1: Edit image from file path (DALL-E 2)...\n";
    echo str_repeat("-", 50) . "\n";
    
    createTestImage('test_image.png');
    
    $response = $provider->editImage(
        "Add a red car to the image.",
        'test_image.png',
        [
            'model' => 'dall-e-2',
            'response_format' => 'b64_json'
        ]
    );
        
    $content = $response->getContent();
    if (strlen($content) > 0) {
        file_put_contents('test_a_dalle2_edit.png', base64_decode($content));
        echo "Edited image saved as: test_a_dalle2_edit.png\n";
    }
    
    echo "\n";
    
    // ============================================
    // TEST B: Edit with base64 input
    // ============================================
    
    // echo "TEST 2: Edit image from base64 string (DALL-E 2)...\n";
    // echo str_repeat("-", 50) . "\n";

    // $imageBase64 = base64_encode(file_get_contents('test_image.png'));

    // $response = $provider->editImage(
    //     "Add a green tree on the left side",
    //     $imageBase64,
    //     [
    //         'model' => 'dall-e-2',
    //         'response_format' => 'b64_json'
    //     ]
    // );
        
    // $content = $response->getContent();
    // if (strlen($content) > 0) {
    //     file_put_contents('test_b_dalle2_base64_edit.png', base64_decode($content));
    //     echo "Base64 edited image saved as: test_b_dalle2_base64_edit.png\n";
    // }
    
    // echo "\n";
    
    // ============================================
    // TEST C: Try masking (inpainting)
    // ============================================
    
    // echo "TEST 3: Edit with mask (inpainting - DALL-E 2)...\n";
    // echo str_repeat("-", 50) . "\n";
    
    // function createMask($filename) {
    //     if (file_exists($filename)) {
    //         return;
    //     }else{
    //         echo "Create a test image: {$filename}\n";
    //     }
    // }
    
    // createMask('test_mask.png');
    
    // $response = $provider->editImage(
    //     "Add a flower in the center",
    //     'test_dalle2.png',
    //     [
    //         'model' => 'dall-e-2',
    //         'mask' => 'test_mask.png',
    //         'response_format' => 'b64_json'
    //     ]
    // );
    
    
    // $content = $response->getContent();
    // if (strlen($content) > 0) {
    //     file_put_contents('test_c_dalle2_mask_edit.png', base64_decode($content));
    //     echo "Masked edit saved as: test_c_dalle2_mask_edit.png\n";
    // }
    
    // echo "\n";
    
    // ============================================
    // TEST D: Try with GPT-Image-1 (catch org exception)
    // ============================================
    
    // echo "TEST 4: GPT-Image-1 edit (catch organization exception)...\n";
    // echo str_repeat("-", 50) . "\n";
    
    // try {
    //     // Create multiple test images for GPT-Image-1
    //     createTestImage('test_gpt1.png', 512);
    //     createTestImage('test_gpt2.png', 512);
        
    //     $response = $provider->editImage(
    //         "Combine these images into a beautiful collage",
    //         ['test_gpt1.png', 'test_gpt2.png'],
    //         [
    //             'model' => 'gpt-image-1',
    //             'quality' => 'high'
    //         ]
    //     );

    //     // Note: GPT-Image-1 requires a specific organization setup, so this will likely throw an exception
    //     $content = $response->getContent();
    //     if (strlen($content) > 0) {
    //         file_put_contents('test_d_gpt_image_edit.png', base64_decode($content));
    //         echo "GPT-Image-1 edit saved as: test_d_gpt_image_edit.png\n";
    //     }
        
    // } catch (Exception $e) {
    //     echo "GPT-Image-1 failed (expected): " . $e->getMessage() . "\n";
        
    //     if (strpos($e->getMessage(), 'organization') !== false || 
    //         strpos($e->getMessage(), 'verified') !== false ||
    //         strpos($e->getMessage(), 'access') !== false) {
    //         echo "This is expected - GPT-Image-1 requires verified organization\n";
    //     } else {
    //         echo "Unexpected error\n";
    //     }
    // }
    
    // echo "\n";
    
    // echo "\n" . str_repeat("=", 60) . "\n";
    echo "ALL TESTS COMPLETED!\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
