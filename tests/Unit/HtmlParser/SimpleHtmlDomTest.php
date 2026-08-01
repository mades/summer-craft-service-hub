<?php

namespace SummerCraft\Service\Tests\Unit\HtmlParser;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\HtmlParser\SimpleHtmlDom\SimpleHtmlDom;

/**
 * - load_file()/loadFile()/createTextNode() removed entirely — confirmed
 *   unreachable from anywhere in the 4 repos, and each was broken in its own
 *   way (loadFile() double-wrapped its args before forwarding to load_file(),
 *   itself the URL/local-file-fetching entry point behind the SSRF;
 *   createTextNode() crashed with a TypeError via end(null) on
 *   empty/oversized input).
 * - the constructor no longer accepts raw content ($str) at all — both real
 *   callers (str_get_html() and SimpleHtmlDomParser::load()) already loaded
 *   content afterwards via load(), never relying on the constructor to do it.
 */
class SimpleHtmlDomTest extends TestCase
{
    public function testDeadAndBrokenMethodsWereRemoved(): void
    {
        self::assertFalse(method_exists(SimpleHtmlDom::class, 'load_file'));
        self::assertFalse(method_exists(SimpleHtmlDom::class, 'loadFile'));
        self::assertFalse(method_exists(SimpleHtmlDom::class, 'createTextNode'));
    }

    public function testConstructorOnlyAcceptsTargetCharset(): void
    {
        $dom = new SimpleHtmlDom('ISO-8859-1');

        // target_charset is a magic property (SimpleHtmlDom::__get())
        self::assertSame('ISO-8859-1', $dom->target_charset); // @phpstan-ignore property.notFound
    }

    public function testStrGetHtmlStillParsesNormally(): void
    {
        $dom = SimpleHtmlDom::str_get_html('<html><body><div class="x">Hello</div></body></html>');

        self::assertNotNull($dom);
        $found = $dom->find('.x');
        self::assertCount(1, $found);
        self::assertSame('Hello', $found[0]->innertext());
    }

    /**
     * convert_text() is reached through attribute access. parse_charset() only
     * reads the old-style meta http-equiv, so that is the form used here.
     */
    private static function documentIn(string $charset, string $title): SimpleHtmlDom
    {
        return SimpleHtmlDom::str_get_html(
            '<html><head><meta http-equiv="Content-Type" content="text/html; charset=' . $charset . '">'
            . '</head><body><a class="x" title="' . $title . '">link</a></body></html>'
        );
    }

    public function testTextIsRecodedFromTheDeclaredCharset(): void
    {
        // "Привет, мир" in windows-1251 — deliberately not valid UTF-8
        $dom = self::documentIn('windows-1251', "\xCF\xF0\xE8\xE2\xE5\xF2, \xEC\xE8\xF0");

        self::assertSame('Привет, мир', $dom->find('.x')[0]->title);
    }

    public function testUnknownCharsetKeepsTheRawTextInsteadOfDroppingIt(): void
    {
        $source = "\xCF\xF0\xE8\xE2\xE5\xF2";
        // iconv() knows x-mac-cyrillic but refuses it as a source, and returns
        // false; mbstring does not know it at all and throws. Either way the
        // bytes must survive.
        $dom = @self::documentIn('x-mac-cyrillic', $source);

        self::assertSame($source, @$dom->find('.x')[0]->title);
    }
}
