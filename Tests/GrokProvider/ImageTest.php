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

class ImageTest extends TestCase
{
    public function testGenerateImageReturnsUrlSuccessfully(): void
    {
        // Test 1: Test generateImage method returns URL content successfully

        $url = 'https://example.com/generated-image.png';

        $imageResponse = $this->createJsonResponse([
            'created' => 1234567890,
            'data' => [
                [
                    'url' => $url,
                    'revised_prompt' => 'A vivid mountain landscape',
                ],
            ],
        ]);

        $provider = $this->createProviderWithResponses($imageResponse);

        $response = $provider->generateImage('A mountain landscape', [
            'model' => 'grok-2-image-latest',
            'response_format' => 'url',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($url, $response->getContent());

        $metadata = $response->getMetadata();
        $this->assertSame('grok-2-image-latest', $metadata['model']);
        $this->assertSame('url', $metadata['response_format']);
        $this->assertSame(1, $metadata['image_count']);
        $this->assertSame($url, $metadata['images'][0]['url']);
        $this->assertSame('A vivid mountain landscape', $metadata['images'][0]['revised_prompt']);
    }

    public function testGenerateImageReturnsBase64Successfully(): void
    {
        // Test 2: Test generateImage method returns base64 content successfully

        $base64Image = base64_encode('fake-image-data');

        $imageResponse = $this->createJsonResponse([
            'created' => 1234567890,
            'model' => 'grok-2-image-latest',
            'data' => [
                ['b64_json' => $base64Image],
            ],
        ]);

        $provider = $this->createProviderWithResponses($imageResponse);

        $response = $provider->generateImage('A scenic sunset', [
            'model' => 'grok-2-image-latest',
            'response_format' => 'b64_json',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($base64Image, $response->getContent());

        $metadata = $response->getMetadata();
        $this->assertSame('b64_json', $metadata['response_format']);
        $this->assertSame(1, $metadata['image_count']);
        $this->assertSame($base64Image, $metadata['images'][0]['b64_json']);
    }

    public function testGenerateImageReturnsMultipleImages(): void
    {
        // Test 3: Test generateImage method returns multiple images when n > 1

        $url1 = 'https://example.com/image1.png';
        $url2 = 'https://example.com/image2.png';

        $imageResponse = $this->createJsonResponse([
            'created' => 1234567890,
            'data' => [
                ['url' => $url1],
                ['url' => $url2],
            ],
        ]);

        $provider = $this->createProviderWithResponses($imageResponse);

        $response = $provider->generateImage('Two cats', [
            'model' => 'grok-2-image-latest',
            'n' => 2,
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $metadata = $response->getMetadata();
        $this->assertSame(2, $metadata['image_count']);
        $this->assertSame($url1, $metadata['images'][0]['url']);
        $this->assertSame($url2, $metadata['images'][1]['url']);
    }

    public function testGenerateImageThrowsOnInvalidN(): void
    {
        // Test 4: Test generateImage method raises InvalidArgumentException when n is out of range

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $provider = new GrokProvider(['api_key' => 'test-api-key'], $httpFactoryMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be between 1 and 10');

        $provider->generateImage('A cat', ['n' => 15]);
    }

    public function testGenerateImageThrowsOnInvalidResponseFormat(): void
    {
        // Test 5: Test generateImage method raises InvalidArgumentException on invalid response format

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $provider = new GrokProvider(['api_key' => 'test-api-key'], $httpFactoryMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Response format must be either "url" or "b64_json"');

        $provider->generateImage('A dog', ['response_format' => 'invalid']);
    }

    public function testGenerateImageThrowsProviderExceptionOnError(): void
    {
        // Test 6: Test generateImage method raises ProviderException on API error

        $errorResponse = $this->createJsonResponse([
            'error' => [
                'message' => 'Invalid request',
                'type' => 'invalid_request_error',
            ],
        ], 400);

        $provider = $this->createProviderWithResponses($errorResponse);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Invalid request');

        $provider->generateImage('Bad request');
    }

    public function testEditImageThrowsProviderException(): void
    {
        // Test 7: Test editImage method raises ProviderException as Grok does not support image editing

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $provider = new GrokProvider(['api_key' => 'test-api-key'], $httpFactoryMock);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Image editing is not supported');

        $provider->editImage('image.png', 'Edit this');
    }

    public function testCreateImageVariationThrowsProviderException(): void
    {
        // Test 8: Test createImageVariation method raises ProviderException as Grok does not support image variations

        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $provider = new GrokProvider(['api_key' => 'test-api-key'], $httpFactoryMock);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Image variations are not supported');

        $provider->createImageVariation('image.png');
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
