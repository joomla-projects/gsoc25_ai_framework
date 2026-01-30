<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Joomla\AI\Provider\GeminiProvider;
use Joomla\AI\Response\Response;
use Joomla\Http\HttpFactory;
use Joomla\Http\Http;
use Joomla\Http\Response as HttpResponse;

echo "=== GeminiProvider Test Suite ===\n\n";

// 1. Try to load real config
$configFile = __DIR__ . '/../config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
$apiKey = $config['gemini_api_key'] ?? getenv('GEMINI_API_KEY');

if ($apiKey) {
    echo "[Integration Mode] Real API Key found. Testing against Google Gemini API...\n";
    testRealApi($apiKey);
} else {
    echo "[Mock Mode] No API Key found. Testing logic using Mock HTTP Client...\n";
    testMockApi();
}

function testRealApi($apiKey) {
    try {
        $provider = new GeminiProvider(['api_key' => $apiKey]);
        echo "Provider initialized.\n";

        $response = $provider->chat("Explain what Joomla is in one sentence.");
        
        echo "Response received:\n";
        echo "Model: " . $response->getModel() . "\n";
        echo "Content: " . $response->getContent() . "\n"; 

        if (empty($response->getContent())) {
            throw new Exception("Empty content received!");
        }

        echo "\n[PASS] Real API Test Passed.\n";

    } catch (Exception $e) {
        echo "[FAIL] Real API Test Failed: " . $e->getMessage() . "\n";
    }
}

function testMockApi() {
    // anonymous class for HttpFactory mock
    $mockHttpFactory = new class extends HttpFactory {
        public function getHttp(array $options = [], $adapters = null): Http {
            // anonymous class for Http mock
            return new class($options) extends Http {
                public function post($url, $data, array $headers = [], $timeout = null) {
                    echo "  -> Mock POST request to: " . substr($url, 0, 50) . "...\n";
                    
                    // Simulate Gemini Success Response
                    $body = json_encode([
                        'candidates' => [
                            [
                                'content' => [
                                    'parts' => [
                                        ['text' => 'Joomla is a powerful, open-source content management system used for building websites and applications.']
                                    ]
                                ]
                            ]
                        ]
                    ]);

                    return new HttpResponse(200, [], $body);
                }
            };
        }
    };

    try {
        echo "Initializing Provider with Mock Factory...\n";
        // Pass mock factory to constructor
        $provider = new GeminiProvider(['api_key' => 'mock_key'], $mockHttpFactory);
        
        echo "Sending chat request...\n";
        $response = $provider->chat("Test Message");

        echo "Response processed.\n";
        echo "Content: " . $response->getContent() . "\n";

        if ($response->getContent() === 'Joomla is a powerful, open-source content management system used for building websites and applications.') {
             echo "\n[PASS] Mock Logic Test Passed.\n";
        } else {
             throw new Exception("Unexpected content in mock response.");
        }

    } catch (Exception $e) {
        echo "[FAIL] Mock Logic Test Failed: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString();
    }
}
