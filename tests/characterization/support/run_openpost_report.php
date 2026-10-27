<?php
// tests/characterization/support/run_openpost_report.php
//
// Child-process runner for debitor/rapport.php, the page behind Debitor ->
// Rapporter -> Åbne poster (and the Salgsstat button on the same front page).
//
// rapport.php is a legacy page script: it starts a session, includes
// connect/online/std_func at top level, prints HTML and exit()s on several
// branches, so it cannot be included inside the PHPUnit process. This runner
// fills $_GET/$_POST the way the browser does and includes the page with a
// fabricated session that CharacterizationEnv has seeded in the `online`
// table. The page's HTML goes to stdout unbuffered, so an exit() inside the
// page still delivers everything printed before it.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = JSON object {"get": {...}, "post": {...}}
//
// History:
// 20261027 CL/LH PR #450: created for OpenPostReportLayoutTest.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_openpost_report.php <session_id> <request json>\n");
    exit(2);
}
$sessionId = $argv[1];
$request = json_decode($argv[2], true);
if (!is_array($request)) {
    fwrite(STDERR, "the request must be a JSON object\n");
    exit(2);
}

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy; keep child output usable

$_SERVER['REQUEST_URI'] = '/saldi/debitor/rapport.php';
$_SERVER['PHP_SELF'] = '/saldi/debitor/rapport.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

$_GET = isset($request['get']) && is_array($request['get']) ? $request['get'] : array();
$_POST = isset($request['post']) && is_array($request['post']) ? $request['post'] : array();
$_REQUEST = array_merge($_GET, $_POST);

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId); // the page's @session_start() picks this id up -> online row matches

chdir(dirname(__DIR__, 3) . '/debitor'); // the page uses ../includes/... relative includes

include 'rapport.php';
