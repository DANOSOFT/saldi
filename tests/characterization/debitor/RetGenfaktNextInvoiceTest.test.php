<?php
// tests/characterization/debitor/RetGenfaktNextInvoiceTest.test.php
//
// «Ret abonnementsordrer» (debitor/ret_genfakt.php): the Genfakt. field
// (next invoice date, ordrer.nextfakt) on «Gem».
//
// An empty field keeps the stored date instead of saving today's date, and
// '-' clears it, as on the order card (SST-927). When Genfakt. is not after
// the invoice date, the message names every such order and their fields are
// marked in red. The message is a valid JavaScript string whatever the names
// contain; an apostrophe used to break it, so no message showed at all.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261122 CL/SZ SST-927 Created; the regression test was asked for by Lui on PR #1070.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class RetGenfaktNextInvoiceTest extends CharacterizationTestCase
{
    /** @return array{id:int, ordrenr:int} a subscription order with a stored next invoice date */
    private function subscription(string $name, string $nextfakt): array
    {
        $order = self::$fx->order(self::$fx->debtor(), [], ['firmanavn' => $name]);
        self::rows('UPDATE ordrer SET nextfakt = $1 WHERE id = $2', [$nextfakt, $order['id']]);
        return $order;
    }

    /**
     * Posts the form the way its «Gem» button does: invoice date today, and the given Genfakt. text per row.
     *
     * @param list<array{id:int}> $orders in the order the form lists them
     * @param list<string> $genfakt the Genfakt. field of each row
     * @return array{alert:string|array|null, red_genfakt:list<string>}
     */
    private function save(array $orders, array $genfakt): array
    {
        $fields = ['ordreliste' => array_column($orders, 'id'), 'linjeantal' => 0, 'submit' => 'Gem', 'fokus' => '', 'id' => ''];
        foreach ($orders as $x => $order) {
            $row = self::one('SELECT firmanavn, email FROM ordrer WHERE id = $1', [$order['id']]);
            $fields += [
                "firmanavn_$x" => $row['firmanavn'],
                "fakturadato_$x" => date('d-m-Y'),
                "genfakt_$x" => $genfakt[$x],
                "udskriv_til_$x" => 'PDF',
                "email_$x" => $row['email'],
                "betalingsbet_$x" => 'Netto',
                "betalingsdage_$x" => '8',
                "procenttillag_$x" => '0',
            ];
        }
        return $this->runChildJson('run_ret_genfakt_page.php', [http_build_query($fields), CharacterizationEnv::sessionId()]);
    }

    private function nextfakt(int $id): ?string
    {
        return self::one('SELECT nextfakt FROM ordrer WHERE id = $1', [$id])['nextfakt'];
    }

    public function test_an_empty_field_keeps_the_stored_next_invoice_date(): void
    {
        $stored = date('Y-m-d', strtotime('+2 months'));
        $order = $this->subscription('Tom Genfakt', $stored);

        $out = $this->save([$order], ['']);

        $this->assertSame($stored, $this->nextfakt($order['id']), 'not today');
        $this->assertNull($out['alert']);
    }

    public function test_a_dash_clears_the_next_invoice_date(): void
    {
        $order = $this->subscription('Streg Genfakt', date('Y-m-d', strtotime('+2 months')));

        $out = $this->save([$order], ['-']);

        $this->assertNull($this->nextfakt($order['id']));
        $this->assertNull($out['alert']);
    }

    public function test_every_order_with_a_next_invoice_date_not_after_the_invoice_date_is_named_and_marked(): void
    {
        $later = date('Y-m-d', strtotime('+2 months'));
        $first = $this->subscription('Forkert Et', $later);
        $fine = $this->subscription('Rigtig', $later);
        $second = $this->subscription('Forkert To', $later);
        $today = date('d-m-Y');

        $out = $this->save([$first, $fine, $second], [$today, date('d-m-Y', strtotime('+1 month')), $today]);

        $this->assertIsString($out['alert']);
        $this->assertStringContainsString("{$first['ordrenr']} Forkert Et", $out['alert']);
        $this->assertStringContainsString("{$second['ordrenr']} Forkert To", $out['alert']);
        $this->assertStringNotContainsString('Rigtig', $out['alert']);
        $this->assertSame(['genfakt_0', 'genfakt_2'], $out['red_genfakt'], 'only the two invalid fields are red');
    }

    public function test_the_message_shows_a_name_with_an_apostrophe_as_typed(): void
    {
        $order = $this->subscription("Anne O'Brien", date('Y-m-d', strtotime('+2 months')));

        $out = $this->save([$order], [date('d-m-Y')]);

        $this->assertIsString($out['alert'], 'the alert is a valid JavaScript string');
        $this->assertStringContainsString("{$order['ordrenr']} Anne O'Brien", $out['alert']);
    }
}
