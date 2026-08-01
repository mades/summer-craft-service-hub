<?php

namespace SummerCraft\Service\Tests\Unit\Mime;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Mime\DefaultMimeTypeConverter;

class DefaultMimeTypeConverterTest extends TestCase
{
    public function testGetTypeReturnsPrimaryMimeForKnownExtension(): void
    {
        $converter = new DefaultMimeTypeConverter();

        self::assertSame('image/png', $converter->getType('png'));
        self::assertSame('application/pdf', $converter->getType('pdf'));
    }

    public function testGetTypeResolvesExtensionFromFullFilename(): void
    {
        $converter = new DefaultMimeTypeConverter();

        self::assertSame('text/x-comma-separated-values', $converter->getType('report.final.csv'));
    }

    public function testGetTypeFallsBackToOctetStreamForUnknownExtension(): void
    {
        $converter = new DefaultMimeTypeConverter();

        self::assertSame('application/octet-stream', $converter->getType('unknown-extension-xyz'));
    }

    public function testGetTypeHeaderIncludesCharsetWhenGiven(): void
    {
        $converter = new DefaultMimeTypeConverter();

        self::assertSame('Content-Type: text/plain; charset=UTF-8', $converter->getTypeHeader('txt', 'UTF-8'));
        self::assertSame('Content-Type: text/plain', $converter->getTypeHeader('txt'));
    }
}
