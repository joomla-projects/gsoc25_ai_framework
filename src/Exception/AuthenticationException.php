<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Exception;

/**
 * Exception for authentication and authorization errors from AI providers.
 *
 * @since  __DEPLOY_VERSION__
 */
class AuthenticationException extends AIException
{
    /**
     * Constructor
     *
     * @param   string  $provider         The AI provider name
     * @param   array   $errorData        Raw error data from provider response
     * @param   int     $httpStatusCode   HTTP status code
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct(string $provider, array $errorData, int $httpStatusCode)
    {
        $context = ['error_data' => $errorData];
        $providerErrorCode = $errorData['code'] ?? $errorData['error']['code'] ?? null;
        $errorType = $errorData['type'] ?? $errorData['error']['type'] ?? 'Authentication';
        $message = $errorData['message'] ?? $errorData['error']['message'] ?? $errorData['error'] ?? null;
        if (is_array($message)) {
            $message = implode('. ', $message);
        }
        if (!$message) {
            $code = $providerErrorCode;
            $message = $code ? "Error: $code" : 'Authentication error';
        }

        $detailedMessage = $this->buildDetailedMessage($provider, $errorType, $message, $httpStatusCode, $providerErrorCode);
        parent::__construct($detailedMessage, $provider, $context, null, $httpStatusCode, $providerErrorCode);
    }

    /**
     * Get comprehensive authentication error details in one formatted message.
     *
     * @return  string  Complete error information including provider, status, type, code, message, and retry info
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getAuthenticationErrorDetails(): string
    {
        return $this->getMessage();
    }
}
