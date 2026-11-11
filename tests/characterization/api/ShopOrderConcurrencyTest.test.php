<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261111 CL/MMR SST-986 Created. A shop order sent twice at the same moment (MEDshop 2026-11-08/09) got one ordrenr twice, its lines twice on one order and an empty twin,
//                 or the race check deleted the order that had the lines. insert_shop_order() now holds a per-shop-order lock around insert_shop_order_unlocked(),
//                 and the order number, the insert and the id lookup run in one transaction. The lock functions are lifted from api/rest_api.php (including the file
//                 connects to a database) and evaluated in their own namespace with stand-ins for the database calls, so the production db functions a full suite
//                 has loaded are not involved; the transaction order is asserted on the source.
// 20261111 CL/MMR SST-986 (review) A lock that is not taken (NULL, 0, a failed query) refuses the call without entering the creation body; MySQL/MariaDB use a 60-second timeout.
// 20261111 CL/MMR SST-986 (review 2) Negative ids are locked too, since the creation body accepts them; the last test ties the locked ids to the body's own check.
final class ShopOrderConcurrencyTest extends TestCase
{
    private const API = __DIR__ . '/../../../api/rest_api.php';
    private const NS = 'ShopOrderConcurrencyFixture';

    private const STUBS = <<<'PHP'
        final class Db {
            /** @var list<string> */
            public static $queries = array();
            /** @var mixed what GET_LOCK answers (MySQL/MariaDB) */
            public static $getLock = 1;
            /** @var bool whether the PostgreSQL lock query succeeds */
            public static $pgLockOk = true;
            /** @var mixed */
            public static $innerResult = 17304;
            /** @var bool */
            public static $innerThrows = false;
        }
        function db_escape_string($value) {
            return str_replace("'", "''", (string)$value);
        }
        function db_select($sql, $where = '') {
            Db::$queries[] = $sql;
            if (stripos($sql, 'GET_LOCK') !== false) {
                return new \ArrayIterator(array(array('locked' => Db::$getLock)));
            }
            if (stripos($sql, 'pg_advisory_lock') !== false) {
                return Db::$pgLockOk ? new \ArrayIterator(array(array('locked' => ''))) : false;
            }
            return new \ArrayIterator(array());
        }
        function db_fetch_array($result) {
            if (!$result || !$result->valid()) {
                return false;
            }
            $row = $result->current();
            $result->next();
            return $row;
        }
        function insert_shop_order_unlocked(...$args) {
            Db::$queries[] = 'inner:' . $args[1] . ':' . ($args[50] ?? 'DO');
            if (Db::$innerThrows) {
                throw new \RuntimeException('inner failed');
            }
            return Db::$innerResult;
        }
PHP;

    public static function setUpBeforeClass(): void
    {
        if (function_exists(self::NS . '\insert_shop_order')) {
            return;
        }
        if (!defined('SHOP_ORDER_LOCK_SECONDS')) {
            define('SHOP_ORDER_LOCK_SECONDS', 60);
        }
        $code = '';
        foreach (['shop_order_lock', 'shop_order_unlock', 'insert_shop_order'] as $fn) {
            $code .= self::functionSource($fn) . "\n";
        }
        eval('namespace ' . self::NS . ';' . self::STUBS . $code);
    }

    protected function setUp(): void
    {
        $db = self::NS . '\Db';
        $db::$queries = [];
        $db::$getLock = 1;
        $db::$pgLockOk = true;
        $db::$innerResult = 17304;
        $db::$innerThrows = false;
        $GLOBALS['db_type'] = 'postgres';
    }

    /** @return list<string> */
    private static function queries(): array
    {
        $db = self::NS . '\Db';
        return $db::$queries;
    }

    /** @return mixed */
    private static function call($shopOrderId, ?string $art = null)
    {
        $args = array_fill(0, 50, '');
        $args[1] = $shopOrderId;
        if ($art !== null) {
            $args[] = $art;
        }
        return call_user_func_array(self::NS . '\insert_shop_order', $args);
    }

    public function testTheCallRunsInsideTheLockForItsShopOrder(): void
    {
        self::assertSame(17304, self::call('2634506'));
        self::assertSame([
            "SELECT pg_advisory_lock(hashtext('shop_order'), hashtext('2634506')) AS locked",
            'inner:2634506:DO',
            "SELECT pg_advisory_unlock(hashtext('shop_order'), hashtext('2634506'))",
        ], self::queries());
    }

    public function testTheDuplicateAnswerIsPassedOnAndTheLockReleased(): void
    {
        $db = self::NS . '\Db';
        $db::$innerResult = 'Order id: 2634506 exists in saldi (internal ID: 17304)';
        self::assertSame($db::$innerResult, self::call(2634506));
        $q = self::queries();
        self::assertStringContainsString('pg_advisory_unlock', end($q));
    }

