<?php
// tests/characterization/debitor/AnonymizeAccountTest.test.php
//
// Anonymisér on the debtor card (debitor/anonymize.php) removes the customer's
// name, address, phone and e-mail from the card, from its delivery addresses
// and from every order, and touches no other customer.
//
// The button's confirmation says "Navn, adresse og telefonnummer fjernes på
// dette kort og på alle ordrer, rykkere mm.", but anonymize.php blanked only
// the name and address columns. The card's delivery phone and e-mail
// (adresser.lev_tlf, lev_email), every row of delivery_addresses and the
// orders' lev_email and contact phone (kontakt_tlf) stayed: customer C's
// Strandvejen 101, 2900 Hellerup, 39 61 22 33 and c.levering@test.invalid
// were all still there after Anonymisér (testlab round 2,
// customer-lifecycle DEVY-7, on master and on the candidate).
//
// The page is posted the way the card's Anonymisér button posts it. The
// branch reads only the account id and the button, so the test posts those.
// The card the page then draws is checked too: none of the old details may
// show on it.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class AnonymizeAccountTest extends CharacterizationTestCase
{
    private const PAGE = 'debitor/debitorkort.php';
    private const BUTTON = 'Anonymisér';

    /**
     * The customer the lab anonymized (C), and another customer (D) that must be left alone: name, card phone and
     * mobile, primary delivery address on the card and in delivery_addresses, a second delivery address, an extra
     * e-mail and the contact phone the orders carry. No value is shared, and each fits its column.
     */
    private const CUSTOMERS = [
        'C' => [
            'firmanavn' => 'Lis Ejlersen', 'email' => 'c.kunde@test.invalid', 'tlf' => '+4540874727', 'mobile' => '20304050',
            'lev_firmanavn' => 'Lis Ejlersen Levering', 'lev_addr1' => 'Strandvejen 101', 'lev_postnr' => '2900', 'lev_bynavn' => 'Hellerup',
            'lev_tlf' => '39 61 22 33', 'lev_email' => 'c.levering@test.invalid',
            'second_addr1' => 'Klitvej 7', 'second_tlf' => '49 70 11 22', 'second_email' => 'c.sommerhus@test.invalid',
            'extra_email' => 'c.ekstra@test.invalid', 'kontakt_tlf' => '40 87 47 27',
        ],
        'D' => [
            'firmanavn' => 'Dorte Damgaard', 'email' => 'd.kunde@test.invalid', 'tlf' => '+4586123456', 'mobile' => '21314151',
            'lev_firmanavn' => 'Dorte Damgaard Lager', 'lev_addr1' => 'Havnegade 12', 'lev_postnr' => '8000', 'lev_bynavn' => 'Aarhus C',
            'lev_tlf' => '86 12 34 99', 'lev_email' => 'd.levering@test.invalid',
            'second_addr1' => 'Skovvej 3', 'second_tlf' => '86 98 76 54', 'second_email' => 'd.filial@test.invalid',
            'extra_email' => 'd.ekstra@test.invalid', 'kontakt_tlf' => '41 22 33 44',
        ],
    ];

    /** The columns of the card that hold the customer's name, address, phone or e-mail. */
    private const CARD_COLUMNS = [
        'addr1', 'addr2', 'postnr', 'bynavn', 'kontakt', 'tlf', 'mobile', 'email',
        'lev_firmanavn', 'lev_fornavn', 'lev_efternavn', 'lev_addr1', 'lev_addr2', 'lev_postnr', 'lev_bynavn',
        'lev_kontakt', 'lev_tlf', 'lev_email',
    ];

    /** The columns of an order that hold them. */
    private const ORDER_COLUMNS = [
        'addr1', 'addr2', 'postnr', 'bynavn', 'kontakt', 'phone', 'kontakt_tlf', 'email',
        'lev_navn', 'lev_addr1', 'lev_addr2', 'lev_postnr', 'lev_bynavn', 'lev_kontakt', 'lev_email',
    ];

    /** @var list<int> adresser.id of the accounts a test made */
    private array $accounts = [];

    protected function tearDown(): void
    {
        foreach ($this->accounts as $id) {
            self::rows('DELETE FROM ordrelinjer WHERE ordre_id IN (SELECT id FROM ordrer WHERE konto_id = $1)', [$id]);
            self::rows('DELETE FROM ordrer WHERE konto_id = $1', [$id]);
            self::rows('DELETE FROM kontakt_emails WHERE konto_id = $1', [$id]);
            self::rows('DELETE FROM delivery_addresses WHERE account_id = $1', [$id]);
            self::rows('DELETE FROM ansatte WHERE konto_id = $1', [$id]);
            self::rows('DELETE FROM adresser WHERE id = $1', [$id]);
        }
        $this->accounts = [];
    }

    /**
     * A business customer with a phone, a mobile, a primary delivery address on the card and in delivery_addresses,
     * a second delivery address, an extra e-mail, a contact person, an order and a reminder, both delivered to the
     * primary address.
     *
     * @param array<string, string> $v one of self::CUSTOMERS
     * @return array{id:int, kontonr:string}
     */
    private function customer(array $v): array
    {
        $debtor = self::$fx->debtor(['firmanavn' => $v['firmanavn'], 'email' => $v['email']]);
        $this->accounts[] = $debtor['id'];

        self::rows(
            "UPDATE adresser SET kontotype = 'erhverv', tlf = $2, mobile = $3, kontakt = $4, lev_firmanavn = $5, lev_addr1 = $6,
                    lev_postnr = $7, lev_bynavn = $8, lev_kontakt = $4, lev_tlf = $9, lev_email = $10
              WHERE id = $1",
            [$debtor['id'], $v['tlf'], $v['mobile'], $v['firmanavn'], $v['lev_firmanavn'], $v['lev_addr1'],
             $v['lev_postnr'], $v['lev_bynavn'], $v['lev_tlf'], $v['lev_email']]
        );
        self::rows(
            "INSERT INTO delivery_addresses (account_id, is_primary, sort_order, description, company_name, address_line1,
                                             postal_code, city, contact_name, phone, email)
             VALUES ($1, true, 1, 'Levering', $2, $3, $4, $5, $6, $7, $8),
                    ($1, false, 2, 'Sommerhus', $2, $9, '3100', 'Hornbæk', $6, $10, $11)",
            [$debtor['id'], $v['lev_firmanavn'], $v['lev_addr1'], $v['lev_postnr'], $v['lev_bynavn'], $v['firmanavn'],
             $v['lev_tlf'], $v['lev_email'], $v['second_addr1'], $v['second_tlf'], $v['second_email']]
        );
        self::rows("INSERT INTO kontakt_emails (konto_id, email, email_type) VALUES ($1, $2, '')", [$debtor['id'], $v['extra_email']]);
        self::rows('INSERT INTO ansatte (konto_id, navn, posnr) VALUES ($1, $2, 1)', [$debtor['id'], $v['firmanavn']]);

        foreach (['DO', 'R1'] as $art) {
            $order = self::$fx->order($debtor, [], ['art' => $art, 'firmanavn' => $v['firmanavn'], 'email' => $v['email']]);
            self::rows(
                'UPDATE ordrer SET phone = $2, kontakt = $3, kontakt_tlf = $4, lev_navn = $5, lev_addr1 = $6, lev_postnr = $7,
                        lev_bynavn = $8, lev_kontakt = $3, lev_email = $9
                  WHERE id = $1',
                [$order['id'], $v['tlf'], $v['firmanavn'], $v['kontakt_tlf'], $v['lev_firmanavn'], $v['lev_addr1'], $v['lev_postnr'],
                 $v['lev_bynavn'], $v['lev_email']]
            );
        }
        return $debtor;
    }

    /** @return array{html:string, alerts:list<string>, warnings:list<string>, posted:list<string>} */
    private function anonymize(int $id): array
    {
        $body = http_build_query(['id' => (string)$id, 'anonymize' => self::BUTTON]);
        return $this->runChildJson('run_account_card_page.php', [self::PAGE, '', $body, CharacterizationEnv::SESSION_ID]);
    }

    /** @return array<string, string|null> */
    private static function card(int $id): array
    {
        $row = self::one('SELECT * FROM adresser WHERE id = $1', [$id]);
        self::assertNotNull($row, "account $id exists");
        return $row;
    }

    public function test_the_card_offers_anonymiser_for_a_customer_with_orders(): void
    {
        $c = $this->customer(self::CUSTOMERS['C']);

        $res = $this->runChildJson('run_account_card_page.php', [self::PAGE, 'id=' . $c['id'], '', CharacterizationEnv::SESSION_ID]);

        $this->assertMatchesRegularExpression("/name='anonymize' value='" . self::BUTTON . "'/u", $res['html'], 'the card offers Anonymisér');
    }

    public function test_anonymiser_removes_name_address_phone_and_email_from_the_card_its_delivery_addresses_and_its_orders(): void
    {
        $c = $this->customer(self::CUSTOMERS['C']);
        $other = $this->customer(self::CUSTOMERS['D']);
        $otherCard = self::card($other['id']);
        $otherDelivery = self::rows('SELECT * FROM delivery_addresses WHERE account_id = $1 ORDER BY id', [$other['id']]);
        $otherOrders = self::rows('SELECT * FROM ordrer WHERE konto_id = $1 ORDER BY id', [$other['id']]);

        $res = $this->anonymize($c['id']);

        $card = self::card($c['id']);
        $this->assertSame('Anonym', $card['firmanavn']);
        foreach (self::CARD_COLUMNS as $column) {
            $this->assertSame('', (string)$card[$column], "adresser.$column is removed");
        }
        $this->assertSame(
            [],
            self::rows('SELECT id, address_line1, phone, email FROM delivery_addresses WHERE account_id = $1', [$c['id']]),
            'the delivery addresses are removed'
        );
        $this->assertSame('0', self::one('SELECT count(*) AS n FROM kontakt_emails WHERE konto_id = $1', [$c['id']])['n'], 'the extra e-mails are removed');
        $this->assertSame('0', self::one('SELECT count(*) AS n FROM ansatte WHERE konto_id = $1', [$c['id']])['n'], 'the contact persons are removed');

        $orders = self::rows('SELECT * FROM ordrer WHERE konto_id = $1 ORDER BY id', [$c['id']]);
        $this->assertCount(2, $orders, 'the order and the reminder keep their customer');
        foreach ($orders as $order) {
            $this->assertSame('Anonym', $order['firmanavn'], "order {$order['art']}");
            foreach (self::ORDER_COLUMNS as $column) {
                $this->assertSame('', (string)$order[$column], "ordrer.$column of the {$order['art']} is removed");
            }
        }

        // The card the page draws after Anonymisér shows none of it.
        $this->assertStringContainsString('Anonym', $res['html'], 'the card is drawn again');
        foreach (['firmanavn', 'email', 'tlf', 'mobile', 'lev_addr1', 'lev_tlf', 'lev_email', 'second_addr1', 'second_tlf', 'second_email', 'extra_email'] as $what) {
            $this->assertStringNotContainsString(self::CUSTOMERS['C'][$what], $res['html'], "the card no longer shows $what");
        }
        $this->assertSame([], array_values(array_filter($res['warnings'], static fn(string $w): bool => str_starts_with($w, 'debitor/anonymize.php:'))));

        // Another customer is left as it was.
        $this->assertSame($otherCard, self::card($other['id']));
        $this->assertSame($otherDelivery, self::rows('SELECT * FROM delivery_addresses WHERE account_id = $1 ORDER BY id', [$other['id']]));
        $this->assertSame($otherOrders, self::rows('SELECT * FROM ordrer WHERE konto_id = $1 ORDER BY id', [$other['id']]));
    }
}
