<?php

namespace SummerCraft\Service\FileStorage;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class FileStorageConfig implements SharedComponent
{
    public int $filePermissions = 0644;
    public int $directoryPermissions = 0755;
}
