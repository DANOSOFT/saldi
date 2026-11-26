<?php
// tests/characterization/includes/settings/SettingsFiscalYearSaveTest.test.php
//
// SD-877: a POS till setting (kept per fiscal year, grupper art POS) saved in a year that has no POS row
// of its own. SettingsService used to read another year's row and save into it, so the settings page said
// "Gemt" while the till, which reads only the current year, saw nothing, and the other year changed.
// The till's brand (pos.tills.brand, POS kodenr 4) is the case that matters: that row exists only in a
// year where someone has saved a brand.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261125 CL/MJ SD-877 Created.
// 20261126 CL/MJ SD-877 The first save copies the shown row into this year: a joined value, a second save, no fiscal year, a row without a year.

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class SettingsFiscalYearSaveTest extends CharacterizationTestCase
{
    private const OLD_YEAR = 1;
    private const NEW_YEAR = 2;

    protected function setUp(): void
    {
        parent::setUp();
        self::rows("DELETE FROM grupper WHERE art = 'POS' AND kodenr = '4'");
        // The tills come from the current year's POS kodenr 1 row (settings_till_count()).
        foreach ([self::OLD_YEAR, self::NEW_YEAR] as $year) {
            if (!self::one("SELECT id FROM grupper WHERE art = 'POS' AND kodenr = '1' AND fiscal_year = $1", [$year])) {
                self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1) VALUES ('POS_valg', '1', 'POS', '', $1, '2')", [$year]);
            }
        }
    }

    /** @return array{saved: bool, rows: list<array<string, int|string>>} */
    private function save(int $year, string $key, string $value, string $kodenr = '4', string $boxes = 'box1'): array
    {
        return $this->runChildJson('run_settings_fiscal_save.php', [CharacterizationEnv::sessionId(), $year, $key, $value, 'POS', $kodenr, $boxes]);
    }

    public function testASaveInAYearWithoutItsOwnRowCreatesThatYearsRow(): void
    {
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1) VALUES ('POS salgsopsaetning', '4', 'POS', '', $1, 'Dansk')", [self::OLD_YEAR]);

        $result = $this->save(self::NEW_YEAR, 'pos.tills.brand.1', 'Havemøbelland');

        self::assertTrue($result['saved']);
        self::assertSame([
            ['fiscal_year' => self::OLD_YEAR, 'box1' => 'Dansk'],
            ['fiscal_year' => self::NEW_YEAR, 'box1' => 'Havemøbelland'],
        ], $result['rows'], 'the older year is untouched and the current year gets its own row');
    }

    public function testAJoinedValueInAYearWithoutItsOwnRowStartsFromTheRowThePageShowed(): void
    {
        // Till 2's printer in a year without its own POS/2 row: the new row is the shown row with that
        // one entry changed, so till 1's printer and the terminals (box5) come along.
        self::rows("DELETE FROM grupper WHERE art = 'POS' AND kodenr = '2'");
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box3, box5) VALUES ('Pos valg', '2', 'POS', '', $1, $2, 'T1')", [self::OLD_YEAR, "192.168.0.1\t192.168.0.2"]);

        $result = $this->save(self::NEW_YEAR, 'pos.tills.printer_ip.2', '10.0.0.9', '2', 'box3,box5');

        self::assertTrue($result['saved']);
        self::assertSame([
            ['fiscal_year' => self::OLD_YEAR, 'box3' => "192.168.0.1\t192.168.0.2", 'box5' => 'T1'],
            ['fiscal_year' => self::NEW_YEAR, 'box3' => "192.168.0.1\t10.0.0.9", 'box5' => 'T1'],
        ], $result['rows']);
    }

    public function testASecondSaveInTheNewYearMakesNoSecondRow(): void
    {
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1) VALUES ('POS salgsopsaetning', '4', 'POS', '', $1, 'Dansk')", [self::OLD_YEAR]);

        $this->save(self::NEW_YEAR, 'pos.tills.brand.1', 'Havemøbelland');
        $result = $this->save(self::NEW_YEAR, 'pos.tills.brand.2', 'Dansk');

        self::assertSame([
            ['fiscal_year' => self::OLD_YEAR, 'box1' => 'Dansk'],
            ['fiscal_year' => self::NEW_YEAR, 'box1' => "Havemøbelland\tDansk"],
        ], $result['rows']);
    }

    public function testWithoutAFiscalYearTheShownRowIsUpdatedAsBefore(): void
    {
        // No year to copy into (a caller without online.php's globals): nothing is inserted for year 0.
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1) VALUES ('POS salgsopsaetning', '4', 'POS', '', $1, 'Dansk')", [self::OLD_YEAR]);

        $result = $this->save(0, 'pos.tills.brand.1', 'Havemøbelland');

        self::assertSame([
            ['fiscal_year' => self::OLD_YEAR, 'box1' => 'Havemøbelland'],
        ], $result['rows']);
    }

    public function testALegacyRowWithoutAYearIsCopiedIntoThisYear(): void
    {
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1) VALUES ('POS salgsopsaetning', '4', 'POS', '', NULL, $1)", ["Dansk\tDansk"]);

        $result = $this->save(self::NEW_YEAR, 'pos.tills.brand.2', 'Havemøbelland');

        self::assertSame([
            ['fiscal_year' => self::NEW_YEAR, 'box1' => "Dansk\tHavemøbelland"],
            ['fiscal_year' => 0, 'box1' => "Dansk\tDansk"],
        ], $result['rows'], 'the row without a year is left as it was; NULL sorts last');
    }

    public function testASaveInAYearWithItsOwnRowUpdatesThatRowOnly(): void
    {
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1) VALUES ('POS salgsopsaetning', '4', 'POS', '', $1, 'Dansk')", [self::OLD_YEAR]);
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1) VALUES ('POS salgsopsaetning', '4', 'POS', '', $1, $2)", [self::NEW_YEAR, "Dansk\tDansk"]);

        $result = $this->save(self::NEW_YEAR, 'pos.tills.brand.2', 'Havemøbelland');

        self::assertTrue($result['saved']);
        self::assertSame([
            ['fiscal_year' => self::OLD_YEAR, 'box1' => 'Dansk'],
            ['fiscal_year' => self::NEW_YEAR, 'box1' => "Dansk\tHavemøbelland"],
        ], $result['rows'], 'till 2 changes in the current year, till 1 and the older year stay');
    }
}
