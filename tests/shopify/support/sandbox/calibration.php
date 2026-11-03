<?php
// tests/shopify/support/sandbox/calibration.php
//
// Calibration mode of the sandbox runner (SD-766): the same documents against FakeShopifyStore
// and a real Danosoft dev store (credentials from env only), and a diff of the answers' shape:
// keys, value types, userError and error codes, never the values. A difference means the fake
// has drifted from Shopify; the probe names the FakeShopifyBehaviours entries it calibrates.
// Recorded pairs are sanitized: ids and cursors masked, personal fields and handles redacted;
// tokens are never part of an answer. The two mutation probes are refused by Shopify by design
// (26 metafields, a line-item owner); were one accepted, it writes probe metafields
// (namespace sd766_probe) on the dev store's shop, nothing else.
//
// History:
// 20261103 CL/LH SD-766: created.

require_once __DIR__ . '/../FakeShopifyStore.php';

/** Keys whose values are personal data or store handles: redacted in a recorded pair. */
const SHOPIFY_CALIBRATION_REDACT = ['email', 'phone', 'firstName', 'lastName', 'name', 'displayName', 'emailAddress', 'phoneNumber', 'address1',
	'address2', 'city', 'zip', 'myshopifyDomain', 'uri', 'url', 'title', 'handle', 'note', 'cursor', 'startCursor', 'endCursor', 'sku'];

/**
 * @return array<string, array{calibrates: list<string>, query: string, vars?: callable(array): array, mutation?: bool}>
 *   vars gets array{shop: string, location: string}: the shop's and its first location's GID on that side
 */
function shopify_calibration_probes(): array
{
	$metafields = 'mutation mf($m: [MetafieldsSetInput!]!) { metafieldsSet(metafields: $m) { metafields { id } userErrors { field message code elementIndex } } }';
	$probe = fn(string $owner, int $i): array => ['ownerId' => $owner, 'namespace' => 'sd766_probe', 'key' => "k$i", 'type' => 'single_line_text_field', 'value' => 'probe'];
	return [
		'shop' => ['calibrates' => ['B09', 'B24', 'B30'], 'query' => 'query { shop { id name myshopifyDomain currencyCode ianaTimezone plan { partnerDevelopment shopifyPlus publicDisplayName } } }'],
		'locations' => ['calibrates' => ['B19'], 'query' => 'query { locations(first: 5) { nodes { id name isActive fulfillsOnlineOrders } pageInfo { hasNextPage endCursor } } }'],
		'products' => ['calibrates' => ['B19', 'B22'], 'query' => 'query { products(first: 3) { nodes { id handle status variantsCount { count }
			variants(first: 2) { nodes { id sku inventoryPolicy inventoryItem { id tracked } } } } pageInfo { hasNextPage } } }'],
		'webhook_subscriptions' => ['calibrates' => ['B12'], 'query' => 'query { webhookSubscriptions(first: 10) { nodes { id topic uri } pageInfo { hasNextPage } } }'],
		'connection_without_first' => ['calibrates' => ['B19'], 'query' => 'query { products { nodes { id } } }'],
		'metafields_over_limit' => ['calibrates' => ['B06'], 'mutation' => true, 'query' => $metafields,
			'vars' => fn(array $ids): array => ['m' => array_map(fn(int $i): array => $probe($ids['shop'], $i), range(1, 26))]],
		'metafield_on_line_item' => ['calibrates' => ['B07'], 'mutation' => true, 'query' => $metafields,
			'vars' => fn(array $ids): array => ['m' => [$probe('gid://shopify/LineItem/1', 1)]]],
		'move_unknown_fulfillment_order' => ['calibrates' => ['B29'], 'mutation' => true,
			'query' => 'mutation m($id: ID!, $to: ID!) { fulfillmentOrderMove(id: $id, newLocationId: $to) { movedFulfillmentOrder { id } userErrors { field message code } } }',
			'vars' => fn(array $ids): array => ['id' => 'gid://shopify/FulfillmentOrder/1', 'to' => $ids['location']]],
	];
}

/**
 * Runs every probe on both sides and compares the shapes. A side is array{row: shop row,
 * opts: callable(): array of shopify_graphql() opts}.
 *
 * @return list<array{status: string, step: string, detail: string}> one result per probe
 */
