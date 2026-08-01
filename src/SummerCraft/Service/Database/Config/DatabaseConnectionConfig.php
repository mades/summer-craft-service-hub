<?php

namespace SummerCraft\Service\Database\Config;

use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;

class DatabaseConnectionConfig implements RequestScopeComponent
{
    public ?string $name = null;
    public string $dsn = 'mysql:host=localhost;dbname=app_db;charset=utf8mb4';
    public bool $persistent = true;
    public ?string $username = 'appuser';
    public ?string $password = 'apppassword';
    public string $charset = 'utf8mb4';
    public string $collate = 'utf8mb4_general_ci';
    public bool $debug = false; // Save in memory data of time executing for totally print page
    public float $logIfLonger = 100.0; // Logging queries if execute time longer than X ms
    public bool $logTrace = true;
    /**
     * Re-open the connection and replay the statement when the server has
     * dropped it (driver codes 2006/2013). On by default: any process that
     * outlives the server's wait_timeout while idle — a queue or cron worker
     * above all — otherwise keeps a dead handle for good. Never applies inside
     * a transaction, which the server has already rolled back by then.
     */
    public bool $reconnect = true;
    /**
     * Refuse to restore a dump into this connection. The maintenance command read
     * this flag for years while the property did not exist, so the guard never held
     * — phpstan reported it and the baseline carried the finding.
     */
    public bool $disableBackupImport = false;
}