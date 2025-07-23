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

    /**
     * Generate a completion for a given prompt
     *
     * @param   string  $modelName   The model name to use for generation
     * @param   string  $prompt      The prompt to generate a response for
     * @param   array   $options     Additional options for generation
     * 
     * @return  Response  The generation response
     * @throws  InvalidArgumentException  If model doesn't exist
     * @throws  ProviderException        If generation fails
     * @since   __DEPLOY_VERSION__
     */
    public function generate(string $prompt, array $options = []): Response
    {
        $this->validateConnection();

        // Get available models first
        $availableModels = $this->getAvailableModels();
        
        // Handle model selection and installation
        if (isset($options['model'])) {
            $modelName = $options['model'];
            if (!$this->checkModelExists($modelName, $availableModels)) {
                echo "Model '$modelName' not found locally. Installing...\n";
                $this->pullModel($modelName);
            } else {
                echo "Using specified model: $modelName\n";
            }
        } else {
            $modelName = 'llama2';  // Default model
            if (!$this->checkModelExists($modelName, $availableModels)) {
                echo "Installing default model 'llama2'...\n";
                $this->pullModel($modelName);
            } else {
                echo "Using default model 'llama2' since no model specified\n";
            }
        }
        
        $requestData = [
            'model' => $modelName,
            'prompt' => $prompt
        ];
        
        // Add optional parameters
        if (isset($options['suffix'])) {
            $requestData['suffix'] = $options['suffix'];
        }
        
        if (isset($options['images']) && is_array($options['images'])) {
            $requestData['images'] = $options['images'];
        }
        
        if (isset($options['think'])) {
            $requestData['think'] = (bool) $options['think'];
        }
        
        if (isset($options['format'])) {
            $requestData['format'] = $options['format'];
        }
        
        if (isset($options['system'])) {
            $requestData['system'] = $options['system'];
        }
        
        if (isset($options['template'])) {
            $requestData['template'] = $options['template'];
        }
        
        // Default to streaming unless explicitly disabled
        $stream = $options['stream'] ?? true;
        $requestData['stream'] = $stream;
        
        if (isset($options['raw'])) {
            $requestData['raw'] = (bool) $options['raw'];
        }
        
        if (isset($options['keep_alive'])) {
            $requestData['keep_alive'] = $options['keep_alive'];
        }
        
        try {
            $jsonData = json_encode($requestData);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ProviderException(
                    $this->getName(),
                    ['message' => 'Failed to encode request data: ' . json_last_error_msg()]
                );
            }

            $endpoint = $this->getGenerateEndpoint();

            $response = $this->makePostRequest($endpoint, $jsonData);
            $body = $response->getBody();
            $fullContent = (string) $body;
            
            if (!$stream) {
                // Non-streaming response - return single JSON object
                $data = $this->parseJsonResponse($fullContent);
                
                if (isset($data['error'])) {
                    throw new ProviderException(
                        $this->getName(),
                        ['message' => $data['error']]
                    );
                }
                
                return $this->createGenerateResponse($data);
            } else {
                // Streaming response - process stream of JSON objects
                return $this->processStreamingGenerate($fullContent);
            }
            
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (ProviderException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Failed to generate completion: ' . $e->getMessage()]
            );
        }
    }

    /**
     * Process streaming generate response
     *
     * @param   string  $content  The streaming response content
     * @return  Response  The processed response
     * @throws  ProviderException  If processing fails
     * @since   __DEPLOY_VERSION__
     */
    private function processStreamingGenerate(string $content): Response
    {
        $lines = explode("\n", $content);
        $fullResponse = '';
        $finalData = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $data = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            
            // Check for error in any part of the stream
            if (isset($data['error'])) {
                throw new ProviderException(
                    $this->getName(),
                    ['message' => $data['error']]
                );
            }
            
            // Accumulate response text
            if (isset($data['response'])) {
                $fullResponse .= $data['response'];
            }
            
            // Check if this is the final response (done: true)
            if (isset($data['done']) && $data['done'] === true) {
                $finalData = $data;
                break;
            }
        }
        
        if ($finalData === null) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Incomplete streaming response received']
            );
        }
        
        // Set the full accumulated response
        $finalData['response'] = $fullResponse;
        
        return $this->createGenerateResponse($finalData);
    }
}
