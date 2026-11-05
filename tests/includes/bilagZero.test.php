<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../finans/kassekladde_includes/bilagNumber.php';

/**
 * SST-934: voucher number 0 can be used in the cash journal.
 *
 * The save reads an empty Bilag field as '0' and gives such a line the journal's next number (SST-817).
 * A typed 0 travels as '00' and is saved as 0; a line saved under 0 shows 0, so the next save keeps it.
 *
 * History:
 * 20261105 CL/MMR Created (SST-934).
 */
final class bilagZero extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function formValues(): array
    {
        return [
            'empty field'           => ['', '0'],
            'spaces only'           => ['  ', '0'],
            'not a string'          => [null, '0'],
            'typed zero'            => ['0', '00'],
            'zero with spaces'      => [' 0 ', '00'],
            'several zeros'         => ['000', '00'],
            'number'                => ['17', '17'],
            'number with zero'      => ['05', '05'],
            'delete marker'         => ['-', '-'],
            'copy to the end'       => ['=*', '=*'],
            'copy from above'       => ['=', '='],
            'next after above'      => ['+', '+'],
            'not a number'          => ['abc', 'abc'],
        ];
    }

    #[DataProvider('formValues')]
    public function testTheFieldIsCarriedSoAnEmptyFieldAndATypedZeroDiffer($raw, string $carried): void
    {
        self::assertSame($carried, bilagFromInput($raw));
    }

    public function testOnlyAnEmptyFieldIsNumberedByTheSave(): void
    {
        self::assertTrue(bilagIsUnnumbered(bilagFromInput('')), 'an empty field gets the next number (SST-817)');
        self::assertTrue(bilagIsUnnumbered('0'), 'a staged 0 is an empty field');
        self::assertTrue(bilagIsUnnumbered(''), 'a blank staged value is an empty field');
        self::assertTrue(bilagIsUnnumbered(null), 'no staged value is an empty field');
        self::assertFalse(bilagIsUnnumbered(bilagFromInput('0')), 'a typed 0 is kept');
        self::assertFalse(bilagIsUnnumbered('000'), 'a zero written with several digits is kept');
        self::assertFalse(bilagIsUnnumbered('7'), 'a number is kept');
        self::assertFalse(bilagIsUnnumbered('2147483648'), 'a number the column cannot hold is not unnumbered, the size checks replace it');
        self::assertTrue(bilagIsUnnumbered('-3'), 'a negative number is not a voucher number');
        self::assertTrue(bilagIsUnnumbered('abc'), 'text is not a voucher number');
    }

    public function testATypedZeroStaysZeroForTheSave(): void
    {
        // kontroller() casts a number to int and leaves '0' (empty) alone; '00' is neither, so it passes unchanged
        $carried = bilagFromInput('0');
        self::assertTrue((bool)$carried, 'PHP does not read the typed zero as empty');
        self::assertTrue($carried == '0', 'it is still the number 0, so it groups with other 0 lines');
        self::assertSame(0, (int)$carried, 'and the save writes 0');
        self::assertSame(1, $carried + 1, '"+" on the next line gives 1, as it did after a 0');
    }

    public function testASavedZeroIsShownAsZero(): void
    {
        self::assertSame('0', bilagFieldValue('0', true), 'kassekladde.bilag 0 is voucher 0');
        self::assertSame('0', bilagFieldValue(0, true), 'also as an integer');
        self::assertSame('', bilagFieldValue('0', false), 'a staged 0 is an empty field (SST-888)');
        self::assertSame('0', bilagFieldValue('00', false), 'a staged typed zero is shown as 0');
        self::assertSame('', bilagFieldValue('', false));
        self::assertSame('', bilagFieldValue(null, true));
        self::assertSame('12', bilagFieldValue('12', true));
        self::assertSame('abc', bilagFieldValue('abc', false), 'a refused value comes back as typed (SST-888)');
    }

    public function testASavedZeroSurvivesTheNextSave(): void
    {
        // the page shows the saved 0, the user saves without touching it, the save keeps it
        $shown = bilagFieldValue('0', true);
        self::assertFalse(bilagIsUnnumbered(bilagFromInput($shown)));
        // the user empties the field: the line is numbered again, as SST-817 does
        self::assertTrue(bilagIsUnnumbered(bilagFromInput('')));
    }
}
