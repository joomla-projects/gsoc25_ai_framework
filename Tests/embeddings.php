<?php

require_once '../vendor/autoload.php';

use Joomla\AI\Provider\OpenAIProvider;

$api_key = 'xyz';

try {
    echo "=== OpenAI Embeddings Test ===\n\n";
    
    $provider = new OpenAIProvider([
        'api_key' => $api_key
    ]);

    // Test 1: Single Text Embedding
    echo "Test 1: Single Text Embedding\n";
    echo "------------------------------\n";
    
    $text = "The quick brown fox jumps over the lazy dog";
    $response1 = $provider->createEmbeddings($text, 'text-embedding-ada-002');
    
    $embedding = $response1->getContent();
    echo "Text: \"$text\"\n";
    if (is_string($embedding)) {
        $embedding = json_decode($embedding, true);
    }
    if (is_array($embedding)) {
        echo "Embedding dimensions: " . count($embedding) . "\n";
        echo "First 5 values: [" . implode(', ', array_slice($embedding, 0, 5)) . "...]\n";
    } else {
        echo "Embedding is not an array. Type: " . gettype($embedding) . "\n";
    }
    
    $metadata1 = $response1->getMetadata();
    echo "Model: " . $metadata1['model'] . "\n";

    // Test 2: Multiple Text Embeddings
    echo "Test 2: Multiple Text Embeddings\n";
    echo "--------------------------------\n";
    
    $texts = [
        "I love programming in PHP",
        "Python is great for machine learning", 
        "JavaScript runs in the browser",
        "Cats are wonderful pets"
    ];
    
    $response2 = $provider->createEmbeddings($texts, 'text-embedding-ada-002');
    
    $embeddings = $response2->getContent();
    if (is_string($embeddings)) {
        $embeddings = json_decode($embeddings, true);
    }
    echo "Number of texts: " . count($texts) . "\n";
    echo "Number of embeddings: " . count($embeddings) . "\n";
    
    foreach ($texts as $i => $text) {
        echo "Text $i: \"$text\"\n";
        $embeddingArr = $embeddings[$i]['embedding'];
        if (is_string($embeddingArr)) {
            $embeddingArr = json_decode($embeddingArr, true);
        }
        if (is_array($embeddingArr)) {
            echo "  Embedding dimensions: " . count($embeddingArr) . "\n";
            echo "  First 3 values: [" . implode(', ', array_slice($embeddingArr, 0, 3)) . "...]\n";
        } else {
            echo "  Embedding is not an array. Type: " . gettype($embeddingArr) . "\n";
        }
    }
    echo "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}