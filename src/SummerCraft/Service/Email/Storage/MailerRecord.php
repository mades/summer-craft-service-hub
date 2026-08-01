<?php

namespace SummerCraft\Service\Email\Storage;

use SummerCraft\Service\Database\Record;

class MailerRecord extends Record
{
    /** @var int|null */
    public $id;
    /** @var int */
    public $user_id;
    /** @var string */
    public $subject;
    /** @var string */
    public $email;
    /** @var string */
    public $message;
    /** @var string */
    public $created_at;
    /** @var string */
    public $updated_at;
    /** @var int stored as a SQL tinyint flag (0/1), never a native bool */
    public $sended;
}