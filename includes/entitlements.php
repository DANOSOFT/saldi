<?php
// --- includes/entitlements.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see admin/vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Catalogue + entitlement layer in the master database, tables as in
//                  Requirements_packages_entitlements_billing_EN.md §4.1/§5.1 with three additions needed for the
//                  operator's subscription card: prices on plans/addons (price_ore, billing_interval,
//                  extra_user_price_ore), valid_from on regnskab_entitlements, and regnskab_plan_changes for
//                  changes with a future effective date. Source is 'manual' until the Stripe webhook (§6) exists;
//                  the resolver (§5.2) is the same either way. Every change is written to partner_log as
//                  entitlement.* (the stage 2 audit_log mirrors it when it exists).

if (!function_exists('ent_tables_ensure')) {

function ent_plan_defs() { # plan_key => [name, sort, price_ore (null = contact), included_users, max_users, included_postings, max_items, included_webshops, extra_user_price_ore]
	return array(
		'finans'       => array('Finans', 1, 9900, 1, 1, 500, 0, 0, null),
		'professionel' => array('Professionel', 2, 19900, 1, 6, 5000, 10000, 1, 7900),
		'business'     => array('Business', 3, 49900, 1, 12, 25000, null, 0, 18900),
		'erp'          => array('ERP', 4, null, 1, null, null, null, null, 22900),
	);
}
function ent_feature_defs() { # [key, name, area, finans, professionel, business, erp]  ✓ included · ✗ none · B basic · A addon
	return array(
		array('finans.core','Finansmodul, kontokort, kassekladder, årsafslutning, momsberegning','Finans','✓','✓','✓','✓'),
		array('bilag.digital','Digitale bilag','Finans','✓','✓','✓','✓'),
		array('bilag.scan_mobile','Bilagsscanning (mobil)','Finans','✗','✓','✓','✓'),
		array('bilag.automatch','Automatisk bilagsmatch','Finans','✗','✓','✓','✓'),
		array('finans.dimensioner','Dimensioner','Finans','✗','✗','✓','✓'),
		array('finans.budget','Budget og forecast','Finans','✗','✓','✓','✓'),
		array('moms.eu_reverse','EU-handel (reverse charge)','Finans','✗','✓','✓','✓'),
		array('debitor.fakturering','Fakturering (Basis/Fuld)','Salg','B','✓','✓','✓'),
		array('debitor.tilbud','Tilbud','Salg','✗','✗','✓','✓'),
		array('debitor.ordrer','Ordrer og ordre-tracking','Salg','✗','✓','✓','✓'),
		array('debitor.abonnement','Abonnementsfakturering','Salg','✗','✓','✓','✓'),
		array('debitor.peppol','E-faktura (PEPPOL)','Salg','✗','✓','✓','✓'),
		array('debitor.rykker','Rykkersystem','Salg','✗','✓','✓','✓'),
		array('debitor.rente','Renteberegning','Salg','✗','✓','✓','✓'),
		array('debitor.location_sales','Debitorsalg pr. location','Salg','✗','✗','✓','✓'),
		array('salg.prisgrupper','Kundegrupper, prislister og rabatgrupper','Salg','✗','✓','✓','✓'),
		array('salg.backorder','Backorder-håndtering','Salg','✗','✗','✓','✓'),
		array('kreditor.register','Kreditorregister (Basis/Fuld)','Køb','B','✓','✓','✓'),
		array('kreditor.indkob','Indkøbsordrer og leverandørstyring','Køb','✗','✓','✓','✓'),
		array('kreditor.pbs','Betalingsintegration (PBS)','Køb','✗','✓','✓','✓'),
		array('lager.styring','Lagerstyring (Simpel/Fuld)','Lager','✗','B','✓','✓'),
		array('lager.varekartotek','Varekartotek','Lager','✗','✓','✓','✓'),
		array('lager.optaelling','Lageroptælling','Lager','✗','✗','✓','✓'),
		array('lager.serienumre','Serienumre og batch','Lager','✗','✗','✓','✓'),
		array('lager.multilocation','Multi-location lager og flytninger','Lager','✗','✗','✓','✓'),
		array('lager.minmax','Min/max og genbestilling','Lager','✗','✗','✓','✓'),
		array('lager.barcode','Barcode-scanning','Lager','✗','✗','✓','✓'),
		array('lager.plukliste','Plukliste','Lager','✗','✗','✓','✓'),
		array('shop.integration','Webshop-integration','Webshop og kasse','✗','A','A','✓'),
		array('shop.advanced','Avanceret webshop, lagersync, multi-shop','Webshop og kasse','✗','✗','A','✓'),
		array('pos','Integreret kassesystem','Webshop og kasse','✗','A','A','✓'),
		array('pos.multilocation','Multi-location kasse','Webshop og kasse','✗','✗','✓','✓'),
		array('pos.kasseafstemning','Kasseafstemning og bon-printer','Webshop og kasse','✗','A','✓','✓'),
		array('booking','Booking og udlejning','Moduler','✗','A','A','A'),
		array('kommission','Kommissionssalg','Moduler','✗','A','A','A'),
		array('sager','Sager og jobkort','Moduler','✓','✓','✓','✓'),
		array('produktion','Produktion','Moduler','✗','✗','✗','✓'),
	);
}
function ent_addon_defs() { # addon_key => [name, feature_key, quantity_based, price_ore, available_on]
	return array(
		'webshop_simple'   => array('Simpel webshop', 'shop.integration', false, 12900, 'professionel'),
		'webshop_advanced' => array('Avanceret webshop', 'shop.advanced', false, 28900, 'business'),
		'kommission'       => array('Kommissionssalg', 'kommission', false, 39900, 'professionel,business,erp'),
		'booking'          => array('Booking / udlejning', 'booking', false, 29900, 'professionel,business,erp'),
		'pos_location'     => array('Kasse-lokation', 'pos', true, 29900, 'professionel,business'),
		'pos_extra'        => array('Ekstra kasse', 'pos', true, null, 'professionel,business'),
		'lager_location'   => array('Ekstra lagerlokation', 'lager.multilocation', true, 9900, 'business'),
	);
}

function ent_tables_ensure() {
	static $done = false; if ($done) return; $done = true;
	if (db_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = 'regnskab_plan_changes'", __FILE__ . " linje " . __LINE__))) return;
	$sql = array(
		"CREATE TABLE IF NOT EXISTS catalog_versions (id SERIAL PRIMARY KEY, name text NOT NULL, valid_from date NOT NULL, is_default boolean NOT NULL DEFAULT false, created_at timestamp DEFAULT now())",
		"CREATE TABLE IF NOT EXISTS plans (id SERIAL PRIMARY KEY, catalog_version_id int NOT NULL, plan_key varchar(30) NOT NULL, name text NOT NULL, sort int NOT NULL, stripe_price_id varchar(255), varenr varchar(30), included_users int NOT NULL, max_users int, included_postings int, max_items int, included_webshops int NOT NULL DEFAULT 0, extra_user_stripe_price_id varchar(255), extra_user_varenr varchar(30), price_ore int, billing_interval varchar(10) NOT NULL DEFAULT 'month', extra_user_price_ore int, UNIQUE (catalog_version_id, plan_key))",
		"CREATE TABLE IF NOT EXISTS plan_features (plan_id int NOT NULL, feature_key varchar(50) NOT NULL, level varchar(10) NOT NULL, PRIMARY KEY (plan_id, feature_key))",
		"CREATE TABLE IF NOT EXISTS addons (id SERIAL PRIMARY KEY, catalog_version_id int NOT NULL, addon_key varchar(50) NOT NULL, name text NOT NULL, feature_key varchar(50) NOT NULL, quantity_based boolean NOT NULL DEFAULT false, stripe_price_id varchar(255), varenr varchar(30), available_on text NOT NULL, price_ore int, UNIQUE (catalog_version_id, addon_key))",
		"CREATE TABLE IF NOT EXISTS posting_tiers (catalog_version_id int NOT NULL, from_count int NOT NULL, to_count int, unit_centiore int NOT NULL, varenr varchar(30))",
		"CREATE TABLE IF NOT EXISTS regnskab_entitlements (regnskab_id int PRIMARY KEY, catalog_version_id int, plan_key varchar(30), source varchar(20) NOT NULL DEFAULT 'manual', stripe_customer_id varchar(255), stripe_subscription_id varchar(255), subscription_status varchar(30), current_period_end timestamp, enforcement varchar(10) NOT NULL DEFAULT 'observe', valid_from date, synced_at timestamp, updated_at timestamp DEFAULT now())",
		"CREATE TABLE IF NOT EXISTS regnskab_addons (regnskab_id int NOT NULL, addon_key varchar(50) NOT NULL, quantity int NOT NULL DEFAULT 1, source varchar(20) NOT NULL DEFAULT 'manual', stripe_subscription_item_id varchar(255), expires_at date, PRIMARY KEY (regnskab_id, addon_key))",
		"CREATE TABLE IF NOT EXISTS regnskab_overrides (id SERIAL PRIMARY KEY, regnskab_id int NOT NULL, kind varchar(20) NOT NULL, key varchar(50) NOT NULL, value text NOT NULL, reason text NOT NULL, expires_at date, created_by text NOT NULL, created_at timestamp DEFAULT now())",
		"CREATE TABLE IF NOT EXISTS usage_snapshots (id SERIAL PRIMARY KEY, regnskab_id int NOT NULL, taken_at date NOT NULL, postings_12m int, postings_month int, users_active int, users_invited int, items int, webshops int, pos_tills int, pos_locations int, warehouses int, billable_postings int, billable_users int, UNIQUE (regnskab_id, taken_at))",
		"CREATE TABLE IF NOT EXISTS regnskab_plan_changes (id SERIAL PRIMARY KEY, regnskab_id int NOT NULL, plan_key varchar(30) NOT NULL, effective_from date NOT NULL, reason text, created_by text NOT NULL, created_at timestamp DEFAULT now(), applied_at timestamp, cancelled_at timestamp)",
	);
	foreach ($sql as $q) db_modify($q, __FILE__ . " linje " . __LINE__);
	ent_seed();
}
function ent_seed() {
	if (db_fetch_array(db_select("select 1 from catalog_versions", __FILE__ . " linje " . __LINE__))) return;
	db_modify("insert into catalog_versions (name, valid_from, is_default) values ('Priser 2026-03-01', '2026-03-01', true)", __FILE__ . " linje " . __LINE__);
	$cv = db_fetch_array(db_select("select max(id) as id from catalog_versions", __FILE__ . " linje " . __LINE__)); $cv = (int)$cv['id'];
	$n = fn($v) => $v === null ? 'NULL' : (int)$v;
	$idx = 3;
	foreach (ent_plan_defs() as $k => $d) {
		db_modify("insert into plans (catalog_version_id, plan_key, name, sort, included_users, max_users, included_postings, max_items, included_webshops, price_ore, extra_user_price_ore) values ('$cv', '$k', '".db_escape_string($d[0])."', '$d[1]', '$d[3]', ".$n($d[4]).", ".$n($d[5]).", ".$n($d[6]).", ".(int)$d[7].", ".$n($d[2]).", ".$n($d[8]).")", __FILE__ . " linje " . __LINE__);
		$p = db_fetch_array(db_select("select id from plans where catalog_version_id = '$cv' and plan_key = '$k'", __FILE__ . " linje " . __LINE__)); $pid = (int)$p['id'];
		foreach (ent_feature_defs() as $f) {
			$lv = array('✓' => 'included', '✗' => 'none', 'B' => 'basic', 'A' => 'addon');
			db_modify("insert into plan_features (plan_id, feature_key, level) values ('$pid', '$f[0]', '".$lv[$f[$idx]]."')", __FILE__ . " linje " . __LINE__);
		}
		$idx++;
	}
	foreach (ent_addon_defs() as $k => $d) db_modify("insert into addons (catalog_version_id, addon_key, name, feature_key, quantity_based, available_on, price_ore) values ('$cv', '$k', '".db_escape_string($d[0])."', '$d[1]', ".($d[2] ? 'true' : 'false').", '$d[4]', ".$n($d[3]).")", __FILE__ . " linje " . __LINE__);
	foreach (array(array(0,100000,250), array(100000,199999,200), array(200000,499999,100), array(500000,1000000,50), array(1000000,null,36)) as $t) db_modify("insert into posting_tiers (catalog_version_id, from_count, to_count, unit_centiore) values ('$cv', '$t[0]', ".$n($t[1]).", '$t[2]')", __FILE__ . " linje " . __LINE__);
}

function ent_catalog() {
	static $c = null; if ($c !== null) return $c;
	ent_tables_ensure();
	$cv = db_fetch_array(db_select("select * from catalog_versions where is_default order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	$c = array('version' => $cv, 'plans' => array(), 'features' => array(), 'addons' => array(), 'feature_names' => array());
	if (!$cv) return $c;
	$q = db_select("select * from plans where catalog_version_id = '".(int)$cv['id']."' order by sort", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) { $c['plans'][$r['plan_key']] = $r; $c['features'][$r['plan_key']] = array(); }
	foreach ($c['plans'] as $k => $p) { $q = db_select("select feature_key, level from plan_features where plan_id = '".(int)$p['id']."'", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $c['features'][$k][$r['feature_key']] = $r['level']; }
	$q = db_select("select * from addons where catalog_version_id = '".(int)$cv['id']."' order by id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $c['addons'][$r['addon_key']] = $r;
	foreach (ent_feature_defs() as $f) $c['feature_names'][$f[0]] = array($f[1], $f[2]);
	return $c;
}
function ent_kr($ore) { return $ore === null ? null : number_format($ore / 100, 0, ',', '.').' kr.'; }
function ent_log($action, $rid, $details = '') {
	if (function_exists('partner_log')) partner_log($action, $rid, null, $details);
}
function ent_actor() { global $brugernavn; return (string)$brugernavn; }

// Apply plan changes whose effective date has arrived (lazy, on read and on the nightly job).
function ent_apply_due_changes($rid) {
	$rid = (int)$rid;
	$q = db_select("select * from regnskab_plan_changes where regnskab_id = '$rid' and applied_at is null and cancelled_at is null and effective_from <= current_date order by effective_from, id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		ent_write_plan($rid, $r['plan_key'], $r['effective_from'], 'scheduled');
		db_modify("update regnskab_plan_changes set applied_at = now() where id = '".(int)$r['id']."'", __FILE__ . " linje " . __LINE__);
		ent_log('entitlement.plan_applied', $rid, $r['plan_key'].' fra '.$r['effective_from'].($r['reason'] ? ' – '.$r['reason'] : ''));
	}
}
function ent_write_plan($rid, $plan_key, $valid_from, $source = 'manual') {
	$c = ent_catalog(); $cv = $c['version'] ? (int)$c['version']['id'] : 'NULL';
	$rid = (int)$rid; $pk = db_escape_string($plan_key); $vf = db_escape_string($valid_from);
	if (db_fetch_array(db_select("select 1 from regnskab_entitlements where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__)))
		db_modify("update regnskab_entitlements set plan_key = '$pk', catalog_version_id = $cv, source = '".db_escape_string($source)."', valid_from = '$vf', updated_at = now() where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__);
	else db_modify("insert into regnskab_entitlements (regnskab_id, catalog_version_id, plan_key, source, valid_from, subscription_status) values ('$rid', $cv, '$pk', '".db_escape_string($source)."', '$vf', 'none')", __FILE__ . " linje " . __LINE__);
}

// §5.2 resolution: plan matrix -> add-ons -> overrides. NULL limit = unlimited.
function ent_resolve($rid) {
	static $cache = array(); $rid = (int)$rid;
	if (isset($cache[$rid])) return $cache[$rid];
	ent_apply_due_changes($rid);
	$c = ent_catalog();
	$e = db_fetch_array(db_select("select * from regnskab_entitlements where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__));
	$out = array('row' => $e ?: null, 'plan_key' => $e ? $e['plan_key'] : null, 'plan' => null, 'features' => array(), 'limits' => array(), 'addons' => array(), 'overrides' => array(), 'pending' => array(), 'price_ore' => null, 'extra_user_price_ore' => null, 'interval' => 'month');
	if ($out['plan_key'] && isset($c['plans'][$out['plan_key']])) {
		$p = $c['plans'][$out['plan_key']]; $out['plan'] = $p;
		$out['features'] = $c['features'][$out['plan_key']];
		$out['limits'] = array('included_users' => (int)$p['included_users'], 'max_users' => $p['max_users'] === null ? null : (int)$p['max_users'], 'included_postings' => $p['included_postings'] === null ? null : (int)$p['included_postings'], 'max_items' => $p['max_items'] === null ? null : (int)$p['max_items'], 'max_webshops' => (int)$p['included_webshops']);
		$out['price_ore'] = $p['price_ore'] === null ? null : (int)$p['price_ore']; $out['extra_user_price_ore'] = $p['extra_user_price_ore'] === null ? null : (int)$p['extra_user_price_ore']; $out['interval'] = $p['billing_interval'];
	}
	$q = db_select("select * from regnskab_addons where regnskab_id = '$rid' and (expires_at is null or expires_at >= current_date)", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out['addons'][$r['addon_key']] = $r;
		if (isset($c['addons'][$r['addon_key']])) { $fk = $c['addons'][$r['addon_key']]['feature_key']; $out['features'][$fk] = 'included'; if ($r['addon_key'] == 'webshop_simple' || $r['addon_key'] == 'webshop_advanced') $out['limits']['max_webshops'] = max(1, (int)($out['limits']['max_webshops'] ?? 0)); }
	}
	$q = db_select("select * from regnskab_overrides where regnskab_id = '$rid' and (expires_at is null or expires_at >= current_date) order by id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out['overrides'][] = $r;
		if ($r['kind'] == 'plan' && isset($c['plans'][$r['value']])) { $out['plan_key'] = $r['value']; $out['plan'] = $c['plans'][$r['value']]; $out['features'] = array_merge($c['features'][$r['value']], array_intersect_key($out['features'], array())); foreach ($out['addons'] as $ak => $ar) if (isset($c['addons'][$ak])) $out['features'][$c['addons'][$ak]['feature_key']] = 'included'; $pp = $out['plan']; $out['limits'] = array('included_users' => (int)$pp['included_users'], 'max_users' => $pp['max_users'] === null ? null : (int)$pp['max_users'], 'included_postings' => $pp['included_postings'] === null ? null : (int)$pp['included_postings'], 'max_items' => $pp['max_items'] === null ? null : (int)$pp['max_items'], 'max_webshops' => (int)$pp['included_webshops']); }
		elseif ($r['kind'] == 'feature') $out['features'][$r['key']] = ($r['value'] == 'on') ? 'included' : 'none';
		elseif ($r['kind'] == 'limit') $out['limits'][$r['key']] = ($r['value'] === '' || strtolower($r['value']) == 'ubegrænset' || $r['value'] == '*') ? null : (int)$r['value'];
		elseif ($r['kind'] == 'addon') { $out['addons'][$r['key']] = array('addon_key' => $r['key'], 'quantity' => (int)$r['value'] ?: 1, 'source' => 'override', 'expires_at' => $r['expires_at']); if (isset($c['addons'][$r['key']])) $out['features'][$c['addons'][$r['key']]['feature_key']] = 'included'; }
	}
	$q = db_select("select * from regnskab_plan_changes where regnskab_id = '$rid' and applied_at is null and cancelled_at is null order by effective_from", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $out['pending'][] = $r;
	return $cache[$rid] = $out;
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
	if ($p['max_users'] !== null && isset($usage['users_active']) && $usage['users_active'] > (int)$p['max_users']) $out[] = "Regnskabet har {$usage['users_active']} aktive brugere – {$p['name']} tillader {$p['max_users']}. Deaktivér ".($usage['users_active'] - (int)$p['max_users'])." brugere først.";
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
function ent_set_addon($rid, $addon_key, $qty, $expires) {
	$c = ent_catalog(); if (!isset($c['addons'][$addon_key])) return 'Ukendt tillæg';
	$rid = (int)$rid; $qty = max(1, (int)$qty); $ex = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$expires) ? "'$expires'" : 'NULL';
	db_modify("delete from regnskab_addons where regnskab_id = '$rid' and addon_key = '".db_escape_string($addon_key)."'", __FILE__ . " linje " . __LINE__);
	db_modify("insert into regnskab_addons (regnskab_id, addon_key, quantity, source, expires_at) values ('$rid', '".db_escape_string($addon_key)."', '$qty', 'manual', $ex)", __FILE__ . " linje " . __LINE__);
	ent_log('entitlement.addon_set', $rid, $c['addons'][$addon_key]['name'].' × '.$qty.($ex != 'NULL' ? ' indtil '.$expires : ''));
	return '';
}
function ent_remove_addon($rid, $addon_key) {
	$c = ent_catalog(); db_modify("delete from regnskab_addons where regnskab_id = '".(int)$rid."' and addon_key = '".db_escape_string($addon_key)."'", __FILE__ . " linje " . __LINE__);
	ent_log('entitlement.addon_removed', (int)$rid, isset($c['addons'][$addon_key]) ? $c['addons'][$addon_key]['name'] : $addon_key);
}
function ent_add_override($rid, $kind, $key, $value, $reason, $expires) {
	if (!in_array($kind, array('plan','feature','limit','addon'), true)) return 'Ugyldig type';
	if (trim($reason) === '') return 'Begrundelse mangler';
	$ex = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$expires) ? "'$expires'" : 'NULL';
	if ($ex == 'NULL' && !($kind == 'plan' && stripos($reason, 'grandfather') !== false || stripos($reason, 'overgangsordning') !== false)) return 'Udløbsdato er påkrævet (undtagen overgangsordning)';
	db_modify("insert into regnskab_overrides (regnskab_id, kind, key, value, reason, expires_at, created_by) values ('".(int)$rid."', '$kind', '".db_escape_string($key)."', '".db_escape_string($value)."', '".db_escape_string($reason)."', $ex, '".db_escape_string(ent_actor())."')", __FILE__ . " linje " . __LINE__);
	ent_log('entitlement.override_added', (int)$rid, "$kind $key = $value".($ex != 'NULL' ? " indtil $expires" : '').' – '.$reason);
	return '';
}
function ent_expire_override($rid, $id) {
	$r = db_fetch_array(db_select("select * from regnskab_overrides where id = '".(int)$id."' and regnskab_id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__));
	if (!$r) return;
	db_modify("update regnskab_overrides set expires_at = current_date - 1 where id = '".(int)$id."'", __FILE__ . " linje " . __LINE__);
	ent_log('entitlement.override_expired', (int)$rid, "$r[kind] $r[key] = $r[value]");
}

// ---------- usage (area 2 "Forbrug og begrænsninger") ----------
// Live measurement in the customer database, rules from the entitlements spec §2.3/§5.6.
function ent_usage_collect($reg) {
	global $sqhost, $squser, $sqpass;
	$u = array('ok' => false, 'users_active' => null, 'users_total' => null, 'postings_12m' => null, 'postings_month' => null, 'postings_by_month' => array(), 'items' => null, 'webshops' => null, 'pos_tills' => null, 'pos_locations' => null, 'warehouses' => null, 'invoices_12m' => null, 'last_activity' => null, 'modules' => array());
	if (!$reg['db'] || !db_exists($reg['db'])) return $u;
	if (!@db_connect($sqhost, $squser, $sqpass, $reg['db'], __FILE__ . " linje " . __LINE__)) return $u;
	$u['ok'] = true;
	$one = function($q) { $r = @db_fetch_array(@db_select($q, __FILE__ . " linje " . __LINE__)); return $r ? $r : array(); };
	$cols = function($t) { $c = array(); $q = db_select("select column_name from information_schema.columns where table_name = '$t'", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $c[] = $r['column_name']; return $c; };
	if (tbl_exists('brugere')) {
		$bc = $cols('brugere'); $w = array(); if (in_array('revisor', $bc)) $w[] = "revisor is not true"; 
		$r = $one("select count(*) as n from brugere".($w ? " where ".implode(' and ', $w) : "")); $u['users_total'] = (int)($r['n'] ?? 0);
		if (in_array('status', $bc)) $w[] = "(status is true or status is null)";
		$r = $one("select count(*) as n from brugere".($w ? " where ".implode(' and ', $w) : "")); $u['users_active'] = (int)($r['n'] ?? 0);
	}
	if (tbl_exists('transaktioner')) {
		$from = date('Y-m-d', strtotime('-1 year')); $r = $one("select count(id) as n, max(logdate) as l from transaktioner where logdate >= '$from'"); $u['postings_12m'] = (int)($r['n'] ?? 0); $u['last_activity'] = $r['l'] ?? null;
		$r = $one("select count(id) as n from transaktioner where logdate >= '".date('Y-m-01')."'"); $u['postings_month'] = (int)($r['n'] ?? 0);
		$q = @db_select("select to_char(logdate, 'YYYY-MM') as m, count(id) as n from transaktioner where logdate >= '".date('Y-m-01', strtotime('-23 months'))."' group by 1 order by 1", __FILE__ . " linje " . __LINE__);
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
	$out = array(); $q = db_select("select distinct on (to_char(taken_at, 'YYYY-MM')) to_char(taken_at, 'YYYY-MM') as m, * from usage_snapshots where regnskab_id = '".(int)$rid."' order by to_char(taken_at, 'YYYY-MM') desc, taken_at desc limit ".(int)$months, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $out[] = $r;
	return $out;
}
function ent_limit_label($k) { $l = array('max_users' => vr_t('Brugere','Users'), 'included_postings' => vr_t('Posteringer (12 mdr.)','Entries (12 m)'), 'max_items' => vr_t('Varer','Items'), 'max_webshops' => vr_t('Webshops','Webshops'), 'pos_tills' => vr_t('Kasser','Tills'), 'warehouses' => vr_t('Lagre','Warehouses')); return isset($l[$k]) ? $l[$k] : $k; }

} // function_exists
?>
