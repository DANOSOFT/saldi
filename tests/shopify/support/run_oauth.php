<?php
// tests/shopify/support/run_oauth.php
//
// Child-process runner for the app install (SD-790). Bootstraps the tenant like run_events.php
// and runs one mode with the JSON spec file in argv[3]:
//   request    shopify_oauth_request() with spec.query at spec.now; the token exchange goes to a
//              stand-in transport that answers spec.answer ({status, body}) and records each call
//   uninstall  the shop.uninstalled handler for spec.job
//   promote    shopify_promote_events() (no spec)
// Prints JSON on stdout: the results, or the error that ended the run. Log lines go to stderr,
// where the test checks that no secret is in them.
//
// History:
// 20261103 CL/MJ SD-790: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_oauth.php <request|uninstall|promote> <test_db> [spec.json]\n");
    exit(2);
}
$mode = $argv[1];
$testDb = $argv[2];
$spec = isset($argv[3]) ? json_decode((string)file_get_contents($argv[3]), true) : [];

require_once __DIR__ . '/ShopifyEnv.php';
ShopifyEnv::assertSafeTestDb($testDb);

function alert($msg)
{
    fwrite(STDERR, "alert: $msg\n");
    exit(3);
}

function out(array $data, int $exit = 0): void
{
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit($exit);
}

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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/bootstrap.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/oauth.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/queue.php';

ini_set('log_errors', '1');
ini_set('error_log', 'php://stderr');

try {
    shopify_bootstrap($testDb, false);
    switch ($mode) {
        case 'request':
            $calls = [];
            $transport = function (string $url, array $headers, string $body, float $connect, float $timeout) use (&$calls, $spec): array {
                $calls[] = ['url' => $url, 'headers' => $headers, 'body' => json_decode($body, true)];
                $answer = $spec['answer'] ?? ['status' => 0, 'body' => ''];
                return ['status' => (int)$answer['status'], 'body' => (string)$answer['body'], 'errno' => 0, 'error' => '', 'sent' => true, 'retry_after' => null, 'request_id' => null];
            };
            $result = shopify_oauth_request($spec['query'], ['now' => (int)$spec['now'], 'transport' => $transport]);
            out(['result' => $result, 'calls' => $calls]);
            // no break: out() exits
        case 'uninstall':
            out(['result' => shopify_shop_uninstalled_job($spec['job'], time() + 30, fn(): bool => true)]);
            // no break: out() exits
        case 'promote':
            out(['result' => shopify_promote_events()]);
            // no break: out() exits
        default:
            fwrite(STDERR, "unknown mode $mode\n");
            exit(2);
    }
} catch (Throwable $e) {
    out(['error' => ['class' => get_class($e), 'message' => $e->getMessage()]], 1);
}
