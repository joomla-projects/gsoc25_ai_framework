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
 * AI provider embedding capability interface.
 *
 * @since  __DEPLOY_VERSION__
 */
interface EmbeddingInterface
{
    /**
     * Create embeddings for the given input text(s).
     *
     * @param   string|array  $input    Text string or array of texts to embed
     * @param   string        $model    The embedding model to use
     * @param   array         $options  Additional options
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function createEmbeddings($input, string $model, array $options = []): Response;

    /**
     * Get available embedding models for this provider.
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    public function getEmbeddingModels(): array;
}
