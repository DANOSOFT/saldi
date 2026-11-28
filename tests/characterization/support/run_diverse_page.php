<?php
// tests/characterization/support/run_diverse_page.php
//
// Child-process runner for the old settings pages, systemdata/diverse.php.
//
// The page starts a session and includes connect/online at top level, so it
// runs as a child process with a fabricated session that CharacterizationEnv
// seeded in `online`. The query string and the body are parsed with
// parse_str(), the way PHP builds $_GET and $_POST. The session (and with it
// the CSRF token every post must carry) lives in the system temp directory
// under the seeded session id, so a POST in a second child sees the token
// the GET drew.
//
// The page may end with exit() (includes/online.php after the session is
// gone), so the result is printed from a shutdown function.
//
// argv: 1 = query string, e.g. "sektion=kontoindstillinger"
//       2 = form-urlencoded body to POST, or "" for a GET
//       3 = session id (must match the seeded online row)
//       4 = optional: the installation's charset, e.g. "LATIN9", to run the
//           page as on such an installation (support/ConnectEncodingStream.php);
//           without it the stack's own includes/connect.php decides
//
// Prints one JSON line on stdout:
//   status   - the HTTP status the page set
//   html     - what the page printed, as UTF-8: on a LATIN9 installation it is
//              read as the ISO-8859-1 the page declares (includes/online.php)
//   warnings - every PHP warning, notice and deprecation the request raised,
//              as "path:line: message" (path from the repository root), so
//              the test can tell the legacy floor from what a change adds.
//              @-suppressed calls (the page's @session_start()) are left out,
//              as PHP leaves them out of the log. With SALDI_CHAR_DEBUG set
//              they go to stderr as well.
//
// History:
// 20261105 CL/LH Created for KontoindstillingerLedgerTest.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_diverse_page.php <query string> <body or \"\"> <session_id>\n");
    exit(2);
}
$chartestQuery = $argv[1];
$chartestBody = $argv[2];
$chartestSessionId = $argv[3];
$chartestEncode = $argv[4] ?? '';

error_reporting(E_ALL);
$chartestRoot = dirname(__DIR__, 3) . '/';
$chartestWarnings = [];
set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use (&$chartestWarnings, $chartestRoot): bool {
    // @-suppressed calls arrive with error_reporting() masked.
    if (error_reporting() & $errno) {
        $file = str_starts_with($errfile, $chartestRoot) ? substr($errfile, strlen($chartestRoot)) : $errfile;
        $chartestWarnings[] = "$file:$errline: $errstr";
    }
    return true; // reported in the JSON line, not in the page's HTML
});

$_SERVER['REQUEST_METHOD'] = $chartestBody === '' ? 'GET' : 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/systemdata/diverse.php?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/systemdata/diverse.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/systemdata/diverse.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/systemdata/diverse.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
$_POST = [];
if ($chartestBody !== '') {
    parse_str($chartestBody, $_POST);
}
$_REQUEST = $_GET + $_POST;

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId);

chdir(dirname(__DIR__, 3) . '/systemdata'); // the page uses ../includes/... relative includes

ob_start();
$chartestLevel = ob_get_level();
$chartestReport = static function () use ($chartestLevel, &$chartestWarnings, $chartestEncode): void {
    // The page's own buffer (the CSRF filter) is still open when it simply ends; closing it runs its output handler.
    while (ob_get_level() > $chartestLevel) {
        ob_end_flush();
    }
    $html = (string) ob_get_clean();
    if ($chartestEncode !== '' && $chartestEncode !== 'UTF8') {
        $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1'); // includes/online.php: the charset a non-UTF8 page declares
    }
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $html . "\n" . implode("\n", $chartestWarnings) . "\n");
    }
    fwrite(STDOUT, "\n" . json_encode(['status' => http_response_code(), 'html' => $html, 'warnings' => $chartestWarnings], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
};
// Registered from a shutdown function, so it runs after the ones the page registers (includes/errorReport.php).
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
if ($chartestEncode !== '') {
    require_once __DIR__ . '/ConnectEncodingStream.php';
    ConnectEncodingStream::register(dirname(__DIR__, 3) . '/includes/connect.php', $chartestEncode);
}
include __DIR__ . '/../../../systemdata/diverse.php';
