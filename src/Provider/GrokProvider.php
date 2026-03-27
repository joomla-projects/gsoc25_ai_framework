<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  Copyright (C) 2025 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Provider;

use Joomla\AI\AbstractProvider;
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Interface\ProviderInterface;
use Joomla\AI\Interface\ChatInterface;
use Joomla\AI\Interface\EmbeddingInterface;
use Joomla\AI\Interface\ImageInterface;
use Joomla\AI\Interface\ModelInterface;
use Joomla\AI\Response\Response;
use Joomla\Http\HttpFactory;

/**
 * Grok (xAI) provider implementation.
 *
 * Grok uses an OpenAI-compatible API format with the base URL https://api.x.ai/v1.
 *
 * @since  __DEPLOY_VERSION__
 */
class GrokProvider extends AbstractProvider implements ProviderInterface, ChatInterface, ImageInterface, EmbeddingInterface, ModelInterface
{
    /**
     * Custom base URL for API requests
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    private $baseUrl;

    /**
     * Models that support chat capability.
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    private const CHAT_MODELS = [
        'grok-3',
        'grok-3-fast',
        'grok-3-mini',
        'grok-3-mini-fast',
        'grok-2',
        'grok-2-latest',
    ];

    /**
     * Models that support vision capability.
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    private const VISION_MODELS = [
        'grok-2-vision',
        'grok-2-vision-latest',
    ];

    /**
     * Models that support image generation capability.
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    private const IMAGE_MODELS = [
        'grok-2-image',
        'grok-2-image-latest',
    ];

    /**
     * Models that support embedding capability.
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    private const EMBEDDING_MODELS = [
        'v1',
    ];

    /**
     * Constructor.
     *
     * @param   array|\ArrayAccess  $options     Provider options array.
     * @param   HttpFactory         $httpFactory The http factory
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct($options = [], ?HttpFactory $httpFactory = null)
    {
        parent::__construct($options, $httpFactory);

        $this->baseUrl = $this->getOption('base_url', 'https://api.x.ai/v1');

        // Remove trailing slash if present
        if (substr($this->baseUrl, -1) === '/') {
            $this->baseUrl = rtrim($this->baseUrl, '/');
        }
    }

    /**
     * Check if Grok provider is supported/configured.
     *
     * @return  boolean  True if API key is available
     * @since  __DEPLOY_VERSION__
     */
    public static function isSupported(): bool
    {
        return !empty($_ENV['XAI_API_KEY']) ||
               !empty(getenv('XAI_API_KEY'));
    }

    /**
     * Get the provider name.
     *
     * @return  string  The provider name
     * @since  __DEPLOY_VERSION__
     */
    public function getName(): string
    {
        return 'Grok';
    }

    /**
     * Build HTTP headers for xAI API request.
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
        ];
    }

    /**
     * Get the xAI API key.
     *
     * @return  string  The API key
     * @throws  AuthenticationException  If API key is not found
     * @since  __DEPLOY_VERSION__
     */
    private function getApiKey(): string
    {
        $apiKey = $this->getOption('api_key') ??
                  $_ENV['XAI_API_KEY'] ??
                  getenv('XAI_API_KEY');

        if (empty($apiKey)) {
            throw new AuthenticationException(
                $this->getName(),
                ['message' => 'xAI API key not configured. Set XAI_API_KEY environment variable or provide api_key option.'],
                401
            );
        }

        return $apiKey;
    }

    /**
     * Get the chat completions endpoint URL.
     *
     * @return  string  The endpoint URL
     * @since  __DEPLOY_VERSION__
     */
    private function getChatEndpoint(): string
    {
        return $this->baseUrl . '/chat/completions';
    }

    /**
     * Get the image generation endpoint URL.
     *
     * @return  string  The endpoint URL
     * @since  __DEPLOY_VERSION__
     */
    private function getImageEndpoint(): string
    {
        return $this->baseUrl . '/images/generations';
    }

    /**
     * Get the embeddings endpoint URL.
     *
     * @return  string  The endpoint URL
     * @since  __DEPLOY_VERSION__
     */
    private function getEmbeddingsEndpoint(): string
    {
        return $this->baseUrl . '/embeddings';
    }

    /**
     * Get the models endpoint URL.
     *
     * @return  string  The endpoint URL
     * @since  __DEPLOY_VERSION__
     */
    private function getModelsEndpoint(): string
    {
        return $this->baseUrl . '/models';
    }

