<?php
// tests/shopify/support/sandbox/calibration.php
//
// Calibration mode of the sandbox runner (SD-766): the same documents against FakeShopifyStore
// and a real Danosoft dev store (credentials from env only), compared: first the outcome (success,
// userErrors or a refused request, with every error code), then the answers' shape (keys, value
// types, codes, never the values), then the requested cost. Only a resource collection may be
// empty on one side, as the stores hold different things. A difference means the fake has
// drifted from Shopify; the probe names the FakeShopifyBehaviours entries it calibrates. The
// result also carries the real store's throttle bucket, a plan-specific number (B10).
// Recorded pairs are sanitized: ids and cursors masked, personal fields and handles redacted;
// tokens are never part of an answer. The mutation probes are refused by Shopify by design
// (26 metafields, a line-item owner, an unknown fulfillment order, a stock set without its
// idempotency key on an unknown item); were the metafield ones accepted, they write probe
// metafields (namespace sd766_probe) on the dev store's shop, nothing else.
//
// History:
// 20261103 CL/LH SD-766: created.
// 20261103 CL/LH SD-766: review fixes: success against failure and every error code compared, the empty-list
//                wildcard only for resource collections, cost and throttle kept, a probe for a missing idempotency key.

require_once __DIR__ . '/../FakeShopifyStore.php';

/** Keys whose values are personal data or store handles: redacted in a recorded pair. */
const SHOPIFY_CALIBRATION_REDACT = ['email', 'phone', 'firstName', 'lastName', 'name', 'displayName', 'emailAddress', 'phoneNumber', 'address1',
	'address2', 'city', 'zip', 'myshopifyDomain', 'uri', 'url', 'title', 'handle', 'note', 'cursor', 'startCursor', 'endCursor', 'sku'];

