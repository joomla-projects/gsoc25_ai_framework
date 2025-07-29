<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OllamaProvider;

echo "Testing Ollama Generate API Calls...\n\n";

try {
    $provider = new OllamaProvider();
    
    echo "Provider created successfully\n";
    echo "Provider name: " . $provider->getName() . "\n\n";

    // Test 1: Simple prompt
    echo "Test 1: Simple prompt\n";
    echo str_repeat('-', 50) . "\n";

    $response1 = $provider->generate("Write a short paragraph about artificial intelligence.");
    echo $response1->getContent() . "\n\n";

    // // Test 2: With streaming
    // echo "Test 2: With streaming\n";
    // echo str_repeat('-', 50) . "\n";
    
    // $response2 = $provider->generate("Explain how neural networks work in 3-4 sentences.", ['stream' => true]);
    // echo $response2->getContent() . "\n\n";

    // // Test 3: With suffix option
    // echo "Test 3: With suffix option\n";
    // echo str_repeat('-', 50) . "\n";
    // $options = [
    //     'model' => 'codellama:7b-code-q4_0',
    //     'suffix' => "    return result",
    //     'options' => [
    //         'temperature' => 0
    //     ],
    //     'stream' => false
    // ];

    // $response3 = $provider->generate("def compute_gcd(a, b):", $options);
    // echo $response3->getContent() . "\n\n";

    // Test 4: With response_format option
    echo "Test 4: Structured JSON output\n";
    echo str_repeat('-', 50) . "\n";

    $options = [
        'format' => [
            'type' => 'object',
            'properties' => [
                'age' => [
                    'type' => 'integer'
                ],
                'available' => [
                    'type' => 'boolean'
                ]
            ],
            'required' => [
                'age',
                'available'
            ]
        ]
    ];

    $response = $provider->generate(
        "Ollama is 22 years old and is busy saving the world. Respond using JSON",
        $options
    );
    
    echo $response->getContent() . "\n\n";

    // Test 5: Image processing with base64 encoding
    echo "Test 5: Image processing with base64 encoding\n";
    echo str_repeat('-', 50) . "\n";

    // Read the image file and convert to base64
    $imagePath = __DIR__ . '/test_files/fish.png';
    $imageData = file_get_contents($imagePath);
    if ($imageData === false) {
        throw new Exception("Failed to read image file: $imagePath");
    }
    $base64Image = base64_encode($imageData);

    $options = [
        'model' => 'llava',
        'images' => [$base64Image]
    ];

    $response = $provider->generate(
        "What is in this picture?",
        $options
    );

    echo "Response content:\n";
    echo $response->getContent() . "\n\n";

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "All Generate tests completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
