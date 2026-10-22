<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Http;

use Joomla\Http\Response as HttpResponse;

/**
 * Transport that delivers an HTTP response body incrementally.
 *
 * @since  __DEPLOY_VERSION__
 */
interface StreamTransportInterface
{
    /**
     * Send a request and yield the response body as it arrives.
     *
     * Body chunks are only yielded for 2xx responses. For any other status the body is
     * buffered instead, so the caller can turn it into the right exception.
     *
     * @param   string    $method   HTTP method
     * @param   string    $url      Request URL
     * @param   string    $body     Request body
     * @param   array     $headers  Request headers, name => value
     * @param   int|null  $timeout  Timeout in seconds
     *
     * @return  \Generator<int, string, mixed, HttpResponse>  Yields raw body chunks; returns the response
     *                                                         (status, headers, and the body for non-2xx)
     * @throws  \RuntimeException  On transport failure
     * @since   __DEPLOY_VERSION__
     */
    public function stream(string $method, string $url, string $body, array $headers = [], ?int $timeout = null): \Generator;
}
