<?php
// tests/characterization/finans/HentOrdrerFlytTest.test.php
//
// Hent ordrer (finans/hentordrer.php) moves an invoice that was not posted
// straight away into the journal.
//
// With "Bogfør straks" off (grupper DIV/3 box5), invoicing leaves the order
// at status 3, and Kassekladde > Hent ordrer > "Flyt til kladde" is how it
// reaches the ledger: flytordre() writes the invoice's journal lines and sets
// the order to status 4. flytordre() split the invoice date with split(),
// which PHP 7 removed, so every Flyt died with "Call to undefined function
// split()" before it wrote anything (testlab round 1,
// deferred-invoice-posting).
//
// Pins: Flyt til kladde on a deferred sales invoice writes the debtor line
// for the gross amount, the revenue line and the VAT line to the journal,
// sets the order to status 4, and posts nothing to transaktioner or openpost
// yet (that happens when the journal is posted).
//
// The journal, once posted, books what immediate posting books for the same
// invoice: a discount in kr per unit (rabatart 'amount'), procent
// (procentfakturering) and whole øre per account, as bogfor_nu() in
// includes/ordrefunc.php posts them; a sales or supplier credit note with
// every line on the opposite side, as bogfor_nu() and kreditor/bogfor.php post
// it, never as a negative amount. Deleting every journal line of a moved
// invoice before the journal is posted puts it back to status 3, so it can be
// fetched again. Flyt alle leaves a POS bon at status 3 for the register
// close, and with vis_saet (POS/2 box12), where the register close takes sales
// orders, Hent ordrer leaves an ordinary order paid at a till (the order's
// kasse fields) to the register close as well; without vis_saet those fields
// are free order fields and the order is moved as before. An øre left by
// rounding two revenue accounts goes to the øre account (OreDif, an operating
// or status account) as bogfor_nu() posts it, and so does the whole
// difference that 10 or 12 projects of half an øre each add up to (the 5 øre
// limit is for the lines at 3 decimals); without an øre account the invoice is
// not moved and stays at status 3. Every voucher balances on its own, also an
// invoice and its credit note in one journal; an invoice whose lines would not
// balance is not moved and stays at status 3 (audit 20261114: sales F5, F9,
// critic C-2, C-3, and their review).
//
// A supplier in an EU goods (E) or EU services (Y) group gets the reverse-charge
// VAT kreditor/bogfor.php books, in the journal and in the list. A customer or
// supplier group without a VAT code is moved without a VAT line, as immediate
// posting books it; an invoice with VAT for such a customer stays at status 3.
// A foreign-currency invoice's revenue, VAT and rounding lines are written in
// DKK, converted before they are rounded, and the difference is on the
// currency's difference account, as bogfor_nu() posts it. Two users moving one
// invoice at the same time write it into one journal; the other is told (P1
// journal review: F1, F2, J1, J2).
//
// The list and the move read the groups of the fiscal year the invoice is
// posted in, as immediate posting does, also when another year's row of the
// same group is read first. A moved foreign-currency invoice whose rate is
// corrected before the journal is posted is refused with a message that names
// the voucher and the rates and says to delete its lines and move it again,
// which then posts it at the corrected rate. The Simulér and Bogfør previews,
// where such a journal stops, show the same message (P1 journal review round
// 2: N1, J1b). So are a moved invoice and its credit note after the same
// correction, whose vouchers are off by opposite amounts while the journal's
// total is 0; once each voucher balances on its own, the journal is posted
// (P1 journal review round 3: J3h).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261115 CL/ASR Integration: master's per-class test database; the session id comes from CharacterizationEnv::sessionId().
// 20261117 CL/ASR Kr discount, procent, credit notes (sales and supplier), deleting the moved lines and Flyt alle with a POS bon.
//                 An order paid at a till, and an invoice on two revenue accounts with and without an øre account.
//                 The till-paid test sets vis_saet; without it an order with numbers in its order fields is moved; a status account as øre account.
//                 An invoice on 10 and on 12 projects, an invoice and its credit note in one journal, and an invoice whose lines do not balance.
// 20261119 CL/ASR EU goods and services purchases with list rows, customer and supplier groups without a VAT code, a EUR invoice and credit note, and two concurrent moves of one invoice.
//                 hentordrerPage() also fails on db_select()'s error alert in the page.
//                 A purchase and a sales invoice whose groups have another fiscal year's row read first, and a moved EUR invoice whose rate is corrected before Bogfør (the voucher alone, and after another voucher). The Simulér and Bogfør previews of such invoices, one alone and two in one journal.
// 20261120 CL/ASR A moved EUR invoice and its credit note in one journal whose rate is corrected: refused although the journal's total is 0, and posted once each voucher balances (p1-journal-J3h).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class HentOrdrerFlytTest extends CharacterizationTestCase
{
    /** @var list<array{id:string, box5:?string}> grupper DIV/3 before the test turned "Bogfør straks" off; restored in tearDown(). */
    private array $orderSettings = [];

    protected function setUp(): void
    {
        $this->orderSettings = self::rows("SELECT id, box5 FROM grupper WHERE art = 'DIV' AND kodenr = 3");
        $this->assertNotSame([], $this->orderSettings, 'the installed tenant has the order settings row (grupper DIV/3)');
        self::rows("UPDATE grupper SET box5 = '' WHERE art = 'DIV' AND kodenr = 3");
    }

    protected function tearDown(): void
    {
        foreach ($this->orderSettings as $row) {
            self::rows('UPDATE grupper SET box5 = $1 WHERE id = $2', [$row['box5'], $row['id']]);
        }
    }

    /**
     * A sales invoice for 2 x 125.00 + 25% VAT, invoiced through ordrefunc's
     * bogfor() with "Bogfør straks" off, so it is left at status 3.
     *
     * @return array{id:int, kontonr:string, konto_id:int, fakturanr:string, fakturadate:string}
     */
    private function deferredInvoice(): array
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['stocked' => false]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 2, 'pris' => 125.00]]);
        // opret_ordrelinje() stores the line's project as text; flytordre() books the lines per project.
        self::rows("UPDATE ordrelinjer SET projekt = '' WHERE ordre_id = $1", [$order['id']]);

        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['bogfor'] ?? null, 'the order is invoiced');

        $row = self::one('SELECT status, fakturanr, fakturadate FROM ordrer WHERE id = $1', [$order['id']]);
        $this->assertSame('3', (string)$row['status'], 'invoiced, not posted: Hent ordrer lists it');
        $this->assertSame([], self::rows('SELECT id FROM transaktioner WHERE ordre_id = $1', [$order['id']]), 'nothing is in the ledger yet');

        return [
            'id' => $order['id'],
            'kontonr' => $debtor['kontonr'],
            'konto_id' => $debtor['id'],
            'fakturanr' => (string)$row['fakturanr'],
            'fakturadate' => (string)$row['fakturadate'],
        ];
    }

    public function test_flyt_til_kladde_moves_a_deferred_invoice_into_the_journal(): void
    {
        $invoice = $this->deferredInvoice();
        $kladdeId = self::$fx->kladde([]);
        $revenueAccount = self::one('SELECT DISTINCT bogf_konto FROM ordrelinjer WHERE ordre_id = $1', [$invoice['id']]);
        $vatAccount = self::one(
            "SELECT sm.box1 FROM adresser a
             JOIN grupper dg ON dg.art = 'DG' AND dg.kodenr = a.gruppe
             JOIN grupper sm ON sm.art = 'SM' AND sm.kodenr = substr(trim(dg.box1), 2, 1)::integer
             WHERE a.id = $1",
            [$invoice['konto_id']]
        );

        $res = self::runChild('run_hentordrer_page.php', [
            "kladde_id=$kladdeId&ordre_id={$invoice['id']}",
            CharacterizationEnv::sessionId(),
        ]);
        $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the page ran to its end.\nstderr: {$res['stderr']}");

        $lines = array_map(
            static fn(array $r): array => [
                'debet' => $r['d_type'] ? [$r['d_type'], (int)$r['debet']] : null,
                'kredit' => $r['k_type'] ? [$r['k_type'], (int)$r['kredit']] : null,
                'amount' => round((float)$r['amount'], 2),
                'faktura' => $r['faktura'],
            ],
            self::rows(
                'SELECT d_type, debet, k_type, kredit, amount, faktura FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 ORDER BY id',
                [$kladdeId, $invoice['id']]
            )
        );
        $this->assertSame(
            [
                ['debet' => ['D', (int)$invoice['kontonr']], 'kredit' => null, 'amount' => 312.5, 'faktura' => $invoice['fakturanr']],
                ['debet' => null, 'kredit' => ['F', (int)$revenueAccount['bogf_konto']], 'amount' => 250.0, 'faktura' => $invoice['fakturanr']],
                ['debet' => null, 'kredit' => ['F', (int)$vatAccount['box1']], 'amount' => 62.5, 'faktura' => $invoice['fakturanr']],
            ],
            $lines,
            'the debtor is debited the gross amount, revenue and VAT are credited'
        );

        $debtorLine = self::one("SELECT forfaldsdate FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 AND d_type = 'D'", [$kladdeId, $invoice['id']]);
        $this->assertSame(
            date('Y-m-d', strtotime($invoice['fakturadate'] . ' +8 days')),
            $debtorLine['forfaldsdate'],
            'the debtor line is due on the invoice\'s terms (Netto 8)'
        );

        $order = self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']]);
        $this->assertSame('4', (string)$order['status'], 'the order is marked as moved, so Hent ordrer no longer lists it');
        $this->assertSame([], self::rows('SELECT id FROM transaktioner WHERE ordre_id = $1', [$invoice['id']]), 'the ledger is written when the journal is posted, not by the move');
        $this->assertSame(
            [],
            self::rows('SELECT id FROM openpost WHERE konto_id = $1 AND faktnr = $2', [$invoice['konto_id'], $invoice['fakturanr']]),
            'no open post before the journal is posted'
        );
    }

    // ------------------------------------------------------------------ helpers for the 20261117 tests

    /**
     * A sales order for one debtor, invoiced through ordrefunc's bogfor(): with "Bogfør straks" off it is left at
     * status 3 for Hent ordrer, with it on bogfor_nu() posts it at once.
     *
     * Each line carries the discount as a rabatgruppe or quantity discount leaves it on the stored line
     * (rabat, rabatart) and procent (procentfakturering); the header holds the totals of those lines.
     * A line without 'item' is for one untracked item, without 'projekt' on no project; $header sets order fields
     * before Fakturér.
     *
     * @param list<array{antal:float, pris:float, rabat?:float, rabatart?:string, procent?:float, item?:array, projekt?:string}> $lines
     * @param array<string, string> $header
     * @return array{id:int, konto_id:int, kontonr:string, fakturanr:string, art:string, sum:float, moms:float}
     */
    private function salesInvoice(array $lines, bool $postRightAway, array $header = []): array
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['stocked' => false]);
        $order = self::$fx->order($debtor, array_map(static fn(array $l): array => ['item' => $l['item'] ?? $item, 'antal' => $l['antal'], 'pris' => $l['pris']], $lines));
        foreach ($header as $field => $value) {
            self::rows("UPDATE ordrer SET $field = $1 WHERE id = $2", [$value, $order['id']]);
        }
        $sum = 0.0;
        foreach ($lines as $i => $l) {
            $rabat = (float)($l['rabat'] ?? 0);
            $rabatart = (string)($l['rabatart'] ?? 'percent');
            $procent = (float)($l['procent'] ?? 100);
            self::rows(
                'UPDATE ordrelinjer SET rabat = $1, rabatart = $2, procent = $3, projekt = $4 WHERE ordre_id = $5 AND posnr = $6',
                [$rabat, $rabatart, $procent, (string)($l['projekt'] ?? ''), $order['id'], $i + 1]
            );
            $net = $rabatart === 'amount' ? ($l['pris'] - $rabat) * $l['antal'] : $l['pris'] * $l['antal'] * (1 - $rabat / 100);
            $sum += round($net * $procent / 100, 3);
        }
        self::rows('UPDATE ordrer SET sum = $1, moms = $2 WHERE id = $3', [round($sum, 2), round(round($sum, 2) * 0.25, 2), $order['id']]);
        self::rows("UPDATE grupper SET box5 = $1 WHERE art = 'DIV' AND kodenr = 3", [$postRightAway ? 'on' : '']);

        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['bogfor'] ?? null, 'the order is invoiced');
        $row = self::one('SELECT status, art, fakturanr, sum, moms FROM ordrer WHERE id = $1', [$order['id']]);
        $this->assertSame($postRightAway ? '4' : '3', (string)$row['status'], $postRightAway ? 'posted at once' : 'invoiced, not posted: Hent ordrer lists it');

        return [
            'id' => $order['id'],
            'konto_id' => $debtor['id'],
            'kontonr' => $debtor['kontonr'],
            'fakturanr' => (string)$row['fakturanr'],
            'art' => (string)$row['art'],
            'sum' => (float)$row['sum'],
            'moms' => (float)$row['moms'],
        ];
    }

    /** Hent ordrer > Flyt til kladde for one order, into the given journal or a new one; returns the journal's id. */
    private function moveToJournal(int $orderId, ?int $kladdeId = null): int
    {
        $kladdeId ??= self::$fx->kladde([]);
        $res = self::runChild('run_hentordrer_page.php', ["kladde_id=$kladdeId&ordre_id=$orderId", CharacterizationEnv::sessionId()]);
        $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the page ran to its end.\nstderr: {$res['stderr']}");
        return $kladdeId;
    }

    /** Runs Hent ordrer with the given query string and returns the page's HTML (the order list, an alert). */
    private function hentordrerPage(string $query): string
    {
        $htmlFile = (string)tempnam(sys_get_temp_dir(), 'chartest-hentordrer-');
        try {
            $res = self::runChild('run_hentordrer_page.php', [$query, CharacterizationEnv::sessionId(), $htmlFile]);
            $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the page ran to its end.\nstderr: {$res['stderr']}");
            $this->assertStringNotContainsString('db_select failed', $res['stderr'], 'the page runs without a database error');
            $html = (string)file_get_contents($htmlFile);
            // A failed query also shows db_select()'s alert. Its log line on stderr can be overwritten by the error mail's
            // own output (msmtp, which cannot connect in the test stack), the alert cannot.
            $this->assertStringNotContainsString('Uforudset h', $html, 'the page shows no database error');
            return $html;
        } finally {
            @unlink($htmlFile);
        }
    }

    /**
     * An untracked item whose item group books its sales on a second revenue account, with the VAT code of the
     * first one: an invoice with this item and the item of salesInvoice() has two revenue accounts.
     *
     * @return array{item:array, account:int}
     */
    private function itemOnASecondRevenueAccount(): array
    {
        $item = self::$fx->item(['stocked' => false]);
        $group = self::one("SELECT box3, box4 FROM grupper WHERE art = 'VG' AND kodenr = $1 AND fiscal_year = $2", [$item['gruppe'], self::$regnaar]);
        $first = self::one('SELECT moms FROM kontoplan WHERE kontonr = $1 AND regnskabsaar = $2', [(int)$group['box4'], self::$regnaar]);
        $account = (int)self::one(
            'SELECT g AS kontonr FROM generate_series(9100, 9899) g
             WHERE NOT EXISTS (SELECT 1 FROM kontoplan k WHERE k.kontonr = g AND k.regnskabsaar = $1) ORDER BY g LIMIT 1',
            [self::$regnaar]
        )['kontonr'];
        self::rows(
            "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, lukket, primo, saldo, regnskabsaar) VALUES ($1, $2, 'D', $3, '', 0, 0, $4)",
            [$account, Fixtures::TAG . ' salg B', $first['moms'], self::$regnaar]
        );
        $kodenr = (int)self::one("SELECT COALESCE(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'VG'")['kodenr'];
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box3, box4, fiscal_year) VALUES ($1, '', $2, 'VG', $3, $4, $5)",
            [Fixtures::TAG . ' ydelser B', $kodenr, $group['box3'], (string)$account, self::$regnaar]
        );
        self::rows('UPDATE varer SET gruppe = $1 WHERE id = $2', [$kodenr, $item['id']]);
        return ['item' => ['gruppe' => $kodenr] + $item, 'account' => $account];
    }

    /**
     * Indstillinger > Diverse > Øredifferencer: the øre account (grupper OreDif box2), by default a plain operating
     * account; returns the account.
     */
    private function setOreAccount(?int $account = null): int
    {
        $account ??= self::$fx->plainAccounts(1)[0];
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, fiscal_year) VALUES ('Oredifferencer', '', 1, 'OreDif', '0.05', $1, $2)",
            [(string)$account, self::$regnaar]
        );
        return $account;
    }

    private function removeOreAccount(): void
    {
        self::rows("DELETE FROM grupper WHERE art = 'OreDif'");
    }

    /**
     * 3 x 99.95 less 10 % (269.865) on the first revenue account and 1 x 10.01 less 50 % (5.005) on a second one:
     * 274.87 + 68.72 VAT. Each account rounded to whole øre is 269.87 + 5.01 = 274.88, an øre more than the
     * invoice. $sign -1 is the credit note of the same lines.
     *
     * @return list<array{antal:float, pris:float, rabat?:float, item?:array}>
     */
    private static function twoAccountLines(array $secondItem, int $sign): array
    {
        return [
            ['antal' => 3 * $sign, 'pris' => 99.95, 'rabat' => 10],
            ['antal' => 1 * $sign, 'pris' => 10.01, 'rabat' => 50, 'item' => $secondItem],
        ];
    }

    /** The journal lines sorted by side, account and amount, for lines whose order of writing is not fixed. */
    private static function sorted(array $lines): array
    {
        usort($lines, static fn(array $a, array $b): int => [$a[0], $a[2], $a[3]] <=> [$b[0], $b[2], $b[3]]);
        return $lines;
    }

    /**
     * The journal lines of one order as [side, type, account, amount], in the order they were written.
     *
     * @return list<array{0:string, 1:string, 2:int, 3:float}>
     */
    private function journalLines(int $kladdeId, int $orderId): array
    {
        return array_map(
            static fn(array $r): array => $r['d_type']
                ? ['debet', $r['d_type'], (int)$r['debet'], round((float)$r['amount'], 3)]
                : ['kredit', $r['k_type'], (int)$r['kredit'], round((float)$r['amount'], 3)],
            self::rows('SELECT d_type, debet, k_type, kredit, amount FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 ORDER BY id', [$kladdeId, $orderId])
        );
    }

    /** Posts the journal the way Kassekladde > Bogfør does (finans/bogfor.php). */
    private function postJournal(int $kladdeId): void
    {
        self::runChild('run_bogfor_page.php', ['bogfor', $kladdeId, CharacterizationEnv::sessionId()]);
        $this->assertSame('V', (string)self::one('SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId])['bogfort'], "journal $kladdeId is posted");
    }

    /**
     * The ledger an order left, as account => [debit, credit] (the journal's posting carries the order id).
     *
     * @return array<int, array{0:float, 1:float}>
     */
    private function ledger(int $orderId): array
    {
        $ledger = [];
        foreach (self::rows('SELECT kontonr, sum(coalesce(debet, 0)) AS d, sum(coalesce(kredit, 0)) AS k FROM transaktioner WHERE ordre_id = $1 GROUP BY kontonr ORDER BY kontonr', [$orderId]) as $r) {
            $ledger[(int)$r['kontonr']] = [round((float)$r['d'], 3), round((float)$r['k'], 3)];
        }
        return $ledger;
    }

    /**
     * @param array<int, array{0:float, 1:float}> $ledger
     * @return array<int, array{0:float, 1:float}> The same, by account number, as ledger() returns it.
     */
    private static function byAccount(array $ledger): array
    {
        ksort($ledger);
        return $ledger;
    }

    /** @return array{revenue:int, vat:int, collective:int} The accounts a sales invoice of salesInvoice() is booked on. */
    private function salesAccounts(int $orderId, int $kontoId): array
    {
        $revenue = self::one('SELECT DISTINCT bogf_konto FROM ordrelinjer WHERE ordre_id = $1', [$orderId]);
        $groups = self::one(
            "SELECT sm.box1 AS vat, dg.box2 AS collective FROM adresser a
             JOIN grupper dg ON dg.art = 'DG' AND dg.kodenr = a.gruppe
             JOIN grupper sm ON sm.art = 'SM' AND sm.kodenr = substr(trim(dg.box1), 2, 1)::integer
             WHERE a.id = $1",
            [$kontoId]
        );
        return ['revenue' => (int)$revenue['bogf_konto'], 'vat' => (int)$groups['vat'], 'collective' => (int)$groups['collective']];
    }

    // ------------------------------------------------------------------ 20261117 tests

    public function test_a_discount_in_kr_reaches_the_ledger_as_immediate_posting_books_it(): void
    {
        // 3 x 99.95 less 10 % = 269.865, and 2 x 125.01 less 20 kr per unit (a rabatgruppe in kr) = 210.02: 479.89 + 25 % VAT
        $lines = [
            ['antal' => 3, 'pris' => 99.95, 'rabat' => 10],
            ['antal' => 2, 'pris' => 125.01, 'rabat' => 20, 'rabatart' => 'amount'],
        ];
        $invoice = $this->salesInvoice($lines, false);
        $this->assertSame([479.89, 119.97], [$invoice['sum'], $invoice['moms']], 'the invoice is for 479.89 + 119.97 VAT');
        $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);

        $kladdeId = $this->moveToJournal($invoice['id']);
        $this->assertSame(
            [
                ['debet', 'D', (int)$invoice['kontonr'], 599.86],
                ['kredit', 'F', $acc['revenue'], 479.89],
                ['kredit', 'F', $acc['vat'], 119.97],
            ],
            $this->journalLines($kladdeId, $invoice['id']),
            'the revenue line is the invoiced 479.89, not 469.881 (the kr discount read as percent), so the voucher balances'
        );

        $this->postJournal($kladdeId);
        $deferred = $this->ledger($invoice['id']);
        $this->assertSame(
            self::byAccount([$acc['revenue'] => [0.0, 479.89], $acc['collective'] => [599.86, 0.0], $acc['vat'] => [0.0, 119.97]]),
            $deferred,
            'the posted journal books revenue 479.89, VAT 119.97 and the debtor 599.86'
        );

        $twin = $this->salesInvoice($lines, true);
        $this->assertSame($this->ledger($twin['id']), $deferred, 'the same invoice posted at once (bogfor_nu) books the same amounts per account');
    }

    public function test_procent_invoicing_reaches_the_journal_at_the_invoiced_share(): void
    {
        // procentfakturering: 50 % of 2 x 125.00 is invoiced, 125.00 + 31.25 VAT
        $invoice = $this->salesInvoice([['antal' => 2, 'pris' => 125.00, 'procent' => 50]], false);
        $this->assertSame([125.0, 31.25], [$invoice['sum'], $invoice['moms']], 'the invoice is for half the lines');
        $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);

        $kladdeId = $this->moveToJournal($invoice['id']);
        $this->assertSame(
            [
                ['debet', 'D', (int)$invoice['kontonr'], 156.25],
                ['kredit', 'F', $acc['revenue'], 125.0],
                ['kredit', 'F', $acc['vat'], 31.25],
            ],
            $this->journalLines($kladdeId, $invoice['id']),
            'the revenue line is the invoiced 125.00, not the full 250.00'
        );
        $this->postJournal($kladdeId);
        $this->assertSame(
            self::byAccount([$acc['revenue'] => [0.0, 125.0], $acc['collective'] => [156.25, 0.0], $acc['vat'] => [0.0, 31.25]]),
            $this->ledger($invoice['id']),
            'the posted journal books the invoiced share'
        );
    }

    public function test_a_sales_credit_note_is_moved_with_every_line_on_the_opposite_side(): void
    {
        $lines = [['antal' => -2, 'pris' => 125.00]];
        $invoice = $this->salesInvoice($lines, false);
        $this->assertSame('DK', $invoice['art'], 'only negative lines: invoicing makes it a credit note');
        $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);

        $kladdeId = $this->moveToJournal($invoice['id']);
        $this->assertSame(
            [
                ['kredit', 'D', (int)$invoice['kontonr'], 312.5],
                ['debet', 'F', $acc['revenue'], 250.0],
                ['debet', 'F', $acc['vat'], 62.5],
            ],
            $this->journalLines($kladdeId, $invoice['id']),
            'the VAT is debited 62.50, not credited -62.50'
        );

        $this->postJournal($kladdeId);
        $deferred = $this->ledger($invoice['id']);
        $this->assertSame(
            self::byAccount([$acc['revenue'] => [250.0, 0.0], $acc['collective'] => [0.0, 312.5], $acc['vat'] => [62.5, 0.0]]),
            $deferred,
            'no negative debit or credit in the ledger'
        );
        $twin = $this->salesInvoice($lines, true);
        $this->assertSame($this->ledger($twin['id']), $deferred, 'the same credit note posted at once (bogfor_nu) books the same amounts per account');
    }

    public function test_a_supplier_credit_note_is_moved_with_every_line_on_the_opposite_side(): void
    {
        // A supplier credit note for 2 x 50.00 + 25 % VAT, as kreditor/bogfor.php leaves it with immediate posting of
        // purchases off: status 3, the invoice date and receipt number set, each line on its purchase account.
        $creditor = self::$fx->creditor();
        $item = self::$fx->item(['stocked' => false]);
        $order = self::$fx->creditorOrder($creditor, [['item' => $item, 'antal' => -2, 'pris' => 50.00]], ['art' => 'KK', 'status' => 3]);
        $purchase = (int)self::one("SELECT box3 FROM grupper WHERE art = 'VG' AND kodenr = $1 AND fiscal_year = $2", [$item['gruppe'], self::$regnaar])['box3'];
        $fakturanr = 'KN' . $order['id'];
        self::rows("UPDATE ordrer SET fakturadate = ordredate, fakturanr = $1, modtagelse = $2 WHERE id = $3", [$fakturanr, $order['id'], $order['id']]);
        self::rows("UPDATE ordrelinjer SET bogf_konto = $1, projekt = '' WHERE ordre_id = $2", [$purchase, $order['id']]);
        $groups = self::one(
            "SELECT kg.box2 AS collective, km.box1 AS vat FROM adresser a
             JOIN grupper kg ON kg.art = 'KG' AND kg.kodenr = a.gruppe
             JOIN grupper km ON km.art = 'KM' AND km.kodenr = substr(trim(kg.box1), 2, 1)::integer
             WHERE a.id = $1",
            [$creditor['id']]
        );

        $kladdeId = $this->moveToJournal($order['id']);
        $this->assertSame(
            [
                ['debet', 'K', (int)$creditor['kontonr'], 125.0],
                ['kredit', 'F', $purchase, 100.0],
                ['kredit', 'F', (int)$groups['vat'], 25.0],
            ],
            $this->journalLines($kladdeId, $order['id']),
            'the supplier is debited 125.00 and the purchase VAT credited 25.00, not credited -125.00 and debited -25.00'
        );

        $this->postJournal($kladdeId);
        $this->assertSame(
            self::byAccount([$purchase => [0.0, 100.0], (int)$groups['collective'] => [125.0, 0.0], (int)$groups['vat'] => [0.0, 25.0]]),
            $this->ledger($order['id']),
            'the ledger has the amounts kreditor/bogfor.php books: supplier debit 125.00, purchases credit 100.00, VAT credit 25.00'
        );
        $this->assertSame(
            ['125.000'],
            array_column(self::rows('SELECT amount FROM openpost WHERE konto_id = $1 AND faktnr = $2', [$creditor['id'], $fakturanr]), 'amount'),
            'the open item is +125.00 on the supplier, as before'
        );
    }

    public function test_deleting_the_moved_lines_lets_the_invoice_be_fetched_again(): void
    {
        $invoice = $this->salesInvoice([['antal' => 2, 'pris' => 125.00]], false);
        $kladdeId = $this->moveToJournal($invoice['id']);
        self::rows("UPDATE kladdeliste SET bogfort = '-' WHERE id = $1", [$kladdeId]); // an open journal, as kassekladde.php creates it
        $lineIds = array_map('intval', array_column(self::rows('SELECT id FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 ORDER BY id', [$kladdeId, $invoice['id']]), 'id'));
        $this->assertCount(3, $lineIds, 'debtor, revenue and VAT line');
        $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'moved: status 4');

        $this->deleteJournalLines($kladdeId, [$lineIds[2]]);
        $this->assertSame([], self::rows('SELECT id FROM kassekladde WHERE id = $1', [$lineIds[2]]), 'the save deleted the VAT line');
        $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'two of its lines are still in the journal: it stays moved');

        $this->deleteJournalLines($kladdeId, [$lineIds[0], $lineIds[1]]);
        $this->assertSame([], self::rows('SELECT id FROM kassekladde WHERE ordre_id = $1', [$invoice['id']]), 'no journal line of the invoice is left');
        $this->assertSame('3', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'back to status 3: Hent ordrer lists it again');
        $this->assertSame([], self::rows('SELECT id FROM transaktioner WHERE ordre_id = $1', [$invoice['id']]), 'nothing of it was posted');

        $again = $this->moveToJournal($invoice['id']);
        $this->assertCount(3, $this->journalLines($again, $invoice['id']), 'Flyt til kladde moves it again');
        $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'and marks it moved');
    }

    public function test_flyt_alle_leaves_a_pos_bon_for_the_register_close(): void
    {
        // Flyt alle moves every order Hent ordrer lists; set aside status-3 orders of the other tests.
        $setAside = array_column(self::rows("SELECT id FROM ordrer WHERE status = 3"), 'id');
        foreach ($setAside as $id) {
            self::rows('UPDATE ordrer SET status = 2 WHERE id = $1', [$id]);
        }
        try {
            $invoice = $this->salesInvoice([['antal' => 2, 'pris' => 125.00]], false);
            // A cash bon that waits for the register close: status 3, no customer, till 1 (POS/1 box9 empty).
            $item = self::$fx->item(['stocked' => false]);
            $bon = self::one(
                "INSERT INTO ordrer (konto_id, kontonr, firmanavn, art, valuta, valutakurs, ordredate, levdate, fakturadate, ordrenr, fakturanr,
                                     sum, moms, momssats, status, ref, felt_1, felt_2, felt_5, betalt)
                 VALUES (0, '', '', 'PO', 'DKK', 100, CURRENT_DATE, CURRENT_DATE, CURRENT_DATE, (SELECT COALESCE(max(ordrenr), 0) + 1 FROM ordrer), 'B1',
                         100, 25, 25, 3, $1, 'Kontant', '125', '1', 125)
                 RETURNING id",
                [Fixtures::TAG]
            );
            self::rows(
                "INSERT INTO ordrelinjer (ordre_id, posnr, vare_id, varenr, beskrivelse, antal, pris, rabat, momssats, procent, projekt)
                 VALUES ($1, 1, $2, $3, 'bon', 1, 100, 0, 25, 100, '')",
                [$bon['id'], $item['id'], $item['varenr']]
            );
            $kladdeId = self::$fx->kladde([]);

            $res = self::runChild('run_hentordrer_page.php', ["kladde_id=$kladdeId&flyt=alle", CharacterizationEnv::sessionId()]);
            $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the page ran to its end.\nstderr: {$res['stderr']}");
            // flytordre() on a bon queried grupper with kodenr='' (an undefined $momskode): PostgreSQL refuses it, the page
            // showed "Uforudset hændelse" and stopped, and every later Flyt alle stopped at the same bon.
            $this->assertStringNotContainsString('db_select failed', $res['stderr'], 'Flyt alle runs without a database error');

            $this->assertCount(3, $this->journalLines($kladdeId, $invoice['id']), 'the invoice is moved');
            $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'and marked moved');
            $this->assertSame([], $this->journalLines($kladdeId, (int)$bon['id']), 'the bon is not moved');
            $this->assertSame('3', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$bon['id']])['status'], 'the bon stays at status 3, where the register close takes it');
            self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [$bon['id']]);
        } finally {
            foreach ($setAside as $id) {
                self::rows('UPDATE ordrer SET status = 3 WHERE id = $1', [$id]);
            }
        }
    }

    /**
     * Indstillinger > POS: grupper POS/2 with box12 (vis_saet) set. debitor/ordre.php then shows the order's kasse
     * fields and the register close (debitor/pos_ordre.php) takes sales orders (art D*) of its till as well as bons.
     */
    private function setVisSaet(): void
    {
        $this->assertSame([], self::rows("SELECT id FROM grupper WHERE art = 'POS'"), 'the template tenant has no POS settings');
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box12, fiscal_year) VALUES ('POS', '', 2, 'POS', 'on', $1)",
            [self::$regnaar]
        );
    }

    private function removeVisSaet(): void
    {
        self::rows("DELETE FROM grupper WHERE art = 'POS'");
    }

    public function test_hent_ordrer_leaves_an_order_paid_at_a_till_for_the_register_close(): void
    {
        // Flyt alle moves every order Hent ordrer lists; set aside status-3 orders of the other tests.
        $setAside = array_column(self::rows("SELECT id FROM ordrer WHERE status = 3"), 'id');
        foreach ($setAside as $id) {
            self::rows('UPDATE ordrer SET status = 2 WHERE id = $1', [$id]);
        }
        $this->setVisSaet();
        try {
            $invoice = $this->salesInvoice([['antal' => 2, 'pris' => 125.00]], false);
            // An ordinary sales order paid at till 1 through the order's kasse fields (debitor/ordre.php with vis_saet:
            // felt_1 payment type, felt_2 and felt_4 amounts, felt_5 the till), invoiced with "Bogfør straks" off: the
            // register close posts it as a POS sale, as bogfor_nu() would (#20171004), and creates no open item.
            $paid = $this->salesInvoice(
                [['antal' => 1, 'pris' => 125.00]],
                false,
                ['felt_1' => 'Kontant', 'felt_2' => '156.25', 'felt_3' => '', 'felt_4' => '0', 'felt_5' => '1']
            );
            $this->assertSame('DO', $paid['art']);
            $registerClose = static fn(): array => array_map('intval', array_column(self::rows(
                "SELECT ordrer.id FROM ordrer LEFT JOIN pos_betalinger ON ordrer.id = pos_betalinger.ordre_id
                 WHERE ordrer.felt_5 = '1' AND COALESCE(pos_betalinger.betalingstype, ordrer.felt_1) = 'Kontant' AND ordrer.status = '3'"
            ), 'id'));
            $this->assertSame([$paid['id']], $registerClose(), 'the register close of till 1 selects the paid order (debitor/pos_ordre.php)');

            // A supplier invoice with the same values in its order fields: the register close takes sales orders only.
            $supplier = self::$fx->creditorOrder(self::$fx->creditor(), [['item' => self::$fx->item(['stocked' => false]), 'antal' => 1, 'pris' => 50.00]], ['art' => 'KO', 'status' => 3]);
            self::rows(
                "UPDATE ordrer SET fakturadate = ordredate, fakturanr = $1, modtagelse = $2, felt_1 = 'Kontant', felt_2 = '62.5', felt_3 = '', felt_4 = '0', felt_5 = '1' WHERE id = $2",
                ['F' . $supplier['id'], $supplier['id']]
            );

            $kladdeId = self::$fx->kladde([]);
            $list = $this->hentordrerPage("kladde_id=$kladdeId");
            $this->assertStringContainsString("&ordre_id={$invoice['id']}>Flyt til kladde", $list, 'Hent ordrer lists the deferred invoice');
            $this->assertStringNotContainsString("&ordre_id={$paid['id']}>Flyt til kladde", $list, 'but not the order paid at the till');
            $this->assertStringContainsString("&ordre_id={$supplier['id']}>Flyt til kladde", $list, 'and lists the supplier invoice, which no register close takes');
            self::rows('UPDATE ordrer SET status = 2 WHERE id = $1', [$supplier['id']]); // not part of the Flyt alle below

            $this->hentordrerPage("kladde_id=$kladdeId&ordre_id={$paid['id']}");
            $this->assertSame([], $this->journalLines($kladdeId, $paid['id']), 'Flyt til kladde on it writes no journal line');
            $this->assertSame('3', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$paid['id']])['status'], 'and leaves it at status 3');

            $this->hentordrerPage("kladde_id=$kladdeId&flyt=alle");
            $this->assertCount(3, $this->journalLines($kladdeId, $invoice['id']), 'Flyt alle moves the invoice');
            $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'and marks it moved');
            $this->assertSame([], $this->journalLines($kladdeId, $paid['id']), 'Flyt alle does not move the order paid at the till: no debtor line for a paid sale');
            $this->assertSame([$paid['id']], $registerClose(), 'it stays at status 3, where the register close takes it');
            self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [$paid['id']]);
        } finally {
            $this->removeVisSaet();
            foreach ($setAside as $id) {
                self::rows('UPDATE ordrer SET status = 3 WHERE id = $1', [$id]);
            }
        }
    }

    public function test_without_vis_saet_an_order_with_numbers_in_its_order_fields_is_moved_as_before(): void
    {
        // Without vis_saet felt_1..felt_5 are the free order fields (Ordrefelt 1-5, or the debtor card's Ekstrafelt 1-5)
        // and the register close takes bons (art PO) only: Hent ordrer is the only way such a deferred invoice reaches
        // the ledger, whatever its order fields hold.
        $this->assertSame([], self::rows("SELECT id FROM grupper WHERE art = 'POS'"), 'no POS settings, so no vis_saet');
        $invoice = $this->salesInvoice(
            [['antal' => 2, 'pris' => 125.00]],
            false,
            ['felt_1' => 'Rekv. lager', 'felt_2' => '4711', 'felt_3' => '', 'felt_4' => '2', 'felt_5' => '3']
        );
        $this->assertSame('DO', $invoice['art']);
        $this->assertSame(
            [],
            self::rows("SELECT id FROM ordrer WHERE felt_5 = '3' AND (konto_id = '0' OR betalingsbet = 'Kontant') AND art = 'PO' AND status = '3'"),
            'the register close of till 3 without vis_saet (debitor/pos_ordre.php) does not take it'
        );
        $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);

        $kladdeId = self::$fx->kladde([]);
        $list = $this->hentordrerPage("kladde_id=$kladdeId");
        $this->assertStringContainsString("&ordre_id={$invoice['id']}>Flyt til kladde", $list, 'Hent ordrer lists it');

        $this->hentordrerPage("kladde_id=$kladdeId&ordre_id={$invoice['id']}");
        $this->assertSame(
            [
                ['debet', 'D', (int)$invoice['kontonr'], 312.5],
                ['kredit', 'F', $acc['revenue'], 250.0],
                ['kredit', 'F', $acc['vat'], 62.5],
            ],
            $this->journalLines($kladdeId, $invoice['id']),
            'Flyt til kladde moves it: the debtor is debited 312.50, revenue credited 250.00 and VAT 62.50'
        );
        $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'and marks it moved');
    }

    public function test_an_ore_left_by_rounding_two_accounts_goes_to_the_ore_account_as_immediate_posting_books_it(): void
    {
        $second = $this->itemOnASecondRevenueAccount();
        $oreAccount = $this->setOreAccount();
        try {
            foreach ([1 => 'invoice', -1 => 'credit note'] as $sign => $what) {
                $lines = self::twoAccountLines($second['item'], $sign);
                $invoice = $this->salesInvoice($lines, false);
                $this->assertSame([274.87 * $sign, 68.72 * $sign], [$invoice['sum'], $invoice['moms']], "the $what is for 274.87 + 68.72 VAT");
                $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);
                $first = (int)self::one('SELECT bogf_konto FROM ordrelinjer WHERE ordre_id = $1 AND posnr = 1', [$invoice['id']])['bogf_konto'];
                $this->assertNotSame($second['account'], $first, 'two revenue accounts');

                // invoice: the debtor is debited, revenue and VAT credited, the øre the rounding added is debited the øre account;
                // the credit note has every line on the other side
                [$d, $k] = $sign > 0 ? ['debet', 'kredit'] : ['kredit', 'debet'];
                $kladdeId = $this->moveToJournal($invoice['id']);
                $this->assertSame(
                    self::sorted([
                        [$d, 'D', (int)$invoice['kontonr'], 343.59],
                        [$k, 'F', $first, 269.87],
                        [$k, 'F', $second['account'], 5.01],
                        [$k, 'F', $acc['vat'], 68.72],
                        [$d, 'F', $oreAccount, 0.01],
                    ]),
                    self::sorted($this->journalLines($kladdeId, $invoice['id'])),
                    "the $what's revenue is whole øre per account and the 0.01 they add up to over the invoice is on the øre account"
                );
                $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'moved: status 4');

                $this->postJournal($kladdeId);
                $deferred = $this->ledger($invoice['id']);
                $this->assertSame(
                    self::byAccount($sign > 0
                        ? [$first => [0.0, 269.87], $second['account'] => [0.0, 5.01], $acc['vat'] => [0.0, 68.72], $acc['collective'] => [343.59, 0.0], $oreAccount => [0.01, 0.0]]
                        : [$first => [269.87, 0.0], $second['account'] => [5.01, 0.0], $acc['vat'] => [68.72, 0.0], $acc['collective'] => [0.0, 343.59], $oreAccount => [0.0, 0.01]]),
                    $deferred,
                    "the posted $what"
                );
                $twin = $this->salesInvoice($lines, true);
                $this->assertSame($this->ledger($twin['id']), $deferred, "the same $what posted at once (bogfor_nu) books the same amounts per account");
            }
        } finally {
            $this->removeOreAccount();
        }
    }

    public function test_without_an_ore_account_the_invoice_is_not_moved_and_stays_for_hent_ordrer(): void
    {
        $this->assertSame([], self::rows("SELECT id FROM grupper WHERE art = 'OreDif'"), 'no øre account set (the template tenant has none)');
        $second = $this->itemOnASecondRevenueAccount();
        $invoice = $this->salesInvoice(self::twoAccountLines($second['item'], 1), false);

        // bogfor_nu() refuses this invoice without an øre account; Flyt til kladde does the same instead of writing a
        // voucher that does not balance (D 343.59 against K 269.87 + 5.01 + 68.72), which finans/bogfor.php refuses.
        $kladdeId = self::$fx->kladde([]);
        $html = $this->hentordrerPage("kladde_id=$kladdeId&ordre_id={$invoice['id']}");
        $this->assertStringContainsString('Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff', $html, 'the page says which setting is missing, as bogfor_nu() does');
        $this->assertSame([], $this->journalLines($kladdeId, $invoice['id']), 'no journal line is written');
        $this->assertSame('3', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'the invoice stays at status 3: Hent ordrer still lists it');
        $this->assertSame([], self::rows('SELECT id FROM transaktioner WHERE ordre_id = $1', [$invoice['id']]), 'nothing is in the ledger');

        // an øre account that is a heading (kontotype H) cannot be posted to: refused the same way
        $heading = (int)self::one("SELECT kontonr FROM kontoplan WHERE regnskabsaar = $1 AND kontotype = 'H' ORDER BY kontonr LIMIT 1", [self::$regnaar])['kontonr'];
        $this->setOreAccount($heading);
        try {
            $html = $this->hentordrerPage("kladde_id=$kladdeId&ordre_id={$invoice['id']}");
            $this->assertStringContainsString('Manglende kontonummer til &oslash;redifferencer', $html, 'a heading is not an øre account');
            $this->assertSame([], $this->journalLines($kladdeId, $invoice['id']), 'no journal line is written');
            $this->assertSame('3', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'the invoice stays at status 3');
        } finally {
            $this->removeOreAccount();
        }

        // with the øre account set, the same invoice is moved, balanced; a status account (kontotype S) is accepted, as
        // bogfor_nu() posts the øre on it and finans/bogfor.php posts the journal line
        $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);
        $first = (int)self::one('SELECT bogf_konto FROM ordrelinjer WHERE ordre_id = $1 AND posnr = 1', [$invoice['id']])['bogf_konto'];
        $oreAccount = $this->setOreAccount((int)self::one(
            "SELECT kontonr FROM kontoplan WHERE regnskabsaar = $1 AND kontotype = 'S' AND (moms IS NULL OR moms = '') AND kontonr NOT IN ($2, $3) ORDER BY kontonr LIMIT 1",
            [self::$regnaar, $acc['collective'], $acc['vat']]
        )['kontonr']);
        try {
            $again = $this->moveToJournal($invoice['id']);
            $this->assertSame(
                self::sorted([
                    ['debet', 'D', (int)$invoice['kontonr'], 343.59],
                    ['kredit', 'F', $first, 269.87],
                    ['kredit', 'F', $second['account'], 5.01],
                    ['kredit', 'F', $acc['vat'], 68.72],
                    ['debet', 'F', $oreAccount, 0.01],
                ]),
                self::sorted($this->journalLines($again, $invoice['id'])),
                'debtor, two revenue lines, VAT and the øre debited the status account'
            );
            $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'and marks it moved');

            $this->postJournal($again);
            $deferred = $this->ledger($invoice['id']);
            $this->assertSame(
                self::byAccount([$first => [0.0, 269.87], $second['account'] => [0.0, 5.01], $acc['vat'] => [0.0, 68.72], $acc['collective'] => [343.59, 0.0], $oreAccount => [0.01, 0.0]]),
                $deferred,
                'the posted journal books the øre on the status account'
            );
            $twin = $this->salesInvoice(self::twoAccountLines($second['item'], 1), true);
            $this->assertSame($this->ledger($twin['id']), $deferred, 'the same invoice posted at once (bogfor_nu) books the same amounts per account');
        } finally {
            $this->removeOreAccount();
        }
    }

    /**
     * Indstillinger > Projekter: $n projects; returns their numbers (grupper PRJ kodenr) for the order lines.
     *
     * @return list<string>
     */
    private function projects(int $n): array
    {
        $first = (int)self::one("SELECT COALESCE(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'PRJ'")['kodenr'];
        $projects = [];
        for ($p = $first; $p < $first + $n; $p++) {
            self::rows("INSERT INTO grupper (beskrivelse, kode, kodenr, art) VALUES ($1, '', $2, 'PRJ')", [Fixtures::TAG . " projekt $p", $p]);
            $projects[] = (string)$p;
        }
        return $projects;
    }

    private function removeProjects(): void
    {
        self::rows("DELETE FROM grupper WHERE art = 'PRJ' AND beskrivelse LIKE $1", [Fixtures::TAG . ' projekt %']);
    }

    /**
     * One line of 1 x 20.03 less 50 % (10.015) on each project. Each project's revenue rounded to whole øre is 10.02,
     * half an øre more, so the rounding leaves n half øre over the invoice. $sign -1 is the credit note of the same lines.
     *
     * @param list<string> $projects
     * @return list<array{antal:float, pris:float, rabat:float, projekt:string}>
     */
    private static function projectLines(array $projects, int $sign): array
    {
        return array_map(static fn(string $p): array => ['antal' => $sign, 'pris' => 20.03, 'rabat' => 50, 'projekt' => $p], $projects);
    }

    /**
     * The vouchers of a journal, by order, as [order id, debit total, credit total]: the journal lines (kassekladde) or,
     * with $posted, what posting the journal left in the ledger (transaktioner). Each voucher (bilag) holds one order.
     *
     * @return list<array{0:int, 1:float, 2:float}>
     */
    private function vouchers(int $kladdeId, bool $posted = false): array
    {
        $qtxt = $posted
            ? "SELECT bilag, min(ordre_id) AS ordre_id, max(ordre_id) AS last_ordre_id, sum(COALESCE(debet, 0)) AS d, sum(COALESCE(kredit, 0)) AS k
               FROM transaktioner WHERE kladde_id = $1 GROUP BY bilag ORDER BY min(ordre_id)"
            : "SELECT bilag, min(ordre_id) AS ordre_id, max(ordre_id) AS last_ordre_id,
                      sum(CASE WHEN d_type <> '' THEN amount ELSE 0 END) AS d, sum(CASE WHEN k_type <> '' THEN amount ELSE 0 END) AS k
               FROM kassekladde WHERE kladde_id = $1 GROUP BY bilag ORDER BY min(ordre_id)";
        return array_map(function (array $r): array {
            $this->assertSame($r['ordre_id'], $r['last_ordre_id'], "voucher {$r['bilag']} holds one order");
            return [(int)$r['ordre_id'], round((float)$r['d'], 3), round((float)$r['k'], 3)];
        }, self::rows($qtxt, [$kladdeId]));
    }

    /**
     * The revenue lines of one order in the journal, as project => amount.
     *
     * @return array<string, float>
     */
    private function revenueByProject(int $kladdeId, int $orderId, int $revenue): array
    {
        $lines = [];
        foreach (self::rows(
            "SELECT projekt, amount FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 AND (kredit = $3 OR debet = $3) ORDER BY projekt",
            [$kladdeId, $orderId, $revenue]
        ) as $r) {
            $lines[(string)$r['projekt']] = round((float)$r['amount'], 3);
        }
        ksort($lines);
        return $lines;
    }

    /**
     * An invoice of one line on each of $n projects (projectLines()), moved to the journal and posted: the debtor is
     * debited $gross, each project's revenue credited 10.02 and the VAT $vat, and the $ore the rounding adds up to over
     * the invoice is debited the øre account, so the voucher balances at $total.
     */
    private function assertInvoiceOnProjectsBalances(int $n, float $sum, float $vat, float $ore, float $total): void
    {
        $projects = $this->projects($n);
        $oreAccount = $this->setOreAccount();
        try {
            $lines = self::projectLines($projects, 1);
            $invoice = $this->salesInvoice($lines, false);
            $this->assertSame([$sum, $vat], [$invoice['sum'], $invoice['moms']], "the invoice is for $n x 10.015 = $sum + $vat VAT");
            $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);
            $gross = round($sum + $vat, 2);

            $kladdeId = $this->moveToJournal($invoice['id']);
            $this->assertSame(
                self::sorted(array_merge(
                    [['debet', 'D', (int)$invoice['kontonr'], $gross]],
                    array_fill(0, $n, ['kredit', 'F', $acc['revenue'], 10.02]),
                    [['kredit', 'F', $acc['vat'], $vat], ['debet', 'F', $oreAccount, $ore]]
                )),
                self::sorted($this->journalLines($kladdeId, $invoice['id'])),
                "each project's revenue is whole øre, and the $ore that $n half øre add up to is on the øre account"
            );
            $this->assertSame(array_fill_keys($projects, 10.02), $this->revenueByProject($kladdeId, $invoice['id'], $acc['revenue']), 'one revenue line per project');
            $this->assertSame([[$invoice['id'], $total, $total]], $this->vouchers($kladdeId), "the voucher balances: debit $gross + $ore = credit 10.02 x $n + $vat");
            $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'moved: status 4');

            $this->postJournal($kladdeId);
            $this->assertSame([[$invoice['id'], $total, $total]], $this->vouchers($kladdeId, true), 'the posted voucher balances');
            $deferred = $this->ledger($invoice['id']);
            $this->assertSame(
                self::byAccount([$acc['revenue'] => [0.0, round(10.02 * $n, 2)], $acc['collective'] => [$gross, 0.0], $acc['vat'] => [0.0, $vat], $oreAccount => [$ore, 0.0]]),
                $deferred,
                'the posted journal'
            );
            // bogfor_nu() rounds each account and project to whole øre the same way. (It books VAT per account and project,
            // not the invoice's VAT, so the VAT and øre lines are not compared.)
            $twin = $this->salesInvoice($lines, true);
            $this->assertSame($deferred[$acc['revenue']], $this->ledger($twin['id'])[$acc['revenue']], 'the same invoice posted at once (bogfor_nu) books the same revenue');
        } finally {
            $this->removeOreAccount();
            $this->removeProjects();
        }
    }

    public function test_an_invoice_on_ten_projects_balances_with_the_rounding_on_the_ore_account(): void
    {
        // 10 x 10.015 = 100.15 + 25.04 VAT; 10 x 10.02 = 100.20 is 0.05 more, which the 5 øre limit on the rounded
        // lines left out: the voucher was 0.05 short, and the invoice was set to status 4
        $this->assertInvoiceOnProjectsBalances(10, 100.15, 25.04, 0.05, 125.24);
    }

    public function test_an_invoice_on_twelve_projects_balances_with_the_rounding_on_the_ore_account(): void
    {
        // 12 x 10.015 = 120.18 + 30.05 VAT; 12 x 10.02 = 120.24 is 0.06 more
        $this->assertInvoiceOnProjectsBalances(12, 120.18, 30.05, 0.06, 150.29);
    }

    public function test_an_invoice_and_its_credit_note_in_one_journal_each_balance(): void
    {
        // finans/bogfor.php checks the journal's total only: an invoice 0.05 short and its credit note 0.05 over were
        // posted together without a word.
        $projects = $this->projects(10);
        $oreAccount = $this->setOreAccount();
        try {
            $invoice = $this->salesInvoice(self::projectLines($projects, 1), false);
            $creditNote = $this->salesInvoice(self::projectLines($projects, -1), false);
            $this->assertSame(['DO', 100.15, 25.04], [$invoice['art'], $invoice['sum'], $invoice['moms']], 'the invoice is for 100.15 + 25.04 VAT');
            $this->assertSame(['DK', -100.15, -25.04], [$creditNote['art'], $creditNote['sum'], $creditNote['moms']], 'the credit note is for the same lines');
            $inv = $this->salesAccounts($invoice['id'], $invoice['konto_id']);
            $cn = $this->salesAccounts($creditNote['id'], $creditNote['konto_id']);
            $this->assertSame($inv, $cn, 'both on the same accounts');

            $kladdeId = $this->moveToJournal($invoice['id']);
            $this->moveToJournal($creditNote['id'], $kladdeId);
            $this->assertSame(
                self::sorted(array_merge(
                    [['kredit', 'D', (int)$creditNote['kontonr'], 125.19]],
                    array_fill(0, 10, ['debet', 'F', $cn['revenue'], 10.02]),
                    [['debet', 'F', $cn['vat'], 25.04], ['kredit', 'F', $oreAccount, 0.05]]
                )),
                self::sorted($this->journalLines($kladdeId, $creditNote['id'])),
                'the credit note has every line of the invoice on the other side, the øre account credited 0.05'
            );
            $this->assertSame(
                [[$invoice['id'], 125.24, 125.24], [$creditNote['id'], 125.24, 125.24]],
                $this->vouchers($kladdeId),
                'each voucher balances on its own, not only the journal as a whole'
            );
            $this->assertSame(['4', '4'], array_column(self::rows('SELECT status FROM ordrer WHERE id IN ($1, $2) ORDER BY id', [$invoice['id'], $creditNote['id']]), 'status'), 'both moved');

            $this->postJournal($kladdeId);
            $this->assertSame([[$invoice['id'], 125.24, 125.24], [$creditNote['id'], 125.24, 125.24]], $this->vouchers($kladdeId, true), 'each posted voucher balances');
            $this->assertSame(
                self::byAccount([$inv['revenue'] => [0.0, 100.2], $inv['collective'] => [125.19, 0.0], $inv['vat'] => [0.0, 25.04], $oreAccount => [0.05, 0.0]]),
                $this->ledger($invoice['id']),
                'the posted invoice'
            );
            $this->assertSame(
                self::byAccount([$cn['revenue'] => [100.2, 0.0], $cn['collective'] => [0.0, 125.19], $cn['vat'] => [25.04, 0.0], $oreAccount => [0.0, 0.05]]),
                $this->ledger($creditNote['id']),
                'the posted credit note: the same amounts on the other side'
            );
        } finally {
            $this->removeOreAccount();
            $this->removeProjects();
        }
    }

    public function test_an_invoice_whose_lines_do_not_balance_is_not_moved_and_stays_for_hent_ordrer(): void
    {
        $oreAccount = $this->setOreAccount();
        try {
            // An invoice whose total no longer matches its lines (2 x 125.00 = 250.00), set here on the header. As in
            // bogfor_nu(), a difference under 5 øre goes to the øre account ...
            $near = $this->salesInvoice([['antal' => 2, 'pris' => 125.00]], false);
            self::rows('UPDATE ordrer SET sum = 250.04 WHERE id = $1', [$near['id']]);
            $acc = $this->salesAccounts($near['id'], $near['konto_id']);
            $kladdeId = $this->moveToJournal($near['id']);
            $this->assertSame(
                [
                    ['debet', 'D', (int)$near['kontonr'], 312.54],
                    ['kredit', 'F', $acc['revenue'], 250.0],
                    ['kredit', 'F', $acc['vat'], 62.5],
                    ['kredit', 'F', $oreAccount, 0.04],
                ],
                $this->journalLines($kladdeId, $near['id']),
                '4 øre under the limit: on the øre account, the voucher balances'
            );
            $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$near['id']])['status'], 'and it is moved');

            // ... and from 5 øre bogfor_nu() refuses it; so does Flyt til kladde, instead of a voucher 0.05 short
            $off = $this->salesInvoice([['antal' => 2, 'pris' => 125.00]], false);
            self::rows('UPDATE ordrer SET sum = 250.05 WHERE id = $1', [$off['id']]);
            $ordrenr = (string)self::one('SELECT ordrenr FROM ordrer WHERE id = $1', [$off['id']])['ordrenr'];
            $html = $this->hentordrerPage("kladde_id=$kladdeId&ordre_id={$off['id']}");
            $this->assertStringContainsString(
                "uoverensstemmelse i posteringssummen, ID {$off['id']} ordre $ordrenr, d=312,550, k=312,500 - fakturaen er ikke flyttet til kladden",
                $html,
                'the page says the voucher would not balance: debit 312.55 against credit 312.50'
            );
            $this->assertSame([], $this->journalLines($kladdeId, $off['id']), 'no journal line is written');
            $this->assertSame('3', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$off['id']])['status'], 'the invoice stays at status 3: Hent ordrer still lists it');
            $this->assertSame([], self::rows('SELECT id FROM transaktioner WHERE ordre_id = $1', [$off['id']]), 'nothing of it is in the ledger');

            // once the header is right again it is fetched, and every voucher in the journal balances
            self::rows('UPDATE ordrer SET sum = 250 WHERE id = $1', [$off['id']]);
            $this->moveToJournal($off['id'], $kladdeId);
            $this->assertSame([[$near['id'], 312.54, 312.54], [$off['id'], 312.5, 312.5]], $this->vouchers($kladdeId), 'every voucher in the journal balances');
            $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$off['id']])['status'], 'and it is moved');
        } finally {
            $this->removeOreAccount();
        }
    }

    // ------------------------------------------------------------------ 20261119 tests

    /**
     * Indstillinger > Kreditorgrupper: a creditor group with the given VAT code (box1, e.g. 'Y1', or '' for none) and
     * the collective account of the template's first group; returns a creditor in it. With $otherYearVatCode the group
     * also has a row of another fiscal year with that VAT code, written first, so a lookup without the year finds it.
     *
     * @return array{id:int, kontonr:string, collective:int}
     */
    private function creditorInGroup(string $vatCode, ?string $otherYearVatCode = null): array
    {
        $collective = (int)self::one("SELECT box2 FROM grupper WHERE art = 'KG' AND kodenr = 1 AND fiscal_year = $1", [self::$regnaar])['box2'];
        $kodenr = (int)self::one("SELECT COALESCE(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'KG'")['kodenr'];
        if ($otherYearVatCode !== null) {
            self::rows(
                "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, fiscal_year) VALUES ($1, 'K', $2, 'KG', $3, $4, 'DKK', 'Dansk', $5)",
                [Fixtures::TAG . " KG '$otherYearVatCode' other year", $kodenr, $otherYearVatCode, (string)$collective, self::$regnaar + 10]
            );
        }
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, fiscal_year) VALUES ($1, 'K', $2, 'KG', $3, $4, 'DKK', 'Dansk', $5)",
            [Fixtures::TAG . " KG '$vatCode'", $kodenr, $vatCode, (string)$collective, self::$regnaar]
        );
        $creditor = self::$fx->creditor();
        self::rows('UPDATE adresser SET gruppe = $1 WHERE id = $2', [$kodenr, $creditor['id']]);
        return $creditor + ['collective' => $collective];
    }

    /**
     * A purchase of $antal x 50.00 without VAT on the supplier's invoice, as kreditor/bogfor.php leaves it with
     * immediate posting of purchases off: status 3, the invoice date and receipt number set, the line on its purchase
     * account. A negative $antal with art KK is the credit note.
     *
     * @return array{id:int, purchase:int, fakturanr:string}
     */
    private function deferredPurchase(array $creditor, float $antal, string $art): array
    {
        $item = self::$fx->item(['stocked' => false]);
        $order = self::$fx->creditorOrder($creditor, [['item' => $item, 'antal' => $antal, 'pris' => 50.00, 'momssats' => 0]], ['art' => $art, 'status' => 3, 'momssats' => 0]);
        $purchase = (int)self::one("SELECT box3 FROM grupper WHERE art = 'VG' AND kodenr = $1 AND fiscal_year = $2", [$item['gruppe'], self::$regnaar])['box3'];
        $fakturanr = 'P' . $order['id'];
        self::rows('UPDATE ordrer SET fakturadate = ordredate, fakturanr = $1, modtagelse = $2, moms = 0 WHERE id = $3', [$fakturanr, $order['id'], $order['id']]);
        self::rows("UPDATE ordrelinjer SET bogf_konto = $1, projekt = '' WHERE ordre_id = $2", [$purchase, $order['id']]);
        return ['id' => $order['id'], 'purchase' => $purchase, 'fakturanr' => $fakturanr];
    }

    /**
     * The rows Hent ordrer's list shows for one invoice, as [debit type, debit account, credit type, credit account,
     * amount], sorted.
     *
     * @return list<array{0:string, 1:string, 2:string, 3:string, 4:string}>
     */
    private static function listRows(string $html, string $fakturanr): array
    {
        $rows = [];
        foreach (preg_split('/<tr\b/i', $html) as $tr) {
            if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $tr, $m)) {
                continue;
            }
            $cells = array_map(static fn(string $c): string => trim(html_entity_decode(strip_tags($c))), $m[1]);
            if (count($cells) >= 9 && $cells[6] === $fakturanr) {
                $rows[] = [$cells[2], $cells[3], $cells[4], $cells[5], $cells[7]];
            }
        }
        sort($rows);
        return $rows;
    }

    public function test_an_eu_services_purchase_gets_the_reverse_charge_vat_immediate_posting_books(): void
    {
        // A supplier in a creditor group with VAT code Y1 (EU services, YM 1: 7720, 25 %, 7740). kreditor/bogfor.php books
        // the reverse charge for it as for E: the VAT owed (box1) credited and the purchase VAT (box3) debited 25 % of the
        // purchase. Flyt til kladde wrote a 0.00 line on the KM account of the group's number instead, and the list showed
        // no VAT.
        $this->assertEuPurchaseGetsTheReverseCharge('Y1', 7720, 7740);
    }

    public function test_an_eu_goods_purchase_gets_the_reverse_charge_vat_immediate_posting_books(): void
    {
        // E1 (EU goods, EM 1: 7700, 25 %, 7740), which Flyt til kladde already moved this way. The list showed a credit
        // note's VAT owed on the purchase VAT account, as a negative amount.
        $this->assertEuPurchaseGetsTheReverseCharge('E1', 7700, 7740);
    }

    /**
     * A purchase invoice and credit note of 2 x 50.00 from a supplier in a creditor group with the EU VAT code $code: the
     * list, the moved journal lines and the posted ledger carry the reverse charge kreditor/bogfor.php books, the VAT owed
     * ($owed, box1 of EM/YM) credited and the purchase VAT ($input, box3) debited 25.00, the other way for the credit note.
     */
    private function assertEuPurchaseGetsTheReverseCharge(string $code, int $owed, int $input): void
    {
        $group = self::one("SELECT box1, box2, box3 FROM grupper WHERE art = $1 AND kodenr = 1 AND fiscal_year = $2", [$code[0] . 'M', self::$regnaar]);
        $this->assertSame([(string)$owed, '25', (string)$input], [$group['box1'], $group['box2'], $group['box3']], "the template has {$code[0]}M 1");
        $creditor = $this->creditorInGroup($code);

        $invoice = $this->deferredPurchase($creditor, 2, 'KO');
        $credit = $this->deferredPurchase($creditor, -2, 'KK');
        $kladdeId = self::$fx->kladde([]);
        $list = $this->hentordrerPage("kladde_id=$kladdeId");
        // [debit type, debit account, credit type, credit account, amount] of each row the list shows
        $rows = static function (array $rows): array {
            sort($rows);
            return $rows;
        };
        [$kontonr, $owedNr, $inputNr] = [(string)$creditor['kontonr'], (string)$owed, (string)$input];
        $this->assertSame(
            $rows([
                ['', '', 'K', $kontonr, '100,00'],
                ['F', (string)$invoice['purchase'], '', '', '100,00'],
                ['', '', 'F', $owedNr, '25,00'],
                ['F', $inputNr, '', '', '25,00'],
            ]),
            self::listRows($list, $invoice['fakturanr']),
            "$code: the list shows the invoice with the reverse charge, $owed credited and $input debited 25.00"
        );
        $this->assertSame(
            $rows([
                ['K', $kontonr, '', '', '100,00'],
                ['', '', 'F', (string)$credit['purchase'], '100,00'],
                ['F', $owedNr, '', '', '25,00'],
                ['', '', 'F', $inputNr, '25,00'],
            ]),
            self::listRows($list, $credit['fakturanr']),
            "$code: and the credit note with every line on the other side, $owed debited and no negative amount"
        );

        foreach ([1 => $invoice, -1 => $credit] as $sign => $order) {
            $what = "$code " . ($sign > 0 ? 'invoice' : 'credit note');
            [$d, $k] = $sign > 0 ? ['debet', 'kredit'] : ['kredit', 'debet'];
            $journal = $this->moveToJournal($order['id']);
            $this->assertSame(
                self::sorted([
                    [$k, 'K', (int)$creditor['kontonr'], 100.0],
                    [$d, 'F', $order['purchase'], 100.0],
                    [$k, 'F', $owed, 25.0],
                    [$d, 'F', $input, 25.0],
                ]),
                self::sorted($this->journalLines($journal, $order['id'])),
                "the $what: supplier and purchase 100.00, the VAT owed and the purchase VAT 25.00"
            );
            $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$order['id']])['status'], "the $what is moved");

            $this->postJournal($journal);
            $this->assertSame(
                self::byAccount($sign > 0
                    ? [$order['purchase'] => [100.0, 0.0], $creditor['collective'] => [0.0, 100.0], $owed => [0.0, 25.0], $input => [25.0, 0.0]]
                    : [$order['purchase'] => [0.0, 100.0], $creditor['collective'] => [100.0, 0.0], $owed => [25.0, 0.0], $input => [0.0, 25.0]]),
                $this->ledger($order['id']),
                "the posted $what has the amounts kreditor/bogfor.php books"
            );
        }
    }

    /**
     * Indstillinger > Debitorgrupper: a customer group without a VAT code (box1 empty, allowed for customers who pay no
     * VAT) on the collective account of the template's first group; returns its number.
     */
    private function debtorGroupWithoutVatCode(): int
    {
        $collective = (string)self::one("SELECT box2 FROM grupper WHERE art = 'DG' AND kodenr = 1 AND fiscal_year = $1", [self::$regnaar])['box2'];
        $kodenr = (int)self::one("SELECT COALESCE(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'DG'")['kodenr'];
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, fiscal_year) VALUES ($1, 'D', $2, 'DG', '', $3, 'DKK', 'Dansk', $4)",
            [Fixtures::TAG . ' DG uden moms', $kodenr, $collective, self::$regnaar]
        );
        return $kodenr;
    }

    /**
     * A sales invoice of 2 x 125.00 without VAT for a customer in $group, invoiced through ordrefunc's bogfor(): with
     * "Bogfør straks" off it is left at status 3, with it on bogfor_nu() posts it at once.
     *
     * @return array{id:int, kontonr:string, ordrenr:string}
     */
    private function vatFreeInvoice(int $group, bool $postRightAway): array
    {
        $debtor = self::$fx->debtor();
        self::rows('UPDATE adresser SET gruppe = $1 WHERE id = $2', [$group, $debtor['id']]);
        $item = self::$fx->item(['stocked' => false]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 2, 'pris' => 125.00, 'momssats' => 0]], ['momssats' => 0]);
        self::rows("UPDATE ordrelinjer SET projekt = '', momsfri = 'on' WHERE ordre_id = $1", [$order['id']]);
        self::rows("UPDATE grupper SET box5 = $1 WHERE art = 'DIV' AND kodenr = 3", [$postRightAway ? 'on' : '']);
        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['bogfor'] ?? null, 'the order is invoiced');
        $row = self::one('SELECT status, sum, moms, ordrenr FROM ordrer WHERE id = $1', [$order['id']]);
        $this->assertSame([$postRightAway ? '4' : '3', 250.0, 0.0], [(string)$row['status'], (float)$row['sum'], (float)$row['moms']], 'invoiced for 250.00 without VAT');
        return ['id' => $order['id'], 'kontonr' => $debtor['kontonr'], 'ordrenr' => (string)$row['ordrenr']];
    }

    public function test_a_customer_group_without_a_vat_code_is_moved_as_immediate_posting_books_it(): void
    {
        // flytordre() looked up the VAT group with kodenr = '' for such a customer: PostgreSQL refuses that, the move
        // ended in "Uforudset hændelse" and the invoice stayed at status 3 for good. The list ran the same query.
        $group = $this->debtorGroupWithoutVatCode();
        $invoice = $this->vatFreeInvoice($group, false);
        $revenue = (int)self::one('SELECT DISTINCT bogf_konto FROM ordrelinjer WHERE ordre_id = $1', [$invoice['id']])['bogf_konto'];
        $collective = (int)self::one("SELECT box2 FROM grupper WHERE art = 'DG' AND kodenr = $1", [$group])['box2'];

        $kladdeId = self::$fx->kladde([]);
        $list = $this->hentordrerPage("kladde_id=$kladdeId");
        $this->assertStringContainsString("&ordre_id={$invoice['id']}>Flyt til kladde", $list, 'the list shows the invoice, without a database error');

        $this->hentordrerPage("kladde_id=$kladdeId&ordre_id={$invoice['id']}");
        $this->assertSame(
            [
                ['debet', 'D', (int)$invoice['kontonr'], 250.0],
                ['kredit', 'F', $revenue, 250.0],
            ],
            $this->journalLines($kladdeId, $invoice['id']),
            'the customer is debited and revenue credited 250.00, with no VAT line'
        );
        $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'and the invoice is moved');

        $this->postJournal($kladdeId);
        $deferred = $this->ledger($invoice['id']);
        $this->assertSame(self::byAccount([$collective => [250.0, 0.0], $revenue => [0.0, 250.0]]), $deferred, 'the posted journal');
        $twin = $this->vatFreeInvoice($group, true);
        $this->assertSame($this->ledger($twin['id']), $deferred, 'the same invoice posted at once (bogfor_nu) books the same amounts per account');

        // An invoice with VAT for such a customer: bogfor_nu() refuses it ("ikke tilknyttet en momsgruppe"), and so does
        // the move, instead of a voucher without its VAT line.
        $withVat = $this->vatFreeInvoice($group, false);
        self::rows('UPDATE ordrer SET moms = 62.5 WHERE id = $1', [$withVat['id']]);
        $html = $this->hentordrerPage("kladde_id=$kladdeId&ordre_id={$withVat['id']}");
        $this->assertStringContainsString("Debitorgruppe $group er ikke tilknyttet en momsgruppe med momskonto - fakturaen er ikke flyttet til kladden", $html, 'the page says why');
        $this->assertSame([], $this->journalLines($kladdeId, $withVat['id']), 'no journal line is written');
        $this->assertSame('3', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$withVat['id']])['status'], 'the invoice stays at status 3');
        self::rows('UPDATE ordrer SET status = 2 WHERE id = $1', [$withVat['id']]); // not for the Flyt alle tests
    }

    public function test_a_supplier_group_without_a_vat_code_is_moved_without_a_vat_line(): void
    {
        // The same kodenr = '' on the supplier side (the KM lookup). kreditor/bogfor.php books such a purchase without VAT.
        $creditor = $this->creditorInGroup('');
        $purchase = $this->deferredPurchase($creditor, 2, 'KO');
        $kladdeId = self::$fx->kladde([]);
        $list = $this->hentordrerPage("kladde_id=$kladdeId");
        $this->assertStringContainsString("&ordre_id={$purchase['id']}>Flyt til kladde", $list, 'the list shows the purchase, without a database error');

        $this->hentordrerPage("kladde_id=$kladdeId&ordre_id={$purchase['id']}");
        $this->assertSame(
            [
                ['kredit', 'K', (int)$creditor['kontonr'], 100.0],
                ['debet', 'F', $purchase['purchase'], 100.0],
            ],
            $this->journalLines($kladdeId, $purchase['id']),
            'the supplier is credited and the purchase debited 100.00, with no VAT line'
        );
        $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$purchase['id']])['status'], 'and the purchase is moved');
        $this->postJournal($kladdeId);
        $this->assertSame(
            self::byAccount([$purchase['purchase'] => [100.0, 0.0], $creditor['collective'] => [0.0, 100.0]]),
            $this->ledger($purchase['id']),
            'the posted journal books the purchase without VAT, as kreditor/bogfor.php does'
        );
    }

    /**
     * A ledger that has set up group setting $art $kodenr for several fiscal years: the template's row of this year
     * becomes the row of another year with $otherYear set, and a copy of it with $thisYear set is this year's row. A
     * lookup without the year finds the other year's row, written first. Returns a function that puts the template's
     * row back.
     *
     * @param array<string, string> $otherYear Columns (box1 ...) of the other year's row.
     * @param array<string, string> $thisYear Columns of this year's row.
     */
    private function groupSetUpForTwoYears(string $art, int $kodenr, array $otherYear, array $thisYear): callable
    {
        $template = self::one('SELECT * FROM grupper WHERE art = $1 AND kodenr = $2 AND fiscal_year = $3', [$art, $kodenr, self::$regnaar]);
        $this->assertNotNull($template, "the template has $art $kodenr");
        $columns = ['beskrivelse', 'kode', 'kodenr', 'art', 'box1', 'box2', 'box3', 'box4', 'box5', 'box6', 'box7', 'box8', 'box9'];
        $set = ['fiscal_year = $1'];
        $params = [self::$regnaar + 10];
        foreach ($otherYear as $column => $value) {
            $params[] = $value;
            $set[] = "$column = $" . count($params);
        }
        $params[] = $template['id'];
        self::rows('UPDATE grupper SET ' . implode(', ', $set) . ' WHERE id = $' . count($params), $params);
        $values = array_map(static fn(string $c) => $thisYear[$c] ?? $template[$c], $columns);
        $values[] = self::$regnaar;
        $placeholders = implode(', ', array_map(static fn(int $i): string => '$' . $i, range(1, count($values))));
        $copy = self::one('INSERT INTO grupper (' . implode(', ', $columns) . ", fiscal_year) VALUES ($placeholders) RETURNING id", $values);
        return static function () use ($template, $copy, $columns): void {
            self::rows('DELETE FROM grupper WHERE id = $1', [$copy['id']]);
            $set = [];
            $params = [];
            foreach (array_merge($columns, ['fiscal_year']) as $column) {
                $params[] = $template[$column];
                $set[] = "$column = $" . count($params);
            }
            $params[] = $template['id'];
            self::rows('UPDATE grupper SET ' . implode(', ', $set) . ' WHERE id = $' . count($params), $params);
        };
    }

    public function test_a_purchase_reads_the_groups_of_the_fiscal_year_it_is_posted_in(): void
    {
        // The supplier's group is E1 (EU goods) in another fiscal year and Y1 (EU services) in this one, and YM 1 is 25 % in
        // another year and 20 % in this one; each other year's row is read first. kreditor/bogfor.php reads both for the
        // year it posts in: 20.00 reverse-charge VAT on a 100.00 purchase, on the YM accounts. Flyt til kladde took the
        // first row of each, so it wrote the EU goods VAT, and with Y the 25 % of the other year (25.00).
        $restore = $this->groupSetUpForTwoYears('YM', 1, ['box2' => '25'], ['box2' => '20']);
        $purchase = null;
        try {
            [$owed, $input] = [7720, 7740];
            $group = self::one("SELECT box1, box3 FROM grupper WHERE art = 'YM' AND kodenr = 1 AND fiscal_year = $1", [self::$regnaar]);
            $this->assertSame([(string)$owed, (string)$input], [$group['box1'], $group['box3']], 'this year\'s YM 1 has the template\'s accounts');
            $creditor = $this->creditorInGroup('Y1', 'E1');
            $purchase = $this->deferredPurchase($creditor, 2, 'KO');
            $kladdeId = self::$fx->kladde([]);

            $list = [
                ['', '', 'K', (string)$creditor['kontonr'], '100,00'],
                ['F', (string)$purchase['purchase'], '', '', '100,00'],
                ['', '', 'F', (string)$owed, '20,00'],
                ['F', (string)$input, '', '', '20,00'],
            ];
            sort($list);
            $this->assertSame(
                $list,
                self::listRows($this->hentordrerPage("kladde_id=$kladdeId"), $purchase['fakturanr']),
                'the list shows 20 % reverse-charge VAT on the YM 1 accounts'
            );
            $this->moveToJournal($purchase['id'], $kladdeId);
            $this->assertSame(
                self::sorted([
                    ['kredit', 'K', (int)$creditor['kontonr'], 100.0],
                    ['debet', 'F', $purchase['purchase'], 100.0],
                    ['kredit', 'F', $owed, 20.0],
                    ['debet', 'F', $input, 20.0],
                ]),
                self::sorted($this->journalLines($kladdeId, $purchase['id'])),
                'the journal has the VAT owed and the purchase VAT of this year\'s Y1 and YM 1: 20.00, not 25.00'
            );
            $this->postJournal($kladdeId);
            $this->assertSame(
                self::byAccount([$purchase['purchase'] => [100.0, 0.0], $creditor['collective'] => [0.0, 100.0], $owed => [0.0, 20.0], $input => [20.0, 0.0]]),
                $this->ledger($purchase['id']),
                'the posted journal books 20.00 VAT owed and purchase VAT, as kreditor/bogfor.php books it for this year'
            );
        } finally {
            $restore();
            if ($purchase) {
                self::rows('UPDATE ordrer SET status = 2 WHERE id = $1 AND status = 3', [$purchase['id']]); // not for the Flyt alle tests
            }
        }
    }

    public function test_a_sales_invoice_reads_the_groups_of_the_fiscal_year_it_is_posted_in(): void
    {
        // The customer's group has no VAT code in another fiscal year and the template's in this one, and the VAT group
        // (SM) has another VAT account this year than in the other year; each other year's row is read first. bogfor_nu()
        // and the journal's posting read the groups of the year they post in. Flyt til kladde took the first row of each:
        // without a VAT code the invoice was refused ("ikke tilknyttet en momsgruppe"), and with the customer group fixed
        // the VAT went to the other year's VAT account.
        $invoice = $this->salesInvoice([['antal' => 2, 'pris' => 125.00]], false);
        $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);
        $vatCode = trim((string)self::one("SELECT box1 FROM grupper WHERE art = 'DG' AND kodenr = 1 AND fiscal_year = $1", [self::$regnaar])['box1']);
        $vatAccount = self::$fx->plainAccounts(3)[2];
        $this->assertNotSame($acc['vat'], $vatAccount, 'this year\'s VAT account is another account than the template\'s');
        $kodenr = (int)self::one("SELECT COALESCE(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'DG'")['kodenr'];
        foreach ([[self::$regnaar + 10, ''], [self::$regnaar, $vatCode]] as [$year, $code]) {
            self::rows(
                "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, fiscal_year) VALUES ($1, 'D', $2, 'DG', $3, $4, 'DKK', 'Dansk', $5)",
                [Fixtures::TAG . " DG '$code' $year", $kodenr, $code, (string)$acc['collective'], $year]
            );
        }
        self::rows('UPDATE adresser SET gruppe = $1 WHERE id = $2', [$kodenr, $invoice['konto_id']]);
        $restore = $this->groupSetUpForTwoYears('SM', (int)substr($vatCode, 1), [], ['box1' => (string)$vatAccount]);
        try {
            $kladdeId = self::$fx->kladde([]);
            $this->assertContains(
                ['', '', 'F', (string)$vatAccount, '62,50'],
                self::listRows($this->hentordrerPage("kladde_id=$kladdeId"), $invoice['fakturanr']),
                'the list shows the VAT on this year\'s VAT account'
            );
            $this->moveToJournal($invoice['id'], $kladdeId);
            $this->assertSame(
                [
                    ['debet', 'D', (int)$invoice['kontonr'], 312.5],
                    ['kredit', 'F', $acc['revenue'], 250.0],
                    ['kredit', 'F', $vatAccount, 62.5],
                ],
                $this->journalLines($kladdeId, $invoice['id']),
                'the invoice is moved with its VAT on this year\'s VAT account'
            );
            $this->postJournal($kladdeId);
            $this->assertSame(
                self::byAccount([$acc['collective'] => [312.5, 0.0], $acc['revenue'] => [0.0, 250.0], $vatAccount => [0.0, 62.5]]),
                $this->ledger($invoice['id']),
                'the posted journal'
            );
        } finally {
            $restore();
            self::rows("DELETE FROM grupper WHERE art = 'DG' AND kodenr = $1", [$kodenr]);
            self::rows('UPDATE ordrer SET status = 2 WHERE id = $1 AND status = 3', [$invoice['id']]); // not for the Flyt alle tests
        }
    }

    /**
     * Indstillinger > Valuta: EUR at 746 from 1 January, with $diffAccount as its difference account (grupper VK box3).
     *
     * @return int The currency's number (grupper VK kodenr, kassekladde.valuta).
     */
    private function euro(int $diffAccount): int
    {
        $kodenr = (int)self::one("SELECT COALESCE(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'VK'")['kodenr'];
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box3, fiscal_year) VALUES ('Euro', 'V', $1, 'VK', 'EUR', $2, $3)",
            [$kodenr, (string)$diffAccount, self::$regnaar]
        );
        self::rows('INSERT INTO valuta (gruppe, valdate, kurs) VALUES ($1, $2, 746)', [$kodenr, date('Y') . '-01-01']);
        return $kodenr;
    }

    private function removeEuro(int $kodenr): void
    {
        self::rows('DELETE FROM valuta WHERE gruppe = $1', [$kodenr]);
        self::rows("DELETE FROM grupper WHERE art = 'VK' AND kodenr = $1", [$kodenr]);
    }

    public function test_a_foreign_currency_invoice_reaches_the_ledger_as_immediate_posting_books_it(): void
    {
        // 1 x 2.01 EUR less 50 % = 1.005 EUR; the invoice is 1.01 + 0.25 VAT = 1.26 EUR, at 746. bogfor_nu() converts the
        // line before it rounds it: revenue 1.005 x 7.46 = 7.497, 7.50 DKK; VAT 1.87; the customer 1.26 x 7.46 = 9.40; the
        // 0.03 left goes to the currency's difference account. The move rounded the line to 1.01 EUR first, so the posted
        // journal had revenue 7.53 and no difference.
        [$oreAccount, $diffAccount] = self::$fx->plainAccounts(2);
        $this->setOreAccount($oreAccount);
        $euro = $this->euro($diffAccount);
        try {
            foreach ([1 => 'invoice', -1 => 'credit note'] as $sign => $what) {
                $lines = [['antal' => $sign, 'pris' => 2.01, 'rabat' => 50]];
                $invoice = $this->salesInvoice($lines, false, ['valuta' => 'EUR', 'valutakurs' => '746']);
                $this->assertSame([1.01 * $sign, 0.25 * $sign], [$invoice['sum'], $invoice['moms']], "the $what is for 1.01 + 0.25 VAT EUR");
                $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);

                [$d, $k] = $sign > 0 ? ['debet', 'kredit'] : ['kredit', 'debet'];
                $kladdeId = $this->moveToJournal($invoice['id']);
                $this->assertSame(
                    self::sorted([
                        [$d, 'D', (int)$invoice['kontonr'], 1.26],
                        [$k, 'F', $acc['revenue'], 7.5],
                        [$k, 'F', $acc['vat'], 1.87],
                        [$k, 'F', $diffAccount, 0.03],
                    ]),
                    self::sorted($this->journalLines($kladdeId, $invoice['id'])),
                    "the $what: the customer 1.26 EUR, revenue 7.50, VAT 1.87 and the difference 0.03 in DKK"
                );
                $this->assertSame(
                    [['valuta' => (string)$euro, 'n' => '1'], ['valuta' => '0', 'n' => '3']],
                    self::rows('SELECT valuta, count(*) AS n FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 GROUP BY valuta ORDER BY valuta DESC', [$kladdeId, $invoice['id']]),
                    'the customer line is in EUR (its open item), the others in DKK'
                );

                $this->postJournal($kladdeId);
                $deferred = $this->ledger($invoice['id']);
                $this->assertSame(
                    self::byAccount($sign > 0
                        ? [$acc['revenue'] => [0.0, 7.5], $acc['collective'] => [9.4, 0.0], $acc['vat'] => [0.0, 1.87], $diffAccount => [0.0, 0.03]]
                        : [$acc['revenue'] => [7.5, 0.0], $acc['collective'] => [0.0, 9.4], $acc['vat'] => [1.87, 0.0], $diffAccount => [0.03, 0.0]]),
                    $deferred,
                    "the posted $what: revenue 7.50, not 7.53, and the 0.03 on the currency's difference account, none on the øre account"
                );
                $twin = $this->salesInvoice($lines, true, ['valuta' => 'EUR', 'valutakurs' => '746']);
                $this->assertSame($this->ledger($twin['id']), $deferred, "the same $what posted at once (bogfor_nu) books the same amounts per account");
            }
        } finally {
            $this->removeOreAccount();
            $this->removeEuro($euro);
        }
    }

    /**
     * Simulér or Bogfør of a journal the posting refuses (finans/bogfor.php); returns the page's HTML (its alert). The
     * journal is not posted.
     */
    private function refusedPosting(string $mode, int $kladdeId): string
    {
        $htmlFile = (string)tempnam(sys_get_temp_dir(), 'chartest-bogfor-');
        try {
            self::runChild('run_bogfor_page.php', [$mode, $kladdeId, CharacterizationEnv::sessionId(), '', $htmlFile]);
            $html = (string)file_get_contents($htmlFile);
        } finally {
            @unlink($htmlFile);
        }
        $this->assertNotSame('V', (string)self::one('SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId])['bogfort'], "$mode: journal $kladdeId is not posted");
        $this->assertSame([], self::rows('SELECT id FROM transaktioner WHERE kladde_id = $1', [$kladdeId]), "$mode: nothing is in the ledger");
        return $html;
    }

    /**
     * Indstillinger > Valuta: the rate of the currency's only date (euro()) is changed, as when it is corrected.
     */
    private function correctRate(int $euro, string $kurs): void
    {
        self::rows('UPDATE valuta SET kurs = $1 WHERE gruppe = $2', [$kurs, $euro]);
    }

    public function test_a_moved_currency_invoice_whose_rate_is_corrected_is_refused_saying_what_happened_and_what_to_do(): void
    {
        // 2 x 100.00 EUR + 25 % VAT at 746: Flyt til kladde writes the customer 250.00 EUR and revenue 1,492.00 and VAT
        // 373.00 in DKK. The rate of the invoice date is then corrected to 746.01, and posting converts the customer line
        // to 1,865.03 against 1,865.00. Simulér and Bogfør refused the whole journal with "Manglende konto til
        // valutadiffencer! (bilag: N)", although EUR has its difference account, and nothing said what to do.
        [$oreAccount, $diffAccount] = self::$fx->plainAccounts(2);
        $this->setOreAccount($oreAccount);
        $euro = $this->euro($diffAccount);
        $invoice = null;
        try {
            $invoice = $this->salesInvoice([['antal' => 2, 'pris' => 100.00]], false, ['valuta' => 'EUR', 'valutakurs' => '746']);
            $this->assertSame([200.0, 50.0], [$invoice['sum'], $invoice['moms']], 'the invoice is for 200.00 + 50.00 VAT EUR');
            $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);
            $kladdeId = $this->moveToJournal($invoice['id']);
            $this->assertSame(
                [
                    ['debet', 'D', (int)$invoice['kontonr'], 250.0],
                    ['kredit', 'F', $acc['revenue'], 1492.0],
                    ['kredit', 'F', $acc['vat'], 373.0],
                ],
                $this->journalLines($kladdeId, $invoice['id']),
                'moved at 746: the customer 250.00 EUR, revenue 1,492.00 and VAT 373.00 DKK'
            );
            $voucher = self::one('SELECT bilag, transdate FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 LIMIT 1', [$kladdeId, $invoice['id']]);
            $this->correctRate($euro, '746.01');

            $expected = 'Kursen for EUR den ' . date('d-m-Y', strtotime($voucher['transdate'])) . ' er ændret fra 746,00 til 746,01, efter at faktura '
                . $invoice['fakturanr'] . ' blev flyttet til kladden fra Hent ordrer (bilag ' . $voucher['bilag'] . '), så bilaget ikke længere balancerer i DKK'
                . ' (difference 0,03). Slet bilagets linjer i kladden, så kommer fakturaen tilbage i Hent ordrer, og flyt den til kladden igen. Ingen posteringer er gemt.';
            $this->assertStringContainsString(json_encode($expected), $this->refusedPosting('simuler', $kladdeId), 'Simulér names the voucher and the rates and says what to do');
            $this->assertStringContainsString($expected, $this->refusedPosting('bogfor', $kladdeId), 'so does Bogfør');
            $this->assertCount(3, $this->journalLines($kladdeId, $invoice['id']), 'the voucher\'s lines are kept');

            // As the message says: deleting the voucher's lines puts the invoice back in Hent ordrer, and moved again it is
            // written and posted at 746.01: 200.00 x 7.4601 = 1,492.02, 50.00 x 7.4601 = 373.01 and 250.00 x 7.4601 = 1,865.03.
            self::rows("UPDATE kladdeliste SET bogfort = '-' WHERE id = $1", [$kladdeId]); // an open journal, as kassekladde.php creates it
            $lineIds = array_map('intval', array_column(self::rows('SELECT id FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 ORDER BY id', [$kladdeId, $invoice['id']]), 'id'));
            $this->deleteJournalLines($kladdeId, $lineIds);
            $this->assertSame('3', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'the invoice is back in Hent ordrer');
            $again = $this->moveToJournal($invoice['id']);
            $this->postJournal($again);
            $this->assertSame(
                self::byAccount([$acc['revenue'] => [0.0, 1492.02], $acc['collective'] => [1865.03, 0.0], $acc['vat'] => [0.0, 373.01]]),
                $this->ledger($invoice['id']),
                'moved again, the invoice posts at the corrected rate'
            );
        } finally {
            if ($invoice) {
                self::rows('UPDATE ordrer SET status = 2 WHERE id = $1 AND status = 3', [$invoice['id']]); // not for the Flyt alle tests
            }
            $this->removeOreAccount();
            $this->removeEuro($euro);
        }
    }

    public function test_a_moved_currency_invoice_that_no_longer_balances_is_named_when_the_journal_holds_other_vouchers(): void
    {
        // The rate is corrected between invoicing (746) and Flyt til kladde (746.01), which then writes the invoice at
        // 746.01, and once more before Bogfør (746.02): the customer 250.00 EUR is 1,865.05 against 1,865.03. Another
        // voucher is read first, so bogfor.php did not stop at the currency check but at the stored balance and refused
        // the journal as unbalanced. The invoice was not moved at its own rate, so the message cannot name the rate of the
        // move; it names the rate now and says that the rate or the voucher's lines changed.
        [$oreAccount, $diffAccount, $debit, $credit] = self::$fx->plainAccounts(4);
        $this->setOreAccount($oreAccount);
        $euro = $this->euro($diffAccount);
        $invoice = null;
        try {
            $invoice = $this->salesInvoice([['antal' => 2, 'pris' => 100.00]], false, ['valuta' => 'EUR', 'valutakurs' => '746']);
            $this->correctRate($euro, '746.01');
            $kladdeId = self::$fx->kladde([
                ['bilag' => 1, 'debet' => $debit, 'amount' => 100.00, 'tekst' => Fixtures::TAG . ' other voucher'],
                ['bilag' => 1, 'kredit' => $credit, 'amount' => 100.00, 'tekst' => Fixtures::TAG . ' other voucher'],
            ]);
            $this->moveToJournal($invoice['id'], $kladdeId);
            self::rows('UPDATE kassekladde SET bilag = 900 WHERE kladde_id = $1 AND ordre_id = $2', [$kladdeId, $invoice['id']]); // after the other voucher
            $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);
            $this->assertSame(
                [
                    ['debet', 'D', (int)$invoice['kontonr'], 250.0],
                    ['kredit', 'F', $acc['revenue'], 1492.02],
                    ['kredit', 'F', $acc['vat'], 373.01],
                ],
                $this->journalLines($kladdeId, $invoice['id']),
                'moved at 746.01: the customer 250.00 EUR, revenue 1,492.02 and VAT 373.01 DKK'
            );
            $transdate = (string)self::one('SELECT fakturadate FROM ordrer WHERE id = $1', [$invoice['id']])['fakturadate'];
            $this->correctRate($euro, '746.02');

            $expected = 'Bilag 900 med faktura ' . $invoice['fakturanr'] . ' er flyttet til kladden fra Hent ordrer og balancerer ikke længere i DKK med kursen 746,02 for EUR den '
                . date('d-m-Y', strtotime($transdate)) . ' (difference 0,02): kursen for den dato eller bilagets linjer er ændret, efter at fakturaen blev flyttet.'
                . ' Slet bilagets linjer i kladden, så kommer fakturaen tilbage i Hent ordrer, og flyt den til kladden igen. Ingen posteringer er gemt.';
            $this->assertStringContainsString($expected, $this->refusedPosting('bogfor', $kladdeId), 'Bogfør names the voucher and the rate now and says what to do, instead of an unbalanced journal');
        } finally {
            if ($invoice) {
                self::rows('UPDATE ordrer SET status = 2 WHERE id = $1 AND status = 3', [$invoice['id']]); // not for the Flyt alle tests
            }
            $this->removeOreAccount();
            $this->removeEuro($euro);
        }
    }

    /**
     * Kassekladde > Simulér or Bogfør: the preview (finans/bogfor.php without a POST), where the user sees the journal
     * before the button that posts it; returns its HTML. The preview posts nothing.
     */
    private function previewPage(string $funktion, int $kladdeId): string
    {
        $htmlFile = (string)tempnam(sys_get_temp_dir(), 'chartest-bogfor-preview-');
        try {
            $res = self::runChild('run_bogfor_page.php', ["preview-$funktion", $kladdeId, CharacterizationEnv::sessionId(), '', $htmlFile]);
            $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the $funktion preview ran to its end.\nstderr: {$res['stderr']}");
            $html = (string)file_get_contents($htmlFile);
        } finally {
            @unlink($htmlFile);
        }
        $this->assertSame([], self::rows('SELECT id FROM transaktioner WHERE kladde_id = $1', [$kladdeId]), "$funktion preview: nothing is in the ledger");
        return $html;
    }

    public function test_the_simuler_and_bogfor_previews_of_a_moved_currency_invoice_whose_rate_is_corrected_say_what_happened_and_what_to_do(): void
    {
        // Kassekladde's Simulér and Bogfør buttons open the preview, and only its button posts. A voucher that Flyt til
        // kladde wrote at 746 (the customer 250.00 EUR, revenue 1,492.00 and VAT 373.00 DKK) is 0.03 off once the rate is
        // corrected to 746.01. A journal of such vouchers has one currency line per voucher, so the preview never takes the
        // difference as currency rounding: it listed the vouchers under "Der er differencer på følgende bilag" and left
        // out the button, so the user never reached the posting's message and was not told why or what to do.
        [$oreAccount, $diffAccount] = self::$fx->plainAccounts(2);
        $this->setOreAccount($oreAccount);
        $euro = $this->euro($diffAccount);
        $ids = [];
        try {
            $one = $this->salesInvoice([['antal' => 2, 'pris' => 100.00]], false, ['valuta' => 'EUR', 'valutakurs' => '746']);
            $ids[] = $one['id'];
            $alone = $this->moveToJournal($one['id']);
            $two = [];
            $both = self::$fx->kladde([]);
            for ($i = 0; $i < 2; $i++) {
                $two[$i] = $this->salesInvoice([['antal' => 2, 'pris' => 100.00]], false, ['valuta' => 'EUR', 'valutakurs' => '746']);
                $ids[] = $two[$i]['id'];
                $this->moveToJournal($two[$i]['id'], $both);
            }
            $this->correctRate($euro, '746.01');
            $rateChanged = function (int $kladdeId, array $invoice): string {
                $voucher = self::one('SELECT bilag, transdate FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 LIMIT 1', [$kladdeId, $invoice['id']]);
                $this->assertNotNull($voucher, "invoice {$invoice['fakturanr']} is in journal $kladdeId");
                return 'Kursen for EUR den ' . date('d-m-Y', strtotime($voucher['transdate'])) . ' er ændret fra 746,00 til 746,01, efter at faktura '
                    . $invoice['fakturanr'] . ' blev flyttet til kladden fra Hent ordrer (bilag ' . $voucher['bilag'] . '), så bilaget ikke længere balancerer i DKK'
                    . ' (difference 0,03).';
            };

            $expected = $rateChanged($alone, $one) . ' Slet bilagets linjer i kladden, så kommer fakturaen tilbage i Hent ordrer, og flyt den til kladden igen.';
            foreach (['simuler', 'bogfor'] as $funktion) {
                $html = $this->previewPage($funktion, $alone);
                $this->assertStringContainsString('Der er differencer p', $html, "$funktion preview: the voucher is listed with its difference, as before");
                $this->assertDoesNotMatchRegularExpression('/name="?(simuler|bogfor)"?/', $html, "$funktion preview: there is no button that posts the journal, as before");
                $this->assertStringContainsString($expected, $html, "$funktion preview: names the voucher and the rates and says what to do");
                $this->assertStringNotContainsString('Ingen posteringer er gemt', $html, "$funktion preview: nothing was posted or refused yet");
            }

            $expected = $rateChanged($both, $two[0]) . ' ' . $rateChanged($both, $two[1])
                . ' Slet bilagenes linjer i kladden, så kommer fakturaerne tilbage i Hent ordrer, og flyt dem til kladden igen.';
            $this->assertStringContainsString($expected, $this->previewPage('bogfor', $both), 'two moved invoices: the preview names each and says what to do');
        } finally {
            foreach ($ids as $id) {
                self::rows('UPDATE ordrer SET status = 2 WHERE id = $1 AND status = 3', [$id]); // not for the Flyt alle tests
            }
            $this->removeOreAccount();
            $this->removeEuro($euro);
        }
    }

    public function test_a_moved_currency_invoice_and_its_credit_note_whose_rate_is_corrected_are_refused_although_the_journal_balances(): void
    {
        // p1-journal-J3h: 2 x 100.00 EUR + 25 % VAT and its credit note, both moved into one journal at 746: the customer
        // 250.00 EUR against revenue 1,492.00 and VAT 373.00 DKK, on the other sides for the credit note. Corrected to
        // 746.01, the customer line is 1,865.03 against 1,865.00, so the invoice's voucher is 0.03 off one way and the
        // credit note's 0.03 the other, and the journal's total is 0. The preview named both vouchers but showed Bogfør,
        // and Bogfør posted each voucher 0.03 out of balance (transaktioner per order +0.030 and -0.030).
        [$oreAccount, $diffAccount, $correction] = self::$fx->plainAccounts(3);
        $this->setOreAccount($oreAccount);
        $euro = $this->euro($diffAccount);
        $ids = [];
        try {
            $invoice = $this->salesInvoice([['antal' => 2, 'pris' => 100.00]], false, ['valuta' => 'EUR', 'valutakurs' => '746']);
            $ids[] = $invoice['id'];
            $creditNote = $this->salesInvoice([['antal' => -2, 'pris' => 100.00]], false, ['valuta' => 'EUR', 'valutakurs' => '746']);
            $ids[] = $creditNote['id'];
            $this->assertSame([[200.0, 50.0], [-200.0, -50.0]], [[$invoice['sum'], $invoice['moms']], [$creditNote['sum'], $creditNote['moms']]], 'an invoice and its credit note of 200.00 + 50.00 VAT EUR');
            $kladdeId = self::$fx->kladde([]);
            $this->moveToJournal($invoice['id'], $kladdeId);
            $this->moveToJournal($creditNote['id'], $kladdeId);
            $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);
            $this->assertSame(
                self::sorted([['kredit', 'D', (int)$creditNote['kontonr'], 250.0], ['debet', 'F', $acc['revenue'], 1492.0], ['debet', 'F', $acc['vat'], 373.0]]),
                self::sorted($this->journalLines($kladdeId, $creditNote['id'])),
                'the credit note is moved at 746 with every line on the other side'
            );
            $voucher = fn(array $order): array => self::one('SELECT bilag, transdate FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 LIMIT 1', [$kladdeId, $order['id']]);
            [$invoiceVoucher, $creditVoucher] = [$voucher($invoice), $voucher($creditNote)];
            $this->assertNotSame($invoiceVoucher['bilag'], $creditVoucher['bilag'], 'each is a voucher of its own');
            $this->correctRate($euro, '746.01');

            $named = static fn(array $v, array $order): string => 'Kursen for EUR den ' . date('d-m-Y', strtotime($v['transdate']))
                . ' er ændret fra 746,00 til 746,01, efter at faktura ' . $order['fakturanr'] . ' blev flyttet til kladden fra Hent ordrer (bilag '
                . $v['bilag'] . '), så bilaget ikke længere balancerer i DKK (difference 0,03).';
            $expected = $named($invoiceVoucher, $invoice) . ' ' . $named($creditVoucher, $creditNote)
                . ' Slet bilagenes linjer i kladden, så kommer fakturaerne tilbage i Hent ordrer, og flyt dem til kladden igen.';
            foreach (['simuler', 'bogfor'] as $funktion) {
                $html = $this->previewPage($funktion, $kladdeId);
                $this->assertStringContainsString($expected, $html, "$funktion preview: names both vouchers and says what to do");
                $this->assertDoesNotMatchRegularExpression('/name="?(simuler|bogfor)"?/', $html, "$funktion preview: no button that posts, although the journal's total is 0");
            }
            $this->assertStringContainsString(json_encode($expected . ' Ingen posteringer er gemt.'), $this->refusedPosting('simuler', $kladdeId), 'Simulér refuses with the message');
            $this->assertStringContainsString($expected . ' Ingen posteringer er gemt.', $this->refusedPosting('bogfor', $kladdeId), 'so does Bogfør: no voucher is posted 0.03 off');
            $this->assertSame([3, 3], [count($this->journalLines($kladdeId, $invoice['id'])), count($this->journalLines($kladdeId, $creditNote['id']))], 'the lines are kept');
            $this->assertSame(['4', '4'], array_column(self::rows('SELECT status FROM ordrer WHERE id IN ($1, $2) ORDER BY id', [$invoice['id'], $creditNote['id']]), 'status'), 'both stay moved');

            // Only a voucher that does not balance on its own is refused: with each voucher's 0.03 booked on another account
            // in the same voucher, the journal is posted as before, every voucher balanced.
            foreach ([[$invoiceVoucher, 'kredit'], [$creditVoucher, 'debet']] as [$v, $side]) {
                self::rows(
                    "INSERT INTO kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, amount, kladde_id) VALUES ($1, $2, $3, 'F', $4, 'F', $5, 0.03, $6)",
                    [$v['bilag'], $v['transdate'], Fixtures::TAG . ' 0.03 of the rate', $side === 'debet' ? $correction : null, $side === 'kredit' ? $correction : null, $kladdeId]
                );
            }
            $this->assertMatchesRegularExpression('/name="?bogfor"?/', $this->previewPage('bogfor', $kladdeId), 'with each voucher balanced the preview shows Bogfør');
            $this->postJournal($kladdeId);
            $balanced = [(int)$invoiceVoucher['bilag'] => 0.0, (int)$creditVoucher['bilag'] => 0.0];
            ksort($balanced);
            $this->assertSame(
                $balanced,
                array_map(static fn(array $r): float => round((float)$r['net'], 3) + 0.0, array_column(self::rows(
                    'SELECT bilag, sum(COALESCE(debet, 0)) - sum(COALESCE(kredit, 0)) AS net FROM transaktioner WHERE kladde_id = $1 GROUP BY bilag ORDER BY bilag',
                    [$kladdeId]
                ), null, 'bilag')),
                'each posted voucher balances'
            );
            $this->assertSame(
                self::byAccount([$acc['revenue'] => [0.0, 1492.0], $acc['collective'] => [1865.03, 0.0], $acc['vat'] => [0.0, 373.0]]),
                $this->ledger($invoice['id']),
                'the invoice is posted at 746.01 for the customer, its revenue and VAT as moved'
            );
        } finally {
            foreach ($ids as $id) {
                self::rows('UPDATE ordrer SET status = 2 WHERE id = $1 AND status = 3', [$id]); // not for the Flyt alle tests
            }
            $this->removeOreAccount();
            $this->removeEuro($euro);
        }
    }

    /**
     * Start Flyt til kladde of one order in the background, like moveToJournal(), with its own session save path (PHP's
     * files handler locks a session file until the request ends, so two children sharing one would run in turn).
     *
     * @return array{proc: resource, out: resource, err: resource, html: string, sessionDir: string}
     */
    private function startMove(int $kladdeId, int $orderId, string $login): array
    {
        $sessionDir = sys_get_temp_dir() . '/chartest-session-' . $login . '-' . bin2hex(random_bytes(6));
        mkdir($sessionDir, 0700);
        $html = (string)tempnam(sys_get_temp_dir(), 'chartest-hentordrer-');
        $out = tmpfile();
        $err = tmpfile();
        $proc = proc_open(
            [PHP_BINARY, __DIR__ . '/../support/run_hentordrer_page.php', "kladde_id=$kladdeId&ordre_id=$orderId", CharacterizationEnv::sessionId(), $html, $sessionDir],
            [0 => ['file', '/dev/null', 'r'], 1 => $out, 2 => $err],
            $pipes
        );
        $this->assertIsResource($proc, 'could not start Hent ordrer');
        return ['proc' => $proc, 'out' => $out, 'err' => $err, 'html' => $html, 'sessionDir' => $sessionDir];
    }

    /**
     * Wait for a child of startMove(); it is stopped after $timeout seconds.
     *
     * @return array{exit: int, stdout: string, stderr: string, html: string}
     */
    private function finishMove(array $child, float $timeout): array
    {
        // Only the first status that reports the child as stopped carries its exit code.
        $deadline = microtime(true) + $timeout;
        $status = proc_get_status($child['proc']);
        while ($status['running'] && microtime(true) < $deadline) {
            usleep(50000);
            $status = proc_get_status($child['proc']);
        }
        if ($status['running']) {
            proc_terminate($child['proc'], 9);
        }
        proc_close($child['proc']);
        $result = ['exit' => $status['running'] ? -1 : $status['exitcode']];
        foreach (['out' => 'stdout', 'err' => 'stderr'] as $stream => $key) {
            rewind($child[$stream]);
            $result[$key] = (string)stream_get_contents($child[$stream]);
            fclose($child[$stream]);
        }
        $result['html'] = (string)@file_get_contents($child['html']);
        @unlink($child['html']);
        array_map('unlink', glob($child['sessionDir'] . '/*') ?: []);
        rmdir($child['sessionDir']);
        return $result;
    }

    /** Other connections to the test database that wait for a lock in a statement on ordrer. */
    private function orderLockWaiters(): int
    {
        return (int)self::one(
            "SELECT count(*) AS n FROM pg_stat_activity
             WHERE datname = current_database() AND pid <> pg_backend_pid()
               AND wait_event_type = 'Lock' AND query ~* '(from|update) ordrer'"
        )['n'];
    }

    public function test_two_users_moving_one_invoice_at_once_write_it_into_one_journal(): void
    {
        // Two users press Flyt til kladde on the same invoice, each from their own journal, while the first move is still
        // running. Both found it at status 3 and each wrote it into their journal: posted, the revenue was booked twice.
        $invoice = $this->salesInvoice([['antal' => 2, 'pris' => 125.00]], false);
        $acc = $this->salesAccounts($invoice['id'], $invoice['konto_id']);
        $ordrenr = (string)self::one('SELECT ordrenr FROM ordrer WHERE id = $1', [$invoice['id']])['ordrenr'];
        $journals = [self::$fx->kladde([]), self::$fx->kladde([])];

        // A third connection holds the order row, as a move still writing its lines would; both requests start their
        // move and wait for the order inside their transaction.
        $blocker = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        CharacterizationEnv::rows($blocker, 'BEGIN');
        CharacterizationEnv::rows($blocker, 'SELECT id FROM ordrer WHERE id = $1 FOR UPDATE', [$invoice['id']]);
        $children = [];
        try {
            foreach ($journals as $i => $kladdeId) {
                $children[] = $this->startMove($kladdeId, $invoice['id'], "user$i");
            }
            $deadline = microtime(true) + 60;
            while ($this->orderLockWaiters() < 2 && microtime(true) < $deadline) {
                usleep(100000);
            }
            $waiting = $this->orderLockWaiters();
        } finally {
            pg_query($blocker, 'ROLLBACK');
            pg_close($blocker);
        }
        $results = array_map(fn(array $child): array => $this->finishMove($child, 120), $children);
        $this->assertSame(2, $waiting, 'both requests waited for the order at the same time');
        foreach ($results as $result) {
            $this->assertStringContainsString('CHARTEST_PAGE_DONE', $result['stdout'], "the page ran to its end.\nstderr: {$result['stderr']}");
        }

        $moved = self::rows('SELECT kladde_id, count(*) AS n FROM kassekladde WHERE ordre_id = $1 GROUP BY kladde_id', [$invoice['id']]);
        $this->assertSame(1, count($moved), 'the invoice is written into one journal only: ' . json_encode($moved));
        $kladdeId = (int)$moved[0]['kladde_id'];
        $this->assertCount(3, $this->journalLines($kladdeId, $invoice['id']), 'customer, revenue and VAT line');
        $refused = array_filter($results, static fn(array $r): bool => str_contains($r['html'], "Ordre $ordrenr er allerede flyttet til en kladde eller bogf&oslash;rt og flyttes ikke igen"));
        $this->assertCount(1, $refused, 'the other request tells its user the invoice is already moved');
        $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']])['status'], 'the invoice is moved');

        $this->postJournal($kladdeId);
        $this->assertSame(
            self::byAccount([$acc['revenue'] => [0.0, 250.0], $acc['collective'] => [312.5, 0.0], $acc['vat'] => [0.0, 62.5]]),
            $this->ledger($invoice['id']),
            'posted, the invoice is booked once: revenue 250.00, not 500.00'
        );
    }

    /** Kassekladde > Gem with "-" in the Bilag field of the given lines, which deletes them (kontroller() in kassekladde.php). */
    private function deleteJournalLines(int $kladdeId, array $lineIds): void
    {
        $body = ['save' => 'Gem', 'kladde_id' => $kladdeId, 'antal_ny' => count($lineIds), 'id' => []];
        foreach (array_values($lineIds) as $i => $lineId) {
            $body['id'][$i + 1] = $lineId;
            $body['bila' . ($i + 1)] = '-';
        }
        $htmlFile = (string)tempnam(sys_get_temp_dir(), 'chartest-kassekladde-');
        try {
            $res = self::runChild('run_kassekladde_save_page.php', [http_build_query($body), CharacterizationEnv::sessionId(), $htmlFile]);
            $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the journal page ran to its end.\nstderr: {$res['stderr']}");
        } finally {
            @unlink($htmlFile);
        }
    }
}
