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
use Joomla\AI\Interface\ProviderInterface;
use Joomla\AI\Interface\AudioInterface;
use Joomla\AI\Interface\ChatInterface;
use Joomla\AI\Interface\EmbeddingInterface;
use Joomla\AI\Interface\ImageInterface;
use Joomla\AI\Interface\ModelInterface;
use Joomla\AI\Interface\ModerationInterface;
use Joomla\AI\Response\Response;
use Joomla\Http\HttpFactory;

/**
 * OpenAI provider implementation for chat completions.
 *
 * @since  __DEPLOY_VERSION__
 */
class OpenAIProvider extends AbstractProvider implements ProviderInterface, ChatInterface, ModelInterface, ImageInterface, EmbeddingInterface, AudioInterface, ModerationInterface
{
    /**
     * Custom base URL for API requests
     *
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private $baseUrl;

    /**
     * Models that support chat capability.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const CHAT_MODELS = ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'];

    /**
     * Models that support vision capability.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const VISION_MODELS = ['gpt-4o', 'gpt-4o-mini'];

    /**
     * Models that support image generation capability.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const IMAGE_MODELS = ['dall-e-2', 'dall-e-3', 'gpt-image-1'];

    /**
     * Models that support text-to-speech (TTS) capability.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const TTS_MODELS = ['gpt-4o-mini-tts',  'tts-1', 'tts-1-hd'];

    /**
     * Models that support text embeddings creating capability.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const EMBEDDING_MODELS = ['text-embedding-3-large', 'text-embedding-3-small', 'text-embedding-ada-002'];

    /**
     * Models that support audio transcription.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const TRANSCRIPTION_MODELS = ['gpt-4o-transcribe', 'gpt-4o-mini-transcribe', 'whisper-1'];

    /**
     * Models that support content moderation.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const MODERATION_MODELS = ['omni-moderation-latest', 'text-moderation-007'];

    /**
     * Available voices for text-to-speech.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const VOICES = ['alloy', 'ash', 'ballad', 'coral', 'echo', 'fable', 'nova', 'onyx', 'sage', 'shimmer', 'verse'];

    /**
     * Supported audio formats for OpenAI TTS.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const AUDIO_FORMATS = ['mp3', 'opus', 'aac', 'flac', 'wav', 'pcm'];

    /**
     * Supported input formats for transcription.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const TRANSCRIPTION_INPUT_FORMATS = ['flac','mp3','mp4','mpeg','mpga','m4a','ogg','wav','webm'];

    /**
     * Constructor.
     *
     * @param   array|\ArrayAccess  $options     Provider options array.
     * @param   HttpFactory         $httpFactory The http factory
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct($options = [], ?HttpFactory $httpFactory = null)
    {
        parent::__construct($options, $httpFactory);

        $this->baseUrl = $this->getOption('base_url', 'https://api.openai.com/v1');

        // Remove trailing slash if present
        if (substr($this->baseUrl, -1) === '/') {
            $this->baseUrl = rtrim($this->baseUrl, '/');
        }
    }

    /**
     * Check if OpenAI provider is supported/configured.
     *
     * @return  boolean  True if API key is available
     * @since  __DEPLOY_VERSION__
     */
    public static function isSupported(): bool
    {
        return !empty($_ENV['OPENAI_API_KEY']) ||
               !empty(getenv('OPENAI_API_KEY'));
    }

    /**
     * Get the provider name.
     *
     * @return  string  The provider name
     * @since  __DEPLOY_VERSION__
     */
    public function getName(): string
    {
        return 'OpenAI';
    }

    /**
    * Get the chat completions endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getChatEndpoint(): string
    {
        return $this->baseUrl . '/chat/completions';
    }

    /**
    * Get the image generation endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getImageEndpoint(): string
    {
        return $this->baseUrl . '/images/generations';
    }

    /**
    * Get the image edit endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getImageEditEndpoint(): string
    {
        return $this->baseUrl . '/images/edits';
    }

    /**
    * Get the image variations endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getImageVariationsEndpoint(): string
    {
        return $this->baseUrl . '/images/variations';
    }

    /**
    * Get the audio speech endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getAudioSpeechEndpoint(): string
    {
        return $this->baseUrl . '/audio/speech';
    }

    /**
    * Get the audio transcription endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getAudioTranscriptionEndpoint(): string
    {
        return $this->baseUrl . '/audio/transcriptions';
    }

    /**
    * Get the audio translation endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getAudioTranslationEndpoint(): string
    {
        return $this->baseUrl . '/audio/translations';
    }

    /**
    * Get the embeddings endpoint URL.
    *
    * @return  string  The endpoint URL
    * @since  __DEPLOY_VERSION__
    */
    private function getEmbeddingsEndpoint(): string
    {
        return $this->baseUrl . '/embeddings';
    }

    /**
     * Get the content moderation endpoint URL.
     *
     * @return  string  The endpoint URL
     * @since  __DEPLOY_VERSION__
     */
    private function getModerationEndpoint(): string
    {
        return $this->baseUrl . '/moderations';
    }

    /**
     * Get all available models for this provider.
     *
     * @return  array  Array of available model names
     * @since   __DEPLOY_VERSION__
     */
    public function getAvailableModels(): array
    {
        $headers = $this->buildHeaders();
        $response = $this->makeGetRequest('https://api.openai.com/v1/models', $headers);
        $data = $this->parseJsonResponse($response->getBody());

        return array_column($data['data'], 'id');
    }

    /**
     * Get models that support chat capability.
     *
     * @return  array  Array of chat-capable model names
     * @since   __DEPLOY_VERSION__
     */
    public function getChatModels(): array
    {
        $available = $this->getAvailableModels();
        return $this->getModelsByCapability($available, self::CHAT_MODELS);
    }

    /**
     * Get models that support vision capability.
     *
     * @return  array  Array of vision-capable model names
     * @since   __DEPLOY_VERSION__
     */
    public function getVisionModels(): array
    {
        $available = $this->getAvailableModels();
        return $this->getModelsByCapability($available, self::VISION_MODELS);
    }

    /**
     * Get models that support image generation capability.
     *
     * @return  array  Array of image capable model names
     * @since   __DEPLOY_VERSION__
     */
    public function getImageModels(): array
    {
        $available = $this->getAvailableModels();
        return $this->getModelsByCapability($available, self::IMAGE_MODELS);
    }

    /**
     * Get available TTS models for this provider.
     *
     * @return  array  Array of available TTS model names
     * @since   __DEPLOY_VERSION__
     */
    public function getTTSModels(): array
    {
        $available = $this->getAvailableModels();
        return $this->getModelsByCapability($available, self::TTS_MODELS);
    }

    /**
     * Get available transcription models for this provider.
     *
     * @return  array  Array of available transcription model names
     * @since   __DEPLOY_VERSION__
     */
    public function getTranscriptionModels(): array
    {
        return self::TRANSCRIPTION_MODELS;
    }

    /**
     * Get available embedding models for this provider.
     *
     * @return  array  Array of available embedding model names
     * @since   __DEPLOY_VERSION__
     */
    public function getEmbeddingModels(): array
    {
        return self::EMBEDDING_MODELS;
    }

    /**
     * Get available voices for speech generation.
     *
     * @return  array  Array of available voice names
     * @since   __DEPLOY_VERSION__
     */
    public function getAvailableVoices(): array
    {
        return self::VOICES;
    }

    /**
     * Get supported audio output formats.
     *
     * @return  array  Array of supported format names
     * @since   __DEPLOY_VERSION__
     */
    public function getSupportedAudioFormats(): array
    {
        return self::AUDIO_FORMATS;
    }

    /**
     * Get supported audio input formats for transcription.
     *
     * @return  array  Array of supported input format names
     * @since   __DEPLOY_VERSION__
     */
    public function getSupportedTranscriptionFormats(): array
    {
        return self::TRANSCRIPTION_INPUT_FORMATS;
    }

