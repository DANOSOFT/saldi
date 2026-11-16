<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261116 CL/MMR SST-984 (CodeRabbit on PR #1055): the e-invoice webhook (debitor/easyUBL.php) counts each
//                 incoming invoice in the master table regnskab before it stores the invoice and mails the
//                 receiver. A failed count must not stop the webhook: the count runs in webservice mode, where the
//                 real db_modify() logs the error and returns instead of showing an alert and exiting.
//
//                 easyUBL.php is an entry script that calls fixed external services (locator, storage), so it
//                 is not run as a whole. The test runs the committed code itself: increaseEinvoiceCount() and the
//                 block that calls it are read from easyUBL.php and executed in the global scope, as the script
//                 does, with the real includes/db_query.php against an owned PostgreSQL fixture. The counter
//                 update is made to fail for real (missing column, and a trigger that rejects it). The process
//                 reaching the next statement is what lets the storage and mail part run; an exit would end the
//                 separate test process and fail the test.
//
// History:
// 20261116 CL/MMR SST-984 created.

#[RunTestsInSeparateProcesses]
final class EinvoiceCountWebhookTest extends TestCase
{
    private string $master = '';
    private string $tenant = '';
    private string $work = '';
    /** @var \PgSql\Connection */
    private $masterConnection;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $name = TestDatabase::sqlFixture(static::class)->name();
        $suffix = bin2hex(random_bytes(5));
        $this->master = 'einv_master_' . $suffix;
        $this->tenant = 'einv_tenant_' . $suffix;

        // The tenant connection is the script's $connection; the master connection is what db_modify(..., true) uses.
        $tenantConnection = TestDatabase::connect($name);
        $this->masterConnection = TestDatabase::connect($name);
        TestDatabase::query($tenantConnection, "CREATE SCHEMA {$this->tenant}");
        TestDatabase::query($tenantConnection, "SET search_path TO {$this->tenant}");
        TestDatabase::query($tenantConnection, 'CREATE TABLE adresser (id serial PRIMARY KEY, art varchar(2), email text)');
        TestDatabase::query($tenantConnection, "INSERT INTO adresser (art, email) VALUES ('S', 'owner@example.invalid')");
        TestDatabase::query($this->masterConnection, "CREATE SCHEMA {$this->master}");
        TestDatabase::query($this->masterConnection, "SET search_path TO {$this->master}");
        TestDatabase::query($this->masterConnection, 'CREATE TABLE regnskab (id serial PRIMARY KEY, db varchar(25))');
        TestDatabase::query($this->masterConnection, "INSERT INTO regnskab (db) VALUES ('tenant_1'), ('tenant_2')");

        $GLOBALS['connection'] = $tenantConnection;
        $GLOBALS['global_connection'] = $this->masterConnection;
        $GLOBALS['db_type'] = 'postgres';
        $GLOBALS['db'] = 'tenant_1';
        // 'develop' makes db_modify() print its error report instead of mailing fejl@saldi.dk.
        $GLOBALS['sqdb'] = 'develop';
        $GLOBALS['brugernavn'] = '';
        $GLOBALS['db_skriv_id'] = 0;
        $_SERVER['REQUEST_URI'] = '/debitor/easyUBL.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // db_modify() writes its log under temp/<db> relative to the page; for /debitor/easyUBL.php that is ./temp.
        // The shared log helpers use this process' own group, as in SharedFileTest (doc/runtime-permissions.md).
        putenv('SALDI_SHARED_FILE_GROUP=' . posix_getgrgid(posix_getegid())['name']);
        $this->work = sys_get_temp_dir() . '/' . $this->tenant;
        mkdir($this->work . '/temp/tenant_1', 0700, true);
        chdir($this->work);
        // The real alert() prints a script element; the same output here shows when the alert path is taken.
        eval('function alert($msg) { echo "<script>alert(\'$msg\');</script>"; }');
        require_once __DIR__ . '/../../../includes/db_query.php';
        eval(self::committedCode('function'));
    }

    protected function tearDown(): void
    {
        if ($this->master !== '') {
            pg_query($this->masterConnection, "DROP SCHEMA IF EXISTS {$this->master} CASCADE");
            pg_query($GLOBALS['connection'], "DROP SCHEMA IF EXISTS {$this->tenant} CASCADE");
            pg_close($this->masterConnection);
            pg_close($GLOBALS['connection']);
            exec('rm -rf ' . escapeshellarg($this->work));
        }
    }

    /**
     * Reads increaseEinvoiceCount() or the block that calls it from the committed debitor/easyUBL.php.
     * @param string $part 'function' or 'call'.
     * @return string PHP code without the opening tag.
     */
    private static function committedCode(string $part): string
    {
        // The file has Windows line endings.
        $source = (string)file_get_contents(__DIR__ . '/../../../debitor/easyUBL.php');
        $pattern = $part === 'function'
            ? '/^ *function increaseEinvoiceCount\(\$db\) \{\r?\n.*?^ *\}\r?$/ms'
            : '/^ *\$previousWebservice = .*?^ *\$webservice = \$previousWebservice;\r?$/ms';
        self::assertSame(1, preg_match($pattern, $source, $match), "the $part is in debitor/easyUBL.php");
        return $match[0];
    }

    /**
     * Runs the committed call block in the global scope with a given webservice mode before it.
     * @param bool|null $webservice The mode before the block; null means not set at all.
     * @return string What the block printed.
     */
    private static function runCallBlock(?bool $webservice): string
    {
        unset($GLOBALS['webservice'], $GLOBALS['db_modify_fejl']);
        if ($webservice !== null) {
            $GLOBALS['webservice'] = $webservice;
        }
        // pg_query() warns when the update fails, as it does in production; keep the warnings to check them.
        $GLOBALS['__warnings'] = [];
        set_error_handler(static function (int $no, string $msg): bool {
            $GLOBALS['__warnings'][] = $msg;
            return true;
        }, E_WARNING);
        ob_start();
        try {
            // The script runs at file scope, so its $webservice and $db are globals.
            eval('global $webservice, $db; ' . self::committedCode('call'));
        } finally {
            $out = (string)ob_get_clean();
            restore_error_handler();
        }
        return $out;
    }

    /** Asserts that the only warning was pg_query()'s report of the failed counter update. */
    private function assertOnlyTheQueryFailureWarned(string $reason): void
    {
        $this->assertCount(1, $GLOBALS['__warnings']);
        $this->assertStringContainsString('pg_query(): Query failed', $GLOBALS['__warnings'][0]);
        $this->assertStringContainsString($reason, $GLOBALS['__warnings'][0]);
    }

    private function invoices(string $db): ?string
    {
        $r = pg_fetch_row(TestDatabase::query($this->masterConnection, 'SELECT invoices FROM regnskab WHERE db = $1', [$db]));
        return $r ? $r[0] : null;
    }

    /** @return list<bool|null> */
    private static function priorModes(): array
    {
        return [null, false, true];
    }

    public function testAMissingCounterColumnDoesNotStopTheWebhook(): void
    {
        foreach (self::priorModes() as $mode) {
            $out = self::runCallBlock($mode);
            $this->assertStringNotContainsString('<script>alert(', $out, 'no alert, so no exit');
            $this->assertTrue($GLOBALS['db_modify_fejl'] ?? false, 'the failure is flagged');
            $this->assertSame($mode, $GLOBALS['webservice'] ?? null, 'the mode from before the count is back');
            $this->assertSame('1', pg_fetch_result(pg_query($GLOBALS['connection'], 'SELECT count(*) FROM adresser'), 0, 0), 'the tenant connection still works');
            $this->assertOnlyTheQueryFailureWarned('column "invoices" does not exist');
        }
        $this->assertStringContainsString('Fejl!!', (string)file_get_contents($this->work . '/temp/tenant_1/.ht_modify.log'), 'the failure is logged');
    }

    public function testARejectedCounterUpdateDoesNotStopTheWebhook(): void
    {
        $m = $this->masterConnection;
        TestDatabase::query($m, 'ALTER TABLE regnskab ADD COLUMN invoices int DEFAULT 0');
        TestDatabase::query($m, "CREATE FUNCTION refuse_count() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'counter refused'; END \$\$ LANGUAGE plpgsql");
        TestDatabase::query($m, 'CREATE TRIGGER refuse_count BEFORE UPDATE ON regnskab FOR EACH ROW EXECUTE FUNCTION refuse_count()');
        foreach (self::priorModes() as $mode) {
            $out = self::runCallBlock($mode);
            $this->assertStringNotContainsString('<script>alert(', $out);
            $this->assertTrue($GLOBALS['db_modify_fejl'] ?? false);
            $this->assertSame($mode, $GLOBALS['webservice'] ?? null);
            $this->assertOnlyTheQueryFailureWarned('counter refused');
        }
        $this->assertSame('0', $this->invoices('tenant_1'), 'nothing counted while the update is refused');
        $this->assertStringContainsString('counter refused', (string)file_get_contents($this->work . '/temp/tenant_1/.ht_modify.log'));

        // Once the update works again, the next webhook counts normally.
        TestDatabase::query($m, 'DROP TRIGGER refuse_count ON regnskab');
        $this->assertSame('', self::runCallBlock(false));
        $this->assertArrayNotHasKey('db_modify_fejl', $GLOBALS);
        $this->assertSame([], $GLOBALS['__warnings']);
        $this->assertSame('1', $this->invoices('tenant_1'));
    }

    public function testTheCountIsAddedToTheRightAccountOnly(): void
    {
        $m = $this->masterConnection;
        TestDatabase::query($m, 'ALTER TABLE regnskab ADD COLUMN invoices int');
        TestDatabase::query($m, "UPDATE regnskab SET invoices = 41 WHERE db = 'tenant_2'");
        $this->assertSame('', self::runCallBlock(null), 'nothing printed on success');
        $this->assertSame('1', $this->invoices('tenant_1'), 'NULL counts from zero');
        self::runCallBlock(null);
        $this->assertSame('2', $this->invoices('tenant_1'));
        $this->assertSame('41', $this->invoices('tenant_2'), 'another account is untouched');

        // A name with a quote is escaped and matches no account.
        $GLOBALS['db'] = "tenant_1' OR '1'='1";
        mkdir($this->work . "/temp/tenant_1' OR '1'='1", 0700, true);
        self::runCallBlock(null);
        $this->assertSame(['2', '41'], [$this->invoices('tenant_1'), $this->invoices('tenant_2')]);
    }
}
