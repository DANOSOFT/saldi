<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../includes/ordrefunc.php';

final class OrderDateValidationCharacterizationTest extends TestCase
{
	public function testCreditNoteReturnDateMayPrecedeCreditNoteOrderDate(): void
	{
		$this->assertFalse(delivery_date_before_order_date('DK', '2026-05-08', '2026-08-31'));
	}

	public function testCreditNoteStillRequiresReturnDate(): void
	{
		$this->assertTrue(delivery_date_before_order_date('DK', '', '2026-08-31'));
	}

	public function testSalesOrderDeliveryDateMayNotPrecedeOrderDate(): void
	{
		$this->assertTrue(delivery_date_before_order_date('DO', '2026-05-08', '2026-08-31'));
	}

	public function testSalesOrderDeliveryDateMayEqualOrderDate(): void
	{
		$this->assertFalse(delivery_date_before_order_date('DO', '2026-08-31', '2026-08-31'));
	}
}