    public function testTheLockIsReleasedWhenTheCallFails(): void
    {
        $db = self::NS . '\Db';
        $db::$innerThrows = true;
        try {
            self::call('2634506');
            self::fail('the exception should reach the caller');
        } catch (RuntimeException $e) {
            self::assertSame('inner failed', $e->getMessage());
        }
        $q = self::queries();
        self::assertStringContainsString('pg_advisory_unlock', end($q));
    }

    public function testTheCreditNoteArtIsPassedOn(): void
    {
        self::call('2634506', 'DK');
        self::assertContains('inner:2634506:DK', self::queries());
    }

    /** @return array<string, array{string}> ids the creation body refuses: 0 after the cast, or not numeric */
    public static function notLockedIds(): array
    {
        return ['empty' => [''], 'zero' => ['0'], 'zero point five' => ['0.5'], 'text' => ['abc']];
    }

    #[DataProvider('notLockedIds')]
    public function testAnIdTheBodyRefusesIsNotLocked(string $id): void
    {
        self::call($id);
        self::assertSame(["inner:$id:DO"], self::queries(), 'the creation body refuses it as before');
    }

    public function testANegativeIdIsLockedLikeAPositiveOne(): void
    {
        self::assertSame(17304, self::call('-19860700'));
        self::assertSame([
            "SELECT pg_advisory_lock(hashtext('shop_order'), hashtext('-19860700')) AS locked",
            'inner:-19860700:DO',
            "SELECT pg_advisory_unlock(hashtext('shop_order'), hashtext('-19860700'))",
        ], self::queries());
    }

    public function testTheLockedIdsAreTheIdsTheBodyAccepts(): void
    {
        // insert_shop_order_unlocked() refuses a non-number, then casts to int and refuses only 0 (!$shopOrderId);
        // so a negative integer is accepted and must be locked
        $code = self::code();
        $a = strpos($code, 'function insert_shop_order_unlocked(');
        $b = strpos($code, 'function insert_shop_orderline(', (int)$a);
        $body = substr($code, (int)$a, (int)$b - (int)$a);
        self::assertStringContainsString('if (!is_numeric($shopOrderId)) {', $body);
        self::assertStringContainsString('$shopOrderId = (int)$shopOrderId;', $body);
        self::assertStringContainsString('if (!$shopOrderId || !is_integer($shopOrderId)) {', $body);
        $wrapper = self::functionSource('insert_shop_order');
        self::assertStringContainsString('$needsLock = is_numeric($shopOrderId) && (int)$shopOrderId !== 0;', $wrapper);
    }

    public function testAFailedPostgresLockRefusesTheCall(): void
    {
        $db = self::NS . '\Db';
        $db::$pgLockOk = false;
        $answer = self::call('2634506');
        self::assertSame('Error: shop order 2634506 could not be locked for creation, try again', $answer);
        self::assertNotContains('inner:2634506:DO', self::queries(), 'nothing is created');
        self::assertCount(1, self::queries(), 'no unlock for a lock that was not taken');
    }

    public function testMysqlUsesANamedLockWithAFiniteTimeout(): void
    {
        $GLOBALS['db_type'] = 'mysqli';
        self::assertSame(17304, self::call('77'));
        $q = self::queries();
        self::assertStringContainsString("GET_LOCK(CONCAT('saldi:shop_order:', MD5(DATABASE()), ':', '77'), 60)", $q[0]);
        self::assertStringNotContainsString(', -1)', $q[0]);
        self::assertSame('inner:77:DO', $q[1]);
        self::assertStringContainsString("RELEASE_LOCK(CONCAT('saldi:shop_order:', MD5(DATABASE()), ':', '77'))", $q[2]);
    }

    /** @return array<string, array{mixed}> GET_LOCK answers that mean "not locked": NULL (MariaDB, bad timeout or error), 0 (timeout) */
    public static function mysqlNotLocked(): array
    {
        return ['NULL' => [null], 'timeout 0' => [0], 'string 0' => ['0'], 'false' => [false]];
    }

    #[DataProvider('mysqlNotLocked')]
    public function testAMysqlLockThatIsNotTakenRefusesTheCall($answer): void
    {
        $GLOBALS['db_type'] = 'mysqli';
        $db = self::NS . '\Db';
        $db::$getLock = $answer;
        self::assertSame('Error: shop order 77 could not be locked for creation, try again', self::call('77'));
        self::assertCount(1, self::queries(), 'neither the creation body nor RELEASE_LOCK ran');
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
