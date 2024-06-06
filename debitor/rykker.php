<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------debitor/rykker.php---------lap 4.0.7-------2023-02-28--------
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
// Copyright (c) 2003 - 2023 saldi.dk aps
// ----------------------------------------------------------------------
// 20140628 - Diverse rettelser da rykkergebyr altid vistes i DKK Søg 20140628
// 20140707 - Opdatering virkede kun når der blev ændret valuta 20140707
// 20140903 - Opdatering slettede beskrivelse ved bogført rykker 20140903  
// 20170303	-	Tilføjet inkasso - Søg inkasso
// 20210701 - LOE - Translated some of these texts from Danish to English and Norsk
// 20211110 MSC - Implementing new design
// 20230228 PHR Implementing formular language $formularsprog, $newFormLanguage & FormLangId

@session_start();
$s_id=session_id();

$modulnr=5;
$title="Rykker";
$css="../css/standard.css";

global $menu;
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$inkasso=if_isset($_POST['inkasso']);
$mail_fakt=if_isset($_POST['mail_fakt']);
$submit=if_isset($_POST['submit']);
$rykker_id=if_isset($_GET['rykker_id']);

if ($rykker_id && $inkasso) {
	db_modify("update ordrer set felt_5 = 'inkasso' where id='$rykker_id'",__FILE__ . " linje " . __LINE__);
	$felt_5='inkasso';
	#cho "update ordrer set felt_5 = 'inkasso' where id = '$rykker_id'<br>";
	#xit;
}
if ($submit || $inkasso) {
	$linjeantal=if_isset($_POST['linjeantal']);
	if (!$rykker_id) $rykker_id=if_isset($_POST['rykker_id']);
	$r=db_fetch_array(db_select("select status from ordrer where id = '$rykker_id'",__FILE__ . " linje " . __LINE__)); #20140903
	$status=$r['status'];
	$rykkernr=if_isset($_POST['rykkernr']);
	$submit=trim(if_isset($_POST['submit']));
	if (strstr($submit, "Opdat")) $submit="Opdater";
	$linje_id=if_isset($_POST['linje_id']);
	$kontakt=db_escape_string(trim($_POST['kontakt']));
	$email=db_escape_string(trim($_POST['email']));
	$valuta=trim($_POST['valuta']);
	if (!isset($felt_5)) $felt=trim($_POST['felt_5']);
	$ny_valuta=$_POST['ny_valuta']; #21040628
	$formularsprog   = $_POST['formularsprog'];
	$newFormLanguage = $_POST['newFormLanguage'];
	if ($mail_fakt && (!strpos($email,"@") || !strpos($email,".") || !strlen($email)>5)) { 
		$mail_fakt=NULL;
		print "<BODY onLoad=\"javascript:alert('e-mail ikke gyldig')\">";
	}
	if ($ny_valuta != $valuta) { #21040628 ->
	if ($valuta=='DKK') $valutakurs=100;
			$r=db_fetch_array(db_select("select valutakurs,fakturadate from ordrer where id = '$rykker_id'",__FILE__ . " linje " . __LINE__));
			$valutakurs=$r['valutakurs'];
			if ($valuta=='DKK') $valutakurs=100;
		if (!$valutakurs) {
			$qtxt = "select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$valuta' ";
			$qtxt.= "and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$r[fakturadate]' order by valuta.valdate desc";
			if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$valutakurs=$r2['kurs'];
			} else {
				print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs i $valuta d. $r[fakturadate]')\">";	
				exit;
			}
		}
		if ($ny_valuta=='DKK') $ny_valutakurs=100; 
		else {
			$qtxt = "select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$ny_valuta' and ";
			$qtxt.= "valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$r[fakturadate]' ";
 			$qtxt.= "order by valuta.valdate desc";
			if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$ny_valutakurs=$r2['kurs'];
			} else {
				print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs i $valuta d. $r[fakturadate]')\">";	
				exit;
			}
		}
		$qtxt = "update ordrelinjer set pris =pris*$valutakurs/$ny_valutakurs where varenr != '' and varenr is not NULL and ordre_id='$rykker_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "update ordrer set valuta ='$ny_valuta',valutakurs ='$ny_valutakurs',felt_5='$felt_5'  where id='$rykker_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	#20140707
	$qtxt = "update ordrer set email ='$email',mail_fakt='$mail_fakt',kontakt='$kontakt',sprog = '$newFormLanguage' ";
	$qtxt.= "where id='$rykker_id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($submit=="Send" && $mail_fakt) {
		#	print "<BODY onLoad=\"return confirm('Dokumentet sendes pr. mail til $email')\">";
	}
	if ($submit=="Slet valgte") {
		$rykkerbox=$_POST['rykkerbox'];
		$slettet=0;
		for ($x=1; $x<=$linjeantal; $x++) {
			if ($rykkerbox[$x]=='on') {
				db_modify("delete from ordrelinjer where id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
				$slettet++;
			}
		}
		if ($slettet==$linjeantal) {
#		#cho "delete from ordrer where id=$rykker_id<br>";
			db_modify("delete from ordrer where id=$rykker_id",__FILE__ . " linje " . __LINE__);
			$rykker_id=0;
		} 
	} elseif ($submit=="Opdater" && $status < 3) { #20140903
		if ($newFormLanguage != $formularsprog) {
			$qtxt = "select kodenr from grupper where art = 'VSPR' and box1 = '$newFormLanguage'"; 
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $FormLangId=$r['kodenr'];
			else $FormLangId = 0;
			$qtxt = "select distinct(vare_id) as vare_id from ordrelinjer where ordre_id = '$rykker_id'"; 
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if ($r['vare_id']) {
					$qtxt = "select * from varetekster where vare_id='$r[vare_id]' and sprog_id = '$FormLangId'"; 
					if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
						$qtxt = "update ordrelinjer set beskrivelse = '";
						$qtxt.= db_escape_string($r2['tekst']);
						$qtxt.= "' where ordre_id = '$rykker_id' and vare_id = '$r[vare_id]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
					}
				}
			}
		} else {
			$formularsprog = 	$newFormLanguage;
			$beskrivelse=$_POST['beskrivelse'];
			$dkpris=$_POST['dkpris'];
			$ny_beskrivelse=db_escape_string(trim($_POST['ny_beskrivelse']));
			if ($ny_beskrivelse) {
				$qtxt = "insert into ordrelinjer(ordre_id, beskrivelse) values ($rykker_id, '$ny_beskrivelse')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}	else {
				for ($x=1; $x<=$linjeantal; $x++) {
					$beskrivelse[$x]=db_escape_string($beskrivelse[$x]);
#			$pris[$x]=usdecimal($dkpris[$x]); 2009.02.05 - Pris fjernet fra update da den elles bogfoerer hele beloebet.
					$qtxt = "update ordrelinjer set beskrivelse = '$beskrivelse[$x]' where id=$linje_id[$x]";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}	
			}
		}
	} elseif (strstr($submit,"Udskriv") || $submit=="Send" || $_POST['inkasso']) {
		if ($_POST['inkasso']) {
			$qtxt="select box9 from grupper where art = 'DIV' and kodenr = '4'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['box9'] && is_numeric($r['box9'])) {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=inkassoprint.php?rykker_id=$rykker_id&inkasso=$r[box9]\">";
				exit;
			} 
		} else {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=rykkerprint.php?";
			print "rykker_id=$rykker_id&rykkernr=$rykkernr&kontoantal=1&formularsprog=$formularsprog\">";
		}
	} elseif (strstr($submit,"Tilbage")) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=rapport.php?rapportart=openpost\">";
		exit;
	}
}


	
if ($rykker_id) {
	$query = db_select("select * from ordrer where id = '$rykker_id'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$konto_id = $row['konto_id'];
	$kontonr = htmlentities($row['kontonr'],ENT_COMPAT,$charset);
	$firmanavn = htmlentities($row['firmanavn'],ENT_COMPAT,$charset);
	$addr1 = htmlentities($row['addr1'],ENT_COMPAT,$charset);
	$addr2 = htmlentities($row['addr2'],ENT_COMPAT,$charset);
	$postnr = htmlentities($row['postnr'],ENT_COMPAT,$charset);
	$bynavn = htmlentities($row['bynavn'],ENT_COMPAT,$charset);
	$land = htmlentities($row['land'],ENT_COMPAT,$charset);
	$kontakt = htmlentities($row['kontakt'],ENT_COMPAT,$charset);
	$email = htmlentities($row['email'],ENT_COMPAT,$charset);
	$valuta = htmlentities($row['valuta'],ENT_COMPAT,$charset);
	$mail_fakt = htmlentities($row['mail_fakt'],ENT_COMPAT,$charset);
	$kundeordnr = htmlentities($row['kundeordnr'],ENT_COMPAT,$charset);
	$formularsprog = $row['sprog'];
	$cvrnr = $row['cvrnr'];
	$ean = htmlentities($row['ean'],ENT_COMPAT,$charset);
	$institution = htmlentities($row['institution'],ENT_COMPAT,$charset);
	$betalingsbet = trim($row['betalingsbet']);
		$betalingsdage = $row['betalingsdage'];
	$ref = trim(htmlentities($row['ref'],ENT_COMPAT,$charset));
	$ordrenr=$row['ordrenr'];
	$ordredato=dkdato($row['ordredate']);
	$fakturadate=$row['fakturadate'];
	$momssats=$row['momssats'];
	$status=$row['status'];
	$rykkernr=substr($row['art'],-1);
	if ($row['valuta']) $valuta=$row['valuta'];
	else $valuta='DKK';
	if (!$status){$status=0;}
	$kontonr=$row['kontonr'];
	$felt_5=$row['felt_5'];
	($felt_5=='inkasso')?$inkasso=2:$inkasso=0;
	$intxt1=0;
	$intxt2=0;
	if (!$inkasso && date('U')-strtotime($fakturadate)>=11*24*60*60) { 
		$inkassotxt1='10 dage';
		$inkassotxt2='inkasso';
		$formular=$rykkernr+5;
		if ($formular<6) $formular=6;
		$qtxt="select beskrivelse from formularer where formular = '$formular' and art = '2' and lower(sprog)='dansk'";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
		if (strstr(strtolower($r['beskrivelse']),$inkassotxt1)) $intxt1=1;
		if (strstr(strtolower($r['beskrivelse']),$inkassotxt2)) $intxt2=1;
		if ($intxt1 && $intxt2) $inkasso=1;
		}
	}
} else {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=rapport.php?rapportart=openpost\">";
	exit;
}

