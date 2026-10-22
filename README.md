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
use Joomla\AI\AIFactory;

$openai = (new AIFactory())->getAI('openai', [
  'api_key' => getenv('OPENAI_API_KEY'),
  // Optional defaults:
  // 'model' => 'gpt-4o',
  // 'base_url' => 'https://api.openai.com/v1',
]);
```

### Anthropic:
```php
use Joomla\AI\AIFactory;

$anthropic = (new AIFactory())->getAI('anthropic', [
  'api_key' => getenv('ANTHROPIC_API_KEY'),
  // 'model' => 'claude-3-5-sonnet',
]);
```

### Ollama (local):
```php
use Joomla\AI\AIFactory;

$ollama = (new AIFactory())->getAI('ollama', [
  // 'base_url' => 'http://localhost:11434',
  // 'model' => 'llama3',
]);
```

### Streaming
OpenAI, Anthropic and Ollama stream chat responses as they are generated. Use the generator, which yields text deltas and returns the final `Response`:

```php
$stream = $anthropic->chatStream('Write a haiku about Joomla');

foreach ($stream as $delta) {
    echo $delta;
    flush();
}

$response = $stream->getReturn(); // full text, getUsage(), getFinishReason()
```

Or pass a callback to `chat()`:

```php
$response = $openai->chat('Write a haiku about Joomla', [
    'stream'   => true,
    'on_chunk' => fn (string $delta) => print($delta),
]);
```

Ollama also has `generateStream()`, and `generate()` accepts the same `stream` / `on_chunk` options. Streaming uses cURL directly (`Joomla\Http` buffers whole responses); the `timeout` and `http_options` options still apply, and a custom `Joomla\AI\Http\StreamTransportInterface` can be passed as `stream_transport`.

### Tool calling
Pass tools in one format for every provider (OpenAI, Anthropic, Ollama). The model's requests come back from `getToolCalls()` as `['id', 'name', 'arguments']`, and `buildToolResultMessages()` turns your results into the provider's own message format for the next turn:

```php
$tools = [[
    'name' => 'get_weather',
    'description' => 'Get the current weather for a city',
    'parameters' => [
        'type' => 'object',
        'properties' => ['city' => ['type' => 'string']],
        'required' => ['city'],
        'additionalProperties' => false,
    ],
    'strict' => true, // optional
]];

$messages = [['role' => 'user', 'content' => 'What is the weather in Paris?']];
$response = $ai->chat('', ['messages' => $messages, 'tools' => $tools]);

while ($response->hasToolCalls()) {
    $results = [];
    foreach ($response->getToolCalls() as $call) {
        $results[$call['id']] = getWeather($call['arguments']['city']); // your code
    }

    $messages = array_merge($messages, $ai->buildToolResultMessages($response, $results));
    $response = $ai->chat('', ['messages' => $messages, 'tools' => $tools]);
}

echo $response->getContent();
```

`tool_choice` accepts `'auto'`, `'none'`, `'required'` or `['name' => 'get_weather']` (Ollama ignores it), and `'parallel_tool_calls' => false` limits the model to one call per turn. Some newer Claude models, such as `claude-opus-5-5`, reject `'required'` and a named tool; use `'auto'` and ask for the tool in the prompt. Tools can't be combined with streaming.

### JSON schema output
`response_schema` constrains the reply to a JSON schema (OpenAI `response_format`, Anthropic `output_config.format`, Ollama `format`). `getJson()` decodes it:

```php
$response = $ai->chat('Extract the person: Ada Lovelace, 36', [
    'response_schema' => [
        'name' => 'person', // optional, used by OpenAI
        'schema' => [
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string'], 'age' => ['type' => 'integer']],
            'required' => ['name', 'age'],
            'additionalProperties' => false,
        ],
    ],
]);

$person = $response->getJson(); // ['name' => 'Ada Lovelace', 'age' => 36]
```

You can also pass the schema directly as `response_schema`.

### Retries
Rate limits (429, except quota/billing errors), 5xx responses and connection errors are retried up to `max_retries` times (default 2). The provider's `Retry-After` / `retry-after-ms` header is honoured; otherwise the delay is exponential backoff starting at `retry_base_delay` seconds (default 0.5). No wait is longer than `retry_max_delay` (default 60). A stream is only retried if it fails before any text was received.

```php
$ai = $factory->getAI('anthropic', ['api_key' => $key, 'max_retries' => 4, 'retry_max_delay' => 20]);
```

Set `'max_retries' => 0` to turn retries off.

### Logging and hooks
Providers implement PSR-3 `LoggerAwareInterface`. Pass a logger as the `logger` option or call `setLogger()`. Requests are logged at debug level, responses at info level (status and duration), retries at warning level and failures at error level. Only the provider, method, URL, status and timing are logged: never headers or bodies, so API keys and prompts stay out of your logs.

Hooks run around every request:

```php
$ai->getProvider()
    ->addHook('before_request', fn (array $request) => $metrics->increment('ai.requests'))
    ->addHook('after_response', fn (array $request, $response, float $seconds) => $metrics->timing('ai.latency', $seconds))
    ->addHook('on_retry', fn (array $request, float $delay, $reason) => null)
    ->addHook('on_error', fn (array $request, \Throwable $e) => $alerts->notify($e));
```

`$request` contains `provider`, `method`, `url`, `stream` and `attempt`. Hooks can also be passed as the `hooks` option, keyed by event name.

### Embeddings
`getEmbeddings()` returns one float vector per input, and `getEmbedding()` returns the first. Base64 responses (`encoding_format => 'base64'`) are decoded for you.

```php
$vectors = $openai->createEmbeddings(['first text', 'second text'], 'text-embedding-3-small')->getEmbeddings();
```

### Custom providers and DI
`AIFactory` is a regular service: inject an `HttpFactory` and register extra providers (any class extending `AbstractProvider`), or pass a provider class name straight to `getAI()`.

```php
use Joomla\AI\AIFactory;
use Joomla\Http\HttpFactory;

$factory = new AIFactory(new HttpFactory());
$factory->registerProvider('mistral', MistralProvider::class);

$mistral = $factory->getAI('mistral', ['api_key' => $key]);
$custom  = $factory->getAI(MyProvider::class, []);
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

- **[Guides](docs/providers/)**  
  Provider-specific guides with code examples
---
