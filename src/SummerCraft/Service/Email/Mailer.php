<?php

namespace SummerCraft\Service\Email;

interface Mailer
{
    public function getLastStatusMessage(): string;

    /**
     * @param array $emails [email1, email2, email3]
     * @param array $ccEmails [email1, email2, email3]
     */
    public function sendFromSite(
        string $subject,
        string $content,
        array $emails,
        array $ccEmails = [],
        bool $queued = true,
        bool $force = false
    ): bool;

    /**
     * @param array $from [email => name]
     * @param array $emails [email1, email2, email3]
     * @param array $ccEmails [email1, email2, email3]
     */
    public function send(
        string $subject,
        string $content,
        array $from,
        array $emails,
        array $ccEmails,
        bool $queued,
        bool $force
    ): bool;
}
