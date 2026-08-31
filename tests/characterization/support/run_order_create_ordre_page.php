<?php
// tests/characterization/support/run_order_create_ordre_page.php
//
// Child-process runner for the plain "new order" UI path (SD-600, scope
// widened 2026-08-07 per Lui): debitor/ordre.php's own direct
// INSERT INTO ordrer (~line 782-810), reached by
// GET debitor/ordre.php?konto_id=<id> with no `id` param. This is the main
// "New order" flow for an existing debtor - distinct from
// includes/ordrefunc.php::opret_ordre(), which only fires from a case's
// "new quote" button (see ClassicOrderCreationCharacterizationTest).
//
// debitor/ordre.php is a full session-driven UI page (like finans/bogfor.php
// in the SD-601 suite), not a bare function library, so it needs the same
// fabricated session + seeded `online` row as run_bogfor_page.php rather
// than the includes/ordrefunc.php-style bootstrap the other order-creation
// runners use.
//
// argv: 1 = konto_id (an existing debtor)
//       2 = session id (must match the seeded online row)
//
// Prints CHARTEST_PAGE_DONE on stdout if the include ran to completion. The
// INSERT itself is a plain, non-transactional db_modify() call (no
// transaktion('begin') wraps it), so it persists even if something later in
// the page's HTML rendering errors out - the test asserts on DB state, not
// on this marker.
//
// History:
// 20260807 CL/SZ SD-600: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_order_create_ordre_page.php <konto_id> <session_id>\n");
    exit(2);
}
$kontoId = (int)$argv[1];
$sessionId = $argv[2];

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy; keep child output usable

$_SERVER['REQUEST_URI'] = '/saldi/debitor/ordre.php';
$_SERVER['PHP_SELF'] = '/saldi/debitor/ordre.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

$_GET['konto_id'] = (string)$kontoId;

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId); // the page's own @session_start() picks this id up -> online row matches

chdir(dirname(__DIR__, 3) . '/debitor'); // the page uses ../includes/... relative includes

ob_start();
include 'ordre.php';
$pageOutput = ob_get_clean();
if (getenv('SALDI_CHAR_DEBUG')) {
    fwrite(STDERR, $pageOutput . "\n");
}

fwrite(STDOUT, "CHARTEST_PAGE_DONE\n");
