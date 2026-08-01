<?php

namespace SummerCraft\Service\Tests\Unit\HtmlParser;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\HtmlParser\SimpleHtmlDomParser;

/**
 * The vendored SimpleHtmlDom constructor
 * silently treats a string that looks like a URL ("^http://") or an existing
 * local file path (is_file()) as something to fetch/read instead of parsing
 * it as HTML text. HtmlParser::load(?string $html) promises to parse $html as
 * literal HTML — real callers (SiteChangedNotificator::checkSite()) feed it
 * the raw body of an externally-fetched, untrusted page, so honoring that
 * sniffing was a live SSRF / arbitrary local file read.
 */
class SimpleHtmlDomParserTest extends TestCase
{
    public function testLoadParsesOrdinaryHtmlNormally(): void
    {
        $parser = new SimpleHtmlDomParser();

        $parser->load('<html><body><div class="x">Hello</div></body></html>');

        self::assertTrue($parser->exist());
        $found = $parser->find('.x');
        self::assertCount(1, $found);
        self::assertSame('Hello', $found[0]->content());
    }

    public function testLoadDoesNotFetchUrlWhenHtmlLooksLikeOne(): void
    {
        $port = random_int(20000, 60000);
        $socket = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
        self::assertIsResource($socket, "failed to bind listener: {$errstr}");

        $previousTimeout = ini_set('default_socket_timeout', '2');
        try {
            $parser = new SimpleHtmlDomParser();
            $parser->load("http://127.0.0.1:{$port}/should-not-be-fetched");
        } finally {
            ini_set('default_socket_timeout', $previousTimeout);
        }

        $connection = @stream_socket_accept($socket, 0);
        self::assertFalse($connection, 'HtmlParser::load() must never make an outbound request for its $html argument');
        fclose($socket);

        // the literal string was still accepted and parsed as text, not silently dropped
        self::assertTrue($parser->exist());
    }

    public function testLoadDoesNotReadLocalFileWhenHtmlHappensToBeARealPath(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'shd-test-');
        file_put_contents($tmpFile, '<html><body>TOTALLY_SECRET_MARKER</body></html>');

        try {
            $parser = new SimpleHtmlDomParser();
            $parser->load($tmpFile);

            self::assertTrue($parser->exist());
            self::assertSame([], $parser->find('body'), 'the real file content must not have been read and parsed');
        } finally {
            unlink($tmpFile);
        }
    }
}
