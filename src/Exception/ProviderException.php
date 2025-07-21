<?php
/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Exception;

/**
 * Exception for provider-specific or uncategorized errors from AI providers.
 *
 * @since  __DEPLOY_VERSION__
 */
class ProviderException extends AIException
{
    /**
     * Constructor.
     *
     * @param   string             $provider           The AI provider name
     * @param   string|array       $errorData          The error message or error data array
     * @param   int|null           $httpStatusCode     HTTP status code (if available)
     * @param   string|int|null    $providerErrorCode  Provider-specific error code (if available)
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct(string $provider, string|array $errorData, ?int $httpStatusCode = null, string|int|null $providerErrorCode = null) 
    {
        $context = ['error_data' => $errorData];
        $providerErrorCode = $errorData['code'] ?? $errorData['error']['code'] ?? null;
        $errorType = $errorData['type'] ?? $errorData['error']['type'] ?? 'Unknown Error';

        if (is_array($errorData)) {
            $message = $errorData['message'] ?? $errorData['error']['message'] ?? $errorData['error'] ?? 'Unknown provider error';
        } else {
            $message = $errorData;
        }

        $detailedMessage = $this->buildDetailedMessage($provider, $errorType, $message, $httpStatusCode, $providerErrorCode);
        
        parent::__construct($detailedMessage, $provider, $context, null, $httpStatusCode, $providerErrorCode);
    }

    /**
     * Get comprehensive provider error details in one formatted message.
     *
     * @return  string
     */
    public function getProviderErrorDetails(): string
    {
        return $this->getMessage();
    }
}
