<?php

namespace SummerCraft\Service\Database\Config;

class RelationalStorageConfig implements StorageConfig
{
    public function __construct(
        public string $modelClass,
        public string $dbHandlerServiceName,
        public string $table,
        public ?string $autoIncrementField = 'id',
        public array $primaryFields = ['id'],
        /**
         * Whether created_at/updated_at are unix time rather than SQL datetime.
         * Both conventions exist in the same database, and writing the wrong one
         * is not a mismatch the driver forgives — it truncates the value.
         */
        public bool $unixTimestamps = false,
    ) {
    }
}
