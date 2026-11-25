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
//   - an amount stored with three decimals before MB-86 is kept while the field still holds the text it was drawn
//     with, and any other text is saved as a new, rounded amount (0,006 over a stored 0.005 shown as 0,01 -> 0.010)
//   - usdecimal() itself still keeps three decimals for every other caller
//
// History:
// 20261125 CL/MMR MB-86: created.
// 20261125 CL/MMR MB-86 (Codex round 1) The kept amount is decided on the field's text; regression tests for an edit that rounds to the shown amount.
// 20261125 CL/MMR MB-86 (Codex round 2) The column's range limit and the typed sign that decides the debit/credit swap.
// 20261125 CL/MMR MB-86 (Codex round 3) A stored amount at the limit, left as drawn, is not range checked and is kept.
// 20261125 CL/MMR MB-86 (Codex round 4) One rule for both signs (journalAmountToSave()), tested at +-999999999999.995 through the decision opdater() makes.

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

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function amountsAtTheColumnLimit(): array
    {
        return array(
            'the highest cent the column holds'          => array('999.999.999.999,99', true),
            'its negative'                               => array('-999.999.999.999,99', true),
            'below the half of the next cent'            => array('999.999.999.999,994', true),
            'the first text that rounds past the limit'  => array('999.999.999.999,995', false),
            'its negative counterpart'                   => array('-999.999.999.999,995', false),
            'a whole amount past the limit'              => array('1.000.000.000.000', false),
            'past the limit without thousands separators' => array('9999999999999', false),
            'an ordinary amount'                         => array('12,345', true),
            'an empty field'                             => array('', true),
            'a dot as decimal separator stays refused'   => array('1234.56', false),
        );
    }

    #[DataProvider('amountsAtTheColumnLimit')]
    public function testAnAmountThatRoundsPastTheColumnIsRefused(string $typed, bool $valid): void
    {
        $this->assertSame($valid, journalAmountIsValid($typed));
    }

    public function testAStoredAmountAtTheLimitLeftAsDrawnIsNotRangeChecked(): void
    {
        // 999999999999.995 fits the column, but the page draws it as 1.000.000.000.000,00
        $this->assertSame(dkdecimal('999999999999.995', 2), journalAmountShown('999999999999.995'));
        $this->assertSame('1.000.000.000.000,00', journalAmountShown('999999999999.995'));
        $this->assertTrue(journalAmountDrawn('999999999999.995', '1.000.000.000.000,00'));
        $this->assertTrue(journalAmountIsValid('1.000.000.000.000,00', '999999999999.995'));
        // The same text without the stored line (a new line, or the "+=" copy) is a new amount past the limit
        $this->assertFalse(journalAmountIsValid('1.000.000.000.000,00'));
        // A newly typed amount past the limit is refused also on that line
        $this->assertFalse(journalAmountIsValid('999.999.999.999,995', '999999999999.995'));
        $this->assertFalse(journalAmountIsValid('1.000.000.000.000,01', '999999999999.995'));
        // and opdater() keeps its exact value, so nothing past the limit is written
        $this->assertSame(array('999999999999.995', true, false), self::saveLine('999999999999.995', '1.000.000.000.000,00'));
    }

    public function testANegativeStoredAmountAtTheLimitLeftAsDrawnIsNotRangeCheckedEither(): void
    {
        // Codex round 4: one rule for both signs
        $this->assertSame('-1.000.000.000.000,00', journalAmountShown('-999999999999.995'));
        $this->assertSame(dkdecimal('-999999999999.995', 2), journalAmountShown('-999999999999.995'));
        $this->assertTrue(journalAmountDrawn('-999999999999.995', '-1.000.000.000.000,00'));
        $this->assertTrue(journalAmountIsValid('-1.000.000.000.000,00', '-999999999999.995'));
        $this->assertFalse(journalAmountIsValid('-1.000.000.000.000,00'));
        $this->assertFalse(journalAmountIsValid('-999.999.999.999,995', '-999999999999.995'));
        // Its exact magnitude on swapped accounts, as every save has written a negative amount: 999999999999.995 fits
        $this->assertSame(array('999999999999.995', false, true), self::saveLine('-999999999999.995', '-1.000.000.000.000,00'));
        // and once saved that way it is drawn as 1.000.000.000.000,00 and left alone
        $this->assertSame(array('999999999999.995', true, false), self::saveLine('999999999999.995', '1.000.000.000.000,00'));
    }

    public function testTheHighestValidAmountIsSavedAsTyped(): void
    {
        $this->assertSame(999999999999.99, journalAmountFromInput('999.999.999.999,99'));
        $this->assertSame(-999999999999.99, journalAmountFromInput('-999.999.999.999,994'));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function typedSigns(): array
    {
        return array(
            'rounds to zero but is negative' => array('-0,004', true),
            'a negative half øre'            => array('-0,005', true),
            'an ordinary negative amount'    => array('-50', true),
            'negative with thousands'        => array('-1.234,56', true),
            'a negative zero'                => array('-0,000', false),
            'minus and nothing else'         => array('-', false),
            'a positive amount'              => array('0,004', false),
            'a leading plus'                 => array('+5', false),
            'an empty field'                 => array('', false),
        );
    }

    #[DataProvider('typedSigns')]
    public function testTheTypedSignDecidesTheSwap(string $typed, bool $negative): void
    {
        $this->assertSame($negative, journalAmountIsNegative($typed));
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
            $this->assertSame(dkdecimal($stored, 2), journalAmountShown($stored), "stored $stored");
        }
        $this->assertSame('1.234,57', journalAmountShown('1234.565'));
        $this->assertSame(0.0, journalAmountRound(null));
    }

    public function testUsdecimalStillKeepsThreeDecimalsForItsOtherCallers(): void
    {
        $this->assertEquals(0.005, usdecimal('0,005', 2));
        $this->assertEquals(12.345, usdecimal('12,345', 2));
    }

    public function testAnAmountLeftAsDrawnKeepsItsExactStoredValue(): void
    {
        $this->assertSame(array('0.005', false), journalAmountToSave('0,01', '0.005'));
        $this->assertSame(array('0.005', false), journalAmountToSave(' 0,01 ', '0.005'));
        $this->assertSame(array('12.345', false), journalAmountToSave('12,35', '12.345'));
        $this->assertSame(array('1234.565', false), journalAmountToSave('1.234,57', '1234.565'));
        $this->assertSame(array('1.010', false), journalAmountToSave('1,01', '1.010'));
        // A stored negative amount: its exact magnitude, swapped
        $this->assertSame(array('5.005', true), journalAmountToSave('-5,01', '-5.005'));
        $this->assertSame(array('7.500', true), journalAmountToSave('-7,50', '-7.500'));
    }

    public function testOtherTextInTheFieldIsANewRoundedAmount(): void
    {
        // Codex round 1: 0,006 rounds to the 0,01 the field showed, and must still replace the stored 0.005
        $this->assertSame(array(0.01, false), journalAmountToSave('0,006', '0.005'));
        $this->assertSame(array(0.01, false), journalAmountToSave('0,010', '0.005'));
        $this->assertSame(array(0.02, false), journalAmountToSave('0,02', '0.005'));
        $this->assertSame(array(12.34, false), journalAmountToSave('12,34', '12.345'));
        $this->assertSame(array(1234.57, false), journalAmountToSave('1234,57', '1234.565'));
        // A negative amount typed over a positive one, and the typed sign of an amount that rounds to zero
        $this->assertSame(array(0.01, true), journalAmountToSave('-0,01', '0.005'));
        $this->assertSame(array(0.0, true), journalAmountToSave('-0,004', '10.000'));
        $this->assertSame(array(5.0, true), journalAmountToSave('-5', '-5.005'));
    }

    public function testANewLineIsRoundedAndSwappedOnItsTypedSign(): void
    {
        $this->assertSame(array(0.01, false), journalAmountToSave('0,005'));
        $this->assertSame(array(0.0, true), journalAmountToSave('-0,004'));
        $this->assertSame(array(50.0, true), journalAmountToSave('-50'));
        $this->assertSame(array(0.0, false), journalAmountToSave(''));
    }

    /**
     * The decision opdater() makes for a stored line whose Beløb field is posted with $typed: the amount it writes,
     * whether it swaps the accounts, and whether the line counts as unchanged (no write).
     *
     * @return array{0: string|float, 1: bool, 2: bool}
     */
    private static function saveLine(string $storedAmount, string $typed, string $text = 'Porto'): array
    {
        $stored = self::storedLine();
        $stored['amount'] = $storedAmount;
        $posted = self::postedLine();
        $posted['beskrivelse'] = $text;
        list($posted['amount'], $swap) = journalAmountToSave($typed, $storedAmount);
        if ($swap) {
            list($posted['debet'], $posted['kredit']) = array($posted['kredit'], $posted['debet']);
            list($posted['d_type'], $posted['k_type']) = array($posted['k_type'], $posted['d_type']);
            list($posted['debetvat'], $posted['kreditvat']) = array($posted['kreditvat'], $posted['debetvat']);
        }
        return array($posted['amount'], journalLineUnchanged($stored, $posted), $swap);
    }

    public function testALegacyLineLeftAloneIsNotWritten(): void
    {
        $this->assertSame(array('0.005', true, false), self::saveLine('0.005', '0,01'));
    }

    public function testALegacyLineEditedToAnAmountThatRoundsToTheShownOneIsWritten(): void
    {
        $this->assertSame(array(0.01, false, false), self::saveLine('0.005', '0,006'));
        $this->assertSame(array(0.02, false, false), self::saveLine('0.005', '0,02'));
    }

    public function testADescriptionChangeKeepsTheExactStoredAmountAtTheLimit(): void
    {
        $this->assertSame(array('999999999999.995', false, false), self::saveLine('999999999999.995', '1.000.000.000.000,00', 'Porto, rettet'));
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

    public function testTheAmountComparesWithTheStoredValueToThreeDecimals(): void
    {
        // 0.010 in the database and 0.01 from the save are the same amount
        $this->assertTrue(journalLineUnchanged(self::storedLine(), self::postedLine()));
        // but a stored 0.005 is not the 0.01 a save would write (Codex round 1)
        $stored = self::storedLine();
        $stored['amount'] = '0.005';
        $this->assertFalse(journalLineUnchanged($stored, self::postedLine()));
        $posted = self::postedLine();
        $posted['amount'] = '0.005';
        $this->assertTrue(journalLineUnchanged($stored, $posted));
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
