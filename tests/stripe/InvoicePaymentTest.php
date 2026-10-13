<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/InvoicePaymentTest.php --- ver 5.0.0 --- 2026.10.13
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
// 20261007 CL/LH Created: invoice-first card payments against a fake Stripe
//                transport. SQLite in memory by default; set SALDI_STRIPE_TEST_DSN,
//                SALDI_STRIPE_TEST_USER and SALDI_STRIPE_TEST_PASSWORD to run on an
//                isolated PostgreSQL database (a throwaway schema per test).
// 20261012 CL/LH Cover collection exclusions, persistent worker skip throttling and test-mode ledger isolation.
// 20261012 CL/LH Cover event-time transitions, payment identity, card generations and refund/dispute holds.
// 20261012 CL/LH Cover durable dispatch, receipt/enrollment separation, reconciliation, recovery and notification failures.
// 20261012 CL/LH Release fixture connections after each test, including unfinished transactions and partial setup.
// 20261012 CL/LH Regress hosted recovery, actual consent time, REST declines, year-end receipt dates and rejected requests.
// 20261012 CL/LH Regress sticky unknown charge outcomes and stable recovered declines without advancing signed event ordering.
// 20261013 CL/LH Regress recovery beyond 100 abandoned intents, later decline evidence, successor totals and local refusal repair.
// 20261013 CL/LH (CodeRabbit) Regress live receipt posting with whitespace and lowercase invoice currency.

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Saldi\Stripe\InvoicePaymentLedger;
use Saldi\Stripe\InvoicePaymentSchema;
use Saldi\Stripe\InvoicePaymentService;
use Saldi\Stripe\InvoicePaymentStore;

require_once dirname(__DIR__, 2) . '/includes/stripeIncludes/InvoicePaymentMath.php';
require_once dirname(__DIR__, 2) . '/includes/stripeIncludes/InvoicePaymentStore.php';
require_once dirname(__DIR__, 2) . '/includes/stripeIncludes/InvoicePaymentSchema.php';
require_once dirname(__DIR__, 2) . '/includes/stripeIncludes/InvoicePaymentLedger.php';
require_once dirname(__DIR__, 2) . '/includes/stripeIncludes/InvoicePaymentService.php';

/** Just enough of the PaymentIntents, Customers and PaymentMethods APIs. */
final class FakeInvoiceStripe
{
    /** @var list<array{0:string,1:string,2:array,3:array}> */
    public array $calls = [];
    /** @var array<string,array<string,mixed>> */
    public array $intents = [];
    /** @var array<string,string> idempotency key => object id */
    private array $keys = [];
    public $intercept = null;
    public string $decline = '';
    public bool $networkDown = false;
    public bool $liveMode = false;
    public string $detachError = '';
    public int $now;
    private int $n = 0;

    public function __construct(int $now) { $this->now = $now; }

    public function transport(string $method, string $path, array $params, array $opts): array
    {
        $this->calls[] = [$method, $path, $params, $opts];
        if ($this->intercept !== null) {
            $response = ($this->intercept)($method, $path, $params, $opts);
            if ($response !== null) return $response;
        }
        if ($this->networkDown) return ['ok' => false, 'status' => 0, 'error' => 'Could not connect', 'error_code' => 'network', 'body' => null];
        $key = $opts['idempotency_key'] ?? '';
        if ($key !== '' && isset($this->keys[$key])) return ['ok' => true, 'status' => 200, 'body' => $this->intents[$this->keys[$key]] ?? ['id' => $this->keys[$key]]];

        if ($method === 'POST' && $path === '/v1/customers') {
            $id = 'cus_' . ++$this->n;
            if ($key !== '') $this->keys[$key] = $id;
            return ['ok' => true, 'status' => 200, 'body' => ['id' => $id]];
        }
        if ($method === 'POST' && $path === '/v1/payment_intents') {
            $id = 'pi_' . ++$this->n;
            $pi = ['id' => $id, 'object' => 'payment_intent', 'amount' => $params['amount'], 'currency' => $params['currency'], 'customer' => $params['customer'],
                'metadata' => $params['metadata'], 'setup_future_usage' => $params['setup_future_usage'] ?? null, 'livemode' => $this->liveMode, 'created' => $this->now,
                'client_secret' => $id . '_secret_x', 'status' => 'requires_payment_method', 'payment_method' => $params['payment_method'] ?? null, 'last_payment_error' => null];
            if (($params['confirm'] ?? '') === 'true') {
                if ($this->decline !== '') {
                    $pi['last_payment_error'] = ['code' => 'card_declined', 'decline_code' => $this->decline, 'message' => 'Your card was declined.'];
                    $this->intents[$id] = $pi;
                    if ($key !== '') $this->keys[$key] = $id;
                    return ['ok' => false, 'status' => 402, 'error' => 'Your card was declined.', 'error_code' => 'card_declined',
                        'body' => ['error' => ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => $this->decline, 'message' => 'Your card was declined.', 'payment_intent' => $pi]]];
                }
                $pi['status'] = 'succeeded';
                $pi['amount_received'] = $pi['amount'];
            }
            $this->intents[$id] = $pi;
            if ($key !== '') $this->keys[$key] = $id;
            return ['ok' => true, 'status' => 200, 'body' => $pi];
        }
        if (preg_match('~^/v1/payment_intents/(pi_\d+)(/cancel)?$~', $path, $m)) {
            $pi = &$this->intents[$m[1]];
            if (($m[2] ?? '') === '/cancel') $pi['status'] = 'canceled';
            elseif ($method === 'POST') foreach ($params as $k => $v) $pi[$k] = $v === '' ? null : $v;
            return ['ok' => true, 'status' => 200, 'body' => $pi];
        }
        if (preg_match('~^/v1/payment_methods/(pm_\w+)(/detach)?$~', $path, $m)) {
            if (($m[2] ?? '') === '/detach' && $this->detachError !== '') return ['ok' => false, 'status' => 400, 'error' => 'Detach failed', 'error_code' => $this->detachError, 'body' => []];
            return ['ok' => true, 'status' => 200, 'body' => ['id' => $m[1], 'type' => 'card', 'customer' => ($m[2] ?? '') ? null : 'cus_1',
                'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]]];
        }
        return ['ok' => false, 'status' => 404, 'error' => 'Unhandled fixture request: ' . $method . ' ' . $path, 'error_code' => 'resource_missing', 'body' => []];
    }

    /** The customer completes the card form (3DS passed). */
    public function succeed(string $id, string $pm = 'pm_card1'): array
    {
        $this->intents[$id]['status'] = 'succeeded';
        $this->intents[$id]['payment_method'] = $pm;
        $this->intents[$id]['amount_received'] = $this->intents[$id]['amount'];
        return $this->intents[$id];
    }

    /** @return list<array{0:string,1:string,2:array,3:array}> */
    public function callsTo(string $method, string $pathPrefix): array
    {
        // Creating POST counts exclude intent updates/cancellation on child paths.
        $exact = $method === 'POST' && in_array($pathPrefix, ['/v1/customers', '/v1/payment_intents'], true);
        return array_values(array_filter($this->calls, static fn($c) => $c[0] === $method && ($exact ? $c[1] === $pathPrefix : str_starts_with($c[1], $pathPrefix))));
    }
}

final class InvoicePaymentTest extends TestCase
{
    private InvoicePaymentStore $db;
    private InvoicePaymentService $service;
    private FakeInvoiceStripe $stripe;
    private int $now;
    /** @var list<array{0:string,1:string,2:string,3:int}> */
    private array $alerts = [];
    private ?string $schema = null;

