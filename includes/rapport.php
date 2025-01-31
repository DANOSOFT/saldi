<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------lager/rapport.php------------patch 3.9.0-------2020.04.08----
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 2013.02.10 Break ændret til break 1
// 2013.03.18 $modulnr ændret fra 12  til 15
// 2013.08.27 Større omskrivning for bedre datovalg og detaljeringsgrad 
// 2014.11.05 Større omskrivning
// 2015.01.19 Kostpriser på varelinjer er forkert i ordrer < 010115 med anden valuta en DKK. 
//		Derfor findes kostpriser med ordrefunc funktionen find_kostpris. Søg "/*find_kostpris*/" 
// 2015.09.03 Lagerregulering kom ikke med ved tilgang. Tilføjet $bk_id & $bs_id samt ændret ordre_id til hhv. $k_ordre_id/$s_ordre_id & 
// antal til hhv. $k_antal/$s_antal. 
// 2015.11.05 lagerreguleringer blev trukket fra i til-/afgang. Skal lægges til. #20151105  
// 2015.11.06 Ikke lagerførte samlevarer (sæt) medtages nu ikke da disse er repræsenteret af de varer som indgår i sættet. #20151106  
// 2015.12.10 Kostpriser blev altid trukket fra varekort - se også find_kostpriser i ordrefunc.php #21051210
// 2016.02.01 # Flyttet db *-1 under sammentælling da sammentælling af db blev forkert ved negativt salg.
// 2016.04.18 Tilføjet selection på afdeling. Søg $afd
// 2016.04.18 Negatativ regulering blev vist uden fortegn. #20160418
// 2016.08.04 Datofejl v visning af detaljeret regulering da $fakturadate blev nullet ved inden søgning efter både køb & salg
// 2016.08.24	PHR $x erstattes af $f da $x blev brugt i 'for løkken' 20160824
// 2016.08.26 PHR tilføjet $r[antal]*0 i "if" linje for at udelukke rabatter.
// 2017.01.14 PHR Order by .... id rettet til Order by .... batch_salg.id da .id er 'ambiguous'
// 2017.04.19 PHR Flyttet sammentælling af reguleringssum "en tuborg" ned da negativ regulering ikke kom med. 
// 2017.05.02 PHR Sammentælling af reguleringssum viste hele beholdningen.
// 2017.05.02 PHR Tilføjet selection på sælger. Søg $ref
// 2017.08.01 PHR Tilføjet selection på leverandør. Søg $lev
// 2018.09.28 PHR Inddeling efter grupper når detajler er fravalgt.
// 2018.10.03 PHR Fjernet "batch_kob.fakturadate>='$date_from' and " da beholdninger viste fejl. 20181003
// 2019.01.18 MSC - Fjernet isset fejl
// 2019.02.20 MSC - Rettet topmenu design
// 2019.06.04 PHR - Added CSV and stock in 'kun salg'
// 2019.08.30 PHR - Added 'afrund' to $t_s_pris to avoid division by almost zero which makes crazy numbers 20190830
// 2019.10.31 PHR	- Minor design update look for '$trbg'
// 2020.03.15 PHR - Added minmaxrepport (execlusive for bizsys_49)
// 2020.04.08 PHR	- Added Vat to summary repport

	@session_start();
	$s_id=session_id();
	$css="../css/standard.css";
 
	$title="Varerapport";
	$modulnr=15;

	$vk_kost=NULL;
	
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	include("../includes/forfaldsdag.php");
	include("../includes/ordrefunc.php");
#	include("../includes/db_query.php");

	if (!isset ($_GET['detaljer'])) $_GET['detaljer'] = NULL;
	if (!isset ($_GET['kun_salg'])) $_GET['kun_salg'] = NULL;
	if (!isset ($_GET['lagertal'])) $_GET['lagertal'] = NULL;

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

$lokMinMax=if_isset($_POST['lokMinMax']);
if ($lokMinMax) {
	$varegruppe = trim($_POST['varegruppe']);
	$varenr     = if_isset($_POST['varenr']);
	$varenavn   = if_isset($_POST['varenavn']);
	$afd        = if_isset($_POST['afd']);
	print print "<meta http-equiv=\"refresh\" content=\"0;URL=minmaxstock.php?vgrp=$varegruppe&vnr=$varenr&vname=$varenavn&afd=$afd\">";
}
if (isset($_POST['submit']) && $_POST['submit']) {
	$submit=strtolower(trim($_POST['submit']));
	$varegruppe=trim($_POST['varegruppe']);
	$afd=if_isset($_POST['afd']);
	$ref=if_isset($_POST['ref']);
	$lev=if_isset($_POST['lev']);
	$date_from=usdate($_POST['dato_fra']);
	$date_to=usdate($_POST['dato_til']);
#	$md=$_POST['md'];
	$varenr = if_isset($_POST['varenr']);
	$varenavn = if_isset($_POST['varenavn']);
	$detaljer = if_isset($_POST['detaljer']);
	$kun_salg = if_isset($_POST['kun_salg']);
	$lagertal = if_isset($_POST['lagertal']);	
	$vk_kost = if_isset($_POST['vk_kost']);
	$varenr = trim($varenr);
	$varenavn = trim($varenavn);
} else {
	$varegruppe=if_isset($_GET['varegruppe']);
	$afd=if_isset($_GET['afd']);
	$ref=if_isset($_GET['ref']);
	$lev=if_isset($_GET['lev']);
	$date_from=if_isset($_GET['date_from']);
	$date_to=if_isset($_GET['date_to']);
	$varenr=if_isset($_GET['varenr']);
	$varenavn=if_isset($_GET['varenavn']);
	$detaljer = $_GET['detaljer'];
	$kun_salg = $_GET['kun_salg'];
	$lagertal = $_GET['lagertal'];
	$submit=if_isset($_GET['submit']);
}
#$md[1]="januar"; $md[2]="februar"; $md[3]="marts"; $md[4]="april"; $md[5]="maj"; $md[6]="juni"; $md[7]="juli"; $md[8]="august"; $md[9]="september"; $md[10]="oktober"; $md[11]="november"; $md[12]="december";

#if (strstr($varegruppe, "ben post")) {$varegruppe="openpost";}
#cho "$date_from, $date_to, $varenr, $varenavn, $varegruppe,$detaljer<br>";
if ($submit == 'ok') varegruppe ($date_from, $date_to, $varenr, $varenavn, $varegruppe,$detaljer,$kun_salg,$lagertal,$vk_kost,$afd,$lev,$ref); 
elseif ($submit == 'lagerstatus') print print "<meta http-equiv=\"refresh\" content=\"0;URL=lagerstatus.php?varegruppe=$varegruppe\">";
elseif (strpos($submit,'ageropt')) print print "<meta http-equiv=\"refresh\" content=\"0;URL=optalling.php?varegruppe=$varegruppe\">";
else 	forside ($date_from,$date_to,$varenr,$varenavn,$varegruppe,$detaljer,$kun_salg,$lagertal,$vk_kost,$afd,$lev,$ref);
#cho "$submit($regnaar, $date_from, $date_to, $varenr, $varenavn, $varegruppe)";

