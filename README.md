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

If you want to include the test sources and docs, use

```sh
composer require --prefer-source joomla/ai "~4.0"
```

## Using the AI Framework
The AI Framework provides a straightforward, provider-agnostic interface for working with three main AI providers: OpenAI, Anthropic and Ollama. Instead of writing separate code for each provider’s SDK, developers write once and simply switch providers by changing configuration.

You can find the official provider APIs documentation here:
- OpenAI API: https://platform.openai.com/docs/api-reference
- Anthropic API: https://docs.anthropic.com/claude/docs
- Ollama API: https://github.com/ollama/ollama/blob/main/docs/api.md

The AI framework is built upon the Http package which provides an easy way to consume URLs and web services in a transport independent way. Joomla\Http currently supports streams, sockets and cURL. The framework centralizes HTTP handling in the Abstract Provider. Providers encapsulate:
- Base URLs, headers, and auth (API keys/tokens)
- Request building (JSON/multipart)
- Response normalization and error mapping into framework exceptions

## Instantiating a Provider

Each provider is instantiated with its configuration (API key, defaults such as model or base URL). You can override these defaults per call when needed.

### OpenAI:
```php
use Joomla\AI\Provider\OpenAIProvider;

$openai = new OpenAIProvider([
  'api_key' => getenv('OPENAI_API_KEY'),
  // Optional defaults:
  // 'model' => 'gpt-4o',
  // 'base_url' => 'https://api.openai.com/v1',
]);
```

### Anthropic:
```php
use Joomla\AI\Provider\AnthropicProvider;

$anthropic = new AnthropicProvider([
  'api_key' => getenv('ANTHROPIC_API_KEY'),
  // 'model' => 'claude-3-5-sonnet',
]);
```

### Ollama (local):
```php
use Joomla\AI\Provider\OllamaProvider;

$ollama = new OllamaProvider([
  // 'base_url' => 'http://localhost:11434',
  // 'model' => 'llama3',
]);
```

## Supported Methods

| Provider | Methods |
| --- | --- |
| OpenAI | `chat`, `vision`, `generateImage`, `createImageVariation`, `editImage`, `speech`, `transcribe`, `translate`, `createEmbeddings`, `moderate`, `isContentFlagged`|
| Anthropic | `chat`, `vision`, `getModel`|
| Ollama | `chat`, `generate`, `pullModel`, `copyModel`, `deleteModel`, `checkModelExists`, `getRunningModels`|

Not all providers implement every capability. The framework exposes capabilities via interfaces (e.g. ChatInterface, ImageInterface). Developers can use what each provider supports.

## Making your first request
All providers implement a shared set of capability interfaces (e.g., Chat, Images, Audio). Invoke these methods directly, passing per-call options to override defaults.

```php
// Chat example (OpenAI)
$response = $openai->chat("Write a haiku about Joomla.", [
  'model' => 'gpt-4o-mini', // overrides constructor default if set
]);
echo $response->getContent();           // primary content (e.g. text)
$meta = $response->getMetadata();       // metadata content (e.g. model, usage)
```

## Error handling
Provider HTTP errors are mapped to framework exceptions (e.g. auth, rate limit, invalid arguments). Catch and handle them as needed.
```php
try {
  $response = $openai->chat("Hello!");
} catch (\Throwable $e) {
  // Log and surface a friendly message
}
```

## Documentation

- **[Overview](docs/overview.md)**  
  Architecture & goals  

- **[Getting Started](docs/getting-started.md)**  
  Install, configure, first provider  

- **[Guides](guides/)**  
  Task-specific guides (Chat, Images, Audio, etc.)  

---
