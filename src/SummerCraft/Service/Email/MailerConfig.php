<?php

namespace SummerCraft\Service\Email;

use SummerCraft\Service\Database\RelationalDatabaseHandler;

abstract class MailerConfig
{
    public string $databaseHandlerServiceName = RelationalDatabaseHandler::class;

    public string $siteRobotEmail = 'robot@app.local';
    public string $siteRobotName = 'CodeHuiter Robot Name';
    public bool $queueForce = false;
    public array $senderConfig = [

    ];
}