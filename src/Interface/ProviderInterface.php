<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Interface;

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
     * Get all available models for this provider.
     *
     * @return  array  Array of available model names
     * @since   __DEPLOY_VERSION__
     */
    public function getAvailableModels(): array;
}
