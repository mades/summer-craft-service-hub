<?php

namespace SummerCraft\Service\Tests\Unit\FileStorage;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Service\FileStorage\DefaultFileStorage;
use SummerCraft\Service\FileStorage\FileStorageConfig;
use SummerCraft\Service\Tests\Fixture\NullLogger;

class DefaultFileStorageTest extends TestCase
{
    private string $workDir;

    private DefaultFileStorage $storage;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/file-storage-test-' . uniqid() . '/';
        mkdir($this->workDir, 0777, true);
        $this->storage = new DefaultFileStorage(new NullLogger(), new FileStorageConfig());
    }

    protected function tearDown(): void
    {
        $this->storage->forFilesInDirectory($this->workDir, true, static function (string $file): void {
            unlink($file);
        });
        $this->removeEmptyDirsRecursively($this->workDir);
    }

    private function removeEmptyDirsRecursively(string $dir): void
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . $entry;
            if (is_dir($path)) {
                $this->removeEmptyDirsRecursively($path . '/');
            }
        }
        rmdir($dir);
    }

    /**
     * toRealPath() used to be the only path
     * normalization applied, and it doesn't resolve '..' at all — a filename
     * containing a traversal payload reached the filesystem call unmodified.
     */
    public function testDeleteFileRejectsPathTraversal(): void
    {
        // more '..' segments than $this->workDir has real path components, so this
        // throws regardless of how deep sys_get_temp_dir() happens to be nested
        $this->expectException(RuntimeException::class);
        $this->storage->deleteFile($this->workDir . '../../../../../../etc/passwd');
    }

    public function testCopyFileRejectsPathTraversalInSource(): void
    {
        $this->expectException(RuntimeException::class);
        $this->storage->copyFile('../../etc/passwd', $this->workDir . 'copy.txt');
    }

    public function testIsFileExistRejectsPathTraversal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->storage->isFileExist($this->workDir . '../../../../../../etc/passwd');
    }

    public function testMakeDirectoryRejectsPathTraversal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->storage->makeDirectory($this->workDir . '../../../../../../tmp/scs-0005-should-not-be-created');
    }

    public function testCreateCopyMoveDeleteRoundTrip(): void
    {
        $source = $this->workDir . 'source.txt';
        file_put_contents($source, 'hello');

        self::assertTrue($this->storage->isFileExist($source));
        self::assertSame(5, $this->storage->getFileSize($source));

        $copy = $this->workDir . 'copy.txt';
        $this->storage->copyFile($source, $copy);
        self::assertTrue($this->storage->isFileExist($copy));
        self::assertSame('hello', file_get_contents($copy));

        $moved = $this->workDir . 'moved.txt';
        $this->storage->moveFile($copy, $moved);
        self::assertFalse($this->storage->isFileExist($copy));
        self::assertTrue($this->storage->isFileExist($moved));

        $this->storage->deleteFile($moved);
        self::assertFalse($this->storage->isFileExist($moved));
    }

    public function testMakeDirectoryCreatesNestedDirectories(): void
    {
        $nested = $this->workDir . 'a/b/c';

        $this->storage->makeDirectory($nested);

        self::assertTrue($this->storage->isDirectoryExist($nested));
    }

    /**
     * forDirectoriesInDirectory() used to call
     * scandir() without checking its result, unlike its sibling
     * forFilesInDirectory(). scandir() returns false for a missing/unreadable
     * directory, and foreach(false as...) throws a TypeError on PHP8+ instead
     * of the same RuntimeException the sibling method gives.
     */
    public function testForDirectoriesInDirectoryThrowsRuntimeExceptionOnMissingDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->storage->forDirectoriesInDirectory($this->workDir . 'does-not-exist/', static function (): void {
        });
    }
}
