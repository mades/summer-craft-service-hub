<?php

namespace SummerCraft\Service\Logger;

interface LoggerContext
{
    public const WITH_TRACE = 'withTrace';
    public const TAG = 'tag';
    public const EXCEPTION = 'exception';
}