<?php

namespace SummerCraft\Service\Tests\Unit\Network;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Network\DefaultCurlNetwork;
use SummerCraft\Service\Network\Network;
use SummerCraft\Service\Tests\Fixture\NullLogger;

/**
 * Network::METHOD_* constants used to be ints
 * (1-4) while httpRequest()'s $method parameter is declared `string` — passing
 * a METHOD_* constant got silently coerced to a string on the way in, so the
 * strict `$method === self::METHOD_HEAD` check inside httpRequest() could never
 * match (string !== int), and headers were never auto-captured for HEAD
 * requests. Constants are now strings, matching the declared parameter type.
 */
class DefaultCurlNetworkMethodTest extends TestCase
{
    /** @var resource|null */
    private $serverProcess;

    private int $port;

    protected function setUp(): void
    {
        $this->port = random_int(20000, 60000);
        $script = __DIR__ . '/../../Fixture/fake-cookie-http-server.php';
        $this->serverProcess = proc_open(
            ['php', $script, (string)$this->port],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($this->serverProcess, 'failed to start fake HTTP server');

        $deadline = microtime(true) + 3;
        $ready = false;
        while (microtime(true) < $deadline) {
            $probe = @stream_socket_client("tcp://127.0.0.1:{$this->port}", $errno, $errstr, 1);
            if ($probe !== false) {
                fclose($probe);
                $ready = true;
                break;
            }
            usleep(50000);
        }
        self::assertTrue($ready, 'fake HTTP server never started listening');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }
    }

    public function testHeadRequestAutoCapturesHeadersWithoutExplicitSaveCookieOption(): void
    {
        $network = new DefaultCurlNetwork(new NullLogger());

        $body = $network->httpRequest("http://127.0.0.1:{$this->port}/", Network::METHOD_HEAD);

        self::assertSame('', $body, 'CURLOPT_NOBODY should discard the response body for a HEAD request');
        self::assertStringContainsString('200 OK', $network->getLastResponseInfo()['header']);
    }
}
