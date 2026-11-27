# Groq Provider

The Groq Provider ([`GroqProvider`](../../src/Provider/GroqProvider.php)) gives access to open-source models such as Llama, Qwen and gpt-oss, hosted on Groq's fast inference platform through its OpenAI-compatible API.

Groq is a cloud service: prompts are sent to Groq's servers and handled under [Groq's data policies](https://console.groq.com/docs/your-data). If data must not leave your own server at all, run open models locally with the [Ollama provider](ollama.md).

## Capabilities Overview

| Feature | Description |
|---------|-------------|
| **Chat** | Text conversation with open-source models |
| **Speech to Text** | Transcription and translation to English with Whisper |
| **Model Management** | List the models available to your API key |

Streaming, tool calling, JSON schema output, conversations and limits work as described in [Features](../features.md). Groq has no embeddings, image generation or text-to-speech models in production.

## Configuration

```php
use Joomla\AI\AIFactory;

$groq = (new AIFactory())->getAI('groq', [
    'api_key' => getenv('GROQ_API_KEY'), // Required: the key is only read from this option
    'model' => 'openai/gpt-oss-120b', // Optional: default chat model (this is the built-in default)
]);
```

Get an API key in the [Groq console](https://console.groq.com/keys). Current models are listed in the [Groq docs](https://console.groq.com/docs/models); popular ones include `openai/gpt-oss-120b`, `openai/gpt-oss-20b`, `llama-3.3-70b-versatile` and `llama-3.1-8b-instant`.

## Chat

```php
$response = $groq->chat("Explain Joomla's component structure in three sentences.", [
    'model' => 'llama-3.3-70b-versatile',
    'system' => 'You are a senior Joomla developer.',
    'max_tokens' => 500, // sent as max_completion_tokens
]);

echo $response->getContent();
echo $response->getUsage()['total_tokens'];
```

Also accepted: `temperature`, `top_p`, `stop`, `seed` and `reasoning_effort` (for reasoning models, e.g. `low`, `medium` or `high` for gpt-oss).

## Groq limitations

These options are rejected with an `InvalidArgumentException` before anything is sent, because Groq does not support them:

- `n` other than 1
- `response_schema` together with `tools`
- `response_schema` together with streaming

JSON schema output is enforced strictly only by some models (`openai/gpt-oss-120b`, `openai/gpt-oss-20b`, `qwen/qwen3.8-27b`). In strict mode every property must be listed in `required` and every object must set `'additionalProperties' => false`. Pass `'strict' => false` in `response_schema` for best-effort output. `isModelCapable($model, 'json_schema')` checks the built-in list.

## Vision

Groq has no vision model in production at the moment, so `vision()` needs a vision-capable model, either as the `model` option or as the `vision_model` provider option:

```php
$response = $groq->vision("Describe this image.", "https://example.com/photo.jpg", ['model' => 'a-vision-model']);
```

## Speech to Text

```php
$text = $groq->transcribe('meeting.mp3', [
    'language' => 'en', // optional ISO-639-1 code, improves accuracy and speed
    'response_format' => 'verbose_json', // json (default), verbose_json or text
    'timestamp_granularities' => ['segment', 'word'], // needs verbose_json
]);

echo $text->getContent();
print_r($text->getMetadata()['segments']);

$english = $groq->translate('interview-de.mp3'); // translates the speech into English text
```

- Supported files: flac, mp3, mp4, mpeg, mpga, m4a, ogg, wav and webm. Uploads are limited to 25 MB.
- Transcription uses `whisper-large-v3-turbo` by default; set the `transcription_model` option or pass `model` to change it.
- Translation uses `whisper-large-v3`, the Whisper model that supports translation.
- `srt` and `vtt` output are not available on Groq.

## Models

```php
print_r($groq->getAvailableModels()); // active models your key can use

$groq->isModelCapable('openai/gpt-oss-20b', 'json_schema'); // true
```

`isModelCapable()`, `getChatModels()` and `getTranscriptionModels()` use a built-in list of current models. Requests are not blocked for models missing from it. To change the list, pass a `capabilities` option, for example `['models' => ['vision' => ['a-vision-model']]]`.

## Next Steps

- **[OpenAI Provider](openai.md)**
- **[Anthropic Provider](anthropic.md)**
- **[Ollama Provider](ollama.md)**
