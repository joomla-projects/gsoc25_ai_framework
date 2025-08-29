# OpenAI Provider

The OpenAI Provider ([`OpenAIProvider`](../../src/Provider/OpenAIProvider.php)) offers the most comprehensive feature set in the framework, supporting chat, vision, image generation, audio processing, embeddings, and content moderation.

## Capabilities Overview

| Feature | Description |
|---------|-------------|
| **Chat** | Standard text conversation with GPT models |
| **Vision** | Image analysis and description using vision-enabled models |
| **Audio Chat** | Experimental audio responses with gpt-4o-audio-preview |
| **Image Generation** | Text-to-image generation with DALL-E 2, DALL-E 3, and GPT-Image-1 models |
| **Image Editing** | Edit existing images with masks and prompts |
| **Image Variations** | Create variations of existing images (DALL-E 2 only) |
| **Text-to-Speech** | Convert text to speech with multiple voices and audio formats |
| **Transcription** | Convert audio to text using Whisper and GPT transcription models |
| **Translation** | Translate audio from any language to English |
| **Embeddings** | Generate vector embeddings for text similarity and search |
| **Moderation** | Content safety checking and flagging inappropriate content |

## Configuration

```php
use Joomla\AI\AIFactory;

$openai = AIFactory::getAI('openai', [
    'api_key' => getenv('OPENAI_API_KEY'),
    'base_url' => 'https://api.openai.com/v1', // Optional: custom endpoint
    'model' => 'gpt-4o-mini' // Optional: default model
]);
```

## Chat Completions

### Basic Chat
```php
$response = $openai->chat("Explain Joomla CMS in one paragraph.");
echo $response->getContent();
```

### Advanced Chat Options
```php
$response = $openai->chat("Write a technical article outline", [
    'model' => 'gpt-4o',
    'max_tokens' => 1000,
    'temperature' => 0.7,
    'n' => 2 // Generate 2 response choices
]);

$metadata = $response->getMetadata();
echo "Used model: " . $metadata['model'] . "\n";
echo "Tokens used: " . $metadata['usage']['total_tokens'] . "\n";
```

### Message History
```php
$response = $openai->chat("", [
    'messages' => [
        ['role' => 'system', 'content' => 'You are a Joomla expert.'],
        ['role' => 'user', 'content' => 'How do I create a custom component?'],
        ['role' => 'assistant', 'content' => 'To create a custom component...'],
        ['role' => 'user', 'content' => 'What about adding database tables?']
    ]
]);
```

### Audio Chat (Experimental)
```php
$response = $openai->chat("Say hello in a friendly voice", [
    'model' => 'gpt-4o-audio-preview',
    'modalities' => ['text', 'audio'],
    'audio' => [
        'voice' => 'alloy',
        'format' => 'wav'
    ]
]);

// Save audio
$metadata = $response->getMetadata();
if (isset($metadata['choices'][0]['message']['audio']['data'])) {
    file_put_contents('hello.wav', base64_decode($metadata['choices'][0]['message']['audio']['data']));
}
```

## Vision

Analyze images with text prompts:

```php
// URL image
$response = $openai->vision(
    "Describe this website screenshot in detail",
    "https://example.com/screenshot.jpg"
);

// Base64 image
$imageData = base64_encode(file_get_contents('local-image.jpg'));
$response = $openai->vision(
    "What coding framework is shown in this image?",
    "data:image/jpeg;base64," . $imageData
);

echo $response->getContent();
```

## Image Generation

### DALL-E 2
```php
$image = $openai->generateImage("A futuristic Joomla logo in space", [
    'model' => 'dall-e-2',
    'size' => '1024x1024',
    'n' => 4, // Generate 4 variations
    'response_format' => 'b64_json'
]);

$image->saveFile('joomla-space-logo.png');
```

### DALL-E 3
```php
$image = $openai->generateImage("Minimalist Joomla CMS dashboard design", [
    'model' => 'dall-e-3',
    'quality' => 'hd',
    'style' => 'vivid', // or 'natural'
    'response_format' => 'url'
]);

echo "Image URL: " . $image->getContent();
```

### GPT-Image-1
```php
$image = $openai->generateImage("Professional web development workspace", [
    'model' => 'gpt-image-1',
    'background' => 'transparent',
    'output_format' => 'png',
    'output_compression' => 80
]);

$image->saveFile('workspace.png');
```

## Image Editing

### Edit with Mask (DALL-E 2)
```php
$edited = $openai->editImage(
    'original-logo.png',
    "Change the background to a gradient blue",
    [
        'model' => 'dall-e-2',
        'mask' => 'logo-mask.png',
        'size' => '1024x1024',
        'n' => 2
    ]
);

$edited->saveFile('logo-blue-bg.png');
```

### Multi-Image Edit (GPT-Image-1)
```php
$edited = $openai->editImage(
    ['image1.png', 'image2.png', 'image3.png'],
    "Combine these into a cohesive collage",
    [
        'model' => 'gpt-image-1',
        'background' => 'auto',
        'quality' => 'high'
    ]
);

$edited->saveFile('collage.png');
```

## Image Variations

Create variations of existing images (DALL-E 2 only):

