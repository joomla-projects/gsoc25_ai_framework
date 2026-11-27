<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Http;

use Composer\CaBundle\CaBundle;
use Joomla\Http\Response as HttpResponse;

/**
 * cURL transport that yields the response body while it is being received.
 *
 * Joomla\Http buffers the whole response, so streaming uses curl_multi directly. The
 * "transport.curl" and "curl.certpath" options mirror the Joomla\Http cURL transport.
 *
 * @since  __DEPLOY_VERSION__
 */
class CurlStreamTransport implements StreamTransportInterface
{
    /**
     * Transport options
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    private array $options;

    /**
     * Constructor.
     *
     * @param   array  $options  Options: timeout, curl.certpath, transport.curl (array of CURLOPT_* => value)
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Check if cURL is available.
     *
     * @return  bool
     * @since   __DEPLOY_VERSION__
     */
    public static function isSupported(): bool
    {
        return \function_exists('curl_multi_init');
    }

    /**
     * {@inheritdoc}
     */
    public function stream(string $method, string $url, string $body, array $headers = [], ?int $timeout = null): \Generator
    {
        $buffer = '';
        $errorBody = '';
        $statusCode = 0;
        $responseHeaders = [];

        $headerLines = ['Expect:'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $timeout ??= isset($this->options['timeout']) ? (int) $this->options['timeout'] : null;

        $options = [
            \CURLOPT_URL            => $url,
            \CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            \CURLOPT_HTTPHEADER     => $headerLines,
            \CURLOPT_FOLLOWLOCATION => true,
            \CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$statusCode, &$responseHeaders): int {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches)) {
                    // New status line (e.g. after a redirect or 100 Continue): reset headers
                    $statusCode = (int) $matches[1];
                    $responseHeaders = [];
                } elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[trim($name)][] = trim($value);
                }

                return \strlen($line);
            },
            \CURLOPT_WRITEFUNCTION  => function ($ch, string $data) use (&$buffer, &$errorBody, &$statusCode): int {
                if ($statusCode >= 200 && $statusCode < 300) {
                    $buffer .= $data;
                } else {
                    $errorBody .= $data;
                }

                return \strlen($data);
            },
        ];

        if ($body !== '' || strtoupper($method) === 'POST') {
            $options[\CURLOPT_POSTFIELDS] = $body;
        }

        if ($timeout !== null) {
            $options[\CURLOPT_TIMEOUT] = $timeout;
            $options[\CURLOPT_CONNECTTIMEOUT] = $timeout;
        }

        if (!empty($this->options['curl.certpath'])) {
            $options[\CURLOPT_CAINFO] = $this->options['curl.certpath'];
        } elseif (class_exists(CaBundle::class)) {
            $caPathOrFile = CaBundle::getSystemCaRootBundlePath();
            $options[is_dir($caPathOrFile) ? \CURLOPT_CAPATH : \CURLOPT_CAINFO] = $caPathOrFile;
        }

        foreach ($this->options['transport.curl'] ?? [] as $key => $value) {
            $options[$key] = $value;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $multi = curl_multi_init();
        curl_multi_add_handle($multi, $ch);

        try {
            do {
                $status = curl_multi_exec($multi, $running);

                if ($buffer !== '') {
                    $chunk = $buffer;
                    $buffer = '';

                    yield $chunk;
                }

                if ($running && $status === \CURLM_OK) {
                    curl_multi_select($multi, 1.0);
                }
            } while ($running && $status === \CURLM_OK);

            $info = curl_multi_info_read($multi);
            if ($status !== \CURLM_OK || ($info !== false && $info['result'] !== \CURLE_OK)) {
                $message = curl_error($ch) ?: ($info !== false ? curl_strerror($info['result']) : curl_multi_strerror($status));

                throw new \RuntimeException('Streaming request failed: ' . $message);
            }
        } finally {
            curl_multi_remove_handle($multi, $ch);
            curl_multi_close($multi);
        }

        $response = new HttpResponse('php://memory', $statusCode ?: 200, $responseHeaders);
        $response->getBody()->write($errorBody);
        $response->getBody()->rewind();

        return $response;
    }
}
