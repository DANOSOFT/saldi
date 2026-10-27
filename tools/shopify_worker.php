<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/shopify_worker.php --- ver 5.0.0 --- 2026.10.26
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
//
// 20261026 Sawaneh SD-737: created (WP02-2).

if (php_sapi_name() !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	print 'CLI only';
	exit;
}

/** @return array<string, callable(array<string, string>): int> */
function shopify_worker_commands(): array {
	return array(
		'run' => 'shopify_worker_cmd_run',
	);
}

/**
 * Handlers and producers of the worker run; each later package registers its job types here.
 *
 * @return array{registry: array<string, callable>, producers: array<string, callable>}
 */
function shopify_worker_wiring(): array {
	$registry = array();
	$producers = array();
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
	$dir = dirname(__DIR__) . "/temp/$db";
	if (!is_dir($dir)) {
		@mkdir($dir, 0777, true);
	}
	$lock = @fopen("$dir/.ht_shopify_worker.lock", 'c');
	if ($lock === false) {
		fwrite(STDERR, "shopify worker: lock file $dir/.ht_shopify_worker.lock cannot be opened; nothing run\n");
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
