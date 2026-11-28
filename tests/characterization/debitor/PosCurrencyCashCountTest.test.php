<?php
// tests/characterization/debitor/PosCurrencyCashCountTest.test.php
//
// The cash count of a till that takes euro (POS-F2, POS-F3, POS-F8), on the shipped kasseoptalling() and posbogfor()
// of debitor/pos_ordre.php and findBoxSale(), run by support/run_pos_currency_count.php against the class's own tenant.
//
// A POS order is in the base currency. Cash paid in euro is a payment row 'Kontant' in EUR with its value in kroner,
// and the sale posts that value to the kroner till account. The count moves it to the euro till account
// ('Kassesalg EUR'). The count used to find no euro takings (it looked for orders in EUR), listed the euro as a card
// named 'Kontant' in the kroner count, and booked the counted euro as a cash difference, while the kroner till account
// kept their value. With each sale posted at once, a second count the same day moved the euro left in the till from
// the kroner till account again. The turnover the count form posts for the report includes the euro, now that the euro
// is no longer a card. A euro difference on a difference account with a VAT code posted the full amount on the
// difference account and the VAT on the wrong side, so the voucher did not balance. The euro takings and difference
// were cut to whole euro before posting.
//
// Rate 745,60: 20 EUR = 149,12 kr.; 10,75 EUR = 80,15 kr.; 2,50 EUR = 18,64 kr.
//
// History:
// 20261117 CL/ASR Created.
// 20261125 CL/ASR Dates as the page has them (support/AppTimezone.php): between midnight and 01:00 the UTC date of the test process was the day before.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../support/AppTimezone.php';

final class PosCurrencyCashCountTest extends TestCase
{
    use AppTimezone;

    private const RUNNER = __DIR__ . '/../support/run_pos_currency_count.php';

    private const TILL_DKK = 99100;
    private const TILL_EUR = 99110;
    private const DANKORT = 99120;
    private const OTHER_CARDS = 99130;
    private const SAFE_DKK = 99140;
    private const SAFE_EUR = 99145;
    private const DIFF_DKK = 99150;
    private const DIFF_EUR = 99160;
    private const SALES = 99170;
    private const VAT = 99180;

