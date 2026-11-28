<?php
// tests/characterization/support/run_kassekladde_save_page.php
//
// Child-process runner for a save (Gem) of the cash journal page, finans/kassekladde.php.
//
// Like run_kassekladde_page.php, but the request is a POST: the body is
// parsed with parse_str(), the way PHP builds $_POST from the journal form
// ("save=Gem&kladde_id=K&antal_ny=N&id[1]=...&bila1=-..."). A line whose
// Bilag field is "-" is deleted by the save, which is how a user removes a
// line from an open journal.
//
// argv: 1 = form-urlencoded body to POST
//       2 = session id (must match the seeded online row)
//       3 = file the page's HTML is written to
//
// The HTML goes to a file, not stdout: the page's own JavaScript contains the
// text "Fatal error", which CharacterizationTestCase::runChild() reads as a
// crash. Prints CHARTEST_PAGE_DONE on stdout when the page ran to its end; a
// fatal error is repeated on stderr, so runChild() fails the test with it.
//
// History:
// 20261117 CL/ASR Created for HentOrdrerFlytTest (deleting the journal lines of a fetched invoice).

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_kassekladde_save_page.php <body> <session_id> <html file>\n");
    exit(2);
}
$chartestBody = $argv[1];
$chartestSessionId = $argv[2];
$chartestHtmlFile = $argv[3];

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy; keep child output usable

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/finans/kassekladde.php';
$_SERVER['PHP_SELF'] = '/saldi/finans/kassekladde.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/finans/kassekladde.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_GET = [];
parse_str($chartestBody, $_POST);
$_REQUEST = $_POST;

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
