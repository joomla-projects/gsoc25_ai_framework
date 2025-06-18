<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  opyright (C) 2025 Open Source Matters, Inc. All rights reserved.
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
     * OpenAI API endpoint for conversational responses
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const RESPONSES_ENDPOINT = 'https://api.openai.com/v1/responses/create';

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
     * Models that support conversational image generation.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const CONVERSATIONAL_IMAGE_MODELS = ['gpt-4.1-mini', 'gpt-4.1', 'gpt-4o', 'gpt-4o-mini'];

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
            'conversational_image' => self::CONVERSATIONAL_IMAGE_MODELS
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
     * Generate an image through conversational interaction.
     *
     * @param   string  $prompt   Text prompt for image generation or refinement.
     * @param   array   $context  Conversation context for multi-turn interactions.
     * @param   array   $options  Additional options for the request.
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function generateImageConversational(string $prompt, array $context = [], array $options = []): Response
    {
        $requestData = $this->buildConversationalImageRequestPayload($prompt, $context, $options, 'conversational_image');
        
        $headers = $this->buildHeaders();
        
        $httpResponse = $this->makePostRequest(
            self::RESPONSES_ENDPOINT, 
            json_encode($requestData), 
            $headers
        );
        
        $this->validateResponse($httpResponse);
        
        return $this->parseConversationalImageResponse($httpResponse->body);
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
        $model = $options['model'] ?? 'gpt-image-1';
        
        if (!$this->isModelCapable($model, $capability)) {
            throw new \InvalidArgumentException("Model '$model' does not support $capability capability");
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => $options['count'] ?? 1,
            'size' => $options['size'] ?? '1024x1024',
            'response_format' => 'b64_json',
            'quality' => $options['quality'] ?? 'standard'
        ];

        // To Do: Add optional parameters if provided

        return $payload;
    }

    /**
     * Build request payload for conversational image generation.
     *
     * @param   string  $prompt   The image generation prompt.
     * @param   array   $context  Conversation context.
     * @param   array   $options  Additional options for the request.
     *
     * @return  array  The request payload
     * @since   __DEPLOY_VERSION__
     */
    private function buildConversationalImageRequestPayload(string $prompt, array $context, array $options, string $capability): array
    {
        $model = $options['model'] ?? 'gpt-4.1-mini';
        
        if (!$this->isModelCapable($model, $capability)) {
            throw new \InvalidArgumentException("Model '$model' does not support $capability capability");
        }

        $payload = [
            'model' => $model,
            'input' => $prompt,
            'tools' => [
                [
                    'type' => 'image_generation'
                ]
            ]
        ];

        if (!empty($context['previous_response_id'])) {
            $payload['previous_response_id'] = $context['previous_response_id'];
        }

        if (!empty($context['image_id'])) {
            $payload['input'] = [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $prompt]
                    ]
                ],
                [
                    'type' => 'image_generation_call',
                    'id' => $context['image_id']
                ]
            ];
        }

        // To Do: Add optional parameters if provided
        $toolOptions = [];
        
        if (isset($options['size'])) {
            $toolOptions['size'] = $options['size'];
        }

        // Can give partial images a default number
        if (!empty($options['stream']) && !empty($options['partial_images'])) {
            $toolOptions['partial_images'] = $options['partial_images'];
            $payload['stream'] = true;
        }

        if (!empty($toolOptions)) {
            $payload['tools'][0] = array_merge($payload['tools'][0], $toolOptions);
        }

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

        $content = $data['data'][0]['b64_json'] ?? '';
        
        $metadata = [
            'usage' => $data['usage'] ?? null,
            'created' => $data['created'] ?? time(),
            'format' => 'base64_png',
            'total_images' => count($data['data']),
            'revised_prompt' => $data['data'][0]['revised_prompt'] ?? null,
            'size' => $data['data'][0]['size'] ?? 'unknown'
        ];

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            200
        );
    }
    
    /**
    * Parse OpenAI Responses API response for conversational images.
    *
    * @param   string  $responseBody  The JSON response body
    *
    * @return  Response  Unified response object
    * @throws  \Exception  If response parsing fails
    * @since   __DEPLOY_VERSION__
    */
    private function parseConversationalImageResponse(string $responseBody): Response
    {
        $data = $this->parseJsonResponse($responseBody);
        
        if (isset($data['error'])) {
            throw new \Exception(
                'OpenAI Responses API Error: ' . ($data['error']['message'] ?? 'Unknown error')
            );
        }

        $imageData = '';
        $revisedPrompt = '';
        $imageCallId = '';
        
        if (isset($data['output']) && is_array($data['output'])) {
            foreach ($data['output'] as $output) {
                if ($output['type'] === 'image_generation_call' && $output['status'] === 'completed') {
                    $imageData = $output['result'];
                    $revisedPrompt = $output['revised_prompt'] ?? '';
                    $imageCallId = $output['id'] ?? '';
                    break;
                }
            }
        }

        $metadata = [
            'created' => time(),
            'format' => 'base64_png',
            'response_type' => 'conversational_image'
        ];

        if ($revisedPrompt) {
            $metadata['revised_prompt'] = $revisedPrompt;
        }
        
        if ($imageCallId) {
            $metadata['image_call_id'] = $imageCallId;
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
