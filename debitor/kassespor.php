<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/kassespor.php -----patch 4.0.8 ----2023-09-07--------------
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20141119 PHR Tilføjet summer og bord
// 20150305 PHR Tilføjet status.
// 20150305 PHR Skriver nu bordnavn i stedet for bordnr. Søg $bordnavn & $bordnr
// 20160413	PHR Medtog ej pos_ordrer i sum hvis kasse ikke var valgt. Søg straksksbogfor
// 20160929	PHR	Løb tør for hukommelse hvis ingen søgekriterier.
// 20161129 PHR	rettet nysort=refs til nysort=ref & nysort=summer til nysort=sum. Søg nysort=sum
// 20170419	PHR Straksbogfør skelner nu mellem debitor og kreditorordrer. Dvs debitor;kreditor - Søg # 20170419
// 20171004	PHR	Viser nu kun summer hvis der er saldo. Søg: if ($bet_sum[$z])
// 20180615	PHR	Indsat mulighed for at rettet betalingsmetode på ikke bogførte bonnner. Søg '$ret_bet_id'
// 20190107 MSC Rettet topmenu design til
// 20190218 MSC Rettet topmenu design
// 20190404 PHR If status field is empty it will now be set to '3:4' as it included orders with invoicedate and status less than 3
// 20200624 PHR Added time as search posibility.
// 20201024 PHR Added lokk to pos_ordre.
// 20210305 PHR Added limit 10000 to query.
// 20210330 Loe translated these to English 
// 20210707 LOE Fixed a bug  Uncaught Error: Cannot pass parameter 1 by reference
// 20210805 LOE Translated title texts
// 20211012 MSC Implementing new design
// 20211101 MSC Implementing new design
// 20220905 MSC Implementing new design
// 20230427 PHR Extendet cookie lifetime to 1 year.
// 20230907 PHR PHP-8
// 20243025 Sawaneh add rabat and gross profit columns

ob_start();
@session_start();
$s_id=session_id();
$title="Kassespor";
$modulnr=12;
$css="../css/standard.css";
$hreftext=0;
$udskriv=NULL;
$valg=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");
include("../includes/topline_settings.php");
include("../includes/row-hover-style.js.php");
$r = db_fetch_array(db_select("select box2 from grupper where art = 'DIV' and kodenr = '3'", __FILE__ . " linje " . __LINE__));
if ($r && $r['box2']) {
    $v = db_fetch_array(db_select("select varenr from varer where id = '{$r['box2']}'", __FILE__ . " linje " . __LINE__));
    if ($v && $v['varenr']) {
        $rabat_varenr = trim($v['varenr']);
    }
}

$r = db_fetch_array(db_select("select box12 from grupper where art = 'POS' and kodenr = '1'", __FILE__ . " linje " . __LINE__));
if ($r && $r['box12'] == 'on') $vis_saet = 1;


$status = if_isset($_GET['status']);
$id = if_isset($_GET['id']);
$fakturadatoer = if_isset($_GET['fakturadatoer']);
$logtimes = if_isset($_GET['logtimes']);
$afdelinger= if_isset($_GET['afdelinger']);
$sort = if_isset($_GET['sort']);
$nysort = if_isset($_GET['nysort']);
$idnumre = if_isset($_GET['idnumre']);
$kontonumre = if_isset($_GET['kontonumre']);
$fakturanumre = if_isset($_GET['fakturanumre']);
$summer = if_isset($_GET['summer']);
$betalinger = if_isset($_GET['betalinger']);
$betalinger2 = if_isset($_GET['betalinger2']);
$modtagelser = if_isset($_GET['modtagelser']);
$modtagelser2 = if_isset($_GET['modtagelser2']);
$kasser =  if_isset($_GET['kasser']);
$borde =  if_isset($_GET['borde']);
$refs =  if_isset($_GET['refs']);
$start = if_isset($_GET['start']);
$ret_bet_id = if_isset($_GET['ret_bet_id']);
$changeCashNo = if_isset($_GET['changeCashNo']);

if (!isset ($_COOKIE['saldi_kassespor'])) $_COOKIE['saldi_kassespor'] = NULL;
if (!isset ($beskrivelse)) $beskrivelse = NULL;
if (!isset ($logtid)) $logtid = NULL;

