<?php
// --------------------------------------systemdata/formularkort -------lap 1.1.0-------------------------
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
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();

$title="Formulareditor";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
	
$id=$db_id;
	
if ($_GET['upload']) {
	upload($id, $font);
	exit;
}
if ($_GET['nyt_sprog']) {
	$nyt_sprog=$_GET['nyt_sprog'];
#	exit;
}
if ($_GET['id']) {$id = $_GET['id'];}
if($_GET['returside']) {
	$returside= $_GET['returside'];
#	$ordre_id = $_GET['ordre_id'];
#	$fokus = $_GET['fokus'];
}
else {$returside="syssetup.php";}
$navn=$_GET['navn'];

if ($_POST) {
	$nyt_sprog=$_POST['nyt_sprog'];
	$skabelon=$_POST['skabelon'];
	$submit=$_POST['linjer'];
	$formular=$_POST['formular'];
	$sprog=$_POST['sprog'];
#	if ($formular==6) {tjek_rykker();}
	$beskrivelse=$_POST['beskrivelse'];
	$ny_beskrivelse=$_POST['ny_beskrivelse'];
	$art=$_POST['art'];
	$id=$_POST['id'];
	$xa=$_POST['xa'];
	$ya=$_POST['ya'];
	$xb=$_POST['xb'];
	$yb=$_POST['yb'];
	$str=$_POST['str'];
	$color=$_POST['color'];
	$form_font=$_POST['form_font'];
	$fed=$_POST['fed'];
	$placering=$_POST['placering'];
	$kursiv=$_POST['kursiv'];
	$side=$_POST['side'];
	$linjeantal=$_POST['linjeantal'];
	$gebyr=$_POST['gebyr'];
	
	
	list($art_nr, $art_tekst)=split(":", $art);
	list($form_nr, $form_tekst)=split(":", $formular);
	
	if (($_POST['op']) || ($_POST['hojre'])) {
		$op=$_POST['op']*1; $hojre=$_POST['hojre']*1;
		$query=db_select("select id, xa, xb, ya, yb from formularer where formular=$form_nr and sprog='$sprog'");
		while ($row=db_fetch_array($query)){
			db_modify("update formularer set xa=$row[xa]+$hojre, ya=$row[ya]+$op where id=$row[id]");
			if ($row[yb]) db_modify("update formularer set xb=$row[xb]+$hojre, yb=$row[yb]+$op where id=$row[id]");
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
	
	if (($submit=='Opdater')&&($form_nr==6)&&($art_nr==2)&&($gebyr)) {
		$gebyr=$_POST['gebyr'];
		$r1=db_fetch_array(db_select("select id from varer where varenr = '$gebyr'"));
		if ($r1=db_fetch_array(db_select("select id from varer where varenr = '$gebyr'"))) { 
			if ($r2=db_fetch_array(db_select("select id from formularer where beskrivelse ='GEBYR' and formular=6 and art=2 and sprog='$sprog'"))) db_modify("update formularer set xb='$r1[id]' where id = $r2[id]");
			else db_modify("insert into formularer (beskrivelse, formular, art, xb) values ('GEBYR', 6, 2, $r1[id])");
		} else print "<BODY onLoad=\"javascript:alert('Varenummer $gebyr findes ikke i varelisten')\">";
	} elseif (($submit=='Opdater')&&($form_nr==6)&&($art_nr==2)&&(!$gebyr)) db_modify("delete from formularer where beskrivelse = 'GEBYR' and sprog='$sprog'");
	
	if ($_POST['linjer']){
		transaktion('begin');	
		for ($x=0; $x<=$linjeantal; $x++) {
			if ((trim($xa[$x])=='-')&&($id[$x])&&($beskrivelse[$x]!='LOGO')) {db_modify("delete from formularer where id =$id[$x] and sprog='$sprog'");}
			else {
				if ($ny_beskrivelse[$x]) {$beskrivelse[$x]=trim($beskrivelse[$x]." $".$ny_beskrivelse[$x].";");}
				$xa[$x]=$xa[$x]*1; $ya[$x]=$ya[$x]*1; $xb[$x]=$xb[$x]*1; $yb[$x]=$yb[$x]*1; $str[$x]=$str[$x]*1; $color[$x]=$color[$x]*1;
				if ($x==0){
					if ($xa[$x]>0) {
						if (($art!='1') && ($str[$x]<=1)) $str[$x]=10;
						db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, placering) values ('$beskrivelse[$x]', $form_nr, $art_nr, $xa[$x], $ya[$x], $xb[$x], $yb[$x], $str[$x], $color[$x], '$form_font[$x]', '$fed[$x]', '$kursiv[$x]', '$side[$x]', '$placering[$x]')");
					}
				}
				elseif ($id[$x]) db_modify("update formularer set beskrivelse='$beskrivelse[$x]', xa=$xa[$x], ya=$ya[$x], xb=$xb[$x], yb=$yb[$x], str=$str[$x], color=$color[$x], font='$form_font[$x]', fed='$fed[$x]', kursiv='$kursiv[$x]', side='$side[$x]', placering='$placering[$x]'	where id = $id[$x]");
			} 
		}
	}
	transaktion('commit');	 
}
#}


print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td width=100% height=1% align=\"center\" valign=\"top\" collspan=2>";
print "<table width=\"100%\" height=\"1%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=$returside accesskey=\"l\">Luk</a></small></td>";
print "<td width=\"80%\" $top_bund align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Formularkort</small></td>";
print "<td width=\"5%\" $top_bund align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><span title=\"Opret eller nedl&aelig;g sprog\"><a href=formularkort.php?nyt_sprog=yes accesskey=\"s\">Sprog</a></span></small></td>";
print "<td width=\"5%\" $top_bund align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><span title=\"Indl&aelig;s eller fjern logo\"><a href=logoupload.php?upload=yes accesskey=\"u\">Logo</a></span></small></td>";
print "</tbody></table></td></tr>";
if ($nyt_sprog) sprog($nyt_sprog, $skabelon);
print "<tr><td align=center width=100%><table align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";

print "<tr><td colspan=10 align=center><table><tbody>";
print "<form name=formularvalg action=$_SERVER[PHP_SELF] method=\"post\">";
print "<tr><td> $font<small> Formular</td>";
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
print "<td> $font<small> Art</td>";
print "<td><SELECT NAME=art>";
if ($formular) print "<option>$art</option>";
print "<option>1:Linjer</option>";
print "<option>2:Tekster</option>";
print "<option>3:Ordrelinjer</option>";
print "<option>4:Flyt center</option>";
print "</SELECT></td>";
print "<td> $font<small> Sprog</td>";
print "<td><SELECT NAME=sprog>";
if (!trim($sprog)) $sprog="Dansk";
print "<option>$sprog</option>";
$q=db_select("select distinct sprog from formularer order by sprog");
while ($r=db_fetch_array($q)) {
	if ($sprog!=$r['sprog']) print "<option>$r[sprog]</option>";
}
	print "</SELECT></td>";
print "<td><input type=submit accesskey=\"v\" value=\"V&aelig;lg\" name=\"formularvalg\"></td></tr>";
print "</tbody></table></td></tr>";
#if ($form_nr) {

print "<form name=linjer action=$_SERVER[PHP_SELF] method=\"post\">";

if ($art_nr==1) {
	print "<tr><td><br></td></tr>";
	print "<tr><td colspan=10 align=center>$font LOGO</td></tr>";
	print "<tr><td><br></td></tr>";
		
	print "<tr><td></td><td></td><td align=center>$font<small>X</td><td align=center>$font<small> Y</td></tr>";
	$x=1;
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse ='LOGO' and sprog = '$sprog'");
	$row=db_fetch_array($query);
	print "<tr>";
	print "<input type=hidden name=id[$x] value=$row[id]><input type=hidden name=beskrivelse[$x] value='LOGO'>";
	print "<td colspan=2></td><td align=center><input type=text style=text-align:right size=5 name=xa[$x] value=$row[xa]>";
	print "<td align=center><input type=text style=text-align:right size=5 name=ya[$x] value=$row[ya]>";

	print "<tr><td><br></td></tr>";
	print "<tr><td colspan=6 align=center>$font Linjer</td></tr>";
	print "<tr><td><br></td></tr>";

	print "<tr><td colspan=2 align=center>$font<small> Start</td>";
	print "<td colspan=2 align=center>$font<small> Slut</td></tr>";
	print "<tr><td align=center>$font<small>X</td><td align=center>$font<small> Y</td><td align=center>$font<small> X</td><td align=center>$font<small> Y</td>";
	print "<td align=center>$font<small> Bredde</td><td align=center>$font<small> Farve</td></tr>";

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
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse !='LOGO' and sprog='$sprog'");
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
} 
elseif ($art_nr==2) {
	if (substr($formular,0,1)>=6) {
		if ($r=db_fetch_array(db_select("select varenr from varer where id IN(select xb from formularer where beskrivelse ='GEBYR' and formular='$form_nr' and art='$art_nr' and sprog='$sprog')"))) $gebyr=$r['varenr'];
		print "<tr><td colspan=11 align=center>$font<small>Varenummer for rykkergebyr <input type=text size=15 name=gebyr value=$gebyr></td></tr>";
		print "<tr><td colspan=11><hr></td></tr>";
	}
	 
	print "<tr><td></td><td align=center>$font<small>Tekst</td>";
	print "<td align=center>$font<small>X</td><td align=center>$font<small> Y</td>";
	print "<td align=center>$font<small>H&oslash;jde</td><td align=center>$font<small> Farve</td>";
	$span="H: Højrestillet\n C: Centreret\n V: Venstrestillet";
	print "<td align=center><span title = \"$span\">$font<small>Plac.</small></span></td><td align=center>$font<small>Font</small></td>";
	$span="1: Kun side 1\n!1: Alle foruden side 1\nS: Sidste side\n!S: Alle foruden sidste side\nA: Alle sider";	
	print "<td align=center><span title = \"$span\">$font<small>Side</small></span></td>";
	print "<td align=center>$font<small>Fed</td><td align=center>$font<small>&nbsp;Kursiv</td>";
	#		print "<td align=center>$font<small>Understr.</td></tr>";

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
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse != 'GEBYR' and sprog='$sprog' order by ya desc, xa");
	while ($row=db_fetch_array($query))
	{
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
		print "<td align=center><input type=text size=25 name=beskrivelse[$x] value='$row[beskrivelse]'></td>";
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
		if ($row[fed]=='on') {$row[fed]='checked';}
		print "<td align=center><input type=checkbox name=fed[$x] $row[fed]></td>";
		if ($row[kursiv]=='on') {$row[kursiv]='checked';}
		print "<td align=center><input type=checkbox name=kursiv[$x] $row[kursiv]></td>";
		print "</tr>";
	}	 
}

elseif ($art_nr==3) {
	$x=1;
	print "<tr><td></td><td></td><td align=cente>$font<small>Linjeantal</td>";
	print "<td align=center>$font<small>Y</td>";
	print "<td align=center>$font<small>Linafs.</td></tr>";
	#		print "<td align=center>$font<small>Understr.</td></tr>";
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse = 'generelt' and sprog='$sprog' order by xa");
	if (!$row=db_fetch_array($query)) {
		$query=db_modify ("insert into formularer (formular, art, beskrivelse, xa, ya, xb) values ($form_nr, $art_nr, 'generelt', 34, 185, 4)");
		$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse = 'generelt' and sprog='$sprog' order by xa");
		$row=db_fetch_array($query);
	}
	print "<tr><td></td><td></td>";
	print "<input type=hidden name=id[$x] value=$row[id]>";
	print "<input type=hidden name=beskrivelse[$x] value=$row[beskrivelse]>";
	print "<td align=center><input type=text style=text-align:right size=5 name=xa[$x] value=$row[xa]></td>";
	print "<td align=center><input type=text style=text-align:right size=5 name=ya[$x] value=$row[ya]></td>";
	print "<td align=center><input type=text style=text-align:right size=3 name=xb[$x] value=$row[xb]></td></tr>";
	print "<tr><td>$font<small>Beskrivelse</td>";
	print "<td align=center>$font<small>X</td>";
	print "<td align=center>$font<small>H&oslash;jde</td><td align=center>$font<small> Farve</td>";
	print "<td align=center>$font<small>Plac.</td><td align=center>$font<small>Font</td><td align=center>$font<small> Fed</td>";
	print "<td align=center>$font<small> Kursiv</td><td align=center>$font<small> Tekstl&aelig;ngde</td></tr>";
	#		print "<td align=center>$font<small>Understr.</td></tr>";

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
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse != 'generelt' and sprog='$sprog' order by xa");
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
}
elseif ($art_nr==4) {
	print "<tr><td><br></td></tr><tr><td><br></td></tr>";
	print "<tr><td colspan=2 align=center>$font<small>Her har du mulighed for at flytte centreringen p&aring; formularen</td></tr>";
	print "<tr><td colspan=2 align=center>$font<small>Angiv blot det antal mm. der skal flyttes hhv. op og til h&oslash;jre</td></tr>";
	print "<tr><td colspan=2 align=center>$font<small>Anvend negativt fortegn, hvis der skal rykkes ned eller til venstre</td></tr>";
	print "<tr><td colspan=2 align=center>$font<small></td></tr>";
	print "<tr><td align=center>$font<small>Op</td><td><input type=text style=text-align:right size=2 name=op></td><tr>";
	print "<tr><td align=center>$font<small>Højre</td><td><input type=text style=text-align:right size=2 name=hojre></td><tr>";
}
print "<input type=hidden name=linjeantal value=$x>";
print "<tr><td colspan=10 align=center><hr></td></tr>";
print "<td colspan=10 align=center><input type=submit accesskey=\"v\" value=\"Opdater\" name=\"linjer\"></td></tr>";
print "</tbody></table></td></tr>";

function sprog($nyt_sprog, $skabelon){

if ($nyt_sprog && $nyt_sprog!="yes") {
	$tmp=strtolower($nyt_sprog);
	$q=db_select("select distinct sprog from formularer where lower(sprog) = '$tmp'");
	if ($r=db_fetch_array($q)) print "<BODY onLoad=\"javascript:alert('$nyt_sprog er allerede oprettet. Oprettelse annulleret')\">";
	elseif ($skabelon) {
		$q=db_select("select * from formularer where sprog = '$skabelon'");
		while ($r=db_fetch_array($q)) {
			$xa=$r['xa']*1; $ya=$r['ya']*1; $xb=$r['xb']*1; $yb=$r['yb']*1;$str=$r['str']*1;$color=$r['color']*1;
			db_modify("insert into formularer(formular,art,beskrivelse,placering,xa,ya,xb,yb,str,color,font,fed,kursiv,side,sprog) values	('$r[formular]','$r[art]','$r[beskrivelse]','$r[placering]','$xa','$ya','$xb','$yb','$str','$color','$r[font]','$r[fed]','$r[kursiv]','$r[side]','$nyt_sprog')");
		}
		print "<BODY onLoad=\"javascript:alert('$nyt_sprog er oprettet.')\">";
	}
} else {
	print "<form name=formularvalg action=$_SERVER[PHP_SELF] method=\"post\">";
	print "<tr><td width=100% align=center><table border=1><tbody>";
	print "<tr><td>Skriv sprog der &oslash;nskes tilf&oslash;jet: </td><td><input type=tekst name=nyt_sprog size=15<td></tr>";
	print "<tr><td>V&aelig;lg formularskabelon</td>";
	print "<td><select NAME=skabelon>";
	$q=db_select("select distinct sprog from formularer order by sprog");
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
			<td width="10%" <?php echo $top_bund ?>" onClick="javascript:window.open('formularimport.php', '','left=10,top=10,width=400,height=200,scrollbars=yes,resizable=yes,menubar=no,location=no')" onMouseOver="this.style.cursor = 'pointer'" ><font face="Helvetica, Arial, sans-serif" color="#000000"><small>
			<u>Formularimport</u></small></td>
			<td width="35%" <?php echo $top_bund ?>><br></td>
 			<td width="10%" <?php echo $top_bund ?> onClick="javascript:window.open('logoslet.php', '','left=10,top=10,width=400,height=200,scrollbars=yes,resizable=yes,menubar=no,location=no')" onMouseOver="this.style.cursor = 'pointer'" ><font face="Helvetica, Arial, sans-serif" color="#000000"><small><u>Slet logo</u></small></td>
		</tbody></table>
</td></tr>
</tbody></table>
</body></html>
