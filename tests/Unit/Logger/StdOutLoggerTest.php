<?php

namespace SummerCraft\Service\Tests\Unit\Logger;

use PHPUnit\Framework\TestCase;

/**
 * StdOutLogger writes to php://stdout/php://stderr (real process
 * streams under any SAPI, unlike the STDOUT/STDERR constants which only exist
 * under the CLI SAPI) — exercised via a real subprocess (tests/Fixture/
 * stdout-logger-script.php) so stdout/stderr can be captured separately
 * without corrupting PHPUnit's own output.
 */
class StdOutLoggerTest extends TestCase
{
    private function runFixture(string $threshold, bool $splitStreams, string $format = 'default'): array
    {
        $script = __DIR__ . '/../../Fixture/stdout-logger-script.php';
        $process = proc_open(
            ['php', $script, $threshold, $splitStreams ? '1' : '0', $format],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process, 'failed to start stdout-logger-script.php subprocess');

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return [$stdout, $stderr];
    }

    public function testSplitsBySeverityBetweenStdoutAndStderrByDefault(): void
    {
        [$stdout, $stderr] = $this->runFixture('debug', true);

        self::assertStringContainsString('[TAG] debug message', $stdout);
        self::assertStringContainsString('[TAG] info message', $stdout);
        self::assertStringNotContainsString('warning message', $stdout);
        self::assertStringNotContainsString('error message', $stdout);

        self::assertStringContainsString('[TAG] warning message', $stderr);
        self::assertStringContainsString('[TAG] error message', $stderr);
        self::assertStringNotContainsString('debug message', $stderr);
        self::assertStringNotContainsString('info message', $stderr);
    }

    public function testWritesEverythingToStdoutWhenSplitStreamsDisabled(): void
    {
        [$stdout, $stderr] = $this->runFixture('debug', false);

        self::assertStringContainsString('debug message', $stdout);
        self::assertStringContainsString('info message', $stdout);
        self::assertStringContainsString('warning message', $stdout);
        self::assertStringContainsString('error message', $stdout);

        self::assertSame('', $stderr);
    }

    public function testMessagesBelowThresholdAreNotWrittenToEitherStream(): void
    {
        [$stdout, $stderr] = $this->runFixture('warning', true);

        self::assertStringNotContainsString('debug message', $stdout . $stderr);
        self::assertStringNotContainsString('info message', $stdout . $stderr);
        self::assertStringContainsString('warning message', $stderr);
        self::assertStringContainsString('error message', $stderr);
    }

    public function testMessageFormatMatchesLevelAndDateBracketPrefix(): void
    {
        [$stdout] = $this->runFixture('debug', false);

        self::assertMatchesRegularExpression(
            '/^\[debug \| \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[TAG\] debug message$/m',
            $stdout
        );
    }

    public function testJsonFormatProducesOneValidJsonObjectPerLine(): void
    {
        [$stdout] = $this->runFixture('debug', false, 'json');

        $lines = array_filter(explode("\n", $stdout));
        self::assertCount(4, $lines);

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            self::assertIsArray($decoded, "not valid JSON: $line");
            self::assertArrayHasKey('level', $decoded);
            self::assertArrayHasKey('date', $decoded);
            self::assertArrayHasKey('tag', $decoded);
            self::assertArrayHasKey('message', $decoded);
            self::assertArrayHasKey('context', $decoded);
        }

        $first = json_decode($lines[0], true);
        self::assertSame('debug', $first['level']);
        self::assertSame('TAG', $first['tag']);
        self::assertSame('debug message', $first['message']);
    }

    public function testCustomTemplateFormatSubstitutesPlaceholders(): void
    {
        [$stdout] = $this->runFixture('debug', false, '{level}::{tag}::{message}');

        self::assertStringContainsString('debug::TAG::debug message', $stdout);
        self::assertStringContainsString('info::TAG::info message', $stdout);
    }
}
