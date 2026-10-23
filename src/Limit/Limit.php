<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Limit;

use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\LimitExceededException;
use Joomla\AI\Response\Response;

/**
 * A usage limit checked before requests are sent: at most $max requests or tokens within
 * any $window seconds (sliding window).
 *
 * Usage is kept in small JSON files, locked while they are updated, so a limit holds across
 * web requests and processes. Create one with Limit::requests() or Limit::tokens().
 *
 * @since  __DEPLOY_VERSION__
 */
final class Limit
{
    private const REQUESTS = 'requests';
    private const TOKENS = 'tokens';

    /**
     * Directory for the usage files
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    private string $directory;

    /**
     * Constructor.
     *
     * @param   string           $type       self::REQUESTS or self::TOKENS
     * @param   int              $max        Maximum units in the window
     * @param   int              $window     Window length in seconds
     * @param   string|null      $directory  Directory for the usage files
     * @param   string|\Closure  $key        Scope of the limit
     * @param   \Closure|null    $clock      Returns the current time as a float (for tests)
     *
     * @throws  InvalidArgumentException  If max or window is not positive
     * @throws  \RuntimeException         If the directory cannot be created
     * @since   __DEPLOY_VERSION__
     */
    private function __construct(
        private string $type,
        private int $max,
        private int $window,
        ?string $directory,
        private string|\Closure $key,
        private ?\Closure $clock
    ) {
        if ($max < 1 || $window < 1) {
            throw InvalidArgumentException::invalidParameter('limit', [$max, $window], 'limit', 'Limit maximum and window must be positive.');
        }

        $this->directory = $directory ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'joomla-ai-limits';

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Cannot create limit directory: ' . $this->directory);
        }
    }

    /**
     * Limit the number of model calls (chat, generate, embeddings, images, audio).
     *
     * Helper calls such as moderation checks or model listings are blocked once the limit is
     * reached but are not counted.
     *
     * @param   int              $max        Maximum calls in the window
     * @param   int              $seconds    Window length in seconds (60, 3600, 86400, ...)
     * @param   string|null      $directory  Writable directory for the usage files; the system temp
     *                                       directory by default. Use a shared directory to share a
     *                                       limit between servers.
     * @param   string|\Closure  $key        Scope of the limit, or fn (): string,
     *                                       e.g. fn () => 'user:' . $userId
     * @param   \Closure|null    $clock      Returns the current time as a float (for tests)
     *
     * @return  self
     * @since   __DEPLOY_VERSION__
     */
    public static function requests(
        int $max,
        int $seconds,
        ?string $directory = null,
        string|\Closure $key = 'global',
        ?\Closure $clock = null
    ): self {
        return new self(self::REQUESTS, $max, $seconds, $directory, $key, $clock);
    }

    /**
     * Limit the tokens (input + output, as reported by the provider).
     *
     * Token counts are only known after a response, so the request that crosses the limit
     * finishes; the following requests are blocked until usage leaves the window.
     *
     * @param   int              $max        Maximum tokens in the window
     * @param   int              $seconds    Window length in seconds
     * @param   string|null      $directory  Writable directory for the usage files; the system temp
     *                                       directory by default
     * @param   string|\Closure  $key        Scope of the limit, or fn (): string
     * @param   \Closure|null    $clock      Returns the current time as a float (for tests)
     *
     * @return  self
     * @since   __DEPLOY_VERSION__
     */
    public static function tokens(
        int $max,
        int $seconds,
        ?string $directory = null,
        string|\Closure $key = 'global',
        ?\Closure $clock = null
    ): self {
        return new self(self::TOKENS, $max, $seconds, $directory, $key, $clock);
    }

    /**
     * Throw when the request must not be sent. Called by the provider before each request.
     *
     * @param   array  $request  Request information (provider, method, url, ...)
     *
     * @throws  LimitExceededException
     * @since   __DEPLOY_VERSION__
     */
    public function check(array $request): void
    {
        $now = $this->now();
        $entries = $this->entries($request, $now);
        $used = array_sum(array_column($entries, 1));

        if ($used < $this->max) {
            return;
        }

        $key = $this->resolveKey($request);

        throw new LimitExceededException(
            (string) ($request['provider'] ?? ''),
            sprintf('Limit of %d %s per %d seconds reached for "%s".', $this->max, $this->type, $this->window, $key),
            $this->retryAfter($entries, $used, $now),
            ['type' => $this->type, 'key' => $key, 'max' => $this->max, 'window' => $this->window, 'used' => $used]
        );
    }

    /**
     * Record a model response. Called by the provider after each response.
     *
     * @param   array     $request   Request information
     * @param   Response  $response  The response, with normalised usage
     *
     * @since   __DEPLOY_VERSION__
     */
    public function record(array $request, Response $response): void
    {
        $units = $this->type === self::REQUESTS ? 1 : (int) ($response->getUsage()['total_tokens'] ?? 0);

        if ($units < 1) {
            return;
        }

        $now = $this->now();

        $this->update($request, function (array $entries) use ($now, $units): array {
            $entries = $this->prune($entries, $now);
            $entries[] = [$now, $units];

            return $entries;
        });
    }

    /**
     * Get the requests or tokens used in the current window.
     *
     * @param   array  $request  Request information (only needed for callable keys)
     *
     * @return  int
     * @since   __DEPLOY_VERSION__
     */
    public function getUsed(array $request = []): int
    {
        return (int) array_sum(array_column($this->entries($request, $this->now()), 1));
    }

    /**
     * Read the entries still inside the window (and drop the rest from the store).
     */
    private function entries(array $request, float $now): array
    {
        return $this->update($request, fn (array $entries): array => $this->prune($entries, $now));
    }

    /**
     * Read, change and write the usage entries for a request's scope, with the file locked.
     *
     * @param   array     $request  Request information
     * @param   callable  $change   Receives the stored entries and returns the new ones
     *
     * @return  array  The new entries
     * @throws  \RuntimeException  If the usage file cannot be opened
     * @since   __DEPLOY_VERSION__
     */
    private function update(array $request, callable $change): array
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $this->storeKey($request)) . '.json';
        $handle = @fopen($file, 'c+');

        if ($handle === false) {
            throw new \RuntimeException('Cannot open limit file: ' . $file);
        }

        try {
            flock($handle, LOCK_EX);

            $stored = json_decode((string) stream_get_contents($handle), true);
            $entries = $change(is_array($stored) ? $stored : []);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($entries));
            fflush($handle);

            return $entries;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function prune(array $entries, float $now): array
    {
        return array_values(array_filter($entries, fn ($entry): bool => is_array($entry) && $entry[0] > $now - $this->window));
    }

    /**
     * Seconds until enough of the oldest usage leaves the window to go below the maximum.
     */
    private function retryAfter(array $entries, int|float $used, float $now): float
    {
        usort($entries, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        foreach ($entries as [$time, $units]) {
            $used -= $units;

            if ($used < $this->max) {
                return max(0.0, $time + $this->window - $now);
            }
        }

        return (float) $this->window;
    }

    private function resolveKey(array $request): string
    {
        return is_string($this->key) ? $this->key : (string) ($this->key)($request);
    }

    private function storeKey(array $request): string
    {
        return 'joomla-ai:' . $this->type . ':' . $this->window . ':' . $this->resolveKey($request);
    }

    private function now(): float
    {
        return $this->clock !== null ? (float) ($this->clock)() : microtime(true);
    }
}
