<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/shopify_worker.php --- ver 5.1.0 --- 2026.11.08
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// Shopify worker command line (WP02). One runner per tenant, guarded by a fail-closed flock
// on temp/<db>/.ht_shopify_worker.lock: a second runner exits 0 at once, a lock file that
// cannot be opened runs nothing and exits 3. Started every minute by a systemd timer, or in
// dev by tools/shopify_worker_loop.sh. Later packages add subcommands to shopify_worker_commands()
// and their handlers and producers to shopify_worker_wiring().
//
// Usage: php tools/shopify_worker.php run --db=<tenant> [--max-seconds=50]
//        php tools/shopify_worker.php register-webhooks --db=<tenant> [--base-url=https://...] [--shop=<shop_key>]
//        php tools/shopify_worker.php replay --db=<tenant> --shop=<shop_key> --file=<day file> [--promote=1]
//        php tools/shopify_worker.php seed --db=<tenant> --shop=<shop_key> [--batch=250]
//        php tools/shopify_worker.php baseline --db=<tenant> --shop=<shop_key>
//        php tools/shopify_worker.php reconcile --db=<tenant> --shop=<shop_key>
//        php tools/shopify_worker.php remap --db=<tenant> --shop=<shop_key> --lager=<kodenr> [--location=gid://shopify/Location/...]
//        php tools/shopify_worker.php set-app --db=<tenant> --shop=<shop_key> --client-id=<id> < client-secret
//
// 20261026 Sawaneh SD-737: created (WP02-2).
// 20261028 Sawaneh SD-738: the alarm mail (mail.alert) and the call-log purge (runtime.purge) are wired in.
// 20261028 CL/SZ SD-748: the events producer promotes stored webhook events into jobs.
// 20261028 CL/SZ SD-749: commands register-webhooks and replay; the webhook.health handler.
// 20261029 CL/SZ SD-749 review: register-webhooks reports a misconfigured shop as failed and goes on.
// 20261029 CL/SZ SD-749 review: register-webhooks and replay need no fiscal year; a tenant they can't use ends with a line and exit 2.
// 20261029 CL/SS SD-741: the catalogue jobs (catalogue.upsert, catalogue.link, catalogue.seed) and the command seed.
// 20261101 CL/SS SD-792: the customer.upsert handler (Shopify customer to debtor).
// 20261103 CL/SS SD-802: the price.window handler (prices and offers to every shop with prices_enabled).
// 20261103 CL/SS SD-801: the status handlers fulfillment.upsert, order.status, order.cancelled and refund.upsert.
// 20261103 CL/SS SD-743: the order.import handler (paid web order created, delivered and posted).
// 20261103 CL/MJ SD-790: the shop.uninstalled handler and the command set-app (the app's client ID and secret).
// 20261104 CL/SZ SD-756: the stock producer drains shopify_stock_dirty after the events; the stock.push handler.
// 20261105 CL/SZ SD-757: the stock.display, stock.reconcile and stock.baseline handlers; commands baseline, reconcile and remap.
// 20261106 CL/MJ SD-790 review: set-app's messages end in \n like the other commands' do.
// 20261108 CL/SS SD-803: the customer.export handler (a Saldi debtor becomes a Shopify customer).

if (php_sapi_name() !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	print 'CLI only';
	exit;
}

/** @return array<string, callable(array<string, string>): int> */
function shopify_worker_commands(): array {
	return array(
		'run' => 'shopify_worker_cmd_run',
		'register-webhooks' => 'shopify_worker_cmd_register_webhooks',
		'replay' => 'shopify_worker_cmd_replay',
		'seed' => 'shopify_worker_cmd_seed',
		'baseline' => 'shopify_worker_cmd_baseline',
		'reconcile' => 'shopify_worker_cmd_reconcile',
		'remap' => 'shopify_worker_cmd_remap',
		'set-app' => 'shopify_worker_cmd_set_app',
	);
}

/**
 * Handlers and producers of the worker run; each later package registers its job types here.
 *
 * @return array{registry: array<string, callable>, producers: array<string, callable>}
 */
