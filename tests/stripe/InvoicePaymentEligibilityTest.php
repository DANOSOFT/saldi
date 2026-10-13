<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/InvoicePaymentEligibilityTest.php --- 2026.10.12
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
// 20261012 CL/LH Read-only eligibility tests on SQLite and isolated PostgreSQL schemas.

use PHPUnit\Framework\TestCase;
use Saldi\Stripe\InvoicePaymentEligibility;
use Saldi\Stripe\InvoicePaymentSchema;

require_once __DIR__ . '/support/InvoicePaymentDatabase.php';
require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentEligibility.php';

final class InvoicePaymentEligibilityTest extends TestCase
{
    use InvoicePaymentDatabase;

    private InvoicePaymentEligibility $eligibility;

    protected function setUp(): void
    {
        $this->openDatabase();
        $this->db->query('CREATE TABLE settings (var_grp TEXT,var_name TEXT,var_value TEXT)');
        $this->db->query('CREATE TABLE adresser (id INTEGER PRIMARY KEY,pbs TEXT,stripe_fravalg TEXT)');
        $this->db->query("INSERT INTO adresser (id,pbs,stripe_fravalg) VALUES (1,'',''),(2,'','')");
        foreach (['mode' => 'test', 'invoice_pay_enabled' => 'on', 'autopay_enabled' => 'on'] as $key => $value) {
            $this->db->query("INSERT INTO settings (var_grp,var_name,var_value) VALUES ('stripe',?,?)", [$key, $value]);
        }
        $this->eligibility = new InvoicePaymentEligibility($this->db);
    }

    public function testPbsTruthyVariantsAreBlockedAndOtherValuesAreEligible(): void
    {
        foreach (['on', ' ON ', '1', 't', 'T', 'true', ' TrUe ', 'yes', ' YES '] as $value) {
            $this->db->query('UPDATE adresser SET pbs=? WHERE id=1', [$value]);
            $this->assertSame(['pbs'], $this->eligibility->collectionReasons(1), $value);
        }
        foreach (['', 'off', '0', 'false', 'no', null] as $value) {
            $this->db->query('UPDATE adresser SET pbs=? WHERE id=1', [$value]);
            $this->assertSame([], $this->eligibility->collectionReasons(1));
        }
    }

    public function testLiveSubscriptionStatesBlockRegardlessOfModeButCanceledDoesNot(): void
    {
        $this->db->query('CREATE TABLE stripe_customers (konto_id INTEGER,status VARCHAR(30))');
        foreach (['active', 'trialing', 'past_due', 'canceled', 'incomplete_expired'] as $status) {
            $this->db->query('DELETE FROM stripe_customers');
            $this->db->query('INSERT INTO stripe_customers (konto_id,status) VALUES (1,?)', [$status]);
            $this->assertSame(in_array($status, ['active', 'trialing', 'past_due'], true) ? ['subscription'] : [], $this->eligibility->collectionReasons(1));
            $this->assertSame([], $this->eligibility->collectionReasons(2));
        }
    }

    public function testMissingSubscriptionTableIsAllowed(): void
    {
        $this->assertSame([], $this->eligibility->collectionReasons(1));
        $this->assertSame(['no_card'], $this->eligibility->autopayReasons(1));
    }

    public function testOptoutAndSwitchOffReturnAllReasonsInPriorityOrder(): void
    {
        $this->db->query('CREATE TABLE stripe_customers (konto_id INTEGER,status VARCHAR(30))');
        $this->db->query("INSERT INTO stripe_customers VALUES (1,'past_due')");
        $this->db->query("UPDATE adresser SET pbs='on',stripe_fravalg=' yes ' WHERE id=1");
        $this->db->query("UPDATE settings SET var_value='off' WHERE var_name IN ('invoice_pay_enabled','autopay_enabled')");
        $this->assertSame(['pbs', 'subscription', 'optout', 'disabled'], $this->eligibility->collectionReasons(1));
        $this->assertSame(['pbs', 'subscription', 'optout', 'disabled', 'autopay_disabled', 'no_card'], $this->eligibility->autopayReasons(1));
        $this->db->query("UPDATE adresser SET stripe_fravalg='   ',pbs='' WHERE id=2");
        $this->assertSame(['disabled'], $this->eligibility->collectionReasons(2));
        $this->db->query("DELETE FROM settings WHERE var_name='invoice_pay_enabled'");
        $this->assertSame(['disabled'], $this->eligibility->collectionReasons(2), 'Missing switches default off');
    }

    public function testBatchMatchesSingleReadsAndDoesNotWrite(): void
    {
        foreach (InvoicePaymentSchema::statements($this->db->driver()) as $sql) $this->db->query($sql);
        $this->db->query("UPDATE adresser SET pbs='on' WHERE id=2");
        $this->db->query("INSERT INTO stripe_invoice_cards (mode,konto_id,customer_id,payment_method_id,autopay,consent_at,broken_at,revoked_at,updated_at) VALUES ('test',1,'cus_fixture','pm_fixture',1,100,110,120,120)");
        $tables = ['adresser', 'settings', 'stripe_invoice_cards', 'stripe_invoice_audit'];
        $before = [];
        foreach ($tables as $table) $before[$table] = $this->db->rows('SELECT * FROM ' . $table);
        $batch = $this->eligibility->autopayReasonsFor([2, 1, 2]);
        $this->assertSame(['card_broken', 'card_revoked'], $batch[1]);
        $this->assertSame(['pbs', 'no_card'], $batch[2]);
        foreach ([1, 2] as $id) $this->assertSame($this->eligibility->autopayReasons($id), $batch[$id]);
        $this->assertSame([2 => ['pbs'], 1 => []], $this->eligibility->collectionReasonsFor([2, 1, 2]));
        $this->assertSame([], $this->eligibility->collectionReasonsFor([]));
        $this->assertSame([], $this->eligibility->autopayReasonsFor([]));
        foreach ($tables as $table) $this->assertSame($before[$table], $this->db->rows('SELECT * FROM ' . $table));
    }

    public function testAutopayRequiresModeMatchedCardConsentAndActivation(): void
    {
        foreach (InvoicePaymentSchema::statements($this->db->driver()) as $sql) $this->db->query($sql);
        $this->db->query("INSERT INTO stripe_invoice_cards (mode,konto_id,customer_id,payment_method_id,autopay,consent_at,updated_at) VALUES ('live',1,'cus_fixture','pm_fixture',1,100,100)");
        $this->assertSame(['no_card'], $this->eligibility->autopayReasons(1));
        $this->db->query("UPDATE stripe_invoice_cards SET mode='test',autopay=0");
        $this->assertSame(['no_card'], $this->eligibility->autopayReasons(1));
        $this->db->query('UPDATE stripe_invoice_cards SET autopay=1,consent_at=NULL');
        $this->assertSame(['no_card'], $this->eligibility->autopayReasons(1));
        $this->db->query('UPDATE stripe_invoice_cards SET consent_at=100');
        $this->assertSame([], $this->eligibility->autopayReasons(1));
    }

    public function testLargeBatchIsChunkedAndStillKeyedByEveryDebtor(): void
    {
        $ids = range(1, 1200);
        $batch = $this->eligibility->collectionReasonsFor($ids);
        $this->assertCount(1200, $batch);
        foreach ($ids as $id) $this->assertSame([], $batch[$id]);
    }
}
