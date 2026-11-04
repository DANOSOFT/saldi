<?php
// tests/shopify/support/run_prices.php
//
// Child-process runner for prices (SD-802): bootstraps the tenant with its fiscal year and runs a
// list of steps against a FakeShopifyStore whose state lives in a JSON file, so a test can edit the
// tenant between runs and the shops keep their products.
//   php run_prices.php <test_db> <state file> '<steps json>'
// Steps: ["seed", shop_key, vare_id] (a product for the item in that shop, linked to it),
//        ["sync", shop_key, now|null, after, deadline_offset], ["sync_relink", shop_key, vare_id] (the link changes while the first call is in flight), ["worker", now] (one worker pass with the price.window handler),
//        ["fail", root_field, mode], ["legacy", vare_id, fields] (POS's price next to the resolver's, see legacy_vs_resolver()).
// Prints JSON: one result per step (or its exception), the calls sent and the shops' variants and metafields.
//
// History:
// 20261103 CL/SS SD-802: created.
// 20261104 CL/SS SD-802 review: POS's own line loop for the total; ["sync_relink"] relinks while a call is in flight.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_prices.php <test_db> <state file> '<steps json>'\n");
    exit(2);
}
$testDb = $argv[1];
$stateFile = $argv[2];
$steps = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);

require_once __DIR__ . '/ShopifyEnv.php';
require_once __DIR__ . '/FakeShopifyStore.php';
ShopifyEnv::assertSafeTestDb($testDb);
ini_set('display_errors', 'stderr'); // the legacy price functions warn; stdout is the result

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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/worker.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/prices.php';
require_once ShopifyEnv::repoRoot() . '/includes/ordrefunc.php';

$store = is_file($stateFile) ? FakeShopifyStore::fromState(json_decode((string)file_get_contents($stateFile), true)) : new FakeShopifyStore();
$sent = count($store->calls);
$domain = fn(string $key): string => "$key-fake.myshopify.com";
$shopId = fn(string $key): int => (int)shopify_shop($key)['id'];

/**
 * The price POS charges for the item now, the way pos_ordre.php gets it: varescan() prefills
 * find_pris() through dkdecimal(), the cashier's Enter sends it to opret_ordrelinje() as a given
 * gross price (usdecimal(), incl_moms on), and POS totals the order (pos_total()).
 * $fields are written to the item first; a value ["day", n] or ["time", s] is now plus n days or
 * s seconds, so a window boundary falls on the second the two run in. Retried until data, POS
 * and the resolver ran within one second.
 */
function legacy_vs_resolver(int $vareId, array $fields, array $offers): array
{
    global $momssats, $db, $afd;
    $momssats = 25; // the POS order's rate
    for ($try = 0; $try < 10; $try++) {
        time_nanosleep(0, (int)(1e9 - (int)(microtime(true) * 1e9) % 1000000000) + 1000000); // just after a second starts
        $second = time();
        $now = (new DateTimeImmutable('@' . $second))->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $value = fn($v) => is_array($v) ? $now->modify(($v[0] === 'day' ? $v[1] . ' days' : $v[1] . ' seconds'))->format($v[0] === 'day' ? 'Y-m-d' : 'H:i:s') : $v;
        $set = [];
        $params = [$vareId];
        foreach ($fields as $column => $v) {
            $params[] = $value($v);
            $set[] = "$column = $" . count($params);
        }
        if ($set) {
            shopify_db_rows('UPDATE varer SET ' . implode(', ', $set) . ' WHERE id = $1', $params);
        }
        shopify_db_rows('DELETE FROM varetilbud WHERE vare_id = $1', [$vareId]);
        foreach ($offers as $o) {
            shopify_db_rows('INSERT INTO varetilbud (vare_id, ugedag, startdag, slutdag, starttid, sluttid, salgspris, kostpris) VALUES ($1, $2, $3, $4, $5, $6, $7, 0)',
                [$vareId, (int)$now->format('N'), $second - 3600, $second + 3600, '00:00:00', '23:59:59', $o]);
        }
        $item = shopify_db_rows('SELECT * FROM varer WHERE id = $1', [$vareId])[0];
        $gross = find_pris($item['varenr']) * 1;
        $prisNy = $gross ? dkdecimal($gross, 2) : '';
        $order = (int)shopify_db_rows("INSERT INTO ordrer (konto_id, art, momssats, status, ordredate, valuta, valutakurs) VALUES (0, 'PO', 25, 0, CURRENT_DATE, 'DKK', 100) RETURNING id")[0]['id'];
        // debitor/pos_ordre.php:1614
        ob_start();
        $answer = opret_ordrelinje($order, '', $item['varenr'], 1, '', usdecimal($prisNy, 2), usdecimal('', 2), 100, 'PO', '', '', '0', 'on', '', '', '', '', '', '0', 1, __LINE__);
        $printed = (string)ob_get_clean();
        $line = shopify_db_rows('SELECT pris, rabat, momssats FROM ordrelinjer WHERE ordre_id = $1', [$order])[0] ?? null;
        $resolved = shopify_price_resolve($item, shopify_catalogue_vat_rate((int)$item['gruppe']), $now);
        if (time() !== $second) {
            continue;
        }
        $total = $line === null ? null : pos_total($order);
        return ['answer' => $answer, 'printed' => $printed, 'prefill' => $prisNy, 'rabat' => $line === null ? null : (float)$line['rabat'],
            'pos' => $total === null ? null : sprintf('%.2f', $total), 'resolver' => $resolved];
    }
    throw new RuntimeException('could not run POS and the resolver within one second');
}

