<?php
// 20261012 CL/LH REST read-model contract, immutable ordering, reconciliation and tenant isolation.
// 20261013 CL/LH Verify due-date parity with settled history, outstanding items and the worker fallback.
// 20261013 CL/LH (CodeRabbit) Regress blank and NULL invoice numbers against unrelated debtor open items.

use PHPUnit\Framework\TestCase;
use Saldi\Stripe\InvoicePaymentReadModel;
use Saldi\Stripe\InvoicePaymentSchema;
use Saldi\Stripe\InvoicePaymentService;
use Saldi\Stripe\InvoicePaymentLedger;

require_once __DIR__ . '/support/InvoicePaymentDatabase.php';
require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentReadModel.php';
require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentService.php';
require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentLedger.php';
require_once __DIR__ . '/../../restapi/core/InvoicePaymentReadContext.php';

/** Counts executed queries rather than asserting an implementation-specific SQL string. */
final class InvoicePaymentReadCountingStatement extends PDOStatement
{
    private stdClass $counter;
    protected function __construct(stdClass $counter) { $this->counter = $counter; }
    public function execute(?array $params = null): bool
    {
        $this->counter->queries++;
        return parent::execute($params);
    }
}

final class InvoicePaymentReadModelTest extends TestCase
{
    use InvoicePaymentDatabase;

    private function fixture(bool $provision = true): void
    {
        foreach ([
            'CREATE TABLE settings (var_grp TEXT,var_name TEXT,var_value TEXT)',
            'CREATE TABLE adresser (id INTEGER PRIMARY KEY,pbs TEXT,stripe_fravalg TEXT,art TEXT,firmanavn TEXT,addr1 TEXT,postnr TEXT,bynavn TEXT,cvrnr TEXT,email TEXT,bank_reg TEXT,bank_konto TEXT)',
            'CREATE TABLE ordrer (id INTEGER PRIMARY KEY,konto_id INTEGER,art TEXT,status INTEGER,fakturanr TEXT,fakturadate TEXT,due_date TEXT,betalingsbet TEXT,betalingsdage INTEGER,sum NUMERIC,moms NUMERIC,valuta TEXT,email TEXT,betalt TEXT)',
            'CREATE TABLE openpost (id INTEGER PRIMARY KEY,konto_id INTEGER,faktnr TEXT,refnr INTEGER,amount NUMERIC,udlignet TEXT,forfaldsdate TEXT)',
            'CREATE TABLE ordrelinjer (id INTEGER PRIMARY KEY,ordre_id INTEGER,posnr INTEGER,varenr TEXT,beskrivelse TEXT,enhed TEXT,antal NUMERIC,pris NUMERIC,rabat NUMERIC)',
        ] as $sql) $this->db->query($sql);
        foreach (['mode' => 'live', 'invoice_pay_enabled' => 'on', 'autopay_enabled' => 'on'] as $key => $value) {
            $this->db->query("INSERT INTO settings VALUES ('stripe',?,?)", [$key, $value]);
        }
        $this->db->query("INSERT INTO adresser (id,pbs,stripe_fravalg,art) VALUES (1,'','','D'),(2,'','','D')");
        $this->db->query("INSERT INTO ordrer (id,konto_id,art,status,fakturanr,fakturadate,due_date,betalingsbet,betalingsdage,sum,moms,valuta,email,betalt) VALUES (10,1,'DO',3,'100','2026-10-01',NULL,'netto',14,100,0,'DKK','',''),(11,1,'DO',3,'101','2026-10-02',NULL,'netto',14,100,0,'DKK','','1'),(12,2,'DO',3,'100','2026-10-01',NULL,'netto',14,100,0,'DKK','','')");
        $this->db->query("INSERT INTO openpost VALUES (1,1,'100',10,100,'0','2026-10-20'),(2,1,'101',11,100,'0',NULL),(3,2,'100',12,900,'0','2026-10-30')");
        if ($provision) {
            foreach (InvoicePaymentSchema::statements($this->db->driver()) as $sql) $this->db->query($sql);
        }
    }

    protected function setUp(): void { $this->openDatabase(); }

    private function model(): InvoicePaymentReadModel { return new InvoicePaymentReadModel($this->db->pdo); }

