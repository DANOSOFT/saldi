<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/StripeEventLedgerTest.php --- 2026.10.12
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
// 20261012 CL/LH Cover first delivery, leases, retry recovery, stale owners and operator replay.

use PHPUnit\Framework\TestCase;
use Saldi\Stripe\StripeEventLedger;

require_once __DIR__ . '/support/InvoicePaymentDatabase.php';
require_once __DIR__ . '/../../includes/stripeIncludes/StripeEventLedger.php';

final class StripeEventLedgerTest extends TestCase
{
    use InvoicePaymentDatabase;
    private StripeEventLedger $ledger;
    private int $now = 1791360000;

    protected function setUp(): void
    {
        $this->openDatabase();
        $id = $this->db->driver() === 'pgsql' ? 'BIGSERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $this->db->query("CREATE TABLE stripe_events (id $id,event_id VARCHAR(255) UNIQUE NOT NULL,event_type VARCHAR(100),payload TEXT,status VARCHAR(30) NOT NULL DEFAULT 'received',saldi_order_id INTEGER,invoice_number VARCHAR(30),error TEXT,received_at TIMESTAMP,processed_at TIMESTAMP,claim_token VARCHAR(64),claimed_at TIMESTAMP,attempts INTEGER NOT NULL DEFAULT 0,event_created BIGINT)");
        $this->ledger = new StripeEventLedger($this->db->pdo, fn() => $this->now);
    }

    /** @return array{action:string,id?:int,token?:string} */
    private function receive(): array
    {
        return $this->ledger->receive('evt_fixture', 'invoice.paid', base64_encode('{}'), $this->now - 60);
    }

    public function testFirstDeliveryProcessesAndStoresVerifiedPayloadAndEventTime(): void
    {
        $claim = $this->receive();
        $this->assertSame('process', $claim['action']);
        $this->assertGreaterThan(0, $claim['id']);
        $this->assertSame(64, strlen($claim['token']));
        $row = $this->db->row('SELECT * FROM stripe_events');
        $this->assertSame(base64_encode('{}'), $row['payload']);
        $this->assertSame($this->now - 60, (int)$row['event_created']);
        $this->assertSame($claim['token'], $row['claim_token']);
    }

    public function testEveryTerminalStatusReturnsDuplicate(): void
    {
        foreach (StripeEventLedger::TERMINAL as $status) {
            $id = 'evt_' . $status;
            $claim = $this->ledger->receive($id, 'invoice.paid', '', $this->now);
            $this->ledger->finish($claim['id'], $claim['token'], $status, ['saldi_order_id' => 10, 'invoice_number' => '1001']);
            $this->assertSame(['action' => 'duplicate'], $this->ledger->receive($id, 'invoice.paid', '', $this->now));
            $row = $this->db->row('SELECT * FROM stripe_events WHERE id=?', [$claim['id']]);
            $this->assertNull($row['claim_token']);
            $this->assertSame(10, (int)$row['saldi_order_id']);
            $this->assertSame('1001', $row['invoice_number']);
        }
    }

    public function testFreshForeignLeaseReturnsBusy(): void
    {
        $claim = $this->receive();
        $other = new StripeEventLedger($this->db->pdo, fn() => $this->now);
        $this->assertSame(['action' => 'busy'], $other->receive('evt_fixture', 'invoice.paid', '', $this->now));
        $this->assertSame($claim['token'], $this->db->row('SELECT claim_token FROM stripe_events')['claim_token']);
    }

    public function testExpiredLeaseIsReclaimed(): void
    {
        $first = $this->receive();
        $this->now += StripeEventLedger::LEASE_SECONDS + 1;
        $next = $this->receive();
        $this->assertSame('process', $next['action']);
        $this->assertSame($first['id'], $next['id']);
        $this->assertNotSame($first['token'], $next['token']);
    }

    public function testRepeatedFailureImmediatelyReprocessesAndCountsAttempts(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $claim = $this->receive();
            $this->assertSame('process', $claim['action']);
            $this->ledger->fail($claim['id'], $claim['token'], 'unmapped_customer');
            $row = $this->db->row('SELECT * FROM stripe_events');
            $this->assertSame('retry', $row['status']);
            $this->assertSame($i, (int)$row['attempts']);
            $this->assertNull($row['claim_token']);
            $this->assertNull($row['processed_at']);
        }
        $claim = $this->receive();
        $this->ledger->finish($claim['id'], $claim['token'], 'recorded');
        $this->assertSame(['action' => 'duplicate'], $this->receive());
    }

    public function testLegacyRetryingAndFailedRowsRecover(): void
    {
        foreach (['retrying', 'failed', 'received', 'retry'] as $status) {
            $this->db->query('INSERT INTO stripe_events (event_id,status,error) VALUES (?,?,?)', ['evt_' . $status, $status, 'legacy error']);
            $claim = $this->ledger->receive('evt_' . $status, 'invoice.paid', '', $this->now);
            $this->assertSame('process', $claim['action']);
            $this->assertSame('received', $this->db->row('SELECT status FROM stripe_events WHERE id=?', [$claim['id']])['status']);
        }
    }

    public function testStaleFinishAndFailCannotOverwriteTheNewOwner(): void
    {
        $first = $this->receive();
        $this->now += StripeEventLedger::LEASE_SECONDS + 1;
        $next = $this->receive();
        $before = $this->db->row('SELECT * FROM stripe_events');
        $this->assertFalse($this->ledger->finish($first['id'], $first['token'], 'processed', ['error' => 'stale']));
        $this->ledger->fail($first['id'], $first['token'], 'stale');
        $this->assertSame($before, $this->db->row('SELECT * FROM stripe_events'));
        $this->ledger->finish($next['id'], $next['token'], 'recorded');
        $this->assertSame('recorded', $this->db->row('SELECT status FROM stripe_events')['status']);
    }

    public function testOperatorRequeueClearsClaimAndRequiresForceForTerminalRows(): void
    {
        $first = $this->receive();
        $result = $this->ledger->requeue('evt_fixture');
        $this->assertSame('received', $result['previousStatus']);
        $next = $this->receive();
        $this->ledger->finish($next['id'], $next['token'], 'recorded');
        try {
            $this->ledger->requeue('evt_fixture');
            $this->fail('A terminal event needs explicit --force');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('--force', $e->getMessage());
        }
        $this->assertSame('recorded', $this->ledger->requeue('evt_fixture', true)['previousStatus']);
        $this->assertSame('process', $this->receive()['action']);
        $this->ledger->finish($first['id'], $first['token'], 'processed');
        $this->assertSame('received', $this->db->row('SELECT status FROM stripe_events')['status']);
    }

    public function testUnknownOperatorEventIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->ledger->requeue('evt_missing');
    }
}