if ($submit= if_isset($_POST['submit'])){
	$status = if_isset($_POST['status']);
	$fakturadatoer = if_isset($_POST['fakturadatoer']);
	$logtimes = if_isset($_POST['logtimes']);
	$afdelinger=if_isset($_POST['afdelinger']);
	$sort = if_isset($_POST['sort']);
	$nysort = if_isset($_POST['nysort']);
	$idnumre = if_isset($_POST['idnumre']);
	$kontonumre = if_isset($_POST['kontonumre']);
	$fakturanumre = if_isset($_POST['fakturanumre']);
	$summer = if_isset($_POST['summer']);
	$betalinger = if_isset($_POST['betalinger']);
	$betalinger2 = if_isset($_POST['betalinger2']);
	$modtagelser = if_isset($_POST['modtagelser']);
	$modtagelser2 = if_isset($_POST['modtagelser2']);
	$kasser =  if_isset($_POST['kasser']);
	$borde =  if_isset($_POST['borde']);
	$refs =  if_isset($_POST['refs']);
	$linjeantal = if_isset($_POST['linjeantal']);
	$cookievalue="$sort;$nysort;$fakturadatoer;$logtimes;$afdelinger;$sort;$nysort;$idnumre;$fakturanumre;$summer;$betalinger;$betalinger2;$modtagelser;$modtagelser2;$kasser;$refs;$linjeantal;$borde;$status";
	setcookie("saldi_kassespor", $cookievalue, time()+3600*24*365);
} else {
	list ($sort,$nysort,$fakturadatoer,$logtimes,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$summer,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$refs,$linjeantal,$borde,$status) = array_pad(explode(";", $_COOKIE['saldi_kassespor']),19,NULL);
	$beskrivelse=str_replace("semikolon",";",$beskrivelse);
	if (isset($_GET['sort'])) $sort = $_GET['sort'];
	if (isset($_GET['nysort'])) $nysort = $_GET['nysort'];
}
ob_end_flush();  //Sender det "bufferede" output afsted...
if (!$fakturadatoer&&!$logtimes&&!$afdelinger&&!$sort&&!$nysort&&!$idnumre&&!$fakturanumre&&!$summer&&!$betalinger&&!$betalinger2&&!$modtagelser&&!$modtagelser2&&!$kasser&&!$refs&&!$borde) {
	$r=db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
	$fakturadatoer="01".$r['box1'].substr($r['box2'],-2).":31".$r['box3'].substr($r['box4'],-2);
}
if ($logtid) {
	list ($h,$m)=explode(":",$logtid);
	$h=$h*1;
	$m=$m*1;
	if (strlen($h)>2) $h=substr($h,-2);
	if (strlen($m)>2) $m=substr($m,-2);
	$logtid="$h:$m";
}
$tidspkt=date("U");

$modulnr=2;

if (!$sort) $sort = "tidspkt desc";
elseif ($nysort==$sort) $sort=$sort." desc";
elseif ($nysort) $sort=$nysort;
$x=0;
$bet_type=array();
$q=db_select("select betalingstype from pos_betalinger group by betalingstype order by betalingstype",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if (!is_numeric($r['betalingstype']) && !in_array(strtolower($r['betalingstype']),$bet_type)) {
		$bet_type[$x]=strtolower($r['betalingstype']);
		$bet_sum[$x]=0;
		$x++;
	}
}

# Check if the old system is in use.
$r=db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__));
if ($r[0]) {
	($r['box7'])?$bordnavn=explode(chr(9),$r['box7']):$bordnavn=array(); #20141119
} else {
	$bordnavn = array();
	$q=db_select("select name from table_plan",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		array_push($bordnavn, $r["name"]);
	}
}


if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\"><a href=rapport.php  accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu == 'S') {
	print "<center><table width=100% height=5% border=0 cellspacing=0 cellpadding=0><tbody>";
	print "<tr><td height = 25 align=center valign=top>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
	print "<tr>";

	print "<td width=10%><a href=rapport.php accesskey=L>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst(30,$sprog_id)."</button></a></td>\n";

	print "<td width=80% align='center' style='$topStyle'>".findtekst(455,$sprog_id)."</td>\n";

	print "<td width=10% align='center' style='$topStyle'><br></td>\n";
	print "</tr>\n";
	print "<tr>";
} else {
	print "<center><table width=100% height=5% border=0 cellspacing=0 cellpadding=0><tbody>";
	print "<tr><td height = 25 align=center valign=top>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
	print "<tr>";
	print "<td width=10% $top_bund>";
		if ($popup) print "<a href=../includes/luk.php accesskey=L>".findtekst(30,$sprog_id)."</a></td>\n";
		else print "<a href=rapport.php accesskey=L>".findtekst(30,$sprog_id)."</a></td>\n";
	print "<td width=80% $top_bund>".findtekst(455,$sprog_id)."</td>\n";
	print "<td width=10% $top_bund><br></td>\n";
	print "</tr>\n";
	print "<tr>";
}

$newCashNo = if_isset($_POST['newCashNo']); #20210707

