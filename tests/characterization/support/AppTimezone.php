<?php
// tests/characterization/support/AppTimezone.php
//
// "Today" in a test as the code it runs has it. A page sets Europe/Copenhagen (includes/online.php, includes/settings.php),
// the test process keeps php.ini's zone (UTC in CI) unless an earlier test in the same process included one of those
// files. Between midnight and 01:00 (02:00 in summer) the two dates differ, and a test that dates its rows or its
// expectations with date() failed against a child process that dates them in Copenhagen.
//
// History:
// 20261125 CL/ASR Created for the tests that compare their dates with a page's (POS day close, commission, dunning).

trait AppTimezone
{
    private static string $timezoneBefore = '';

    /** From here on date() gives the day and time a page gives; call at the start of setUpBeforeClass(). */
    private static function useAppTimezone(): void
    {
        self::$timezoneBefore = date_default_timezone_get();
        date_default_timezone_set('Europe/Copenhagen');
    }

    /** The test process's zone as before; call in tearDownAfterClass(). */
    private static function restoreTimezone(): void
    {
        if (self::$timezoneBefore !== '') {
            date_default_timezone_set(self::$timezoneBefore);
        }
    }
}
