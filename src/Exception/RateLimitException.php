<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  Copyright (C) 2025 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Exception;

/**
 * Exception thrown when the API provider indicates that too many requests have been sent in a given time period.
 *
 * @since __DEPLOY_VERSION__
 */
class RateLimitException extends AIException
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
        $errorType = $errorData['type'] ?? $errorData['error']['type'] ?? 'Rate Limit Exceeded';

        $detailedMessage = $this->buildDetailedMessage($provider, $errorType, $message, $httpStatusCode, $providerErrorCode);

        parent::__construct($detailedMessage, $provider, $context, null, $httpStatusCode, $providerErrorCode);
    }

    /**
     * Get comprehensive rate limit error details in one formatted message.
     *
     * @return  string  Complete error information including provider, status, type, code, message, and retry info
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getRateLimitErrorDetails(): string
    {
        return $this->getMessage();
    }
}
