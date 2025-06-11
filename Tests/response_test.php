<?php
require_once '../src/Response.php';

echo "Testing Response class with magic getter...\n\n";

$response = new \Joomla\AI\Response(
    "Hello from OpenAI!",
    "OpenAI",
    ["model" => "gpt-4o-mini", "tokens" => 25],
    200
);

echo "Testing Method Calls: \n";
echo "Content (method): " . $response->getContent() . "\n";
echo "Provider (method): " . $response->getProvider() . "\n";
echo "Status (method): " . $response->getStatusCode() . "\n";

echo "\nTesting Magic Getter: \n";
echo "Content (magic): " . $response->content . "\n";        // Uses __get()
echo "Provider (magic): " . $response->provider . "\n";      // Uses __get()
echo "Status (magic): " . $response->statuscode . "\n";          // Uses __get()

echo "\nTesting Metadata: \n";
$metadata = $response->metadata; // Uses __get()
echo "Model: " . $metadata['model'] . "\n";
echo "Tokens: " . $metadata['tokens'] . "\n";

echo "\nTesting Error Case: \n";
echo "Accessing invalid property:\n";
$invalid = $response->invalidProperty;  // Should trigger error
