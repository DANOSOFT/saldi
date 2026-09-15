<?php
// tests/characterization/support/run_bogfor_page.php
//
// Child-process runner for the kassekladde posting engine (SD-601).
//
// finans/bogfor.php is a legacy PAGE SCRIPT: it starts a session, includes
// connect/online/std_func at top level, prints HTML, and exit()s on several
// branches. It cannot be included inside the PHPUnit process. This runner is
// executed as a separate `php` child by KassekladdePostingCharacterizationTest
// and drives the page exactly the way a browser POST does: it fabricates the
// session (seeded in the `online` table by CharacterizationEnv), sets
// $_GET/$_POST the way the kassekladde form does, and includes the page. The
// page's own dispatch then runs transaktion('begin'); bogfor(...);
// transaktion('commit') - the same code path production takes.
//
// argv: 1 = mode ('simuler' posts to the simulering table, 'bogfor' posts
//            for real to transaktioner)
//       2 = kladde_id (kladdeliste.id, fixture created by the test)
//       3 = session id (must match the seeded online row)
//
// Prints CHARTEST_PAGE_DONE on stdout if the include ran to completion.
// (Some page branches exit() before that - the test asserts on DB state,
// not on this marker.)
//
// History:
// 20260723 CL/LH SD-601: created.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_bogfor_page.php <simuler|bogfor> <kladde_id> <session_id>\n");
    exit(2);
}
$mode = $argv[1];
$kladdeId = (int)$argv[2];
$sessionId = $argv[3];
if ($mode !== 'simuler' && $mode !== 'bogfor') {
    fwrite(STDERR, "mode must be simuler or bogfor\n");
    exit(2);
}

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy; keep child output usable

$_SERVER['REQUEST_URI'] = '/saldi/finans/bogfor.php';
$_SERVER['PHP_SELF'] = '/saldi/finans/bogfor.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

$_GET['kladde_id'] = (string)$kladdeId;
$_GET['funktion'] = $mode;
$_POST['kladde_id'] = (string)$kladdeId;
$_POST[$mode] = 'on';
$_POST['kladdenote'] = 'chartest';

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId); // the page's @session_start() picks this id up -> online row matches

chdir(dirname(__DIR__, 3) . '/finans'); // the page uses ../includes/... relative includes

ob_start();
include 'bogfor.php';
$pageOutput = ob_get_clean();
if (getenv('SALDI_CHAR_DEBUG')) {
    fwrite(STDERR, $pageOutput . "\n");
}

fwrite(STDOUT, "CHARTEST_PAGE_DONE\n");
