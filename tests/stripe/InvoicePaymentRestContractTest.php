<?php
// 20261012 CL/LH HTTP producer contract for PR #785, on a disposable authenticated REST tenant.

use PHPUnit\Framework\TestCase;
use Saldi\Stripe\InvoicePaymentSchema;

require_once __DIR__ . '/../restapi/support/RestApiEnv.php';
require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentSchema.php';

/** The separate verification runner supplies its local REST stack and database credentials. */
final class InvoicePaymentRestContractTest extends TestCase
{
    private const CUSTOMERS = '/restapi/endpoints/v1/debitor/customers/';
    private const INVOICES = '/restapi/endpoints/v1/debitor/invoices/';
    private $conn;
    private int $konto;
    private int $order;
    private string $tag;

    public static function setUpBeforeClass(): void
    {
        // Never use RestApiEnv's legacy literal password fallback.
        if (!getenv('SALDI_CHAR_PGPASS')) self::markTestSkipped('HTTP contract requires SALDI_CHAR_PGPASS from the local test environment.');
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) self::markTestSkipped($reason);
        RestApiEnv::bootstrapTenant();
    }

    public static function tearDownAfterClass(): void { RestApiEnv::teardownTenant(); }

    protected function setUp(): void
    {
        $this->conn = RestApiEnv::connect(RestApiEnv::testDb());
        // Only our owned clone is altered; the template and any live tenant are untouched.
        foreach (['stripe_invoice_payments', 'stripe_invoice_cards', 'stripe_invoice_customers', 'stripe_invoice_operations', 'stripe_invoice_audit', 'stripe_invoice_notifications'] as $table) {
            RestApiEnv::rows($this->conn, 'DROP TABLE IF EXISTS ' . $table . ' CASCADE');
        }
        RestApiEnv::rows($this->conn, 'ALTER TABLE adresser ADD COLUMN IF NOT EXISTS stripe_fravalg VARCHAR(10)');
        $this->tag = 'autopay-contract-' . bin2hex(random_bytes(5));
        $r = RestApiEnv::rows($this->conn, "INSERT INTO adresser (art,firmanavn,kontonr,pbs,stripe_fravalg) VALUES ('D',$1,$1,'','') RETURNING id", [$this->tag]);
        $this->konto = (int)$r[0]['id'];
        $r = RestApiEnv::rows($this->conn, "INSERT INTO ordrer (konto_id,kontonr,art,status,fakturanr,fakturadate,due_date,betalingsbet,betalingsdage,sum,moms,valuta,betalt) VALUES ($1,$2,'DO',3,$2,'2026-10-01','2026-10-15','netto',14,100,0,'DKK','0') RETURNING id", [$this->konto, $this->tag]);
        $this->order = (int)$r[0]['id'];
        RestApiEnv::rows($this->conn, "INSERT INTO openpost (konto_id,faktnr,refnr,amount,udlignet,forfaldsdate) VALUES ($1,$2,$3,100,'0','2026-10-20')", [$this->konto, $this->tag, $this->order]);
        RestApiEnv::rows($this->conn, "DELETE FROM settings WHERE var_grp='stripe' AND var_name IN ('mode','invoice_pay_enabled','autopay_enabled')");
        foreach (['mode' => 'live', 'invoice_pay_enabled' => 'on', 'autopay_enabled' => 'on'] as $key => $value) {
            RestApiEnv::rows($this->conn, "INSERT INTO settings (var_grp,var_name,var_value) VALUES ('stripe',$1,$2)", [$key, $value]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->conn) pg_close($this->conn);
    }

    private function get(string $path): array
    {
        $r = RestApiEnv::http('GET', $path, null, RestApiEnv::authHeaders());
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertTrue($r['json']['success'], $r['body']);
        return $r['json']['data'];
    }

    private function provision(): void
    {
        foreach (InvoicePaymentSchema::statements('pgsql') as $sql) RestApiEnv::rows($this->conn, $sql);
    }

    public function testEndpointsRequireAuthenticationAndExposeNullWhenSchemaIsAbsent(): void
    {
        foreach ([self::CUSTOMERS, self::INVOICES] as $path) {
            $r = RestApiEnv::http('GET', $path);
            $this->assertSame(401, $r['status']);
        }
        $customer = $this->get(self::CUSTOMERS . '?id=' . $this->konto);
        $invoice = $this->get(self::INVOICES . '?id=' . $this->order);
        $this->assertArrayHasKey('autopay', $customer);
        $this->assertNull($customer['autopay']);
        $this->assertArrayHasKey('payment', $invoice);
        $this->assertNull($invoice['payment']);
        $this->assertSame('0', $invoice['paid']);
        $list = $this->get(self::CUSTOMERS . '?search=' . $this->tag . '&limit=50');
        $this->assertCount(1, $list);
        $this->assertNull($list[0]['autopay']);
        $list = $this->get(self::INVOICES . '?customer=' . $this->konto);
        $this->assertCount(1, $list);
        $this->assertNull($list[0]['payment']);
        $this->assertSame([], RestApiEnv::rows($this->conn, "SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name='stripe_invoice_cards'"), 'GET must not provision missing tables');
    }

    public function testListSearchAndDetailSerializeTheSameExactReadOnlyContract(): void
    {
        $this->provision();
        RestApiEnv::rows($this->conn, "INSERT INTO stripe_invoice_cards (mode,konto_id,customer_id,payment_method_id,brand,last4,exp_month,exp_year,autopay,consent_at,consent_order_id,terms_version,updated_at) VALUES ('live',$1,'cus_contract','pm_contract','visa','4242',12,2030,1,1790841600,$2,'v1-2026-10',1790841600),('test',$1,'cus_other','pm_other','visa','5555',11,2031,1,1790841600,$2,'v1-2026-10',1790841600)", [$this->konto, $this->order]);
        RestApiEnv::rows($this->conn, "INSERT INTO stripe_invoice_payments (mode,konto_id,order_id,kind,status,amount_ore,received_at,open_ore_at_receipt,settlement,created_at,updated_at) VALUES ('live',$1,$2,'manual','succeeded',5000,1790841600,10000,'manual',1790841500,1790841600)", [$this->konto, $this->order]);
        RestApiEnv::rows($this->conn, "INSERT INTO stripe_invoice_payments (mode,konto_id,order_id,kind,status,amount_ore,failed_at,error_code,created_at,updated_at) VALUES ('live',$1,$2,'auto','failed',5000,1790841700,'expired_card',1790841650,1790841700)", [$this->konto, $this->order]);
        $before = $this->snapshot();
        $customer = $this->get(self::CUSTOMERS . '?id=' . $this->konto);
        $customers = $this->get(self::CUSTOMERS . '?search=' . $this->tag . '&limit=50');
        $invoice = $this->get(self::INVOICES . '?id=' . $this->order);
        $invoices = $this->get(self::INVOICES . '?customer=' . $this->konto);
        $this->assertCount(1, $customers);
        $this->assertCount(1, $invoices);
        $this->assertSame($customer['autopay'], $customers[0]['autopay']);
        $this->assertSame($invoice['payment'], $invoices[0]['payment']);
        $a = $customer['autopay'];
        $p = $invoice['payment'];
        $this->assertSame(['mode','invoicePayEnabled','autopayEnabled','eligible','blockedReasons','card','lastPayment','lastDecline','unresolvedSettlementCount'], array_keys($a));
        $this->assertSame(['brand','last4','expMonth','expYear','consentAt','consentOrderId','termsVersion','brokenAt','brokenReason','revokedAt'], array_keys($a['card']));
        $this->assertSame(['orderId','invoiceNo','kind','amountOre','receivedAt','settlement'], array_keys($a['lastPayment']));
        $this->assertSame(['orderId','invoiceNo','kind','failedAt','code','hard'], array_keys($a['lastDecline']));
        $this->assertSame(['ledgerPaid','ledgerOpenOre','dueDate','receivedUnpostedOre','processing','collectionHold','latestAttempt'], array_keys($p));
        $this->assertSame(['kind','status','at','code'], array_keys($p['latestAttempt']));
        $this->assertSame('live', $a['mode']);
        $this->assertSame('4242', $a['card']['last4']);
        $this->assertTrue($a['invoicePayEnabled']);
        $this->assertTrue($a['autopayEnabled']);
        $this->assertTrue($a['eligible']);
        $this->assertSame([], $a['blockedReasons']);
        $this->assertSame(1, $a['unresolvedSettlementCount']);
        $this->assertIsInt($a['card']['expMonth']);
        $this->assertIsInt($a['card']['expYear']);
        $this->assertIsInt($a['lastPayment']['orderId']);
        $this->assertIsInt($a['lastPayment']['amountOre']);
        $this->assertIsString($a['lastPayment']['receivedAt']);
        $this->assertTrue($a['lastDecline']['hard']);
        $this->assertFalse($p['ledgerPaid']);
        $this->assertSame(10000, $p['ledgerOpenOre']);
        $this->assertSame(5000, $p['receivedUnpostedOre']);
        $this->assertSame('2026-10-20', $p['dueDate']);
        $this->assertFalse($p['processing']);
        $this->assertFalse($p['collectionHold']);
        $this->assertSame('0', $invoice['paid']);
        foreach (['cus_contract','pm_contract','cus_other','pm_other','client_secret','payment_method_id'] as $value) {
            $this->assertStringNotContainsString($value, json_encode([$customer, $invoice]));
        }
        $this->assertSame($before, $this->snapshot());
        foreach (['broken_at' => 'card_broken', 'revoked_at' => 'card_revoked'] as $column => $reason) {
            RestApiEnv::rows($this->conn, 'UPDATE stripe_invoice_cards SET ' . $column . '=1790841800 WHERE mode=\'live\'');
            $a = $this->get(self::CUSTOMERS . '?id=' . $this->konto)['autopay'];
            $this->assertContains($reason, $a['blockedReasons']);
            $this->assertSame('4242', $a['card']['last4']);
            $this->assertFalse($a['eligible']);
        }
        RestApiEnv::rows($this->conn, "UPDATE openpost SET udlignet='1' WHERE refnr=$1 AND konto_id=$2", [$this->order, $this->konto]);
        $p = $this->get(self::INVOICES . '?id=' . $this->order)['payment'];
        $this->assertTrue($p['ledgerPaid']);
        $this->assertSame(0, $p['receivedUnpostedOre']);
    }

    private function snapshot(): array
    {
        $out = [];
        foreach (['stripe_invoice_cards','stripe_invoice_payments','stripe_invoice_audit','stripe_invoice_operations','openpost','ordrer','settings'] as $table) {
            $out[$table] = RestApiEnv::rows($this->conn, 'SELECT * FROM ' . $table . ' ORDER BY id');
        }
        return $out;
    }
}
