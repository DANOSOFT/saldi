<?php
// --- admin/inc_proeve.php --- ver 5.1.0 --- 2026.10.12 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261012 CL/ASR New. Trial follow-up for the operator panel: tabs "Nye kunder" (ledgers still in the 30-day trial, with
//                  days left) and "Overskredet prøveperiode" (trial ended without payment, with follow-up status and note).
//                  The trial is kept in regnskab_trial (one row per ledger):
//                    - operator_opret.php registers new ledgers (start = today, end = trial override expiry or +30 days);
//                    - ledgers created elsewhere are picked up from the trial override, or from the creation time of the
//                      customer database (PG_VERSION, needs superuser/pg_read_server_files) when created within the
//                      last 60 days. Older ledgers are treated as existing customers and never enter the lists.
//                  Paid = marked paid here, billing status 'paid' (own or payer's, see bill_payer) or "Betalt til" set
//                  after the trial start. Closed ledgers and bookkeeper/group home ledgers are not listed. All changes are logged.

if (!function_exists('tr_tables_ensure')) {
define('TR_DAYS', 30);
function tr_tables_ensure() {
	static $d = false; if ($d) return; $d = true;
	if (db_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = 'regnskab_trial'", __FILE__ . " linje " . __LINE__))) return;
	db_modify("CREATE TABLE IF NOT EXISTS regnskab_trial (regnskab_id int PRIMARY KEY, started date NOT NULL, ends date NOT NULL, source varchar(10), followup varchar(12) NOT NULL DEFAULT 'open', note text, contacted date, resolved varchar(10), resolved_at timestamp, updated_at timestamp, updated_by text)", __FILE__ . " linje " . __LINE__);
}
function tr_followups() { return array('open' => vr_t('Ikke kontaktet','Not contacted'), 'contacted' => vr_t('Kontaktet','Contacted'), 'promised' => vr_t('Lovet betaling','Payment promised'), 'closing' => vr_t('Skal lukkes','To be closed')); }
// Register a new ledger (called from operator_opret.php).
function tr_register($rid, $days = TR_DAYS) {
	tr_tables_ensure(); $rid = (int)$rid; if (!$rid) return;
	$o = db_fetch_array(db_select("select expires_at from regnskab_overrides where regnskab_id = '$rid' and reason = 'trial' order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	$ends = $o && $o['expires_at'] ? substr($o['expires_at'], 0, 10) : date('Y-m-d', strtotime('+'.(int)$days.' days'));
	if (!db_fetch_array(db_select("select 1 from regnskab_trial where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__)))
		db_modify("insert into regnskab_trial (regnskab_id, started, ends, source, updated_by) values ('$rid', current_date, '$ends', 'opret', '".db_escape_string(ent_actor())."')", __FILE__ . " linje " . __LINE__);
}
// Pick up ledgers created without operator_opret.php (once per request).
function tr_sync() {
	static $d = false; if ($d) return; $d = true; tr_tables_ensure(); global $sqdb;
	db_modify("insert into regnskab_trial (regnskab_id, started, ends, source) select distinct on (o.regnskab_id) o.regnskab_id, o.created_at::date, o.expires_at::date, 'trial' from regnskab_overrides o join regnskab r on r.id = o.regnskab_id left join regnskab_trial t on t.regnskab_id = o.regnskab_id where o.reason = 'trial' and o.expires_at is not null and t.regnskab_id is null order by o.regnskab_id, o.id desc", __FILE__ . " linje " . __LINE__);
	$ok = db_fetch_array(db_select("select (rolsuper or exists (select 1 from pg_auth_members m join pg_roles g on g.oid = m.roleid where g.rolname = 'pg_read_server_files' and m.member = r.oid)) as ok from pg_roles r where r.rolname = current_user", __FILE__ . " linje " . __LINE__));
	if (!$ok || ($ok['ok'] !== 't' && $ok['ok'] !== true)) return;
	$q = db_select("select r.id, (pg_stat_file('base/'||d.oid||'/PG_VERSION')).modification::date as born from regnskab r join pg_database d on d.datname = r.db left join regnskab_trial t on t.regnskab_id = r.id where t.regnskab_id is null and r.db != '".db_escape_string($sqdb)."'", __FILE__ . " linje " . __LINE__);
	$cut = date('Y-m-d', strtotime('-60 days'));
	while ($r = db_fetch_array($q)) if ($r['born'] && $r['born'] >= $cut && $r['born'] <= date('Y-m-d'))
		db_modify("insert into regnskab_trial (regnskab_id, started, ends, source) values ('".(int)$r['id']."', '".$r['born']."', '".date('Y-m-d', strtotime($r['born'].' +'.TR_DAYS.' days'))."', 'db')", __FILE__ . " linje " . __LINE__);
}
function tr_get($rid) { tr_tables_ensure(); return db_fetch_array(db_select("select * from regnskab_trial where regnskab_id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__)); }
function tr_days_left($t) { return (int)round((strtotime($t['ends']) - strtotime(date('Y-m-d'))) / 86400); }
// Payment evidence: [paid bool, pay_status of the payer, label]
function tr_payment($t, $reg) {
	if ($t['resolved'] == 'paid') return array(true, 'paid', vr_t('Markeret betalt','Marked paid'));
	list($payer) = bill_payer((int)$t['regnskab_id']); $pid = $payer ?: (int)$t['regnskab_id'];
	$b = db_fetch_array(db_select("select pay_status from regnskab_billing where regnskab_id = '$pid'", __FILE__ . " linje " . __LINE__)); $ps = $b ? $b['pay_status'] : '';
	if ($ps == 'paid') return array(true, 'paid', $payer ? vr_t('Betalt via','Paid via').' '.$payer : vr_t('Betalt','Paid'));
	if ($reg['betalt_til'] && substr($reg['betalt_til'], 0, 10) != '2099-12-31' && $reg['betalt_til'] >= $t['started']) return array(true, 'paid', vr_t('Betalt til','Paid until').' '.date('d-m-Y', strtotime($reg['betalt_til'])));
	return array(false, $ps, '');
}
// All open, unresolved trial ledgers split into [in trial, overdue]; each row = regnskab + trial + derived fields.
function tr_lists() {
	static $c = null; if ($c !== null) return $c;
	tr_sync(); $new = array(); $over = array();
	$q = db_select("select r.*, t.started, t.ends, t.source, t.followup, t.note, t.contacted, t.resolved, t.updated_at as tr_updated, t.updated_by as tr_by from regnskab_trial t join regnskab r on r.id = t.regnskab_id where coalesce(r.lukket,'') != 'on' and (t.resolved is null or t.resolved = 'paid') and r.id not in (select home_regnskab_id from partners where home_regnskab_id is not null)", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$r['left'] = tr_days_left($r); list($r['paid'], $r['pay_status'], $r['pay_label']) = tr_payment(array('regnskab_id' => $r['id']) + $r, $r);
		if ($r['left'] >= 0) $new[] = $r; elseif (!$r['paid']) $over[] = $r;
	}
	return $c = array($new, $over);
}
function tr_counts() { list($n, $o) = tr_lists(); return array(count($n), count($o)); }
function tr_actions(&$notes) {
	if (!isset($_POST['tr_action'])) return;
	tr_tables_ensure(); $a = $_POST['tr_action']; $rid = (int)if_isset($_POST, 0, 'tr_rid'); $t = tr_get($rid); if (!$t) return;
	$who = "'".db_escape_string(ent_actor())."'"; $name = ($r = db_fetch_array(db_select("select regnskab from regnskab where id = '$rid'", __FILE__ . " linje " . __LINE__))) ? $r['regnskab'] : "#$rid";
	if ($a == 'followup') {
		$f = (string)if_isset($_POST, 'open', 'followup'); if (!isset(tr_followups()[$f])) $f = 'open';
		$note = trim((string)if_isset($_POST, '', 'note')); $ct = ($f != 'open' && $t['followup'] == 'open' && !$t['contacted']) ? ", contacted = current_date" : ($f == 'open' ? ", contacted = NULL" : "");
		db_modify("update regnskab_trial set followup = '$f', note = '".db_escape_string($note)."'$ct, updated_at = now(), updated_by = $who where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__);
		partner_log('trial.followup', $rid, null, tr_followups()[$f].($note !== '' ? ": $note" : ''));
		$notes[] = vr_t('Opfølgningen er gemt for','Follow-up saved for').' '.$name;
	} elseif ($a == 'extend') {
		$days = max(1, min(90, (int)if_isset($_POST, 14, 'days'))); $from = max(strtotime($t['ends']), strtotime(date('Y-m-d'))); $ends = date('Y-m-d', strtotime("+$days days", $from));
		db_modify("update regnskab_trial set ends = '$ends', updated_at = now(), updated_by = $who where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__);
		partner_log('trial.extended', $rid, null, "$days dage, til $ends");
		$notes[] = vr_t('Prøveperioden er forlænget til','Trial extended to').' '.date('d-m-Y', strtotime($ends)).' – '.$name;
	} elseif (in_array($a, array('paid', 'exempt'), true)) {
		db_modify("update regnskab_trial set resolved = '$a', resolved_at = now(), updated_at = now(), updated_by = $who where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__);
		partner_log('trial.'.($a == 'paid' ? 'marked_paid' : 'exempted'), $rid, null, $a == 'paid' ? 'markeret betalt' : 'ikke en prøvekunde');
		$notes[] = $name.' '.($a == 'paid' ? vr_t('er markeret som betalt','is marked as paid') : vr_t('er fjernet fra prøvelisterne','is removed from the trial lists'));
	} elseif ($a == 'reopen') {
		db_modify("update regnskab_trial set resolved = NULL, resolved_at = NULL, updated_at = now(), updated_by = $who where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__);
		partner_log('trial.reopened', $rid, null, 'tilbage på prøvelisterne');
		$notes[] = $name.' '.vr_t('er tilbage på prøvelisterne','is back on the trial lists');
	}
}
// Card header text: [html, state] or null.
function tr_card_lead($rid, $reg) {
	$t = tr_get($rid); if (!$t || $t['resolved'] == 'exempt' || $reg['lukket'] == 'on') return null;
	$left = tr_days_left($t); list($paid) = tr_payment($t, $reg);
	if ($left >= 0) return '<a href="operator_panel.php?ltab=nye" style="color:'.($left <= 3 ? 'var(--bad)' : ($left <= 10 ? 'var(--warn-ink)' : 'inherit')).'">'.vr_t('Prøveperiode','Trial').': '.($left == 0 ? vr_t('sidste dag','last day') : $left.' '.($left == 1 ? vr_t('dag','day') : vr_t('dage','days')).' '.vr_t('tilbage','left')).'</a>'.($paid ? ' ('.vr_t('betalt','paid').')' : '');
	if ($paid) return null;
	return '<a href="operator_panel.php?ltab=overskredet" style="color:var(--bad)">'.vr_t('Prøveperiode overskredet for','Trial ended').' '.(-$left).' '.(-$left == 1 ? vr_t('dag','day') : vr_t('dage','days')).' '.vr_t('siden','ago').'</a>';
}
function tr_pay_badge($r) {
	if ($r['paid']) return "<span class=\"vr-st\"><span class=\"vr-dot vr-ok\"></span>".vr_h($r['pay_label'])."</span>";
	$t = array('pending' => vr_t('Faktureret, ikke betalt','Invoiced, unpaid'), 'overdue' => vr_t('Forfalden','Overdue'), 'failed' => vr_t('Betaling fejlet','Payment failed'), 'none' => vr_t('Ingen fakturaer','No invoices'));
	$d = array('pending' => 'vr-warn', 'overdue' => 'vr-bad', 'failed' => 'vr-bad', 'none' => 'vr-off');
	return isset($t[$r['pay_status']]) ? "<span class=\"vr-st\"><span class=\"vr-dot ".$d[$r['pay_status']]."\"></span>".$t[$r['pay_status']]."</span>" : "<span class=\"vr-mut2\">".vr_t('Ikke betalt','Not paid')."</span>";
}
function tr_activity($r) {
	return "<span class=\"vr-num\">".vr_num($r['posteret'])."</span> <span class=\"vr-mut2\">".vr_t('post.','entries')." · ".vr_num($r['brugerantal'])." ".vr_t('brugere','users')."</span><small>".vr_t('Sidst aktiv','Last active')." ".vr_rel($r['sidst'] ? (int)$r['sidst'] : 0)."</small>";
}
function tr_sort(&$rows, $sorts, $def, $defdir) {
	$s = (string)if_isset($_GET, $def, 'tsort'); if (!isset($sorts[$s])) $s = $def; $dir = (string)if_isset($_GET, $defdir, 'tdir') == 'desc' ? -1 : 1;
	$k = $sorts[$s]; usort($rows, fn($a, $b) => $dir * ($k($a) <=> $k($b)) ?: strcasecmp($a['regnskab'], $b['regnskab']));
	return array($s, $dir);
}
function tr_th($ltab, $col, $label, $cur, $dir, $cls = '') {
	$on = ($col == $cur); $nd = ($on && $dir == 1) ? 'desc' : 'asc';
	return "<th".($cls ? " class=\"$cls\"" : "")." aria-sort=\"".($on ? ($dir == 1 ? 'ascending' : 'descending') : 'none')."\"><a href=\"operator_panel.php?ltab=$ltab&tsort=$col&tdir=$nd\">".vr_h($label).($on ? ' <span class="vr-ar">'.($dir == 1 ? '&#9650;' : '&#9660;').'</span>' : '')."</a></th>";
}
function tr_name_cell($r) {
	$id = (int)$r['id'];
	return "<td class=\"vr-nm\"><a href=\"operator_panel.php?regnskab_id=$id\">".vr_h($r['regnskab'])."</a><small>".vr_h($r['db']).($r['email'] ? ' · '.vr_h($r['email']) : '')."</small></td>";
}
function tr_hidden($r, $a) { return "<input type=\"hidden\" name=\"tr_action\" value=\"$a\"><input type=\"hidden\" name=\"tr_rid\" value=\"".(int)$r['id']."\">"; }
function tr_exempt_render() {
	$q = db_select("select r.id, r.regnskab, t.resolved_at, t.updated_by from regnskab_trial t join regnskab r on r.id = t.regnskab_id where t.resolved = 'exempt' and coalesce(r.lukket,'') != 'on' order by t.resolved_at desc", __FILE__ . " linje " . __LINE__);
	$ex = array(); while ($r = db_fetch_array($q)) $ex[] = $r; if (!$ex) return;
	print "<details class=\"vr-tr-ex\"><summary class=\"vr-link\">".count($ex)." ".(count($ex) == 1 ? vr_t('regnskab er markeret som ikke en prøvekunde','account is marked as not a trial customer') : vr_t('regnskaber er markeret som ikke prøvekunder','accounts are marked as not trial customers'))."</summary><div class=\"vr-card\" style=\"margin-top:8px\"><table class=\"vr-t\"><tbody>";
	foreach ($ex as $r) print "<tr><td class=\"vr-nm\"><a href=\"operator_panel.php?regnskab_id=".(int)$r['id']."\">".vr_h($r['regnskab'])."</a><small>".vr_t('markeret','marked')." ".($r['resolved_at'] ? date('d-m-Y', strtotime($r['resolved_at'])) : '')." ".vr_t('af','by')." ".vr_h($r['updated_by'])."</small></td><td class=\"vr-r\"><form method=\"post\">".tr_hidden($r, 'reopen')."<button class=\"vr-link\" type=\"submit\">".vr_t('Sæt tilbage på listerne','Put back on the lists')."</button></form></td></tr>";
	print "</tbody></table></div></details>";
}
function tr_new_render() {
	list($rows) = tr_lists();
	list($s, $dir) = tr_sort($rows, array('left' => fn($r) => $r['left'], 'started' => fn($r) => $r['started'], 'regnskab' => fn($r) => mb_strtolower($r['regnskab']), 'posteret' => fn($r) => (int)$r['posteret'], 'sidst' => fn($r) => (int)$r['sidst']), 'left', 'asc');
	$soon = count(array_filter($rows, fn($r) => $r['left'] <= 7 && !$r['paid'])); $paidN = count(array_filter($rows, fn($r) => $r['paid']));
	print "<section class=\"vr-sect\"><h2>".vr_t('Nye kunder','New customers')." <small>".count($rows)." ".vr_t('i prøveperioden','in trial')." · $soon ".vr_t('udløber inden for 7 dage','end within 7 days')." · $paidN ".vr_t('har betalt','have paid')."</small></h2><div class=\"vr-card\">";
	print "<table class=\"vr-t vr-tr\"><thead><tr>".tr_th('nye', 'regnskab', vr_t('Regnskab','Account'), $s, $dir).tr_th('nye', 'left', vr_t('Prøveperiode','Trial'), $s, $dir).tr_th('nye', 'started', vr_t('Oprettet','Created'), $s, $dir).tr_th('nye', 'posteret', vr_t('Aktivitet','Activity'), $s, $dir)."<th>".vr_t('Betaling','Payment')."</th><th></th></tr></thead><tbody>";
	foreach ($rows as $r) {
		$id = (int)$r['id']; $l = $r['left']; $tot = max(1, (int)round((strtotime($r['ends']) - strtotime($r['started'])) / 86400)); $pct = max(0, min(100, (int)round(($tot - $l) / $tot * 100)));
		$cls = $r['paid'] ? 'vr-tr-paid' : ($l <= 3 ? 'vr-tr-bad' : ($l <= 10 ? 'vr-tr-warn' : ''));
		print "<tr onclick=\"location.href='operator_panel.php?regnskab_id=$id'\" style=\"cursor:pointer\">".tr_name_cell($r);
		print "<td class=\"vr-tr-days $cls\"><b>".($l == 0 ? vr_t('Sidste dag','Last day') : $l)."</b>".($l ? "<span>".($l == 1 ? vr_t('dag tilbage','day left') : vr_t('dage tilbage','days left'))."</span>" : "")."<span class=\"vr-b\"><i style=\"width:$pct%\"></i></span><small>".vr_t('udløber','ends')." ".date('d-m-Y', strtotime($r['ends']))."</small></td>";
		print "<td class=\"vr-mut\">".date('d-m-Y', strtotime($r['started']))."<small>".vr_rel($r['started'].' 00:00:00')."</small></td><td class=\"vr-nm\">".tr_activity($r)."</td><td>".tr_pay_badge($r)."</td>";
		print "<td class=\"vr-r\" onclick=\"event.stopPropagation()\"><details class=\"vr-tr-more\"><summary class=\"vr-link\">".vr_t('Handlinger','Actions')."</summary><div class=\"vr-tr-pop\">";
		print "<form method=\"post\" class=\"vr-inline\">".tr_hidden($r, 'extend')."<select name=\"days\" class=\"vr-sel\"><option value=\"7\">7 ".vr_t('dage','days')."</option><option value=\"14\" selected>14 ".vr_t('dage','days')."</option><option value=\"30\">30 ".vr_t('dage','days')."</option></select><button class=\"vr-btn\" type=\"submit\">".vr_t('Forlæng','Extend')."</button></form>";
		if (!$r['paid']) print "<form method=\"post\">".tr_hidden($r, 'paid')."<button class=\"vr-link\" type=\"submit\">".vr_t('Markér som betalt','Mark as paid')."</button></form>";
		print "<form method=\"post\" onsubmit=\"return confirm('".vr_t('Fjern regnskabet fra prøvelisterne (fx internt regnskab eller gratisaftale)?','Remove the account from the trial lists (e.g. internal or free)?')."')\">".tr_hidden($r, 'exempt')."<button class=\"vr-link\" type=\"submit\">".vr_t('Ikke en prøvekunde','Not a trial customer')."</button></form>";
		print "</div></details></td></tr>";
	}
	if (!$rows) print "<tr><td colspan=\"6\" class=\"vr-mut\" style=\"padding:24px 20px\">".vr_t('Ingen regnskaber i prøveperioden lige nu.','No accounts in trial right now.')."</td></tr>";
	print "</tbody></table></div></section>";
	print "<p class=\"vr-sub\" style=\"margin-top:10px\">".vr_t('Prøveperioden er '.TR_DAYS.' dage fra oprettelsen. Regnskaber, der ikke har betalt, når den udløber, flyttes til Overskredet prøveperiode. Betalt = betalingsstatus fra faktureringen (natligt), "Betalt til" på regnskabet eller markeret her.','The trial is '.TR_DAYS.' days from creation. Accounts that have not paid when it ends move to Trial expired. Paid = billing status (nightly), "Paid until" on the account, or marked here.')."</p>";
	tr_exempt_render();
}
function tr_over_render() {
	list(, $rows) = tr_lists(); $fu = tr_followups(); $ford = array_flip(array_keys($fu));
	$ff = (string)if_isset($_GET, '', 'fu'); if (!isset($fu[$ff])) $ff = '';
	$cnt = array_fill_keys(array_keys($fu), 0); foreach ($rows as $r) $cnt[$r['followup']] = ($cnt[$r['followup']] ?? 0) + 1;
	if ($ff) $rows = array_values(array_filter($rows, fn($r) => $r['followup'] == $ff));
	list($s, $dir) = tr_sort($rows, array('over' => fn($r) => -$r['left'], 'regnskab' => fn($r) => mb_strtolower($r['regnskab']), 'posteret' => fn($r) => (int)$r['posteret'], 'sidst' => fn($r) => (int)$r['sidst'], 'followup' => fn($r) => $ford[$r['followup']] ?? 9), 'over', 'desc');
	print "<section class=\"vr-sect\"><h2>".vr_t('Overskredet prøveperiode','Trial expired')." <small>".vr_t('prøveperioden er udløbet uden betaling – følg op, forlæng, markér betalt eller luk regnskabet','trial ended without payment – follow up, extend, mark paid or close the account')."</small></h2>";
	print "<div class=\"vr-chips\"><a class=\"vr-chip2".($ff == '' ? ' on' : '')."\" href=\"operator_panel.php?ltab=overskredet\">".vr_t('Alle','All')." <span>".array_sum($cnt)."</span></a>";
	foreach ($fu as $k => $t) print "<a class=\"vr-chip2".($ff == $k ? ' on' : '')."\" href=\"operator_panel.php?ltab=overskredet&fu=$k\">".vr_h($t)." <span>".(int)$cnt[$k]."</span></a>";
	print "</div><div class=\"vr-card\"><table class=\"vr-t vr-tr\"><thead><tr>".tr_th('overskredet', 'regnskab', vr_t('Regnskab','Account'), $s, $dir).tr_th('overskredet', 'over', vr_t('Overskredet','Expired'), $s, $dir).tr_th('overskredet', 'posteret', vr_t('Aktivitet','Activity'), $s, $dir)."<th>".vr_t('Betaling','Payment')."</th>".tr_th('overskredet', 'followup', vr_t('Opfølgning','Follow-up'), $s, $dir)."<th></th></tr></thead><tbody>";
	foreach ($rows as $r) {
		$id = (int)$r['id']; $o = -$r['left'];
		print "<tr onclick=\"location.href='operator_panel.php?regnskab_id=$id'\" style=\"cursor:pointer\">".tr_name_cell($r);
		print "<td class=\"vr-tr-days vr-tr-bad\"><b>$o</b><span>".($o == 1 ? vr_t('dag siden','day ago') : vr_t('dage siden','days ago'))."</span><small>".vr_t('udløb','ended')." ".date('d-m-Y', strtotime($r['ends']))." · ".vr_t('oprettet','created')." ".date('d-m-Y', strtotime($r['started']))."</small></td>";
		print "<td class=\"vr-nm\">".tr_activity($r)."</td><td>".tr_pay_badge($r)."</td>";
		$dot = array('open' => 'vr-off', 'contacted' => 'vr-warn', 'promised' => 'vr-ok', 'closing' => 'vr-bad');
		print "<td class=\"vr-nm\"><span class=\"vr-st\"><span class=\"vr-dot ".($dot[$r['followup']] ?? 'vr-off')."\"></span>".vr_h($fu[$r['followup']] ?? $r['followup'])."</span>".($r['note'] !== null && $r['note'] !== '' ? "<small class=\"vr-tr-note\" title=\"".vr_h($r['note'])."\">".vr_h(mb_strlen($r['note']) > 70 ? mb_substr($r['note'], 0, 67).'…' : $r['note'])."</small>" : "").($r['followup'] != 'open' && $r['contacted'] ? "<small>".vr_t('kontaktet','contacted')." ".date('d-m-Y', strtotime($r['contacted'])).($r['tr_by'] ? ' · '.vr_h($r['tr_by']) : '')."</small>" : "")."</td>";
		print "<td class=\"vr-r\" onclick=\"event.stopPropagation()\"><details class=\"vr-tr-more\"><summary class=\"vr-btn\">".vr_t('Følg op','Follow up')."</summary><div class=\"vr-tr-pop\">";
		print "<form method=\"post\" class=\"vr-form\" style=\"max-width:none;padding:0\">".tr_hidden($r, 'followup')."<label>".vr_t('Status','Status')."<select name=\"followup\" class=\"vr-sel\">"; foreach ($fu as $k => $t) print "<option value=\"$k\"".($r['followup'] == $k ? " selected" : "").">".vr_h($t)."</option>"; print "</select></label>";
		print "<label>".vr_t('Note','Note')."<textarea class=\"vr-inp\" name=\"note\" style=\"height:70px;padding:8px 12px\" placeholder=\"".vr_t('Fx Ringet 12/10, vender tilbage fredag','e.g. Called 12/10, will reply Friday')."\">".vr_h($r['note'])."</textarea></label><div><button class=\"vr-btn vr-primary\" type=\"submit\">".vr_t('Gem opfølgning','Save follow-up')."</button></div></form>";
		print "<div class=\"vr-tr-acts\"><form method=\"post\" class=\"vr-inline\">".tr_hidden($r, 'extend')."<select name=\"days\" class=\"vr-sel\"><option value=\"7\">7 ".vr_t('dage','days')."</option><option value=\"14\" selected>14 ".vr_t('dage','days')."</option><option value=\"30\">30 ".vr_t('dage','days')."</option></select><button class=\"vr-btn\" type=\"submit\">".vr_t('Forlæng prøveperiode','Extend trial')."</button></form>";
		print "<form method=\"post\">".tr_hidden($r, 'paid')."<button class=\"vr-link\" type=\"submit\">".vr_t('Markér som betalt','Mark as paid')."</button></form>";
		print "<a class=\"vr-link\" href=\"operator_panel.php?regnskab_id=$id&tab=indstillinger\">".vr_t('Luk regnskabet…','Close the account…')."</a>";
		print "<form method=\"post\" onsubmit=\"return confirm('".vr_t('Fjern regnskabet fra prøvelisterne (fx internt regnskab eller gratisaftale)?','Remove the account from the trial lists (e.g. internal or free)?')."')\">".tr_hidden($r, 'exempt')."<button class=\"vr-link\" type=\"submit\">".vr_t('Ikke en prøvekunde','Not a trial customer')."</button></form></div>";
		print "</div></details></td></tr>";
	}
	if (!$rows) print "<tr><td colspan=\"6\" class=\"vr-mut\" style=\"padding:24px 20px\">".($ff ? vr_t('Ingen regnskaber med denne status.','No accounts with this status.') : vr_t('Ingen regnskaber har overskredet prøveperioden uden betaling.','No accounts have passed the trial without paying.'))."</td></tr>";
	print "</tbody></table></div></section>";
	tr_exempt_render();
}
}
?>
