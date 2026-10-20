<?php
ob_start(); # for the redirect after an action (vr_prg)
@session_start();
$s_id=session_id();
// --- admin/partnere.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261020 CL/ASR Review #785: new ids from INSERT … RETURNING (max(id) could return another session's row); an
//                  employee's customer scope is explicit (partner_users.scope all|selected, from partnerScope.php), so
//                  removing or moving the last selected customer leaves the employee with none instead of all; emp_remove
//                  and emp_scope only act on the partner's own employees and active customer links.
// 20261012 CL/ASR Post/Redirect/Get after every action (vr_prg), so the card is drawn from fresh data.
// 20261011 CL/ASR Groups and larger customers: per-ledger plan/usage/payment columns on the Kunder tab, group totals,
//                  billing mode (individual/consolidated) with a billing ledger, per-ledger payer, move to another
//                  partner without losing history.
// 20261010 CL/ASR New. Partners (bogholdere/revisorer and koncerner), phase P1 of
//                  Requirements_accountant_and_operator_panels_EN.md. Operator: list, create, import from the legacy
//                  adgang_til lists, partner card with Oversigt / Kunder (or Selskaber) / Medarbejdere / Indstillinger / Log,
//                  link and unlink ledgers, add and remove employees (master users). A partner owner sees their own
//                  partner as "Mit firma" and may manage employees' customer scope.

$css="../css/standard.css";
$title="Partnere";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("../includes/entitlements.php");
include("inc_betalinger.php");
include("vr_ui.php");
vr_require_operator(); # Saldi staff only; everyone else keeps the existing pages
partner_tables_ensure();
partner_billing_cols_ensure(); bill_cache_ensure();

if ($db != $sqdb) { print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/logud.php\">"; exit; }

$me = partner_current_user();
$op = $me['is_operator'];
$pid = (int) if_isset($_GET, 0, 'id');
$tab = if_isset($_GET, 'oversigt', 'tab');
$notes = vr_flash();
if (!$op) { # partner users only see their own partner
	if (!$me['partner']) { vr_open(array(vr_t('Mit firma','My firm')), vr_t('Du er ikke knyttet til en partner','You are not linked to a partner'), ''); vr_close(); exit; }
	$pid = (int)$me['partner']['partner_id'];
}
$isOwner = $op || ($me['partner'] && $me['partner']['partner_role'] == 'owner');

