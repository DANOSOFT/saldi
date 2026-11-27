<?php
// tests/characterization/finans/SaftOpeningBalanceTest.test.php
//
// Regression test for audit finding reports R-1 (integration 2026-11-14): SAF-T
// Financial left the fiscal year's opening balance out of the accounts'
// opening and closing balances.
//
// The opening balance of a fiscal year is not posted. It is kept in
// kontoplan.primo of the status accounts (typed in on the fiscal year card for
// the first year, carried over from the previous year's saldo after that), and
// genberegn() and the balance report add it to the year's postings.
// finans/saft.php summed only the postings since the start of the fiscal year,
// so every balance-sheet account with an opening balance was exported wrong.
// The balances still summed to 0, so a balance check of the file did not show it.
//
// The test gives the fiscal year an opening balance (bank 10.000 against
// retained earnings), posts a sale in the first month and a VAT payment in the
// third, maps the accounts to the standard chart (as Indstillinger > Diverse >
// Import/eksport does) and reads the balances the page hands to
// finans/saftCreator.php for the whole year and for months 2..12.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261117 CL/ASR Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class SaftOpeningBalanceTest extends CharacterizationTestCase
{
    private const BANK = 6480;
    private const RETAINED = 6940;
    private const SALES = 1010;
    private const OUTPUT_VAT = 7680;

    /** @var array{box1:string, box2:string, box3:string, box4:string} the fiscal year (grupper art='RA') */
    private static array $year = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        CharacterizationEnv::resyncSequences(self::$tenant);
        self::$year = self::one("SELECT box1, box2, box3, box4 FROM grupper WHERE art = 'RA' AND kodenr = $1", [self::$regnaar]);

        // SAF-T exports only accounts mapped to the standard chart; the installed chart is the standard chart.
        self::rows("UPDATE kontoplan SET map_to = kontonr WHERE regnskabsaar = $1 AND kontotype IN ('D', 'S')", [self::$regnaar]);
        // The opening balance as the fiscal year card stores it: kontoplan.primo, no postings.
        self::rows('UPDATE kontoplan SET primo = $3 WHERE regnskabsaar = $1 AND kontonr = $2', [self::$regnaar, self::BANK, 10000]);
        self::rows('UPDATE kontoplan SET primo = $3 WHERE regnskabsaar = $1 AND kontonr = $2', [self::$regnaar, self::RETAINED, -10000]);

        // Month 1: a cash sale of 1.000 + 25 % VAT. Month 3: the VAT is paid.
        $kladdeId = CharacterizationEnv::nextId(self::$tenant, 'kladdeliste');
        self::post(1, self::date(1, 15), [[self::BANK, 1250, 0], [self::SALES, 0, 1000], [self::OUTPUT_VAT, 0, 250]], $kladdeId);
        self::post(2, self::date(3, 10), [[self::OUTPUT_VAT, 250, 0], [self::BANK, 0, 250]], $kladdeId);
    }

    /** A date in month $n of the fiscal year (1 = its first month). */
    private static function date(int $n, int $day): string
    {
        $month = (int)self::$year['box1'] + $n - 1;
        $year = (int)self::$year['box2'] + intdiv($month - 1, 12);
        return sprintf('%04d-%02d-%02d', $year, ($month - 1) % 12 + 1, $day);
    }

    /** @param list<array{int, float|int, float|int}> $lines account, debit, credit */
    private static function post(int $bilag, string $date, array $lines, int $kladdeId): void
    {
        foreach ($lines as [$konto, $debet, $kredit]) {
            self::rows(
                "INSERT INTO transaktioner (kontonr, bilag, transdate, beskrivelse, debet, kredit, kladde_id, afd, ordre_id, logdate, logtime)
                 VALUES ($1, $2, $3, $4, $5, $6, $7, 0, 0, CURRENT_DATE, '12:00')",
                [$konto, $bilag, $date, Fixtures::TAG . ' saft', $debet, $kredit, $kladdeId]
            );
        }
    }

    /**
     * The balances saft.php hands to saftCreator.php for months $from..$to of the fiscal year,
     * per standard account: [opening, closing] as the debit-positive strings the XML is written from.
     *
     * @return array<string, array{string, string}>
     */
    private function balances(int $from, int $to): array
    {
        [$aarFra, $maanedFra] = array_map('intval', explode('-', self::date($from, 1)));
        [$aarTil, $maanedTil] = array_map('intval', explode('-', self::date($to, 1)));
        $query = http_build_query([
            'regnaar' => self::$regnaar,
            'maaned_fra' => $maanedFra,
            'maaned_til' => $maanedTil,
            'aar_fra' => $aarFra,
            'aar_til' => $aarTil,
            'dato_fra' => 1,
            'dato_til' => 31,
            'konto_fra' => '',
            'konto_til' => '',
            'rapportart' => 'saft',
        ]);
        $html = self::runChild('run_saft_page.php', [CharacterizationEnv::sessionId(), $query])['stdout'];
        $fields = [];
        foreach (['openingDbCrString', 'closingDbCrString', 'standardKontonrString'] as $name) {
            $this->assertMatchesRegularExpression("/name='$name' value='[^']*'/", $html, "the page offers the SAF-T file with $name:\n" . substr(strip_tags($html), 0, 2000));
            preg_match("/name='$name' value='([^']*)'/", $html, $m);
            $fields[$name] = unserialize($m[1]);
            $this->assertIsArray($fields[$name], "$name is a serialized array");
        }
        $balances = [];
        foreach ($fields['standardKontonrString'] as $x => $standard) {
            $balances[(string)$standard] = [$fields['openingDbCrString'][$x], $fields['closingDbCrString'][$x]];
        }
        return $balances;
    }

    public function test_the_whole_year_starts_from_the_opening_balance(): void
    {
        $balances = $this->balances(1, 12);
        $this->assertSame(['10000.00', '11000.00'], $balances[(string)self::BANK], 'bank: opening balance 10.000, + 1.250 sale - 250 VAT paid');
        $this->assertSame(['-10000.00', '-10000.00'], $balances[(string)self::RETAINED], 'retained earnings: the opening balance, credit');
        $this->assertSame(['0.00', '0.00'], $balances[(string)self::OUTPUT_VAT], 'output VAT: 250 posted and paid in the year');
        $this->assertSame(['0.00', '-1000.00'], $balances[(string)self::SALES], 'sales: no opening balance on an operating account');
        $this->assertEqualsWithDelta(0.0, array_sum(array_map(static fn($b) => (float)$b[0], $balances)), 0.001, 'the opening balances sum to 0');
        $this->assertEqualsWithDelta(0.0, array_sum(array_map(static fn($b) => (float)$b[1], $balances)), 0.001, 'the closing balances sum to 0');
    }

    public function test_a_later_period_starts_from_the_opening_balance_and_the_months_before_it(): void
    {
        $balances = $this->balances(2, 12);
        $this->assertSame(['11250.00', '11000.00'], $balances[(string)self::BANK], 'bank: 10.000 + the month-1 sale at the start, - the VAT paid in month 3');
        $this->assertSame(['-10000.00', '-10000.00'], $balances[(string)self::RETAINED], 'retained earnings: the opening balance');
        $this->assertSame(['-250.00', '0.00'], $balances[(string)self::OUTPUT_VAT], 'output VAT: owed at the start, paid in month 3');
        $this->assertSame(['-1000.00', '-1000.00'], $balances[(string)self::SALES], 'sales: the month-1 sale, from the start of the year');
    }
}
