<?php

namespace SummerCraft\Service\Tests\Unit\HtmlParser;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SummerCraft\Service\HtmlParser\DomHtmlParser;

/**
 * DOMDocument/DOMXPath-based alternative to SimpleHtmlDomParser.
 * Mirrors SimpleHtmlDomParserTest's SSRF regressions (same threat model —
 * SiteChangedNotificator::checkSite() feeds this an externally-fetched,
 * untrusted page body) plus the real selector vocabulary audited across all
 * 4 repos (LawsConroller, WebNotifierService).
 */
class DomHtmlParserTest extends TestCase
{
    public function testLoadParsesOrdinaryHtmlNormally(): void
    {
        $parser = new DomHtmlParser();

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
            $parser = new DomHtmlParser();
            $parser->load("http://127.0.0.1:{$port}/should-not-be-fetched");
        } finally {
            ini_set('default_socket_timeout', $previousTimeout);
        }

        $connection = @stream_socket_accept($socket, 0);
        self::assertFalse($connection, 'DomHtmlParser::load() must never make an outbound request for its $html argument');
        fclose($socket);

        // the literal string was still accepted and parsed as text, not silently dropped
        self::assertTrue($parser->exist());
    }

    public function testLoadDoesNotReadLocalFileWhenHtmlHappensToBeARealPath(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'dom-html-test-');
        file_put_contents($tmpFile, '<html><body>TOTALLY_SECRET_MARKER</body></html>');

        try {
            $parser = new DomHtmlParser();
            $parser->load($tmpFile);

            self::assertTrue($parser->exist());
            // DOMDocument (unlike SimpleHtmlDom) auto-wraps any parsed text in
            // an <html><body>, so a bare body element legitimately exists here —
            // the security-relevant assertion is that its content is the path
            // string itself, never the real file's content.
            $body = $parser->findOne('body');
            self::assertStringNotContainsString(
                'TOTALLY_SECRET_MARKER',
                $body->content(),
                'the real file content must not have been read and parsed'
            );
        } finally {
            unlink($tmpFile);
        }
    }

    public function testLoadDoesNotResolveExternalEntityFromDoctype(): void
    {
        $port = random_int(20000, 60000);
        $socket = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
        self::assertIsResource($socket, "failed to bind listener: {$errstr}");

        $previousTimeout = ini_set('default_socket_timeout', '2');
        try {
            $parser = new DomHtmlParser();
            $parser->load(
                '<!DOCTYPE html [<!ENTITY xxe SYSTEM "http://127.0.0.1:' . $port . '/xxe">]>'
                . '<html><body>&xxe;</body></html>'
            );
        } finally {
            ini_set('default_socket_timeout', $previousTimeout);
        }

        $connection = @stream_socket_accept($socket, 0);
        self::assertFalse($connection, 'DomHtmlParser::load() must never resolve external entities declared in $html');
        fclose($socket);
    }

    public function testFindByTagSelector(): void
    {
        $parser = (new DomHtmlParser())->load('<html><body><a href="/one">One</a><a href="/two">Two</a></body></html>');

        $links = $parser->find('a');

        self::assertCount(2, $links);
        self::assertSame('/one', $links[0]->attr('href'));
        self::assertSame('/two', $links[1]->attr('href'));
    }

    public function testFindByClassSelector(): void
    {
        $parser = (new DomHtmlParser())->load(
            '<html><body><div class="Section1">Body text</div><div class="Other">Skip</div></body></html>'
        );

        $section = $parser->findOne('.Section1');

        self::assertTrue($section->exist());
        self::assertSame('Body text', $section->content());
    }

    public function testFindByDescendantCombinator(): void
    {
        $parser = (new DomHtmlParser())->load(
            '<html><body><article>Direct</article><section><article>Nested</article></section></body></html>'
        );

        $articles = $parser->find('body article');

        self::assertCount(2, $articles);
        self::assertSame('Direct', $articles[0]->content());
        self::assertSame('Nested', $articles[1]->content());
    }

    public function testFindByDirectChildCombinator(): void
    {
        $parser = (new DomHtmlParser())->load(
            '<html><body>'
            . '<div class="usercontent"><span>Direct child</span></div>'
            . '<div class="usercontent"><p><span>Grandchild, not a direct child</span></p></div>'
            . '</body></html>'
        );

        $spans = $parser->find('.usercontent > span');

        self::assertCount(1, $spans);
        self::assertSame('Direct child', $spans[0]->content());
    }

    public function testFindOneReturnsEmptyWrapperWhenNothingMatches(): void
    {
        $parser = (new DomHtmlParser())->load('<html><body><p>No links here</p></body></html>');

        $notFound = $parser->findOne('a');

        self::assertFalse($notFound->exist());
        self::assertSame('', $notFound->content());
        self::assertSame('', $notFound->attr('href'));
    }

    public function testAttrReturnsEmptyStringWhenAttributeMissing(): void
    {
        $parser = (new DomHtmlParser())->load('<html><body><a>No href here</a></body></html>');

        $link = $parser->findOne('a');

        self::assertSame('', $link->attr('href'));
    }

    public function testContentReturnsInnerHtmlNotPlainText(): void
    {
        // Real callers (LawsConroller) run strip_tags() on the result, so
        // content() must include inner markup, not just plain text.
        $parser = (new DomHtmlParser())->load('<html><body><div class="x">Hello <b>World</b></div></body></html>');

        $found = $parser->findOne('.x');

        self::assertSame('Hello <b>World</b>', $found->content());
    }

    public function testUnicodeContentIsPreservedNotMangled(): void
    {
        $parser = (new DomHtmlParser())->load('<html><body><div class="x">Привет, Świat</div></body></html>');

        $found = $parser->findOne('.x');

        self::assertSame('Привет, Świat', $found->content());
    }

    public function testUnsupportedSelectorThrows(): void
    {
        $parser = (new DomHtmlParser())->load('<html><body><a id="x">Link</a></body></html>');

        $this->expectException(InvalidArgumentException::class);
        $parser->find('#x');
    }

    public function testUnloadClearsFoundChildInstances(): void
    {
        $parser = (new DomHtmlParser())->load('<html><body><div class="x">Hello</div></body></html>');
        $found = $parser->findOne('.x');
        self::assertTrue($found->exist());

        $parser->unload();

        self::assertFalse($parser->exist());
        self::assertFalse($found->exist());
    }
}
