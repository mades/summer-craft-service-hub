<?php

namespace SummerCraft\Service\Waiter;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class DefaultWaiter implements Waiter, SharedComponent
{
    public function sleep(int $seconds): void
    {
        sleep($seconds);
    }

    public function usleep(int $microseconds): void
    {
        usleep($microseconds);
    }

}
