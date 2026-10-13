<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/InvoicePaymentLinkTest.php --- ver 5.0.0 --- 2026.10.13
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
// 20261012 CL/LH Exercise real mail helpers with an injected PDO store and generated signing key.
// 20261013 CL/LH Cover read-only Systemdata POST refusal and disabled settings controls alongside authorized driver guards.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Saldi\Stripe\InvoicePaymentSchema;

require_once __DIR__ . '/support/InvoicePaymentDatabase.php';

/**
 * The pinned-tenant fixture replaces only the install locator, within isolated test processes.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class InvoicePaymentLinkTest extends TestCase
{
    use InvoicePaymentDatabase;

    protected function setUp(): void
    {
        $this->openDatabase();
        // 20261012 CL/LH Avoid a real installation locator/global database in mail-helper tests.
        if (!function_exists('stripeConfigDb')) {
            function stripeConfigDb() { return 'saldi_test'; }
        }
        if (!function_exists('db_select')) {
            function db_select($sql, $source, $global = false) {
                return $GLOBALS['stripe_link_test_store']->query($sql);
            }
        }
        if (!function_exists('db_fetch_array')) {
            function db_fetch_array($query) { return $query->fetch(); }
        }
        require_once __DIR__ . '/../../includes/formFuncIncludes/invoicePaymentLink.php';
        require_once __DIR__ . '/../../includes/formFuncIncludes/subscriptionLink.php';
        $GLOBALS['db'] = 'saldi_test';
        $GLOBALS['db_type'] = 'pgsql';
        $GLOBALS['stripe_link_test_store'] = $this->db;
        $GLOBALS['stripe_settings_cache'] = [
            'mode' => 'test', 'enabled' => 'on', 'invoice_pay_enabled' => 'on',
            'autopay_enabled' => 'on', 'secret_key' => bin2hex(random_bytes(32)),
            'publishable_key' => 'pk_test_' . bin2hex(random_bytes(16)),
            'link_secret' => bin2hex(random_bytes(32)), 'public_base_url' => 'https://example.test',
        ];
        $this->db->query('CREATE TABLE settings (var_grp TEXT,var_name TEXT,var_value TEXT)');
        $this->db->query("INSERT INTO settings VALUES ('stripe','invoice_pay_enabled','on')");
        $this->db->query('CREATE TABLE adresser (id INTEGER PRIMARY KEY,pbs TEXT,stripe_fravalg TEXT)');
        $this->db->query("INSERT INTO adresser VALUES (1,'','')");
        $this->db->query('CREATE TABLE ordrer (id INTEGER PRIMARY KEY,art TEXT,konto_id INTEGER,fakturanr TEXT,valuta TEXT,sum NUMERIC,moms NUMERIC)');
        $this->db->query("INSERT INTO ordrer VALUES (10,'DO',1,'1001','DKK',400,100)");
        $this->db->query('CREATE TABLE stripe_customers (konto_id INTEGER,status VARCHAR(30))');
    }

    public function testInvoiceLinkAndHtmlAreSuppressedForEachCollectionBlock(): void
    {
        $url = invoicePaymentLinkUrl(10, $this->db);
        $this->assertStringContainsString('/api/stripe/faktura.php?id=10&sig=', $url);
        $this->assertStringContainsString('Se faktura og betal', invoicePaymentLinkHtml($url));
        $this->assertSame('', subscriptionLinkUrl(10));
        foreach (['pbs', 'subscription', 'optout', 'disabled'] as $reason) {
            $this->db->query("UPDATE adresser SET pbs='',stripe_fravalg=''");
            $this->db->query('DELETE FROM stripe_customers');
            $this->db->query("UPDATE settings SET var_value='on'");
            if ($reason === 'pbs') $this->db->query("UPDATE adresser SET pbs=' YES '");
            elseif ($reason === 'subscription') $this->db->query("INSERT INTO stripe_customers VALUES (1,'trialing')");
            elseif ($reason === 'optout') $this->db->query("UPDATE adresser SET stripe_fravalg='on'");
            else $this->db->query("UPDATE settings SET var_value='off'");
            $url = invoicePaymentLinkUrl(10, $this->db);
            $this->assertSame('', $url, $reason);
            $this->assertSame('', invoicePaymentLinkHtml($url));
        }
        $this->db->query("UPDATE settings SET var_value='on'");
        $this->db->query("INSERT INTO stripe_customers VALUES (1,'canceled')");
        $this->assertNotSame('', invoicePaymentLinkUrl(10, $this->db));
        $this->db->query('DROP TABLE stripe_customers');
        $this->assertNotSame('', invoicePaymentLinkUrl(10, $this->db));
    }

    public function testMailTokensNeverProduceTwoButtonsForAnySwitchCombination(): void
    {
        // Seed the existing subscription helper's guards through its ordinary DB facade.
        // SQLite supplies a test-only to_regclass function so production helper code is unchanged.
        if ($this->db->driver() === 'sqlite') {
            $this->db->pdo->sqliteCreateFunction('to_regclass', static fn($name) => in_array($name, ['stripe_catalog', 'stripe_customers'], true) ? $name : null);
            $this->db->query('ATTACH DATABASE ":memory:" AS information_schema');
            $this->db->query('CREATE TABLE information_schema.columns (table_name TEXT,column_name TEXT)');
            $this->db->query("INSERT INTO information_schema.columns VALUES ('adresser','stripe_fravalg')");
        }
        $this->db->query('CREATE TABLE stripe_catalog (varenr TEXT,active BOOLEAN)');
        $this->db->query("INSERT INTO stripe_catalog VALUES ('ABO',true)");
        $this->db->query('CREATE TABLE ordrelinjer (id INTEGER PRIMARY KEY,ordre_id INTEGER,varenr TEXT)');
        $this->db->query("INSERT INTO ordrelinjer VALUES (1,10,'ABO')");
        // Column selected by the existing subscription helper.
        $this->db->query('ALTER TABLE stripe_customers ADD COLUMN stripe_subscription_id VARCHAR(255)');
        foreach (['off', 'on'] as $invoice) {
            foreach (['off', 'on'] as $subscription) {
                $GLOBALS['stripe_settings_cache']['invoice_pay_enabled'] = $invoice;
                $GLOBALS['stripe_settings_cache']['enabled'] = $subscription;
                $this->db->query('UPDATE settings SET var_value=?', [$invoice]);
                $payUrl = invoicePaymentLinkUrl(10, $this->db);
                $subUrl = subscriptionLinkUrl(10);
                $html = str_replace(['$fakturalink', '$abonnementslink'], [invoicePaymentLinkHtml($payUrl), subscriptionLinkHtml($subUrl)], '$fakturalink $abonnementslink');
                $plain = str_replace(['$fakturalink', '$abonnementslink'], [$payUrl, $subUrl], '$fakturalink $abonnementslink');
                $expected = $invoice === 'on' || $subscription === 'on' ? 1 : 0;
                $this->assertSame($expected, substr_count($html, '<a '));
                $this->assertSame($expected, substr_count($plain, 'https://example.test/'));
                if ($invoice === 'on') $this->assertSame('', $subUrl);
            }
        }
    }

    public function testMysqlAndUnavailableEligibilityNeverProduceInvoiceLink(): void
    {
        $GLOBALS['db_type'] = 'mysqli';
        $this->assertSame('', invoicePaymentLinkUrl(10, $this->db));
        $GLOBALS['db_type'] = 'mysql';
        $this->assertSame('', invoicePaymentLinkUrl(10, $this->db));
        $GLOBALS['db_type'] = 'pgsql';
        $this->db->query('DROP TABLE adresser');
        $this->assertSame('', invoicePaymentLinkUrl(10, $this->db));
    }

    public function testSettingsSaveForcesInvoiceSwitchesOffOnUnsupportedDrivers(): void
    {
        if (!function_exists('update_settings_value')) {
            function update_settings_value($name, $group, $value, $description) {
                $GLOBALS['stripe_link_saved_settings'][$name] = $value;
            }
        }
        if (!function_exists('db_escape_string')) {
            function db_escape_string($value) { return str_replace("'", "''", (string)$value); }
        }
        require_once __DIR__ . '/../../includes/std_func.php';
        require_once __DIR__ . '/../../systemdata/diverseIncludes/stripeValg.php';
        $GLOBALS['rettigheder'] = '010000000';
        $cache = $GLOBALS['stripe_settings_cache'];
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_POST = [
            'csrf_token' => $token, 'stripe_enabled' => '1', 'stripe_mode' => 'test',
            'stripe_secret_key' => '', 'stripe_webhook_secret' => '', 'stripe_regen_link_secret' => '',
            'stripe_vat_rate_id' => '', 'stripe_public_base_url' => 'https://example.test',
            'stripe_bookkeeper_email' => '', 'stripe_publishable_key' => '',
            'stripe_invoice_pay_enabled' => '1', 'stripe_autopay_enabled' => '1',
            'stripe_posting_enabled' => '1', 'stripe_clearing_account' => '3000',
        ];
        $this->db->query('CREATE TABLE kontoplan (id INTEGER PRIMARY KEY,kontonr INTEGER,kontotype TEXT,lukket TEXT)');
        $this->db->query("INSERT INTO kontoplan VALUES (1,3000,'D','')");
        foreach (['pgsql' => 'on', 'mysql' => 'off', 'mysqli' => 'off'] as $driver => $expected) {
            $GLOBALS['db_type'] = $driver;
            $GLOBALS['stripe_settings_cache'] = $cache;
            $GLOBALS['stripe_link_saved_settings'] = [];
            ob_start();
            try { stripeValgSave(); } finally { $output = ob_get_clean(); }
            $this->assertSame('', $output);
            foreach (['invoice_pay_enabled', 'autopay_enabled', 'posting_enabled'] as $switch) {
                $this->assertSame($expected, $GLOBALS['stripe_link_saved_settings'][$switch], $driver . ': ' . $switch);
            }
            $this->assertSame('on', $GLOBALS['stripe_link_saved_settings']['enabled'], 'Subscription switch remains independent');
        }
        $_POST = [];
        unset($_SESSION['csrf_token'], $GLOBALS['stripe_link_saved_settings']);
    }

    public function testReadOnlySystemdataPostWithValidCsrfCannotMutateAnyStripeSetting(): void
    {
        if (!function_exists('update_settings_value')) {
            function update_settings_value($name, $group, $value, $description) {
                $GLOBALS['stripe_link_saved_settings'][$name] = $value;
            }
        }
        require_once __DIR__ . '/../../includes/std_func.php';
        require_once __DIR__ . '/../../systemdata/diverseIncludes/stripeValg.php';
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_POST = ['csrf_token' => $token, 'stripe_mode' => 'live', 'stripe_enabled' => '1',
            'stripe_invoice_pay_enabled' => '1', 'stripe_autopay_enabled' => '1', 'stripe_posting_enabled' => '1',
            'stripe_clearing_account' => '3000', 'stripe_regen_link_secret' => '1',
            'stripe_secret_key' => 'sk_live_' . bin2hex(random_bytes(16)),
            'stripe_webhook_secret' => 'whsec_' . bin2hex(random_bytes(16))];
        $before = $this->db->rows('SELECT * FROM settings');
        $cache = $GLOBALS['stripe_settings_cache'];
        foreach (['020000100', '000000100', '', '0'] as $rights) {
            $GLOBALS['rettigheder'] = $rights;
            $GLOBALS['stripe_link_saved_settings'] = [];
            ob_start();
            try { stripeValgSave(); } finally { $output = ob_get_clean(); }
            $this->assertStringContainsString('ikke skriverettighed til Systemdata', $output);
            $this->assertSame([], $GLOBALS['stripe_link_saved_settings']);
            $this->assertSame($before, $this->db->rows('SELECT * FROM settings'));
            $this->assertSame($cache, $GLOBALS['stripe_settings_cache']);
        }
        $this->assertTrue(stripeValgCanWrite('010000000'), 'Systemdata uses index 1, independently of debtor rights');
    }

    public function testReadOnlySystemdataRendersEveryMutationControlDisabledWithSavedValues(): void
    {
        if (!function_exists('findtekst')) {
            function findtekst($text, $language) { return explode('|', $text, 2)[1]; }
        }
        require_once __DIR__ . '/../../systemdata/diverseIncludes/stripeValg.php';
        if ($this->db->driver() === 'sqlite') {
            $this->db->pdo->sqliteCreateFunction('to_regclass', static fn($name) => null);
        }
        $GLOBALS['csrf_token'] = bin2hex(random_bytes(32));
        $GLOBALS['sprog_id'] = 1;
        $GLOBALS['stripe_settings_cache']['posting_enabled'] = 'on';
        $GLOBALS['stripe_settings_cache']['clearing_account'] = '3000';
        $GLOBALS['stripe_settings_cache']['secret_key'] = 'sk_test_' . bin2hex(random_bytes(16));
        $GLOBALS['stripe_settings_cache']['webhook_secret'] = 'whsec_' . bin2hex(random_bytes(16));
        foreach (['020000100' => true, '010000000' => false] as $rights => $disabled) {
            $GLOBALS['rettigheder'] = $rights;
            ob_start();
            try { stripeValg(); } finally { $html = ob_get_clean(); }
            preg_match_all('/<(?:input|select)\b[^>]*>/i', $html, $matches);
            $controls = array_values(array_filter($matches[0], static fn($tag) => preg_match("/name='stripe_|type='submit'/", $tag) === 1));
            $this->assertCount(14, $controls);
            foreach ($controls as $control) {
                $this->assertSame($disabled, preg_match('/\bdisabled\b/', $control) === 1, $control);
            }
            $this->assertMatchesRegularExpression("/name='stripe_invoice_pay_enabled'[^>]*checked/", $html);
            $this->assertMatchesRegularExpression("/name='stripe_clearing_account'[^>]*value='3000'/", $html);
            $this->assertStringNotContainsString($GLOBALS['stripe_settings_cache']['secret_key'], $html);
            $this->assertStringNotContainsString($GLOBALS['stripe_settings_cache']['webhook_secret'], $html);
        }
    }

    protected function tearDown(): void
    {
        // Explicitly call the trait cleanup before resetting injected globals.
        if ($this->schema !== null) $this->db->pdo->exec('DROP SCHEMA ' . $this->schema . ' CASCADE');
        $_POST = [];
        unset($_SESSION['csrf_token'], $GLOBALS['stripe_link_saved_settings'], $GLOBALS['rettigheder'], $GLOBALS['csrf_token'], $GLOBALS['sprog_id']);
        unset($GLOBALS['stripe_link_test_store'], $GLOBALS['stripe_settings_cache'], $GLOBALS['db'], $GLOBALS['db_type']);
    }
}
