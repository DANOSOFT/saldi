<?php
// tests/characterization/support/run_public_booking_api.php
//
// Child-process runner for the login-free booking APIs, remoteBooking/api.php
// and mysale/api.php.
//
// They need no session: the regnskab comes from the query string (?id= for
// remoteBooking/api.php, ?db= for mysale/api.php). Several branches end with
// exit(), so the result is printed from a shutdown function.
//
// argv: 1 = the script, remoteBooking/api.php or mysale/api.php
//       2 = query string, e.g. "getClosedDates&id=saldi_7"
//
// Prints one JSON line on stdout:
//   status - the HTTP status the script set (false when it set none)
//   body   - what it printed
//
// History:
// 20261105 CL/LH Created for RemoteBookingClosedDatesTest.

if ($argc < 3 || !in_array($argv[1], ['remoteBooking/api.php', 'mysale/api.php'], true)) {
    fwrite(STDERR, "usage: php run_public_booking_api.php <remoteBooking/api.php|mysale/api.php> <query string>\n");
    exit(2);
}
$chartestScript = $argv[1];
$chartestQuery = $argv[2];

error_reporting(E_ALL);
set_error_handler(static function (): bool {
    return true; // keep the legacy includes' warnings off stdout
});

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/' . $chartestScript . '?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/' . $chartestScript;
$_SERVER['SCRIPT_NAME'] = '/saldi/' . $chartestScript;
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/' . $chartestScript;
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
$_POST = [];
$_REQUEST = $_GET;

chdir(dirname(__DIR__, 3) . '/' . dirname($chartestScript));

ob_start();
$chartestLevel = ob_get_level();
$chartestReport = static function () use ($chartestLevel): void {
    while (ob_get_level() > $chartestLevel) {
        ob_end_flush();
    }
    $body = (string) ob_get_clean();
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $body . "\n");
    }
    fwrite(STDOUT, "\n" . json_encode(['status' => http_response_code(), 'body' => $body], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
};
// Registered from a shutdown function, so it runs after the ones the script registers (includes/errorReport.php).
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
include dirname(__DIR__, 3) . '/' . $chartestScript;
