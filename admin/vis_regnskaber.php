<?php
@session_start();
$s_id=session_id();

// --- admin/vis_regnskaber.php --- ver 5.1.0 --- 2026.10.08 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20210328 PHR Some cleanup.
// 20210916 LOE Translated some texts
// 20250201 Add hostname to psql
// 20250503 LOE Updated with improved if_isset func.
// 20260507 CL/PHR Blå topline med hvid tekst. Admin Panel link styres af settings.showAdminPanel. Fjernet apostroffer fra Vis/Skjul Luk.
// 20260521 PHR Added email.
// 20260717 CL/NTR Warning (accept to continue) before opening a regnskab, only on master at
//                  ssl3.saldi.dk. Click fetches details from master_warning_info.php and shows
//                  db name, db version, master version and why opening live from master is risky.
// 20260728 NTR Fixed lukket, booking and email being shuffled/missing.
// 20260728 CL/NTR Changed how show/hide closed button is rendered, both so all params are preserved, but also so we don't have duplicate markup. Also made it a http_build_query so that it's easier to read.
//                 Both the mobile and computer version.
// 20260928 CL/NTR regnskab has no booking column, so the Booking column warned on every row and
//                  sorting by it was a fatal error. It now comes from license_features (feature_key
//                  'booking', see license_manager.php) via a left join, and the sort/sort2 params are
//                  whitelisted before they reach ORDER BY.
// 20261008 CL/ASR New page layout aligned with the settings/front-page redesign (one card, search,
//                  status as dot + text, edit mode with save bar, dialogs instead of confirm()).
//                  Same features and same GET/POST parameters as before: sort/sort2/desc, showClosed,
//                  rediger, beregn, submit. The dead $menu=='S' branch and the hard-coded blue
//                  topline are gone; buttons use the user's buttonColor. Output is now escaped.
//                  Recalculation no longer echoes its SQL; it reports a summary instead. Edit mode, saving and
//                  recalculation are admin-only (users with adgang_til get the read-only list).
//                  New texts go through vr_t() (da/en) instead of findtekst('plain text'), which is uncached.
// 20261010 CL/ASR Partner layer (P1): operator tabs Normale kunder / Bogholdere/revisorer / Koncernregnskaber / Alle,
//                  Tilhører column, scope via includes/partnerScope.php (partner users see their portfolio; legacy
//                  adgang_til still works for everyone else). Shared view helpers moved to admin/vr_ui.php.

$css="../css/standard.css";
$title="vis regnskaber";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("vr_ui.php");
partner_tables_ensure();

$saldiregnskab = NULL; # Lukkes / Betalt til / Logintekst are kept out of this page (see Admin Panel)
$lukket=array();

$rediger    = if_isset($_GET, NULL, 'rediger');
$showClosed = if_isset($_GET, NULL, 'showClosed');
$beregn     = if_isset($_GET, NULL, 'beregn');
$sort       = if_isset($_GET, NULL, 'sort');
$sort2      = if_isset($_GET, NULL, 'sort2');
$desc       = if_isset($_GET, NULL, 'desc');
$tab        = if_isset($_GET, 'normale', 'tab');

$modulnr    = 102;

if ($db != $sqdb) {
	$alert = findtekst('1905|Hmm du har vist ikke noget at gøre her! Dit IP nummer, brugernavn og regnskab er registreret!', $sprog_id); #20210916
	print "<BODY onLoad=\"javascript:alert('$alert')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
	exit;
}

$vr_me = partner_current_user();
$admin = $vr_me['is_operator'] ? 'on' : '';
$oprette = $vr_me['oprette'];
$vr_scope = partner_ledgers(); # NULL = all
$vr_partner = $vr_me['partner'];
if (!in_array($tab, array('normale','bogholdere','koncerner','alle'), true) || !$admin) $tab = $admin ? 'normale' : 'alle';
// Editing (rediger / submit) and recalculation are for administrators only. Users who only have
// adgang_til a few regnskaber get the read-only list.
if (!$admin) $rediger = NULL;

$vr_saved = NULL; # number of regnskaber written by the last POST
$vr_notes = array(); # messages from the recalculation

