<?php
// tests/characterization/debitor/KassesporSetCostTest.test.php
//
// SST-1017: Debitor > Kassespor summed kostpris * antal over every order line, also the set head line
// (samlevare 'on'), which carries the cost of the whole set while the parts are on the order with their own
// cost. With sets on (POS box12) the set's cost was counted twice and BA was too low; debitor/ordre.php leaves
// the head line out of the order's DB/DG. The page's two BA queries are lifted from debitor/kassespor.php and
// run against a temporary ordrelinjer table with synthetic lines.
//
// History:
// 20261117 CL/MMR SST-1017 Created.
// 20261117 CL/MMR SST-1017 The database part skips when the master-only TestDatabase helper is missing (live).
// 20261119 CL/MMR SST-1017 Review: set head matched exactly as PHP does, on PostgreSQL and on MySQL/MariaDB with a PAD SPACE collation.
//                 The MySQL test runs when SALDI_TEST_MYSQL_SOCKET is set. Negative net sales keep 0 % BA, with and without sets.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class KassesporSetCostTest extends TestCase
{
    private const KASSESPOR = __DIR__ . '/../../../debitor/kassespor.php';

    /** @var PgSql\Connection */
    private $connection;

    /** Connects to the test database and builds the synthetic ordrelinjer table; skips when no database is available. */
    private function database()
    {
        if (isset($this->connection)) {
            return $this->connection;
        }
        $helper = __DIR__ . '/../support/database/TestDatabase.php';
        if (!is_file($helper)) {
            // The database test helper is on master but not yet on live; without it the SQL cannot be run here.
            self::markTestSkipped('tests/characterization/support/database/TestDatabase.php is not available on this branch');
        }
        require_once $helper;
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $this->connection = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        pg_query($this->connection, 'DROP TABLE IF EXISTS pg_temp.ordrelinjer');
        pg_query($this->connection, 'CREATE TEMP TABLE ordrelinjer (id SERIAL PRIMARY KEY, ordre_id INTEGER, antal NUMERIC(15,3),
            pris NUMERIC(15,3), rabat NUMERIC(15,5), rabatart TEXT, kostpris NUMERIC(15,3), samlevare TEXT)');
        // Order 1: a set (head line plus two parts) and an ordinary line. Order 2: no set. Orders 3 and 4: orders 1 and 2 credited.
        pg_query($this->connection, "INSERT INTO ordrelinjer (ordre_id, antal, pris, rabat, rabatart, kostpris, samlevare) VALUES
            (1, 1, 0, 0, '', 300, 'on'),
            (1, 1, 400, 10, '', 100, ''),
            (1, 2, 150, 0, NULL, 50, NULL),
            (1, 1, 100, 5, 'amount', 40, '0'),
            (2, 2, 100, 0, '', 30, ''),
            (2, 1, 50, 10, '', 20, NULL),
            (3, -1, 0, 0, '', 300, 'on'),
            (3, -1, 400, 10, '', 100, ''),
            (3, -2, 150, 0, NULL, 50, NULL),
            (3, -1, 100, 5, 'amount', 40, '0'),
            (4, -2, 100, 0, '', 30, ''),
            (4, -1, 50, 10, '', 20, NULL)");
        return $this->connection;
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            pg_query($this->connection, 'DROP TABLE IF EXISTS pg_temp.ordrelinjer');
            pg_query($this->connection, 'DROP TABLE IF EXISTS pg_temp.set_values');
        }
    }

    /** Both BA queries in the page are identical, so the list and the per-order view treat a set head the same. */
    public function testBothBaQueriesUseTheSameLineCost(): void
    {
        [$list, $perOrder] = self::queries();
        self::assertSame(self::normalised($list), self::normalised($perOrder));
        self::assertStringContainsString('SUM($line_cost * antal)', $list);
        self::assertStringContainsString('- $line_cost', $list);
        self::assertStringNotContainsString('SUM(kostpris * antal)', $list);
    }

    public function testWithSetsOnTheSetHeadCostIsLeftOut(): void
    {
        $row = $this->margin(1, 1);
        // Sales 800, discount 40 + 5 = 45. Cost of the parts and the ordinary line only: 100 + 2 * 50 + 40 = 240.
        self::assertEqualsWithDelta(800.0, (float)$row['total_sales'], 0.0001);
        self::assertEqualsWithDelta(45.0, (float)$row['discount'], 0.0001);
        self::assertEqualsWithDelta(240.0, (float)$row['kostpris'], 0.0001);
        self::assertEqualsWithDelta(515.0, (float)$row['dg'], 0.0001);
        self::assertEqualsWithDelta(68.21, round(self::baPercent($row), 2), 0.0001);
        // Same figure as the order page: sum minus the cost of the lines that are not a set head.
        self::assertEqualsWithDelta(755.0 - 240.0, (float)$row['dg'], 0.0001);
    }

    public function testWithSetsOffEveryLineCostCounts(): void
    {
        // debitor/ordre.php counts the samlevare line too when sets are off: if (!$samlevare[$x] || !$vis_saet).
        $row = $this->margin(1, null);
        self::assertEqualsWithDelta(540.0, (float)$row['kostpris'], 0.0001);
        self::assertEqualsWithDelta(215.0, (float)$row['dg'], 0.0001);
    }

    public function testAnOrderWithoutASetIsUnchanged(): void
    {
        $on = $this->margin(2, 1);
        $off = $this->margin(2, null);
        self::assertEqualsWithDelta(80.0, (float)$on['kostpris'], 0.0001);
        self::assertEqualsWithDelta(165.0, (float)$on['dg'], 0.0001);
        self::assertSame($off, $on);
    }

    /**
     * SST-1017's acceptance criteria keep 0 % BA when net sales are zero or negative. A credit note still gets the
     * set-cost correction in its DB, but BA stays 0 %, with a set and without, sets on and off.
     */
    public function testNegativeNetSalesStillShowZeroBa(): void
    {
        $creditedSet = $this->margin(3, 1);
        self::assertEqualsWithDelta(-515.0, (float)$creditedSet['dg'], 0.0001);
        self::assertSame(0.0, self::baPercent($creditedSet));
        self::assertSame(0.0, self::baPercent($this->margin(3, null)));
        $creditedPlain = $this->margin(4, 1);
        self::assertEqualsWithDelta(-165.0, (float)$creditedPlain['dg'], 0.0001);
        self::assertSame(0.0, self::baPercent($creditedPlain));
        self::assertSame(0.0, self::baPercent($this->margin(4, null)));
        self::assertEqualsWithDelta(68.21, round(self::baPercent($this->margin(1, 1)), 2), 0.0001);
    }

    /** SST-835 still holds: net sales that round to 0 (an exchange order's sub-øre remainder) give 0 %, not a huge figure. */
    public function testNetSalesThatRoundToZeroGiveZeroBa(): void
    {
        self::assertSame(0.0, (float)self::baPercent(['total_sales' => '0.00084', 'discount' => '0', 'dg' => '-12.5']));
        self::assertSame(0.0, (float)self::baPercent(['total_sales' => '0', 'discount' => '0', 'dg' => '0']));
    }

    /** ordre.php decides in PHP (!$samlevare); the page's SQL must leave out exactly the same lines' cost. */
    public function testSetHeadIsMatchedAsPhpDoesOnPostgres(): void
    {
        $connection = $this->database();
        pg_query($connection, 'DROP TABLE IF EXISTS pg_temp.set_values');
        pg_query($connection, 'CREATE TEMP TABLE set_values (n INTEGER, samlevare VARCHAR(2), kostpris NUMERIC(15,3))');
        foreach (self::SET_VALUES as $n => $value) {
            pg_query_params($connection, 'INSERT INTO set_values VALUES ($1, $2, 10)', [$n, $value]);
        }
        $result = pg_query($connection, 'SELECT n, ' . self::lineCost(1) . ' AS cost FROM set_values ORDER BY n');
        self::assertNotFalse($result, pg_last_error($connection));
        $costs = array_column(pg_fetch_all($result), 'cost', 'n');
        self::assertSetCostsMatchPhp($costs);
    }

    /**
     * MySQL/MariaDB with a PAD SPACE collation (utf8mb4_general_ci) compare ' ' equal to '' and '0 ' equal to '0';
     * the page must still treat those as a set head, as PHP does. Needs a MySQL/MariaDB server: CI has none, so
     * this test is on the skip allowlist in tools/ci/check-skips.php and runs where SALDI_TEST_MYSQL_SOCKET is set.
     */
    public function testSetHeadIsMatchedAsPhpDoesOnMysqlPadSpace(): void
    {
        $socket = getenv('SALDI_TEST_MYSQL_SOCKET');
        if (!extension_loaded('mysqli') || $socket === false || $socket === '') {
            self::markTestSkipped('needs the mysqli extension and SALDI_TEST_MYSQL_SOCKET (a MySQL/MariaDB test server)');
        }
        $user = getenv('SALDI_TEST_MYSQL_USER') ?: 'root';
        $password = getenv('SALDI_TEST_MYSQL_PASS') ?: '';
        $db = new mysqli('localhost', $user, $password, '', 0, $socket);
        $schema = 'saldi_kassespor_test_' . getmypid();
        $db->query("CREATE DATABASE $schema");
        try {
            $db->select_db($schema);
            $db->query('CREATE TEMPORARY TABLE set_values (n INT, samlevare VARCHAR(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, kostpris DECIMAL(15,3))');
            $insert = $db->prepare('INSERT INTO set_values VALUES (?, ?, 10)');
            foreach (self::SET_VALUES as $n => $value) {
                $insert->bind_param('is', $n, $value);
                $insert->execute();
            }
            // The collation really pads: ' ' (key 4) compares equal to ''.
            self::assertSame('1', (string)$db->query("SELECT COUNT(*) FROM set_values WHERE samlevare = '' AND n = 4")->fetch_row()[0]);
            $costs = [];
            $result = $db->query('SELECT n, ' . self::lineCost(1) . ' AS cost FROM set_values ORDER BY n');
            while ($row = $result->fetch_assoc()) {
                $costs[$row['n']] = $row['cost'];
            }
        } finally {
            $db->query("DROP DATABASE $schema");
            $db->close();
        }
        self::assertSetCostsMatchPhp($costs);
    }

    /** udskriv() puts $sort into ORDER BY, so only a known column, optionally followed by desc, gets through. */
    public function testSortIsLimitedToKnownColumns(): void
    {
        $src = (string)file_get_contents(self::KASSESPOR);
        self::assertSame(1, preg_match('/\t\$sort_parts = explode.*?\n\t\}\n/s', $src, $m), 'sort guard not found in udskriv()');
        $guard = $m[0];
        $cases = [
            'fakturanr' => 'fakturanr',
            'sum desc' => 'sum desc',
            'tidspkt DESC' => 'tidspkt desc',
            'logdate' => 'logdate',
            "id; drop table ordrer" => 'tidspkt desc',
            "fakturanr desc, (select 1)" => 'fakturanr',
            "(case when true then id end)" => 'tidspkt desc',
            '' => 'tidspkt desc',
        ];
        foreach ($cases as $input => $expected) {
            $sort = $input;
            eval($guard);
            self::assertSame($expected, $sort, "sort input: $input");
        }
    }

    /** The table filter is NULL when the page opens without a saved Kassespor cookie; escaping it must not warn. */
    public function testAnEmptyTableFilterIsEscapedWithoutADeprecation(): void
    {
        $src = (string)file_get_contents(self::KASSESPOR);
        $line = trim(self::line($src, "select id from table_plan where name="));
        self::assertSame(1, preg_match('/db_select\((".*?")\s*,\s*__FILE__/', $line, $m), 'table_plan query not found');
        set_error_handler(static function (int $no, string $msg): never {
            throw new ErrorException($msg, 0, $no);
        });
        try {
            $escape = static fn (string $txt): string => str_replace("'", "''", $txt);
            foreach ([null => "select id from table_plan where name=''", "Bord 1" => "select id from table_plan where name='Bord 1'"] as $input => $expected) {
                $borde = ($input === '') ? null : $input;
                $sql = eval('return ' . str_replace('db_escape_string(', '$escape(', $m[1]) . ';');
                self::assertSame($expected, $sql);
            }
            $borde = "x' OR TRUE --";
            $sql = eval('return ' . str_replace('db_escape_string(', '$escape(', $m[1]) . ';');
            self::assertSame("select id from table_plan where name='x'' OR TRUE --'", $sql);
        } finally {
            restore_error_handler();
        }
    }

    /** samlevare values: unset or falsy in PHP (cost counts) and truthy in PHP (a set head, cost left out). */
    private const SET_VALUES = [0 => null, 1 => '', 2 => '0', 3 => 'on', 4 => ' ', 5 => '0 ', 6 => '1', 7 => '00', 8 => '  '];

    /** @param array<int|string, string|null> $costs cost per SET_VALUES key as returned by the database */
    private static function assertSetCostsMatchPhp(array $costs): void
    {
        self::assertCount(count(self::SET_VALUES), $costs);
        foreach (self::SET_VALUES as $n => $value) {
            $expected = !$value ? 10.0 : 0.0;
            self::assertEqualsWithDelta($expected, (float)$costs[$n], 0.0001, 'samlevare ' . var_export($value, true));
        }
    }

    /** The page's $line_cost expression for the given $vis_saet. */
    private static function lineCost(?int $vis_saet): string
    {
        $line_cost = null;
        eval(self::line((string)file_get_contents(self::KASSESPOR), '$line_cost = '));
        self::assertIsString($line_cost);
        return $line_cost;
    }

    /**
     * Runs the page's per-order BA query for one order.
     *
     * @return array{total_sales: string, discount: string, kostpris: string, dg: string}
     */
    private function margin(int $orderId, ?int $vis_saet): array
    {
        $line_cost = self::lineCost($vis_saet);

        $id = [$orderId];
        $x = 0;
        $sql = eval('return "' . self::queries()[1] . '";');
        $connection = $this->database();
        $result = pg_query($connection, $sql);
        self::assertNotFalse($result, pg_last_error($connection));
        $row = pg_fetch_assoc($result);
        self::assertIsArray($row);
        return $row;
    }

    /** The page's own BA rule, lifted from udskriv(): DB over net sales rounded to øre (afrund as round here). */
    private static function baPercent(array $row): float
    {
        $src = (string)file_get_contents(self::KASSESPOR);
        self::assertSame(1, preg_match('/\t\$net_sales = afrund\(.*?\$dg_percent = 0;\s*\}/s', $src, $m), 'BA rule not found in udskriv()');
        $afrund = static fn ($tal, $decimaler): float => round((float)$tal, (int)$decimaler);
        $q_dg = $row;
        $dg_percent = null;
        eval(str_replace('afrund(', '$afrund(', $m[0]));
        return (float)$dg_percent;
    }

    /** @return array{0: string, 1: string} the list query and the per-order query, as PHP string bodies */
    private static function queries(): array
    {
        $src = (string)file_get_contents(self::KASSESPOR);
        preg_match_all('/db_select\("(\s*SELECT.*?FROM ordrelinjer\s+WHERE ordre_id = \'\{\$id\[\$x\]\}\')\s*"/s', $src, $m);
        self::assertCount(2, $m[1], 'expected two BA queries in debitor/kassespor.php');
        return [$m[1][0], $m[1][1]];
    }

    private static function normalised(string $sql): string
    {
        return (string)preg_replace('/\s+/', ' ', trim($sql));
    }

    /** The single source line that contains $needle. */
    private static function line(string $src, string $needle): string
    {
        $hits = array_values(array_filter(explode("\n", $src), static fn ($l) => strpos($l, $needle) !== false));
        self::assertCount(1, $hits, "expected one line with $needle in debitor/kassespor.php");
        return $hits[0];
    }
}
