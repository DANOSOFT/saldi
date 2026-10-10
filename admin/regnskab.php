<?php
@session_start();
$s_id=session_id();
// --- admin/regnskab.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Ledger card (regnskabskort) for the accountant and operator panels, phase P1/P2-lite of
//                  Requirements_accountant_and_operator_panels_EN.md. Tabs: Oversigt (live counts from the customer
//                  database, partners with access, Tilknyt/Frigør for operators), Brugere (read-only list from the
//                  customer database), Funktioner (license_features, read-only until the entitlement layer exists),
//                  Log (partner_log rows for this ledger).
// 20261010 CL/ASR Abonnement tab (operator): plan, price and interval, included limits and modules, add-ons,
//                  overrides, plan change with effective date and downgrade guard, pending changes. Uses
//                  includes/entitlements.php; every change is logged as entitlement.* in partner_log.

$css="../css/standard.css";
$title="Regnskab";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("../includes/entitlements.php");
include("vr_ui.php");
partner_tables_ensure();
ent_tables_ensure();

if ($db != $sqdb) { print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/logud.php\">"; exit; }

$rid = (int) if_isset($_GET, 0, 'id');
$tab = if_isset($_GET, 'oversigt', 'tab');
if (!in_array($tab, array('oversigt','abonnement','brugere','funktioner','log'), true)) $tab = 'oversigt';
$me = partner_current_user();
$op = $me['is_operator'];
$notes = array();

$reg = db_fetch_array(db_select("select * from regnskab where id = '$rid'", __FILE__ . " linje " . __LINE__));
if (!$reg || !partner_can($rid, 'open')) {
	vr_open(array(array(vr_t('Regnskaber','Accounts'), 'vis_regnskaber.php'), vr_t('Regnskab','Account')), vr_t('Regnskabet findes ikke, eller du har ikke adgang', 'Account not found, or no access'), '');
	print "<p><a class=\"vr-btn\" href=\"vis_regnskaber.php\">&larr; ".findtekst('30|Tilbage', $sprog_id)."</a></p>"; vr_close(); exit;
}

// ---------- operator actions: link / unlink ----------
if ($op && isset($_POST['vr_action'])) {
	$act = $_POST['vr_action'];
	if ($act == 'link') {
		$pid = (int) if_isset($_POST, 0, 'partner_id');
		$access = in_array(if_isset($_POST, 'full', 'access'), array('full','readonly','owner'), true) ? $_POST['access'] : 'full';
		$note = db_escape_string(trim(if_isset($_POST, '', 'note')));
		$p = db_fetch_array(db_select("select * from partners where id = '$pid' and status = 'active'", __FILE__ . " linje " . __LINE__));
		if (!$p) $notes[] = vr_t('Partneren findes ikke', 'Partner not found');
		elseif (db_fetch_array(db_select("select 1 from regnskab_partners where regnskab_id = '$rid' and partner_id = '$pid' and until is null", __FILE__ . " linje " . __LINE__))) $notes[] = vr_t('Partneren har allerede adgang', 'The partner already has access');
		elseif ($access == 'owner' && db_fetch_array(db_select("select 1 from regnskab_partners where regnskab_id = '$rid' and access = 'owner' and until is null", __FILE__ . " linje " . __LINE__))) $notes[] = vr_t('Regnskabet har allerede en ejer', 'The account already has an owner');
		else {
			db_modify("insert into regnskab_partners (regnskab_id, partner_id, access, created_by, note) values ('$rid', '$pid', '$access', '".(int)$me['id']."', '$note')", __FILE__ . " linje " . __LINE__);
			partner_log('partner.link_added', $rid, $pid, $p['name'].' ('.$access.')');
			$notes[] = vr_h($p['name']).' '.vr_t('har nu adgang', 'now has access');
		}
	} elseif ($act == 'unlink') {
		$lid = (int) if_isset($_POST, 0, 'link_id');
		$l = db_fetch_array(db_select("select rp.*, p.name from regnskab_partners rp join partners p on p.id = rp.partner_id where rp.id = '$lid' and rp.regnskab_id = '$rid' and rp.until is null", __FILE__ . " linje " . __LINE__));
		if ($l) {
			db_modify("update regnskab_partners set until = now() where id = '$lid'", __FILE__ . " linje " . __LINE__);
			partner_log('partner.link_removed', $rid, (int)$l['partner_id'], $l['name']);
			$notes[] = vr_h($l['name']).' '.vr_t('har ikke længere adgang', 'no longer has access');
		}
	}
}

