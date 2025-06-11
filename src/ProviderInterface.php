<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  ___Copyright___
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI;

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
