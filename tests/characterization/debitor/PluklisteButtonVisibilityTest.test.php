<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261011 MJ SST-828 Pins which orders show the plukliste buttons on the order card.
//                  debitor/ordre.php decided this from $opValue, which is $_GET['valg'] -
//                  the list tab the user arrived from, not what the order is. With
//                  hurtigfakturering on, ordreliste.php turns the tilbud tab into 'ordrer'
//                  (ordreliste.php:369), so valg was never 'faktura' for an open order and
//                  Whesco never saw the button at all. It now reads the order's own status.
//
//                  The rule is "anything but a tilbud", so no order that shows the buttons
//                  today loses them. SST-579 (f5b603d2, 20260429) put them on invoices on
//                  purpose: the same commit added the leveret column to formular 9 and
//                  filled it from batch_salg "for invoice printing". testNothingThatShows
//                  TheButtonsTodayLosesThem below pins that against the old expression.
//
//                  ordreside() is a 3000-line function inside a page script that opens a
//                  session, so the decision is lifted out of the source and evaluated here
//                  rather than by loading the file. Restating the condition in the test
//                  would let it pass while the real one is wrong.
final class PluklisteButtonVisibilityTest extends TestCase
{
    private const PAGE = __DIR__ . '/../../../debitor/ordre.php';

    /** @var string|null Cached source of debitor/ordre.php. */
    private static ?string $src = null;

    private static function source(): string
    {
        if (self::$src === null) {
            $src = file_get_contents(self::PAGE);
            self::assertNotFalse($src, 'could not read debitor/ordre.php');
            self::$src = $src;
        }
        return self::$src;
    }

    /**
     * Evaluates the real $visPlukliste assignment, lifted from the source.
     *
     * @param int    $id         The order id; 0 for an unsaved order.
     * @param string $hurtigfakt 'on' when quick invoicing is enabled.
     * @param int    $status     0 tilbud, 1-2 order, 3+ invoiced.
     * @return bool Whether the plukliste controls are shown.
     */
    private function visPlukliste(int $id, string $hurtigfakt, int $status): bool
    {
        $src = self::source();

        $at = strpos($src, '$visPlukliste = ');
        self::assertNotFalse($at, 'the $visPlukliste assignment is gone from ordre.php');
        $end = strpos($src, ';', $at);
        self::assertNotFalse($end, 'the $visPlukliste assignment is unterminated');

        eval(substr($src, $at, $end - $at + 1));

        return (bool)$visPlukliste;
    }

    /**
     * The expression this branch replaces, kept verbatim for the superset check below.
     * It is history, not the thing under test - the assertions always compare it against
     * the lifted condition, never against a restatement of the new rule.
     */
    private function visPluklisteOnMaster(string $hurtigfakt, ?string $opValue): bool
    {
        return ($hurtigfakt == 'on' && $opValue == 'faktura')
            || ($hurtigfakt != 'on' && $opValue != 'tilbud');
    }

    /**
     * With hurtigfakturering on, an order that is not yet invoiced shows the buttons.
     * This is the reported bug: every one of these was hidden.
     */
    #[DataProvider('quickInvoicingUninvoiced')]
    public function testWithQuickInvoicingAnUninvoicedOrderShowsThem(int $status): void
    {
        self::assertTrue($this->visPlukliste(4711, 'on', $status),
            "status $status with hurtigfakturering should show the plukliste buttons");
    }

    /** @return array<string, array{int}> */
    public static function quickInvoicingUninvoiced(): array
    {
        return [
            // With hurtigfakturering there are no tilbud, so status 0 is an order here -
            // matching how ordreliste.php lists it (status < 3 for the ordrer tab), and it
            // is reachable: ordre.php:1689 raises a saved order to status 1, then the
            // delivery-date check at 1698-1701 puts it back to 0.
            'status 0, listed as an order' => [0],
            'status 1' => [1],
            'status 2' => [2],
        ];
    }

    /**
     * An invoice keeps them. SST-579 added them there deliberately for hurtigfakturering,
     * where the invoice exists before the goods are picked, and without hurtigfakturering
     * master shows them on an invoice too. Taking them away would be a regression.
     */
    #[DataProvider('invoicedStatuses')]
    public function testAnInvoicedOrderStillShowsThem(int $status): void
    {
        self::assertTrue($this->visPlukliste(4711, 'on', $status),
            "status $status with hurtigfakturering should keep the plukliste buttons");
        self::assertTrue($this->visPlukliste(4711, '', $status),
            "status $status without hurtigfakturering should keep the plukliste buttons");
    }

    /** @return array<string, array{int}> */
    public static function invoicedStatuses(): array
    {
        return [
            'status 3, invoiced' => [3],
            'status 4, booked' => [4],
            'status 5' => [5],
        ];
    }

