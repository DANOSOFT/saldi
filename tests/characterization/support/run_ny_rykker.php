<?php
// tests/characterization/support/run_ny_rykker.php
//
// Child-process runner for the dunning generator (debitor/ny_rykker.php).
//
// Like finans/bogfor.php, ny_rykker.php is a legacy page script: it starts a
// session, includes connect/online/std_func at top level, prints HTML and
// exit()s on several branches, so it cannot be included inside the PHPUnit
// process. This runner drives it the way the "Dan rykkere" button does -
// kontoliste=alle, which is the automatic run that walks every unsettled open
// post - with a fabricated session that CharacterizationEnv has seeded in the
// `online` table. Given reminder ids it drives "Ny rykker" instead, with the
// parameters debitor/rapport.php's rapport_reminder_action() sends.
// Both carry thisDb, the ledger the report was rendered in; the page refuses
// to run for any other ledger.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = thisDb, the report's ledger (the test database for a normal run)
//       3 = optional ';'-separated reminder ids for "Ny rykker"
//
// The page's output goes to stdout, followed by CHARTEST_PAGE_DONE if the
// include ran to completion.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.
// 20261105 CL/LH Drives "Ny rykker" (rykker_id) as well, and prints the page's output.
// 20261126 CL/ASR Stripe port (#1134): passes thisDb (argv 2), which ny_rykker.php checks against the session's ledger.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_ny_rykker.php <session_id> <thisDb> [<reminder ids>]\n");
    exit(2);
}
$sessionId = $argv[1];
$_GET['thisDb'] = $argv[2];

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy

$_SERVER['REQUEST_URI'] = '/saldi/debitor/ny_rykker.php';
$_SERVER['PHP_SELF'] = '/saldi/debitor/ny_rykker.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

if ($argc > 3) {
    // "Ny rykker" on ticked reminders: no kontoliste, as from rapport_reminder_action().
    $_GET['rykker_id'] = $argv[3];
    $_GET['kontoantal'] = (string)count(explode(';', $argv[3]));
} else {
    // The automatic run: walk every debtor with unsettled open posts.
    $_GET['kontoliste'] = 'alle';
    $_GET['kontoantal'] = '1';
}

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId);

chdir(dirname(__DIR__, 3) . '/debitor'); // the page uses ../includes/... relative includes

ob_start();
include 'ny_rykker.php';
$pageOutput = ob_get_clean();
fwrite(STDOUT, $pageOutput . "\n");

fwrite(STDOUT, "CHARTEST_PAGE_DONE\n");
