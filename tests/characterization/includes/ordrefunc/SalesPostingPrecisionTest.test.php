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
     * The pre-flight check, run on the invoiced order, must predict exactly the difference that was posted
     * on the currency and øre difference accounts, and the stored rows must balance.
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
        $this->assertEqualsWithDelta($posted, (float)$out['preflight']['difference'], 0.001, 'pre-flight predicts the posted difference');
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
