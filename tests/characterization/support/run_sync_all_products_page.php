<?php
// tests/characterization/support/run_sync_all_products_page.php
//
// Child-process runner for ShopOfferPushTest: runs the bulk webshop sync page (sync_shop/sync_all_products.php) in a CLI process,
// in the seeded session of a test tenant, with the request globals a browser would set. The page sees the CLI SAPI, so it takes
// its cron path and skips the browser's integrations write check: this covers the page's sync, not browser authorisation.
// Its calls go out as background curl commands, so the caller waits for them at the fake shops.
//
// argv: 1 = session id
//
// History:
// 20261118 CL/LH Created for the webshop offer push: a bulk sync that waits on a locked table while the offer ends.
// 20261118 CL/LH CodeRabbit review of #1157: the header says this is CLI coverage, not browser authorisation.
if ($argc < 2) {
	fwrite(STDERR, "usage: php run_sync_all_products_page.php <session_id>\n");
	exit(2);
}
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 'stderr');
$_SERVER['REQUEST_URI'] = '/saldi/sync_shop/sync_all_products.php';
$_SERVER['PHP_SELF'] = '/saldi/sync_shop/sync_all_products.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_SERVER['REQUEST_METHOD'] = 'GET';
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[1]);
register_shutdown_function(function () {
	while (ob_get_level()) ob_end_flush();
	fwrite(STDOUT, "\nCHARTEST_PAGE_DONE\n");
});
chdir(dirname(__DIR__, 3) . '/sync_shop'); // the page uses ../includes/... relative includes
include 'sync_all_products.php';
