<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- sager/kunder.php --- lap 4.0.8 --- 2023-09-27---
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
// Copyright (c) 2012-2023 saldi.dk aps
// ----------------------------------------------------------------------
// Har rettet KONTOANSVARLIG i 'depkort_2_stillads.php'
// Diverse html rettelser i 'debkort_1_stillads.php' + 'debkort_2_stillads.php' + 'debkort_4_stillads.php'
// html rettelser i 'kunder.php'
// Ny fil 'debkort_load_stillads.php' oprettet i debitormappe
// 20160205 PK - Har ændret på kundeansvarlig, s	å det kun er admin og konduktøre der vises. Initialer er skiftet ud med navn. Søg #20160205 i 'debkort_2_stillads.php'
// 20170119 PK - Tilføjet query der henter 'antal' af alle sager fra kunde i function ret_kunde, da limet ellers kun viser 100. Søg #20170119
// 20170119 PK - Har indsat 'Ansvarlig' i listen med sager fra kunde.
// 20230927 PHR Page title now set in online.php


	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();

	$bg="nix";
	$header='nix';

	$konto_id = $kontoansvarlig_id = array();
#	$kunder_sag_limit = $kunder_sager_ansvarlig = $kunder_sager_oprettet_af = $kunder_sager_tidspkt = array();
#	$kunder_sager_sort = $kunder_sager_sagsnr = $kunder_sager_status = $kunder_sager_udf_addr1 = array();
	$bank_konto = $bank_reg = $betalingsdage = $betalingsbet = NULL;
	$cvrnr = NULL;
	$fax = NULL;
	$kontotype = $kreditmax = NULL;
	$menu_sager = $menu_planlaeg = $menu_dagbog = $menu_loen = $menu_ansatte = NULL;
	$menu_certificering = $menu_medarbejdermappe = NULL;
	$menu_kunder ='id="menuActive"';
	$pbs = NULL;
	$tlf = NULL;
	$modulnr=0;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");

	if (isset($_GET['sag_id']))      $sagid = (int)$_GET['sag_id']*1;
	elseif (isset($_POST['sag_id'])) $sagid = (int)$_POST['sag_id'];
	
	if (isset($_GET['konto_id']))      $konto_id = (int)$_GET['konto_id'];
	elseif (isset($_POST['konto_id'])) $konto_id = (int)$_POST['konto_id'];
	else $konto_id=$_POST['id']*1;
	$funktion=if_isset($_GET['funktion']);
	if (!$funktion)$funktion="kundeliste";
	
	print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
	<html>
	<head>
		<meta http-equiv=\"X-UA-Compatible\" content=\"IE=10\">
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
		<meta name=\"viewport\" content=\"width=1024\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/main.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/search.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/form.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/pajinate.css\">
		<script type=\"text/javascript\" src=\"../javascript/jquery-1.8.0.min.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.autocomplete.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.pajinate.js\"></script>
		
		<!--[if lt IE 9]>
		<script src=\"http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js\"></script>
		<![endif]-->
		<title>".$pageTitle."</title>
	</head>
	<body>
		<div id=\"wrapper\">";
			include ("../includes/sagsmenu.php");
			print "<div id=\"breadcrumbbar\">

				<ul id=\"breadcrumb\">
					<li>";
					if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					else print "<a href=\"\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					print "</li>
					<!--<li><a href=\"#\" title=\"Sample page 1\">Sample page 1</a></li>
					<li><a href=\"#\" title=\"Sample page 2\">Sample page 2</a></li>
					<li><a href=\"#\" title=\"Sample page 3\">Sample page 3</a></li>
					<li>Current page</li>-->
					<li>Kunder</li>
				</ul>

			</div><!-- end of breadcrumbbar -->

			<div id=\"leftmenuholder\">";
				include ("leftmenu.php");
			print "</div><!-- end of leftmenuholder -->
			<div class=\"maincontent\">";
			$funktion($konto_id);
			print "</div><!-- end of maincontent -->
			</div><!-- end of wrapper -->  
		<!-- <div id=\"footer\"><p>Pluder | Pluder</p></div> -->
		<script type=\"text/javascript\" src=\"../javascript/jquery.kunder.js\"></script>";
		print "</body></html>";
		

