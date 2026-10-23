# Overview

The AI package gives PHP applications one interface to several AI providers. You write code once and switch providers by changing configuration; the framework handles each provider's request format, authentication, responses and errors.

## Supported Providers

| Provider | Chat | Streaming | Vision | Tools | JSON schema | Images | Audio | Embeddings | Moderation | Models |
|----------|------|-----------|--------|-------|-------------|--------|-------|------------|------------|--------|
| **OpenAI** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Anthropic** | ✓ | ✓ | ✓ | ✓ | ✓ | - | - | - | - | ✓ |
| **Ollama** | ✓ | ✓ | ✓ | ✓ | ✓ | - | - | ✓ | - | ✓ |
| **Grok** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | - | - | - | ✓ |

[Conversations](features.md#conversations), [limits](features.md#limits), [retries](features.md#retries) and [logging](features.md#logging-and-hooks) work with every provider.

## Architecture

**AI Factory** ([`AIFactory`](../src/AIFactory.php))
Creates providers by name (`openai`, `anthropic`, `ollama`, `grok`) or class name. It is instantiable, so it can be registered in a DI container, and `registerProvider()` adds custom providers.

**AI** ([`AI`](../src/AI.php))
Wraps a provider: calls are passed through to it, and `conversation()` starts a [conversation](features.md#conversations).

**Providers** ([`src/Provider/`](../src/Provider/))
[`OpenAIProvider`](../src/Provider/OpenAIProvider.php), [`AnthropicProvider`](../src/Provider/AnthropicProvider.php), [`OllamaProvider`](../src/Provider/OllamaProvider.php) and [`GrokProvider`](../src/Provider/GrokProvider.php) build each provider's requests and turn its responses into a `Response`. OpenAI and Grok share their Chat Completions code through [`OpenAICompatibleTrait`](../src/Provider/OpenAICompatibleTrait.php).

**Abstract Provider** ([`AbstractProvider`](../src/AbstractProvider.php))
Shared by all providers:
- HTTP requests (JSON, multipart, streaming), timeouts and HTTP options
- Retries with backoff, logging and hooks
- Limit checks
- Mapping HTTP errors to exceptions and validating responses

**Interfaces** ([`src/Interface/`](../src/Interface/))
What a provider can do: [`ChatInterface`](../src/Interface/ChatInterface.php), [`StreamInterface`](../src/Interface/StreamInterface.php), [`ToolInterface`](../src/Interface/ToolInterface.php), [`ImageInterface`](../src/Interface/ImageInterface.php), [`AudioInterface`](../src/Interface/AudioInterface.php), [`EmbeddingInterface`](../src/Interface/EmbeddingInterface.php), [`ModerationInterface`](../src/Interface/ModerationInterface.php), [`ModelInterface`](../src/Interface/ModelInterface.php). Check them with `instanceof` before using a capability.

**Response** ([`Response`](../src/Response/Response.php))
The same object for every provider:
- `getContent()`: the result (text, base64 image, binary audio)
- `getUsage()`, `getFinishReason()`: token usage and finish reason, normalised across providers
- `getToolCalls()`, `getJson()`, `getEmbeddings()`: tool requests, JSON output, embedding vectors
- `getMetadata()`: everything the provider returned, in its own format
- `saveFile($path)`: save images, audio or text

**Conversation** ([`Conversation`](../src/Conversation/Conversation.php))
Keeps the message history of a multi-turn chat.

**Limits** ([`src/Limit/`](../src/Limit/))
`Limit::requests()` and `Limit::tokens()`; usage is kept in files so limits hold across web requests.

## Exceptions

All exceptions extend [`AIException`](../src/Exception/AIException.php):

| Exception | When |
|-----------|------|
| [`AuthenticationException`](../src/Exception/AuthenticationException.php) | Missing or invalid API key (401/403), Ollama server not running |
| [`RateLimitException`](../src/Exception/RateLimitException.php) | The provider's rate limit (429), after retries |
| [`QuotaExceededException`](../src/Exception/QuotaExceededException.php) | Out of credits or quota |
| [`LimitExceededException`](../src/Exception/LimitExceededException.php) | Blocked by one of your [limits](features.md#limits); nothing was sent |
| [`InvalidArgumentException`](../src/Exception/InvalidArgumentException.php) | Invalid option, model or file, caught before sending |
| [`UnserializableResponseException`](../src/Exception/UnserializableResponseException.php) | The provider returned a response that could not be read |
| [`ProviderException`](../src/Exception/ProviderException.php) | Any other provider error |

## Model selection

The model for a call is chosen in this order:

1. `model` in the call's options
2. The default set with `setDefaultModel()`
3. The `model` option given when the provider was created
4. The provider's built-in default for that capability
