<?php
// tests/characterization/support/run_rental_api.php
//
// Child-process runner for the booking module's API, rental/rental.php.
//
// The script starts a session and includes connect/online at top level, so it
// runs as a child process with a fabricated session that CharacterizationEnv
// seeded in `online`. The Host header and the scheme come from the arguments,
// so a test can call it the way a browser on a given server and port does.
//
// argv: 1 = query string, e.g. "getRemoteLink"
//       2 = session id (must match the seeded online row)
//       3 = Host header, e.g. "localhost:5000"
//       4 = "https" for a request over TLS, "proxy-https" for one a reverse
//           proxy took over TLS for (X-Forwarded-Proto), "http" otherwise
//
// Prints one JSON line on stdout:
//   status - the HTTP status the script set
//   body   - what it printed
//
// History:
// 20261105 CL/LH Created for RemoteBookingLinkTest.

if ($argc < 5) {
    fwrite(STDERR, "usage: php run_rental_api.php <query string> <session_id> <host> <http|https|proxy-https>\n");
    exit(2);
}
$chartestQuery = $argv[1];
$chartestSessionId = $argv[2];
$chartestHost = $argv[3];
$chartestScheme = $argv[4];

error_reporting(E_ALL);
set_error_handler(static function (): bool {
    return true; // keep the legacy includes' warnings off stdout
});

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/rental/rental.php?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/rental/rental.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/rental/rental.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/rental/rental.php';
$_SERVER['HTTP_HOST'] = $chartestHost;
$_SERVER['SERVER_NAME'] = preg_replace('/:\d+$/', '', $chartestHost);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_SERVER['CONTENT_TYPE'] = 'application/json'; // rental/api/api.js sends it on every call, so online.php prints no page styles
if ($chartestScheme === 'https') {
    $_SERVER['HTTPS'] = 'on';
} elseif ($chartestScheme === 'proxy-https') {
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
}
parse_str($chartestQuery, $_GET);
$_POST = [];
$_REQUEST = $_GET;

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId);

chdir(dirname(__DIR__, 3) . '/rental'); // the script uses ../includes/... relative includes

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
include __DIR__ . '/../../../rental/rental.php';
