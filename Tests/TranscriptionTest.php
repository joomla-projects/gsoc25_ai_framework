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

    echo "=== Testing OpenAI Audio Transcription Functionality ===\n\n";

    echo "Step 0: Creating Test Audio File\n";
    echo "--------------------------------\n";
    
    $testText = "Hello world! This is a test of the OpenAI transcription functionality. We are testing speech to text conversion with the Whisper model.";

    $speechResponse = $provider->speech($testText, ['model' => 'tts-1', 'voice' => 'alloy', 'response_format' => 'wav']);
    $speechResponse->saveFile('test_files/test_audio.wav');
    echo "Audio file created: test_files/test_audio.wav\n\n";
    
    echo str_repeat("=", 60) . "\n\n";

    // Test 1: Basic Transcription with Whisper-1
    echo "Test 1: Basic Transcription (Whisper-1)\n";
    echo "---------------------------------------\n";
    
    $audioFile = 'test_files/test_audio.wav';
    $options = [
        'model' => 'whisper-1',
    ];
    
    echo "Audio File: $audioFile\n";

    $response1 = $provider->transcribe($audioFile, $options);

    $metadata1 = $response1->getMetadata();
    echo "Model Used: " . $metadata1['model'] . "\n";
    echo "Response Format: " . $metadata1['response_format'] . "\n";
    echo "Duration: " . ($metadata1['duration'] ?? 'N/A') . " seconds\n";
    
    $transcribedText = $response1->getContent();
    echo "\n Transcribed Text:\n";
    echo "\"$transcribedText\"\n\n";
    
    echo "Original:    \"$testText\"\n";
    echo "Transcribed: \"$transcribedText\"\n";
    
    echo str_repeat("=", 60) . "\n\n";

    // Test 2: Different Response Formats
    echo "Test 2: Different Response Formats\n";
    echo "----------------------------------\n";
    
    $formats = ['text', 'srt', 'vtt'];
    
    foreach ($formats as $format) {
        echo "Testing format: $format\n";
        echo str_repeat("-", 20) . "\n";
        
        try {
            $response = $provider->transcribe($audioFile, [
                'model' => 'whisper-1',
                'response_format' => $format
            ]);
            
            $content = $response->getContent();
            $metadata = $response->getMetadata();
            
            echo "Success - Format: " . $metadata['response_format'] . "\n";
            
            if ($format === 'text') {
                echo "Content: \"" . trim($content) . "\"\n";
            } else {
                $filename = "transcription_test.$format";
                file_put_contents("output/$filename", $content);
                echo "Saved as: $filename\n";
            }
            
        } catch (Exception $e) {
            echo "Format $format failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    echo str_repeat("=", 60) . "\n\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
