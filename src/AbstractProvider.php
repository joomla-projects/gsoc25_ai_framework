<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI;

use Joomla\Http\HttpFactory;
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\RateLimitException;
use Joomla\AI\Exception\QuotaExceededException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Interface\ProviderInterface;
use Joomla\AI\Interface\ModerationInterface;

/**
 * Abstract provider class.
 *
 * @since  __DEPLOY_VERSION__
 */
abstract class AbstractProvider implements ProviderInterface
{
    /**
     * The provider options.
     *
     * @var    array|\ArrayAccess
     * @since  __DEPLOY_VERSION__
     */
    protected $options;

    /**
     * The HTTP factory instance.
     *
     * @var    HttpFactory
     * @since  __DEPLOY_VERSION__
     */
    protected $httpFactory;

    /**
     * The default model to use for API requests.
     *
     * @var    string|null
     * @since  __DEPLOY_VERSION__
     */
    protected $defaultModel = null;

    /**
     * Constructor.
     *
     * @param   array|\ArrayAccess  $options  Provider options array.
     * @param   HttpFactory   $httpFactory  The http factory
     * 
     * @throws  \InvalidArgumentException
     * @since  ___DEPLOY_VERSION___
     */
    public function __construct(array $options = [], ?HttpFactory $httpFactory = null)
    {
        // Validate provider is suported
        if (!\is_array($options) && !($options instanceof \ArrayAccess)) {
            throw new \InvalidArgumentException(
                'The options param must be an array or implement the ArrayAccess interface.'
            );
        }

        $this->options = $options;
        $this->httpFactory = $httpFactory ?: new HttpFactory();
    }

    /**
     * Get an option from the AI provider.
     * 
     * @param   string  $key      The name of the option to get.
     * @param   mixed   $default  The default value if the option is not set.
     * 
     * @return  mixed The option value.
     * @since  ___DEPLOY_VERSION___
     */
    protected function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Set the default model to use for API requests.
     *
     * @param   string  $model  The model name to set as default
     * @since   __DEPLOY_VERSION__
     */
    public function setDefaultModel(string $model)
    {
        $this->defaultModel = $model;
        return $this;
    }

    /**
     * Unset the default model, reverting to provider-specific defaults.
     *
     * @since   __DEPLOY_VERSION__
     */
    public function unsetDefaultModel()
    {
        $this->defaultModel = null;
        return $this;
    }

    /**
     * Get the current default model.
     *
     * @return  string|null  The current default model or null if not set
     * @since   __DEPLOY_VERSION__
     */
    public function getDefaultModel()
    {
        return $this->defaultModel;
    }

    /**
     * Make HTTP GET request to AI provider API.
     *
     * @param   string  $url      API endpoint URL
     * @param   array   $headers  Additional HTTP headers
     * @param   integer $timeout  Request timeout in seconds
     *
     * @return  \Joomla\Http\Response
     * @throws  \Exception
     * @since  ___DEPLOY_VERSION___
     */
    protected function makeGetRequest(string $url, array $headers = [], $timeout = null)
    {
        try {
            $response = $this->httpFactory->getHttp([])->get($url, $headers, $timeout);
            
            $this->validateResponse($response);
        } catch (AuthenticationException|RateLimitException|QuotaExceededException $e) {
            throw $e;
        } catch (ProviderException $e) {
            throw $e;
        }

        return $response;
    }

    /**
     * Make HTTP POST request.
     *
     * @param   string   $url      API endpoint URL  
     * @param   mixed    $data     POST data
     * @param   array    $headers  HTTP headers
     * @param   integer  $timeout  Request timeout
     *
     * @return  \Joomla\Http\Response
     * @throws  \Exception
     * @since  ___DEPLOY_VERSION___
     */
    protected function makePostRequest(string $url, $data, array $headers = [], $timeout = null)
    {
        try {
            $response = $this->httpFactory->getHttp([])->post($url, $data, $headers, $timeout);
            
            $this->validateResponse($response);
            
        } catch (AuthenticationException|RateLimitException|QuotaExceededException $e) {
            throw $e;
        } catch (ProviderException $e) {
            throw $e;
        }

        return $response;
    }

