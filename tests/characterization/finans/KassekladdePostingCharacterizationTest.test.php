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
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $ra = CharacterizationEnv::one(self::$tenant, "SELECT max(kodenr) AS ra FROM grupper WHERE art = 'RA'");
        self::$regnaar = (int)$ra['ra'];
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            pg_close(self::$tenant);
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
        $r = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO kladdeliste (kladdedate, bogfort, kladdenote, oprettet_af)
             VALUES (CURRENT_DATE, '', 'chartest', 'chartest') RETURNING id"
        );
        $kladdeId = (int)$r['id'];
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
        return CharacterizationEnv::runChild(
            __DIR__ . '/../support/run_bogfor_page.php',
            [$mode, $kladdeId, CharacterizationEnv::SESSION_ID]
        );
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
