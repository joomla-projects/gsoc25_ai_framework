<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Interface;

use Joomla\AI\Response\Response;

/**
 * Interface for AI providers that support tool (function) calling.
 *
 * Tools are passed to chat() with the "tools" option:
 * ['name' => 'get_weather', 'description' => '...', 'parameters' => <JSON schema>, 'strict' => true].
 * Requested calls are available from Response::getToolCalls().
 *
 * @since  __DEPLOY_VERSION__
 */
interface ToolInterface
{
    /**
     * Build the messages to append to the conversation after running the requested tools.
     *
     * Returns the assistant turn that requested the tools followed by the tool results, in the
     * provider's own message format, ready to add to the "messages" option of the next chat() call.
     *
     * @param   Response  $response  The response that contains tool calls
     * @param   array     $results   Tool call id => result (string, or any JSON-encodable value)
     *
     * @return  array
     * @since   __DEPLOY_VERSION__
     */
    public function buildToolResultMessages(Response $response, array $results): array;
}
