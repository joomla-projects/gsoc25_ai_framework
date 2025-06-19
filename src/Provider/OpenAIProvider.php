<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  Copyright (C) 2025 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Provider;

use Joomla\AI\AbstractProvider;
use Joomla\AI\Interface\ChatInterface;
use Joomla\AI\Interface\ImageInterface;
use Joomla\AI\Response\Response;
use Joomla\AI\Interface\ModelInterface;

/**
 * OpenAI provider implementation for chat completions.
 *
 * @since  __DEPLOY_VERSION__
 */
class OpenAIProvider extends AbstractProvider implements ChatInterface, ModelInterface, ImageInterface
{
    /**
     * Default OpenAI API endpoint for chat completions
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const DEFAULT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * OpenAI API endpoint for image generation
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const IMAGE_ENDPOINT = 'https://api.openai.com/v1/images/generations';

    /**
     * OpenAI API endpoint for image editing
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const IMAGE_EDIT_ENDPOINT = 'https://api.openai.com/v1/images/edits';

    /**
     * OpenAI API endpoint for image variations
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const IMAGE_VARIATIONS_ENDPOINT = 'https://api.openai.com/v1/images/variations';

    /**
     * Models that support chat capability.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const CHAT_MODELS = ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'];

    /**
     * Models that support vision capability.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const VISION_MODELS = ['gpt-4o', 'gpt-4o-mini'];

    /**
     * Models that support image generation capability.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const IMAGE_MODELS = ['dall-e-2', 'dall-e-3', 'gpt-image-1'];

    /**
     * Check if OpenAI provider is supported/configured.
     *
     * @return  boolean  True if API key is available
     * @since  __DEPLOY_VERSION__
     */
    public static function isSupported(): bool
    {
        return !empty($_ENV['OPENAI_API_KEY']) || 
               !empty(getenv('OPENAI_API_KEY'));
    }

    /**
     * Get the provider name.
     *
     * @return  string  The provider name
     * @since  __DEPLOY_VERSION__
     */
    public function getName(): string
    {
        return 'OpenAI';
    }

    /**
     * Get all available models for this provider.
     *
     * @return  array  Array of available model names
     * @since   __DEPLOY_VERSION__
     */
    public function getAvailableModels(): array
    {
        $headers = $this->buildHeaders();
        $response = $this->makeGetRequest('https://api.openai.com/v1/models', $headers);
        $this->validateResponse($response);
        $data = $this->parseJsonResponse($response->body);
        
        return array_column($data['data'], 'id');
    }

    /**
     * Get models that support chat capability.
     *
     * @return  array  Array of chat-capable model names
     * @since   __DEPLOY_VERSION__
     */
    public function getChatModels(): array
    {
        $available = $this->getAvailableModels();
        return $this->getModelsByCapability($available, self::CHAT_MODELS);
    }

    /**
     * Get models that support vision capability.
     *
     * @return  array  Array of vision-capable model names
     * @since   __DEPLOY_VERSION__
     */
    public function getVisionModels(): array
    {
        $available = $this->getAvailableModels();
        return $this->getModelsByCapability($available, self::VISION_MODELS);
    }

    /**
     * Get models that support image generation capability.
     *
     * @return  array  Array of image capable model names
     * @since   __DEPLOY_VERSION__
     */
    public function getImageModels(): array
    {
        $available = $this->getAvailableModels();
        return $this->getModelsByCapability($available, self::IMAGE_MODELS);
    }

    /**
     * Check if a specific model is supported by this provider.
     *
     * @param   string  $model  The model name to check
     *
     * @return  bool    True if model is available, false otherwise
     * @since   __DEPLOY_VERSION__
     */
    public function isModelSupported(string $model): bool
    {
        $available = $this->getAvailableModels();
        return $this->isModelAvailable($model, $available);
    }

    /**
     * Check if a model supports a specific capability.
     *
     * @param   string  $model       The model name to check
     * @param   string  $capability  The capability to check (chat, image, vision)
     *
     * @return  bool    True if model supports the capability, false otherwise
     * @since   __DEPLOY_VERSION__
     */
    public function isModelCapable(string $model, string $capability): bool
    {
        $capabilityMap = [
            'chat' => self::CHAT_MODELS,
            'vision' => self::VISION_MODELS,
            'image' => self::IMAGE_MODELS,
        ];
        return $this->checkModelCapability($model, $capability, $capabilityMap);
    }

