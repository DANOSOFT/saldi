<?php
// tests/characterization/finans/JournalAmountTest.test.php
//
// MB-86: the cash journal stored what usdecimal() made of the Beløb field (three decimals) but showed two, and
// every Gem wrote every line again from its field. 0,005 was stored as 0.005 and shown as 0,01, and the next save
// of any line rewrote it to 0.010 (0,001 to 0.000, 12,345 to 12.350).
//
// Pinned here, without a database (finans/kassekladde_includes/journalAmount.php):
//   - a typed amount is rounded to whole øre, half away from zero, also where the float is just below the half
//   - saving again what the field shows never changes the stored amount (the round trip through dkdecimal())
//   - a line whose values are the stored ones counts as unchanged, so opdater() does not write it
//   - an amount stored with three decimals before MB-86 is kept while the field still shows it
//   - usdecimal() itself still keeps three decimals for every other caller
//
// History:
// 20261125 CL/MMR MB-86: created.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/std_func.php';
require_once dirname(__DIR__, 3) . '/finans/kassekladde_includes/journalAmount.php';

final class JournalAmountTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: float}>
     */
    public static function typedAmounts(): array
    {
        return array(
            'half an øre rounds up'                  => array('0,005', 0.01),
            'a negative half øre rounds away from 0' => array('-0,005', -0.01),
            'a tenth of an øre rounds to zero'       => array('0,001', 0.0),
            'the ticket example 12,345'              => array('12,345', 12.35),
            'negative 12,345'                        => array('-12,345', -12.35),
            '1,005 is 1.00499... as a float'         => array('1,005', 1.01),
            '2,675 is 2.67499... as a float'         => array('2,675', 2.68),
            'just below the half stays down'         => array('0,0049', 0.0),
            'just below the half on 1'               => array('1,0049', 1.0),
            'more decimals, still half away'         => array('7,12500001', 7.13),
            'thousands separator'                    => array('1.234,56', 1234.56),
            'thousands separator and three decimals' => array('1.234,565', 1234.57),
            'a whole negative amount'                => array('-50', -50.0),
            'half a krone'                           => array('0,5', 0.5),
            'a leading plus'                         => array('+5', 5.0),
            'spaces around the text'                 => array(' 12,3 ', 12.3),
            'an empty field'                         => array('', 0.0),
            'a negative zero is zero'                => array('-0,001', 0.0),
            'the largest amount the column holds'    => array('999.999.999.999,994', 999999999999.99),
            'text that is no number'                 => array('abc', 0.0),
        );
    }

    #[DataProvider('typedAmounts')]
    public function testTypedAmountIsRoundedToWholeOere(string $typed, float $expected): void
    {
        $this->assertSame($expected, journalAmountFromInput($typed));
    }

    public function testNegativeZeroIsNotWrittenAsMinusZero(): void
    {
        $this->assertSame('0', (string)journalAmountFromInput('-0,004'));
    }

    /**
     * Every amount the field can show is stored as that amount: saving the shown text again changes nothing.
     */
    public function testSavingWhatTheFieldShowsKeepsTheStoredAmount(): void
    {
        for ($thousandths = -3000; $thousandths <= 3000; $thousandths++) {
            $typed = str_replace('.', ',', sprintf('%.3F', $thousandths / 1000));
            $stored = journalAmountFromInput($typed);
            $shown = dkdecimal($stored, 2);
            $this->assertSame($stored, journalAmountFromInput($shown), "typed $typed, stored $stored, shown $shown");
        }
    }

    /**
     * A database value with three decimals is shown by dkdecimal() as journalAmountRound() rounds it, so a line
     * stored before MB-86 compares with what its field showed.
     */
    public function testRoundingADatabaseValueMatchesWhatTheFieldShows(): void
    {
        for ($thousandths = -3000; $thousandths <= 3000; $thousandths++) {
            $stored = sprintf('%.3F', $thousandths / 1000);
            $this->assertSame(journalAmountFromInput(dkdecimal($stored, 2)), journalAmountRound($stored), "stored $stored");
        }
        $this->assertSame(0.0, journalAmountRound(null));
    }

    public function testUsdecimalStillKeepsThreeDecimalsForItsOtherCallers(): void
    {
        $this->assertEquals(0.005, usdecimal('0,005', 2));
        $this->assertEquals(12.345, usdecimal('12,345', 2));
    }

    public function testStoredAmountIsKeptWhileTheFieldShowsIt(): void
    {
        $this->assertSame('0.005', journalAmountKept('0.005', 0.01));
        $this->assertSame('12.345', journalAmountKept('12.345', 12.35));
        $this->assertSame('1.010', journalAmountKept('1.010', 1.01));
        // The user typed another amount: the new, rounded one is written
        $this->assertSame(0.02, journalAmountKept('0.005', 0.02));
        $this->assertSame(12.34, journalAmountKept('12.345', 12.34));
        // A new line, or a line without a stored amount, takes the new amount
        $this->assertSame(0.01, journalAmountKept(null, 0.01));
        $this->assertSame(0.0, journalAmountKept('', 0.0));
    }

    /**
     * @return array<string, string|null> A kassekladde row the way the database returns it.
     */
    private static function storedLine(): array
    {
        return array(
            'bilag' => '14', 'transdate' => '2026-11-25', 'beskrivelse' => 'Porto', 'd_type' => 'F', 'debet' => '2390',
            'k_type' => 'F', 'kredit' => '6480', 'faktura' => '', 'amount' => '0.010', 'debetvat' => '', 'kreditvat' => null,
            'momsfri' => '', 'afd' => '0', 'projekt' => '', 'ansat' => '0', 'valuta' => '0', 'forfaldsdate' => null,
            'betal_id' => null,
        );
    }

    /**
     * @return array<string, mixed> The same line as opdater() builds it from the posted form.
     */
    private static function postedLine(): array
    {
        return array(
            'bilag' => 14, 'transdate' => '2026-11-25', 'beskrivelse' => 'Porto', 'd_type' => 'F', 'debet' => 2390,
            'k_type' => 'F', 'kredit' => 6480, 'faktura' => '', 'amount' => 0.01, 'debetvat' => '', 'kreditvat' => '',
            'momsfri' => '', 'afd' => 0, 'projekt' => '', 'ansat' => 0, 'valuta' => 0, 'forfaldsdate' => null,
            'betal_id' => '',
        );
    }

    public function testAnUntouchedLineIsUnchanged(): void
    {
        $this->assertTrue(journalLineUnchanged(self::storedLine(), self::postedLine()));
    }

    public function testALineStoredWithThreeDecimalsIsUnchangedWhileItsFieldShowsTheAmount(): void
    {
        $stored = self::storedLine();
        $stored['amount'] = '0.005';
        $this->assertTrue(journalLineUnchanged($stored, self::postedLine()));
        $posted = self::postedLine();
        $posted['amount'] = 0.02;
        $this->assertFalse(journalLineUnchanged($stored, $posted));
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function changedValues(): array
    {
        return array(
            'amount'       => array('amount', 0.02),
            'description'  => array('beskrivelse', 'Porto, rettet'),
            'date'         => array('transdate', '2026-11-24'),
            'voucher'      => array('bilag', 15),
            'debit'        => array('debet', 2450),
            'credit type'  => array('k_type', 'K'),
            'debit VAT'    => array('debetvat', 'K1'),
            'VAT exempt'   => array('momsfri', 'on'),
            'department'   => array('afd', 2),
            'employee'     => array('ansat', 3),
            'currency'     => array('valuta', 1),
            'due date'     => array('forfaldsdate', '2026-12-01'),
            'invoice'      => array('faktura', '1001'),
        );
    }

    #[DataProvider('changedValues')]
    public function testAnyChangedValueMakesTheLineChanged(string $column, $value): void
    {
        $posted = self::postedLine();
        $posted[$column] = $value;
        $this->assertFalse(journalLineUnchanged(self::storedLine(), $posted));
    }

    public function testEmptyAndZeroMeanTheSameAsNull(): void
    {
        $stored = self::storedLine();
        $stored['debetvat'] = null;
        $stored['afd'] = null;
        $this->assertTrue(journalLineUnchanged($stored, self::postedLine()));
    }

    public function testALineThatWasNotFoundOrLacksAColumnIsWritten(): void
    {
        $this->assertFalse(journalLineUnchanged(null, self::postedLine()));
        $stored = self::storedLine();
        unset($stored['valuta']);
        $this->assertFalse(journalLineUnchanged($stored, self::postedLine()));
        $stored = self::storedLine();
        $stored['amount'] = null;
        $posted = self::postedLine();
        $posted['amount'] = 0.0;
        $this->assertFalse(journalLineUnchanged($stored, $posted));
    }

    public function testTextIsComparedExactly(): void
    {
        $posted = self::postedLine();
        $posted['beskrivelse'] = 'Porto ';
        $this->assertFalse(journalLineUnchanged(self::storedLine(), $posted));
        $stored = self::storedLine();
        $stored['ansat'] = 'hkl';
        $this->assertFalse(journalLineUnchanged($stored, self::postedLine()));
    }
}
