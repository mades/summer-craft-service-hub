<?php
namespace SummerCraft\Service\Database\Handlers;

use SummerCraft\Service\Database\Config\DatabaseConnectionConfig;
use SummerCraft\Service\Logger\Logger;
use SummerCraft\Service\Logger\LoggerContext;

class DatabaseProfiler
{
    private array $benchmarkData = [];

    private float $isLogIfLonger;

    private bool $isDebug;

    private bool $isLogTrace;

    public bool $isEnabled;

    private float $startTime = 0.0;

    private float $executeTime = 0.0;

    public function __construct(
        private Logger $log,
        DatabaseConnectionConfig $databaseConfig,
    ) {
        $this->isLogIfLonger = $databaseConfig->logIfLonger;
        $this->isDebug = $databaseConfig->debug;
        $this->isEnabled = ($this->isDebug === true) || ($this->isLogIfLonger > 0);
        $this->isLogTrace = $databaseConfig->logTrace;
    }

    public function preExecution(): void
    {
        if (!$this->isEnabled) {
            return;
        }
        $this->startTime = microtime(true);
    }

    public function preFormatting(): void
    {
        if (!$this->isEnabled) {
            return;
        }
        $this->executeTime = microtime(true);
    }

    public function done($query, $params): void
    {
        if (!$this->isEnabled) {
            return;
        }
        $finishTime = microtime(true);
        $executeTime = $this->executeTime - $this->startTime;
        $formattingTime = $finishTime - $this->executeTime;
        $totalTime = $executeTime + $formattingTime;

        if ($this->isDebug === true) {
            $this->benchmarkData[] = [
                'query' => $query,
                'params' => $params,
                'time_execute' => $executeTime,
                'time_format' => $formattingTime,
                'time_total' => $totalTime,
            ];
        }

        if ($this->isLogIfLonger > 0 && ($totalTime > $this->isLogIfLonger / 1000)) {
            $logContext = [
                LoggerContext::TAG => 'DB_QUERY_TIME',
                'query' => $query,
                'params' => $params,
                'time_execute' => $executeTime,
                'time_format' => $formattingTime,
                'time_total' => $totalTime,
            ];
            if ($this->isLogTrace === true) {
                $logContext[LoggerContext::WITH_TRACE] = true;
            }
            $this->log->warning("Database query time is {$totalTime}", $logContext);
        }
    }

    public function getBenchmarkData(): array
    {
        return $this->benchmarkData;
    }
}
