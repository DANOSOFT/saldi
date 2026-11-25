<?php
// tests/characterization/support/run_ansatte_page.php
//
// Child-process runner for the contact person form on the customer card
// (debitor/ansatte.php), posted the way the browser posts it.
//
// The page starts a session and includes connect/online/std_func at top
// level, so it runs as a child process with a fabricated session that
// CharacterizationEnv seeded in `online`. The form fields are parsed with
// parse_str(), the same way PHP builds $_POST.
//
// argv: 1 = form fields as a query string, e.g. "id=12&konto_id=34&navn=Anna&submit=Gem"
//       2 = session id (must match the seeded online row)
//
// Prints one JSON line on stdout:
//   warnings - PHP warnings/notices raised in ansatte.php itself
//              (the legacy includes' own noise is ignored)
//
// History:
// 20261122 CL/SZ MB-138 Created for DebitorContactPositionTest.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_ansatte_page.php <form fields> <session_id>\n");
    exit(2);
}
$chartestFields = $argv[1];
$chartestSessionId = $argv[2];

error_reporting(E_ALL);
$chartestWarnings = [];
set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use (&$chartestWarnings): bool {
    // @-suppressed calls (the page's @session_start()) arrive with error_reporting() masked.
    if ((error_reporting() & $errno) && str_ends_with($errfile, '/debitor/ansatte.php')) {
        $chartestWarnings[] = "line $errline: $errstr";
    }
    return true; // keep the legacy includes' warnings off stdout
});

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/debitor/ansatte.php';
$_SERVER['PHP_SELF'] = '/saldi/debitor/ansatte.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestFields, $_POST);

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId);

chdir(dirname(__DIR__, 3) . '/debitor'); // the page uses ../includes/... relative includes

// The page exits after its redirect after a save, so the result is printed on shutdown.
register_shutdown_function(static function () use (&$chartestWarnings): void {
    $chartestOutput = (string)ob_get_clean();
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $chartestOutput . "\n");
    }
    fwrite(STDOUT, json_encode(['warnings' => $chartestWarnings]) . "\n");
});

ob_start();
include 'ansatte.php';
