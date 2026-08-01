<?php

namespace SummerCraft\Service\Database\Event;

use SummerCraft\Service\Database\Record;
use SummerCraft\Core\EventDispatcher\Event;
use SummerCraft\Core\EventDispatcher\EventsConfig;

class ModelDeletingEvent implements Event
{

    private string $eventName;

    public function __construct(
        private Record $model,
    ) {
        $this->eventName = self::recordDeleting(get_class($model));
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getModel(): Record
    {
        return $this->model;
    }

    public static function recordDeleting(string $class): string
    {
        return $class . '.deleting';
    }
}
