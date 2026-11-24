<?php
// tests/characterization/includes/SettingsCompositeRowLockTest.test.php
//
// How SettingsService::saveRaw() reads a stored value under its lock on MySQL/MariaDB and PostgreSQL, when the stored row
// is missing and when a locking read fails (p1-settings-posting-F1r3). A composite is several settings in one stored
// value, such as Omgående bogføring of sales and of purchase orders in grupper DIV/3 box5 ("<sales>;<purchases>").
//
// An older ledger may have no DIV/3 row. A locking read (select ... for update) of no row locks nothing, so two first saves
// at the same time each inserted a row, 'on;' and ';on', and the readers take one of them: one setting that was switched
// on read as off. A row the request read as missing is now created under its named lock, which the save takes before its
// transaction begins (PostgreSQL pg_advisory_lock(), MySQL and MariaDB GET_LOCK() through saldiNamedLockWait()) and lets go
// only after it has committed. A save that waits for the lock has no snapshot and holds no row lock yet, so once it has the
// lock it sees the row the other save created, also under repeatable read, and on MySQL and MariaDB a gap lock of its own
// cannot block the other save's insert while InnoDB does not see the wait. A row that is gone when the save reads it under
// its row lock is created under the named lock taken inside the transaction (PostgreSQL pg_advisory_xact_lock()).
// SettingsCompositeSaveRaceTest runs two saves at the same time on PostgreSQL.
//
// The DIV/3 row holds settings of their own as well (box4 Hurtigfakturering, box9 negative stock). The first save of any of
// them creates the row, so a save of one of them at the same time as a save of Omgående bogføring also inserted a second
// row. Such a setting is now saved the same way when the request found no row: in a transaction, under the row's named
// lock, and it writes into the row the other save created. With the row there it is saved as before, without a lock.
//
// On MySQL and MariaDB a failed statement does not end the transaction, so a locking read that failed (a lock wait timeout)
// read as no row, and the save inserted a second row. Now a failed read or lock writes nothing and rolls back.
//
// CI has no MySQL or MariaDB: support/settings_lock_runner.php plays what those servers answer, against the real registry.
//
// History:
// 20261120 CL/ASR Created (p1-settings-posting-F1r3).
// 20261120 CL/ASR The named lock of a row the request read as missing is taken before the transaction; a setting of its own in DIV/3 (Hurtigfakturering, box4) without a row is saved under it too (fix round 7, review round 1).

use PHPUnit\Framework\TestCase;

final class SettingsCompositeRowLockTest extends TestCase
{
    private const SALES = 'sales.orders.post_sales_immediately';
    private const PURCHASES = 'purchase.orders.post_immediately';
    private const QUICK = 'sales.orders.quick_invoice';

    private const PRELOAD = "select: select * from grupper where (art = 'DIV' and kodenr = '3') order by (coalesce(fiscal_year, 0) = 1) desc, id";
    private const READ = "select: select * from grupper where art = 'DIV' and kodenr = '3' order by (coalesce(fiscal_year, 0) = 1) desc, id for update";
    private const GET_LOCK = "select: select get_lock(concat('saldi:settings:', md5(concat(database(), ':', 'grupper:DIV|3'))), 30) as locked";
    private const RELEASE_LOCK = "select: select release_lock(concat('saldi:settings:', md5(concat(database(), ':', 'grupper:DIV|3'))))";
    private const PG_LOCK = "select: select pg_advisory_lock(hashtext('saldi:settings'), hashtext('grupper:DIV|3'))";
    private const PG_XACT_LOCK = "select: select pg_advisory_xact_lock(hashtext('saldi:settings'), hashtext('grupper:DIV|3'))";
    private const PG_UNLOCK = "select: select pg_advisory_unlock(hashtext('saldi:settings'), hashtext('grupper:DIV|3'))";
    private const INSERT_SALES = "modify: insert into grupper (beskrivelse, kodenr, art, box5) values ('Div_valg (Ordrer)', '3', 'DIV', 'on;')";
    private const UPDATE_BOTH = "modify: update grupper set box5 = 'on;on' where art = 'DIV' and kodenr = '3'";
    private const INSERT_QUICK = "modify: insert into grupper (beskrivelse, kodenr, art, box4) values ('Div_valg (Ordrer)', '3', 'DIV', 'on')";
    private const UPDATE_QUICK = "modify: update grupper set box4 = 'on' where art = 'DIV' and kodenr = '3'";