// ---------- operator actions: subscription ----------
if ($op && isset($_POST['ent_action'])) {
	$ea = $_POST['ent_action']; $err = '';
	if ($ea == 'set_plan') {
		$target = if_isset($_POST, '', 'plan_key'); $ef = trim(if_isset($_POST, '', 'effective_from')); $reason = trim(if_isset($_POST, '', 'reason'));
		$usage = isset($vr_usage_for_guard) ? $vr_usage_for_guard : array();
		$conf = ent_conflicts($target, ent_live_usage_quick($reg));
		if ($conf && !isset($_POST['force'])) { $notes[] = vr_t('Nedgraderingen er ikke gennemført. Løs konflikterne, eller markér "Gennemfør alligevel":', 'Downgrade not applied. Resolve the conflicts or tick "Apply anyway":'); foreach ($conf as $cf) $notes[] = '• '.$cf; }
		else { $err = ent_set_plan($rid, $target, $ef, $reason.($conf ? ' (gennemført trods konflikter)' : '')); if (!$err) $notes[] = ($ef && $ef > date('Y-m-d')) ? vr_t('Pakkeskiftet er planlagt','Plan change scheduled') : vr_t('Pakken er ændret','Plan changed'); }
	} elseif ($ea == 'cancel_change') { ent_cancel_change($rid, (int)if_isset($_POST, 0, 'change_id')); $notes[] = vr_t('Planlagt ændring annulleret','Scheduled change cancelled'); }
	elseif ($ea == 'set_addon') { $err = ent_set_addon($rid, if_isset($_POST, '', 'addon_key'), if_isset($_POST, 1, 'quantity'), trim(if_isset($_POST, '', 'expires_at'))); if (!$err) $notes[] = vr_t('Tillæg gemt','Add-on saved'); }
	elseif ($ea == 'remove_addon') { ent_remove_addon($rid, if_isset($_POST, '', 'addon_key')); $notes[] = vr_t('Tillæg fjernet','Add-on removed'); }
	elseif ($ea == 'add_override') { $err = ent_add_override($rid, if_isset($_POST, '', 'kind'), trim(if_isset($_POST, '', 'key')), trim(if_isset($_POST, '', 'value')), trim(if_isset($_POST, '', 'reason')), trim(if_isset($_POST, '', 'expires_at'))); if (!$err) $notes[] = vr_t('Tilsidesættelse oprettet','Override created'); }
	elseif ($ea == 'expire_override') { ent_expire_override($rid, (int)if_isset($_POST, 0, 'override_id')); $notes[] = vr_t('Tilsidesættelsen er udløbet','Override expired'); }
	if ($err) $notes[] = $err;
}
function ent_live_usage_quick($reg) { # users/items/postings from the customer db for the downgrade guard
	global $sqhost, $squser, $sqpass; $u = array();
	if (!$reg['db'] || !db_exists($reg['db'])) return $u;
	if (!@db_connect($sqhost, $squser, $sqpass, $reg['db'], __FILE__ . " linje " . __LINE__)) return $u;
	if (tbl_exists('brugere')) { $cols = array(); $q = db_select("select column_name from information_schema.columns where table_name = 'brugere'", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $cols[] = $r['column_name']; $r = db_fetch_array(db_select("select count(*) as n from brugere".(in_array('revisor', $cols) ? " where revisor is not true" : ""), __FILE__ . " linje " . __LINE__)); $u['users_active'] = (int)$r['n']; }
	if (tbl_exists('transaktioner')) { $r = db_fetch_array(db_select("select count(id) as n from transaktioner where logdate >= '".date('Y-m-d', strtotime('-1 year'))."'", __FILE__ . " linje " . __LINE__)); $u['postings_12m'] = (int)$r['n']; }
	if (tbl_exists('varer')) { $r = db_fetch_array(db_select("select count(id) as n from varer", __FILE__ . " linje " . __LINE__)); $u['items'] = (int)$r['n']; }
	include("../includes/connect.php");
	return $u;
}

// ---------- data from the customer database (live, on this page only) ----------
$live = array('ok' => false, 'cvr' => '', 'firma' => '', 'users' => array(), 'active' => 0, 'trans12' => null, 'last' => null);
if ($reg['db'] && db_exists($reg['db'])) {
	$cc = @db_connect($sqhost, $squser, $sqpass, $reg['db'], __FILE__ . " linje " . __LINE__);
	if ($cc) {
		$live['ok'] = true;
		if ($r = db_fetch_array(db_select("select firmanavn, cvrnr from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__))) { $live['firma'] = $r['firmanavn']; $live['cvr'] = $r['cvrnr']; }
		if (tbl_exists('brugere')) {
			// older customer databases lack some of these columns – select only what exists
			$cols = array(); $q = db_select("select column_name from information_schema.columns where table_name = 'brugere'", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) $cols[] = $r['column_name'];
			$sel = array('id', 'brugernavn', 'rettigheder');
			foreach (array('email', 'tlf', 'twofactor', 'revisor') as $c) $sel[] = in_array($c, $cols) ? $c : "NULL as $c";
			$q = db_select("select ".implode(', ', $sel)." from brugere order by brugernavn", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) { $live['users'][] = $r; if (!($r['revisor'] === 't' || $r['revisor'] === true || $r['revisor'] === '1')) $live['active']++; }
		}
		if (tbl_exists('transaktioner')) {
			$dd = date('Y-m-d', strtotime('-1 year'));
			if ($r = db_fetch_array(db_select("select count(id) as n, max(logdate) as l from transaktioner where logdate >= '$dd'", __FILE__ . " linje " . __LINE__))) { $live['trans12'] = (int)$r['n']; $live['last'] = $r['l']; }
		}
		include("../includes/connect.php"); # back to master
	}
}
$links = array();
$q = db_select("select rp.*, p.name, p.kind from regnskab_partners rp join partners p on p.id = rp.partner_id where rp.regnskab_id = '$rid' and rp.until is null order by p.name", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) $links[] = $r;
$home = db_fetch_array(db_select("select * from partners where home_regnskab_id = '$rid' and status = 'active'", __FILE__ . " linje " . __LINE__));
$isClosed = ($reg['lukket'] == 'on');
$ent = ent_resolve($rid);
$cat = ent_catalog();

// ---------- page ----------
$acts = '';
$acts .= "<a class=\"vr-btn vr-primary\" href=\"aaben_regnskab.php?db_id=$rid\">".vr_t('Åbn regnskab', 'Open account')."</a>";
$lead = ($isClosed ? '<span class="vr-st"><span class="vr-dot vr-off"></span>'.findtekst('387|Lukket', $sprog_id).'</span> · ' : '').vr_h($reg['db']).' · '.($links ? vr_t('Bogholder', 'Accountant').': '.implode(', ', array_map(fn($l) => vr_h($l['name']), $links)) : ($home ? vr_t('Partnerens eget regnskab', 'The partner\'s own account') : vr_t('Direkte kunde', 'Direct customer')));
$crumb0 = $op ? array(vr_t('Operatørpanel','Operator panel'), '../index/admin_menu.php') : array(vr_t('Bogholderpanel','Accountant panel'), '../index/admin_menu.php');
vr_open(array($crumb0, array(vr_t('Regnskaber','Accounts'), 'vis_regnskaber.php'), $reg['regnskab']), $reg['regnskab'], $lead, $acts, $home ? strtolower(vr_kind($home['kind'])) : '');
vr_note($notes);
$tu = fn($t) => "regnskab.php?id=$rid&tab=$t";
$vr_tabs_all = array(
	'oversigt'   => array(vr_t('Oversigt','Overview'), $tu('oversigt'), null),
	'abonnement' => array(vr_t('Abonnement','Subscription'), $tu('abonnement'), null),
	'brugere'    => array(vr_t('Brugere','Users'), $tu('brugere'), count($live['users'])),
	'funktioner' => array(vr_t('Funktioner','Features'), $tu('funktioner'), null),
	'log'        => array(vr_t('Log','Log'), $tu('log'), null),
);
if (!$op) unset($vr_tabs_all['abonnement']);
vr_tabs($vr_tabs_all, $tab);
if (!$op && $tab == 'abonnement') $tab = 'oversigt';

if ($tab == 'oversigt') {
	print "<div class=\"vr-grid2\">\n<section class=\"vr-sect\"><h2>".vr_t('Regnskab','Account')."</h2><div class=\"vr-card vr-kv\">";
	$kv = array(
		array(vr_t('Firma i regnskabet','Company in the account'), $live['firma'] ? vr_h($live['firma']) : '–'),
		array('CVR', $live['cvr'] ? vr_h($live['cvr']) : '–'),
		array(vr_t('Database','Database'), '<span class="vr-num">'.vr_h($reg['db']).'</span>'),
		array(vr_t('Pakke','Plan'), $ent['plan'] ? vr_h($ent['plan']['name']).($op ? ' <a class="vr-card-link" href="regnskab.php?id='.$rid.'&tab=abonnement">'.vr_t('Abonnement','Subscription').'</a>' : '') : '<span class="vr-mut2">'.vr_t('ingen pakke endnu','no plan yet').($op ? ' · <a href="regnskab.php?id='.$rid.'&tab=abonnement">'.vr_t('vælg','choose').'</a>' : '').'</span>'),
		array(findtekst('777|Brugere', $sprog_id), ($live['ok'] ? $live['active'].' '.vr_t('aktive af','active of').' ' : '').($ent['plan'] ? ($ent['limits']['max_users'] === null ? vr_t('ubegrænset','unlimited') : $ent['limits']['max_users']) : vr_num($reg['brugerantal']))),
		array(vr_t('Posteringer 12 mdr.','Entries 12 months'), ($live['trans12'] !== null ? vr_num($live['trans12']) : vr_num($reg['posteret'])).' '.vr_t('af','of').' '.($ent['plan'] ? ($ent['limits']['included_postings'] === null ? vr_t('ubegrænset','unlimited') : vr_num($ent['limits']['included_postings'])) : vr_num($reg['posteringer'])).($live['trans12'] !== null ? ' <span class="vr-mut2">('.vr_t('live','live').')</span>' : ' <span class="vr-mut2">('.vr_t('sidst genberegnet','last recalculated').')</span>')),
		array(vr_t('Sidst aktiv','Last active'), $live['last'] ? vr_rel($live['last']).' <span class="vr-mut2">'.vr_h(substr($live['last'],0,10)).'</span>' : vr_rel($reg['sidst'])),
		array(vr_t('Status','Status'), '<span class="vr-st"><span class="vr-dot '.($isClosed ? 'vr-off' : 'vr-ok').'"></span>'.($isClosed ? findtekst('387|Lukket', $sprog_id) : vr_t('Åbent','Open')).'</span>'),
		array('E-mail', $reg['email'] ? vr_h($reg['email']) : '–'),
	);
	foreach ($kv as $row) print "<div><span>$row[0]</span><b>$row[1]</b></div>";
	print "</div>".(!$live['ok'] ? "<p class=\"vr-sub\">".vr_t('Kunne ikke læse fra regnskabets database – tallene er fra sidste genberegning.','Could not read the account database – numbers are from the last recalculation.')."</p>" : "")."</section>\n";
	print "<section class=\"vr-sect\"><h2>".vr_t('Bogholdere og koncern med adgang','Accountants and group with access').($op ? " <small>".vr_t('operatøren styrer tilknytning','the operator manages links')."</small>" : "")."</h2><div class=\"vr-card\">";
	if ($links) foreach ($links as $l) {
		print "<div class=\"vr-frow\"><div><b><a href=\"partnere.php?id=".(int)$l['partner_id']."\">".vr_h($l['name'])."</a> <span class=\"vr-pill\">".vr_h(strtolower(vr_kind($l['kind'])))."</span></b><small>".($l['access']=='owner' ? vr_t('Ejer','Owner') : ($l['access']=='readonly' ? vr_t('Kun læsning','Read only') : vr_t('Fuld adgang','Full access')))." &middot; ".vr_t('siden','since')." ".vr_h(substr($l['since'],0,10)).($l['note'] ? " &middot; ".vr_h($l['note']) : "")."</small></div><span></span>";
		if ($op) print "<form method=\"post\" style=\"display:inline\"><input type=\"hidden\" name=\"vr_action\" value=\"unlink\"><input type=\"hidden\" name=\"link_id\" value=\"".(int)$l['id']."\"><button type=\"submit\" class=\"vr-link\" onclick=\"return confirm('".vr_t('Fjern adgangen?','Remove access?')."')\">".vr_t('Frigør','Unlink')."</button></form>"; else print "<span></span>";
		print "</div>";
	} else print "<div class=\"vr-empty\"><b>".vr_t('Ingen bogholder','No accountant')."</b><span>".($home ? vr_t('Regnskabet er partnerens eget.','This is the partner\'s own account.') : vr_t('Regnskabet er en direkte kunde.','The account is a direct customer.'))."</span></div>";
	if ($op) {
		$partners = partner_all();
		print "<div class=\"vr-foot\"><form method=\"post\" class=\"vr-inline\"><input type=\"hidden\" name=\"vr_action\" value=\"link\"><select name=\"partner_id\" class=\"vr-sel\" required><option value=\"\">".vr_t('Vælg bogholder eller koncern…','Choose accountant or group…')."</option>";
		foreach ($partners as $pp) print "<option value=\"".(int)$pp['id']."\">".vr_h($pp['name'])." · ".vr_h(vr_kind($pp['kind']))."</option>";
		print "</select><select name=\"access\" class=\"vr-sel\"><option value=\"full\">".vr_t('Fuld adgang','Full access')."</option><option value=\"readonly\">".vr_t('Kun læsning','Read only')."</option><option value=\"owner\">".vr_t('Ejer (koncern)','Owner (group)')."</option></select><input class=\"vr-inp\" name=\"note\" placeholder=\"".vr_t('Note','Note')."\"><button type=\"submit\" class=\"vr-btn\">+ ".vr_t('Tilknyt','Link')."</button></form></div>";
	}
	print "</div></section>\n</div>\n";
} elseif ($tab == 'abonnement' && $op) {
	$P = $ent['plan']; $today = date('Y-m-d');
	$lim = fn($v) => $v === null ? vr_t('ubegrænset','unlimited') : vr_num($v);
	print "<div class=\"vr-grid2\">\n<section class=\"vr-sect\"><h2>".vr_t('Pakke','Plan')."</h2><div class=\"vr-card vr-kv\">";
	if ($P) {
		$price = $ent['price_ore'] === null ? vr_t('efter aftale','by agreement') : ent_kr($ent['price_ore']).' / '.($ent['interval'] == 'year' ? vr_t('år','year') : vr_t('md.','month'));
		$src = array('manual' => vr_t('manuel (sat af operatør)','manual (set by operator)'), 'stripe' => 'Stripe', 'override' => vr_t('tilsidesættelse','override'), 'migration' => vr_t('overgangsordning','grandfathered'), 'scheduled' => vr_t('planlagt skift','scheduled change'));
		$rowsKv = array(
			array(vr_t('Pakke','Plan'), '<b>'.vr_h($P['name']).'</b>'.(count(array_filter($ent['overrides'], fn($o) => $o['kind']=='plan')) ? ' <span class="vr-pill">'.vr_t('via tilsidesættelse','via override').'</span>' : '')),
			array(vr_t('Pris','Price'), $price.' <span class="vr-mut2">'.vr_t('ekskl. moms','excl. VAT').'</span>'),
			array(vr_t('Faktureringsinterval','Billing interval'), $ent['interval'] == 'year' ? vr_t('Årligt','Yearly') : vr_t('Månedligt','Monthly')),
			array(vr_t('Kilde','Source'), isset($src[$ent['row']['source']]) ? $src[$ent['row']['source']] : vr_h($ent['row']['source'])),
			array(vr_t('Gældende fra','Valid from'), $ent['row']['valid_from'] ? vr_h($ent['row']['valid_from']) : '–'),
			array(vr_t('Stripe-status','Stripe status'), $ent['row']['subscription_status'] && $ent['row']['subscription_status'] != 'none' ? vr_h($ent['row']['subscription_status']) : '<span class="vr-mut2">'.vr_t('ikke koblet','not linked').'</span>'),
			array(vr_t('Håndhævelse','Enforcement'), $ent['row']['enforcement'] == 'enforce' ? vr_t('Håndhæves','Enforced') : vr_t('Observerer (ingen begrænsning endnu)','Observe (no gating yet)')),
			array(vr_t('Katalog','Catalogue'), $cat['version'] ? vr_h($cat['version']['name']) : '–'),
		);
	} else $rowsKv = array(array(vr_t('Pakke','Plan'), '<span class="vr-mut2">'.vr_t('Ingen pakke registreret. Vælg en nedenfor.','No plan registered. Choose one below.').'</span>'));
	foreach ($rowsKv as $row) print "<div><span>$row[0]</span><b>$row[1]</b></div>";
	print "</div>";
	// change plan
	print "<h2 style=\"margin-top:18px\">".vr_t('Skift pakke','Change plan')."</h2><div class=\"vr-card\"><form method=\"post\" class=\"vr-form\"><input type=\"hidden\" name=\"ent_action\" value=\"set_plan\">";
	print "<label>".vr_t('Ny pakke','New plan')."<select name=\"plan_key\" class=\"vr-sel\">";
	foreach ($cat['plans'] as $k => $pl) print "<option value=\"$k\"".($P && $P['plan_key'] == $k ? " selected" : "").">".vr_h($pl['name'])." · ".($pl['price_ore'] === null ? vr_t('efter aftale','by agreement') : ent_kr((int)$pl['price_ore']).'/md.')."</option>";
	print "</select></label><label>".vr_t('Ikrafttrædelse','Effective from')."<input class=\"vr-inp\" type=\"date\" name=\"effective_from\" value=\"$today\" min=\"$today\"></label><label>".vr_t('Begrundelse','Reason')."<input class=\"vr-inp\" name=\"reason\" placeholder=\"".vr_t('Fx aftale pr. mail 10/10','e.g. agreed by mail 10/10')."\"></label>";
	print "<label class=\"vr-chk\"><input type=\"checkbox\" name=\"force\"> ".vr_t('Gennemfør alligevel, selv om forbruget overstiger den nye pakkes grænser','Apply anyway, even if usage exceeds the new plan limits')."</label>";
	print "<div><button type=\"submit\" class=\"vr-btn vr-primary\">".vr_t('Skift pakke','Change plan')."</button> <span class=\"vr-mut2\" style=\"font-size:12.5px\">".vr_t('En dato i fremtiden planlægger skiftet; nedgradering tjekkes mod aktuelt forbrug.','A future date schedules the change; downgrades are checked against current usage.')."</span></div></form>";
	if ($ent['pending']) { print "<div class=\"vr-foot\" style=\"display:block\"><b style=\"font-weight:600\">".vr_t('Planlagte ændringer','Scheduled changes')."</b>"; foreach ($ent['pending'] as $pc) print "<div class=\"vr-frow\" style=\"padding:8px 0\"><div><b>".vr_h(ent_plan_name($pc['plan_key']))." ".vr_t('fra','from')." ".vr_h($pc['effective_from'])."</b><small>".vr_h($pc['reason'])." · ".vr_h($pc['created_by'])."</small></div><span></span><form method=\"post\"><input type=\"hidden\" name=\"ent_action\" value=\"cancel_change\"><input type=\"hidden\" name=\"change_id\" value=\"".(int)$pc['id']."\"><button class=\"vr-link\" type=\"submit\">".vr_t('Annullér','Cancel')."</button></form></div>"; print "</div>"; }
	print "</div></section>\n";
	// included
	print "<section class=\"vr-sect\"><h2>".vr_t('Inkluderet i pakken','Included in the plan')."</h2><div class=\"vr-card vr-kv\">";
	if ($P) {
		$ovKeys = array(); foreach ($ent['overrides'] as $o) if ($o['kind'] == 'limit') $ovKeys[$o['key']] = $o;
		$mark = fn($k) => isset($ovKeys[$k]) ? ' <span class="vr-pill">'.vr_t('tilsidesat','overridden').'</span>' : '';
		print "<div><span>".vr_t('Brugere','Users')."</span><b>".$ent['limits']['included_users']." ".vr_t('inkl., maks.','incl., max')." ".$lim($ent['limits']['max_users']).$mark('max_users').($ent['extra_user_price_ore'] ? " <span class=\"vr-mut2\">· ".vr_t('ekstra bruger','extra user')." ".ent_kr($ent['extra_user_price_ore'])."/md.</span>" : "")."</b></div>";
		print "<div><span>".vr_t('Posteringer (12 mdr.)','Entries (12 m)')."</span><b>".$lim($ent['limits']['included_postings']).$mark('included_postings')."</b></div>";
		print "<div><span>".vr_t('Varer','Items')."</span><b>".$lim($ent['limits']['max_items']).$mark('max_items')."</b></div>";
		print "<div><span>".vr_t('Webshops','Webshops')."</span><b>".$lim($ent['limits']['max_webshops']).$mark('max_webshops')."</b></div>";
		print "</div>";
		$areas = array(); foreach ($cat['feature_names'] as $fk => $fn) { $lv = isset($ent['features'][$fk]) ? $ent['features'][$fk] : 'none'; $areas[$fn[1]][] = array($fk, $fn[0], $lv); }
		print "<h2 style=\"margin-top:18px\">".vr_t('Moduler og funktioner','Modules and features')."</h2><div class=\"vr-card\">";
		foreach ($areas as $an => $fs) { print "<div class=\"vr-frow\" style=\"background:var(--surface-2);padding:7px 20px\"><b style=\"font-size:12.5px;color:var(--text-2)\">".vr_h($an)."</b><span></span><span></span></div>"; foreach ($fs as $f) { $lvTxt = array('included' => vr_t('Inkluderet','Included'), 'basic' => vr_t('Basis','Basic'), 'addon' => vr_t('Tillæg','Add-on'), 'none' => vr_t('Ikke med','Not included')); print "<div class=\"vr-frow".($f[2]=='none' ? " vr-off" : "")."\" style=\"padding:8px 20px\"><div><b style=\"font-weight:400\">".vr_h($f[1])."</b></div><span class=\"vr-lvl\">".$lvTxt[$f[2]]."</span><span class=\"vr-st\"><span class=\"vr-dot ".($f[2]=='included'||$f[2]=='basic' ? 'vr-ok' : 'vr-off')."\"></span></span></div>"; } }
		print "</div>";
	} else print "<div><span class=\"vr-mut2\">–</span></div></div>";
	print "</section>\n</div>\n";
	// add-ons
	print "<div class=\"vr-grid2\" style=\"margin-top:22px\">\n<section class=\"vr-sect\"><h2>".vr_t('Tillæg','Add-ons')." <small>".count($ent['addons'])."</small></h2><div class=\"vr-card\">";
	foreach ($ent['addons'] as $ak => $ar) { $ad = isset($cat['addons'][$ak]) ? $cat['addons'][$ak] : null; print "<div class=\"vr-frow\"><div><b>".vr_h($ad ? $ad['name'] : $ak).($ad && $ad['quantity_based'] ? " × ".(int)$ar['quantity'] : "")."</b><small>".($ad && $ad['price_ore'] !== null ? ent_kr((int)$ad['price_ore'] * max(1,(int)$ar['quantity'])).'/md. · ' : '').vr_h($ar['source']).($ar['expires_at'] ? ' · '.vr_t('udløber','expires').' '.vr_h($ar['expires_at']) : '')."</small></div><span></span>".($ar['source'] != 'override' ? "<form method=\"post\"><input type=\"hidden\" name=\"ent_action\" value=\"remove_addon\"><input type=\"hidden\" name=\"addon_key\" value=\"".vr_h($ak)."\"><button class=\"vr-link\" type=\"submit\" onclick=\"return confirm('".vr_t('Fjern tillægget?','Remove the add-on?')."')\">".vr_t('Fjern','Remove')."</button></form>" : "<span></span>")."</div>"; }
	if (!$ent['addons']) print "<div class=\"vr-empty\"><b>".vr_t('Ingen tillæg','No add-ons')."</b></div>";
	if ($P) { print "<div class=\"vr-foot\"><form method=\"post\" class=\"vr-inline\"><input type=\"hidden\" name=\"ent_action\" value=\"set_addon\"><select name=\"addon_key\" class=\"vr-sel\">"; foreach ($cat['addons'] as $ak => $ad) { $avail = in_array($P['plan_key'], explode(',', $ad['available_on'])); print "<option value=\"$ak\"".($avail ? "" : " disabled").">".vr_h($ad['name']).($ad['price_ore'] !== null ? ' · '.ent_kr((int)$ad['price_ore']).'/md.' : '').($avail ? '' : ' · '.vr_t('ikke på denne pakke','not on this plan'))."</option>"; } print "</select><input class=\"vr-inp\" type=\"number\" name=\"quantity\" value=\"1\" min=\"1\" style=\"width:70px\" title=\"".vr_t('Antal','Quantity')."\"><input class=\"vr-inp\" type=\"date\" name=\"expires_at\" title=\"".vr_t('Udløber (valgfrit)','Expires (optional)')."\"><button type=\"submit\" class=\"vr-btn\">+ ".vr_t('Tilføj','Add')."</button></form></div>"; }
	print "</div></section>\n";
	// overrides
	print "<section class=\"vr-sect\"><h2>".vr_t('Individuelle aftaler (tilsidesættelser)','Individual terms (overrides)')." <small>".count($ent['overrides'])."</small></h2><div class=\"vr-card\">";
	$kinds = array('plan' => vr_t('Pakke','Plan'), 'feature' => vr_t('Funktion','Feature'), 'limit' => vr_t('Grænse','Limit'), 'addon' => vr_t('Tillæg','Add-on'));
	foreach ($ent['overrides'] as $o) print "<div class=\"vr-frow\"><div><b>".vr_h($kinds[$o['kind']])." · ".vr_h($o['key'])." = ".vr_h($o['value'])."</b><small>".vr_h($o['reason'])." · ".vr_h($o['created_by'])." ".vr_h(substr($o['created_at'],0,10)).($o['expires_at'] ? ' · '.vr_t('udløber','expires').' '.vr_h($o['expires_at']) : ' · '.vr_t('uden udløb','no expiry'))."</small></div><span></span><form method=\"post\"><input type=\"hidden\" name=\"ent_action\" value=\"expire_override\"><input type=\"hidden\" name=\"override_id\" value=\"".(int)$o['id']."\"><button class=\"vr-link\" type=\"submit\" onclick=\"return confirm('".vr_t('Lad tilsidesættelsen udløbe nu?','Expire the override now?')."')\">".vr_t('Udløb nu','Expire now')."</button></form></div>";
	if (!$ent['overrides']) print "<div class=\"vr-empty\"><b>".vr_t('Ingen individuelle aftaler','No individual terms')."</b><span>".vr_t('Brug dem til prøveperioder, goodwill-grænser og pilotfunktioner. Begrundelse og udløb er påkrævet.','Use for trials, goodwill limits and pilot features. Reason and expiry are required.')."</span></div>";
	print "<div class=\"vr-foot\" style=\"display:block\"><form method=\"post\" class=\"vr-form\" style=\"padding:0;max-width:none\"><input type=\"hidden\" name=\"ent_action\" value=\"add_override\"><div class=\"vr-inline\"><select name=\"kind\" class=\"vr-sel\" id=\"ovKind\">"; foreach ($kinds as $k => $n) print "<option value=\"$k\">$n</option>"; print "</select><input class=\"vr-inp\" name=\"key\" list=\"ovKeys\" placeholder=\"".vr_t('nøgle (fx max_users, lager.styring, booking)','key (e.g. max_users, lager.styring, booking)')."\" style=\"min-width:260px\"><datalist id=\"ovKeys\">"; foreach (array('max_users','included_postings','max_items','max_webshops') as $k) print "<option value=\"$k\">"; foreach ($cat['feature_names'] as $fk => $fn) print "<option value=\"$fk\">"; foreach ($cat['addons'] as $ak => $ad) print "<option value=\"$ak\">"; foreach ($cat['plans'] as $pk => $pl) print "<option value=\"$pk\">"; print "</datalist><input class=\"vr-inp\" name=\"value\" placeholder=\"".vr_t('værdi (on/off, tal, pakke)','value (on/off, number, plan)')."\" style=\"width:160px\"><input class=\"vr-inp\" type=\"date\" name=\"expires_at\" title=\"".vr_t('Udløber','Expires')."\"></div><div class=\"vr-inline\" style=\"margin-top:8px\"><input class=\"vr-inp\" name=\"reason\" placeholder=\"".vr_t('Begrundelse (påkrævet)','Reason (required)')."\" style=\"flex:1;min-width:260px\" required><button type=\"submit\" class=\"vr-btn\">+ ".vr_t('Opret','Create')."</button></div></form></div>";
	print "</div></section>\n</div>\n";
} elseif ($tab == 'brugere') {
	print "<section class=\"vr-sect\"><h2>".vr_t('Kundens brugere','Customer users')." <small>".$live['active']." ".vr_t('aktive af','active of')." ".vr_num($reg['brugerantal'])."</small></h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Brugernavn','Username')."</th><th>E-mail</th><th>".vr_t('Telefon','Phone')."</th><th>2FA</th><th>".vr_t('Rettigheder','Rights')."</th><th>".vr_t('Type','Type')."</th></tr></thead><tbody>";
	if (!$live['ok']) print "<tr><td colspan=\"6\" class=\"vr-mut\">".vr_t('Kunne ikke læse fra regnskabets database','Could not read the account database')."</td></tr>";
	foreach ($live['users'] as $u) {
		$isRev = ($u['revisor'] === 't' || $u['revisor'] === true || $u['revisor'] === '1');
		$tfa = ($u['twofactor'] === 't' || $u['twofactor'] === true || $u['twofactor'] === '1');
		$rights = (strpos((string)$u['rettigheder'], '1') === 0 && strlen((string)$u['rettigheder']) > 10 && strpos((string)$u['rettigheder'], '0') === false) ? vr_t('Alt','All') : vr_t('Begrænset','Limited');
		print "<tr><td class=\"vr-nm\"><a>".vr_h($u['brugernavn'])."</a></td><td class=\"vr-mut\">".($u['email'] ? vr_h($u['email']) : '–')."</td><td class=\"vr-mut\">".($u['tlf'] ? vr_h($u['tlf']) : '–')."</td><td>".($tfa ? '<span class="vr-st"><span class="vr-dot vr-ok"></span>'.vr_t('Ja','Yes').'</span>' : '<span class="vr-mut2">–</span>')."</td><td class=\"vr-mut\">$rights</td><td>".($isRev ? '<span class="vr-pill">revisor</span>' : vr_t('Bruger','User'))."</td></tr>";
	}
	print "</tbody></table><div class=\"vr-foot\"><span>".vr_t('Kun visning i denne fase. Oprettelse, invitation og roller kommer med roller etape 2 (P2).','Read-only in this phase. Create, invite and roles come with roles stage 2 (P2).')."</span></div></div></section>\n";
} elseif ($tab == 'funktioner') {
	$feat = array('booking' => vr_t('Booking / udlejning','Booking / rental'), 'lager' => vr_t('Lager (varer)','Inventory'), 'kreditor' => vr_t('Kreditor','Creditors'));
	$lic = array();
	if (db_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = 'license_features'", __FILE__ . " linje " . __LINE__))) {
		$q = db_select("select feature_key, enabled, expires_at from license_features where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) $lic[$r['feature_key']] = $r;
	}
	print "<p class=\"vr-sub\">".vr_t('I dag styres kun tre funktioner centralt (license_manager). Styring af funktioner inden for pakken og forslag om tilkøb kommer med pakkelaget (P3).','Today only three features are licensed centrally. Plan-based toggles and purchase proposals come with the entitlement layer (P3).')."</p><section class=\"vr-sect\"><h2>".vr_t('Licenserede funktioner','Licensed features')."</h2><div class=\"vr-card\">";
	foreach ($feat as $k => $n) {
		$on = !isset($lic[$k]) || (($lic[$k]['enabled'] === 't' || $lic[$k]['enabled'] === true) && (!$lic[$k]['expires_at'] || strtotime($lic[$k]['expires_at']) >= strtotime(date('Y-m-d'))));
		print "<div class=\"vr-frow".($on ? "" : " vr-off")."\"><div><b>".$n."</b><small>$k".(isset($lic[$k]) && $lic[$k]['expires_at'] ? " &middot; ".vr_t('udløber','expires')." ".vr_h(substr($lic[$k]['expires_at'],0,10)) : "")."</small></div><span class=\"vr-lvl\">".($on ? vr_t('Slået til','Enabled') : vr_t('Slået fra','Disabled'))."</span><span class=\"vr-st\"><span class=\"vr-dot ".($on ? 'vr-ok' : 'vr-off')."\"></span></span></div>";
	}
	print "</div>".($op ? "<p class=\"vr-sub\"><a href=\"license_manager.php?regnskab_id=$rid\">".vr_t('Redigér i license_manager','Edit in license_manager')."</a></p>" : "")."</section>\n";
} else {
	$where = "regnskab_id = '$rid'".($op ? "" : " and actor_partner_id = '".(int)$me['partner']['partner_id']."'");
	print "<section class=\"vr-sect\"><h2>".vr_t('Hændelser via panelet','Events via the panel')."</h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Tidspunkt','Time')."</th><th>".vr_t('Hvem','Who')."</th><th>".vr_t('Hvad','What')."</th><th>".vr_t('Detaljer','Details')."</th></tr></thead><tbody>";
	$q = db_select("select l.*, b.brugernavn, p.name as pname from partner_log l left join brugere b on b.id = l.actor_bruger_id left join partners p on p.id = l.actor_partner_id where $where order by l.id desc limit 200", __FILE__ . " linje " . __LINE__);
	$n = 0;
	while ($r = db_fetch_array($q)) { $n++; print "<tr><td class=\"vr-mut vr-num\">".vr_h(substr($r['ts'],0,16))."</td><td>".vr_h($r['brugernavn']).($r['pname'] ? " <span class=\"vr-mut2\">(".vr_h($r['pname']).")</span>" : " <span class=\"vr-mut2\">(Saldi)</span>")."</td><td>".vr_h($r['action'])."</td><td class=\"vr-mut\">".vr_h($r['details'])."</td></tr>"; }
	if (!$n) print "<tr><td colspan=\"4\" class=\"vr-mut\">".vr_t('Ingen hændelser endnu','No events yet')."</td></tr>";
	print "</tbody></table></div></section>\n";
}
vr_close();
?>
</body></html>
