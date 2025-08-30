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
use Joomla\AI\Interface\ChatInterface;
use Joomla\AI\Interface\ProviderInterface;
use Joomla\AI\Response\Response;
use Joomla\Http\HttpFactory;

/**
 * Anthropic provider implementation.
 *
 * @since  __DEPLOY_VERSION__
 */
class AnthropicProvider extends AbstractProvider implements ProviderInterface, ChatInterface
{
    /**
     * Custom base URL for API requests
     *
     * @var    string
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
    public function __construct($options = [], ?HttpFactory $httpFactory = null)
    {
        parent::__construct($options, $httpFactory);

        $this->baseUrl = $this->getOption('base_url', 'https://api.anthropic.com/v1');

        // Remove trailing slash if present
        if (substr($this->baseUrl, -1) === '/') {
            $this->baseUrl = rtrim($this->baseUrl, '/');
        }
    }

    /**
     * Check if Anthropic provider is supported/configured.
     *
     * @return  boolean  True if API key is available
     * @since  __DEPLOY_VERSION__
     */
    public static function isSupported(): bool
    {
        return !empty($_ENV['ANTHROPIC_API_KEY']) ||
               !empty(getenv('ANTHROPIC_API_KEY'));
    }

    /**
     * Get the provider name.
     *
     * @return  string  The provider name
     * @since  __DEPLOY_VERSION__
     */
    public function getName(): string
    {
        return 'Anthropic';
    }

    /**
     * Build HTTP headers for Anthropic API request.
     *
     * @return  array  HTTP headers
     * @since  __DEPLOY_VERSION__
     */
    private function buildHeaders(): array
    {
        $apiKey = $this->getApiKey();

        return [
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01', // Latest version
            'content-type' => 'application/json'
        ];
    }

    /**
     * Get the Anthropic API key.
     *
     * @return  string  The API key
     * @throws  AuthenticationException  If API key is not found
     * @since  __DEPLOY_VERSION__
     */
    private function getApiKey(): string
    {
        $apiKey = $this->getOption('api_key') ??
                  $_ENV['ANTHROPIC_API_KEY'] ??
                  getenv('ANTHROPIC_API_KEY');

        if (empty($apiKey)) {
            throw new AuthenticationException(
                $this->getName(),
                ['message' => 'Anthropic API key not configured. Set ANTHROPIC_API_KEY environment variable or provide api_key option.'],
                401
            );
        }

        return $apiKey;
    }

    /**
     * Get the messages endpoint URL.
     *
     * @return  string  The endpoint URL
     * @since  __DEPLOY_VERSION__
     */
    private function getMessagesEndpoint(): string
    {
        return $this->baseUrl . '/messages';
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
     * List available models from Anthropic.
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
     * Get information about a specific model.
     *
     * @param   string  $modelId  The model identifier or alias
     *
     * @return  Response  The response containing model information
     * @since  __DEPLOY_VERSION__
     */
    public function getModel(string $modelId): Response
    {
        $endpoint = $this->getModelsEndpoint() . '/' . urlencode($modelId);
        $headers = $this->buildHeaders();
        $httpResponse = $this->makeGetRequest($endpoint, $headers);
        $data = $this->parseJsonResponse($httpResponse->getBody());

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        return new Response(
            json_encode($data, JSON_PRETTY_PRINT),
            $this->getName(),
            ['raw_response' => $data],
            200
        );
    }

    /**
     * Build payload for chat request.
     *
     * @param   string  $message   The user message to send
     * @param   array   $options  Additional options
     *
     * @return  array   The request payload
     * @since  __DEPLOY_VERSION__
     */
    private function buildChatRequestPayload(string $message, array $options = []): array
    {
        $model = $options['model'] ?? $this->getOption('model', 'claude-3-haiku-20240307');
        $maxTokens = $options['max_tokens'] ?? 1024;

        $messages = $options['messages'] ?? [
            [
                'role' => 'user',
                'content' => $message
            ]
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens
        ];

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
     * @throws  \InvalidArgumentException  If model does not support vision capability
     * @since  __DEPLOY_VERSION__
     */
    private function buildVisionRequestPayload(string $message, string $image, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'claude-3-haiku-20240307');
        $maxTokens = $options['max_tokens'] ?? 1024;

        // Determine image format and validate
        $imageContent = $this->buildImageContent($image);

        $content = [
            [
                'type' => 'text',
                'text' => $message
            ],
            $imageContent
        ];

        $messages = [
            [
                'role' => 'user',
                'content' => $content
            ]
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens
        ];

        // Add optional parameters
        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['top_k'])) {
            $payload['top_k'] = (int) $options['top_k'];
        }

        if (isset($options['top_p'])) {
            $payload['top_p'] = (float) $options['top_p'];
        }

