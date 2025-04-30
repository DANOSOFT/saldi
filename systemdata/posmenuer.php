<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/posmenuer.php --- ver 4.0.8 -- 2023-09-28 --
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
// Copyright (c) 2009-2023 Saldi.dk ApS
// --------------------------------------------------------------------------
// 20131017 Ku max ha' 10 menuer.
// 20141111 Tilføjet knapdesign på menu 0: Tastatur og tilføjet radius på knapper.
// 20150107 Menu blev slettet når side blev åbnet #20150107
// 20150925 Kolonner og rækker kan nu flyttes. Søg flyt_col & flyt_row
// 20151023	Tilføjet knap Enter+Menu. Efter knaptekst skrives +x, hvor x er den menu der skal kaldes.
// 20151129	Tilføjet knap Konant på beløb & Betalingskort på beløb.
// 20160128 Tilføjet systemknap Stamkunder. Se funktion stamkunder i ordrefunc.php
// 20160131 Tilføjet systemknap Kontoudtog & Udskriv sidste.
// 20160218 Kontrol for strenglængde for butcolor. Søg 20160218
// 20160307 Man kunne ikke lave tastaturknapper med '-'Søg 20160307
// 20170207 Tilføjet knap sæt
// 20170323 Tilføjet afdeling på menuer. Søg $afd
// 20181029 CA  Tilføjet gavekort og tilgodebevis med nummerering. Søg 20181029
// 20190107 PHR Tilføjet systemknapknap til totalrabat Søg totalrabat
// 20190218 LN Adding array value to the button txt because we have different language
// 20190305 LN Added new button: Z-Rapport
// 20190313	LN Add more language txt to the buttons
// 20190430	LN Add new button: Copy
// 20190508	LN Delegate systemButtons to systemButtons.php
// 20211124 CA  Link til import og eksport af POS menuer
// 20220209 PHR Renamed proforma to udskriv
// 20230209 PHR Enhanced 'Kopi fra' (copy from') 
// 20230405 PHR Added price option.
// 20230928 PHR added "'"art='POSBUT' and " to fetch.

@session_start();
$s_id = session_id();
$title = "POS menuer";
$modulnr = 1;
$css = "../css/pos.css";

$diffkto = NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/posmenufunc_includes/buttonFunc.php");

$title = findtekst(1940, $sprog_id);
$buttonTextArr = setAccordinglyLanguage();

#$systemknap=array('Afslut','Bordvalg','Brugervalg','Del bord','Enter','Find bon','Flyt bord','Kasseoptælling','Kassevalg','Køkkenprint','Luk','Skuffe','Udskriv','Forfra','Ekspedient','Ryd','Afslut','Pris','Rabat','Tilbage','Ny kunde','Korrektion','Kortterminal','Send til køkken','Kør bord');

$menuvalg = if_isset($_POST['menuvalg']);
$menu_id = if_isset($_POST['menu_id'], 0);
$beskrivelse = if_isset($_POST['beskrivelse']);
$localPrice = if_isset($_POST['localPrice'], 0);
$plads = if_isset($_POST['plads']);
$cols = if_isset($_POST['cols']) * 1;
$rows = if_isset($_POST['rows']) * 1;
$height = if_isset($_POST['height']) * 1;
$radius = if_isset($_POST['radius']) * 1;
$fontsize = if_isset($_POST['fontsize']) * 1;
$width = if_isset($_POST['width']) * 1;
$menutype = if_isset($_POST['menutype']);
$begin = if_isset($_POST['begin']);
$end = if_isset($_POST['end']);
$projekt = if_isset($_POST['projekt']);
$afd = if_isset($_POST['afd']);
$buttxt = trim($_POST['buttxt']);
$butcolor = if_isset($_POST['butcolor']);
$butexttcolor = if_isset($_POST['butexttcolor']);
$butfunc = if_isset($_POST['butfunc']);
$butvnr = if_isset($_POST['butvnr']);
$ret_col = if_isset($_POST['ret_col']);
$ret_row = if_isset($_POST['ret_row']);
$byt = if_isset($_POST['byt']);
$flyt_col = if_isset($_POST['flyt_col']);
$flyt_row = if_isset($_POST['flyt_row']);
$kopier_menu = if_isset($_POST['kopier_menu']);
$posButtonId = if_isset($_POST['posButtonId']);
$copyFromMenu = if_isset($_POST['copyFromMenu']);
$copyFromCol = if_isset($_POST['copyFromCol']);
$copyFromRow = if_isset($_POST['copyFromRow']);
$folgevarer = if_isset($_POST['folgevarer']);

setcookie("pos_menu_location", "$_GET[menu_id]-$_GET[ret_col]-$_GET[ret_row]", time() + (86400 * 30), "/");