    /**
     * List available models from xAI.
     *
     * @return  array
     * @since  __DEPLOY_VERSION__
     */
    public function getAvailableModels(): array
    {
        $headers = $this->buildHeaders();
        $response = $this->makeGetRequest($this->getModelsEndpoint(), $headers);
        $data = $this->parseJsonResponse($response->getBody());

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
        return self::CHAT_MODELS;
    }

    /**
     * Get models that support vision capability.
     *
     * @return  array  Array of vision-capable model names
     * @since   __DEPLOY_VERSION__
     */
    public function getVisionModels(): array
    {
        return self::VISION_MODELS;
    }

    /**
     * Get models that support image generation capability.
     *
     * @return  array  Array of image-capable model names
     * @since   __DEPLOY_VERSION__
     */
    public function getImageModels(): array
    {
        return self::IMAGE_MODELS;
    }

    /**
     * Get available embedding models for this provider.
     *
     * @return  array  Array of available embedding model names
     * @since   __DEPLOY_VERSION__
     */
    public function getEmbeddingModels(): array
    {
        return self::EMBEDDING_MODELS;
    }

    /**
     * Check if a model supports a specific capability.
     *
     * @param   string  $model       The model name to check
     * @param   string  $capability  The capability to check (chat, vision, image, embedding)
     *
     * @return  bool    True if model supports the capability
     * @since   __DEPLOY_VERSION__
     */
    public function isModelCapable(string $model, string $capability): bool
    {
        $capabilityMap = [
            'chat' => self::CHAT_MODELS,
            'vision' => self::VISION_MODELS,
            'image' => self::IMAGE_MODELS,
            'embedding' => self::EMBEDDING_MODELS,
        ];

        return $this->checkModelCapability($model, $capability, $capabilityMap);
    }

    /**
     * Send a message to Grok and return response.
     *
     * @param   string  $message  The message to send
     * @param   array   $options  Additional options for the request
     *
     * @return  Response  The AI response object
     * @since  __DEPLOY_VERSION__
     */
    public function chat(string $message, array $options = []): Response
    {
        $payload = $this->buildChatRequestPayload($message, $options);

        $headers = $this->buildHeaders();

        $httpResponse = $this->makePostRequest(
            $this->getChatEndpoint(),
            json_encode($payload),
            $headers
        );

        return $this->parseChatResponse($httpResponse->getBody());
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
    public function vision(string $message, string $image, array $options = []): Response
    {
        $payload = $this->buildVisionRequestPayload($message, $image, $options);

        $headers = $this->buildHeaders();

        $httpResponse = $this->makePostRequest(
            $this->getChatEndpoint(),
            json_encode($payload),
            $headers
        );

        return $this->parseChatResponse($httpResponse->getBody());
    }

    /**
     * Generate an image from a text prompt.
     *
     * @param   string  $prompt   Descriptive text prompt for the desired image.
     * @param   array   $options  Additional options for the request.
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function generateImage(string $prompt, array $options = []): Response
    {
        $payload = $this->buildImageRequestPayload($prompt, $options);

        $headers = $this->buildHeaders();

        $httpResponse = $this->makePostRequest(
            $this->getImageEndpoint(),
            json_encode($payload),
            $headers
        );

        return $this->parseImageResponse($httpResponse->getBody(), $payload);
    }

    /**
     * Edit an existing image with a text prompt.
     *
     * Note: xAI Grok API does not currently support image editing.
     * This method throws an exception indicating the limitation.
     *
     * @param   string  $imagePath  Path to the image file to modify.
     * @param   string  $prompt     Text description of the desired modifications.
     * @param   array   $options    Additional options for the request.
     *
     * @return  Response
     * @throws  ProviderException  Always, as this capability is not supported
     * @since   __DEPLOY_VERSION__
     */
    public function editImage($imagePath, string $prompt, array $options = []): Response
    {
        throw new ProviderException(
            $this->getName(),
            ['message' => 'Image editing is not supported by the Grok provider. Use generateImage() instead.']
        );
    }

    /**
     * Create variations of an image.
     *
     * Note: xAI Grok API does not currently support image variations.
     * This method throws an exception indicating the limitation.
     *
     * @param   string  $imagePath  Path to the source image file.
     * @param   array   $options    Additional options for the request.
     *
     * @return  Response
     * @throws  ProviderException  Always, as this capability is not supported
     * @since   __DEPLOY_VERSION__
     */
    public function createImageVariation(string $imagePath, array $options = []): Response
    {
        throw new ProviderException(
            $this->getName(),
            ['message' => 'Image variations are not supported by the Grok provider. Use generateImage() instead.']
        );
    }

    /**
     * Create embeddings for the given input text(s).
     *
     * @param   string|array  $input    Text string or array of texts to embed
     * @param   string        $model    The embedding model to use
     * @param   array         $options  Additional options
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function createEmbeddings($input, string $model, array $options = []): Response
    {
        $payload = $this->buildEmbeddingPayload($input, $model, $options);

        $headers = $this->buildHeaders();

        $httpResponse = $this->makePostRequest(
            $this->getEmbeddingsEndpoint(),
            json_encode($payload),
            $headers
        );

        return $this->parseEmbeddingResponse($httpResponse->getBody(), $payload);
    }

    /**
     * Build payload for chat request.
     *
     * @param   string  $message  The user message to send
     * @param   array   $options  Additional options
     *
     * @return  array   The request payload
     * @since  __DEPLOY_VERSION__
     */
    private function buildChatRequestPayload(string $message, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'grok-3-mini-fast');

        $messages = $options['messages'] ?? [
            [
                'role' => 'user',
                'content' => $message,
            ],
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['top_p'])) {
            $payload['top_p'] = (float) $options['top_p'];
        }

