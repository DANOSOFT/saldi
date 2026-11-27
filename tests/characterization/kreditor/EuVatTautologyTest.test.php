<?php
// tests/characterization/kreditor/EuVatTautologyTest.test.php
//
// Regression tests for D8:
//   1. includes/purchaseInvoicePosting.php:94 had ($box1!='E' || $box1!='Y'),
//      which is always true, adding ordrer.moms to the creditor payable sum
//      even for EU goods ('E') and EU services ('Y') suppliers.
//   2. finans/bogfor_includes/postingRules.php:120-122 had ($a!='E' || $a!='Y')
//      and substr($kontrol, 1.1), unconditionally overriding an EU finance
//      account's own EU VAT code with the creditor's $kontrol and emitting a
//      PHP 8.2+ float-to-int deprecation warning.
//
// History:
// 20261127 CL/HUG Created for D8 EU VAT tautology and substr(1.1) regression tests.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../support/Fixtures.php';
require_once dirname(__DIR__, 3) . '/includes/db_query.php';
require_once dirname(__DIR__, 3) . '/includes/std_func.php';
require_once dirname(__DIR__, 3) . '/finans/bogfor_includes/postingRules.php';

final class EuVatTautologyTest extends TestCase
{
    /** @var resource|\PgSql\Connection */
    private static $tenant;
    private static int $year;
    private static int $purchaseAccount;
    private static int $domesticVatAccount;
    private static int $collectiveAccount;
    private static int $euGoodsVatAccount;
    private static int $euGoodsCounterAccount;
    private static int $euGoods2VatAccount;
    private static int $euGoods2CounterAccount;
    private static int $euServiceVatAccount;
    private static int $euServiceCounterAccount;
    private static int $euGoods12VatAccount;
    private static int $euGoods12CounterAccount;
    private static int $seq = 98000;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant(static::class);
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());

        $year = CharacterizationEnv::one(self::$tenant, "SELECT max(kodenr) AS year FROM grupper WHERE art='RA'");
        self::$year = (int)$year['year'];
        $fixtures = new Fixtures(self::$tenant, self::$year);
        [self::$purchaseAccount, self::$domesticVatAccount] = $fixtures->vatPurchaseAccount();

        $collective = CharacterizationEnv::one(
            self::$tenant,
            "SELECT box2 FROM grupper WHERE art='KG' AND fiscal_year=$1 ORDER BY kodenr LIMIT 1",
            [self::$year]
        );
        self::$collectiveAccount = (int)$collective['box2'];

        // Provision distinct ledger accounts for EM/1, EM/2, and YM/1 so each VAT group is uniquely identifiable.
        self::$euGoodsVatAccount = 69101;
        self::$euGoodsCounterAccount = 69102;
        self::$euGoods2VatAccount = 69103;
        self::$euGoods2CounterAccount = 69104;
        self::$euServiceVatAccount = 69105;
        self::$euServiceCounterAccount = 69106;
        self::$euGoods12VatAccount = 69107;
        self::$euGoods12CounterAccount = 69108;
        foreach ([
            self::$euGoodsVatAccount,
            self::$euGoodsCounterAccount,
            self::$euGoods2VatAccount,
            self::$euGoods2CounterAccount,
            self::$euServiceVatAccount,
            self::$euServiceCounterAccount,
            self::$euGoods12VatAccount,
            self::$euGoods12CounterAccount,
        ] as $acct) {
            CharacterizationEnv::rows(
                self::$tenant,
                "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, lukket, primo, saldo, regnskabsaar)
                 VALUES ($1, 'D8 EU VAT test', 'S', '', '', 0, 0, $2)",
                [$acct, self::$year]
            );
        }

        // Seed EM/1 (25%), EM/2 (15%), EM/12 (10%), YM/1 (20%), plus KG groups for domestic ('K1'), EU goods ('E1'), EU services ('Y1').
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM grupper WHERE art IN ('EM','YM') AND fiscal_year=$1", [self::$year]);
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, fiscal_year) VALUES
             ('D8 EU goods 1',   'E', 1,  'EM', $1, '25', $2, $9),
             ('D8 EU goods 2',   'E', 2,  'EM', $3, '15', $4, $9),
             ('D8 EU goods 12',  'E', 12, 'EM', $5, '10', $6, $9),
             ('D8 EU service 1', 'Y', 1,  'YM', $7, '20', $8, $9)",
            [
                (string)self::$euGoodsCounterAccount,
                (string)self::$euGoodsVatAccount,
                (string)self::$euGoods2CounterAccount,
                (string)self::$euGoods2VatAccount,
                (string)self::$euGoods12CounterAccount,
                (string)self::$euGoods12VatAccount,
                (string)self::$euServiceCounterAccount,
                (string)self::$euServiceVatAccount,
                self::$year,
            ]
        );
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO grupper (art, kodenr, fiscal_year, box1, box2) VALUES
             ('KG', 9801, $1, 'K1', $2),
             ('KG', 9802, $1, 'E1', $2),
             ('KG', 9803, $1, 'Y1', $2)",
            [self::$year, (string)self::$collectiveAccount]
        );
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO ansatte (id, navn, afd) VALUES (9801, 'D8 purchaser', 0)");
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            pg_close(self::$tenant);
            self::$tenant = null;
            CharacterizationEnv::teardownTenant();
        }
    }

    private static function bindGlobals(): void
    {
        global $connection, $db, $db_type, $regnaar, $brugernavn;
        $connection = self::$tenant;
        $db = CharacterizationEnv::testDb();
        $db_type = 'postgresql';
        $regnaar = self::$year;
        $brugernavn = 'chartest';
        $_SERVER['REQUEST_URI'] = '/saldi/finans/bogfor.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    /** @return array<string, array{int, float, int, int}> */
    public static function euSupplierGroups(): array
    {
        return [
            'EU goods supplier (E1)' => [9802, 25.0, 69101, 69102],
            'EU services supplier (Y1)' => [9803, 20.0, 69105, 69106],
        ];
    }

    #[DataProvider('euSupplierGroups')]
    public function test_eu_supplier_with_nonzero_order_moms_does_not_add_moms_to_payable_sum(
        int $creditorGroup,
        float $euRate,
        int $expectedInputVatAccount,
        int $expectedReverseChargeAccount
    ): void {
        $net = 1000.00;
        $orderMoms = 250.00;
        $kontoNr = ++self::$seq;
        $fakturaNr = 'D8-' . $kontoNr;

        $supplier = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO adresser (kontonr, firmanavn, art, gruppe) VALUES ($1, 'D8 EU Supplier', 'K', $2) RETURNING id",
            [$kontoNr, $creditorGroup]
        );
        $order = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO ordrer (art, konto_id, kontonr, firmanavn, ref, ordredate, levdate, fakturadate, fakturanr, modtagelse, sum, moms, momssats, valuta, valutakurs, status, projekt)
             VALUES ('KO', $1, $2, 'D8 EU Supplier', 'D8 purchaser', CURRENT_DATE, CURRENT_DATE, CURRENT_DATE, $3, 41, $4, $5, 25, 'DKK', 100, 3, 0)
             RETURNING id",
            [$supplier['id'], $kontoNr, $fakturaNr, $net, $orderMoms]
        );
        $orderId = (int)$order['id'];
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO ordrelinjer (ordre_id, vare_id, antal, pris, rabat, bogf_konto, posnr, projekt) VALUES ($1, 9801, 1, $2, 0, $3, 1, 0)",
            [$orderId, $net, self::$purchaseAccount]
        );

        $result = CharacterizationEnv::runChild(
            __DIR__ . '/support/run_purchase_posting.php',
            [$orderId, CharacterizationEnv::testDb(), self::$year]
        );
        $this->assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);
        $out = json_decode(trim($result['stdout']), true);
        $this->assertIsArray($out, 'EU purchase invoice must balance and post cleanly: ' . $result['stdout'] . $result['stderr']);

        $header = CharacterizationEnv::one(self::$tenant, 'SELECT status FROM ordrer WHERE id=$1', [$orderId]);
        $this->assertSame('4', (string)$header['status']);

        $open = CharacterizationEnv::one(self::$tenant, 'SELECT amount FROM openpost WHERE refnr=$1', [$orderId]);
        $this->assertEqualsWithDelta(-$net, (float)$open['amount'], 0.0001, 'EU supplier openpost must stay net without ordrer.moms added');

        $rows = CharacterizationEnv::rows(self::$tenant, 'SELECT kontonr, debet, kredit FROM transaktioner WHERE ordre_id=$1', [$orderId]);
        $byAccount = [];
        $totalDebet = 0.0;
        $totalKredit = 0.0;
        foreach ($rows as $row) {
            $byAccount[(int)$row['kontonr']] = [(float)$row['debet'], (float)$row['kredit']];
            $totalDebet += (float)$row['debet'];
            $totalKredit += (float)$row['kredit'];
        }
        $expectedEuVat = round($net * $euRate / 100, 2);
        $this->assertEqualsWithDelta($totalDebet, $totalKredit, 0.0001, 'ledger postings must balance');
        $this->assertSame([0.0, $net], $byAccount[self::$collectiveAccount], 'creditor payable must credit net sum only');
        $this->assertSame([$net, 0.0], $byAccount[self::$purchaseAccount], 'purchase account debits net sum');
        $this->assertSame([0.0, $expectedEuVat], $byAccount[$expectedReverseChargeAccount], 'EU reverse-charge VAT is computed from net sum');
        $this->assertSame([$expectedEuVat, 0.0], $byAccount[$expectedInputVatAccount], 'EU deductible VAT matches reverse-charge VAT');
    }

    public function test_domestic_supplier_still_adds_moms_to_payable_sum(): void
    {
        $net = 1000.00;
        $orderMoms = 250.00;
        $kontoNr = ++self::$seq;
        $fakturaNr = 'D8-' . $kontoNr;

        $supplier = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO adresser (kontonr, firmanavn, art, gruppe) VALUES ($1, 'D8 Domestic Supplier', 'K', 9801) RETURNING id",
            [$kontoNr]
        );
        $order = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO ordrer (art, konto_id, kontonr, firmanavn, ref, ordredate, levdate, fakturadate, fakturanr, modtagelse, sum, moms, momssats, valuta, valutakurs, status, projekt)
             VALUES ('KO', $1, $2, 'D8 Domestic Supplier', 'D8 purchaser', CURRENT_DATE, CURRENT_DATE, CURRENT_DATE, $3, 42, $4, $5, 25, 'DKK', 100, 3, 0)
             RETURNING id",
            [$supplier['id'], $kontoNr, $fakturaNr, $net, $orderMoms]
        );
        $orderId = (int)$order['id'];
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO ordrelinjer (ordre_id, vare_id, antal, pris, rabat, bogf_konto, posnr, projekt) VALUES ($1, 9801, 1, $2, 0, $3, 1, 0)",
            [$orderId, $net, self::$purchaseAccount]
        );

        $result = CharacterizationEnv::runChild(
            __DIR__ . '/support/run_purchase_posting.php',
            [$orderId, CharacterizationEnv::testDb(), self::$year]
        );
        $this->assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);

        $open = CharacterizationEnv::one(self::$tenant, 'SELECT amount FROM openpost WHERE refnr=$1', [$orderId]);
        $this->assertEqualsWithDelta(-($net + $orderMoms), (float)$open['amount'], 0.0001, 'domestic supplier openpost must include moms');
    }

    public function test_posting_rules_does_not_override_eu_finance_account_vat_code_with_creditor_kontrol(): void
    {
        self::bindGlobals();

        // Finance account carries its own EU services VAT code (Y1, 20%) while creditor carries E1 (25%).
        [$netY, $vatY, $vatAcctY, $counterAcctY] = momsberegning(self::$purchaseAccount, 1000.00, '', 'E1', 'Y1', false, true);
        $this->assertEqualsWithDelta(1000.00, (float)$netY, 0.001);
        $this->assertEqualsWithDelta(200.00, (float)$vatY, 0.001, 'Y1 finance account must keep its own 20% EU service VAT rate');
        $this->assertSame((string)self::$euServiceVatAccount, (string)$vatAcctY);
        $this->assertSame((string)self::$euServiceCounterAccount, (string)$counterAcctY);

        // Finance account carries its own EU goods VAT code (E2, 15%) while creditor carries E1 (25%).
        [$netE2, $vatE2, $vatAcctE2, $counterAcctE2] = momsberegning(self::$purchaseAccount, 1000.00, '', 'E1', 'E2', false, true);
        $this->assertEqualsWithDelta(1000.00, (float)$netE2, 0.001);
        $this->assertEqualsWithDelta(150.00, (float)$vatE2, 0.001, 'E2 finance account must keep its own 15% EU goods VAT rate');
        $this->assertSame((string)self::$euGoods2VatAccount, (string)$vatAcctE2);
        $this->assertSame((string)self::$euGoods2CounterAccount, (string)$counterAcctE2);
    }

    public function test_posting_rules_overrides_domestic_finance_account_with_eu_creditor_without_deprecation(): void
    {
        self::bindGlobals();

        $deprecations = [];
        set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                $deprecations[] = $message;
                return true;
            }
            return false;
        });

        try {
            [$net, $vat, $vatAcct, $counterAcct] = momsberegning(self::$purchaseAccount, 1000.00, '', 'E1', 'K1', false, true);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations, 'substr($kontrol, 1) must not emit float-to-int deprecation warnings');
        $this->assertEqualsWithDelta(1000.00, (float)$net, 0.001);
        $this->assertEqualsWithDelta(250.00, (float)$vat, 0.001, 'domestic finance account paired with EU creditor uses creditor EU VAT');
        $this->assertSame((string)self::$euGoodsVatAccount, (string)$vatAcct);
        $this->assertSame((string)self::$euGoodsCounterAccount, (string)$counterAcct);

        // Multi-digit legacy EU creditor code (E12, 10%) must not be truncated to E1 (25%).
        [$net12, $vat12, $vatAcct12, $counterAcct12] = momsberegning(self::$purchaseAccount, 1000.00, '', 'E12', 'K1', false, true);
        $this->assertEqualsWithDelta(1000.00, (float)$net12, 0.001);
        $this->assertEqualsWithDelta(100.00, (float)$vat12, 0.001, 'multi-digit E12 creditor code must resolve EM/12 (10%), not EM/1 (25%)');
        $this->assertSame((string)self::$euGoods12VatAccount, (string)$vatAcct12);
        $this->assertSame((string)self::$euGoods12CounterAccount, (string)$counterAcct12);
    }

    public function test_posting_rules_falls_back_to_creditor_eu_vat_when_saved_finance_eu_group_is_missing(): void
    {
        self::bindGlobals();

        // Stale/deleted Y9 override must fall back to creditor E1 (25%) rather than silently dropping reverse-charge VAT.
        [$net, $vat, $vatAcct, $counterAcct] = momsberegning(self::$purchaseAccount, 1000.00, '', 'E1', 'Y9', false, true);
        $this->assertEqualsWithDelta(1000.00, (float)$net, 0.001);
        $this->assertEqualsWithDelta(250.00, (float)$vat, 0.001, 'missing finance EU VAT group must fall back to valid creditor E1');
        $this->assertSame((string)self::$euGoodsVatAccount, (string)$vatAcct);
        $this->assertSame((string)self::$euGoodsCounterAccount, (string)$counterAcct);

        // Malformed non-numeric suffix (Y1x) must not abort PostgreSQL or coerce on MySQL; falls back to E1.
        [$netBad, $vatBad, $vatAcctBad, $counterAcctBad] = momsberegning(self::$purchaseAccount, 1000.00, '', 'E1', 'Y1x', false, true);
        $this->assertEqualsWithDelta(1000.00, (float)$netBad, 0.001);
        $this->assertEqualsWithDelta(250.00, (float)$vatBad, 0.001, 'malformed Y1x suffix must fall back cleanly to E1');
        $this->assertSame((string)self::$euGoodsVatAccount, (string)$vatAcctBad);
        $this->assertSame((string)self::$euGoodsCounterAccount, (string)$counterAcctBad);

        // Incomplete EU VAT group (YM/2 missing box3) must fall back to complete creditor E1.
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, fiscal_year)
             VALUES ('D8 incomplete YM/2', 'Y', 2, 'YM', $1, '20', '', $2)",
            [(string)self::$euServiceCounterAccount, self::$year]
        );
        try {
            [$netInc, $vatInc, $vatAcctInc, $counterAcctInc] = momsberegning(self::$purchaseAccount, 1000.00, '', 'E1', 'Y2', false, true);
            $this->assertEqualsWithDelta(1000.00, (float)$netInc, 0.001);
            $this->assertEqualsWithDelta(250.00, (float)$vatInc, 0.001, 'incomplete YM/2 group must fall back to complete E1');
            $this->assertSame((string)self::$euGoodsVatAccount, (string)$vatAcctInc);
            $this->assertSame((string)self::$euGoodsCounterAccount, (string)$counterAcctInc);
        } finally {
            CharacterizationEnv::rows(self::$tenant, "DELETE FROM grupper WHERE art='YM' AND kodenr=2 AND fiscal_year=$1", [self::$year]);
        }
    }

    public function test_eu_supplier_credit_note_with_nonzero_order_moms_does_not_add_moms_to_payable_sum(): void
    {
        $net = -1000.00;
        $orderMoms = -250.00;
        $kontoNr = ++self::$seq;
        $fakturaNr = 'D8-CN-' . $kontoNr;

        $supplier = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO adresser (kontonr, firmanavn, art, gruppe) VALUES ($1, 'D8 EU Credit Supplier', 'K', 9802) RETURNING id",
            [$kontoNr]
        );
        $order = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO ordrer (art, konto_id, kontonr, firmanavn, ref, ordredate, levdate, fakturadate, fakturanr, modtagelse, sum, moms, momssats, valuta, valutakurs, status, projekt)
             VALUES ('KK', $1, $2, 'D8 EU Credit Supplier', 'D8 purchaser', CURRENT_DATE, CURRENT_DATE, CURRENT_DATE, $3, 43, $4, $5, 25, 'DKK', 100, 3, 0)
             RETURNING id",
            [$supplier['id'], $kontoNr, $fakturaNr, $net, $orderMoms]
        );
        $orderId = (int)$order['id'];
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO ordrelinjer (ordre_id, vare_id, antal, pris, rabat, bogf_konto, posnr, projekt) VALUES ($1, 9801, -1, 1000.00, 0, $2, 1, 0)",
            [$orderId, self::$purchaseAccount]
        );

        $result = CharacterizationEnv::runChild(
            __DIR__ . '/support/run_purchase_posting.php',
            [$orderId, CharacterizationEnv::testDb(), self::$year]
        );
        $this->assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);
        $out = json_decode(trim($result['stdout']), true);
        $this->assertIsArray($out, 'EU credit note must balance and post cleanly: ' . $result['stdout'] . $result['stderr']);

        $open = CharacterizationEnv::one(self::$tenant, 'SELECT amount FROM openpost WHERE refnr=$1', [$orderId]);
        $this->assertEqualsWithDelta(-$net, (float)$open['amount'], 0.0001, 'EU credit note openpost must stay net (+1000)');
    }

    public function test_posting_line_entries_and_preview_preserve_eu_finance_account_vat_code(): void
    {
        self::bindGlobals();

        $kontoNr = ++self::$seq;
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO adresser (kontonr, firmanavn, art, gruppe) VALUES ($1, 'D8 Journal EU Creditor', 'K', 9802)",
            [$kontoNr]
        );

        $row = [
            'id' => 1,
            'bilag' => 1,
            'transdate' => date('Y-m-d'),
            'beskrivelse' => 'D8 EU service line',
            'd_type' => 'F',
            'debet' => (string)self::$purchaseAccount,
            'k_type' => 'K',
            'kredit' => (string)$kontoNr,
            'amount' => 1000.00,
            'valuta' => '0',
            'momsfri' => '',
            'debetvat' => 'Y1',
            'kreditvat' => '',
        ];

        $entries = postingLineEntries(postingLineAmounts($row, true));
        $byAccount = [];
        $totalDebet = 0.0;
        $totalKredit = 0.0;
        foreach ($entries as $entry) {
            $byAccount[(string)$entry['kontonr']] = [(float)$entry['debet'], (float)$entry['kredit']];
            $totalDebet += (float)$entry['debet'];
            $totalKredit += (float)$entry['kredit'];
        }
        $this->assertEqualsWithDelta($totalDebet, $totalKredit, 0.0001, 'journal entries must balance');
        $this->assertSame([200.0, 0.0], $byAccount[(string)self::$euServiceVatAccount] ?? null, 'Y1 service VAT debits 200');
        $this->assertSame([0.0, 200.0], $byAccount[(string)self::$euServiceCounterAccount] ?? null, 'Y1 counter VAT credits 200');
        $this->assertArrayNotHasKey((string)self::$euGoodsVatAccount, $byAccount, 'must not overwrite Y1 with creditor E1 VAT account');

        // Deleted Y9 override on the same journal line falls back to creditor E1 (250).
        $rowStale = $row;
        $rowStale['debetvat'] = 'Y9';
        $staleEntries = postingLineEntries(postingLineAmounts($rowStale, true));
        $staleByAccount = [];
        foreach ($staleEntries as $entry) {
            $staleByAccount[(string)$entry['kontonr']] = [(float)$entry['debet'], (float)$entry['kredit']];
        }
        $this->assertSame([250.0, 0.0], $staleByAccount[(string)self::$euGoodsVatAccount] ?? null, 'deleted Y9 override must fall back to creditor E1 (250)');
        $this->assertSame([0.0, 250.0], $staleByAccount[(string)self::$euGoodsCounterAccount] ?? null);
    }
}
