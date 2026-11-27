<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Exception;

/**
 * Thrown when a limit configured in the framework blocks a request before it is sent.
 *
 * This is not a provider rate limit (see RateLimitException): no request reached the provider.
 *
 * @since  __DEPLOY_VERSION__
 */
class LimitExceededException extends AIException
{
    /**
     * Constructor.
     *
     * @param   string  $provider    The AI provider name
     * @param   string  $message     What was exceeded
     * @param   float   $retryAfter  Seconds until the request would be allowed
     * @param   array   $context     Limit details (type, key, max, window, used)
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct(string $provider, string $message, private float $retryAfter, array $context = [])
    {
        parent::__construct($message, $provider, $context, null, 429, 'LIMIT_EXCEEDED');
    }

    /**
     * Seconds until the limit allows the request again.
     *
     * @return  float
     * @since   __DEPLOY_VERSION__
     */
    public function getRetryAfter(): float
    {
        return $this->retryAfter;
    }
}
