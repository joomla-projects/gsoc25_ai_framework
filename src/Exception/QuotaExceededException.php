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
     * @param   string        $message           The error message
     * @param   array         $context           Additional context data
     * @param   int|null      $httpStatusCode    The actual HTTP status code from response
     * @param   string|null   $providerErrorCode The provider-specific error code
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct(string $provider, string $message, array $context = [], ?int $httpStatusCode = null, ?string $providerErrorCode = null)
    {
        $errorType = $errorData['type'] ?? $errorData['error']['type'] ?? 'Quota Exceeded';

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
