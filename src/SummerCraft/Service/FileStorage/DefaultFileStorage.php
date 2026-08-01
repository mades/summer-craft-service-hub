<?php

namespace SummerCraft\Service\FileStorage;

use RuntimeException;
use SummerCraft\Service\Logger\TaggedLogger;
use SummerCraft\Service\Modifier\FileStringModifier;
use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class DefaultFileStorage implements FileStorage, SharedComponent
{
    public function __construct(
        private TaggedLogger $logger,
        private FileStorageConfig $config,
    ) {
    }

    public function isDirectoryExist(string $directory): bool
    {
        $directory = FileStringModifier::resolveBackSegments($directory);
        return file_exists($directory) && is_dir($directory);
    }

    public function isFileExist(string $file): bool
    {
        $file = FileStringModifier::resolveBackSegments($file);
        return file_exists($file);
    }

    public function getFileSize(string $file): int
    {
        $file = FileStringModifier::resolveBackSegments($file);
        return (int)filesize($file);
    }

    public function getFileHash(string $file): string
    {
        return hash_file('md5', $file);
    }

    public function forFilesInDirectory(string $directory, bool $recursive, callable $callback, int $maxDeep = 10): void
    {
        $directory = FileStringModifier::resolveBackSegments($directory);
        $fileNames = scandir($directory);
        if (!is_array($fileNames)) {
            throw new RuntimeException(sprintf('Impossible to scan directory %s', $directory));
        }
        $uniqueFileNames = [];
        foreach ($fileNames as $fileName) {
            if (!in_array($fileName, $uniqueFileNames, true)) {
                $uniqueFileNames[] = $fileName;
            }
        }

        foreach ($uniqueFileNames as $fileName) {
            $file = $directory . $fileName;
            if (is_dir($file)) {
                if ($fileName !== '.' && $fileName !== '..' && $recursive && $maxDeep > 0) {
                    $this->forFilesInDirectory(
                        $file . DIRECTORY_SEPARATOR,
                        $recursive,
                        $callback,
                        $maxDeep - 1
                    );
                }
            } elseif (is_file($file)) {
                $callback($file);
            } else {
                $this->logger->taggedError(
                    'STORAGE',
                    "Remove file that already can not find [$file]",
                    ['fileNames' => $uniqueFileNames]
                );

                throw new RuntimeException("Unknown resource [$file]");
            }
        }
    }

    public function forDirectoriesInDirectory(string $directory, callable $callback): void
    {
        $directory = FileStringModifier::resolveBackSegments($directory);
        $fileNames = @scandir($directory);
        if (!is_array($fileNames)) {
            throw new RuntimeException(sprintf('Impossible to scan directory %s', $directory));
        }

        foreach ($fileNames as $fileName) {
            $file = $directory . $fileName;
            if (is_dir($file)) {
                if ($fileName !== '.' && $fileName !== '..') {
                    $callback($file . DIRECTORY_SEPARATOR);
                }
            }
        }
    }

    public function setDefaultChmod(string $file): void
    {
        $file = FileStringModifier::resolveBackSegments($file);
        chmod($file, $this->config->filePermissions);
    }

    public function copyFileToDirectory(string $fromFile, string $toDirectory, string $fileName): void
    {
        $fromFile = FileStringModifier::resolveBackSegments($fromFile);
        $toDirectory = FileStringModifier::resolveBackSegments($toDirectory);
        $this->makeDirectory($toDirectory);

        copy($fromFile, $toDirectory . $fileName);
        chmod($toDirectory . $fileName, $this->config->filePermissions);
        touch($toDirectory . $fileName, (int)filemtime($fromFile));
    }

    public function copyFile(string $fromFile, string $toFile): void
    {
        if (!$this->isFileExist($fromFile)) {
            throw new RuntimeException("File [$fromFile] not found");
        }
        if ($this->isFileExist($toFile)) {
            throw new RuntimeException("File [$toFile] already exists");
        }

        $fromFile = FileStringModifier::resolveBackSegments($fromFile);
        $toFile = FileStringModifier::resolveBackSegments($toFile);
        $toDirectory = FileStringModifier::dirName($toFile);
        $this->makeDirectory($toDirectory);

        copy($fromFile, $toFile);
        chmod($toFile, $this->config->filePermissions);
        touch($toFile, (int)filemtime($fromFile));
    }

    public function moveFile(string $fromFile, string $toFile): void
    {
        if (!$this->isFileExist($fromFile)) {
            throw new RuntimeException("File [$fromFile] not found");
        }
        if ($this->isFileExist($toFile)) {
            throw new RuntimeException("File [$toFile] already exists. File [$fromFile] not moved");
        }

        $fromFile = FileStringModifier::resolveBackSegments($fromFile);
        $toFile = FileStringModifier::resolveBackSegments($toFile);
        $toDirectory = FileStringModifier::dirName($toFile);
        $this->makeDirectory($toDirectory);

        rename($fromFile, $toFile);
    }

    public function copyModifierTime(string $fromFile, string $toFile): void
    {
        touch($toFile, (int)filemtime($fromFile));
    }

    public function getModifierTime(string $fromFile): int
    {
        return (int)filemtime($fromFile);
    }

    public function makeDirectory(string $toDirectory): void
    {
        $toDirectory = FileStringModifier::resolveBackSegments($toDirectory);
        if (!file_exists($toDirectory)){
            if (!mkdir($toDirectory, $this->config->directoryPermissions, true) && !is_dir($toDirectory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $toDirectory));
            }
            chmod($toDirectory, $this->config->directoryPermissions);
        }
        if (!is_dir($toDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" is not directory', $toDirectory));
        }
    }

    public function deleteFile(string $file): void
    {
        $file = FileStringModifier::resolveBackSegments($file);
        if(file_exists($file) && is_file($file)){
            unlink($file);
        } else {
            $this->logger->taggedWarning('STORAGE', "Remove file that already can not find [$file]");
        }
    }
}