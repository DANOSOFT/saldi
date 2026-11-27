<?php
// tests/characterization/rental/BookingOrderVatTest.test.php
//
// A booking's order keeps its VAT (testlab round 1, rental-public-booking,
// 2026-11-05).
//
// The order a booking makes, CreateOrder in remoteBooking/api.php, its copy in
// mysale/api.php and the staff one in rental/rental.php, has its VAT on the
// header (25% of the sum), but wrote its item line with momssats 0.
// opret_ordrelinje() gives a line no higher rate than the order's and reads
// that rate from the global $momssats, which debitor/ordre.php sets and
// CreateOrder did not. Approving the public booking then did not invoice its
// order: fakturer_ordre() refuses an order whose VAT is not its lines' VAT.
//
// In the lab's book no fiscal year covers today (they end with 2025), so the
// public scripts found none, and CreateOrder looked the rate up in fiscal
// year 0 and gave the order momssats 0 as well. debitor/ordre.php gives a line
// without a rate the order's rate when the order is opened, so it summed 0
// and stored 0.00 over the 14.80 the customer was charged. The scripts now
// take the newest fiscal year then, for every request: approving the booking
// is a request of its own, and its posting looked the item group up in an
// empty fiscal year, which Postgres refuses. The newest is the highest
// number, also in a legacy book whose grupper.kodenr is text ('10' above '9').
//
// In mysale/api.php, with its own old copy of opret_ordrelinje(),
// fakturer_ordre() then had no levering() or bogfor() to call; the script now
// uses includes/ordrefunc.php like remoteBooking/api.php does.
//
// Since approving invoices and posts the order, the approval acts only for
// the visitor's session that made the booking (testlab, 2026-11-16): any other
// is refused with 403 and nothing is invoiced, and so is Mit salg's once it
// is signed out. A hold with no order yet is answered 409. An order that is
// already invoiced is not invoiced again, and when fakturer_ordre() refuses
// the order the page gets an error (500) instead of "approved", and the hold
// keeps its expiry, which an approval clears, also of an order that is
// already invoiced. The staff page's clean-up of expired holds keeps a hold
// whose order is invoiced, and ends its expiry.
// An approved booking's invoiced order is not deleted by a later rejection.
//
// Driven through the three scripts themselves in a child process, with the
// bodies remoteBooking/index.js, mysale/remote.js and rental/booking.js send.
// The public scripts only take account database names that end in
// _<number>, which the test tenant's name does not, so they run against a
// fresh installation copied under such a name
// (support/PublicBookingDatabase.php), and the staff requests run in the
// test session moved to that copy.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261115 CL/ASR Integration: The regnskab is a copy of a fresh installation (PublicBookingDatabase), as master's test environment (2bd490c6) has no template to clone; the session is CharacterizationEnv::sessionId(), master's replacement for SESSION_ID.
// 20261116 CL/ASR The public booking is approved from the session that made it, which mysale/api.php has signed in as the customer.
//                 Approval from another session, a second approval and a failed invoicing are not answered as an approval and invoice nothing.
//                 A failed invoicing is answered 500 and keeps the hold's expiry, an approval clears it, a hold with no order is answered 409, and Mit salg refuses the approval once signed out.
//                 An approval of an order invoiced elsewhere clears the hold's expiry too, and rental/rental.php keeps an expired hold whose order is invoiced (status 3, or status 2 with an invoice number) and removes the other expired ones.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/support/PublicBookingDatabase.php';
require_once __DIR__ . '/../../../includes/stdFunc/rentalBookingCustomer.php';
require_once __DIR__ . '/../../../mysale/shopLookup.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class BookingOrderVatTest extends CharacterizationTestCase
{
    /** The regnskab the endpoints are asked about: a name the public ones accept. */
    private static string $booking = '';

    /** @var array{stand:int, customer:int, sku:string, rate:float} a stand of one product, the customer booking it, and the product's VAT rate */
    private static array $fixture = [];

    /** rental/rental.php's CreateOrder logs each order's request to order.txt in rental/: what was there before this suite, or null for no file. */
    private static ?string $orderLog = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $log = self::orderLogFile();
        self::$orderLog = is_file($log) ? (string) file_get_contents($log) : null;
        $booking = PublicBookingDatabase::name('_vat_1');
        PublicBookingDatabase::create(static::class, $booking);
        self::$booking = $booking; // from here on tearDownAfterClass() drops it, whatever fails below
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            // Registered like the test tenant, so the staff requests can open it.
            CharacterizationEnv::rows(
                $master,
                "INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version, sidst, brugerantal, posteringer, lukket, administrator)
                 SELECT 'chartest_vat', dbhost, dbuser, $1, version, sidst, brugerantal, 1000000, '', administrator
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
            $stand = (int) CharacterizationEnv::one($conn, 'INSERT INTO rentalitems (item_name, product_id) VALUES ($1, $2) RETURNING id', ['Stand 1', $item['id']])['id'];
            self::$fixture = ['stand' => $stand, 'customer' => (int) $fx->debtor()['id'], 'sku' => $item['varenr'], 'rate' => self::itemRate($conn, $item['gruppe'])];
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
            } finally {
                pg_close($master);
            }
            PublicBookingDatabase::drop(self::$booking); // with its regnskab row
            self::$booking = '';
        }
        $log = self::orderLogFile();
        if (self::$orderLog === null) {
            @unlink($log);
        } else {
            file_put_contents($log, self::$orderLog);
        }
        parent::tearDownAfterClass();
    }

    /** Every test starts with the stand free and the session in the clone. */
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

    private static function orderLogFile(): string
    {
        return dirname(__DIR__, 3) . '/rental/order.txt';
    }

    /** What admin/aaben_regnskab.php does when the user opens another regnskab: the same session, another db. */
    private static function moveSession($master, string $db): void
    {
        CharacterizationEnv::rows($master, 'UPDATE online SET db = $1 WHERE session_id = $2', [$db, CharacterizationEnv::sessionId()]);
    }

    /**
     * The VAT rate of an item group, the way opret_ordrelinje() finds it: the group's sales account (box4), that
     * account's VAT code (kontoplan.moms, e.g. S1) and the rate of that code (grupper art SM, box2).
     *
     * @param resource|\PgSql\Connection $conn
     */
    private static function itemRate($conn, int $gruppe): float
    {
        $group = CharacterizationEnv::one($conn, "SELECT box4 FROM grupper WHERE art = 'VG' AND kodenr = $1 AND fiscal_year = $2", [(string) $gruppe, self::$regnaar]);
        $account = CharacterizationEnv::one($conn, 'SELECT moms FROM kontoplan WHERE kontonr = $1 AND regnskabsaar = $2', [(int) $group['box4'], self::$regnaar]);
        $code = (int) substr(trim((string) $account['moms']), 1);
        $vat = CharacterizationEnv::one($conn, "SELECT box2 FROM grupper WHERE art = 'SM' AND kodenr = $1 AND fiscal_year = $2", [(string) $code, self::$regnaar]);
        return (float) ($vat['box2'] ?? 0);
    }

    /** Moves every fiscal year of the clone by $years years (grupper art RA: box2 is the first year, box4 the last). */
    private static function shiftFiscalYears(int $years): void
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            CharacterizationEnv::rows($conn, "UPDATE grupper SET box2 = (box2::int + $1)::text, box4 = (box4::int + $1)::text WHERE art = 'RA'", [$years]);
        } finally {
            pg_close($conn);
        }
    }

    /** @var array<int, array{box1:string, box2:string, box3:string, box4:string}> the clone's own fiscal years by id, while makeLegacyFiscalYears() has replaced them */
    private static array $ownFiscalYears = [];

    /**
     * Makes the clone a legacy book: grupper.kodenr is text, as in books from before 4.0, and the fiscal years are
     * numbered up to 10, year N running through 2000 + N, so none covers today. Years 9 and 10 are copies of the
     * fixture's year (its groups and chart of accounts); in year 9 the item's VAT code is 0%, in year 10 it is the
     * item's rate. As text, '9' is the highest code.
     */
    private static function makeLegacyFiscalYears(): void
    {
        if (self::$regnaar >= 9) {
            throw new RuntimeException('the fixture year ' . self::$regnaar . ' is in the way of the legacy years 9 and 10');
        }
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            self::$ownFiscalYears = [];
            foreach (CharacterizationEnv::rows($conn, "SELECT id, box1, box2, box3, box4 FROM grupper WHERE art = 'RA'") as $row) {
                self::$ownFiscalYears[(int) $row['id']] = ['box1' => (string) $row['box1'], 'box2' => (string) $row['box2'], 'box3' => (string) $row['box3'], 'box4' => (string) $row['box4']];
            }
            CharacterizationEnv::rows($conn, 'ALTER TABLE grupper ALTER COLUMN kodenr TYPE text USING kodenr::text');
            for ($year = self::$regnaar + 1; $year <= 10; $year++) {
                CharacterizationEnv::rows(
                    $conn,
                    "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box5, fiscal_year)
                     SELECT $1, kode, $2::text, art, box5, $3::int FROM grupper WHERE art = 'RA' AND kodenr = $4::text",
                    ['Legacy ' . $year, (string) $year, $year, (string) self::$regnaar]
                );
            }
            CharacterizationEnv::rows($conn, "UPDATE grupper SET box1 = '01', box2 = (2000 + kodenr::int)::text, box3 = '12', box4 = (2000 + kodenr::int)::text WHERE art = 'RA'");
            $boxes = 'box1, box2, box3, box4, box5, box6, box7, box8, box9, box10, box11, box12, box13, box14';
            $accountColumns = implode(', ', array_column(CharacterizationEnv::rows(
                $conn,
                "SELECT column_name FROM information_schema.columns
                 WHERE table_schema = current_schema() AND table_name = 'kontoplan' AND column_name NOT IN ('id', 'regnskabsaar')
                 ORDER BY ordinal_position"
            ), 'column_name'));
            foreach ([9, 10] as $year) {
                CharacterizationEnv::rows(
                    $conn,
                    "INSERT INTO grupper (beskrivelse, kode, kodenr, art, $boxes, fiscal_year)
                     SELECT beskrivelse, kode, kodenr, art, $boxes, $1::int FROM grupper WHERE fiscal_year = $2 AND art <> 'RA'",
                    [$year, self::$regnaar]
                );
                CharacterizationEnv::rows(
                    $conn,
                    "INSERT INTO kontoplan ($accountColumns, regnskabsaar) SELECT $accountColumns, $1::int FROM kontoplan WHERE regnskabsaar = $2",
                    [$year, self::$regnaar]
                );
            }
            CharacterizationEnv::rows($conn, "UPDATE grupper SET box2 = '0' WHERE art = 'SM' AND fiscal_year = 9");
        } finally {
            pg_close($conn);
        }
    }

    /** Undoes makeLegacyFiscalYears(), also when it stopped halfway. */
    private static function restoreFiscalYears(): void
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            CharacterizationEnv::rows($conn, 'DELETE FROM kontoplan WHERE regnskabsaar > $1', [self::$regnaar]);
            CharacterizationEnv::rows($conn, 'DELETE FROM grupper WHERE fiscal_year > $1', [self::$regnaar]); // the years added and their copies
            foreach (self::$ownFiscalYears as $id => $year) {
                CharacterizationEnv::rows($conn, 'UPDATE grupper SET box1 = $1, box2 = $2, box3 = $3, box4 = $4 WHERE id = $5', [$year['box1'], $year['box2'], $year['box3'], $year['box4'], $id]);
            }
            self::$ownFiscalYears = [];
            $type = CharacterizationEnv::one($conn, "SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'grupper' AND column_name = 'kodenr'");
            if (($type['data_type'] ?? '') !== 'integer') {
                CharacterizationEnv::rows($conn, 'ALTER TABLE grupper ALTER COLUMN kodenr TYPE integer USING kodenr::integer');
            }
        } finally {
            pg_close($conn);
        }
    }

    /** A day as rental/booking.js stores it: new Date('YYYY-MM-DD') / 1000, midnight UTC. */
    private static function staffDay(string $date): int
    {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->getTimestamp();
    }

    /** @return array<string, array{string}> */
    public static function bookingScripts(): array
    {
        return [
            'remoteBooking/api.php' => ['remoteBooking/api.php'],
            'mysale/api.php'        => ['mysale/api.php'],
            'rental/rental.php'     => ['rental/rental.php'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function publicScripts(): array
    {
        return [
            'remoteBooking/api.php' => ['remoteBooking/api.php'],
            'mysale/api.php'        => ['mysale/api.php'],
        ];
    }

    /** The account database parameter a public script reads. */
    private static function param(string $script): string
    {
        return $script === 'remoteBooking/api.php' ? 'id' : 'db';
    }

    /**
     * A visitor's browser before it books on a public page: a session cookie, and the session the page left. For
     * mysale/api.php that is Mit salg signed in as the fixture's customer at this regnskab (mysale.php, mySaleSignIn()).
     *
     * @return array{sid:string, session:array<string, mixed>}
     */
    private static function visitor(string $script): array
    {
        $sid = 'chartest' . bin2hex(random_bytes(12));
        $session = [];
        if ($script === 'mysale/api.php') {
            mySaleSignIn($session, $sid, (string) self::$fixture['customer'], self::$booking);
        }
        return ['sid' => $sid, 'session' => $session];
    }

    /**
     * Books the stand for a week from 01-03-2027 the way the script's page does, and answers the booking and its order.
     * The public pages make the order with the hold (createBooking); the staff page books, then asks for the order (createOrder).
     * For a public page also the visitor's session after createBooking, which the page approves the booking from.
     *
     * @return array{booking:int, order:int, visitor?:array{sid:string, session:array<string, mixed>}}
     */
    private function book(string $script): array
    {
        $visitor = null;
        if ($script === 'rental/rental.php') {
            $conn = CharacterizationEnv::connect(self::$booking);
            try {
                $booking = (int) CharacterizationEnv::one(
                    $conn,
                    'INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id) VALUES ($1, $2, $3, $4, 0) RETURNING id',
                    [self::$fixture['stand'], self::$fixture['customer'], self::staffDay('2027-03-01'), self::staffDay('2027-03-08')]
                )['id'];
            } finally {
                pg_close($conn);
            }
            $body = json_encode([
                'customer_id' => (string) self::$fixture['customer'],
                'item_id' => (string) self::$fixture['stand'],
                'from' => self::staffDay('2027-03-01'),
                'to' => self::staffDay('2027-03-08'),
                'fromDate' => '2027-03-01',
                'toDate' => '2027-03-08',
                'days' => 7,
                'booking_id' => $booking,
            ]);
            $this->runChildJson('run_rental_api.php', ['createOrder', CharacterizationEnv::sessionId(), 'localhost:5000', 'http', $body]);
        } else {
            $customer = self::$fixture['customer'];
            $visitor = self::visitor($script);
            if ($script === 'remoteBooking/api.php') { // its page names the customer by the handle createCust gave the session
                $customer = rental_booking_customer_issue($visitor['session'], self::$booking, $customer);
            }
            $body = json_encode([
                'product_id' => 1,
                'start_date' => '2027-03-01',
                'end_date' => '2027-03-08',
                'weeks' => 1,
                'price' => 100,
                'item_id' => self::$fixture['stand'],
                'cust_id' => $customer,
                'sku' => self::$fixture['sku'],
                'unit' => 'stk',
            ]);
            $res = $this->runChildJson('run_public_booking_api.php', [$script, 'createBooking&' . self::param($script) . '=' . rawurlencode(self::$booking), $body, $visitor['sid'], json_encode($visitor['session'])]);
            $booking = (int) (json_decode(trim($res['body']), true)['id'] ?? 0);
            $this->assertGreaterThan(0, $booking, "$script made no hold; it said: " . $res['body']);
            $this->assertIsArray($res['session'], 'the visitor keeps the session');
            $visitor['session'] = $res['session'];
        }
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $order = (int) CharacterizationEnv::one($conn, 'SELECT order_id FROM rentalperiod WHERE id = $1', [$booking])['order_id'];
        } finally {
            pg_close($conn);
        }
        $this->assertGreaterThan(0, $order, "$script made the booking's order");
        return ['booking' => $booking, 'order' => $order] + ($visitor === null ? [] : ['visitor' => $visitor]);
    }

    /**
     * The order's header and its lines.
     *
     * @return array{header:array{sum:float, moms:float, momssats:float, status:int, fakturanr:?string}, items:list<array{momssats:float}>, linesVat:float}
     */
    private static function order(int $order): array
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $header = CharacterizationEnv::one($conn, 'SELECT sum, moms, momssats, status, fakturanr FROM ordrer WHERE id = $1', [$order]);
            $lines = CharacterizationEnv::rows($conn, 'SELECT vare_id, antal, pris, rabat, momssats FROM ordrelinjer WHERE ordre_id = $1 ORDER BY posnr, id', [$order]);
        } finally {
            pg_close($conn);
        }
        $items = [];
        $linesVat = 0.0;
        foreach ($lines as $line) {
            if ((int) $line['vare_id'] <= 0) {
                continue; // the stand and the period are text lines
            }
            $items[] = ['momssats' => (float) $line['momssats']];
            // As debitor/ordre.php adds it up when the order is opened, and fakturer_ordre() before it invoices.
            $net = (float) $line['antal'] * ((float) $line['pris'] - (float) $line['pris'] * (float) $line['rabat'] / 100);
            $linesVat += $net * (float) $line['momssats'] / 100;
        }
        return [
            'header' => ['sum' => (float) $header['sum'], 'moms' => (float) $header['moms'], 'momssats' => (float) $header['momssats'], 'status' => (int) $header['status'], 'fakturanr' => $header['fakturanr']],
            'items' => $items,
            'linesVat' => $linesVat,
        ];
    }

    #[DataProvider('bookingScripts')]
    public function test_the_booking_order_has_its_vat_on_the_item_line_too(string $script): void
    {
        $this->assertGreaterThan(0.0, self::$fixture['rate'], "the fixture's item group carries VAT; with none this test proves nothing");

        $order = self::order($this->book($script)['order']);

        $this->assertCount(1, $order['items'], 'the booked product is the one item line');
        $this->assertEqualsWithDelta(self::$fixture['rate'], $order['items'][0]['momssats'], 0.001, "the item line has the product's VAT rate, not 0");
        $this->assertEqualsWithDelta($order['header']['sum'] * self::$fixture['rate'] / 100, $order['header']['moms'], 0.01, "the order's VAT is the product's rate of its sum");
        $this->assertEqualsWithDelta($order['header']['moms'], $order['linesVat'], 0.01, "the order's VAT is its lines' VAT, so opening it in debitor/ordre.php keeps it");
    }

    #[DataProvider('publicScripts')]
    public function test_the_booking_order_has_its_vat_when_no_fiscal_year_covers_today(string $script): void
    {
        // As in the lab's book in November 2026, whose fiscal years end with 2025: the script finds no fiscal year for today.
        self::shiftFiscalYears(-10);
        try {
            $order = self::order($this->book($script)['order']);
        } finally {
            self::shiftFiscalYears(10);
        }

        $this->assertEqualsWithDelta(self::$fixture['rate'], $order['header']['momssats'], 0.001, "the order has the product's VAT rate, which debitor/ordre.php gives a line without one when the order is opened");
        $this->assertCount(1, $order['items'], 'the booked product is the one item line');
        $this->assertEqualsWithDelta(self::$fixture['rate'], $order['items'][0]['momssats'], 0.001, "the item line has the product's VAT rate, not 0");
        $this->assertEqualsWithDelta($order['header']['moms'], $order['linesVat'], 0.01, "the order's VAT is its lines' VAT");
    }

    #[DataProvider('publicScripts')]
    public function test_the_booking_order_has_its_vat_when_fiscal_year_codes_are_text(string $script): void
    {
        // A legacy book whose fiscal years run 1 to 10 in a text kodenr, none covering today: the newest is 10, not '9'.
        try {
            self::makeLegacyFiscalYears();
            $order = self::order($this->book($script)['order']);
        } finally {
            self::restoreFiscalYears();
        }

        $this->assertEqualsWithDelta(self::$fixture['rate'], $order['header']['momssats'], 0.001, "the order has the product's VAT rate in fiscal year 10, not year 9's 0%");
        $this->assertCount(1, $order['items'], 'the booked product is the one item line');
        $this->assertEqualsWithDelta(self::$fixture['rate'], $order['items'][0]['momssats'], 0.001, "the item line has the product's VAT rate in fiscal year 10");
        $this->assertEqualsWithDelta($order['header']['moms'], $order['linesVat'], 0.01, "the order's VAT is its lines' VAT");
    }

    /**
     * What updateBooking answered the booking page sending this status for the booking, in a request of its own, from
     * the visitor's session (no session cookie when null).
     *
     * @param array{booking:int, order:int} $made what book() made
     * @param array{sid:string, session:array<string, mixed>}|null $visitor
     * @return array{status:int|false, answer:mixed}
     */
    private function update(string $script, array $made, string $status, ?array $visitor): array
    {
        $args = [
            $script,
            'updateBooking&' . self::param($script) . '=' . rawurlencode(self::$booking),
            json_encode(['id' => $made['booking'], 'status' => $status]),
        ];
        if ($visitor !== null) {
            array_push($args, $visitor['sid'], json_encode($visitor['session']));
        }
        $res = $this->runChildJson('run_public_booking_api.php', $args);
        return ['status' => $res['status'], 'answer' => json_decode(trim($res['body']), true)];
    }

    /**
     * Approves the booking as the booking page does once the payment succeeded, in a request of its own from the session
     * that made it, and answers the order's header after it.
     *
     * @param array{booking:int, order:int, visitor:array{sid:string, session:array<string, mixed>}} $made what book() made
     * @return array{sum:float, moms:float, momssats:float, status:int, fakturanr:?string}
     */
    private function approve(string $script, array $made): array
    {
        $res = $this->update($script, $made, 'approved', $made['visitor']);
        $this->assertSame(['status' => 'approved'], $res['answer'], 'updateBooking answered with HTTP ' . var_export($res['status'], true));
        return self::order($made['order'])['header'];
    }

    /**
     * What an approval can have written: the order, its lines, the hold, and the count of postings and open items.
     *
     * @param array{booking:int, order:int} $made what book() made
     * @return array<string, mixed>
     */
    private static function written(array $made): array
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            return [
                'order' => CharacterizationEnv::one($conn, 'SELECT * FROM ordrer WHERE id = $1', [$made['order']]),
                'lines' => CharacterizationEnv::rows($conn, 'SELECT * FROM ordrelinjer WHERE ordre_id = $1 ORDER BY id', [$made['order']]),
                'hold' => CharacterizationEnv::one($conn, 'SELECT * FROM rentalperiod WHERE id = $1', [$made['booking']]),
                'postings' => CharacterizationEnv::one($conn, 'SELECT count(*) AS n FROM transaktioner')['n'],
                'open items' => CharacterizationEnv::one($conn, 'SELECT count(*) AS n FROM openpost')['n'],
            ];
        } finally {
            pg_close($conn);
        }
    }

    /** @param array<string, mixed> $written what written() read */
    private function assertNotInvoiced(array $written, string $why): void
    {
        $this->assertLessThan(3, (int) $written['order']['status'], "the order is not invoiced: $why");
        $this->assertSame('', trim((string) $written['order']['fakturanr']), "the order has no invoice number: $why");
        $this->assertNotNull($written['hold']['expiry_time'], "the hold keeps its expiry: $why");
    }

    /** @return array<string, array{string, string}> */
    public static function sessionsThatDidNotBook(): array
    {
        $cases = [];
        foreach (self::publicScripts() as $endpoint => [$script]) {
            $cases["$endpoint: no session cookie"] = [$script, 'no session'];
            $cases["$endpoint: a session that made no booking"] = [$script, 'fresh session'];
        }
        return $cases;
    }

    #[DataProvider('sessionsThatDidNotBook')]
    public function test_the_public_booking_is_not_approved_from_a_session_that_did_not_make_it(string $script, string $claim): void
    {
        $made = $this->book($script);
        $before = self::written($made);

        // A fresh session of Mit salg is signed in as the booking's customer: still not the session that booked.
        $res = $this->update($script, $made, 'approved', $claim === 'no session' ? null : self::visitor($script));

        $this->assertSame(403, $res['status'], 'updateBooking answered: ' . json_encode($res['answer']));
        $this->assertIsArray($res['answer']);
        $this->assertArrayHasKey('error', $res['answer'], 'an error the page can show');
        $this->assertSame($before, self::written($made), 'nothing is written');
        $this->assertNotInvoiced(self::written($made), 'another session asked');
    }

    public function test_mit_salgs_booking_is_not_approved_once_its_session_is_signed_out(): void
    {
        $made = $this->book('mysale/api.php');
        $before = self::written($made);
        $visitor = $made['visitor'];
        unset($visitor['session']['mySalePw'], $visitor['session']['mySaleAcId'], $visitor['session']['mySaleDb']); // what mySaleCustomerLogout() leaves

        $res = $this->update('mysale/api.php', $made, 'approved', $visitor);

        $this->assertSame(403, $res['status'], 'updateBooking answered: ' . json_encode($res['answer']));
        $this->assertIsArray($res['answer']);
        $this->assertArrayHasKey('error', $res['answer'], 'an error the page can show');
        $this->assertSame($before, self::written($made), 'nothing is written');
        $this->assertNotInvoiced(self::written($made), 'the session that booked is signed out');
    }

    /** @return array<string, array{string, string}> */
    public static function invoicedBookings(): array
    {
        $cases = [];
        foreach (self::publicScripts() as $endpoint => [$script]) {
            $cases["$endpoint: invoiced by an earlier approval"] = [$script, 'approved'];
            $cases["$endpoint: invoiced elsewhere while the hold waits"] = [$script, 'elsewhere'];
        }
        return $cases;
    }

    /**
     * The approval invoices nothing more, but the hold no longer expires: rental/rental.php removes a hold that still
     * expires, and the invoiced booking's stand could then be booked twice.
     */
    #[DataProvider('invoicedBookings')]
    public function test_approving_a_booking_whose_order_is_invoiced_writes_nothing_more_and_ends_its_expiry(string $script, string $invoiced): void
    {
        $made = $this->book($script);
        if ($invoiced === 'approved') {
            $this->approve($script, $made);
        } else { // as staff invoicing the order in debitor/ordre.php leaves its header
            $conn = CharacterizationEnv::connect(self::$booking);
            try {
                CharacterizationEnv::rows($conn, "UPDATE ordrer SET status = 3, fakturanr = '90001', fakturadate = ordredate WHERE id = \$1", [$made['order']]);
            } finally {
                pg_close($conn);
            }
        }
        $before = self::written($made);
        $this->assertNotNull($before['order']['fakturanr'], 'the order is invoiced');
        if ($invoiced === 'elsewhere') {
            $this->assertNotNull($before['hold']['expiry_time'], 'the hold still expires when staff invoice its order');
        }
        // another visitor's hold on the stand, a month later: the approval ends only its own booking's expiry
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $other = (int) CharacterizationEnv::one(
                $conn,
                "INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id, expiry_time) VALUES ($1, $2, $3, $4, 0, now() + interval '4 minutes') RETURNING id",
                [self::$fixture['stand'], self::$fixture['customer'], strtotime('2027-08-02'), strtotime('2027-08-09')]
            )['id'];
            $otherBefore = CharacterizationEnv::one($conn, 'SELECT * FROM rentalperiod WHERE id = $1', [$other]);
        } finally {
            pg_close($conn);
        }

        $res = $this->update($script, $made, 'approved', $made['visitor']);

        $this->assertSame(['status' => 'approved'], $res['answer'], 'the booking is approved: HTTP ' . var_export($res['status'], true));
        $after = self::written($made);
        $this->assertNull($after['hold']['expiry_time'], 'the invoiced booking no longer expires');
        $before['hold']['expiry_time'] = null;
        $this->assertSame($before, $after, 'no second invoice, posting or delivery, and nothing else is written');
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $this->assertSame($otherBefore, CharacterizationEnv::one($conn, 'SELECT * FROM rentalperiod WHERE id = $1', [$other]), 'another hold keeps its expiry');
            CharacterizationEnv::rows($conn, 'DELETE FROM rentalperiod WHERE id = $1', [$other]);
        } finally {
            pg_close($conn);
        }
    }

    /**
     * rental/rental.php removes the holds that have expired on every request of the staff page (cleanUpExpiredBookings()).
     * A hold whose order was invoiced while it waited, by staff or by an approval that stopped before it ended the
     * expiry, is a booking: it stays and no longer expires. The other expired holds go as before, and their orders stay
     * as they are. A hold that has not expired stays as it is.
     */
    public function test_the_staff_page_keeps_an_expired_hold_whose_order_is_invoiced(): void
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        $orders = [];
        try {
            $order = static function (int $status, ?string $fakturanr) use ($conn, &$orders): int {
                $id = (int) CharacterizationEnv::one(
                    $conn,
                    "INSERT INTO ordrer (konto_id, art, status, fakturanr, ordredate) VALUES ($1, 'DO', $2, $3, CURRENT_DATE) RETURNING id",
                    [self::$fixture['customer'], $status, $fakturanr]
                )['id'];
                $orders[] = $id;
                return $id;
            };
            // Expiries two days off, so neither PHP's nor the database's time zone moves a hold across now.
            $hold = static fn (int $orderId, string $expiry, string $from): int => (int) CharacterizationEnv::one(
                $conn,
                'INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id, expiry_time) VALUES ($1, $2, $3, $4, $5, now() + $6::interval) RETURNING id',
                [self::$fixture['stand'], self::$fixture['customer'], strtotime($from), strtotime($from . ' +7 days'), $orderId, $expiry]
            )['id'];
            $holds = [
                'invoiced'                         => $hold($order(3, '90101'), '-2 days', '2027-05-03'),
                'with an invoice number, status 2' => $hold($order(2, '90102'), '-2 days', '2027-05-10'),
                'not invoiced'                     => $hold($order(1, null), '-2 days', '2027-05-17'),
                'with an empty invoice number'     => $hold($order(2, ''), '-2 days', '2027-05-24'),
                'without an order'                 => $hold(0, '-2 days', '2027-05-31'),
                'not expired'                      => $hold($order(1, null), '2 days', '2027-06-07'),
                'status 3 without an invoice number' => $hold($order(3, null), '-2 days', '2027-06-14'),
                'invoiced, not expired'            => $hold($order(3, '90103'), '2 days', '2027-06-21'),
            ];
            $readHolds = static function () use ($conn): array {
                $rows = [];
                foreach (CharacterizationEnv::rows($conn, 'SELECT * FROM rentalperiod ORDER BY id') as $row) {
                    $rows[(int) $row['id']] = $row;
                }
                return $rows;
            };
            $readOrders = static fn (): array => CharacterizationEnv::rows($conn, 'SELECT * FROM ordrer WHERE id = ANY($1::int[]) ORDER BY id', ['{' . implode(',', $orders) . '}']);
            $before = $readHolds();
            $ordersBefore = $readOrders();

            $res = $this->runChildJson('run_rental_api.php', ['getRemoteLink', CharacterizationEnv::sessionId(), 'localhost:5000', 'http']);

            $this->assertIsString(json_decode((string) $res['body'], true), 'the staff page answered its request, after its clean-up; it said: ' . $res['body']);
            $after = $readHolds();
            foreach (['invoiced', 'with an invoice number, status 2', 'status 3 without an invoice number'] as $name) {
                $this->assertArrayHasKey($holds[$name], $after, "the expired hold $name is kept");
                $this->assertNull($after[$holds[$name]]['expiry_time'], "the hold $name no longer expires");
                $expected = $before[$holds[$name]];
                $expected['expiry_time'] = null;
                $this->assertSame($expected, $after[$holds[$name]], "only the expiry of the hold $name changes");
            }
            foreach (['not invoiced', 'with an empty invoice number', 'without an order'] as $name) {
                $this->assertArrayNotHasKey($holds[$name], $after, "the expired hold $name is removed");
            }
            foreach (['not expired', 'invoiced, not expired'] as $name) {
                $this->assertSame($before[$holds[$name]], $after[$holds[$name]] ?? null, "the hold $name has not expired and stays as it is");
            }
            $this->assertSame($ordersBefore, $readOrders(), 'the orders stay as they are');
        } finally {
            if ($orders !== []) { // the invoice numbers made here are not left to the later approvals
                CharacterizationEnv::rows($conn, 'DELETE FROM ordrer WHERE id = ANY($1::int[])', ['{' . implode(',', $orders) . '}']);
            }
            pg_close($conn);
        }
    }

    #[DataProvider('publicScripts')]
    public function test_a_failed_invoicing_is_not_answered_as_an_approval(string $script): void
    {
        $made = $this->book($script);
        // fakturer_ordre() refuses an order whose VAT is not its lines' VAT: it answers what is wrong instead of the order id.
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            CharacterizationEnv::rows($conn, 'UPDATE ordrer SET moms = moms + 10 WHERE id = $1', [$made['order']]);
        } finally {
            pg_close($conn);
        }
        $before = self::written($made);

        $res = $this->update($script, $made, 'approved', $made['visitor']);

        $this->assertNotSame(['status' => 'approved'], $res['answer'], 'a refused invoice is answered as an approval');
        $this->assertIsArray($res['answer']);
        $this->assertArrayHasKey('error', $res['answer'], 'an error the page can show');
        $this->assertSame(500, $res['status'], 'updateBooking answered: ' . json_encode($res['answer']));
        $this->assertSame($before, self::written($made), 'nothing is written');
        $this->assertNotInvoiced(self::written($made), 'the invoicing was refused');
        $this->assertSame($before['hold']['expiry_time'], self::written($made)['hold']['expiry_time'], 'the hold keeps the expiry it had');
    }

    #[DataProvider('publicScripts')]
    public function test_approving_a_hold_that_has_no_order_is_answered_409(string $script): void
    {
        // createBooking writes the hold with order_id 0 and sets it once the order is made: until then there is nothing to invoice.
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $booking = (int) CharacterizationEnv::one(
                $conn,
                "INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id, expiry_time) VALUES ($1, $2, $3, $4, 0, now() + interval '6 minutes') RETURNING id",
                [self::$fixture['stand'], self::$fixture['customer'], strtotime('2027-03-01'), strtotime('2027-03-08')]
            )['id'];
        } finally {
            pg_close($conn);
        }
        $visitor = self::visitor($script);
        $notes = $script === 'mysale/api.php' ? rental_booking_mysale_notes(self::$booking) : self::$booking;
        rental_booking_note($visitor['session'], $notes, $booking); // as createBooking left the session that made the hold
        $made = ['booking' => $booking, 'order' => 0];
        $before = self::written($made);

        $res = $this->update($script, $made, 'approved', $visitor);

        $this->assertSame(409, $res['status'], 'updateBooking answered: ' . json_encode($res['answer']));
        $this->assertIsArray($res['answer']);
        $this->assertArrayHasKey('error', $res['answer'], 'an error the page can show');
        $this->assertSame($before, self::written($made), 'nothing is invoiced, and the hold keeps its expiry');
    }

    #[DataProvider('publicScripts')]
    public function test_an_approved_booking_is_not_deleted_by_a_rejection(string $script): void
    {
        $made = $this->book($script);
        $this->approve($script, $made);
        $before = self::written($made);

        $res = $this->update($script, $made, 'rejected', $made['visitor']);

        $this->assertSame(409, $res['status'], 'updateBooking answered: ' . json_encode($res['answer']));
        $this->assertIsArray($res['answer']);
        $this->assertArrayHasKey('error', $res['answer'], 'an error the page can show');
        $this->assertSame($before, self::written($made), 'the invoiced order, its lines and the hold stay as they were');
    }

    #[DataProvider('publicScripts')]
    public function test_approving_the_public_booking_invoices_its_order(string $script): void
    {
        $made = $this->book($script);
        $this->assertNotNull(self::written($made)['hold']['expiry_time'], 'the hold expires while the booking waits for its payment');

        $header = $this->approve($script, $made);

        $this->assertNotNull($header['fakturanr'], 'fakturer_ordre() invoiced the order instead of refusing its VAT');
        $this->assertGreaterThanOrEqual(3, $header['status'], 'the order is booked as an invoice');
        $this->assertNull(self::written($made)['hold']['expiry_time'], 'the approved booking no longer expires');
    }

    #[DataProvider('publicScripts')]
    public function test_approving_the_public_booking_invoices_its_order_when_no_fiscal_year_covers_today(string $script): void
    {
        // The approval finds its own fiscal year: with none for today its posting looked the item group up in fiscal year '', which Postgres refuses.
        self::shiftFiscalYears(-10);
        try {
            $header = $this->approve($script, $this->book($script));
        } finally {
            self::shiftFiscalYears(10);
        }

        $this->assertNotNull($header['fakturanr'], 'fakturer_ordre() invoiced the order in the newest fiscal year');
        $this->assertGreaterThanOrEqual(3, $header['status'], 'the order is booked as an invoice');
        $this->assertEqualsWithDelta($header['sum'] * self::$fixture['rate'] / 100, $header['moms'], 0.01, 'the invoice keeps the VAT the customer paid');
    }
}
