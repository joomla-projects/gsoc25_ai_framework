<?php

namespace Joomla\AI\Tests;

use Joomla\AI\AI;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Tests\Stub\TestProvider;
use PHPUnit\Framework\TestCase;

class AITest extends TestCase
{
    public function testDelegatesCallsToProvider(): void
    {
        $ai = new AI(new TestProvider());

        $this->assertSame('a b c', $ai->echo('a', 'b', 'c'));
        $this->assertSame(['test-model'], $ai->getAvailableModels());
        $this->assertSame('Test', $ai->getName());
    }

    public function testGetProviderReturnsTheSameInstance(): void
    {
        $provider = new TestProvider();

        $this->assertSame($provider, (new AI($provider))->getProvider());
    }

    public function testUnsupportedMethodThrowsProviderException(): void
    {
        $ai = new AI(new TestProvider());

        try {
            $ai->generateImage('a cat');
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame('Test', $e->getProvider());
            $this->assertStringContainsString('Method generateImage is not supported by Test', $e->getMessage());
        }
    }
}
