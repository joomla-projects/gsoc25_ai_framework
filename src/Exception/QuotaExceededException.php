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
        parent::__construct($message, $provider, $context, null, $httpStatusCode, $providerErrorCode);
    }
}
