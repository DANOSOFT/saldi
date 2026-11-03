<?php
// tests/shopify/support/sandbox/scenarios.php
//
// The sandbox runner's scenarios (SD-766), one per K, for run.php. A scenario runs on the targets
// it lists (fake: FakeShopifyStore and a cloned tenant; sandbox: the three DTAILS sandboxes from
// env credentials) and reports PASS, FAIL, SKIP or BLOCKED; BLOCKED names an owner. The per-K
// scripts the WP owners write next to this file (K03.php, K45.php, ...) are aggregated as
// script scenarios: run on the server that hosts the sandbox tenant, their arguments from env.
// Add a K here when its script or scenario lands.
//
// History:
// 20261103 CL/LH SD-766: created.
// 20261103 CL/SZ SD-754: K54.

require_once __DIR__ . '/../ShopifyEnv.php';
require_once __DIR__ . '/../FakeShopifyStore.php';

const SHOPIFY_SANDBOX_SHOP_QUERY = 'query { shop { id name myshopifyDomain plan { partnerDevelopment shopifyPlus } } }';

/**
 * @return array<string, array{title: string, targets: list<string>, run?: callable(array): list<array>, script?: string,
 *   args?: array<string, string>, owner?: string, elsewhere?: array{status: string, detail: string, owner?: string}}>
 *   args: script argument => env variable, ":default" after it for a value that is no credential (the tenant is always
 *   the first argument); elsewhere: the result on a target not listed
 */
function shopify_sandbox_scenarios(): array
{
	return [
		'K05' => [
			'title' => 'authenticated access to all three sandboxes; production disabled',
			'targets' => ['fake', 'sandbox'],
			'run' => 'shopify_sandbox_k05',
		],
		'K03' => [
			'title' => 'the same webhook twice gives one inbox row; a wrong signature gets 401',
			'targets' => ['sandbox'],
			'script' => 'K03.php',
			'args' => ['shop_key' => 'SALDI_SHOPIFY_SANDBOX_K03_SHOP:hml', 'endpoint_url' => 'SALDI_SHOPIFY_SANDBOX_ENDPOINT'],
			'owner' => 'DTAILS',
			'elsewhere' => ['status' => 'SKIP', 'detail' => 'runs on the server that hosts the sandbox tenant; on the fake it is WebhookEndpointTest'],
		],
		'K45' => [
			'title' => 'the document link of a posted invoice opens its PDF; a changed link opens nothing',
			'targets' => ['sandbox'],
			'script' => 'K45.php',
			'args' => ['ordre_id' => 'SALDI_SHOPIFY_SANDBOX_K45_ORDRE'],
			'owner' => 'DTAILS',
			'elsewhere' => ['status' => 'SKIP', 'detail' => 'runs on the server that hosts the sandbox tenant; on the fake it is DokumentLinkTest'],
		],
		'K54' => [
			'title' => 'a web order\'s invoice and credit note show the Shopify order name and their own number, a store order\'s invoice its number; none shows the order number',
			'targets' => ['sandbox'],
			'script' => 'K54.php',
			'args' => ['web_invoice_id' => 'SALDI_SHOPIFY_SANDBOX_K54_WEB_INVOICE', 'web_credit_note_id' => 'SALDI_SHOPIFY_SANDBOX_K54_WEB_CREDIT',
				'store_invoice_id' => 'SALDI_SHOPIFY_SANDBOX_K54_STORE_INVOICE'],
			'owner' => 'DTAILS',
			'elsewhere' => ['status' => 'SKIP', 'detail' => 'runs on the server that hosts the sandbox tenant; on the fake it is FormularprintLagerFieldsTest'],
		],
		'X01' => [
			'title' => 'one web order across the lanes: item, webhooks, customer, order, allocation, move, fulfilment, invoice, stock',
			'targets' => ['fake'],
			'run' => 'shopify_sandbox_x01',
			'elsewhere' => ['status' => 'BLOCKED', 'owner' => 'SD-743',
				'detail' => 'runs on the fake until paid-order intake is merged; on the sandboxes it also needs DTAILS\' setup (Å11)'],
		],
	];
}

/** One result row. */
function shopify_sandbox_result(string $status, string $detail, ?string $shop = null, ?string $owner = null, ?string $step = null): array
{
	return array_filter(['status' => $status, 'shop' => $shop, 'step' => $step, 'detail' => $detail, 'owner' => $owner], fn($v): bool => $v !== null);
}

/**
 * K05: every shop answers an authenticated shop query as itself, is a development store (B30:
 * production stays disabled), and refuses a wrong token. On the sandboxes also: the tenant points
 * at no other shop, and DTAILS' setup of the sandboxes (Å11) is listed BLOCKED.
 *
 * @param array{target: string, shops: array<string, array>, tenant_db?: ?string} $ctx
 */
