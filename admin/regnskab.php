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

$css="../css/standard.css";
$title="Regnskab";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("vr_ui.php");
partner_tables_ensure();

if ($db != $sqdb) { print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/logud.php\">"; exit; }

$rid = (int) if_isset($_GET, 0, 'id');
$tab = if_isset($_GET, 'oversigt', 'tab');
if (!in_array($tab, array('oversigt','brugere','funktioner','log'), true)) $tab = 'oversigt';
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

// ---------- data from the customer database (live, on this page only) ----------
$live = array('ok' => false, 'cvr' => '', 'firma' => '', 'users' => array(), 'active' => 0, 'trans12' => null, 'last' => null);
if ($reg['db'] && db_exists($reg['db'])) {
	$cc = @db_connect($sqhost, $squser, $sqpass, $reg['db'], __FILE__ . " linje " . __LINE__);
	if ($cc) {
		$live['ok'] = true;
		if ($r = db_fetch_array(db_select("select firmanavn, cvrnr from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__))) { $live['firma'] = $r['firmanavn']; $live['cvr'] = $r['cvrnr']; }
		if (tbl_exists('brugere')) {
			$q = db_select("select id, brugernavn, rettigheder, email, tlf, twofactor, revisor from brugere order by brugernavn", __FILE__ . " linje " . __LINE__);
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

// ---------- page ----------
$acts = '';
$acts .= "<a class=\"vr-btn vr-primary\" href=\"aaben_regnskab.php?db_id=$rid\">".vr_t('Åbn regnskab', 'Open account')."</a>";
$lead = ($isClosed ? '<span class="vr-st"><span class="vr-dot vr-off"></span>'.findtekst('387|Lukket', $sprog_id).'</span> · ' : '').vr_h($reg['db']).' · '.($links ? vr_t('Bogholder', 'Accountant').': '.implode(', ', array_map(fn($l) => vr_h($l['name']), $links)) : ($home ? vr_t('Partnerens eget regnskab', 'The partner\'s own account') : vr_t('Direkte kunde', 'Direct customer')));
$crumb0 = $op ? array(vr_t('Operatørpanel','Operator panel'), 'vis_regnskaber.php') : array(vr_t('Bogholderpanel','Accountant panel'), 'vis_regnskaber.php');
vr_open(array($crumb0, array(vr_t('Regnskaber','Accounts'), 'vis_regnskaber.php'), $reg['regnskab']), $reg['regnskab'], $lead, $acts, $home ? strtolower(vr_kind($home['kind'])) : '');
vr_note($notes);
$tu = fn($t) => "regnskab.php?id=$rid&tab=$t";
vr_tabs(array(
	'oversigt'   => array(vr_t('Oversigt','Overview'), $tu('oversigt'), null),
	'brugere'    => array(vr_t('Brugere','Users'), $tu('brugere'), count($live['users'])),
	'funktioner' => array(vr_t('Funktioner','Features'), $tu('funktioner'), null),
	'log'        => array(vr_t('Log','Log'), $tu('log'), null),
), $tab);

if ($tab == 'oversigt') {
	print "<div class=\"vr-grid2\">\n<section class=\"vr-sect\"><h2>".vr_t('Regnskab','Account')."</h2><div class=\"vr-card vr-kv\">";
	$kv = array(
		array(vr_t('Firma i regnskabet','Company in the account'), $live['firma'] ? vr_h($live['firma']) : '–'),
		array('CVR', $live['cvr'] ? vr_h($live['cvr']) : '–'),
		array(vr_t('Database','Database'), '<span class="vr-num">'.vr_h($reg['db']).'</span>'),
		array(vr_t('Pakke','Plan'), '<span class="vr-mut2">'.vr_t('kommer med pakkelaget','comes with the entitlement layer').'</span>'),
		array(findtekst('777|Brugere', $sprog_id), $live['ok'] ? $live['active'].' '.vr_t('aktive af','active of').' '.vr_num($reg['brugerantal']) : vr_num($reg['brugerantal'])),
		array(vr_t('Posteringer 12 mdr.','Entries 12 months'), ($live['trans12'] !== null ? vr_num($live['trans12']) : vr_num($reg['posteret'])).' '.vr_t('af','of').' '.vr_num($reg['posteringer']).($live['trans12'] !== null ? ' <span class="vr-mut2">('.vr_t('live','live').')</span>' : ' <span class="vr-mut2">('.vr_t('sidst genberegnet','last recalculated').')</span>')),
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
