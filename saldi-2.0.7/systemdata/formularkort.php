<?php
// -------systemdata/formularkort-------lap 2.0.7------2009-05-15-14:51--
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();

$title="Formulareditor";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
	
$id=$db_id;
	
/*
if (isset($_GET['upload']) && $_GET['upload']) {
	upload($id);
	exit;
}
*/
if (isset($_GET['nyt_sprog']) && $_GET['nyt_sprog']) {
	$nyt_sprog=$_GET['nyt_sprog'];
#	exit;
}
$id = if_isset($_GET['id']);
if(isset($_GET['returside']) && $_GET['returside']) {
	$returside= $_GET['returside'];
#	$ordre_id = $_GET['ordre_id'];
#	$fokus = $_GET['fokus'];
}
else {$returside="syssetup.php";}
$navn=if_isset($_GET['navn']);

if ($_POST) {
	$nyt_sprog=if_isset($_POST['nyt_sprog']);
	$skabelon=if_isset($_POST['skabelon']);
	$formular=if_isset($_POST['formular']);
	$formularsprog=if_isset($_POST['sprog']);
	$art=if_isset($_POST['art']);
	
	if (isset($_POST['linjer'])) {
		$submit=$_POST['linjer'];
		if (strstr($submit, "Opdat")) $submit="Opdater";
		$beskrivelse=$_POST['beskrivelse'];
		$ny_beskrivelse=$_POST['ny_beskrivelse'];
		$id=$_POST['id'];
		$xa=$_POST['xa'];
		$ya=$_POST['ya'];
		$xb=$_POST['xb'];
		$yb=$_POST['yb'];
#		$art_nr=$_POST['art_nr'];
		$str=$_POST['str'];
		$color=$_POST['color'];
		$form_font=$_POST['form_font'];
		$fed=$_POST['fed'];
		$placering=$_POST['placering'];
		$kursiv=$_POST['kursiv'];
		$side=$_POST['side'];
		$linjeantal=$_POST['linjeantal'];
		$gebyr=$_POST['gebyr'];
		$rentevnr=$_POST['rentevnr'];
		$rentesats=$_POST['rentesats'];
	}
	
	list($art_nr, $art_tekst)=split(":", $art);
	list($form_nr, $form_tekst)=split(":", $formular);
	
	if (isset($_POST['op']) || isset($_POST['hojre'])) { #Flytning af 0 punkt.
		$op=$_POST['op']*1; $hojre=$_POST['hojre']*1;
		$query=db_select("select id, xa, xb, ya, yb from formularer where formular=$form_nr and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);
		while ($row=db_fetch_array($query)){
			db_modify("update formularer set xa=$row[xa]+$hojre, ya=$row[ya]+$op where id=$row[id]",__FILE__ . " linje " . __LINE__);
			if ($row[yb]) db_modify("update formularer set xb=$row[xb]+$hojre, yb=$row[yb]+$op where id=$row[id]",__FILE__ . " linje " . __LINE__);
		}
		if ($op<0) {
			$op=$op*-1;
			$otext="ned"; 
		}
		else $otext="op";
		if ($hojre<0) {
			$hojre=$hojre*-1;
			$htext="venstre"; 
		}
		else $htext="h&oslash;jre";
		print "<BODY onLoad=\"javascript:alert('Logo, tekster og linjer er flyttet $op mm $otext og $hojre mm til $htext')\">";
		$linjeantal=0; #
	}
	if ($submit=='Opdater' && $form_nr>=6 && $form_nr<=8 && $art_nr==2 && $gebyr) { #Rykkergebyr
		if ($r1=db_fetch_array(db_select("select id from varer where varenr = '$gebyr'",__FILE__ . " linje " . __LINE__))) { 
			if ($r2=db_fetch_array(db_select("select id from formularer where beskrivelse ='GEBYR' and formular='$form_nr' and art=2 and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__))) {
				db_modify("update formularer set xb='$r1[id]' where id = $r2[id]",__FILE__ . " linje " . __LINE__);
			}	else {
					db_modify("insert into formularer (beskrivelse, formular, art, xb, sprog) values ('GEBYR', '$form_nr', '2', '$r1[id]', '$formularsprog')",__FILE__ . " linje " . __LINE__);
				}
		} else print "<BODY onLoad=\"javascript:alert('Varenummeret $gebyr findes ikke i varelisten')\">";
	} elseif (($submit=='Opdater')&&($form_nr==6)&&($art_nr==2)&&(!$gebyr)) db_modify("delete from formularer where beskrivelse = 'GEBYR' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);
	if ($submit=='Opdater' && $form_nr>=6 && $form_nr<=8 && $art_nr==2 && $rentevnr) { #Rykkerrenter
#		$rentevnr=$_POST['rentevnr'];
		$rentesats=usdecimal($rentesats);
		if ($r1=db_fetch_array(db_select("select id from varer where varenr = '$rentevnr'",__FILE__ . " linje " . __LINE__))) { 
			if ($r2=db_fetch_array(db_select("select id from formularer where beskrivelse ='GEBYR' and formular='$form_nr' and art=2 and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__))) {
				db_modify("update formularer set yb='$r1[id]', str='$rentesats' where id = $r2[id]",__FILE__ . " linje " . __LINE__);
			}	else {
					db_modify("insert into formularer (beskrivelse, formular, art, yb, str, sprog) values ('GEBYR', '$form_nr', '2', '$r1[id]', '$rentesats', '$formularsprog')",__FILE__ . " linje " . __LINE__);
				}
		} else print "<BODY onLoad=\"javascript:alert('Varenummeret $gebyr findes ikke i varelisten')\">";
	} elseif (($submit=='Opdater')&&($form_nr==6)&&($art_nr==2)&&(!$gebyr)) db_modify("delete from formularer where beskrivelse = 'GEBYR' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);
	
	if ($_POST['linjer']){ 
		transaktion('begin');
		for ($x=0; $x<=$linjeantal; $x++) {
			if ((trim($xa[$x])=='-')&&($id[$x])&&($beskrivelse[$x]!='LOGO')) {db_modify("delete from formularer where id =$id[$x] and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);}
			else {
					if ($art==5 && $xa[$x]==2) {
						$beskrivelse[$x]=str_replace("\n","<br>",$beskrivelse[$x]); 
					}
					$beskrivelse[$x]=addslashes($beskrivelse[$x]);
				if ($ny_beskrivelse[$x]) {$beskrivelse[$x]=trim($beskrivelse[$x]." $".$ny_beskrivelse[$x].";");}
				$xa[$x]=$xa[$x]*1; $ya[$x]=$ya[$x]*1; $xb[$x]=$xb[$x]*1; $yb[$x]=$yb[$x]*1; $str[$x]=$str[$x]*1; $color[$x]=$color[$x]*1;
				if ($x==0 ||(!$id[$x] && $art_nr==5)) {
					if ($xa[$x]>0) {
						if (($art!='1') && ($str[$x]<=1)) $str[$x]=10;
						db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, placering, sprog) values ('$beskrivelse[$x]', $form_nr, $art_nr, $xa[$x], $ya[$x], $xb[$x], $yb[$x], $str[$x], $color[$x], '$form_font[$x]', '$fed[$x]', '$kursiv[$x]', '$side[$x]', '$placering[$x]', '$formularsprog')",__FILE__ . " linje " . __LINE__);
					}
				}
				elseif ($id[$x]) {
					db_modify("update formularer set beskrivelse='$beskrivelse[$x]', xa=$xa[$x], ya=$ya[$x], xb=$xb[$x], yb=$yb[$x], str=$str[$x], color=$color[$x], font='$form_font[$x]', fed='$fed[$x]', kursiv='$kursiv[$x]', side='$side[$x]', placering='$placering[$x]'	where id = $id[$x]",__FILE__ . " linje " . __LINE__);
				}
			} 
		}
		transaktion('commit');	 
	}
}
#}


print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td width=100% height=1% align=\"center\" valign=\"top\" collspan=2>";
print "<table width=\"100%\" height=\"1%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=$returside accesskey=\"l\">Luk</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Formularkort</td>";
print "<td width=\"5%\" $top_bund align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><span title=\"Opret eller nedl&aelig;g sprog\"><a href=formularkort.php?nyt_sprog=yes accesskey=\"s\">Sprog</a></span></td>";
print "<td width=\"5%\" $top_bund align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><span title=\"Indl&aelig;s eller fjern logo\"><a href=logoupload.php?upload=yes accesskey=\"u\">Logo</a></span></td>";
print "</tbody></table></td></tr>";
if ($nyt_sprog) sprog($nyt_sprog, $skabelon);
print "<tr><td align=center width=100%><table align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";

print "<tr><td colspan=10 align=center><table><tbody>";
print "<form name=formularvalg action=$_SERVER[PHP_SELF] method=\"post\">";
print "<tr><td>  Formular</td>";
print "<td><SELECT NAME=formular>";
if ($formular) print "<option>$formular</option>";
print "<option>1:Tilbud</option>";
print "<option>2:Ordrebekr&aelig;ftelse</option>";
print "<option>3:F&oslash;lgeseddel</option>";
print "<option>4:Faktura</option>";
print "<option>5:Kreditnota</option>";
print "<option>6:Rykker_1</option>";
print "<option>7:Rykker_2</option>";
print "<option>8:Rykker_3</option>";
print "</SELECT></td>";
print "<td>  Art</td>";
print "<td><SELECT NAME=art>";
if ($formular) print "<option>$art</option>";
print "<option>1:Linjer</option>";
print "<option>2:Tekster</option>";
print "<option>3:Ordrelinjer</option>";
print "<option>4:Flyt center</option>";
print "<option>5:Mail tekst</option>";
print "</SELECT></td>";
print "<td>  Sprog</td>";
print "<td><SELECT NAME=sprog>";
if (!trim($formularsprog)) $formularsprog="Dansk";
print "<option>$formularsprog</option>";
$q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($formularsprog!=$r['sprog']) print "<option>$r[sprog]</option>";
}
	print "</SELECT></td>";
print "<td><input type=submit accesskey=\"v\" value=\"V&aelig;lg\" name=\"formularvalg\"></td></tr>";
print "</form></tbody></table></td></tr>";
#if ($form_nr) {

print "<form name=linjer action=$_SERVER[PHP_SELF]?formular=$formular&art=$art method=\"post\">";
	print "<input type = hidden name = formular value = \"$formular\">";
	print "<input type = hidden name = sprog value = \"$formularsprog\">";
	print "<input type = hidden name = art value = \"$art\">";

if ($art_nr==1) {
	print "<tr><td><br></td></tr>";
	print "<tr><td colspan=10 align=center> LOGO</td></tr>";
	print "<tr><td><br></td></tr>";
		
	print "<tr><td></td><td></td><td align=center>X</td><td align=center> Y</td></tr>";
	$x=1;
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse ='LOGO' and sprog = '$formularsprog'",__FILE__ . " linje " . __LINE__);
	$row=db_fetch_array($query);
	print "<tr>";
	print "<input type=hidden name=id[$x] value=$row[id]><input type=hidden name=beskrivelse[$x] value='LOGO'>";
	print "<td colspan=2></td><td align=center><input type=text style=text-align:right size=5 name=xa[$x] value=$row[xa]>";
	print "<td align=center><input type=text style=text-align:right size=5 name=ya[$x] value=$row[ya]>";

	print "<tr><td><br></td></tr>";
	print "<tr><td colspan=6 align=center> Linjer</td></tr>";
	print "<tr><td><br></td></tr>";

	print "<tr><td colspan=2 align=center> Start</td>";
	print "<td colspan=2 align=center> Slut</td></tr>";
	print "<tr><td align=center>X</td><td align=center> Y</td><td align=center> X</td><td align=center> Y</td>";
	print "<td align=center> Bredde</td><td align=center> Farve</td></tr>";

	$x=0;
	print "<tr>";
	print "<td align=center><input type=text style=text-align:right size=5 name=xa[$x]>";
	print "<td align=center><input type=text style=text-align:right size=5 name=ya[$x]>";
	print "<td align=center><input type=text style=text-align:right size=5 name=xb[$x]>";
	print "<td align=center><input type=text style=text-align:right size=5 name=yb[$x]>";
	print "<td align=center><input type=text style=text-align:right size=5 name=str[$x]>";
	print "<td align=center><input type=text style=text-align:right size=5 name=color[$x]>";
	print "</tr>";
 
	# echo "select * from formularer where formular = $form_nr and art = $art_nr<br>"; 
	$x=1;
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse !='LOGO' and sprog='$formularsprog' order by ya,xa,yb,xb",__FILE__ . " linje " . __LINE__);
	while ($row=db_fetch_array($query)){
		$x++; 
		print "<tr>";
		print "<input type=hidden name=id[$x] value=$row[id]>";
		print "<td align=center><input type=text style=text-align:right size=5 name=xa[$x] value=$row[xa]>";
		print "<td align=center><input type=text style=text-align:right size=5 name=ya[$x] value=$row[ya]>"; 
		print "<td align=center><input type=text style=text-align:right size=5 name=xb[$x] value=$row[xb]>";
		print "<td align=center><input type=text style=text-align:right size=5 name=yb[$x] value=$row[yb]>";
		print "<td align=center><input type=text style=text-align:right size=5 name=str[$x] value=$row[str]>";
		print "<td align=center><input type=text style=text-align:right size=5 name=color[$x] value=$row[color]>";
		print "</tr>";
	}	 
} elseif ($art_nr==2) {
	if (substr($formular,0,1)>=6) {
		$gebyr='';$rentevnr='';
		$r=db_fetch_array(db_select("select xb,yb,str from formularer where beskrivelse ='GEBYR' and formular='$form_nr' and art='$art_nr' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__));
		$gebyr=$r['xb']*1;$rentevnr=$r['yb']*1;$rentesats=dkdecimal($r['str']);
		$r=db_fetch_array(db_select("select varenr from varer where id ='$gebyr'",__FILE__ . " linje " . __LINE__));
		$gebyr=$r['varenr'];
		print "<tr><td colspan=11 align=center>Varenummer for rykkergebyr <input type=text size=15 name=gebyr value=$gebyr></td></tr>";
		$r=db_fetch_array(db_select("select varenr from varer where id ='$rentevnr'",__FILE__ . " linje " . __LINE__)); 
		$rentevnr=$r['varenr'];
		print "<tr><td colspan=11 align=center>Varenummer /sats for rente <input type=text size=15 name=rentevnr value=$rentevnr><input type=text size=1 name=rentesats value=$rentesats></td></tr>";
		print "<tr><td colspan=11><hr></td></tr>";
	}
	 
	print "<tr><td></td><td align=center>Tekst</td>";
	print "<td align=center>X</td><td align=center> Y</td>";
	print "<td align=center>H&oslash;jde</td><td align=center> Farve</td>";
	$span="Justering - H: H&oslash;jrestillet\n C: Centreret\n V: Venstrestillet";
	print "<td align=center><span title = \"$span\">Just.</span></td><td align=center>Font</td>";
	$span="1: Kun side 1\n!1: Alle foruden side 1\nS: Sidste side\n!S: Alle foruden sidste side\nA: Alle sider";	
	print "<td align=center><span title = \"$span\">Side</span></td>";
	print "<td align=center>Fed</td><td align=center>&nbsp;Kursiv</td>";
	#		print "<td align=center>Understr.</td></tr>";

	$x=0;
	print "<tr>";
	print "<td><SELECT NAME=ny_beskrivelse[$x]>";
	print "<option></option>";
	print "<option>eget_firmanavn</option>";
	print "<option>egen_addr1</option>";
	print "<option>egen_addr2</option>";
	print "<option>eget_postnr</option>";
	print "<option>eget_bynavn</option>";
	print "<option>eget_land</option>";
	print "<option>eget_cvrnr</option>";
	print "<option>egen_tlf</option>";
	print "<option>egen_fax</option>";
	print "<option>egen_bank_navn</option>";
	print "<option>egen_bank_reg</option>";
	print "<option>egen_bank_konto</option>";
	print "<option>egen_email</option>";
	print "<option>egen_web</option>";
	if (substr($formular,0,1)<6) {
		print "<option>ansat_initialer</option>";
		print "<option>ansat_navn</option>";
		print "<option>ansat_addr1</option>";
		print "<option>ansat_addr2</option>";
		print "<option>ansat_postnr</option>";
		print "<option>ansat_by</option>";
		print "<option>ansat_email</option>";
		print "<option>ansat_mobil</option>";
		print "<option>ansat_tlf</option>";
		print "<option>ansat_fax</option>";
		print "<option>ansat_privattlf</option>";
		print "<option>ordre_firmanavn</option>";
		print "<option>ordre_addr1</option>";
		print "<option>ordre_addr2</option>";
		print "<option>ordre_postnr</option>";
		print "<option>ordre_bynavn</option>";
		print "<option>ordre_land</option>";
		print "<option>ordre_kontakt</option>";
		print "<option>ordre_cvrnr</option>";
		print "<option>ordre_betalingsbet</option>";
		print "<option>ordre_betalingsdage</option>";
		print "<option>ordre_ordredate</option>";
		print "<option>ordre_levdate</option>";
		print "<option>ordre_notes</option>";
		print "<option>ordre_ordrenr</option>";
		print "<option>ordre_momssats</option>";
		print "<option>ordre_kundeordnr</option>";
		print "<option>ordre_projekt</option>";
		print "<option>ordre_lev_navn</option>";
		print "<option>ordre_lev_addr1</option>";
		print "<option>ordre_lev_addr2</option>";
		print "<option>ordre_lev_postnr</option>";
		print "<option>ordre_lev_bynavn</option>";
		print "<option>ordre_lev_kontakt</option>";
		print "<option>ordre_ean</option>";
		print "<option>ordre_institution</option>";
		print "<option>ordre_lev_kontakt</option>";
	}	
	if (substr($formular,0,1)==4) {
		print "<option>ordre_fakturanr</option>";
		print "<option>ordre_fakturadate</option>";
	}	
	print "<option>formular_side</option>";
	print "<option>formular_nextside</option>";
	print "<option>formular_preside</option>";
	print "<option>formular_transportsum</option>";
	if (substr($formular,0,1)>6) {
		print "<option>formular_moms</option>";
		print "<option>formular_momsgrundlag</option>";
	}
	print "<option>formular_ialt</option>";
	if (substr($formular,0,1)==3) {
		print "<option>levering_lev_nr</option>";
		print "<option>levering_salgsdate</option>";
	} 
	if (substr($formular,0,1)>=6) {
		print "<option>ordre_firmanavn</option>";
		print "<option>ordre_addr1</option>";
		print "<option>ordre_addr2</option>";
		print "<option>ordre_postnr</option>";
		print "<option>ordre_bynavn</option>";
		print "<option>ordre_land</option>";
		print "<option>ordre_kontakt</option>";
		print "<option>ordre_cvrnr</option>";
		print "<option>forfalden_sum</option>";
		print "<option>rykker_gebyr</option>";
	}	
	print "</SELECT></td>";
	print "<td align=center><input type=text size=25 name=beskrivelse[$x]></td>";
	print "<td align=center><input type=text style=text-align:right size=5 name=xa[$x]></td>";
	print "<td align=center><input type=text style=text-align:right size=5 name=ya[$x]></td>";
	print "<td align=center><input type=text style=text-align:right size=5 name=str[$x]></td>";
	print "<td align=center><input type=text style=text-align:right size=5 name=color[$x]></td>";
	print "<td><SELECT NAME=placering[$x]>";
	print "<option>V</option>";
	print "<option>C</option>";
	print "<option>H</option>";
	print "</SELECT></td>";
	print "<td><SELECT NAME=form_font[$x]>";
	print "<option>Helvetica</option>";
	#	 print "<option>Courier</option>";
	#	 print "<option>Bookman</option>";
	print "<option>Times</option>";
	print "</SELECT></td>";
	print "<td><SELECT NAME=side[$x]>";
	print "<option>A</option>";
	print "<option>1</option>";
	print "<option>!1</option>";
	print "<option>S</option>";
	print "<option>!S</option>";
	print "</SELECT></td>";
	print "<td align=center><input type=checkbox name=fed[$x]></td>";
	print "<td align=center><input type=checkbox name=kursiv[$x]></td>";
	#	print "<td align=center><input type=checkbox name=understr[$x]>";
	print "</tr>";
 
	# echo "select * from formularer where formular = $form_nr and art = $art_nr<br>";
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse != 'GEBYR' and sprog='$formularsprog' order by ya desc, xa",__FILE__ . " linje " . __LINE__);
	while ($row=db_fetch_array($query)) {
		$x++;
		print "<tr>";
		print "<input type=hidden name=id[$x] value=$row[id]>";
		print "<td><SELECT NAME=ny_beskrivelse[$x]>";
		print "<option></option>";
		print "<option>eget_firmanavn</option>";
		print "<option>egen_addr1</option>";
		print "<option>egen_addr2</option>";
		print "<option>eget_postnr</option>";
		print "<option>eget_bynavn</option>";
		print "<option>eget_land</option>";
		print "<option>eget_cvrnr</option>";
		print "<option>egen_tlf</option>";
		print "<option>egen_fax</option>";
		print "<option>egen_bank_navn</option>";
		print "<option>egen_bank_reg</option>";
		print "<option>egen_bank_konto</option>";
		print "<option>egen_email</option>";
		print "<option>egen_web</option>";
		if (substr($formular,0,1)!=6) {
			print "<option>ansat_initialer</option>";
			print "<option>ansat_navn</option>";
			print "<option>ansat_addr1</option>";
			print "<option>ansat_addr2</option>";
			print "<option>ansat_postnr</option>";
			print "<option>ansat_by</option>";
			print "<option>ansat_email</option>";
			print "<option>ansat_mobil</option>";
			print "<option>ansat_tlf</option>";
			print "<option>ansat_fax</option>";
			print "<option>ansat_privattlf</option>";
		}	
		print "<option>ordre_firmanavn</option>";
		print "<option>ordre_addr1</option>";
		print "<option>ordre_addr2</option>";
		print "<option>ordre_postnr</option>";
		print "<option>ordre_bynavn</option>";
		print "<option>ordre_land</option>";
		print "<option>ordre_kontakt</option>";
		print "<option>ordre_cvrnr</option>";
		if (substr($formular,0,1)!=6) {
			print "<option>ordre_ordredate</option>";
			print "<option>ordre_levdate</option>";
			print "<option>ordre_notes</option>";
			print "<option>ordre_ordrenr</option>";
			print "<option>ordre_momssats</option>";
			print "<option>ordre_kundeordnr</option>";
			print "<option>ordre_projekt</option>";
			print "<option>ordre_lev_navn</option>";
			print "<option>ordre_lev_addr1</option>";
			print "<option>ordre_lev_addr2</option>";
			print "<option>ordre_lev_postnr</option>";
			print "<option>ordre_lev_bynavn</option>";
			print "<option>ordre_lev_kontakt</option>";
			print "<option>ordre_ean</option>";
			print "<option>ordre_institution</option>";
			print "<option>ordre_lev_kontakt</option>";
		}	
		if (substr($formular,0,1)==4) {
			print "<option>ordre_fakturanr</option>";
			print "<option>ordre_fakturadate</option>";
		}	
		print "<option>formular_side</option>";
		print "<option>formular_nextside</option>";
		print "<option>formular_preside</option>";
		print "<option>formular_transportsum</option>";
		if (substr($formular,0,1)!=6) {
			print "<option>formular_moms</option>";
			print "<option>formular_momsgrundlag</option>";
		}
		print "<option>formular_ialt</option>";
		if (substr($formular,0,1)==3) {
			print "<option>levering_lev_nr</option>";
			print "<option>levering_salgsdate</option>";
		} 
		if (substr($formular,0,1)==6) {
			print "<option>forfalden_sum</option>";
			print "<option>rykker_gebyr</option>";
		}	
		print "</SELECT></td>";
		print "<td align=center><input type=text size=25 name=beskrivelse[$x] value=\"$row[beskrivelse]\"></td>";
		print "<td align=center><input type=text style=text-align:right size=5 name=xa[$x] value=$row[xa]></td>";
		print "<td align=center><input type=text style=text-align:right size=5 name=ya[$x] value=$row[ya]></td>";
		print "<td align=center><input type=text style=text-align:right size=5 name=str[$x] value=$row[str]></td>";
		print "<td align=center><input type=text style=text-align:right size=5 name=color[$x] value=$row[color]></td>";
		print "<td><SELECT NAME=placering[$x]>";
		print "<option>$row[placering]</option>";
		print "<option>V</option>";
		print "<option>C</option>";
		print "<option>H</option>";
		print "</SELECT></td>";
		print "<td><SELECT NAME=form_font[$x]>";
		print "<option>$row[font]</option>";
		print "<option>Helvetica</option>";
		#			print "<option>Courier</option>";
		#			print "<option>Bookman</option>";
		print "<option>Times</option>";
		print "<option>Ocrbb12</option>";
		 print "</SELECT></td>";
		print "<td><SELECT NAME=side[$x]>";
		print "<option>$row[side]</option>";
		print "<option>A</option>";
		print "<option>1</option>";
		print "<option>!1</option>";
		print "<option>S</option>";
		print "<option>!S</option>";
		print "</SELECT></td>";
		if ($row['fed']=='on') {$row['fed']='checked';}
		print "<td align=center><input type=checkbox name=fed[$x] $row[fed]></td>";
		if ($row['kursiv']=='on') {$row['kursiv']='checked';}
		print "<td align=center><input type=checkbox name=kursiv[$x] $row[kursiv]></td>";
		print "</tr>";
	}	 
} elseif ($art_nr==3) {
	$x=1;
	print "<tr><td></td><td></td><td align=cente>Linjeantal</td>";
	print "<td align=center>Y</td>";
	print "<td align=center>Linafs.</td></tr>";
	#		print "<td align=center>Understr.</td></tr>";
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse = 'generelt' and sprog='$formularsprog' order by xa",__FILE__ . " linje " . __LINE__);
	if (!$row=db_fetch_array($query)) {
		$query=db_modify ("insert into formularer (formular, art, beskrivelse, xa, ya, xb) values ($form_nr, $art_nr, 'generelt', 34, 185, 4)",__FILE__ . " linje " . __LINE__);
		$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse = 'generelt' and sprog='$formularsprog' order by xa",__FILE__ . " linje " . __LINE__);
		$row=db_fetch_array($query);
	}
	print "<tr><td></td><td></td>";
	print "<input type=hidden name=id[$x] value=$row[id]>";
	print "<input type=hidden name=beskrivelse[$x] value=$row[beskrivelse]>";
	print "<td align=center><input type=text style=text-align:right size=5 name=xa[$x] value=$row[xa]></td>";
	print "<td align=center><input type=text style=text-align:right size=5 name=ya[$x] value=$row[ya]></td>";
	print "<td align=center><input type=text style=text-align:right size=3 name=xb[$x] value=$row[xb]></td></tr>";
	print "<tr><td>Beskrivelse</td>";
	print "<td align=center>X</td>";
	print "<td align=center>H&oslash;jde</td><td align=center> Farve</td>";
	print "<td align=center>Plac.</td><td align=center>Font</td><td align=center> Fed</td>";
	print "<td align=center> Kursiv</td><td align=center> Tekstl&aelig;ngde</td></tr>";
	#		print "<td align=center>Understr.</td></tr>";

	$x=0;
	print "<tr>";
	print "<td><SELECT NAME=beskrivelse[$x]>";
	if (substr($formular,0,1)<6) {
		print "<option>posnr</option>";
		print "<option>varenr</option>";
		print "<option>antal</option>";
		print "<option>beskrivelse</option>";
		print "<option>pris</option>";
		print "<option>rabat</option>";
		print "<option>linjesum</option>";
		if (substr($formular,0,1)==3) {
			print "<option>lev_tidl_lev</option>";
			print "<option>lev_antal</option>";
			print "<option>lev_rest</option>";
		} 
	} else {
		print "<option>dato</option>";
		print "<option>faktnr</option>";
		print "<option>beskrivelse</option>";
		print "<option>bel&oslash;b</option>";
	}
	print "</SELECT></td>";
		#		print "<td align=center><input type=text style=text-align:right size=5 name=xa[$x]></td>";
	print "<td align=center><input type=text style=text-align:right size=5 name=xa[$x]></td>";
	print "<td align=center><input type=text style=text-align:right size=5 name=str[$x]></td>";
	print "<td align=center><input type=text style=text-align:right size=5 name=color[$x]></td>";
	print "<td><SELECT NAME=placering[$x]>";
	print "<option>V</option>";
	print "<option>C</option>";
	print "<option>H</option>";
	print "</SELECT></td>";
	print "<td><SELECT NAME=form_font[$x]>";
	print "<option>Helvetica</option>";
	#	 print "<option>Courier</option>";
	#	 print "<option>Bookman</option>";
	print "<option>Times</option>";
	print "</SELECT></td>";
	print "<td align=center><input type=checkbox name=fed[$x]></td>";
	print "<td align=center><input type=checkbox name=kursiv[$x]></td>";
	#	print "<td align=center><input type=checkbox name=understr[$x]>";
	print "</tr>";
		
	# echo "select * from formularer where formular = $form_nr and art = $art_nr<br>";
	$x=1;
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse != 'generelt' and sprog='$formularsprog' order by xa",__FILE__ . " linje " . __LINE__);
	while ($row=db_fetch_array($query)){
		$x++;
		print "<tr>";
		print "<input type=hidden name=id[$x] value=$row[id]>";
		print "<td><SELECT NAME=beskrivelse[$x]>";
		print "<option>$row[beskrivelse]</option>";
		if (substr($formular,0,1)<6) {
			print "<option>posnr</option>";
			print "<option>varenr</option>";
			print "<option>antal</option>";
			print "<option>beskrivelse</option>";
			print "<option>pris</option>";
			print "<option>rabat</option>";
			print "<option>linjesum</option>";
			if (substr($formular,0,1)==3) {
				print "<option>lev_tidl_lev</option>";
				print "<option>lev_antal</option>";
				print "<option>lev_rest</option>";
	 		} 
		}
		else {
			print "<option>dato</option>";
			print "<option>faktnr</option>";
			print "<option>beskrivelse</option>";
			print "<option>bel&oslash;b</option>";
		}
		print "</SELECT></td>";
		print "<td align=center><input type=text style=text-align:right size=5 name=xa[$x] value=$row[xa]></td>";
		print "<td align=center><input type=text style=text-align:right size=5 name=str[$x] value=$row[str]></td>";
		print "<td align=center><input type=text style=text-align:right size=5 name=color[$x] value=$row[color]></td>";
		print "<td><SELECT NAME=placering[$x]>";
		print "<option>$row[placering]</option>";
		print "<option>V</option>";
		print "<option>C</option>";
		print "<option>H</option>";
		print "</SELECT></td>";
		print "<td><SELECT NAME=form_font[$x]>";
		print "<option>$row[font]</option>";
		print "<option>Helvetica</option>";
		print "<option>Times</option>";
		print "</SELECT></td>";
		if ($row[fed]=='on') {$row[fed]='checked';}
		print "<td align=center><input type=checkbox name=fed[$x] $row[fed]></td>";
		if ($row[kursiv]=='on') {$row[kursiv]='checked';}
		print "<td align=center><input type=checkbox name=kursiv[$x] $row[kursiv]></td>";
		if ($row[beskrivelse]=='beskrivelse'){print "<td align=center><input type=text style=text-align:right size=5 name=xb[$x] value=$row[xb]></td>";}
		print "</tr>";
	}	 
} elseif ($art_nr==4) {
	print "<tr><td><br></td></tr><tr><td><br></td></tr>";
	print "<tr><td colspan=2 align=center>Her har du mulighed for at flytte centreringen p&aring; formularen</td></tr>";
	print "<tr><td colspan=2 align=center>Angiv blot det antal mm. der skal flyttes hhv. op og til h&oslash;jre</td></tr>";
	print "<tr><td colspan=2 align=center>Anvend negativt fortegn, hvis der skal rykkes ned eller til venstre</td></tr>";
	print "<tr><td colspan=2 align=center></td></tr>";
	print "<tr><td align=center>Op</td><td><input type=text style=text-align:right size=2 name=op></td><tr>";
	print "<tr><td align=center>H&oslash;jre</td><td><input type=text style=text-align:right size=2 name=hojre></td><tr>";
} elseif ($art_nr==5 && $formular!=3) {
	print "<tr><td><br></td></tr><tr><td align=center colspan=2>".findtekst(215,$sprog_id)."</td></tr><tr><td><br></td></tr>";
	$q=db_select("select * from formularer where formular = '$form_nr' and art = '$art_nr' and sprog='$formularsprog' order by xa,id",__FILE__ . " linje " . __LINE__);
	for ($x=1;$x<=2;$x++) {
		$r=db_fetch_array($q);
		if ($r['xa']==1) $subjekt=$r['beskrivelse'];	
		elseif ($r['xa']==2) $mailtext=str_replace("<br>","\n",$r['beskrivelse']);
		print "<input type=hidden name='id[$x]' value='$r[id]'>";
		print "<input type=hidden name='xa[$x]' value='$x'>";
		print "<input type=hidden name='form_nr[$x]' value='$form_nr'>";
		print "<input type=hidden name='art' value='$art'>";
		print "<input type=hidden name='sprog' value='$formularsprog'>";
	}
	print "<tr><td title=\"".findtekst(217,$sprog_id)."\">".findtekst(216,$sprog_id)."&nbsp;</td><td title=\"".findtekst(217,$sprog_id)."\"><input type=\"text\" size=\"40\" name=\"beskrivelse[1]\" value = \"$subjekt\"></td></tr>";
	print "<tr><td title=\"".findtekst(219,$sprog_id)."\" valign=\"top\">".findtekst(218,$sprog_id)."&nbsp;</td><td colspan=4  title=\"".findtekst(219,$sprog_id)."\"><textarea name=\"beskrivelse[2]\" rows=\"5\" cols=\"100\" onchange=\"javascript:docChange = true;\">$mailtext</textarea></td></tr>\n";
}
print "<input type=hidden name=linjeantal value=$x>";
print "<tr><td colspan=10 align=center><hr></td></tr>";
if ($formular && $art) print "<td colspan=10 align=center><input type=submit accesskey=\"v\" value=\"Opdat&eacute;r\" name=\"linjer\"></td></tr>";
print "</tbody></table></td></tr></form>";

function sprog($nyt_sprog, $skabelon){

if ($nyt_sprog && $nyt_sprog!="yes") {
	$tmp=strtolower($nyt_sprog);
	$q=db_select("select distinct sprog from formularer where lower(sprog) = '$tmp'",__FILE__ . " linje " . __LINE__);
	if ($r=db_fetch_array($q)) print "<BODY onLoad=\"javascript:alert('$nyt_sprog er allerede oprettet. Oprettelse annulleret')\">";
	elseif ($skabelon) {
		$q=db_select("select * from formularer where sprog = '$skabelon'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$xa=$r['xa']*1; $ya=$r['ya']*1; $xb=$r['xb']*1; $yb=$r['yb']*1;$str=$r['str']*1;$color=$r['color']*1;
			db_modify("insert into formularer(formular,art,beskrivelse,placering,xa,ya,xb,yb,str,color,font,fed,kursiv,side,sprog) values	('$r[formular]','$r[art]','$r[beskrivelse]','$r[placering]','$xa','$ya','$xb','$yb','$str','$color','$r[font]','$r[fed]','$r[kursiv]','$r[side]','$nyt_sprog')",__FILE__ . " linje " . __LINE__);
		}
		print "<BODY onLoad=\"javascript:alert('$nyt_sprog er oprettet.')\">";
	}
} else {
	print "<form name=formularvalg action=$_SERVER[PHP_SELF] method=\"post\">";
	print "<tr><td width=100% align=center><table border=1><tbody>";
	print "<tr><td>Skriv sprog der &oslash;nskes tilf&oslash;jet: </td><td><input type=tekst name=nyt_sprog size=15<td></tr>";
	print "<tr><td>V&aelig;lg formularskabelon</td>";
	print "<td><select NAME=skabelon>";
	$q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) print "<option>$r[sprog]</option>";
	print "<option></option>";
	print "</SELECT></td><tr>";
	print "<tr><td colspan=2 align=center><input type=submit accesskey=\"g\" value=\"gem\" name=\"sprog\">&nbsp;<input type=submit accesskey=\"a\" value=\"fortryd\"></td></tr>";
	print "</tbody></table></td></tr>";
	exit;
}	

} # endfunc sprog



?>

<tr><td	width="100%" height="2.5%" align = "center" valign = "bottom">		
		<table width="100%" align="center" border="0" cellspacing="2" cellpadding="0"><tbody>
			<td width="10%" align=center <?php echo $top_bund ?>><br></td>
			<td width="35%" align=center <?php echo $top_bund ?>><br></td>
			<td width="10%" <?php echo $top_bund ?>" onClick="javascript:window.open('formularimport.php', '','left=10,top=10,width=400,height=200,scrollbars=yes,resizable=yes,menubar=no,location=no')" onMouseOver="this.style.cursor = 'pointer'" ><font face="Helvetica, Arial, sans-serif" color="#000000">
			<u>Formularimport</u></td>
			<td width="35%" <?php echo $top_bund ?>><br></td>
 			<td width="10%" <?php echo $top_bund ?> onClick="javascript:window.open('logoslet.php', '','left=10,top=10,width=400,height=200,scrollbars=yes,resizable=yes,menubar=no,location=no')" onMouseOver="this.style.cursor = 'pointer'" ><font face="Helvetica, Arial, sans-serif" color="#000000"><u>Slet logo</u></td>
		</tbody></table>
</td></tr>
</tbody></table>
</body></html>
