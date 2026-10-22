<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  Copyright (C) 2025 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI;

use Joomla\AI\Conversation\Conversation;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Interface\ChatInterface;

/**
 * AI client class.
 *
 * @since  __DEPLOY_VERSION__
 */
class AI
{
    /**
     * The AI provider object to use for AI requests.
     *
     * @var AbstractProvider
     * @since  __DEPLOY_VERSION__
     */
    protected AbstractProvider $provider;

    /**
     * Constructor.
     *
     * @param   AbstractProvider  $provider  The AI provider object.
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct(AbstractProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Magic method to delegate all calls to the provider.
     *
     * @param   string  $method     The method name being called
     * @param   array   $arguments  The arguments passed to the method
     *
     * @return  mixed
     * @throws  ProviderException  If the provider doesn't have the method
     * @since   __DEPLOY_VERSION__
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists($this->provider, $method)) {
            return $this->provider->$method(...$arguments);
        }

        throw new ProviderException(
            $this->provider->getName(),
            ['message' => 'Method ' . $method . ' is not supported by ' . $this->provider->getName()],
        );
    }

    /**
     * Start a conversation that remembers previous messages.
     *
     * @param   array  $options  Default chat options (system, model, tools, max_turns, ...)
     *
     * @return  Conversation
     * @throws  ProviderException  If the provider cannot chat
     * @since   __DEPLOY_VERSION__
     */
    public function conversation(array $options = []): Conversation
    {
        if (!$this->provider instanceof ChatInterface) {
            throw new ProviderException($this->provider->getName(), ['message' => $this->provider->getName() . ' does not support chat.']);
        }

        return new Conversation($this->provider, $options);
    }

    /**
     * Get the underlying provider instance.
     *
     * @return  AbstractProvider
     * @since   __DEPLOY_VERSION__
     */
    public function getProvider(): AbstractProvider
    {
        return $this->provider;
    }
}
