<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------sager/kontrol_sager-php---ver. 3.8.1---2020-04-16--------------
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
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 20150904 Tilføjet kopi af arbejdsseddel til sagen. Søg #20150904
// 20160107 Har fjernet aftrådte i 'kolleger' ved afsendelse af mail. Søg #20160107
// 20170303 Visning af bilag, hvis tilknyttet. Søg #20170303
// 20190806 PHR Added db_escape_string to $status_tekst[$x]
// 20200416 PHR Moved query text to qtxt and added db_escape_string to needed fields. #20200416

@session_start();
$s_id=session_id();

$bg="nix";
$header='nix';

$menu_sager='id="menuActive"';
$menu_planlaeg=NULL;
$menu_dagbog=NULL;
$menu_kunder=NULL;
$menu_loen=NULL;
$menu_ansatte=NULL;
$menu_certificering=NULL;
$menu_medarbejdermappe=NULL;
	
$modulnr=0;
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$sag_id=if_isset($_GET['sag_id']);
$konto_id=if_isset($_GET['konto_id']);
$funktion=if_isset($_GET['funktion']);
if (!$funktion) $funktion="kontrolliste";  

global $brugernavn;
global $db;
global $regnskab;
global $ansat_navn;

include_once '../includes/top_header_sager_small.php';
include_once '../includes/top_sagsmenu.php';
		
$funktion($sag_id);
print "</div><!-- end of maincontentLargeHolder -->\n";
print "</div><!-- end of wrapper2 -->\n";
print "</body>\n";
print "</html>\n";
		
function kontrolliste() {
	$sag_id=if_isset($_GET['sag_id']);
	$konto_id=if_isset($_GET['konto_id']);
		
	if (!$sag_id) return('Sag ID ikke angivet');

	// Visning af sagsnr og beskrivelse i breadcrumb
	$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__)); 
	$sagsnr=$r['sagsnr'];
	$sag_beskrivelse=htmlspecialchars($r['beskrivelse']);
	$udf_addr1=htmlspecialchars($r['udf_addr1']);
	$udf_postnr=$r['udf_postnr'];
	$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
		
		// Her hentes tjeklister til visning
	$x=0;
	$qtxt="select * from tjekliste where assign_to = 'sager' and assign_id = '0' and fase >= '2' order by fase";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tjek_id[$x]=$r['id'];
		//$tjek_sub_id[$x]=$r['sub_id'];
		$tjek_punkt[$x]=$r['tjekpunkt']; 
		$tjek_fase[$x]=$r['fase']*1;
		$x++;
	}
		
	// Her hentes arbejdsseddel fra tjeklister
	$qtxt="select * from tjekliste where assign_to = 'sager' and assign_id = '0' and fase = '1'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
	$arbejdsseddel_id=$r['id'];
	$arbejdsseddel_punkt=$r['tjekpunkt']; 
	$arbejdsseddel_fase=$r['fase']*1;
			
	print "<div id=\"breadcrumbbar\">
		<ul id=\"breadcrumb\">
			<li><a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a></li>
			<!--<li><a href=\"#\" title=\"Sample page 1\">Sample page 1</a></li>-->
			<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id\" title=\"Sag: $sagsnr, $sag_beskrivelse, $udf_addr1, $udf_postnr $udf_bynavn\">Tilbage til sag $sagsnr</a></li>\n";
	print "<li>Kontrolskema</li>
		</ul>
	</div><!-- end of breadcrumbbar -->\n";

	print "<div class=\"maincontentLargeHolder\">\n";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
	print "<tbody>\n";
	print "<tr><td width=\"100%\" align=\"center\">\n";
	print "<table width=\"500\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"kontrolskema_liste\" >\n";
	print "<tbody>\n";
	print "<tr><td colspan=\"2\" width=\"100%\" align=\"center\"><h4>Vælg Kontrolskema</h4></td></tr>\n";
	print "<tr><td colspan=\"2\" width=\"100%\" align=center><br>\n";
	print "</tbody>\n";
	print "<tbody class=\"dataTableZebra dataTableTopBorder\">\n";
	print "<tr><td>$arbejdsseddel_punkt</td><td class=\"alignRight\"><a href=\"kontrol_sager.php?funktion=arbejdsseddel&amp;sag_id=$sag_id&amp;sag_fase=$arbejdsseddel_fase&amp;tjek_id=$arbejdsseddel_id\" title=\"Opret $arbejdsseddel_punkt til sagen her!\" class=\"button blue small\">Til skema</a></td></tr>\n";

	for ($y=0;$y<count($tjek_id);$y++) {
		print "<tr><td>$tjek_punkt[$y]</td><td class=\"alignRight\"><a href=\"kontrol_sager.php?funktion=kontrolskema&amp;sag_id=$sag_id&amp;sag_fase=$tjek_fase[$y]&amp;tjek_id=$tjek_id[$y]\" title=\"Opret kontrolskema '$tjek_punkt[$y]' til sagen her!\" class=\"button blue small\">Til skema</a></td></tr>\n";
	}

	print "</tbody>\n";
	print "</table>\n";
	print "</td></tr>\n";
	print "</tbody>\n";
	print "</table>\n";
}