if ($newCashNo && $editCashNo) {
	$qtxt="update ordrer set felt_5='$ny_betal_type' where id='$ret_bet_id'";
#		echo $qtxt."<br>"; 
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	alert("Bon $bon rettet fra $betal_type til $ny_betal_type");

	
} elseif ($editCashNo) {
	print "<form name=ret_betaling action='kassespor.php?editCashNo='$editCashNo' method='post'>";
	print "<table><tbody><br><br><br>";
	print "<tr><td>Ret Kasse på bon: <b>$bon</b> fra <b>$currentCashNo</b> til:</td>"; 
	$qtxt = "select box1 from grupper where art = 'POS' and kodenr = '1'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	print "<td><select class=\"inputbox\" style=\"text-align:right;width:200px;height:30px;font-size:12px\" name=\"newcashNo\">";
	print "<option value=\"$currentCashNo\">$newCashNo</option>";
	for ($x=1;$x<=$r['box1'];$x++) {
		if ($currentCashNo!=$x) print "<option value='$x'>$x</option>"; 
	}
	print "</select></td></tr>";
	print "<tr><td colspan='2' align='center'><br>";
	print "<input style='width:100px;' type='submit' value='Fortryd' name='fortryd'>";	
	print "&nbsp;&nbsp;&nbsp;";
	print "<input style='width:100px;' type='submit' value='Ret' name='ret'></td>\n";
	print "</tbody></table></td></tr>\n"; 
	exit;

} elseif ($ret_bet_id && !isset($_POST['fortryd'])) {
	$betal_type=$_GET['betal_type'];
	$bon=$_GET['bon'];
	$sum=$_GET['sum'];
	if ($ret=if_isset($_POST['ret']) && $ny_betal_type=if_isset($_POST['ny_betal_type'])) {
		$qtxt="update pos_betalinger set betalingstype='$ny_betal_type' where id='$ret_bet_id'";
#		echo $qtxt."<br>"; 
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		alert("Bon $bon rettet fra $betal_type til $ny_betal_type");
	} else {
		print "<form name=ret_betaling action='kassespor.php?ret_bet_id=$ret_bet_id&betal_type=$betal_type&bon=$bon&sum=$sum' method='post'>";
		print "<tr><td colspan='3' align='center'><table><tbody><br><br><br>";
		print "<tr><td>Ret bon: <b>$bon</b> betaling på kr. ".dkdecimal($sum,2)." fra <b>$betal_type</b> til:</td>"; 
		$qtxt="select box5 from grupper where art = 'POS' and kodenr = '1'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$korttyper=explode(chr(9),$r['box5']);
		print "<td><select class=\"inputbox\" style=\"text-align:right;width:200px;height:30px;font-size:12px\" name=\"ny_betal_type\">";
		if ($betal_type!='Kontant') print "<option value=\"Kontant\">Kontant</option>";
		for ($x=0;$x<count($korttyper);$x++) {
			if ($betal_type!=$korttyper[$x]) print "<option value=\"$korttyper[$x]\">$korttyper[$x]</option>"; 
		}
		print "</select></td></tr>";
		print "<tr><td colspan='2' align='center'><br>";
		print "<input style='width:100px;' type='submit' value='Fortryd' name='fortryd'>";	
		print "&nbsp;&nbsp;&nbsp;";
		print "<input style='width:100px;' type='submit' value='Ret' name='ret'></td>\n";
		print "</tbody></table></td></tr>\n";
		exit;
	}
} 

if (!isset($kontoid)) $kontoid = NULL;

	print "<form name=bonliste action=kassespor.php method=post>";

if ($menu=='T') {
	print "<table width=100% class='dataTable'><tbody>";
	if (!$linjeantal) $linjeantal=50;
	$next=udskriv($fakturadatoer,$logtimes,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$summer,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$refs,$linjeantal,$start,'',$borde,$status);
	if ($start>=$linjeantal) {
		$tmp=$start-$linjeantal;
		print "<td class='imgNoTextDeco'><a href='kassespor.php?sort=$sort&start=$tmp'><img class='imgInvert imgFade' src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>\n";
	} else print  "<td></td>\n";
	print "<td align=center valign=top style='width:90%'><span title= '".findtekst(1609, $sprog_id)."'><input class=\"inputbox\" type=text style=\"text-align:right;width:50px; text-align:center;\" name=\"linjeantal\" value=\"$linjeantal\"></td>\n";
	$tmp=$start+$linjeantal;
	if ($next>0) {
		print "<td class='imgNoTextDeco' align=right><a href='kassespor.php?sort=$sort&start=$tmp'><img class='imgInvert imgFade' src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>\n";
	} else {
		print "<td></td>\n";
	}
	print "</tr>\n";
	print "</tbody></table>";
} else {
	if (!$linjeantal) $linjeantal=50;
	$next=udskriv($fakturadatoer,$logtimes,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$summer,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$refs,$linjeantal,$start,'',$borde,$status);
	if ($start>=$linjeantal) {
		$tmp=$start-$linjeantal;
		print "<td><a href='kassespor.php?sort=$sort&start=$tmp'><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>\n";
	} else print  "<td></td>\n";
	print "<td align=center valign=top style='height:10%;><span title= '".findtekst(1609, $sprog_id)."'><input class=\"inputbox\" type=text style=\"text-align:right;width:30px\" name=\"linjeantal\" value=\"$linjeantal\"></td>\n";
	$tmp=$start+$linjeantal;
	if ($next>0) {
		print "<td align=right><a href='kassespor.php?sort=$sort&start=$tmp'><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>\n";
	} else {
		print "<td></td>\n";
	}
	print "</tr>\n";
	print "</tbody></table>";
}

if ($menu=='T') {
	print "<div class='dataTablediv'>";
} else {
	print "";
}

print "<table cellpadding=1 cellspacing=1 border=0 valign = top class='dataTable' width='100%'>";

