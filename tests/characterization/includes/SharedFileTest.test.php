<?php
// 20261029 CDX/NTR Verify shared runtime permissions under restrictive umasks and existing files.
// 20261029 CDX/NTR Cover group-only sharing, untrusted parents and administrator migration.
// 20261102 CL/NTR Cover the balances group, the unmigrated fallback and once-per-message warnings.
//                 Cover symlinks held by trusted directories and symlinks into untrusted ones.
// 20261103 CDX/NTR Cover non-throwing diagnostics, private fallback creation, stream failures and migration boundaries.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/db_query.php';

final class SharedFileTest extends TestCase
{
    private string $dir;
    private int $originalUmask;
    private string|false $originalGroup;
    private string $originalErrorLog;

    /** Prepare private runtime storage and use the test account's existing group. */
    protected function setUp(): void
    {
        $this->originalUmask = umask(0077);
        $this->originalGroup = getenv('SALDI_SHARED_FILE_GROUP');
        putenv('SALDI_SHARED_FILE_GROUP=' . posix_getgrgid(posix_getegid())['name']);
        $this->dir = sys_get_temp_dir() . '/saldi_shared_' . bin2hex(random_bytes(8));
        mkdir($this->dir, 0700);
        $this->originalErrorLog = ini_get('error_log');
        ini_set('error_log', $this->dir . '/diagnostics.log');
    }

