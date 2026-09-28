<?php
// 20260908 CDX/LH Cover shared order-header eligibility, moved lines and copy/post lock ordering (SST-765).

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Set SALDI_CHAR_PG_DSN to an isolated PostgreSQL connection string before running.
 * No production bootstrap or credentials: every test owns a randomized schema,
 * three independent connections, and drops only its own schema during teardown.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class OrderHeaderLockRegressionTest extends TestCase {
    private $owner;
    private $waiter;
    private $observer;
    private string $schema;
    private bool $waiterPending = false;

    protected function setUp(): void {
        if (!extension_loaded('pgsql') || !getenv('SALDI_CHAR_PG_DSN')) {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN for an isolated PostgreSQL fixture.');
        }
        require_once __DIR__ . '/support/order_lock_database.php';
        require_once dirname(__DIR__, 3) . '/kreditor/orderIncludes/lockOrderForSave.php';
        $dsn = getenv('SALDI_CHAR_PG_DSN');
        // Report an unavailable fixture without echoing a potentially credential-bearing DSN.
        set_error_handler(static function () {
            throw new RuntimeException('Configured PostgreSQL connection is unavailable.');
        });
        try {
            $this->owner = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
            $this->waiter = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
            $this->observer = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        } catch (Throwable $error) {
            self::markTestSkipped('Configured PostgreSQL connection is unavailable.');
        } finally {
            restore_error_handler();
        }
        if (!$this->owner || !$this->waiter || !$this->observer) {
            self::markTestSkipped('The configured isolated PostgreSQL fixture is unavailable.');
        }
        $this->schema = 'sst765_lock_' . bin2hex(random_bytes(8));
        pg_query($this->owner, 'CREATE SCHEMA ' . $this->schema);
        foreach ([$this->owner, $this->waiter, $this->observer] as $connection) {
            pg_query($connection, 'SET search_path TO ' . $this->schema);
            pg_query($connection, "SET statement_timeout = '5s'");
        }
        pg_query($this->owner, "CREATE TABLE ordrer (id INTEGER PRIMARY KEY, status INTEGER, art TEXT);
            CREATE TABLE ordrelinjer (id INTEGER PRIMARY KEY, ordre_id INTEGER);
            INSERT INTO ordrer VALUES (10,1,'KO'),(20,2,'KK'),(30,3,'KO'),(40,4,'KO'),(50,1,'DO');
            INSERT INTO ordrelinjer VALUES (101,10),(102,10),(201,20),(401,40);");
        $GLOBALS['creditorLockConnection'] = $this->owner;
        $GLOBALS['creditorLockQueries'] = [];
    }

    protected function tearDown(): void {
        if ($this->owner) pg_query($this->owner, 'ROLLBACK');
        if ($this->waiter) {
            if ($this->waiterPending) {
                pg_cancel_query($this->waiter);
                while (pg_get_result($this->waiter) !== false) {}
            }
            pg_query($this->waiter, 'ROLLBACK');
        }
        if (isset($this->schema) && $this->observer) pg_query($this->observer, 'DROP SCHEMA ' . $this->schema . ' CASCADE');
        foreach ([$this->owner, $this->waiter, $this->observer] as $connection) {
            if ($connection) pg_close($connection);
        }
    }

    public static function eligibilityCases(): array {
        return [
            'editable source' => [10,1,null,false,[],true],
            'stale status' => [10,0,null,false,[],false],
            'missing source' => [999,1,null,false,[],false],
            'posted ordinary save' => [30,3,null,false,[],false],
            'posted copy with unrelated source line' => [40,4,null,true,[101],false],
            'posted copy with own source line' => [40,4,null,true,[401],true],
            'posted credit/copy without source lines' => [40,4,null,true,[],true],
            'posted copy stale status' => [40,3,null,true,[],false],
            'new order' => [0,0,null,false,[],true],
            'new order blank lines' => [0,0,null,false,['',null,0],true],
            'new order cannot claim existing lines' => [0,0,null,false,[101],false],
            'new order malformed line id' => [0,0,null,false,['101 OR 1=1'],false],
            'new order scalar line id' => [0,0,null,false,'101',false],
            'copy requires a source order' => [0,0,null,true,[],false],
            'split has no source' => [0,0,0,false,[],false],
            'invalid source' => [-1,0,null,false,[],false],
            'editable destination' => [10,1,20,false,[101,102],true],
            'destination posted' => [10,1,30,false,[101],false],
            'destination deleted' => [10,1,999,false,[101],false],
            'destination is debtor' => [10,1,50,false,[101],false],
            'split to new order' => [10,1,0,false,[101],true],
            'self split' => [10,1,10,false,[101],false],
            'invalid destination' => [10,1,-1,false,[101],false],
            'blank new line' => [10,1,null,false,[0,101],true],
            'empty submitted line collection' => [10,1,null,false,'',true],
            'null submitted line collection' => [10,1,null,false,null,true],
            'blank submitted line ids' => [10,1,null,false,['',null,101],true],
            'scalar submitted line collection' => [10,1,null,false,'101',false],
            'array submitted line id' => [10,1,null,false,[[101]],false],
            'duplicate source line id' => [10,1,null,false,[101,101],true],
            'line moved since form opened' => [10,1,null,false,[201],false],
            'line deleted since form opened' => [10,1,null,false,[999],false],
            'invalid line id' => [10,1,null,false,[-1],false],
            'line id SQL expression' => [10,1,null,false,['101 OR 1=1'],false],
            'fractional line id' => [10,1,null,false,['101.5'],false],
        ];
    }

    #[DataProvider('eligibilityCases')]
    public function testEligibilityUnderCallerTransaction($id, $status, $destination, $allowPosted, $lines, $expected): void {
        pg_query($this->owner, 'BEGIN');
        self::assertSame($expected, lockCreditorOrderForSave($id, $status, $destination, $allowPosted, $lines));
        self::assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->owner), 'Guard committed or aborted its caller transaction');
        self::assertSame(5, (int)pg_fetch_result(pg_query($this->owner, 'SELECT count(*) FROM ordrer'), 0, 0));
    }

    public function testReverseSplitLocksBothHeadersInIdOrder(): void {
        pg_query($this->owner, 'BEGIN');
        self::assertTrue(lockCreditorOrderForSave(20,2,10,false,[201]));
        self::assertStringContainsString('id in (10,20) order by id for update', $GLOBALS['creditorLockQueries'][0]);
        $this->startPostingRead(10);
        self::assertSame('transactionid', $this->waitForBlockedRead()['locktype']);
        pg_query($this->owner, 'COMMIT');
        self::assertSame('1', $this->finishPostingRead());
    }

    public function testWaitingPostingReadsSavedStatusAfterHeaderUnlock(): void {
        pg_query($this->owner, 'BEGIN');
        self::assertTrue(lockCreditorOrderForSave(10,1));
        $this->startPostingRead(10);
        $this->waitForBlockedRead();
        pg_query($this->owner, 'UPDATE ordrer SET status=4 WHERE id=10');
        pg_query($this->owner, 'COMMIT');
        self::assertSame('4', $this->finishPostingRead(), 'Waiting posting used a stale eligibility snapshot');
    }

    public function testCopyTakesAllocatorTableLockBeforePostingCanTakeItsRowShareLock(): void {
        pg_query($this->owner, 'BEGIN');
        self::assertTrue(lockCreditorOrderForSave(40,4,null,true));
        self::assertSame('LOCK TABLE ordrer IN EXCLUSIVE MODE', $GLOBALS['creditorLockQueries'][0]);
        $this->startPostingRead(40);
        $lock = $this->waitForBlockedRead();
        self::assertSame('relation', $lock['locktype']);
        self::assertSame('RowShareLock', $lock['mode']);
        // Re-entering the allocator lock must not deadlock with the waiting posting.
        pg_query($this->owner, 'LOCK TABLE ordrer IN EXCLUSIVE MODE');
        pg_query($this->owner, "INSERT INTO ordrer VALUES (60,0,'KK')");
        self::assertSame('0', pg_fetch_result(pg_query($this->observer, 'SELECT count(*) FROM ordrer WHERE id=60'), 0, 0));
        pg_query($this->owner, 'ROLLBACK');
        self::assertSame('4', $this->finishPostingRead());
        self::assertSame('0', pg_fetch_result(pg_query($this->observer, 'SELECT count(*) FROM ordrer WHERE id=60'), 0, 0));
    }

    private function startPostingRead(int $id): void {
        pg_query($this->waiter, 'BEGIN');
        self::assertTrue(pg_send_query($this->waiter, 'SELECT status FROM ordrer WHERE id=' . $id . ' FOR UPDATE'));
        $this->waiterPending = true;
    }

    /** @return array{locktype: string, mode: string} The lock actually blocking the independent posting connection. */
    private function waitForBlockedRead(): array {
        $pid = pg_get_pid($this->waiter);
        $deadline = microtime(true) + 2;
        do {
            $result = pg_query($this->observer, 'SELECT locktype, mode FROM pg_locks WHERE pid=' . $pid . ' AND NOT granted');
            if ($lock = pg_fetch_assoc($result)) return $lock;
            usleep(10000);
        } while (microtime(true) < $deadline);
        self::fail('Independent posting did not wait on the held header/table lock');
    }

    private function finishPostingRead(): string {
        $result = pg_get_result($this->waiter);
        self::assertSame(PGSQL_TUPLES_OK, pg_result_status($result), pg_result_error($result));
        $status = pg_fetch_result($result, 0, 0);
        while (pg_get_result($this->waiter) !== false) {}
        $this->waiterPending = false;
        pg_query($this->waiter, 'ROLLBACK');
        return $status;
    }
}
