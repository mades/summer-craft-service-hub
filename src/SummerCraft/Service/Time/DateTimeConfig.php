<?php

namespace SummerCraft\Service\Time;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class DateTimeConfig implements SharedComponent
{
    public string $siteTimezone = 'UTC';
}