if (isset($_POST['submit']) && $admin) {
	$rediger="on";
	$db_antal = if_isset($_POST, NULL, 'db_antal');
	$id = if_isset($_POST, NULL, 'id');
	$gl_brugerantal = if_isset($_POST, NULL, 'gl_brugerantal');
	$gl_posteringer = if_isset($_POST, NULL, 'gl_posteringer');
	$brugerantal = if_isset($_POST, NULL, 'brugerantal');
	$posteringer = if_isset($_POST, NULL, 'posteringer');
	$gl_lukket = if_isset($_POST, NULL, 'gl_lukket');
	$lukket = if_isset($_POST, array(), 'lukket');
	$gl_lukkes = if_isset($_POST, NULL, 'gl_lukkes');
	$lukkes = if_isset($_POST, NULL, 'lukkes');
	$gl_betalt_til = if_isset($_POST, NULL, 'gl_betalt_til');
	$betalt_til = if_isset($_POST, NULL, 'betalt_til');
	$gl_logintekst = if_isset($_POST, NULL, 'gl_logintekst');
	$logintekst = if_isset($_POST, NULL, 'logintekst');

	$vr_saved = 0;
	for ($x=1;$x<=$db_antal; $x++) {
		if (!isset($id[$x])) continue;
		$brugerantal[$x] = (int) preg_replace('/\D/', '', if_isset($brugerantal, 0, $x));
		$posteringer[$x] = (int) preg_replace('/\D/', '', if_isset($posteringer, 0, $x));
		$lukket[$x]      = (isset($lukket[$x]) && $lukket[$x]) ? 'on' : '';
		$gl_brugerantal[$x] = (int) if_isset($gl_brugerantal, 0, $x);
		$gl_posteringer[$x] = (int) if_isset($gl_posteringer, 0, $x);
		$gl_lukket[$x]      = if_isset($gl_lukket, '', $x);
		if (!isset($lukkes[$x]) || !$lukkes[$x]) $lukkes[$x]="2099-12-31";
		else $lukkes[$x]=usdate($lukkes[$x]);
		if (!isset($betalt_til[$x]) || !$betalt_til[$x]) $betalt_til[$x]="2099-12-31";
		else $betalt_til[$x]=usdate($betalt_til[$x]);
		if (
			$gl_brugerantal[$x]!=$brugerantal[$x] ||
			$gl_posteringer[$x]!=$posteringer[$x] ||
			$gl_lukket[$x]!=$lukket[$x] ||
			($saldiregnskab && (
				if_isset($gl_lukkes, '', $x)!=$lukkes[$x] ||
				if_isset($gl_betalt_til, '', $x)!=$betalt_til[$x] ||
				if_isset($gl_logintekst, '', $x)!=if_isset($logintekst, '', $x)
			))
		){
			$vr_id = (int) $id[$x];
			if ($saldiregnskab) $qtxt="update regnskab set brugerantal='$brugerantal[$x]',posteringer='$posteringer[$x]',lukket='$lukket[$x]',lukkes='$lukkes[$x]',betalt_til='$betalt_til[$x]',logintekst='".db_escape_string($logintekst[$x])."' where id = '$vr_id'";
			else $qtxt="update regnskab set brugerantal='$brugerantal[$x]',posteringer='$posteringer[$x]',lukket='$lukket[$x]' where id = '$vr_id'";
			if ($vr_id) {
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$vr_saved++;
			}
		}
	}
} else { # 2020090 can be removed
	$qtxt="update regnskab set lukket='' where lukket is NULL";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

$r_ap = db_fetch_array(db_select("select var_value from settings where var_name='useAdminPanel'", __FILE__ . " linje " . __LINE__));
$showAdminPanel = ($r_ap && $r_ap['var_value']) ? true : false;

// Warn before opening a regnskab, but only on the master install at ssl3.saldi.dk.
// The click is intercepted by vrWarnOpen() (printed below), which fetches the regnskab's
// details on demand from master_warning_info.php, so no per-database work happens on load.
$vr_serverName  = if_isset($_SERVER, '', 'SERVER_NAME');
$vr_pathParts   = explode('/', trim(if_isset($_SERVER, '', 'PHP_SELF'), '/'));
$vr_firstFolder = if_isset($vr_pathParts, '', 0);
$vr_warnOpen    = ($vr_serverName == 'ssl3.saldi.dk' && $vr_firstFolder == 'master');


$id=array(); $regnskab=array(); $db_navn=array();

$sortable = ['id', 'regnskab', 'brugerantal', 'posteringer', 'posteret', 'sidst', 'booking', 'lukket', 'lukkes', 'betalt_til', 'logintekst'];
if (!in_array($sort, $sortable, true)) $sort='regnskab';
if (!in_array($sort2, $sortable, true)) $sort2='id';
if ($sort==$sort2) {
	if (!$desc) {
		$order="order by $sort desc";
		$desc='on';
	} else {
		$order="order by $sort";
		$desc='';
	}
} else {
	$order="order by $sort,$sort2";
	$desc='';
}

$x=0;
// The Booking column mirrors license_features (feature_key 'booking', edited in license_manager.php);
// regnskab itself has no booking column. A regnskab without a row counts as licensed, matching
// is_feature_licensed() in includes/license_func.php. The alias makes "order by booking" work.
$qtxt = "SELECT 1 FROM information_schema.tables WHERE table_name = 'license_features'";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "select regnskab.*, case when lf.id is null or (lf.enabled and (lf.expires_at is null or lf.expires_at >= current_date)) then 'on' else '' end as booking";
	$qtxt.= " from regnskab left join license_features lf on lf.regnskab_id = regnskab.id and lf.feature_key = 'booking'";
} else {
	$qtxt = "select regnskab.*, 'on' as booking from regnskab";
}
$qtxt.= " where regnskab.db != '$sqdb'";
if (!$showClosed) $qtxt.= " and lukket != 'on'";
$qtxt.= " $order";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($vr_scope === null || in_array((int)$r['id'],$vr_scope)) {
		$id[$x]=$r['id'];
		$regnskab[$x]=$r['regnskab'];
		$db_navn[$x]=$r['db'];
		$posteringer[$x]=$r['posteringer']*1;
		$posteret[$x]=$r['posteret']*1;
		$brugerantal[$x]=$r['brugerantal']*1;
		$sidst[$x]=$r['sidst'];
		$email[$x]=$r['email'];
		$booking[$x]=($r['booking'] == 'on') ? 'on' : '';
		$lukket[$x]=($r['lukket'] == 'on') ? 'on' : '';
		$x++;
	}
}
// Counts for the heading, independent of showClosed.
$vr_open = $vr_closed = 0;
$q=db_select("select lukket, id from regnskab where db != '$sqdb'",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($vr_scope === null || in_array((int)$r['id'],$vr_scope)) {
		if ($r['lukket'] == 'on') $vr_closed++; else $vr_open++;
	}
}

