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
        // Order 1: a set (head line plus two parts) and an ordinary line. Order 2: no set.
        pg_query($this->connection, "INSERT INTO ordrelinjer (ordre_id, antal, pris, rabat, rabatart, kostpris, samlevare) VALUES
            (1, 1, 0, 0, '', 300, 'on'),
            (1, 1, 400, 10, '', 100, ''),
            (1, 2, 150, 0, NULL, 50, NULL),
            (1, 1, 100, 5, 'amount', 40, '0'),
            (2, 2, 100, 0, '', 30, ''),
            (2, 1, 50, 10, '', 20, NULL)");
        return $this->connection;
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            pg_query($this->connection, 'DROP TABLE IF EXISTS pg_temp.ordrelinjer');
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

    /**
     * Runs the page's per-order BA query for one order.
     *
     * @return array{total_sales: string, discount: string, kostpris: string, dg: string}
     */
    private function margin(int $orderId, ?int $vis_saet): array
    {
        $line_cost = null;
        eval(self::line((string)file_get_contents(self::KASSESPOR), '$line_cost = '));
        self::assertIsString($line_cost);

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

    /** The page's rule: BA is DB over net sales rounded to øre, 0 when net sales are not positive. */
    private static function baPercent(array $row): float
    {
        $net = round((float)$row['total_sales'] - (float)$row['discount'], 2);
        return $net > 0 ? (float)$row['dg'] / $net * 100 : 0.0;
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
