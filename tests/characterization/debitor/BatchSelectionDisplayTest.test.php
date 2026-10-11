<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261008 MJ SST-833 Pins how the batch choice presents itself on a sales line.
//                  MEDSHOP switched Batch-kontrol on, saw Saldi's serial-number icon appear
//                  on items that have no serial number, and a batch window showing neither
//                  batch number nor expiry date - so the feature looked broken and they
//                  switched it back off.
//
//                  Only the presentation is covered here. The krediter() defect in
//                  includes/ordrefunc.php is escalated separately: with a manually typed
//                  return line it queries batch_salg on linje_id = 0 and, but for the
//                  cryptic error it raises first, would add the returned quantity to an
//                  unrelated item's purchase batch. The last test below pins that nothing
//                  in this change touched it.
final class BatchSelectionDisplayTest extends TestCase
{
    private const ORDRE = __DIR__ . '/../../../debitor/ordre.php';
    private const BATCH = __DIR__ . '/../../../debitor/batch.php';
    private const DOC = __DIR__ . '/../../../doc/Funktionsbeskrivelse/batch_udlobsdato.txt';

    private function read(string $path): string
    {
        $src = file_get_contents($path);
        self::assertNotFalse($src, 'could not read ' . basename($path));
        return $src;
    }

    /** The line that opens batch() must not look like the one that opens serienummer(). */
    public function testTheBatchControlIsNotTheSerialNumberIcon(): void
    {
        $src = $this->read(self::ORDRE);

        $at = strpos($src, 'if ($batchvare && $antal > 0) print');
        self::assertNotFalse($at, 'the batch control is gone from the sales line');
        $line = substr($src, $at, strpos($src, "\n", $at) - $at);

        self::assertStringContainsString('onClick=\\"batch($linje_id)', $line,
            'this control no longer opens the batch window');
        self::assertStringNotContainsString('serienr.png', $line,
            'the batch control uses the serial-number icon again, which is the reported confusion');
        self::assertStringNotContainsString("findtekst('1497|Serienummer'", $line,
            'the batch control is labelled "Serienummer" again');
        self::assertStringContainsString('../ikoner/opslag.png', $line,
            'the batch control lost its lookup icon');
        self::assertStringContainsString("findtekst('1496|Vælg fra købsordre', \$sprog_id)", $line,
            'the batch control no longer describes what it does');
    }

    /** A genuine serial-number line keeps the serial icon - acceptance criterion 2. */
    public function testASerialNumberLineKeepsItsIcon(): void
    {
        $src = $this->read(self::ORDRE);

        $at = strpos($src, 'elseif ($serienr) print');
        self::assertNotFalse($at, 'the serial-number control is gone from the sales line');
        $line = substr($src, $at, strpos($src, "\n", $at) - $at);

        self::assertStringContainsString('onClick=\\"serienummer($linje_id)', $line);
        self::assertStringContainsString('serienr.png', $line,
            'the serial-number line lost its own icon');
        self::assertStringContainsString("findtekst('1501|Vælg serienr'", $line);
    }

    /** The window now shows what it exists to show. */
    public function testTheBatchWindowShowsBatchNumberAndExpiry(): void
    {
        $src = $this->read(self::BATCH);

        self::assertStringContainsString("findtekst('5005|Batchnr.', \$sprog_id)", $src,
            'the batch number column is gone');
        self::assertStringContainsString("findtekst('5001|Udløbsdato', \$sprog_id)", $src,
            'the expiry date column is gone');
        self::assertStringContainsString("\$batch_no[\$x]=\$row['batch_no'];", $src,
            'the batch number is no longer read from the row');
        self::assertStringContainsString("\$udlobsdato[\$x]=\$row['due_date'];", $src,
            'the expiry date is no longer read from the row');
    }

    /** Both labels come from ids that already exist, so tekster.csv needs nothing new. */
    public function testTheLabelsUseExistingTextIds(): void
    {
        $src = $this->read(self::BATCH);
        foreach (['5005', '5001'] as $id) {
            self::assertSame(1, preg_match("/findtekst\('$id\|/", $src),
                "text id $id is no longer used for its column heading");
        }
    }

