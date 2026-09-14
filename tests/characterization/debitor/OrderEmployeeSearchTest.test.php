<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Exercise the actual order-grid query builders against PostgreSQL VALUES fixtures.
 * No tenant rows are read or changed. Credentials come from local connect.php.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class OrderEmployeeSearchTest extends TestCase
{
    private string $originalCwd;

    protected function setUp(): void
    {
        global $sqhost, $squser, $sqpass, $sqdb, $db_type, $db_encode, $connection;

        $root = dirname(__DIR__, 3);
        $this->originalCwd = getcwd();
        if (!extension_loaded('pgsql') || !is_file($root . '/includes/connect.php')) {
            self::markTestSkipped('Local PostgreSQL configuration is required.');
        }
        chdir($root . '/debitor');
        $_SERVER['REQUEST_URI'] = '/saldi/debitor/ordreliste.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        require_once $root . '/includes/connect.php';
        if (in_array($db_type, ['mysql', 'mysqli'], true)) {
            self::markTestSkipped('The order grid uses PostgreSQL SQL syntax.');
        }
        $connection = db_connect($sqhost, $squser, $sqpass, $sqdb);
        if (!$connection) {
            self::markTestSkipped('Local PostgreSQL connection is unavailable.');
        }
        require_once $root . '/includes/std_func.php';
        require_once $root . '/includes/orderFuncIncludes/grid_order.php';
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
    }

    /**
     * @return array{ids: int[], count: int} Ordered matches and grid pagination count.
     */
    private function search(array $terms, ?callable $generateSearch = null): array
    {
        global $connection;

        $name = db_escape_string("Søren O'Neil");
        $backslashName = db_escape_string('Søren\\West');
        $grid = ['query' => "SELECT o.* FROM (VALUES
            (1, '$name', 'Anna', 12.50, DATE '2026-09-11'),
            (2, 'Team $name', 'Anna', 25.00, DATE '2026-09-12'),
            (3, 'Anna', '$name', 5.00, DATE '2026-09-11'),
            (4, '$backslashName', 'Anna', 0.00, DATE '2026-09-13')
            ) AS o(id,hvem,ref,amount,ordredate)
            WHERE {{WHERE}} ORDER BY {{SORT}}"];
        $columns = [];
        foreach (['id' => 'number', 'hvem' => 'text', 'ref' => 'text', 'amount' => 'number', 'ordredate' => 'date'] as $field => $type) {
            $columns[] = [
                'field' => $field, 'sqlOverride' => 'o.' . $field, 'type' => $type,
                'searchable' => true, 'decimalPrecision' => 2,
                'generateSearch' => $generateSearch ?? 'DEFAULT_GENERATE_SEARCH',
            ];
        }
        $sql = build_query('sd186', $grid, $columns, [], $terms, 'id desc', 20, 0);
        $countSql = build_count_query($grid, $columns, [], $terms, 'id desc');
        $rows = pg_query($connection, $sql);
        $count = pg_query($connection, $countSql);
        self::assertNotFalse($rows);
        self::assertNotFalse($count);
        return [
            'ids' => array_map('intval', pg_fetch_all_columns($rows)),
            'count' => (int) pg_fetch_result($count, 0, 'total_items'),
        ];
    }

    public function testApostropheMatchesPerformedByAndPrioritizesExactName(): void
    {
        self::assertSame(['ids' => [1, 2], 'count' => 2], $this->search(['hvem' => "Søren O'Neil"]));
    }

    public function testReferenceSearchRemainsIndependent(): void
    {
        self::assertSame(['ids' => [3], 'count' => 1], $this->search(['ref' => "Søren O'Neil"]));
    }

    public function testCustomSearchReceivesUnescapedTerm(): void
    {
        $exactSearch = static function ($column, $term): string {
            return $column['sqlOverride'] . " = '" . db_escape_string($term) . "'";
        };
        self::assertSame(['ids' => [4], 'count' => 1], $this->search(['hvem' => 'Søren\\West'], $exactSearch));
    }

    public function testNonmatchingNameReturnsNoRows(): void
    {
        self::assertSame(['ids' => [], 'count' => 0], $this->search(['hvem' => "Nobody O'Neil"]));
    }

    public function testExistingNumericAndDateSearchesStillWork(): void
    {
        self::assertSame(['ids' => [2, 1], 'count' => 2], $this->search(['amount' => '>10']));
        self::assertSame(['ids' => [3, 1], 'count' => 2], $this->search(['ordredate' => '11-09-2026']));
    }
}
