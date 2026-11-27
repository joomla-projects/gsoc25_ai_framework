<?php

namespace Joomla\AI\Tests;

use Joomla\AI\AI;
use Joomla\AI\AIFactory;
use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Provider\AnthropicProvider;
use Joomla\AI\Provider\GeminiProvider;
use Joomla\AI\Provider\OllamaProvider;
use Joomla\AI\Provider\OpenAIProvider;
use Joomla\AI\Tests\Stub\BrokenProvider;
use Joomla\AI\Tests\Stub\RoutingHttpTrait;
use Joomla\AI\Tests\Stub\TestProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AIFactoryTest extends TestCase
{
    use RoutingHttpTrait;

    public static function builtInProviders(): array
    {
        return [
            'openai' => ['openai', OpenAIProvider::class],
            'anthropic' => ['anthropic', AnthropicProvider::class],
            'ollama' => ['ollama', OllamaProvider::class],
            'gemini' => ['gemini', GeminiProvider::class],
            'case-insensitive' => ['OpenAI', OpenAIProvider::class],
        ];
    }

    #[DataProvider('builtInProviders')]
    public function testCreatesBuiltInProviders(string $name, string $class): void
    {
        $ai = (new AIFactory())->getAI($name, ['api_key' => 'k']);

        $this->assertInstanceOf(AI::class, $ai);
        $this->assertInstanceOf($class, $ai->getProvider());
    }

    public function testUnknownProviderThrowsWithErrorCode(): void
    {
        try {
            (new AIFactory())->getAI('nope');
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame('INVALID_PROVIDER', $e->getProviderErrorCode());
            $this->assertStringContainsString('openai, anthropic, ollama, gemini', $e->getMessage());
        }
    }

    public function testRegisterProviderAndAvailability(): void
    {
        $factory = new AIFactory();

        $this->assertFalse($factory->isProviderAvailable('custom'));

        $this->assertSame($factory, $factory->registerProvider('Custom', TestProvider::class));

        $this->assertTrue($factory->isProviderAvailable('custom'));
        $this->assertSame(['openai', 'anthropic', 'ollama', 'gemini', 'custom'], $factory->getAvailableProviders());
        $this->assertInstanceOf(TestProvider::class, $factory->getAI('CUSTOM')->getProvider());
    }

    public function testProvidersCanBePassedToConstructorAndReplaceBuiltIns(): void
    {
        $factory = new AIFactory(null, ['openai' => TestProvider::class]);

        $this->assertInstanceOf(TestProvider::class, $factory->getAI('openai')->getProvider());
    }

    public function testAcceptsProviderClassName(): void
    {
        $provider = (new AIFactory())->getAI(TestProvider::class, ['model' => 'm'])->getProvider();

        $this->assertInstanceOf(TestProvider::class, $provider);
        $this->assertSame(['model' => 'm'], $provider->options());
    }

    public function testRejectsClassesThatAreNotProviders(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AIFactory())->registerProvider('bad', \stdClass::class);
    }

    public function testRejectsNonProviderClassNameInGetAI(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AIFactory())->getAI(\stdClass::class);
    }

    public function testProviderConstructorFailureIsWrapped(): void
    {
        $factory = (new AIFactory())->registerProvider('broken', BrokenProvider::class);

        try {
            $factory->getAI('broken');
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame('PROVIDER_CREATION_FAILED', $e->getProviderErrorCode());
            $this->assertStringContainsString('boom', $e->getMessage());
        }
    }

    public function testHttpFactoryIsPassedToProviders(): void
    {
        $factory = new AIFactory($this->routingHttpFactory([
            'POST https://api.anthropic.com/v1/messages' => ['content' => [['type' => 'text', 'text' => 'hi']], 'stop_reason' => 'end_turn'],
        ]));

        $response = $factory->getAI('anthropic', ['api_key' => 'k'])->chat('Hello');

        $this->assertSame('hi', $response->getContent());
        $this->assertCount(1, $this->requests);
    }
}
