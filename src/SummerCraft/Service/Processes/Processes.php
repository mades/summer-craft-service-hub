<?php

namespace SummerCraft\Service\Processes;

interface Processes
{
    /**
     * @return string[] array of pid
     */
    public function getActive(): array;

    /**
     * @return bool is Active
     */
    public function isActive(string $pid): bool;

    /**
     * @param string $command Command to Run
     * @param string $outputFile Output Data file
     * @param string|null $uniqueName Unique pid or null to generate automatic
     * @return string pid
     */
    public function runAsync(string $command, string $outputFile, ?string $uniqueName = null): string;

    /**
     * @param string $command Command to Run
     * @return array output Data
     */
    public function run(string $command): ?array;
}
