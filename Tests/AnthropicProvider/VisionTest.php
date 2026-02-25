<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Tests\AnthropicProvider;

use Joomla\AI\Exception\AuthenticationException;
use Joomla\AI\Exception\ProviderException;
use Joomla\AI\Provider\AnthropicProvider;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnthropicProvider::vision()
 *
 * @since  __DEPLOY_VERSION__
 */
class VisionTest extends TestCase
{
    /**
     * Tests a successful vision response when a base64 data-URI image is provided.
     */
    public function testVisionWithBase64DataUriReturnsSuccessfulResponse(): void
    {
        // Create a minimal 1×1 white PNG encoded as a data URI
        $fakeBase64 = base64_encode('fake-png-bytes');
        $dataUri    = 'data:image/png;base64,' . $fakeBase64;

        $apiResponse = $this->createJsonResponse([
            'id'            => 'msg_vision_1',
            'type'          => 'message',
            'role'          => 'assistant',
            'model'         => 'claude-3-haiku-20240307',
            'content'       => [['type' => 'text', 'text' => 'I see a white square.']],
            'usage'         => ['input_tokens' => 200, 'output_tokens' => 12],
            'stop_reason'   => 'end_turn',
            'stop_sequence' => null,
        ]);

        $provider = $this->createProviderWithResponses([$apiResponse]);
        $response = $provider->vision('What is in this image?', $dataUri);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('I see a white square.', $response->getContent());
        $this->assertSame('Anthropic', $response->getProvider());

        $metadata = $response->getMetadata();
        $this->assertSame('claude-3-haiku-20240307', $metadata['model']);
        $this->assertSame('end_turn', $metadata['stop_reason']);
    }

    /**
     * Tests a successful vision response when a local image file path is provided.
     * The file is created temporarily and cleaned up after the test.
     */
    public function testVisionWithFilePathReturnsSuccessfulResponse(): void
    {
        // Write a temporary fake image file
        $tempFile = tempnam(sys_get_temp_dir(), 'ai_vision_test_') . '.png';
        file_put_contents($tempFile, "\x89PNG\r\n\x1a\nfake-png-data");

        try {
            $apiResponse = $this->createJsonResponse([
                'id'            => 'msg_vision_file',
                'type'          => 'message',
                'role'          => 'assistant',
                'model'         => 'claude-3-haiku-20240307',
                'content'       => [['type' => 'text', 'text' => 'A PNG image.']],
                'usage'         => ['input_tokens' => 180, 'output_tokens' => 8],
                'stop_reason'   => 'end_turn',
                'stop_sequence' => null,
            ]);

            $provider = $this->createProviderWithResponses([$apiResponse]);
            $response = $provider->vision('Describe this image.', $tempFile);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('A PNG image.', $response->getContent());
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Tests that a ProviderException is thrown when the Anthropic API returns an error body.
     */
    public function testVisionThrowsProviderExceptionWhenApiReturnsError(): void
    {
        $fakeBase64 = base64_encode('fake-png-bytes');
        $dataUri    = 'data:image/png;base64,' . $fakeBase64;

        $errorResponse = $this->createJsonResponse([
            'error' => [
                'type'    => 'invalid_request_error',
                'message' => 'Image data could not be decoded.',
            ],
        ]);

        $provider = $this->createProviderWithResponses([$errorResponse]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Image data could not be decoded.');

        $provider->vision('What is this?', $dataUri);
    }

    /**
     * Tests that a ProviderException is thrown on an HTTP 500 response.
     */
    public function testVisionThrowsProviderExceptionOnHttpServerError(): void
    {
        $fakeBase64 = base64_encode('fake-png-bytes');
        $dataUri    = 'data:image/png;base64,' . $fakeBase64;

        $serverError = $this->createJsonResponse(
            ['message' => 'Internal server error'],
            500
        );

        $provider = $this->createProviderWithResponses([$serverError]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Internal server error');

        $provider->vision('Describe this.', $dataUri);
    }

    /**
     * Tests that an AuthenticationException is thrown when no API key is configured.
     */
    public function testVisionThrowsAuthenticationExceptionWhenApiKeyMissing(): void
    {
        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $provider        = new AnthropicProvider([], $httpFactoryMock);

        $fakeBase64 = base64_encode('fake-png-bytes');
        $dataUri    = 'data:image/png;base64,' . $fakeBase64;

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage(
            'Anthropic API key not configured. Set ANTHROPIC_API_KEY environment variable or provide api_key option.'
        );

        $provider->vision('What is this?', $dataUri);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createProviderWithResponses(array $responses): AnthropicProvider
    {
        $httpFactoryMock = $this->createMock(HttpFactory::class);
        $httpClientMock  = $this->createMock(Http::class);

        $httpFactoryMock->method('getHttp')->with([])->willReturn($httpClientMock);
        $httpClientMock->method('post')->willReturnOnConsecutiveCalls(...$responses);

        return new AnthropicProvider(['api_key' => 'test-api-key'], $httpFactoryMock);
    }

    private function createJsonResponse(array $payload, int $status = 200): HttpResponse
    {
        $response = new HttpResponse('php://memory', $status, ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($payload));

        return $response;
    }
}
