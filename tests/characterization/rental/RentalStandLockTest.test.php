<?php
// tests/characterization/rental/RentalStandLockTest.test.php
//
// On MySQL and MariaDB a save of a booking sees the bookings of its stand that were committed while it waited for the
// stand's lock (review of the overlap fix, testlab round 1, rental-public-booking). InnoDB reads a transaction from the
// snapshot of its first plain read. rental/rental.php?updateBooking read the booking inside its transaction before it
// locked the stand, so a booking for 28-11..05-12-2026 committed in that pause was missing from the snapshot its check
// read, and a move onto 01-12..08-12 went through. Every save now opens its transaction with the lock
// (rental_begin_stand_lock()) and reads nothing in it before.
//
// CI has no MySQL or MariaDB, so the database answers are scripted (support/innodb_booking_stub_database.php), and the
// staff saves run as their blocks stand in rental/rental.php (support/run_booking_save_innodb.php). RemoteBookingOverlapTest
// runs every save on PostgreSQL.
//
// History:
// 20261105 CL/LH Created.
// 20261116 CL/ASR The staff booking overview (rental/index.js) deletes a booking or a block only after OK; Annuller on an invoiced booking in the booking view deleted it as well.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RentalStandLockTest extends TestCase
{
    private const STAND = 2;

    private const LOCK = 'select id from rentalitems where id = 2 for update';

    /** A day as rental/booking.js and rental/edit.js send it: new Date('YYYY-MM-DD') / 1000, midnight UTC. */
    private static function day(string $date): int
    {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->getTimestamp();
    }

    /** @return array{id:int, item_id:int, rt_from:int, rt_to:int, order_id:int} a booking of the stand */
    private static function booking(int $id, string $from, string $to): array
    {
        return ['id' => $id, 'item_id' => self::STAND, 'rt_from' => self::day($from), 'rt_to' => self::day($to), 'order_id' => 0];
    }

    /**
     * What the save answered, and the statements it sent, when $waiting was committed while it waited for the stand.
     *
     * @param list<array{id:int, item_id:int, rt_from:int, rt_to:int, order_id:int}> $periods
     * @param array{id:int, item_id:int, rt_from:int, rt_to:int, order_id:int}|null  $waiting
     * @param array<string, mixed>                                                    $body
     * @return array{status:int|false, body:string, statements:list<string>}
     */
    private function save(string $handler, array $periods, ?array $waiting, array $body): array
    {
        $scenario = json_encode(['stands' => [self::STAND], 'periods' => $periods, 'waiting' => $waiting, 'body' => $body]);
        $cmd = [PHP_BINARY, __DIR__ . '/support/run_booking_save_innodb.php', $handler, $scenario];
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($proc, 'could not start run_booking_save_innodb.php');
        fclose($pipes[0]);
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $lines = array_values(array_filter(explode("\n", trim($stdout))));
        $answer = $lines !== [] ? json_decode(end($lines), true) : null;
        $this->assertIsArray($answer, "run_booking_save_innodb.php must emit JSON on its last stdout line.\nstdout: $stdout\nstderr: $stderr");
        $this->assertSame('', $stderr, 'the save ran without warnings');
        return $answer;
    }

    /** The statements of the save's transaction, from its begin. */
    private static function transaction(array $res): array
    {
        $begin = array_search('begin', $res['statements'], true);
        self::assertNotFalse($begin, 'the save opened no transaction: ' . json_encode($res['statements']));
        return array_slice($res['statements'], (int)$begin);
    }

    /** @return list<string> the writes to rentalperiod */
    private static function writes(array $res): array
    {
        return array_values(array_filter($res['statements'], static fn(string $sql): bool => (bool)preg_match('/^(insert into|update) rentalperiod/i', $sql)));
    }

    /** @return array<string, mixed> the body rental/edit.js sends to move the booking */
    private static function move(int $booking, string $from, string $to): array
    {
        return ['booking_id' => (string)$booking, 'from' => self::day($from), 'to' => self::day($to), 'fromDate' => $from, 'toDate' => $to, 'days' => 8, 'customer_id' => '7'];
    }

    /** @return array<string, mixed> the body rental/booking.js sends to book the stand */
    private static function book(string $from, string $to): array
    {
        return ['customer_id' => '7', 'item_id' => (string)self::STAND, 'from' => self::day($from), 'to' => self::day($to), 'fromDate' => $from, 'toDate' => $to, 'days' => 8];
    }

    public function test_a_move_onto_days_booked_while_it_waited_for_the_stand_is_refused(): void
    {
        // The reviewer's case: booking 5 is moved onto 01-12..08-12 while another save commits 28-11..05-12 on the stand.
        $res = $this->save('updateBooking', [self::booking(5, '2027-02-01', '2027-02-08')], self::booking(6, '2026-11-28', '2026-12-05'), self::move(5, '2026-12-01', '2026-12-08'));

        $this->assertSame(409, $res['status'], 'updateBooking answered: ' . $res['body']);
        $this->assertSame('Der er allerede en booking i det valgte tidsrum', json_decode($res['body'], true)['msg'] ?? null, 'the message rental/edit.js shows');
        $this->assertSame([], self::writes($res), 'the moved booking keeps its days');
        $transaction = self::transaction($res);
        $this->assertSame(['begin', self::LOCK], array_slice($transaction, 0, 2), 'the lock is the first statement of the transaction');
        $this->assertSame('rollback', end($transaction));
    }

    public function test_a_move_onto_days_that_stayed_free_while_it_waited_is_saved(): void
    {
        $res = $this->save('updateBooking', [self::booking(5, '2027-02-01', '2027-02-08')], self::booking(6, '2026-11-20', '2026-11-27'), self::move(5, '2026-12-01', '2026-12-08'));

        $this->assertSame(['msg' => 'Booking opdateret', 'booking_id' => 5, 'status' => 1], json_decode($res['body'], true), 'updateBooking answered: ' . $res['body']);
        $this->assertSame(['UPDATE rentalperiod SET rt_from = ' . self::day('2026-12-01') . ', rt_to = ' . self::day('2026-12-08') . ' WHERE id = 5'], self::writes($res));
        $transaction = self::transaction($res);
        $this->assertSame(['begin', self::LOCK], array_slice($transaction, 0, 2), 'the lock is the first statement of the transaction');
        $this->assertSame('commit', end($transaction));
    }

    public function test_a_staff_booking_onto_days_booked_while_it_waited_for_the_stand_is_refused(): void
    {
        $res = $this->save('createBooking', [], self::booking(6, '2026-11-28', '2026-12-05'), self::book('2026-12-01', '2026-12-08'));

        $this->assertSame(409, $res['status'], 'createBooking answered: ' . $res['body']);
        $this->assertSame('Der er allerede en booking i den valgte periode', json_decode($res['body'], true)['msg'] ?? null, 'the message rental/booking.js shows');
        $this->assertSame([], self::writes($res), 'no second booking for those days');
        $transaction = self::transaction($res);
        $this->assertSame(['begin', self::LOCK], array_slice($transaction, 0, 2), 'the lock is the first statement of the transaction');
        $this->assertSame('rollback', end($transaction));
    }

    /** @return array<string, array{string, string, string}> the file, where the save starts, and its write of the booking */
    public static function saves(): array
    {
        return [
            'remoteBooking/api.php?createBooking' => ['remoteBooking/api.php', 'if(isset($_GET["createBooking"])){', 'INSERT INTO rentalperiod'],
            'mysale/api.php?createBooking'        => ['mysale/api.php', 'if(isset($_GET["createBooking"])){', 'INSERT INTO rentalperiod'],
            'rental/rental.php?createBooking'     => ['rental/rental.php', 'if(isset($_GET["createBooking"])){', 'INSERT INTO rentalperiod'],
            'rental/rental.php?updateBooking'     => ['rental/rental.php', 'if(isset($_GET["updateBooking"])){', 'UPDATE rentalperiod SET rt_from'],
            'rental/upload.php, a row of the CSV' => ['rental/upload.php', 'while(!empty($data)){', 'INSERT INTO rentalperiod'],
        ];
    }

    /** The public saves and the importer cannot run without their includes; they open their transaction the same way. */
    #[DataProvider('saves')]
    public function test_every_save_of_a_booking_opens_its_transaction_with_the_stand_lock(string $file, string $start, string $write): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 3) . '/' . $file);
        $from = strpos($source, $start);
        $this->assertNotFalse($from, "$file no longer has $start");
        $to = strpos($source, $write, (int)$from);
        $this->assertNotFalse($to, "$file no longer writes the booking with $write");
        $save = substr($source, (int)$from, (int)$to - (int)$from);

        $this->assertStringContainsString('rental_begin_stand_lock(', $save, "$file writes the booking without locking the stand");
        $this->assertDoesNotMatchRegularExpression('/transaktion\(\s*[\'"]begin/i', $save, "$file opens the transaction itself, so a read in it can come before the lock");
    }

    /**
     * The staff booking overview (rental/index.js) asks before it deletes a booking or a block, and a No stops the delete.
     * The question on an invoiced booking in the booking view (index.php?showBooking) had its answer ignored, so Annuller
     * deleted as well. PHPUnit cannot run the page's script (a module the page loads with the API module rental/api/api.js),
     * so this reads it: every click handler that deletes returns on a No before the call, and no confirm() stands alone.
     */
    public function test_the_booking_overview_deletes_only_after_ok(): void
    {
        $source = str_replace("\r\n", "\n", (string)file_get_contents(dirname(__DIR__, 3) . '/rental/index.js'));
        preg_match_all('/^[ \t]*confirm\s*\(/m', $source, $unread, PREG_OFFSET_CAPTURE);
        $unreadLines = array_map(static fn (array $hit): int => substr_count($source, "\n", 0, $hit[1]) + 1, $unread[0]);
        $this->assertSame([], $unreadLines, 'rental/index.js asks a question on these lines and does not read the answer');

        $this->assertGreaterThan(0, preg_match_all('/\bawait\s+delete(?:Booking|Reservation)\s*\(/', $source, $calls, PREG_OFFSET_CAPTURE));
        foreach ($calls[0] as [$call, $at]) {
            $line = substr_count($source, "\n", 0, $at) + 1;
            $handler = strrpos(substr($source, 0, $at), '.addEventListener("click"');
            $this->assertNotFalse($handler, "rental/index.js:$line $call is not in a click handler");
            $before = substr($source, (int)$handler, $at - (int)$handler);
            $asked = preg_match_all('/\bconfirm\s*\(/', $before);
            $honoured = preg_match_all('/\bif\s*\(\s*confirm\s*\([^\n]*\)\s*===?\s*false\s*\)\s*return\b/', $before);
            $this->assertGreaterThan(0, $asked, "rental/index.js:$line deletes without asking");
            $this->assertSame($asked, $honoured, "rental/index.js:$line deletes also when the question is answered Annuller");
        }
    }
}