    /**
     * Send a message to OpenAI and return response.
     *
     * @param   string  $message   The message to send
     * @param   array   $options  Additional options for the request
     * 
     * @return  Response  The AI response object
     * @since  __DEPLOY_VERSION__
     */
    public function chat(string $message, array $options = []): Response
    {
        $requestData = $this->buildChatRequestPayload($message, $options, 'chat');

        // To Do: Remove repetition 
        $endpoint = $this->getEndpoint();
        $headers = $this->buildHeaders();
        
        $httpResponse = $this->makePostRequest(
            $endpoint, 
            json_encode($requestData), 
            $headers
        );
        
        $this->validateResponse($httpResponse);
        
        return $this->parseOpenAIResponse($httpResponse->body);
    }

    /**
     * Generate chat completion with vision capability and return Response.
     *
     * @param   string  $message  The chat message about the image.
     * @param   string  $image    Image URL or base64 encoded image.
     * @param   array   $options  Additional options for the request.
     * 
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    public function chatWithVision(string $message, string $image, array $options = []): Response
    {
        
        $requestData = $this->buildVisionRequestPayload($message, $image, $options, 'vision');
        
        $endpoint = $this->getEndpoint();
        $headers = $this->buildHeaders();
        
        $httpResponse = $this->makePostRequest(
            $endpoint, 
            json_encode($requestData), 
            $headers
        );
        
        $this->validateResponse($httpResponse);
        
        return $this->parseOpenAIResponse($httpResponse->body);
    }

    /**
     * Generate a new image from the given prompt.
     *
     * @param   string  $prompt   Descriptive text prompt for the desired image.
     * @param   array   $options  Additional options for the request.
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function generateImage(string $prompt, array $options = []): Response
    {
        $requestData = $this->buildImageRequestPayload($prompt, $options, 'image');
        
        $headers = $this->buildHeaders();
        
        $httpResponse = $this->makePostRequest(
            self::IMAGE_ENDPOINT, 
            json_encode($requestData), 
            $headers
        );
        
        $this->validateResponse($httpResponse);
        
        return $this->parseImageResponse($httpResponse->body);
    }

    /**
     * Edit an image using OpenAI Image Edit API.
     *
     * @param   string  $prompt   The text description of the desired edit
     * @param   mixed   $images   Image file path(s), base64 string(s), or array of either
     * @param   array   $options  Additional options for image editing
     *
     * @return  Response  The response containing the edited image
     * @since   __DEPLOY_VERSION__
     */
    public function editImage(string $prompt, $images, array $options = []): Response
    {
        // Only dall-e-2 and gpt-image-1 support editing
        $model = $options['model'] ?? 'dall-e-2';
        if (!in_array($model, ['dall-e-2', 'gpt-image-1'])) {
            throw new \InvalidArgumentException('Image editing only supports dall-e-2 and gpt-image-1 models');
        }

        $formData = $this->buildImageEditFormData($prompt, $images, $options);
        
        $headers = $this->buildHeaders();
        
        $httpResponse = $this->makeMultipartPostRequest(
            self::IMAGE_EDIT_ENDPOINT, 
            $formData, 
            $headers
        );
        
        $this->validateResponse($httpResponse);
        
        return $this->parseImageResponse($httpResponse->body);
    }

    /**
     * Build form data for image edit request.
     *
     * @param   string  $prompt   The edit prompt
     * @param   mixed   $images   Image data (various formats)
     * @param   array   $options  Additional options
     *
     * @return  array  Form data for multipart request
     * @throws  \InvalidArgumentException  If data is invalid
     * @since   __DEPLOY_VERSION__
     */
    private function buildImageEditFormData(string $prompt, $images, array $options): array
    {
        $model = $options['model'] ?? 'dall-e-2';
        
        $formData = [
            'model' => $model,
            'prompt' => $prompt
        ];

        if ($model === 'dall-e-2') {
            // DALL-E 2: Only single image allowed
            if (is_array($images) && count($images) > 1) {
                throw new \InvalidArgumentException('DALL-E 2 only supports editing one image at a time');
            }

            // To Do: Validate square PNG for DALL-E 2
            $image = is_array($images) ? $images[0] : $images;
            $imageData = $this->processImageInput($image);
            $formData["image"] = $imageData;
        } else {
            // GPT-Image-1: Supports multiple images (up to 16)
            $imageArray = is_array($images) ? $images : [$images];
            
            if (count($imageArray) > 16) {
                throw new \InvalidArgumentException('GPT-Image-1 supports maximum 16 images for editing');
            }
            
            if (count($imageArray) === 1) {
                $imageData = $this->processImageInput($imageArray[0]);
                $formData["image"] = $imageData;
            } else {
                $formData["images"] = [];
                foreach ($imageArray as $image) {
                    $imageData = $this->processImageInput($image);
                    $formData["images"][] = $imageData;
                }
            }
        }

        // Handle mask if provided
        if (isset($options['mask'])) {
            $maskData = $this->processImageInput($options['mask']);
            $formData['mask'] = $maskData;
        }

        // Add response format for DALL-E 2 only
        if ($model === 'dall-e-2') {
            $formData['response_format'] = $options['response_format'] ?? 'b64_json';
        }

        // To Do: Add optional parameters

        return $formData;
    }

