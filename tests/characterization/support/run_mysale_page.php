<?php
// tests/characterization/support/run_mysale_page.php
//
// Child-process runner for the Mit salg page, mysale/mysale.php, as the
// customer's browser opens it: a GET with a session cookie. Also for its
// "Mine bookinger" page, mysale/mybooking.php, which looks the regnskab up
// from the link in ?id=.
//
// The page starts the session itself and signs the customer in by writing to
// it (mySaleSignIn()), so this runner reports what the session holds when the
// page is done, next to what it printed. The page ends with exit() on several
// branches, so the result is printed from a shutdown function, and the session
// is removed again after that.
//
// argv: 1 = query string, e.g. "tmpcode=<hex of email|code>"
//       2 = the session id, sent as the session cookie
//       3 = optional page in mysale/, mysale.php (the default) or
//           mybooking.php
//
// Prints one JSON line on stdout:
//   html    - what the page printed
//   session - what the session held after the page
//   db      - the page's $db when it is done: for mybooking.php the
//             regnskab it found for the link (null for none)
//
// History:
// 20261105 CL/LH Created for PublicBookingReadsTest (the temporary-code sign-in).
// 20261105 CL/LH An optional third argument runs mysale/mybooking.php instead, and the output has the page's $db (PublicBookingReadsTest: a quote in the link).

if ($argc < 3 || $argv[2] === '' || !in_array($argv[3] ?? 'mysale.php', ['mysale.php', 'mybooking.php'], true)) {
    fwrite(STDERR, "usage: php run_mysale_page.php <query string> <session id> [mysale.php|mybooking.php]\n");
    exit(2);
}
$chartestQuery = $argv[1];
$chartestSessionId = $argv[2];
$chartestPage = $argv[3] ?? 'mysale.php';

error_reporting(E_ALL);
set_error_handler(static function (): bool {
    return true; // keep the legacy page's warnings off stdout
});

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/mysale/' . $chartestPage . '?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/mysale/' . $chartestPage;
$_SERVER['SCRIPT_NAME'] = '/saldi/mysale/' . $chartestPage;
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/mysale/' . $chartestPage;
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
$_POST = [];
$_REQUEST = $_GET;
session_id($chartestSessionId); // the page's own session_start() opens this session
$_COOKIE[session_name()] = $chartestSessionId;

chdir(dirname(__DIR__, 3) . '/mysale'); // the page uses ../includes/... and ../temp/... relative paths

ob_start();
$chartestLevel = ob_get_level();
$chartestReport = static function () use ($chartestLevel, $chartestSessionId): void {
    while (ob_get_level() > $chartestLevel) {
        ob_end_flush();
    }
    $html = (string) ob_get_clean();
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $html . "\n");
    }
    $session = session_status() === PHP_SESSION_ACTIVE ? $_SESSION : null;
    fwrite(STDOUT, "\n" . json_encode(['html' => $html, 'session' => $session, 'db' => $GLOBALS['db'] ?? null], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
    if (session_status() === PHP_SESSION_ACTIVE) { // leave no session file behind
        session_destroy();
    } else {
        session_id($chartestSessionId);
        @session_start();
        session_destroy();
    }
};
// Registered from a shutdown function, so it runs after any the page's includes register.
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
include dirname(__DIR__, 3) . '/mysale/' . $chartestPage;
