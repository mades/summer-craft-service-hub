<?php

namespace SummerCraft\Service\Tests\Unit\Email\Sender;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Email\Sender\EmailSender;

/**
 * `$$this->config['SMTPKeepAlive']` (a double
 * variable-variable) in SMTPEnd()/SMTPAuthenticate() fatally crashed
 * ("Object of class EmailSender could not be converted to string") on every SMTP
 * send. There's no way to catch that without actually driving the SMTP protocol
 * end to end, so this spins up a minimal fake SMTP server as a subprocess.
 */
class EmailSenderSmtpTest extends TestCase
{
    /** @var resource|null */
    private $serverProcess;

    private int $port;

    protected function setUp(): void
    {
        $this->port = random_int(20000, 60000);
        $script = __DIR__ . '/../../../Fixture/fake-smtp-server.php';

        $this->serverProcess = proc_open(
            ['php', $script, (string)$this->port],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($this->serverProcess, 'failed to start fake SMTP server');

        $deadline = microtime(true) + 2;
        $connected = false;
        while (microtime(true) < $deadline) {
            $probe = @stream_socket_client("tcp://127.0.0.1:{$this->port}", $errno, $errstr, 0.1);
            if ($probe !== false) {
                fclose($probe);
                $connected = true;
                break;
            }
            usleep(20000);
        }
        self::assertTrue($connected, 'fake SMTP server never started listening');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }
    }

    public function testSendViaSmtpWithoutAuthDoesNotFatal(): void
    {
        $sender = new EmailSender([
            'protocol' => 'smtp',
            'SMTPHost' => '127.0.0.1',
            'SMTPPort' => $this->port,
            'SMTPKeepAlive' => false,
            'SMTPTimeout' => 5,
        ]);
        $sender->setFrom('sender@example.test', 'Sender');
        $sender->setSubject('Test subject');
        $sender->setMessage('Hello world');
        $sender->setTo(['recipient@example.test']);

        $result = $sender->send();

        self::assertTrue($result, $sender->printDebugger());
    }

    /**
     * Exercises the SMTPAuthenticate() code path specifically — that's where the
     * second occurrence of the bug lived.
     */
    public function testSendViaSmtpWithAuthDoesNotFatal(): void
    {
        $sender = new EmailSender([
            'protocol' => 'smtp',
            'SMTPHost' => '127.0.0.1',
            'SMTPPort' => $this->port,
            'SMTPUser' => 'user',
            'SMTPPass' => 'pass',
            'SMTPKeepAlive' => true,
            'SMTPTimeout' => 5,
        ]);
        $sender->setFrom('sender@example.test', 'Sender');
        $sender->setSubject('Test subject');
        $sender->setMessage('Hello world');
        $sender->setTo(['recipient@example.test']);

        $result = $sender->send();

        self::assertTrue($result, $sender->printDebugger());
    }
}
