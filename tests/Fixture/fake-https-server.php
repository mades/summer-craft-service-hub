<?php

/**
 * Minimal HTTPS stub for DefaultCurlNetworkSslTest — serves a fixed plain-text
 * body over TLS using a self-signed certificate, for verifying curl's SSL
 * certificate verification behavior.
 *
 * Accepts connections in a loop rather than just once, same reasoning as
 * fake-smtp-server.php: a throwaway readiness-probe connection (no request sent)
 * is served as a no-op and the loop goes back to accept() for the real request.
 *
 * Run as a subprocess: php fake-https-server.php <port> <path-to-cert+key-pem>
 */

$port = (int)($argv[1] ?? 0);
$certFile = $argv[2] ?? '';

$context = stream_context_create([
    'ssl' => [
        'local_cert' => $certFile,
        'allow_self_signed' => true,
        'verify_peer' => false,
    ],
]);

$socket = stream_socket_server(
    "ssl://127.0.0.1:{$port}",
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context
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
    while (!feof($conn)) {
        $line = fgets($conn);
        if ($line === false || trim($line) === '') {
            break;
        }
        $sawRequestLine = true;
    }

    if ($sawRequestLine) {
        $body = 'hello-from-fake-https-server';
        $response = "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nContent-Length: " . strlen($body)
            . "\r\nConnection: close\r\n\r\n" . $body;
        fwrite($conn, $response);
    }

    fclose($conn);
}

fclose($socket);
