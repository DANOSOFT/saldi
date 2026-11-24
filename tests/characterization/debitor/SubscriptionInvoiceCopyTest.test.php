<?php
// tests/characterization/debitor/SubscriptionInvoiceCopyTest.test.php
//
// Regression test for the audit finding sales-F2: Genfakturer (debitor/genfakturer.php, genfakt()) copies a
// subscription order for its next invoice, and the copy left out the lines' rabatart and omvbet and the order's
// omvbet. A discount of 20 kr per unit (rabatart 'amount', as the discount matrix writes it) became 20 % on every
// following invoice: 2 x 125,01 less 20 kr is 210,02 + 52,51 VAT = 262,53, and the copy was invoiced at
// 200,02 + 50,00 = 250,02. A reverse-charge line (omvbet) was charged VAT on the copy.
//
// The subscription line editor, debitor/ret_genfakt.php, showed the line's I alt and wrote the order total with a
// percentage discount too, and charged VAT on a reverse-charge line in that total.
//
// Drives the pages as a user does, through support/run_debitor_order_page.php: Genfakturer with Komplet
// (copy, deliver and invoice at once), and Genfakturer without it, then ret_genfakt.php's Fakturer.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261117 CL/ASR Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../support/PageForm.php';

final class SubscriptionInvoiceCopyTest extends CharacterizationTestCase
{
    /**
     * An invoiced subscription order: 2 x 125,01 less 20 kr per unit (rabatart 'amount'), next invoice in a month.
     *
     * @return array{id:int, item:array}
     */
    private function invoicedSubscription(): array
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['salgspris' => 125.01, 'kostpris' => 50.00]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 2, 'rabat' => 20]]);
        self::rows("UPDATE ordrelinjer SET rabatart = 'amount' WHERE ordre_id = $1 AND vare_id > 0", [$order['id']]);
        // procenttillag 0 as debitor/ordre.php stores it on a new order; genfakt() copies it into a numeric column
        self::rows("UPDATE ordrer SET sum = 210.02, moms = 52.51, procenttillag = 0, nextfakt = CURRENT_DATE + interval '1 month' WHERE id = $1", [$order['id']]);
        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['bogfor'], 'precondition: the original is invoiced');
        $this->assertSame([210.02, 52.51, 262.53], $this->posted($order['id']), 'precondition: the original is 210,02 + 52,51');
        return ['id' => $order['id'], 'item' => $item];
    }

    /**
     * Genfakturer on one order, as the page's form posts it: the Ok button and the choices (1 = Ja, 0 = Nej).
     *
     * @param array<string, string> $choices org_nr, komplet, fakt_dato, opdat_pris, opdat_text, slet_gfdato
     * @return int the id of the copy
     */
    private function genfakturer(int $orderId, array $choices): int
    {
        $html = $this->page('debitor/genfakturer.php', "id=$orderId");
        $this->assertSame(1, preg_match('/name=Ok value=([^ >]+)/', $html, $ok), 'the page draws its Ok button');
        $choices += ['org_nr' => '0', 'komplet' => '0', 'fakt_dato' => '0', 'opdat_pris' => '0', 'opdat_text' => '0', 'slet_gfdato' => '0'];
        $this->page('debitor/genfakturer.php', "id=$orderId", ['Ok' => $ok[1]] + $choices);
        $copy = self::one('SELECT max(id) AS id FROM ordrer WHERE id > $1', [$orderId]);
        $this->assertNotNull($copy['id'], 'Genfakturer made a copy');
        return (int)$copy['id'];
    }

    /**
     * A GET of a page, or a POST of its form.
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

    /** @return array{0:float,1:float,2:float} revenue, output VAT and receivable an invoice posted, in kr */
    private function posted(int $orderId): array
    {
        $line = self::one('SELECT bogf_konto, vat_account FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$orderId]);
        $credit = fn (int $kontonr): float => round((float)self::one(
            'SELECT coalesce(sum(kredit), 0) - coalesce(sum(debet), 0) AS amount FROM transaktioner WHERE ordre_id = $1 AND kontonr = $2',
            [$orderId, $kontonr]
        )['amount'], 2);
        $total = self::one('SELECT coalesce(sum(debet), 0) AS debet, coalesce(sum(kredit), 0) AS kredit FROM transaktioner WHERE ordre_id = $1', [$orderId]);
        $this->assertEqualsWithDelta((float)$total['debet'], (float)$total['kredit'], 0.005, 'the voucher balances');
        return [$credit((int)$line['bogf_konto']), $credit((int)$line['vat_account']), round((float)$total['debet'], 2)];
    }

    public function test_genfakturer_komplet_invoices_the_copy_with_the_discount_in_kr(): void
    {
        $original = $this->invoicedSubscription();

        $copy = $this->genfakturer($original['id'], ['komplet' => '1']);

        $line = self::one('SELECT antal, rabat, rabatart FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$copy]);
        $this->assertEqualsWithDelta(20.0, (float)$line['rabat'], 0.001);
        $this->assertSame('amount', $line['rabatart'], 'the copy keeps the discount in kr per unit (it was NULL: a percentage)');
        $order = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$copy]);
        $this->assertSame('4', (string)$order['status'], 'Komplet invoices the copy');
        $this->assertEqualsWithDelta(210.02, (float)$order['sum'], 0.005, 'the copy is invoiced at 210,02 (it was 200,02)');
        $this->assertEqualsWithDelta(52.51, (float)$order['moms'], 0.005, 'with 52,51 VAT (it was 50,00)');
        $this->assertSame([210.02, 52.51, 262.53], $this->posted($copy), 'revenue, VAT and receivable as the original (it was 200,02 / 50,00 / 250,02)');
        $openpost = self::one('SELECT amount FROM openpost WHERE refnr = $1', [$copy]);
        $this->assertEqualsWithDelta(262.53, (float)$openpost['amount'], 0.005, 'the open item is 262,53 (it was 250,02)');
    }

    public function test_the_copy_shows_and_invoices_the_discount_in_kr_on_ret_genfakt(): void
    {
        $original = $this->invoicedSubscription();

        // Opdater priser takes the price from the item card, which is still 125,01
        $copy = $this->genfakturer($original['id'], ['opdat_pris' => '1']);

        $order = self::one('SELECT status, sum FROM ordrer WHERE id = $1', [$copy]);
        $this->assertSame('2', (string)$order['status'], 'without Komplet the copy waits for ret_genfakt.php');
        $this->assertEqualsWithDelta(210.02, (float)$order['sum'], 0.005, 'the copy total counts 20 kr per unit (it was 200,02)');

        $html = $this->page('debitor/ret_genfakt.php', "ordreliste=$copy");
        $this->assertStringContainsString('value="210,02"', $html, "the line's I alt is 2 x (125,01 - 20) (it showed 200,02)");

        $form = PageForm::fields($html, 'ret_genfakt');
        $form['submit'] = 'Fakturer';
        $this->page('debitor/ret_genfakt.php', 'returside=ordreliste.php', $form);

        $order = self::one('SELECT status, sum, moms FROM ordrer WHERE id = $1', [$copy]);
        $this->assertSame('4', (string)$order['status'], 'Fakturer invoices the copy');
        $this->assertEqualsWithDelta(210.02, (float)$order['sum'], 0.005, 'invoiced at 210,02 (it was 200,02)');
        $this->assertEqualsWithDelta(52.51, (float)$order['moms'], 0.005, 'with 52,51 VAT (it was 50,00)');
        $this->assertSame([210.02, 52.51, 262.53], $this->posted($copy), 'revenue, VAT and receivable as the original (it was 200,02 / 50,00 / 250,02)');
    }

    public function test_the_copy_of_a_reverse_charge_line_stays_reverse_charge(): void
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['salgspris' => 125.01, 'kostpris' => 50.00]);
        $original = self::$fx->order($debtor, [['item' => $item, 'antal' => 2]], ['status' => 4]);
        self::rows("UPDATE ordrelinjer SET omvbet = 'on' WHERE ordre_id = $1 AND vare_id > 0", [$original['id']]);
        self::rows("UPDATE ordrer SET omvbet = 'on', moms = 0, procenttillag = 0, fakturadate = CURRENT_DATE, nextfakt = CURRENT_DATE + interval '1 month' WHERE id = $1", [$original['id']]);

        $copy = $this->genfakturer($original['id'], []);

        $line = self::one('SELECT omvbet FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$copy]);
        $this->assertSame('on', $line['omvbet'], 'the copied line is reverse charge (it was NULL)');
        $order = self::one('SELECT omvbet FROM ordrer WHERE id = $1', [$copy]);
        $this->assertSame('on', $order['omvbet'], 'the copy is an order of a reverse-charge customer');

        $form = PageForm::fields($this->page('debitor/ret_genfakt.php', "ordreliste=$copy"), 'ret_genfakt');
        $form['submit'] = 'Gem';
        $this->page('debitor/ret_genfakt.php', 'returside=ordreliste.php', $form);

        $order = self::one('SELECT sum, moms FROM ordrer WHERE id = $1', [$copy]);
        $this->assertEqualsWithDelta(250.02, (float)$order['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$order['moms'], 0.005, 'the saved total charges no VAT on the reverse-charge line (it was 62,51)');
    }
}
