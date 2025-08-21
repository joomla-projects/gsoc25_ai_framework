<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Exception;

/**
 * Base exception class for the AI framework.
 *
 * @since  __DEPLOY_VERSION__
 */
class AIException extends \Exception
{
    /**
     * The AI provider that caused the exception.
     *
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    protected string $provider = '';

    /**
     * Additional context information about the error.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    protected array $context = [];

    /**
     * HTTP status code if applicable.
     *
     * @var int|null
     * @since  __DEPLOY_VERSION__
     */
    protected ?int $httpStatusCode = null;

    /**
     * Provider-specific error code.
     *
     * @var string|null
     * @since  __DEPLOY_VERSION__
     */
    protected ?string $providerErrorCode = null;

    /**
     * Constructor.
     *
     * @param   string          $message            The exception message
     * @param   string          $provider           The AI provider name
     * @param   array           $context            Additional context information
     * @param   \Throwable|null $previous           Previous exception
     * @param   int|null        $httpStatusCode     HTTP status code if applicable
     * @param   string|null     $providerErrorCode  Provider-specific error code
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(string $message, string $provider, array $context, ?\Throwable $previous, ?int $httpStatusCode, ?string $providerErrorCode) {
        parent::__construct($message, 0, $previous);
        $this->provider = $provider;
        $this->context = $context;
        $this->httpStatusCode = $httpStatusCode;
        $this->providerErrorCode = $providerErrorCode;
    }

    /**
     * Get the provider name that caused this exception.
     *
     * @return  string  The provider name
     * @since  __DEPLOY_VERSION__
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get additional context information about the error.
     *
     * @return  array  Context information
     * @since  __DEPLOY_VERSION__
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get the HTTP status code if applicable.
     *
     * @return  int|null  HTTP status code or null
     * @since  __DEPLOY_VERSION__
     */
    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    /**
     * Get the provider-specific error code.
     *
     * @return  string|null  Provider error code or null
     * @since  __DEPLOY_VERSION__
     */
    public function getProviderErrorCode(): ?string
    {
        return $this->providerErrorCode;
    }

    /**
     * Check if this exception is retryable.
     *
     * @return  bool  True if retryable, false otherwise
     * @since  __DEPLOY_VERSION__
     */
    public function isRetryable(): bool
    {
        return false;
    }

    /**
     * Build a detailed error message for all AI exceptions.
     *
     * @param   string        $provider           The AI provider name
     * @param   string        $errorType          The error type (e.g. Authentication, Rate Limit, etc.)
     * @param   string        $message            The error message
     * @param   int|null      $httpStatusCode     HTTP status code
     * @param   string|int|null $providerErrorCode Provider-specific error code
     *
     * @return  string  Detailed error message
     */
    protected function buildDetailedMessage(string $provider, string $errorType, string $message, ?int $httpStatusCode = null, string|int|null $providerErrorCode = null): string {
        $details = [];
        $details[] = "Provider: {$provider}";
        $details[] = "HTTP Status: " . ($httpStatusCode ?? 'Unknown');
        $details[] = "Error Type: {$errorType}";
        if ($providerErrorCode) {
            $details[] = "Error Code: {$providerErrorCode}";
        }
        $details[] = "Message: {$message}";
        return implode("\n", $details);
    }
}
