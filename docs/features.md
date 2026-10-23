# Features

These features work the same way for every provider that supports them. Provider-specific options are in the [provider guides](providers/).

- [Conversations](#conversations)
- [Streaming](#streaming)
- [Tool calling](#tool-calling)
- [JSON schema output](#json-schema-output)
- [Limits](#limits)
- [Retries](#retries)
- [Logging and hooks](#logging-and-hooks)
- [Embeddings](#embeddings)
- [Custom providers and dependency injection](#custom-providers-and-dependency-injection)

The examples use `$ai` from `(new AIFactory())->getAI('anthropic', ['api_key' => $key])`. Any provider works.

## Conversations

`conversation()` starts a multi-turn chat that remembers earlier messages. The history is kept in the `Conversation` object and sent with every message, so it works with every chat provider.

```php
$chat = $ai->conversation([
    'system' => 'You are a helpful Joomla assistant.',
    'max_tokens' => 1000,
]);

$chat->send('My site runs Joomla 5. How do I enable SEF URLs?');
$reply = $chat->send('And where do I check that it worked?'); // knows the earlier question

echo $reply->getContent();
print_r($chat->getUsage()); // tokens summed over all turns
```

- The options given to `conversation()` apply to every turn; `send($message, $options)` can override them for one turn.
- `stream($message)` streams the reply (see [Streaming](#streaming)) and adds it to the history when the stream ends.
- When a reply asks for tools, run them and call `submitToolResults([$id => $result])` to continue. `send()` throws until you do, because the APIs reject a new message while tool results are missing.
- `max_turns` keeps only the last N user turns to limit tokens. It never splits a tool call from its result. Trimming changes the start of the history, so it reduces prompt-cache hits.
- `getMessages()` returns the history in the provider's own format, and `clear()` empties it.

Save a conversation (in the session, the database, ...) and continue it later:

```php
use Joomla\AI\Conversation\Conversation;

$session->set('chat', json_encode($chat));

$chat = Conversation::fromArray($ai->getProvider(), json_decode($session->get('chat'), true));
$chat->send('Where were we?');
```

Pending tool calls are not saved, so finish them before storing.

## Streaming

OpenAI, Anthropic, Ollama and Gemini can stream replies as they are generated. `chatStream()` yields text deltas and returns the final `Response`:

```php
$stream = $ai->chatStream('Write a haiku about Joomla');

foreach ($stream as $delta) {
    echo $delta;
    flush();
}

$response = $stream->getReturn(); // full text, getUsage(), getFinishReason()
```

Or pass a callback to `chat()`:

```php
$response = $ai->chat('Write a haiku about Joomla', [
    'stream'   => true,
    'on_chunk' => fn (string $delta) => print($delta),
]);
```

Ollama also has `generateStream()`, and `generate()` accepts the same `stream` / `on_chunk` options.

Streaming uses cURL directly, because `Joomla\Http` buffers whole responses. The `timeout` and `http_options` options still apply, and a custom `Joomla\AI\Http\StreamTransportInterface` can be passed as `stream_transport`. Tools can't be combined with streaming.

## Tool calling

Tools are defined in one format for every provider. The model's requests come back from `getToolCalls()` as `['id', 'name', 'arguments']`, and `buildToolResultMessages()` turns your results into the provider's message format for the next turn:

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

In a [conversation](#conversations), `submitToolResults()` does the message handling for you.

- `tool_choice` accepts `'auto'`, `'none'`, `'required'` or `['name' => 'get_weather']`. Ollama ignores it.
- `'parallel_tool_calls' => false` limits the model to one call per turn (not supported by Gemini or Ollama).
- Some Claude models, such as `claude-opus-5-5`, reject `'required'` and a named tool. Use `'auto'` and ask for the tool in the prompt.

## JSON schema output

`response_schema` constrains the reply to a JSON schema (sent as OpenAI `response_format`, Anthropic `output_config.format`, Ollama `format` or Gemini `response_format`). `getJson()` decodes the reply:

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

You can also pass the schema itself as `response_schema`.

## Limits

Limits stop excessive usage before a request leaves your application. They are checked before every call and updated after every model response. A blocked call throws `LimitExceededException`, and nothing is sent to the provider.

| Limit | Counts |
|-------|--------|
| `Limit::requests($max, $seconds)` | Model calls (chat, generate, embeddings, images, audio) |
| `Limit::tokens($max, $seconds)` | Tokens (input + output) as reported by the provider |

Both use a sliding window: at most `$max` within any `$seconds`.

```php
use Joomla\AI\Limit\Limit;

$ai = $factory->getAI('openai', [
    'api_key' => $key,
    'limits' => [
        Limit::requests(20, 60),         // 20 calls per minute
        Limit::tokens(200000, 86400),    // 200k tokens per day
    ],
]);
```

Handle a blocked call:

```php
use Joomla\AI\Exception\LimitExceededException;

try {
    $response = $ai->chat($question);
} catch (LimitExceededException $e) {
    $wait = ceil($e->getRetryAfter()); // seconds until the limit allows it again
    echo "Too many requests, try again in $wait seconds.";
}
```

**Scope.** By default a limit is global. Pass `key` to give each user (or site, or API key) their own allowance:

```php
$user = $app->getIdentity();

Limit::tokens(50000, 86400, key: fn () => 'user:' . $user->id);
```

**Storage.** Usage is kept in small files, locked while they are updated, so a limit holds across web requests. They go in the system temp directory unless you pass a directory as the third argument, e.g. `Limit::requests(20, 60, JPATH_CACHE . '/ai-limits')`. To share a limit between several servers, point them at the same shared directory.

**Good to know.**
- Tokens are only known after a reply, so the call that crosses a token limit completes; the calls after it are blocked. Keep `max_tokens` low to bound that last call.
- Retries of one call count once. Helper calls (moderation checks, model listings) are blocked once a limit is reached, but are not counted.
- One limit instance can be shared by several providers (`$provider->addLimit($limit)`) to limit them together.
- These are limits in your application. A provider's own rate limits still apply and are handled by [retries](#retries).

## Retries

Provider rate limits (429, except quota and billing errors), 5xx responses and connection errors are retried up to `max_retries` times (default 2). The provider's `Retry-After` / `retry-after-ms` header is honoured. Otherwise the delay is exponential backoff starting at `retry_base_delay` seconds (default 0.5). No wait is longer than `retry_max_delay` seconds (default 60). A stream is only retried if it fails before any text was received.

```php
$ai = $factory->getAI('anthropic', ['api_key' => $key, 'max_retries' => 4, 'retry_max_delay' => 20]);
```

Set `'max_retries' => 0` to turn retries off.

## Logging and hooks

Providers implement PSR-3 `LoggerAwareInterface`. Pass a logger as the `logger` option or call `setLogger()`.

| Level | Logged |
|-------|--------|
| debug | Each request |
| info | Each response, with status and duration |
| warning | Retries and calls blocked by a limit |
| error | Failures |

Only the provider, method, URL, status and timing are logged, never headers or bodies, so API keys and prompts stay out of your logs.

Hooks run around every request:

```php
$ai->getProvider()
    ->addHook('before_request', fn (array $request) => $metrics->increment('ai.requests'))
    ->addHook('after_response', fn (array $request, $response, float $seconds) => $metrics->timing('ai.latency', $seconds))
    ->addHook('on_retry', fn (array $request, float $delay, $reason) => null)
    ->addHook('on_error', fn (array $request, \Throwable $e) => $alerts->notify($e));
```

`$request` contains `provider`, `method`, `url`, `stream` and `attempt`. Hooks can also be passed as the `hooks` option, keyed by event name.

## Embeddings

`getEmbeddings()` returns one float vector per input, and `getEmbedding()` returns the first. OpenAI's base64 format (`encoding_format => 'base64'`) is decoded for you.

```php
$vectors = $openai->createEmbeddings(['first text', 'second text'], 'text-embedding-3-small')->getEmbeddings();
$vector  = $ollama->createEmbeddings('one text', 'nomic-embed-text')->getEmbedding();
```

## Custom providers and dependency injection

`AIFactory` is a regular service: inject an `HttpFactory` and register extra providers (any class extending `AbstractProvider`), or pass a provider class name to `getAI()`.

```php
use Joomla\AI\AIFactory;
use Joomla\Http\HttpFactory;

$factory = new AIFactory(new HttpFactory());
$factory->registerProvider('mistral', MistralProvider::class);

$mistral = $factory->getAI('mistral', ['api_key' => $key]);
$custom  = $factory->getAI(MyProvider::class, []);
```
