<?php

namespace SummerCraft\Service\Tests\Unit\Processes;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Processes\DefaultProcesses;

class DefaultProcessesTest extends TestCase
{
    public function testRunReturnsCommandOutput(): void
    {
        $processes = new DefaultProcesses();

        $output = $processes->run('echo hello-world');

        self::assertSame(['hello-world'], $output);
    }

    /**
     * runAsync() built its exec() command via
     * sprintf() without escaping $outputFile/$uniqueName — a filename containing
     * shell metacharacters used to be able to inject additional commands. This
     * class has zero real callers today (grepped across all 4 repos), but the
     * fix must not silently swallow injection attempts: a malicious "filename"
     * must land as a literal (non-existent-as-a-command) filename, not execute.
     */
    public function testRunAsyncTreatsMaliciousOutputFileNameAsLiteral(): void
    {
        $processes = new DefaultProcesses();
        $tmpDir = sys_get_temp_dir() . '/default-processes-test-' . uniqid();
        mkdir($tmpDir);
        $canary = $tmpDir . '/canary';
        $maliciousOutputFile = $tmpDir . '/out.txt; touch ' . $canary . ' #';
        $pidFile = $tmpDir . '/pid';

        $processes->runAsync('echo hi', $maliciousOutputFile, $pidFile);
        usleep(300000);

        self::assertFileDoesNotExist($canary, 'shell metacharacters in the output filename must not execute as a command');

        foreach (glob($tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($tmpDir);
    }
}
