<?php

namespace Joomla\AI\Provider;

use Joomla\AI\AbstractProvider;
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Response\Response;
use Joomla\Http\HttpFactory;

/**
 * Ollama provider implementation.
 *
 * @since  __DEPLOY_VERSION__
 */
class OllamaProvider extends AbstractProvider
{
    /**
     * Custom base URL for API requests
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private $baseUrl;

    /**
     * Constructor.
     *
     * @param   array|\ArrayAccess  $options     Provider options array.
     * @param   HttpFactory         $httpFactory The http factory
     * 
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(array $options = [], ?HttpFactory $httpFactory = null)
    {
        parent::__construct($options, $httpFactory);
        
        $this->baseUrl = $this->getOption('base_url', 'http://localhost:11434');
        
        // Remove trailing slash if present
        if (substr($this->baseUrl, -1) === '/') {
            $this->baseUrl = rtrim($this->baseUrl, '/');
        }
    }

    /**
     * Check if Ollama provider is supported/configured.
     *
     * @return  boolean  True if Ollama server is accessible
     * @since  __DEPLOY_VERSION__
     */
    public static function isSupported(): bool
    {
        // We'll implement a check to see if Ollama server is running
        try {
            $response = file_get_contents('http://localhost:11434/api/tags');
            return $response !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ensure server is running
     * 
     * @throws  AuthenticationException  If the server is not running
     * @since  __DEPLOY_VERSION__
     */
    private function validateConnection(): void
    {
        try {
            $this->makeGetRequest($this->baseUrl . '/api/tags');
        } catch (\Exception $e) {
            throw new AuthenticationException(
                $this->getName(),
                ['message' => 'Ollama server not running. Please start with: ollama serve'],
                401
            );
        }
    }

    /**
     * Get the provider name.
     *
     * @return  string  The provider name
     * @since  __DEPLOY_VERSION__
     */
    public function getName(): string
    {
        return 'Ollama';
    }

    /**
    * Get the chat endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getChatEndpoint(): string
    {
        return $this->baseUrl . '/api/chat';
    }

    /**
     * Get the copy model endpoint URL.
     *
     * @return  string  The endpoint URL
     * @since  __DEPLOY_VERSION__
     */
    private function getCopyModelEndpoint(): string
    {
        return $this->baseUrl . '/api/copy';
    }

    /**
     * Get the delete model endpoint URL.
     *
     * @return  string  The endpoint URL
     * @since  __DEPLOY_VERSION__
     */
    private function getDeleteModelEndpoint(): string
    {
        return $this->baseUrl . '/api/delete';
    }

    /**
    * Get the pull model endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getPullEndpoint(): string
    {
        return $this->baseUrl . '/api/pull';
    }

    /**
    * Get the generate endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getGenerateEndpoint(): string
    {
        return $this->baseUrl . '/api/generate';
    }

    /**
     * Get all available models for this provider.
     *
     * @return  array  Array of available model names
     * @since   __DEPLOY_VERSION__
     */
    public function getAvailableModels(): array
    {
        $this->validateConnection();

        $response = $this->makeGetRequest($this->baseUrl . '/api/tags');
        $data = $this->parseJsonResponse($response->getBody());
        
        return array_column($data['models'], 'name');
    }

    /**
     * List models currently loaded into memory (running) and echo their names.
     *
     * @return array Array of running model info
     * @throws ProviderException If the request fails
     * @since __DEPLOY_VERSION__
     */
    public function getRunningModels()
    {
        $this->validateConnection();

        $endpoint = $this->baseUrl . '/api/ps';
        $response = $this->makeGetRequest($endpoint);
        $data = $this->parseJsonResponse($response->getBody());

        $models = $data['models'] ?? [];

        if (empty($models)) {
            echo "No models are currently loaded into memory.\n";
        } else {
            echo "Running models:\n";
            foreach ($models as $model) {
                echo "- " . ($model['name'] ?? '[unknown]') . "\n";
            }
        }
    }

    /**
     * Check if a model exists in the available models list, handling name variations
     *
     * @param   string  $modelName        Model name to check
     * @param   array   $availableModels  List of available models
     * @return  bool    True if model exists
     */
    private function checkModelExists(string $modelName, array $availableModels): bool
    {
        // To Do: Improve logic
        if (in_array($modelName, $availableModels)) {
            return true;
        }
        
        // Check with :latest suffix added
        if (!str_ends_with($modelName, ':latest') && in_array($modelName . ':latest', $availableModels)) {
            return true;
        }
        
        // Check with :latest suffix removed
        if (str_ends_with($modelName, ':latest')) {
            $baseModelName = str_replace(':latest', '', $modelName);
            if (in_array($baseModelName, $availableModels)) {
                return true;
            }
        }
                
        return false;
    }

    /**
     * Copy a model to a new name.
     *
     * @param string $sourceModel      The name of the source model to copy
     * @param string $destinationModel The new name for the copied model
     * 
     * @return bool                    True if copy was successful
     * @since __DEPLOY_VERSION__
     */
    public function copyModel(string $sourceModel, string $destinationModel): bool
    {
        $this->validateConnection();

        $endpoint = $this->getCopyModelEndpoint();
        $payload = [
            'source' => $sourceModel,
            'destination' => $destinationModel
        ];

        $jsonData = json_encode($payload);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Failed to encode copy request: ' . json_last_error_msg()]
            );
        }

        $httpResponse = $this->makePostRequest($endpoint, $jsonData);
        $status = $httpResponse->getStatusCode();

        if ($status === 200) {
            echo "Model '$sourceModel' copied to '$destinationModel' successfully.\n";
            return true;
        } elseif ($status === 404) {
            throw InvalidArgumentException::invalidModel(
                $sourceModel,
                $this->getName(),
                ['message' => "Source model '$sourceModel' does not exist."]
            );
        } else {
            throw new ProviderException(
                $this->getName(),
                ['message' => "Unexpected status code $status from copy API."]
            );
        }
    }

