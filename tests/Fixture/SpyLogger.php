<?php

namespace SummerCraft\Service\Tests\Fixture;

use Stringable;

/** Logger double that records warning() calls for assertions */
class SpyLogger extends NullLogger
{
    /** @var array<int, array{message: string, context: array}> */
    public array $warnings = [];

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->warnings[] = ['message' => (string)$message, 'context' => $context];
    }
}
