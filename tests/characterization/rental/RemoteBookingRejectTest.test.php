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
// Driven through the two scripts themselves in a child process, with the
// bodies the booking page sends. They only take account database names that
// end in _<number>, which the test tenant's name does not, so they run against
// a clone of the template named that way.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261105 CL/LH remoteBooking/api.php is asked for the hold with the handle createCust gives the visitor's session, not the customer's id (rental_booking_customer_issue()).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../../../includes/stdFunc/rentalBookingCustomer.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class RemoteBookingRejectTest extends CharacterizationTestCase
{
    /** The regnskab the public endpoints are asked about: a name they accept. */
    private static string $booking = '';

    /** @var array{stands:list<int>, customer:int, sku:string} two stands of one product and the customer booking them */
    private static array $fixture = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $booking = CharacterizationEnv::testDb() . '_rj_1';
        if (!preg_match('/^[a-z][a-z0-9_]*_[0-9]+$/', $booking) || in_array($booking, [CharacterizationEnv::templateDb(), CharacterizationEnv::masterDb()], true)) {
            throw new RuntimeException('unsafe database name ' . $booking);
        }
        self::$booking = $booking; // from here on tearDownAfterClass() drops it, whatever fails below
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $booking));
            CharacterizationEnv::rows($master, 'CREATE DATABASE ' . pg_escape_identifier($master, $booking) . ' TEMPLATE ' . pg_escape_identifier($master, CharacterizationEnv::templateDb()));
        } finally {
            pg_close($master);
        }

        $conn = CharacterizationEnv::connect($booking);
        try {
            $fx = new Fixtures($conn, self::$regnaar);
            $item = $fx->item();
            $stands = [];
            foreach (['Stand 1', 'Stand 2'] as $name) {
                $stands[] = (int) CharacterizationEnv::one($conn, 'INSERT INTO rentalitems (item_name, product_id) VALUES ($1, $2) RETURNING id', [$name, $item['id']])['id'];
            }
            self::$fixture = ['stands' => $stands, 'customer' => (int) $fx->debtor()['id'], 'sku' => $item['varenr']];
        } finally {
            pg_close($conn);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$booking !== '') {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            try {
                CharacterizationEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()', [self::$booking]);
                CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, self::$booking));
            } finally {
                pg_close($master);
            }
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

    /** @return array{hold:int, order:int} the hold the booking page made on the stand for a week from 01-03-2027, and its order */
    private function hold(string $script, string $param, int $stand): array
    {
        $customer = self::$fixture['customer'];
        $session = [];
        if ($script === 'remoteBooking/api.php') { // its page names the customer by the handle createCust gave the session
            $customer = rental_booking_customer_issue($session, self::$booking, $customer);
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
        $res = $this->runChildJson('run_public_booking_api.php', [$script, 'createBooking&' . $param . '=' . rawurlencode(self::$booking), $body, 'chartest' . bin2hex(random_bytes(12)), json_encode($session)]);
        $hold = (int) (json_decode(trim($res['body']), true)['id'] ?? 0);
        $this->assertGreaterThan(0, $hold, "$script made no hold; it said: " . $res['body']);
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $order = (int) CharacterizationEnv::one($conn, 'SELECT order_id FROM rentalperiod WHERE id = $1', [$hold])['order_id'];
        } finally {
            pg_close($conn);
        }
        $this->assertGreaterThan(0, $order, 'the hold has its order');
        $this->assertSame(3, self::countRows('SELECT count(*) AS n FROM ordrelinjer WHERE ordre_id = $1', [$order]), 'the item, the stand and the period');
        return ['hold' => $hold, 'order' => $order];
    }

    /** What updateBooking answered when the page rejected the hold, decoded. */
    private function reject(string $script, string $param, int $hold): mixed
    {
        $res = $this->runChildJson('run_public_booking_api.php', [
            $script,
            'updateBooking&' . $param . '=' . rawurlencode(self::$booking),
            json_encode(['id' => $hold, 'status' => 'rejected']),
        ]);
        return json_decode(trim($res['body']), true);
    }

    #[DataProvider('endpoints')]
    public function test_a_rejected_hold_leaves_no_order_and_no_order_lines(string $script, string $param): void
    {
        $made = $this->hold($script, $param, self::$fixture['stands'][0]);

        $this->assertSame(['status' => 'deleted'], $this->reject($script, $param, $made['hold']));

        $this->assertSame(0, self::countRows('SELECT count(*) AS n FROM rentalperiod WHERE id = $1', [$made['hold']]), 'the hold is gone');
        $this->assertSame(0, self::countRows('SELECT count(*) AS n FROM ordrer WHERE id = $1', [$made['order']]), 'its order is gone');
        $this->assertSame(0, self::countRows('SELECT count(*) AS n FROM ordrelinjer WHERE ordre_id = $1', [$made['order']]), 'and so are its lines');
    }

    #[DataProvider('endpoints')]
    public function test_rejecting_a_hold_leaves_another_holds_order_and_lines(string $script, string $param): void
    {
        $rejected = $this->hold($script, $param, self::$fixture['stands'][0]);
        $kept = $this->hold($script, $param, self::$fixture['stands'][1]);

        $this->reject($script, $param, $rejected['hold']);

        $this->assertSame(1, self::countRows('SELECT count(*) AS n FROM rentalperiod WHERE id = $1 AND order_id = $2', [$kept['hold'], $kept['order']]));
        $this->assertSame(1, self::countRows('SELECT count(*) AS n FROM ordrer WHERE id = $1', [$kept['order']]));
        $this->assertSame(3, self::countRows('SELECT count(*) AS n FROM ordrelinjer WHERE ordre_id = $1', [$kept['order']]));
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

        $this->assertSame(['status' => 'deleted'], $this->reject($script, $param, $hold));

        $this->assertSame(0, self::countRows('SELECT count(*) AS n FROM rentalperiod WHERE id = $1', [$hold]), 'the hold is gone');
        $this->assertSame($lines, self::countRows('SELECT count(*) AS n FROM ordrelinjer'), 'no order line is deleted');
    }
}
