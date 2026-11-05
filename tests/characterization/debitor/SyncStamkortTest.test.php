<?php
// tests/characterization/debitor/SyncStamkortTest.test.php
//
// The arrows on the order page (debitor/ordre.php) that copy name and address
// between an order and its customer card, driven through
// debitor/sync_stamkort.php itself.
//
// Arrow up copies the order onto the card. A private card shows fornavn and
// efternavn and rebuilds firmanavn from them on save, so those two must
// follow the order's name too, or the next save of the card restores the old
// name (SST-887). A business card's fornavn/efternavn are its contact person
// and stay as they are.
//
// The ids come from the query string. Only a plain digit string is an id: an
// array such as ordre_id[]=7 used to be cast to 1 and sync order 1 instead.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261027 CL/LH SST-887 Created; the array-valued id cases were raised by CodeRabbit on PR #851.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class SyncStamkortTest extends CharacterizationTestCase
{
    /** @return array{redirect_id:int|null, warnings:list<string>} */
    private function sync(string $query): array
    {
        return $this->runChildJson('run_sync_stamkort_page.php', [$query, CharacterizationEnv::SESSION_ID]);
    }

    /** @return array<string,string|null> the card columns the sync writes, plus the name split */
    private function card(int $id): array
    {
        return self::one(
            'SELECT firmanavn, fornavn, efternavn, addr1, postnr, bynavn, email, kontotype FROM adresser WHERE id = $1',
            [$id]
        );
    }

    /**
     * A customer card with a name of its own, different from any order's.
     *
     * @return array{id:int, kontonr:string}
     */
    private function customer(string $kontotype, string $fornavn, string $efternavn, string $firmanavn): array
    {
        $debtor = self::$fx->debtor(['firmanavn' => $firmanavn]);
        self::rows(
            'UPDATE adresser SET kontotype = $1, fornavn = $2, efternavn = $3, addr1 = $4 WHERE id = $5',
            [$kontotype, $fornavn, $efternavn, 'Gammel Vej 9', $debtor['id']]
        );
        return $debtor;
    }

    public function test_sync_up_sets_first_and_last_name_on_a_private_card(): void
    {
        $debtor = $this->customer('privat', 'Old', 'Name', 'Old Name');
        $order = self::$fx->order($debtor, [], ['firmanavn' => "Anne Marie O'Brien"]);

        $out = $this->sync("ordre_id={$order['id']}&konto_id={$debtor['id']}&retning=op");

        $this->assertSame($order['id'], $out['redirect_id'], 'back to the order');
        $card = $this->card($debtor['id']);
        $this->assertSame("Anne Marie O'Brien", $card['firmanavn']);
        $this->assertSame('Anne Marie', $card['fornavn'], 'all words but the last are the first name');
        $this->assertSame("O'Brien", $card['efternavn'], 'the last word is the last name');
        $this->assertSame('Testvej 1', $card['addr1'], 'the address follows the order');
        $this->assertSame([], $out['warnings']);
    }

    public function test_sync_up_keeps_a_business_cards_contact_name(): void
    {
        $debtor = $this->customer('erhverv', 'Kontakt', 'Person', 'Gammelt Firma ApS');
        $order = self::$fx->order($debtor, [], ['firmanavn' => 'Nyt Firma ApS']);

        $out = $this->sync("ordre_id={$order['id']}&konto_id={$debtor['id']}&retning=op");

        $card = $this->card($debtor['id']);
        $this->assertSame('Nyt Firma ApS', $card['firmanavn']);
        $this->assertSame('Kontakt', $card['fornavn'], 'the contact person is not the company name');
        $this->assertSame('Person', $card['efternavn']);
        $this->assertSame([], $out['warnings']);
    }

    /** (int) of a non-empty array is 1, so ordre_id[]=N used to copy order 1 onto the card. */
    public function test_an_array_valued_order_id_syncs_nothing(): void
    {
        $debtor = $this->customer('privat', 'Old', 'Name', 'Old Name');
        $order = self::$fx->order($debtor, [], ['firmanavn' => 'New Name']);
        $before = $this->card($debtor['id']);

        $out = $this->sync("ordre_id[]={$order['id']}&konto_id={$debtor['id']}&retning=op");

        $this->assertSame(0, $out['redirect_id'], 'treated as a missing order id, not as order 1');
        $this->assertSame($before, $this->card($debtor['id']), 'the card is untouched');
        $this->assertSame([], $out['warnings']);
    }

    /** konto_id[]=N used to become customer 1, which then got the order's name and address. */
    public function test_an_array_valued_customer_id_syncs_nothing(): void
    {
        // Customer 1 has to exist for the old cast to have something to overwrite.
        if (self::one('SELECT id FROM adresser WHERE id = 1') === null) {
            self::rows(
                "INSERT INTO adresser (id, kontonr, firmanavn, addr1, art, kontotype, fornavn, efternavn)
                 VALUES (1, '90001', 'Kunde Et', 'Etvej 1', 'D', 'privat', 'Kunde', 'Et')"
            );
        }
        $customerOne = $this->card(1);
        $debtor = $this->customer('privat', 'Old', 'Name', 'Old Name');
        $order = self::$fx->order($debtor, [], ['firmanavn' => 'New Name']);
        $before = $this->card($debtor['id']);

        $out = $this->sync("ordre_id={$order['id']}&konto_id[]={$debtor['id']}&retning=op");

        $this->assertSame($order['id'], $out['redirect_id'], 'back to the order');
        $this->assertSame($customerOne, $this->card(1), 'customer 1 is untouched');
        $this->assertSame($before, $this->card($debtor['id']), 'the card is untouched');
        $this->assertSame([], $out['warnings']);
    }
}