#############################################################################################################
function forside($date_from,$date_to,$varenr,$varenavn,$varegruppe,$detaljer,$kun_salg,$lagertal,$vk_kost,$afd,$lev,$ref) {

	#global $connection;
	global $bgcolor,$bgcolor5,$brugernavn;
	global $db,$jsvars,$md,$menu;
	global $popup,$returside,$top_bund;

#	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk
	($date_from)?$dato_fra=dkdato($date_from):$dato_fra="01-01-".date("Y");
	($date_to)?$dato_til=dkdato($date_to):$dato_til=date("d-m-Y");
	if (!$varenr) $varenr="*";
	if (!$varenavn) $varenavn="*";
	if ($detaljer) $detaljer='checked';
	if ($kun_salg) $kun_salg='checked';
	if ($lagertal) $lagertal='checked';	

	if (!isset ($l_id)) $l_id = NULL;

	$l_id = array();
	
#	if (!$regnaar) {
#		$query = db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
#		$row = db_fetch_array($query);
#		$regnaar = $row['regnskabsaar'];
#	}
#	$query = db_select("select * from grupper where art = 'RA' order by box2",__FILE__ . " linje " . __LINE__);
#	$x=0;
#	while ($row = db_fetch_array($query)){
#		$x++;
#		$regnaar_id[$x]=$row['id'];
#		$regn_beskrivelse[$x]=$row['beskrivelse'];
#		$start_md[$x]=$row['box1']*1;
#		$start_aar[$x]=$row['box2']*1;
#		$slut_md[$x]=$row['box3']*1;
#		$slut_aar[$x]=$row['box4']*1;
#		$regn_kode[$x]=$row['kodenr'];
#		if ($regnaar==$row['kodenr']){$aktiv=$x;}
#	}
#	$antal_regnaar=$x;
	if ($menu=='T') {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\"> 
				<div class=\"headerbtnLft\"></div>
				<span class=\"headerTxt\"></span>";     
		print "<div class=\"headerbtnRght\"></div>";       
		print "</div><!-- end of header -->
			<div class=\"maincontentLargeHolder\">\n";
	} else {
		print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" align=\"center\"><tbody>"; #A
		print "<tr><td width=100%>";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a href=$returside accesskey=L>Luk</a></td>";
		print "<td width=\"80%\" $top_bund>Varerapport - forside</td>";
		print "<td width=\"10%\" $top_bund><a href='../utils/batch_salg_rabat.php?bogfor=0&md=8' target='blank'>|</a></td></tr>";
		print "</tbody></table></td></tr>"; #B slut
		print "</tr><tr><td height=\"60%\" \"width=100%\" align=\"center\" valign=\"bottom\">";
	}
#	print "<form name=regnskabsaar action=rapport.php method=post>";
#	print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\"><tbody>";
#	print "<tr><td align=center><h3>Rapporter<br></h3></td></tr>";
#	print "<tr><td align=center><table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=\"100%\"><tbody>";
#	print "<tr><td> Regnskabs&aring;r</td><td width=100><select class=\"inputbox\" name=regnaar>";
#	print "<option>$regnaar - $regn_beskrivelse[$aktiv]</option>";
#	for ($x=1; $x<=$antal_regnaar;$x++) {
#		if ($x!=$aktiv) {print "<option>$regn_kode[$x] - $regn_beskrivelse[$x]</option>";}
#	}
	
#	print "</td><td width=100 align=center><input type=submit value=\"Opdat&eacute;r\" name=\"submit\"></td>";
#	print "</form>";
	$vg_nr[0]='0';
	$vg_navn[0]='Alle';
	$x=1;
	$q = db_select("select * from grupper where art = 'VG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		$vg_nr[$x]=$r['kodenr'];
		$vg_navn[$x]=$r['beskrivelse'];
		$vg_lagerfort=$r['box8'];
		$x++;
	}
	$afd_nr[0]='0';
	$afd_navn[0]='Alle';
	$x=1;
	$q = db_select("select * from grupper where art = 'AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$afd_nr[$x]=$r['kodenr'];
		$afd_navn[$x]=$r['beskrivelse'];
		$x++;
	}
	$lev_id[0]='0';
	$lev_nr[0]='0';
	$lev_navn[0]='Alle';
	$x=0;
	$q = db_select("select distinct(lev_id) from vare_lev",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$l_id[$x]=$r['lev_id'];
		$x++;
	}
	$x=1;
	$q = db_select("select * from adresser where art = 'K' order by firmanavn",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (in_array($r['id'],$l_id)) {
			$lev_id[$x]=$r['id'];
			$lev_nr[$x]=$r['kontonr'];
			$lev_navn[$x]=$r['firmanavn'];
			$x++;
		}
	}
	$ref_nr[0]='0';
	$ref_navn[0]='Alle';
	$x=1;
	$q = db_select("select * from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ref_nr[$x]=$r['id'];
		$ref_brugernavn[$x]=$r['brugernavn'];
		$ref_ansat[$x]=$r['ansat_id'];
		$ref_navn[$x]=NULL;
		$x++;
	} 
	for ($x=1;$x<count($ref_nr);$x++) {
		if ($ref_ansat[$x]) {
			$r = db_fetch_array(db_select("select navn from ansatte where id=$ref_ansat[$x]",__FILE__ . " linje " . __LINE__));
			$ref_navn[$x]=$r['navn'];
		}  
		if (!$ref_navn[$x]) $ref_navn[$x]=$ref_brugernavn[$x];
	}
	$trbg=$bgcolor;
	print "<form name=rapport action=rapport.php method=post>";
	print "<table  bgcolor='$bgcolor' class='dataTable2' cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	print "<tr><td align=\"center\" colspan=\"3\"><h3>Varerapport<br></h3></td></tr>";
	($trbg==$bgcolor)?$trbg=$bgcolor5:$trbg=$bgcolor;
	print "<tr bgcolor='$trbg'><td>Varegruppe</td><td colspan=\"2\"><select class=\"inputbox\" name=\"varegruppe\" style=\"width:200px;\">";
	for ($x=0;$x<count($vg_nr);$x++) {
		if ($varegruppe == $vg_nr[$x]) print "<option value=$vg_nr[$x]>$vg_nr[$x] : $vg_navn[$x]</option>";
 	}
	for ($x=0;$x<count($vg_nr);$x++) {
		if ($varegruppe != $vg_nr[$x]) print "<option value=$vg_nr[$x]>$vg_nr[$x] : $vg_navn[$x]</option>";
 	}
	print "</select>";
	print "<!--Kostpris fra varekort --><input type=\"hidden\" name=\"vk_kost\" value=\"$vk_kost\">";
	print "</td></tr>";
	if (count($lev_id)>1) {
		($trbg==$bgcolor)?$trbg=$bgcolor5:$trbg=$bgcolor;
		print "<tr bgcolor='$trbg'><td> Leverandør </td><td colspan=\"2\"><select class=\"inputbox\" name=\"lev\" style=\"width:200px;\">";
		for ($x=0;$x<count($lev_id);$x++) {
			if ($lev == $lev_id[$x]) print "<option value='$lev_id[$x]'>$lev_navn[$x]</option>";
		}
		for ($x=0;$x<count($lev_id);$x++) {
			if ($lev != $lev_id[$x]) print "<option value='$lev_id[$x]'>$lev_nr[$x] : $lev_navn[$x]</option>";
		}
		print "</select></td></tr>";
	}
	if (count($afd_nr)>1) { 
		($trbg==$bgcolor)?$trbg=$bgcolor5:$trbg=$bgcolor;
		print "<tr bgcolor='$trbg'><td> Afdeling </td><td colspan=\"2\"><select class=\"inputbox\" name=\"afd\" style=\"width:200px;\">";
		for ($x=0;$x<count($afd_nr);$x++) {
			if ($afd == $afd_nr[$x]) print "<option value=$afd_nr[$x]>$afd_nr[$x] : $afd_navn[$x]</option>";
		}
		for ($x=0;$x<count($afd_nr);$x++) {
			if ($afd != $afd_nr[$x]) print "<option value=$afd_nr[$x]>$afd_nr[$x] : $afd_navn[$x]</option>";
		}
		print "</select></td></tr>";
	}
	if (count($ref_nr)>1) {
		($trbg==$bgcolor)?$trbg=$bgcolor5:$trbg=$bgcolor;
		print "<tr bgcolor='$trbg'><td> Sælger </td><td colspan=\"2\"><select class=\"inputbox\" name=\"ref\" style=\"width:200px;\">";
		for ($x=0;$x<count($ref_nr);$x++) {
			if ($ref == $ref_nr[$x]) print "<option value=$ref_nr[$x]>$ref_brugernavn[$x]</option>";
		}
		for ($x=0;$x<count($ref_nr);$x++) {
			if ($ref != $ref_nr[$x]) print "<option value=$ref_nr[$x]>$ref_brugernavn[$x]</option>";
		}
		print "</select></td></tr>";
	}
	($trbg==$bgcolor)?$trbg=$bgcolor5:$trbg=$bgcolor;
	print "<tr bgcolor='$trbg'>";
	print "	<td> Periode</td>";
	print "	<td colspan=\"1\"><input class=\"inputbox\" style=\"width:97px;\" type=\"text\" name=\"dato_fra\" value=\"$dato_fra\"></td>";
	print "	<td colspan=\"1\"><input class=\"inputbox\" style=\"width:97px;\" type=\"text\" name=\"dato_til\" value=\"$dato_til\"></td>";
	print "	</tr>";
	($trbg==$bgcolor)?$trbg=$bgcolor5:$trbg=$bgcolor;
	print "<tr bgcolor='$trbg'><td>Varenr</td><td colspan=\"2\"><input class=\"inputbox\" style=\"width:200px;\" name=\"varenr\" value=\"$varenr\"></td></tr>";
	($trbg==$bgcolor)?$trbg=$bgcolor5:$trbg=$bgcolor;
	print "<tr bgcolor='$trbg'><td>Varenavn</td><td colspan=\"2\"><input class=\"inputbox\" style=\"width:200px;\" name=\"varenavn\" value=\"$varenavn\"></td></tr>";
	($trbg==$bgcolor)?$trbg=$bgcolor5:$trbg=$bgcolor;
	print "<tr bgcolor='$trbg'><td>Detaljeret</td><td colspan=\"2\"><input type=\"checkbox\" name=\"detaljer\" $detaljer></td></tr>";
	($trbg==$bgcolor)?$trbg=$bgcolor5:$trbg=$bgcolor;
	print "<tr bgcolor='$trbg'><td>Kun salg / DB</td><td colspan=\"2\"><input type=\"checkbox\" name=\"kun_salg\"$kun_salg></td></tr>";
	($trbg==$bgcolor)?$trbg=$bgcolor5:$trbg=$bgcolor;
	print "<tr bgcolor='$trbg'><td>Beregn lagerbeh./værdi</td><td colspan=\"2\"><input type=\"checkbox\" name=\"lagertal\" $lagertal></td></tr>";
	($trbg==$bgcolor)?$trbg=$bgcolor5:$trbg=$bgcolor;
	print "<tr bgcolor='$trbg'><td colspan='3' align=center><input class='button green medium' type=submit value=\"  OK  \" name=\"submit\"></td></tr>";
	print "</tbody></table>";
	print "<tr><td ALIGN=\"center\" Valign=\"top\" height=39%><center><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>\n";
	print "<tr><td><hr></td></tr>";
	$txt = "<tr><td ALIGN=center><span title='Se lagerstatus p&aring; vilk&aring;rlig dato'>";
	$txt.= "<input class='button blue medium' style='width:350px;' type='submit' value='Lagerstatus' name='submit'>";
	$txt.= "</span></td></tr>";
	print $txt;
	$txt = "<tr><td ALIGN=center><span title='Beholdningsrapport med lokation samt min og max'>";
	$txt.= "<input class='button gray medium' style=\"width:350px;\" type=submit value=\"Beholdningsrapport\" name=\"lokMinMax\">";
	$txt.= "</span></td></tr>";
	if ($db == 'bizsys_49') print $txt;
	print "<tr><td><hr></td></tr>";
	$txt = "<tr><td ALIGN=center><span title='Funktion til opt&aelig;lling og regulering af varelager'>"; 
	$txt.=  "<input class='button gray medium' style=\"width:350px;\" type=submit value=\"Lageropt&aelig;lling\" name=\"submit\">";
	$txt.=  "</span></td></tr>";
	print $txt;
	print "</form>";
	print "</tbody></table></center>\n";
	print "</td></tr>";
	
}