// ---------- actions ----------
if (isset($_POST['vr_action'])) {
	$act = $_POST['vr_action'];
	if ($act == 'create' && $op) {
		$name = trim(if_isset($_POST, '', 'name')); $kind = in_array(if_isset($_POST, 'bogholder', 'kind'), partner_kinds(), true) ? $_POST['kind'] : 'bogholder';
		$cvr = trim(if_isset($_POST, '', 'cvr')); $home = (int) if_isset($_POST, 0, 'home_regnskab_id');
		if (!$name) $notes[] = vr_t('Navn mangler','Name missing');
		else {
			$r = db_fetch_array(db_select("insert into partners (kind, name, cvr, home_regnskab_id, can_create, created_by) values ('$kind', '".db_escape_string($name)."', '".db_escape_string($cvr)."', ".($home ? "'$home'" : "NULL").", ".(isset($_POST['can_create']) ? 'true' : 'false').", '".(int)$me['id']."') returning id", __FILE__ . " linje " . __LINE__));
			$pid = (int)$r['id'];
			partner_log('partner.created', null, $pid, "$kind: $name");
			$notes[] = vr_t('Partneren er oprettet','Partner created');
		}
	} elseif ($act == 'migrate' && $op) {
		$n = partner_migrate_adgang_til();
		$notes[] = $n.' '.vr_t('partnere oprettet fra adgang_til-lister','partners created from adgang_til lists');
	} elseif ($pid && ($op || $isOwner)) {
		if ($act == 'settings' && $op) {
			$kind = in_array(if_isset($_POST, 'bogholder', 'kind'), partner_kinds(), true) ? $_POST['kind'] : 'bogholder';
			$home = (int) if_isset($_POST, 0, 'home_regnskab_id');
			db_modify("update partners set name = '".db_escape_string(trim($_POST['name']))."', kind = '$kind', cvr = '".db_escape_string(trim(if_isset($_POST,'','cvr')))."', home_regnskab_id = ".($home ? "'$home'" : "NULL").", can_create = ".(isset($_POST['can_create']) ? 'true' : 'false').", can_manage_features = ".(isset($_POST['can_manage_features']) ? 'true' : 'false').", can_manage_users = ".(isset($_POST['can_manage_users']) ? 'true' : 'false')." where id = '$pid'", __FILE__ . " linje " . __LINE__);
			partner_log('partner.updated', null, $pid, trim($_POST['name']));
			$notes[] = vr_t('Gemt','Saved');
		} elseif ($act == 'close' && $op) {
			db_modify("update partners set status = 'closed' where id = '$pid'", __FILE__ . " linje " . __LINE__);
			db_modify("update regnskab_partners set until = now() where partner_id = '$pid' and until is null", __FILE__ . " linje " . __LINE__);
			partner_log('partner.closed', null, $pid, '');
			$notes[] = vr_t('Partneren er lukket, og alle adgange er fjernet','Partner closed, all access removed');
		} elseif ($act == 'link' && $op) {
			$rid = (int) if_isset($_POST, 0, 'regnskab_id');
			$access = in_array(if_isset($_POST, 'full', 'access'), array('full','readonly','owner'), true) ? $_POST['access'] : 'full';
			if (!db_fetch_array(db_select("select 1 from regnskab where id = '$rid'", __FILE__ . " linje " . __LINE__))) $notes[] = vr_t('Regnskabet findes ikke','Account not found');
			elseif (db_fetch_array(db_select("select 1 from regnskab_partners where regnskab_id = '$rid' and partner_id = '$pid' and until is null", __FILE__ . " linje " . __LINE__))) $notes[] = vr_t('Allerede tilknyttet','Already linked');
			elseif ($access == 'owner' && db_fetch_array(db_select("select 1 from regnskab_partners where regnskab_id = '$rid' and access = 'owner' and until is null", __FILE__ . " linje " . __LINE__))) $notes[] = vr_t('Regnskabet har allerede en ejer','The account already has an owner');
			else { db_modify("insert into regnskab_partners (regnskab_id, partner_id, access, created_by) values ('$rid', '$pid', '$access', '".(int)$me['id']."')", __FILE__ . " linje " . __LINE__); partner_log('partner.link_added', $rid, $pid, $access); $notes[] = vr_t('Tilknyttet','Linked'); }
		} elseif ($act == 'unlink' && $op) {
			$lid = (int) if_isset($_POST, 0, 'link_id');
			$l = db_fetch_array(db_select("select * from regnskab_partners where id = '$lid' and partner_id = '$pid' and until is null", __FILE__ . " linje " . __LINE__));
			if ($l) { db_modify("update regnskab_partners set until = now() where id = '$lid'", __FILE__ . " linje " . __LINE__); partner_log('partner.link_removed', (int)$l['regnskab_id'], $pid, ''); $notes[] = vr_t('Adgang fjernet','Access removed'); }
		} elseif ($act == 'move' && $op) {
			$rid = (int)if_isset($_POST, 0, 'regnskab_id'); $to = (int)if_isset($_POST, 0, 'to_partner_id');
			$access = in_array(if_isset($_POST, 'full', 'access'), array('full','readonly','owner'), true) ? $_POST['access'] : 'full';
			$err = partner_move_ledger($rid, $pid, $to, $access, trim(if_isset($_POST, '', 'note'))); $notes[] = $err ? $err : vr_t('Regnskabet er flyttet. Historik, abonnement og log følger regnskabet.','Account moved. History, subscription and log stay with the account.');
		} elseif ($act == 'payer' && $op) {
			bill_set_payer((int)if_isset($_POST, 0, 'regnskab_id'), (int)if_isset($_POST, 0, 'payer_regnskab_id')); $notes[] = vr_t('Betalingsansvarlig gemt','Payer saved');
		} elseif ($act == 'billing' && $op) {
			$mode = if_isset($_POST, 'individual', 'billing_mode') == 'consolidated' ? 'consolidated' : 'individual'; $br = (int)if_isset($_POST, 0, 'billing_regnskab_id');
			db_modify("update partners set billing_mode = '$mode', billing_regnskab_id = ".($br ? "'$br'" : "NULL")." where id = '$pid'", __FILE__ . " linje " . __LINE__);
			partner_log('partner.billing_updated', null, $pid, "$mode".($br ? " via regnskab $br" : '')); $notes[] = vr_t('Faktureringsmodel gemt','Billing model saved');
		} elseif ($act == 'emp_add') {
			$bid = (int) if_isset($_POST, 0, 'bruger_id'); $newname = trim(if_isset($_POST, '', 'brugernavn')); $kode = trim(if_isset($_POST, '', 'kode'));
			$role = if_isset($_POST, 'employee', 'partner_role') == 'owner' ? 'owner' : 'employee';
			if (!$bid && $newname) { # create a new master user (login only; no ledger rights of its own)
				if (db_fetch_array(db_select("select 1 from brugere where brugernavn = '".db_escape_string($newname)."'", __FILE__ . " linje " . __LINE__))) $notes[] = vr_t('Brugernavnet findes allerede','Username already exists');
				elseif (strlen($kode) < 6) $notes[] = vr_t('Adgangskoden skal være mindst 6 tegn','Password must be at least 6 characters');
				else {
					$r = db_fetch_array(db_select("insert into brugere (brugernavn, rettigheder, status) values ('".db_escape_string($newname)."', ',,,', true) returning id", __FILE__ . " linje " . __LINE__)); $bid = (int)$r['id'];
					db_modify("update brugere set kode = '".db_escape_string(saldikrypt($bid, $kode))."' where id = '$bid'", __FILE__ . " linje " . __LINE__);
				}
			}
			if ($bid) {
				if (db_fetch_array(db_select("select 1 from partner_users where bruger_id = '$bid'", __FILE__ . " linje " . __LINE__))) $notes[] = vr_t('Brugeren er allerede medarbejder hos en partner','User already belongs to a partner');
				else { db_modify("insert into partner_users (bruger_id, partner_id, partner_role) values ('$bid', '$pid', '$role')", __FILE__ . " linje " . __LINE__); partner_log('partner.employee_added', null, $pid, "bruger $bid ($role)"); $notes[] = vr_t('Medarbejder tilføjet','Employee added'); }
			}
		} elseif ($act == 'emp_remove') {
			$bid = (int) if_isset($_POST, 0, 'bruger_id');
			if (db_fetch_array(db_select("select 1 from partner_users where bruger_id = '$bid' and partner_id = '$pid'", __FILE__ . " linje " . __LINE__))) {
				db_modify("delete from partner_users where bruger_id = '$bid' and partner_id = '$pid'", __FILE__ . " linje " . __LINE__);
				db_modify("delete from partner_user_access where bruger_id = '$bid'", __FILE__ . " linje " . __LINE__);
				partner_log('partner.employee_removed', null, $pid, "bruger $bid"); $notes[] = vr_t('Medarbejder fjernet','Employee removed');
			}
		} elseif ($act == 'emp_scope') {
			$bid = (int) if_isset($_POST, 0, 'bruger_id');
			if (db_fetch_array(db_select("select 1 from partner_users where bruger_id = '$bid' and partner_id = '$pid'", __FILE__ . " linje " . __LINE__))) {
				$sel = if_isset($_POST, array(), 'scope'); $sel = is_array($sel) ? array_map('intval', $sel) : array();
				$linked = array(); $q = db_select("select regnskab_id from regnskab_partners where partner_id = '$pid' and until is null", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $linked[] = (int)$r['regnskab_id'];
				$sel = in_array(0, $sel, true) ? array() : array_values(array_intersect(array_unique($sel), $linked)); # "Alle" wins; only the partner's own customers
				db_modify("delete from partner_user_access where bruger_id = '$bid'", __FILE__ . " linje " . __LINE__);
				foreach ($sel as $rid) db_modify("insert into partner_user_access (bruger_id, regnskab_id) values ('$bid', '$rid')", __FILE__ . " linje " . __LINE__);
				db_modify("update partner_users set scope = '".($sel ? 'selected' : 'all')."' where bruger_id = '$bid'", __FILE__ . " linje " . __LINE__);
				partner_log('partner.employee_access_changed', null, $pid, "bruger $bid: ".($sel ? implode(',', $sel) : 'alle'));
				$notes[] = vr_t('Adgang gemt','Access saved');
			}
		}
	}
}

if (isset($_POST['vr_action'])) vr_prg($notes, $pid ? 'partnere.php?'.http_build_query(array('id' => $op ? $pid : null, 'tab' => $tab)) : null);
$regNames = array(); $q = db_select("select id, regnskab, db, lukket from regnskab where db != '$sqdb' order by regnskab", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $regNames[(int)$r['id']] = $r;

// ================= LIST (operator) =================
// 20261012 CL/ASR The partner list is no longer a menu item: partners are created on operator_opret.php (Regnskabstype) and
//                  listed on the customer list's tabs. Without an id the operator is sent there (after a legacy import).
if (!$pid && $op) {
	$t = isset($_GET['ltab']) && $_GET['ltab'] == 'koncerner' ? 'koncerner' : 'bogholdere';
	print "<meta http-equiv=\"refresh\" content=\"0;URL=operator_panel.php?ltab=$t".($notes ? '&msg='.rawurlencode(implode(' ', $notes)) : '')."\">"; exit;
}
if (!$pid) {
	$partners = partner_all();
	$legacy = 0; $q = db_select("select rettigheder from brugere", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) { $t = explode(",", $r['rettigheder'], 4); if (isset($t[3]) && strpos($t[3], '*') === false && array_filter(array_map('intval', explode(",", $t[3])))) $legacy++; }
	$pending = 0; $q = db_select("select bruger_id from partner_users", __FILE__ . " linje " . __LINE__); $inP = array(); while ($r = db_fetch_array($q)) $inP[] = (int)$r['bruger_id'];
	$acts = "<a class=\"vr-btn vr-primary\" href=\"#opret\">+ ".vr_t('Opret partner','Create partner')."</a>";
	vr_open(array(array(vr_t('Operatørpanel','Operator panel'), '../admin/operator_overblik.php'), vr_t('Bogholdere og koncerner','Accountants and groups')), vr_t('Bogholdere og koncerner','Accountants and groups'), vr_t('Partnere med adgang til flere regnskaber: bogholdere/revisorer med kunder og koncerner med selskaber.','Partners with access to several accounts: accountants with customers and groups with companies.'), $acts);
	vr_note($notes, vr_flash_kind());
	if ($legacy) print "<div class=\"vr-note vr-note-warn\"><form method=\"post\" class=\"vr-inline\"><span>$legacy ".vr_t('brugere har en gammel adgang_til-liste uden partner. Importér dem som bogholdere (én partner pr. bruger – kan slås sammen bagefter).','users still have a legacy adgang_til list without a partner. Import them as accountants (one partner per user; merge afterwards).')."</span><input type=\"hidden\" name=\"vr_action\" value=\"migrate\"><button class=\"vr-btn\" type=\"submit\">".vr_t('Importér','Import')."</button></form></div>";
	print "<section class=\"vr-sect\"><h2>".vr_t('Alle partnere','All partners')." <small>".count($partners)."</small></h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Navn','Name')."</th><th>".vr_t('Type','Type')."</th><th>CVR</th><th>".vr_t('Eget regnskab','Own account')."</th><th class=\"vr-r\">".vr_t('Kunder/selskaber','Customers')."</th><th class=\"vr-r\">".vr_t('Medarbejdere','Employees')."</th><th>".vr_t('Må oprette','May create')."</th></tr></thead><tbody>";
	foreach ($partners as $p) {
		$nc = db_fetch_array(db_select("select count(*) as n from regnskab_partners where partner_id = '".(int)$p['id']."' and until is null", __FILE__ . " linje " . __LINE__));
		$ne = db_fetch_array(db_select("select count(*) as n from partner_users where partner_id = '".(int)$p['id']."'", __FILE__ . " linje " . __LINE__));
		print "<tr onclick=\"location.href='partnere.php?id=".(int)$p['id']."'\"><td class=\"vr-nm\"><a href=\"partnere.php?id=".(int)$p['id']."\">".vr_h($p['name'])."</a></td><td class=\"vr-mut\">".vr_h(vr_kind($p['kind']))."</td><td class=\"vr-mut vr-num\">".vr_h($p['cvr'])."</td><td class=\"vr-mut\">".($p['home_regnskab_id'] && isset($regNames[(int)$p['home_regnskab_id']]) ? vr_h($regNames[(int)$p['home_regnskab_id']]['regnskab']) : '–')."</td><td class=\"vr-r vr-num\">".(int)$nc['n']."</td><td class=\"vr-r vr-num\">".(int)$ne['n']."</td><td>".($p['can_create'] === 't' ? '<span class="vr-st"><span class="vr-dot vr-ok"></span>'.vr_t('Ja','Yes').'</span>' : '<span class="vr-mut2">–</span>')."</td></tr>";
	}
	if (!$partners) print "<tr><td colspan=\"7\" class=\"vr-mut\">".vr_t('Ingen partnere endnu','No partners yet')."</td></tr>";
	print "</tbody></table></div></section>\n";
	print "<section class=\"vr-sect\" id=\"opret\"><h2>".vr_t('Opret partner','Create partner')."</h2><div class=\"vr-card\"><form method=\"post\" class=\"vr-form\"><input type=\"hidden\" name=\"vr_action\" value=\"create\">";
	print "<label>".vr_t('Type','Type')."<select name=\"kind\" class=\"vr-sel\"><option value=\"bogholder\">".vr_t('Bogholder/revisor','Accountant')."</option><option value=\"koncern\">".vr_t('Koncern','Group')."</option></select></label>";
	print "<label>".vr_t('Navn','Name')."<input class=\"vr-inp\" name=\"name\" required></label><label>CVR<input class=\"vr-inp\" name=\"cvr\"></label>";
	print "<label>".vr_t('Eget regnskab (moderselskab / bogholderens eget)','Own account (parent / the accountant\'s own)')."<select name=\"home_regnskab_id\" class=\"vr-sel\"><option value=\"0\">–</option>"; foreach ($regNames as $rid => $r) print "<option value=\"$rid\">".vr_h($r['regnskab'])." · ".vr_h($r['db'])."</option>"; print "</select></label>";
	print "<label class=\"vr-chk\"><input type=\"checkbox\" name=\"can_create\"> ".vr_t('Må oprette kunderegnskaber','May create customer accounts')."</label>";
	print "<div><button type=\"submit\" class=\"vr-btn vr-primary\">".vr_t('Opret','Create')."</button></div></form></div></section>\n";
	vr_close(); print "</body></html>"; exit;
}

// ================= CARD =================
$p = db_fetch_array(db_select("select * from partners where id = '$pid' and status = 'active'", __FILE__ . " linje " . __LINE__));
if (!$p) { vr_open(array(array(vr_t('Regnskaber','Accounts'), 'operator_panel.php?ltab=bogholdere'), vr_t('Partner','Partner')), vr_t('Partneren findes ikke','Partner not found'), ''); vr_close(); exit; }
$isK = ($p['kind'] == 'koncern');
$KU = $isK ? vr_t('Selskaber','Companies') : vr_t('Kunder','Customers');
if (!in_array($tab, array('oversigt','kunder','medarbejdere','indstillinger','log'), true)) $tab = 'oversigt';
$links = array(); $q = db_select("select * from regnskab_partners where partner_id = '$pid' and until is null order by since", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $links[] = $r;
$emps = array(); $q = db_select("select pu.*, b.brugernavn, b.email from partner_users pu join brugere b on b.id = pu.bruger_id where pu.partner_id = '$pid' order by pu.partner_role desc, b.brugernavn", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $emps[] = $r;
$home = $p['home_regnskab_id'] && isset($regNames[(int)$p['home_regnskab_id']]) ? $regNames[(int)$p['home_regnskab_id']] : null;

$crumbs = $op ? array(array(vr_t('Operatørpanel','Operator panel'), '../admin/operator_overblik.php'), array(vr_t('Regnskaber','Accounts'), 'operator_panel.php?ltab='.($isK ? 'koncerner' : 'bogholdere')), $p['name']) : array(array(vr_t('Bogholderpanel','Accountant panel'), '../admin/operator_overblik.php'), vr_t('Mit firma','My firm'));
$lead = ($isK ? vr_t('Koncern','Group').($home ? ' · '.vr_t('moderselskab','parent').' '.vr_h($home['regnskab']) : '') : vr_h(vr_kind($p['kind'])).($home ? ' · '.vr_t('eget regnskab','own account').' '.vr_h($home['regnskab']) : '')).($p['cvr'] ? ' · CVR '.vr_h($p['cvr']) : '').' · '.count($links).' '.strtolower($KU).' · '.count($emps).' '.vr_t('medarbejdere','employees');
vr_open($crumbs, $p['name'], $lead, '', strtolower(vr_kind($p['kind'])));
vr_note($notes, vr_flash_kind());
$tu = fn($t) => "partnere.php?id=$pid&tab=$t";
$tabs = array('oversigt' => array(vr_t('Oversigt','Overview'), $tu('oversigt'), null), 'kunder' => array($KU, $tu('kunder'), count($links)), 'medarbejdere' => array(vr_t('Medarbejdere','Employees'), $tu('medarbejdere'), count($emps)));
if ($op) $tabs['indstillinger'] = array(vr_t('Indstillinger','Settings'), $tu('indstillinger'), null);
$tabs['log'] = array(vr_t('Log','Log'), $tu('log'), null);
vr_tabs($tabs, $tab);

if ($tab == 'oversigt') {
	print "<div class=\"vr-grid2\"><section class=\"vr-sect\"><h2>".vr_t('Firma','Firm')."</h2><div class=\"vr-card vr-kv\">";
	$owner = ''; foreach ($emps as $e) if ($e['partner_role'] == 'owner') { $owner = $e['brugernavn']; break; }
	foreach (array(array(vr_t('Type','Type'), vr_h(vr_kind($p['kind']))), array('CVR', $p['cvr'] ? vr_h($p['cvr']) : '–'), array(vr_t('Eget regnskab','Own account'), $home ? "<a href=\"regnskab.php?id=".(int)$p['home_regnskab_id']."\">".vr_h($home['regnskab'])."</a>" : '–'), array(vr_t('Ejer','Owner'), $owner ? vr_h($owner) : '–'), array($KU, count($links)), array(vr_t('Medarbejdere','Employees'), count($emps)), array(vr_t('Må oprette regnskaber','May create accounts'), $p['can_create'] === 't' ? vr_t('Ja','Yes') : vr_t('Nej','No')), array(vr_t('Oprettet','Created'), vr_h(substr($p['created'],0,10)))) as $row) print "<div><span>$row[0]</span><b>$row[1]</b></div>";
	print "</div></section><section class=\"vr-sect\"><h2>".vr_t('Seneste hændelser','Latest events')."</h2><div class=\"vr-card\"><table class=\"vr-t\"><tbody>";
	$q = db_select("select l.*, b.brugernavn from partner_log l left join brugere b on b.id = l.actor_bruger_id where l.partner_id = '$pid' or l.actor_partner_id = '$pid' order by l.id desc limit 8", __FILE__ . " linje " . __LINE__); $n = 0;
	while ($r = db_fetch_array($q)) { $n++; print "<tr><td class=\"vr-mut vr-num\">".vr_h(substr($r['ts'],0,16))."</td><td>".vr_h($r['action'])."<small style=\"display:block;color:var(--text-3)\">".vr_h($r['brugernavn'])." ".vr_h($r['details'])."</small></td></tr>"; }
	if (!$n) print "<tr><td class=\"vr-mut\">".vr_t('Ingen','None')."</td></tr>";
	print "</tbody></table></div></section></div>\n";
} elseif ($tab == 'kunder') {
	$plans = ent_plans_by_ledger(); $cat = ent_catalog();
	$snaps = array(); $q = db_select("select distinct on (regnskab_id) * from usage_snapshots order by regnskab_id, taken_at desc", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $snaps[(int)$r['regnskab_id']] = $r;
	$bills = array(); $q = db_select("select * from regnskab_billing", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $bills[(int)$r['regnskab_id']] = $r;
	$stTxt = array('paid' => vr_t('Betalt','Paid'), 'pending' => vr_t('Afventer','Pending'), 'overdue' => vr_t('Forfalden','Overdue'), 'failed' => vr_t('Fejlet','Failed'), 'none' => vr_t('Ingen fakturaer','No invoices')); $stDot = array('paid' => 'vr-ok', 'pending' => 'vr-warn', 'overdue' => 'vr-bad', 'failed' => 'vr-bad', 'none' => 'vr-off');
	$members = array(); if ($home) $members[(int)$p['home_regnskab_id']] = $home['regnskab']; foreach ($links as $l) if (isset($regNames[(int)$l['regnskab_id']])) $members[(int)$l['regnskab_id']] = $regNames[(int)$l['regnskab_id']]['regnskab'];
	$mrr = 0; $out = 0; $others = array_filter(partner_all(), fn($pp) => (int)$pp['id'] != $pid);
	print "<section class=\"vr-sect\"><h2>$KU <small>".count($links)."</small></h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th class=\"vr-r\" style=\"width:50px\">Id</th><th>".findtekst('2682|Regnskab', $sprog_id)."</th><th>".vr_t('Pakke','Plan')."</th><th class=\"vr-r\">".vr_t('Brugere','Users')."</th><th class=\"vr-r\">".vr_t('Post. 12 mdr.','Entries 12 m')."</th><th>".vr_t('Betaling','Payment')."</th><th>".vr_t('Faktureres via','Invoiced via')."</th><th>".vr_t('Adgang','Access')."</th><th></th></tr></thead><tbody>";
	foreach ($links as $l) { $rid = (int)$l['regnskab_id']; $r = isset($regNames[$rid]) ? $regNames[$rid] : null; if (!$r) continue;
		$e = ent_resolve($rid); $sn = isset($snaps[$rid]) ? $snaps[$rid] : null; $b = isset($bills[$rid]) ? $bills[$rid] : null; list($payerId, $payerSrc) = bill_payer($rid);
		if ($e['plan'] && $e['price_ore'] !== null && $r['lukket'] != 'on') { $m = $e['interval'] == 'year' ? (int)round($e['price_ore'] / 12) : (int)$e['price_ore']; foreach ($e['addons'] as $ak => $ar) if (isset($cat['addons'][$ak]) && $cat['addons'][$ak]['price_ore'] !== null) $m += (int)$cat['addons'][$ak]['price_ore'] * max(1, (int)$ar['quantity']); $mrr += $m; }
		if ($b && $b['outstanding_ore']) $out += (int)$b['outstanding_ore'];
		$uPct = ($sn && $e['plan'] && $e['limits']['max_users']) ? min(100, (int)round($sn['users_active'] / $e['limits']['max_users'] * 100)) : 0; $pPct = ($sn && $e['plan'] && $e['limits']['included_postings']) ? min(100, (int)round($sn['postings_12m'] / $e['limits']['included_postings'] * 100)) : 0;
		print "<tr".($r['lukket']=='on' ? " class=\"vr-closed\"" : "")."><td class=\"vr-r vr-id\">$rid</td><td class=\"vr-nm\"><a href=\"operator_panel.php?regnskab_id=$rid\">".vr_h($r['regnskab'])."</a><small>".vr_h($r['db']).($r['lukket']=='on' ? ' · '.findtekst('387|Lukket', $sprog_id) : '')."</small></td>";
		print "<td class=\"vr-mut\">".($e['plan'] ? vr_h($e['plan']['name']) : '<span class="vr-mut2">–</span>')."</td>";
		print "<td class=\"vr-r vr-num\">".($sn ? (int)$sn['users_active'].($e['plan'] && $e['limits']['max_users'] !== null ? ' <span class="vr-mut2">/ '.(int)$e['limits']['max_users'].'</span>' : '').($uPct >= 80 ? ' <span class="vr-dot vr-warn"></span>' : '') : '<span class="vr-mut2">–</span>')."</td>";
		print "<td class=\"vr-r vr-num\">".($sn ? vr_num($sn['postings_12m']).($e['plan'] && $e['limits']['included_postings'] !== null ? ' <span class="vr-mut2">/ '.vr_num($e['limits']['included_postings']).'</span>' : '').($pPct >= 80 ? ' <span class="vr-dot vr-warn"></span>' : '') : '<span class="vr-mut2">–</span>')."</td>";
		print "<td>".($b && $b['pay_status'] ? "<span class=\"vr-st\"><span class=\"vr-dot ".$stDot[$b['pay_status']]."\"></span>".$stTxt[$b['pay_status']]."</span>".($b['outstanding_ore'] ? " <span class=\"vr-mut2\">· ".number_format($b['outstanding_ore']/100, 0, ',', '.')." kr.</span>" : "") : '<span class="vr-mut2">'.vr_t('ikke tjekket','not checked').'</span>')."</td>";
		print "<td class=\"vr-mut\">".($payerId ? vr_h(isset($regNames[$payerId]) ? $regNames[$payerId]['regnskab'] : "#$payerId")." <span class=\"vr-mut2\" title=\"".vr_h($payerSrc)."\">·</span>" : vr_t('selv','itself'))."</td>";
		print "<td class=\"vr-mut\">".($l['access']=='owner' ? vr_t('Ejer','Owner') : ($l['access']=='readonly' ? vr_t('Kun læsning','Read only') : vr_t('Fuld','Full')))." <span class=\"vr-mut2\">· ".vr_h(substr($l['since'],0,10))."</span></td>";
		print "<td class=\"vr-r\" style=\"white-space:nowrap\">".($op ? "<a class=\"vr-link\" href=\"#\" onclick=\"var e=document.getElementById('act$rid');e.hidden=!e.hidden;return false\">".vr_t('Handlinger','Actions')."</a>" : "")."</td></tr>";
		if ($op) { print "<tr id=\"act$rid\" hidden><td colspan=\"9\" style=\"background:var(--surface-2)\"><div class=\"vr-inline\" style=\"padding:6px 0 10px;gap:18px;align-items:flex-start\">";
			print "<form method=\"post\" class=\"vr-inline\"><input type=\"hidden\" name=\"vr_action\" value=\"payer\"><input type=\"hidden\" name=\"regnskab_id\" value=\"$rid\"><b style=\"font-weight:500;font-size:12.5px\">".vr_t('Betalingsansvarlig','Payer')."</b><select name=\"payer_regnskab_id\" class=\"vr-sel\"><option value=\"0\">".vr_t('Regnskabet selv / partnerens model','Itself / partner model')."</option>"; foreach ($members as $mid => $mn) if ($mid != $rid) print "<option value=\"$mid\"".(($bills[$rid]['payer_regnskab_id'] ?? 0) == $mid ? " selected" : "").">".vr_h($mn)."</option>"; print "</select><button type=\"submit\" class=\"vr-btn\">".findtekst('3|Gem', $sprog_id)."</button></form>";
			print "<form method=\"post\" class=\"vr-inline\" onsubmit=\"return confirm('".vr_t('Flyt regnskabet til den valgte partner? Historik, abonnement og log følger regnskabet.','Move the account to the chosen partner? History, subscription and log stay with the account.')."')\"><input type=\"hidden\" name=\"vr_action\" value=\"move\"><input type=\"hidden\" name=\"regnskab_id\" value=\"$rid\"><b style=\"font-weight:500;font-size:12.5px\">".vr_t('Flyt til','Move to')."</b><select name=\"to_partner_id\" class=\"vr-sel\" required><option value=\"\">".vr_t('Vælg partner…','Choose partner…')."</option>"; foreach ($others as $pp) print "<option value=\"".(int)$pp['id']."\">".vr_h($pp['name'])." · ".vr_h(vr_kind($pp['kind']))."</option>"; print "</select><select name=\"access\" class=\"vr-sel\"><option value=\"full\"".($l['access']=='full' ? " selected" : "").">".vr_t('Fuld adgang','Full access')."</option><option value=\"readonly\"".($l['access']=='readonly' ? " selected" : "").">".vr_t('Kun læsning','Read only')."</option><option value=\"owner\"".($l['access']=='owner' ? " selected" : "").">".vr_t('Ejer (koncern)','Owner (group)')."</option></select><input class=\"vr-inp\" name=\"note\" placeholder=\"".vr_t('Note','Note')."\" style=\"width:160px\"><button type=\"submit\" class=\"vr-btn\">".vr_t('Flyt','Move')."</button></form>";
			print "<form method=\"post\" style=\"display:inline\"><input type=\"hidden\" name=\"vr_action\" value=\"unlink\"><input type=\"hidden\" name=\"link_id\" value=\"".(int)$l['id']."\"><button type=\"submit\" class=\"vr-btn vr-danger\" onclick=\"return confirm('".vr_t('Fjern adgangen?','Remove access?')."')\">".vr_t('Frigør','Unlink')."</button></form></div></td></tr>"; }
	}
	if (!$links) print "<tr><td colspan=\"9\" class=\"vr-mut\">".vr_t('Ingen tilknyttet endnu','None linked yet')."</td></tr>";
	print "</tbody></table><div class=\"vr-foot\"><span>".vr_t('Samlet','Total').": MRR ".number_format($mrr/100, 0, ',', '.')." kr. · ".vr_t('udestående','outstanding')." ".number_format($out/100, 0, ',', '.')." kr. · ".vr_t('fakturering','billing').": ".($p['billing_mode'] == 'consolidated' ? vr_t('samlet via','consolidated via').' '.vr_h(isset($regNames[(int)($p['billing_regnskab_id'] ?: $p['home_regnskab_id'])]) ? $regNames[(int)($p['billing_regnskab_id'] ?: $p['home_regnskab_id'])]['regnskab'] : '–') : vr_t('individuel','individual'))."</span><span class=\"vr-grow\"></span>";
	if ($op) { print "<form method=\"post\" class=\"vr-inline\"><input type=\"hidden\" name=\"vr_action\" value=\"link\"><select name=\"regnskab_id\" class=\"vr-sel\" required><option value=\"\">".vr_t('Vælg regnskab…','Choose account…')."</option>"; foreach ($regNames as $rid2 => $r2) print "<option value=\"$rid2\">".vr_h($r2['regnskab'])." · ".vr_h($r2['db'])."</option>"; print "</select><select name=\"access\" class=\"vr-sel\"><option value=\"full\">".vr_t('Fuld adgang','Full access')."</option><option value=\"readonly\">".vr_t('Kun læsning','Read only')."</option>".($isK ? "<option value=\"owner\" selected>".vr_t('Ejer (koncern)','Owner (group)')."</option>" : "")."</select><button type=\"submit\" class=\"vr-btn\">+ ".vr_t('Tilknyt','Link')."</button></form>"; }
	print "</div></div></section>\n";
	if ($op) { print "<section class=\"vr-sect\" style=\"margin-top:22px\"><h2>".vr_t('Fakturering af gruppen','Group billing')."</h2><div class=\"vr-card\"><form method=\"post\" class=\"vr-form\"><input type=\"hidden\" name=\"vr_action\" value=\"billing\"><label>".vr_t('Model','Model')."<select name=\"billing_mode\" class=\"vr-sel\"><option value=\"individual\"".($p['billing_mode'] != 'consolidated' ? " selected" : "").">".vr_t('Individuel – hvert regnskab faktureres selv','Individual – each account is invoiced itself')."</option><option value=\"consolidated\"".($p['billing_mode'] == 'consolidated' ? " selected" : "").">".vr_t('Samlet – alle regnskaber faktureres via én enhed','Consolidated – all accounts invoiced via one entity')."</option></select></label><label>".vr_t('Betalingsansvarlig enhed (ved samlet)','Billing entity (when consolidated)')."<select name=\"billing_regnskab_id\" class=\"vr-sel\"><option value=\"0\">".($home ? vr_h($home['regnskab']).' ('.vr_t('eget regnskab','own account').')' : vr_t('– vælg –','– choose –'))."</option>"; foreach ($members as $mid => $mn) print "<option value=\"$mid\"".((int)$p['billing_regnskab_id'] == $mid ? " selected" : "").">".vr_h($mn)."</option>"; print "</select></label><p class=\"vr-sub\" style=\"margin:0\">".vr_t('Et regnskab kan afvige fra modellen med sin egen betalingsansvarlige under Handlinger. Stripe-abonnementer og fakturaer lever stadig i faktureringsregnskabet; modellen styrer, hvem panelet peger på som betaler.','An account can deviate from the model with its own payer under Actions. Stripe subscriptions and invoices still live in the billing ledger; the model controls who the panel points to as payer.')."</p><div><button type=\"submit\" class=\"vr-btn vr-primary\">".findtekst('3|Gem', $sprog_id)."</button></div></form></div></section>"; }
} elseif ($tab == 'medarbejdere') {
	print "<section class=\"vr-sect\"><h2>".vr_t('Medarbejdere','Employees')." <small>".count($emps)."</small></h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Brugernavn','Username')."</th><th>E-mail</th><th>".vr_t('Rolle i firmaet','Role in firm')."</th><th>".vr_t('Ser','Sees')."</th><th></th></tr></thead><tbody>";
	foreach ($emps as $e) {
		$sc = array(); $q = db_select("select regnskab_id from partner_user_access where bruger_id = '".(int)$e['bruger_id']."'", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $sc[] = (int)$r['regnskab_id'];
		print "<tr><td class=\"vr-nm\"><a>".vr_h($e['brugernavn'])."</a></td><td class=\"vr-mut\">".($e['email'] ? vr_h($e['email']) : '–')."</td><td>".($e['partner_role']=='owner' ? vr_t('Ejer','Owner') : vr_t('Medarbejder','Employee'))."</td><td class=\"vr-mut\">";
		$scAll = (($e['scope'] ?? 'all') != 'selected');
		if ($isOwner) { print "<form method=\"post\" class=\"vr-inline\"><input type=\"hidden\" name=\"vr_action\" value=\"emp_scope\"><input type=\"hidden\" name=\"bruger_id\" value=\"".(int)$e['bruger_id']."\"><select name=\"scope[]\" multiple size=\"3\" class=\"vr-sel\"><option value=\"0\"".($scAll ? " selected" : "").">".vr_t('Alle','All')."</option>"; foreach ($links as $l) if (isset($regNames[(int)$l['regnskab_id']])) print "<option value=\"".(int)$l['regnskab_id']."\"".(in_array((int)$l['regnskab_id'], $sc) ? " selected" : "").">".vr_h($regNames[(int)$l['regnskab_id']]['regnskab'])."</option>"; print "</select><button type=\"submit\" class=\"vr-btn vr-quiet\">".vr_t('Gem','Save')."</button></form>"; }
		else print ($scAll ? vr_t('Alle','All') : count($sc).' '.strtolower($KU));
		if (!$scAll && !$sc) print " <span class=\"vr-mut2\">".vr_t('ingen kunder valgt','no customers selected')."</span>";
		print "</td><td class=\"vr-r\">".($isOwner && (int)$e['bruger_id'] != (int)$me['id'] ? "<form method=\"post\" style=\"display:inline\"><input type=\"hidden\" name=\"vr_action\" value=\"emp_remove\"><input type=\"hidden\" name=\"bruger_id\" value=\"".(int)$e['bruger_id']."\"><button type=\"submit\" class=\"vr-link\" onclick=\"return confirm('".vr_t('Fjern medarbejderen fra firmaet?','Remove the employee from the firm?')."')\">".vr_t('Fjern','Remove')."</button></form>" : "")."</td></tr>";
	}
	if (!$emps) print "<tr><td colspan=\"5\" class=\"vr-mut\">".vr_t('Ingen medarbejdere endnu','No employees yet')."</td></tr>";
	print "</tbody></table>";
	if ($isOwner) {
		print "<div class=\"vr-foot\"><span>".vr_t('Medarbejdere tæller ikke i kundens brugergrænse','Employees do not count against the customer limit')."</span></div></div></section>";
		print "<section class=\"vr-sect\"><h2>".vr_t('Tilføj medarbejder','Add employee')."</h2><div class=\"vr-card\"><form method=\"post\" class=\"vr-form\"><input type=\"hidden\" name=\"vr_action\" value=\"emp_add\">";
		if ($op) { print "<label>".vr_t('Eksisterende masterbruger','Existing master user')."<select name=\"bruger_id\" class=\"vr-sel\"><option value=\"0\">– ".vr_t('eller opret ny nedenfor','or create new below')." –</option>"; $q = db_select("select b.id, b.brugernavn from brugere b left join partner_users pu on pu.bruger_id = b.id where pu.bruger_id is null order by b.brugernavn", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) print "<option value=\"".(int)$r['id']."\">".vr_h($r['brugernavn'])."</option>"; print "</select></label>"; }
		print "<label>".vr_t('Nyt brugernavn','New username')."<input class=\"vr-inp\" name=\"brugernavn\"></label><label>".vr_t('Adgangskode (min. 6 tegn)','Password (min. 6 chars)')."<input class=\"vr-inp\" type=\"password\" name=\"kode\"></label><label>".vr_t('Rolle','Role')."<select name=\"partner_role\" class=\"vr-sel\"><option value=\"employee\">".vr_t('Medarbejder','Employee')."</option><option value=\"owner\">".vr_t('Ejer','Owner')."</option></select></label><div><button type=\"submit\" class=\"vr-btn vr-primary\">".vr_t('Tilføj','Add')."</button></div></form></div></section>\n";
	} else print "</div></section>\n";
} elseif ($tab == 'indstillinger' && $op) {
	print "<section class=\"vr-sect\"><h2>".vr_t('Partnerens indstillinger','Partner settings')."</h2><div class=\"vr-card\"><form method=\"post\" class=\"vr-form\"><input type=\"hidden\" name=\"vr_action\" value=\"settings\">";
	print "<label>".vr_t('Type','Type')."<select name=\"kind\" class=\"vr-sel\">"; foreach (partner_kinds() as $k) print "<option value=\"$k\"".($p['kind'] == $k ? " selected" : "").">".vr_h(vr_kind($k))."</option>"; print "</select></label>";
	print "<label>".vr_t('Navn','Name')."<input class=\"vr-inp\" name=\"name\" value=\"".vr_h($p['name'])."\" required></label><label>CVR<input class=\"vr-inp\" name=\"cvr\" value=\"".vr_h($p['cvr'])."\"></label>";
	print "<label>".vr_t('Eget regnskab','Own account')."<select name=\"home_regnskab_id\" class=\"vr-sel\"><option value=\"0\">–</option>"; foreach ($regNames as $rid => $r) print "<option value=\"$rid\"".((int)$p['home_regnskab_id'] == $rid ? " selected" : "").">".vr_h($r['regnskab'])." · ".vr_h($r['db'])."</option>"; print "</select></label>";
	foreach (array('can_create' => vr_t('Må oprette kunderegnskaber','May create customer accounts'), 'can_manage_features' => vr_t('Må styre funktioner inden for pakken (P3)','May manage features within the plan (P3)'), 'can_manage_users' => vr_t('Må styre kundens brugere (P2)','May manage customer users (P2)')) as $k => $lab) print "<label class=\"vr-chk\"><input type=\"checkbox\" name=\"$k\"".($p[$k] === 't' ? " checked" : "")."> $lab</label>";
	print "<div><button type=\"submit\" class=\"vr-btn vr-primary\">".findtekst('3|Gem', $sprog_id)."</button></div></form></div></section>";
	print "<section class=\"vr-sect\"><h2>".vr_t('Farezone','Danger zone')."</h2><div class=\"vr-card\"><div class=\"vr-frow\"><div><b>".vr_t('Luk partneren','Close the partner')."</b><small>".vr_t('Fjerner alle adgange. Regnskaberne og masterbrugerne bevares.','Removes all access. Accounts and master users are kept.')."</small></div><span></span><form method=\"post\"><input type=\"hidden\" name=\"vr_action\" value=\"close\"><button type=\"submit\" class=\"vr-btn vr-danger\" onclick=\"return confirm('".vr_t('Luk partneren og fjern alle adgange?','Close the partner and remove all access?')."')\">".vr_t('Luk','Close')."</button></form></div></div></section>\n";
} else {
	print "<section class=\"vr-sect\"><h2>".vr_t('Log','Log')."</h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Tidspunkt','Time')."</th><th>".vr_t('Hvem','Who')."</th><th>".vr_t('Hvad','What')."</th><th>".findtekst('2682|Regnskab', $sprog_id)."</th><th>".vr_t('Detaljer','Details')."</th></tr></thead><tbody>";
	$q = db_select("select l.*, b.brugernavn from partner_log l left join brugere b on b.id = l.actor_bruger_id where l.partner_id = '$pid' or l.actor_partner_id = '$pid' order by l.id desc limit 200", __FILE__ . " linje " . __LINE__); $n = 0;
	while ($r = db_fetch_array($q)) { $n++; print "<tr><td class=\"vr-mut vr-num\">".vr_h(substr($r['ts'],0,16))."</td><td>".vr_h($r['brugernavn'])."</td><td>".vr_h($r['action'])."</td><td class=\"vr-mut\">".($r['regnskab_id'] && isset($regNames[(int)$r['regnskab_id']]) ? vr_h($regNames[(int)$r['regnskab_id']]['regnskab']) : '–')."</td><td class=\"vr-mut\">".vr_h($r['details'])."</td></tr>"; }
	if (!$n) print "<tr><td colspan=\"5\" class=\"vr-mut\">".vr_t('Ingen hændelser endnu','No events yet')."</td></tr>";
	print "</tbody></table></div></section>\n";
}
vr_close();
?>
</body></html>
