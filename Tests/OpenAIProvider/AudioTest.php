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

class AudioTest extends TestCase
{
    public function testSpeechGeneratesAudioSuccessfully()
    {
        // Test 1: Test speech method for successful completion

        $fakeAudioContent = "FAKEAUDIOCONTENT";

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-audio-test',
            'model' => 'omni-moderation-latest',
            'results' => [['flagged' => false]],
        ]);

        $speechResponse = new HttpResponse('php://memory', 200, ['Content-Type' => 'audio/mpeg']);
        $speechResponse->getBody()->write($fakeAudioContent);

        $provider = $this->createProviderWithResponses($moderationResponse, $speechResponse);

        $response = $provider->speech('Turn this text into speech.', [
            'model' => 'gpt-4o-mini-tts',
            'voice' => 'alloy',
            'response_format' => 'mp3',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($fakeAudioContent, $response->getContent());

        $metadata = $response->getMetadata();
        $this->assertSame('gpt-4o-mini-tts', $metadata['model']);
        $this->assertSame('alloy', $metadata['voice']);
        $this->assertSame('mp3', $metadata['format']);
        $this->assertSame('audio/mpeg', $metadata['content_type']);
        $this->assertSame('binary_audio', $metadata['data_type']);
        $this->assertSame(1.0, $metadata['speed']);
        $this->assertSame(strlen($fakeAudioContent), $metadata['size_bytes']);
        $this->assertArrayHasKey('created', $metadata);
    }

    public function testSpeechThrowsInvalidVoiceException()
    {
        // Test 2: Test speech method raises InvalidArgumentException on invalid voice

        $moderationResponse = $this->createJsonResponse([
            'id' => 'modr-audio-invalid-voice',
            'model' => 'omni-moderation-latest',
            'results' => [['flagged' => false]],
        ]);

        $provider = $this->createProviderWithResponses($moderationResponse);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Voice 'robot'");

        $provider->speech('Please speak with an unsupported voice.', [
            'voice' => 'robot',
            'response_format' => 'mp3',
        ]);
    }

    public function testTranscribeReturnsTextSuccessfully()
    {
        // Test 3: Test transcribe method for successful completion

        $audioFile = $this->createTempAudioFile();

        try {
            $transcriptionResponse = $this->createJsonResponse([
                'text' => 'This is the transcribed text.',
                'language' => 'en',
                'duration' => 1.42,
                'usage' => ['prompt_tokens' => 12, 'total_tokens' => 18],
            ]);

            $provider = $this->createProviderWithResponses($transcriptionResponse);

            $response = $provider->transcribe($audioFile, [
                'model' => 'gpt-4o-transcribe',
                'response_format' => 'json',
            ]);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('This is the transcribed text.', $response->getContent());

            $metadata = $response->getMetadata();
            $this->assertSame('gpt-4o-transcribe', $metadata['model']);
            $this->assertSame('json', $metadata['response_format']);
            $this->assertSame('en', $metadata['language']);
            $this->assertSame(1.42, $metadata['duration']);
            $this->assertSame(['prompt_tokens' => 12, 'total_tokens' => 18], $metadata['usage']);
            $this->assertArrayHasKey('created', $metadata);
        } finally {
            @unlink($audioFile);
        }
    }

    public function testTranscribeThrowsInvalidResponseFormat()
    {
        // Test 4: Test transcribe method raises InvalidArgumentException on invalid response format

        $audioFile = $this->createTempAudioFile();

        try {
            $provider = $this->createProviderWithResponses();

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("only 'json' response format is supported");

            $provider->transcribe($audioFile, [
                'model' => 'gpt-4o-transcribe',
                'response_format' => 'unsupported_format',
            ]);
        } finally {
            @unlink($audioFile);
        }
    }

    public function testTranslateReturnsTextSuccessfully()
    {
        // Test 5: Test translate method for successful completion

        $audioFile = $this->createTempAudioFile();

        try {
            $translationResponse = $this->createJsonResponse([
                'text' => 'This is the translated text.',
                'usage' => ['prompt_tokens' => 10, 'total_tokens' => 15],
            ]);

            $provider = $this->createProviderWithResponses($translationResponse);

            $response = $provider->translate($audioFile, [
                'model' => 'whisper-1',
                'response_format' => 'json',
            ]);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('This is the translated text.', $response->getContent());

            $metadata = $response->getMetadata();
            $this->assertSame('whisper-1', $metadata['model']);
            $this->assertSame('json', $metadata['response_format']);
            $this->assertSame(['prompt_tokens' => 10, 'total_tokens' => 15], $metadata['usage']);
            $this->assertArrayHasKey('created', $metadata);
        } finally {
            @unlink($audioFile);
        }
    }

    public function testTranslateRaisesProviderExceptionOnServerError()
    {
        // Test 6: Test translate method raises ProviderException on server error

        $audioFile = $this->createTempAudioFile();

        try {
            $serverErrorResponse = $this->createJsonResponse([
                'error' => [
                    'message' => 'Internal server error',
                    'type' => 'server_error',
                ],
            ], 500);

            $provider = $this->createProviderWithResponses($serverErrorResponse);

            $this->expectException(ProviderException::class);
            $this->expectExceptionMessage('Internal server error');

            $provider->translate($audioFile, [
                'model' => 'whisper-1',
                'response_format' => 'json',
            ]);
        } finally {
            @unlink($audioFile);
        }
    }

    public function testTranslateRaisesUnserializableResponseException()
    {
        // Test 7: Test translate method raises UnserializableResponseException on invalid JSON

        $audioFile = $this->createTempAudioFile();

        try {
            $invalidResponse = new HttpResponse('php://memory', 200, ['Content-Type' => 'application/json']);
            $invalidResponse->getBody()->write('{ not valid json');

            $provider = $this->createProviderWithResponses($invalidResponse);

            $this->expectException(UnserializableResponseException::class);
            $this->expectExceptionMessage('Syntax error');

            $provider->translate($audioFile, [
                'model' => 'whisper-1',
                'response_format' => 'json',
            ]);
        } finally {
            @unlink($audioFile);
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

    private function createTempAudioFile(): string
    {
        $audioFile = uniqid('audio-test-', true) . '.mp3';
        file_put_contents($audioFile, 'fake audio data');

        return $audioFile;
    }

    private function createJsonResponse(array $payload, int $status = 200): HttpResponse
    {
        $response = new HttpResponse('php://memory', $status, ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($payload));

        return $response;
    }
}
