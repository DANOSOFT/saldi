<?php
// tests/characterization/debitor/CopyCustomerCardTest.test.php
//
// Regression test for the customer a case copy is built from (SST-967).
//
// debitor/ordre.php resolves the customer card once and maps it onto the copy. When the case exception admits
// the page the card is the case's customer - sager.konto_id -> adresser - and a kontonr on the request is only
// the fallback for a user who holds the Ordre right. This test pins that resolution against the real schema,
// because a source pin cannot see a column name or the art filter being wrong: the case customer is the card
// even when the request names another customer, and the request's kontonr still finds that other customer.
//
// History:
// 20261113 LOE SST-967 created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class CopyCustomerCardTest extends CharacterizationTestCase
{
    private static int $case_customer = 0;
    private static int $other_customer = 0;
    private static int $case_id = 0;
    private static string $case_kontonr = '';
    private static string $other_kontonr = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Unique per run, numeric because both columns are numeric in the books.
        self::$case_kontonr = (string) random_int(9100000, 9199999);
        self::$other_kontonr = (string) random_int(9200000, 9299999);

        self::$case_customer = self::insertCustomer(self::$case_kontonr, 'SST967 case customer');
        self::$other_customer = self::insertCustomer(self::$other_kontonr, 'SST967 other customer');

        $r = pg_query_params(self::$tenant,
            'insert into sager (konto_id, firmanavn, beskrivelse) values ($1, $2, $3) returning id',
            [self::$case_customer, 'SST967 case', 'SST967 copy customer test']);
        self::assertNotFalse($r, 'the fixture case could not be inserted: ' . pg_last_error(self::$tenant));
        self::$case_id = (int) pg_fetch_result($r, 0, 0);
    }

    private static function insertCustomer(string $kontonr, string $firmanavn): int
    {
        $r = pg_query_params(self::$tenant,
            "insert into adresser (art, kontonr, firmanavn, addr1) values ('D', $1, $2, $3) returning id",
            [$kontonr, $firmanavn, 'SST967 street 1']);
        self::assertNotFalse($r, 'the fixture customer could not be inserted: ' . pg_last_error(self::$tenant));

        return (int) pg_fetch_result($r, 0, 0);
    }

    public function testTheCopyTakesTheCaseCustomersCardNotThePostedOne(): void
    {
        // What the page does when the exception admitted it: sager.konto_id -> adresser.
        $case = self::one('select konto_id from sager where id = $1', [self::$case_id]);
        $this->assertSame(self::$case_customer, (int) ($case['konto_id'] ?? 0), 'the case has its customer');

        $card = self::one("select id, kontonr, firmanavn from adresser where id = $1 and art='D'", [self::$case_customer]);
        $this->assertNotSame([], $card, 'the case customer has an address row');
        $this->assertSame(self::$case_customer, (int) $card['id'], 'the card is the case customer');
        $this->assertSame('SST967 case customer', $card['firmanavn'], 'with that customer\'s name');
        $this->assertSame(self::$case_kontonr, $card['kontonr'], 'and that customer\'s number');
    }

    public function testThePostedKontonrStillFindsTheOtherCustomer(): void
    {
        // The fallback, and the reason the case arm has to come first: a kontonr on the request names a real,
        // different customer, so a page that read the card by it would hand the copy the stranger's details.
        $stranger = self::one("select id, firmanavn from adresser where kontonr = $1 and art='D'", [self::$other_kontonr]);
        $this->assertSame(self::$other_customer, (int) ($stranger['id'] ?? 0), 'the posted number is another customer');
        $this->assertSame('SST967 other customer', $stranger['firmanavn'] ?? '', 'with a different name');

        $card = self::one("select firmanavn from adresser where id = $1 and art='D'", [self::$case_customer]);
        $this->assertNotSame($stranger['firmanavn'] ?? '', $card['firmanavn'] ?? '',
            'the two cards must differ, or this test proves nothing');
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            pg_query_params(self::$tenant, 'delete from sager where id = $1', [self::$case_id]);
            pg_query_params(self::$tenant, 'delete from adresser where kontonr = $1', [self::$case_kontonr]);
            pg_query_params(self::$tenant, 'delete from adresser where kontonr = $1', [self::$other_kontonr]);
        }
        parent::tearDownAfterClass();
    }
}
