<?php
// tests/characterization/support/run_sync_stamkort_page.php
//
// Child-process runner for the arrows on the order page that copy name and
// address between an order and its customer card (debitor/sync_stamkort.php).
//
// The page starts a session and includes connect/online/std_func at top
// level, so it runs as a child process with a fabricated session that
// CharacterizationEnv seeded in `online`. The query string is parsed with
// parse_str(), the same way PHP builds $_GET, so `ordre_id[]=7` arrives as an
// array exactly as it would from a browser.
//
// argv: 1 = query string, e.g. "ordre_id=12&konto_id=34&retning=op"
//       2 = session id (must match the seeded online row)
//
// Prints one JSON line on stdout:
//   redirect_id - the id in the page's "URL=ordre.php?id=N" redirect, or null
//   warnings    - PHP warnings/notices raised in sync_stamkort.php itself
//                 (the legacy includes' own noise is ignored)
//
// History:
// 20261027 CL/LH SST-887 Created for SyncStamkortTest.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_sync_stamkort_page.php <query string> <session_id>\n");
    exit(2);
}
$chartestQuery = $argv[1];
$chartestSessionId = $argv[2];

error_reporting(E_ALL);
$chartestWarnings = [];
set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use (&$chartestWarnings): bool {
    // @-suppressed calls (the page's @session_start()) arrive with error_reporting() masked.
    if ((error_reporting() & $errno) && str_ends_with($errfile, '/debitor/sync_stamkort.php')) {
        $chartestWarnings[] = "line $errline: $errstr";
    }
    return true; // keep the legacy includes' warnings off stdout
});

$_SERVER['REQUEST_URI'] = '/saldi/debitor/sync_stamkort.php?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/debitor/sync_stamkort.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId);

chdir(dirname(__DIR__, 3) . '/debitor'); // the page uses ../includes/... relative includes

ob_start();
include 'sync_stamkort.php';
$chartestOutput = ob_get_clean();
if (getenv('SALDI_CHAR_DEBUG')) {
    fwrite(STDERR, $chartestOutput . "\n");
}

$chartestRedirectId = preg_match('/URL=ordre\.php\?id=(\d+)/', $chartestOutput, $chartestMatch) ? (int)$chartestMatch[1] : null;
fwrite(STDOUT, json_encode(['redirect_id' => $chartestRedirectId, 'warnings' => $chartestWarnings]) . "\n");
