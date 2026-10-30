<?php
// tests/shopify/support/run_catalogue.php
//
// Child-process runner for the catalogue (SD-741): bootstraps the tenant with its fiscal year and
// runs a list of steps against a FakeCatalogueShop whose state lives in a file, so a test can edit
// the tenant between runs and Shopify keeps its products.
//   php run_catalogue.php <test_db> <state file> '<steps json>'
// Steps: ["sync"|"discover", shop_key, vare_id, verify], ["job"|"job_late", type, shop_key, vare_id, verify],
//        ["saved", vare_id, delay, php time zone, db session time zone] (a page's zones), ["saved_rollback", vare_id], ["seed", shop_key, after, batch],
//        ["seed_job", shop_key, deadline_offset, lease_kept], ["fail", op, mode].
// Prints JSON: one result per step (or its exception), the operations sent and the fake's products.
//
// History:
// 20261029 CL/SS SD-741: created.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_catalogue.php <test_db> <state file> '<steps json>'\n");
    exit(2);
}
$testDb = $argv[1];
$stateFile = $argv[2];
$steps = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);

require_once __DIR__ . '/ShopifyEnv.php';
require_once __DIR__ . '/FakeCatalogueShop.php';
ShopifyEnv::assertSafeTestDb($testDb);

function alert($msg)
{
    fwrite(STDERR, "alert: $msg\n");
    exit(3);
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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/catalogue.php';

$fake = is_file($stateFile) ? unserialize((string)file_get_contents($stateFile)) : new FakeCatalogueShop();
$sent = count($fake->calls);
$rt = ['graphql' => $fake->opts()];
$shopId = fn(string $key): int => (int)shopify_shop($key)['id'];

$results = [];
try {
    shopify_bootstrap($testDb);
    foreach ($steps as $step) {
        try {
            switch ($step[0]) {
                case 'sync':
                    $results[] = shopify_catalogue_sync($rt + ['verify' => $step[3] ?? true], $shopId($step[1]), (int)$step[2], 'test');
                    break;
                case 'discover':
                    $results[] = shopify_catalogue_discover($rt + ['verify' => $step[3] ?? true], $shopId($step[1]), (int)$step[2], 'test');
                    break;
                case 'job':
                case 'job_late':
                    // the handler as the worker runs it, with the fake as transport; job_late has no time left
                    $jobRt = $step[0] === 'job_late' ? ['graphql' => $fake->opts(['deadline' => 1000.0])] : $rt;
                    $job = ['id' => 0, 'type' => $step[1], 'shop_id' => $shopId($step[2]), 'payload' => ['vare_id' => (int)$step[3], 'verify' => $step[4] ?? true], 'checkpoint' => null];
                    $results[] = shopify_catalogue_job($job, 0.0, fn(array $r, int $s, int $v, string $c): array
                        => ($step[1] === 'catalogue.link' ? 'shopify_catalogue_discover' : 'shopify_catalogue_sync')($jobRt + $r, $s, $v, $c));
                    break;
                case 'saved':
                    if (isset($step[3])) {
                        date_default_timezone_set($step[3]); // a page whose user cookie sets another zone
                    }
                    if (isset($step[4])) {
                        shopify_db_rows("SELECT set_config('TimeZone', $1, false)", [$step[4]]); // a page session in the server's default zone
                    }
                    shopify_catalogue_item_saved((int)$step[1], (int)($step[2] ?? 0));
                    $results[] = 'saved';
                    break;
                case 'saved_rollback':
                    shopify_db_rows('BEGIN');
                    shopify_catalogue_item_saved((int)$step[1]);
                    shopify_db_rows('ROLLBACK');
                    $results[] = 'rolled back';
                    break;
                case 'seed':
                    $results[] = shopify_catalogue_seed(['after' => (int)$step[2]], $shopId($step[1]), (int)$step[3]);
                    break;
                case 'seed_job':
                    $job = ['id' => 0, 'type' => 'catalogue.seed', 'shop_id' => $shopId($step[1]), 'payload' => ['batch' => 2], 'checkpoint' => null];
                    $results[] = shopify_catalogue_seed_job($job, microtime(true) + (float)$step[2], fn(): bool => $step[3] ?? true);
                    break;
                case 'fail':
                    $fake->fail[$step[1]] = $step[2];
                    $results[] = 'armed';
                    break;
                default:
                    throw new LogicException("unknown step {$step[0]}");
            }
        } catch (Throwable $e) {
            $results[] = ['exception' => get_class($e), 'message' => $e->getMessage(), 'retryable' => $e instanceof ShopifyCatalogueError ? $e->retryable : null];
        }
    }
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage(), 'class' => get_class($e)]);
    exit(1);
}
file_put_contents($stateFile, serialize($fake));
echo json_encode(['results' => $results, 'ops' => array_slice($fake->ops(), $sent), 'calls' => array_slice($fake->calls, $sent),
    'products' => $fake->products, 'metafields' => $fake->metafields], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
