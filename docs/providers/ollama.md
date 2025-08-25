# Ollama Provider

The Ollama Provider ([`OllamaProvider`](../../src/Provider/OllamaProvider.php)) enables local AI inference without API costs, offering privacy-focused processing and offline capabilities.

## Capabilities Overview

| Feature | Description |
|---------|-------------|
| **Chat** | Text conversation with locally-hosted models |
| **Generate** | Text generation and completion tasks |
| **Model Management** | Pull, copy, delete, and list local models |
| **Model Availability** | Automatic model pulling and status checking |

## Configuration

```php
use Joomla\AI\Provider\OllamaProvider;

$ollama = new OllamaProvider([
    'base_url' => 'http://localhost:11434', // Default Ollama server
    'model' => 'llama3' // Optional: default model
]);
```

## Prerequisites

### Install Ollama
1. Download from https://ollama.ai/
2. Install and start the Ollama service
3. Pull your first model:
```bash
ollama pull llama3
```

## Chat Completions

### Basic Chat
```php
$response = $ollama->chat("Explain what Joomla CMS is and its main features.");
echo $response->getContent();
```

### Chat with Model Selection
```php
$response = $ollama->chat("Write a PHP function for Joomla component development", [
    'model' => 'codellama:7b',
    'temperature' => 0.1 // More focused for code generation
]);

echo $response->getContent();
```

## Text Generation

### Basic Generation
```php
$response = $ollama->generate("Complete this Joomla tutorial: To create a custom module, first you need to", [
    'model' => 'llama3'
]);

echo $response->getContent();
```

### Generation with Parameters
```php
$response = $ollama->generate("Write a Joomla plugin that", [
    'model' => 'codellama:13b',
    'max_tokens' => 500,
    'temperature' => 0.3,
    'top_p' => 0.9,
]);

echo $response->getContent();
```

## Model Management

### List Available Models (Local)
```php
$models = $ollama->getAvailableModels();
echo "Available Models: " . implode(', ', $models) . "\n";
```

### List Running Models
```php
$running = $ollama->getRunningModels();
echo $running;
```

### Pull a Model
```php
// Pull model if not already available
$ollama->pullModel('mistral:7b');
echo "Mistral 7B model is now available\n";
```

### Copy a Model
```php
// Create a copy with a custom name
$ollama->copyModel('llama3', 'llama3-joomla-tuned');
echo "Model copied for Joomla-specific use\n";
```

### Delete a Model
```php
$ollama->deleteModel('old-model:tag');
echo "Model removed to free up space\n";
```

### Check Model Availability
```php
if ($ollama->checkModelExists('llama3')) {
    echo "Llama3 is available locally\n";
} else {
    echo "Pulling Llama3...\n";
    $ollama->pullModel('llama3');
}
```

## Default Model Management

```php
// Set a default model for all operations
$ollama->setDefaultModel('llama3');

// These will use the default
$chat = $ollama->chat("Hello!");
$generate = $ollama->generate("Complete this: ");

// Override for specific calls
$code = $ollama->generate("Write PHP code:", ['model' => 'codellama:7b']);

// Clear the default
$ollama->unsetDefaultModel();
```

### Custom Parameters
```php
$response = $ollama->generate("Explain Joomla architecture", [
    'model' => 'llama3',
    'temperature' => 0.7,
    'top_k' => 40,
    'top_p' => 0.9,
    'repeat_penalty' => 1.1,
    'seed' => 42, // For reproducible results
    'num_predict' => 100
]);
```

## Error Handling

```php
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\InvalidArgumentException;

try {
    $response = $ollama->chat("Hello", ['model' => 'nonexistent-model']);
} catch (ProviderException $e) {
    echo "Model not available. \n";
} catch (InvalidArgumentException $e) {
    echo "Parameter error: " . $e->getMessage();
}
```

## Troubleshooting

### Connection Issues
```php
// Test Ollama server connectivity
try {
    $models = $ollama->getAvailableModels();
    echo "Ollama server is responding\n";
} catch (Exception $e) {
    echo "Ollama server not accessible. Check if it's running on localhost:11434\n";
}
```

### Model Issues
```php
// Verify model availability before use
$modelName = 'llama3';
if (!$ollama->checkModelExists($modelName)) {
    echo "Model $modelName not found. Available models:\n";
}
```

## Next Steps

- **[OpenAI Provider](openai.md)**
- **[Anthropic Provider](anthropic.md)**
