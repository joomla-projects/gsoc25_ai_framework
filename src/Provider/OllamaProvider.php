<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  Copyright (C) 2025 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Provider;

use Joomla\AI\AbstractProvider;
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Interface\ChatInterface;
use Joomla\AI\Interface\EmbeddingInterface;
use Joomla\AI\Interface\ProviderInterface;
use Joomla\AI\Interface\StreamInterface;
use Joomla\AI\Interface\ToolInterface;
use Joomla\AI\Response\Response;
use Joomla\Http\HttpFactory;

/**
 * Ollama provider implementation.
 *
 * @since  __DEPLOY_VERSION__
 */
class OllamaProvider extends AbstractProvider implements ProviderInterface, ChatInterface, EmbeddingInterface, StreamInterface, ToolInterface
{
    /**
     * Custom base URL for API requests
     *
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private string $baseUrl;

    /**
     * Constructor.
     *
     * @param   array|\ArrayAccess  $options     Provider options array.
     * @param   HttpFactory         $httpFactory The http factory
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(array|\ArrayAccess $options = [], ?HttpFactory $httpFactory = null)
    {
        parent::__construct($options, $httpFactory);

        $this->baseUrl = $this->getOption('base_url', 'http://localhost:11434');

        // Remove trailing slash if present
        if (substr($this->baseUrl, -1) === '/') {
            $this->baseUrl = rtrim($this->baseUrl, '/');
        }
    }

    /**
     * Check if Ollama provider is supported/configured.
     *
     * @param   string  $baseUrl  The Ollama server base URL
     *
     * @return  boolean  True if Ollama server is accessible
     * @since  __DEPLOY_VERSION__
     */
    public static function isSupported(string $baseUrl = 'http://localhost:11434'): bool
    {
        try {
            $response = @file_get_contents(rtrim($baseUrl, '/') . '/api/tags');
            return $response !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ensure server is running
     *
     * @throws  AuthenticationException  If the server is not running
     * @since  __DEPLOY_VERSION__
     */
    private function validateConnection(): void
    {
        try {
            $this->makeGetRequest($this->baseUrl . '/api/tags');
        } catch (\Exception $e) {
            throw new AuthenticationException(
                $this->getName(),
                ['message' => 'Ollama server not running. Please start with: ollama serve'],
                401
            );
        }
    }

    /**
     * Get the provider name.
     *
     * @return  string  The provider name
     * @since  __DEPLOY_VERSION__
     */
    public function getName(): string
    {
        return 'Ollama';
    }

    /**
    * Get the chat endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getChatEndpoint(): string
    {
        return $this->baseUrl . '/api/chat';
    }

    /**
     * Get the copy model endpoint URL.
     *
     * @return  string  The endpoint URL
     * @since  __DEPLOY_VERSION__
     */
    private function getCopyModelEndpoint(): string
    {
        return $this->baseUrl . '/api/copy';
    }

    /**
     * Get the delete model endpoint URL.
     *
     * @return  string  The endpoint URL
     * @since  __DEPLOY_VERSION__
     */
    private function getDeleteModelEndpoint(): string
    {
        return $this->baseUrl . '/api/delete';
    }

    /**
    * Get the pull model endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getPullEndpoint(): string
    {
        return $this->baseUrl . '/api/pull';
    }

    /**
    * Get the generate endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getGenerateEndpoint(): string
    {
        return $this->baseUrl . '/api/generate';
    }

    /**
     * Get the embed endpoint URL
     *
     * @return  string  The endpoint URL
     * @since   __DEPLOY_VERSION__
     */
    private function getEmbedEndpoint(): string
    {
        return $this->baseUrl . '/api/embed';
    }

    /**
     * Get the show model endpoint URL
     *
     * @return  string  The endpoint URL
     * @since   __DEPLOY_VERSION__
     */
    private function getShowEndpoint(): string
    {
        return $this->baseUrl . '/api/show';
    }

    /**
     * Get all available models for this provider.
     *
     * @return  array  Array of available model names
     * @since   __DEPLOY_VERSION__
     */
    public function getAvailableModels(): array
    {
        $this->validateConnection();

        $response = $this->makeGetRequest($this->baseUrl . '/api/tags');
        $data = $this->parseJsonResponse($response->getBody());
        $this->assertResponseFields($data, ['models[]'], (string) $response->getBody());

        return array_column($data['models'], 'name');
    }

    /**
     * List models currently loaded into memory (running).
     *
     * @return  array  Names of the running models
     * @throws ProviderException If the request fails
     * @since __DEPLOY_VERSION__
     */
    public function getRunningModels(): array
    {
        $this->validateConnection();

        $endpoint = $this->baseUrl . '/api/ps';
        $response = $this->makeGetRequest($endpoint);
        $data = $this->parseJsonResponse($response->getBody());

        return array_column(is_array($data['models'] ?? null) ? $data['models'] : [], 'name');
    }

    /**
     * Check if a model exists in the available models list, handling name variations
     *
     * @param   string  $modelName        Model name to check
     * @param   array   $availableModels  List of available models
     * @return  bool    True if model exists
     */
    private function checkModelExists(string $modelName, array $availableModels): bool
    {
        $wanted = $this->normaliseModelName($modelName);

        foreach ($availableModels as $available) {
            if ($this->normaliseModelName((string) $available) === $wanted) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a model is available locally. "llama3" and "llama3:latest" are the same model.
     *
     * @param   string  $modelName  The model name
     *
     * @return  bool
     * @throws  AuthenticationException  If the server is not running
     * @since   __DEPLOY_VERSION__
     */
    public function hasModel(string $modelName): bool
    {
        $this->validateConnection();

        return $this->checkModelExists($modelName, $this->getAvailableModels());
    }

    /**
     * Normalise an Ollama model name: a name without a tag means the "latest" tag.
     *
     * @param   string  $modelName  e.g. "llama3", "llama3:latest", "library/llama3:8b"
     *
     * @return  string  e.g. "llama3:latest", "llama3:latest", "library/llama3:8b"
     * @since   __DEPLOY_VERSION__
     */
    private function normaliseModelName(string $modelName): string
    {
        // The tag follows the last ":" after the last "/" (a registry host may contain a port)
        $name = substr($modelName, (int) strrpos('/' . $modelName, '/'));

        return str_contains($name, ':') ? $modelName : $modelName . ':latest';
    }

    /**
     * Copy a model to a new name.
     *
     * @param string $sourceModel      The name of the source model to copy
     * @param string $destinationModel The new name for the copied model
     *
     * @return bool                    True if copy was successful
     * @since __DEPLOY_VERSION__
     */
    public function copyModel(string $sourceModel, string $destinationModel): bool
    {
        $this->validateConnection();

        $endpoint = $this->getCopyModelEndpoint();
        $payload = [
            'source' => $sourceModel,
            'destination' => $destinationModel
        ];

        $jsonData = json_encode($payload);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Failed to encode copy request: ' . json_last_error_msg()]
            );
        }

        try {
            $httpResponse = $this->makePostRequest($endpoint, $jsonData);
        } catch (ProviderException $e) {
            // Ollama answers 404 when the source model does not exist
            if ($e->getHttpStatusCode() === 404) {
                throw InvalidArgumentException::invalidModel($sourceModel, $this->getName());
            }

            throw $e;
        }

        $status = $httpResponse->getStatusCode();

        if ($status === 200) {
            return true;
        } else {
            throw new ProviderException(
                $this->getName(),
                ['message' => "Unexpected status code $status from copy API."]
            );
        }
    }

    /**
     * Delete a model and its data.
     *
     * @param string $modelName        The name of the model to delete
     *
     * @return bool                    True if deletion was successful
     * @throws ProviderException       If the deletion fails or model does not exist
     * @since __DEPLOY_VERSION__
     */
    public function deleteModel(string $modelName): bool
    {
        $this->validateConnection();

        $endpoint = $this->getDeleteModelEndpoint();
        $payload = [
            'model' => $modelName
        ];

        $jsonData = json_encode($payload);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Failed to encode delete request: ' . json_last_error_msg()]
            );
        }

        try {
            $httpResponse = $this->makeDeleteRequest($endpoint, $jsonData);
        } catch (ProviderException $e) {
            // Ollama answers 404 when the model does not exist
            if ($e->getHttpStatusCode() === 404) {
                throw InvalidArgumentException::invalidModel($modelName, $this->getName());
            }

            throw $e;
        }

        $status = $httpResponse->getStatusCode();

        if ($status === 200) {
            return true;
        } else {
            throw new ProviderException(
                $this->getName(),
                ['message' => "Unexpected status code $status from delete API."]
            );
        }
    }

