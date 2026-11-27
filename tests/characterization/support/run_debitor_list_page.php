<?php
// tests/characterization/support/run_debitor_list_page.php
//
// Child-process runner for the customer list pages: debitor/debitor.php, the
// page behind Debitor (its ?valg=kommission shows the Kommission view), and
// debitor/debitor_kommission.php, the Kommission view itself, which the list's
// top line links to directly.
//
// Both are legacy page scripts: they start a session, include
// connect/online/std_func at top level and print as they go, so they cannot be
// included inside the PHPUnit process. This runner fills $_GET from the query
// string the way the browser's GET does and includes the page with the
// fabricated session CharacterizationEnv seeded in the `online` table. The
// page's HTML goes to stdout. A fatal error ends the process with exit code
// 255 and its message on stderr (display_errors is off, log_errors on), which
// CharacterizationTestCase::runChild() reports as a crash.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = the page, from the repository root: debitor/debitor.php or
//           debitor/debitor_kommission.php
//       3 = query string, e.g. "valg=kommission"
//
// History:
// 20261121 CL/ASR Created for CommissionViewEntryPointsTest.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_debitor_list_page.php <session_id> <page> <query string>\n");
    exit(2);
}
$chartestSessionId = $argv[1];
$chartestPage = $argv[2];
$chartestQuery = $argv[3];
if (!in_array($chartestPage, ['debitor/debitor.php', 'debitor/debitor_kommission.php'], true)) {
    fwrite(STDERR, "unknown page $chartestPage\n");
    exit(2);
}

error_reporting(E_ALL);
set_error_handler(static function (): bool {
    return true; // keep the legacy page's warnings off stdout; a fatal error does not come here
});

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = "/saldi/$chartestPage" . ($chartestQuery === '' ? '' : '?' . $chartestQuery);
$_SERVER['PHP_SELF'] = "/saldi/$chartestPage";
$_SERVER['SCRIPT_NAME'] = "/saldi/$chartestPage";
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . "/$chartestPage";
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
$_POST = [];
$_REQUEST = $_GET;

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId); // the page's @session_start() picks this id up -> online row matches

chdir(dirname(__DIR__, 3) . '/debitor'); // the pages use ../includes/... relative includes

include __DIR__ . '/../../../' . $chartestPage;
