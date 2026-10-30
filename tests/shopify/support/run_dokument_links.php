<?php
// tests/shopify/support/run_dokument_links.php
//
// Child-process runner for includes/documents/dokumentLinks.php (SD-752). Boots the tenant
// session-less with shopify_bootstrap(), as the worker does, and runs the calls given as JSON,
// e.g. [["link", 12, "invoice"], ["revoke", 12, "invoice"], ["resolve", "<token>"]].
//
// Usage: php run_dokument_links.php <test_db> <calls json>
//
// Prints a JSON list with one entry per call: result, or error (class, message and code).
//
// History:
// 20261030 CL/SZ SD-752: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_dokument_links.php <test_db> <calls json>\n");
    exit(2);
}
$testDb = $argv[1];
$calls = json_decode($argv[2], true);

require_once __DIR__ . '/ShopifyEnv.php';
ShopifyEnv::assertSafeTestDb($testDb);

$db_type = 'postgresql';
$db_encode = 'UTF8';
$sqhost = ShopifyEnv::pgHost();
$squser = ShopifyEnv::pgUser();
$sqpass = ShopifyEnv::pgPass();
$sqdb = ShopifyEnv::masterDb();
$db = $sqdb;
$db_skriv_id = null;
$brugernavn = '';
$webservice = false;

require_once ShopifyEnv::repoRoot() . '/includes/db_query.php';
$connection = db_connect($sqhost, $squser, $sqpass, $sqdb);
require_once ShopifyEnv::repoRoot() . '/includes/std_func.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/bootstrap.php';
require_once ShopifyEnv::repoRoot() . '/includes/documents/dokumentLinks.php';

shopify_bootstrap($testDb);
$out = array();
foreach ($calls as $call) {
    try {
        switch ($call[0]) {
            case 'link':
                $out[] = array('result' => dokument_link((int)$call[1], (string)$call[2]));
                break;
            case 'resolve':
                $out[] = array('result' => dokument_resolve((string)$call[1]));
                break;
            case 'revoke':
                dokument_revoke((int)$call[1], (string)$call[2]);
                $out[] = array('result' => null);
                break;
            default:
                throw new InvalidArgumentException("unknown call {$call[0]}");
        }
    } catch (Throwable $e) {
        $out[] = array('error' => array('class' => get_class($e), 'message' => $e->getMessage(), 'code' => $e->getCode()));
    }
}
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
