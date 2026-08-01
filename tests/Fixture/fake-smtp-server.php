<?php

/**
 * Minimal SMTP stub for EmailSenderSmtpTest — understands just enough of the
 * protocol (EHLO/HELO, AUTH LOGIN, MAIL FROM, RCPT TO, DATA, RSET, QUIT) to let
 * EmailSender's sendWithSmtp() run its full real code path.
 *
 * Accepts connections in a loop rather than just once: the test's own readiness
 * probe (connect-then-close, to know when the server is listening) would
 * otherwise consume the one connection a single accept() serves, leaving the
 * real client to hit "connection refused" once this script has already exited.
 * A throwaway probe sends nothing, so its inner command loop ends on EOF right
 * away and we go back to accept() for the real client.
 *
 * Run as a subprocess: php fake-smtp-server.php <port>
 */

$port = (int)($argv[1] ?? 0);
$socket = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
if ($socket === false) {
    fwrite(STDERR, "bind failed: {$errstr}\n");
    exit(1);
}

while (true) {
    $conn = stream_socket_accept($socket, 10);
    if ($conn === false) {
        break;
    }

    fwrite($conn, "220 fake.smtp ready\r\n");

    $quit = false;
    while (!feof($conn)) {
        $line = fgets($conn);
        if ($line === false) {
            break;
        }
        $line = rtrim($line, "\r\n");

        if (stripos($line, 'EHLO') === 0 || stripos($line, 'HELO') === 0) {
            fwrite($conn, "250 fake.smtp\r\n");
        } elseif (strcasecmp($line, 'AUTH LOGIN') === 0) {
            fwrite($conn, '334 ' . base64_encode('Username:') . "\r\n");
            fgets($conn); // base64 username, ignored
            fwrite($conn, '334 ' . base64_encode('Password:') . "\r\n");
            fgets($conn); // base64 password, ignored
            fwrite($conn, "235 Authentication successful\r\n");
        } elseif (stripos($line, 'MAIL FROM') === 0) {
            fwrite($conn, "250 OK\r\n");
        } elseif (stripos($line, 'RCPT TO') === 0) {
            fwrite($conn, "250 OK\r\n");
        } elseif (strcasecmp($line, 'DATA') === 0) {
            fwrite($conn, "354 Start mail input\r\n");
            while (!feof($conn)) {
                $dataLine = fgets($conn);
                if ($dataLine === false || rtrim($dataLine, "\r\n") === '.') {
                    break;
                }
            }
            fwrite($conn, "250 OK: queued\r\n");
        } elseif (strcasecmp($line, 'RSET') === 0) {
            fwrite($conn, "250 OK\r\n");
        } elseif (strcasecmp($line, 'QUIT') === 0) {
            fwrite($conn, "221 Bye\r\n");
            $quit = true;
            break;
        }
    }

    fclose($conn);
    if ($quit) {
        break;
    }
}

fclose($socket);
