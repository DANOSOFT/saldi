<?php
// tests/includes/salesPostingVat.test.php
//
// MB-80: the grouping and VAT allocation in includes/salesPostingVat.php, without a database.
// The cases are Lui's from tests/test_sales_posting_precision.php in #657 (62bdc0d1); the posted
// rows themselves are pinned in tests/characterization/includes/ordrefunc/SalesPostingPrecisionTest.test.php.
//
// History:
// 20261125 CL/MMR MB-80: created.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/salesPostingVat.php';

final class salesPostingVat extends TestCase
{
    /** @return array<string, mixed> An ordrelinjer row as bogfor_nu() reads it. */
    private static function line(int $account, int $vatAccount, float $price, float $rate = 25, float $qty = 1, array $extra = []): array
    {
        return array_merge([
            'bogf_konto' => (string)$account, 'vat_account' => (string)$vatAccount, 'rabatart' => '',
            'pris' => $price, 'antal' => $qty, 'rabat' => 0, 'procent' => '100',
            'momssats' => $rate, 'momsfri' => '',
        ], $extra);
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $linesPerProject 1-based projects.
     * @return array<int, array<int, float>>|null
     */
    private static function allocate(array $linesPerProject, float $invoiceVat, float $rate): ?array
    {
        $groups = [];
        foreach ($linesPerProject as $p => $lines) {
            $groups[$p] = salesPostingGroupLines($lines);
        }
        return salesPostingVatAllocations($groups, $invoiceVat, $rate);
    }

    /** @return array<string, array{float, float, float, float, float}> net, quantity, VAT rate, expected net, expected VAT */
    public static function singleLineInvoices(): array
    {
        return [
            'MB-80 acceptance' => [999.99, 3, 25, 7449.93, 1862.50],
            'credit note' => [-999.99, 3, 25, -7449.93, -1862.50],
            'round amount' => [1000, 1, 25, 7450.00, 1862.50],
            'no VAT' => [999.99, 3, 0, 7449.93, 0.00],
            'VAT below half an øre' => [0.01, 1, 25, 0.07, 0.00],
            'VAT just above half an øre' => [0.03, 1, 25, 0.22, 0.07],
        ];
    }

    #[DataProvider('singleLineInvoices')]
    public function testSingleLineEurInvoicePostsTheConvertedInvoiceVat(float $net, float $qty, float $rate, float $expectedNet, float $expectedVat): void
    {
        $invoiceVat = afrund($net * $rate / 100, 2);
        $groups = salesPostingGroupLines([self::line(1010, 7680, $net / $qty, $rate, $qty)]);
        $allocation = salesPostingVatAllocations([1 => $groups], $invoiceVat, 745);

        self::assertCount(1, $groups);
        self::assertEqualsWithDelta($expectedNet, salesPostingAmount($groups[1]['pris'], 745), 0.00001);
        self::assertEqualsWithDelta($expectedVat, $allocation[1][1], 0.00001);
    }

    public function testTheOldThreeDecimalVatIsWhatMadeTheAcceptanceInvoiceOreShort(): void
    {
        $groups = salesPostingGroupLines([self::line(1010, 7680, 333.33, 25, 3)]);

        self::assertEqualsWithDelta(249.998, $groups[1]['linjemoms'], 0.000001);
        self::assertEqualsWithDelta(1862.49, salesPostingAmount($groups[1]['linjemoms'], 745), 0.00001);
    }

    /** (a) Until MB-80 the second line was dropped because its revenue account was already grouped. */
    public function testLinesOnTheSameRevenueAccountWithAnotherVatAccountGetTheirOwnGroup(): void
    {
        $groups = salesPostingGroupLines([self::line(1010, 7680, 1000.02), self::line(1010, 7700, 1000.02, 10)]);

        self::assertCount(2, $groups);
        self::assertSame(7680, $groups[1]['vat_account']);
        self::assertSame(7700, $groups[2]['vat_account']);
        self::assertEqualsWithDelta(1000.02, $groups[2]['pris'], 0.000001);
    }

    public function testLinesOnTheSameAccountsAreSummedAsBefore(): void
    {
        $groups = salesPostingGroupLines([
            self::line(1010, 7680, 199.95, 25, 3, ['rabat' => 10]),
            self::line(1010, 7680, 49.99, 25, 2, ['rabat' => 5, 'rabatart' => 'amount']),
            self::line(1010, 7680, -25.00),
            self::line(1010, 7680, 80.00, 25, 1, ['momsfri' => 'on']),
        ]);

        self::assertCount(1, $groups);
        self::assertEqualsWithDelta(684.845, $groups[1]['pris'], 0.000001);
        self::assertEqualsWithDelta(151.211, $groups[1]['linjemoms'], 0.000001);
        self::assertSame(3, $groups[1]['vatLines']);
    }

    /** @return array<string, array{array<int, array<int, array<string, mixed>>>, float, array<int, float>}> */
    public static function multiGroupInvoices(): array
    {
        $exemptCredit = self::line(1200, 7680, -100, 25, 1, ['momsfri' => 'on']);
        return [
            '(b) net 0 with VAT' => [[1 => [self::line(1200, 7680, 100), $exemptCredit]], 25.00, [7680 => 186.25]],
            'two revenue accounts, one VAT account' => [[1 => [self::line(1200, 7680, 1000.02), self::line(1210, 7680, 1000.02)]], 500.01, [7680 => 3725.07]],
            'the same as a credit note' => [[1 => [self::line(1200, 7680, -1000.02), self::line(1210, 7680, -1000.02)]], -500.01, [7680 => -3725.07]],
            '(a) one revenue account, two VAT accounts' => [[1 => [self::line(1200, 7680, 1000.02), self::line(1200, 7700, 1000.02, 10)]], 350.01, [7680 => 1862.57, 7700 => 745.00]],
            'the same as a credit note ' => [[1 => [self::line(1200, 7680, -1000.02), self::line(1200, 7700, -1000.02, 10)]], -350.01, [7680 => -1862.57, 7700 => -745.00]],
            'two rates on one VAT account' => [[1 => [self::line(1200, 7680, 1000.02), self::line(1210, 7680, 1000.02, 10)]], 350.01, [7680 => 2607.57]],
            'two projects' => [[1 => [self::line(1200, 7680, 1000.02)], 2 => [self::line(1200, 7680, 1000.02)]], 500.01, [7680 => 3725.07]],
        ];
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $linesPerProject
     * @param array<int, float> $expectedPerVatAccount
     */
    #[DataProvider('multiGroupInvoices')]
    public function testInvoiceVatIsSplitWithoutExtraOre(array $linesPerProject, float $invoiceVat, array $expectedPerVatAccount): void
    {
        $allocation = self::allocate($linesPerProject, $invoiceVat, 745);
        self::assertNotNull($allocation);

        $perVatAccount = [];
        foreach ($linesPerProject as $p => $lines) {
            foreach (salesPostingGroupLines($lines) as $y => $group) {
                $perVatAccount[$group['vat_account']] = ($perVatAccount[$group['vat_account']] ?? 0) + $allocation[$p][$y];
            }
        }
        foreach ($expectedPerVatAccount as $vatAccount => $expected) {
            self::assertEqualsWithDelta($expected, $perVatAccount[$vatAccount] ?? 0, 0.00001, "VAT account $vatAccount");
        }
        self::assertEqualsWithDelta(afrund($invoiceVat * 7.45, 2), array_sum($perVatAccount), 0.00001, 'total is the converted invoice VAT');
    }

    /** #657 refused such an invoice; here bogfor_nu() posts the lines' VAT as before and the pre-flight check decides. */
    public function testAMaterialVatDifferenceIsNotRedistributed(): void
    {
        $projects = [1 => [self::line(1200, 7680, 1000.02)], 2 => [self::line(1200, 7680, 1000.02)]];

        self::assertNull(self::allocate($projects, 510, 745));
        self::assertNull(self::allocate([1 => [self::line(1200, 0, 100, 0)]], 25, 745), 'invoice VAT without any VAT on the lines');
        self::assertSame([1 => [1 => 0]], self::allocate([1 => [self::line(1200, 0, 100, 0)]], 0, 745), 'no VAT at all');
    }

    public function testAllocateCentsKeepsTheTotal(): void
    {
        self::assertSame(['a' => 0.34, 'b' => 0.33, 'c' => 0.33], salesPostingAllocateCents(['a' => 1 / 3, 'b' => 1 / 3, 'c' => 1 / 3], 1.00));
        self::assertSame(['a' => -0.34, 'b' => -0.33, 'c' => -0.33], salesPostingAllocateCents(['a' => -1 / 3, 'b' => -1 / 3, 'c' => -1 / 3], -1.00));
        self::assertSame([], salesPostingAllocateCents([], 0));
    }
}