##################################################################################################
function varegruppe($date_from,$date_to,$varenr,$varenavn,$varegruppe,$detaljer,$kun_salg,$lagertal,$vk_kost,$afd,$lev,$ref) {

#	global $connection;
	global $bgcolor,$bgcolor5;
	global $db;
	global $jsvars;
	global $md,$menu;
	global $returside;
	global $top_bund;

	if ($detaljer) $cols=9;
	elseif ($kun_salg) $cols=8;
	else $cols=13;

	$v_gr = array();
	$v_navn = array();

	$gruppenr=$kobssum=0;
	if (is_numeric($varegruppe)) $gruppenr=$varegruppe;
	elseif (strpos(":",$varegruppe)) list($gruppenr, $tmp)=explode(":",$varegruppe); 
	
/*
	if ($aarsrapport) {
#		list($fy,)
		$qtxt="select kodenr from grupper where are='RA' and box1 <= '".date('d')."' and box2 <='$daa' and box4 >=";
	}
*/	
	
#	if ($returside) $luk= "<a accesskey=L href=\"$returside\">";
#	else 

$luk= "<a class='button red small' accesskey=L href=\"rapport.php?varegruppe=$varegruppe&afd=$afd&lev=$lev&ref=$ref&date_from=$date_from&date_to=$date_to&varenr=$varenr&varenavn=$varenavn&detaljer=$detaljer&kun_salg=$kun_salg&lagertal=$lagertal\">";
	if ($menu=='T') {
		include_once '../includes/top_menu.php';
		include_once '../includes/top_header.php';
		print "<div id=\"header\"> 
		<div class=\"headerbtnLft\">$luk Luk</a></div>
		<span class=\"headerTxt\">Rapport | Varesalg | ".dkdato($date_from)." - ".dkdato($date_to); "</span>";     
		print "<div class=\"headerbtnRght\"></div>";       
		print "</div><!-- end of header -->
			<div class=\"maincontentLargeHolder\">\n";
		print "<table class='dataTable' width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	} else {
		print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

		print "<tr><td colspan=\"$cols\" height=\"9\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund>$luk Luk</a></td>";
		print "<td width=\"80%\" $top_bund>Rapport | varesalg | ".dkdato($date_from)." - ".dkdato($date_to);
	}
	if ($afd) {
		$r=db_fetch_array(db_select("select beskrivelse from grupper where art = 'AFD' and kodenr = '$afd'",__FILE__ . " linje " . __LINE__));
		print " | $r[beskrivelse]";
	}
	if ($lev) {
		$r = db_fetch_array(db_select("select kontonr,firmanavn from adresser where id='$lev'",__FILE__ . " linje " . __LINE__));
		$lev_nr=$r['kontonr'];
		$lev_navn=$r['firmanavn'];
		if ($lev_navn) print " | $lev_nr ($lev_navn)";
		$x=0;
		$q=db_select("select vare_id from vare_lev where lev_id='$lev'",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$lev_vare_id[$x]=$r['vare_id'];
			$x++;
		}
	}
	if ($ref) {
		$r = db_fetch_array(db_select("select brugernavn,ansat_id from brugere where id='$ref'",__FILE__ . " linje " . __LINE__));
		$ref_brugernavn=$r['brugernavn'];
		$ref_ansat=$r['ansat_id'];
		$ref_navn=NULL;
		if ($ref_ansat) {
			$r = db_fetch_array(db_select("select navn from ansatte where id=$ref_ansat",__FILE__ . " linje " . __LINE__));
			$ref_navn=$r['navn'];
		}  
		if (!$ref_navn) $ref_navn=$ref_brugernavn;
		if ($ref_navn) print " | $ref_navn";
	}
	if ($menu=='T') {

	} else {
	print "</td>";
	print "<td width=\"10%\" $top_bund><a href='../temp/$db/salgsrapport.csv' target='_blank'>csv</a></td>";
	print "</tbody></table>"; #B slut
	}
	print "</td></tr>";
	$lagergruppe=array();
	if ($gruppenr) {
		$qtxt="select box8,box9 from grupper where kodenr ='$gruppenr' and art='VG'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$batch_kontrol=$r['box9'];
		if ($r['box8']=='on') $lagergruppe[0]=$gruppenr;
	} else {
		$x=0;
		$qtxt="select kodenr,box8,box9 from grupper where art='VG' order by kodenr";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			if ($r['box8']=='on') {
				$lagergruppe[$x]=$r['kodenr'];
				$x++;
			}	
		}
	}
	$antal=0;
	$k_antal=0;
	$s_antal=0;
	$kontonr=array();
	$x=0;
	$tmp="";
	if ($gruppenr) $tmp = "where gruppe = '$gruppenr'"; 
	if ($varenr && $varenr != '*') {
		if (strstr($varenr, "*")) {
			if (substr($varenr,0,1)=='*') $varenr="%".substr($varenr,1);
			if (substr($varenr,-1,1)=='*') $varenr=substr($varenr,0,strlen($varenr)-1)."%";
		} 
		$low=strtolower($varenr);
		$upp=strtoupper($varenr);
		if ($tmp) $tmp.=" and (varenr LIKE '".db_escape_string($varenr)."' or lower(varenr) LIKE '".db_escape_string($low)."' or upper(varenr) LIKE '".db_escape_string($upp)."')";
		else $tmp =  "where (varenr LIKE '".db_escape_string($varenr)."' or lower(varenr) LIKE '".db_escape_string($low)."' or upper(varenr) LIKE '".db_escape_string($upp)."')";
	}
	if ($varenavn && $varenavn != '*') {
		if (strstr($varenavn, "*")) {
			if (substr($varenavn,0,1)=='*') $varenavn="%".substr($varenavn,1);
			if (substr($varenavn,-1,1)=='*') $varenavn=substr($varenavn,0,strlen($varenavn)-1)."%";
		} 
		$low=strtolower($varenavn);
		$upp=strtoupper($varenavn);
		if ($tmp) $tmp.=" and (beskrivelse LIKE '".db_escape_string($varenavn)."' or lower(beskrivelse) LIKE '".db_escape_string($low)."' or upper(beskrivelse) LIKE '".db_escape_string($upp)."')";
		else $tmp =  "where (beskrivelse LIKE '".db_escape_string($varenavn)."' or lower(beskrivelse) LIKE '".db_escape_string($low)."' or upper(beskrivelse) LIKE '".db_escape_string($upp)."')";
	}
	$vare_id=array();
	$x=0;
	if ($lagertal) $qtxt="select id,gruppe,samlevare from varer $tmp order by gruppe,beskrivelse";
	else $qtxt="select id,gruppe,samlevare from varer $tmp order by beskrivelse";
	$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!$lev || in_array($r['id'],$lev_vare_id)) { 
			if (!$r['samlevare'] || in_array($r['gruppe'],$lagergruppe)) { #20151105
				$x++;
				$vare_id[$x]=$r['id'];
			}
		}
