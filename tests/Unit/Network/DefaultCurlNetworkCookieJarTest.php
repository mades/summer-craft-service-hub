<?php

namespace SummerCraft\Service\Tests\Unit\Network;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Network\DefaultCurlNetwork;
use SummerCraft\Service\Tests\Fixture\NullLogger;

/**
 * A single DefaultCurlNetwork
 * instance now auto-attaches cookies received via Set-Cookie on a prior
 * request to later requests against the same host, without the caller having
 * to manage a cookie array by hand — with explicit $options['cookie'] still
 * able to add to/override the jar per-request, and $options['noCookieJar']
 * able to opt out entirely.
 */
class DefaultCurlNetworkCookieJarTest extends TestCase
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
        self::assertIsResource($this->serverProcess, 'failed to start fake cookie HTTP server');

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
        self::assertTrue($ready, 'fake cookie HTTP server never started listening');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }
    }

    public function testJarAutoAttachesCookieReceivedFromEarlierRequestToSameHost(): void
    {
        $network = new DefaultCurlNetwork(new NullLogger());
        $url = "http://127.0.0.1:{$this->port}/";

        $first = $network->httpRequest($url, options: ['saveCookie' => true]);
        self::assertSame('none', $first, 'first request should not have carried any cookie yet');

        $second = $network->httpRequest($url, options: ['saveCookie' => true]);
        self::assertSame('sid=abc123; theme=dark;', $second);
    }

    public function testExplicitCookieOptionIsMergedOnTopOfJar(): void
    {
        $network = new DefaultCurlNetwork(new NullLogger());
        $url = "http://127.0.0.1:{$this->port}/";

        $network->httpRequest($url, options: ['saveCookie' => true]);

        $second = $network->httpRequest($url, options: [
            'saveCookie' => true,
            'cookie' => ['extra' => 'value'],
        ]);
        self::assertSame('sid=abc123; theme=dark; extra=value;', $second);
    }

    public function testExplicitCookieOptionOverridesJarValueForSameName(): void
    {
        $network = new DefaultCurlNetwork(new NullLogger());
        $url = "http://127.0.0.1:{$this->port}/";

        $network->httpRequest($url, options: ['saveCookie' => true]);

        $second = $network->httpRequest($url, options: [
            'saveCookie' => true,
            'cookie' => ['sid' => 'overridden'],
        ]);
        self::assertSame('sid=overridden; theme=dark;', $second);
    }

    public function testNoCookieJarOptionOptsOutOfAutoAttach(): void
    {
        $network = new DefaultCurlNetwork(new NullLogger());
        $url = "http://127.0.0.1:{$this->port}/";

        $network->httpRequest($url, options: ['saveCookie' => true]);

        $second = $network->httpRequest($url, options: ['saveCookie' => true, 'noCookieJar' => true]);
        self::assertSame('none', $second);
    }
}
