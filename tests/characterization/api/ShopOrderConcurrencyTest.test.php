<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261111 CL/MMR SST-986 Created. A shop order sent twice at the same moment (MEDshop 2026-11-08/09) got one ordrenr twice, its lines twice on one order and an empty twin,
//                 or the race check deleted the order that had the lines. insert_shop_order() now holds a per-shop-order lock around insert_shop_order_unlocked(),
//                 and the order number, the insert and the id lookup run in one transaction. The lock functions are lifted from api/rest_api.php with stand-ins
//                 for the database calls (including the file connects to a database); the transaction order is asserted on the source.
final class ShopOrderConcurrencyTest extends TestCase
{
    private const API = __DIR__ . '/../../../api/rest_api.php';

    /** @var list<string> */
    public static array $queries = [];
    /** @var mixed */
    public static $innerResult = null;
    public static bool $innerThrows = false;

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('db_select')) {
            eval('function db_select($q, $where = "") { ShopOrderConcurrencyTest::$queries[] = $q; return true; }');
        }
        if (!function_exists('db_fetch_array')) {
            eval('function db_fetch_array($q) { return array("locked" => 1); }');
        }
        if (!function_exists('db_escape_string')) {
            eval('function db_escape_string($s) { return addslashes((string)$s); }');
        }
        if (!function_exists('insert_shop_order_unlocked')) {
            eval('function insert_shop_order_unlocked(...$args) { ShopOrderConcurrencyTest::$queries[] = "inner:" . $args[1] . ":" . ($args[50] ?? "DO"); if (ShopOrderConcurrencyTest::$innerThrows) throw new RuntimeException("inner failed"); return ShopOrderConcurrencyTest::$innerResult; }');
        }
        foreach (['shop_order_lock', 'shop_order_unlock', 'insert_shop_order'] as $fn) {
            if (!function_exists($fn)) {
                eval(self::functionSource($fn));
            }
        }
    }

    protected function setUp(): void
    {
        self::$queries = [];
        self::$innerResult = 17304;
        self::$innerThrows = false;
        $GLOBALS['db_type'] = 'postgres';
    }

    /** @return list<mixed> insert_shop_order()'s 50 arguments with the shop order id in place */
    private static function args($shopOrderId): array
    {
        $args = array_fill(0, 50, '');
        $args[1] = $shopOrderId;
        return $args;
    }

    public function testTheCallRunsInsideTheLockForItsShopOrder(): void
    {
        self::assertSame(17304, insert_shop_order(...self::args('2634506')));
        self::assertSame([
            "SELECT pg_advisory_lock(hashtext('shop_order'), hashtext('2634506'))",
            'inner:2634506:DO',
            "SELECT pg_advisory_unlock(hashtext('shop_order'), hashtext('2634506'))",
        ], self::$queries);
    }

    public function testTheDuplicateAnswerIsPassedOnAndTheLockReleased(): void
    {
        self::$innerResult = 'Order id: 2634506 exists in saldi (internal ID: 17304)';
        self::assertSame(self::$innerResult, insert_shop_order(...self::args(2634506)));
        self::assertStringContainsString('pg_advisory_unlock', end(self::$queries));
    }

    public function testTheLockIsReleasedWhenTheCallFails(): void
    {
        self::$innerThrows = true;
        try {
            insert_shop_order(...self::args('2634506'));
            self::fail('the exception should reach the caller');
        } catch (RuntimeException $e) {
            self::assertSame('inner failed', $e->getMessage());
        }
        self::assertStringContainsString('pg_advisory_unlock', end(self::$queries));
    }

    public function testTheCreditNoteArtIsPassedOn(): void
    {
        $args = self::args('2634506');
        $args[] = 'DK';
        insert_shop_order(...$args);
        self::assertContains('inner:2634506:DK', self::$queries);
    }

    public function testNoLockWithoutAShopOrderId(): void
    {
        foreach (['', '0', 'abc'] as $id) {
            self::$queries = [];
            insert_shop_order(...self::args($id));
            self::assertSame(["inner:$id:DO"], self::$queries, "id '$id'");
        }
    }

    public function testMysqlUsesANamedLockPerDatabase(): void
    {
        $GLOBALS['db_type'] = 'mysqli';
        insert_shop_order(...self::args('77'));
        self::assertStringContainsString("GET_LOCK(CONCAT('saldi:shop_order:', MD5(DATABASE()), ':', '77'), -1)", self::$queries[0]);
        self::assertStringContainsString("RELEASE_LOCK(CONCAT('saldi:shop_order:', MD5(DATABASE()), ':', '77'))", self::$queries[2]);
    }

    public function testNumberInsertAndIdAreOneTransaction(): void
    {
        // the body has braces inside strings, so it is cut at the next function instead of by brace counting
        $code = self::code();
        $a = strpos($code, 'function insert_shop_order_unlocked(');
        $b = strpos($code, 'function insert_shop_orderline(', (int)$a);
        self::assertNotFalse($a);
        self::assertNotFalse($b);
        $body = substr($code, $a, $b - $a);
        $begin = strpos($body, "transaktion('begin');");
        $number = strpos($body, 'get_next_order_number("DO", false)');
        $insert = strpos($body, '$qtxt = "insert into ordrer ";');
        $lookup = strpos($body, 'select id from ordrer where ordrenr=');
        $commit = strpos($body, "transaktion('commit');");
        self::assertNotFalse($begin);
        self::assertTrue($begin < $number && $number < $insert && $insert < $lookup && $lookup < $commit, 'begin, number, insert, id, commit in that order');
        self::assertSame(1, substr_count($body, 'get_next_order_number("'), 'the number is taken once, inside the transaction');
        self::assertStringNotContainsString('get_next_order_number("DO");', $body);
    }

    private static function code(): string
    {
        return (string)file_get_contents(self::API);
    }

    private static function functionSource(string $fn): string
    {
        $code = self::code();
        $a = strpos($code, "function $fn(");
        self::assertNotFalse($a, "$fn() is gone from api/rest_api.php");
        $depth = 0;
        for ($i = strpos($code, '{', $a); $i < strlen($code); $i++) {
            if ($code[$i] === '{') $depth++;
            if ($code[$i] === '}' && --$depth === 0) {
                return substr($code, $a, $i - $a + 1);
            }
        }
        self::fail("$fn() has no end");
    }
}
