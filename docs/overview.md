## Overview

The AI package provides an abstraction layer to access the services of several AI providers. It provides a unified, provider-agnostic interface for integrating multiple AI services into your applications. Instead of learning different SDKs and handling varying response formats, you write code once and switch providers by changing configuration.

Official provider API references:
- OpenAI: https://platform.openai.com/docs/api-reference
- Anthropic: https://docs.anthropic.com/claude/docs
- Ollama: https://github.com/ollama/ollama/blob/main/docs/api.md

## Supported Providers

| Provider | Chat | Vision | Images | Audio | Embeddings | Moderation | Models |
|----------|------|--------|---------|-------|------------|------------|---------|
| **OpenAI** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Anthropic** | ✓ | ✓ | - | - | - | - | ✓ |
| **Ollama** | ✓ | - | - | - | - | - | ✓ |

## Architecture

### Core Components

**Interfaces** (`/src/Interface/`)
Define the interfaces that providers implement:
- [`ProviderInterface`](../src/Interface/ProviderInterface.php) - Base provider contract
- [`ChatInterface`](../src/Interface/ChatInterface.php) - Text conversation
- [`ImageInterface`](../src/Interface/ImageInterface.php) - Image generation, editing, variations
- [`AudioInterface`](../src/Interface/AudioInterface.php) - Speech synthesis, transcription, translation
- [`EmbeddingInterface`](../src/Interface/EmbeddingInterface.php) - Vector embeddings
- [`ModelInterface`](../src/Interface/ModelInterface.php) - Model management and capabilities
- [`ModerationInterface`](../src/Interface/ModerationInterface.php) - Content safety

**Abstract Provider** (`/src/AbstractProvider.php`)
Centralizes common functionality:
- HTTP client management (GET, POST, DELETE, multipart)
- Error mapping (401→Auth, 429→RateLimit/Quota, etc.)
- JSON response parsing

**Response Object** (`/src/Response/Response.php`)
Unified response wrapper that extends Joomla's HttpResponse:
- `getContent()` - Primary result (text, base64 image, binary audio)
- `getMetadata()` - Normalized provider details (model, usage, formats)
- `getProvider()` - Provider name ("OpenAI", "Anthropic", "Ollama")
- `saveFile($path)` - Method to save the generated output

**Providers** (`/src/Provider/`)
- [`OpenAIProvider`](../src/Provider/OpenAIProvider.php) - Implements methods related to OpenAI
- [`AnthropicProvider`](../src/Provider/AnthropicProvider.php) - Implements methods related to Anthropic
- [`OllamaProvider`](../src/Provider/OllamaProvider.php) - Implements methods related to Ollama

### Exception Hierarchy

All exceptions inherit from [`AIException`](../src/Exception/AIException.php):
- [`AuthenticationException`](../src/Exception/AuthenticationException.php)
- [`RateLimitException`](../src/Exception/RateLimitException.php)
- [`QuotaExceededException`](../src/Exception/QuotaExceededException.php)
- [`ProviderException`](../src/Exception/ProviderException.php)
- [`InvalidArgumentException`](../src/Exception/InvalidArgumentException.php)
- [`UnserializableResponseException`](../src/Exception/UnserializableResponseException.php)

## Key Design Principles

### Provider Abstraction
```php
// Same interface, different providers
$openai = new OpenAIProvider(['api_key' => $key]);
$anthropic = new AnthropicProvider(['api_key' => $key]);

// Identical usage
$response1 = $openai->chat("Hello!");
$response2 = $anthropic->chat("Hello!");
```

### Unified Response Format
```php
$response = $provider->chat("Hello!");
echo $response->getContent();      // "Hello! How can I help you today?"
echo $response->getProvider();     // "OpenAI"
$metadata = $response->getMetadata();
echo $metadata['model'];           // "gpt-4o"

```

### Default Model Management
Flexible precedence system for model selection with four levels of fallback:

1. **Per-call `options['model']`** - Highest priority
2. **Provider default via `setDefaultModel()`** - Session-level default
3. **Constructor option `'model'`** - Provider-level configuration
4. **Method-specific fallback** - Built-in defaults per capability

### File Handling
Streamlined output persistence:
```php
// Images
$image = $provider->generateImage("A sunset");
$image->saveFile('sunset.png');

// Audio
$audio = $provider->speech("Hello world");
$audio->saveFile('greeting.mp3');
```

## Next Steps

- **[Getting Started](getting-started.md)** - Installation and first requests
- **[Provider Guides](providers/)** - Provider-specific documentation
