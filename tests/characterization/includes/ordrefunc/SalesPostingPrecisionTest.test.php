<?php
// tests/characterization/includes/ordrefunc/SalesPostingPrecisionTest.test.php
//
// MB-80: what bogfor_nu() posts for an invoice, row by row, in EUR and in DKK.
//
// An EUR invoice (net 999.99, VAT 250.00, rate 745) used to post VAT 1862.49 instead of 1862.50,
// plus 0.02 on the currency difference account and -0.01 on the øre difference account, because the
// VAT was converted from 249.998 (3 decimals) and the control totals were summed from 3-decimal amounts.
// Also pinned (Lui's comment on MB-80): a line on a revenue account already used with another VAT
// account is no longer dropped (a), a group with net 0 still posts its VAT (b), and the pre-flight
// check forkontrolPosteringsbalance() predicts exactly what bogfor_nu() posts (c). DKK invoices post as
// before (d): their expected rows below are what origin/master f2b557416 posted for the same orders.
//
// Ported from Lui's tests/test_sales_posting_precision.php in #657 (62bdc0d1), which ran the posting
// loop with stubbed database functions; this one runs the real invoicing on a fresh test tenant.
//
// History:
// 20261125 CL/MMR MB-80: created.
// 20261125 CL/MMR MB-80 (Codex round 3): orders that fail only the rounded-sides check (99.961/-0.005), and a table of edge prices decided as on origin/master.
// 20261125 CL/MMR MB-80 (Codex round 2): the cutoff of the pre-flight check, DKK and EUR, invoice and credit note, refused and accepted as on origin/master.
// 20261125 CL/MMR MB-80 (Codex round 1): ten half-øre revenue groups in DKK (invoice and credit note) and EUR; the pre-flight must accept each invoice and the order must reach status 4.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../support/CharacterizationEnv.php';

final class SalesPostingPrecisionTest extends TestCase
{
    private const DEBTOR = '6190';
    private const REVENUE = '1010';
    private const REVENUE_ABROAD = '1200';
    private const REVENUE_EXEMPT = '1050';
    private const VAT25 = 7680;
    private const VAT10 = 7700;
    private const CURRENCY_DIFF = '3610';
    private const ORE_DIFF = '2720';

    /** @var resource|\PgSql\Connection */
    private static $tenant;
    private static int $regnaar;
    private static int $debtorId;
    private static string $tempDir = '';

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant(static::class);
        $t = self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $ra = CharacterizationEnv::one($t, "SELECT max(kodenr) AS ra FROM grupper WHERE art = 'RA'");
        self::$regnaar = (int)$ra['ra'];

