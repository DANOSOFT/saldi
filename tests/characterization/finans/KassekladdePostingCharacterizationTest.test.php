<?php
// tests/characterization/finans/KassekladdePostingCharacterizationTest.test.php
//
// Characterization tests for the kassekladde posting engine (SD-601).
//
// These pin the CURRENT behavior of finans/bogfor.php's bogfor() as driven
// through the page's own POST dispatch (see support/run_bogfor_page.php):
// a balanced two-account voucher, a VAT'd voucher, a real posting with
// kontoplan.saldo effects, and the unbalanced-kladde failure mode.
//
// Requires the docker-compose stack (postgres reachable, template tenant
// installed) - skips cleanly otherwise. See tests/characterization/README.md.
//
// History:
// 20260723 CL/LH SD-601: created.
// 20260916 CDX/LH Isolate disposable test tenants and retain child-process failure diagnostics.
// 20261022 CL/NTR Dropped the no-op `use CharacterizationEnv;` (PHP warning on load) and the unused
//                 saldiFor(), which called a rows() helper this class does not have.
// 20261111 CDX/NTR Isolate the test class database and session.
// 20261117 CL/ASR Two concurrent Bogfør requests for one journal post it once (bookkeeping F1).
// 20261124 CL/ASR The concurrent test starts the second request once the first waits for the journal row.
//                 Started together, the second request did not reach the posting in 1 of about 10 runs, and a miss now reports each request's exit code and output.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class KassekladdePostingCharacterizationTest extends TestCase
{
    /** @var resource|\PgSql\Connection */
    private static $tenant;
    private static int $regnaar;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant(static::class);
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $ra = CharacterizationEnv::one(self::$tenant, "SELECT max(kodenr) AS ra FROM grupper WHERE art = 'RA'");
        self::$regnaar = (int)$ra['ra'];
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            pg_close(self::$tenant);
            self::$tenant = null;
            CharacterizationEnv::teardownTenant();
        }
    }

    /** Two finance accounts without a VAT code, for plain balanced entries. */
    private function plainAccounts(): array
    {
        $rows = CharacterizationEnv::rows(
            self::$tenant,
            "SELECT kontonr FROM kontoplan
             WHERE regnskabsaar = $1 AND kontotype = 'D' AND (moms IS NULL OR moms = '')
             ORDER BY kontonr LIMIT 2",
            [self::$regnaar]
        );
        $this->assertCount(2, $rows, 'template tenant must have >= 2 plain finance accounts');
        return [(int)$rows[0]['kontonr'], (int)$rows[1]['kontonr']];
    }

    /** A sales account carrying an S-type VAT code, plus its VAT rate/account from grupper. */
    private function vatSalesAccount(): array
    {
        $acct = CharacterizationEnv::one(
            self::$tenant,
            "SELECT kontonr, moms FROM kontoplan
             WHERE regnskabsaar = $1 AND moms LIKE 'S%' ORDER BY kontonr LIMIT 1",
            [self::$regnaar]
        );
        $this->assertNotNull($acct, 'template tenant must have an S-VAT-coded sales account');
        $kode = (int)substr(trim($acct['moms']), 1);
        $grp = CharacterizationEnv::one(
            self::$tenant,
            "SELECT box1, box2 FROM grupper WHERE art = 'SM' AND kodenr = $1",
            [$kode]
        );
        $this->assertNotNull($grp, 'SM VAT group missing for code ' . $kode);
        return [(int)$acct['kontonr'], (int)$grp['box1'], (float)$grp['box2']]; // [account, vat account, vat %]
    }

    private function createKladde(array $lines): int
    {
        $kladdeId = CharacterizationEnv::nextId(self::$tenant, 'kladdeliste');
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO kladdeliste (id, kladdedate, bogfort, kladdenote, oprettet_af)
             VALUES ($1, CURRENT_DATE, '', 'chartest', 'chartest')",
            [$kladdeId]
        );
        foreach ($lines as $line) {
            CharacterizationEnv::rows(
                self::$tenant,
                "INSERT INTO kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, amount, kladde_id)
                 VALUES ($1, CURRENT_DATE, $2, $3, $4, $5, $6, $7, $8)",
                [
                    $line['bilag'] ?? 1,
                    $line['tekst'] ?? 'chartest',
                    $line['d_type'] ?? 'F',
                    $line['debet'] ?? null,
                    $line['k_type'] ?? 'F',
                    $line['kredit'] ?? null,
                    $line['amount'],
                    $kladdeId,
                ]
            );
        }
        return $kladdeId;
    }

    private function runPage(string $mode, int $kladdeId): array
    {
        $result = CharacterizationEnv::runChild(
            __DIR__ . '/../support/run_bogfor_page.php',
            [$mode, $kladdeId, CharacterizationEnv::sessionId()]
        );
        $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
        return $result;
    }

    private function ledgerRows(string $table, int $kladdeId): array
    {
        return CharacterizationEnv::rows(
            self::$tenant,
            "SELECT kontonr, debet, kredit, moms, beskrivelse FROM $table WHERE kladde_id = $1 ORDER BY id",
            [$kladdeId]
        );
    }

    public function test_simulated_posting_of_balanced_entry_is_balanced_and_marks_kladde_simulated(): void
    {
        [$acctA, $acctB] = $this->plainAccounts();
        $kladdeId = $this->createKladde([
            ['debet' => $acctA, 'kredit' => $acctB, 'amount' => 800.00, 'tekst' => 'chartest plain'],
        ]);

        $this->runPage('simuler', $kladdeId);

        $rows = $this->ledgerRows('simulering', $kladdeId);
        $this->assertNotEmpty($rows, 'simulated posting must write simulering rows');

        $debet = array_sum(array_map(fn($r) => (float)$r['debet'], $rows));
        $kredit = array_sum(array_map(fn($r) => (float)$r['kredit'], $rows));
        $this->assertEqualsWithDelta(800.00, $debet, 0.001, 'debit total');
        $this->assertEqualsWithDelta($debet, $kredit, 0.001, 'simulated entry must balance');

        $byKonto = [];
        foreach ($rows as $r) {
            $byKonto[(int)$r['kontonr']][] = $r;
        }
        $this->assertArrayHasKey($acctA, $byKonto, 'debit account posted');
        $this->assertArrayHasKey($acctB, $byKonto, 'credit account posted');

        $kladde = CharacterizationEnv::one(self::$tenant, 'SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]);
        $this->assertSame('S', trim((string)$kladde['bogfort']), 'kladde is marked simulated');

        $trans = CharacterizationEnv::rows(self::$tenant, 'SELECT id FROM transaktioner WHERE kladde_id = $1', [$kladdeId]);
        $this->assertSame([], $trans, 'simulation must not write to transaktioner');
    }

    public function test_simulated_posting_splits_vat_from_gross_amount(): void
    {
        [$plain] = $this->plainAccounts();
        [$salesAcct, $vatAcct, $vatPct] = $this->vatSalesAccount();
        $gross = 1250.00;
        $expectedNet = round($gross / (1 + $vatPct / 100), 2);   // 1000.00 at 25%
        $expectedVat = round($gross - $expectedNet, 2);          // 250.00 at 25%

        $kladdeId = $this->createKladde([
            ['debet' => $plain, 'kredit' => $salesAcct, 'amount' => $gross, 'tekst' => 'chartest vat'],
        ]);

        $this->runPage('simuler', $kladdeId);

        $rows = $this->ledgerRows('simulering', $kladdeId);
        $this->assertNotEmpty($rows, 'simulated VAT posting must write simulering rows');

        $debet = array_sum(array_map(fn($r) => (float)$r['debet'], $rows));
        $kredit = array_sum(array_map(fn($r) => (float)$r['kredit'], $rows));
        $this->assertEqualsWithDelta($debet, $kredit, 0.001, 'VAT entry must balance');

        $vatLeg = array_values(array_filter($rows, fn($r) => (int)$r['kontonr'] === $vatAcct));
        $this->assertNotEmpty($vatLeg, "a leg must be posted to the VAT account $vatAcct");
        $this->assertEqualsWithDelta($expectedVat, (float)$vatLeg[0]['kredit'], 0.005, 'VAT amount split from gross');

        $salesLeg = array_values(array_filter($rows, fn($r) => (int)$r['kontonr'] === $salesAcct));
        $this->assertNotEmpty($salesLeg, 'sales account leg exists');
        $this->assertEqualsWithDelta($expectedNet, (float)$salesLeg[0]['kredit'], 0.005, 'sales leg is net of VAT');
    }

    public function test_real_posting_writes_balanced_transaktioner_and_updates_account_saldo(): void
    {
        [$acctA, $acctB] = $this->plainAccounts();
        $before = CharacterizationEnv::rows(
            self::$tenant,
            'SELECT kontonr, saldo FROM kontoplan WHERE regnskabsaar = $1 AND kontonr IN ($2, $3)',
            [self::$regnaar, $acctA, $acctB]
        );
        $saldoBefore = [];
        foreach ($before as $r) {
            $saldoBefore[(int)$r['kontonr']] = (float)$r['saldo'];
        }

        $kladdeId = $this->createKladde([
            ['debet' => $acctA, 'kredit' => $acctB, 'amount' => 640.00, 'tekst' => 'chartest real'],
        ]);

        $this->runPage('bogfor', $kladdeId);

        $rows = $this->ledgerRows('transaktioner', $kladdeId);
        $this->assertNotEmpty($rows, 'real posting must write transaktioner rows');
        $debet = array_sum(array_map(fn($r) => (float)$r['debet'], $rows));
        $kredit = array_sum(array_map(fn($r) => (float)$r['kredit'], $rows));
        $this->assertEqualsWithDelta(640.00, $debet, 0.001);
        $this->assertEqualsWithDelta($debet, $kredit, 0.001, 'posted entry must balance');

        $kladde = CharacterizationEnv::one(self::$tenant, 'SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]);
        $this->assertSame('V', trim((string)$kladde['bogfort']), 'kladde is marked posted');

        $after = CharacterizationEnv::rows(
            self::$tenant,
            'SELECT kontonr, saldo FROM kontoplan WHERE regnskabsaar = $1 AND kontonr IN ($2, $3)',
            [self::$regnaar, $acctA, $acctB]
        );
        $saldoAfter = [];
        foreach ($after as $r) {
            $saldoAfter[(int)$r['kontonr']] = (float)$r['saldo'];
        }
        $this->assertEqualsWithDelta($saldoBefore[$acctA] + 640.00, $saldoAfter[$acctA], 0.005, 'debit account saldo increases');
        $this->assertEqualsWithDelta($saldoBefore[$acctB] - 640.00, $saldoAfter[$acctB], 0.005, 'credit account saldo decreases');
    }

    /**
     * Start the posting page as a background child, like runPage(), with its own session save path: PHP's
     * files handler locks a session file until the request ends, so two children sharing one would run one
     * after the other. SALDI_CHAR_DEBUG makes the runner repeat the page's HTML on stderr.
     *
     * @return array{proc: resource, out: resource, err: resource, sessionDir: string}
     */
    private function startPage(string $mode, int $kladdeId, string $login): array
    {
        $sessionDir = sys_get_temp_dir() . '/chartest-session-' . $login . '-' . bin2hex(random_bytes(6));
        mkdir($sessionDir, 0700);
        $out = tmpfile();
        $err = tmpfile();
        $env = getenv();
        $env['SALDI_CHAR_DEBUG'] = '1';
        $proc = proc_open(
            [PHP_BINARY, __DIR__ . '/../support/run_bogfor_page.php', $mode, (string)$kladdeId, CharacterizationEnv::sessionId(), $sessionDir],
            [0 => ['file', '/dev/null', 'r'], 1 => $out, 2 => $err],
            $pipes,
            null,
            $env
        );
        $this->assertIsResource($proc, 'could not start the posting page');
        return ['proc' => $proc, 'out' => $out, 'err' => $err, 'sessionDir' => $sessionDir];
    }

    /**
     * Wait for a child from startPage(); it is stopped after $timeout seconds.
     *
     * @param array{proc: resource, out: resource, err: resource, sessionDir: string} $child
     * @return array{exit: int, output: string}
     */
    private function finishPage(array $child, float $timeout): array
    {
        // Only the first status that reports the child as stopped carries its exit code.
        $deadline = microtime(true) + $timeout;
        $status = proc_get_status($child['proc']);
        while ($status['running'] && microtime(true) < $deadline) {
            usleep(50000);
            $status = proc_get_status($child['proc']);
        }
        if ($status['running']) {
            proc_terminate($child['proc'], 9);
        }
        proc_close($child['proc']);
        $exit = $status['running'] ? -1 : $status['exitcode'];
        $output = '';
        foreach (['out', 'err'] as $stream) {
            rewind($child[$stream]);
            $output .= stream_get_contents($child[$stream]);
            fclose($child[$stream]);
        }
        array_map('unlink', glob($child['sessionDir'] . '/*') ?: []);
        rmdir($child['sessionDir']);
        return ['exit' => $exit, 'output' => $output];
    }

    /**
     * Wait until $count other connections wait for a lock on kladdeliste, until a child has stopped, or until the
     * deadline. A child that stopped first can no longer be waiting, so there is nothing to wait for.
     *
     * @param array<int, array{proc: resource}> $children
     */
    private function awaitJournalLockWaiters(int $count, array $children, float $deadline): int
    {
        while (($waiting = $this->journalLockWaiters()) < $count && microtime(true) < $deadline) {
            foreach ($children as $child) {
                if (!proc_get_status($child['proc'])['running']) {
                    return $this->journalLockWaiters();
                }
            }
            usleep(100000);
        }
        return $waiting;
    }

    /** Other connections to the test database that wait for a lock in a statement on kladdeliste. */
    private function journalLockWaiters(): int
    {
        $r = CharacterizationEnv::one(
            self::$tenant,
            "SELECT count(*) AS n FROM pg_stat_activity
             WHERE datname = current_database() AND pid <> pg_backend_pid()
               AND wait_event_type = 'Lock' AND query LIKE '%kladdeliste%'"
        );
        return (int)$r['n'];
    }

    /** @return array<int, float> kontoplan.saldo of the current fiscal year by account number */
    private function saldi(int ...$accounts): array
    {
        $saldi = [];
        foreach ($accounts as $kontonr) {
            $r = CharacterizationEnv::one(
                self::$tenant,
                'SELECT saldo FROM kontoplan WHERE regnskabsaar = $1 AND kontonr = $2',
                [self::$regnaar, $kontonr]
            );
            $saldi[$kontonr] = (float)($r['saldo'] ?? 0);
        }
        return $saldi;
    }

    /**
     * Two logins press Bogfør on the same journal while the first posting is still running. Both requests
     * pass the page's check of kladdeliste.bogfort before either has committed. The journal must be posted
     * once: the second request waits for the first, finds bogfort 'V' and writes nothing (bookkeeping F1).
     */
    public function test_two_concurrent_postings_of_one_journal_post_it_once(): void
    {
        [$acctA, $acctB] = $this->plainAccounts();
        $kladdeId = $this->createKladde([
            ['debet' => $acctA, 'kredit' => $acctB, 'amount' => 375.00, 'tekst' => 'chartest concurrent'],
        ]);
        $saldoBefore = $this->saldi($acctA, $acctB);

        // A third connection holds the journal row, as a long first posting would. Both requests get past
        // the page's own check, open their posting transaction and wait for this lock inside it.
        $blocker = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        CharacterizationEnv::rows($blocker, 'BEGIN');
        CharacterizationEnv::rows($blocker, 'SELECT id FROM kladdeliste WHERE id = $1 FOR UPDATE', [$kladdeId]);
        // The second login presses Bogfør once the first waits for the journal row inside its posting transaction,
        // so both page loads are done before the two postings meet.
        $children = [];
        $waiting = 0;
        try {
            $children[] = $this->startPage('bogfor', $kladdeId, 'one');
            if ($this->awaitJournalLockWaiters(1, $children, microtime(true) + 60) === 1) {
                $children[] = $this->startPage('bogfor', $kladdeId, 'two');
                $waiting = $this->awaitJournalLockWaiters(2, $children, microtime(true) + 60);
            }
        } finally {
            pg_query($blocker, 'ROLLBACK');
            pg_close($blocker);
        }
        $results = array_map(fn($child) => $this->finishPage($child, 120), $children);
        $report = implode("\n", array_map(
            fn($i, $result) => 'request ' . ($i + 1) . ' exit ' . $result['exit'] . ":\n" . substr($result['output'], -4000),
            array_keys($results),
            $results
        ));
        $this->assertSame(2, $waiting, "both requests waited inside their posting transaction at the same time\n" . $report);
        foreach ($results as $result) {
            $this->assertSame(0, $result['exit'], $result['output']);
        }

        $rows = $this->ledgerRows('transaktioner', $kladdeId);
        $this->assertCount(2, $rows, 'one posting: one debit and one credit row');
        $byKonto = [];
        foreach ($rows as $r) {
            $byKonto[(int)$r['kontonr']] = [(float)$r['debet'], (float)$r['kredit']];
        }
        $this->assertEqualsWithDelta([375.00, 0.00], $byKonto[$acctA] ?? null, 0.001, 'debit account posted once');
        $this->assertEqualsWithDelta([0.00, 375.00], $byKonto[$acctB] ?? null, 0.001, 'credit account posted once');

        $saldoAfter = $this->saldi($acctA, $acctB);
        $this->assertEqualsWithDelta($saldoBefore[$acctA] + 375.00, $saldoAfter[$acctA], 0.005, 'debit account saldo moved once');
        $this->assertEqualsWithDelta($saldoBefore[$acctB] - 375.00, $saldoAfter[$acctB], 0.005, 'credit account saldo moved once');

        $kladde = CharacterizationEnv::one(self::$tenant, 'SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]);
        $this->assertSame('V', trim((string)$kladde['bogfort']), 'kladde is marked posted');

        $refused = array_filter($results, fn($result) => str_contains($result['output'], "Kladde $kladdeId er allerede bogført. Ingen posteringer er gemt."));
        $this->assertCount(1, $refused, 'exactly one request tells the user the journal is already posted');
    }

    public function test_unbalanced_kladde_does_not_reach_the_ledger(): void
    {
        [$acctA] = $this->plainAccounts();
        // One-sided line: debit with no credit account - the voucher cannot balance.
        $kladdeId = $this->createKladde([
            ['debet' => $acctA, 'kredit' => null, 'amount' => 500.00, 'tekst' => 'chartest unbalanced'],
        ]);

        $this->runPage('simuler', $kladdeId);

        $sim = $this->ledgerRows('simulering', $kladdeId);
        $trans = $this->ledgerRows('transaktioner', $kladdeId);
        $this->assertSame([], $sim, 'unbalanced kladde must not write simulering rows');
        $this->assertSame([], $trans, 'unbalanced kladde must not write transaktioner rows');

        $kladde = CharacterizationEnv::one(self::$tenant, 'SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]);
        $this->assertNotSame('V', trim((string)$kladde['bogfort']), 'unbalanced kladde is not marked posted');
    }
}
