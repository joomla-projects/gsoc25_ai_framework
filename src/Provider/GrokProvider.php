<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  Copyright (C) 2025 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Provider;

use Joomla\AI\AbstractProvider;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Interface\ChatInterface;
use Joomla\AI\Interface\ImageInterface;
use Joomla\AI\Interface\ModelInterface;
use Joomla\AI\Interface\ProviderInterface;
use Joomla\AI\Interface\StreamInterface;
use Joomla\AI\Interface\ToolInterface;
use Joomla\AI\Response\Response;
use Joomla\Http\HttpFactory;

/**
 * Grok (xAI) provider implementation.
 *
 * Uses xAI's OpenAI-compatible Chat Completions API at https://api.x.ai/v1.
 *
 * @since  __DEPLOY_VERSION__
 */
class GrokProvider extends AbstractProvider implements ProviderInterface, ChatInterface, StreamInterface, ToolInterface, ImageInterface, ModelInterface
{
    use OpenAICompatibleTrait;

    /**
     * Default chat and vision model
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    private const DEFAULT_MODEL = 'grok-4.7';

    /**
     * Default image generation model
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    private const DEFAULT_IMAGE_MODEL = 'grok-imagine-image-2.0';

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

        $this->baseUrl = rtrim((string) $this->getOption('base_url', 'https://api.x.ai/v1'), '/');
    }

    /**
     * Check if the Grok provider is supported.
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
     * @return  string  The provider name
     * @since  __DEPLOY_VERSION__
     */
    public function getName(): string
    {
        return 'Grok';
    }

    /**
     * Get the default capability map for Grok.
     * Override via the "capabilities" provider option or in a subclass.
     *
     * The lists are only used by isModelCapable(), getChatModels() and getImageModels();
     * requests are not blocked for models missing from them.
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    protected function getDefaultCapabilities(): array
    {
        $textModels = [
            'grok-4.7',
            'grok-4.6',
            'grok-4.5',
            'grok-4.3',
            'grok-4.20-0309-reasoning',
            'grok-4.20-0309-non-reasoning',
            'grok-4.20-multi-agent-0309',
            'grok-build-0.1',
        ];

        return [
            'models' => [
                'chat'   => $textModels,
                // Chat Completions accepts image input; xAI does not restrict it to certain text models
                'vision' => $textModels,
                'image'  => ['grok-imagine-image-2.0', 'grok-imagine-image', 'grok-imagine-image-quality'],
            ],
            'image_max_count' => 10,
        ];
    }

    /**
     * Get the known image generation models.
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    public function getImageModels(): array
    {
        return $this->getCapability('models', 'image', []);
    }

    /**
     * Ask a question about an image.
     *
     * @param   string  $message  The question about the image
     * @param   string  $image    An http(s) image URL or a base64 data URI (JPEG or PNG, up to 20 MiB)
     * @param   array   $options  The same options as chat(), plus "detail" (auto, low, high)
     *
     * @return  Response
     * @throws  InvalidArgumentException  If the image is not a URL or a JPEG/PNG data URI
     * @since  __DEPLOY_VERSION__
     */
    public function vision(string $message, string $image, array $options = []): Response
    {
        $this->assertValidImage($image);

        return $this->chat($message, $this->addVisionMessage($message, $image, $options));
    }

    /**
     * Generate images from a text prompt.
     *
     * @param   string  $prompt   Description of the image
     * @param   array   $options  model (default: the "image_model" provider option, else grok-imagine-image-2.0),
     *                            n (1-10), response_format (url, b64_json)
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function generateImage(string $prompt, array $options = []): Response
    {
        $payload = $this->buildImageRequestPayload($prompt, $options);

        return $this->parseImageResponse((string) $this->postJson($this->baseUrl . '/images/generations', $payload)->getBody(), $payload);
    }

    /**
     * Image editing is not available in the xAI API.
     *
     * @param   string|array  $imagePath  Path to the image
     * @param   string        $prompt     Description of the change
     * @param   array         $options    Options
     *
     * @return  Response
     * @throws  ProviderException  Always
     * @since   __DEPLOY_VERSION__
     */
    public function editImage(string|array $imagePath, string $prompt, array $options = []): Response
    {
        throw new ProviderException($this->getName(), ['message' => 'Image editing is not supported by the Grok provider. Use generateImage() instead.']);
    }