function kontrolskema() {
// Tjekliste består at flg.pkt.
//	id : fortløbende nr.
//	tjekpunkt : Navn på punkt
//	Fase :  Hvorlangt sagen er nået - Opstart, tilbud, ordre mm. 
//	Assign_id : Id i tjekliste som punktet er underlagt. (Der er 3 niveauer) 
//	Assign_to :I dette tilfælde, 'sager', forberedt for andet.

// Tjekpunkter består at flg.pkt.
//	id : fortløbende nr.
//	tjekliste_id : ID på tjekpunkt i tjekliste -Hvis denne eksisterer er punket afmærket, ellers ikke.
//	assign_id: Sagen punktet tilhører

	$sag_id=if_isset($_GET['sag_id']);
	$sag_fase=if_isset($_GET['sag_fase']);
	$tjekpunkt_id=if_isset($_GET['tjek_id']);
	$tjekskema_id=if_isset($_GET['tjekskema_id']);
	
	if(isset($_POST['sag_id'])) $sag_id = $_POST['sag_id'];
	if(isset($_POST['sag_fase'])) $sag_fase = $_POST['sag_fase'];
	if(isset($_POST['tjek_id'])) $tjekpunkt_id = $_POST['tjek_id'];
	if(isset($_POST['tjekskema_id'])) $tjekskema_id = $_POST['tjekskema_id'];
	/*$sag_id=if_isset($_POST['sag_id']);
	$sag_fase=if_isset($_POST['sag_fase']);
	$tjekpunkt_id=if_isset($_POST['tjek_id']);
	*/
	
	//if(isset($opgave_id)) $_GET['opgave_id'];
	global $brugernavn;
	global $sprog_id;
	global $ansat_navn;
	global $db;
	//global $bgcolor;
	//global $bgcolor5;

	//$linjebg1="ffffff";
	//$linjebg2="f0f0f0";
	//$r = db_fetch_array(db_select("select status from sager where id = '$sag_id'",__FILE__ . " linje " . __LINE__));
	//($sag_fase<$r['status'])?$disabled="DISABLED=\"disabled\"":$disabled=NULL;
	
	$datotid=date("U");
	
	if (isset($_POST['kontrolskema']) && !$tjekskema_id) {
		
		$tjekliste_id=if_isset($_POST['tjekliste_id']);
		$status_tekst=if_isset($_POST['status_tekst']);
		$opg_art=if_isset($_POST['opg_art']);
		$sjak=if_isset($_POST['sjak']);
		$sjakid=if_isset($_POST['sjakid']);
		$tjekantal=if_isset($_POST['tjekantal']);
		$kontrolpunkt=if_isset($_POST['kontrolpunkt']);
		$hvem=if_isset($_POST['hvem']);
		//$opgavenavn=if_isset($_POST['opgavenavn']);
		$opgave=if_isset($_POST['opgave']);
		if($opgave){
			$r=db_fetch_array(db_select("select nr,beskrivelse from opgaver where assign_to = 'sager' and id = '$opgave'",__FILE__ . " linje " . __LINE__)); 
			$opgavenavn="Opgave ".$r['nr'];
			$opgavebeskrivelse=$r['beskrivelse'];
		}
		// Her skal info til tjekskema insættes og opdateres
		$r=db_fetch_array(db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' and fase = '$sag_fase'",__FILE__ . " linje " . __LINE__)); 
		$id=$r['id'];
		db_modify("insert into tjekskema (tjekliste_id,datotid,opg_art,sjak,sag_id,hvem,opg_navn,opg_beskrivelse,sjakid) values ('$id','$datotid','$opg_art','$sjak','$sag_id','$hvem','$opgavenavn','$opgavebeskrivelse','$sjakid')",__FILE__ . " linje " . __LINE__);
		// Her finder vi id fra sidste tjekskema
		$r=db_fetch_array(db_select("select max(id) as id from tjekskema where hvem='$hvem'",__FILE__ . " linje " . __LINE__));
		$tjekskema_id=$r['id'];
		// Her indsættes 'status' og 'status_tekst' i tjekpunkter
		for ($x=1;$x<=$tjekantal;$x++) {
			if ($tjekliste_id[$x]) {
				$qtxt = "insert into tjekpunkter (assign_id,tjekliste_id,status,status_tekst,tjekskema_id) values "; 
				$qtxt.= "('$sag_id','$tjekliste_id[$x]','$kontrolpunkt[$x]','".db_escape_string($status_tekst[$x])."','$tjekskema_id')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		// Her opdateres siden så tjeklisten kan vises 
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/kontrol_sager.php?sag_id=$sag_id&amp;funktion=kontrolskema&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\">";
	} elseif (isset($_POST['kontrolskema']) && $tjekskema_id) {
	
		$tjekliste_id=if_isset($_POST['tjekliste_id']);
		$status_tekst=if_isset($_POST['status_tekst']);
		$opg_art=if_isset($_POST['opg_art']);
		$sjak=if_isset($_POST['sjak']);
		//$sjakid=if_isset($_POST['sjakid']);
		$tjekantal=if_isset($_POST['tjekantal']);
		$kontrolpunkt=if_isset($_POST['kontrolpunkt']);
		$tjekpunkter_id=if_isset($_POST['tjekpunkter_id']);
		$hvem=if_isset($_POST['hvem']);
		//$opgavenavn=if_isset($_POST['opgavenavn']);
		$opgave=if_isset($_POST['opgave']);
		if($opgave){
			$r=db_fetch_array(db_select("select nr,beskrivelse from opgaver where assign_to = 'sager' and id = '$opgave'",__FILE__ . " linje " . __LINE__)); 
			$opgavenavn="Opgave ".$r['nr'];
			$opgavebeskrivelse=$r['beskrivelse'];
		}
		if($sjak) {
			// Her fjerner vi det sidste komma i strengen
			$nysjak = rtrim($sjak, ", ");
			// Her skiller vi initialer fra streng, og laver et array
			$sjakini = explode(", ", $nysjak);
			// Query der henter id fra ansatte
			for ($x=0;$x<count($sjakini);$x++) {
				$r=db_fetch_array(db_select("select * from ansatte where initialer = '$sjakini[$x]'",__FILE__ . " linje " . __LINE__)); 
				$sjakider[$x]=$r['id'];
			}
			// Her filtrerer vi array med ansatte id(er), og fjerner tomme keys i array
			$nysjakider = array_filter($sjakider);
			// Her splejses ansatte id(er) sammen til en streng
			$sjakid = implode(";", $nysjakider);
		}
		$qtxt = "update tjekskema set opg_art='$opg_art',sjak='$sjak',hvem='$hvem',opg_navn='" .db_escape_string($opgavenavn). "',";
		$qtxt.= "opg_beskrivelse='". db_escape_string($opgavebeskrivelse) ."',sjakid='$sjakid' where id = '$tjekskema_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		// Her opdateres tjekpunkter. Hvis der ikke er et tjekpunkt_id indsættes nyt tjekpunkt 
		for ($x=1;$x<=$tjekantal;$x++) {
			if ($tjekliste_id[$x] && !$tjekpunkter_id[$x]) {
				$qtxt = "insert into tjekpunkter (assign_id,tjekliste_id,status,status_tekst,tjekskema_id) values ";
				$qtxt.= "('$sag_id','$tjekliste_id[$x]','$kontrolpunkt[$x]','". db_escape_string($status_tekst[$x]) ."','$tjekskema_id')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} elseif ($tjekliste_id[$x]) {
				$qtxt = "update tjekpunkter set status='$kontrolpunkt[$x]',status_tekst='". db_escape_string($status_tekst[$x]) ."' ";
				$qtxt.= "where id = '$tjekpunkter_id[$x]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
	}
	if (isset($_POST['slet_kontrolskema']) && $tjekskema_id) {
	
		$tjekliste_id=if_isset($_POST['tjekliste_id']);
		$tjekantal=if_isset($_POST['tjekantal']);
		$tjekpunkter_id=if_isset($_POST['tjekpunkter_id']);
		/*
		echo "skemaid: $tjekskema_id";
		echo "sag_id: $sag_id";
		print_r($tjekpunkter_id);
		exit();
	*/
		
		$x=0;
		$q = db_select("select * from bilag_tjekskema where tjekskema_id = '$tjekskema_id'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$bilag_tjekskema_id[$x]=$r['id'];
			$bilag_tjekskema_tjekskema_id[$x]=$r['tjekskema_id'];
			$bilag_tjekskema_bilag_id[$x]=$r['bilag_id'];
			$x++;
		}
		
		if ($bilag_tjekskema_id) {
			for ($x=0;$x<count($bilag_tjekskema_id);$x++) {
				db_modify("delete from bilag_tjekskema where id = '$bilag_tjekskema_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
		
		db_modify("delete from tjekskema where id = '$tjekskema_id'",__FILE__ . " linje " . __LINE__);
		
		for ($x=1;$x<=$tjekantal;$x++) {
			if ($tjekliste_id[$x]) {
				db_modify("delete from tjekpunkter where id = '$tjekpunkter_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/kontrol_sager.php?sag_id=$sag_id\">";
	}
	
	// Visning af tjekskema, hvis tjekskema_id er sat
	if ($tjekskema_id) {
		$r=db_fetch_array(db_select("select * from tjekskema where sag_id='$sag_id' and tjekliste_id='$tjekpunkt_id' and id='$tjekskema_id'",__FILE__ . " linje " . __LINE__));
		$tjekskema_id=$r['id']*1;
		$tjekskema_tjekliste_id=$r['tjekliste_id'];
		$datotid=$r['datotid'];
		$opg_art=htmlspecialchars($r['opg_art']);
		$opg_navn=htmlspecialchars($r['opg_navn']);
		$opg_beskrivelse=htmlspecialchars($r['opg_beskrivelse']);
		$hvem=htmlspecialchars($r['hvem']);
		$sjak=$r['sjak'];
		$sjakid=$r['sjakid'];
	
	
		// Denne funktion laver $sjakid, som indeholder ansatte id(er) om til navn og initialer
		if (isset($sjakid) && $sjakid!=NULL) {
			// Her skiller vi id'erne til et array
			$sjakider = explode(";", $sjakid);
			
			// Query der henter initialer og navn fra ansatte
			for ($x=0;$x<count($sjakider);$x++) {
				$r=db_fetch_array(db_select("select * from ansatte where id = '$sjakider[$x]'",__FILE__ . " linje " . __LINE__)); 
				$sjaknavn[$x]=$r['navn'];
				$sjakini[$x]=$r['initialer'];
				$sjaktitleny[$x]="(".$r['initialer'].")"." ".$r['navn']."\n";
			}
			// Her splejser vi henholdsvis navn og initialer til hver deres streng
			#$sjakinitialer = implode(", ", $sjakini).", ";
			$sjaktitle = implode("", $sjaktitleny);
		}
	}
	// Visning af tjekliste
	$x=0;
	$qtxt = "select * from tjekliste where assign_to = 'sager' and assign_id = '0' and fase = '$sag_fase'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$tjekpunkt[$x]=$r['tjekpunkt']; 
		$fase[$x]=$r['fase']*1;
		$assign_id[$x]=$r['assign_id']*1;
		$punkt_id[$x]=0;
		$gruppe_id[$x]=0;
		$liste_id[$x]=$id[$x];
		$q2 = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '$id[$x]' order by id",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$max_gruppe=$x;
			$id[$x]=$r2['id'];
			$tjekpunkt[$x]=$r2['tjekpunkt']; 
			$assign_id[$x]=$r2['assign_id']*1;
			$fase[$x]=$fase[$x-1];
			$punkt_id[$x]=0;
			$gruppe_id[$x]=$id[$x];
			$liste_id[$x]=$liste_id[$x-1];
			$q3 = db_select("select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by id",__FILE__ . " linje " . __LINE__);
			while ($r3 = db_fetch_array($q3)) {
				$x++;
				$id[$x]=$r3['id'];
				$tjekpunkt[$x]=$r3['tjekpunkt']; 
				$assign_id[$x]=$r3['assign_id']*1;
				$fase[$x]=$fase[$x-1];
				$punkt_id[$x]=$id[$x];
				$gruppe_id[$x]=$gruppe_id[$x-1];
				$liste_id[$x]=$liste_id[$x-1];
			}
		}
	}/*
	echo "id: $id[$x]";
	echo "sag_id: $sag_id";
	echo "sag_fase: $sag_fase";
	echo "tjekpunkt_id: $tjekpunkt_id";
	echo "tjekskema_id: $tjekskema_id";*/
	/*
	$x=0;
	$q = db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$kontrolpunkt[$x]=$r['tjekliste_id'];
		//$status[$x]=$r['status'];
		//$status_tekst[$x]=$r['status_tekst'];
	}*/
	
	// Visning af sagsnr og beskrivelse i breadcrumb
	$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__)); 
	$sagsnr=$r['sagsnr'];
	$sag_beskrivelse=htmlspecialchars($r['beskrivelse']);
	$udf_addr1=htmlspecialchars($r['udf_addr1']);
	$udf_postnr=$r['udf_postnr'];
	$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
	
	// Visning af tjeklistenavn i breadcrumb og overskrift på liste
	$r=db_fetch_array(db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' and id='$tjekpunkt_id'",__FILE__ . " linje " . __LINE__)); 
	$tjekpunktnavn=$r['tjekpunkt'];
	
	// Visning af opgaver fra sagen
	$x=0;
	$q = db_select("select * from opgaver where assign_to = 'sager' and assign_id = '$sag_id' order by nr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$opgave_id[$x]=$r['id'];
		$opgave_nr[$x]=$r['nr'];
		$opgave_sagsnr[$x]=$r['assign_id'];
		$opgave_navn[$x]="Opgave ".$r['nr'];
		$opgave_beskrivelse[$x]=$r['beskrivelse'];
		$opgave_select_beskrivelse[$x]=$opgave_navn[$x].':&nbsp;'.$opgave_beskrivelse[$x].'';
		$x++;
	}
	/*
	// script der updatere tjekskema med beskrivelse fra opgaver. Skal udkommenteres efter opdatering!!!! virker ikke
	$x=0;
	$q = db_select("select * from opgaver where assign_to = 'sager'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$opgid[$x]=$r['id'];
		$opgnr[$x]=$r['nr'];
		$opgsagsnr[$x]=$r['assign_id'];
		$opgnavn[$x]="Opgave ".$r['nr'];
		$opgbeskrivelse[$x]=$r['beskrivelse'];
		$x++;
	}
	
	$x=0;
	$q=db_select("select * from tjekskema",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$tjekid[$x]=$r['id'];
		$tjekskema_opg_navn[$x]=$r['opg_navn'];
		$x++;
	}
	/*
	print "<pre>";
	print_r($tjekid);
	print "</pre>";
	*//*
	for ($x=1;$x<count($tjekid);$x++) {
		//if (!$opgnavn[$x]==NULL) {
			db_modify("update tjekskema set opg_beskrivelse = '$opgbeskrivelse[$x]' where opg_navn = '$opgnavn[$x]' and sag_id = '$opgsagsnr[$x]'",__FILE__ . " linje " . __LINE__);
		//}
	}
	*/
	// Visning af bilag, hvis tilknyttet
	if ($tjekskema_id) { #20170303
		$x=0;
		$q = db_select("SELECT bilag.id as bilagid,bilag_tjekskema.id as bilag_tjekskema_id,* FROM bilag 
										LEFT JOIN bilag_tjekskema ON bilag.id = bilag_tjekskema.bilag_id
										WHERE assign_to = 'sager' and assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$bilag_id[$x]=$r['bilagid'];
			$bilag_title[$x]=$r['navn'];
			$tmp=utf8_decode($r['navn']);
			$bilag_navn[$x]=utf8_encode($tmp);
			$bilag_beskrivelse[$x]=$r['beskrivelse'];
			$bilag_dato[$x]=date("d-m-Y",$r['datotid']);
			$bilag_hvem[$x]=$r['hvem'];
			$bilag_filtype[$x]=$r['filtype'];
			$bilag_tjekskema_id[$x]=$r['bilag_tjekskema_id'];
			$bilag_tjekskema_tjekskema_id[$x]=$r['tjekskema_id'];
			$bilag_tjekskema_bilag_id[$x]=$r['bilag_id'];
			$x++;
		}
	}
	print "<div id=\"breadcrumbbar\">
			<ul id=\"breadcrumb\">
				<li><a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a></li>
				<!--<li><a href=\"#\" title=\"Sample page 1\">Sample page 1</a></li>-->
				<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id\" title=\"Sag: $sagsnr, $sag_beskrivelse, $udf_addr1, $udf_postnr $udf_bynavn\">Tilbage til sag $sagsnr</a></li>
				<li><a href=\"kontrol_sager.php?funktion=kontrolliste&amp;sag_id=$sag_id\" title=\"Tilbage til kontrolskema-liste\">Kontrolskema</a></li>\n";
				print "<li>$tjekpunktnavn</li>\n";
				print "<li style=\"float:right;\"><a href=\"#\" title=\"Print skema\" class=\"print-preview\" onclick=\"printDiv('printableArea')\" style=\"background-image: none;\"><img src=\"../img/printIcon2.png\" alt=\"Print skema\" class=\"printIcon\" /></a></li>"; 
				print "<li style=\"float:right;\"><a href=\"kontrol_sager.php?funktion=emailKontrolskema&amp;sag_id=$sag_id&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\" title=\"Email skema\" style=\"background-image: none;\"><img src=\"../img/mail.png\" alt=\"Email skema\" class=\"printIcon\" /></a></li>";
				// Her er button til jQuery.printElement
				//print "<li style=\"float:right;\"><input type=\"button\" value=\"print\" id=\"simplePrint\" /></li>";
				// Her er button til google cloud print
				//print "<li style=\"float:right;\"><div id=\"print_button_container\"></div></li>";
				print "
			</ul>
			
		</div><!-- end of breadcrumbbar -->\n";
	
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<form name=\"diverse\" action=\"kontrol_sager.php?sag_id=$sag_id&amp;funktion=kontrolskema&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\" method=\"post\">\n";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
	print "<tbody>\n";
	
	print "<tr><td width=\"100%\" align=\"center\">\n";
	print "<div style=\"#background-color:lightblue;height:40px;padding-top:5px;\"><p>Vælg hvilken opgave skeamaet hører til:&nbsp;\n";
	print "<select style=\"width:110px;\" id=\"opgavenavn\" name=\"opgave\">\n";
				for ($x=0;$x<=count($opgave_nr);$x++) {
					if ($opg_navn==$opgave_navn[$x]) print "<option title=\"$opgave_navn[$x]&#013;$opgave_beskrivelse[$x]\" value=\"$opgave_id[$x]\">$opgave_select_beskrivelse[$x]&nbsp;</option>\n";	
				} 
				for ($x=0;$x<=count($opgave_nr);$x++) {
					if ($opg_navn!=$opgave_navn[$x]) print "<option title=\"$opgave_navn[$x]&#013;$opgave_beskrivelse[$x]\" value=\"$opgave_id[$x]\">$opgave_select_beskrivelse[$x]&nbsp;</option>\n";	
				}
	print "</select></p></div>";
	print "<div id=\"printableArea\">\n";
	//print "<a style=\"float:right;\" href=\"javascript:window.print()\">Print</a>\n";
	if (!$tjekskema_id) $hvem = $ansat_navn;
	($opg_navn)?$opg='til&nbsp;'.$opg_navn:$opg=NULL;
	print "<h3 class=\"printHeadLineSkema\">$tjekpunktnavn $opg</h3>\n";
	print "<table border=\"0\" cellspacing=\"0\" class=\"kontrolskema\" style=\"table-layout:fixed;\">\n";
	print "<colgroup>
    <col width=\"100\">
    <col width=\"125\">
    <col width=\"70\">
		<col width=\"80\">
		<col width=\"115\">
    <col width=\"105\">
    <col width=\"0\">
  </colgroup>
  <tbody >
		<tr>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
		</tr>
  </tbody>\n";
	print "<tbody>\n";
	print "<tr><td colspan=\"2\" class=\"printtxt\"><p><b>Opstillingsadresse:</b></p><p>$udf_addr1, $udf_postnr $udf_bynavn</p></td>\n";
	print "<td rowspan=\"2\" align=\"center\" valign=\"top\"><p><b>Status:</b></p></td>\n";
	print "<td rowspan=\"2\" align=\"center\" valign=\"top\" class=\"printdate\"><p><b>Dato:</b></p><p>".date("d-m-Y",$datotid)."</p></td>\n";
	print "<td rowspan=\"2\" align=\"center\" valign=\"top\"><p><b>Opgavens art:</b></p><textarea class=\"textAreaSager autosize kontrolskema_font\" name=\"opg_art\" rows=\"4\" cols=\"12\" style=\"height:64px;width:95px;\">".htmlspecialchars($opg_art)."</textarea></td>\n";
	print "<td rowspan=\"2\" align=\"center\" valign=\"top\"><p><b>Sjak:</b></p><textarea class=\"textAreaSager autosize kontrolskema_font sjak\" name=\"sjak\" rows=\"4\" cols=\"10\" title=\"$sjaktitle\" style=\"height:64px;width:85px;\">".htmlspecialchars($sjak)."</textarea></td>\n"; // onfocus=\"var val=this.value; this.value=''; this.value= val;\"
	print "<td style=\"height:0px;padding:0px;margin:0px;border:none;\"><input type=\"hidden\" class=\"sjakid\" name=\"sjakid\" value=\"\"></td></tr>\n";
	print "<tr><td colspan=\"2\" class=\"printtxt\"><input type=\"hidden\" name=\"hvem\" value='$ansat_navn'><p><b>Kontroleret af:</b></p><p>".htmlspecialchars($hvem)."</p></td></tr>\n";
	if ($opg_beskrivelse) print "<tr><td colspan=\"2\" class=\"printtxt\" style=\"vertical-align:top;\"><p><b>Opgave beskrivelse:</b></p></td><td colspan=\"4\" class=\"printtxt\"><p><i><b>$opg_navn:</b> $opg_beskrivelse&nbsp;</i></p></td></tr>\n";
	// Array til status select-box i kontrolskema
	$value = array(0,1,2,3,4);
	$color = array("white","green","yellow","red","white");
	$option_name = array("&nbsp;","OK","Fejl","Kritisk","N/A");
	
	for ($x=1;$x<=count($id);$x++) {
		
		if (!$gruppe_id[$x] && !$punkt_id[$x]) {
			
			print "<tr style=\"display:none;\"><td colspan=\"6\"><input type=\"hidden\" name=\"tjekantal\" value='".count($id)."'><input type=\"hidden\" name=\"id[$x]\" value='$id[$x]'></td></tr>\n";
			$l_id=$id[$x];
		}
		if ($gruppe_id[$x] && !$punkt_id[$x]) { 
		
			print "<tr><td class=\"printtxt\" colspan=\"6\" title=\"$assign_id[$x]==$l_id\"><input type=\"hidden\" name=\"tjekgruppe[$x]\" value='$id[$x]'><p><b>".$tjekpunkt[$x]."</b></p></td></tr>\n"; #<td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"aktiv[$x]\"></td></tr>\n";
		}
		// Kontrolskema vises hvis der er id
		if ($punkt_id[$x] && $tjekskema_id) { 
		
			$r=db_fetch_array(db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id' and tjekliste_id = '$id[$x]'",__FILE__ . " linje " . __LINE__)); 
			$tjekpunkter_id=$r['id'];
			$status=$r['status'];
			$status_tekst=$r['status_tekst'];
		//echo "id: $tjekpunkter_id";
			/*
			$x=0;
			$q=db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id' and tjekliste_id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				//$tjekpunkter_id[$x]=$r['id'];
				$x++;	
			}*/
			//if (!isset($kontrolpunkt[$x])) $kontrolpunkt[$x]=NULL;
		
			//(in_array($id[$x],$kontrolpunkt))?$tmp="checked='checked'":$tmp=NULL;
			$statcolor = NULL;
				if ($status<=0) $statcolor = "background-color:white;";
				if ($status==1) $statcolor = "background-color:green;";
				if ($status==2) $statcolor = "background-color:yellow;";
				if ($status==3) $statcolor = "background-color:red;";
				if ($status==4) $statcolor = "background-color:white;";
			
			print "<tr><td colspan=\"2\" class=\"printtxt\" title=\"$assign_id[$x]==$l_id\"><p>".$tjekpunkt[$x]."</p></td>\n";
			print "<td class=\"kontrol_color\" style=\"$statcolor\"><input type=\"hidden\" name=\"tjekliste_id[$x]\" value='$id[$x]'><input type=\"hidden\" name=\"tjekskema_id\" value='$tjekskema_id'><input type=\"hidden\" name=\"tjekpunkter_id[$x]\" value='$tjekpunkter_id'>\n";
			print "<select name=\"kontrolpunkt[$x]\" class=\"kontrol_status\" >\n";
			for($y=0;$y<count($value);$y++) {
				if ($status==$value[$y]) print "<option class=\"kontrol_status_option\" value=\"$value[$y]\" style=\"background-color:$color[$y];\">$option_name[$y]</option>\n";
			}
			for($y=0;$y<count($value);$y++) {
				if ($status!=$value[$y]) print "<option class=\"kontrol_status_option\" value=\"$value[$y]\" style=\"background-color:$color[$y];\">$option_name[$y]</option>\n";
			}
			print "</select>\n";
			//print "<input type=\"hidden\" name=\"pre_kontrolpunkt[$x]\" value='$tmp'>\n";
			//print "<input class=\"inputbox\" $disabled type=\"checkbox\" name=\"kontrolpunkt[$x]\" $tmp>";
			/*
			print "<select name=\"kontrolpunkt[$x]\" class=\"kontrol_status\" >
				<option value=\"0\" style=\"background-color:white;\">&nbsp;</option>
				<option value=\"1\" style=\"background-color:green;\">green</option>
				<option value=\"2\" style=\"background-color:yellow;\">yellow</option>
				<option value=\"3\" style=\"background-color:red;\">red</option>
			</select>\n";
			*/
			
			print "</td>\n";
			
			print "<td colspan=\"3\" class=\"printtxtbox\"><textarea class=\"textAreaSager autosize kontrolskemaText kontrolskema_font\" name=\"status_tekst[$x]\" rows=\"1\" cols=\"37\">".htmlspecialchars($status_tekst)."</textarea></td></tr>\n";
		}	
		// Nyt kontrolskema vises her
		if ($punkt_id[$x] && !$tjekskema_id) { 
		/*
			$r=db_fetch_array(db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id' and tjekliste_id = '$id[$x]'",__FILE__ . " linje " . __LINE__)); 
			$status=$r['status'];
			$status_tekst=$r['status_tekst'];
		*/
			//if (!isset($kontrolpunkt[$x])) $kontrolpunkt[$x]=NULL;
		
			//(in_array($id[$x],$kontrolpunkt))?$tmp="checked='checked'":$tmp=NULL;

			print "<tr><td colspan=\"2\" class=\"printtxt\" title=\"$assign_id[$x]==$l_id\"><p>".$tjekpunkt[$x]."</p></td>\n";
			print "<td align=\"center\" class=\"kontrol_color\"><input type=\"hidden\" name=\"tjekliste_id[$x]\" value='$id[$x]'><input type=\"hidden\" name=\"tjekskema_id\" value='$tjekskema_id'>\n";
			/*
			print "<select name=\"kontrolpunkt[$x]\" class=\"kontrol_status\" >\n";
			for($y=0;$y<count($value);$y++) {
				if ($status==$value[$y]) print "<option value=\"$value[$y]\" style=\"background-color:$color[$y];\">$option_name[$y]</option>\n";
			}
			for($y=0;$y<count($value);$y++) {
				if ($status!=$value[$y]) print "<option value=\"$value[$y]\" style=\"background-color:$color[$y];\">$option_name[$y]</option>\n";
			}
			print "</select>\n";
			*/
			//print "<input type=\"hidden\" name=\"pre_kontrolpunkt[$x]\" value='$tmp'>\n";
			//print "<input class=\"inputbox\" $disabled type=\"checkbox\" name=\"kontrolpunkt[$x]\" $tmp>";
			
			print "<select name=\"kontrolpunkt[$x]\" class=\"kontrol_status\" >
				<option value=\"0\" style=\"background-color:white;\">&nbsp;</option>
				<option value=\"1\" style=\"background-color:green;\">OK</option>
				<option value=\"2\" style=\"background-color:yellow;\">Fejl</option>
				<option value=\"3\" style=\"background-color:red;\">Kritisk</option>
				<option value=\"4\" style=\"background-color:white;\">N/A</option>
			</select>\n";
			
			
			print "</td>\n";
			
			print "<td colspan=\"3\"><textarea class=\"textAreaSager autosize kontrolskemaText\" name=\"status_tekst[$x]\" rows=\"1\" cols=\"37\">".htmlspecialchars($status_tekst)."</textarea></td></tr>\n";
		}
	}	
	
	print "</tbody>\n";
	print "</table>\n";
	print "</div><!-- end of printableArea -->\n";
	
	print "<table border=\"0\" cellspacing=\"0\">\n";
	print "<tbody>\n";
	print "<tr><td align=\"center\"><input type=\"submit\" class=\"button gray small\" accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"kontrolskema\">\n";
	if ($tjekskema_id) {
		print "<input class=\"button rosy small\" type=\"submit\" name=\"slet_kontrolskema\" style=\"margin-left:10px;\" value=\"Slet kontrolskema\" onclick=\"return confirm('Vil du slette kontrolskemaet?');\">\n";
		//print "<input class=\"button gray small\" type=\"submit\" name=\"afslut_kontrolskema\" style=\"margin-left:10px;\" value=\"Godkend\" onclick=\"return confirm('Du er ved at godkende skemaet.\n Der vil ikke være muligt at rette eller slette derefter');\">\n";
	}
	print "</td></tr>\n";
	print "</tbody></table>\n";
	
	if ($bilag_id) { #20170303
		print "<br>";
		print "<h3>Bilag:</h3>\n";
		print "<table border=\"0\" cellspacing=\"0\" class=\"tableBilag\">\n";
		print "<tbody class=\"tableBilagZebra tableBilagBorderTop tableBilagBorderBottom\">\n";
		for ($y=0;$y<count($bilag_id);$y++) {
			print "<tr><td><p>$bilag_beskrivelse[$y]</p></td><td align=\"right\"><p><a href=\"../bilag/$db/$sag_id/$bilag_id[$y].$bilag_filtype[$y]\" target=\"blank\" class=\"button blue small\">Vis</a></p></td></tr>\n";
		}
		print "</tbody>\n";
		print "</table>\n";
	}
	
	print "</td></tr>\n";
	print "</tbody></table>\n";
	print "</form>\n";
} # endfunc kontrolskema

function arbejdsseddel() {


	$sag_id=if_isset($_GET['sag_id'],0);
	$sag_fase=if_isset($_GET['sag_fase'],0);
	$tjekpunkt_id=if_isset($_GET['tjek_id'],0);
	$tjekskema_id=if_isset($_GET['tjekskema_id'],0);
	
	if(isset($_POST['sag_id'])       && $_POST['sag_id'])   $sag_id       = $_POST['sag_id'];
	if(isset($_POST['sag_fase'])     && $_POST['sag_fase']) $sag_fase     = $_POST['sag_fase'];
	if(isset($_POST['tjek_id'])      && $_POST['sag_fase']) $tjekpunkt_id = $_POST['tjek_id'];
	if(isset($_POST['tjekskema_id']) && $_POST['sag_fase']) $tjekskema_id = $_POST['tjekskema_id'];
	/*$sag_id=if_isset($_POST['sag_id']);
	$sag_fase=if_isset($_POST['sag_fase']);
	$tjekpunkt_id=if_isset($_POST['tjek_id']);
	*/
	
	//if(isset($opgave_id)) $_GET['opgave_id'];
	global $brugernavn;
	global $sprog_id;
	global $regnskab;
	global $ansat_navn;
	global $db;
	//global $ansat_id;
	//global $bgcolor;
	//global $bgcolor5;
//echo "id: $ansat_id";
	//$linjebg1="ffffff";
	//$linjebg2="f0f0f0";
	//$r = db_fetch_array(db_select("select status from sager where id = '$sag_id'",__FILE__ . " linje " . __LINE__));
	//($sag_fase<$r['status'])?$disabled="DISABLED=\"disabled\"":$disabled=NULL;
	
	if(!$tjekskema_id) {
		$datotid=date("U");
		$udf_dato=date("d-m-Y",$datotid);
	} 
	
	if (isset($_POST['kontrolskema']) && !$tjekskema_id) {
		
		$tjekliste_id=if_isset($_POST['tjekliste_id']);
		$status_tekst=if_isset($_POST['status_tekst']);
		$opg_art=if_isset($_POST['opg_art']);
		$sjak=if_isset($_POST['sjak']);
		$sjakid=if_isset($_POST['sjakid']);
		$tjekantal=if_isset($_POST['tjekantal']);
		$kontrolpunkt=if_isset($_POST['kontrolpunkt']);
		$udf_dato=if_isset($_POST['udf_dato']);
		list ($day, $month, $year) = explode('-', $udf_dato);
		if (checkdate($month, $day, $year) && (strlen($year)==4)) { // Validering af dato
			$udf_dato = $day . "-" . $month . "-" . $year;
			$unixdato=strtotime($udf_dato);// Formatere dato til UNIX
		} else {
			$datotid=date("U");
			$udf_dato=date("d-m-Y",$datotid);
			$unixdato=strtotime($udf_dato);
		}
		$man_trans=if_isset($_POST['man_trans']); 
		$stillads_til=if_isset($_POST['stillads_til']);
		$opgave=if_isset($_POST['opgave']);
		if($opgave){
			$r=db_fetch_array(db_select("select nr,beskrivelse from opgaver where assign_to = 'sager' and id = '$opgave'",__FILE__ . " linje " . __LINE__)); 
			$opgavenavn="Opgave ".$r['nr'];
			$opgavebeskrivelse=$r['beskrivelse'];
		}
		//echo "OpgNavn: $opgavenavn, Beskr: $opgavebeskrivelse";
		#print_r($kontrolpunkt);
		//exit();
		// Her skal info til tjekskema insættes og opdateres
		$qtxt = "select * from tjekliste where assign_to = 'sager' and assign_id = '0' and fase = '$sag_fase'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { 
			$id=$r['id'];
			$qtxt = "insert into tjekskema ";
			$qtxt.= "(tjekliste_id,datotid,opg_art,sjak,sag_id,hvem,man_trans,stillads_til,opg_navn,opg_beskrivelse,sjakid)";
			$qtxt.= " values "; 
			$qtxt.= "('$id','$unixdato','$opg_art','$sjak','$sag_id','$ansat_navn','$man_trans',";
			$qtxt.= "'". db_escape_string($stillads_til) ."','". db_escape_string($opgavenavn) ."',";
			$qtxt.= "'". db_escape_string($opgavebeskrivelse) ."','$sjakid')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		// Her finder vi id fra sidste tjekskema
			$qtxt = "select max(id) as id from tjekskema where hvem='$ansat_navn'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$tjekskema_id=$r['id'];
		}
		// Her indsættes 'status' og 'status_tekst' i tjekpunkter
		for ($x=1;$x<=$tjekantal;$x++) {
			if ($tjekliste_id[$x]) {
				if (!isset($kontrolpunkt[$x])) $kontrolpunkt[$x] = 0;// Hvis checkbox value er tom, indsættes 0
				$qtxt = "insert into tjekpunkter (assign_id,tjekliste_id,status,status_tekst,tjekskema_id) ";
				$qtxt.= "values ";
				$qtxt.= "('$sag_id','$tjekliste_id[$x]','$kontrolpunkt[$x]','$status_tekst[$x]','$tjekskema_id')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		// Her opdateres siden så tjeklisten kan vises 
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/kontrol_sager.php?sag_id=$sag_id&amp;funktion=arbejdsseddel&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\">";
	} elseif (isset($_POST['kontrolskema']) && $tjekskema_id) {
		$tjekliste_id=if_isset($_POST['tjekliste_id']);
		//$status_tekst=if_isset($_POST['status_tekst']);
		$opg_art=if_isset($_POST['opg_art']);
		$sjak=if_isset($_POST['sjak']);
		//$sjakid=if_isset($_POST['sjakid']);
		$man_trans=if_isset($_POST['man_trans']);
		$stillads_til=if_isset($_POST['stillads_til']);
		$tjekantal=if_isset($_POST['tjekantal']);
		$kontrolpunkt=if_isset($_POST['kontrolpunkt']);
		$tjekpunkter_id=if_isset($_POST['tjekpunkter_id']);
		$udf_dato=if_isset($_POST['udf_dato']);
		list ($day, $month, $year) = explode('-', $udf_dato); 
		if (checkdate($month, $day, $year) && (strlen($year)==4)) { // Validering af dato
			$udf_dato = $day . "-" . $month . "-" . $year;
			$unixdato=strtotime($udf_dato);// Formatere dato til UNIX
		} else {
			$r=db_fetch_array(db_select("select datotid from tjekskema where id = '$tjekskema_id'",__FILE__ . " linje " . __LINE__)); 
			$unixdato=$r['datotid'];
		}
		$opgave=if_isset($_POST['opgave']);
		if($opgave){
			$r=db_fetch_array(db_select("select nr,beskrivelse from opgaver where assign_to = 'sager' and id = '$opgave'",__FILE__ . " linje " . __LINE__)); 
			$opgavenavn="Opgave ".$r['nr'];
			$opgavebeskrivelse=$r['beskrivelse'];
		}
		if($sjak) {
			// Her fjerner vi det sidste komma i strengen
			$nysjak = rtrim($sjak, ", ");
			// Her skiller vi initialer fra streng, og laver et array
			$sjakini = explode(", ", $nysjak);
			// Query der henter id fra ansatte
			for ($x=0;$x<count($sjakini);$x++) {
				$r=db_fetch_array(db_select("select * from ansatte where initialer = '$sjakini[$x]'",__FILE__ . " linje " . __LINE__)); 
				$sjakider[$x]=$r['id'];
			}
			// Her filtrerer vi array med ansatte id(er), og fjerner tomme keys i array
			$nysjakider = array_filter($sjakider);
			// Her splejses ansatte id(er) sammen til en streng
			$sjakid = implode(";", $nysjakider);
		}
		$qtxt = "update tjekskema set datotid='$unixdato',opg_art='$opg_art',sjak='". db_escape_string($sjak) ."',";
		$qtxt.= "man_trans='$man_trans',stillads_til='". db_escape_string($stillads_til) ."',opg_navn='$opgavenavn',";
		$qtxt.= "opg_beskrivelse='". db_escape_string($opgavebeskrivelse) ."',";
		$qtxt.= "sjakid='$sjakid' where id = '$tjekskema_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		// Her opdateres tjekpunkter. Hvis der ikke er et tjekpunkt_id indsættes nyt tjekpunkt 
		for ($x=1;$x<=$tjekantal;$x++) {
			if ($tjekliste_id[$x] && !$tjekpunkter_id[$x]) {
				if (!isset($kontrolpunkt[$x])) $kontrolpunkt[$x] = 0;// Hvis checkbox value er tom, indsættes 0
					db_modify("insert into tjekpunkter (assign_id,tjekliste_id,status,status_tekst,tjekskema_id) values ('$sag_id','$tjekliste_id[$x]','$kontrolpunkt[$x]','$status_tekst[$x]','$tjekskema_id')",__FILE__ . " linje " . __LINE__);
				} elseif ($tjekliste_id[$x]) {
				if (!isset($kontrolpunkt[$x])) $kontrolpunkt[$x] = 0;// Hvis checkbox value er tom, indsættes 0
					db_modify("update tjekpunkter set status='$kontrolpunkt[$x]' where id = '$tjekpunkter_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
	}
	if (isset($_POST['slet_kontrolskema']) && $tjekskema_id) {
	
		$tjekliste_id=if_isset($_POST['tjekliste_id']);
		$tjekantal=if_isset($_POST['tjekantal']);
		$tjekpunkter_id=if_isset($_POST['tjekpunkter_id']);
		/*
		echo "skemaid: $tjekskema_id";
		echo "sag_id: $sag_id";
		print_r($tjekpunkter_id);
		exit();
	*/
	
		$x=0;
		$q = db_select("select * from bilag_tjekskema where tjekskema_id = '$tjekskema_id'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$bilag_tjekskema_id[$x]=$r['id'];
			$bilag_tjekskema_tjekskema_id[$x]=$r['tjekskema_id'];
			$bilag_tjekskema_bilag_id[$x]=$r['bilag_id'];
			$x++;
		}
		
		if ($bilag_tjekskema_id) {
			for ($x=0;$x<count($bilag_tjekskema_id);$x++) {
				db_modify("delete from bilag_tjekskema where id = '$bilag_tjekskema_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
		
		db_modify("delete from tjekskema where id = '$tjekskema_id'",__FILE__ . " linje " . __LINE__);
		
		for ($x=1;$x<=$tjekantal;$x++) {
			if ($tjekliste_id[$x] && $tjekpunkter_id[$x]) {
				db_modify("delete from tjekpunkter where id = '$tjekpunkter_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/kontrol_sager.php?sag_id=$sag_id\">";
	}
	
	if (isset($_POST['kopi_kontrolskema']) && $tjekskema_id) { #20150904
	
		$tjekliste_id=if_isset($_POST['tjekliste_id']);
		$tjekantal=if_isset($_POST['tjekantal']);
		$tjekpunkter_id=if_isset($_POST['tjekpunkter_id']);
		$kontrolpunkt=if_isset($_POST['kontrolpunkt']);
		$man_trans=if_isset($_POST['man_trans']);
		$stillads_til=if_isset($_POST['stillads_til']);
		$datotid=date("U");
		$udf_dato=date("d-m-Y",$datotid);
		$unixdato=strtotime($udf_dato);
		/*
		echo "skemaid: $tjekskema_id<br>";
		echo "sag_id: $sag_id<br>";
		echo "man_trans: $man_trans<br>";
		echo "stillads_til: $stillads_til<br>";
		echo "sag_fase: $sag_fase<br>";
		echo "tjekliste_id: $tjekpunkt_id<br>";
		echo "udf_dato: $udf_dato / $unixdato<br>";
		//print_r($tjekpunkter_id);
		//exit();
		*/
		db_modify("insert into tjekskema (tjekliste_id,datotid,sag_id,hvem,man_trans,stillads_til) values ('$tjekpunkt_id','$unixdato','$sag_id','$ansat_navn','$man_trans','$stillads_til')",__FILE__ . " linje " . __LINE__);
		
		$r=db_fetch_array(db_select("select max(id) as id from tjekskema",__FILE__ . " linje " . __LINE__));
		$ny_tjekskema_id=$r['id']; 
		/*
		echo "ny_tjekskema_id: $ny_tjekskema_id<br>";
		exit();
		*/
		// Her indsættes 'status' i tjekpunkter
		for ($x=1;$x<=$tjekantal;$x++) {
			if ($tjekliste_id[$x]) {
				if (!isset($kontrolpunkt[$x])) $kontrolpunkt[$x] = 0;// Hvis checkbox value er tom, indsættes 0
					db_modify("insert into tjekpunkter (assign_id,tjekliste_id,status,tjekskema_id) values ('$sag_id','$tjekliste_id[$x]','$kontrolpunkt[$x]','$ny_tjekskema_id')",__FILE__ . " linje " . __LINE__);
			
			}
		}
		// Her opdateres siden så kopi tjeklisten kan vises 
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/kontrol_sager.php?sag_id=$sag_id&amp;funktion=arbejdsseddel&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$ny_tjekskema_id\">";
	
		
	}
	
	// Visning af tjekskema, hvis tjekskema_id er sat
	if ($tjekskema_id) {
		$r=db_fetch_array(db_select("select * from tjekskema where sag_id='$sag_id' and tjekliste_id='$tjekpunkt_id' and id='$tjekskema_id'",__FILE__ . " linje " . __LINE__));
		$tjekskema_id=$r['id']*1;
		$tjekskema_tjekliste_id=$r['tjekliste_id'];
		$udf_dato=date("d-m-Y",$r['datotid']);
		$opg_art=htmlspecialchars($r['opg_art']);
		$opg_navn=htmlspecialchars($r['opg_navn']);
		$opg_beskrivelse=htmlspecialchars($r['opg_beskrivelse']);
		$sjak=$r['sjak'];
		$sjakid=$r['sjakid'];
		$hvem=htmlspecialchars($r['hvem']);
		$stillads_til=htmlspecialchars($r['stillads_til']);
		$man_trans=htmlspecialchars($r['man_trans']);
	
	
		// Denne funktion laver $sjak, som indeholder ansatte id(er) om til navn og initialer
		if (isset($sjakid) && $sjakid!=NULL) {
			// Her skiller vi id'erne til et array
			$sjakider = explode(";", $sjakid);
			
			// Query der henter initialer og navn fra ansatte
			for ($x=0;$x<count($sjakider);$x++) {
				$r=db_fetch_array(db_select("select * from ansatte where id = '$sjakider[$x]'",__FILE__ . " linje " . __LINE__)); 
				$sjaknavn[$x]=$r['navn'];
				$sjakini[$x]=$r['initialer'];
				$sjaktitleny[$x]="(".$r['initialer'].")"." ".$r['navn']."\n";
			}
			// Her splejser vi henholdsvis navn og initialer til hver deres streng
			#$sjakinitialer = implode(", ", $sjakini).", ";
			$sjaktitle = implode("", $sjaktitleny);
		}
	}
	
	// Visning af tjekliste
	$x=0;
	$qtxt = "select * from tjekliste where assign_to = 'sager' and assign_id = '0' and fase = '$sag_fase'";
#cho __line__." $qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$tjekpunkt[$x]=$r['tjekpunkt']; 
#cho __line__." $tjekpunkt[$x]<br>";
		$fase[$x]=$r['fase']*1;
		$assign_id[$x]=$r['assign_id']*1;
		$punkt_id[$x]=0;
		$gruppe_id[$x]=0;
		$liste_id[$x]=$id[$x];
		$qtxt = "select * from tjekliste where assign_to = 'sager' and assign_id = '$id[$x]' order by id";
#cho __line__." $qtxt<br>";
		$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$max_gruppe=$x;
			$id[$x]=$r2['id'];
			$tjekpunkt[$x]=htmlspecialchars($r2['tjekpunkt']); 
#cho __line__." $tjekpunkt[$x]<br>";
			$assign_id[$x]=$r2['assign_id']*1;
			$fase[$x]=$fase[$x-1];
			$punkt_id[$x]=0;
			$gruppe_id[$x]=$id[$x];
			$liste_id[$x]=$liste_id[$x-1];
			$qtxt = "select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by id";
#cho __line__." $qtxt<br>";
			$q3 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r3 = db_fetch_array($q3)) {
				$x++;
				$id[$x]=$r3['id'];
				$tjekpunkt[$x]=htmlspecialchars($r3['tjekpunkt']); 
#cho __line__." $tjekpunkt[$x]<br>";
				$assign_id[$x]=$r3['assign_id']*1;
				$fase[$x]=$fase[$x-1];
				$punkt_id[$x]=$id[$x];
				$gruppe_id[$x]=$gruppe_id[$x-1];
				$liste_id[$x]=$liste_id[$x-1];
			}
		}
	}/*
	echo "id: $id[$x]";
	echo "sag_id: $sag_id";
	echo "sag_fase: $sag_fase";
	echo "tjekpunkt_id: $tjekpunkt_id";
	echo "tjekskema_id: $tjekskema_id";*/
	/*
	$x=0;
	$q = db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$kontrolpunkt[$x]=$r['tjekliste_id'];
		//$status[$x]=$r['status'];
		//$status_tekst[$x]=$r['status_tekst'];
	}*/
	
	// Visning af sagsnr og beskrivelse i breadcrumb og liste
	$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__)); 
	$sagsnr=$r['sagsnr'];
	$sag_beskrivelse=htmlspecialchars($r['beskrivelse']);
	$sag_firmanavn=htmlspecialchars($r['firmanavn']);
	$sag_kontakt=htmlspecialchars($r['kontakt']);
	$udf_addr1=htmlspecialchars($r['udf_addr1']);
	$udf_postnr=$r['udf_postnr'];
	$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
	$sag_omfang=htmlspecialchars($r['omfang']);
	
	// Visning af tjeklistenavn i breadcrumb og overskrift på liste
	$r=db_fetch_array(db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' and id='$tjekpunkt_id'",__FILE__ . " linje " . __LINE__)); 
	$tjekpunktnavn=$r['tjekpunkt'];
	
	// Visning af opgaver fra sagen
	$x=0;
	$q = db_select("select * from opgaver where assign_to = 'sager' and assign_id = '$sag_id' order by nr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$opgave_id[$x]=$r['id'];
		$opgave_nr[$x]=$r['nr'];
		$opgave_navn[$x]="Opgave ".$r['nr'];
		$opgave_beskrivelse[$x]=$r['beskrivelse'];
		$opgave_select_beskrivelse[$x]=$opgave_navn[$x].':&nbsp;'.$opgave_beskrivelse[$x].'';
		$x++;
	}
	
	// Visning af bilag, hvis tilknyttet
	if ($tjekskema_id) { #20170303
		$x=0;
		$q = db_select("SELECT bilag.id as bilagid,bilag_tjekskema.id as bilag_tjekskema_id,* FROM bilag 
										LEFT JOIN bilag_tjekskema ON bilag.id = bilag_tjekskema.bilag_id
										WHERE assign_to = 'sager' and assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$bilag_id[$x]=$r['bilagid'];
			$bilag_title[$x]=$r['navn'];
			$tmp=utf8_decode($r['navn']);
			$bilag_navn[$x]=utf8_encode($tmp);
			$bilag_beskrivelse[$x]=$r['beskrivelse'];
			$bilag_dato[$x]=date("d-m-Y",$r['datotid']);
			$bilag_hvem[$x]=$r['hvem'];
			$bilag_filtype[$x]=$r['filtype'];
			$bilag_tjekskema_id[$x]=$r['bilag_tjekskema_id'];
			$bilag_tjekskema_tjekskema_id[$x]=$r['tjekskema_id'];
			$bilag_tjekskema_bilag_id[$x]=$r['bilag_id'];
			$x++;
		}
	}
	print "<div id=\"breadcrumbbar\">
			<ul id=\"breadcrumb\">
				<li><a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a></li>
				<!--<li><a href=\"#\" title=\"Sample page 1\">Sample page 1</a></li>-->
				<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id\" title=\"Sag: $sagsnr, $sag_beskrivelse, $udf_addr1, $udf_postnr $udf_bynavn\">Tilbage til sag $sagsnr</a></li>
				<li><a href=\"kontrol_sager.php?funktion=kontrolliste&amp;sag_id=$sag_id\" title=\"Tilbage til kontrolskema-liste\">Kontrolskema</a></li>\n";
				print "<li>$tjekpunktnavn</li>\n";
				print "<li style=\"float:right;\"><a href=\"#\" title=\"Print skema\" class=\"print-preview\" onclick=\"printDiv('printableArea')\" style=\"background-image: none;\"><img src=\"../img/printIcon2.png\" alt=\"Print skema\" class=\"printIcon\" /></a></li>";
				print "<li style=\"float:right;\"><a href=\"kontrol_sager.php?funktion=emailArbejdsseddel&amp;sag_id=$sag_id&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\" title=\"Email skema\" style=\"background-image: none;\"><img src=\"../img/mail.png\" alt=\"Email skema\" class=\"printIcon\" /></a></li>";
				print "
			</ul>
			
		</div><!-- end of breadcrumbbar -->\n";
	
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<form name=\"diverse\" action=\"kontrol_sager.php?sag_id=$sag_id&amp;funktion=arbejdsseddel&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\" method=\"post\">\n";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
	print "<tbody>\n";
	
	print "<tr><td width=\"100%\" align=\"center\">\n";
	print "<div style=\"#background-color:lightblue;height:40px;padding-top:5px;\"><p>Vælg hvilken opgave skeamaet hører til:&nbsp;\n"; 
	print "<select style=\"width:110px;\" id=\"opgavenavn\" name=\"opgave\">\n";
				for ($x=0;$x<=count($opgave_nr);$x++) {
					if ($opg_navn==$opgave_navn[$x]) print "<option title=\"$opgave_navn[$x]&#013;$opgave_beskrivelse[$x]\" value=\"$opgave_id[$x]\">$opgave_select_beskrivelse[$x]&nbsp;</option>\n";	
				} 
				for ($x=0;$x<=count($opgave_nr);$x++) {
					if ($opg_navn!=$opgave_navn[$x]) print "<option title=\"$opgave_navn[$x]&#013;$opgave_beskrivelse[$x]\" value=\"$opgave_id[$x]\">$opgave_select_beskrivelse[$x]&nbsp;</option>\n";	
				}
	print "</select></p><p><span id=\"dataText\"></span></p></div>";
	print "<div id=\"printableArea\">\n";
	//print "<a style=\"float:right;\" href=\"javascript:window.print()\">Print</a>\n";
	if (!$tjekskema_id) $hvem = $ansat_navn;
	($opg_navn)?$opg='til&nbsp;'.$opg_navn:$opg=NULL;
	print "<h3 class=\"printHeadLineSkema\">$tjekpunktnavn $opg</h3>\n";
	print "<table border=\"0\" cellspacing=\"0\" class=\"kontrolskema printKontrolskematxt\">\n";
	print "<colgroup>
    <col width=\"75\">
    <col width=\"75\">
		<col width=\"150\">
		<col width=\"100\">
    <col width=\"100\">
		<col width=\"95\">
		<col width=\"0\">
  </colgroup>
  <tbody >
		<tr>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
		</tr>
  </tbody>\n";
	print "<tbody>\n";
	print "<tr><td colspan=\"4\"><p><b>Udføres dato:&nbsp;</b><input name=\"udf_dato\" id=\"datepicker\" type=\"text\" style=\"width:95px;\" class=\"printBorderNone kontrolskema_font\" value='$udf_dato'/></p></td>\n";// .date("d-m-Y",$udf_dato).
	print "<td colspan=\"2\"><p><b>Sagsnr:&nbsp;</b> $sagsnr</p></td></tr>\n";
	print "<tr><td colspan=\"2\" style=\"vertical-align:top;#width:200px;\"><p><b>Opstillingsadresse:</b></p><p>$udf_addr1, $udf_postnr $udf_bynavn</p></td>\n";
	print "<td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Kunde:</b></p><p>$sag_firmanavn</p></td>\n";
	print "<td colspan=\"2\" style=\"vertical-align:top;\"><p><b>kontakt:</b></p><p>$sag_kontakt&nbsp;</p></td></tr>\n";
	print "<tr><td colspan=\"4\" style=\"vertical-align:top;\"><p><b>Sjak:</b></p>\n";
	print "<textarea class=\"textAreaSager autosize kontrolskema_font sjak\" name=\"sjak\" rows=\"1\" cols=\"10\" title=\"$sjaktitle\" style=\"height:16px;width:100%;\" >".htmlspecialchars($sjak)."</textarea></td>\n";//onfocus=\"var val=this.value; this.value=''; this.value= val;\"
	print "<td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Konduktør:</b></p><p>$hvem</p></td>\n";
	print "<td style=\"height:0px;padding:0px;margin:0px;border:none;\"><input type=\"hidden\" class=\"sjakid\" name=\"sjakid\" value=\"\"></td></tr>\n";
	if ($opg_beskrivelse) print "<tr><td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Opgave beskrivelse:</b></p></td><td colspan=\"4\"><p><i><b>$opg_navn:</b> $opg_beskrivelse&nbsp;</i></p></td></tr>\n";
	print "</tbody>\n";
	#print "</table>\n";
	#print "<table border=\"0\" cellspacing=\"0\" class=\"kontrolskema\">\n";
	print "<tbody>\n";
	// Array til status select-box i kontrolskema
	//$value = array(0,1,2,3);
	//$color = array("white","green","yellow","red");
	//$option_name = array("&nbsp;","OK","Fejl","Kritisk");
	
	for ($x=1;$x<=count($id);$x++) {
		
		if (!$gruppe_id[$x] && !$punkt_id[$x]) {
			
			print "<tr style=\"display:none;\"><td colspan=\"6\"><input type=\"hidden\" name=\"tjekantal\" value='".count($id)."'><input type=\"hidden\" name=\"id[$x]\" value='$id[$x]'></td></tr>\n";
			$l_id=$id[$x];
		}
		if ($gruppe_id[$x] && !$punkt_id[$x]) { 
		
			print "<tr><td colspan=\"2\" title=\"$assign_id[$x]==$l_id\" style=\"vertical-align:top;\"><input type=\"hidden\" name=\"tjekgruppe[$x]\" value='$id[$x]'><p><b>".$tjekpunkt[$x].":</b></p></td><td colspan=\"4\" class=\"printtxt\">\n"; #<td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"aktiv[$x]\"></td></tr>\n";
		}
		
		// Kontrolskema vises hvis der er id
		if ($punkt_id[$x] && $tjekskema_id) { 
		
			$r=db_fetch_array(db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id' and tjekliste_id = '$id[$x]'",__FILE__ . " linje " . __LINE__)); 
			$tjekpunkter_id=$r['id'];
			$status=$r['status'];
			//$status_tekst=$r['status_tekst'];
		//echo "id: $status";
			/*
			$x=0;
			$q=db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id' and tjekliste_id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				//$tjekpunkter_id[$x]=$r['id'];
				$x++;	
			}*/
			/*
			if (!isset($kontrolpunkt[$x])) $kontrolpunkt[$x]=NULL;
			(in_array($id[$x],$kontrolpunkt))?$tmp="checked='checked'":$tmp=NULL;
			*/
			/*
			$statcolor = NULL;
				if ($status<=0) $statcolor = "background-color:white;";
				if ($status==1) $statcolor = "background-color:green;";
				//if ($status==2) $statcolor = "background-color:yellow;";
				//if ($status==3) $statcolor = "background-color:red;";
			*/
			($status == 1)?$status="checked='checked'":$status=NULL;
			
			print "<span style=\"float:left;min-width:91px;font-size: 12px;line-height: 18px;margin:0 6px 0 0;#background-color:lightblue;\" class=\"selectTextColor\">\n";
			print "<input style=\"float:left;margin-right:4px;\" class=\"inputbox\" type=\"checkbox\" name=\"kontrolpunkt[$x]\" id=\"id$x\" value=\"1\" $status>\n";
			#print "<p style=\"float:left;margin-left:4px;\">".$tjekpunkt[$x]."</p>\n";
			print "<label for=\"id$x\">".$tjekpunkt[$x]."</label>\n";
			print "<input type=\"hidden\" name=\"tjekliste_id[$x]\" value='$id[$x]'><input type=\"hidden\" name=\"tjekskema_id\" value='$tjekskema_id'><input type=\"hidden\" name=\"tjekpunkter_id[$x]\" value='$tjekpunkter_id'>\n";
			/*
			print "<select name=\"kontrolpunkt[$x]\" class=\"kontrol_status\" >\n";
			for($y=0;$y<count($value);$y++) {
				if ($status==$value[$y]) print "<option value=\"$value[$y]\" style=\"background-color:$color[$y];\">$option_name[$y]</option>\n";
			}
			for($y=0;$y<count($value);$y++) {
				if ($status!=$value[$y]) print "<option value=\"$value[$y]\" style=\"background-color:$color[$y];\">$option_name[$y]</option>\n";
			}
			print "</select>\n";
			*/
			//print "<input type=\"hidden\" name=\"pre_kontrolpunkt[$x]\" value='$tmp'>\n";
			
			/*
			print "<select name=\"kontrolpunkt[$x]\" class=\"kontrol_status\" >
				<option value=\"0\" style=\"background-color:white;\">&nbsp;</option>
				<option value=\"1\" style=\"background-color:green;\">OK</option>
				<option value=\"2\" style=\"background-color:yellow;\">Fejl</option>
				<option value=\"3\" style=\"background-color:red;\">Kritisk</option>
			</select>\n";
			*/
			print "</span>\n";
			
			//print "<td colspan=\"3\"><textarea class=\"textAreaSager autosize kontrolskemaText\" name=\"status_tekst[$x]\" rows=\"1\" cols=\"37\">".htmlspecialchars($status_tekst)."</textarea></td></tr>\n";
		}	
		// Nyt kontrolskema vises her
		if ($punkt_id[$x] && !$tjekskema_id) { 
			/*
			if (!isset($kontrolpunkt[$x])) $kontrolpunkt[$x]=NULL;
			(in_array($id[$x],$kontrolpunkt))?$tmp="checked='checked'":$tmp=NULL;
			*/
			#print "<td colspan=\"5\" title=\"$assign_id[$x]==$l_id\">\n";
			
			
			print "<span style=\"float:left;min-width:91px;font-size: 12px;line-height: 18px;margin:0 6px 0 0;#background-color:lightblue;\" class=\"selectTextColor\">\n";
			print "<input style=\"float:left;margin-right:4px;\" class=\"inputbox\" type=\"checkbox\" name=\"kontrolpunkt[$x]\" id=\"id$x\" value=\"1\">\n";
			#print "<p style=\"float:left;margin-left:4px;\">".$tjekpunkt[$x]."</p>\n";
			print "<label for=\"id$x\">".$tjekpunkt[$x]."</label>\n";
			print "<input type=\"hidden\" name=\"tjekliste_id[$x]\" value='$id[$x]'><input type=\"hidden\" name=\"tjekskema_id\" value='$tjekskema_id'>\n";
			/*
			print "<select name=\"kontrolpunkt[$x]\" class=\"kontrol_status\" >\n";
			for($y=0;$y<count($value);$y++) {
				if ($status==$value[$y]) print "<option value=\"$value[$y]\" style=\"background-color:$color[$y];\">$option_name[$y]</option>\n";
			}
			for($y=0;$y<count($value);$y++) {
				if ($status!=$value[$y]) print "<option value=\"$value[$y]\" style=\"background-color:$color[$y];\">$option_name[$y]</option>\n";
			}
			print "</select>\n";
			*/
			//print "<input type=\"hidden\" name=\"pre_kontrolpunkt[$x]\" value='$tmp'>\n";
			
			/*
			print "<select name=\"kontrolpunkt[$x]\" class=\"kontrol_status\" >
				<option value=\"0\" style=\"background-color:white;\">&nbsp;</option>
				<option value=\"1\" style=\"background-color:green;\">OK</option>
				<option value=\"2\" style=\"background-color:yellow;\">Fejl</option>
				<option value=\"3\" style=\"background-color:red;\">Kritisk</option>
			</select>\n";
			*/
			print "</span>\n";
			
			
			#print "</td></tr>\n";
			
			//print "<td colspan=\"3\"><textarea class=\"textAreaSager autosize kontrolskemaText\" name=\"status_tekst[$x]\" rows=\"1\" cols=\"37\">".htmlspecialchars($status_tekst)."</textarea></td></tr>\n";
		}
	}	
	print "</td></tr>\n";
	
	if ($db == 'stillads_18') {
		$apvPss = "APV / PSS er tilgængeligt i Saldi, firmaet og på mobilen.<br>\n";
		$apvPss.= "Alle nødvendige vejledninger er tilgængelige i Saldi, også via mobilen.<br>\n";
		$apvPss.= "Døre og adgangsveje holdes fri, med mindre andet er aftalt.\n";
	} else $apvPss = NULL; 
	print "<tr><td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Manuel Transport:</b></p></td><td colspan=\"4\">
		<input class=\"textXSmall printBorderNone\" type=\"text\" name=\"man_trans\" 
		value=\"$man_trans\" style=\"float:left;margin-right:4px;text-align:right;\"/>
		<p> Gange (hvis mere end forventet skal der ringes til ansvarlig konduktør)</p></td></tr>\n";
	print "<tr><td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Stillads til:<br>(Evt. Tegning)</b></p></td><td colspan=\"4\">
		<textarea class=\"textAreaSager autosize kontrolskema_tegning\" name=\"stillads_til\" rows=\"1\" cols=\"37\">$stillads_til</textarea>
		</td></tr>\n";
	print "<tr><td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Generelt for sagen:</b></p></td>
		<td colspan=\"4\" style=\"#color:#cd3300 !important;\"><p><i><b>$sag_omfang</b></i></p></td></tr>\n";
	if ($apvPss) {
		print "<tr><td colspan=\"2\" style=\"vertical-align:top;\"><p><b>APV / PSS:</b></p></td>
			<td colspan=\"4\" style=\"#color:#cd3300 !important;\"><p>$apvPss</p></td></tr>\n";
	}
	print "<tr><td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Husk hver dag at:</b></p></td>
		<td colspan=\"4\" style=\"text-align:center;color:#cd3300 !important;\"><p><b>Kontrollere bilen for fejl/mangler.</b></p>
		<p><b>Kontrollere eget udstyr og værktøj.</b></p></td></tr>\n";
	print "</tbody>\n";
	print "</table>\n";
	#echo "regnskab: $regnskab";
	print "<div id=\"printableFooter\">\n";
	// Footer skal laves unik til de enkelte stilladsvirksomheder!!!
	if(file_exists("../includes/footer_$regnskab.php")){
		include("../includes/footer_$regnskab.php");
	}
	print "</div><!-- end of printableFooter -->\n";
	print "</div><!-- end of printableArea -->\n";
	
	print "<table border=\"0\" cellspacing=\"0\">\n";
	print "<tbody>\n";
	
	print "<tr><td align=\"center\"><input type=\"submit\" class=\"button gray small\" accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"kontrolskema\">\n";
	if ($tjekskema_id) {
		print "<input class=\"button gray small\" type=\"submit\" name=\"kopi_kontrolskema\" style=\"margin-left:10px;\" value=\"Kopiere kontrolskema\">\n";
		print "<input class=\"button rosy small\" type=\"submit\" name=\"slet_kontrolskema\" style=\"margin-left:10px;\" value=\"Slet kontrolskema\" onclick=\"return confirm('Vil du slette arbejdsseddel?');\">\n";
	}
	print "</td></tr>\n";
	print "</tbody></table>\n";
	
	if ($bilag_id) { #20170303
		print "<br>";
		print "<h3>Bilag:</h3>\n";
		print "<table border=\"0\" cellspacing=\"0\" class=\"tableBilag\">\n";
		print "<tbody class=\"tableBilagZebra tableBilagBorderTop tableBilagBorderBottom\">\n";
		for ($y=0;$y<count($bilag_id);$y++) {
			print "<tr><td><p>$bilag_beskrivelse[$y]</p></td><td align=\"right\"><p><a href=\"../bilag/$db/$sag_id/$bilag_id[$y].$bilag_filtype[$y]\" target=\"blank\" class=\"button blue small\">Vis</a></p></td></tr>\n";
		}
		print "</tbody>\n";
		print "</table>\n";
	}
	
	print "</td></tr>\n";
	print "</tbody></table>\n";
	print "</form>\n";
} # endfunc arbejdsseddel

function emailKontrolskema() {

	global $db;

	$sag_id=if_isset($_GET['sag_id']);
	$sag_fase=if_isset($_GET['sag_fase']);
	$tjekpunkt_id=if_isset($_GET['tjek_id']);
	$tjekskema_id=if_isset($_GET['tjekskema_id']);
	$check=if_isset($_GET['check']);
	$check=if_isset($_POST['check']);
	
	// Visning af tjekskema, hvis tjekskema_id er sat
	if ($tjekskema_id) {
	$r=db_fetch_array(db_select("select * from tjekskema where sag_id='$sag_id' and tjekliste_id='$tjekpunkt_id' and id='$tjekskema_id'",__FILE__ . " linje " . __LINE__));
	$tjekskema_id=$r['id']*1;
	$tjekskema_tjekliste_id=$r['tjekliste_id'];
	$datotid=$r['datotid'];
	$opg_art=htmlspecialchars($r['opg_art']);
	$opg_navn=htmlspecialchars($r['opg_navn']);
	$opg_beskrivelse=htmlspecialchars($r['opg_beskrivelse']);
	$sjak=htmlspecialchars($r['sjak']);
	$hvem=htmlspecialchars($r['hvem']);
	}
	
	// Visning af tjekliste
	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' and fase = '$sag_fase'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$tjekpunkt[$x]=$r['tjekpunkt']; 
		$fase[$x]=$r['fase']*1;
		$assign_id[$x]=$r['assign_id']*1;
		$punkt_id[$x]=0;
		$gruppe_id[$x]=0;
		$liste_id[$x]=$id[$x];
		$q2 = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '$id[$x]' order by id",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$max_gruppe=$x;
			$id[$x]=$r2['id'];
			$tjekpunkt[$x]=$r2['tjekpunkt']; 
			$assign_id[$x]=$r2['assign_id']*1;
			$fase[$x]=$fase[$x-1];
			$punkt_id[$x]=0;
			$gruppe_id[$x]=$id[$x];
			$liste_id[$x]=$liste_id[$x-1];
			$q3 = db_select("select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by id",__FILE__ . " linje " . __LINE__);
			while ($r3 = db_fetch_array($q3)) {
				$x++;
				$id[$x]=$r3['id'];
				$tjekpunkt[$x]=$r3['tjekpunkt']; 
				$assign_id[$x]=$r3['assign_id']*1;
				$fase[$x]=$fase[$x-1];
				$punkt_id[$x]=$id[$x];
				$gruppe_id[$x]=$gruppe_id[$x-1];
				$liste_id[$x]=$liste_id[$x-1];
			}
		}
	}
	
	// Visning af sagsnr og beskrivelse i breadcrumb og liste
	$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__)); 
	$sagsnr=$r['sagsnr'];
	$sag_beskrivelse=htmlspecialchars($r['beskrivelse']);
	$sag_firmanavn=htmlspecialchars($r['firmanavn']);
	$sag_kontakt=htmlspecialchars($r['kontakt']);
	$udf_addr1=htmlspecialchars($r['udf_addr1']);
	$udf_postnr=$r['udf_postnr'];
	$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
	$sag_omfang=htmlspecialchars($r['omfang']);
	
	// Visning af tjeklistenavn i breadcrumb og overskrift på liste
	$r=db_fetch_array(db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' and id='$tjekpunkt_id'",__FILE__ . " linje " . __LINE__)); 
	$tjekpunktnavn=htmlspecialchars($r['tjekpunkt']);
	
	// Hvis der er en opgavebeskrivelse sættes den i en variable
	($opg_beskrivelse)?$opgavebeskrivelse="<tr><td colspan=\"2\" style=\"vertical-align:top;border: 1px solid black;padding: 5px 7px;\"><span><b>Opgave beskrivelse:</b></span></td><td colspan=\"4\" style=\"border: 1px solid black;padding: 5px 7px;\"><span><i><b>$opg_navn:</b> $opg_beskrivelse&nbsp;</i></span></td></tr>":$opgavebeskrivelse=NULL;
	
	// Body-tekst til mail
	$mailtext = '
	<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>Kontrolskema</title>
		<style>
		*
		{
				margin:0px;
				padding:0px;
		}
		body
		{
				font: normal 12px Arial, Helvetica, sans-serif;
				color: #444;
				#height: 100%;
				#margin:20px;
		}
		span
		{
				font-size: 12px;
				line-height: 18px;   
		}
		/*
		.kontrolskema
		{
			width:595px;
			border-collapse:collapse;
			text-align:left;
		}
		.kontrolskema td
		{
			border:1px solid black;
		}
		.kontrolskema tbody tr td
		{
			padding: 5px 7px;
    }
    */
		</style>
	</head>
	<body style="font: normal 12px Arial, Helvetica, sans-serif;color: #444;">
	
	<table border="0" cellspacing="0" style="width:595px;margin-left:20px;">
	<tr>
		<td align="center"><h3 style="font: bold 16px/30px Arial, Helvetica, sans-serif;margin:0px;">'.$tjekpunktnavn.'</h3></td>
	</tr>
	</table>
	
	<table border="0" cellspacing="0" style="width:595px;border-collapse:collapse;text-align:left;margin:0 20px 20px 20px;">
	<colgroup>
    <col width="100">
    <col width="125">
    <col width="70">
		<col width="80">
		<col width="115">
    <col width="105">
  </colgroup>
  <tbody >
		<tr>
			<td style="height:0px;padding:0px;margin:0px;border:none;"></td>
			<td style="height:0px;padding:0px;margin:0px;border:none;"></td>
			<td style="height:0px;padding:0px;margin:0px;border:none;"></td>
			<td style="height:0px;padding:0px;margin:0px;border:none;"></td>
			<td style="height:0px;padding:0px;margin:0px;border:none;"></td>
			<td style="height:0px;padding:0px;margin:0px;border:none;"></td>
		</tr>
  </tbody>
  <tbody>
		<tr>
			<td colspan="2" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Opstillingsadresse:</b></span><br><span>'.$udf_addr1.', '.$udf_postnr.' '.$udf_bynavn.'</span></td>
			<td rowspan="2" align="center" valign="top" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Status:</b></span></td>
			<td rowspan="2" align="center" valign="top" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Dato:</b></span><br><span>'.date("d-m-Y",$datotid).'</span></td>
			<td rowspan="2" align="center" valign="top" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Opgavens art:</b></span><br><span>'.htmlspecialchars($opg_art).'</span></td>
			<td rowspan="2" align="center" valign="top" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Sjak:</b></span><br><span>'.htmlspecialchars($sjak).'</span></td>
		</tr>
		<tr>
			<td colspan="2" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Kontroleret af:</b></span><br><span>'.htmlspecialchars($hvem).'</span></td>
		</tr>'.$opgavebeskrivelse.'
	';
	
	$mailTableBottom = '
	</tbody>
	</table>
	</body>
	</html>
	';
	
	if (isset($_POST['mail'])) {
		$besked_til=NULL;
		$mailvalg=$_POST['mailvalg'];
		$e_mail=$_POST['e_mail'];
		$ny_mail=if_isset($_POST['ny_mail']);
		($ny_mail)?$besked_til=$ny_mail:$besked_til=NULL;
		$nymessage=db_escape_string(if_isset($_POST['nymessage']));
		for($x=0;$x<count($e_mail);$x++) {
			if ($mailvalg[$x]=='on') {
				($besked_til)?$besked_til.=";".$e_mail[$x]:$besked_til=$e_mail[$x];
			}
		}
		
		// Validering af email
		$mail_fejl = "0";
		$email_list=preg_split('[,|;]',$besked_til);
		foreach ($email_list as $mail) {
			if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
				$mail_fejl = "1";
				$error_message = $mail."\\n\\nEr ikke en gyldig email adresse";
				print "<BODY onLoad=\"javascript:alert('$error_message')\">";
			}
		}
		
		$emails=array();
		$besked_til=str_replace(",",";",$besked_til);
		if (strpos($besked_til,";")) {
			$emails=explode(";",$besked_til);
		} else $emails[0]=$besked_til;
		
		// Henter firma adresse og email
		$row = db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
		$afsendermail=$row['email'];
		$afsendernavn=$row['firmanavn'];
		
		$smtp = 'localhost';
		$from = $afsendernavn.'<mailer.'.$db.'@saldi.dk>';
		$replyto = $afsendernavn.'<'.$afsendermail.'>';
		$beskrivelse= $tjekpunktnavn.' Vedr.: '.$udf_addr1.', '.$udf_postnr.' '.$udf_bynavn;
		/*
		if ($mail_fejl == "0") {
			$headers='From: ' . $from . "\r\n";
			$headers.='Reply-To: ' . $replyto . "\r\n";
			$headers.='MIME-Version: 1.0\r\n';
			$headers.='Content-type: text/html; charset=iso-8859-1' . "\r\n";
			$headers.='X-Mailer: PHP/' . phpversion(). "\r\n";
			//$headers .= 'Cc: '.$besked_til."\r\n";
			$to = "$besked_til";
			$subject = "$beskrivelse";
			$message="Der er en besked til dig fra $afsendernavn";
			$message.= ".<br>$nymessage\r\n";
			//if ($sag_id) $message.=", vedrørende $sag_tekst\r\n";
			$message.="<br>$mailtext\r\n";
			$message.="<br>\r\nVenlig hilsen $afsendernavn.\r\n";
			$subject=utf8_decode($subject);
			$message=utf8_decode($message);
			if (mail ($to, $subject, $message, $headers)) {
				print "<BODY onLoad=\"javascript:alert('Besked sendt')\">";
			}
		} 
		*/
		if ($mail_fejl == "0") {
			ini_set("include_path", ".:../phpmailer");
			require("class.phpmailer.php");
			
			$beskrivelse=utf8_decode($beskrivelse);
			$mailtext=utf8_decode($mailtext);
			
			
			$mail = new PHPMailer();

			$mail->IsSMTP();                                      // set mailer to use SMTP
			$mail->Host = $smtp;  // specify main and backup server
			$mail->SMTPAuth = false;     // turn on SMTP authentication
			//$mail->Username = "";  // SMTP username
			//$mail->Password = ""; // SMTP password

			$mail->From = 'mailer.'.$db.'@saldi.dk';
			$mail->FromName = $afsendernavn;
			$mail->AddAddress($emails[0]);
			for ($i=1;$i<count($emails);$i++) $mail->AddCC($emails[$i]);
			//$mail->AddAddress("ellen@example.com");                  // name is optional
			$mail->AddReplyTo($afsendermail,$afsendernavn);

			$mail->WordWrap = 50;                                 // set word wrap to 50 characters
			//$mail->AddAttachment("/var/tmp/file.tar.gz");         // add attachments
			//$mail->AddAttachment("/tmp/image.jpg", "new.jpg");    // optional name
			$mail->IsHTML(true);                                  // set email format to HTML

			$mail->Subject = "$beskrivelse";
			if (!$nymessage) {
				$mail->Body = "<br>";
				$mail->Body .= "$mailtext";
			} else {
				//$mail->Body = '<span style="font-size: 12px;line-height: 18px;">'.$nymessage.'</span>';
				$mail->Body = '<table border="0" cellspacing="0" style="width:595px;margin:20px;">';
				$mail->Body .= '<tr>';
				$mail->Body .= '<td><span style="font: normal 12px Arial, Helvetica, sans-serif;line-height: 18px;color: #444;">'.utf8_decode($nymessage).'</span></td>';
				$mail->Body .= '</tr>';
				$mail->Body .= '</table>';
				$mail->Body .= "$mailtext";
			}
			
			for ($x=1;$x<=count($id);$x++) {
				if ($gruppe_id[$x] && !$punkt_id[$x]) { 
					$mail->Body .= '<tr><td colspan="6" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>'.utf8_decode($tjekpunkt[$x]).'</b></span></td></tr>'; 
				}
				// Kontrolskema vises hvis der er id
				if ($punkt_id[$x] && $tjekskema_id) { 
				
					$r=db_fetch_array(db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id' and tjekliste_id = '$id[$x]'",__FILE__ . " linje " . __LINE__)); 
					$tjekpunkter_id=$r['id'];
					$status=$r['status'];
					$status_tekst=htmlspecialchars($r['status_tekst']);
					
					$statcolor = NULL;
					$option_name = NULL;
						if ($status<=0) {$statcolor = "background-color:white;"; $option_name = "&nbsp;";}
						if ($status==1) {$statcolor = "background-color:green;"; $option_name = "OK";}
						if ($status==2) {$statcolor = "background-color:yellow;"; $option_name = "Fejl";}
						if ($status==3) {$statcolor = "background-color:red;"; $option_name = "Kritisk";}
						if ($status==4) {$statcolor = "background-color:white;"; $option_name = "N/A";}
					
					$mail->Body .= '<tr>';
					$mail->Body .= '<td colspan="2" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span>'.utf8_decode($tjekpunkt[$x]).'</span></td>';
					$mail->Body .= '<td style="'.$statcolor.'border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span>'.utf8_decode($option_name).'</span></td>';
					$mail->Body .= '<td colspan="3" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span>'.utf8_decode($status_tekst).'&nbsp;</span></td>';
					$mail->Body .= '</tr>';
				}	
			}
			
			$mail->Body .= "$mailTableBottom";
			$mail->AltBody = "This is the body in plain text for non-HTML mail clients";

			if(!$mail->Send())
			{
				echo "Message could not be sent. <p>";
				echo "Mailer Error: " . $mail->ErrorInfo;
				exit;
			} else {
				for ($i=0;$i<count($emails);$i++) {
					$beskedSendtTil.=$emails[$i].'\\n';
				}
				print "<BODY onLoad=\"javascript:alert('Besked sendt til:\\n$beskedSendtTil')\">";
			}
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/kontrol_sager.php?sag_id=$sag_id&amp;funktion=kontrolskema&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\">";
		}
	}
	
	
	
		$r=db_fetch_array(db_select("select konto_id,kontakt from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$konto_id=$r['konto_id']*1;
		$s_kontakt=$r['kontakt']; # finder ud af om der er valgt en kontakt til sagen....
		
	// Visning af firmanavn og email
		$r=db_fetch_array(db_select("select firmanavn,email from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$f_navn[0]=$r['firmanavn'];
		$f_email[0]=$r['email'];
		
	// Hvis der ingen kontakt er til sagen, sættes 'checked' i checkbox ved kunde (kun hvis kunde har email) 
	// Der sættes også en variable 'check', så den første if/else kun kører første gang
		if(!$s_kontakt && !$mailvalg[0] && !$check) { 
			$check="1";
			if($f_email[0]) {
				$checked='checked="checked"';
				//$check="1";
				
			} else {
				$checked=NULL;
				//$check="1";
			}
			
		} elseif ($mailvalg[0]) {
			$checked='checked="checked"';
		} else {
			$checked=NULL;
		}
		#print_r ($mailvalg); echo "s_kontakt: $s_kontakt"; echo "check: $check";
	// Finder kundes navn og email (da $x skal starte efter firmanavn findes $x ved at counte antal firmanavn)
		$x=count($f_navn);
		$q=db_select("select navn,email from ansatte where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$a_navn[$x]=$r['navn'];
			$a_email[$x]=$r['email'];
			$x++;
		}
		
	 // Kontaktpersoner til sagen
		$x=count($f_navn)+count($a_navn);
		$q=db_select("select navn,email from ansatte where sag_id = '$sag_id' order by posnr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$k_navn[$x]=$r['navn'];
			$k_email[$x]=$r['email'];
			$x++;
		}
		
	 // Finder konto_id fra egen konto
		$r=db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__));
		$konto_id=$r['id']*1;
		// Finder egne ansatte 
		$x=count($f_navn)+count($a_navn)+count($k_navn);
		$q=db_select("select navn,email from ansatte where konto_id = '$konto_id' and email > '' and lukket < '0'",__FILE__ . " linje " . __LINE__); #20160107
		while ($r = db_fetch_array($q)) {
			$s_navn[$x]=$r['navn'];
			$s_email[$x]=$r['email'];
			$x++;
		}
	
	// Visning af opgavenavn i breadcrumb-title
	($opg_navn)?$opg_navn='('.$opg_navn.')':$opg_navn=NULL;
	
	print "<div id=\"breadcrumbbar\">
			<ul id=\"breadcrumb\">
				<li><a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a></li>
				<!--<li><a href=\"#\" title=\"Sample page 1\">Sample page 1</a></li>-->
				<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id\" title=\"Sag: $sagsnr, $sag_beskrivelse, $udf_addr1, $udf_postnr $udf_bynavn\">Tilbage til sag $sagsnr</a></li>
				<li><a href=\"kontrol_sager.php?funktion=kontrolliste&amp;sag_id=$sag_id\" title=\"Tilbage til kontrolskema-liste\">Kontrolskema</a></li>
				<li><a href=\"kontrol_sager.php?funktion=kontrolskema&amp;sag_id=$sag_id&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\" title=\"Tilbage til $tjekpunktnavn $opg_navn\">$tjekpunktnavn</a></li>\n";
				print "<li>Mail kontrolskema</li>\n";
				print "
			</ul>
			
		</div><!-- end of breadcrumbbar -->\n";
	
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
	print "<tbody>\n";
	print "<tr><td align=\"center\">\n";
	print "<form action=\"$_SERVER[PHP_SELF]?funktion=emailKontrolskema&amp;sag_id=$sag_id&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id&amp;check=$check\" method=\"post\">\n";
	/*echo "sag_id: $sag_id<br> 
				sag_fase: $sag_fase<br>
				tjek_id: $tjekpunkt_id<br>
				tjekskema_id: $tjekskema_id<br>
				konto_id: $konto_id<br>
				kontakt: $s_kontakt\n";*/
	
	for ($x=0;$x<count($f_navn);$x++) {
		print "<input type=\"hidden\" name=\"e_mail[$x]\" value=\"$f_email[$x]\">\n";
	}
	print "<input type=\"hidden\" name=\"space\" value=\"\">\n";
	for ($x=count($f_navn);$x<count($f_navn)+count($a_navn);$x++) { 
		print "<input type=\"hidden\" name=\"e_mail[$x]\" value=\"$a_email[$x]\">\n";
	}
	print "<input type=\"hidden\" name=\"space\" value=\"\">\n";
	for ($x=count($f_navn)+count($a_navn);$x<count($f_navn)+count($a_navn)+count($k_navn);$x++) { 
		print "<input type=\"hidden\" name=\"e_mail[$x]\" value=\"$k_email[$x]\">\n";
	}
	print "<input type=\"hidden\" name=\"space\" value=\"\">\n";
	for ($x=count($f_navn)+count($a_navn)+count($k_navn);$x<count($f_navn)+count($a_navn)+count($k_navn)+count($s_navn);$x++) { 
		print "<input type=\"hidden\" name=\"e_mail[$x]\" value=\"$s_email[$x]\">\n";
	}
	//print "<input type=\"hidden\" name=\"space\" value=\"\">\n";
	//$x=count($f_navn)+count($a_navn);
	//print "<input type=\"hidden\" name=\"e_mail[$x]\" value=\"$n_email[$x]\">\n";
	#echo "skema: $skema";
	
	print "<table border=\"0\" cellspacing=\"0\" width=\"595\" class=\"tableMail\">\n";
	print "<tbody>
					<tr>
						<td><p><b>Kunde:</b></p></td>
						<td colspan=\"2\">&nbsp;</td>
					</tr>
					<tr class=\"tableMailHead\">
						<td><p><b>Navn</b></p></td>
						<td><p><b>e-mail</b></p></td>
						<td>&nbsp;</td>
					</tr>
				</tbody>\n";
	
	print "<tbody>\n";
				for ($x=0;$x<count($f_navn);$x++) {
					print "<tr>
						<td><p>$f_navn[$x]</p></td>\n";
						if (!$f_email[$x]) {
						print "<td colspan=\"2\"><p><i>Der er ingen e-mail adresse</i></p></td>\n";
						} else {
						print "<td><p>$f_email[$x]</p></td>
						<td><p><input type=\"checkbox\" name=\"mailvalg[$x]\" $checked></p></td>\n";
						}
					print "</tr>\n";
				}
	print "</tbody>\n";
	print "<tbody><tr><td class=\"tableMailBorder\" colspan=\"3\">&nbsp;</td></tr></tbody>\n";
	
	print "<tbody>
					<tr>
						<td><p><b>Kundekontakter:</b></p></td>
						<td colspan=\"2\">&nbsp;</td>
					</tr>
					<tr class=\"tableMailHead\">
						<td><p><b>Navn</b></p></td>
						<td><p><b>e-mail</b></p></td>
						<td>&nbsp;</td>
					</tr>
				</tbody>\n";
				
	print "<tbody class=\"tableMailZebra\">\n";
				if (!$a_navn) {
					print "<tr><td colspan=\"3\"align=\"center\"><p><i>Der er ingen kontakter tilknyttet kunde</i></p></td></tr>\n";
				} else {
					for ($x=count($f_navn);$x<count($f_navn)+count($a_navn);$x++) {
						if (!$mailvalg[$x] && !$check && ($a_navn[$x] == $s_kontakt)) {
							$check="1"; // Her sætter vi variablen 'check', så if/else kun kører en gang
							if ($a_navn[$x] == $s_kontakt) {
								$checked="checked='checked'";
							} else {
								$checked=NULL;
							}
						} elseif ($mailvalg[$x]) {
							$checked="checked='checked'";
						} else {
							$checked=NULL;
						}
						
						print "<tr>
							<td><p>$a_navn[$x]</p></td>\n";
							if (!$a_email[$x]) {
							print "<td colspan=\"2\"><p><i>Der er ingen e-mail adresse</i></p></td>\n";
							} else {
							print "<td><p>$a_email[$x]&nbsp;</p></td>
							<td><p><input type=\"checkbox\" name=\"mailvalg[$x]\" $checked><input type=\"hidden\" name=\"check\" value=\"$check\"></p></td>\n";
							}
						print "</tr>";
					}
					#print_r ($mailvalg)."<br>"; echo "s_kontakt: $s_kontakt<br>"; print_r($a_navn)."<br>"; echo "check: $check";
				}
	print "</tbody>\n";
	print "<tbody><tr><td class=\"tableMailBorder\" colspan=\"3\">&nbsp;</td></tr></tbody>\n";
	
	print "<tbody>
					<tr>
						<td><p><b>Sagskontakter:</b></p></td>
						<td colspan=\"2\">&nbsp;</td>
					</tr>
					<tr class=\"tableMailHead\">
						<td><p><b>Navn</b></p></td>
						<td><p><b>e-mail</b></p></td>
						<td>&nbsp;</td>
					</tr>
				</tbody>\n";
	
	print "<tbody class=\"tableMailZebra\">\n";
				if (!$k_navn) {
					print "<tr><td colspan=\"3\"align=\"center\"><p><i>Der er ingen kontakter tilknyttet sagen</i></p></td></tr>\n";
				} else {
					for ($x=count($f_navn)+count($a_navn);$x<count($f_navn)+count($a_navn)+count($k_navn);$x++) { 
						print "<tr>
							<td><p>$k_navn[$x]</p></td>\n";
							if (!$k_email[$x]) {
							print "<td colspan=\"2\"><p><i>Der er ingen e-mail adresse</i></p></td>\n";
							} else {
							print "<td><p>$k_email[$x]</p></td>
							<td><p><input type=\"checkbox\" name=\"mailvalg[$x]\" ></p></td>\n";
							}
						print "</tr>\n";
					}
				}
	print "</tbody>\n";
	print "<tbody><tr><td class=\"tableMailBorder\" colspan=\"3\">&nbsp;</td></tr></tbody>\n";
	
	print "<tbody>
					<tr>
						<td><p><b>Kolleger:</b></p></td>
						<td colspan=\"2\">&nbsp;</td>
					</tr>
					<tr class=\"tableMailHead\">
						<td><p><b>Navn</b></p></td>
						<td><p><b>e-mail</b></p></td>
						<td>&nbsp;</td>
					</tr>
				</tbody>\n";
	
	print "<tbody class=\"tableMailZebra\">\n";
				for ($x=count($f_navn)+count($a_navn)+count($k_navn);$x<count($f_navn)+count($a_navn)+count($k_navn)+count($s_navn);$x++) { 
					print "<tr>
						<td><p>$s_navn[$x]</p></td>\n";
						if (!$s_email[$x]) {
						print "<td colspan=\"2\"><p><i>&nbsp;</i></p></td>\n";
						} else {
						print "<td><p>$s_email[$x]</p></td>
						<td><p><input type=\"checkbox\" name=\"mailvalg[$x]\" ></p></td>\n";
						}
					print "</tr>\n";
				}
	print "</tbody>\n";
	print "<tbody><tr><td class=\"tableMailBorder\" colspan=\"3\">&nbsp;</td></tr></tbody>\n";
	
	print "<tbody>
					<tr>
						<td><p><b>Indtast evt. email:</b></p></td>
						<td colspan=\"2\">&nbsp;</td>
					</tr>
					<tr>
						<td colspan=\"3\"><p><input type=\"text\" class=\"text\" name=\"ny_mail\" value=\"$ny_mail\"></p></td>
					</tr>
					<tr>
						<td colspan=\"3\">&nbsp;</td>
					</tr>
					<tr>
						<td><p><b>Indtast evt. tekst:</b></p></td>
						<td colspan=\"2\">&nbsp;</td>
					</tr>
					<tr>
						<td colspan=\"3\"><p><textarea name=\"nymessage\" rows=\"3\" cols=\"76\" style=\"min-width:679px;max-width:679px;\">$nymessage</textarea></p></td>
					</tr>
					<tr>
						<td colspan=\"3\">&nbsp;</td>
					</tr>
				</tbody>\n";
	print "<tbody><tr><td class=\"tableMailBorder\" colspan=\"3\">&nbsp;</td></tr></tbody>\n";
	
	print "<tbody>
					<tr>
						<td colspan=\"3\"><input type=\"submit\" class=\"button blue medium\" name=\"mail\" value=\"Send mail\"></td>
					</tr>
				</tbody>
				</table>\n";
	
	print "</form>\n";
	print "</td></tr>\n";
	print "</tbody></table>\n";
} # endfunc emailKontrolskema

function emailArbejdsseddel() {


global $db;

	$sag_id=if_isset($_GET['sag_id']);
	$sag_fase=if_isset($_GET['sag_fase']);
	$tjekpunkt_id=if_isset($_GET['tjek_id']);
	$tjekskema_id=if_isset($_GET['tjekskema_id']);
	$check=if_isset($_GET['check']);
	$check=if_isset($_POST['check']);
	
	// Visning af tjekskema, hvis tjekskema_id er sat
	if ($tjekskema_id) {
	$r=db_fetch_array(db_select("select * from tjekskema where sag_id='$sag_id' and tjekliste_id='$tjekpunkt_id' and id='$tjekskema_id'",__FILE__ . " linje " . __LINE__));
	$tjekskema_id=$r['id']*1;
	$tjekskema_tjekliste_id=$r['tjekliste_id'];
	$udf_dato=date("d-m-Y",$r['datotid']);
	$opg_art=htmlspecialchars($r['opg_art']);
	$opg_navn=htmlspecialchars($r['opg_navn']);
	$opg_beskrivelse=htmlspecialchars($r['opg_beskrivelse']);
	$sjak=htmlspecialchars($r['sjak']);
	$hvem=htmlspecialchars($r['hvem']);
	$stillads_til=htmlspecialchars($r['stillads_til']);
	$man_trans=htmlspecialchars($r['man_trans']);
	if (!$man_trans) $man_trans="0";
	}
	
	// Visning af tjekliste
	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' and fase = '$sag_fase'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$tjekpunkt[$x]=htmlspecialchars($r['tjekpunkt']); 
		$fase[$x]=$r['fase']*1;
		$assign_id[$x]=$r['assign_id']*1;
		$punkt_id[$x]=0;
		$gruppe_id[$x]=0;
		$liste_id[$x]=$id[$x];
		$q2 = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '$id[$x]' order by id",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$max_gruppe=$x;
			$id[$x]=$r2['id'];
			$tjekpunkt[$x]=htmlspecialchars($r2['tjekpunkt']); 
			$assign_id[$x]=$r2['assign_id']*1;
			$fase[$x]=$fase[$x-1];
			$punkt_id[$x]=0;
			$gruppe_id[$x]=$id[$x];
			$liste_id[$x]=$liste_id[$x-1];
			$q3 = db_select("select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by id",__FILE__ . " linje " . __LINE__);
			while ($r3 = db_fetch_array($q3)) {
				$x++;
				$id[$x]=$r3['id'];
				$tjekpunkt[$x]=htmlspecialchars($r3['tjekpunkt']); 
				$assign_id[$x]=$r3['assign_id']*1;
				$fase[$x]=$fase[$x-1];
				$punkt_id[$x]=$id[$x];
				$gruppe_id[$x]=$gruppe_id[$x-1];
				$liste_id[$x]=$liste_id[$x-1];
			}
		}
	}
	
	// Visning af sagsnr og beskrivelse i breadcrumb og liste
	$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__)); 
	$sagsnr=$r['sagsnr'];
	$sag_beskrivelse=htmlspecialchars($r['beskrivelse']);
	$sag_firmanavn=htmlspecialchars($r['firmanavn']);
	$sag_kontakt=htmlspecialchars($r['kontakt']);
	$udf_addr1=htmlspecialchars($r['udf_addr1']);
	$udf_postnr=$r['udf_postnr'];
	$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
	$sag_omfang=htmlspecialchars($r['omfang']);
	
	// Visning af tjeklistenavn i breadcrumb og overskrift på liste
	$r=db_fetch_array(db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' and id='$tjekpunkt_id'",__FILE__ . " linje " . __LINE__)); 
	$tjekpunktnavn=$r['tjekpunkt'];
	
	// Hvis der er en opgavebeskrivelse sættes den i en variable
	($opg_beskrivelse)?$opgavebeskrivelse="<tr><td colspan=\"2\" style=\"vertical-align:top;border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;\"><span><b>Opgave beskrivelse:</b></span></td><td colspan=\"4\" style=\"border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;\"><span><i><b>$opg_navn:</b> $opg_beskrivelse&nbsp;</i></span></td></tr>":$opgavebeskrivelse=NULL;
	
	// Body-tekst til mail
	$mailtext = '
	<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
	<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>Kontrolskema</title>
		<style type="text/css">
		*
		{
				margin:0px;
				padding:0px;
		}
		
		body
		{
				font: normal 12px Arial, Helvetica, sans-serif;
				color: #444;
				#height: 100%;
				#margin:20px;
		}
		
		span
		{
				font-size: 12px;
				line-height: 18px; 
				display:inline-block;
		}
		/*
		.kontrolskema
		{
			width:595px;
			border-collapse:collapse;
			text-align:left;
		}
		.kontrolskema td
		{
			border:1px solid black;
		}
		.kontrolskema tbody tr td
		{
			padding: 5px 7px;
    }
    */
    img {
			width: 10px !important; height: 10px !important; display: inline !important;
    }
    
		</style>
	</head>
	<body style="font: normal 12px Arial, Helvetica, sans-serif;color: #444;">
		
	<table border="0" cellspacing="0" cellpadding="0" style="width:595px;margin-left:20px;">
	<tr>
		<td align="center"><h3 style="font: bold 16px/30px Arial, Helvetica, sans-serif;margin:0px;">'.$tjekpunktnavn.'</h3></td>
	</tr>
	</table>
	
	<table border="0" cellspacing="0" cellpadding="0" style="width:595px;border-collapse:collapse;text-align:left;margin:0 20px 20px 20px;">
	<colgroup>
    <col width="75">
    <col width="75">
		<col width="150">
		<col width="100">
    <col width="100">
		<col width="95">
  </colgroup>
  <tbody>
		<tr>
			<td style="height:0px;padding:0px;margin:0px;border:none;"></td>
			<td style="height:0px;padding:0px;margin:0px;border:none;"></td>
			<td style="height:0px;padding:0px;margin:0px;border:none;"></td>
			<td style="height:0px;padding:0px;margin:0px;border:none;"></td>
			<td style="height:0px;padding:0px;margin:0px;border:none;"></td>
			<td style="height:0px;padding:0px;margin:0px;border:none;"></td>
		</tr>
  </tbody>
  <tbody>
		<tr>
			<td colspan="4" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Udføres dato:&nbsp;</b>'.$udf_dato.'</span></td>
			<td colspan="2" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Sagsnr:&nbsp;</b>'.$sagsnr.'</span></td>
		</tr>
		<tr>
			<td colspan="2" style="vertical-align:top;border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Opstillingsadresse:</b></span><br><span>'.$udf_addr1.', '.$udf_postnr.' '.$udf_bynavn.'</span></td>
			<td colspan="2" style="vertical-align:top;border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Kunde:</b></span><br><span>'.$sag_firmanavn.'</span></td>
			<td colspan="2" style="vertical-align:top;border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>kontakt:</b></span><br><span>'.$sag_kontakt.'&nbsp;</span></td>
		</tr>
		<tr>
			<td colspan="4" style="vertical-align:top;border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Sjak:</b></span><br><span>'.$sjak.'</span></td>
			<td colspan="2" style="vertical-align:top;border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Konduktør:&nbsp;</b></span><br><span>'.$hvem.'</span></td>
		</tr>
		'.$opgavebeskrivelse.'
	';
	
	$mailTableBottom = '
		<tr>
			<td colspan="2" style="vertical-align:top;border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Manuel Transport:</b></span></td>
			<td colspan="4" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span>'.$man_trans.'</span><span>&nbsp;Gange (hvis mere end forventet skal der ringes til ansvarlig kondukt&oslash;r)</span></td>
		</tr>
		<tr>
			<td colspan="2" style="vertical-align:top;border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Stillads til:<br>(Evt. Tegning)</b></span></td>
			<td colspan="4" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span>'.utf8_decode($stillads_til).'</span></td>
		</tr>
		<tr>
			<td colspan="2" style="vertical-align:top;border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Generalt for sagen:</b></span></td>
			<td colspan="4" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><i><b>'.utf8_decode($sag_omfang).'</b></i></span></td>
		</tr>
		<tr>
			<td colspan="2" style="vertical-align:top;border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Husk hver dag at:</b></span></td>
			<td colspan="4" style="text-align:center;border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>Kontrollere bilen for fejl/mangler.</b></span><br><span><b>Kontrollere eget udstyr og v&aelig;rkt&oslash;j.</b></span></td>
		</tr>
	</tbody>
	</table>
	</body>
	</html>
	';
	
	if (isset($_POST['mail'])) {
		$besked_til=NULL;
		$mailvalg=$_POST['mailvalg'];
		$e_mail=$_POST['e_mail'];
		$ny_mail=if_isset($_POST['ny_mail']);
		($ny_mail)?$besked_til=$ny_mail:$besked_til=NULL;
		$nymessage=db_escape_string(if_isset($_POST['nymessage']));
		for($x=0;$x<count($e_mail);$x++) {
			if ($mailvalg[$x]=='on') {
				($besked_til)?$besked_til.=";".$e_mail[$x]:$besked_til=$e_mail[$x];
			}
		}
		
		// Validering af email
		$mail_fejl = "0";
		$email_list=preg_split('[,|;]',$besked_til);
		foreach ($email_list as $mail) {
			if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
				$mail_fejl = "1";
				$error_message = $mail."\\n\\nEr ikke en gyldig email adresse";
				print "<BODY onLoad=\"javascript:alert('$error_message')\">";
			}
		}
		
		$emails=array();
		$besked_til=str_replace(",",";",$besked_til);
		if (strpos($besked_til,";")) {
			$emails=explode(";",$besked_til);
		} else $emails[0]=$besked_til;
		
		// Henter firma adresse og email
		$row = db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
		$afsendermail=$row['email'];
		$afsendernavn=$row['firmanavn'];
		
		$smtp = 'localhost';
		$from = $afsendernavn.'<mailer.'.$db.'@saldi.dk>';
		$replyto = $afsendernavn.'<'.$afsendermail.'>';
		$beskrivelse = $tjekpunktnavn.' Vedr.: '.$udf_addr1.', '.$udf_postnr.' '.$udf_bynavn;
		/*
		if ($mail_fejl == "0") {
			$headers='From: ' . $from . "\r\n";
			$headers.='Reply-To: ' . $replyto . "\r\n";
			$headers.='MIME-Version: 1.0\r\n';
			$headers.='Content-type: text/html; charset=iso-8859-1' . "\r\n";
			$headers.='X-Mailer: PHP/' . phpversion(). "\r\n";
			//$headers .= 'Cc: '.$besked_til."\r\n";
			$to = "$besked_til";
			$subject = "$beskrivelse";
			$message="Der er en besked til dig fra $afsendernavn";
			$message.= ".<br>$nymessage\r\n";
			//if ($sag_id) $message.=", vedrørende $sag_tekst\r\n";
			$message.="<br>$mailtext\r\n";
			$message.="<br>\r\nVenlig hilsen $afsendernavn.\r\n";
			$subject=utf8_decode($subject);
			$message=utf8_decode($message);
			if (mail ($to, $subject, $message, $headers)) {
				print "<BODY onLoad=\"javascript:alert('Besked sendt')\">";
			}
		} 
		*/
		if ($mail_fejl == "0") {
			ini_set("include_path", ".:../phpmailer");
			require("class.phpmailer.php");
			
			$beskrivelse=utf8_decode($beskrivelse);
			$mailtext=utf8_decode($mailtext);
			
			
			$mail = new PHPMailer();

			$mail->IsSMTP();                                      // set mailer to use SMTP
			$mail->Host = $smtp;  // specify main and backup server
			$mail->SMTPAuth = false;     // turn on SMTP authentication
			//$mail->Username = "";  // SMTP username
			//$mail->Password = ""; // SMTP password

			$mail->From = 'mailer.'.$db.'@saldi.dk';
			$mail->FromName = $afsendernavn;
			$mail->AddAddress($emails[0]);
			for ($i=1;$i<count($emails);$i++) $mail->AddCC($emails[$i]);
			//$mail->AddAddress("ellen@example.com");                  // name is optional
			$mail->AddReplyTo($afsendermail,$afsendernavn);

			$mail->WordWrap = 50;                                 // set word wrap to 50 characters
			//$mail->AddAttachment("/var/tmp/file.tar.gz");         // add attachments
			//$mail->AddAttachment("/tmp/image.jpg", "new.jpg");    // optional name
			$mail->IsHTML(true);                                  // set email format to HTML

			$mail->Subject = "$beskrivelse";
			if (!$nymessage) {
				$mail->Body = "<br>";
				$mail->Body = "$mailtext";
			} else {
				//$mail->Body = '<span style="font-size: 12px;line-height: 18px;">'.$nymessage.'</span>';
				$mail->Body = '<table border="0" cellspacing="0" style="width:595px;margin:20px;">';
				$mail->Body .= '<tr>';
				$mail->Body .= '<td><span style="font: normal 12px Arial, Helvetica, sans-serif;line-height:18px;color:#444;">'.utf8_decode($nymessage).'</span></td>';
				$mail->Body .= '</tr>';
				$mail->Body .= '</table>';
				$mail->Body .= "$mailtext";
			}
			
			for ($x=1;$x<=count($id);$x++) {
				if ($gruppe_id[$x] && !$punkt_id[$x]) { 
					$mail->Body .= '<tr><td colspan="2" style="vertical-align:top;border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;"><span><b>'.utf8_decode($tjekpunkt[$x]).':</b></span></td><td colspan="4" style="border-width:1px;border-style:solid;border-color:black;padding-top:5px;padding-bottom:5px;padding-right:7px;padding-left:7px;">';
				}
				// Kontrolskema vises hvis der er id
				if ($punkt_id[$x] && $tjekskema_id) { 
				
					$r=db_fetch_array(db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id' and tjekliste_id = '$id[$x]'",__FILE__ . " linje " . __LINE__)); 
					$tjekpunkter_id=$r['id'];
					$status=$r['status'];
					
					if ($status == 1) { // html code for checkbox: checked = &#9745; unchecked = &#9744;
						$status="&#10004;";
						$statuscolor="color:#000 !important;";
					} else {
						$status="&#9744;"; 
						$statuscolor=NULL;
					}
					
					$mail->Body .= '<span style="float:left;min-width:91px;margin-top:0px;margin-bottom:0px;margin-right:6px;margin-left:0px;'.$statuscolor.'">'.$status.'&nbsp;'.utf8_decode($tjekpunkt[$x]).'&nbsp;&nbsp;</span>';
					//$mail->Body .= '<table border="0" cellspacing="0" style="width:91px;float:left;">';
					//$mail->Body .= '<tr><td>'.$status.'&nbsp;'.utf8_decode($tjekpunkt[$x]).'</td></tr>';
					//$mail->Body .= '</table>';
				}	
				
			}
			$mail->Body .= '</td></tr>';
			$mail->Body .= "$mailTableBottom";
			$mail->AltBody = "This is the body in plain text for non-HTML mail clients";

			if(!$mail->Send())
			{
				echo "Message could not be sent. <p>";
				echo "Mailer Error: " . $mail->ErrorInfo;
				exit;
			} else {
				for ($i=0;$i<count($emails);$i++) {
					$beskedSendtTil.=$emails[$i].'\\n';
				}
				print "<BODY onLoad=\"javascript:alert('Besked sendt til:\\n$beskedSendtTil')\">";
			}
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/kontrol_sager.php?sag_id=$sag_id&amp;funktion=arbejdsseddel&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\">";
		}
	}
	
	
	
		$r=db_fetch_array(db_select("select konto_id,kontakt from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$konto_id=$r['konto_id']*1;
		$s_kontakt=$r['kontakt']; # finder ud af om der er valgt en kontakt til sagen....
		
	// Visning af firmanavn og email
		$r=db_fetch_array(db_select("select firmanavn,email from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$f_navn[0]=$r['firmanavn'];
		$f_email[0]=$r['email'];
		
	// Hvis der ingen kontakt er til sagen, sættes 'checked' i checkbox ved kunde (kun hvis kunde har email) 
	// Der sættes også en variable 'check', så den første if/else kun kører første gang
		if(!$s_kontakt && !$mailvalg[0] && !$check) { 
			
			if($f_email[0]) {
				$checked='checked="checked"';
				$check="1";
				
			} else {
				$checked=NULL;
				$check="1";
			}
			
		} elseif ($mailvalg[0]) {
			$checked='checked="checked"';
		} else {
			$checked=NULL;
		}
		
	// Finder kundes navn og email (da $x skal starte efter firmanavn findes $x ved at counte antal firmanavn)
		$x=count($f_navn);
		$q=db_select("select navn,email from ansatte where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$a_navn[$x]=$r['navn'];
			$a_email[$x]=$r['email'];
			$x++;
		}
		
	 // Kontaktpersoner til sagen
		$x=count($f_navn)+count($a_navn);
		$q=db_select("select navn,email from ansatte where sag_id = '$sag_id' order by posnr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$k_navn[$x]=$r['navn'];
			$k_email[$x]=$r['email'];
			$x++;
		}
		
	 // Finder konto_id fra egen konto
		$r=db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__));
		$konto_id=$r['id']*1;
		// Finder egne ansatte 
		$x=count($f_navn)+count($a_navn)+count($k_navn);
		$q=db_select("select navn,email from ansatte where konto_id = '$konto_id' and email > '' and lukket < '0'",__FILE__ . " linje " . __LINE__); #20160107
		while ($r = db_fetch_array($q)) {
			$s_navn[$x]=$r['navn'];
			$s_email[$x]=$r['email'];
			$x++;
		}
	
	// Visning af opgavenavn i breadcrumb-title
	($opg_navn)?$opg_navn='('.$opg_navn.')':$opg_navn=NULL;
	
	print "<div id=\"breadcrumbbar\">
			<ul id=\"breadcrumb\">
				<li><a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a></li>
				<!--<li><a href=\"#\" title=\"Sample page 1\">Sample page 1</a></li>-->
				<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id\" title=\"Sag: $sagsnr, $sag_beskrivelse, $udf_addr1, $udf_postnr $udf_bynavn\">Tilbage til sag $sagsnr</a></li>
				<li><a href=\"kontrol_sager.php?funktion=kontrolliste&amp;sag_id=$sag_id\" title=\"Tilbage til kontrolskema-liste\">Kontrolskema</a></li>
				<li><a href=\"kontrol_sager.php?funktion=arbejdsseddel&amp;sag_id=$sag_id&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\" title=\"Tilbage til $tjekpunktnavn $opg_navn\">$tjekpunktnavn</a></li>\n";
				print "<li>Mail arbejdsseddel</li>\n";
				print "
			</ul>
			
		</div><!-- end of breadcrumbbar -->\n";
	
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
	print "<tbody>\n";
	print "<tr><td align=\"center\">\n";
	print "<form action=\"$_SERVER[PHP_SELF]?funktion=emailArbejdsseddel&amp;sag_id=$sag_id&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id&amp;check=$check\" method=\"post\">\n";
	/*echo "sag_id: $sag_id<br> 
				sag_fase: $sag_fase<br>
				tjek_id: $tjekpunkt_id<br>
				tjekskema_id: $tjekskema_id<br>
				konto_id: $konto_id<br>
				kontakt: $s_kontakt\n";*/
	
	for ($x=0;$x<count($f_navn);$x++) {
		print "<input type=\"hidden\" name=\"e_mail[$x]\" value=\"$f_email[$x]\">\n";
	}
	print "<input type=\"hidden\" name=\"space\" value=\"\">\n";
	for ($x=count($f_navn);$x<count($f_navn)+count($a_navn);$x++) { 
		print "<input type=\"hidden\" name=\"e_mail[$x]\" value=\"$a_email[$x]\">\n";
	}
	print "<input type=\"hidden\" name=\"space\" value=\"\">\n";
	for ($x=count($f_navn)+count($a_navn);$x<count($f_navn)+count($a_navn)+count($k_navn);$x++) { 
		print "<input type=\"hidden\" name=\"e_mail[$x]\" value=\"$k_email[$x]\">\n";
	}
	print "<input type=\"hidden\" name=\"space\" value=\"\">\n";
	for ($x=count($f_navn)+count($a_navn)+count($k_navn);$x<count($f_navn)+count($a_navn)+count($k_navn)+count($s_navn);$x++) { 
		print "<input type=\"hidden\" name=\"e_mail[$x]\" value=\"$s_email[$x]\">\n";
	}
	//print "<input type=\"hidden\" name=\"space\" value=\"\">\n";
	//$x=count($f_navn)+count($a_navn);
	//print "<input type=\"hidden\" name=\"e_mail[$x]\" value=\"$n_email[$x]\">\n";
	#echo "skema: $skema";
	
	print "<table border=\"0\" cellspacing=\"0\" width=\"595\" class=\"tableMail\">\n";
	print "<tbody>
					<tr>
						<td><p><b>Kunde:</b></p></td>
						<td colspan=\"2\">&nbsp;</td>
					</tr>
					<tr class=\"tableMailHead\">
						<td><p><b>Navn</b></p></td>
						<td><p><b>e-mail</b></p></td>
						<td>&nbsp;</td>
					</tr>
				</tbody>\n";
	
	print "<tbody>\n";
				for ($x=0;$x<count($f_navn);$x++) {
					print "<tr>
						<td><p>$f_navn[$x]</p></td>\n";
						if (!$f_email[$x]) {
						print "<td colspan=\"2\"><p><i>Der er ingen e-mail adresse</i></p></td>\n";
						} else {
						print "<td><p>$f_email[$x]</p></td>
						<td><p><input type=\"checkbox\" name=\"mailvalg[$x]\" $checked></p></td>\n";
						}
					print "</tr>\n";
				}
	print "</tbody>\n";
	print "<tbody><tr><td class=\"tableMailBorder\" colspan=\"3\">&nbsp;</td></tr></tbody>\n";
	
	print "<tbody>
					<tr>
						<td><p><b>Kundekontakter:</b></p></td>
						<td colspan=\"2\">&nbsp;</td>
					</tr>
					<tr class=\"tableMailHead\">
						<td><p><b>Navn</b></p></td>
						<td><p><b>e-mail</b></p></td>
						<td>&nbsp;</td>
					</tr>
				</tbody>\n";
				
	print "<tbody class=\"tableMailZebra\">\n";
				if (!$a_navn) {
					print "<tr><td colspan=\"3\"align=\"center\"><p><i>Der er ingen kontakter tilknyttet kunde</i></p></td></tr>\n";
				} else {
					for ($x=count($f_navn);$x<count($f_navn)+count($a_navn);$x++) {
						if (!$mailvalg[$x] && !$check && ($a_navn[$x] == $s_kontakt)) {
							$check="1"; // Her sætter vi variablen 'check', så if/else kun kører en gang
							if ($a_navn[$x] == $s_kontakt) {
								$checked="checked='checked'";
							} else {
								$checked=NULL;
							}
						} elseif ($mailvalg[$x]) {
							$checked="checked='checked'";
						} else {
							$checked=NULL;
						}
						print "<tr>
							<td><p>$a_navn[$x]</p></td>\n";
							if (!$a_email[$x]) {
							print "<td colspan=\"2\"><p><i>Der er ingen e-mail adresse</i></p></td>\n";
							} else {
							print "<td><p>$a_email[$x]&nbsp;</p></td>
							<td><p><input type=\"checkbox\" name=\"mailvalg[$x]\" $checked><input type=\"hidden\" name=\"check\" value=\"$check\"></p></td>\n";
							}
						print "</tr>";
					}
				}
	print "</tbody>\n";
	print "<tbody><tr><td class=\"tableMailBorder\" colspan=\"3\">&nbsp;</td></tr></tbody>\n";
	
	print "<tbody>
					<tr>
						<td><p><b>Sagskontakter:</b></p></td>
						<td colspan=\"2\">&nbsp;</td>
					</tr>
					<tr class=\"tableMailHead\">
						<td><p><b>Navn</b></p></td>
						<td><p><b>e-mail</b></p></td>
						<td>&nbsp;</td>
					</tr>
				</tbody>\n";
	
	print "<tbody class=\"tableMailZebra\">\n";
				if (!$k_navn) {
					print "<tr><td colspan=\"3\"align=\"center\"><p><i>Der er ingen kontakter tilknyttet sagen</i></p></td></tr>\n";
				} else {
					for ($x=count($f_navn)+count($a_navn);$x<count($f_navn)+count($a_navn)+count($k_navn);$x++) { 
						print "<tr>
							<td><p>$k_navn[$x]</p></td>\n";
							if (!$k_email[$x]) {
							print "<td colspan=\"2\"><p><i>Der er ingen e-mail adresse</i></p></td>\n";
							} else {
							print "<td><p>$k_email[$x]</p></td>
							<td><p><input type=\"checkbox\" name=\"mailvalg[$x]\" ></p></td>\n";
							}
						print "</tr>\n";
					}
				}
	print "</tbody>\n";
	print "<tbody><tr><td class=\"tableMailBorder\" colspan=\"3\">&nbsp;</td></tr></tbody>\n";
	
	print "<tbody>
					<tr>
						<td><p><b>Kolleger:</b></p></td>
						<td colspan=\"2\">&nbsp;</td>
					</tr>
					<tr class=\"tableMailHead\">
						<td><p><b>Navn</b></p></td>
						<td><p><b>e-mail</b></p></td>
						<td>&nbsp;</td>
					</tr>
				</tbody>\n";
	
	print "<tbody class=\"tableMailZebra\">\n";
				for ($x=count($f_navn)+count($a_navn)+count($k_navn);$x<count($f_navn)+count($a_navn)+count($k_navn)+count($s_navn);$x++) { 
					print "<tr>
						<td><p>$s_navn[$x]</p></td>\n";
						if (!$s_email[$x]) {
						print "<td colspan=\"2\"><p><i>&nbsp;</i></p></td>\n";
						} else {
						print "<td><p>$s_email[$x]</p></td>
						<td><p><input type=\"checkbox\" name=\"mailvalg[$x]\" ></p></td>\n";
						}
					print "</tr>\n";
				}
	print "</tbody>\n";
	print "<tbody><tr><td class=\"tableMailBorder\" colspan=\"3\">&nbsp;</td></tr></tbody>\n";
	
	print "<tbody>
					<tr>
						<td><p><b>Indtast evt. email:</b></p></td>
						<td colspan=\"2\">&nbsp;</td>
					</tr>
					<tr>
						<td colspan=\"3\"><p><input type=\"text\" class=\"text\" name=\"ny_mail\" value=\"$ny_mail\"></p></td>
					</tr>
					<tr>
						<td colspan=\"3\">&nbsp;</td>
					</tr>
					<tr>
						<td><p><b>Indtast evt. tekst:</b></p></td>
						<td colspan=\"2\">&nbsp;</td>
					</tr>
					<tr>
						<td colspan=\"3\"><p><textarea name=\"nymessage\" rows=\"3\" cols=\"76\" style=\"min-width:679px;max-width:679px;\">$nymessage</textarea></p></td>
					</tr>
					<tr>
						<td colspan=\"3\">&nbsp;</td>
					</tr>
				</tbody>\n";
	print "<tbody><tr><td class=\"tableMailBorder\" colspan=\"3\">&nbsp;</td></tr></tbody>\n";
	
	print "<tbody>
					<tr>
						<td colspan=\"3\"><input type=\"submit\" class=\"button blue medium\" name=\"mail\" value=\"Send mail\"></td>
					</tr>
				</tbody>
				</table>\n";
	
	print "</form>\n";
	print "</td></tr>\n";
	print "</tbody></table>\n";




} #endfunc emailArbejdsseddel
?>
