<?php
// tests/shopify/support/run_varekort.php
//
// Child-process runner for the item card (SD-802): includes the real lager/varekort.php the way a
// browser request reaches it, with a session row in the master's online table for the test
// tenant (one session id per tenant, so parallel suites on other tenants do not meet), and the
// page's own includes/connect.php. The output is written to a file, also when the page exit()s.
//   php run_varekort.php <test_db> '<request json {get: {...}, post: {...}}>' <output file>
//
// History:
// 20261103 CL/SS SD-802: created.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_varekort.php <test_db> '<request json>' <output file>\n");
    exit(2);
}
[, $testDb, $request, $outputFile] = $argv;
$request = json_decode($request, true, 512, JSON_THROW_ON_ERROR);

require_once __DIR__ . '/ShopifyEnv.php';
ShopifyEnv::assertSafeTestDb($testDb);
ini_set('display_errors', 'stderr'); // the page warns; its output is the page

$sessionId = substr(hash('sha256', "sd802:$testDb"), 0, 32);
$master = ShopifyEnv::connect(ShopifyEnv::masterDb());
ShopifyEnv::rows($master, 'DELETE FROM online WHERE session_id = $1', [$sessionId]);
ShopifyEnv::rows($master, "INSERT INTO online (session_id, brugernavn, db, dbuser, rettigheder, regnskabsaar, logtime, revisor, language_id)
    VALUES ($1, 'admin', $2, $3, '1111111111111111', 1, $4, false, 0)", [$sessionId, $testDb, ShopifyEnv::pgUser(), (string)time()]);
pg_close($master);

$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = '/saldi/lager/varekort.php';
$_SERVER['HTTP_HOST'] = 'sd802.local';
$_SERVER['HTTPS'] = '';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'sd802';
$_GET = $request['get'] ?? [];
$_POST = $request['post'] ?? [];
$_SERVER['REQUEST_METHOD'] = $_POST ? 'POST' : 'GET';
ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId);

chdir(ShopifyEnv::repoRoot() . '/lager');
ob_start();
register_shutdown_function(static function () use ($outputFile, $sessionId): void {
    file_put_contents($outputFile, (string)ob_get_clean());
    $master = ShopifyEnv::connect(ShopifyEnv::masterDb());
    ShopifyEnv::rows($master, 'DELETE FROM online WHERE session_id = $1', [$sessionId]);
    fwrite(STDOUT, "SD802_PAGE_DONE\n");
});
include 'varekort.php';
