<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Provider;

use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Response\Response;

/**
 * Shared code for providers with an OpenAI-compatible Chat Completions API (OpenAI, Grok):
 * response parsing, streaming, tool calls, JSON schema output and usage.
 *
 * @since  __DEPLOY_VERSION__
 */
trait OpenAICompatibleTrait
{
    /**
     * Add tools, tool_choice and JSON schema output to a chat payload.
     *
     * @param   array  $payload  The request payload
     * @param   array  $options  Request options (tools, tool_choice, parallel_tool_calls, response_schema)
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    private function addToolAndSchemaParameters(array $payload, array $options): array
    {
        $tools = $this->getToolDefinitions($options);

        if ($tools !== []) {
            $payload['tools'] = array_map(function (array $tool): array {
                $function = [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                ];

                if ($tool['strict'] !== null) {
                    $function['strict'] = $tool['strict'];
                }

                return ['type' => 'function', 'function' => $function];
            }, $tools);

            if (isset($options['tool_choice'])) {
                $choice = $options['tool_choice'];
                $payload['tool_choice'] = is_array($choice) && isset($choice['name'])
                    ? ['type' => 'function', 'function' => ['name' => $choice['name']]]
                    : $choice;
            }

            if (isset($options['parallel_tool_calls'])) {
                $payload['parallel_tool_calls'] = (bool) $options['parallel_tool_calls'];
            }
        }

        $schema = $this->getResponseSchema($options);

        if ($schema !== null) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schema['name'],
                    'schema' => $schema['schema'],
                    'strict' => $schema['strict'],
                ],
            ];
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
        $message = $response->getMetadata()['choices'][0]['message'] ?? null;

        // Streamed responses have no choices; their text is all there is
        if (!is_array($message)) {
            return parent::buildAssistantMessage($response);
        }

        return ['role' => 'assistant'] + array_intersect_key($message, array_flip(['content', 'tool_calls']));
    }

    /**
     * Build the messages to append after running the tools requested in a response.
     *
     * @param   Response  $response  The response with tool calls
     * @param   array     $results   Tool call id => result
     *
     * @return  array  The assistant message and one "tool" message per result
     * @since   __DEPLOY_VERSION__
     */
    public function buildToolResultMessages(Response $response, array $results): array
    {
        $messages = [$this->buildAssistantMessage($response)];

        foreach ($response->getToolCalls() as $call) {
            if (!array_key_exists($call['id'], $results)) {
                throw InvalidArgumentException::missingParameter("result for tool call '{$call['id']}'", $this->getName());
            }

            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $call['id'],
                'content' => $this->encodeToolResult($results[$call['id']]),
            ];
        }

