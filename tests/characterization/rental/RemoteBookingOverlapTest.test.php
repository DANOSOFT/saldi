<?php
// tests/characterization/rental/RemoteBookingOverlapTest.test.php
//
// A stand is booked once for a day (testlab round 1, rental-public-booking,
// 2026-11-05): the login-free booking refuses a stand that is already booked
// or blocked in the period asked for, and the staff saves refuse one that is
// already booked, also when a public and a staff save come at once.
//
// remoteBooking/api.php?createBooking, and its copy in mysale/api.php,
// inserted a hold and its order for whatever stand and period the request
// named. Only the page's calendar kept customers off taken days, so a request
// for 01-12..08-12-2026 on a stand held 28-11..05-12 got a second hold and a
// second order for the same days.
//
// Taken means taken on a calendar day, whatever time of day the rows carry:
// the staff booking (rental/booking.js) stores UTC midnight, an hour after
// the public booking's local midnight. Two requests at once for one free
// stand book it once.
//
// The staff saves, rental/rental.php?createBooking and ?updateBooking, took
// neither the stand's lock nor a look at its bookings, so a staff booking
// saved while a public request had checked the stand and not yet written gave
// the stand two bookings on the same days, and so did one for days booked
// since the staff page loaded. They now refuse days the stand has another
// booking on, under the same lock. A block does not stop staff: the staff
// page lets them book over one after asking. updateBooking stopped with a
// fatal error before it saved anything, reading the order's status from the
// query result instead of its row.
//
// The booking importer, rental/upload.php, only skipped a row it had imported
// before, the same customer on the same stand and days. A row for days the
// stand has another booking on is now not imported either, under the same
// lock, and is written to customer.txt with the other rows it does not import.
//
// Driven through the scripts themselves in a child process, with the booking
// as the JSON body remoteBooking/index.js, mysale/remote.js, rental/booking.js
// and rental/edit.js send. The public scripts only take account database names
// that end in _<number>, which the test tenant's name does not, so they run
// against a clone of the template named that way, and the staff requests run
// in the test session moved to that clone.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261105 CL/LH remoteBooking/api.php is asked for a booking with the handle createCust gives the visitor's session, not the customer's id (rental_booking_customer_issue()).
//                Two requests at once come from two visitors, each with a session of its own.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../../../includes/stdFunc/rentalBookingCustomer.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class RemoteBookingOverlapTest extends CharacterizationTestCase
{
    /** The regnskab the public endpoints are asked about: a name they accept. */
    private static string $booking = '';

    /** @var array{stand:int, other:int, staff:int, customer:int, kontonr:string, sku:string} the stands of one product and the customer booking them */
    private static array $fixture = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $booking = CharacterizationEnv::testDb() . '_ov_1';
        if (!preg_match('/^[a-z][a-z0-9_]*_[0-9]+$/', $booking) || in_array($booking, [CharacterizationEnv::templateDb(), CharacterizationEnv::masterDb()], true)) {
            throw new RuntimeException('unsafe database name ' . $booking);
        }
        self::$booking = $booking; // from here on tearDownAfterClass() drops it, whatever fails below
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $booking));
            CharacterizationEnv::rows($master, 'CREATE DATABASE ' . pg_escape_identifier($master, $booking) . ' TEMPLATE ' . pg_escape_identifier($master, CharacterizationEnv::templateDb()));
            // Registered like the test tenant, so the staff requests can open it.
            CharacterizationEnv::rows($master, 'DELETE FROM regnskab WHERE db = $1', [$booking]);
            CharacterizationEnv::rows(
                $master,
                "INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version, sidst, brugerantal, posteringer, lukket, administrator)
                 SELECT 'chartest_overlap', dbhost, dbuser, $1, version, sidst, brugerantal, 1000000, '', administrator
                 FROM regnskab WHERE db = $2",
                [$booking, CharacterizationEnv::testDb()]
            );
        } finally {
            pg_close($master);
        }

        $conn = CharacterizationEnv::connect($booking);
        try {
            $fx = new Fixtures($conn, self::$regnaar);
            $item = $fx->item();
            $stands = [];
            foreach (['Stand 1', 'Stand 2', 'Stand 3'] as $name) {
                $stands[] = (int) CharacterizationEnv::one($conn, 'INSERT INTO rentalitems (item_name, product_id) VALUES ($1, $2) RETURNING id', [$name, $item['id']])['id'];
            }
            $customer = $fx->debtor();
            self::$fixture = ['stand' => $stands[0], 'other' => $stands[1], 'staff' => $stands[2], 'customer' => $customer['id'], 'kontonr' => $customer['kontonr'], 'sku' => $item['varenr']];
        } finally {
            pg_close($conn);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$booking !== '') {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            try {
                self::moveSession($master, CharacterizationEnv::testDb());
                CharacterizationEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()', [self::$booking]);
                CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, self::$booking));
                CharacterizationEnv::rows($master, "DELETE FROM regnskab WHERE db = $1 AND regnskab = 'chartest_overlap'", [self::$booking]);
            } finally {
                pg_close($master);
            }
            self::$booking = '';
        }
        parent::tearDownAfterClass();
    }

    /**
     * Every test starts from the lab's state: the stand held 28-11..05-12-2026 and blocked 20-12..31-12, no closed day.
     * The staff stand has the same hold and a block from 10-01-2027, both as the staff booking page writes them.
     * The other stand is free. The session is in the clone.
     */
    protected function setUp(): void
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            self::moveSession($master, self::$booking);
        } finally {
            pg_close($master);
        }
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            CharacterizationEnv::rows($conn, 'DELETE FROM rentalperiod');
            CharacterizationEnv::rows($conn, 'DELETE FROM rentalreserved');
            CharacterizationEnv::rows($conn, 'DELETE FROM rentalclosed');
            CharacterizationEnv::rows(
                $conn,
                'INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id) VALUES ($1, $2, $3, $4, 0)',
                [self::$fixture['stand'], self::$fixture['customer'], self::day('2026-11-28'), self::day('2026-12-05')]
            );
            CharacterizationEnv::rows(
                $conn,
                "INSERT INTO rentalreserved (item_id, rr_from, rr_to, comment) VALUES ($1, $2, $3, '')",
                [self::$fixture['stand'], self::day('2026-12-20'), self::day('2026-12-31')]
            );
            CharacterizationEnv::rows(
                $conn,
                'INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id) VALUES ($1, $2, $3, $4, 0)',
                [self::$fixture['staff'], self::$fixture['customer'], self::staffDay('2026-11-28'), self::staffDay('2026-12-05')]
            );
            CharacterizationEnv::rows(
                $conn,
                "INSERT INTO rentalreserved (item_id, rr_from, rr_to, comment) VALUES ($1, $2, $3, '')",
                [self::$fixture['staff'], self::staffDay('2027-01-10'), self::staffDay('2027-01-20')]
            );
        } finally {
            pg_close($conn);
        }
    }

    protected function tearDown(): void
    {
        // The other suites expect the session in the test tenant, whatever this one did.
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            self::moveSession($master, CharacterizationEnv::testDb());
        } finally {
            pg_close($master);
        }
    }

    /** What admin/aaben_regnskab.php does when the user opens another regnskab: the same session, another db. */
    private static function moveSession($master, string $db): void
    {
        CharacterizationEnv::rows($master, 'UPDATE online SET db = $1 WHERE session_id = $2', [$db, CharacterizationEnv::SESSION_ID]);
    }

    /** A day as the scripts store it: strtotime() of the date at midnight in includes/settings.php's timezone. */
    private static function day(string $date): int
    {
        return (new DateTimeImmutable($date, new DateTimeZone('Europe/Copenhagen')))->getTimestamp();
    }

    /** A day as rental/booking.js stores it: new Date('YYYY-MM-DD') / 1000, midnight UTC, an hour into the day in Copenhagen. */
    private static function staffDay(string $date): int
    {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->getTimestamp();
    }

    /** @return array{periods:int, orders:int} */
    private static function counts(): array
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            return [
                'periods' => (int) CharacterizationEnv::one($conn, 'SELECT count(*) AS n FROM rentalperiod')['n'],
                'orders' => (int) CharacterizationEnv::one($conn, 'SELECT count(*) AS n FROM ordrer')['n'],
            ];
        } finally {
            pg_close($conn);
        }
    }

    /** A booking as the staff booking page writes it, on the stand from $from to $to; its id. */
    private static function staffBooking(int $stand, string $from, string $to): int
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            return (int) CharacterizationEnv::one(
                $conn,
                'INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id) VALUES ($1, $2, $3, $4, 0) RETURNING id',
                [$stand, self::$fixture['customer'], self::staffDay($from), self::staffDay($to)]
            )['id'];
        } finally {
            pg_close($conn);
        }
    }

    /** @return array{0:int, 1:int}|null a booking's rt_from and rt_to */
    private static function period(int $id): ?array
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $row = CharacterizationEnv::one($conn, 'SELECT rt_from, rt_to FROM rentalperiod WHERE id = $1', [$id]);
        } finally {
            pg_close($conn);
        }
        return $row === null ? null : [(int) $row['rt_from'], (int) $row['rt_to']];
    }

    /** @return list<array{0:string, 1:string}> the first and last day of each booking of the stand, in Copenhagen, by first day */
    private static function bookedDays(int $stand): array
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $rows = CharacterizationEnv::rows($conn, 'SELECT rt_from, rt_to FROM rentalperiod WHERE item_id = $1 ORDER BY rt_from', [$stand]);
        } finally {
            pg_close($conn);
        }
        $zone = new DateTimeZone('Europe/Copenhagen');
        return array_map(static fn(array $row): array => [
            (new DateTimeImmutable('@' . $row['rt_from']))->setTimezone($zone)->format('Y-m-d'),
            (new DateTimeImmutable('@' . $row['rt_to']))->setTimezone($zone)->format('Y-m-d'),
        ], $rows);
    }

    /**
     * What createBooking answered when asked for the stand from $from to $to, the way the booking page asks.
     *
     * @return array{status:int|false, body:string}
     */
    private function book(string $script, string $param, int $stand, string $from, string $to): array
    {
        return $this->runChildJson('run_public_booking_api.php', self::bookingArgs($script, $param, $stand, $from, $to));
    }

    /**
     * What rental/rental.php?createBooking answered when staff booked the stand from $from to $to on rental/booking.php.
     *
     * @return array{status:int|false, body:string}
     */
    private function staffBook(int $stand, string $from, string $to): array
    {
        return $this->runChildJson('run_rental_api.php', self::staffBookingArgs($stand, $from, $to));
    }

    /**
     * What rental/rental.php?updateBooking answered when staff moved the booking to $from..$to on rental/edit.php.
     *
     * @return array{status:int|false, body:string}
     */
    private function staffMove(int $booking, string $from, string $to): array
    {
        return $this->runChildJson('run_rental_api.php', self::staffMoveArgs($booking, $from, $to));
    }

    /** @return list<string> run_rental_api.php's arguments for a booking as rental/booking.js sends it */
    private static function staffBookingArgs(int $stand, string $from, string $to): array
    {
        $body = json_encode([
            'customer_id' => (string) self::$fixture['customer'],
            'item_id' => (string) $stand,
            'from' => self::staffDay($from),
            'to' => self::staffDay($to),
            'fromDate' => $from,
            'toDate' => $to,
            'days' => 8,
        ]);
        return ['createBooking', CharacterizationEnv::SESSION_ID, 'localhost:5000', 'http', $body];
    }

    /** @return list<string> run_rental_api.php's arguments for a move as rental/edit.js sends it */
    private static function staffMoveArgs(int $booking, string $from, string $to): array
    {
        $body = json_encode([
            'booking_id' => (string) $booking,
            'from' => self::staffDay($from),
            'to' => self::staffDay($to),
            'fromDate' => $from,
            'toDate' => $to,
            'days' => 8,
            'customer_id' => (string) self::$fixture['customer'],
        ]);
        return ['updateBooking', CharacterizationEnv::SESSION_ID, 'localhost:5000', 'http', $body];
    }

    /** @return list<string> run_public_booking_api.php's arguments for that request, from a visitor with a session of its own */
    private static function bookingArgs(string $script, string $param, int $stand, string $from, string $to): array
    {
        $customer = self::$fixture['customer'];
        $session = [];
        if ($script === 'remoteBooking/api.php') { // its page names the customer by the handle createCust gave the session
            $customer = rental_booking_customer_issue($session, self::$booking, $customer);
        }
        $body = json_encode([
            'product_id' => 1,
            'start_date' => $from,
            'end_date' => $to,
            'weeks' => 1,
            'price' => 100,
            'item_id' => $stand,
            'cust_id' => $customer,
            'sku' => self::$fixture['sku'],
            'unit' => 'stk',
        ]);
        return [$script, 'createBooking&' . $param . '=' . rawurlencode(self::$booking), $body, 'chartest' . bin2hex(random_bytes(12)), json_encode($session)];
    }

    /** The stands by the names the importer's CSV gives them (rentalitems.item_name). */
    private const STAND_NAMES = ['stand' => 'Stand 1', 'other' => 'Stand 2', 'staff' => 'Stand 3'];

    /**
     * Puts a CSV for the importer where rental/upload.php reads it, a row per period on the stand, as the fixture's customer,
     * without a block (LangtidsLeje "nej"). Dates are DD-MM-YYYY, as the importer reads them.
     *
     * @param list<array{0:string, 1:string, 2:string}> $rows the stand, its first day and its last day
     * @return list<string> run_rental_upload_page.php's arguments for it
     */
    private static function importArgs(array $rows): array
    {
        $csv = "KundeNr;Fornavn;Efternavn;Type;UdstyrNavn;LangtidsLeje;StartDato;SlutDato\n";
        foreach ($rows as [$stand, $from, $to]) {
            $csv .= implode(';', [self::$fixture['kontonr'], 'Chartest', 'Kunde', 'Reol', self::STAND_NAMES[$stand], 'nej', $from, $to]) . "\n";
        }
        $name = 'chartest_overlap_' . getmypid() . '.csv';
        file_put_contents(self::rentalDir() . '/uploads/' . $name, $csv);
        return [CharacterizationEnv::SESSION_ID, $name];
    }

    private static function rentalDir(): string
    {
        return dirname(__DIR__, 3) . '/rental';
    }

    /**
     * Runs $run while the importer's CSV is in rental/uploads/, and puts its files back as they were: the CSV is removed,
     * customer.txt and period_id.txt get their old content again.
     *
     * @return string what the run added to customer.txt, the rows it did not import
     */
    private static function withImporterFiles(callable $run): string
    {
        $kept = [];
        foreach (['customer.txt', 'period_id.txt'] as $file) {
            $path = self::rentalDir() . '/' . $file;
            $kept[$path] = is_file($path) ? (string) file_get_contents($path) : null;
        }
        $customers = self::rentalDir() . '/customer.txt';
        try {
            $run();
            $after = is_file($customers) ? (string) file_get_contents($customers) : '';
            return substr($after, strlen($kept[$customers] ?? ''));
        } finally {
            foreach (glob(self::rentalDir() . '/uploads/chartest_overlap_*.csv') ?: [] as $csv) {
                unlink($csv);
            }
            foreach ($kept as $path => $content) {
                if ($content === null) {
                    @unlink($path);
                } else {
                    file_put_contents($path, $content);
                }
            }
        }
    }

    /**
     * What two requests answered when both had got as far as they could before either could write.
     *
     * Writes to rentalperiod wait behind a SHARE lock this test holds; reads do not. A public request that locks the
     * stand checks it, finds it free and waits at its INSERT, holding the stand. A staff request waits at the first
     * thing rental/rental.php writes, the clean-up of expired holds. A request that does not lock the stand checks it,
     * finds it free and waits at its INSERT or UPDATE. The lock is let go once both requests wait on a lock.
     *
     * @param list<array{0:string, 1:list<string>}> $requests the runner and its arguments, for each request
     * @return list<array{status:int|false, body:string}>
     */
    private function atOnce(array $requests): array
    {
        $gate = CharacterizationEnv::connect(self::$booking);
        $observer = CharacterizationEnv::connect(self::$booking);
        $children = [];
        try {
            CharacterizationEnv::rows($gate, 'BEGIN');
            CharacterizationEnv::rows($gate, 'LOCK TABLE rentalperiod IN SHARE MODE');
            foreach ($requests as [$runner, $args]) {
                $err = tmpfile();
                $cmd = array_merge([PHP_BINARY, dirname(__DIR__) . '/support/' . $runner], $args);
                $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $err], $pipes);
                $this->assertIsResource($proc, "could not start $runner");
                fclose($pipes[0]);
                $children[] = ['proc' => $proc, 'out' => $pipes[1], 'err' => $err, 'runner' => $runner];
            }
            $deadline = microtime(true) + 30;
            while ((int) CharacterizationEnv::one($observer, "SELECT count(*) AS n FROM pg_stat_activity WHERE datname = $1 AND wait_event_type = 'Lock'", [self::$booking])['n'] < 2) {
                foreach ($children as $child) {
                    if (!proc_get_status($child['proc'])['running']) {
                        break 2; // it answered without waiting: the asserts below say what
                    }
                }
                $this->assertLessThan($deadline, microtime(true), 'the two requests never both waited on a lock');
                usleep(20000);
            }
            CharacterizationEnv::rows($gate, 'COMMIT');
        } finally {
            pg_close($gate);
            pg_close($observer);
        }

        $answers = [];
        foreach ($children as $child) {
            $stdout = (string) stream_get_contents($child['out']);
            fclose($child['out']);
            proc_close($child['proc']);
            rewind($child['err']);
            $stderr = (string) stream_get_contents($child['err']);
            fclose($child['err']);
            $lines = array_values(array_filter(explode("\n", trim($stdout))));
            $answer = $lines !== [] ? json_decode(end($lines), true) : null;
            $this->assertIsArray($answer, "{$child['runner']} must emit JSON on its last stdout line.\nstdout: $stdout\nstderr: $stderr");
            $answers[] = $answer;
        }
        return $answers;
    }

    /** @return array<string, array{string, string}> */
    public static function endpoints(): array
    {
        return [
            'remoteBooking/api.php' => ['remoteBooking/api.php', 'id'],
            'mysale/api.php'        => ['mysale/api.php', 'db'],
        ];
    }

    /**
     * @param array<string, list<string>> $periods
     * @return array<string, list<string>>
     */
    private static function onEveryEndpoint(array $periods): array
    {
        $cases = [];
        foreach (self::endpoints() as $endpoint => $args) {
            foreach ($periods as $name => $period) {
                $cases["$endpoint: $name"] = array_merge($args, $period);
            }
        }
        return $cases;
    }

    /** @return array<string, list<string>> */
    public static function takenPeriods(): array
    {
        return self::onEveryEndpoint([
            "the lab's request, inside the hold"                            => ['stand', '2026-12-01', '2026-12-08'],
            'around the whole hold'                                         => ['stand', '2026-11-21', '2026-12-12'],
            'from the day the hold ends, a day the calendar does not offer' => ['stand', '2026-12-05', '2026-12-12'],
            'from noon on the day the hold ends'                            => ['stand', '2026-12-05 12:00', '2026-12-12 12:00'],
            'on days the stand is blocked'                                  => ['stand', '2026-12-24', '2026-12-31'],
            'up to the day a staff booking starts'                          => ['staff', '2026-11-21', '2026-11-28'],
            'from the day a staff booking ends'                             => ['staff', '2026-12-05', '2026-12-12'],
            'up to the day a staff block starts'                            => ['staff', '2027-01-03', '2027-01-10'],
        ]);
    }

    /** @return array<string, list<string>> */
    public static function freePeriods(): array
    {
        return self::onEveryEndpoint([
            'between the hold and the block'       => ['stand', '2026-12-11', '2026-12-18'],
            "the hold's days on another stand"     => ['other', '2026-12-01', '2026-12-08'],
            'up to the day before a staff booking' => ['staff', '2026-11-20', '2026-11-27'],
            'from the day after a staff booking'   => ['staff', '2026-12-06', '2026-12-13'],
        ]);
    }

    #[DataProvider('takenPeriods')]
    public function test_a_stand_taken_on_a_day_of_the_period_is_refused_without_a_hold_or_an_order(string $script, string $param, string $stand, string $from, string $to): void
    {
        $before = self::counts();

        $res = $this->book($script, $param, self::$fixture[$stand], $from, $to);

        $this->assertSame(409, $res['status'], "$script answered: " . $res['body']);
        $this->assertSame($before, self::counts(), 'no second hold and no order for days the stand is taken');
    }

    #[DataProvider('freePeriods')]
    public function test_a_stand_free_on_every_day_of_the_period_is_booked(string $script, string $param, string $stand, string $from, string $to): void
    {
        $res = $this->book($script, $param, self::$fixture[$stand], $from, $to);

        $this->assertNotContains($res['status'], [400, 409], "$script answered: " . $res['body']);
        $answer = json_decode(trim($res['body']), true);
        $this->assertIsArray($answer, "$script answers JSON; it said: " . $res['body']);
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $hold = CharacterizationEnv::one($conn, 'SELECT id, rt_from, rt_to FROM rentalperiod WHERE id = $1 AND item_id = $2', [(int) ($answer['id'] ?? 0), self::$fixture[$stand]]);
        } finally {
            pg_close($conn);
        }
        $this->assertNotNull($hold, 'the answer names the new hold on the stand');
        $this->assertSame([self::day($from), self::day($to)], [(int) $hold['rt_from'], (int) $hold['rt_to']]);
    }

    #[DataProvider('endpoints')]
    public function test_two_requests_at_once_for_the_same_free_stand_book_it_once(string $script, string $param): void
    {
        $before = self::counts();

        $request = fn(): array => ['run_public_booking_api.php', self::bookingArgs($script, $param, self::$fixture['stand'], '2026-12-11', '2026-12-18')];
        $answers = $this->atOnce([$request(), $request()]);

        $refused = array_filter($answers, static fn(array $answer): bool => $answer['status'] === 409);
        $this->assertCount(1, $refused, "one request books the stand and the other gets 409; $script answered: " . json_encode($answers));
        $this->assertSame(['periods' => $before['periods'] + 1, 'orders' => $before['orders'] + 1], self::counts(), 'one hold and one order');
    }

    #[DataProvider('endpoints')]
    public function test_a_stand_that_does_not_exist_is_refused(string $script, string $param): void
    {
        $before = self::counts();

        $res = $this->book($script, $param, max(self::$fixture['stand'], self::$fixture['other'], self::$fixture['staff']) + 1000, '2026-12-11', '2026-12-18');

        $this->assertSame(400, $res['status'], "$script answered: " . $res['body']);
        $this->assertSame($before, self::counts());
    }

    #[DataProvider('endpoints')]
    public function test_a_period_that_ends_before_it_starts_is_refused(string $script, string $param): void
    {
        $before = self::counts();

        $res = $this->book($script, $param, self::$fixture['stand'], '2026-12-18', '2026-12-11');

        $this->assertSame(400, $res['status'], "$script answered: " . $res['body']);
        $this->assertSame($before, self::counts());
    }

    /** @return array<string, array{string, string, string}> */
    public static function periodsStaffCannotBook(): array
    {
        return [
            "the lab's days, inside the public hold"            => ['stand', '2026-12-01', '2026-12-08'],
            'around the whole public hold'                      => ['stand', '2026-11-21', '2026-12-12'],
            'from the day the public hold ends'                 => ['stand', '2026-12-05', '2026-12-12'],
            'up to the day a staff booking starts'              => ['staff', '2026-11-21', '2026-11-28'],
            "the staff booking's own days, booked a second time" => ['staff', '2026-11-28', '2026-12-05'],
        ];
    }

    /** @return array<string, array{string, string, string}> */
    public static function periodsStaffCanBook(): array
    {
        return [
            'between the hold and the block'                   => ['stand', '2026-12-11', '2026-12-18'],
            'on days the stand is blocked, which staff may book' => ['stand', '2026-12-24', '2026-12-31'],
            "the hold's days on another stand"                 => ['other', '2026-12-01', '2026-12-08'],
            'from the day after a staff booking'               => ['staff', '2026-12-06', '2026-12-13'],
        ];
    }

    #[DataProvider('periodsStaffCannotBook')]
    public function test_staff_cannot_book_a_stand_on_a_day_it_has_another_booking(string $stand, string $from, string $to): void
    {
        $before = self::counts();

        $res = $this->staffBook(self::$fixture[$stand], $from, $to);

        $this->assertSame(409, $res['status'], 'rental/rental.php answered: ' . $res['body']);
        $answer = json_decode(trim($res['body']), true);
        $this->assertSame('Der er allerede en booking i den valgte periode', $answer['msg'] ?? null, 'the message the page shows for it');
        $this->assertArrayNotHasKey('id', $answer, 'no id, so rental/booking.js makes no block and no order');
        $this->assertSame($before, self::counts(), 'no second booking for days the stand is booked');
    }

    #[DataProvider('periodsStaffCanBook')]
    public function test_staff_can_book_a_stand_on_days_it_has_no_other_booking(string $stand, string $from, string $to): void
    {
        $res = $this->staffBook(self::$fixture[$stand], $from, $to);

        $this->assertNotContains($res['status'], [400, 409], 'rental/rental.php answered: ' . $res['body']);
        $answer = json_decode(trim($res['body']), true);
        $this->assertSame('Booking oprettet', $answer['msg'] ?? null, 'rental/rental.php answered: ' . $res['body']);
        $this->assertSame([self::staffDay($from), self::staffDay($to)], self::period((int) ($answer['id'] ?? 0)), 'the answer names the new booking');
    }

    public function test_staff_cannot_book_a_period_that_ends_before_it_starts(): void
    {
        $before = self::counts();

        $res = $this->staffBook(self::$fixture['other'], '2026-12-18', '2026-12-11');

        $this->assertSame(400, $res['status'], 'rental/rental.php answered: ' . $res['body']);
        $this->assertSame($before, self::counts());
    }

    /** @return array<string, array{string, string}> */
    public static function movesOntoAnotherBooking(): array
    {
        return [
            "onto the other booking's days"           => ['2026-12-01', '2026-12-08'],
            'from the day the other booking ends'     => ['2026-12-05', '2026-12-12'],
            'around the other booking'                => ['2026-11-21', '2026-12-19'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function movesOntoFreeDays(): array
    {
        return [
            'partly onto its own days'                => ['2026-12-14', '2026-12-21'],
            'from the day after the other booking'    => ['2026-12-06', '2026-12-13'],
            'onto days the stand is blocked'          => ['2027-01-12', '2027-01-19'],
        ];
    }

    /** The staff stand's booking 28-11..05-12 stays; a second one, 12-12..19-12, is moved. */
    #[DataProvider('movesOntoAnotherBooking')]
    public function test_staff_cannot_move_a_booking_onto_a_day_the_stand_has_another_booking(string $from, string $to): void
    {
        $moved = self::staffBooking(self::$fixture['staff'], '2026-12-12', '2026-12-19');
        $before = self::bookedDays(self::$fixture['staff']);

        $res = $this->staffMove($moved, $from, $to);

        $this->assertSame(409, $res['status'], 'rental/rental.php answered: ' . $res['body']);
        $answer = json_decode(trim($res['body']), true);
        $this->assertSame('Der er allerede en booking i det valgte tidsrum', $answer['msg'] ?? null, 'the message the page shows for it');
        $this->assertSame($before, self::bookedDays(self::$fixture['staff']), 'both bookings keep their days');
    }

    #[DataProvider('movesOntoFreeDays')]
    public function test_staff_can_move_a_booking_onto_days_the_stand_has_no_other_booking(string $from, string $to): void
    {
        $moved = self::staffBooking(self::$fixture['staff'], '2026-12-12', '2026-12-19');

        $res = $this->staffMove($moved, $from, $to);

        $this->assertNotContains($res['status'], [400, 409], 'rental/rental.php answered: ' . $res['body']);
        $answer = json_decode(trim($res['body']), true);
        $this->assertSame(['msg' => 'Booking opdateret', 'booking_id' => $moved, 'status' => 1], $answer, 'rental/edit.js then updates the order');
        $this->assertSame([self::staffDay($from), self::staffDay($to)], self::period($moved));
    }

    public function test_an_invoiced_booking_moved_onto_free_days_tells_the_page_to_leave_its_order_alone(): void
    {
        $moved = self::staffBooking(self::$fixture['staff'], '2026-12-12', '2026-12-19');
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $order = CharacterizationEnv::one(
                $conn,
                "INSERT INTO ordrer (konto_id, art, status, ordrenr) VALUES ($1, 'DO', 3, (SELECT COALESCE(max(ordrenr), 0) + 1 FROM ordrer)) RETURNING id",
                [self::$fixture['customer']]
            );
            CharacterizationEnv::rows($conn, 'UPDATE rentalperiod SET order_id = $1 WHERE id = $2', [(int) $order['id'], $moved]);
        } finally {
            pg_close($conn);
        }

        $res = $this->staffMove($moved, '2026-12-14', '2026-12-21');

        $this->assertSame(['msg' => 'Booking opdateret', 'status' => 2], json_decode(trim($res['body']), true), 'rental/edit.js then does not rebuild the invoiced order');
        $this->assertSame([self::staffDay('2026-12-14'), self::staffDay('2026-12-21')], self::period($moved));
    }

    public function test_staff_cannot_move_a_booking_to_a_period_that_ends_before_it_starts(): void
    {
        $moved = self::staffBooking(self::$fixture['staff'], '2026-12-12', '2026-12-19');

        $res = $this->staffMove($moved, '2026-12-19', '2026-12-12');

        $this->assertSame(400, $res['status'], 'rental/rental.php answered: ' . $res['body']);
        $this->assertSame([self::staffDay('2026-12-12'), self::staffDay('2026-12-19')], self::period($moved));
    }

    /**
     * The reviewer's case: a public request for 28-11..05-12 and a staff booking for 01-12..08-12 of a free stand at once.
     */
    #[DataProvider('endpoints')]
    public function test_a_public_and_a_staff_booking_at_once_for_overlapping_days_book_the_stand_once(string $script, string $param): void
    {
        $stand = self::$fixture['other'];

        $answers = $this->atOnce([
            ['run_public_booking_api.php', self::bookingArgs($script, $param, $stand, '2026-11-28', '2026-12-05')],
            ['run_rental_api.php', self::staffBookingArgs($stand, '2026-12-01', '2026-12-08')],
        ]);

        $refused = array_filter($answers, static fn(array $answer): bool => $answer['status'] === 409);
        $this->assertCount(1, $refused, "one of them books the stand and the other gets 409; they answered: " . json_encode($answers));
        $this->assertCount(1, self::bookedDays($stand), 'one booking of the stand: ' . json_encode(self::bookedDays($stand)));
    }

    /** A staff move onto 01-12..08-12 and a public request for 28-11..05-12 of the stand at once. */
    #[DataProvider('endpoints')]
    public function test_a_public_booking_and_a_staff_move_at_once_onto_overlapping_days_leave_one_booking_on_them(string $script, string $param): void
    {
        $stand = self::$fixture['other'];
        $moved = self::staffBooking($stand, '2027-02-01', '2027-02-08');

        $answers = $this->atOnce([
            ['run_public_booking_api.php', self::bookingArgs($script, $param, $stand, '2026-11-28', '2026-12-05')],
            ['run_rental_api.php', self::staffMoveArgs($moved, '2026-12-01', '2026-12-08')],
        ]);

        $refused = array_filter($answers, static fn(array $answer): bool => $answer['status'] === 409);
        $this->assertCount(1, $refused, "one of them gets the days and the other gets 409; they answered: " . json_encode($answers));
        $days = self::bookedDays($stand);
        $this->assertTrue(
            count($days) === 1 || $days[0][1] < $days[1][0],
            'no two bookings of the stand on one day: ' . json_encode($days)
        );
    }

    /** @return array<string, array{string, string, string}> */
    public static function rowsTheImporterLeavesOut(): array
    {
        return [
            "the reviewer's row, inside the public hold"     => ['stand', '01-12-2026', '08-12-2026'],
            'from the day the public hold ends'               => ['stand', '05-12-2026', '12-12-2026'],
            'up to the day a staff booking starts'            => ['staff', '21-11-2026', '28-11-2026'],
            'a period that ends before it starts'             => ['other', '18-12-2026', '11-12-2026'],
        ];
    }

    /** @return array<string, array{string, string, string}> */
    public static function rowsTheImporterImports(): array
    {
        return [
            'between the hold and the block'                     => ['stand', '11-12-2026', '18-12-2026'],
            'on days the stand is blocked, which staff may book' => ['stand', '24-12-2026', '31-12-2026'],
            "the hold's days on another stand"                   => ['other', '01-12-2026', '08-12-2026'],
        ];
    }

    #[DataProvider('rowsTheImporterLeavesOut')]
    public function test_the_importer_does_not_import_a_row_for_days_the_stand_has_another_booking(string $stand, string $from, string $to): void
    {
        $before = self::bookedDays(self::$fixture[$stand]);

        $notImported = self::withImporterFiles(function () use ($stand, $from, $to): void {
            $this->runChildJson('run_rental_upload_page.php', self::importArgs([[$stand, $from, $to]]));
        });

        $this->assertSame($before, self::bookedDays(self::$fixture[$stand]), 'no second booking of the stand');
        $this->assertStringContainsString(self::STAND_NAMES[$stand] . ",nej,$from,$to", $notImported, 'the row is kept in customer.txt with the other rows not imported');
    }

    #[DataProvider('rowsTheImporterImports')]
    public function test_the_importer_imports_a_row_for_days_the_stand_has_no_other_booking(string $stand, string $from, string $to): void
    {
        $before = self::bookedDays(self::$fixture[$stand]);

        $notImported = self::withImporterFiles(function () use ($stand, $from, $to): void {
            $this->runChildJson('run_rental_upload_page.php', self::importArgs([[$stand, $from, $to]]));
        });

        $this->assertSame('', $notImported, 'every row is imported');
        $day = static fn(string $date): string => DateTimeImmutable::createFromFormat('d-m-Y', $date)->format('Y-m-d');
        $expected = array_merge($before, [[$day($from), $day($to)]]);
        usort($expected, static fn(array $a, array $b): int => strcmp($a[0], $b[0]));
        $this->assertSame($expected, self::bookedDays(self::$fixture[$stand]), 'the row is the stand\'s new booking');
    }

    public function test_the_importer_skips_a_row_it_has_imported_before(): void
    {
        $notImported = self::withImporterFiles(function (): void {
            $this->runChildJson('run_rental_upload_page.php', self::importArgs([['other', '01-12-2026', '08-12-2026'], ['other', '01-12-2026', '08-12-2026']]));
        });

        $this->assertSame([['2026-12-01', '2026-12-08']], self::bookedDays(self::$fixture['other']), 'one booking');
        $this->assertSame('', $notImported, 'a row imported before is skipped, as it was');
    }

    /** A CSV row for 01-12..08-12 and a public request for 28-11..05-12 of a free stand at once. */
    #[DataProvider('endpoints')]
    public function test_an_imported_row_and_a_public_booking_at_once_for_overlapping_days_book_the_stand_once(string $script, string $param): void
    {
        $stand = self::$fixture['other'];

        $answers = [];
        self::withImporterFiles(function () use ($script, $param, $stand, &$answers): void {
            $answers = $this->atOnce([
                ['run_rental_upload_page.php', self::importArgs([['other', '01-12-2026', '08-12-2026']])],
                ['run_public_booking_api.php', self::bookingArgs($script, $param, $stand, '2026-11-28', '2026-12-05')],
            ]);
        });

        $this->assertCount(1, self::bookedDays($stand), 'one booking of the stand: ' . json_encode(self::bookedDays($stand)) . '; the public request answered ' . json_encode($answers[1]));
    }
}
