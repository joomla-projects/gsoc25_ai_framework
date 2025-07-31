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
use Joomla\AI\Response\Response;
use Joomla\Http\HttpFactory;

/**
 * Anthropic provider implementation.
 *
 * @since  __DEPLOY_VERSION__
 */
class AnthropicProvider extends AbstractProvider implements ProviderInterface
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
    public function __construct(array $options = [], ?HttpFactory $httpFactory = null)
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
}