    /**
     * Image variations are not available in the xAI API.
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
        throw new ProviderException($this->getName(), ['message' => 'Image variations are not supported by the Grok provider. Use generateImage() instead.']);
    }

    /**
     * Build the chat completions payload.
     *
     * Options: model, messages, system, max_tokens, temperature, top_p, stop, frequency_penalty,
     * presence_penalty, n, seed, tools, tool_choice, parallel_tool_calls, response_schema.
     * Some reasoning models reject sampling and penalty options.
     *
     * @param   string  $message  The user message
     * @param   array   $options  Request options
     *
     * @return  array
     * @since  __DEPLOY_VERSION__
     */
    private function buildChatRequestPayload(string $message, array $options): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->defaultModel ?? $this->getOption('model', self::DEFAULT_MODEL),
            'messages' => $this->buildChatMessages($message, $options),
        ];

        foreach (['max_tokens' => 'int', 'n' => 'int', 'seed' => 'int', 'temperature' => 'float', 'top_p' => 'float', 'frequency_penalty' => 'float', 'presence_penalty' => 'float'] as $key => $type) {
            if (isset($options[$key])) {
                $payload[$key] = $type === 'int' ? (int) $options[$key] : (float) $options[$key];
            }
        }

        if (isset($options['stop'])) {
            $payload['stop'] = $options['stop'];
        }

        return $this->addToolAndSchemaParameters($payload, $options);
    }

    /**
     * Build the image generation payload.
     *
     * @param   string  $prompt   The prompt
     * @param   array   $options  Request options
     *
     * @return  array
     * @throws  InvalidArgumentException  If an option is invalid
     * @since   __DEPLOY_VERSION__
     */
    private function buildImageRequestPayload(string $prompt, array $options): array
    {
        // Not the chat default model: image generation needs an image model
        $payload = [
            'model' => $options['model'] ?? $this->getOption('image_model', self::DEFAULT_IMAGE_MODEL),
            'prompt' => $prompt,
        ];

        if (isset($options['n'])) {
            $n = (int) $options['n'];
            $max = (int) $this->getCapability('image_max_count', null, 10);

            if ($n < 1 || $n > $max) {
                throw InvalidArgumentException::invalidParameter('n', $options['n'], 'grok', "Parameter \"n\" must be between 1 and $max.", ['min_value' => 1, 'max_value' => $max]);
            }

            $payload['n'] = $n;
        }

        if (isset($options['response_format'])) {
            if (!in_array($options['response_format'], ['url', 'b64_json'], true)) {
                throw InvalidArgumentException::invalidParameter('response_format', $options['response_format'], 'grok', 'Response format must be either "url" or "b64_json".', ['valid_values' => ['url', 'b64_json']]);
            }

            $payload['response_format'] = $options['response_format'];
        }

        return $payload;
    }

    /**
     * Check a vision image: an http(s) URL or a JPEG/PNG base64 data URI.
     *
     * @param   string  $image  The image
     *
     * @throws  InvalidArgumentException
     * @since   __DEPLOY_VERSION__
     */
    private function assertValidImage(string $image): void
    {
        if (preg_match('#^https?://#i', $image) && filter_var($image, FILTER_VALIDATE_URL)) {
            return;
        }

        if (preg_match('#^data:image/(jpeg|jpg|png);base64,#i', $image)) {
            return;
        }

        throw InvalidArgumentException::invalidParameter('image', substr($image, 0, 100), 'grok', 'Image must be an http(s) URL or a JPEG/PNG base64 data URI.');
    }
}
