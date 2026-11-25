<?php
// --- includes/entitlements.php --- ver 5.1.1 --- 2026.11.25 ---
// LICENSE: GNU GPL v2 or later; see admin/vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Catalogue + entitlement layer in the master database, tables as in Requirements_packages_entitlements_billing_EN.md §4.1/§5.1 with three additions needed for the operator's subscription card: prices on plans/addons (price_ore, billing_interval, extra_user_price_ore), valid_from on regnskab_entitlements, and regnskab_plan_changes for changes with a future effective date.
//                 Source is 'manual' until the plan is synced from the billing ledger's recurring invoicing order (payment model B1, 12/10-2026: "Saldi invoices, Stripe collects" – the recurring order is the truth for the plan; spec §6's per-installation Stripe webhook is dropped); the resolver (§5.2) is the same either way.
//                 Every change is written to partner_log as entitlement.* (the stage 2 audit_log mirrors it when it exists).
// 20261029 CL/ASR ent_tables_ensure() no longer creates tables (update chain); it only seeds the catalogue.
// 20261109 CL/ASR New ent_accept_addon() for accepted proposals: it adds one unit to a counted add-on the account already has (expiry kept) and leaves an on/off add-on as it is, where ent_set_addon() reset it to quantity 1 without expiry (review #785).
//                 ent_tables_ensure() does nothing on a MySQL/MariaDB master, and the information_schema look-ups are limited to current_schema().
//                 An accepted proposal for an on/off add-on the account has only for a limited time (e.g. a trial) removes the end date, so the bought add-on stays.
//                 ent_tables_ensure() seeds the catalogue only when operator_panel_ready() says the schema run finished (flag at the current version).
//                 ent_set_addon() stores an on/off add-on with quantity 1, whatever quantity the form sends (review #785).
//                 New ent_addon_purchase_error(), checked when a proposal is created and again when it is accepted: the add-on must be on sale for the account's effective plan, and a counted add-on with a time-limited allocation is refused, because a bought unit would share that row's end date (review #785).
//                 ent_usage_collect() reads column names and the type of brugere.status from pg_attribute for the relation the unqualified table name resolves to (to_regclass), the same table the counts read (review #785).
// 20261113 CL/ASR Accepting an add-on proposal and setting or removing the add-on on the Abonnement tab now wait for each other (ent_addon_locked()), so a bought unit cannot pick up an end date that another operator sets in between (review #785).
// 20261116 CL/ASR ent_seed() runs in one transaction under an advisory lock and looks again once it holds it, so two first page loads cannot seed the catalogue twice and a failure halfway leaves no partial catalogue; the new ids come from RETURNING (review #785).
// 20261116 CL/ASR MySQL/MariaDB masters are supported too (review #785, SirRolin): the seed and the add-on writers lock through db_locked(), which releases a MySQL/MariaDB lock in a finally, and new ids come from db_insert_id().
//                 The usage count reads column names and the type of brugere.status from information_schema on MySQL/MariaDB, and months come from DATE_FORMAT() there instead of to_char().
//                 ent_usage_history() picks the last snapshot per month in PHP instead of DISTINCT ON, and the column key is quoted on MySQL/MariaDB (db_key_column()).
// 20261124 CDX/NTR Use shared database compatibility helpers and the operator-panel structure include.
// 20261125 CDX/ASR Resolve catalogue-backed package policy on the master and keep numeric limits editable as data.

