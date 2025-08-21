<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  Copyright (C) 2025 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Interface;

/**
 * Interface for AI providers that support content moderation.
 *
 * @since  __DEPLOY_VERSION__
 */
interface ModerationInterface
{
    /**
     * Moderate content using the provider's moderation system.
     *
     * @param   string|array  $input    Text/Image input(s) to moderate
     * @param   array         $options  Additional options for moderation
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    public function moderate($input, array $options = []): array;

    /**
     * Check if content is flagged by the moderation system.
     *
     * @param   array  $moderationResult  Result from moderate() method
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    public function isContentFlagged(array $moderationResult): bool;
}
