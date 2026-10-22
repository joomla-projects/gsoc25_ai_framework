<?php

namespace Joomla\AI\Tests\Stub;

use Joomla\AI\AbstractProvider;

/**
 * Minimal provider for factory and AI tests.
 */
class TestProvider extends AbstractProvider
{
    public static function isSupported(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'Test';
    }

    public function getAvailableModels(): array
    {
        return ['test-model'];
    }

    public function options()
    {
        return $this->options;
    }

    public function echo(string ...$parts): string
    {
        return implode(' ', $parts);
    }
}
