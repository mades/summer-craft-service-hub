<?php

namespace SummerCraft\Service\Console;

interface Console
{
    /**
     * Log message to console
     * @param mixed $message Message
     * @param bool $clearLine Clear line (only if message without endLines)
     * @param bool $endLine Is end of line (not possible to clear in feature)
     */
    public function log(mixed $message, bool $clearLine = false, bool $endLine = true): void;

    /**
     * Log message to console with progress
     * @param string $message Message
     */
    public function logWithProgress(string $message, int $now, int $total): void;

    /**
     * Return time as string, that show ETA
     */
    public function progressRemaining(int $now, int $total, bool $showCounters): string;

    public function memoryUsage(): string;
}
