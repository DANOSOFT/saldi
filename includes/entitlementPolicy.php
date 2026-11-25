<?php
// --- includes/entitlementPolicy.php --- ver 5.1.1 --- 2026.11.25 ---
// LICENSE: GNU GPL v2 or later. Copyright (c) 2026 Danosoft ApS
// 20261125 CDX/ASR Resolve package policy from catalogue data, add-ons and explicit overrides.

/** @return array<string, mixed> Versioned seed data; runtime allowances come from the master catalogue. */
function ent_policy(): array
{
	static $policy;
	if ($policy === null) {
		$policy = json_decode(file_get_contents(__DIR__ . '/../config/entitlements.json'), true, 512, JSON_THROW_ON_ERROR);
	}
	return $policy;
}

/** @return string Canonical feature key, including aliases kept for existing operator overrides. */
function ent_feature_key(string $key): string
{
	return ent_policy()['aliases'][$key] ?? $key;
}

/**
 * Resolve an account without changing the connection or writing to the database.
 * A plan override is applied before add-ons, then explicit feature/limit overrides.
 * Expired allocations are filtered by the database reader.
 *
 * @param array|null $row regnskab_entitlements row.
 * @param array $catalog Catalogue belonging to this row's catalog_version_id.
 * @param array $addons Current add-on rows.
 * @param array $overrides Current override rows.
 * @return array{row: ?array, plan_key: ?string, plan: ?array, features: array, limits: array, addons: array, overrides: array, pending: array, price_ore: ?int, extra_user_price_ore: ?int, interval: string}
 */
function ent_resolve_policy(?array $row, array $catalog, array $addons, array $overrides): array
{
	$key = $row['plan_key'] ?? null;
	foreach ($overrides as $override) {
		if ($override['kind'] === 'plan' && isset($catalog['plans'][$override['value']])) {
			$key = $override['value'];
		}
	}
	$plan = $catalog['plans'][$key] ?? null;
	$out = array('row' => $row, 'plan_key' => $key, 'plan' => $plan, 'features' => array(), 'limits' => array(),
		'addons' => array(), 'overrides' => $overrides, 'pending' => array(), 'price_ore' => null,
		'extra_user_price_ore' => null, 'interval' => 'month');
	if ($plan === null) {
		return $out;
	}
	foreach ($catalog['features'][$key] as $feature => $level) {
		$out['features'][ent_feature_key($feature)] = $level;
	}
	foreach (array('included_users', 'max_users', 'included_postings', 'max_items') as $limit) {
		$out['limits'][$limit] = $plan[$limit] === null ? null : (int) $plan[$limit];
	}
	$out['limits']['max_webshops'] = (int) $plan['included_webshops'];
	foreach ($catalog['limits'][$key] ?? array() as $limit => $value) {
		$out['limits'][$limit] = $value === null ? null : (int) $value;
	}
	$out['price_ore'] = $plan['price_ore'] === null ? null : (int) $plan['price_ore'];
	$out['extra_user_price_ore'] = $plan['extra_user_price_ore'] === null ? null : (int) $plan['extra_user_price_ore'];
	$out['interval'] = $plan['billing_interval'];
	foreach ($overrides as $override) {
		if ($override['kind'] === 'addon') {
			$addons[] = array('addon_key' => $override['key'], 'quantity' => (int) $override['value'], 'source' => 'override', 'expires_at' => $override['expires_at']);
		}
	}
	foreach ($addons as $addon) {
		$definition = $catalog['addons'][$addon['addon_key']] ?? null;
		$out['addons'][$addon['addon_key']] = $addon;
		if ($definition === null || (int) $addon['quantity'] < 1) {
			continue;
		}
		if (($addon['source'] ?? '') !== 'override' && !in_array($key, explode(',', $definition['available_on']), true)) {
			continue;
		}
		$features = ent_policy()['addon_features'][$addon['addon_key']] ?? array($definition['feature_key']);
		foreach ($features as $feature) {
			$out['features'][ent_feature_key($feature)] = 'included';
		}
	}
	foreach ($overrides as $override) {
		if ($override['kind'] === 'feature') {
			$out['features'][ent_feature_key($override['key'])] = $override['value'] === 'on' ? 'included' : 'none';
		} elseif ($override['kind'] === 'limit') {
			$value = strtolower(trim((string) $override['value']));
			$out['limits'][$override['key']] = in_array($value, array('', '*', 'ubegrænset'), true) ? null : max(0, (int) $value);
		}
	}
	return $out;
}

/** @return bool Catalogue allowance, independent of observe/enforce and the user's role. */
function ent_policy_allows(array $state, string $feature, array $visited = array()): bool
{
	$feature = ent_feature_key($feature);
	if (empty($state['plan_key']) || in_array($feature, ent_policy()['protected'], true)) {
		return true;
	}
	if (isset($visited[$feature])) {
		return false;
	}
	$visited[$feature] = true;
	if (!in_array($state['features'][$feature] ?? 'none', array('included', 'basic'), true)) {
		return false;
	}
	foreach (ent_policy()['requires'][$feature] ?? array() as $required) {
		if (!ent_policy_allows($state, $required, $visited)) {
			return false;
		}
	}
	return true;
}
