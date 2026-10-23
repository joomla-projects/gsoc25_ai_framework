<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI;

use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\LimitExceededException;
use Joomla\AI\Limit\Limit;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\RateLimitException;
use Joomla\AI\Exception\QuotaExceededException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Interface\ProviderInterface;
use Joomla\AI\Interface\ModerationInterface;
use Joomla\AI\Http\CurlStreamTransport;
use Joomla\AI\Http\StreamTransportInterface;
use Joomla\AI\Response\Response;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract provider class.
 *
 * @since  __DEPLOY_VERSION__
 */
abstract class AbstractProvider implements ProviderInterface, LoggerAwareInterface
{
    /**
     * The provider options.
     *
     * @var    array|\ArrayAccess
     * @since  __DEPLOY_VERSION__
     */
    protected array|\ArrayAccess $options;

    /**
     * The HTTP factory instance.
     *
     * @var    HttpFactory
     * @since  __DEPLOY_VERSION__
     */
    protected HttpFactory $httpFactory;

    /**
     * The default model to use for API requests.
     *
     * @var    string|null
     * @since  __DEPLOY_VERSION__
     */
    protected ?string $defaultModel = null;

    /**
     * PSR-3 logger
     *
     * @var    LoggerInterface|null
     * @since  __DEPLOY_VERSION__
     */
    private ?LoggerInterface $logger = null;

    /**
     * Hooks registered with addHook(), event => callbacks
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    private array $hooks = [];

    /**
     * Limits added with addLimit()
     *
     * @var    Limit[]
     * @since  __DEPLOY_VERSION__
     */
    private array $limits = [];

    /**
     * Resolved capability map (defaults merged with the "capabilities" option).
     *
     * @var    array|null
     * @since  __DEPLOY_VERSION__
     */
    private ?array $capabilities = null;

    /**
     * Constructor.
     *
     * @param   array|\ArrayAccess  $options      Provider options array.
     * @param   HttpFactory   $httpFactory  The http factory
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(array|\ArrayAccess $options = [], ?HttpFactory $httpFactory = null)
    {
        $this->options = $options;
        $this->httpFactory = $httpFactory ?: new HttpFactory();
    }

    /**
     * Get an option from the AI provider.
     *
     * @param   string  $key      The name of the option to get.
     * @param   mixed   $default  The default value if the option is not set.
     *
     * @return  mixed The option value.
     * @since  __DEPLOY_VERSION__
     */
    protected function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Set the default model to use for API requests.
     *
     * @param   string  $model  The model name to set as default
     * @since   __DEPLOY_VERSION__
     */
    public function setDefaultModel(string $model): static
    {
        $this->defaultModel = $model;
        return $this;
    }

    /**
     * Unset the default model, reverting to provider-specific defaults.
     *
     * @since   __DEPLOY_VERSION__
     */
    public function unsetDefaultModel(): static
    {
        $this->defaultModel = null;
        return $this;
    }

    /**
     * Get the current default model.
     *
     * @return  string|null  The current default model or null if not set
     * @since   __DEPLOY_VERSION__
     */
    public function getDefaultModel(): ?string
    {
        return $this->defaultModel;
    }

    /**
     * Get the HTTP client, configured from the "http_options" and "timeout" provider options.
     *
     * @return  \Joomla\Http\Http
     * @since   __DEPLOY_VERSION__
     */
    protected function getHttpClient(): Http
    {
        $httpOptions = (array) $this->getOption('http_options', []);

        if ($this->getOption('timeout') !== null) {
            $httpOptions['timeout'] = (int) $this->getOption('timeout');
        }

        return $this->httpFactory->getHttp($httpOptions);
    }

    /**
     * Get the transport used for streaming requests.
     * A StreamTransportInterface instance can be passed in the "stream_transport" option.
     *
     * @return  StreamTransportInterface
     * @since   __DEPLOY_VERSION__
     */
    protected function getStreamTransport(): StreamTransportInterface
    {
        $transport = $this->getOption('stream_transport');

        if ($transport instanceof StreamTransportInterface) {
            return $transport;
        }

        $options = (array) $this->getOption('http_options', []);

        if ($this->getOption('timeout') !== null) {
            $options['timeout'] = (int) $this->getOption('timeout');
        }

        return new CurlStreamTransport($options);
    }

