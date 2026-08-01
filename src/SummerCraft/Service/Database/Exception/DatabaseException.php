<?php
namespace SummerCraft\Service\Database\Exception;

use PDOException;
use RuntimeException;
use SummerCraft\Service\Database\Config\DatabaseConnectionConfig;
use Throwable;

class DatabaseException extends RuntimeException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 500, $previous);
    }

    public static function onPDOConnect(PDOException $exception, DatabaseConnectionConfig $config): self
    {
        $clonedConfig = clone $config;
        $clonedConfig->password = 'secret_of_' . md5($config->password);
        return new self($exception->getMessage() . print_r($clonedConfig, true), $exception);
    }

    public static function onDirectionStartTransactionInTransaction(): self
    {
        return new self('Direction start of transaction is not supported');
    }

    public static function onInsertWithoutSet(): self
    {
        return new self('Trying to insert with no set');
    }

    public static function onInvalidIdentifier(string $identifier): self
    {
        return new self("Invalid SQL identifier [$identifier]: expected a plain column/table name");
    }
}
