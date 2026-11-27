<?php
// 20261127 CDX/NTR Keep database/login logs outside writable application ancestors.
// 20261127 CDX/NTR Cover login entry points, canonical web-tree boundaries and creation under trusted parents.

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
    private ?string $originalDocumentRoot;

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
        $this->originalDocumentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
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
        if ($this->originalDocumentRoot === null) {
            unset($_SERVER['DOCUMENT_ROOT']);
        } else {
            $_SERVER['DOCUMENT_ROOT'] = $this->originalDocumentRoot;
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

    public function testMissingRootIsCreatedOnlyUnderTrustedExistingParent(): void
    {
        $root = $this->directory . '/new-root';
        putenv('SALDI_LOG_DIR=' . $root);
        self::assertSame(7, applicationLogPutContents('online.log', 'private'));
        clearstatcache(true, $root);
        self::assertSame(02770, fileperms($root) & 07777);
        self::assertSame(0700, fileperms($this->directory) & 0777);
    }

    public function testMissingAncestorsAreNotCreated(): void
    {
        putenv('SALDI_LOG_DIR=' . $this->directory . '/missing-parent/logs');
        self::assertFalse(applicationLogPutContents('online.log', 'private'));
        self::assertFalse(file_exists($this->directory . '/missing-parent'));
    }

    public function testUnsafeParentPreventsCreatingLogRoot(): void
    {
        mkdir($this->directory . '/unsafe', 0777);
        chmod($this->directory . '/unsafe', 0777);
        putenv('SALDI_LOG_DIR=' . $this->directory . '/unsafe/logs');
        self::assertFalse(applicationLogPutContents('online.log', 'private'));
        self::assertFalse(is_dir($this->directory . '/unsafe/logs'));
        self::assertSame(0777, fileperms($this->directory . '/unsafe') & 0777);
    }

    public function testInstallationAndSymlinkAliasesAreNotLogStorage(): void
    {
        $installation = realpath(__DIR__ . '/../../..');
        foreach ([$installation, $installation . '/includes'] as $root) {
            putenv('SALDI_LOG_DIR=' . $root);
            self::assertFalse(applicationLogTrustedPath($root . '/online.log'));
        }
        symlink($installation . '/includes', $this->directory . '/alias');
        putenv('SALDI_LOG_DIR=' . $this->directory . '/alias');
        self::assertFalse(applicationLogPutContents('online.log', 'private'));
    }

    public function testMissingDirectoryBelowDocumentRootIsRejected(): void
    {
        mkdir($this->directory . '/public', 0700);
        $_SERVER['DOCUMENT_ROOT'] = $this->directory . '/public';
        putenv('SALDI_LOG_DIR=' . $this->directory . '/public/new-logs');
        self::assertFalse(applicationLogPutContents('online.log', 'private'));
        self::assertFalse(is_dir($this->directory . '/public/new-logs'));
    }

    public function testAllLoginEntryPointsWriteOutsideTheWebTree(): void
    {
        $db = $regnskab = $brugernavn = $spor = 'fixture';
        $s_id = 'fixture-session';
        $loginLogName = '.ht_fixture.log';
        foreach (['index/login.php', 'includes/login.php', 'soapserver/logon.php'] as $file) {
            $source = file_get_contents(__DIR__ . '/../../../' . $file);
            preg_match_all('/\bapplicationLogPutContents\(.*?\);/s', $source, $calls);
            self::assertCount($file === 'soapserver/logon.php' ? 1 : 2, $calls[0]);
            foreach ($calls[0] as $call) {
                eval($call);
            }
        }
        self::assertStringContainsString('fixture', file_get_contents($this->directory . '/.ht_fixture.log'));
        self::assertStringContainsString('fixture', file_get_contents($this->directory . '/.ht_online.log'));
        self::assertStringContainsString('fixture', file_get_contents($this->directory . '/online.log'));
    }
}
