<?php
@session_start();
$s_id=session_id();
// --- index/admin_menu.php --- lap 5.1.0 --- 2026-10-10 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20180131
// 20210916 LOE translated some texts
// 20261010 CL/ASR Rendered in the new admin-layer shell (sidebar + topbar, admin/vr_ui.php). Same entries, same
//                  rights and gating as before ($admin/$oprette/$slette, $revisorregnskab/$forhandlerregnskab,
//                  settings.useAdminPanel, ROTARY). Partner users land on their own menu.

$css="../css/standard.css";
$title="Administration";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/version.php");
include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("../admin/vr_ui.php");
partner_tables_ensure();

$modulnr=100;

if ($db != $sqdb) {
	print "<BODY onLoad=\"javascript:alert('Hmm du har vist ikke noget at g&oslash;re her! Dit IP nummer, brugernavn og regnskab er registreret!')\">\n";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">\n";
	exit;
}

$q = db_select("select * from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
if ($bruger_id=$r['id']) {
	$rettigheder=$r['rettigheder'];
	if (strstr($rettigheder,",")==false) {
		$rettigheder="on,on,on,*";
		db_modify("update brugere set rettigheder='$rettigheder' where id='$bruger_id'",__FILE__ . " linje " . __LINE__);
	}
}
$u = partner_current_user();
$desc = array(
	'vis_regnskaber.php' => vr_t('Alle regnskaber, grupperet efter bogholder og koncern','All accounts, grouped by accountant and group'),
	'partnere.php'       => vr_t('Bogholdere, revisorer og koncerner med adgang til flere regnskaber','Accountants and groups with access to several accounts'),
	'opret.php'          => vr_t('Nyt regnskab med standardkontoplan','New account with the standard chart of accounts'),
	'slet_regnskab.php'  => vr_t('Slet eller nulstil et regnskab','Delete or reset an account'),
	'admin_brugere.php'  => vr_t('Brugere i administrationslaget','Users in the administration layer'),
	'admin_settings.php' => vr_t('Indstillinger for installationen','Installation settings'),
	'admin_panel.php'    => vr_t('Saldis eget administrationspanel: kunder, abonnementer, brugere, betalinger og indstillinger','Saldi\'s own administration panel: customers, subscriptions, users, payments and settings'),
	'bankfordeling.php'  => vr_t('Kortbetalinger','Card payments'),
);
if ($u['partner']) { $desc['vis_regnskaber.php'] = vr_t('Regnskaber du har adgang til','Accounts you have access to'); $desc['partnere.php'] = vr_t('Medarbejdere og kunder i dit firma','Employees and customers in your firm'); }

vr_open(array(vr_t('Overblik','Overview')), vr_t('Overblik','Overview'), $u['is_operator'] ? vr_t('Du er logget ind som operatør. Vælg et område i menuen, eller gå direkte til regnskaberne.','You are logged in as operator. Pick an area in the menu, or go straight to the accounts.') : ($u['partner'] ? vr_h($u['partner']['name']).' · '.vr_t('Vælg et område i menuen.','Pick an area in the menu.') : vr_t('Vælg et område i menuen.','Pick an area in the menu.')), "<a class=\"vr-btn vr-primary\" href=\"../admin/vis_regnskaber.php\">".($u['partner'] && !$u['is_operator'] ? vr_t('Mine regnskaber','My accounts') : vr_t('Regnskaber','Accounts'))."</a>");
print "<section class=\"vr-sect vr-menu\"><h2>".vr_t('Områder','Areas')."</h2><div class=\"vr-card\">";
foreach (vr_menu() as $it) {
	$k = basename($it[1]);
	if ($it[3]) print "<a class=\"vr-frow\" href=\"".vr_h($it[1])."\">".vr_icon($it[2])."<div><b>".vr_h($it[0])."</b><small>".(isset($desc[$k]) ? $desc[$k] : '')."</small></div><span class=\"vr-chev\">&rsaquo;</span></a>";
	else print "<div class=\"vr-frow\" style=\"opacity:.5;cursor:default\">".vr_icon($it[2])."<div><b>".vr_h($it[0])."</b><small>".vr_t('Kræver rettighed','Requires permission')."</small></div><span></span></div>";
}
print "</div></section>\n";
vr_close();
print "</body></html>";
?>