    private function card(string $mode = 'live', int $konto = 1): void
    {
        $this->db->query('INSERT INTO stripe_invoice_cards (mode,konto_id,customer_id,payment_method_id,brand,last4,exp_month,exp_year,autopay,consent_at,consent_order_id,terms_version,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$mode, $konto, 'cus_fixture', 'pm_fixture', 'visa', '4242', 12, 2030, 1, 1790841600, 10, 'v1-2026-10', 1790841600]);
    }

    private function payment(int $id, array $changes = []): void
    {
        $p = array_replace(['id' => $id, 'mode' => 'live', 'order_id' => 10, 'konto_id' => 1, 'kind' => 'manual', 'status' => 'succeeded',
            'amount_ore' => 5000, 'received_at' => 1790841600, 'failed_at' => null, 'open_ore_at_receipt' => 10000,
            'collection_hold' => 0, 'error_code' => null, 'settlement' => 'manual', 'created_at' => 1790841500, 'updated_at' => 1790841600], $changes);
        $this->db->query('INSERT INTO stripe_invoice_payments (' . implode(',', array_keys($p)) . ') VALUES (' . implode(',', array_fill(0, count($p), '?')) . ')', array_values($p));
    }

    public function testMissingTablesReturnNullWithoutDdlOrAbortingTransaction(): void
    {
        $this->fixture(false);
        $this->db->pdo->beginTransaction();
        $model = $this->model();
        $this->assertSame([1 => null, 2 => null], $model->debtorStatuses([1, 2]));
        $this->assertSame([10 => null], $model->invoicePayments([10]));
        $this->assertSame(2, (int)$this->db->row('SELECT COUNT(*) AS n FROM adresser')['n']);
        $this->assertTrue($this->db->pdo->inTransaction());
        $this->db->pdo->rollBack();
        $this->assertSame([1 => null], $this->model()->debtorStatuses([1]));
    }

    public function testMissingOptoutOrEventTimeCapabilityAndUnconfiguredModeReturnNull(): void
    {
        $this->fixture();
        $this->db->query("UPDATE settings SET var_value='' WHERE var_name='mode'");
        $this->assertNull($this->model()->debtorStatuses([1])[1]);
        $this->db->query("UPDATE settings SET var_value='live' WHERE var_name='mode'");
        $this->db->query('ALTER TABLE adresser RENAME COLUMN stripe_fravalg TO unavailable_optout');
        $this->assertNull($this->model()->debtorStatuses([1])[1]);
        $this->db->query('ALTER TABLE adresser RENAME COLUMN unavailable_optout TO stripe_fravalg');
        $this->db->query('ALTER TABLE stripe_invoice_payments RENAME COLUMN received_at TO unavailable_received');
        $this->assertNull($this->model()->invoicePayments([10])[10]);
    }

    public function testEmptySchemaHasObjectsNullFactsAndDefaultOffSwitches(): void
    {
        $this->fixture();
        $this->db->query("DELETE FROM settings WHERE var_name<>'mode'");
        $status = $this->model()->debtorStatuses([1])[1];
        $this->assertFalse($status['invoicePayEnabled']);
        $this->assertFalse($status['autopayEnabled']);
        $this->assertFalse($status['eligible']);
        $this->assertSame(['disabled', 'autopay_disabled', 'no_card'], $status['blockedReasons']);
        foreach (['card', 'lastPayment', 'lastDecline'] as $key) $this->assertNull($status[$key]);
        $this->assertSame(0, $status['unresolvedSettlementCount']);
        $this->assertSame([], $this->model()->debtorStatuses([]));
        $this->assertSame([], $this->model()->invoicePayments([]));
    }

    public function testEveryBlockedReasonUsesSharedEligibilityAndRetainsBrokenRevokedCard(): void
    {
        $this->fixture();
        $this->card();
        $this->assertTrue($this->model()->debtorStatuses([1])[1]['eligible']);
        $this->db->query('CREATE TABLE stripe_customers (konto_id INTEGER,status TEXT)');
        $this->db->query("INSERT INTO stripe_customers VALUES (1,'past_due')");
        $this->db->query("UPDATE adresser SET pbs=' ON ',stripe_fravalg=' yes ' WHERE id=1");
        $this->db->query("UPDATE settings SET var_value='off' WHERE var_name<>'mode'");
        $this->db->query("UPDATE stripe_invoice_cards SET autopay=0,broken_at=1790841600,broken_reason='expired_card',revoked_at=1790841700");
        $status = $this->model()->debtorStatuses([1])[1];
        $this->assertSame(['pbs', 'subscription', 'optout', 'disabled', 'autopay_disabled', 'no_card', 'card_broken', 'card_revoked'], $status['blockedReasons']);
        $this->assertFalse($status['eligible']);
        $this->assertSame('4242', $status['card']['last4']);
        $this->assertSame('expired_card', $status['card']['brokenReason']);
        $this->assertNotNull($status['card']['revokedAt']);
    }

    public function testCurrentModeIsolationAndOrderDebtorIdentity(): void
    {
        $this->fixture();
        $this->card('test');
        $this->payment(1, ['mode' => 'test']);
        $this->payment(2, ['order_id' => 12]); // wrong debtor for this order
        $model = $this->model();
        $status = $model->debtorStatuses([1])[1];
        $this->assertNull($status['card']);
        $this->assertNull($status['lastPayment']);
        $this->assertSame(0, $model->invoicePayments([10, 12])[12]['receivedUnpostedOre']);
        $this->db->query("UPDATE settings SET var_value='test' WHERE var_name='mode'");
        $this->assertSame('4242', $this->model()->debtorStatuses([1])[1]['card']['last4']);
        $this->assertSame(5000, $this->model()->invoicePayments([10])[10]['receivedUnpostedOre']);
    }

    public function testLatestReceiptAndDeclineUseImmutableTimesAndIdTiebreak(): void
    {
        $this->fixture();
        $this->payment(1, ['received_at' => 100, 'updated_at' => 99999]);
        $this->payment(2, ['received_at' => 200, 'order_id' => 11]);
        $this->payment(3, ['received_at' => 200, 'kind' => 'auto']);
        $this->payment(4, ['received_at' => null]); // unknown historical time sorts last
        $this->payment(5, ['status' => 'failed', 'received_at' => null, 'failed_at' => 300, 'error_code' => 'expired_card']);
        $this->payment(6, ['status' => 'failed', 'received_at' => null, 'failed_at' => 400, 'order_id' => 11, 'error_code' => 'insufficient_funds']);
        $this->payment(7, ['status' => 'failed', 'received_at' => null, 'failed_at' => 400, 'kind' => 'auto', 'error_code' => 'lost_card']);
        $this->payment(8, ['status' => 'canceled', 'received_at' => null, 'failed_at' => 500, 'error_code' => 'expired_card']);
        $this->payment(9, ['status' => 'failed', 'received_at' => null, 'failed_at' => 600, 'error_code' => 'network']);
        $status = $this->model()->debtorStatuses([1])[1];
        $this->assertSame('auto', $status['lastPayment']['kind']);
        $this->assertSame(10, $status['lastPayment']['orderId']);
        $this->assertSame('auto', $status['lastDecline']['kind']);
        $this->assertSame('lost_card', $status['lastDecline']['code']);
        $this->assertTrue($status['lastDecline']['hard']);
        $this->db->query('UPDATE stripe_invoice_payments SET settlement=\'posted\',updated_at=999999 WHERE id=1');
        $this->assertSame($status['lastPayment'], $this->model()->debtorStatuses([1])[1]['lastPayment']);
    }

    public function testHistoricalFactTimesRemainNullAndFreeFormDiagnosticsAreNotExposed(): void
    {
        $this->fixture();
        $this->payment(1, ['received_at' => null]);
        $this->payment(2, ['status' => 'failed', 'received_at' => null, 'failed_at' => null, 'error_code' => 'card_declined']);
        $status = $this->model()->debtorStatuses([1])[1];
        $this->assertNull($status['lastPayment']['receivedAt']);
        $this->assertNull($status['lastDecline']['failedAt']);
        $this->assertSame('card_declined', $status['lastDecline']['code']);
        $this->assertFalse($status['lastDecline']['hard']);
        $this->card();
        $this->db->query("UPDATE stripe_invoice_cards SET broken_reason='request payload with private diagnostics'");
        $this->assertNull($this->model()->debtorStatuses([1])[1]['card']['brokenReason']);
    }

    public function testLedgerPaidUsesPositiveOpenpostAndOpenOreIncludesPartialPayments(): void
    {
        $this->fixture();
        $this->db->query("INSERT INTO openpost VALUES (4,1,'100',10,-25,'0',NULL)");
        $blocks = $this->model()->invoicePayments([10, 11, 12]);
        $this->assertSame(7500, $blocks[10]['ledgerOpenOre']);
        $this->assertSame(90000, $blocks[12]['ledgerOpenOre']);
        $this->assertFalse($blocks[11]['ledgerPaid'], 'betalt=1 cannot override openpost');
        $this->db->query("UPDATE openpost SET udlignet='1' WHERE konto_id=1 AND faktnr='100'");
        $paid = $this->model()->invoicePayments([10])[10];
        $this->assertTrue($paid['ledgerPaid']);
        $this->assertSame(0, $paid['ledgerOpenOre']);
        $this->db->query("DELETE FROM openpost WHERE konto_id=1 AND faktnr='100'");
        $this->assertFalse($this->model()->invoicePayments([10])[10]['ledgerPaid'], 'No positive posting is not proof of payment');
    }

    public function testOrdersWithoutInvoiceNumbersHaveNoLedgerFacts(): void
    {
        $this->fixture();
        $this->db->query("INSERT INTO openpost VALUES (4,1,'',10,50,'0','2026-10-20'),(5,1,'',10,100,'1','2026-10-21'),(6,1,'   ',10,75,'0','2026-10-22'),(7,1,'   ',10,100,'1','2026-10-23')");
        foreach (['', '   ', null] as $invoiceNumber) {
            $this->db->query('UPDATE ordrer SET fakturanr=? WHERE id=10', [$invoiceNumber]);
            $blocks = $this->model()->invoicePayments([10, 11]);
            $this->assertFalse($blocks[10]['ledgerPaid']);
            $this->assertSame(0, $blocks[10]['ledgerOpenOre']);
            $this->assertSame(10000, $blocks[11]['ledgerOpenOre'], 'Numbered invoices in the same batch retain their ledger facts');
        }
        // 20261013 CL/LH (CodeRabbit) Settled blank-number items are not evidence that an unnumbered order is paid.
        $this->db->query("UPDATE ordrer SET fakturanr='' WHERE id=10");
        $this->db->query("UPDATE openpost SET udlignet='1' WHERE konto_id=1 AND faktnr=''");
        $block = $this->model()->invoicePayments([10])[10];
        $this->assertFalse($block['ledgerPaid']);
        $this->assertSame(0, $block['ledgerOpenOre']);
    }

    public function testManualReconciliationIsReadOnlyForSettledAndPartialLedgerReceipts(): void
    {
        $this->fixture();
        $this->payment(1);
        $this->assertSame(5000, $this->model()->invoicePayments([10])[10]['receivedUnpostedOre']);
        $this->db->query('UPDATE openpost SET amount=75 WHERE id=1');
        $this->assertSame(5000, $this->model()->invoicePayments([10])[10]['receivedUnpostedOre']);
        $this->db->query('UPDATE openpost SET amount=50 WHERE id=1');
        $before = $this->snapshot();
        $status = $this->model()->debtorStatuses([1])[1];
        $this->assertSame('posted_manual', $status['lastPayment']['settlement']);
        $this->assertSame(0, $status['unresolvedSettlementCount']);
        $this->assertSame(0, $this->model()->invoicePayments([10])[10]['receivedUnpostedOre']);
        $this->assertSame($before, $this->snapshot());
        $this->db->query("UPDATE openpost SET amount=100,udlignet='1' WHERE id=1");
        $this->db->query('UPDATE stripe_invoice_payments SET open_ore_at_receipt=NULL');
        $this->assertSame('posted_manual', $this->model()->debtorStatuses([1])[1]['lastPayment']['settlement']);
    }

    public function testPendingErrorAndManualReceiptsCountWhilePostedReceiptsDoNot(): void
    {
        $this->fixture();
        foreach (['manual', 'pending', 'error', 'posted'] as $i => $state) $this->payment($i + 1, ['settlement' => $state]);
        $this->assertSame(3, $this->model()->debtorStatuses([1])[1]['unresolvedSettlementCount']);
        $this->assertSame(15000, $this->model()->invoicePayments([10])[10]['receivedUnpostedOre']);
    }

    public function testProcessingHoldAndLatestAttemptAreIndependentAndModeScoped(): void
    {
        $this->fixture();
        $this->payment(1, ['status' => 'processing', 'received_at' => null, 'created_at' => 100]);
        $this->payment(2, ['status' => 'failed', 'received_at' => null, 'failed_at' => 250, 'created_at' => 200, 'collection_hold' => 1, 'error_code' => 'insufficient_funds']);
        $this->payment(3, ['mode' => 'test', 'status' => 'canceled', 'created_at' => 900]);
        $block = $this->model()->invoicePayments([10])[10];
        $this->assertTrue($block['processing']);
        $this->assertTrue($block['collectionHold']);
        $this->assertSame('failed', $block['latestAttempt']['status']);
        $this->assertSame('insufficient_funds', $block['latestAttempt']['code']);
        $this->assertSame((new DateTimeImmutable('@250'))->setTimezone(new DateTimeZone('Europe/Copenhagen'))->format(DATE_ATOM), $block['latestAttempt']['at']);
    }

    public function testLatestAttemptCreationTimeAndIdTiesIgnoreSettlementUpdates(): void
    {
        $this->fixture();
        $this->payment(1, ['created_at' => 100, 'updated_at' => 999999]);
        $this->payment(2, ['created_at' => 200, 'status' => 'processing', 'received_at' => null]);
        $this->payment(3, ['created_at' => 200, 'status' => 'created', 'received_at' => null, 'kind' => 'auto']);
        $latest = $this->model()->invoicePayments([10])[10]['latestAttempt'];
        $this->assertSame('created', $latest['status']);
        $this->assertSame('auto', $latest['kind']);
        $this->assertSame((new DateTimeImmutable('@200'))->setTimezone(new DateTimeZone('Europe/Copenhagen'))->format(DATE_ATOM), $latest['at']);
    }

    public function testDueDateFallbackAndServiceAgreeOnValidFixtures(): void
    {
        $this->fixture();
        $service = new InvoicePaymentService($this->db, static function () { throw new RuntimeException('REST must never call Stripe'); },
            new InvoicePaymentLedger($this->db), static function () { throw new RuntimeException('REST must never notify'); }, 'fixture');
        foreach ([['2026-10-20', '2026-10-25', 'netto', 14, '2026-10-20'],
            [null, '2026-10-25', 'netto', 14, '2026-10-25'],
            [null, null, 'netto', 14, '2026-10-15'], [null, null, 'lb. md.', 5, '2026-11-05']] as $case) {
            [$openDate, $orderDate, $terms, $days, $expected] = $case;
            $this->db->query('UPDATE openpost SET forfaldsdate=? WHERE id=1', [$openDate]);
            $this->db->query('UPDATE ordrer SET due_date=?,betalingsbet=?,betalingsdage=? WHERE id=10', [$orderDate, $terms, $days]);
            $block = $this->model()->invoicePayments([10])[10];
            $this->assertSame($expected, $block['dueDate']);
            $this->assertSame($service->invoice(10)['dueDate'], $block['dueDate']);
            $this->assertSame($service->invoice(10)['due'], $block['ledgerOpenOre']);
        }
        $this->db->query("UPDATE openpost SET forfaldsdate='2026-02-30' WHERE id=1");
        $this->db->query("UPDATE ordrer SET due_date='2026-02-30',fakturadate='2026-02-30' WHERE id=10");
        $this->assertNull($this->model()->invoicePayments([10])[10]['dueDate']);
    }

    public function testDueDateSkipsSettledHistoryAndMatchesWorkerOutstandingItem(): void
    {
        $this->fixture();
        $this->db->query("UPDATE openpost SET udlignet='1',forfaldsdate='2026-09-01' WHERE id=1");
        $this->db->query("INSERT INTO openpost VALUES (4,1,'100',10,100,'0','2026-10-25')");
        $service = new InvoicePaymentService($this->db, static function () { throw new RuntimeException('No Stripe calls expected'); },
            new InvoicePaymentLedger($this->db), static function () { throw new RuntimeException('No notifications expected'); }, 'fixture');
        $this->assertSame('2026-10-25', $this->model()->invoicePayments([10])[10]['dueDate']);
        $this->assertSame($service->invoice(10)['dueDate'], $this->model()->invoicePayments([10])[10]['dueDate']);
        $this->db->query("UPDATE openpost SET udlignet='1' WHERE id=4");
        $this->db->query("UPDATE ordrer SET due_date='2026-11-01' WHERE id=10");
        $this->assertSame('2026-11-01', $this->model()->invoicePayments([10])[10]['dueDate']);
        $this->assertSame($service->invoice(10)['dueDate'], $this->model()->invoicePayments([10])[10]['dueDate']);
    }

    public function testExactContractKeysTypesCopenhagenTimesAndNoSecrets(): void
    {
        $this->fixture();
        $this->card();
        $this->payment(1);
        $this->payment(2, ['status' => 'failed', 'received_at' => null, 'failed_at' => 1790841700, 'error_code' => 'insufficient_funds']);
        $status = $this->model()->debtorStatuses([1])[1];
        $block = $this->model()->invoicePayments([10])[10];
        $this->assertSame(['mode', 'invoicePayEnabled', 'autopayEnabled', 'eligible', 'blockedReasons', 'card', 'lastPayment', 'lastDecline', 'unresolvedSettlementCount'], array_keys($status));
        $this->assertSame(['brand', 'last4', 'expMonth', 'expYear', 'consentAt', 'consentOrderId', 'termsVersion', 'brokenAt', 'brokenReason', 'revokedAt'], array_keys($status['card']));
        $this->assertSame(['orderId', 'invoiceNo', 'kind', 'amountOre', 'receivedAt', 'settlement'], array_keys($status['lastPayment']));
        $this->assertSame(['orderId', 'invoiceNo', 'kind', 'failedAt', 'code', 'hard'], array_keys($status['lastDecline']));
        $this->assertSame(['ledgerPaid', 'ledgerOpenOre', 'dueDate', 'receivedUnpostedOre', 'processing', 'collectionHold', 'latestAttempt'], array_keys($block));
        $this->assertSame(['kind', 'status', 'at', 'code'], array_keys($block['latestAttempt']));
        foreach (['invoicePayEnabled', 'autopayEnabled', 'eligible'] as $key) $this->assertIsBool($status[$key]);
        foreach (['ledgerPaid', 'processing', 'collectionHold'] as $key) $this->assertIsBool($block[$key]);
        foreach (['ledgerOpenOre', 'receivedUnpostedOre'] as $key) $this->assertIsInt($block[$key]);
        $this->assertIsInt($status['unresolvedSettlementCount']);
        $this->assertIsInt($status['card']['expMonth']);
        $this->assertIsInt($status['card']['expYear']);
        $this->assertIsInt($status['lastPayment']['amountOre']);
        $this->assertIsString($status['lastPayment']['invoiceNo']);
        $this->assertIsBool($status['lastDecline']['hard']);
        $this->assertFalse($status['lastDecline']['hard']);
        $this->assertStringEndsWith('+02:00', $status['lastPayment']['receivedAt']);
        $this->db->query('UPDATE stripe_invoice_cards SET consent_at=1767225600');
        $this->assertStringEndsWith('+01:00', $this->model()->debtorStatuses([1])[1]['card']['consentAt']);
        $json = json_encode([$status, $block]);
        foreach (['pm_fixture', 'cus_fixture', 'client_secret', 'payment_method', 'link_secret', 'data_json'] as $secret) $this->assertStringNotContainsString($secret, $json);
    }

    public function testBatchQueryCountIsConstantForOneFiftyAndHundredDebtorsAndContextCachesNulls(): void
    {
        $this->fixture();
        $this->card();
        $this->payment(1);
        $this->db->query('CREATE TABLE stripe_customers (konto_id INTEGER,status TEXT)');
        $counter = (object)['queries' => 0];
        $this->db->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [InvoicePaymentReadCountingStatement::class, [$counter]]);
        $counts = [];
        foreach ([1, 50, 100] as $size) {
            $counter->queries = 0;
            $model = $this->model();
            $model->debtorStatuses(range(1, $size));
            $counts[] = $counter->queries;
        }
        $this->assertSame([$counts[0], $counts[0], $counts[0]], $counts);
        $counts = [];
        foreach ([1, 50, 100] as $size) {
            $counter->queries = 0;
            $this->model()->invoicePayments(range(10, 9 + $size));
            $counts[] = $counter->queries;
        }
        $this->assertSame([$counts[0], $counts[0], $counts[0]], $counts);
        $connects = 0;
        InvoicePaymentReadContext::configure(function () use (&$connects): PDO { $connects++; return $this->db->pdo; });
        InvoicePaymentReadContext::prepareDebtors(range(1, 100));
        $counter->queries = 0;
        foreach (range(1, 100) as $id) InvoicePaymentReadContext::debtor($id);
        $this->assertSame(0, $counter->queries);
        $this->assertSame(1, $connects);
        InvoicePaymentReadContext::prepareInvoices([10, 11]);
        $counter->queries = 0;
        InvoicePaymentReadContext::invoice(10);
        InvoicePaymentReadContext::invoice(11);
        $this->assertSame(0, $counter->queries);
        InvoicePaymentReadContext::configure(null);
        $this->assertNull(InvoicePaymentReadContext::debtor(1));
    }