        if (isset($options['stop'])) {
            $payload['stop'] = $options['stop'];
        }

        if (isset($options['frequency_penalty'])) {
            $payload['frequency_penalty'] = (float) $options['frequency_penalty'];
        }

        if (isset($options['presence_penalty'])) {
            $payload['presence_penalty'] = (float) $options['presence_penalty'];
        }

        if (isset($options['n'])) {
            $payload['n'] = (int) $options['n'];
        }

        if (isset($options['stream'])) {
            $payload['stream'] = (bool) $options['stream'];
        }

        return $payload;
    }

    /**
     * Build payload for vision request.
     *
     * @param   string  $message  The chat message about the image
     * @param   string  $image    Image URL or base64 encoded image
     * @param   array   $options  Additional options
     *
     * @return  array   The request payload
     * @since  __DEPLOY_VERSION__
     */
    private function buildVisionRequestPayload(string $message, string $image, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'grok-2-vision-latest');
        $maxTokens = $options['max_tokens'] ?? 1024;

        $imageUrl = $image;

        $content = [
            [
                'type' => 'text',
                'text' => $message,
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $imageUrl,
                ],
            ],
        ];

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'max_tokens' => $maxTokens,
        ];

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['top_p'])) {
            $payload['top_p'] = (float) $options['top_p'];
        }

        return $payload;
    }

    /**
     * Build payload for image generation request.
     *
     * @param   string  $prompt   The image generation prompt
     * @param   array   $options  Additional options
     *
     * @return  array  The request payload
     * @since   __DEPLOY_VERSION__
     */
    private function buildImageRequestPayload(string $prompt, array $options): array
    {
        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'grok-2-image-latest');

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
        ];

        if (isset($options['n'])) {
            $n = (int) $options['n'];
            if ($n < 1 || $n > 10) {
                throw InvalidArgumentException::invalidParameter('n', $options['n'], 'grok', 'Parameter "n" must be between 1 and 10.', ['min_value' => 1, 'max_value' => 10]);
            }
            $payload['n'] = $n;
        }

        if (isset($options['response_format'])) {
            if (!in_array($options['response_format'], ['url', 'b64_json'])) {
                throw InvalidArgumentException::invalidParameter('response_format', $options['response_format'], 'grok', 'Response format must be either "url" or "b64_json".', ['valid_values' => ['url', 'b64_json']]);
            }
            $payload['response_format'] = $options['response_format'];
        }

        if (isset($options['size'])) {
            $payload['size'] = $options['size'];
        }

        return $payload;
    }

    /**
     * Build request payload for embeddings.
     *
     * @param   string|array  $input    Text input(s) to embed
     * @param   string        $model    The embedding model to use
     * @param   array         $options  Additional options
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    private function buildEmbeddingPayload($input, string $model, array $options): array
    {
        $payload = [
            'input' => $input,
            'model' => $model,
        ];

        if (isset($options['encoding_format'])) {
            if (!in_array($options['encoding_format'], ['float', 'base64'])) {
                throw InvalidArgumentException::invalidParameter('encoding_format', $options['encoding_format'], 'grok', "Encoding format must be 'float' or 'base64'.");
            }
            $payload['encoding_format'] = $options['encoding_format'];
        }

        if (isset($options['dimensions'])) {
            $payload['dimensions'] = (int) $options['dimensions'];
        }

        return $payload;
    }

    /**
     * Parse xAI chat API response into unified Response object.
     *
     * @param   string  $responseBody  The JSON response body
     *
     * @return  Response  Unified response object
     * @since  __DEPLOY_VERSION__
     */
    private function parseChatResponse(string $responseBody): Response
    {
        $data = $this->parseJsonResponse($responseBody);

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        $content = $data['choices'][0]['message']['content'] ?? '';

        $statusCode = $this->determineAIStatusCode($data);

        $metadata = [
            'model' => $data['model'],
            'usage' => $data['usage'] ?? [],
            'finish_reason' => $data['choices'][0]['finish_reason'],
            'created' => $data['created'] ?? time(),
            'id' => $data['id'],
            'choices' => $data['choices'],
        ];

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            $statusCode
        );
    }

    /**
     * Parse xAI Image API response into unified Response object.
     *
     * @param   string  $responseBody  The JSON response body
     * @param   array   $payload       The original request payload
     *
     * @return  Response  Unified response object
     * @since  __DEPLOY_VERSION__
     */
    private function parseImageResponse(string $responseBody, array $payload): Response
    {
        $data = $this->parseJsonResponse($responseBody);

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        $images = [];
        $responseFormat = '';

        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $imageData) {
                $imageItem = [];

                if (isset($imageData['url'])) {
                    $imageItem['url'] = $imageData['url'];
                    $responseFormat = 'url';
                }

                if (isset($imageData['b64_json'])) {
                    $imageItem['b64_json'] = $imageData['b64_json'];
                    $responseFormat = 'b64_json';
                }

                if (isset($imageData['revised_prompt'])) {
                    $imageItem['revised_prompt'] = $imageData['revised_prompt'];
                }

                $images[] = $imageItem;
            }
        }

        $content = '';
        if ($responseFormat === 'url') {
            $urls = array_column($images, 'url');
            $content = count($urls) === 1 ? $urls[0] : json_encode($urls, JSON_PRETTY_PRINT);
        } elseif ($responseFormat === 'b64_json') {
            $base64Data = array_column($images, 'b64_json');
            $content = count($base64Data) === 1 ? $base64Data[0] : json_encode($base64Data, JSON_PRETTY_PRINT);
        }

        $metadata = [
            'model' => $data['model'] ?? $payload['model'],
            'created' => $data['created'] ?? time(),
            'response_format' => $responseFormat,
            'image_count' => count($images),
            'images' => $images,
        ];

        if (isset($data['usage'])) {
            $metadata['usage'] = $data['usage'];
        }

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            200
        );
    }

    /**
     * Parse xAI Embeddings API response into unified Response object.
     *
     * @param   string  $responseBody  The JSON response body
     * @param   array   $payload       The original request payload
     *
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    private function parseEmbeddingResponse(string $responseBody, array $payload): Response
    {
        $data = $this->parseJsonResponse($responseBody);

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        $embeddings = [];
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $embeddingData) {
                $embeddings[] = [
                    'embedding' => $embeddingData['embedding'],
                    'index' => $embeddingData['index'],
                    'object' => $embeddingData['object'],
                ];
            }
        }

        $contentData = count($embeddings) === 1 ? $embeddings[0]['embedding'] : $embeddings;
        $content = json_encode($contentData);

        $metadata = [
            'model' => $data['model'] ?? $payload['model'],
            'object' => $data['object'] ?? 'list',
            'embedding_count' => count($embeddings),
            'encoding_format' => $payload['encoding_format'] ?? 'float',
            'input_type' => is_array($payload['input']) ? 'array' : 'string',
            'raw_embeddings' => $embeddings,
        ];

        if (isset($data['usage'])) {
            $metadata['usage'] = $data['usage'];
        }

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            200
        );
    }

    /**
     * Determine status code based on finish_reason.
     *
     * @param   array  $data  Parsed response
     *
     * @return  int  Status code
     * @since  __DEPLOY_VERSION__
     */
    private function determineAIStatusCode(array $data): int
    {
        $finishReason = $data['choices'][0]['finish_reason'] ?? null;

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
