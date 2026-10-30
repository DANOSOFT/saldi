<?php
// 20261029 CDX/NTR Verify shared runtime permissions under restrictive umasks and existing files.
// 20261029 CDX/NTR Cover group-only sharing, untrusted parents and administrator migration.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/db_query.php';

final class SharedFileTest extends TestCase
{
    private string $dir;
    private int $originalUmask;
    private string|false $originalGroup;

    /** Prepare private runtime storage and use the test account's existing group. */
    protected function setUp(): void
    {
        $this->originalUmask = umask(0077);
        $this->originalGroup = getenv('SALDI_SHARED_FILE_GROUP');
        putenv('SALDI_SHARED_FILE_GROUP=' . posix_getgrgid(posix_getegid())['name']);
        $this->dir = sys_get_temp_dir() . '/saldi_shared_' . bin2hex(random_bytes(8));
        mkdir($this->dir, 0700);
    }

    /** Restore process settings and remove only this test's disposable paths. */
    protected function tearDown(): void
    {
        umask($this->originalUmask);
        putenv($this->originalGroup === false ? 'SALDI_SHARED_FILE_GROUP' : 'SALDI_SHARED_FILE_GROUP=' . $this->originalGroup);
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->dir);
    }

    /** Creation grants the configured group access without changing the caller's umask. */
    public function testNewFileIsSharedDespiteRestrictiveUmask(): void
    {
        $path = $this->dir . '/.ht_online.log';
        self::assertSame(6, sharedFilePutContents($path, "first\n", true));
        clearstatcache(true, $path);
        self::assertSame(0660, fileperms($path) & 0777);
        self::assertSame(0077, umask());
    }

    public function testAppendRepairsExistingFileAndPreservesContents(): void
    {
        $path = $this->dir . '/existing.log';
        file_put_contents($path, "first\n");
        chmod($path, 0644);
        self::assertSame(7, sharedFilePutContents($path, "second\n", true));
        clearstatcache(true, $path);
        self::assertSame(0660, fileperms($path) & 0777);
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
        self::assertSame(02770, fileperms($path) & 07777);
        chmod($path, 0700);
        self::assertTrue(sharedDirectory($path));
        clearstatcache(true, $path);
        self::assertSame(02770, fileperms($path) & 07777);
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
        self::assertSame(0660, fileperms($path) & 0777);
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
        self::assertStringContainsString('lstat', $warnings[0]);
    }

    /** An unsafe parent is rejected before an existing file can be truncated or chmodded. */
    public function testWorldWritableParentIsRejectedBeforeMutation(): void
    {
        $path = $this->dir . '/existing.log';
        file_put_contents($path, 'keep');
        chmod($this->dir, 0777);
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            self::assertFalse(sharedFilePutContents($path, 'truncated'));
            self::assertFalse(sharedFilePermissions($path));
        } finally {
            restore_error_handler();
            chmod($this->dir, 0700);
        }
        self::assertCount(2, $warnings);
        self::assertStringContainsString('Untrusted shared-file parent', $warnings[0]);
        self::assertSame('keep', file_get_contents($path));
        clearstatcache(true, $path);
        self::assertSame(0600, fileperms($path) & 0777);
    }

    /** Symlinks in intermediate components cannot redirect file creation or permission changes. */
    public function testIntermediateSymlinkIsRejected(): void
    {
        $target = $this->dir . '/target';
        mkdir($target, 0700);
        $link = $this->dir . '/link';
        symlink($target, $link);
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            self::assertFalse(sharedFilePutContents($link . '/log', 'data'));
            // A lexical '..' must not conceal a symlink from validation.
            self::assertFalse(sharedFilePutContents($link . '/../log', 'data'));
        } finally {
            restore_error_handler();
        }
        self::assertCount(2, $warnings);
        self::assertFileDoesNotExist($target . '/log');
        self::assertFileDoesNotExist($this->dir . '/log');
    }

    /** Creation with an unknown group fails closed instead of falling back to public access. */
    public function testUnknownGroupDoesNotCreateFile(): void
    {
        putenv('SALDI_SHARED_FILE_GROUP=saldi_missing_' . bin2hex(random_bytes(8)));
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            self::assertFalse(sharedFilePutContents($this->dir . '/log', 'data'));
        } finally {
            restore_error_handler();
        }
        self::assertCount(1, $warnings);
        self::assertStringContainsString('unavailable', $warnings[0]);
        self::assertFileDoesNotExist($this->dir . '/log');
    }

    /** A real second user cannot repair a foreign log, but can append after administrator migration. */
    public function testCrossUserMigrationAndSharedWrites(): void
    {
        if (posix_geteuid() !== 0) {
            self::markTestSkipped('Cross-user verification requires root, as in the CI web container.');
        }
        $web = posix_getpwnam('www-data');
        $other = posix_getpwnam('nobody');
        if ($web === false || $other === false) {
            self::markTestSkipped('Cross-user verification requires www-data and nobody accounts.');
        }
        putenv('SALDI_SHARED_FILE_GROUP=' . posix_getgrgid($web['gid'])['name']);
        chmod($this->dir, 0755);
        $runtime = $this->dir . '/runtime';
        mkdir($runtime, 0755);
        chmod($runtime, 0755);
        $path = $runtime . '/.ht_online.log';
        file_put_contents($path, "old\n");
        chmod($path, 0600);
        chown($path, $other['uid']);
        $source = var_export(dirname(__DIR__, 3) . '/includes/sharedFile.php', true);
        $file = var_export($path, true);
        $identity = 'if (!posix_initgroups("www-data", ' . $web['gid'] . ') || !posix_setgid(' . $web['gid'] . ') || !posix_setuid(' . $web['uid'] . ')) { exit(2); }';
        // Failed repair must be actionable and preserve the file before an overwrite open.
        $before = $this->runPhp($identity . 'require ' . $source . '; '
            . '$warnings = []; set_error_handler(static function($severity, $message) use (&$warnings) { $warnings[] = $message; return true; }); '
            . '$result = sharedFilePutContents(' . $file . ', "lost"); '
            . 'exit($result === false && count($warnings) === 1 && str_contains($warnings[0], "migration") ? 0 : 1);');
        self::assertSame(0, $before['status'], $before['output']);
        self::assertSame("old\n", file_get_contents($path));

        $migration = $this->runCommand([PHP_BINARY, dirname(__DIR__, 3) . '/tools/migrate_shared_runtime.php', $runtime]);
        self::assertSame(0, $migration['status'], $migration['output']);
        clearstatcache();
        self::assertSame($web['gid'], filegroup($path));
        self::assertSame(0660, fileperms($path) & 0777);
        self::assertSame(02770, fileperms($runtime) & 07777);
        self::assertSame($other['uid'], fileowner($path));

        $after = $this->runPhp($identity . 'require ' . $source . '; '
            . 'umask(0077); exit(sharedFilePutContents(' . $file . ', "web\n", true) === 4 ? 0 : 1);');
        self::assertSame(0, $after['status'], $after['output']);
        self::assertSame("old\nweb\n", file_get_contents($path));
        $created = $runtime . '/new.log';
        self::assertSame(4, sharedFilePutContents($created, "cli\n"));
        $append = $this->runPhp($identity . 'require ' . $source . '; '
            . 'exit(sharedFilePutContents(' . var_export($created, true) . ', "web\n", true) === 4 ? 0 : 1);');
        self::assertSame(0, $append['status'], $append['output']);
        self::assertSame("cli\nweb\n", file_get_contents($created));
        $outsider = 'if (!posix_initgroups("nobody", ' . $other['gid'] . ') || !posix_setgid(' . $other['gid'] . ') || !posix_setuid(' . $other['uid'] . ')) { exit(2); }';
        $denied = $this->runPhp($outsider . 'exit(!is_readable(' . var_export($created, true)
            . ') && !is_writable(' . var_export($runtime, true) . ') ? 0 : 1);');
        self::assertSame(0, $denied['status'], $denied['output']);
    }

    /**
     * Run a PHP child so identity changes never affect PHPUnit itself.
     *
     * @param string $code Child PHP program.
     * @return array{status: int, output: string} Exit code and captured diagnostics.
     */
    private function runPhp(string $code): array
    {
        return $this->runCommand([PHP_BINARY, '-r', $code]);
    }

    /**
     * Capture diagnostics from a migration or test child without invoking a shell.
     *
     * @param string[] $command Executable and arguments.
     * @return array{status: int, output: string} Exit code and captured diagnostics.
     */
    private function runCommand(array $command): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['status' => proc_close($process), 'output' => $output];
    }

}
