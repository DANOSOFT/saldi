<?php
// tests/shopify/support/run_config.php
//
// Child-process runner for the shop registry and the link functions (SD-735). Bootstraps the
// tenant like run_bootstrap.php and runs one mode:
//   config   shops, flow flags, secrets, lager roles, location map, lager settings, shipping,
//            payment and brand maps
//   links    order, product and customer links: reservation, advance with flag merge, upserts,
//            13+ digit GIDs in two shops
//   race     reserves one web order link (two of these run at once in the concurrency test)
//   secret_race  saves the access token of shop 'race' (two of these run at once)
// Prints JSON on stdout. Secrets are never printed; the config mode reports only whether the
// round trip matched and whether the value was found in the tenant's temp/ logs.
//
// History:
// 20261022 Sawaneh SD-735: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_config.php <config|links|race|secret_race> <test_db>\n");
    exit(2);
}
$mode = $argv[1];
$testDb = $argv[2];

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

/** @return array{class: string, sqlstate: string, message: string} */
function failure(Throwable $e): array
{
    return ['class' => get_class($e), 'sqlstate' => $e instanceof ShopifyDbError ? $e->sqlstate : '', 'message' => $e->getMessage()];
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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/config.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/links.php';

try {
    shopify_bootstrap($testDb);
    switch ($mode) {
        case 'config':
            $out = [];
            $ids = shopify_db_transaction(function () {
                return [
                    'hml' => shopify_save_shop(['shop_key' => 'hml', 'domain' => 'HML-Test.myshopify.com', 'navn' => 'Havemøbelland', 'afd' => 1, 'is_primary' => true]),
                    'brand2' => shopify_save_shop(['shop_key' => 'brand2', 'domain' => 'brand2.myshopify.com', 'afd' => 2]),
                    'brand3' => shopify_save_shop(['shop_key' => 'brand3', 'domain' => 'brand3.myshopify.com', 'afd' => 3, 'legacy_slot' => 5]),
                ];
            });
            $out['ids'] = $ids;
            $out['hml'] = shopify_shop('hml');
            $out['unknown_shop'] = shopify_shop('nope');
            $out['all_keys'] = array_column(shopify_shops(), 'shop_key');
            $out['orders_before'] = array_column(shopify_shops('orders'), 'shop_key');
            shopify_db_transaction(fn() => shopify_save_shop(['shop_key' => 'brand2', 'orders_enabled' => true, 'navn' => 'Brand two']));
            $out['orders_after'] = array_column(shopify_shops('orders'), 'shop_key');
            $out['brand2'] = shopify_shop('brand2');
            foreach ([
                'bad_flow' => fn() => shopify_shops('invoices'),
                'bad_column' => fn() => shopify_db_transaction(fn() => shopify_save_shop(['shop_key' => 'hml', 'colour' => 'red'])),
                'bad_domain' => fn() => shopify_db_transaction(fn() => shopify_save_shop(['shop_key' => 'x', 'domain' => 'example.com'])),
                'bad_key' => fn() => shopify_db_transaction(fn() => shopify_save_shop(['shop_key' => 'Bad Key', 'domain' => 'x.myshopify.com'])),
                'dup_afd' => fn() => shopify_db_transaction(fn() => shopify_save_shop(['shop_key' => 'fourth', 'domain' => 'fourth.myshopify.com', 'afd' => 1])),
                'secret_unknown_shop' => fn() => shopify_db_transaction(fn() => shopify_save_secret('nope', 'access_token', 'x')),
            ] as $name => $attempt) {
                try {
                    $attempt();
                    $out[$name] = 'accepted';
                } catch (Throwable $e) {
                    $out[$name] = failure($e);
                }
            }

            $secret = 'shpat_' . bin2hex(random_bytes(12));
            $out['secret_before'] = shopify_secret('hml', 'access_token');
            shopify_db_transaction(fn() => shopify_save_secret('hml', 'access_token', $secret));
            shopify_db_transaction(fn() => shopify_save_secret('hml', 'api_secret', 'shpss_other'));
            $out['secret_roundtrip'] = shopify_secret('hml', 'access_token') === $secret;
            $out['secret_other_shop'] = shopify_secret('brand2', 'access_token');
            shopify_db_transaction(fn() => shopify_save_secret('hml', 'access_token', 'shpat_rotated'));
            $out['secret_rotated'] = shopify_secret('hml', 'access_token') === 'shpat_rotated';
            $out['secret_rows'] = (int)shopify_db_rows("SELECT count(*) AS n FROM settings WHERE var_grp = 'shopify:hml' AND var_name = 'access_token'")[0]['n'];
            shopify_db_transaction(fn() => shopify_save_secret('hml', 'access_token', ''));
            $out['secret_after_delete'] = shopify_secret('hml', 'access_token');
            $out['secret_group'] = shopify_db_rows("SELECT var_grp FROM settings WHERE var_name = 'api_secret' AND var_grp LIKE 'shopify:%'")[0]['var_grp'];
            $found = false;
            $dir = ShopifyEnv::repoRoot() . '/temp/' . $testDb;
            foreach (is_dir($dir) ? scandir($dir) : [] as $file) {
                if (is_file("$dir/$file") && str_contains((string)file_get_contents("$dir/$file"), $secret)) {
                    $found = true;
                }
            }
            $out['secret_in_logs'] = $found;

            shopify_db_transaction(function () use ($ids) {
                shopify_save_lager(1, 'saelgbar', 1);
                shopify_save_lager(2, 'saelgbar', 2);
                shopify_save_lager(3, 'udstilling', null, 1);
                shopify_save_lager(4, 'ingen');
                shopify_save_lager(2, 'saelgbar', 3);
                shopify_db_rows("INSERT INTO settings (var_name, var_grp, var_value) VALUES ('split_set_lager', 'shopify', '2'), ('virtual_lager', 'shopify', '')");
                shopify_save_map($ids['hml'], 1, '1234567890123');
                shopify_save_map($ids['hml'], 2, 'gid://shopify/Location/98765432109876543210');
                shopify_save_map($ids['hml'], 1, 'gid://shopify/Location/1234567890124');
                shopify_save_map($ids['brand2'], 1, 'gid://shopify/Location/1234567890124');
                shopify_db_rows("INSERT INTO shopify_shipping_map (shop_id, rate_name, vare_id, pickup_lager) VALUES ($1, 'Fragt', 77, NULL), ($1, 'Afhent Mårum', NULL, 2)", [$ids['hml']]);
                shopify_db_rows("INSERT INTO shopify_payment_map (shop_id, gateway, card_brand, betalingstype) VALUES ($1, 'shopify_payments', '', 'Kort'), ($1, 'shopify_payments', 'mobilepay', 'MobilePay')", [$ids['hml']]);
                shopify_db_rows("INSERT INTO shopify_brands (sprog, shop_id) VALUES ('Dansk', $1), ('english', $2)", [$ids['hml'], $ids['brand2']]);
            });
            $out['lager_config'] = shopify_lager_config();
            foreach ([
                'bad_role' => fn() => shopify_db_transaction(fn() => shopify_save_lager(5, 'vip')),
                'saelgbar_without_prioritet' => fn() => shopify_db_transaction(fn() => shopify_save_lager(5, 'saelgbar')),
                'dup_prioritet' => fn() => shopify_db_transaction(fn() => shopify_save_lager(5, 'saelgbar', 1)),
                'dup_location' => fn() => shopify_db_transaction(fn() => shopify_save_map($ids['hml'], 3, 'gid://shopify/Location/1234567890124')),
                'bad_location_gid' => fn() => shopify_db_transaction(fn() => shopify_save_map($ids['hml'], 3, 'gid://shopify/Product/1')),
            ] as $name => $attempt) {
                try {
                    $attempt();
                    $out[$name] = 'accepted';
                } catch (Throwable $e) {
                    $out[$name] = failure($e);
                }
            }
            $out['location_1'] = shopify_location($ids['hml'], 1);
            $out['location_2'] = shopify_location($ids['hml'], 2);
            $out['location_unmapped'] = shopify_location($ids['hml'], 9);
            $out['shipping_fragt'] = shopify_shipping($ids['hml'], 'Fragt');
            $out['shipping_pickup'] = shopify_shipping($ids['hml'], 'Afhent Mårum');
            $out['shipping_unknown'] = shopify_shipping($ids['hml'], 'GLS');
            $out['payment_exact'] = shopify_payment($ids['hml'], 'shopify_payments', 'mobilepay');
            $out['payment_fallback'] = shopify_payment($ids['hml'], 'shopify_payments', 'visa');
            $out['payment_no_brand'] = shopify_payment($ids['hml'], 'shopify_payments');
            $out['payment_unknown'] = shopify_payment($ids['hml'], 'paypal');
            $out['brand_empty'] = shopify_brand_shop('')['shop_key'] ?? null;
            $out['brand_spaces'] = shopify_brand_shop('  ')['shop_key'] ?? null;
            $out['brand_dansk_cased'] = shopify_brand_shop('Dansk')['shop_key'] ?? null;
            $out['brand_english'] = shopify_brand_shop('English')['shop_key'] ?? null;
            $out['brand_unknown'] = shopify_brand_shop('Deutsch');
            try {
                shopify_customer_number();
                $out['customer_number'] = 'returned';
            } catch (Throwable $e) {
                $out['customer_number'] = failure($e);
            }
            out($out);
            // no break: out() exits
        case 'links':
            $out = [];
            $ids = shopify_db_transaction(fn() => [
                'a' => shopify_save_shop(['shop_key' => 'a', 'domain' => 'a.myshopify.com']),
                'b' => shopify_save_shop(['shop_key' => 'b', 'domain' => 'b.myshopify.com']),
            ]);
            $gid = 'gid://shopify/Order/98765432109876543210';
            $first = shopify_reserve_order_link($ids['a'], '98765432109876543210', 'web', ['order_name' => '#1001', 'flags' => ['intake' => ['step' => 'validated']]]);
            $again = shopify_reserve_order_link($ids['a'], $gid, 'web');
            $otherShop = shopify_reserve_order_link($ids['b'], $gid, 'web');
            $out['web'] = ['first' => ['id' => $first['id'], 'created' => $first['created'], 'state' => $first['state'], 'gid' => $first['link']['order_gid'], 'name' => $first['link']['order_name'], 'flags' => $first['link']['flags']],
                'again' => ['id' => $again['id'], 'created' => $again['created'], 'state' => $again['state']],
                'other_shop' => ['id' => $otherShop['id'], 'created' => $otherShop['created'], 'gid' => $otherShop['link']['order_gid']]];
            $out['lookup_gid'] = shopify_order_link($ids['a'], '98765432109876543210')['id'] ?? null;
            $store = shopify_reserve_order_link($ids['b'], null, 'store', ['ordre_id' => 4242, 'ship_lager' => 2]);
            $storeAgain = shopify_reserve_order_link($ids['b'], null, 'store', ['ordre_id' => 4242]);
            $out['store'] = ['first' => ['id' => $store['id'], 'created' => $store['created'], 'gid' => $store['link']['order_gid'], 'ship_lager' => $store['link']['ship_lager']],
                'again' => ['id' => $storeAgain['id'], 'created' => $storeAgain['created']]];
            $out['lookup_ordre'] = shopify_order_link_for_ordre(4242, 'store')['id'] ?? null;
            $advanced = shopify_advance_order_link($first['id'], 'created', ['ordre_id' => 555, 'flags' => ['allocation_v1' => ['lager' => 1]]]);
            $advanced = shopify_advance_order_link($first['id'], 'posted', ['flags' => ['intake' => ['step' => 'posted'], 'writeback_v1' => ['tags' => true]]]);
            $out['advanced'] = ['state' => $advanced['state'], 'ordre_id' => $advanced['ordre_id'], 'flags' => json_decode($advanced['flags'], true)];
            $emptyFlags = shopify_advance_order_link($first['id'], 'posted', ['flags' => []]);
            $out['empty_flags'] = ['raw' => $emptyFlags['flags'], 'intake' => shopify_db_rows("SELECT flags->'intake'->>'step' AS step FROM shopify_order_links WHERE id = $1", [$first['id']])[0]['step']];
            try {
                shopify_advance_order_link($first['id'], 'posted', ['flags' => ['not a namespace']]);
                $out['list_flags'] = 'accepted';
            } catch (Throwable $e) {
                $out['list_flags'] = failure($e);
            }
            $storeDone = shopify_advance_order_link($store['id'], 'exported', ['order_gid' => '1234567890123', 'order_name' => '#S1']);
            $out['store_exported'] = ['gid' => $storeDone['order_gid'], 'name' => $storeDone['order_name']];
            foreach ([
                'bad_direction' => fn() => shopify_reserve_order_link($ids['a'], $gid, 'pos'),
                'web_without_gid' => fn() => shopify_reserve_order_link($ids['a'], null, 'web'),
                'store_without_ordre' => fn() => shopify_reserve_order_link($ids['a'], null, 'store'),
                'bad_gid' => fn() => shopify_reserve_order_link($ids['a'], 'gid://shopify/Product/1', 'web'),
                'bad_column' => fn() => shopify_advance_order_link($first['id'], 'x', ['colour' => 'red']),
                'missing_link' => fn() => shopify_advance_order_link(999999, 'x'),
            ] as $name => $attempt) {
                try {
                    $attempt();
                    $out[$name] = 'accepted';
                } catch (Throwable $e) {
                    $out[$name] = failure($e);
                }
            }

            $p1 = shopify_upsert_product_link($ids['a'], 10, 0, ['product_gid' => '12345678901234567890', 'variant_gid' => 'gid://shopify/ProductVariant/22345678901234567890', 'inventory_item_gid' => '32345678901234567890', 'last_hash' => 'h1']);
            $p2 = shopify_upsert_product_link($ids['a'], 10, 0, ['last_hash' => 'h2', 'listed' => false]);
            $pb = shopify_upsert_product_link($ids['b'], 10, 0, ['product_gid' => '12345678901234567890']);
            $out['product'] = ['first' => ['id' => $p1['id'], 'product_gid' => $p1['product_gid'], 'variant_gid' => $p1['variant_gid'], 'inventory_item_gid' => $p1['inventory_item_gid'], 'hash' => $p1['last_hash'], 'listed' => $p1['listed']],
                'second' => ['id' => $p2['id'], 'product_gid' => $p2['product_gid'], 'hash' => $p2['last_hash'], 'listed' => $p2['listed']],
                'other_shop' => ['id' => $pb['id'], 'product_gid' => $pb['product_gid']],
                'by_variant' => shopify_product_link_for_variant($ids['a'], '22345678901234567890')['id'] ?? null,
                'lookup' => shopify_product_link($ids['a'], 10)['id'] ?? null,
                'lookup_missing' => shopify_product_link($ids['a'], 11)];

            $c1 = shopify_upsert_customer_link($ids['a'], '42345678901234567890', 700, ['source_hash' => 'c1', 'source_updated_at' => new DateTimeImmutable('2026-10-22 10:00:00')]);
            $c2 = shopify_upsert_customer_link($ids['a'], 'gid://shopify/Customer/42345678901234567890', 700, ['source_hash' => 'c2']);
            $out['customer'] = ['first' => ['id' => $c1['id'], 'gid' => $c1['customer_gid'], 'adresser_id' => $c1['adresser_id'], 'hash' => $c1['source_hash'], 'updated' => $c1['source_updated_at']],
                'second' => ['id' => $c2['id'], 'hash' => $c2['source_hash']],
                'by_gid' => shopify_customer_link($ids['a'], '42345678901234567890')['id'] ?? null,
                'by_adresser' => shopify_customer_link_for_adresser($ids['a'], 700)['id'] ?? null];
            try {
                shopify_upsert_customer_link($ids['a'], '52345678901234567890', 700);
                $out['customer_relink'] = 'accepted';
            } catch (Throwable $e) {
                $out['customer_relink'] = failure($e);
            }
            out($out);
            // no break: out() exits
        case 'secret_race':
            usleep(random_int(0, 20000));
            shopify_db_transaction(fn() => shopify_save_secret('race', 'access_token', 'shpat_' . getmypid()));
            out(['pid' => getmypid(), 'rows' => (int)shopify_db_rows("SELECT count(*) AS n FROM settings WHERE var_grp = 'shopify:race' AND var_name = 'access_token'")[0]['n']]);
            // no break: out() exits
        case 'race':
            $shop = shopify_shop('race') ?? null;
            if ($shop === null) {
                out(['error' => 'race shop missing'], 1);
            }
            usleep(random_int(0, 20000));
            $r = shopify_reserve_order_link((int)$shop['id'], 'gid://shopify/Order/77777777777777777777', 'web', ['order_name' => '#race']);
            out(['id' => $r['id'], 'created' => $r['created'], 'pid' => getmypid()]);
            // no break: out() exits
        default:
            fwrite(STDERR, "unknown mode $mode\n");
            exit(2);
    }
} catch (ShopifyBootstrapError | ShopifyDbError | ShopifyConfigError | ShopifyLinkError $e) {
    out(['error' => $e->getMessage(), 'class' => get_class($e)], 1);
}