    /** Without hurtigfakturering a tilbud is the one thing left out. */
    public function testWithoutQuickInvoicingATilbudIsTheOnlyThingExcluded(): void
    {
        self::assertFalse($this->visPlukliste(4711, '', 0),
            'a tilbud (status 0) must not show the plukliste buttons');
        self::assertTrue($this->visPlukliste(4711, '', 1), 'status 1 is an order');
        self::assertTrue($this->visPlukliste(4711, '', 2), 'status 2 is an order');
    }

    /** An unsaved order has nothing to pick, and udskriftsvalg.php needs a real id. */
    public function testAnUnsavedOrderShowsNothing(): void
    {
        foreach (['on', ''] as $hurtigfakt) {
            foreach ([0, 1, 2, 3] as $status) {
                self::assertFalse($this->visPlukliste(0, $hurtigfakt, $status),
                    "an unsaved order (id 0) should show nothing, status $status");
            }
        }
    }

    /**
     * The point of the change: it only ever adds. For every saved order the order list can
     * open, if the old expression showed the buttons the new one must show them too.
     *
     * @param string      $hurtigfakt 'on' when quick invoicing is enabled.
     * @param string|null $opValue    The tab the row was opened from ($_GET['valg']).
     * @param int         $status     The order's own status.
     */
    #[DataProvider('rowsMasterShowsThemOn')]
    public function testNothingThatShowsTheButtonsTodayLosesThem(
        string $hurtigfakt,
        ?string $opValue,
        int $status
    ): void {
        self::assertTrue($this->visPluklisteOnMaster($hurtigfakt, $opValue),
            'this row is in the wrong provider - master hid the buttons here');
        self::assertTrue($this->visPlukliste(4711, $hurtigfakt, $status),
            "master showed the plukliste buttons for hurtigfakt='$hurtigfakt', "
            . "valg='$opValue', status $status, and this branch hides them");
    }

    /**
     * The reachable rows the old expression showed the buttons on. Built by running that
     * expression, so a row cannot quietly fall out of the check.
     *
     * @return array<string, array{string, string|null, int}>
     */
    public static function rowsMasterShowsThemOn(): array
    {
        $rows = array();
        foreach (self::reachableFromTheOrderList() as $name => $row) {
            list($hurtigfakt, $opValue, $status) = $row;
            $onMaster = ($hurtigfakt == 'on' && $opValue == 'faktura')
                || ($hurtigfakt != 'on' && $opValue != 'tilbud');
            if ($onMaster) {
                $rows[$name] = $row;
            }
        }
        self::assertNotEmpty($rows, 'the old expression never showed the buttons anywhere');
        return $rows;
    }

    /**
     * And the other half: over every row the order list can open, a tilbud without
     * hurtigfakturering is the only one still hidden. This is the whole behaviour change in
     * one assertion - anything that widens or narrows the rule shows up here as a diff.
     */
    public function testATilbudIsTheOnlyReachableRowStillHidden(): void
    {
        $hidden = array();
        foreach (self::reachableFromTheOrderList() as $name => $row) {
            list($hurtigfakt, $opValue, $status) = $row;
            if (!$this->visPlukliste(4711, $hurtigfakt, $status)) {
                $hidden[] = $name;
            }
        }
        self::assertSame(array('off, tilbud tab, status 0'), $hidden,
            'the set of orders without plukliste buttons changed');
    }

    /**
     * Every (tab, status) pair ordreliste.php can hand to the order card, from its own
     * per-tab filters at ordreliste.php:526-533 plus the tilbud -> ordrer rewrite at 369.
     *
     * @return array<string, array{string, string|null, int}>
     */
    public static function reachableFromTheOrderList(): array
    {
        $rows = [];
        // Quick invoicing: no tilbud tab, and the ordrer tab lists status < 3.
        foreach ([0, 1, 2] as $status) {
            $rows["on, ordrer tab, status $status"] = ['on', 'ordrer', $status];
        }
        foreach ([3, 4] as $status) {
            $rows["on, faktura tab, status $status"] = ['on', 'faktura', $status];
            $rows["on, pbs tab, status $status"] = ['on', 'pbs', $status];
        }
        // Without it: tilbud is its own tab and the ordrer tab lists status 1-2 only.
        $rows['off, tilbud tab, status 0'] = ['', 'tilbud', 0];
        foreach ([1, 2] as $status) {
            $rows["off, ordrer tab, status $status"] = ['', 'ordrer', $status];
        }
        foreach ([3, 4] as $status) {
            $rows["off, faktura tab, status $status"] = ['', 'faktura', $status];
            $rows["off, pbs tab, status $status"] = ['', 'pbs', $status];
        }
        return $rows;
    }

