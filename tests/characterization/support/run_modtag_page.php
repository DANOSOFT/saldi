<?php
// tests/characterization/support/run_modtag_page.php
//
// Child-process runner for goods receipt on a purchase order
// (kreditor/modtag.php).
//
// Receiving is NOT the same code as delivering. A sale goes through
// includes/ordrefunc.php levering(), which subtracts from stock; a purchase
// receipt goes through this separate page, which adds
// (`$vare_beholdning = $r['beholdning'] + $leveres[$x]`, modtag.php:162) and
// writes batch_kob rather than batch_salg. Driving a KO order through
// levering() would subtract - it is not a substitute.
//
// Like the other legacy pages, modtag.php starts a session and includes
// connect/online/std_func at top level, so it runs as a child process with a
// fabricated session that CharacterizationEnv seeded in `online`.
//
// The caller must already have set `ordrelinjer.leveres` to the arriving
// quantities - that is the state kreditor/ordre.php leaves behind before it
// hands control here, and the test writes it directly over pg for the same
// reason every other fixture does.
//
// argv: 1 = ordre_id (art='KO')
//       2 = session id (must match the seeded online row)
//
// Prints CHARTEST_PAGE_DONE on stdout; the test asserts on database state.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_modtag_page.php <ordre_id> <session_id>\n");
    exit(2);
}
$ordreId = (int)$argv[1];
$sessionId = $argv[2];

error_reporting(E_ERROR | E_PARSE);

$_SERVER['REQUEST_URI'] = '/saldi/kreditor/modtag.php';
$_SERVER['PHP_SELF'] = '/saldi/kreditor/modtag.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_GET['id'] = (string)$ordreId;

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId);

chdir(dirname(__DIR__, 3) . '/kreditor'); // the page uses ../includes/... relative includes

ob_start();
include 'modtag.php';
$pageOutput = ob_get_clean();
if (getenv('SALDI_CHAR_DEBUG')) {
    fwrite(STDERR, $pageOutput . "\n");
}

fwrite(STDOUT, "CHARTEST_PAGE_DONE\n");
