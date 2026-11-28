<?php
// tests/characterization/support/run_kreditor_order_page.php
//
// Child-process runner for the purchase order pages: the order list
// (kreditor/ordreliste.php) and the order itself (kreditor/ordre.php).
//
// Both pages start a session and include connect/online at top level, so they
// run as a child process with a fabricated session that CharacterizationEnv
// seeded in `online`. The query string and the body are parsed with
// parse_str(), the way PHP builds $_GET and $_POST. The order form posts to
// action='ordre.php', so a save carries no query string.
//
// ordre.php ends with exit() after some redirects, so the result is printed
// from a shutdown function.
//
// argv: 1 = page, ordre.php or ordreliste.php
//       2 = query string, e.g. "returside=ordreliste.php"
//       3 = form-urlencoded body to POST, or "" for a GET
//       4 = session id (must match the seeded online row)
//
// Prints one JSON line on stdout:
//   status   - the HTTP status the page set
//   html     - what the page printed
//   warnings - every PHP warning and notice the request raised, as
//              "path:line: message" (path from the repository root).
//              @-suppressed calls (the page's @session_start()) are left out,
//              as PHP leaves them out of the log. With SALDI_CHAR_DEBUG set
//              they go to stderr as well.
//   deprecations - the deprecations, in the same form. They are kept apart
//              because the pages raise some on every request (null passed to
//              strpos()/str_replace()), which a test of warnings is not about.
//
// History:
// 20261105 CL/LH Created for NewCreditorOrderWarningsTest, after run_lagerflyt_page.php.

if ($argc < 5 || !in_array($argv[1], ['ordre.php', 'ordreliste.php'], true)) {
    fwrite(STDERR, "usage: php run_kreditor_order_page.php <ordre.php|ordreliste.php> <query string> <body or \"\"> <session_id>\n");
    exit(2);
}
$chartestPage = $argv[1];
$chartestQuery = $argv[2];
$chartestBody = $argv[3];
$chartestSessionId = $argv[4];

error_reporting(E_ALL);
$chartestRoot = dirname(__DIR__, 3) . '/';
$chartestWarnings = [];
$chartestDeprecations = [];
set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use (&$chartestWarnings, &$chartestDeprecations, $chartestRoot): bool {
    // @-suppressed calls arrive with error_reporting() masked.
    if (error_reporting() & $errno) {
        $file = str_starts_with($errfile, $chartestRoot) ? substr($errfile, strlen($chartestRoot)) : $errfile;
        if ($errno & (E_DEPRECATED | E_USER_DEPRECATED)) {
            $chartestDeprecations[] = "$file:$errline: $errstr";
        } else {
            $chartestWarnings[] = "$file:$errline: $errstr";
        }
    }
    return true; // reported in the JSON line, not in the page's HTML
});

$_SERVER['REQUEST_METHOD'] = $chartestBody === '' ? 'GET' : 'POST';
$_SERVER['REQUEST_URI'] = "/saldi/kreditor/$chartestPage" . ($chartestQuery === '' ? '' : '?' . $chartestQuery);
$_SERVER['PHP_SELF'] = "/saldi/kreditor/$chartestPage";
$_SERVER['SCRIPT_NAME'] = "/saldi/kreditor/$chartestPage";
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . "/kreditor/$chartestPage";
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

chdir(dirname(__DIR__, 3) . '/kreditor'); // the pages use ../includes/... and orderIncludes/... relative includes

ob_start();
$chartestLevel = ob_get_level();
$chartestReport = static function () use ($chartestLevel, &$chartestWarnings, &$chartestDeprecations): void {
    while (ob_get_level() > $chartestLevel) {
        ob_end_flush();
    }
    $html = (string) ob_get_clean();
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $html . "\n" . implode("\n", array_merge($chartestWarnings, $chartestDeprecations)) . "\n");
    }
    fwrite(STDOUT, "\n" . json_encode(['status' => http_response_code(), 'html' => $html, 'warnings' => $chartestWarnings, 'deprecations' => $chartestDeprecations], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
};
// Registered from a shutdown function, so it runs after the ones the page registers (includes/errorReport.php).
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
include __DIR__ . '/../../../kreditor/' . $chartestPage;