if ($beregn && $admin) {
	$fp=fopen("../temp/$sqdb/tmp.sh","w");
	fwrite($fp,"#!/bin/sh\n");
	fwrite($fp,"export PGPASSWORD='$sqpass'\n");
	fwrite($fp,"psql --host=$sqhost --username=$squser -l > ../temp/dbliste.txt\n");
	fclose($fp);
	system("/bin/sh '../temp/$sqdb/tmp.sh'");
	unlink ("../temp/$sqdb/tmp.sh");
	$dbs=file("../temp/dbliste.txt");
	unlink("../temp/dbliste.txt");
	$dbliste=array();
	$l=0;
	for ($i=0;$i<count($dbs);$i++) {
		if (strpos($dbs[$i],"|") && strpos($dbs[$i],"_")) {
			list($tmp1,$tmp2)=explode("|",$dbs[$i],2);
			if (strpos($tmp1,"_")) {
				$dbliste[$l]=trim($tmp1);
				$l++;
			}
		}
	}
	$y=date("Y")-1;
	$m=date("m");
	$d=date("d");
	$dd=$y."-".$m."-".$d;
	$vr_recalced=0;
	for ($x=0;$x<count($id);$x++) {
		if (in_array($db_navn[$x],$dbliste)) {
			$qtxt = "SELECT datname FROM pg_database WHERE datname = '$db_navn[$x]'";
			if (db_fetch_array($q = db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_connect ("$sqhost", "$squser", "$sqpass", "$db_navn[$x]", __FILE__ . " linje " . __LINE__);
				$qtxt="select * from pg_tables where tablename='transaktioner'";
				if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$r=db_fetch_array(db_select("select count(id) as transantal from transaktioner where logdate >= '$dd'",__FILE__ . " linje " . __LINE__));
					$posteret[$x]=$r['transantal']*1;
					if ($r=db_fetch_array(db_select("select max(logdate) as logdate from transaktioner",__FILE__ . " linje " . __LINE__))) {
						$sidst[$x]=strtotime($r['logdate']);
					}
					if ($r=db_fetch_array(db_select("select * from batch_salg order by id desc limit 1",__FILE__ . " linje " . __LINE__))) {
						if (isset($r['modtime']) &&  $r['modtime']) {
							if (strtotime($r['modtime']) > $sidst[$x]) $sidst[$x]=strtotime($r['modtime']);
						}
					}
				} else $sidst[$x]=NULL;
				include("../includes/connect.php");
			} else {
				$vr_notes[] = vr_t('Opretter database', 'Creating database')." $db_navn[$x]";
				db_create($db_navn[$x]);
			}
		} else {
			$vr_notes[] = vr_t('Opretter database', 'Creating database')." $db_navn[$x]";
			db_create($db_navn[$x]);
			$sidst[$x]=NULL;
		}
		if (!$sidst[$x]) $sidst[$x]=0;
		$qtxt = "update regnskab set posteret='".(int)$posteret[$x]."',sidst='".(int)$sidst[$x]."' where id='".(int)$id[$x]."'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$vr_recalced++;
	}
	array_unshift($vr_notes, vr_t('Posteringer og seneste aktivitet er genberegnet for', 'Entries and latest activity recalculated for')." $vr_recalced ".vr_t('regnskaber', 'accounts'));
}

function vr_sort_th($col, $label, $title='', $cls='') {
	global $sort, $sort2, $desc, $rediger, $showClosed, $tab;
	$on = ($sort == $col);
	$arrow = $on ? ($desc ? ' <span class="vr-ar" aria-hidden="true">&#9660;</span>' : ' <span class="vr-ar" aria-hidden="true">&#9650;</span>') : '';
	$aria = $on ? ($desc ? 'descending' : 'ascending') : 'none';
	$th = "<th aria-sort=\"$aria\"".($cls ? " class=\"$cls\"" : "").">";
	$href = vr_url('vis_regnskaber.php', ['tab'=>$tab, 'sort'=>$col, 'sort2'=>$sort, 'desc'=>$desc, 'rediger'=>$rediger, 'showClosed'=>$showClosed]);
	return $th."<a href=\"".vr_h($href)."\"".($title ? " title=\"".vr_h($title)."\"" : "").">".vr_h($label)."$arrow</a></th>";
}

