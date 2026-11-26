<?php
// tests/characterization/finans/BilagOpenVoucherTest.test.php
//
// SST-895: while the cash journal's highest voucher does not balance, the blank line at the end continues it
// with the same number and date, so a voucher typed over several lines keeps one number.
// Once it balances, the blank line gets the journal's next number as before.
// bilagOpenVoucher() in finans/kassekladde_includes/bilagNumber.php is that rule without database access.
//
// History:
// 20261119 CL/SS SST-895: created.
// 20261126 CL/SS SST-895 review: a currency voucher that balances in its own currency is balanced only as posting allows it (base difference under 0.10).
//                The date is the voucher's latest whatever the sort, and a voucher number that does not fit is ignored.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/finans/kassekladde_includes/bilagNumber.php';

final class BilagOpenVoucherTest extends TestCase
{
    private static function line($bilag, string $debet, string $kredit, float $amount, string $dato = '19-11-2026', ?float $foreign = null, string $diffkonto = ''): array
    {
        return array('bilag' => $bilag, 'dato' => $dato, 'debet' => $debet, 'kredit' => $kredit, 'belob' => $amount, 'amount' => $foreign ?? $amount, 'diffkonto' => $diffkonto);
    }

    public function testADebitWithoutItsCreditKeepsTheVoucherOpen(): void
    {
        $this->assertSame(array('bilag' => 7, 'dato' => '19-11-2026'), bilagOpenVoucher(array(self::line(7, '58000', '', 1000))));
    }

    public function testAVoucherOverThreeLinesStaysOpenUntilItBalances(): void
    {
        $lines = array(self::line(7, '58000', '', 1000), self::line(7, '', '1010', 400));
        $this->assertSame(7, bilagOpenVoucher($lines)['bilag']);
        $lines[] = self::line(7, '', '1020', 600);
        $this->assertNull(bilagOpenVoucher($lines));
    }

    public function testALineWithBothAccountsBalancesItself(): void
    {
        $this->assertNull(bilagOpenVoucher(array(self::line(7, '58000', '1010', 1000))));
    }

    public function testOnlyTheHighestVoucherCounts(): void
    {
        $lines = array(self::line(9, '58000', '1010', 250), self::line(3, '58000', '', 1000));
        $this->assertNull(bilagOpenVoucher($lines), 'an older unbalanced voucher does not hold the blank line back');
        $lines[] = self::line(10, '', '1010', 80, '20-11-2026');
        $this->assertSame(array('bilag' => 10, 'dato' => '20-11-2026'), bilagOpenVoucher($lines));
    }

    public function testTheDateComesFromTheVouchersLastLine(): void
    {
        $lines = array(self::line(4, '58000', '', 500, '01-11-2026'), self::line(4, '', '1010', 100, '02-11-2026'));
        $this->assertSame(array('bilag' => 4, 'dato' => '02-11-2026'), bilagOpenVoucher($lines));
    }

    public function testACurrencyVoucherIsBalancedOnlyWhenPostingWouldBookItsRateDifference(): void
    {
        $small = array(self::line(8, '58000', '', 745.00, '01-11-2026', 100.00, '3690'), self::line(8, '', '1010', 745.05, '03-11-2026', 100.00, '3690'));
        $this->assertNull(bilagOpenVoucher($small), 'EUR 100 against EUR 100, DKK 0.05 apart: posting books it on the difference account, so the voucher is closed');
        $noAccount = array(self::line(8, '58000', '', 745.00, '01-11-2026', 100.00), self::line(8, '', '1010', 745.05, '03-11-2026', 100.00));
        $this->assertSame(8, bilagOpenVoucher($noAccount)['bilag'], 'no difference account: posting cannot book the 0.05, so it stays open');
        $large = array(self::line(8, '58000', '', 745.00, '01-11-2026', 100.00, '3690'), self::line(8, '', '1010', 746.20, '03-11-2026', 100.00, '3690'));
        $this->assertSame(8, bilagOpenVoucher($large)['bilag'], 'DKK 1.20 apart: posting refuses it (bogfor.php:1063 books under 0.10 only), so it stays open');
        $small[] = self::line(8, '', '1020', 0.05, '03-11-2026', 0.01, '3690');
        $this->assertSame(8, bilagOpenVoucher($small)['bilag'], 'off in its own currency: open, whatever the base difference');
    }

    public function testTheDateIsTheVouchersLatestWhateverTheSort(): void
    {
        $lines = array(self::line(4, '', '1010', 100, '02-11-2026'), self::line(4, '58000', '', 500, '01-11-2026'));
        $this->assertSame(array('bilag' => 4, 'dato' => '02-11-2026'), bilagOpenVoucher($lines), 'a descending view lists the latest line first');
    }

    public function testAVoucherNumberThatDoesNotFitIsIgnored(): void
    {
        $lines = array(self::line(6, '58000', '', 100), self::line('99999999999', '58000', '', 100), self::line('12abc', '58000', '', 100));
        $this->assertSame(6, bilagOpenVoucher($lines)['bilag'], 'an out-of-range or non-numeric staged number is never proposed');
    }

    public function testOnlyADifferenceBelowAnOereIsBalanced(): void
    {
        $this->assertNull(bilagOpenVoucher(array(self::line(5, '58000', '', 100.004), self::line(5, '', '1010', 100))));
        $this->assertSame(7, bilagOpenVoucher(array(self::line(7, '58000', '', 1000), self::line(7, '', '1010', 999.99)))['bilag'],
            'one øre off is unbalanced to the posting too');
    }

    public function testANegativeAmountCountsWithItsSign(): void
    {
        $this->assertNull(bilagOpenVoucher(array(self::line(6, '58000', '', -200), self::line(6, '1010', '', 200))));
    }

    public function testUnnumberedAndZeroLinesAndAnEmptyJournalOpenNothing(): void
    {
        $this->assertNull(bilagOpenVoucher(array()));
        $this->assertNull(bilagOpenVoucher(array(self::line('', '58000', '', 1000), self::line(0, '58000', '', 1000), self::line('00', '58000', '', 1000))));
        $this->assertNull(bilagOpenVoucher(array(self::line(8, '', '', 0))), 'a line without accounts has nothing to balance');
    }
}
