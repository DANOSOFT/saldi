<?php
// tests/characterization/finans/KassekladdeHentButtonTest.test.php
//
// An open cash journal shows Hent (name='upload', it opens
// finans/hentordrer.php) while invoices that were not posted wait at status 3.
//
// "Omgående bogføring af salgsordrer" and "... købsordrer" (grupper DIV/3
// box5, "<sales>;<purchases>") unticked leave an invoice at status 3, and
// Kassekladde > Hent is how it reaches the journal. The button was drawn only
// when the DIV/3 row was missing, and every regnskab is created with it, so it
// never showed (testlab round 1, deferred-invoice-posting, DEVY-1).
//
// The button then showed only while sales or purchases were not posted right
// away. An invoice fetched into a journal while one setting was off, whose
// journal lines were deleted after both were switched on, went back to status
// 3 without Hent. Owner's decision 20 Nov 2026, "Vis Hent ved efterladte": Hent
// shows whenever an order Hent ordrer lists waits, whatever the settings say.
//
// Pins: with a non-POS order at status 3 the journal shows Hent, whatever
// box5 holds (also both on, the single 'on' from before 3.6 and no DIV/3 row);
// with nothing waiting it does not, also with both on; POS orders alone, which
// hentordrer.php does not list, do not show it, and with vis_saet neither does
// a sales order paid at a till alone, which Hent ordrer leaves to the register
// close. A sales and a purchase invoice left over that way (both on, their
// journal lines deleted) show Hent, Hent ordrer lists and moves them, and the
// posted journal books them as when the settings were off.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261115 CL/ASR Integration: master's per-class test database; the session id comes from CharacterizationEnv::sessionId().
// 20261120 CL/ASR Hent shows for an order left waiting with both settings on (owner, 20 Nov 2026), so the both-on cases now show it while an order waits.
//                 Nothing waiting draws no Hent for any setting, and a sales order paid at a till alone draws none with vis_saet.
//                 A sales and a purchase invoice left over after both were switched on (their journal lines deleted) show Hent, are moved by Hent ordrer and are posted.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class KassekladdeHentButtonTest extends CharacterizationTestCase
{
    /** The Hent button as kassekladde.php draws it; its name is the request field the page dispatches on. */
    private const HENT = "/<input[^>]*name='upload'/";

    /** @var list<array{id:string, art:string, box5:?string}> grupper DIV/3 before the test changed it; restored in tearDown(). */
    private array $orderSettings = [];

    /** @var list<int> Orders this test left at status 3; tearDown() takes them out of Hent's list again. */
    private array $createdOrders = [];

    /** @var list<string> Status-3 orders already in the tenant that a test set aside; tearDown() puts them back. */
    private array $setAside = [];

    /** Whether the test added POS settings (vis_saet); tearDown() removes them. */
    private bool $visSaet = false;

    protected function setUp(): void
    {
        $this->orderSettings = self::rows("SELECT id, art, box5 FROM grupper WHERE art = 'DIV' AND kodenr = 3");
        $this->assertNotSame([], $this->orderSettings, 'the installed tenant has the order settings row (grupper DIV/3)');
    }

    protected function tearDown(): void
    {
        foreach ($this->orderSettings as $row) {
            self::rows('UPDATE grupper SET art = $1, box5 = $2 WHERE id = $3', [$row['art'], $row['box5'], $row['id']]);
        }
        foreach ($this->createdOrders as $id) {
            self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [$id]);
        }
        foreach ($this->setAside as $id) {
            self::rows('UPDATE ordrer SET status = 3 WHERE id = $1', [$id]);
        }
        if ($this->visSaet) {
            self::rows("DELETE FROM grupper WHERE art = 'POS'");
        }
    }

    private function setPostRightAway(string $box5): void
    {
        self::rows("UPDATE grupper SET box5 = $1 WHERE art = 'DIV' AND kodenr = 3", [$box5]);
    }

    /** box5 as given, or null: the ledger has no DIV/3 row (it is renamed; tearDown() restores it). */
    private function setOrderSettings(?string $box5): void
    {
        if ($box5 === null) {
            self::rows("UPDATE grupper SET art = 'DIV-chartest' WHERE art = 'DIV' AND kodenr = 3");
        } else {
            $this->setPostRightAway($box5);
        }
    }

    /** Takes the status-3 orders already in the tenant out of Hent's list, so only what the test makes waits. */
    private function setAsideWaitingOrders(): void
    {
        foreach (self::rows("SELECT id FROM ordrer WHERE art != 'PO' AND status = 3") as $row) {
            self::rows('UPDATE ordrer SET status = -3 WHERE id = $1', [$row['id']]);
            $this->setAside[] = $row['id'];
        }
    }

    /** @param array<string,mixed> $overrides Fixtures::order() overrides, e.g. status and art. */
    private function order(array $overrides = []): int
    {
        $order = self::$fx->order(self::$fx->debtor(), [['item' => self::$fx->item(['stocked' => false]), 'antal' => 2, 'pris' => 125.00]], $overrides);
        $this->createdOrders[] = (int)$order['id'];
        return (int)$order['id'];
    }

    /** The open journal's page, as finans/kassekladde.php?kladde_id=.. draws it. */
    private function journalPage(): string
    {
        $kladdeId = self::$fx->kladde([]);
        $htmlFile = (string)tempnam(sys_get_temp_dir(), 'chartest-kassekladde-');
        try {
            $res = self::runChild('run_kassekladde_page.php', ["kladde_id=$kladdeId", CharacterizationEnv::sessionId(), $htmlFile]);
            $html = (string)file_get_contents($htmlFile);
        } finally {
            @unlink($htmlFile);
        }
        $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the page ran to its end.\nstderr: {$res['stderr']}");
        // Booleans, not assertStringContainsString(): a failure would print the whole page.
        $this->assertTrue(str_contains($html, "name='doPost'"), 'the journal is open for editing: its buttons are drawn');
        return $html;
    }

    private static function hasHent(string $html): bool
    {
        return preg_match(self::HENT, $html) === 1;
    }

    public function test_hent_shows_for_an_invoice_left_unposted_by_the_order_settings(): void
    {
        // What the lab did: both "Omgående bogføring" boxes unticked and saved, then a sales invoice.
        $this->setPostRightAway(';');
        $id = $this->order();
        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $id, CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['bogfor'] ?? null, 'the order is invoiced');
        $this->assertSame('3', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$id])['status'], 'invoiced, not posted: it waits for Hent');

        $this->assertTrue(self::hasHent($this->journalPage()), 'Hent is drawn while the invoice waits');
    }

    /** @return array<string, array{0: ?string}> box5, or null for no DIV/3 row */
    public static function settings(): array
    {
        return [
            'neither posted right away'          => [';'],
            'purchases not posted right away'    => ['on;'],
            'sales not posted right away'        => [';on'],
            'single value from before 3.6, off'  => [''],
            'no order settings row'              => [null],
            'both posted right away'             => ['on;on'],
            'single value from before 3.6, on'   => ['on'],
        ];
    }

    #[DataProvider('settings')]
    public function test_hent_shows_while_an_order_waits_whatever_the_immediate_posting_settings(?string $box5): void
    {
        $this->setOrderSettings($box5);
        $this->order(['status' => 3]);

        $this->assertTrue(
            self::hasHent($this->journalPage()),
            'an order waits at status 3, which Hent ordrer lists: Hent is drawn, also when it was left over with sales and purchases posted right away'
        );
    }

    #[DataProvider('settings')]
    public function test_without_a_waiting_order_there_is_no_hent(?string $box5): void
    {
        $this->setAsideWaitingOrders();
        $this->setOrderSettings($box5);

        $this->assertFalse(self::hasHent($this->journalPage()), 'no order waits for Hent ordrer: no Hent, also with sales and purchases posted right away');
    }

    public function test_pos_orders_alone_do_not_show_hent(): void
    {
        $this->setAsideWaitingOrders();
        $this->setPostRightAway(';');
        $this->order(['status' => 3, 'art' => 'PO']);

        $this->assertFalse(self::hasHent($this->journalPage()), 'hentordrer.php lists no POS order, so a POS order alone draws no Hent');
    }

    public function test_with_vis_saet_a_sales_order_paid_at_a_till_alone_does_not_show_hent(): void
    {
        // Indstillinger > POS with vis_saet (POS/2 box12): the register close takes a sales order paid at a till through
        // its kasse fields (felt_1 payment type, felt_2 and felt_4 amounts, felt_5 the till), and Hent ordrer leaves it out.
        $this->setAsideWaitingOrders();
        $this->assertSame([], self::rows("SELECT id FROM grupper WHERE art = 'POS'"), 'the template tenant has no POS settings');
        self::rows("INSERT INTO grupper (beskrivelse, kode, kodenr, art, box12, fiscal_year) VALUES ('POS', '', 2, 'POS', 'on', $1)", [self::$regnaar]);
        $this->visSaet = true;
        $this->setPostRightAway(';');
        $paid = $this->order(['status' => 3]);
        self::rows("UPDATE ordrer SET felt_1 = 'Kontant', felt_2 = '312.5', felt_3 = '', felt_4 = '0', felt_5 = '1' WHERE id = $1", [$paid]);

        $kladdeId = self::$fx->kladde([]);
        $this->assertStringNotContainsString("&ordre_id=$paid>Flyt til kladde", $this->hentordrerPage("kladde_id=$kladdeId"), 'Hent ordrer does not list the order paid at the till');
        $this->assertFalse(self::hasHent($this->journalPage()), 'so it alone draws no Hent');

        $this->order(['status' => 3]);
        $this->assertTrue(self::hasHent($this->journalPage()), 'an order Hent ordrer lists next to it draws Hent');
    }

    // ------------------------------------------------------------------ an invoice left over with both settings on

    /** Runs Hent ordrer with the given query string and returns the page's HTML (the order list, an alert). */
    private function hentordrerPage(string $query): string
    {
        $htmlFile = (string)tempnam(sys_get_temp_dir(), 'chartest-hentordrer-');
        try {
            $res = self::runChild('run_hentordrer_page.php', [$query, CharacterizationEnv::sessionId(), $htmlFile]);
            $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the page ran to its end.\nstderr: {$res['stderr']}");
            $this->assertStringNotContainsString('db_select failed', $res['stderr'], 'the page runs without a database error');
            $html = (string)file_get_contents($htmlFile);
            $this->assertStringNotContainsString('Uforudset h', $html, 'the page shows no database error');
            return $html;
        } finally {
            @unlink($htmlFile);
        }
    }

    /** Deletes journal lines the way a user does: Bilag "-" on each line, then Gem (kontroller() in finans/kassekladde.php). */
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

    /**
     * The journal lines of one order as [side, type, account, amount], sorted.
     *
     * @return list<array{0:string, 1:string, 2:int, 3:float}>
     */
    private function journalLines(int $kladdeId, int $orderId): array
    {
        $lines = array_map(
            static fn(array $r): array => $r['d_type']
                ? ['debet', $r['d_type'], (int)$r['debet'], round((float)$r['amount'], 3)]
                : ['kredit', $r['k_type'], (int)$r['kredit'], round((float)$r['amount'], 3)],
            self::rows('SELECT d_type, debet, k_type, kredit, amount FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2', [$kladdeId, $orderId])
        );
        return self::sorted($lines);
    }

    /** Journal lines sorted by side, type, account and amount, as journalLines() returns them. */
    private static function sorted(array $lines): array
    {
        sort($lines);
        return $lines;
    }

    /**
     * The ledger an order left, as account => [debit, credit], by account (the journal's posting carries the order id).
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

    private static function orderStatus(int $orderId): string
    {
        return (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$orderId])['status'];
    }

    public function test_invoices_left_waiting_with_both_settings_on_show_hent_and_are_moved_and_posted(): void
    {
        $this->setAsideWaitingOrders();

        // Sales and purchases not posted right away: a sales invoice of 2 x 125.00 + 25 % VAT through ordrefunc's bogfor(),
        // and a purchase of 2 x 50.00 + 25 % VAT as kreditor/bogfor.php leaves it (status 3, invoice date and receipt
        // number set, the line on its purchase account). Both wait at status 3.
        $this->setPostRightAway(';');
        $debtor = self::$fx->debtor();
        $sale = (int)self::$fx->order($debtor, [['item' => self::$fx->item(['stocked' => false]), 'antal' => 2, 'pris' => 125.00]])['id'];
        $this->createdOrders[] = $sale;
        self::rows("UPDATE ordrelinjer SET projekt = '' WHERE ordre_id = $1", [$sale]); // flytordre() books the lines per project
        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $sale, CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['bogfor'] ?? null, 'the sales order is invoiced');
        $this->assertSame('DO', (string)self::one('SELECT art FROM ordrer WHERE id = $1', [$sale])['art']);

        $creditor = self::$fx->creditor();
        $item = self::$fx->item(['stocked' => false]);
        $purchase = (int)self::$fx->creditorOrder($creditor, [['item' => $item, 'antal' => 2, 'pris' => 50.00]], ['art' => 'KO', 'status' => 3])['id'];
        $this->createdOrders[] = $purchase;
        $purchaseAccount = (int)self::one("SELECT box3 FROM grupper WHERE art = 'VG' AND kodenr = $1 AND fiscal_year = $2", [$item['gruppe'], self::$regnaar])['box3'];
        self::rows('UPDATE ordrer SET fakturadate = ordredate, fakturanr = $1, modtagelse = $2 WHERE id = $2', ['P' . $purchase, $purchase]);
        self::rows("UPDATE ordrelinjer SET bogf_konto = $1, projekt = '' WHERE ordre_id = $2", [$purchaseAccount, $purchase]);
        $this->assertSame(['3', '3'], [self::orderStatus($sale), self::orderStatus($purchase)], 'invoiced, not posted: both wait for Hent');

        // Fetched into a journal (status 4), then both settings switched on, which nothing waiting allows.
        $first = self::$fx->kladde([]);
        self::rows("UPDATE kladdeliste SET bogfort = '-' WHERE id = $1", [$first]); // an open journal, as kassekladde.php creates it
        $this->hentordrerPage("kladde_id=$first&ordre_id=$sale");
        $this->hentordrerPage("kladde_id=$first&ordre_id=$purchase");
        $this->assertSame(['4', '4'], [self::orderStatus($sale), self::orderStatus($purchase)], 'both are moved into the journal');
        $this->setPostRightAway('on;on');
        $this->assertFalse(self::hasHent($this->journalPage()), 'both on and nothing waits: no Hent');

        // The journal's lines deleted: nothing of them is in the ledger, so both go back to status 3 (left over).
        $lineIds = array_map('intval', array_column(self::rows('SELECT id FROM kassekladde WHERE kladde_id = $1 ORDER BY id', [$first]), 'id'));
        $this->assertCount(6, $lineIds, 'three lines each');
        $this->deleteJournalLines($first, $lineIds);
        $this->assertSame([], self::rows('SELECT id FROM kassekladde WHERE kladde_id = $1', [$first]), 'the journal\'s lines are deleted');
        $this->assertSame(['3', '3'], [self::orderStatus($sale), self::orderStatus($purchase)], 'both wait at status 3 again, with both settings on');

        $this->assertTrue(self::hasHent($this->journalPage()), 'Hent is drawn for the invoices left waiting, although sales and purchases are posted right away');

        // Hent ordrer lists and moves them with both settings on, and the posted journal books what the settings off book.
        $second = self::$fx->kladde([]);
        $list = $this->hentordrerPage("kladde_id=$second");
        $this->assertStringContainsString("&ordre_id=$sale>Flyt til kladde", $list, 'Hent ordrer lists the sales invoice');
        $this->assertStringContainsString("&ordre_id=$purchase>Flyt til kladde", $list, 'and the purchase invoice');
        $this->hentordrerPage("kladde_id=$second&flyt=alle");
        $this->assertSame(['4', '4'], [self::orderStatus($sale), self::orderStatus($purchase)], 'Flyt alle moves both');

        $sales = self::one(
            "SELECT sm.box1 AS vat, dg.box2 AS collective FROM adresser a
             JOIN grupper dg ON dg.art = 'DG' AND dg.kodenr = a.gruppe
             JOIN grupper sm ON sm.art = 'SM' AND sm.kodenr = substr(trim(dg.box1), 2, 1)::integer
             WHERE a.id = $1",
            [$debtor['id']]
        );
        $revenue = (int)self::one('SELECT DISTINCT bogf_konto FROM ordrelinjer WHERE ordre_id = $1', [$sale])['bogf_konto'];
        $purchases = self::one(
            "SELECT km.box1 AS vat, kg.box2 AS collective FROM adresser a
             JOIN grupper kg ON kg.art = 'KG' AND kg.kodenr = a.gruppe
             JOIN grupper km ON km.art = 'KM' AND km.kodenr = substr(trim(kg.box1), 2, 1)::integer
             WHERE a.id = $1",
            [$creditor['id']]
        );
        $this->assertSame(
            self::sorted([['debet', 'D', (int)$debtor['kontonr'], 312.5], ['kredit', 'F', $revenue, 250.0], ['kredit', 'F', (int)$sales['vat'], 62.5]]),
            $this->journalLines($second, $sale),
            'the debtor is debited 312.50, revenue credited 250.00 and VAT 62.50'
        );
        $this->assertSame(
            self::sorted([['debet', 'F', $purchaseAccount, 100.0], ['debet', 'F', (int)$purchases['vat'], 25.0], ['kredit', 'K', (int)$creditor['kontonr'], 125.0]]),
            $this->journalLines($second, $purchase),
            'the purchase is debited 100.00 and the purchase VAT 25.00, the supplier credited 125.00'
        );

        self::runChild('run_bogfor_page.php', ['bogfor', $second, CharacterizationEnv::sessionId()]);
        $this->assertSame('V', (string)self::one('SELECT bogfort FROM kladdeliste WHERE id = $1', [$second])['bogfort'], 'the journal is posted');
        $expectedSale = [(int)$sales['collective'] => [312.5, 0.0], $revenue => [0.0, 250.0], (int)$sales['vat'] => [0.0, 62.5]];
        ksort($expectedSale);
        $this->assertSame($expectedSale, $this->ledger($sale), 'the sale is booked: customers 312.50, revenue 250.00, VAT 62.50');
        $expectedPurchase = [(int)$purchases['collective'] => [0.0, 125.0], $purchaseAccount => [100.0, 0.0], (int)$purchases['vat'] => [25.0, 0.0]];
        ksort($expectedPurchase);
        $this->assertSame($expectedPurchase, $this->ledger($purchase), 'the purchase is booked: suppliers 125.00, purchases 100.00, purchase VAT 25.00');

        $this->assertFalse(self::hasHent($this->journalPage()), 'nothing waits any more: no Hent');
    }
}