$vr_count = count($id);
$vr_closedLabel = vr_t('Vis lukkede', 'Show closed');
$vr_toggleClosed = vr_url('vis_regnskaber.php', ['tab'=>$tab, 'sort'=>$sort, 'sort2'=>$sort2, 'desc'=>$desc, 'rediger'=>$rediger, 'showClosed'=>$showClosed ? NULL : 'on']);
$vr_toggleEdit   = vr_url('vis_regnskaber.php', ['tab'=>$tab, 'sort'=>$sort, 'sort2'=>$sort2, 'desc'=>$desc, 'showClosed'=>$showClosed, 'rediger'=>$rediger ? NULL : 'on']);
$vr_recalcUrl    = vr_url('vis_regnskaber.php', ['tab'=>$tab, 'sort'=>$sort, 'sort2'=>$sort2, 'desc'=>$desc, 'showClosed'=>$showClosed, 'beregn'=>1]);

$vr_links = partner_links_by_ledger();
$vr_partners = partner_all();
$vr_homes = array(); foreach ($vr_partners as $pp) if ($pp['home_regnskab_id']) $vr_homes[(int)$pp['home_regnskab_id']] = $pp;
$vr_acts = '';
if ($showAdminPanel && $admin) $vr_acts .= "<a class=\"vr-btn vr-quiet\" href=\"admin_panel.php\">Admin Panel</a>\n";
$vr_acts .= "<a class=\"vr-tog\" role=\"switch\" aria-checked=\"".($showClosed ? 'true' : 'false')."\" href=\"".vr_h($vr_toggleClosed)."\"><span>$vr_closedLabel</span><span class=\"vr-sw\"></span></a>\n";
if ($admin) {
	if ($rediger) $vr_acts .= "<a class=\"vr-btn\" aria-pressed=\"true\" href=\"".vr_h($vr_toggleEdit)."\" id=\"vrLock\" data-confirm=\"".vr_t('Du har ugemte ændringer. Forlad uden at gemme?', 'You have unsaved changes. Leave without saving?')."\">".findtekst('1908|Lås', $sprog_id)." <kbd>R</kbd></a>\n";
	else $vr_acts .= "<a class=\"vr-btn\" href=\"".vr_h($vr_toggleEdit)."\" accesskey=\"R\">".findtekst('1206|Ret', $sprog_id)." <kbd>R</kbd></a>\n";
}
if ($admin) $vr_acts = "<a class=\"vr-btn vr-quiet\" href=\"partnere.php\">".vr_t('Bogholdere og koncerner', 'Accountants and groups')."</a>\n".$vr_acts;
if ($admin || $oprette == 'on') $vr_acts .= "<a class=\"vr-btn vr-primary\" href=\"opret.php\">+ ".findtekst('339|Opret regnskab', $sprog_id)."</a>\n"; # partner-created ledgers come in P4
if ($admin) {
	$vr_lead = vr_t('Alle regnskaber på installationen. Fanerne deler dem op efter, hvem de tilhører. Klik på et regnskab for at åbne det, eller på kortet for detaljer, bogholdere og brugere.', 'All accounts on this installation. The tabs split them by owner. Click an account to open it, or its card for details, accountants and users.');
	$vr_crumbs = array(array(vr_t('Operatørpanel', 'Operator panel'), 'vis_regnskaber.php'), vr_t('Regnskaber', 'Accounts'));
} else {
	$vr_lead = ($vr_partner ? vr_h($vr_partner['name']).' · ' : '').vr_t('Regnskaber du har adgang til. Klik på et regnskab for at åbne det.', 'Accounts you have access to. Click an account to open it.');
	$vr_crumbs = array(array(vr_t('Bogholderpanel', 'Accountant panel'), 'vis_regnskaber.php'), vr_t('Mine regnskaber', 'My accounts'));
}
vr_open($vr_crumbs, $admin ? vr_t('Regnskaber', 'Accounts') : vr_t('Mine regnskaber', 'My accounts'), $vr_lead, $vr_acts);
vr_note($vr_notes);

// ---------- tabs (operator) ----------
$vr_isDirect = function($rid) use ($vr_links, $vr_homes) { return empty($vr_links[(int)$rid]) && empty($vr_homes[(int)$rid]); };
if ($admin) {
	$nDirect = 0; for ($x=0;$x<$vr_count;$x++) if ($vr_isDirect($id[$x])) $nDirect++;
	$nB = count(array_filter($vr_partners, fn($pp) => $pp['kind'] != 'koncern'));
	$nK = count(array_filter($vr_partners, fn($pp) => $pp['kind'] == 'koncern'));
	$tabUrl = fn($t) => vr_url('vis_regnskaber.php', ['tab'=>$t, 'sort'=>$sort, 'sort2'=>$sort2, 'desc'=>$desc, 'showClosed'=>$showClosed, 'rediger'=>$rediger]);
	vr_tabs(array(
		'normale'    => array(vr_t('Normale kunder', 'Direct customers'), $tabUrl('normale'), $nDirect),
		'bogholdere' => array(vr_t('Bogholdere/revisorer', 'Accountants'), $tabUrl('bogholdere'), $nB),
		'koncerner'  => array(vr_t('Koncernregnskaber', 'Group accounts'), $tabUrl('koncerner'), $nK),
		'alle'       => array(vr_t('Alle', 'All'), $tabUrl('alle'), $vr_count),
	), $tab);
}
// ---------- list ----------
print "<section class=\"vr-sect\">\n";
print "<h2>".vr_t('Regnskaber', 'Accounts')." <small>$vr_open ".vr_t('åbne', 'open')." &middot; $vr_closed ".vr_t('lukkede', 'closed')."</small></h2>\n";
print "<div class=\"vr-card\">\n";
print "<div class=\"vr-bar\"><label class=\"vr-search\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\" aria-hidden=\"true\"><circle cx=\"11\" cy=\"11\" r=\"7\"/><path d=\"M20 20l-3.5-3.5\"/></svg><input type=\"search\" id=\"vrQ\" placeholder=\"".vr_t('Søg på navn, database eller e-mail', 'Search by name, database or e-mail')."\" autocomplete=\"off\" aria-label=\"".findtekst('913|Søg', $sprog_id)."\"></label>";
print "<span class=\"vr-hint\">".($rediger ? vr_t('Ret tallene direkte i listen. Kun ændrede rækker gemmes.', 'Edit the numbers directly in the list. Only changed rows are saved.') : vr_t('Klik på et regnskab for at åbne det', 'Click an account to open it'))."</span></div>\n";

