<?php
// tests/characterization/finans/DoubleEntryIntegrityCharacterizationTest.test.php
//
// Characterization tests for the one invariant the general ledger cannot be
// allowed to break: within a voucher, total debit equals total credit.
//
// finans/bogfor.php checks that invariant on the amounts as entered
// (bogfor.php:751/757 accumulate $b_diff from the raw $dkkamount) but writes
// each leg to the ledger rounded to two decimals. kassekladde.amount is
// numeric(15,3), so the two can disagree: a voucher whose lines cancel out
// exactly at three decimals passes the check and then posts legs that do not
// cancel out at two.
//
// The tests below PIN that behavior rather than assert the invariant, because
// the invariant does not currently hold - see
// test_voucher_balanced_at_three_decimals_posts_an_unbalanced_ledger_entry.
// If someone fixes the rounding, that test fails and should be rewritten to
// assert balance; that is the point of pinning it.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class DoubleEntryIntegrityCharacterizationTest extends CharacterizationTestCase
{
    /** @return array{debet:float, kredit:float, rows:int} */
    private function simulate(array $lines, string $note = 'double entry'): array
    {
        $kladdeId = self::$fx->kladde($lines, $note);
        self::runChild('run_bogfor_page.php', ['simuler', $kladdeId, CharacterizationEnv::SESSION_ID]);
        $rows = self::rows('SELECT kontonr, debet, kredit FROM simulering WHERE kladde_id = $1', [$kladdeId]);

        return [
            'debet' => self::sumOf($rows, 'debet'),
            'kredit' => self::sumOf($rows, 'kredit'),
            'rows' => count($rows),
        ];
    }

    /** Multi-line vouchers that balance at two decimals stay balanced. */
    public function test_multi_line_voucher_balances_in_the_ledger(): void
    {
        [$a, $b, $c] = self::$fx->plainAccounts(3);

        $result = $this->simulate([
            ['bilag' => 1, 'debet' => $a, 'kredit' => null, 'amount' => 300.00],
            ['bilag' => 1, 'debet' => $b, 'kredit' => null, 'amount' => 200.50],
            ['bilag' => 1, 'debet' => null, 'kredit' => $c, 'amount' => 500.50],
        ]);

        $this->assertGreaterThan(0, $result['rows'], 'voucher reaches the ledger');
        $this->assertEqualsWithDelta($result['debet'], $result['kredit'], 0.005, 'debit equals credit');
        $this->assertEqualsWithDelta(500.50, $result['debet'], 0.005, 'voucher total');
    }

    /** Several independent vouchers in one kladde each balance on their own. */
    public function test_each_voucher_in_a_kladde_balances_independently(): void
    {
        [$a, $b] = self::$fx->plainAccounts(2);

        $kladdeId = self::$fx->kladde([
            ['bilag' => 1, 'debet' => $a, 'kredit' => $b, 'amount' => 100.00],
            ['bilag' => 2, 'debet' => $a, 'kredit' => $b, 'amount' => 250.25],
            ['bilag' => 3, 'debet' => $b, 'kredit' => $a, 'amount' => 33.33],
        ], 'per-voucher balance');

        self::runChild('run_bogfor_page.php', ['simuler', $kladdeId, CharacterizationEnv::SESSION_ID]);

        $rows = self::rows('SELECT bilag, debet, kredit FROM simulering WHERE kladde_id = $1', [$kladdeId]);
        $this->assertNotEmpty($rows);

        $byBilag = [];
        foreach ($rows as $r) {
            $byBilag[(int)$r['bilag']][] = $r;
        }
        $this->assertSame([1, 2, 3], array_keys($byBilag), 'all three vouchers posted');
        foreach ($byBilag as $bilag => $legs) {
            $this->assertEqualsWithDelta(
                self::sumOf($legs, 'debet'),
                self::sumOf($legs, 'kredit'),
                0.005,
                "voucher $bilag balances"
            );
        }
    }

    /**
     * DEFECT PINNED: a voucher whose lines cancel out exactly at three
     * decimals posts legs that do not cancel out at two.
     *
     * kassekladde.amount is numeric(15,3). The balance guard sums the raw
     * amounts (finans/bogfor.php:751/757), finds $b_diff == 0 and skips the
     * whole difference-handling block at bogfor.php:962. Each leg is then
     * rounded to two decimals on its way into transaktioner, and the halves
     * round up while the whole does not:
     *
     *     debit  0.005 -> 0.01
     *     debit  0.005 -> 0.01
     *     credit 0.010 -> 0.01
     *     ledger: debit 0.02, credit 0.01
     *
     * The ledger is left one ore out of balance with nothing reported to the
     * user. Reachable wherever sub-ore amounts enter a journal - currency
     * conversion (valutaopslag) and bank/CSV imports both produce them.
     *
     * When the rounding is fixed, this test will fail; rewrite it to assert
     * balance at that point.
     */
    public function test_voucher_balanced_at_three_decimals_posts_an_unbalanced_ledger_entry(): void
    {
        [$a, $b] = self::$fx->plainAccounts(2);

        $result = $this->simulate([
            ['bilag' => 1, 'debet' => $a, 'kredit' => null, 'amount' => 0.005],
            ['bilag' => 1, 'debet' => $a, 'kredit' => null, 'amount' => 0.005],
            ['bilag' => 1, 'debet' => null, 'kredit' => $b, 'amount' => 0.010],
        ], 'sub-ore split');

        $this->assertGreaterThan(0, $result['rows'], 'the voucher is accepted, not rejected');
        $this->assertEqualsWithDelta(0.02, $result['debet'], 0.0005, 'both half-ore debits round up');
        $this->assertEqualsWithDelta(0.01, $result['kredit'], 0.0005, 'the one-ore credit does not');
        $this->assertEqualsWithDelta(
            0.01,
            $result['debet'] - $result['kredit'],
            0.0005,
            'ledger is out of balance by one ore - see the docblock above'
        );
    }

    /**
     * DEFECT PINNED: the same rounding gap scales with the number of lines.
     *
     * Five half-ore debits against one matching credit leave the ledger two
     * ore out. A hundred would leave it fifty. The size of the discrepancy is
     * bounded only by the line count, not by half an ore.
     */
    public function test_rounding_imbalance_scales_with_the_number_of_lines(): void
    {
        [$a, $b] = self::$fx->plainAccounts(2);

        $lines = [];
        for ($i = 0; $i < 5; $i++) {
            $lines[] = ['bilag' => 1, 'debet' => $a, 'kredit' => null, 'amount' => 100.005];
        }
        $lines[] = ['bilag' => 1, 'debet' => null, 'kredit' => $b, 'amount' => 500.025];

        $result = $this->simulate($lines, 'sub-ore scaled');

        $this->assertGreaterThan(0, $result['rows'], 'the voucher is accepted');
        $this->assertEqualsWithDelta(500.05, $result['debet'], 0.0005, 'five legs each rounded up half an ore');
        $this->assertEqualsWithDelta(500.03, $result['kredit'], 0.0005, 'the single credit leg rounds up once');
        $this->assertEqualsWithDelta(
            0.02,
            $result['debet'] - $result['kredit'],
            0.0005,
            'the gap grows with the line count'
        );
    }

    /**
     * A truly unbalanced voucher - one where the raw amounts do not cancel -
     * is still caught and kept out of the ledger.
     */
    public function test_genuinely_unbalanced_voucher_is_kept_out_of_the_ledger(): void
    {
        [$a, $b] = self::$fx->plainAccounts(2);

        $result = $this->simulate([
            ['bilag' => 1, 'debet' => $a, 'kredit' => null, 'amount' => 300.00],
            ['bilag' => 1, 'debet' => null, 'kredit' => $b, 'amount' => 250.00],
        ], 'genuinely unbalanced');

        $this->assertSame(0, $result['rows'], 'an unbalanced voucher must not reach the ledger');
    }

    /**
     * Real posting keeps kontoplan.saldo consistent with the transaktioner
     * rows it wrote - the ledger and the account balances agree with each
     * other even though both are derived separately.
     */
    public function test_account_saldo_matches_the_posted_transactions(): void
    {
        [$a, $b] = self::$fx->plainAccounts(2);
        $before = $this->saldiFor([$a, $b]);

        $kladdeId = self::$fx->kladde([
            ['bilag' => 1, 'debet' => $a, 'kredit' => $b, 'amount' => 1234.56],
            ['bilag' => 2, 'debet' => $a, 'kredit' => $b, 'amount' => 65.44],
        ], 'saldo consistency');

        self::runChild('run_bogfor_page.php', ['bogfor', $kladdeId, CharacterizationEnv::SESSION_ID]);

        $posted = self::rows(
            'SELECT kontonr, debet, kredit FROM transaktioner WHERE kladde_id = $1',
            [$kladdeId]
        );
        $this->assertNotEmpty($posted, 'kladde posted for real');

        $movement = [];
        foreach ($posted as $r) {
            $konto = (int)$r['kontonr'];
            $movement[$konto] = ($movement[$konto] ?? 0.0) + (float)$r['debet'] - (float)$r['kredit'];
        }

        $after = $this->saldiFor([$a, $b]);
        foreach ([$a, $b] as $konto) {
            $this->assertEqualsWithDelta(
                $before[$konto] + ($movement[$konto] ?? 0.0),
                $after[$konto],
                0.005,
                "saldo for account $konto matches its posted movement"
            );
        }
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
