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
use Joomla\Http\HttpFactory;

/**
 * AI Factory class for creating AI instances.
 *
 * Instantiable so it can be registered in a DI container. Custom providers can be
 * added with registerProvider() or passed directly as a class name to getAI().
 *
 * @since  __DEPLOY_VERSION__
 */
class AIFactory
{
    /**
     * Built-in AI providers
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const DEFAULT_PROVIDERS = [
        'openai' => OpenAIProvider::class,
        'anthropic' => AnthropicProvider::class,
        'ollama' => OllamaProvider::class,
    ];

    /**
     * Registered providers, name => class name
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private array $providers;

    /**
     * HTTP factory passed to every provider created by this factory
     *
     * @var HttpFactory|null
     * @since  __DEPLOY_VERSION__
     */
    private ?HttpFactory $httpFactory;

    /**
     * Constructor.
     *
     * @param   HttpFactory|null  $httpFactory  HTTP factory to pass to providers
     * @param   array             $providers    Additional providers, name => class name
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct(?HttpFactory $httpFactory = null, array $providers = [])
    {
        $this->httpFactory = $httpFactory;
        $this->providers = self::DEFAULT_PROVIDERS;

        foreach ($providers as $name => $class) {
            $this->registerProvider($name, $class);
        }
    }

    /**
     * Register a provider under a name, or replace a built-in one.
     *
     * @param   string  $name   The provider name
     * @param   string  $class  Class name extending AbstractProvider
     *
     * @return  $this
     * @throws  InvalidArgumentException  If the class is not a provider
     * @since   __DEPLOY_VERSION__
     */
    public function registerProvider(string $name, string $class): static
    {
        $this->assertProviderClass($class);
        $this->providers[strtolower($name)] = $class;

        return $this;
    }

    /**
     * Create an AI instance with the specified provider
     *
     * @param   string  $provider  A registered provider name or a provider class name
     * @param   array   $options   Provider configuration options
     *
     * @return  AI
     * @throws  InvalidArgumentException
     * @throws  ProviderException
     * @since   __DEPLOY_VERSION__
     */
    public function getAI(string $provider, array $options = []): AI
    {
        $providerClass = $this->providers[strtolower($provider)] ?? null;

        if ($providerClass === null && class_exists($provider)) {
            $this->assertProviderClass($provider);
            $providerClass = $provider;
        }

        if ($providerClass === null) {
            throw new ProviderException(
                $provider,
                [
                    'message' => 'Unknown AI provider: ' . $provider . '. Available providers: ' . implode(', ', $this->getAvailableProviders()),
                    'requested_provider' => $provider,
                    'available_providers' => $this->getAvailableProviders()
                ],
                null,
                'INVALID_PROVIDER'
            );
        }

        try {
            $providerInstance = new $providerClass($options, $this->httpFactory);
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
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Check if a provider is available
     *
     * @param   string  $provider  The provider name
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    public function isProviderAvailable(string $provider): bool
    {
        return isset($this->providers[strtolower($provider)]);
    }

    /**
     * Ensure a class name is a usable provider.
     *
     * @param   string  $class  The class name
     *
     * @throws  InvalidArgumentException
     * @since   __DEPLOY_VERSION__
     */
    private function assertProviderClass(string $class): void
    {
        if (!class_exists($class) || !is_subclass_of($class, AbstractProvider::class)) {
            throw InvalidArgumentException::invalidParameter('provider', $class, 'factory', 'Provider class must exist and extend ' . AbstractProvider::class . '.');
        }
    }
}
