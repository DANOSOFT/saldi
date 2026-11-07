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
// Driven through the three scripts themselves in a child process, with the
// bodies remoteBooking/index.js, mysale/remote.js and rental/booking.js send.
// The public scripts only take account database names that end in
// _<number>, which the test tenant's name does not, so they run against a
// clone of the template named that way, and the staff requests run in the
// test session moved to that clone.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../../../includes/stdFunc/rentalBookingCustomer.php';

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
        $booking = CharacterizationEnv::testDb() . '_vat_1';
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
                CharacterizationEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()', [self::$booking]);
                CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, self::$booking));
                CharacterizationEnv::rows($master, "DELETE FROM regnskab WHERE db = $1 AND regnskab = 'chartest_vat'", [self::$booking]);
            } finally {
                pg_close($master);
            }
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
        CharacterizationEnv::rows($master, 'UPDATE online SET db = $1 WHERE session_id = $2', [$db, CharacterizationEnv::SESSION_ID]);
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
     * Books the stand for a week from 01-03-2027 the way the script's page does, and answers the booking and its order.
     * The public pages make the order with the hold (createBooking); the staff page books, then asks for the order (createOrder).
     *
     * @return array{booking:int, order:int}
     */
    private function book(string $script): array
    {
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
            $this->runChildJson('run_rental_api.php', ['createOrder', CharacterizationEnv::SESSION_ID, 'localhost:5000', 'http', $body]);
        } else {
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
                'item_id' => self::$fixture['stand'],
                'cust_id' => $customer,
                'sku' => self::$fixture['sku'],
                'unit' => 'stk',
            ]);
            $res = $this->runChildJson('run_public_booking_api.php', [$script, 'createBooking&' . self::param($script) . '=' . rawurlencode(self::$booking), $body, 'chartest' . bin2hex(random_bytes(12)), json_encode($session)]);
            $booking = (int) (json_decode(trim($res['body']), true)['id'] ?? 0);
            $this->assertGreaterThan(0, $booking, "$script made no hold; it said: " . $res['body']);
        }
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            $order = (int) CharacterizationEnv::one($conn, 'SELECT order_id FROM rentalperiod WHERE id = $1', [$booking])['order_id'];
        } finally {
            pg_close($conn);
        }
        $this->assertGreaterThan(0, $order, "$script made the booking's order");
        return ['booking' => $booking, 'order' => $order];
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
     * Approves the booking as the booking page does once the payment succeeded, in a request of its own, and answers the order's header after it.
     *
     * @param array{booking:int, order:int} $made what book() made
     * @return array{sum:float, moms:float, momssats:float, status:int, fakturanr:?string}
     */
    private function approve(string $script, array $made): array
    {
        $res = $this->runChildJson('run_public_booking_api.php', [
            $script,
            'updateBooking&' . self::param($script) . '=' . rawurlencode(self::$booking),
            json_encode(['id' => $made['booking'], 'status' => 'approved']),
        ]);
        $this->assertSame(['status' => 'approved'], json_decode(trim($res['body']), true));
        return self::order($made['order'])['header'];
    }

    #[DataProvider('publicScripts')]
    public function test_approving_the_public_booking_invoices_its_order(string $script): void
    {
        $header = $this->approve($script, $this->book($script));

        $this->assertNotNull($header['fakturanr'], 'fakturer_ordre() invoiced the order instead of refusing its VAT');
        $this->assertGreaterThanOrEqual(3, $header['status'], 'the order is booked as an invoice');
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
