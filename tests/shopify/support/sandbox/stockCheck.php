<?php
// tests/shopify/support/sandbox/stockCheck.php
//
// What the stock sandbox scripts share (SD-757): boots the tenant in $argv[1] like K12.php (the including
// script defines STOCK_CHECK_K), reads a variant's levels and saldi.udstillet per shop. Run on the server that
// hosts the tenant. Reads only, in Saldi and in Shopify; working out what an item should show can open a
// warning on the error list.
//
// History:
// 20261105 CL/SZ SD-757: created.

$stockCheckTenant = (string)$argv[1];
if (!preg_match('/^saldi_shopify_test_[a-z0-9_]+$/', $stockCheckTenant)) {
    echo STOCK_CHECK_K . " SKIP: $stockCheckTenant is not a saldi_shopify_test_* tenant\n";
    exit(0);
}
$stockCheckRoot = dirname(__DIR__, 4);
if (!is_readable("$stockCheckRoot/includes/connect.php")) {
    echo STOCK_CHECK_K . " SKIP: includes/connect.php is not installed here\n";
    exit(0);
}
// connect.php finds db_query.php relative to the working directory, like the worker in tools/.
chdir("$stockCheckRoot/tools");
include "$stockCheckRoot/includes/connect.php";
require_once "$stockCheckRoot/includes/shopify/bootstrap.php";
require_once "$stockCheckRoot/includes/shopify/links.php";
require_once "$stockCheckRoot/includes/shopify/services/stock.php";
try {
    shopify_bootstrap($stockCheckTenant);
} catch (Throwable $e) {
    echo STOCK_CHECK_K . ' SKIP: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(0);
}

const STOCK_CHECK_READ = 'query stockCheck($item: ID!, $variant: ID!) {
	inventoryItem(id: $item) { inventoryLevels(first: 50) { nodes { location { id } quantities(names: ["available"]) { quantity } } } }
	productVariant(id: $variant) { metafield(namespace: "saldi", key: "udstillet") { value } }
}';

/**
 * Per shop that publishes stock and lists the item: what Saldi wants and what the shop shows.
 *
 * @return array<string, array{want: array<int, int>, shown: array<int, int|null>, udstillet: array<int, string>|null, display: array<int, bool>}|string>
 *   by shop key; a string is why the shop can't be checked
 */
function stock_check_read(int $vareId): array {
    $out = [];
    foreach (shopify_db_rows('SELECT * FROM shopify_shops WHERE stock_publish_enabled ORDER BY shop_key') as $shop) {
        $item = shopify_stock_plan((int)$shop['id'], [$vareId], shopify_lager_config())['items'][$vareId] ?? null;
        if ($item === null || $item['variant_gid'] === null) {
            $out[$shop['shop_key']] = "item $vareId is not listed here, or can't be published";
            continue;
        }
        $r = shopify_graphql($shop, 'stockCheck', STOCK_CHECK_READ, ['item' => $item['inventory_item_gid'], 'variant' => $item['variant_gid']], ['retry_mode' => 'read', 'deadline' => microtime(true) + 20]);
        if (!$r['ok']) {
            $out[$shop['shop_key']] = "the read failed: {$r['error']}";
            continue;
        }
        $levels = [];
        foreach ($r['data']['inventoryItem']['inventoryLevels']['nodes'] ?? [] as $level) {
            $levels[$level['location']['id']] = (int)($level['quantities'][0]['quantity'] ?? 0);
        }
        $row = ['want' => [], 'shown' => [], 'display' => [], 'udstillet' => null];
        foreach ($item['pairs'] as $lager => $pair) {
            $row['want'][$lager] = $pair['quantity'];
            $row['shown'][$lager] = $levels[$pair['location_gid']] ?? null;
            $row['display'][$lager] = $pair['udstillet'];
        }
        $value = $r['data']['productVariant']['metafield']['value'] ?? null;
        $row['udstillet'] = $value === null ? null : json_decode((string)$value, true);
        $out[$shop['shop_key']] = $row;
    }
    return $out;
}

/** The set check of K09 and K55: every lager shows what Saldi works out, and the total is what the sellable lagre can assemble. */
function stock_check_set(int $vareId): int {
    $k = STOCK_CHECK_K;
    $failed = false;
    $shops = stock_check_read($vareId);
    if ($shops === []) {
        echo "$k SKIP: no shop publishes stock\n";
        return 0;
    }
    $config = shopify_lager_config();
    $sellable = array_map(fn(array $l): int => (int)$l['lager'], array_filter(shopify_availability_lagre($config), fn(array $l): bool => $l['rolle'] === 'saelgbar' && !$l['virtual']));
    $needs = [];
    foreach (shopify_stock_boms([$vareId])[$vareId] ?? [] as $c) {
        $needs[$c['vare_id']] = ($needs[$c['vare_id']] ?? 0) + $c['quantity_milli'];
    }
    $stock = [];
    foreach (shopify_db_rows('SELECT vare_id, lager, round(sum(beholdning) * 1000)::bigint AS milli FROM lagerstatus WHERE vare_id = ANY($1::int[]) AND coalesce(variant_id, 0) = 0 GROUP BY vare_id, lager', ['{' . implode(',', array_keys($needs) ?: [0]) . '}']) as $row) {
        $stock[(int)$row['vare_id']][(int)$row['lager']] = (int)$row['milli'];
    }
    $most = $needs === [] ? 0 : shopify_availability_total($stock, $needs, array_values($sellable));
    foreach ($shops as $key => $row) {
        if (is_string($row)) {
            echo "$k SKIP $key: $row\n";
            continue;
        }
        $total = array_sum(array_map('intval', array_intersect_key($row['shown'], array_flip($sellable))));
        if ($row['shown'] === $row['want'] && $total === $most) {
            echo "$k PASS $key: " . json_encode($row['shown']) . ", $total in total, as many as the sellable lagre can assemble\n";
        } else {
            $failed = true;
            echo "$k FAIL $key: shows " . json_encode($row['shown']) . ", Saldi wants " . json_encode($row['want']) . "; total $total, the lagre can assemble $most\n";
        }
    }
    return $failed ? 1 : 0;
}
