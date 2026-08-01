<?php

namespace SummerCraft\Service\Tests\Unit\Network;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Network\CookieJar;

/**
 * The jar used to be a single flat bag
 * shared across every host talked to during the process lifetime, and the
 * Set-Cookie regex required a trailing ";" so the last cookie in a header
 * without one was silently dropped.
 */
class CookieJarTest extends TestCase
{
    public function testHostOnlyCookieAppliesOnlyToExactHost(): void
    {
        $jar = new CookieJar();

        $jar->storeFromSetCookieHeader('sid=abc123', 'example.com');

        self::assertSame(['sid' => 'abc123'], $jar->forHost('example.com'));
        self::assertSame([], $jar->forHost('www.example.com'));
        self::assertSame([], $jar->forHost('other.com'));
    }

    public function testDomainCookieAppliesToSubdomains(): void
    {
        $jar = new CookieJar();

        $jar->storeFromSetCookieHeader('sid=abc123; Domain=example.com; Path=/', 'www.example.com');

        self::assertSame(['sid' => 'abc123'], $jar->forHost('example.com'));
        self::assertSame(['sid' => 'abc123'], $jar->forHost('www.example.com'));
        self::assertSame(['sid' => 'abc123'], $jar->forHost('deep.sub.example.com'));
        self::assertSame([], $jar->forHost('notexample.com'));
        self::assertSame([], $jar->forHost('other.com'));
    }

    public function testDomainAttributeWithLeadingDotIsNormalized(): void
    {
        $jar = new CookieJar();

        $jar->storeFromSetCookieHeader('sid=abc123; Domain=.example.com', 'www.example.com');

        self::assertSame(['sid' => 'abc123'], $jar->forHost('example.com'));
        self::assertSame(['sid' => 'abc123'], $jar->forHost('www.example.com'));
    }

    public function testCookiesForDifferentHostsDoNotBleedIntoEachOther(): void
    {
        $jar = new CookieJar();

        $jar->storeFromSetCookieHeader('a=1', 'host-a.com');
        $jar->storeFromSetCookieHeader('b=2', 'host-b.com');

        self::assertSame(['a' => '1'], $jar->forHost('host-a.com'));
        self::assertSame(['b' => '2'], $jar->forHost('host-b.com'));
    }

    public function testCookieWithoutTrailingSemicolonIsStillStored(): void
    {
        $jar = new CookieJar();

        // No trailing ";" after the last attribute/name=value pair — this used
        // to be lost when the header block was split with a regex requiring one.
        $jar->storeFromSetCookieHeader('sid=abc123', 'example.com');

        self::assertSame(['sid' => 'abc123'], $jar->forHost('example.com'));
    }

    public function testMultipleCookiesForSameScopeAreMerged(): void
    {
        $jar = new CookieJar();

        $jar->storeFromSetCookieHeader('sid=abc123; Domain=example.com', 'example.com');
        $jar->storeFromSetCookieHeader('theme=dark; Domain=example.com; Path=/', 'example.com');

        self::assertSame(['sid' => 'abc123', 'theme' => 'dark'], $jar->forHost('example.com'));
    }

    public function testLaterCookieOverwritesEarlierOneWithSameName(): void
    {
        $jar = new CookieJar();

        $jar->storeFromSetCookieHeader('sid=first', 'example.com');
        $jar->storeFromSetCookieHeader('sid=second', 'example.com');

        self::assertSame(['sid' => 'second'], $jar->forHost('example.com'));
    }

    public function testMalformedCookieWithoutEqualsSignIsIgnored(): void
    {
        $jar = new CookieJar();

        $jar->storeFromSetCookieHeader('not-a-valid-cookie', 'example.com');

        self::assertSame([], $jar->forHost('example.com'));
    }

    public function testEmptyHostReturnsNoCookies(): void
    {
        $jar = new CookieJar();

        $jar->storeFromSetCookieHeader('sid=abc123', 'example.com');

        self::assertSame([], $jar->forHost(''));
    }

    public function testHostMatchingIsCaseInsensitive(): void
    {
        $jar = new CookieJar();

        $jar->storeFromSetCookieHeader('sid=abc123; Domain=Example.COM', 'Example.COM');

        self::assertSame(['sid' => 'abc123'], $jar->forHost('www.example.com'));
    }
}
