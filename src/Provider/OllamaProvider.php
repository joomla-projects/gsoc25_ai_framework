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
            return $this->pullModel($modelName, true, false, $force);
        }
        
        return true;
    }
}
