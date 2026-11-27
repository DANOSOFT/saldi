<?php
// 20261127 ASR Created. rewriteTmpCodes() (betweenUpdates.php) brings brugere.tmp_kode values in the form "<type>|<expire>|<code>" to the current forms, once per value.
// 20261127 ASR The rewrite is checked to come after the earlier blocks of betweenUpdates.php; the shared-rights block follows it.
// 20261127 ASR A temporary password in the earlier form becomes "0|0|reset|<expire>|<code>".

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TmpCodeRewriteMigrationTest extends TestCase
{
    private ?\PgSql\Connection $connection = null;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $this->connection = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        $GLOBALS['tmpCodeRewriteConnection'] = $this->connection;
        $GLOBALS['tmpCodeRewriteWrites'] = [];
        if (!function_exists('db_select')) {
            function db_select($sql, $location = '') {
                $result = pg_query($GLOBALS['tmpCodeRewriteConnection'], $sql);
                if ($result === false) {
                    throw new RuntimeException(pg_last_error($GLOBALS['tmpCodeRewriteConnection']) . " -- $sql");
                }
                return $result;
            }
            function db_fetch_array($result) {
                return pg_fetch_assoc($result);
            }
            function db_modify($sql, $location = '') {
                $GLOBALS['tmpCodeRewriteWrites'][] = $sql;
                db_select($sql, $location);
            }
            function db_escape_string($value) {
                return pg_escape_string($GLOBALS['tmpCodeRewriteConnection'], (string) $value);
            }
        }
        require_once __DIR__ . '/../../../includes/opdat_func/rewriteTmpCodes.php';
        $schema = 'tmp_code_' . bin2hex(random_bytes(8));
        $this->sql('BEGIN');
        $this->sql("CREATE SCHEMA $schema");
        $this->sql("SET LOCAL search_path TO $schema");
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            pg_query($this->connection, 'ROLLBACK');
            pg_close($this->connection);
        }
        unset($GLOBALS['tmpCodeRewriteConnection'], $GLOBALS['tmpCodeRewriteWrites']);
    }

    private function sql(string $sql): \PgSql\Result
    {
        $result = pg_query($this->connection, $sql);
        self::assertNotFalse($result);
        return $result;
    }

    /** @return array<int, string|null> id => tmp_kode */
    private function stored(): array
    {
        $out = [];
        foreach (pg_fetch_all($this->sql('SELECT id, tmp_kode FROM brugere ORDER BY id')) ?: [] as $row) {
            $out[(int) $row['id']] = $row['tmp_kode'];
        }
        return $out;
    }

    public function test_the_earlier_form_is_rewritten_and_every_other_value_is_kept(): void
    {
        $hash = str_repeat('c', 64);
        $this->sql('CREATE TABLE brugere (id integer PRIMARY KEY, brugernavn text, kode text, tmp_kode text)');
        $rows = [
            1 => 'reset|1764000000|abcDEF2345',
            2 => 'invite|1764000000|' . $hash,
            3 => '2fa|1764000000|482913',
            4 => 'reset|soon|abcDEF2345',
            5 => '1764000000|abcDEF2345',
            6 => '0|0|invite|1764000000|' . $hash,
            7 => null,
            8 => '482913|1764000000',
            9 => "reset|1764000000|it's",
        ];
        foreach ($rows as $id => $value) {
            pg_query_params($this->connection, 'INSERT INTO brugere (id, brugernavn, kode, tmp_kode) VALUES ($1, $2, $3, $4)', [$id, "user$id", '', $value]);
        }

        self::assertSame(5, rewriteTmpCodes('postgresql'));
        self::assertSame([
            1 => '0|0|reset|1764000000|abcDEF2345',
            2 => '0|0|invite|1764000000|' . $hash,
            3 => null,
            4 => null,
            5 => '1764000000|abcDEF2345',
            6 => '0|0|invite|1764000000|' . $hash,
            7 => null,
            8 => '482913|1764000000',
            9 => "0|0|reset|1764000000|it's",
        ], $this->stored());

        $writes = $GLOBALS['tmpCodeRewriteWrites'];
        self::assertSame(0, rewriteTmpCodes('postgresql'), 'a second login finds nothing to rewrite');
        self::assertSame($writes, $GLOBALS['tmpCodeRewriteWrites']);
    }

    public function test_a_row_changed_since_it_was_read_is_left_as_it_is(): void
    {
        $this->sql('CREATE TABLE brugere (id integer PRIMARY KEY, brugernavn text, kode text, tmp_kode text)');
        $this->sql("INSERT INTO brugere VALUES (1, 'a', '', 'reset|1764000000|abcDEF2345')");
        self::assertSame([], $GLOBALS['tmpCodeRewriteWrites']);
        rewriteTmpCodes('postgresql');
        $update = $GLOBALS['tmpCodeRewriteWrites'][0];
        self::assertStringContainsString("AND tmp_kode = 'reset|1764000000|abcDEF2345'", $update, 'the update names the value it read');
        $this->sql("UPDATE brugere SET tmp_kode = '1764009999|NewCode234' WHERE id = 1");
        $this->sql($update);
        self::assertSame([1 => '1764009999|NewCode234'], $this->stored(), 'a newer value is not overwritten');
    }

    public function test_a_ledger_without_the_field_is_left_alone(): void
    {
        $this->sql('CREATE TABLE brugere (id integer PRIMARY KEY, brugernavn text, kode text)');
        self::assertSame(0, rewriteTmpCodes('postgresql'));
        self::assertSame([], $GLOBALS['tmpCodeRewriteWrites']);
    }

    public function test_the_rewrite_runs_at_login_after_the_earlier_blocks(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../includes/betweenUpdates.php');
        $at = strpos($source, 'rewriteTmpCodes($db_type);');
        self::assertNotFalse($at);
        self::assertGreaterThan(strpos($source, 'reminder_dispatch_since_ensure($db_type);'), $at, 'appended after the blocks before it');
    }
}
