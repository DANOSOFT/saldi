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
// returns the competitor. Skips cleanly when the database is not reachable.
//
// History:
// 20261112 LOE SST-967 created.

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class OrderCreationIdTest extends PHPUnit\Framework\TestCase
{
    private static ?PDO $pdo = null;
    private static string $kontonr = '';
    private static int $caseA = 0;
    private static int $caseB = 0;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        // The books live in the tenant clone the harness maintains, not in the master registry.
        CharacterizationEnv::bootstrapTenantOnce();
        $host = CharacterizationEnv::pgHost();
        $user = CharacterizationEnv::pgUser();
        $pass = CharacterizationEnv::pgPass();
        $db = CharacterizationEnv::testDb();
        try {
            self::$pdo = new PDO("pgsql:host=$host;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (Throwable $e) {
            self::markTestSkipped('tenant not reachable: ' . $e->getMessage());
        }
        // Two real cases, so the competitor can be filed under a different one.
        $stmt = self::$pdo->query("select id from sager order by id limit 2");
        $cases = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($cases) < 2) {
            self::markTestSkipped('the book needs two cases for this test');
        }
        self::$caseA = (int) $cases[0];
        self::$caseB = (int) $cases[1];
        // Both the order number and the customer number are numeric in the books; the test only needs them
        // to be unique to this run.
        self::$kontonr = (string) random_int(9000000, 9999999);
    }

    private function insertOrder(int $case, string $kontonr, string $date): int
    {
        self::$pdo->prepare("insert into ordrer (ordrenr,konto_id,kontonr,art,ordredate,sag_id,firmanavn) values (?,?,?,?,?,?,?)")
            ->execute([(int) $kontonr, 1, $kontonr, 'DO', $date, $case, 'SST967 test']);

        return (int) self::$pdo->query("select currval(pg_get_serial_sequence('ordrer','id'))")->fetchColumn();
    }

    public function testTheInsertedOrderStaysOursWhenACompetitorArrives(): void
    {
        $date = date('Y-m-d');
        $own = $this->insertOrder(self::$caseA, self::$kontonr, $date);

        // A concurrent request inserts a newer order for the same customer and date, in another case.
        $competitor = $this->insertOrder(self::$caseB, self::$kontonr, $date);

        // The old lookup would hand the page the competitor.
        $stmt = self::$pdo->prepare("select id from ordrer where kontonr=? and ordredate=? order by id desc");
        $stmt->execute([self::$kontonr, $date]);
        $oldLookup = (int) $stmt->fetchColumn();
        $this->assertSame($competitor, $oldLookup, 'the customer-and-date lookup must be the race this test exists for');

        // The id taken from the insert itself is ours, not the competitor.
        $this->assertNotSame($own, $competitor, 'the fixture must produce two distinct orders');
        $this->assertSame(self::$caseA, (int) self::$pdo->query("select sag_id from ordrer where id = $own")->fetchColumn());
        $this->assertSame(self::$caseB, (int) self::$pdo->query("select sag_id from ordrer where id = $competitor")->fetchColumn());
    }

    public function testTheOrderPageTakesItsIdFromTheInsert(): void
    {
        $code = (string) @file_get_contents(__DIR__ . '/../../../debitor/ordre.php');
        $this->assertStringContainsString('function ordre_last_insert_id()', $code, 'the page has one place that reads the inserted id');
        $this->assertStringContainsString("currval(pg_get_serial_sequence('ordrer','id'))", $code, 'which uses the sequence of the insert');
        $this->assertStringContainsString('$sst967_inserted_id = ordre_last_insert_id();', $code, 'straight after the insert');
        $this->assertStringNotContainsString(
            "select id from ordrer where kontonr='\$kontonr' and ordredate='\$ordredate' order by id desc\"), __FILE__ . \" linje \" . __LINE__);\n\tif (\$row = db_fetch_array(\$query)) {\n\t\t\$id = (int) \$row['id'];\n\t\t# SST-967 this page created it",
            $code,
            'the created marker no longer comes from the customer-and-date lookup'
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo !== null) {
            self::$pdo->prepare("delete from ordrelinjer where ordre_id in (select id from ordrer where kontonr = ?)")->execute([self::$kontonr]);
            self::$pdo->prepare("delete from ordrer where kontonr = ?")->execute([self::$kontonr]);
            self::$pdo = null;
        }
    }
}
