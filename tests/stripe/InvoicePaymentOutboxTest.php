<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/InvoicePaymentOutboxTest.php --- 2026.10.12
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
// 20261012 CL/LH Outbox dedupe, transaction rollback, false/throw retries and post-commit delivery.

use PHPUnit\Framework\TestCase;
use Saldi\Stripe\InvoicePaymentOutbox;
use Saldi\Stripe\InvoicePaymentSchema;

require_once __DIR__ . '/support/InvoicePaymentDatabase.php';
require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentOutbox.php';

final class InvoicePaymentOutboxTest extends TestCase
{
    use InvoicePaymentDatabase;

    protected function setUp(): void
    {
        $this->openDatabase();
        foreach (InvoicePaymentSchema::statements($this->db->driver()) as $sql) $this->db->query($sql);
    }

    public function testNotificationCommitsWithBusinessTransactionAndDeduplicates(): void
    {
        $sent = [];
        $queue = new InvoicePaymentOutbox($this->db, function ($subject, $body, $key, $throttle) use (&$sent) {
            $this->assertFalse($this->db->inTransaction());
            $this->assertSame('', $key, 'The queue owns dedupe, not a filesystem mail throttle');
            $sent[] = [$subject, $body];
            return true;
        });
        $this->db->transaction(function () use ($queue) {
            $queue->enqueue('manual_receipt', 'receipt-pi1', 'Betaling modtaget', 'Faktura 1001');
            $queue->enqueue('manual_receipt', 'receipt-pi1', 'Betaling modtaget', 'Duplicate');
            $this->assertSame(0, $queue->flush());
        });
        $this->assertSame(1, $queue->flush());
        $this->assertSame(0, $queue->flush());
        $this->assertSame([['Betaling modtaget', 'Faktura 1001']], $sent);
        $row = $this->db->row('SELECT * FROM stripe_invoice_notifications');
        $this->assertSame(1, (int)$row['attempts']);
        $this->assertNotNull($row['sent_at']);
    }

    public function testRollbackRemovesNotificationWithBusinessFact(): void
    {
        $queue = new InvoicePaymentOutbox($this->db, static function () { throw new RuntimeException('Must not send'); });
        try {
            $this->db->transaction(function () use ($queue) {
                $queue->enqueue('refund', 'refund-event', 'Refusion', 'Faktura 1001');
                throw new RuntimeException('Business transaction failed');
            });
        } catch (RuntimeException $e) { }
        $this->assertSame(0, $queue->flush());
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_invoice_notifications')['n']);
    }

    public function testFalseAndThrownMailFailuresRetryWithoutReplayingBusiness(): void
    {
        $queue = new InvoicePaymentOutbox($this->db, static fn() => false);
        $queue->enqueue('manual_receipt', 'receipt-pi1', 'Betaling modtaget', 'Faktura 1001');
        $this->assertSame(0, $queue->flush());
        $row = $this->db->row('SELECT * FROM stripe_invoice_notifications');
        $this->assertNull($row['sent_at']);
        $this->assertSame(1, (int)$row['attempts']);
        $this->assertSame('Mail kunne ikke sendes', $row['last_error']);
        $queue = new InvoicePaymentOutbox($this->db, static function () { throw new RuntimeException('SMTP unavailable'); });
        $this->assertSame(0, $queue->flush());
        $this->assertSame('SMTP unavailable', $this->db->row('SELECT last_error FROM stripe_invoice_notifications')['last_error']);
        $sent = 0;
        $queue = new InvoicePaymentOutbox($this->db, static function () use (&$sent) { $sent++; return true; });
        $this->assertSame(1, $queue->flush());
        $this->assertSame(0, $queue->flush());
        $this->assertSame(1, $sent);
        $row = $this->db->row('SELECT * FROM stripe_invoice_notifications');
        $this->assertSame(3, (int)$row['attempts']);
        $this->assertNull($row['last_error']);
        $this->assertSame(1, (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_invoice_notifications')['n']);
    }
}