function shopify_worker_wiring(): array {
	require_once __DIR__ . '/../includes/shopify/events.php';
	require_once __DIR__ . '/../includes/shopify/services/catalogue.php';
	require_once __DIR__ . '/../includes/shopify/services/customers.php';
	require_once __DIR__ . '/../includes/shopify/services/customersOut.php';
	require_once __DIR__ . '/../includes/shopify/services/prices.php';
	require_once __DIR__ . '/../includes/shopify/services/statusIn.php';
	require_once __DIR__ . '/../includes/shopify/services/orderImport.php';
	require_once __DIR__ . '/../includes/shopify/services/stock.php';
	require_once __DIR__ . '/../includes/shopify/oauth.php';
	$registry = array();
	// SD-749: the hourly reconcile that restores webhook subscriptions Shopify deleted.
	shopify_worker_register($registry, 'webhook.health', 'shopify_webhook_health_job');
	$producers = array(
		// SD-748: received webhook events become queued jobs, ORDERS_PAID first.
		'events' => function (array $ctx, float $deadline): array {
			return shopify_promote_events();
		},
		// SD-756: stock changes become one stock.push per shop (run-loop step 4).
		'stock' => function (array $ctx, float $deadline): array {
			return shopify_stock_drain_dirty($ctx);
		},
	);
	shopify_worker_register($registry, 'mail.alert', 'shopify_mail_alert_job');
	shopify_worker_register($registry, 'runtime.purge', 'shopify_runtime_purge_job');
	// SD-741: items to the primary shop as drafts, links in the others, and the seed.
	shopify_worker_register($registry, 'catalogue.upsert', 'shopify_catalogue_upsert_job');
	shopify_worker_register($registry, 'catalogue.link', 'shopify_catalogue_link_job');
	shopify_worker_register($registry, 'catalogue.seed', 'shopify_catalogue_seed_job');
	// SD-792: a Shopify customer becomes, or updates, its debtor.
	shopify_worker_register($registry, 'customer.upsert', 'shopify_customer_upsert_job');
	// SD-803: a Saldi debtor becomes a customer in the shop (store orders carry it).
	shopify_worker_register($registry, 'customer.export', 'shopify_customer_export_job');
	// SD-802: the quarter-hour price window of each shop with prices_enabled.
	shopify_worker_register($registry, 'price.window', 'shopify_prices_window_job');
	// SD-801: fulfillments, order status, cancellations and refunds projected onto the order's link.
	shopify_worker_register($registry, 'fulfillment.upsert', 'shopify_fulfillment_upsert_job');
	shopify_worker_register($registry, 'order.status', 'shopify_order_status_job');
	shopify_worker_register($registry, 'order.cancelled', 'shopify_order_cancelled_job');
	shopify_worker_register($registry, 'refund.upsert', 'shopify_refund_upsert_job');
	// SD-743: a paid web order is created, delivered and posted.
	shopify_worker_register($registry, 'order.import', 'shopify_order_import_job');
	// SD-756: absolute stock per location to each shop.
	shopify_worker_register($registry, 'stock.push', fn(array $job, float $deadline, callable $heartbeat): array
		=> shopify_stock_push(array('heartbeat' => $heartbeat), $job, $deadline));
	// SD-757: the saldi.udstillet metafield, the nightly drift read and the go-live baseline.
	shopify_worker_register($registry, 'stock.display', fn(array $job, float $deadline, callable $heartbeat): array
		=> shopify_stock_display(array('heartbeat' => $heartbeat), $job, $deadline));
	shopify_worker_register($registry, 'stock.reconcile', fn(array $job, float $deadline, callable $heartbeat): array
		=> shopify_stock_reconcile(array('heartbeat' => $heartbeat), $job, $deadline));
	shopify_worker_register($registry, 'stock.baseline', fn(array $job, float $deadline, callable $heartbeat): array
		=> shopify_stock_baseline(array('heartbeat' => $heartbeat), $job, $deadline));
	// SD-790: the app was uninstalled; the token goes and every flow is switched off.
	shopify_worker_register($registry, 'shop.uninstalled', 'shopify_shop_uninstalled_job');
	return array('registry' => $registry, 'producers' => $producers);
}

