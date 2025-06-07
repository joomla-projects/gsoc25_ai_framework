<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  Copyright (C) 2005 - 2025 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
*/

namespace Joomla\AI;

/**
 * AI response data object class.
 *
 * @since  __DEPLOY_VERSION__
 */
class Response
 {
    /**
     * The content of the response.
     *
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private $content;

    /**
     * The status code of the response.
     *
     * @var int
     * @since  __DEPLOY_VERSION__
     */
    private $statusCode;

    /**
     * The metadata of the response.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private $metadata;

    /**
     * The provider of the response.
     *
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private $provider;

    /**
     * Constructor.
     *
     * @param   string  $content   The content of the response.
     * @param   string  $provider  The provider of the response.
     * @param   array   $metadata  The metadata of the response.
     * @param   int     $status    The status code of the response.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(string $content, string $provider, array $metadata = [], int $status = 200)
    {
        $this->content = $content;
        $this->provider = $provider;
        $this->metadata = $metadata;
        $this->statusCode = $status;
    }

    /**
     * Get the content of the response.
     *
     * @return  string  The content of the response.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the metadata of the response.
     *
     * @return  array  The metadata of the response.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the provider of the response.
     *
     * @return  string  The provider of the response.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get the status code of the response.
     *
     * @return  int  The status code of the response.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Magic method to access properties of the response object.
     *
     * @param   string  $name  The name of the property to get.
     *
     * @return  mixed  The value of the property.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __get($name)
    {
        switch (strtolower($name)) {
            case 'content':
                return $this->getContent();

            case 'metadata':
                return $this->getMetadata();

            case 'provider':
                return $this->getProvider();

            case 'statuscode':
                return $this->getStatusCode();

            default:
                $trace = debug_backtrace();

                trigger_error(
                    sprintf(
                        'Undefined property via __get(): %s in %s on line %s',
                        $name,
                        $trace[0]['file'],
                        $trace[0]['line']
                    ),
                    E_USER_NOTICE
                );

                break;
        }
    }
}