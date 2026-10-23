<?php

namespace Joomla\AI\Tests\Stub;

use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use Joomla\Http\Response as HttpResponse;

/**
 * Builds an HttpFactory stub whose client answers by "METHOD url" and records every request.
 *
 * Route values: a JSON-encodable array (200), an HttpResponse, a \Throwable to throw, or a
 * list of those (wrapped in ['sequence' => [...]]) returned one per call.
 */
trait RoutingHttpTrait
{
    /** @var array<int, array{method: string, url: string, body: mixed, headers: array}> */
    protected array $requests = [];

    /** @var array<int, array> Options passed to HttpFactory::getHttp() */
    protected array $httpOptions = [];

    protected function routingHttpFactory(array $routes): HttpFactory
    {
        $http = $this->createStub(Http::class);

        $handle = function (string $method, string $url, $body, array $headers) use (&$routes) {
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => $body, 'headers' => $headers];
            $key = $method . ' ' . $url;

            if (!array_key_exists($key, $routes)) {
                throw new \LogicException("No stub route for $key");
            }

            $route = $routes[$key];

            if (is_array($route) && isset($route['sequence'])) {
                $route = array_shift($routes[$key]['sequence']);
            }

            if ($route instanceof \Throwable) {
                throw $route;
            }

            return $route instanceof HttpResponse ? $route : $this->jsonResponse($route);
        };

        $http->method('get')->willReturnCallback(fn ($url, $headers = []) => $handle('GET', $url, null, $headers));
        $http->method('post')->willReturnCallback(fn ($url, $data, $headers = []) => $handle('POST', $url, $data, $headers));
        $http->method('delete')->willReturnCallback(fn ($url, $headers = [], $timeout = null, $data = null) => $handle('DELETE', $url, $data, $headers));

        $factory = $this->createStub(HttpFactory::class);
        $factory->method('getHttp')->willReturnCallback(function ($options = []) use ($http) {
            $this->httpOptions[] = $options;

            return $http;
        });

        return $factory;
    }

    protected function jsonResponse($payload, int $status = 200, array $headers = []): HttpResponse
    {
        $response = new HttpResponse('php://memory', $status, $headers + ['Content-Type' => 'application/json']);
        $response->getBody()->write(is_string($payload) ? $payload : json_encode($payload));
        $response->getBody()->rewind();

        return $response;
    }

    /**
     * Decoded JSON body of the request at the given index.
     */
    protected function sentJson(int $index): array
    {
        return json_decode((string) $this->requests[$index]['body'], true);
    }
}
