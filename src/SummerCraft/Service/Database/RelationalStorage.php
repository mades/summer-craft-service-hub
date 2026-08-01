<?php

namespace SummerCraft\Service\Database;

use SummerCraft\Service\Database\Config\RelationalStorageConfig;
use SummerCraft\Core\EventDispatcher\EventDispatcher;
use SummerCraft\Service\Database\Event\ModelDeletingEvent;
use SummerCraft\Service\Database\Event\ModelUpdatedEvent;
use SummerCraft\Service\Logger\Logger;
use SummerCraft\Service\Logger\LoggerContext;
use SummerCraft\Service\Time\DateTimeService;
use SummerCraft\Core\ComponentManaging\RequestScope;

/**
 * @template T of Record
 */
class RelationalStorage
{
    private ?RelationalDatabaseHandler $dbHandler = null;

    private ?DateTimeService $dateService = null;

    private ?Logger $logger = null;

    private string $modelClass;

    private string $table;

    /**
     * @var string[]
     */
    private array $primaryFields;

    public function __construct(
        private RequestScope $requestScope,
        private RelationalStorageConfig $config,
    ) {
    }

    public function getConfig(): RelationalStorageConfig
    {
        return $this->config;
    }

    /**
     * Can be ['firstPrimaryValue', 'secondPrimaryValue']
     * Can be ['field2' => 'secondPrimaryValue', 'field1' => 'firstPrimaryValue']
     * @param string[]|int[] $primaryIdParts
     * @return T|null
     */
    public function findByPrimary(array $primaryIdParts): ?Record
    {
        $db = $this->getDB();
        $where = [];
        foreach ($this->primaryFields as $key => $primaryField) {
            $where[$primaryField] = $primaryIdParts[$primaryField] ?? $primaryIdParts[$key];
        }
        /** @var Record|null $model */
        $model = $db->selectWhereOneObject($this->modelClass, $this->table, $where);
        if ($model !== null) {
            $this->initOriginsForModels([$model]);
        }
        return $model;
    }

    /**
     * @param array $where [
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
     * @param array $opt ['key'=>field as key, order => ['field1' => 'asc', 'field2' => 'desc'], limit=>[count=>,from=>,page=>,per_page=>]]
     * @return array<string, T>
     */
    public function find(array $where, array $opt = []): array
    {
        /** @var Record[] $models */
        $models = $this->getDB()->selectWhereObjects($this->modelClass, $this->table, $where, $opt);
        $this->initOriginsForModels($models);
        return $models;
    }

    public function count(array $where = []): int
    {
        return $this->getDB()->selectCountWhere($this->table, $where);
    }

    /**
     * @param array $opt [order => ['field1' => 'asc', 'field2' => 'desc']]
     * @return T|null
     */
    public function findOne(array $where, array $opt = []): ?Record
    {
        /** @var Record|null $model */
        $model = $this->getDB()->selectWhereOneObject($this->modelClass, $this->table, $where, $opt);
        if ($model) {
            $this->initOriginsForModels([$model]);
        }
        return $model;
    }

    /**
     * @param T $model
     */
    public function exist(Record $model): bool
    {
        foreach ($this->config->primaryFields as $field) {
            if (!$model->getModelOriginalField($field)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param T $model
     */
    public function save(Record $model): void
    {
        $modelBeforeSaving = clone $model;

        $whereSet = $this->getPrimarySet($model);
        $timeString = $this->config->unixTimestamps
            ? (string)$this->getDateService()->getCurrentTimestamp()
            : $this->getDateService()->sqlTime();
        if ($whereSet && $this->exist($model)) {
            $set = $model->getModelTouchedSet();
            if (!$set) {
                return;
            }
            $model->updateModelBySet(['updated_at' => $timeString], true);
            $set = $model->getModelTouchedSet();
            $this->getDB()->update($this->table, $whereSet, $set);
        } else {
            $model->updateModelBySet(['updated_at' => $timeString, 'created_at' => $timeString], true);
            $set = $model->getModelSettledSet();
            if ($this->config->autoIncrementField !== null) {
                $primaryKey = $this->getDB()->insert($this->table, $set, true);
                $model->updateModelBySet([$this->config->autoIncrementField => $primaryKey]);
            } else {
                $this->getDB()->insert($this->table, $set, false);
            }
        }
        $model->initModelOriginals();
        $this->getEventDispatcher()->fire(new ModelUpdatedEvent($modelBeforeSaving, $model));
    }

    /**
     * @param T $model
     */
    public function delete(Record $model): bool
    {
        $where = $this->getPrimarySet($model);
        if (!$where) {
            $this->getLogger()->warning(
                sprintf('Trying to delete not exist model [%s] with table [%s]', get_class($model), $this->config->table),
                [
                    LoggerContext::TAG => 'RelationalModelRepository',
                    LoggerContext::WITH_TRACE => true,
                ]
            );
            return false;
        }
        // TODO Add AutoStart Transaction
        $this->getEventDispatcher()->fire(new ModelDeletingEvent($model));
        return (bool)$this->getDB()->delete($this->table, $where);
    }

    public function update(array $where, array $set): void
    {
        $this->getDB()->update($this->table, $where, $set);
    }

    public function deleteBulk(array $where): bool
    {
        return (bool)$this->getDB()->delete($this->table, $where);
    }

    /**
     * @param T[] $models
     */
    private function initOriginsForModels(array $models): void
    {
        foreach ($models as $model) {
            $model->initModelOriginals();
        }
    }

    /**
     * Return [primaryField => value] map or null if dont set
     * @param T $model
     * @return array|null
     */
    private function getPrimarySet(Record $model): ?array
    {
        $set = [];
        $isOriginalInitialized = $model->isModelOriginalInitialized();
        foreach ($this->config->primaryFields as $field) {
            if ($isOriginalInitialized && $model->getModelOriginalField($field)) {
                $set[$field] = $model->getModelOriginalField($field);
            } elseif ($model->getModelField($field)) {
                $set[$field] = $model->getModelField($field);
            } else {
                return null;
            }
        }
        return $set;
    }

    protected function getDB(): RelationalDatabaseHandler
    {
        if ($this->dbHandler === null) {
            /** @var RelationalDatabaseHandler $dbHandler */
            $dbHandler = $this->requestScope->get($this->config->dbHandlerServiceName);
            $this->dbHandler = $dbHandler;
            $this->modelClass = $this->config->modelClass;
            $this->table = $this->config->table;
            $this->primaryFields = $this->config->primaryFields;
        }
        return $this->dbHandler;
    }

    protected function getDateService(): DateTimeService
    {
        if ($this->dateService === null) {
            $this->dateService = $this->requestScope->get(DateTimeService::class);
        }
        return $this->dateService;
    }

    private function getLogger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = $this->requestScope->get(Logger::class);
        }
        return $this->logger;
    }

    private function getEventDispatcher(): EventDispatcher
    {
        return $this->requestScope->get(EventDispatcher::class);
    }
}
