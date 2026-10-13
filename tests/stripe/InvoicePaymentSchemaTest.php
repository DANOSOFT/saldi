<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/InvoicePaymentSchemaTest.php --- 2026.10.12
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261012 CL/LH Provisioning parity, event-lease upgrades, and fail-closed schema tests.
// 20261012 CL/LH Repair the newest notification table and verify durable-operation columns.
// 20261012 CL/LH Verify failed-charge identity and dispatch ambiguity defaults in the shared CREATE definitions.

use PHPUnit\Framework\TestCase;
use Saldi\Stripe\InvoicePaymentSchema;

require_once __DIR__ . '/support/InvoicePaymentDatabase.php';

final class InvoicePaymentSchemaTest extends TestCase
{
    use InvoicePaymentDatabase;

    protected function setUp(): void { $this->openDatabase(); }

    public function testProvisioningEmptyTenantIsCompleteAndIdempotent(): void
    {
        $this->assertTrue(InvoicePaymentSchema::provision($this->db));
        $this->assertTrue(InvoicePaymentSchema::provision($this->db));
        InvoicePaymentSchema::assertProvisioned($this->db);
        $this->db->query("INSERT INTO stripe_invoice_payments (mode,order_id,konto_id,amount_ore,kind,status,created_at,updated_at) VALUES ('test',1,1,100,'manual','created',1,1)");
        $row = $this->db->row('SELECT * FROM stripe_invoice_payments');
        foreach (['received_at', 'failed_at', 'failed_charge', 'event_created', 'payment_method', 'card_generation', 'open_ore_at_receipt', 'hold_reason'] as $column) $this->assertNull($row[$column], $column);
        $this->assertSame(0, (int)$row['collection_hold']);
        $this->db->query("INSERT INTO stripe_invoice_operations (mode,kind,external_id,data_json,created_at) VALUES ('test','intent_create','fixture','{}',1)");
        $this->assertSame(0, (int)$this->db->row('SELECT ambiguous_dispatch FROM stripe_invoice_operations')['ambiguous_dispatch']);
        $this->db->query("INSERT INTO stripe_invoice_cards (mode,konto_id,customer_id,payment_method_id,updated_at) VALUES ('test',1,'cus_fixture','pm_fixture',1)");
        $row = $this->db->row('SELECT * FROM stripe_invoice_cards');
        $this->assertSame(1, (int)$row['generation']);
        $this->assertNull($row['terms_version']);
        $this->assertNull($row['consent_text_sha256']);
    }

    public function testMissingTableAndIndexAreRepairedEvenWhenAuditExists(): void
    {
        $this->assertTrue(InvoicePaymentSchema::provision($this->db));
        $this->db->query('DROP TABLE stripe_invoice_cards');
        $this->db->query('DROP INDEX stripe_invoice_payments_received');
        try {
            InvoicePaymentSchema::assertProvisioned($this->db);
            $this->fail('Incomplete schema must fail closed');
        } catch (Throwable $e) {
            $this->assertNotSame('', $e->getMessage());
        }
        $this->assertTrue(InvoicePaymentSchema::provision($this->db));
        InvoicePaymentSchema::assertProvisioned($this->db);
    }

    public function testProvisioningFailureIsLoggedAndLeavesConnectionUsable(): void
    {
        // A conflicting relation deterministically makes the CREATE/index fail on either driver.
        $this->db->query('CREATE TABLE stripe_invoice_payments (id INTEGER PRIMARY KEY)');
        $messages = [];
        $this->assertFalse(InvoicePaymentSchema::provision($this->db, static function ($message) use (&$messages) { $messages[] = $message; }));
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('ikke klargjort', $messages[0]);
        $this->assertFalse($this->db->inTransaction());
        $this->assertSame(1, (int)$this->db->row('SELECT 1 AS n')['n']);
        $this->db->query('DROP TABLE stripe_invoice_payments');
        $this->assertTrue(InvoicePaymentSchema::provision($this->db));
    }

