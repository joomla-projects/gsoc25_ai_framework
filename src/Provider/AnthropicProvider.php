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
use Joomla\AI\Interface\ModelInterface;
use Joomla\AI\Interface\ProviderInterface;
use Joomla\AI\Response\Response;
use Joomla\Http\HttpFactory;

/**
 * Anthropic provider implementation.
 *
 * @since  __DEPLOY_VERSION__
 */
class AnthropicProvider extends AbstractProvider implements ProviderInterface, ChatInterface, ModelInterface
{
    /**
     * Default model used when none is configured
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    private const DEFAULT_MODEL = 'claude-opus-5-5';

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
     * The API key comes from the api_key option and is checked when a request is made.
     *
     * @return  boolean  Always true
     * @since  __DEPLOY_VERSION__
     */
    public static function isSupported(): bool
    {
        return true;
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
        $apiKey = $this->getOption('api_key');

        if (empty($apiKey)) {
            throw new AuthenticationException(
                $this->getName(),
                ['message' => 'Anthropic API key not configured. Provide the api_key option.'],
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
        // The Models API pages at 20 by default
        $response = $this->makeGetRequest($this->getModelsEndpoint() . '?limit=1000', $headers);
        $data = $this->parseJsonResponse($response->getBody());
        $this->assertResponseFields($data, ['data[]'], (string) $response->getBody());

        return array_column($data['data'], 'id');
    }

    /**
     * Check if a model supports a capability.
     *
     * Uses the "models" entry of the capabilities option when set for that capability;
     * otherwise every model returned by the Models API supports chat and vision.
     *
     * @param   string  $model       The model ID
     * @param   string  $capability  The capability (chat, vision)
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    public function isModelCapable(string $model, string $capability): bool
    {
        $listed = $this->getCapability('models', $capability);

        if ($listed !== null) {
            return in_array($model, (array) $listed, true);
        }

        if (!in_array($capability, ['chat', 'vision'], true)) {
            return false;
        }

        return in_array($model, $this->getAvailableModels(), true);
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
        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', self::DEFAULT_MODEL);
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

        return $this->addOptionalParameters($payload, $options);
    }

    /**
     * Add optional sampling and prompt parameters to a request payload.
     *
     * @param   array  $payload  The request payload
     * @param   array  $options  Additional options
     *
     * @return  array  The request payload with optional parameters applied
     * @since  __DEPLOY_VERSION__
     */
    private function addOptionalParameters(array $payload, array $options): array
    {
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
        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', self::DEFAULT_MODEL);
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

        return $this->addOptionalParameters($payload, $options);
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

        $this->assertResponseFields($data, ['content[]'], $responseBody);

        // Join the text blocks; thinking and tool_use blocks may come before them
        $content = '';
        foreach ($data['content'] as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text') {
                $content .= (string) ($block['text'] ?? '');
            }
        }

        $statusCode = $this->determineAIStatusCode($data);

        $metadata = [
            'id' => $data['id'] ?? null,
            'model' => $data['model'] ?? null,
            'role' => $data['role'] ?? null,
            'type' => $data['type'] ?? null,
            'usage' => is_array($data['usage'] ?? null) ? $data['usage'] : [],
            'finish_reason' => $this->normaliseFinishReason($data['stop_reason'] ?? null),
            'stop_reason' => $data['stop_reason'] ?? null,
            'stop_sequence' => $data['stop_sequence'] ?? null
        ] + $this->normaliseUsage(
            $data['usage']['input_tokens'] ?? null,
            $data['usage']['output_tokens'] ?? null
        );

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            $statusCode
        );
    }

    /**
     * Map Anthropic's stop_reason to the normalised finish reason.
     *
     * @param   string|null  $stopReason  Anthropic stop_reason
     *
     * @return  string|null
     * @since  __DEPLOY_VERSION__
     */
    private function normaliseFinishReason(?string $stopReason): ?string
    {
        return match ($stopReason) {
            'end_turn', 'stop_sequence' => 'stop',
            'max_tokens', 'model_context_window_exceeded' => 'length',
            'tool_use' => 'tool_calls',
            'refusal' => 'content_filter',
            default => $stopReason,
        };
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
     * @param   string  $image  Image URL or base64 data URI (data:image/...;base64,...)
     *
     * @return  array   Image content block
     * @throws  \InvalidArgumentException  If image format is invalid
     * @since  __DEPLOY_VERSION__
     */
    private function buildImageContent(string $image): array
    {
        // URLs are passed through; Anthropic fetches the image itself
        if (filter_var($image, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $image)) {
            return [
                'type' => 'image',
                'source' => [
                    'type' => 'url',
                    'url' => $image
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

        throw InvalidArgumentException::invalidParameter('image', $image, 'anthropic', 'Image must be an http(s) URL or a base64 data URI.');
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