/** @param array<string, string> $opts */
function shopify_worker_cmd_run(array $opts): int {
	global $db_type, $sqhost, $squser, $sqpass, $sqdb, $db_encode, $connection;
	$db = $opts['db'] ?? '';
	if (!preg_match('/^[a-z0-9_]{1,40}$/', $db)) {
		fwrite(STDERR, "usage: php tools/shopify_worker.php run --db=<tenant> [--max-seconds=50]\n");
		return 2;
	}
	$lock = shopify_worker_lock_file($db);
	if ($lock === false) {
		return 3;
	}
	if (!flock($lock, LOCK_EX | LOCK_NB)) {
		fclose($lock);
		return 0;
	}
	try {
		require_once __DIR__ . '/../includes/db_query.php';
		require_once __DIR__ . '/../includes/connect.php';
		require_once __DIR__ . '/../includes/shopify/bootstrap.php';
		require_once __DIR__ . '/../includes/shopify/worker.php';
		shopify_bootstrap($db);
		if (!shopify_schema_ready()) {
			fwrite(STDERR, "shopify worker: schema not installed on $db; nothing run\n");
			return 4;
		}
		$wiring = shopify_worker_wiring();
		$summary = shopify_worker_run(array('db' => $db), $wiring['registry'], $wiring['producers'], max(1, (int)($opts['max-seconds'] ?? 50)));
		echo json_encode($summary, JSON_UNESCAPED_SLASHES) . "\n";
		return 0;
	} finally {
		flock($lock, LOCK_UN);
		fclose($lock);
	}
}

/**
 * Opens the tenant's worker lock file, not yet locked. False, with the reason on stderr, when it can't be opened.
 *
 * @return resource|false
 */
function shopify_worker_lock_file(string $db) {
	$dir = dirname(__DIR__) . "/temp/$db";
	if (!is_dir($dir)) {
		@mkdir($dir, 0777, true);
	}
	$lock = @fopen("$dir/.ht_shopify_worker.lock", 'c');
	if ($lock === false) {
		fwrite(STDERR, "shopify worker: lock file $dir/.ht_shopify_worker.lock cannot be opened; nothing run\n");
	}
	return $lock;
}

/**
 * Bootstraps the tenant for a one-off command (no worker lock: it claims no jobs). False, with
 * the reason on stderr, when the tenant can't be used.
 *
 * @param array<string, string> $opts
 */
function shopify_worker_boot(array $opts, string $usage): bool {
	global $db_type, $sqhost, $squser, $sqpass, $sqdb, $db_encode, $connection;
	$db = $opts['db'] ?? '';
	if (!preg_match('/^[a-z0-9_]{1,40}$/', $db)) {
		fwrite(STDERR, "usage: php tools/shopify_worker.php $usage\n");
		return false;
	}
	require_once __DIR__ . '/../includes/db_query.php';
	require_once __DIR__ . '/../includes/connect.php';
	require_once __DIR__ . '/../includes/shopify/bootstrap.php';
	require_once __DIR__ . '/../includes/shopify/events.php';
	try {
		shopify_bootstrap($db, false); // the commands post nothing, so no fiscal year is needed
	} catch (ShopifyBootstrapError $e) {
		fwrite(STDERR, "shopify worker: $db can't be used: " . $e->getMessage() . "\n");
		return false;
	}
	if (!shopify_schema_ready()) {
		fwrite(STDERR, "shopify worker: schema not installed on $db; nothing run\n");
		return false;
	}
	return true;
}

/**
 * Registers the webhook subscriptions of every shop, or of --shop, at --base-url (else
 * SALDI_SHOPIFY_WEBHOOK_BASE_URL, else the stored one). A given URL is stored for the hourly
 * webhook.health job. Refused when this install's webhook endpoint serves another tenant (or
 * none), since the deliveries would land there. Prints one JSON line per shop; exits 1 when a
 * topic failed.
 *
 * @param array<string, string> $opts
 */
