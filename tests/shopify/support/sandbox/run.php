<?php
// tests/shopify/support/sandbox/run.php
//
// The sandbox runner (SD-766, WP16): runs shopify_sandbox_scenarios() against a target and
// prints one PASS/FAIL/SKIP/BLOCKED line per result (BLOCKED names an owner); the regression runs
// of 19 and 20 Nov fill the burn report's acceptance column from it.
//
//   php tests/shopify/support/sandbox/run.php --target=fake|sandbox [--tenant=saldi_shopify_test_<x>] [--only=K05,X01] [--json]
//   php tests/shopify/support/sandbox/run.php --calibrate [--tenant=saldi_shopify_test_<x>] [--record=<dir>]
//
// --target=fake: FakeShopifyStore as the three shops; scenarios that need a tenant run on a clone
// of SALDI_SHOPIFY_TEMPLATE_DB named <tenant>_sr, dropped afterwards (the named tenant itself is
// never touched), with the suite's SALDI_SHOPIFY_PG* settings (tests/shopify/support/ShopifyEnv.php).
// --target=sandbox: the DTAILS sandboxes, SALDI_SHOPIFY_SANDBOX_<HML|HMS|DI>_DOMAIN and _TOKEN;
// a shop without them is SKIP. --calibrate: the same documents against the fake and a Danosoft dev
// store (SALDI_SHOPIFY_DEVSTORE_DOMAIN and _TOKEN; SKIP without), see calibration.php.
// Credentials come from the environment only, never from a file or a default. The tenant (--tenant,
// else SALDI_SHOPIFY_TEST_DB) must be saldi_shopify_test_*; pos_50 and test_12 are refused.
// Exit 0 when nothing failed, 1 when something did, 2 on an unsafe or incomplete config (nothing run).
//
// History:
// 20261103 CL/LH SD-766: created (the ticket's tests/shopify/sandbox/ is tests/shopify/support/sandbox/ since SD-793).

require_once __DIR__ . '/../../../../includes/shopify/client.php';
require_once __DIR__ . '/scenarios.php';
require_once __DIR__ . '/calibration.php';

const SHOPIFY_SANDBOX_SHOPS = ['hml', 'hms', 'di'];
const SHOPIFY_SANDBOX_STATUSES = ['PASS', 'FAIL', 'SKIP', 'BLOCKED'];
const SHOPIFY_SANDBOX_USAGE = "usage: php tests/shopify/support/sandbox/run.php --target=fake|sandbox [--tenant=saldi_shopify_test_<x>] [--only=K05,...] [--json]\n"
	. "       php tests/shopify/support/sandbox/run.php --calibrate [--tenant=saldi_shopify_test_<x>] [--record=<dir>]\n";

/** A config the runner refuses to run with (exit 2). */
final class ShopifySandboxConfigError extends RuntimeException
{
}

