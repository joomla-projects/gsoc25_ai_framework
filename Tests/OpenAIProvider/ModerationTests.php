<?php

use Joomla\AI\Exception\InvalidArgumentException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Exception\UnserializableResponseException;
use Joomla\AI\Provider\OpenAIProvider;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

class OpenAIModerationTests extends TestCase
{
	public function testModerateReturnsSafeResult()
	{
		echo "Test 1: Test moderate method returns non-flagged result successfully\n";

		$moderationResponse = $this->createJsonResponse([
			'id' => 'modr-safe',
			'model' => 'omni-moderation-latest',
			'results' => [
				[
					'flagged' => false,
					'categories' => ['hate' => false],
				],
			],
		]);

		$provider = $this->createProviderWithResponses($moderationResponse);

		$result = $provider->moderate('Hello world');
		$this->assertSame('modr-safe', $result['id']);
		$this->assertFalse($provider->isContentFlagged($result));
	}

	public function testModerateDetectsFlaggedContent()
	{
		echo "Test 2: Test moderate method detects flagged content\n";

		$flaggedResponse = $this->createJsonResponse([
			'id' => 'modr-flagged',
			'model' => 'omni-moderation-latest',
			'results' => [
				[
					'flagged' => true,
					'categories' => ['self-harm' => true],
				],
			],
		]);

		$provider = $this->createProviderWithResponses($flaggedResponse);

		$result = $provider->moderate('Content that should be blocked');
		$this->assertTrue($provider->isContentFlagged($result));
	}

	public function testModerateThrowsInvalidModel()
	{
		echo "Test 3: Test moderate method raises InvalidArgumentException on invalid model\n";

		$provider = $this->createProviderWithResponses();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('moderation');

		$provider->moderate('Invalid model', ['model' => 'bad-model']);
	}

	public function testModerateRaisesProviderExceptionOnError()
	{
		echo "Test 4: Test moderate method raises ProviderException on server error\n";

		$errorResponse = $this->createJsonResponse([
			'error' => [
				'message' => 'Server error processing moderation',
				'type' => 'server_error',
			],
		], 500);

		$provider = $this->createProviderWithResponses($errorResponse);

		$this->expectException(ProviderException::class);
		$this->expectExceptionMessage('Server error processing moderation');

		$provider->moderate('Trigger provider error');
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