```php
$variations = $openai->createImageVariation('base-design.png', [
    'model' => 'dall-e-2',
    'n' => 3,
    'size' => '512x512',
    'response_format' => 'b64_json'
]);

$variations->saveFile('design-variation.png');
```

## Text-to-Speech

### Basic TTS
```php
$audio = $openai->speech("Welcome to our Joomla website!", [
    'model' => 'tts-1',
    'voice' => 'nova',
    'response_format' => 'mp3'
]);

$audio->saveFile('welcome.mp3');
```

### High-Quality TTS
```php
$audio = $openai->speech("Professional announcement text", [
    'model' => 'tts-1-hd',
    'response_format' => 'wav',
    'speed' => 1.25 // 0.25 to 4.0
]);

$audio->saveFile('announcement.wav');
```

### Advanced TTS with Instructions
```php
$audio = $openai->speech("Say this with enthusiasm!", [
    'model' => 'gpt-4o-mini-tts',
    'voice' => 'alloy',
    'response_format' => 'mp3',
    'instructions' => 'Speak with high energy and excitement',
    'speed' => 1.1
]);

$audio->saveFile('enthusiastic.mp3');
```

## Audio Transcription

### Basic Transcription
```php
$transcript = $openai->transcribe('meeting-recording.wav', [
    'model' => 'whisper-1',
    'response_format' => 'json',
    'language' => 'en'
]);

echo "Transcript: " . $transcript->getContent();
```

### Subtitle Generation
```php
$subtitles = $openai->transcribe('video-audio.wav', [
    'model' => 'whisper-1',
    'response_format' => 'srt' // or 'vtt'
]);

$subtitles->saveFile('video-subtitles.srt');
```

## Audio Translation

Translate audio to English:

```php
$translation = $openai->translate('spanish-audio.mp3', [
    'model' => 'whisper-1',
    'response_format' => 'json',
]);

echo "English translation: " . $translation->getContent();
```

## Embeddings

### Basic Embeddings
```php
$embeddings = $openai->createEmbeddings(
    "Joomla is a content management system",
    'text-embedding-3-small'
);

$vectors = json_decode($embeddings->getContent());
echo "Vector dimensions: " . count($vectors) . "\n";
```

## Content Moderation

### Basic Moderation
```php
$result = $openai->moderate("Some text to check for safety");

if ($openai->isContentFlagged($result)) {
    echo "Content was flagged\n";
    print_r($result['results'][0]['categories']);
} else {
    echo "Content passed moderation\n";
}
```

## Model Management

### List Available Models
```php
$allModels = $openai->getAvailableModels();
echo "Available models: " . implode(', ', $allModels) . "\n";
```

## Default Model Management

Set defaults to avoid repeating model names:

```php
// Set defaults for different capabilities
$openai->setDefaultModel('gpt-4o-mini');

// All these use the default
$chat = $openai->chat("Hello");
$vision = $openai->vision("Describe this", $imageUrl);

// Override for specific calls
$premium = $openai->chat("Complex analysis", ['model' => 'gpt-4o']);

// Clear defaults
$openai->unsetDefaultModel();
```

## Error Handling

OpenAI-specific error patterns:

```php
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\QuotaExceededException;

try {
    $response = $openai->generateImage("A simple logo", [
        'model' => 'dall-e-3',
        'style' => 'invalid-style' // This will throw InvalidArgumentException
    ]);
} catch (InvalidArgumentException $e) {
    echo "Parameter error: " . $e->getMessage();
} catch (QuotaExceededException $e) {
    echo "Quota exceeded - check your billing";
}
```

## Important Constraints

### Model-Specific Limitations

**DALL-E 2:**
- Image variations: requires square PNG, max 4MB
- Sizes: 256x256, 512x512, 1024x1024 only
- Max 10 images per request

**DALL-E 3:**
- Only 1 image per request (`n=1`)
- Sizes: 1024x1024, 1792x1024, 1024x1792 only
- Style parameter: `vivid` or `natural`

**GPT-Image-1:**
- Always returns base64 (no `response_format` parameter)
- Supports multiple input images for editing
- Max 16 images for editing
- Advanced parameters: background, output_format, compression

**TTS Models:**
- Text limit: 4096 characters
- `instructions` parameter: only `gpt-4o-mini-tts`
- Speed range: 0.25 to 4.0

**Transcription:**
- File size limit: 25MB
- Formats: flac, mp3, mp4, mpeg, mpga, m4a, ogg, wav, webm
- `gpt-4o-transcribe`/`gpt-4o-mini-transcribe`: only JSON response format

**Embeddings:**
- `dimensions` parameter: only text-embedding-3-large (max 3072) and text-embedding-3-small (max 1536)

### Content Safety

The framework automatically applies moderation to:
- Chat messages (before sending)
- Image generation prompts
- TTS text input
- Image editing prompts

Content flagged by moderation will throw an exception before the API call.

## Best Practices

1. **Use appropriate models**: Choose the right model for your use case (cost vs capability)
2. **Handle rate limits**: Implement exponential backoff for production use

## Next Steps

- **[Anthropic Provider](anthropic.md)**
- **[Ollama Provider](ollama.md)**