/** @param list<string> $argv */
function shopify_sandbox_main(array $argv): int
{
	try {
		$o = shopify_sandbox_options($argv);
		$ctx = shopify_sandbox_context($o);
	} catch (RuntimeException $e) {
		fwrite(STDERR, 'sandbox runner: unsafe config, nothing run: ' . $e->getMessage() . "\n" . SHOPIFY_SANDBOX_USAGE);
		return 2;
	}
	try {
		$results = $o['calibrate'] ? shopify_sandbox_calibration($ctx, $o['record']) : shopify_sandbox_run($ctx, $o['only']);
	} finally {
		if (($ctx['db'] ?? null) !== null) {
			ShopifyEnv::unregisterTenants();
			ShopifyEnv::dropOwnedTenants();
		}
	}
	$counts = array_fill_keys(SHOPIFY_SANDBOX_STATUSES, 0);
	foreach ($results as $r) {
		$counts[$r['status']]++;
	}
	if ($o['json']) {
		echo json_encode(['target' => $ctx['target'], 'tenant' => $ctx['tenant'], 'clone' => $ctx['db'] ?? null, 'counts' => $counts, 'results' => $results],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
	} else {
		echo shopify_sandbox_report($ctx, $results, $counts);
	}
	return $counts['FAIL'] > 0 ? 1 : 0;
}

/**
 * @param list<string> $argv
 * @return array{target: string, tenant: string, only: list<string>, json: bool, calibrate: bool, record: ?string}
 * @throws ShopifySandboxConfigError
 */
function shopify_sandbox_options(array $argv): array
{
	$o = ['target' => '', 'tenant' => (string)getenv('SALDI_SHOPIFY_TEST_DB'), 'only' => [], 'json' => false, 'calibrate' => false, 'record' => null];
	foreach (array_slice($argv, 1) as $arg) {
		if (preg_match('/^--(target|tenant|only|record)=(.*)$/', $arg, $m)) {
			$o[$m[1]] = $m[1] === 'only' ? array_values(array_filter(explode(',', $m[2]))) : $m[2];
		} elseif ($arg === '--json' || $arg === '--calibrate') {
			$o[substr($arg, 2)] = true;
		} else {
			throw new ShopifySandboxConfigError("unknown argument $arg");
		}
	}
	if ($o['calibrate']) {
		$o['target'] = 'calibrate';
	} elseif (!in_array($o['target'], ['fake', 'sandbox'], true)) {
		throw new ShopifySandboxConfigError('--target must be fake or sandbox');
	}
	if ($o['tenant'] === '') {
		throw new ShopifySandboxConfigError('no tenant: give --tenant=saldi_shopify_test_<x> or SALDI_SHOPIFY_TEST_DB');
	}
	ShopifyEnv::assertSafeTestDb($o['tenant']);
	$unknown = array_diff($o['only'], array_keys(shopify_sandbox_scenarios()));
	if ($unknown) {
		throw new ShopifySandboxConfigError('no scenario ' . implode(', ', $unknown));
	}
	if ($o['record'] !== null && !is_dir($o['record'])) {
		throw new ShopifySandboxConfigError("--record needs an existing directory, not {$o['record']}");
	}
	return $o;
}

/**
 * A shop from SALDI_SHOPIFY_<PREFIX>_DOMAIN and _TOKEN: null when neither is set; both, or refused.
 *
 * @return array{row: array, opts: callable(): array}|null
 */
function shopify_sandbox_env_shop(string $prefix, string $key): ?array
{
	$domain = (string)getenv("SALDI_SHOPIFY_{$prefix}_DOMAIN");
	$token = (string)getenv("SALDI_SHOPIFY_{$prefix}_TOKEN");
	if ($domain === '' && $token === '') {
		return null;
	}
	if ($domain === '' || $token === '') {
		throw new ShopifySandboxConfigError("SALDI_SHOPIFY_{$prefix}_DOMAIN and _TOKEN go together; only one is set");
	}
	if (!preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', $domain)) {
		throw new ShopifySandboxConfigError("SALDI_SHOPIFY_{$prefix}_DOMAIN must be a <shop>.myshopify.com domain");
	}
	return ['row' => ['shop_key' => $key, 'domain' => $domain, 'api_version' => SHOPIFY_API_VERSION],
		'opts' => fn(): array => ['access_token' => $token, 'deadline' => microtime(true) + 30, 'log' => fn(array $entry) => null]];
}

/** The target's shops (and, for the fake, the cloned tenant). */
function shopify_sandbox_context(array $o): array
{
	$ctx = ['target' => $o['target'], 'tenant' => $o['tenant'], 'shops' => [], 'db' => null];
	if ($o['target'] === 'sandbox') {
		foreach (SHOPIFY_SANDBOX_SHOPS as $key) {
			$prefix = 'SANDBOX_' . strtoupper($key);
			$ctx['shops'][$key] = shopify_sandbox_env_shop($prefix, $key) ?? ['skip' => "SALDI_SHOPIFY_{$prefix}_DOMAIN and _TOKEN are not set"];
		}
		return $ctx;
	}
	$store = new FakeShopifyStore();
	foreach (SHOPIFY_SANDBOX_SHOPS as $key) {
		$ctx['shops'][$key] = ['row' => $store->addShop($key), 'opts' => fn(): array => $store->opts()];
	}
	$ctx['store'] = $store;
	if ($o['target'] === 'calibrate') {
		$ctx['real'] = shopify_sandbox_env_shop('DEVSTORE', 'devstore');
		return $ctx;
	}
	// ShopifyEnv reads the test tenant's name from the environment
	putenv('SALDI_SHOPIFY_TEST_DB=' . $o['tenant']);
	$ctx['db_skip'] = ShopifyEnv::unavailableReason();
	if ($ctx['db_skip'] === null) {
		$ctx['db'] = ShopifyEnv::testDb('_sr');
		ShopifyEnv::cloneTenant($ctx['db']);
		ShopifyEnv::registerTenant($ctx['db']);
		$schema = ShopifyEnv::runSchema($ctx['db'], 'ensure');
		if ($schema['exit'] !== 0) {
			throw new RuntimeException('schema ensure failed on ' . $ctx['db'] . ': ' . $schema['stderr']);
		}
	}
	return $ctx;
}

/** @return list<array{id: string, target: string, status: string, detail: string, shop?: string, step?: string, owner?: string}> */
function shopify_sandbox_run(array $ctx, array $only): array
{
	$out = [];
	foreach (shopify_sandbox_scenarios() as $id => $scenario) {
		if ($only !== [] && !in_array($id, $only, true)) {
			continue;
		}
		try {
			if (!in_array($ctx['target'], $scenario['targets'], true)) {
				$results = [$scenario['elsewhere'] ?? shopify_sandbox_result('SKIP', 'not run on ' . $ctx['target'])];
			} elseif (isset($scenario['script'])) {
				$results = shopify_sandbox_script($id, $scenario, $ctx);
			} else {
				$results = ($scenario['run'])($ctx);
			}
		} catch (Throwable $e) {
			$results = [shopify_sandbox_result('FAIL', get_class($e) . ': ' . $e->getMessage())];
		}
		foreach ($results as $r) {
			$out[] = shopify_sandbox_checked(['id' => $id, 'target' => $ctx['target']] + $r);
		}
	}
	return $out;
}

function shopify_sandbox_calibration(array $ctx, ?string $record): array
{
	if ($ctx['real'] === null) {
		return [['id' => 'CAL', 'target' => 'calibrate', 'status' => 'SKIP', 'detail' => 'SALDI_SHOPIFY_DEVSTORE_DOMAIN and _TOKEN are not set']];
	}
	$store = $ctx['store'];
	$fake = $ctx['shops']['hml'];
	// a product and a location on the fake side, as a dev store has
	$store->seedProduct($fake['row']['domain'], 'calibration-1', 'SD766-CAL-1', [$store->locations($fake['row']['domain'])[0] => 1]);
	return array_map(fn(array $r): array => shopify_sandbox_checked(['id' => 'CAL', 'target' => 'calibrate'] + $r), shopify_calibrate($fake, $ctx['real'], $record));
}

/** A result as the report takes it: a known status, and BLOCKED only with an owner. */
function shopify_sandbox_checked(array $r): array
{
	if (!in_array($r['status'] ?? null, SHOPIFY_SANDBOX_STATUSES, true)) {
		return ['status' => 'FAIL', 'detail' => 'unknown status ' . json_encode($r['status'] ?? null) . ': ' . ($r['detail'] ?? '')] + $r;
	}
	if ($r['status'] === 'BLOCKED' && trim((string)($r['owner'] ?? '')) === '') {
		return ['status' => 'FAIL', 'detail' => 'BLOCKED without an owner: ' . $r['detail']] + $r;
	}
	return $r;
}

function shopify_sandbox_report(array $ctx, array $results, array $counts): string
{
	$out = 'SD-766 sandbox runner: target ' . $ctx['target'] . ', tenant ' . $ctx['tenant'] . (($ctx['db'] ?? null) ? " (clone {$ctx['db']})" : '') . "\n";
	foreach ($results as $r) {
		$out .= sprintf("%-4s %-8s %-10s %s%s\n", $r['id'], $r['status'], $r['shop'] ?? '', isset($r['step']) ? "[{$r['step']}] " : '', $r['detail'])
			. (isset($r['owner']) ? sprintf("%25s owner %s\n", '', $r['owner']) : '');
	}
	return $out . implode('  ', array_map(fn(string $s): string => "$s {$counts[$s]}", SHOPIFY_SANDBOX_STATUSES)) . "\n";
}

/**
 * Observes until $accept takes the observed value or $timeout seconds pass, for the five-minute
 * targets (NF-2, NF-5): the result carries the seconds it took.
 *
 * @param array{clock?: callable(): float, sleep?: callable(float): void, interval?: float} $seams test seams; interval defaults to 5 s
 * @return array{ok: bool, value: mixed, seconds: float, polls: int}
 */
function shopify_await(callable $observe, callable $accept, int $timeout = 300, array $seams = []): array
{
	$clock = $seams['clock'] ?? fn(): float => microtime(true);
	$sleep = $seams['sleep'] ?? fn(float $s) => usleep((int)round($s * 1e6));
	$interval = (float)($seams['interval'] ?? 5.0);
	$started = $clock();
	for ($polls = 1; ; $polls++) {
		$value = $observe();
		$elapsed = $clock() - $started;
		$ok = (bool)$accept($value);
		if ($ok || $elapsed >= $timeout) {
			return ['ok' => $ok, 'value' => $value, 'seconds' => round($elapsed, 3), 'polls' => $polls];
		}
		$sleep(min($interval, $timeout - $elapsed));
	}
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
	exit(shopify_sandbox_main($argv));
}