function shopify_worker_cmd_register_webhooks(array $opts): int {
	$usage = 'register-webhooks --db=<tenant> [--base-url=https://...] [--shop=<shop_key>]';
	require_once __DIR__ . '/../includes/shopify/events.php';
	$given = $opts['base-url'] ?? (string)getenv('SALDI_SHOPIFY_WEBHOOK_BASE_URL');
	if ($given !== '' && shopify_webhook_base_url_check($given) === null) {
		fwrite(STDERR, "register-webhooks: the base URL must be https without query or fragment\n");
		return 2;
	}
	if (!shopify_worker_boot($opts, $usage)) {
		return 2;
	}
	try {
		$served = shopify_bootstrap_configured_db();
	} catch (ShopifyBootstrapError $e) {
		$served = '';
	}
	if ($served !== $opts['db']) {
		fwrite(STDERR, "register-webhooks: this install's webhook endpoint serves " . ($served === '' ? 'no tenant' : $served)
			. ", not {$opts['db']}; set SALDI_SHOPIFY_DB or includes/shopify/.ht_shopify_db first\n");
		return 2;
	}
	$shops = isset($opts['shop']) ? array_filter(array(shopify_shop($opts['shop']))) : shopify_shops();
	if (!$shops) {
		fwrite(STDERR, "register-webhooks: no shop to register\n");
		return 2;
	}
	if ($given !== '') {
		shopify_save_webhook_base_url($given);
	}
	$base = shopify_webhook_base_url();
	if ($base === null) {
		fwrite(STDERR, "register-webhooks: no base URL; pass --base-url=https://... once\n");
		return 2;
	}
	$failed = 0;
	foreach ($shops as $shop) {
		try {
			$result = shopify_register_webhooks($shop, $base);
		} catch (RuntimeException | InvalidArgumentException $e) {
			// InvalidArgumentException: the shop's domain or API version is wrong; the next shops still run
			$result = array('failed' => count(SHOPIFY_WEBHOOK_TOPICS), 'errors' => array($e->getMessage()));
		}
		$failed += $result['failed'];
		echo json_encode(array('shop' => $shop['shop_key']) + $result, JSON_UNESCAPED_SLASHES) . "\n";
	}
	return $failed > 0 ? 1 : 0;
}

/**
 * Replays a recorded day of webhooks (tests/shopify/fixtures/) for --shop through the receiver,
 * and with --promote=1 promotes the inbox afterwards. Test tenants (saldi_shopify_test_*) only,
 * so synthetic orders never reach real books. Prints the counts; exits 1 when a delivery was
 * rejected.
 *
 * @param array<string, string> $opts
 */
function shopify_worker_cmd_replay(array $opts): int {
	$usage = 'replay --db=<tenant> --shop=<shop_key> --file=<day file> [--promote=1]';
	if (($opts['shop'] ?? '') === '' || ($opts['file'] ?? '') === '') {
		fwrite(STDERR, "usage: php tools/shopify_worker.php $usage\n");
		return 2;
	}
	if (strncmp($opts['db'] ?? '', 'saldi_shopify_test_', 19) !== 0) {
		fwrite(STDERR, "replay: test tenants (saldi_shopify_test_*) only\n");
		return 2;
	}
	if (!shopify_worker_boot($opts, $usage)) {
		return 2;
	}
	$shop = shopify_shop($opts['shop']);
	if ($shop === null) {
		fwrite(STDERR, "replay: unknown shop {$opts['shop']}\n");
		return 2;
	}
	$file = $opts['file'][0] === '/' ? $opts['file'] : dirname(__DIR__) . '/' . $opts['file'];
	try {
		$result = shopify_replay_fixture($shop, $file, array('promote' => ($opts['promote'] ?? '') === '1'));
	} catch (InvalidArgumentException | RuntimeException $e) {
		fwrite(STDERR, 'replay: ' . $e->getMessage() . "\n");
		return 2;
	}
	echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
	return $result['rejected'] > 0 ? 1 : 0;
}

/**
 * Queues the catalogue seed of --shop (one catalogue.seed job; the worker run then queues every
 * webshop item for it in chunks of --batch). The shop needs products_enabled. Prints the job.
 *
 * @param array<string, string> $opts
 */
