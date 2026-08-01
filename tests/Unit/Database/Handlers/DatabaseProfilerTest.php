<?php

namespace SummerCraft\Service\Tests\Unit\Database\Handlers;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Database\Config\DatabaseConnectionConfig;
use SummerCraft\Service\Database\Handlers\DatabaseProfiler;
use SummerCraft\Service\Tests\Fixture\SpyLogger;

/**
 * The default $logIfLonger (0.00010) was meant to be
 * milliseconds but got compared as `$totalTime > $logIfLonger / 1000` seconds — i.e.
 * a ~0.1 microsecond threshold, so every query, however fast, logged a warning.
 */
class DatabaseProfilerTest extends TestCase
{
    public function testDefaultConfigDoesNotWarnOnAFastQuery(): void
    {
        $logger = new SpyLogger();
        $profiler = new DatabaseProfiler($logger, new DatabaseConnectionConfig());

        $profiler->preExecution();
        $profiler->preFormatting();
        $profiler->done('SELECT 1', []);

        self::assertSame([], $logger->warnings);
    }

    public function testExplicitLowThresholdStillTriggersAWarning(): void
    {
        // confirms the threshold mechanism itself still works — the fix is only
        // about the *default* value, not about disabling the check
        $logger = new SpyLogger();
        $config = new DatabaseConnectionConfig();
        $config->logIfLonger = 0.00010;
        $profiler = new DatabaseProfiler($logger, $config);

        $profiler->preExecution();
        $profiler->preFormatting();
        // the threshold is ~0.1 microsecond — real elapsed time can round down to
        // exactly 0.0 without a deliberate delay, making the comparison flaky
        usleep(1000);
        $profiler->done('SELECT 1', []);

        self::assertNotEmpty($logger->warnings);
    }

    public function testZeroThresholdNeverWarns(): void
    {
        $logger = new SpyLogger();
        $config = new DatabaseConnectionConfig();
        $config->logIfLonger = 0.0;
        $profiler = new DatabaseProfiler($logger, $config);

        $profiler->preExecution();
        $profiler->preFormatting();
        usleep(1000);
        $profiler->done('SELECT 1', []);

        self::assertSame([], $logger->warnings);
    }
}
