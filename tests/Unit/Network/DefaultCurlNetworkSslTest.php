<?php

namespace SummerCraft\Service\Tests\Unit\Network;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Network\DefaultCurlNetwork;
use SummerCraft\Service\Tests\Fixture\NullLogger;
use SummerCraft\Service\Tests\Fixture\SpyLogger;

/**
 * curl used to disable SSL certificate verification
 * by default for every HTTPS request unless an explicit CA path was given — the
 * common case (a plain https:// call) silently accepted any certificate.
 */
class DefaultCurlNetworkSslTest extends TestCase
{
    private const BODY = 'hello-from-fake-https-server';

    /** @var resource|null */
    private $serverProcess;

    private int $port;

    private string $tmpDir;

    private string $certFile;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/curl-ssl-test-' . uniqid();
        mkdir($this->tmpDir);

        $keyFile = $this->tmpDir . '/key.pem';
        $this->certFile = $this->tmpDir . '/cert.pem';
        $combinedFile = $this->tmpDir . '/combined.pem';

        exec(sprintf(
            'openssl req -x509 -newkey rsa:2048 -keyout %s -out %s -days 1 -nodes -subj "/CN=127.0.0.1" 2>&1',
            escapeshellarg($keyFile),
            escapeshellarg($this->certFile)
        ), $output, $exitCode);
        self::assertSame(0, $exitCode, 'openssl cert generation failed: ' . implode("\n", $output));

        file_put_contents($combinedFile, file_get_contents($this->certFile) . file_get_contents($keyFile));

        $this->port = random_int(20000, 60000);
        $script = __DIR__ . '/../../Fixture/fake-https-server.php';
        $this->serverProcess = proc_open(
            ['php', $script, (string)$this->port, $combinedFile],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($this->serverProcess, 'failed to start fake HTTPS server');

        $probeContext = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $deadline = microtime(true) + 3;
        $ready = false;
        while (microtime(true) < $deadline) {
            $probe = @stream_socket_client(
                "ssl://127.0.0.1:{$this->port}",
                $errno,
                $errstr,
                1,
                STREAM_CLIENT_CONNECT,
                $probeContext
            );
            if ($probe !== false) {
                fclose($probe);
                $ready = true;
                break;
            }
            usleep(50000);
        }
        self::assertTrue($ready, 'fake HTTPS server never started listening');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    /**
     * A curl-level failure (here, SSL verification rejecting
     * the self-signed cert) used to be silently coerced to '' by the `: ?string`
     * return type; it must now surface as null plus a logged warning.
     */
    public function testSelfSignedCertificateIsRejectedByDefault(): void
    {
        $logger = new SpyLogger();
        $network = new DefaultCurlNetwork($logger);

        $result = $network->httpRequest("https://127.0.0.1:{$this->port}/");

        self::assertNull($result);
        self::assertNotEmpty($logger->warnings);
    }

    public function testSelfSignedCertificateIsAcceptedWithExplicitCaOption(): void
    {
        $network = new DefaultCurlNetwork(new NullLogger());

        $result = $network->httpRequest("https://127.0.0.1:{$this->port}/", options: ['ssl' => $this->certFile]);

        self::assertSame(self::BODY, $result);
    }

    public function testSelfSignedCertificateIsAcceptedWithExplicitNoSslVerifyOption(): void
    {
        $network = new DefaultCurlNetwork(new NullLogger());

        $result = $network->httpRequest("https://127.0.0.1:{$this->port}/", options: ['noSslVerify' => true]);

        self::assertSame(self::BODY, $result);
    }
}
