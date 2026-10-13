<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/InvoicePaymentStaffActionsTest.php --- 2026.10.12
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
// 20261012 CL/LH Reject forged and read-only debtor payment POSTs before constructing services or mutating consent/holds.

use PHPUnit\Framework\TestCase;
use Saldi\Stripe\InvoicePaymentLedger;
use Saldi\Stripe\InvoicePaymentService;
use Saldi\Stripe\InvoicePaymentSchema;

require_once __DIR__ . '/support/InvoicePaymentDatabase.php';
require_once __DIR__ . '/../../includes/stripeIncludes/invoicePaymentStaffActions.php';
require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentLedger.php';
require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentService.php';

final class InvoicePaymentStaffActionsTest extends TestCase
{
    use InvoicePaymentDatabase;
    private int $constructions = 0;
    private array $stripeCalls = [];
    private string $csrf;
    private int $payment;

    protected function setUp(): void
    {
        $this->openDatabase();
        foreach (InvoicePaymentSchema::statements($this->db->driver()) as $sql) $this->db->query($sql);
        $this->db->query('CREATE TABLE settings (var_grp TEXT,var_name TEXT,var_value TEXT)');
        $this->db->query("INSERT INTO settings VALUES ('stripe','mode','test')");
        $this->db->query("INSERT INTO stripe_invoice_cards (mode,konto_id,customer_id,payment_method_id,autopay,consent_at,updated_at) VALUES ('test',1,'cus_staff','pm_staff',1,1,1)");
        $this->payment = $this->db->insert("INSERT INTO stripe_invoice_payments (mode,order_id,konto_id,payment_intent_id,amount_ore,kind,status,collection_hold,hold_reason,created_at,updated_at) VALUES ('test',10,1,'pi_staff',10000,'manual','succeeded',1,'refund',1,1)", []);
        $this->csrf = bin2hex(random_bytes(32));
    }

    public function service(): InvoicePaymentService
    {
        $this->constructions++;
        return new InvoicePaymentService($this->db, function ($method, $path, $params, $options) {
            $this->stripeCalls[] = [$method, $path];
            return ['ok' => true, 'body' => ['id' => 'pm_staff']];
        }, new InvoicePaymentLedger($this->db), static fn() => true, 'saldi_test');
    }

    private function assertUntouched(): void
    {
        $card = $this->db->row('SELECT * FROM stripe_invoice_cards');
        $this->assertSame(1, (int)$card['autopay']);
        $this->assertNull($card['revoked_at']);
        $this->assertSame(1, (int)$this->db->row('SELECT collection_hold FROM stripe_invoice_payments')['collection_hold']);
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_invoice_audit')['n']);
        $this->assertSame(0, $this->constructions);
        $this->assertSame([], $this->stripeCalls);
    }

    public function testMissingAndInvalidCsrfNeverRevokeConsentOrCallStripe(): void
    {
        foreach ([[], ['stripe_hold_csrf' => ''], ['stripe_hold_csrf' => []], ['stripe_hold_csrf' => bin2hex(random_bytes(32))]] as $token) {
            foreach (['stripe_fjern_kort' => 1, 'stripe_release_hold' => $this->payment] as $action => $value) {
                try {
                    stripe_invoice_staff_payment_request($token + [$action => $value, 'firmanavn' => 'Forged address'], 1, 'Saldi: staff', $this->csrf, '1111111', [$this, 'service']);
                    $this->fail('CSRF must reject the direct POST');
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('Formularen er udløbet', $e->getMessage());
                }
                $this->assertUntouched();
            }
        }
    }

    public function testReadOnlyDirectPostsWithValidCsrfAreRefusedBeforeServiceConstruction(): void
    {
        foreach (['1111112', '1111110', '', '1'] as $rights) {
            $this->assertFalse(stripe_invoice_staff_can_write($rights));
            foreach (['stripe_fjern_kort' => 1, 'stripe_release_hold' => $this->payment] as $action => $value) {
                try {
                    stripe_invoice_staff_payment_request(['stripe_hold_csrf' => $this->csrf, $action => $value], 1, 'Saldi: reader', $this->csrf, $rights, [$this, 'service']);
                    $this->fail('Missing write permission must reject the direct POST');
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('skriverettighed', $e->getMessage());
                }
                $this->assertUntouched();
            }
        }
    }

    public function testWriterWithSessionCsrfCanReleaseHoldAndRemoveCard(): void
    {
        $this->assertTrue(stripe_invoice_staff_can_write('1111111'));
        $message = stripe_invoice_staff_payment_request(['stripe_hold_csrf' => $this->csrf, 'stripe_release_hold' => $this->payment], 1, 'Saldi: writer', $this->csrf, '1111111', [$this, 'service']);
        $this->assertStringContainsString('frigivet', $message);
        $this->assertSame(0, (int)$this->db->row('SELECT collection_hold FROM stripe_invoice_payments')['collection_hold']);
        $message = stripe_invoice_staff_payment_request(['stripe_hold_csrf' => $this->csrf, 'stripe_fjern_kort' => 1], 1, 'Saldi: writer', $this->csrf, '1111111', [$this, 'service']);
        $this->assertStringContainsString('kortet er fjernet', $message);
        $card = $this->db->row('SELECT * FROM stripe_invoice_cards');
        $this->assertSame(0, (int)$card['autopay']);
        $this->assertNotNull($card['revoked_at']);
        $this->assertSame([['POST', '/v1/payment_methods/pm_staff/detach']], $this->stripeCalls);
        $this->assertSame(2, $this->constructions);
    }
}
