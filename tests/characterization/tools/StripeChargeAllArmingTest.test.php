<?php
// tests/characterization/tools/StripeChargeAllArmingTest.test.php
//
// tools/stripe_charge_due.php --all collects in every open ledger with Stripe switched on that is armed on this
// server (review round 1, I15). A ledger keeps its Stripe settings, live keys included, when it is copied or restored,
// so a ledger other than the pinned one (SALDI_STRIPE_DB) runs only when its settings were saved in it on this server:
// target_db is the ledger and armed_for is stripe_arming_key() of this host and master database. An enabled ledger
// that does not match is skipped with the log line "skipped=not_armed"; the pinned ledger runs regardless.
//
// The job is run as cron runs it, in a child process, with --dry-run, so it creates no PaymentIntent and sends nothing.
// Each ledger the job runs prints "<ledger>: stripe_charge summary" (from its own child, also when it stops with an error).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261126 CL/ASR Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class StripeChargeAllArmingTest extends CharacterizationTestCase
{
    protected function setUp(): void
    {
        self::stripeSettings(['enabled' => 'on', 'mode' => 'test', 'target_db' => CharacterizationEnv::testDb(), 'armed_for' => null]);
    }

    public function testAnEnabledLedgerNotArmedOnThisServerIsSkippedAndLogged(): void
    {
        $ledger = CharacterizationEnv::testDb();
        foreach (['saved before armed_for existed' => null, 'saved on another server' => hash('sha256', 'another-host|db|saldi|' . $ledger)] as $case => $armedFor) {
            self::stripeSettings(['armed_for' => $armedFor]);
            $out = $this->runAll();
            self::assertStringContainsString("$ledger: stripe_charge skipped=not_armed", $out, $case);
            self::assertStringNotContainsString("$ledger: stripe_charge summary", $out, $case);
        }
        self::stripeSettings(['target_db' => 'another_ledger', 'armed_for' => $this->armingKey('another_ledger')]);
        self::assertStringContainsString("$ledger: stripe_charge skipped=not_armed", $this->runAll(), 'settings copied from another ledger');
    }

    public function testALedgerArmedOnThisServerRuns(): void
    {
        $ledger = CharacterizationEnv::testDb();
        self::stripeSettings(['armed_for' => $this->armingKey($ledger)]);
        $out = $this->runAll();
        self::assertStringContainsString("$ledger: stripe_charge summary", $out);
        self::assertStringNotContainsString("$ledger: stripe_charge skipped", $out);
    }

    public function testThePinnedLedgerRunsWithoutArmingAndADisabledLedgerIsLeftQuietly(): void
    {
        $ledger = CharacterizationEnv::testDb();
        $out = $this->runAll($ledger);
        self::assertStringContainsString("$ledger: stripe_charge summary", $out);
        self::assertStringNotContainsString("$ledger: stripe_charge skipped", $out);
        self::stripeSettings(['enabled' => 'off']);
        $out = $this->runAll();
        self::assertStringNotContainsString("$ledger: stripe_charge", $out);
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

    /** The armed_for value a save on this server stores, as a CLI job computes it. */
    private function armingKey(string $ledger): string
    {
        $res = self::runChild('run_stripe_arming_key.php', [$ledger]);
        self::assertSame(0, $res['exit'], $res['stderr']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $res['stdout']);
        return $res['stdout'];
    }

    /**
     * Run tools/stripe_charge_due.php --all --dry-run as cron does.
     *
     * @param string $pinned The pinned ledger (SALDI_STRIPE_DB), or '' for none.
     * @return string What the job printed, stdout and stderr.
     */
    private function runAll(string $pinned = ''): string
    {
        $env = getenv();
        unset($env['SALDI_STRIPE_DB']);
        if ($pinned !== '') {
            $env['SALDI_STRIPE_DB'] = $pinned;
        }
        $err = tmpfile();
        $proc = proc_open([PHP_BINARY, CharacterizationEnv::repoRoot() . '/tools/stripe_charge_due.php', '--all', '--dry-run', '--today=2026-11-26'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => $err], $pipes, null, $env);
        self::assertIsResource($proc);
        $out = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($proc);
        rewind($err);
        $out .= (string)stream_get_contents($err);
        fclose($err);
        self::assertStringNotContainsString('Fatal error', $out);
        return $out;
    }
}
