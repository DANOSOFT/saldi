<?php
// tests/characterization/support/run_ordre_page.php
//
// Child-process runner for the debitor order page (debitor/ordre.php), so a test can act like the browser:
// fetch the page, read its form, change a field and post it back. Like run_modtag_page.php it runs the page
// with a fabricated session that CharacterizationEnv seeded in `online`.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = JSON file with {"get": {...}, "post": {...}}; a non-empty post makes it a POST
//       3 = file the page's HTML is written to
//
// Prints CHARTEST_PAGE_DONE on stdout; the test asserts on the HTML and the database state.
//
// History:
// 20261118 CL/SS SD-833 Created.

if (PHP_SAPI !== 'cli') {
    exit;
}
if ($argc < 4) {
    fwrite(STDERR, "usage: php run_ordre_page.php <session_id> <request.json> <out.html>\n");
    exit(2);
}
$sessionId = $argv[1];
$request = json_decode((string)file_get_contents($argv[2]), true) ?: [];
$outFile = $argv[3];

error_reporting(E_ERROR | E_PARSE);

$_SERVER['REQUEST_URI'] = '/saldi/debitor/ordre.php';
$_SERVER['PHP_SELF'] = '/saldi/debitor/ordre.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/debitor/ordre.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_GET = $request['get'] ?? [];
$_POST = $request['post'] ?? [];
$_REQUEST = $_GET + $_POST;
$_SERVER['REQUEST_METHOD'] = $_POST ? 'POST' : 'GET';

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId);

chdir(dirname(__DIR__, 3) . '/debitor');

register_shutdown_function(function () use ($outFile) {
    $html = '';
    while (ob_get_level() > 0) {
        $html = ob_get_clean() . $html;
    }
    file_put_contents($outFile, $html);
    fwrite(STDOUT, "CHARTEST_PAGE_DONE\n");
});
ob_start();
include 'ordre.php';
