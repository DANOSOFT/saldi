<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/formularkort --- patch 4.0.8 --- 2023-10-03 ---
// 							LICENSE
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
// ----------------------------------------------------------------------------

// 20120906 Tilføjet mulighed for at vise momssats på ordrelinjer.
// 20130212 Tilføjet linjemoms og varemomssats, søg linjemoms eller varemomssats 
// 20130221 Tilføjet kontokort (formular 11)
// 20130815 Tilføjet Indkøbsforslag, Rekvisision & Købsfaktura (formular 12,13,14)
// 20131121 Mulighed for at tilføje navn på vedhæftet bilag i 'mail-tekst' for Tilbud,Ordrer og Fakture
// 20131121 Div. rettelser i html. Indsat meta i head, så ÆØÅ vises korekt. Ensrettet font i top og bund. Ændret 'Logo' til 'Upload', og fjernet 'Slet logo' i bunden
// 20131121 Nye tekster skrevet ind i tekster.csv (671,672) til bilag
// 20140124 #1 Tilføjet *1 for at sikre at værdi er numerisk Søg 20140124
// 20140709 PK - Indsat procent i ordrelinjer. Søg #20140709
// 20140902 Phr -indsat 'and formular='$form_nr'' da gebyr bleve slette i alle formularer ved gemning af formular uden gebyr.
// 20150117	Phr - Merget med version fra jan. 14 som var blevet overskrevet 2014.07.09.
// 20150331 CA  Topmenudesign tilføjet                             søg 20150331
// 20160111 PHR Tilføjet lev_varenr til ordrelinjer  søg 'lev_varenr'
// 20160804 PHR X & Y koordinater kan nu indeholde decimaler.
// 20171004 PHR Kopier alt - nu også på indkøbs...
// 20190221 MSC - Rettet topmenu design til
// 20190225 MSC - Rettet topmenu design til
// 20191106 PHR Added $formular_netWeight & $formular_grossWeight
// 20191222 PHR Added $konto_valuta  and changed 'adresser_' to 'konto_' in 'Kontoudtog'
// 20210211 PHR Some cleanup
// 20210628 LOE Translated some texts to English and Norsk
// 20220113 PHR Corrected variable name 'sprog'
// 20220113 PHR Corrected text selection from 977 & 978 to 877 & 578
// 20220213 PHR Various changes to fit php8
// 20230719 PHR Cleanup in 'mailtext
// 20230828 PHR Fixed error in above
// 20231003 PHR Added ordre_valuta

@session_start();
$s_id=session_id();

$title="Formulareditor";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
	
$art=$art_nr=$form_nr=$linjeantal=$nyt_sprog=$submit=$x=NULL;
$id=$db_id;
	
/*
if (isset($_GET['upload']) && $_GET['upload']) {
	upload($id);
	exit;
}
*/
if (isset($_GET['nyt_sprog']) && $_GET['nyt_sprog']) {
	$nyt_sprog=$_GET['nyt_sprog'];
}
$id = if_isset($_GET['id']);
if(isset($_GET['returside']) && $_GET['returside']) {
	$returside= $_GET['returside'];
#	$ordre_id = $_GET['ordre_id'];
#	$fokus = $_GET['fokus'];
}
else {$returside="syssetup.php";}
$navn=if_isset($_GET['navn']);

