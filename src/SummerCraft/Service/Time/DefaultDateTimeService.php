<?php

namespace SummerCraft\Service\Time;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use SummerCraft\Service\Time\DateTimeConvertException;

class DefaultDateTimeService implements DateTimeService, RequestScopeComponent
{
    private const UTC_TIMEZONE = 'UTC';
    private const STRING_FORMAT = 'Y-m-d H:i:s';

    private int $now = 0;

    protected int $stateTime = 0;

    protected int|string|null $outTimezone = null;

    protected bool $utcAppend = false;

    public function __construct(
        protected DateTimeConfig $config,
    ) {
        $this->now = time();
    }

    public function getCurrentTimestamp(): int
    {
        return $this->now;
    }

    public function fromTime(?int $timestamp = null): DateTimeService
    {
        $this->stateTime = $timestamp === null ? $this->now : $timestamp;
        $this->outTimezone = null;
        $this->utcAppend = false;
        return $this;
    }


    public function fromString(string $string, string $timezone = 'UTC'): DateTimeService
    {
        date_default_timezone_set($timezone);
        $this->stateTime = strtotime($string);
        $this->outTimezone = null;
        $this->utcAppend = false;
        return $this;
    }


    public function forTimezone(string $timezone): DateTimeService
    {
        if ($timezone) {
            $this->outTimezone = $timezone;
        }
        return $this;
    }

    public function forUser(?HasTimezone $user = null): DateTimeService
    {
        if ($user && $user->getTimezone() !== '') {
            $this->outTimezone = (int)$user->getTimezone();
        }
        return $this;
    }

    public function toTime(): int
    {
        return $this->stateTime;
    }

    public function toFormat(string $format, bool $isFormat = false, bool $utcAppend = false): string
    {
        $append = '';
        if ($this->outTimezone === null) {
            $this->outTimezone = $this->config->siteTimezone;
        }

        if (is_int($this->outTimezone)) {
            date_default_timezone_set('UTC');
            $this->stateTime -= (int)$this->outTimezone * 60;
            if ($utcAppend) {
                $times = $this->secondsToTimeSimple(- $this->outTimezone * 60);
                $append = ' UTC';
                $append .= (($times[2]>0) ? '+' . $times[2] : '-' . abs($times[2]));
                $append .= ($times[1] ? ':' . abs($times[1]) : '');
            }
        } elseif ($this->outTimezone) {
            date_default_timezone_set($this->outTimezone);
        } else {
            date_default_timezone_set('UTC');
        }

        if ($isFormat) {
            return $this->strftimeCompatible($format, $this->stateTime) . $append;
        }
        return date($format, $this->stateTime) . $append;
    }

    /**
     * strftime() was removed/deprecated (PHP 8.1+) without a built-in replacement
     * that accepts the same '%'-directive format strings. This maps the commonly
     * used directives onto date()'s format characters so existing callers of
     * toFormat($format, isFormat: true) keep working without the deprecation.
     */
    private function strftimeCompatible(string $format, int $timestamp): string
    {
        static $map = [
            'a' => 'D', 'A' => 'l', 'd' => 'd', 'm' => 'm', 'b' => 'M', 'h' => 'M',
            'B' => 'F', 'y' => 'y', 'Y' => 'Y', 'H' => 'H', 'I' => 'h', 'M' => 'i',
            'S' => 's', 'p' => 'A', 'P' => 'a', 'Z' => 'T', 'u' => 'N', 'w' => 'w',
        ];
        $result = '';
        $length = strlen($format);
        for ($i = 0; $i < $length; $i++) {
            if ($format[$i] !== '%' || $i === $length - 1) {
                $result .= $format[$i];
                continue;
            }
            $specifier = $format[++$i];
            switch ($specifier) {
                case '%':
                    $result .= '%';
                    break;
                case 'n':
                    $result .= "\n";
                    break;
                case 't':
                    $result .= "\t";
                    break;
                case 'e':
                    $result .= str_pad(date('j', $timestamp), 2, ' ', STR_PAD_LEFT);
                    break;
                case 'j':
                    $result .= str_pad((string)((int)date('z', $timestamp) + 1), 3, '0', STR_PAD_LEFT);
                    break;
                default:
                    $result .= isset($map[$specifier]) ? date($map[$specifier], $timestamp) : '';
            }
        }
        return $result;
    }