    /** The batch number is escaped - it is free text typed on the purchase order. */
    public function testTheBatchNumberIsEscapedForOutput(): void
    {
        self::assertStringContainsString('htmlspecialchars((string)$batch_no[$x])', $this->read(self::BATCH),
            'the batch number is printed unescaped; it is free text from the purchase order');
    }

    /**
     * Both sections print into the same table, so the incoming-orders rows need the same
     * number of cells or the columns slide out of line.
     */
    public function testBothSectionsOfTheWindowHaveTheSameColumnCount(): void
    {
        $src = $this->read(self::BATCH);

        self::assertSame(0, substr_count($src, 'colspan=3'),
            'a separator still spans three columns, so it stops short of the new ones');
        self::assertGreaterThanOrEqual(3, substr_count($src, 'colspan=5'),
            'the separators no longer span the whole table');

        // The incoming rows carry two empty cells where batch number and expiry would go.
        self::assertSame(2, substr_count($src, '<td></td><td></td><td align = right>".reducer($rest[$x])."</td>'),
            'the incoming-order rows no longer line up with the batch columns');
    }

    /** recucer() does not exist; that line was fatal for an unreceived credit note. */
    public function testTheMisspelledHelperIsGone(): void
    {
        $src = $this->read(self::BATCH);

        // The only mention left should be the comment explaining the fix.
        self::assertSame(0, substr_count($src, '".recucer('),
            'the misspelled helper is called again; it does not exist and the line fatals');
        self::assertGreaterThanOrEqual(2, substr_count($src, 'reducer($rest[$x])'),
            'both branches should format the quantity with reducer()');
    }

    /**
     * The incoming-order rows must keep their order type in their own array.
     *
     * $art holds the current order's type as a string (batch.php line ~119), so the old
     * $art[$x] = $r2['art'] was a string-offset write: PHP stored only the first byte, the
     * "(KN)" test was therefore always false, every row emitted "Only the first byte will be
     * assigned to the string offset", and $art itself was corrupted before being posted back
     * in a hidden field. Reported by CodeRabbit on PR #789.
     */
    public function testIncomingOrderTypesAreKeptInTheirOwnArray(): void
    {
        $src = $this->read(self::BATCH);

        self::assertStringContainsString('$incomingArt = array();', $src,
            'the incoming order types have no array of their own again');
        self::assertStringContainsString("\$incomingArt[\$x]=\$r2['art'];", $src,
            'the incoming order type is not stored in that array');
        self::assertStringContainsString("if (\$incomingArt[\$x]=='DK')", $src,
            'the credit-note test reads something other than the incoming order type');

        // Nothing may write into $art by offset - that is the defect itself.
        self::assertSame(0, preg_match('/\$art\[\$x\]\s*=/', $src),
            'a value is written into the $art string by offset again');
        self::assertSame(0, preg_match('/if \(\$art\[\$x\]\s*==/', $src),
            'the credit-note test reads a byte of the $art string again');
    }

    /** The known-gaps list records what changed. */
    public function testTheDocumentationRecordsThis(): void
    {
        $doc = $this->read(self::DOC);

        self::assertStringContainsString('partly closed 2026-10-08, SST-833', $doc,
            'batch_udlobsdato.txt §8 item 3 no longer records this change');
        self::assertStringContainsString('it now shows Batchnr. and Udløbsdato', $doc);
        // The part that is still open must not be recorded as done.
        self::assertStringContainsString('that part is still open', $doc,
            'the missing expiry warning at point of sale is no longer recorded as open');
    }

    /**
     * Guard rail for the defect this change deliberately leaves alone. krediter() queries
     * batch_salg on the line's kred_linje_id, which is 0 for a manually typed return line,
     * and then writes batch_kob.rest for whatever came back first. If that moves, it should
     * be because Nicolai or Lui decided it.
     */
    public function testTheCreditLookupIsUnchanged(): void
    {
        $src = $this->read(__DIR__ . '/../../../includes/ordrefunc.php');

        self::assertStringContainsString('select * from batch_salg where linje_id=$kred_linje_id order by id desc', $src,
            'the batch_salg lookup in krediter() changed; that is escalated, not fixed here');
        self::assertStringContainsString("update batch_kob set rest = rest+\$antal where id ='\$batch_kob_id[1]'", $src,
            'the restock in krediter() changed; that is escalated, not fixed here');
    }
}
