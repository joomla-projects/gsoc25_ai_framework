<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  Copyright (C) 2025 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Provider;

use Joomla\AI\AbstractProvider;
use Joomla\AI\Interface\AudioInterface;
use Joomla\AI\Interface\ChatInterface;
use Joomla\AI\Interface\ImageInterface;
use Joomla\AI\Interface\ModelInterface;
use Joomla\AI\Response\Response;

/**
 * OpenAI provider implementation for chat completions.
 *
 * @since  __DEPLOY_VERSION__
 */
class OpenAIProvider extends AbstractProvider implements ChatInterface, ModelInterface, ImageInterface, AudioInterface
{
    /**
     * Default OpenAI API endpoint for chat completions
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const DEFAULT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * OpenAI API endpoint for image generation
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const IMAGE_ENDPOINT = 'https://api.openai.com/v1/images/generations';

    /**
     * OpenAI API endpoint for image editing
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const IMAGE_EDIT_ENDPOINT = 'https://api.openai.com/v1/images/edits';

    /**
     * OpenAI API endpoint for audio transcription
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const AUDIO_TRANSCRIPTION_ENDPOINT = 'https://api.openai.com/v1/audio/transcriptions';

    /**
     * OpenAI API endpoint for image variations
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const IMAGE_VARIATIONS_ENDPOINT = 'https://api.openai.com/v1/images/variations';

    /**
     * OpenAI API endpoint for audio speech synthesis
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const AUDIO_SPEECH_ENDPOINT = 'https://api.openai.com/v1/audio/speech';

    /**
     * OpenAI API endpoint for audio translation
     * 
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private const AUDIO_TRANSLATION_ENDPOINT = 'https://api.openai.com/v1/audio/translations';

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
     * Models that support audio transcription.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const TRANSCRIPTION_MODELS = ['gpt-4o-transcribe', 'gpt-4o-mini-transcribe', 'whisper-1'];

    /**
     * Available voices for text-to-speech.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private const VOICES = ['alloy', 'ash', 'ballad', 'coral', 'echo', 'fable', 'nova', 'onyx', 'sage', 'shimmer'];

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
     * Get all available models for this provider.
     *
     * @return  array  Array of available model names
     * @since   __DEPLOY_VERSION__
     */
    public function getAvailableModels(): array
    {
        $headers = $this->buildHeaders();
        $response = $this->makeGetRequest('https://api.openai.com/v1/models', $headers);
        $this->validateResponse($response);
        $data = $this->parseJsonResponse($response->body);
        
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
        $requestData = $this->buildChatRequestPayload($message, $options, 'chat');

        // To Do: Remove repetition 
        $endpoint = $this->getEndpoint();
        $headers = $this->buildHeaders();
        
        $httpResponse = $this->makePostRequest(
            $endpoint, 
            json_encode($requestData), 
            $headers
        );
        
        $this->validateResponse($httpResponse);
        
        return $this->parseOpenAIResponse($httpResponse->body);
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
    public function chatWithVision(string $message, string $image, array $options = []): Response
    {
        
        $requestData = $this->buildVisionRequestPayload($message, $image, $options, 'vision');
        
        $endpoint = $this->getEndpoint();
        $headers = $this->buildHeaders();
        
        $httpResponse = $this->makePostRequest(
            $endpoint, 
            json_encode($requestData), 
            $headers
        );
        
        $this->validateResponse($httpResponse);
        
        return $this->parseOpenAIResponse($httpResponse->body);
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
        $requestData = $this->buildImageRequestPayload($prompt, $options, 'image');
        
        $headers = $this->buildHeaders();
        
        $httpResponse = $this->makePostRequest(
            self::IMAGE_ENDPOINT, 
            json_encode($requestData), 
            $headers
        );
        
        $this->validateResponse($httpResponse);
        
        return $this->parseImageResponse($httpResponse->body);
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
        // To Do: Validate image file
        
        $formData = $this->buildImageVariationPayload($imagePath, $options);
        
        $headers = $this->buildMultipartHeaders();
        
        $httpResponse = $this->makeMultipartPostRequest(
            self::IMAGE_VARIATIONS_ENDPOINT, 
            $formData, 
            $headers
        );
        
        $this->validateResponse($httpResponse);
        
        return $this->parseImageResponse($httpResponse->body);
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
        // To Do: Validate inputs

        $formData = $this->buildImageEditPayload($images, $prompt, $options);
        
        $headers = $this->buildMultipartHeaders();
        
        $httpResponse = $this->makeMultipartPostRequest(
            self::IMAGE_EDIT_ENDPOINT, 
            $formData, 
            $headers
        );
        
        $this->validateResponse($httpResponse);
        
        return $this->parseImageResponse($httpResponse->body);
    }

    /**
     * Generate speech audio from text input.
     *
     * @param   string  $text     The text to convert to speech
     * @param   string  $model    The model to use for speech synthesis
     * @param   string  $voice    The voice to use for speech synthesis
     * @param   array   $options  Additional options for speech generation
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function speech(string $text, string $model, string $voice, array $options = []): Response    
    {
        // To Do: Validate inputs
        
        $payload = $this->buildSpeechPayload($text, $model, $voice, $options);

        //getEndpoint?
        $headers = $this->buildHeaders();
        $httpResponse = $this->makePostRequest(self::AUDIO_SPEECH_ENDPOINT, json_encode($payload), $headers);
        
        $this->validateResponse($httpResponse);
        
        return $this->parseAudioResponse($httpResponse->body, $payload);
    }

    /**
     * Transcribe audio into text.
     *
     * @param   string  $audioFile  Path to audio file
     * @param   string  $model      The transcription model to use
     * @param   array   $options    Additional options for transcription
     *
     * @return  Response  The AI response containing transcribed text
     * @throws  \InvalidArgumentException  If inputs are invalid
     * @throws  \Exception  If API request fails
     * @since   __DEPLOY_VERSION__
     */
    public function transcribe(string $audioFile, string $model, array $options = []): Response
    {
        // To Do: Validate inputs
        if (!file_exists($audioFile)) {
            throw new \InvalidArgumentException("Audio file not found: $audioFile");
        }
        
        $audioData = file_get_contents($audioFile);
        if ($audioData === false) {
            throw new \Exception("Failed to read audio file: $audioFile");
        }
        
        $payload = $this->buildTranscriptionPayload($audioFile, $model, $options);
        
        $headers = $this->buildMultipartHeaders();
        
        $httpResponse = $this->makeMultipartPostRequest(
            self::AUDIO_TRANSCRIPTION_ENDPOINT,
            $payload,
            $headers
        );
        
        $this->validateResponse($httpResponse);

        return $this->parseTranscriptionResponse($httpResponse->body, $payload);
    }

    /**
     * Translate audio to English text.
     *
     * @param   string  $audioFile  Path to audio file
     * @param   string  $model      Model to use for translation
     * @param   array   $options    Additional options
     *
     * @return  Response  Translation response
     * @since   __DEPLOY_VERSION__
     */
    public function translate(string $audioFile, string $model, array $options = []): Response
    {
        // To Do: Validate inputs

        $formData = $this->buildTranslationFormData($audioFile, $model, $options);

        $headers = $this->buildMultipartHeaders();

        $httpResponse = $this->makeMultipartPostRequest(
            self::AUDIO_TRANSLATION_ENDPOINT,
            $formData,
            $headers
        );

        $this->validateResponse($httpResponse);

        return $this->parseTranslationResponse($httpResponse->body, $formData);
    }

    /**
     * Ask method - alias for chat/prompt for now
     *
     * @param   string  $question  The question to ask
     * @param   array   $options   Additional options
     * 
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    public function ask(string $question, array $options = []): Response
    {
        return $this->chat($question, $options);
    }

    /**
     * Alias for chat/prompt for now.
     *
     * @param   string  $prompt   The prompt to send
     * @param   array   $options  Additional options
     * 
     * @return  Response
     * @since  __DEPLOY_VERSION__
     */
    public function prompt(string $prompt, array $options = []): Response
    {
        return $this->chat($prompt, $options);
    }

    /**
     * Build the request payload for OpenAI API.
     *
     * @param   string  $message   The user message to send
     * @param   array   $options  Additional options
     * 
     * @return  array   The request payload
     * @throws  \InvalidArgumentException  If model does not support chat capability
     * @since  __DEPLOY_VERSION__
     */
    private function buildChatRequestPayload(string $message, array $options = [], string $capability): array
    {
        $model = $options['model'] ?? $this->getOption('model', 'gpt-4o-mini');
        
        if (!$this->isModelCapable($model, $capability)) {
            throw new \InvalidArgumentException("Model '$model' does not support $capability capability");
        }

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message
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
     * Build the request payload for OpenAI API with vision capability.
     *
     * @param   string  $message  The chat message about the image
     * @param   string  $image    Image URL or base64 encoded image
     * @param   array   $options  Additional options
     * 
     * @return  array   The request payload
     * @throws  \InvalidArgumentException  If model does not support vision capability
     * @since  __DEPLOY_VERSION__
     */
    private function buildVisionRequestPayload(string $message, string $image, array $options = [], string $capability): array
    {
        $model = $options['model'] ?? $this->getOption('model', 'gpt-4o-mini');
        
        if (!$this->isModelCapable($model, $capability)) {
            throw new \InvalidArgumentException("Model '$model' does not support $capability capability");
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
     * Build request payload for image generation.
     *
     * @param   string  $prompt      The image generation prompt.
     * @param   array   $options     Additional options for the request.
     * @param   string  $capability  Required capability.
     *
     * @return  array  The request payload.
     * @since   __DEPLOY_VERSION__
     */
    private function buildImageRequestPayload(string $prompt, array $options, string $capability): array
    {
        $model = $options['model'] ?? 'dall-e-2';

        if (!$this->isModelCapable($model, $capability)) {
            throw new \InvalidArgumentException("Model '$model' does not support $capability capability");
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt
        ];
        
        if (in_array($model, ['dall-e-2', 'dall-e-3'])) {
            $responseFormat = $options['response_format'] ?? 'b64_json';
            if (in_array($responseFormat, ['url', 'b64_json'])) {
                $payload['response_format'] = $responseFormat;
            } else {
                throw new \InvalidArgumentException("Unsupported response format: $responseFormat");
            }
        }
        
        // To Do: Add optional parameters if provided

        return $payload;
    }
    
    /**
     * Build the request payload for image variation request.
     *
     * @param   string  $imagePath  Path to the image file.
     * @param   array   $options    Additional options for the request.
     *
     * @return  array  The form data for multipart request.
     * @since   __DEPLOY_VERSION__
     */
    private function buildImageVariationPayload(string $imagePath, array $options): array
    {
        $model = $options['model'] ?? 'dall-e-2';
        
        // Only dall-e-2 supports variations
        if ($model !== 'dall-e-2') {
            throw new \InvalidArgumentException("Model '$model' does not support image variations. Only dall-e-2 is supported.");
        }
        
        $payload = [
            'model' => $model,
            'image' => file_get_contents($imagePath)
        ];
        
        // To Do: Check additional optional parameters
        if (isset($options['n'])) {
            $n = (int) $options['n'];
            if ($n < 1 || $n > 10) {
                throw new \InvalidArgumentException('Parameter "n" must be between 1 and 10');
            }
            $payload['n'] = $n;
        }
        
        if (isset($options['size'])) {
            $validSizes = ['256x256', '512x512', '1024x1024'];
            if (!in_array($options['size'], $validSizes)) {
                throw new \InvalidArgumentException('Size must be one of: ' . implode(', ', $validSizes));
            }
            $payload['size'] = $options['size'];
        }
        
        if (isset($options['response_format'])) {
            $validFormats = ['url', 'b64_json'];
            if (!in_array($options['response_format'], $validFormats)) {
                throw new \InvalidArgumentException('Response format must be either "url" or "b64_json"');
            }
            $payload['response_format'] = $options['response_format'];
        }

        // To Do: Add optional parameters

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
        $model = $options['model'] ?? 'dall-e-2';

        // Only dall-e-2 and gpt-image-1 support image editing
        if (!in_array($model, ['dall-e-2', 'gpt-image-1'])) {
            throw new \InvalidArgumentException("Model '$model' does not support image editing.");
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt
        ];
        
        // Handle images
        if (is_string($images)) {
            // Single image
            $payload['image'] = file_get_contents($images);
        } else {
            // Multiple images (gpt-image-1)
            foreach ($images as $index => $imagePath) {
                $payload["image[{$index}]"] = file_get_contents($imagePath);
            }
        }
        
        // Add mask if provided
        if (isset($options['mask'])) {
            $payload['mask'] = file_get_contents($options['mask']);
        }

        // To Do: Check additional optional parameters
                
        return $payload;
    }

    /**
     * Build request payload for text-to-speech (TTS) synthesis.
     *
     * @param   string  $text     The text to convert to speech
     * @param   string  $model    The model to use for speech synthesis
     * @param   string  $voice    The voice to use for speech synthesis
     * @param   array   $options  Additional options for speech generation
     *
     * @return  array  The request payload.
     * @since   __DEPLOY_VERSION__
     */
    private function buildSpeechPayload(string $text, string $model, string $voice, array $options): array
    {
        $payload = [
            'input' => $text,
            'model' => $model,
            'voice' => $voice,
            'response_format' => $options['response_format'] ?? 'mp3',
        ];
        
        // To Do: Add optional parameters 
        if (isset($options['speed'])) {
            $payload['speed'] = (float) $options['speed'];
        }
        
        // Instructions only work with gpt-4o-mini-tts
        if (isset($options['instructions']) && $model === 'gpt-4o-mini-tts') {
            $payload['instructions'] = $options['instructions'];
        }
        
        return $payload;
    }

    /**
     * Build form data for transcription request.
     *
     * @param   string  $audioData  Binary audio data
     * @param   string  $model      The transcription model
     * @param   array   $options    Additional options
     *
     * @return  array   Form data for multipart request
     * @since   __DEPLOY_VERSION__
     */
    private function buildTranscriptionPayload(string $audioData, string $model, array $options): array
    {
        $payload = [
            'model' => $model,
            'file' => null,
            '_filename' => basename($audioData),
            '_filepath' => $audioData,
            'response_format' => $options['response_format'] ?? 'json',
        ];

        // To Do: Add optional parameters
        if (isset($options['language'])) {
            $payload['language'] = $options['language'];
        }
        
        return $payload;
    }

    /**
     * Build form data for translation request.
     * 
     * @param   string  $audioFile  Path to the audio file
     * @param   string  $model      The translation model to use
     * @param   array   $options    Additional options for translation
     */
    private function buildTranslationFormData(string $audioFile, string $model, array $options): array
    {
        $formData = [
            'model' => $model,
            'file' => null,
            '_filename' => basename($audioFile),
            '_filepath' => $audioFile,
            'response_format' => $options['response_format'] ?? 'json',
        ];
        
        if (isset($options['prompt'])) {
            $formData['prompt'] = $options['prompt'];
        }
        
        if (isset($options['temperature'])) {
            $formData['temperature'] = (float) $options['temperature'];
        }
        
        return $formData;
    }

    /**
     * Get the API endpoint URL.
     *
     * @return  string  The endpoint URL
     * @since  __DEPLOY_VERSION__
     */
    private function getEndpoint(): string
    {
        return $this->getOption('endpoint', self::DEFAULT_ENDPOINT);
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
     * @throws  \Exception  If API key is not found
     * @since  __DEPLOY_VERSION__
     */
    private function getApiKey(): string
    {
        // To do: Move this to a configuration file or environment variable
        $apiKey = $this->getOption('api_key') ?? 
                  $_ENV['OPENAI_API_KEY'] ?? 
                  getenv('OPENAI_API_KEY');
        
        if (empty($apiKey)) {
            throw new \Exception('OpenAI API key not configured. Set OPENAI_API_KEY environment variable.');
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
            throw new \Exception(
                'OpenAI API Error: ' . ($data['error']['message'] ?? 'Unknown error')
            );
        }

        // To Do: Handle multiple choices if needed
        $content = $data['choices'][0]['message']['content'] ?? '';
        
        $statusCode = $this->determineAIStatusCode($data);

        $metadata = [
            'model' => $data['model'],
            'usage' => $data['usage'],
            'finish_reason' => $data['choices'][0]['finish_reason'],
            'created' => $data['created'] ?? time(),
            'id' => $data['id']
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
    private function parseImageResponse(string $responseBody): Response
    {
        // To Do: Clean Image API response for generation and editing
        $data = $this->parseJsonResponse($responseBody);
        // error_log('OpenAI Image Response: ' . print_r($data, true));
        
        if (isset($data['error'])) {
            throw new \Exception(
                'OpenAI Image API Error: ' . ($data['error']['message'] ?? 'Unknown error')
            );
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
                throw new \Exception(
                    'OpenAI API Error: ' . ($data['error']['message'] ?? 'Unknown error')
                );
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
     * Parse OpenAI Transcription API response into unified Response object.
     *
     * @param   string  $responseBody  The response body
     * @param   array   $payload       The original request payload for metadata
     * 
     * @return  Response  Unified response object
     * @throws  \Exception  If response parsing fails
     * @since   __DEPLOY_VERSION__
     */
    private function parseTranscriptionResponse(string $responseBody, array $payload): Response
    {
        $responseFormat = $payload['response_format'];
        
        switch ($responseFormat) {
            case 'json':
            case 'verbose_json':
                $data = $this->parseJsonResponse($responseBody);
                
                if (isset($data['error'])) {
                    throw new \Exception(
                        'OpenAI Transcription API Error: ' . ($data['error']['message'] ?? 'Unknown error')
                    );
                }
                
                $content = $data['text'] ?? '';
                $metadata = [
                    'model' => $payload['model'],
                    'response_format' => $responseFormat,
                    'language' => $data['language'] ?? null,
                    'duration' => $data['duration'] ?? null,
                    'created' => time()
                ];
                
                if (isset($data['usage'])) {
                    $metadata['usage'] = $data['usage'];
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
                    'subtitle_format' => $responseFormat,
                    'created' => time()
                ];
                break;
                
            default:
                throw new \Exception("Unsupported response format: $responseFormat");
        }

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            200
        );
    }

    /**
     * Parse OpenAI Translation API response into unified Response object.
     *
     * @param   string  $responseBody  The response body
     * @param   array   $payload       The original request payload for metadata
     * 
     * @return  Response  Unified response object
     * @throws  \Exception  If response parsing fails
     * @since   __DEPLOY_VERSION__
     */
    private function parseTranslationResponse(string $responseBody, array $payload): Response
    {
        //To Do: Remove repetition
        $responseFormat = $payload['response_format'];
        $content = '';
        $metadata = [];
        
        switch ($responseFormat) {
            case 'json':
            case 'verbose_json':
                $data = $this->parseJsonResponse($responseBody);
                
                if (isset($data['error'])) {
                    throw new \Exception(
                        'OpenAI Translation API Error: ' . ($data['error']['message'] ?? 'Unknown error')
                    );
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
                
                if ($responseFormat === 'verbose_json' && isset($data['segments'])) {
                    $metadata['segments'] = $data['segments'];
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
                break;
                
            default:
                throw new \Exception("Unsupported response format: $responseFormat");
        }

        return new Response(
            $content,
            $this->getName(),
            $metadata,
            200
        );
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
