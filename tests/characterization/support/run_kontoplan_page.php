<?php
// tests/characterization/support/run_kontoplan_page.php
//
// Child-process runner for the chart of accounts, systemdata/kontoplan.php.
//
// The page starts a session and includes connect/online/std_func at top
// level, so it runs as a child process with a fabricated session that
// CharacterizationEnv seeded in `online`. The query string is parsed with
// parse_str(), the same way PHP builds $_GET, so the grid's own search
// parameters (search[kontoplan][kontonr]=...) arrive as they would from a
// browser.
//
// argv: 1 = query string, e.g. "search[kontoplan][kontonr]=97000"
//       2 = session id (must match the seeded online row)
//
// Prints one JSON line on stdout:
//   saldo    - per account id in the grid, the Saldo cell: its text and its
//              "DKK ..." title
//   warnings - PHP warnings/notices raised in systemdata/kontoplan.php itself
//              (the legacy includes' own noise is ignored)
//
// History:
// 20261105 CL/LH Created for KontoplanZeroRateTest.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_kontoplan_page.php <query string> <session_id>\n");
    exit(2);
}
$chartestQuery = $argv[1];
$chartestSessionId = $argv[2];

error_reporting(E_ALL);
$chartestWarnings = [];
set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use (&$chartestWarnings): bool {
    // @-suppressed calls (the page's @session_start()) arrive with error_reporting() masked.
    if ((error_reporting() & $errno) && str_ends_with($errfile, '/systemdata/kontoplan.php')) {
        $chartestWarnings[] = "line $errline: $errstr";
    }
    return true; // keep the legacy includes' warnings off stdout
});

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/systemdata/kontoplan.php?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/systemdata/kontoplan.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/systemdata/kontoplan.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
$_REQUEST = $_GET;

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId);

chdir(dirname(__DIR__, 3) . '/systemdata'); // the page uses ../includes/... relative includes

ob_start();
include __DIR__ . '/../../../systemdata/kontoplan.php';
$chartestOutput = ob_get_clean();
if (getenv('SALDI_CHAR_DEBUG')) {
    fwrite(STDERR, $chartestOutput . "\n");
}

// One grid row per account: the Kontonr cell links to kontokort.php?id=N, the Saldo cell carries the DKK title.
$chartestSaldo = [];
foreach (explode('<tr', $chartestOutput) as $chartestRow) {
    if (preg_match("/kontokort\\.php\\?id=(\\d+)/", $chartestRow, $chartestId)
        && preg_match("/<td align='right' title='([^']*)'>([^<]*)<\\/td>/", $chartestRow, $chartestCell)) {
        $chartestSaldo[$chartestId[1]] = ['text' => $chartestCell[2], 'title' => $chartestCell[1]];
    }
}
fwrite(STDOUT, json_encode(['saldo' => $chartestSaldo, 'warnings' => $chartestWarnings], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
