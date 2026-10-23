<?php

namespace Joomla\AI\Tests\Http;

use Joomla\AI\Http\CurlStreamTransport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Runs CurlStreamTransport against PHP's built-in web server.
 */
#[Group('integration')]
class CurlStreamTransportTest extends TestCase
{
    private static $server;
    private static int $port;
    private static string $docRoot;

    public static function setUpBeforeClass(): void
    {
        if (!CurlStreamTransport::isSupported()) {
            self::markTestSkipped('cURL is not available.');
        }

        self::$docRoot = sys_get_temp_dir() . '/ai-stream-test-' . uniqid();
        mkdir(self::$docRoot);

        file_put_contents(self::$docRoot . '/stream.php', <<<'PHP'
<?php
if (($_GET['status'] ?? '') === 'error') {
    http_response_code(429);
    echo '{"error":{"message":"slow down"}}';
    return;
}
header('Content-Type: text/event-stream');
while (ob_get_level()) { ob_end_flush(); }
foreach (['one', 'two', 'three'] as $word) {
    echo "data: $word\n\n";
    flush();
    usleep(150000);
}
PHP);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $command = [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, '-t', self::$docRoot];
        self::$server = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        // Wait for the server to accept connections
        for ($i = 0; $i < 50; $i++) {
            $conn = @fsockopen('127.0.0.1', self::$port);
            if ($conn) {
                fclose($conn);
                return;
            }
            usleep(100000);
        }

        self::markTestSkipped('Could not start the PHP built-in server.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }

        @unlink(self::$docRoot . '/stream.php');
        @rmdir(self::$docRoot);
    }

    public function testChunksArriveIncrementally(): void
    {
        $transport = new CurlStreamTransport(['timeout' => 10]);
        $stream = $transport->stream('POST', 'http://127.0.0.1:' . self::$port . '/stream.php', '{}', ['Content-Type' => 'application/json']);

        $chunks = [];
        $start = microtime(true);
        $firstChunkAt = null;

        foreach ($stream as $chunk) {
            $firstChunkAt ??= microtime(true) - $start;
            $chunks[] = $chunk;
        }

        $total = microtime(true) - $start;

        $this->assertSame("data: one\n\ndata: two\n\ndata: three\n\n", implode('', $chunks));
        $this->assertGreaterThan(1, count($chunks), 'Body should arrive in several chunks');
        $this->assertLessThan($total, $firstChunkAt + 0.1, 'First chunk should arrive before the response completes');
        $this->assertSame(200, $stream->getReturn()->getStatusCode());
    }

    public function testErrorStatusIsBufferedNotYielded(): void
    {
        $transport = new CurlStreamTransport(['timeout' => 10]);
        $stream = $transport->stream('POST', 'http://127.0.0.1:' . self::$port . '/stream.php?status=error', '{}');

        $this->assertSame([], iterator_to_array($stream));

        $response = $stream->getReturn();
        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('{"error":{"message":"slow down"}}', (string) $response->getBody());
    }
}
