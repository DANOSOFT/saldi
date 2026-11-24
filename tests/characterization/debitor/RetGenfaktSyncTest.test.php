<?php
// tests/characterization/debitor/RetGenfaktSyncTest.test.php
//
// «Ret abonnementsordrer» (debitor/ret_genfakt.php): the box next to a red
// company name or email copies the customer card's data onto that order on
// «Gem».
//
// The name sync used an unquoted array key, a fatal error on PHP 8, so
// ticking the box gave a white screen (SST-879). The ticks are posted per
// form row, so when an order drops out of the list (invoiced in the
// meantime), the remaining ticks must still land on the order they were
// next to.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261122 CL/SZ SST-879 Created; the regression test was asked for by Lui on PR #1069.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class RetGenfaktSyncTest extends CharacterizationTestCase
{
    /**
     * A subscription order whose customer card has since got another name, address and email.
     *
     * @return array{order:int, card:int}
     */
    private function orderBehindItsCard(string $label): array
    {
        $debtor = self::$fx->debtor(['firmanavn' => "Kort $label", 'email' => "kort-$label@example.invalid"]);
        self::rows('UPDATE adresser SET addr1 = $1 WHERE id = $2', ["Kortvej $label", $debtor['id']]);
        $order = self::$fx->order($debtor, [], ['firmanavn' => "Ordre $label", 'email' => "ordre-$label@example.invalid"]);
        return ['order' => $order['id'], 'card' => $debtor['id']];
    }

    /**
     * Posts the form the way its «Gem» button does, one row per order, with the given rows' boxes ticked.
     *
     * @param list<int> $orders the order ids, in the order the form lists them
     * @param list<int> $syncName rows with the name/address box ticked
     * @param list<int> $syncEmail rows with the email box ticked
     */
    private function save(array $orders, array $syncName = [], array $syncEmail = []): void
    {
        $fields = ['ordreliste' => $orders, 'linjeantal' => 0, 'submit' => 'Gem', 'fokus' => '', 'id' => ''];
        foreach ($orders as $x => $orderId) {
            $order = self::one('SELECT firmanavn, email FROM ordrer WHERE id = $1', [$orderId]);
            $fields += [
                "firmanavn_$x" => $order['firmanavn'],
                "fakturadato_$x" => date('d-m-Y'),
                "genfakt_$x" => date('d-m-Y', strtotime('+1 month')),
                "udskriv_til_$x" => 'PDF',
                "email_$x" => $order['email'],
                "betalingsbet_$x" => 'Netto',
                "betalingsdage_$x" => '8',
                "procenttillag_$x" => '0',
            ];
            if (in_array($x, $syncName, true)) $fields["sync_stamdata_$x"] = 'on';
            if (in_array($x, $syncEmail, true)) $fields["sync_email_$x"] = 'on';
        }
        $this->runChildJson('run_ret_genfakt_page.php', [http_build_query($fields), CharacterizationEnv::sessionId()]);
    }

    /** @return array{firmanavn:string, addr1:string, email:string} */
    private function order(int $id): array
    {
        return self::one('SELECT firmanavn, addr1, email FROM ordrer WHERE id = $1', [$id]);
    }

    public function test_ticking_the_name_box_copies_the_customer_card_onto_the_order(): void
    {
        $a = $this->orderBehindItsCard('A');

        $this->save([$a['order']], [0]);

        $this->assertSame(
            ['firmanavn' => 'Kort A', 'addr1' => 'Kortvej A', 'email' => 'ordre-A@example.invalid'],
            $this->order($a['order']),
            'name and address follow the card; email only has its own box'
        );
    }

    public function test_ticking_the_email_box_copies_only_the_email(): void
    {
        $a = $this->orderBehindItsCard('B');

        $this->save([$a['order']], [], [0]);

        $this->assertSame(
            ['firmanavn' => 'Ordre B', 'addr1' => 'Testvej 1', 'email' => 'kort-B@example.invalid'],
            $this->order($a['order'])
        );
    }

    public function test_the_ticks_stay_on_their_order_when_an_earlier_order_was_invoiced_meanwhile(): void
    {
        $first = $this->orderBehindItsCard('C1');
        $second = $this->orderBehindItsCard('C2');
        $third = $this->orderBehindItsCard('C3');
        // Invoiced in another tab after the form was opened.
        self::rows('UPDATE ordrer SET status = 3 WHERE id = $1', [$first['order']]);

        $this->save([$first['order'], $second['order'], $third['order']], [2], [1]);

        $this->assertSame('Ordre C1', $this->order($first['order'])['firmanavn'], 'the invoiced order is left alone');
        $this->assertSame(
            ['firmanavn' => 'Ordre C2', 'addr1' => 'Testvej 1', 'email' => 'kort-C2@example.invalid'],
            $this->order($second['order']),
            'row 2 only had its email box ticked'
        );
        $this->assertSame(
            ['firmanavn' => 'Kort C3', 'addr1' => 'Kortvej C3', 'email' => 'ordre-C3@example.invalid'],
            $this->order($third['order']),
            'row 3 only had its name box ticked'
        );
    }
}
