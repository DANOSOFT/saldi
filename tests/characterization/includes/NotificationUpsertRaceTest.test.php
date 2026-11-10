<?php
// tests/characterization/includes/NotificationUpsertRaceTest.test.php
//
// Two users' first page loads at the same moment both ran notif_refresh(). Both looked for the broadcast 'fiscal:none'
// warning, found none and inserted it, so every user saw "Intet regnskabsår dækker dags dato" twice, and marking one read
// left the other unread (testlab login-race, test_39, candidate rounds 1 and 3). notifications has no unique key on
// (source_key, user_id), and a broadcast row's user_id is null, which a unique index would not compare.
//
// Set SALDI_CHAR_PG_DSN to an isolated PostgreSQL connection string. Every PostgreSQL test owns a randomized schema and
// drops only that schema again. CI has no MySQL, so the MySQL tests script the database's answers
// (support/notification_database.php).
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class NotificationUpsertRaceTest extends TestCase
{
    private const LOCK = "select get_lock(concat('saldi:notifications:', md5(database())), 30) as locked";
    private const UNLOCK = "select release_lock(concat('saldi:notifications:', md5(database())))";

    private $request;
    private $rival;
    private $observer;
    private string $schema;
    private bool $rivalPending = false;

    protected function setUp(): void
    {
        require_once __DIR__ . '/support/notification_database.php';
        require_once dirname(__DIR__, 3) . '/includes/notifications.php';
        $GLOBALS['notifQueries'] = [];
    }

    protected function tearDown(): void
    {
        if ($this->rival) {
            if ($this->rivalPending) {
                pg_cancel_query($this->rival);
                while (pg_get_result($this->rival) !== false) {}
            }
            pg_query($this->rival, 'ROLLBACK');
        }
        if (isset($this->schema) && $this->observer) {
            pg_query($this->observer, 'DROP SCHEMA ' . $this->schema . ' CASCADE');
        }
        foreach ([$this->request, $this->rival, $this->observer] as $connection) {
            if ($connection) {
                pg_close($connection);
            }
        }
    }

    public static function generatedItems(): array
    {
        return [
            'fiscal:none for everyone, two users log in' => ['fiscal:none', null, 'Intet regnskabsår dækker dags dato', 'Opret næste regnskabsår under Indstillinger » Virksomhed » Regnskabsår', '/systemdata/settingsSection.php?s=company.fiscal_years'],
            'batch:expiry for one user, two tabs' => ['batch:expiry', 14, 'Varer med udløb', '2 batch(er) udløber inden for 30 dage – 0 er allerede udløbet', '/lager/udlobsrapport.php'],
        ];
    }

    #[DataProvider('generatedItems')]
    public function testTheSecondPollUpdatesTheItemTheFirstPollIsInserting(string $key, ?int $userId, string $title, string $body, string $link): void
    {
        $this->connectFixture();

        // The other request has looked for the item, found none, and its insert is not visible yet.
        $this->runAs($this->rival, true);
        notif_upsert($key, $userId, 'warning', $title, $body, $link, '2026-11-15');
        self::assertTrue(isset($GLOBALS['notifHeld']), 'The other request inserted nothing');
        self::assertSame(0, $this->storedCount($key, $userId), 'The other request\'s insert is already visible');
        // It goes on a second from now, while this request polls the same item.
        self::assertTrue(pg_send_query($this->rival, 'SELECT pg_sleep(1); ' . implode('; ', $GLOBALS['notifHeld'])));
        $this->rivalPending = true;

        $this->runAs($this->request, false);
        notif_upsert($key, $userId, 'warning', $title, $body, $link, '2026-11-16');
        $this->finishRival();

        self::assertSame(1, $this->storedCount($key, $userId), 'The item is stored twice');
        $inserts = array_filter($GLOBALS['notifQueries'], static fn ($sql) => stripos($sql, 'insert into notifications') === 0);
        self::assertSame([], array_values($inserts), 'This request inserted the item again instead of updating the other request\'s row');
        $row = pg_fetch_assoc(pg_query_params($this->observer, 'SELECT expires, title FROM notifications WHERE source_key = $1', [$key]));
        self::assertSame(['expires' => '2026-11-16', 'title' => $title], $row, 'This request did not update the row');
        $locks = pg_query($this->observer, "SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND pid IN (" . (int) pg_get_pid($this->request) . ', ' . (int) pg_get_pid($this->rival) . ')');
        self::assertSame('0', pg_fetch_result($locks, 0, 0), 'A request kept the notifications lock');
    }

    public function testOnMysqlTheLookupAndTheInsertRunUnderAGetLockForThisDatabase(): void
    {
        $GLOBALS['db_type'] = 'mysqli';
        $GLOBALS['notifLockAnswers'] = ['1'];

        notif_upsert('fiscal:none', null, 'warning', 'Intet regnskabsår dækker dags dato', 'Opret næste regnskabsår', '/systemdata/settingsSection.php?s=company.fiscal_years', '2026-11-15');

        $queries = $GLOBALS['notifQueries'];
        self::assertCount(4, $queries);
        self::assertSame(self::LOCK, $queries[0]);
        self::assertSame("select id from notifications where source_key = 'fiscal:none' and user_id is null", $queries[1]);
        self::assertStringStartsWith('insert into notifications ', $queries[2]);
        self::assertSame(self::UNLOCK, $queries[3]);
    }

    public static function lockNotHad(): array
    {
        return [
            'GET_LOCK waited 30 seconds' => ['0'],
            'GET_LOCK failed' => [null],
        ];
    }

    #[DataProvider('lockNotHad')]
    public function testOnMysqlNothingIsWrittenWithoutTheLock(?string $answer): void
    {
        $GLOBALS['db_type'] = 'mysqli';
        $GLOBALS['notifLockAnswers'] = [$answer];

        notif_upsert('fiscal:none', null, 'warning', 'Intet regnskabsår dækker dags dato', 'Opret næste regnskabsår', '/systemdata/settingsSection.php?s=company.fiscal_years', '2026-11-15');

        self::assertSame([self::LOCK], $GLOBALS['notifQueries'], 'The item was looked up or written without the lock, or a lock it did not hold was released');
    }

    private function connectFixture(): void
    {
        if (!extension_loaded('pgsql') || !getenv('SALDI_CHAR_PG_DSN')) {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN for an isolated PostgreSQL fixture.');
        }
        $dsn = getenv('SALDI_CHAR_PG_DSN');
        // Report an unavailable fixture without echoing a potentially credential-bearing DSN.
        set_error_handler(static function () {
            throw new RuntimeException('Configured PostgreSQL connection is unavailable.');
        });
        try {
            $this->request = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
            $this->rival = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
            $this->observer = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        } catch (Throwable $error) {
            self::markTestSkipped('Configured PostgreSQL connection is unavailable.');
        } finally {
            restore_error_handler();
        }
        if (!$this->request || !$this->rival || !$this->observer) {
            self::markTestSkipped('The configured isolated PostgreSQL fixture is unavailable.');
        }
        $this->schema = 'notif_race_' . bin2hex(random_bytes(8));
        pg_query($this->observer, 'CREATE SCHEMA ' . $this->schema);
        foreach ([$this->request, $this->rival, $this->observer] as $connection) {
            pg_query($connection, 'SET search_path TO ' . $this->schema);
            pg_query($connection, "SET statement_timeout = '5s'");
        }
        // The table includes/betweenUpdates.php creates.
        pg_query($this->observer, 'CREATE TABLE notifications (id SERIAL PRIMARY KEY NOT NULL, user_id integer, type varchar(30) NOT NULL, title text, body text, link text, created timestamp DEFAULT now(), expires date, source_key varchar(80))');
        pg_query($this->observer, 'CREATE INDEX notifications_user_idx ON notifications (user_id, created)');
        $GLOBALS['db_type'] = 'postgresql';
    }

    /** Point the fixture's db_select()/db_modify() at one request's connection. */
    private function runAs($connection, bool $hold): void
    {
        $GLOBALS['notifConnection'] = $connection;
        $GLOBALS['notifHold'] = $hold;
        $GLOBALS['notifQueries'] = [];
    }

    private function storedCount(string $key, ?int $userId): int
    {
        $result = pg_query_params($this->observer, 'SELECT count(*) FROM notifications WHERE source_key = $1 AND user_id IS NOT DISTINCT FROM $2::integer', [$key, $userId]);
        return (int) pg_fetch_result($result, 0, 0);
    }

    private function finishRival(): void
    {
        while (($result = pg_get_result($this->rival)) !== false) {
            self::assertContains(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], (string) pg_result_error($result));
        }
        $this->rivalPending = false;
    }
}
