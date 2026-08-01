<?php
namespace SummerCraft\Service\Database\Handlers;

use PDO;
use Pdo\Mysql;
use PDOException;
use PDOStatement;
use SummerCraft\Service\Database\Config\DatabaseConnectionConfig;
use SummerCraft\Service\Database\RelationalDatabaseHandler;
use SummerCraft\Service\Logger\Logger;
use SummerCraft\Service\Modifier\StringModifier;
use SummerCraft\Service\Database\Exception\DatabaseException;
use Throwable;

class PDORelationalDatabaseHandler implements RelationalDatabaseHandler
{
    protected ?PDO $connection = null;

    protected int $transactionLevel = 0;

    protected DatabaseConnectionConfig $config;

    protected DatabaseProfiler $profiler;

    protected Logger $log;

    public function __construct(Logger $log, DatabaseConnectionConfig $databaseConfig)
    {
        $this->log = $log;
        $this->profiler = new DatabaseProfiler($log, $databaseConfig);
        $this->connect($databaseConfig);
    }

    protected function connect(DatabaseConnectionConfig $config): void
    {
        $this->config = $config;
        $options = [
            PDO::ATTR_PERSISTENT => $config->persistent,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];
        $isSqlite = StringModifier::pos($config->dsn, 'sqlite:') === 0;
        if ($config->charset && $config->collate && !$isSqlite) {
            // Pdo\Mysql, not PDO::MYSQL_*: the old constants are deprecated as of 8.5,
            // and the package requires 8.4 where the driver classes exist
            $options[Mysql::ATTR_INIT_COMMAND] = "SET NAMES {$config->charset} COLLATE {$config->collate}";
        }
        try {
            $this->connection = new PDO($config->dsn, $config->username, $config->password, $options);
        } catch (PDOException $exception) {
            throw DatabaseException::onPDOConnect($exception, $config);
        }
        if ($config->persistent) {
            if ($config->charset && $config->collate && !$isSqlite) {
                $this->connection->exec("SET NAMES {$config->charset} COLLATE {$config->collate}");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function quote(string $string): string
    {
        return $this->connection->quote($string);
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        // No explicit "disconnect" query needed for MySQL/SQLite — PDO closes the
        // connection once nothing references it anymore.
        $this->connection = null;
    }

    /**
     * MySQL/MariaDB driver codes for a connection that is no longer usable:
     * 2006 "server has gone away", 2013 "lost connection".
     */
    private const CONNECTION_LOST_DRIVER_CODES = [2006, 2013];

    private function reconnectProblem(PDOException $exception): bool
    {
        if (!$this->config->reconnect) {
            return false;
        }

        // Never mid-transaction: the server already rolled it back when the
        // connection dropped, so replaying only the failed statement would
        // commit it on its own and leave a partial write with no error anywhere.
        if ($this->transactionLevel > 0) {
            return false;
        }

        // Matched on the driver code rather than the message text: the wording
        // varies by server version and locale, and a missed match here is
        // silent — it just looks like an ordinary query failure.
        $driverCode = (int)($exception->errorInfo[1] ?? 0);
        if (!in_array($driverCode, self::CONNECTION_LOST_DRIVER_CODES, true)) {
            return false;
        }

        // recovering silently would hide a flapping server: the queries all
        // succeed, so nothing else in the system ever reports it
        $this->log->warning('Database connection was lost, reconnecting', [
            'driverCode' => $driverCode,
            'connection' => $this->config->name,
        ]);

        $this->connection = null;
        $this->connect($this->config);
        return true;
    }

    private function executeStatement(string $query, array $params): PDOStatement
    {
        try {
            $statement = $this->connection->prepare($query);
            $statement->execute($params);
        } catch (PDOException $exception) {
            if (!$this->reconnectProblem($exception)) {
                throw $this->pdoException($exception, $query, $params);
            }
            $statement = $this->connection->prepare($query);
            $statement->execute($params);
        } catch (Throwable $event) {
            throw $this->pdoException($event, $query, $params);
        }
        return $statement;
    }

    /**
     * {@inheritdoc}
     */
    public function selectObjects(?string $className, string $query, array $params = [], ?string $fieldAsKey = null): array
    {
        $this->profiler->isEnabled && $this->profiler->preExecution();
        $statement = $this->executeStatement($query, $params);
        $this->profiler->isEnabled && $this->profiler->preFormatting();

        if ($className === null) {
            $statement->setFetchMode(PDO::FETCH_OBJ);
        } else {
            $statement->setFetchMode(PDO::FETCH_CLASS, $className);
        }
        $result = $statement->fetchAll();
        if ($fieldAsKey !== null) {
            $fieldAsKeyResult = [];
            foreach ($result as $resultItem) {
                $fieldAsKeyResult[$resultItem->$fieldAsKey] = $resultItem;
            }
            $result = $fieldAsKeyResult;
        }

        $this->profiler->isEnabled && $this->profiler->done($query, $params);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function selectOneObject(?string $className, string $query, array $params = []): ?object
    {
        $this->profiler->isEnabled && $this->profiler->preExecution();
        $statement = $this->executeStatement($query, $params);
        $this->profiler->isEnabled && $this->profiler->preFormatting();

        if ($className === null) {
            $statement->setFetchMode(PDO::FETCH_OBJ);
        } else {
            $statement->setFetchMode(PDO::FETCH_CLASS, $className);
        }
        $result = $statement->fetch();

        $this->profiler->isEnabled && $this->profiler->done($query, $params);

        if (!$result) $result = null;
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function select(string $query, array $params = [], ?string $fieldAsKey = null): array
    {
        $this->profiler->isEnabled && $this->profiler->preExecution();
        $statement = $this->executeStatement($query, $params);
        $this->profiler->isEnabled && $this->profiler->preFormatting();

        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $result = $statement->fetchAll();
        if ($fieldAsKey !== null) {
            $fieldAsKeyResult = [];
            foreach ($result as $resultItem) {
                $fieldAsKeyResult[$resultItem[$fieldAsKey]] = $resultItem;
            }
            $result = $fieldAsKeyResult;
        }

        $this->profiler->isEnabled && $this->profiler->done($query, $params);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function selectOne(string $query, array $params = []): ?array
    {
        $this->profiler->isEnabled && $this->profiler->preExecution();
        $statement = $this->executeStatement($query, $params);
        $this->profiler->isEnabled && $this->profiler->preFormatting();

        // Format
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $result = $statement->fetch();

        $this->profiler->isEnabled && $this->profiler->done($query, $params);

        if (!$result) $result = null;
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function selectOneField(string $query, array $params = [], ?string $field = null): ?string
    {
        $this->profiler->isEnabled && $this->profiler->preExecution();
        $statement = $this->executeStatement($query, $params);
        $this->profiler->isEnabled && $this->profiler->preFormatting();

        $statement->setFetchMode(PDO::FETCH_BOTH);
        $ret = $statement->fetch();
        $result = null;
        if ($ret) {
            if ($field === null) {
                $result = $ret[0];
            } else {
                $result = $ret[$field];
            }
        }

        $this->profiler->isEnabled && $this->profiler->done($query, $params);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function selectField(string $query, array $params, string $field, ?string $fieldAsKey = null): array
    {
        $this->profiler->isEnabled && $this->profiler->preExecution();
        $statement = $this->executeStatement($query, $params);
        $this->profiler->isEnabled && $this->profiler->preFormatting();

        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $tmpResult = $statement->fetchAll();

        $result = [];
        if ($fieldAsKey !== null) {
            foreach ($tmpResult as $resultItem) {
                $result[$resultItem[$fieldAsKey]] = $resultItem[$field];
            }
        } else {
            foreach ($tmpResult as $resultItem) {
                $result[] = $resultItem[$field];
            }
        }

        $this->profiler->isEnabled && $this->profiler->done($query, $params);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function execute(string $query, array $params = [], bool $returnInsertedId = false): string
    {
        $this->profiler->isEnabled && $this->profiler->preExecution();

        $result = null;
        if ($returnInsertedId === true) {
            // Execute
            if ($this->transactionLevel === 0) {
                $this->connection->beginTransaction();
            }
            $this->executeStatement($query, $params);
            // Format
            $result = $this->connection->lastInsertId();
            if ($this->transactionLevel === 0) {
                $this->connection->commit();
            }
            if ($this->transactionLevel === 0 && $this->connection->inTransaction()) {
                $this->connection->rollBack();
                throw DatabaseException::onDirectionStartTransactionInTransaction();
            }
        } else {
            $statement = $this->executeStatement($query, $params);
            // Format
            $result = $statement->rowCount();
        }

        $this->profiler->isEnabled && $this->profiler->preFormatting();
        $this->profiler->isEnabled && $this->profiler->done($query, $params);

        return (string)$result;
    }

    /**
     * @inheritDoc
     */
    public function selectWhereObjects(?string $className, string $table, array $where, array $opt = []): array
    {
        self::validateIdentifier($table);
        $compiled = self::arrayCompile(
            $where, null, null,
            ($opt['order'] ?? null),
            ($opt['limit'] ?? null)
        );
        return $this->selectObjects(
            $className,
            "SELECT * FROM `{$table}` \n WHERE {$compiled['where']} {$compiled['order']} {$compiled['limit']}",
            $compiled['params'],
            ($opt['key'] ?? null)
        );
    }

    /**
     * @inheritDoc
     */
    public function selectWhereOneObject(?string $className, string $table, array $where, array $opt = []): ?object
    {
        self::validateIdentifier($table);
        $compiled = self::arrayCompile(
            $where, null, null, ($opt['order'] ?? null), null
        );
        return $this->selectOneObject(
            $className,
            "SELECT * FROM `{$table}` \n WHERE {$compiled['where']} {$compiled['order']} LIMIT 0,1",
            $compiled['params']
        );
    }

    /**
     * @inheritDoc
     */
    public function selectWhere(string $table, array $where, array $opt = []): array
    {
        self::validateIdentifier($table);
        $compiled = self::arrayCompile(
            $where, null, null,
            ($opt['order'] ?? null),
            ($opt['limit'] ?? null)
        );
        return $this->select(
            "SELECT * FROM `{$table}` \n WHERE {$compiled['where']} {$compiled['order']} {$compiled['limit']}",
            $compiled['params'],
            ($opt['key'] ?? null)
        );
    }

    /**
     * @inheritDoc
     */
    public function selectCountWhere(string $table, array $where): int
    {
        self::validateIdentifier($table);
        $compiled = self::arrayCompile($where, null, null, null, null);
        return (int)$this->selectOneField(
            "SELECT COUNT(*) as counter FROM `{$table}` \n WHERE {$compiled['where']} {$compiled['order']} {$compiled['limit']}",
            $compiled['params'],
            'counter'
        );
    }

    /**
     * @inheritDoc
     */
    public function selectWhereOne(string $table, array $where, array $opt = []): ?array
    {
        self::validateIdentifier($table);
        $compiled = self::arrayCompile(
            $where, null, null, ($opt['order'] ?? null), null
        );
        return $this->selectOne(
            "SELECT * FROM `{$table}` \n WHERE {$compiled['where']} {$compiled['order']} LIMIT 0,1",
            $compiled['params']
        );
    }

    /**
     * @inheritDoc
     */
    public function selectFieldWhere(string $table, array $where, string $field, array $opt = []): array
    {
        self::validateIdentifier($table);
        $compiled = self::arrayCompile(
            $where, null, null,
            ($opt['order'] ?? null),
            ($opt['limit'] ?? null)
        );
        return $this->selectField(
            "SELECT * FROM `{$table}` \n WHERE {$compiled['where']} {$compiled['order']} {$compiled['limit']}",
            $compiled['params'],
            $field,
            ($opt['key'] ?? null)
        );
    }

    /**
     * @inheritDoc
     */
    public function insert(string $table, array $set, bool $returnInsertId): string
    {
        self::validateIdentifier($table);
        if (!$set) {
            throw DatabaseException::onInsertWithoutSet();
        }
        $compiled = self::arrayCompile(null, null, $set, null, null);
        return $this->execute(
            "INSERT INTO `{$table}` \n ({$compiled['insert_keys']}) \n VALUES ({$compiled['insert_values']})",
            $compiled['params'],
            $returnInsertId
        );
    }

    /**
     * @inheritDoc
     */
    public function update(string $table, array $where, array $set): int
    {
        self::validateIdentifier($table);
        if (!$set) {
            return 0;
        }
        $compiled = self::arrayCompile($where, $set, null, null, null);
        return (int)$this->execute(
            "UPDATE `{$table}` \n SET {$compiled['set']} \n WHERE {$compiled['where']}",
            $compiled['params']
        );
    }

    /**
     * @inheritDoc
     */
    public function delete(string $table, array $where): int
    {
        self::validateIdentifier($table);
        $compiled = self::arrayCompile($where, null, null, null, null);
        /** @noinspection SqlWithoutWhere */
        return (int)$this->execute(
            "DELETE FROM `{$table}` \n WHERE {$compiled['where']}",
            $compiled['params']
        );
    }

    public function transactionStart(): void
    {
        if ($this->transactionLevel === 0) {
            $this->connection->beginTransaction();
            $this->transactionLevel++;
        }
    }

    public function transactionCommit(): void
    {
        if ($this->transactionLevel > 0) {
            $this->transactionLevel--;
            if ($this->transactionLevel === 0) {
                $this->connection->commit();
            }
        }
    }

    public function transactionRollBack(): void
    {
        if ($this->transactionLevel > 0) {
            $this->connection->rollBack();
            $this->transactionLevel = 0;
        }
    }

    public function getBenchmarkData(): array
    {
        return $this->profiler->getBenchmarkData();
    }

    private function pdoException(Throwable $exception, string $query, array $params): PDOException
    {
        return new PDOException("{$exception->getMessage()} \n with query: '$query' with params: " . json_encode($params), 0, $exception);
    }

    private static function validateIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw DatabaseException::onInvalidIdentifier($identifier);
        }
    }

    /**
     * @param array|null $whereArray [
     *          'filed1' => equalValue,
     *          'field1,field2' => equal one of field. field
     *          'field1' => [orEqual1, orEqual2]
     *          'field1' => ['>' => value]
     *          'field1' => ['>=' => value]
     *          'field1' => ['<' => value]
     *          'field1' => ['<=' => value]
     *          'field1' => ['!=' => value]
     *          'field1' => ['like' => value]
     * ]
     * @param array|null $setArray
     * @param array|null $insertArray
     * @param array|null $orderArray ['field1' => 'asc', 'field2' => 'desc']
     * @param array|null $limitArray [count => int or 'all', from => int] or [page => int, per_page => int]
     * @return array ['where' => string, 'set' => string, 'insert' => string, 'params' => array]
     */
    private static function arrayCompile(
        ?array $whereArray,
        ?array $setArray = null,
        ?array $insertArray = null,
        ?array $orderArray = null,
        ?array $limitArray = null
    ): array {
        $result = [];
        $pdoParams = [];

        if ($whereArray) {
            $sqlWherePartArray = [];
            foreach ($whereArray as $keys => $value) {
                $sqlWhereKeyPartArray = [];
                $keysArray = explode(',', $keys);
                $sqlWhereKeyPartArrayImplodeValue = 'OR';
                foreach ($keysArray as $strKey) {
                    self::validateIdentifier($strKey);
                    if (is_array($value)) {
                        $specialWhere = false;
                        if (isset($value['and_required'])) {
                            $sqlWhereKeyPartArrayImplodeValue = 'AND';
                        }
                        if (isset($value['>'])) {
                            $sqlWhereKeyPartArray[] = " `{$strKey}` > :w_{$strKey}_more ";
                            $pdoParams[":w_{$strKey}_more"] = $value['>'];
                            $specialWhere = true;
                        }
                        if (isset($value['>='])) {
                            $sqlWhereKeyPartArray[] = " `{$strKey}` >= :w_{$strKey}_more_equal ";
                            $pdoParams[":w_{$strKey}_more_equal"] = $value['>='];
                            $specialWhere = true;
                        }
                        if (isset($value['<'])) {
                            $sqlWhereKeyPartArray[] = " `{$strKey}` < :w_{$strKey}_less ";
                            $pdoParams[":w_{$strKey}_less"] = $value['<'];
                            $specialWhere = true;
                        }
                        if (isset($value['<='])) {
                            $sqlWhereKeyPartArray[] = " `{$strKey}` <= :w_{$strKey}_less_equal ";
                            $pdoParams[":w_{$strKey}_less_equal"] = $value['<='];
                            $specialWhere = true;
                        }
                        if (isset($value['!='])) {
                            $sqlWhereKeyPartArray[] = " `{$strKey}` != :w_{$strKey}_not_equal ";
                            $pdoParams[":w_{$strKey}_not_equal"] = $value['!='];
                            $specialWhere = true;
                        }
                        if (isset($value['IN'])) {
                            $inParamsKeys = [];
                            foreach ($value['IN'] as $inIndex => $inValue) {
                                $inParamKey = ":w_{$strKey}_in_{$inIndex}";
                                $inParamsKeys[] = $inParamKey;
                                $pdoParams[$inParamKey] = $inValue;
                            }
                            $sqlWhereKeyPartArray[] = $inParamsKeys
                                ? " `{$strKey}` IN( " . implode(' , ', $inParamsKeys) . " ) "
                                : ' 0 ';
                            $specialWhere = true;
                        }
                        if (isset($value['NOT_IN'])) {
                            $inParamsKeys = [];
                            foreach ($value['NOT_IN'] as $inIndex => $inValue) {
                                $inParamKey = ":w_{$strKey}_not_in_{$inIndex}";
                                $inParamsKeys[] = $inParamKey;
                                $pdoParams[$inParamKey] = $inValue;
                            }
                            $sqlWhereKeyPartArray[] = $inParamsKeys
                                ? " `{$strKey}` NOT IN( " . implode(' , ', $inParamsKeys) . " ) "
                                : ' 1 ';
                            $specialWhere = true;
                        }
                        if (isset($value['like'])) {
                            $sqlWhereKeyPartArray[] = " `{$strKey}` LIKE :w_{$strKey}_like ";
                            $pdoParams[":w_{$strKey}_like"] = $value['like'];
                            $specialWhere = true;
                        }
                        if (!$specialWhere) {
                            $tmpSqlArr = [];
                            foreach ($value as $valueIndex => $valueItem) {
                                $tmpSqlArr[] = " :w_{$strKey}_{$valueIndex} ";
                                $pdoParams[":w_{$strKey}_{$valueIndex}"] = $valueItem;
                            }
                            if ($tmpSqlArr) {
                                $sqlWhereKeyPartArray[] = " `{$strKey}` IN(" . implode(',', $tmpSqlArr) . ') ';
                            } else {
                                $sqlWhereKeyPartArray[] = ' 0 ';
                            }
                        }
                    } else {
                        $sqlWhereKeyPartArray[] = " `{$strKey}` = :w_{$strKey} ";
                        $pdoParams[":w_{$strKey}"] = $value;
                    }
                }
                $sqlWherePartArray[] = implode(" $sqlWhereKeyPartArrayImplodeValue ", $sqlWhereKeyPartArray);
            }
            $result['where'] = implode(' AND ', $sqlWherePartArray);
        } else {
            $result['where'] = ' 1 ';
        }

        if ($setArray) {
            $sqlSetPartArray = [];
            foreach ($setArray as $strKey => $value) {
                self::validateIdentifier($strKey);
                $sqlSetPartArray[] = " `{$strKey}` = :s_{$strKey} ";
                $pdoParams[":s_{$strKey}"] = $value;
            }
            $result['set'] = implode(' , ', $sqlSetPartArray);
        }

        if ($insertArray) {
            $sqlInsertKeysPartArray = [];
            $sqlInsertValuesPartArray = [];
            foreach ($insertArray as $strKey => $value) {
                self::validateIdentifier($strKey);
                $sqlInsertKeysPartArray[] = " `{$strKey}` ";
                $sqlInsertValuesPartArray[] = " :i_{$strKey} ";
                $pdoParams[":i_{$strKey}"] = $value;
            }
            $result['insert_keys'] = implode(' , ', $sqlInsertKeysPartArray);
            $result['insert_values'] = implode(' , ', $sqlInsertValuesPartArray);
        }

        $result['order'] = $orderArray ? self::sqlOrder($orderArray) : '';
        $result['limit'] = $limitArray ? self::sqlLimit($limitArray) : '';

        $result['params'] = $pdoParams;

        return $result;
    }

    /**
     * @param array $options ['field1' => 'asc', 'field2' => 'desc']
     */
    private static function sqlOrder(array $options): string
    {
        $orderArrays = [];
        foreach ($options as $field => $orderValue) {
            $orderValue = strtolower($orderValue);
            if (is_string($field) && in_array($orderValue, ['asc', 'desc'], true)) {
                self::validateIdentifier($field);
                $orderArrays[] = " `{$field}` " . ($orderValue === 'asc' ? 'ASC' : 'DESC');
            }
        }
        if (!$orderArrays)  {
            return '';
        }
        return ' ORDER BY ' . implode(',', $orderArrays) . ' ';
    }

    /**
     * @param array $options [count => int or 'all', from => int] or [page => int, per_page => int]
     */
    public static function sqlLimit(array $options): string
    {
        $sqlLimit = '';
        $from = 0;
        $count = 'all';
        if (isset($options['count']) && $options['count'] && $options['count'] !== 'all' ) {
            $count = (int)$options['count'];
            if ($count < 0) $count = 0;
        }
        if (isset($options['from']) && $options['from']) {
            $from = (int)$options['from'];
            if ($from < 0) $from = 0;
        }
        if (isset($options['page']) && $options['page'] && isset($options['per_page']) && $options['per_page']) {
            $from = ($options['page']-1) * $options['per_page'];
            if ($from < 0) $from = 0;
            $count = $options['per_page'];
            if ($count < 0) $count = 0;
        }
        if ($count !== 'all') $sqlLimit = " LIMIT $from, $count ";
        return $sqlLimit;
    }
}