    /**
     * Check if a specific model is supported by this provider.
     *
     * @param   string  $model  The model name to check
     *
     * @return  bool    True if model is available, false otherwise
     * @since   __DEPLOY_VERSION__
     */
    public function isModelSupported(string $model): bool
    {
        $available = $this->getAvailableModels();
        return $this->isModelAvailable($model, $available);
    }

    /**
     * Check if a model supports a specific capability.
     *
     * @param   string  $model       The model name to check
     * @param   string  $capability  The capability to check (chat, image, vision)
     *
     * @return  bool    True if model supports the capability, false otherwise
     * @since   __DEPLOY_VERSION__
     */
    public function isModelCapable(string $model, string $capability): bool
    {
        $capabilityMap = [
            'chat' => self::CHAT_MODELS,
            'vision' => self::VISION_MODELS,
            'image' => self::IMAGE_MODELS,
            'text-to-speech' => self::TTS_MODELS,
            'transcription' => self::TRANSCRIPTION_MODELS,
            'embedding' => self::EMBEDDING_MODELS,
        ];
        return $this->checkModelCapability($model, $capability, $capabilityMap);
    }

    /**
     * Send a message to OpenAI and return response.
     *
     * @param   string  $message   The message to send
     * @param   array   $options  Additional options for the request
     *
     * @return  Response  The AI response object
     * @since  __DEPLOY_VERSION__
     */
    public function chat(string $message, array $options = []): Response
    {
        // Apply moderation to the chat message
        $isBlocked = $this->moderateInput($message, []);

        if ($isBlocked) {
            throw new \Exception('Content flagged by moderation system and blocked.');
        }

        $payload = $this->buildChatRequestPayload($message, $options);

        // To Do: Remove repetition
        $endpoint = $this->getChatEndpoint();
        $headers = $this->buildHeaders();

        $httpResponse = $this->makePostRequest(
            $endpoint,
            json_encode($payload),
            $headers
        );

        return $this->parseOpenAIResponse($httpResponse->getBody());
    }

    /**
     * Generate chat completion with vision capability and return Response.
     *
     * @param   string  $message  The chat message about the image.
     * @param   string  $image    Image URL or base64 encoded image.
     * @param   array   $options  Additional options for the request.
     *
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    public function vision(string $message, string $image, array $options = []): Response
    {
        // Apply moderation to the input (text + image)
        $multiModalInput = [
            ['type' => 'text', 'text' => $message],
            ['type' => 'image_url', 'image_url' => ['url' => $image]]
        ];
        $isBlocked = $this->moderateInput($multiModalInput, []);

        if ($isBlocked) {
            throw new \Exception('Content flagged by moderation system and blocked.');
        }

        $payload = $this->buildVisionRequestPayload($message, $image, 'vision', $options);

        $endpoint = $this->getChatEndpoint();
        $headers = $this->buildHeaders();

        $httpResponse = $this->makePostRequest(
            $endpoint,
            json_encode($payload),
            $headers
        );

        return $this->parseOpenAIResponse($httpResponse->getBody());
    }

    /**
     * Generate a new image from the given prompt.
     *
     * @param   string  $prompt   Descriptive text prompt for the desired image.
     * @param   array   $options  Additional options for the request.
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function generateImage(string $prompt, array $options = []): Response
    {
        // Apply moderation to the image generation prompt
        $isBlocked = $this->moderateInput($prompt, []);

        if ($isBlocked) {
            throw new \Exception('Content flagged by moderation system and blocked.');
        }

        $payload = $this->buildImageRequestPayload($prompt, $options);

        $headers = $this->buildHeaders();

        $httpResponse = $this->makePostRequest(
            $this->getImageEndpoint(),
            json_encode($payload),
            $headers
        );

        return $this->parseImageResponse($httpResponse->getBody(), $payload);
    }

    /**
     * Create variations of an image using OpenAI Image API.
     *
     * @param   string  $imagePath  Path to the image file to create variations of.
     * @param   array   $options    Additional options for the request.
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function createImageVariation(string $imagePath, array $options = []): Response
    {
        $payload = $this->buildImageVariationPayload($imagePath, $options);

        $headers = $this->buildMultipartHeaders();

        $httpResponse = $this->makeMultipartPostRequest(
            $this->getImageVariationsEndpoint(),
            $payload,
            $headers
        );

        return $this->parseImageResponse($httpResponse->getBody(), $payload);
    }

    /**
     * Edit an image using OpenAI Image API.
     *
     * @param   mixed   $images   Single image path or array of image paths
     * @param   string  $prompt   Description of desired edits
     * @param   array   $options  Additional options for the request
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function editImage($images, string $prompt, array $options = []): Response
    {
        // Apply moderation to the image editing prompt
        $isBlocked = $this->moderateInput($prompt, []);

        if ($isBlocked) {
            throw new \Exception('Content flagged by moderation system and blocked.');
        }

        $payload = $this->buildImageEditPayload($images, $prompt, $options);

        $headers = $this->buildMultipartHeaders();

        $httpResponse = $this->makeMultipartPostRequest(
            $this->getImageEditEndpoint(),
            $payload,
            $headers
        );

        return $this->parseImageResponse($httpResponse->getBody(), $payload);
    }

    /**
     * Generate speech audio from text input.
     *
     * @param   string  $text     The text to convert to speech
     * @param   array   $options  Additional options for speech generation
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function speech(string $text, array $options = []): Response
    {
        // Apply moderation to the text input for speech generation
        $isBlocked = $this->moderateInput($text, []);

        if ($isBlocked) {
            throw new \Exception('Content flagged by moderation system and blocked.');
        }

        $payload = $this->buildSpeechPayload($text, $options);

        $endpoint = $this->getAudioSpeechEndpoint();
        $headers = $this->buildHeaders();
        $httpResponse = $this->makePostRequest($endpoint, json_encode($payload), $headers);

        return $this->parseAudioResponse($httpResponse->getBody(), $payload);
    }

    /**
     * Transcribe audio into text.
     *
     * @param   string  $audioFile  Path to audio file
     * @param   array   $options    Additional options for transcription
     *
     * @return  Response  The AI response containing transcribed text
     * @since   __DEPLOY_VERSION__
     */
    public function transcribe(string $audioFile, array $options = []): Response
    {
        $payload = $this->buildTranscriptionPayload($audioFile, $options);

        $headers = $this->buildMultipartHeaders();

        $httpResponse = $this->makeMultipartPostRequest(
            $this->getAudioTranscriptionEndpoint(),
            $payload,
            $headers
        );

        return $this->parseAudioTextResponse($httpResponse->getBody(), $payload, 'Transcription');
    }

    /**
     * Translate audio to English text.
     *
     * @param   string  $audioFile  Path to audio file
     * @param   array   $options    Additional options
     *
     * @return  Response  Translation response
     * @since   __DEPLOY_VERSION__
     */
    public function translate(string $audioFile, array $options = []): Response
    {
        $payload = $this->buildTranslationPayload($audioFile, $options);

        $headers = $this->buildMultipartHeaders();

        $httpResponse = $this->makeMultipartPostRequest(
            $this->getAudioTranslationEndpoint(),
            $payload,
            $headers
        );

        return $this->parseAudioTextResponse($httpResponse->getBody(), $payload, 'Translation');
    }