    /**
     * Delete a model and its data.
     *
     * @param string $modelName        The name of the model to delete
     * 
     * @return bool                    True if deletion was successful
     * @throws ProviderException       If the deletion fails or model does not exist
     * @since __DEPLOY_VERSION__
     */
    public function deleteModel(string $modelName): bool
    {
        $this->validateConnection();

        $endpoint = $this->getDeleteModelEndpoint();
        $payload = [
            'model' => $modelName
        ];

        $jsonData = json_encode($payload);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Failed to encode delete request: ' . json_last_error_msg()]
            );
        }

        $httpResponse = $this->makeDeleteRequest($endpoint, $jsonData);
        $status = $httpResponse->getStatusCode();

        if ($status === 200) {
            echo "Model '$modelName' deleted successfully.\n";
            return true;
        } elseif ($status === 404) {
            throw InvalidArgumentException::invalidModel(
                $modelName,
                $this->getName(),
                ['message' => "Model '$modelName' does not exist."]
            );
        } else {
            throw new ProviderException(
                $this->getName(),
                ['message' => "Unexpected status code $status from delete API."]
            );
        }
    }

    /**
     * Pull a model from Ollama library
     *
     * @param   string  $modelName  Name of the model to pull
     * @param   bool    $stream     Whether to stream the response (for progress updates)
     * @param   bool    $insecure   Allow insecure connections to the library
     * 
     * @return  bool    True if model was pulled successfully
     * @throws  InvalidArgumentException  If model doesn't exist in Ollama library
     * @throws  ProviderException        If pull fails for other reasons
     * @since   __DEPLOY_VERSION__
     */
    public function pullModel(string $modelName, bool $stream = true, bool $insecure = false)
    {
        $this->validateConnection();

        $availableModels = $this->getAvailableModels();
        if ($this->checkModelExists($modelName, $availableModels)) {
            echo "Model '$modelName' is already available locally.\n";
            return true;
        }

        $endpoint = $this->getPullEndpoint();
        
        $requestData = [
            'model' => $modelName
        ];
        if ($insecure) {
            $requestData['insecure'] = true;
        }
        if (!$stream) {
            $requestData['stream'] = false;
        }
        
        try {
            $jsonData = json_encode($requestData);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ProviderException(
                    $this->getName(),
                    ['message' => 'Failed to encode request data: ' . json_last_error_msg()]
                );
            }

            $response = $this->makePostRequest($endpoint, $jsonData);

            if (!$stream) {
                $data = $this->parseJsonResponse($response->getBody());
                return isset($data['status']) && $data['status'] === 'success';
            }
            
            $body = $response->getBody();
            $fullContent = (string) $body;
            
            $lines = explode("\n", $fullContent);
            $hasError = false;
            $errorMessage = '';
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $data = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }
    
                // Check for error in response
                if (isset($data['error'])) {
                    $errorMessage = $data['error'];
                    
                    // Check if this is a "model not found" type error
                    if (strpos(strtolower($errorMessage), 'file does not exist') !== false ||
                        strpos(strtolower($errorMessage), 'model') !== false && strpos(strtolower($errorMessage), 'not found') !== false ||
                        strpos(strtolower($errorMessage), 'manifest') !== false && strpos(strtolower($errorMessage), 'not found') !== false) {
                        
                        throw InvalidArgumentException::invalidModel(
                            $modelName,
                            $this->getName(),
                            []
                        );
                    }
                    
                    // For other errors, throw ProviderException
                    throw new ProviderException(
                        $this->getName(),
                        ['message' => $errorMessage]
                    );
                }
            }
            
            // Check if success status exists in the response
            if (strpos($fullContent, '"status":"success"') !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    $data = json_decode($line, true);
                    if (json_last_error() !== JSON_ERROR_NONE) continue;
                    
                    $status = $data['status'] ?? '';
                    
                    if (strpos($status, 'pulling') === 0 && isset($data['digest'])) {
                        $digest = $data['digest'];
                        $total = $data['total'] ?? 0;
                        $completed = $data['completed'] ?? 0;
                        if ($total > 0) {
                            $percentage = round(($completed / $total) * 100, 1);
                            echo "\rPulling $digest: $percentage%";
                        }
                    } elseif ($status === 'verifying sha256 digest') {
                        echo "\nVerifying sha256 digest...\n";
                    } elseif ($status === 'writing manifest') {
                        echo "Writing manifest...\n";
                    } elseif ($status === 'success') {
                        echo "\nModel $modelName successfully pulled!\n";
                    }
                }
                
                return true;
            } 
        }  catch (InvalidArgumentException $e) {
            throw $e;
        } catch (ProviderException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Failed to pull model: ' . $e->getMessage()]
            );
        }
    }

    /**
     * Ensure model is available, pulling it if necessary
     *
     * @param   string  $modelName  Name of the model to ensure
     * @return  bool    True if model is available
     * @throws  ProviderException   If model cannot be made available
     */
    private function ensureModelAvailable(string $modelName): bool
    {
        $availableModels = $this->getAvailableModels();

        $availableModels = $this->getAvailableModels();
        if (!$this->checkModelExists($modelName, $availableModels)) {
            echo "Model $modelName not found locally. Attempting to pull...\n";
            $this->pullModel($modelName, true, false);
        } elseif ($this->checkModelExists($modelName, $availableModels)) {
            echo "Model $modelName is already available locally.\n";
        }
        return true;
    }

    /**
     * Build the request payload for the chat endpoint
     *
     * @param   string  $message   The user message to send
     * @param   array   $options  Additional options
     * 
     * @return  array   The request payload
     * @throws  \InvalidArgumentException  If model does not support chat capability
     * @since  __DEPLOY_VERSION__
     */
    public function buildChatRequestPayload(string $message, array $options = [])
    {
        $this->validateConnection();

        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'tinyllama');
        $this->ensureModelAvailable($model);
        echo "Using model: $model\n";

        if (isset($options['messages'])) {
            $messages = $options['messages'];
            if (!is_array($messages) || empty($messages)) {
                throw InvalidArgumentException::invalidMessages('ollama', 'Messages must be a non-empty array.');
            }
        } else {
            $messages = [
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false
        ];
        
        if (isset($options['stream'])) {
            $payload['stream'] = (bool) $options['stream'];
        }

        return $payload;
    }

    /**
     * Send a chat message to the Ollama server
     *
     * @param   string  $message   The user message to send
     * @param   array   $options  Additional options
     * 
     * @return  Response  The AI response
     * @throws  ProviderException  If the request fails
     * @since   __DEPLOY_VERSION__
     */
    public function chat(string $message, array $options = []): Response
    {
        $payload = $this->buildChatRequestPayload($message, $options);
        
        $endpoint = $this->getChatEndpoint();
        
        $jsonData = json_encode($payload);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Failed to encode request data: ' . json_last_error_msg()]
            );
        }
        
        $httpResponse = $this->makePostRequest(
            $endpoint, 
            $jsonData,
        );
                    
        // Check if this is a streaming response
        if (isset($payload['stream']) && $payload['stream'] === true) {
            return $this->parseOllamaStreamingResponse($httpResponse->getBody(), true);
        }
        
        return $this->parseOllamaResponse($httpResponse->getBody(), true);
    }

    /**
     * Build the request payload for the generate endpoint
     *
     * @param   string  $prompt   The prompt to generate a response for
     * @param   array   $options  Additional options
     * @return  array   The formatted payload
     * @throws  InvalidArgumentException  If options are invalid
     * @since   __DEPLOY_VERSION__
     */
    public function buildGenerateRequestPayload(string $prompt, array $options = []): array
    {
        $this->validateConnection();

        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'tinyllama');
        $this->ensureModelAvailable($model);
        echo "Using model: $model\n";

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false
        ];
        
        // Handle optional parameters
        if (isset($options['stream'])) {
            $payload['stream'] = (bool) $options['stream'];
        }
        
        if (isset($options['suffix'])) {
            $payload['suffix'] = $options['suffix'];
        }
        
        if (isset($options['images']) && is_array($options['images'])) {
            $payload['images'] = $options['images'];
        }
        
        if (isset($options['format'])) {
            $payload['format'] = $options['format'];
        }
        
        if (isset($options['options']) && is_array($options['options'])) {
            $payload['options'] = $options['options'];
        }
        
        if (isset($options['system'])) {
            $payload['system'] = $options['system'];
        }
        
        if (isset($options['template'])) {
            $payload['template'] = $options['template'];
        }
        
        if (isset($options['context'])) {
            $payload['context'] = $options['context'];
        }
        
        if (isset($options['raw'])) {
            $payload['raw'] = (bool) $options['raw'];
        }
        
        if (isset($options['keep_alive'])) {
            $payload['keep_alive'] = $options['keep_alive'];
        }
        
        return $payload;
    }

    /**
     * Generate a completion for a given prompt
     *
     * @param   string    $prompt    The prompt to generate a response for
     * @param   array     $options   Additional options
     * @param   callable  $callback  Optional callback function for streaming responses
     * @return  Response  The AI response
     * @throws  ProviderException  If the request fails
     * @since   __DEPLOY_VERSION__
     */
    public function generate(string $prompt, array $options = []): Response
    {
        $payload = $this->buildGenerateRequestPayload($prompt, $options);
        
        $endpoint = $this->getGenerateEndpoint();
        
        $jsonData = json_encode($payload);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Failed to encode request data: ' . json_last_error_msg()]
            );
        }
        
        $httpResponse = $this->makePostRequest(
            $endpoint, 
            $jsonData,
        );
                
        // Check if this is a streaming response
        if (isset($payload['stream']) && $payload['stream'] === true) {
            return $this->parseOllamaStreamingResponse($httpResponse->getBody(), false);
        }
        
        return $this->parseOllamaResponse($httpResponse->getBody(), false);
    }

    /**
     * Parse a streaming response from Ollama API 
     *
     * @param   string  $responseBody  The raw response body
     * @param   bool    $isChat        Whether this is a chat response (true) or generate response (false)
     * 
     * @return  Response  The parsed response
     * @since   __DEPLOY_VERSION__
     */
    private function parseOllamaStreamingResponse(string $responseBody, bool $isChat = true): Response
    {
        $lines = explode("\n", $responseBody);
        $fullContent = '';
        $lastMetadata = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $data = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) continue;
            
            // Accumulate content from each chunk - handle both chat and generate formats
            if ($isChat && isset($data['message']['content'])) {
                $fullContent .= $data['message']['content'];
            } elseif (!$isChat && isset($data['response'])) {
                $fullContent .= $data['response'];
            }
            
            // Keep track of the last metadata
            if ($data['done'] === true) {
                $lastMetadata = [
                    'model' => $data['model'],
                    'created_at' => $data['created_at'],
                    'done_reason' => $data['done_reason'] ?? null,
                    'done' => $data['done'],
                    'total_duration' => $data['total_duration'],
                    'load_duration' => $data['load_duration'],
                    'prompt_eval_count' => $data['prompt_eval_count'],
                    'prompt_eval_duration' => $data['prompt_eval_duration'],
                    'eval_count' => $data['eval_count'],
                    'eval_duration' => $data['eval_duration']
                ];
                
                // Add chat-specific metadata
                if ($isChat && isset($data['message']['role'])) {
                    $lastMetadata['role'] = $data['message']['role'];
                }
                
                // Add generate-specific metadata
                if (!$isChat && isset($data['context'])) {
                    $lastMetadata['context'] = $data['context'];
                }
            }
        }
        
        $statusCode = isset($lastMetadata['done_reason']) ? $this->determineAIStatusCode($lastMetadata) : 200;

        return new Response(
            $fullContent,
            $this->getName(),
            $lastMetadata,
            $statusCode
        );
    }

    /**
     * Parse a non-streaming response from Ollama API (works for both chat and generate endpoints)
     *
     * @param   string  $responseBody  The raw response body
     * @param   bool    $isChat        Whether this is a chat response (true) or generate response (false)
     * 
     * @return  Response  The parsed response
     * @throws  ProviderException  If the response contains an error
     * @since   __DEPLOY_VERSION__
     */
    private function parseOllamaResponse(string $responseBody, bool $isChat = true): Response
    {
        $data = $this->parseJsonResponse($responseBody);
        
        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        // Extract content based on whether it's a chat or generate response
        $content = $isChat ? ($data['message']['content'] ?? '') : ($data['response'] ?? '');
        
        $statusCode = isset($data['done_reason']) ? $this->determineAIStatusCode($data) : 200;

        // Build common metadata
        $metadata = [
            'model' => $data['model'],
            'created_at' => $data['created_at'] ?? $data['created'] ?? time(),
            'done_reason' => $data['done_reason'] ?? null,
            'done' => $data['done'] ?? true,
            'total_duration' => $data['total_duration'] ?? 0,
            'load_duration' => $data['load_duration'] ?? 0,
            'prompt_eval_count' => $data['prompt_eval_count'] ?? 0,
            'prompt_eval_duration' => $data['prompt_eval_duration'] ?? 0,
            'eval_count' => $data['eval_count'] ?? 0,
            'eval_duration' => $data['eval_duration'] ?? 0
        ];
        
        // Add chat-specific metadata
        if ($isChat && isset($data['message']['role'])) {
            $metadata['role'] = $data['message']['role'];
        }
        
        // Add generate-specific metadata
        if (!$isChat && isset($data['context'])) {
            $metadata['context'] = $data['context'];
        }

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            $statusCode
        );
    }

    private function determineAIStatusCode(array $data): int
    {
        $finishReason = $data['done_reason'];
        
        switch ($finishReason) {
            case 'stop':
                return 200;
                
            case 'length':
                return 206;
                
            case 'content_filter':
                return 422;
                
            case 'tool_calls':
            case 'function_call':
                return 202;
                
            default:
                return 200;
        }
    }
}
