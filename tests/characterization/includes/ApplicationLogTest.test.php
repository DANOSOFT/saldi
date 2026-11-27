<?php
// 20261127 CDX/NTR Keep database/login logs outside writable application ancestors.
// 20261127 CDX/NTR Cover login entry points, canonical web-tree boundaries and creation under trusted parents.
// 20261127 CDX/NTR Cover installation/database routing independently of URL depth and working directory.
// 20261127 CDX/NTR Cover first-use online events, temp aliases and checkout roots without web-server variables.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/db_query.php';

final class ApplicationLogTest extends TestCase
{
    private string $directory;
    private string $logger;
    private string $otherLogger;
    private string|false $originalGroup;
    private string|false $originalLogRoot;
    private string $originalErrorLog;
    private string $originalCwd;
    private array $originalGlobals;
    private array $originalServer;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/saldi_log_routing_' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $this->originalGroup = getenv('SALDI_SHARED_FILE_GROUP');
        $this->originalLogRoot = getenv('SALDI_LOG_DIR');
        putenv('SALDI_SHARED_FILE_GROUP=' . posix_getgrgid(posix_getegid())['name']);
        putenv('SALDI_LOG_DIR');
        $this->originalErrorLog = ini_get('error_log');
        ini_set('error_log', $this->directory . '/diagnostics.log');
        $this->originalCwd = getcwd();
        $this->originalGlobals = array_intersect_key($GLOBALS, array_flip(['db', 'sqdb']));
        $this->originalServer = $_SERVER;
        $GLOBALS['db'] = 'fixture';
        $GLOBALS['sqdb'] = 'master_fixture';
        $this->logger = $this->loadInstallation('ntr');
        $this->otherLogger = $this->loadInstallation('other');
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $_SERVER = $this->originalServer;
        foreach (['db', 'sqdb'] as $key) {
            unset($GLOBALS[$key]);
        }
        foreach ($this->originalGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
        }
        putenv($this->originalGroup === false ? 'SALDI_SHARED_FILE_GROUP' : 'SALDI_SHARED_FILE_GROUP=' . $this->originalGroup);
        putenv($this->originalLogRoot === false ? 'SALDI_LOG_DIR' : 'SALDI_LOG_DIR=' . $this->originalLogRoot);
        ini_set('error_log', $this->originalErrorLog);
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

    /** Load the production logger with __DIR__ pointing at an isolated installation fixture. */
    private function loadInstallation(string $name): string
    {
        $root = $this->directory . '/' . $name;
        mkdir($root, 0700);
        foreach (['includes', 'temp', 'finans', 'index'] as $folder) {
            mkdir($root . '/' . $folder, 0700);
        }
        $namespace = 'InstalledLog' . bin2hex(random_bytes(8));
        $helper = __DIR__ . '/../../../includes/applicationLog.php';
        if (!is_file($helper)) {
            return $namespace;
        }
        $source = file_get_contents($helper);
        $source = str_replace("require_once __DIR__ . '/sharedFile.php';", '', substr($source, 5));
        $source = str_replace('__DIR__', var_export($root . '/includes', true), $source);
        eval('namespace ' . $namespace . ';' . $source);
        return $namespace;
    }

    public function testLoadedCheckoutIsTheRootWithoutWebServerVariables(): void
    {
        unset($_SERVER['REQUEST_URI'], $_SERVER['DOCUMENT_ROOT'], $_SERVER['SCRIPT_FILENAME']);
        self::assertSame(dirname(__DIR__, 3) . '/temp/fixture', applicationLogDirectory('fixture'));
    }

    public function testDatabaseWritersResolveTheLoadedInstallationWithoutUriOrCwdGuessing(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../includes/db_query.php');
        $db = 'fixture';
        foreach (['/ntr/index/main.php', '/ntr/lager/lister/varer.php?x=1', '/other/index/main.php'] as $uri) {
            $_SERVER['REQUEST_URI'] = $uri;
            foreach ([$this->directory, $this->directory . '/ntr/finans'] as $cwd) {
                chdir($cwd);
                foreach (['db_select', 'db_modify', 'transaktion', 'injecttjek'] as $function) {
                    $start = strpos($source, 'function ' . $function . '(');
                    self::assertNotFalse($start);
                    $start = strpos($source, '$temp = ', $start);
                    $end = strpos($source, ';', $start);
                    eval('namespace ' . $this->logger . ';' . substr($source, $start, $end - $start + 1));
                    self::assertSame($this->directory . '/ntr/temp/fixture', $temp);
                }
            }
        }
    }

