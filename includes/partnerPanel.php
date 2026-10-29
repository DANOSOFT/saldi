<?php
// --- includes/partnerPanel.php --- ver 5.1.0 --- 2026.10.29 ---
// LICENSE: GNU GPL v2 or later; see admin/vis_regnskaber.php for the full notice.
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261012 CL/ASR New. Library for the accountant panel (bogholderpanelet, admin/bogholder_*.php), built on
//                  includes/partnerScope.php. Own tables only, created on first use: invitations (employees and
//                  customer users), access requests to existing ledgers, requests to Saldi (payer change, cancellation,
//                  archiving), favourites, recently opened, a work cache per customer (open journals, documents in the
//                  pool, company details from the customer database, refreshed every 10 minutes) and provisional
//                  volume pricing for the bookkeeper scheme. Nothing here changes the operator panel or the customer
//                  pages; the operator's includes (entitlements, proposals, trials) are read through their own functions.
// 20261029 CL/ASR Group mode (partners.kind = 'koncern', addendum 29/10): the group owns its companies (access 'owner'),
//                  companies are invoiced via the parent by default and may pay themselves, the group changes plans
//                  directly without a proposal, and each group employee counts as one ERP user across all companies.
// 20261029 CL/ASR Review fixes: group and scheme prices count the whole firm, invitations and their list follow the
//                  employee's customer scope, expired invitations can be resent, no rights stays no rights, user seats
//                  are checked on creation, the payer is always mirrored to billing, an accepted upgrade moves payment
//                  once, a group taking in a company pays for it, sessions are ended only for the person concerned.
// 20261029 CL/ASR Group mode (koncern); the tables are created by the update chain (includes/opdat_func/partnerPanelSchema.php).

if (!function_exists('bp_tables_ensure')) {

define('BP_CACHE_SECONDS', 600);     # work cache age before a customer database is read again
define('BP_INVITE_DAYS', 14);        # invitations and access requests expire after this
define('BP_PRICING_PROVISIONAL', true); # the volume tiers are placeholders until the price is decided

// ---------- tables ----------
function bp_tables_ensure() {
	# 20261029 CL/ASR The tables come from the update chain (includes/opdat_func/partnerPanelSchema.php via betweenUpdates.php).
	return;
}

// ---------- small helpers (work with or without admin/vr_ui.php) ----------
// 20261029 CL/ASR True once the update chain has created the accountant panel's tables (settings flag on the master).
function bp_ready() { static $r = null; if ($r === null) $r = (bool)db_fetch_array(db_select("SELECT 1 FROM settings WHERE var_grp = 'system' AND var_name = 'partner_panel_schema'", __FILE__ . " linje " . __LINE__)); return $r; }
function bp_h($s) { return function_exists('vr_h') ? vr_h($s) : htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bp_t($da, $en) { return function_exists('vr_t') ? vr_t($da, $en) : $da; }
function bp_date($d) { return $d ? date('d-m-Y', strtotime(substr((string)$d, 0, 10))) : '–'; }
function bp_token() { return bin2hex(random_bytes(24)); }
function bp_me() { return partner_current_user(); }
function bp_pid() { $u = partner_current_user(); return $u['partner'] ? (int)$u['partner']['partner_id'] : 0; }
function bp_is_owner() { $u = partner_current_user(); return $u['partner'] && $u['partner']['partner_role'] == 'owner'; }
function bp_display_name() { $u = partner_current_user(); return ($u['partner'] && $u['partner']['user_name']) ? $u['partner']['user_name'] : $u['brugernavn']; }
// A right of the current employee, combined with what Saldi allows the firm (partners.can_*).
function bp_can($right) {
	$u = partner_current_user(); if (!$u['partner']) return false;
	if ($right == 'features') return $u['partner']['can_manage_features'] === 't';
	if (!partner_user_can($right)) return false;
	if ($right == 'create') return $u['partner']['can_create'] === 't';
	if ($right == 'users') return $u['partner']['can_manage_users'] === 't';
	return true;
}
function bp_partner() { static $p = null; if ($p === null) { bp_tables_ensure(); $p = db_fetch_array(db_select("select * from partners where id = '".bp_pid()."'", __FILE__ . " linje " . __LINE__)) ?: array(); } return $p; }
function bp_kind_label() { $p = bp_partner(); return $p ? vr_kind($p['kind']) : bp_t('Bogholder', 'Bookkeeper'); }
function bp_cust_word($plural = true) { $p = bp_partner(); $k = $p ? $p['kind'] : 'bogholder'; return $k == 'koncern' ? ($plural ? bp_t('selskaber','companies') : bp_t('selskab','company')) : ($plural ? bp_t('kunder','customers') : bp_t('kunde','customer')); }
function bp_is_group() { $p = bp_partner(); return $p && $p['kind'] == 'koncern'; }
function bp_panel_name() { return bp_is_group() ? bp_t('Koncernpanel','Group panel') : bp_t('Bogholderpanel','Accountant panel'); }
// Group pricing: monthly price of the companies invoiced via the parent + one ERP user per active employee.
// [companies[], plans_ore, unpriced_n, users_n, user_price_ore, users_ore, total_ore]
function bp_group_price() {
	$rows = array_filter(bp_customers(false, true), fn($r) => !$r['closed'] && ($r['is_home'] || $r['payer'] == 'partner'));
	$plans = 0; $unpriced = 0; $list = array();
	foreach ($rows as $r) { $e = ent_resolve((int)$r['id']); $m = null; if ($e['plan'] && $e['price_ore'] !== null) $m = ($e['interval'] == 'year') ? (int)round($e['price_ore'] / 12) : (int)$e['price_ore']; if ($m === null) $unpriced++; else $plans += $m; $list[] = array($r, $e['plan'] ? $e['plan']['name'] : '', $m); }
	$users = count(array_filter(bp_employees(), fn($e) => $e['active']));
	$c = ent_catalog(); $up = isset($c['plans']['erp']) && $c['plans']['erp']['extra_user_price_ore'] !== null ? (int)$c['plans']['erp']['extra_user_price_ore'] : 22900;
	return array($list, $plans, $unpriced, $users, $up, $users * $up, $plans + $users * $up);
}
// Group: change a company's plan directly (no proposal). Same downgrade guard as the operator panel.
function bp_group_set_plan($reg, $plan_key, $date, $reason, $force) {
	if (!bp_is_group() || !bp_can('subscription')) return bp_t('Handlingen kræver retten Ændre abonnementer','The action requires the right Change subscriptions');
	$l = partner_link((int)$reg['id']); if (!$l || $l['access'] != 'owner') return bp_t('Koncernen ejer ikke selskabet','The group does not own the company');
	$c = ent_catalog(); if (!isset($c['plans'][$plan_key])) return bp_t('Ukendt pakke','Unknown plan');
	if (function_exists('ent_live_usage_quick') && !$force) { $conf = ent_conflicts($plan_key, ent_live_usage_quick($reg)); if ($conf) return bp_t('Pakken er ikke skiftet, fordi selskabet bruger mere, end den nye pakke tillader:','The plan was not changed because the company uses more than the new plan allows:').' '.implode(' ', $conf); }
	$e = ent_set_plan((int)$reg['id'], $plan_key, $date, trim($reason).' ('.bp_t('skiftet af koncernen','changed by the group').': '.bp_display_name().')'); if ($e) return $e;
	bp_mail(bp_saldi_mail(), '[Koncernpanel] Pakkeskift: '.$reg['regnskab'], bp_mail_wrap('Pakkeskift i koncern', array(bp_h(bp_partner()['name']).' har skiftet '.bp_h($reg['regnskab']).' (id '.(int)$reg['id'].') til '.bp_h($c['plans'][$plan_key]['name']).' fra '.bp_h($date ?: date('Y-m-d')).'. Opdatér genfaktureringen.')));
	return '';
}
function bp_url($page, $params = array()) { # absolute link to a page in admin/ (for mails)
	$host = isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] ? $_SERVER['SERVER_NAME'] : 'saldi.dk';
	$dir = rtrim(dirname((string)($_SERVER['PHP_SELF'] ?? '/admin/x.php')), '/'); if (basename($dir) != 'admin') $dir = preg_replace('#/[^/]*$#', '', $dir).'/admin';
	return 'https://'.$host.$dir.'/'.$page.($params ? '?'.http_build_query($params) : '');
}
function bp_mail($to, $subject, $html) {
	if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
	if (php_sapi_name() == 'cli') return true; # tests and jobs never send
	if (function_exists('vr_mail')) return vr_mail($to, $subject, $html);
	return @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $html, "From: Saldi <info@saldi.dk>\r\nReply-To: info@saldi.dk\r\nContent-Type: text/html; charset=UTF-8\r\n");
}
function bp_mail_wrap($title, $lines, $btnText = '', $btnUrl = '') { # one plain, readable mail layout
	$h = "<div style=\"font-family:Inter,Segoe UI,system-ui,sans-serif;font-size:15px;line-height:1.6;color:#0b1a3a;max-width:560px\"><p style=\"font-size:18px;font-weight:600;margin:0 0 12px\">".bp_h($title)."</p>";
	foreach ((array)$lines as $l) $h .= "<p style=\"margin:0 0 10px\">".$l."</p>";
	if ($btnUrl) $h .= "<p style=\"margin:18px 0\"><a href=\"".bp_h($btnUrl)."\" style=\"background:#014294;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600\">".bp_h($btnText)."</a></p><p style=\"font-size:13px;color:#5b6b85\">".bp_t('Virker knappen ikke, så kopiér dette link:','If the button does not work, copy this link:')."<br>".bp_h($btnUrl)."</p>";
	return $h."<p style=\"color:#5b6b85;font-size:13px;margin-top:18px\">".bp_t('Venlig hilsen','Kind regards')."<br>Saldi · Danosoft ApS</p></div>";
}
function bp_saldi_mail() { # where requests to Saldi go
	$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'admin' and var_name = 'partner_request_mail'", __FILE__ . " linje " . __LINE__));
	return ($r && filter_var($r['var_value'], FILTER_VALIDATE_EMAIL)) ? $r['var_value'] : 'info@saldi.dk';
}
// A login name the login page keeps as typed (index/login.php sanitize_input() drops other characters, e.g. an apostrophe).
function bp_login_name_ok($un) { return (bool)preg_match('/^[\p{L}\p{M}\p{N}\p{Sc} @._+\-!#%()*,:=?\[\]^{|}~`\/]+$/u', (string)$un); }
function bp_login_name_msg() { return bp_t('Brugernavnet må kun indeholde bogstaver, tal, mellemrum og tegn som . _ - @ (ikke apostrof eller anførselstegn)','The username may only contain letters, digits, spaces and characters like . _ - @ (no apostrophe or quotes)'); }
function bp_bool($v) { return $v === 't' || $v === true || $v === '1' || $v === 1; }
function bp_log($action, $rid = null, $details = '') { partner_log($action, $rid, bp_pid() ?: null, $details); }

