<?php
// --- includes/structure/entitlementSchema.php --- ver 5.1.1 --- 2026.11.25 ---
// LICENSE: GNU GPL v2 or later. Copyright (c) 2026 Danosoft ApS
// 20261125 CDX/ASR Install the package policy and generic limits once, on the master, in observe mode.

include_once(__DIR__ . '/../database/compatibility.php');
include_once(__DIR__ . '/../entitlementPolicy.php');

/** @return void Execute a checked master statement; a partial seed must never set the readiness flag. */
function ent_schema_write(string $sql): void
{
	if (db_modify($sql, __FILE__ . ' linje ' . __LINE__, true) === false || !empty($GLOBALS['db_modify_fejl'])) {
		throw new RuntimeException('Entitlement migration statement failed');
	}
}

/**
 * Called only by betweenUpdates.php and the new-install path, after operator_panel_schema().
 * Keeps prices, account allocations and explicit overrides. Known package feature/limit rows
 * are updated once; subsequent edits in the database are never overwritten on a page request.
 *
 * @return bool True when this policy revision is installed.
 */
function entitlement_schema(): bool
{
	$revision = ent_policy()['revision'];
	$flag = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_grp = 'system' AND var_name = 'entitlement_policy'", __FILE__ . ' linje ' . __LINE__, true));
	if ($flag && $flag['var_value'] === $revision) return true;
	if (!operator_panel_ready() || !db_lock('entitlement_policy', 0, true)) return false;
	try {
		$flag = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_grp = 'system' AND var_name = 'entitlement_policy'", __FILE__ . ' linje ' . __LINE__, true));
		if ($flag && $flag['var_value'] === $revision) return true;
		// DDL is outside the data transaction: MySQL commits DDL implicitly.
		ent_schema_write('CREATE TABLE IF NOT EXISTS plan_limits (plan_id int NOT NULL, limit_key varchar(50) NOT NULL, limit_value bigint, PRIMARY KEY (plan_id, limit_key))');
		ent_schema_write('BEGIN');
		try {
			ent_schema_seed();
			// All existing package assignments begin with observation. Never enable enforcement in a migration.
			ent_schema_write("UPDATE regnskab_entitlements SET enforcement = 'observe'");
			ent_schema_write("DELETE FROM settings WHERE var_grp = 'system' AND var_name = 'entitlement_policy'");
			ent_schema_write("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('system', 'entitlement_policy', '" . db_escape_string($revision) . "')");
			ent_schema_write('COMMIT');
		} catch (Throwable $error) {
			db_modify('ROLLBACK', __FILE__ . ' linje ' . __LINE__, true);
			throw $error;
		}
		unset($GLOBALS['ent_ready'], $GLOBALS['ent_catalog_cache'], $GLOBALS['ent_resolve_cache'], $GLOBALS['ent_runtime_state']);
		return true;
	} finally {
		db_unlock('entitlement_policy', true);
	}
}

