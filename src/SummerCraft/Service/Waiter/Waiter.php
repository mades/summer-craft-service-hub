<?php

namespace SummerCraft\Service\Waiter;

interface Waiter
{
    public function sleep(int $seconds): void;

    public function usleep(int $microseconds): void;
}
