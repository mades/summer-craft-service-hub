<?php

namespace SummerCraft\Service\Tests\Unit\Email;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Database\Config\DatabaseConnectionConfig;
use SummerCraft\Service\Database\Handlers\PDORelationalDatabaseHandler;
use SummerCraft\Service\Database\RelationalDatabaseHandler;
use SummerCraft\Service\Email\DefaultMailer;
use SummerCraft\Service\Email\MailerConfig;
use SummerCraft\Service\Email\Storage\MailerRepository;
use SummerCraft\Service\Tests\Fixture\NullLogger;
use SummerCraft\Service\Time\DateTimeConfig;
use SummerCraft\Service\Time\DateTimeService;
use SummerCraft\Service\Time\DefaultDateTimeService;
use SummerCraft\Core\ComponentManaging\Config\Config;
use SummerCraft\Core\ComponentManaging\Config\ComponentConfig;
use SummerCraft\Core\ComponentManaging\ComponentHolder;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\EventDispatcher\DefaultEventDispatcher;
use SummerCraft\Core\EventDispatcher\EventDispatcher;

/**
 * DefaultMailer::sendFromQueue() used to set
 * MailerRecord::$updated_at via fromTime()->toTime() — a raw Unix timestamp int
 * — instead of the SQL datetime string format ("Y-m-d H:i:s") used everywhere
 * else in the codebase (RelationalStorage::save() uses sqlTime() for the same
 * column on every other model). Found via phpstan (assign.propertyType: string
 * property does not accept int).
 */
class DefaultMailerTest extends TestCase
{
    private function handler(): PDORelationalDatabaseHandler
    {
        $config = new DatabaseConnectionConfig();
        $config->dsn = 'sqlite::memory:';
        $config->persistent = false;
        $config->username = null;
        $config->password = null;

        $handler = new PDORelationalDatabaseHandler(new NullLogger(), $config);
        $handler->execute(
            'CREATE TABLE mailer (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                subject TEXT,
                email TEXT,
                message TEXT,
                created_at TEXT,
                updated_at TEXT,
                sended INTEGER
            )'
        );
        return $handler;
    }

    private function mailer(RelationalDatabaseHandler $handler): DefaultMailer
    {
        $mailerConfig = new class extends MailerConfig {
        };

        // RelationalStorage::save() resolves its DB handler, DateTimeService and
        // EventDispatcher lazily through the scope, not via constructor injection
        $config = new Config();
        $config->services[$mailerConfig->databaseHandlerServiceName] = ComponentConfig::forCallback(
            static fn () => $handler,
            PDORelationalDatabaseHandler::class
        );
        $config->services[DateTimeService::class] = ComponentConfig::forClass(DefaultDateTimeService::class);
        $config->services[EventDispatcher::class] = ComponentConfig::forClass(DefaultEventDispatcher::class);
        $repository = new MailerRepository(new RequestScope(new ComponentHolder($config)), $mailerConfig);

        return new DefaultMailer(
            $mailerConfig,
            new NullLogger(),
            new DefaultDateTimeService(new DateTimeConfig()),
            $repository
        );
    }

    public function testQueuedSendStoresUpdatedAtAsSqlDatetimeStringNotRawTimestamp(): void
    {
        $handler = $this->handler();
        $mailer = $this->mailer($handler);

        // updated_at is set unconditionally regardless of whether the underlying
        // mail() call actually succeeds — this test container has no MTA, so
        // mail() itself warns; that's expected and irrelevant to what's under test.
        @$mailer->send(
            'Subject',
            'Body',
            ['robot@app.local' => 'Robot'],
            ['to@example.com'],
            [],
            true,
            true
        );

        $row = $handler->selectOne('SELECT * FROM mailer WHERE email = :email', ['email' => 'to@example.com']);

        self::assertNotNull($row);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['updated_at']);
    }

    /**
     * send() with queued+force used to `return` from
     * inside the foreach right after queuing the first recipient — the rest of
     * $emails never got a queue row at all, a silent loss of mail for any
     * multi-recipient call.
     */
    public function testQueuedSendWithForceQueuesEveryRecipientNotJustTheFirst(): void
    {
        $handler = $this->handler();
        $mailer = $this->mailer($handler);

        @$mailer->send(
            'Subject',
            'Body',
            ['robot@app.local' => 'Robot'],
            ['first@example.com', 'second@example.com', 'third@example.com'],
            [],
            true,
            true
        );

        $rows = $handler->select('SELECT email FROM mailer ORDER BY id');

        self::assertSame(
            ['first@example.com', 'second@example.com', 'third@example.com'],
            array_column($rows, 'email')
        );
    }
}
