<?php

namespace SummerCraft\Service\Modifier;


class ArrayModifier
{
    public static function diff(array $oldArray, array $newArray): array
    {
        $addedItems = [];
        foreach ($newArray as $newArrayItem) {
            if (!in_array($newArrayItem, $oldArray, true)) {
                $addedItems[] = $newArrayItem;
            }
        }
        $removedItems = [];
        foreach ($oldArray as $oldArrayItem) {
            if (!in_array($oldArrayItem, $newArray, true)) {
                $removedItems[] = $oldArrayItem;
            }
        }
        if ($addedItems || $removedItems) {
            return [
                'added' => $addedItems,
                'removed' => $removedItems,
            ];
        }
        return [];
    }
}
