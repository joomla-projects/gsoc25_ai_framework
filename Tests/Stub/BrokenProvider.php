<?php

namespace Joomla\AI\Tests\Stub;

/**
 * Provider whose constructor fails.
 */
class BrokenProvider extends TestProvider
{
    public function __construct($options = [], $httpFactory = null)
    {
        throw new \RuntimeException('boom');
    }
}
