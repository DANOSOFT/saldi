<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261104 LOE SST-940 Pins the datotid formatting the Dagbog, the check sheets and the employee folder share.
//
//                  Those columns are text: a unix timestamp normally, but older installations also hold a
//                  finished date string ("01-01-1970 01:00"). date() throws a TypeError on that in PHP 8,
//                  so the Dagbog autocomplete answered 500 and the list page stopped halfway through.
//
// History:
// 20261104 LOE SST-940 created.

require_once __DIR__ . '/../../../includes/stdFunc/datotidTilTekst.php';

final class DatotidTilTekstTest extends TestCase
{
    public function testATimestampIsFormatted(): void
    {
        self::assertSame('06-06-2025', datotidTilTekst('1749197221'));
        self::assertSame('06-06-2025', datotidTilTekst(1749197221));
        self::assertSame('06-06-2025', datotidTilTekst('1749197221', 'd-m-Y'));
        self::assertSame('2025', datotidTilTekst('1749197221', 'Y'));
    }

    public function testATextDateIsShownAsItStands(): void
    {
        // The value the customer's old notes carry; this used to be an uncaught TypeError.
        self::assertSame('01-01-1970 01:00', datotidTilTekst('01-01-1970 01:00'));
        self::assertSame('01-01-1970 01:00', datotidTilTekst('01-01-1970 01:00', 'd-m-Y'));
        self::assertSame('01-01-1970 01:00', datotidTilDatoOgTid('01-01-1970 01:00'));
    }

    public function testAnEmptyValueStaysEmpty(): void
    {
        self::assertSame('', datotidTilTekst(''));
        self::assertSame('', datotidTilTekst(null));
        self::assertSame('', datotidTilDatoOgTid(''));
        self::assertSame('', datotidTilDatoOgTid(null));
    }

    public function testTheListCellKeepsItsShapeForATimestamp(): void
    {
        // What the Dagbog and the check sheet lists print, unchanged for normal data. The time is
        // built from date() itself, so the assertion does not depend on the runner's time zone.
        $ts = '1749197221';
        self::assertSame(date('d-m-y', (int)$ts) . ' kl. ' . date('H:i', (int)$ts), datotidTilDatoOgTid($ts));
        self::assertSame(date('d-m-y', (int)$ts) . ' at ' . date('H:i', (int)$ts), datotidTilDatoOgTid($ts, 'at'));
    }

    public function testOnlyAWholeNumberOfSecondsCountsAsATimestamp(): void
    {
        // '0' is a real timestamp and formats like one - the workaround the ticket suggests for the data.
        self::assertTrue(datotidErTidsstempel('0'));
        self::assertSame('01-01-1970', datotidTilTekst('0'));
        // Values that look numeric but are not a timestamp are shown as they stand, not as 1970.
        self::assertFalse(datotidErTidsstempel('01-01-1970 01:00'));
        self::assertFalse(datotidErTidsstempel('0.5'));
        self::assertSame('0.5', datotidTilTekst('0.5'));
    }

    public function testATextValueIsEscapedForThePage(): void
    {
        // The text goes straight into the list and into the autocomplete's HTML, so the helper escapes it
        // once instead of every call site doing so; a formatted timestamp is unaffected.
        self::assertSame('&lt;b&gt;x&lt;/b&gt;', datotidTilTekst('<b>x</b>'));
        self::assertSame('a&amp;b', datotidTilDatoOgTid('a&b'));
        self::assertSame('06-06-2025', datotidTilTekst('1749197221'));
    }
}
