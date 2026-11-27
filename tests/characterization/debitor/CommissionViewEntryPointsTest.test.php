<?php
// tests/characterization/debitor/CommissionViewEntryPointsTest.test.php
//
// Regression test for the testlab finding "Debitor > Kommission dies with
// Cannot redeclare array_column_php5()" (campaign integration-20261120-B,
// books check on test_33).
//
// The Kommission view (debitor/debitor_kommission.php, where customers are
// activated for and invited to Mit salg, SST-919's commission settlement) is
// opened two ways: through debitor/debitor.php?valg=kommission (the menu
// route in includes/stdFunc/pageRoutes.php and the classic top line), which
// includes includes/grid.php and then the view, and directly from the list's
// top line (debitor/debLstIncludes/topLine.php). The view included
// includes/grid.php a second time, whose functions have no guard, so the first
// way died with a fatal error after the page had started: HTTP 200, a cut-off
// page and no message.
//
// The test opens the view both ways with one seeded customer searched in the
// grid, and checks that the page is whole: the customer's row with its Aktiv
// and Inviter boxes, and the OK form printed after the grid.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261121 CL/ASR Created.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class CommissionViewEntryPointsTest extends CharacterizationTestCase
{
    /** The grid's id in debitor/debitor_kommission.php. */
    private const GRID = 'debitor_kommission';

    /** @var array{id:int, kontonr:string} */
    private array $debtor = ['id' => 0, 'kontonr' => ''];

    protected function setUp(): void
    {
        $this->debtor = self::$fx->debtor();
    }

    protected function tearDown(): void
    {
        self::rows('DELETE FROM adresser WHERE id = $1', [$this->debtor['id']]);
    }

    /** @return array<string, array{string, string}> page => [page, query before the grid search] */
    public static function entryPoints(): array
    {
        return [
            'through debitor.php?valg=kommission' => ['debitor/debitor.php', 'valg=kommission'],
            'debitor_kommission.php opened directly' => ['debitor/debitor_kommission.php', 'returside=debitor.php'],
        ];
    }

    #[DataProvider('entryPoints')]
    public function test_the_commission_view_is_shown_whole(string $page, string $query): void
    {
        $query .= '&' . http_build_query(['search' => [self::GRID => ['kontonr' => $this->debtor['kontonr']]]]);
        // runChild() fails the test on a crash, with the child's stderr (the fatal error's message).
        $res = self::runChild('run_debitor_list_page.php', [CharacterizationEnv::sessionId(), $page, $query]);
        $html = $res['stdout'];

        $this->assertStringNotContainsString('Cannot redeclare', $res['stdout'] . $res['stderr']);
        $this->assertSame(0, $res['exit'], "$page ends normally.\nstderr: {$res['stderr']}");
        $this->assertStringContainsString('id="datatable-' . self::GRID . '"', $html, 'the Kommission grid is drawn');
        $this->assertStringContainsString(
            "<input type='hidden' name='debId[]' value='{$this->debtor['id']}' form='kommission-form'>",
            $html,
            "the seeded customer's row is in the grid"
        );
        $this->assertStringContainsString("name='mySale[]' value='{$this->debtor['id']}'", $html, 'the row has its Aktiv box');
        $this->assertStringContainsString("name='invite[]' value='{$this->debtor['id']}'", $html, 'the row has its Inviter box');
        $this->assertStringContainsString("<form id='kommission-form'", $html, 'the page goes on past the grid to its OK form');
    }
}
