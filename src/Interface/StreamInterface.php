<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Interface;

use Joomla\AI\Response\Response;

/**
 * Interface for AI providers that can stream chat responses.
 *
 * @since  __DEPLOY_VERSION__
 */
interface StreamInterface
{
    /**
     * Stream a chat response as it is generated.
     *
     * Yields text deltas as they arrive. When the generator finishes, getReturn() gives the
     * complete Response with the full text, normalised usage and finish reason.
     *
     * @param   string  $message  The user message
     * @param   array   $options  The same options as chat()
     *
     * @return  \Generator<int, string, mixed, Response>
     * @since   __DEPLOY_VERSION__
     */
    public function chatStream(string $message, array $options = []): \Generator;
}