if (!function_exists('ent_tables_ensure')) {
include_once(__DIR__ . '/database/compatibility.php');

include_once(__DIR__ . '/entitlementPolicy.php');
include_once(__DIR__ . '/entitlementRuntime.php');
include_once(__DIR__ . '/entitlementRoutes.php');

/** @return array<string, array> Seed plans; never use these values to decide a customer's access. */
function ent_plan_defs() { return ent_policy()['plans']; }
/** @return array<int, array> Catalogue feature labels and initial levels. */
function ent_feature_defs() { return ent_policy()['features']; }
/** @return array<string, array> Catalogue add-on seed data. */
function ent_addon_defs() { return ent_policy()['addons']; }

function ent_tables_ensure() {
	# 20261029 CL/ASR The tables come from the update chain (includes/structure/operatorPanelSchema.php via betweenUpdates.php).
	static $done = false; if ($done) return; $done = true;
	include_once(__DIR__ . '/structure/operatorPanelSchema.php');
	if (operator_panel_ready()) ent_seed(); # catalogue rows on first use (data, not schema), only after a finished schema run
}
function ent_seed() {
	if (db_fetch_array(db_select("select 1 from catalog_versions", __FILE__ . " linje " . __LINE__))) return;
	db_locked('catalog_seed', function () {
		if (db_fetch_array(db_select("select 1 from catalog_versions", __FILE__ . " linje " . __LINE__))) return ''; # seeded while we waited
		$cv = db_insert_id("insert into catalog_versions (name, valid_from, is_default) values ('Priser 2026-03-01', '2026-03-01', true)", __FILE__ . " linje " . __LINE__);
		$n = fn($v) => $v === null ? 'NULL' : (int)$v;
		$idx = 3;
		foreach (ent_plan_defs() as $k => $d) {
			$pid = db_insert_id("insert into plans (catalog_version_id, plan_key, name, sort, included_users, max_users, included_postings, max_items, included_webshops, price_ore, extra_user_price_ore) values ('$cv', '$k', '".db_escape_string($d[0])."', '$d[1]', '$d[3]', ".$n($d[4]).", ".$n($d[5]).", ".$n($d[6]).", ".(int)$d[7].", ".$n($d[2]).", ".$n($d[8]).")", __FILE__ . " linje " . __LINE__);
			foreach (ent_feature_defs() as $f) {
				$lv = array('✓' => 'included', '✗' => 'none', 'B' => 'basic', 'A' => 'addon');
				db_modify("insert into plan_features (plan_id, feature_key, level) values ('$pid', '$f[0]', '".$lv[$f[$idx]]."')", __FILE__ . " linje " . __LINE__);
			}
			$idx++;
		}
		foreach (ent_addon_defs() as $k => $d) db_modify("insert into addons (catalog_version_id, addon_key, name, feature_key, quantity_based, available_on, price_ore) values ('$cv', '$k', '".db_escape_string($d[0])."', '$d[1]', ".($d[2] ? 'true' : 'false').", '$d[4]', ".$n($d[3]).")", __FILE__ . " linje " . __LINE__);
		foreach (ent_policy()['posting_tiers'] as $t) db_modify("insert into posting_tiers (catalog_version_id, from_count, to_count, unit_centiore) values ('$cv', '$t[0]', ".$n($t[1]).", '$t[2]')", __FILE__ . " linje " . __LINE__);
		return '';
	});
}

/** @return array{version: mixed, plans: array, features: array, limits: array, addons: array, feature_names: array} */
function ent_catalog($versionId = null) {
	$slot = $versionId === null ? 'default' : (int) $versionId;
	if (isset($GLOBALS['ent_catalog_cache'][$slot])) return $GLOBALS['ent_catalog_cache'][$slot];
	$condition = $versionId === null ? 'is_default' : 'id = ' . (int) $versionId;
	$cv = db_fetch_array(db_select("select * from catalog_versions where $condition order by id desc limit 1", __FILE__ . " linje " . __LINE__, true));
	$c = array('version' => $cv, 'plans' => array(), 'features' => array(), 'limits' => array(), 'addons' => array(), 'feature_names' => array());
	if (!$cv) return $c;
	$cid = (int) $cv['id'];
	$q = db_select("select * from plans where catalog_version_id = $cid order by sort", __FILE__ . " linje " . __LINE__, true);
	while ($r = db_fetch_array($q)) {
		$c['plans'][$r['plan_key']] = $r;
		$c['features'][$r['plan_key']] = array();
	}
	foreach ($c['plans'] as $k => $p) {
		$pid = (int) $p['id'];
		$q = db_select("select feature_key, level from plan_features where plan_id = $pid", __FILE__ . " linje " . __LINE__, true);
		while ($r = db_fetch_array($q)) $c['features'][$k][$r['feature_key']] = $r['level'];
		if (ent_runtime_ready()) {
			$q = db_select("select limit_key, limit_value from plan_limits where plan_id = $pid", __FILE__ . " linje " . __LINE__, true);
			while ($r = db_fetch_array($q)) $c['limits'][$k][$r['limit_key']] = $r['limit_value'];
		}
	}
	$q = db_select("select * from addons where catalog_version_id = $cid order by id", __FILE__ . " linje " . __LINE__, true);
	while ($r = db_fetch_array($q)) $c['addons'][$r['addon_key']] = $r;
	foreach (ent_feature_defs() as $f) $c['feature_names'][$f[0]] = array($f[1], $f[2]);
	return $GLOBALS['ent_catalog_cache'][$slot] = $c;
}
function ent_kr($ore) { return $ore === null ? null : number_format($ore / 100, 0, ',', '.').' kr.'; }
function ent_log($action, $rid, $details = '') {
	if ($rid) ent_resolve_flush($rid); # every entitlement change is logged, so the cached resolution is dropped here
	if (($GLOBALS['db'] ?? '') === ($GLOBALS['sqdb'] ?? '') && function_exists('partner_log')) {
		partner_log($action, $rid, null, $details);
	} else {
		db_modify("INSERT INTO partner_log (regnskab_id, action, details) VALUES (" . (int) $rid . ", '" . db_escape_string($action) . "', '" . db_escape_string($details) . "')", __FILE__ . ' linje ' . __LINE__, true);
	}
}
function ent_actor() { global $brugernavn; if (function_exists('partner_current_user')) { $u = partner_current_user(); if (!empty($u['brugernavn'])) return (string)$u['brugernavn']; } return (string)$brugernavn; } # the logged-in user, also on operator_opret.php where \$brugernavn is reused for the new admin

// Apply plan changes whose effective date has arrived (lazy, on read and on the nightly job).
/** Apply due operator changes on the master connection. @return void */
function ent_apply_due_changes($rid) {
	$rid = (int) $rid;
	$q = db_select("select * from regnskab_plan_changes where regnskab_id = $rid and applied_at is null and cancelled_at is null and effective_from <= current_date order by effective_from, id", __FILE__ . " linje " . __LINE__, true);
	while ($r = db_fetch_array($q)) {
		ent_write_plan($rid, $r['plan_key'], $r['effective_from'], 'scheduled');
		db_modify("update regnskab_plan_changes set applied_at = now() where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__, true);
		ent_log('entitlement.plan_applied', $rid, $r['plan_key'] . ' fra ' . $r['effective_from']);
	}
}

/** Write the single authoritative package assignment. @return void */
function ent_write_plan($rid, $plan_key, $valid_from, $source = 'manual') {
	ent_resolve_flush($rid);
	$c = ent_catalog();
	if (!isset($c['plans'][$plan_key])) throw new InvalidArgumentException('Unknown entitlement plan');
	$cv = (int) $c['version']['id'];
	$rid = (int) $rid;
	$pk = db_escape_string($plan_key);
	$vf = db_escape_string($valid_from);
	$src = db_escape_string($source);
	if (db_fetch_array(db_select("select 1 from regnskab_entitlements where regnskab_id = $rid", __FILE__ . " linje " . __LINE__, true))) {
		db_modify("update regnskab_entitlements set plan_key = '$pk', catalog_version_id = $cv, source = '$src', valid_from = '$vf', updated_at = now() where regnskab_id = $rid", __FILE__ . " linje " . __LINE__, true);
	} else {
		db_modify("insert into regnskab_entitlements (regnskab_id, catalog_version_id, plan_key, source, valid_from, subscription_status, enforcement) values ($rid, $cv, '$pk', '$src', '$vf', 'none', 'observe')", __FILE__ . " linje " . __LINE__, true);
	}
}

/** @return void Invalidate the resolver and any current runtime snapshot for this account. */
function ent_resolve_flush($rid = null) {
	if ($rid === null) $GLOBALS['ent_resolve_cache'] = array();
	else unset($GLOBALS['ent_resolve_cache'][(int) $rid]);
	if ($rid === null || (int) ($GLOBALS['ent_runtime_id'] ?? 0) === (int) $rid) unset($GLOBALS['ent_runtime_state']);
}

/** @return array Resolved package, allocations and limits for a master regnskab id. */
function ent_resolve($rid) {
	$rid = (int) $rid;
	if (isset($GLOBALS['ent_resolve_cache'][$rid])) return $GLOBALS['ent_resolve_cache'][$rid];
	ent_apply_due_changes($rid);
	$e = db_fetch_array(db_select("select * from regnskab_entitlements where regnskab_id = $rid", __FILE__ . " linje " . __LINE__, true));
	$c = ent_catalog($e && $e['catalog_version_id'] !== null ? (int) $e['catalog_version_id'] : null);
	$addons = $overrides = array();
	$q = db_select("select * from regnskab_addons where regnskab_id = $rid and (expires_at is null or expires_at >= current_date)", __FILE__ . " linje " . __LINE__, true);
	while ($r = db_fetch_array($q)) $addons[] = $r;
	$q = db_select("select * from regnskab_overrides where regnskab_id = $rid and (expires_at is null or expires_at >= current_date) order by id", __FILE__ . " linje " . __LINE__, true);
	while ($r = db_fetch_array($q)) $overrides[] = $r;
	$out = ent_resolve_policy($e ?: null, $c, $addons, $overrides);
	$q = db_select("select * from regnskab_plan_changes where regnskab_id = $rid and applied_at is null and cancelled_at is null order by effective_from, id", __FILE__ . " linje " . __LINE__, true);
	while ($r = db_fetch_array($q)) $out['pending'][] = $r;
	return $GLOBALS['ent_resolve_cache'][$rid] = $out;
}

function ent_plan_name($plan_key) { $c = ent_catalog(); return isset($c['plans'][$plan_key]) ? $c['plans'][$plan_key]['name'] : ($plan_key ?: null); }

// Plans for all ledgers in one query (for lists): regnskab_id => plan_key
function ent_plans_by_ledger() {
	ent_tables_ensure(); $out = array();
	$q = db_select("select regnskab_id, plan_key from regnskab_entitlements", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $out[(int)$r['regnskab_id']] = $r['plan_key'];
	return $out;
}

// ---------- operator actions ----------
// Downgrade guard (§7.1.5): compare live usage with the target plan; returns list of conflict texts.
function ent_conflicts($target_key, $usage) {
	$c = ent_catalog(); if (!isset($c['plans'][$target_key])) return array('Ukendt pakke');
	$p = $c['plans'][$target_key]; $out = array();
	// The package limits concurrent sessions, never the number of user records.
	if ($p['max_items'] !== null && isset($usage['items']) && $usage['items'] > (int)$p['max_items']) $out[] = "Regnskabet har ".number_format($usage['items'],0,',','.')." varer – {$p['name']} tillader ".number_format((int)$p['max_items'],0,',','.').".";
	if ($p['included_postings'] !== null && isset($usage['postings_12m']) && $usage['postings_12m'] > (int)$p['included_postings']) $out[] = "Posteringer de seneste 12 måneder (".number_format($usage['postings_12m'],0,',','.').") overstiger det inkluderede i {$p['name']} (".number_format((int)$p['included_postings'],0,',','.')."). Forbruget derover afregnes efter posteringstrappen.";
	return $out;
}
function ent_set_plan($rid, $plan_key, $effective_from, $reason) {
	$c = ent_catalog(); if (!isset($c['plans'][$plan_key])) return 'Ukendt pakke';
	$rid = (int)$rid; $ef = $effective_from ?: date('Y-m-d');
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ef)) return 'Ugyldig dato';
	if ($ef <= date('Y-m-d')) {
		$old = ent_resolve($rid); ent_write_plan($rid, $plan_key, $ef, 'manual');
		ent_log('entitlement.plan_changed', $rid, ($old['plan_key'] ? ent_plan_name($old['plan_key']) : '–').' → '.ent_plan_name($plan_key).' fra '.$ef.($reason ? ' – '.$reason : ''));
	} else {
		db_modify("insert into regnskab_plan_changes (regnskab_id, plan_key, effective_from, reason, created_by) values ('$rid', '".db_escape_string($plan_key)."', '$ef', '".db_escape_string($reason)."', '".db_escape_string(ent_actor())."')", __FILE__ . " linje " . __LINE__);
		ent_log('entitlement.plan_scheduled', $rid, ent_plan_name($plan_key).' fra '.$ef.($reason ? ' – '.$reason : ''));
	}
	return '';
}
function ent_cancel_change($rid, $id) {
	$r = db_fetch_array(db_select("select * from regnskab_plan_changes where id = '".(int)$id."' and regnskab_id = '".(int)$rid."' and applied_at is null and cancelled_at is null", __FILE__ . " linje " . __LINE__));
	if (!$r) return;
	db_modify("update regnskab_plan_changes set cancelled_at = now() where id = '".(int)$id."'", __FILE__ . " linje " . __LINE__);
	ent_log('entitlement.plan_change_cancelled', (int)$rid, ent_plan_name($r['plan_key']).' fra '.$r['effective_from']);
}
// 20261113 CL/ASR Runs $fn under a transaction-scoped advisory lock on (account, add-on), so the add-on writers below wait
//                  for each other, also while the account has no row for the add-on yet (review #785). Inside an open
//                  transaction (fs_decide()) the lock is held until that transaction ends. Otherwise the call gets its own
//                  transaction, which also makes the delete and insert in ent_set_addon() one step. $fn returns '' or an
//                  error, and an error rolls its own transaction back.
// 20261116 CL/ASR Through db_locked(): on MySQL/MariaDB a GET_LOCK() taken before the transaction and released after it; fs_decide() takes the same lock around its own transaction there.
function ent_addon_lock_name($rid, $addon_key) { return 'regnskab_addons:' . (int)$rid . ':' . $addon_key; }
function ent_addon_locked($rid, $addon_key, $fn) {
	return db_locked(ent_addon_lock_name($rid, $addon_key), $fn);
}
function ent_set_addon($rid, $addon_key, $qty, $expires) {
	$c = ent_catalog(); if (!isset($c['addons'][$addon_key])) return 'Ukendt tillæg';
	return ent_addon_locked($rid, $addon_key, function () use ($c, $rid, $addon_key, $qty, $expires) {
		$rid = (int)$rid; $qty = ent_addon_counted($c['addons'][$addon_key]) ? max(1, (int)$qty) : 1; $ex = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$expires) ? "'$expires'" : 'NULL'; # an on/off add-on is one unit, whatever quantity the form sends
		db_modify("delete from regnskab_addons where regnskab_id = '$rid' and addon_key = '".db_escape_string($addon_key)."'", __FILE__ . " linje " . __LINE__);
		db_modify("insert into regnskab_addons (regnskab_id, addon_key, quantity, source, expires_at) values ('$rid', '".db_escape_string($addon_key)."', '$qty', 'manual', $ex)", __FILE__ . " linje " . __LINE__);
		ent_log('entitlement.addon_set', $rid, $c['addons'][$addon_key]['name'].' × '.$qty.($ex != 'NULL' ? ' indtil '.$expires : ''));
		return '';
	});
}
function ent_addon_counted($a) { return in_array((string)($a['quantity_based'] ?? ''), array('t', 'true', '1'), true); } # pg returns booleans as 't'/'f', and 'f' is truthy in PHP
// 20261111 CL/ASR Why buying $addon_key cannot be proposed or accepted now ('' = it can). fs_create() checks it, and
//                  ent_accept_addon() checks it again, because the plan can change while a proposal is open (review #785).
//                  The add-on must be on sale for the account's effective plan (a plan override such as a trial counts),
//                  the same rule as the add-on list on the Abonnement tab. A counted add-on must not be allocated for a
//                  limited time: regnskab_addons holds one row per add-on, so a bought unit would get the allocation's
//                  end date and disappear with it. The operator first makes the allocation permanent or lets it expire.
function ent_addon_purchase_error($rid, $addon_key) {
	$c = ent_catalog(); if (!isset($c['addons'][$addon_key])) return 'Ukendt tillæg';
	$a = $c['addons'][$addon_key]; $e = ent_resolve($rid);
	if (!$e['plan']) return vr_t('Tillægget kan ikke købes, før regnskabet har en pakke.','The add-on cannot be bought before the account has a plan.');
	if (!in_array($e['plan']['plan_key'], explode(',', (string)$a['available_on']), true)) return vr_t('Tillægget kan ikke købes på pakken','The add-on cannot be bought on the plan').' '.$e['plan']['name'].'.';
	if (ent_addon_counted($a)) {
		$tmp = db_fetch_array(db_select("select expires_at from regnskab_addons where regnskab_id = '".(int)$rid."' and addon_key = '".db_escape_string($addon_key)."' and expires_at >= current_date", __FILE__ . " linje " . __LINE__));
		if ($tmp) return vr_t('Tillægget er tildelt midlertidigt til','The add-on is allocated temporarily until').' '.date('d-m-Y', strtotime($tmp['expires_at'])).', '.vr_t('og en købt enhed ville udløbe samme dag. Det kan købes, når tildelingen er gjort fast eller er udløbet.','and a bought unit would expire on the same day. It can be bought once the allocation is made permanent or has expired.');
	}
	return '';
}
// 20261109 CL/ASR An accepted proposal buys one more unit of a counted add-on (quantity + 1, expiry kept); an on/off add-on
//                  the account already has stays as it is. Without an active row it is added as quantity 1 (review #785).
// 20261111 CL/ASR ent_addon_purchase_error() runs first, so a counted add-on reaching quantity + 1 has no end date (review #785).
// 20261113 CL/ASR The check and the update run under ent_addon_locked(), so no allocation can be set between them (review #785).
function ent_accept_addon($rid, $addon_key) {
	$c = ent_catalog(); if (!isset($c['addons'][$addon_key])) return 'Ukendt tillæg';
	return ent_addon_locked($rid, $addon_key, function () use ($c, $rid, $addon_key) {
		if ($err = ent_addon_purchase_error($rid, $addon_key)) return $err;
		$rid = (int)$rid; $ak = db_escape_string($addon_key); $a = $c['addons'][$addon_key];
		$cur = db_fetch_array(db_select("select quantity, expires_at from regnskab_addons where regnskab_id = '$rid' and addon_key = '$ak' and (expires_at is null or expires_at >= current_date)", __FILE__ . " linje " . __LINE__));
		if (!$cur) return ent_set_addon($rid, $addon_key, 1, ''); # none, or only an expired row: replaced by quantity 1
		if (!ent_addon_counted($a)) { # on/off: already there; a time-limited one (e.g. a trial) is bought and loses its end date
			if ($cur['expires_at']) { db_modify("update regnskab_addons set expires_at = NULL where regnskab_id = '$rid' and addon_key = '$ak'", __FILE__ . " linje " . __LINE__); ent_log('entitlement.addon_set', $rid, $a['name'].' uden slutdato'); }
			return '';
		}
		db_modify("update regnskab_addons set quantity = quantity + 1 where regnskab_id = '$rid' and addon_key = '$ak'", __FILE__ . " linje " . __LINE__);
		ent_log('entitlement.addon_set', $rid, $a['name'].' × '.((int)$cur['quantity'] + 1).($cur['expires_at'] ? ' indtil '.$cur['expires_at'] : ''));
		return '';
	});
}
function ent_remove_addon($rid, $addon_key) {
	ent_addon_locked($rid, $addon_key, function () use ($rid, $addon_key) {
		$c = ent_catalog(); db_modify("delete from regnskab_addons where regnskab_id = '".(int)$rid."' and addon_key = '".db_escape_string($addon_key)."'", __FILE__ . " linje " . __LINE__);
		ent_log('entitlement.addon_removed', (int)$rid, isset($c['addons'][$addon_key]) ? $c['addons'][$addon_key]['name'] : $addon_key);
		return '';
	});
}
// 20261012 CL/ASR Validation split out so callers that replace an active override can check BEFORE expiring the old one
//                  (an invalid replacement used to remove the active override). The grandfather/overgangsordning
//                  exemption from the expiry date now applies to plan overrides only (the parentheses were wrong).
// 20261013 CL/ASR Expiry must be a real date, today or later; a plan override also carries its plan's price (review #785).
function ent_override_error($kind, $reason, $expires) {
	if (!in_array($kind, array('plan','feature','limit','addon'), true)) return 'Ugyldig type';
	if (trim((string)$reason) === '') return 'Begrundelse mangler';
	$hasEx = (bool)preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$expires, $m);
	if (!$hasEx && trim((string)$expires) !== '') return 'Ugyldig udløbsdato'; # a malformed date is an error, not "no expiry"
	if ($hasEx && !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return 'Ugyldig udløbsdato';
	if ($hasEx && $expires < date('Y-m-d')) return 'Udløbsdatoen er overskredet – vælg i dag eller senere';
	if (!$hasEx && !($kind == 'plan' && (stripos($reason, 'grandfather') !== false || stripos($reason, 'overgangsordning') !== false))) return 'Udløbsdato er påkrævet (undtagen overgangsordning)';
	return '';
}
function ent_add_override($rid, $kind, $key, $value, $reason, $expires) {
	if ($e = ent_override_error($kind, $reason, $expires)) return $e;
	$ex = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$expires) ? "'$expires'" : 'NULL';
	db_modify("insert into regnskab_overrides (regnskab_id, kind, ".db_key_column().", value, reason, expires_at, created_by) values ('".(int)$rid."', '$kind', '".db_escape_string($key)."', '".db_escape_string($value)."', '".db_escape_string($reason)."', $ex, '".db_escape_string(ent_actor())."')", __FILE__ . " linje " . __LINE__);
	ent_log('entitlement.override_added', (int)$rid, "$kind $key = $value".($ex != 'NULL' ? " indtil $expires" : '').' – '.$reason);
	return '';
}
function ent_expire_override($rid, $id) {
	$r = db_fetch_array(db_select("select * from regnskab_overrides where id = '".(int)$id."' and regnskab_id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__));
	if (!$r) return;
	db_modify("update regnskab_overrides set expires_at = '".date('Y-m-d', strtotime('-1 day'))."' where id = '".(int)$id."'", __FILE__ . " linje " . __LINE__);
	ent_log('entitlement.override_expired', (int)$rid, "$r[kind] $r[key] = $r[value]");
}

// ---------- usage (area 2 "Forbrug og begrænsninger") ----------
// Live measurement in the customer database, rules from the entitlements spec §2.3/§5.6.
function ent_usage_collect($reg) {
	global $sqhost, $squser, $sqpass;
	$u = array('ok' => false, 'users_active' => null, 'users_concurrent' => null, 'users_total' => null, 'postings_12m' => null, 'postings_month' => null, 'postings_by_month' => array(), 'items' => null, 'webshops' => null, 'pos_tills' => null, 'pos_locations' => null, 'warehouses' => null, 'invoices_12m' => null, 'last_activity' => null, 'modules' => array());
	if (!$reg['db'] || !db_exists($reg['db'])) return $u;
	if (!db_connect_database($reg['db'], __FILE__ . " linje " . __LINE__)) return $u;
	$u['ok'] = true;
	$u['users_concurrent'] = ent_concurrent_users((string) $reg['db']);
	$one = function($q) { $r = @db_fetch_array(@db_select($q, __FILE__ . " linje " . __LINE__)); return $r ? $r : array(); };
	# 20261111 CL/ASR Columns of the relation the unqualified name resolves to through search_path (to_regclass), the same table the counts below read; current_schema() can name a schema that has no such table (review #785).
	$my = db_is_mysql(); # 20261116 CL/ASR on MySQL/MariaDB the ledger's own database (DATABASE()) through information_schema
	$cols = function($t) use ($my) { $c = array(); $q = db_select($my ? "select column_name as attname from information_schema.columns where table_name = '$t'".opdat_schema_filter(db_type()) : "select attname from pg_attribute where attrelid = to_regclass('$t') and attnum > 0 and not attisdropped", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $c[] = $r['attname']; return $c; };
	if (tbl_exists('brugere')) {
		$bc = $cols('brugere'); $w = array(); if (in_array('revisor', $bc)) $w[] = "revisor is not true";
		$r = $one("select count(*) as n from brugere".($w ? " where ".implode(' and ', $w) : "")); $u['users_total'] = (int)($r['n'] ?? 0);
		if (in_array('status', $bc)) { $st = $one($my ? "select data_type as data_type from information_schema.columns where table_name = 'brugere' and column_name = 'status'".opdat_schema_filter(db_type()) : "select format_type(atttypid, null) as data_type from pg_attribute where attrelid = to_regclass('brugere') and attname = 'status' and not attisdropped"); $w[] = in_array(strtolower($st['data_type'] ?? ''), array('boolean', 'tinyint'), true) ? "(status is true or status is null)" : "(status is null or lower(".($my ? "status" : "status::text").") in ('t','true','1','on',''))"; } # older ledgers store status as text
		$r = $one("select count(*) as n from brugere".($w ? " where ".implode(' and ', $w) : "")); $u['users_active'] = (int)($r['n'] ?? 0);
	}
	if (tbl_exists('transaktioner')) {
		$from = date('Y-m-d', strtotime('-1 year')); $r = $one("select count(id) as n, max(logdate) as l from transaktioner where logdate >= '$from'"); $u['postings_12m'] = (int)($r['n'] ?? 0); $u['last_activity'] = $r['l'] ?? null;
		$r = $one("select count(id) as n from transaktioner where logdate >= '".date('Y-m-01')."'"); $u['postings_month'] = (int)($r['n'] ?? 0);
		$q = @db_select("select ".($my ? "DATE_FORMAT(logdate, '%Y-%m')" : "to_char(logdate, 'YYYY-MM')")." as m, count(id) as n from transaktioner where logdate >= '".date('Y-m-01', strtotime('-23 months'))."' group by 1 order by 1", __FILE__ . " linje " . __LINE__);
		if ($q) while ($r = db_fetch_array($q)) $u['postings_by_month'][$r['m']] = (int)$r['n'];
	}
	if (tbl_exists('varer')) { $vc = $cols('varer'); $r = $one("select count(id) as n from varer".(in_array('lukket', $vc) ? " where lukket is null or lukket = '' or lukket = '0'" : "")); $u['items'] = (int)($r['n'] ?? 0); if (in_array('serienr', $vc)) { $r = $one("select count(id) as n from varer where serienr = '1' or serienr = 'on' or serienr = 'true'"); $u['modules']['lager.serienumre'] = (int)($r['n'] ?? 0) > 0; } }
	if (tbl_exists('ordrer')) { $oc = $cols('ordrer'); $r = $one("select count(id) as n from ordrer where art = 'DF' and fakturadate >= '".date('Y-m-d', strtotime('-1 year'))."'"); $u['invoices_12m'] = (int)($r['n'] ?? 0); if (in_array('nextfakt', $oc)) { $r = $one("select count(id) as n from ordrer where nextfakt is not null"); $u['modules']['debitor.abonnement'] = (int)($r['n'] ?? 0) > 0; } $r = $one("select count(id) as n from ordrer where art = 'KO' or art = 'KF'"); $u['modules']['kreditor.indkob'] = (int)($r['n'] ?? 0) > 0; $r = $one("select count(id) as n from ordrer where art = 'DT'"); $u['modules']['debitor.tilbud'] = (int)($r['n'] ?? 0) > 0; }
	if (tbl_exists('grupper')) {
		$r = $one("select box1, box3 from grupper where art = 'POS' and kodenr = '1'"); $u['pos_tills'] = isset($r['box1']) ? (int)$r['box1'] : 0; $u['modules']['pos'] = $u['pos_tills'] > 0;
		if (isset($r['box3']) && $r['box3'] !== '') $u['pos_locations'] = count(array_unique(array_filter(array_map('trim', explode(',', $r['box3']))))); else $u['pos_locations'] = $u['pos_tills'] > 0 ? 1 : 0;
		$r = $one("select box4, box5, box6 from grupper where art = 'API' and kodenr = '1'"); $u['webshops'] = ($r && (trim((string)($r['box4'] ?? '')) !== '' || trim((string)($r['box5'] ?? '')) !== '' || trim((string)($r['box6'] ?? '')) !== '')) ? 1 : 0; $u['modules']['shop.integration'] = $u['webshops'] > 0;
		$r = $one("select count(id) as n from grupper where art = 'LG'"); $u['warehouses'] = (int)($r['n'] ?? 0); $u['modules']['lager.multilocation'] = $u['warehouses'] > 1;
		$r = $one("select count(id) as n from grupper where art = 'AFD' or art = 'PRJ'"); $u['modules']['finans.dimensioner'] = (int)($r['n'] ?? 0) > 0;
		$r = $one("select box7 from grupper where art = 'DIV' and kodenr = '2'"); $u['modules']['sager'] = isset($r['box7']) && $r['box7'] !== '' && $r['box7'] !== '0';
		$r = $one("select box4 from grupper where art = 'DIV' and kodenr = '3'"); $u['modules']['debitor.fakturering_fuld'] = !(isset($r['box4']) && ($r['box4'] === 'on' || $r['box4'] === '1'));
		$r = $one("select count(id) as n from grupper where art = 'DIV' and kodenr = '4'"); $u['modules']['debitor.rykker'] = (int)($r['n'] ?? 0) > 0;
	}
	if (tbl_exists('settings')) {
		$r = $one("select count(id) as n from settings where var_grp = 'debitor' and var_name = 'mySale' and var_value <> '' and var_value is not null"); $u['modules']['kommission'] = (int)($r['n'] ?? 0) > 0;
		$r = $one("select count(id) as n from settings where var_grp = 'rental'"); $u['modules']['booking'] = (int)($r['n'] ?? 0) > 0;
		$r = $one("select count(id) as n from settings where var_grp = 'productOptions' and var_name = 'min_beholdning' and var_value <> '' and var_value <> '0'"); $u['modules']['lager.minmax'] = (int)($r['n'] ?? 0) > 0;
	}
	if (tbl_exists('lagerstatus')) { $r = $one("select count(id) as n from lagerstatus"); $u['modules']['lager.styring'] = (int)($r['n'] ?? 0) > 0; }
	if (tbl_exists('adresser')) { $r = $one("select count(id) as n from adresser where art = 'K'"); $u['modules']['kreditor.register'] = (int)($r['n'] ?? 0) > 0; }
	if (tbl_exists('budget')) { $r = $one("select count(*) as n from budget"); $u['modules']['finans.budget'] = (int)($r['n'] ?? 0) > 0; }
	include("../includes/connect.php");
	return $u;
}
// Store today's snapshot (upsert) and refresh the legacy columns the lists read.
function ent_snapshot_store($rid, $u) {
	if (!$u['ok']) return;
	$rid = (int)$rid; $n = fn($v) => $v === null ? 'NULL' : (int)$v;
	db_modify("delete from usage_snapshots where regnskab_id = '$rid' and taken_at = current_date", __FILE__ . " linje " . __LINE__);
	db_modify("insert into usage_snapshots (regnskab_id, taken_at, postings_12m, postings_month, users_active, users_invited, items, webshops, pos_tills, pos_locations, warehouses) values ('$rid', current_date, ".$n($u['postings_12m']).", ".$n($u['postings_month']).", ".$n($u['users_active']).", 0, ".$n($u['items']).", ".$n($u['webshops']).", ".$n($u['pos_tills']).", ".$n($u['pos_locations']).", ".$n($u['warehouses']).")", __FILE__ . " linje " . __LINE__);
	$sidst = $u['last_activity'] ? (int)strtotime($u['last_activity']) : 0;
	db_modify("update regnskab set posteret = '".(int)$u['postings_12m']."'".($sidst ? ", sidst = '$sidst'" : "")." where id = '$rid'", __FILE__ . " linje " . __LINE__);
}
// Last snapshot per month (newest first).
function ent_usage_history($rid, $months = 12) {
	$out = array(); $q = db_select("select * from usage_snapshots where regnskab_id = '".(int)$rid."' order by taken_at desc", __FILE__ . " linje " . __LINE__);
	while (count($out) < (int)$months && ($r = db_fetch_array($q))) { $r['m'] = substr((string)$r['taken_at'], 0, 7); if (!isset($out[$r['m']])) $out[$r['m']] = $r; } # the newest snapshot of each month
	return array_values($out);
}
function ent_limit_label($k) { $l = array('max_users' => vr_t('Samtidige brugere','Concurrent users'), 'included_postings' => vr_t('Posteringer (12 mdr.)','Entries (12 m)'), 'max_items' => vr_t('Varer','Items'), 'max_webshops' => vr_t('Webshops','Webshops'), 'pos_tills' => vr_t('Kasser','Tills'), 'warehouses' => vr_t('Lagre','Warehouses')); return isset($l[$k]) ? $l[$k] : $k; }

// New ledger: base plan + trial override (entitlements spec §6.5). $plan_key = the plan tried, $days = trial length.
function ent_start_trial($rid, $plan_key, $days = 30, $base = 'finans') {
	$c = ent_catalog(); if (!isset($c['plans'][$plan_key])) return 'Ukendt pakke';
	$rid = (int)$rid; $days = max(1, (int)$days);
	if (!db_fetch_array(db_select("select 1 from regnskab_entitlements where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__))) ent_write_plan($rid, isset($c['plans'][$base]) ? $base : $plan_key, date('Y-m-d'), 'manual');
	$until = date('Y-m-d', strtotime("+$days days"));
	db_modify("insert into regnskab_overrides (regnskab_id, kind, ".db_key_column().", value, reason, expires_at, created_by) values ('$rid', 'plan', 'plan', '".db_escape_string($plan_key)."', 'trial', '$until', '".db_escape_string(ent_actor())."')", __FILE__ . " linje " . __LINE__);
	ent_log('entitlement.trial_started', $rid, ent_plan_name($plan_key)." i $days dage (til $until)");
	return '';
}

} // function_exists
?>
