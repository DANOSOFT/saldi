<?php
// tests/characterization/support/run_debitor_order_page.php
//
// Child-process runner for the sales order page, debitor/ordre.php, opened the
// way a browser opens it: a GET with the order's id, or a POST of the order
// form, with a fabricated session that CharacterizationEnv seeded in `online`.
// The query string and the body are parsed with parse_str(), the way PHP builds
// $_GET and $_POST. Drawing the order stores its totals in ordrer (sum,
// kostpris, moms), which is what the tests read afterwards.
//
// ordre.php ends with exit() after some redirects, so the result is printed
// from a shutdown function.
//
// argv: 1 = query string, e.g. "id=12"
//       2 = session id (must match the seeded online row)
//       3 = form-urlencoded body to POST; '' or none makes the request a GET
//       4 = the page, from the repository root (default debitor/ordre.php), e.g.
//           finans/ordre.php, the older copy of the sales order page, or
//           debitor/ret_genfakt.php, which edits the lines of subscription orders
//           (query "ordreliste=12" to open it)
//
// Prints one JSON line on stdout:
//   status   - the HTTP status the page set
//   html     - what the page printed
//   warnings - every PHP warning and notice the request raised, as
//              "path:line: message" (path from the repository root), as
//              run_kreditor_order_page.php reports them; deprecations apart.
//
// History:
// 20261105 CL/LH Created for RestOrderLineTotalsTest, after run_kreditor_order_page.php.
// 20261105 CL/LH A third argument posts a form body, and the request's warnings are reported, for OrderLinePositionsRaceTest.
//                A fourth argument opens finans/ordre.php, the older copy of the page, instead.
//                Or debitor/ret_genfakt.php, the subscription orders' line editor.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_debitor_order_page.php <query string> <session_id> [body] [page]\n");
    exit(2);
}
$chartestQuery = $argv[1];
$chartestSessionId = $argv[2];
$chartestBody = $argv[3] ?? '';
$chartestPage = $argv[4] ?? 'debitor/ordre.php';
if (!in_array($chartestPage, ['debitor/ordre.php', 'finans/ordre.php', 'debitor/ret_genfakt.php'], true)) {
    fwrite(STDERR, "unknown page $chartestPage\n");
    exit(2);
}

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
$_SERVER['REQUEST_URI'] = "/saldi/$chartestPage?" . $chartestQuery;
$_SERVER['PHP_SELF'] = "/saldi/$chartestPage";
$_SERVER['SCRIPT_NAME'] = "/saldi/$chartestPage";
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . "/$chartestPage";
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

chdir(dirname(__DIR__, 3) . '/' . dirname($chartestPage)); // the page uses ../includes/... and orderIncludes/... relative includes

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
include __DIR__ . '/../../../' . $chartestPage;
