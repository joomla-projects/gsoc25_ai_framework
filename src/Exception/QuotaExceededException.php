<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  Copyright (C) 2025 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Exception;

/**
 * Exception thrown when the API provider indicates that billing limits, credit limits, or usage caps have been reached.
 *
 * @since __DEPLOY_VERSION__
 */
class QuotaExceededException extends AIException
{
    /**
     * Constructor.
     *
     * @param   string        $provider          The AI provider name
     * @param   int|null      $httpStatusCode    The actual HTTP status code from response
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct(string $provider, array $errorData, $httpStatusCode)
    {
        $context = ['error_data' => $errorData];
        $providerErrorCode = $errorData['code'] ?? $errorData['error']['code'] ?? null;
        $errorType = $errorData['type'] ?? $errorData['error']['type'] ?? 'Quota Exceeded';
        $message = $errorData['message'] ?? $errorData['error']['message'] ?? $errorData['error'] ?? null;

        if (is_array($message)) {
            $message = implode('. ', $message);
        }
        if (!$message) {
            $code = $providerErrorCode;
            $message = $code ? "Error: $code" : 'Quota exceeded error';
        }

        $detailedMessage = $this->buildDetailedMessage($provider, $errorType, $message, $httpStatusCode, $providerErrorCode);

        parent::__construct($detailedMessage, $provider, $context, null, $httpStatusCode, $providerErrorCode);
    }

    /**
     * Get comprehensive quota exceeded error details in one formatted message.
     *
     * @return  string  Complete error information including provider, status, type, code, message, and retry info
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getQuotaExceededErrorDetails(): string
    {
        return $this->getMessage();
    }
}
