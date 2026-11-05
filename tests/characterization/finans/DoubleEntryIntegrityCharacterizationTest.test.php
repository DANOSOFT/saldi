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
// exactly at three decimals can post legs that do not cancel out at two.
// Until #674 that posted silently out of balance; now the posting is rolled
// back, and the sub-ore tests below assert that nothing reaches the ledger.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.
// 20261028 CL/LH The two sub-ore rounding tests pinned a defect that #674 fixed; they now assert
//                that such a voucher is kept out of the ledger.

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
     * A voucher whose lines cancel out at three decimals but not after each
     * leg is rounded to two is kept out of the ledger.
     *
     * kassekladde.amount is numeric(15,3). Rounding each leg on its way into
     * transaktioner turns 0.005 + 0.005 against 0.010 into 0.01 + 0.01 against
     * 0.01. This used to post that one-ore imbalance silently; since #674
     * (roll back unbalanced journal postings) the posting is rolled back
     * instead, so nothing reaches the ledger.
     */
    public function test_voucher_balanced_only_at_three_decimals_is_kept_out_of_the_ledger(): void
    {
        [$a, $b] = self::$fx->plainAccounts(2);

        $result = $this->simulate([
            ['bilag' => 1, 'debet' => $a, 'kredit' => null, 'amount' => 0.005],
            ['bilag' => 1, 'debet' => $a, 'kredit' => null, 'amount' => 0.005],
            ['bilag' => 1, 'debet' => null, 'kredit' => $b, 'amount' => 0.010],
        ], 'sub-ore split');

        $this->assertSame(0, $result['rows'], 'a voucher that would post one ore out of balance must not reach the ledger');
    }

    /**
     * The same holds when the rounding gap would grow with the line count:
     * five half-ore debits against one matching credit used to post two ore
     * out of balance; now nothing is posted.
     */
    public function test_rounding_gap_over_many_lines_is_kept_out_of_the_ledger(): void
    {
        [$a, $b] = self::$fx->plainAccounts(2);

        $lines = [];
        for ($i = 0; $i < 5; $i++) {
            $lines[] = ['bilag' => 1, 'debet' => $a, 'kredit' => null, 'amount' => 100.005];
        }
        $lines[] = ['bilag' => 1, 'debet' => null, 'kredit' => $b, 'amount' => 500.025];

        $result = $this->simulate($lines, 'sub-ore scaled');

        $this->assertSame(0, $result['rows'], 'a voucher that would post two ore out of balance must not reach the ledger');
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
