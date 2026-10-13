<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/StripeWebhookTest.php --- 2026.10.12
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
// 20261012 CL/LH Exercise signed dispatch, subscription recovery and invoice holds through the real webhook.
// 20261012 CL/LH Include ledger facts in the receipt fixture for the committed receipt balance baseline.
// 20261012 CL/LH Initialize request adapters before webhook entry and verify non-POST rejection.
// 20261012 CL/LH Regress production event migration, real mail throttling and corrections before intent binding.

use PHPUnit\Framework\TestCase;
use Saldi\Stripe\InvoicePaymentStore;
use Saldi\Stripe\InvoicePaymentSchema;
use Saldi\Stripe\StripeEventLedger;

require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentStore.php';
require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentSchema.php';
require_once __DIR__ . '/../../includes/stripeIncludes/StripeEventLedger.php';

final class StripeWebhookTest extends TestCase
{
    private InvoicePaymentStore $db;
    private string $directory;
    private string $dsn;
    private ?string $schema = null;
    private string $secret;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/stripe_webhook_' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $this->secret = bin2hex(random_bytes(32));
        $this->dsn = getenv('SALDI_STRIPE_TEST_DSN') ?: 'sqlite:' . $this->directory . '/fixture.sqlite';
        $pdo = new PDO($this->dsn, getenv('SALDI_STRIPE_TEST_USER') ?: null, getenv('SALDI_STRIPE_TEST_PASSWORD') ?: null);
        $this->db = new InvoicePaymentStore($pdo);
        if ($this->db->driver() === 'pgsql') {
            $this->schema = 'stripe_test_' . bin2hex(random_bytes(8));
            $pdo->exec('CREATE SCHEMA ' . $this->schema);
            $pdo->exec('SET search_path TO ' . $this->schema);
        }
        $id = $this->db->driver() === 'pgsql' ? 'BIGSERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        foreach ([
            "CREATE TABLE stripe_events (id $id,event_id VARCHAR(255) UNIQUE NOT NULL,event_type VARCHAR(100),payload TEXT,status VARCHAR(30) DEFAULT 'received',saldi_order_id INTEGER,invoice_number VARCHAR(30),error TEXT,received_at TIMESTAMP,processed_at TIMESTAMP,claim_token VARCHAR(64),claimed_at TIMESTAMP,attempts INTEGER DEFAULT 0,event_created BIGINT)",
            "CREATE TABLE settings (var_grp TEXT,var_name TEXT,var_value TEXT)",
            "CREATE TABLE stripe_customers (id $id,stripe_customer_id VARCHAR(255) UNIQUE,stripe_subscription_id VARCHAR(255),konto_id INTEGER,kontonr VARCHAR(30),order_id INTEGER,status VARCHAR(30),updated_at TIMESTAMP)",
            "CREATE TABLE fixture_alerts (id $id,subject TEXT,body TEXT,dedupe_key TEXT)",
            "CREATE TABLE fixture_mail_attempts (id $id,subject TEXT)",
            "CREATE TABLE fixture_intents (intent_id TEXT PRIMARY KEY,body TEXT)",
            "CREATE TABLE pool_files (id $id,filename VARCHAR(255),subject TEXT,amount TEXT,norm_amount NUMERIC,file_date DATE,invoice_number VARCHAR(30),description TEXT,currency VARCHAR(3))",
        ] as $sql) $pdo->exec($sql);
        foreach (['mode' => 'test', 'import_enabled' => 'off', 'invoice_pay_enabled' => 'off', 'autopay_enabled' => 'off', 'posting_enabled' => 'off'] as $key => $value) {
            $this->db->query("INSERT INTO settings VALUES ('stripe',?,?)", [$key, $value]);
        }
        // 20261012 CL/LH Run the real mail throttle, substituting only the mail transport and temporary directory.
        $mail = file_get_contents(__DIR__ . '/../../includes/stripeIncludes/stripeAlertMail.php');
        $mail = str_replace("use ($" . "to, $" . "subject, $" . "body, $" . "headers)", "use ($" . "to, $" . "subject, $" . "body, $" . "headers, $" . "throttleKey)", $mail);
        $mail = str_replace('return mail($to,', 'return stripe_test_mail($throttleKey, $to,', $mail);
        $mail = str_replace("dirname(__DIR__, 2) . '/temp'", "getenv('SALDI_WEBHOOK_TEST_DIR')", $mail);
        $mail = str_replace('__DIR__', var_export(dirname(__DIR__, 2) . '/includes/stripeIncludes', true), $mail);
        file_put_contents($this->directory . '/alert.php', $mail);
        $source = file_get_contents(__DIR__ . '/../../api/stripe/webhook.php');
        // Replace only I/O adapters; signature, lease, mode, helpers and dispatch run unchanged.
        $source = str_replace("$" . "rawBody = file_get_contents('php://input');", "$" . "rawBody = base64_decode((string)getenv('SALDI_WEBHOOK_TEST_BODY'));", $source);
        foreach (['connect.php', 'stripeIncludes/stripeBootstrap.php', 'stripeIncludes/stripeHttp.php', 'stripeIncludes/stripeAlertMail.php'] as $include) {
            $source = str_replace("include(__DIR__ . '/../../includes/$include');", $include === 'connect.php' ? 'require_once ' . var_export(__DIR__ . '/support/WebhookFixture.php', true) . ';' : '', $source);
        }
        $source = str_replace("new PDO('pgsql:host=' . $" . "sqhost . ';dbname=' . stripeConfigDb(), $" . "squser, $" . "sqpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION])", "$" . "GLOBALS['stripe_test_pdo']", $source);
        $source = str_replace("include_once __DIR__ . '/../../includes/stripeIncludes/invoicePaymentRuntime.php';", 'stripe_test_runtime_load();', $source);
        $source = str_replace("$" . "dir = dirname(__DIR__, 2) . '/bilag/' . $" . "db . '/pulje';", "$" . "dir = getenv('SALDI_WEBHOOK_TEST_DIR') . '/pulje';", $source);
        $source = preg_replace('/\$ch = curl_init\(\$inv\[\'invoice_pdf\'\]\);.*?curl_close\(\$ch\);/s', '$pdf = "%PDF-1.4 fixture"; $code = 200;', $source);
        $source = str_replace('__DIR__', var_export(dirname(__DIR__, 2) . '/api/stripe', true), $source);
        file_put_contents($this->directory . '/webhook.php', $source);
    }

    protected function tearDown(): void
    {
        if ($this->schema !== null) $this->db->pdo->exec('DROP SCHEMA ' . $this->schema . ' CASCADE');
        foreach (glob($this->directory . '/pulje/*') ?: [] as $file) unlink($file);
        if (is_dir($this->directory . '/pulje')) rmdir($this->directory . '/pulje');
        foreach (array_merge(glob($this->directory . '/*') ?: [], glob($this->directory . '/.ht_*') ?: []) as $file) unlink($file);
        rmdir($this->directory);
    }

    /** @return array{status:string,http:int} */
    private function deliver(string $id, string $type, array $object, bool $live = false, string $method = 'POST'): array
    {
        $env = getenv();
        $env['SALDI_WEBHOOK_TEST_DSN'] = $this->dsn;
        $env['SALDI_WEBHOOK_TEST_SCHEMA'] = $this->schema ?? '';
        $env['SALDI_WEBHOOK_TEST_SECRET'] = $this->secret;
        $env['SALDI_WEBHOOK_TEST_DIR'] = $this->directory;
        $env['SALDI_WEBHOOK_TEST_METHOD'] = $method;
        $env['SALDI_WEBHOOK_TEST_BODY'] = base64_encode(json_encode(['id' => $id, 'type' => $type, 'created' => time() - 60, 'livemode' => $live, 'data' => ['object' => $object]]));
        // 20261012 CL/LH CLI needs the HTTP request context before the webhook's POST guard.
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'error_reporting=32767',
            '-d', 'auto_prepend_file=' . __DIR__ . '/support/WebhookFixture.php', $this->directory . '/webhook.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $this->assertIsResource($process);
        $output = trim(stream_get_contents($pipes[1]));
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exit = proc_close($process);
        $this->assertSame('', $errors);
        $this->assertSame(0, $exit, $output);
        $lines = explode("\n", $output);
        return json_decode($lines[0], true) + json_decode($lines[1], true);
    }

    /** @return array<string,mixed> */
    private function subscriptionInvoice(): array
    {
        return ['id' => 'in_fixture', 'number' => 'S1001', 'customer' => 'cus_subscription', 'billing_reason' => 'subscription_cycle',
            'status' => 'paid', 'currency' => 'dkk', 'amount_remaining' => 0, 'amount_paid' => 50000, 'invoice_pdf' => 'https://example.test/invoice.pdf'];
    }

    public function testNonPostRequestIsRejectedBeforeEventClaim(): void
    {
        foreach (['GET', 'PUT'] as $method) {
            $this->assertSame(['status' => 'method_not_allowed', 'http' => 405],
                $this->deliver('evt_non_post_' . $method, 'unknown.event', [], false, $method));
        }
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_events')['n']);
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM fixture_alerts')['n']);
    }

    public function testUnmappedSubscriptionRetriesThenRecordsOnePdfAndOneBookingAlert(): void
    {
        $invoice = $this->subscriptionInvoice();
        $this->assertSame(['status' => 'unmapped_customer', 'http' => 500], $this->deliver('evt_subscription', 'invoice.paid', $invoice));
        $this->assertSame('retry', $this->db->row('SELECT status FROM stripe_events')['status']);
        $this->db->query("INSERT INTO stripe_customers (stripe_customer_id,konto_id,kontonr,order_id,status) VALUES ('cus_subscription',1,'1001',10,'active')");
        $this->assertSame(['status' => 'recorded', 'http' => 200], $this->deliver('evt_subscription', 'invoice.paid', $invoice));
        $this->assertSame(['status' => 'duplicate', 'http' => 200], $this->deliver('evt_subscription', 'invoice.paid', $invoice));
        // Simulate recovery after side effects committed but terminal completion was lost.
        (new StripeEventLedger($this->db->pdo))->requeue('evt_subscription', true);
        $this->assertSame(['status' => 'recorded', 'http' => 200], $this->deliver('evt_subscription', 'invoice.paid', $invoice));
        $this->assertSame(1, (int)$this->db->row('SELECT COUNT(*) AS n FROM pool_files')['n']);
        $this->assertSame(1, (int)$this->db->row("SELECT COUNT(*) AS n FROM fixture_alerts WHERE dedupe_key='subscription-paid-in_fixture'")['n']);
    }

    public function testSubscriptionStoreFailureReleasesClaimAndRecoversImmediately(): void
    {
        $this->db->query("INSERT INTO stripe_customers (stripe_customer_id,konto_id,kontonr,order_id,status) VALUES ('cus_subscription',1,'1001',10,'active')");
        $this->db->query("INSERT INTO settings VALUES ('stripe','fixture_fail_write','on')");
        $this->assertSame(['status' => 'database_failed', 'http' => 500], $this->deliver('evt_store', 'invoice.paid', $this->subscriptionInvoice()));
        $row = $this->db->row('SELECT * FROM stripe_events');
        $this->assertSame('retry', $row['status']);
        $this->assertNull($row['claim_token']);
        $this->db->query("UPDATE settings SET var_value='off' WHERE var_name='fixture_fail_write'");
        $this->assertSame(['status' => 'recorded', 'http' => 200], $this->deliver('evt_store', 'invoice.paid', $this->subscriptionInvoice()));
    }

    public function testForeignIntentsIgnoreMissingRuntimeAndImportPrerequisites(): void
    {
        $this->db->query("UPDATE settings SET var_value='on' WHERE var_name='import_enabled'");
        $this->db->query("INSERT INTO settings VALUES ('stripe','fixture_runtime_error','on')");
        foreach (['payment_intent.succeeded', 'payment_intent.payment_failed'] as $type) {
            $this->assertSame(['status' => 'ignored', 'http' => 200], $this->deliver('evt_' . str_replace('.', '_', $type), $type, ['metadata' => []]));
        }
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM fixture_alerts')['n']);
    }

    public function testTaggedRuntimeFailureIsImmediatelyRetryable(): void
    {
        $object = ['metadata' => ['saldi_db' => 'saldi_test', 'saldi_kind' => 'invoice_manual', 'saldi_order_id' => '10']];
        $this->db->query("INSERT INTO settings VALUES ('stripe','fixture_runtime_error','on')");
        for ($i = 1; $i <= 2; $i++) {
            $this->assertSame(['status' => 'retry', 'http' => 503], $this->deliver('evt_runtime', 'payment_intent.succeeded', $object));
            $row = $this->db->row('SELECT * FROM stripe_events');
            $this->assertSame('retry', $row['status']);
            $this->assertSame($i, (int)$row['attempts']);
            $this->assertNull($row['claim_token']);
        }
    }

    public function testBusyLeaseReturns503AndModeMismatchIsTerminal(): void
    {
        (new StripeEventLedger($this->db->pdo))->receive('evt_busy', 'unknown', '', time());
        $this->assertSame(['status' => 'busy', 'http' => 503], $this->deliver('evt_busy', 'unknown', []));
        $this->assertSame(['status' => 'ignored_mode', 'http' => 200], $this->deliver('evt_live', 'payment_intent.succeeded', [], true));
        $this->assertSame(['status' => 'duplicate', 'http' => 200], $this->deliver('evt_live', 'payment_intent.succeeded', [], true));
    }

    public function testImportReadinessBlocksOnlyTheSubscriptionImport(): void
    {
        $this->db->query("UPDATE settings SET var_value='on' WHERE var_name='import_enabled'");
        $this->db->query("INSERT INTO stripe_customers (stripe_customer_id,konto_id,kontonr,order_id,status) VALUES ('cus_subscription',1,'1001',10,'active')");
        $this->assertSame(['status' => 'import_prereqs_missing', 'http' => 503], $this->deliver('evt_import', 'invoice.paid', $this->subscriptionInvoice()));
        $this->assertSame(['status' => 'ignored', 'http' => 200], $this->deliver('evt_other', 'unknown.event', []));
    }

    public function testRetainedSubscriptionRefundDoesNotNeedInvoiceTables(): void
    {
        $this->db->query("INSERT INTO stripe_customers (stripe_customer_id,konto_id,kontonr,order_id,status) VALUES ('cus_subscription',1,'1001',10,'active')");
        $this->assertSame(['status' => 'ok', 'http' => 200], $this->deliver('evt_subscription_refund', 'charge.refunded', ['id' => 'ch_subscription', 'customer' => 'cus_subscription', 'payment_intent' => 'pi_subscription', 'amount_refunded' => 50000]));
        $this->assertSame(1, (int)$this->db->row('SELECT COUNT(*) AS n FROM fixture_alerts')['n']);
    }

    public function testInvoiceReceiptProcessesWithImportEnabledAndCollectionSwitchesOff(): void
    {
        foreach (InvoicePaymentSchema::statements($this->db->driver()) as $sql) $this->db->query($sql);
        $this->db->query("CREATE TABLE ordrer (id INTEGER,konto_id INTEGER,fakturanr TEXT,kontonr TEXT,firmanavn TEXT,art TEXT,status INTEGER,valuta TEXT)");
        $this->db->query("INSERT INTO ordrer VALUES (10,1,'1001','1001','Kunde','DO',3,'DKK')");
        $this->db->query("CREATE TABLE openpost (id INTEGER,konto_id INTEGER,faktnr TEXT,amount NUMERIC,udlignet TEXT)");
        $this->db->query("INSERT INTO openpost VALUES (1,1,'1001',500,'0')");
        $this->db->query("INSERT INTO stripe_invoice_customers (mode,konto_id,customer_id,created_at) VALUES ('test',1,'cus_invoice',1)");
        $this->db->query("INSERT INTO stripe_invoice_payments (mode,order_id,konto_id,payment_intent_id,amount_ore,kind,status,created_at,updated_at) VALUES ('test',10,1,'pi_fixture',50000,'manual','created',1,1)");
        $this->db->query("UPDATE settings SET var_value='on' WHERE var_name='import_enabled'");
        $object = ['id' => 'pi_fixture', 'status' => 'succeeded', 'amount' => 50000, 'amount_received' => 50000, 'currency' => 'dkk', 'customer' => 'cus_invoice', 'livemode' => false,
            'metadata' => ['saldi_db' => 'saldi_test', 'saldi_kind' => 'invoice_manual', 'saldi_order_id' => '10', 'saldi_konto_id' => '1']];
        $this->assertSame(['status' => 'ok', 'http' => 200], $this->deliver('evt_invoice_receipt', 'payment_intent.succeeded', $object));
        $row = $this->db->row('SELECT * FROM stripe_invoice_payments');
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame('manual', $row['settlement']);
        $this->assertSame('test_mode', $row['settle_error']);
        $this->assertSame(50000, (int)$row['open_ore_at_receipt']);
        $event = $this->db->row('SELECT event_created FROM stripe_events');
        $this->assertSame((int)$event['event_created'], (int)$row['received_at']);
    }

    public function testCheckoutFailureAndDeletionRetainSubscriptionDispatch(): void
    {
        $this->db->query("CREATE TABLE ordrer (id INTEGER,kontonr TEXT,firmanavn TEXT)");
        $this->db->query("INSERT INTO ordrer VALUES (10,'1001','Kunde')");
        $this->assertSame(['status' => 'ok', 'http' => 200], $this->deliver('evt_checkout', 'checkout.session.completed', ['client_reference_id' => '10', 'customer' => 'cus_subscription', 'subscription' => 'sub_fixture', 'metadata' => ['saldi_konto_id' => '1']]));
        $this->assertSame('active', $this->db->row('SELECT status FROM stripe_customers')['status']);
        $this->assertSame(['status' => 'ok', 'http' => 200], $this->deliver('evt_decline', 'invoice.payment_failed', $this->subscriptionInvoice()));
        $this->assertSame(['status' => 'ok', 'http' => 200], $this->deliver('evt_deleted', 'customer.subscription.deleted', ['id' => 'sub_fixture', 'customer' => 'cus_subscription', 'metadata' => ['saldi_order_id' => '10', 'saldi_konto_id' => '1', 'saldi_kontonr' => '1001']]));
        $this->assertSame('canceled', $this->db->row('SELECT status FROM stripe_customers')['status']);
        $this->assertSame(3, (int)$this->db->row('SELECT COUNT(*) AS n FROM fixture_alerts')['n']);
    }

    public function testInvoiceRefundAndDisputesRouteToHoldsWithImportEnabled(): void
    {
        foreach (InvoicePaymentSchema::statements($this->db->driver()) as $sql) $this->db->query($sql);
        $this->db->query("CREATE TABLE ordrer (id INTEGER,konto_id INTEGER,fakturanr TEXT,kontonr TEXT)");
        $this->db->query("INSERT INTO ordrer VALUES (10,1,'1001','1001')");
        $this->db->query("INSERT INTO stripe_invoice_payments (mode,order_id,konto_id,payment_intent_id,amount_ore,kind,status,created_at,updated_at) VALUES ('test',10,1,'pi_fixture',50000,'manual','succeeded',1,1)");
        $this->db->query("UPDATE settings SET var_value='on' WHERE var_name='import_enabled'");
        $this->assertSame(['status' => 'ok', 'http' => 200], $this->deliver('evt_invoice_refund', 'charge.refunded', ['id' => 'ch_invoice', 'payment_intent' => 'pi_fixture', 'amount_refunded' => 10000]));
        foreach (['created', 'closed'] as $phase) {
            $this->assertSame(['status' => 'ok', 'http' => 200], $this->deliver('evt_invoice_' . $phase, 'charge.dispute.' . $phase, ['id' => 'dp_fixture', 'charge' => 'ch_invoice', 'amount' => 50000, 'status' => $phase]));
        }
        $row = $this->db->row('SELECT * FROM stripe_invoice_payments');
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame(1, (int)$row['collection_hold']);
        $this->assertSame('dispute', $row['hold_reason']);
        $this->assertSame(3, (int)$this->db->row("SELECT COUNT(*) AS n FROM stripe_invoice_audit WHERE entity_type='payment'")['n']);
    }

    public function testProductionEventTableWaitsForLoginMigrationAndThrottlesOnlySentAlerts(): void
    {
        $this->db->query('DROP TABLE stripe_events');
        $this->db->query("UPDATE settings SET var_value='live' WHERE var_name='mode'");
        $id = $this->db->driver() === 'pgsql' ? 'BIGSERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        // 20261012 CL/LH Exactly the ten deployed columns and the existing unique event-id index; all collection switches stay off.
        $this->db->query("CREATE TABLE stripe_events (id $id,event_id VARCHAR(255) NOT NULL,event_type VARCHAR(100),payload TEXT,status VARCHAR(30) NOT NULL DEFAULT 'received',saldi_order_id INTEGER,invoice_number VARCHAR(30),error TEXT,received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,processed_at TIMESTAMP)");
        $this->db->query('CREATE UNIQUE INDEX stripe_events_event_id_uidx ON stripe_events (event_id)');
        $this->db->query("INSERT INTO stripe_customers (stripe_customer_id,konto_id,kontonr,order_id,status) VALUES ('cus_subscription',1,'1001',10,'active')");
        $this->db->query("INSERT INTO settings VALUES ('stripe','fixture_mail_fail','on')");
        $this->assertSame(['status' => 'store_failed', 'http' => 503], $this->deliver('evt_migration', 'invoice.paid', $this->subscriptionInvoice(), true));
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM fixture_alerts')['n']);
        $this->db->query("UPDATE settings SET var_value='off' WHERE var_name='fixture_mail_fail'");
        for ($i = 0; $i < 2; $i++) {
            $this->assertSame(['status' => 'store_failed', 'http' => 503], $this->deliver('evt_migration', 'invoice.paid', $this->subscriptionInvoice(), true));
        }
        $this->assertSame(1, (int)$this->db->row('SELECT COUNT(*) AS n FROM fixture_alerts')['n']);
        $stamp = $this->directory . '/.ht_stripe_rl_' . md5('alert_event-store-saldi_test');
        file_put_contents($stamp, (string)(time() - 3601));
        $this->assertSame(['status' => 'store_failed', 'http' => 503], $this->deliver('evt_migration', 'invoice.paid', $this->subscriptionInvoice(), true));
        $this->assertSame(['status' => 'store_failed', 'http' => 503], $this->deliver('evt_migration', 'invoice.paid', $this->subscriptionInvoice(), true));
        $this->assertSame(2, (int)$this->db->row('SELECT COUNT(*) AS n FROM fixture_alerts')['n']);
        $alert = $this->db->row('SELECT subject FROM fixture_alerts');
        $this->assertStringContainsString('Stripe-webhook kan ikke gemme events - log ind i regnskabet, så databasen opdateres', $alert['subject']);
        $columns = $this->db->driver() === 'pgsql'
            ? array_column($this->db->rows("SELECT column_name FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='stripe_events' ORDER BY ordinal_position"), 'column_name')
            : array_column($this->db->rows('PRAGMA table_info(stripe_events)'), 'name');
        $this->assertSame(['id', 'event_id', 'event_type', 'payload', 'status', 'saldi_order_id', 'invoice_number', 'error', 'received_at', 'processed_at'], $columns);
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_events')['n']);
        foreach (['invoice_pay_enabled', 'autopay_enabled', 'posting_enabled'] as $switch) $this->assertSame('off', $this->db->setting($switch));
        // 20261012 CL/LH This is the same upgrade invoked by betweenUpdates; no DDL has run in the request.
        InvoicePaymentSchema::upgradeEvents($this->db);
        InvoicePaymentSchema::upgradeEvents($this->db);
        $this->assertSame(['status' => 'recorded', 'http' => 200], $this->deliver('evt_migration', 'invoice.paid', $this->subscriptionInvoice(), true));
        $this->assertSame('recorded', $this->db->row('SELECT status FROM stripe_events')['status']);
    }

    public function testFailedSubscriptionBookingMailRetriesWithoutConsumingItsPermanentThrottle(): void
    {
        $this->db->query("INSERT INTO stripe_customers (stripe_customer_id,konto_id,kontonr,order_id,status) VALUES ('cus_subscription',1,'1001',10,'active')");
        $this->db->query("INSERT INTO settings VALUES ('stripe','fixture_mail_fail','off')");
        foreach (['on', 'throw'] as $failure) {
            $invoice = $this->subscriptionInvoice();
            $invoice['id'] = 'in_mail_' . $failure;
            $invoice['number'] = 'S_' . $failure;
            $event = 'evt_mail_' . $failure;
            $this->db->query("UPDATE settings SET var_value=? WHERE var_name='fixture_mail_fail'", [$failure]);
            $this->assertSame(['status' => 'mail_failed', 'http' => 503], $this->deliver($event, 'invoice.paid', $invoice));
            $row = $this->db->row('SELECT * FROM stripe_events WHERE event_id=?', [$event]);
            $this->assertSame('retry', $row['status']);
            $this->assertNull($row['claim_token']);
            $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM fixture_alerts WHERE dedupe_key=?', ['subscription-paid-' . $invoice['id']])['n']);
            $this->db->query("UPDATE settings SET var_value='off' WHERE var_name='fixture_mail_fail'");
            $this->assertSame(['status' => 'recorded', 'http' => 200], $this->deliver($event, 'invoice.paid', $invoice));
            (new StripeEventLedger($this->db->pdo))->requeue($event, true);
            $this->assertSame(['status' => 'recorded', 'http' => 200], $this->deliver($event, 'invoice.paid', $invoice));
            $this->assertSame(1, (int)$this->db->row('SELECT COUNT(*) AS n FROM fixture_alerts WHERE dedupe_key=?', ['subscription-paid-' . $invoice['id']])['n']);
        }
        $this->assertSame(4, (int)$this->db->row('SELECT COUNT(*) AS n FROM fixture_mail_attempts')['n']);
        $this->assertSame(2, (int)$this->db->row('SELECT COUNT(*) AS n FROM pool_files')['n']);
    }

    /** @return list<array{string,bool}> Correction deliveries that may precede the intent success webhook. */
    public static function earlyCorrections(): array
    {
        return [['charge.refunded', false], ['charge.dispute.created', false], ['charge.dispute.closed', false], ['charge.refunded', true],
            ['charge.dispute.updated', false], ['charge.dispute.funds_withdrawn', false], ['charge.dispute.funds_reinstated', false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('earlyCorrections')]
    public function testCorrectionBeforeSuccessBindsLostCreationResponseAndKeepsHold(string $type, bool $missingIntent): void
    {
        require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentOperations.php';
        foreach (InvoicePaymentSchema::statements($this->db->driver()) as $sql) $this->db->query($sql);
        $this->db->query("CREATE TABLE ordrer (id INTEGER,konto_id INTEGER,fakturanr TEXT,kontonr TEXT,firmanavn TEXT,art TEXT,status INTEGER,valuta TEXT)");
        $this->db->query("INSERT INTO ordrer VALUES (10,1,'1001','1001','Kunde','DO',3,'DKK')");
        $this->db->query('CREATE TABLE openpost (konto_id INTEGER,faktnr TEXT,amount NUMERIC,udlignet TEXT)');
        $this->db->query("INSERT INTO openpost VALUES (1,'1001',500,'0')");
        $this->db->query("INSERT INTO stripe_invoice_customers (mode,konto_id,customer_id,created_at) VALUES ('test',1,'cus_invoice',1)");
        $payment = $this->db->insert("INSERT INTO stripe_invoice_payments (mode,order_id,konto_id,amount_ore,kind,status,created_at,updated_at) VALUES ('test',10,1,50000,'auto','created',1,1)", []);
        $meta = ['saldi_db' => 'saldi_test', 'saldi_kind' => 'invoice_auto', 'saldi_order_id' => '10', 'saldi_konto_id' => '1', 'saldi_payment_row' => (string)$payment];
        $pi = ['id' => 'pi_fixture', 'status' => 'succeeded', 'amount' => 50000, 'amount_received' => 50000, 'currency' => 'dkk', 'customer' => 'cus_invoice', 'livemode' => false, 'created' => time() - 120, 'metadata' => $meta];
        $ops = new \Saldi\Stripe\InvoicePaymentOperations($this->db, 'saldi_test');
        $op = $ops->prepare('intent_create', (string)$payment, '/v1/payment_intents', ['amount' => 50000, 'metadata' => $meta], 10, 50000, null, $payment);
        try {
            $ops->dispatch((int)$op['id'], function () use ($pi) {
                // 20261012 CL/LH Stripe created and charged the intent, but the creating response never reached Saldi.
                $this->db->query('INSERT INTO fixture_intents VALUES (?,?)', [$pi['id'], json_encode($pi)]);
                throw new RuntimeException('Transport timeout after creation');
            }, static function () {});
            $this->fail('Expected the lost creation response');
        } catch (RuntimeException $e) {
            $this->assertSame('Transport timeout after creation', $e->getMessage());
        }
        $this->assertNull($this->db->row('SELECT payment_intent_id FROM stripe_invoice_payments')['payment_intent_id']);
        $charge = ['id' => 'ch_invoice', 'payment_intent' => 'pi_fixture', 'amount_refunded' => 10000, 'currency' => 'dkk'];
        if ($missingIntent) unset($charge['payment_intent']);
        $object = $type === 'charge.refunded' ? $charge : ['id' => 'dp_fixture', 'charge' => 'ch_invoice', 'amount' => 10000, 'status' => 'lost'];
        $this->assertSame(['status' => 'ok', 'http' => 200], $this->deliver('evt_early', $type, $object));
        $row = $this->db->row('SELECT * FROM stripe_invoice_payments');
        $this->assertSame('pi_fixture', $row['payment_intent_id']);
        $this->assertSame(1, (int)$row['collection_hold']);
        $this->assertSame('created', $row['status']);
        $this->assertNull($row['received_at']);
        $this->assertSame(['status' => 'ok', 'http' => 200], $this->deliver('evt_later_success', 'payment_intent.succeeded', $pi));
        $row = $this->db->row('SELECT * FROM stripe_invoice_payments');
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame(1, (int)$row['collection_hold']);
        $this->assertSame($type === 'charge.refunded' ? 'refund' : 'dispute', $row['hold_reason']);
        $this->assertSame(1, (int)$this->db->row("SELECT COUNT(*) AS n FROM stripe_invoice_audit WHERE action IN ('refund','dispute')")['n']);
        $this->assertSame(['status' => 'duplicate', 'http' => 200], $this->deliver('evt_early', $type, $object));
    }

    public function testOwnedCorrectionWithMissingDurableAttemptStaysRetryable(): void
    {
        foreach (InvoicePaymentSchema::statements($this->db->driver()) as $sql) $this->db->query($sql);
        $pi = ['id' => 'pi_unbound', 'livemode' => false, 'metadata' => ['saldi_db' => 'saldi_test', 'saldi_kind' => 'invoice_auto', 'saldi_order_id' => '10', 'saldi_konto_id' => '1', 'saldi_payment_row' => '999']];
        $this->db->query('INSERT INTO fixture_intents VALUES (?,?)', ['pi_unbound', json_encode($pi)]);
        $charge = ['id' => 'ch_unbound', 'payment_intent' => 'pi_unbound', 'amount_refunded' => 10000];
        for ($i = 0; $i < 2; $i++) {
            $this->assertSame(['status' => 'retry', 'http' => 503], $this->deliver('evt_unbound_correction', 'charge.refunded', $charge));
            $this->assertSame('retry', $this->db->row('SELECT status FROM stripe_events')['status']);
        }
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM fixture_alerts')['n']);
    }

}
