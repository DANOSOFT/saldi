<?php
// tests/characterization/rental/RemoteBookingCustomerTest.test.php
//
// The login-free booking tells nobody who the customer with an e-mail is
// (testlab round 1, rental-public-booking, 2026-11-05).
//
// The booking page (remoteBooking/index.js) posts the form's name, address
// and e-mail to remoteBooking/api.php?createCust and books with what it
// answers (createBooking's cust_id). An e-mail that was already a customer's
// was answered with that customer's adresser id, and a new one with the new
// customer's, so anyone could ask which customer an e-mail belongs to, and
// createBooking booked for any customer id it was sent.
//
// createCust now answers a random handle and keeps the customer it stands
// for in the visitor's session, for that regnskab
// (rental_booking_customer_issue()). createBooking books for the customer of
// the handle and refuses anything else: a customer id, a handle from another
// session, one given at another regnskab, or no session at all. A returning
// customer still books on the customer the e-mail is. A later createCust in
// the session leaves the earlier handles good, so two tabs that both send the
// form before either books each book for their own customer.
//
// That needs both tabs in one session. In a browser without a session cookie,
// two createCust requests sent before either answered each made a session of
// their own, the browser kept one of the two cookies, and the other tab's
// handle booked nothing (400). The booking page (remoteBooking/index.php) now
// makes the session when it opens (rental_booking_customer_session()), so the
// cookie is there before the form is sent. The tests of that keep the
// visitor's sessions between requests, as the server does, start with no
// cookie, and keep the cookie the answers leave, as the browser does.
//
// The Mit salg copy, mysale/api.php?createBooking, is called by the portal's
// booking page (mysale/remoteBooking.php, remote.js). It books only for the
// customer Mit salg signed in at that regnskab in the visitor's session
// (mysale.php, mySaleSignIn(), mySaleSignedInAs()), and answers anything else
// with 403 before it holds the stand.
//
// Driven through the script itself in a child process, with the bodies the
// booking page sends and the session cookie the browser keeps between the two
// requests. The script only takes account database names that end in
// _<number>, which the test tenant's name does not, so it runs against a
// fresh installation copied under such a name (support/PublicBookingDatabase.php).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
//                The booking page makes the session a browser without a cookie books in, also from two tabs that send the form at once.
// 20261115 CL/ASR Integration: The regnskab is a copy of a fresh installation (PublicBookingDatabase), as master's test environment (2bd490c6) has no template to clone.
// 20261116 CL/ASR mysale/api.php?createBooking books only for the customer Mit salg signed in at that regnskab in the session.
// 20261124 CL/ASR Comments state the current rule; no code change.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/support/PublicBookingDatabase.php';
require_once __DIR__ . '/../../../includes/stdFunc/rentalBookingCustomer.php';
require_once __DIR__ . '/../../../mysale/shopLookup.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class RemoteBookingCustomerTest extends CharacterizationTestCase
{
    /** The regnskab the public endpoint is asked about: a name it accepts. */
    private static string $booking = '';

    /** @var array{stand:int, sku:string, own:array{id:int, email:string}, other:array{id:int, email:string}} a stand, the visitor's earlier customer and another one */
    private static array $fixture = [];

    /** Where the visitor's sessions live between requests, as on the server (run_public_booking_api.php's "store:"). */
    private static string $sessions = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $booking = PublicBookingDatabase::name('_cu_1');
        PublicBookingDatabase::create(static::class, $booking);
        self::$booking = $booking; // from here on tearDownAfterClass() drops it, whatever fails below
        self::$sessions = sys_get_temp_dir() . '/chartest-booking-sessions-' . bin2hex(random_bytes(6));
        if (!mkdir(self::$sessions, 0700)) {
            throw new RuntimeException('could not make ' . self::$sessions);
        }

        $conn = CharacterizationEnv::connect($booking);
        try {
            $fx = new Fixtures($conn, self::$regnaar);
            $item = $fx->item();
            $stand = (int) CharacterizationEnv::one($conn, 'INSERT INTO rentalitems (item_name, product_id) VALUES ($1, $2) RETURNING id', ['Stand 1', $item['id']])['id'];
            $customers = [];
            foreach (['own' => 'bodil', 'other' => 'arne'] as $who => $mailbox) {
                $email = $mailbox . '.chartest@example.invalid';
                $customers[$who] = ['id' => (int) $fx->debtor(['email' => $email])['id'], 'email' => $email];
            }
            self::$fixture = ['stand' => $stand, 'sku' => $item['varenr']] + $customers;
        } finally {
            pg_close($conn);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$sessions !== '') {
            array_map('unlink', glob(self::$sessions . '/*') ?: []);
            rmdir(self::$sessions);
            self::$sessions = '';
        }
        if (self::$booking !== '') {
            PublicBookingDatabase::drop(self::$booking);
            self::$booking = '';
        }
        parent::tearDownAfterClass();
    }

    /** Every test starts with the stand free. */
    protected function setUp(): void
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            CharacterizationEnv::rows($conn, 'DELETE FROM rentalperiod');
        } finally {
            pg_close($conn);
        }
    }

    /**
     * @param array<int, mixed> $params
     * @return list<array<string, mixed>>
     */
    private static function query(string $sql, array $params = []): array
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            return CharacterizationEnv::rows($conn, $sql, $params);
        } finally {
            pg_close($conn);
        }
    }

    /**
     * What createCust answered the booking page's form with this e-mail, from a visitor whose session holds $session
     * (a new session when empty), and what the session held after it.
     *
     * @param array<string, mixed> $session
     * @return array{status:int|false, body:string, session:array<string, mixed>|null}
     */
    private function createCust(string $email, array $session = []): array
    {
        $form = json_encode(['name' => 'Booker Bodil', 'email' => $email, 'tlf' => '12345678', 'addr' => 'Testvej 1', 'zip' => '8000', 'city' => 'Aarhus']);
        return $this->runChildJson('run_public_booking_api.php', ['remoteBooking/api.php', 'createCust&id=' . rawurlencode(self::$booking), $form, self::sessionId(), $session === [] ? '{}' : json_encode($session)]);
    }

    /**
     * What createBooking answered the booking page asking for the stand for a week from $from (01-03-2027) for
     * $customer, from a visitor whose session holds $session (no session cookie when null).
     *
     * @param array<string, mixed>|null $session
     * @return array{status:int|false, body:string}
     */
    private function createBooking(mixed $customer, ?array $session, string $from = '2027-03-01'): array
    {
        $args = ['remoteBooking/api.php', 'createBooking&id=' . rawurlencode(self::$booking), json_encode([
            'product_id' => 1,
            'start_date' => $from,
            'end_date' => date('Y-m-d', strtotime($from . ' +7 days')),
            'weeks' => 1,
            'price' => 100,
            'item_id' => self::$fixture['stand'],
            'cust_id' => $customer,
            'sku' => self::$fixture['sku'],
            'unit' => 'stk',
        ])];
        if ($session !== null) {
            array_push($args, self::sessionId(), json_encode($session));
        }
        return $this->runChildJson('run_public_booking_api.php', $args);
    }

    /**
     * The arguments for a request from a browser that holds the session cookie $cookie ('' for none), to a server that
     * keeps the visitor's sessions between requests.
     *
     * @return list<string>
     */
    private static function fromBrowser(string $script, string $query, string $body, string $cookie): array
    {
        return [$script, $query, $body, $cookie, 'store:' . self::$sessions];
    }

    /**
     * The booking page (remoteBooking/index.php), opened by a browser that holds $cookie ('' for none).
     *
     * @return array{status:int|false, body:string, session:array<string, mixed>|null, cookie:string}
     */
    private function openPage(string $cookie): array
    {
        return $this->runChildJson('run_public_booking_api.php', self::fromBrowser('remoteBooking/index.php', 'db=' . rawurlencode(self::$booking), '', $cookie));
    }

    /** @return list<string> createCust with the booking page's form for $email, from a browser that holds $cookie */
    private static function createCustFrom(string $cookie, string $email): array
    {
        $form = json_encode(['name' => 'Booker Bodil', 'email' => $email, 'tlf' => '12345678', 'addr' => 'Testvej 1', 'zip' => '8000', 'city' => 'Aarhus']);
        return self::fromBrowser('remoteBooking/api.php', 'createCust&id=' . rawurlencode(self::$booking), $form, $cookie);
    }

    /** @return list<string> createBooking for the stand for a week from $from, for $customer, from a browser that holds $cookie */
    private static function createBookingFrom(string $cookie, string $customer, string $from): array
    {
        return self::fromBrowser('remoteBooking/api.php', 'createBooking&id=' . rawurlencode(self::$booking), json_encode([
            'product_id' => 1,
            'start_date' => $from,
            'end_date' => date('Y-m-d', strtotime($from . ' +7 days')),
            'weeks' => 1,
            'price' => 100,
            'item_id' => self::$fixture['stand'],
            'cust_id' => $customer,
            'sku' => self::$fixture['sku'],
            'unit' => 'stk',
        ]), $cookie);
    }

    /**
     * The answers to createCust requests that are all sent before any of them is answered, as from two tabs: each waits
     * at the customer lookup (adresser is locked) until all of them do.
     *
     * @param list<list<string>> $requests run_public_booking_api.php's arguments for each
     * @return list<array{status:int|false, body:string, session:array<string, mixed>|null, cookie:string}>
     */
    private function sentTogether(array $requests): array
    {
        $gate = CharacterizationEnv::connect(self::$booking);
        $observer = CharacterizationEnv::connect(self::$booking);
        $children = [];
        try {
            CharacterizationEnv::rows($gate, 'BEGIN');
            CharacterizationEnv::rows($gate, 'LOCK TABLE adresser IN ACCESS EXCLUSIVE MODE');
            foreach ($requests as $args) {
                $err = tmpfile();
                $proc = proc_open(array_merge([PHP_BINARY, dirname(__DIR__) . '/support/run_public_booking_api.php'], $args), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $err], $pipes);
                $this->assertIsResource($proc, 'could not start run_public_booking_api.php');
                fclose($pipes[0]);
                $children[] = ['proc' => $proc, 'out' => $pipes[1], 'err' => $err];
            }
            $deadline = microtime(true) + 30;
            while ((int) CharacterizationEnv::one($observer, "SELECT count(*) AS n FROM pg_stat_activity WHERE datname = $1 AND wait_event_type = 'Lock'", [self::$booking])['n'] < count($requests)) {
                foreach ($children as $child) {
                    if (!proc_get_status($child['proc'])['running']) {
                        break 2; // it answered without waiting: the asserts below say what
                    }
                }
                $this->assertLessThan($deadline, microtime(true), 'the requests never all waited at the customer lookup');
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
            $this->assertIsArray($answer, "run_public_booking_api.php must emit JSON on its last stdout line.\nstdout: $stdout\nstderr: $stderr");
            $answers[] = $answer;
        }
        return $answers;
    }

    /** The session cookie a browser holds after these answers, in this order, when it held $cookie before them. */
    private static function keptCookie(string $cookie, array ...$answers): string
    {
        foreach ($answers as $answer) {
            if ((string) ($answer['cookie'] ?? '') !== '') {
                $cookie = (string) $answer['cookie'];
            }
        }
        return $cookie;
    }

    private static function sessionId(): string
    {
        return 'chartest' . bin2hex(random_bytes(12));
    }

    /** The handle createCust answered, after checking it is one: 32 hex digits, which no adresser id is. */
    private function handleIn(array $res): string
    {
        $answer = json_decode(trim($res['body']), true);
        $this->assertIsArray($answer, 'createCust answers JSON; it said: ' . $res['body']);
        $this->assertSame(['id'], array_keys($answer), 'createCust answers only the handle: ' . $res['body']);
        $this->assertIsString($answer['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $answer['id'], 'createCust answers a handle, not a customer id: ' . $res['body']);
        return $answer['id'];
    }

    /**
     * The customer on the hold createBooking answered with and on its order, after checking it made one.
     *
     * @param array{status:int|false, body:string} $res
     * @return array{int, int} rentalperiod.cust_id and ordrer.konto_id
     */
    private function heldFor(array $res): array
    {
        $this->assertNotContains($res['status'], [400, 409], 'createBooking answered: ' . $res['body']);
        $hold = (int) (json_decode(trim($res['body']), true)['id'] ?? 0);
        $booked = self::query('SELECT rp.cust_id, o.konto_id FROM rentalperiod rp LEFT JOIN ordrer o ON o.id = rp.order_id WHERE rp.id = $1', [$hold]);
        $this->assertCount(1, $booked, 'the answer names the new hold: ' . $res['body']);
        return [(int) $booked[0]['cust_id'], (int) $booked[0]['konto_id']];
    }

    private static function customerWith(string $email): int
    {
        return (int) self::query('SELECT id FROM adresser WHERE email = $1', [$email])[0]['id'];
    }

    private static function newEmail(): string
    {
        return 'ny.chartest' . bin2hex(random_bytes(4)) . '@example.invalid';
    }

    /** @return array<string, array{string}> */
    public static function customers(): array
    {
        return [
            "the visitor's own e-mail"  => ['own'],
            "another customer's e-mail" => ['other'],
        ];
    }

    #[DataProvider('customers')]
    public function test_an_email_that_is_a_customers_is_answered_without_the_customers_id(string $who): void
    {
        $customer = self::$fixture[$who];
        $before = self::query('SELECT count(*) AS n, max(id) AS last FROM adresser')[0];

        $res = $this->createCust($customer['email']);

        $handle = $this->handleIn($res);
        $this->assertNotSame((string) $customer['id'], $handle);
        $this->assertSame($before, self::query('SELECT count(*) AS n, max(id) AS last FROM adresser')[0], 'no customer is made for an e-mail that is a customer\'s');
    }

    public function test_a_new_email_is_answered_without_the_new_customers_id(): void
    {
        $email = self::newEmail();

        $res = $this->createCust($email);

        $handle = $this->handleIn($res);
        $made = self::query('SELECT id FROM adresser WHERE email = $1', [$email]);
        $this->assertCount(1, $made, 'createCust made the customer');
        $this->assertNotSame((string) $made[0]['id'], $handle);
    }

    /** @return array<string, array{string}> */
    public static function visitors(): array
    {
        return [
            "with a customer's e-mail" => ['own'],
            'with a new e-mail'        => ['new'],
        ];
    }

    #[DataProvider('visitors')]
    public function test_the_booking_page_books_for_the_customer_createCust_answered_for(string $who): void
    {
        $email = $who === 'new' ? self::newEmail() : self::$fixture[$who]['email'];
        $customer = $this->createCust($email);
        $this->assertIsArray($customer['session'], 'createCust keeps the customer in the session');

        $res = $this->createBooking($this->handleIn($customer), $customer['session']);

        $expected = self::customerWith($email);
        $this->assertSame([$expected, $expected], $this->heldFor($res), 'the hold and its order are the customer the e-mail is');
    }

    /** @return array<string, array{string}> */
    public static function secondForms(): array
    {
        return [
            'with the same e-mail'           => ['own'],
            "with another customer's e-mail" => ['other'],
            'with a new e-mail'              => ['new'],
        ];
    }

    /** Two tabs of one visitor: both send the form (createCust, createCust), then both book (createBooking, createBooking). */
    #[DataProvider('secondForms')]
    public function test_a_handle_still_books_after_createCust_gave_the_session_another(string $second): void
    {
        $firstEmail = self::$fixture['own']['email'];
        $secondEmail = $second === 'new' ? self::newEmail() : self::$fixture[$second]['email'];
        $first = $this->createCust($firstEmail);
        $this->assertIsArray($first['session'], 'createCust keeps the customer in the session');
        $then = $this->createCust($secondEmail, $first['session']);
        $this->assertIsArray($then['session'], 'createCust keeps the customer in the session');
        [$firstHandle, $secondHandle] = [$this->handleIn($first), $this->handleIn($then)];
        $this->assertNotSame($firstHandle, $secondHandle);

        $firstBooking = $this->createBooking($firstHandle, $then['session']);
        $secondBooking = $this->createBooking($secondHandle, $then['session'], '2027-03-15');

        $expected = self::customerWith($firstEmail);
        $this->assertSame([$expected, $expected], $this->heldFor($firstBooking), 'the first tab books for its own customer');
        $expected = self::customerWith($secondEmail);
        $this->assertSame([$expected, $expected], $this->heldFor($secondBooking), 'the second tab books for its own customer');
    }

    /**
     * A browser without a session cookie opens the booking page in two tabs at once, and both send the form
     * (createCust) before either is answered; then both book (createBooking) with the cookie the browser kept.
     */
    #[DataProvider('secondForms')]
    public function test_two_tabs_of_a_browser_without_a_session_send_the_form_at_once_and_both_book(string $second): void
    {
        $firstEmail = self::$fixture['own']['email'];
        $secondEmail = $second === 'new' ? self::newEmail() : self::$fixture[$second]['email'];
        $tabs = [$this->openPage(''), $this->openPage('')]; // both asked for before either answered: neither has a cookie
        foreach ($tabs as $tab) {
            $this->assertStringContainsString('index.js', $tab['body'], 'the booking page is drawn: ' . substr($tab['body'], 0, 300));
            $this->assertNotSame('', $tab['cookie'], 'the booking page gives the browser its session cookie');
        }
        $cookie = self::keptCookie('', ...$tabs);

        $forms = $this->sentTogether([self::createCustFrom($cookie, $firstEmail), self::createCustFrom($cookie, $secondEmail)]);

        $this->assertSame([$cookie, $cookie], array_column($forms, 'cookie'), 'createCust keeps the customer in the session the page made, and gives the browser no other cookie');
        $cookie = self::keptCookie($cookie, ...$forms);
        [$firstHandle, $secondHandle] = [$this->handleIn($forms[0]), $this->handleIn($forms[1])];
        $this->assertNotSame($firstHandle, $secondHandle);
        $firstBooking = $this->runChildJson('run_public_booking_api.php', self::createBookingFrom($cookie, $firstHandle, '2027-03-01'));
        $secondBooking = $this->runChildJson('run_public_booking_api.php', self::createBookingFrom($cookie, $secondHandle, '2027-03-15'));

        $expected = self::customerWith($firstEmail);
        $this->assertSame([$expected, $expected], $this->heldFor($firstBooking), 'the first tab books for its own customer');
        $expected = self::customerWith($secondEmail);
        $this->assertSame([$expected, $expected], $this->heldFor($secondBooking), 'the second tab books for its own customer');
    }

    /** A tab opened after another one sent the form leaves that tab's customer in the session. */
    public function test_opening_the_booking_page_again_keeps_the_customer_another_tab_sent_the_form_for(): void
    {
        $cookie = self::keptCookie('', $this->openPage(''));
        $this->assertNotSame('', $cookie, 'the booking page gives the browser its session cookie');
        $form = $this->runChildJson('run_public_booking_api.php', self::createCustFrom($cookie, self::$fixture['own']['email']));
        $cookie = self::keptCookie($cookie, $form);

        $cookie = self::keptCookie($cookie, $this->openPage($cookie));
        $res = $this->runChildJson('run_public_booking_api.php', self::createBookingFrom($cookie, $this->handleIn($form), '2027-03-01'));

        $expected = self::$fixture['own']['id'];
        $this->assertSame([$expected, $expected], $this->heldFor($res), 'the first tab still books for its customer');
    }

    /** @return array<string, array{string}> */
    public static function claimsThatAreNotTheVisitors(): array
    {
        return [
            "a customer's id, without a session"                           => ['id, no session'],
            "a customer's id, from the session createCust gave its handle" => ['id, its session'],
            "createCust's handle, from another session"                    => ['handle, another session'],
            "createCust's handle, without a session"                       => ['handle, no session'],
            'a handle the session was given at another regnskab'           => ['handle, another regnskab'],
        ];
    }

    #[DataProvider('claimsThatAreNotTheVisitors')]
    public function test_createBooking_refuses_a_customer_it_was_not_handed_in_this_session(string $claim): void
    {
        $customer = self::$fixture['other'];
        $asked = $this->createCust($customer['email']);
        $handle = $this->handleIn($asked);
        $elsewhere = [];
        $otherHandle = rental_booking_customer_issue($elsewhere, PublicBookingDatabase::name('_cu_2'), $customer['id']);
        [$sent, $session] = match ($claim) {
            'id, no session'           => [$customer['id'], null],
            'id, its session'          => [$customer['id'], $asked['session']],
            'handle, another session'  => [$handle, []],
            'handle, no session'       => [$handle, null],
            'handle, another regnskab' => [$otherHandle, $elsewhere],
        };

        $res = $this->createBooking($sent, $session);

        $this->assertSame(400, $res['status'], 'createBooking answered: ' . $res['body']);
        $this->assertSame([], self::query('SELECT id FROM rentalperiod'), 'no hold');
    }

    /**
     * What mysale/api.php?createBooking answered the portal's booking page (remote.js) asking for the stand for a week
     * from 01-03-2027 for customer $customer, from a visitor whose session $sid holds $session (no session cookie when null).
     *
     * @param array<string, mixed>|null $session
     * @return array{status:int|false, body:string, session:array<string, mixed>|null}
     */
    private function mySaleBooking(int $customer, string $sid, ?array $session): array
    {
        $args = ['mysale/api.php', 'createBooking&db=' . rawurlencode(self::$booking), json_encode([
            'product_id' => 1,
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-08',
            'weeks' => 1,
            'price' => 100,
            'item_id' => self::$fixture['stand'],
            'cust_id' => $customer, // getDB.php's id: the customer of the Mit salg link
            'sku' => self::$fixture['sku'],
            'unit' => 'stk',
        ])];
        if ($session !== null) {
            array_push($args, $sid, json_encode($session));
        }
        return $this->runChildJson('run_public_booking_api.php', $args);
    }

    /** The session mysale.php leaves when it has signed $customer of regnskab $db in, in session $sid (mySaleSignIn()). */
    private static function signedIn(string $sid, int $customer, string $db): array
    {
        $session = [];
        mySaleSignIn($session, $sid, (string) $customer, $db); // mysale.php has the adresser id as read from the database
        return $session;
    }

    public function test_mit_salg_books_for_the_customer_it_signed_in(): void
    {
        $sid = self::sessionId();
        $customer = self::$fixture['own']['id'];

        $res = $this->mySaleBooking($customer, $sid, self::signedIn($sid, $customer, self::$booking));

        $this->assertSame([$customer, $customer], $this->heldFor($res), 'the hold and its order are the signed-in customer');
    }

    /** @return array<string, array{string}> */
    public static function mySaleClaimsThatAreNotTheVisitors(): array
    {
        return [
            'no session cookie'                                     => ['no session'],
            'a session nobody signed in'                            => ['nobody'],
            'signed in as another customer'                         => ['another customer'],
            'the customer, but signed in in another session'        => ['another session'],
            'the customer, signed in at another regnskab'           => ['another regnskab'],
            'the customer, by a sign-in that recorded no regnskab'  => ['unbound'],
        ];
    }

    #[DataProvider('mySaleClaimsThatAreNotTheVisitors')]
    public function test_mit_salg_createBooking_refuses_a_customer_it_did_not_sign_in(string $claim): void
    {
        $sid = self::sessionId();
        $customer = self::$fixture['own']['id'];
        $session = match ($claim) {
            'no session'       => null,
            'nobody'           => [],
            'another customer' => self::signedIn($sid, self::$fixture['other']['id'], self::$booking),
            'another session'  => self::signedIn(self::sessionId(), $customer, self::$booking),
            'another regnskab' => self::signedIn($sid, $customer, PublicBookingDatabase::name('_cu_2')),
            'unbound'          => array_diff_key(self::signedIn($sid, $customer, self::$booking), ['mySaleDb' => true]),
        };
        $orders = self::query('SELECT count(*) AS n FROM ordrer');

        $res = $this->mySaleBooking($customer, $sid, $session);

        $this->assertSame(403, $res['status'], 'createBooking answered: ' . $res['body']);
        $this->assertArrayHasKey('error', (array) json_decode(trim($res['body']), true), 'an error the page can show');
        $this->assertSame([], self::query('SELECT id FROM rentalperiod'), 'no hold');
        $this->assertSame($orders, self::query('SELECT count(*) AS n FROM ordrer'), 'no order');
    }
}
