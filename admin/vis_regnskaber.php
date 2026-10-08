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
//                  Recalculation no longer echoes its SQL; it reports a summary instead.

$css="../css/standard.css";
$title="vis regnskaber";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

$saldiregnskab = NULL; # Lukkes / Betalt til / Logintekst are kept out of this page (see Admin Panel)
$lukket=array();

$rediger    = if_isset($_GET, NULL, 'rediger');
$showClosed = if_isset($_GET, NULL, 'showClosed');
$beregn     = if_isset($_GET, NULL, 'beregn');
$sort       = if_isset($_GET, NULL, 'sort');
$sort2      = if_isset($_GET, NULL, 'sort2');
$desc       = if_isset($_GET, NULL, 'desc');

$modulnr    = 102;

if ($db != $sqdb) {
	$alert = findtekst('1905|Hmm du har vist ikke noget at gøre her! Dit IP nummer, brugernavn og regnskab er registreret!', $sprog_id); #20210916
	print "<BODY onLoad=\"javascript:alert('$alert')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
	exit;
}

$vr_saved = NULL; # number of regnskaber written by the last POST
$vr_notes = array(); # messages from the recalculation

if (isset($_POST['submit'])) {
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

$q = db_select("select * from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
list($admin,$oprette,$slette,$tmp)=explode(",",$r['rettigheder'],4);
$adgang_til=explode(",",$tmp);

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
	if ($admin || in_array($r['id'],$adgang_til)) {
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
	if ($admin || in_array($r['id'],$adgang_til)) {
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
				$vr_notes[] = findtekst('Opretter database', $sprog_id)." $db_navn[$x]";
				db_create($db_navn[$x]);
			}
		} else {
			$vr_notes[] = findtekst('Opretter database', $sprog_id)." $db_navn[$x]";
			db_create($db_navn[$x]);
			$sidst[$x]=NULL;
		}
		if (!$sidst[$x]) $sidst[$x]=0;
		$qtxt = "update regnskab set posteret='".(int)$posteret[$x]."',sidst='".(int)$sidst[$x]."' where id='".(int)$id[$x]."'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$vr_recalced++;
	}
	array_unshift($vr_notes, findtekst('Posteringer og seneste aktivitet er genberegnet for', $sprog_id)." $vr_recalced ".findtekst('regnskaber', $sprog_id));
}

// ---------- helpers for the view ----------
function vr_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function vr_num($n) { return number_format((int)$n, 0, ',', '.'); }
function vr_rel($ts) { # relative "sidst aktiv" text; $ts is unix time
	global $sprog_id;
	if (!$ts) return '–';
	$days = (int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $ts))) / 86400);
	if ($days <= 0) return findtekst('i dag', $sprog_id);
	if ($days == 1) return findtekst('i går', $sprog_id);
	if ($days < 7)   return $days.' '.findtekst('dage siden', $sprog_id);
	if ($days < 60)  { $w = (int) round($days/7); return $w.' '.findtekst($w==1 ? 'uge siden' : 'uger siden', $sprog_id); }
	if ($days < 400) { $mo = (int) round($days/30); return $mo.' '.findtekst('mdr. siden', $sprog_id); }
	$yr = (int) floor($days/365); return $yr.' '.findtekst('år siden', $sprog_id);
}
function vr_url($params) { # link to this page with the given state
	$params = array_filter($params, fn($v) => isset($v) && $v !== '' && $v !== false);
	return 'vis_regnskaber.php'.($params ? '?'.http_build_query($params) : '');
}
function vr_sort_link($col, $label, $title='') {
	global $sort, $sort2, $desc, $rediger, $showClosed;
	$on = ($sort == $col);
	$arrow = $on ? ($desc ? ' <span class="vr-ar" aria-hidden="true">&#9660;</span>' : ' <span class="vr-ar" aria-hidden="true">&#9650;</span>') : '';
	$aria = $on ? ($desc ? 'descending' : 'ascending') : 'none';
	$href = vr_url(['sort'=>$col, 'sort2'=>$sort, 'desc'=>$desc, 'rediger'=>$rediger, 'showClosed'=>$showClosed]);
	return "<a href=\"".vr_h($href)."\" aria-sort=\"$aria\"".($title ? " title=\"".vr_h($title)."\"" : "").">".vr_h($label)."$arrow</a>";
}

