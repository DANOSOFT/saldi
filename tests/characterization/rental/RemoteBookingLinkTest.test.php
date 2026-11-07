<?php
// tests/characterization/rental/RemoteBookingLinkTest.test.php
//
// The public booking link the booking settings show (rental/remote.php,
// "Link til jeres eksterne booking") opens the booking page (testlab round 1,
// rental-public-booking, 2026-11-05).
//
// rental/rental.php?getRemoteLink built the link as
// https://<SERVER_NAME>/<folder>/remoteBooking/index.php?id=<regnskab>.
// remoteBooking/index.php and index.js read the regnskab from ?db=, so the
// page refused every such link as "Invalid account", and on a server reached
// over http or on another port (http://localhost:5000) the link did not reach
// the server at all.
//
// Driven through rental/rental.php and remoteBooking/index.php themselves:
// the link is asked for the way the settings page asks for it, under the Host
// and scheme a browser sends, and the booking page is opened with the link's
// query. The public page only takes account database names that end in
// _<number>, which the test tenant's name does not, so the session is moved
// to a clone of the template named that way.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class RemoteBookingLinkTest extends CharacterizationTestCase
{
    /** The regnskab the session is in while the link is asked for: a name the public page accepts. */
    private static string $booking = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $booking = CharacterizationEnv::testDb() . '_rb_1';
        if (!preg_match('/^[a-z][a-z0-9_]*_[0-9]+$/', $booking) || in_array($booking, [CharacterizationEnv::templateDb(), CharacterizationEnv::masterDb()], true)) {
            throw new RuntimeException('unsafe database name ' . $booking);
        }
        self::$booking = $booking; // from here on tearDownAfterClass() drops it, whatever fails below
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $booking));
            CharacterizationEnv::rows($master, 'CREATE DATABASE ' . pg_escape_identifier($master, $booking) . ' TEMPLATE ' . pg_escape_identifier($master, CharacterizationEnv::templateDb()));
            CharacterizationEnv::rows($master, 'DELETE FROM regnskab WHERE db = $1', [$booking]);
            CharacterizationEnv::rows(
                $master,
                "INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version, sidst, brugerantal, posteringer, lukket, administrator)
                 SELECT 'chartest_booking', dbhost, dbuser, $1, version, sidst, brugerantal, 1000000, '', administrator
                 FROM regnskab WHERE db = $2",
                [$booking, CharacterizationEnv::testDb()]
            );
        } finally {
            pg_close($master);
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
                CharacterizationEnv::rows($master, "DELETE FROM regnskab WHERE db = $1 AND regnskab = 'chartest_booking'", [self::$booking]);
            } finally {
                pg_close($master);
            }
            self::$booking = '';
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            self::moveSession($master, self::$booking);
        } finally {
            pg_close($master);
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

    /** The link rental/remote.php shows, asked for under this Host header and scheme. */
    private function remoteLink(string $host, string $scheme): string
    {
        $res = $this->runChildJson('run_rental_api.php', ['getRemoteLink', CharacterizationEnv::SESSION_ID, $host, $scheme]);
        $link = json_decode((string) $res['body'], true);
        $this->assertIsString($link, 'getRemoteLink answers with the link as a JSON string; it said: ' . $res['body']);
        return $link;
    }

    /** @return array<string, array{string, string, string}> */
    public static function servers(): array
    {
        return [
            'http on another port (the lab, a local install)' => ['localhost:5000', 'http', 'http://localhost:5000'],
            'https on the default port'                        => ['ssl7.saldi.dk', 'https', 'https://ssl7.saldi.dk'],
            'https ended at a reverse proxy'                   => ['ssl7.saldi.dk', 'proxy-https', 'https://ssl7.saldi.dk'],
        ];
    }

    #[DataProvider('servers')]
    public function test_the_link_is_on_the_server_the_settings_page_was_opened_on(string $host, string $scheme, string $origin): void
    {
        $this->assertSame(
            $origin . '/saldi/remoteBooking/index.php?db=' . self::$booking,
            $this->remoteLink($host, $scheme),
            'same scheme, host and port as the request, the regnskab in ?db='
        );
    }

    public function test_the_link_opens_the_booking_page_of_the_regnskab(): void
    {
        $link = $this->remoteLink('localhost:5000', 'http');

        $page = $this->runChildJson('run_remote_booking_page.php', [(string) parse_url($link, PHP_URL_QUERY)]);

        $this->assertNotSame(400, $page['status'], "the booking page refused $link: " . $page['html']);
        $this->assertStringNotContainsString('Invalid account', $page['html']);
        $this->assertStringContainsString('<title>Booking</title>', $page['html'], 'the booking page itself is drawn');
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $this->assertSame(['db' => self::$booking], $query, 'the link names the regnskab and nothing else');
    }
}
