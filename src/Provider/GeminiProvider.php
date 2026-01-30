<?php
/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright
 * Copyright (C) 2026 Open Source Matters, Inc.
 *
 * @license
 * GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Provider;

use Joomla\AI\AbstractProvider;
use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Interface\ChatInterface;
use Joomla\AI\Interface\ModelInterface;
use Joomla\AI\Interface\ProviderInterface;
use Joomla\AI\Response\Response;
use Joomla\Http\HttpFactory;

/**
 * Google Gemini provider implementation.
 *
 * @since  __DEPLOY_VERSION__
 */
class GeminiProvider extends AbstractProvider implements ProviderInterface, ChatInterface, ModelInterface
{
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    private const CHAT_MODELS = [
        'gemini-pro',
        'gemini-1.5-pro',
        'gemini-1.5-flash',
    ];

    public function chat(string $message, array $options = []): Response
    {
        $this->validateApiKey();

        $model = $options['model'] ?? $this->defaultModel ?? 'gemini-pro';

        if (!in_array($model, self::CHAT_MODELS, true)) {
            throw new InvalidArgumentException('Unsupported Gemini model: ' . $model);
        }

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $message],
                    ],
                ]
            ],
        ];

        // Merge additional options if needed, but for now just message
        // $payload = array_merge($payload, $options);

        $url = $this->baseUrl . '/models/' . $model . ':generateContent?key=' . $this->getApiKey();

        $response = $this->makePostRequest(
            $url,
            json_encode($payload),
            ['Content-Type' => 'application/json']
        );

        $data = json_decode($response->body, true);

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new ProviderException('Invalid response received from Gemini API.', $data);
        }

        return new Response([
            'content' => $data['candidates'][0]['content']['parts'][0]['text'],
            'model'   => $model,
            'raw'     => $data,
        ]);
    }

    public function vision(string $message, string $image, array $options = []): Response
    {
         $this->validateApiKey();

        $model = $options['model'] ?? $this->defaultModel ?? 'gemini-1.5-flash';

         if (!in_array($model, self::CHAT_MODELS, true)) {
            throw new InvalidArgumentException('Unsupported Gemini model: ' . $model);
        }

        // Detect if input is a URL or base64
        // For simplicity assuming base64 or handled by caller for now as per other providers
        // But Gemini expects inlineData or fileData.
        
        // This is a placeholder for full vision support, implementing basic structure
        throw new ProviderException("Vision not fully implemented for Gemini yet");
    }

    public function models(): array
    {
        return self::CHAT_MODELS;
    }

    private function validateApiKey(): void
    {
        if (!$this->getApiKey()) {
            throw new AuthenticationException('Gemini API key is missing.');
        }
    }

    private function getApiKey(): ?string
    {
        return $this->getOption('api_key');
    }
}
