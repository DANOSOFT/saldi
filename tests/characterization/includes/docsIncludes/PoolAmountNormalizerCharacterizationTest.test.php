<?php
// tests/characterization/includes/docsIncludes/PoolAmountNormalizerCharacterizationTest.test.php
//
// SST-775: pins normalizePoolAmount() in includes/docsIncludes/poolAmountNormalizer.php.
//
// The function is shared by extractInvoiceHandler.php's save path, the Bilagsmatch scoring
// engine and the pool_files.norm_amount backfill, but had no test coverage. It read an
// accounting credit "(1.234,56)" as +1234.56 because only a literal '-' set the sign, so a
// credit note in the pool could only ever match a debit.
//
// The Danish/US separator handling it already got right is pinned too, so the sign fix
// cannot regress it.
//
// History:
// 20260909 CDX/MJ SST-775: created.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/includes/docsIncludes/poolAmountNormalizer.php';

final class PoolAmountNormalizerCharacterizationTest extends TestCase
{
	/** Separator handling that already worked - guard against regression. */
	public static function separatorProvider(): array
	{
		return [
			'danish, dot thousands + comma decimal' => ['1.234,56', 1234.56],
			'us, comma thousands + dot decimal'     => ['1,234.56', 1234.56],
			'plain decimal'                         => ['1234.56', 1234.56],
			'single dot, three trailing digits'     => ['1.234', 1234.0],
			'space as thousands separator'          => ['1 234,56', 1234.56],
			'repeated thousands grouping'           => ['1.234.567', 1234567.0],
			'currency prefix is ignored'            => ['DKK 1.234,56', 1234.56],
			'signed negative'                       => ['-1.234,56', -1234.56],
			'trailing minus'                        => ['1.234,56-', -1234.56],
		];
	}

	#[DataProvider('separatorProvider')]
	public function testSeparatorHandlingIsUnchanged(string $input, float $expected): void
	{
		$this->assertSame($expected, normalizePoolAmount($input));
	}

	public function testParenthesisedAmountIsACredit(): void
	{
		// Was +1234.56 / +500.0: the docblock notes parentheses are stripped, but nothing
		// treated them as a sign.
		$this->assertSame(-1234.56, normalizePoolAmount('(1.234,56)'));
		$this->assertSame(-500.0, normalizePoolAmount('(500)'));
		$this->assertSame(-1234.56, normalizePoolAmount('(DKK 1.234,56)'));
	}

	public function testParenthesisElsewhereInTheStringIsNotASign(): void
	{
		// The sign test is anchored to the whole value, so a parenthetical note stays positive.
		// The magnitude is wrong for a separate, pre-existing reason - see the next test.
		$this->assertGreaterThan(0, normalizePoolAmount('1.234,56 (faktura 123)'));
	}

	public function testTrailingDigitsOutsideTheAmountBleedIntoIt(): void
	{
		// Pinned as-is, NOT as desired behaviour. Non-numeric characters are stripped but the
		// digits they surrounded are kept and concatenated, so any trailing text containing a
		// number corrupts the amount: "1.234,56 (faktura 123)" becomes 1234.56123, rounded to
		// 1234.561 rather than 1234.56.
		//
		// This is an amount extraction defect in its own right and in SST-775's scope, but
		// fixing it means changing how this shared function tokenizes - it also feeds the
		// Bilagsmatch scoring engine and the pool_files.norm_amount backfill - so it is left
		// for a separate change rather than stacked onto the sign fix.
		// (round() to 3 decimals is what stops these being even further out.)
		$this->assertSame(1234.561, normalizePoolAmount('1.234,56 (faktura 123)'));
		$this->assertSame(1234.562, normalizePoolAmount('1.234,56 DKK 2025'));
	}

	public function testAlreadyNegativeParenthesisedAmountStaysNegativeOnce(): void
	{
		$this->assertSame(-1234.56, normalizePoolAmount('(-1.234,56)'));
	}

	public function testEmptyAndUnparseableInputReturnNull(): void
	{
		$this->assertNull(normalizePoolAmount(null));
		$this->assertNull(normalizePoolAmount(''));
		$this->assertNull(normalizePoolAmount('   '));
		$this->assertNull(normalizePoolAmount('ingen beløb'));
	}
}
