<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Conversation;

use Joomla\AI\AbstractProvider;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Interface\ChatInterface;
use Joomla\AI\Interface\StreamInterface;
use Joomla\AI\Interface\ToolInterface;
use Joomla\AI\Response\Response;

/**
 * A multi-turn conversation that keeps its history and sends it with every message.
 *
 * Works with any chat provider. Messages are stored in the provider's own format.
 *
 * @since  __DEPLOY_VERSION__
 */
class Conversation implements \JsonSerializable
{
    private array $messages = [];

    private ?Response $pendingToolResponse = null;

    private array $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0];

    /**
     * Constructor.
     *
     * @param   AbstractProvider&ChatInterface  $provider  The chat provider
     * @param   array                           $options   Default chat options for every turn (system, model,
     *                                                     max_tokens, tools, ...) plus "max_turns" to keep only
     *                                                     the last N user turns
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct(private AbstractProvider&ChatInterface $provider, private array $options = [])
    {
    }

    /**
     * Send a user message and get the reply. Both are added to the history.
     *
     * @param   string  $message  The user message
     * @param   array   $options  Options for this turn only
     *
     * @return  Response
     * @throws  InvalidArgumentException  If tool results are still pending
     * @since   __DEPLOY_VERSION__
     */
    public function send(string $message, array $options = []): Response
    {
        $this->addUserMessage($message);

        return $this->complete($this->provider->chat($message, $this->requestOptions($options)));
    }

    /**
     * Send a user message and stream the reply. Yields text deltas; getReturn() gives the Response.
     *
     * @param   string  $message  The user message
     * @param   array   $options  Options for this turn only
     *
     * @return  \Generator<int, string, mixed, Response>
     * @since   __DEPLOY_VERSION__
     */
    public function stream(string $message, array $options = []): \Generator
    {
        $provider = $this->provider;

        if (!$provider instanceof StreamInterface) {
            throw InvalidArgumentException::invalidParameter('provider', $provider->getName(), $provider->getName(), 'This provider does not support streaming.');
        }

        $this->addUserMessage($message);

        $stream = $provider->chatStream($message, $this->requestOptions($options));
        yield from $stream;

        return $this->complete($stream->getReturn());
    }

    /**
     * Send the results of the tool calls requested in the last reply and get the next reply.
     *
     * @param   array  $results  Tool call id => result
     * @param   array  $options  Options for this turn only
     *
     * @return  Response
     * @throws  InvalidArgumentException  If no tool calls are pending
     * @since   __DEPLOY_VERSION__
     */
    public function submitToolResults(array $results, array $options = []): Response
    {
        if ($this->pendingToolResponse === null || !$this->provider instanceof ToolInterface) {
            throw InvalidArgumentException::invalidParameter('results', $results, $this->provider->getName(), 'There are no pending tool calls.');
        }

        // The first message is the assistant turn, which is already in the history
        $toolMessages = array_slice($this->provider->buildToolResultMessages($this->pendingToolResponse, $results), 1);
        $this->messages = array_merge($this->messages, $toolMessages);
        $this->pendingToolResponse = null;

        return $this->complete($this->provider->chat('', $this->requestOptions($options)));
    }

    /**
     * Check whether the last reply asked for tool calls that still need results.
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    public function hasPendingToolCalls(): bool
    {
        return $this->pendingToolResponse !== null;
    }

    /**
     * Get the history in the provider's message format.
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the token usage summed over all turns.
     *
     * @return  array{input_tokens: int, output_tokens: int, total_tokens: int}
     * @since   __DEPLOY_VERSION__
     */
    public function getUsage(): array
    {
        return $this->usage;
    }

    /**
     * Forget the history (the options are kept).
     *
     * @return  $this
     * @since   __DEPLOY_VERSION__
     */
    public function clear(): static
    {
        $this->messages = [];
        $this->pendingToolResponse = null;
        $this->usage = ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0];

        return $this;
    }

    /**
     * Export the conversation so it can be stored (session, database) and restored with fromArray().
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    public function toArray(): array
    {
        $options = $this->options;
        unset($options['on_chunk']);

        return [
            'provider' => $this->provider->getName(),
            'options' => $options,
            'messages' => $this->messages,
            'usage' => $this->usage,
        ];
    }

    /**
     * Restore a conversation exported with toArray(). Pending tool calls are not restored.
     *
     * @param   AbstractProvider&ChatInterface  $provider  The provider (the same one the conversation used)
     * @param   array                           $data      Data from toArray()
     *
     * @return  self
     * @since   __DEPLOY_VERSION__
     */
    public static function fromArray(AbstractProvider&ChatInterface $provider, array $data): self
    {
        $conversation = new self($provider, (array) ($data['options'] ?? []));
        $conversation->messages = (array) ($data['messages'] ?? []);
        $conversation->usage = array_replace($conversation->usage, (array) ($data['usage'] ?? []));

        return $conversation;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function addUserMessage(string $message): void
    {
        if ($this->pendingToolResponse !== null) {
            throw InvalidArgumentException::invalidParameter('message', $message, $this->provider->getName(), 'The last reply requested tool calls. Call submitToolResults() first.');
        }

        $this->messages[] = ['role' => 'user', 'content' => $message];
        $this->trimHistory();
    }

    private function requestOptions(array $options): array
    {
        $merged = array_replace($this->options, $options, ['messages' => $this->messages]);
        unset($merged['max_turns']);

        return $merged;
    }

    private function complete(Response $response): Response
    {
        $this->messages[] = $this->provider->buildAssistantMessage($response);
        $this->pendingToolResponse = $response->hasToolCalls() ? $response : null;

        foreach ($response->getUsage() as $key => $value) {
            $this->usage[$key] += (int) $value;
        }

        return $response;
    }

    /**
     * Keep only the last "max_turns" user turns. A turn starts at a user message with text content,
     * so tool calls and their results are never split.
     */
    private function trimHistory(): void
    {
        $maxTurns = (int) ($this->options['max_turns'] ?? 0);

        if ($maxTurns < 1) {
            return;
        }

        $turnStarts = [];
        foreach ($this->messages as $index => $message) {
            if (($message['role'] ?? null) === 'user' && is_string($message['content'] ?? null)) {
                $turnStarts[] = $index;
            }
        }

        if (count($turnStarts) > $maxTurns) {
            $this->messages = array_slice($this->messages, $turnStarts[count($turnStarts) - $maxTurns]);
        }
    }
}