// ---------- customers of the current partner ----------
// Rows: regnskab + link + plan + trial + cache + responsible + open items. Scoped by partner_ledgers() (employee scope).
function bp_customers($includeClosed = true, $wholeFirm = false) { # $wholeFirm: every linked ledger, also outside the employee's scope (firm-level prices)
	static $cache = array(); $k = ($includeClosed ? 'all' : 'open').($wholeFirm ? '-firm' : ''); if (isset($cache[$k])) return $cache[$k];
	bp_tables_ensure(); $pid = bp_pid();
	if ($wholeFirm) { $ids = array(); if ($pid) { $hm = bp_partner(); if (!empty($hm['home_regnskab_id'])) $ids[] = (int)$hm['home_regnskab_id']; $q = db_select("select regnskab_id from regnskab_partners where partner_id = '$pid' and until is null", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $ids[] = (int)$r['regnskab_id']; $ids = array_values(array_unique($ids)); } }
	else { $ids = partner_ledgers(); if ($ids === null) $ids = array(); }
	$out = array(); if (!$ids) return $cache[$k] = $out;
	$in = implode(',', array_map('intval', $ids));
	$plans = function_exists('ent_plans_by_ledger') ? ent_plans_by_ledger() : array();
	$ent = array(); if (function_exists('ent_tables_ensure')) { ent_tables_ensure(); $q = db_select("select regnskab_id, subscription_status, current_period_end from regnskab_entitlements where regnskab_id in ($in)", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $ent[(int)$r['regnskab_id']] = $r; }
	$trial = array(); if (db_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = 'regnskab_trial'", __FILE__ . " linje " . __LINE__))) { $q = db_select("select * from regnskab_trial where regnskab_id in ($in)", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $trial[(int)$r['regnskab_id']] = $r; }
	$cache_rows = array(); $q = db_select("select * from partner_ledger_cache where regnskab_id in ($in)", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $cache_rows[(int)$r['regnskab_id']] = $r;
	$links = array(); $q = db_select("select rp.*, b.brugernavn as resp_login, pu.name as resp_name from regnskab_partners rp left join brugere b on b.id = rp.responsible_bruger_id left join partner_users pu on pu.bruger_id = rp.responsible_bruger_id where rp.partner_id = '$pid' and rp.until is null and rp.regnskab_id in ($in)", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $links[(int)$r['regnskab_id']] = $r;
	$props = array(); if (db_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = 'partner_proposals'", __FILE__ . " linje " . __LINE__))) { $q = db_select("select regnskab_id, status, kind, key, decided_at, created from partner_proposals where partner_id = '$pid' and regnskab_id in ($in) and (status = 'open' or decided_at >= now() - interval '30 days')", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $props[(int)$r['regnskab_id']][] = $r; }
	$reqs = array(); $q = db_select("select regnskab_id, kind, status, created from partner_requests where partner_id = '$pid' and status = 'open' and regnskab_id in ($in)", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $reqs[(int)$r['regnskab_id']][] = $r;
	$favs = bp_favorites(); $home = (int)(bp_partner()['home_regnskab_id'] ?? 0);
	$q = db_select("select id, regnskab, db, lukket, lukkes, sidst, posteret, posteringer, brugerantal, email, betalt_til from regnskab where id in ($in) order by regnskab", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$id = (int)$r['id']; $closed = ($r['lukket'] == 'on');
		if (!$includeClosed && $closed) continue;
		$r['closed'] = $closed; $r['is_home'] = ($id == $home);
		$r['link'] = isset($links[$id]) ? $links[$id] : null;
		$r['payer'] = $r['link'] ? $r['link']['payer'] : 'customer';
		$r['responsible_id'] = $r['link'] ? (int)$r['link']['responsible_bruger_id'] : 0;
		$r['responsible'] = $r['link'] ? ($r['link']['resp_name'] ?: $r['link']['resp_login']) : '';
		$r['plan_key'] = isset($plans[$id]) ? $plans[$id] : null; $r['plan_name'] = $r['plan_key'] && function_exists('ent_plan_name') ? ent_plan_name($r['plan_key']) : ($r['plan_key'] ?: '');
		$r['sub_status'] = isset($ent[$id]) ? $ent[$id]['subscription_status'] : ''; $r['period_end'] = isset($ent[$id]) ? $ent[$id]['current_period_end'] : null;
		$r['trial'] = isset($trial[$id]) ? $trial[$id] : null; $r['trial_left'] = $r['trial'] ? (int)round((strtotime($r['trial']['ends']) - strtotime(date('Y-m-d'))) / 86400) : null;
		$r['cache'] = isset($cache_rows[$id]) ? $cache_rows[$id] : null;
		$r['cvr'] = $r['cache'] ? (string)$r['cache']['cvr'] : ''; $r['firma'] = $r['cache'] ? (string)$r['cache']['firma'] : '';
		$r['open_journals'] = $r['cache'] ? (int)$r['cache']['open_journals'] : 0; $r['journal_lines'] = $r['cache'] ? (int)$r['cache']['journal_lines'] : 0; $r['pool_docs'] = $r['cache'] ? (int)$r['cache']['pool_docs'] : 0;
		$r['proposals'] = isset($props[$id]) ? $props[$id] : array(); $r['requests'] = isset($reqs[$id]) ? $reqs[$id] : array();
		$r['fav'] = in_array($id, $favs);
		$r['status'] = bp_status($r); $r['attention'] = bp_attention($r); $r['work'] = ($r['open_journals'] > 0 || $r['pool_docs'] > 0);
		$out[$id] = $r;
	}
	return $cache[$k] = $out;
}
function bp_customer($rid) { $c = bp_customers(true); return isset($c[(int)$rid]) ? $c[(int)$rid] : null; }
// Subscription/ledger status: [key, text, dot]
function bp_status($r) {
	if ($r['closed']) return array('closed', bp_t('Lukket','Closed'), 'vr-off');
	if ($r['sub_status'] == 'canceled') return array('canceled', bp_t('Opsagt','Cancelled').($r['period_end'] ? ' · '.bp_t('ophører','ends').' '.bp_date($r['period_end']) : ''), 'vr-warn');
	if ($r['trial'] && !$r['trial']['resolved'] && $r['trial_left'] !== null) {
		if ($r['trial_left'] >= 0) return array('trial', bp_t('Prøveperiode','Trial').' · '.($r['trial_left'] == 0 ? bp_t('sidste dag','last day') : $r['trial_left'].' '.($r['trial_left'] == 1 ? bp_t('dag','day') : bp_t('dage','days'))), $r['trial_left'] <= 7 ? 'vr-warn' : 'vr-ok');
		if (!$r['plan_key'] || $r['trial']['resolved'] != 'paid') return array('trial_over', bp_t('Prøve udløbet','Trial ended'), 'vr-bad');
	}
	return array('active', bp_t('Aktiv','Active'), 'vr-ok');
}
// Things that need a decision or follow-up (not daily work): list of [kind, text, href]
function bp_attention($r) {
	$id = (int)$r['id']; $o = array(); $st = $r['status'][0];
	if ($st == 'trial' && $r['trial_left'] <= 7) $o[] = array('trial_soon', bp_t('Prøveperioden udløber om','Trial ends in').' '.$r['trial_left'].' '.($r['trial_left'] == 1 ? bp_t('dag','day') : bp_t('dage','days')), "bogholder_panel.php?regnskab_id=$id&tab=abonnement");
	if ($st == 'trial_over') $o[] = array('trial_over', bp_t('Prøveperioden er udløbet uden pakke','Trial ended without a plan'), "bogholder_panel.php?regnskab_id=$id&tab=abonnement");
	if ($st == 'canceled') $o[] = array('canceled', bp_t('Abonnementet er opsagt','Subscription cancelled'), "bogholder_panel.php?regnskab_id=$id&tab=abonnement");
	foreach ($r['proposals'] as $p) { if ($p['status'] == 'open') $o[] = array('proposal_open', bp_t('Opgradering afventer kundens accept','Upgrade awaits the customer'), "bogholder_panel.php?regnskab_id=$id&tab=abonnement"); elseif (in_array($p['status'], array('rejected','expired'))) $o[] = array('proposal_failed', bp_t('Opgradering blev ikke gennemført','Upgrade was not completed').' ('.($p['status'] == 'rejected' ? bp_t('afvist','rejected') : bp_t('udløbet','expired')).')', "bogholder_panel.php?regnskab_id=$id&tab=abonnement"); }
	foreach ($r['requests'] as $rq) $o[] = array('request_open', bp_request_label($rq['kind']).' · '.bp_t('afventer','awaiting').' '.($rq['kind'] == 'payer_to_customer' ? bp_t('kunden','the customer') : 'Saldi'), "bogholder_panel.php?regnskab_id=$id&tab=handlinger");
	return $o;
}

// ---------- work cache: open journals and documents in the pool, company details ----------
function bp_cache_refresh(&$rows, $force = false, $max = 80) {
	global $sqhost, $squser, $sqpass; bp_tables_ensure();
	$n = 0; $cut = time() - BP_CACHE_SECONDS;
	foreach ($rows as $id => &$r) {
		if ($n >= $max) break;
		$c = $r['cache']; if (!$force && $c && $c['checked_at'] && strtotime($c['checked_at']) > $cut) continue;
		if ($r['closed'] && $c) continue;
		$n++; $live = bp_ledger_live($r);
		if (!$live['ok']) continue;
		$v = array(); foreach (array('firma','cvr','email','tlf','addr1','postnr','bynavn','kontakt') as $f) $v[] = "$f = '".db_escape_string(mb_substr((string)$live[$f], 0, 120))."'";
		foreach (array('open_journals','journal_lines','pool_docs','users_active') as $f) $v[] = "$f = ".($live[$f] === null ? 'NULL' : (int)$live[$f]);
		if (!db_fetch_array(db_select("select 1 from partner_ledger_cache where regnskab_id = '$id'", __FILE__ . " linje " . __LINE__))) db_modify("insert into partner_ledger_cache (regnskab_id) values ('$id')", __FILE__ . " linje " . __LINE__);
		db_modify("update partner_ledger_cache set ".implode(', ', $v).", checked_at = now() where regnskab_id = '$id'", __FILE__ . " linje " . __LINE__);
		$r['cache'] = db_fetch_array(db_select("select * from partner_ledger_cache where regnskab_id = '$id'", __FILE__ . " linje " . __LINE__));
		$r['cvr'] = (string)$live['cvr']; $r['firma'] = (string)$live['firma']; $r['open_journals'] = (int)$live['open_journals']; $r['journal_lines'] = (int)$live['journal_lines']; $r['pool_docs'] = (int)$live['pool_docs']; $r['work'] = ($r['open_journals'] > 0 || $r['pool_docs'] > 0);
	}
	unset($r); return $n;
}
// Live read in one customer database (cheap queries only).
function bp_ledger_live($reg) {
	global $sqhost, $squser, $sqpass;
	$u = array('ok' => false, 'firma' => '', 'cvr' => '', 'email' => '', 'tlf' => '', 'addr1' => '', 'postnr' => '', 'bynavn' => '', 'kontakt' => '', 'open_journals' => null, 'journal_lines' => null, 'pool_docs' => null, 'users_active' => null);
	if (!$reg['db'] || !db_exists($reg['db'])) return $u;
	if (!@db_connect($sqhost, $squser, $sqpass, $reg['db'], __FILE__ . " linje " . __LINE__)) return $u;
	$u['ok'] = true;
	$one = function($q) { $r = @db_fetch_array(@db_select($q, __FILE__ . " linje " . __LINE__)); return $r ? $r : array(); };
	if (tbl_exists('adresser')) { $r = $one("select firmanavn, cvrnr, email, tlf, addr1, postnr, bynavn, kontakt from adresser where art = 'S' order by id limit 1"); if ($r) { $u['firma'] = (string)$r['firmanavn']; $u['cvr'] = str_replace(' ', '', preg_replace('/^DK\s*/i', '', trim((string)$r['cvrnr']))); foreach (array('email','tlf','addr1','postnr','bynavn','kontakt') as $f) $u[$f] = (string)$r[$f]; } }
	if (tbl_exists('kladdeliste')) { $r = $one("select count(*) as n from kladdeliste where bogfort = '-'"); $u['open_journals'] = (int)($r['n'] ?? 0); }
	if (tbl_exists('kassekladde')) { $r = $one("select count(*) as n from kassekladde"); $u['journal_lines'] = (int)($r['n'] ?? 0); }
	if (tbl_exists('pool_files')) { $r = $one("select count(*) as n from pool_files"); $u['pool_docs'] = (int)($r['n'] ?? 0); }
	if (tbl_exists('brugere')) { $cols = array(); $q = db_select("select column_name from information_schema.columns where table_name = 'brugere'", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $cols[] = $r['column_name']; $r = $one("select count(*) as n from brugere".(in_array('revisor', $cols) ? " where revisor is not true" : "")); $u['users_active'] = (int)($r['n'] ?? 0); }
	include("../includes/connect.php");
	return $u;
}
function bp_cache_age($rows) { $t = null; foreach ($rows as $r) if ($r['cache'] && $r['cache']['checked_at'] && (!$t || $r['cache']['checked_at'] < $t)) $t = $r['cache']['checked_at']; return $t; }

// ---------- favourites and recently opened ----------
function bp_favorites() { static $f = null; if ($f !== null) return $f; bp_tables_ensure(); $f = array(); $q = db_select("select regnskab_id from partner_favorites where bruger_id = '".(int)bp_me()['id']."'", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $f[] = (int)$r['regnskab_id']; return $f; }
function bp_fav_toggle($rid) {
	$uid = (int)bp_me()['id']; $rid = (int)$rid;
	if (db_fetch_array(db_select("select 1 from partner_favorites where bruger_id = '$uid' and regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__))) { db_modify("delete from partner_favorites where bruger_id = '$uid' and regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__); return false; }
	db_modify("insert into partner_favorites (bruger_id, regnskab_id) values ('$uid', '$rid')", __FILE__ . " linje " . __LINE__); return true;
}
function bp_recent_add($rid) {
	$uid = (int)bp_me()['id']; $rid = (int)$rid; if (!$uid || !$rid) return;
	db_modify("delete from partner_recent where bruger_id = '$uid' and regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__);
	db_modify("insert into partner_recent (bruger_id, regnskab_id) values ('$uid', '$rid')", __FILE__ . " linje " . __LINE__);
	db_modify("delete from partner_recent where bruger_id = '$uid' and regnskab_id not in (select regnskab_id from partner_recent where bruger_id = '$uid' order by opened_at desc limit 12)", __FILE__ . " linje " . __LINE__);
}
function bp_recent($limit = 6) { $o = array(); $q = db_select("select regnskab_id, opened_at from partner_recent where bruger_id = '".(int)bp_me()['id']."' order by opened_at desc limit ".(int)$limit, __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $o[(int)$r['regnskab_id']] = $r['opened_at']; return $o; }

// ---------- pricing for the bookkeeper scheme (provisional) ----------
function bp_tiers() { bp_tables_ensure(); $o = array(); $q = db_select("select * from partner_pricing_tiers order by min_count", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $o[] = $r; return $o; }
function bp_finans_price_ore() { if (function_exists('ent_catalog')) { $c = ent_catalog(); if (isset($c['plans']['finans']) && $c['plans']['finans']['price_ore'] !== null) return (int)$c['plans']['finans']['price_ore']; } return 9900; }
// [count, list_ore, discount_pct, total_ore, avg_ore, tier]
function bp_price_calc($n) {
	$n = max(0, (int)$n); $list = bp_finans_price_ore(); $pct = 0; $tier = null;
	foreach (bp_tiers() as $t) if ($n >= (int)$t['min_count'] && ($t['max_count'] === null || $n <= (int)$t['max_count'])) { $pct = (float)$t['discount_pct']; $tier = $t; }
	$total = (int)round($n * $list * (1 - $pct / 100)); return array($n, $list, $pct, $total, $n ? (int)round($total / $n) : 0, $tier);
}
function bp_scheme_rows() { return array_filter(bp_customers(false, true), fn($r) => $r['payer'] == 'partner' && !$r['closed']); }
function bp_kr($ore) { return number_format($ore / 100, 0, ',', '.').' kr.'; }

// ---------- invitations (employees and customer users) ----------
function bp_invite_create($kind, $rid, $name, $email, $opt = array()) {
	bp_tables_ensure(); $email = trim((string)$email); $name = trim((string)$name);
	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return array(0, bp_t('Skriv en gyldig e-mail','Enter a valid e-mail'));
	if (!in_array($kind, array('employee', 'customer_user'), true)) return array(0, 'Ukendt type');
	if ($kind == 'customer_user' && !partner_can((int)$rid, 'open')) return array(0, bp_t('Regnskabet er ikke tilknyttet','The account is not linked'));
	if (db_fetch_array(db_select("select 1 from partner_invites where partner_id = '".bp_pid()."' and kind = '$kind' and email = '".db_escape_string($email)."' and status = 'sent' and expires_at >= current_date".($rid ? " and regnskab_id = '".(int)$rid."'" : ""), __FILE__ . " linje " . __LINE__))) return array(0, bp_t('Der er allerede en åben invitation til den e-mail','There is already an open invitation for that e-mail'));
	$tok = bp_token(); $exp = date('Y-m-d', strtotime('+'.BP_INVITE_DAYS.' days'));
	$r = db_fetch_array(db_select("insert into partner_invites (partner_id, kind, regnskab_id, name, email, partner_role, rights, scope, role, message, token, created_by, sent_at, expires_at, replaces_user_id) values ('".bp_pid()."', '$kind', ".($rid ? "'".(int)$rid."'" : 'NULL').", '".db_escape_string($name)."', '".db_escape_string($email)."', '".db_escape_string((string)($opt['partner_role'] ?? 'employee'))."', '".db_escape_string((string)($opt['rights'] ?? 'work'))."', '".db_escape_string((string)($opt['scope'] ?? 'all'))."', '".db_escape_string((string)($opt['role'] ?? 'all'))."', '".db_escape_string((string)($opt['message'] ?? ''))."', '$tok', '".(int)bp_me()['id']."', now(), '$exp', ".(!empty($opt['replaces']) ? "'".(int)$opt['replaces']."'" : 'NULL').") returning id", __FILE__ . " linje " . __LINE__));
	$id = (int)$r['id']; bp_invite_mail($id);
	bp_log('partner.invite_sent', $rid ?: null, "$kind: $name <$email>".(!empty($opt['replaces']) ? ' (udskifter bruger '.(int)$opt['replaces'].')' : ''));
	return array($id, '');
}
function bp_invite_mail($id) {
	$i = db_fetch_array(db_select("select i.*, p.name as pname, r.regnskab from partner_invites i join partners p on p.id = i.partner_id left join regnskab r on r.id = i.regnskab_id where i.id = '".(int)$id."'", __FILE__ . " linje " . __LINE__)); if (!$i) return;
	$url = bp_url('bogholder_godkend.php', array('t' => $i['token']));
	if ($i['kind'] == 'employee') $html = bp_mail_wrap(bp_t('Du er inviteret som medarbejder hos','You are invited as an employee of').' '.$i['pname'], array(bp_h(bp_display_name()).' '.bp_t('har inviteret dig til at arbejde i','has invited you to work in').' '.bp_h($i['pname']).bp_t('s kunderegnskaber i Saldi. Du vælger selv brugernavn og adgangskode.','\'s customer accounts in Saldi. You choose your own username and password.'), $i['message'] ? '<i>'.bp_h($i['message']).'</i>' : '', bp_t('Linket virker til','The link works until').' '.bp_date($i['expires_at']).'.'), bp_t('Opret dit login','Create your login'), $url);
	else $html = bp_mail_wrap(bp_t('Dit login til','Your login to').' '.$i['regnskab'].' '.bp_t('i Saldi','in Saldi'), array(bp_h($i['pname']).' '.bp_t('har oprettet adgang til dig i regnskabet','has set up access for you in the account').' <b>'.bp_h($i['regnskab']).'</b>. '.bp_t('Du vælger selv brugernavn og adgangskode.','You choose your own username and password.'), $i['message'] ? '<i>'.bp_h($i['message']).'</i>' : '', bp_t('Linket virker til','The link works until').' '.bp_date($i['expires_at']).'.'), bp_t('Opret dit login','Create your login'), $url);
	bp_mail($i['email'], ($i['kind'] == 'employee' ? bp_t('Invitation til','Invitation to').' '.$i['pname'] : 'Saldi: '.$i['regnskab']), $html);
}
function bp_invite_resend($id) { $i = bp_invite_get($id); if (!$i || !in_array($i['status'], array('sent', 'expired'), true)) return bp_t('Invitationen er ikke åben','The invitation is not open'); db_modify("update partner_invites set status = 'sent', expires_at = '".date('Y-m-d', strtotime('+'.BP_INVITE_DAYS.' days'))."', sent_at = now() where id = '".(int)$id."'", __FILE__ . " linje " . __LINE__); bp_invite_mail($id); bp_log('partner.invite_resent', $i['regnskab_id'] ?: null, $i['email']); return ''; }
function bp_invite_revoke($id) { $i = bp_invite_get($id); if (!$i || $i['status'] != 'sent') return bp_t('Invitationen er ikke åben','The invitation is not open'); db_modify("update partner_invites set status = 'revoked' where id = '".(int)$id."'", __FILE__ . " linje " . __LINE__); bp_log('partner.invite_revoked', $i['regnskab_id'] ?: null, $i['email']); return ''; }
// An employee invitation's scope: 'all', or 'sel:' + ledger ids (may be empty = no customers yet). Older rows hold the bare id list.
function bp_invite_scope($s) {
	if ($s === null || $s === 'all') return array('all', array());
	return array('selected', array_values(array_filter(array_map('intval', explode(',', preg_replace('/^sel:/', '', (string)$s))))));
}
function bp_invite_get($id) { $i = db_fetch_array(db_select("select * from partner_invites where id = '".(int)$id."' and partner_id = '".bp_pid()."'", __FILE__ . " linje " . __LINE__)); return ($i && (!$i['regnskab_id'] || partner_can((int)$i['regnskab_id'], 'open'))) ? $i : null; }
function bp_invites($status = null) { bp_tables_ensure(); db_modify("update partner_invites set status = 'expired' where status = 'sent' and expires_at < current_date", __FILE__ . " linje " . __LINE__); $o = array(); $q = db_select("select i.*, r.regnskab, b.brugernavn as by_login from partner_invites i left join regnskab r on r.id = i.regnskab_id left join brugere b on b.id = i.created_by where i.partner_id = '".bp_pid()."'".($status ? " and i.status = '".db_escape_string($status)."'" : "")." order by i.id desc", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) if (!$r['regnskab_id'] || partner_can((int)$r['regnskab_id'], 'open')) $o[] = $r; return $o; }

// ---------- access requests to existing ledgers ----------
// Find a ledger by number, name or (cached) CVR. Returns [row|null, note]
function bp_find_ledger($s) {
	global $sqdb; $s = trim((string)$s); if ($s === '') return array(null, bp_t('Skriv regnskabsnummer, navn eller CVR','Enter account number, name or CVR'));
	$w = "db != '".db_escape_string($sqdb)."' and coalesce(lukket,'') != 'on'";
	if (ctype_digit($s) && strlen($s) == 8) { $c = db_fetch_array(db_select("select regnskab_id from partner_ledger_cache where cvr = '".db_escape_string($s)."' limit 1", __FILE__ . " linje " . __LINE__)); if ($c) { $r = db_fetch_array(db_select("select id, regnskab, email from regnskab where id = '".(int)$c['regnskab_id']."' and $w", __FILE__ . " linje " . __LINE__)); if ($r) return array($r, ''); } return array(null, bp_t('Ingen kunde med det CVR-nummer er kendt. Prøv regnskabsnummer eller navn.','No customer with that CVR is known. Try the account number or name.')); }
	if (ctype_digit($s)) { $r = db_fetch_array(db_select("select id, regnskab, email from regnskab where id = '".(int)$s."' and $w", __FILE__ . " linje " . __LINE__)); return $r ? array($r, '') : array(null, bp_t('Intet regnskab med det nummer','No account with that number')); }
	$q = db_select("select id, regnskab, email from regnskab where regnskab ilike '".db_escape_string($s)."' and $w", __FILE__ . " linje " . __LINE__); $rows = array(); while ($r = db_fetch_array($q)) $rows[] = $r;
	if (count($rows) == 1) return array($rows[0], ''); if (count($rows) > 1) return array(null, bp_t('Flere regnskaber matcher – brug regnskabsnummeret','Several accounts match – use the account number'));
	return array(null, bp_t('Intet regnskab med det navn. Navnet skal skrives præcist, som kunden har det i Saldi.','No account with that name. The name must match the customer\'s name in Saldi exactly.'));
}
function bp_access_request_create($rid, $message) {
	bp_tables_ensure(); $rid = (int)$rid; $pid = bp_pid();
	$reg = db_fetch_array(db_select("select id, regnskab, email from regnskab where id = '$rid'", __FILE__ . " linje " . __LINE__)); if (!$reg) return array(0, bp_t('Regnskabet findes ikke','Account not found'));
	if (partner_link($rid)) return array(0, bp_t('I har allerede adgang til regnskabet','You already have access to the account'));
	if (db_fetch_array(db_select("select 1 from partner_access_requests where partner_id = '$pid' and regnskab_id = '$rid' and status = 'open'", __FILE__ . " linje " . __LINE__))) return array(0, bp_t('Der er allerede en åben anmodning','There is already an open request'));
	$to = filter_var((string)$reg['email'], FILTER_VALIDATE_EMAIL) ? $reg['email'] : bp_saldi_mail();
	$tok = bp_token(); $exp = date('Y-m-d', strtotime('+'.BP_INVITE_DAYS.' days'));
	$r = db_fetch_array(db_select("insert into partner_access_requests (partner_id, regnskab_id, requested_by, message, token, sent_to, sent_at, expires_at) values ('$pid', '$rid', '".(int)bp_me()['id']."', '".db_escape_string($message)."', '$tok', '".db_escape_string($to)."', now(), '$exp') returning id", __FILE__ . " linje " . __LINE__));
	bp_access_request_mail((int)$r['id']);
	bp_log('partner.access_requested', $rid, $reg['regnskab'].' → '.$to.($message ? ' – '.$message : ''));
	return array((int)$r['id'], $to == $reg['email'] ? '' : bp_t('Regnskabet har ingen e-mail – anmodningen er sendt til Saldi, som godkender på kundens vegne.','The account has no e-mail – the request went to Saldi, who approves on the customer\'s behalf.'));
}
function bp_access_request_mail($id) {
	$a = db_fetch_array(db_select("select a.*, p.name as pname, p.cvr as pcvr, p.kind as pkind, r.regnskab from partner_access_requests a join partners p on p.id = a.partner_id join regnskab r on r.id = a.regnskab_id where a.id = '".(int)$id."'", __FILE__ . " linje " . __LINE__)); if (!$a) return;
	$url = bp_url('bogholder_godkend.php', array('t' => $a['token']));
	if ($a['pkind'] == 'koncern') { bp_mail($a['sent_to'], bp_t('Tilknytning af','Linking').' '.$a['regnskab'].' '.bp_t('til','to').' '.$a['pname'], bp_mail_wrap(bp_h($a['regnskab']).' '.bp_t('tilknyttes koncernen','joins the group').' '.bp_h($a['pname']), array(bp_h($a['pname']).' '.bp_t('vil tilknytte regnskabet','wants to link the account').' <b>'.bp_h($a['regnskab']).'</b> '.bp_t('som et selskab i koncernen. Godkender I, ejer koncernen regnskabet i Saldi og kan styre brugere og pakke. Betalingen ændres ikke, før koncernen vælger det.','as a company in the group. If you approve, the group owns the account in Saldi and can manage users and plan. Payment does not change until the group chooses so.'), $a['message'] ? '<i>'.bp_h($a['message']).'</i>' : '', bp_t('Linket virker til','The link works until').' '.bp_date($a['expires_at']).'.'), bp_t('Se anmodningen','View the request'), $url)); return; }
	$html = bp_mail_wrap(bp_h($a['pname']).' '.bp_t('beder om adgang til','requests access to').' '.bp_h($a['regnskab']), array(bp_h($a['pname']).($a['pcvr'] ? ' (CVR '.bp_h($a['pcvr']).')' : '').' '.bp_t('vil gerne arbejde i jeres regnskab','would like to work in your account').' <b>'.bp_h($a['regnskab']).'</b> '.bp_t('i Saldi. Giver du adgang, kan deres medarbejdere åbne og bogføre i regnskabet. Pakke, betaling og andre bogholderes adgang ændres ikke.','in Saldi. If you approve, their employees can open and post in the account. Plan, payment and other bookkeepers\' access are not changed.'), $a['message'] ? '<i>'.bp_h($a['message']).'</i>' : '', bp_t('Du kan altid fjerne adgangen igen. Linket virker til','You can always remove the access again. The link works until').' '.bp_date($a['expires_at']).'.'), bp_t('Se anmodningen','View the request'), $url);
	bp_mail($a['sent_to'], bp_t('Anmodning om adgang til','Access request for').' '.$a['regnskab'], $html);
}
function bp_access_requests($status = null) { bp_tables_ensure(); db_modify("update partner_access_requests set status = 'expired', decided_at = now() where status = 'open' and expires_at < current_date", __FILE__ . " linje " . __LINE__); $o = array(); $q = db_select("select a.*, r.regnskab, b.brugernavn as by_login from partner_access_requests a join regnskab r on r.id = a.regnskab_id left join brugere b on b.id = a.requested_by where a.partner_id = '".bp_pid()."'".($status ? " and a.status = '".db_escape_string($status)."'" : "")." order by a.id desc", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $o[] = $r; return $o; }
function bp_access_request_resend($id) { $a = db_fetch_array(db_select("select * from partner_access_requests where id = '".(int)$id."' and partner_id = '".bp_pid()."' and status = 'open'", __FILE__ . " linje " . __LINE__)); if (!$a) return bp_t('Anmodningen er ikke åben','The request is not open'); db_modify("update partner_access_requests set expires_at = '".date('Y-m-d', strtotime('+'.BP_INVITE_DAYS.' days'))."', sent_at = now() where id = '".(int)$id."'", __FILE__ . " linje " . __LINE__); bp_access_request_mail((int)$id); bp_log('partner.access_request_resent', (int)$a['regnskab_id'], $a['sent_to']); return ''; }
function bp_access_request_cancel($id) { $a = db_fetch_array(db_select("select * from partner_access_requests where id = '".(int)$id."' and partner_id = '".bp_pid()."' and status = 'open'", __FILE__ . " linje " . __LINE__)); if (!$a) return bp_t('Anmodningen er ikke åben','The request is not open'); db_modify("update partner_access_requests set status = 'cancelled', decided_at = now(), decided_by = '".db_escape_string(bp_me()['brugernavn'])."' where id = '".(int)$id."'", __FILE__ . " linje " . __LINE__); bp_log('partner.access_request_cancelled', (int)$a['regnskab_id'], ''); return ''; }
// Customer (or Saldi) decides via the token page. $by = text for the log.
function bp_access_request_decide($token, $accept, $by) {
	$a = db_fetch_array(db_select("select a.*, p.name as pname from partner_access_requests a join partners p on p.id = a.partner_id where a.token = '".db_escape_string($token)."' and a.status = 'open'", __FILE__ . " linje " . __LINE__)); if (!$a) return bp_t('Anmodningen er ikke længere åben','The request is no longer open');
	if ($a['expires_at'] < date('Y-m-d')) { db_modify("update partner_access_requests set status = 'expired', decided_at = now() where id = '".(int)$a['id']."'", __FILE__ . " linje " . __LINE__); return bp_t('Anmodningen er udløbet','The request has expired'); }
	$rid = (int)$a['regnskab_id']; $pid = (int)$a['partner_id'];
	if ($accept && !db_fetch_array(db_select("select 1 from regnskab_partners where regnskab_id = '$rid' and partner_id = '$pid' and until is null", __FILE__ . " linje " . __LINE__))) {
		$kind = db_fetch_array(db_select("select kind from partners where id = '$pid'", __FILE__ . " linje " . __LINE__));
		$acc = ($kind && $kind['kind'] == 'koncern' && !db_fetch_array(db_select("select 1 from regnskab_partners where regnskab_id = '$rid' and access = 'owner' and until is null", __FILE__ . " linje " . __LINE__))) ? 'owner' : 'full'; # a group owns the companies it takes in
		db_modify("insert into regnskab_partners (regnskab_id, partner_id, access, created_by, note, payer) values ('$rid', '$pid', '$acc', ".((int)$a['requested_by'] ?: 'NULL').", 'godkendt af $by', 'customer')", __FILE__ . " linje " . __LINE__);
		partner_log('partner.link_added', $rid, $pid, $a['pname']." ($acc) – ".bp_t('adgang godkendt af','access approved by').' '.$by);
		if ($acc == 'owner') bp_set_payer($rid, $pid, 'partner', bp_t('selskabet er optaget i koncernen','the company joined the group')); # group rule: invoiced via the parent unless changed
	}
	db_modify("update partner_access_requests set status = '".($accept ? 'approved' : 'rejected')."', decided_at = now(), decided_by = '".db_escape_string($by)."' where id = '".(int)$a['id']."'", __FILE__ . " linje " . __LINE__);
	partner_log($accept ? 'partner.access_approved' : 'partner.access_rejected', $rid, $pid, $by);
	return '';
}

// ---------- requests to Saldi and the customer (payer, cancellation, archiving) ----------
function bp_request_kinds() { return array('payer_to_partner' => array(bp_t('Firmaet overtager betalingen','The firm takes over payment'), bp_t('Kundens Finans-abonnement flyttes ind i jeres bogholderordning. I faktureres fra næste periode; kundens egen fakturering stopper. Saldi gennemfører skiftet.','The customer\'s Finans subscription moves into your scheme. You are invoiced from next period; the customer\'s own invoicing stops. Saldi completes the change.')), 'payer_to_customer' => array(bp_t('Kunden overtager betalingen','The customer takes over payment'), bp_t('Regnskabet forlader jeres ordning, og kunden faktureres selv fra næste periode. Kunden skal acceptere via e-mail, før skiftet sker. Jeres mængderabat beregnes på ét regnskab mindre.','The account leaves your scheme and the customer is invoiced from next period. The customer must accept by e-mail first. Your volume discount is recalculated on one account less.')), 'cancel_subscription' => array(bp_t('Opsig abonnementet','Cancel the subscription'), bp_t('Abonnementet ophører ved periodens udløb, og regnskabet lukkes derefter. Data bevares, men ingen kan logge ind. Saldi gennemfører opsigelsen og bekræfter datoen.','The subscription ends at period end and the account is then closed. Data is kept, but no one can log in. Saldi completes the cancellation and confirms the date.')), 'archive' => array(bp_t('Arkivér regnskabet','Archive the account'), bp_t('Regnskabet lukkes for login og bevares som arkiv. Et betalende abonnement skal opsiges først. Saldi gennemfører arkiveringen.','The account is closed for login and kept as an archive. A paid subscription must be cancelled first. Saldi completes the archiving.'))); }
function bp_request_label($kind) { $k = bp_request_kinds(); return isset($k[$kind]) ? $k[$kind][0] : $kind; }
function bp_request_create($rid, $kind, $details, $date = '') {
	bp_tables_ensure(); $rid = (int)$rid; $pid = bp_pid(); $kinds = bp_request_kinds();
	if (!isset($kinds[$kind])) return bp_t('Ukendt handling','Unknown action');
	if (!partner_link($rid)) return bp_t('Regnskabet er ikke tilknyttet','The account is not linked');
	if (db_fetch_array(db_select("select 1 from partner_requests where partner_id = '$pid' and regnskab_id = '$rid' and kind = '$kind' and status = 'open'", __FILE__ . " linje " . __LINE__))) return bp_t('Der er allerede en åben anmodning af den type','There is already an open request of that type');
	if (trim((string)$details) === '') return bp_t('Begrundelse er påkrævet','A reason is required');
	$reg = db_fetch_array(db_select("select regnskab, email from regnskab where id = '$rid'", __FILE__ . " linje " . __LINE__));
	$toCustomer = ($kind == 'payer_to_customer'); $to = $toCustomer ? (filter_var((string)$reg['email'], FILTER_VALIDATE_EMAIL) ? $reg['email'] : '') : bp_saldi_mail();
	if ($toCustomer && !$to) return bp_t('Regnskabet har ingen e-mail, så kunden kan ikke acceptere. Bed Saldi om hjælp.','The account has no e-mail, so the customer cannot accept. Ask Saldi for help.');
	$tok = $toCustomer ? bp_token() : null; $ef = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date) ? "'$date'" : 'NULL';
	$r = db_fetch_array(db_select("insert into partner_requests (partner_id, regnskab_id, kind, details, effective_from, token, sent_to, created_by) values ('$pid', '$rid', '$kind', '".db_escape_string($details)."', $ef, ".($tok ? "'$tok'" : 'NULL').", '".db_escape_string($to)."', '".(int)bp_me()['id']."') returning id", __FILE__ . " linje " . __LINE__));
	$p = bp_partner();
	if ($toCustomer) bp_mail($to, bp_t('Betaling for','Payment for').' '.$reg['regnskab'].' '.bp_t('i Saldi','in Saldi'), bp_mail_wrap(bp_h($p['name']).' '.bp_t('foreslår, at I selv betaler for','proposes that you pay for').' '.bp_h($reg['regnskab']), array(bp_h($p['name']).' '.bp_t('betaler i dag for jeres Saldi-abonnement. De foreslår, at I overtager betalingen fra næste periode. Accepterer I, fakturerer Saldi jer direkte for pakken','pays for your Saldi subscription today. They propose that you take over payment from next period. If you accept, Saldi invoices you directly for the plan').' <b>'.bp_h(bp_customer($rid)['plan_name'] ?: 'Finans').'</b>.', '<i>'.bp_h($details).'</i>', bp_t('Intet ændres, før I accepterer.','Nothing changes until you accept.')), bp_t('Se forslaget','View the proposal'), bp_url('bogholder_godkend.php', array('t' => $tok))));
	else bp_mail($to, '[Bogholderpanel] '.$kinds[$kind][0].': '.$reg['regnskab'], bp_mail_wrap($kinds[$kind][0].' – '.bp_h($reg['regnskab']).' (id '.$rid.')', array(bp_t('Fra','From').': '.bp_h($p['name']).' / '.bp_h(bp_display_name()), ($ef != 'NULL' ? bp_t('Ønsket dato','Requested date').': '.bp_date($date).'<br>' : '').bp_t('Begrundelse','Reason').': '.bp_h($details), bp_t('Udføres i operatørpanelet; anmodningen ligger i partner_requests id','Handled in the operator panel; the request is partner_requests id').' '.(int)$r['id'].'.')));
	bp_log('partner.request_created', $rid, $kinds[$kind][0].($ef != 'NULL' ? " fra $date" : '').' – '.$details.' → '.$to);
	return '';
}
function bp_requests($rid = null, $status = null) { bp_tables_ensure(); $o = array(); $q = db_select("select q.*, r.regnskab, b.brugernavn as by_login from partner_requests q join regnskab r on r.id = q.regnskab_id left join brugere b on b.id = q.created_by where q.partner_id = '".bp_pid()."'".($rid ? " and q.regnskab_id = '".(int)$rid."'" : "").($status ? " and q.status = '".db_escape_string($status)."'" : "")." order by q.id desc", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $o[] = $r; return $o; }
function bp_request_cancel($id) { $r = db_fetch_array(db_select("select * from partner_requests where id = '".(int)$id."' and partner_id = '".bp_pid()."' and status = 'open'", __FILE__ . " linje " . __LINE__)); if (!$r) return bp_t('Anmodningen er ikke åben','The request is not open'); db_modify("update partner_requests set status = 'cancelled', decided_at = now(), decided_by = '".db_escape_string(bp_me()['brugernavn'])."' where id = '".(int)$id."'", __FILE__ . " linje " . __LINE__); bp_log('partner.request_cancelled', (int)$r['regnskab_id'], bp_request_label($r['kind'])); return ''; }
// Customer accepts a payer change via the token page.
function bp_request_decide($token, $accept, $by) {
	$r = db_fetch_array(db_select("select q.*, p.name as pname from partner_requests q join partners p on p.id = q.partner_id where q.token = '".db_escape_string($token)."' and q.status = 'open'", __FILE__ . " linje " . __LINE__)); if (!$r) return bp_t('Forslaget er ikke længere åbent','The proposal is no longer open');
	$rid = (int)$r['regnskab_id']; $pid = (int)$r['partner_id'];
	if ($accept) { bp_set_payer($rid, $pid, 'customer', $by); }
	db_modify("update partner_requests set status = '".($accept ? 'approved' : 'rejected')."', decided_at = now(), decided_by = '".db_escape_string($by)."' where id = '".(int)$r['id']."'", __FILE__ . " linje " . __LINE__);
	partner_log($accept ? 'partner.request_approved' : 'partner.request_rejected', $rid, $pid, bp_request_label($r['kind']).' – '.$by);
	if ($accept) bp_mail(bp_saldi_mail(), '[Bogholderpanel] Betaler skiftet til kunden: regnskab '.$rid, bp_mail_wrap('Kunden overtager betalingen', array('Regnskab id '.$rid.' – kunden har accepteret at overtage betalingen fra '.bp_h($r['pname']).'. Opdatér faktureringen i faktureringsregnskabet.')));
	return '';
}
// Set who pays on the link (the panel's own flag) and mirror it to the operator's payer where possible.
function bp_set_payer($rid, $pid, $payer, $by) {
	db_modify("update regnskab_partners set payer = '".($payer == 'partner' ? 'partner' : 'customer')."' where regnskab_id = '".(int)$rid."' and partner_id = '".(int)$pid."' and until is null", __FILE__ . " linje " . __LINE__);
	$p = db_fetch_array(db_select("select * from partners where id = '".(int)$pid."'", __FILE__ . " linje " . __LINE__));
	if (!function_exists('ent_actor')) include_once(__DIR__.'/entitlements.php');
	if (!function_exists('bill_set_payer')) include_once(__DIR__.'/../admin/inc_betalinger.php');
	{ bill_set_payer((int)$rid, ($payer == 'partner' && $p && $p['home_regnskab_id']) ? (int)$p['home_regnskab_id'] : 0);
		if ($payer != 'partner' && $p && ($p['billing_mode'] ?? '') == 'consolidated') db_modify("update regnskab_billing set payer_regnskab_id = '".(int)$rid."' where regnskab_id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__); } # the company itself, overriding the partner's consolidated model
	$isK = ($p && $p['kind'] == 'koncern');
	partner_log('partner.payer_changed', (int)$rid, (int)$pid, ($payer == 'partner' ? ($isK ? 'faktureres via hovedselskabet' : 'bogholderfirmaet betaler') : ($isK ? 'selskabet betaler selv' : 'kunden betaler selv')).' – '.$by);
}
// An accepted upgrade proposed by the partner moves future payment to the customer (requirement: upgrade from Finans).
function bp_sync_proposals() {
	if (bp_is_group()) return;
	if (!db_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = 'partner_proposals'", __FILE__ . " linje " . __LINE__))) return;
	$pid = bp_pid(); $q = db_select("select pp.id, pp.regnskab_id, pp.key from partner_proposals pp join regnskab_partners rp on rp.regnskab_id = pp.regnskab_id and rp.partner_id = pp.partner_id and rp.until is null where pp.partner_id = '$pid' and pp.kind = 'plan' and pp.status = 'accepted' and rp.payer = 'partner' and pp.decided_at >= rp.since and not exists (select 1 from partner_log l where l.action = 'partner.proposal_payer_applied' and l.details = 'proposal ' || pp.id)", __FILE__ . " linje " . __LINE__);
	$rows = array(); while ($r = db_fetch_array($q)) $rows[] = $r;
	foreach ($rows as $r) { partner_log('partner.proposal_payer_applied', (int)$r['regnskab_id'], $pid, 'proposal '.(int)$r['id']); bp_set_payer((int)$r['regnskab_id'], $pid, 'customer', 'godkendt opgradering til '.$r['key']); bp_mail(bp_saldi_mail(), '[Bogholderpanel] Opgradering godkendt – kunden betaler selv: regnskab '.(int)$r['regnskab_id'], bp_mail_wrap('Opgradering godkendt', array('Regnskab id '.(int)$r['regnskab_id'].' er opgraderet til '.bp_h($r['key']).' efter forslag fra bogholderen. Fremtidig betaling flyttes til kunden – opdatér faktureringen.'))); }
}

// ---------- the customer's users (in the customer database) ----------
function bp_ledger_users($reg) {
	global $sqhost, $squser, $sqpass; $o = array('ok' => false, 'users' => array());
	if (!$reg['db'] || !db_exists($reg['db']) || !@db_connect($sqhost, $squser, $sqpass, $reg['db'], __FILE__ . " linje " . __LINE__)) return $o;
	$o['ok'] = true;
	if (tbl_exists('brugere')) {
		$cols = array(); $q = db_select("select column_name from information_schema.columns where table_name = 'brugere'", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $cols[] = $r['column_name'];
		$sel = array('id', 'brugernavn', 'rettigheder'); foreach (array('email', 'tlf', 'twofactor', 'revisor', 'ansat_id') as $c) $sel[] = in_array($c, $cols) ? $c : "NULL as $c";
		$q = db_select("select ".implode(', ', $sel)." from brugere order by brugernavn", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) { $r['revisor'] = bp_bool($r['revisor']); $r['twofactor'] = bp_bool($r['twofactor']); $rt = (string)$r['rettigheder']; $r['level'] = (strlen($rt) > 10 && strpos($rt, '1') === false && strpos($rt, '2') === false) ? 'locked' : ((strlen($rt) > 10 && strpos($rt, '0') === false && strpos($rt, '2') === false) ? 'all' : ((strlen($rt) > 10 && strpos($rt, '1') === false) ? 'readonly' : 'limited')); $o['users'][] = $r; }
	}
	include("../includes/connect.php"); return $o;
}
function bp_ledger_user_create($reg, $un, $pw, $email, $tlf, $role) {
	global $sqhost, $squser, $sqpass; $un = trim((string)$un);
	if ($un === '' || strlen($pw) < 6) return array(0, bp_t('Brugernavn og adgangskode (mindst 6 tegn) er påkrævet','Username and password (at least 6 characters) are required'));
	if (!bp_login_name_ok($un)) return array(0, bp_login_name_msg());
	$max = bp_ledger_max_users($reg); # on master, before switching to the customer database
	if (!$reg['db'] || !db_exists($reg['db']) || !@db_connect($sqhost, $squser, $sqpass, $reg['db'], __FILE__ . " linje " . __LINE__)) return array(0, bp_t('Kunne ikke forbinde til regnskabet','Could not connect to the account'));
	$fail = function($m) { include("../includes/connect.php"); return array(0, $m); };
	if (db_fetch_array(db_select("select id from brugere where brugernavn = '".db_escape_string($un)."'", __FILE__ . " linje " . __LINE__))) return $fail(bp_t('Brugernavnet findes allerede i regnskabet','The username already exists in the account'));
	if ($max !== null && (int)db_fetch_array(db_select("select count(*) as n from brugere", __FILE__ . " linje " . __LINE__))['n'] >= $max) return $fail(bp_t('Pakken har ikke flere brugere. Udskift en bruger, eller foreslå en større pakke.','The plan has no more users. Replace a user, or propose a larger plan.'));
	$rights = str_repeat('1', 21); # Saldi has no read-only rights level; the customer's administrator narrows the rights per module
	$ry = db_fetch_array(db_select("select max(kodenr) as k from grupper where art = 'RA'", __FILE__ . " linje " . __LINE__)); $aar = ($ry && $ry['k']) ? (int)$ry['k'] : 1;
	$r = db_fetch_array(db_select("insert into brugere (brugernavn, kode, rettigheder, regnskabsaar, email, tlf) values ('".db_escape_string($un)."', '', '$rights', '$aar', '".db_escape_string(trim((string)$email))."', '".db_escape_string(trim((string)$tlf))."') returning id", __FILE__ . " linje " . __LINE__)); $nid = (int)$r['id'];
	db_modify("update brugere set kode = '".db_escape_string(saldikrypt($nid, $pw))."' where id = '$nid'", __FILE__ . " linje " . __LINE__);
	include("../includes/connect.php");
	bp_log('ledger.user_created', (int)$reg['id'], "$un (alle rettigheder)");
	return array($nid, '');
}
// Users the customer's plan allows (null = no limit). Accounts without a plan keep the old behaviour: regnskab.brugerantal
// was never enforced, and older accounts hold more users than it says.
function bp_ledger_max_users($reg) {
	if (!function_exists('ent_resolve')) include_once(__DIR__.'/entitlements.php');
	$ent = ent_resolve((int)$reg['id']); return ($ent['plan'] && $ent['limits']['max_users'] !== null) ? (int)$ent['limits']['max_users'] : null;
}
// Sign the firm's employees out of a customer account (they came in through the panel, so the session is marked revisor).
function bp_end_partner_sessions($rdb, $pid, $bid = 0) {
	if (!$rdb) return;
	db_modify("delete from online where db = '".db_escape_string($rdb)."' and revisor and brugernavn in (select b.brugernavn from brugere b join partner_users pu on pu.bruger_id = b.id where pu.partner_id = '".(int)$pid."'".($bid ? " and pu.bruger_id = '".(int)$bid."'" : "").")", __FILE__ . " linje " . __LINE__);
}
// Lock = no module access (reversible, kept in the account); unlock = all rights again, the customer's admin narrows them.
function bp_ledger_user_set($reg, $uid, $mode) {
	global $sqhost, $squser, $sqpass; $uid = (int)$uid;
	if (!$reg['db'] || !db_exists($reg['db']) || !@db_connect($sqhost, $squser, $sqpass, $reg['db'], __FILE__ . " linje " . __LINE__)) return bp_t('Kunne ikke forbinde til regnskabet','Could not connect to the account');
	$u = db_fetch_array(db_select("select brugernavn, rettigheder from brugere where id = '$uid'", __FILE__ . " linje " . __LINE__));
	if (!$u) { include("../includes/connect.php"); return bp_t('Brugeren findes ikke','User not found'); }
	$n = max(21, strlen((string)$u['rettigheder'])); db_modify("update brugere set rettigheder = '".str_repeat($mode == 'lock' ? '0' : '1', $n)."' where id = '$uid'", __FILE__ . " linje " . __LINE__);
	include("../includes/connect.php"); # back on master before touching online (the $global flag only works when $db was switched)
	if ($mode == 'lock') db_modify("delete from online where db = '".db_escape_string($reg['db'])."' and brugernavn = '".db_escape_string($u['brugernavn'])."'", __FILE__ . " linje " . __LINE__);
	bp_log($mode == 'lock' ? 'ledger.user_locked' : 'ledger.user_unlocked', (int)$reg['id'], $u['brugernavn']);
	return '';
}
function bp_ledger_company_save($reg, $f) {
	global $sqhost, $squser, $sqpass;
	if (!$reg['db'] || !db_exists($reg['db']) || !@db_connect($sqhost, $squser, $sqpass, $reg['db'], __FILE__ . " linje " . __LINE__)) return bp_t('Kunne ikke forbinde til regnskabet','Could not connect to the account');
	$set = array(); foreach (array('firmanavn' => 60, 'addr1' => 60, 'postnr' => 10, 'bynavn' => 60, 'kontakt' => 60, 'tlf' => 20, 'email' => 120, 'cvrnr' => 20) as $k => $len) if (isset($f[$k])) $set[] = "$k = '".db_escape_string(mb_substr(trim((string)$f[$k]), 0, $len))."'";
	$row = db_fetch_array(db_select("select id from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__));
	if ($row) db_modify("update adresser set ".implode(', ', $set)." where id = '".(int)$row['id']."'", __FILE__ . " linje " . __LINE__); else db_modify("insert into adresser (art, ".implode(', ', array_keys($f)).") values ('S', '".implode("', '", array_map('db_escape_string', array_values($f)))."')", __FILE__ . " linje " . __LINE__);
	include("../includes/connect.php");
	db_modify("update partner_ledger_cache set checked_at = NULL where regnskab_id = '".(int)$reg['id']."'", __FILE__ . " linje " . __LINE__);
	bp_log('ledger.company_updated', (int)$reg['id'], implode(', ', array_keys($f)));
	return '';
}
function bp_set_responsible($rid, $bid) {
	$bid = (int)$bid; if ($bid && !db_fetch_array(db_select("select 1 from partner_users where bruger_id = '$bid' and partner_id = '".bp_pid()."'", __FILE__ . " linje " . __LINE__))) return bp_t('Medarbejderen hører ikke til firmaet','The employee does not belong to the firm');
	db_modify("update regnskab_partners set responsible_bruger_id = ".($bid ?: 'NULL')." where regnskab_id = '".(int)$rid."' and partner_id = '".bp_pid()."' and until is null", __FILE__ . " linje " . __LINE__);
	$n = $bid ? db_fetch_array(db_select("select b.brugernavn, pu.name from brugere b join partner_users pu on pu.bruger_id = b.id where b.id = '$bid'", __FILE__ . " linje " . __LINE__)) : null;
	bp_log('partner.responsible_set', (int)$rid, $n ? ($n['name'] ?: $n['brugernavn']) : 'ingen'); return '';
}

// ---------- employees ----------
function bp_employees() { bp_tables_ensure(); $o = array(); $q = db_select("select pu.*, b.brugernavn, b.email, (select count(*) from partner_user_access a where a.bruger_id = pu.bruger_id) as n_scope, (select count(*) from regnskab_partners rp where rp.responsible_bruger_id = pu.bruger_id and rp.partner_id = pu.partner_id and rp.until is null) as n_resp from partner_users pu join brugere b on b.id = pu.bruger_id where pu.partner_id = '".bp_pid()."' order by pu.partner_role desc, coalesce(pu.name, b.brugernavn)", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) { $r['active'] = bp_bool($r['active']) || $r['active'] === null; $r['rights_list'] = ($r['partner_role'] == 'owner') ? array_keys(partner_rights_all()) : (($r['rights'] === null || $r['rights'] === '') ? array('work') : array_filter(array_map('trim', explode(',', $r['rights'])))); $o[] = $r; } return $o; }
function bp_employee($bid) { foreach (bp_employees() as $e) if ((int)$e['bruger_id'] == (int)$bid) return $e; return null; }
function bp_employee_scope($bid) { $o = array(); $q = db_select("select regnskab_id from partner_user_access where bruger_id = '".(int)$bid."'", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $o[] = (int)$r['regnskab_id']; return $o; }
function bp_employee_save_scope($bid, $scope, $ids) {
	$bid = (int)$bid; if (!bp_employee($bid)) return bp_t('Medarbejderen hører ikke til firmaet','The employee does not belong to the firm');
	$mine = array_keys(bp_customers(true)); $ids = array_values(array_intersect(array_map('intval', (array)$ids), $mine));
	db_modify("delete from partner_user_access where bruger_id = '$bid'", __FILE__ . " linje " . __LINE__);
	if ($scope == 'selected') foreach ($ids as $rid) db_modify("insert into partner_user_access (bruger_id, regnskab_id) values ('$bid', '$rid')", __FILE__ . " linje " . __LINE__);
	db_modify("update partner_users set scope = '".($scope == 'selected' ? 'selected' : 'all')."' where bruger_id = '$bid'", __FILE__ . " linje " . __LINE__);
	if ($scope == 'selected') db_modify("delete from online where brugernavn = '".db_escape_string(bp_employee($bid)['brugernavn'])."' and revisor and db != '".db_escape_string($GLOBALS['sqdb'])."'", __FILE__ . " linje " . __LINE__);
	bp_log('partner.employee_access_changed', null, "bruger $bid: ".($scope == 'selected' ? count($ids).' udvalgte ('.implode(',', $ids).')' : 'alle')); return '';
}
function bp_employee_save($bid, $f) {
	$bid = (int)$bid; $e = bp_employee($bid); if (!$e) return bp_t('Medarbejderen hører ikke til firmaet','The employee does not belong to the firm');
	$rights = array_values(array_intersect(array_keys(partner_rights_all()), (array)($f['rights'] ?? array()))); $role = ($f['partner_role'] ?? 'employee') == 'owner' ? 'owner' : 'employee';
	if ($e['partner_role'] == 'owner' && $role != 'owner' && (int)db_fetch_array(db_select("select count(*) as n from partner_users where partner_id = '".bp_pid()."' and partner_role = 'owner' and coalesce(active, true)", __FILE__ . " linje " . __LINE__))['n'] <= 1) return bp_t('Firmaet skal have mindst én ejer','The firm must keep at least one owner');
	db_modify("update partner_users set name = '".db_escape_string(mb_substr(trim((string)($f['name'] ?? '')), 0, 120))."', partner_role = '$role', rights = '".(implode(',', $rights) ?: '-')."' where bruger_id = '$bid' and partner_id = '".bp_pid()."'", __FILE__ . " linje " . __LINE__);
	if (isset($f['email'])) db_modify("update brugere set email = '".db_escape_string(trim((string)$f['email']))."' where id = '$bid'", __FILE__ . " linje " . __LINE__);
	if ($role != 'owner' && !in_array('work', $rights, true)) db_modify("delete from online where brugernavn = '".db_escape_string($e['brugernavn'])."' and revisor and db != '".db_escape_string($GLOBALS['sqdb'])."'", __FILE__ . " linje " . __LINE__); # out of the customer accounts now
	bp_log('partner.employee_updated', null, $e['brugernavn'].": $role, ".implode(',', $rights)); return '';
}
function bp_employee_active($bid, $on) {
	$bid = (int)$bid; $e = bp_employee($bid); if (!$e) return bp_t('Medarbejderen hører ikke til firmaet','The employee does not belong to the firm');
	if ($bid == (int)bp_me()['id']) return bp_t('Du kan ikke deaktivere dig selv','You cannot deactivate yourself');
	db_modify("update partner_users set active = ".($on ? 'true' : 'false')." where bruger_id = '$bid' and partner_id = '".bp_pid()."'", __FILE__ . " linje " . __LINE__);
	if (!$on) db_modify("delete from online where brugernavn = '".db_escape_string($e['brugernavn'])."' and (db = '".db_escape_string($GLOBALS['sqdb'])."' or revisor)", __FILE__ . " linje " . __LINE__);
	bp_log($on ? 'partner.employee_activated' : 'partner.employee_deactivated', null, $e['brugernavn']); return '';
}
function bp_employee_remove($bid) {
	$bid = (int)$bid; $e = bp_employee($bid); if (!$e) return bp_t('Medarbejderen hører ikke til firmaet','The employee does not belong to the firm');
	if ($bid == (int)bp_me()['id']) return bp_t('Du kan ikke fjerne dig selv','You cannot remove yourself');
	db_modify("delete from partner_users where bruger_id = '$bid' and partner_id = '".bp_pid()."'", __FILE__ . " linje " . __LINE__);
	db_modify("delete from partner_user_access where bruger_id = '$bid'", __FILE__ . " linje " . __LINE__);
	db_modify("update regnskab_partners set responsible_bruger_id = NULL where responsible_bruger_id = '$bid' and partner_id = '".bp_pid()."'", __FILE__ . " linje " . __LINE__);
	db_modify("delete from online where brugernavn = '".db_escape_string($e['brugernavn'])."' and (db = '".db_escape_string($GLOBALS['sqdb'])."' or revisor)", __FILE__ . " linje " . __LINE__);
	bp_log('partner.employee_removed', null, $e['brugernavn']); return '';
}
// Create a master login + employee row directly (owner knows the password), or accept an invitation.
function bp_employee_create($username, $password, $name, $email, $role, $rights, $scope, $ids, $viaInvite = 0) {
	$username = trim((string)$username); if ($username === '' || is_input_too_long($username)) return array(0, bp_t('Brugernavnet mangler eller er for langt','Username missing or too long'));
	if (!bp_login_name_ok($username)) return array(0, bp_login_name_msg());
	if (strlen($password) < 8) return array(0, bp_t('Adgangskoden skal være mindst 8 tegn','The password must be at least 8 characters'));
	if (!partner_login_free($username)) return array(0, bp_t('Brugernavnet er optaget – vælg et andet','The username is taken – choose another'));
	$r = db_fetch_array(db_select("insert into brugere (brugernavn, rettigheder, status, email) values ('".db_escape_string($username)."', ',,,', true, '".db_escape_string(trim((string)$email))."') returning id", __FILE__ . " linje " . __LINE__)); $bid = (int)$r['id'];
	db_modify("update brugere set kode = '".db_escape_string(saldikrypt($bid, $password))."' where id = '$bid'", __FILE__ . " linje " . __LINE__);
	$rights = array_values(array_intersect(array_keys(partner_rights_all()), (array)$rights));
	db_modify("insert into partner_users (bruger_id, partner_id, partner_role, rights, scope, name) values ('$bid', '".bp_pid()."', '".($role == 'owner' ? 'owner' : 'employee')."', '".(implode(',', $rights) ?: '-')."', '".($scope == 'selected' ? 'selected' : 'all')."', '".db_escape_string(mb_substr(trim((string)$name), 0, 120))."')", __FILE__ . " linje " . __LINE__);
	if ($scope == 'selected') foreach (array_map('intval', (array)$ids) as $rid) if ($rid) db_modify("insert into partner_user_access (bruger_id, regnskab_id) values ('$bid', '$rid')", __FILE__ . " linje " . __LINE__);
	bp_log('partner.employee_added', null, "$username ($role; ".implode(',', $rights)."; ".($scope == 'selected' ? count((array)$ids).' udvalgte' : 'alle').")".($viaInvite ? " via invitation $viaInvite" : ''));
	return array($bid, '');
}

// ---------- texts for the log ----------
function bp_action_label($a) {
	static $m = null; if ($m === null) $m = array(
		'partner.link_added' => bp_t('Adgang til regnskab oprettet','Access to account created'), 'partner.link_removed' => bp_t('Adgang til regnskab afsluttet','Access to account ended'), 'partner.ledger_moved' => bp_t('Regnskab flyttet','Account moved'),
		'partner.employee_added' => bp_t('Medarbejder tilføjet','Employee added'), 'partner.employee_removed' => bp_t('Medarbejder fjernet','Employee removed'), 'partner.employee_updated' => bp_t('Medarbejder ændret','Employee updated'), 'partner.employee_access_changed' => bp_t('Kundeadgang ændret','Customer access changed'), 'partner.employee_deactivated' => bp_t('Medarbejder deaktiveret','Employee deactivated'), 'partner.employee_activated' => bp_t('Medarbejder aktiveret','Employee activated'),
		'partner.invite_sent' => bp_t('Invitation sendt','Invitation sent'), 'partner.invite_resent' => bp_t('Invitation gensendt','Invitation resent'), 'partner.invite_revoked' => bp_t('Invitation tilbagekaldt','Invitation revoked'), 'partner.invite_accepted' => bp_t('Invitation accepteret','Invitation accepted'),
		'partner.access_requested' => bp_t('Adgang anmodet','Access requested'), 'partner.access_request_resent' => bp_t('Anmodning gensendt','Request resent'), 'partner.access_request_cancelled' => bp_t('Anmodning tilbagekaldt','Request cancelled'), 'partner.access_approved' => bp_t('Adgang godkendt','Access approved'), 'partner.access_rejected' => bp_t('Adgang afvist','Access rejected'),
		'partner.request_created' => bp_t('Anmodning sendt','Request sent'), 'partner.request_cancelled' => bp_t('Anmodning annulleret','Request cancelled'), 'partner.request_approved' => bp_t('Anmodning godkendt','Request approved'), 'partner.request_rejected' => bp_t('Anmodning afvist','Request rejected'), 'partner.payer_changed' => bp_t('Betaler ændret','Payer changed'), 'partner.responsible_set' => bp_t('Ansvarlig medarbejder sat','Responsible employee set'),
		'partner.updated' => bp_t('Firmaoplysninger ændret','Firm details updated'), 'partner.company_detached' => bp_t('Selskab udskilt fra koncernen','Company detached from the group'), 'partner.created' => bp_t('Firma oprettet','Firm created'),
		'ledger.user_created' => bp_t('Bruger oprettet i regnskabet','User created in the account'), 'ledger.user_locked' => bp_t('Bruger lukket','User locked'), 'ledger.user_unlocked' => bp_t('Bruger genåbnet','User unlocked'), 'ledger.user_replaced' => bp_t('Kundebruger udskiftet','Customer user replaced'), 'ledger.company_updated' => bp_t('Virksomhedsoplysninger ændret','Company details updated'), 'ledger.created' => bp_t('Regnskab oprettet','Account created'), 'ledger.opened' => bp_t('Regnskab åbnet','Account opened'),
		'entitlement.override_added' => bp_t('Funktion ændret','Feature changed'), 'entitlement.override_expired' => bp_t('Funktion følger pakken igen','Feature follows the plan again'), 'entitlement.plan_changed' => bp_t('Pakke ændret','Plan changed'), 'entitlement.plan_scheduled' => bp_t('Pakkeskift planlagt','Plan change scheduled'), 'entitlement.plan_applied' => bp_t('Planlagt pakkeskift gennemført','Scheduled plan change applied'), 'entitlement.trial_started' => bp_t('Prøveperiode startet','Trial started'), 'entitlement.subscription_cancelled' => bp_t('Abonnement opsagt','Subscription cancelled'), 'entitlement.subscription_resumed' => bp_t('Abonnement genoptaget','Subscription resumed'),
		'proposal.created' => bp_t('Opgradering foreslået','Upgrade proposed'), 'proposal.accepted' => bp_t('Opgradering godkendt','Upgrade approved'), 'proposal.rejected' => bp_t('Opgradering afvist','Upgrade rejected'), 'proposal.expired' => bp_t('Forslag udløbet','Proposal expired'),
		'ledger.closed' => bp_t('Regnskab lukket','Account closed'), 'ledger.suspended' => bp_t('Regnskab suspenderet','Account suspended'), 'ledger.reopened' => bp_t('Regnskab genåbnet','Account reopened'), 'ledger.closed_scheduled' => bp_t('Lukning planlagt','Closing scheduled'), 'billing.payer_set' => bp_t('Fakturering: betaler sat','Billing: payer set'),
	);
	return isset($m[$a]) ? $m[$a] : $a;
}
// "takes effect" date from a log row's details (fra YYYY-MM-DD / til YYYY-MM-DD / ophør YYYY-MM-DD)
function bp_effective_from_details($d) { return preg_match('/(?:fra|from|ophør|til|until)\s+(\d{4}-\d{2}-\d{2})/u', (string)$d, $m) ? $m[1] : ''; }
function bp_log_rows($where, $limit = 200) {
	$o = array(); $q = db_select("select l.*, b.brugernavn, pu.name as uname, p.name as pname, r.regnskab from partner_log l left join brugere b on b.id = l.actor_bruger_id left join partner_users pu on pu.bruger_id = l.actor_bruger_id left join partners p on p.id = l.actor_partner_id left join regnskab r on r.id = l.regnskab_id where $where order by l.id desc limit ".(int)$limit, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $o[] = $r; return $o;
}

} // function_exists
?>
