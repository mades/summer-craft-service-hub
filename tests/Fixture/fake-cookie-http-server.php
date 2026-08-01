<?php

/**
 * Minimal plain-HTTP stub for the DefaultCurlNetwork <-> CookieJar wiring test.
 * Every response sets a fixed cookie via Set-Cookie, and the
 * body echoes back whatever Cookie header the request actually carried (or
 * "none" if there wasn't one) — lets the test assert whether the jar did or
 * didn't auto-attach a previously stored cookie on a later request.
 *
 * Same accept-loop reasoning as fake-https-server.php/fake-smtp-server.php: a
 * throwaway readiness-probe connection must not consume the only accept().
 *
 * Run as a subprocess: php fake-cookie-http-server.php <port>
 */

$port = (int)($argv[1] ?? 0);

$socket = stream_socket_server(
    "tcp://127.0.0.1:{$port}",
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
);
if ($socket === false) {
    fwrite(STDERR, "bind failed: {$errstr}\n");
    exit(1);
}

while (true) {
    $conn = @stream_socket_accept($socket, 10);
    if ($conn === false) {
        break;
    }
    stream_set_timeout($conn, 5);

    $sawRequestLine = false;
    $cookieHeader = null;
    while (!feof($conn)) {
        $line = fgets($conn);
        if ($line === false || trim($line) === '') {
            break;
        }
        $sawRequestLine = true;
        if (stripos($line, 'Cookie:') === 0) {
            $cookieHeader = trim(substr($line, strlen('Cookie:')));
        }
    }

    if ($sawRequestLine) {
        $body = $cookieHeader ?? 'none';
        $response = "HTTP/1.1 200 OK\r\n"
            . "Content-Type: text/plain\r\n"
            . "Set-Cookie: sid=abc123\r\n"
            . "Set-Cookie: theme=dark; Path=/\r\n"
            . "Content-Length: " . strlen($body) . "\r\n"
            . "Connection: close\r\n\r\n"
            . $body;
        fwrite($conn, $response);
    }

    fclose($conn);
}

fclose($socket);
