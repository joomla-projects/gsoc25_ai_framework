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
 * AI provider class interface.
 *
 * @since  __DEPLOY_VERSION__
 */
interface ProviderInterface
{
    /**
     * Method to check if AI provider is available for using
     *
     * @return  boolean  True if available else false
     * @since  __DEPLOY_VERSION__
     */
    public static function isSupported(): bool;

    /**
     * Method to get the name of the AI provider.
     *
     * @return  string  The name of the AI provider.
     * @since  __DEPLOY_VERSION__
     */
    public function getName(): string;

    // Should be a smart router in future versions.
    /**
     * Send a prompt to the AI provider and return a Response object with the response.
     * 
     * @param   string  $prompt     The prompt to send to the AI provider.
     * @param   array   $options    An associative array of options to send with the request.
     * 
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    public function prompt(string $prompt, array $options = []): Response;

    // Does the same as prompt. Need to be checked if removed, or kept for option parameter
    /**
     * Ask a question to the AI provider and return a Response object with the response.
     * 
     * @param   string  $question     The question to send to the AI provider.
     * @param   array   $options    An associative array of options to send with the request.
     * 
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    public function ask(string $question, array $options = []): Response;
}
