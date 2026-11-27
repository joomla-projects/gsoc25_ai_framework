# Gemini Provider

The Gemini Provider ([`GeminiProvider`](../../src/Provider/GeminiProvider.php)) connects to Google's Gemini models through the [Interactions API](https://ai.google.dev/gemini-api/docs/interactions-overview), Google's recommended interface for Gemini.

## Capabilities Overview

| Feature | Description |
|---------|-------------|
| **Chat** | Text conversation, with optional thinking control |
| **Vision** | Questions about images (PNG, JPEG, WebP, HEIC, HEIF) |
| **Image Generation** | Images from a text prompt with Gemini image models ("Nano Banana") |
| **Image Editing** | Change up to 14 input images with a text instruction |
| **Embeddings** | Vector embeddings with `gemini-embedding-2` |
| **Model Management** | List the models available to your API key |

Streaming, tool calling, JSON schema output, conversations and limits work as described in [Features](../features.md). Gemini has no image variations; `createImageVariation()` throws a `ProviderException` (use `editImage()` with an instruction instead).

## Configuration

```php
use Joomla\AI\AIFactory;

$gemini = (new AIFactory())->getAI('gemini', [
    'api_key' => getenv('GEMINI_API_KEY'), // Required: the key is only read from this option
    'model' => 'gemini-3.8-flash', // Optional: default chat model (this is the built-in default)
    'image_model' => 'gemini-nano-banana-2.1', // Optional: default image model (this is the built-in default)
]);
```

Get an API key in [Google AI Studio](https://aistudio.google.com/apikey). Current models are listed in the [Gemini docs](https://ai.google.dev/gemini-api/docs/models).

Requests are sent with `store` set to `false`, so Google does not keep the interaction and the full history is sent with every message, as with the other providers. Pass `'store' => true` in the options to let Google store it.

## Chat

```php
$response = $gemini->chat("Explain Joomla's plugin events in three sentences.", [
    'system' => 'You are a senior Joomla developer.',
    'max_tokens' => 800,
    'thinking_level' => 'low', // minimal, low, medium or high
]);

echo $response->getContent();
echo $response->getUsage()['total_tokens'];
```

Also accepted: `temperature`, `top_p`, `stop` and `seed`. Thinking tokens are included in `total_tokens`; Gemini's own breakdown, including `total_thought_tokens`, is in `getMetadata()['usage']`.

When you pass a `messages` history yourself, send earlier replies back with `buildAssistantMessage($response)`. It keeps Gemini's thought signatures, which Gemini needs when a conversation continues. A [conversation](../features.md#conversations) does this for you.

## Vision

The image can be a base64 data URI or an `http(s)` URL that Google can fetch.

```php
$image = base64_encode(file_get_contents('screenshot.png'));

$response = $gemini->vision("What is shown in this screenshot?", "data:image/png;base64," . $image, [
    'resolution' => 'high', // optional: low, medium, high or ultra_high
]);
```

## Image Generation and Editing

```php
$image = $gemini->generateImage("A Joomla logo made of clouds", [
    'aspect_ratio' => '16:9', // optional, e.g. 1:1, 3:2, 16:9
    'image_size' => '2K',     // optional: 512, 1K, 2K or 4K
]);

$image->saveFile('clouds.jpg');

$edited = $gemini->editImage(['photo.png', 'logo.png'], "Put the logo in the top right corner of the photo");
$edited->saveFile('photo-with-logo.jpg');
```

Image generation and editing use the `image_model` option (default `gemini-nano-banana-2.1`), not the chat model, unless you pass `model`. If the model answers with text instead of an image (for example when it declines a prompt), a `ProviderException` with that text is thrown.

## Embeddings

```php
$vectors = $gemini->createEmbeddings(['first text', 'second text'], 'gemini-embedding-2', [
    'dimensions' => 768, // optional: 128 to 3072
])->getEmbeddings();
```

`task_type` is passed on for `gemini-embedding-001`; `gemini-embedding-2` takes task hints in the text instead.

## Rate Limits and Quota

Gemini returns 429 `RESOURCE_EXHAUSTED` both for short-term rate limits and for exhausted quota. When the error includes a retry delay, the call is [retried](../features.md#retries) after that delay and, if retries run out, throws `RateLimitException`. Without a retry delay it throws `QuotaExceededException` straight away.

## Models

```php
print_r($gemini->getAvailableModels()); // the models your key can use

$gemini->isModelCapable('gemini-3.8-flash', 'vision'); // true
```

`isModelCapable()` and `getEmbeddingModels()` use a built-in list of current models. Requests are not blocked for models missing from it. To change the list, pass a `capabilities` option, for example `['models' => ['chat' => ['gemini-4-argon']]]`.

## Next Steps

- **[OpenAI Provider](openai.md)**
- **[Anthropic Provider](anthropic.md)**
- **[Ollama Provider](ollama.md)**
