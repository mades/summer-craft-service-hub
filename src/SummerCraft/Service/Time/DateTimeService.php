<?php

namespace SummerCraft\Service\Time;

use DateInterval;
use DateTimeImmutable;

interface DateTimeService
{
    public function getCurrentTimestamp(): int;

    /**
     * @param int|null $timestamp
     */
    public function fromTime(?int $timestamp = null): DateTimeService;

    /**
     * Create DateService State for time from string
     */
    public function fromString(string $string, string $timezone = 'UTC'): DateTimeService;

    /**
     * Prepare time for format for timezone
     */
    public function forTimezone(string $timezone): DateTimeService;

    /**
     * Prepare time for format for user using his timezone
     *
     * @param HasTimezone|null $user
     */
    public function forUser(?HasTimezone $user = null): DateTimeService;

    /**
     * Format time to timestamp
     */
    public function toTime(): int;

    /**
     * Format Time to string
     */
    public function toFormat(string $format, bool $isFormat = false, bool $utcAppend = false): string;

    /**
     * Date in Y-m-d H:i:s format for SQL DateTime
     *
     * @param int|null $time
     */
    public function sqlTime(?int $time = null): string;

    /**
     * Modify timestamp for days
     */
    public function addDays(int $timeStamp, int $days): int;

    public function diffDateTime(string $stringMin, ?string $stringMax = null): DateInterval;

    public function getCurrentDateTime(): DateTimeImmutable;

    public function add(DateTimeImmutable $time, string $intervalString): DateTimeImmutable;

    public function sub(DateTimeImmutable $time, string $intervalString): DateTimeImmutable;

    public function addSeconds(DateTimeImmutable $time, int $seconds): DateTimeImmutable;

    public function subSeconds(DateTimeImmutable $time, int $seconds): DateTimeImmutable;

    public function timeStringToDateTime(string $string): DateTimeImmutable;

    public function dateTimeToTimeString(DateTimeImmutable $datetime): string;

    public function getCurrentTimeAsString(): string;
}