    /**
     * Make multipart HTTP POST request.
     *
     * @param   string  $url      API endpoint URL  
     * @param   array   $data     Form data
     * @param   array   $headers  HTTP headers
     *
     * @return  \Joomla\Http\Response
     * @since   __DEPLOY_VERSION__
     */
    protected function makeMultipartPostRequest(string $url, array $data, array $headers): \Joomla\Http\Response
    {
        $boundary = '----aiframeworkjoomla-boundary-' . uniqid();
        $postData = '';

        foreach ($data as $key => $value) {
            // Handle metadata fields
            if (in_array($key, ['_filename', '_filepath'])) {
                continue;
            }

            // Handle creating audio file object
            if ($key === 'file' && isset($data['_filepath'])) {
                $filepath = $data['_filepath'];
                $filename = $data['_filename'];
                $mimeType = $this->detectAudioMimeType($filepath);
                
                $fileResource = fopen($filepath, 'rb');
                if (!$fileResource) {
                    throw new \Exception("Cannot open file: $filepath");
                }
                
                $postData .= "--{$boundary}\r\n";
                $postData .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
                $postData .= "Content-Type: {$mimeType}\r\n\r\n";

                $fileContent = stream_get_contents($fileResource);
                fclose($fileResource);

                $postData .= $fileContent . "\r\n";
            }
            // To do: Currently strict format
            elseif ($key === 'image') {
                if (is_array($value)) {
                    foreach ($value as $index => $imageData) {
                        $postData .= "--{$boundary}\r\n";
                        $postData .= "Content-Disposition: form-data; name=\"image\"; filename=\"image{$index}.png\"\r\n";
                        $postData .= "Content-Type: image/png\r\n\r\n";
                        $postData .= $imageData . "\r\n";
                    }
                } else {
                    // Single image
                    $postData .= "--{$boundary}\r\n";
                    $postData .= "Content-Disposition: form-data; name=\"image\"; filename=\"image.png\"\r\n";
                    $postData .= "Content-Type: image/png\r\n\r\n";
                    $postData .= $value . "\r\n";
                }
            }
            // Handle mask file
            elseif ($key === 'mask') {
                $postData .= "--{$boundary}\r\n";
                $postData .= "Content-Disposition: form-data; name=\"mask\"; filename=\"mask.png\"\r\n";
                $postData .= "Content-Type: image/png\r\n\r\n";
                $postData .= $value . "\r\n";
            }
            // Handle regular form fields
            else {
                $postData .= "--{$boundary}\r\n";
                $postData .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
                $postData .= $value . "\r\n";
            }
        }
        $postData .= "--{$boundary}--\r\n";

        $headers['Content-Type'] = "multipart/form-data; boundary={$boundary}";

        return $this->makePostRequest($url, $postData, $headers);
    }

    /**
     * Extract filename from multipart field or generate default.
     *
     * @param   string  $fieldName  The form field name
     * @param   string  $data       The file data
     *
     * @return  string  The filename
     * @since   __DEPLOY_VERSION__
     */
    protected function extractFilename(string $fieldName, string $data): string
    {
        $mimeType = $this->detectImageMimeType($data);
        $extension = $this->getExtensionFromMimeType($mimeType);

        if (strpos($fieldName, 'image[') === 0) {
            $index = preg_replace('/[^0-9]/', '', $fieldName);
            return "image_{$index}.{$extension}";
        }

        return "image.{$extension}";

    }

    /**
     * Detect MIME type from image binary data.
     *
     * @param   string  $imageData  Binary image data
     *
     * @return  string  MIME type
     * @since   __DEPLOY_VERSION__
     */
    protected function detectImageMimeType(string $imageData): string
    {
        $header = substr($imageData, 0, 16);
        
        // PNG signature
        if (substr($header, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return 'image/png';
        }
        
        // JPEG signature
        if (substr($header, 0, 2) === "\xFF\xD8") {
            return 'image/jpeg';
        }
        
        // WebP signature
        if (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        
        throw new \InvalidArgumentException('Unsupported image format. Only PNG, JPEG, and WebP are supported.');
    }

    protected function getExtensionFromMimeType(string $mimeType): string
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return 'jpg';
            case 'image/webp':
                return 'webp';
            case 'image/png':
            default:
                return 'png';
        }
    }
  
