<?php

namespace SummerCraft\Service\Email;

use SummerCraft\Service\Logger\Logger;

abstract class AbstractEmail implements \SummerCraft\Service\Email\Mailer
{
    protected string $lastStatusMessage = '';

    public function __construct(
        protected MailerConfig $config,
        protected Logger $log,
    ) {
    }

    public function getLastStatusMessage(): string
    {
        return $this->lastStatusMessage;
    }

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
    ): bool {
        $from = [$this->config->siteRobotEmail => $this->config->siteRobotName];
        return $this->send($subject, $content, $from, $emails, $ccEmails, $queued, $force);
    }

    /**
     * @param array $from [email => name]
     * @param array $emails [email1, email2, email3]
     * @param array $ccEmails [email1, email2, email3]
     */
    abstract public function send(
        string $subject,
        string $content,
        array $from,
        array $emails,
        array $ccEmails,
        bool $queued,
        bool $force
    ): bool;
}