<?php
// tests/characterization/includes/settings/TillCountPerYearTest.test.php
//
// The settings page's "Tilføj/Fjern kasse" actions in a fiscal year with and without its own POS/1
// row (grupper art POS, kodenr 1, box1 = number of tills, box5 = payment cards). The till reads this
// year's row only, so a year without one first gets a copy of the row the page showed; the count is
// then written to every year's row (risk review R2), as many readers take any year's POS/1 row.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261125 CL/MJ SD-880 Created.
// 20261125 CL/MJ SD-880 CodeRabbit: the new year's row starts from the other year's payment cards.
// 20261125 CL/MJ SD-880 Hugin/CodeRabbit: Tilføj/Fjern kasse changes this year's row only; the new year's row is copied from the row the page showed.
// 20261126 CL/MJ SD-880 /code-review: the count is written to every year again (R2); a year without its own row gets a copy first.

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
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1, box5) VALUES ('POS_valg', '1', 'POS', '', $1, '2', $2)", [self::OLD_YEAR, self::CARDS]);
    }

    /** @return array{count: int, rows: list<array{fiscal_year: int, box1: string, box5: string}>} */
    private function tills(int $year, string $what): array
    {
        return $this->runChildJson('run_till_count_action.php', [CharacterizationEnv::sessionId(), $year, $what]);
    }

    public function testAddingATillInAYearWithoutItsOwnRowCopiesTheShownRowAndCountsForEveryYear(): void
    {
        $result = $this->tills(self::NEW_YEAR, 'add');

        self::assertSame(3, $result['count']);
        self::assertSame([
            ['fiscal_year' => self::OLD_YEAR, 'box1' => '3', 'box5' => self::CARDS],
            ['fiscal_year' => self::NEW_YEAR, 'box1' => '3', 'box5' => self::CARDS],
        ], $result['rows'], 'the new year gets the payment cards the page showed, and the till count stays the same in every year');
    }

    public function testTheNewYearsRowIsCopiedFromTheRowThePageShowed(): void
    {
        // settings_pos_row(1) shows the oldest row (lowest id) when this year has none.
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1, box5) VALUES ('POS_valg', '1', 'POS', '', 3, '2', 'Visa')");

        $result = $this->tills(self::NEW_YEAR, 'add');

        self::assertSame([
            ['fiscal_year' => self::OLD_YEAR, 'box1' => '3', 'box5' => self::CARDS],
            ['fiscal_year' => self::NEW_YEAR, 'box1' => '3', 'box5' => self::CARDS],
            ['fiscal_year' => 3, 'box1' => '3', 'box5' => 'Visa'],
        ], $result['rows']);
    }

    public function testASecondActionInTheNewYearMakesNoSecondRow(): void
    {
        $this->tills(self::NEW_YEAR, 'add');
        $result = $this->tills(self::NEW_YEAR, 'remove');

        self::assertSame(2, $result['count']);
        self::assertSame([
            ['fiscal_year' => self::OLD_YEAR, 'box1' => '2', 'box5' => self::CARDS],
            ['fiscal_year' => self::NEW_YEAR, 'box1' => '2', 'box5' => self::CARDS],
        ], $result['rows']);
    }

    public function testWithARowInBothYearsAddAndRemoveCountForEveryYear(): void
    {
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, kode, fiscal_year, box1, box5) VALUES ('POS_valg', '1', 'POS', '', $1, '2', $2)", [self::NEW_YEAR, self::CARDS]);

        self::assertSame(3, $this->tills(self::NEW_YEAR, 'add')['count']);
        $result = $this->tills(self::NEW_YEAR, 'remove');

        self::assertSame(2, $result['count']);
        self::assertSame([
            ['fiscal_year' => self::OLD_YEAR, 'box1' => '2', 'box5' => self::CARDS],
            ['fiscal_year' => self::NEW_YEAR, 'box1' => '2', 'box5' => self::CARDS],
        ], $result['rows']);
    }

    public function testWithNoPosRowAnywhereTheFirstTillMakesThisYearsRow(): void
    {
        self::rows("DELETE FROM grupper WHERE art = 'POS' AND kodenr = '1'");

        $result = $this->tills(self::NEW_YEAR, 'add');

        self::assertSame(1, $result['count']);
        self::assertSame([['fiscal_year' => self::NEW_YEAR, 'box1' => '1', 'box5' => '']], $result['rows']);
    }
}