    /**
     * Get audio MIME type from file path.
     *
     * @param   string  $filepath  The file path
     *
     * @return  string  The MIME type
     * @since   __DEPLOY_VERSION__
     */
    protected function detectAudioMimeType(string $input): string
    {
        if (strpos($input, '.') !== false && !in_array($input, ['mp3', 'wav', 'flac', 'mp4', 'mpeg', 'mpga', 'm4a', 'ogg', 'webm', 'opus', 'aac', 'pcm'])) {
            $input = strtolower(pathinfo($input, PATHINFO_EXTENSION));
        }
        
        $mimeMap = [
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'ogg' => 'audio/ogg',
            'webm' => 'audio/webm',
            'mp4' => 'audio/mp4',
            'mpeg' => 'audio/mpeg',
            'mpga' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'opus' => 'audio/opus',
            'aac' => 'audio/aac',
            'pcm' => 'audio/pcm',
        ];
        
        return $mimeMap[$input];
    }

    /**
     * Check if a model is available with the provider.
     *
     * @param   string  $model           The model to check
     * @param   array   $availableModels Array of available models
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    protected function isModelAvailable(string $model, array $availableModels): bool
    {
        return in_array($model, $availableModels, true);
    }

    /**
     * Get models that support a specific capability from available models.
     *
     * @param   array  $availableModels  All available models
     * @param   array  $capableModels    Models that support the capability
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    protected function getModelsByCapability(array $availableModels, array $capableModels): array
    {
        return array_values(array_intersect($availableModels, $capableModels));
    }

    /**
     * Check if a model supports a specific capability.
     *
     * @param   string  $model          The model to check
     * @param   string  $capability     The capability to check
     * @param   array   $capabilityMap  Map of capabilities to model arrays
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    protected function checkModelCapability(string $model, string $capability, array $capabilityMap): bool
    {
        if (!isset($capabilityMap[$capability])) {
            return false;
        }
        
        return $this->isModelAvailable($model, $capabilityMap[$capability]);
    }

    /**
     * Check response code and handle errors
     *
     * @param   \Joomla\Http\Response  $response  HTTP response
     *
     * @return  boolean  True if successful
     * @throws  \Exception
     * @since  ___DEPLOY_VERSION___
     */
    protected function validateResponse($response): bool
    {
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $responseBody = $response->getBody();
            $errorData = json_decode($responseBody, true) ?? ['message' => $responseBody];
            $message = $errorData['message'] ?? $errorData['error']['message'] ?? 'Unknown error';
            $providerErrorCode = $errorData['code'] ?? $errorData['error']['code'] ?? $errorData['type'] ?? $errorData['error']['type'] ?? null;

            // Handle specific HTTP status codes with appropriate exceptions
            switch ($response->getStatusCode()) {
                case 401:
                case 403:
                    throw new AuthenticationException($this->getName(), $errorData, $response->getStatusCode());
                    
                case 429:
                    if (str_contains(strtolower($message), 'quota') || str_contains(strtolower($message), 'credits') ||str_contains(strtolower($message), 'billing')) {
                        throw new QuotaExceededException($this->getName(), $errorData, $response->getStatusCode());
                    } elseif (str_contains(strtolower($message), 'rate') || str_contains(strtolower($message), 'limit') || str_contains(strtolower($message), 'too many requests')) {
                        throw new RateLimitException($this->getName(), $errorData, $response->getStatusCode());
                    }
                    
                default:
                    throw new ProviderException($this->getName(), $errorData, $response->getStatusCode(), $providerErrorCode);
            }
        }
    
        return true;
    }

    protected function isJsonResponse(string $responseBody): bool
    {
        // JSON responses start with { or [
        $trimmed = ltrim($responseBody);
        return !empty($trimmed) && ($trimmed[0] === '{' || $trimmed[0] === '[');
    }

    /**
     * Parse JSON response safely
     * 
     * @param   string  $jsonString  The JSON string to parse
     * 
     * @return  array  The parsed JSON data
     * @throws  UnserializableResponseException  If JSON parsing fails
     * @since  ___DEPLOY_VERSION___
     */
    protected function parseJsonResponse(string $jsonString): array
    {
        $decoded = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new UnserializableResponseException($this->getName(), $jsonString, json_last_error_msg(), 422);
        }
        
        return $decoded;
    }

    /**
     * Apply moderation to the input if the provider implements ModerationInterface.
     *
     * @param   string|array  $input    The input to moderate (text or images)
     * @param   array         $options  Additional options for moderation
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    protected function moderateInput($input, array $options = []): bool
    {
        // Check if the provider supports moderation
        if (!($this instanceof ModerationInterface)) {
            return false;
        }

        $moderationResult = $this->moderate($input, $options);
        return $this->isContentFlagged($moderationResult);
    }
}
