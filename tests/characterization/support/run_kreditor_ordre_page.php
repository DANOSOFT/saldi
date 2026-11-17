<?php
// tests/characterization/support/run_kreditor_ordre_page.php
//
// Child-process runner for one request to the purchase order page
// (kreditor/ordre.php), as a browser sends it.
//
// The page starts a session and includes connect/online/std_func at top
// level, so it runs as a child process with a fabricated session that
// CharacterizationEnv seeded in `online`. The caller gives the GET and POST
// parameters; a POST is what the browser sends when the order form is
// submitted, so a test can take the page this runner rendered, fill the form
// the way a browser does, and send it back.
//
// argv: 1 = JSON file {"get": {...}, "post": {...}}
//       2 = session id (must match the seeded online row)
//       3 = file the rendered page is written to
//
// Prints CHARTEST_PAGE_DONE on stdout, also when the page ends with exit();
// the test asserts on the rendered page and on database state.
//
// History:
// 20261117 CL/MMR SST-1024 Created to drive «Opslag» and the next «Gem» on a purchase order end to end.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_kreditor_ordre_page.php <request.json> <session_id> <page.html>\n");
    exit(2);
}
$request = json_decode((string)file_get_contents($argv[1]), true);
if (!is_array($request)) {
    fwrite(STDERR, "request file is not JSON\n");
    exit(2);
}
$sessionId = $argv[2];
$pageFile = $argv[3];

error_reporting(E_ERROR | E_PARSE);

$_GET = $request['get'] ?? [];
$_POST = $request['post'] ?? [];
$_REQUEST = $_GET + $_POST;
$query = http_build_query($_GET);
$_SERVER['REQUEST_METHOD'] = $_POST ? 'POST' : 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/kreditor/ordre.php' . ($query !== '' ? "?$query" : '');
$_SERVER['PHP_SELF'] = '/saldi/kreditor/ordre.php';
$_SERVER['QUERY_STRING'] = $query;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId);

chdir(dirname(__DIR__, 3) . '/kreditor'); // the page uses ../includes/... relative includes

ob_start();
register_shutdown_function(static function () use ($pageFile): void {
    $page = '';
    while (ob_get_level() > 0) {
        $page = ob_get_clean() . $page;
    }
    file_put_contents($pageFile, $page);
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $page . "\n");
    }
    fwrite(STDOUT, "CHARTEST_PAGE_DONE\n");
});
include 'ordre.php';
