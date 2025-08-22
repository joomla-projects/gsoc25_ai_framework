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
 * AI provider image capability interface.
 *
 * @since  __DEPLOY_VERSION__
 */
interface ImageInterface
{
    /**
     * Generate an image from the text prompt given to the AI provider.
     *
     * @param   string  $prompt     The text prompt describing the desired image
     * @param   array   $options   An associative array of options to send with the request.
     *
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    public function generateImage(string $prompt, array $options = []): Response;

    /**
     * Modify an existing image from the text prompt given to the AI provider.
     *
     * @param   string  $imagePath  Path to the image file to modify.
     * @param   string  $prompt     Text description of the desired modifications
     * @param   array   $options   An associative array of options to send with the request.
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    // public function editImage(string $imagePath, string $prompt, array $options = []): Response;

    /**
     * Create alternative versions of an image from the text prompt given to the AI provider.
     *
     * @param   string  $imagePath  Path to the source image file.
     * @param   array   $options   An associative array of options to send with the request.
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    // public function createImageVariations(string $imagePath, array $options = []): Response;
}