if ($rediger) print "<form name=\"regnskaber\" id=\"vrForm\" action=\"vis_regnskaber.php\" method=\"post\">\n";

print "<table class=\"vr-t\" id=\"vrTable\">\n<thead><tr>";
print vr_sort_th('id', 'Id', '', 'vr-r vr-id');
print vr_sort_th('regnskab', findtekst('2682|Regnskab', $sprog_id));
if ($admin) print "<th>".vr_t('Tilhører', 'Belongs to')."</th>";
print vr_sort_th('brugerantal', findtekst('777|Brugere', $sprog_id), vr_t('Maks. antal brugere', 'Maximum number of users'), 'vr-r');
print vr_sort_th('posteret', findtekst('1910|Posteringer', $sprog_id), vr_t('Posteringer de seneste 12 måneder / posteringsgrænse', 'Entries in the last 12 months / entry limit'), 'vr-r');
print vr_sort_th('sidst', vr_t('Sidst aktiv', 'Last active'), '', 'vr-r');
print vr_sort_th('booking', findtekst('1116|Booking', $sprog_id));
print "<th>e-mail</th>";
print vr_sort_th('lukket', findtekst('494|Status', $sprog_id));
print "</tr></thead>\n<tbody>\n";

$vr_openAttr = $vr_warnOpen ? " onclick=\"return vrWarnOpen(this);\"" : "";
$vr_row = function($x, $indent = false) use (&$id, &$regnskab, &$db_navn, &$posteringer, &$posteret, &$brugerantal, &$sidst, &$email, &$booking, &$lukket, $rediger, $vr_openAttr, $admin, $vr_links, $vr_homes, $sprog_id) {
	if (!$sidst[$x]) $sidst[$x]=0;
	$n = $x+1; # form index, as before (db_antal counts from 1)
	$pct = $posteringer[$x] ? min(100, (int) round($posteret[$x] / $posteringer[$x] * 100)) : 0;
	$isClosed = ($lukket[$x] == 'on');
	$q_attr = vr_h(mb_strtolower($regnskab[$x].' '.$db_navn[$x].' '.$email[$x], vr_cs()));
	$openHref = "aaben_regnskab.php?db_id=".(int)$id[$x];
	print "<tr class=\"".($isClosed ? "vr-closed" : "").($indent ? " vr-in" : "")."\" data-q=\"$q_attr\" data-href=\"$openHref\">";
	print "<td class=\"vr-r vr-id\">";
	if ($rediger) {
		print "<input type=\"hidden\" name=\"id[$n]\" value=\"".(int)$id[$x]."\">";
		print "<input type=\"hidden\" name=\"gl_lukket[$n]\" value=\"".vr_h($lukket[$x])."\">";
		print "<input type=\"hidden\" name=\"gl_brugerantal[$n]\" value=\"".(int)$brugerantal[$x]."\">";
		print "<input type=\"hidden\" name=\"gl_posteringer[$n]\" value=\"".(int)$posteringer[$x]."\">";
	}
	print (int)$id[$x]."</td>";
	$home = isset($vr_homes[(int)$id[$x]]) ? $vr_homes[(int)$id[$x]] : null;
	print "<td class=\"vr-nm\"><a href=\"$openHref\"$vr_openAttr>".vr_h($regnskab[$x])."</a>".($home ? " <span class=\"vr-pill\">".vr_h(strtolower(vr_kind($home['kind'])))."</span>" : "")." <a class=\"vr-card-link\" href=\"regnskab.php?id=".(int)$id[$x]."\" title=\"".vr_t('Regnskabskort', 'Account card')."\">".vr_t('Kort', 'Card')."</a><small>".vr_h($db_navn[$x])."</small></td>";
	if ($admin) {
		$lk = isset($vr_links[(int)$id[$x]]) ? $vr_links[(int)$id[$x]] : array();
		if ($lk) print "<td class=\"vr-mut\"><a href=\"partnere.php?id=".(int)$lk[0]['id']."\">".vr_h($lk[0]['name'])."</a>".(count($lk) > 1 ? "<span class=\"vr-plus\">+".(count($lk)-1)."</span>" : "")."</td>";
		elseif ($home) print "<td class=\"vr-mut2\">".vr_t('eget regnskab', 'own account')."</td>";
		else print "<td class=\"vr-mut2\">".vr_t('direkte kunde', 'direct customer')."</td>";
	}
	if ($rediger) {
		print "<td class=\"vr-r\"><input class=\"vr-e\" type=\"text\" inputmode=\"numeric\" name=\"brugerantal[$n]\" value=\"".vr_num($brugerantal[$x])."\" data-orig=\"".(int)$brugerantal[$x]."\" aria-label=\"".findtekst('777|Brugere', $sprog_id)."\"></td>";
		print "<td class=\"vr-r\"><span class=\"vr-cap\"><span class=\"vr-mut\">".vr_num($posteret[$x])." /</span><input class=\"vr-e\" type=\"text\" inputmode=\"numeric\" name=\"posteringer[$n]\" value=\"".vr_num($posteringer[$x])."\" data-orig=\"".(int)$posteringer[$x]."\" aria-label=\"".vr_t('Posteringsgrænse', 'Entry limit')."\"></span></td>";
	} else {
		print "<td class=\"vr-r\">".vr_num($brugerantal[$x])."</td>";
		print "<td class=\"vr-r\"><span class=\"vr-cap\" title=\"".vr_num($posteret[$x])." ".findtekst('5320|af', $sprog_id)." ".vr_num($posteringer[$x])." ($pct %)\"><span>".vr_num($posteret[$x])."</span><span class=\"vr-mut2\">/ ".vr_num($posteringer[$x])."</span><span class=\"vr-b\"><i".($pct >= 90 ? " class=\"vr-hi\"" : "")." style=\"width:$pct%\"></i></span></span></td>";
	}
	print "<td class=\"vr-r vr-mut\"".($sidst[$x] ? " title=\"".date("d-m-Y",$sidst[$x])."\"" : "").">".vr_rel($sidst[$x])."</td>";
	print "<td>".($booking[$x] ? "<span class=\"vr-st\"><span class=\"vr-dot vr-ok\"></span>".findtekst('83|Ja', $sprog_id)."</span>" : "<span class=\"vr-mut2\">–</span>")."</td>";
	print "<td class=\"vr-mut\">".($email[$x] ? vr_h($email[$x]) : "<span class=\"vr-mut2\">–</span>")."</td>";
	if ($rediger) {
		print "<td><label class=\"vr-tog\"><input type=\"checkbox\" class=\"vr-swin\" name=\"lukket[$n]\" value=\"on\"".($isClosed ? " checked" : "")." data-orig=\"".($isClosed ? '1' : '0')."\"><span class=\"vr-sw\"></span><span class=\"vr-swtx\" data-on=\"".findtekst('387|Lukket', $sprog_id)."\" data-off=\"".vr_t('Åbent', 'Open')."\">".($isClosed ? findtekst('387|Lukket', $sprog_id) : vr_t('Åbent', 'Open'))."</span></label></td>";
	} else {
		print "<td><span class=\"vr-st\"><span class=\"vr-dot ".($isClosed ? 'vr-off' : 'vr-ok')."\"></span>".($isClosed ? findtekst('387|Lukket', $sprog_id) : vr_t('Åbent', 'Open'))."</span></td>";
	}
	print "</tr>\n";
};
$vr_byId = array(); for ($x=0;$x<$vr_count;$x++) $vr_byId[(int)$id[$x]] = $x;
$vr_shown = 0;
if ($admin && ($tab == 'bogholdere' || $tab == 'koncerner')) {
	$colspan = 9;
	foreach ($vr_partners as $pp) {
		if (($tab == 'koncerner') != ($pp['kind'] == 'koncern')) continue;
		$members = array();
		if ($pp['home_regnskab_id'] && isset($vr_byId[(int)$pp['home_regnskab_id']])) $members[] = $vr_byId[(int)$pp['home_regnskab_id']];
		$nCust = 0;
		foreach ($vr_byId as $rid => $x) { if (isset($vr_links[$rid])) foreach ($vr_links[$rid] as $l) if ((int)$l['id'] == (int)$pp['id']) { $members[] = $x; $nCust++; } }
		$members = array_unique($members);
		$nEmp = db_fetch_array(db_select("select count(*) as n from partner_users where partner_id = '".(int)$pp['id']."'", __FILE__ . " linje " . __LINE__));
		print "<tr class=\"vr-grp\"><td colspan=\"$colspan\"><a href=\"partnere.php?id=".(int)$pp['id']."\">".vr_h($pp['name'])."</a><small>$nCust ".($pp['kind']=='koncern' ? vr_t('selskaber','companies') : vr_t('kunder','customers'))." &middot; ".(int)$nEmp['n']." ".vr_t('medarbejdere','employees')."</small></td></tr>\n";
		foreach ($members as $x) { $vr_row($x, true); $vr_shown++; }
		if (!$members) print "<tr><td colspan=\"$colspan\" class=\"vr-mut2 vr-in\">".vr_t('Ingen regnskaber tilknyttet endnu', 'No accounts linked yet')."</td></tr>\n";
	}
} else {
	for ($x=0;$x<$vr_count;$x++) {
		if ($admin && $tab == 'normale' && !$vr_isDirect($id[$x])) continue;
		$vr_row($x); $vr_shown++;
	}
}
print "</tbody>\n</table>\n";
print "<div class=\"vr-empty\" id=\"vrEmpty\"".($vr_shown ? " hidden" : "")."><b>".vr_t('Ingen regnskaber matcher', 'No accounts match')."</b><span>".vr_t('Prøv et andet søgeord, eller slå Vis lukkede til.', 'Try another search term, or switch on Show closed.')."</span></div>\n";
print "<div class=\"vr-foot\"><span><span id=\"vrFoot\" data-one=\"".vr_t('regnskab vist', 'account shown')."\" data-many=\"".vr_t('regnskaber vist', 'accounts shown')."\">$vr_shown ".($vr_shown == 1 ? vr_t('regnskab vist', 'account shown') : vr_t('regnskaber vist', 'accounts shown'))."</span> &middot; ".vr_t('posteringer er talt for de seneste 12 måneder', 'entries are counted for the last 12 months')."</span><span class=\"vr-grow\"></span>";
if ($admin && !$rediger) print "<button type=\"button\" class=\"vr-btn vr-quiet\" id=\"vrRecalc\">".findtekst('1916|Genberegn posteringer', $sprog_id)."</button>";
print "</div>\n";