        if (isset($options['stop_sequences'])) {
            $payload['stop_sequences'] = $options['stop_sequences'];
        }

        if (isset($options['system'])) {
            $payload['system'] = $options['system'];
        }

        return $payload;
    }

    /**
     * Send a message to Anthropic and return response.
     *
     * @param   string  $message   The message to send
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
            $this->getMessagesEndpoint(),
            json_encode($payload),
            $headers
        );

        return $this->parseAnthropicResponse($httpResponse->getBody());
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
            $this->getMessagesEndpoint(),
            json_encode($payload),
            $headers
        );

        return $this->parseAnthropicResponse($httpResponse->getBody());
    }

    /**
     * Parse Anthropic API response into unified Response object.
     *
     * @param   string  $responseBody  The JSON response body
     *
     * @return  Response  Unified response object
     * @since  __DEPLOY_VERSION__
     */
    private function parseAnthropicResponse(string $responseBody): Response
    {
        $data = $this->parseJsonResponse($responseBody);

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        // Get the text content from the first content block
        $content = '';
        if (!empty($data['content'][0]['text'])) {
            $content = $data['content'][0]['text'];
        }

        $statusCode = $this->determineAIStatusCode($data);

        $metadata = [
            'id' => $data['id'] ?? null,
            'model' => $data['model'],
            'role' => $data['role'],
            'type' => $data['type'],
            'usage' => $data['usage'] ?? [],
            'input_tokens' => $data['usage']['input_tokens'] ?? 0,
            'output_tokens' => $data['usage']['output_tokens'] ?? 0,
            'stop_reason' => $data['stop_reason'],
            'stop_sequence' => $data['stop_sequence']
        ];

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            $statusCode
        );
    }

    /**
     * Determine status code based on Anthropic's stop_reason.
     *
     * @param array $data Parsed Anthropic response
     * @return int Status code
     */
    private function determineAIStatusCode(array $data): int
    {
        $stopReason = $data['stop_reason'] ?? null;

        switch ($stopReason) {
            case 'end_turn':
                return 200;
            case 'max_tokens':
                return 429;
            case 'refusal':
                return 403;
            case null:
                return 200; // Streaming: message_start event
            default:
                return 200;
        }
    }

    /**
     * Build image content block for Anthropic API.
     *
     * @param   string  $image  Image URL or base64 encoded image
     *
     * @return  array   Image content block
     * @throws  \InvalidArgumentException  If image format is invalid
     * @since  __DEPLOY_VERSION__
     */
    private function buildImageContent(string $image): array
    {
        // Check if it's a URL
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            $imageData = $this->fetchImageFromUrl($image);
            $mimeType = $this->detectImageMimeType($imageData);

            return [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $mimeType,
                    'data' => base64_encode($imageData)
                ]
            ];
        }

        // Check if it's already base64 encoded
        if (preg_match('/^data:image\/([a-zA-Z0-9+\/]+);base64,(.+)$/', $image, $matches)) {
            $mimeType = 'image/' . $matches[1];
            $base64Data = $matches[2];

            $this->validateImageMimeType($mimeType);

            return [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $mimeType,
                    'data' => $base64Data
                ]
            ];
        }

        // If it is a file path
        if (file_exists($image)) {
            $imageData = file_get_contents($image);
            $mimeType = $this->detectImageMimeType($imageData);

            return [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $mimeType,
                    'data' => base64_encode($imageData)
                ]
            ];
        }

        throw InvalidArgumentException::invalidParameter('image', $image, 'anthropic', 'Image must be a valid URL, file path, or base64 encoded data.');
    }

    /**
     * Fetch image data from URL.
     *
     * @param   string  $url  Image URL
     *
     * @return  string  Image binary data
     * @throws  \Exception  If image cannot be fetched
     * @since  __DEPLOY_VERSION__
     */
    private function fetchImageFromUrl(string $url): string
    {
        $httpResponse = $this->makeGetRequest($url);

        if ($httpResponse->getStatusCode() !== 200) {
            throw new \Exception("Failed to fetch image from URL: {$url}");
        }

        return $httpResponse->getBody();
    }

    /**
     * Validate image MIME type for Anthropic API.
     *
     * @param   string  $mimeType  MIME type to validate
     *
     * @throws  \InvalidArgumentException  If MIME type is not supported
     * @since  __DEPLOY_VERSION__
     */
    private function validateImageMimeType(string $mimeType): void
    {
        $supportedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mimeType, $supportedTypes)) {
            throw InvalidArgumentException::invalidParameter('image_type', $mimeType, 'anthropic', 'Supported image types: ' . implode(', ', $supportedTypes), ['supported_types' => $supportedTypes]);
        }
    }
}
