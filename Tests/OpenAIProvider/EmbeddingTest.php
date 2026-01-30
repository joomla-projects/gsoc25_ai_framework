<?php

namespace Joomla\AI\Tests\OpenAIProvider;

use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Provider\OpenAIProvider;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

class EmbeddingTest extends TestCase
{
    public function testCreateEmbeddingsReturnsVectorSuccessfully()
    {
        // Test 1: Test createEmbeddings returns float embedding successfully

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-embed-success',
            'model' => 'omni-moderation-latest',
            'results' => [['flagged' => false]],
        ]);

        $embeddingResponse = $this->createJsonResponse([
            'object' => 'list',
            'model' => 'text-embedding-3-small',
            'data' => [
                [
                    'object' => 'embedding',
                    'index' => 0,
                    'embedding' => [0.12, -0.34, 0.56],
                ],
            ],
            'usage' => ['prompt_tokens' => 8, 'total_tokens' => 8],
        ]);

        $provider = $this->createProviderWithResponses($moderationResponse, $embeddingResponse);

        $response = $provider->createEmbeddings('Embed this text', 'text-embedding-3-small', [
            'encoding_format' => 'float',
            'dimensions' => 3,
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $decodedContent = json_decode($response->getContent(), true);
        $this->assertSame([0.12, -0.34, 0.56], $decodedContent);

        $metadata = $response->getMetadata();
        $this->assertSame('text-embedding-3-small', $metadata['model']);
        $this->assertSame([['embedding' => [0.12, -0.34, 0.56], 'index' => 0, 'object' => 'embedding']], $metadata['raw_embeddings']);
        $this->assertEquals(['prompt_tokens' => 8, 'total_tokens' => 8], $metadata['usage']);
    }

    public function testCreateEmbeddingsReturnsMultipleBase64Vectors()
    {
        // Test 2: Test createEmbeddings returns multiple base64 vectors

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-embed-base64',
            'model' => 'omni-moderation-latest',
            'results' => [['flagged' => false]],
        ]);

        $embeddingResponse = $this->createJsonResponse([
            'object' => 'list',
            'model' => 'text-embedding-3-large',
            'data' => [
                [
                    'object' => 'embedding',
                    'index' => 0,
                    'embedding' => base64_encode('vector-one'),
                ],
                [
                    'object' => 'embedding',
                    'index' => 1,
                    'embedding' => base64_encode('vector-two'),
                ],
            ],
        ]);

        $provider = $this->createProviderWithResponses($moderationResponse, $embeddingResponse);

        $response = $provider->createEmbeddings(['First input', 'Second input'], 'text-embedding-3-large', [
            'encoding_format' => 'base64',
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $decodedContent = json_decode($response->getContent(), true);
        $this->assertCount(2, $decodedContent);
        $this->assertSame(base64_encode('vector-one'), $decodedContent[0]['embedding']);
        $this->assertSame(base64_encode('vector-two'), $decodedContent[1]['embedding']);

        $metadata = $response->getMetadata();
        $this->assertSame('text-embedding-3-large', $metadata['model']);
        $this->assertSame(2, $metadata['embedding_count']);
        $this->assertSame('base64', $metadata['encoding_format']);
        $this->assertSame('array', $metadata['input_type']);
    }

    public function testCreateEmbeddingsThrowsInvalidModel()
    {
        // Test 3: Test createEmbeddings raises InvalidArgumentException on invalid model

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-embed-invalid-model',
            'model' => 'omni-moderation-latest',
            'results' => [['flagged' => false]],
        ]);

        $provider = $this->createProviderWithResponses($moderationResponse);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('embedding');

        $provider->createEmbeddings('test', 'not-a-model');
    }

    public function testCreateEmbeddingsThrowsInvalidEncodingFormat()
    {
        // Test 4: Test createEmbeddings raises InvalidArgumentException on invalid encoding format

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-embed-invalid-encoding',
            'model' => 'omni-moderation-latest',
            'results' => [['flagged' => false]],
        ]);

        $provider = $this->createProviderWithResponses($moderationResponse);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Encoding format must be 'float' or 'base64'");

        $provider->createEmbeddings('unsupported encoding format', 'text-embedding-3-small', [
            'encoding_format' => 'hex',
        ]);
    }

    public function testCreateEmbeddingsRaisesProviderException()
    {
        // Test 5: Test createEmbeddings raises ProviderException on provider error

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-embed-error',
            'model' => 'omni-moderation-latest',
            'results' => [['flagged' => false]],
        ]);

        $errorResponse = $this->createJsonResponse([
            'error' => [
                'message' => 'Embeddings API error',
                'type' => 'server_error',
            ],
        ], 500);

        $provider = $this->createProviderWithResponses($moderationResponse, $errorResponse);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Embeddings API error');

        $provider->createEmbeddings('trigger error', 'text-embedding-3-small');
    }

    public function testCreateEmbeddingsRaisesUnserializableResponseException()
    {
        // Test 6: Test createEmbeddings raises UnserializableResponseException on invalid JSON

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-embed-invalid-json',
            'model' => 'omni-moderation-latest',
            'results' => [['flagged' => false]],
        ]);

        $invalidResponse = new HttpResponse('php://memory', 200, ['Content-Type' => 'application/json']);
        $invalidResponse->getBody()->write('{ not valid json');

        $provider = $this->createProviderWithResponses($moderationResponse, $invalidResponse);

        $this->expectException(UnserializableResponseException::class);
        $this->expectExceptionMessage('Syntax error');

        $provider->createEmbeddings('bad json', 'text-embedding-3-small');
    }

    public function testCreateEmbeddingsThrowsWhenModerationFlagsContent()
    {
        // Test 7: Test createEmbeddings raises Exception when moderation flags content

        $flaggedModeration = $this->createJsonResponse([
            'id' => 'modr-embed-flagged',
            'model' => 'omni-moderation-latest',
            'results' => [['flagged' => true]],
        ]);

        $provider = $this->createProviderWithResponses($flaggedModeration);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Content flagged by moderation system and blocked.');

        $provider->createEmbeddings('forbidden text', 'text-embedding-3-small');
    }

    private function createProviderWithResponses(HttpResponse ...$responses): OpenAIProvider
    {
        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock = $this->createMock(Http::class);

        $httpFactoryMock->method('getHttp')->willReturn($httpClientMock);

        if (!empty($responses)) {
            $httpClientMock->method('post')->willReturnOnConsecutiveCalls(...$responses);
        }

        return new OpenAIProvider(['api_key' => 'test-api-key'], $httpFactoryMock);
    }

    private function createJsonResponse(array $payload, int $status = 200): HttpResponse
    {
        $response = new HttpResponse('php://memory', $status, ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($payload));

        return $response;
    }
}
