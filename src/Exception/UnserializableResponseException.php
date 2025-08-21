<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Exception;

/**
 * Exception for unserializable or malformed responses from AI providers.
 * 
 * Handles cases where the API response cannot be properly parsed or processed,
 * such as invalid JSON, unexpected response structure, corrupted data, or empty responses.
 * 
 * @since  __DEPLOY_VERSION__
 */
class UnserializableResponseException extends AIException
{
    /**
     * Constructor
     *
     * @param   string  $provider         The AI provider name
     * @param   string  $rawResponse      Raw response content that couldn't be parsed
     * @param   string  $parseError       The specific parsing error message
     * @param   int     $httpStatusCode   HTTP status code (default 422)
     * @param   string|int|null  $providerErrorCode  Provider-specific error code
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct(string $provider, string $rawResponse, string $parseError = '', int $httpStatusCode = 422, string|int|null $providerErrorCode = null)
    {
        $context = [
            'raw_response' => $rawResponse,
            'parse_error' => $parseError,
        ];
        $errorType = 'Response Parsing';
        $message = $parseError ?: (empty($rawResponse) ? 'Received empty response from provider' : 'Unable to parse response');

        $detailedMessage = $this->buildDetailedMessage($provider, $errorType, $message, $httpStatusCode, $providerErrorCode);

        parent::__construct($detailedMessage, $provider, $context, null, $httpStatusCode, $providerErrorCode);
    }

    /**
     * Get comprehensive response parsing error details in one formatted message.
     *
     * @return  string  Complete error information including provider, status, type, parsing details, and response info
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getResponseErrorDetails(): string
    {
        return $this->getMessage();
    }
}
