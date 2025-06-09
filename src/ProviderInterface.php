<?php

/**
 * Part of the Joomla Framework Http Package
 *
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
     *
     */
    public static function isSupported();

    /**
     * Method to get the name of the AI provider.
     *
     * @return  string  The name of the AI provider.
     *
     */
    public function getName();

    /**
     * Send a prompt to the AI provider and return a Response object with the response.
     * 
     * @param   string  $prompt     The prompt to send to the AI provider.
     * @param   array   $options    An associative array of options to send with the request.
     * @return  Response
     * 
     */
    public function prompt($prompt, $options = []);

    /**
     * Ask a question to the AI provider and return a Response object with the response.
     * 
     * @param   string  $question     The question to send to the AI provider.
     * @param   array   $options    An associative array of options to send with the request.
     * @return  Response
     * 
     */
    public function ask($question, $options = []);

}
