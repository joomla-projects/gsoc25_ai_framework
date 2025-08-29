<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  Copyright (C) 2025 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI;

use Joomla\AI\Provider\OpenAIProvider;
use Joomla\AI\Provider\AnthropicProvider;
use Joomla\AI\Provider\OllamaProvider;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;

/**
 * AI Factory class for creating AI instances
 *
 * @since  __DEPLOY_VERSION__
 */
class AIFactory
{
    /**
     * Available AI providers
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const PROVIDERS = [
        'openai' => OpenAIProvider::class,
        'anthropic' => AnthropicProvider::class,
        'ollama' => OllamaProvider::class,
    ];

    /**
     * Create an AI instance with the specified provider
     *
     * @param   string  $provider  The provider name (openai, anthropic, ollama)
     * @param   array   $options   Provider configuration options
     *
     * @return  AI
     * @throws  InvalidArgumentException
     * @throws  ProviderException
     * @since   __DEPLOY_VERSION__
     */
    public static function getAI(string $provider, array $options = []): AI
    {
        $provider = strtolower($provider);

        if (!isset(self::PROVIDERS[$provider])) {
            throw new ProviderException(
                $provider,
                [
                    'message' => 'Unknown AI provider: ' . $provider . '. Available providers: ' . implode(', ', array_keys(self::PROVIDERS)),
                    'requested_provider' => $provider,
                    'available_providers' => array_keys(self::PROVIDERS)
                ],
                null,
                'INVALID_PROVIDER'
            );
        }

        $providerClass = self::PROVIDERS[$provider];

        try {
            $providerInstance = new $providerClass($options);
            return new AI($providerInstance);
        } catch (\Exception $e) {
            throw new ProviderException(
                $provider,
                [
                    'message' => 'Failed to create ' . $provider . ' provider: ' . $e->getMessage(),
                    'provider' => $provider,
                    'original_error' => $e->getMessage()
                ],
                null,
                'PROVIDER_CREATION_FAILED'
            );
        }
    }

    /**
     * Get available provider names
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    public static function getAvailableProviders(): array
    {
        return array_keys(self::PROVIDERS);
    }

    /**
     * Check if a provider is available
     *
     * @param   string  $provider  The provider name
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    public static function isProviderAvailable(string $provider): bool
    {
        return isset(self::PROVIDERS[strtolower($provider)]);
    }
}