$vr_count = count($id);
$vr_closedLabel = findtekst('Vis lukkede', $sprog_id);
$vr_toggleClosed = vr_url(['sort'=>$sort, 'sort2'=>$sort2, 'desc'=>$desc, 'rediger'=>$rediger, 'showClosed'=>$showClosed ? NULL : 'on']);
$vr_toggleEdit   = vr_url(['sort'=>$sort, 'sort2'=>$sort2, 'desc'=>$desc, 'showClosed'=>$showClosed, 'rediger'=>$rediger ? NULL : 'on']);
$vr_recalcUrl    = vr_url(['sort'=>$sort, 'sort2'=>$sort2, 'desc'=>$desc, 'showClosed'=>$showClosed, 'beregn'=>1]);

print "<link rel=\"stylesheet\" href=\"../css/vis_regnskaber.css?v=5.1.0\">\n";
print "<div class=\"vr\" style=\"--vr-user:".vr_h($buttonColor).";--vr-user-text:".vr_h($buttonTxtColor).";\">\n";

// ---------- page head ----------
print "<div class=\"vr-head\">\n<div class=\"vr-title\">\n";
print "<a class=\"vr-back\" href=\"../index/admin_menu.php\" accesskey=\"L\">&larr; ".findtekst('30|Tilbage', $sprog_id)."</a>\n";
print "<h1>".findtekst('Regnskaber', $sprog_id)."</h1>\n";
if ($admin) print "<p class=\"vr-lead\">".findtekst('Alle regnskaber på denne installation. Klik på et regnskab for at åbne det, eller tryk Ret for at ændre brugergrænse, posteringsgrænse og lukning.', $sprog_id)."</p>\n";
else print "<p class=\"vr-lead\">".findtekst('Regnskaber du har adgang til. Klik på et regnskab for at åbne det.', $sprog_id)."</p>\n";
print "</div>\n<div class=\"vr-acts\">\n";
if ($showAdminPanel) print "<a class=\"vr-btn vr-quiet\" href=\"admin_panel.php\">Admin Panel</a>\n";
print "<a class=\"vr-tog\" role=\"switch\" aria-checked=\"".($showClosed ? 'true' : 'false')."\" href=\"".vr_h($vr_toggleClosed)."\"><span>$vr_closedLabel</span><span class=\"vr-sw\"></span></a>\n";
if ($admin) {
	if ($rediger) print "<a class=\"vr-btn\" aria-pressed=\"true\" href=\"".vr_h($vr_toggleEdit)."\" id=\"vrLock\">".findtekst('1908|Lås', $sprog_id)." <kbd>R</kbd></a>\n";
	else print "<a class=\"vr-btn\" href=\"".vr_h($vr_toggleEdit)."\" accesskey=\"R\">".findtekst('1206|Ret', $sprog_id)." <kbd>R</kbd></a>\n";
}
if ($admin || $oprette) print "<a class=\"vr-btn vr-primary\" href=\"opret.php\">+ ".findtekst('339|Opret regnskab', $sprog_id)."</a>\n";
print "</div>\n</div>\n";

if ($vr_notes) {
	print "<div class=\"vr-note\" role=\"status\">";
	foreach ($vr_notes as $n) print "<div>".vr_h($n)."</div>";
	print "</div>\n";
}

