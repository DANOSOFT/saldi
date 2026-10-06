<?php
// 20260908 CDX/LH Guard bounded batch updates and retained outer posting transactions (SST-765).

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BatchPostingRegressionTest extends TestCase
{
    private $db = null;
    private ?string $schema = null;
    private string $postingSource;

    protected function setUp(): void
    {
        $dsn = getenv('SALDI_CHAR_PG_DSN');
        if (!extension_loaded('pgsql') || !$dsn) {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN to a disposable PostgreSQL database connection string.');
        }
        set_error_handler(static function () {
            throw new RuntimeException('Configured PostgreSQL connection is unavailable.');
        });
        try {
            $this->db = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        } catch (Throwable $error) {
            self::markTestSkipped('Configured PostgreSQL connection is unavailable.');
        } finally {
            restore_error_handler();
        }
        $this->schema = 'sst765_batch_' . bin2hex(random_bytes(8));
        $this->query('CREATE SCHEMA ' . $this->schema);
        $this->query('SET search_path TO ' . $this->schema);
        require_once __DIR__ . '/support/batch_posting_database.php';
        $GLOBALS['batchPostingDb'] = $this->db;
        $GLOBALS['batchPostingUpdates'] = $GLOBALS['batchPostingAffected'] = 0;
        $GLOBALS['batchPostingTransactions'] = [];
        $this->postingSource = file_get_contents(dirname(__DIR__, 3) . '/kreditor/bogfor.php');
        $this->query('CREATE TABLE batch_kob (id integer PRIMARY KEY, linje_id integer, vare_id integer DEFAULT 100,
            pris numeric, fakturadate date, antal numeric, rest numeric, modtime timestamp)');
        $this->query('CREATE TABLE update_count (versions bigint)');
        $this->query('INSERT INTO update_count VALUES (0)');
        $this->query("CREATE FUNCTION count_updates() RETURNS trigger LANGUAGE plpgsql AS '
            BEGIN UPDATE update_count SET versions=versions+(SELECT count(*) FROM changed); RETURN NULL; END'");
        $this->query('CREATE TRIGGER count_updates AFTER UPDATE ON batch_kob
            REFERENCING NEW TABLE AS changed FOR EACH STATEMENT EXECUTE FUNCTION count_updates()');
        $this->query("CREATE FUNCTION batch_modtime() RETURNS trigger LANGUAGE plpgsql AS '
            BEGIN NEW.modtime=now(); RETURN NEW; END'");
        $this->query('CREATE TRIGGER batch_modtime BEFORE UPDATE ON batch_kob
            FOR EACH ROW EXECUTE FUNCTION batch_modtime()');
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            if (pg_transaction_status($this->db) !== PGSQL_TRANSACTION_IDLE) {
                pg_query($this->db, 'ROLLBACK');
            }
            if ($this->schema) {
                pg_query($this->db, 'DROP SCHEMA ' . $this->schema . ' CASCADE');
            }
            pg_close($this->db);
        }
    }

    private function query(string $sql)
    {
        $result = pg_query($this->db, $sql);
        self::assertNotFalse($result);
        return $result;
    }

    private function block(string $start, string $end): string
    {
        $from = strpos($this->postingSource, $start);
        self::assertNotFalse($from, 'Posting block start must still exist.');
        $to = strpos($this->postingSource, $end, $from);
        self::assertNotFalse($to, 'Posting block end must still exist.');
        return substr($this->postingSource, $from, $to - $from);
    }

    private function positiveBlock(): string
    {
        // Execute the production block, not a second implementation of its loop.
        return $this->block('$query = db_select("select * from batch_kob where linje_id=$linje_id[$x]"', 'if ($fifo)');
    }

    public static function batchCounts(): array
    {
        return [[0], [1], [3], [1463]];
    }

    #[DataProvider('batchCounts')]
    public function testPositiveBatchesAreUpdatedOnceWithoutChangingStock(int $count): void
    {
        $this->query("INSERT INTO batch_kob (id,linje_id,pris,fakturadate,antal,rest)
            SELECT n,11,1.25,'2026-08-01',n::numeric/10,n::numeric/20 FROM generate_series(1,$count) n");
        $this->query("INSERT INTO batch_kob VALUES (90001,22,100,7.75,'2026-07-01',8.5,4.25,NULL)");
        $this->query('BEGIN');
        $x = 1;
        $linje_id = [1 => 11];
        $dkpris = [1 => 12.345];
        $levdate = '2026-09-02';
        $batch_id = 777;
        eval($this->positiveBlock());
        self::assertSame($count ? 1 : 0, $GLOBALS['batchPostingUpdates']);
        self::assertSame($count, $GLOBALS['batchPostingAffected']);
        self::assertSame($count, (int)pg_fetch_result($this->query('SELECT versions FROM update_count'), 0, 0));
        self::assertSame($count, (int)pg_fetch_result($this->query("SELECT count(*) FROM batch_kob WHERE linje_id=11
            AND pris=12.345 AND fakturadate='2026-09-02' AND antal=id::numeric/10 AND rest=id::numeric/20
            AND modtime=now()"), 0, 0));
        self::assertSame(1, (int)pg_fetch_result($this->query("SELECT count(*) FROM batch_kob WHERE id=90001
            AND pris=7.75 AND fakturadate='2026-07-01' AND antal=8.5 AND rest=4.25 AND modtime IS NULL"), 0, 0));
        self::assertSame($count ?: 777, $batch_id);
    }

    public static function interveningEmptyLine(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('interveningEmptyLine')]
    public function testFollowingNegativeLineRetainsExistingBatchLink(bool $emptyLine): void
    {
        $this->query("INSERT INTO batch_kob (id,linje_id,pris,antal,rest) VALUES
            (1,11,1,1,1),(2,11,1,1,1),(3,11,1,1,1),(91,33,1,-1,-1)");
        $this->query('CREATE TABLE batch_salg (batch_kob_id integer, linje_id integer)');
        $this->query('CREATE TABLE ordrelinjer (id integer PRIMARY KEY, kostpris numeric)');
        $this->query('INSERT INTO batch_salg VALUES (3,501),(91,502)');
        $this->query('INSERT INTO ordrelinjer VALUES (501,1),(502,2)');
        $this->query('BEGIN');
        $x = 1;
        $linje_id = [1 => 11];
        $dkpris = [1 => 12.345];
        $levdate = '2026-09-02';
        $batch_id = 777;
        eval($this->positiveBlock());
        if ($emptyLine) {
            $linje_id[1] = 44;
            eval($this->positiveBlock());
        }
        self::assertSame(3, $batch_id);
        $linje_id[1] = 33;
        $vare_id = [1 => 100];
        $dkpris[1] = 40;
        eval($this->block('$batch_id=(int)$batch_id;', '} # endif & else ($antal[$x]>0)'));
        // The optimization preserves existing cross-line behavior; credit linkage is a separate concern.
        self::assertSame([['id' => '501', 'kostpris' => '40'], ['id' => '502', 'kostpris' => '2']],
            pg_fetch_all($this->query('SELECT id,kostpris FROM ordrelinjer ORDER BY id')));
        self::assertSame('-1', pg_fetch_result($this->query('SELECT antal FROM batch_kob WHERE id=91'), 0, 0));
    }

    public function testOrderNumberAllocationDoesNotCommitItsCaller(): void
    {
        $this->query('CREATE TABLE ordrer (id serial PRIMARY KEY, ordrenr integer, art text)');
        $source = file_get_contents(dirname(__DIR__, 3) . '/includes/std_func.php');
        $start = strpos($source, "if (!function_exists('get_next_order_number')) {");
        self::assertNotFalse($start);
        $end = strpos($source, "\n}", $start);
        self::assertNotFalse($end);
        eval(substr($source, $start, $end + 2 - $start));
        self::assertSame(1, get_next_order_number('KO'));
        self::assertSame(['begin', 'commit'], $GLOBALS['batchPostingTransactions']);
        $GLOBALS['batchPostingTransactions'] = [];
        transaktion('begin');
        $number = get_next_order_number('KO', false);
        $this->query("INSERT INTO ordrer (ordrenr,art) VALUES ($number,'KO')");
        self::assertSame(['begin'], $GLOBALS['batchPostingTransactions']);
        self::assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->db));
        transaktion('rollback');
        self::assertSame('0', pg_fetch_result($this->query('SELECT count(*) FROM ordrer'), 0, 0));
        self::assertSame(1, get_next_order_number('KO'));
    }
}