    /**
     * Pull a model from Ollama library
     *
     * @param   string  $modelName  Name of the model to pull
     * @param   bool    $stream     Whether to stream the response (for progress updates)
     * @param   bool    $insecure   Allow insecure connections to the library
     *
     * @throws  InvalidArgumentException  If model doesn't exist in Ollama library
     * @throws  ProviderException        If pull fails for other reasons
     * @since   __DEPLOY_VERSION__
     */
    public function pullModel(string $modelName, bool $stream = true, bool $insecure = false): bool
    {
        $this->validateConnection();

        $availableModels = $this->getAvailableModels();
        if ($this->checkModelExists($modelName, $availableModels)) {
            return true;
        }

        $endpoint = $this->getPullEndpoint();

        $requestData = [
            'model' => $modelName
        ];
        if ($insecure) {
            $requestData['insecure'] = true;
        }
        if (!$stream) {
            $requestData['stream'] = false;
        }

        try {
            $jsonData = json_encode($requestData);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ProviderException(
                    $this->getName(),
                    ['message' => 'Failed to encode request data: ' . json_last_error_msg()]
                );
            }

            $response = $this->makePostRequest($endpoint, $jsonData);

            if (!$stream) {
                $data = $this->parseJsonResponse($response->getBody());
                return isset($data['status']) && $data['status'] === 'success';
            }

            $body = $response->getBody();
            $fullContent = (string) $body;

            $lines = explode("\n", $fullContent);
            $hasError = false;
            $errorMessage = '';

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $data = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                    continue;
                }

