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

use Joomla\AI\Provider\OpenAIProvider;

// Create provider instance
$openai = new OpenAIProvider([
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

use Joomla\AI\Provider\OpenAIProvider;
use Joomla\AI\Provider\AnthropicProvider;
use Joomla\AI\Provider\OllamaProvider;

// Configure multiple providers
$providers = [
    'openai' => new OpenAIProvider(['api_key' => getenv('OPENAI_API_KEY')]),
    'anthropic' => new AnthropicProvider(['api_key' => getenv('ANTHROPIC_API_KEY')]),
    'ollama' => new OllamaProvider() // Local server, no API key needed
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
use Joomla\AI\Provider\OpenAIProvider;

$openai = new OpenAIProvider(['api_key' => getenv('OPENAI_API_KEY')]);

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
$openai = new OpenAIProvider(['api_key' => getenv('OPENAI_API_KEY')]);

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

The framework provides specific exceptions that are inherited from [`AIException`](../src/Exception/AIException.php):

```php
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\RateLimitException;
use Joomla\AI\Exception\QuotaExceededException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;

try {
    $response = $openai->chat("Hello!");
    echo $response->getContent();
} catch (AuthenticationException $e) {
    echo "Authentication failed: Check your API key\n";
} catch (RateLimitException $e) {
    echo "Rate limited: Please wait before trying again\n";
} catch (QuotaExceededException $e) {
    echo "Quota exceeded: Check your billing\n";
} catch (InvalidArgumentException $e) {
    echo "Invalid input: " . $e->getMessage() . "\n";
} catch (ProviderException $e) {
    echo "Provider error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
}
```

## Local Development with Ollama

For local AI without API costs:

1. **Install Ollama**: Download from https://ollama.ai/
2. **Pull a model**: `ollama pull llama3`
3. **Use in your code**:

```php
use Joomla\AI\Provider\OllamaProvider;

$ollama = new OllamaProvider();

$response = $ollama->chat("Hello!", ['model' => 'llama3']);
echo $response->getContent();
```

## Response Object

All methods return a unified [`Response`](../src/Response/Response.php) object:

```php
$response = $provider->chat("Hello!");

// Primary content
echo $response->getContent();

// Provider metadata (model, usage, etc.)
$metadata = $response->getMetadata();
print_r($metadata);

// Provider name
echo $response->getProvider(); // "OpenAI", "Anthropic", or "Ollama"

// Save files (for images, audio)
$response->saveFile('output.png');
```

## Next Steps

Now that you have the basics working:

- **[Provider Guides](providers/)** - Provider-specific features and limitations

## Troubleshooting

**API Key Issues:**
- Ensure environment variables are set correctly
- Restart your terminal/web server after setting environment variables
- Check that your API keys have sufficient credits/permissions

**Model Issues:**
- Verify the model name is correct and available
- Some models may require special access or billing setup
