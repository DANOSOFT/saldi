<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/InvoicePaymentConsentTest.php --- 2026.10.12
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
// 20261012 CL/LH Consent text: agreed version, stable hash, required statements, and the page renders it.

use PHPUnit\Framework\TestCase;
use Saldi\Stripe\InvoicePaymentConsent;

require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentConsent.php';

final class InvoicePaymentConsentTest extends TestCase
{
    public function testVersionIsTheAgreedPlaceholderAndNotYetApproved(): void
    {
        $this->assertSame('v1-2026-10', InvoicePaymentConsent::VERSION);
        $this->assertFalse(InvoicePaymentConsent::APPROVED, 'Flip only after Adam approves the wording');
    }

    public function testHashCoversTitleAndBodyAndIsStable(): void
    {
        $this->assertSame(InvoicePaymentConsent::TITLE . "\n" . InvoicePaymentConsent::TEXT, InvoicePaymentConsent::text());
        $this->assertSame(hash('sha256', InvoicePaymentConsent::text()), InvoicePaymentConsent::sha256());
        $this->assertSame(InvoicePaymentConsent::sha256(), InvoicePaymentConsent::sha256());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', InvoicePaymentConsent::sha256());
        $this->assertLessThanOrEqual(30, strlen(InvoicePaymentConsent::VERSION), 'terms_version is VARCHAR(30)');
    }

    public function testTextStatesTheAgreedTerms(): void
    {
        $text = InvoicePaymentConsent::TEXT;
        $this->assertSame('Betal fremtidige fakturaer automatisk', InvoicePaymentConsent::TITLE);
        foreach ([
            'gemme mit kort hos Stripe',                  // Danosoft may keep the card with Stripe
            'automatisk trække fremtidige fakturaer fra Danosoft', // future invoices from Danosoft
            'på forfaldsdatoen',                          // on their due date
            'stadig er åbent på den enkelte faktura',     // amount still open on each invoice
            'fakturaen på mail',                          // the invoice still arrives by mail
            '»Fjern kort« på fakturasiden',               // how to stop: the link
            'kontakte Danosoft',                          // how to stop: contact
        ] as $phrase) {
            $this->assertStringContainsString($phrase, $text);
        }
        $this->assertTrue(mb_check_encoding($text, 'UTF-8'));
    }

    public function testHostedPageRendersTheConsentConstantsNextToAnUntickedBox(): void
    {
        $page = (string)file_get_contents(dirname(__DIR__, 2) . '/api/stripe/faktura.php');
        $this->assertStringContainsString("<input type='checkbox' id='save'>", $page, 'The box starts unticked');
        $this->assertStringContainsString('fp_h(Consent::TITLE)', $page);
        $this->assertStringContainsString('fp_h(Consent::TEXT)', $page);
        $this->assertStringContainsString("data-terms='\" . fp_h(Consent::VERSION)", $page);
        $this->assertStringNotContainsString('gemmer aldrig dine kortoplysninger', $page);
        $this->assertStringContainsString('Saldi gemmer kun korttype, de sidste fire cifre og udløbsdato.', $page);
    }
}
