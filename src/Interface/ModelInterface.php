<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Interface;

/**
 * Model management interface for AI providers.
 *
 * @since  __DEPLOY_VERSION__
 */
interface ModelInterface
{
    /**
     * Get all available models for this provider.
     *
     * @return  array  Array of available model names
     * @since   __DEPLOY_VERSION__
     */
    public function getAvailableModels(): array;

    /**
     * Get models that support chat capability.
     *
     * @return  array  Array of chat capable model names
     * @since   __DEPLOY_VERSION__
     */
    public function getChatModels(): array;

    /**
     * Get models that support vision capability.
     *
     * @return  array  Array of vision capable model names
     * @since   __DEPLOY_VERSION__
     */
    public function getVisionModels(): array;

    /**
     * Get models that support image generation capability.
     *
     * @return  array  Array of image capable model names
     * @since   __DEPLOY_VERSION__
     */
    // public function getImageModels(): array;

    /**
     * Check if a model supports a specific capability.
     *
     * @param   string  $model       The model name to check
     * @param   string  $capability  The capability to check (chat, image, audio)
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    public function isModelCapable(string $model, string $capability): bool;
}