    /**
     * Make a streaming POST request and yield the raw response body chunks.
     *
     * @param   string  $url      API endpoint URL
     * @param   string  $data     Request body
     * @param   array   $headers  HTTP headers
     *
     * @return  \Generator<int, string>
     * @throws  AuthenticationException|RateLimitException|QuotaExceededException|ProviderException  On HTTP errors
     * @since   __DEPLOY_VERSION__
     */
    protected function makeStreamingPostRequest(string $url, string $data, array $headers = []): \Generator
    {
        $request = ['provider' => $this->getName(), 'method' => 'POST', 'url' => $url, 'stream' => true];
        $maxRetries = $this->getMaxRetries();

        $this->checkLimits($request);

        for ($attempt = 0;; $attempt++) {
            $request['attempt'] = $attempt;
            $this->beforeRequest($request);
            $start = microtime(true);
            $progress = new \ArrayObject(['yielded' => false]);

            try {
                $response = yield from $this->relayChunks(
                    $this->getStreamTransport()->stream('POST', $url, $data, $headers),
                    $progress
                );
            } catch (\RuntimeException $e) {
                // A dropped connection can only be retried before any output was passed on
                if (!$progress['yielded'] && $attempt < $maxRetries) {
                    $this->retryAfter($request, $this->getBackoffDelay($attempt), $e);
                    continue;
                }

                $this->onRequestError($request, $e);

                throw $e;
            }

            $this->afterResponse($request, $response, microtime(true) - $start);

            if (!$progress['yielded'] && $attempt < $maxRetries && $this->isRetryableResponse($response)) {
                $this->retryAfter($request, $this->getRetryDelay($response, $attempt), $response);
                continue;
            }

            $this->validateWithHooks($request, $response);

            return;
        }
    }

    /**
     * Pass a transport's chunks on, recording whether any were yielded (used to decide if a retry is safe).
     *
     * @param   \Generator    $stream    Generator from StreamTransportInterface::stream()
     * @param   \ArrayObject  $progress  Gets 'yielded' => true once the first chunk is passed on
     *
     * @return  \Generator<int, string, mixed, \Joomla\Http\Response>
     * @since   __DEPLOY_VERSION__
     */
    private function relayChunks(\Generator $stream, \ArrayObject $progress): \Generator
    {
        foreach ($stream as $chunk) {
            $progress['yielded'] = true;

            yield $chunk;
        }

        return $stream->getReturn();
    }

    /**
     * Send a request with logging, hooks and retries.
     *
     * Retries 429 (except quota errors), 5xx responses and transport errors up to "max_retries"
     * times (default 2), waiting for Retry-After when the provider sends it, otherwise with
     * exponential backoff starting at "retry_base_delay" seconds, capped at "retry_max_delay".
     *
     * @param   string    $method  HTTP method, for logging and hooks
     * @param   string    $url     Request URL
     * @param   callable  $send    Performs the request and returns the HTTP response
     *
     * @return  \Joomla\Http\Response
     * @throws  AuthenticationException|RateLimitException|QuotaExceededException|ProviderException
     * @since   __DEPLOY_VERSION__
     */
    protected function sendRequest(string $method, string $url, callable $send): HttpResponse
    {
        $request = ['provider' => $this->getName(), 'method' => $method, 'url' => $url, 'stream' => false];
        $maxRetries = $this->getMaxRetries();

        $this->checkLimits($request);

        for ($attempt = 0;; $attempt++) {
            $request['attempt'] = $attempt;
            $this->beforeRequest($request);
            $start = microtime(true);

            try {
                $response = $send();
            } catch (\RuntimeException $e) {
                if ($attempt < $maxRetries) {
                    $this->retryAfter($request, $this->getBackoffDelay($attempt), $e);
                    continue;
                }

                $this->onRequestError($request, $e);

                throw $e;
            }

            $this->afterResponse($request, $response, microtime(true) - $start);

            if ($attempt < $maxRetries && $this->isRetryableResponse($response)) {
                $this->retryAfter($request, $this->getRetryDelay($response, $attempt), $response);
                continue;
            }

            $this->validateWithHooks($request, $response);

            return $response;
        }
    }

    /**
     * Add a usage limit. Limits can also be passed as the "limits" option, and one limit
     * instance can be shared by several providers to limit them together.
     *
     * @param   Limit  $limit  The limit
     *
     * @return  $this
     * @since   __DEPLOY_VERSION__
     */
    public function addLimit(Limit $limit): static
    {
        $this->limits[] = $limit;

        return $this;
    }

    /**
     * Get all limits for this provider.
     *
     * @return  Limit[]
     * @since   __DEPLOY_VERSION__
     */
    public function getLimits(): array
    {
        $fromOptions = array_filter((array) $this->getOption('limits', []), fn ($limit): bool => $limit instanceof Limit);

        return array_merge(array_values($fromOptions), $this->limits);
    }

    /**
     * Check the limits before a request is sent (retries are not checked again).
     *
     * @param   array  $request  Request information
     *
     * @throws  LimitExceededException
     * @since   __DEPLOY_VERSION__
     */
    protected function checkLimits(array $request): void
    {
        foreach ($this->getLimits() as $limit) {
            try {
                $limit->check($request);
            } catch (LimitExceededException $e) {
                $this->getLogger()->warning('{provider} {method} {url} blocked: {error}', $request + ['error' => $e->getMessage()]);
                $this->dispatchHook('on_error', $request, $e);

                throw $e;
            }
        }
    }

