<?php

namespace Joomla\AI\Provider;

use Joomla\AI\AbstractProvider;
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Interface\ChatInterface;
use Joomla\AI\Interface\ImageInterface;
use Joomla\AI\Interface\ModelInterface;
use Joomla\AI\Interface\EmbeddingInterface;
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
     * No API key needed for Ollama, but we need to ensure server is running
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
    public function pullModel(string $modelName, bool $stream = true, bool $insecure = false): bool
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
            
            // If no success and no explicit error
            // throw new ProviderException($modelName, $this->getName());
            
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
     * Check if a model exists in the available models list, handling name variations
     *
     * @param   string  $modelName        Model name to check
     * @param   array   $availableModels  List of available models
     * @return  bool    True if model exists
     */
    private function checkModelExists(string $modelName, array $availableModels): bool
    {
        // To Do: Improve logic
        // Direct match
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
     * Ensure model is available, pulling it if necessary
     *
     * @param   string  $modelName  Name of the model to ensure
     * @return  bool    True if model is available
     * @throws  ProviderException   If model cannot be made available
     */
    private function ensureModelAvailable(string $modelName): bool
    {
        $availableModels = $this->getAvailableModels();
        
        if (!in_array($modelName, $availableModels)) {
            echo "Model $modelName not found locally. Attempting to pull...\n";
            return $this->pullModel($modelName, true, false);
        }
        
        return true;
    }

    public function buildChatRequestPayload(string $message, array $options = [])
    {
        $this->validateConnection();

        $availableModels = $this->getAvailableModels();
        
        // Handle model selection and installation
        if (isset($options['model'])) {
            $model = $options['model'];
            if (!$this->checkModelExists($model, $availableModels)) {
                echo "Model '$model' not found locally. Installing...\n";
                $this->pullModel($model);
            } else {
                echo "Using specified model: $model\n";
            }
        } else {
            $model = $this->getOption('model', 'tinyllama');  // Default model
            if (!$this->checkModelExists($model, $availableModels)) {
                echo "Installing default model $model...\n";
                $this->pullModel($model);
            } else {
                echo "Using default model $model since no model specified\n";
            }
        }

        if (isset($options['messages'])) {
            $messages = $options['messages'];
            if (!is_array($messages) || empty($messages)) {
                throw InvalidArgumentException::invalidMessages('ollama', 'Messages must be a non-empty array.');
            }
            $this->validateMessages($messages);
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
            return $this->parseOllamaStreamingResponse($httpResponse->getBody());
        }
        
        return $this->parseOllamaResponse($httpResponse->getBody());
    }

    private function parseOllamaStreamingResponse(string $responseBody): Response
    {
        $lines = explode("\n", $responseBody);
        $fullContent = '';
        $lastMetadata = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $data = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            
            // Accumulate content from each chunk
            if (isset($data['message']['content'])) {
                $fullContent .= $data['message']['content'];
            }
            
            // Keep track of the last metadata
            if ($data['done'] === true) {
                $lastMetadata = [
                    'model' => $data['model'],
                    'created_at' => $data['created_at'],
                    'role' => $data['message']['role'],
                    'done_reason' => $data['done_reason'] ?? null,
                    'done' => $data['done'],
                    'total_duration' => $data['total_duration'],
                    'load_duration' => $data['load_duration'],
                    'prompt_eval_count' => $data['prompt_eval_count'],
                    'prompt_eval_duration' => $data['prompt_eval_duration'],
                    'eval_count' => $data['eval_count'],
                    'eval_duration' => $data['eval_duration']
                ];
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

    private function parseOllamaResponse(string $responseBody): Response
    {
        $data = $this->parseJsonResponse($responseBody);
        
        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        $content = $data['message']['content'] ?? '';
        
        $statusCode = $this->determineAIStatusCode($data);

        $metadata = [
            'model' => $data['model'],
            'created_at' => $data['created'] ?? time(),
            'role' => $data['message']['role'],
            'done_reason' => $data['done_reason'],
            'done' => $data['done'],
            'total_duration' => $data['total_duration'],
            'load_duration' => $data['load_duration'],
            'prompt_eval_count' => $data['prompt_eval_count'],
            'prompt_eval_duration' => $data['prompt_eval_duration'],
            'eval_count' => $data['eval_count'],
            'eval_duration' => $data['eval_duration']
        ];

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