    public function testInstallationsKeepTheSameDatabaseNameSeparate(): void
    {
        $first = ($this->logger . '\\applicationLogDirectory')('fixture');
        $second = ($this->otherLogger . '\\applicationLogDirectory')('fixture');
        self::assertSame($this->directory . '/ntr/temp/fixture', $first);
        self::assertSame($this->directory . '/other/temp/fixture', $second);
    }

    public function testFirstDatabaseDirectoryCreationStillWritesItsOnlineEvent(): void
    {
        $db = $brugernavn = 'fixture';
        $s_id = 'fixture-session';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        ($this->logger . '\\applicationLogDirectory')($db);
        self::assertFalse(is_dir($this->directory . '/ntr/temp/fixture'), 'Resolving injecttjek log paths must not create the directory');
        $source = file_get_contents(__DIR__ . '/../../../includes/db_query.php');
        $start = strpos($source, '$runtimeTemp = ', strpos($source, 'function db_select('));
        $end = strpos($source, 'if ($db_type', $start);
        eval('namespace ' . $this->logger . ';' . substr($source, $start, $end - $start));
        self::assertStringContainsString('fixture-session', file_get_contents($this->directory . '/ntr/temp/fixture/.ht_online.log'));
    }

    public function testLoggingChecksLocationWithoutChangingExistingPermissions(): void
    {
        $root = $this->directory . '/ntr';
        mkdir($root . '/temp/fixture', 0777);
        foreach ([$root, $root . '/temp', $root . '/temp/fixture'] as $path) {
            chmod($path, 0777);
        }
        $write = $this->logger . '\\applicationLogPutContents';
        self::assertSame(4, $write('online.log', 'one ', true, 'fixture'));
        self::assertSame(3, $write('online.log', 'two', true, 'fixture'));
        $path = $root . '/temp/fixture/online.log';
        self::assertSame('one two', file_get_contents($path));
        chmod($path, 0640);
        self::assertSame(1, $write('online.log', '!', true, 'fixture'));
        clearstatcache();
        self::assertSame(0640, fileperms($path) & 0777);
        foreach ([$root, $root . '/temp', $root . '/temp/fixture'] as $folder) {
            self::assertSame(0777, fileperms($folder) & 0777);
        }
        $diagnostics = file_exists($this->directory . '/diagnostics.log') ? file_get_contents($this->directory . '/diagnostics.log') : '';
        self::assertStringNotContainsString('Untrusted shared-file parent', $diagnostics);
    }

    public function testLogLocationRejectsTheTempRootAnotherInstallationAndTraversal(): void
    {
        ($this->logger . '\\applicationLogDirectory')('fixture');
        $allowed = $this->logger . '\\applicationLogPathAllowed';
        self::assertTrue($allowed($this->directory . '/ntr/temp/fixture/online.log'));
        foreach (['/temp/online.log', '/online.log', '/temp/fixture/../online.log', '/../other/temp/fixture/online.log'] as $suffix) {
            self::assertFalse($allowed($this->directory . '/ntr' . $suffix));
        }
        self::assertFalse(($this->logger . '\\applicationLogPutContents')('../outside.log', 'no', true, 'fixture'));
        self::assertFalse(($this->logger . '\\applicationLogPutContents')('online.log', 'no', true, '../other'));
    }

    public function testDatabaseLogLinesAndErrorMarkersUseTheSameScopedFolder(): void
    {
        chmod($this->directory . '/ntr/temp', 0777);
        $folder = ($this->logger . '\\applicationLogDirectory')('fixture');
        $append = $this->logger . '\\applicationLogAppend';
        self::assertTrue($append($folder . '/.ht_select.log', ["first\n", "second\n"]));
        self::assertSame("first\nsecond\n", file_get_contents($folder . '/.ht_select.log'));
        self::assertTrue($append($folder . '/selectfejl.txt', 'old', 'w'));
        self::assertTrue($append($folder . '/selectfejl.txt', 'new', 'w'));
        self::assertSame('new', file_get_contents($folder . '/selectfejl.txt'));
        self::assertFalse($append($this->directory . '/ntr/temp/selectfejl.txt', 'unscoped'));
        clearstatcache();
        self::assertSame(02770, fileperms($folder) & 07777);
        self::assertSame(0660, fileperms($folder . '/.ht_select.log') & 0777);
        self::assertSame(0777, fileperms($this->directory . '/ntr/temp') & 0777);
    }

