<?php

namespace SummerCraft\Service\Email;

use SummerCraft\Service\Email\Sender\EmailSender;
use SummerCraft\Service\Email\Storage\MailerRepository;
use SummerCraft\Service\Logger\Logger;
use SummerCraft\Service\Logger\LoggerContext;
use SummerCraft\Service\Time\DateTimeService;
use Throwable;

class DefaultMailer extends AbstractEmail
{
    protected EmailSender $emailSender;

    public function __construct(
        MailerConfig $config,
        Logger $log,
        protected DateTimeService $date,
        private MailerRepository $mailerRepository,
    ) {
        parent::__construct($config, $log);
    }

    protected function getEmailSender(): EmailSender
    {
        if (!isset($this->emailSender)) {
            $this->emailSender = new EmailSender($this->config->senderConfig);
        }
        return $this->emailSender;
    }

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
    ): bool {
        if ($queued) {
            $queuedIds = [];
            foreach ($emails as $email) {
                /** @var \SummerCraft\Service\Email\Storage\MailerRecord $model */
                $model = \SummerCraft\Service\Email\Storage\MailerRecord::emptyRecord();
                // TODO rewrite by sets ?
                $model->updateModelBySet([
                    'user_id' => 0,
                    'subject' => $subject,
                    'email' => $email,
                    'message' => $content,
                    'sended' => 0,
                ]);
                $this->mailerRepository->save($model);
                $queuedIds[] = $model->id;
            }
            if ($this->config->queueForce || $force) {
                $success = true;
                foreach ($queuedIds as $queuedId) {
                    $success = $this->sendFromQueue(1, $queuedId) && $success;
                }
                return $success;
            }
            return true;
        }

        return $this->innerSend($subject, $content, $from, $emails, $ccEmails);
    }

    /**
     * @param string $subject
     * @param string $content
     * @param array $from [email => name]
     * @param array $emails [email1, email2, email3]
     * @param array $ccEmails [email1, email2, email3]
     */
    protected function innerSend($subject, $content, $from, $emails, $ccEmails): bool
    {
        $success = false;
        $this->lastStatusMessage = '';
        try {
            $sender = $this->getEmailSender();
            if (strpos($content,'<') === 0) {
                $sender->setMailType('html');
            } else {
                $sender->setMailType('text');
            }
            $sender->setSubject($subject);
            $sender->setMessage($content);
            foreach ($from as $email => $name) {
                $sender->setFrom($email, $name);
            }
            $sender->setTo($emails);
            if ($ccEmails) {
                $sender->setCC($ccEmails);
            }
            $success = $sender->send();
        } catch (Throwable $exception) {
            $this->lastStatusMessage .= $exception->getMessage();
        }
        $this->lastStatusMessage .= isset($sender) ? $sender->printDebugger() : '';
        $toEmailsString = '[' . implode(',', $emails) . ']';
        if (!$success) {
            $this->log->warning(
                "Email to $toEmailsString Subject: $subject not sent. " . $this->lastStatusMessage,
                [LoggerContext::TAG => 'MAILER']
            );
            return false;
        }
        $this->log->info(
            "Sent email to $toEmailsString Subject: $subject",
            [LoggerContext::TAG => 'MAILER']
        );
        return true;
    }

    /**
     * @param int $count
     * @param int|null $id
     */
    protected function sendFromQueue($count = 1, $id = null): bool
    {
        $messages = $id
            ? $this->mailerRepository->findWithId($id)
            : $this->mailerRepository->findNext($count);

        $success = false;
        foreach($messages as $message){
            $success = $this->sendFromSite(
                $message->subject,
                $message->message,
                [$message->email],
                [],
                false
            );
            if ($success){
                $message->sended = 1;
            }
            $message->updated_at = $this->date->sqlTime();
            $this->mailerRepository->save($message);
        }
        return $success;
    }
}
