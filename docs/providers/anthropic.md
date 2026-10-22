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

$anthropic = (new AIFactory())->getAI('anthropic', [
    'api_key' => getenv('ANTHROPIC_API_KEY'), // Required: the key is only read from this option
    'model' => 'claude-opus-5-5', // Optional: default model
    'timeout' => 60, // Optional: request timeout in seconds
    'http_options' => [], // Optional: passed to HttpFactory::getHttp()
]);
```

Vision accepts an `http(s)` image URL (sent to Anthropic as a URL source, Anthropic fetches it) or a base64 data URI. Local file paths are not read; encode the file yourself as shown below.

## Chat Completions

### Basic Chat
```php
$response = $anthropic->chat("Explain the advantages of Joomla over other CMS platforms.");
echo $response->getContent();
```

### Advanced Chat with options
```php
$response = $anthropic->chat("How do I optimize database queries in Joomla?", [
    'model' => 'claude-opus-5-5',
    'max_tokens' => 1000,
    'system' => 'You are a Joomla performance expert. Answer concisely.'
]);

$metadata = $response->getMetadata();
echo "Model used: " . $metadata['model'] . "\n";
echo "Tokens used: " . $response->getUsage()['total_tokens'] . "\n";
echo "Finish reason: " . $response->getFinishReason() . "\n";
```

`temperature`, `top_p` and `top_k` are passed through when set, but current Claude models (e.g. `claude-opus-5-5`, `claude-sonnet-5-5`) reject non-default sampling values with a 400 error. Steer the output with `system` instead.

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
        'model' => 'claude-opus-5-5',
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
$anthropic->setDefaultModel('claude-sonnet-5-5');

// These will use the default
$chat1 = $anthropic->chat("What is web accessibility?");
$vision1 = $anthropic->vision("Describe this image", $imageUrl);

// Override for specific calls
$detailed = $anthropic->chat("Provide a comprehensive analysis", [
    'model' => 'claude-opus-5-5',
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