function shopify_worker_cmd_seed(array $opts): int {
	$usage = 'seed --db=<tenant> --shop=<shop_key> [--batch=250]';
	if (($opts['shop'] ?? '') === '') {
		fwrite(STDERR, "usage: php tools/shopify_worker.php $usage\n");
		return 2;
	}
	if (!shopify_worker_boot($opts, $usage)) {
		return 2;
	}
	require_once __DIR__ . '/../includes/shopify/services/catalogue.php';
	$shop = shopify_shop($opts['shop']);
	if ($shop === null || $shop['products_enabled'] !== 't') {
		fwrite(STDERR, "seed: shop {$opts['shop']} " . ($shop === null ? 'is unknown' : 'has products_enabled off') . "\n");
		return 2;
	}
	$batch = (string)($opts['batch'] ?? '250');
	if (!ctype_digit($batch) || (int)$batch < 1 || (int)$batch > 1000) {
		fwrite(STDERR, "seed: --batch must be a whole number from 1 to 1000\n");
		return 2;
	}
	$batch = (int)$batch;
	$job = shopify_queue_enqueue('catalogue.seed', (int)$shop['id'], "{$shop['shop_key']}:seed", array('batch' => $batch), SHOPIFY_QUEUE_PRIORITY['catalogue.seed']);
	// a seed already waiting or running keeps its own batch
	echo json_encode(array('shop' => $shop['shop_key']) + ($job['created'] ? array('batch' => $batch) : array('note' => 'a seed is already queued or running')) + $job, JSON_UNESCAPED_SLASHES) . "\n";
	return 0;
}

/**
 * The shop row of a stock command, null with the reason on stderr when --db or --shop can't be used.
 *
 * @param array<string, string> $opts
 * @return array<string, mixed>|null
 */
function shopify_worker_stock_shop(array $opts, string $usage): ?array {
	if (($opts['shop'] ?? '') === '') {
		fwrite(STDERR, "usage: php tools/shopify_worker.php $usage\n");
		return null;
	}
	if (!shopify_worker_boot($opts, $usage)) {
		return null;
	}
	require_once __DIR__ . '/../includes/shopify/services/stock.php';
	$shop = shopify_shop($opts['shop']);
	if ($shop === null) {
		fwrite(STDERR, "shopify worker: unknown shop {$opts['shop']}\n");
	}
	return $shop;
}

/**
 * Queues the stock baseline of --shop (one stock.baseline job, which the worker run sends in chunks; see
 * shopify_stock_start_baseline()). Prints the job.
 *
 * @param array<string, string> $opts
 */
function shopify_worker_cmd_baseline(array $opts): int {
	$shop = shopify_worker_stock_shop($opts, 'baseline --db=<tenant> --shop=<shop_key>');
	if ($shop === null) {
		return 2;
	}
	try {
		$job = shopify_stock_start_baseline(array(), (int)$shop['id']);
	} catch (ShopifyConfigError $e) {
		fwrite(STDERR, 'baseline: ' . $e->getMessage() . "\n");
		return 2;
	}
	echo json_encode(array('shop' => $shop['shop_key'], 'job' => $job)) . "\n";
	return 0;
}

/**
 * Stores the client ID of --shop's app, and the client secret when one is given on stdin (one
 * line, so it stays out of the shell history and the process list). The secret is the shop's
 * webhook secret too: Shopify signs the app's webhooks with it. Prints what was set.
 *
 * @param array<string, string> $opts
 */
