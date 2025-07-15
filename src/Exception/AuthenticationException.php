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
        
        $message = $this->buildDetailedMessage($provider, $errorData, $httpStatusCode);
        
        parent::__construct($message, $provider, $context, null, $httpStatusCode, $providerErrorCode);
    }

    /**
     * Extract message from error data
     * 
     * @param   array   $errorData  Raw error data from provider response
     * @param   string  $provider   The AI provider name
     * 
     * @since  __DEPLOY_VERSION__
     */
    private function extractMessage(array $errorData, string $provider): string
    {
        $message = $errorData['message'] ??
                   $errorData['error']['message'] ??
                   $errorData['error'] ??
                   null;

        if (is_array($message)) {
            $message = implode('. ', $message);
        }

        if (!$message) {
            $code = $errorData['code'] ?? $errorData['error']['code'] ?? null;
            $message = $code ? "Error: $code" : 'Authentication error';
        }

        return "{$provider}: {$message}";
    }

    /**
     * Build detailed message that includes all relevant information
     * 
     * @param   string  $provider         The AI provider name
     * @param   array   $errorData        Raw error data from provider response
     * @param   int     $httpStatusCode   HTTP status code
     * 
     * @return  string  Detailed error message
     * 
     * @since  __DEPLOY_VERSION__
     */
    private function buildDetailedMessage(string $provider, array $errorData, int $httpStatusCode): string
    {
        $details = [];
        
        $details[] = "Provider: {$provider}";
        
        $details[] = "HTTP Status: {$httpStatusCode}";
        
        $errorType = $errorData['type'] ?? $errorData['error']['type'] ?? 'Unknown';
        $details[] = "Error Type: {$errorType}";
        
        $errorCode = $errorData['code'] ?? $errorData['error']['code'] ?? 'Unknown';
        $details[] = "Error Code: {$errorCode}";
        
        // $isRetryable = $httpStatusCode === 429;
        // $details[] = "Retryable: " . ($isRetryable ? 'Yes' : 'No');
        
        // if ($isRetryable) {
        //     $details[] = "Retry Delay: 60 seconds";
        // }

        $originalMessage = $this->extractMessage($errorData, $provider);
        $details[] = "Message: {$originalMessage}";
        
        return implode("\n", $details);
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