        return $messages;
    }

    /**
     * Extract normalised tool calls from an OpenAI-style message.
     *
     * @param   array   $message       The assistant message
     * @param   string  $responseBody  Raw response, for errors
     *
     * @return  array
     * @throws  UnserializableResponseException  If tool call arguments are not valid JSON
     * @since   __DEPLOY_VERSION__
     */
    private function extractToolCalls(array $message, string $responseBody): array
    {
        $calls = [];

        foreach ((array) ($message['tool_calls'] ?? []) as $toolCall) {
            if (!is_array($toolCall) || !is_string($toolCall['function']['name'] ?? null)) {
                throw new UnserializableResponseException($this->getName(), $responseBody, 'Tool call in response is missing a function name');
            }

            $arguments = $toolCall['function']['arguments'] ?? '{}';
            $decoded = is_array($arguments) ? $arguments : json_decode((string) $arguments, true);

            if (!is_array($decoded)) {
                throw new UnserializableResponseException($this->getName(), $responseBody, "Arguments for tool '{$toolCall['function']['name']}' are not valid JSON");
            }

            $calls[] = [
                'id' => (string) ($toolCall['id'] ?? ''),
                'name' => $toolCall['function']['name'],
                'arguments' => $decoded,
            ];
        }

        return $calls;
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
    private function parseChatCompletion(string $responseBody): Response
    {
        $data = $this->parseJsonResponse($responseBody);

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        $this->assertResponseFields($data, ['choices.0[]'], $responseBody);

        // Handle multiple choices - use first choice for content, but include all in metadata
        $content = (string) ($data['choices'][0]['message']['content'] ?? '');

        $statusCode = $this->determineAIStatusCode($data);
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        $metadata = [
            'model' => $data['model'] ?? null,
            'usage' => $usage,
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
            'created' => $data['created'] ?? time(),
            'id' => $data['id'] ?? null,
            'choices' => $data['choices'],
            'tool_calls' => $this->extractToolCalls((array) ($data['choices'][0]['message'] ?? []), $responseBody)
        ] + $this->normaliseOpenAIUsage($usage);

        return $this->recordResponse(new Response(
            $content,
            $this->getName(),
            $metadata,
            $statusCode
        ));
    }

    /**
     * Normalise an OpenAI usage block (chat uses prompt/completion, images use input/output tokens).
     *
     * @param   array  $usage  The usage block from the API response
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    private function normaliseOpenAIUsage(array $usage): array
    {
        return $this->normaliseUsage(
            $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? null,
            $usage['output_tokens'] ?? $usage['completion_tokens'] ?? null,
            $usage['total_tokens'] ?? null
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
        $data = $this->parseJsonResponse($responseBody);

        if (isset($data['error'])) {
            throw new ProviderException($this->getName(), $data);
        }

        $this->assertResponseFields($data, ['data[]'], $responseBody);

        // Generation and editing return the same shape: a list of {url|b64_json, revised_prompt?}
        $images = [];
        $responseFormat = '';

        foreach ($data['data'] as $imageData) {
            if (!is_array($imageData)) {
                throw new UnserializableResponseException($this->getName(), $responseBody, 'Image entries in response must be objects');
            }

            $images[] = array_intersect_key($imageData, array_flip(['url', 'b64_json', 'revised_prompt']));

            if (isset($imageData['b64_json'])) {
                $responseFormat = 'b64_json';
            } elseif (isset($imageData['url'])) {
                $responseFormat = 'url';
            }
        }

        // One image: the URL or base64 string; several: a JSON list of them
        $values = $responseFormat === '' ? [] : array_column($images, $responseFormat);
        $content = count($values) === 1 ? $values[0] : ($values === [] ? '' : json_encode($values, JSON_PRETTY_PRINT));

        $metadata = [
            'model' => $data['model'] ?? $payload['model'] ?? null,
            'created' => $data['created'] ?? time(),
            'response_format' => $responseFormat,
            'image_count' => count($images),
            'images' => $images
        ];

        if ($responseFormat === 'url') {
            $metadata['url_expires'] = 'URLs are valid for 60 minutes';
        } elseif ($responseFormat === 'b64_json') {
            // gpt-image-1 can return jpeg or webp; the other models return png
            $metadata['format'] = 'base64_' . ($data['output_format'] ?? $payload['output_format'] ?? 'png');
        }

        if (isset($data['usage']) && is_array($data['usage'])) {
            $metadata['usage'] = $data['usage'];
            $metadata += $this->normaliseOpenAIUsage($data['usage']);
        }

        return $this->recordResponse(new Response(
            $content,
            $this->getName(),
            $metadata,
            200
        ));
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
                throw InvalidArgumentException::invalidParameter('messages', $message, $this->getName(), "Message at index $index must be an array with a 'role' field.");
            }

            if (!in_array($message['role'], $validRoles)) {
                throw InvalidArgumentException::invalidParameter('role', $message['role'], $this->getName(), "Invalid role '{$message['role']}' at message index $index. Valid roles are: " . implode(', ', $validRoles));
            }

            // For most roles, content is required (except assistant with tool_calls)
            if (
                !isset($message['content']) &&
                !($message['role'] === 'assistant' && (isset($message['tool_calls']) || isset($message['function_call'])))
            ) {
                throw InvalidArgumentException::missingParameter('content at message index ' . $index, $this->getName());
            }
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
        $finishReason = $data['choices'][0]['finish_reason'] ?? null;

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

    /**
     * Stream a chat completion and yield text deltas; returns the final Response.
     *
     * @param   string  $endpoint  The chat completions endpoint
     * @param   array   $payload   The request payload, with "stream" set
     * @param   array   $headers   HTTP headers
     *
     * @return  \Generator<int, string, mixed, Response>
     * @since   __DEPLOY_VERSION__
     */
    private function streamChatCompletion(string $endpoint, array $payload, array $headers): \Generator
    {
        $chunks = $this->makeStreamingPostRequest($endpoint, json_encode($payload), $headers);

        $content = '';
        $finishReason = null;
        $usage = [];
        $model = $payload['model'];
        $id = null;

        foreach ($this->readServerSentEvents($chunks) as $event) {
            if ($event['data'] === '[DONE]') {
                break;
            }

            $data = $this->decodeStreamEvent($event['data']);

            if (isset($data['error'])) {
                throw new ProviderException($this->getName(), $data);
            }

            $model = $data['model'] ?? $model;
            $id = $data['id'] ?? $id;

            if (is_array($data['usage'] ?? null)) {
                $usage = $data['usage'];
            }

            $choice = $data['choices'][0] ?? null;
            if (!is_array($choice)) {
                continue;
            }

            $finishReason = $choice['finish_reason'] ?? $finishReason;
            $delta = $choice['delta']['content'] ?? null;

            if (is_string($delta) && $delta !== '') {
                $content .= $delta;

                yield $delta;
            }
        }

        $metadata = [
            'model' => $model,
            'id' => $id,
            'usage' => $usage,
            'finish_reason' => $finishReason,
            'stream' => true,
        ] + $this->normaliseOpenAIUsage($usage);

        return $this->recordResponse(new Response(
            $content,
            $this->getName(),
            $metadata,
            $this->determineAIStatusCode(['choices' => [['finish_reason' => $finishReason]]])
        ));
    }
}
