<?php

namespace SummerCraft\Service\Logger;

interface TaggedLogger extends Logger
{
    /**
     * System is unusable.
     */
    public function taggedEmergency(string $tag, string $message, array $context = []): void;

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     */
    public function taggedAlert(string $tag, string $message, array $context = []): void;

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     */
    public function taggedCritical(string $tag, string $message, array $context = []): void;

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     */
    public function taggedError(string $tag, string $message, array $context = []): void;


    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     */
    public function taggedWarning(string $tag, string $message, array $context = []): void;

    /**
     * Normal but significant events.
     */
    public function taggedNotice(string $tag, string $message, array $context = []): void;

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     */
    public function taggedInfo(string $tag, string $message, array $context = []): void;

    /**
     * Detailed debug information.
     */
    public function taggedDebug(string $tag, string $message, array $context = []): void;
}