    /** secondsToTimeSimple
     * Получение интервала в удобном виде количество дней, часов, минут, секунд
     * @param int $seconds
     * @return array $times:
     * $times[0] - секунды
     * $times[1] - минуты
     * $times[2] - часы
     * $times[3] - дни
     * $times[4] - года
     */
    protected function secondsToTimeSimple($seconds): array
    {
        if ($seconds < 0) { $seconds = - $seconds; }
        $times = array(0,0,0,0,0);
        $periods = array(60, 3600, 86400, 31536000);
        for ($i = 3; $i >= 0; $i--){
            $period = floor($seconds/$periods[$i]);
            if ($period > 0) {
                $times[$i+1] = $period;
                $seconds -= $period * $periods[$i];
            }
        }
        $times[0] = $seconds;

        return $times;
    }

    /**
     * @param int|null $time
     */
    public function sqlTime(?int $time = null): string
    {
        return $this->fromTime($time)->forTimezone('UTC')->toFormat('Y-m-d H:i:s');
    }

    public function addDays(int $timeStamp, int $days): int
    {
        return (new DateTime())->setTimestamp($timeStamp)->add(new DateInterval("P{$days}D"))->getTimestamp();
    }

    public function diffDateTime(string $stringMin, ?string $stringMax = null): DateInterval
    {
        $dateTimeMax = ($stringMax !== null) ? $this->timeStringToDateTime($stringMax) : $this->getCurrentDateTime();
        $dateTimeMin = $this->timeStringToDateTime($stringMin);
        return $dateTimeMin->diff($dateTimeMax);
    }

    public function getCurrentDateTime(): DateTimeImmutable
    {
        try {
            return (new DateTimeImmutable())->setTimezone($this->getUTCTimeZone());
        } catch (Exception $exception) {
            throw DateTimeConvertException::cantCreateCurrentDateTime($exception);
        }
    }

    public function add(DateTimeImmutable $time, string $intervalString): DateTimeImmutable
    {
        try {
            return $time->add(new DateInterval( $intervalString));
        } catch (Exception $exception) {
            throw DateTimeConvertException::cantCreateDateInterval($intervalString, $exception);
        }
    }

    public function sub(DateTimeImmutable $time, string $intervalString): DateTimeImmutable
    {
        try {
            return $time->sub(new DateInterval($intervalString));
        } catch (Exception $exception) {
            throw DateTimeConvertException::cantCreateDateInterval($intervalString, $exception);
        }
    }


    public function addSeconds(DateTimeImmutable $time, int $seconds): DateTimeImmutable
    {
        try {
            return $time->add(new DateInterval('PT' . $seconds . 'S'));
        } catch (Exception $exception) {
            throw DateTimeConvertException::cantCreateDateInterval('PT' . $seconds . 'S', $exception);
        }
    }

    public function subSeconds(DateTimeImmutable $time, int $seconds): DateTimeImmutable
    {
        try {
            return $time->sub(new DateInterval('PT' . $seconds . 'S'));
        } catch (Exception $exception) {
            throw DateTimeConvertException::cantCreateDateInterval('PT' . $seconds . 'S', $exception);
        }
    }

    public function timeStringToDateTime(string $string): DateTimeImmutable
    {
        if ($string && strpos($string, ' ') === false) {
            $string .= ' 00:00:00';
        }
        $result = DateTimeImmutable::createFromFormat(self::STRING_FORMAT, $string, $this->getUTCTimeZone());
        if ($result === false) {
            throw DateTimeConvertException::cantConvertStringToDateTime($string);
        }
        return $result;
    }

    public function dateTimeToTimeString(DateTimeImmutable $datetime): string
    {
        return $datetime->format(self::STRING_FORMAT);
    }

    public function getCurrentTimeAsString(): string
    {
        return $this->getCurrentDateTime()->format(self::STRING_FORMAT);
    }

    private function getUTCTimeZone(): DateTimeZone
    {
        return new DateTimeZone(self::UTC_TIMEZONE);
    }
}
