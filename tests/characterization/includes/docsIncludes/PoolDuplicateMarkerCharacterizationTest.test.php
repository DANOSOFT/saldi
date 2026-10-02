<?php
// tests/characterization/includes/docsIncludes/PoolDuplicateMarkerCharacterizationTest.test.php
//
// MB-42: pins poolMarkDuplicates() in includes/docsIncludes/poolDuplicateMarker.php.
//
// The customer's pool held the same bilag under two or three filenames - the mail client forwards it,
// the uploader sanitises the name, the attachment endpoint adds a random suffix - and the accountant
// had no way to see that two rows were one invoice. The content hash (pool_files.content_sha256)
// stops new ones; this grouping is what shows the rows already in the list.
//
// What matters:
//   - all three of fakturanr, amount and date must match, or every invoice of the same amount on the
//     same day would be flagged
//   - a group of three tells each member about the other two
//   - file_date is a varchar in two shapes, so the same day in both shapes groups together
//
// History:
// 20260925 LOE MB-42: created.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/includes/docsIncludes/poolDuplicateMarker.php';

final class PoolDuplicateMarkerCharacterizationTest extends TestCase
{
	private function row($filename, $invoice, $amount, $date)
	{
		return array(
			'filename'      => $filename,
			'invoiceNumber' => $invoice,
			'amount'        => $amount,
			'date'          => $date,
		);
	}

	public function testRowsWithTheSameInvoiceNumberAmountAndDateMarkEachOther(): void
	{
		$rows = poolMarkDuplicates(array(
			$this->row('Faktura2138433.pdf', '2138433', '245,00', '2026-07-13'),
			$this->row('Faktura_2138433.pdf', '2138433', '245,00', '2026-07-13'),
		));

		$this->assertSame(array('Faktura_2138433.pdf'), $rows[0]['duplicateOf']);
		$this->assertSame(array('Faktura2138433.pdf'), $rows[1]['duplicateOf']);
	}

	public function testThreeCopiesTellEachMemberAboutTheOtherTwo(): void
	{
		$rows = poolMarkDuplicates(array(
			$this->row('Faktura 2138433.pdf', '2138433', '245,00', '2026-07-13'),
			$this->row('Faktura2138433.pdf', '2138433', '245,00', '2026-07-13'),
			$this->row('Faktura_2138433.pdf', '2138433', '245,00', '2026-07-13'),
		));

		$this->assertSame(array('Faktura2138433.pdf', 'Faktura_2138433.pdf'), $rows[0]['duplicateOf']);
		$this->assertSame(array('Faktura 2138433.pdf', 'Faktura_2138433.pdf'), $rows[1]['duplicateOf']);
		$this->assertSame(array('Faktura 2138433.pdf', 'Faktura2138433.pdf'), $rows[2]['duplicateOf']);
	}

	public function testADifferentAmountOrDateIsNotADuplicate(): void
	{
		$rows = poolMarkDuplicates(array(
			$this->row('a.pdf', '2138433', '245,00', '2026-07-13'),
			$this->row('b.pdf', '2138433', '245,50', '2026-07-13'),
			$this->row('c.pdf', '2138433', '245,00', '2026-07-14'),
			$this->row('d.pdf', '2138434', '245,00', '2026-07-13'),
		));

		foreach ($rows as $row) {
			$this->assertArrayNotHasKey('duplicateOf', $row, $row['filename'] . ' must not be marked');
		}
	}

	public function testTheSameDayInBothVarcharShapesStillGroups(): void
	{
		// file_date holds 'Y-m-d H:i:s' from the upload paths and 'Y-m-d' from the scan path.
		$rows = poolMarkDuplicates(array(
			$this->row('uploaded.pdf', '2138433', '245,00', '2026-07-13 05:41:38'),
			$this->row('scanned.pdf', '2138433', '245,00', '2026-07-13'),
		));

		$this->assertSame(array('scanned.pdf'), $rows[0]['duplicateOf']);
		$this->assertSame(array('uploaded.pdf'), $rows[1]['duplicateOf']);
	}

	public function testARowMissingAnyOfTheThreeFieldsIsNeverMarked(): void
	{
		$rows = poolMarkDuplicates(array(
			$this->row('no-invoice.pdf', '', '245,00', '2026-07-13'),
			$this->row('no-invoice-either.pdf', '', '245,00', '2026-07-13'),
			$this->row('no-amount.pdf', '2138433', '', '2026-07-13'),
			$this->row('no-date.pdf', '2138433', '245,00', ''),
			$this->row('complete.pdf', '2138433', '245,00', '2026-07-13'),
		));

		foreach ($rows as $row) {
			$this->assertArrayNotHasKey('duplicateOf', $row, $row['filename'] . ' must not be marked');
		}
	}

	public function testWhitespaceAroundTheFieldsIsIgnored(): void
	{
		$rows = poolMarkDuplicates(array(
			$this->row('a.pdf', ' 2138433 ', '245,00', ' 2026-07-13 '),
			$this->row('b.pdf', '2138433', '245,00', '2026-07-13'),
		));

		$this->assertSame(array('b.pdf'), $rows[0]['duplicateOf']);
	}

	public function testAnEmptyPoolIsLeftAlone(): void
	{
		$this->assertSame(array(), poolMarkDuplicates(array()));
	}
}
