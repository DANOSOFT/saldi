<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/StripeWebhookRoutingTest.php --- 2026.10.12
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
// 20261012 CL/LH Cover invoice/foreign/subscription routing without any runtime or database.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stripeIncludes/stripeWebhookRouting.php';

final class StripeWebhookRoutingTest extends TestCase
{
    public function testOnlyInvoiceKindsForThePinnedTenantAreRouted(): void
    {
        foreach (['invoice_manual', 'invoice_auto'] as $kind) {
            $this->assertTrue(stripe_wh_is_invoice_payment(['metadata' => ['saldi_kind' => $kind, 'saldi_db' => 'saldi_test']], 'saldi_test'));
            $this->assertFalse(stripe_wh_is_invoice_payment(['metadata' => ['saldi_kind' => $kind, 'saldi_db' => 'foreign']], 'saldi_test'));
        }
    }

    public function testSubscriptionMissingAndMalformedMetadataAreIgnored(): void
    {
        foreach ([[], ['metadata' => []], ['metadata' => null], ['metadata' => 'invalid'],
            ['metadata' => ['saldi_kind' => 'subscription', 'saldi_db' => 'saldi_test']],
            ['metadata' => ['saldi_kind' => 'invoice_manual', 'saldi_db' => '']],
            ['metadata' => ['saldi_kind' => 'invoice_manual', 'saldi_database' => 'saldi_test']]] as $intent) {
            $this->assertFalse(stripe_wh_is_invoice_payment($intent, 'saldi_test'));
        }
        $this->assertFalse(stripe_wh_is_invoice_payment(['metadata' => ['saldi_kind' => 'invoice_manual', 'saldi_db' => '']], ''));
    }
    public function testStaffHoldReleaseRejectsMissingOrInvalidCsrfBeforeRuntimeConstruction(): void
    {
        require_once __DIR__ . '/../../includes/stripeIncludes/invoicePaymentRuntime.php';
        $csrf = bin2hex(random_bytes(32));
        foreach ([[], ['stripe_hold_csrf' => []], ['stripe_hold_csrf' => bin2hex(random_bytes(32))]] as $request) {
            try {
                stripe_invoice_release_hold_request($request + ['stripe_release_hold' => 1], 1, 'Saldi: staff', $csrf);
                $this->fail('A bad CSRF must be rejected before database access');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Formularen er udløbet', $e->getMessage());
            }
        }
    }

}
