<?php

namespace SummerCraft\Service\Console;

use SummerCraft\Service\Logger\Logger;
use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class DefaultConsole implements Console, SharedComponent
{
    protected string $currentLineOutput = '';

    public function __construct(
        protected ?Logger $log = null,
    ) {
    }

    public function log(mixed $message, bool $clearLine = false, bool $endLine = true): void
    {
        if ($clearLine){
            print(str_pad('', mb_strlen($this->currentLineOutput), chr(0x08)));
            print(str_pad('', mb_strlen($this->currentLineOutput), ' '));
            print(str_pad('', mb_strlen($this->currentLineOutput), chr(0x08)));
            $this->currentLineOutput = '';

            //$numNewLines = substr_count($str, "\n");
            //echo chr(27) . "[0G"; // Set cursor to first column
            //echo $str;
            //echo chr(27) . "[" . $numNewLines ."A"; // Set cursor up x lines


        }
        if(is_object($message) || is_array($message)){
            $message = print_r($message,true);
        } else {
            $message = (string) $message;
        }
        $this->currentLineOutput .= $message;
        print($message);
        if ($endLine){
            print("\r\n");
            $this->currentLineOutput = '';
            if ($this->log) {
                $this->log->info($message, ['tag' => 'console']);
            }
        }
    }


    protected float $startTime;

    public function logWithProgress(string $message, int $now, int $total): void
    {
        $this->log(
            $this->progressRemaining($now, $total, true) . " | " . $message,
            true,
            false
        );
    }

    public function progressRemaining(int $now, int $total, bool $showCounters = false): string
    {
        if (!isset($this->startTime) || $now === 0) {
            $this->startTime = microtime(true);
            return '???';
        }
        $nowTime = microtime(true);
        $done = $nowTime - $this->startTime;

        if ($total === 0) {
            $total = 1;
        }
        $percentageString = number_format($now * 100 / $total, 1) . '%';
        $totalTime = $done * $total / $now;
        $remainTime = $done * ($total - $now) / $now;
        $doneString = $this->timeSimpleToString($this->secondsToTimeSimple((int)$done));
        $totalString = $this->timeSimpleToString($this->secondsToTimeSimple((int)$totalTime));
        $remainString = $this->timeSimpleToString($this->secondsToTimeSimple((int)$remainTime));
        return
            ($showCounters ? "{$now}/{$total}. " : '')
            . "ETA: $doneString / $totalString | $percentageString | $remainString";
    }

    /** secondsToTimeSimple
     * Получение интервала в удобном виде количество дней, часов, минут, секунд
     * @return array $times:
     * $times[0] - секунды
     * $times[1] - минуты
     * $times[2] - часы
     * $times[3] - дни
     * $times[4] - года
     */
    protected function secondsToTimeSimple(int $seconds): array
    {
        if ($seconds < 0) {
            $seconds = - $seconds;
        }
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

    protected function timeSimpleToString(array $r): string
    {
        return trim(''
            . ($r[4] ? $r[4].'Y' : '')
            . ($r[3]? ' ' . $r[3].'D' : '')
            . ($r[2]? ' ' . $r[2].'H' : '')
            . ($r[1]? ' ' . $r[1].'m' : '')
            . ($r[0]? ' ' . $r[0].'s' : '')
        );
    }

    public function memoryUsage(): string
    {
        return sprintf('Memory:%dMB', memory_get_usage() / (1024 * 1024));
    }
}
