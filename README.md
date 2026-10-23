# The Joomla! AI Package

## Installation via Composer

Add `"joomla/ai": "~4.0"` to the require block in your composer.json and then run `composer install`.

```json
{
	"require": {
		"joomla/ai": "~4.0"
	}
}
```

Alternatively, you can simply run the following from the command line:

```sh
composer require joomla/ai "~4.0"
```

Requires PHP 8.3 or higher. See [Getting Started](docs/getting-started.md) for details.

## Quick start

```php
use Joomla\AI\AIFactory;

$ai = (new AIFactory())->getAI('anthropic', [
    'api_key' => getenv('ANTHROPIC_API_KEY'), // or 'openai' with an OpenAI key, or 'ollama' with no key
]);

$response = $ai->chat('Write a haiku about Joomla.');

echo $response->getContent();
echo $response->getUsage()['total_tokens'];
```

## What it does

- **Chat, vision, images, audio, embeddings and moderation** behind shared interfaces. See the [capability table](docs/overview.md#supported-providers).
- **[Conversations](docs/features.md#conversations)** with memory, which can be saved and continued later.
- **[Streaming](docs/features.md#streaming)** through a generator or a callback.
- **[Tool calling](docs/features.md#tool-calling)** and **[JSON schema output](docs/features.md#json-schema-output)**, in one format for every provider.
- **[Limits](docs/features.md#limits)** on requests and tokens, per user if you like, to prevent excessive usage.
- **[Retries](docs/features.md#retries)** with backoff, **[PSR-3 logging and hooks](docs/features.md#logging-and-hooks)**.
- **One response object** with the same content, usage and finish-reason getters for every provider, and typed exceptions for errors.

## Documentation

- **[Overview](docs/overview.md)**: architecture and supported providers
- **[Getting Started](docs/getting-started.md)**: install, configure, first requests, error handling
- **[Features](docs/features.md)**: conversations, streaming, tools, JSON output, limits, retries, logging
- **Provider guides**: [OpenAI](docs/providers/openai.md), [Anthropic](docs/providers/anthropic.md), [Ollama](docs/providers/ollama.md)

Provider API references: [OpenAI](https://platform.openai.com/docs/api-reference), [Anthropic](https://platform.claude.com/docs), [Ollama](https://github.com/ollama/ollama/blob/main/docs/api.md).
