<?php
// tests/characterization/support/run_luk_page.php
//
// Child-process runner for includes/luk.php, the page a popup moves on to when it is done (debitor/ny_rykker.php
// and others): it closes the window, and when the browser does not let it, moves on to the returside.
//
// luk.php is a legacy page script: it starts a session, includes connect/online/std_func and prints HTML, so it
// runs here with a fabricated session that CharacterizationEnv has seeded in the `online` table.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = JSON object of GET parameters
//
// History:
// 20261126 ASR Created for review M1: the fallback waits while a Saldi message is open.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_luk_page.php <session_id> <get json>\n");
    exit(2);
}
$sessionId = $argv[1];
$get = json_decode($argv[2], true);
if (!is_array($get)) {
    fwrite(STDERR, "the GET parameters must be a JSON object\n");
    exit(2);
}

error_reporting(E_ERROR | E_PARSE);

$_SERVER['REQUEST_URI'] = '/saldi/includes/luk.php';
$_SERVER['PHP_SELF'] = '/saldi/includes/luk.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest Chrome';

$_GET = $get;
$_POST = array();
$_REQUEST = $_GET;

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId);

chdir(dirname(__DIR__, 3) . '/debitor'); // a page folder next to includes/, as for a popup of debitor/

include __DIR__ . '/../../../includes/luk.php';
