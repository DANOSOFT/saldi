<?php
// 20261027 CL/LH MB-78: The quantity-discount rows on the item card render whole numbers without a PHP notice.
//                array_pad(..., null) left the missing decimal part null and strlen() logged "Passing null to parameter #1" on PHP 8.1+ (CodeRabbit on PR #838).
// 20261109 CDX/NTR Verify explicit renderer inputs, unchanged caller values, repeatable VAT rendering and the percentage-offer branch.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * support/render_qty_discounts.php calls renderQtyDiscounts() with explicit inputs in a child
 * process with every notice recorded, then checks that rendering is repeatable and leaves inputs alone.
 * No database: the runner answers findtekst() with the text id.
 */
final class QtyDiscountDecimalsTest extends TestCase
{
	/**
	 * @param array<string, mixed> $inputs
	 * @return array{
	 *   html: string,
	 *   errors: list<string>,
	 *   includeOutput: string,
	 *   inputsUnchanged: bool,
	 *   repeatHtml: string
	 * }
	 */
	private function render(array $inputs): array
	{
		$inputs += array(
			'm_type' => 'amount', 'incl_moms' => 0, 'rabatgruppe' => 0,
			'special_price' => 0, 'specialType' => '',
		);
		$process = proc_open(
			array(PHP_BINARY, '-d', 'display_errors=stderr', __DIR__ . '/support/render_qty_discounts.php'),
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes
		);
		self::assertIsResource($process);
		fwrite($pipes[0], (string) json_encode($inputs));
		fclose($pipes[0]);
		$stdout = (string) stream_get_contents($pipes[1]);
		$stderr = (string) stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exitCode = proc_close($process);
		self::assertSame(0, $exitCode, "runner failed: $stderr$stdout");

		$result = json_decode($stdout, true);
		self::assertIsArray($result, "runner output is not JSON: $stdout");
		self::assertSame('', $result['includeOutput'], 'Loading the renderer must not render anything.');
		self::assertTrue($result['inputsUnchanged'], 'Rendering must not change the caller\'s input values.');
		self::assertSame($result['html'], $result['repeatHtml'], 'Repeated rendering must not apply VAT twice.');
		return $result;
	}

	public function testWholeNumberDiscountsRenderWithoutNotices(): void
	{
		$result = $this->render(array('m_antal_array' => array('5'), 'm_rabat_array' => array('10')));

		self::assertSame(array(), $result['errors']);
		self::assertStringContainsString('name="m_rabat_array[0]" value=10>', $result['html']);
		self::assertStringContainsString('name="m_antal_array[0]" value="5">', $result['html']);
		// The empty row for a new discount (whole-number '0' defaults) renders without decimals too.
		self::assertStringContainsString("name='m_rabat_array[1]'value='0'", $result['html']);
		self::assertStringContainsString("name='m_antal_array[1]'value='0'", $result['html']);
	}

	public function testDecimalDiscountsKeepTheirDecimals(): void
	{
		$result = $this->render(array('m_antal_array' => array('2.5'), 'm_rabat_array' => array('12.75')));

		self::assertSame(array(), $result['errors']);
		self::assertStringContainsString('name="m_rabat_array[0]" value=12,75>', $result['html']);
		self::assertStringContainsString('name="m_antal_array[0]" value="2,5">', $result['html']);
	}

	public function testNumericStringsDiscardInsignificantZeros(): void
	{
		$result = $this->render(array('m_antal_array' => array('002.5000'), 'm_rabat_array' => array('012.7500')));

		self::assertSame(array(), $result['errors']);
		self::assertStringContainsString('name="m_rabat_array[0]" value=12,75>', $result['html']);
		self::assertStringContainsString('name="m_antal_array[0]" value="2,5">', $result['html']);
	}

	public function testAmountDiscountIncludesVatWithoutLosingDecimals(): void
	{
		$result = $this->render(array('m_antal_array' => array('2.5'), 'm_rabat_array' => array('12.5'), 'incl_moms' => 25));

		self::assertSame(array(), $result['errors']);
		self::assertStringContainsString('name="m_rabat_array[0]" value=15,625>', $result['html']);
	}

	public function testPercentageDiscountIsUnchangedByVat(): void
	{
		$result = $this->render(array('m_antal_array' => array('2.5'), 'm_rabat_array' => array('12.5'), 'incl_moms' => 25, 'm_type' => 'percent'));

		self::assertSame(array(), $result['errors']);
		self::assertStringContainsString('name="m_rabat_array[0]" value=12,5>', $result['html']);
	}

	public function testGroupDiscountIsReadonlyAndHasNoEmptyRow(): void
	{
		$result = $this->render(array('m_antal_array' => array('5'), 'm_rabat_array' => array('10'), 'rabatgruppe' => 1));

		self::assertSame(array(), $result['errors']);
		self::assertStringContainsString('readonly="readonly"', $result['html']);
		self::assertStringNotContainsString('m_rabat_array[1]', $result['html']);
	}

	public function testEmptyDiscountListRendersZeroDefaultsWithoutNotices(): void
	{
		$result = $this->render(array('m_antal_array' => array(), 'm_rabat_array' => array()));

		self::assertSame(array(), $result['errors']);
		self::assertStringContainsString("name='m_rabat_array[0]'value='0'", $result['html']);
		self::assertStringContainsString("name='m_antal_array[0]'value='0'", $result['html']);
	}

	public function testPercentageOfferReplacesQuantityDiscountFields(): void
	{
		$result = $this->render(array('m_antal_array' => array('5'), 'm_rabat_array' => array('10'), 'special_price' => 20, 'specialType' => 'percent'));

		self::assertSame(array(), $result['errors']);
		self::assertStringContainsString('tekst-2043', $result['html']);
		self::assertStringNotContainsString('name="m_rabat_array', $result['html']);
		self::assertStringNotContainsString('NAME=m_type', $result['html']);
	}
}
