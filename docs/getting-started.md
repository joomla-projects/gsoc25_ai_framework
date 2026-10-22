# Getting Started

This guide will help you install the AI Framework and make your first request in just a few minutes.

## Requirements

- PHP 8.3.0 or higher
- Composer
- API keys for the providers you want to use

## Installation

### Via Composer

Add the AI package to your project:

```json
{
    "require": {
        "joomla/ai": "~4.0"
    }
}
```

Then run:
```bash
composer install
```

Alternatively, install directly:
```bash
composer require joomla/ai "~4.0"
```

For development with test sources:
```bash
composer require --prefer-source joomla/ai "~4.0"
```

## Configuration

### API Keys

You will need API keys from the providers you want to use:

- **OpenAI**: Get your key at https://platform.openai.com/api-keys
- **Anthropic**: Get your key at https://console.anthropic.com/
- **Ollama**: Runs locally, no API key needed

### Storing API Keys

You should not hardcode your API keys into your program code. Make them configurable via for example a registry object or by storing them in environment variables. **Never commit API keys to version control.**

## Your First Request

### Basic Chat Example

```php
<?php
require_once 'vendor/autoload.php';

use Joomla\AI\AIFactory;

// Create provider instance
$openai = (new AIFactory())->getAI('openai', [
    'api_key' => getenv('OPENAI_API_KEY')
]);

// Make your first chat request
$response = $openai->chat("Hello! Tell me about Joomla in one sentence.");

// Display the result
echo "Response: " . $response->getContent() . "\n";
echo "Model used: " . $response->getMetadata()['model'] . "\n";
echo "Provider: " . $response->getProvider() . "\n";
```

### Multi-Provider Example

```php
<?php
require_once 'vendor/autoload.php';

use Joomla\AI\AIFactory;

// Configure multiple providers
$providers = [
    'openai' => $openai = (new AIFactory())->getAI('openai', ['api_key' => getenv('OPENAI_API_KEY')]),
    'anthropic' => (new AIFactory())->getAI('anthropic', ['api_key' => getenv('ANTHROPIC_API_KEY')]),
    'ollama' => (new AIFactory())->getAI('ollama', []) // Local server, no API key needed
];

$question = "What is Joomla?";

foreach ($providers as $name => $provider) {
    try {
        $response = $provider->chat($question);
        echo "\n=== {$name} ===\n";
        echo $response->getContent() . "\n";
    } catch (Exception $e) {
        echo "{$name}: Error - " . $e->getMessage() . "\n";
    }
}
```

## Working with Different Capabilities

### Image Generation

```php
use Joomla\AI\AIFactory;

$openai = (new AIFactory())->getAI('openai', ['api_key' => getenv('OPENAI_API_KEY')]);

// Generate an image
$image = $openai->generateImage("A beautiful sunset over mountains", [
    'model' => 'dall-e-3',
    'size' => '1024x1024',
    'response_format' => 'b64_json'
]);

// Save the image
$image->saveFile('my_sunset.png');
echo "Image saved as my_sunset.png\n";
```

### Text-to-Speech

```php
$audio = $openai->speech("Welcome to Joomla! This is a text-to-speech demo.", [
    'model' => 'tts-1',
    'voice' => 'alloy',
    'response_format' => 'mp3'
]);

$audio->saveFile('welcome.mp3');
echo "Audio saved as welcome.mp3\n";
```

### Vision (Image Analysis)

```php
$vision = $openai->vision(
    "What do you see in this image?",
    "https://example.com/your-image.jpg"
);

echo "Vision analysis: " . $vision->getContent() . "\n";
```

## Setting Default Models

Avoid repeating model names by setting defaults:

```php
$openai = (new AIFactory())->getAI('openai', ['api_key' => getenv('OPENAI_API_KEY')]);

// Set a default model for all chat requests
$openai->setDefaultModel('gpt-4o-mini');

// These will use gpt-4o-mini
$response1 = $openai->chat("Hello!");
$response2 = $openai->chat("How are you?");

// Override the default for one request
$response3 = $openai->chat("Complex task", ['model' => 'gpt-4o']);

// Back to default
$response4 = $openai->chat("Simple task");

// Clear the default
$openai->unsetDefaultModel();
```

## Error Handling

All framework exceptions extend [`AIException`](../src/Exception/AIException.php). The [overview](overview.md#exceptions) lists when each one is thrown.

```php
use Joomla\AI\Exception\AIException;
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\LimitExceededException;
use Joomla\AI\Exception\QuotaExceededException;
use Joomla\AI\Exception\RateLimitException;

try {
    $response = $openai->chat("Hello!");
    echo $response->getContent();
} catch (AuthenticationException $e) {
    echo "Authentication failed: Check your API key\n";
} catch (LimitExceededException $e) {
    echo "Usage limit reached, try again in " . ceil($e->getRetryAfter()) . " seconds\n";
} catch (RateLimitException $e) {
    echo "The provider is rate limiting requests, try again later\n";
} catch (QuotaExceededException $e) {
    echo "Quota exceeded: Check your billing\n";
} catch (InvalidArgumentException $e) {
    echo "Invalid input: " . $e->getMessage() . "\n";
} catch (AIException $e) {
    echo "Provider error: " . $e->getMessage() . "\n";
}
```

Rate limits and temporary provider errors are retried automatically before an exception is thrown (see [Retries](features.md#retries)).

## Local Development with Ollama

For local AI without API costs:

1. **Install Ollama**: Download from https://ollama.com/
2. **Pull a model**: `ollama pull llama3`
3. **Use in your code**:

```php
use Joomla\AI\AIFactory;

$ollama = (new AIFactory())->getAI('ollama');

$response = $ollama->chat("Hello!", ['model' => 'llama3']);
echo $response->getContent();
```

## Response Object

All methods return the same [`Response`](../src/Response/Response.php) object, whichever provider you use:

```php
$response = $provider->chat("Hello!");

echo $response->getContent();       // the text (or base64 image, binary audio)
echo $response->getFinishReason();  // "stop", "length", "content_filter" or "tool_calls"
print_r($response->getUsage());     // ['input_tokens' => .., 'output_tokens' => .., 'total_tokens' => ..]
echo $response->getProvider();      // "OpenAI", "Anthropic" or "Ollama"

print_r($response->getMetadata());  // everything the provider returned, in its own format

$response->saveFile('output.txt');  // images, audio and text
```

## Next Steps

- **[Features](features.md)**: conversations, streaming, tool calling, JSON output, limits, retries and logging
- **[Provider Guides](providers/)**: provider-specific options and constraints

## Troubleshooting

**API Key Issues:**
- The API key is only read from the `api_key` option. If you load it from an environment variable, check that the variable is set for your web server or CLI process.
- Check that your API keys have sufficient credits/permissions

**Model Issues:**
- Verify the model name is correct and available
- Some models may require special access or billing setup
