<?php
// tests/characterization/rental/RemoteBookingRejectTest.test.php
//
// A rejected hold leaves nothing behind (testlab round 1,
// rental-public-booking, 2026-11-05).
//
// The booking page rejects its hold when the payment fails, is declined or
// times out (remoteBooking/index.js, mysale/remote.js): updateBooking with
// status "rejected". Both copies of the endpoint, remoteBooking/api.php and
// mysale/api.php, deleted the hold (rentalperiod) and its order (ordrer) but
// not the order's lines, so every rejected hold left its three ordrelinjer
// behind with an ordre_id no order has.
//
// updateBooking acts only on a booking the visitor's own session made:
// createBooking records the booking in the session (rental_booking_note()),
// and updateBooking refuses any other id with 403 and writes nothing. In Mit
// salg (mysale/api.php) the session must also still be signed in as the
// booking's customer at that regnskab (mySaleSignedInAs()), or, once the hold
// is gone, signed in at all. The id is taken as the page sends it, a whole
// number or its digits: a list, a float or text such as '2abc' is refused
// with 403. The two pages share the visitor's PHP session, and Mit salg keeps
// its bookings apart (rental_booking_mysale_notes()), so a hold made through
// one page is not rejected through the other. An invoiced order (status 3 or
// more, or a fakturanr) is never deleted: the rejection is refused with 409
// and the hold, the order and its lines stay.
//
// Driven through the two scripts themselves in a child process, with the
// bodies the booking page sends and the session the visitor's browser keeps
// between createBooking and updateBooking. They only take account database
// names that end in _<number>, which the test tenant's name does not, so they
// run against a fresh installation copied under such a name
// (support/PublicBookingDatabase.php).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261105 CL/LH remoteBooking/api.php is asked for the hold with the handle createCust gives the visitor's session, not the customer's id (rental_booking_customer_issue()).
// 20261115 CL/ASR Integration: The regnskab is a copy of a fresh installation (PublicBookingDatabase), as master's test environment (2bd490c6) has no template to clone.
// 20261116 CL/ASR The hold is rejected from the session that made it, which mysale/api.php has signed in as the customer.
//                 Another session's rejection is refused and leaves the hold, and an invoiced order is not deleted.
//                 A hold made through one page is not rejected through the other from the same session (Mit salg's notes are kept apart).
//                 An id that is not a booking id (a list, a float, true, text such as ' 2' or '2abc') is refused for both statuses with nothing written, while the id's digits as text still act on the hold.
//                 Mit salg refuses a signed-out session also when the hold is gone.
// 20261124 CL/ASR Comments state the current rule; no code change.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/support/PublicBookingDatabase.php';
require_once __DIR__ . '/../../../includes/stdFunc/rentalBookingCustomer.php';
require_once __DIR__ . '/../../../mysale/shopLookup.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class RemoteBookingRejectTest extends CharacterizationTestCase
{
    /** The regnskab the public endpoints are asked about: a name they accept. */
    private static string $booking = '';

    /** @var array{stands:list<int>, customer:int, other:int, sku:string} two stands of one product, the customer booking them and another customer */
    private static array $fixture = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $booking = PublicBookingDatabase::name('_rj_1');
        PublicBookingDatabase::create(static::class, $booking);
        self::$booking = $booking; // from here on tearDownAfterClass() drops it, whatever fails below

        $conn = CharacterizationEnv::connect($booking);
        try {
            $fx = new Fixtures($conn, self::$regnaar);
            $item = $fx->item();
            $stands = [];
            foreach (['Stand 1', 'Stand 2'] as $name) {
                $stands[] = (int) CharacterizationEnv::one($conn, 'INSERT INTO rentalitems (item_name, product_id) VALUES ($1, $2) RETURNING id', [$name, $item['id']])['id'];
            }
            self::$fixture = ['stands' => $stands, 'customer' => (int) $fx->debtor()['id'], 'other' => (int) $fx->debtor()['id'], 'sku' => $item['varenr']];
        } finally {
            pg_close($conn);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$booking !== '') {
            PublicBookingDatabase::drop(self::$booking);
            self::$booking = '';
        }
        parent::tearDownAfterClass();
    }

    /** Every test starts with both stands free. */
    protected function setUp(): void
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            CharacterizationEnv::rows($conn, 'DELETE FROM rentalperiod');
        } finally {
            pg_close($conn);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function endpoints(): array
    {
        return [
            'remoteBooking/api.php' => ['remoteBooking/api.php', 'id'],
            'mysale/api.php'        => ['mysale/api.php', 'db'],
        ];
    }

    /** @param array<int, mixed> $params */
    private static function countRows(string $sql, array $params = []): int
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            return (int) CharacterizationEnv::one($conn, $sql, $params)['n'];
        } finally {
            pg_close($conn);
        }
    }

    /** @param array<int, mixed> $params */
    private static function execute(string $sql, array $params = []): void
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            CharacterizationEnv::rows($conn, $sql, $params);
        } finally {
            pg_close($conn);
        }
    }

    private static function sessionId(): string
    {
        return 'chartest' . bin2hex(random_bytes(12));
    }

    /** What the script's createBooking keeps its bookings at the regnskab under in the session (rental_booking_note()). */
    private static function notes(string $script, string $db): string
    {
        return $script === 'mysale/api.php' ? rental_booking_mysale_notes($db) : $db;
    }

    /**
     * A visitor's browser before it books: a session cookie, and the session the booking page left. For mysale/api.php
     * that is Mit salg signed in as the customer at this regnskab (mysale.php, mySaleSignIn()).
     *
     * @return array{sid:string, session:array<string, mixed>}
     */
    private static function visitor(string $script, int $customer): array
    {
        $sid = self::sessionId();
        $session = [];
        if ($script === 'mysale/api.php') {
            mySaleSignIn($session, $sid, (string) $customer, self::$booking); // mysale.php has the adresser id as read from the database
        }
        return ['sid' => $sid, 'session' => $session];
    }

    /**
     * The hold the booking page made on the stand for a week from 01-03-2027, its order, and the visitor's session after
     * it. Made from the visitor's browser when one is given (a session that has booked before), else from a new one.
     *
     * @param array{sid:string, session:array<string, mixed>}|null $visitor
     * @return array{hold:int, order:int, visitor:array{sid:string, session:array<string, mixed>}}
     */
    private function hold(string $script, string $param, int $stand, ?array $visitor = null): array
    {
        $customer = self::$fixture['customer'];
        $visitor ??= self::visitor($script, $customer);
        if ($script === 'remoteBooking/api.php') { // its page names the customer by the handle createCust gave the session
            $customer = rental_booking_customer_issue($visitor['session'], self::$booking, $customer);
        }
        $body = json_encode([
            'product_id' => 1,
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-08',
            'weeks' => 1,
            'price' => 100,
            'item_id' => $stand,
            'cust_id' => $customer,
            'sku' => self::$fixture['sku'],
            'unit' => 'stk',
        ]);
        $res = $this->runChildJson('run_public_booking_api.php', [$script, 'createBooking&' . $param . '=' . rawurlencode(self::$booking), $body, $visitor['sid'], json_encode($visitor['session'])]);
        $hold = (int) (json_decode(trim($res['body']), true)['id'] ?? 0);
        $this->assertGreaterThan(0, $hold, "$script made no hold; it said: " . $res['body']);
        $this->assertIsArray($res['session'], 'the visitor keeps the session');
        $this->assertTrue(rental_booking_noted($res['session'], $hold, self::notes($script, self::$booking)), 'the session has the hold under its page');
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $order = (int) CharacterizationEnv::one($conn, 'SELECT order_id FROM rentalperiod WHERE id = $1', [$hold])['order_id'];
        } finally {
            pg_close($conn);
        }
        $this->assertGreaterThan(0, $order, 'the hold has its order');
        $this->assertSame(3, self::countRows('SELECT count(*) AS n FROM ordrelinjer WHERE ordre_id = $1', [$order]), 'the item, the stand and the period');
        return ['hold' => $hold, 'order' => $order, 'visitor' => ['sid' => $visitor['sid'], 'session' => $res['session']]];
    }

    /**
     * What updateBooking answered the page sending this status for this id, from the visitor's session (no session
     * cookie when null), and what the session held after it.
     *
     * @param mixed $id what the request names the booking by
     * @param array{sid:string, session:array<string, mixed>}|null $visitor
     * @return array{status:int|false, answer:mixed, session:mixed}
     */
    private function update(string $script, string $param, mixed $id, string $status, ?array $visitor): array
    {
        $args = [
            $script,
            'updateBooking&' . $param . '=' . rawurlencode(self::$booking),
            json_encode(['id' => $id, 'status' => $status]),
        ];
        if ($visitor !== null) {
            array_push($args, $visitor['sid'], json_encode($visitor['session']));
        }
        $res = $this->runChildJson('run_public_booking_api.php', $args);
        return ['status' => $res['status'], 'answer' => json_decode(trim($res['body']), true), 'session' => $res['session']];
    }

    /**
     * What updateBooking answered when the page rejected the hold, from the visitor's session (no session cookie when null).
     *
     * @param array{sid:string, session:array<string, mixed>}|null $visitor
     * @return array{status:int|false, answer:mixed, session:mixed}
     */
    private function reject(string $script, string $param, int $hold, ?array $visitor): array
    {
        return $this->update($script, $param, $hold, 'rejected', $visitor);
    }

    /**
     * Two holds one visitor's session made through the page, one on each stand. The first is booking 1, as the first
     * booking of a book is, so an id that casts to 1 names it; the second's visitor has both in the session.
     *
     * @return array{array{hold:int, order:int, visitor:array{sid:string, session:array<string, mixed>}}, array{hold:int, order:int, visitor:array{sid:string, session:array<string, mixed>}}}
     */
    private function twoHolds(string $script, string $param): array
    {
        self::execute("SELECT setval(pg_get_serial_sequence('rentalperiod', 'id'), 1, false)"); // setUp() left no hold
        $first = $this->hold($script, $param, self::$fixture['stands'][0]);
        $later = $this->hold($script, $param, self::$fixture['stands'][1], $first['visitor']);
        $this->assertSame(1, $first['hold'], 'the first hold is booking 1');
        $this->assertTrue(rental_booking_noted($later['visitor']['session'], $first['hold'], self::notes($script, self::$booking)), 'the session has both holds');
        return [$first, $later];
    }

    /**
     * The holds, their orders, the orders' lines and the count of postings, as they are now.
     *
     * @param list<array{hold:int, order:int}> $made
     * @return array<string, mixed>
     */
    private static function state(array $made): array
    {
        $holds = '{' . implode(',', array_column($made, 'hold')) . '}';
        $orders = '{' . implode(',', array_column($made, 'order')) . '}';
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            return [
                'holds' => CharacterizationEnv::rows($conn, 'SELECT * FROM rentalperiod WHERE id = ANY($1::int[]) ORDER BY id', [$holds]),
                'orders' => CharacterizationEnv::rows($conn, 'SELECT * FROM ordrer WHERE id = ANY($1::int[]) ORDER BY id', [$orders]),
                'lines' => CharacterizationEnv::rows($conn, 'SELECT * FROM ordrelinjer WHERE ordre_id = ANY($1::int[]) ORDER BY id', [$orders]),
                'postings' => CharacterizationEnv::one($conn, 'SELECT count(*) AS n FROM transaktioner')['n'],
            ];
        } finally {
            pg_close($conn);
        }
    }

    /** @param array{hold:int, order:int} $made */
    private function assertHoldKept(array $made, string $why): void
    {
        $this->assertSame(1, self::countRows('SELECT count(*) AS n FROM rentalperiod WHERE id = $1 AND order_id = $2', [$made['hold'], $made['order']]), "the hold stays: $why");
        $this->assertSame(1, self::countRows('SELECT count(*) AS n FROM ordrer WHERE id = $1', [$made['order']]), "its order stays: $why");
        $this->assertSame(3, self::countRows('SELECT count(*) AS n FROM ordrelinjer WHERE ordre_id = $1', [$made['order']]), "and its lines: $why");
    }

    #[DataProvider('endpoints')]
    public function test_a_rejected_hold_leaves_no_order_and_no_order_lines(string $script, string $param): void
    {
        $made = $this->hold($script, $param, self::$fixture['stands'][0]);

        $res = $this->reject($script, $param, $made['hold'], $made['visitor']);

        $this->assertSame(['status' => 'deleted'], $res['answer']);
        $this->assertSame(0, self::countRows('SELECT count(*) AS n FROM rentalperiod WHERE id = $1', [$made['hold']]), 'the hold is gone');
        $this->assertSame(0, self::countRows('SELECT count(*) AS n FROM ordrer WHERE id = $1', [$made['order']]), 'its order is gone');
        $this->assertSame(0, self::countRows('SELECT count(*) AS n FROM ordrelinjer WHERE ordre_id = $1', [$made['order']]), 'and so are its lines');
    }

    #[DataProvider('endpoints')]
    public function test_rejecting_a_hold_leaves_another_holds_order_and_lines(string $script, string $param): void
    {
        $rejected = $this->hold($script, $param, self::$fixture['stands'][0]);
        $kept = $this->hold($script, $param, self::$fixture['stands'][1]);

        $this->reject($script, $param, $rejected['hold'], $rejected['visitor']);

        $this->assertHoldKept($kept, 'another hold was rejected');
    }

    #[DataProvider('endpoints')]
    public function test_rejecting_a_hold_with_no_order_deletes_no_order_line(string $script, string $param): void
    {
        // createBooking writes the hold with order_id 0 and sets it once the order is made; a line with ordre_id 0 is not the hold's.
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $hold = (int) CharacterizationEnv::one(
                $conn,
                'INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id) VALUES ($1, $2, $3, $4, 0) RETURNING id',
                [self::$fixture['stands'][0], self::$fixture['customer'], strtotime('2027-04-01'), strtotime('2027-04-08')]
            )['id'];
            CharacterizationEnv::rows($conn, "INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 1, 0, 0, 0, 0, 'chartest stray line')");
        } finally {
            pg_close($conn);
        }
        $lines = self::countRows('SELECT count(*) AS n FROM ordrelinjer');
        $visitor = self::visitor($script, self::$fixture['customer']);
        rental_booking_note($visitor['session'], self::notes($script, self::$booking), $hold); // as createBooking left the session that made the hold

        $this->assertSame(['status' => 'deleted'], $this->reject($script, $param, $hold, $visitor)['answer']);

        $this->assertSame(0, self::countRows('SELECT count(*) AS n FROM rentalperiod WHERE id = $1', [$hold]), 'the hold is gone');
        $this->assertSame($lines, self::countRows('SELECT count(*) AS n FROM ordrelinjer'), 'no order line is deleted');
    }

    /**
     * Sessions that did not make the hold, or (Mit salg) no longer have its customer signed in, and the session that
     * made it asking the other page. '<made>' is the session that made it, changed as the case says.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function claimsThatAreNotTheVisitors(): array
    {
        $cases = [];
        foreach (self::endpoints() as $endpoint => [$script, $param]) {
            $cases["$endpoint: no session cookie"] = [$script, $param, 'no session'];
            $cases["$endpoint: a session that made no hold"] = [$script, $param, 'fresh session'];
            $cases["$endpoint: the session that made another hold"] = [$script, $param, 'another hold'];
            $cases["$endpoint: the session that made it, noted at another regnskab"] = [$script, $param, 'another regnskab'];
            $cases["$endpoint: the session that made it, through the other page"] = [$script, $param, 'other page'];
        }
        $cases['mysale/api.php: the session that made it, now signed in as another customer'] = ['mysale/api.php', 'db', 'signed in as another'];
        $cases['mysale/api.php: the session that made it, signed out'] = ['mysale/api.php', 'db', 'signed out'];
        return $cases;
    }

    #[DataProvider('claimsThatAreNotTheVisitors')]
    public function test_a_hold_is_not_rejected_from_a_session_that_has_no_claim_to_it(string $script, string $param, string $claim): void
    {
        $made = $this->hold($script, $param, self::$fixture['stands'][0]);
        $visitor = $made['visitor'];
        [$askScript, $askParam] = [$script, $param];
        switch ($claim) {
            case 'no session':
                $visitor = null;
                break;
            case 'fresh session': // for Mit salg signed in as the hold's customer, but not the session that booked
                $visitor = self::visitor($script, self::$fixture['customer']);
                break;
            case 'another hold':
                $visitor = $this->hold($script, $param, self::$fixture['stands'][1])['visitor'];
                break;
            case 'another regnskab':
                $notes = $visitor['session']['rentalBookings'][self::notes($script, self::$booking)] ?? [];
                $this->assertContains($made['hold'], $notes, 'the notes that are moved have the hold');
                $visitor['session']['rentalBookings'] = [self::notes($script, PublicBookingDatabase::name('_rj_2')) => $notes];
                break;
            case 'other page': // both pages share the visitor's session; Mit salg has the hold's customer signed in
                [$askScript, $askParam] = $script === 'mysale/api.php' ? self::endpoints()['remoteBooking/api.php'] : self::endpoints()['mysale/api.php'];
                if ($askScript === 'mysale/api.php') {
                    mySaleSignIn($visitor['session'], $visitor['sid'], (string) self::$fixture['customer'], self::$booking);
                }
                break;
            case 'signed in as another':
                mySaleSignIn($visitor['session'], $visitor['sid'], (string) self::$fixture['other'], self::$booking);
                break;
            case 'signed out':
                unset($visitor['session']['mySalePw'], $visitor['session']['mySaleAcId'], $visitor['session']['mySaleDb']); // what mySaleCustomerLogout() leaves
                break;
        }

        $res = $this->reject($askScript, $askParam, $made['hold'], $visitor);

        $this->assertSame(403, $res['status'], 'updateBooking answered: ' . json_encode($res['answer']));
        $this->assertIsArray($res['answer']);
        $this->assertArrayHasKey('error', $res['answer'], 'an error the page can show');
        $this->assertHoldKept($made, 'the rejection came from a session with no claim to it');
    }

    /** @return array<string, array{string, string, string}> */
    public static function invoicedOrders(): array
    {
        $cases = [];
        foreach (self::endpoints() as $endpoint => [$script, $param]) {
            $cases["$endpoint: status 3"] = [$script, $param, 'UPDATE ordrer SET status = 3 WHERE id = $1'];
            $cases["$endpoint: an invoice number, status 2"] = [$script, $param, "UPDATE ordrer SET status = 2, fakturanr = '90001' WHERE id = \$1"];
        }
        return $cases;
    }

    #[DataProvider('invoicedOrders')]
    public function test_the_order_of_a_hold_is_not_deleted_once_it_is_invoiced(string $script, string $param, string $invoiced): void
    {
        $made = $this->hold($script, $param, self::$fixture['stands'][0]);
        self::execute($invoiced, [$made['order']]);
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $before = CharacterizationEnv::one($conn, 'SELECT * FROM ordrer WHERE id = $1', [$made['order']]);
        } finally {
            pg_close($conn);
        }

        $res = $this->reject($script, $param, $made['hold'], $made['visitor']);

        $this->assertSame(409, $res['status'], 'updateBooking answered: ' . json_encode($res['answer']));
        $this->assertIsArray($res['answer']);
        $this->assertArrayHasKey('error', $res['answer'], 'an error the page can show');
        $this->assertHoldKept($made, 'its order is invoiced');
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $this->assertSame($before, CharacterizationEnv::one($conn, 'SELECT * FROM ordrer WHERE id = $1', [$made['order']]), 'the order is as it was');
        } finally {
            pg_close($conn);
        }
    }

    /**
     * What a request can name a booking by that is not a booking id. The page sends a whole number, and its digits as
     * text are taken too. Each is made from the two holds of one session (twoHolds()), booking 1 and a later one, n.
     * Read as a whole number, the list, true and '1 OR 1=1' name booking 1, and n.9, ' n' and 'nabc' name n, so their
     * 403 comes from the shape of the id, not from a hold the session lacks.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function idsThatAreNotBookingIds(): array
    {
        $cases = [];
        foreach (self::endpoints() as $endpoint => [$script, $param]) {
            foreach (['a list [n]', 'a float n.9', 'true', 'false', 'null', "'1 OR 1=1'", "' n'", "'nabc'", "'0x' and n in hex", "'-n'", "'0'", "''"] as $shape) {
                $cases["$endpoint: $shape"] = [$script, $param, $shape];
            }
        }
        return $cases;
    }

    #[DataProvider('idsThatAreNotBookingIds')]
    public function test_an_id_that_is_not_a_booking_id_is_refused_and_writes_nothing(string $script, string $param, string $shape): void
    {
        [$first, $later] = $this->twoHolds($script, $param);
        $n = $later['hold'];
        $id = match ($shape) {
            'a list [n]'        => [$n],
            'a float n.9'       => $n + 0.9,
            'true'              => true,
            'false'             => false,
            'null'              => null,
            "'1 OR 1=1'"        => '1 OR 1=1',
            "' n'"              => ' ' . $n,
            "'nabc'"            => $n . 'abc',
            "'0x' and n in hex" => '0x' . dechex($n),
            "'-n'"              => '-' . $n,
            "'0'"               => '0',
            "''"                => '',
        };
        $before = self::state([$first, $later]);

        foreach (['rejected', 'approved'] as $status) {
            $res = $this->update($script, $param, $id, $status, $later['visitor']);

            $asked = "$status, id " . json_encode($id);
            $this->assertSame(403, $res['status'], "$asked: updateBooking answered " . json_encode($res['answer']));
            $this->assertIsArray($res['answer']);
            $this->assertArrayHasKey('error', $res['answer'], "$asked: an error the page can show");
            $this->assertSame($before, self::state([$first, $later]), "$asked: both holds, their orders and lines stay as they were");
            foreach ([$first, $later] as $made) {
                $this->assertTrue(rental_booking_noted($res['session'], $made['hold'], self::notes($script, self::$booking)), "$asked: the session keeps hold {$made['hold']}");
            }
        }
    }

    #[DataProvider('endpoints')]
    public function test_a_booking_id_sent_as_digits_still_acts_on_that_hold_only(string $script, string $param): void
    {
        [$first, $later] = $this->twoHolds($script, $param);
        $notes = self::notes($script, self::$booking);

        $res = $this->update($script, $param, (string) $first['hold'], 'rejected', $later['visitor']);

        $this->assertSame(['status' => 'deleted'], $res['answer'], 'updateBooking answered with HTTP ' . var_export($res['status'], true));
        $this->assertSame(0, self::countRows('SELECT count(*) AS n FROM rentalperiod WHERE id = $1', [$first['hold']]), 'the hold is gone');
        $this->assertSame(0, self::countRows('SELECT count(*) AS n FROM ordrer WHERE id = $1', [$first['order']]), 'its order is gone');
        $this->assertSame(0, self::countRows('SELECT count(*) AS n FROM ordrelinjer WHERE ordre_id = $1', [$first['order']]), 'and so are its lines');
        $this->assertHoldKept($later, 'another hold of the session was rejected');
        $this->assertFalse(rental_booking_noted($res['session'], $first['hold'], $notes), 'the session no longer has the rejected hold');
        $this->assertTrue(rental_booking_noted($res['session'], $later['hold'], $notes), 'and keeps the other');

        $res = $this->update($script, $param, (string) $later['hold'], 'approved', ['sid' => $later['visitor']['sid'], 'session' => $res['session']]);

        $this->assertSame(['status' => 'approved'], $res['answer'], 'updateBooking answered with HTTP ' . var_export($res['status'], true));
        $this->assertSame(1, self::countRows('SELECT count(*) AS n FROM ordrer WHERE id = $1 AND status >= 3', [$later['order']]), 'its order is invoiced');
    }

    /** @return array<string, array{string, bool}> */
    public static function mitSalgHoldsThatAreGone(): array
    {
        return [
            'approved, signed out'      => ['approved', false],
            'rejected, signed out'      => ['rejected', false],
            'approved, still signed in' => ['approved', true],
            'rejected, still signed in' => ['rejected', true],
        ];
    }

    #[DataProvider('mitSalgHoldsThatAreGone')]
    public function test_mit_salg_asks_for_its_sign_in_also_when_the_hold_is_gone(string $status, bool $signedIn): void
    {
        $made = $this->hold('mysale/api.php', 'db', self::$fixture['stands'][0]);
        self::execute('DELETE FROM rentalperiod WHERE id = $1', [$made['hold']]); // as rental/rental.php removes an expired hold; its order stays
        $visitor = $made['visitor'];
        if (!$signedIn) {
            unset($visitor['session']['mySalePw'], $visitor['session']['mySaleAcId'], $visitor['session']['mySaleDb']); // what mySaleCustomerLogout() leaves
        }

        $res = $this->update('mysale/api.php', 'db', $made['hold'], $status, $visitor);

        if (!$signedIn) {
            $this->assertSame(403, $res['status'], 'updateBooking answered: ' . json_encode($res['answer']));
            $this->assertIsArray($res['answer']);
            $this->assertArrayHasKey('error', $res['answer'], 'an error the page can show');
            $this->assertTrue(rental_booking_noted($res['session'], $made['hold'], self::notes('mysale/api.php', self::$booking)), 'the session keeps the hold');
        } elseif ($status === 'approved') { // the customer the session is signed in as: nothing to invoice
            $this->assertSame(409, $res['status'], 'updateBooking answered: ' . json_encode($res['answer']));
        } else { // and nothing left to delete
            $this->assertSame(['status' => 'deleted'], $res['answer'], 'updateBooking answered with HTTP ' . var_export($res['status'], true));
        }
        $this->assertSame(1, self::countRows('SELECT count(*) AS n FROM ordrer WHERE id = $1', [$made['order']]), 'the order stays');
        $this->assertSame(3, self::countRows('SELECT count(*) AS n FROM ordrelinjer WHERE ordre_id = $1', [$made['order']]), 'and its lines');
    }
}
