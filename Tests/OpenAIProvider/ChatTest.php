<?php

use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use Joomla\AI\Provider\OpenAIProvider;
use PHPUnit\Framework\TestCase;

class ChatTest extends TestCase
{
    public function testSimpleChatCompletion()
    {
        echo "Test 1: Test chat method with a simple prompt\n";

        $fakeChatResponseBody = json_encode([
            'id'      => 'chatcmpl-test',
            'object'  => 'chat.completion',
            'created' => time(),
            'model'   => 'gpt-4o-mini',
            'usage'   => ['prompt_tokens' => 5, 'completion_tokens' => 7, 'total_tokens' => 12],
            'choices' => [
                [
                    'index'         => 0,
                    'message'       => ['role' => 'assistant', 'content' => 'All good here!'],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $httpFactoryMock  = $this->createMock(HttpFactory::class);
        $httpClientMock   = $this->createMock(Http::class);

        $httpFactoryMock->method('getHttp')->willReturn($httpClientMock);

        $response = new HttpResponse('php://memory', 200, ['Content-Type' => 'application/json']);
        $stream   = $response->getBody();
        $stream->write($fakeChatResponseBody);

        $httpClientMock->method('post')->willReturn($response);

        $provider = new OpenAIProviderNoModeration(['api_key' => 'test-api-key'], $httpFactoryMock);

        $response = $provider->chat('Hello! How are you?', ['model' => 'gpt-4o-mini']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('All good here!', $response->getContent());
        $metadata = $response->getMetadata();
        $this->assertArrayHasKey('model', $metadata);
        $this->assertArrayHasKey('usage', $metadata);
    }
}

class OpenAIProviderNoModeration extends OpenAIProvider
{
    protected function moderateInput($input, array $options = []): bool
    {
        return false;
    }
}