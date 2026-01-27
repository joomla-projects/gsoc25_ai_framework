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

    public function chat(array $messages, array $options = []): Response
    {
        $this->validateApiKey();

        $model = $options['model'] ?? 'gemini-pro';

        if (!in_array($model, self::CHAT_MODELS, true)) {
            throw new InvalidArgumentException('Unsupported Gemini model: ' . $model);
        }

        $http = HttpFactory::getHttp();

        $payload = [
            'contents' => array_map(
                static function (array $message): array {
                    return [
                        'role'  => $message['role'] ?? 'user',
                        'parts' => [
                            ['text' => $message['content'] ?? ''],
                        ],
                    ];
                },
                $messages
            ),
        ];

        try {
            $response = $http->post(
                $this->baseUrl . '/models/' . $model . ':generateContent?key=' . $this->getApiKey(),
                json_encode($payload),
                ['Content-Type' => 'application/json']
            );
        } catch (\Throwable $e) {
            throw new ProviderException('Gemini API request failed: ' . $e->getMessage(), 0, $e);
        }

        $data = json_decode($response->body, true);

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new ProviderException('Invalid response received from Gemini API.');
        }

        return new Response([
            'content' => $data['candidates'][0]['content']['parts'][0]['text'],
            'model'   => $model,
            'raw'     => $data,
        ]);
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
}
