<?php

namespace SummerCraft\Service\Tests\Unit\Time;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Time\DateTimeConfig;
use SummerCraft\Service\Time\DefaultDateTimeService;
use SummerCraft\Service\Time\HasTimezone;

class DefaultDateTimeServiceTest extends TestCase
{
    private const TIMESTAMP = 1700000000;

    private function service(): DefaultDateTimeService
    {
        return new DefaultDateTimeService(new DateTimeConfig());
    }

    /**
     * forUser() must accept any HasTimezone
     * implementation, not just the concrete User class of the layer above — this
     * repo has no dependency on that layer at all.
     */
    public function testForUserAcceptsAnyHasTimezoneImplementation(): void
    {
        $user = new class implements HasTimezone {
            public function getTimezone(): string
            {
                return '0';
            }
        };

        $result = $this->service()->fromTime(self::TIMESTAMP)->forUser($user)->toFormat('Y-m-d H:i:s');

        self::assertSame(gmdate('Y-m-d H:i:s', self::TIMESTAMP), $result);
    }

    public function testForUserWithNullKeepsSiteTimezone(): void
    {
        $result = $this->service()->fromTime(self::TIMESTAMP)->forUser(null)->toFormat('Y-m-d H:i:s');

        self::assertSame(gmdate('Y-m-d H:i:s', self::TIMESTAMP), $result);
    }

    public function testForUserAppliesTimezoneOffsetInMinutes(): void
    {
        $user = new class implements HasTimezone {
            public function getTimezone(): string
            {
                return '60';
            }
        };

        $result = $this->service()->fromTime(self::TIMESTAMP)->forUser($user)->toFormat('Y-m-d H:i:s');

        self::assertSame(gmdate('Y-m-d H:i:s', self::TIMESTAMP - 60 * 60), $result);
    }

    /**
     * toFormat(..., isFormat: true) called the
     * deprecated strftime() directly (removed function signature deprecation
     * since PHP 8.1) — replaced with an internal '%'-directive-to-date()
     * translation that must produce the same output strftime() used to, without
     * triggering E_DEPRECATED.
     */
    public function testToFormatWithIsFormatTranslatesStrftimeDirectives(): void
    {
        $result = $this->service()->fromTime(self::TIMESTAMP)->toFormat('%Y-%m-%d %H:%M:%S', true);

        self::assertSame(gmdate('Y-m-d H:i:s', self::TIMESTAMP), $result);
    }

    public function testToFormatWithIsFormatHandlesDayOfYearAndLiteralPercent(): void
    {
        $result = $this->service()->fromTime(self::TIMESTAMP)->toFormat('%j%%', true);

        self::assertSame(str_pad((string)((int)gmdate('z', self::TIMESTAMP) + 1), 3, '0', STR_PAD_LEFT) . '%', $result);
    }
}