if ($menu_id) {
	if (!$beskrivelse)
		$beskrivelse = "?";
	if (!$cols)
		$cols = 1;
	if (!$rows)
		$rows = 1;
}
if (isset($_GET['menu_id'])) {
	$menu_id = if_isset($_GET['menu_id']);
	$ret_col = if_isset($_GET['ret_col']);
	$ret_row = if_isset($_GET['ret_row']);
	$farvekode = if_isset($_GET['farvekode']);
	if ($farvekode && $menu_id && $ret_col && $ret_row) {
		db_modify("update pos_buttons set color='$farvekode' where menu_id='$menu_id' and col='$ret_col' and row='$ret_row'", __FILE__ . " linje " . __LINE__);
	}
	if ($flyt_col) {
		for ($x = 1; $x <= count($flyt_col); $x++) {
			if (ord($flyt_col[$x]) - 96 != $x && ord($flyt_col[$x]) - 96 <= count($flyt_col)) {
				$ny = ord($flyt_col[$x]) - 96;
				db_modify("update pos_buttons set col='0' where menu_id='$menu_id' and col='$x'", __FILE__ . " linje " . __LINE__);
				db_modify("update pos_buttons set col='$x' where menu_id='$menu_id' and col='$ny'", __FILE__ . " linje " . __LINE__);
				db_modify("update pos_buttons set col='$ny' where menu_id='$menu_id' and col='0'", __FILE__ . " linje " . __LINE__);
			}
		}
	}
	if ($flyt_row) {
		for ($x = 1; $x <= count($flyt_row); $x++) {
			if ($flyt_row[$x] != $x && $flyt_row[$x] <= count($flyt_row)) {
				$qtxt = "update pos_buttons set row='0' where menu_id='$menu_id' and row='$x'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$qtxt = "update pos_buttons set row='$x' where menu_id='$menu_id' and row='$flyt_row[$x]'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$qtxt = "update pos_buttons set row='$flyt_row[$x]' where menu_id='$menu_id' and row='0'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}
	}
}

if ($butfunc == 1 && $butvnr) {
	$price = usdecimal($localPrice, 2);

	$qtxt = "update varer set salgspris = $price*0.8 ";
	if ($folgevarer && $folgevarer != "-")
		$qtxt .= ", folgevare = $folgevarer ";
	else if ($folgevarer && $folgevarer == "-")
		$qtxt .= ", folgevare = 0 ";
	$qtxt .= "where varenr = '$butvnr'";

	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$beskrivelse = db_escape_string($beskrivelse);
if ($menutype && ($begin || $end)) {
	$begin = tid($begin);
	$end = tid($end);
	if ($end == '00:00')
		$end = '24:00';
}
if ($menuvalg == 'ny') {
	$x = 0;
	$m_id = NULL;
	$kodenr = array();
	$qtxt = "select kodenr from grupper where art='POSBUT' order by kodenr"; #20230928
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$kodenr[$x] = $r['kodenr'];
		$x++;
	}
	for ($x = 0; $x < count($kodenr); $x++) {
		if (!$m_id && $x && !in_array($x, $kodenr))
			$m_id = $x;
	}
	if (!$m_id)
		$m_id = $x;
	if (($m_id || $m_id == '0') && $beskrivelse) {
		$qtxt = "insert into grupper(beskrivelse,art,kode,kodenr,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12) values ('POS menu knapper','POSBUT','$plads','$m_id','$beskrivelse','$cols','$rows','$height','$width','$menutype','$begin','$end','$projekt','$fontsize','$radius','$afd')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
} else {
	($menuvalg) ? list($m_id, $tmp) = explode(':', $menuvalg) : $m_id = 0;
	$menu_id = (int) $menu_id;
	if ($menu_id && $beskrivelse == '-' && $butfunc != '5') { #20160307
		$qtxt = "delete from pos_buttons where menu_id=$menu_id";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$qtxt = "delete from grupper where art='POSBUT' and kodenr='$menu_id'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	} elseif ($menu_id == $m_id && $cols && $rows) {
		if ($kopier_menu) {
			db_modify("delete from pos_buttons where menu_id='$menu_id'", __FILE__ . " linje " . __LINE__);
			$q = db_select("select * from pos_buttons where menu_id='$kopier_menu'", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$qtxt = "insert into pos_buttons (menu_id,row,col,beskrivelse,color,funktion,vare_id,colspan,rowspan) values ";
				$qtxt .= "('$menu_id','$r[row]','$r[col]','" . db_escape_string($r['beskrivelse']) . "','$r[color]','$r[funktion]','$r[vare_id]','$r[colspan]','$r[rowspan]')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
			$r = db_fetch_array(db_select("select * from grupper where art='POSBUT' and kodenr='$kopier_menu'", __FILE__ . " linje " . __LINE__));
			$cols = $r['box2'];
			$rows = $r['box3'];
			$height = $r['box4'];
			$width = $r['box5'];
			$projekt = $r['box9'];
			$fontsize = $r['box10'];
			$radius = $r['box11'];
			$afd = $r['box12'];
		}
		$qtxt = "update grupper set
		kode='$plads',box1='$beskrivelse',box2='$cols',box3='$rows',box4='$height',box5='$width',
		box6='$menutype',box7='$begin',box8='$end',box9='$projekt',box10='$fontsize',box11='$radius',
		box12='$afd' where art='POSBUT' and kodenr='$m_id'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);

	} elseif (is_numeric($copyFromMenu) && $copyFromCol && $copyFromCol != '*' && is_numeric($copyFromRow)) {
		$copyFromCol = ord($copyFromCol) - 96;
		$qtxt = "select * from pos_buttons where menu_id='$copyFromMenu' and col = '$copyFromCol' and row = '$copyFromRow'";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			if ($posButtonId) {
				$qtxt = "update pos_buttons set beskrivelse = '" . db_escape_string($r['beskrivelse']) . "', ";
				$qtxt .= "vare_id = '$r[vare_id]', funktion = '$r[funktion]', color = '$r[color]' ";
				$qtxt .= "where id = '$posButtonId'";
			} else {
				$qtxt = "insert into pos_buttons (menu_id,row,col,beskrivelse,color,funktion,vare_id,colspan,rowspan) values ";
				$qtxt .= "('$menu_id','$ret_row','$ret_col','" . db_escape_string($r['beskrivelse']) . "','$r[color]','$r[funktion]',";
				$qtxt .= "'$r[vare_id]','$r[colspan]','$r[rowspan]')";
			}
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	} elseif (is_numeric($copyFromMenu) && $copyFromCol == '*' && $copyFromRow) {
		#		$copyFromCol=ord($copyFromCol)-96;
		$qtxt = "select * from pos_buttons where menu_id='$copyFromMenu' and row = '$copyFromRow' order by col";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$qtxt = "select id from pos_buttons where menu_id='$menu_id' and row = '$ret_row' and col = '$r[col]'";
			if ($r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$qtxt = "update pos_buttons set beskrivelse = '" . db_escape_string($r['beskrivelse']) . "', ";
				$qtxt .= "vare_id = '$r[vare_id]', funktion = '$r[funktion]', color = '$r[color]' ";
				$qtxt .= "where id = '$r2[id]'";
			} else {
				$qtxt = "insert into pos_buttons (menu_id,row,col,beskrivelse,color,funktion,vare_id,colspan,rowspan) values ";
				$qtxt .= "('$menu_id','$ret_row','$r[col]','" . db_escape_string($r['beskrivelse']) . "','$r[color]','$r[funktion]',";
				$qtxt .= "'$r[vare_id]','$r[colspan]','$r[rowspan]')";
			}
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	} elseif ($menu_id && $ret_col && $ret_row && $butfunc) { #20150107
		$buttxt = db_escape_string($buttxt);
		$buttxt = str_replace("<br>", "\n", $buttxt);
		if ($butfunc == 1) {
			$qtxt = "select * from varer where lower(varenr)='" . strtolower($butvnr) . "' and lukket !='on'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			if (!$buttxt)
				$buttxt = db_escape_string($r['beskrivelse']);
			$butvnr = (int) $r['id'];
		} elseif ($butfunc == 3) {
			$r = db_fetch_array(db_select("select id from adresser where kontonr='$butvnr' and lukket !='on'", __FILE__ . " linje " . __LINE__));
			$butvnr = (int) $r['id'];
		} elseif ($butfunc == 6) {
			$butvnr = (int) $butvnr;
		} elseif ($butfunc == 8) {
			$butvnr = (int) $butvnr;
			if ($butvnr) {
				$qtxt = "select box1 from grupper where art = 'VK' and kodenr='$butvnr'";
				$r = db_fetch_array($qtxt, __FILE__ . " linje " . __LINE__);
				$buttxt = $r['box1'];
			}
		} else
			$butvnr = (int) $butvnr;
		$qtxt = "select id from pos_buttons where menu_id='$menu_id' and row='$ret_row' and col='$ret_col'";
		if (strlen($butcolor) > 6)
			$butcolor = substr($butcolor, -6); #20160218
		if (strlen($butexttcolor) > 6)
			$butexttcolor = substr($butexttcolor, -6); #20160218
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$but_id = $r['id'];
			if ($buttxt == '-' && $butfunc != '5')
				$qtxt = "delete from pos_buttons where id='$but_id'"; # 20160307
			else
				$qtxt = "update pos_buttons set beskrivelse='$buttxt',color='$butcolor',fontcolor='$butexttcolor',funktion='$butfunc',vare_id='$butvnr' where id='$r[id]'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} elseif ($buttxt || $butcolor || $butfunc || $butvnr) {
			$qtxt = "insert into pos_buttons (menu_id,row,col,beskrivelse,color,fontcolor,funktion,vare_id,colspan,rowspan) values ('$menu_id','$ret_row','$ret_col','$buttxt','$butcolor','$butexttcolor','$butfunc','$butvnr','1','1')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		if ($byt) {
			$ny_col = ord(substr($byt, 0, 1)) - 96;
			$ny_row = substr($byt, 1);
			if (is_numeric($ny_col) && is_numeric($ny_row)) {
				if (!$bud_id) {
					$qtxt = "select id from pos_buttons where menu_id='$menu_id' and row='$ret_row' and col='$ret_col'";
					$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
					$but_id = $r['id'];
				}
				$qtxt = "select id from pos_buttons where menu_id='$menu_id' and row='$ny_row' and col='$ny_col'";
				if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "update pos_buttons set col='$ret_col',row='$ret_row' where id='$r[id]'";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				db_modify("update pos_buttons set col='$ny_col',row='$ny_row' where id='$but_id'", __FILE__ . " linje " . __LINE__);
				$ret_col = $ny_col;
				$ret_row = $ny_row;
			}
		}
	}
}
$x = 0;
$afd_nr = array();
$afd_beskrivelse = array();
$qtxt = "select * from grupper where art='AFD'";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$afd_nr[$x] = $r['kodenr'];
	$afd_beskrivelse[$x] = $r['beskrivelse'];
	$x++;
}

$x = 0;
if ($menuvalg) {
	list($tmp, $beskrivelse) = explode(":", $menuvalg);
	if (is_numeric($tmp) && $menu_id != $tmp)
		$menu_id = $tmp;
}
$menu_id *= 1;
$qtxt = "select * from grupper where art='POSBUT' and kodenr='$menu_id'";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
$menu_id = $r['kodenr'];
if (!$menu_id) {
	$plads = 'H';
	$beskrivelse = findtekst('2275|Tastatur', $sprog_id);
	$menutype = '';
	$cols = '3';
	$rows = '3';
} else {
	$plads = $r['kode'];
	$beskrivelse = str_replace("\n", "<br>", $r['box1']);
	$menutype = $r['box6'];
	$cols = $r['box2'];
	$rows = $r['box3'];
}
$height = $r['box4'];
$width = $r['box5'];
$begin = $r['box7'];
$end = $r['box8'];
$projekt = $r['box9'];
$fontsize = $r['box10'];
$radius = $r['box11'];
$afd = $r['box12'];
if ($menutype == 'on' || $menutype == 'H') {
	$menutype = 'H';
	if (!$begin)
		$begin = "00:00";
	if (!$end)
		$end = "24:00";
}
if (!$cols)
	$cols = 10;
if (!$rows)
	$rows = 1;
if (!$height)
	$height = 40;
if (!$width)
	$width = 80;
if (!$radius)
	$radius = 1;
if (!$fontsize)
	$fontsize = 20;

print "<table border = '1'><tbody><tr><td>\n";
print "<form name='posmenuer' action='posmenuer.php' method='post'>\n";
// Vindue 1 - >
print "<table border='0'><tbody>\n";
print "<tr><td><a href=diverse.php?sektion=pos_valg>" . findtekst('2172|Luk', $sprog_id) . "</a></td></tr>\n";
if (($menu_id) && $ret_col && $ret_row) {
	$qtxt = "select * from pos_buttons where menu_id='$menu_id' and row='$ret_row' and col='$ret_col'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$posButtonId = $r['id'];
	$a = str_replace("\n", "<br>", $r['beskrivelse']);
	$b = $r['color'];
	$b_font = $r['fontcolor'];
	$c = (int) $r['vare_id'];
	$d = (int) $r['funktion'];

	$qtxt = "select * from grupper where art='POSBUT' and kodenr='$menu_id'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$menu_navn = $r[0];

	print "<tr><td colspan='2' align='center'>" . findtekst('2276|Menu', $sprog_id) . " $menu_id - $menu_navn</td></tr>\n";
	print "<tr>
    <td colspan='1' align='center'><a href='posmenuer.php?menu_id=$menu_id'>" . findtekst('2287|Skift menu', $sprog_id) . "</a></td>
    <td colspan='1' align='center' id='choose-item' onclick='open_popup()'>" . findtekst('586|Vælg', $sprog_id) . "</td>
  	</tr>\n";
	print "<tr>\n";
	print "<td style='width:140px;height:" . $height . "px;text-align:left'>\n
	<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='" . findtekst('1163|Tekst', $sprog_id) . "'><br>\n";
	if ($d == 1)
		print "<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='" . findtekst('915|Pris', $sprog_id) . "'><br>\n";
	print "<a href='../includes/farvekort.php?menu_id=$menu_id&ret_col=$ret_col&ret_row=$ret_row'>\n
	<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='" . findtekst('2288|Farvekode', $sprog_id) . "'></a><br>\n
	<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='" . findtekst('2289|Tekst farvekode', $sprog_id) . "'></a><br>\n
	<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='" . findtekst('2290|Vare-/menunr', $sprog_id) . ".'><br>\n

  <div style='position: relative;'>
    <INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='" . findtekst('2291|Funktion', $sprog_id) . "'>\n
    <div class='tooltip' id='funk-help'>
      <svg 
        fill='none' 
        height='16' 
        stroke='currentColor' 
        stroke-linecap='round' 
        stroke-linejoin='round' 
        stroke-width='2' 
        viewBox='0 0 24 24' 
        width='16' 
        xmlns='http://www.w3.org/2000/svg'
      >
        <circle cx='12' cy='12' r='10'/>
        <line x1='12' x2='12' y1='16' y2='12'/>
        <line x1='12' x2='12.01' y1='8' y2='8'/>
      </svg>
      <div class='tooltiptext' style='width: 500px; text-align: left; padding-left: 20px; padding-right: 20px; transform: translateY(-44%)'>
        <b>Varenr:</b> Åbner muligheden for at indtaste et varenummer som tilføjes til den aktive ordre, du kan også bruge knappen \"Vælg Varenr\", så åbnes en menu hvor der kan vælges en vare.
        <br>
        <div style='height: 4px;'></div>
        <b>Menu:</b> Det samme som varenr. Her kan også bruges funktionen \"Vælg Menunr\", her åbnes en menu ligene varenr 
        <br>
        <div style='height: 4px;'></div>
        <b>Systemknap:</b> Tryk OK, så kommer der en dorp down med muligheder i linjen over.
        <br>
        <div style='height: 4px;'></div>
        <b>Betalingsknap:</b> Tryk OK, så kommer en liste over mulige betalingsknapper i feltet Tekst.
        <br>
        <div style='height: 4px;'></div>
        <b>Valutaknap:</b> Tryk OK, så kommer der en liste over oprettede valutaer.
        <br>
        <div style='height: 4px;'></div>
        <b>Specialfunktion</b> er til hvis man har fået lavet en special funktion, spøg hvis du vil vide mere.
      </div>
    </div>
  </div>

	<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='" . findtekst('2292|Byt med', $sprog_id) . ":'>\n
	<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='" . findtekst('2293|Kopier fra', $sprog_id) . ":'>\n
	<input type='hidden' name='menu_id' value='$menu_id'>\n
	<input type='hidden' name='ret_col' value='$ret_col'>\n
	<input type='hidden' name='ret_row' value='$ret_row'>\n
	</td>\n";

	if ($d == 1 && $c) {
		$r = db_fetch_array(db_select("select * from varer where id='$c' and lukket !='on'", __FILE__ . " linje " . __LINE__));
		$c = $r['varenr'];
		$price = $r['salgspris'] * 1.25;
		$localPrice = dkdecimal($price, 2);
		$folgevarer = $r['folgevare'];
		#		$grp   = $r['gruppe'];
	}
	if ($d == 3 && $c) {
		$r = db_fetch_array(db_select("select kontonr from adresser where id='$c' and lukket !='on'", __FILE__ . " linje " . __LINE__));
		$c = $r['kontonr'];
	}
	if (!$c)
		$c = '';
	print "<td style='width:100px;height:100px;text-align:center'>";
	if ($d == 7) {
		$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'", __FILE__ . " linje " . __LINE__));
		$kortantal = $r['box4'] * 1;
		$korttyper = explode(chr(9), $r['box5']);

		print "<SELECT CLASS='inputbox' style='width:100px;' name='buttxt'>\n";
		if ($a == 'Kontant')
			print "<OPTION>Kontant</OPTION>\n";
		elseif ($a == 'Kontant på beløb')
			print "<OPTION>Kontant på beløb</OPTION>\n";
		elseif ($a == 'Konto')
			print "<OPTION>Konto</OPTION>\n";
		elseif ($a == 'Betalingskort')
			print "<OPTION>Betalingskort</OPTION>\n";
		if ($a == 'Betalingskort på beløb')
			print "<OPTION>Betalingskort på beløb</OPTION>\n";
		for ($x = 0; $x < $kortantal; $x++) {
			if ($a == $korttyper[$x])
				print "<OPTION>$korttyper[$x]</OPTION>\n";
			elseif ($a == $korttyper[$x] . ' på beløb')
				print "<OPTION>$korttyper[$x] på beløb</OPTION>\n";
		}
		$country = db_fetch_array(db_select("select * from adresser where art = 'S'", __FILE__ . " linje " . __LINE__))['land'];
		if ($a == 'Gem som tilbud')
			print "<OPTION>Gem som tilbud</OPTION>\n";
		if ($country == "Switzerland") {
			alert("country is swizz");
			if ($a == 'Cash') {
				print "<OPTION>Cash</OPTION>\n";
			} elseif ($a == 'Cash on amount') {
				print "<OPTION>Cash on amount</OPTION>\n";
			} else {
				for ($x = 0; $x < $kortantal; $x++) {
					if ($a == $korttyper[$x])
						print "<OPTION>$korttyper[$x]</OPTION>\n";
					elseif ($a == $korttyper[$x] . ' on amount')
						print "<OPTION>$korttyper[$x] on amount</OPTION>\n";
				}
			}
		} else {
			if ($a != 'Kontant')
				print "<OPTION>Kontant</OPTION>\n";
			if ($a != 'Kontant på beløb')
				print "<OPTION>Kontant på beløb</OPTION>\n";
		}
		if ($a != 'Konto')
			print "<OPTION>Konto</OPTION>\n";
		if ($a != 'Betalingskort')
			print "<OPTION>Betalingskort</OPTION>\n";
		if ($a != 'Betalingskort på beløb')
			print "<OPTION>Betalingskort på beløb</OPTION>\n";
		for ($x = 0; $x < $kortantal; $x++) {
			if ($buttxt != $korttyper[$x])
				print "<OPTION>$korttyper[$x]</OPTION>\n";
			if ($country == 'Switzerland') { #LN 20190212 Add new payment method for Swiss
				if ($buttxt != $korttyper[$x] . ' på beløb')
					print "<OPTION>$korttyper[$x] on amount</OPTION>\n";
			} else {
				if ($buttxt != $korttyper[$x] . ' på beløb')
					print "<OPTION>$korttyper[$x] på beløb</OPTION>\n";
			}
		}
		if ($a != 'Gem som tilbud')
			print "<OPTION>Gem som tilbud</OPTION>\n";
		print "</SELECT>\n";



	} else
		print "<INPUT CLASS='inputbox' TYPE='text' style='width:100px;text-align:center' name='buttxt' value='$a'><br>\n";
	if ($d == '1') {
		print "<INPUT CLASS='inputbox' TYPE='text' style='width:100px;text-align:center' ";
		print "name='localPrice' value='$localPrice'><br>\n";

		# Til fra valg select
		print "<select CLASS='inputbox' style='width:100px;text-align:center' name='folgevarer'>\n";
		print "<option value='-'>-- Ingen valgt --</option>\n";
		$q = db_select("select * from grupper where art = 'POSBUT' AND box6='' order by box1", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$tmp1 = $r['kodenr'];
			$tmp2 = $r['box1'];
			if ("-" . $tmp1 == $folgevarer && $tmp1) {
				print "<option value='-$tmp1' selected>$tmp2 ($r[kodenr])</option>\n";
			} else {
				print "<option value='-$tmp1'>$tmp2 ($r[kodenr])</option>\n";
			}
		}
		print "</select><br>\n";
	}
	if ($b == "") {
		$b = "eeeef0";
	}
	if ($b_font == "" || $b_font == null) {
		$b_font = "111";
	}
	print "
  <div style='display: flex;   align-items: center; justify-content: space-between;'>
    <INPUT CLASS='inputbox' TYPE='color' style='width:77px;text-align:center;cursor: pointer;height:27px' name='butcolor' value='#$b'
	onchange=\"document.getElementsByName('posmenuer')[0].submit();\">
    <div id='color-reset' onclick='document.getElementsByName(\"butcolor\")[0].value=\"#eeeef0\"'>
      <?xml version='1.0' ?>
      <svg 
        viewBox='0 0 512 512' 
        xmlns='http://www.w3.org/2000/svg'
      >
      <path d='M64,256H34A222,222,0,0,1,430,118.15V85h30V190H355V160h67.27A192.21,192.21,0,0,0,256,64C150.13,64,64,150.13,64,256Zm384,0c0,105.87-86.13,192-192,192A192.21,192.21,0,0,1,89.73,352H157V322H52V427H82V393.85A222,222,0,0,0,478,256Z'/>
      </svg>
    </div>  
  </div>
  \n";
	print "
  <div style='display: flex;  align-items: center; justify-content: space-between;'
	onchange=\"document.getElementsByName('posmenuer')[0].submit();\">
    <INPUT CLASS='inputbox' TYPE='color' style='width:77px;text-align:center;cursor: pointer;height:27px' name='butexttcolor' value='#$b_font'>
    <div id='color-reset' onclick='document.getElementsByName(\"butexttcolor\")[0].value=\"#111111\"'>
      <?xml version='1.0' ?>
      <svg 
        viewBox='0 0 512 512' 
        xmlns='http://www.w3.org/2000/svg'
      >
      <path d='M64,256H34A222,222,0,0,1,430,118.15V85h30V190H355V160h67.27A192.21,192.21,0,0,0,256,64C150.13,64,64,150.13,64,256Zm384,0c0,105.87-86.13,192-192,192A192.21,192.21,0,0,1,89.73,352H157V322H52V427H82V393.85A222,222,0,0,0,478,256Z'/>
      </svg>
    </div>  
  </div>
  \n";

	include("posmenuer_includes/systemButtons.php");

	print "<SELECT CLASS='inputbox' style='width:100px;' name='butfunc' id='func-select' onchange=\"document.getElementsByName('posmenuer')[0].submit();\">\n";
	if ($d == 1)
		print "<OPTION value='1'>Varenr</OPTION>\n";
	if ($d == 2)
		print "<OPTION value='2'>Menu</OPTION>\n";
	if ($d == 3)
		print "<OPTION value='3'>Kundenr</OPTION>\n";
	if ($d == 4)
		print "<OPTION value='4'>Specialfunktion</OPTION>\n";
	if ($d == 5)
		print "<OPTION value='5'>Tastatur</OPTION>\n";
	if ($d == 6)
		print "<OPTION value='6'>Systemknap</OPTION>\n";
	if ($d == 7)
		print "<OPTION value='7'>Betalingsknap</OPTION>\n";
	if ($d == 8)
		print "<OPTION value='8'>Valutaknap</OPTION>\n";
	if ($d != 1)
		print "<OPTION value='1'>Varenr</OPTION>\n";
	if ($d != 2)
		print "<OPTION value='2'>Menu</OPTION>\n";
	if ($d != 3)
		print "<OPTION value='3'>Kundenr</OPTION>\n";
	if ($d != 4)
		print "<OPTION value='4'>Specialfunktion</OPTION>\n";
	if ($d != 5)
		print "<OPTION value='5'>Tastatur</OPTION>\n";
	if ($d != 6)
		print "<OPTION value='6'>Systemknap</OPTION>\n";
	if ($d != 7)
		print "<OPTION value='7'>Betalingsknap</OPTION>\n";
	if ($d != 8)
		print "<OPTION value='8'>Valutaknap</OPTION>\n";
	print "</SELECT><br>\n";
	print "<input style='width:100px;' type='text' name='byt'>\n";
	print "<input style='width:33px;' type='text' name='copyFromMenu' placeholder = 'Menu'>";
	print "<input style='width:33px;' type='text' name='copyFromCol' placeholder = 'Col'>";
	print "<input style='width:33px;' type='text' name='copyFromRow' placeholder = 'Row'></td></tr>\n";
	print "<input type='hidden' name='posButtonId' value='$posButtonId'>\n";

	$extra_buttons = "
    <input type=button value='Anuller' name='Anuller' onclick='window.location.href = \"?menu_id=$menu_id\";'> 
    <input type=button value='Slet Knap' name='SletKnap' onclick='delete_btn();'>
  ";
} else {
	print "<tr><td>" . findtekst('2276|Menu', $sprog_id) . " (ID)</td><td><select CLASS='inputbox' name='menuvalg' onchange=\"
	
	if (document.getElementsByName('menuvalg')[0].value == 'ny') {
		const userInput = prompt('Navn på menu (kan ændres senere)');
		console.log(userInput);
		if (userInput != null) {
			document.getElementsByName('beskrivelse')[0].value = userInput;
			alert('Opretter menuen, du kan finde den i menulisten herefter');
			document.getElementsByName('posmenuer')[0].submit();
		}
	} else {
		document.getElementsByName('posmenuer')[0].submit();
	}
	
	\">\n";
	if (($menu_id || $menu_id == '0') && $beskrivelse)
		$menuvalg = $menu_id . ":" . $beskrivelse;
	else
		$menuvalg = NULL;
	$menu_id = $menu_id * 1;
	($menu_id) ? $disabled = '' : $disabled = 'disabled=disabled';

	$id = explode(":", $menuvalg)[0];
	$navn = explode(":", $menuvalg)[1];
	print "<option value='$menuvalg'>$navn ($id) </option>";
	$q = db_select("select * from grupper where art = 'POSBUT' order by box6 DESC, box1", __FILE__ . " linje " . __LINE__);
	$last_menu = "-";
	while ($r = db_fetch_array($q)) {
		# Create the menu category
		if ($r["box6"] != $last_menu) {
			if ($last_menu != "-") {
				print "</optgroup>";
			}

			if ($r["box6"] == "")
				print "<optgroup label='Undermenuer'>";
			if ($r["box6"] == "H")
				print "<optgroup label='Hovedmenuer'>";
			if ($r["box6"] == "A")
				print "<optgroup label='Afslutning'>";
			if ($r["box6"] == "B")
				print "<optgroup label='Bogført'>";
			if ($r["box6"] == "U")
				print "<optgroup label='Brugermenu'>";

			$last_menu = $r["box6"];
		}
		$tmp1 = $r['kodenr'] . ":" . $r['box1'];
		$tmp2 = $r['box1'];
		if ($tmp != $menuvalg)
			print "<option value='$tmp1'>$tmp2 ($r[kodenr])</option>\n";
	}
	print "</optgroup>";
	print "<option value='ny'>" . findtekst('2277|Opret ny', $sprog_id) . "</option>\n";
	print "</select></td>\n";
	print "<td><input type=submit value='OK' name='OK'></td></tr>\n";
	print "<input type='hidden' name='menu_id' value='$menu_id'>\n";
	print "<tr><td>" . findtekst('914|Beskrivelse', $sprog_id) . "</td><td><INPUT CLASS='inputbox' TYPE='text' name='beskrivelse' value=\"$beskrivelse\"></td></tr>\n";
	#
	# Hvis der er en aktiv menu valgt
	if ($beskrivelse) {
		print "<tr><td>" . findtekst('2280|Menutype', $sprog_id) . "</td><td><SELECT CLASS='inputbox' $disabled name='menutype'>\n";
		if ($menutype == 'H')
			print "<option value='H'>" . findtekst('2273|Hovedmenu', $sprog_id) . "</option>\n";
		elseif ($menutype == 'B')
			print "<option value='B'>" . findtekst('637|Bogført', $sprog_id) . "</option>\n";
		elseif ($menutype == 'A')
			print "<option value='A'>" . findtekst('2270|Afslutning', $sprog_id) . "</option>\n";
		elseif ($menutype == 'U')
			print "<option value='U'>" . findtekst('990|Bruger', $sprog_id) . "</option>\n";
		else
			print "<option value=''>" . findtekst('2274|Undermenu', $sprog_id) . "</option>\n";
		if ($menutype != 'H')
			print "<option value='H'>" . findtekst('2273|Hovedmenu', $sprog_id) . "</option>\n";
		if ($menutype != 'A')
			print "<option value='A'>" . findtekst('2270|Afslutning', $sprog_id) . "</option>\n";
		if ($menutype != 'B')
			print "<option value='B'>" . findtekst('637|Bogført', $sprog_id) . "</option>\n";
		if ($menutype != 'U')
			print "<option value='U'>" . findtekst('990|Bruger', $sprog_id) . "</option>\n";
		if ($menutype)
			print "<option value=''>" . findtekst('2274|Undermenu', $sprog_id) . "</option>\n";
		print "</select></td></tr>\n";

		# Hvis det er en hoved menu
		if ($menutype == 'H') {
			print "<tr><td>" . findtekst('2286|Aktiv fra', $sprog_id) . "</td><td><INPUT CLASS='inputbox' TYPE='text' 
			style='width:50px;text-align:right' name='begin' value='$begin'> - <INPUT CLASS='inputbox' TYPE='text' 
			style='width:50px;text-align:right' name='end' value='$end'></td></tr>";
			print "<tr><td>" . findtekst('274|Afdeling', $sprog_id) . "</td><td><SELECT CLASS='inputbox' $disabled name='projekt'>";
			$projekt = (int) $projekt;
			$qtxt = "select * from grupper where art='PRJ' and kodenr='$projekt'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			print "<option value='$projekt'>$r[beskrivelse]</option>";
			$q = db_select("select * from grupper where art='PRJ'", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if ($projekt != $r['kodenr'])
					print "<option value='$r[kodenr]'>$r[beskrivelse]</option>";
			}
			if ($projekt)
				print "<option value=''></option>";
			print "</select></td></tr>";
		}

		print "<tr><td colspan=3 style='border-bottom: 1px #999 solid'></td></tr>";

		if (count($afd_nr)) {
			print "<tr><td>Afdeling</td><td><SELECT CLASS='inputbox' $disabled name='afd'>";
			if ($afd == '')
				print "<option value=''></option>";
			for ($x = 0; $x < count($afd_nr); $x++) {
				if ($afd == $afd_nr[$x])
					print "<option value='$afd_nr[$x]'>$afd_beskrivelse[$x]</option>";
			}
			for ($x = 0; $x < count($afd_nr); $x++) {
				if ($afd != $afd_nr[$x])
					print "<option value='$afd_nr[$x]'>$afd_beskrivelse[$x]</option>";
			}
			if ($afd != '')
				print "<option value=''></option>";
		}
		if ($menu_id || $menu_id == '0') {
			print "<tr><td>" . findtekst('2281|Menustørrelse', $sprog_id) . "</td><td><INPUT CLASS='inputbox' $disabled TYPE='text' 
			style='width:50px;text-align:right' title='Kolonner' name='rows' value='$rows'> x <INPUT CLASS='inputbox' $disabled TYPE='text' 
			style='width:50px;text-align:right' title='Rækker' name='cols' value='$cols'></td></tr>";
		} else {
			print "<INPUT TYPE='hidden' name='rows' value='$rows'></td></tr>";
			print "<INPUT TYPE='hidden' name='cols' value='$cols'></td></tr>";
		}
		print "<tr><td>" . findtekst('2282|Knapstørrelse', $sprog_id) . "</td><td><INPUT CLASS='inputbox' TYPE='text' 
		style='width:50px;text-align:right' name='height' value='$height'> x <INPUT CLASS='inputbox' TYPE='text' 
		style='width:50px;text-align:right' name='width' value='$width'></td></tr>";
		print "<tr><td>Radius</td><td><INPUT CLASS='inputbox' TYPE='text' 
		style='width:50px;text-align:right' name='radius' value='$radius'></td></tr>";
		print "<tr><td>" . findtekst('2283|Tekststørrelse', $sprog_id) . "</td><td><INPUT CLASS='inputbox' TYPE='text' 
		style='width:50px;text-align:right' name='fontsize' value='$fontsize'></td></tr>";
		print "<tr><td>" . findtekst('2284|Plads', $sprog_id) . "</td><td><SELECT CLASS='inputbox' $disabled name='plads'>";
		if ($plads == 'H') {
			print "<option value='H'>" . findtekst('2278|Højre side', $sprog_id) . "</option>";
			print "<option value='B'>" . findtekst('2279|Bund', $sprog_id) . "</option>";
		} else {
			print "<option value='B'>" . findtekst('2279|Bund', $sprog_id) . "</option>";
			print "<option value='H'>" . findtekst('2278|Højre side', $sprog_id) . "</option>";
		}
		print "</select></td></tr>";
		if ($rows == '3' && $cols == '3') {
			print "<tr><td>" . findtekst('2285|Kopier fra menu nr.', $sprog_id) . ":</td><td><input type='text' 
			CLASS='inputbox' $disabled style='width:25px;text-align:right' name='kopier_menu'></td></tr>";
		}
	}
}
if (!$menu_id) {
	print "<input type='hidden' name='projekt' value=''>";
	print "<input type='hidden' name='rows' value='$rows'>";
	print "<input type='hidden' name='cols' value='$cols'>";
	print "<input type='hidden' name='plads' value='$plads'>";
}

$extra_buttons = if_isset($extra_buttons, "");

print "<tr>
  <td colspan=2>
    <input type=submit value='OK' name='OK'> 
    $extra_buttons
  </td>
</tr>";
print "</tbody></table></td></form>";
print "<td><table border=0><tbody>";
// <- Vindue 1
#if ($menu_id ) {
if ($plads == 'H')
	output($menu_id, $rows, $cols, $radius, $width, $height, $fontsize, $bgcolor2);
#}
print "</tbody></table>";
print "</td></tr><tr><td colspan='2'>";
// vindue 2 ->
print "<table border='0' cellspacing='5' cellpadding='5'><tbody>";
#if ($menu_id) {
if ($plads == 'B')
	output($menu_id, $rows, $cols, $radius, $width, $height, $fontsize, $bgcolor2);
#}
print "</tbody></table>";
print "</td><tr><td colspan='2\ width='100%'><tr><td>";
print "<table>\n"; #20211124
print "<tr><th>" . findtekst('1941|Import og eksport af POS-menuer', $sprog_id) . "</th></tr>\n"; # Import and export POS menues
print "<tr><td><form name='import_posmenu' action='importer_posmenu.php' method='post'>";
print "<input class='button blue medium' type='submit' style='width: 16em' value='" . findtekst('1934|Importér POS-menuer', $sprog_id) . "'>"; # Import POS menus
print "</form></td></tr>\n";
print "<tr><td><form name='export_posmenu' action='exporter_posmenu.php' method='post'>";
print "<input class='button blue medium' type='submit' style='width: 16em' value='" . findtekst('1932|Eksportér POS-menuer', $sprog_id) . "'>"; # Export POS menus
print "</form></td></tr>\n";
print "</table>";

print "</td></tr></tbody></table>";

function tid($tid)
{
	list($a, $b) = explode(":", $tid);
	$a = $a * 1;
	if ($a > 24)
		$a = 24;
	while (strlen($a) < 2)
		$a = "0" . $a;
	if ($b > 59)
		$b = 59;
	while (strlen($b) < 2)
		$b = "0" . $b;
	$tid = $a . ":" . $b;
	return ($tid);
}
function input($menu_id, $rows, $cols)
{
	for ($x = 1; $x <= $rows; $x++) {
		print "<tr>";
		print "<td style='width:140px;height:" . $height . "px;text-align:left'>
		<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='Tekst'><br>
		<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='Pris'><br>
		<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='Farvekode'><br>
		<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='Vare-/menunr'><br>
		<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='Funktion'>
		<INPUT CLASS='inputbox' READONLY='readonly' style='width:140px;text-align:left' value='Byt plads med'>
		</td>";
		for ($y = 1; $y <= $cols; $y++) {
			$r = db_fetch_array(db_select("select * from pos_buttons where menu_id='$menu_id' and row='$x' and col='$y'", __FILE__ . " linje " . __LINE__));
			$a = str_replace("\n", "<br>", $r['beskrivelse']);
			$b = $r['color'];
			$c = $r['vare_id'] * 1;
			$d = $r['funktion'] * 1;
			if ($d == 1 && $c) {
				$r = db_fetch_array(db_select("select varenr from varer where id='$c' and lukket !='on'", __FILE__ . " linje " . __LINE__));
				$c = $r['varenr'];
			}
			if ($d == 3 && $c) {
				$r = db_fetch_array(db_select("select kontonr from adresser where id='$c' and lukket !='on'", __FILE__ . " linje " . __LINE__));
				$c = $r['kontonr'];
			}
			if (!$c)
				$c = '';
			print "<td style='width:100px;height:100px;text-align:center'>
			<INPUT CLASS='inputbox' TYPE='text' style='width:100px;text-align:center' name='buttxt' value='$a'><br>
			<INPUT CLASS='inputbox' TYPE='text' style='width:100px;text-align:center' name='butcolor' value='$b'><br>\n";
			if ($d == 6) {
				print "<SELECT CLASS='inputbox' style='width:100px;' name='butvnr'
				onchange=\"document.getElementsByName('posmenuer')[0].submit();\">";
				/*if ($c==1) print "<OPTION value='1'>Bordvalg</OPTION>";
										if ($c==2) print "<OPTION value='2'>$buttonTextArr[user]</OPTION>";
										if ($c==3) print "<OPTION value='3'>Del bord</OPTION>";
										if ($c==4) print "<OPTION value='4'>Enter</OPTION>";
										if ($c==5) print "<OPTION value='5'>Find bon</OPTION>";
										if ($c==6) print "<OPTION value='6'>$buttonTextArr[movetable]</OPTION>";
										if ($c==7) print "<OPTION value='7'>$buttonTextArr[boxCount]</OPTION>";
										if ($c==8) print "<OPTION value='8'>Kassevalg</OPTION>";
										if ($c==9) print "<OPTION value='9'>Køkkenprint</OPTION>";
										if ($c==10) print "<OPTION value='10'>$buttonTextArr[close]</OPTION>";
										if ($c==11) print "<OPTION value='11'>$buttonTextArr[draw]</OPTION>";
										if ($c==12) print "<OPTION value='12'>$buttonTextArr[print]</OPTION>";
										if ($c==40 && $landeconfig == 'Norway') print "<OPTION value='40'>Proforma</OPTION>"; # LN 20190205
										elseif ($c==40) print "<OPTION value='40'>Udskriv</OPTION>"; # LN 20190205
										if ($c==41) print "<OPTION value='41'>X-Rapport</OPTION>"; # LN 20190205
										if ($c==42) print "<OPTION value='42'>Z-Rapport</OPTION>"; # LN 20190305
										if ($c==43) print "<OPTION value='43'>$buttonTextArr[copy]</OPTION>"; # LN 20190305
										if ($c==44) print "<OPTION value='44'>Hent bestilling</OPTION>"; # LN 20190709
										if ($c==45) print "<OPTION value='45'>Gem bestilling</OPTION>"; # LN 20190709
										if ($c!=1) print "<OPTION value='1'>Bordvalg</OPTION>";
										if ($c!=2) print "<OPTION value='2'>$buttonTextArr[user]</OPTION>";
										if ($c!=3) print "<OPTION value='3'>Del bord</OPTION>";
										if ($c!=4) print "<OPTION value='4'>Enter</OPTION>";
										if ($c!=5) print "<OPTION value='5'>Find bon</OPTION>";
										if ($c!=6) print "<OPTION value='6'>$buttonTextArr[moveTable]</OPTION>";
										if ($c!=7) print "<OPTION value='7'>$buttonTextArr[boxCount]</OPTION>";
										if ($c!=8) print "<OPTION value='8'>Kassevalg</OPTION>";
										if ($c!=9) print "<OPTION value='9'>Køkkenprint</OPTION>";
										if ($c!=10) print "<OPTION value='10'>$buttonTextArr[close]</OPTION>";
										if ($c!=11) print "<OPTION value='11'>$buttonTextArr[draw]</OPTION>";
										if ($c!=12) print "<OPTION value='12'>$buttonTextArr[print]</OPTION>";
										if ($c!=40) print "<OPTION value='40'>Udskriv</OPTION>"; # LN 20190205
										if ($c!=41) print "<OPTION value='41'>X-Rapport</OPTION>"; # LN 20190205
										if ($c!=42) print "<OPTION value='42'>Z-Rapport</OPTION>"; # LN 20190305
										if ($c!=43) print "<OPTION value='43'>$buttonTextArr[copy]</OPTION>"; # LN 20190305
										if ($c!=44) print "<OPTION value='44'>Hent bestilling</OPTION>"; # LN 20190709
										if ($c!=45) print "<OPTION value='45'>Gem bestilling</OPTION>"; */# LN 20190709
				print "<OPTION value='1'" . ($c == 1 ? " selected" : "") . ">$buttonTextArr[table]</OPTION>\n";
				print "<OPTION value='3'" . ($c == 3 ? " selected" : "") . ">$buttonTextArr[splitTable]</OPTION>\n";
				print "<OPTION value='6'" . ($c == 6 ? " selected" : "") . ">$buttonTextArr[moveTable]</OPTION>\n";
				print "<OPTION value='24'" . ($c == 24 ? " selected" : "") . ">Kør bord</OPTION>\n";

				# User
				print "<OPTION value='2'" . ($c == 2 ? " selected" : "") . ">$buttonTextArr[user]</OPTION>\n";
				print "<OPTION value='14'" . ($c == 14 ? " selected" : "") . ">Ekspedient</OPTION>\n";

				# Fysiske
				print "<OPTION value='11'" . ($c == 11 ? " selected" : "") . ">$buttonTextArr[draw]</OPTION>\n";
				if ($country == "Denmark")
					print "<OPTION value='12'" . ($c == 12 ? " selected" : "") . ">$buttonTextArr[print]</OPTION>\n";
				print "<OPTION value='32'" . ($c == 32 ? " selected" : "") . ">Udskriv sidste</OPTION>\n";
				print "<OPTION value='9'" . ($c == 9 ? " selected" : "") . ">Køkkenprint</OPTION>\n";
				print "<OPTION value='23'" . ($c == 23 ? " selected" : "") . ">$buttonTextArr[sendToKitchen]</OPTION>\n";

				# Varer / rabatter
				print "<OPTION value='33'" . ($c == 33 ? " selected" : "") . ">Sæt</OPTION>\n";
				print "<OPTION value='17'" . ($c == 17 ? " selected" : "") . ">$buttonTextArr[price]</OPTION>\n";
				print "<OPTION value='18'" . ($c == 18 ? " selected" : "") . ">$buttonTextArr[discount]</OPTION>\n";
				print "<OPTION value='47'" . ($c == 47 ? " selected" : "") . ">Sæt</OPTION>"; # PHR 20240823
				print "<OPTION value='38'" . ($c == 38 ? " selected" : "") . ">Totalrabat</OPTION>\n";	# 20190104

				# Generelle knapper
				print "<OPTION value='4'" . ($c == 4 ? " selected" : "") . ">Enter</OPTION>\n";
				print "<OPTION value='28'" . ($c == 28 ? " selected" : "") . ">Enter+Menu</OPTION>\n";
				print "<OPTION value='16'" . ($c == 16 ? " selected" : "") . ">Afslut</OPTION>\n";
				print "<OPTION value='5'" . ($c == 5 ? " selected" : "") . ">$buttonTextArr[findReceipt]</OPTION>\n";
				print "<OPTION value='8'" . ($c == 8 ? " selected" : "") . ">Kassevalg</OPTION>\n";
				print "<OPTION value='44'" . ($c == 44 ? " selected" : "") . ">Hent bestilling</OPTION>\n"; # LN 20190709
				print "<OPTION value='45'" . ($c == 45 ? " selected" : "") . ">Gem bestilling</OPTION>\n"; # LN 20190709

				# Bogført
				print "<OPTION value='20'" . ($c == 20 ? " selected" : "") . ">$buttonTextArr[newCustomer]</OPTION>\n";
				print "<OPTION value='21'" . ($c == 21 ? " selected" : "") . ">$buttonTextArr[correction]</OPTION>\n";

				print "<OPTION value='7'" . ($c == 7 ? " selected" : "") . ">$buttonTextArr[boxCount]</OPTION>\n";
				print "<OPTION value='10'" . ($c == 10 ? " selected" : "") . ">$buttonTextArr[close]</OPTION>\n";
				print "<OPTION value='13'" . ($c == 13 ? " selected" : "") . ">$buttonTextArr[start]</OPTION>\n";
				print "<OPTION value='15'" . ($c == 15 ? " selected" : "") . ">$buttonTextArr[clear]</OPTION>\n";
				print "<OPTION value='19'" . ($c == 19 ? " selected" : "") . ">$buttonTextArr[back]</OPTION>\n";
				print "<OPTION value='22'" . ($c == 22 ? " selected" : "") . ">Kortterminal</OPTION>\n";
				print "<OPTION value='25'" . ($c == 25 ? " selected" : "") . ">Debitoropslag</OPTION>\n";
				print "<OPTION value='26'" . ($c == 26 ? " selected" : "") . ">Indbetaling</OPTION>\n";
				print "<OPTION value='27'" . ($c == 27 ? " selected" : "") . ">Konto</OPTION>\n";
				print "<OPTION value='29'" . ($c == 29 ? " selected" : "") . ">Vareopslag</OPTION>\n";
				print "<OPTION value='30'" . ($c == 30 ? " selected" : "") . ">Stamkunder</OPTION>\n";
				print "<OPTION value='31'" . ($c == 31 ? " selected" : "") . ">Kontoudtog</OPTION>\n";
				print "<OPTION value='34'" . ($c == 34 ? " selected" : "") . ">Følgeseddel</OPTION>\n";
				print "<OPTION value='35'" . ($c == 35 ? " selected" : "") . ">Kreditoropslag</OPTION>\n";
				print "<OPTION value='36'" . ($c == 36 ? " selected" : "") . ">Gavekortsalg</OPTION>\n";	# 20181029
				print "<OPTION value='37'" . ($c == 37 ? " selected" : "") . ">Gavekortstatus</OPTION>\n";	# 20181029
				if ($country == "Norway")
					print "<OPTION value='39'" . ($c == 39 ? " selected" : "") . ">Retur</OPTION>\n";  # LN 20190205
				if ($country == "Norway")
					print "<OPTION value='40'" . ($c == 40 ? " selected" : "") . ">Udskriv</OPTION>\n";   # LN 20190205
				if ($country == "Norway")
					print "<OPTION value='41'" . ($c == 41 ? " selected" : "") . ">X-Rapport</OPTION>\n";   # LN 20190205
				if ($country == "Norway")
					print "<OPTION value='42'" . ($c == 42 ? " selected" : "") . ">Z-Rapport</OPTION>\n";   # LN 20190305
				if ($country == "Norway")
					print "<OPTION value='43'" . ($c == 43 ? " selected" : "") . ">$buttonTextArr[copy]</OPTION>\n";   # LN 20190305
				print "<OPTION value='46'" . ($c == 46 ? " selected" : "") . ">Åben scannermodul</OPTION>\n"; # MMK 20242208
				# Latest id: 47 next id: 48
				print "</SELECT>";
			} else
				print "<INPUT 
                      CLASS='inputboxxx' 
                      TYPE='text' 
                      style='width:100px;text-align:center' 
                      name='butvnr' 
					  onchange=\"document.getElementsByName('posmenuer')[0].submit();\"
                      value='$c'
                    >
                  <br>\n";
			print "<SELECT CLASS='inputbox' style='width:100px;' name='butfunc'>";
			if ($d == 1)
				print "<OPTION value='1'>Varenr</OPTION>";
			if ($d == 2)
				print "<OPTION value='2'>Menu</OPTION>";
			if ($d == 3)
				print "<OPTION value='3'>Kundenr</OPTION>";
			if ($d == 4)
				print "<OPTION value='4'>Specialfunktion</OPTION>";
			if ($d == 5)
				print "<OPTION value='5'>Tastatur</OPTION>";
			if ($d == 6)
				print "<OPTION value='6'>Systemknap</OPTION>";

			if ($d != 1)
				print "<OPTION value='1'>Varenr</OPTION>";
			if ($d != 2)
				print "<OPTION value='2'>Menu</OPTION>";
			if ($d != 3)
				print "<OPTION value='3'>Kundenr</OPTION>";
			if ($d != 4)
				print "<OPTION value='4'>Specialfunktion</OPTION>";
			if ($d != 5)
				print "<OPTION value='5'>Tastatur</OPTION>";
			if ($d != 6)
				print "<OPTION value='6'>Systemknap</OPTION>";
			print "</SELECT></td>";
		}
		print "</tr>";
	}
}
function output($menu_id, $rows, $cols, $radius, $width, $height, $fontsize, $bgcolor2)
{
	print "<tr><td></td>\n";
	print "<form name='flyt_cols' action='posmenuer.php?menu_id=$menu_id' method='post'>\n";
	for ($y = 1; $y <= $cols; $y++) {
		print "";
		print "<td align='center'>
			<input type='hidden' name='col[$y]' value='" . chr(96 + $y) . "'>\n
			<input style='width:20px;text-align:center;' type='text' name='flyt_col[$y]' value='" . chr(96 + $y) . "' onchange= 'this.form.submit()'>
			</td>\n";
	}
	print "</form>\n";
	print "</tr>\n";
	$a = '';
	print "<form name='flyt_rows' action='posmenuer.php?menu_id=$menu_id' method='post'>\n";
	for ($x = 1; $x <= $rows; $x++) {
		print "<tr><td>
			<input style='width:20px;text-align:center;' type='text' 
			name='flyt_row[$x]' value='$x' onchange= 'this.form.submit()'>
			</td>";
		for ($y = 1; $y <= $cols; $y++) {
			$qtxt = "select * from pos_buttons where menu_id='$menu_id' and row='$x' and col='$y'";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$a = $r['beskrivelse'];
				$b = $r['color'];
				$b_font = $r['fontcolor'];
				$c = (int) $r['vare_id'];
				$d = (int) $r['funktion'];
				if ($d == 1 && $c) {
					$qtxt = "select varenr from varer where id='$c' and lukket !='on'";
					$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
					$c = $r['varenr'];
				}
				if ($d == '7' && substr($a, -4) == 'kort' && strlen($a) > 8) {
					$a = str_replace('kort', ' <br>kort', $a);
				}
			} else {
				$a = $b = $c = '';
				$d = 1;
			}

			$selected = ($_GET["ret_row"] == $x && $_GET["ret_col"] == $y) ? "box-shadow: 0 0 0 3px green;" : "";

			#			$fontsize=$height*0.7;
			$style = "
				display: table-cell;
				moz-border-radius:" . $radius . "px;
				-webkit-border-radius:" . $radius . "px;
				width:" . $width . "px;
				height:" . $height . "px;
				min-width:" . $width . "px;
				min-height:" . $height . "px;
				text-align:center;
				vertical-align:middle;
				font-size:" . $fontsize . "px;
				border: 1px solid $bgcolor2;
				white-space: normal;
				background-color:#$b;
				color:#$b_font;
        		cursor: pointer;
				$selected;
			";
			/*
								 if (!$menu_id) {
									 $a=$y;
									 if ($x>1) $a+=3;
									 if ($x>2) $a+=3;
								 }
					 */
			#			style='width:".$width."px;height:".$height."px;text-align:center;font-size:".$fontsize."px; background-color:#$b;'
			$a = str_replace(" på beløb", "\n på beløb", $a);
			$a = str_replace('<br>', '&#x00A; ', $a);
			print "
      <td
      >
        <a 
          href='posmenuer.php?menu_id=$menu_id&ret_row=$x&ret_col=$y' 
          style='text-decoration: none; user-select: none'
        >
          <span 
            type='button' 
            style='$style' 
            draggable='true' 
            ondragstart='drag(event)'
            ondrop='drop(event)' 
            ondragover='allowDrop(event)'
          >
            $a
          </span>
        </a>
      </td>";
		}
		print "</tr>";
	}
	print "</form>\n"; # 20211124
}
# print "<script language='javascript'>document.posmenuer.buttxt.focus();</script>";

