<?php
// --- admin/inc_lifecycle.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Area 4 "Administration af regnskaber": lifecycle of a ledger as explicit states –
//                  active | suspended | closed – with reason and optional future date, plus subscription
//                  cancellation at a date. Stored in regnskab_lifecycle (master); regnskab.lukket/lukkes/
//                  lukkes_kommentar are kept in sync so login.php/online.php and the lists keep working.
//                  Suspended = temporarily blocked (login refused like closed, but not deletable and shown as
//                  "Suspenderet"). Due events are applied lazily on the card and nightly by usage_snapshot_job.php.
//                  Closing a partner never touches its customers' ledgers (partnere.php only ends the links).

if (!function_exists('lc_tables_ensure')) {
function lc_tables_ensure() {
	static $d = false; if ($d) return; $d = true;
	if (db_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = 'regnskab_lifecycle'", __FILE__ . " linje " . __LINE__))) return;
	db_modify("CREATE TABLE IF NOT EXISTS regnskab_lifecycle (id SERIAL PRIMARY KEY, regnskab_id int NOT NULL, state varchar(12) NOT NULL, reason text, effective_from date NOT NULL, applied_at timestamp, cancelled_at timestamp, created_by text NOT NULL, created_at timestamp DEFAULT now())", __FILE__ . " linje " . __LINE__);
}
// Current state: last applied lifecycle row, else derived from regnskab.lukket
function lc_state($rid, $reg) {
	lc_tables_ensure();
	$r = db_fetch_array(db_select("select * from regnskab_lifecycle where regnskab_id = '".(int)$rid."' and applied_at is not null and cancelled_at is null order by applied_at desc, id desc limit 1", __FILE__ . " linje " . __LINE__));
	if ($r) return array('state' => $r['state'], 'since' => substr($r['applied_at'], 0, 10), 'reason' => $r['reason'], 'by' => $r['created_by']);
	return array('state' => ($reg['lukket'] == 'on') ? 'closed' : 'active', 'since' => null, 'reason' => $reg['lukkes_kommentar'] ?? '', 'by' => '');
}
function lc_pending($rid) {
	$out = array(); $q = db_select("select * from regnskab_lifecycle where regnskab_id = '".(int)$rid."' and applied_at is null and cancelled_at is null order by effective_from, id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $out[] = $r; return $out;
}
function lc_apply_row($row) {
	$rid = (int)$row['regnskab_id']; $st = $row['state']; $reason = db_escape_string((string)$row['reason']);
	if ($st == 'active') db_modify("update regnskab set lukket = '', lukkes = NULL, lukkes_kommentar = '' where id = '$rid'", __FILE__ . " linje " . __LINE__);
	else { db_modify("update regnskab set lukket = 'on', lukkes_kommentar = '".($st == 'suspended' ? 'SUSPENDERET: ' : '').$reason."' where id = '$rid'", __FILE__ . " linje " . __LINE__); db_modify("delete from online where db = (select db from regnskab where id = '$rid')", __FILE__ . " linje " . __LINE__); }
	db_modify("update regnskab_lifecycle set applied_at = now() where id = '".(int)$row['id']."'", __FILE__ . " linje " . __LINE__);
	partner_log('ledger.'.($st == 'active' ? 'reopened' : $st), $rid, null, ($row['effective_from'] ? $row['effective_from'].' ' : '').(string)$row['reason']);
}
// Apply due events for one ledger (card) or all (nightly job). Returns number applied.
function lc_apply_due($rid = null) {
	lc_tables_ensure(); $n = 0;
	$q = db_select("select * from regnskab_lifecycle where applied_at is null and cancelled_at is null and effective_from <= current_date".($rid ? " and regnskab_id = '".(int)$rid."'" : "")." order by effective_from, id", __FILE__ . " linje " . __LINE__);
	$rows = array(); while ($r = db_fetch_array($q)) $rows[] = $r;
	foreach ($rows as $r) { lc_apply_row($r); $n++; }
	// legacy: regnskab.lukkes date reached without a lifecycle row (set in the old panel)
	$q = db_select("select id, lukkes from regnskab where lukket != 'on' and lukkes is not null and lukkes <> '2099-12-31' and lukkes <= current_date".($rid ? " and id = '".(int)$rid."'" : ""), __FILE__ . " linje " . __LINE__);
	$rows = array(); while ($r = db_fetch_array($q)) $rows[] = $r;
	foreach ($rows as $r) { db_modify("update regnskab set lukket = 'on' where id = '".(int)$r['id']."'", __FILE__ . " linje " . __LINE__); db_modify("delete from online where db = (select db from regnskab where id = '".(int)$r['id']."')", __FILE__ . " linje " . __LINE__); partner_log('ledger.auto_closed', (int)$r['id'], null, 'lukkes '.$r['lukkes']); $n++; }
	return $n;
}
function lc_schedule($rid, $state, $date, $reason) {
	if (!in_array($state, array('active','suspended','closed'), true)) return 'Ugyldig tilstand';
	$date = $date ?: date('Y-m-d'); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return 'Ugyldig dato';
	if ($state != 'active' && trim($reason) === '') return vr_t('Begrundelse er påkrævet','Reason is required');
	// one pending event per ledger: cancel older pending ones
	db_modify("update regnskab_lifecycle set cancelled_at = now() where regnskab_id = '".(int)$rid."' and applied_at is null and cancelled_at is null", __FILE__ . " linje " . __LINE__);
	db_modify("insert into regnskab_lifecycle (regnskab_id, state, reason, effective_from, created_by) values ('".(int)$rid."', '$state', '".db_escape_string($reason)."', '$date', '".db_escape_string(ent_actor())."')", __FILE__ . " linje " . __LINE__);
	if ($date <= date('Y-m-d')) lc_apply_due((int)$rid);
	else { db_modify("update regnskab set lukkes = ".($state == 'active' ? 'NULL' : "'$date'").", lukkes_kommentar = '".db_escape_string(($state == 'suspended' ? 'SUSPENDERES ' : 'LUKKES ').$date.': '.$reason)."' where id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__); partner_log('ledger.'.$state.'_scheduled', (int)$rid, null, "$date – $reason"); }
	return '';
}
function lc_cancel_pending($rid) {
	db_modify("update regnskab_lifecycle set cancelled_at = now() where regnskab_id = '".(int)$rid."' and applied_at is null and cancelled_at is null", __FILE__ . " linje " . __LINE__);
	db_modify("update regnskab set lukkes = NULL where id = '".(int)$rid."' and lukket != 'on'", __FILE__ . " linje " . __LINE__);
	partner_log('ledger.schedule_cancelled', (int)$rid, null, '');
}
// Subscription cancellation at a date: entitlement status 'canceled' with period end, and the ledger closes on that date.
function lc_cancel_subscription($rid, $date, $reason) {
	$date = $date ?: date('Y-m-d'); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return 'Ugyldig dato';
	if (trim($reason) === '') return vr_t('Begrundelse er påkrævet','Reason is required');
	if (db_fetch_array(db_select("select 1 from regnskab_entitlements where regnskab_id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__))) db_modify("update regnskab_entitlements set subscription_status = 'canceled', current_period_end = '$date', updated_at = now() where regnskab_id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__);
	else db_modify("insert into regnskab_entitlements (regnskab_id, source, subscription_status, current_period_end) values ('".(int)$rid."', 'manual', 'canceled', '$date')", __FILE__ . " linje " . __LINE__);
	db_modify("update regnskab_plan_changes set cancelled_at = now() where regnskab_id = '".(int)$rid."' and applied_at is null and cancelled_at is null", __FILE__ . " linje " . __LINE__);
	ent_log('entitlement.subscription_cancelled', (int)$rid, "ophør $date – $reason");
	return lc_schedule($rid, 'closed', $date, vr_t('Abonnement opsagt','Subscription cancelled').': '.$reason);
}
function lc_actions($rid, &$notes) {
	if (!isset($_POST['lc_action'])) return;
	$a = $_POST['lc_action']; $date = trim(if_isset($_POST, '', 'effective_from')); $reason = trim(if_isset($_POST, '', 'reason')); $err = '';
	if ($a == 'suspend') $err = lc_schedule($rid, 'suspended', $date, $reason);
	elseif ($a == 'close') $err = lc_schedule($rid, 'closed', $date, $reason);
	elseif ($a == 'reopen') $err = lc_schedule($rid, 'active', $date, $reason);
	elseif ($a == 'cancel_sub') $err = lc_cancel_subscription($rid, $date, $reason);
	elseif ($a == 'cancel_pending') lc_cancel_pending($rid);
	$notes[] = $err ? $err : vr_t('Gemt','Saved');
}
function lc_render($rid, $reg, $ent) {
	global $sprog_id;
	$st = lc_state($rid, $reg); $pending = lc_pending($rid); $today = date('Y-m-d');
	$txt = array('active' => vr_t('Aktivt','Active'), 'suspended' => vr_t('Suspenderet','Suspended'), 'closed' => vr_t('Lukket','Closed'));
	$dot = array('active' => 'vr-ok', 'suspended' => 'vr-warn', 'closed' => 'vr-off');
	print "<section class=\"vr-sect\"><h2>".vr_t('Status og livscyklus','Status and lifecycle')."</h2><div class=\"vr-card\"><div class=\"vr-kv\">";
	print "<div><span>".vr_t('Tilstand','State')."</span><b><span class=\"vr-st\"><span class=\"vr-dot ".$dot[$st['state']]."\"></span>".$txt[$st['state']]."</span>".($st['since'] ? " <span class=\"vr-mut2\">· ".vr_t('siden','since')." ".vr_h($st['since'])."</span>" : "").($st['reason'] ? "<br><small style=\"color:var(--text-2)\">".vr_h($st['reason'])."</small>" : "")."</b></div>";
	if ($ent['row'] && $ent['row']['subscription_status'] == 'canceled') print "<div><span>".vr_t('Abonnement','Subscription')."</span><b><span class=\"vr-st\"><span class=\"vr-dot vr-warn\"></span>".vr_t('Opsagt','Cancelled')."</span> <span class=\"vr-mut2\">· ".vr_t('ophører','ends')." ".vr_h(substr((string)$ent['row']['current_period_end'],0,10))."</span></b></div>";
	foreach ($pending as $p) print "<div><span>".vr_t('Planlagt','Scheduled')."</span><b>".$txt[$p['state']]." ".vr_t('fra','from')." ".vr_h($p['effective_from'])." <span class=\"vr-mut2\">· ".vr_h($p['reason'])." · ".vr_h($p['created_by'])."</span> <form method=\"post\" style=\"display:inline\"><input type=\"hidden\" name=\"lc_action\" value=\"cancel_pending\"><button class=\"vr-link\" type=\"submit\">".vr_t('Annullér','Cancel')."</button></form></b></div>";
	print "</div>";
	// actions
	$f = function($action, $label, $cls, $needReason = true) use ($today) { return "<form method=\"post\" class=\"vr-inline\" style=\"padding:10px 20px;border-top:1px solid var(--grid)\"><input type=\"hidden\" name=\"lc_action\" value=\"$action\"><b style=\"font-weight:500;min-width:170px\">$label</b><input class=\"vr-inp\" type=\"date\" name=\"effective_from\" value=\"$today\" min=\"$today\" title=\"".vr_t('Dato (i dag = straks)','Date (today = now)')."\"><input class=\"vr-inp\" name=\"reason\" placeholder=\"".vr_t('Begrundelse','Reason').($needReason ? ' ('.vr_t('påkrævet','required').')' : '')."\" style=\"flex:1;min-width:220px\"".($needReason ? " required" : "")."><button type=\"submit\" class=\"vr-btn $cls\" onclick=\"return confirm('".vr_t('Er du sikker?','Are you sure?')."')\">".vr_t('Udfør','Apply')."</button></form>"; };
	if ($st['state'] == 'active') { print $f('suspend', vr_t('Suspendér regnskabet','Suspend the account'), ''); print $f('close', vr_t('Luk regnskabet','Close the account'), 'vr-danger'); print $f('cancel_sub', vr_t('Opsig abonnementet','Cancel the subscription'), 'vr-danger'); }
	else print $f('reopen', vr_t('Genåbn regnskabet','Reopen the account'), 'vr-primary', false);
	print "<div class=\"vr-foot\"><span>".vr_t('Suspenderet: login afvises, men regnskabet kan ikke slettes. Lukket: login afvises, og regnskabet kan slettes under Slet regnskab. En dato i fremtiden planlægger handlingen; den udføres automatisk af det natlige job. Opsigelse lukker regnskabet ved periodens udløb.','Suspended: login refused, cannot be deleted. Closed: login refused, deletable. A future date schedules the action; the nightly job applies it. Cancellation closes the account at period end.')."</span></div></div></section>";
}
}
?>