if ($menu=='T') {
	include_once '../includes/topmenu/header.php';
	if ($felt_5=='inkasso') {
		print "<div class='$kund'>".findtekst(1143,$sprog_id)."</div>";
	} else {
		print "<div class='$kund'>".findtekst(1143,$sprog_id)."</div>";
	}
	print "<div class='content-noside'>";
	print "<div class='dataTablediv'>"; 
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=rapport.php?rapportart=openpost accesskey=L>".findtekst(30,$sprog_id)."</a></td>";
	if ($felt_5=='inkasso') print "<td width=\"80%\" $top_bund>".findtekst(1144,$sprog_id)."</td>";
	else print "<td width=\"80%\" $top_bund>".findtekst(1143,$sprog_id)."</td>";
	print "<td width=\"10%\" $top_bund><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<td align = center valign = top>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
}

$x=0;
$valutakode[$x]='DKK';
$valutabesk[$x]='Danske kroner';
$q = db_select("select * from grupper where art = 'VK' order by box1",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)){
	$x++;
	$valutakode[$x]=$r['box1'];
	$valutabesk[$x]=$r['beskrivelse'];
}

print "<form name=\"rykker\" action=\"rykker.php?rykker_id=$rykker_id\" method=\"post\">";
print "<input type = 'hidden' name='formularsprog' value=\"$formularsprog\">";
print "<input type = 'hidden' name='rykker_id'     value=\"$rykker_id\">";
print "<input type = 'hidden' name='rykkernr'      value=\"$rykkernr\">";
print "<input type = 'hidden' name='valuta'        value=\"$valuta\">"; #21040628
print "<input type = 'hidden' name='felt_5'        value=\"$felt_5\">"; #21070303