function shopify_calibrate(array $fake, array $real, ?string $recordDir = null): array
{
	$answers = ['fake' => [], 'real' => []];
	foreach (['fake' => $fake, 'real' => $real] as $side => $s) {
		$ids = null;
		foreach (shopify_calibration_probes() as $name => $p) {
			if (isset($p['vars']) && $ids === null) {
				$ids = ['shop' => $answers[$side]['shop']['data']['shop']['id'] ?? 'gid://shopify/Shop/1',
					'location' => $answers[$side]['locations']['data']['locations']['nodes'][0]['id'] ?? 'gid://shopify/Location/1'];
			}
			$opts = $s['opts']() + (empty($p['mutation']) ? [] : ['retry_mode' => 'reconcile', 'reconcile' => fn(): array => ['state' => 'uncertain']]);
			$r = shopify_graphql($s['row'], "calibration_$name", $p['query'], isset($p['vars']) ? $p['vars']($ids) : [], $opts);
			$answers[$side][$name] = ['http' => ['code' => $r['status']], 'data' => $r['data'], 'errors' => array_map(fn(array $e): array => ['message' => $e['message'], 'code' => $e['code']], $r['errors'])];
		}
	}
	$out = [];
	foreach (shopify_calibration_probes() as $name => $p) {
		$diff = shopify_calibration_diff(shopify_calibration_shape($answers['fake'][$name]), shopify_calibration_shape($answers['real'][$name]));
		$out[] = ['status' => $diff === [] ? 'PASS' : 'FAIL', 'step' => $name,
			'detail' => ($diff === [] ? 'same shape' : implode('; ', array_slice($diff, 0, 5)) . (count($diff) > 5 ? '; ...' : '')) . ' (calibrates ' . implode(', ', $p['calibrates']) . ')'];
		if ($recordDir !== null) {
			file_put_contents("$recordDir/$name.json", json_encode(['probe' => $name, 'calibrates' => $p['calibrates'], 'query' => $p['query'], 'diff' => $diff,
				'fake' => shopify_calibration_sanitize($answers['fake'][$name]), 'real' => shopify_calibration_sanitize($answers['real'][$name])],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
		}
	}
	return $out;
}

/** The shape of an answer: value types, keys, the first element of a list; the values of code keys (HTTP status, error codes) kept. */
function shopify_calibration_shape(mixed $value, ?string $key = null): mixed
{
	if (is_array($value)) {
		if (array_is_list($value)) {
			return $value === [] ? [] : [shopify_calibration_shape($value[0])];
		}
		$out = [];
		foreach ($value as $k => $v) {
			$out[$k] = shopify_calibration_shape($v, (string)$k);
		}
		return $out;
	}
	if ($key === 'code') {
		return '=' . var_export($value, true);
	}
	return get_debug_type($value);
}

/** @return list<string> path: fake shape vs real shape, for every place they differ */
function shopify_calibration_diff(mixed $fake, mixed $real, string $path = '$'): array
{
	if (is_array($fake) && is_array($real)) {
		if (array_is_list($fake) && array_is_list($real)) {
			return $fake === [] || $real === [] ? [] : shopify_calibration_diff($fake[0], $real[0], "{$path}[0]");
		}
		$out = [];
		foreach (array_unique(array_merge(array_keys($fake), array_keys($real))) as $k) {
			if (!array_key_exists($k, $fake) || !array_key_exists($k, $real)) {
				$out[] = "$path.$k: " . (array_key_exists($k, $fake) ? 'only the fake has it' : 'only the real store has it');
				continue;
			}
			$out = array_merge($out, shopify_calibration_diff($fake[$k], $real[$k], "$path.$k"));
		}
		return $out;
	}
	if ($fake === $real) {
		return [];
	}
	$show = fn(mixed $s): string => is_array($s) ? 'object' : (string)$s;
	return ["$path: fake " . $show($fake) . ', real ' . $show($real)];
}

/** A recorded answer without ids, cursors, personal fields or handles. */
function shopify_calibration_sanitize(mixed $value, ?string $key = null): mixed
{
	if (is_array($value)) {
		$out = [];
		foreach ($value as $k => $v) {
			$out[$k] = shopify_calibration_sanitize($v, is_string($k) ? $k : $key);
		}
		return $out;
	}
	if (is_string($value) && $key !== null && in_array($key, SHOPIFY_CALIBRATION_REDACT, true)) {
		return '<redacted>';
	}
	return is_string($value) ? preg_replace('#(gid://shopify/\w+/)\d+#', '${1}N', $value) : $value;
}
