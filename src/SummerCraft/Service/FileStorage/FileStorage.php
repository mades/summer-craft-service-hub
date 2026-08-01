<?php

namespace SummerCraft\Service\FileStorage;

interface FileStorage
{
    public function isDirectoryExist(string $directory): bool;

    public function isFileExist(string $file): bool;

    public function getFileSize(string $file): int;

    public function getFileHash(string $file): string;

    public function forFilesInDirectory(string $directory, bool $recursive, callable $callback): void;

    public function forDirectoriesInDirectory(string $directory, callable $callback): void;

    public function setDefaultChmod(string $file): void;

    public function copyFileToDirectory(string $fromFile, string $toDirectory, string $fileName): void;

    public function copyFile(string $fromFile, string $toFile): void;

    public function moveFile(string $fromFile, string $toFile): void;

    public function copyModifierTime(string $fromFile, string $toFile): void;

    public function getModifierTime(string $fromFile): int;

    public function makeDirectory(string $toDirectory): void;

    public function deleteFile(string $file): void;
}

