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

    /** @return array{saved: bool, rows: list<array{fiscal_year: int, box1: string}>} */
    private function save(int $year, string $key, string $value): array
    {
        return $this->runChildJson('run_settings_fiscal_save.php', [CharacterizationEnv::sessionId(), $year, $key, $value, 'POS', '4']);
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