function shopify_sandbox_k05(array $ctx): array
{
	$out = [];
	foreach ($ctx['shops'] as $key => $shop) {
		if (isset($shop['skip'])) {
			$out[] = shopify_sandbox_result('SKIP', $shop['skip'], $key);
			continue;
		}
		$r = shopify_graphql($shop['row'], 'shop', SHOPIFY_SANDBOX_SHOP_QUERY, [], $shop['opts']());
		if (!$r['ok']) {
			$out[] = shopify_sandbox_result('FAIL', "shop query failed: {$r['error']} " . json_encode(array_column($r['errors'], 'message')), $key);
			continue;
		}
		$s = $r['data']['shop'];
		$wrong = shopify_graphql($shop['row'], 'shop', SHOPIFY_SANDBOX_SHOP_QUERY, [], ['access_token' => 'sd766-not-a-token'] + $shop['opts']());
		if ($s['myshopifyDomain'] !== $shop['row']['domain']) {
			$out[] = shopify_sandbox_result('FAIL', "answered as {$s['myshopifyDomain']}, not {$shop['row']['domain']}", $key);
		} elseif ($s['plan']['partnerDevelopment'] !== true) {
			$out[] = shopify_sandbox_result('FAIL', 'not a development store (plan.partnerDevelopment false): production must stay disabled', $key);
		} elseif ($wrong['error'] !== 'http_401') {
			$out[] = shopify_sandbox_result('FAIL', "a wrong token got {$wrong['error']}, not http_401", $key);
		} else {
			$out[] = shopify_sandbox_result('PASS', "authenticated as {$s['name']} on API " . SHOPIFY_API_VERSION . ', a development store; a wrong token gets 401', $key);
		}
	}
	if ($ctx['target'] !== 'sandbox') {
		return $out;
	}
	$out[] = shopify_sandbox_k05_tenant($ctx);
	$out[] = shopify_sandbox_result('BLOCKED', 'the sandboxes are set up as the production shops (locations, metafield definitions, payment and shipping names, EasyRoutes, Shipmondo, hold rules): DTAILS\' delivery, Å11',
		null, 'DTAILS', 'setup');
	return $out;
}

/** K05 on the sandbox tenant: every shop it has is one of the configured sandboxes. */
function shopify_sandbox_k05_tenant(array $ctx): array
{
	if (!getenv('SALDI_SHOPIFY_PGUSER') || !getenv('SALDI_SHOPIFY_PGPASS')) {
		return shopify_sandbox_result('SKIP', 'SALDI_SHOPIFY_PGUSER/PGPASS are not set: the tenant\'s shops are not read', null, null, 'tenant');
	}
	try {
		$master = ShopifyEnv::connect(ShopifyEnv::masterDb());
		$exists = ShopifyEnv::one($master, 'SELECT 1 AS x FROM pg_database WHERE datname = $1', [$ctx['tenant']]) !== null;
		pg_close($master);
		if (!$exists) {
			return shopify_sandbox_result('SKIP', "no database {$ctx['tenant']} on " . ShopifyEnv::pgHost() . ': run K05 on the server that hosts the sandbox tenant', null, null, 'tenant');
		}
		$conn = ShopifyEnv::connect($ctx['tenant']);
		$domains = array_column(ShopifyEnv::rows($conn, 'SELECT domain FROM shopify_shops ORDER BY domain'), 'domain');
		pg_close($conn);
	} catch (Throwable $e) {
		return shopify_sandbox_result('SKIP', 'the tenant could not be read: ' . $e->getMessage(), null, null, 'tenant');
	}
	$sandboxes = array_column(array_column(array_filter($ctx['shops'], fn(array $s): bool => isset($s['row'])), 'row'), 'domain');
	$other = array_diff($domains, $sandboxes);
	return $other === [] ? shopify_sandbox_result('PASS', count($domains) . ' shops on the tenant, all configured sandboxes', null, null, 'tenant')
		: shopify_sandbox_result('FAIL', 'the tenant points at shops that are not configured sandboxes: ' . implode(', ', $other), null, null, 'tenant');
}

/** X01 on the fake: support/run_chain.php on the runner's cloned tenant. */
function shopify_sandbox_x01(array $ctx): array
{
	if (($ctx['db'] ?? null) === null) {
		return [shopify_sandbox_result('SKIP', 'no test tenant: ' . ($ctx['db_skip'] ?? 'not configured'))];
	}
	$run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_chain.php', [$ctx['db']]));
	if ($run['exit'] !== 0 || !isset($run['result']['results'])) {
		return [shopify_sandbox_result('FAIL', 'run_chain.php exited ' . $run['exit'] . ': ' . substr($run['stderr'] . $run['stdout'], 0, 500))];
	}
	return array_map(fn(array $r): array => shopify_sandbox_result($r['status'], $r['detail'], null, $r['owner'] ?? null, $r['step']), $run['result']['results']);
}

/**
 * A per-K script as a scenario: its arguments from env (SKIP naming the variable when one is
 * unset), its PASS/FAIL/SKIP/BLOCKED lines as results; a BLOCKED line without "(owner X)" gets
 * the scenario's owner.
 */
function shopify_sandbox_script(string $id, array $scenario, array $ctx): array
{
	$args = [$ctx['tenant']];
	foreach ($scenario['args'] as $name => $spec) {
		[$var, $default] = explode(':', $spec, 2) + [1 => ''];
		$value = (string)getenv($var) ?: $default;
		if ($value === '') {
			return [shopify_sandbox_result('SKIP', "$var is not set ($name for {$scenario['script']})")];
		}
		$args[] = $value;
	}
	$proc = proc_open(array_merge([PHP_BINARY, __DIR__ . '/' . $scenario['script']], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ShopifyEnv::repoRoot());
	$stdout = (string)stream_get_contents($pipes[1]);
	$stderr = (string)stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exit = proc_close($proc);
	$out = [];
	foreach (explode("\n", trim($stdout)) as $line) {
		if (preg_match('/^(K\d+)\s+(PASS|FAIL|SKIP|BLOCKED):\s*(.*)$/', $line, $m)) {
			$owner = preg_match('/\(owner ([^)]+)\)/', $m[3], $o) ? $o[1] : ($m[2] === 'BLOCKED' ? ($scenario['owner'] ?? null) : null);
			$out[] = shopify_sandbox_result($m[2], $m[3], null, $owner, $m[1] === $id ? null : $m[1]);
		}
	}
	return $out ?: [shopify_sandbox_result('FAIL', "{$scenario['script']} exited $exit without a result line: " . substr($stderr . $stdout, 0, 300))];
}