function kundeliste() {
	
	$sortstyle=array();
	$nysortstyle=if_isset($_GET['nysortstyle']);
	$sortarray=array('kontonr','firmanavn','addr1','postnr','bynavn','kontakt','tlf');
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$unsetsort=if_isset($_GET['unsetsort']);
	
	
	if ($nysort && $nysort==$sort) {
		$sort=$nysort."%20desc";
			foreach ($sortarray as $key => $val){
			($nysortstyle==$sortarray[$key])?$sortstyle[$key]="desc":$sortstyle[$key]="";
		}
	}else{ 
		$sort=$nysort;
			foreach ($sortarray as $key => $val){
			($nysortstyle==$sortarray[$key])?$sortstyle[$key]="asc":$sortstyle[$key]="";
		}
	}
	
	if ($_GET['nysortstyle']) {
		$_SESSION['kunder_kontonr']=$sortstyle[0];
		$_SESSION['kunder_firmanavn']=$sortstyle[1];
		$_SESSION['kunder_addr1']=$sortstyle[2];
		$_SESSION['kunder_postnr']=$sortstyle[3];
		$_SESSION['kunder_bynavn']=$sortstyle[4];
		$_SESSION['kunder_kontakt']=$sortstyle[5];
		$_SESSION['kunder_tlf']=$sortstyle[6];
	} else {
		$sortstyle[0]=$_SESSION['kunder_kontonr'];
		$sortstyle[1]=$_SESSION['kunder_firmanavn'];
		$sortstyle[2]=$_SESSION['kunder_addr1'];
		$sortstyle[3]=$_SESSION['kunder_postnr'];
		$sortstyle[4]=$_SESSION['kunder_bynavn'];
		$sortstyle[5]=$_SESSION['kunder_kontakt'];
		$sortstyle[6]=$_SESSION['kunder_tlf'];
	}
	
	if ($unsetsort) {
		unset($_SESSION['kunder_sort'],
					$_SESSION['kunder_kontonr'],$sortstyle[0],
					$_SESSION['kunder_firmanavn'],$sortstyle[1],
					$_SESSION['kunder_addr1'],$sortstyle[2],
					$_SESSION['kunder_postnr'],$sortstyle[3],
					$_SESSION['kunder_bynavn'],$sortstyle[4],
					$_SESSION['kunder_kontakt'],$sortstyle[5],
					$_SESSION['kunder_tlf'],$sortstyle[6]
				);
	}
		
	//print_r($sortstyle);
	//print_r($sortarray);
	//echo "sort: $sort";
	
	if ($sort) $_SESSION['kunder_sort']=$sort;
	else $sort=$_SESSION['kunder_sort'];
	if (!$sort) $sort="firmanavn";
	
	$sqlsort=urldecode($sort);

	$x=0;
	//$sort=if_isset($_GET['sort']);
	//if (!$sort) $sort='firmanavn';
	$konto_id = array();
	$q=db_select("SELECT * FROM adresser WHERE art='D' ORDER BY $sqlsort",__FILE__ . " linje " . __LINE__); #20140718 Har fjernet " AND lukket != 'on' ", da den ikke viste alle uden 'on'???
	while ($r = db_fetch_array($q)) {
		$x++;
		$konto_id[$x]=$r['id'];
		$kontonr[$x]=$r['kontonr'];
		$firmanavn[$x]=htmlspecialchars($r['firmanavn']);
		$addr1[$x]=htmlspecialchars($r['addr1']);
		$addr2[$x]=htmlspecialchars($r['addr2']);
		$postnr[$x]=$r['postnr'];
		$bynavn[$x]=htmlspecialchars($r['bynavn']);
		$kontakt[$x]=htmlspecialchars($r['kontakt']); //????
		$telefon[$x]=$r['tlf'];
	}
	$antal_adresser=$x;
	
	print "
		<div class=\"contentsoeg\">
		<form name=\"kundesoeg\" action=\"kunder.php?funktion=ret_kunde\" method=\"post\"> 
			<table border=\"0\" cellspacing=\"0\" width=\"828\">
				<thead>
					<tr>
						<th width=\"100\">Kundenr</th>
						<th width=\"225\">Firmanavn</th>
						<th width=\"385\">Adresse</th>
						<th colspan=\"2\">&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						
						<td><input class=\"textinput kontonr\" type=\"text\" value=\"\" id=\"kontonr\" name=\"kontonr\" tabindex=\"1\"/></td>
						<td><input class=\"textinput firmanavn\" type=\"text\" value=\"\" id=\"firmanavn\" name=\"firmanavn\" tabindex=\"2\"/></td>
						<td><input class=\"textinput firmaadresse\" type=\"text\" value=\"\" id=\"adresse\" name=\"adresse\" tabindex=\"3\"/></td>
						<td style=\"padding:0px;\"><input type=\"hidden\" class=\"id\" value=\"\" name=\"id\"></td>
						<td align=\"center\"><input type=\"submit\" value=\"Find kunde\" name=\"findkunde\" class=\"button gray small\" tabindex=\"4\"></td>
						
					</tr>
				</tbody>
			</table>
		</form>
			<div style=\"height:25px;padding:10px 12px 0 12px;#background-color:#f2f2f2;\">
					<span style=\"float:left;\"><a href=\"kunder.php?funktion=kundeliste&amp;unsetsort=unset\" class=\"button gray small\">Slet sortering</a></span>
			</div>
		</div><!-- end of contentsoeg -->\n";
	(count($konto_id)<=50)?$abortlist="abort_small_list":$abortlist=NULL; // tallet sættes til det samme som 'items_per_page' i jquery.kunder.js, under pagination
	print "<div id=\"paging_container\">
		<div class=\"info_text\"></div>
		<div class=\"page_navigation $abortlist\"></div>
	<div class=\"contentkundehead\">
			<ul id=\"sort\">
					<li>
							<a href=\"kunder.php?funktion=kundeliste&amp;nysort=kontonr&amp;sort=$sort&amp;nysortstyle=$sortarray[0]\" class=\"felt01 $sortstyle[0]\" style=\"width:72px\">Kundenr</a>
							<a href=\"kunder.php?funktion=kundeliste&amp;nysort=firmanavn&amp;sort=$sort&amp;nysortstyle=$sortarray[1]\" class=\"felt02 $sortstyle[1]\" style=\"width:175px\">Navn</a>
							<a href=\"kunder.php?funktion=kundeliste&amp;nysort=addr1&amp;sort=$sort&amp;nysortstyle=$sortarray[2]\" class=\"felt03 $sortstyle[2]\" style=\"width:180px\">Addresse</a>
							<a href=\"kunder.php?funktion=kundeliste&amp;nysort=postnr&amp;sort=$sort&amp;nysortstyle=$sortarray[3]\" class=\"felt04 $sortstyle[3]\" style=\"width:60px\">Postnr</a>
							<a href=\"kunder.php?funktion=kundeliste&amp;nysort=bynavn&amp;sort=$sort&amp;nysortstyle=$sortarray[4]\" class=\"felt05 $sortstyle[4]\" style=\"width:105px\">By</a>       
							<a href=\"kunder.php?funktion=kundeliste&amp;nysort=kontakt&amp;sort=$sort&amp;nysortstyle=$sortarray[5]\" class=\"felt06 $sortstyle[5]\" style=\"width:120px\">Kontaktperson</a>
							<a href=\"kunder.php?funktion=kundeliste&amp;nysort=tlf&amp;sort=$sort&amp;nysortstyle=$sortarray[6]\" class=\"felt07 $sortstyle[6]\" style=\"width:85px\">Telefon</a>
					</li>
			</ul>
	</div><!-- end of contentkundehead -->
		<div class=\"contentkunde\"> 
			<ul id=\"things\" class=\"paging_content\">";
				for ($x=1;$x<=$antal_adresser;$x++) {
					print "<li><a href=\"kunder.php?funktion=ret_kunde&amp;konto_id=$konto_id[$x]&amp;sag_id=0\">
						<span class=\"felt01\" style=\"width:72px\">$kontonr[$x]&nbsp;</span>
						<span class=\"felt02\" style=\"width:175px\" title=\"$firmanavn[$x]\">$firmanavn[$x]&nbsp;</span>
						<span class=\"felt03\" style=\"width:180px\" title=\"$addr1[$x]\">$addr1[$x]&nbsp;</span>
						<span class=\"felt04\" style=\"width:60px\">$postnr[$x]&nbsp;</span>
						<span class=\"felt05\" style=\"width:105px\" title=\"$bynavn[$x]\">$bynavn[$x]&nbsp;</span>	           
						<span class=\"felt06\" style=\"width:120px\" title=\"$kontakt[$x]\">$kontakt[$x]&nbsp;</span>
						<span class=\"felt07\" style=\"width:85px\">$telefon[$x]&nbsp;</span>
					</a></li>";
				}
			print "</ul>
		</div><!-- end of contentkunde -->
		<div class=\"page_navigation $abortlist\"></div>
		</div><!-- end of paging_container -->";
}
function ret_kunde($id) {

	global $charset;
	global $sprog_id;
	
	$sagid=if_isset($_GET['sag_id']);
	$id=if_isset($_GET['konto_id']);
	if (isset($_POST['id'])) $id=$_POST['id'];
	//if (!$id) { header("location:kunder.php?funktion=kundeliste"); exit(); }
	
	// Her slettes kunde og ansatte
	if (isset($_POST['slet_kunde']) && $id) {
		$x=0;
		$q=db_select("SELECT * FROM ansatte WHERE konto_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$ansat_id[$x]=$r['id'];
		}
		$antal_ansatte=$x;
		
		if ($ansat_id[$x] && $id) {
		
			for ($x=1;$x<=$antal_ansatte;$x++) {
				db_modify("delete from ansatte where id = '$ansat_id[$x]' and konto_id = '$id'",__FILE__ . " linje " . __LINE__);
			}
			db_modify("delete from adresser where id = '$id'",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/kunder.php\">";
		} else {
			db_modify("delete from adresser where id = '$id'",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/kunder.php\">";
		}
	}
	
	print "<div class=\"content\">\n";
	if (isset($_POST['gem_rettelse']) && $_POST['gem_rettelse']) include "../debitor/debkort_save.php";
	print "<form name=\"ret_kunde\" action=\"kunder.php?funktion=ret_kunde&amp;sag_id=$sagid\" method=\"post\">\n";
	include "../debitor/debkort_load_stillads.php";
	#print "		<table border=\"0\"><tbody><tr><td><table><tbody>";
	include "debkort_1_stillads.php";
	#print "		</tbody></table></td><td><table><tbody>";
	include "debkort_2_stillads.php";
	#print "		</tbody></table></td><td><table><tbody>";
	#include "../debitor/debkort_3.php";
	#print "		</tbody></table></td><tr><tr><td colspan=\"3\"><table><tbody>";
	include "debkort_4_stillads.php";
	print "<div class=\"clear\"></div>\n";
	print "<hr>\n";
	print "<input type=\"hidden\" name=\"konto_id\" value='$id'>\n";
	
	$sortstyle=array();
	$nysortstyle=if_isset($_GET['nysortstyle']);
	$sortarray=array('sagsnr','udf_addr1','tidspkt','oprettet_af','ref','status');
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$unsetsort=if_isset($_GET['unsetsort']);
	$kunder_sag_limit=if_isset($_POST['kunder_sag_limit']);
	
	if ($nysort && $nysort==$sort) {
		$sort=$nysort."%20desc";
			foreach ($sortarray as $key => $val){
			($nysortstyle==$sortarray[$key])?$sortstyle[$key]="desc":$sortstyle[$key]="";
		}
	}else{ 
		$sort=$nysort;
			foreach ($sortarray as $key => $val){
			($nysortstyle==$sortarray[$key])?$sortstyle[$key]="asc":$sortstyle[$key]="";
		}
	}
	
	if ($_GET['nysortstyle']) {
		$_SESSION['kunder_sager_sagsnr']=$sortstyle[0];
		$_SESSION['kunder_sager_udf_addr1']=$sortstyle[1];
		$_SESSION['kunder_sager_tidspkt']=$sortstyle[2];
		$_SESSION['kunder_sager_oprettet_af']=$sortstyle[3];
		$_SESSION['kunder_sager_ansvarlig']=$sortstyle[4];
		$_SESSION['kunder_sager_status']=$sortstyle[5];
	} else {
		$sortstyle[0] = if_isset($_SESSION['kunder_sager_sagsnr'],NULL);
		$sortstyle[1] = if_isset($_SESSION['kunder_sager_udf_addr1'],NULL);
		$sortstyle[2] = if_isset($_SESSION['kunder_sager_tidspkt'],NULL);
		$sortstyle[3] = if_isset($_SESSION['kunder_sager_oprettet_af'],NULL);
		$sortstyle[4] = if_isset($_SESSION['kunder_sager_ansvarlig'],NULL);
		$sortstyle[5] = if_isset($_SESSION['kunder_sager_status'],NULL);
	}
	
	if ($_POST['kunder_sag_limit']) {
		$_SESSION['kunder_sag_limit']=$kunder_sag_limit;
	} else {
		$kunder_sag_limit=if_isset($_SESSION['kunder_sag_limit']);
	}
	
	if ($unsetsort) {
		unset($_SESSION['kunder_sager_sort'],
					$_SESSION['kunder_sager_sagsnr'],$sortstyle[0],
					$_SESSION['kunder_sager_udf_addr1'],$sortstyle[1],
					$_SESSION['kunder_sager_tidspkt'],$sortstyle[2],
					$_SESSION['kunder_sager_oprettet_af'],$sortstyle[3],
					$_SESSION['kunder_sager_ansvarlig'],$sortstyle[4],
					$_SESSION['kunder_sager_status'],$sortstyle[5],
					$_SESSION['kunder_sag_limit'],$kunder_sag_limit
				);
	}
	//print_r($sortstyle);
	//print_r($sortarray);
	//echo "sort: $sort";
	
	if ($sort) $_SESSION['kunder_sager_sort']=$sort;
	else $sort=$_SESSION['kunder_sager_sort'];
	if (!$sort) $sort="tidspkt%20desc";
	
	$sqlsort=urldecode($sort);
	
	$limitarray=array('100','250','500','1000','NULL');
	$limitnavn=array('100','250','500','1000','Alle');
	
	($kunder_sag_limit)?$limit=$kunder_sag_limit:$limit='100';
	
	$x=0;
	$id = (int)$id;
	$qtxt = "SELECT * FROM sager WHERE konto_id='$id' ORDER BY $sqlsort limit $limit";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$sag_id[$x]=$r['id'];
		$sag_nr[$x]=$r['sagsnr']*1;
		$sag_beskrivelse[$x]=htmlspecialchars($r['beskrivelse']);
		$sag_firmanavn[$x]=htmlspecialchars($r['firmanavn']);
		$sag_ansvarlig[$x]=htmlspecialchars($r['ref']);
		$sag_omfang[$x]=htmlspecialchars($r['omfang']);
		$sag_oprettet[$x]=htmlspecialchars($r['ref']);
		$udf_firmanavn[$x]=htmlspecialchars($r['udf_firmanavn']);
		$udf_addr1[$x]=htmlspecialchars($r['udf_addr1']);
		$udf_postnr[$x]=$r['udf_postnr'];
		$udf_bynavn[$x]=htmlspecialchars($r['udf_bynavn']);
		$oprettet_af[$x]=htmlspecialchars($r['oprettet_af']);
		$dato[$x]=date("d-m-Y",$r['tidspkt']);
		$tid[$x]=date("H:i",$r['tidspkt']);
		$sag_status[$x]=$r['status'];
		}
	$antal_sager=$x;
	
	// Her tæller vi alle sager for kunde uden limit #20170119
	$y=0;
	$q=db_select("SELECT id FROM sager WHERE konto_id='$id'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$y++;
		$alleSagerId[$y]=$r['id'];
		}
	$antal_sager_ialt=$y;

	print "<div style=\"float:left; width:828px;\">\n";
	print "<div class=\"contentA\" >\n";
	print "<input type=\"submit\" accesskey=\"g\" value=\"Gem / opdat&eacute;r\" class=\"button gray medium\" name=\"gem_rettelse\" onclick=\"javascript:docChange = false;\">\n";
	if ($id && !$sag_id[$x]) {
		print "<input class=\"button rosy medium\" style=\"float:right\" type=\"submit\" name=\"slet_kunde\" value=\"Slet kunde\" onclick=\"return confirm('Vil du slette denne kunde?');\">
		</div><!-- end of contentA -->";
	} else {
		print "</div><!-- end of contentA -->\n";
	}
	print "</div><!-- end of full container -->\n";
	print "</form>";
	print "</div><!-- end of content -->\n";
	
		
	if ($id && $sag_id[$x]) {
		print "<div class=\"clear\"></div>\n";
		print "<hr>\n";
		print "<div class=\"content\" id=\"sect\">\n";
		print "<h3>liste med sager fra $firmanavn</h3>\n";
		print "<form name=\"sagliste\" action=\"kunder.php?funktion=ret_kunde&amp;konto_id=$id#sect\" method=\"post\">
				<div style=\"height:25px;padding:10px 12px 0 12px;#background-color:#f2f2f2;\">
					<span style=\"float:left;width:260px;\"><a href=\"kunder.php?funktion=ret_kunde&amp;unsetsort=unset&amp;konto_id=$id#sect\" class=\"button gray small\">Slet sortering</a></span>\n";
					($antal_sager_ialt<=100)?$display="display:none;":$display=NULL;
					print "
					<div style=\"float:right;$display\">
						<p style=\"float:left;\">Vælg antal viste linjer:&nbsp;</p>
						<select name=\"kunder_sag_limit\" class=\"selectinputloen\" style=\"width:76px;\" onchange=\"this.form.submit()\">\n";
						
							for ($i=0;$i<count($limitarray);$i++) {
									if ($kunder_sag_limit==$limitarray[$i]) print "<option value=\"$limitarray[$i]\">$limitnavn[$i]</option>\n"; 
								}
								for ($i=0;$i<count($limitarray);$i++) {
									if ($kunder_sag_limit!=$limitarray[$i]) print "<option value=\"$limitarray[$i]\">$limitnavn[$i]</option>\n"; 
								}
								
							print "
						</select>
					</div><!-- end of select -->
				</div>
			</form>\n";
		print "</div><!-- end of content -->\n";
		
		($antal_sager<=50)?$abortlist="abort_small_list":$abortlist=NULL; // tallet sættes til det samme som 'items_per_page' i head, under pagination
		print "<div id=\"paging_container_sager\">
			<div class=\"info_text\"></div>
			<div class=\"page_navigation $abortlist\"></div>";
		print "<div class=\"contentkundehead\">
				<ul id=\"sort\">
						<li>
								<a href=\"kunder.php?funktion=ret_kunde&amp;nysort=sagsnr&amp;sort=$sort&amp;konto_id=$id&amp;nysortstyle=$sortarray[0]#sect\" id=\"felt01\" class=\"felt01 $sortstyle[0]\" style=\"width:65px\">Sagsnr</a>
								<a href=\"kunder.php?funktion=ret_kunde&amp;nysort=udf_addr1&amp;sort=$sort&amp;konto_id=$id&amp;nysortstyle=$sortarray[1]#sect\" class=\"felt02 $sortstyle[1]\" style=\"width:315px\">Opstillings adresse</a>
								<a href=\"kunder.php?funktion=ret_kunde&amp;nysort=tidspkt&amp;sort=$sort&amp;konto_id=$id&amp;nysortstyle=$sortarray[2]#sect\" class=\"felt03 $sortstyle[2]\" style=\"width:130px\">Indtastet</a>
								<a href=\"kunder.php?funktion=ret_kunde&amp;nysort=oprettet_af&amp;sort=$sort&amp;konto_id=$id&amp;nysortstyle=$sortarray[3]#sect\" class=\"felt04 $sortstyle[3]\" style=\"width:100px\">Af</a>
								<a href=\"kunder.php?funktion=ret_kunde&amp;nysort=ref&amp;sort=$sort&amp;konto_id=$id&amp;nysortstyle=$sortarray[4]#sect\" class=\"felt05 $sortstyle[4]\" style=\"width:120px\">Ansvarlig</a>
								<a href=\"kunder.php?funktion=ret_kunde&amp;nysort=status&amp;sort=$sort&amp;konto_id=$id&amp;nysortstyle=$sortarray[5]#sect\" class=\"felt06 $sortstyle[5]\" style=\"width:75px\">Status</a>
								
						</li>
				</ul>
		</div><!-- end of contentkundehead -->
		<div class=\"contentkunde\"> 
			<ul id=\"things\" class=\"paging_content_sager\">";
				for ($x=1;$x<=$antal_sager;$x++) {
				/*$stat = "";
						if ($status[$x]<=1) $stat = "Tilbud";
						if ($status[$x]==2) $stat = "Opstart";
						if ($status[$x]==3) $stat = "Aflevering";
						if ($status[$x]==4) $stat = "Kontrol";
						if ($status[$x]==5) $stat = "Nedtagning";
						if ($status[$x]==6) $stat = "Afsluttet";*/
						/*
						$statcolor = NULL;
						if ($sag_status[$x]=='Opmåling') $statcolor = "color:#006600;";// green
						if ($sag_status[$x]=='Tilbud') $statcolor = "color:#009900;";//
						if ($sag_status[$x]=='Ordre modtaget') $statcolor = "color:#00CC00;";//
						if ($sag_status[$x]=='Montage') $statcolor = "color:#C1BE00;";//
						if ($sag_status[$x]=='Aflevering') $statcolor = "color:#FF9900;";// 
						if ($sag_status[$x]=='Afmeldt') $statcolor = "color:#FF6600;";// 
						if ($sag_status[$x]=='Demontage') $statcolor = "color:#FF3300;";// 
						if ($sag_status[$x]=='Afsluttet') $statcolor = "color:#FF0000;";// red
						*/
						$statcolor = NULL;
						if ($sag_status[$x]=='Tilbud') $statcolor = "color:black;";
						if ($sag_status[$x]=='Ordrebekræftelse') $statcolor = "color:black;";
						if ($sag_status[$x]=='Montage') $statcolor = "color:red;";
						if ($sag_status[$x]=='Godkendt') $statcolor = "color:green;";
						if ($sag_status[$x]=='Afmeldt') $statcolor = "color:#C1BE00;";
						if ($sag_status[$x]=='Afsluttet') $statcolor = "color:black;";
					print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id[$x]\">
						<span class=\"felt01\" style=\"width:65px;\">$sag_nr[$x]&nbsp;</span>
						<span class=\"felt02\" style=\"width:315px;\" title='$udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]'>$udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]&nbsp;</span>
						<span class=\"felt03\" style=\"width:130px;\">$dato[$x] kl. $tid[$x]&nbsp;</span>
						<span class=\"felt04\" style=\"width:100px;\" title='$oprettet_af[$x]'>$oprettet_af[$x]&nbsp;</span>
						<span class=\"felt05\" style=\"width:120px;\" title='$sag_ansvarlig[$x]'>$sag_ansvarlig[$x]&nbsp;</span>
						<span class=\"felt06\" style=\"width:75px;$statcolor\" title='$sag_status[$x]'>$sag_status[$x]&nbsp;</span>";
					print "</a></li>\n";
				}
				//print "<li style=\"display:none;\">&nbsp;</li>";
			print "</ul>
		</div><!-- end of contentkunde -->
		<div class=\"page_navigation $abortlist\"></div>
		</div><!-- end of paging_container_sager -->";
	}
}
function ret_kunde_ansat($konto_id) {

#	$ansat_id=if_isset($_GET['ansat_id'])*1;
	global $charset;
	global $sprog_id;
	
	$sagid=if_isset($_GET['sag_id']);

	if(isset($_POST['brugergruppe']) && isset($_POST['id'])) {	
		$id  = $_POST['id']*1;
		$brugergruppe = $_POST['brugergruppe']*1;
		if ($id && $brugergruppe) {
			db_modify("update ansatte set gruppe = '$brugergruppe' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	}
	if(isset($_POST['brugernavn']) && isset($_POST['id'])) {	
		$id  = $_POST['id']*1;
		$brugernavn = $_POST['brugernavn']*1;
		$kode1=if_isset($_POST['kode1']);
		$kode2=if_isset($_POST['kode2']);
		if ($id && $bruger_id) {
			$ret_kode=1;
			if (!$kode1) {
				$ret_kode=0;
				print "<BODY onLoad=\"javascript:alert('Kodeord skal angives!')\">";
			} elseif ($kode1 == '********') $ret_kode=0;
			elseif ($kode1 != $kode2) {
				$ret_kode=0;
				print "<BODY onLoad=\"javascript:alert('Kodeord ikke ens!')\">";
			}
			if ($ret_kode && $bruger_id) {
				$kode1=md5($kode1);
				db_modify("update brugere set kode = '$kode1' where id = '$bruger_id'",__FILE__ . " linje " . __LINE__);
			}
			if ($brugernavn && $bruger_id) {
				db_modify("update brugere set brugernavn = '$brugernavn' where id = '$bruger_id'",__FILE__ . " linje " . __LINE__);
			}
		}
	}
	$x=0;
	$q=db_select("select * from grupper where art='brgrp' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$gruppe_id[$x]=$r['id'];
		$gruppe_beskrivelse[$x]=$r['beskrivelse'];
		$x++;
	}
	include "../debitor/ansatte_save_stillads.php";
	include "../debitor/ansatte_load.php";
	print "<div class=\"content\">\n";
	print "<form name=\"ansatte\" action=\"kunder.php?funktion=ret_kunde_ansat&amp;konto_id=$konto_id&amp;sag_id=$sagid\" method=\"post\">\n";
	//print "<table border=\"0\"><tbody><tr><td><table><tbody>";
	include "kunder_ansat.php";
	/* Har udkommenteret login til kunde kontakt 20161206
	print "<div style=\"float:left; margin-right:70px; width:379px;\">\n";
	print "<div class=\"contentA\">\n";
	print "<div class=\"row\"><div class=\"left\">brugernavn</div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"brugernavn\" value=\"$brugernavn\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "<div class=\"row\"><div class=\"left\">brugergruppe</div><div class=\"right\"><select style=\"width:194px;\" name=\"brugergruppe\">\n";
	for ($x=0;$x<=count($gruppe_id);$x++) {
		if ($gruppe==$gruppe_id[$x]) print "<option value=\"$gruppe_id[$x]\">$gruppe_beskrivelse[$x]&nbsp;</option>\n"; 
	}
	for ($x=0;$x<=count($gruppe_id);$x++) {
		if ($gruppe!=$gruppe_id[$x]) print "<option value=\"$gruppe_id[$x]\">$gruppe_beskrivelse[$x]&nbsp;</option>\n"; 
	}
	print "</select></div><div class=\"clear\"></div></div><!-- end of row -->\n"; 
	print "<input type=\"hidden\" name=\"konto_id\" value='$konto_id'>\n";
	print "<input type=\"hidden\" name=\"bruger_id\" value='$bruger_id'>\n";
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of left container -->\n";
	print "<div style=\"float:left; width:379px;\">\n";
	print "<div class=\"contentA\">\n";
	print "<div class=\"row\"><div class=\"left\">adgangskode</div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"kode1\" value=\"********\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "<div class=\"row\"><div class=\"left\">adgangskode</div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"kode1\" value=\"********\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of right container -->\n";
	print "<div class=\"clear\"></div>\n";*/
	print "<hr>\n";
	print "<div class=\"contentA\" style=\"text-align:center;\">\n";
	#	print "<input type=submit accesskey=\"g\" value=\"Gem / opdat&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\">";
	print "<input type=\"submit\" class=\"button gray medium\" accesskey=\"g\" value=\"Gem\" name=\"submit\"><input type=\"submit\" class=\"button rosy medium textSpaceLarge\" accesskey=\"s\" value=\"Slet\" onclick=\"return confirm('Er du sikker på du vil slette denne kontakt?');\" name=\"submit\">\n";
	print "</div><!-- end of contentA -->\n";
	print "	</form>\n";
	print "</div><!-- end of content -->\n";
}
function split_navn($firmanavn) {
	$y=0;
	$tmp=array();
	$tmp=explode(" ",$firmanavn);
	$x=count($tmp)-1;
	$efternavn=$tmp[$x];
	while($y<$x-1) {
		$fornavn.=$tmp[$y]." ";
		$y++;
	}
	$fornavn.=$tmp[$y];
	return ($fornavn.",".$efternavn);
}
?>