#cho "A $vare_id[$x]<br>";
	}
	$v_id=array();
	$x=0;
	# finder alle konti med bevaegelser i den anfoerte periode eller aabne poster fra foer perioden
	$qtxt="select batch_salg.vare_id,batch_salg.pris,varer.gruppe,varer.beskrivelse from batch_salg,varer";
	if ($afd || $ref) $qtxt.=",ordrer";
	$qtxt.=" where batch_salg.fakturadate<='$date_to' and batch_salg.vare_id = varer.id";  # 20181003
	if ($afd) $qtxt.=" and batch_salg.ordre_id = ordrer.id and ordrer.afd='$afd'";
	if ($ref) $qtxt.=" and batch_salg.ordre_id = ordrer.id and (ordrer.ref='$ref_navn' or ordrer.ref='$ref_brugernavn')";
	if ($lagertal) $qtxt.=" order by varer.gruppe,varer.beskrivelse";
	else $qtxt.=" and batch_salg.fakturadate>='$date_from' order by varer.beskrivelse";
#cho "$qtxt<br>";
	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if ((in_array(trim($row['vare_id']),$vare_id))&&(!in_array(trim($row['vare_id']), $v_id))) {
			$x++;
			$v_id[$x]=trim($row['vare_id']);
			$v_gr[$x]=trim($row['gruppe']);
			$v_navn[$x]=trim(strip_tags($row['beskrivelse']));
		}
	}
 #cho "select vare_id, pris from batch_kob where fakturadate>='$date_from' and fakturadate<='$date_to' order by vare_id<br>";	
	$qtxt="select batch_kob.fakturadate,batch_kob.vare_id,batch_kob.pris,varer.gruppe,varer.beskrivelse ";
	$qtxt.="from batch_kob,varer where batch_kob.fakturadate<='$date_to' and batch_kob.vare_id = varer.id "; #20181003
	if ($lagertal) $qtxt.="order by gruppe,varer.beskrivelse";
	else $qtxt.=" and batch_kob.fakturadate>='$date_from' order by varer.beskrivelse";
#cho "$qtxt<br>";
	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__); 
	while ($row = db_fetch_array($query)) {
		if ((in_array(trim($row['vare_id']), $vare_id))&&(!in_array(trim($row['vare_id']), $v_id))) {
			$x++;
			$v_id[$x]=trim($row['vare_id']);
			$v_gr[$x]=trim($row['gruppe']);
			$v_navn[$x]=trim(strip_tags($row['beskrivelse']));
		}
	}
	if ($lagertal) {
		array_multisort($v_gr,$v_navn,$v_id);
	} else {
		array_multisort($v_navn,$v_id,$v_gr);
	}
	$csvfile=fopen("../temp/$db/salgsrapport.csv","w");
	fwrite($csvfile, "\"\";\"\";\"Rapport | Varesalg | ".dkdato($date_from)." - ".dkdato($date_to)."\"\r\n");