# Setup popups
print "<div id='modal-bg' onclick='close_menu_popup()'></div>";

# Menu modal
print "<div id='menu-popup' class='posmenu-popup'>";
print "<input type='text' id='search-menu' placeholder='Søg efter en menu' onkeyup='filter_table(\"search-menu\", \"menu-table\")'>";
print "<table id='menu-table' class='search-table'>";
print "<tr><th>ID</th><th>" . findtekst('914|Beskrivelse', $sprog_id) . "</th></tr>
       <tbody>";
$q = db_select("select kodenr, box1 from grupper where art='POSBUT' order by box1", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	print "
    <tr 
      id='menu-table-tr' 
      onclick='document.getElementsByName(\"butvnr\")[0].value=\"$r[kodenr]\"; close_menu_popup(); '
    >
      <td class='id'>$r[kodenr]</td>
      <td class='desc'>$r[box1]</td>
    </tr>
  ";
	$x++;
}
print "</tbody></table>";
print "</div>";

# Item modal
print "<div id='vare-popup' class='posmenu-popup'>";
print "<input type='text' id='search-vare' placeholder='Søg efter en vare' onkeyup='filter_table(\"search-vare\", \"vare-table\")'>";
print "<table id='vare-table' class='search-table'>";
print "<tr><th>ID</th><th>" . findtekst('914|Beskrivelse', $sprog_id) . "</th></tr>
       <tbody>";
