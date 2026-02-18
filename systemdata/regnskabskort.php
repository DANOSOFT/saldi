<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/regnskabskort.php --- lap 4.1.1 -- 2025-07-02 --
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
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------------
// 2013.02.10 Break ændret til break 1
// 2015-01-02 Tilrettet til dynamisk lagerværdi. Søg find_lagervaerdi
// 20150327 CA  Topmenudesign tilføjet søg 20150327
// 20170516 Div oprydning samt tilføjelse af laas_lager Søg laas
// 20190221 MSC - Rettet topmenu design og isset fejl
// 20190225 MSC - Rettet topmenu design
// 20210101 PHR - Various changes and cleanup. Accounting now alloewd by default.
// 20210709 LOE - Translated these texts
// 20220103 PHR - Made some cleanup, set it to save twice and set year active when new year created.
// 20220614 MSC - Implementing new design
// 20230609 PHR - php8
// 20231230 PHR - Added individual groups for each year.
// 20250522	PHR	- (int)$id
// 20250702 PHR - PHP8

@session_start();
$s_id=session_id();

$aut_lager=$aaben=$beskrivelse=$laast=$setFiscialYear=NULL;

$modulnr=2;
$title="Regnskabskort";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/genberegn.php");
include("../includes/topline_settings.php");

print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";
if ($menu=='T') {
		#	print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">";
		print "<div class=\"headerbtnLft headLink\"><a href=regnskabsaar.php accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst('30|Tilbage', $sprog_id)."</a></div>";
		print "<div class=\"headerTxt\">$title</div>";
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
		print "</div>";
		print "<div class='content-noside'>";
		print "<div id=\"leftmenuholder\">";
		include_once 'left_menu.php';
		print "</div><!-- end of leftmenuholder -->\n";
		print "<div class=\"maincontentLargeHolder\">\n";
		print "<div class='divSys'>";
		print "<table class='dataTableSys' cellpadding=0 cellspacing=0 border=1 width='100%' bordercolor='$bgcolor5'><tbody>\n"; ############################	##table 3b start
	} else {
		include("top.php");
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
		print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>"; ####################table 1a start.
		print "<tr><td align='center' valign=top>";
		print "<a href=\"javascript:confirmClose('regnskabsaar.php','". findtekst('154|Dine ændringer er ikke blevet gemt! Tryk OK for at forlade siden uden at gemme.', $sprog_id) ."')\">";
		if ($menu=='S') {
			print "<button style = '$buttonStyle; width: 10%' onMouseOver=\"this.style.cursor='pointer'\">";
		} else {
			print "<button style = 'width: 80px;'>";
		}
		print findtekst('2172|Luk', $sprog_id)."</button></a>";
		print "<table width=100% align='center' border=0 cellspacing=4 cellpadding=0><tbody>\n"; ##############table 2b start
		print "<tr>\n";
		print "</tbody></table>\n"; #####################################################table 2b slut.
		print "</td></tr>\n";
		print "<tr>\n";
		print "<td align = center valign = center>";
		print "<table class='dataTable2' cellpadding=0 cellspacing=0 border=1><tbody>\n"; ############################	##table 3b start
	}

$id=if_isset($_GET['id']);

