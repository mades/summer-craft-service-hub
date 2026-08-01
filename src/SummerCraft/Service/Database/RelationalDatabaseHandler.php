<?php
namespace SummerCraft\Service\Database;

interface RelationalDatabaseHandler
{
    /**
     * Quote of string for save usage in query
     */
    public function quote(string $string): string;

    /**
     * Free connection
     */
    public function disconnect(): void;

    /**
     * Data to describe about queries and times for execution
     */
    public function getBenchmarkData(): array;

    /**
     * @param string|null $className CClassName or null for stdObject
     * @param string $query SQL query @lang MySQL
     * @param array $params Key-Value params
     * @param string|null $fieldAsKey Field values used as key in result
     * @return \stdClass[] array of objects
     */
    public function selectObjects(?string $className, string $query, array $params = [], ?string $fieldAsKey = null): array;

    /**
     * @param string|null $className ClassName or null for stdObject
     * @param string $query SQL query @lang MySQL
     * @param array $params Key-Value params
     * @return object|null object or null if not found
     */
    public function selectOneObject(?string $className, string $query, array $params = []): ?object;

    /**
     * @param string $query SQL query @lang MySQL
     * @param array $params Key-Value params
     * @param string|null $fieldAsKey Field values used as key in result
     * @return array Array of result arrays
     */
    public function select(string $query, array $params = [], ?string $fieldAsKey = null): array;

    /**
     * @param string $query SQL query @lang MySQL
     * @param array $params Key-Value params
     * @return array|null result array or null if not found
     */
    public function selectOne(string $query, array $params = []): ?array;

    /**
     * @param string $query SQL query @lang MySQL
     * @param array $params Key-Value params
     * @param string|null $field field to extract or null for first value extract
     * @return string|null Field value or null if not found
     */
    public function selectOneField(string $query, array $params = [], ?string $field = null): ?string;

    /**
     * @param string $query SQL query @lang MySQL
     * @param array $params Key-Value params
     * @param string $field field to extract
     * @param string|null $fieldAsKey Field values used as key in result
     * @return string[]
     */
    public function selectField(string $query, array $params, string $field, ?string $fieldAsKey = null): array;

    /**
     * @param string $query SQL query @lang MySQL
     * @param array $params Key-Value params
     * @param bool $returnInsertedId is return inserted id
     * @return string affected rows count when $returnInsertedId is false,
     *                inserted id (as PDO::lastInsertId() returns it) otherwise
     */
    public function execute(string $query, array $params = [], bool $returnInsertedId = false): string;

    /**
     * @param string|null $className  CClassName or null for stdObject
     * @param string $table Table name
     * @param array $where Where Key-Value array
     * @param array $opt ['key'=>field as key, order => ['field1' => 'asc', 'field2' => 'desc'], limit=>[count=>,from=>,page=>,per_page=>]]
     * @return \stdClass[]
     */
    public function selectWhereObjects(?string $className, string $table, array $where, array $opt = []): array;

    /**
     * @param string|null $className ClassName
     * @param string $table Table name
     * @param array $where Where Key-Value array
     * @param array $opt [order => ['field1' => 'asc', 'field2' => 'desc']]
     * @return object|null
     */
    public function selectWhereOneObject(?string $className, string $table, array $where, array $opt = []): ?object;

    /**
     * @param string $table Table name
     * @param array $where Where Key-Value array
     * @param array $opt ['key'=>field as key, order => ['field1' => 'asc', 'field2' => 'desc'], limit=>[count=>,from=>,page=>,per_page=>]]
     * @return array[]
     */
    public function selectWhere(string $table, array $where, array $opt = []): array;

    /**
     * @param string $table Table name
     * @param array $where Where Key-Value array
     */
    public function selectCountWhere(string $table, array $where): int;

    /**
     * @param string $table Table name
     * @param array $where Where Key-Value array
     * @param array $opt [order => ['field1' => 'asc', 'field2' => 'desc']]
     * @return array|null
     */
    public function selectWhereOne(string $table, array $where, array $opt = []): ?array;

    /**
     * @param string $table Table name
     * @param array $where Where Key-Value array
     * @param string $field field to extract or null for first value extract
     * @param array $opt ['key'=>field as key, order => ['field1' => 'asc', 'field2' => 'desc'], limit=>[count=>,from=>,page=>,per_page=>]]
     * @return string[]
     */
    public function selectFieldWhere(string $table, array $where, string $field, array $opt = []): array;

    /**
     * @param string $table Table name
     * @param array $set SetMap Key-Value array
     * @param bool $returnInsertId True - returned insertId, FALSE return affected rows count
     * @return string Primary Key Last Increment
     */
    public function insert(string $table, array $set, bool $returnInsertId): string;

    /**
     * @param string $table Table name
     * @param array $where Where Key-Value array
     * @param array $set SetMap Key-Value array
     * @return int affected rows
     */
    public function update(string $table, array $where, array $set): int;

    /**
     * @param string $table Table name
     * @param array $where Where Key-Value array
     * @return int affected rows
     */
    public function delete(string $table, array $where): int;

    public function transactionStart(): void;
    public function transactionCommit(): void;
    public function transactionRollBack(): void;
}
