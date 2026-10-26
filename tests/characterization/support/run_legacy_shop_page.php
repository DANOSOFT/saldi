<?php
// tests/characterization/support/run_legacy_shop_page.php
//
// Child-process runner for the legacy shop calls made directly from page scripts
// (SD-750): debitor/ordreliste.php (order intake and the shop-import link),
// sync_shop/sync_all_products.php and lager/varekort.php. Like run_bogfor_page.php it
// fabricates the session seeded by CharacterizationEnv, sets $_GET/$_POST the way the
// browser does and includes the real page, so the page's own code runs unchanged. The
// shop endpoints in the tenant point at LegacyShopStub.
//
// argv: 1 = page, relative to the repo root (e.g. debitor/ordreliste.php)
//       2 = session id (must match the seeded online row)
//       3 = JSON object {get: {...}, post: {...}}
//       4 = file the page output is written to (also when the page exit()s)
//
// History:
// 20261026 CL/SZ Created (SD-777, sub-task of SD-750).

if ($argc < 5) {
	fwrite(STDERR, "usage: php run_legacy_shop_page.php <page> <session_id> <request.json> <output file>\n");
	exit(2);
}
[, $page, $sessionId, $request, $outputFile] = $argv;
$request = json_decode($request, true) ?: [];

error_reporting(E_ERROR | E_PARSE); // the legacy pages are warning-noisy; keep child output usable

$_SERVER['REQUEST_URI'] = '/saldi/' . $page;
$_SERVER['PHP_SELF'] = '/saldi/' . $page;
$_SERVER['HTTP_HOST'] = 'chartest.local';
$_SERVER['HTTPS'] = '';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_GET = $request['get'] ?? [];
$_POST = $request['post'] ?? [];
if ($_POST) {
	$_SERVER['REQUEST_METHOD'] = 'POST';
}

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId);

$root = dirname(__DIR__, 3);
chdir($root . '/' . dirname($page)); // the pages use ../includes/... relative includes

ob_start();
register_shutdown_function(static function () use ($outputFile): void {
	file_put_contents($outputFile, (string)ob_get_clean());
	fwrite(STDOUT, "CHARTEST_PAGE_DONE\n");
});
include basename($page);