    /**
     * Process image input.
     *
     * @param   mixed   $image  Image file path or base64 string
     *
     * @return  array  Processed image data for form upload
     * @since   __DEPLOY_VERSION__
     */
    private function processImageInput($image): array
    {
        // Case 1: File path
        if (is_string($image) && file_exists($image)) {
            return [
                'name' => basename($image),
                'type' => mime_content_type($image),
                'content' => file_get_contents($image)
            ];
        }
        
        // Case 2: Base64 string
        if (is_string($image)) {
            if (strpos($image, 'data:') === 0) {
                $image = substr($image, strpos($image, ',') + 1);
            }
            
            $content = base64_decode($image);
            
            return [
                'name' => 'image.png',
                'type' => 'image/png',
                'content' => $content
            ];
        }
        
        throw new \InvalidArgumentException('Invalid image format. Provide file path or base64 string');
    }

    /**
     * Ask method - alias for chat/prompt for now
     *
     * @param   string  $question  The question to ask
     * @param   array   $options   Additional options
     * 
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    public function ask(string $question, array $options = []): Response
    {
        return $this->chat($question, $options);
    }

    /**
     * Alias for chat/prompt for now.
     *
     * @param   string  $prompt   The prompt to send
     * @param   array   $options  Additional options
     * 
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    public function prompt(string $prompt, array $options = []): Response
    {
        return $this->chat($prompt, $options);
    }

    /**
     * Build the request payload for OpenAI API.
     *
     * @param   string  $message   The user message to send
     * @param   array   $options  Additional options
     * 
     * @return  array   The request payload
     * @throws  \InvalidArgumentException  If model does not support chat capability
     * @since  __DEPLOY_VERSION__
     */
    private function buildChatRequestPayload(string $message, array $options = [], string $capability): array
    {
        $model = $options['model'] ?? $this->getOption('model', 'gpt-4o-mini');
        
        if (!$this->isModelCapable($model, $capability)) {
            throw new \InvalidArgumentException("Model '$model' does not support $capability capability");
        }

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ]
        ];

        // To Do: Add optional parameters if provided
        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['n'])) {
            $payload['n'] = (int) $options['n'];
        }

        return $payload;
    }

    /**
     * Build the request payload for OpenAI API with vision capability.
     *
     * @param   string  $message  The chat message about the image
     * @param   string  $image    Image URL or base64 encoded image
     * @param   array   $options  Additional options
     * 
     * @return  array   The request payload
     * @throws  \InvalidArgumentException  If model does not support vision capability
     * @since  __DEPLOY_VERSION__
     */
    private function buildVisionRequestPayload(string $message, string $image, array $options = [], string $capability): array
    {
        $model = $options['model'] ?? $this->getOption('model', 'gpt-4o-mini');
        
        if (!$this->isModelCapable($model, $capability)) {
            throw new \InvalidArgumentException("Model '$model' does not support $capability capability");
        }

        $content = [
            [
                'type' => 'text',
                'text' => $message
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $image
                ]
            ]
        ];
            
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ]
        ];

        // To Do: Add optional parameters if provided
        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['n'])) {
            $payload['n'] = (int) $options['n'];
        }

        return $payload;
    }

    /**
     * Build request payload for image generation.
     *
     * @param   string  $prompt      The image generation prompt.
     * @param   array   $options     Additional options for the request.
     * @param   string  $capability  Required capability.
     *
     * @return  array  The request payload.
     * @since   __DEPLOY_VERSION__
     */
    private function buildImageRequestPayload(string $prompt, array $options, string $capability): array
    {
        $model = $options['model'] ?? 'dall-e-2';

        if (!$this->isModelCapable($model, $capability)) {
            throw new \InvalidArgumentException("Model '$model' does not support $capability capability");
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt
        ];
        
        if (in_array($model, ['dall-e-2', 'dall-e-3'])) {
            $responseFormat = $options['response_format'] ?? 'b64_json';
            if (in_array($responseFormat, ['url', 'b64_json'])) {
                $payload['response_format'] = $responseFormat;
            } else {
                throw new \InvalidArgumentException("Unsupported response format: $responseFormat");
            }
        }
        
        // To Do: Add optional parameters if provided

        return $payload;
    }

    /**
     * Get the API endpoint URL.
     *
     * @return  string  The endpoint URL
     * @since  __DEPLOY_VERSION__
     */
    private function getEndpoint(): string
    {
        return $this->getOption('endpoint', self::DEFAULT_ENDPOINT);
    }

    /**
     * Build HTTP headers for OpenAI API request.
     *
     * @return  array  HTTP headers
     * @since  __DEPLOY_VERSION__
     */
    private function buildHeaders(): array
    {
        $apiKey = $this->getApiKey();
        
        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'User-Agent' => 'Joomla-AI-Framework'
        ];
    }

    /**
     * Get the OpenAI API key.
     *
     * @return  string  The API key
     * @throws  \Exception  If API key is not found
     * @since  __DEPLOY_VERSION__
     */
    private function getApiKey(): string
    {
        // To do: Move this to a configuration file or environment variable
        $apiKey = $this->getOption('api_key') ?? 
                  $_ENV['OPENAI_API_KEY'] ?? 
                  getenv('OPENAI_API_KEY');
        
        if (empty($apiKey)) {
            throw new \Exception('OpenAI API key not configured. Set OPENAI_API_KEY environment variable.');
        }
        
        return $apiKey;
    }

    /**
     * Parse OpenAI API response into unified Response object.
     *
     * @param   string  $responseBody  The JSON response body
     * 
     * @return  Response  Unified response object
     * @throws  \Exception  If response parsing fails
     * @since  __DEPLOY_VERSION__
     */
    private function parseOpenAIResponse(string $responseBody): Response
    {
        $data = $this->parseJsonResponse($responseBody);
        
        if (isset($data['error'])) {
            throw new \Exception(
                'OpenAI API Error: ' . ($data['error']['message'] ?? 'Unknown error')
            );
        }

        // To Do: Handle multiple choices if needed
        $content = $data['choices'][0]['message']['content'] ?? '';
        
        $statusCode = $this->determineAIStatusCode($data);

        $metadata = [
            'model' => $data['model'],
            'usage' => $data['usage'],
            'finish_reason' => $data['choices'][0]['finish_reason'],
            'created' => $data['created'] ?? time(),
            'id' => $data['id']
        ];

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            $statusCode
        );
    }

    /**
     * Parse OpenAI Image API response into unified Response object.
     *
     * @param   string  $responseBody  The JSON response body
     * 
     * @return  Response  Unified response object
     * @throws  \Exception  If response parsing fails
     * @since  __DEPLOY_VERSION__
     */
    private function parseImageResponse(string $responseBody): Response
    {
        $data = $this->parseJsonResponse($responseBody);
        
        if (isset($data['error'])) {
            throw new \Exception(
                'OpenAI Image API Error: ' . ($data['error']['message'] ?? 'Unknown error')
            );
        }

        $imageData = '';
        $imageUrl = '';
        $revisedPrompt = '';
        $responseFormat = '';
        
        $firstImage = $data['data'][0];
            
        // Check for base64 data (gpt-image-1 default, or dall-e with response_format = b64_json)
        if (isset($firstImage['b64_json'])) {
            $imageData = $firstImage['b64_json'];
            $responseFormat = 'base64';
        }

        // Check for URL (dall-e-2/dall-e-3 with response_format = url)
        if (isset($firstImage['url'])) {
            $imageUrl = $firstImage['url'];
            $responseFormat = 'url';
        }
        
        // Revised prompt (dall-e-3 only)
        if (isset($firstImage['revised_prompt'])) {
            $revisedPrompt = $firstImage['revised_prompt'];
        }

        $metadata = [
            'created' => $data['created'] ?? time(),
            'format' => $responseFormat === 'base64' ? 'base64_png' : 'url',
            'response_format' => $responseFormat,
            'total_images' => isset($data['data']) ? count($data['data']) : 1
        ];

        if ($imageUrl) {
            $metadata['image_url'] = $imageUrl;
            $metadata['url_expires'] = 'URLs are valid for 60 minutes';
        }

        // Add usage/token information if available (gpt-image-1 only)
        if (isset($data['usage'])) {
            $metadata['usage'] = $data['usage'];
            $metadata['total_tokens'] = $data['usage']['total_tokens'];
        }

        return new Response(
            $imageData,
            $this->getName(),
            $metadata,
            200
        );
    }

    /**
     * Determine status code based on OpenAI's finish_reason.
     *
     * @param   array  $data  Parsed OpenAI response
     * 
     * @return  integer  Status Code
     * @since   __DEPLOY_VERSION__
     */
    private function determineAIStatusCode(array $data): int
    {
        $finishReason = $data['choices'][0]['finish_reason'];
        
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