    /** Restore process settings and remove only this test's disposable paths. */
    protected function tearDown(): void
    {
        umask($this->originalUmask);
        ini_set('error_log', $this->originalErrorLog);
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

    /** A log that is itself a symlink is written through, but its target keeps its permissions. */
    public function testSymlinkedFileIsWrittenWithoutChangingTarget(): void
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
            self::assertSame(7, sharedFilePutContents($link, 'changed'));
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $warnings);
        clearstatcache(true, $path);
        self::assertSame(0600, fileperms($path) & 0777);
        self::assertSame('changed', file_get_contents($path));
    }

    /** A missing parent fails quietly, like the suppressed fopen these helpers replaced. */
    public function testOpenFailureReturnsFalseWithoutWritingToInvalidStream(): void
    {
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            if (error_reporting() & $severity) {
                $warnings[] = $message;
            }
            return true;
        });
        try {
            self::assertFalse(db_log_append($this->dir . '/missing/log', 'line'));
            self::assertFalse(sharedDirectory($this->dir . '/missing/tenant'));
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $warnings);
    }

    /** An unsafe parent keeps the plain write but never has permissions changed through it. */
    public function testWorldWritableParentFallsBackWithoutChangingPermissions(): void
    {
        $path = $this->dir . '/existing.log';
        file_put_contents($path, 'keep');
        chmod($this->dir, 0777);
        // Match the journal-delete handler: even suppressed PHP errors become exceptions.
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });
        try {
            self::assertSame(9, sharedFilePutContents($path, 'truncated'));
            self::assertFalse(sharedFilePermissions($path));
        } finally {
            restore_error_handler();
            chmod($this->dir, 0700);
        }
        $diagnostics = file_get_contents($this->dir . '/diagnostics.log');
        self::assertSame(1, substr_count($diagnostics, 'Untrusted shared-file parent'));
        self::assertSame('truncated', file_get_contents($path));
        clearstatcache(true, $path);
        self::assertSame(0600, fileperms($path) & 0777);
    }

    /** A symlinked install directory held by a trusted directory is shared like any other. */
    public function testTrustedIntermediateSymlinkIsShared(): void
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
            self::assertSame(4, sharedFilePutContents($link . '/log', 'data'));
            self::assertTrue(sharedDirectory($link . '/tenant'));
            // '..' after a symlink climbs from the target, as the kernel does.
            self::assertSame(4, sharedFilePutContents($link . '/../log', 'data'));
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $warnings);
        clearstatcache();
        self::assertSame(0660, fileperms($target . '/log') & 0777);
        self::assertSame(02770, fileperms($target . '/tenant') & 07777);
        self::assertSame(0660, fileperms($this->dir . '/log') & 0777);
    }

    /** A symlink into a world-writable directory gets the plain write, never a permission change. */
    public function testSymlinkToUntrustedDirectoryFallsBack(): void
    {
        $open = $this->dir . '/open';
        mkdir($open);
        chmod($open, 0777);
        $link = $this->dir . '/link';
        symlink($open, $link);
        self::assertSame(4, sharedFilePutContents($link . '/log', 'data'));
        self::assertTrue(sharedDirectory($link . '/tenant'));
        $diagnostics = file_get_contents($this->dir . '/diagnostics.log');
        self::assertSame(1, substr_count($diagnostics, 'Untrusted shared-file parent: ' . $open));
        clearstatcache();
        self::assertSame(0600, fileperms($open . '/log') & 0777);
        self::assertSame(0700, fileperms($open . '/tenant') & 07777);
    }

    /** An unknown group keeps the plain write instead of losing the log or granting public access. */
    public function testUnknownGroupFallsBackToPlainWrite(): void
    {
        putenv('SALDI_SHARED_FILE_GROUP=saldi_missing_' . bin2hex(random_bytes(8)));
        self::assertSame(4, sharedFilePutContents($this->dir . '/log', 'data'));
        self::assertSame(4, sharedFilePutContents($this->dir . '/log', 'more', true));
        $diagnostics = file_get_contents($this->dir . '/diagnostics.log');
        self::assertSame(1, substr_count($diagnostics, 'unavailable'));
        clearstatcache();
        self::assertSame(0600, fileperms($this->dir . '/log') & 0777);
        self::assertSame('datamore', file_get_contents($this->dir . '/log'));
    }

    /** A configured group always wins over the balances and www-data defaults. */
    public function testConfiguredGroupNameWins(): void
    {
        putenv('SALDI_SHARED_FILE_GROUP=saldi-runtime');
        self::assertSame('saldi-runtime', sharedFileGroupName());
    }

    /** A conventional umask must never create world-readable logs on the fallback. */
    public function testFallbackCreationExcludesOthersAndPreservesExistingModes(): void
    {
        umask(0022);
        chmod($this->dir, 0777);
        try {
            foreach (['a', 'w', 'x', 'c', 'r+'] as $mode) {
                $path = $this->dir . '/mode-' . $mode;
                if ($mode === 'r+') {
                    file_put_contents($path, 'old');
                    chmod($path, 0644);
                }
                $stream = sharedFileOpen($path, $mode);
                self::assertIsResource($stream);
                fclose($stream);
                clearstatcache(true, $path);
                self::assertSame($mode === 'r+' ? 0644 : 0640, fileperms($path) & 0777);
                self::assertSame(0022, umask());
            }
            $existing = $this->dir . '/existing';
            file_put_contents($existing, 'private');
            chmod($existing, 0664);
            self::assertSame(7, sharedFilePutContents($existing, 'changed'));
            clearstatcache(true, $existing);
            self::assertSame(0664, fileperms($existing) & 0777);
        } finally {
            chmod($this->dir, 0700);
        }
    }

    /** A symlink to a FIFO must be refused without blocking a web worker. */
    public function testSymlinkedFifoIsRefusedBeforeOpening(): void
    {
        $fifo = $this->dir . '/fifo';
        self::assertTrue(posix_mkfifo($fifo, 0600));
        $link = $this->dir . '/log';
        symlink($fifo, $link);
        $program = 'require ' . var_export(dirname(__DIR__, 3) . '/includes/sharedFile.php', true)
            . '; exit(sharedFileOpen(' . var_export($link, true) . ') === false ? 0 : 1);';
        // A regression must fail in seconds instead of hanging PHPUnit on the FIFO.
        $result = $this->runCommand(['timeout', '3', PHP_BINARY, '-r', $program]);
        self::assertSame(0, $result['status'], $result['output']);
        self::assertStringContainsString('special file', $result['output']);
    }

    /** A failed truncate must close the file and report failure before writing new bytes. */
    public function testFailedTruncationClosesStreamAndReportsFailure(): void
    {
        $result = $this->runFaultStream('SharedFileFaultStream::$truncateSucceeds = false; '
            . '$result = sharedFilePutContents("/shared-file-fixture/log", "new"); '
            . 'exit($result === false && SharedFileFaultStream::$closed === 1 '
            . '&& SharedFileFaultStream::$contents === "" ? 0 : 1);');
        self::assertSame(0, $result['status'], $result['output']);
        self::assertStringContainsString('Could not truncate', $result['output']);
    }

    /** Incomplete lines must not prevent later diagnostic lines from being attempted. */
    public function testDatabaseLogContinuesAfterIncompleteWrite(): void
    {
        $result = $this->runFaultStream('SharedFileFaultStream::$writeCounts = [2, 0]; '
            . '$result = db_log_append("/shared-file-fixture/log", ["first\n", "later\n"]); '
            . 'echo SharedFileFaultStream::$contents; '
            . 'exit($result === false && SharedFileFaultStream::$closed === 1 '
            . '&& str_contains(SharedFileFaultStream::$contents, "later\n") ? 0 : 1);');
        self::assertSame(0, $result['status'], $result['output']);
    }

    /** Reusing an operation's validated parent avoids a second walk without caching filesystem trust. */
    public function testFileOpenChecksParentOncePerOperation(): void
    {
        $result = $this->runFaultStream('$stream = sharedFileOpen("/shared-file-fixture/log"); '
            . 'if ($stream === false) { exit(2); } fclose($stream); '
            . 'if (SharedFileFaultStream::$parentStats !== 1) { exit(1); } '
            . '$stream = sharedFileOpen("/shared-file-fixture/log"); '
            . 'if ($stream === false) { exit(2); } fclose($stream); '
            . 'exit(SharedFileFaultStream::$parentStats === 2 ? 0 : 1);');
        self::assertSame(0, $result['status'], $result['output']);
    }

    /** Only the installation's temp is eligible, including a trusted symlink to it. */
    public function testMigrationRejectsOtherTreesAndContinuesPastRefusedEntries(): void
    {
        if (posix_geteuid() !== 0) {
            self::markTestSkipped('Administrator migration requires root, as in the CI web container.');
        }
        $runtime = $this->dir . '/runtime';
        mkdir($runtime);
        $script = $this->migrationInstallation($runtime);
        $outside = $this->dir . '/outside';
        mkdir($outside);
        file_put_contents($outside . '/keep', 'untouched');
        foreach (['/', $outside] as $path) {
            $result = $this->runCommand([PHP_BINARY, $script, $path]);
            self::assertSame(1, $result['status'], $result['output']);
        }
        self::assertSame(0700, fileperms($outside) & 07777);
        self::assertSame(0600, fileperms($outside . '/keep') & 07777);
        $unsafe = $this->dir . '/unsafe';
        mkdir($unsafe);
        chmod($unsafe, 0777);
        symlink($runtime, $unsafe . '/alias');
        $result = $this->runCommand([PHP_BINARY, $script, $unsafe . '/alias/']);
        self::assertSame(1, $result['status'], $result['output']);
        self::assertStringContainsString('untrusted runtime path', $result['output']);

        // The first entry is refused, but the later regular log still receives shared permissions.
        symlink($outside . '/keep', $runtime . '/a-link');
        self::assertTrue(posix_mkfifo($runtime . '/b-fifo', 0600));
        file_put_contents($runtime . '/z-log', 'preserved');
        $result = $this->runCommand([PHP_BINARY, $script, dirname($script, 2) . '/temp']);
        self::assertSame(1, $result['status'], $result['output']);
        clearstatcache();
        self::assertSame(0660, fileperms($runtime . '/z-log') & 07777);
        self::assertSame('preserved', file_get_contents($runtime . '/z-log'));
        self::assertSame(0600, fileperms($outside . '/keep') & 07777);
        unlink($runtime . '/a-link');
        unlink($runtime . '/b-fifo');
        $result = $this->runCommand([PHP_BINARY, $script, dirname($script, 2) . '/temp']);
        self::assertSame(0, $result['status'], $result['output']);
    }

    /** Without configuration, balances is used only while www-data belongs to it. */
    public function testBalancesGroupIsPreferredWhenWebUserIsMember(): void
    {
        if (posix_geteuid() !== 0) {
            self::markTestSkipped('Creating the balances group requires root, as in the CI web container.');
        }
        if (posix_getpwnam('www-data') === false || posix_getgrnam('balances') !== false) {
            self::markTestSkipped('Requires a www-data account and no existing balances group.');
        }
        putenv('SALDI_SHARED_FILE_GROUP');
        self::assertSame(0, $this->runCommand(['groupadd', 'balances'])['status']);
        try {
            self::assertSame('www-data', sharedFileGroupName());
            $added = $this->runCommand(['usermod', '-a', '-G', 'balances', 'www-data']);
            self::assertSame(0, $added['status'], $added['output']);
            self::assertSame('balances', sharedFileGroupName());
        } finally {
            $this->runCommand(['groupdel', 'balances']);
        }
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
            . '$result = sharedFilePutContents(' . $file . ', "lost"); '
            . 'exit($result === false ? 0 : 1);');
        self::assertSame(0, $before['status'], $before['output']);
        self::assertStringContainsString('migration', $before['output']);
        self::assertSame("old\n", file_get_contents($path));

        $migration = $this->runCommand([PHP_BINARY, $this->migrationInstallation($runtime), $runtime]);
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
     * Inject controlled local-file failures without replacing PHPUnit's own filesystem wrapper.
     *
     * @param string $code Assertions executed with the fake file wrapper.
     * @return array{status: int, output: string} Exit code and captured diagnostics.
     */
    private function runFaultStream(string $code): array
    {
        return $this->runPhp('require ' . var_export(dirname(__DIR__, 3) . '/includes/db_query.php', true)
            . '; require ' . var_export(__DIR__ . '/support/SharedFileFaultStream.php', true)
            . '; stream_wrapper_unregister("file"); stream_wrapper_register("file", SharedFileFaultStream::class); '
            . $code);
    }

    /**
     * Copy the migration into a disposable installation whose temp may point to the fixture runtime.
     *
     * @param string $runtime Temporary tree to migrate; never the checkout's real temp.
     * @return string Migration script inside the disposable installation.
     */
    private function migrationInstallation(string $runtime): string
    {
        $installation = $this->dir . '/installation';
        mkdir($installation);
        mkdir($installation . '/tools');
        mkdir($installation . '/includes');
        copy(dirname(__DIR__, 3) . '/tools/migrate_shared_runtime.php', $installation . '/tools/migrate_shared_runtime.php');
        copy(dirname(__DIR__, 3) . '/includes/sharedFile.php', $installation . '/includes/sharedFile.php');
        symlink($runtime, $installation . '/temp');
        return $installation . '/tools/migrate_shared_runtime.php';
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