    /**
     * The one narrowing, pinned on purpose. Opened without a valg parameter - a direct link
     * or a return path - master read $opValue as NULL, so without hurtigfakturering
     * NULL != "tilbud" showed the buttons on a tilbud as well. They no longer do.
     */
    public function testATilbudOpenedWithoutATabNoLongerShowsThem(): void
    {
        self::assertTrue($this->visPluklisteOnMaster('', null),
            'master showed them for a tilbud opened without a tab');
        self::assertFalse($this->visPlukliste(4711, '', 0),
            'a tilbud should stay excluded however the card was reached');
    }

    /**
     * The decision has to agree with the categorisation three lines above it and with
     * ordreliste.php's own per-tab filters, which are what make status 0 an order under
     * hurtigfakturering. If those drift apart, one screen disagrees with the other about
     * what an order is.
     */
    public function testTheThresholdsMatchTheCategorisationAboveThem(): void
    {
        $src = self::source();

        self::assertStringContainsString('if ($status == 0) $tmp = "tilbud";', $src,
            'the tilbud/faktura/ordrer categorisation is gone from ordre.php');
        self::assertStringContainsString('elseif ($status >= 3) $tmp = "faktura";', $src,
            'the faktura threshold changed in ordre.php');

        $list = file_get_contents(__DIR__ . '/../../../debitor/ordreliste.php');
        self::assertNotFalse($list, 'could not read debitor/ordreliste.php');
        self::assertStringContainsString('$status = "ordrer.status < 3"', $list,
            "ordreliste.php's own threshold for the ordrer tab with hurtigfakturering changed");
        self::assertStringContainsString('$status = "(ordrer.status = 1 or ordrer.status = 2)"', $list,
            "ordreliste.php's own threshold for the ordrer tab without hurtigfakturering changed");
        self::assertStringContainsString('if ($valg == "tilbud" && $hurtigfakt) $valg = "ordrer";', $list,
            'ordreliste.php no longer folds the tilbud tab into ordrer under hurtigfakturering');
    }

    /**
     * Both places on the card - the side panel and the button row - decide from the one
     * variable. Two copies of the expression is what drifted apart before (see the 20260429
     * and 20260701 history lines), so this pins that there is only one.
     */
    public function testBothPlacesOnTheCardUseTheOneDecision(): void
    {
        $src = self::source();

        self::assertSame(1, substr_count($src, '$visPlukliste = '),
            'the decision is assigned more than once, so the two places can disagree again');
        self::assertSame(3, substr_count($src, 'if ($visPlukliste'),
            'expected three guarded blocks: the side-panel pair and the button row');

        // The navigation value must not be back in the decision.
        self::assertStringNotContainsString("\$opValue == 'faktura'", $src,
            'the buttons are keyed off the navigation value again, which is the reported bug');
        self::assertStringNotContainsString('$opValue != "tilbud"', $src,
            'the buttons are keyed off the navigation value again, which is the reported bug');
        self::assertStringNotContainsString("\$opValue != 'tilbud'", $src,
            'the buttons are keyed off the navigation value again, which is the reported bug');
    }

    /**
     * $opValue itself stays: includes/udskriv.php reads the file it writes to pick the
     * post-print redirect, and #526 restored it after an earlier merge deleted it.
     */
    public function testTheNavigationValueIsStillRecordedForTheRedirect(): void
    {
        $src = self::source();

        self::assertStringContainsString("\$opValue = if_isset(\$_GET, NULL, 'valg');", $src,
            'the $opValue assignment was removed again - udskriv.php needs the file it writes');
        self::assertStringContainsString('file_put_contents("../temp/$db/area$bruger_id.txt", $opValue, LOCK_EX);', $src,
            'the area file write was removed, which breaks the post-print redirect');
    }

    /** The printed plukliste is the same form the order list prints (formular=9). */
    public function testItPrintsTheSameFormAsTheOrderList(): void
    {
        $src = self::source();
        self::assertSame(2, substr_count($src, 'formular=9'),
            'expected the two plukliste print buttons to use formular=9');

        $list = file_get_contents(__DIR__ . '/../../../debitor/ordreliste.php');
        self::assertStringContainsString('formular=9', $list,
            "the order list's plukliste icon no longer uses formular=9");
    }

    /** Inside a scaffolding case the card hides them; that guard is unchanged. */
    public function testTheCaseGuardIsStillInPlace(): void
    {
        $src = self::source();
        $guards = 0;
        $offset = 0;
        while (($at = strpos($src, 'if ($visPlukliste', $offset)) !== false) {
            // Walk back to the nearest enclosing sag_id guard.
            $before = substr($src, max(0, $at - 2000), min($at, 2000));
            if (strpos($before, 'if (!$sag_id) {') !== false) {
                $guards++;
            }
            $offset = $at + 1;
        }
        self::assertSame(3, $guards,
            'a plukliste block is no longer inside the !$sag_id guard, so it would show inside a case');
    }
}
