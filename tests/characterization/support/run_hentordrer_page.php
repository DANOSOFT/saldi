<?php
// tests/characterization/support/run_hentordrer_page.php
//
// Child-process runner for Hent ordrer, finans/hentordrer.php.
//
// The page starts a session and includes connect/online/std_func at top
// level, and its order list ends in exit(), so it runs as a child process
// with a fabricated session that CharacterizationEnv seeded in `online`. The
// query string is parsed with parse_str(), the same way PHP builds $_GET:
// "kladde_id=K&ordre_id=O" is the request the "Flyt til kladde" link of one
// invoice sends.
//
// argv: 1 = query string, e.g. "kladde_id=12&ordre_id=34"
//       2 = session id (must match the seeded online row)
//
// Prints CHARTEST_PAGE_DONE on stdout when the page ran to its end; the tests
// assert on database state. A fatal error is repeated on stderr, so
// CharacterizationTestCase::runChild() fails the test with it.
//
// History:
// 20261105 CL/LH Created for HentOrdrerFlytTest.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_hentordrer_page.php <query string> <session_id>\n");
    exit(2);
}
$chartestQuery = $argv[1];
$chartestSessionId = $argv[2];

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy; keep child output usable

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/finans/hentordrer.php?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/finans/hentordrer.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/finans/hentordrer.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
$_REQUEST = $_GET;

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId); // the page's @session_start() picks this id up -> online row matches

chdir(dirname(__DIR__, 3) . '/finans'); // the page uses ../includes/... relative includes

// hentordrer() ends the request with exit, so the result is reported from a shutdown function.
register_shutdown_function(static function (): void {
    $output = ob_get_level() > 0 ? ob_get_clean() : '';
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $output . "\n");
    }
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        fwrite(STDERR, "Fatal error: {$error['message']} in {$error['file']}:{$error['line']}\n");
        return;
    }
    fwrite(STDOUT, "CHARTEST_PAGE_DONE\n");
});

ob_start();
include __DIR__ . '/../../../finans/hentordrer.php';
