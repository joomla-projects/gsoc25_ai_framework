<?php

/**
 * Part of the Joomla Framework AI Package
 *
 */

namespace Joomla\AI;

use Joomla\Http\HttpFactory;

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
     */
    protected $options;

    /**
     * The HTTP factory instance.
     *
     * @var    HttpFactory
     */
    protected $httpFactory;

    /**
     * Constructor.
     *
     * @param   array|\ArrayAccess  $options  Provider options array.
     * @param   HttpFactory   $httpFactory  The http factory
     */
    public function __construct($options = [], ?HttpFactory $httpFactory)
    {
        // To do: Exception Handeling Code
        // Validate provider is suported
        // Validate that $options is an array or implements ArrayAccess
        if (!\is_array($options) && !($options instanceof \ArrayAccess)) {
            throw new \InvalidArgumentException(
                'The options param must be an array or implement the ArrayAccess interface.'
            );
        }

        $this->options = $options;
        $this->httpFactory = $httpFactory;
    }

    /**
     * Get an option from the AI provider.
     * 
     * @param   string  $key      The name of the option to get.
     * @param   mixed   $default  The default value if the option is not set.
     * 
     * @return  mixed The option value.
     */
    protected function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
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
        } catch (\Exception $e) {
            throw new \Exception('AI API GET request failed: ' . $e->getMessage(), 0, $e);
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
        } catch (\Exception $e) {
            throw new \Exception('AI API POST request failed: ' . $e->getMessage(), 0, $e);
        }

        return $response;
    }

    /**
     * Check response code and handle errors
     *
     * @param   \Joomla\Http\Response  $response  HTTP response
     *
     * @return  boolean  True if successful
     * @throws  \Exception
     */
    protected function validateResponse($response): bool
    {
        if ($response->code !== 200) {
            // To Do: Error Handling Code
            throw new \Exception('AI API Error: HTTP ' . $response->code . ' - ' . $response->body);
        }

        return true;
    }

    /**
     * Parse JSON response safely
     */
    protected function parseJsonResponse(string $jsonString): array
    {
        $decoded = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        return $decoded;
    }

}
