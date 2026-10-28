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
// `online` table.
//
// argv: 1 = session id (must match the seeded online row)
//
// Prints CHARTEST_PAGE_DONE on stdout if the include ran to completion; the
// test asserts on database state, not on this marker.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

if ($argc < 2) {
    fwrite(STDERR, "usage: php run_ny_rykker.php <session_id>\n");
    exit(2);
}
$sessionId = $argv[1];

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy

$_SERVER['REQUEST_URI'] = '/saldi/debitor/ny_rykker.php';
$_SERVER['PHP_SELF'] = '/saldi/debitor/ny_rykker.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

// The automatic run: walk every debtor with unsettled open posts.
$_GET['kontoliste'] = 'alle';
$_GET['kontoantal'] = '1';

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId);

chdir(dirname(__DIR__, 3) . '/debitor'); // the page uses ../includes/... relative includes

ob_start();
include 'ny_rykker.php';
$pageOutput = ob_get_clean();
if (getenv('SALDI_CHAR_DEBUG')) {
    fwrite(STDERR, $pageOutput . "\n");
}

fwrite(STDOUT, "CHARTEST_PAGE_DONE\n");
