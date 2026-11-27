<?php
// 20261127 CDX/NTR Keep database/login logs outside writable application ancestors.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/db_query.php';

final class ApplicationLogTest extends TestCase
{
    private string $directory;
    private string|false $originalRoot;
    private string|false $originalGroup;
    private string $originalErrorLog;
    private ?string $originalRequestUri;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/saldi_logs_' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $this->originalRoot = getenv('SALDI_LOG_DIR');
        $this->originalGroup = getenv('SALDI_SHARED_FILE_GROUP');
        putenv('SALDI_LOG_DIR=' . $this->directory);
        putenv('SALDI_SHARED_FILE_GROUP=' . posix_getgrgid(posix_getegid())['name']);
        $this->originalErrorLog = ini_get('error_log');
        ini_set('error_log', $this->directory . '/diagnostics.log');
        $this->originalRequestUri = $_SERVER['REQUEST_URI'] ?? null;
    }

    protected function tearDown(): void
    {
        putenv($this->originalRoot === false ? 'SALDI_LOG_DIR' : 'SALDI_LOG_DIR=' . $this->originalRoot);
        putenv($this->originalGroup === false ? 'SALDI_SHARED_FILE_GROUP' : 'SALDI_SHARED_FILE_GROUP=' . $this->originalGroup);
        ini_set('error_log', $this->originalErrorLog);
        if ($this->originalRequestUri === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $this->originalRequestUri;
        }
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->directory);
    }

    public function testDatabaseSelectChoosesPrivateLogDirectory(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../includes/db_query.php');
        $start = strpos($source, 'function db_select(');
        self::assertNotFalse($start);
        $start = strpos($source, '$temp = ', $start);
        $end = strpos($source, ';', $start);
        $_SERVER['REQUEST_URI'] = '/saldi/finans/kassekladde.php';
        $db = 'fixture';
        eval(substr($source, $start, $end - $start + 1));
        self::assertSame($this->directory . '/tenant-fixture', $temp);
        self::assertTrue(db_log_append($temp . '/.ht_select.log', "fixture\n"));
        self::assertSame("fixture\n", file_get_contents($temp . '/.ht_select.log'));
        self::assertSame(0700, fileperms($this->directory) & 0777, 'Existing log-root permissions must stay unchanged');
    }

    public function testLoginLogsArePrivateAndPreserveAppendSemantics(): void
    {
        self::assertSame(4, applicationLogPutContents('online.log', 'one '));
        self::assertSame(3, applicationLogPutContents('online.log', 'two'));
        self::assertSame('one two', file_get_contents($this->directory . '/online.log'));
        self::assertSame(0660, fileperms($this->directory . '/online.log') & 0777);
        self::assertSame(0700, fileperms($this->directory) & 0777);
    }

    public function testUnsafeRootIsRefusedWithoutChangingItsPermissions(): void
    {
        chmod($this->directory, 0777);
        try {
            self::assertFalse(applicationLogPutContents('online.log', 'private'));
            self::assertFalse(db_log_append($this->directory . '/.ht_modify.log', 'private'));
            self::assertFalse(file_exists($this->directory . '/online.log'));
            self::assertFalse(file_exists($this->directory . '/.ht_modify.log'));
            self::assertSame(0777, fileperms($this->directory) & 0777);
        } finally {
            chmod($this->directory, 0700);
        }
    }

    public function testTenantNamesCannotEscapeThePrivateRoot(): void
    {
        $path = applicationLogDirectory('../other');
        self::assertSame($this->directory . '/tenant-..%2Fother', $path);
        clearstatcache(true, $path);
        self::assertSame(02770, fileperms($path) & 07777);
        self::assertTrue(applicationLogTrustedPath($path . '/.ht_select.log'));
        self::assertFalse(applicationLogPutContents('../outside.log', 'private'));
        self::assertFalse(applicationLogTrustedPath($this->directory . '/../outside.log'));
    }

    public function testMissingRootIsNotCreatedByTheWebWorker(): void
    {
        putenv('SALDI_LOG_DIR=' . $this->directory . '/not-provisioned');
        self::assertFalse(applicationLogPutContents('online.log', 'private'));
        self::assertFalse(file_exists($this->directory . '/not-provisioned'));
    }
}
