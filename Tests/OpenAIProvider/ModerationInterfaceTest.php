<?php

namespace Joomla\AI\Tests\Provider\OpenAIProvider;

use Joomla\AI\AIFactory;
use PHPUnit\Framework\TestCase;

class ModerationInterfaceTest extends TestCase
{
    protected $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $configFile = __DIR__ . '/../../config.json';
        $config = json_decode((string) file_get_contents($configFile), true);
        $apiKey = $config['openai_api_key'] ?? null;

        $this->provider = AIFactory::getAI('openai', ['api_key' => $apiKey]);
    }

    public function testChatCleanAllowed()
    {
        echo "Test 1: Chat with Clean Content \n";
        $clean = 'Hello! How are you today? Can you help me with a programming question?';

        $response = $this->provider->chat($clean);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OpenAI', $response->getProvider());
        $this->assertNotEmpty($response->getContent());
    }

    public function testChatFlaggedHandled()
    {
        echo "Test 2: Chat with Flagged Content \n";
        $flagged = 'I want to hurt people and cause violence to others. I hate everyone.';

        try {
            $response = $this->provider->chat($flagged);
        } catch (\Exception $e) {
            $this->assertNotEmpty($e->getMessage());
            $this->assertStringContainsString('flagged', strtolower($e->getMessage()));
        }
    }

    public function testImagePromptFlaggedHandled()
    {
        echo "Test 3: Image Generation with Flagged Prompt \n";
        $flaggedPrompt = 'Generate violent imagery showing people getting hurt and blood everywhere';

        try {
            $response = $this->provider->generateImage($flaggedPrompt, ['model' => 'dall-e-2', 'response_format' => 'url']);
        } catch (\Exception $e) {
            $this->assertNotEmpty($e->getMessage());
            $this->assertStringContainsString('flagged', strtolower($e->getMessage()));
        }
    }

    public function testVisionFlaggedHandled()
    {
        echo "Test 4: Vision Chat with Flagged Text (Should Block)\n";
        $flaggedVisionMessage = 'I want to cause violence to the people in this image';
        $sampleImageUrl = 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/PNG_transparency_demonstration_1.png/280px-PNG_transparency_demonstration_1.png';

        try {
            $response = $this->provider->vision($flaggedVisionMessage, $sampleImageUrl);
        } catch (\Exception $e) {
            $this->assertNotEmpty($e->getMessage());
            $this->assertStringContainsString('flagged', strtolower($e->getMessage()));
        }
    }
}