if ($menu=='T') {
	print "<thead>";
} else {
	print "<tbody>";
	print "<tr><td colspan=20><hr></td></tr>\n";
}
// print "<tr>";
// print "<td style=\"padding-top: 20px;width:30px\"><b><a href='kassespor.php?nysort=id&sort=$sort&valg=$valg$hreftext'>Status</b></td>\n";
// print "<td style=\"padding-top: 20px;width:60px\"><b><a href='kassespor.php?nysort=id&sort=$sort&valg=$valg$hreftext'>Id</b></td>\n";
// print "<td style=\"padding-top: 20px; text-align:right;width:110px\"><b><a href='kassespor.php?nysort=fakturadate&sort=$sort&valg=$valg$hreftext'>".findtekst(929,$sprog_id)."</a></b></td>\n"; #20210330
// print "<td style=\"padding-top: 20px; text-align:right;width:50px\"><b>".findtekst(930,$sprog_id).".</a></b></td>\n";
// #print "<td align=center><b><a href='kassespor.php?nysort=kontonr&sort=$sort&valg=$valg$hreftext'>Konto</b></td>\n";
// print "<td style=\"padding-top: 20px; text-align:right;width:110px\"><b><a href='kassespor.php?nysort=fakturanr&sort=$sort&valg=$valg$hreftext'>".findtekst(928,$sprog_id)."</a></b></td>\n"; 
// print "<td style=\"padding-top: 20px; text-align:right;width:50px\"><b><a href='kassespor.php?nysort=felt_5&sort=$sort&valg=$valg$hreftext'>".findtekst(931,$sprog_id)."</a></b></td>\n";
// print "<td style=\"padding-top: 20px; text-align:right;width:50px\"><b><a href='kassespor.php?nysort=nr&sort=$sort&valg=$valg$hreftext'>".findtekst(932,$sprog_id)."</a></b></td>\n";
// print "<td style=\"padding-top: 20px; text-align:right;width:50px\"><b><a href='kassespor.php?nysort=ref&sort=$sort&valg=$valg$hreftext'>".findtekst(933,$sprog_id)."</a></b></td>\n";
// print "<td style=\"padding-top: 20px; text-align:right;width:100px\"><b><a href='kassespor.php?nysort=sum&sort=$sort&valg=$valg$hreftext'>".findtekst(934,$sprog_id)."</a></b></td>\n";
// print "<td style=\"padding-top: 20px; text-align:right;width:100px\"><b><a href='kassespor.php?nysort=felt_1&sort=$sort&valg=$valg$hreftext'>".findtekst(935,$sprog_id)."</a></b></td>\n";
// print "<td style=\"padding-top: 20px; text-align:right;width:100px\"><b><a href='kassespor.php?nysort=felt_2&sort=$sort&valg=$valg$hreftext'>".findtekst(936,$sprog_id)."</a></b></td>\n";
// #print "<td style=\"text-align:center;width:100px\"><b><a href='kassespor.php?nysort=felt_3&sort=$sort&valg=$valg$hreftext'>Betaling 2</a></b></td>\n";
// #print "<td style=\"text-align:center;width:100px\"><b><a href='kassespor.php?nysort=felt_4&sort=$sort&valg=$valg$hreftext'>Modtaget 2</a></b></td>\n";
// print "<td style=\"padding-top: 20px; text-align:right;width:100px\"><b>".findtekst(937,$sprog_id)."</a></b></td><td></td>\n";
// print "<td style=\"padding-top: 20px; text-align:right;width:100px\"><b>Rabat</b></td>\n";
// print "<td style=\"padding-top: 20px; text-align:right;width:100px\"><b>DG</b></td>\n";
// print "<td></td>\n";
// print "</tr>\n";


print "<tr>";

