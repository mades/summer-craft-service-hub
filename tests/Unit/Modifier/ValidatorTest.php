<?php

namespace SummerCraft\Service\Tests\Unit\Modifier;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Modifier\Validator;

/**
 * Validator::isValidEmail() used to run its
 * own regex with a `{2,6}` TLD length limit, rejecting legitimate modern TLDs
 * longer than 6 characters. It's now a thin wrapper over
 * filter_var(FILTER_VALIDATE_EMAIL) with IDN support, matching
 * EmailSender::isValidEmail()'s (the more correct of the two duplicates)
 * behavior.
 */
class ValidatorTest extends TestCase
{
    public function testAcceptsOrdinaryEmail(): void
    {
        self::assertTrue(Validator::isValidEmail('user@example.com'));
    }

    public function testAcceptsTldLongerThanSixCharacters(): void
    {
        self::assertTrue(Validator::isValidEmail('user@example.photography'));
        self::assertTrue(Validator::isValidEmail('user@example.company'));
    }

    public function testRejectsMalformedEmail(): void
    {
        self::assertFalse(Validator::isValidEmail('not-an-email'));
        self::assertFalse(Validator::isValidEmail('user@'));
        self::assertFalse(Validator::isValidEmail('@example.com'));
    }
}
