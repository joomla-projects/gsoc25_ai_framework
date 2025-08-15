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
 * AI provider chat capability interface.
 *
 * @since  __DEPLOY_VERSION__
 */
interface ChatInterface
{
    /**
     * Generate a chat response from the AI provider.
     *
     * @param   string  $message     The message to send to the AI provider.
     * @param   array   $options    An associative array of options to send with the request.
     *
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    public function chat(string $message, array $options = []): Response;

    /**
     * Generate chat completion with vision capability from the AI provider.
     *
     * @param   string  $message   The chat message about the image to send to the AI provider.
     * @param   string  $image     Image URL or base64 encoded image.
     * @param   array   $options   An associative array of options to send with the request.
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function vision(string $message, string $image, array $options = []): Response;
}
