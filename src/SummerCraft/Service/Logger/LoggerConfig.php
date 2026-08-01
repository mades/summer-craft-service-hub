<?php

namespace SummerCraft\Service\Logger;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;
use SummerCraft\Core\Context\ApplicationContext;

class LoggerConfig implements SharedComponent
{
    public string $threshold = 'info';
    public string $directory;
    public string $byFile = '{#tag}_{#level}';
    public string $datePrepend = 'Y-m';
    public int $filePermission = 0644;
    public string $dateFormat = 'Y-m-d H:i:s';
    public string $defaultLevel = 'info';

    public bool $holdToViewMessages = false;

    /**
     * StdOutLogger only. Monolog-style convention: split by severity between
     * STDOUT/STDERR rather than writing everything to a single stream.
     */
    public bool $stdoutSplitStreams = true;

    /**
     * StdOutLogger only. Messages at this level or more severe go to STDERR
     * when $stdoutSplitStreams is true; everything less severe goes to STDOUT.
     */
    public string $stdoutSplitThreshold = 'warning';

    /**
     * StdOutLogger only. One of:
     * - 'default': "[level | date] [tag] message\n\ncontext" (current shape)
     * - 'json': one JSON object per line — {"level","date","tag","message","context"}
     * - any other string: a custom template using {level} {date} {tag}
     *   {message} {context} placeholders (context rendered via print_r)
     */
    public string $format = 'default';

    public function __construct(ApplicationContext $applicationContext)
    {
        $this->directory = $applicationContext->getTemporaryPath() . 'logs/';
    }


}