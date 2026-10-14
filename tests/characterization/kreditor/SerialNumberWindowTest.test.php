<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261007 MJ SST-831 Pins the two usability defects in the serial-number window, and that
//                  the ids it puts into SQL are cast.
//
//                  MEDSHOP reported three things about this window: "Ingen varer er sat til
//                  levering" appeared even with 1 in Modtag, there was no Gem button so the
//                  entry looked unsaved, and the return list was wrong. Only the first two
//                  are addressed here - the return list depends on what 8 credited rows
//                  without a successor mean in the customer's data, which has to be
//                  established before the selection is changed, and returning a serial that
//                  has been sold and credited is a design gap in returnering(). Both are
//                  split out; this test covers what shipped.
//
//                  kreditor/serienummer.php is a page script that opens a session and
//                  prints on include, so the condition is lifted from the source and
//                  evaluated rather than reimplemented.
final class SerialNumberWindowTest extends TestCase
{
    private const PAGE = __DIR__ . '/../../../kreditor/serienummer.php';

    private static ?string $src = null;

    private static function source(): string
    {
        if (self::$src === null) {
            $src = file_get_contents(self::PAGE);
            self::assertNotFalse($src, 'could not read kreditor/serienummer.php');
            self::$src = $src;
        }
        return self::$src;
    }

    /** Evaluates the real guard around the "Ingen varer er sat til levering" message. */
    private function warnsNothingIsBeingReceived(float $leveres, float $leveret): bool
    {
        $src = self::source();

        $at = strpos($src, 'if ($leveres == 0 && $leveret == 0) {');
        self::assertNotFalse($at, 'the message is unconditional again, which is the reported bug');
        $end = strpos($src, '{', $at);

        $condition = substr($src, $at + 3, $end - $at - 4);
        return (bool)eval('return ' . $condition . ';');
    }

    /** With something to receive, the window must stay quiet. */
    #[DataProvider('receivingSomething')]
    public function testNoWarningWhenSomethingIsBeingReceived(float $leveres, float $leveret): void
    {
        self::assertFalse($this->warnsNothingIsBeingReceived($leveres, $leveret),
            "leveres=$leveres leveret=$leveret is a delivery, so the message must not appear");
    }

    /** @return array<string, array{float, float}> */
    public static function receivingSomething(): array
    {
        return [
            // The customer's case: 1 in Modtag and the message appeared anyway.
            '1 to receive' => [1.0, 0.0],
            'already received 1' => [0.0, 1.0],
            'both' => [1.0, 1.0],
            // Fractional quantities: the 20260123 history line records that !$leveres was
            // wrong precisely because 0.000 is falsy, so the comparison must stay numeric.
            'a fraction to receive' => [0.5, 0.0],
            'a fraction already received' => [0.0, 0.5],
        ];
    }

    /** With nothing to receive it is still shown - the message itself is not removed. */
    public function testTheWarningStillAppearsWhenThereIsNothingToReceive(): void
    {
        self::assertTrue($this->warnsNothingIsBeingReceived(0.0, 0.0),
            'the message no longer appears at all, which loses the warning it exists for');
    }

    /**
     * The Gem button was guarded on $gem, which is never assigned anywhere in this file -
     * so it never rendered and Luk was the only way out. Luk does save, but the customer
     * could not tell.
     */
    public function testTheSaveButtonIsRendered(): void
    {
        $src = self::source();

        self::assertStringContainsString('if ($status<3){print "<td align=center><input type=submit value=', $src,
            'the save button is gone or guarded again');
        self::assertStringNotContainsString('($gem)', $src,
            '$gem is back; it is never assigned, so the button would never appear');
    }

    /** Its label comes from the existing text id rather than a new hardcoded string. */
    public function testTheSaveButtonIsTranslated(): void
    {
        self::assertStringContainsString("findtekst('3|Gem', \$sprog_id)", self::source(),
            'the save button label is hardcoded instead of using the existing text id 3');
    }

    /**
     * Both buttons post the same form and the save block runs for either, so Gem saves and
     * leaves the window open while Luk saves and closes. If that stopped being true, Gem
     * would be a button that appears to do nothing.
     */
    public function testBothButtonsRunTheSameSave(): void
    {
        $src = self::source();

        $save = strpos($src, "if (\$_POST['status']<3) {");
        self::assertNotFalse($save, 'the save block is gone');

        $close = strpos($src, 'if ($submit=="Luk")');
        self::assertNotFalse($close, 'the close branch is gone');
        self::assertLessThan($close, $save,
            'the window closes before saving, so what was typed would be lost');

        // The save must not be conditional on which button was pressed.
        $block = substr($src, $save, $close - $save);
        self::assertStringNotContainsString('$submit=="Gem"', $block,
            'the save now depends on the button, so Luk would no longer save');
    }

    /**
     * Every id this window puts into a statement comes from the request. They are cast
     * where they arrive; serial numbers are text and are escaped at each use.
     */
    public function testRequestValuesReachSqlCastOrEscaped(): void
    {
        $src = self::source();

        self::assertStringContainsString("\$linje_id=(int)ifset(\$_GET, 'linje_id', 0);", $src,
            'linje_id reaches SQL straight from $_GET again');
        self::assertStringContainsString("\$kred_linje_id=(int)ifset(\$_POST, 'kred_linje_id', 0);", $src,
            'kred_linje_id reaches SQL straight from $_POST again');
        self::assertStringContainsString("\$vare_id=(int)ifset(\$_POST, 'vare_id', 0);", $src,
            'vare_id reaches SQL straight from $_POST again');

        // The per-row id and the serial itself are handled at each use.
        self::assertSame(0, preg_match('/where id=\$sn_id\[/', $src),
            'a row id is interpolated without a cast');
        self::assertSame(0, preg_match("/serienr='\\\$serienr\\[/", $src),
            'a serial number is interpolated without escaping');
        self::assertGreaterThanOrEqual(2, substr_count($src, 'db_escape_string($serienr[$x])'),
            'the serial number is no longer escaped on both the insert and the update');
        self::assertGreaterThanOrEqual(5, substr_count($src, '(int)$sn_id[$x]'),
            'not every row id is cast');
    }

    /**
     * Guard rail for the two defects left out of this PR: nothing here changed which rows
     * the return list offers, or which rows Modtag counts. If either moves, it should be
     * because someone decided to, with the customer's data in front of them.
     */
    public function testTheReturnListSelectionIsUnchanged(): void
    {
        $src = self::source();
        self::assertStringContainsString(
            "select * from serienr where (salgslinje_id='\$linje_id' or salgslinje_id<= '0') and kobslinje_id >'0'",
            $src,
            'the return-list selection changed; that needs the 8 successor-less rows established first'
        );

        $modtag = file_get_contents(__DIR__ . '/../../../kreditor/modtag.php');
        self::assertNotFalse($modtag, 'could not read kreditor/modtag.php');
        // Both branches - the credit note and the ordinary negative line - count by the
        // same rule. Counting occurrences rather than checking for one, so changing either
        // is noticed.
        self::assertSame(2, substr_count($modtag, 'and batch_salg_id<=0'),
            'the rule Modtag counts by changed; that is the other half of the same decision');
    }
}
