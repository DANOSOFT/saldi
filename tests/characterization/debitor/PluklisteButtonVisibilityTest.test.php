<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261006 MJ SST-828 Pins which orders show the plukliste buttons on the order card.
//                  debitor/ordre.php decided this from $opValue, which is $_GET['valg'] -
//                  the list tab the user arrived from, not what the order is. With
//                  hurtigfakturering on, ordreliste.php turns the tilbud tab into 'ordrer'
//                  (ordreliste.php:369), so valg was never 'faktura' for an open order and
//                  Whesco never saw the button at all. It now reads the order's own status.
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
     * With hurtigfakturering on, an order that is not yet invoiced shows the buttons.
     * This is the reported bug: every one of these was hidden.
     */
    #[DataProvider('quickInvoicingShown')]
    public function testWithQuickInvoicingAnUninvoicedOrderShowsThem(int $status): void
    {
        self::assertTrue($this->visPlukliste(4711, 'on', $status),
            "status $status with hurtigfakturering should show the plukliste buttons");
    }

    /** @return array<string, array{int}> */
    public static function quickInvoicingShown(): array
    {
        return [
            // With hurtigfakturering there are no tilbud, so status 0 is an order here -
            // matching how ordreliste.php lists it (status < 3 for the ordrer tab).
            'status 0, listed as an order' => [0],
            'status 1' => [1],
            'status 2' => [2],
        ];
    }

    /** An invoiced order is finished; the plukliste belongs to the order stage. */
    #[DataProvider('invoicedStatuses')]
    public function testAnInvoicedOrderDoesNotShowThem(int $status): void
    {
        self::assertFalse($this->visPlukliste(4711, 'on', $status),
            "status $status is invoiced and should not show the plukliste buttons");
        self::assertFalse($this->visPlukliste(4711, '', $status),
            "status $status is invoiced and should not show them without hurtigfakturering either");
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

    /** Without hurtigfakturering: orders yes, tilbud no. */
    public function testWithoutQuickInvoicingOrdersShowThemAndTilbudDoesNot(): void
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
     * The thresholds have to agree with the categorisation three lines above them, which is
     * the same one ordreliste.php's queries use. If those drift apart, one screen will
     * disagree with the other about what an order is.
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
