<?php
// tests/characterization/rental/PublicBookingReadsTest.test.php
//
// The login-free booking APIs give out no order, customer or booking list
// without a claim to it (testlab round 1, rental-public-booking, 2026-11-05).
//
// Called without a cookie, remoteBooking/api.php?getOrder=<booking id> and its
// copy in mysale/api.php answered with the booking's whole order row and its
// lines, mysale/api.php?getUser=<customer id> with the whole adresser row of
// any customer, and mysale/api.php?getAllBookingsByUser=<customer id> with
// that customer's bookings. Nothing calls getOrder or getUser, so they are
// gone. The booking list (mysale/mybooking.js, "Mine bookinger") is answered
// only to the customer the Mit salg session signed in at that regnskab:
// mysale.php signs in with mySaleSignIn() (the session id, the customer and
// the regnskab), and api.php asks mySaleSignedInAs(). Customer ids are per
// regnskab, so two shops each have a customer 42; signed in at one, the list
// of the other's is refused.
//
// A temporary code (mysale.php?tmpcode=, sent by "Glemt adgangskode") signs in
// the customer number and regnskab of the row in temp/.ht_MyTmpPass it
// matches. The page used to keep reading the file after that row and took the
// regnskab from the last one, and took whichever Mit salg link the e-mail had
// there, so the code of a customer at one shop signed in the same e-mail's
// customer at another, or another customer with that e-mail at the same shop.
//
// The "Mine bookinger" page, mysale/mybooking.php, looks the regnskab up from
// the link in ?id= in the master's mysale table. It put the link into the SQL
// as it came, so ?id=x' failed the query (unterminated quoted string) and the
// page alerted, and ?id=' OR '1'='1 found the regnskab of whichever link came
// first. Its copy in remoteBooking.php and getDB.php already escaped it.
//
// Driven through the scripts themselves in a child process, with and without
// a session cookie. The sessions are made by mySaleSignIn() itself, or by
// mysale.php from a temporary code. The scripts only take account database
// names that end in _<number>, which the test tenant's name does not, so they
// run against two clones of the template named that way, with a customer of
// the same id in each.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261105 CL/LH mysale/mybooking.php looks a link with a quote in it up as text (testlab round 1, DEVY-14).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../../../mysale/shopLookup.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class PublicBookingReadsTest extends CharacterizationTestCase
{
    /** @var array<string, string> the two regnskaber the public endpoints are asked about, 'A' and 'B': names they accept */
    private static array $tenants = [];

    /**
     * @var array<string, array<string, array{id:int, kontonr:string, name:string, email:string, link:string, bookings:list<int>}>>
     *   per regnskab its customers: A has 'customer', 'other' and 'sibling', B a 'customer' with the id of A's 'customer';
     *   A's 'sibling' and B's 'customer' have the e-mail of A's 'customer', and each of them has a Mit salg link
     */
    private static array $people = [];

    /** The recovery file mysale.php reads temporary codes from (../temp/.ht_MyTmpPass from mysale/). */
    private const TMP_PASS_FILE = 'temp/.ht_MyTmpPass';

    /** The booking at A that has an order (getOrder). */
    private static int $orderedBooking;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            foreach (['A' => 1, 'B' => 2] as $at => $n) {
                $name = CharacterizationEnv::testDb() . '_pr_' . $n;
                if (!preg_match('/^[a-z][a-z0-9_]*_[0-9]+$/', $name) || in_array($name, [CharacterizationEnv::templateDb(), CharacterizationEnv::masterDb()], true)) {
                    throw new RuntimeException('unsafe database name ' . $name);
                }
                self::$tenants[$at] = $name; // from here on tearDownAfterClass() drops it, whatever fails below
                CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $name));
                CharacterizationEnv::rows($master, 'CREATE DATABASE ' . pg_escape_identifier($master, $name) . ' TEMPLATE ' . pg_escape_identifier($master, CharacterizationEnv::templateDb()));
            }
        } finally {
            pg_close($master);
        }

        // name and e-mail; one e-mail can be a customer's at two shops, and two customers' at one shop
        $customers = [
            'A' => ['customer' => ['Booker Bodil', 'bodil'], 'other' => ['Anden Arne', 'arne'], 'sibling' => ['Booker Bent', 'bodil']],
            'B' => ['customer' => ['Fremmed Frida', 'bodil']],
        ];
        $holds = [
            'A' => [['customer', '2027-03-01', '2027-03-08', true], ['customer', '2027-04-01', '2027-04-08', false], ['other', '2027-05-01', '2027-05-08', false], ['sibling', '2027-08-01', '2027-08-08', false]],
            'B' => [['customer', '2027-06-01', '2027-06-08', false], ['customer', '2027-07-01', '2027-07-08', false]],
        ];
        foreach (self::$tenants as $at => $tenant) {
            $conn = CharacterizationEnv::connect($tenant);
            try {
                $fx = new Fixtures($conn, self::$regnaar);
                foreach ($customers[$at] as $who => [$name, $mailbox]) {
                    $email = $mailbox . '.chartest@example.invalid';
                    $debtor = $fx->debtor(['firmanavn' => $name, 'email' => $email]);
                    $id = $debtor['id'];
                    if ($at === 'B' && $who === 'customer' && $id !== self::$people['A']['customer']['id']) {
                        // the same customer id as at A: a fresh clone of the template has it free
                        CharacterizationEnv::rows($conn, 'UPDATE adresser SET id = $1 WHERE id = $2', [self::$people['A']['customer']['id'], $id]);
                        $id = self::$people['A']['customer']['id'];
                    }
                    // the link mysale.php keeps in the master's mysale table: hex of id|kontonr@db@host
                    $link = bin2hex($id . '|' . $debtor['kontonr'] . '@' . $tenant . '@localhost');
                    self::$people[$at][$who] = ['id' => $id, 'kontonr' => $debtor['kontonr'], 'name' => $name, 'email' => $email, 'link' => $link, 'bookings' => []];
                }
                $item = $fx->item();
                $stand = (int) CharacterizationEnv::one($conn, 'INSERT INTO rentalitems (item_name, product_id) VALUES ($1, $2) RETURNING id', ['Stand ' . $at, $item['id']])['id'];
                foreach ($holds[$at] as [$who, $from, $to, $withOrder]) {
                    $person = self::$people[$at][$who];
                    $orderId = $withOrder
                        ? $fx->order($person, [['item' => $item, 'beskrivelse' => "Udlejning: Fra $from til $to"]], ['firmanavn' => $person['name'], 'email' => $person['email']])['id']
                        : 0;
                    $booking = (int) CharacterizationEnv::one(
                        $conn,
                        'INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id) VALUES ($1, $2, $3, $4, $5) RETURNING id',
                        [$stand, $person['id'], strtotime($from), strtotime($to), $orderId]
                    )['id'];
                    self::$people[$at][$who]['bookings'][] = $booking;
                    if ($withOrder) {
                        self::$orderedBooking = $booking;
                    }
                }
            } finally {
                pg_close($conn);
            }
        }
        if (self::$people['B']['customer']['id'] !== self::$people['A']['customer']['id']) {
            throw new RuntimeException('the two regnskaber need a customer with the same id');
        }

        // The Mit salg links, in the master database. The sibling's comes before the customer's: the first link of
        // the e-mail at A is not the customer's.
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'DELETE FROM mysale WHERE db = ANY($1)', ['{' . implode(',', self::$tenants) . '}']);
            foreach ([['A', 'sibling'], ['A', 'customer'], ['A', 'other'], ['B', 'customer']] as [$at, $who]) {
                $person = self::$people[$at][$who];
                CharacterizationEnv::rows($master, 'INSERT INTO mysale (deb_id, db, email, link) VALUES ($1, $2, $3, $4)', [$person['id'], self::$tenants[$at], $person['email'], $person['link']]);
            }
        } finally {
            pg_close($master);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenants !== []) {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            try {
                CharacterizationEnv::rows($master, 'DELETE FROM mysale WHERE db = ANY($1)', ['{' . implode(',', self::$tenants) . '}']);
                foreach (self::$tenants as $tenant) {
                    CharacterizationEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()', [$tenant]);
                    CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $tenant));
                }
            } finally {
                pg_close($master);
            }
            self::$tenants = [];
        }
        parent::tearDownAfterClass();
    }

    /**
     * What the script answered to a GET with that action about regnskab $at, and the Mit salg session when one is given.
     *
     * @param array<string, mixed>|null $session what the session holds, '<sid>' standing for its id, which is made here
     * @return array{status:int|false, body:string}
     */
    private function ask(string $script, string $action, string $at = 'A', ?array $session = null): array
    {
        $param = $script === 'remoteBooking/api.php' ? 'id' : 'db';
        $args = [$script, $action . '&' . $param . '=' . rawurlencode(self::$tenants[$at])];
        if ($session !== null) {
            $sid = 'chartest' . bin2hex(random_bytes(12));
            array_push($args, '', $sid, json_encode(array_map(static fn($v) => $v === '<sid>' ? $sid : $v, $session)));
        }
        $res = $this->runChildJson('run_public_booking_api.php', $args);
        $this->assertNotSame(400, $res['status'], "$script refused the regnskab: " . $res['body']);
        return ['status' => $res['status'], 'body' => (string) $res['body']];
    }

    /**
     * The session mysale.php leaves when it has signed $who of regnskab $at in.
     *
     * @return array<string, mixed>
     */
    private static function signedIn(string $who, string $at): array
    {
        $session = [];
        mySaleSignIn($session, '<sid>', (string) self::$people[$at][$who]['id'], self::$tenants[$at]); // mysale.php has the adresser id as read from the database
        return $session;
    }

    private function assertNothingAbout(array $person, string $body, string $what): void
    {
        $this->assertStringNotContainsString($person['name'], $body, "$what gives out the customer's name");
        $this->assertStringNotContainsString($person['email'], $body, "$what gives out the customer's email");
    }

    /** @return array<string, array{string}> */
    public static function endpoints(): array
    {
        return [
            'remoteBooking/api.php' => ['remoteBooking/api.php'],
            'mysale/api.php'        => ['mysale/api.php'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_getOrder_gives_out_no_order(string $script): void
    {
        $res = $this->ask($script, 'getOrder=' . self::$orderedBooking);

        $this->assertNothingAbout(self::$people['A']['customer'], $res['body'], "getOrder on $script");
        $this->assertStringNotContainsString('orderLines', $res['body'], "getOrder on $script answers with the order and its lines");
        $this->assertStringNotContainsString('Udlejning', $res['body'], "getOrder on $script answers with the order's lines");
    }

    public function test_getUser_gives_out_no_customer_row(): void
    {
        foreach (['customer', 'other'] as $who) {
            $person = self::$people['A'][$who];
            $res = $this->ask('mysale/api.php', 'getUser=' . $person['id']);

            $this->assertNothingAbout($person, $res['body'], "getUser for the $who");
            $this->assertStringNotContainsString('kontonr', $res['body'], "getUser for the $who answers with the adresser row");
        }
    }

    /**
     * The list of the customer with the shared id at regnskab $asked, and the session the request carries:
     * none, nobody's, or signed in as $as of regnskab $at, perhaps in another session or by a sign-in that recorded no regnskab.
     *
     * @return array<string, array{string, array{as?:string, at?:string, session?:string, unbound?:bool}|null}>
     */
    public static function noClaim(): array
    {
        return [
            'no session cookie'                                       => ['A', null],
            'a session nobody signed in'                              => ['A', []],
            'signed in as another customer'                           => ['A', ['as' => 'other', 'at' => 'A']],
            'the customer, but signed in in another session'          => ['A', ['as' => 'customer', 'at' => 'A', 'session' => 'chartest-another-session']],
            'the customer, by a sign-in that recorded no regnskab'    => ['A', ['as' => 'customer', 'at' => 'A', 'unbound' => true]],
            'the same customer id, signed in at the other regnskab'   => ['B', ['as' => 'customer', 'at' => 'A']],
            'and the other way round'                                 => ['A', ['as' => 'customer', 'at' => 'B']],
        ];
    }

    /** @param array{as?:string, at?:string, session?:string, unbound?:bool}|null $claim */
    #[DataProvider('noClaim')]
    public function test_the_booking_list_is_refused_without_a_claim_to_the_customer_at_that_regnskab(string $asked, ?array $claim): void
    {
        $session = $claim;
        if (isset($claim['as'])) {
            $session = self::signedIn($claim['as'], $claim['at']);
            if (isset($claim['session'])) {
                $session['mySalePw'] = $claim['session'];
            }
            if (!empty($claim['unbound'])) {
                unset($session['mySaleDb']); // what mysale.php stored before it recorded the regnskab
            }
        }

        $res = $this->ask('mysale/api.php', 'getAllBookingsByUser=' . self::$people[$asked]['customer']['id'], $asked, $session);

        $this->assertSame(403, $res['status'], 'refused: ' . $res['body']);
        $this->assertSame([], json_decode(trim($res['body']), true), 'still a list, so mybooking.js does not throw before it sends the visitor to sign in');
    }

    /** @return array<string, array{string}> */
    public static function regnskaber(): array
    {
        return ['at A' => ['A'], 'at B' => ['B']];
    }

    #[DataProvider('regnskaber')]
    public function test_the_signed_in_customer_gets_their_bookings_and_no_one_elses(string $at): void
    {
        $res = $this->ask('mysale/api.php', 'getAllBookingsByUser=' . self::$people[$at]['customer']['id'], $at, self::signedIn('customer', $at));

        $this->assertBookingsOf('customer', $at, $res);
    }

    /** @param array{status:int|false, body:string} $res */
    private function assertBookingsOf(string $who, string $at, array $res): void
    {
        $this->assertNotSame(403, $res['status'], "the $who Mit salg signed in at $at is refused: " . $res['body']);
        $bookings = json_decode(trim($res['body']), true);
        $this->assertIsArray($bookings, 'a list; it said: ' . $res['body']);
        $ids = array_map('intval', array_column($bookings, 'id'));
        sort($ids);
        $this->assertSame(self::$people[$at][$who]['bookings'], $ids, "the $who's own bookings at $at, and not another customer's");
        $this->assertSame(['Stand ' . $at], array_values(array_unique(array_column($bookings, 'item_name'))), "the bookings of $at, not of the other regnskab");
    }

    /**
     * Opens mysale.php?tmpcode= for $email and $code, with temp/.ht_MyTmpPass holding $rows while it runs.
     *
     * @param list<array{string, string, string, string}> $rows e-mail, code, regnskab ('A' or 'B') and customer number, the way resetPW writes them
     * @return array{html:string, session:array<string, mixed>, sid:string} what the page printed and left in its session, and the session id
     */
    private function openTmpCode(array $rows, string $email, string $code): array
    {
        $file = CharacterizationEnv::repoRoot() . '/' . self::TMP_PASS_FILE;
        $this->assertDirectoryIsWritable(dirname($file), 'the stack has no temp/ directory to put the recovery file in');
        $before = is_file($file) ? file_get_contents($file) : null;
        $lines = '';
        foreach ($rows as [$rowEmail, $rowCode, $at, $kontonr]) {
            $lines .= implode(chr(9), [$rowEmail, $rowCode, self::$tenants[$at], $kontonr, (string) time()]) . "\n";
        }
        file_put_contents($file, $lines);
        try {
            $sid = 'chartest' . bin2hex(random_bytes(12));
            $res = $this->runChildJson('run_mysale_page.php', ['tmpcode=' . bin2hex($email . '|' . $code), $sid]);
        } finally {
            if ($before === null) {
                unlink($file);
            } else {
                file_put_contents($file, $before);
            }
        }
        return ['html' => (string) $res['html'], 'session' => is_array($res['session']) ? $res['session'] : [], 'sid' => $sid];
    }

    /**
     * The recovery file as the reviewer's case has it: the row a code matches is followed by rows for other customers
     * with the same e-mail, at the same shop and at another. Each code is for one of them.
     *
     * @return array<string, array{string, string}>
     */
    public static function tmpCodes(): array
    {
        return [
            "the customer's code at A, followed by the sibling's at A and the customer's at B" => ['A', 'customer'],
            "the sibling's code at A: the same e-mail, another customer number"               => ['A', 'sibling'],
            "the customer's code at B, the file's last row"                                    => ['B', 'customer'],
        ];
    }

    #[DataProvider('tmpCodes')]
    public function test_a_temporary_code_signs_in_the_customer_and_regnskab_of_its_own_row(string $at, string $who): void
    {
        $holders = [['A', 'customer'], ['A', 'sibling'], ['B', 'customer']];
        $codes = [];
        $rows = [];
        foreach ($holders as [$rowAt, $rowWho]) {
            $person = self::$people[$rowAt][$rowWho];
            $codes["$rowAt $rowWho"] = bin2hex(random_bytes(4));
            $rows[] = [$person['email'], $codes["$rowAt $rowWho"], $rowAt, $person['kontonr']];
        }
        $person = self::$people[$at][$who];

        $page = $this->openTmpCode($rows, $person['email'], $codes["$at $who"]);

        $this->assertStringContainsString("mysale.php?id={$person['link']}&", $page['html'], "the page goes on to the $who's own Mit salg link at $at");
        $this->assertTrue(
            mySaleSignedInAs($page['session'], $page['sid'], $person['id'], self::$tenants[$at]),
            "the session is signed in as the $who at $at; it holds " . json_encode($page['session'])
        );

        // The booking list takes that sign-in for this customer of this regnskab, and for no other.
        $session = array_map(static fn($v) => $v === $page['sid'] ? '<sid>' : $v, $page['session']);
        $this->assertBookingsOf($who, $at, $this->ask('mysale/api.php', 'getAllBookingsByUser=' . $person['id'], $at, $session));
        foreach ($holders as [$otherAt, $otherWho]) {
            if ([$otherAt, $otherWho] === [$at, $who]) {
                continue;
            }
            $res = $this->ask('mysale/api.php', 'getAllBookingsByUser=' . self::$people[$otherAt][$otherWho]['id'], $otherAt, $session);
            $this->assertSame(403, $res['status'], "signed in by the $who's code at $at, the list of the $otherWho at $otherAt: " . $res['body']);
        }
    }

    public function test_a_temporary_code_with_another_e_mail_signs_in_no_one(): void
    {
        $customer = self::$people['A']['customer'];
        $code = bin2hex(random_bytes(4));

        $page = $this->openTmpCode([[$customer['email'], $code, 'A', $customer['kontonr']]], self::$people['A']['other']['email'], $code);

        $this->assertStringNotContainsString('mysale.php?id=', $page['html'], 'the page goes on to a Mit salg link');
        $this->assertNotSame($page['sid'], $page['session']['mySalePw'] ?? null, 'the session is signed in: ' . json_encode($page['session']));
    }

    /**
     * Opens mysale/mybooking.php?id=$link without a sign-in, the way a link in a mail opens it.
     *
     * @return array{html:string, db:mixed} what the page printed, and the regnskab it found for the link (null for none)
     */
    private function openMyBooking(string $link): array
    {
        $res = $this->runChildJson('run_mysale_page.php', ['id=' . rawurlencode($link), 'chartest' . bin2hex(random_bytes(12)), 'mybooking.php']);
        $html = (string) $res['html'];
        $this->assertStringContainsString('mybooking.js', $html, 'the page is drawn');
        return ['html' => $html, 'db' => $res['db'] ?? null];
    }

    public function test_mybooking_finds_the_regnskab_of_a_link(): void
    {
        $page = $this->openMyBooking(self::$people['A']['customer']['link']);

        $this->assertSame(self::$tenants['A'], $page['db'], "the regnskab of the customer's Mit salg link");
    }

    /**
     * Links with a quote in them; '<link>' stands for A's customer's link.
     *
     * @return array<string, array{string}>
     */
    public static function quotedLinks(): array
    {
        return [
            "a lone quote (the testlab's probe)"                                 => ["x'"],
            "a customer's link with a quote after it"                            => ["<link>'"],
            'a quote that ends the text and adds a condition true for every row' => ["' OR '1'='1"],
        ];
    }

    #[DataProvider('quotedLinks')]
    public function test_mybooking_looks_a_link_with_a_quote_up_as_text(string $link): void
    {
        $link = str_replace('<link>', self::$people['A']['customer']['link'], $link);

        $page = $this->openMyBooking($link);

        $this->assertStringNotContainsString("<script type='text/javascript'>alert(", $page['html'], 'the lookup failed and the page alerted: ' . $page['html']);
        $this->assertNull($page['db'], 'no Mit salg link is that text, so the page has no regnskab');
    }
}
