<?php

namespace SummerCraft\Service\Language;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class LanguageConfig implements SharedComponent
{
    public string $defaultLanguage = 'english';

    public bool $isGenerateVolumeOnUnknown = true;
    public int $generateVolumeDirectoryPermissions = 0755;

    public array $supportedLanguages = [
        'english',
    ];
}