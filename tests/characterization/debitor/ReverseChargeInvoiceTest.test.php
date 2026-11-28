<?php
// tests/characterization/debitor/ReverseChargeInvoiceTest.test.php
//
// Regression test for the audit finding p1-sales-F1: a sales line with reverse charge (omvendt betalingspligt,
// ordrelinjer.omvbet) is invoiced without output VAT. The line is reverse charge when the customer's group has
// DG box9 and the item's group has VG box6 (opret_ordrelinje()); the order page then shows and saves the order
// with no VAT on that line.
//
// Posting refused such an invoice. batch_salg() wrote the sales account's VAT rate (25 %) back onto the line,
// momsupdat() left the header at the order page's zero VAT, and bogfor_nu()'s balance pre-check
// (forkontrolPosteringsbalance()) added VAT from the line's rate: 2 x 125,01 was refused with
// "Bogføring afbrudt: ordren balancerer ikke (difference -62,51 DKK)".
//
// Each route that invoices a sales order is driven as a user drives it, through
// support/run_debitor_order_page.php: an order made on the order page and invoiced with Fakturér, its credit note
// (Kreditér), a Genfakturer copy of a subscription, the remainder of Del ordre, and bulk invoicing.
//
// A Genfakturer copy and a Del ordre remainder are written without the line's momssats. The order page gave such a
// line the order's rate before it looked at omvbet, so it showed and stored VAT on the reverse-charge line. With a
// VAT-free line on the same order, momsupdat() keeps that header, and the invoice was refused as out of balance.
//
// Bulk invoicing with Delfakturer wrote the remainder of a split without the order's reverse charge (ordrer.omvbet)
// and department (afd). The order page draws the O/B box only for an order with omvbet, so the remainder's credit
// note, and a remainder the run left open and invoiced on the order page, lost the line's reverse charge and charged VAT.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261119 CL/ASR Created.
//                 A Genfakturer copy and a Del ordre remainder invoiced with Fakturér on the order page are checked, with and without a VAT-free line.
//                 The credit note of a Delfakturer remainder, and a remainder the bulk run left open and invoiced on the order page, are checked.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../support/PageForm.php';