    /**
     * Record a model response against the limits, then return it unchanged.
     *
     * @param   Response  $response  The response
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    protected function recordResponse(Response $response): Response
    {
        $request = ['provider' => $this->getName(), 'model' => $response->getMetadata()['model'] ?? null];

        foreach ($this->getLimits() as $limit) {
            $limit->record($request, $response);
        }

        return $response;
    }

    /**
     * Register a hook. Events and arguments:
     * - before_request(array $request)
     * - after_response(array $request, \Joomla\Http\Response $response, float $seconds)
     * - on_retry(array $request, float $delaySeconds, \Joomla\Http\Response|\Throwable $reason)
     * - on_error(array $request, \Throwable $error)
     *
     * $request holds provider, method, url, stream and attempt (0 for the first try).
     * Hooks can also be passed as the "hooks" option: ['before_request' => callable, ...].
     *
     * @param   string    $event     The event name
     * @param   callable  $callback  The callback
     *
     * @return  $this
     * @throws  InvalidArgumentException  For an unknown event
     * @since   __DEPLOY_VERSION__
     */
    public function addHook(string $event, callable $callback): static
    {
        if (!in_array($event, ['before_request', 'after_response', 'on_retry', 'on_error'], true)) {
            throw InvalidArgumentException::invalidParameter('event', $event, $this->getName(), 'Unknown hook event.');
        }

        $this->hooks[$event][] = $callback;

        return $this;
    }

    /**
     * Set the PSR-3 logger. The "logger" provider option does the same.
     *
     * @param   LoggerInterface  $logger  The logger
     *
     * @return  void
     * @since   __DEPLOY_VERSION__
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Get the PSR-3 logger (a NullLogger unless one was set or passed as the "logger" option).
     *
     * @return  LoggerInterface
     * @since   __DEPLOY_VERSION__
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger ??= ($this->getOption('logger') instanceof LoggerInterface ? $this->getOption('logger') : new NullLogger());
    }

    /**
     * Run the hooks registered for an event.
     *
     * @param   string  $event      The event name
     * @param   mixed   ...$arguments  Arguments for the callbacks
     *
     * @since   __DEPLOY_VERSION__
     */
    private function dispatchHook(string $event, mixed ...$arguments): void
    {
        $callbacks = array_merge((array) ($this->getOption('hooks', [])[$event] ?? []), $this->hooks[$event] ?? []);

        foreach ($callbacks as $callback) {
            if (is_callable($callback)) {
                $callback(...$arguments);
            }
        }
    }

    private function beforeRequest(array $request): void
    {
        $this->getLogger()->debug('{provider} {method} {url} (attempt {attempt})', $request);
        $this->dispatchHook('before_request', $request);
    }

    private function afterResponse(array $request, HttpResponse $response, float $seconds): void
    {
        $this->getLogger()->info('{provider} {method} {url} returned {status} in {seconds}s', $request + [
            'status' => $response->getStatusCode(),
            'seconds' => round($seconds, 3),
        ]);
        $this->dispatchHook('after_response', $request, $response, $seconds);
    }

    private function retryAfter(array $request, float $delay, HttpResponse|\Throwable $reason): void
    {
        $this->getLogger()->warning('{provider} {method} {url} failed ({reason}), retrying in {delay}s', $request + [
            'reason' => $reason instanceof \Throwable ? $reason->getMessage() : 'HTTP ' . $reason->getStatusCode(),
            'delay' => round($delay, 3),
        ]);
        $this->dispatchHook('on_retry', $request, $delay, $reason);
        $this->pause($delay);
    }

    private function onRequestError(array $request, \Throwable $error): void
    {
        $this->getLogger()->error('{provider} {method} {url} failed: {error}', $request + ['error' => $error->getMessage(), 'exception' => $error]);
        $this->dispatchHook('on_error', $request, $error);
    }

    /**
     * Validate a response, reporting failures to the logger and on_error hooks.
     *
     * @param   array                  $request   Request info
     * @param   \Joomla\Http\Response  $response  The response
     *
     * @since   __DEPLOY_VERSION__
     */
    private function validateWithHooks(array $request, HttpResponse $response): void
    {
        try {
            $this->validateResponse($response);
        } catch (\Exception $e) {
            $this->onRequestError($request, $e);

            throw $e;
        }
    }

    /**
     * Get the configured number of retries.
     *
     * @return  int
     * @since   __DEPLOY_VERSION__
     */
    private function getMaxRetries(): int
    {
        return max(0, (int) $this->getOption('max_retries', 2));
    }

    /**
     * Check whether a response should be retried: 429 (not quota/billing) or a 5xx other than 501.
     *
     * @param   \Joomla\Http\Response  $response  The response
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    protected function isRetryableResponse(HttpResponse $response): bool
    {
        $status = $response->getStatusCode();

        if ($status === 429) {
            return !$this->isQuotaError($this->getErrorMessage($response));
        }

        return $status >= 500 && $status !== 501;
    }

    /**
     * Seconds to wait before retrying, from Retry-After / retry-after-ms or exponential backoff.
     *
     * @param   \Joomla\Http\Response  $response  The response
     * @param   int                    $attempt   Zero-based attempt number
     *
     * @return  float
     * @since   __DEPLOY_VERSION__
     */
    protected function getRetryDelay(HttpResponse $response, int $attempt): float
    {
        $maxDelay = (float) $this->getOption('retry_max_delay', 60);

        $retryAfterMs = $response->getHeaderLine('retry-after-ms');
        if (is_numeric($retryAfterMs)) {
            return min($maxDelay, max(0.0, (float) $retryAfterMs / 1000));
        }

        $retryAfter = trim($response->getHeaderLine('retry-after'));
        if (is_numeric($retryAfter)) {
            return min($maxDelay, max(0.0, (float) $retryAfter));
        }

        if ($retryAfter !== '' && ($timestamp = strtotime($retryAfter)) !== false) {
            return min($maxDelay, max(0.0, (float) ($timestamp - time())));
        }

        return $this->getBackoffDelay($attempt);
    }

