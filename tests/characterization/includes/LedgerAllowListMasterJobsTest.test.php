<?php
// tests/characterization/includes/LedgerAllowListMasterJobsTest.test.php
//
// $allowedLedgers in includes/connect.php: the master database stays connected for the registry, but a command-line
// job that is named a ledger with --db works in it only when it is listed. Named the master database while the
// master is not listed, every single-ledger job ends with exit code 1 and the refusal before it reads its settings,
// takes its lock or writes anything; with the master listed, or without the setting, the job runs as before.
//
// The setting is given the way connect.php gives it, as a global variable, here through auto_prepend_file.
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261127 ASR Created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class LedgerAllowListMasterJobsTest extends TestCase
{
    private static string $prependDir = '';

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        self::$prependDir = sys_get_temp_dir() . '/saldi_allowlist_' . bin2hex(random_bytes(6));
        mkdir(self::$prependDir, 0700);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$prependDir !== '' && is_dir(self::$prependDir)) {
            array_map('unlink', glob(self::$prependDir . '/*') ?: []);
            rmdir(self::$prependDir);
        }
    }

    /**
     * Run a job of the repository on the command line, with $allowedLedgers set as connect.php sets it (null: not set).
     *
     * @param list<string> $args
     * @return array{exit:int, stdout:string, stderr:string}
     */
    private static function job(?array $allowed, string $script, array $args): array
    {
        $cmd = [PHP_BINARY];
        if ($allowed !== null) {
            $prepend = self::$prependDir . '/' . md5(json_encode($allowed)) . '.php';
            file_put_contents($prepend, '<?php $allowedLedgers = ' . var_export($allowed, true) . ";\n");
            $cmd[] = '-d';
            $cmd[] = 'auto_prepend_file=' . $prepend;
        }
        $cmd[] = CharacterizationEnv::repoRoot() . '/' . $script;
        $errFile = tmpfile();
        $proc = proc_open(array_merge($cmd, $args), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $errFile], $pipes, CharacterizationEnv::repoRoot());
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($proc);
        rewind($errFile);
        $stderr = (string) stream_get_contents($errFile);
        fclose($errFile);
        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private static function masterTable(string $table): ?string
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            $row = CharacterizationEnv::one($master, 'SELECT to_regclass($1) AS t', [$table]);
        } finally {
            pg_close($master);
        }
        return $row['t'] ?? null;
    }

    /** @return list<array{0:string, 1:list<string>}> */
    private static function singleLedgerJobs(string $master): array
    {
        return [
            ['tools/stripe_charge_due.php', ['--db=' . $master, '--dry-run']],
            ['tools/reminder_due.php', ['--db=' . $master, '--dry-run']],
            ['tools/stripe_invoice_pdf.php', ['--db=' . $master, '--id=1']],
            ['tools/stripe_print_link.php', ['--db=' . $master, '1']],
            ['tools/stripe_arm_ledgers.php', ['--db=' . $master]],
            ['tools/notification_digest.php', ['--db=' . $master, '--dry-run']],
            ['admin/stripe_setup.php', ['--db=' . $master, '--dry-run']],
        ];
    }

    public function test_every_single_ledger_job_refuses_the_master_database_while_it_is_not_listed(): void
    {
        $master = CharacterizationEnv::masterDb();
        $paymentsBefore = self::masterTable('stripe_payments');
        foreach (self::singleLedgerJobs($master) as [$script, $args]) {
            $res = self::job(['saldi_other_ledger'], $script, $args);
            $this->assertSame(1, $res['exit'], "$script is refused.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
            $this->assertStringContainsString("Database $master is not in \$allowedLedgers", $res['stderr'], $script);
            $this->assertStringNotContainsString('summary', $res['stdout'], "$script did not run its job");
        }
        if ($paymentsBefore === null) {
            $this->assertNull(self::masterTable('stripe_payments'), 'no ledger table was created in the master database');
        }
    }

    public function test_the_master_database_runs_a_job_when_it_is_listed_or_without_the_setting(): void
    {
        $master = CharacterizationEnv::masterDb();
        // stripe_arm_ledgers.php without --stamp only reads: the ledger's Stripe settings.
        foreach ([[$master], null] as $allowed) {
            $res = self::job($allowed, 'tools/stripe_arm_ledgers.php', ['--db=' . $master]);
            $this->assertSame(0, $res['exit'], "stdout: {$res['stdout']}\nstderr: {$res['stderr']}");
            $this->assertStringNotContainsString('$allowedLedgers', $res['stderr']);
        }
    }

    public function test_the_master_database_stays_connected_for_the_registry(): void
    {
        // A listed name the registry does not know: the job reads the registry in the master database and says so.
        $res = self::job(['saldi_no_such_ledger'], 'tools/stripe_arm_ledgers.php', ['--db=saldi_no_such_ledger']);
        $this->assertSame(1, $res['exit']);
        $this->assertStringContainsString('unknown or closed ledger saldi_no_such_ledger', $res['stderr']);
        $this->assertStringNotContainsString('$allowedLedgers', $res['stderr']);
    }
}