/** Keys whose lists are errors: compared element by element, never as an empty-list wildcard. */
const SHOPIFY_CALIBRATION_ERROR_LISTS = ['errors', 'userErrors'];

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
		'inventory_set_without_key' => ['calibrates' => ['B21'], 'mutation' => true,
			'query' => 'mutation s($input: InventorySetQuantitiesInput!) { inventorySetQuantities(input: $input) { userErrors { field message code } } }',
			'vars' => fn(array $ids): array => ['input' => ['name' => 'available', 'reason' => 'correction',
				'quantities' => [['inventoryItemId' => 'gid://shopify/InventoryItem/1', 'locationId' => $ids['location'], 'quantity' => 0, 'changeFromQuantity' => null]]]]],
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
			$answers[$side][$name] = ['http' => ['code' => $r['status']], 'data' => $r['data'],
				'errors' => array_map(fn(array $e): array => ['message' => $e['message'], 'code' => $e['code']], $r['errors']), 'cost' => $r['cost']];
		}
	}
	$out = [];
	foreach (shopify_calibration_probes() as $name => $p) {
		$diff = shopify_calibration_compare($answers['fake'][$name], $answers['real'][$name]);
		$bucket = $answers['real'][$name]['cost']['throttleStatus'] ?? null;
		$out[] = ['status' => $diff === [] ? 'PASS' : 'FAIL', 'step' => $name,
			'detail' => ($diff === [] ? 'same outcome and shape' : implode('; ', array_slice($diff, 0, 5)) . (count($diff) > 5 ? '; ...' : '')) . ' (calibrates ' . implode(', ', $p['calibrates']) . ')'
				. ($name === 'shop' && is_array($bucket) ? sprintf('; real bucket %s, restore %s/s (B10, plan-specific)', $bucket['maximumAvailable'] ?? '?', $bucket['restoreRate'] ?? '?') : '')];
		if ($recordDir !== null) {
			file_put_contents("$recordDir/$name.json", json_encode(['probe' => $name, 'calibrates' => $p['calibrates'], 'query' => $p['query'], 'diff' => $diff,
				'fake' => shopify_calibration_sanitize($answers['fake'][$name]), 'real' => shopify_calibration_sanitize($answers['real'][$name])],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
		}
	}
	return $out;
}

/**
 * Fake against real for one probe: the outcome first (success, userErrors or a refused request,
 * with all their codes), then the shapes, then the requested cost (B09).
 *
 * @param array{http: array, data: mixed, errors: list<array>, cost: ?array} $fake
 * @param array{http: array, data: mixed, errors: list<array>, cost: ?array} $real
 * @return list<string> one line per difference
 */
function shopify_calibration_compare(array $fake, array $real): array
{
	[$a, $b] = [shopify_calibration_outcome($fake), shopify_calibration_outcome($real)];
	$out = $a === $b ? [] : ["outcome: fake $a, real $b"];
	$out = array_merge($out, shopify_calibration_diff(shopify_calibration_shape($fake), shopify_calibration_shape($real)));
	$cost = [$fake['cost']['requestedQueryCost'] ?? null, $real['cost']['requestedQueryCost'] ?? null];
	if ($cost[0] !== null && $cost[1] !== null && (int)$cost[0] !== (int)$cost[1]) {
		$out[] = "requested cost: fake {$cost[0]}, real {$cost[1]} (B09)";
	}
	return $out;
}

/** "ok", "userErrors CODE, ..." or "refused CODE, ..." (each code once, sorted): whether Shopify did it, and why not. */
function shopify_calibration_outcome(array $answer): string
{
	$codes = function (array $errors): string {
		$list = array_values(array_unique(array_map(fn(array $e): string => (string)($e['code'] ?? 'null'), $errors)));
		sort($list);
		return implode(', ', $list);
	};
	if ($answer['errors'] !== []) {
		return 'refused ' . $codes($answer['errors']);
	}
	$userErrors = [];
	$walk = function (mixed $value) use (&$walk, &$userErrors): void {
		if (!is_array($value)) {
			return;
		}
		foreach ($value as $k => $v) {
			if ($k === 'userErrors' && is_array($v)) {
				$userErrors = array_merge($userErrors, $v);
			} else {
				$walk($v);
			}
		}
	};
	$walk($answer['data']);
	return $userErrors === [] ? 'ok' : 'userErrors ' . $codes($userErrors);
}

/**
 * The shape of an answer: keys and value types (a number is a number, int or float), the first
 * element of a resource list, every distinct element of an error list; the values of code keys
 * (HTTP status, error codes) kept.
 */
function shopify_calibration_shape(mixed $value, ?string $key = null): mixed
{
	if (is_array($value)) {
		if (array_is_list($value)) {
			if (in_array($key, SHOPIFY_CALIBRATION_ERROR_LISTS, true)) {
				$shapes = array_values(array_unique(array_map(fn(mixed $e): string => json_encode(shopify_calibration_shape($e)), $value)));
				sort($shapes);
				return ['errors' => array_map(fn(string $e): mixed => json_decode($e, true), $shapes)];
			}
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
	return is_int($value) || is_float($value) ? 'number' : get_debug_type($value);
}

/**
 * @return list<string> path: fake shape vs real shape, for every place they differ; an empty
 *   resource list on either side matches, an empty error list only an empty one
 */
function shopify_calibration_diff(mixed $fake, mixed $real, string $path = '$'): array
{
	if (is_array($fake) && is_array($real)) {
		$errorList = fn(array $shape): bool => array_keys($shape) === ['errors'] && array_is_list($shape['errors']);
		if (!array_is_list($fake) && !array_is_list($real) && $errorList($fake) && $errorList($real)) {
			$show = fn(array $list): string => $list === [] ? 'none' : implode(' ', array_map(fn(mixed $e): string => json_encode($e, JSON_UNESCAPED_SLASHES), $list));
			$onlyFake = array_values(array_filter($fake['errors'], fn(mixed $e): bool => !in_array($e, $real['errors'], true)));
			$onlyReal = array_values(array_filter($real['errors'], fn(mixed $e): bool => !in_array($e, $fake['errors'], true)));
			return $onlyFake === [] && $onlyReal === [] ? [] : ["$path: fake " . $show($fake['errors']) . ', real ' . $show($real['errors'])];
		}
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
