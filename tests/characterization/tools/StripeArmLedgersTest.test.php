<?php
// tests/characterization/tools/StripeArmLedgersTest.test.php
//
// tools/stripe_arm_ledgers.php is the one-off deploy step for review round 1, S2. Since I15 a ledger other than the
// pinned one collects only when its Stripe settings were saved in it on this server (armed_for). A ledger saved in
// itself before that update (target_db = the ledger, no armed_for) is unarmed until it is saved again. The tool lists
// every enabled ledger's arming state and, with --stamp, stores armed_for for exactly those ledgers, as a save on this
// server does. It never stamps settings copied from another ledger or saved on another server, and without --stamp it
// writes nothing.
//
// The tool is run as an operator runs it, in a child process, with --db=<the test ledger>.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261126 CL/ASR Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class StripeArmLedgersTest extends CharacterizationTestCase
{
    protected function setUp(): void
    {
        self::stripeSettings(['enabled' => 'on', 'mode' => 'test', 'target_db' => CharacterizationEnv::testDb(), 'armed_for' => null]);
    }

    public function testALedgerSavedBeforeArmingIsListedAndStampedOnlyWithStamp(): void
    {
        $ledger = CharacterizationEnv::testDb();
        $res = $this->runTool([]);
        self::assertSame(0, $res['exit'], $res['out']);
        self::assertStringContainsString("$ledger: stripe_arm state=saved_before_arming action=run_with_stamp", $res['out']);
        self::assertNull(self::armedFor(), 'nothing is written without --stamp');

        $res = $this->runTool(['--stamp']);
        self::assertSame(0, $res['exit'], $res['out']);
        self::assertStringContainsString("$ledger: stripe_arm state=saved_before_arming action=stamped", $res['out']);
        // The value a save of the Stripe page on this server stores, which stripe_charge_due.php --all and stripe_ledger_armed() check.
        self::assertSame($this->armingKey($ledger), self::armedFor());

        $res = $this->runTool(['--stamp']);
        self::assertStringContainsString("$ledger: stripe_arm state=armed action=none", $res['out']);
    }

    public function testACopyOrALedgerSavedOnAnotherServerIsNeverStamped(): void
    {
        $ledger = CharacterizationEnv::testDb();
        $elsewhere = hash('sha256', 'another-host|db|saldi|' . $ledger);
        self::stripeSettings(['armed_for' => $elsewhere]);
        $res = $this->runTool(['--stamp']);
        self::assertStringContainsString("$ledger: stripe_arm state=other_server action=none", $res['out']);
        self::assertSame($elsewhere, self::armedFor());

        foreach (['copied from another ledger' => 'another_ledger', 'never saved' => null] as $case => $targetDb) {
            self::stripeSettings(['target_db' => $targetDb, 'armed_for' => null]);
            $res = $this->runTool(['--stamp']);
            self::assertStringContainsString("$ledger: stripe_arm state=not_saved_here action=none", $res['out'], $case);
            self::assertNull(self::armedFor(), $case);
        }
    }

    public function testThePinnedLedgerNeedsNoStampAndADisabledLedgerIsLeftQuietly(): void
    {
        $ledger = CharacterizationEnv::testDb();
        $res = $this->runTool(['--stamp'], $ledger);
        self::assertStringContainsString("$ledger: stripe_arm state=pinned action=none", $res['out']);
        self::assertNull(self::armedFor());

        self::stripeSettings(['enabled' => 'off']);
        $res = $this->runTool(['--stamp']);
        self::assertSame(0, $res['exit'], $res['out']);
        self::assertStringNotContainsString("$ledger: stripe_arm", $res['out']);
        self::assertNull(self::armedFor());
    }

    public function testAnUnknownLedgerIsRefused(): void
    {
        $res = $this->runTool([], '', 'no_such_ledger_s2');
        self::assertSame(1, $res['exit']);
        self::assertStringContainsString('unknown or closed ledger no_such_ledger_s2', $res['out']);
        $res = $this->runTool([], '', 'Not-A-Ledger');
        self::assertSame(1, $res['exit']);
        self::assertStringContainsString('--db must be a ledger db', $res['out']);
    }

    /** @param array<string, ?string> $settings var_grp 'stripe' rows to set (null deletes the row) */
    private static function stripeSettings(array $settings): void
    {
        foreach ($settings as $name => $value) {
            self::rows("DELETE FROM settings WHERE var_grp = 'stripe' AND var_name = $1", [$name]);
            if ($value !== null) {
                self::rows("INSERT INTO settings (var_grp, var_name, var_value, user_id, group_id) VALUES ('stripe', $1, $2, NULL, 0)", [$name, $value]);
            }
        }
    }

    private static function armedFor(): ?string
    {
        $row = self::one("SELECT var_value FROM settings WHERE var_grp = 'stripe' AND var_name = 'armed_for'");
        return $row === null ? null : (string)$row['var_value'];
    }

    /** The armed_for value a save on this server stores, as a CLI job computes it. */
    private function armingKey(string $ledger): string
    {
        $res = self::runChild('run_stripe_arming_key.php', [$ledger]);
        self::assertSame(0, $res['exit'], $res['stderr']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $res['stdout']);
        return $res['stdout'];
    }

    /**
     * Run tools/stripe_arm_ledgers.php --db=<ledger> as an operator does.
     *
     * @param list<string> $args Further arguments (--stamp).
     * @param string $pinned The pinned ledger (SALDI_STRIPE_DB), or '' for none.
     * @param string|null $ledger The --db value; null = the test ledger.
     * @return array{exit: int, out: string} Exit code and what the tool printed, stdout and stderr.
     */
    private function runTool(array $args, string $pinned = '', ?string $ledger = null): array
    {
        $env = getenv();
        unset($env['SALDI_STRIPE_DB']);
        if ($pinned !== '') {
            $env['SALDI_STRIPE_DB'] = $pinned;
        }
        $err = tmpfile();
        $proc = proc_open(array_merge([PHP_BINARY, CharacterizationEnv::repoRoot() . '/tools/stripe_arm_ledgers.php',
            '--db=' . ($ledger ?? CharacterizationEnv::testDb())], $args),
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => $err], $pipes, null, $env);
        self::assertIsResource($proc);
        $out = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($proc);
        rewind($err);
        $out .= (string)stream_get_contents($err);
        fclose($err);
        self::assertStringNotContainsString('Fatal error', $out);
        return ['exit' => $exit, 'out' => $out];
    }
}