    /**
     * Create embeddings for the given input text(s).
     *
     * @param   string|array  $input    Text string or array of texts to embed
     * @param   string        $model    The embedding model to use
     * @param   array         $options  Additional options
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function createEmbeddings($input, string $model, array $options = []): Response
    {
        // Apply moderation to the text input for embeddings
        $isBlocked = $this->moderateInput($input, []);

        if ($isBlocked) {
            throw new \Exception('Content flagged by moderation system and blocked.');
        }

        $payload = $this->buildEmbeddingPayload($input, $model, $options);

        $headers = $this->buildHeaders();

        $httpResponse = $this->makePostRequest(
            $this->getEmbeddingsEndpoint(),
            json_encode($payload),
            $headers
        );

        return $this->parseEmbeddingResponse($httpResponse->getBody(), $payload);
    }

    /**
     * Moderate content using OpenAI's moderation endpoint.
     *
     * @param   string|array  $input    Text/Image input(s) to moderate
     * @param   array         $options  Additional options for moderation
     *
     * @return  array
     * @throws  \Exception
     * @since   __DEPLOY_VERSION__
     */
    public function moderate($input, array $options = []): array
    {
        $model = $options['model'] ?? 'omni-moderation-latest';

        if (!in_array($model, self::MODERATION_MODELS)) {
            throw InvalidArgumentException::invalidModel($model, 'openai', self::MODERATION_MODELS, 'moderation');
        }

        $payload = [
            'input' => $input,
            'model' => $model
        ];

        $headers = $this->buildHeaders();

        $httpResponse = $this->makePostRequest(
            $this->getModerationEndpoint(),
            json_encode($payload),
            $headers
        );

        $data = $this->parseJsonResponse($httpResponse->getBody());

        return $data;
    }

    /**
     * Check if content is flagged by OpenAI moderation.
     *
     * @param   array  $moderationResult  Result from moderate() method
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    public function isContentFlagged(array $moderationResult): bool
    {
        if (!isset($moderationResult['results']) || empty($moderationResult['results'])) {
            throw InvalidArgumentException::invalidParameter('moderation[results]', $moderationResult, 'openai', 'Moderation result must contain valid results array.');
        }

        return $moderationResult['results'][0]['flagged'] ?? false;
    }

    /**
     * Build payload for chat request.
     *
     * @param   string  $message   The user message to send
     * @param   array   $options  Additional options
     *
     * @return  array   The request payload
     * @throws  \InvalidArgumentException  If model does not support chat capability
     * @since  __DEPLOY_VERSION__
     */
    private function buildChatRequestPayload(string $message, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'gpt-4o-mini');

        if (isset($options['messages'])) {
            $messages = $options['messages'];
            if (!is_array($messages) || empty($messages)) {
                throw InvalidArgumentException::invalidMessages('openai', 'Messages must be a non-empty array.');
            }
            $this->validateMessages($messages);
        } else {
            $messages = [
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => $messages
        ];

        // Handle modalities parameter
        if (isset($options['modalities'])) {
            if (!is_array($options['modalities'])) {
                throw InvalidArgumentException::invalidParameter('modalities', $options['modalities'], 'openai', 'Modalities must be an array.');
            }

            $validModalities = ['text', 'audio'];
            foreach ($options['modalities'] as $modality) {
                if (!in_array($modality, $validModalities)) {
                    throw InvalidArgumentException::invalidParameter('modality', $modality, 'openai', 'Valid modalities: ' . implode(', ', $validModalities), ['valid_modalities' => $validModalities]);
                }
            }

            // Audio modality requires gpt-4o-audio-preview model
            if (in_array('audio', $options['modalities']) && $model !== 'gpt-4o-audio-preview') {
                throw InvalidArgumentException::invalidModel($model, 'openai', ['gpt-4o-audio-preview'], 'audio');
            }

            $payload['modalities'] = $options['modalities'];
        }

        // Handle audio output parameters
        if (isset($options['audio'])) {
            // Audio output requires audio modality
            if (!is_array($options['audio']) || !isset($payload['modalities']) || !in_array('audio', $payload['modalities'])) {
                throw InvalidArgumentException::invalidParameter('audio', $options['audio'], 'openai', 'Audio output parameter must be an array and requires modalities to include "audio".', ['required_modalities' => ['audio']]);
            }

            $audioParams = [];

            // Validate and set audio format
            if (!isset($options['audio']['format'])) {
                throw InvalidArgumentException::missingParameter('audio.format', 'openai', 'chat');
            }

            $validAudioFormats = ['wav', 'mp3', 'flac', 'opus', 'pcm16'];
            if (!in_array($options['audio']['format'], $validAudioFormats)) {
                throw InvalidArgumentException::invalidParameter('audio.format', $options['audio']['format'], 'openai', 'Audio format must be one of: ' . implode(', ', $validAudioFormats), ['valid_formats' => $validAudioFormats]);
            }
            $audioParams['format'] = $options['audio']['format'];

            // Validate and set voice
            if (!isset($options['audio']['voice'])) {
                throw InvalidArgumentException::missingParameter('audio.voice', 'openai', 'chat');
            }

            $validVoices = ['alloy', 'ash', 'ballad', 'coral', 'echo', 'fable', 'nova', 'onyx', 'sage', 'shimmer'];
            if (!in_array($options['audio']['voice'], $validVoices)) {
                throw InvalidArgumentException::invalidVoice($options['audio']['voice'], $validVoices, 'openai');
            }
            $audioParams['voice'] = $options['audio']['voice'];

            $payload['audio'] = $audioParams;
        }

        if (isset($options['n'])) {
            $n = (int) $options['n'];
            if ($n < 1 || $n > 128) {
                throw InvalidArgumentException::invalidParameter('n', $options['n'], 'openai', 'Parameter "n" must be between 1 and 128.', ['min_value' => 1, 'max_value' => 128]);
            }
            $payload['n'] = $n;
        }

        if (isset($options['stream'])) {
            $payload['stream'] = (bool) $options['stream'];
        }

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        return $payload;
    }

    /**
     * Build payload for vision request.
     *
     * @param   string  $message  The chat message about the image
     * @param   string  $image    Image URL or base64 encoded image
     * @param   array   $options  Additional options
     *
     * @return  array   The request payload
     * @throws  \InvalidArgumentException  If model does not support vision capability
     * @since  __DEPLOY_VERSION__
     */
    private function buildVisionRequestPayload(string $message, string $image, string $capability, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'gpt-4o-mini');

        if (!$this->isModelCapable($model, $capability)) {
            throw InvalidArgumentException::invalidModel($model, 'openai', self::VISION_MODELS, $capability);
        }

