<?php

namespace SummerCraft\Service\Modifier;

use Exception;
use RuntimeException;

class IntModifier
{
    public static function normalizeBetween(int $value, ?int $min = null, ?int $max = null): int
    {
        if ($min !== null && $value < $min) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $value = $min;
        }
        if ($max !== null && $value > $max) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $value = $max;
        }
        return $value;
    }

    public static function random(int $min, int $max): int
    {
        try {
            return random_int($min, $max);
        } catch (Exception $exception) {
            throw new RuntimeException('Cant run random_int command: ' . $exception->getMessage());
        }
    }
}