/** @return void Seed catalogue policy inside the migration's master transaction and lock. */
function ent_schema_seed(): void
{
	$data = ent_policy();
	$number = static function ($value): string { return $value === null ? 'NULL' : (string) (int) $value; };
	$cv = db_fetch_array(db_select('SELECT id FROM catalog_versions WHERE is_default ORDER BY id DESC LIMIT 1', __FILE__ . ' linje ' . __LINE__, true));
	if (!$cv) {
		ent_schema_write("INSERT INTO catalog_versions (name, valid_from, is_default) VALUES ('Pakker 2026-11-24', '2026-11-24', true)");
		$cv = db_fetch_array(db_select('SELECT id FROM catalog_versions WHERE is_default ORDER BY id DESC LIMIT 1', __FILE__ . ' linje ' . __LINE__, true));
	}
	$cid = (int) $cv['id'];
	foreach ($data['plans'] as $key => $plan) {
		$key = db_escape_string($key);
		if (!db_fetch_array(db_select("SELECT id FROM plans WHERE catalog_version_id = $cid AND plan_key = '$key'", __FILE__ . ' linje ' . __LINE__, true))) {
			ent_schema_write("INSERT INTO plans (catalog_version_id, plan_key, name, sort, price_ore, included_users, max_users, included_postings, max_items, included_webshops, extra_user_price_ore) VALUES ($cid, '$key', '" . db_escape_string($plan[0]) . "', " . (int) $plan[1] . ', ' . $number($plan[2]) . ', ' . (int) $plan[3] . ', ' . $number($plan[4]) . ', ' . $number($plan[5]) . ', ' . $number($plan[6]) . ', ' . (int) $plan[7] . ', ' . $number($plan[8]) . ')');
		}
	}
	$q = db_select('SELECT id, plan_key, catalog_version_id FROM plans ORDER BY id', __FILE__ . ' linje ' . __LINE__, true);
	$versions = array();
	while ($plan = db_fetch_array($q)) {
		$key = $plan['plan_key'];
		if (!isset($data['plans'][$key])) continue;
		$pid = (int) $plan['id'];
		$versions[(int) $plan['catalog_version_id']] = true;
		$index = array_search($key, array_keys($data['plans']), true) + 3;
		foreach ($data['features'] as $feature) {
			$name = db_escape_string($feature[0]);
			$level = array('✓' => 'included', '✗' => 'none', 'B' => 'basic', 'A' => 'addon')[$feature[$index]];
			ent_schema_write("DELETE FROM plan_features WHERE plan_id = $pid AND feature_key = '$name'");
			ent_schema_write("INSERT INTO plan_features (plan_id, feature_key, level) VALUES ($pid, '$name', '$level')");
		}
		foreach ($data['aliases'] as $alias => $canonical) {
			ent_schema_write("DELETE FROM plan_features WHERE plan_id = $pid AND feature_key = '" . db_escape_string($alias) . "'");
		}
		foreach ($data['limits'][$key] as $limit => $value) {
			if (in_array($limit, array('max_users', 'included_postings', 'max_items'), true)) continue;
			$limit = db_escape_string($limit);
			ent_schema_write("DELETE FROM plan_limits WHERE plan_id = $pid AND limit_key = '$limit'");
			ent_schema_write("INSERT INTO plan_limits (plan_id, limit_key, limit_value) VALUES ($pid, '$limit', " . $number($value) . ')');
		}
		// Preserve the existing operator UI's numeric fields as the authoritative values for these limits.
		$limits = $data['limits'][$key];
		ent_schema_write("UPDATE plans SET max_users = " . $number($limits['max_users']) . ', included_postings = ' . $number($limits['included_postings']) . ', max_items = ' . $number($limits['max_items']) . " WHERE id = $pid");
	}
	foreach (array_keys($versions) as $version) {
		if (!db_fetch_array(db_select("SELECT 1 FROM posting_tiers WHERE catalog_version_id = $version", __FILE__ . ' linje ' . __LINE__, true))) {
			foreach ($data['posting_tiers'] as $tier) {
				ent_schema_write("INSERT INTO posting_tiers (catalog_version_id, from_count, to_count, unit_centiore) VALUES ($version, " . (int) $tier[0] . ', ' . $number($tier[1]) . ', ' . (int) $tier[2] . ')');
			}
		}
		foreach ($data['addons'] as $key => $addon) {
			$key = db_escape_string($key);
			$feature = db_escape_string($addon[1]);
			$available = db_escape_string($addon[4]);
			if (db_fetch_array(db_select("SELECT id FROM addons WHERE catalog_version_id = $version AND addon_key = '$key'", __FILE__ . ' linje ' . __LINE__, true))) {
				ent_schema_write("UPDATE addons SET feature_key = '$feature', available_on = '$available' WHERE catalog_version_id = $version AND addon_key = '$key'");
			} else {
				ent_schema_write("INSERT INTO addons (catalog_version_id, addon_key, name, feature_key, quantity_based, available_on, price_ore) VALUES ($version, '$key', '" . db_escape_string($addon[0]) . "', '$feature', " . ($addon[2] ? 'true' : 'false') . ", '$available', " . $number($addon[3]) . ')');
			}
		}
	}
	// The old signup selection is only an input to this one-time migration, never a runtime fallback.
	$hasPackage = db_fetch_array(db_select("SELECT 1 FROM information_schema.columns WHERE table_name = 'regnskab' AND column_name = 'pakke'" . opdat_schema_filter(db_type()), __FILE__ . ' linje ' . __LINE__, true));
	if ($hasPackage) {
		ent_schema_write("INSERT INTO regnskab_entitlements (regnskab_id, catalog_version_id, plan_key, source, enforcement, subscription_status) SELECT r.id, p.catalog_version_id, p.plan_key, 'signup_migration', 'observe', 'none' FROM regnskab r JOIN plans p ON p.plan_key = r.pakke AND p.catalog_version_id = $cid WHERE NOT EXISTS (SELECT 1 FROM regnskab_entitlements e WHERE e.regnskab_id = r.id)");
	}
}
