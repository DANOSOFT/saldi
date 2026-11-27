<?php
// tests/characterization/support/run_public_booking_api.php
//
// Child-process runner for the login-free booking APIs, remoteBooking/api.php
// and mysale/api.php, and for the booking page that calls the first,
// remoteBooking/index.php.
//
// The regnskab comes from the query string (?id= for remoteBooking/api.php,
// ?db= for mysale/api.php and the page). Several branches end with exit(), so
// the result is printed from a shutdown function. mysale/api.php answers the
// booking list and books only for the customer the Mit salg session signed
// in, remoteBooking/api.php books only for a customer createCust put in the
// visitor's session, which the page makes, and both approve or reject only a
// booking the session's createBooking recorded in it, so a request can carry
// a session cookie.
//
// argv: 1 = the script, remoteBooking/api.php, mysale/api.php or
//           remoteBooking/index.php
//       2 = query string, e.g. "getClosedDates&id=saldi_7"
//       3 = optional request body, e.g. the JSON createBooking reads; sent as
//           a POST and read by the script from php://input ('' for none)
//       4 = optional session id, sent as the session cookie
//       5 = optional JSON object the session holds under that id, e.g.
//           {"mySalePw":"<the id>","mySaleAcId":"7","mySaleDb":"saldi_7"} as
//           mysale.php leaves it (mySaleSignIn()); the session is removed
//           after the script. Or "store:<directory>" for a visitor whose
//           sessions live on between requests, as on the server: they are
//           kept in that directory, the script finds the session as the
//           visitor's earlier requests left it, and it stays after the
//           script. Then argv 4 may be '' for a browser without a cookie.
//
// Prints one JSON line on stdout:
//   status  - the HTTP status the script set (false when it set none)
//   body    - what it printed
//   session - what the session held when the script was done (null without a
//             session id), for the next request of the same visitor
//   cookie  - the session id the browser holds after the answer: the one it
//             sent, or the one the script started a session under ('' for
//             none)
//
// History:
// 20261105 CL/LH Created for RemoteBookingClosedDatesTest.
// 20261105 CL/LH An optional third argument is the request body, read as php://input (ChartestRequestBody, RemoteBookingOverlapTest).
// 20261105 CL/LH Optional fourth and fifth arguments are a session cookie and what its session holds (PublicBookingReadsTest).
// 20261105 CL/LH The output has what the session held after the script (RemoteBookingCustomerTest: createCust, then createBooking).
// 20261105 CL/LH The booking page remoteBooking/index.php runs too, a visitor's sessions can live on between requests in a directory ("store:"), and the output has the session cookie the browser keeps (RemoteBookingCustomerTest: a browser without a cookie).
// 20261116 CL/ASR Documents that updateBooking and Mit salg's createBooking read the session too (RemoteBookingRejectTest, BookingOrderVatTest).

if ($argc < 3 || !in_array($argv[1], ['remoteBooking/api.php', 'mysale/api.php', 'remoteBooking/index.php'], true)) {
    fwrite(STDERR, "usage: php run_public_booking_api.php <remoteBooking/api.php|mysale/api.php|remoteBooking/index.php> <query string> [request body] [session id] [session JSON|store:<directory>]\n");
    exit(2);
}
$chartestScript = $argv[1];
$chartestQuery = $argv[2];
$chartestBody = ($argv[3] ?? '') === '' ? null : $argv[3];
$chartestSessionId = $argv[4] ?? '';
$chartestStore = str_starts_with($argv[5] ?? '', 'store:') ? substr($argv[5], strlen('store:')) : '';
$chartestSession = $chartestStore === '' ? json_decode($argv[5] ?? '{}', true) : null;

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

if ($chartestStore !== '') {
    // The visitor's sessions as the server keeps them: the script starts or finds the session itself.
    ini_set('session.save_path', $chartestStore);
    if ($chartestSessionId !== '') {
        session_id($chartestSessionId);
        $_COOKIE[session_name()] = $chartestSessionId;
    }
} elseif ($chartestSessionId !== '') {
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
$chartestReport = static function () use ($chartestLevel, $chartestSessionId, $chartestStore): void {
    while (ob_get_level() > $chartestLevel) {
        ob_end_flush();
    }
    $body = (string) ob_get_clean();
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $body . "\n");
    }
    $cookie = session_id(); // the one sent, or the one a session the script started got
    $session = null;
    if ($chartestStore !== '') { // it stays for the visitor's next request
        session_write_close();
        if ($cookie !== '') {
            session_id($cookie);
            @session_start(['read_and_close' => true]);
            $session = $_SESSION;
        }
    } elseif ($chartestSessionId !== '') { // leave no session file behind
        session_write_close();
        session_id($chartestSessionId);
        @session_start();
        $session = $_SESSION;
        session_destroy();
    }
    fwrite(STDOUT, "\n" . json_encode(['status' => http_response_code(), 'body' => $body, 'session' => $session, 'cookie' => $cookie], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
};
// Registered from a shutdown function, so it runs after the ones the script registers (includes/errorReport.php).
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
include dirname(__DIR__, 3) . '/' . $chartestScript;