    public function testExistingEventsReceiveLeaseColumnsOnceWithoutLosingRows(): void
    {
        $this->db->query('CREATE TABLE stripe_events (id INTEGER PRIMARY KEY,event_id VARCHAR(255),status VARCHAR(30))');
        $this->db->query("INSERT INTO stripe_events (id,event_id,status) VALUES (1,'evt_existing','processed')");
        InvoicePaymentSchema::upgradeEvents($this->db);
        InvoicePaymentSchema::upgradeEvents($this->db);
        $row = $this->db->row('SELECT * FROM stripe_events');
        $this->assertSame('processed', $row['status']);
        $this->assertSame('evt_existing', $row['event_id']);
        $this->assertSame(0, (int)$row['attempts']);
        foreach (['claim_token', 'claimed_at', 'event_created'] as $column) $this->assertNull($row[$column]);
    }

    public function testMissingEventTableDoesNotPoisonConnection(): void
    {
        try {
            InvoicePaymentSchema::upgradeEvents($this->db);
            $this->fail('Events must exist before upgrading');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('mangler stripe_events', $e->getMessage());
        }
        $this->assertFalse($this->db->inTransaction());
        $this->assertSame(1, (int)$this->db->row('SELECT 1 AS n')['n']);
    }

    public function testRuntimeAssertionRejectsMissingRequiredColumns(): void
    {
        $this->assertTrue(InvoicePaymentSchema::provision($this->db));
        $this->db->query('DROP TABLE stripe_invoice_cards');
        $this->db->query('CREATE TABLE stripe_invoice_cards (id INTEGER PRIMARY KEY)');
        $this->expectException(PDOException::class);
        InvoicePaymentSchema::assertProvisioned($this->db);
    }

    public function testRuntimeAssertionChecksColumnNamesContainingDigits(): void
    {
        $this->assertTrue(InvoicePaymentSchema::provision($this->db));
        $this->db->query('DROP TABLE stripe_invoice_cards');
        foreach (InvoicePaymentSchema::statements($this->db->driver()) as $sql) {
            if (str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS stripe_invoice_cards ')) {
                $this->db->query(str_replace(', consent_text_sha256 VARCHAR(64) NULL', '', $sql));
            }
        }
        $this->expectException(PDOException::class);
        InvoicePaymentSchema::assertProvisioned($this->db);
    }

    public function testProvisioningRepairsMissingOutboxAndAllowsPostedManualSettlement(): void
    {
        $this->assertTrue(InvoicePaymentSchema::provision($this->db));
        $this->db->query('DROP TABLE stripe_invoice_notifications');
        $this->assertTrue(InvoicePaymentSchema::provision($this->db));
        $this->db->query("INSERT INTO stripe_invoice_notifications (kind,dedupe_key,subject,body,created_at) VALUES ('receipt','pi1','Modtaget','Faktura 1',1)");
        $this->db->query("INSERT INTO stripe_invoice_payments (mode,order_id,konto_id,amount_ore,kind,status,settlement,created_at,updated_at) VALUES ('live',1,1,100,'manual','succeeded','posted_manual',1,1)");
        $this->assertSame('posted_manual', $this->db->row('SELECT settlement FROM stripe_invoice_payments')['settlement']);
        InvoicePaymentSchema::assertProvisioned($this->db);
        $this->db->query('SELECT request_path,request_json,idempotency_key,first_dispatch_at,ambiguous_dispatch,card_generation,payment_id,status,result_id,last_error,updated_at FROM stripe_invoice_operations LIMIT 0');
        $migration = file_get_contents(__DIR__ . '/../../includes/betweenUpdates.php');
        $this->assertStringContainsString('SELECT id FROM stripe_invoice_notifications LIMIT 0', $migration);
    }

    public function testMysqlSchemaIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('kræver PostgreSQL');
        InvoicePaymentSchema::statements('mysql');
    }
}