$ordre_id=if_isset($id);
if ($menu=='T') {
	print "<table cellpadding=0 cellspacing=0 border=0 width=100% class='dataTableNTH'>";
	print "<tr>";
	print "<td width=25%><b>".findtekst(804,$sprog_id).".:</b></td><td width=25%>$kontonr</td><td class='table-border-right' width='1%'></td>";
	print "<td width=24%><b>".findtekst(48,$sprog_id).":</b></td><td width=25%>$cvrnr</td>";
	print "</tr>\n";
	print "<tr>";
	print "<td><b>".findtekst(360,$sprog_id).":</b></td><td>$firmanavn</td><td class='table-border-right' width='1%'></td>";
	print "<td><b>EAN.nr.:</b></td><td>$ean</td>";
	print "</tr>\n";
	print "<tr>";
	print "<td><b>".findtekst(648,$sprog_id).":</b></td><td>$addr1</td><td class='table-border-right' width='1%'></td>";
	print "<td><b>Institution:</b></td><td>$institution</td>";
	print "</tr>\n";
	print "<tr>";
	print "<td></td><td>$addr2</td><td class='table-border-right' width='1%'></td>";
	print "<td><b>Rykkerdato:</b></td><td width=100>$ordredato</td>";
	print "</tr>\n";
	print "<tr>";
	print "<td><b>".findtekst(549,$sprog_id).":</b></td><td>$postnr $bynavn</td><td class='table-border-right' width='1%'></td>";
	print "<td><b>".findtekst(935,$sprog_id).":</b></td><td>$betalingsbet&nbsp;+&nbsp;$betalingsdage</td>";
	print "</tr>\n";
	print "<tr>";
	print "<td><b>".findtekst(364,$sprog_id).":</b></td><td>$land</td><td class='table-border-right' width='1%'></td>";
	print "<td><b>".findtekst(1097,$sprog_id).":</b></td><td>$ref</td>";
	print "</tr>\n";
	print "<tr>";
	print "<td><b>Att.:</b></td><td><input type=text name=kontakt size = \"20\" class=\"inputbox\" value=\"$kontakt\"></td><td class='table-border-right' width='1%'></td>\n";
	if ($mail_fakt=='on') $mail_fakt='checked';
	print "<td><b>".findtekst(1145,$sprog_id).":</b></td><td><label class='checkContainerOrdreliste'><input type=checkbox name=mail_fakt $mail_fakt><span class='checkmarkOrdreliste'></span></label></td>";
	print "</tr>\n";
	print "<tr>";
	print "<td><b>E-mail:</b></td><td><input type=text name=email size = \"20\" class=\"inputbox\" value=\"$email\"></td><td class='table-border-right' width='1%'></td>";
	print "<td><b>".findtekst(1069,$sprog_id).":</b></td><td><select class=\"inputbox\" name=ny_valuta>";
	for ($x=0;$x<count($valutakode);$x++) {
		if ($valuta==$valutakode[$x]) print "<option title=\"$valutabesk[$x]\" onchange=\"javascript:docChange = true;\">$valutakode[$x]</option>";
	}
	for ($x=0;$x<count($valutakode);$x++) {
		if ($valuta!=$valutakode[$x]) print "<option title=\"$valutabesk[$x]\" onchange=\"javascript:docChange = true;\">$valutakode[$x]</option>";
	}
	print "</SELECT></td>";
	print "</tr>\n";
	print "<tr><td colspan=10 class='border-hr-top'></td></tr>\n";
	print "</tbody></table>";
	print "<table cellpadding=0 cellspacing=0 border=0 width=100% class='dataTable'><thead>";
} else {
	print "<tr><td width=50%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
	print "<tr><td width=100><b>".findtekst(804,$sprog_id)."</td><td width=100>$kontonr</td></tr>\n";
	print "<tr><td><b>".findtekst(360,$sprog_id)."</td><td>$firmanavn</td></tr>\n";
	print "<tr><td><b>".findtekst(648,$sprog_id)."</td><td>$addr1</td></tr>\n";
	print "<tr><td></td><td>$addr2</td></tr>\n";
	print "<tr><td><b>".findtekst(549,$sprog_id)."</td><td>$postnr $bynavn</td></tr>\n";
	print "<tr><td><b>".findtekst(364,$sprog_id)."</td><td>$land</td></tr>\n";

	print "<tr><td><b>Att.:</td><td><input type=text name=kontakt size = \"20\" class=\"inputbox\" value=\"$kontakt\"></td></tr>\n";
	print "<tr><td><b>e-mail</td><td><input type=text name=email size = \"20\" class=\"inputbox\" value=\"$email\"></td></tr>\n";
	print "</tbody></table></td>";
	print "<td width=50%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
	print "<tr><td><b>".findtekst(48,$sprog_id)."</td><td>$cvrnr</td></tr>\n";
	print "<tr><td><b>EAN.nr</td><td>$ean</td></tr>\n";
	print "<tr><td><b>Institution</td><td>$institution</td></tr>\n";
	print "<tr><td width=100><b>Rykkerdato</td><td width=100>$ordredato</td></tr>\n";
	print "<tr><td><b>".findtekst(935,$sprog_id)."</td><td>$betalingsbet&nbsp;+&nbsp;$betalingsdage</td>";
	print "<tr><td><b>".findtekst(1097,$sprog_id)."</td><td>$ref</td></tr>\n";
	if ($mail_fakt=='on') $mail_fakt='checked';
	print "<tr><td><b>".findtekst(1145,$sprog_id)."</td><td><input type=checkbox name=mail_fakt $mail_fakt></td></tr>\n";
	print "<tr><td><b>".findtekst(1069,$sprog_id)."</b></td><td><select class=\"inputbox\" name=ny_valuta>";
	for ($x=0;$x<count($valutakode);$x++) {
		if ($valuta==$valutakode[$x]) print "<option title=\"$valutabesk[$x]\" onchange=\"javascript:docChange = true;\">$valutakode[$x]</option>";
	}
	for ($x=0;$x<count($valutakode);$x++) {
		if ($valuta!=$valutakode[$x]) print "<option title=\"$valutabesk[$x]\" onchange=\"javascript:docChange = true;\">$valutakode[$x]</option>";
	}
	print "</SELECT></td></tr>";
	$qtxt = "select distinct sprog from formularer where sprog != 'Dansk'";
	if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		print "<tr><td title=\"".findtekst(1468, $sprog_id)."\"><b>&nbsp;".findtekst(801, $sprog_id)."</b></span></td>\n";
		print "<td><select class=\"inputbox\" style=\"width:130px\" name=\"newFormLanguage\" onchange='this.form.submit()'>\n";
		print "<option>$formularsprog</option>\n";
		$q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) print "<option>$r[sprog]</option>\n";
		print "</SELECT></td></tr>";
	} 

	print "</tbody></table></td>";
	print "</td></tr><tr><td align=center colspan=3><table cellpadding=0 cellspacing=0 border=1 width=100%><tbody>";
	print "<tr><td colspan=4></td></tr><tr>";
}
	print "</tr>\n";
	if ($menu=='T') {
		print "<th align=center class='text-center'><b>".lcfirst(findtekst(635,$sprog_id))."</th><th class='text-center' align=center><b>".findtekst(893,$sprog_id)."</th><th class='text-center' align=center><b>".lcfirst(findtekst(914,$sprog_id))."</th><th class='text-center' align=center><b>".findtekst(934,$sprog_id)." i $valuta</th><th></th>";
		print "</thead><tbody>";
	} else {
		print "<td align=center><b>".lcfirst(findtekst(635,$sprog_id))."</td><td align=center><b>".findtekst(893,$sprog_id)."</td><td align=center><b>".lcfirst(findtekst(914,$sprog_id))."</td><td align=center><b>".findtekst(934,$sprog_id)." i $valuta</td>";
	}
	$x=0;
	$sum=0;
	$ialt=0;
