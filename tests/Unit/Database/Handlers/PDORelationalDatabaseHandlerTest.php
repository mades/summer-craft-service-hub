<?php

namespace SummerCraft\Service\Tests\Unit\Database\Handlers;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Database\Config\DatabaseConnectionConfig;
use SummerCraft\Service\Database\Exception\DatabaseException;
use SummerCraft\Service\Database\Handlers\PDORelationalDatabaseHandler;
use SummerCraft\Service\Tests\Fixture\NullLogger;

class PDORelationalDatabaseHandlerTest extends TestCase
{
    private function config(): DatabaseConnectionConfig
    {
        $config = new DatabaseConnectionConfig();
        $config->dsn = 'sqlite::memory:';
        $config->persistent = false;
        $config->username = null;
        $config->password = null;

        return $config;
    }

    private function handler(?DatabaseConnectionConfig $config = null): PDORelationalDatabaseHandler
    {
        $config ??= new DatabaseConnectionConfig();
        $config->dsn = 'sqlite::memory:';
        $config->persistent = false;
        $config->username = null;
        $config->password = null;

        return new PDORelationalDatabaseHandler(new NullLogger(), $config);
    }

    /**
     * disconnect() used to run Postgres-only SQL
     * (pg_terminate_backend) against a MySQL/SQLite connection and always threw.
     */
    public function testDisconnectDoesNotThrow(): void
    {
        $handler = $this->handler();

        $handler->disconnect();

        $this->expectNotToPerformAssertions();
    }

    public function testUsingHandlerAfterDisconnectFailsCleanly(): void
    {
        $handler = $this->handler();
        $handler->disconnect();

        $this->expectException(\Error::class);
        $handler->quote('x');
    }

    /**
     * field/table names used to be interpolated into
     * SQL (wrapped in backticks) without any validation — only bound values were safe.
     */
    public function testSelectWhereRejectsMaliciousFieldName(): void
    {
        $handler = $this->handler();
        $handler->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

        $this->expectException(DatabaseException::class);
        $handler->selectWhere('users', ['name`; DROP TABLE users;--' => 'x']);
    }

    public function testSelectWhereRejectsMaliciousTableName(): void
    {
        $handler = $this->handler();

        $this->expectException(DatabaseException::class);
        $handler->selectWhere('users`; DROP TABLE users;--', ['id' => 1]);
    }

    public function testUpdateRejectsMaliciousSetKey(): void
    {
        $handler = $this->handler();
        $handler->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

        $this->expectException(DatabaseException::class);
        $handler->update('users', ['id' => 1], ['name`=`x`,`id' => 'evil']);
    }

    public function testInsertRejectsMaliciousInsertKey(): void
    {
        $handler = $this->handler();
        $handler->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

        $this->expectException(DatabaseException::class);
        $handler->insert('users', ['name`)VALUES(`x' => 'evil'], false);
    }

    public function testSelectWhereRejectsMaliciousOrderField(): void
    {
        $handler = $this->handler();
        $handler->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

        $this->expectException(DatabaseException::class);
        $handler->selectWhere('users', [], ['order' => ['id; DROP TABLE users;--' => 'asc']]);
    }

    public function testSelectWhereStillWorksWithLegitimateFieldsAndTable(): void
    {
        $handler = $this->handler();
        $handler->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $handler->insert('users', ['id' => 1, 'name' => 'Alice'], false);

        $result = $handler->selectWhere('users', ['name' => 'Alice']);

        self::assertCount(1, $result);
        self::assertSame('Alice', $result[0]['name']);
    }

    /**
     * An empty 'IN' list used to compile to
     * `` `field` IN() `` — a SQL syntax error on MariaDB (SQLite happens to
     * accept it, so this only asserts the intended "always false" semantics,
     * not the raw SQL — the syntax-error side was confirmed separately against
     * real MariaDB).
     */
    public function testSelectWhereWithEmptyInReturnsNoRows(): void
    {
        $handler = $this->handler();
        $handler->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $handler->insert('users', ['id' => 1, 'name' => 'Alice'], false);

        $result = $handler->selectWhere('users', ['id' => ['IN' => []]]);

        self::assertCount(0, $result);
    }

    /**
     * An empty exclusion list
     * means "exclude nothing" — every row should still match.
     */
    public function testSelectWhereWithEmptyNotInReturnsAllRows(): void
    {
        $handler = $this->handler();
        $handler->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $handler->insert('users', ['id' => 1, 'name' => 'Alice'], false);

        $result = $handler->selectWhere('users', ['id' => ['NOT_IN' => []]]);

        self::assertCount(1, $result);
    }

    /**
     * The Backoffice search code
     * builds a comma-joined field-name key (e.g. 'name,email') to search LIKE
     * across several columns for the same value. This is a documented
     * arrayCompile() primitive — each comma-separated identifier is validated
     * individually after explode(), never as one joined string — so it must
     * not throw DatabaseException and must OR the columns together. Also
     * confirmed separately against real MariaDB.
     */
    public function testSelectWhereWithCommaJoinedKeySearchesAcrossFields(): void
    {
        $handler = $this->handler();
        $handler->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
        $handler->insert('users', ['id' => 1, 'name' => 'Alice', 'email' => 'nomatch@example.com'], false);
        $handler->insert('users', ['id' => 2, 'name' => 'Nobody', 'email' => 'bob@example.com'], false);
        $handler->insert('users', ['id' => 3, 'name' => 'Carol', 'email' => 'carol@example.com'], false);

        $result = $handler->selectWhere('users', ['name,email' => ['like' => '%bob%']]);

        self::assertCount(1, $result);
        self::assertSame(2, (int)$result[0]['id']);
    }

    private function reconnectDecision(DatabaseConnectionConfig $config, int $driverCode, bool $inTransaction = false): bool
    {
        $handler = $this->handler($config);
        if ($inTransaction) {
            $handler->transactionStart();
        }

        $exception = new \PDOException('however the server worded it');
        $exception->errorInfo = ['HY000', $driverCode, 'message text'];

        $method = new \ReflectionMethod($handler, 'reconnectProblem');

        return $method->invoke($handler, $exception);
    }

    /**
     * A dropped connection is recognised by the driver code, not by
     * matching message text — the wording varies by server version, and a
     * missed match is silent, looking like an ordinary query failure.
     */
    public function testReconnectsOnConnectionLostDriverCodes(): void
    {
        foreach ([2006, 2013] as $driverCode) {
            $config = $this->config();
            $config->reconnect = true;

            self::assertTrue(
                $this->reconnectDecision($config, $driverCode),
                "driver code $driverCode should count as a lost connection"
            );
        }
    }

    public function testDoesNotReconnectOnOrdinaryQueryErrors(): void
    {
        $config = $this->config();
        $config->reconnect = true;

        // 1064 is a syntax error: the connection is healthy, a retry is pointless
        self::assertFalse($this->reconnectDecision($config, 1064));
    }

    public function testDoesNotReconnectWhenDisabled(): void
    {
        $config = $this->config();
        $config->reconnect = false;

        self::assertFalse($this->reconnectDecision($config, 2006));
    }

    /**
     * The server rolls the transaction back when the connection
     * drops, so replaying just the failed statement on a fresh connection
     * would commit it on its own — a partial write with nothing in the log.
     */
    public function testDoesNotReconnectInsideTransaction(): void
    {
        $config = $this->config();
        $config->reconnect = true;

        self::assertFalse($this->reconnectDecision($config, 2006, inTransaction: true));
    }
}
