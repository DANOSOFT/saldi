<?php
// tests/characterization/support/run_kassekladde_page.php
//
// Child-process runner for the cash journal page, finans/kassekladde.php.
//
// The page starts a session and includes connect/online/std_func at top
// level, so it runs as a child process with a fabricated session that
// CharacterizationEnv seeded in `online`, like run_hentordrer_page.php. The
// query string is parsed with parse_str(), the same way PHP builds $_GET:
// "kladde_id=K" is the request that opens journal K.
//
// argv: 1 = query string, e.g. "kladde_id=12"
//       2 = session id (must match the seeded online row)
//       3 = file the page's HTML is written to
//
// The HTML goes to a file, not stdout: the page's own JavaScript contains the
// text "Fatal error", which CharacterizationTestCase::runChild() reads as a
// crash. Prints CHARTEST_PAGE_DONE on stdout when the page ran to its end; a
// fatal error is repeated on stderr, so runChild() fails the test with it.
//
// History:
// 20261105 CL/LH Created for KassekladdeHentButtonTest.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_kassekladde_page.php <query string> <session_id> <html file>\n");
    exit(2);
}
$chartestQuery = $argv[1];
$chartestSessionId = $argv[2];
$chartestHtmlFile = $argv[3];

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy; keep child output usable

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/finans/kassekladde.php?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/finans/kassekladde.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/finans/kassekladde.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
$_REQUEST = $_GET;

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId); // the page's @session_start() picks this id up -> online row matches

chdir(dirname(__DIR__, 3) . '/finans'); // the page uses ../includes/... relative includes

// The page buffers its own output (ob_start()) and may end in exit, so the HTML is collected from a shutdown function.
register_shutdown_function(static function () use ($chartestHtmlFile): void {
    $output = '';
    while (ob_get_level() > 0) {
        $output = ob_get_clean() . $output;
    }
    file_put_contents($chartestHtmlFile, $output);
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        fwrite(STDERR, "Fatal error: {$error['message']} in {$error['file']}:{$error['line']}\n");
        return;
    }
    fwrite(STDOUT, "CHARTEST_PAGE_DONE\n");
});

ob_start();
include __DIR__ . '/../../../finans/kassekladde.php';