// ---------- list ----------
print "<section class=\"vr-sect\">\n";
print "<h2>".findtekst('Alle regnskaber', $sprog_id)." <small>$vr_open ".findtekst('åbne', $sprog_id)." &middot; $vr_closed ".findtekst('lukkede', $sprog_id)."</small></h2>\n";
print "<div class=\"vr-card\">\n";
print "<div class=\"vr-bar\"><label class=\"vr-search\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\" aria-hidden=\"true\"><circle cx=\"11\" cy=\"11\" r=\"7\"/><path d=\"M20 20l-3.5-3.5\"/></svg><input type=\"search\" id=\"vrQ\" placeholder=\"".findtekst('Søg på navn, database eller e-mail', $sprog_id)."\" autocomplete=\"off\" aria-label=\"".findtekst('Søg', $sprog_id)."\"></label>";
print "<span class=\"vr-hint\">".($rediger ? findtekst('Ret tallene direkte i listen. Kun ændrede rækker gemmes.', $sprog_id) : findtekst('Klik på et regnskab for at åbne det', $sprog_id))."</span></div>\n";

if ($rediger) print "<form name=\"regnskaber\" id=\"vrForm\" action=\"vis_regnskaber.php\" method=\"post\">\n";

print "<table class=\"vr-t\" id=\"vrTable\">\n<thead><tr>";
print "<th class=\"vr-r vr-id\">".vr_sort_link('id', 'Id')."</th>";
print "<th>".vr_sort_link('regnskab', findtekst('2682|Regnskab', $sprog_id))."</th>";
print "<th class=\"vr-r\">".vr_sort_link('brugerantal', findtekst('777|Brugere', $sprog_id), findtekst('Maks. antal brugere', $sprog_id))."</th>";
print "<th class=\"vr-r\">".vr_sort_link('posteret', findtekst('1910|Posteringer', $sprog_id), findtekst('Posteringer de seneste 12 måneder / posteringsgrænse', $sprog_id))."</th>";
print "<th class=\"vr-r\">".vr_sort_link('sidst', findtekst('Sidst aktiv', $sprog_id))."</th>";
print "<th>".vr_sort_link('booking', findtekst('1116|Booking', $sprog_id))."</th>";
print "<th>e-mail</th>";
print "<th>".vr_sort_link('lukket', findtekst('Status', $sprog_id))."</th>";
print "</tr></thead>\n<tbody>\n";