print "<td style=\"padding-top: 20px;width:30px\"><b><a href='kassespor.php?nysort=id&sort=$sort&valg=$valg$hreftext'>Status</b></td>\n";
print "<td style=\"padding-top: 20px;width:60px\"><b><a href='kassespor.php?nysort=id&sort=$sort&valg=$valg$hreftext'>Id</b></td>\n";
print "<th style='padding-top: 20px; width:110px; text-align:right'><b><a href='kassespor.php?nysort=fakturadate&sort=$sort&valg=$valg$hreftext'>" . findtekst(929, $sprog_id) . "</a></b></th>\n";
print "<th style='padding-top: 20px; width:50px; text-align:right'><b>" . findtekst(930, $sprog_id) . "</b></th>\n";
print "<th style='padding-top: 20px; width:110px; text-align:right'><b><a href='kassespor.php?nysort=fakturanr&sort=$sort&valg=$valg$hreftext'>" . findtekst(928, $sprog_id) . "</a></b></th>\n";
print "<th style='padding-top: 20px; width:50px; text-align:right'><b><a href='kassespor.php?nysort=felt_5&sort=$sort&valg=$valg$hreftext'>" . findtekst(931, $sprog_id) . "</a></b></th>\n";
print "<th style='padding-top: 20px; width:50px; text-align:right'><b><a href='kassespor.php?nysort=nr&sort=$sort&valg=$valg$hreftext'>" . findtekst(932, $sprog_id) . "</a></b></th>\n";
print "<th style='padding-top: 20px; width:100px; text-align:right'><b><a href='kassespor.php?nysort=ref&sort=$sort&valg=$valg$hreftext'>" . findtekst(933, $sprog_id) . "</a></b></th>\n";
print "<th style='padding-top: 20px; width:100px; text-align:right'><b><a href='kassespor.php?nysort=sum&sort=$sort&valg=$valg$hreftext'>" . findtekst(934, $sprog_id) . "</a></b></th>\n";
print "<th style='padding-top: 20px; width:100px; text-align:right'><b><a href='kassespor.php?nysort=felt_1&sort=$sort&valg=$valg$hreftext'>" . findtekst(935, $sprog_id) . "</a></b></th>\n";
print "<th style='padding-top: 20px; width:100px; text-align:right'><b><a href='kassespor.php?nysort=felt_2&sort=$sort&valg=$valg$hreftext'>" . findtekst(936, $sprog_id) . "</a></b></th>\n";
// print "<th style='padding-top: 20px; width:100px; text-align:right'><b>" . findtekst(937, $sprog_id) . "</b></th>\n";
// print "<th style='padding-top: 20px; width:100px; text-align:right'><b>" . findtekst(428, $sprog_id) . "</b></th>\n";

// print "<th style='padding-top: 20px; width:100px; text-align:right'><b>BA</b></th>\n";
print "<th style='padding-top: 20px; width:100px; text-align:right'><b>" . findtekst(937, $sprog_id) . "</b></th>\n";
if ($rabat_varenr && $vis_saet) {
    print "<th style='padding-top: 20px; width:100px; text-align:right'><b>Rabat</b></th>\n";
}
print "<th style='padding-top: 20px; width:100px; text-align:right'><b>BA</b></th>\n";
print "<th style='width:30px;'></th>\n";
print "</tr>\n";


