<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Provider;

use Joomla\AI\AbstractProvider;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Interface\ChatInterface;
use Joomla\AI\Interface\ModelInterface;
use Joomla\AI\Interface\ProviderInterface;
use Joomla\AI\Interface\StreamInterface;
use Joomla\AI\Interface\ToolInterface;
use Joomla\AI\Response\Response;
use Joomla\Http\HttpFactory;

/**
 * Groq provider implementation.
 *
 * Groq runs open-source models (Llama, Qwen, gpt-oss, ...) through an OpenAI-compatible API at
 * https://api.groq.com/openai/v1. Supports chat, streaming, tools, JSON schema output and
 * Whisper speech-to-text.
 *
 * @since  __DEPLOY_VERSION__
 */
class GroqProvider extends AbstractProvider implements ProviderInterface, ChatInterface, StreamInterface, ToolInterface, ModelInterface
{
    use OpenAICompatibleTrait;

    /**
     * Default chat model
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    private const DEFAULT_MODEL = 'openai/gpt-oss-120b';

    /**
     * Default speech-to-text model
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    private const DEFAULT_TRANSCRIPTION_MODEL = 'whisper-large-v3-turbo';

    /**
     * Audio file extensions accepted for speech-to-text
     *
     * @var    string[]
     * @since  __DEPLOY_VERSION__
     */
    private const AUDIO_FORMATS = ['flac', 'mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'ogg', 'wav', 'webm'];

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

        $this->baseUrl = rtrim((string) $this->getOption('base_url', 'https://api.groq.com/openai/v1'), '/');
    }

    /**
     * Check if the Groq provider is supported.
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
        return 'Groq';
    }

    /**
     * Get the default capability map for Groq.
     * Override via the "capabilities" provider option or in a subclass.
     *
     * The lists are only used by isModelCapable() and the get*Models() helpers; requests are
     * not blocked for models missing from them.
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    protected function getDefaultCapabilities(): array
    {
        return [
            'models' => [
                'chat' => [
                    'openai/gpt-oss-120b',
                    'openai/gpt-oss-20b',
                    'llama-3.3-70b-versatile',
                    'llama-3.1-8b-instant',
                    'qwen/qwen3.8-27b',
                    'minimaxai/minimax-m2.7',
                    'meta-models/muse-spark-open',
                ],
                // Models that enforce a JSON schema strictly; others only support it best-effort or not at all
                'json_schema' => ['openai/gpt-oss-120b', 'openai/gpt-oss-20b', 'qwen/qwen3.8-27b', 'meta-models/muse-spark-open'],
                // Groq has no vision model in production; add one here when it does
                'vision' => [],
                'transcription' => ['whisper-large-v3-turbo', 'whisper-large-v3'],
                'translation' => ['whisper-large-v3'],
            ],
        ];
    }

    /**
     * Get the known speech-to-text models.
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    public function getTranscriptionModels(): array
    {
        return $this->getCapability('models', 'transcription', []);
    }

    /**
     * Ask a question about an image.
     *
     * Groq has no vision model in production, so a vision-capable model must be named, either with
     * the "model" option or the "vision_model" provider option.
     *
     * @param   string  $message  The question
     * @param   string  $image    An http(s) image URL or a base64 data URI
     * @param   array   $options  The same options as chat(), plus "detail" (auto, low, high)
     *
     * @return  Response
     * @throws  InvalidArgumentException  If no model is given or the image is not a URL or data URI
     * @since   __DEPLOY_VERSION__
     */
    public function vision(string $message, string $image, array $options = []): Response
    {
        $options['model'] ??= $this->getOption('vision_model');

        if (empty($options['model'])) {
            throw InvalidArgumentException::missingParameter('model (Groq has no default vision model; pass a vision-capable model as "model" or set the "vision_model" option)', 'groq');
        }

        if (!preg_match('#^(https?://|data:image/[a-z0-9.+-]+;base64,)#i', $image)) {
            throw InvalidArgumentException::invalidParameter('image', substr($image, 0, 100), 'groq', 'Image must be an http(s) URL or a base64 data URI.');
        }

        return $this->chat($message, $this->addVisionMessage($message, $image, $options));
    }

