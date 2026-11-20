<?php
// tests/characterization/finans/ExchangeRateSettlementPageCharacterizationTest.test.php
//
// MB-79/MB-123 through the real page on PostgreSQL: finans/bogfor.php posts a
// journal through its own dispatch (support/run_bogfor_page.php), so the
// exchange difference, the open-post settlement and the refusal of a currency
// line without a rate are checked where they are written, not on lifted code.
// ExchangeRateSettlementTest covers the rules in detail on SQLite.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261120 CL/LH MB-123 Created.
// 20261121 CL/LH MB-123 Voucher roundings both ways on one difference account are shown as the two movements posting books.
// 20261121 CL/LH MB-123 The settlement is read from the three settled rows rather than from the absence of open ones.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class ExchangeRateSettlementPageCharacterizationTest extends CharacterizationTestCase
{
    private static int $eur;
    private static int $usd;
    private static int $expense;
    private static int $bank;
    private static int $diffAccount;
    private static string $day1;
    private static string $day2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        [self::$expense, self::$bank, self::$diffAccount] = self::$fx->plainAccounts(3);
        $ra = self::one("SELECT box1, box2 FROM grupper WHERE art = 'RA' AND kodenr = $1", [(string)self::$regnaar]);
        self::$day1 = sprintf('%04d-%02d-01', (int)$ra['box2'], (int)$ra['box1']);
        self::$day2 = date('Y-m-d', strtotime(self::$day1 . ' +1 day'));
        $next = self::one("SELECT coalesce(max(cast(kodenr as integer)),0)+1 AS n FROM grupper WHERE art = 'VK'");
        self::$eur = (int)$next['n'];
        self::$usd = self::$eur + 1;
        foreach ([[self::$eur, 'EUR', '745'], [self::$usd, 'USD', '680']] as [$code, $letters, $rate]) {
            self::rows("INSERT INTO grupper (beskrivelse, kodenr, kode, art, box1, box2, box3)
                VALUES ($1, $2, '', 'VK', $3, $4, $5)", ["MB-123 $letters", (string)$code, $letters, $rate, (string)self::$diffAccount]);
        }
        // EUR is 745 on day 1 and 746 on day 2. USD has a rate from day 2 only.
        self::rows('INSERT INTO valuta (gruppe, valdate, kurs) VALUES ($1, $2, 745), ($1, $3, 746), ($4, $3, 680)',
            [self::$eur, self::$day1, self::$day2, self::$usd]);
    }

    /** @return array<int,array<string,mixed>> */
    private static function ledger(int $kladdeId): array
    {
        return self::rows('SELECT kontonr, debet, kredit FROM transaktioner WHERE kladde_id = $1 ORDER BY id', [$kladdeId]);
    }

    /** Posts the journal through the page and returns what the page printed. */
    private static function post(int $kladdeId, string $mode = 'bogfor'): string
    {
        return self::runChild('run_bogfor_page.php', [$mode, $kladdeId, CharacterizationEnv::sessionId(), 'output'])['stdout'];
    }

    /**
     * The confirmation page's row for an account in the account movements table, as its cells' text: account,
     * description, balance, debit, credit, new balance. NULL when the page has no row for it.
     *
     * @return list<string>|null
     */
    private static function accountRow(string $page, int $konto): ?array
    {
        if (!preg_match_all('#<tr>(.*?)</tr>#s', $page, $rows)) {
            return null;
        }
        foreach ($rows[1] as $row) {
            preg_match_all('#<td[^>]*>(.*?)</td>#s', $row, $cells);
            $cells = array_map(static fn($c) => trim(html_entity_decode(strip_tags($c))), $cells[1]);
            if (count($cells) === 6 && $cells[0] === (string)$konto) {
                return $cells;
            }
        }
        return null;
    }

    public function test_a_payment_at_another_rate_books_the_difference_and_settles_the_invoice(): void
    {
        $creditor = self::$fx->creditor();
        $faktura = 'MB123-EUR-' . $creditor['kontonr'];
        $kladdeId = self::$fx->kladde([
            ['bilag' => 1, 'transdate' => self::$day1, 'd_type' => 'F', 'debet' => self::$expense,
                'k_type' => 'K', 'kredit' => $creditor['kontonr'], 'amount' => 100.00, 'faktura' => $faktura, 'valuta' => self::$eur],
            ['bilag' => 2, 'transdate' => self::$day2, 'd_type' => 'K', 'debet' => $creditor['kontonr'],
                'k_type' => 'F', 'kredit' => self::$bank, 'amount' => 100.00, 'faktura' => $faktura, 'valuta' => self::$eur],
        ], 'MB-123 settlement');

        self::post($kladdeId);

        $rows = self::ledger($kladdeId);
        $this->assertEqualsWithDelta(self::sumOf($rows, 'debet'), self::sumOf($rows, 'kredit'), 0.005, 'the journal balances');
        $diff = array_values(array_filter($rows, fn($r) => (int)$r['kontonr'] === self::$diffAccount));
        $this->assertCount(1, $diff, 'one row on the difference account');
        $this->assertEqualsWithDelta(1.00, (float)$diff[0]['debet'] + (float)$diff[0]['kredit'], 0.005,
            'EUR 100 booked at 745 and paid at 746 leaves 1,00 DKK');
        $posts = self::rows('SELECT udlignet, udlign_id FROM openpost WHERE konto_id = $1 ORDER BY id', [$creditor['id']]);
        $this->assertCount(3, $posts, 'the invoice, the payment and the difference row are on the open-post ledger');
        $this->assertSame(['1', '1', '1'], array_map(fn($r) => trim((string)$r['udlignet']), $posts), 'and all settled');
        $this->assertGreaterThan(0, (int)$posts[0]['udlign_id']);
        $this->assertSame(array_fill(0, 3, (int)$posts[0]['udlign_id']), array_map(fn($r) => (int)$r['udlign_id'], $posts),
            'together, as one settlement');
        $kladde = self::one('SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]);
        $this->assertSame('V', trim((string)$kladde['bogfort']));
    }

    public function test_a_currency_line_without_a_rate_is_refused_and_nothing_is_posted(): void
    {
        // The USD invoice is booked at 680 on day 2. A payment dated day 1 has no USD rate: it used to post at
        // rate 100 and book 580,00 DKK of difference against the invoice.
        $creditor = self::$fx->creditor();
        $faktura = 'MB123-USD-' . $creditor['kontonr'];
        $kladdeId = self::$fx->kladde([
            ['bilag' => 1, 'transdate' => self::$day2, 'd_type' => 'F', 'debet' => self::$expense,
                'k_type' => 'K', 'kredit' => $creditor['kontonr'], 'amount' => 100.00, 'faktura' => $faktura, 'valuta' => self::$usd],
            ['bilag' => 2, 'transdate' => self::$day1, 'd_type' => 'K', 'debet' => $creditor['kontonr'],
                'k_type' => 'F', 'kredit' => self::$bank, 'amount' => 100.00, 'faktura' => $faktura, 'valuta' => self::$usd],
        ], 'MB-123 missing rate');

        // The runner keeps the page's output (SALDI_CHAR_DEBUG prints it); the message is pinned in
        // ExchangeRateSettlementTest, so this checks what reaches the database.
        self::post($kladdeId);

        $this->assertSame([], self::ledger($kladdeId), 'nothing reaches the ledger');
        $this->assertSame([], self::rows('SELECT id FROM openpost WHERE konto_id = $1', [$creditor['id']]),
            'nor the open-post ledger');
        $kladde = self::one('SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]);
        $this->assertNotSame('V', trim((string)$kladde['bogfort']), 'and the journal is not marked posted');
    }

    public function test_voucher_roundings_both_ways_are_shown_as_the_movements_posting_books(): void
    {
        // At 745, EUR 0,50 is 3,73 DKK. Voucher 1 debits EUR 1,00 (7,45) and credits 0,50 twice (7,46), which
        // leaves 0,01 to debit on the difference account; voucher 2 debits 0,50 four times (14,92) and credits
        // 2,00 (14,90), 0,02 to credit. Posting books each voucher's rounding as a row of its own, and the
        // confirmation page used to show only their net, 0,01 credit.
        $line = fn(int $bilag, string $side, int $konto, float $amount) => ['bilag' => $bilag, 'transdate' => self::$day1,
            $side === 'd' ? 'debet' : 'kredit' => $konto, 'amount' => $amount, 'valuta' => self::$eur];
        $kladdeId = self::$fx->kladde([
            $line(1, 'd', self::$expense, 1.00), $line(1, 'k', self::$bank, 0.50), $line(1, 'k', self::$bank, 0.50),
            $line(2, 'd', self::$expense, 0.50), $line(2, 'd', self::$expense, 0.50), $line(2, 'd', self::$expense, 0.50),
            $line(2, 'd', self::$expense, 0.50), $line(2, 'k', self::$bank, 2.00),
        ], 'MB-123 roundings both ways');
        // The journal form saves a line's department as 0; the rounding row carries it into an integer column.
        self::rows('UPDATE kassekladde SET afd = 0 WHERE kladde_id = $1', [$kladdeId]);

        $row = self::accountRow(self::post($kladdeId, 'vis'), self::$diffAccount);
        $this->assertNotNull($row, 'the confirmation page has a row for the difference account');
        $this->assertSame(['0,01', '0,02'], [$row[3], $row[4]], 'with the 0,01 debit and the 0,02 credit');

        self::post($kladdeId);
        $diff = array_values(array_filter(self::ledger($kladdeId), fn($r) => (int)$r['kontonr'] === self::$diffAccount));
        $this->assertEqualsWithDelta([0.01, 0.02], [self::sumOf($diff, 'debet'), self::sumOf($diff, 'kredit')], 0.0001,
            'which is what posting books');
    }
}
