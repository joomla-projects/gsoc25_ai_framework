<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  ___Copyright___
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Provider;

use Joomla\AI\AbstractProvider;
use Joomla\AI\Response;

/**
 * OpenAI provider implementation for chat completions.
 *
 * @since  __DEPLOY_VERSION__
 */
class OpenAIProvider extends AbstractProvider
{
    /**
     * Default OpenAI API endpoint for chat completions
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const DEFAULT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * Default model to use for chat completions
     *      
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const DEFAULT_MODEL = 'gpt-4o-mini';

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
     * Send a prompt to OpenAI and return response.
     *
     * @param   string  $prompt   The prompt to send
     * @param   array   $options  Additional options for the request
     * 
     * @return  Response  The AI response object
     * @since  __DEPLOY_VERSION__
     */
    public function prompt(string $prompt, array $options = []): Response
    {
        $requestData = $this->buildRequestPayload($prompt, $options);
        
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
     * Ask method - alias for prompt for now
     *
     * @param   string  $question  The question to ask
     * @param   array   $options   Additional options
     * 
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    public function ask(string $question, array $options = []): Response
    {
        return $this->prompt($question, $options);
    }

    /**
     * Build the request payload for OpenAI API.
     *
     * @param   string  $prompt   The user prompt
     * @param   array   $options  Additional options
     * 
     * @return  array   The request payload
     * @since  __DEPLOY_VERSION__
     */
    private function buildRequestPayload(string $prompt, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->getOption('model', self::DEFAULT_MODEL),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
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