    /**
     * Transcribe an audio file with Whisper.
     *
     * @param   string  $audioFile  Path to the audio file (flac, mp3, mp4, mpeg, mpga, m4a, ogg, wav, webm)
     * @param   array   $options    model (default whisper-large-v3-turbo), language, prompt, temperature,
     *                              response_format (json, verbose_json, text), timestamp_granularities
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function transcribe(string $audioFile, array $options = []): Response
    {
        $model = $options['model'] ?? $this->getOption('transcription_model', self::DEFAULT_TRANSCRIPTION_MODEL);

        return $this->sendAudio($this->baseUrl . '/audio/transcriptions', $audioFile, $model, $options, true);
    }

    /**
     * Translate the speech in an audio file into English text with Whisper.
     *
     * @param   string  $audioFile  Path to the audio file
     * @param   array   $options    model (default whisper-large-v3, the model that supports translation),
     *                              prompt, temperature, response_format (json, verbose_json, text)
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function translate(string $audioFile, array $options = []): Response
    {
        return $this->sendAudio($this->baseUrl . '/audio/translations', $audioFile, $options['model'] ?? 'whisper-large-v3', $options, false);
    }

    /**
     * Build the chat completions payload.
     *
     * Options: model, messages, system, max_tokens, temperature, top_p, stop, seed,
     * reasoning_effort, tools, tool_choice, parallel_tool_calls, response_schema.
     *
     * @param   string  $message  The user message
     * @param   array   $options  Request options
     *
     * @return  array
     * @throws  InvalidArgumentException  For options Groq rejects
     * @since   __DEPLOY_VERSION__
     */
    private function buildChatRequestPayload(string $message, array $options): array
    {
        if (isset($options['n']) && (int) $options['n'] !== 1) {
            throw InvalidArgumentException::invalidParameter('n', $options['n'], 'groq', 'Groq only supports n = 1.');
        }

        if (isset($options['response_schema']) && !empty($options['tools'])) {
            throw InvalidArgumentException::invalidParameter('response_schema', 'tools', 'groq', 'Groq does not support JSON schema output together with tools.');
        }

        if (isset($options['response_schema']) && !empty($options['stream'])) {
            throw InvalidArgumentException::invalidParameter('response_schema', 'stream', 'groq', 'Groq does not support JSON schema output with streaming.');
        }

        $payload = [
            'model' => $options['model'] ?? $this->defaultModel ?? $this->getOption('model', self::DEFAULT_MODEL),
            'messages' => $this->buildChatMessages($message, $options),
        ];

        // max_tokens is deprecated on Groq in favour of max_completion_tokens
        if (isset($options['max_tokens'])) {
            $payload['max_completion_tokens'] = (int) $options['max_tokens'];
        }

        foreach (['temperature' => 'float', 'top_p' => 'float', 'seed' => 'int'] as $key => $type) {
            if (isset($options[$key])) {
                $payload[$key] = $type === 'int' ? (int) $options[$key] : (float) $options[$key];
            }
        }

        if (isset($options['stop'])) {
            $payload['stop'] = $options['stop'];
        }

        if (isset($options['reasoning_effort'])) {
            $payload['reasoning_effort'] = (string) $options['reasoning_effort'];
        }

        return $this->addToolAndSchemaParameters($payload, $options);
    }

    /**
     * Send an audio file to a speech-to-text endpoint.
     *
     * @param   string  $endpoint     The endpoint URL
     * @param   string  $audioFile    Path to the audio file
     * @param   string  $model        The model
     * @param   array   $options      Request options
     * @param   bool    $isTranscript Whether language and timestamp options apply
     *
     * @return  Response
     * @throws  InvalidArgumentException  If the file or an option is invalid
     * @since   __DEPLOY_VERSION__
     */
    private function sendAudio(string $endpoint, string $audioFile, string $model, array $options, bool $isTranscript): Response
    {
        if (!is_file($audioFile)) {
            throw InvalidArgumentException::fileNotFound($audioFile, 'groq');
        }

        $extension = strtolower(pathinfo($audioFile, PATHINFO_EXTENSION));

        if (!in_array($extension, self::AUDIO_FORMATS, true)) {
            throw InvalidArgumentException::invalidFileFormat($audioFile, $extension, self::AUDIO_FORMATS, 'groq');
        }

        $responseFormat = $options['response_format'] ?? 'json';

        if (!in_array($responseFormat, ['json', 'verbose_json', 'text'], true)) {
            throw InvalidArgumentException::invalidParameter('response_format', $responseFormat, 'groq', 'Response format must be json, verbose_json or text (Groq does not support srt or vtt).');
        }

        $payload = [
            'model' => $model,
            'file' => null,
            '_filename' => basename($audioFile),
            '_filepath' => $audioFile,
            'response_format' => $responseFormat,
        ];

        foreach ($isTranscript ? ['language', 'prompt', 'temperature'] : ['prompt', 'temperature'] as $key) {
            if (isset($options[$key])) {
                $payload[$key] = (string) $options[$key];
            }
        }

        if ($isTranscript && isset($options['timestamp_granularities'])) {
            if ($responseFormat !== 'verbose_json') {
                throw InvalidArgumentException::invalidParameter('timestamp_granularities', $options['timestamp_granularities'], 'groq', 'timestamp_granularities needs response_format verbose_json.');
            }

            $payload['timestamp_granularities'] = array_map('strval', array_values((array) $options['timestamp_granularities']));
        }

        $headers = $this->buildHeaders();
        unset($headers['Content-Type']);

        $body = (string) $this->makeMultipartPostRequest($endpoint, $payload, $headers)->getBody();

        $metadata = ['model' => $model, 'response_format' => $responseFormat];

        if ($responseFormat === 'text') {
            return $this->recordResponse(new Response(trim($body), $this->getName(), $metadata, 200));
        }

        $data = $this->parseJsonResponse($body);

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        $this->assertResponseFields($data, ['text'], $body);

        foreach (['language', 'duration', 'segments', 'words'] as $key) {
            if (isset($data[$key])) {
                $metadata[$key] = $data[$key];
            }
        }

        $usage = is_array($data['x_groq']['usage'] ?? null) ? $data['x_groq']['usage'] : (is_array($data['usage'] ?? null) ? $data['usage'] : []);
        $metadata['usage'] = $usage;
        $metadata += $this->normaliseOpenAIUsage($usage);

        return $this->recordResponse(new Response((string) $data['text'], $this->getName(), $metadata, 200));
    }
}
