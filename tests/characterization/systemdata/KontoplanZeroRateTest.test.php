<?php
// tests/characterization/systemdata/KontoplanZeroRateTest.test.php
//
// The chart of accounts (systemdata/kontoplan.php) lists an account whose
// exchange rate is stored as 0.
//
// The REST API's account create stores valutakurs 0 when no currency is
// given, where the pages store 100 for DKK. The grid's Saldo column divides
// the balance by that rate behind a truthy check, and Postgres hands the
// rate over as the string '0.0000', which is truthy: the page died with a
// DivisionByZeroError at the first such row and showed no accounts at all,
// also not through the grid's search (testlab round 1, api-masterdata-stock).
//
// Pins: a zero-rate account is listed with its balance unconverted, as for an
// account without a rate, and an account with a real rate still shows its
// balance converted, with the DKK amount in the title.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class KontoplanZeroRateTest extends CharacterizationTestCase
{
    /** The test accounts' numbers share this prefix, so the grid's Kontonr search lists exactly them. */
    private const PREFIX = '97000';

    private static int $seq = 0;

    /** @return array{saldo:array<int,array{text:string,title:string}>, warnings:list<string>} */
    private function page(): array
    {
        return $this->runChildJson('run_kontoplan_page.php', [
            'search[kontoplan][kontonr]=' . self::PREFIX,
            CharacterizationEnv::SESSION_ID,
        ]);
    }

    /** A finance account in the current fiscal year, written the way the REST create writes it; returns its id. */
    private function account(float $saldo, string $valuta, string $valutakurs): int
    {
        $row = self::one(
            "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, fra_kto, til_kto, lukket, primo, saldo,
                                    regnskabsaar, genvej, overfor_til, anvendelse, modkonto, valuta, valutakurs, map_to)
             VALUES ($1, $2, 'D', '', 0, 0, '', 0, $3, $4, '', 0, '', 0, $5, $6, 0)
             RETURNING id",
            [self::PREFIX . ++self::$seq, Fixtures::TAG . ' kontoplan rate', $saldo, self::$regnaar, $valuta, $valutakurs]
        );
        return (int)$row['id'];
    }

    /** A currency (grupper art VK) at $kurs DKK per 100; returns its kodenr. */
    private function currency(string $kurs): int
    {
        $row = self::one("SELECT coalesce(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'VK'");
        $kodenr = (int)$row['kodenr'];
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, fiscal_year) VALUES ($1, '', $2, 'VK', 'EUR', $3, $4)",
            [Fixtures::TAG . ' euro', $kodenr, $kurs, self::$regnaar]
        );
        return $kodenr;
    }

    public function test_the_grid_lists_accounts_whose_rate_is_zero(): void
    {
        $unused = $this->account(0, '0', '0');
        $withBalance = $this->account(1234.5, '0', '0');
        $euro = $this->account(7450, (string)$this->currency('745'), '745');

        $out = $this->page();

        $this->assertSame(
            ['text' => '0,00', 'title' => 'DKK 0,00'],
            $out['saldo'][$unused] ?? null,
            'the account the REST create made: listed, balance 0'
        );
        $this->assertSame(
            ['text' => '1.234,50', 'title' => 'DKK 1.234,50'],
            $out['saldo'][$withBalance] ?? null,
            'no rate to convert by: the balance as booked'
        );
        $this->assertSame(
            ['text' => '1.000,00', 'title' => 'DKK 7.450,00'],
            $out['saldo'][$euro] ?? null,
            'a real rate still converts: 7450 DKK at 745 is 1000 EUR'
        );
        $this->assertCount(3, $out['saldo'], 'the search lists exactly the three test accounts');
    }
}