        // EUR at 745 with its currency difference account, the øre difference account, and a 10% VAT code
        CharacterizationEnv::rows($t, "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3) VALUES ('Euro', 'EUR', 1, 'VK', 'EUR', '', $1)", [self::CURRENCY_DIFF]);
        CharacterizationEnv::rows($t, "INSERT INTO valuta (gruppe, valdate, kurs) VALUES (1, '2000-01-01', 745)");
        CharacterizationEnv::rows($t, "INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2) VALUES ('Øredifferencer', 1, 'OreDif', '0.05', $1)", [self::ORE_DIFF]);
        CharacterizationEnv::rows(
            $t,
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, fiscal_year) VALUES ('Salgsmoms 10', 'S', 2, 'SM', $1, '10', $2)",
            [(string)self::VAT10, self::$regnaar]
        );

        $debtor = CharacterizationEnv::one(
            $t,
            "INSERT INTO adresser (kontonr, firmanavn, addr1, postnr, bynavn, email, art, gruppe, betalingsbet, betalingsdage)
             VALUES (92001, 'MB-80 Kunde', 'Testvej 1', '8000', 'Aarhus', 'mb80@example.invalid', 'D', 1, 'Netto', 8)
             RETURNING id"
        );
        self::$debtorId = (int)$debtor['id'];

        // bogfor_nu() logs to ../temp/<db>/ and needs the folder
        self::$tempDir = CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb();
        if (!is_dir(self::$tempDir)) {
            mkdir(self::$tempDir, 0700, true);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tempDir !== '' && is_dir(self::$tempDir)) {
            foreach (array_merge(glob(self::$tempDir . '/*') ?: [], glob(self::$tempDir . '/.[!.]*') ?: []) as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir(self::$tempDir);
        }
        if (isset(self::$tenant) && self::$tenant) {
            pg_close(self::$tenant);
            self::$tenant = null;
            CharacterizationEnv::teardownTenant();
        }
    }

    /**
     * A free-text order line on its own revenue account, VAT account and VAT rate.
     *
     * @param array<string, mixed> $extra Other ordrelinjer columns (rabat, rabatart, momsfri, procent).
     * @return array<string, mixed>
     */
    private static function line(string $account, int $vatAccount, float $rate, float $price, float $qty = 1, array $extra = []): array
    {
        return array_merge([
            'bogf_konto' => $account, 'vat_account' => $vatAccount, 'momssats' => $rate,
            'pris' => $price, 'antal' => $qty, 'rabat' => 0, 'rabatart' => '', 'momsfri' => '', 'procent' => 100,
        ], $extra);
    }

    /**
     * Creates an order the way the order page leaves it before invoicing (sum and moms as shown on it).
     *
     * @param array<int, array<string, mixed>> $lines
     */
    private function createOrder(string $currency, string $art, array $lines, float $sum, float $vat): int
    {
        $order = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsbet, betalingsdage, kontonr, art, valuta,
                                 ordredate, levdate, ordrenr, sum, moms, momssats, status, konto_id, udskriv_til)
             VALUES ('MB-80 Kunde', 'Testvej 1', '8000', 'Aarhus', 'mb80@example.invalid', 'Netto', 8, 92001, $1, $2,
                     CURRENT_DATE, CURRENT_DATE, (SELECT COALESCE(max(ordrenr), 0) + 1 FROM ordrer), $3, $4, 25, 1, $5, 'email')
             RETURNING id",
            [$art, $currency, $sum, $vat, self::$debtorId]
        );
        $orderId = (int)$order['id'];
        $posnr = 0;
        foreach ($lines as $line) {
            $posnr++;
            CharacterizationEnv::rows(
                self::$tenant,
                "INSERT INTO ordrelinjer (ordre_id, posnr, beskrivelse, antal, pris, rabat, rabatart, momssats, momsfri, procent, bogf_konto, vat_account, projekt)
                 VALUES ($1, $2, 'MB-80 linje', $3, $4, $5, $6, $7, $8, $9, $10, $11, '')",
                [$orderId, $posnr, $line['antal'], $line['pris'], $line['rabat'], $line['rabatart'], $line['momssats'],
                    $line['momsfri'], $line['procent'], $line['bogf_konto'], $line['vat_account']]
            );
        }
        return $orderId;
    }

    /** @return array<string, mixed> The child runner's JSON. */
    private function runInvoiceChild(string $scenario, int $orderId): array
    {
        $res = CharacterizationEnv::runChild(
            __DIR__ . '/../../support/run_order_invoice.php',
            [$scenario, $orderId, CharacterizationEnv::testDb()]
        );
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        $this->assertIsArray($json, "runner must emit JSON (stdout: {$res['stdout']} stderr: {$res['stderr']})");
        return $json;
    }

    /**
     * Invoices the order and returns the posted rows in posting order.
     *
     * @return array<int, array{0: string, 1: string, 2: string}> [kontonr, debet, kredit] with 2 decimals.
     */
    private function invoiceRows(int $orderId): array
    {
        $out = $this->runInvoiceChild('invoice', $orderId);
        $this->assertSame('OK', $out['bogfor'], 'bogfor reports OK');
        $this->assertSame('4', (string)$out['status'], 'the order is invoiced (status 4)');
        $rows = CharacterizationEnv::rows(
            self::$tenant,
            "SELECT kontonr, to_char(debet, 'FM999999990.00') AS debet, to_char(kredit, 'FM999999990.00') AS kredit
             FROM transaktioner WHERE ordre_id = $1 ORDER BY id",
            [$orderId]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[] = [(string)$row['kontonr'], $row['debet'], $row['kredit']];
        }
        return $result;
    }

    /**
     * The pre-flight check, run on the invoiced order, must accept it and predict exactly the rounding that was
     * posted on the currency and øre difference accounts, and the stored rows must balance.
     *
     * @param array<int, array{0: string, 1: string, 2: string}> $rows
     */
    private function assertPreflightMatchesPosting(int $orderId, array $rows): void
    {
        $posted = 0.0;
        $balance = 0.0;
        foreach ($rows as [$account, $debet, $kredit]) {
            $balance += (float)$debet - (float)$kredit;
            if ($account === self::CURRENCY_DIFF || $account === self::ORE_DIFF) {
                $posted += (float)$kredit - (float)$debet;
            }
        }
        $this->assertEqualsWithDelta(0.0, $balance, 0.001, 'the posted rows balance');
        $out = $this->runInvoiceChild('preflight', $orderId);
        $this->assertEqualsWithDelta($posted, (float)$out['preflight']['rounding'], 0.001, 'pre-flight predicts the posted difference');
        $this->assertNull($out['balance'], 'pre-flight accepts the invoice');
    }

    /** @return array<int, array{0: string, 1: string, 2: string}> */
    private static function rows(array ...$rows): array
    {
        return $rows;
    }

    public function test_acceptance_eur_invoice_posts_exactly_three_rows(): void
    {
        $id = $this->createOrder('EUR', 'DO', [self::line(self::REVENUE, self::VAT25, 25, 333.33, 3)], 999.99, 250.00);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '9312.43', '0.00'],
            [self::REVENUE, '0.00', '7449.93'],
            [(string)self::VAT25, '0.00', '1862.50']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    public function test_eur_credit_note_posts_the_mirror_rows(): void
    {
        $id = $this->createOrder('EUR', 'DK', [self::line(self::REVENUE, self::VAT25, 25, 333.33, -3)], -999.99, -250.00);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '0.00', '9312.43'],
            [self::REVENUE, '7449.93', '0.00'],
            [(string)self::VAT25, '1862.50', '0.00']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    public function test_eur_invoice_with_two_vat_rates_posts_each_rate_rounded_in_euro(): void
    {
        // 250.005 + 100.002 EUR VAT; the invoice shows 250.01 + 100.00 = 350.01
        $id = $this->createOrder('EUR', 'DO', [
            self::line(self::REVENUE, self::VAT25, 25, 1000.02),
            self::line(self::REVENUE_ABROAD, self::VAT10, 10, 1000.02),
        ], 2000.04, 350.01);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '17507.87', '0.00'],
            [self::REVENUE, '0.00', '7450.15'],
            [(string)self::VAT25, '0.00', '1862.57'],
            [self::REVENUE_ABROAD, '0.00', '7450.15'],
            [(string)self::VAT10, '0.00', '745.00']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    public function test_eur_invoice_splits_the_invoice_vat_over_two_revenue_accounts_without_extra_ore(): void
    {
        // 250.005 EUR VAT on each account; the invoice shows 500.01, posted 3725.07 in all.
        // The øre that does not divide evenly goes to the first group (input order breaks the tie).
        $id = $this->createOrder('EUR', 'DO', [
            self::line(self::REVENUE, self::VAT25, 25, 1000.02),
            self::line(self::REVENUE_ABROAD, self::VAT25, 25, 1000.02),
        ], 2000.04, 500.01);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '18625.37', '0.00'],
            [self::REVENUE, '0.00', '7450.15'],
            [(string)self::VAT25, '0.00', '1862.53'],
            [self::REVENUE_ABROAD, '0.00', '7450.15'],
            [(string)self::VAT25, '0.00', '1862.54']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    /** (a) The second line used to be dropped, and the pre-flight refused the invoice as unbalanced. */
    public function test_eur_line_on_the_same_revenue_account_with_another_vat_account_is_posted(): void
    {
        $id = $this->createOrder('EUR', 'DO', [
            self::line(self::REVENUE, self::VAT25, 25, 1000.02),
            self::line(self::REVENUE, self::VAT10, 10, 1000.02),
        ], 2000.04, 350.01);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '17507.87', '0.00'],
            [self::REVENUE, '0.00', '7450.15'],
            [(string)self::VAT25, '0.00', '1862.57'],
            [self::REVENUE, '0.00', '7450.15'],
            [(string)self::VAT10, '0.00', '745.00']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    /** (b) Net 100 with VAT and -100 without on the same accounts: no revenue row, but the VAT is posted. */
    public function test_eur_group_with_net_zero_still_posts_its_vat(): void
    {
        $id = $this->createOrder('EUR', 'DO', [
            self::line(self::REVENUE, self::VAT25, 25, 100),
            self::line(self::REVENUE, self::VAT25, 25, -100, 1, ['momsfri' => 'on']),
        ], 0, 25.00);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '186.25', '0.00'],
            [(string)self::VAT25, '0.00', '186.25']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    public function test_dkk_invoice_posts_as_before(): void
    {
        $id = $this->createOrder('DKK', 'DO', [self::line(self::REVENUE, self::VAT25, 25, 333.33, 3)], 999.99, 250.00);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '1249.99', '0.00'],
            [self::REVENUE, '0.00', '999.99'],
            [(string)self::VAT25, '0.00', '250.00']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    public function test_dkk_credit_note_posts_as_before(): void
    {
        $id = $this->createOrder('DKK', 'DK', [self::line(self::REVENUE, self::VAT25, 25, 333.33, -3)], -999.99, -250.00);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '0.00', '1249.99'],
            [self::REVENUE, '999.99', '0.00'],
            [(string)self::VAT25, '250.00', '0.00']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    /** Mixed rates, a VAT-free line, a percent and an amount discount, and a negative line. */
    public function test_dkk_invoice_with_mixed_vat_discounts_and_a_negative_line_posts_as_before(): void
    {
        $id = $this->createOrder('DKK', 'DO', [
            self::line(self::REVENUE, self::VAT25, 25, 199.95, 3, ['rabat' => 10]),
            self::line(self::REVENUE, self::VAT25, 25, 49.99, 2, ['rabat' => 5, 'rabatart' => 'amount']),
            self::line(self::REVENUE, self::VAT25, 25, -25.00, 1),
            self::line(self::REVENUE_ABROAD, self::VAT10, 10, 333.33, 1),
            self::line(self::REVENUE_EXEMPT, 0, 0, 80.00, 1, ['momsfri' => 'on']),
        ], 1018.18, 184.55);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '1202.73', '0.00'],
            [self::REVENUE, '0.00', '604.85'],
            [(string)self::VAT25, '0.00', '151.21'],
            [self::REVENUE_EXEMPT, '0.00', '80.00'],
            [self::REVENUE_ABROAD, '0.00', '333.33'],
            [(string)self::VAT10, '0.00', '33.33'],
            [self::ORE_DIFF, '0.00', '0.01']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    /** (a) in DKK: origin/master refused this invoice («ordren balancerer ikke (difference 110,00 DKK)»). */
    public function test_dkk_line_on_the_same_revenue_account_with_another_vat_account_is_posted(): void
    {
        $id = $this->createOrder('DKK', 'DO', [
            self::line(self::REVENUE, self::VAT25, 25, 100),
            self::line(self::REVENUE, self::VAT10, 10, 100),
        ], 200, 35);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '235.00', '0.00'],
            [self::REVENUE, '0.00', '100.00'],
            [(string)self::VAT25, '0.00', '25.00'],
            [self::REVENUE, '0.00', '100.00'],
            [(string)self::VAT10, '0.00', '10.00']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    /** @return array<int, string> Ten revenue accounts from the seeded chart of accounts. */
    private static function tenRevenueAccounts(): array
    {
        $rows = CharacterizationEnv::rows(
            self::$tenant,
            "SELECT kontonr FROM kontoplan WHERE kontotype = 'D' AND regnskabsaar = $1 AND kontonr NOT IN (2720, 3610) ORDER BY kontonr LIMIT 10",
            [self::$regnaar]
        );
        return array_map(static fn(array $row): string => (string)$row['kontonr'], $rows);
    }

    /**
     * Codex round 1: ten VAT-free revenue groups at 100.005 each post 100.01, 0.05 more than the debtor's 1000.05.
     * origin/master posts this with 0.05 on the øre difference account; it must not be refused as unbalanced.
     *
     * @return array{0: int, 1: array<int, string>} Order id and the revenue accounts used.
     */
    private function createTenGroupOrder(string $currency, string $art, float $qty): array
    {
        $accounts = self::tenRevenueAccounts();
        $this->assertCount(10, $accounts);
        $lines = [];
        foreach ($accounts as $account) {
            $lines[] = self::line($account, 0, 0, 100.005, $qty);
        }
        return [$this->createOrder($currency, $art, $lines, 1000.05 * $qty, 0), $accounts];
    }

    public function test_dkk_invoice_with_ten_half_ore_revenue_groups_posts_the_rounding_on_the_ore_account(): void
    {
        [$id, $accounts] = $this->createTenGroupOrder('DKK', 'DO', 1);
        $rows = $this->invoiceRows($id);
        $expected = [[self::DEBTOR, '1000.05', '0.00']];
        foreach ($accounts as $account) {
            $expected[] = [$account, '0.00', '100.01'];
        }
        $expected[] = [self::ORE_DIFF, '0.05', '0.00'];
        $this->assertSame($expected, $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    public function test_dkk_credit_note_with_ten_half_ore_revenue_groups_posts_the_rounding_on_the_ore_account(): void
    {
        [$id, $accounts] = $this->createTenGroupOrder('DKK', 'DK', -1);
        $rows = $this->invoiceRows($id);
        $expected = [[self::DEBTOR, '0.00', '1000.05']];
        foreach ($accounts as $account) {
            $expected[] = [$account, '100.01', '0.00'];
        }
        $expected[] = [self::ORE_DIFF, '0.00', '0.05'];
        $this->assertSame($expected, $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    /** The same in EUR: 745.04 per group against a debtor of 7450.37 leaves 0.03, booked once on the currency account. */
    public function test_eur_invoice_with_ten_half_cent_revenue_groups_posts_the_rounding_on_the_currency_account(): void
    {
        [$id, $accounts] = $this->createTenGroupOrder('EUR', 'DO', 1);
        $rows = $this->invoiceRows($id);
        $expected = [[self::DEBTOR, '7450.37', '0.00']];
        foreach ($accounts as $account) {
            $expected[] = [$account, '0.00', '745.04'];
        }
        $expected[] = [self::CURRENCY_DIFF, '0.03', '0.00'];
        $this->assertSame($expected, $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    /**
     * The order must be refused before anything is written: status, invoice number, ledger and open items untouched.
     */
    private function assertRefusedBeforePosting(int $orderId, string $difference): void
    {
        $out = $this->runInvoiceChild('invoice', $orderId);
        $this->assertStringContainsString('balancerer ikke (difference ' . $difference, (string)$out['bogfor']);
        $this->assertSame('1', (string)$out['status'], 'status unchanged');
        $this->assertNull($out['fakturanr'], 'no invoice number');
        $this->assertSame([], CharacterizationEnv::rows(self::$tenant, 'SELECT id FROM transaktioner WHERE ordre_id = $1', [$orderId]), 'no ledger rows');
        $this->assertSame([], CharacterizationEnv::rows(self::$tenant, "SELECT id FROM openpost WHERE refnr = $1", [$orderId]), 'no open items');
    }

    /**
     * Two VAT-free lines whose 3-decimal amounts differ from the 2-decimal header: the pre-flight decides on the
     * net 3-decimal difference rounded once, as origin/master does (Codex round 2).
     */
    private function createCutoffOrder(string $currency, string $art, float $mainPrice, float $qty, ?float $secondPrice = -0.004, float $header = 100): int
    {
        $lines = [self::line(self::REVENUE, 0, 0, $mainPrice, $qty, ['momsfri' => 'on'])];
        if ($secondPrice !== null) {
            $lines[] = self::line(self::REVENUE_EXEMPT, 0, 0, $secondPrice, $qty, ['momsfri' => 'on']);
        }
        return $this->createOrder($currency, $art, $lines, $header * $qty, 0);
    }

    /**
     * Codex round 3: 100.005 - 99.961 = 0.044 passes the pre-flight's own check, but the rounded control sides
     * (100.01 - 99.96 = 0.05) fail the check origin/master makes after posting. Refused before anything is written.
     */
    public function test_dkk_invoice_failing_only_the_rounded_sides_check_is_refused_as_before(): void
    {
        $this->assertRefusedBeforePosting($this->createCutoffOrder('DKK', 'DO', 99.961, 1, -0.005), '0,05 DKK');
    }

    public function test_dkk_credit_note_failing_only_the_rounded_sides_check_is_refused_as_before(): void
    {
        $this->assertRefusedBeforePosting($this->createCutoffOrder('DKK', 'DK', 99.961, -1, -0.005), '-0,05 DKK');
    }

    /**
     * Edge prices around both cutoffs, DKK (0.05) and EUR (one line: 4.00). The decision is origin/master's
     * (checked by running this table on origin/master): refused before anything is written, or posted balanced.
     *
     * @return array<string, array{string, string, float, ?float, float, bool}> currency, art, main price, second price, header, posted
     */
    public static function edgeOrders(): array
    {
        return [
            'DKK net 0.045' => ['DKK', 'DO', 99.959, -0.004, 100, false],
            'DKK net 0.044, sides 0.04' => ['DKK', 'DO', 99.960, -0.004, 100, true],
            'DKK net 0.044, sides 0.05' => ['DKK', 'DO', 99.961, -0.005, 100, false],
            'DKK net 0.043, sides 0.05' => ['DKK', 'DO', 99.962, -0.005, 100, false],
            'DKK net 0.040, sides 0.04' => ['DKK', 'DO', 99.965, -0.005, 100, true],
            'DKK net -0.044, sides -0.04' => ['DKK', 'DO', 100.043, 0.001, 100, true],
            'DKK net -0.045' => ['DKK', 'DO', 100.044, 0.001, 100, false],
            'DKK credit net 0.045' => ['DKK', 'DK', 99.959, -0.004, 100, false],
            'DKK credit net 0.044, sides 0.04' => ['DKK', 'DK', 99.960, -0.004, 100, true],
            'DKK credit net 0.044, sides 0.05' => ['DKK', 'DK', 99.961, -0.005, 100, false],
            'EUR net 4.001, sides 4.01' => ['EUR', 'DO', 99.473, null, 100.01, false],
            'EUR net 3.994, sides 4.00' => ['EUR', 'DO', 99.474, null, 100.01, true],
            'EUR net 4.001, sides 4.00' => ['EUR', 'DO', 99.463, null, 100, true],
            'EUR net 4.008' => ['EUR', 'DO', 99.462, null, 100, false],
        ];
    }

    #[DataProvider('edgeOrders')]
    public function test_edge_orders_are_posted_or_refused_as_before(string $currency, string $art, float $mainPrice, ?float $secondPrice, float $header, bool $posted): void
    {
        $qty = ($art === 'DK') ? -1 : 1;
        $id = $this->createCutoffOrder($currency, $art, $mainPrice, $qty, $secondPrice, $header);
        if (!$posted) {
            $this->assertRefusedBeforePosting($id, '');
            return;
        }
        $rows = $this->invoiceRows($id);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    /** 100.004 - 99.959 = 0.045 rounds to 0.05: refused, as on origin/master. */
    public function test_dkk_invoice_at_the_cutoff_is_refused_as_before(): void
    {
        $this->assertRefusedBeforePosting($this->createCutoffOrder('DKK', 'DO', 99.959, 1), '0,05 DKK');
    }

    public function test_dkk_credit_note_at_the_cutoff_is_refused_as_before(): void
    {
        $this->assertRefusedBeforePosting($this->createCutoffOrder('DKK', 'DK', 99.959, -1), '-0,05 DKK');
    }

    /** 100.004 - 99.960 = 0.044 rounds to 0.04: posted, with the 0.04 on the øre account, as on origin/master. */
    public function test_dkk_invoice_just_below_the_cutoff_posts_as_before(): void
    {
        $id = $this->createCutoffOrder('DKK', 'DO', 99.960, 1);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '100.00', '0.00'],
            [self::REVENUE, '0.00', '99.96'],
            [self::REVENUE_EXEMPT, '0.00', '0.00'],
            [self::ORE_DIFF, '0.00', '0.04']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    public function test_dkk_credit_note_just_below_the_cutoff_posts_as_before(): void
    {
        $id = $this->createCutoffOrder('DKK', 'DK', 99.960, -1);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '0.00', '100.00'],
            [self::REVENUE, '99.96', '0.00'],
            [self::REVENUE_EXEMPT, '0.00', '0.00'],
            [self::ORE_DIFF, '0.04', '0.00']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    /** EUR, one line, so the limit is 2 + 2 = 4.00 DKK: (100 - 99.462) x 7.45 = 4.008 rounds to 4.01 and is refused. */
    public function test_eur_invoice_over_its_cutoff_is_refused_as_before(): void
    {
        $this->assertRefusedBeforePosting($this->createCutoffOrder('EUR', 'DO', 99.462, 1, null), '4,01 DKK');
    }

    /** (100 - 99.463) x 7.45 = 4.001 rounds to 4.00: posted, with 4.00 on the currency account. */
    public function test_eur_invoice_at_its_cutoff_posts_as_before(): void
    {
        $id = $this->createCutoffOrder('EUR', 'DO', 99.463, 1, null);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '745.00', '0.00'],
            [self::REVENUE, '0.00', '741.00'],
            [self::CURRENCY_DIFF, '0.00', '4.00']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }

    /** A real header/line discrepancy (VAT 26.00 on the order, 25.00 on its lines) is still refused before anything is written. */
    public function test_dkk_invoice_with_a_real_vat_difference_is_refused_before_posting(): void
    {
        $id = $this->createOrder('DKK', 'DO', [
            self::line(self::REVENUE, self::VAT25, 25, 100),
            self::line(self::REVENUE_EXEMPT, 0, 0, 50, 1, ['momsfri' => 'on']),
        ], 150, 26.00);
        $out = $this->runInvoiceChild('invoice', $id);
        $this->assertStringContainsString('balancerer ikke (difference 1,00 DKK)', (string)$out['bogfor']);
        $this->assertNotSame('4', (string)$out['status'], 'the order is not invoiced');
        $this->assertSame([], CharacterizationEnv::rows(self::$tenant, 'SELECT id FROM transaktioner WHERE ordre_id = $1', [$id]), 'nothing is posted');
    }

    /** Two accounts each rounding 2.5025 VAT down: the invoice's 5.01 leaves 0.01 on the øre difference account, as before. */
    public function test_dkk_invoice_with_an_ore_difference_posts_as_before(): void
    {
        $id = $this->createOrder('DKK', 'DO', [
            self::line(self::REVENUE, self::VAT25, 25, 10.01),
            self::line(self::REVENUE_ABROAD, self::VAT25, 25, 10.01),
        ], 20.02, 5.01);
        $rows = $this->invoiceRows($id);
        $this->assertSame(self::rows(
            [self::DEBTOR, '25.03', '0.00'],
            [self::REVENUE, '0.00', '10.01'],
            [(string)self::VAT25, '0.00', '2.50'],
            [self::REVENUE_ABROAD, '0.00', '10.01'],
            [(string)self::VAT25, '0.00', '2.50'],
            [self::ORE_DIFF, '0.00', '0.01']
        ), $rows);
        $this->assertPreflightMatchesPosting($id, $rows);
    }
}
