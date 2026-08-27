<?php
// tests/characterization/finans/MomsPeriodeLukMigrationHealthCheckTest.test.php
//
// Absent-schema regression test for SD-646: the R5 periodelaasning migration
// (includes/betweenUpdates.php) installs a table, a PL/pgSQL function, and a
// trigger on transaktioner, gated on trigger existence alone. That hid a
// partial install (e.g. the table created but the function/trigger step
// failing silently) from both the login-time repair and
// finans/moms_periode.php's own queries, which had no existence guard at all.
//
// Pins:
//   - moms_periode_luk_schema_status()/_ready() (includes/std_func.php)
//     correctly report every combination of present/absent objects, not just
//     trigger existence.
//   - moms_periode_luk_ensure_schema() self-heals from every partial state,
//     and is a safe no-op once everything already exists (acceptance
//     criterion 2).
//   - finans/moms_periode.php shows a friendly message and does not fatal
//     when the schema is entirely absent, and renders normally once it is
//     present (acceptance criterion 1).
//
// Requires the docker-compose stack - skips cleanly otherwise, like
// tests/restapi's suites.
//
// History:
// 20260815 CL/SZ SD-646: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/MomsPeriodeLukEnv.php';
require_once dirname(__DIR__, 3) . '/includes/db_query.php';
require_once dirname(__DIR__, 3) . '/includes/std_func.php';

final class MomsPeriodeLukMigrationHealthCheckTest extends TestCase
{
    /** @var resource|\PgSql\Connection */
    private static $tenant;

    public static function setUpBeforeClass(): void
    {
        $reason = MomsPeriodeLukEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        MomsPeriodeLukEnv::bootstrapTenant();
    }

    public static function tearDownAfterClass(): void
    {
        // Leave the schema fully healthy - this is a dedicated throwaway
        // tenant (not shared with any other suite), but re-running
        // ensure_schema() here doubles as one more no-op/idempotency check.
        if (self::$tenant) {
            self::pointAtTenant();
            moms_periode_luk_ensure_schema();
            pg_close(self::$tenant);
        }
    }

    protected function setUp(): void
    {
        self::pointAtTenant();
    }

    /** Bind the shared $connection/$db/$db_type globals db_select()/db_modify() read to this test's tenant. */
    private static function pointAtTenant(): void
    {
        global $connection, $db, $db_type;
        if (!self::$tenant) {
            self::$tenant = MomsPeriodeLukEnv::connect(MomsPeriodeLukEnv::testDb());
        }
        $connection = self::$tenant;
        $db = MomsPeriodeLukEnv::testDb();
        $db_type = 'postgresql';
        // get_relative() (includes/db_query.php) derives its "../" depth from
        // REQUEST_URI's slash count - unset here (no chdir, PHPUnit runs from
        // the repo root), which is fine functionally but noisy under E_ALL.
        $_SERVER['REQUEST_URI'] = '/x';
    }

    private function dropAll(): void
    {
        pg_query(self::$tenant, 'DROP TRIGGER IF EXISTS tr_check_moms_periode_luk ON transaktioner');
        pg_query(self::$tenant, 'DROP FUNCTION IF EXISTS check_moms_periode_luk()');
        pg_query(self::$tenant, 'DROP TABLE IF EXISTS moms_periode_luk');
    }

    private function objectExists(string $sql): bool
    {
        $r = pg_query(self::$tenant, $sql);
        return $r !== false && pg_num_rows($r) === 1;
    }

    private function tableExists(): bool
    {
        return $this->objectExists("SELECT 1 FROM information_schema.tables WHERE table_name = 'moms_periode_luk'");
    }

    private function functionExists(): bool
    {
        return $this->objectExists("SELECT 1 FROM pg_proc WHERE proname = 'check_moms_periode_luk'");
    }

    private function triggerExists(): bool
    {
        return $this->objectExists("SELECT 1 FROM pg_trigger WHERE tgname = 'tr_check_moms_periode_luk'");
    }

    public function test_status_reports_every_object_missing_when_nothing_is_installed(): void
    {
        $this->dropAll();

        $status = moms_periode_luk_schema_status();

        $this->assertFalse($status['table']);
        $this->assertFalse($status['function']);
        $this->assertFalse($status['trigger']);
        $this->assertFalse(moms_periode_luk_schema_ready());
    }

