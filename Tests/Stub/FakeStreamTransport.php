<?php

namespace Joomla\AI\Tests\Stub;

use Joomla\AI\Http\StreamTransportInterface;
use Joomla\Http\Response as HttpResponse;

/**
 * Stream transport that replays fixed chunks and records the request.
 */
class FakeStreamTransport implements StreamTransportInterface
{
    public array $requests = [];

    /**
     * @param   string[]  $chunks      Body chunks to yield (2xx) or concatenate as the error body
     * @param   int       $statusCode  HTTP status of the fake response
     */
    public function __construct(private array $chunks, private int $statusCode = 200)
    {
    }

    public function stream(string $method, string $url, string $body, array $headers = [], ?int $timeout = null): \Generator
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'body' => json_decode($body, true), 'headers' => $headers];

        $response = new HttpResponse('php://memory', $this->statusCode, ['Content-Type' => 'application/json']);

        if ($this->statusCode >= 200 && $this->statusCode < 300) {
            foreach ($this->chunks as $chunk) {
                yield $chunk;
            }
        } else {
            $response->getBody()->write(implode('', $this->chunks));
            $response->getBody()->rewind();
        }

        return $response;
    }
}