$q = db_select("select varenr, beskrivelse, salgspris from varer where NOT beskrivelse = '' order by beskrivelse", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	print "
    <tr 
      id='menu-table-tr' 
      onclick='
        document.getElementsByName(\"butvnr\")[0].value=\"$r[varenr]\"; 
        document.getElementsByName(\"buttxt\")[0].value=\"$r[beskrivelse]\"; 
        document.getElementsByName(\"localPrice\")[0].value=\"" . dkDecimal($r["salgspris"]) . "\"; 
        close_menu_popup(); 
      '
    >
      <td class='id'>$r[varenr]</td>
      <td class='desc'>$r[beskrivelse]</td>
    </tr>
  ";
	$x++;
}
print "</tbody></table>";
print "</div>";

?>
<script>
	var start_pos = { menu: 0, row: 0, col: 0 };

	function allowDrop(ev) {
		ev.preventDefault();
	}

	function drag(ev) {
		var link = ev.target.parentElement.href.split('?')[1];
		var [menu, row, col] = link.split('&');
		var [
			menu,
			row,
			col
		] = [
				menu.slice(8, menu.length),
				row.slice(8, row.length),
				col.slice(8, col.length)
			];

		start_pos = { menu: menu, row: row, col: col };

		console.log(menu, row, col);
		ev.dataTransfer.setData('text', ev.target.id);
	}

	function drop(ev) {
		var [menu, row, col] = ev.target.parentElement.href.split('?')[1].split('&');
		var [
			menu,
			row,
			col
		] = [
				menu.slice(8, menu.length),
				row.slice(8, row.length),
				col.slice(8, col.length)
			];

		var end_pos = { menu: menu, row: row, col: col };

		console.log(start_pos);
		console.log(end_pos);
		data = { from: start_pos, to: end_pos };

		fetch('pos_ryk_knap.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify(data)
		}).then((res) => {
			location.reload()
		});
	}

	const choose_lnk = document.getElementById('choose-item');
	const select = document.getElementById('func-select');

	function func_change() {
		var selected = select.value;
		document.getElementById('varekort-btn').style.display = 'none';
		if (selected === '1') {
			document.getElementById('varekort-btn').style.display = '';
			choose_lnk.innerText = 'Vælg Varenr';
		} else if (selected === '2') {
			choose_lnk.innerText = 'Vælg Menunr';
		} else {
			choose_lnk.innerText = '';
		}
	}

	// Initialise the script
	func_change();

	function open_popup() {
		if (choose_lnk.innerText === 'Vælg Menunr') {
			open_menu_popup();
		} else if (choose_lnk.innerText === 'Vælg Varenr') {
			open_vare_popup();
		}
	}

	function open_menu_popup() {
		document.getElementById('modal-bg').style.display = 'block';
		document.getElementById('menu-popup').style.display = 'block';

		document.getElementById('search-menu').focus();
		document.getElementById('search-menu').value = '';
	}

	function open_vare_popup() {
		document.getElementById('modal-bg').style.display = 'block';
		document.getElementById('vare-popup').style.display = 'block';

		document.getElementById('search-vare').focus();
		document.getElementById('search-vare').value = '';
	}

	function close_menu_popup() {
		document.getElementById('modal-bg').style.display = '';
		document.getElementById('menu-popup').style.display = '';
		document.getElementById('vare-popup').style.display = '';
	}

	// Filtering function
	function filter_table(input_id, table_id) {
		// Declare variables
		var input, filter, table, tr, td, i, txtValue;
		input = document.getElementById(input_id);
		filter = input.value.toUpperCase();
		table = document.getElementById(table_id);
		tr = table.getElementsByTagName('tr');

		// Loop through all table rows, and hide those who don't match the search query
		for (i = 0; i < tr.length; i++) {
			td = tr[i].getElementsByTagName('td')[1];
			if (td) {
				txtValue = td.textContent || td.innerText;
				if (txtValue.toUpperCase().indexOf(filter) > -1) {
					tr[i].style.display = '';
				} else {
					tr[i].style.display = 'none';
				}
			}
		}
	}

	function open_product_page() {
		// Get the id of the product we want to open
		const product = document.getElementsByName("butvnr")[0].value;

	fetch(
			'pos_get_product_id.php',
			{
				method: 'POST',
				body: JSON.stringify({ 'id': product }),
			}
		)
				.then((res) => res.text())
				.then((res) => {
				var lines = res.split('\n');
					var id = lines[lines.length - 1];
					console.log(id);
					if (isNaN(id) || id === '') {
						alert('Ikke et validt varenummer.');
					} else {
					window.location.href = `../lager/varekort.php?id=${id}&returside=${encodeURI(window.location.href)}`;
					}
				})
  }

	function delete_btn() {
		fetch(
			'pos_del_btn.php',
			{
				method: 'POST',
				body: JSON.stringify({ 'menu': '<?php print $menu_id; ?>', 'row': '<?php print $ret_row; ?>', 'col': '<?php print $ret_col; ?>' }),
			}
		)
			.then((res) => res.text())
			.then((res) => {
				window.location.href = "?menu_id=<?php print $menu_id; ?>";
			})
	}
</script>