        $content = [
            [
                'type' => 'text',
                'text' => $message
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $image
                ]
            ]
        ];

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ]
        ];

        // To Do: Add optional parameters if provided
        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['n'])) {
            $payload['n'] = (int) $options['n'];
        }

        return $payload;
    }

    /**
     * Build payload for image generation request.
     *
     * @param   string  $prompt      The image generation prompt.
     * @param   array   $options     Additional options for the request.
     *
     * @return  array  The request payload.
     * @since   __DEPLOY_VERSION__
     */
    private function buildImageRequestPayload(string $prompt, array $options): array
    {
        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'dall-e-2');

        if (!in_array($model, ['dall-e-2', 'gpt-image-1', 'dall-e-3'])) {
            throw InvalidArgumentException::invalidModel($model, 'openai', self::IMAGE_MODELS, 'image generation');
        }

        $this->validateImagePrompt($prompt, $model);

        $payload = [
            'model' => $model,
            'prompt' => $prompt
        ];

        // Add optional parameters based on model support
        if (isset($options['n'])) {
            $n = (int) $options['n'];
            if ($model === 'dall-e-3' && $n !== 1) {
                throw InvalidArgumentException::invalidParameter('n', $options['n'], 'openai', 'For dall-e-3, only n=1 is supported.', ['model' => 'dall-e-3', 'allowed_value' => 1]);
            }
            if ($n < 1 || $n > 10) {
                throw InvalidArgumentException::invalidParameter('n', $options['n'], 'openai', 'Parameter "n" must be between 1 and 10.', ['min_value' => 1, 'max_value' => 10]);
            }
            $payload['n'] = $n;
        }

        if (isset($options['size'])) {
            $this->validateImageSize($options['size'], $model, 'generation');
            $payload['size'] = $options['size'];
        }

        if (isset($options['quality'])) {
            $this->validateImageQuality($options['quality'], $model);
            $payload['quality'] = $options['quality'];
        }

        if (isset($options['style'])) {
            if ($model !== 'dall-e-3') {
                throw InvalidArgumentException::invalidParameter('style', $options['style'], 'openai', 'Style parameter is only supported for dall-e-3.', ['model' => $model, 'supported_models' => ['dall-e-3']]);
            }
            if (!in_array($options['style'], ['vivid', 'natural'])) {
                throw InvalidArgumentException::invalidParameter('style', $options['style'], 'openai', 'Style must be either "vivid" or "natural".', ['valid_values' => ['vivid', 'natural']]);
            }
            $payload['style'] = $options['style'];
        }

        if (isset($options['response_format'])) {
            if ($model === 'gpt-image-1') {
                throw InvalidArgumentException::invalidParameter('response_format', $options['response_format'], 'openai', 'response_format is not supported for gpt-image-1 (always returns base64).', ['model' => 'gpt-image-1', 'fixed_format' => 'base64']);
            } elseif (!in_array($options['response_format'], ['url', 'b64_json'])) {
                throw InvalidArgumentException::invalidParameter('response_format', $options['response_format'], 'openai', 'Response format must be either "url" or "b64_json".', ['valid_values' => ['url', 'b64_json']]);
            } else {
                $payload['response_format'] = $options['response_format'];
            }
        }

        if (!isset($options['response_format']) && $model !== 'gpt-image-1') {
            $payload['response_format'] = 'b64_json';
        }

        // gpt-image-1 specific parameters
        if ($model === 'gpt-image-1') {
            if (isset($options['background']) && !in_array($options['background'], ['transparent', 'opaque', 'auto'])) {
                throw InvalidArgumentException::invalidParameter('background', $options['background'], 'openai', 'Background must be one of: transparent, opaque, auto.', ['valid_values' => ['transparent', 'opaque', 'auto']]);
            }
            if (isset($options['background'])) {
                $payload['background'] = $options['background'];
            }

            if (isset($options['output_format'])) {
                if (!in_array($options['output_format'], ['png', 'jpeg', 'webp'])) {
                    throw InvalidArgumentException::invalidParameter('output_format', $options['output_format'], 'openai', 'Output format must be one of: png, jpeg, webp.', ['valid_values' => ['png', 'jpeg', 'webp']]);
                }
                $payload['output_format'] = $options['output_format'];
            }

            if (isset($options['output_compression'])) {
                $compression = (int) $options['output_compression'];
                if ($compression < 0 || $compression > 100) {
                    throw InvalidArgumentException::invalidParameter('output_compression', $options['output_compression'], 'openai', 'Output compression must be between 0 and 100.', ['valid_range' => [0, 100]]);
                }
                $payload['output_compression'] = $compression;
            }

            if (isset($options['moderation']) && !in_array($options['moderation'], ['low', 'auto'])) {
                throw InvalidArgumentException::invalidParameter('moderation', $options['moderation'], 'openai', 'Moderation must be either "low" or "auto".', ['valid_values' => ['low', 'auto']]);
            }
            if (isset($options['moderation'])) {
                $payload['moderation'] = $options['moderation'];
            }
        }

        if (isset($options['user'])) {
            $payload['user'] = $options['user'];
        }

        return $payload;
    }

    /**
     * Build payload for image variation request.
     *
     * @param   string  $imagePath  Path to the image file.
     * @param   array   $options    Additional options for the request.
     *
     * @return  array  The form data for multipart request.
     * @since   __DEPLOY_VERSION__
     */
    private function buildImageVariationPayload(string $imagePath, array $options): array
    {
        $model = $options['model'] ?? $this->defaultModel ?? 'dall-e-2';

        // Only dall-e-2 supports variations
        if ($model !== 'dall-e-2') {
            throw InvalidArgumentException::invalidModel($model, 'openai', ['dall-e-2'], 'image variation');
        }

        $this->validateImageFile($imagePath, $model, 'variation');

        $payload = [
            'model' => $model,
            'image' => file_get_contents($imagePath)
        ];

        if (isset($options['n'])) {
            $n = (int) $options['n'];
            if ($n < 1 || $n > 10) {
                throw InvalidArgumentException::invalidParameter('n', $options['n'], 'openai', 'Parameter "n" must be between 1 and 10.', ['valid_range' => [1, 10]]);
            }
            $payload['n'] = $n;
        }

        if (isset($options['size'])) {
            $validSizes = ['256x256', '512x512', '1024x1024'];
            if (!in_array($options['size'], $validSizes)) {
                throw InvalidArgumentException::invalidParameter('size', $options['size'], 'openai', 'Size must be one of: ' . implode(', ', $validSizes), ['valid_values' => $validSizes]);
            }
            $payload['size'] = $options['size'];
        }

        if (isset($options['response_format'])) {
            $validFormats = ['url', 'b64_json'];
            if (!in_array($options['response_format'], $validFormats)) {
                throw InvalidArgumentException::invalidParameter('response_format', $options['response_format'], 'openai', 'Response format must be either "url" or "b64_json".', ['valid_values' => $validFormats]);
            }
            $payload['response_format'] = $options['response_format'];
        }

        if (!isset($options['response_format'])) {
            $payload['response_format'] = 'b64_json';
        }

        if (isset($options['user'])) {
            $payload['user'] = $options['user'];
        }

        return $payload;
    }

    /**
     * Build payload for image editing request.
     *
     * @param   mixed   $images   Single image path or array of image paths
     * @param   string  $prompt   Description of desired edits
     * @param   array   $options  Additional options
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    private function buildImageEditPayload($images, string $prompt, array $options): array
    {
        $model = $options['model'] ?? $this->defaultModel ??  $this->getOption('model', 'dall-e-2');

        // Only dall-e-2 and gpt-image-1 support image editing
        if (!in_array($model, ['dall-e-2', 'gpt-image-1'])) {
            throw InvalidArgumentException::invalidModel($model, 'openai', ['dall-e-2', 'gpt-image-1'], 'image editing');
        }

        $this->validateImageEditInputs($images, $prompt, $model, $options);

        $payload = [
            'model' => $model,
            'prompt' => $prompt
        ];

        // Handle images
        if (is_string($images)) {
            // Single image
            $payload['image'] = file_get_contents($images);
        } else {
            // Multiple images for gpt-image-1 model
            if ($model !== 'gpt-image-1') {
                throw InvalidArgumentException::invalidModel($model, 'openai', ['gpt-image-1'], 'image editing');
            }

            $imageArray = [];
            foreach ($images as $imagePath) {
                if (!file_exists($imagePath)) {
                    throw InvalidArgumentException::fileNotFound($imagePath, 'openai');
                }
                $imageArray[] = file_get_contents($imagePath);
            }
            $payload['image'] = $imageArray;
        }

        // Add mask if provided
        if (isset($options['mask'])) {
            $this->validateMaskFile($options['mask']);
            $payload['mask'] = file_get_contents($options['mask']);
        }

        if (isset($options['n'])) {
            $n = (int) $options['n'];
            if ($n < 1 || $n > 10) {
                throw InvalidArgumentException::invalidParameter('n', $options['n'], 'openai', 'Parameter "n" must be between 1 and 10.', ['valid_range' => [1, 10]]);
            }
            $payload['n'] = $n;
        }

        if (isset($options['size'])) {
            $this->validateImageSize($options['size'], $model, 'edit');
            $payload['size'] = $options['size'];
        }

        if (isset($options['quality'])) {
            $this->validateImageQuality($options['quality'], $model);
            $payload['quality'] = $options['quality'];
        }

        if (isset($options['response_format'])) {
            if ($model === 'gpt-image-1') {
                throw InvalidArgumentException::invalidParameter('response_format', $options['response_format'], 'openai', 'response_format is not supported for gpt-image-1 (always returns base64).', ['model' => 'gpt-image-1', 'fixed_format' => 'base64']);
            } elseif (!in_array($options['response_format'], ['url', 'b64_json'])) {
                throw InvalidArgumentException::invalidParameter('response_format', $options['response_format'], 'openai', 'Response format must be either "url" or "b64_json".', ['valid_values' => ['url', 'b64_json']]);
            } else {
                $payload['response_format'] = $options['response_format'];
            }
        }

        if (!isset($options['response_format']) && $model !== 'gpt-image-1') {
            $payload['response_format'] = 'b64_json';
        }

        // gpt-image-1 specific parameters
        if ($model === 'gpt-image-1') {
            if (isset($options['background']) && !in_array($options['background'], ['transparent', 'opaque', 'auto'])) {
                throw InvalidArgumentException::invalidParameter('background', $options['background'], 'openai', 'Background must be one of: transparent, opaque, auto.', ['valid_values' => ['transparent', 'opaque', 'auto']]);
            }
            if (isset($options['background'])) {
                $payload['background'] = $options['background'];
            }

            if (isset($options['output_format']) && !in_array($options['output_format'], ['png', 'jpeg', 'webp'])) {
                throw InvalidArgumentException::invalidParameter('output_format', $options['output_format'], 'openai', 'Output format must be one of: png, jpeg, webp.', ['valid_values' => ['png', 'jpeg', 'webp']]);
            }
            if (isset($options['output_format'])) {
                $payload['output_format'] = $options['output_format'];
            }

            if (isset($options['output_compression'])) {
                $compression = (int) $options['output_compression'];
                if ($compression < 0 || $compression > 100) {
                    throw InvalidArgumentException::invalidParameter('output_compression', $compression, 'openai', 'Output compression must be between 0 and 100.', ['valid_range' => [0, 100]]);
                }
                $payload['output_compression'] = $compression;
            }
        }

        if (isset($options['user'])) {
            $payload['user'] = $options['user'];
        }

        return $payload;
    }

    /**
     * Build payload for text-to-speech request.
     *
     * @param   string  $text     The text to convert to speech
     * @param   array   $options  Additional options for speech generation
     *
     * @return  array  The request payload.
     * @since   __DEPLOY_VERSION__
     */
    private function buildSpeechPayload(string $text, array $options): array
    {
        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'gpt-4o-mini-tts');
        $voice = $options['voice'] ?? $this->getOption('voice', 'alloy');

        // Validate model
        if (!in_array($model, self::TTS_MODELS)) {
            throw InvalidArgumentException::invalidModel($model, 'openai', self::TTS_MODELS, 'text-to-speech');
        }

        // Validate voice
        if (!in_array($voice, self::VOICES)) {
            throw InvalidArgumentException::invalidVoice($voice, self::VOICES, 'openai');
        }

        // Validate input text
        if (strlen($text) > 4096) {
            throw InvalidArgumentException::invalidParameter('text', $text, 'openai', 'Speech input text cannot exceed 4096 characters, got: ' . strlen($text) . ' characters.', ['max_length' => 4096, 'actual_length' => strlen($text)]);
        }

        $payload = [
            'input' => $text,
            'model' => $model,
            'voice' => $voice
        ];

        $responseFormat = $options['response_format'] ?? 'mp3';
        if (!in_array($responseFormat, self::AUDIO_FORMATS)) {
            throw InvalidArgumentException::invalidParameter('response_format', $responseFormat, 'openai', 'Audio response format must be one of: ' . implode(', ', self::AUDIO_FORMATS), ['valid_formats' => self::AUDIO_FORMATS]);
        }
        $payload['response_format'] = $responseFormat;

        if (isset($options['speed'])) {
            $speed = (float) $options['speed'];
            if ($speed < 0.25 || $speed > 4.0) {
                throw InvalidArgumentException::invalidParameter('speed', $speed, 'openai', 'Speed must be between 0.25 and 4.0, got: ' . $speed, ['valid_range' => [0.25, 4.0]]);
            }
            $payload['speed'] = $speed;
        }

        if (isset($options['instructions'])) {
            if ($model !== 'gpt-4o-mini-tts') {
                throw InvalidArgumentException::invalidModel($model, 'openai', ['gpt-4o-mini-tts'], 'instructions parameter for text-to-speech');
            }
            if (!is_string($options['instructions']) || empty(trim($options['instructions']))) {
                throw InvalidArgumentException::invalidParameter('instructions', $options['instructions'], 'openai', 'Instructions must be a non-empty string.', ['expected_type' => 'string', 'actual_type' => gettype($options['instructions'])]);
            }
            $payload['instructions'] = $options['instructions'];
        }

        if (isset($options['stream_format'])) {
            if ($model !== 'gpt-4o-mini-tts') {
                throw InvalidArgumentException::invalidModel($model, 'openai', ['gpt-4o-mini-tts'], 'stream format parameter for text-to-speech');
            }
            if (!in_array($options['stream_format'], ['sse', 'audio'])) {
                throw InvalidArgumentException::invalidParameter('stream_format', $options['stream_format'], 'openai', "Stream format must be 'sse' or 'audio', got: " . $options['stream_format']);
            }
            $payload['stream_format'] = $options['stream_format'];
        }

        return $payload;
    }

    /**
     * Build payload for transcription request.
     *
     * @param   string  $audioFile  The audio file
     * @param   array   $options    Additional options
     *
     * @return  array   Form data for multipart request
     * @throws  \InvalidArgumentException  If parameters are invalid
     * @since   __DEPLOY_VERSION__
     */
    private function buildTranscriptionPayload(string $audioFile, array $options): array
    {
        // Validate audio file
        $this->validateAudioFile($audioFile);

        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'gpt-4o-transcribe');

        // Validate model
        if (!in_array($model, self::TRANSCRIPTION_MODELS)) {
            throw InvalidArgumentException::invalidModel($model, 'openai', self::TRANSCRIPTION_MODELS, 'transcription');
        }

        $payload = [
            'model' => $model,
            'file' => null,
            '_filename' => basename($audioFile),
            '_filepath' => $audioFile,
        ];

        $responseFormat = $options['response_format'] ?? 'json';
        $validFormats = ['json', 'text', 'srt', 'verbose_json', 'vtt'];

        if (in_array($model, ['gpt-4o-transcribe', 'gpt-4o-mini-transcribe'])) {
            if ($responseFormat !== 'json') {
                throw InvalidArgumentException::invalidParameter('response_format', $responseFormat, 'openai', "For $model, only 'json' response format is supported.");
            }
        } elseif (!in_array($responseFormat, $validFormats)) {
            throw InvalidArgumentException::invalidParameter('response_format', $responseFormat, 'openai', "Invalid response format: $responseFormat. Valid formats: " . implode(', ', $validFormats));
        }
        $payload['response_format'] = $responseFormat;

        if (isset($options['language'])) {
            $payload['language'] = $options['language'];
        }

        if (isset($options['prompt'])) {
            $payload['prompt'] = $options['prompt'];
        }

        if (isset($options['temperature'])) {
            $temperature = (float) $options['temperature'];
            if ($temperature < 0 || $temperature > 1) {
                throw InvalidArgumentException::invalidTemperature($temperature, 'openai');
            }
            $payload['temperature'] = $temperature;
        }

        if (isset($options['chunking_strategy'])) {
            $payload['chunking_strategy'] = $options['chunking_strategy'];
        }

        if (isset($options['include'])) {
            if (!is_array($options['include'])) {
                throw InvalidArgumentException::invalidParameter('include', $options['include'], 'openai', "Include parameter must be an array.");
            }
            $validIncludes = ['logprobs'];
            foreach ($options['include'] as $include) {
                if (!in_array($include, $validIncludes)) {
                    throw InvalidArgumentException::invalidParameter('include', $include, 'openai', "Invalid include option: $include. Valid options: " . implode(', ', $validIncludes));
                }
            }
            // logprobs only works with json format and specific models
            if (in_array('logprobs', $options['include'])) {
                if ($responseFormat !== 'json') {
                    throw InvalidArgumentException::invalidParameter('response_format', $responseFormat, 'openai', "logprobs include option only works with 'json' response format.");
                }
                if (!in_array($model, ['gpt-4o-transcribe', 'gpt-4o-mini-transcribe'])) {
                    throw InvalidArgumentException::invalidParameter('include', 'logprobs', 'openai', 'logprobs include option only works with gpt-4o-transcribe and gpt-4o-mini-transcribe models.');
                }
            }
            $payload['include'] = $options['include'];
        }

        if (isset($options['stream'])) {
            if ($model === 'whisper-1') {
                throw InvalidArgumentException::invalidModel($model, 'openai', ['whisper-1'], 'streaming for text-to-speech');
            }
            $payload['stream'] = (bool) $options['stream'];
        }

        if (isset($options['timestamp_granularities'])) {
            if ($responseFormat !== 'verbose_json') {
                throw InvalidArgumentException::invalidParameter('response_format', $responseFormat, 'openai', "timestamp_granularities only works with 'verbose_json' response format.");
            }
            if (!is_array($options['timestamp_granularities'])) {
                throw InvalidArgumentException::invalidParameter('timestamp_granularities', $options['timestamp_granularities'], 'openai', "timestamp_granularities must be an array.");
            }
            $validGranularities = ['word', 'segment'];
            foreach ($options['timestamp_granularities'] as $granularity) {
                if (!in_array($granularity, $validGranularities)) {
                    throw InvalidArgumentException::invalidParameter('timestamp_granularities', $granularity, 'openai', "Valid options: " . implode(', ', $validGranularities));
                }
            }
            $payload['timestamp_granularities'] = $options['timestamp_granularities'];
        }

        return $payload;
    }

    /**
     * Build payload for translation request.
     *
     * @param   string  $audioFile  Path to the audio file
     * @param   array   $options    Additional options for translation
     *
     * @return  array   Form data for multipart request
     * @throws  \InvalidArgumentException  If parameters are invalid
     * @since   __DEPLOY_VERSION__
     */
    private function buildTranslationPayload(string $audioFile, array $options): array
    {
        // Validate audio file
        $this->validateAudioFile($audioFile);

        $model = $options['model'] ?? $this->defaultModel ?? $this->getOption('model', 'whisper-1');

        // Validate model
        if ($model !== 'whisper-1') {
            throw InvalidArgumentException::invalidModel($model, 'openai', ['whisper-1'], 'translation');
        }

        $payload = [
            'model' => $model,
            'file' => null,
            '_filename' => basename($audioFile),
            '_filepath' => $audioFile,
        ];

        $responseFormat = $options['response_format'] ?? 'json';
        $validFormats = ['json', 'text', 'srt', 'verbose_json', 'vtt'];
        if (!in_array($responseFormat, $validFormats)) {
            throw InvalidArgumentException::invalidParameter('response_format', $responseFormat, 'openai', "Valid formats: " . implode(', ', $validFormats));
        }
        $payload['response_format'] = $responseFormat;

        if (isset($options['prompt'])) {
            $payload['prompt'] = $options['prompt'];
        }

        if (isset($options['temperature'])) {
            $temperature = (float) $options['temperature'];
            if ($temperature < 0 || $temperature > 1) {
                throw InvalidArgumentException::invalidTemperature($temperature, 'openai');
            }
            $payload['temperature'] = $temperature;
        }

        return $payload;
    }

    /**
     * Build request payload for embeddings.
     *
     * @param   string|array  $input    Text input(s) to embed
     * @param   string        $model    The embedding model to use
     * @param   array         $options  Additional options
     *
     * @return  array
     * @throws  \InvalidArgumentException  If parameters are invalid
     * @since   __DEPLOY_VERSION__
     */
    private function buildEmbeddingPayload($input, string $model, array $options): array
    {
        // Validate model
        if (!in_array($model, self::EMBEDDING_MODELS)) {
            throw InvalidArgumentException::invalidModel($model, 'openai', self::EMBEDDING_MODELS, 'embedding');
        }

        $payload = [
            'input' => $input,
            'model' => $model
        ];

        $encodingFormat = $options['encoding_format'] ?? 'float';
        if (!in_array($encodingFormat, ['float', 'base64'])) {
            throw InvalidArgumentException::invalidParameter('encoding_format', $encodingFormat, 'openai', "Encoding format must be 'float' or 'base64'.");
        }
        $payload['encoding_format'] = $encodingFormat;

        if (isset($options['dimensions'])) {
            if (!in_array($model, ['text-embedding-3-large', 'text-embedding-3-small'])) {
                throw InvalidArgumentException::invalidParameter('dimensions', $options['dimensions'], 'openai', "Dimensions parameter is only supported for text-embedding-3-large and text-embedding-3-small models.");
            }

            $dimensions = (int) $options['dimensions'];
            $maxDimensions = $model === 'text-embedding-3-large' ? 3072 : 1536;

            if ($dimensions < 1 || $dimensions > $maxDimensions) {
                throw InvalidArgumentException::invalidParameter('dimensions', $dimensions, 'openai', "Dimensions must be between 1 and $maxDimensions for $model.");
            }

            $payload['dimensions'] = $dimensions;
        }

        if (isset($options['user'])) {
            $payload['user'] = $options['user'];
        }

        return $payload;
    }

    /**
     * Build HTTP headers for OpenAI API request.
     *
     * @return  array  HTTP headers
     * @since  __DEPLOY_VERSION__
     */
    private function buildHeaders(): array
    {
        $apiKey = $this->getApiKey();

        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'User-Agent' => 'Joomla-AI-Framework'
        ];
    }

    /**
     * Build HTTP headers for multipart form data requests.
     *
     * @return  array  HTTP headers
     * @since   __DEPLOY_VERSION__
     */
    private function buildMultipartHeaders(): array
    {
        $apiKey = $this->getApiKey();

        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'User-Agent' => 'Joomla-AI-Framework'
        ];
    }

    /**
     * Get the OpenAI API key.
     *
     * @return  string  The API key
     * @throws  AuthenticationException  If API key is not found
     * @since  __DEPLOY_VERSION__
     */
    private function getApiKey(): string
    {
        // To do: Move this to a configuration file or environment variable
        $apiKey = $this->getOption('api_key') ??
                  $_ENV['OPENAI_API_KEY'] ??
                  getenv('OPENAI_API_KEY');

        if (empty($apiKey)) {
            throw new AuthenticationException(
                $this->getName(),
                ['message' => 'OpenAI API key not configured. Set OPENAI_API_KEY environment variable or provide api_key option.'],
                401
            );
        }

        return $apiKey;
    }

    /**
     * Parse OpenAI API response into unified Response object.
     *
     * @param   string  $responseBody  The JSON response body
     *
     * @return  Response  Unified response object
     * @throws  \Exception  If response parsing fails
     * @since  __DEPLOY_VERSION__
     */
    private function parseOpenAIResponse(string $responseBody): Response
    {
        $data = $this->parseJsonResponse($responseBody);

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        // Handle multiple choices - use first choice for content, but include all in metadata
        $content = $data['choices'][0]['message']['content'] ?? '';

        $statusCode = $this->determineAIStatusCode($data);

        $metadata = [
            'model' => $data['model'],
            'usage' => $data['usage'],
            'finish_reason' => $data['choices'][0]['finish_reason'],
            'created' => $data['created'] ?? time(),
            'id' => $data['id'],
            'choices' => $data['choices']
        ];

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            $statusCode
        );
    }

    /**
     * Parse OpenAI Image API response into unified Response object.
     *
     * @param   string  $responseBody  The JSON response body
     *
     * @return  Response  Unified response object
     * @throws  \Exception  If response parsing fails
     * @since  __DEPLOY_VERSION__
     */
    private function parseImageResponse(string $responseBody, array $payload): Response
    {
        // To Do: Clean Image API response for generation and editing
        $data = $this->parseJsonResponse($responseBody);
        // error_log('OpenAI Image Response: ' . print_r($data, true));

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        $images = [];
        $responseFormat = '';

        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $imageData) {
                $imageItem = [];

                // Handle URL format
                if (isset($imageData['url'])) {
                    $imageItem['url'] = $imageData['url'];
                    $responseFormat = 'url';
                }

                // Handle base64 format
                if (isset($imageData['b64_json'])) {
                    $imageItem['b64_json'] = $imageData['b64_json'];
                    $responseFormat = 'b64_json';
                }

                // Handle revised prompt (DALL-E 3 only)
                if (isset($imageData['revised_prompt'])) {
                    $imageItem['revised_prompt'] = $imageData['revised_prompt'];
                }

                $images[] = $imageItem;
            }
        }

        $content = '';
        if ($responseFormat === 'url') {
            // For URLs, create a clean list
            $urls = array_column($images, 'url');
            $content = count($urls) === 1 ? $urls[0] : json_encode($urls, JSON_PRETTY_PRINT);
        } elseif ($responseFormat === 'b64_json') {
            // For base64, return the data
            $base64Data = array_column($images, 'b64_json');
            $content = count($base64Data) === 1 ? $base64Data[0] : json_encode($base64Data, JSON_PRETTY_PRINT);
        }

        $metadata = [
            'model' => $data['model'] ?? $payload['model'],
            'created' => $data['created'] ?? time(),
            'response_format' => $responseFormat,
            'image_count' => count($images),
            'images' => $images
        ];

        if ($responseFormat === 'url') {
            $metadata['url_expires'] = 'URLs are valid for 60 minutes';
        } elseif ($responseFormat === 'b64_json') {
            $metadata['format'] = 'base64_png';
        }

        if (isset($data['usage'])) {
            $metadata['usage'] = $data['usage'];
        }

        if (isset($data['model'])) {
            $metadata['model'] = $data['model'];
        }

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            200
        );
    }

    /**
     * Parse OpenAI Audio API response into unified Response object.
     *
     * @param   string  $responseBody  The binary or JSON response body
     * @param   array   $payload       The original request payload for metadata
     *
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    private function parseAudioResponse(string $responseBody, array $payload): Response
    {

        if ($this->isJsonResponse($responseBody)) {
            $data = $this->parseJsonResponse($responseBody);

            if (isset($data['error'])) {
                throw new ProviderException($this->getName(), $data);
            }
        }

        $metadata = [
            'model' => $payload['model'],
            'voice' => $payload['voice'],
            'format' => $payload['response_format'],
            'speed' => $payload['speed'] ?? 1.0,
            'content_type' => $this->detectAudioMimeType($payload['response_format']),
            'data_type' => 'binary_audio',
            'size_bytes' => strlen($responseBody),
            'created' => time()
        ];

        // Add instructions if present (gpt-4o-mini-tts only)
        if (isset($payload['instructions'])) {
            $metadata['instructions'] = $payload['instructions'];
        }

        return new Response(
            $responseBody, // Binary audio data
            $this->getName(),
            $metadata,
            200
        );
    }

    /**
     * Parse OpenAI Audio API response (transcription/translation) into unified Response object.
     *
     * @param   string  $responseBody  The response body
     * @param   array   $payload       The original request payload for metadata
     * @param   string  $apiType       Either ' Transcription' or 'Translation'
     *
     * @return  Response  Unified response object
     * @throws  \Exception  If response parsing fails
     * @since   __DEPLOY_VERSION__
     */
    private function parseAudioTextResponse(string $responseBody, array $payload, string $apiType): Response
    {
        $responseFormat = $payload['response_format'];
        $content = '';
        $metadata = [];

        switch ($responseFormat) {
            case 'json':
            case 'verbose_json':
                $data = $this->parseJsonResponse($responseBody);

                if (isset($data['error'])) {
                    throw new ProviderException($this->getName(), $data);
                }

                $content = $data['text'] ?? '';
                $metadata = [
                    'model' => $payload['model'],
                    'response_format' => $responseFormat,
                    'created' => time()
                ];

                if (isset($data['usage'])) {
                    $metadata['usage'] = $data['usage'];
                }

                // Add language info for transcription
                if ($apiType === 'Transcription' && isset($data['language'])) {
                    $metadata['language'] = $data['language'];
                }

                if (isset($data['duration'])) {
                    $metadata['duration'] = $data['duration'];
                }

                if ($responseFormat === 'verbose_json' && isset($data['segments'])) {
                    $metadata['segments'] = $data['segments'];
                }

                if (isset($data['words'])) {
                    $metadata['words'] = $data['words'];
                }

                break;

            case 'text':
                $content = trim($responseBody);
                $metadata = [
                    'model' => $payload['model'],
                    'response_format' => 'text',
                    'created' => time()
                ];
                break;

            case 'srt':
            case 'vtt':
                $content = $responseBody;
                $metadata = [
                    'model' => $payload['model'],
                    'response_format' => $responseFormat,
                    'created' => time()
                ];

                if ($apiType === 'Transcription') {
                    $metadata['subtitle_format'] = $responseFormat;
                }
                break;

            default:
                throw new ProviderException($this->getName(), ['error' => 'Unsupported response format: ' . $responseFormat]);
        }

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            200
        );
    }

    /**
     * Parse OpenAI Embeddings API response into unified Response object.
     *
     * @param   string  $responseBody  The JSON response body
     * @param   array   $payload       The original request payload for metadata
     *
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    private function parseEmbeddingResponse(string $responseBody, array $payload): Response
    {
        $data = $this->parseJsonResponse($responseBody);

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        $embeddings = [];
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $embeddingData) {
                $embeddings[] = [
                    'embedding' => $embeddingData['embedding'],
                    'index' => $embeddingData['index'],
                    'object' => $embeddingData['object']
                ];
            }
        }

        $contentData = count($embeddings) === 1 ? $embeddings[0]['embedding'] : $embeddings;
        $content = json_encode($contentData);

        $metadata = [
            'model' => $data['model'] ?? $payload['model'],
            'object' => $data['object'],
            'embedding_count' => count($embeddings),
            'encoding_format' => $payload['encoding_format'],
            'input_type' => is_array($payload['input']) ? 'array' : 'string',
            'raw_embeddings' => $embeddings,
        ];

        if (isset($data['usage'])) {
            $metadata['usage'] = $data['usage'];
        }

        if (isset($payload['dimensions'])) {
            $metadata['requested_dimensions'] = $payload['dimensions'];
        }

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            200
        );
    }

    /**
     * Basic validation for messages array
     *
     * @param   array   $messages  Array of messages to validate
     *
     * @throws  \InvalidArgumentException  If basic structure is invalid
     * @since   __DEPLOY_VERSION__
     */
    private function validateMessages(array $messages): void
    {
        $validRoles = ['developer', 'system', 'user', 'assistant', 'tool', 'function'];

        foreach ($messages as $index => $message) {
            if (!is_array($message) || !isset($message['role'])) {
                throw InvalidArgumentException::invalidParameter('messages', $message, 'openai', "Message at index $index must be an array with a 'role' field.");
            }

            if (!in_array($message['role'], $validRoles)) {
                throw InvalidArgumentException::invalidParameter('role', $message['role'], 'openai', "Invalid role '{$message['role']}' at message index $index. Valid roles are: " . implode(', ', $validRoles));
            }

            // For most roles, content is required (except assistant with tool_calls)
            if (
                !isset($message['content']) &&
                !($message['role'] === 'assistant' && (isset($message['tool_calls']) || isset($message['function_call'])))
            ) {
                throw InvalidArgumentException::missingParameter('content at message index ' . $index, 'openai');
            }
        }
    }

    /**
     * Validate image prompt for generation and editing operations.
     *
     * @param   string  $prompt     The prompt to validate
     * @param   string  $model      The model being used
     *
     * @return  void
     * @throws  \InvalidArgumentException  If validation fails
     * @since   __DEPLOY_VERSION__
     */
    private function validateImagePrompt(string $prompt, string $model): void
    {
        // Max lengths per model
        $maxLengths = [
            'gpt-image-1' => 32000,
            'dall-e-2' => 1000,
            'dall-e-3' => 4000
        ];

        // Validate prompt length
        if (isset($maxLengths[$model]) && strlen(trim($prompt)) > $maxLengths[$model]) {
            throw InvalidArgumentException::invalidParameter('prompt', $prompt, 'openai', "Prompt length (" . strlen(trim($prompt)) . ") exceeds maximum for $model ({$maxLengths[$model]} characters)");
        }
    }

    /**
     * Validate inputs for image editing.
     *
     * @param   mixed   $images   Single image path or array of image paths
     * @param   string  $prompt   Description of desired edits
     * @param   string  $model    The model to use
     * @param   array   $options  Additional options
     *
     * @throws  \InvalidArgumentException  If inputs are invalid
     * @since   __DEPLOY_VERSION__
     */
    private function validateImageEditInputs($images, string $prompt, string $model, array $options): void
    {
        $this->validateImagePrompt($prompt, $model);

        // Validate images
        if (is_string($images)) {
            $this->validateImageFile($images, $model, 'edit');
        } else {
            if ($model !== 'gpt-image-1') {
                throw InvalidArgumentException::invalidParameter('images', $images, 'openai', 'Multiple images only supported with gpt-image-1 model.');
            }

            if (count($images) > 16) {
                throw InvalidArgumentException::invalidParameter('images', $images, 'openai', 'Maximum 16 images allowed for gpt-image-1.');
            }

            foreach ($images as $imagePath) {
                $this->validateImageFile($imagePath, $model, 'edit');
            }
        }
    }

    /**
     * Validate an image file.
     *
     * @param   string  $imagePath  Path to the image file
     * @param   string  $model      The model being used
     * @param   string  $operation  The operation
     *
     * @throws  \InvalidArgumentException  If file is invalid
     * @since   __DEPLOY_VERSION__
     */
    private function validateImageFile(string $imagePath, string $model, string $operation): void
    {
        if (!file_exists($imagePath)) {
            throw InvalidArgumentException::fileNotFound($imagePath, 'openai');
        }

        $fileSize = filesize($imagePath);
        $fileInfo = pathinfo($imagePath);
        $extension = strtolower($fileInfo['extension'] ?? '');

        if ($model === 'gpt-image-1') {
            // gpt-image-1 supports png, webp, jpg, max 50MB
            if (!in_array($extension, ['png', 'webp', 'jpg', 'jpeg'])) {
                throw InvalidArgumentException::invalidParameter('image', $imagePath, 'openai', "For gpt-image-1, image must be png, webp, or jpg. Got: $extension");
            }

            if ($fileSize > 50 * 1024 * 1024) { // 50MB
                throw InvalidArgumentException::fileSizeExceeded($imagePath, $fileSize, 50, $model, 'openai');
            }
        } elseif ($model === 'dall-e-2') {
            // dall-e-2 requires square PNG, max 4MB
            if ($extension !== 'png') {
                throw InvalidArgumentException::invalidParameter('image', $imagePath, 'openai', "For dall-e-2, image must be a PNG file. Got: $extension");
            }

            if ($fileSize > 4 * 1024 * 1024) { // 4MB
                throw InvalidArgumentException::fileSizeExceeded($imagePath, $fileSize, 4, $model, 'openai');
            }

            // Check if image is square (for variations)
            if ($operation === 'variation') {
                $imageInfo = getimagesize($imagePath);
                if ($imageInfo === false) {
                    throw InvalidArgumentException::invalidParameter('image', $imagePath, 'openai', "Unable to read image dimensions from: $imagePath");
                }
                if ($imageInfo[0] !== $imageInfo[1]) {
                    throw InvalidArgumentException::invalidParameter('image', $imagePath, 'openai', "For dall-e-2 variations, image must be square. Current dimensions: {$imageInfo[0]}x{$imageInfo[1]}");
                }
            }
        }
    }

    /**
     * Validate a mask file.
     *
     * @param   string  $maskPath  Path to the mask file
     *
     * @throws  \InvalidArgumentException  If mask file is invalid
     * @since   __DEPLOY_VERSION__
     */
    private function validateMaskFile(string $maskPath): void
    {
        if (!file_exists($maskPath)) {
            throw InvalidArgumentException::fileNotFound($maskPath, 'openai');
        }

        $fileSize = filesize($maskPath);
        $fileInfo = pathinfo($maskPath);
        $extension = strtolower($fileInfo['extension'] ?? '');

        if ($extension !== 'png') {
            throw InvalidArgumentException::invalidFileFormat($maskPath, $extension, ['png'], 'openai');
        }

        if ($fileSize > 4 * 1024 * 1024) { // 4MB
            throw InvalidArgumentException::fileSizeExceeded($maskPath, $fileSize, 4, 'openai');
        }
    }

    /**
     * Validate image size parameter.
     *
     * @param   string  $size       The size to validate
     * @param   string  $model      The model being used
     * @param   string  $operation  The operation (generation, edit, variation)
     *
     * @throws  \InvalidArgumentException  If size is invalid
     * @since   __DEPLOY_VERSION__
     */
    private function validateImageSize(string $size, string $model, string $operation): void
    {
        $validSizes = [];

        switch ($model) {
            case 'gpt-image-1':
                $validSizes = ['1024x1024', '1536x1024', '1024x1536', 'auto'];
                break;

            case 'dall-e-2':
                $validSizes = ['256x256', '512x512', '1024x1024'];
                break;

            case 'dall-e-3':
                $validSizes = ['1024x1024', '1792x1024', '1024x1792'];
                break;
        }

        if (!in_array($size, $validSizes)) {
            throw InvalidArgumentException::invalidImageSize($size, $validSizes, $model, $operation);
        }
    }

    /**
     * Validate image quality parameter.
     *
     * @param   string  $quality  The quality to validate
     * @param   string  $model    The model being used
     *
     * @throws  \InvalidArgumentException  If quality is invalid
     * @since   __DEPLOY_VERSION__
     */
    private function validateImageQuality(string $quality, string $model): void
    {
        $validQualities = [];

        switch ($model) {
            case 'gpt-image-1':
                $validQualities = ['auto', 'high', 'medium', 'low'];
                break;

            case 'dall-e-2':
                $validQualities = ['standard'];
                break;

            case 'dall-e-3':
                $validQualities = ['auto', 'hd', 'standard'];
                break;
        }

        if (!in_array($quality, $validQualities)) {
            throw InvalidArgumentException::invalidParameter('quality', $quality, 'openai', 'Valid qualities: ' . implode(', ', $validQualities) . ' for model: ' . $model);
        }
    }

    /**
     * Validate audio file according to OpenAI API requirements.
     *
     * @param   string  $audioFile  Path to the audio file
     *
     * @throws  \InvalidArgumentException  If audio file is invalid
     * @throws  \Exception  If file cannot be read
     * @since   __DEPLOY_VERSION__
     */
    private function validateAudioFile(string $audioFile): void
    {
        if (!file_exists($audioFile)) {
            throw InvalidArgumentException::fileNotFound($audioFile, 'openai');
        }

        $audioData = file_get_contents($audioFile);
        if ($audioData === false) {
            throw InvalidArgumentException::fileNotFound($audioFile, 'openai');
        }

        $fileInfo = pathinfo($audioFile);
        $extension = strtolower($fileInfo['extension'] ?? '');

        if (!in_array($extension, self::TRANSCRIPTION_INPUT_FORMATS)) {
            throw InvalidArgumentException::invalidFileFormat($audioFile, $extension, self::TRANSCRIPTION_INPUT_FORMATS, 'openai');
        }

        // Check file size (OpenAI has a 25MB limit for audio files)
        $fileSize = filesize($audioFile);
        if ($fileSize > 25 * 1024 * 1024) { // 25MB
            throw InvalidArgumentException::fileSizeExceeded($audioFile, $fileSize, 25, 'openai');
        }
    }

    /**
     * Determine status code based on OpenAI's finish_reason.
     *
     * @param   array  $data  Parsed OpenAI response
     *
     * @return  integer  Status Code
     * @since   __DEPLOY_VERSION__
     */
    private function determineAIStatusCode(array $data): int
    {
        $finishReason = $data['choices'][0]['finish_reason'];

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
