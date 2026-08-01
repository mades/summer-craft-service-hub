<?php

namespace SummerCraft\Service\Database\Event;

use SummerCraft\Service\Database\Record;
use SummerCraft\Core\EventDispatcher\Event;
use SummerCraft\Core\EventDispatcher\EventsConfig;

class ModelUpdatedEvent implements Event
{
    private string $eventName;

    public function __construct(
        private Record $previousModel,
        private Record $model,
    ) {
        $this->eventName = self::recordUpdated(get_class($model));
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getPreviousVersion(): Record
    {
        return $this->previousModel;
    }

    public function getModel(): Record
    {
        return $this->model;
    }

    public static function recordUpdated(string $class): string
    {
        return $class . '.updated';
    }
}
