<?php
// tests/characterization/includes/LedgerAllowListSessionTest.test.php
//
// $allowedLedgers in includes/connect.php: an installation that sets it serves a session only for a listed ledger
// (includes/online.php, includes/ledgerAllowList.php). A session whose row in `online` names another ledger is
// refused before anything is opened or updated: its logtime is left as it was. Without the setting, and for a
// listed ledger, the page runs as before.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261127 ASR Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class LedgerAllowListSessionTest extends CharacterizationTestCase
{
    /** @param array<string, mixed> $settings */
    private static function luk(array $settings): array
    {
        return self::runChild('run_page_with_install_settings.php', [CharacterizationEnv::sessionId(),
            json_encode((object) $settings), 'includes/luk.php', json_encode(['popup' => '1', 'returside' => 'rapport.php'])]);
    }

    private static function logtime(): ?string
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            $row = CharacterizationEnv::one($master, 'SELECT logtime FROM online WHERE session_id = $1', [CharacterizationEnv::sessionId()]);
        } finally {
            pg_close($master);
        }
        return $row === null ? null : (string) $row['logtime'];
    }

    private static function setLogtime(string $value): void
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'UPDATE online SET logtime = $1 WHERE session_id = $2', [$value, CharacterizationEnv::sessionId()]);
        } finally {
            pg_close($master);
        }
    }

    public function test_a_session_for_a_ledger_outside_the_list_is_refused_before_anything_is_written(): void
    {
        self::setLogtime('1000');
        $res = self::luk(['allowedLedgers' => ['saldi_other_ledger']]);

        $this->assertSame(1, $res['exit'], "refused.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        $this->assertStringContainsString('is not in $allowedLedgers', $res['stderr']);
        $this->assertStringNotContainsString('SaldiDialog.busy()', $res['stdout'], 'the page stopped at the session check');
        $this->assertSame('1000', self::logtime(), 'the session row is not touched');
    }

    public function test_a_listed_ledger_runs_as_before(): void
    {
        self::setLogtime('1000');
        $res = self::luk(['allowedLedgers' => ['saldi_other_ledger', CharacterizationEnv::testDb()]]);

        $this->assertSame(0, $res['exit'], "stdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        $this->assertStringContainsString('SaldiDialog.busy()', $res['stdout'], 'the page ran past the session check');
        $this->assertNotSame('1000', self::logtime(), 'the session is in use');
    }

    public function test_one_name_as_a_string_works_like_a_list(): void
    {
        $res = self::luk(['allowedLedgers' => CharacterizationEnv::testDb()]);
        $this->assertSame(0, $res['exit'], "stdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        $res = self::luk(['allowedLedgers' => 'saldi_other_ledger']);
        $this->assertSame(1, $res['exit']);
    }

    public function test_without_the_setting_every_ledger_runs(): void
    {
        $res = self::luk([]);
        $this->assertSame(0, $res['exit'], "stdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        $this->assertStringContainsString('SaldiDialog.busy()', $res['stdout'], 'the page ran past the session check');
    }
}
