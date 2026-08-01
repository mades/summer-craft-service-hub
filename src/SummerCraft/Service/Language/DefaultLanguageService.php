<?php

namespace SummerCraft\Service\Language;

use SummerCraft\Core\Context\ApplicationContext;
use SummerCraft\Service\Modifier\StringModifier;

class DefaultLanguageService implements Language
{
    protected array $cache = [];

    protected array $loaded = [];

    protected string $language = 'undefined';

    public function __construct(
        private readonly LanguageConfig $languageConfig,
        private readonly ApplicationContext $applicationContext,
    ) {
        $this->language = $this->languageConfig->defaultLanguage;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    /**
     * Get string in language volume
     * @param string $alias alias of the string
     * @param array $replacePairs key -> value replace pairs
     */
    public function get(string $alias, array $replacePairs = [], ?string $default = null): string
    {
        if (!isset($this->cache[$alias])) {
            // limit 2: the text after the first colon is the value, and a value may
            // well contain a colon of its own — a URL, a time, a sentence
            $volumeArr = explode(':', $alias, 2);
            $volume = (count($volumeArr) > 1) ? $volumeArr[0] : 'default';

            if (!isset($this->loaded[$volume])) {
                $this->loadVolume($volume);
            }
            if (!isset($this->cache[$alias])) {
                $defaultValue = $default ?? ((count($volumeArr) > 1) ? $volumeArr[1] : $alias);
                if ($this->languageConfig->isGenerateVolumeOnUnknown) {
                    $this->writeToVolume($volume, $alias, $defaultValue, array_keys($replacePairs));
                }
                $this->cache[$alias] = $defaultValue;
            }
        }
        if ($replacePairs) {
            return StringModifier::replace($this->cache[$alias], $replacePairs);
        }
        return $this->cache[$alias];
    }

    protected function loadVolume(string $volume): void
    {
        $volumeFile = $this->getResourceVolumeFile($volume);
        if (!file_exists($volumeFile)) {
            return;
        }
        require($volumeFile);

        if (isset($lang) && is_array($lang)) {
            foreach ($lang as $key => $value) {
                $this->cache[$key] = $value;
            }
        }

        $this->loaded[$volume] = true;
    }

    protected function writeToVolume(string $volume, string $alias, string $defaultValue, array $keys): void
    {
        $genVolumeFile = $this->getGeneratedVolumeFile($volume);
        if (!file_exists($genVolumeFile)) {
            $genVolumeDir = dirname($genVolumeFile);
            if (!is_dir($genVolumeDir)) {
                mkdir($genVolumeDir, $this->languageConfig->generateVolumeDirectoryPermissions, true);
            }
            $resourceVolumeFile = $this->getResourceVolumeFile($volume);
            if (!file_exists($resourceVolumeFile)) {
                file_put_contents($genVolumeFile, "<?php\n\n", LOCK_EX);
            } else {
                file_put_contents($genVolumeFile, file_get_contents($resourceVolumeFile), LOCK_EX);
            }
        }
        $addedKeys = ($keys) ? ' ' . implode(' ', $keys) : '';
        // exported, not interpolated: an apostrophe in the fallback text — "don't" —
        // used to write a php file that no longer parses, and anything worse than an
        // apostrophe wrote code into a file this then requires
        file_put_contents(
            $genVolumeFile,
            '$lang[' . var_export($alias, true) . '] = ' . var_export($defaultValue . $addedKeys, true) . ";\n",
            FILE_APPEND | LOCK_EX
        );
    }

    protected function getResourceVolumeFile(string $volume): string
    {
        return $this->applicationContext->getResourcePath() . 'language/'. $this->language . '/' . $volume . '.php';
    }

    protected function getGeneratedVolumeFile(string $volume): string
    {
        return $this->applicationContext->getTemporaryPath() . 'language/'. $this->language . '/' . $volume . '.php';
    }
}
