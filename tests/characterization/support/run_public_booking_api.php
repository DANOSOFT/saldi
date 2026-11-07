<?php
// tests/characterization/support/run_public_booking_api.php
//
// Child-process runner for the login-free booking APIs, remoteBooking/api.php
// and mysale/api.php.
//
// They need no session: the regnskab comes from the query string (?id= for
// remoteBooking/api.php, ?db= for mysale/api.php). Several branches end with
// exit(), so the result is printed from a shutdown function. mysale/api.php
// answers the booking list only to the customer the Mit salg session signed
// in, so a request can carry a session cookie.
//
// argv: 1 = the script, remoteBooking/api.php or mysale/api.php
//       2 = query string, e.g. "getClosedDates&id=saldi_7"
//       3 = optional request body, e.g. the JSON createBooking reads; sent as
//           a POST and read by the script from php://input ('' for none)
//       4 = optional session id, sent as the session cookie
//       5 = optional JSON object the session holds under that id, e.g.
//           {"mySalePw":"<the id>","mySaleAcId":"7","mySaleDb":"saldi_7"} as
//           mysale.php leaves it (mySaleSignIn())
//
// Prints one JSON line on stdout:
//   status - the HTTP status the script set (false when it set none)
//   body   - what it printed
//
// History:
// 20261105 CL/LH Created for RemoteBookingClosedDatesTest.
// 20261105 CL/LH An optional third argument is the request body, read as php://input (ChartestRequestBody, RemoteBookingOverlapTest).
// 20261105 CL/LH Optional fourth and fifth arguments are a session cookie and what its session holds (PublicBookingReadsTest).

if ($argc < 3 || !in_array($argv[1], ['remoteBooking/api.php', 'mysale/api.php'], true)) {
    fwrite(STDERR, "usage: php run_public_booking_api.php <remoteBooking/api.php|mysale/api.php> <query string> [request body] [session id] [session JSON]\n");
    exit(2);
}
$chartestScript = $argv[1];
$chartestQuery = $argv[2];
$chartestBody = ($argv[3] ?? '') === '' ? null : $argv[3];
$chartestSessionId = $argv[4] ?? '';
$chartestSession = json_decode($argv[5] ?? '{}', true);

require_once __DIR__ . '/ChartestRequestBody.php';

error_reporting(E_ALL);
set_error_handler(static function (): bool {
    return true; // keep the legacy includes' warnings off stdout
});

$_SERVER['REQUEST_METHOD'] = $chartestBody === null ? 'GET' : 'POST';
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
if ($chartestBody !== null) {
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    ChartestRequestBody::register($chartestBody);
}

if ($chartestSessionId !== '') {
    // Stored the way the page that signed the visitor in left it; the script reads it again itself.
    session_id($chartestSessionId);
    session_start();
    $_SESSION = is_array($chartestSession) ? $chartestSession : [];
    session_write_close();
    $_SESSION = [];
    session_id($chartestSessionId);
    $_COOKIE[session_name()] = $chartestSessionId;
}

chdir(dirname(__DIR__, 3) . '/' . dirname($chartestScript));

ob_start();
$chartestLevel = ob_get_level();
$chartestReport = static function () use ($chartestLevel, $chartestSessionId): void {
    while (ob_get_level() > $chartestLevel) {
        ob_end_flush();
    }
    $body = (string) ob_get_clean();
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $body . "\n");
    }
    fwrite(STDOUT, "\n" . json_encode(['status' => http_response_code(), 'body' => $body], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
    if ($chartestSessionId !== '') { // leave no session file behind
        session_write_close();
        session_id($chartestSessionId);
        @session_start();
        session_destroy();
    }
};
// Registered from a shutdown function, so it runs after the ones the script registers (includes/errorReport.php).
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
include dirname(__DIR__, 3) . '/' . $chartestScript;
