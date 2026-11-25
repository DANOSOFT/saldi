<?php
// tests/characterization/includes/settings/TillCountPerYearTest.test.php
//
// The settings page's till count, and its "Tilføj kasse" action, in a fiscal year with and without
// its own POS/1 row (grupper art POS, kodenr 1, box1 = number of tills). The till reads this year's
// row only. The page used to take another year's count, so in a year without a POS/1 row it offered
// tills whose saves made a POS/1 row with only that field in it.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261125 CL/MJ SD-880 Created.
// 20261125 CL/MJ SD-880 CodeRabbit: the new year's row starts from the other year's payment cards.

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class TillCountPerYearTest extends CharacterizationTestCase
{
    private const OLD_YEAR = 1;
    private const NEW_YEAR = 2;
    private const CARDS = "Dankort	MobilePay";

    protected function setUp(): void
    {
        parent::setUp();
        self::rows("DELETE FROM grupper WHERE art = 'POS' AND kodenr = '1'");
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1) VALUES ('POS_valg', '1', 'POS', '', $1, '2')", [self::OLD_YEAR]);
        self::rows("UPDATE grupper SET box5 = $1 WHERE art = 'POS' AND kodenr = '1'", [self::CARDS]);
    }

    /** @return array{count: int, rows: list<array{fiscal_year: int, box1: string}>} */
    private function tills(int $year, string $what): array
    {
        return $this->runChildJson('run_till_count_action.php', [CharacterizationEnv::sessionId(), $year, $what]);
    }

    public function testAYearWithoutItsOwnRowHasNoTills(): void
    {
        self::assertSame(0, $this->tills(self::NEW_YEAR, 'count')['count'], "not the other year's 2");
        self::assertSame(2, $this->tills(self::OLD_YEAR, 'count')['count']);
    }

    public function testAddingATillInAYearWithoutItsOwnRowCreatesThatYearsRowOnly(): void
    {
        $result = $this->tills(self::NEW_YEAR, 'add');

        self::assertSame(1, $result['count']);
        self::assertSame([
            ['fiscal_year' => self::OLD_YEAR, 'box1' => '2', 'box5' => self::CARDS],
            ['fiscal_year' => self::NEW_YEAR, 'box1' => '1', 'box5' => self::CARDS],
        ], $result['rows'], 'the other year keeps its 2 tills, and the new year starts from its payment cards');
    }

    public function testAddingATillInAYearWithItsOwnRowStillCountsForEveryYear(): void
    {
        // Unchanged (risk review R2): the till count is shared by the years that have a POS/1 row.
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1) VALUES ('POS_valg', '1', 'POS', '', $1, '2')", [self::NEW_YEAR]);
        self::rows("UPDATE grupper SET box5 = $1 WHERE art = 'POS' AND kodenr = '1'", [self::CARDS]);

        $result = $this->tills(self::NEW_YEAR, 'add');

        self::assertSame(3, $result['count']);
        self::assertSame([
            ['fiscal_year' => self::OLD_YEAR, 'box1' => '3', 'box5' => self::CARDS],
            ['fiscal_year' => self::NEW_YEAR, 'box1' => '3', 'box5' => self::CARDS],
        ], $result['rows']);
    }

    public function testRemovingTheLastTillInAYearWithItsOwnRowStillCountsForEveryYear(): void
    {
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1) VALUES ('POS_valg', '1', 'POS', '', $1, '2')", [self::NEW_YEAR]);
        self::rows("UPDATE grupper SET box5 = $1 WHERE art = 'POS' AND kodenr = '1'", [self::CARDS]);

        $result = $this->tills(self::NEW_YEAR, 'remove');

        self::assertSame(1, $result['count']);
        self::assertSame([
            ['fiscal_year' => self::OLD_YEAR, 'box1' => '1', 'box5' => self::CARDS],
            ['fiscal_year' => self::NEW_YEAR, 'box1' => '1', 'box5' => self::CARDS],
        ], $result['rows']);
    }
}