                // Check for error in response
                if (isset($data['error'])) {
                    $errorMessage = (string) $data['error'];

                    // Check if this is a "model not found" type error
                    if (
                        strpos(strtolower($errorMessage), 'file does not exist') !== false ||
                        strpos(strtolower($errorMessage), 'model') !== false && strpos(strtolower($errorMessage), 'not found') !== false ||
                        strpos(strtolower($errorMessage), 'manifest') !== false && strpos(strtolower($errorMessage), 'not found') !== false
                    ) {
                        throw InvalidArgumentException::invalidModel(
                            $modelName,
                            $this->getName(),
                            []
                        );
                    }

                    // For other errors, throw ProviderException
                    throw new ProviderException(
                        $this->getName(),
                        ['message' => $errorMessage]
                    );
                }
            }

            // Check if success status exists in the response
            if (strpos($fullContent, '"status":"success"') !== false) {
                return true;
            }

            throw new ProviderException(
                $this->getName(),
                ['message' => "Pull of model '$modelName' did not report success."]
            );
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (ProviderException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Failed to pull model: ' . $e->getMessage()]
            );
        }
    }

    /**
     * Ensure model is available, pulling it if necessary
     *
     * @param   string  $modelName  Name of the model to ensure
     * @return  bool    True if model is available
     * @throws  ProviderException   If model cannot be made available
     */
    private function ensureModelAvailable(string $modelName): bool
    {
        $availableModels = $this->getAvailableModels();
        if (!$this->checkModelExists($modelName, $availableModels)) {
            $this->pullModel($modelName, true, false);
        }
        return true;
    }

    /**
     * Build the request payload for the chat endpoint
     *
     * @param   string|array  $message   The user message to send
     * @param   array   $options  Additional options
     *
     * @return  array   The request payload
     * @throws  \InvalidArgumentException  If model does not support chat capability
     * @since  __DEPLOY_VERSION__
     */
    public function buildChatRequestPayload(mixed $message, array $options = []): array
    {
        $this->validateConnection();

        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'tinyllama');
        $this->ensureModelAvailable($model);

        if (isset($options['messages'])) {
            $messages = $options['messages'];
            if (!is_array($messages) || empty($messages)) {
                throw InvalidArgumentException::invalidMessages('ollama', 'Messages must be a non-empty array.');
            }
        } else {
            $userMessage = [
                'role' => 'user',
                'content' => $message
            ];

            if (!empty($options['images'])) {
                $userMessage['images'] = $this->normaliseImages((array) $options['images']);
            }

            $messages = [$userMessage];
        }

        $messages = $this->prependSystemMessage($messages, $options);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false
        ];

        if (isset($options['stream'])) {
            $payload['stream'] = (bool) $options['stream'];
        }

        // Model parameters (temperature, top_p, num_predict, seed, ...) go in "options", as for generate()
        if (isset($options['options']) && is_array($options['options'])) {
            $payload['options'] = $options['options'];
        }

        if (isset($options['keep_alive'])) {
            $payload['keep_alive'] = $options['keep_alive'];
        }

        $tools = $this->getToolDefinitions($options);

        if ($tools !== []) {
            $payload['tools'] = array_map(fn (array $tool): array => [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                ],
            ], $tools);
        }

        $schema = $this->getResponseSchema($options);

        if ($schema !== null) {
            $payload['format'] = $schema['schema'];
        }

        return $payload;
    }

    /**
     * Build the assistant message for a conversation history, keeping any tool calls.
     *
     * @param   Response  $response  The response
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    public function buildAssistantMessage(Response $response): array
    {
        $message = parent::buildAssistantMessage($response);
        $toolCalls = $response->getMetadata()['raw_tool_calls'] ?? [];

        if ($toolCalls !== []) {
            $message['tool_calls'] = $toolCalls;
        }

        return $message;
    }

    /**
     * Build the messages to append after running the tools requested in a response.
     *
     * @param   Response  $response  The response with tool calls
     * @param   array     $results   Tool call id (as returned by getToolCalls()) => result
     *
     * @return  array  The assistant message and one "tool" message per result
     * @since   __DEPLOY_VERSION__
     */
    public function buildToolResultMessages(Response $response, array $results): array
    {
        $messages = [$this->buildAssistantMessage($response)];

        foreach ($response->getToolCalls() as $call) {
            if (!array_key_exists($call['id'], $results)) {
                throw InvalidArgumentException::missingParameter("result for tool call '{$call['id']}'", 'ollama');
            }

            $messages[] = [
                'role' => 'tool',
                'tool_name' => $call['name'],
                'content' => $this->encodeToolResult($results[$call['id']]),
            ];
        }

        return $messages;
    }

    /**
     * Send a chat message to the Ollama server
     *
     * @param   string  $message   The user message to send
     * @param   array   $options  Additional options
     *
     * @return  Response  The AI response
     * @throws  ProviderException  If the request fails
     * @since   __DEPLOY_VERSION__
     */
    public function chat(string $message, array $options = []): Response
    {
        if (!empty($options['stream'])) {
            return $this->consumeStream($this->chatStream($message, $options), $options['on_chunk'] ?? null);
        }

        $payload = $this->buildChatRequestPayload($message, $options);

        $endpoint = $this->getChatEndpoint();

        $jsonData = json_encode($payload);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Failed to encode request data: ' . json_last_error_msg()]
            );
        }

        $httpResponse = $this->makePostRequest(
            $endpoint,
            $jsonData,
        );

        return $this->parseOllamaResponse($httpResponse->getBody(), true);
    }

    /**
     * Stream a chat message. Yields text deltas; getReturn() gives the final Response.
     *
     * @param   string  $message  The user message to send
     * @param   array   $options  The same options as chat()
     *
     * @return  \Generator<int, string, mixed, Response>
     * @since   __DEPLOY_VERSION__
     */
    public function chatStream(string $message, array $options = []): \Generator
    {
        $this->assertNoToolsWhenStreaming($options);

        $payload = $this->buildChatRequestPayload($message, $options);
        $payload['stream'] = true;

        return yield from $this->streamOllama($this->getChatEndpoint(), $payload, true);
    }

    /**
     * Send a message with an image to a multimodal Ollama model (e.g. llava, llama3.2-vision).
     *
     * @param   string  $message  The question about the image
     * @param   string  $image    Base64 data URI or raw base64 image data
     * @param   array   $options  Additional options (same as chat())
     *
     * @return  Response  The AI response
     * @throws  InvalidArgumentException  If the image is not base64 data
     * @since   __DEPLOY_VERSION__
     */
    public function vision(string $message, string $image, array $options = []): Response
    {
        $options['images'] = [$image];

        return $this->chat($message, $options);
    }

    /**
     * Create embeddings using the Ollama /api/embed endpoint.
     *
     * @param   string|array  $input    Text or list of texts to embed
     * @param   string        $model    The embedding model (e.g. nomic-embed-text)
     * @param   array         $options  Optional: truncate, dimensions, keep_alive, options
     *
     * @return  Response  Embedding vector (single input) or list of vectors as JSON
     * @throws  ProviderException  If the request fails
     * @since   __DEPLOY_VERSION__
     */
    public function createEmbeddings(string|array $input, string $model, array $options = []): Response
    {
        $this->validateConnection();
        $this->ensureModelAvailable($model);

        $payload = [
            'model' => $model,
            'input' => $input
        ];

        foreach (['truncate', 'dimensions', 'keep_alive', 'options'] as $key) {
            if (isset($options[$key])) {
                $payload[$key] = $options[$key];
            }
        }

        $jsonData = json_encode($payload);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Failed to encode embed request: ' . json_last_error_msg()]
            );
        }

        $httpResponse = $this->makePostRequest($this->getEmbedEndpoint(), $jsonData);
        $data = $this->parseJsonResponse($httpResponse->getBody());

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        $this->assertResponseFields($data, ['embeddings[]'], (string) $httpResponse->getBody());

        $embeddings = $data['embeddings'];
        $content = json_encode(is_array($input) ? $embeddings : ($embeddings[0] ?? []));

        $metadata = [
            'model' => $data['model'] ?? $model,
            'embedding_count' => count($embeddings),
            'embeddings' => array_map(fn ($vector): array => array_map('floatval', (array) $vector), $embeddings),
            'input_type' => is_array($input) ? 'array' : 'string',
            'total_duration' => $data['total_duration'] ?? 0,
            'load_duration' => $data['load_duration'] ?? 0,
            'prompt_eval_count' => $data['prompt_eval_count'] ?? 0
        ] + $this->normaliseUsage($data['prompt_eval_count'] ?? null, null);

        return $this->recordResponse(new Response($content, $this->getName(), $metadata, 200));
    }

    /**
     * Get locally available models that report the "embedding" capability.
     *
     * @return  array  Model names
     * @since   __DEPLOY_VERSION__
     */
    public function getEmbeddingModels(): array
    {
        $models = [];

        foreach ($this->getAvailableModels() as $model) {
            $response = $this->makePostRequest($this->getShowEndpoint(), json_encode(['model' => $model]));
            $data = $this->parseJsonResponse($response->getBody());

            if (in_array('embedding', (array) ($data['capabilities'] ?? []), true)) {
                $models[] = $model;
            }
        }

        return $models;
    }

    /**
     * Convert images to the raw base64 strings Ollama expects.
     *
     * @param   array  $images  Base64 data URIs or raw base64 strings
     *
     * @return  array
     * @throws  InvalidArgumentException  If an image is not base64 data
     * @since   __DEPLOY_VERSION__
     */
    private function normaliseImages(array $images): array
    {
        $normalised = [];

        foreach ($images as $image) {
            if (is_string($image) && preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,(.+)$/s', $image, $matches)) {
                $image = $matches[1];
            }

            if (!is_string($image) || $image === '' || base64_decode($image, true) === false) {
                throw InvalidArgumentException::invalidParameter('image', $image, 'ollama', 'Images must be base64 data URIs or raw base64 strings. URLs and file paths are not supported.');
            }

            $normalised[] = $image;
        }

        return $normalised;
    }

    /**
     * Build the request payload for the generate endpoint
     *
     * @param   string  $prompt   The prompt to generate a response for
     * @param   array   $options  Additional options
     * @return  array   The formatted payload
     * @throws  InvalidArgumentException  If options are invalid
     * @since   __DEPLOY_VERSION__
     */
    public function buildGenerateRequestPayload(string $prompt, array $options = []): array
    {
        $this->validateConnection();

        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'tinyllama');
        $this->ensureModelAvailable($model);

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false
        ];

        // Handle optional parameters
        if (isset($options['stream'])) {
            $payload['stream'] = (bool) $options['stream'];
        }

        if (isset($options['suffix'])) {
            $payload['suffix'] = $options['suffix'];
        }

        if (!empty($options['images'])) {
            $payload['images'] = $this->normaliseImages((array) $options['images']);
        }

        if (isset($options['format'])) {
            $payload['format'] = $options['format'];
        }

        $schema = $this->getResponseSchema($options);

        if ($schema !== null) {
            $payload['format'] = $schema['schema'];
        }

        if (isset($options['options']) && is_array($options['options'])) {
            $payload['options'] = $options['options'];
        }

        if (isset($options['system'])) {
            $payload['system'] = $options['system'];
        }

        if (isset($options['template'])) {
            $payload['template'] = $options['template'];
        }

        if (isset($options['context'])) {
            $payload['context'] = $options['context'];
        }

        if (isset($options['raw'])) {
            $payload['raw'] = (bool) $options['raw'];
        }

        if (isset($options['keep_alive'])) {
            $payload['keep_alive'] = $options['keep_alive'];
        }

        return $payload;
    }

    /**
     * Generate a completion for a given prompt
     *
     * @param   string    $prompt    The prompt to generate a response for
     * @param   array     $options   Additional options
     *
     * @return  Response  The AI response
     * @throws  ProviderException  If the request fails
     * @since   __DEPLOY_VERSION__
     */
    public function generate(string $prompt, array $options = []): Response
    {
        if (!empty($options['stream'])) {
            return $this->consumeStream($this->generateStream($prompt, $options), $options['on_chunk'] ?? null);
        }

        $payload = $this->buildGenerateRequestPayload($prompt, $options);

        $endpoint = $this->getGenerateEndpoint();

        $jsonData = json_encode($payload);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProviderException(
                $this->getName(),
                ['message' => 'Failed to encode request data: ' . json_last_error_msg()]
            );
        }

        $httpResponse = $this->makePostRequest(
            $endpoint,
            $jsonData,
        );

        return $this->parseOllamaResponse($httpResponse->getBody(), false);
    }

    /**
     * Stream a completion. Yields text deltas; getReturn() gives the final Response.
     *
     * @param   string  $prompt   The prompt to generate a response for
     * @param   array   $options  The same options as generate()
     *
     * @return  \Generator<int, string, mixed, Response>
     * @since   __DEPLOY_VERSION__
     */
    public function generateStream(string $prompt, array $options = []): \Generator
    {
        $payload = $this->buildGenerateRequestPayload($prompt, $options);
        $payload['stream'] = true;

        return yield from $this->streamOllama($this->getGenerateEndpoint(), $payload, false);
    }

    /**
     * Send a streaming request to Ollama and yield text deltas from the NDJSON response.
     *
     * @param   string  $endpoint  The chat or generate endpoint
     * @param   array   $payload   The request payload with stream=true
     * @param   bool    $isChat    Whether this is a chat (true) or generate (false) stream
     *
     * @return  \Generator<int, string, mixed, Response>
     * @since   __DEPLOY_VERSION__
     */
    private function streamOllama(string $endpoint, array $payload, bool $isChat): \Generator
    {
        $chunks = $this->makeStreamingPostRequest($endpoint, json_encode($payload), ['Content-Type' => 'application/json']);

        $fullContent = '';
        $lastMetadata = [];
        $validChunks = 0;

        foreach ($this->readLines($chunks) as $line) {
            $data = $this->decodeStreamEvent($line);

            if (isset($data['error'])) {
                throw new ProviderException($this->getName(), $data);
            }

            $validChunks++;

            // Each line carries a piece of the reply
            $delta = $isChat ? ($data['message']['content'] ?? null) : ($data['response'] ?? null);

            if (is_string($delta) && $delta !== '') {
                $fullContent .= $delta;

                yield $delta;
            }

            // Keep track of the last metadata
            if (($data['done'] ?? false) === true) {
                $lastMetadata = [
                    'model' => $data['model'] ?? null,
                    'created_at' => $data['created_at'] ?? null,
                    'done_reason' => $data['done_reason'] ?? null,
                    'done' => true,
                    'total_duration' => $data['total_duration'] ?? 0,
                    'load_duration' => $data['load_duration'] ?? 0,
                    'prompt_eval_count' => $data['prompt_eval_count'] ?? 0,
                    'prompt_eval_duration' => $data['prompt_eval_duration'] ?? 0,
                    'eval_count' => $data['eval_count'] ?? 0,
                    'eval_duration' => $data['eval_duration'] ?? 0,
                    'finish_reason' => $data['done_reason'] ?? null
                ] + $this->normaliseUsage($data['prompt_eval_count'] ?? null, $data['eval_count'] ?? null);

                // Add chat-specific metadata
                if ($isChat && isset($data['message']['role'])) {
                    $lastMetadata['role'] = $data['message']['role'];
                }

                // Add generate-specific metadata
                if (!$isChat && isset($data['context'])) {
                    $lastMetadata['context'] = $data['context'];
                }
            }
        }

        if ($validChunks === 0) {
            throw new UnserializableResponseException($this->getName(), '', 'No valid JSON chunks in streaming response');
        }

        $lastMetadata['stream'] = true;

        $statusCode = isset($lastMetadata['done_reason']) ? $this->determineAIStatusCode($lastMetadata) : 200;

        return $this->recordResponse(new Response(
            $fullContent,
            $this->getName(),
            $lastMetadata,
            $statusCode
        ));
    }

    /**
     * Parse a non-streaming response from Ollama API (works for both chat and generate endpoints)
     *
     * @param   string  $responseBody  The raw response body
     * @param   bool    $isChat        Whether this is a chat response (true) or generate response (false)
     *
     * @return  Response  The parsed response
     * @throws  ProviderException  If the response contains an error
     * @since   __DEPLOY_VERSION__
     */
    private function parseOllamaResponse(string $responseBody, bool $isChat = true): Response
    {
        $data = $this->parseJsonResponse($responseBody);

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        $this->assertResponseFields($data, [$isChat ? 'message[]' : 'response'], $responseBody);

        // Extract content based on whether it's a chat or generate response
        $content = (string) ($isChat ? ($data['message']['content'] ?? '') : $data['response']);

        $statusCode = isset($data['done_reason']) ? $this->determineAIStatusCode($data) : 200;

        // Build common metadata
        $metadata = [
            'model' => $data['model'] ?? null,
            'created_at' => $data['created_at'] ?? $data['created'] ?? time(),
            'done_reason' => $data['done_reason'] ?? null,
            'done' => $data['done'] ?? true,
            'total_duration' => $data['total_duration'] ?? 0,
            'load_duration' => $data['load_duration'] ?? 0,
            'prompt_eval_count' => $data['prompt_eval_count'] ?? 0,
            'prompt_eval_duration' => $data['prompt_eval_duration'] ?? 0,
            'eval_count' => $data['eval_count'] ?? 0,
            'eval_duration' => $data['eval_duration'] ?? 0,
            'finish_reason' => $data['done_reason'] ?? null
        ] + $this->normaliseUsage($data['prompt_eval_count'] ?? null, $data['eval_count'] ?? null);

        // Add chat-specific metadata
        if ($isChat && isset($data['message']['role'])) {
            $metadata['role'] = $data['message']['role'];
        }

        if ($isChat) {
            $rawToolCalls = is_array($data['message']['tool_calls'] ?? null) ? $data['message']['tool_calls'] : [];
            $metadata['raw_tool_calls'] = $rawToolCalls;
            $metadata['tool_calls'] = [];

            foreach ($rawToolCalls as $index => $toolCall) {
                $arguments = $toolCall['function']['arguments'] ?? [];
                $arguments = is_string($arguments) ? json_decode($arguments, true) : $arguments;

                if (!is_string($toolCall['function']['name'] ?? null) || !is_array($arguments)) {
                    throw new UnserializableResponseException($this->getName(), $responseBody, 'Tool call in response is missing a function name or valid arguments');
                }

                // Older Ollama versions do not return ids
                $metadata['tool_calls'][] = [
                    'id' => (string) ($toolCall['id'] ?? 'call_' . $index),
                    'name' => $toolCall['function']['name'],
                    'arguments' => $arguments,
                ];
            }
        }

        // Add generate-specific metadata
        if (!$isChat && isset($data['context'])) {
            $metadata['context'] = $data['context'];
        }

        return $this->recordResponse(new Response(
            $content,
            $this->getName(),
            $metadata,
            $statusCode
        ));
    }

    private function determineAIStatusCode(array $data): int
    {
        $finishReason = $data['done_reason'] ?? null;

        switch ($finishReason) {
            case 'stop':
                return 200;

            case 'length':
                return 206;

            case 'content_filter':
                return 422;

            case 'tool_calls':
            case 'function_call':
                return 202;

            default:
                return 200;
        }
    }
}
