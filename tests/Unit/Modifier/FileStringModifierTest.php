<?php

namespace SummerCraft\Service\Tests\Unit\Modifier;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Service\Modifier\FileStringModifier;

class FileStringModifierTest extends TestCase
{
    public function testResolveBackSegmentsCollapsesDotDot(): void
    {
        self::assertSame('a/c', FileStringModifier::resolveBackSegments('a/b/../c'));
    }

    public function testResolveBackSegmentsDropsSingleDot(): void
    {
        self::assertSame('a/b', FileStringModifier::resolveBackSegments('a/./b'));
    }

    /**
     * A '..' with no preceding real segment to cancel out
     * used to be silently dropped (array_pop() on an empty stack is a no-op), instead of
     * signalling that the path tries to climb above its own root — exactly the shape of
     * a path-traversal payload.
     */
    public function testResolveBackSegmentsThrowsOnExcessDotDot(): void
    {
        $this->expectException(RuntimeException::class);
        FileStringModifier::resolveBackSegments('../../etc/passwd');
    }

    public function testResolveBackSegmentsThrowsOnExcessDotDotAfterRealSegment(): void
    {
        $this->expectException(RuntimeException::class);
        FileStringModifier::resolveBackSegments('a/../../etc/passwd');
    }

    public function testToRealPathDoesNotResolveDotDot(): void
    {
        // documents the (intentionally) narrow contract of toRealPath(): it only
        // swaps slash direction, it is not a security boundary
        self::assertSame('a/../b', FileStringModifier::toRealPath('a/../b'));
    }
}
