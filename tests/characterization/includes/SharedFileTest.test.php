<?php
// 20261029 CDX/NTR Verify shared runtime permissions under restrictive umasks and existing files.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/db_query.php';

final class SharedFileTest extends TestCase
{
    private string $dir;
    private int $originalUmask;

    protected function setUp(): void
    {
        $this->originalUmask = umask(0077);
        $this->dir = sys_get_temp_dir() . '/saldi_shared_' . bin2hex(random_bytes(8));
        mkdir($this->dir, 0700);
    }

    protected function tearDown(): void
    {
        umask($this->originalUmask);
        foreach (scandir($this->dir) as $name) {
            if ($name !== '.' && $name !== '..') {
                $path = $this->dir . '/' . $name;
                if (is_dir($path) && !is_link($path)) {
                    rmdir($path);
                } else {
                    unlink($path);
                }
            }
        }
        rmdir($this->dir);
    }

    public function testNewFileIsSharedDespiteRestrictiveUmask(): void
    {
        $path = $this->dir . '/.ht_online.log';
        self::assertSame(6, sharedFilePutContents($path, "first\n", true));
        clearstatcache(true, $path);
        self::assertSame(0666, fileperms($path) & 0777);
        self::assertSame(0077, umask());
    }

    public function testAppendRepairsExistingFileAndPreservesContents(): void
    {
        $path = $this->dir . '/existing.log';
        file_put_contents($path, "first\n");
        chmod($path, 0644);
        self::assertSame(7, sharedFilePutContents($path, "second\n", true));
        clearstatcache(true, $path);
        self::assertSame(0666, fileperms($path) & 0777);
        self::assertSame("first\nsecond\n", file_get_contents($path));
    }

    public function testOverwriteReplacesContents(): void
    {
        $path = $this->dir . '/timestamp.txt';
        file_put_contents($path, 'old timestamp');
        self::assertSame(3, sharedFilePutContents($path, 'new'));
        self::assertSame('new', file_get_contents($path));
    }

    public function testNewAndExistingDirectoriesAreSharedDespiteUmask(): void
    {
        $path = $this->dir . '/tenant';
        self::assertTrue(sharedDirectory($path));
        clearstatcache(true, $path);
        self::assertSame(0777, fileperms($path) & 0777);
        chmod($path, 0700);
        self::assertTrue(sharedDirectory($path));
        clearstatcache(true, $path);
        self::assertSame(0777, fileperms($path) & 0777);
    }

    public function testDatabaseLogAppendSharesFilesAndKeepsModeSemantics(): void
    {
        $path = $this->dir . '/.ht_modify.log';
        self::assertTrue(db_log_append($path, ["one\n", "two\n"]));
        self::assertTrue(db_log_append($path, "three\n"));
        self::assertSame("one\ntwo\nthree\n", file_get_contents($path));
        self::assertTrue(db_log_append($path, 'replacement', 'w'));
        self::assertSame('replacement', file_get_contents($path));
        clearstatcache(true, $path);
        self::assertSame(0666, fileperms($path) & 0777);
    }

    public function testSymlinkTargetIsNeitherChangedNorWritten(): void
    {
        $path = $this->dir . '/target';
        file_put_contents($path, 'private');
        chmod($path, 0600);
        $link = $this->dir . '/log';
        symlink($path, $link);
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            self::assertFalse(sharedFilePutContents($link, 'changed'));
        } finally {
            restore_error_handler();
        }
        self::assertCount(1, $warnings);
        self::assertStringContainsString('symlink', $warnings[0]);
        clearstatcache(true, $path);
        self::assertSame(0600, fileperms($path) & 0777);
        self::assertSame('private', file_get_contents($path));
    }

    public function testOpenFailureReturnsFalseWithoutWritingToInvalidStream(): void
    {
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            self::assertFalse(db_log_append($this->dir . '/missing/log', 'line'));
        } finally {
            restore_error_handler();
        }
        self::assertCount(1, $warnings);
        self::assertStringContainsString('Failed to open stream', $warnings[0]);
    }
}
