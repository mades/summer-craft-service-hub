<?php

namespace SummerCraft\Service\Processes;

use Exception;

class DefaultProcesses implements Processes
{
    protected array $processes = [];

    public function __construct()
    {
    }

    /**
     * @return string[] array of pid
     */
    public function getActive(): array
    {
        foreach ($this->processes as $pid => $processActive) {
            if (!$this->isActive($pid)) {
                unset($this->processes[$pid]);
            }
        }

        return array_values($this->processes);
    }

    /**
     * @return bool is Active
     */
    public function isActive(string $pid): bool
    {
        try {
            $result = shell_exec(sprintf('ps %d', $pid));
            if( count(explode("\n", $result)) > 2){
                return true;
            }
        } catch (Exception $e) { }

        return false;
    }

    /**
     * @param string $command Command to Run
     * @param string $outputFile Output Data file
     * @param string|null $uniqueName Unique pid or null to generate automatic
     * @return string pid
     * @throws Exception
     */
    public function runAsync(string $command, string $outputFile, ?string $uniqueName = null): string
    {
        $pidFile = $uniqueName;
        if (!$uniqueName) {
            $pidFile = md5((string)random_int(0,10000000));
        }

        // $command is intentionally not escaped — by contract it IS the shell
        // command to run (same contract as run() below), not a data value being
        // interpolated into one. $outputFile/$pidFile are filenames and are
        // escaped, since an attacker-controlled filename here could otherwise
        // inject additional shell commands.
        exec(
            sprintf(
                '%s > %s 2>&1 & echo $! >> %s', // " > /dev/null 2>/dev/null &" // output and stderror
                $command,
                escapeshellarg($outputFile),
                escapeshellarg($pidFile)
            )
        );

        $this->processes[$pidFile] = true;
        return $pidFile;
    }

    /**
     * @param string $command Command to Run
     * @return array output Data
     */
    public function run(string $command): ?array
    {
        $output = [];
        exec($command, $output);
        return $output;
    }
}
