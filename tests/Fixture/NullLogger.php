<?php

namespace SummerCraft\Service\Tests\Fixture;

use SummerCraft\Service\Logger\TaggedLogger;
use Stringable;

/** No-op Logger double for tests that need a Logger but don't assert on it */
class NullLogger implements TaggedLogger
{
    public function log($level, string|Stringable $message, array $context = []): void
    {
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
    }

    public function error(string|Stringable $message, array $context = []): void
    {
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
    }

    public function info(string|Stringable $message, array $context = []): void
    {
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
    }

    public function taggedEmergency(string $tag, string $message, array $context = []): void
    {
    }

    public function taggedAlert(string $tag, string $message, array $context = []): void
    {
    }

    public function taggedCritical(string $tag, string $message, array $context = []): void
    {
    }

    public function taggedError(string $tag, string $message, array $context = []): void
    {
    }

    public function taggedWarning(string $tag, string $message, array $context = []): void
    {
    }

    public function taggedNotice(string $tag, string $message, array $context = []): void
    {
    }

    public function taggedInfo(string $tag, string $message, array $context = []): void
    {
    }

    public function taggedDebug(string $tag, string $message, array $context = []): void
    {
    }
}