$vr_openAttr = $vr_warnOpen ? " onclick=\"return vrWarnOpen(this);\"" : "";
for ($x=0;$x<$vr_count;$x++) {
	if (!$sidst[$x]) $sidst[$x]=0;
	$n = $x+1; # form index, as before (db_antal counts from 1)
	$pct = $posteringer[$x] ? min(100, (int) round($posteret[$x] / $posteringer[$x] * 100)) : 0;
	$isClosed = ($lukket[$x] == 'on');
	$q_attr = vr_h(mb_strtolower($regnskab[$x].' '.$db_navn[$x].' '.$email[$x], 'UTF-8'));
	$openHref = "aaben_regnskab.php?db_id=".(int)$id[$x];
	print "<tr".($isClosed ? " class=\"vr-closed\"" : "")." data-q=\"$q_attr\" data-href=\"$openHref\">";
	print "<td class=\"vr-r vr-id\">";
	if ($rediger) {
		print "<input type=\"hidden\" name=\"id[$n]\" value=\"".(int)$id[$x]."\">";
		print "<input type=\"hidden\" name=\"gl_lukket[$n]\" value=\"".vr_h($lukket[$x])."\">";
		print "<input type=\"hidden\" name=\"gl_brugerantal[$n]\" value=\"".(int)$brugerantal[$x]."\">";
		print "<input type=\"hidden\" name=\"gl_posteringer[$n]\" value=\"".(int)$posteringer[$x]."\">";
	}
	print (int)$id[$x]."</td>";
	print "<td class=\"vr-nm\"><a href=\"$openHref\"$vr_openAttr>".vr_h($regnskab[$x])."</a><small>".vr_h($db_navn[$x])."</small></td>";
	if ($rediger) {
		print "<td class=\"vr-r\"><input class=\"vr-e\" type=\"text\" inputmode=\"numeric\" name=\"brugerantal[$n]\" value=\"".vr_num($brugerantal[$x])."\" data-orig=\"".(int)$brugerantal[$x]."\" aria-label=\"".findtekst('777|Brugere', $sprog_id)."\"></td>";
		print "<td class=\"vr-r\"><span class=\"vr-cap\"><span class=\"vr-mut\">".vr_num($posteret[$x])." /</span><input class=\"vr-e\" type=\"text\" inputmode=\"numeric\" name=\"posteringer[$n]\" value=\"".vr_num($posteringer[$x])."\" data-orig=\"".(int)$posteringer[$x]."\" aria-label=\"".findtekst('Posteringsgrænse', $sprog_id)."\"></span></td>";
	} else {
		print "<td class=\"vr-r\">".vr_num($brugerantal[$x])."</td>";
		print "<td class=\"vr-r\"><span class=\"vr-cap\" title=\"".vr_num($posteret[$x])." ".findtekst('af', $sprog_id)." ".vr_num($posteringer[$x])." ($pct %)\"><span>".vr_num($posteret[$x])."</span><span class=\"vr-mut2\">/ ".vr_num($posteringer[$x])."</span><span class=\"vr-b\"><i".($pct >= 90 ? " class=\"vr-hi\"" : "")." style=\"width:$pct%\"></i></span></span></td>";
	}
	print "<td class=\"vr-r vr-mut\"".($sidst[$x] ? " title=\"".date("d-m-Y",$sidst[$x])."\"" : "").">".vr_rel($sidst[$x])."</td>";
	print "<td>".($booking[$x] ? "<span class=\"vr-st\"><span class=\"vr-dot vr-ok\"></span>".findtekst('Ja', $sprog_id)."</span>" : "<span class=\"vr-mut2\">–</span>")."</td>";
	print "<td class=\"vr-mut\">".($email[$x] ? vr_h($email[$x]) : "<span class=\"vr-mut2\">–</span>")."</td>";
	if ($rediger) {
		print "<td><label class=\"vr-tog\"><input type=\"checkbox\" class=\"vr-swin\" name=\"lukket[$n]\" value=\"on\"".($isClosed ? " checked" : "")." data-orig=\"".($isClosed ? '1' : '0')."\"><span class=\"vr-sw\"></span><span class=\"vr-swtx\" data-on=\"".findtekst('387|Lukket', $sprog_id)."\" data-off=\"".findtekst('Åbent', $sprog_id)."\">".($isClosed ? findtekst('387|Lukket', $sprog_id) : findtekst('Åbent', $sprog_id))."</span></label></td>";
	} else {
		print "<td><span class=\"vr-st\"><span class=\"vr-dot ".($isClosed ? 'vr-off' : 'vr-ok')."\"></span>".($isClosed ? findtekst('387|Lukket', $sprog_id) : findtekst('Åbent', $sprog_id))."</span></td>";
	}
	print "</tr>\n";
}
print "</tbody>\n</table>\n";
print "<div class=\"vr-empty\" id=\"vrEmpty\"".($vr_count ? " hidden" : "")."><b>".findtekst('Ingen regnskaber matcher', $sprog_id)."</b><span>".findtekst('Prøv et andet søgeord, eller slå Vis lukkede til.', $sprog_id)."</span></div>\n";
print "<div class=\"vr-foot\"><span><span id=\"vrFoot\" data-one=\"".findtekst('regnskab vist', $sprog_id)."\" data-many=\"".findtekst('regnskaber vist', $sprog_id)."\">$vr_count ".($vr_count == 1 ? findtekst('regnskab vist', $sprog_id) : findtekst('regnskaber vist', $sprog_id))."</span> &middot; ".findtekst('posteringer er talt for de seneste 12 måneder', $sprog_id)."</span><span class=\"vr-grow\"></span>";
if ($admin && !$rediger) print "<button type=\"button\" class=\"vr-btn vr-quiet\" id=\"vrRecalc\">".findtekst('1916|Genberegn posteringer', $sprog_id)."</button>";
print "</div>\n";

