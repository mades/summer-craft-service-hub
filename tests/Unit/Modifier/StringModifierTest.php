<?php

namespace SummerCraft\Service\Tests\Unit\Modifier;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Modifier\StringModifier;

class StringModifierTest extends TestCase
{
    private const WORD1 = 'голос';
    private const WORD2 = 'голоса';
    private const WORD5 = 'голосов';

    /**
     * @dataProvider wordEndProvider
     */
    public function testWordEnd(int $count, string $expected): void
    {
        self::assertSame($expected, StringModifier::wordEnd($count, self::WORD1, self::WORD2, self::WORD5));
    }

    public static function wordEndProvider(): array
    {
        return [
            '0 -> word5' => [0, self::WORD5],
            '1 -> word1' => [1, self::WORD1],
            '2 -> word2' => [2, self::WORD2],
            '4 -> word2' => [4, self::WORD2],
            '5 -> word5' => [5, self::WORD5],
            '11 -> word5 (exception)' => [11, self::WORD5],
            '12 -> word5 (exception)' => [12, self::WORD5],
            '14 -> word5 (exception)' => [14, self::WORD5],
            '15 -> word5' => [15, self::WORD5],
            '21 -> word1' => [21, self::WORD1],
            '22 -> word2' => [22, self::WORD2],
            '25 -> word5' => [25, self::WORD5],
            // Regression: exception for xx11-xx14 was lost once $count > 19,
            // because the code collapsed straight to the last digit without re-checking the range.
            '111 -> word5 (regression)' => [111, self::WORD5],
            '112 -> word5 (regression)' => [112, self::WORD5],
            '114 -> word5 (regression)' => [114, self::WORD5],
            '211 -> word5 (regression)' => [211, self::WORD5],
            '115 -> word5' => [115, self::WORD5],
            '121 -> word1' => [121, self::WORD1],
            '122 -> word2' => [122, self::WORD2],
            '100 -> word5' => [100, self::WORD5],
            '101 -> word1' => [101, self::WORD1],
            '1011 -> word5 (regression)' => [1011, self::WORD5],
        ];
    }

    public function testFillWordEndPrependsCountByDefault(): void
    {
        self::assertSame('111 голосов', StringModifier::fillWordEnd(111, 'голос;голоса;голосов'));
    }

    public function testFillWordEndWithoutCount(): void
    {
        self::assertSame(' голосов', StringModifier::fillWordEnd(111, 'голос;голоса;голосов', false));
    }

    /**
     * htmlForHtml() used to return its input
     * unchanged despite being documented/named as an HTML sanitizer — silently
     * unsafe. It must now fail loudly instead of pretending to sanitize.
     */
    public function testHtmlForHtmlThrowsBecauseItIsNotImplemented(): void
    {
        $this->expectException(\RuntimeException::class);
        StringModifier::htmlForHtml('<script>alert(1)</script>');
    }

    /**
     * fromHtml() used to always return '',
     * indistinguishable from a legitimately empty result.
     */
    public function testFromHtmlThrowsBecauseItIsNotImplemented(): void
    {
        $this->expectException(\RuntimeException::class);
        StringModifier::fromHtml('<p>text</p>');
    }

    /**
     * Documents (without changing) how the
     * string-$filterOption branch of filterChars() behaves — it is interpolated
     * directly into a regex character class, so callers must only ever pass
     * static/trusted literals. Mirrors the one real string-literal caller
     * (FileExplorer's MainFileExplorerController).
     */
    public function testFilterCharsWithStringOptionKeepsOnlyCharsInClass(): void
    {
        $result = StringModifier::filterChars(
            'a1!b2.c[3]d-e',
            'A-Za-z0-9\! \.\,\(\)\[\]_-'
        );

        self::assertSame('a1!b2.c[3]d-e', $result);
    }

    public function testFilterCharsWithStringOptionStripsDisallowedChars(): void
    {
        self::assertSame('abc123', StringModifier::filterChars('abc#123$', 'A-Za-z0-9'));
    }

    /**
     * stringToJsonEncode() backs AbstractBuilder::js() one layer up:
     * embedding a value into a JS-string-literal context inside <script> needs
     * JSON encoding, not HTML-entity escaping — htmlspecialchars() doesn't
     * escape '\' or raw control characters, which are significant inside a JS
     * string and can break the surrounding script's syntax.
     */
    public function testStringToJsonEncodeProducesAJsonStringLiteral(): void
    {
        self::assertSame('"#popup1"', StringModifier::stringToJsonEncode('#popup1'));
    }

    public function testStringToJsonEncodeEscapesBackslashAndQuotes(): void
    {
        self::assertSame('"a\\\\b\"c"', StringModifier::stringToJsonEncode('a\\b"c'));
    }

    public function testStringToJsonEncodeEscapesControlCharacters(): void
    {
        $result = StringModifier::stringToJsonEncode("a\nb");

        self::assertSame('"a\nb"', $result);
        self::assertStringNotContainsString("\n", $result);
    }
}