/**
 * What POS charges for an open order: the real line loop (ordrelinjerDataII.php, as vis_pos_linjer()
 * includes it) and the total's rounding (showPosLinesFunc.php:163).
 */
function pos_total(int $id): float
{
    $bgcolor = '';
    $vis_saet = false;
    $status = 0;
    $nettosum = 0;
    $lagernr = $lagernavn = []; // no named stocks
    ob_start();
    include ShopifyEnv::repoRoot() . '/debitor/pos_ordre_includes/showPosLines/ordrelinjerDataII.php';
    ob_end_clean();
    return afrund($sum, 2);
}

$results = [];
try {
    shopify_bootstrap($testDb);
    @mkdir(ShopifyEnv::repoRoot() . "/temp/$db", 0777, true);
    chdir(ShopifyEnv::repoRoot() . '/debitor'); // opret_ordrelinje() writes ../temp/<db>/
    foreach ($steps as $step) {
        try {
            switch ($step[0]) {
                case 'seed':
                    $d = $domain($step[1]);
                    try {
                        $store->shopRow($d);
                    } catch (LogicException $e) {
                        $store->addShop($step[1]);
                    }
                    $varenr = shopify_db_rows('SELECT varenr FROM varer WHERE id = $1', [(int)$step[2]])[0]['varenr'];
                    $p = $store->seedProduct($d, "saldi-{$step[2]}", $varenr);
                    $results[] = shopify_upsert_product_link($shopId($step[1]), (int)$step[2], 0, ['product_gid' => $p['product'], 'variant_gid' => $p['variant'],
                        'inventory_item_gid' => $p['item'], 'listed' => true]);
                    break;
                case 'sync':
                    $rt = ['graphql' => $store->opts(), 'after' => (int)($step[3] ?? 0)];
                    if (($step[2] ?? null) !== null) {
                        $rt['now'] = new DateTimeImmutable($step[2]);
                    }
                    if (isset($step[4])) {
                        $rt['deadline'] = microtime(true) + (float)$step[4];
                    }
                    $results[] = shopify_prices_sync($rt, $shopId($step[1]), 'test');
                    break;
                case 'sync_relink':
                    $relinked = false;
                    $opts = $store->opts(['transport' => function (string $url, array $headers, string $body, float $connect, float $timeout) use ($store, $step, &$relinked): array {
                        if (!$relinked) {
                            // another job relinks the item and commits while Shopify is answering
                            shopify_db_rows('UPDATE shopify_product_links SET updated_at = clock_timestamp()::timestamp WHERE vare_id = $1', [(int)$step[2]]);
                            $relinked = true;
                        }
                        return $store->send($url, $headers, $body, $connect, $timeout);
                    }]);
                    $results[] = shopify_prices_sync(['graphql' => $opts], $shopId($step[1]), 'test');
                    break;
                case 'worker':
                    $at = new DateTimeImmutable($step[1]);
                    $registry = ['price.window' => fn(array $job, float $deadline, callable $hb): array
                        => shopify_prices_window_job($job, $deadline, $hb, ['graphql' => $store->opts(), 'now' => $at])];
                    $results[] = shopify_worker_run(['db' => $testDb, 'now' => fn(): DateTimeImmutable => $at, 'heartbeat_file' => null], $registry, [], 30);
                    break;
                case 'fail':
                    $store->fail[$step[1]] = $step[2];
                    $results[] = 'armed';
                    break;
                case 'legacy':
                    $results[] = legacy_vs_resolver((int)$step[1], $step[2], $step[3] ?? []);
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
$state = $store->exportState();
file_put_contents($stateFile, json_encode($state));
$variants = $metafields = [];
foreach ($state['shops'] as $d => $s) {
    foreach ($s['variants'] as $gid => $v) {
        $variants[$s['key']][$v['sku']] = ['price' => $v['price'], 'compareAtPrice' => $v['compareAtPrice']];
        foreach ($s['metafields'][$gid] ?? [] as $key => $mf) {
            $metafields[$s['key']][$v['sku']][$key] = ['value' => $mf['value'], 'type' => $mf['type']];
        }
    }
}
$calls = array_map(fn(array $c): array => ['shop' => substr($c['shop'], 0, strpos($c['shop'], '-')), 'operation' => $c['operation'], 'variables' => $c['variables']],
    array_slice($store->calls, $sent));
echo json_encode(['results' => $results, 'calls' => $calls, 'variants' => $variants, 'metafields' => $metafields], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
