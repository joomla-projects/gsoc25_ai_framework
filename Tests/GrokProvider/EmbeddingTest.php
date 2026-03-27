<?php

namespace Joomla\AI\Tests\GrokProvider;

use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Provider\GrokProvider;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

class EmbeddingTest extends TestCase
{
    public function testCreateEmbeddingsReturnsVectorSuccessfully(): void
    {
        // Test 1: Test createEmbeddings returns float embedding successfully

        $embeddingResponse = $this->createJsonResponse([
            'object' => 'list',
            'model' => 'v1',
            'data' => [
                [
                    'object' => 'embedding',
                    'index' => 0,
                    'embedding' => [0.12, -0.34, 0.56],
                ],
            ],
            'usage' => ['prompt_tokens' => 8, 'total_tokens' => 8],
        ]);

        $provider = $this->createProviderWithResponses($embeddingResponse);

        $response = $provider->createEmbeddings('Embed this text', 'v1');

        $this->assertSame(200, $response->getStatusCode());

        $decodedContent = json_decode($response->getContent(), true);
        $this->assertSame([0.12, -0.34, 0.56], $decodedContent);

        $metadata = $response->getMetadata();
        $this->assertSame('v1', $metadata['model']);
        $this->assertSame(1, $metadata['embedding_count']);
        $this->assertSame('float', $metadata['encoding_format']);
        $this->assertSame('string', $metadata['input_type']);
        $this->assertEquals(['prompt_tokens' => 8, 'total_tokens' => 8], $metadata['usage']);
    }

    public function testCreateEmbeddingsReturnsMultipleVectors(): void
    {
        // Test 2: Test createEmbeddings returns multiple embedding vectors for array input

        $embeddingResponse = $this->createJsonResponse([
            'object' => 'list',
            'model' => 'v1',
            'data' => [
                [
                    'object' => 'embedding',
                    'index' => 0,
                    'embedding' => [0.1, 0.2, 0.3],
                ],
                [
                    'object' => 'embedding',
                    'index' => 1,
                    'embedding' => [0.4, 0.5, 0.6],
                ],
            ],
            'usage' => ['prompt_tokens' => 12, 'total_tokens' => 12],
        ]);

        $provider = $this->createProviderWithResponses($embeddingResponse);

        $response = $provider->createEmbeddings(['First text', 'Second text'], 'v1');

        $this->assertSame(200, $response->getStatusCode());

        $decodedContent = json_decode($response->getContent(), true);
        $this->assertCount(2, $decodedContent);

        $metadata = $response->getMetadata();
        $this->assertSame(2, $metadata['embedding_count']);
        $this->assertSame('array', $metadata['input_type']);
    }

    public function testCreateEmbeddingsThrowsOnInvalidEncodingFormat(): void
    {
        // Test 3: Test createEmbeddings raises InvalidArgumentException on invalid encoding format

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $provider = new GrokProvider(['api_key' => 'test-api-key'], $httpFactoryMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Encoding format must be 'float' or 'base64'");

        $provider->createEmbeddings('test', 'v1', ['encoding_format' => 'hex']);
    }

    public function testCreateEmbeddingsThrowsProviderExceptionOnError(): void
    {
        // Test 4: Test createEmbeddings raises ProviderException on server error

        $errorResponse = $this->createJsonResponse([
            'error' => [
                'message' => 'Embeddings API error',
                'type' => 'server_error',
            ],
        ], 500);

        $provider = $this->createProviderWithResponses($errorResponse);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Embeddings API error');

        $provider->createEmbeddings('trigger error', 'v1');
    }

    public function testCreateEmbeddingsThrowsUnserializableResponseException(): void
    {
        // Test 5: Test createEmbeddings raises UnserializableResponseException on invalid JSON

        $invalidResponse = new HttpResponse('php://memory', 200, ['Content-Type' => 'application/json']);
        $invalidResponse->getBody()->write('{ not valid json');

        $provider = $this->createProviderWithResponses($invalidResponse);

        $this->expectException(UnserializableResponseException::class);
        $this->expectExceptionMessage('Syntax error');

        $provider->createEmbeddings('bad json', 'v1');
    }

    private function createProviderWithResponses(HttpResponse ...$responses): GrokProvider
    {
        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock = $this->createMock(Http::class);

        $httpFactoryMock->method('getHttp')->willReturn($httpClientMock);

        if (!empty($responses)) {
            $httpClientMock->method('post')->willReturnOnConsecutiveCalls(...$responses);
        }

        return new GrokProvider(['api_key' => 'test-api-key'], $httpFactoryMock);
    }

    private function createJsonResponse(array $payload, int $status = 200): HttpResponse
    {
        $response = new HttpResponse('php://memory', $status, ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($payload));

        return $response;
    }
}
