<?php
// tests/characterization/support/run_ret_genfakt_page.php
//
// Child-process runner for «Ret abonnementsordrer» (debitor/ret_genfakt.php),
// with the form posted the way the browser posts it.
//
// The page starts a session and includes connect/online/std_func at top
// level, so it runs as a child process with a fabricated session that
// CharacterizationEnv seeded in `online`. The form fields are parsed with
// parse_str(), the same way PHP builds $_POST, so `ordreliste[]=7` arrives
// as an array exactly as it would from a browser.
//
// argv: 1 = form fields as a query string
//       2 = session id (must match the seeded online row)
//
// Prints one JSON line on stdout when the page finishes. A fatal error (the
// white screen) exits 255, which CharacterizationTestCase::runChild() fails on.
//
// History:
// 20261122 CL/SZ SST-879 Created for RetGenfaktSyncTest.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_ret_genfakt_page.php <form fields> <session_id>\n");
    exit(2);
}
$chartestFields = $argv[1];
$chartestSessionId = $argv[2];

error_reporting(E_ALL);
set_error_handler(static function (): bool {
    return true; // the page's and the legacy includes' notices stay off stdout
});

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/debitor/ret_genfakt.php';
$_SERVER['PHP_SELF'] = '/saldi/debitor/ret_genfakt.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestFields, $_POST);

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId);

chdir(dirname(__DIR__, 3) . '/debitor'); // the page uses ../includes/... relative includes

ob_start();
include 'ret_genfakt.php';
$chartestOutput = ob_get_clean();
if (getenv('SALDI_CHAR_DEBUG')) {
    fwrite(STDERR, $chartestOutput . "\n");
}
fwrite(STDOUT, json_encode(['done' => true]) . "\n");