#cho "select * from ordrelinjer where ordre_id = '$rykker_id' order by posnr<br>";
	$q = db_select("select * from ordrelinjer where ordre_id = '$rykker_id' order by serienr, posnr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$pris[$x]=$r['pris'];
		$linje_id[$x]=$r['id'];
		$vare_id[$x]=$r['vare_id'];
		$varenr[$x]=htmlentities($r['varenr'],ENT_COMPAT,$charset);
		$beskrivelse[$x]=htmlentities($r['beskrivelse'],ENT_COMPAT,$charset);
		$enhed[$x]=htmlentities($r['enhed'],ENT_COMPAT,$charset);
		if ($r['serienr']) $dato[$x]=dkdato($r['serienr']);
		if ($vare_id[$x]) $sum=$sum+$r['pris'];
		if (($r['enhed'])&&(is_numeric($r['enhed']))) {
			$r2 = db_fetch_array(db_select("select * from openpost where id = '$r[enhed]'",__FILE__ . " linje " . __LINE__));
			if ($r2['valuta']) $opp_valuta[$x]=$r2['valuta'];
			else $opp_valuta[$x]='DKK';
			if ($r2['valutakurs']) $opp_valkurs[$x]=$r2['valutakurs'];
			else $opp_valkurs[$x]=100;
			if ($valuta!="DKK") {
				if ($r3=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$r2[transdate]' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
					$valutakurs=$r3['kurs'];
				} else print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs i $valuta for faktura $r2[faktnr]')\">";	
			} else $valutakurs=100;
			$dkkpris[$x]=$r2['amount']*$opp_valkurs[$x]/100;
			if ($valuta!="DKK" && ($opp_valuta[$x]!=$valuta))  $pris[$x]=$dkkpris[$x]*100/$valutakurs;
			elseif ($opp_valuta[$x]==$valuta) $pris[$x]=$r2['amount'];
			else $pris[$x]=$dkkpris[$x];

			$faktnr[$x]=$r2['faktnr'];
			$udlignet=$r2['udlignet'];
			$inputtype[$x]="readonly";
		} else $inputtype[$x]="text";
		$ialt=$ialt+$pris[$x];
#		print "<input type = 'hidden' name = 'linje_id[$x]' value = \"$linje_id[$x]\">";
	}
	$linjeantal=$x;
	print "<input type = 'hidden' name='linjeantal' value=\"$x\">";
	for ($x=1; $x<=$linjeantal; $x++) {
		if ($pris[$x]) $dkpris[$x]=dkdecimal($pris[$x],2);
		else $dkpris[$x]='';
#		print "<tr bgcolor=\"$linjebg\">";
		print "<tr><input type = 'hidden' name = 'linje_id[$x]' value = \"$linje_id[$x]\">";
		if ($dato[$x]) {
			print "<td align=center>$dato[$x]</td>";
			print "<td align=center>$faktnr[$x]</td>";
			if ($status<3) {
				print "<td><input class=\"inputbox\" type=\"text\" size=\"44\" name=\"beskrivelse[$x]\" value=\"$beskrivelse[$x]\"></td>";
				print "<td><input class=\"inputbox\" \"$inputtype[$x]\" style=\"text-align:right\" size=\"10\" name=\"dkpris[$x]\" value=\"$dkpris[$x]\"></td>";
			} else {
				print "<td>$beskrivelse[$x]</td>";
				print "<td align=right>$dkpris[$x]</td>";
			}
		}	else {
			print "<td colspan=3><input class=\"inputbox\" type=\"text\" size=\"60\" name=\"beskrivelse[$x]\" value=\"$beskrivelse[$x]\"></td><td></td>";
		}
		if ($status<3) print "<td align=center><label class='checkContainerOrdreliste'><input type=checkbox name=rykkerbox[$x]><span class='checkmarkOrdreliste'></span></label>";	
		print "</td></tr>";
	}
	if ($dato[$linjeantal]&&$status<3) {	
		$x++;
		print "<tr><td colspan=3><input class=\"inputbox\" type=\"text\" size=\"60\" name=\"ny_beskrivelse\"></td>";
	}
	if ($menu=='T') {
		print "</tbody><tfoot>";
	} else {
		print "<tr><td colspan=5><br></td></tr>\n";
	}
	print "<tr><td align=right colspan=4>I alt ".dkdecimal($ialt,2)."</td><td></td></tr>";
	if ($menu=='T') {
		print "<tr><td align=center colspan=10><input type=submit value=\"".findtekst(1091,$sprog_id)."\" name=\"submit\">";

		if ( strlen("which ps2pdf")) {
			if ($mail_fakt) print "&nbsp;•&nbsp;<input type=submit value=\"Send\" name=\"submit\" onclick=\"return confirm('Send rykker som mail til $email?')\">"; 
			else print "&nbsp;•&nbsp;<input type=submit value=\"".findtekst(880,$sprog_id)."\" name=\"submit\">";#20210701
		} else {
			if ($mail_fakt) print "&nbsp;•&nbsp;<input type=submit value=\"Send\" name=\"submit\" onclick=\"return confirm('Send rykker som mail til $email?') disabled=\"disabled\">"; 
			else print "&nbsp;•&nbsp;<input type=submit value=\"".findtekst(880,$sprog_id)."\" name=\"submit\" disabled=\"disabled\">";
		}
		if ($status<3) {
			db_modify("update ordrer set sum='$sum' where id='$rykker_id'",__FILE__ . " linje " . __LINE__);
			print "&nbsp;•&nbsp;<input type=submit value=\"".findtekst(1146,$sprog_id)."\" name=\"submit\">";
		} elseif ($inkasso == 1) {
			print "&nbsp;•&nbsp;<input type='submit' value=\"Inkasso\" name=\"".findtekst(1147,$sprog_id)."\" onclick=\"return confirm('Send sagen til inkasso?')\">";
		} elseif ($inkasso == 2) {
			print "&nbsp;•&nbsp;<input type='submit' value=\"Inkasso\" name=\"".findtekst(1147,$sprog_id)."\" onclick=\"return confirm('Send sagen til inkasso igen?')\">";
		}
		print "</td></tr>";
		print "</form>";
		print "</tfoot></table></div>";
		print "<center><input type='button' onclick=\"location.href='rapport.php?rapportart=openpost'\" accesskey='L' value='".findtekst(30,$sprog_id)."'></center>";
	} else {
		print "</tbody></table></td></tr>\n";
		print "<tr><td align=center colspan=8>";
		print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>";
#		print "<td align=center><input type=submit value=\"Tilbage\" name=\"submit\"></td>";
		print "<td align=center><input type=submit value=\"".findtekst(1091,$sprog_id)."\" name=\"submit\"></td>";

		if ( strlen("which ps2pdf")) {
			if ($mail_fakt) print "<td align=center><input type=submit value=\"Send\" name=\"submit\" onclick=\"return confirm('Send rykker som mail til $email?')\"></td>"; 
			else print "<td align=center><input type=submit value=\"".findtekst(880,$sprog_id)."\" name=\"submit\"></td>";#20210701
		} else {
			if ($mail_fakt) print "<td align=center><input type=submit value=\"Send\" name=\"submit\" onclick=\"return confirm('Send rykker som mail til $email?') disabled=\"disabled\"></td>"; 
			else print "<td align=center><input type=submit value=\"".findtekst(880,$sprog_id)."\" name=\"submit\" disabled=\"disabled\"></td>";
		}
		if ($status<3) {
			db_modify("update ordrer set sum='$sum' where id='$rykker_id'",__FILE__ . " linje " . __LINE__);
			print "<td align=center><input type=submit value=\"".findtekst(1146,$sprog_id)."\" name=\"submit\"></td>";
		} elseif ($inkasso == 1) {
			print "<td align=center><input type='submit' value=\"Inkasso\" name=\"".findtekst(1147,$sprog_id)."\" onclick=\"return confirm('Send sagen til inkasso?')\"></td>";
		} elseif ($inkasso == 2) {
			print "<td align=center><input type='submit' value=\"Inkasso\" name=\"".findtekst(1147,$sprog_id)."\" onclick=\"return confirm('Send sagen til inkasso igen?')\"></td>";
		}
		print "</form>";
		print "</tbody></table>
		</td></tr>
		</tbody></table>";
	}

if ($menu=='T') {
	include_once '../includes/topmenu/footerDebRapporter.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>