final class ReverseChargeInvoiceTest extends CharacterizationTestCase
{
    /** grupper.kodenr of the debtor group with reverse charge (DG box9), made once per class */
    private static ?int $debtorGroup = null;
    /** grupper.kodenr of the item group with reverse charge (VG box6), made once per class */
    private static ?int $itemGroup = null;
    /** grupper.kodenr of an item group whose items are VAT-free (VG box7), made once per class */
    private static ?int $vatFreeGroup = null;
    /** @var list<array{0:string, 1:array}> the statements that put back the settings a test changed */
    private array $restore = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$debtorGroup = null;
        self::$itemGroup = null;
        self::$vatFreeGroup = null;
    }

    protected function tearDown(): void
    {
        $this->restoreSettings();
    }

    /** Puts back the settings the test changed. */
    private function restoreSettings(): void
    {
        foreach (array_reverse($this->restore) as [$sql, $params]) {
            self::rows($sql, $params);
        }
        $this->restore = [];
    }

    /** Hurtigfakturering off (grupper DIV 3 box4) until tearDown(): the order page offers Del ordre only without it. */
    private function quickInvoicingOff(): void
    {
        $row = self::one("SELECT id, box4 FROM grupper WHERE art = 'DIV' AND kodenr = 3");
        $this->assertNotNull($row, 'the tenant has the order settings row');
        $this->restore[] = ['UPDATE grupper SET box4 = $1 WHERE id = $2', [$row['box4'], $row['id']]];
        self::rows("UPDATE grupper SET box4 = '' WHERE id = $1", [$row['id']]);
    }

    /** Delfakturer on in bulk invoicing (grupper MFAKT box2), with no delivery deadline (box3), until tearDown(). */
    private function partialBulkInvoicingOn(): void
    {
        $row = self::one("SELECT id, box2, box3 FROM grupper WHERE art = 'MFAKT' AND kodenr = 1");
        if ($row === null) {
            $row = self::one("INSERT INTO grupper (beskrivelse, kode, kodenr, art, box2, box3) VALUES ('Massefakturering', '', 1, 'MFAKT', '', '') RETURNING id, box2, box3");
            $this->restore[] = ['DELETE FROM grupper WHERE id = $1', [$row['id']]];
        } else {
            $this->restore[] = ['UPDATE grupper SET box2 = $1, box3 = $2 WHERE id = $3', [$row['box2'], $row['box3'], $row['id']]];
        }
        self::rows("UPDATE grupper SET box2 = 'on', box3 = '0' WHERE id = $1", [$row['id']]);
    }

    /**
     * A copy of the first debtor group (or of item group $from) with the reverse-charge box on.
     *
     * @return int the new group's kodenr
     */
    private static function reverseChargeGroup(string $art, string $box, ?int $from = null, string $name = 'chartest omvendt betalingspligt'): int
    {
        $source = $from === null
            ? self::one("SELECT id FROM grupper WHERE art = $1 ORDER BY kodenr LIMIT 1", [$art])
            : self::one("SELECT id FROM grupper WHERE art = $1 AND kodenr = $2 AND fiscal_year = $3", [$art, $from, self::$regnaar]);
        self::assertNotNull($source, "the tenant has a $art group to copy");
        $boxes = implode(', ', array_map(static fn(int $n): string => "box$n", range(1, 14)));
        $copied = str_replace("$box,", "'on',", "$boxes,");
        $row = self::one(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, fiscal_year, $boxes)
             SELECT $3, kode, (SELECT max(kodenr) + 1 FROM grupper WHERE art = $1), art, fiscal_year, "
            . rtrim($copied, ',') . " FROM grupper WHERE id = $2 RETURNING kodenr",
            [$art, (int)$source['id'], $name]
        );
        return (int)$row['kodenr'];
    }

    /** @return array{id:int, kontonr:string} a customer in a debtor group with reverse charge */
    private static function reverseChargeDebtor(): array
    {
        self::$debtorGroup ??= self::reverseChargeGroup('DG', 'box9');
        $debtor = self::$fx->debtor();
        self::rows('UPDATE adresser SET gruppe = $1 WHERE id = $2', [self::$debtorGroup, $debtor['id']]);
        return $debtor;
    }

    /** @return array{id:int, varenr:string, salgspris:float, kostpris:float, gruppe:int} an item in a group with reverse charge */
    private static function reverseChargeItem(float $price, float $stock = 100.0): array
    {
        $item = self::$fx->item(['salgspris' => $price, 'kostpris' => 50.00, 'beholdning' => $stock]);
        self::$itemGroup ??= self::reverseChargeGroup('VG', 'box6', $item['gruppe']);
        self::rows('UPDATE varer SET gruppe = $1 WHERE id = $2', [self::$itemGroup, $item['id']]);
        $item['gruppe'] = self::$itemGroup;
        return $item;
    }

    /** @return array{id:int, varenr:string, salgspris:float, kostpris:float, gruppe:int} an item of a VAT-free item group, on the same sales account */
    private static function vatFreeItem(float $price): array
    {
        $item = self::$fx->item(['salgspris' => $price, 'kostpris' => 10.00, 'beholdning' => 100]);
        self::$vatFreeGroup ??= self::reverseChargeGroup('VG', 'box7', $item['gruppe'], 'chartest momsfri');
        self::rows('UPDATE varer SET gruppe = $1 WHERE id = $2', [self::$vatFreeGroup, $item['id']]);
        $item['gruppe'] = self::$vatFreeGroup;
        return $item;
    }

    /**
     * An open order (status 1) of a reverse-charge customer as the order page saves it: $antal x 125,01 less 20 kr per
     * unit with reverse charge, and $antal x 50,00 VAT-free. The order's total has no VAT.
     *
     * @return array{id:int, ordrenr:int}
     */
    private function mixedReverseChargeOrder(float $antal): array
    {
        $reverse = self::reverseChargeItem(125.01);
        $free = self::vatFreeItem(50.00);
        $order = self::$fx->order(self::reverseChargeDebtor(), [
            ['item' => $reverse, 'antal' => $antal, 'rabat' => 20], ['item' => $free, 'antal' => $antal, 'momssats' => 0],
        ]);
        self::rows("UPDATE ordrelinjer SET omvbet = 'on', rabatart = 'amount' WHERE ordre_id = $1 AND vare_id = $2", [$order['id'], $reverse['id']]);
        self::rows("UPDATE ordrelinjer SET momsfri = 'on' WHERE ordre_id = $1 AND vare_id = $2", [$order['id'], $free['id']]);
        self::rows("UPDATE ordrer SET omvbet = 'on', sum = $1, moms = 0, procenttillag = 0 WHERE id = $2", [round($antal * 155.01, 2), $order['id']]);
        return ['id' => $order['id'], 'ordrenr' => $order['ordrenr']];
    }

    /**
     * A page, through support/run_debitor_order_page.php: a GET, or a POST of its form.
     *
     * @param array<string, string> $post
     */
    private function page(string $page, string $query, array $post = []): string
    {
        $res = $this->runChildJson(
            'run_debitor_order_page.php',
            [$query, CharacterizationEnv::sessionId(), $post === [] ? '' : http_build_query($post), $page]
        );
        $this->assertStringNotContainsString('Fatal error', $res['html'], "$page?$query crashed");
        return $res['html'];
    }

    /** The query of the page a meta refresh hands over to, or null. */
    private static function refreshTo(string $html, string $page): ?string
    {
        if (!preg_match('~content="0;URL=' . preg_quote($page, '~') . '\?([^"]*)"~', $html, $m)) {
            return null;
        }
        return html_entity_decode($m[1]);
    }

    /**
     * What an invoice posted, in kr: revenue on the line's sales account, output VAT on its VAT account, and the
     * receivable (the voucher's debit, which must equal its credit).
     *
     * @return array{0:float,1:float,2:float}
     */
    private function posted(int $orderId): array
    {
        $line = self::one('SELECT bogf_konto, vat_account FROM ordrelinjer WHERE ordre_id = $1 AND bogf_konto > 0 AND posnr >= 0 ORDER BY id LIMIT 1', [$orderId]);
        $credit = fn (int $kontonr): float => round((float)self::one(
            'SELECT coalesce(sum(kredit), 0) - coalesce(sum(debet), 0) AS amount FROM transaktioner WHERE ordre_id = $1 AND kontonr = $2',
            [$orderId, $kontonr]
        )['amount'], 2);
        $total = self::one('SELECT coalesce(sum(debet), 0) AS debet, coalesce(sum(kredit), 0) AS kredit FROM transaktioner WHERE ordre_id = $1', [$orderId]);
        $this->assertEqualsWithDelta((float)$total['debet'], (float)$total['kredit'], 0.005, 'the voucher balances');
        $vatAccount = (int)$line['vat_account'];
        if ($vatAccount === 0) {
            $vatAccount = (int)self::one("SELECT box1 FROM grupper WHERE art = 'SM' ORDER BY kodenr LIMIT 1")['box1'];
        }
        return [$credit((int)$line['bogf_konto']), $credit($vatAccount), round((float)$total['debet'], 2)];
    }

    private static function openPost(int $orderId): ?float
    {
        $row = self::one('SELECT amount FROM openpost WHERE refnr = $1', [$orderId]);
        return $row === null ? null : round((float)$row['amount'], 2);
    }

    /**
     * An open order (status 1) of a reverse-charge customer, as the order page saves it: $antal x $price less
     * $discount kr per unit (rabatart 'amount'), the line reverse charge and the order's total without VAT.
     *
     * @return array{id:int, ordrenr:int}
     */
    private function reverseChargeOrder(float $antal, float $price, float $discount, float $stock = 100.0): array
    {
        $item = self::reverseChargeItem($price, $stock);
        $order = self::$fx->order(self::reverseChargeDebtor(), [['item' => $item, 'antal' => $antal, 'rabat' => $discount]]);
        self::rows("UPDATE ordrelinjer SET omvbet = 'on', rabatart = $1 WHERE ordre_id = $2 AND vare_id > 0", [$discount ? 'amount' : '', $order['id']]);
        self::rows("UPDATE ordrer SET omvbet = 'on', sum = $1, moms = 0, procenttillag = 0 WHERE id = $2", [round($antal * ($price - $discount), 2), $order['id']]);
        return ['id' => $order['id'], 'ordrenr' => $order['ordrenr']];
    }

    /**
     * Genfakturer (debitor/genfakturer.php) on one subscription order, as its form posts it.
     *
     * @return int the id of the copy
     */
    private function genfakturer(int $orderId, bool $komplet): int
    {
        $html = $this->page('debitor/genfakturer.php', "id=$orderId");
        $this->assertSame(1, preg_match('/name=Ok value=([^ >]+)/', $html, $ok), 'the page draws its Ok button');
        $this->page('debitor/genfakturer.php', "id=$orderId", [
            'Ok' => $ok[1], 'org_nr' => '0', 'komplet' => $komplet ? '1' : '0', 'fakt_dato' => '0', 'opdat_pris' => '0',
            'opdat_text' => '0', 'slet_gfdato' => '0',
        ]);
        $copy = self::one('SELECT max(id) AS id FROM ordrer WHERE id > $1', [$orderId]);
        $this->assertNotNull($copy['id'], 'Genfakturer made a copy');
        return (int)$copy['id'];
    }

    /**
     * An invoiced subscription of a reverse-charge customer: 2 x 125,01 less 20 kr per unit, no VAT, next invoice in a
     * month. With $vatFreeLine also 2 x 50,00 VAT-free.
     */
    private function invoicedReverseChargeSubscription(bool $vatFreeLine = false): int
    {
        $orderId = $vatFreeLine ? $this->mixedReverseChargeOrder(2)['id'] : $this->reverseChargeOrder(2, 125.01, 20)['id'];
        self::rows("UPDATE ordrer SET status = 4, fakturadate = CURRENT_DATE, nextfakt = CURRENT_DATE + interval '1 month',
            udskriv_til = 'PDF', mail_fakt = '' WHERE id = $1", [$orderId]);
        return $orderId;
    }

    /**
     * Bulk invoicing, debitor/massefakt.php?valg=ordrer. It starts after order $after, through the ordre_id the page
     * hands to its own next batch, so the orders other tests in this class left open are not invoiced too.
     */
    private function bulkInvoice(int $after): void
    {
        $this->page('debitor/massefakt.php', "valg=ordrer&ordre_id=$after");
    }

    private static function lastOrderId(): int
    {
        return (int)self::one('SELECT coalesce(max(id), 0) AS id FROM ordrer')['id'];
    }

    /**
     * The id bulk invoicing starts after, as lastOrderId(). The page starts a run without an ordre_id after order 1, so
     * a closed order is made first: the test's order is never order 1, also when the test runs on its own.
     */
    private static function startAfter(): int
    {
        self::$fx->order(self::$fx->debtor(), [], ['status' => 9]);
        return self::lastOrderId();
    }

    /**
     * An order made on the order page: opened from the customer (ordre.php?konto_id=), each item typed into the new
     * line and saved with Gem.
     *
     * @param list<array{0:string,1:string}> $lines varenr and Antal as typed
     * @return int the order's id
     */
    private function orderMadeOnThePage(array $debtor, array $lines): int
    {
        $before = (int)self::one('SELECT coalesce(max(id), 0) AS id FROM ordrer')['id'];
        $this->page('debitor/ordre.php', "konto_id={$debtor['id']}");
        $orderId = (int)self::one('SELECT coalesce(max(id), 0) AS id FROM ordrer WHERE konto_id = $1', [$debtor['id']])['id'];
        $this->assertGreaterThan($before, $orderId, 'the order page made an order for the customer');
        foreach ($lines as [$varenr, $antal]) {
            $form = PageForm::fields($this->page('debitor/ordre.php', "id=$orderId"), 'ordre');
            $form['vare0'] = $varenr;
            $form['dkan0'] = $antal;
            $form['save'] = 'Gem';
            $this->page('debitor/ordre.php', "id=$orderId", $form);
        }
        return $orderId;
    }

    /**
     * Fakturér on the order page, and the pages it hands over to: debitor/levering.php (fast invoicing), then
     * debitor/bogfor.php.
     *
     * @return string debitor/bogfor.php's refusal, or '' when it posted
     */
    private function pressFakturer(int $orderId): string
    {
        $form = PageForm::fields($this->page('debitor/ordre.php', "id=$orderId"), 'ordre');
        $form['doInvoice'] = 'Fakturér';
        $html = $this->page('debitor/ordre.php', "id=$orderId", $form);
        $levering = self::refreshTo($html, 'levering.php');
        if ($levering !== null) {
            $html = $this->page('debitor/levering.php', $levering);
        }
        $bogfor = self::refreshTo($html, 'bogfor.php');
        $this->assertNotNull($bogfor, 'Fakturér hands the order over to debitor/bogfor.php');
        $html = $this->page('debitor/bogfor.php', $bogfor);
        return preg_match('~Svar (.*?)<br>~s', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
    }

    /**
     * The ordinary route: an order made on the order page for a reverse-charge customer, with an item of a
     * reverse-charge group, invoiced with Fakturér. 2 x 125,01 = 250,02 with no VAT.
     */
    public function test_an_order_page_invoice_of_a_reverse_charge_line_is_posted_without_vat(): void
    {
        $debtor = self::reverseChargeDebtor();
        $item = self::reverseChargeItem(125.01);
        $orderId = $this->orderMadeOnThePage($debtor, [[$item['varenr'], '2']]);

        $line = self::one('SELECT omvbet, momssats FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$orderId]);
        $this->assertSame('on', $line['omvbet'], 'precondition: the typed line is reverse charge');
        $order = self::one('SELECT omvbet, sum, moms FROM ordrer WHERE id = $1', [$orderId]);
        $this->assertSame('on', $order['omvbet'], 'precondition: the order is a reverse-charge customer\'s');

        $refusal = $this->pressFakturer($orderId);

        $this->assertSame('', $refusal, 'posting is not refused (it was: ordren balancerer ikke, difference -62,51 DKK)');
        $order = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$orderId]);
        $this->assertGreaterThanOrEqual(3, (int)$order['status'], 'the order is invoiced');
        $this->assertEqualsWithDelta(250.02, (float)$order['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$order['moms'], 0.005, 'no VAT on the invoice');
        $this->assertSame([250.02, 0.0, 250.02], $this->posted($orderId), 'revenue 250,02, no output VAT, receivable 250,02');
        $this->assertSame(250.02, self::openPost($orderId), 'the open item is 250,02');
        $line = self::one('SELECT momssats FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$orderId]);
        $this->assertEqualsWithDelta(0.0, (float)$line['momssats'], 0.001, 'the invoiced line carries no VAT rate (it got the account\'s 25)');
    }

    /**
     * Kreditér on an invoiced reverse-charge order, then Fakturér on the credit note: it credits 250,02 with no VAT.
     *
     * The order page made the credit note without the invoice's reverse charge (ordrer.omvbet), so the credit note drew
     * no O/B box and its save cleared the line's omvbet. It then credited 312,53, of which 62,51 was output VAT that the
     * invoice never charged.
     */
    public function test_a_credit_note_of_a_reverse_charge_invoice_is_posted_without_vat(): void
    {
        $debtor = self::reverseChargeDebtor();
        $item = self::reverseChargeItem(125.01);
        $orderId = $this->orderMadeOnThePage($debtor, [[$item['varenr'], '2']]);
        $this->assertSame('', $this->pressFakturer($orderId), 'precondition: the invoice is posted');
        $this->assertSame([250.02, 0.0, 250.02], $this->posted($orderId), 'precondition: the invoice has no VAT');

        $form = PageForm::fields($this->page('debitor/ordre.php', "id=$orderId"), 'ordre');
        $form['credit'] = 'Kreditér';
        $this->page('debitor/ordre.php', "id=$orderId", $form);
        $note = self::one('SELECT id, art, omvbet FROM ordrer WHERE kred_ord_id = $1', [$orderId]);
        $this->assertNotNull($note, 'Kreditér made a credit note');
        $noteId = (int)$note['id'];
        $this->assertSame(['DK', 'on'], [$note['art'], $note['omvbet']], 'the credit note keeps the invoice\'s reverse charge (it was NULL)');

        $refusal = $this->pressFakturer($noteId);

        $this->assertSame('', $refusal, 'the credit note is posted');
        $line = self::one('SELECT omvbet, momssats FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$noteId]);
        $this->assertSame('on', $line['omvbet'], 'the credited line stays reverse charge (the save cleared it)');
        $this->assertEqualsWithDelta(0.0, (float)$line['momssats'], 0.001);
        $order = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$noteId]);
        $this->assertGreaterThanOrEqual(3, (int)$order['status'], 'the credit note is invoiced');
        $this->assertEqualsWithDelta(-250.02, (float)$order['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$order['moms'], 0.005, 'no VAT on the credit note (it was -62,51)');
        $this->assertSame([-250.02, 0.0, 250.02], $this->posted($noteId), 'revenue -250,02, no output VAT, receivable -250,02 (it was -312,53)');
        $this->assertSame(-250.02, self::openPost($noteId), 'the open item is -250,02 (it was -312,53)');
    }

    /**
     * A reverse-charge line and an ordinary line on one order, both on the same sales account: VAT on the ordinary
     * line only. 2 x 125,01 + 1 x 100,00 = 350,02 with 25,00 VAT.
     */
    public function test_a_mixed_order_charges_vat_only_on_the_line_without_reverse_charge(): void
    {
        $debtor = self::reverseChargeDebtor();
        $reverse = self::reverseChargeItem(125.01);
        $ordinary = self::$fx->item(['salgspris' => 100.00, 'kostpris' => 40.00]);
        $orderId = $this->orderMadeOnThePage($debtor, [[$reverse['varenr'], '2'], [$ordinary['varenr'], '1']]);
        $lines = self::rows('SELECT vare_id, omvbet FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0 ORDER BY id', [$orderId]);
        $this->assertSame([[(string)$reverse['id'], 'on'], [(string)$ordinary['id'], '']], array_map('array_values', $lines),
            'precondition: only the item of the reverse-charge group is reverse charge');

        $refusal = $this->pressFakturer($orderId);

        $this->assertSame('', $refusal, 'posting is not refused (it was: difference -62,51 DKK)');
        $order = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$orderId]);
        $this->assertGreaterThanOrEqual(3, (int)$order['status'], 'the order is invoiced');
        $this->assertEqualsWithDelta(350.02, (float)$order['sum'], 0.005);
        $this->assertEqualsWithDelta(25.00, (float)$order['moms'], 0.005, 'VAT on the ordinary line only');
        $this->assertSame([350.02, 25.0, 375.02], $this->posted($orderId), 'revenue 350,02, output VAT 25,00, receivable 375,02');
        $this->assertSame(375.02, self::openPost($orderId));
        $rate = self::one('SELECT momssats FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$orderId, $ordinary['id']]);
        $this->assertEqualsWithDelta(25.0, (float)$rate['momssats'], 0.001, 'the ordinary line keeps its rate');
    }

    /**
     * A line with its own sales account and no item (a text line whose O/B box the user ticked) is reverse charge
     * too. batch_salg() never sees such a line, so its rate stays 25 and only posting itself leaves the VAT out.
     */
    public function test_a_reverse_charge_line_with_its_own_account_is_posted_without_vat(): void
    {
        $order = self::$fx->order(self::reverseChargeDebtor(), []);
        $account = self::one("SELECT box4 FROM grupper WHERE art = 'VG' AND kodenr = $1 AND fiscal_year = $2",
            [self::$fx->item()['gruppe'], self::$regnaar])['box4'];
        self::rows("INSERT INTO ordrelinjer (ordre_id, posnr, beskrivelse, antal, pris, rabat, procent, bogf_konto, momssats, omvbet, leveres, leveret)
            VALUES ($1, 1, 'chartest ydelse', 1, 200, 0, 100, $2, 25, 'on', 0, 0)", [$order['id'], (int)$account]);
        self::rows("UPDATE ordrer SET omvbet = 'on', sum = 200, moms = 0, procenttillag = 0 WHERE id = $1", [$order['id']]);

        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);

        $this->assertSame('OK', $out['bogfor'], 'posting is not refused (it was: difference -50,00 DKK)');
        $this->assertEqualsWithDelta(0.0, (float)$out['moms'], 0.005);
        $this->assertSame([200.0, 0.0, 200.0], $this->posted($order['id']), 'revenue 200,00, no output VAT, receivable 200,00');
    }

    /** Genfakturer with Komplet copies, delivers and invoices at once: 2 x 125,01 less 20 kr = 210,02 with no VAT. */
    public function test_a_genfakturer_copy_with_komplet_is_invoiced_without_vat(): void
    {
        $copy = $this->genfakturer($this->invoicedReverseChargeSubscription(), true);

        $line = self::one('SELECT omvbet, rabatart FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$copy]);
        $this->assertSame(['on', 'amount'], [$line['omvbet'], $line['rabatart']], 'precondition: the copy is reverse charge with the discount in kr');
        $order = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$copy]);
        $this->assertSame('4', (string)$order['status'], 'Komplet invoices the copy (it stayed at 2: difference -52,51 DKK)');
        $this->assertEqualsWithDelta(210.02, (float)$order['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$order['moms'], 0.005);
        $this->assertSame([210.02, 0.0, 210.02], $this->posted($copy), 'revenue 210,02, no output VAT, receivable 210,02');
        $this->assertSame(210.02, self::openPost($copy));
    }

    /** Genfakturer without Komplet leaves the copy for debitor/ret_genfakt.php, whose Fakturer invoices it. */
    public function test_a_genfakturer_copy_invoiced_on_ret_genfakt_is_posted_without_vat(): void
    {
        $copy = $this->genfakturer($this->invoicedReverseChargeSubscription(), false);
        $form = PageForm::fields($this->page('debitor/ret_genfakt.php', "ordreliste=$copy"), 'ret_genfakt');
        $form['submit'] = 'Fakturer';

        $html = $this->page('debitor/ret_genfakt.php', 'returside=ordreliste.php', $form);

        $this->assertStringNotContainsString('balancerer ikke', strip_tags(html_entity_decode($html)), 'posting is not refused (it was: difference -52,51 DKK)');
        $order = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$copy]);
        $this->assertSame('4', (string)$order['status'], 'Fakturer invoices the copy');
        $this->assertEqualsWithDelta(0.0, (float)$order['moms'], 0.005);
        $this->assertSame([210.02, 0.0, 210.02], $this->posted($copy));
        $this->assertSame(210.02, self::openPost($copy));
    }

    /**
     * A Genfakturer copy without Komplet, opened on the order page and saved with Gem: the page shows and stores no VAT
     * on the reverse-charge line, and Fakturér posts 210,02 with no VAT.
     *
     * The copy's line has no momssats (Genfakturer does not copy it), and the page gave such a line the order's rate
     * before it looked at omvbet: it stored moms 52,51 (total 262,53), and the invoice then posted 210,02 with no VAT.
     */
    public function test_a_genfakturer_copy_on_the_order_page_shows_and_stores_no_vat_on_a_reverse_charge_line(): void
    {
        $copy = $this->genfakturer($this->invoicedReverseChargeSubscription(), false);
        $line = self::one('SELECT omvbet, momssats FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$copy]);
        $this->assertSame('on', $line['omvbet'], 'precondition: the copy is reverse charge');
        $this->assertEqualsWithDelta(0.0, (float)$line['momssats'], 0.001, 'precondition: the copy\'s line has no rate of its own');

        $form = PageForm::fields($this->page('debitor/ordre.php', "id=$copy"), 'ordre');
        $form['save'] = 'Gem';
        $this->page('debitor/ordre.php', "id=$copy", $form);

        $order = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$copy]);
        $this->assertLessThan(3, (int)$order['status'], 'precondition: the copy is not invoiced yet');
        $this->assertEqualsWithDelta(210.02, (float)$order['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$order['moms'], 0.005, 'the page stores no VAT on the reverse-charge line (it stored 52,51)');

        $this->assertSame('', $this->pressFakturer($copy), 'the copy is posted');
        $this->assertSame([210.02, 0.0, 210.02], $this->posted($copy), 'revenue 210,02, no output VAT, receivable 210,02');
        $this->assertSame(210.02, self::openPost($copy));
    }

    /**
     * A Genfakturer copy with a reverse-charge line (2 x 125,01 less 20 kr) and a VAT-free line (2 x 50,00), invoiced
     * with Fakturér on the order page: 310,02 with no VAT.
     *
     * The page stored moms 52,51 on the reverse-charge line. With the VAT-free line momsupdat() keeps that header, so the
     * invoice was refused (difference 52,51 DKK); before the reverse-charge fix it posted the 52,51 as output VAT.
     */
    public function test_a_mixed_genfakturer_copy_invoiced_on_the_order_page_charges_no_vat_on_the_reverse_charge_line(): void
    {
        $copy = $this->genfakturer($this->invoicedReverseChargeSubscription(true), false);
        $lines = self::rows('SELECT omvbet, momsfri FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0 ORDER BY id', [$copy]);
        $this->assertSame([['on', ''], ['', 'on']], array_map('array_values', $lines), 'precondition: one reverse-charge and one VAT-free line');

        $refusal = $this->pressFakturer($copy);

        $this->assertSame('', $refusal, 'posting is not refused (it was: ordren balancerer ikke, difference 52,51 DKK)');
        $order = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$copy]);
        $this->assertGreaterThanOrEqual(3, (int)$order['status'], 'the copy is invoiced');
        $this->assertEqualsWithDelta(310.02, (float)$order['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$order['moms'], 0.005, 'no VAT on the invoice');
        $this->assertSame([310.02, 0.0, 310.02], $this->posted($copy), 'revenue 310,02, no output VAT, receivable 310,02 (it was 362,53 with 52,51 VAT)');
        $this->assertSame(310.02, self::openPost($copy));
    }

    /**
     * Del ordre on a half-delivered order with a reverse-charge line (2 x 125,01 less 20 kr) and a VAT-free line
     * (2 x 50,00); the remainder is invoiced with Fakturér on the order page at 155,01 with no VAT.
     *
     * Del ordre writes the remainder's lines without momssats, so the page stored moms 26,25 on the reverse-charge line,
     * momsupdat() kept it, and the invoice was refused (difference 26,25 DKK).
     */
    public function test_a_mixed_del_ordre_remainder_invoiced_on_the_order_page_charges_no_vat_on_the_reverse_charge_line(): void
    {
        $this->quickInvoicingOff();
        $orderId = $this->mixedReverseChargeOrder(2)['id'];
        $out = $this->runChildJson('run_order_lifecycle.php', ['deliver', $orderId, 1, CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['levering'], 'precondition: one unit of each line delivered');
        $form = PageForm::fields($this->page('debitor/ordre.php', "id=$orderId"), 'ordre');
        $form['b_submit'] = 'Del ordre';
        $this->page('debitor/ordre.php', "id=$orderId", $form);
        $rest = self::one('SELECT r.id FROM ordrer r, ordrer o WHERE o.id = $1 AND r.ordrenr = o.ordrenr AND r.art = o.art AND r.id > o.id', [$orderId]);
        $this->assertNotNull($rest, 'Del ordre made the remainder order');
        $restId = (int)$rest['id'];
        $this->restoreSettings(); // Fakturér on the remainder delivers and invoices it at once, as without Del ordre

        $refusal = $this->pressFakturer($restId);

        $this->assertSame('', $refusal, 'posting is not refused (it was: ordren balancerer ikke, difference 26,25 DKK)');
        $order = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$restId]);
        $this->assertGreaterThanOrEqual(3, (int)$order['status'], 'the remainder is invoiced');
        $this->assertEqualsWithDelta(155.01, (float)$order['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$order['moms'], 0.005, 'no VAT on the invoice');
        $this->assertSame([155.01, 0.0, 155.01], $this->posted($restId), 'revenue 155,01, no output VAT, receivable 155,01');
        $this->assertSame(155.01, self::openPost($restId));
    }

    /** Del ordre on a half-delivered reverse-charge order: the remainder, 1 x 125,01 less 20 kr, is invoiced at 105,01 with no VAT. */
    public function test_the_del_ordre_remainder_of_a_reverse_charge_line_is_invoiced_without_vat(): void
    {
        $this->quickInvoicingOff();
        $orderId = $this->reverseChargeOrder(2, 125.01, 20)['id'];
        $out = $this->runChildJson('run_order_lifecycle.php', ['deliver', $orderId, 1, CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['levering'], 'precondition: one unit delivered');
        $html = $this->page('debitor/ordre.php', "id=$orderId");
        $this->assertStringContainsString('value="Del ordre"', $html, 'precondition: the page offers Del ordre');
        $form = PageForm::fields($html, 'ordre');
        $form['b_submit'] = 'Del ordre';
        $this->page('debitor/ordre.php', "id=$orderId", $form);
        $rest = self::one('SELECT r.id FROM ordrer r, ordrer o WHERE o.id = $1 AND r.ordrenr = o.ordrenr AND r.art = o.art AND r.id > o.id', [$orderId]);
        $this->assertNotNull($rest, 'Del ordre made the remainder order');
        $restId = (int)$rest['id'];

        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $restId, CharacterizationEnv::testDb()]);

        $this->assertSame('OK', $out['bogfor'], 'the remainder is invoiced (it was refused: difference -26,25 DKK)');
        $this->assertEqualsWithDelta(105.01, (float)$out['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$out['moms'], 0.005);
        $this->assertSame([105.01, 0.0, 105.01], $this->posted($restId), 'revenue 105,01, no output VAT, receivable 105,01');
        $this->assertSame(105.01, self::openPost($restId));
    }

    /** Bulk invoicing of a reverse-charge order: 2 x 125,01 = 250,02 with no VAT. */
    public function test_bulk_invoicing_posts_a_reverse_charge_order_without_vat(): void
    {
        $after = self::lastOrderId();
        $orderId = $this->reverseChargeOrder(2, 125.01, 0)['id'];

        $this->bulkInvoice($after);

        $order = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$orderId]);
        $this->assertGreaterThanOrEqual(3, (int)$order['status'], 'the order is invoiced (it stayed at 2: difference -62,51 DKK)');
        $this->assertEqualsWithDelta(0.0, (float)$order['moms'], 0.005);
        $this->assertSame([250.02, 0.0, 250.02], $this->posted($orderId), 'revenue 250,02, no output VAT, receivable 250,02');
        $this->assertSame(250.02, self::openPost($orderId));
    }

    /**
     * Audit finding p1-sales-F2: with Delfakturer on, bulk invoicing splits an order with more ordered than in stock,
     * and the split ended in db_modify() calls without their second argument. PHP 8 stopped the page with an
     * ArgumentCountError and rolled the split back, so the order was neither split nor invoiced.
     *
     * 2 x 125,01 less 20 kr per unit with 1 in stock: the order keeps the unit in stock and is invoiced at 105,01 with
     * no VAT; the other unit goes to a new order with the same order number. find_next() does not hold that order back
     * for want of stock (its stock check never runs: $vare_id[$y] is only set inside the block it guards), so the same
     * run invoices it too, also at 105,01 with no VAT.
     */
    public function test_bulk_invoicing_with_delfakturer_splits_a_reverse_charge_order_and_invoices_the_part_in_stock(): void
    {
        $this->partialBulkInvoicingOn();
        $after = self::lastOrderId();
        $order = $this->reverseChargeOrder(2, 125.01, 20, 1.0);

        $this->bulkInvoice($after);

        $rows = self::rows(
            "SELECT o.id, o.status, o.sum, o.moms, l.antal, l.rabatart, l.procent, l.omvbet
             FROM ordrer o JOIN ordrelinjer l ON l.ordre_id = o.id AND l.vare_id > 0 WHERE o.ordrenr = $1 AND o.art = 'DO' ORDER BY o.id",
            [$order['ordrenr']]
        );
        $this->assertCount(2, $rows, 'the order is split in two');
        [$kept, $rest] = $rows;
        $this->assertSame((string)$order['id'], $kept['id']);
        $this->assertEqualsWithDelta(1.0, (float)$kept['antal'], 0.001, 'the order keeps the unit in stock');
        $this->assertGreaterThanOrEqual(3, (int)$kept['status'], 'and is invoiced');
        $this->assertEqualsWithDelta(105.01, (float)$kept['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$kept['moms'], 0.005);
        $this->assertSame([105.01, 0.0, 105.01], $this->posted($order['id']), 'revenue 105,01, no output VAT, receivable 105,01');
        $this->assertEqualsWithDelta(1.0, (float)$rest['antal'], 0.001, 'the other unit is on the remainder order');
        $this->assertSame(['amount', 'on'], [$rest['rabatart'], $rest['omvbet']], 'with the discount in kr and reverse charge');
        $this->assertEqualsWithDelta(100.0, (float)$rest['procent'], 0.001);
        $this->assertGreaterThanOrEqual(3, (int)$rest['status'], 'the same run invoices the remainder');
        $this->assertEqualsWithDelta(105.01, (float)$rest['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$rest['moms'], 0.005);
        $this->assertSame([105.01, 0.0, 105.01], $this->posted((int)$rest['id']), 'revenue 105,01, no output VAT, receivable 105,01');
    }

    /** A department (grupper AFD) until tearDown(), with no stock location of its own. @return int its number */
    private function department(): int
    {
        $row = self::one(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1)
             SELECT 'chartest afdeling', '', coalesce(max(kodenr), 0) + 1, 'AFD', '' FROM grupper WHERE art = 'AFD' RETURNING id, kodenr"
        );
        $this->restore[] = ['DELETE FROM grupper WHERE id = $1', [$row['id']]];
        return (int)$row['kodenr'];
    }

    /**
     * Kreditér on the remainder of a Delfakturer split, then Fakturér on the credit note: it credits 100,00 with no VAT.
     *
     * Bulk invoicing splits 2 x 100,00 with 1 in stock of a reverse-charge order on a department, and invoices both orders
     * at 100,00 with no VAT. delfakturer() wrote the remainder without the order's reverse charge (ordrer.omvbet),
     * department (afd), e-mail, phone and extra fields, which Del ordre copies. A credit note keeps the credited order's
     * omvbet, so the remainder's credit note drew no O/B box, its save cleared the line's omvbet, and it credited 125,00,
     * of which 25,00 output VAT the invoice never charged.
     */
    public function test_a_credit_note_of_a_delfakturer_remainder_of_a_reverse_charge_order_is_posted_without_vat(): void
    {
        $this->partialBulkInvoicingOn();
        $department = $this->department();
        $after = self::startAfter();
        $order = $this->reverseChargeOrder(2, 100.00, 0, 1.0);
        self::rows("UPDATE ordrer SET afd = $1, phone = '12345678', felt_1 = 'chartest felt 1' WHERE id = $2", [$department, $order['id']]);

        $this->bulkInvoice($after);

        $orders = self::rows("SELECT id, status, omvbet, afd, email, phone, felt_1 FROM ordrer WHERE ordrenr = $1 AND art = 'DO' ORDER BY id", [$order['ordrenr']]);
        $this->assertCount(2, $orders, 'precondition: the order is split in two');
        [$kept, $rest] = $orders;
        $restId = (int)$rest['id'];
        $this->assertSame(
            ['on', (string)$department, $kept['email'], '12345678', 'chartest felt 1'],
            [$rest['omvbet'], $rest['afd'], $rest['email'], $rest['phone'], $rest['felt_1']],
            'the remainder keeps the order\'s reverse charge, department, e-mail, phone and extra field (it had none of them)'
        );
        $this->assertGreaterThanOrEqual(3, (int)$rest['status'], 'precondition: the same run invoices the remainder');
        $this->assertSame([100.0, 0.0, 100.0], $this->posted($restId), 'precondition: the remainder invoice has no VAT');
        $afd = self::rows('SELECT DISTINCT afd FROM transaktioner WHERE ordre_id = $1', [$restId]);
        $this->assertSame([[(string)$department]], array_map('array_values', $afd), 'the remainder invoice is posted on the order\'s department (it was 0)');

        $form = PageForm::fields($this->page('debitor/ordre.php', "id=$restId"), 'ordre');
        $form['credit'] = 'Kreditér';
        $this->page('debitor/ordre.php', "id=$restId", $form);
        $note = self::one('SELECT id, art, omvbet FROM ordrer WHERE kred_ord_id = $1', [$restId]);
        $this->assertNotNull($note, 'Kreditér made a credit note');
        $noteId = (int)$note['id'];
        $this->assertSame(['DK', 'on'], [$note['art'], $note['omvbet']], 'the credit note keeps the remainder\'s reverse charge (it was empty)');

        $refusal = $this->pressFakturer($noteId);

        $this->assertSame('', $refusal, 'the credit note is posted');
        $line = self::one('SELECT omvbet FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$noteId]);
        $this->assertSame('on', $line['omvbet'], 'the credited line stays reverse charge (the save cleared it)');
        $note = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$noteId]);
        $this->assertGreaterThanOrEqual(3, (int)$note['status'], 'the credit note is invoiced');
        $this->assertEqualsWithDelta(-100.00, (float)$note['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$note['moms'], 0.005, 'no VAT on the credit note (it was -25,00)');
        $this->assertSame([-100.0, 0.0, 100.0], $this->posted($noteId), 'revenue -100,00, no output VAT, receivable -100,00 (it was -125,00 with -25,00 VAT)');
        $this->assertSame(-100.0, self::openPost($noteId), 'the open item is -100,00 (it was -125,00)');
    }

    /**
     * A Delfakturer remainder that bulk invoicing leaves open: a run stops after ten invoices, and the split order is the
     * tenth. The missing unit arrives, and the remainder, 1 x 100,00, is invoiced with Fakturér on the order page at
     * 100,00 with no VAT.
     *
     * delfakturer() wrote the remainder without the order's reverse charge (ordrer.omvbet). The order page draws the O/B
     * box only for such an order, so the save behind Fakturér cleared the line's omvbet and the invoice posted 25,00
     * output VAT (125,00 open).
     */
    public function test_an_open_delfakturer_remainder_of_a_reverse_charge_order_invoiced_on_the_order_page_is_posted_without_vat(): void
    {
        $this->partialBulkInvoicingOn();
        $after = self::startAfter();
        $other = self::$fx->item(['salgspris' => 10.00, 'kostpris' => 1.00, 'beholdning' => 100]);
        for ($i = 0; $i < 9; $i++) {
            self::$fx->order(self::$fx->debtor(), [['item' => $other, 'antal' => 1]]);
        }
        $order = $this->reverseChargeOrder(2, 100.00, 0, 1.0);

        $this->bulkInvoice($after);

        $orders = self::rows("SELECT id, status, omvbet FROM ordrer WHERE ordrenr = $1 AND art = 'DO' ORDER BY id", [$order['ordrenr']]);
        $this->assertCount(2, $orders, 'precondition: the order is split in two');
        [$kept, $rest] = $orders;
        $this->assertGreaterThanOrEqual(3, (int)$kept['status'], 'precondition: the split order is the run\'s tenth invoice');
        $this->assertLessThan(3, (int)$rest['status'], 'precondition: the run stops there and leaves the remainder open');
        $restId = (int)$rest['id'];
        $this->assertSame('on', $rest['omvbet'], 'the remainder keeps the order\'s reverse charge (it was NULL)');
        $line = self::one('SELECT vare_id FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$restId]);
        self::rows('UPDATE varer SET beholdning = 1 WHERE id = $1', [(int)$line['vare_id']]); // the missing unit arrives
        $this->assertMatchesRegularExpression('~name="omvbet\[\d+\]"~', $this->page('debitor/ordre.php', "id=$restId"), 'the page draws the remainder\'s O/B box');

        $refusal = $this->pressFakturer($restId);

        $this->assertSame('', $refusal, 'the remainder is posted');
        $line = self::one('SELECT omvbet FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$restId]);
        $this->assertSame('on', $line['omvbet'], 'the line stays reverse charge (the save cleared it)');
        $rest = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$restId]);
        $this->assertGreaterThanOrEqual(3, (int)$rest['status'], 'the remainder is invoiced');
        $this->assertEqualsWithDelta(100.00, (float)$rest['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$rest['moms'], 0.005, 'no VAT on the invoice (it was 25,00)');
        $this->assertSame([100.0, 0.0, 100.0], $this->posted($restId), 'revenue 100,00, no output VAT, receivable 100,00 (it was 125,00 with 25,00 VAT)');
        $this->assertSame(100.0, self::openPost($restId), 'the open item is 100,00 (it was 125,00)');
    }
}
