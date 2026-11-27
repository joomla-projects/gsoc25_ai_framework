# Ollama Provider

The Ollama Provider ([`OllamaProvider`](../../src/Provider/OllamaProvider.php)) runs models on your own machine or server: no API costs, and your data stays local.

## Capabilities Overview

| Feature | Description |
|---------|-------------|
| **Chat** | Text conversation with locally hosted models |
| **Generate** | Text generation and completion |
| **Vision** | Image questions with multimodal models (e.g. `llava`, `llama3.2-vision`) |
| **Embeddings** | Vector embeddings with embedding models (e.g. `nomic-embed-text`) |
| **Model Management** | Pull, copy, delete and list local models |

Missing models are pulled automatically before chat and generate. Streaming, tool calling, JSON schema output, conversations and limits are described in [Features](../features.md).

## Prerequisites

1. Download Ollama from https://ollama.com/
2. Install and start the Ollama service
3. Pull your first model:
```bash
ollama pull llama3
```

## Configuration

```php
use Joomla\AI\AIFactory;

$ollama = (new AIFactory())->getAI('ollama', [
    'base_url' => 'http://localhost:11434', // Optional: this is the default
    'model' => 'llama3' // Optional: default model
]);
```

No API key is needed.

## Chat

```php
$response = $ollama->chat("Explain what Joomla CMS is and its main features.");
echo $response->getContent();
```

### Model parameters

Ollama takes sampling and length settings in `options` (the same for `chat()` and `generate()`):

```php
$response = $ollama->chat("Write a PHP function for a Joomla component", [
    'model' => 'codellama:7b',
    'options' => [
        'temperature' => 0.1, // more focused, good for code
        'top_p' => 0.9,
        'num_predict' => 500, // maximum tokens to generate
        'seed' => 42,         // reproducible results
    ],
    'keep_alive' => '10m', // keep the model loaded between calls
]);
```

See the [Ollama parameter list](https://github.com/ollama/ollama/blob/main/docs/modelfile.md#valid-parameters-and-values) for all options.

## Text Generation

```php
$response = $ollama->generate("Complete this Joomla tutorial: To create a custom module, first you need to", [
    'model' => 'llama3',
    'system' => 'You write short, practical tutorials.',
    'options' => ['num_predict' => 300],
]);

echo $response->getContent();
```

## Vision

Images are sent as base64. Ollama cannot fetch URLs or read file paths, so encode the file yourself:

```php
$image = base64_encode(file_get_contents('screenshot.png'));

$response = $ollama->vision("What is shown in this screenshot?", $image, ['model' => 'llava']);
echo $response->getContent();
```

A `data:image/png;base64,...` URI works too.

## Embeddings

```php
$vectors = $ollama->createEmbeddings(['first text', 'second text'], 'nomic-embed-text')->getEmbeddings();

// Local models that can create embeddings
print_r($ollama->getEmbeddingModels());
```

## Model Management

```php
// Local and currently loaded models
echo implode(', ', $ollama->getAvailableModels());
echo implode(', ', $ollama->getRunningModels());

// "llama3" and "llama3:latest" are the same model
if (!$ollama->hasModel('mistral:7b')) {
    $ollama->pullModel('mistral:7b');
}

$ollama->copyModel('llama3', 'llama3-joomla-tuned');
$ollama->deleteModel('llama3-joomla-tuned');
```

## Error Handling

```php
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;

try {
    $response = $ollama->chat("Hello", ['model' => 'nonexistent-model']);
} catch (AuthenticationException $e) {
    echo "Ollama server is not running. Start it with: ollama serve\n";
} catch (InvalidArgumentException $e) {
    echo "Model not found in the Ollama library: " . $e->getMessage() . "\n";
} catch (ProviderException $e) {
    echo "Ollama error: " . $e->getMessage() . "\n";
}
```

To check that the server is reachable without making a request through a provider instance, use `OllamaProvider::isSupported('http://localhost:11434')`.

## Next Steps

- **[OpenAI Provider](openai.md)**
- **[Anthropic Provider](anthropic.md)**
