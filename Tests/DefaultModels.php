<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

echo "Testing Real OpenAI API Calls...\n\n";

$configFile = __DIR__ . '/../config.json';
$config = json_decode(file_get_contents($configFile), true);
$api_key = $config['openai_api_key'] ?? null;

try {
    // Create provider with your API key
    $provider = new OpenAIProvider([
        'api_key' => $api_key
    ]);
    
    echo "Provider created with API key\n";
    echo "Provider name: " . $provider->getName() . "\n\n";

    // To Do: Check if the provider is supported. Currently key set as env variables only
    // // if (!OpenAIProvider::isSupported()) {
    //     throw new \Exception('OpenAI API is not supported or API key is missing.');
    // }

    // Set default model for all subsequent calls
    $provider->setDefaultModel('gpt-3.5-turbo');
    echo "Default model: " . $provider->getDefaultModel() . "\n\n";

    // Test 1: Will use default model since ('gpt-3.5-turbo')
    // no model is specified in the options 
    // and the default model is set
    echo "Test 1: Simple prompt- Will use default model gpt-3.5-turbo\n";
    echo str_repeat('-', 50) . "\n";
    $response = $provider->chat("Hello! How are you?");
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "\n";

    // Test 2: Will use default model for the next call again since ('gpt-3.5-turbo')
    // no model is specified in the options 
    // and the default model is set
    echo "Test 2: Multiple Response Choices- Will use default model gpt-3.5-turbo\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->chat("Suggest a name for a movie based on pilots and astronauts");
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "\n";

    // Test 3: This will override the default model since 
    // model is specified in the options ('gpt-4o-audio-preview')
    echo "Test 3: Test chat completions audio capability- Will override the default and use gpt-4o-audio-preview model\n";
    echo str_repeat('-', 50) . "\n";
    $response = $provider->chat("Say a few words on Joomla! for about 30 seconds in english.", [
        'model' => 'gpt-4o-audio-preview',
        'modalities' => ['text', 'audio'],
        'audio' => [
            'voice' => 'alloy',
            'format' => 'wav'
        ],
    ]);

    $metadata = $response->getMetadata();
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    if (isset($metadata['choices'][0]['message']['audio']['data'])) {
        $audioData = $metadata['choices'][0]['message']['audio']['data'];
        $audioDatab64 = base64_decode($audioData, true);
        $audioFile = file_put_contents("output/chat_completions_audio.wav", $audioDatab64);
        echo "Audio file found and saved to: \"output/chat_completions_audio.wav\".\n";
    } else {
        echo "Audio file not found.\n";
    }
    echo "\n";

    // Test 4: Will use default model for the next call again since ('gpt-3.5-turbo')
    // no model is specified in the options 
    // and the default model was never unset
    echo "Test 4: Simple prompt- Will use default model gpt-3.5-turbo because default model was not unset\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->chat("What is the capital of France?");
    echo "Model: " . $response->getMetadata()['model'] . "\n";
    echo "Response: " . $response->getContent() . "\n";
    echo "\n";

    // Unset default model
    $provider->unsetDefaultModel();
    echo "Default model unset\n\n";

    // Test 5: Uses method's default 
    // (no model in options, 
    // no default model, 
    // no config model)
    echo "Test 5: generateImage with method's default model (should use 'dall-e-2')\n";
    echo str_repeat('-', 50) . "\n";

    $response = $provider->generateImage("Generate an image of a dog playing chess.");
    $response->saveFile("output/test5_image.png");

    echo "Model: " . ($response->getMetadata()['model']) . "\n";
    echo "File saved to: output/test5_image.png\n";
    echo "\n";

    // Test 6: Uses provider's config default 
    // (no model in options, 
    // no default model, 
    // config has model)
    $providerWithConfig = new OpenAIProvider([
        'api_key' => $api_key,
        'model' => 'dall-e-3' // Set default model in config
    ]);

    echo "Test 6: generateImage with provider's config default model (should use 'dall-e-3')\n";
    echo str_repeat('-', 50) . "\n";
    $response = $providerWithConfig->generateImage("Generate an image of gray tabby cat hugging an otter with an orange scarf. Make it look realistic.");
    
    $response->saveFile("output/test6_image.png");
    echo "Model: " . ($response->getMetadata()['model']) . "\n";
    echo "File saved to: output/test6_image.png\n";
    echo "\n";

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "All Chat Completions API tests completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
