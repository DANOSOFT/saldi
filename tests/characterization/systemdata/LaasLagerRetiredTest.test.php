<?php
// tests/characterization/systemdata/LaasLagerRetiredTest.test.php
//
// Lås lagerværdi (systemdata/laas_lager.php) is retired for every tenant.
//
// On every GET with print=0 the page inserted the fiscal year's computed stock
// movements straight into transaktioner, with no guard against a second run,
// and set box9 on the fiscal year (grupper art RA). Its button in
// systemdata/fiscalYearInc/yearX.php is commented out, but the page could
// still be opened by URL. Now it only shows that it is retired, for the lock
// run (print=0) and for the preview (print=1) alike.
//
// The fixture is the setup the old page posted from: a stock-tracked item
// group with Lagertilgang and Lagertræk (box1/box2) on stock account 6140, an
// item in that group, and a purchase batch inside the fiscal year. With it,
// print=0 used to insert a debit on 6140 and a credit on the group's purchase
// account (box3) and set box9.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261114 CL/ASR Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class LaasLagerRetiredTest extends CharacterizationTestCase
{
    private const RETIRED_TEXT = 'Lås lagerværdi er udgået. Brug Lagerværdi i regnskabet.';
    private const STOCK_ACCOUNT = '6140';

    /** grupper.id of the fiscal year, the page's regnaar_id parameter. */
    private static int $raId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $ra = self::one("SELECT id, box1, box2 FROM grupper WHERE art = 'RA' AND kodenr = $1", [self::$regnaar]);
        if ($ra === null) {
            throw new RuntimeException('the test tenant has no fiscal year ' . self::$regnaar);
        }
        self::$raId = (int)$ra['id'];
        $stock = self::one(
            "SELECT kontonr FROM kontoplan WHERE regnskabsaar = $1 AND kontonr = $2 AND kontotype = 'S'",
            [self::$regnaar, self::STOCK_ACCOUNT]
        );
        if ($stock === null) {
            throw new RuntimeException('the standard chart must have balance account ' . self::STOCK_ACCOUNT);
        }

        $item = self::$fx->item(['kostpris' => 50.00, 'salgspris' => 100.00]);
        self::rows(
            "UPDATE grupper SET box1 = $1, box2 = $1 WHERE art = 'VG' AND kodenr = $2 AND fiscal_year = $3",
            [self::STOCK_ACCOUNT, $item['gruppe'], self::$regnaar]
        );
        $purchased = sprintf('%04d-%02d-10', (int)$ra['box2'], (int)$ra['box1']);
        self::rows(
            'INSERT INTO batch_kob (kobsdate, fakturadate, vare_id, linje_id, ordre_id, pris, antal, rest, lager)
             VALUES ($1, $1, $2, 0, 0, 50, 10, 10, 0)',
            [$purchased, $item['id']]
        );
    }

    public function test_the_lock_run_shows_that_it_is_retired_and_posts_nothing(): void
    {
        $this->assertRetired('0');
    }

    public function test_the_preview_shows_that_it_is_retired(): void
    {
        $this->assertRetired('1');
    }

    /**
     * Open the page by URL, the way the old button in yearX.php linked to it, and check that it only shows the text.
     */
    private function assertRetired(string $print): void
    {
        $before = $this->ledgerState();

        $out = $this->runChildJson('run_page.php', [
            'systemdata/laas_lager.php',
            CharacterizationEnv::sessionId(),
            'regnaar=' . self::$regnaar . '&regnaar_id=' . self::$raId . '&print=' . $print,
        ]);

        $this->assertStringContainsString(self::RETIRED_TEXT, $out['output']);
        $this->assertFalse($out['returned'], 'the page stops after the text');
        $this->assertStringNotContainsString('Primosaldo', $out['output'], 'no computed stock movements are listed');
        $this->assertStringNotContainsString('URL=regnskabskort.php', $out['output'], 'no redirect back after a lock run');
        $this->assertSame([], $out['warnings'], 'no warnings from the page itself');
        $this->assertSame($before, $this->ledgerState(), 'transaktioner and the groups, among them box9 of the fiscal year, are untouched');
    }

    /**
     * What the old page wrote: rows in transaktioner, and box9 on the fiscal year in grupper.
     * USET rows are left out: includes/online.php keeps the user's settings there on every page load.
     *
     * @return array{transaktioner: array<string, string|null>|null, grupper: list<array<string, string|null>>}
     */
    private function ledgerState(): array
    {
        return [
            'transaktioner' => self::one('SELECT count(*) AS n, coalesce(max(id), 0) AS max_id FROM transaktioner'),
            'grupper' => self::rows("SELECT * FROM grupper WHERE art <> 'USET' ORDER BY id"),
        ];
    }
}