    public function testSymlinkCannotRedirectLogsOutsideTheDatabaseFolder(): void
    {
        $root = $this->directory . '/ntr/temp';
        mkdir($this->directory . '/outside', 0700);
        symlink($this->directory . '/outside', $root . '/fixture');
        self::assertFalse(($this->logger . '\\applicationLogPutContents')('online.log', 'no', true, 'fixture'));
        self::assertFalse(file_exists($this->directory . '/outside/online.log'));
        unlink($root . '/fixture');
        mkdir($root . '/fixture', 0700);
        file_put_contents($this->directory . '/outside/keep', 'keep');
        symlink($this->directory . '/outside/keep', $root . '/fixture/online.log');
        self::assertFalse(($this->logger . '\\applicationLogPutContents')('online.log', 'overwrite', false, 'fixture'));
        self::assertSame('keep', file_get_contents($this->directory . '/outside/keep'));
    }

    public function testUnknownDatabaseDoesNotWriteAnUnscopedLog(): void
    {
        unset($GLOBALS['db'], $GLOBALS['sqdb']);
        self::assertFalse(($this->logger . '\\applicationLogPutContents')('online.log', 'no'));
        self::assertFalse(file_exists($this->directory . '/ntr/temp/online.log'));
    }

    public function testTempAliasCannotSendLogsIntoAnotherInstallation(): void
    {
        rmdir($this->directory . '/ntr/temp');
        symlink($this->directory . '/other/temp', $this->directory . '/ntr/temp');
        self::assertFalse(($this->logger . '\\applicationLogPutContents')('online.log', 'no', true, 'fixture'));
        self::assertFalse(is_dir($this->directory . '/other/temp/fixture'));
    }

    public function testMasterDatabaseIsUsedBeforeATenantIsSelected(): void
    {
        $GLOBALS['db'] = '';
        self::assertSame($this->directory . '/ntr/temp/master_fixture', ($this->logger . '\\applicationLogDirectory')());
    }

    public function testExternalLogOverrideDoesNotChangeTheInstallationDestination(): void
    {
        putenv('SALDI_LOG_DIR=' . $this->directory . '/external');
        self::assertSame($this->directory . '/ntr/temp/fixture', ($this->logger . '\\applicationLogDirectory')('fixture'));
        self::assertFalse(file_exists($this->directory . '/external'));
    }

    public function testAllLoginEntryPointsWriteToTheirDatabaseFolder(): void
    {
        $db = $regnskab = $brugernavn = $spor = 'fixture';
        $s_id = 'fixture-session';
        $loginLogName = '.ht_fixture.log';
        foreach (['index/login.php', 'includes/login.php', 'soapserver/logon.php'] as $file) {
            $source = file_get_contents(__DIR__ . '/../../../' . $file);
            preg_match_all('/\bapplicationLogPutContents\(.*?\);/s', $source, $calls);
            self::assertCount($file === 'soapserver/logon.php' ? 1 : 2, $calls[0]);
            foreach ($calls[0] as $call) {
                eval('namespace ' . $this->logger . ';' . $call);
            }
        }
        foreach (['.ht_fixture.log', '.ht_online.log', 'online.log'] as $filename) {
            self::assertStringContainsString('fixture', file_get_contents($this->directory . '/ntr/temp/fixture/' . $filename));
        }
    }

    public function testVatDiagnosticUsesTheSameDatabaseLocationPolicy(): void
    {
        $db = 'fixture';
        $varenr = 'fixture-item';
        $varemomssats = 25;
        $vatAccount = 1000;
        $source = file_get_contents(__DIR__ . '/../../../includes/ordrefunc.php');
        preg_match_all('/\bapplicationLogPutContents\(.*?\);/s', $source, $calls);
        self::assertCount(1, $calls[0]);
        eval('namespace ' . $this->logger . ';' . $calls[0][0]);
        self::assertStringContainsString('fixture-item', file_get_contents($this->directory . '/ntr/temp/fixture/momslog.log'));
    }
}
