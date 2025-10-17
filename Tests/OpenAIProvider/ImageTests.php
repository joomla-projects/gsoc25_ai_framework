<?php

use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Provider\OpenAIProvider;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase
{
	public function testGenerateImageReturnsBase64Successfully()
	{
		echo "Test 1: Test generateImage method returns base64 content successfully\n";

		$moderationResponse = $this->createJsonResponse([
			'id' => 'modr-image-test',
			'model' => 'omni-moderation-latest',
			'results' => [['flagged' => false]],
		]);

		$base64Image = base64_encode('fake-image-data');

		$imageResponse = $this->createJsonResponse([
			'created' => 1234567890,
			'model' => 'dall-e-2',
			'usage' => ['prompt_tokens' => 12, 'total_tokens' => 18],
			'data' => [
				['b64_json' => $base64Image],
			],
		]);

		$provider = $this->createProviderWithResponses($moderationResponse, $imageResponse);

		$response = $provider->generateImage('A scenic mountain range at sunrise', [
			'model' => 'dall-e-2',
			'response_format' => 'b64_json',
		]);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame($base64Image, $response->getContent());

		$metadata = $response->getMetadata();
		$this->assertSame('dall-e-2', $metadata['model']);
		$this->assertSame('b64_json', $metadata['response_format']);
		$this->assertSame(1, $metadata['image_count']);
		$this->assertSame('base64_png', $metadata['format']);
		$this->assertSame($base64Image, $metadata['images'][0]['b64_json']);
		$this->assertEquals(['prompt_tokens' => 12, 'total_tokens' => 18], $metadata['usage']);
	}

	public function testGenerateImageReturnsUrlSuccessfully()
	{
		echo "Test 2: Test generateImage method returns URL content successfully\n";

		$moderationResponse = $this->createJsonResponse([
			'id' => 'modr-image-url',
			'model' => 'omni-moderation-latest',
			'results' => [['flagged' => false]],
		]);

		$url = 'https://example.com/generated-image.png';

		$imageResponse = $this->createJsonResponse([
			'created' => 987654321,
			'model' => 'dall-e-3',
			'data' => [
				[
					'url' => $url,
					'revised_prompt' => 'A vivid sunrise over mountains',
				],
			],
		]);

		$provider = $this->createProviderWithResponses($moderationResponse, $imageResponse);

		$response = $provider->generateImage('Mountains at dawn', [
			'model' => 'dall-e-3',
			'response_format' => 'url',
		]);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame($url, $response->getContent());

		$metadata = $response->getMetadata();
		$this->assertSame('dall-e-3', $metadata['model']);
		$this->assertSame('url', $metadata['response_format']);
		$this->assertSame(1, $metadata['image_count']);
		$this->assertSame($url, $metadata['images'][0]['url']);
		$this->assertSame('URLs are valid for 60 minutes', $metadata['url_expires']);
	}

	public function testGenerateImageThrowsInvalidModel()
	{
		echo "Test 3: Test generateImage method raises InvalidArgumentException on invalid model\n";

		$moderationResponse = $this->createJsonResponse([
			'id' => 'modr-image-invalid-model',
			'model' => 'omni-moderation-latest',
			'results' => [['flagged' => false]],
		]);

		$provider = $this->createProviderWithResponses($moderationResponse);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('image generation');

		$provider->generateImage('A futuristic city skyline', [
			'model' => 'invalid-model',
		]);
	}

	public function testGenerateImageRaisesProviderException()
	{
		echo "Test 4: Test generateImage method raises ProviderException on provider error\n";

		$moderationResponse = $this->createJsonResponse([
			'id' => 'modr-image-error',
			'model' => 'omni-moderation-latest',
			'results' => [['flagged' => false]],
		]);

		$errorResponse = $this->createJsonResponse([
			'error' => [
				'message' => 'Invalid request payload',
				'type' => 'invalid_request_error',
			],
		], 400);

		$provider = $this->createProviderWithResponses($moderationResponse, $errorResponse);

		$this->expectException(ProviderException::class);
		$this->expectExceptionMessage('Invalid request payload');

		$provider->generateImage('Abstract shapes', [
			'model' => 'dall-e-2',
		]);
	}

	public function testGenerateImageRaisesUnserializableResponseException()
	{
		echo "Test 5: Test generateImage method raises UnserializableResponseException on invalid JSON\n";

		$moderationResponse = $this->createJsonResponse([
			'id' => 'modr-image-invalid-json',
			'model' => 'omni-moderation-latest',
			'results' => [['flagged' => false]],
		]);

		$invalidResponse = new HttpResponse('php://memory', 200, ['Content-Type' => 'application/json']);
		$invalidResponse->getBody()->write('{ not valid json');

		$provider = $this->createProviderWithResponses($moderationResponse, $invalidResponse);

		$this->expectException(UnserializableResponseException::class);
		$this->expectExceptionMessage('Syntax error');

		$provider->generateImage('A glowing forest', [
			'model' => 'dall-e-2',
		]);
	}

	public function testCreateImageVariationReturnsBase64Successfully()
	{
		echo "Test 6: Test createImageVariation method returns base64 content successfully\n";

		$imagePath = $this->createTempPngImage();

		try {
			$variationBase64 = base64_encode('variation-data');

			$variationResponse = $this->createJsonResponse([
				'created' => 1357913579,
				'model' => 'dall-e-2',
				'data' => [
					['b64_json' => $variationBase64],
				],
			]);

			$provider = $this->createProviderWithResponses($variationResponse);

			$response = $provider->createImageVariation($imagePath, [
				'model' => 'dall-e-2',
				'response_format' => 'b64_json',
			]);

			$this->assertSame(200, $response->getStatusCode());
			$this->assertSame($variationBase64, $response->getContent());

			$metadata = $response->getMetadata();
			$this->assertSame('b64_json', $metadata['response_format']);
			$this->assertSame(1, $metadata['image_count']);
			$this->assertSame($variationBase64, $metadata['images'][0]['b64_json']);
		} finally {
			@unlink($imagePath);
		}
	}

	public function testCreateImageVariationThrowsFileNotFound()
	{
		echo "Test 7: Test createImageVariation method raises InvalidArgumentException when file missing\n";

		$provider = $this->createProviderWithResponses();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('not found');

		$provider->createImageVariation('nonexistent-image.png');
	}

	public function testEditImageReturnsUrlSuccessfully()
	{
		echo "Test 8: Test editImage method returns URL content successfully\n";

		$imagePath = $this->createTempPngImage();
		$maskPath = $this->createTempPngImage('mask-test-');

		try {
			$moderationResponse = $this->createJsonResponse([
				'id' => 'modr-image-edit',
				'model' => 'omni-moderation-latest',
				'results' => [['flagged' => false]],
			]);

			$editUrl = 'https://example.com/edited-image.png';

			$editResponse = $this->createJsonResponse([
				'created' => 1122334455,
				'model' => 'dall-e-2',
				'usage' => ['prompt_tokens' => 20, 'total_tokens' => 30],
				'data' => [
					['url' => $editUrl],
				],
			]);

			$provider = $this->createProviderWithResponses($moderationResponse, $editResponse);

			$response = $provider->editImage($imagePath, 'Add a bright sun to the top corner', [
				'model' => 'dall-e-2',
				'mask' => $maskPath,
				'response_format' => 'url',
			]);

			$this->assertSame(200, $response->getStatusCode());
			$this->assertSame($editUrl, $response->getContent());

			$metadata = $response->getMetadata();
			$this->assertSame('url', $metadata['response_format']);
			$this->assertSame(1, $metadata['image_count']);
			$this->assertSame($editUrl, $metadata['images'][0]['url']);
			$this->assertSame(['prompt_tokens' => 20, 'total_tokens' => 30], $metadata['usage']);
		} finally {
			@unlink($imagePath);
			@unlink($maskPath);
		}
	}

	public function testEditImageThrowsInvalidMaskFormat()
	{
		echo "Test 9: Test editImage method raises InvalidArgumentException on invalid mask format\n";

		$imagePath = $this->createTempPngImage();
		$maskPath = $this->createTempFileWithExtension('jpg');

		try {
			$moderationResponse = $this->createJsonResponse([
				'id' => 'modr-image-mask',
				'model' => 'omni-moderation-latest',
				'results' => [['flagged' => false]],
			]);

			$provider = $this->createProviderWithResponses($moderationResponse);

			$this->expectException(InvalidArgumentException::class);
			$this->expectExceptionMessage('Supported formats: png');

			$provider->editImage($imagePath, 'Remove the background', [
				'model' => 'dall-e-2',
				'mask' => $maskPath,
			]);
		} finally {
			@unlink($imagePath);
			@unlink($maskPath);
		}
	}

	public function testEditImageThrowsInvalidResponseFormatForGptImage1()
	{
		echo "Test 10: Test editImage method raises InvalidArgumentException on unsupported response format\n";

		$imagePath = $this->createTempPngImage();

		try {
			$moderationResponse = $this->createJsonResponse([
				'id' => 'modr-image-gpt',
				'model' => 'omni-moderation-latest',
				'results' => [['flagged' => false]],
			]);

			$provider = $this->createProviderWithResponses($moderationResponse);

			$this->expectException(InvalidArgumentException::class);
			$this->expectExceptionMessage('response_format is not supported for gpt-image-1');

			$provider->editImage($imagePath, 'Convert to blueprint style', [
				'model' => 'gpt-image-1',
				'response_format' => 'url',
			]);
		} finally {
			@unlink($imagePath);
		}
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

	private function createTempPngImage(string $prefix = 'image-test-'): string
	{
		$path = uniqid($prefix, true) . '.png';
		$pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=');
		file_put_contents($path, $pngData);

		return $path;
	}

	private function createTempFileWithExtension(string $extension): string
	{
		$path = uniqid('image-test-', true) . '.' . $extension;
		file_put_contents($path, 'placeholder');

		return $path;
	}

	private function createJsonResponse(array $payload, int $status = 200): HttpResponse
	{
		$response = new HttpResponse('php://memory', $status, ['Content-Type' => 'application/json']);
		$response->getBody()->write(json_encode($payload));

		return $response;
	}
}