    protected function setUp(): void
    {
        $this->now = strtotime('2026-10-07 10:00:00 Europe/Copenhagen');
        $dsn = getenv('SALDI_STRIPE_TEST_DSN');
        $pdo = new PDO($dsn ?: 'sqlite::memory:', getenv('SALDI_STRIPE_TEST_USER') ?: null, getenv('SALDI_STRIPE_TEST_PASSWORD') ?: null);
        if ($dsn) {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') throw new RuntimeException('Integration tests require PostgreSQL');
            $this->schema = 'stripe_test_' . bin2hex(random_bytes(8));
            $pdo->exec('CREATE SCHEMA ' . $this->schema);
            $pdo->exec('SET search_path TO ' . $this->schema);
        }
        $this->db = new InvoicePaymentStore($pdo, fn() => $this->now);
        foreach (InvoicePaymentSchema::statements($this->db->driver()) as $s) $pdo->exec($s);
        // Run twice: the DDL must be idempotent (betweenUpdates runs it on every login).
        foreach (InvoicePaymentSchema::statements($this->db->driver()) as $s) $pdo->exec($s);
        self::fixture($pdo);
        foreach (['mode' => 'test', 'invoice_pay_enabled' => 'on', 'autopay_enabled' => 'on', 'posting_enabled' => 'off', 'clearing_account' => '3000'] as $k => $v) {
            $this->db->query("INSERT INTO settings (var_grp,var_name,var_value) VALUES ('stripe',?,?)", [$k, $v]);
        }
        $this->stripe = new FakeInvoiceStripe($this->now);
        $this->service = new InvoicePaymentService($this->db, [$this->stripe, 'transport'], new InvoicePaymentLedger($this->db),
            function (string $subject, string $body, string $key, int $throttle) { $this->alerts[] = [$subject, $body, $key, $throttle]; }, 'saldi_test');
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->db)) {
                if ($this->db->inTransaction()) {
                    $this->db->pdo->rollBack();
                }
                if ($this->schema !== null) {
                    $this->db->pdo->exec('DROP SCHEMA ' . $this->schema . ' CASCADE');
                }
            }
        } finally {
            // 20261012 CL/LH Retained test cases and their bound callbacks must not retain PostgreSQL clients.
            unset($this->service, $this->stripe, $this->db);
            $this->schema = null;
            $this->alerts = [];
        }
    }

    public function testFixtureTearDownReleasesConnectionWhileTestCaseIsRetained(): void
    {
        $fixture = new self(__FUNCTION__);
        try {
            $fixture->setUp();
            $connection = WeakReference::create($fixture->db->pdo);
            $store = WeakReference::create($fixture->db);
            $service = WeakReference::create($fixture->service);
        } finally {
            $fixture->tearDown();
        }
        $this->assertNull($connection->get(), 'A retained test case must release its PDO without waiting for cycle collection');
        $this->assertNull($store->get());
        $this->assertNull($service->get());
    }

    public function testFixtureTearDownRollsBackUnfinishedTransactionAndReleasesConnection(): void
    {
        $fixture = new self(__FUNCTION__);
        try {
            $fixture->setUp();
            $pdo = $fixture->db->pdo;
            $connection = WeakReference::create($pdo);
            $schema = $fixture->schema;
            $pdo->beginTransaction();
            $pdo->exec("UPDATE settings SET var_value='off' WHERE var_name='invoice_pay_enabled'");
        } finally {
            $fixture->tearDown();
        }
        $this->assertFalse($pdo->inTransaction());
        if ($schema !== null) {
            $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');
            $query->execute([$schema]);
            $this->assertSame(0, (int)$query->fetchColumn());
            unset($query);
        }
        unset($pdo);
        $this->assertNull($connection->get());
    }

    public function testFixtureTearDownAcceptsUninitializedFixture(): void
    {
        $fixture = new self(__FUNCTION__);
        $fixture->tearDown();
        $this->assertFalse(isset($fixture->db));
        $this->assertFalse(isset($fixture->service));
        $this->assertFalse(isset($fixture->stripe));
        $this->assertNull($fixture->schema);
    }

    private static function fixture(PDO $pdo): void
    {
        $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $real = $pg ? self::productionSchema() : [];
        foreach ([
            'CREATE TABLE settings (id INTEGER PRIMARY KEY,var_grp TEXT,var_name TEXT,var_value TEXT)',
            'CREATE TABLE adresser (id INTEGER PRIMARY KEY,kontonr TEXT,firmanavn TEXT,addr1 TEXT,postnr TEXT,bynavn TEXT,cvrnr TEXT,email TEXT,bank_reg TEXT,bank_konto TEXT,pbs TEXT,stripe_fravalg TEXT,art TEXT,gruppe INTEGER)',
            'CREATE TABLE ordrer (id INTEGER PRIMARY KEY,konto_id INTEGER,kontonr TEXT,firmanavn TEXT,addr1 TEXT,postnr TEXT,bynavn TEXT,email TEXT,cvrnr TEXT,art TEXT,valuta TEXT,status INTEGER,ordrenr INTEGER,fakturanr TEXT,fakturadate TEXT,due_date TEXT,sum NUMERIC,moms NUMERIC,betalingsbet TEXT,betalingsdage INTEGER,betalt TEXT)',
            'CREATE TABLE ordrelinjer (id INTEGER PRIMARY KEY,ordre_id INTEGER,posnr INTEGER,varenr TEXT,beskrivelse TEXT,enhed TEXT,pris NUMERIC,antal NUMERIC,rabat NUMERIC)',
            'CREATE TABLE openpost (id INTEGER PRIMARY KEY,konto_id INTEGER,konto_nr TEXT,faktnr TEXT,amount NUMERIC,refnr INTEGER,beskrivelse TEXT,udlignet TEXT,transdate TEXT,uxtid TEXT,kladde_id INTEGER,valuta TEXT,valutakurs NUMERIC,forfaldsdate TEXT,betalings_id TEXT,udlign_id INTEGER,udlign_date TEXT)',
            'CREATE TABLE grupper (id INTEGER PRIMARY KEY,art TEXT,kodenr INTEGER,fiscal_year INTEGER,box1 TEXT,box2 TEXT,box3 TEXT,box4 TEXT,box5 TEXT)',
            'CREATE TABLE kontoplan (id INTEGER PRIMARY KEY,kontonr NUMERIC,regnskabsaar INTEGER,kontotype TEXT,lukket TEXT,saldo NUMERIC,fra_kto NUMERIC)',
            'CREATE TABLE transaktioner (id INTEGER PRIMARY KEY,kontonr NUMERIC,bilag NUMERIC,transdate TEXT,logdate TEXT,logtime TEXT,beskrivelse TEXT,debet NUMERIC,kredit NUMERIC,faktura TEXT,kladde_id INTEGER,projekt TEXT,ansat INTEGER,afd INTEGER,ordre_id INTEGER,valuta TEXT,valutakurs NUMERIC,moms NUMERIC,adresser_id INTEGER,kasse_nr INTEGER)',
            'CREATE TABLE moms_periode_luk (kalender_aar INTEGER,kalender_maaned INTEGER,status TEXT)',
        ] as $sql) {
            if ($pg) {
                preg_match('/^CREATE TABLE (\w+)/', $sql, $m);
                $sql = isset($real[$m[1]]) && in_array($m[1], ['ordrer', 'ordrelinjer', 'openpost', 'transaktioner', 'kontoplan', 'grupper'], true)
                    ? $real[$m[1]] : str_replace('id INTEGER PRIMARY KEY', 'id SERIAL PRIMARY KEY', $sql);
            }
            $pdo->exec($sql);
        }
        foreach ([
            "INSERT INTO adresser (id,kontonr,firmanavn,email,stripe_fravalg,art,gruppe) VALUES (1,'1001','Kunde A/S','kunde@example.test','','D',1)",
            "INSERT INTO adresser (id,kontonr,firmanavn,addr1,postnr,bynavn,cvrnr,email,bank_reg,bank_konto,art) VALUES (2,'','Danosoft ApS','Vej 1','2100','København Ø','12345678','faktura@example.test','1234','0001234567','S')",
            "INSERT INTO grupper (art,kodenr,box1,box2,box3,box4,box5) VALUES ('RA',1,'1','2026','12','2026','on')",
            "INSERT INTO grupper (art,kodenr,fiscal_year,box2) VALUES ('DG',1,1,'7000')",
            "INSERT INTO kontoplan (kontonr,regnskabsaar,kontotype,lukket,saldo) VALUES (3000,1,'D','',0)",
            "INSERT INTO kontoplan (kontonr,regnskabsaar,kontotype,lukket,saldo) VALUES (7000,1,'D','',0)",
        ] as $sql) $pdo->exec($sql);
        self::addInvoice($pdo, 10, '1001', '2026-10-01', 8, 400.0);
    }

    /** A posted invoice with one line and its open item (amount incl. 25% VAT). */
    private static function addInvoice(PDO $pdo, int $id, string $nr, string $date, int $days, float $net, int $konto = 1): void
    {
        $gross = $net * 1.25;
        $due = (new DateTimeImmutable($date))->modify("+$days days")->format('Y-m-d');
        $pdo->prepare("INSERT INTO ordrer (id,konto_id,kontonr,firmanavn,email,art,valuta,status,ordrenr,fakturanr,fakturadate,sum,moms,betalingsbet,betalingsdage,betalt) VALUES (?,?,'1001','Kunde A/S','kunde@example.test','DO','DKK',3,?,?,?,?,?,'Netto',?,'')")
            ->execute([$id, $konto, $id, $nr, $date, $net, $gross - $net, $days]);
        $pdo->prepare("INSERT INTO ordrelinjer (ordre_id,posnr,varenr,beskrivelse,enhed,pris,antal,rabat) VALUES (?,1,'ABO','Abonnement','stk',?,1,0)")->execute([$id, $net]);
        $pdo->prepare("INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,valuta,valutakurs,forfaldsdate) VALUES (?,'1001',?,?,?,'Faktura','0',?,'DKK',100,?)")
            ->execute([$konto, $nr, $gross, $id, $date, $due]);
    }

    /** Read the fresh-install DDL from admin/opret.php without running the installer. */
    private static function productionSchema(): array
    {
        $ddl = [];
        $current = '';
        foreach (explode("\n", (string)file_get_contents(dirname(__DIR__, 2) . '/admin/opret.php')) as $line) {
            if (preg_match('/^\s*\$qtxt\s*=\s*"(CREATE TABLE [^"]*)";/', $line, $m)) $current = $m[1];
            elseif ($current && preg_match('/^\s*\$qtxt\s*\.=\s*"([^"]*)";/', $line, $m)) $current .= $m[1];
            elseif ($current && str_contains($line, 'db_modify($qtxt,')) {
                if (preg_match('/^CREATE TABLE (\w+)/', $current, $m)) $ddl[$m[1]] = str_replace(['$id_column', '$decimal_type'], ['id SERIAL', 'NUMERIC'], $current);
                $current = '';
            } elseif (preg_match('/^\s*db_modify\("(CREATE TABLE [^"]*)"/', $line, $m) && preg_match('/^CREATE TABLE (\w+)/', $m[1], $name)) {
                $ddl[$name[1]] = str_replace(['$id_column', '$decimal_type'], ['id SERIAL', 'NUMERIC'], $m[1]);
            }
        }
        return $ddl;
    }

    // ------------------------------------------------------------- helpers

    private function setting(string $key, string $value): void
    {
        $this->db->query("UPDATE settings SET var_value=? WHERE var_grp='stripe' AND var_name=?", [$value, $key]);
    }

    private function event(string $type, array $pi): string
    {
        // 20261012 CL/LH Real Stripe events always carry created; without it the service would treat the
        //                webhook like API recovery and fetch the charge time from Stripe.
        return $this->service->handleEvent(['type' => $type, 'created' => $this->now, 'data' => ['object' => $pi]]);
    }

    /** The customer pays invoice 10 on the hosted page and ticks "save card". */
    private function payAndSaveCard(int $orderId = 10): string
    {
        $start = $this->service->startPayment($orderId, true);
        $this->assertSame('succeeded', $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent'])));
        return $start['payment_intent'];
    }

    private function payments(int $orderId): array
    {
        return $this->db->rows('SELECT * FROM stripe_invoice_payments WHERE order_id=? ORDER BY id', [$orderId]);
    }

    private function alertSubjects(): array
    {
        return array_column($this->alerts, 0);
    }

    // --------------------------------------------------------------- read model

    public function testInvoiceReadModel(): void
    {
        $inv = $this->service->invoice(10);
        $this->assertTrue($inv['payable']);
        $this->assertSame('', $inv['reason']);
        $this->assertSame(50000, $inv['due']);
        $this->assertSame(40000, $inv['net']);
        $this->assertSame(10000, $inv['vat']);
        $this->assertSame('2026-10-09', $inv['dueDate']);
        $this->assertSame('Netto 8 dage', $inv['terms']);
        $this->assertSame('kunde@example.test', $inv['email']);
        $this->assertSame('Danosoft ApS', $inv['seller']['firmanavn']);
        $this->assertCount(1, $inv['lines']);
        $this->assertSame(40000, $inv['lines'][0]['total']);
        $this->assertNull($inv['card']);
    }

    public function testInvoiceIsNullForOtherDocuments(): void
    {
        $this->db->query("INSERT INTO ordrer (id,konto_id,art,status,fakturanr) VALUES (20,1,'DO',2,'')");
        $this->db->query("INSERT INTO ordrer (id,konto_id,art,status,fakturanr) VALUES (21,1,'KO',3,'5')");
        $this->assertNull($this->service->invoice(20));
        $this->assertNull($this->service->invoice(21));
        $this->assertNull($this->service->invoice(999));
    }

    public function testInvoiceReasons(): void
    {
        $this->setting('invoice_pay_enabled', 'off');
        $this->assertSame('disabled', $this->service->invoice(10)['reason']);
        $this->setting('invoice_pay_enabled', 'on');

        $this->db->query("UPDATE ordrer SET valuta='EUR' WHERE id=10");
        $this->assertSame('currency', $this->service->invoice(10)['reason']);
        $this->db->query("UPDATE ordrer SET valuta='' WHERE id=10");
        $this->assertTrue($this->service->invoice(10)['payable'], 'An empty currency is DKK');

        $this->db->query("UPDATE adresser SET stripe_fravalg='on' WHERE id=1");
        $this->assertSame('opted_out', $this->service->invoice(10)['reason']);
        $this->db->query("UPDATE adresser SET stripe_fravalg='' WHERE id=1");

        // Paid by bank transfer through the kassekladde: the open items are settled.
        $this->db->query("UPDATE openpost SET udlignet='1' WHERE faktnr='1001'");
        $this->db->query("UPDATE ordrer SET betalt='2026-10-05' WHERE id=10");
        $inv = $this->service->invoice(10);
        $this->assertSame('paid', $inv['reason']);
        $this->assertTrue($inv['paid']);

        // No open item and no payment mark: nothing to collect.
        $this->db->query("UPDATE ordrer SET betalt='' WHERE id=10");
        $this->assertSame('nothing_due', $this->service->invoice(10)['reason']);
    }

    public function testPartialBankPaymentLowersTheAmountDue(): void
    {
        $this->db->query("INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,refnr,udlignet,transdate) VALUES (1,'1001','1001',-100,0,'0','2026-10-03')");
        $this->assertSame(40000, $this->service->invoice(10)['due']);
    }

    // ------------------------------------------------------------------ pay now

    public function testStartPaymentReusesTheIntentAndTogglesCardSaving(): void
    {
        $a = $this->service->startPayment(10, false);
        $this->assertSame(50000, $a['amount']);
        $created = $this->stripe->callsTo('POST', '/v1/payment_intents');
        $this->assertCount(1, $created);
        $this->assertArrayNotHasKey('setup_future_usage', $created[0][2]);
        $this->assertSame(['card'], $created[0][2]['payment_method_types']);
        $this->assertSame('invoice_manual', $created[0][2]['metadata']['saldi_kind']);
        $this->assertSame('kunde@example.test', $created[0][2]['receipt_email']);

        $b = $this->service->startPayment(10, true);
        $this->assertSame($a['payment_intent'], $b['payment_intent'], 'The open intent is reused');
        $this->assertSame('off_session', $this->stripe->intents[$a['payment_intent']]['setup_future_usage']);
        $this->assertSame(1, (int)$this->payments(10)[0]['save_card']);

        $this->service->startPayment(10, false);
        $this->assertNull($this->stripe->intents[$a['payment_intent']]['setup_future_usage']);
        $this->assertCount(1, $this->stripe->callsTo('POST', '/v1/customers'));
        $this->assertCount(1, $this->payments(10));
    }

    public function testChangedAmountCancelsTheOldIntent(): void
    {
        $a = $this->service->startPayment(10, false);
        $this->db->query("INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,refnr,udlignet,transdate) VALUES (1,'1001','1001',-100,0,'0','2026-10-07')");
        $b = $this->service->startPayment(10, false);
        $this->assertNotSame($a['payment_intent'], $b['payment_intent']);
        $this->assertSame(40000, $b['amount']);
        $this->assertSame('canceled', $this->stripe->intents[$a['payment_intent']]['status']);
        $this->assertSame(['canceled', 'requires_payment_method'], array_column($this->payments(10), 'status'));
    }

    public function testStartPaymentRefusesAnUnpayableInvoice(): void
    {
        $this->db->query("UPDATE adresser SET stripe_fravalg='on' WHERE id=1");
        $this->expectException(RuntimeException::class);
        $this->service->startPayment(10, false);
    }

    public function testSucceededPaymentSavesTheCardAndAlertsTheBookkeeper(): void
    {
        $pi = $this->payAndSaveCard();
        $card = $this->service->card(1);
        $this->assertSame('pm_card1', $card['payment_method_id']);
        $this->assertSame('4242', $card['last4']);
        $this->assertSame(1, (int)$card['autopay']);
        $this->assertSame(10, (int)$card['consent_order_id']);

        $row = $this->payments(10)[0];
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame('manual', $row['settlement'], 'Test mode records a manual receipt without posting');
        $this->assertSame(['Testbetaling modtaget - ingen bogføring'], $this->alertSubjects());
        $this->assertStringContainsString('500,00 kr.', $this->alerts[0][1]);
        $this->assertSame('received', $this->service->invoice(10)['reason'], 'The page shows the payment before the bookkeeper posts it');

        // Stripe redelivers the webhook and the return page confirms the same intent.
        $this->assertSame('duplicate', $this->event('payment_intent.succeeded', $this->stripe->intents[$pi]));
        $this->assertSame('succeeded', $this->service->confirmReturn(10, $pi));
        $this->assertCount(1, $this->alerts);
        $this->assertCount(1, $this->stripe->callsTo('GET', '/v1/payment_methods/'));
    }

    public function testPaymentWithoutConsentSavesNoCard(): void
    {
        $start = $this->service->startPayment(10, false);
        $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent']));
        $this->assertNull($this->service->card(1));
    }

    public function testProcessingAndFailedHostedPaymentsKeepTheInvoiceOpen(): void
    {
        $start = $this->service->startPayment(10, false);
        $pi = $this->stripe->intents[$start['payment_intent']];
        $pi['status'] = 'processing';
        $this->assertSame('processing', $this->event('payment_intent.processing', $pi));
        $this->assertSame('in_flight', $this->service->invoice(10)['reason']);

        $pi['status'] = 'requires_payment_method';
        $pi['last_payment_error'] = ['code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'message' => 'Declined'];
        $this->assertSame('requires_payment_method', $this->event('payment_intent.payment_failed', $pi));
        $this->assertTrue($this->service->invoice(10)['payable'], 'The customer may try another card');
        $this->assertSame([], $this->alerts, 'A hosted-page decline is shown to the customer, not mailed');
    }

    public function testForeignEventsAreIgnored(): void
    {
        $start = $this->service->startPayment(10, false);
        $pi = $this->stripe->succeed($start['payment_intent']);
        $this->assertSame('ignored', $this->event('invoice.paid', $pi));
        $this->assertSame('ignored', $this->event('payment_intent.succeeded', ['metadata' => ['saldi_db' => 'other'] + $pi['metadata']] + $pi));
        $this->assertSame('ignored', $this->event('payment_intent.succeeded', ['metadata' => ['saldi_kind' => 'subscription'] + $pi['metadata']] + $pi));
        $this->assertSame('ignored', $this->event('payment_intent.succeeded', ['livemode' => true] + $pi));
        $this->assertSame('ignored', $this->event('payment_intent.succeeded', ['metadata' => []] + $pi));
        $this->assertSame('requires_payment_method', $this->payments(10)[0]['status']);
    }

    public function testConfirmReturnRejectsAnotherInvoicesIntent(): void
    {
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 8, 100.0);
        $start = $this->service->startPayment(11, false);
        $this->expectException(RuntimeException::class);
        $this->service->confirmReturn(10, $start['payment_intent']);
    }

    // ------------------------------------------------------------------ ledger

    public function testLiveModeWithoutPostingHandsReceiptToBookkeeper(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $start = $this->service->startPayment(10, false);
        $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent']));
        $this->assertSame('manual', $this->payments(10)[0]['settlement']);
        $this->assertSame(['Kortbetaling modtaget - bogfør i kassekladden'], $this->alertSubjects());
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM transaktioner')['n']);
    }

    public function testPostingEnabledPostsTheReceiptAndSettlesTheInvoice(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $this->setting('posting_enabled', 'on');
        $start = $this->service->startPayment(10, false);
        $pi = $this->stripe->succeed($start['payment_intent']);
        $this->assertSame('succeeded', $this->event('payment_intent.succeeded', $pi));

        $this->assertSame('posted', $this->payments(10)[0]['settlement']);
        $this->assertSame([], $this->alerts);
        $t = $this->db->rows('SELECT kontonr,debet,kredit FROM transaktioner ORDER BY id');
        $this->assertCount(2, $t);
        $this->assertSame([3000, 500.0, 0.0], [(int)$t[0]['kontonr'], (float)$t[0]['debet'], (float)$t[0]['kredit']]);
        $this->assertSame([7000, 0.0, 500.0], [(int)$t[1]['kontonr'], (float)$t[1]['debet'], (float)$t[1]['kredit']]);
        $this->assertSame(0, (int)$this->db->row("SELECT COUNT(*) AS n FROM openpost WHERE faktnr='1001' AND udlignet<>'1'")['n']);
        $this->assertSame('2026-10-07', $this->db->row('SELECT betalt FROM ordrer WHERE id=10')['betalt']);
        $this->assertSame(500.0, (float)$this->db->row('SELECT saldo FROM kontoplan WHERE kontonr=3000')['saldo']);
        $this->assertSame('paid', $this->service->invoice(10)['reason']);

        $this->assertSame('duplicate', $this->event('payment_intent.succeeded', $pi));
        $this->assertSame(2, (int)$this->db->row('SELECT COUNT(*) AS n FROM transaktioner')['n']);
    }

    public function testPostingEnabledAcceptsTrimmedLowercaseDkkInvoiceCurrency(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $this->setting('posting_enabled', 'on');
        $this->db->query('UPDATE ordrer SET valuta=? WHERE id=10', [' dkk']);
        $start = $this->service->startPayment(10, false);
        $this->assertSame('succeeded', $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent'])));

        $this->assertSame('posted', $this->payments(10)[0]['settlement']);
        $this->assertSame([], $this->alerts);
        $transactions = $this->db->rows('SELECT kontonr,debet,kredit FROM transaktioner ORDER BY id');
        $this->assertCount(2, $transactions);
        $this->assertSame([3000, 500.0, 0.0], [(int)$transactions[0]['kontonr'], (float)$transactions[0]['debet'], (float)$transactions[0]['kredit']]);
        $this->assertSame([7000, 0.0, 500.0], [(int)$transactions[1]['kontonr'], (float)$transactions[1]['debet'], (float)$transactions[1]['kredit']]);
        $this->assertSame(0, (int)$this->db->row("SELECT COUNT(*) AS n FROM openpost WHERE faktnr='1001' AND udlignet<>'1'")['n']);
        $this->assertSame('paid', $this->service->invoice(10)['reason']);
    }

    public function testPostingFailureIsRetriedBySettlePending(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $this->setting('posting_enabled', 'on');
        $this->setting('clearing_account', '3100');
        $start = $this->service->startPayment(10, false);
        $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent']));
        $row = $this->payments(10)[0];
        $this->assertSame('succeeded', $row['status'], 'A posting error never loses the payment');
        $this->assertSame('error', $row['settlement']);
        $this->assertSame(['Kortbetaling kunne ikke bogføres'], $this->alertSubjects());
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM transaktioner')['n']);
        $this->assertSame('received', $this->service->invoice(10)['reason']);

        $this->db->query("INSERT INTO kontoplan (kontonr,regnskabsaar,kontotype,lukket,saldo) VALUES (3100,1,'D','',0)");
        $this->assertSame(1, $this->service->settlePending());
        $this->assertSame('posted', $this->payments(10)[0]['settlement']);
        $this->assertSame(0, $this->service->settlePending());
    }

    public function testClosedFiscalYearBlocksPosting(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $this->setting('posting_enabled', 'on');
        $this->db->query("UPDATE grupper SET box5='' WHERE art='RA'");
        $start = $this->service->startPayment(10, false);
        $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent']));
        $this->assertSame('error', $this->payments(10)[0]['settlement']);
        $this->assertStringContainsString('Regnskabsåret er lukket', $this->alerts[0][1]);
    }

    // ----------------------------------------------------------------- autopay

    public function testAutopayChargesALaterInvoiceOnItsDueDate(): void
    {
        $this->payAndSaveCard();
        $this->alerts = [];
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 3, 200.0);

        $this->assertSame(['charged' => 0, 'failed' => 0, 'skipped' => 0], $this->service->autopay(), 'Not due before 10-10');
        $this->now += 3 * 86400;
        $this->stripe->now = $this->now;
        $this->assertSame(['charged' => 1, 'failed' => 0, 'skipped' => 0], $this->service->autopay());

        $charge = $this->stripe->callsTo('POST', '/v1/payment_intents');
        $charge = end($charge);
        $this->assertSame(25000, $charge[2]['amount']);
        $this->assertSame('pm_card1', $charge[2]['payment_method']);
        $this->assertSame('true', $charge[2]['off_session']);
        $this->assertSame('invoice_auto', $charge[2]['metadata']['saldi_kind']);
        $this->assertSame('saldi-' . hash('sha256', 'saldi_test') . '-test-intent_create-' . $this->payments(11)[0]['id'], $charge[3]['idempotency_key']);
        $row = $this->payments(11)[0];
        $this->assertSame(['auto', 'succeeded', 'manual'], [$row['kind'], $row['status'], $row['settlement']]);
        $this->assertSame(['Testbetaling modtaget - ingen bogføring'], $this->alertSubjects());

        // The webhook for the same charge arrives afterwards; the next run finds nothing to do.
        $this->assertSame('duplicate', $this->event('payment_intent.succeeded', $this->stripe->intents[$row['payment_intent_id']]));
        $this->assertSame(['charged' => 0, 'failed' => 0, 'skipped' => 0], $this->service->autopay());
    }

    public function testAutopayIgnoresInvoicesFromBeforeConsent(): void
    {
        self::addInvoice($this->db->pdo, 11, '1002', '2026-09-20', 8, 200.0);
        $this->payAndSaveCard();
        $this->assertSame(['charged' => 0, 'failed' => 0, 'skipped' => 0], $this->service->autopay());
        $this->assertSame([], $this->payments(11));
    }

    public function testAutopayOffChargesNothing(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->setting('autopay_enabled', 'off');
        $this->service->autopay();
        $this->assertSame([], $this->payments(11));
    }

    public function testOptedOutDebtorIsNotCharged(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->db->query("UPDATE adresser SET stripe_fravalg='on' WHERE id=1");
        $this->service->autopay();
        $this->assertSame([], $this->payments(11));
    }

    public function testDeclineIsRetriedThreeTimesThreeDaysApart(): void
    {
        $this->payAndSaveCard();
        $this->alerts = [];
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->stripe->decline = 'insufficient_funds';

        $this->assertSame(1, $this->service->autopay()['failed']);
        $this->assertStringContainsString('Forsøg 1 af 3', $this->alerts[0][1]);
        $this->assertStringContainsString('forsøges igen om 3 dage', $this->alerts[0][1]);
        $failed = $this->payments(11)[0];
        $this->assertSame(['failed', 'insufficient_funds'], [$failed['status'], $failed['error_code']]);
        $this->assertNotNull($failed['payment_intent_id']);

        // Stripe's payment_failed webhook for the same intent adds no second alert.
        $this->assertSame('failed', $this->event('payment_intent.payment_failed', $this->stripe->intents[$failed['payment_intent_id']]));
        $this->assertCount(1, $this->alerts);

        $this->service->autopay();
        $this->assertCount(1, $this->payments(11), 'No retry within three days');
        foreach ([2, 3] as $attempt) {
            $this->now += 3 * 86400;
            $this->service->autopay();
            $this->assertCount($attempt, $this->payments(11));
        }
        $this->assertStringContainsString('Der forsøges ikke igen', end($this->alerts)[1]);
        $this->now += 3 * 86400;
        $this->service->autopay();
        $this->assertCount(3, $this->payments(11), 'Stops after three attempts');
        $this->assertNull($this->service->card(1)['broken_at'], 'A soft decline keeps the card');
    }

    public function testHardDeclineMarksTheCardBroken(): void
    {
        $this->payAndSaveCard();
        $this->alerts = [];
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->stripe->decline = 'expired_card';
        $this->assertSame(1, $this->service->autopay()['failed']);
        $card = $this->service->card(1);
        $this->assertSame('expired_card', $card['broken_reason']);
        $this->assertStringContainsString('kan ikke trækkes automatisk igen', $this->alerts[0][1]);

        $this->now += 3 * 86400;
        $this->stripe->decline = '';
        $this->service->autopay();
        $this->assertCount(1, $this->payments(11), 'A broken card is not charged again');

        // Paying on the hosted page with "save card" again repairs it.
        $this->payAndSaveCard(11);
        $this->assertNull($this->service->card(1)['broken_at']);
    }

    public function testNetworkFailureResendsWithTheSameIdempotencyKey(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->stripe->networkDown = true;
        $this->assertSame(['charged' => 0, 'failed' => 0, 'skipped' => 1], $this->service->autopay());
        $row = $this->payments(11)[0];
        $this->assertSame(['created', null], [$row['status'], $row['payment_intent_id']]);
        $this->assertSame('in_flight', $this->service->invoice(11)['reason'], 'The hosted page must not take a second payment');

        $this->stripe->networkDown = false;
        $this->service->autopay();
        $this->assertSame('created', $this->payments(11)[0]['status'], 'Waits ten minutes before resending');

        $this->now += InvoicePaymentService::RESEND_SECONDS;
        $this->assertSame(1, $this->service->autopay()['charged']);
        $rows = $this->payments(11);
        $this->assertCount(1, $rows);
        $this->assertSame('succeeded', $rows[0]['status']);
        $keys = array_map(static fn($c) => $c[3]['idempotency_key'] ?? '', $this->stripe->callsTo('POST', '/v1/payment_intents'));
        $this->assertSame(2, count(array_keys($keys, 'saldi-' . hash('sha256', 'saldi_test') . '-test-intent_create-' . $row['id'], true)));
    }

    public function testAutopayCancelsAnOpenHostedPayment(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $open = $this->service->startPayment(11, false);
        $this->assertSame(1, $this->service->autopay()['charged']);
        $this->assertSame('canceled', $this->stripe->intents[$open['payment_intent']]['status']);
        $this->assertSame(['canceled', 'succeeded'], array_column($this->payments(11), 'status'));
    }

    public function testCreditItemsSkipAutopay(): void
    {
        $this->payAndSaveCard();
        $this->alerts = [];
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->db->query("INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,refnr,udlignet,transdate) VALUES (1,'1001','K1',-100,0,'0','2026-10-07')");
        $this->assertSame(['charged' => 0, 'failed' => 0, 'skipped' => 1], $this->service->autopay());
        $this->assertSame(['Automatisk kortbetaling sprunget over'], $this->alertSubjects());
        $this->assertSame(15000, $this->service->invoice(11)['customerBalance'], 'Invoice 10 was paid by card but not posted yet');
        $this->assertSame([], $this->payments(11));
    }

    /** @return array<string,array{0:string}> */
    public static function collectionBlockCases(): array
    {
        return ['PBS' => ['pbs'], 'subscriber' => ['subscription'], 'optout' => ['optout'], 'disabled' => ['disabled']];
    }

    private function blockDebtor(string $reason): void
    {
        if ($reason === 'pbs') $this->db->query("UPDATE adresser SET pbs=' ON ' WHERE id=1");
        elseif ($reason === 'subscription') {
            $this->db->query('CREATE TABLE stripe_customers (konto_id INTEGER,status VARCHAR(30))');
            $this->db->query("INSERT INTO stripe_customers (konto_id,status) VALUES (1,'active')");
        } elseif ($reason === 'optout') $this->db->query("UPDATE adresser SET stripe_fravalg='on' WHERE id=1");
        elseif ($reason === 'disabled') $this->setting('invoice_pay_enabled', 'off');
    }

    /** @dataProvider collectionBlockCases */
    #[DataProvider('collectionBlockCases')]
    public function testStartPaymentRefusesEveryCollectionBlock(string $reason): void
    {
        $this->blockDebtor($reason);
        $messages = [
            'pbs' => 'Denne faktura opkræves via Betalingsservice.',
            'subscription' => 'Denne debitor betaler allerede via et Stripe-abonnement.',
            'optout' => 'Kortbetaling er fravalgt for denne debitor.',
            'disabled' => 'Kortbetaling af fakturaer er slået fra.',
        ];
        try {
            $this->service->startPayment(10, true);
            $this->fail('Blocked collection must be refused');
        } catch (RuntimeException $e) {
            $this->assertSame($messages[$reason], $e->getMessage());
        }
        $this->assertSame([], $this->stripe->calls);
        $this->assertSame([], $this->payments(10));
        $this->assertSame($reason, $this->service->collectionBlock(1));
    }

    /** @dataProvider collectionBlockCases */
    #[DataProvider('collectionBlockCases')]
    public function testCollectionChangeDuringPaymentDoesNotActivateAutopay(string $reason): void
    {
        $start = $this->service->startPayment(10, true);
        $this->blockDebtor($reason);
        $this->assertSame('succeeded', $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent'])));
        $this->assertNull($this->service->card(1));
        $this->assertSame('manual', $this->payments(10)[0]['settlement']);
    }

    /** @dataProvider collectionBlockCases */
    #[DataProvider('collectionBlockCases')]
    public function testWorkerSkipsBlockedDebtorAndAlertsOncePerDay(string $reason): void
    {
        $this->payAndSaveCard();
        $this->alerts = [];
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        self::addInvoice($this->db->pdo, 12, '1003', '2026-10-07', 0, 200.0);
        $this->blockDebtor($reason);
        $calls = count($this->stripe->calls);
        $this->assertSame(['charged' => 0, 'failed' => 0, 'skipped' => 1], $this->service->autopay());
        $this->service->autopay();
        $this->assertSame($calls, count($this->stripe->calls));
        $this->assertSame([], $this->payments(11));
        $this->assertSame([], $this->payments(12));
        $this->assertCount(1, $this->alerts);
        // A new service (another cron process) shares the persisted throttle.
        $worker = new InvoicePaymentService($this->db, [$this->stripe, 'transport'], new InvoicePaymentLedger($this->db),
            function ($subject, $body, $key, $throttle) { $this->alerts[] = [$subject, $body, $key, $throttle]; }, 'saldi_test');
        $worker->autopay();
        $this->assertCount(1, $this->alerts);
        $this->now += 86400;
        $worker->autopay();
        $this->assertCount(2, $this->alerts);
    }

    public function testDirectChargeRefusesStaleCardAfterCollectionChanges(): void
    {
        $this->payAndSaveCard();
        $card = $this->service->card(1);
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->db->query("UPDATE adresser SET pbs='on' WHERE id=1");
        $calls = count($this->stripe->calls);
        $this->assertSame('skipped', $this->service->chargeOffSession($card, 11));
        $this->assertSame($calls, count($this->stripe->calls));
        $this->assertSame([], $this->payments(11));
    }

    public function testAutopayBlockReportsCardStatesAndSwitch(): void
    {
        $this->assertSame('no_card', $this->service->autopayBlock(1));
        $this->payAndSaveCard();
        $this->assertNull($this->service->autopayBlock(1));
        $this->db->query('UPDATE stripe_invoice_cards SET broken_at=? WHERE konto_id=1', [$this->now]);
        $this->assertSame('card_broken', $this->service->autopayBlock(1));
        $this->db->query('UPDATE stripe_invoice_cards SET broken_at=NULL,revoked_at=? WHERE konto_id=1', [$this->now]);
        $this->assertSame('card_revoked', $this->service->autopayBlock(1));
        $this->setting('autopay_enabled', 'off');
        $this->assertSame('autopay_disabled', $this->service->autopayBlock(1));
    }

    /** @return array<string,array{0:string}> */
    public static function postingSwitchCases(): array
    {
        return ['posting on' => ['on'], 'posting off' => ['off']];
    }

    /** @dataProvider postingSwitchCases */
    #[DataProvider('postingSwitchCases')]
    public function testTestModeNeverPostsAndAlertsOnlyOnce(string $posting): void
    {
        $this->setting('posting_enabled', $posting);
        $before = [];
        foreach (['ordrer', 'openpost', 'transaktioner', 'kontoplan'] as $table) $before[$table] = $this->db->rows('SELECT * FROM ' . $table . ' ORDER BY id');
        $start = $this->service->startPayment(10, false);
        $pi = $this->stripe->succeed($start['payment_intent']);
        $this->event('payment_intent.succeeded', $pi);
        $this->event('payment_intent.succeeded', $pi);
        $this->service->settlePending();
        // Recover a formerly error/pending settlement through the same mode guard.
        $this->db->query("UPDATE stripe_invoice_payments SET settlement='error' WHERE order_id=10");
        $this->service->settlePending();
        $row = $this->payments(10)[0];
        $this->assertSame('manual', $row['settlement']);
        $this->assertSame('test_mode', $row['settle_error']);
        foreach ($before as $table => $rows) $this->assertSame($rows, $this->db->rows('SELECT * FROM ' . $table . ' ORDER BY id'), $table);
        $this->assertCount(1, $this->alerts);
        $this->assertSame(['Testbetaling modtaget - ingen bogføring'], $this->alertSubjects());
    }

    public function testDirectLedgerReceiptCannotPostTestMoney(): void
    {
        $this->setting('posting_enabled', 'on');
        $ledger = new InvoicePaymentLedger($this->db);
        $this->assertFalse($ledger->enabled());
        try {
            $ledger->receipt(10, 'pi_test', 50000, '2026-10-07');
            $this->fail('Test money must never be posted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('slået fra', $e->getMessage());
        }
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM transaktioner')['n']);
        $this->assertSame(1, (int)$this->db->row('SELECT COUNT(*) AS n FROM openpost')['n']);
        $this->assertSame('', $this->db->row('SELECT betalt FROM ordrer WHERE id=10')['betalt']);
    }

    // ------------------------------------------------------------------ remove

    public function testRemoveCardStopsAutopayAndDetaches(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->assertTrue($this->service->removeCard(1, 'kunde'));
        $this->assertCount(1, $this->stripe->callsTo('POST', '/v1/payment_methods/pm_card1/detach'));
        $this->assertNull($this->service->card(1));
        $this->assertSame('kunde', $this->db->row('SELECT revoked_by FROM stripe_invoice_cards WHERE konto_id=1')['revoked_by']);
        $this->service->autopay();
        $this->assertSame([], $this->payments(11));
        $this->assertFalse($this->service->removeCard(1, 'kunde'));
        $this->assertSame('card_removed', $this->db->row("SELECT action FROM stripe_invoice_audit WHERE entity_type='card' ORDER BY id DESC LIMIT 1")['action']);
    }

    public function testDetachFailureAlertsButStillStopsAutopay(): void
    {
        $this->payAndSaveCard();
        $this->alerts = [];
        $this->stripe->detachError = 'api_error';
        $this->assertTrue($this->service->removeCard(1, 'Saldi: admin'));
        $this->assertNull($this->service->card(1));
        $this->assertSame(['Kort kunne ikke fjernes hos Stripe'], $this->alertSubjects());

        $this->stripe->detachError = 'resource_missing';
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 8, 200.0);
        $this->payAndSaveCard(11);
        $this->alerts = [];
        $this->service->removeCard(1, 'kunde');
        $this->assertSame([], $this->alerts, 'An already detached card is not an error');
    }

    public function testCardRemovedDuringPaymentIsNotSavedAgain(): void
    {
        $this->payAndSaveCard();
        $this->now += 60;
        $this->stripe->now = $this->now;
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 8, 200.0);
        $start = $this->service->startPayment(11, true);
        $this->now += 60;
        $this->service->removeCard(1, 'kunde');
        $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent'], 'pm_card2'));
        $this->assertNull($this->service->card(1));
    }
    // ------------------------------------------------------- Slice B transitions

    private function eventAt(string $type, array $pi, int $created): string
    {
        return $this->service->handleEvent(['id' => 'evt_transition', 'type' => $type, 'created' => $created, 'data' => ['object' => $pi]]);
    }

    public function testEventFactsUseStripeTimeAndSuccessIsTerminal(): void
    {
        $start = $this->service->startPayment(10, false);
        $pi = $this->stripe->intents[$start['payment_intent']];
        $pi['last_payment_error'] = ['code' => 'card_declined', 'decline_code' => 'insufficient_funds'];
        $this->eventAt('payment_intent.payment_failed', $pi, $this->now - 120);
        $this->assertSame($this->now - 120, (int)$this->payments(10)[0]['failed_at']);
        $this->assertNull($this->payments(10)[0]['received_at']);
        $this->eventAt('payment_intent.payment_failed', $pi, $this->now - 60);
        $this->assertSame($this->now - 60, (int)$this->payments(10)[0]['failed_at']);
        $success = $this->stripe->succeed($start['payment_intent']);
        $this->assertSame('succeeded', $this->eventAt('payment_intent.succeeded', $success, $this->now - 90), 'Success wins even over a newer failure snapshot');
        $row = $this->payments(10)[0];
        $this->assertSame($this->now - 90, (int)$row['received_at']);
        $this->assertSame($this->now - 90, (int)$row['event_created']);
        $this->assertSame('pm_card1', $row['payment_method']);
        $this->assertSame('duplicate', $this->eventAt('payment_intent.payment_failed', $pi, $this->now));
        $this->assertSame($row, $this->payments(10)[0]);
        $this->assertCount(1, $this->alerts);
    }

    public function testStaleProcessingCannotRestoreCanceledOrFailedPayments(): void
    {
        $start = $this->service->startPayment(10, false);
        $pi = $this->stripe->intents[$start['payment_intent']];
        $pi['status'] = 'canceled';
        $this->assertSame('canceled', $this->eventAt('payment_intent.canceled', $pi, $this->now - 20));
        $pi['status'] = 'processing';
        $this->assertSame('stale', $this->eventAt('payment_intent.processing', $pi, $this->now - 30));
        $this->assertTrue($this->service->invoice(10)['payable']);
        $pi['status'] = 'requires_payment_method';
        $pi['last_payment_error'] = ['code' => 'card_declined', 'decline_code' => 'insufficient_funds'];
        $this->eventAt('payment_intent.payment_failed', $pi, $this->now - 10);
        $failed = $this->payments(10)[0];
        $pi['status'] = 'processing';
        $pi['last_payment_error'] = null;
        $this->assertSame('stale', $this->eventAt('payment_intent.processing', $pi, $this->now - 15));
        $this->assertSame($failed, $this->payments(10)[0]);
        $this->assertSame('processing', $this->eventAt('payment_intent.processing', $pi, $this->now));
        $this->assertSame('in_flight', $this->service->invoice(10)['reason']);
    }

    public function testIdentityMismatchIsRejectedBeforeReceiptOrCardWrites(): void
    {
        $start = $this->service->startPayment(10, true);
        $good = $this->stripe->succeed($start['payment_intent']);
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 400.0);
        $wrongOrder = $good;
        $wrongOrder['metadata']['saldi_order_id'] = '11';
        $wrongDebtor = $good;
        $wrongDebtor['metadata']['saldi_konto_id'] = '2';
        $wrongKind = $good;
        $wrongKind['metadata']['saldi_kind'] = 'invoice_auto';
        $before = $this->payments(10)[0];
        foreach ([$wrongOrder, $wrongDebtor, $wrongKind, ['currency' => 'eur'] + $good,
            ['amount' => 1] + $good, ['amount_received' => 1] + $good, ['customer' => 'cus_foreign'] + $good] as $invalid) {
            try {
                $this->eventAt('payment_intent.succeeded', $invalid, $this->now);
                $this->fail('An identity or money mismatch must be rejected');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('matcher ikke', $e->getMessage());
            }
            $this->assertSame($before, $this->payments(10)[0]);
            $this->assertSame([], $this->payments(11));
            $this->assertNull($this->service->card(1));
            $this->assertSame([], $this->alerts);
        }
        $this->assertSame('ignored', $this->eventAt('payment_intent.succeeded', ['metadata' => ['saldi_db' => 'other'] + $good['metadata']] + $good, $this->now));
    }

    public function testReceiptIdentityUsesStoredAmountAfterBankBalanceChanges(): void
    {
        $start = $this->service->startPayment(10, false);
        $this->db->query("UPDATE openpost SET amount=400 WHERE faktnr='1001'");
        $this->assertSame('succeeded', $this->eventAt('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent']), $this->now - 30));
        $this->assertSame(50000, (int)$this->payments(10)[0]['amount_ore']);
    }

    /** @return array<string,mixed> An ambiguous automatic attempt waiting for its webhook. */
    private function delayedAutomaticIntent(): array
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->stripe->networkDown = true;
        $this->service->autopay();
        $this->stripe->networkDown = false;
        $row = $this->payments(11)[0];
        $card = $this->service->card(1);
        $this->alerts = [];
        return ['id' => 'pi_delayed', 'status' => 'requires_payment_method', 'amount' => 25000, 'currency' => 'dkk',
            'customer' => $card['customer_id'], 'payment_method' => $card['payment_method_id'], 'livemode' => false, 'created' => $this->now,
            'metadata' => ['saldi_db' => 'saldi_test', 'saldi_kind' => 'invoice_auto', 'saldi_order_id' => '11', 'saldi_konto_id' => '1', 'saldi_payment_row' => (string)$row['id'], 'saldi_card_generation' => (string)$card['generation']],
            'last_payment_error' => ['code' => 'card_declined', 'decline_code' => 'expired_card', 'message' => 'Expired']];
    }

    public function testDelayedDeclineBreaksOnlyTheMatchingMethodAndGeneration(): void
    {
        $pi = $this->delayedAutomaticIntent();
        $this->db->query("UPDATE stripe_invoice_cards SET payment_method_id='pm_replacement',generation=generation+1 WHERE konto_id=1");
        $this->assertSame('failed', $this->eventAt('payment_intent.payment_failed', $pi, $this->now + 1));
        $this->assertNull($this->service->card(1)['broken_at']);
        $this->assertSame('pm_replacement', $this->service->card(1)['payment_method_id']);
        $this->assertCount(1, $this->alerts);
    }

    public function testDeclineWithDifferentMethodDoesNotBreakCurrentCard(): void
    {
        $pi = $this->delayedAutomaticIntent();
        $pi['payment_method'] = 'pm_other';
        $this->eventAt('payment_intent.payment_failed', $pi, $this->now + 1);
        $this->assertNull($this->service->card(1)['broken_at']);
    }

    public function testDeclineWithSameMethodButOlderGenerationDoesNotBreakCard(): void
    {
        $pi = $this->delayedAutomaticIntent();
        $this->db->query('UPDATE stripe_invoice_cards SET generation=generation+1 WHERE konto_id=1');
        $this->eventAt('payment_intent.payment_failed', $pi, $this->now + 1);
        $this->assertNull($this->service->card(1)['broken_at']);
    }

    public function testMatchingDeclineUsesEventTimeToBreakCard(): void
    {
        $pi = $this->delayedAutomaticIntent();
        $this->eventAt('payment_intent.payment_failed', $pi, $this->now + 10);
        $this->assertSame($this->now + 10, (int)$this->service->card(1)['broken_at']);
        $this->assertSame($this->now + 10, (int)$this->payments(11)[0]['failed_at']);
    }

    /** @return array<string,array{0:?string}> */
    public static function failingIntentMethods(): array
    {
        return ['foreign method' => ['pm_foreign'], 'missing method' => [null]];
    }

    /** @dataProvider failingIntentMethods */
    #[DataProvider('failingIntentMethods')]
    public function testSynchronousDeclineOnlyBreaksTheIntentPaymentMethod(?string $method): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->stripe->decline = 'expired_card';
        $worker = new InvoicePaymentService($this->db, function ($verb, $path, $params, $options) use ($method) {
            $response = $this->stripe->transport($verb, $path, $params, $options);
            if (!$response['ok'] && isset($response['body']['error']['payment_intent'])) {
                $response['body']['error']['payment_intent']['payment_method'] = $method;
            }
            return $response;
        }, new InvoicePaymentLedger($this->db), function ($subject, $body, $key, $throttle) {
            $this->alerts[] = [$subject, $body, $key, $throttle];
        }, 'saldi_test');
        $this->assertSame(1, $worker->autopay()['failed']);
        $this->assertNull($this->service->card(1)['broken_at']);
    }

    /** @return list<array{string,?string}> Initial decline, later stronger issuer advice. */
    public static function strongerDeclineEvidence(): array
    {
        return [['expired_card', null], ['insufficient_funds', 'do_not_try_again'], ['insufficient_funds', 'confirm_card_data']];
    }

    #[DataProvider('strongerDeclineEvidence')]
    public function testSignedFailureBreaksMatchingCardAfterSynchronousNotification(string $code, ?string $advice): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->stripe->decline = $code;
        $worker = new InvoicePaymentService($this->db, function ($method, $path, $params, $options) {
            $response = $this->stripe->transport($method, $path, $params, $options);
            if (!$response['ok'] && isset($response['body']['error']['payment_intent'])) {
                $response['body']['error']['payment_intent']['payment_method'] = null;
            }
            return $response;
        }, new InvoicePaymentLedger($this->db), function ($subject, $body, $key, $throttle) {
            $this->alerts[] = [$subject, $body, $key, $throttle];
        }, 'saldi_test');
        $this->assertSame(1, $worker->autopay()['failed']);
        $this->assertNull($this->service->card(1)['broken_at']);
        $payment = $this->payments(11)[0];
        $pi = $this->stripe->intents[$payment['payment_intent_id']];
        $pi['last_payment_error']['advice_code'] = $advice;
        $this->assertSame('failed', $this->eventAt('payment_intent.payment_failed', $pi, $this->now + 10));
        $this->assertSame($this->now + 10, (int)$this->service->card(1)['broken_at']);
        $this->eventAt('payment_intent.payment_failed', $pi, $this->now + 20);
        $this->assertSame($this->now + 10, (int)$this->service->card(1)['broken_at']);
        $this->assertSame(1, (int)$this->db->row("SELECT COUNT(*) AS n FROM stripe_invoice_audit WHERE entity_type='payment' AND entity_id=? AND action='auto_failed'", [$payment['id']])['n']);
        $this->assertSame(1, (int)$this->db->row("SELECT COUNT(*) AS n FROM stripe_invoice_audit WHERE action='card_broken'")['n']);
        $this->assertSame(1, (int)$this->db->row("SELECT COUNT(*) AS n FROM stripe_invoice_notifications WHERE kind='card_decline'")['n']);
        $this->assertSame(1, count(array_filter($this->alertSubjects(), static fn($subject) => $subject === 'Automatisk kortbetaling afvist')));
    }

    public function testAutomaticMetadataCannotClaimAnotherOrdersPendingRow(): void
    {
        $pi = $this->delayedAutomaticIntent();
        $pi['metadata']['saldi_order_id'] = '10';
        $this->expectException(RuntimeException::class);
        $this->eventAt('payment_intent.payment_failed', $pi, $this->now);
    }

    public function testReverseSavedCardSuccessKeepsNewerChoiceIncludingTimestampTies(): void
    {
        $first = $this->service->startPayment(10, true);
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $second = $this->service->startPayment(11, true);
        $this->eventAt('payment_intent.succeeded', $this->stripe->succeed($second['payment_intent'], 'pm_card2'), $this->now + 2);
        $this->eventAt('payment_intent.succeeded', $this->stripe->succeed($first['payment_intent'], 'pm_card1'), $this->now + 1);
        $this->assertSame('pm_card2', $this->service->card(1)['payment_method_id']);
        $this->assertSame('succeeded', $this->payments(10)[0]['status']);
        $this->assertSame('succeeded', $this->payments(11)[0]['status']);
        $this->assertSame([], $this->stripe->callsTo('POST', '/v1/payment_methods/pm_card2/detach'));
    }

    public function testRemovalAndReconsentDoNotAllowAnOlderSuccessToReplaceCard(): void
    {
        $this->payAndSaveCard();
        $this->now += 60;
        $this->stripe->now = $this->now;
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $old = $this->service->startPayment(11, true);
        $this->service->removeCard(1, 'kunde');
        self::addInvoice($this->db->pdo, 12, '1003', '2026-10-07', 0, 200.0);
        $new = $this->service->startPayment(12, true);
        $this->eventAt('payment_intent.succeeded', $this->stripe->succeed($new['payment_intent'], 'pm_card2'), $this->now + 1);
        $this->eventAt('payment_intent.succeeded', $this->stripe->succeed($old['payment_intent']), $this->now + 2);
        $this->assertSame('pm_card2', $this->service->card(1)['payment_method_id']);
        $this->assertSame('succeeded', $this->payments(11)[0]['status']);
        $this->assertSame([], $this->stripe->callsTo('POST', '/v1/payment_methods/pm_card2/detach'));
    }

    public function testRefundHoldExcludesPageAndAutopayAndIsAuditedOnce(): void
    {
        $pi = $this->delayedAutomaticIntent();
        $charge = ['id' => 'ch_invoice', 'payment_intent' => $pi['id'], 'amount_refunded' => 10000, 'currency' => 'dkk', 'refunds' => ['data' => [['id' => 're_partial', 'amount' => 10000]]]];
        $pi['status'] = 'succeeded';
        $pi['last_payment_error'] = null;
        $pi['amount_received'] = $pi['amount'];
        $this->eventAt('payment_intent.succeeded', $pi, $this->now);
        $this->alerts = [];
        $before = $this->payments(11)[0];
        $event = ['id' => 'evt_refund', 'type' => 'charge.refunded', 'created' => $this->now + 1, 'data' => ['object' => $charge]];
        $this->assertTrue($this->service->chargeEvent($event, $charge));
        $this->assertTrue($this->service->chargeEvent($event, $charge));
        $this->assertCount(1, $this->alerts);
        $this->assertStringContainsString('Faktura: 1002', $this->alerts[0][1]);
        $this->assertStringContainsString('Konto: 1001 (id 1)', $this->alerts[0][1]);
        $this->assertStringContainsString('100,00 kr.', $this->alerts[0][1]);
        $row = $this->payments(11)[0];
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame($before['received_at'], $row['received_at']);
        $this->assertSame($before['settlement'], $row['settlement']);
        $this->assertSame('refund', $row['hold_reason']);
        $this->assertSame('collection_hold', $this->service->invoice(11)['reason']);
        $this->assertFalse($this->service->invoice(11)['payable']);
        // A manually reopened ledger item still cannot bypass the invoice hold.
        $this->db->query("UPDATE stripe_invoice_payments SET settlement='posted' WHERE order_id=11");
        $calls = count($this->stripe->calls);
        $this->now += 4 * 86400;
        $this->service->autopay();
        $this->assertSame('skipped', $this->service->chargeOffSession($this->service->card(1), 11));
        $this->assertSame($calls, count($this->stripe->calls));
        $this->assertCount(1, $this->payments(11));
        $audit = $this->db->row("SELECT data_json FROM stripe_invoice_audit WHERE entity_type='payment' AND entity_id=? AND action='refund'", [(int)$row['id']]);
        $this->assertSame('re_partial', json_decode($audit['data_json'], true)['refunds'][0]['id']);
    }

    public function testUnorderedPartialRefundsPreserveIdentitiesAndReceiptWithoutDuplicateAlerts(): void
    {
        $intent = $this->payAndSaveCard();
        $this->alerts = [];
        $before = $this->payments(10)[0];
        foreach ([['evt_refund_second', 20000, 're_second', $this->now + 2], ['evt_refund_first', 10000, 're_first', $this->now + 1], ['evt_refund_second', 20000, 're_second', $this->now + 2]] as [$id, $amount, $refund, $created]) {
            $charge = ['id' => 'ch_partial', 'payment_intent' => $intent, 'currency' => 'dkk', 'amount_refunded' => $amount, 'refunds' => ['data' => [['id' => $refund, 'amount' => 10000]]]];
            $this->service->chargeEvent(['id' => $id, 'type' => 'charge.refunded', 'created' => $created, 'data' => ['object' => $charge]], $charge);
        }
        $this->assertCount(2, $this->alerts);
        $this->assertStringContainsString('Samlet refunderet: 200,00 kr.', $this->alerts[0][1]);
        $rows = $this->db->rows("SELECT data_json FROM stripe_invoice_audit WHERE entity_type='payment' AND action='refund' ORDER BY id");
        $this->assertCount(2, $rows);
        $this->assertSame(['re_second', 're_first'], array_map(static fn($row) => json_decode($row['data_json'], true)['refunds'][0]['id'], $rows));
        $payment = $this->payments(10)[0];
        $this->assertSame($before['amount_ore'], $payment['amount_ore']);
        $this->assertSame($before['received_at'], $payment['received_at']);
        $this->assertSame('succeeded', $payment['status']);
        $this->assertSame(1, (int)$payment['collection_hold']);
    }

    public function testDisputeCreatedAndClosedKeepHoldUntilScopedStaffRelease(): void
    {
        $intent = $this->payAndSaveCard();
        $this->alerts = [];
        $charge = ['id' => 'ch_dispute', 'payment_intent' => $intent];
        foreach (['created', 'closed'] as $phase) {
            $this->service->chargeEvent(['id' => 'evt_dispute_' . $phase, 'type' => 'charge.dispute.' . $phase, 'created' => $this->now,
                'data' => ['object' => ['id' => 'dp_invoice', 'amount' => 50000, 'currency' => 'dkk', 'status' => $phase === 'closed' ? 'won' : 'needs_response']]], $charge);
        }
        $this->assertSame('collection_hold', $this->service->invoice(10)['reason']);
        $this->assertCount(2, $this->alerts);
        $paymentId = (int)$this->payments(10)[0]['id'];
        $this->assertFalse($this->service->releaseCollectionHold($paymentId, 2, 'Saldi: staff'));
        $this->setting('mode', 'live');
        $this->assertFalse($this->service->releaseCollectionHold($paymentId, 1, 'Saldi: staff'));
        $this->setting('mode', 'test');
        $this->service->removeCard(1, 'kunde');
        $this->assertNull($this->service->card(1));
        $this->assertCount(1, $this->service->collectionHolds(1), 'Staff can release without a card');
        $this->assertSame([], $this->service->collectionHolds(2));
        $this->assertTrue($this->service->releaseCollectionHold($paymentId, 1, 'Saldi: staff'));
        $this->assertFalse($this->service->releaseCollectionHold($paymentId, 1, 'Saldi: staff'));
        $this->assertSame([], $this->service->collectionHolds(1));
        $this->assertSame('received', $this->service->invoice(10)['reason'], 'Release does not reopen a receipt');
        $this->assertSame('collection_released', $this->db->row("SELECT action FROM stripe_invoice_audit WHERE entity_type='payment' ORDER BY id DESC LIMIT 1")['action']);
        $this->assertSame('succeeded', $this->payments(10)[0]['status']);
        $this->service->chargeEvent(['id' => 'evt_dispute_closed', 'type' => 'charge.dispute.closed', 'created' => $this->now, 'data' => ['object' => ['id' => 'dp_invoice', 'amount' => 50000]]], $charge);
        $this->assertSame([], $this->service->collectionHolds(1), 'A duplicate correction must not reapply a released hold');
    }

    public function testForeignSubscriptionChargeDoesNotChangeInvoicePayments(): void
    {
        $before = $this->db->rows('SELECT * FROM stripe_invoice_payments');
        $this->assertFalse($this->service->chargeEvent(['id' => 'evt_sub', 'type' => 'charge.refunded'], ['id' => 'ch_subscription', 'payment_intent' => ['id' => 'pi_subscription', 'metadata' => []]]));
        $this->assertSame($before, $this->db->rows('SELECT * FROM stripe_invoice_payments'));
        $this->assertSame([], $this->alerts);
    }


    // ------------------------------------------------------- Slice C correctness

    public function testDiscoveredSuccessCommitsBeforeStartPaymentReturns(): void
    {
        $start = $this->service->startPayment(10, true);
        $this->stripe->succeed($start['payment_intent']);
        $result = $this->service->startPayment(10, false);
        $this->assertSame('succeeded', $result['status']);
        $this->assertSame('', $result['client_secret']);
        $this->assertSame('succeeded', $this->payments(10)[0]['status']);
        $this->assertSame(50000, (int)$this->payments(10)[0]['open_ore_at_receipt']);
        $this->assertNotNull($this->payments(10)[0]['received_at']);
        $this->assertSame('pm_card1', $this->service->card(1)['payment_method_id']);
        $this->assertCount(1, $this->alerts);
        $this->assertCount(1, $this->stripe->callsTo('POST', '/v1/payment_intents'));
    }

    public function testDiscoveredProcessingCommitsAndLaterSuccessConverges(): void
    {
        $start = $this->service->startPayment(10, false);
        $this->stripe->intents[$start['payment_intent']]['status'] = 'processing';
        $this->assertSame('processing', $this->service->startPayment(10, false)['status']);
        $this->assertSame('processing', $this->payments(10)[0]['status']);
        $this->assertSame('processing', $this->service->startPayment(10, true)['status']);
        $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent']));
        $this->assertSame('succeeded', $this->payments(10)[0]['status']);
        $this->assertCount(1, $this->stripe->callsTo('POST', '/v1/payment_intents'));
    }

    public function testCancellationTransportFailureBlocksPageAndWorkerReplacement(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $open = $this->service->startPayment(11, false);
        $this->db->query("UPDATE openpost SET amount=200 WHERE faktnr='1002'");
        $this->stripe->intercept = static function ($method, $path) {
            if (str_ends_with($path, '/cancel')) return ['ok' => false, 'status' => 0, 'error_code' => 'network', 'error' => 'Timeout', 'body' => []];
            return null;
        };
        $this->assertSame('blocked', $this->service->startPayment(11, false)['status']);
        $this->assertSame('skipped', $this->service->chargeOffSession($this->service->card(1), 11));
        $this->assertSame('requires_payment_method', $this->stripe->intents[$open['payment_intent']]['status']);
        $this->assertCount(1, $this->payments(11));
        $this->assertCount(2, $this->stripe->callsTo('POST', '/v1/payment_intents'));
        $this->assertContains('Betaling er allerede i gang - afstem hos Stripe', $this->alertSubjects());
    }

    public function testConfirmationDuringCancellationRecordsSuccessAndDoesNotReplace(): void
    {
        $open = $this->service->startPayment(10, false);
        $this->db->query("UPDATE openpost SET amount=400 WHERE faktnr='1001'");
        $this->stripe->intercept = function ($method, $path) use ($open) {
            if (!str_ends_with($path, '/cancel')) return null;
            $this->stripe->succeed($open['payment_intent']);
            return ['ok' => false, 'status' => 400, 'error_code' => 'payment_intent_unexpected_state', 'error' => 'Already paid', 'body' => []];
        };
        $this->assertSame('succeeded', $this->service->startPayment(10, false)['status']);
        $this->assertSame('succeeded', $this->payments(10)[0]['status']);
        $this->assertCount(1, $this->stripe->callsTo('POST', '/v1/payment_intents'));
    }

    public function testWorkerRechecksAmountAfterCancelBeforeDispatch(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->service->startPayment(11, false);
        $this->stripe->intercept = function ($method, $path) {
            if (str_ends_with($path, '/cancel')) $this->db->query("UPDATE openpost SET amount=150 WHERE faktnr='1002'");
            return null;
        };
        $this->assertSame(1, $this->service->autopay()['charged']);
        $this->assertSame(15000, (int)$this->payments(11)[1]['amount_ore']);
    }

    /** @return array<string,array{0:string}> */
    public static function hardDeclines(): array
    {
        $cases = [];
        foreach (['authentication_required', 'expired_card', 'card_not_supported', 'lost_card', 'stolen_card', 'pickup_card'] as $code) $cases[$code] = [$code];
        return $cases;
    }

    /** @dataProvider hardDeclines */
    #[DataProvider('hardDeclines')]
    public function testHardDeclineStopsAllRemainingInvoicesForDebtor(string $code): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        self::addInvoice($this->db->pdo, 12, '1003', '2026-10-07', 0, 200.0);
        $this->stripe->decline = $code;
        $this->assertSame(1, $this->service->autopay()['failed']);
        $this->assertCount(1, $this->payments(11));
        $this->assertSame([], $this->payments(12));
    }

    public function testDirectChargeRejectsRevokedAndReplacedCardSnapshots(): void
    {
        $this->payAndSaveCard();
        $old = $this->service->card(1);
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->db->query("UPDATE stripe_invoice_cards SET generation=generation+1,payment_method_id='pm_replaced' WHERE konto_id=1");
        $this->assertSame('skipped', $this->service->chargeOffSession($old, 11));
        $new = $this->service->card(1);
        $this->service->removeCard(1, 'kunde');
        $this->assertSame('skipped', $this->service->chargeOffSession($new, 11));
        $this->assertSame([], $this->payments(11));
    }

    public function testCreatingRequestsAreCommittedBeforeTransportAndReplayImmutablePayload(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $loseResponse = true;
        $first = null;
        $worker = new InvoicePaymentService($this->db, function ($method, $path, $params, $opts) use (&$loseResponse, &$first) {
            if ($method === 'POST' && in_array($path, ['/v1/customers', '/v1/payment_intents'], true)) {
                $this->assertFalse($this->db->inTransaction());
                $op = $this->db->row('SELECT * FROM stripe_invoice_operations WHERE idempotency_key=?', [$opts['idempotency_key']]);
                $this->assertNotNull($op);
                $this->assertNotNull($op['first_dispatch_at']);
                $this->assertSame($params, json_decode($op['request_json'], true));
                if ($path === '/v1/payment_intents') {
                    $first = $first ?? [$params, $opts];
                    $this->assertSame($first, [$params, $opts]);
                }
            }
            $response = $this->stripe->transport($method, $path, $params, $opts);
            if ($method === 'POST' && $path === '/v1/payment_intents' && $loseResponse) {
                $loseResponse = false;
                return ['ok' => false, 'status' => 0, 'error_code' => 'network', 'error' => 'Response lost', 'body' => []];
            }
            return $response;
        }, new InvoicePaymentLedger($this->db), static function () {}, 'saldi_test');
        $this->assertSame(1, $worker->autopay()['skipped']);
        $this->db->query("UPDATE ordrer SET email='changed@example.test',firmanavn='Changed' WHERE id=11");
        $this->now += InvoicePaymentService::RESEND_SECONDS;
        $this->assertSame(1, $worker->autopay()['charged']);
        $this->assertCount(1, $this->payments(11));
        $this->assertSame(2, count($this->stripe->intents), 'One enrollment and one charge despite a lost response');
    }

    public function testAmbiguousOperationStopsReplayAtTwentyHours(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->stripe->networkDown = true;
        $this->service->autopay();
        $calls = count($this->stripe->callsTo('POST', '/v1/payment_intents'));
        $this->stripe->networkDown = false;
        $this->now += 20 * 3600;
        $this->service->autopay();
        $this->service->autopay();
        $this->assertCount($calls, $this->stripe->callsTo('POST', '/v1/payment_intents'));
        $this->assertSame('needs_reconcile', $this->db->row("SELECT status FROM stripe_invoice_operations WHERE payment_id=? AND kind='intent_create'", [$this->payments(11)[0]['id']])['status']);
        $this->assertSame('created', $this->payments(11)[0]['status']);
        $this->assertSame(1, count(array_keys($this->alertSubjects(), 'Betaling skal afstemmes hos Stripe', true)));
    }

    public function testManualIntentResponseLossReplaysOriginalRequestAfterRestart(): void
    {
        $lost = true;
        $transport = function ($method, $path, $params, $opts) use (&$lost) {
            $response = $this->stripe->transport($method, $path, $params, $opts);
            if ($method === 'POST' && $path === '/v1/payment_intents' && $lost) {
                $lost = false;
                return ['ok' => false, 'status' => 0, 'error_code' => 'network', 'error' => 'Lost response', 'body' => []];
            }
            return $response;
        };
        $first = new InvoicePaymentService($this->db, $transport, new InvoicePaymentLedger($this->db), static function () {}, 'saldi_test');
        try { $first->startPayment(10, true); $this->fail('Response must be lost'); } catch (\Saldi\Stripe\InvoicePaymentApiError $e) { }
        $this->assertSame('created', $this->payments(10)[0]['status']);
        $this->db->query("UPDATE adresser SET email='changed@example.test' WHERE id=1");
        $second = new InvoicePaymentService($this->db, $transport, new InvoicePaymentLedger($this->db), static function () {}, 'saldi_test');
        $result = $second->startPayment(10, false);
        $this->assertSame('ready', $result['status']);
        $this->assertSame(0, (int)$this->payments(10)[0]['save_card']);
        $this->assertNull($this->stripe->intents[$result['payment_intent']]['setup_future_usage']);
        $calls = $this->stripe->callsTo('POST', '/v1/payment_intents');
        $this->assertSame(array_slice($calls[0], 2), array_slice($calls[1], 2));
        $this->assertCount(1, $this->payments(10));
    }

    public function testReceiptCommitsWhenCardEnrichmentFailsAndEnrollmentRetries(): void
    {
        $start = $this->service->startPayment(10, true);
        $this->stripe->intercept = static function ($method, $path) {
            if (str_starts_with($path, '/v1/payment_methods/')) return ['ok' => false, 'status' => 0, 'error_code' => 'network', 'error' => 'Timeout', 'body' => []];
            return null;
        };
        $this->assertSame('succeeded', $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent'])));
        $receipt = $this->payments(10)[0];
        $this->assertSame('succeeded', $receipt['status']);
        $this->assertSame('manual', $receipt['settlement']);
        $this->assertSame(50000, (int)$receipt['open_ore_at_receipt']);
        $this->assertNull($this->service->card(1));
        $op = $this->db->row("SELECT * FROM stripe_invoice_operations WHERE kind='card_enrichment'");
        $this->assertSame('error', $op['status']);
        $this->assertStringNotContainsString('client_secret', $op['data_json']);
        $this->stripe->intercept = null;
        $this->assertSame(1, $this->service->enrichCards());
        $this->assertSame('v1-2026-10', $this->service->card(1)['terms_version']);
        $this->assertSame($receipt, $this->payments(10)[0]);
        $this->assertSame(0, $this->service->enrichCards());
    }

    public function testAgedIntentsRecoverWithCollectionOffWithoutReplacement(): void
    {
        $start = $this->service->startPayment(10, false);
        $this->stripe->intents[$start['payment_intent']]['status'] = 'processing';
        $this->event('payment_intent.processing', $this->stripe->intents[$start['payment_intent']]);
        $this->setting('invoice_pay_enabled', 'off');
        $this->setting('autopay_enabled', 'off');
        $this->stripe->succeed($start['payment_intent']);
        $this->now += 3600;
        $this->service->autopay();
        $this->assertSame('succeeded', $this->payments(10)[0]['status']);
        $this->assertCount(1, $this->stripe->callsTo('POST', '/v1/payment_intents'));
        $this->assertCount(1, $this->alerts);
    }

    public function testAgedIntentCancellationRecoversAndRetrievalFailureStaysPending(): void
    {
        $start = $this->service->startPayment(10, false);
        $this->db->query("UPDATE stripe_invoice_payments SET status='processing' WHERE order_id=10");
        $this->now += 3600;
        $this->stripe->networkDown = true;
        $this->assertSame(0, $this->service->reconcileIntents());
        $this->assertSame('processing', $this->payments(10)[0]['status']);
        $this->stripe->networkDown = false;
        $this->stripe->intents[$start['payment_intent']]['status'] = 'canceled';
        $this->assertSame(1, $this->service->reconcileIntents());
        $this->assertSame('canceled', $this->payments(10)[0]['status']);
        $this->assertCount(1, $this->stripe->callsTo('POST', '/v1/payment_intents'));
    }

    public function testManualReceiptReconcilesSettledInvoiceBeforeLaterCreditGuard(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $this->payAndSaveCard();
        $this->db->query("UPDATE openpost SET udlignet='1' WHERE konto_id=1 AND faktnr='1001'");
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->assertSame(1, $this->service->autopay()['charged']);
        $this->assertSame('posted_manual', $this->payments(10)[0]['settlement']);
        $this->assertSame(0, $this->service->reconcileManualReceipts());
        $this->setting('posting_enabled', 'on');
        $this->assertSame(0, $this->service->settlePending());
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM transaktioner')['n']);
    }

    public function testManualReceiptReconciliationWaitsForEnoughBalanceReduction(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $start = $this->service->startPayment(10, false);
        $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent']));
        $this->assertSame(0, $this->service->reconcileManualReceipts());
        $this->db->query("INSERT INTO openpost (konto_id,faktnr,amount,udlignet,valuta) VALUES (1,'1001',-100,'0','DKK')");
        $this->assertSame(0, $this->service->reconcileManualReceipts());
        $this->assertSame('manual', $this->payments(10)[0]['settlement']);
        $this->db->query("INSERT INTO openpost (konto_id,faktnr,amount,udlignet,valuta) VALUES (1,'1001',-400,'0','DKK')");
        $this->assertSame(1, $this->service->reconcileManualReceipts());
        $this->assertSame('posted_manual', $this->payments(10)[0]['settlement']);
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM transaktioner')['n']);
    }

    public function testUnreconciledManualReceiptSkipsAutomaticCollectionAndAlertsOnce(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->payAndSaveCard(11);
        $this->alerts = [];
        $this->service->autopay();
        $this->service->autopay();
        $this->assertSame(['Automatisk kortbetaling afventer bogføring'], $this->alertSubjects());
        $this->assertCount(1, $this->payments(11));
        $this->assertSame('manual', $this->payments(11)[0]['settlement']);
    }

    public function testPostingRetryUsesCopenhagenReceiptDateAcrossYearBoundary(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $this->setting('posting_enabled', 'on');
        $this->setting('clearing_account', '3100');
        $start = $this->service->startPayment(10, false);
        $received = strtotime('2026-12-31 23:30:00 Europe/Copenhagen');
        $this->now = strtotime('2027-01-01 08:00:00 Europe/Copenhagen');
        $this->eventAt('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent']), $received);
        $this->assertSame('error', $this->payments(10)[0]['settlement']);
        $this->db->query("INSERT INTO kontoplan (kontonr,regnskabsaar,kontotype,lukket,saldo) VALUES (3100,1,'D','',0)");
        $this->now = strtotime('2027-02-01 10:00:00 Europe/Copenhagen');
        $this->service->settlePending();
        $this->assertSame('posted', $this->payments(10)[0]['settlement']);
        $this->assertSame(['2026-12-31'], array_values(array_unique(array_column($this->db->rows('SELECT transdate FROM transaktioner'), 'transdate'))));
        $this->assertSame($received, (int)$this->payments(10)[0]['received_at']);
    }

    public function testDueDatesRejectInvalidCalendarDatesAndSettledItems(): void
    {
        // PostgreSQL DATE columns cannot contain corruption; use text in this isolated fixture.
        if ($this->db->driver() === 'pgsql') {
            $this->db->query('ALTER TABLE openpost ALTER COLUMN forfaldsdate TYPE TEXT USING forfaldsdate::text');
            $this->db->query('ALTER TABLE ordrer ALTER COLUMN due_date TYPE TEXT USING due_date::text');
        }
        $this->db->query("UPDATE openpost SET forfaldsdate='2026-02-30' WHERE faktnr='1001'");
        $this->db->query("UPDATE ordrer SET due_date='2026-10-12' WHERE id=10");
        $this->assertSame('2026-10-12', $this->service->invoice(10)['dueDate']);
        $this->db->query("UPDATE ordrer SET due_date='2026-02-30',betalingsbet='lb. md.',betalingsdage=5 WHERE id=10");
        $this->assertSame('2026-11-05', $this->service->invoice(10)['dueDate']);
        $this->db->query("UPDATE openpost SET udlignet='1',forfaldsdate='2026-01-01' WHERE faktnr='1001'");
        $this->assertSame('2026-11-05', $this->service->invoice(10)['dueDate']);
    }

    public function testMixedCurrencyCreditAndNonpositiveInvoicesNeverDispatch(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->db->query("INSERT INTO openpost (konto_id,faktnr,amount,udlignet,valuta) VALUES (1,'EUR-credit',-100,'0','EUR')");
        $this->assertSame('currency', $this->service->invoice(11)['reason']);
        $this->assertSame(0, $this->service->autopay()['charged']);
        $this->assertSame([], $this->payments(11));
        $this->db->query("DELETE FROM openpost WHERE valuta='EUR'");
        $this->db->query('UPDATE ordrer SET sum=-10,moms=-2.5 WHERE id=11');
        $this->assertFalse($this->service->invoice(11)['payable']);
        $this->assertSame('skipped', $this->service->chargeOffSession($this->service->card(1), 11));
    }

    public function testStartResultCarriesActualPartialBankAmountAndTinyBalanceIsRefused(): void
    {
        $this->db->query("INSERT INTO openpost (konto_id,faktnr,amount,udlignet,valuta) VALUES (1,'1001',-100,'0','DKK')");
        $result = $this->service->startPayment(10, false);
        $this->assertSame(40000, $result['amount']);
        $this->assertSame('dkk', $result['currency']);
        $this->assertSame($result['amount'], $this->stripe->intents[$result['payment_intent']]['amount']);
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 0.8);
        try { $this->service->startPayment(11, false); $this->fail('Tiny residual must use bank transfer'); } catch (RuntimeException $e) {
            $this->assertStringContainsString('bankoverførsel', $e->getMessage());
        }
        $this->assertSame([], $this->payments(11));
    }

    public function testConfigurationAndRateLimitErrorsKeepCardAndDoNotSpendDeclineAttempts(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        foreach ([401, 403, 429, 400, 500] as $status) {
            $this->stripe->intercept = static function ($method, $path) use ($status) {
                if ($path === '/v1/payment_intents') return ['ok' => false, 'status' => $status, 'error_code' => 'invalid_request_error', 'error' => 'Configuration failure', 'body' => ['error' => ['type' => 'invalid_request_error']]];
                return null;
            };
            $this->service->autopay();
            $this->now += InvoicePaymentService::RESEND_SECONDS;
            $this->assertCount(1, $this->payments(11));
            $this->assertSame('created', $this->payments(11)[0]['status']);
            $this->assertNull($this->payments(11)[0]['failed_at']);
            $this->assertNull($this->service->card(1)['broken_at']);
        }
        $this->assertContains('Automatisk kortbetaling - teknisk fejl', $this->alertSubjects());
        $this->assertNotContains('Automatisk kortbetaling afvist', $this->alertSubjects());
    }

    public function testIssuerStopAdviceBreaksMatchingCardAndStopsDebtorLoop(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        self::addInvoice($this->db->pdo, 12, '1003', '2026-10-07', 0, 200.0);
        $this->stripe->decline = 'generic_decline';
        $worker = new InvoicePaymentService($this->db, function ($method, $path, $params, $opts) {
            $r = $this->stripe->transport($method, $path, $params, $opts);
            if (isset($r['body']['error'])) $r['body']['error']['advice_code'] = 'do_not_try_again';
            return $r;
        }, new InvoicePaymentLedger($this->db), static function () {}, 'saldi_test');
        $this->assertSame(1, $worker->autopay()['failed']);
        $this->assertSame('do_not_try_again', $this->payments(11)[0]['advice_code']);
        $this->assertNotNull($this->service->card(1)['broken_at']);
        $this->assertSame([], $this->payments(12));
    }

    public function testWebhookSuccessWinsSynchronousErrorUsingTwoConnections(): void
    {
        if ($this->schema === null) $this->markTestSkipped('Requires PostgreSQL for two independent connections');
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $pdo = new PDO(getenv('SALDI_STRIPE_TEST_DSN'), getenv('SALDI_STRIPE_TEST_USER') ?: null, getenv('SALDI_STRIPE_TEST_PASSWORD') ?: null);
        $pdo->exec('SET search_path TO ' . $this->schema);
        $other = new InvoicePaymentStore($pdo, fn() => $this->now);
        $webhook = new InvoicePaymentService($other, [$this->stripe, 'transport'], new InvoicePaymentLedger($other), static function () {}, 'saldi_test');
        $worker = new InvoicePaymentService($this->db, function ($method, $path, $params, $opts) use ($other, $webhook) {
            $r = $this->stripe->transport($method, $path, $params, $opts);
            if ($method === 'POST' && $path === '/v1/payment_intents') {
                $this->assertNotNull($other->row('SELECT id FROM stripe_invoice_operations WHERE idempotency_key=?', [$opts['idempotency_key']]));
                $success = $r['body'];
                $webhook->paymentIntentEvent($success, $this->now);
                return ['ok' => false, 'status' => 402, 'error_code' => 'card_declined', 'error' => 'Late decline response', 'body' => ['error' => ['type' => 'card_error', 'decline_code' => 'expired_card', 'payment_intent' => $success]]];
            }
            return $r;
        }, new InvoicePaymentLedger($this->db), static function () {}, 'saldi_test');
        $this->assertSame(1, $worker->autopay()['charged']);
        $this->assertSame('succeeded', $this->payments(11)[0]['status']);
        $this->assertNull($this->service->card(1)['broken_at']);
        $this->assertSame(0, (int)$this->db->row("SELECT COUNT(*) AS n FROM stripe_invoice_audit WHERE action='auto_failed'")['n']);
    }

    public function testFailedReceiptAndRefundMailRetryWithoutRepeatingFinancialFacts(): void
    {
        $mailDown = new InvoicePaymentService($this->db, [$this->stripe, 'transport'], new InvoicePaymentLedger($this->db), static fn() => false, 'saldi_test');
        $start = $mailDown->startPayment(10, false);
        $pi = $this->stripe->succeed($start['payment_intent']);
        $mailDown->paymentIntentEvent($pi);
        $charge = ['id' => 'ch_mail', 'payment_intent' => $pi['id'], 'amount_refunded' => 10000];
        $event = ['id' => 'evt_mail', 'type' => 'charge.refunded', 'data' => ['object' => $charge]];
        $mailDown->chargeEvent($event, $charge);
        $before = $this->payments(10)[0];
        $this->assertSame('succeeded', $before['status']);
        $this->assertSame(1, (int)$before['collection_hold']);
        $this->assertSame(2, (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_invoice_notifications WHERE sent_at IS NULL')['n']);
        $this->setting('invoice_pay_enabled', 'off');
        $this->setting('autopay_enabled', 'off');
        $this->service->autopay();
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_invoice_notifications WHERE sent_at IS NULL')['n']);
        $this->assertCount(2, $this->alerts);
        $this->assertSame($before, $this->payments(10)[0]);
        $this->service->chargeEvent($event, $charge);
        $this->service->paymentIntentEvent($pi);
        $this->assertCount(2, $this->alerts);
        $this->assertCount(1, $this->payments(10));
    }

    public function testPostingPropagatesFollowingOpeningBalancesOnSameConnection(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $this->setting('posting_enabled', 'on');
        if ($this->db->driver() === 'sqlite') {
            $this->db->query('ALTER TABLE kontoplan ADD COLUMN primo NUMERIC');
            $this->db->query('ALTER TABLE kontoplan ADD COLUMN overfor_til NUMERIC');
        }
        $this->db->query("UPDATE kontoplan SET kontotype='S',primo=0,overfor_til=kontonr");
        $this->db->query("INSERT INTO grupper (art,kodenr,box1,box2,box3,box4,box5) VALUES ('RA',2,'1','2027','12','2027','on')");
        $this->db->query("INSERT INTO kontoplan (kontonr,regnskabsaar,kontotype,lukket,primo,saldo,overfor_til) VALUES (8000,1,'X','',0,0,9000),(3000,2,'S','',0,0,3000),(7000,2,'S','',0,0,7000),(9000,2,'S','',0,0,9000)");
        $start = $this->service->startPayment(10, false);
        $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent']));
        $this->assertSame('posted', $this->payments(10)[0]['settlement']);
        $rows = $this->db->rows('SELECT kontonr,primo,saldo FROM kontoplan WHERE regnskabsaar=2 ORDER BY kontonr');
        $this->assertSame([50000,-50000,0], array_map(static fn($r) => \Saldi\Stripe\InvoicePaymentMath::fromDb($r['primo']), $rows));
        $this->assertSame([50000,-50000,0], array_map(static fn($r) => \Saldi\Stripe\InvoicePaymentMath::fromDb($r['saldo']), $rows));
    }

    public function testAutopayPostingRecalculatesSuccessorSubtotalsAndResultsLikeJournal(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        if ($this->db->driver() === 'sqlite') {
            $this->db->query('ALTER TABLE kontoplan ADD COLUMN primo NUMERIC');
            $this->db->query('ALTER TABLE kontoplan ADD COLUMN overfor_til NUMERIC');
        }
        $this->db->query("UPDATE kontoplan SET kontotype='S',primo=0,overfor_til=kontonr");
        $this->db->query("INSERT INTO grupper (art,kodenr,box1,box2,box3,box4,box5) VALUES ('RA',2,'1','2027','12','2027','on')");
        $this->db->query("INSERT INTO kontoplan (kontonr,regnskabsaar,kontotype,lukket,primo,saldo,overfor_til,fra_kto) VALUES
            (8000,1,'X','',0,0,9000,0),
            (1000,2,'D','',123,999,0,0),(1100,2,'D','',123,999,0,0),(1200,2,'H','',123,999,0,0),
            (1300,2,'Z','',123,10,0,1000),(1400,2,'R','',123,10,0,1300),
            (1500,2,'Z','',123,20,0,1000),(1600,2,'R','',123,999,0,1000),
            (3000,2,'S','',0,10,3000,0),(3100,2,'Z','',123,10,0,3000),(3200,2,'R','',123,10,0,3100),
            (7000,2,'S','',0,0,7000,0),(9000,2,'S','',0,0,9000,0),(9900,2,'X','',123,999,0,0)");
        foreach ([[1000,20,5], [1100,0,5], [1200,99,0], [1300,99,0], [3000,10,0], [9900,99,0]] as [$account, $debit, $credit]) {
            $this->db->query('INSERT INTO transaktioner (kontonr,transdate,debet,kredit) VALUES (?,?,?,?)', [$account, '2027-02-01', $debit, $credit]);
        }
        $this->setting('posting_enabled', 'on');
        $this->assertSame(1, $this->service->autopay()['charged']);
        $this->assertSame('posted', $this->payments(11)[0]['settlement']);
        // 20261013 CL/LH These are genberegn's ordered ranges/references, including R in Z and excluding H/Z transactions.
        $expected = [1000 => 1500, 1100 => -500, 1200 => 0, 1300 => 1000, 1400 => 1000,
            1500 => 2000, 1600 => 0, 3000 => 26000, 3100 => 26000, 3200 => 26000,
            7000 => -25000, 9000 => 0, 9900 => 0];
        foreach ($this->db->rows('SELECT kontonr,kontotype,primo,saldo FROM kontoplan WHERE regnskabsaar=2 ORDER BY kontonr') as $account) {
            $number = (int)$account['kontonr'];
            $this->assertSame($expected[$number], \Saldi\Stripe\InvoicePaymentMath::fromDb($account['saldo']), 'Successor account ' . $number);
            if ($account['kontotype'] !== 'S') $this->assertSame(0, \Saldi\Stripe\InvoicePaymentMath::fromDb($account['primo']));
        }
        $this->assertSame(25000, \Saldi\Stripe\InvoicePaymentMath::fromDb($this->db->row('SELECT primo FROM kontoplan WHERE regnskabsaar=2 AND kontonr=3000')['primo']));
        $this->assertFalse($this->db->inTransaction());
    }

    public function testDecimalMoneyRoundingStaysInIntegerOre(): void
    {
        $this->assertSame(101, \Saldi\Stripe\InvoicePaymentMath::lineTotal('1.005', '1', '0'));
        $this->assertSame(22500, \Saldi\Stripe\InvoicePaymentMath::lineTotal('100', '2.5', '10'));
        $this->assertSame(1, \Saldi\Stripe\InvoicePaymentMath::fromDb('.005'));
        foreach (['0.005' => 1, '-0.005' => -1, '1234.505' => 123451, '9999999999999.99' => 999999999999999] as $decimal => $ore) {
            $this->assertSame($ore, \Saldi\Stripe\InvoicePaymentMath::fromDb($decimal));
            $this->assertSame($ore, \Saldi\Stripe\InvoicePaymentMath::fromDb(\Saldi\Stripe\InvoicePaymentMath::decimal($ore)));
        }
    }

    public function testRevocationParksAmbiguousChargeAndExpiryRunsWithCollectionOff(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->stripe->networkDown = true;
        $this->service->autopay();
        $this->stripe->networkDown = false;
        $this->service->removeCard(1, 'kunde');
        $this->assertSame('needs_reconcile', $this->db->row("SELECT status FROM stripe_invoice_operations WHERE payment_id=? AND kind='intent_create'", [$this->payments(11)[0]['id']])['status']);
        $this->setting('invoice_pay_enabled', 'off');
        $this->setting('autopay_enabled', 'off');
        $this->now += 20 * 3600;
        $before = count($this->stripe->callsTo('POST', '/v1/payment_intents'));
        $this->service->autopay();
        $this->assertCount($before, $this->stripe->callsTo('POST', '/v1/payment_intents'));
        $this->assertSame('created', $this->payments(11)[0]['status']);
        $this->assertContains('Betaling skal afstemmes hos Stripe', $this->alertSubjects());
    }

    public function testAmbiguousExpiryContinuesWhenBothCollectionSwitchesAreOff(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->stripe->networkDown = true;
        $this->service->autopay();
        $this->stripe->networkDown = false;
        $this->setting('invoice_pay_enabled', 'off');
        $this->setting('autopay_enabled', 'off');
        $this->now += 20 * 3600;
        $this->assertSame(1, $this->service->expireAmbiguousOperations());
        $this->assertSame(0, $this->service->expireAmbiguousOperations());
        $this->assertSame('needs_reconcile', $this->db->row("SELECT status FROM stripe_invoice_operations WHERE payment_id=? AND kind='intent_create'", [$this->payments(11)[0]['id']])['status']);
    }

    public function testCustomerResponseLossReusesCommittedPayloadAfterEmailChange(): void
    {
        $lose = true;
        $transport = function ($method, $path, $params, $opts) use (&$lose) {
            if ($method === 'POST' && $path === '/v1/customers') $this->assertFalse($this->db->inTransaction());
            $r = $this->stripe->transport($method, $path, $params, $opts);
            if ($method === 'POST' && $path === '/v1/customers' && $lose) {
                $lose = false;
                return ['ok' => false, 'status' => 0, 'error_code' => 'network', 'error' => 'Lost customer response', 'body' => []];
            }
            return $r;
        };
        $first = new InvoicePaymentService($this->db, $transport, new InvoicePaymentLedger($this->db), static function () {}, 'saldi_test');
        try { $first->startPayment(10, false); $this->fail('Customer response must be lost'); } catch (\Saldi\Stripe\InvoicePaymentApiError $e) { }
        $this->assertSame([], $this->payments(10));
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_invoice_customers')['n']);
        $this->db->query("UPDATE adresser SET email='changed@example.test',firmanavn='Changed' WHERE id=1");
        $second = new InvoicePaymentService($this->db, $transport, new InvoicePaymentLedger($this->db), static function () {}, 'saldi_test');
        $this->assertSame('ready', $second->startPayment(10, false)['status']);
        $calls = $this->stripe->callsTo('POST', '/v1/customers');
        $this->assertSame(array_slice($calls[0], 2), array_slice($calls[1], 2));
        $this->assertSame(1, (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_invoice_customers')['n']);
        $this->assertCount(1, $this->payments(10));
    }

    public function testRevocationCancelsUndispatchedAutomaticOperation(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $card = $this->service->card(1);
        $id = $this->db->insert("INSERT INTO stripe_invoice_payments (mode,order_id,konto_id,amount_ore,kind,status,card_generation,created_at,updated_at) VALUES ('test',11,1,25000,'auto','created',?,?,?)", [(int)$card['generation'], $this->now, $this->now]);
        $operations = new \Saldi\Stripe\InvoicePaymentOperations($this->db, 'saldi_test');
        $operations->prepare('intent_create', (string)$id, '/v1/payment_intents', ['amount' => 25000], 11, 25000, (int)$card['generation'], $id);
        $this->service->removeCard(1, 'kunde');
        $this->assertSame('canceled', $this->payments(11)[0]['status']);
        $this->assertSame('canceled', $this->db->row("SELECT status FROM stripe_invoice_operations WHERE payment_id=? AND kind='intent_create'", [$id])['status']);
        $this->assertCount(1, $this->stripe->callsTo('POST', '/v1/payment_intents'));
    }

    public function testWebhookResolvesLostCreationBeforeReplayExpiry(): void
    {
        $pi = $this->delayedAutomaticIntent();
        $pi['status'] = 'succeeded';
        $pi['amount_received'] = $pi['amount'];
        $pi['last_payment_error'] = null;
        $this->event('payment_intent.succeeded', $pi);
        $this->now += 20 * 3600;
        $this->assertSame(0, $this->service->expireAmbiguousOperations());
        $op = $this->db->row("SELECT status,result_id FROM stripe_invoice_operations WHERE payment_id=? AND kind='intent_create'", [$this->payments(11)[0]['id']]);
        $this->assertSame('succeeded', $op['status']);
        $this->assertSame($pi['id'], $op['result_id']);
    }

    public function testDeclineWithoutIntentIdentityBlocksAnyReplacement(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $worker = new InvoicePaymentService($this->db, static function () {
            return ['ok' => false, 'status' => 402, 'error_code' => 'card_declined', 'error' => 'Missing intent reference', 'body' => ['error' => ['type' => 'card_error', 'decline_code' => 'expired_card']]];
        }, new InvoicePaymentLedger($this->db), static function () {}, 'saldi_test');
        $this->assertSame('pending', $worker->chargeOffSession($this->service->card(1), 11));
        $this->assertSame('created', $this->payments(11)[0]['status']);
        $this->assertNull($this->service->card(1)['broken_at']);
        $this->assertSame('blocked', $this->service->startPayment(11, false)['status']);
        $this->now += 3 * 86400;
        $this->assertSame(0, $worker->autopay()['charged']);
        $this->assertCount(1, $this->payments(11));
    }

    public function testMidPostingFailureRollsBackLedgerButKeepsReceiptForRetry(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $this->setting('posting_enabled', 'on');
        if ($this->db->driver() === 'pgsql') {
            $this->db->query("CREATE FUNCTION reject_card_posting() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.kontonr=7000 THEN RAISE EXCEPTION 'Fixture posting failure'; END IF; RETURN NEW; END; $$");
            $this->db->query('CREATE TRIGGER reject_card_posting BEFORE INSERT ON transaktioner FOR EACH ROW EXECUTE FUNCTION reject_card_posting()');
        } else {
            $this->db->query("CREATE TRIGGER reject_card_posting BEFORE INSERT ON transaktioner WHEN NEW.kontonr=7000 BEGIN SELECT RAISE(ABORT,'Fixture posting failure'); END");
        }
        $start = $this->service->startPayment(10, false);
        $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent']));
        $this->assertSame('succeeded', $this->payments(10)[0]['status']);
        $this->assertSame('error', $this->payments(10)[0]['settlement']);
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM transaktioner')['n']);
        $this->assertSame(0, (int)$this->db->row("SELECT COUNT(*) AS n FROM stripe_invoice_operations WHERE kind='receipt'")['n']);
        $this->assertSame(1, (int)$this->db->row('SELECT COUNT(*) AS n FROM openpost')['n']);
        foreach ($this->db->rows('SELECT saldo FROM kontoplan') as $account) $this->assertSame(0, \Saldi\Stripe\InvoicePaymentMath::fromDb($account['saldo']));
        $this->db->query($this->db->driver() === 'pgsql' ? 'DROP TRIGGER reject_card_posting ON transaktioner' : 'DROP TRIGGER reject_card_posting');
        $this->assertSame(1, $this->service->settlePending());
        $this->assertSame('posted', $this->payments(10)[0]['settlement']);
        $this->assertSame(2, (int)$this->db->row('SELECT COUNT(*) AS n FROM transaktioner')['n']);
    }

    public function testManualReconciliationIgnoresOtherDebtorsAndInvoiceNumbers(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $start = $this->service->startPayment(10, false);
        $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent']));
        $this->db->query("INSERT INTO openpost (konto_id,faktnr,amount,udlignet,valuta) VALUES (2,'1001',500,'1','DKK'),(2,'1001',-500,'1','DKK'),(1,'other',-500,'0','DKK')");
        $this->assertSame(0, $this->service->reconcileManualReceipts());
        $this->assertSame('manual', $this->payments(10)[0]['settlement']);
        $this->assertSame(50000, (int)$this->payments(10)[0]['open_ore_at_receipt']);
    }

    public function testForeignCurrencyIntroducedAfterDispatchCannotPostOrReconcileReceipt(): void
    {
        $this->setting('mode', 'live');
        $this->stripe->liveMode = true;
        $this->setting('posting_enabled', 'on');
        $start = $this->service->startPayment(10, false);
        $this->db->query("INSERT INTO openpost (konto_id,faktnr,amount,udlignet,valuta) VALUES (1,'1001',-100,'0','EUR')");
        $this->assertSame('succeeded', $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent'])));
        $this->assertSame('error', $this->payments(10)[0]['settlement']);
        $this->assertNull($this->payments(10)[0]['open_ore_at_receipt']);
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM transaktioner')['n']);
        $this->db->query("UPDATE stripe_invoice_payments SET settlement='manual' WHERE order_id=10");
        $this->assertSame(0, $this->service->reconcileManualReceipts());
    }

    public function testReplacementDetachFailureKeepsNewEnrollmentAndQueuesNotification(): void
    {
        $this->payAndSaveCard();
        $this->now += 60;
        $this->stripe->now = $this->now;
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $start = $this->service->startPayment(11, true);
        $this->stripe->detachError = 'api_error';
        $this->event('payment_intent.succeeded', $this->stripe->succeed($start['payment_intent'], 'pm_card2'));
        $this->assertSame('succeeded', $this->payments(11)[0]['status']);
        $this->assertSame('pm_card2', $this->service->card(1)['payment_method_id']);
        $this->assertSame(1, (int)$this->service->card(1)['autopay']);
        $this->assertContains('Tidligere kort kunne ikke fjernes hos Stripe', $this->alertSubjects());
        $this->assertSame(1, (int)$this->db->row("SELECT COUNT(*) AS n FROM stripe_invoice_notifications WHERE kind='card_detach'")['n']);
    }

    // 20261012 CL/LH Regression coverage for missed hosted successes, grant-time consent, receipt dates and safe retries.
    public function testOrdinaryHostedSuccessRecoversWithoutWebhookOrReturnWithAutopayOff(): void
    {
        $start = $this->service->startPayment(10, false);
        $this->assertSame('requires_payment_method', $this->payments(10)[0]['status']);
        $this->stripe->succeed($start['payment_intent']);
        $this->setting('autopay_enabled', 'off');
        $this->setting('invoice_pay_enabled', 'off');
        $this->now += 3601;
        $this->service->autopay();
        $this->assertSame('succeeded', $this->payments(10)[0]['status']);
        $this->assertSame('manual', $this->payments(10)[0]['settlement']);
        $this->assertCount(1, $this->alerts);
        $this->assertCount(1, $this->stripe->callsTo('POST', '/v1/payment_intents'));
        $this->assertNull($this->service->card(1));
    }

    /** @return list<array{bool}> Successful retrievals or failures for the first hundred abandoned intents. */
    public static function abandonedRecoveryOutcomes(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('abandonedRecoveryOutcomes')]
    public function testRecoveryVisitsSuccessAfterOneHundredAbandonedIntentsInOneRun(bool $retrievalFails): void
    {
        // 20261013 CL/LH Real hosted attempts exercise keyset paging, including cursor progress on GET failures.
        foreach (range(20, 120) as $orderId) {
            self::addInvoice($this->db->pdo, $orderId, (string)(2000 + $orderId), '2026-10-07', 0, 100.0);
            $start = $this->service->startPayment($orderId, false);
        }
        $successfulId = $start['payment_intent'];
        $this->stripe->succeed($successfulId);
        $this->stripe->intents[$successfulId]['latest_charge'] = ['id' => 'ch_recovered', 'created' => $this->now];
        if ($retrievalFails) {
            $this->stripe->intercept = static function ($method, $path) use ($successfulId) {
                if ($method === 'GET' && str_starts_with($path, '/v1/payment_intents/') && $path !== '/v1/payment_intents/' . $successfulId) {
                    return ['ok' => false, 'status' => 0, 'error_code' => 'network', 'error' => 'Retrieval failed', 'body' => null];
                }
                return null;
            };
        }
        $this->setting('autopay_enabled', 'off');
        $this->setting('invoice_pay_enabled', 'off');
        $this->now += 3601;
        $this->stripe->calls = [];
        $this->service->autopay();
        $this->assertCount(101, $this->stripe->callsTo('GET', '/v1/payment_intents/'));
        $this->assertSame('succeeded', $this->payments(120)[0]['status']);
        $this->assertSame('manual', $this->payments(120)[0]['settlement']);
        $this->assertSame(100, (int)$this->db->row("SELECT COUNT(*) AS n FROM stripe_invoice_payments WHERE status='requires_payment_method'")['n']);
        $this->assertCount(0, $this->stripe->callsTo('POST', '/v1/payment_intents'));
        $this->assertSame(1, (int)$this->db->row("SELECT COUNT(*) AS n FROM stripe_invoice_notifications WHERE subject='Testbetaling modtaget - ingen bogføring'")['n']);
    }

    public function testEveryKnownNonterminalHostedStateIsRecovered(): void
    {
        foreach (['created', 'requires_confirmation', 'requires_action', 'processing'] as $index => $state) {
            $order = 20 + $index;
            self::addInvoice($this->db->pdo, $order, (string)(1100 + $index), '2026-10-07', 0, 100.0);
            $start = $this->service->startPayment($order, false);
            $pi = $this->stripe->intents[$start['payment_intent']];
            $pi['status'] = $state;
            $this->eventAt('payment_intent.processing', $pi, $this->now);
            $this->stripe->succeed($start['payment_intent']);
        }
        $this->setting('autopay_enabled', 'off');
        $this->now += 3601;
        $this->assertSame(4, $this->service->reconcileIntents());
        foreach (range(20, 23) as $order) $this->assertSame('succeeded', $this->payments($order)[0]['status']);
    }

    public function testConsentOnReusedOlderIntentStartsAtActualGrantAndExcludesEarlierInvoices(): void
    {
        $this->now = strtotime('2026-10-01 10:00:00 Europe/Copenhagen');
        $this->stripe->now = $this->now;
        $start = $this->service->startPayment(10, false);
        $this->assertNull($this->payments(10)[0]['consent_granted_at']);
        foreach (range(1, 11) as $day) {
            self::addInvoice($this->db->pdo, 20 + $day, (string)(1100 + $day), sprintf('2026-10-%02d', $day), 0, 100.0);
        }
        $this->now = strtotime('2026-10-12 10:00:00 Europe/Copenhagen');
        $this->stripe->now = $this->now;
        $reused = $this->service->startPayment(10, true);
        $this->assertSame($start['payment_intent'], $reused['payment_intent']);
        $this->assertSame($this->now, (int)$this->payments(10)[0]['consent_granted_at']);
        $this->eventAt('payment_intent.succeeded', $this->stripe->succeed($reused['payment_intent']), $this->now);
        $card = $this->service->card(1);
        $this->assertSame($this->now, (int)$card['consent_at']);
        $this->assertSame($this->now, json_decode($this->db->row("SELECT data_json FROM stripe_invoice_audit WHERE action='card_saved'")['data_json'], true)['consent_at']);
        self::addInvoice($this->db->pdo, 40, '1200', '2026-10-12', 0, 100.0);
        // 20261012 CL/LH Enrollment receipt has been booked, so it cannot trip the unrelated credit guard.
        $this->db->query("UPDATE stripe_invoice_payments SET settlement='posted_manual' WHERE order_id=10");
        $this->db->query("UPDATE openpost SET udlignet='1' WHERE faktnr='1001'");
        $this->assertSame(1, $this->service->autopay()['charged']);
        foreach (range(21, 31) as $order) $this->assertSame([], $this->payments($order));
        $this->assertSame('succeeded', $this->payments(40)[0]['status']);
    }

    /** @return list<array{string}> API discovery paths using the same receipt transition. */
    public static function receiptRecoveryPaths(): array
    {
        return [['return'], ['prior'], ['worker'], ['snapshot']];
    }

    #[DataProvider('receiptRecoveryPaths')]
    public function testApiRecoveryAcrossCopenhagenNewYearKeepsStripeChargeDate(string $path): void
    {
        $this->setting('mode', 'live');
        $this->setting('posting_enabled', 'on');
        $this->stripe->liveMode = true;
        $this->now = strtotime('2026-12-30 10:00:00 Europe/Copenhagen');
        $this->stripe->now = $this->now;
        $start = $this->service->startPayment(10, false);
        $received = strtotime('2026-12-31 23:59:00 Europe/Copenhagen');
        $this->stripe->succeed($start['payment_intent']);
        $this->stripe->intents[$start['payment_intent']]['latest_charge'] = ['id' => 'ch_december', 'created' => $received];
        $this->now = strtotime('2027-01-01 01:30:00 Europe/Copenhagen');
        if ($path === 'return') $this->service->confirmReturn(10, $start['payment_intent']);
        elseif ($path === 'prior') $this->assertSame('succeeded', $this->service->startPayment(10, false)['status']);
        elseif ($path === 'worker') $this->assertSame(1, $this->service->reconcileIntents());
        else {
            $pi = $this->stripe->intents[$start['payment_intent']];
            $pi['latest_charge'] = 'ch_december';
            $this->service->paymentIntentEvent($pi);
        }
        $row = $this->payments(10)[0];
        $this->assertSame($received, (int)$row['received_at']);
        $this->assertSame('posted', $row['settlement']);
        $this->assertSame(['2026-12-31'], array_values(array_unique(array_column($this->db->rows('SELECT transdate FROM transaktioner'), 'transdate'))));
        foreach ($this->stripe->callsTo('GET', '/v1/payment_intents/') as $call) $this->assertSame(['latest_charge'], $call[2]['expand']);
        // 20261012 CL/LH A later signed success cannot move the committed receipt date.
        $this->eventAt('payment_intent.succeeded', $this->stripe->intents[$start['payment_intent']], $this->now);
        $this->assertSame($received, (int)$this->payments(10)[0]['received_at']);
    }

    public function testApiRecoveryFallsBackToIntentCreationWhenChargeTimeIsUnavailable(): void
    {
        $this->now = strtotime('2026-12-31 23:59:00 Europe/Copenhagen');
        $this->stripe->now = $this->now;
        $start = $this->service->startPayment(10, false);
        $created = $this->now;
        $this->stripe->succeed($start['payment_intent']);
        $this->now = strtotime('2027-01-01 01:30:00 Europe/Copenhagen');
        $snapshot = $this->stripe->intents[$start['payment_intent']];
        unset($snapshot['created']);
        $snapshot['latest_charge'] = null;
        $this->service->paymentIntentEvent($snapshot);
        $this->service->confirmReturn(10, $start['payment_intent']);
        $this->assertSame($created, (int)$this->payments(10)[0]['received_at']);
    }

    public function testManualFailureProjectsActualRestDeclineWithoutHandSeededStatus(): void
    {
        require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentReadModel.php';
        $start = $this->service->startPayment(10, false);
        $pi = $this->stripe->intents[$start['payment_intent']];
        $pi['last_payment_error'] = ['code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'message' => 'Declined'];
        $this->eventAt('payment_intent.payment_failed', $pi, $this->now);
        $this->assertSame('requires_payment_method', $this->payments(10)[0]['status']);
        $model = new \Saldi\Stripe\InvoicePaymentReadModel($this->db->pdo);
        $decline = $model->debtorStatuses([1])[1]['lastDecline'];
        $this->assertSame(['orderId', 'invoiceNo', 'kind', 'failedAt', 'code', 'hard'], array_keys($decline));
        $this->assertSame(10, $decline['orderId']);
        $this->assertSame('1001', $decline['invoiceNo']);
        $this->assertSame('manual', $decline['kind']);
        $this->assertSame('insufficient_funds', $decline['code']);
        $this->assertFalse($decline['hard']);
        $this->assertSame((new DateTimeImmutable('@' . $this->now))->setTimezone(new DateTimeZone('Europe/Copenhagen'))->format(DATE_ATOM), $decline['failedAt']);
        $this->now++;
        $pi['last_payment_error'] = ['type' => 'card_error', 'code' => 'transaction_not_allowed', 'message' => 'Card failure'];
        $this->eventAt('payment_intent.payment_failed', $pi, $this->now);
        $this->assertSame('transaction_not_allowed', (new \Saldi\Stripe\InvoicePaymentReadModel($this->db->pdo))->debtorStatuses([1])[1]['lastDecline']['code']);
        $this->now++;
        $pi['last_payment_error'] = ['type' => 'invalid_request_error', 'code' => 'parameter_invalid_integer', 'message' => 'Configuration failure'];
        $this->eventAt('payment_intent.payment_failed', $pi, $this->now);
        $this->assertNull((new \Saldi\Stripe\InvoicePaymentReadModel($this->db->pdo))->debtorStatuses([1])[1]['lastDecline']);
        $this->now++;
        $pi['last_payment_error'] = ['code' => 'api_key_invalid', 'message' => 'Configuration failure'];
        $this->eventAt('payment_intent.payment_failed', $pi, $this->now);
        $this->assertNull((new \Saldi\Stripe\InvoicePaymentReadModel($this->db->pdo))->debtorStatuses([1])[1]['lastDecline']);
    }

    /** @return list<array{int,string}> Definitive HTTP rejection and explicit local no-dispatch refusals. */
    public static function configurationRefusals(): array
    {
        return [[401, 'api_key_invalid'], [0, 'config_key'], [0, 'config_mode_mismatch']];
    }

    #[DataProvider('configurationRefusals')]
    public function testConfigurationRepairAfterTwentyHoursResumesCollectionWithNewOperationKey(int $status, string $code): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->stripe->intercept = static function ($method, $path) use ($status, $code) {
            if ($method === 'POST' && $path === '/v1/payment_intents') return ['ok' => false, 'status' => $status, 'error_code' => $code, 'error' => 'Configuration failure', 'body' => $status === 0 ? null : ['error' => ['type' => 'authentication_error']]];
            return null;
        };
        $this->service->autopay();
        $payment = $this->payments(11)[0];
        $rejected = $this->db->row("SELECT * FROM stripe_invoice_operations WHERE payment_id=? AND kind='intent_create'", [$payment['id']]);
        $this->assertSame('rejected', $rejected['status']);
        $this->now += 21 * 3600;
        $this->stripe->now = $this->now;
        $this->stripe->intercept = null;
        $this->assertSame(0, $this->service->expireAmbiguousOperations());
        $this->assertSame(1, $this->service->autopay()['charged']);
        $this->assertCount(1, $this->payments(11));
        $this->assertSame('succeeded', $this->payments(11)[0]['status']);
        $operations = $this->db->rows("SELECT * FROM stripe_invoice_operations WHERE payment_id=? AND kind='intent_create' ORDER BY id", [$payment['id']]);
        $this->assertCount(2, $operations);
        $this->assertNotSame($operations[0]['idempotency_key'], $operations[1]['idempotency_key']);
        $this->assertSame($rejected['request_json'], $operations[1]['request_json']);
        $this->assertNotContains('Betaling skal afstemmes hos Stripe', $this->alertSubjects());
    }

    public function testNewConsentOnReusedIntentAfterRevocationUsesCurrentGeneration(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $start = $this->service->startPayment(11, false);
        $this->now += 86400;
        $this->service->removeCard(1, 'kunde');
        $generation = (int)$this->db->row('SELECT generation FROM stripe_invoice_cards')['generation'];
        $this->now += 4 * 86400;
        $reused = $this->service->startPayment(11, true);
        $this->assertSame($start['payment_intent'], $reused['payment_intent']);
        $this->assertSame($generation, (int)$this->payments(11)[0]['card_generation']);
        $this->eventAt('payment_intent.succeeded', $this->stripe->succeed($reused['payment_intent'], 'pm_card2'), $this->now);
        $this->assertSame('pm_card2', $this->service->card(1)['payment_method_id']);
        $this->assertSame($this->now, (int)$this->service->card(1)['consent_at']);
    }

    public function testLaterGrantOnOlderIntentOrdersCardsByConsentInsteadOfIntentCreation(): void
    {
        $old = $this->service->startPayment(10, false);
        $this->now += 86400;
        $this->stripe->now = $this->now;
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-08', 0, 200.0);
        $this->payAndSaveCard(11);
        $previousConsent = (int)$this->service->card(1)['consent_at'];
        $this->now += 4 * 86400;
        $this->service->startPayment(10, true);
        $this->eventAt('payment_intent.succeeded', $this->stripe->succeed($old['payment_intent'], 'pm_card2'), $this->now);
        $this->assertSame('pm_card2', $this->service->card(1)['payment_method_id']);
        $this->assertSame($this->now, (int)$this->service->card(1)['consent_at']);
        $this->assertGreaterThan($previousConsent, (int)$this->service->card(1)['consent_at']);
    }

    public function testFreshOperationDeclineAfterConfigurationRepairKeepsOriginalRejection(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->stripe->intercept = static function ($method, $path) {
            if ($path === '/v1/payment_intents') return ['ok' => false, 'status' => 401, 'error_code' => 'api_key_invalid', 'error' => 'Configuration failure', 'body' => ['error' => ['type' => 'authentication_error']]];
            return null;
        };
        $this->service->autopay();
        $this->now += 21 * 3600;
        $this->stripe->intercept = null;
        $this->stripe->decline = 'insufficient_funds';
        $this->assertSame(1, $this->service->autopay()['failed']);
        $payment = $this->payments(11)[0];
        $this->assertSame('failed', $payment['status']);
        $this->assertSame('insufficient_funds', $payment['error_code']);
        $ops = $this->db->rows("SELECT status FROM stripe_invoice_operations WHERE payment_id=? AND kind='intent_create' ORDER BY id", [$payment['id']]);
        $this->assertSame(['rejected', 'declined'], array_column($ops, 'status'));
        $this->assertNotContains('Betaling skal afstemmes hos Stripe', $this->alertSubjects());
    }

    public function testCardRemovalCancelsDefinitivelyRejectedAttemptWithoutParkingIt(): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $this->stripe->intercept = static function ($method, $path) {
            if ($path === '/v1/payment_intents') return ['ok' => false, 'status' => 401, 'error_code' => 'api_key_invalid', 'error' => 'Configuration failure', 'body' => ['error' => ['type' => 'authentication_error']]];
            return null;
        };
        $this->service->autopay();
        $payment = $this->payments(11)[0];
        $this->service->removeCard(1, 'kunde');
        $this->assertSame('canceled', $this->payments(11)[0]['status']);
        $this->assertSame('rejected', $this->db->row("SELECT status FROM stripe_invoice_operations WHERE payment_id=? AND kind='intent_create'", [$payment['id']])['status']);
        $this->assertNotContains('Betaling skal afstemmes hos Stripe', $this->alertSubjects());
    }

    /** @return list<array{int}> A technical or card rejection after a successful charge response was lost. */
    public static function ambiguousRetryRejections(): array
    {
        return [[401], [402]];
    }

    #[DataProvider('ambiguousRetryRejections')]
    public function testLostChargeSuccessThenRejectionAndRepairNeverCreatesSecondCharge(int $status): void
    {
        $this->payAndSaveCard();
        self::addInvoice($this->db->pdo, 11, '1002', '2026-10-07', 0, 200.0);
        $phase = 'timeout';
        $posts = [];
        $worker = new InvoicePaymentService($this->db, function ($method, $path, $params, $opts) use (&$phase, &$posts, $status) {
            if ($method !== 'POST' || $path !== '/v1/payment_intents') return $this->stripe->transport($method, $path, $params, $opts);
            $posts[] = [$params, $opts];
            if ($phase === 'rejection') {
                $error = $status === 401 ? ['type' => 'authentication_error'] : [
                    'type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'insufficient_funds',
                    'payment_intent' => ['id' => 'pi_later', 'payment_method' => 'pm_card1']];
                return ['ok' => false, 'status' => $status, 'error_code' => $status === 401 ? 'api_key_invalid' : 'card_declined', 'error' => 'Retry rejected', 'body' => ['error' => $error]];
            }
            $response = $this->stripe->transport($method, $path, $params, $opts);
            if ($phase === 'timeout') return ['ok' => false, 'status' => 0, 'error_code' => 'network', 'error' => 'Timeout after success', 'body' => []];
            return $response;
        }, new InvoicePaymentLedger($this->db), static function () {}, 'saldi_test');
        $this->assertSame(1, $worker->autopay()['skipped']);
        $payment = $this->payments(11)[0];
        $original = $this->db->row("SELECT * FROM stripe_invoice_operations WHERE payment_id=? AND kind='intent_create'", [$payment['id']]);
        $this->assertSame(1, (int)$original['ambiguous_dispatch']);
        $this->assertNull($payment['payment_intent_id']);
        $phase = 'rejection';
        $this->now += InvoicePaymentService::RESEND_SECONDS;
        $this->assertSame(1, $worker->autopay()['skipped']);
        $this->assertCount(2, $posts);
        $this->assertSame($posts[0], $posts[1]);
        $this->assertSame('needs_reconcile', $this->db->row('SELECT status FROM stripe_invoice_operations WHERE id=?', [$original['id']])['status']);
        $this->assertSame('created', $this->payments(11)[0]['status']);
        $this->assertNull($this->service->card(1)['broken_at']);
        $phase = 'repaired';
        foreach ([InvoicePaymentService::RESEND_SECONDS, 21 * 3600] as $advance) {
            $this->now += $advance;
            $this->assertSame(0, $worker->autopay()['charged']);
            $this->assertCount(2, $posts, 'Repair must not POST the charge again');
        }
        $this->assertCount(1, $this->payments(11));
        $this->assertSame(1, (int)$this->db->row("SELECT COUNT(*) AS n FROM stripe_invoice_operations WHERE payment_id=? AND kind='intent_create'", [$payment['id']])['n']);
        $successes = array_values(array_filter($this->stripe->intents, static fn($pi) => ($pi['metadata']['saldi_order_id'] ?? '') === '11'));
        $this->assertCount(1, $successes);
        $this->assertSame('succeeded', $successes[0]['status']);
        // 20261012 CL/LH Stored payment metadata reconciles the original success, including after replay has stopped.
        $this->assertSame('succeeded', $this->eventAt('payment_intent.succeeded', $successes[0], $this->now - 21 * 3600));
        $resolved = $this->db->row('SELECT * FROM stripe_invoice_operations WHERE id=?', [$original['id']]);
        $this->assertSame('succeeded', $resolved['status']);
        $this->assertSame($successes[0]['id'], $resolved['result_id']);
        $this->assertSame($original['idempotency_key'], $resolved['idempotency_key']);
        $this->assertSame(1, (int)$resolved['ambiguous_dispatch']);
        $this->assertSame('succeeded', $this->payments(11)[0]['status']);
        $worker->autopay();
        $this->assertCount(2, $posts);
    }

    /** @return list<array{string}> Stripe failure-time sources, including the no-time fallback. */
    public static function recoveredFailureSources(): array
    {
        return [['latest_charge'], ['error_charge_object'], ['error_charge_id'], ['no_time']];
    }

    #[DataProvider('recoveredFailureSources')]
    public function testRecoveryPollingPreservesFailureTimeAndAcceptsDelayedSignedFacts(string $source): void
    {
        require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentReadModel.php';
        $start = $this->service->startPayment(10, false);
        $id = $start['payment_intent'];
        $failedAt = $this->now + 120;
        $charge = ['id' => 'ch_declined', 'created' => $failedAt];
        $pi = &$this->stripe->intents[$id];
        $pi['last_payment_error'] = ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'message' => 'Declined'];
        $pi['latest_charge'] = $source === 'latest_charge' ? $charge : null;
        if ($source === 'error_charge_object') $pi['last_payment_error']['charge'] = $charge;
        if ($source === 'error_charge_id') {
            $pi['latest_charge'] = ['id' => 'ch_other', 'created' => $failedAt + 60];
            $pi['last_payment_error']['charge'] = $charge['id'];
            $this->stripe->intercept = static function ($method, $path) use ($charge) {
                return $method === 'GET' && $path === '/v1/charges/ch_declined' ? ['ok' => true, 'status' => 200, 'body' => $charge] : null;
            };
        }
        $this->setting('invoice_pay_enabled', 'off');
        $this->setting('autopay_enabled', 'off');
        $this->now += 3600;
        if ($source === 'no_time') $failedAt = $this->now;
        $this->assertSame(1, $this->service->reconcileIntents());
        $first = $this->payments(10)[0];
        $this->assertSame($failedAt, (int)$first['failed_at']);
        $this->assertSame($source === 'no_time' ? null : 'ch_declined', $first['failed_charge']);
        $this->assertNull($first['event_created']);
        $decline = (new \Saldi\Stripe\InvoicePaymentReadModel($this->db->pdo))->debtorStatuses([1])[1]['lastDecline'];
        for ($i = 0; $i < 2; $i++) {
            $this->now += 3600;
            $this->assertSame(1, $this->service->reconcileIntents());
            $row = $this->payments(10)[0];
            $this->assertSame($failedAt, (int)$row['failed_at']);
            $this->assertNull($row['event_created']);
            $this->assertSame($decline, (new \Saldi\Stripe\InvoicePaymentReadModel($this->db->pdo))->debtorStatuses([1])[1]['lastDecline']);
        }
        // 20261012 CL/LH Delayed signed facts are older than the latest poll, but polls cannot make them stale.
        $signedAt = $failedAt + 10;
        $pi['last_payment_error']['decline_code'] = 'transaction_not_allowed';
        $pi['last_payment_error']['advice_code'] = 'do_not_try_again';
        $this->assertSame('requires_payment_method', $this->eventAt('payment_intent.payment_failed', $pi, $signedAt));
        $row = $this->payments(10)[0];
        $this->assertSame($signedAt, (int)$row['failed_at']);
        $this->assertSame($signedAt, (int)$row['event_created']);
        $this->assertSame('transaction_not_allowed', $row['error_code']);
        $this->assertSame('do_not_try_again', $row['advice_code']);
        // 20261012 CL/LH A sparse signed snapshot must not erase the charge identity learned during recovery.
        $sparse = $pi;
        unset($sparse['latest_charge'], $sparse['last_payment_error']['charge']);
        $this->eventAt('payment_intent.payment_failed', $sparse, $signedAt + 1);
        $this->now += 3600;
        $this->assertSame(1, $this->service->reconcileIntents());
        $row = $this->payments(10)[0];
        $this->assertSame($signedAt + 1, (int)$row['failed_at']);
        $this->assertSame($signedAt + 1, (int)$row['event_created']);
        $this->assertSame($first['failed_charge'], $row['failed_charge']);
        $this->assertCount(1, $this->stripe->callsTo('POST', '/v1/payment_intents'));
    }

    public function testRecoveryKeepsSignedFailureTimeAndDetectsNewChargeWithSameCode(): void
    {
        $start = $this->service->startPayment(10, false);
        $pi = &$this->stripe->intents[$start['payment_intent']];
        $signedAt = $this->now + 60;
        $pi['latest_charge'] = ['id' => 'ch_first', 'created' => $signedAt - 1];
        $pi['last_payment_error'] = ['type' => 'card_error', 'decline_code' => 'insufficient_funds'];
        $this->eventAt('payment_intent.payment_failed', $pi, $signedAt);
        $this->now += 3600;
        $this->assertSame(1, $this->service->reconcileIntents());
        $this->assertSame($signedAt, (int)$this->payments(10)[0]['failed_at']);
        $this->assertSame($signedAt, (int)$this->payments(10)[0]['event_created']);
        $secondAt = $this->now + 60;
        $pi['latest_charge'] = ['id' => 'ch_second', 'created' => $secondAt];
        $this->now += 3600;
        $this->assertSame(1, $this->service->reconcileIntents());
        $this->assertSame($secondAt, (int)$this->payments(10)[0]['failed_at']);
        $this->assertSame('ch_second', $this->payments(10)[0]['failed_charge']);
        $this->assertSame($signedAt, (int)$this->payments(10)[0]['event_created']);
        $pi['last_payment_error']['decline_code'] = 'expired_card';
        $this->assertSame('requires_payment_method', $this->eventAt('payment_intent.payment_failed', $pi, $secondAt + 1));
        $this->assertSame('expired_card', $this->payments(10)[0]['error_code']);
    }

    public function testApiProcessingAndSuccessNeverAdvanceSignedEventWatermark(): void
    {
        $start = $this->service->startPayment(10, false);
        $pi = &$this->stripe->intents[$start['payment_intent']];
        $pi['status'] = 'processing';
        $signedAt = $this->now;
        $this->eventAt('payment_intent.processing', $pi, $signedAt);
        $this->now += 3600;
        $this->assertSame('processing', $this->service->paymentIntentEvent($pi));
        $this->assertSame($signedAt, (int)$this->payments(10)[0]['event_created']);
        $this->assertSame('succeeded', $this->service->paymentIntentEvent($this->stripe->succeed($start['payment_intent'])));
        $this->assertSame($signedAt, (int)$this->payments(10)[0]['event_created']);
    }

    public function testUnexpandedApiFailureRetrievesStripeChargeTimeBeforeRecording(): void
    {
        $start = $this->service->startPayment(10, false);
        $id = $start['payment_intent'];
        $failedAt = $this->now + 120;
        $pi = $this->stripe->intents[$id];
        $pi['latest_charge'] = 'ch_declined';
        $pi['last_payment_error'] = ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'charge' => 'ch_declined'];
        $expanded = $pi;
        $expanded['latest_charge'] = ['id' => 'ch_declined', 'created' => $failedAt];
        $this->stripe->intercept = function ($method, $path, $params) use ($id, $expanded) {
            if ($method === 'GET' && $path === '/v1/payment_intents/' . $id) {
                $this->assertSame(['latest_charge'], $params['expand']);
                return ['ok' => true, 'status' => 200, 'body' => $expanded];
            }
            return null;
        };
        $this->now += 3600;
        $this->assertSame('requires_payment_method', $this->service->paymentIntentEvent($pi));
        $row = $this->payments(10)[0];
        $this->assertSame($failedAt, (int)$row['failed_at']);
        $this->assertSame('ch_declined', $row['failed_charge']);
        $this->assertNull($row['event_created']);
        $this->assertCount(1, $this->stripe->callsTo('GET', '/v1/payment_intents/' . $id));
        $this->assertSame([], $this->stripe->callsTo('GET', '/v1/charges/'));
    }

}
