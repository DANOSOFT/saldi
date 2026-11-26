<?php
// tests/characterization/debitor/OrderCreationIdTest.test.php
//
// Regression test for the order this page creates (SST-967).
//
// debitor/ordre.php used to find the order it had just inserted with
//
//     select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc
//
// and then mark that id as created by the request, which the case checks trust. A concurrent request
// inserting a newer order for the same customer and date therefore wins that lookup, and the page goes on
// to treat - and write to - an order from another case. The page now takes the id from the insert itself
// (currval(pg_get_serial_sequence('ordrer','id')) on PostgreSQL, mysqli_insert_id() on MySQL).
//
// This test pins the mechanism: the id captured straight after the insert stays the inserted order even
// when a competitor order with the same customer and date is inserted afterwards, while the old lookup
// returns the competitor. It runs on the shared characterization tenant and does not need the book to have
// cases: both orders carry synthetic case ids, because nothing here reads the case rows.
//
// History:
// 20261112 LOE SST-967 created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class OrderCreationIdTest extends CharacterizationTestCase
{
    private static string $kontonr = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Unique per run, and numeric because both columns are numeric in the books.
        self::$kontonr = (string) random_int(9000000, 9999999);
    }

    private static function insertOrder(int $case, string $date): int
    {
        $sql = "insert into ordrer (ordrenr,konto_id,kontonr,art,ordredate,sag_id,firmanavn)"
            . " values ($1, $2, $3, 'DO', $4, $5, $6) returning id";
        $r = pg_query_params(self::$tenant, $sql, [(int) self::$kontonr, 1, self::$kontonr, $date, $case, 'SST967 test']);
        self::assertNotFalse($r, 'the fixture order could not be inserted: ' . pg_last_error(self::$tenant));

        return (int) pg_fetch_result($r, 0, 0);
    }

    public function testTheInsertedOrderStaysOursWhenACompetitorArrives(): void
    {
        $date = date('Y-m-d');
        $own = self::insertOrder(900001, $date);

        // A concurrent request inserts a newer order for the same customer and date, in another case.
        $competitor = self::insertOrder(900002, $date);

        // The old lookup would hand the page the competitor.
        $oldLookup = self::one(
            "select id from ordrer where kontonr = $1 and ordredate = $2 order by id desc",
            [self::$kontonr, $date]
        );
        $this->assertSame($competitor, (int) ($oldLookup['id'] ?? 0),
            'the customer-and-date lookup must be the race this test exists for');
        $this->assertNotSame($own, $competitor, 'the fixture must produce two distinct orders');
        $this->assertSame(900001, (int) (self::one("select sag_id from ordrer where id = $1", [$own])['sag_id'] ?? 0));
        $this->assertSame(900002, (int) (self::one("select sag_id from ordrer where id = $1", [$competitor])['sag_id'] ?? 0));
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            pg_query_params(self::$tenant, 'delete from ordrelinjer where ordre_id in (select id from ordrer where kontonr = $1)', [self::$kontonr]);
            pg_query_params(self::$tenant, 'delete from ordrer where kontonr = $1', [self::$kontonr]);
        }
        parent::tearDownAfterClass();
    }
}
