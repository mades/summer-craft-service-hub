<?php

namespace SummerCraft\Service\Tests\Unit\Network;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Network\DefaultCurlNetwork;
use SummerCraft\Service\Tests\Fixture\SpyLogger;

/**
 * curl_exec() returns false on any network-level
 * failure (DNS, connection refused, timeout,...) — it does not throw. The old
 * code returned that `false` straight through a `: ?string` return type, which
 * PHP silently coerces to '' under weak typing, masking a failed request as a
 * "successful empty response" and skipping the catch(Throwable) logging path
 * entirely (curl failures never throw).
 */
class DefaultCurlNetworkFailureTest extends TestCase
{
    public function testConnectionRefusedReturnsNullAndLogsWarning(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertIsResource($socket, 'failed to reserve a free local port');
        $address = stream_socket_get_name($socket, false);
        $port = (int)substr($address, strrpos($address, ':') + 1);
        fclose($socket); // now nothing is listening on $port -> connection refused

        $logger = new SpyLogger();
        $network = new DefaultCurlNetwork($logger);

        $result = $network->httpRequest("http://127.0.0.1:{$port}/", options: ['timeout' => 2]);

        self::assertNull($result);
        self::assertNotEmpty($logger->warnings);
        self::assertSame('NETWORK', $logger->warnings[0]['context']['tag'] ?? null);
    }
}