if (isset($_POST) && $_POST) {

	if ($nyt_sprog) {
		$nyt_sprog=if_isset($_POST['nyt_sprog']);
		$skabelon=if_isset($_POST['skabelon']);
		if(isset($_POST['gem']) && $_POST['gem']) $handling = 'gem' ;
		if (!$nyt_sprog) {
			if (!$handling) $handling=if_isset($_POST['slet']);
			if (!$handling) $handling=if_isset($_POST['fortryd']);
			if ($handling == 'slet') $nyt_sprog='slet';
		}
	}
	$formular=if_isset($_POST['formular']);
	$form_nr=if_isset($_POST['form_nr']);
	$formularsprog=db_escape_string(if_isset($_POST['sprog']));
	$art=if_isset($_POST['art']);
	
	if (isset($_POST['streger'])) {
		$submit=$_POST['streger'];
		if (strstr($submit, "Opdat")) $submit="Opdater";
		$beskrivelse=if_isset($_POST['beskrivelse']);
		$ny_beskrivelse=if_isset($_POST['ny_beskrivelse']);
		$id=if_isset($_POST['id']);
		$xa=if_isset($_POST['xa']);
		$ya=if_isset($_POST['ya']);
		$xb=if_isset($_POST['xb']);
		$yb=if_isset($_POST['yb']);
		$str=if_isset($_POST['str']);
		$color=if_isset($_POST['color']);
		$form_font=if_isset($_POST['form_font']);
		$fed=if_isset($_POST['fed']);
		$justering=if_isset($_POST['justering']);
		$kursiv=if_isset($_POST['kursiv']);
		$side=if_isset($_POST['side']);
		$linjeantal=if_isset($_POST['linjeantal']);
		$gebyr=if_isset($_POST['gebyr']);
		$rentevnr=if_isset($_POST['rentevnr']);
		$rentesats=if_isset($_POST['rentesats']);
	}
	
	if ($art) list($art_nr, $art_tekst)=explode(":", $art);
#	list($form_nr, $form_tekst)=explode(":", $formular);

	#tjekker om sprog_id er sat og hvis ikke, oprettes sprog_id
	if ($formularsprog && $formularsprog!='Dansk') {
		$qtxt = "select kodenr from grupper where art = 'VSPR' and box1='$formularsprog'";
		if ($r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$sprog_id=$r['kodenr'];
		} else {
			$r=db_fetch_array($q=db_select("select max(kodenr) as kodenr from grupper where art = 'VSPR' ",__FILE__ . " linje " . __LINE__));
			$sprog_id=$r['kodenr']+1;
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1) values ('Formular og varesprog','$sprog_id','VSPR','$formularsprog')");
		}
	}
	
	if (isset($_POST['op']) || isset($_POST['hojre'])) { #Flytning af 0 punkt.
		$op=$_POST['op']*1; $hojre=$_POST['hojre']*1;
		$qtxt="select id, xa, xb, ya, yb from formularer where formular=$form_nr and sprog='$formularsprog'";
		$query=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($row=db_fetch_array($query)){
			db_modify("update formularer set xa=$row[xa]+$hojre, ya=$row[ya]+$op where id=$row[id]",__FILE__ . " linje " . __LINE__);
			if ($row['yb']) {
				db_modify("update formularer set xb=$row[xb]+$hojre, yb=$row[yb]+$op where id=$row[id]",__FILE__ . " linje " . __LINE__);
			}
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
		else $htext="højre";
		print "<BODY onLoad=\"javascript:alert('Logo, tekster og Streger er flyttet $op mm $otext og $hojre mm til $htext')\">";
		$linjeantal=0; #
	}
	if ($submit=='Opdater' && $form_nr>=6 && $form_nr<=8 && $art_nr==2 && $gebyr) { #Rykkergebyr
		$tmp=strtoupper($gebyr);
		if ($r1=db_fetch_array(db_select("select id,varenr from varer where upper(varenr) = '$tmp'",__FILE__ . " linje " . __LINE__))) { 
			$gebyr=$r1['varenr'];
			$qtxt = "select id from formularer where beskrivelse ='GEBYR' and formular='$form_nr' and art=2 and sprog='$formularsprog'";
			if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("update formularer set xb='$r1[id]' where id = $r2[id]",__FILE__ . " linje " . __LINE__);
			}	else {
				$qtxt = "insert into formularer (beskrivelse, formular, art, xb, sprog) values ";
				$qtxt.= "('GEBYR', '$form_nr', '2', '$r1[id]', '$formularsprog')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
		} else print "<BODY onLoad=\"javascript:alert('Varenummeret $gebyr findes ikke i varelisten')\">";
	} elseif (($submit=='Opdater')&&($form_nr>=6)&&($form_nr<=8)&&($art_nr==2)&&(!$gebyr)) db_modify("delete from formularer where beskrivelse = 'GEBYR' and formular='$form_nr' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__); #20140902
	if ($submit=='Opdater' && $form_nr>=6 && $form_nr<=8 && $art_nr==2 && $rentevnr) { #Rykkerrenter
		$tmp=strtoupper($rentevnr);
		$rentesats=usdecimal($rentesats);
		if ($r1=db_fetch_array(db_select("select id, varenr from varer where upper(varenr) = '$tmp'",__FILE__ . " linje " . __LINE__))) { 
			$rentevnr=$r['varenr'];
			$qtxt = "select id from formularer where beskrivelse ='GEBYR' and formular='$form_nr' and art=2 and sprog='$formularsprog'";
			if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("update formularer set yb='$r1[id]', str='$rentesats' where id = $r2[id]",__FILE__ . " linje " . __LINE__);
			}	else {
					$qtxt = "insert into formularer (beskrivelse, formular, art, yb, str, sprog) values ";
					$qtxt.= "('GEBYR', '$form_nr', '2', '$r1[id]', '$rentesats', '$formularsprog')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
		} else print "<BODY onLoad=\"javascript:alert('Varenummeret $gebyr findes ikke i varelisten')\">";
	} elseif (($submit=='Opdater')&&($form_nr==6)&&($art_nr==2)&&(!$gebyr)) {
		$qtxt="delete from formularer where beskrivelse = 'GEBYR' and formular='$form_nr' and sprog='$formularsprog'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__); #20140902
	}
	if (isset($_POST['streger']) && $_POST['streger']){
		transaktion('begin');
		for ($x=0; $x<=$linjeantal; $x++) {
			if (!isset($id[$x])) $id[$x]=0;
			if (!isset($xa[$x])    || !$xa[$x])    $xa[$x]    = 0;
			if (!isset($ya[$x])    || !$ya[$x])    $ya[$x]    = 0;
			if (!isset($xb[$x])    || !$xb[$x])    $xb[$x]    = 0;
			if (!isset($yb[$x])    || !$yb[$x])    $yb[$x]    = 0;
			if (!isset($str[$x])   || !$str[$x])   $str[$x]   = 0;
			if (!isset($color[$x]) || !$color[$x]) $color[$x] = 0;
			if (!isset($fed[$x])   || !$fed[$x])   $fed[$x]   = NULL;
			if (!isset($kursiv[$x])|| !$kursiv[$x])$kursiv[$x]= NULL;
			if (!isset($beskrivelse[$x])) $beskrivelse[$x]=NULL;
			if ((trim($xa[$x])=='-')&&($id[$x])&&($beskrivelse[$x]!='LOGO')) {
				db_modify("delete from formularer where id =$id[$x] and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);
			} else {
				if ($beskrivelse[$x]=='LOGO' && !$id[$x] && $xa[$x] && $ya[$x]) {
					$qtxt = "insert into formularer (beskrivelse,formular,art,xa,ya,sprog) values ";
					$qtxt.= "('$beskrivelse[$x]',$form_nr,$art_nr,$xa[$x],$ya[$x],'$formularsprog')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				if ($art==5 && $xa[$x]==2) {
					$beskrivelse[$x]=str_replace("\n","<br>",$beskrivelse[$x]); 
				}
				$beskrivelse[$x]=db_escape_string($beskrivelse[$x]);
				if (isset($ny_beskrivelse[$x]) && $ny_beskrivelse[$x]) {
					$beskrivelse[$x]=trim($beskrivelse[$x]." $".$ny_beskrivelse[$x].";");
				}

				$xa[$x]=str_replace(",",".",$xa[$x])*1; $ya[$x]=str_replace(",",".",$ya[$x])*1; 
				$xb[$x]=str_replace(",",".",$xb[$x])*1; $yb[$x]=str_replace(",",".",$yb[$x])*1; 
				$str[$x]=$str[$x]*1; $color[$x]=$color[$x]*1;
				if ($x==0 ||(!$id[$x] && (($art_nr==5) || $form_nr==10))) {
					if ($xa[$x]>0) {
						if (($art!='1') && ($str[$x]<=1)) $str[$x]=10;
						if (!$justering[$x]) $justering[$x]='V';
						$qtxt = "insert into formularer ";
						$qtxt.= "(beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) ";
						$qtxt.= "values ";
						$qtxt.= "('$beskrivelse[$x]', $form_nr, $art_nr, $xa[$x], $ya[$x], $xb[$x], $yb[$x], $str[$x], $color[$x], ";
						$qtxt.= "'$form_font[$x]', '$fed[$x]', '$kursiv[$x]', '$side[$x]', '$justering[$x]', '$formularsprog')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					} elseif (isset($ny_beskrivelse[$x]) && substr($ny_beskrivelse[$x],0,10)=="kopier_alt") {
						list($a,$b)=explode('|',$ny_beskrivelse[$x]);
						kopier_alt($form_nr,$art_nr,$formularsprog,$b);
					}
				}	elseif ($id[$x]) {
					if (strstr($beskrivelse[$x],'betalingsid(')) {
						$streng=$beskrivelse[$x];
						$start=strpos($streng,'betalingsid(')+12; # 1 karakter efter startparantesen 
						$slut=strpos($streng,")");
						$len=$slut-$start;
						$streng=substr($streng,$start,$len);
						list($kontolen,$faktlen)=explode(",",$streng);
						if ($kontolen+$faktlen!=14) {
							$tmp=14-$faktlen;
							$beskrivelse[$x]=str_replace("($kontolen","($tmp",$beskrivelse[$x]);
							print "<BODY onLoad=\"javascript:alert('Den samlede strengl&aelig;ngde for v&aelig;rdierne ($streng) skal v&aelig;re 14.\\nv&aelig;rdierne er rettet')\">";
						}
					}
					if (!isset($justering[$x])) $justering[$x]='V';
					if (!isset($form_font[$x])) $form_font[$x]='';
					if (!isset($side[$x]))      $side[$x]='0';
					$beskrivelse[$x] = str_replace('$formular_bruttovægt','$formular_grossWeight',$beskrivelse[$x]);
					$beskrivelse[$x] = str_replace('$formular_nettovægt','$formular_netWeight',$beskrivelse[$x]);
					$qtxt = "update formularer set beskrivelse='$beskrivelse[$x]',xa=$xa[$x],ya=$ya[$x],xb=$xb[$x],yb=$yb[$x],";
					$qtxt.= "str=$str[$x],color=$color[$x],font='$form_font[$x]',fed='$fed[$x]',kursiv='$kursiv[$x]',";
					$qtxt.= "side='$side[$x]',justering='$justering[$x]' where id = $id[$x]";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			} 
		}
		transaktion('commit');	 
	}
}

if ($menu=='T') {  # 20150331 start
        include_once '../includes/top_header.php';
        include_once '../includes/top_menu.php';
        print "<div id=\"header\">\n";
		print "<div class=\"headerbtnLft\"><a class='button blue small' class=\"button red small left\" href=\"formular_indlaes_std.php\">".findtekst('572|Genindlæs standardformularer', $sprog_id)."</a> &nbsp; <a title=\"".findtekst('1779|Opret eller nedlæg sprog', $sprog_id)."\" class='button blue small' class=\"button red small left\" href=\"formularkort.php?nyt_sprog=yes\" accesskey=\"s\">".findtekst('801|Sprog', $sprog_id)."</a></div>\n";
		print "<span class=\"headerTxt\"></span>\n";     
		print "<div class=\"headerbtnRght\"><a title=\"".findtekst('1780|Indlæs eller fjern baggrundsfil', $sprog_id)."\" class='button blue small' href=logoupload.php?upload=yes accesskey=\"u\">".findtekst('571|Baggrund', $sprog_id)."</a></div>";    
        print "</div><!-- end of header -->";
        print "<div id=\"leftmenuholder\">";
        include_once 'left_menu.php';
        print "</div><!-- end of leftmenuholder -->\n";
				print "<div class=\"maincontentLargeHolder\">\n";
        print "<table border=\"1\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable2\"><tbody>";
} elseif ($menu=='S') {
	print "<html>\n";
	print "<head>\n";
	print "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>\n";
	print "<meta name='viewport' content='width=1024'>\n";
	print "</head>\n";
	print "<body>\n";
	print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>\n";
	print "<tr><td width='' height='1%' align='center' valign='top' collspan='2'>\n";
	print "<table width='100%' height='1%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>\n";

	print "<td width='12%'><a href=$returside accesskey='l'><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
	print findtekst('30|Tilbage', $sprog_id)."</button></a></td>\n";

	print "<td width='80%' align='center' style='$topStyle'>".findtekst('573|Formularkort', $sprog_id)."</td>\n";

	print "<td width='6%'><span title='".findtekst('1779|Opret eller nedlæg sprog', $sprog_id)."'><a href=formularkort.php?nyt_sprog=yes accesskey='s'>";
	print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('801|Sprog', $sprog_id)."</button></a></span></td>\n";

	print "<td width='6%'><span title='".findtekst('1781|Indlæs eller fjern fil', $sprog_id)."'><a href=logoupload.php?upload=yes accesskey='u'>";
	print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">Upload</button></a></span></td>\n";#20210804

	print "</tbody></table></td></tr>\n";
} else {
	# 2013.11.21 Tilføjet meta så ÆØÅ vises rigtigt. Også viewport til bedre visning på tablet
	//print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">\n";
	print "<html>\n";
	print "<head>\n";
	print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n";
	print "<meta name=\"viewport\" content=\"width=1024\">\n";
	print "</head>\n";
	print "<body>\n";
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
	print "<tr><td width=\"\" height=\"1%\" align=\"center\" valign=\"top\" collspan=\"2\">\n";
	print "<table width=\"100%\" height=\"1%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
	print "<td width=\"12%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=$returside accesskey=\"l\">".findtekst('30|Tilbage', $sprog_id)."</a></td>\n";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">".findtekst('573|Formularkort', $sprog_id)."</td>\n";
	print "<td width=\"6%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><span title=\"".findtekst('1779|Opret eller nedlæg sprog', $sprog_id)."\"><a href=formularkort.php?nyt_sprog=yes accesskey=\"s\">".findtekst('801|Sprog', $sprog_id)."</a></span></td>\n";
	print "<td width=\"6%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><span title=\"".findtekst('1781|Indlæs eller fjern fil', $sprog_id)."\"><a href=logoupload.php?upload=yes accesskey=\"u\">Upload</a></span></td>\n";#20210804
	print "</tbody></table></td></tr>\n";
}

if ($nyt_sprog) sprog($nyt_sprog,$skabelon,$handling);
print "<tr><td align=center width=100%><table align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";

$formular=array("","Tilbud","Ordrebekr&aelig;ftelse","Følgeseddel","Faktura","Kreditnota","Rykker_1","Rykker_2","Rykker_3","Plukliste","Pos","Kontokort","Indkøbsforslag","Rekvisition","Købsfaktura");

print "<tr><td colspan=\"10\" align=\"center\"><table><tbody>\n";
print "<form name=\"formularvalg\" action=\"$_SERVER[PHP_SELF]\" method=\"post\">\n"; #20210628
print "<tr><td>".findtekst('780|Formularer', $sprog_id)."</td>\n";
print "<td><SELECT class='inputbox' NAME=\"form_nr\">\n";
if ($form_nr) print "<option value=\"$form_nr\">$formular[$form_nr]</option>\n";
print "<option value=\"1\">".findtekst('812|Tilbud', $sprog_id)."</option>\n";
print "<option value=\"9\">".findtekst('574|Plukliste', $sprog_id)."</option>\n";
print "<option value=\"2\">".findtekst('575|Ordrebekræftelse', $sprog_id)."</option>\n";
print "<option value=\"3\">".findtekst('576|Følgeseddel', $sprog_id)."</option>\n";
print "<option value=\"4\">".findtekst('989|Faktura', $sprog_id)."</option>\n";
print "<option value=\"5\">".findtekst('577|Kreditnota', $sprog_id)."</option>\n";
print "<option value=\"6\">".findtekst('578|Rykker', $sprog_id)."_1</option>\n";
print "<option value=\"7\">".findtekst('578|Rykker', $sprog_id)."_2</option>\n";
print "<option value=\"8\">".findtekst('578|Rykker', $sprog_id)."_3</option>\n";
print "<option value=\"11\">".findtekst('515|Kontokort', $sprog_id)."</option>";
print "<option value=\"12\">".findtekst('954|Indkøbsforslag', $sprog_id)."</option>";
print "<option value=\"13\">".findtekst('579|Rekvisition', $sprog_id)."</option>";
print "<option value=\"14\">".findtekst('580|Købsfaktura', $sprog_id)."</option>";
# print "<option value=\"10\">Pos</option>";
print "</SELECT></td>\n";
print "<td>&nbsp;Art</td>\n";
print "<td><SELECT class='inputbox' NAME=\"art\">\n";
if ($form_nr && $art) print "<option value=\"$art\">$art_tekst</option>\n";
print "<option value=\"2:Tekster\">".findtekst('581|Tekster', $sprog_id)."</option>\n";
print "<option value=\"3:Ordrelinjer\">".findtekst('582|Ordrelinjer', $sprog_id)."</option>\n";
print "<option value=\"1:Streger\">".findtekst('583|Streger', $sprog_id)."</option>\n";
print "<option value=\"4:Flyt center\">".findtekst('584|Flyt center', $sprog_id)."</option>\n";
print "<option value=\"5:Mail tekst\">".findtekst('585|Mail tekst', $sprog_id)."</option>\n";
print "</SELECT></td>\n";
print "<td>".findtekst('801|Sprog', $sprog_id)."</td>\n";
print "<td><SELECT class='inputbox' NAME=\"sprog\">\n";
if (!isset($formularsprog) || !$formularsprog) $formularsprog="Dansk";
print "<option value=\"". $formularsprog ."\">". $formularsprog ."</option>\n";
$q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($formularsprog!=$r['sprog']) print "<option value=\"". $r['sprog'] ."\">". $r['sprog'] ."</option>\n";
}
	print "</SELECT></td>\n";
print "<td><input class='button gray medium' type=\"submit\" accesskey=\"v\" value=\"".findtekst('586|Vælg', $sprog_id)."\" name=\"formularvalg\"></td></tr>\n";
print "</form></tbody></table></td></tr>\n";
if ($form_nr=='10') $art_nr='3';
	print "<form name=\"streger\" action=\"$_SERVER[PHP_SELF]?formular=$form_nr&amp;art=$art\" method=\"post\">\n";
	print "<input type=\"hidden\" name=\"form_nr\" value=\"$form_nr\">\n";
	print "<input type=\"hidden\" name=\"sprog\" value=\"$formularsprog\">\n";
	print "<input type=\"hidden\" name=\"art\" value=\"$art\">\n";

if ($art_nr==1) {
	print "<tr><td><br></td></tr>\n";
	print "<tr><td colspan=10 align=center> LOGO</td></tr>\n";
	print "<tr><td><br></td></tr>\n";
		
	print "<tr><td></td><td></td><td align=center>X</td><td align=center>Y</td></tr>\n";
	$x=1;
	$qtxt="select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse ='LOGO' and sprog = '$formularsprog'";
	$query=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$row=db_fetch_array($query);
	print "<tr>\n";
	print "<input type=\"hidden\" name=\"id[$x]\" value=\"$row[id]\"><input type=\"hidden\" name=\"beskrivelse[$x]\" value=\"LOGO\">\n";
	print "<td colspan=\"2\"></td><td align=\"center\">";
	print "<input class='inputbox' type='text' style='text-align:right;width:40px;' name=xa[$x] value=".str_replace(".",",",round($row['xa'],1)).">\n";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=ya[$x] value=".str_replace(".",",",round($row['ya'],1)).">";

	print "<tr><td><br></td></tr>";
	print "<tr><td colspan=6 align=center>".findtekst('583|Streger', $sprog_id)."</td></tr>";
	print "<tr><td><br></td></tr>";

	print "<tr><td colspan=2 align=center>".findtekst('2365|Start', $sprog_id)."</td>";
	print "<td colspan=2 align=center>".findtekst('2366|Slut', $sprog_id)."</td></tr>";
	print "<tr><td align=center>X</td><td align=center>Y</td><td align=center>X</td><td align=center>Y</td>";
	print "<td align=center>".findtekst('3110|Bredde', $sprog_id)."</td><td align=center>".findtekst('1786|Farve', $sprog_id)."</td></tr>";

	$x=0;
	print "<tr>";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=xa[$x]>";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=ya[$x]>";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=xb[$x]>";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=yb[$x]>";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=str[$x]>";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=color[$x]>";
	print "</tr>";
 
	$x=1;
	$qtxt="select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse !='LOGO' and sprog='$formularsprog' order by ya,xa,yb,xb";
	$query=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row=db_fetch_array($query)){
		$x++; 
		print "<tr>";
		print "<input type=hidden name=id[$x] value=$row[id]>";
		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=xa[$x] value=".str_replace(".",",",round($row['xa'],1)).">";
		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=ya[$x] value=".str_replace(".",",",round($row['ya'],1)).">"; 
		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=xb[$x] value=".str_replace(".",",",round($row['xb'],1)).">";
		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=yb[$x] value=".str_replace(".",",",round($row['yb'],1)).">";
		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=str[$x] value=".round($row['str'],0).">";
		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=color[$x] value=".round($row['color'],0).">";
		print "</tr>";
	}	 
	$linjeantal=$x;
} elseif ($art_nr==2) {
	if ($form_nr>=6 && $form_nr<=9) {
		$gebyr='';$rentevnr='';
		$r=db_fetch_array(db_select("select xb,yb,str from formularer where beskrivelse ='GEBYR' and formular='$form_nr' and art='$art_nr' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__));
		$gebyr=$r['xb']*1;$rentevnr=$r['yb']*1;$rentesats=dkdecimal($r['str'],2);
		$r=db_fetch_array(db_select("select varenr from varer where id ='$gebyr'",__FILE__ . " linje " . __LINE__));
		$gebyr=$r['varenr'];
		print "<tr><td colspan=11 align=center title='".findtekst('1782|Skriv det varenummer der skal bruges til rykkergebyr.', $sprog_id)."'>".findtekst('1783|Varenummer for rykkergebyr', $sprog_id)." <input class='inputbox' type='text' size=15 name=gebyr value=$gebyr></td></tr>";
		$r=db_fetch_array(db_select("select varenr from varer where id ='$rentevnr'",__FILE__ . " linje " . __LINE__)); 
		$rentevnr=$r['varenr'];
		print "<tr><td colspan=11 align=center title='".findtekst('1784|Skriv det varenummer og rentesatsen som bruges ved renteberegning. Rentesatsen gælder pr påbegyndt måned', $sprog_id)."'>".findtekst('1785|Varenummer/sats for rente', $sprog_id)." <input class='inputbox' type='text' size=15 name=rentevnr value=$rentevnr><input class='inputbox' type='text' size=1 name=rentesats value=$rentesats></td></tr>";
		print "<tr><td colspan=11><hr></td></tr>";
	}
	 
	print "<tr><td></td><td align=center>".findtekst('1163|Tekst', $sprog_id)."</td>";
	print "<td align=center>X</td><td align=center>Y</td>";
	print "<td align=center>".findtekst('1790|Højde', $sprog_id)."</td><td align=center> ".findtekst('1786|Farve', $sprog_id)."</td>";
	$span=findtekst('1787|Justering - H: Højrestillet\n C: Centreret\n V: Venstrestillet', $sprog_id);
	print "<td align=center><span title = \"$span\">".findtekst('2367|Just.', $sprog_id)."</span></td><td align=center>Font</td>";
	$span=findtekst('1788|1: Kun side 1\n!1: Alle foruden side 1\nS: Sidste side\n!S: Alle foruden sidste side\nA: Alle sider', $sprog_id);	
	print "<td align=center><span title = \"$span\">".findtekst('2368|Side', $sprog_id)."</span></td>";
	print "<td align=center>".findtekst('2369|Fed', $sprog_id)."</td><td align=center>".findtekst('1789|Kursiv', $sprog_id)."</td>";
	#		print "<td align=center>Understr.</td></tr>";
	drop_down(0,$form_nr,$art_nr,$formularsprog,"","","","","","","","","","","","","","");  
	
$tmp = db_escape_string($formularsprog);
	$qtxt="select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse != 'GEBYR' and sprog='$tmp' order by ya desc, xa";
	$query=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row=db_fetch_array($query)) {
		$x++;
		drop_down($x,$form_nr,$art_nr,$formularsprog,$row['id'],$row['beskrivelse'],$row['xa'],$row['xb'],$row['ya'],$row['yb'],$row['str'],$row['color'],$row['justering'],$row['font'],$row['fed'],$row['kursiv'],$row['side']);  
	}
	$linjeantal=$x;
} elseif ($art_nr==3) {
	if ($form_nr==10) $x = pos_linjer($form_nr,$art_nr,$formularsprog);
	else $x = ordrelinjer($form_nr,$art_nr,$formularsprog);
	$linjeantal=$x;
} elseif ($art_nr==4) {
	print "<tr><td><br></td></tr><tr><td><br></td></tr>\n";
	print "<tr><td colspan=2 align=center>Her har du mulighed for at flytte centreringen p&aring; formularen</td></tr>";
	print "<tr><td colspan=2 align=center>Angiv blot det antal mm. der skal flyttes hhv. op og til højre</td></tr>";
	print "<tr><td colspan=2 align=center>Anvend negativt fortegn, hvis der skal rykkes ned eller til venstre</td></tr>";
	print "<tr><td colspan=2 align=center></td></tr>";
	print "<tr><td align=center>Op</td><td><input class='inputbox' type='text' style='text-align:right' size=2 name=op></td><tr>";
	print "<tr><td align=center>Højre</td><td><input class='inputbox' type='text' style='text-align:right' size=2 name=hojre></td><tr>";
} elseif ($art_nr==5 && $form_nr!=3) {
	print "<tr><td><br></td></tr>";
	print "<tr><td align=\"center\" colspan=\"2\">".findtekst('215|Emne og tekst til brug ved udsendelse som e-mail', $sprog_id)."</td></tr><tr><td><br></td></tr>\n";
	$qtxt = "select * from formularer where formular = '$form_nr' and art = '$art_nr' and sprog='$formularsprog' order by xa,id";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	($form_nr==1 || $form_nr==2 || $form_nr==4)?$i=3:$i=2; # 2013.11.21 Sætter $i til 3 hvis valg er Tilbud, Ordrer eller Faktura, ellers er $i = 2
	$id1 = $id2 = $mailtext = $subjekt = NULL;
	for ($x=1;$x<=$i;$x++) {
		if ($r=db_fetch_array($q)) {
			if ($r['xa']==1) {
				$subjekt=$r['beskrivelse'];
				$id1=$r['id'];
			} elseif ($r['xa']==2) {
				$mailtext=str_replace("<br>","\n",$r['beskrivelse']);
				$id2=$r['id']*1; #20140124
			} 	elseif ($r['xa']==3) { # 2013.11.21 Er kun med hvis $i er 3
				$bilagnavn=$r['beskrivelse'];
				$id3=$r['id'];
			}
			print "<input type=\"hidden\" name='id[$x]' value='$r[id]'>\n";
			print "<input type=\"hidden\" name='xa[$x]' value='$x'>\n";
			print "<input type=\"hidden\" name='form_nr' value='$form_nr'>\n";
			print "<input type=\"hidden\" name='art' value='$art'>\n";
			print "<input type=\"hidden\" name='sprog' value='$formularsprog'>\n";
		}
	}
	if (!$id1) {
		for ($x=1;$x<=2;$x++) {
			$qtxt = "insert into formularer (xa, formular, art, sprog) values ('$x', '$form_nr',$art_nr,'$formularsprog')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "select max id as id from formularer where ";
			$qtxt.= "xa = '$x' and  formular =  '$form_nr', and art = $art_nr and  sprog = '$formularsprog'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			print "<input type=\"hidden\" name='id[$x]' value='$r[id]'>\n";
			print "<input type=\"hidden\" name='xa[$x]' value='$x'>\n";
			print "<input type=\"hidden\" name='form_nr' value='$form_nr'>\n";
			print "<input type=\"hidden\" name='art' value='$art'>\n";
			print "<input type=\"hidden\" name='sprog' value='$formularsprog'>\n";
		}
	}

	# 2013.11.21 Har udkommenteret en overflødig slettefunktion der slettede alt som ikke havde $id1 og $id2 med samme $form_nr og $art_nr. 
	//db_modify("delete from formularer where formular = '$form_nr' and art = '$art_nr' and sprog='$formularsprog' and id!='$id1' and id!= '$id2'",__FILE__ . " linje " . __LINE__);
	print "<tr><td title=\"".findtekst('217|Skriv overskriften til den email som bruges', $sprog_id)."\">".findtekst('216|Emne', $sprog_id)."&nbsp;</td>";
	print "<td title=\"".findtekst('217|Skriv overskriften til den email som bruges', $sprog_id)."\">";
	print "<input class='inputbox' type='text' size='40' name='beskrivelse[1]' value = '$subjekt'></td></tr>\n";
	print "<tr><td title='".findtekst('219|Skriv teksten til den email som bruges', $sprog_id)."' valign='top'>".findtekst('218|Mailtekst', $sprog_id)."&nbsp;</td>\n";
	print "<td colspan = '4'  title=\"".findtekst('219|Skriv teksten til den email som bruges', $sprog_id)."\">";
	print "<textarea name='beskrivelse[2]' rows='5' cols='100' onchange='javascript:docChange = true;'>";
	print "$mailtext</textarea></td></tr>\n";
	if ($form_nr==1 || $form_nr==2 || $form_nr==4) {
		print "<tr>";
		print "<td title=\"".findtekst('672|Skriv navn til vedhæftet bilag', $sprog_id)."\">".findtekst('671|Bilag', $sprog_id)."&nbsp;</td>";
		print "<td title=\"".findtekst('672|Skriv navn til vedhæftet bilag', $sprog_id)."\">";
		print "<input class='inputbox' type='text' size='40' name='beskrivelse[3]' value = \"$bilagnavn\"></td>";
		print "</tr>\n";
	}

}
if (!$linjeantal) $linjeantal=$x;
print "<input type=hidden name=\"linjeantal\" value=$linjeantal>\n";
print "<tr><td colspan=11 align=\"center\"><hr></td></tr>\n";
if ($form_nr && $art) print "<tr><td colspan=\"11\" align=\"center\"><input class='button blue medium' type=\"submit\" accesskey=\"v\" value=\"".findtekst('898|Opdatér', $sprog_id)."\" name=\"streger\"></td></tr>\n";
print "</tbody></table></td></tr></form>\n";

function sprog($nyt_sprog,$skabelon,$handling){
global $sprog_id;
$tmp=db_escape_string(htmlentities($nyt_sprog));
if ($tmp!=$nyt_sprog) {
	print "<BODY onLoad=\"javascript:alert('Sprog ben&aelig;vnelse m&aring; ikke indeholde specialtegn\\nOprettelse af $nyt_sprog er annulleret')\">";
} elseif ($nyt_sprog && $handling=='gem' && $nyt_sprog!="yes") {

	$tmp=strtolower($nyt_sprog);
	if (db_fetch_array($q=db_select("select kodenr from grupper where lower(box1) = '$tmp' and art = 'VSPR' ",__FILE__ . " linje " . __LINE__))) {
		print "<BODY onLoad=\"javascript:alert('$nyt_sprog er allerede oprettet. Oprettelse annulleret')\">";
	} elseif ($skabelon && $handling=='gem') {
		$r=db_fetch_array($q=db_select("select max(kodenr) as kodenr from grupper where art = 'VSPR' ",__FILE__ . " linje " . __LINE__));
		$kodenr=$r['kodenr']+1;
		db_modify("insert into grupper (beskrivelse,kodenr,art,box1) values ('sprog','$kodenr','VSPR','$nyt_sprog')",__FILE__ . " linje " . __LINE__);
		$q=db_select("select * from formularer where sprog = '$skabelon'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$xa=$r['xa']*1; $ya=$r['ya']*1; $xb=$r['xb']*1; $yb=$r['yb']*1;$str=$r['str']*1;$color=$r['color']*1;
			db_modify("insert into formularer(formular,art,beskrivelse,justering,xa,ya,xb,yb,str,color,font,fed,kursiv,side,sprog) values	('$r[formular]','$r[art]','".db_escape_string($r['beskrivelse'])."','$r[justering]','$xa','$ya','$xb','$yb','$str','$color','$r[font]','$r[fed]','$r[kursiv]','$r[side]','".db_escape_string($nyt_sprog)."')",__FILE__ . " linje " . __LINE__);
		}
		print "<BODY onLoad=\"javascript:alert('$nyt_sprog ".findtekst('2363|er oprettet', $sprog_id).".')\">";
	}
} elseif ($skabelon && $handling=='slet') {
	db_modify("delete from formularer where sprog = '$skabelon'",__FILE__ . " linje " . __LINE__);
	db_modify("delete from grupper where art = 'VSPR' and box1 = '$skabelon'",__FILE__ . " linje " . __LINE__);
	 
} else {
	print "<form name=formularvalg action=$_SERVER[PHP_SELF]?nyt_sprog=yes method=\"post\">";
	print "<tr><td width=100% align=center><table border=0><tbody>"; # 20150331
	print "<tr><td>".findtekst('2362|Skriv sprog der ønskes tilføjet', $sprog_id).":</td><td><input class='inputbox' type='text' name='nyt_sprog' size='15'<td></tr>";
	print "<tr><td>".findtekst('801|Sprog', $sprog_id); #." ".findtekst('801|Sprog', $sprog_id)."</td>";
	print "<td><SELECT class='inputbox' NAME='skabelon'>";
	$q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) print "<option>$r[sprog]</option>";
	print "<option></option>";
	print "</SELECT></td><tr>";
	print "<tr><td colspan=2 align=center><input type=submit accesskey=\"g\" name=\"gem\" value=\"".findtekst('3|Gem', $sprog_id)."\">&nbsp;";
	print "<input type=submit accesskey=\"s\" value=\"".findtekst('1099|Slet', $sprog_id)."\" name=\"slet\" onclick=\"return confirm('".findtekst('2364|Slet det valgte sprog', $sprog_id)."?')\">&nbsp;";
	print "<input type=submit accesskey=\"f\" value=\"".findtekst('159|Fortryd', $sprog_id)."\"name=\"fortryd\"></td></tr>";
	print "</tbody></table></td></tr>";
	exit;
}	

} # endfunc sprog

function drop_down($x,$form_nr,$art_nr,$formularsprog,$id,$beskrivelse,$xa,$xb,$ya,$yb,$str,$color,$justering,$font,$fed,$kursiv,$side){
	
/*
	$options=array(print "<option>eget_firmanavn</option>";
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
*/
	print "<tr>";
	print "<input type=hidden name=id[$x] value=$id>";
	print "<td><SELECT class='inputbox' style='width:100px;' NAME='ny_beskrivelse[$x]'>";
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
	if ($form_nr<6  || $form_nr==10 || $form_nr>=12) {
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
	} elseif ($form_nr==11) {
		print "<option value=\"konto_firmanavn\">konto_firmanavn</option>";
		print "<option value=\"konto_addr1\">konto_addr1</option>";
		print "<option value=\"konto_addr2\">konto_addr2</option>";
		print "<option value=\"konto_postnr\">konto_postnr</option>";
		print "<option value=\"konto_bynavn\">konto_bynavn</option>";
		print "<option value=\"konto_land\">konto_land</option>";
		print "<option value=\"konto_kontakt\">konto_kontakt</option>";
		print "<option value=\"konto_cvrnr\">konto_cvrnr</option>";
		print "<option value=\"konto_valuta\">konto_valuta</option>";
	}	
	if ($form_nr!=11) {
		print "<option value=\"ordre_firmanavn\">ordre_firmanavn</option>";
		print "<option value=\"ordre_addr1\">ordre_addr1</option>";
		print "<option value=\"ordre_addr2\">ordre_addr2</option>";
		print "<option value=\"ordre_postnr\">ordre_postnr</option>";
		print "<option value=\"ordre_bynavn\">ordre_bynavn</option>";
		print "<option value=\"ordre_land\">ordre_land</option>";
		print "<option value=\"ordre_kontakt\">ordre_kontakt</option>";
		print "<option value=\"ordre_cvrnr\">ordre_cvrnr</option>";
	}
	if ($form_nr<6 || $form_nr==10 || $form_nr>=12) {
		print "<option>ordre_ean</option>";
		print "<option>ordre_felt_1</option>";
		print "<option>ordre_felt_2</option>";
		print "<option>ordre_felt_3</option>";
		print "<option>ordre_felt_4</option>";
		print "<option>ordre_felt_5</option>";
		print "<option>ordre_institution</option>";
		print "<option>ordre_kundeordnr</option>";
		print "<option>ordre_lev_navn</option>";
		print "<option>ordre_lev_addr1</option>";
		print "<option>ordre_lev_addr2</option>";
		print "<option>ordre_lev_postnr</option>";
		print "<option>ordre_lev_bynavn</option>";
		print "<option>ordre_lev_kontakt</option>";
		print "<option>ordre_levdate</option>";
		print "<option>ordre_momssats</option>";
		print "<option>ordre_notes</option>";
		print "<option>ordre_ordredate</option>";
		print "<option>ordre_ordrenr</option>";
		print "<option>ordre_projekt</option>";
		print "<option>ordre_valuta</option>";
	}	
	if ($form_nr==4 || $form_nr==13) {
		print "<option>ordre_fakturanr</option>";
		print "<option>ordre_fakturadate</option>";
	}	
	if ($form_nr==4) print "<option>formular_forfaldsdato</option>";
	print "<option>formular_side</option>";
	print "<option>formular_nextside</option>";
	print "<option>formular_preside</option>";
	print "<option>formular_transportsum</option>";
	print "<option>formular_betalingsid(9,5)</option>";
	if ($form_nr<6 || $form_nr==10 || $form_nr>=12) {
		print "<option>formular_moms</option>";
		print "<option>formular_momsgrundlag</option>";
	}
	print "<option>formular_ialt</option>";
	if ($form_nr==3) {
		print "<option>levering_lev_nr</option>";
		print "<option>levering_salgsdate</option>";
		print "<option value='formular_grossWeight'>formular_bruttovægt</option>\n";
		print "<option value='formular_netWeight'>formular_nettovægt</option>\n";
	} 
	if ($form_nr>=6) {
		print "<option>forfalden_sum</option>";
		print "<option>rykker_gebyr</option>";
	}	
	if (($form_nr>1 && $form_nr<6) || $form_nr>11) print "<option value = \"kopier_alt|1\">Kopier alt fra tilbud</option>";
	if (($form_nr!=2 && $form_nr<6) || $form_nr>11) print "<option value = \"kopier_alt|2\">Kopier alt fra ordrebrkræftelse</option>";
	if (($form_nr!=4 && $form_nr<6) || $form_nr>11) print "<option value = \"kopier_alt|4\">Kopier alt fra faktura</option>";
	if ($form_nr<5) print "<option value = \"kopier_alt|5\">Kopier alt fra kreditnota</option>";
	if ($form_nr>12) print "<option value = \"kopier_alt|12\">Kopier alt fra indkøbsforslag</option>";
	if ($form_nr>11 && $form_nr!=13) print "<option value = \"kopier_alt|13\">Kopier alt fra rekvisition</option>";
	if ($form_nr>11 && $form_nr!=14) print "<option value = \"kopier_alt|14\">Kopier alt fra indkøbsfaktura</option>";
	
	print "</SELECT></td>";
	$beskrivelse = str_replace('$formular_grossWeight','$formular_bruttovægt',$beskrivelse);
	$beskrivelse = str_replace('$formular_netWeight','$formular_nettovægt',$beskrivelse);
	print "<td align=center><input class='inputbox' type='text' style='width:400px;' name='beskrivelse[$x]' value=\"$beskrivelse\"></td>";
	if (!$xa) $xa = 0;
	print "<td align=center>
		<input class='inputbox' type='text' style='text-align:right;width:40px;'
		name='xa[$x]' value=".str_replace(".",",",round($xa,1)).">
		</td>";
	if ($yb != "-") {
		if (!$ya) $ya = 0;
		print "<td align=center>
			<input class='inputbox' type='text' style='text-align:right;width:40px;' 
			name='ya[$x]'
			value=".str_replace(".",",",round($ya,1)).">
			</td>";
	}
	if (!$str) $str = 0;
	print "<td align=center>
		<input class='inputbox' type='text' style='text-align:right;width:40px;' name=str[$x] value=".round($str,0).">
		</td>";
	if (!$color) $color = 0;
	print "<td align=center>
		<input class='inputbox' type='text' style='text-align:right;width:40px;' name=color[$x] value=".round($color,0).">
		</td>";
	print "<td><SELECT class='inputbox' NAME=justering[$x]>";
	print "<option>$justering</option>";
	print "<option>V</option>";
	print "<option>C</option>";
	print "<option>H</option>";
	print "</SELECT></td>";
	print "<td><SELECT class='inputbox' NAME=form_font[$x]>";
	if ($font) print "<option>$font</option>";
	print "<option>Helvetica</option>";
	#			print "<option>Courier</option>";
	#			print "<option>Bookman</option>";
	print "<option>Times</option>";
	print "<option>Ocrbb12</option>";
	 print "</SELECT></td>";
	print "<td><SELECT class='inputbox' NAME=side[$x]>";
	if ($side) print "<option>$side</option>";
	print "<option>A</option>";
	print "<option>1</option>";
	print "<option>!1</option>";
	print "<option>S</option>";
	print "<option>!S</option>";
	print "</SELECT></td>";
	if ($fed=='on') $fed='checked';
	print "<td align=center><input class='inputbox'' type='checkbox' name='fed[$x]' $fed></td>";
	if ($kursiv=='on') $kursiv='checked';
	print "<td align=center><input class='inputbox' type='checkbox' name='kursiv[$x]' $kursiv></td>";
	print "</tr>";
} #endfunc drop_down		
##############################################################################################
function ordrelinjer($form_nr,$art_nr,$formularsprog){
	global $sprog_id;
	$x=1;
	print "<tr><td></td><td></td><td align=center>Linjeantal</td>\n";
	print "<td align=center>Y</td>\n";
	print "<td align=center>Linafs.</td></tr>\n";
	#		print "<td align=center>Understr.</td></tr>";
	$qtxt="select id from formularer where formular = $form_nr and art = $art_nr and beskrivelse = 'generelt' and sprog='$formularsprog' order by xa";
	$x=0;
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($x >= 1) { #der er dubletter i nogle regnskaber som giver bøvl...
			$qtxt="delete from formularer where id='$r[id]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} 
		$x++;
	}
	if ($x==0) {
		$qtxt="insert into formularer (formular, art, beskrivelse, xa, ya, xb,sprog) values ($form_nr, $art_nr, 'generelt', 34, 185, 4,'$formularsprog')";
		db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$qtxt="select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse = 'generelt' and sprog='$formularsprog' order by xa";
	$query=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$row=db_fetch_array($query);
	print "<tr><td></td><td></td>\n";
	print "<input type=hidden name=id[$x] value=$row[id]>\n";
	print "<input type=hidden name='beskrivelse[$x]' value=$row[beskrivelse]>\n";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=xa[$x] value=".round($row['xa'],1)."></td>\n";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=ya[$x] value=".round($row['ya'],1)."></td>\n";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right' size=3 name=xb[$x] value=".round($row['xb'],1)."></td></tr>\n";
	print "<tr><td>".findtekst('914|Beskrivelse', $sprog_id)."</td>\n";
	print "<td align=center>X</td>\n";
	print "<td align=center>Højde</td><td align=center> Farve</td>\n";
	print "<td align=center>Just.</td><td align=center>Font</td><td align=center> Fed</td>\n";
	print "<td align=center> Kursiv</td><td align=center> Tekstl&aelig;ngde</td></tr>\n";

$x=0;
	print "<tr>\n";
	print "<td><SELECT class='inputbox' NAME=beskrivelse[$x]>\n";
	if ($form_nr<6 || $form_nr==9 || ($form_nr>=12 && $form_nr<=14)) {
		print "<option value = 'posnr'>posnr</option>\n";
		print "<option value = 'varenr'>".findtekst('917|Varenr.', $sprog_id)."</option>\n";
		print "<option value = 'lev_varenr'>lev_varenr</option>\n";
		print "<option value = 'antal'>".findtekst('916|Antal', $sprog_id)."</option>\n";
		print "<option value = 'enhed'>".findtekst('945|Enhed', $sprog_id)."</option>\n";
		print "<option value = 'beskrivelse'>".findtekst('914|Beskrivelse', $sprog_id)."</option>\n";
		print "<option value = 'pris'>".findtekst('915|Pris', $sprog_id)."</option>\n";
		print "<option value = 'rabat'>".findtekst('428|Rabat', $sprog_id)."</option>\n";
		print "<option value = 'momssats'>momssats</option>\n";
		if ($procentfakt) print "<option>procent</option>";
		print "<option value=\"linjemoms\">moms</option>";
		print "<option value=\"varemomssats\">momssats</option>";
		print "<option>linjesum</option>\n";
		print "<option>projekt</option>\n";
		print "<option>procent</option>\n"; #20140709
		print "<option>lokation</option>\n";
		if ($form_nr==3) {
			print "<option>lev_tidl_lev</option>\n";
			print "<option>lev_antal</option>\n";
			print "<option>lev_rest</option>\n";
			print "<option>lokation</option>\n";
			print "<option>vare_note</option>\n";
		} 
		if ($form_nr==9) {
			print "<option>leveres</option>\n";
			print "<option>lokation</option>\n";
			print "<option>lev_antal</option>\n";
			print "<option>lev_rest</option>\n";
			print "<option>Fri tekst</option>\n";
		} 
	} elseif ($form_nr==11) {
		print "<option>".findtekst('914|Beskrivelse', $sprog_id)."</option>";
		print "<option>dato</option>";
		print "<option>debet</option>";
		print "<option>faktnr</option>";
		print "<option>forfaldsdato</option>";
		print "<option>kredit</option>";
		print "<option>saldo</option>";
	} else {
		print "<option>dato</option>\n";
		print "<option>faktnr</option>\n";
		print "<option>".findtekst('914|Beskrivelse', $sprog_id)."</option>\n";
		print "<option>beløb</option>\n";
	}
	print "</SELECT></td>\n";
		#		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=xa[$x]></td>";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=xa[$x]></td>\n";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=str[$x]></td>\n";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=color[$x]></td>\n";
	print "<td><SELECT class='inputbox' NAME=justering[$x]>\n";
	print "<option>V</option>\n";
	print "<option>C</option>\n";
	print "<option>H</option>\n";
	print "</SELECT></td>\n";
	print "<td><SELECT class='inputbox' NAME=form_font[$x]>\n";
	print "<option>Helvetica</option>\n";
	#	 print "<option>Courier</option>";
	#	 print "<option>Bookman</option>";
	print "<option>Times</option>\n";
	print "</SELECT></td>\n";
	print "<td align=center><input class='inputbox' type=checkbox name=fed[$x]></td>\n";
	print "<td align=center><input class='inputbox' type=checkbox name=kursiv[$x]></td>\n";
	print "</tr>\n";

	$x=1;
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse != 'generelt' and sprog='$formularsprog' order by xa",__FILE__ . " linje " . __LINE__);
	while ($row=db_fetch_array($query)){
		$x++;
		$besk[$x]=$row['beskrivelse'];
		if ($besk[$x]=='varemomssats') $besk[$x]="momssats";
		if ($besk[$x]=='linjemoms') $besk[$x]="moms";
		print "<tr>\n";
		print "<input type=hidden name=\"id[$x]\" value=\"$row[id]\">\n";
		print "<input type=hidden name=\"beskrivelse[$x]\" value=\"$row[beskrivelse]\">\n";
		if (strstr($row['beskrivelse'],"fritekst") || $row['beskrivelse'] == "Fri tekst") {
			print "<input type=hidden name=\"tabel[$x]\" value=\"fritekst\">\n";
			print "<td><input class='inputbox' type='text' name=\"beskrivelse[$x]\" value=\"$row[beskrivelse]\"></td>\n";
		} else {
			print "<input type=hidden name=\"tabel[$x]\" value=\"\">\n";
			print "<td>$row[beskrivelse]</td>\n";
		}
		/*		
		print "<td><SELECT class='inputbox' NAME=beskrivelse[$x]>";
		print "<option>$row[beskrivelse]</option>";
		if ($form_nr<6) {
			print "<option>posnr</option>";
			print "<option>varenr</option>";
			print "<option>antal</option>";
			print "<option>beskrivelse</option>";
			print "<option>pris</option>";
			print "<option>rabat</option>";
			print "<option>linjesum</option>";
			if ($form_nr==3) {
				print "<option>lev_tidl_lev</option>";
				print "<option>lev_antal</option>";
				print "<option>lev_rest</option>";
	 		} 
		}
		else {
			print "<option>dato</option>";
			print "<option>faktnr</option>";
			print "<option>beskrivelse</option>";
			print "<option>beløb</option>";
		}
		print "</SELECT></td>";
*/		
		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=xa[$x] value=".str_replace(".",",",round($row['xa'],1))."></td>\n";
		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=str[$x] value=".round($row['str'],0)."></td>\n";
		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=color[$x] value=".round($row['color'],0)."></td>\n";
		print "<td><SELECT class='inputbox' NAME=justering[$x]>\n";
		print "<option>$row[justering]</option>\n";
		print "<option>V</option>\n";
		print "<option>C</option>\n";
		print "<option>H</option>\n";
		print "</SELECT></td>\n";
		print "<td><SELECT class='inputbox' NAME=form_font[$x]>\n";
		print "<option>$row[font]</option>\n";
		print "<option>Helvetica</option>\n";
		print "<option>Times</option>\n";
		print "</SELECT></td>\n";
		if ($row['fed']=='on') {$row['fed']='checked';}
		print "<td align=center><input class='inputbox' type=checkbox name=fed[$x] $row[fed]></td>\n";
		if ($row['kursiv']=='on') {$row['kursiv']='checked';}
		print "<td align=center><input class='inputbox' type='checkbox' name='kursiv[$x]' $row[kursiv]></td>\n";
		if (strtolower($row['beskrivelse']) == 'beskrivelse') {
			print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name='xb[$x]' value='".str_replace(".",",",round($row['xb'],1))."'></td>\n";
		}
		print "</tr>\n";
	}	 
	return($x);
} #endfunc ordrelinjer		
###############################################################################
function pos_linjer($form_nr,$art_nr,$formularsprog){
	global $sprog_id;
	global $menu;
	$x=1;
	print "<tr><td></td><td></td><td align=cente>Toplinjer</td>";
	print "<td align=center>Bundlinjer</td>";
	print "<td align=center>Linafs.</td></tr>";
	#
if (!$r=db_fetch_array(db_select("select * from formularer where formular = '$form_nr' and art = '3' and beskrivelse = 'generelt' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__))) {
		$q=db_modify ("insert into formularer (formular, art, beskrivelse, sprog, xa, ya, xb) values ('$form_nr','3','generelt','$formularsprog','4','2',4)",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from formularer where formular = $form_nr and art = 3 and beskrivelse = 'generelt' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__));
	}
	$header=str_replace(".",",",round($r['xa'],1));
	$footer=str_replace(".",",",round($r['ya'],1));
	$linespace=round($r['xb'],0);
	print "<tr><td></td><td></td>\n";
	print "<input type=hidden name=id[$x] value=\"$r[id]\">\n";
	print "<input type=hidden name='beskrivelse[$x]' value=\"$r[beskrivelse]\">\n";
	print "<input type=hidden name=form value=\"10\">\n";
	print "<input type=hidden name=art value=\"3\">\n";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=xa[$x] value=\"$header\"></td>\n";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=ya[$x] value=\"$footer\"></td>\n";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=xb[$x] value=\"$linespace\"></td></tr>\n";
	# hvis header eller footer er blevet reduceret slettes de overskydende linjer.
  db_modify("delete from formularer where formular = $form_nr and art = '3' and xb > $header and ya='1' and beskrivelse != 'generelt' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);
  db_modify("delete from formularer where formular = $form_nr and art = '3' and xb > $footer and ya='2' and beskrivelse != 'generelt' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);
	$x++;
	if ($header) {
	  print "<tr><td colspan=11><table><tbody>";
		print "<tr><td colspan=11><hr></td></tr>";
		print "<tr><td></td><td align=center>".findtekst('1163|Tekst', $sprog_id)."</td>";
		print "<td align=center>X</td>";
		print "<td align=center>".findtekst('1780|Indlæs eller fjern baggrundsfil', $sprog_id)."</td><td align=center> ".findtekst('1786|Farve', $sprog_id)."</td>";
		$span=findtekst('1787|Justering - H: Højrestillet\n C: Centreret\n V: Venstrestillet', $sprog_id);
		print "<td align=center><span title = \"$span\">Just.</span></td><td align=center>Font</td>";
		$span=$span=findtekst('1788|1: Kun side 1\n!1: Alle foruden side 1\nS: Sidste side\n!S: Alle foruden sidste side\nA: Alle sider', $sprog_id);	#20210804
		print "<td align=center><span title = \"$span\">Side</span></td>";
		print "<td align=center>Fed</td><td align=center>".findtekst('1789|Kursiv', $sprog_id)."</td>";
		$z=0;
		for ($y=$x;$y<$header+$x;$y++) {
			$z++;
			$r=db_fetch_array(db_select("select * from formularer where formular = $form_nr and art = '3' and xb='$z' and ya='1' and sprog='$formularsprog' order by xa",__FILE__ . " linje " . __LINE__));
			print "<input type=hidden name=id[$y] value=\"$r[id]\">\n";
			print "<input type=hidden name=xb[$y] value=\"$z\">\n";
			print "<input type=hidden name=ya[$y] value=\"1\">\n";
			if (!$r['id']) {
				$r['str']='8';$r['color']='0';$r['justering']='V';$r['font']='Helvetica';$r['side']='A';
			}	
			drop_down($y,$form_nr,$art_nr,$formularsprog,$r['id'],$r['beskrivelse'],$r['xa'],$z,"1","-",$r['str'],$r['color'],$r['justering'],$r['font'],$r['fed'],$r['kursiv'],$r['side']);  
			print "\n";
		}
		$x=$x+$header;
		print "<tr><td colspan=11><hr></td></tr>";
	  print "</tbody></table></td></tr>";
	}
#	$x++;
	print "<tr><td>".findtekst('914|Beskrivelse', $sprog_id)."</td>";
	print "<td align=center>X</td>";
	print "<td align=center>Højde</td><td align=center> Farve</td>";
	print "<td align=center>Just.</td><td align=center>Font</td><td align=center> Fed</td>";
	print "<td align=center> Kursiv</td><td align=center> Tekstl&aelig;ngde</td></tr>";
	#		print "<td align=center>Understr.</td></tr>";
	print "<tr>";
	print "<td><SELECT class='inputbox' NAME=beskrivelse[$x]>";
	print "<option>posnr</option>";
	print "<option value = 'varenr'>".findtekst('917|Varenr.', $sprog_id)."</option>\n";
	print "<option value = 'antal'>".findtekst('916|Antal', $sprog_id)."</option>\n";
	print "<option value = 'enhed'>".findtekst('945|Enhed', $sprog_id)."</option>\n";
	print "<option value = 'beskrivelse'>".findtekst('914|Beskrivelse', $sprog_id)."</option>\n";
	print "<option value = 'pris'>".findtekst('915|Pris', $sprog_id)."</option>\n";
	print "<option value = 'rabat'>".findtekst('428|Rabat', $sprog_id)."</option>\n";
	print "<option value=\"linjemoms\">moms</option>";
	print "<option value=\"varemomssats\">momssats</option>";
	print "<option>linjesum</option>";
	print "<option>projekt</option>";
	print "</SELECT></td>";
	print "<input type=hidden style='text-align:right;width:40px;' name=ya[$x] value=\"0\">";
	print "<td align=center><input class='inputbox' type	=text style='text-align:right;width:40px;' name=xa[$x]></td>";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=str[$x]></td>";
	print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=color[$x]></td>";
	print "<td><SELECT class='inputbox' NAME=justering[$x]>";
	print "<option>V</option>";
	print "<option>C</option>";
	print "<option>H</option>";
	print "</SELECT></td>";
	print "<td><SELECT class='inputbox' NAME=form_font[$x]>";
	print "<option>Helvetica</option>";
	#	 print "<option>Courier</option>";
	#	 print "<option>Bookman</option>";
	print "<option>Times</option>";
	print "</SELECT></td>";
	print "<td align=center><input class='inputbox' type=checkbox name=fed[$x]></td>";
	print "<td align=center><input class='inputbox' type=checkbox name=kursiv[$x]></td>";
	print "</tr>";

	$q=db_select("select * from formularer where formular = '$form_nr' and art = '$art_nr' and ya< '1' and beskrivelse != 'generelt' and sprog='$formularsprog' order by xa",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$x++;
		print "<tr>";
		print "<input type=hidden name=id[$x] value=$r[id]>";
		print "<td><SELECT class='inputbox' NAME=beskrivelse[$x]>";
		print "<option>$r[beskrivelse]</option>";
		if ($form_nr<6 || $form_nr==10) {
			print "<option>posnr</option>";
			print "<option value = 'varenr'>".findtekst('917|Varenr.', $sprog_id)."</option>\n";
			print "<option value = 'antal'>".findtekst('916|Antal', $sprog_id)."</option>\n";
			print "<option value = 'beskrivelse'>".findtekst('914|Beskrivelse', $sprog_id)."</option>\n";
			print "<option value = 'pris'>".findtekst('915|Pris', $sprog_id)."</option>\n";
			print "<option value = 'rabat'>".findtekst('428|Rabat', $sprog_id)."</option>\n";
			print "<option value=\"linjemoms\">moms</option>";
			print "<option value=\"varemomssats\">momssats</option>";
			print "<option>linjesum</option>";
			if ($form_nr==3) {
				print "<option>lev_tidl_lev</option>";
				print "<option>lev_antal</option>";
				print "<option>lev_rest</option>";
	 		} 
		}
		else {
			print "<option>dato</option>";
			print "<option>faktnr</option>";
			print "<option>".findtekst('914|Beskrivelse', $sprog_id)."</option>";
			print "<option>beløb</option>";
		}
		print "</SELECT></td>";
		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=xa[$x] value=".str_replace(".",",",round($r['xa'],1))."></td>";
		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=str[$x] value=".round($r['str'],0)."></td>";
		print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=color[$x] value=".round($r['color'],0)."></td>";
		print "<td><SELECT class='inputbox' NAME=justering[$x]>";
		print "<option>$r[justering]</option>";
		print "<option>V</option>";
		print "<option>C</option>";
		print "<option>H</option>";
		print "</SELECT></td>";
		print "<td><SELECT class='inputbox' NAME=form_font[$x]>";
		print "<option>$r[font]</option>";
		print "<option>Helvetica</option>";
		print "<option>Times</option>";
		print "</SELECT></td>";
		if ($r['fed']=='on') {$r['fed']='checked';}
		print "<td align=center><input class='inputbox' type=checkbox name=fed[$x] $r[fed]></td>";
		if ($r['kursiv']=='on') {$r['kursiv']='checked';}
		print "<td align=center><input class='inputbox' type=checkbox name=kursiv[$x] $r[kursiv]></td>";
		if ($r['beskrivelse']=='beskrivelse'){print "<td align=center><input class='inputbox' type='text' style='text-align:right;width:40px;' name=xb[$x] value=".str_replace(".",",",round($r['xb'],1))."></td>";}
		print "</tr>";
	}
	if ($footer) {
	$x++;
		print "<tr><td colspan=11><table><tbody>";
		print "<tr><td colspan=11><hr></td></tr>";
		print "<tr><td></td><td align=center>Tekst</td>";
		print "<td align=center>X</td>";
		print "<td align=center>".findtekst('1790|Højde', $sprog_id)."</td><td align=center> ".findtekst('1786|Farve', $sprog_id)."</td>";
		$span=findtekst('1787|Justering - H: Højrestillet\n C: Centreret\n V: Venstrestillet', $sprog_id);
		print "<td align=center><span title = \"$span\">Just.</span></td><td align=center>Font</td>";
		$span=findtekst('1788|1: Kun side 1\n!1: Alle foruden side 1\nS: Sidste side\n!S: Alle foruden sidste side\nA: Alle sider', $sprog_id);	
		print "<td align=center><span title = \"$span\">Side</span></td>";
		print "<td align=center>Fed</td><td align=center>".findtekst('1789|Kursiv', $sprog_id)."</td>";
		$z=0;
		for ($y=$x;$y<$x+$footer;$y++) {
			$z++;
			$r=db_fetch_array(db_select("select * from formularer where formular = $form_nr and art = '3' and xb='$z' and ya='2' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__));
			print "<input type=hidden name=id[$y] value=\"$r[id]\">\n";
			print "<input type=hidden name=xb[$y] value=\"$z\">\n";
			print "<input type=hidden name=ya[$y] value=\"2\">\n";
			if (!$r['id']) {
				$r['str']='8';$r['color']='0';$r['justering']='V';$r['font']='Helvetica';$r['side']='A';
			}	
			drop_down($y,$form_nr,$art_nr,$formularsprog,$r['id'],$r['beskrivelse'],$r['xa'],$z,"2","-",$r['str'],$r['color'],$r['justering'],$r['font'],$r['fed'],$r['kursiv'],$r['side']);  
			print "\n";
		}
		if (!$menu=='T') print "<tr><td colspan=11><hr></td></tr>";  # 20150331
	 	print "</tbody></table></td></tr>";
		$x=$x+$footer;
	}
	return $x;
} #endfunc pos_linjer		
function kopier_alt($form_nr,$art_nr,$formularsprog,$kilde) {
	if ($form_nr&&$art_nr&&$formularsprog) {
		db_modify("delete from formularer where formular = '$form_nr' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);
		$qtxt="select * from formularer where formular = '$kilde' and sprog='$formularsprog'";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$xa=$r['xa']*1; $ya=$r['ya']*1; $xb=$r['xb']*1; $yb=$r['yb']*1;$str=$r['str']*1;$color=$r['color']*1;
			$qtxt="insert into formularer(formular,art,beskrivelse,justering,xa,ya,xb,yb,str,color,font,fed,kursiv,side,sprog) values	";
			$qtxt.="('$form_nr','$r[art]','".db_escape_string($r['beskrivelse'])."','$r[justering]','$xa','$ya','$xb','$yb','$str','$color',";
			$qtxt.="'$r[font]','$r[fed]','$r[kursiv]','$r[side]','$formularsprog')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
#		print "<meta http-equiv=\"refresh\" content=\"10;URL=formularkort.php?formular=$form_nr&art=$art_nr&sprog=$formularsprog\">";

	}
}

if ($menu=='T') {
	print "";
} elseif ($menu=='S') {
	print "<tr><td width='100%' height='2.5%' align='center' valign='bottom'>\n";		
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>\n";

	print "<td width='38%' align='center' style='$topStyle'></td>\n";

	print "<td width='24%'><a href=\"formular_indlaes_std.php\">";
	print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('572|Genindlæs standardformularer', $sprog_id)."</button></a></td>\n";

	print "<td width='24%' style='$topStyle'></td>\n";

	print "<td width=\"7%\"><title=\"".findtekst('1779|Opret eller nedlæg sprog', $sprog_id)."\"><a href=formularkort.php?nyt_sprog=yes accesskey=\"s\">";
	print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('801|Sprog', $sprog_id)."</button></a></td>\n";

	print "<td width=\"7%\"><title=\"".findtekst('1780|Indlæs eller fjern baggrundsfil', $sprog_id)."\"><a href=logoupload.php?upload=yes accesskey=\"u\">";
	print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('571|Baggrund', $sprog_id)."</button></a></td>\n";
} else {
	print "<tr><td width='100%' height='2.5%' align='center' valign='bottom'>\n";		
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>\n";
	print "<td width='14%' align='center' ".$top_bund.">&nbsp;</td>\n";
	print "<td width='24%' align='center' ".$top_bund.">&nbsp;</td>\n";
	print "<td width='24%' align='center' ".$top_bund." ;>";
	print "<a href=\"formular_indlaes_std.php\">".findtekst('572|Genindlæs standardformularer', $sprog_id)."</a></td>\n";
	print "<td width='24%' ".$top_bund.">&nbsp;</td>\n";
  # 20150331 start bund
		print "<td width=\"7%\" ".$top_bund."><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><span ";
		print "title=\"".findtekst('1779|Opret eller nedlæg sprog', $sprog_id)."\"><a href=formularkort.php?nyt_sprog=yes accesskey=\"s\">".findtekst('801|Sprog', $sprog_id)."</a></span></td>\n";
		print "<td width=\"7%\" ".$top_bund."><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><span ";
		print "title=\"".findtekst('1780|Indlæs eller fjern baggrundsfil', $sprog_id)."\"><a href=logoupload.php?upload=yes accesskey=\"u\">".findtekst('571|Baggrund', $sprog_id)."</a></span></td>\n";
		print "    <td width='14%' ".$top_bund.">&nbsp;</td>\n";
} # 20150331 slut bund
print "    <!-- <td width='10%' ".$top_bund."> ";
print "onClick=\"javascript:window.open('logoslet.php', '','left=10,top=10,width=400,height=200,scrollbars=yes,resizable=yes,menubar=no,location=no')\" ";
print "onMouseOver=\"this.style.cursor = 'pointer'\" ><u>Slet logo</u></td> -->\n";
print "</tbody></table>\n";
print "</td></tr>\n";
print "</tbody></table>\n";
if ($menu=='T') print "</div>\n</div>\n";  # 20150331
print "</body></html>\n";
