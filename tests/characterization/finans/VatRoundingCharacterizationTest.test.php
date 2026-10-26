<?php
// tests/characterization/finans/VatRoundingCharacterizationTest.test.php
//
// Characterization tests for VAT splitting and rounding in the posting engine.
//
// momsberegning() (finans/bogfor.php:1131) divides a gross amount into a net
// leg and a VAT leg, rounds both to 2 decimals, then nudges the VAT leg by a
// single ore when the two no longer add up to the gross:
//
//     $tmp = afrund($amount - ($nettoamount + $moms), 2);
//     if ($tmp > 0)      $moms = $moms + 0.01;
//     elseif ($tmp < 0)  $moms = $moms - 0.01;
//     $tmp = afrund($amount - ($nettoamount + $moms), 2);
//     if (abs($tmp) >= 0.01) { mail('fejl@saldi.dk', ...); print alert; exit; }
//
// The one-ore nudge can only repair a discrepancy of exactly one ore. Anything
// larger aborts the whole request - the voucher is left half-posted, the user
// gets a phone number, and Saldi gets an email. That failure mode is the
// reason this suite sweeps a wide amount matrix rather than checking a couple
// of round numbers: it is a search for a gross amount at which the engine
// refuses to post.
//
// The sweep runs through the REAL engine (via the page's simuler dispatch),
// not against an extracted copy of the arithmetic, so what it pins is what
// production does.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class VatRoundingCharacterizationTest extends CharacterizationTestCase
{
    /**
     * Amounts chosen to land on and around half-ore boundaries, plus the
     * smallest amounts where a percentage split cannot produce two non-zero
     * legs at all.
     *
     * @return list<float>
     */
    private static function amountMatrix(): array
    {
        $amounts = [0.01, 0.02, 0.03, 0.04, 0.05, 0.07, 0.09, 0.11, 0.13, 0.99, 1.00, 1.01];
        // A dense band: every ore from 9.90 to 10.10 exercises consecutive
        // rounding decisions with the same divisor.
        for ($cents = 990; $cents <= 1010; $cents++) {
            $amounts[] = $cents / 100;
        }
        foreach ([99.99, 100.00, 123.45, 333.33, 666.67, 999.99, 1000.01, 12345.67, 99999.99] as $a) {
            $amounts[] = $a;
        }
        return $amounts;
    }

    /**
     * Post the whole amount matrix as one kladde (one bilag per amount) and
     * return the simulated legs grouped by bilag.
     *
     * @param list<float> $amounts
     * @return array<int, list<array<string,string>>>
     */
    private function simulateMatrix(int $salesAcct, array $amounts): array
    {
        [$plain] = self::$fx->plainAccounts(1);

        $lines = [];
        foreach ($amounts as $i => $amount) {
            $lines[] = [
                'bilag' => $i + 1,
                'debet' => $plain,
                'kredit' => $salesAcct,
                'amount' => $amount,
                'tekst' => 'vatsweep ' . $amount,
            ];
        }
        $kladdeId = self::$fx->kladde($lines, 'vat rounding sweep');

        self::runChild('run_bogfor_page.php', ['simuler', $kladdeId, CharacterizationEnv::SESSION_ID]);

        $rows = self::rows(
            'SELECT bilag, kontonr, debet, kredit, moms FROM simulering WHERE kladde_id = $1 ORDER BY bilag, id',
            [$kladdeId]
        );
        $byBilag = [];
        foreach ($rows as $r) {
            $byBilag[(int)$r['bilag']][] = $r;
        }
        return $byBilag;
    }

    /**
     * Every gross amount in the matrix splits into legs that add back up to
     * the gross, at the stock 25% rate.
     */
    public function test_vat_split_reconstructs_the_gross_amount_at_25_percent(): void
    {
        [$salesAcct, $vatAcct, $pct] = self::$fx->vatSalesAccount();
        $this->assertEqualsWithDelta(25.0, $pct, 0.001, 'stock SM group is 25%');

        $amounts = self::amountMatrix();
        $byBilag = $this->simulateMatrix($salesAcct, $amounts);

        $this->assertPostedCleanly($amounts, $byBilag, $salesAcct, $vatAcct, $pct);
    }

    /**
     * The same sweep at 12.5%, where the net/VAT division does not terminate
     * in two decimals and the one-ore nudge is actually exercised.
     */
    public function test_vat_split_reconstructs_the_gross_amount_at_an_awkward_rate(): void
    {
        [$salesAcct, $vatAcct, $pct] = self::$fx->vatSalesAccountAt(12.5);

        $amounts = self::amountMatrix();
        $byBilag = $this->simulateMatrix($salesAcct, $amounts);

        $this->assertPostedCleanly($amounts, $byBilag, $salesAcct, $vatAcct, $pct);
    }

    /**
     * Assert that every bilag reached the ledger, balanced, with the VAT leg
     * matching the rate to within the one-ore the engine allows itself.
     *
     * @param list<float>                              $amounts
     * @param array<int, list<array<string,string>>>   $byBilag
     */
    private function assertPostedCleanly(array $amounts, array $byBilag, int $salesAcct, int $vatAcct, float $pct): void
    {
        foreach ($amounts as $i => $gross) {
            $bilag = $i + 1;
            $legs = $byBilag[$bilag] ?? [];

            // A missing bilag means the engine stopped before reaching it -
            // in this code path that is the momsberegning abort, which also
            // kills every later bilag in the same kladde.
            $this->assertNotEmpty(
                $legs,
                sprintf(
                    'gross %.2f at %.1f%% produced no ledger rows - the posting engine aborted here '
                    . '(momsberegning deviation guard, finans/bogfor.php:1206)',
                    $gross,
                    $pct
                )
            );

            $debet = self::sumOf($legs, 'debet');
            $kredit = self::sumOf($legs, 'kredit');
            $this->assertEqualsWithDelta($debet, $kredit, 0.005, sprintf('gross %.2f must balance', $gross));
            $this->assertEqualsWithDelta(
                $gross,
                $debet,
                0.005,
                sprintf('gross %.2f must reach the ledger at face value', $gross)
            );

            $net = self::sumOf(
                array_filter($legs, static fn($r) => (int)$r['kontonr'] === $salesAcct),
                'kredit'
            );
            $vat = self::sumOf(
                array_filter($legs, static fn($r) => (int)$r['kontonr'] === $vatAcct),
                'kredit'
            );

            $this->assertEqualsWithDelta(
                $gross,
                $net + $vat,
                0.005,
                sprintf('net + VAT must reconstruct gross %.2f', $gross)
            );

            // The engine's own tolerance: it repairs at most one ore, so the
            // VAT leg may sit one ore off the exact percentage.
            $exactVat = $gross - $gross / (1 + $pct / 100);
            $this->assertEqualsWithDelta(
                $exactVat,
                $vat,
                0.0101,
                sprintf('VAT on gross %.2f at %.1f%% is within one ore of exact', $gross, $pct)
            );
        }
    }

    /**
     * A VAT-coded account posted on both legs of the same voucher nets to
     * zero without leaving a VAT residue.
     */
    public function test_vat_account_on_both_legs_nets_to_zero(): void
    {
        [$salesAcct] = self::$fx->vatSalesAccount();
        $kladdeId = self::$fx->kladde([
            ['bilag' => 1, 'debet' => $salesAcct, 'kredit' => $salesAcct, 'amount' => 250.00, 'tekst' => 'vat both legs'],
        ]);

        self::runChild('run_bogfor_page.php', ['simuler', $kladdeId, CharacterizationEnv::SESSION_ID]);

        $rows = self::rows('SELECT kontonr, debet, kredit FROM simulering WHERE kladde_id = $1', [$kladdeId]);
        $this->assertNotEmpty($rows, 'same-account voucher still posts');
        $this->assertEqualsWithDelta(
            self::sumOf($rows, 'debet'),
            self::sumOf($rows, 'kredit'),
            0.005,
            'same-account voucher balances'
        );
    }

    /**
     * A line flagged momsfri skips the VAT split entirely: the gross amount
     * lands on the sales account untouched.
     */
    public function test_momsfri_line_posts_gross_without_a_vat_leg(): void
    {
        [$plain] = self::$fx->plainAccounts(1);
        [$salesAcct, $vatAcct] = self::$fx->vatSalesAccount();

        $kladdeId = self::$fx->kladde([
            ['bilag' => 1, 'debet' => $plain, 'kredit' => $salesAcct, 'amount' => 1250.00, 'momsfri' => 'on', 'tekst' => 'momsfri'],
        ]);

        self::runChild('run_bogfor_page.php', ['simuler', $kladdeId, CharacterizationEnv::SESSION_ID]);

        $rows = self::rows('SELECT kontonr, debet, kredit FROM simulering WHERE kladde_id = $1', [$kladdeId]);
        $this->assertNotEmpty($rows, 'momsfri line still posts');

        $vatLegs = array_filter($rows, static fn($r) => (int)$r['kontonr'] === $vatAcct);
        $this->assertSame([], array_values($vatLegs), 'momsfri line must not produce a VAT leg');

        $salesLeg = array_values(array_filter($rows, static fn($r) => (int)$r['kontonr'] === $salesAcct));
        $this->assertNotEmpty($salesLeg);
        $this->assertEqualsWithDelta(1250.00, (float)$salesLeg[0]['kredit'], 0.005, 'gross posted to sales account');
    }
}
