<?php
// tests/characterization/support/run_remote_booking_page.php
//
// Child-process runner for the public booking page, remoteBooking/index.php.
//
// The page needs no login: it takes the regnskab from the query string and
// answers 400 when it is missing or not an account database name. It ends
// with exit() on that refusal, so the result is printed from a shutdown
// function.
//
// argv: 1 = query string, e.g. "db=saldi_7"
//
// Prints one JSON line on stdout:
//   status - the HTTP status the page set (false when it set none)
//   html   - what the page printed
//
// History:
// 20261105 CL/LH Created for RemoteBookingLinkTest.

if ($argc < 2) {
    fwrite(STDERR, "usage: php run_remote_booking_page.php <query string>\n");
    exit(2);
}
$chartestQuery = $argv[1];

error_reporting(E_ALL);
set_error_handler(static function (): bool {
    return true; // keep the legacy includes' warnings off stdout
});

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/remoteBooking/index.php?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/remoteBooking/index.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/remoteBooking/index.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/remoteBooking/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
$_POST = [];
$_REQUEST = $_GET;

chdir(dirname(__DIR__, 3) . '/remoteBooking');

ob_start();
$chartestLevel = ob_get_level();
$chartestReport = static function () use ($chartestLevel): void {
    while (ob_get_level() > $chartestLevel) {
        ob_end_flush();
    }
    $html = (string) ob_get_clean();
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $html . "\n");
    }
    fwrite(STDOUT, "\n" . json_encode(['status' => http_response_code(), 'html' => $html], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
};
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
include __DIR__ . '/../../../remoteBooking/index.php';
