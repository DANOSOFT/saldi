<?php
// tests/characterization/support/run_page_with_install_settings.php
//
// Child-process runner for a logged-in legacy page with optional installation settings of includes/connect.php
// ($allowedLedgers, $sharedWithLive), set as global variables the way connect.php sets them.
// The page runs with a fabricated session that CharacterizationEnv has seeded in the `online` table.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = JSON object of settings, e.g. '{"allowedLedgers": ["saldi_9"]}' ('{}' for none)
//       3 = the page, relative to the repository root, e.g. "includes/luk.php"
//       4 = JSON object of GET parameters
//
// The page's output goes to stdout. A refusal from includes/ledgerAllowList.php on the command line writes to
// stderr and exits with code 1.
//
// History:
// 20261127 ASR Created for LedgerAllowListSessionTest.

if ($argc < 5) {
    fwrite(STDERR, "usage: php run_page_with_install_settings.php <session_id> <settings json> <page> <get json>\n");
    exit(2);
}
$sessionId = $argv[1];
$settings = json_decode($argv[2], true);
$page = $argv[3];
$get = json_decode($argv[4], true);
if (!is_array($settings) || !is_array($get) || !preg_match('#^[a-z]+/[A-Za-z0-9_]+\.php$#', $page)) {
    fwrite(STDERR, "settings and GET must be JSON objects, the page a path like includes/luk.php\n");
    exit(2);
}
foreach ($settings as $name => $value) {
    if (!in_array($name, array('allowedLedgers', 'sharedWithLive'), true)) {
        fwrite(STDERR, "unknown setting $name\n");
        exit(2);
    }
    $GLOBALS[$name] = $value;
}

error_reporting(E_ERROR | E_PARSE);

$_SERVER['REQUEST_URI'] = '/saldi/' . $page;
$_SERVER['PHP_SELF'] = '/saldi/' . $page;
$_SERVER['SCRIPT_NAME'] = '/saldi/' . $page;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest Chrome';

$_GET = $get;
$_POST = array();
$_REQUEST = $_GET;

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId);

chdir(dirname(__DIR__, 3) . '/' . dirname($page)); // the page uses ../includes/... relative includes

include __DIR__ . '/../../../' . $page;
