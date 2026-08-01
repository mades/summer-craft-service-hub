<?php

namespace SummerCraft\Service\Database;

use ReflectionClass;
use RuntimeException;

/**
 * Hydrated by PDO with FETCH_CLASS, which assigns whatever the SELECT returned —
 * and search lists deliberately select joined columns (inn_user_name and the like)
 * to be folded into sub-objects afterwards. Those arrive as dynamic properties, so
 * this is the contract rather than an oversight: without the attribute every joined
 * column is a deprecation on 8.4, and an Error once dynamic properties are removed.
 */
#[\AllowDynamicProperties]
abstract class Record
{
    /**
     * @var string[] Autofilled
     */
    private $_fields;

    /**
     * @var array Origin fields values
     */
    protected $_origins;

    // final so that new static() in emptyRecord() below is provably safe: no
    // subclass can ever redeclare the constructor with incompatible (required)
    // parameters, since PHP itself forbids overriding a final method.
    final public function __construct()
    {
    }

    public static function emptyRecord(): static
    {
        $model = new static();
        $model->initModelOriginals();
        return $model;
    }

    /**
     * @return string[]
     */
    public function getModelFields(): array
    {
        if ($this->_fields === null) {
            $this->_fields = [];
            // declared properties, not get_object_vars(): that one omits a typed
            // property which has no value yet, so a record whose created_at is
            // filled in on save lost the column silently, and it also reports the
            // joined columns a search list hydrates, which are not columns of this
            // table and must never reach an INSERT
            foreach ((new ReflectionClass($this))->getProperties() as $property) {
                $field = $property->getName();
                if ($property->isStatic() || $field[0] === '_') {
                    continue;
                }
                $this->_fields[$field] = $field;
            }
        }
        return $this->_fields;
    }

    public function initModelOriginals(): void
    {
        $fields = $this->getModelFields();
        foreach ($fields as $field) {
            // a property with no value yet has no original either
            if (isset($this->$field)) {
                $this->_origins[$field] = $this->$field;
            }
        }
    }

    public function getModelTouchedSet(): array
    {
        $set = [];
        $isOriginalInitialized = $this->_origins;
        foreach ($this->getModelFields() as $field) {
            if (!isset($this->$field)) {
                continue;
            }
            if ($isOriginalInitialized && ($this->_origins[$field] ?? null) !== $this->$field) {
                $set[$field] = $this->$field;
            }
        }
        return $set;
    }

    public function getModelSettledSet(): array
    {
        $set = [];
        foreach ($this->getModelFields() as $field) {
            // isset(), not !== null: a typed property with no value yet cannot even
            // be read, and it means the same thing here — nothing to write
            if (isset($this->$field)) {
                $set[$field] = $this->$field;
            }
        }
        return $set;
    }

    public function updateModelBySet(array $set, bool $optional = false): void
    {
        $fields = $this->getModelFields();
        foreach ($set as $key => $value) {
            if (isset($fields[$key])) {
                $this->$key = $value;
            } elseif (!$optional) {
                throw new RuntimeException(sprintf(
                    'Model %s does not have field [%s] , has [%s]',
                     static::class,
                    $key,
                    implode(',', $fields)
                ));
            }
        }
    }

    /**
     * @return mixed|null
     */
    public function getModelField(string $field)
    {
        return $this->$field ?? null;
    }

    /**
     * @return mixed|null
     */
    public function getModelOriginalField(string $field)
    {
        return $this->_origins[$field] ?? null;
    }

    public function isModelOriginalInitialized(): bool
    {
        return !empty($this->_origins);
    }
}
