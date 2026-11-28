<?php
// tests/characterization/rental/RemoteBookingClosedDatesTest.test.php
//
// The public booking calendar draws with no closed day (testlab round 1,
// rental-public-booking, 2026-11-05).
//
// The calendar asks getClosedDates for the regnskab's closed days and maps
// over the answer to disable them (remoteBooking/index.js, mysale/remote.js).
// With rentalclosed empty, both copies of the endpoint, remoteBooking/api.php
// and mysale/api.php, answered {"msg":"Der er ingen lukkede dage",
// "success":false}, so the page threw "getClosedDates.map is not a function"
// and never drew the calendar.
//
// Driven through the two scripts themselves in a child process. They only
// take account database names that end in _<number>, which the test tenant's
// name does not, so they run against a fresh installation copied under such a
// name (support/PublicBookingDatabase.php).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261115 CL/ASR Integration: The regnskab is a copy of a fresh installation (PublicBookingDatabase), as master's test environment (2bd490c6) has no template to clone.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/support/PublicBookingDatabase.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class RemoteBookingClosedDatesTest extends CharacterizationTestCase
{
    /** The regnskab the public endpoints are asked about: a name they accept. */
    private static string $booking = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $booking = PublicBookingDatabase::name('_cd_1');
        PublicBookingDatabase::create(static::class, $booking);
        self::$booking = $booking; // from here on tearDownAfterClass() drops it, whatever fails below
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$booking !== '') {
            PublicBookingDatabase::drop(self::$booking);
            self::$booking = '';
        }
        parent::tearDownAfterClass();
    }

    /** @param list<int|string> $closedDays the rentalclosed.day values the regnskab has, and no others */
    private static function closeDays(array $closedDays): void
    {
        $conn = CharacterizationEnv::connect(self::$booking);
        try {
            CharacterizationEnv::rows($conn, 'DELETE FROM rentalclosed');
            foreach ($closedDays as $day) {
                CharacterizationEnv::rows($conn, 'INSERT INTO rentalclosed (day) VALUES ($1)', [(int) $day]);
            }
        } finally {
            pg_close($conn);
        }
    }

    /** What getClosedDates answered, decoded. */
    private function closedDates(string $script, string $param): mixed
    {
        $res = $this->runChildJson('run_public_booking_api.php', [$script, 'getClosedDates&' . $param . '=' . rawurlencode(self::$booking)]);
        $this->assertNotSame(400, $res['status'], "$script refused the regnskab: " . $res['body']);
        $answer = json_decode(trim((string) $res['body']), true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), "$script answers JSON; it said: " . $res['body']);
        return $answer;
    }

    /** @return array<string, array{string, string}> */
    public static function endpoints(): array
    {
        return [
            'remoteBooking/api.php (remoteBooking/index.js)' => ['remoteBooking/api.php', 'id'],
            'mysale/api.php (mysale/remote.js)'             => ['mysale/api.php', 'db'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_with_no_closed_day_the_answer_is_an_empty_list(string $script, string $param): void
    {
        self::closeDays([]);

        $this->assertSame([], $this->closedDates($script, $param), 'a list the calendar can map over, not a message object');
    }

    #[DataProvider('endpoints')]
    public function test_a_closed_day_is_listed_with_its_date(string $script, string $param): void
    {
        self::closeDays([1795910400]); // 29-11-2026, the day the testlab closed

        $answer = $this->closedDates($script, $param);

        $this->assertIsArray($answer);
        $this->assertTrue(array_is_list($answer), 'a list the calendar can map over');
        $this->assertSame(['1795910400'], array_map('strval', array_column($answer, 'date')));
    }
}
