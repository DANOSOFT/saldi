<?php
// 20261121 CL/SS SD-833 review: order_line_has_credit_columns() looks in the current database on MySQL/MariaDB and in the current schema on PostgreSQL.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class orderLineCreditColumns extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['credit_columns_queries'] = [];

        function db_select($query, $errorFileLine = '')
        {
            $GLOBALS['credit_columns_queries'][] = $query;
            return ['n' => 2];
        }

        function db_fetch_array($result)
        {
            return $result;
        }

        require_once __DIR__ . '/../../includes/orderFuncIncludes/stockNeutralLine.php';
    }

    public function testMysqlLooksInTheCurrentDatabase(): void
    {
        $GLOBALS['db_type'] = 'mysqli';

        $this->assertTrue(order_line_has_credit_columns());
        $this->assertStringContainsString('table_schema = DATABASE()', $GLOBALS['credit_columns_queries'][0]);
        $this->assertStringNotContainsString('current_schema()', $GLOBALS['credit_columns_queries'][0]);
    }

    public function testPostgresqlLooksInTheCurrentSchema(): void
    {
        $GLOBALS['db_type'] = 'postgres';

        $this->assertTrue(order_line_has_credit_columns());
        $this->assertStringContainsString('table_schema = current_schema()', $GLOBALS['credit_columns_queries'][0]);
    }
}
