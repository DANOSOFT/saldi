<?php
// tests/einvoice/EinvoiceAllowanceSplitTest.php
//
// SST-746: order-level discounts stored as negative-price order lines must leave
// invoiceLines and become document-level allowances (Peppol BR-27 forbids a
// negative item net price). The fixture is the real rejected invoice from the
// ticket: MEDSHOP faktura 236656 / ordre 159417.
//
// History:
// 20260828 CL SST-746: created with the splitter (skeleton PR - splitter not yet
//             wired into debitor/api.php sendInvoice()).

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/einvoiceAllowance.php';

final class EinvoiceAllowanceSplitTest extends TestCase
{
	/** Lines of faktura 236656 (ordre 159417) as sendInvoice() builds them today. */
	private function faktura236656Lines(): array
	{
		return [
			['id' => '100', 'lineAmount' => 17196.00, 'price' => 17196.00, 'description' => 'EKG SE-1200 EKG 12-kanal', 'vatPercent' => 25],
			['id' => '200', 'lineAmount' => 0.00,     'price' => 0.00,     'description' => 'SN: 460016-M25201720003', 'vatPercent' => 0],
			['id' => '300', 'lineAmount' => -600.00,  'price' => -600.00,  'description' => 'Rabat', 'vatPercent' => 0],
			['id' => '400', 'lineAmount' => 49.00,    'price' => 49.00,    'description' => 'EKG-papir - EDAN-1200', 'vatPercent' => 25],
		];
	}

	public function testDiscountLineBecomesPositiveAllowance(): void
	{
		[$lines, $allowances] = einvoice_split_allowances($this->faktura236656Lines(), 25);

		$this->assertCount(3, $lines, 'the three non-negative lines stay invoice lines');
		$this->assertCount(1, $allowances);

		$a = $allowances[0];
		$this->assertFalse($a['isCharge'], 'a discount is an allowance, not a charge');
		$this->assertSame('95', $a['reasonCode'], 'UNCL5189 code 95 = Discount');
		$this->assertSame(600.00, $a['amount'], 'amount is POSITIVE (BR-27)');
		$this->assertSame('Rabat', $a['reason']);
		$this->assertSame(25.0, $a['vatPercent'],
			'allowance carries the GOODS VAT rate, not the discount line\'s momssats=0');
	}

	public function testTotalsStillReconcile(): void
	{
		[$lines, $allowances] = einvoice_split_allowances($this->faktura236656Lines(), 25);

		$lineSum = round(array_sum(array_column($lines, 'lineAmount')), 2);
		$allowanceSum = round(array_sum(array_column($allowances, 'amount')), 2);

		$this->assertSame(17245.00, $lineSum, '17196 + 0 + 49');
		// Header of ordre 159417: sum = 16645.00, moms = 4161.25 (25% of the discounted base).
		$this->assertSame(16645.00, round($lineSum - $allowanceSum, 2), 'matches ordrer.sum');
		$this->assertSame(4161.25, round(($lineSum - $allowanceSum) * 0.25, 2), 'matches ordrer.moms');
	}

	public function testInvoiceWithoutDiscountIsUntouched(): void
	{
		$input = [
			['id' => '100', 'lineAmount' => 100.00, 'description' => 'Vare A', 'vatPercent' => 25],
			['id' => '200', 'lineAmount' => 0.00,   'description' => 'Tekstlinje', 'vatPercent' => 0],
		];
		[$lines, $allowances] = einvoice_split_allowances($input, 25);
		$this->assertSame($input, $lines, 'discount-free payloads must be byte-identical');
		$this->assertSame([], $allowances);
	}

	public function testCreditNoteRebateStaysAllowanceWithOriginalSign(): void
	{
		// The credit-note branch in sendInvoice() abs()'es prices BEFORE lines are
		// built, which turns a -600 rebate into +600 and over-credits the customer.
		// The splitter must therefore receive lines with their ORIGINAL sign; given
		// that, a rebate on a credit note is still an allowance, never a charge.
		$creditLines = [
			['id' => '100', 'lineAmount' => 17196.00, 'description' => 'EKG SE-1200 EKG 12-kanal', 'vatPercent' => 25],
			['id' => '300', 'lineAmount' => -600.00,  'description' => 'Rabat', 'vatPercent' => 0],
		];
		[$lines, $allowances] = einvoice_split_allowances($creditLines, 25);
		$this->assertCount(1, $lines);
		$this->assertCount(1, $allowances);
		$this->assertFalse($allowances[0]['isCharge']);
		$this->assertSame(600.00, $allowances[0]['amount']);
	}
}
