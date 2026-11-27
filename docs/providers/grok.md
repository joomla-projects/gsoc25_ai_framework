# Grok Provider

The Grok Provider ([`GrokProvider`](../../src/Provider/GrokProvider.php)) connects to xAI's Grok models through xAI's OpenAI-compatible Chat Completions API.

## Capabilities Overview

| Feature | Description |
|---------|-------------|
| **Chat** | Text conversation with Grok models |
| **Vision** | Questions about JPEG or PNG images |
| **Image Generation** | Images from a text prompt with `grok-imagine-image` models |
| **Model Management** | List the models available to your API key |

Streaming, tool calling, JSON schema output, conversations and limits work as described in [Features](../features.md). xAI has no image editing, image variation or embeddings models; `editImage()` and `createImageVariation()` throw a `ProviderException`.

## Configuration

```php
use Joomla\AI\AIFactory;

$grok = (new AIFactory())->getAI('grok', [
    'api_key' => getenv('XAI_API_KEY'), // Required: the key is only read from this option
    'model' => 'grok-4.7', // Optional: default chat model (this is the built-in default)
    'image_model' => 'grok-imagine-image-2.0', // Optional: default image model (this is the built-in default)
    'base_url' => 'https://api.x.ai/v1', // Optional
    'timeout' => 120, // Optional: reasoning models can take a while to answer
]);
```

Get an API key from the [xAI console](https://console.x.ai/). Current models and prices are listed in the [xAI docs](https://docs.x.ai/docs/models).

## Chat

```php
$response = $grok->chat("Explain the Joomla MVC structure in three sentences.", [
    'system' => 'You are a senior Joomla developer.',
    'max_tokens' => 500,
]);

echo $response->getContent();
echo $response->getUsage()['total_tokens'];
```

Also accepted: `temperature`, `top_p`, `stop`, `seed`, `n`, `frequency_penalty` and `presence_penalty`. Reasoning models may reject some sampling and penalty options with an error.

Pass a `messages` history yourself, or use a [conversation](../features.md#conversations) to keep it for you.

## Vision

The image can be an `http(s)` URL or a base64 data URI. Only JPEG and PNG are accepted, up to 20 MiB.

```php
$response = $grok->vision("What is shown in this screenshot?", "https://example.com/screenshot.png", [
    'detail' => 'high', // optional: auto, low or high
]);

$image = base64_encode(file_get_contents('photo.jpg'));
$response = $grok->vision("Describe this photo.", "data:image/jpeg;base64," . $image);
```

## Image Generation

```php
$images = $grok->generateImage("A Joomla logo made of clouds", [
    'n' => 2, // 1 to 10
    'response_format' => 'b64_json', // or 'url'
]);

$images->saveFile('clouds.jpg'); // saves clouds_1.jpg and clouds_2.jpg
```

Image generation uses the `image_model` option (default `grok-imagine-image-2.0`), not the chat model, unless you pass `model`. Images are priced per image, not per token.

## Models

```php
print_r($grok->getAvailableModels()); // the models your key can use

$grok->isModelCapable('grok-4.7', 'vision'); // true
```

`isModelCapable()`, `getChatModels()` and `getImageModels()` use a built-in list of current models. Requests are not blocked for models missing from it. To change the list, pass a `capabilities` option, for example `['models' => ['chat' => ['grok-5']]]`.

## Next Steps

- **[OpenAI Provider](openai.md)**
- **[Anthropic Provider](anthropic.md)**
- **[Ollama Provider](ollama.md)**