if ($rediger) {
	print "<input type=\"hidden\" name=\"db_antal\" value=\"$vr_count\">\n";
	print "<div class=\"vr-save\" id=\"vrSave\"><div><span class=\"vr-msg\"><span class=\"vr-dot vr-warn\"></span><span id=\"vrSaveMsg\" data-one=\"".findtekst('regnskab ændret', $sprog_id)."\" data-many=\"".findtekst('regnskaber ændret', $sprog_id)."\"></span></span><span class=\"vr-grow\"></span><kbd>Ctrl S</kbd><button type=\"button\" class=\"vr-btn vr-quiet\" id=\"vrUndo\">".findtekst('Fortryd', $sprog_id)."</button><input type=\"submit\" class=\"vr-btn vr-primary\" name=\"submit\" value=\"".findtekst('898|Opdatér', $sprog_id)."\"></div></div>\n";
	print "</form>\n";
}
print "</div>\n</section>\n";

// ---------- dialogs and toast ----------
print "<div class=\"vr-scrim\" id=\"vrScrim\"></div>\n";
if ($admin && !$rediger) {
	print "<div class=\"vr-dlg\" id=\"vrDlgRecalc\" role=\"dialog\" aria-modal=\"true\" aria-labelledby=\"vrDlgRecalcT\"><h3 id=\"vrDlgRecalcT\">".findtekst('Genberegn posteringer for alle regnskaber?', $sprog_id)."</h3><p>".findtekst('Saldi tæller posteringer de seneste 12 måneder og finder seneste aktivitet i hvert regnskabs database. Det kan tage et øjeblik, og regnskaber uden database bliver oprettet undervejs.', $sprog_id)."</p><div class=\"vr-bs\"><button type=\"button\" class=\"vr-btn\" data-close>".findtekst('Annullér', $sprog_id)."</button><a class=\"vr-btn vr-primary\" href=\"".vr_h($vr_recalcUrl)."\">".findtekst('Genberegn', $sprog_id)."</a></div></div>\n";
}
if ($vr_warnOpen) {
	print "<div class=\"vr-dlg\" id=\"vrDlgWarn\" role=\"dialog\" aria-modal=\"true\" aria-labelledby=\"vrDlgWarnT\"><h3 id=\"vrDlgWarnT\">Du er ved at åbne et live-regnskab fra master</h3><div class=\"vr-ls\" id=\"vrWarnList\"></div><p id=\"vrWarnText\">Åbner du et live-regnskab fra master, kan du ændre databasestrukturen, så den bliver inkompatibel med live-versionen, eller forhindre at fremtidige opdateringer migrerer korrekt.</p><div class=\"vr-bs\"><button type=\"button\" class=\"vr-btn\" data-close>Annullér</button><a class=\"vr-btn vr-primary\" id=\"vrWarnGo\" href=\"#\">Åbn alligevel</a></div></div>\n";
}
print "<div class=\"vr-toast\" id=\"vrToast\" role=\"status\"></div>\n";
print "</div>\n"; # .vr

// ---------- script ----------
$vr_savedMsg = '';
if ($vr_saved !== NULL) {
	$vr_savedMsg = $vr_saved ? "$vr_saved ".($vr_saved == 1 ? findtekst('regnskab opdateret', $sprog_id) : findtekst('regnskaber opdateret', $sprog_id)) : findtekst('Ingen ændringer', $sprog_id);
}
$vr_js_saved = json_encode($vr_savedMsg);
$vr_js_warn  = $vr_warnOpen ? 'true' : 'false';
$vr_js_fail  = json_encode(findtekst('Kunne ikke hente regnskabets oplysninger. Vil du fortsætte alligevel?', $sprog_id));
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
		if (lock) lock.addEventListener('click', function (e) { if (changedRows() > 0 && !confirm(lock.getAttribute('data-confirm') || 'Du har ugemte ændringer. Forlad uden at gemme?')) e.preventDefault(); });
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
