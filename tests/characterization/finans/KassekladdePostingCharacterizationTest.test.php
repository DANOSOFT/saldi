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
// 20260725 CL/LH Moved onto CharacterizationTestCase + Fixtures; kladde ids now allocated the way the app does.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class KassekladdePostingCharacterizationTest extends CharacterizationTestCase
{
    private function runPage(string $mode, int $kladdeId): array
    {
        return self::runChild('run_bogfor_page.php', [$mode, $kladdeId, CharacterizationEnv::SESSION_ID]);
    }

    private function ledgerRows(string $table, int $kladdeId): array
    {
        return self::rows(
            "SELECT kontonr, debet, kredit, moms, beskrivelse FROM $table WHERE kladde_id = $1 ORDER BY id",
            [$kladdeId]
        );
    }

    public function test_simulated_posting_of_balanced_entry_is_balanced_and_marks_kladde_simulated(): void
    {
        [$acctA, $acctB] = self::$fx->plainAccounts();
        $kladdeId = self::$fx->kladde([
            ['debet' => $acctA, 'kredit' => $acctB, 'amount' => 800.00, 'tekst' => 'chartest plain'],
        ]);

        $this->runPage('simuler', $kladdeId);

        $rows = $this->ledgerRows('simulering', $kladdeId);
        $this->assertNotEmpty($rows, 'simulated posting must write simulering rows');

        $debet = self::sumOf($rows, 'debet');
        $kredit = self::sumOf($rows, 'kredit');
        $this->assertEqualsWithDelta(800.00, $debet, 0.001, 'debit total');
        $this->assertEqualsWithDelta($debet, $kredit, 0.001, 'simulated entry must balance');

        $konti = array_map(static fn($r) => (int)$r['kontonr'], $rows);
        $this->assertContains($acctA, $konti, 'debit account posted');
        $this->assertContains($acctB, $konti, 'credit account posted');

        $kladde = self::one('SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]);
        $this->assertSame('S', trim((string)$kladde['bogfort']), 'kladde is marked simulated');

        $trans = self::rows('SELECT id FROM transaktioner WHERE kladde_id = $1', [$kladdeId]);
        $this->assertSame([], $trans, 'simulation must not write to transaktioner');
    }

    public function test_simulated_posting_splits_vat_from_gross_amount(): void
    {
        [$plain] = self::$fx->plainAccounts(1);
        [$salesAcct, $vatAcct, $vatPct] = self::$fx->vatSalesAccount();
        $gross = 1250.00;
        $expectedNet = round($gross / (1 + $vatPct / 100), 2);   // 1000.00 at 25%
        $expectedVat = round($gross - $expectedNet, 2);          // 250.00 at 25%

        $kladdeId = self::$fx->kladde([
            ['debet' => $plain, 'kredit' => $salesAcct, 'amount' => $gross, 'tekst' => 'chartest vat'],
        ]);

        $this->runPage('simuler', $kladdeId);

        $rows = $this->ledgerRows('simulering', $kladdeId);
        $this->assertNotEmpty($rows, 'simulated VAT posting must write simulering rows');

        $this->assertEqualsWithDelta(
            self::sumOf($rows, 'debet'),
            self::sumOf($rows, 'kredit'),
            0.001,
            'VAT entry must balance'
        );

        $vatLeg = array_values(array_filter($rows, static fn($r) => (int)$r['kontonr'] === $vatAcct));
        $this->assertNotEmpty($vatLeg, "a leg must be posted to the VAT account $vatAcct");
        $this->assertEqualsWithDelta($expectedVat, (float)$vatLeg[0]['kredit'], 0.005, 'VAT amount split from gross');

        $salesLeg = array_values(array_filter($rows, static fn($r) => (int)$r['kontonr'] === $salesAcct));
        $this->assertNotEmpty($salesLeg, 'sales account leg exists');
        $this->assertEqualsWithDelta($expectedNet, (float)$salesLeg[0]['kredit'], 0.005, 'sales leg is net of VAT');
    }

    public function test_real_posting_writes_balanced_transaktioner_and_updates_account_saldo(): void
    {
        [$acctA, $acctB] = self::$fx->plainAccounts();
        $saldoBefore = $this->saldiFor([$acctA, $acctB]);

        $kladdeId = self::$fx->kladde([
            ['debet' => $acctA, 'kredit' => $acctB, 'amount' => 640.00, 'tekst' => 'chartest real'],
        ]);

        $this->runPage('bogfor', $kladdeId);

        $rows = $this->ledgerRows('transaktioner', $kladdeId);
        $this->assertNotEmpty($rows, 'real posting must write transaktioner rows');
        $debet = self::sumOf($rows, 'debet');
        $kredit = self::sumOf($rows, 'kredit');
        $this->assertEqualsWithDelta(640.00, $debet, 0.001);
        $this->assertEqualsWithDelta($debet, $kredit, 0.001, 'posted entry must balance');

        $kladde = self::one('SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]);
        $this->assertSame('V', trim((string)$kladde['bogfort']), 'kladde is marked posted');

        $saldoAfter = $this->saldiFor([$acctA, $acctB]);
        $this->assertEqualsWithDelta($saldoBefore[$acctA] + 640.00, $saldoAfter[$acctA], 0.005, 'debit account saldo increases');
        $this->assertEqualsWithDelta($saldoBefore[$acctB] - 640.00, $saldoAfter[$acctB], 0.005, 'credit account saldo decreases');
    }

    public function test_unbalanced_kladde_does_not_reach_the_ledger(): void
    {
        [$acctA] = self::$fx->plainAccounts(1);
        // One-sided line: debit with no credit account - the voucher cannot balance.
        $kladdeId = self::$fx->kladde([
            ['debet' => $acctA, 'kredit' => null, 'amount' => 500.00, 'tekst' => 'chartest unbalanced'],
        ]);

        $this->runPage('simuler', $kladdeId);

        $this->assertSame([], $this->ledgerRows('simulering', $kladdeId), 'unbalanced kladde must not write simulering rows');
        $this->assertSame([], $this->ledgerRows('transaktioner', $kladdeId), 'unbalanced kladde must not write transaktioner rows');

        $kladde = self::one('SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]);
        $this->assertNotSame('V', trim((string)$kladde['bogfort']), 'unbalanced kladde is not marked posted');
    }

    /** @param int[] $konti @return array<int,float> */
    private function saldiFor(array $konti): array
    {
        $rows = self::rows(
            'SELECT kontonr, saldo FROM kontoplan WHERE regnskabsaar = $1 AND kontonr = ANY($2)',
            [self::$regnaar, '{' . implode(',', $konti) . '}']
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['kontonr']] = (float)$r['saldo'];
        }
        return $out;
    }
}