function shopify_worker_cmd_set_app(array $opts): int {
	$usage = 'set-app --db=<tenant> --shop=<shop_key> --client-id=<id> < client-secret';
	if (($opts['shop'] ?? '') === '' || !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $opts['client-id'] ?? '')) {
		fwrite(STDERR, "usage: php tools/shopify_worker.php $usage\n");
		return 2;
	}
	if (!shopify_worker_boot($opts, $usage)) {
		return 2;
	}
	if (shopify_shop($opts['shop']) === null) {
		fwrite(STDERR, "set-app: unknown shop {$opts['shop']}\n");
		return 2;
	}
	require_once __DIR__ . '/../includes/shopify/oauth.php';
	$secret = trim((string)fgets(STDIN));
	shopify_db_transaction(function () use ($opts, $secret) {
		shopify_save_secret($opts['shop'], SHOPIFY_OAUTH_CLIENT_ID, $opts['client-id']);
		if ($secret !== '') {
			shopify_save_secret($opts['shop'], SHOPIFY_WEBHOOK_SECRET, $secret);
		}
	});
	echo json_encode(array('shop' => $opts['shop'], 'client_id' => 'set', 'client_secret' => $secret === '' ? 'unchanged' : 'set'), JSON_UNESCAPED_SLASHES) . "\n";
	return 0;
}

/**
 * Queues a stock reconcile of --shop now, besides the nightly slot. The shop must publish stock. Prints the job.
 *
 * @param array<string, string> $opts
 */
function shopify_worker_cmd_reconcile(array $opts): int {
	$shop = shopify_worker_stock_shop($opts, 'reconcile --db=<tenant> --shop=<shop_key>');
	if ($shop === null) {
		return 2;
	}
	if ($shop['stock_publish_enabled'] !== 't' || $shop['stock_baseline_at'] === null) {
		fwrite(STDERR, "reconcile: shop {$shop['shop_key']} doesn't publish stock yet\n");
		return 2;
	}
	$job = shopify_queue_enqueue('stock.reconcile', (int)$shop['id'], "{$shop['shop_key']}:manual", array(), SHOPIFY_QUEUE_PRIORITY['stock.reconcile']);
	echo json_encode(array('shop' => $shop['shop_key']) + $job) . "\n";
	return 0;
}

/**
 * Zeroes --lager's location in --shop and moves the lager to --location, or leaves it unmapped without
 * one (shopify_stock_remap()). Waits for the worker lock and holds it, so no push runs meanwhile. Prints
 * how many pairs were zeroed; exits 1 when Shopify refused or didn't answer, and then the mapping stays.
 *
 * @param array<string, string> $opts
 */
function shopify_worker_cmd_remap(array $opts): int {
	$usage = 'remap --db=<tenant> --shop=<shop_key> --lager=<kodenr> [--location=gid://shopify/Location/...]';
	if (!ctype_digit((string)($opts['lager'] ?? ''))) {
		fwrite(STDERR, "usage: php tools/shopify_worker.php $usage\n");
		return 2;
	}
	$shop = shopify_worker_stock_shop($opts, $usage);
	if ($shop === null) {
		return 2;
	}
	$lock = shopify_worker_lock_file($opts['db']);
	if ($lock === false) {
		return 3;
	}
	flock($lock, LOCK_EX);
	try {
		$zeroed = shopify_stock_remap(array(), (int)$shop['id'], (int)$opts['lager'], $opts['location'] ?? null);
		echo json_encode(array('shop' => $shop['shop_key'], 'lager' => (int)$opts['lager'], 'zeroed' => $zeroed)) . "\n";
		return 0;
	} catch (ShopifyConfigError | InvalidArgumentException $e) {
		fwrite(STDERR, 'remap: ' . $e->getMessage() . "\n");
		return 2;
	} catch (RuntimeException $e) {
		fwrite(STDERR, 'remap: ' . $e->getMessage() . "\n");
		return 1;
	} finally {
		flock($lock, LOCK_UN);
		fclose($lock);
	}
}

/**
 * @param list<string> $argv
 * @return array{0: string, 1: array<string, string>}
 */
function shopify_worker_parse(array $argv): array {
	$command = $argv[1] ?? '';
	$opts = array();
	foreach (array_slice($argv, 2) as $arg) {
		if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m)) {
			$opts[$m[1]] = $m[2];
		}
	}
	return array($command, $opts);
}

[$command, $opts] = shopify_worker_parse($argv);
$commands = shopify_worker_commands();
if (!isset($commands[$command])) {
	fwrite(STDERR, 'usage: php tools/shopify_worker.php <' . implode('|', array_keys($commands)) . "> --db=<tenant>\n");
	exit(2);
}
exit($commands[$command]($opts));