if ($rediger) {
	print "<input type=\"hidden\" name=\"db_antal\" value=\"$vr_count\">\n";
	print "<div class=\"vr-save\" id=\"vrSave\"><div><span class=\"vr-msg\"><span class=\"vr-dot vr-warn\"></span><span id=\"vrSaveMsg\" data-one=\"".vr_t('regnskab ændret', 'account changed')."\" data-many=\"".vr_t('regnskaber ændret', 'accounts changed')."\"></span></span><span class=\"vr-grow\"></span><kbd>Ctrl S</kbd><button type=\"button\" class=\"vr-btn vr-quiet\" id=\"vrUndo\">".findtekst('159|Fortryd', $sprog_id)."</button><input type=\"submit\" class=\"vr-btn vr-primary\" name=\"submit\" value=\"".findtekst('898|Opdatér', $sprog_id)."\"></div></div>\n";
	print "</form>\n";
}
print "</div>\n</section>\n";

// ---------- dialogs and toast ----------
print "<div class=\"vr-scrim\" id=\"vrScrim\"></div>\n";
if ($admin && !$rediger) {
	print "<div class=\"vr-dlg\" id=\"vrDlgRecalc\" role=\"dialog\" aria-modal=\"true\" aria-labelledby=\"vrDlgRecalcT\"><h3 id=\"vrDlgRecalcT\">".vr_t('Genberegn posteringer for alle regnskaber?', 'Recalculate entries for all accounts?')."</h3><p>".vr_t('Saldi tæller posteringer de seneste 12 måneder og finder seneste aktivitet i hvert regnskabs database. Det kan tage et øjeblik, og regnskaber uden database bliver oprettet undervejs.', 'Saldi counts entries in the last 12 months and finds the latest activity in each account database. It may take a moment, and accounts without a database are created on the way.')."</p><div class=\"vr-bs\"><button type=\"button\" class=\"vr-btn\" data-close>".findtekst('5|Annullér', $sprog_id)."</button><a class=\"vr-btn vr-primary\" href=\"".vr_h($vr_recalcUrl)."\">".vr_t('Genberegn', 'Recalculate')."</a></div></div>\n";
}
if ($vr_warnOpen) {
	print "<div class=\"vr-dlg\" id=\"vrDlgWarn\" role=\"dialog\" aria-modal=\"true\" aria-labelledby=\"vrDlgWarnT\"><h3 id=\"vrDlgWarnT\">Du er ved at åbne et live-regnskab fra master</h3><div class=\"vr-ls\" id=\"vrWarnList\"></div><p id=\"vrWarnText\">Åbner du et live-regnskab fra master, kan du ændre databasestrukturen, så den bliver inkompatibel med live-versionen, eller forhindre at fremtidige opdateringer migrerer korrekt.</p><div class=\"vr-bs\"><button type=\"button\" class=\"vr-btn\" data-close>Annullér</button><a class=\"vr-btn vr-primary\" id=\"vrWarnGo\" href=\"#\">Åbn alligevel</a></div></div>\n";
}
print "<div class=\"vr-toast\" id=\"vrToast\" role=\"status\"></div>\n";
vr_close();