print "<input type=hidden name=sort value=\"$sort\">";
#print "<input type=hidden name=nysort value=\"$nysort\">";
print "<input type=hidden name=kontoid value=\"$kontoid\">";
print "<input type=hidden name=start value=\"$start\">";
print "<tr>";
print "<th><span title= '".findtekst(1804, $sprog_id)." '>"; #20210805
print "<select class=\"inputbox\" style=\"text-align:right;width:40px;height:30px;font-size:12px\" name=\"status\">";
if ($status=='') {
	print "<option value=\"\"></option>"; 
	print "<option value=\"3\">3</option>"; 
	print "<option value=\"4\">4</option>";
} elseif ($status==3) {
	print "<option value=\"3\">3</option>"; 
	print "<option value=\"\"></option>"; 
	print "<option value=\"4\">4</option>"; 
} else {
	print "<option value=\"4\">4</option>"; 
	print "<option value=\"\"></option>"; 
	print "<option value=\"3\">3</option>";
}
print "</select></th>\n";
print "<th class='text-center'><span title= '".findtekst(1610, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:60px;height:30px;font-size:12px\" name=\"idnumre\" value=\"$idnumre\"></th>\n";#20210804
print "<th class='text-center'><span title= '".findtekst(1805, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:110px;height:30px;font-size:12px\" name=\"fakturadatoer\" value=\"$fakturadatoer\"></th>\n";
print "<th class='text-center'><span title= '".findtekst(1806, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;height:30px;font-size:12px\" name=\"logtimes\" value=\"$logtimes\"></th>\n";
#print "<td></td>\n";
print "<th class='text-center'><span title= '".findtekst(1807, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:110px;height:30px;font-size:12px\" name=\"fakturanumre\" value=\"$fakturanumre\"></th>\n";
print "<th class='text-center'><span title= '".findtekst(1808, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;height:30px;font-size:12px\" name=\"kasser\" value=\"$kasser\"</th>\n";
print "<th class='text-center'><span title= '".findtekst(1809, $sprog_id)."'><select class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;height:30px;font-size:12px\" name=\"borde\">\n";
if ($borde) print "<option>$borde</option>\n";
print "<option></option>\n";
for ($x=0;$x<count($bordnavn);$x++) {
	if ($bordnavn[$x] != $borde) print "<option value=\"$bordnavn[$x]\">$bordnavn[$x]</option>\n";
}
print "</select></th>\n";
print "<th class='text-center'><span title= '".findtekst(1810, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:150px;height:30px;font-size:12px\" name=\"refs\" value=\"$refs\"></th>\n";
print "<th class='text-center'><span title= '".findtekst(1617, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:30px;font-size:12px\" name=\"summer\" value=\"$summer\"></th>\n";
print "<th class='text-center'><span title= '".findtekst(1811, $sprog_id)."'><select class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:30px;font-size:12px\" name=\"betalinger\">";
if ($betalinger) print "<option>$betalinger</option>\n";
print "<option></option>\n";
for ($x=0;$x<count($bet_type);$x++) {
	if ($bet_type[$x] != $betalinger) print "<option>$bet_type[$x]</option>\n";
}
print "</select></th>\n";
print "<th class='text-center'><span title= '".findtekst(1812, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:30px;font-size:12px\" name=\"modtagelser\" value=\"$modtagelser\"></th>\n";
#print "<td align=center><span title= 'Angiv betalingform 2'><select class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:22px;font-size:12px\" name=\"betalinger2\">";
#if ($betalinger2) print "<option>$betalinger2</option>\n";
#print "<option></option>\n";
#for ($x=0;$x<count($bet_type);$x++) {
#	if ($bet_type[$x] != $betalinger2) print "<option>$bet_type[$x]</option>\n";
#}
#print "</select></td>\n";
#print "<td align=center><span title= 'Angiv et modtaget bel&oslash;b for betaling 2 eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:30px;font-size:12px\" name=\"modtagelser2\" value=\"$modtagelser2\"></td>\n";
#print "<td><br></td>\n";
print "<th></th><th width=5% class='text-center'><input class='button blue small' type=submit value=\"OK\" name=\"submit\"></th>\n";
print "</form></tr>\n";

if ($menu=='T') {
	print "</thead><tbody>";
} else {
	print "";
}


udskriv($fakturadatoer,$logtimes,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$summer,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$refs,$linjeantal,$start,'skriv',$borde,$status);
if ($menu=='T') {
	print "</tbody><tfoot>";
} else {
	print "<tr><td colspan=20><hr></td></tr>\n";
}
print "<tr><td colspan=\"9\" align=\"right\"><b>".dkdecimal($omsaet,2)."</b></td><td colspan=\"2\" align=\"right\"><b>".dkdecimal($modtaget,2)."</b></td><td colspan=\"1\" align=\"right\"><b>".dkdecimal($retursum,2)."</b></td><td></td></tr>"; 

print "<td align=\"right\"><b>".dkdecimal($modtaget,2)."</b></td>
<td align=\"right\"><b>".dkdecimal($total_discount,2)."</b></td>
<td align=\"right\"><b>".dkdecimal($total_gross_profit,2)."</b></td>
<td></td></tr>";

for ($z=0;$z<count($bet_type);$z++) {
	if ($bet_sum[$z]) print "<tr><td colspan=\"10\" align=\"right\"><b>$bet_type[$z]</b></td><td align=\"right\"><b>".dkdecimal($bet_sum[$z],2)."</b></td></tr>"; 
}
for ($z=0;$z<count($bet_type);$z++) {
	if (strtolower($bet_type[$z])=='kontant'){
		print "<tr><td colspan=\"10\" align=\"right\"><b>".findtekst(938,$sprog_id)."</b></td><td align=\"right\"><b>".dkdecimal($bet_sum[$z]-$retursum,2)."</b></td><td></td><td></td></tr>"; 
	}
}
####################################################################################

# $fakturadatoer,$logtimes,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$summer,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$refs,$linjeantal,$sort,$start+50,''
function udskriv($fakturadatoer,$logtimes,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$summer,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$refs,$linjeantal,$start,$skriv,$borde,$status) {

	global $bgcolor;
	global $bgcolor5;
	global $linjeantal;
	global $regnaar;
	global $bet_sum;
	global $bet_type;
	global $bordnavn;
	global $omsaet;
	global $modtaget;
	global $retursum;
    global $rabat_varenr, $vis_saet;
	$total_discount = 0;
    $total_gross_profit = 0;

	$linjebg=NULL;
	$y=0;
	$sort=str_replace('+',' ',$sort);
	
	# Check if the old system is in use.
	$r=db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__));
	if ($r[0]) {
		if ($borde && count($bordnavn)) {
			for ($x=0;$x<count($bordnavn);$x++) {
				if ($bordnavn[$x]==$borde) {
					$bordnr=$x;
				}
			}
		}
	} else {
		$r=db_fetch_array(db_select("select id from table_plan where name='$borde'",__FILE__ . " linje " . __LINE__));
		$bordnr = $r["id"];
	}

	if (!isset ($id)) $id = NULL;
	$id = (int)$id;

	$r = db_fetch_array(db_select("select box5 from grupper where art='DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__));
	if (strstr($r['box5'],';')) list($straksbogfor,$tmp)=explode(';',$r['box5']); # 20170419
	else $straksbogfor=$r['box5'];
	$udvaelg='';
	if (!$status) $status='3:4'; #20190404
	if ($status) $udvaelg=$udvaelg.udvaelg($status, 'ordrer.status', 'NR');
	if (trim($idnumre)) $udvaelg=$udvaelg.udvaelg($idnumre, 'ordrer.id', 'NR');
	if ($fakturanumre) $udvaelg=$udvaelg.udvaelg($fakturanumre, 'ordrer.fakturanr', 'NR');
	if ($betalinger) $udvaelg=$udvaelg.udvaelg($betalinger, 'ordrer.felt_1', '');
	if ($logtimes) $udvaelg=$udvaelg.udvaelg($logtimes, 'ordrer.tidspkt', 'TIME');
	if ($fakturadatoer) $udvaelg=$udvaelg.udvaelg($fakturadatoer, 'ordrer.fakturadate', 'DATO');
	if ($modtagelser) $udvaelg=$udvaelg.udvaelg($modtagelser, 'ordrer.felt_2', 'TEXT');
#	if ($modtagelser2) $udvaelg=$udvaelg.udvaelg($modtagelser2, 'ordrer.felt_4', 'TEXT');
	if ($summer) $udvaelg=$udvaelg.udvaelg($summer, 'ordrer.sum+ordrer.moms', 'BELOB');
#	if ($modtagelser) $udvaelg=$udvaelg.udvaelg($modtagelser, 'ordrer.felt_5', 'TEXT');
	if ($kasser) $udvaelg=$udvaelg.udvaelg($kasser, 'ordrer.felt_5','NR');
	if ($borde && ($bordnr || $bordnr=='0')) $udvaelg=$udvaelg.udvaelg($bordnr, 'ordrer.nr','NR');
	if ($refs) $udvaelg=$udvaelg.udvaelg($refs, 'ordrer.ref', '');
	$udvaelg=trim($udvaelg);
	if (substr($udvaelg,0,3)=='and') $udvaelg="where".substr($udvaelg, 3);
	if ($sort=="logdate") $sort = $sort.", logtime";
	if (!$udvaelg) $udvaelg="where fakturadate = '".date("Y-m-d")."' and"; #20160929 Tilføjet alt efter where
	else $udvaelg=$udvaelg." and";
	$x=0;
	$id=array();
	if ($straksbogfor) $qtxt="select * from ordrer $udvaelg art = 'PO' order by $sort limit 10000";
	else $qtxt="select * from ordrer $udvaelg (art = 'PO' or art like 'D%') order by $sort limit 10000";
	$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$ordrestatus[$x]=$r['status'];
		$id[$x]=$r['id'];
		$fakturadato[$x]=dkdato($r['fakturadate']);
		$tidspkt[$x]=substr($r['tidspkt'],-5);
		$fakturanr[$x]=$r['fakturanr'];
		$kasse[$x]=$r['felt_5'];
		$bord[$x]=$r['nr'];
		$ref[$x]=$r['ref'];
		$sum[$x]=$r['sum'];
		$moms[$x]=$r['moms'];
		$art[$x]=$r['art'];
		$dkksum[$x]=dkdecimal($sum[$x]+$moms[$x],2);
	

					$q_dg = db_fetch_array(db_select("
					SELECT
					COALESCE(SUM(pris * antal), 0) AS total_sales,
					COALESCE(SUM(
						CASE 
						WHEN LOWER(TRIM(COALESCE(rabatart, ''))) = 'amount' THEN rabat * antal
						ELSE (pris * rabat / 100) * antal
						END
					), 0) AS discount,
					COALESCE(SUM(kostpris * antal), 0) AS kostpris,
					COALESCE(SUM(
						(
						pris - 
						CASE 
							WHEN LOWER(TRIM(COALESCE(rabatart, ''))) = 'amount' THEN rabat
							ELSE (pris * rabat / 100)
						END
						- kostpris
						) * antal
					), 0) AS dg
					FROM ordrelinjer
					WHERE ordre_id = '{$id[$x]}'

			        ", __FILE__ . ' linje ' . __LINE__));


					$discount[$x] = $q_dg['discount'];
					$gross_profit[$x] = $q_dg['dg'];
					$kostpris[$x] = $q_dg['kostpris'];
                    $total_discount += $discount[$x];
				    $total_gross_profit += $gross_profit[$x];


		$x++;
	}
	for ($x=0;$x<count($id);$x++) {
		$udskriv=1;
		if (($x>=$start)&&($x<$start+$linjeantal) && ($udskriv)){
			$y++;
			if ($skriv) {
				$omsaet+=($sum[$x]+$moms[$x]);
				if ($linjebg!=$bgcolor) {$linjebg=$bgcolor; $color='#000000';}
				else {$linjebg=$bgcolor5; $color='#000000';}
				$y=0;
				$ordre_id=array();
				$qtxt="select * from pos_betalinger where ordre_id = '$id[$x]' order by betalingstype";
				$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);	
				while ($r2=db_fetch_array($q2)) {
					if (!$y) {
						$betalt=0;
					}
					if (!is_numeric($r2['betalingstype'])) {
						$pos_bet_id[$y]=$r2['id'];
						$ordre_id[$y]=$r2['ordre_id'];
						$amount[$y]=$r2['amount'];
						$modtaget+=$amount[$y];
						$betalt+=$amount[$y];
						$betalingstype[$y]=$r2['betalingstype'];
						for ($z=0;$z<count($bet_type);$z++) {
							if (strtolower($bet_type[$z])==strtolower($betalingstype[$y])) $bet_sum[$z]+=$amount[$y];
						}
						$y++;
					}
				}
				for ($y=0;$y<count($ordre_id);$y++) {
					print "<tr bgcolor=\"$linjebg\">";
					if (!isset($ordre_id[$y-1]) || $ordre_id[$y]!=$ordre_id[$y-1]) {
						print "<td>$ordrestatus[$x]</td>";
						print "<td align=right><a href=";
						if ($art[$x]=='PO') print "pos_";
						print "ordre.php?id=$id[$x]&returside=kassespor.php>$id[$x]</a></span><br></td>\n";
						print "<td align=right>$fakturadato[$x]<br></td>\n";
						print "<td align=right>". str_replace(":",".",$tidspkt[$x]) ."<br></td>\n";
						print "<td align=right>$fakturanr[$x]<br></td>\n";
						print "<td align=right>$kasse[$x]<br></td>\n";
						
						# Check if the old system is in use.
						$r=db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__));
						if ($r[0]) {
							$bordarr = explode(chr(9), str_replace("\n", "  ", $r[0]));
						} else {
							$bordarr = array();
						}

						# Old system
						if (count($bordarr) != 0) {
							$txt = $bordnavn[$bord[$x]];
						# New system
						} else {
							if ($bord[$x] == "") {
								$txt="";
							} else {
								$r=db_fetch_array(db_select("select name from table_plan where id = $bord[$x]",__FILE__ . " linje " . __LINE__));
								$txt = $r[0];
							}
						}

						print "<td align=right>".$txt."<br></td>\n";
						print "<td align=right>$ref[$x]<br></td>\n";
						print "<td align=right>$dkksum[$x]<br></td>\n";
					} else {
						print "<td colspan='9'></td>\n";
					}
					print "<td align=right>";
					if ($ordrestatus[$x]=='3') {
						print "<a href='kassespor.php?ret_bet_id=$pos_bet_id[$y]&bon=$fakturanr[$x]&sum=$amount[$y]&betal_type=$betalingstype[$y]'>$betalingstype[$y]</a>";
					} else print "$betalingstype[$y]";
					print "<br></td>\n";
					print "<td align=right>".dkdecimal($amount[$y],2)."<br></td>\n";
					$retur=$betalt-($sum[$x]+$moms[$x]);
					if (!isset($ordre_id[$y-1]) || $ordre_id[$y]!=$ordre_id[$y-1]) {
						print "<td align=right>".dkdecimal($retur,2)."<br></td>\n";
						$retursum+=$retur;
	
						$q_dg = db_fetch_array(db_select("
						SELECT
						COALESCE(SUM(pris * antal), 0) AS total_sales,
						COALESCE(SUM(
							CASE 
							WHEN LOWER(TRIM(COALESCE(rabatart, ''))) = 'amount' THEN rabat * antal
							ELSE (pris * rabat / 100) * antal
							END
						), 0) AS discount,
						COALESCE(SUM(kostpris * antal), 0) AS kostpris,
						COALESCE(SUM(
							(
							pris - 
							CASE 
								WHEN LOWER(TRIM(COALESCE(rabatart, ''))) = 'amount' THEN rabat
								ELSE (pris * rabat / 100)
							END
							- kostpris
							) * antal
						), 0) AS dg
						FROM ordrelinjer
						WHERE ordre_id = '{$id[$x]}'
						", __FILE__ . ' linje ' . __LINE__));

						$discount_val = $q_dg['discount'];	
						$gross_profit_val = $q_dg['dg'];
						
						if ($rabat_varenr && $vis_saet) {
							$q_rabat = db_fetch_array(db_select(
							"SELECT lev_varenr FROM ordrelinjer WHERE ordre_id = '{$id[$x]}' AND TRIM(UPPER(varenr)) = TRIM(UPPER('$rabat_varenr')) ORDER BY id ASC LIMIT 1",
							__FILE__ . " linje " . __LINE__
							));
						$rabatrabat = '0,00';
						if (isset($q_rabat['lev_varenr']) && $q_rabat['lev_varenr'] !== '') {
							$parts = explode('|', $q_rabat['lev_varenr']);
							$first = isset($parts[0]) ? $parts[0] : '';
							$first = ltrim($first, '-');
							if (is_numeric($first) && $first !== '') {
								$rabatrabat = number_format($first, 2, ',', '.');
							}
						}
						print "<td align=right>" . $rabatrabat . "</td>\n";
						} else {
							print "<td align=right><br></td>\n";
						}
						$net_sales = $q_dg['total_sales'] - $q_dg['discount'];
						if ($net_sales != 0) {
							$dg_percent = ($q_dg['dg'] / $net_sales) * 100;
						} else {
							$dg_percent = 0;
						}
						print "<td align=right>" . dkdecimal($dg_percent, 2) . " %</td>\n";
							

				        } else  {
						print "<td align=right><br></td>\n";
						}
					    print "<td></td>";
				}
			}
		}
	}
if (!isset ($y)) $y = NULL;
	return ($y);
} #endfunction udskriv()
?>

<?php

if ($menu=='T') {
	print "</tfoor></table></div>";
	include_once '../includes/topmenu/footer.php';
} else {
	print "
	</tbody>
	</table>
		</td></tr>
	</tbody></table>";
	include_once '../includes/oldDesign/footer.php';
	print "</body></html>";
}

?>
