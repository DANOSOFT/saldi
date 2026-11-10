<?php
// tests/characterization/support/run_debitor_order_page.php
//
// Child-process runner for the sales order page, debitor/ordre.php, opened the
// way a browser opens it: a GET with the order's id, with a fabricated session
// that CharacterizationEnv seeded in `online`. The query string is parsed with
// parse_str(), the way PHP builds $_GET. Drawing the order stores its totals in
// ordrer (sum, kostpris, moms), which is what the tests read afterwards.
//
// ordre.php ends with exit() after some redirects, so the result is printed
// from a shutdown function.
//
// argv: 1 = query string, e.g. "id=12"
//       2 = session id (must match the seeded online row)
//
// Prints one JSON line on stdout:
//   status   - the HTTP status the page set
//   html     - what the page printed
//
// History:
// 20261105 CL/LH Created for RestOrderLineTotalsTest, after run_kreditor_order_page.php.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_debitor_order_page.php <query string> <session_id>\n");
    exit(2);
}
$chartestQuery = $argv[1];
$chartestSessionId = $argv[2];

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/debitor/ordre.php?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/debitor/ordre.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/debitor/ordre.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/debitor/ordre.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
$_POST = [];
$_REQUEST = $_GET;

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId);

chdir(dirname(__DIR__, 3) . '/debitor'); // the page uses ../includes/... and orderIncludes/... relative includes

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
// Registered from a shutdown function, so it runs after the ones the page registers (includes/errorReport.php).
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
include __DIR__ . '/../../../debitor/ordre.php';
