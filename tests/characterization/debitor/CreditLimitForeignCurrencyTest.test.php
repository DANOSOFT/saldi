<?php
// tests/characterization/debitor/CreditLimitForeignCurrencyTest.test.php
//
// The credit-limit check at the bottom of the sales order page (debitor/ordre.php) converts the customer's unpaid
// invoices into the order's currency. For an order in a foreign currency it stopped the page on PHP 8:
//  - an unpaid invoice in kroner looked up the order currency's rate with db_select() without its second argument
//    (ArgumentCountError);
//  - an unpaid invoice in a third currency multiplied by the currency code instead of its rate (TypeError).
// When no rate existed on the invoice date the warning named only the invoice number, as its text was set in the
// other branch.
// An order in kroner whose valuta field is empty (debitor/csv2ordre.php leaves it NULL, a debtor group without a
// currency leaves it '') is a kroner order: its unpaid kroner invoices count in full, with no rate lookup.
//
// Opens the order as a browser does, through support/run_debitor_order_page.php.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261119 CL/ASR Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class CreditLimitForeignCurrencyTest extends CharacterizationTestCase
{
    /**
     * A currency (grupper art 'VK') with its rates, given as days back => rate, unless the tenant has it.
     *
     * @param array<int, float> $rates
     */
    private static function currency(string $code, array $rates): void
    {
        if (self::one("SELECT id FROM grupper WHERE art = 'VK' AND box1 = $1", [$code]) !== null) {
            return;
        }
        $vk = (int)self::one("SELECT COALESCE(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'VK'")['kodenr'];
        self::rows("INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, fiscal_year) VALUES ($1, '', $2, 'VK', $1, '', $3)",
            [$code, $vk, self::$regnaar]);
        foreach ($rates as $daysBack => $rate) {
            self::rows('INSERT INTO valuta (gruppe, valdate, kurs) VALUES ($1, CURRENT_DATE - $2::int, $3)', [$vk, $daysBack, $rate]);
        }
    }

    /**
     * A customer with a credit limit of 1.000,00 kr and an open EUR order of 1 x 100,00 at the rate 750, with EUR at
     * 745 from 30 days ago and at 750 from 2 days ago.
     *
     * @return array{debtor:array, order:int}
     */
    private function euroOrder(): array
    {
        self::currency('EUR', [30 => 745, 2 => 750]);
        $debtor = self::$fx->debtor(['kreditmax' => 1000]);
        $item = self::$fx->item(['salgspris' => 100.00]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 1]], ['valuta' => 'EUR']);
        self::rows('UPDATE ordrer SET valutakurs = 750 WHERE id = $1', [$order['id']]);
        return ['debtor' => $debtor, 'order' => $order['id']];
    }

    /**
     * The order page for an order, as a GET: the texts of its alert() boxes and its credit-limit warning (or null), so a
     * failure shows those instead of the whole page.
     *
     * @return array{alerts: list<string>, warning: ?string}
     */
    private function creditLimitCheck(int $orderId): array
    {
        $res = $this->runChildJson('run_debitor_order_page.php', ["id=$orderId", CharacterizationEnv::sessionId()]);
        $this->assertFalse(str_contains($res['html'], 'Fatal error'), "debitor/ordre.php?id=$orderId crashed");
        $html = html_entity_decode($res['html'], ENT_QUOTES, 'UTF-8');
        $this->assertTrue(str_contains($html, 'saldi-einvoice-cvr-data'), 'the page is drawn past the credit-limit check');
        preg_match_all("~alert\\('([^']*)'\\)~", $html, $alerts);
        $warning = preg_match('~Kreditloft overskrides med[^<]*~', $html, $m) ? preg_replace('~\\s+~', ' ', trim($m[0])) : null;
        return ['alerts' => $alerts[1], 'warning' => $warning];
    }

    /**
     * An unpaid invoice of 900,00 kr from 10 days ago is converted at the EUR rate of its date, 745: 120,81 EUR. With
     * the order's 125,00 EUR (100,00 + VAT) that is 245,81 EUR against a limit of 1.000,00 kr at 750, 133,33 EUR.
     */
    public function test_an_unpaid_invoice_in_kroner_is_converted_at_the_euro_rate_of_its_date(): void
    {
        ['debtor' => $debtor, 'order' => $orderId] = $this->euroOrder();
        self::$fx->openPost($debtor, 900.00, 10, 'CL1');

        $check = $this->creditLimitCheck($orderId);

        $this->assertSame('Kreditloft overskrides med EUR 112,47', $check['warning'], 'the page is drawn to the end (it stopped with an ArgumentCountError)');
    }

    /** An unpaid invoice in kroner from before the first EUR rate cannot be converted: it is left out, and the warning says so. */
    public function test_an_unpaid_invoice_in_kroner_without_a_rate_on_its_date_is_named_in_the_warning(): void
    {
        ['debtor' => $debtor, 'order' => $orderId] = $this->euroOrder();
        self::$fx->openPost($debtor, 900.00, 40, 'CL2');

        $check = $this->creditLimitCheck($orderId);

        $this->assertContains('Ingen valutakurs for faktura CL2', $check['alerts'], 'the warning has its text (it named only the invoice)');
        $this->assertNull($check['warning'], 'the order alone, 125,00 EUR, is within 133,33 EUR');
    }

    /**
     * An unpaid invoice of 100,00 USD at the rate 650 is 650,00 kr, 86,67 EUR at the order's 750: with the order's
     * 125,00 EUR that is 78,33 EUR over the limit.
     */
    public function test_an_unpaid_invoice_in_a_third_currency_is_converted_through_kroner(): void
    {
        ['debtor' => $debtor, 'order' => $orderId] = $this->euroOrder();
        $openPost = self::$fx->openPost($debtor, 100.00, 10, 'CL3');
        self::rows("UPDATE openpost SET valuta = 'USD', valutakurs = 650 WHERE id = $1", [$openPost]);

        $check = $this->creditLimitCheck($orderId);

        $this->assertSame('Kreditloft overskrides med EUR 78,33', $check['warning'], 'the page is drawn to the end (it stopped with a TypeError)');
    }

    /** @return array<string, array{0:?string}> an empty valuta, as an import (NULL) or a debtor group without a currency ('') leaves it */
    public static function emptyCurrencies(): array
    {
        return ['NULL' => [null], 'empty string' => ['']];
    }

    /**
     * A kroner order with an empty valuta and an unpaid kroner invoice of 900,00: the invoice counts in full, so the
     * order's 125,00 (100,00 + VAT) is 25,00 over the limit of 1.000,00, as with valuta DKK. The check took the empty
     * valuta for a foreign currency, found no rate for it, warned "Ingen valutakurs for faktura" and left the invoice out.
     */
    #[DataProvider('emptyCurrencies')]
    public function test_a_kroner_order_without_a_currency_counts_an_unpaid_kroner_invoice_in_full(?string $valuta): void
    {
        $debtor = self::$fx->debtor(['kreditmax' => 1000]);
        $item = self::$fx->item(['salgspris' => 100.00]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 1]]);
        self::rows('UPDATE ordrer SET valuta = $1, valutakurs = NULL WHERE id = $2', [$valuta, $order['id']]);
        self::$fx->openPost($debtor, 900.00, 10, 'CL4');

        $check = $this->creditLimitCheck($order['id']);

        $this->assertSame([], $check['alerts'], 'no rate is looked up for a kroner order (it warned: Ingen valutakurs for faktura CL4)');
        $this->assertSame('Kreditloft overskrides med 25,00', $check['warning'], '125,00 + 900,00 is 25,00 over 1.000,00 (no warning: the invoice was left out)');
    }
}
