<?php

namespace SummerCraft\Service\Modifier;

use Exception;
use ReflectionClass;
use RuntimeException;

class FileStringModifier
{
    public static function fileExtension(string $file): string
    {
        $fileNameSegments = explode('.', $file);
        return count($fileNameSegments) > 1 ? end($fileNameSegments) : '';
    }

    public static function fileName(string $file): string
    {
        $fileSegments = explode('/', self::toUnixFile($file));
        return end($fileSegments);
    }

    public static function simpleFileName(string $fileName): string
    {
        $fileNameExploded = explode('.', self::fileName($fileName));
        if (count($fileNameExploded) > 1) {
            unset($fileNameExploded[count($fileNameExploded) - 1]);
        }
        return implode('.', $fileNameExploded);
    }

    public static function lastDirName(string $file): string
    {
        $fileSegments = explode('/', self::toUnixFile($file));
        if (count($fileSegments) > 1) {
            return $fileSegments[count($fileSegments) - 2];
        }
        return '';
    }

    public static function dirName(string $file): string
    {
        $fileSegments = explode('/', self::toUnixFile($file));
        unset($fileSegments[count($fileSegments) - 1]);

        return self::toRealPath(implode('/', $fileSegments) . '/');
    }

    public static function toUnixFile(string $file): string
    {
        return StringModifier::replace($file, [DIRECTORY_SEPARATOR => '/']);
    }

    public static function removeBackSegments(string $file): string
    {
        $segments = explode('/', self::toUnixFile($file));
        foreach ($segments as $key => $segment) {
            if ($segment === '.' || $segment === '..') {
                unset($segments[$key]);
            }
        }
        return self::toRealPath(implode('/', array_values($segments)));
    }

    /**
     * @throws RuntimeException if a '..' segment would climb above the start of $file
     *                          itself — e.g. '../../../etc/passwd'. Silently dropping
     *                          the excess '..' is exactly the traversal shape an
     *                          attacker would use.
     */
    public static function resolveBackSegments(string $file): string
    {
        $segments = explode('/', self::toUnixFile($file));
        $keysStack = [];
        foreach ($segments as $key => $segment) {
            if ($segment === '.') {
                unset($segments[$key]);
                continue;
            }
            if ($segment === '..') {
                unset($segments[$key]);
                $parentKey = array_pop($keysStack);
                if ($parentKey === null) {
                    throw new RuntimeException("Path [$file] climbs above its own root via '..'");
                }
                unset($segments[$parentKey]);
                continue;
            }
            array_push($keysStack, $key);
        }
        return self::toRealPath(implode('/', array_values($segments)));
    }

    public static function toRealPath(string $file): string
    {
        return StringModifier::replace($file, ['/' => DIRECTORY_SEPARATOR]);
    }

    public static function realPathOfClass(string $className): ?string
    {
        try {
            return (new ReflectionClass($className))->getFileName();
        } catch (Exception $exception) {
            return null;
        }
    }

    public static function relativePath(string $file, string $directory): string
    {
        $file = self::toRealPath($file);
        $directory = self::toRealPath($directory);
        return StringModifier::replace('^' . $file, ['^' . $directory => '']);
    }
}
