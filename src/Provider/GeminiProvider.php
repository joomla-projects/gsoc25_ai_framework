<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Provider;

use Joomla\AI\AbstractProvider;
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\RateLimitException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Interface\ChatInterface;
use Joomla\AI\Interface\EmbeddingInterface;
use Joomla\AI\Interface\ImageInterface;
use Joomla\AI\Interface\ModelInterface;
use Joomla\AI\Interface\ProviderInterface;
use Joomla\AI\Interface\StreamInterface;
use Joomla\AI\Interface\ToolInterface;
use Joomla\AI\Response\Response;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;

/**
 * Google Gemini provider implementation.
 *
 * Uses the Gemini Interactions API (POST /v1beta/interactions) for chat, vision, tools, JSON output,
 * streaming and image generation, and models/{model}:batchEmbedContents for embeddings.
 * Requests are sent with store=false and the full history, like the other providers.
 *
 * @since  __DEPLOY_VERSION__
 */
class GeminiProvider extends AbstractProvider implements ProviderInterface, ChatInterface, StreamInterface, ToolInterface, ImageInterface, EmbeddingInterface, ModelInterface
{
    /**
     * Default chat and vision model
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    private const DEFAULT_MODEL = 'gemini-3.8-flash';

    /**
     * Default image generation and editing model
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    private const DEFAULT_IMAGE_MODEL = 'gemini-nano-banana-2.1';

    /**
     * Content item types that make up a user turn (as opposed to steps such as function_result)
     *
     * @var    string[]
     * @since  __DEPLOY_VERSION__
     */
    private const CONTENT_TYPES = ['text', 'image', 'audio', 'document', 'video'];