    public function testMissingSchemaBatchQueryCountIsConstantForOneFiftyAndHundredIds(): void
    {
        $this->fixture(false);
        $counter = (object)['queries' => 0];
        $this->db->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [InvoicePaymentReadCountingStatement::class, [$counter]]);
        $counts = [];
        foreach ([1, 50, 100] as $size) {
            $counter->queries = 0;
            $model = $this->model();
            $this->assertSame(array_fill_keys(range(1, $size), null), $model->debtorStatuses(range(1, $size)));
            $this->assertSame(array_fill_keys(range(1, $size), null), $model->invoicePayments(range(1, $size)));
            $counts[] = $counter->queries;
        }
        $this->assertSame([$counts[0], $counts[0], $counts[0]], $counts);
    }

    public function testContextResetCannotReuseAnotherTenantsCardOrReceipt(): void
    {
        $this->fixture();
        $this->card();
        $this->payment(1);
        InvoicePaymentReadContext::configure(fn() => $this->db->pdo);
        $this->assertSame('4242', InvoicePaymentReadContext::debtor(1)['card']['last4']);
        // A separate unprovisioned database with identical requested ids must remain unavailable.
        $dsn = getenv('SALDI_STRIPE_TEST_DSN');
        $other = new PDO($dsn ?: 'sqlite::memory:', getenv('SALDI_STRIPE_TEST_USER') ?: null, getenv('SALDI_STRIPE_TEST_PASSWORD') ?: null);
        $otherSchema = $dsn ? 'stripe_other_' . bin2hex(random_bytes(8)) : null;
        if ($otherSchema !== null) {
            $other->exec('CREATE SCHEMA ' . $otherSchema);
            $other->exec('SET search_path TO ' . $otherSchema);
        }
        try {
            InvoicePaymentReadContext::configure(static fn() => $other);
            $this->assertNull(InvoicePaymentReadContext::debtor(1));
            $this->assertNull(InvoicePaymentReadContext::invoice(10));
        } finally {
            InvoicePaymentReadContext::configure(null);
            if ($otherSchema !== null) $other->exec('DROP SCHEMA ' . $otherSchema . ' CASCADE');
        }
    }

    public function testLargeBatchAndSingleReadsAgreeWithoutWrites(): void
    {
        $this->fixture();
        $this->card();
        $this->payment(1);
        $before = $this->snapshot();
        $batch = $this->model()->debtorStatuses(range(1, 1200));
        $this->assertCount(1200, $batch);
        $this->assertSame($this->model()->debtorStatuses([1])[1], $batch[1]);
        $invoices = $this->model()->invoicePayments([10, 11, 12]);
        foreach ([10, 11, 12] as $id) $this->assertSame($this->model()->invoicePayments([$id])[$id], $invoices[$id]);
        $this->assertSame($before, $this->snapshot());
    }

    private function snapshot(): array
    {
        $out = [];
        foreach (['settings', 'adresser', 'ordrer', 'openpost', 'stripe_invoice_cards', 'stripe_invoice_payments', 'stripe_invoice_audit', 'stripe_invoice_operations'] as $table) {
            $out[$table] = $this->db->rows('SELECT * FROM ' . $table);
        }
        return $out;
    }
}