    /**
     * @param array<string, mixed> $scenario
     * @return array{changed: bool, lockFailed: bool, depth: int, log: list<string>, rows: list<array<string, int|string>>, warnings: list<string>}
     */
    private static function saveRun(string $dbType, array $scenario, string $key = self::SALES, string $raw = 'on'): array
    {
        $errors = tmpfile();
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/support/settings_lock_runner.php', $dbType, json_encode($scenario), $key, $raw],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => $errors],
            $pipes
        );
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($process);
        rewind($errors);
        $stderr = (string) stream_get_contents($errors);
        fclose($errors);
        self::assertSame(0, $exit, $stderr . $stdout);
        $lines = preg_split('/\n/', trim($stdout));
        $run = json_decode((string) end($lines), true);
        self::assertIsArray($run, $stderr . $stdout);
        self::assertSame([], $run['warnings'], 'no PHP warnings in SettingsService.php');
        return $run;
    }

    /** @param list<string> $log */
    private static function inserts(array $log): array
    {
        return array_values(array_filter($log, static fn(string $s): bool => str_starts_with($s, 'modify: insert into grupper')));
    }

    public function test_mysql_creates_a_missing_row_under_the_named_lock_and_releases_it_after_the_commit(): void
    {
        $run = self::saveRun('mysqli', ['rows' => []]);

        self::assertTrue($run['changed']);
        self::assertSame([
            self::PRELOAD,
            self::GET_LOCK,
            'transaktion: begin',
            self::READ,
            self::INSERT_SALES,
            'transaktion: commit',
            self::RELEASE_LOCK,
        ], $run['log'], 'no row: the lock before the transaction, then the row read under it; the lock is let go only after the row is committed');
        self::assertSame([['id' => 1, 'box5' => 'on;']], $run['rows']);
        self::assertSame(0, $run['depth']);

        // With the row there, as before: the row lock alone, no named lock.
        $run = self::saveRun('mysqli', ['rows' => [['id' => 7, 'box5' => ';on']]]);
        self::assertTrue($run['changed']);
        self::assertSame([
            self::PRELOAD,
            'transaktion: begin',
            self::READ,
            self::UPDATE_BOTH,
            'transaktion: commit',
        ], $run['log']);
        self::assertSame([['id' => 7, 'box5' => 'on;on']], $run['rows']);
    }

    public function test_mysql_waits_for_the_named_lock_in_rounds_and_then_merges_into_the_row_the_other_save_created(): void
    {
        // Both saves read no row. The other save (purchases on) took the lock, created ';on' and committed; by the time the
        // lock is given here, its row is there: the read under the lock finds it, and sales is merged into it.
        $run = self::saveRun('mysqli', ['rows' => [], 'locks' => ['0', '1'], 'appears' => ['id' => 9, 'box5' => ';on']]);

        self::assertTrue($run['changed']);
        self::assertSame([
            self::PRELOAD,
            self::GET_LOCK,
            self::GET_LOCK,
            'transaktion: begin',
            self::READ,
            self::UPDATE_BOTH,
            'transaktion: commit',
            self::RELEASE_LOCK,
        ], $run['log'], 'no transaction is open while the lock is waited for');
        self::assertSame([['id' => 9, 'box5' => 'on;on']], $run['rows'], 'one row, both on');
    }

    public function test_postgresql_merges_into_the_row_the_other_save_created_while_it_waited_for_the_lock(): void
    {
        $run = self::saveRun('postgres', ['rows' => [], 'appears' => ['id' => 9, 'box5' => ';on']]);

        self::assertTrue($run['changed']);
        self::assertSame([self::PRELOAD, self::PG_LOCK, 'transaktion: begin', self::READ, self::UPDATE_BOTH, 'transaktion: commit', self::PG_UNLOCK], $run['log'],
            'the transaction begins after the lock is given, so its snapshot has the other save\'s row');
        self::assertSame([['id' => 9, 'box5' => 'on;on']], $run['rows'], 'one row, both on');
    }

    public function test_a_row_deleted_since_the_request_read_it_is_created_under_the_named_lock_inside_the_transaction(): void
    {
        $run = self::saveRun('mysqli', ['rows' => [['id' => 7, 'box5' => ';on']], 'deleted' => true]);
        self::assertTrue($run['changed']);
        self::assertSame([self::PRELOAD, 'transaktion: begin', self::READ, self::GET_LOCK, self::READ, self::INSERT_SALES, 'transaktion: commit', self::RELEASE_LOCK], $run['log']);
        self::assertSame([['id' => 1, 'box5' => 'on;']], $run['rows'], 'one row, created again');

        $run = self::saveRun('postgres', ['rows' => [['id' => 7, 'box5' => ';on']], 'deleted' => true]);
        self::assertTrue($run['changed']);
        self::assertSame([self::PRELOAD, 'transaktion: begin', self::READ, self::PG_XACT_LOCK, self::READ, self::INSERT_SALES, 'transaktion: commit'], $run['log'],
            'the transaction\'s advisory lock ends with it: nothing to release');
    }

    public function test_mysql_a_failed_locking_read_is_not_taken_for_a_missing_row(): void
    {
        // The read waited longer than innodb_lock_wait_timeout (1205): MySQL goes on with the transaction. Before, the read
        // cached the row as missing and the save inserted a second DIV/3 row.
        $cases = [
            'the read under the named lock' => [['rows' => [], 'fail' => [1]],
                [self::PRELOAD, self::GET_LOCK, 'transaktion: begin', self::READ, 'transaktion: rollback', self::RELEASE_LOCK]],
            'the read again under the named lock, of a row deleted since the request read it' => [['rows' => [['id' => 7, 'box5' => ';on']], 'deleted' => true, 'fail' => [2]],
                [self::PRELOAD, 'transaktion: begin', self::READ, self::GET_LOCK, self::READ, 'transaktion: rollback', self::RELEASE_LOCK]],
        ];
        foreach ($cases as $case => [$scenario, $log]) {
            $run = self::saveRun('mysqli', $scenario);

            self::assertFalse($run['changed'], $case);
            self::assertSame($log, $run['log'], "$case: no row is inserted, and the transaction is rolled back");
            self::assertSame([], $run['rows'], $case);
            self::assertSame(0, $run['depth'], $case);
        }
        $run = self::saveRun('mysqli', ['rows' => [['id' => 7, 'box5' => ';on']], 'fail' => [1]]);
        self::assertFalse($run['changed']);
        self::assertSame([['id' => 7, 'box5' => ';on']], $run['rows'], 'an existing row is not written from a read that failed');
    }

    public function test_mysql_a_named_lock_held_elsewhere_for_two_minutes_or_refused_writes_nothing(): void
    {
        foreach (['held for 4 rounds of 30 seconds' => ['0', '0', '0', '0'], 'NULL' => [null]] as $case => $answers) {
            $run = self::saveRun('mysqli', ['rows' => [], 'locks' => $answers]);

            self::assertFalse($run['changed'], $case);
            self::assertSame([], self::inserts($run['log']), $case);
            self::assertNotContains('transaktion: begin', $run['log'], "$case: no transaction is begun");
            self::assertSame(self::GET_LOCK, end($run['log']), "$case: no lock to let go");
            self::assertCount(count($answers), array_filter($run['log'], static fn(string $s): bool => $s === self::GET_LOCK), $case);
            self::assertSame(0, $run['depth'], $case);
        }
    }

    public function test_mysql_inside_the_callers_transaction_a_failed_read_writes_nothing_and_the_caller_rolls_back(): void
    {
        // A caller that opened the transaction without taking the lock first: saveRaw() reads the row again under the lock.
        $run = self::saveRun('mysqli', ['rows' => [], 'fail' => [2], 'caller' => true]);

        self::assertFalse($run['changed']);
        self::assertTrue($run['lockFailed'], 'the caller is told, and rolls back');
        self::assertSame([], self::inserts($run['log']));
        self::assertSame(['transaktion: begin', self::READ, self::GET_LOCK, self::READ, 'transaktion: rollback', self::RELEASE_LOCK], $run['log']);
        self::assertSame(0, $run['depth']);

        $run = self::saveRun('mysqli', ['rows' => [], 'caller' => true], self::PURCHASES);
        self::assertTrue($run['changed']);
        self::assertFalse($run['lockFailed']);
        self::assertSame(['transaktion: begin', self::READ, self::GET_LOCK, self::READ,
            "modify: insert into grupper (beskrivelse, kodenr, art, box5) values ('Div_valg (Ordrer)', '3', 'DIV', ';on')",
            'transaktion: commit', self::RELEASE_LOCK], $run['log'], 'the lock is held until the caller has committed');
    }

    public function test_postgresql_takes_an_advisory_lock_before_the_transaction_for_a_missing_row(): void
    {
        $run = self::saveRun('postgres', ['rows' => []]);

        self::assertTrue($run['changed']);
        self::assertSame([
            self::PRELOAD,
            self::PG_LOCK,
            'transaktion: begin',
            self::READ,
            self::INSERT_SALES,
            'transaktion: commit',
            self::PG_UNLOCK,
        ], $run['log'], 'the session\'s advisory lock is let go after the commit');

        $run = self::saveRun('postgres', ['rows' => [], 'fail' => [1]]);
        self::assertFalse($run['changed']);
        self::assertSame([self::PRELOAD, self::PG_LOCK, 'transaktion: begin', self::READ, 'transaktion: rollback', self::PG_UNLOCK], $run['log']);

        // Inside a caller's transaction: the transaction's advisory lock, which ends with it.
        $run = self::saveRun('postgres', ['rows' => [], 'caller' => true]);
        self::assertTrue($run['changed']);
        self::assertSame(['transaktion: begin', self::READ, self::PG_XACT_LOCK, self::READ, self::INSERT_SALES, 'transaktion: commit'], $run['log']);
    }

    public function test_mysql_a_setting_of_its_own_creates_a_missing_row_under_the_named_lock(): void
    {
        $run = self::saveRun('mysqli', ['rows' => []], self::QUICK, 'on');

        self::assertTrue($run['changed']);
        self::assertSame([
            self::PRELOAD,
            self::GET_LOCK,
            'transaktion: begin',
            self::READ,
            self::INSERT_QUICK,
            'transaktion: commit',
            self::RELEASE_LOCK,
        ], $run['log'], 'no row: the named lock, a transaction, the row read under the lock; the lock is let go after the commit');
        self::assertSame([['id' => 1, 'box4' => 'on']], $run['rows']);
        self::assertSame(0, $run['depth']);

        // The other save switched purchases on and created the row while this one waited: Hurtigfakturering goes into it.
        $run = self::saveRun('mysqli', ['rows' => [], 'appears' => ['id' => 9, 'box5' => ';on']], self::QUICK, 'on');
        self::assertTrue($run['changed']);
        self::assertSame([self::PRELOAD, self::GET_LOCK, 'transaktion: begin', self::READ, self::UPDATE_QUICK, 'transaktion: commit', self::RELEASE_LOCK], $run['log']);
        self::assertSame([['id' => 9, 'box4' => 'on', 'box5' => ';on']], $run['rows'], 'one DIV/3 row: purchases stays on');

        // With the row there, as before: no transaction of its own, no lock.
        $run = self::saveRun('mysqli', ['rows' => [['id' => 7, 'box5' => ';on']]], self::QUICK, 'on');
        self::assertTrue($run['changed']);
        self::assertSame([self::PRELOAD, self::UPDATE_QUICK], $run['log']);
        self::assertSame([['id' => 7, 'box4' => 'on', 'box5' => ';on']], $run['rows']);
    }

    public function test_postgresql_a_setting_of_its_own_writes_into_the_row_the_other_save_created(): void
    {
        $run = self::saveRun('postgres', ['rows' => [], 'appears' => ['id' => 9, 'box5' => ';on']], self::QUICK, 'on');

        self::assertTrue($run['changed']);
        self::assertSame([self::PRELOAD, self::PG_LOCK, 'transaktion: begin', self::READ, self::UPDATE_QUICK, 'transaktion: commit', self::PG_UNLOCK], $run['log']);
        self::assertSame([['id' => 9, 'box4' => 'on', 'box5' => ';on']], $run['rows']);
    }

    public function test_mysql_a_setting_of_its_own_whose_locking_read_fails_inserts_no_row(): void
    {
        $run = self::saveRun('mysqli', ['rows' => [], 'fail' => [1]], self::QUICK, 'on');
        self::assertFalse($run['changed']);
        self::assertSame([self::PRELOAD, self::GET_LOCK, 'transaktion: begin', self::READ, 'transaktion: rollback', self::RELEASE_LOCK], $run['log']);
        self::assertSame([], $run['rows']);
        self::assertSame(0, $run['depth']);

        // Inside a caller's transaction: nothing is written, and the caller is told to roll back.
        $run = self::saveRun('mysqli', ['rows' => [], 'fail' => [1], 'caller' => true], self::QUICK, 'on');
        self::assertFalse($run['changed']);
        self::assertTrue($run['lockFailed']);
        self::assertSame(['transaktion: begin', self::PRELOAD, self::GET_LOCK, self::READ, 'transaktion: rollback', self::RELEASE_LOCK], $run['log']);
        self::assertSame([], $run['rows']);
    }
}
