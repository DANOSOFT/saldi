<?php
// tests/characterization/support/run_chart_of_accounts_page.php
//
// Child-process runner for the chart of accounts, systemdata/kontoplan.php,
// and its account card, systemdata/kontokort.php.
//
// Both pages start a session and include connect/online at top level, so they
// run as a child process with a fabricated session that CharacterizationEnv
// seeded in `online`. The query string and the body are parsed with
// parse_str(), the way PHP builds $_GET and $_POST. A refusal ends the page
// with exit(), so the result is printed from a shutdown function.
//
// argv: 1 = page, kontoplan.php or kontokort.php
//       2 = query string, e.g. "id=12"
//       3 = form-urlencoded body to POST, or "" for a GET
//       4 = session id (must match the seeded online row)
//
// Prints one JSON line on stdout:
//   status   - the HTTP status the page set (200 when it set none)
//   html     - what the page printed
//   warnings - every PHP warning, notice and deprecation the request raised,
//              as "path:line: message" (path from the repository root).
//              @-suppressed calls (the page's @session_start()) are left out,
//              as PHP leaves them out of the log. With SALDI_CHAR_DEBUG set
//              they go to stderr as well.
//
// History:
// 20261105 CL/LH Created for KontoplanRightTest.
// 20261105 CL/LH Reports the request's warnings, for KontokortRefusedSaveTest.

if ($argc < 5) {
    fwrite(STDERR, "usage: php run_chart_of_accounts_page.php <kontoplan.php|kontokort.php> <query string> <body or \"\"> <session_id>\n");
    exit(2);
}
$chartestPage = $argv[1];
$chartestQuery = $argv[2];
$chartestBody = $argv[3];
$chartestSessionId = $argv[4];
if (!in_array($chartestPage, ['kontoplan.php', 'kontokort.php'], true)) {
    fwrite(STDERR, "page must be kontoplan.php or kontokort.php\n");
    exit(2);
}

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
$_SERVER['REQUEST_URI'] = '/saldi/systemdata/' . $chartestPage . '?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/systemdata/' . $chartestPage;
$_SERVER['SCRIPT_NAME'] = '/saldi/systemdata/' . $chartestPage;
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/systemdata/' . $chartestPage;
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

chdir(dirname(__DIR__, 3) . '/systemdata'); // the pages use ../includes/... relative includes

ob_start();
$chartestLevel = ob_get_level();
$chartestReport = static function () use ($chartestLevel, &$chartestWarnings): void {
    // The page's own buffer is still open when it simply ends; closing it runs its output handler.
    while (ob_get_level() > $chartestLevel) {
        ob_end_flush();
    }
    $html = (string) ob_get_clean();
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $html . "\n" . implode("\n", $chartestWarnings) . "\n");
    }
    fwrite(STDOUT, "\n" . json_encode(['status' => http_response_code() ?: 200, 'html' => $html, 'warnings' => $chartestWarnings], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
};
// Registered from a shutdown function, so it runs after the ones the page registers (includes/errorReport.php).
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
include __DIR__ . '/../../../systemdata/' . $chartestPage;