    public function test_ensure_schema_creates_everything_from_scratch(): void
    {
        $this->dropAll();

        $status = moms_periode_luk_ensure_schema();

        $this->assertTrue($status['table']);
        $this->assertTrue($status['function']);
        $this->assertTrue($status['trigger']);
        $this->assertTrue($this->tableExists());
        $this->assertTrue($this->functionExists());
        $this->assertTrue($this->triggerExists());
        $this->assertTrue(moms_periode_luk_schema_ready());
    }

    public function test_ensure_schema_heals_table_without_function_or_trigger(): void
    {
        // Simulates the exact partial state named in SD-646's scope: the
        // table was created but the function/trigger step never completed.
        $this->dropAll();
        moms_periode_luk_ensure_schema(); // get a clean table in place first
        pg_query(self::$tenant, 'DROP TRIGGER IF EXISTS tr_check_moms_periode_luk ON transaktioner');
        pg_query(self::$tenant, 'DROP FUNCTION IF EXISTS check_moms_periode_luk()');
        $this->assertTrue($this->tableExists());
        $this->assertFalse($this->functionExists());
        $this->assertFalse($this->triggerExists());

        $status = moms_periode_luk_ensure_schema();

        $this->assertTrue($status['function']);
        $this->assertTrue($status['trigger']);
        $this->assertTrue($this->functionExists());
        $this->assertTrue($this->triggerExists());
    }

    public function test_ensure_schema_heals_function_without_trigger(): void
    {
        // The one partial state the ORIGINAL trigger-only gate also handled -
        // pinned here so the rewrite doesn't regress the one case that used
        // to work.
        $this->dropAll();
        moms_periode_luk_ensure_schema();
        pg_query(self::$tenant, 'DROP TRIGGER IF EXISTS tr_check_moms_periode_luk ON transaktioner');
        $this->assertTrue($this->tableExists());
        $this->assertTrue($this->functionExists());
        $this->assertFalse($this->triggerExists());

        $status = moms_periode_luk_ensure_schema();

        $this->assertTrue($status['trigger']);
        $this->assertTrue($this->triggerExists());
    }

    public function test_ensure_schema_is_a_safe_noop_once_everything_exists(): void
    {
        $this->dropAll();
        moms_periode_luk_ensure_schema();

        // A second call must not error (e.g. "trigger already exists") and
        // must report the same fully-healthy status.
        $status = moms_periode_luk_ensure_schema();

        $this->assertTrue($status['table']);
        $this->assertTrue($status['function']);
        $this->assertTrue($status['trigger']);
    }

    public function test_page_shows_a_friendly_message_and_does_not_fatal_when_schema_is_absent(): void
    {
        $this->dropAll();

        $res = MomsPeriodeLukEnv::rows(self::$tenant, 'SELECT 1'); // sanity: tenant connection still usable
        $this->assertNotEmpty($res);

        $child = $this->runPage();

        $this->assertStringNotContainsStringIgnoringCase(
            'fatal error',
            $child['stdout'] . $child['stderr'],
            "the page must not fatal when moms_periode_luk is absent.\nstdout: {$child['stdout']}\nstderr: {$child['stderr']}"
        );
        $this->assertStringContainsString(
            'Periodelaasning er ved at blive aktiveret',
            $child['stdout'],
            'the page must show the friendly not-yet-migrated message'
        );
        $this->assertStringNotContainsString(
            'Masseluk',
            $child['stdout'],
            'the interactive locking UI must not render when the schema is absent'
        );
    }

    public function test_page_renders_normally_once_schema_is_healthy(): void
    {
        $this->dropAll();
        moms_periode_luk_ensure_schema();

        $child = $this->runPage();

        $this->assertStringNotContainsStringIgnoringCase(
            'fatal error',
            $child['stdout'] . $child['stderr'],
            "stdout: {$child['stdout']}\nstderr: {$child['stderr']}"
        );
        $this->assertStringNotContainsString(
            'Periodelaasning er ved at blive aktiveret',
            $child['stdout'],
            'the absent-schema message must not show once the migration has run'
        );
        $this->assertStringContainsString('Momsperioder', $child['stdout']);
    }

    /** @return array{stdout:string, stderr:string, exit:int} */
    private function runPage(): array
    {
        $cmd = [
            PHP_BINARY,
            __DIR__ . '/support/run_moms_periode_page.php',
            MomsPeriodeLukEnv::SESSION_ID,
        ];
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            $this->fail('could not start child php');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        return ['stdout' => (string)$stdout, 'stderr' => (string)$stderr, 'exit' => $exit];
    }
}