    /**
     * Exponential backoff with jitter: base * 2^attempt, scaled by 0.8-1.0, capped at retry_max_delay.
     *
     * @param   int  $attempt  Zero-based attempt number
     *
     * @return  float
     * @since   __DEPLOY_VERSION__
     */
    protected function getBackoffDelay(int $attempt): float
    {
        $base = (float) $this->getOption('retry_base_delay', 0.5);
        $delay = $base * (2 ** $attempt) * (0.8 + mt_rand() / mt_getrandmax() * 0.2);

        return min((float) $this->getOption('retry_max_delay', 60), $delay);
    }

    /**
     * Wait before a retry. Override in tests to avoid sleeping.
     *
     * @param   float  $seconds  Seconds to wait
     *
     * @since   __DEPLOY_VERSION__
     */
    protected function pause(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1000000));
        }
    }

    /**
     * Extract the error message from an error response.
     *
     * @param   \Joomla\Http\Response  $response  The response
     *
     * @return  string
     * @since   __DEPLOY_VERSION__
     */
    private function getErrorMessage(HttpResponse $response): string
    {
        $body = (string) $response->getBody();
        $response->getBody()->rewind();
        $errorData = json_decode($body, true);

        if (!is_array($errorData)) {
            return $body;
        }

        $message = $errorData['message'] ?? $errorData['error']['message'] ?? $errorData['error'] ?? '';

        return is_string($message) ? $message : '';
    }

    /**
     * Check whether a 429 message means the account is out of quota (not worth retrying).
     *
     * @param   string  $message  The error message
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    private function isQuotaError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'quota') || str_contains($message, 'credits') || str_contains($message, 'billing');
    }

    /**
     * Split a stream of raw chunks into complete lines (newline-delimited JSON).
     *
     * @param   iterable  $chunks  Raw body chunks
     *
     * @return  \Generator<int, string>  Non-empty lines without the line ending
     * @since   __DEPLOY_VERSION__
     */
    protected function readLines(iterable $chunks): \Generator
    {
        $buffer = '';

        foreach ($chunks as $chunk) {
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $pos), "\r");
                $buffer = substr($buffer, $pos + 1);

                if ($line !== '') {
                    yield $line;
                }
            }
        }

        if (trim($buffer) !== '') {
            yield rtrim($buffer, "\r");
        }
    }

    /**
     * Parse a stream of raw chunks as server-sent events.
     *
     * @param   iterable  $chunks  Raw body chunks
     *
     * @return  \Generator<int, array{event: string|null, data: string}>
     * @since   __DEPLOY_VERSION__
     */
    protected function readServerSentEvents(iterable $chunks): \Generator
    {
        $event = null;
        $data = [];
        $buffer = '';

        foreach ($chunks as $chunk) {
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $pos), "\r");
                $buffer = substr($buffer, $pos + 1);

                if ($line === '') {
                    // Blank line ends the event
                    if ($data !== []) {
                        yield ['event' => $event, 'data' => implode("\n", $data)];
                    }

                    $event = null;
                    $data = [];
                } elseif (str_starts_with($line, 'event:')) {
                    $event = trim(substr($line, 6));
                } elseif (str_starts_with($line, 'data:')) {
                    $data[] = ltrim(substr($line, 5), ' ');
                }
                // Comments (":") and other fields are ignored
            }
        }

        if (str_starts_with(rtrim($buffer, "\r"), 'data:')) {
            $data[] = ltrim(substr(rtrim($buffer, "\r"), 5), ' ');
        }

        if ($data !== []) {
            yield ['event' => $event, 'data' => implode("\n", $data)];
        }
    }

    /**
     * Decode one JSON event from a stream.
     *
     * @param   string  $json  The JSON text
     *
     * @return  array
     * @throws  UnserializableResponseException  If the event is not a JSON object
     * @since   __DEPLOY_VERSION__
     */
    protected function decodeStreamEvent(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new UnserializableResponseException($this->getName(), $json, 'Invalid JSON in stream event: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Run a chat stream to completion, passing each delta to an optional callback.
     *
     * @param   \Generator     $stream   Generator from chatStream()
     * @param   callable|null  $onChunk  Called with each text delta
     *
     * @return  Response  The final response
     * @since   __DEPLOY_VERSION__
     */
    protected function consumeStream(\Generator $stream, ?callable $onChunk = null): Response
    {
        foreach ($stream as $delta) {
            if ($onChunk !== null) {
                $onChunk($delta);
            }
        }

        return $stream->getReturn();
    }

    /**
     * Make HTTP GET request to AI provider API.
     *
     * @param   string  $url      API endpoint URL
     * @param   array   $headers  Additional HTTP headers
     * @param   integer $timeout  Request timeout in seconds
     *
     * @return  \Joomla\Http\Response
     * @throws  \Exception
     * @since  __DEPLOY_VERSION__
     */
    protected function makeGetRequest(string $url, array $headers = [], ?int $timeout = null): HttpResponse
    {
        return $this->sendRequest('GET', $url, fn () => $this->getHttpClient()->get($url, $headers, $timeout));
    }

    /**
     * Make HTTP POST request.
     *
     * @param   string   $url      API endpoint URL
     * @param   mixed    $data     POST data
     * @param   array    $headers  HTTP headers
     * @param   integer  $timeout  Request timeout
     *
     * @return  \Joomla\Http\Response
     * @throws  \Exception
     * @since  __DEPLOY_VERSION__
     */
    protected function makePostRequest(string $url, mixed $data, array $headers = [], ?int $timeout = null): HttpResponse
    {
        return $this->sendRequest('POST', $url, fn () => $this->getHttpClient()->post($url, $data, $headers, $timeout));
    }

    /**
     * Make HTTP DELETE request.
     *
     * @param   string  $url      API endpoint URL
     * @param   mixed   $data     Data to send with DELETE request
     * @param   array   $headers  HTTP headers
     * @param   integer $timeout  Request timeout
     *
     * @return  \Joomla\Http\Response
     * @throws  \Exception
     * @since  __DEPLOY_VERSION__
     */
    protected function makeDeleteRequest(string $url, mixed $data, array $headers = [], ?int $timeout = null): HttpResponse
    {
        return $this->sendRequest('DELETE', $url, fn () => $this->getHttpClient()->delete($url, $headers, $timeout, $data));
    }
    /**
     * Make multipart HTTP POST request.
     *
     * @param   string  $url      API endpoint URL
     * @param   array   $data     Form data
     * @param   array   $headers  HTTP headers
     *
     * @return  \Joomla\Http\Response
     * @since   __DEPLOY_VERSION__
     */
    protected function makeMultipartPostRequest(string $url, array $data, array $headers): HttpResponse
    {
        $boundary = '----aiframeworkjoomla-boundary-' . uniqid();
        $postData = '';

        foreach ($data as $key => $value) {
            // Handle metadata fields
            if (in_array($key, ['_filename', '_filepath', '_image_filenames', '_mask_filename'])) {
                continue;
            }

            // Handle creating audio file object
            if ($key === 'file' && isset($data['_filepath'])) {
                $filepath = $data['_filepath'];
                $filename = $data['_filename'];
                $mimeType = $this->detectAudioMimeType($filepath);

                $fileResource = fopen($filepath, 'rb');
                if (!$fileResource) {
                    throw new \Exception("Cannot open file: $filepath");
                }

                $postData .= "--{$boundary}\r\n";
                $postData .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
                $postData .= "Content-Type: {$mimeType}\r\n\r\n";

                $fileContent = stream_get_contents($fileResource);
                fclose($fileResource);

                $postData .= $fileContent . "\r\n";
            } elseif ($key === 'image') {
                // Multiple images are sent as image[]; MIME type comes from the file contents
                $images = is_array($value) ? array_values($value) : [$value];
                $fieldName = is_array($value) ? 'image[]' : 'image';
                $filenames = array_values((array) ($data['_image_filenames'] ?? []));

                foreach ($images as $index => $imageData) {
                    $mimeType = $this->detectImageMimeType($imageData);
                    $filename = $filenames[$index] ?? 'image' . ($index ?: '') . '.' . $this->getExtensionFromMimeType($mimeType);
                    $postData .= $this->buildMultipartFilePart($boundary, $fieldName, $filename, $mimeType, $imageData);
                }
            } elseif ($key === 'mask') {
                $mimeType = $this->detectImageMimeType($value);
                $filename = $data['_mask_filename'] ?? 'mask.' . $this->getExtensionFromMimeType($mimeType);
                $postData .= $this->buildMultipartFilePart($boundary, 'mask', $filename, $mimeType, $value);
            } else {
                // Handle regular form fields
                $postData .= "--{$boundary}\r\n";
                $postData .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
                $postData .= $value . "\r\n";
            }
        }
        $postData .= "--{$boundary}--\r\n";

        $headers['Content-Type'] = "multipart/form-data; boundary={$boundary}";

        return $this->makePostRequest($url, $postData, $headers);
    }

    /**
     * Build one file part of a multipart body.
     *
     * @param   string  $boundary   The multipart boundary
     * @param   string  $fieldName  The form field name
     * @param   string  $filename   The file name sent to the API
     * @param   string  $mimeType   The file MIME type
     * @param   string  $content    The file contents
     *
     * @return  string
     * @since   __DEPLOY_VERSION__
     */
    private function buildMultipartFilePart(string $boundary, string $fieldName, string $filename, string $mimeType, string $content): string
    {
        $filename = str_replace(['"', "\r", "\n"], '', $filename);

        return "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"{$fieldName}\"; filename=\"{$filename}\"\r\n"
            . "Content-Type: {$mimeType}\r\n\r\n"
            . $content . "\r\n";
    }

    /**
     * Extract filename from multipart field or generate default.
     *
     * @param   string  $fieldName  The form field name
     * @param   string  $data       The file data
     *
     * @return  string  The filename
     * @since   __DEPLOY_VERSION__
     */
    protected function extractFilename(string $fieldName, string $data): string
    {
        $mimeType = $this->detectImageMimeType($data);
        $extension = $this->getExtensionFromMimeType($mimeType);

        if (strpos($fieldName, 'image[') === 0) {
            $index = preg_replace('/[^0-9]/', '', $fieldName);
            return "image_{$index}.{$extension}";
        }

        return "image.{$extension}";
    }

    /**
     * Detect MIME type from image binary data.
     *
     * @param   string  $imageData  Binary image data
     *
     * @return  string  MIME type
     * @since   __DEPLOY_VERSION__
     */
    protected function detectImageMimeType(string $imageData): string
    {
        $header = substr($imageData, 0, 16);

        // PNG signature
        if (substr($header, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return 'image/png';
        }

        // JPEG signature
        if (substr($header, 0, 2) === "\xFF\xD8") {
            return 'image/jpeg';
        }

        // WebP signature
        if (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        throw new \InvalidArgumentException('Unsupported image format. Only PNG, JPEG, and WebP are supported.');
    }

    protected function getExtensionFromMimeType(string $mimeType): string
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return 'jpg';
            case 'image/webp':
                return 'webp';
            case 'image/png':
            default:
                return 'png';
        }
    }

    /**
     * Get audio MIME type from a format name or file path.
     *
     * @param   string  $input  Format name (e.g. "mp3") or file path
     *
     * @return  string  The MIME type
     * @throws  InvalidArgumentException  If the extension is not a known audio format
     * @since   __DEPLOY_VERSION__
     */
    protected function detectAudioMimeType(string $input): string
    {
        $mimeMap = [
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'ogg' => 'audio/ogg',
            'webm' => 'audio/webm',
            'mp4' => 'audio/mp4',
            'mpeg' => 'audio/mpeg',
            'mpga' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'opus' => 'audio/opus',
            'aac' => 'audio/aac',
            'pcm' => 'audio/pcm',
        ];

        // Accept a format name ("mp3") or a file path ("speech.mp3")
        $extension = isset($mimeMap[strtolower($input)]) ? strtolower($input) : strtolower(pathinfo($input, PATHINFO_EXTENSION));

        if (!isset($mimeMap[$extension])) {
            throw InvalidArgumentException::invalidFileFormat($input, $extension, array_keys($mimeMap), $this->getName());
        }

        return $mimeMap[$extension];
    }

    /**
     * Check if a model is available with the provider.
     *
     * @param   string  $model           The model to check
     * @param   array   $availableModels Array of available models
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    protected function isModelAvailable(string $model, array $availableModels): bool
    {
        return in_array($model, $availableModels, true);
    }

    /**
     * Get models that support a specific capability from available models.
     *
     * @param   array  $availableModels  All available models
     * @param   array  $capableModels    Models that support the capability
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    protected function getModelsByCapability(array $availableModels, array $capableModels): array
    {
        return array_values(array_intersect($availableModels, $capableModels));
    }

    /**
     * Check if a model supports a specific capability.
     *
     * @param   string  $model          The model to check
     * @param   string  $capability     The capability to check
     * @param   array   $capabilityMap  Map of capabilities to model arrays
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    protected function checkModelCapability(string $model, string $capability, array $capabilityMap): bool
    {
        if (!isset($capabilityMap[$capability])) {
            return false;
        }

        return $this->isModelAvailable($model, $capabilityMap[$capability]);
    }

    /**
     * Normalise the "tools" option into a list of provider-neutral tool definitions.
     *
     * Each tool is ['name' => string, 'description' => string, 'parameters' => JSON schema array,
     * 'strict' => bool (optional)].
     *
     * @param   array  $options  Request options
     *
     * @return  array  List of tools; empty when no tools were given
     * @throws  InvalidArgumentException  If a tool definition is incomplete
     * @since   __DEPLOY_VERSION__
     */
    protected function getToolDefinitions(array $options): array
    {
        $tools = [];

        foreach ((array) ($options['tools'] ?? []) as $index => $tool) {
            if (!is_array($tool) || !is_string($tool['name'] ?? null) || $tool['name'] === '') {
                throw InvalidArgumentException::invalidParameter('tools', $tool, $this->getName(), "Tool at index $index must be an array with a 'name'.");
            }

            $parameters = $tool['parameters'] ?? ['type' => 'object', 'properties' => []];

            // An empty PHP array would be encoded as [] instead of {}
            if (is_array($parameters) && ($parameters['properties'] ?? null) === []) {
                $parameters['properties'] = new \stdClass();
            }

            if (!is_array($parameters) && !is_object($parameters)) {
                throw InvalidArgumentException::invalidParameter('tools', $tool, $this->getName(), "Tool '{$tool['name']}' parameters must be a JSON schema.");
            }

            $tools[] = [
                'name'        => $tool['name'],
                'description' => (string) ($tool['description'] ?? ''),
                'parameters'  => $parameters,
                'strict'      => isset($tool['strict']) ? (bool) $tool['strict'] : null,
            ];
        }

        return $tools;
    }

    /**
     * Normalise the "response_schema" option.
     *
     * Accepts a JSON schema directly, or ['name' => ..., 'schema' => [...], 'strict' => bool].
     *
     * @param   array  $options  Request options
     *
     * @return  array|null  ['name' => string, 'schema' => array, 'strict' => bool] or null when not set
     * @throws  InvalidArgumentException  If the schema is not an array
     * @since   __DEPLOY_VERSION__
     */
    protected function getResponseSchema(array $options): ?array
    {
        if (!isset($options['response_schema'])) {
            return null;
        }

        $option = $options['response_schema'];

        if (!is_array($option)) {
            throw InvalidArgumentException::invalidParameter('response_schema', $option, $this->getName(), 'response_schema must be a JSON schema array.');
        }

        $isWrapped = isset($option['schema']) && is_array($option['schema']);

        return [
            'name'   => (string) ($isWrapped ? ($option['name'] ?? 'response') : 'response'),
            'schema' => $isWrapped ? $option['schema'] : $option,
            'strict' => (bool) ($isWrapped ? ($option['strict'] ?? true) : true),
        ];
    }

    /**
     * Throw when tools are combined with streaming, which does not collect tool calls.
     *
     * @param   array  $options  Request options
     *
     * @throws  InvalidArgumentException
     * @since   __DEPLOY_VERSION__
     */
    protected function assertNoToolsWhenStreaming(array $options): void
    {
        if (!empty($options['tools'])) {
            throw InvalidArgumentException::invalidParameter('tools', $options['tools'], $this->getName(), 'Tools are not supported with streaming. Call chat() without "stream" to get tool calls.');
        }
    }

    /**
     * Build the assistant message to add to a conversation history after a response.
     *
     * Providers override this to keep their native format (e.g. tool calls or thinking blocks),
     * which some APIs need when the history is sent back.
     *
     * @param   Response  $response  The response
     *
     * @return  array  A message in the provider's format
     * @since   __DEPLOY_VERSION__
     */
    public function buildAssistantMessage(Response $response): array
    {
        return ['role' => 'assistant', 'content' => $response->getContent()];
    }

    /**
     * Put the "system" option at the start of a message list, for APIs that take it as a message.
     * Nothing changes when there is no system option or the list already starts with a system message.
     *
     * @param   array  $messages  Chat messages
     * @param   array  $options   Request options
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    protected function prependSystemMessage(array $messages, array $options): array
    {
        $system = $options['system'] ?? null;

        if (!is_string($system) || $system === '' || (($messages[0]['role'] ?? null) === 'system')) {
            return $messages;
        }

        return array_merge([['role' => 'system', 'content' => $system]], $messages);
    }

    /**
     * Encode a tool result for sending back to the model.
     *
     * @param   mixed  $result  String, or any JSON-encodable value
     *
     * @return  string
     * @since   __DEPLOY_VERSION__
     */
    protected function encodeToolResult(mixed $result): string
    {
        return is_string($result) ? $result : (string) json_encode($result);
    }

    /**
     * Ensure required fields exist in a decoded API response.
     *
     * @param   array   $data          Decoded response
     * @param   array   $requiredPaths Dot paths that must be present and be arrays where noted with a trailing "[]"
     *                                 (e.g. "choices.0[]", "model")
     * @param   string  $rawResponse   Raw response body, for the exception
     *
     * @throws  UnserializableResponseException  If a required field is missing or has the wrong type
     * @since   __DEPLOY_VERSION__
     */
    protected function assertResponseFields(array $data, array $requiredPaths, string $rawResponse): void
    {
        foreach ($requiredPaths as $path) {
            $mustBeArray = str_ends_with($path, '[]');
            $value = $data;

            foreach (explode('.', rtrim($path, '[]')) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    throw new UnserializableResponseException($this->getName(), $rawResponse, "Missing field '" . rtrim($path, '[]') . "' in response");
                }

                $value = $value[$segment];
            }

            if ($mustBeArray && !is_array($value)) {
                throw new UnserializableResponseException($this->getName(), $rawResponse, "Field '" . rtrim($path, '[]') . "' in response must be an array");
            }
        }
    }

    /**
     * Build the normalised token usage metadata shared by all providers.
     *
     * @param   int|null  $inputTokens   Prompt/input tokens
     * @param   int|null  $outputTokens  Completion/output tokens
     * @param   int|null  $totalTokens   Total tokens, computed when not given
     *
     * @return  array  input_tokens, output_tokens and total_tokens
     * @since   __DEPLOY_VERSION__
     */
    protected function normaliseUsage(?int $inputTokens, ?int $outputTokens, ?int $totalTokens = null): array
    {
        if ($totalTokens === null && ($inputTokens !== null || $outputTokens !== null)) {
            $totalTokens = ($inputTokens ?? 0) + ($outputTokens ?? 0);
        }

        return [
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens'  => $totalTokens,
        ];
    }

    /**
     * Get the provider's default capability map (models per capability, limits, allowed values).
     * Providers override this; users can extend or replace entries with the "capabilities" option.
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    protected function getDefaultCapabilities(): array
    {
        return [];
    }

    /**
     * Get the capability map, with the "capabilities" provider option applied on top of the defaults.
     * Keyed maps (e.g. per-model limits) are merged one level deep; lists and scalars are replaced.
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    protected function getCapabilities(): array
    {
        if ($this->capabilities === null) {
            $capabilities = $this->getDefaultCapabilities();

            foreach ((array) $this->getOption('capabilities', []) as $key => $value) {
                if (
                    is_array($value) && !array_is_list($value)
                    && isset($capabilities[$key]) && is_array($capabilities[$key]) && !array_is_list($capabilities[$key])
                ) {
                    $capabilities[$key] = array_replace($capabilities[$key], $value);
                } else {
                    $capabilities[$key] = $value;
                }
            }

            $this->capabilities = $capabilities;
        }

        return $this->capabilities;
    }

    /**
     * Get a single capability entry, optionally for a specific model.
     *
     * @param   string       $key      The capability key
     * @param   string|null  $model    Model name for per-model entries
     * @param   mixed        $default  Value returned when the entry is not defined
     *
     * @return  mixed
     * @since   __DEPLOY_VERSION__
     */
    protected function getCapability(string $key, ?string $model = null, mixed $default = null): mixed
    {
        $value = $this->getCapabilities()[$key] ?? null;

        if ($model !== null) {
            $value = is_array($value) ? ($value[$model] ?? null) : null;
        }

        return $value ?? $default;
    }

    /**
     * Check response code and handle errors
     *
     * @param   \Joomla\Http\Response  $response  HTTP response
     *
     * @return  boolean  True if successful
     * @throws  \Exception
     * @since  __DEPLOY_VERSION__
     */
    protected function validateResponse(HttpResponse $response): bool
    {
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $responseBody = $response->getBody();
            $errorData = json_decode($responseBody, true) ?? ['message' => $responseBody];
            $message = $errorData['message'] ?? $errorData['error']['message'] ?? 'Unknown error';
            $providerErrorCode = $errorData['code'] ?? $errorData['error']['code'] ?? $errorData['type'] ?? $errorData['error']['type'] ?? null;

            // Handle specific HTTP status codes with appropriate exceptions
            switch ($response->getStatusCode()) {
                case 401:
                case 403:
                    throw new AuthenticationException($this->getName(), $errorData, $response->getStatusCode());

                case 429:
                    if ($this->isQuotaError((string) $message)) {
                        throw new QuotaExceededException($this->getName(), $errorData, $response->getStatusCode());
                    }

                    // Any other 429 is treated as a rate limit
                    throw new RateLimitException($this->getName(), $errorData, $response->getStatusCode());

                default:
                    throw new ProviderException($this->getName(), $errorData, $response->getStatusCode(), $providerErrorCode);
            }
        }

        return true;
    }

    protected function isJsonResponse(string $responseBody): bool
    {
        // JSON responses start with { or [
        $trimmed = ltrim($responseBody);
        return !empty($trimmed) && ($trimmed[0] === '{' || $trimmed[0] === '[');
    }

    /**
     * Parse JSON response safely
     *
     * @param   string  $jsonString  The JSON string to parse
     *
     * @return  array  The parsed JSON data
     * @throws  UnserializableResponseException  If JSON parsing fails
     * @since  __DEPLOY_VERSION__
     */
    protected function parseJsonResponse(string $jsonString): array
    {
        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new UnserializableResponseException($this->getName(), $jsonString, json_last_error_msg(), 422);
        }

        if (!is_array($decoded)) {
            throw new UnserializableResponseException($this->getName(), $jsonString, 'Response is not a JSON object or array', 422);
        }

        return $decoded;
    }

    /**
     * Apply moderation to the input if the provider implements ModerationInterface.
     * Can be disabled with the "moderate_input" provider option.
     *
     * @param   string|array  $input    The input to moderate (text or images)
     * @param   array         $options  Additional options for moderation
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    protected function moderateInput(string|array $input, array $options = []): bool
    {
        // Check if the provider supports moderation and it is enabled
        if (!($this instanceof ModerationInterface) || !$this->getOption('moderate_input', true)) {
            return false;
        }

        $moderationResult = $this->moderate($input, $options);
        return $this->isContentFlagged($moderationResult);
    }
}