#	 print "<tr><td colspan=8><hr></td></tr>";
#	print "<tr><td width=10%> Dato</td><td width=10%> Bilag</td><td width=50%> Tekst</td><td width=10% align=right> Debet</td><td width=10% align=right> Kredit</td><td width=10% align=right> Saldo</td></tr>";

	if (!$detaljer) {
		if ($kun_salg) {
			print "<tr><td><b>Varenr.</b></td>
			<td><b>Enhed</b></td>
			<td><b>Beskrivelse</b></td>
			<td align=\"right\"><b>Solgt</b></td>
			<td align=\"right\"><b>Salgspris</b></td>
			<td align=\"right\"><b>DB</b></td>
			<td align=\"right\"><b>DG</b></td>
			<td align=\"right\"><b>På lager</b></td>";
			fwrite($csvfile, "Varenr;Enhed;Beskrivelse;Solgt;Salgspris;DB;DG;På lager\r\n");
		} else { 
			print "<tr><td><b>Varenr.</b></td>
			<td><b>Enhed</b></td>
			<td><b>Beskrivelse</b></td>
			<td align=\"right\"><b>Købt</b></td>
			<td align=\"right\"><b>K&oslash;bspris</b></td>
			<td align=\"right\"><b>Solgt</b></td>
			<td align=\"right\"><b>Salgspris</b></td>
			<td align=\"right\"><b>+Moms</b></td>
			<td align=\"right\"><b>Reguleret</b></td>
			<td align=\"right\"><b>DB</b></td>
			<td align=\"right\"><b>DG</b></td>
			<td align=\"right\"><b>K&oslash;bspris</b></td>";
			fwrite($csvfile, "Varenr;Enhed;Beskrivelse;Købt;Købspris;Solgt;Salgspris;Reguleret;DB;DG;Købspris");
			if (count($lagergruppe) && $lagertal) {
				print "<td align=\"right\"><b>Beholdning</b></td>
				<td align=\"right\"><b>Værdi</b></td>";
				fwrite($csvfile,";Beholdning;Værdi");
			}	
			print "</tr>";
			fwrite($csvfile,"\r\n");
		}
	}
	$tt_kobt=$tt_solgt=$tt_regul=$tt_k_pris=$tt_s_pris=$tt_moms=$tt_kost=$tt_dkBi=$tt_stockvalue=0;
	$beskrivelse=$enhed=$varenr=array();
	for ($x=0;$x<count($v_id);$x++) {
		$beholdning[$x]=0;
		$qtxt="select * from varer where id='$v_id[$x]'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$varenr[$x]=$r['varenr'];
		$enhed[$x]=$r['enhed'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$v_kostpris[$x]=$r['kostpris'];
		$samlevare[$x]=$r['samlevare'];
		if ($kun_salg || ($lagertal && in_array($v_gr[$x],$lagergruppe) && !$samlevare[$x])) {
			$qtxt="select sum(antal) as beholdning from batch_kob where vare_id='$v_id[$x]' and fakturadate <= '$date_to'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$beholdning[$x]+=$r['beholdning'];
			$qtxt="select sum(antal) as beholdning from batch_salg where vare_id='$v_id[$x]' and fakturadate <= '$date_to'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$beholdning[$x]-=$r['beholdning'];
		}
	}
	for ($x=0;$x<count($v_id);$x++) {
		$fakturadate=$bk_id=$linje_id=$k_ordre_id=$k_antal=$pris=array();
		$t_kobt=$t_regul=$t_k_pris=$t_moms=$y=0;
		$qtxt="select * from batch_kob where vare_id='$v_id[$x]' order by fakturadate,ordre_id";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$ok=1;
			if (!$kun_salg && $r['fakturadate'] && $r['fakturadate'] <= $date_to && $r['fakturadate'] >= $date_from) {
				$bk_id[$y]=$r['id'];
#				$bs_id[$y]=NULL;
				$linje_id[$y]=$r['linje_id'];		
				$fakturadate[$y]=$r['fakturadate'];
				$k_ordre_id[$y]=$r['ordre_id'];
				if ($vk_kost) {
					$k_antal[$y]=$r['antal'];
					$pris[$y]=$v_kostpris;
				} else {
					$k_antal[$y]=$r['antal'];
					$pris[$y]=$r['pris'];
				}
				if ($linje_id[$y]) {
					$qtxt="select momssats,momsfri,omvbet from ordrelinjer where id='$linje_id[$y]'";
					if ($r1 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
						$momssats[$y]=$r1['momssats'];
						$momsfri[$y]=$r1['momsfri'];
						$omvbet[$y]=$r1['omvbet'];
						if ($momsfri[$y] || $omvbet[$y]) $moms[$y]=0;
						elseif ($momssats[$y]=='') {
							$momssats[$y]=find_varemomssats($linje_id[$y]);
						}	else $moms[$y]=$pris[$y]/100*$momssats[$y];
					} else $ok=0;
				} else {
					if (!$k_ordre_id[$y]) {
						$t_regul+=$k_antal[$y];
						$tt_regul+=$k_antal[$y];
					}
					$ok=0;
				}
				if ($ok) {
					$t_kobt+=$k_antal[$y];
					$t_k_pris+=$pris[$y]*$k_antal[$y];
					$tt_kobt+=$k_antal[$y];
					$tt_k_pris+=$pris[$y]*$k_antal[$y];
					$t_moms+=$moms[$y];
				}
				$y++;
			} else {
#				if ($r['ordre_id']) $tt_kobt=$r['antal'];
#				else $tt_regul+=$r['antal'];
 			}
		}
		$linjebg=$bgcolor;
		if ($detaljer) {
			print "<tr><td colspan=\"$cols\"><hr></td></tr>";
			print "<tr><td><br></td></tr>";
			print "<tr><td><br></td></tr>";
			print "<tr><td colspan=\"3\"><b>$varenr[$x] $beskrivelse[$x]</b></td></tr>";
			fwrite($csvfile,";;;\"$varenr[$x] ".utf8_decode($beskrivelse[$x])."\"\r\n");
#			if ($enhed[$x]) print "<tr><td colspan=\"3\">$enhed[$x]</td></tr>";
#			print "<tr><td colspan=\"3\"><b>$beskrivelse[$x]</b></td></tr>";
			print "<tr><td></td></tr>";
			if (!$kun_salg) {
				print "<tr><td>Købsdato</td><td align=\"right\">Antal</td><td align=\"right\">Pris</td><td align=\"right\">Moms</td><td align=\"right\">Incl. moms</td><td align=\"right\">Ordre</td></tr>";
			fwrite($csvfile,"Købsdato;Antal;Pris;Moms\Incl. moms;Ordre\r\n");
				print "<tr><td colspan=\"$cols\"><hr></td></tr>";
				for ($y=0;$y<count($k_antal);$y++) {
					if ($k_ordre_id[$y]) {
						($linjebg==$bgcolor)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
						print "<tr bgcolor=\"$linjebg\"><td>".dkdato($fakturadate[$y])."</td><td align=\"right\">".dkdecimal($k_antal[$y],2)."</td>";
						fwrite($csvfile, dkdato($fakturadate[$y]).";".dkdecimal($k_antal[$y],2));
						$linjepris=$pris[$y]*$k_antal[$y];
						$kobssum+=$t_kobt;
						print "<td align=\"right\">".dkdecimal($pris[$y],2)."</td><td align=\"right\">".dkdecimal($moms[$y],2)."</td><td align=\"right\">".dkdecimal($pris[$y]+$moms[$y],2)."</td>";
						fwrite($csvfile, dkdecimal($pris[$y],2).";".dkdecimal($moms[$y],2).";".dkdecimal($pris[$y]+$moms[$y],2)."\r\n");
						print "<td align=right onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$k_ordre_id[$y]&returside=../includes/luk.php','k_ordre','width=800,height=400,$jsvars')\"> <u>Se</u></td></tr>";
					}
				}
				print "<tr><td colspan=\"$cols\"><hr></td></tr>";
				fwrite($csvfile, "-----------\r\n");
				print "<tr><td></td><td align=\"right\"><b>".dkdecimal($t_kobt,2)."</b></td><td align=\"right\"><b>".dkdecimal($t_k_pris,2)."</b></td><td align=\"right\"><b>".dkdecimal($t_moms,2)."</b></td><td align=\"right\"><b>".dkdecimal($t_k_pris+$t_moms,2)."</b></td></tr>";
				fwrite($csvfile, dkdecimal($t_kobt,2).";".dkdecimal($t_k_pris,2).";".dkdecimal($t_moms,2).";".dkdecimal($t_k_pris+$t_moms,2)."\r\n");
				print "<tr><td colspan=\"$cols\"><hr></td></tr>";
			}
		}
		$bs_id=array();
		$s_ordre_id=array();
		$s_antal=array();
#		$pris=array(); 
		$t_dkBi=$t_kost=$t_moms=$t_s_pris=$t_solgt=$t_stockvalue=0;
		$qtxt="select * from batch_salg";
		if ($afd || $ref) $qtxt.=",ordrer";
		$qtxt.=" where batch_salg.vare_id='$v_id[$x]'";
		if ($afd) $qtxt.=" and batch_salg.ordre_id=ordrer.id and ordrer.afd=$afd";
		if ($ref) $qtxt.=" and batch_salg.ordre_id = ordrer.id and (ordrer.ref='$ref_navn' or ordrer.ref='$ref_brugernavn')";
		$qtxt.=" order by batch_salg.fakturadate,batch_salg.id"; #20170114
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$ok=1;
			if ($r['antal']*1 && $r['fakturadate'] && $r['fakturadate'] <= $date_to && $r['fakturadate'] >= $date_from) { #20160824
				$bs_id[$y]=$r['id'];
#				$bk_id[$y]=NULL;
				$fakturadate[$y]=$r['fakturadate'];
				$s_antal[$y]=$r['antal'];
				$pris[$y]=$r['pris'];
				$s_ordre_id[$y]=$r['ordre_id'];
				$linje_id[$y]=$r['linje_id'];
				if ($linje_id[$y]) {
					if ($r1 = db_fetch_array(db_select("select ordrelinjer.id,ordrelinjer.kostpris,ordrelinjer.momssats,ordrelinjer.momsfri,ordrelinjer.omvbet from ordrelinjer where ordrelinjer.id='$linje_id[$y]'",__FILE__ . " linje " . __LINE__))){
						if ($vk_kost) $kostpris[$y]=$v_kostpris;
						else $kostpris[$y]=$r1['kostpris'];
						$momssats[$y]=$r1['momssats'];
						$momsfri[$y]=$r1['momsfri'];
						$omvbet[$y]=$r1['omvbet'];
						if ($momsfri[$y] || $omvbet[$y]) $moms[$y]=0;
						elseif ($momssats[$y]=='') {
							$momssats[$y]=find_varemomssats($linje_id[$y]);
						}	else $moms[$y]=$pris[$y]/100*$momssats[$y];
					} else $ok=0;
					list($koordpr,$koordnr,$koordant,$koordid,$koordart)=explode(chr(9),find_kostpris($v_id[$x],$linje_id[$y]));
					$kobs_ordre_pris=explode(",",$koordpr);
					$ko_ant[$y]=count($kobs_ordre_pris);
 					$kobs_ordre_id=explode(",",$koordid);
 					$kobs_ordre_antal=explode(",",$koordant);
					$kobs_ordre_art=explode(",",$koordart);
#20151210 ->
					if ($ko_ant[$y]) {
						$kostpris[$y]=0;
						for($z=0;$z<$ko_ant[$y];$z++) {
							$kostpris[$y]+=$kobs_ordre_pris[$z];
						}
						$kostpris[$y]/=$ko_ant[$y];
					} elseif ($vk_kost) $kostpris[$y]=$v_kostpris;
				} else $ok=0;
				if ($ok) {
#<- 20151210
						$vg=$v_gr[$x];
						if (!isset($g_sum[$vg])) $g_sum[$vg]=0;
						if (!isset($g_solgt[$vg])) $g_solgt[$vg]=0;
						if (!isset($g_stockvalue[$vg])) $g_stockvalue[$vg]=0;
					if ($s_ordre_id[$y]) {
						if ($fakturadate[$y] >= $date_from && $fakturadate[$y] <= $date_to) {
							$g_sum[$vg]+=afrund($pris[$y]*$s_antal[$y],3);
							$g_solgt[$vg]+=$s_antal[$y];
						} else {
						}
						$t_solgt+=$s_antal[$y];
						$t_s_pris+=$pris[$y]*$s_antal[$y];
						$tt_solgt+=$s_antal[$y];
						$tt_s_pris+=$pris[$y]*$s_antal[$y];
						$t_moms+=$moms[$y]*$s_antal[$y];
						$tt_moms+=$moms[$y]*$s_antal[$y];
						$t_kost+=$kostpris[$y]*$s_antal[$y];
						$tt_kost+=$kostpris[$y]*$s_antal[$y];
						$dkBi[$y]=$pris[$y]-$kostpris[$y];
						$t_dkBi+=$dkBi[$y]*$s_antal[$y];
						$tt_dkBi+=$dkBi[$y]*$s_antal[$y];
						if ($s_antal[$y]<0)$dkBi[$y]*=-1; # 20160201 # Flyttet under sammentælling
						if ($pris[$y]!=0) {
							$dg[$y]=$dkBi[$y]*100/$pris[$y];
						} else $dg[$y]=0;
					} 
				} else { #20170419
					$t_regul-=$s_antal[$y];
					$tt_regul-=$s_antal[$y];
				}
				$y++;
			} else {
#				if ($r['ordre_id']) $tt_solgt+=$r['antal'];
#				else $tt_regul+=$r['antal'];
			}
		}
		$t_s_pris=afrund($t_s_pris,2); #20190830
		if ($t_s_pris && $t_dkBi) $t_dg=$t_dkBi*100/$t_s_pris;
		else $t_dg=100;
		if ($detaljer) {
			print "<tr><td>Salgsdato</td><td align=\"right\">Antal</td><td align=\"right\">Pris</td><td align=\"right\">Moms</td><td align=\"right\">Incl.moms</td><td align=\"right\">Kostpris</td><td align=\"right\">DB</td><td align=\"right\">DG</td><td align=\"right\">Ordre</td></tr>";
			fwrite($csvfile, "Salgsdato;Antal;Pris;Moms;Incl.moms;Kostpris;DB;DG\r\n");
			for ($y=count($bk_id);$y<count($linje_id);$y++) {
				if ($s_ordre_id[$y]) {
					($linjebg==$bgcolor)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
					print "<tr bgcolor=\"$linjebg\"><td>".dkdato($fakturadate[$y])."</td><td align=right>".dkdecimal($s_antal[$y],2)."</td>";
					print "<td align=right>".dkdecimal($pris[$y],2)."</td>";
					print "<td align=right>".dkdecimal($moms[$y],2)."</td>";
					print "<td align=right>".dkdecimal($pris[$y]+($moms[$y]),2)."</td>";
					print "<td align=right>".dkdecimal($kostpris[$y],2)."</td>";
					print "<td align=right>".dkdecimal($dkBi[$y],2)."</td>";
					print "<td align=right> ".dkdecimal($dg[$y],2)."%</td>";
					print "<td align=right title=\"\" onClick=\"javascript:s_ordre=window.open('../debitor/ordre.php?id=$s_ordre_id[$y]&returside=../includes/luk.php','s_ordre','width=800,height=400,$jsvars')\"> <u>Se</u></td></tr>";
					fwrite($csvfile, dkdato($fakturadate[$y]).";".dkdecimal($s_antal[$y],2).";".dkdecimal($pris[$y],2).";");
					fwrite($csvfile, dkdecimal($moms[$y],2).";".dkdecimal($pris[$y]+($moms[$y]),2).";".dkdecimal($kostpris[$y],2).";");
					fwrite($csvfile, dkdecimal($dkBi[$y],2).";".dkdecimal($dg[$y],2)."%\r\n");
				}
			}
			print "<tr><td colspan=\"$cols\"><hr></td></tr>";
			($linjebg==$bgcolor)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
			print "<tr bgcolor=\"$linjebg\"><td></td>";
			print "<td align=right> <b>".dkdecimal($t_solgt,2)."</b></td>";
			fwrite($csvfile, dkdecimal($t_solgt,2).";");
			print "<td align=right> <b>".dkdecimal($t_s_pris,2)."</b></td>";
			fwrite($csvfile, dkdecimal($t_s_pris,2).";");
			print "<td align=right> <b>".dkdecimal($t_moms,2)."</b></td>";
			fwrite($csvfile, dkdecimal($t_moms,2).";");
			print "<td align=right> <b>".dkdecimal($t_s_pris+$t_moms,2)."</b></td>";
			fwrite($csvfile, dkdecimal($t_s_pris+$t_moms,2).";");
			print "<td align=right> <b>".dkdecimal($t_kost,2)."</b></td>";
			fwrite($csvfile, dkdecimal($t_kost,2).";");
			print "<td align=right> <b>".dkdecimal($t_dkBi,2)."</b></td>";
			fwrite($csvfile, dkdecimal($t_dkBi,2).";");
			print "<td align=right> <b>".dkdecimal($t_dg,2)."%</b></td></tr>";
			fwrite($csvfile, dkdecimal($t_dg,2)."\r\n");
			if (!$kun_salg) print "<tr><td colspan=\"$cols\"><hr></td></tr>";
			if (!$afd && !$lev && !$ref && !$kun_salg) {
			print "<tr><td>Lagerreguleret</td><td align=\"right\">Antal</td></tr>";
			fwrite($csvfile, "Lagerreguleret;Antal\r\n");
			$fd=array_unique($fakturadate); #20160804
			sort($fd);
			for ($f=0;$f<count($fd);$f++) { #20160824
				for ($y=0;$y<count($bk_id);$y++) {
					if ($fd[$f]==$fakturadate[$y] && !$k_ordre_id[$y] && $bk_id[$y]) {
						($linjebg==$bgcolor)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
						print "<tr bgcolor=\"$linjebg\"><td>".dkdato($fakturadate[$y])."</td><td align=right>".dkdecimal($k_antal[$y],2)."</td></tr>";
						fwrite($csvfile, dkdato($fakturadate[$y]).";".dkdecimal($k_antal[$y],2)."\r\n");
					}
				}
				for ($y=count($bk_id);$y<count($linje_id);$y++) {
					if ($fd[$f]==$fakturadate[$y] && !$s_ordre_id[$y] && $bs_id[$y]) {
						($linjebg==$bgcolor)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
						print "<tr bgcolor=\"$linjebg\"><td>".dkdato($fakturadate[$y])."</td><td align=right>-".dkdecimal($s_antal[$y],2)."</td></tr>"; #20160418
						fwrite($csvfile, dkdato($fakturadate[$y]).";".dkdecimal($s_antal[$y],2)."\r\n");
					}
				}
			}
#cho "$t_kobt+$t_regul-$t_solgt<br>";
			if (!$kun_salg) {	
				print "<tr><td colspan=\"$cols\"><hr></td></tr>";
				fwrite($csvfile, "-----------\r\n");
				($linjebg==$bgcolor)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				print "<tr bgcolor=\"$linjebg\"><td></td><td align=right> <b>".dkdecimal($t_regul,2)."</b></td><tr>"; #20151105
				fwrite($csvfile, ";".dkdecimal($t_regul,2)."\r\n");
				print "<tr><td colspan=\"$cols\"><hr></td></tr>";
				fwrite($csvfile, "-----------\r\n");
				($linjebg==$bgcolor)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				print "<tr bgcolor=\"$linjebg\"><td>Samlet til-/afgang i perioden</td><td align=right> <b>".dkdecimal($t_kobt+$t_regul-$t_solgt,2)."</b></td><tr>"; #20151105
				fwrite($csvfile, "Samlet til-/afgang i perioden;".dkdecimal($t_kobt+$t_regul-$t_solgt,2)."\r\n");
			}
			}
		} else {
			if (!$x && $lagertal) {
				$qtxt="select beskrivelse from grupper where art='VG' and kodenr='".$v_gr[$x]."'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				print "<tr><td colspan='2'><b><big>$r[beskrivelse]</big></b></tr>";
				fwrite($csvfile, utf8_decode($r['beskrivelse'])."\r\n");
			}
			print "<tr><td>$varenr[$x]</td>";
			print "<td>$enhed[$x]</td>";
			print "<td>$beskrivelse[$x]</td>";
			fwrite($csvfile, "\"$varenr[$x]\";\"$enhed[$x]\";\"".utf8_decode($beskrivelse[$x])."\";");
			if ($kun_salg) {
				print "<td align=right>".dkdecimal($t_solgt,2)."</td>";
				fwrite($csvfile, dkdecimal($t_solgt,2).";");
				print "<td align=right>".dkdecimal($t_s_pris,2)."</td>";
				fwrite($csvfile, dkdecimal($t_s_pris,2).";");
				print "<td align=right>".dkdecimal($t_dkBi,2)."</td>";
				fwrite($csvfile, dkdecimal($t_dkBi,2).";");
				print "<td align=right>".dkdecimal($t_dg,2)."%</td>";
				fwrite($csvfile, dkdecimal($t_dg,2).";");
				print "<td align=right>".dkdecimal($beholdning[$x],2)."</td>";#20180925
				fwrite($csvfile, dkdecimal($beholdning[$x],2)."\r\n");
			} else {
				print "<td align=right>".dkdecimal($t_kobt,2)."</td>";
				fwrite($csvfile, dkdecimal($t_kobt,2).";");
				print "<td align=right>".dkdecimal($t_k_pris,2)."</td>";
				fwrite($csvfile, dkdecimal($t_k_pris,2).";");
				print "<td align=right>".dkdecimal($t_solgt,2)."</td>";
				fwrite($csvfile, dkdecimal($t_solgt,2).";");
				print "<td align=right>".dkdecimal($t_s_pris,2)."</td>";
				fwrite($csvfile, dkdecimal($t_s_pris,2).";");
				print "<td align=right>".dkdecimal($t_moms,2)."</td>";
				fwrite($csvfile, dkdecimal($t_s_pris,2).";");
				print "<td align=right>".dkdecimal($t_regul,2)."</td>";
				fwrite($csvfile, dkdecimal($t_regul,2).";");
				print "<td align=right>".dkdecimal($t_dkBi,2)."</td>";
				fwrite($csvfile, dkdecimal($t_dkBi,2).";");
				print "<td align=right>".dkdecimal($t_dg,2)."%</td>";
				fwrite($csvfile, dkdecimal($t_dg,2).";");
				print "<td align=right>".dkdecimal($t_kobt+$t_regul-$t_solgt,2)."</td>";#20151105
				fwrite($csvfile, dkdecimal($t_kobt+$t_regul-$t_solgt,2).";");
				if ($lagertal && in_array($v_gr[$x],$lagergruppe)) {
					print "<td align=right>".dkdecimal($beholdning[$x],2)."</td>";#20180925
					fwrite($csvfile, dkdecimal($beholdning[$x],2).";");
					print "<td align=right>".dkdecimal($beholdning[$x]*$v_kostpris[$x],2)."</td>";#20180925
					fwrite($csvfile, dkdecimal($beholdning[$x]*$v_kostpris[$x],2));
#					$g_stockvalue[$vg]+=$beholdning[$x]*$v_kostpris[$x];
					$t_stockvalue+=$beholdning[$x]*$v_kostpris[$x];
					$tt_stockvalue+=$beholdning[$x]*$v_kostpris[$x];
				}
				print "</tr>";
				fwrite($csvfile, "\r\n");
			}
			if ($lagertal && (!isset($v_gr[$x+1]) || $v_gr[$x]!=$v_gr[$x+1])) {
				$vg=$v_gr[$x];
				if (!isset($g_solgt[$vg])) $g_solgt[$vg]=NULL;
				if (!isset($g_sum[$vg])) $g_sum[$vg]=NULL;
				if (!isset($g_stockvalue[$vg])) $g_stockvalue[$vg]=NULL;
				$qtxt="select beskrivelse from grupper where art='VG' and kodenr='$vg'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				print "<tr><td><b>$r[beskrivelse]</b></td>";
				fwrite($csvfile, utf8_decode($r['beskrivelse']).";");
				if (!$kun_salg) print "<td colspan='2'>";
				print "<td colspan='2'></td><td align='right'><b>". dkdecimal($g_solgt[$vg],2) ."</b></td>";
				fwrite($csvfile, ";". dkdecimal($g_solgt[$vg],2) .";");

				print "<td align='right'><b>".dkdecimal($g_sum[$vg],2)."</b></td>";
				print "<td colspan='5'></td><td align='right'><b>".dkdecimal($g_stockvalue[$vg],2)."</b></td>";
				fwrite($csvfile, ";;;;;".dkdecimal($g_stockvalue[$vg],2)."\r\n");
				print "</tr>";
				$g_solgt[$vg]=$g_sum[$vg]=0;
				if (isset($v_gr[$x+1])) {
					print "<tr><td colspan=\"$cols\"><hr></td></tr>";
					fwrite($csvfile, "-------------\r\n");
					$qtxt="select beskrivelse from grupper where art='VG' and kodenr='".$v_gr[$x+1]."'";
					if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
						print "<tr><td colspan='2'><b><big>$r[beskrivelse]</big></b></tr>";
						fwrite($csvfile, ";$r[beskrivelse]\r\n");
					}
				}
			}
		} 
		if ($detaljer && !$kun_salg) {
			print "<tr><td colspan=\"$cols\"><hr></td></tr>";
			fwrite($csvfile, "-------------\r\n");
		}
	}
	if (!$detaljer) {
		if ($tt_s_pris && $tt_dkBi) $tt_dg=$tt_dkBi*100/$tt_s_pris;
		else $tt_dg=100;
		print "<tr><td colspan=\"$cols\"><hr></td></tr>";
		fwrite($csvfile, "-------------\r\n");
		if ($kun_salg) {
		print "<tr><td Colspan=\"3\"><b>Summeret</b></td>
			<td align=\"right\">Solgt</td>
			<td align=\"right\">Salgspris</td>
			<td align=\"right\">DB</td>
			<td align=\"right\">DG</td>";
		fwrite($csvfile, "Solgt;Salgspris;DB;DG\r\n");
		}	else {
		print "<tr><td Colspan=\"3\"><b>Summeret</b></td>
			<td align=\"right\">Købt</td>
			<td align=\"right\">K&oslash;bspris</td>
			<td align=\"right\">Solgt</td>
			<td align=\"right\">Salgspris</td>
			<td align=\"right\">Moms</td>
			<td align=\"right\">Reguleret</td>
			<td align=\"right\">DB</td>
			<td align=\"right\">DG</td>
			<td align=\"right\">Samlet til-/afgang i perioden</td>
			<td align=\"right\"></td>
			<td align=\"right\">Samlet lagerværdi</td></tr>";
			fwrite($csvfile, "Købt;Købspris\Solgt;Salgspris;Moms;Reguleret;DB;DG;Samlet til-/afgang;;Samlet lagerværdi\r\n");
		}
		if (!isset($varenr[$x])) $varenr[$x]=$enhed[$x]=$beskrivelse[$x]=NULL;
		print "<tr><td>$varenr[$x]</td>";
		print "<td>$enhed[$x]</td>";
		print "<td>$beskrivelse[$x]</td>";
		fwrite($csvfile, "\"$varenr[$x]\";\"$enhed[$x]\";\"".utf8_decode($beskrivelse[$x])."\"\r\n");
		if (!$kun_salg) {
			print "<td align=right> <b>".dkdecimal($tt_kobt,2)."</b></td>";
			fwrite($csvfile, dkdecimal($tt_kobt,2).";");
			print "<td align=right> <b>".dkdecimal($tt_k_pris,2)."</b></td>";
			fwrite($csvfile, dkdecimal($tt_k_pris,2).";");
		}
		print "<td align=right> <b>".dkdecimal($tt_solgt,2)."</b></td>";
		fwrite($csvfile, dkdecimal($tt_solgt,2).";");
		print "<td align=right> <b>".dkdecimal($tt_s_pris,2)."</b></td>";
		fwrite($csvfile, dkdecimal($tt_s_pris,2).";");
		if (!$kun_salg) {
			print "<td align=right> <b>".dkdecimal($tt_moms,2)."</b></td>";
			fwrite($csvfile, dkdecimal($tt_moms,2).";");
			print "<td align=right> <b>".dkdecimal($tt_regul,2)."</b></td>";
			fwrite($csvfile, dkdecimal($tt_regul,2).";");
		}
		print "<td align=right> <b>".dkdecimal($tt_dkBi,2)."</b></td>";
		fwrite($csvfile, dkdecimal($tt_dkBi,2).";");
		print "<td align=right> <b>".dkdecimal($tt_dg,2)."%</b></td>";
		fwrite($csvfile, dkdecimal($tt_dg,2).";");
		if (!$kun_salg) {
			print "<td align=right><b>".dkdecimal($tt_kobt+$tt_regul-$tt_solgt,2)."</b></td>";
			fwrite($csvfile, dkdecimal($tt_kobt+$tt_regul-$tt_solgt,2).";");
			print "<td align=right> <b></b></td>";
			print "<td align=right> <b>".dkdecimal($tt_stockvalue,2)."</b></td>";
			fwrite($csvfile, ";".dkdecimal($tt_kobt+$tt_regul-$tt_solgt,2).";");
		}
		print "</tr>";
		fwrite($csvfile, "\r\n");
	}
	print "</tbody></table>";
	fclose($csvfile);
}
#############################################################################################################

?>
</html>

