<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * SST-841: the booking APIs answer JSON, so a failed lock query, booking DELETE or order DELETE must not put db_select()'s JavaScript
 * alert in front of the answer (Codex round 11 on PR #969). Uses the real includes/db_query.php and
 * includes/vibrantPayment.php on an isolated PostgreSQL schema; the failures are real (a trigger refuses the order
 * DELETE, another session holds the advisory lock while lock_timeout is short). Each case runs twice, as db_select()
 * stops the request when the same error repeats within a minute.
 *
 * History:
 * 20261104 CL/MMR Created (SST-841, Codex round 11 on PR #969).
 * 20261110 CL/MMR rental_payment_ready() looks at the active schema only (CodeRabbit on PR #969).
 * 20261111 CL/MMR A failed booking DELETE answers pending without output (Codex final re-test on PR #969).
 */
#[RunTestsInSeparateProcesses]
final class bookingPaymentDbErrors extends TestCase
{
    /** @var \PgSql\Connection */
    private $holder;
    private string $schema = '';
    private string $work = '';

    protected function setUp(): void
    {
        $dsn = getenv('SALDI_CHAR_PG_DSN');
        if (!$dsn || !extension_loaded('pgsql')) {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN to a disposable PostgreSQL database connection string.');
        }
        $main = @pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        $holder = @pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        if (!$main || !$holder) {
            self::markTestSkipped('Configured PostgreSQL connection is unavailable.');
        }
        $this->holder = $holder;
        $this->schema = 'booking_db_errors_' . bin2hex(random_bytes(6));
        $GLOBALS['connection'] = $main;
        $GLOBALS['db'] = (string)pg_fetch_result(pg_query($main, 'SELECT current_database()'), 0, 0);
        $GLOBALS['db_type'] = 'postgres';
        $GLOBALS['brugernavn'] = 'test';
        $_SERVER['REQUEST_URI'] = '/remoteBooking/api.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        // db_select() writes its error files under temp/<db> relative to the page
        $this->work = sys_get_temp_dir() . '/' . $this->schema;
        mkdir($this->work . '/temp/' . $GLOBALS['db'], 0777, true);
        chdir($this->work);
        // The real alert() prints a script element; the same output here shows when db_select()'s error path is taken.
        eval('function alert($msg) { echo "<script type=\'text/javascript\'>alert(\'$msg\');</script>"; }');
        require_once __DIR__ . '/../../includes/db_query.php';
        require_once __DIR__ . '/../../includes/vibrantPayment.php';
        ini_set('error_log', $this->work . '/php.log');

        $s = $this->schema;
        pg_query($main, "CREATE SCHEMA $s; SET search_path TO $s");
        pg_query($holder, "SET search_path TO $s");
        pg_query($main, "CREATE TABLE ordrer (id integer PRIMARY KEY, fakturanr text)");
        pg_query($main, "CREATE TABLE rentalperiod (id serial PRIMARY KEY, order_id integer, expiry_time timestamp, payment_id varchar(100), payment_url varchar(500))");
        pg_query($main, "CREATE FUNCTION refuse_delete() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'order % may not be deleted', OLD.id; END \$\$ LANGUAGE plpgsql");
        pg_query($main, "CREATE TRIGGER refuse_delete BEFORE DELETE ON ordrer FOR EACH ROW WHEN (OLD.id = 7) EXECUTE FUNCTION refuse_delete()");
        pg_query($main, "INSERT INTO ordrer VALUES (7, ''), (8, '')");
        pg_query($main, "INSERT INTO rentalperiod (id, order_id, expiry_time, payment_id) VALUES (1, 7, now() + interval '1 hour', ''), (2, 8, now() + interval '1 hour', ''), (3, 8, NULL, 'pi_3')");
    }

    protected function tearDown(): void
    {
        if ($this->schema !== '') {
            pg_query($this->holder, 'SELECT pg_advisory_unlock_all()');
            pg_query($GLOBALS['connection'], "RESET search_path; DROP SCHEMA IF EXISTS {$this->schema} CASCADE");
            exec('rm -rf ' . escapeshellarg($this->work));
        }
    }

    /** @return array{0: string, 1: array<string, string>} the raw output and the answer */
    private static function call(array $data, callable $invoice): array
    {
        ob_start();
        try {
            $answer = update_booking_payment($GLOBALS['db'], $data, $invoice);
        } finally {
            $out = (string)ob_get_clean();
        }
        return [$out, $answer];
    }

    private static function rows(string $sql): int
    {
        return (int)pg_fetch_result(pg_query($GLOBALS['connection'], $sql), 0, 0);
    }

    private static function noInvoice(): callable
    {
        return static function (int $orderId) {
            throw new LogicException("order $orderId must not be invoiced");
        };
    }

    public function testPaymentColumnsInAnotherSchemaDoNotCountForTheActiveOne(): void
    {
        $main = $GLOBALS['connection'];
        $other = $this->schema . '_other';
        pg_query($main, "CREATE SCHEMA $other");
        try {
            pg_query($main, "CREATE TABLE $other.rentalperiod (id serial PRIMARY KEY, payment_id varchar(100), payment_url varchar(500))");
            pg_query($main, "ALTER TABLE rentalperiod DROP COLUMN payment_id, DROP COLUMN payment_url");
            $this->assertFalse(rental_payment_ready($GLOBALS['db']), 'the other schema must not answer for the active one');
            pg_query($main, "ALTER TABLE rentalperiod ADD COLUMN payment_id varchar(100)");
            $this->assertFalse(rental_payment_ready($GLOBALS['db']), 'one of two columns is not ready');
            pg_query($main, "ALTER TABLE rentalperiod ADD COLUMN payment_url varchar(500)");
            $this->assertTrue(rental_payment_ready($GLOBALS['db']));
        } finally {
            pg_query($main, "DROP SCHEMA IF EXISTS $other CASCADE");
        }
    }

    public function testAFailedOrderDeleteAnswersPendingWithoutOutputAndKeepsBothRows(): void
    {
        for ($i = 1; $i <= 2; $i++) {
            [$out, $answer] = self::call(['id' => 1, 'status' => 'rejected'], self::noInvoice());
            $this->assertSame('', $out, "nothing in front of the JSON answer (attempt $i)");
            $this->assertSame(['status' => 'pending'], $answer);
            $this->assertSame(1, self::rows('SELECT count(*) FROM rentalperiod WHERE id = 1'), 'the booking is rolled back');
            $this->assertSame(1, self::rows('SELECT count(*) FROM ordrer WHERE id = 7'), 'the order is kept');
        }
        $this->assertStringContainsString('order 7 may not be deleted', (string)file_get_contents($this->work . '/php.log'), 'the failure is logged on the server');

        [$out, $answer] = self::call(['id' => 2, 'status' => 'rejected'], self::noInvoice());
        $this->assertSame(['', ['status' => 'deleted']], [$out, $answer], 'a normal reject still deletes');
        $this->assertSame(0, self::rows('SELECT count(*) FROM rentalperiod WHERE id = 2') + self::rows('SELECT count(*) FROM ordrer WHERE id = 8'));
    }

    public function testAFailedBookingDeleteAnswersPendingWithoutOutputAndKeepsBothRows(): void
    {
        $main = $GLOBALS['connection'];
        pg_query($main, "INSERT INTO ordrer VALUES (9, '')");
        pg_query($main, "INSERT INTO rentalperiod (id, order_id, expiry_time, payment_id) VALUES (4, 9, now() + interval '1 hour', '')");
        pg_query($main, "CREATE FUNCTION refuse_booking_delete() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'booking % may not be deleted', OLD.id; END \$\$ LANGUAGE plpgsql");
        pg_query($main, "CREATE TRIGGER refuse_booking_delete BEFORE DELETE ON rentalperiod FOR EACH ROW WHEN (OLD.id = 4) EXECUTE FUNCTION refuse_booking_delete()");
        for ($i = 1; $i <= 2; $i++) {
            [$out, $answer] = self::call(['id' => 4, 'status' => 'rejected'], self::noInvoice());
            $this->assertSame('', $out, "nothing in front of the JSON answer (attempt $i)");
            $this->assertSame(['status' => 'pending'], $answer);
            $this->assertSame(1, self::rows('SELECT count(*) FROM rentalperiod WHERE id = 4 AND expiry_time IS NOT NULL'), 'the booking is kept pending');
            $this->assertSame(1, self::rows('SELECT count(*) FROM ordrer WHERE id = 9'), 'the order is kept');
            $this->assertSame(1, self::rows("SELECT count(*) FROM ordrer WHERE id = 9 AND fakturanr = ''"), 'nothing is invoiced');
        }
        $this->assertStringContainsString('booking 4 may not be deleted', (string)file_get_contents($this->work . '/php.log'), 'the failure is logged on the server');
        $this->assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status($main), 'the failed transaction is rolled back, not left open');

        pg_query($main, 'DROP TRIGGER refuse_booking_delete ON rentalperiod');
        [$out, $answer] = self::call(['id' => 4, 'status' => 'rejected'], self::noInvoice());
        $this->assertSame(['', ['status' => 'deleted']], [$out, $answer], 'once the failure is gone, the reject deletes');
        $this->assertSame(0, self::rows('SELECT count(*) FROM rentalperiod WHERE id = 4') + self::rows('SELECT count(*) FROM ordrer WHERE id = 9'));
    }

    public function testAFailedLockQueryAnswersInvoiceFailedWithoutOutputAndCanBeRetried(): void
    {
        pg_query($this->holder, 'SELECT pg_advisory_lock(841, 8)');
        pg_query($GLOBALS['connection'], "SET lock_timeout = '200ms'");
        for ($i = 1; $i <= 2; $i++) {
            [$out, $answer] = self::call(['id' => 3, 'status' => 'approved'], self::noInvoice());
            $this->assertSame('', $out, "nothing in front of the JSON answer (attempt $i)");
            $this->assertSame(['status' => 'invoice_failed'], $answer);
        }
        pg_query($this->holder, 'SELECT pg_advisory_unlock(841, 8)');
        pg_query($GLOBALS['connection'], 'RESET lock_timeout');
        $invoiced = [];
        [$out, $answer] = self::call(['id' => 3, 'status' => 'approved'], static function (int $orderId) use (&$invoiced) {
            $invoiced[] = $orderId;
            pg_query($GLOBALS['connection'], "UPDATE ordrer SET fakturanr = '1001' WHERE id = $orderId");
            return $orderId;
        });
        $this->assertSame(['', ['status' => 'approved'], [8]], [$out, $answer, $invoiced], 'the retry invoices once');
    }
}
