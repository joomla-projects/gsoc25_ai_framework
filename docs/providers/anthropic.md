# Anthropic Provider

The Anthropic Provider ([`AnthropicProvider`](../../src/Provider/AnthropicProvider.php)) focuses on conversational AI using Claude models, offering superior reasoning capabilities.

## Capabilities Overview

| Feature | Description |
|---------|-------------|
| **Chat** | Advanced text conversation with Claude models |
| **Vision** | Image analysis and description using Claude's vision capabilities |
| **Model Management** | List available models and retrieve model information |

## Configuration

```php
use Joomla\AI\AIFactory;

$anthropic = AIFactory::getAI('anthropic', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'model' => 'claude-3-5-sonnet' // Optional: default model
]);
```

## Chat Completions

### Basic Chat
```php
$response = $anthropic->chat("Explain the advantages of Joomla over other CMS platforms.");
echo $response->getContent();
```

### Advanced Chat with options
```php
$response = $anthropic->chat("How do I optimize database queries in Joomla?", [
    'model' => 'claude-3-5-sonnet',
    'max_tokens' => 1000,
    'temperature' => 0.3
]);

$metadata = $response->getMetadata();
echo "Model used: " . $metadata['model'] . "\n";
echo "Tokens used: " . $metadata['usage']['input_tokens'] + $metadata['usage']['output_tokens'] . "\n";
```

### Message History
```php
$response = $anthropic->chat("", [
    'messages' => [
        ['role' => 'user', 'content' => 'What is Joomla?'],
        ['role' => 'assistant', 'content' => 'Joomla is a free, open-source content management system...'],
        ['role' => 'user', 'content' => 'How does it compare to WordPress?']
    ],
    'system' => 'You are a helpful web development consultant.'
]);

echo $response->getContent();
```

## Vision

Analyze images with Claude's vision capabilities:

### URL Image Analysis
```php
$response = $anthropic->vision(
    "Analyze this website design and suggest improvements for user experience",
    "https://example.com/website-screenshot.jpg"
);

echo $response->getContent();
```

### Base64 Image Analysis
```php
$imageData = base64_encode(file_get_contents('ui-mockup.png'));
$response = $anthropic->vision(
    "Review this UI mockup and provide detailed feedback on the design principles",
    "data:image/png;base64," . $imageData,
    [
        'model' => 'claude-3-5-sonnet',
        'max_tokens' => 1500
    ]
);

echo $response->getContent();
```

## Model Management

### List Available Models
```php
$models = $anthropic->getAvailableModels();
echo "Available Models: " . implode(', ', $models) . "\n";
```

## Default Model Management

Set defaults for consistent model usage:

```php
// Set a default model
$anthropic->setDefaultModel('claude-3-5-sonnet');

// These will use the default
$chat1 = $anthropic->chat("What is web accessibility?");
$vision1 = $anthropic->vision("Describe this image", $imageUrl);

// Override for specific calls
$detailed = $anthropic->chat("Provide a comprehensive analysis", [
    'model' => 'claude-3-opus',
    'max_tokens' => 2000
]);

// Clear the default
$anthropic->unsetDefaultModel();
```

## Error Handling

Anthropic-specific error patterns:

```php
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\RateLimitException;
use Joomla\AI\Exception\InvalidArgumentException;

try {
    $response = $anthropic->chat("Hello", [
        'max_tokens' => 500000 // Exceeds model limit
    ]);
} catch (InvalidArgumentException $e) {
    echo "Parameter error: " . $e->getMessage();
} catch (AuthenticationException $e) {
    echo "Authentication failed: Check your API key";
} catch (RateLimitException $e) {
    echo "Rate limited: " . $e->getMessage();
}
```

## Next Steps

- **[OpenAI Provider](openai.md)**
- **[Ollama Provider](ollama.md)**
