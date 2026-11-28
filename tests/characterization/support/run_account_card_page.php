<?php
// tests/characterization/support/run_account_card_page.php
//
// Child-process runner for the debtor and creditor cards, drawn and saved the
// way a browser does: debitor/debitorkort.php, sager/kunder.php (its customer
// card saves through debitor/debkort_save.php), kreditor/kreditorkort.php and
// includes/kreditorkort.php.
//
// Each page starts a session and includes connect/online at top level, and a
// save may end in exit(), so it runs as a child process with a fabricated
// session that CharacterizationEnv seeded in `online`. The query string and
// the body are parsed with parse_str(), the way PHP builds $_GET and $_POST:
// it keeps the first max_input_vars fields and drops the rest, so a test that
// starts this runner with -d max_input_vars=1000 gets a long form cut where a
// browser's post of it is cut.
// The session is stored in the system temp directory, so what one request puts
// in it is there for the next, as in a browser.
//
// argv: 1 = page, relative to the repository root, e.g. "debitor/debitorkort.php"
//       2 = query string, e.g. "id=12"
//       3 = form-urlencoded body to POST, or "" for a GET
//       4 = session id (must match the seeded online row)
//
// Prints one JSON line on stdout, also when the page ends in exit():
//   html     - what the page printed
//   alerts   - the texts of the page's alert() calls, in order
//   warnings - every PHP warning and notice the request raised, as
//              "path:line: message" (path from the repository root), without
//              deprecations and @-suppressed calls.
//   posted   - the names in $_POST, which PHP kept of the body.
//
// History:
// 20261105 CL/LH Created for AccountCardStaleSaveTest, after run_account_delete_page.php and run_kreditor_order_page.php.
//                Reports the fields PHP kept of the post, for the forms longer than max_input_vars.

$chartestPages = ['debitor/debitorkort.php', 'sager/kunder.php', 'kreditor/kreditorkort.php', 'includes/kreditorkort.php'];
if ($argc < 5 || !in_array($argv[1], $chartestPages, true)) {
    fwrite(STDERR, "usage: php run_account_card_page.php <" . implode('|', $chartestPages) . "> <query string> <body or \"\"> <session_id>\n");
    exit(2);
}
$chartestPage = $argv[1];
$chartestQuery = $argv[2];
$chartestBody = $argv[3];
$chartestSessionId = $argv[4];

error_reporting(E_ALL);
$chartestRoot = dirname(__DIR__, 3) . '/';
$chartestWarnings = [];
set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use (&$chartestWarnings, $chartestRoot): bool {
    // @-suppressed calls (the pages' @session_start()) arrive with error_reporting() masked.
    if ((error_reporting() & $errno) && !($errno & (E_DEPRECATED | E_USER_DEPRECATED))) {
        $file = str_starts_with($errfile, $chartestRoot) ? substr($errfile, strlen($chartestRoot)) : $errfile;
        $chartestWarnings[] = "$file:$errline: $errstr";
    }
    return true; // reported in the JSON line, not in the page's HTML
});

$_SERVER['REQUEST_METHOD'] = $chartestBody === '' ? 'GET' : 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/' . $chartestPage . ($chartestQuery === '' ? '' : '?' . $chartestQuery);
$_SERVER['PHP_SELF'] = '/saldi/' . $chartestPage;
$_SERVER['SCRIPT_NAME'] = '/saldi/' . $chartestPage;
$_SERVER['SCRIPT_FILENAME'] = $chartestRoot . $chartestPage;
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
$_POST = [];
if ($chartestBody !== '') {
    parse_str($chartestBody, $_POST);
}
$_REQUEST = $_POST + $_GET;

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId);

chdir($chartestRoot . dirname($chartestPage)); // the pages use ../includes/... relative includes

ob_start();
$chartestLevel = ob_get_level();
$chartestPosted = array_keys($_POST); // before the page sets or unsets anything in it
$chartestReport = static function () use ($chartestLevel, &$chartestWarnings, $chartestPosted): void {
    while (ob_get_level() > $chartestLevel) {
        ob_end_flush();
    }
    $html = (string) ob_get_clean();
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $html . "\n" . implode("\n", $chartestWarnings) . "\n");
    }
    $alerts = [];
    // alert("...") as jsString() writes it, and the legacy alert('...').
    preg_match_all('/alert\((?:("(?:[^"\\\\]|\\\\.)*")|\'((?:[^\'\\\\]|\\\\.)*)\')\)/', $html, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $alerts[] = isset($match[2]) ? stripslashes($match[2]) : json_decode($match[1]);
    }
    fwrite(STDOUT, "\n" . json_encode(['html' => $html, 'alerts' => $alerts, 'warnings' => $chartestWarnings, 'posted' => $chartestPosted], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
};
// Registered from a shutdown function, so it runs after the ones the page registers (includes/errorReport.php).
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
include $chartestRoot . $chartestPage;