// ---------- script ----------
$vr_savedMsg = '';
if ($vr_saved !== NULL) {
	$vr_savedMsg = $vr_saved ? "$vr_saved ".($vr_saved == 1 ? vr_t('regnskab opdateret', 'account updated') : vr_t('regnskaber opdateret', 'accounts updated')) : vr_t('Ingen ændringer', 'No changes');
}
$vr_js_saved = json_encode($vr_savedMsg);
$vr_js_warn  = $vr_warnOpen ? 'true' : 'false';
$vr_js_fail  = json_encode(vr_t('Kunne ikke hente regnskabets oplysninger. Vil du fortsætte alligevel?', 'Could not fetch the account details. Continue anyway?'));
print <<<JS
<script>
(function () {
	var vr = document.querySelector('.vr');
	var q = document.getElementById('vrQ');
	var rows = Array.prototype.slice.call(document.querySelectorAll('#vrTable tbody tr'));
	var empty = document.getElementById('vrEmpty');
	var foot = document.getElementById('vrFoot');
	var form = document.getElementById('vrForm');
	var saveBar = document.getElementById('vrSave');
	var scrim = document.getElementById('vrScrim');
	var toastEl = document.getElementById('vrToast');
	var toastT;

	function toast(t) {
		toastEl.textContent = t; toastEl.classList.add('vr-on');
		clearTimeout(toastT); toastT = setTimeout(function () { toastEl.classList.remove('vr-on'); }, 2800);
	}
	function openDlg(el) { scrim.classList.add('vr-on'); el.classList.add('vr-open'); var b = el.querySelector('[data-close]'); if (b) b.focus(); }
	function closeDlg() { scrim.classList.remove('vr-on'); Array.prototype.forEach.call(document.querySelectorAll('.vr-dlg.vr-open'), function (d) { d.classList.remove('vr-open'); }); }
	scrim.addEventListener('click', closeDlg);
	Array.prototype.forEach.call(document.querySelectorAll('.vr-dlg [data-close]'), function (b) { b.addEventListener('click', closeDlg); });

	/* search: filters the rendered rows on name, database and e-mail */
	function filter() {
		var s = (q.value || '').trim().toLowerCase(), n = 0;
		rows.forEach(function (tr) { var hit = !s || tr.getAttribute('data-q').indexOf(s) !== -1; tr.style.display = hit ? '' : 'none'; if (hit) n++; });
		empty.hidden = n > 0 || rows.length === 0;
		if (foot) foot.textContent = n + ' ' + (n === 1 ? foot.getAttribute('data-one') : foot.getAttribute('data-many'));
	}
	q.addEventListener('input', filter);

	/* row click opens the regnskab (not when clicking an input or link) */
	rows.forEach(function (tr) {
		tr.addEventListener('click', function (e) {
			if (form || e.target.closest('a,input,label,button')) return;
			var a = tr.querySelector('td.vr-nm a'); if (a) a.click();
		});
	});

	/* edit mode: save bar shows how many rows differ from the stored values */
	if (form) {
		var fields = Array.prototype.slice.call(form.querySelectorAll('input[data-orig]'));
		function changedRows() {
			var ids = {};
			fields.forEach(function (f) {
				var cur = f.type === 'checkbox' ? (f.checked ? '1' : '0') : String(parseInt(String(f.value).replace(/\\D/g, ''), 10) || 0);
				var chg = cur !== f.getAttribute('data-orig');
				f.classList.toggle('vr-chg', chg);
				if (chg) ids[f.closest('tr').getAttribute('data-href')] = 1;
			});
			return Object.keys(ids).length;
		}
		function update() {
			var n = changedRows(), m = document.getElementById('vrSaveMsg');
			m.textContent = n + ' ' + (n === 1 ? m.getAttribute('data-one') : m.getAttribute('data-many'));
			saveBar.classList.toggle('vr-on', n > 0);
			fields.forEach(function (f) { if (f.type === 'checkbox') { var t = f.parentNode.querySelector('.vr-swtx'); if (t) t.textContent = f.checked ? t.getAttribute('data-on') : t.getAttribute('data-off'); } });
		}
		form.addEventListener('input', update);
		form.addEventListener('change', update);
		document.getElementById('vrUndo').addEventListener('click', function () { form.reset(); update(); });
		document.addEventListener('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's' && saveBar.classList.contains('vr-on')) { e.preventDefault(); form.querySelector('input[name=submit]').click(); }
		});
		var lock = document.getElementById('vrLock');
		if (lock) lock.addEventListener('click', function (e) { if (changedRows() > 0 && !confirm(lock.getAttribute('data-confirm'))) e.preventDefault(); });
		update();
		var saved = $vr_js_saved; if (saved) toast(saved);
	}

	/* recalculation: ask first */
	var rc = document.getElementById('vrRecalc');
	if (rc) rc.addEventListener('click', function () { openDlg(document.getElementById('vrDlgRecalc')); });

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { closeDlg(); return; }
		if (e.target.matches && e.target.matches('input,select,textarea')) return;
		if (e.key === '/' || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k')) { e.preventDefault(); q.focus(); }
	});

	/* master@ssl3 only: intercept the click, fetch this regnskab's details and warn in a dialog */
	if ($vr_js_warn) {
		window.vrWarnOpen = function (link) {
			var id = new URLSearchParams(link.search).get('db_id');
			var dlg = document.getElementById('vrDlgWarn'), list = document.getElementById('vrWarnList'), go = document.getElementById('vrWarnGo');
			var name = link.textContent;
			function row(k, v) { var d = document.createElement('div'), a = document.createElement('span'), b = document.createElement('span'); a.textContent = k; b.textContent = v; d.appendChild(a); d.appendChild(b); return d; }
			go.href = link.href;
			list.innerHTML = ''; list.appendChild(row('Regnskab', name));
			fetch('master_warning_info.php?db_id=' + encodeURIComponent(id), {credentials: 'same-origin'})
				.then(function (r) { return r.json(); })
				.then(function (d) {
					if (d.error) { alert(d.error); return; }
					list.appendChild(row('Database', d.db)); list.appendChild(row('Databasens version', d.dbver)); list.appendChild(row('Masters version', d.master));
					openDlg(dlg);
				})
				.catch(function () { if (confirm($vr_js_fail)) window.location.href = link.href; });
			return false;
		};
	}
})();
</script>
JS;
?>
</body></html>