    /** @var resource|\PgSql\Connection|null */
    private static $tenant = null;
    private static int $regnaar = 0;
    private static int $vatCode = 0;
    private static string $today = '';

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        self::useAppTimezone();
        CharacterizationEnv::bootstrapTenant(static::class);
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        self::$today = date('Y-m-d');
        // The fiscal year the runner's bootstrap picks: the RA group that covers this month.
        foreach (CharacterizationEnv::rows(self::$tenant, "SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA'") as $ra) {
            $from = sprintf('%04d%02d', (int)$ra['box2'], (int)$ra['box1']);
            $to = sprintf('%04d%02d', (int)$ra['box4'], (int)$ra['box3']);
            if ($from <= date('Ym') && date('Ym') <= $to) {
                self::$regnaar = (int)$ra['kodenr'];
            }
        }
        self::assertGreaterThan(0, self::$regnaar, 'the fresh tenant has a fiscal year for this month');
        // A VAT code of its own: S<n> = 25 % on account VAT.
        $sm = CharacterizationEnv::one(self::$tenant, "SELECT COALESCE(MAX(kodenr), 0) + 1 AS n FROM grupper WHERE art = 'SM'");
        self::$vatCode = (int)$sm['n'];
        CharacterizationEnv::rows(self::$tenant,
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, fiscal_year) VALUES ('Test salgsmoms 25%', 'S', $1, 'SM', $2, '25', $3)",
            [self::$vatCode, (string)self::VAT, self::$regnaar]);
    }

    public static function tearDownAfterClass(): void
    {
        self::restoreTimezone();
        if (self::$tenant) {
            pg_close(self::$tenant);
            self::$tenant = null;
        }
        CharacterizationEnv::teardownTenant();
    }

    protected function setUp(): void
    {
        $t = self::$tenant;
        foreach (['transaktioner', 'pos_betalinger', 'ordrer', 'report', 'pos_events'] as $table) {
            CharacterizationEnv::rows($t, "DELETE FROM $table");
        }
        CharacterizationEnv::rows($t, "DELETE FROM grupper WHERE art IN ('POS', 'VK', 'OreDif')");
        CharacterizationEnv::rows($t, "DELETE FROM valuta");
        CharacterizationEnv::rows($t, "DELETE FROM settings WHERE var_grp = 'VK'");
        CharacterizationEnv::rows($t, 'DELETE FROM kontoplan WHERE kontonr BETWEEN 99100 AND 99199');
        $accounts = [
            [self::TILL_DKK, 'Kasse DKK', 'S', 1000], [self::TILL_EUR, 'Kasse EUR', 'S', 0],
            [self::DANKORT, 'Dankort', 'S', 0], [self::OTHER_CARDS, 'Andre kort', 'S', 0],
            [self::SAFE_DKK, 'Pengeskab DKK', 'S', 0], [self::SAFE_EUR, 'Pengeskab EUR', 'S', 0],
            [self::DIFF_DKK, 'Kassedifference DKK', 'D', 0], [self::DIFF_EUR, 'Kassedifference EUR', 'D', 0],
            [self::SALES, 'Salg', 'D', 0], [self::VAT, 'Salgsmoms', 'S', 0],
        ];
        foreach ($accounts as [$nr, $name, $type, $primo]) {
            CharacterizationEnv::rows($t,
                "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar, primo, saldo) VALUES ($1, $2, $3, '', $4, $5, 0)",
                [$nr, $name, $type, self::$regnaar, $primo]);
        }
        // One till on account TILL_DKK with Dankort; posting at day close until a test switches box9 on.
        CharacterizationEnv::rows($t,
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, box6, box7, box8, box9, box10, box11, box12, box13, box14, fiscal_year)
             VALUES ('Kasseopsaetning', '', 1, 'POS', '1', $1, '0', '1', 'Dankort', $2, '1', '', '', '', '', '', '', '', $3)",
            [(string)self::TILL_DKK, (string)self::DANKORT, self::$regnaar]);
        // No fixed float, the count assistant on, other cards, safe and difference account for the kroner.
        CharacterizationEnv::rows($t,
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, box6, box7, box8, box9, box10, box11, box12, box13, box14, fiscal_year)
             VALUES ('Kasseopsaetning 2', '', 2, 'POS', '', 'on', 'localhost', '', '', $1, '', $2, $3, '', '', '', '', '', $4)",
            [(string)self::OTHER_CARDS, (string)self::SAFE_DKK, (string)self::DIFF_DKK, self::$regnaar]);
        CharacterizationEnv::rows($t,
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, fiscal_year)
             VALUES ('Kasseopsaetning 3', '', 3, 'POS', '', '', '', '', '', $1)", [self::$regnaar]);
        // Euro is used in the till (box4 '1'), with its safe (box5) and difference account (box6) for till 1;
        // the till account is the settings row VK/till_account_EUR of till 1.
        CharacterizationEnv::rows($t,
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, box6) VALUES ('Euro', '', 1, 'VK', 'EUR', '', '', '1', $1, $2)",
            [(string)self::SAFE_EUR, (string)self::DIFF_EUR]);
        CharacterizationEnv::rows($t, "INSERT INTO valuta (gruppe, valdate, kurs) VALUES (1, '2026-01-01', 745.60)");
        CharacterizationEnv::rows($t,
            "INSERT INTO settings (var_name, var_grp, var_value, var_description, user_id, pos_id) VALUES ('till_account_EUR', 'VK', $1, 'pos.tills.currency_account_1.1', 0, 1)",
            [(string)self::TILL_EUR]);
    }

    public function testImmediatePostingCountsAndPostsTheEuroOnTheEuroTill(): void
    {
        $this->setImmediatePosting(true);
        // The last count of till 1, then a sale of 100,12 kr. paid with 20 EUR (149,12 kr.), 49 kr. back in kroner.
        // The sale is posted as bogfor_nu() posts it: the whole cash amount on the kroner till account.
        $this->transaction(0, 0, 0, 0, 'Kasseoptaelling,kasse 1', '08:00:00');
        $order = $this->posOrder(4, 80.09, 20.03);
        $this->payment($order, 'Kontant', 149.12, 'EUR', 745.6);
        $this->transaction($order, self::TILL_DKK, 100.12, 0, 'Bon - 1', '10:00:00');
        $this->transaction($order, self::SALES, 0, 100.12, 'Bon - 1', '10:00:00');

        $form = $this->countForm(951.00, [20.00]);
        self::assertEqualsWithDelta(-49.00, (float)$form['tilgang'], 0.001, 'kroner takings: only the change given back');
        self::assertEqualsWithDelta(20.00, (float)$form['ValutaTilgang[0]'], 0.0001, 'euro takings: the 20 EUR');
        self::assertEqualsWithDelta(0.00, (float)$form['ValutaKasseDiff[0]'], 0.0001, 'no euro difference');
        self::assertEqualsWithDelta(0.00, (float)$form['kassediff'], 0.001, 'no kroner difference');
        self::assertEqualsWithDelta(100.12, (float)$form['omsatning'], 0.001, 'the turnover for the count report is the sale');

        // 10 EUR go to the safe and 10 EUR stay in the till.
        $form['ValutaUdtages[0]'] = '10,00';
        $rows = $this->close($form);
        self::assertSame([
            ['Kassesalg EUR, kasse 1', self::TILL_EUR, '149.120', '0.000'],
            ['Kassesalg EUR, kasse 1', self::TILL_DKK, '0.000', '149.120'],
        ], $this->rowsLike($rows, 'Kassesalg%'), 'the euro takings move from the kroner till account to the euro till account');
        self::assertSame([], $this->rowsLike($rows, 'Kassedifference%'), 'no cash difference is posted');
        $this->assertBalanced($rows);
        self::assertSame('1000.000', $this->balance(self::TILL_DKK), 'the kroner till account holds the 1.000 kr. left in the drawer');
        self::assertSame('74.560', $this->balance(self::TILL_EUR), 'the euro till account holds the 10 EUR left in the till');
        self::assertSame('74.560', $this->balance(self::SAFE_EUR));

        // A second count the same day: the 10 EUR are the till's float, not takings, and nothing is moved twice.
        $form = $this->countForm(1000.00, [10.00]);
        self::assertEqualsWithDelta(10.00, (float)$form['ValutaByttePenge[0]'], 0.0001, 'the euro float is what the first count left');
        self::assertEqualsWithDelta(0.00, (float)$form['ValutaTilgang[0]'], 0.0001, 'no new euro takings');
        self::assertEqualsWithDelta(0.00, (float)$form['ValutaKasseDiff[0]'], 0.0001);
        self::assertEqualsWithDelta(0.00, (float)$form['kassediff'], 0.001);
        $form['ValutaUdtages[0]'] = '10,00';
        $rows = $this->close($form);
        self::assertSame([], $this->rowsLike($rows, 'Kassesalg%'), 'nothing is moved from the kroner till account again');
        self::assertSame([], $this->rowsLike($rows, 'Kassedifference%'));
        self::assertSame('1000.000', $this->balance(self::TILL_DKK));
        self::assertSame('0.000', $this->balance(self::TILL_EUR), 'the last 10 EUR went to the safe');
        self::assertSame('149.120', $this->balance(self::SAFE_EUR));
    }

    public function testPostingAtDayCloseCountsTheEuroAsEuroTakings(): void
    {
        // An unposted sale of 100,12 kr. paid with 20 EUR: the euro is takings in euro, not a card named 'Kontant'.
        $order = $this->posOrder(3, 80.09, 20.03);
        $this->payment($order, 'Kontant', 149.12, 'EUR', 745.6);

        $form = $this->countForm(951.00, [20.00]);
        self::assertEqualsWithDelta(-49.00, (float)$form['tilgang'], 0.001, 'kroner takings: only the change given back');
        self::assertEqualsWithDelta(20.00, (float)$form['ValutaTilgang[0]'], 0.0001, 'euro takings: the 20 EUR');
        self::assertEqualsWithDelta(0.00, (float)$form['ValutaKasseDiff[0]'], 0.0001, 'no euro difference');
        self::assertEqualsWithDelta(100.12, (float)$form['omsatning'], 0.001, 'the turnover for the count report is the sale');
        $cards = [];
        foreach ($form as $name => $value) {
            if (strpos($name, 'kortnavn[') === 0) {
                $cards[] = $value;
            }
        }
        self::assertSame(['Dankort'], $cards, 'the euro is not listed as a card');
    }

    public function testAEuroDifferenceOnAVatCodedAccountPostsABalancedVoucher(): void
    {
        CharacterizationEnv::rows(self::$tenant, 'UPDATE kontoplan SET moms = $1 WHERE kontonr = $2', ['S' . self::$vatCode, self::DIFF_EUR]);

        $rows = $this->close(['valuta[0]' => 'EUR', 'ValutaKasseDiff[0]' => '20', 'ValutaTilgang[0]' => '0', 'ValutaUdtages[0]' => '0,00', 'kassediff' => '0', 'udtages' => '']);
        self::assertSame([
            ['Kassedifference EUR, kasse 1', self::TILL_EUR, '149.120', '0.000', '0.000'],
            ['Kassedifference EUR, kasse 1', self::DIFF_EUR, '0.000', '119.300', '-29.820'],
            ['Kassedifference, kasse 1', self::VAT, '0.000', '29.820', '0.000'],
        ], $this->rowsLike($rows, 'Kassedifference%', true), 'a surplus of 20 EUR: 119,30 kr. ex VAT and 29,82 kr. VAT, both credited');
        $this->assertBalanced($rows);

        $rows = $this->close(['valuta[0]' => 'EUR', 'ValutaKasseDiff[0]' => '-20', 'ValutaTilgang[0]' => '0', 'ValutaUdtages[0]' => '0,00', 'kassediff' => '0', 'udtages' => '']);
        self::assertSame([
            ['Kassedifference EUR, kasse 1', self::TILL_EUR, '0.000', '149.120', '0.000'],
            ['Kassedifference EUR, kasse 1', self::DIFF_EUR, '119.300', '0.000', '29.820'],
            ['Kassedifference, kasse 1', self::VAT, '29.820', '0.000', '0.000'],
        ], $this->rowsLike($rows, 'Kassedifference%', true), 'a shortage of 20 EUR: both debited');
        $this->assertBalanced($rows);
    }

    public function testEuroTakingsAndDifferenceKeepTheirCents(): void
    {
        $rows = $this->close(['valuta[0]' => 'EUR', 'ValutaTilgang[0]' => '10.75', 'ValutaKasseDiff[0]' => '2.5', 'ValutaUdtages[0]' => '0,00', 'kassediff' => '0', 'udtages' => '']);
        self::assertSame([
            ['Kassesalg EUR, kasse 1', self::TILL_EUR, '80.150', '0.000'],
            ['Kassesalg EUR, kasse 1', self::TILL_DKK, '0.000', '80.150'],
        ], $this->rowsLike($rows, 'Kassesalg%'), '10,75 EUR, not 10 EUR (74,56 kr.)');
        self::assertSame([
            ['Kassedifference EUR, kasse 1', self::TILL_EUR, '18.640', '0.000'],
            ['Kassedifference EUR, kasse 1', self::DIFF_EUR, '0.000', '18.640'],
        ], $this->rowsLike($rows, 'Kassedifference%'), '2,50 EUR, not 2 EUR (14,912 kr.)');
        $this->assertBalanced($rows);

        $rows = $this->close(['valuta[0]' => 'EUR', 'ValutaTilgang[0]' => '0', 'ValutaKasseDiff[0]' => '-2.5', 'ValutaUdtages[0]' => '0,00', 'kassediff' => '0', 'udtages' => '']);
        self::assertSame([
            ['Kassedifference EUR, kasse 1', self::TILL_EUR, '0.000', '18.640'],
            ['Kassedifference EUR, kasse 1', self::DIFF_EUR, '18.640', '0.000'],
        ], $this->rowsLike($rows, 'Kassedifference%'), 'a shortage of 2,50 EUR is not cut to 2 EUR');
    }

    public function testTheCountFormsEuroDifferenceIsPostedWithItsCents(): void
    {
        // 50 EUR (372,80 kr.) in the euro till, 52,50 EUR counted.
        $this->transaction(0, self::TILL_EUR, 372.80, 0, 'Primo EUR', '07:00:00', 1);
        $form = $this->countForm(1000.00, [52.50]);
        self::assertEqualsWithDelta(2.50, (float)$form['ValutaKasseDiff[0]'], 0.0001);
        $form['ValutaUdtages[0]'] = '0,00';
        $rows = $this->close($form);
        self::assertSame([
            ['Kassedifference EUR, kasse 1', self::TILL_EUR, '18.640', '0.000'],
            ['Kassedifference EUR, kasse 1', self::DIFF_EUR, '0.000', '18.640'],
        ], $this->rowsLike($rows, 'Kassedifference%'));
        self::assertSame('391.440', $this->balance(self::TILL_EUR), 'the euro till account holds the 52,50 EUR counted');
    }

    // ------------------------------------------------------------------ helpers

    private function setImmediatePosting(bool $on): void
    {
        CharacterizationEnv::rows(self::$tenant, "UPDATE grupper SET box9 = $1 WHERE art = 'POS' AND kodenr = 1", [$on ? 'on' : '']);
    }

    private function posOrder(int $status, float $sum, float $vat): int
    {
        $row = CharacterizationEnv::one(self::$tenant,
            "INSERT INTO ordrer (ordrenr, konto_id, art, valuta, valutakurs, status, felt_5, fakturadate, ordredate, sum, moms, betalingsbet, felt_1)
             VALUES ((SELECT COALESCE(MAX(ordrenr), 0) + 1 FROM ordrer), 0, 'PO', 'DKK', 100, $1, '1', $2, $2, $3, $4, 'Kontant', 'Kontant') RETURNING id",
            [$status, self::$today, $sum, $vat]);
        return (int)$row['id'];
    }

    private function payment(int $order, string $type, float $amount, string $currency, float $rate): void
    {
        CharacterizationEnv::rows(self::$tenant,
            'INSERT INTO pos_betalinger (ordre_id, betalingstype, amount, valuta, valutakurs) VALUES ($1, $2, $3, $4, $5)',
            [$order, $type, $amount, $currency, $rate]);
    }

    private function transaction(int $order, int $account, float $debit, float $credit, string $text, string $time, int $kladde = 0): void
    {
        CharacterizationEnv::rows(self::$tenant,
            "INSERT INTO transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id, kasse_nr)
             VALUES (0, $1, $2, $3, '0', $4, $5, $6, 0, $1, $7, '', 0, $8, 1)",
            [self::$today, $text, $account, $debit, $credit, $kladde, $time, $order]);
    }

    /** @return array<string, string> The count form's fields by name. */
    private function countForm(float $counted, array $countedForeign): array
    {
        $out = $this->runner('count', ['kasse' => 1, 'optalt' => $counted, 'optval' => $countedForeign]);
        self::assertArrayHasKey('fields', $out);
        $fields = [];
        foreach ($out['fields'] as [$name, $value]) {
            $fields[$name] = $value;
        }
        foreach (['tilgang', 'kassediff', 'omsatning', 'ValutaTilgang[0]', 'ValutaKasseDiff[0]'] as $name) {
            self::assertArrayHasKey($name, $fields, "the count form has $name");
        }
        return $fields;
    }

    /**
     * Posts the count and returns the rows it wrote.
     * @param array<string, string> $fields
     * @return list<array<string, string>>
     */
    private function close(array $fields): array
    {
        $before = (int)CharacterizationEnv::one(self::$tenant, 'SELECT COALESCE(MAX(id), 0) AS id FROM transaktioner')['id'];
        $post = [];
        foreach ($fields as $name => $value) {
            $post[] = [$name, (string)$value];
        }
        $out = $this->runner('close', ['kasse' => 1, 'post' => $post]);
        self::assertTrue($out['posbogfor'] ?? false, 'posbogfor() posts the count');
        return CharacterizationEnv::rows(self::$tenant,
            'SELECT beskrivelse, kontonr, debet, kredit, moms FROM transaktioner WHERE id > $1 ORDER BY id', [$before]);
    }

    /** @return array<string, mixed> */
    private function runner(string $mode, array $args): array
    {
        $res = CharacterizationEnv::runChild(self::RUNNER, [CharacterizationEnv::testDb(), $mode, json_encode($args)]);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout'])), static fn ($line) => $line !== '' && $line[0] === '{'));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        self::assertIsArray($json, "runner $mode must print JSON (exit {$res['exit']}, stdout: {$res['stdout']} stderr: {$res['stderr']})");
        return $json;
    }

    /** @return list<list<mixed>> beskrivelse, kontonr, debet, kredit (and moms) of the rows whose text matches. */
    private function rowsLike(array $rows, string $like, bool $withVat = false): array
    {
        $pattern = '/^' . str_replace('%', '.*', preg_quote($like, '/')) . '$/';
        $out = [];
        foreach ($rows as $row) {
            if (preg_match($pattern, $row['beskrivelse'])) {
                $line = [$row['beskrivelse'], (int)$row['kontonr'], $row['debet'], $row['kredit']];
                if ($withVat) {
                    $line[] = $row['moms'];
                }
                $out[] = $line;
            }
        }
        return $out;
    }

    private function assertBalanced(array $rows): void
    {
        $debit = $credit = 0.0;
        foreach ($rows as $row) {
            $debit += (float)$row['debet'];
            $credit += (float)$row['kredit'];
        }
        self::assertEqualsWithDelta($debit, $credit, 0.0001, 'the posted voucher balances');
    }

    private function balance(int $account): string
    {
        $row = CharacterizationEnv::one(self::$tenant,
            'SELECT (SELECT primo FROM kontoplan WHERE kontonr = $1 AND regnskabsaar = $2) + COALESCE(SUM(debet - kredit), 0) AS saldo FROM transaktioner WHERE kontonr = $1',
            [$account, self::$regnaar]);
        return number_format((float)$row['saldo'], 3, '.', '');
    }
}