    /**
     * Image MIME types accepted as input
     *
     * @var    array<string, string>
     * @since  __DEPLOY_VERSION__
     */
    private const IMAGE_MIME_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'heic' => 'image/heic',
        'heif' => 'image/heif',
    ];

    /**
     * Base URL for API requests
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    private string $baseUrl;

    /**
     * Constructor.
     *
     * @param   array|\ArrayAccess  $options      Provider options array.
     * @param   HttpFactory|null    $httpFactory  The http factory
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(array|\ArrayAccess $options = [], ?HttpFactory $httpFactory = null)
    {
        parent::__construct($options, $httpFactory);

        $this->baseUrl = rtrim((string) $this->getOption('base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
    }

    /**
     * Check if the Gemini provider is supported.
     *
     * The API key comes from the api_key option and is checked when a request is made.
     *
     * @return  boolean  Always true
     * @since  __DEPLOY_VERSION__
     */
    public static function isSupported(): bool
    {
        return true;
    }

    /**
     * Get the provider name.
     *
     * @return  string
     * @since  __DEPLOY_VERSION__
     */
    public function getName(): string
    {
        return 'Gemini';
    }

    /**
     * Get the default capability map for Gemini.
     * Override via the "capabilities" provider option or in a subclass.
     *
     * The lists are only used by isModelCapable() and getEmbeddingModels(); requests are not
     * blocked for models missing from them.
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    protected function getDefaultCapabilities(): array
    {
        $textModels = [
            'gemini-3.8-flash',
            'gemini-3.7-flash',
            'gemini-3.6-flash',
            'gemini-3.5-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-3.1-pro-preview',
            'gemini-pro-latest',
            'gemini-flash-latest',
            'gemini-flash-lite-latest',
        ];

        return [
            'models' => [
                'chat'      => $textModels,
                'vision'    => $textModels,
                'image'     => ['gemini-nano-banana-2.1', 'gemini-3.1-flash-image', 'gemini-3.1-flash-lite-image', 'gemini-3-pro-image'],
                'embedding' => ['gemini-embedding-2', 'gemini-embedding-001'],
            ],
            'image_edit_max_images' => 14,
        ];
    }

    /**
     * List the models available to the API key (IDs without the "models/" prefix).
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    public function getAvailableModels(): array
    {
        $response = $this->makeGetRequest($this->baseUrl . '/models?pageSize=1000', $this->buildHeaders());
        $data = $this->parseJsonResponse((string) $response->getBody());

        $this->assertResponseFields($data, ['models[]'], (string) $response->getBody());

        return array_map(
            fn (string $name): string => str_starts_with($name, 'models/') ? substr($name, 7) : $name,
            array_column($data['models'], 'name')
        );
    }

    /**
     * Check if a model supports a capability (chat, vision, image, embedding).
     *
     * @param   string  $model       The model name
     * @param   string  $capability  The capability
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    public function isModelCapable(string $model, string $capability): bool
    {
        return $this->checkModelCapability($model, $capability, $this->getCapability('models', null, []));
    }

    /**
     * Get the known embedding models.
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    public function getEmbeddingModels(): array
    {
        return $this->getCapability('models', 'embedding', []);
    }

    /**
     * Send a message to Gemini and return the response.
     *
     * Options: model, messages, system, max_tokens, temperature, top_p, stop, seed, thinking_level
     * (minimal, low, medium, high), tools, tool_choice, response_schema, store, stream, on_chunk.
     *
     * @param   string  $message  The message to send
     * @param   array   $options  Additional options
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function chat(string $message, array $options = []): Response
    {
        if (!empty($options['stream'])) {
            return $this->consumeStream($this->chatStream($message, $options), $options['on_chunk'] ?? null);
        }

        $payload = $this->buildInteractionPayload($message, $options);

        return $this->parseInteraction((string) $this->postJson($this->getInteractionsEndpoint(), $payload)->getBody());
    }

    /**
     * Stream a chat response. Yields text deltas; getReturn() gives the final Response.
     *
     * @param   string  $message  The message to send
     * @param   array   $options  The same options as chat()
     *
     * @return  \Generator<int, string, mixed, Response>
     * @since   __DEPLOY_VERSION__
     */
    public function chatStream(string $message, array $options = []): \Generator
    {
        $this->assertNoToolsWhenStreaming($options);

        $payload = $this->buildInteractionPayload($message, $options);
        $payload['stream'] = true;

        $chunks = $this->makeStreamingPostRequest($this->getInteractionsEndpoint(), (string) json_encode($payload), $this->buildHeaders());

        $content = '';
        $stepTypes = [];
        $interaction = ['id' => null, 'model' => $payload['model'], 'status' => null, 'usage' => []];

        foreach ($this->readServerSentEvents($chunks) as $event) {
            $data = $this->decodeStreamEvent($event['data']);
            $type = $data['event_type'] ?? $event['event'];

            switch ($type) {
                case 'error':
                    throw new ProviderException($this->getName(), ['message' => $data['error']['message'] ?? 'Streaming error', 'code' => $data['error']['code'] ?? null]);

                case 'step.start':
                    $stepTypes[$data['index'] ?? 0] = $data['step']['type'] ?? null;
                    break;

                case 'step.delta':
                    $index = $data['index'] ?? 0;

                    // Only answer text; thought summaries arrive as their own delta type
                    if (($stepTypes[$index] ?? 'model_output') === 'model_output' && ($data['delta']['type'] ?? null) === 'text' && is_string($data['delta']['text'] ?? null)) {
                        $content .= $data['delta']['text'];

                        yield $data['delta']['text'];
                    }
                    break;

                case 'interaction.created':
                case 'interaction.completed':
                    if (is_array($data['interaction'] ?? null)) {
                        $interaction = array_replace($interaction, array_intersect_key($data['interaction'], $interaction));
                    }
                    break;

                case 'interaction.status_update':
                    $interaction['status'] = $data['status'] ?? $interaction['status'];
                    if (is_array($data['usage'] ?? null)) {
                        $interaction['usage'] = $data['usage'];
                    }
                    break;
            }
        }

        if ($interaction['status'] === 'failed') {
            throw new ProviderException($this->getName(), ['message' => 'The interaction failed.']);
        }

        $finishReason = $this->normaliseFinishReason($interaction['status'], false);
        $usage = is_array($interaction['usage']) ? $interaction['usage'] : [];

        $metadata = [
            'id' => $interaction['id'],
            'model' => $interaction['model'],
            'status' => $interaction['status'],
            'usage' => $usage,
            'finish_reason' => $finishReason,
            'stream' => true,
        ] + $this->normaliseGeminiUsage($usage);

        return $this->recordResponse(new Response($content, $this->getName(), $metadata, $this->statusCodeFor($finishReason)));
    }

    /**
     * Ask a question about an image.
     *
     * @param   string  $message  The question
     * @param   string  $image    A base64 data URI (PNG, JPEG, WebP, HEIC, HEIF) or an http(s) URL Gemini can fetch
     * @param   array   $options  The same options as chat(), plus "resolution" (low, medium, high, ultra_high)
     *
     * @return  Response
     * @throws  InvalidArgumentException  If the image is not a supported data URI or URL
     * @since   __DEPLOY_VERSION__
     */
    public function vision(string $message, string $image, array $options = []): Response
    {
        $imageContent = $this->imageContentFromString($image);

        if (isset($options['resolution'])) {
            if (!in_array($options['resolution'], ['low', 'medium', 'high', 'ultra_high'], true)) {
                throw InvalidArgumentException::invalidParameter('resolution', $options['resolution'], 'gemini', 'Resolution must be low, medium, high or ultra_high.');
            }

            $imageContent['resolution'] = $options['resolution'];
        }

        $options['messages'] = [['role' => 'user', 'content' => [['type' => 'text', 'text' => $message], $imageContent]]];

        return $this->chat($message, $options);
    }

    /**
     * Generate an image from a text prompt.
     *
     * @param   string  $prompt   Description of the image
     * @param   array   $options  model (default: the "image_model" option, else gemini-nano-banana-2.1),
     *                            aspect_ratio (e.g. 16:9), image_size (512, 1K, 2K, 4K)
     *
     * @return  Response  Base64 image data (response_format b64_json)
     * @since   __DEPLOY_VERSION__
     */
    public function generateImage(string $prompt, array $options = []): Response
    {
        return $this->createImage($prompt, [], $options);
    }

    /**
     * Edit one or more images with a text instruction.
     *
     * @param   string|array  $imagePath  Path, or up to 14 paths (PNG, JPEG, WebP, HEIC, HEIF)
     * @param   string        $prompt     Description of the change
     * @param   array         $options    The same options as generateImage()
     *
     * @return  Response  Base64 image data (response_format b64_json)
     * @throws  InvalidArgumentException  If a file is missing, unsupported or there are too many
     * @since   __DEPLOY_VERSION__
     */
    public function editImage(string|array $imagePath, string $prompt, array $options = []): Response
    {
        $paths = (array) $imagePath;
        $max = (int) $this->getCapability('image_edit_max_images', null, 14);

        if ($paths === [] || count($paths) > $max) {
            throw InvalidArgumentException::invalidParameter('images', $paths, 'gemini', "Provide between 1 and $max images.");
        }

        $images = [];
        foreach ($paths as $path) {
            $images[] = $this->imageContentFromFile((string) $path);
        }

        return $this->createImage($prompt, $images, $options);
    }

    /**
     * Image variations are not available in the Gemini API; use editImage() with an instruction instead.
     *
     * @param   string  $imagePath  Path to the image
     * @param   array   $options    Options
     *
     * @return  Response
     * @throws  ProviderException  Always
     * @since   __DEPLOY_VERSION__
     */
    public function createImageVariation(string $imagePath, array $options = []): Response
    {
        throw new ProviderException($this->getName(), ['message' => 'Image variations are not supported by the Gemini provider. Use editImage() with an instruction instead.']);
    }

    /**
     * Create embeddings for one or more texts.
     *
     * @param   string|array  $input    Text or list of texts
     * @param   string        $model    Embedding model, e.g. gemini-embedding-2
     * @param   array         $options  dimensions (128-3072), task_type (gemini-embedding-001 only)
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function createEmbeddings(string|array $input, string $model, array $options = []): Response
    {
        $texts = is_array($input) ? array_values($input) : [$input];

        if ($texts === []) {
            throw InvalidArgumentException::invalidParameter('input', $input, 'gemini', 'Input must not be empty.');
        }

        $modelName = str_starts_with($model, 'models/') ? $model : 'models/' . $model;
        $requests = [];

        foreach ($texts as $text) {
            $request = ['model' => $modelName, 'content' => ['parts' => [['text' => (string) $text]]]];

            if (isset($options['dimensions'])) {
                $request['output_dimensionality'] = (int) $options['dimensions'];
            }

            if (isset($options['task_type'])) {
                $request['task_type'] = $options['task_type'];
            }

            $requests[] = $request;
        }

        $endpoint = $this->baseUrl . '/' . $modelName . ':batchEmbedContents';
        $body = (string) $this->postJson($endpoint, ['requests' => $requests])->getBody();
        $data = $this->parseJsonResponse($body);

        $this->assertResponseFields($data, ['embeddings[]'], $body);

        $vectors = [];
        foreach ($data['embeddings'] as $embedding) {
            if (!is_array($embedding['values'] ?? null)) {
                throw new UnserializableResponseException($this->getName(), $body, 'Embedding entries in response must contain values');
            }

            $vectors[] = array_map('floatval', $embedding['values']);
        }

        $metadata = [
            'model' => substr($modelName, 7),
            'embedding_count' => count($vectors),
            'input_type' => is_array($input) ? 'array' : 'string',
            'embeddings' => $vectors,
        ];

        return $this->recordResponse(new Response((string) json_encode(is_array($input) ? $vectors : $vectors[0]), $this->getName(), $metadata, 200));
    }

    /**
     * Build the assistant message for a conversation history.
     *
     * Uses the response's steps unchanged (thought signatures included), as Gemini expects
     * earlier model steps to be sent back exactly as returned.
     *
     * @param   Response  $response  The response
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    public function buildAssistantMessage(Response $response): array
    {
        $steps = $response->getMetadata()['steps'] ?? null;

        if (is_array($steps) && $steps !== []) {
            return ['role' => 'assistant', 'content' => $steps];
        }

        return parent::buildAssistantMessage($response);
    }

    /**
     * Build the messages to append after running the tools requested in a response.
     *
     * @param   Response  $response  The response with tool calls
     * @param   array     $results   Tool call id => result (string, or a JSON-encodable value)
     *
     * @return  array  The assistant turn and a user turn with function_result steps
     * @since   __DEPLOY_VERSION__
     */
    public function buildToolResultMessages(Response $response, array $results): array
    {
        $resultSteps = [];

        foreach ($response->getToolCalls() as $call) {
            if (!array_key_exists($call['id'], $results)) {
                throw InvalidArgumentException::missingParameter("result for tool call '{$call['id']}'", 'gemini');
            }

            $result = $results[$call['id']];

            $resultSteps[] = [
                'type' => 'function_result',
                'call_id' => $call['id'],
                'name' => $call['name'],
                // Objects are passed as they are; anything else as text
                'result' => is_array($result) && !array_is_list($result) ? $result : $this->encodeToolResult($result),
            ];
        }

        return [
            $this->buildAssistantMessage($response),
            ['role' => 'user', 'content' => $resultSteps],
        ];
    }

    /**
     * Use Gemini's RetryInfo to tell per-minute rate limits apart from exhausted quota.
     *
     * Gemini answers both with 429 RESOURCE_EXHAUSTED, often worded as "exceeded your current quota".
     * A RetryInfo detail means the request can succeed after the given delay.
     *
     * @param   HttpResponse  $response  The response
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    protected function isRetryableResponse(HttpResponse $response): bool
    {
        if ($response->getStatusCode() === 429 && $this->getRetryInfoDelay($response) !== null) {
            return true;
        }

        return parent::isRetryableResponse($response);
    }

    /**
     * Seconds to wait before retrying, preferring Gemini's RetryInfo delay.
     *
     * @param   HttpResponse  $response  The response
     * @param   int           $attempt   Zero-based attempt number
     *
     * @return  float
     * @since   __DEPLOY_VERSION__
     */
    protected function getRetryDelay(HttpResponse $response, int $attempt): float
    {
        $delay = $this->getRetryInfoDelay($response);

        if ($delay !== null) {
            return min((float) $this->getOption('retry_max_delay', 60), $delay);
        }

        return parent::getRetryDelay($response, $attempt);
    }

    /**
     * Report a 429 with RetryInfo as a rate limit rather than exhausted quota.
     *
     * @param   HttpResponse  $response  HTTP response
     *
     * @return  boolean
     * @throws  RateLimitException|\Exception
     * @since   __DEPLOY_VERSION__
     */
    protected function validateResponse(HttpResponse $response): bool
    {
        if ($response->getStatusCode() === 429 && $this->getRetryInfoDelay($response) !== null) {
            $errorData = json_decode((string) $response->getBody(), true);

            throw new RateLimitException($this->getName(), is_array($errorData) ? $errorData : [], 429);
        }

        return parent::validateResponse($response);
    }

    /**
     * Build the Interactions API payload.
     *
     * @param   string  $message  The user message
     * @param   array   $options  Request options
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    private function buildInteractionPayload(string $message, array $options): array
    {
        if (isset($options['messages']) && (!is_array($options['messages']) || $options['messages'] === [])) {
            throw InvalidArgumentException::invalidMessages('gemini', 'Messages must be a non-empty array.');
        }

        $messages = $options['messages'] ?? [['role' => 'user', 'content' => $message]];
        [$input, $systemFromMessages] = $this->messagesToInput($messages);

        $payload = [
            'model' => $options['model'] ?? $this->defaultModel ?? $this->getOption('model', self::DEFAULT_MODEL),
            'input' => $input,
            // Stateless: the full history is sent every time
            'store' => (bool) ($options['store'] ?? false),
        ];

        $system = trim(implode("\n\n", array_filter([(string) ($options['system'] ?? ''), $systemFromMessages])));
        if ($system !== '') {
            $payload['system_instruction'] = $system;
        }

        $config = [];
        foreach (['temperature' => 'float', 'top_p' => 'float', 'seed' => 'int'] as $key => $type) {
            if (isset($options[$key])) {
                $config[$key] = $type === 'int' ? (int) $options[$key] : (float) $options[$key];
            }
        }

        if (isset($options['max_tokens'])) {
            $config['max_output_tokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['stop'])) {
            $config['stop_sequences'] = array_values((array) $options['stop']);
        }

        if (isset($options['thinking_level'])) {
            if (!in_array($options['thinking_level'], ['minimal', 'low', 'medium', 'high'], true)) {
                throw InvalidArgumentException::invalidParameter('thinking_level', $options['thinking_level'], 'gemini', 'Thinking level must be minimal, low, medium or high.');
            }

            $config['thinking_level'] = $options['thinking_level'];
        }

        $tools = $this->getToolDefinitions($options);

        if ($tools !== []) {
            $payload['tools'] = array_map(fn (array $tool): array => [
                'type' => 'function',
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['parameters'],
            ], $tools);

            if (isset($options['tool_choice'])) {
                $config['tool_choice'] = $this->mapToolChoice($options['tool_choice']);
            }
        }

        if ($config !== []) {
            $payload['generation_config'] = $config;
        }

        $schema = $this->getResponseSchema($options);

        if ($schema !== null) {
            $payload['response_format'] = ['type' => 'text', 'mime_type' => 'application/json', 'schema' => $schema['schema']];
            $payload['response_mime_type'] = 'application/json';
        }

        return $payload;
    }

    /**
     * Convert chat messages to Interactions API input steps.
     *
     * User text becomes a user_input step, assistant text a model_output step. Assistant turns from
     * buildAssistantMessage() hold steps and are passed through, as are function_result steps.
     * "system" messages are collected and returned separately.
     *
     * @param   array  $messages  Messages in the framework's role/content form
     *
     * @return  array{0: array, 1: string}  The input steps and any system text
     * @throws  InvalidArgumentException  For an unknown role
     * @since   __DEPLOY_VERSION__
     */
    private function messagesToInput(array $messages): array
    {
        $input = [];
        $system = [];

        foreach ($messages as $index => $message) {
            $role = $message['role'] ?? null;
            $content = $message['content'] ?? '';

            if ($role === 'system') {
                $system[] = (string) $content;
                continue;
            }

            if (!in_array($role, ['user', 'assistant', 'model'], true)) {
                throw InvalidArgumentException::invalidParameter('role', $role, 'gemini', "Message at index $index must have role user, assistant or system.");
            }

            if (is_string($content)) {
                $input[] = [
                    'type' => $role === 'user' ? 'user_input' : 'model_output',
                    'content' => [['type' => 'text', 'text' => $content]],
                ];
                continue;
            }

            if (!is_array($content)) {
                throw InvalidArgumentException::invalidParameter('content', $content, 'gemini', "Message at index $index must have string or array content.");
            }

            $isContent = $content !== [] && array_reduce($content, fn (bool $carry, $item): bool => $carry && in_array($item['type'] ?? null, self::CONTENT_TYPES, true), true);

            if ($isContent) {
                $input[] = ['type' => $role === 'user' ? 'user_input' : 'model_output', 'content' => array_values($content)];
            } else {
                // Steps (model output, thoughts, function calls and results) are sent back unchanged
                foreach ($content as $step) {
                    $input[] = $step;
                }
            }
        }

        return [$input, implode("\n\n", $system)];
    }

    /**
     * Map the provider-neutral tool_choice option.
     *
     * @param   mixed  $choice  "auto", "none", "required", or ['name' => tool name]
     *
     * @return  string|array
     * @since   __DEPLOY_VERSION__
     */
    private function mapToolChoice(mixed $choice): string|array
    {
        return match (true) {
            $choice === 'auto', $choice === 'none' => $choice,
            $choice === 'required' => 'any',
            is_array($choice) && isset($choice['name']) => ['allowed_tools' => ['mode' => 'any', 'tools' => [(string) $choice['name']]]],
            is_array($choice) => $choice,
            default => throw InvalidArgumentException::invalidParameter('tool_choice', $choice, 'gemini', 'tool_choice must be "auto", "none", "required" or [\'name\' => tool].'),
        };
    }

    /**
     * Generate or edit an image.
     *
     * @param   string  $prompt   The instruction
     * @param   array   $images   Input image content items
     * @param   array   $options  Request options
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    private function createImage(string $prompt, array $images, array $options): Response
    {
        $format = ['type' => 'image'];

        if (isset($options['aspect_ratio'])) {
            $format['aspect_ratio'] = (string) $options['aspect_ratio'];
        }

        if (isset($options['image_size'])) {
            if (!in_array($options['image_size'], ['512', '1K', '2K', '4K'], true)) {
                throw InvalidArgumentException::invalidParameter('image_size', $options['image_size'], 'gemini', 'Image size must be 512, 1K, 2K or 4K.');
            }

            $format['image_size'] = $options['image_size'];
        }

        $payload = [
            // Not the chat default model: images need an image model
            'model' => $options['model'] ?? $this->getOption('image_model', self::DEFAULT_IMAGE_MODEL),
            'input' => array_merge([['type' => 'text', 'text' => $prompt]], $images),
            'response_format' => $format,
            'store' => false,
        ];

        $body = (string) $this->postJson($this->getInteractionsEndpoint(), $payload)->getBody();
        $data = $this->decodeInteraction($body);

        $images = [];
        $text = '';

        foreach ($data['steps'] as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            foreach ((array) ($step['content'] ?? []) as $item) {
                if (($item['type'] ?? null) === 'image' && is_string($item['data'] ?? null)) {
                    $images[] = ['b64_json' => $item['data'], 'mime_type' => $item['mime_type'] ?? 'image/jpeg'];
                } elseif (($item['type'] ?? null) === 'text') {
                    $text .= (string) ($item['text'] ?? '');
                }
            }
        }

        if ($images === []) {
            throw new ProviderException($this->getName(), ['message' => 'No image was generated.' . ($text !== '' ? ' The model said: ' . $text : '')]);
        }

        $values = array_column($images, 'b64_json');
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        $metadata = [
            'id' => $data['id'] ?? null,
            'model' => $data['model'] ?? $payload['model'],
            'response_format' => 'b64_json',
            'format' => 'base64_' . str_replace('image/', '', $images[0]['mime_type']),
            'image_count' => count($images),
            'images' => $images,
            'text' => $text,
            'usage' => $usage,
        ] + $this->normaliseGeminiUsage($usage);

        return $this->recordResponse(new Response(count($values) === 1 ? $values[0] : (string) json_encode($values), $this->getName(), $metadata, 200));
    }

    /**
     * Parse an interaction into a Response.
     *
     * @param   string  $body  The response body
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    private function parseInteraction(string $body): Response
    {
        $data = $this->decodeInteraction($body);

        $content = '';
        $toolCalls = [];

        foreach ($data['steps'] as $step) {
            if (!is_array($step)) {
                continue;
            }

            if (($step['type'] ?? null) === 'model_output') {
                foreach ((array) ($step['content'] ?? []) as $item) {
                    if (($item['type'] ?? null) === 'text') {
                        $content .= (string) ($item['text'] ?? '');
                    }
                }
            } elseif (($step['type'] ?? null) === 'function_call') {
                if (!is_string($step['name'] ?? null) || !is_string($step['id'] ?? null)) {
                    throw new UnserializableResponseException($this->getName(), $body, 'function_call step is missing a name or id');
                }

                $arguments = $step['arguments'] ?? [];
                $arguments = is_string($arguments) ? json_decode($arguments, true) : $arguments;

                if (!is_array($arguments)) {
                    throw new UnserializableResponseException($this->getName(), $body, "Arguments for tool '{$step['name']}' are not valid JSON");
                }

                $toolCalls[] = ['id' => $step['id'], 'name' => $step['name'], 'arguments' => $arguments];
            }
        }

        $finishReason = $this->normaliseFinishReason($data['status'] ?? null, $toolCalls !== []);
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        $metadata = [
            'id' => $data['id'] ?? null,
            'model' => $data['model'] ?? null,
            'status' => $data['status'] ?? null,
            'usage' => $usage,
            'finish_reason' => $finishReason,
            'tool_calls' => $toolCalls,
            // Kept so the turn can be sent back unchanged in a conversation
            'steps' => $data['steps'],
        ] + $this->normaliseGeminiUsage($usage);

        return $this->recordResponse(new Response($content, $this->getName(), $metadata, $this->statusCodeFor($finishReason)));
    }

    /**
     * Decode and check an interaction; throw for API errors and failed interactions.
     *
     * @param   string  $body  The response body
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    private function decodeInteraction(string $body): array
    {
        $data = $this->parseJsonResponse($body);

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        $this->assertResponseFields($data, ['steps[]'], $body);

        if (($data['status'] ?? null) === 'failed') {
            $error = $data['errors'][0] ?? [];

            throw new ProviderException($this->getName(), [
                'message' => is_array($error) ? (string) ($error['message'] ?? 'The interaction failed.') : 'The interaction failed.',
                'code' => is_array($error) ? ($error['code'] ?? null) : null,
            ]);
        }

        return $data;
    }

    /**
     * Map the interaction status to the normalised finish reason.
     *
     * @param   string|null  $status        The interaction status
     * @param   bool         $hasToolCalls  Whether the response asks for tool calls
     *
     * @return  string|null
     * @since   __DEPLOY_VERSION__
     */
    private function normaliseFinishReason(?string $status, bool $hasToolCalls): ?string
    {
        if ($hasToolCalls || $status === 'requires_action') {
            return 'tool_calls';
        }

        return match ($status) {
            'completed' => 'stop',
            'incomplete', 'budget_exceeded' => 'length',
            default => $status,
        };
    }

    /**
     * Status code for a finish reason, matching the other providers.
     *
     * @param   string|null  $finishReason  The normalised finish reason
     *
     * @return  int
     * @since   __DEPLOY_VERSION__
     */
    private function statusCodeFor(?string $finishReason): int
    {
        return match ($finishReason) {
            'length' => 206,
            'tool_calls' => 202,
            default => 200,
        };
    }

    /**
     * Normalise a Gemini usage block.
     *
     * @param   array  $usage  The usage block
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    private function normaliseGeminiUsage(array $usage): array
    {
        return $this->normaliseUsage(
            isset($usage['total_input_tokens']) ? (int) $usage['total_input_tokens'] : null,
            isset($usage['total_output_tokens']) ? (int) $usage['total_output_tokens'] : null,
            isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : null
        );
    }

    /**
     * Get the delay from a RetryInfo detail in an error response, in seconds.
     *
     * @param   HttpResponse  $response  The response
     *
     * @return  float|null  Null when there is no RetryInfo
     * @since   __DEPLOY_VERSION__
     */
    private function getRetryInfoDelay(HttpResponse $response): ?float
    {
        $body = (string) $response->getBody();
        $response->getBody()->rewind();
        $data = json_decode($body, true);

        foreach ((array) ($data['error']['details'] ?? []) as $detail) {
            if (is_array($detail) && str_ends_with((string) ($detail['@type'] ?? ''), 'google.rpc.RetryInfo')) {
                // Duration in protobuf JSON form, e.g. "37s" or "1.5s"
                return (float) rtrim((string) ($detail['retryDelay'] ?? '0'), 's');
            }
        }

        return null;
    }

    /**
     * Build an image content item from a data URI or URL.
     *
     * @param   string  $image  The image
     *
     * @return  array
     * @throws  InvalidArgumentException
     * @since   __DEPLOY_VERSION__
     */
    private function imageContentFromString(string $image): array
    {
        if (preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.+)$#is', $image, $matches)) {
            $mimeType = strtolower($matches[1]) === 'image/jpg' ? 'image/jpeg' : strtolower($matches[1]);

            if (!in_array($mimeType, self::IMAGE_MIME_TYPES, true)) {
                throw InvalidArgumentException::invalidParameter('image', $mimeType, 'gemini', 'Supported image types: ' . implode(', ', array_unique(self::IMAGE_MIME_TYPES)) . '.');
            }

            return ['type' => 'image', 'data' => $matches[2], 'mime_type' => $mimeType];
        }

        if (preg_match('#^https?://#i', $image) && filter_var($image, FILTER_VALIDATE_URL)) {
            $item = ['type' => 'image', 'uri' => $image];
            $extension = strtolower(pathinfo((string) parse_url($image, PHP_URL_PATH), PATHINFO_EXTENSION));

            if (isset(self::IMAGE_MIME_TYPES[$extension])) {
                $item['mime_type'] = self::IMAGE_MIME_TYPES[$extension];
            }

            return $item;
        }

        throw InvalidArgumentException::invalidParameter('image', substr($image, 0, 100), 'gemini', 'Image must be a base64 data URI or an http(s) URL.');
    }

    /**
     * Build an image content item from a local file.
     *
     * @param   string  $path  The file path
     *
     * @return  array
     * @throws  InvalidArgumentException
     * @since   __DEPLOY_VERSION__
     */
    private function imageContentFromFile(string $path): array
    {
        if (!is_file($path)) {
            throw InvalidArgumentException::fileNotFound($path, 'gemini');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!isset(self::IMAGE_MIME_TYPES[$extension])) {
            throw InvalidArgumentException::invalidFileFormat($path, $extension, array_keys(self::IMAGE_MIME_TYPES), 'gemini');
        }

        return ['type' => 'image', 'data' => base64_encode((string) file_get_contents($path)), 'mime_type' => self::IMAGE_MIME_TYPES[$extension]];
    }

    /**
     * POST a JSON payload with the standard headers.
     *
     * @param   string  $endpoint  The endpoint URL
     * @param   array   $payload   The payload
     *
     * @return  HttpResponse
     * @throws  ProviderException  If the payload cannot be encoded
     * @since   __DEPLOY_VERSION__
     */
    private function postJson(string $endpoint, array $payload): HttpResponse
    {
        $json = json_encode($payload);

        if ($json === false) {
            throw new ProviderException($this->getName(), ['message' => 'Failed to encode request data: ' . json_last_error_msg()]);
        }

        return $this->makePostRequest($endpoint, $json, $this->buildHeaders());
    }

    /**
     * Build the HTTP headers.
     *
     * @return  array
     * @throws  AuthenticationException  If no API key is configured
     * @since   __DEPLOY_VERSION__
     */
    private function buildHeaders(): array
    {
        $apiKey = $this->getOption('api_key');

        if (empty($apiKey)) {
            throw new AuthenticationException($this->getName(), ['message' => 'Gemini API key not configured. Provide the api_key option.'], 401);
        }

        return [
            'x-goog-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    private function getInteractionsEndpoint(): string
    {
        return $this->baseUrl . '/interactions';
    }
}