if ($_POST) {
	$id = (int)if_isset($_POST['id'],0);
	$beskrivelse = if_isset($_POST['beskrivelse']);
	$kodenr = if_isset($_POST['kodenr']);
	$kode=if_isset($_POST['kode']);
	$startmd = if_isset($_POST['startmd']);
	$startaar = if_isset($_POST['startaar']);
	$slutmd = if_isset($_POST['slutmd']);
	$slutaar = if_isset($_POST['slutaar']);
	$aaben=trim($_POST['aaben']);
	$fakt=if_isset($_POST['fakt'],0);
	$modt=if_isset($_POST['modt'],0);
	$no_faktbill=trim(if_isset($_POST['no_faktbill']));
	$faktbill=trim(if_isset($_POST['faktbill']));
	$modtbill=trim(if_isset($_POST['modtbill']));
	$kontoantal = if_isset($_POST['kontoantal']);
	$kontonr = if_isset($_POST['kontonr']);
	$debet=if_isset($_POST['debet']);
	$kredit=if_isset($_POST['kredit']);
	$saldo = if_isset($_POST['saldo']);
	$overfor_til = if_isset($_POST['overfor_til']);
#		$primotal = if_isset($_POST['primotal']);
	$aar=date("Y");
	$topaar=$aar+10;
	$bundaar=$aar-20;
	$fejl=0;
	$startmd=$startmd*1;
	$startaar=$startaar*1;
	$slutmd=$slutmd*1;
	$slutaar=$slutaar*1;

	if (!$beskrivelse){
		$beskrivelse = $startaar;
		if ($startaar != $slutaar) $beskrivelse.= "/".$slutaar;
	}
	if (($startmd<1)||($startmd>12)){
		alert(findtekst('2476|Startmåned', $sprog_id)." ".strtolower(findtekst('2480|Skal være mellem', $sprog_id))." 1 ".strtolower(findtekst('2481|Og', $sprog_id))." 12!");
		$startmd="";
	}
	elseif ($startmd<10) $startmd="0".$startmd;
	if (($slutmd<1)||($slutmd>12)){
		alert(findtekst('2477|Slutmåned', $sprog_id)." ".strtolower(findtekst('2480|Skal være mellem', $sprog_id))." 1 ".strtolower(findtekst('2481|Og', $sprog_id))." 12!");
		$slutmd="";
	}
	elseif ($slutmd<10) $slutmd="0".$slutmd;
	if (($startaar<$bundaar)||($startaar>$topaar)){
		alert(findtekst('2478|Startår', $sprog_id)." ".strtolower(findtekst('2480|Skal være mellem', $sprog_id))." $bundaar ".strtolower(findtekst('2481|Og', $sprog_id))." $topaar!");
		$startaar="";
	}
	if (($slutaar<$bundaar)||($slutaar>$topaar)){
		alert(findtekst('2479|Slutår', $sprog_id)." ".strtolower(findtekst('2480|Skal være mellem', $sprog_id))." $bundaar ".strtolower(findtekst('2481|Og', $sprog_id))." $topaar!");
		$slutaar="";
	}
	$startdato=$startaar.$startmd;
	$slutdato=$slutaar.$slutmd;
	if ($slutdato<=$startdato){
		alert(findtekst('2482|Regnskabsår skal slutte senere end det starter', $sprog_id));
		$aaben="";
	}
	$qtxt = "select id from grupper where kodenr = '$kodenr' and art = 'RA'";
	if ((($id!=0)||(!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))) &&
	(($startmd)&&($slutmd)&&($startdato)&&($slutdato)&&($startaar)&&($slutaar)&&($beskrivelse))) {
		transaktion ("begin");
		($id == 0)?$nn=2:$nn=1;
		for($n=0;$n<$nn;$n++) { // 1. time it must be rerun to catch and update last year's result.
		if ($id == 0){
			$qtxt = "insert into grupper (beskrivelse,kodenr,kode,art,box1,box2,box3,box4,box5)";
			$qtxt.= " values ";
			$qtxt.= "('".db_escape_string($beskrivelse)."','$kodenr','$kode','RA','$startmd',";
			$qtxt.= "'$startaar','$slutmd','$slutaar','$aaben')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "select id from grupper where kodenr = '$kodenr' and art = 'RA'";
			$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$id = $row['id'];
			$setFiscialYear=1;
		}
		if ($n == 0) {
			$qtxt = "select id from grupper where fiscal_year = '$kodenr' and art != 'RA'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$tmp =$kodenr - 1;
				$qtxt = "select * from grupper where fiscal_year > '0' and fiscal_year = '$tmp' and art != 'RA'";
				$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$qtxt = "CREATE TEMP TABLE tmp (like grupper)";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "INSERT INTO tmp SELECT * FROM grupper WHERE id = '$r[id]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "UPDATE tmp SET id = nextval('grupper_id_seq'), fiscal_year = '$kodenr'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "INSERT INTO grupper SELECT * from tmp";
					db_modify("INSERT INTO grupper SELECT * from tmp",__FILE__ . " linje " . __LINE__);
					db_modify("DROP TABLE tmp",__FILE__ . " linje " . __LINE__);
				}
			}
		}
		if ($kodenr==1) {
			for ($x=1; $x<=$kontoantal; $x++) {
				$sum=0;
				if ($debet[$x]) $sum+=usdecimal($debet[$x]);
				if ($kredit[$x]) $sum-=usdecimal($kredit[$x]);
				$qtxt = "update kontoplan set primo='$sum' where kontonr='$kontonr[$x]' and regnskabsaar='1'";
				db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
			}
			if (db_fetch_array(db_select("select * from grupper where art = 'RB'",__FILE__ . " linje " . __LINE__))) {
				$qtxt = "update grupper set box1 = '$fakt', box2 = '$modt', box3 = '$faktbill', box4 = '$modtbill', box5 = '$no_faktbill' ";
				$qtxt.= "where art = 'RB'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} else {
				$qtxt = "insert into grupper (beskrivelse,kodenr,kode,art,box1,box2,box3,box4,box5) values ";
				$qtxt.= "('Regnskabsbilag','1','1','RB','$fakt','$modt','$faktbill','$modtbill','$no_faktbill')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		if ($id>0 && $kodenr>0) {
			$qtxt = "select kodenr from grupper where id = '$id' and art = 'RA'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$preNo = $r['kodenr'] -1;
			if ($preNo) {
				$qtxt = "select box10 from grupper where art = 'RA' and kodenr = '$preNo'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				($r['box10'] == 'on')?$preDeleted = 1:$preDeleted = 0;
			}
			$qtxt = "update grupper set beskrivelse = '".db_escape_string($beskrivelse)."', kodenr = '$kodenr', kode = '$kode', ";
			$qtxt.= "box1 = '$startmd', box2 = '$startaar', box3 = '$slutmd', box4 = '$slutaar', box5 = '$aaben' where id = '$id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			if ($kodenr==1 || $preDeleted){
				for ($x=1; $x<=$kontoantal; $x++) {
					if ($saldo[$x] && $overfor_til[$x]) {
						$qtxt = "update kontoplan set primo=primo+$saldo[$x],overfor_til=$overfor_til[$x] where ";
						$qtxt = "kontonr='$kontonr[$x]' and regnskabsaar=$kodenr";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
			} else {
				$qtxt = "select id from kontoplan where regnskabsaar = '$kodenr'";
				$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				if($row = db_fetch_array($query)) {
					$qtxt = "update kontoplan set primo='0' where  regnskabsaar = '$kodenr'";
					db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
					for ($x=0; $x<=$kontoantal; $x++) {
						$overfor_til[$x]=(int)$overfor_til[$x];
						$kontonr[$x]=$kontonr[$x]*1;
						if ($overfor_til[$x]) {
							$saldo[$x]=(float)$saldo[$x]; #phr 20110605
							$qtxt = "update kontoplan set overfor_til='$overfor_til[$x]' ";
							$qtxt.= "where kontonr='$kontonr[$x]' and (regnskabsaar=$kodenr-1 or regnskabsaar='$kodenr')";
							db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
							$qtxt = "update kontoplan set primo=primo+'$saldo[$x]' where kontonr='$overfor_til[$x]' and regnskabsaar=$kodenr";
							db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
						}
					}
				} else {
					$qtxt = "select * from kontoplan where regnskabsaar=$kodenr-1 order by kontonr";
					$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
					$y=0;
					while ($row = db_fetch_array($query)) {
						if ($row['kontotype']=="S") {
						$belob=$row['saldo'];
						} else $belob='0';
						if (!$belob) $belob='0';
						if (!$row['fra_kto']) $row['fra_kto']='0';
						if (!$row['til_kto']) $row['til_kto']='0';
						if (!$row['overfor_til']) $row['overfor_til']='0';
						$qtxt = "insert into kontoplan ";
						$qtxt.= "(kontonr,beskrivelse,kontotype,moms,fra_kto,til_kto,lukket,";
						$qtxt.= "primo,regnskabsaar,overfor_til,genvej,valuta)";
						$qtxt.= " values ";
						$qtxt.= "('$row[kontonr]','".db_escape_string($row['beskrivelse'])."','$row[kontotype]','$row[moms]',";
						$qtxt.= "'$row[fra_kto]','$row[til_kto]','$row[lukket]','$belob','$kodenr','$row[overfor_til]',";
						$qtxt.= "'$row[genvej]','". (int)$row['valuta'] ."')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
			}
		}
		if (isset($_POST['laas_lager']) && $_POST['laas_lager']) {
			$fra=$startaar."-".$startmd."01";
			$til=usdate("31-".$slutmd."-".$slutaar);
			print "<meta http-equiv=\"refresh\" content=\"1;URL=laas_lager.php?fra=$fra&til=$til\">";

		}
		}
		transaktion("commit");
	}
}
if ($setFiscialYear) {
	include("../includes/connect.php");
	db_modify("update online set regnskabsaar = '$kodenr' where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
	if ($revisor) {
		$qtxt = "update revisor set regnskabsaar = '$kodenr' where brugernavn = '$brugernavn' and db_id='$db_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	include("../includes/online.php");
	if (!$revisor) db_modify("update brugere set regnskabsaar = '$kodenr' where id = '$bruger_id'",__FILE__ . " linje " . __LINE__);
}

if ($id > 0) {
	$qtxt = "select kodenr from grupper where id = '$id' and art = 'RA'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$preNo = (int)$r['kodenr'];
	if ($preNo) {
		$qtxt = "select box10 from grupper where art = 'RA' and kodenr = '$preNo'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		($r['box10'] == 'on')?$preDeleted = 1:$preDeleted = 0;
	}

	$query = db_select("select * from grupper where id = '$id' and art = 'RA'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		genberegn($row['kodenr']);
		if ($row['kodenr']==1) {
			include_once("fiscalYearInc/year1.php");
			year1($row['id'], $row['kodenr'], $row['beskrivelse'], $row['box1'], $row['box2'], $row['box3'], $row['box4'], $row['box5'],'');
		} elseif ($preDeleted) {
			include_once("fiscalYearInc/yearX1.php");
			yearX1($row['id'], $row['kodenr'], $row['beskrivelse'], $row['box1'], $row['box2'], $row['box3'], $row['box4'], $row['box5'],$row['box9']);
		} else {
			include_once("fiscalYearInc/yearX.php");
			yearX($row['id'], $row['kodenr'], $row['beskrivelse'], $row['box1'], $row['box2'], $row['box3'], $row['box4'], $row['box5'],$row['box9']);
		}
	}
} else {
#	Print "<BODY onLoad=\"javascript:alert('$tekst')\">";
#	print "<BODY onLoad=\"javascript:velkommen=window.open('velkommen.html','velkommen','".$jsvars."';) velkommen.focus();\";"
	print "<BODY onLoad=\"javascript:docChange = true;\">";
	$x=0;
	$q = db_select("select * from grupper where art = 'RA' order by kodenr desc",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($x <= $r['kodenr']) $x = $r['kodenr'];
	}
	$query = db_select("select * from grupper where art = 'RA' and kodenr='$x'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$x++;
		if ($row['box3']==12) {
			$startmd  = 1;
			$startaar = $row['box4']+1;
		} else {
			$startmd  = $row['box3']+1;
			$startaar = $row['box4'];
		}
		$slutmd  = $row['box3'];
		$slutaar = $row['box4']+1;
		$deleted = $row['box10'];
	} else {
		$beskrivelse = date('Y');
		$startaar    = date('Y');
		$startmd     = '01';
		$slutaar     = date('Y');
		$slutmd      = '12';
		$aaben       = 'on';
	}

	if ($x==0) year1($id, 1, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben,$aut_lager);
	else {
		include_once("fiscalYearInc/yearX.php");
		yearX($id, $x, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben,$aut_lager);
	}
}

######################################################################################################################
?>