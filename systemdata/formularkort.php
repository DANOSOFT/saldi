<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/formularkort --- patch 4.1.1 --- 2026-02-20 --- 
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
// Copyright (c) 2003-2026 Saldi.dk ApS
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
// 20260103 LOE User can now set language based on already defined languages
// 20260220 LOE Background terms now used instead of language terms for clarity, as this is more accurate for what the settings do. The term 'language(sprog)' is still used in the database and code for backwards compatibility, but the user interface now refers to 'backgrounds' instead of 'languages'.
@session_start();
$s_id=session_id();

$title="Formulareditor";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
	
$art=$art_nr=$form_nr=$linjeantal=$nyt_sprog=$submit=$x=$form_sprog_id=NULL;
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

       ######
		if (isset($_POST['slet_sprog']) && $_POST['slet_sprog']) {
			$slet_sprog = if_isset($_POST['slet_sprog']);
			
			// Don't allow deleting "Dansk"
			if ($slet_sprog != 'Dansk') {
				// Delete from formularer table
				db_modify("delete from formularer where sprog = '$slet_sprog'",__FILE__ . " linje " . __LINE__);
				
				// Delete from grupper table
				db_modify("delete from grupper where art = 'VSPR' and box1 = '$slet_sprog'",__FILE__ . " linje " . __LINE__);
				
				// Show confirmation message
				print "<BODY onLoad=\"javascript:alert('$slet_sprog has been deleted!')\">";
				
				// Refresh the page
				print "<meta http-equiv=\"refresh\" content=\"0;URL=formularkort.php?nyt_sprog=yes\">";
				exit;
			} else {
				print "<BODY onLoad=\"javascript:alert('".findtekst('2516|Dansk kan ikke slettes', $sprog_id).".')\">";
			}
		}
	   #####


	if ($nyt_sprog) {
		$nyt_sprog=if_isset($_POST['nyt_sprog']);
		$skabelon=if_isset($_POST['skabelon']);
		if(isset($_POST['gem']) && $_POST['gem']) $handling = 'gem' ;
		if (!$nyt_sprog) {
			if (!$handling && isset($_POST['slet']) && $_POST['slet']) $handling='slet';
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
			$form_sprog_id=$r['kodenr'];
		} else {
			$r=db_fetch_array($q=db_select("select max(kodenr) as kodenr from grupper where art = 'VSPR' ",__FILE__ . " linje " . __LINE__));
			$form_sprog_id=$r['kodenr']+1;
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1) values ('Formular og varesprog','$form_sprog_id','VSPR','$formularsprog')");
		}
	}else{
		$form_sprog_id = 0;
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
							print "<BODY onLoad=\"javascript:alert('Den samlede strenglængde for værdierne ($streng) skal være 14.\\nværdierne er rettet')\">";
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
	print "<div class=\"headerbtnLft\">";
    print "<a class='button blue small' class=\"button red small left\" href=\"formular_indlaes_std.php\">".findtekst('572|Genindlæs standardformularer', $sprog_id)."</a> &nbsp;";
    print "<a title=\"".findtekst('1779|Opret eller nedlæg sprog', $sprog_id)."\" class='button blue small' class=\"button red small left\" href=\"formularkort.php?nyt_sprog=yes\" accesskey=\"s\">Bg.".findtekst('646|Navn', $sprog_id)."</a> &nbsp;";
    print "<a title=\"Email indstillinger for sprog\" class='button blue small' href=\"email_settings.php\" accesskey=\"e\">Email</a></div>\n";
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

	print "<td width='76%' align='center' style='$topStyle'>".findtekst('573|Formularkort', $sprog_id)."</td>\n";

	print "<td width='6%'><span title='".findtekst('1779|Opret eller nedlæg sprog', $sprog_id)."'><a href=formularkort.php?nyt_sprog=yes accesskey='s'>";
	print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">Bg.".findtekst('646|Navn', $sprog_id)."</button></a></span></td>\n";

	print "<td width='6%'><span title='Email indstillinger for sprog'><a href=email_settings.php accesskey='e'>";
	print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">Email</button></a></span></td>\n";

	print "<td width='6%'><span title='".findtekst('1781|Indlæs eller fjern fil', $sprog_id)."'><a href=logoupload.php?upload=yes accesskey='u'>";
	print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('571|Baggrund', $sprog_id)."</button></a></span></td>\n";#20210804

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
	print "<td width=\"6%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><span title=\"".findtekst('1779|Opret eller nedlæg sprog', $sprog_id)."\"><a href=formularkort.php?nyt_sprog=yes accesskey=\"s\">Bg.".findtekst('646|Navn', $sprog_id)."</a></span></td>\n";
	print "<td width=\"6%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><span title=\"".findtekst('1781|Indlæs eller fjern fil', $sprog_id)."\"><a href=logoupload.php?upload=yes accesskey=\"u\">".findtekst('571|Baggrund', $sprog_id)."</a></span></td>\n";#20210804
	print "</tbody></table></td></tr>\n";
}

if ($nyt_sprog) sprog($nyt_sprog,$skabelon,$handling);
print "<tr><td align=center width=100%><table align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";

$formular=array("",
	findtekst('812|Tilbud', $sprog_id),
	findtekst('575|Ordrebekræftelse', $sprog_id),
	findtekst('576|Følgeseddel', $sprog_id),
	findtekst('989|Faktura', $sprog_id),
	findtekst('577|Kreditnota', $sprog_id),
	findtekst('578|Rykker', $sprog_id)." 1",
	findtekst('578|Rykker', $sprog_id)." 2",
	findtekst('578|Rykker', $sprog_id)." 3",
	findtekst('574|Plukliste', $sprog_id),
	"Pos",
	findtekst('515|Kontokort', $sprog_id),
	findtekst('954|Indkøbsforslag', $sprog_id),
	findtekst('579|Rekvisition', $sprog_id),
	findtekst('580|Købsfaktura', $sprog_id)
);

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
print "<option value=\"6\">".findtekst('578|Rykker', $sprog_id)." 1</option>\n";
print "<option value=\"7\">".findtekst('578|Rykker', $sprog_id)." 2</option>\n";
print "<option value=\"8\">".findtekst('578|Rykker', $sprog_id)." 3</option>\n";
print "<option value=\"11\">".findtekst('515|Kontokort', $sprog_id)."</option>";
print "<option value=\"12\">".findtekst('954|Indkøbsforslag', $sprog_id)."</option>";
print "<option value=\"13\">".findtekst('579|Rekvisition', $sprog_id)."</option>";
print "<option value=\"14\">".findtekst('580|Købsfaktura', $sprog_id)."</option>";
# print "<option value=\"10\">Pos</option>";
print "</SELECT></td>\n";
print "<td>&nbsp;Type</td>\n";
print "<td><SELECT class='inputbox' NAME=\"art\">\n";
if ($form_nr && $art) print "<option value=\"$art\">$art_tekst</option>\n";
print "<option value=\"2:Tekster\">".findtekst('581|Tekster', $sprog_id)."</option>\n";
print "<option value=\"3:Ordrelinjer\">".findtekst('582|Ordrelinjer', $sprog_id)."</option>\n";
print "<option value=\"1:Streger\">".findtekst('583|Streger', $sprog_id)."</option>\n";
print "<option value=\"4:Flyt center\">".findtekst('584|Flyt center', $sprog_id)."</option>\n";
print "<option value=\"5:Mail tekst\">".findtekst('585|Mail tekst', $sprog_id)."</option>\n";
print "</SELECT></td>\n";
print "<td>Bg.".findtekst('646|Navn', $sprog_id)."</td>\n";
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

	print "<tr><td colspan=2 align=center>".findtekst('2493|Start', $sprog_id)."</td>";
	print "<td colspan=2 align=center>".findtekst('2494|Slut', $sprog_id)."</td></tr>";
	print "<tr><td align=center>X</td><td align=center>Y</td><td align=center>X</td><td align=center>Y</td>";
	print "<td align=center>".findtekst('2411|Bredde', $sprog_id)."</td><td align=center>".findtekst('1786|Farve', $sprog_id)."</td></tr>";

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
	print "<td align=center><span title = \"$span\">".findtekst('2495|Just.', $sprog_id)."</span></td><td align=center>Font</td>";
	$span=findtekst('1788|1: Kun side 1\n!1: Alle foruden side 1\nS: Sidste side\n!S: Alle foruden sidste side\nA: Alle sider', $sprog_id);	
	print "<td align=center><span title = \"$span\">".findtekst('2496|Side', $sprog_id)."</span></td>";
	print "<td align=center>".findtekst('2497|Fed', $sprog_id)."</td><td align=center>".findtekst('1789|Kursiv', $sprog_id)."</td>";
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
	print "<tr><td colspan=2 align=center>".findtekst('2499|Her har du mulighed for at flytte centreringen på formularen', $sprog_id).".</td></tr>";
	print "<tr><td colspan=2 align=center>".findtekst('2500|Angiv blot det antal mm der skal flyttes hhv. op og til højre', $sprog_id).".</td></tr>";
	print "<tr><td colspan=2 align=center>".findtekst('2501|Anvend negativt fortegn, hvis der skal rykkes ned eller til venstre', $sprog_id).".</td></tr>";
	print "<tr><td colspan=2 align=center></td></tr>";
	print "<tr><td align=center>".findtekst('2508|Op', $sprog_id)."</td><td><input class='inputbox' type='text' style='text-align:right' size=2 name=op></td><tr>";
	print "<tr><td align=center>".findtekst('2510|Højre', $sprog_id)."</td><td><input class='inputbox' type='text' style='text-align:right' size=2 name=hojre></td><tr>";
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
		$max_fields = ($form_nr==1 || $form_nr==2 || $form_nr==4) ? 3 : 2; # Back to original field count
		for ($x=1;$x<=$max_fields;$x++) {
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
	print "<BODY onLoad=\"javascript:alert('".findtekst('2513|Sprogbenævnelse må ikke indeholde specialtegn. Oprettelse af', $sprog_id)." $nyt_sprog ".findtekst('2514|er annulleret', $sprog_id).".')\">";
} elseif ($nyt_sprog && $handling=='gem' && $nyt_sprog!="yes") {

	
	########################
	$tmp=strtolower($nyt_sprog);

		// Check if background exists in grupper table
		$exists_in_grupper = db_fetch_array($q=db_select("select kodenr from grupper where lower(box1) = '$tmp' and art = 'VSPR' ",__FILE__ . " linje " . __LINE__));

		// Check if background exists in formularer table
		$exists_in_formularer = db_fetch_array($q=db_select("select id from formularer where lower(sprog) = '$tmp' limit 1",__FILE__ . " linje " . __LINE__));

		// Only show alert if background exists in BOTH tables
		if ($exists_in_grupper && $exists_in_formularer) {
			print "<BODY onLoad=\"javascript:alert('$nyt_sprog ".findtekst('2512|er allerede oprettet. Oprettelse annulleret', $sprog_id).".')\">";
		} elseif ($skabelon && $handling=='gem') {
			// Insert into grupper if it doesn't exist there
			if (!$exists_in_grupper) {
				$r=db_fetch_array($q=db_select("select max(kodenr) as kodenr from grupper where art = 'VSPR' ",__FILE__ . " linje " . __LINE__));
				$kodenr=$r['kodenr']+1;
				db_modify("insert into grupper (beskrivelse,kodenr,art,box1) values ('sprog','$kodenr','VSPR','$nyt_sprog')",__FILE__ . " linje " . __LINE__);
			}
			
			// Insert into formularer if it doesn't exist there //Can be used for tables with defined sequences, but formularer doesn't have one for some, so we need to manually get the next id to avoid conflicts.
			// if (!$exists_in_formularer) {
			// 	$q=db_select("select * from formularer where sprog = '$skabelon'",__FILE__ . " linje " . __LINE__);
			// 	while ($r=db_fetch_array($q)) {
			// 		$xa=$r['xa']*1; $ya=$r['ya']*1; $xb=$r['xb']*1; $yb=$r['yb']*1;$str=$r['str']*1;$color=$r['color']*1;
			// 		db_modify("insert into formularer(formular,art,beskrivelse,justering,xa,ya,xb,yb,str,color,font,fed,kursiv,side,sprog) values	('$r[formular]','$r[art]','".db_escape_string($r['beskrivelse'])."','$r[justering]','$xa','$ya','$xb','$yb','$str','$color','$r[font]','$r[fed]','$r[kursiv]','$r[side]','".db_escape_string($nyt_sprog)."')",__FILE__ . " linje " . __LINE__);
			// 	}
			// }

			####################
			if (!$exists_in_formularer) {
				// Get current max id to avoid sequence conflicts
				$max_id_row = db_fetch_array(db_select("select MAX(id) as max_id from formularer", __FILE__ . " linje " . __LINE__));
				$next_id = intval($max_id_row['max_id']) + 1;

				$q=db_select("select * from formularer where sprog = '$skabelon'",__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					$xa=$r['xa']*1; $ya=$r['ya']*1; $xb=$r['xb']*1; $yb=$r['yb']*1;$str=$r['str']*1;$color=$r['color']*1;
					db_modify("insert into formularer(id,formular,art,beskrivelse,justering,xa,ya,xb,yb,str,color,font,fed,kursiv,side,sprog) values ('$next_id','$r[formular]','$r[art]','".db_escape_string($r['beskrivelse'])."','$r[justering]','$xa','$ya','$xb','$yb','$str','$color','$r[font]','$r[fed]','$r[kursiv]','$r[side]','".db_escape_string($nyt_sprog)."')",__FILE__ . " linje " . __LINE__);
					$next_id++;
				}
			}


			####################
			
			print "<BODY onLoad=\"javascript:alert('$nyt_sprog ".findtekst('2491|er oprettet', $sprog_id).".')\">";
		}
	########################
} elseif ($skabelon && $handling=='slet') {
	// Prevent deletion of "Dansk"
	if ($skabelon != 'Dansk') {
		db_modify("delete from formularer where sprog = '$skabelon'",__FILE__ . " linje " . __LINE__);
		db_modify("delete from grupper where art = 'VSPR' and box1 = '$skabelon'",__FILE__ . " linje " . __LINE__);
		print "<BODY onLoad=\"javascript:alert('$skabelon er slettet!')\">";
	} else {
		print "<BODY onLoad=\"javascript:alert('Danish cannot be deleted.')\">";
	}
} else {
	
	print "<form name=formularvalg action=$_SERVER[PHP_SELF]?nyt_sprog=yes method=\"post\">";
    print "<tr><td width=100% align=center><table border=0><tbody>";

    // Free-text input: user types their own background/sprog name
    print "<tr><td>Enter a background name you want to add:</td><td>";
    print "<input class='inputbox' type='text' name='nyt_sprog' size='20'>";
    print "</td></tr>";

    // Template dropdown (also used for deletion)
    print "<tr><td>Background (Select template):</td>";
    print "<td><SELECT class='inputbox' NAME='skabelon'>";
    $q = db_select("select distinct sprog from formularer order by sprog", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        print "<option value='" . htmlspecialchars($r['sprog'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($r['sprog'], ENT_QUOTES, 'UTF-8') . "</option>";
    }
    print "<option value=''></option>";
    print "</SELECT></td></tr>";

    // Buttons
    print "<tr><td colspan=2 align=center>";
    print "<input type=submit accesskey='g' name='gem' value='" . findtekst('3|Gem', $sprog_id) . "'>&nbsp;";
    print "<input type=submit accesskey='s' name='slet' value='" . findtekst('1099|Slet', $sprog_id) . "' onclick=\"return confirm('" . findtekst('2492|Slet det valgte sprog', $sprog_id) . "?')\">&nbsp;";
    print "<input type=submit accesskey='f' name='fortryd' value='" . findtekst('159|Fortryd', $sprog_id) . "'></td></tr>";
    print "</tbody></table></td></tr>";
    exit;
}	

} # endfunc sprog

function drop_down($x,$form_nr,$art_nr,$formularsprog,$id,$beskrivelse,$xa,$xb,$ya,$yb,$str,$color,$justering,$font,$fed,$kursiv,$side){
	global $sprog_id;
	
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
	print "<option value = 'eget_firmanavn'>".findtekst('2518|Eget', $sprog_id)." ".strtolower(findtekst('28|Firmanavn', $sprog_id))."</option>";                                  #Eget firmanavn
	print "<option value = 'egen_addr1'>".findtekst('2517|Egen', $sprog_id)." ".strtolower(findtekst('44|Adresse 1', $sprog_id))."</option>";                          #Egen adresse 1
	print "<option value = 'egen_addr2'>".findtekst('2517|Egen', $sprog_id)." ".strtolower(findtekst('45|Adresse 2', $sprog_id))."</option>";                          #Egen adresse 2
	print "<option value = 'eget_postnr'>".findtekst('2518|Eget', $sprog_id)." ".strtolower(findtekst('36|Postnr.', $sprog_id))."</option>";                                       #Eget postnr.
	print "<option value = 'eget_bynavn'>".findtekst('2518|Eget', $sprog_id)." ".strtolower(findtekst('910|Bynavn', $sprog_id))."</option>";                                       #Eget bynavn
	print "<option value = 'eget_land'>".findtekst('2518|Eget', $sprog_id)." ".strtolower(findtekst('47|Land', $sprog_id))."</option>";                                            #Eget land
	print "<option value = 'eget_cvrnr'>".findtekst('2518|Eget', $sprog_id)." ".strtolower(findtekst('48|Cvr-nr.', $sprog_id))."</option>";                                        #Eget Cvr nr.
	print "<option value = 'egen_tlf'>".findtekst('2517|Egen', $sprog_id)." ".strtolower(findtekst('49|Tlf', $sprog_id))."</option>";                                               #Egen tlf
	print "<option value = 'egen_fax'>".findtekst('2517|Egen', $sprog_id)." ".strtolower(findtekst('50|Fax', $sprog_id))."</option>";                                              #Egen fax
	print "<option value = 'egen_bank_navn'>".findtekst('2518|Eget', $sprog_id)." ".strtolower(findtekst('58|Banknavn', $sprog_id))."</option>";                                   #Eget banknavn
	print "<option value = 'egen_bank_reg'>".findtekst('2517|Egen', $sprog_id)." bank_reg</option>";
	print "<option value = 'egen_bank_konto'>".findtekst('2517|Egen', $sprog_id)." ".strtolower(findtekst('60|Bankkonto', $sprog_id))."</option>";                                 #Egen bankkonto
	print "<option value = 'egen_email'>".findtekst('2517|Egen', $sprog_id)." ".strtolower(findtekst('52|E-mail', $sprog_id))."</option>";                                         #Egen e-mail
	print "<option value = 'egen_web'>".findtekst('2517|Egen', $sprog_id)." web</option>";                                                                                         #Egen web
	if ($form_nr<6  || $form_nr==10 || $form_nr>=12) {
		print "<option value = 'ansat_initialer'>".findtekst('589|Ansat', $sprog_id)." ".strtolower(findtekst('647|Initialer', $sprog_id))."</option>";                            #Ansat initialer
		print "<option value = 'ansat_navn'>".findtekst('589|Ansat', $sprog_id)." ".strtolower(findtekst('138|Navn', $sprog_id))."</option>";                                      #Ansat navn
		print "<option value = 'ansat_addr1'>".findtekst('589|Ansat', $sprog_id)." ".strtolower(findtekst('44|Adresse 1', $sprog_id))."</option>";                                 #Ansat adresse 1
		print "<option value = 'ansat_addr2'>".findtekst('589|Ansat', $sprog_id)." ".strtolower(findtekst('45|Adresse 2', $sprog_id))."</option>";                                 #Ansat adresse 2
		print "<option value = 'ansat_postnr'>".findtekst('589|Ansat', $sprog_id)." ".strtolower(findtekst('36|Postnr.', $sprog_id))."</option>";                                  #Ansat postnr.
		print "<option value = 'ansat_by'>".findtekst('589|Ansat', $sprog_id)." ".strtolower(findtekst('146|By', $sprog_id))."</option>";                                          #Ansat by
		print "<option value = 'ansat_email'>".findtekst('589|Ansat', $sprog_id)." ".strtolower(findtekst('52|E-mail', $sprog_id))."</option>";                                    #Ansat e-mail
		print "<option value = 'ansat_mobil'>".findtekst('589|Ansat', $sprog_id)." ".strtolower(findtekst('401|Mobil', $sprog_id))."</option>";                                    #Mobil
		print "<option value = 'ansat_tlf'>".findtekst('589|Ansat', $sprog_id)." ".strtolower(findtekst('49|Tlf', $sprog_id))."</option>";                                          #Ansat tlf
		print "<option value = 'ansat_fax'>".findtekst('589|Ansat', $sprog_id)." ".strtolower(findtekst('58|Banknavn', $sprog_id))."</option>";                                    #Ansat banknavn
		print "<option value = 'ansat_privattlf'>".findtekst('589|Ansat', $sprog_id)." ".strtolower(findtekst('656|Privat tlf', $sprog_id))."</option>";                           #Ansat privat tlf
	} elseif ($form_nr==11) {
		print "<option value = 'konto_firmanavn'>".findtekst('440|Konto', $sprog_id)." ".strtolower(findtekst('28|Firmanavn', $sprog_id))."</option>";                             #Konto firmanavn
		print "<option value = 'konto_addr1'>".findtekst('440|Konto', $sprog_id)." ".strtolower(findtekst('44|Adresse 1', $sprog_id))."</option>";                                 #Konto adresse 1
		print "<option value = 'konto_addr2'>".findtekst('440|Konto', $sprog_id)." ".strtolower(findtekst('45|Adresse 2', $sprog_id))."</option>";                                 #Konto adresse 2
		print "<option value = 'konto_postnr'>".findtekst('440|Konto', $sprog_id)." ".strtolower(findtekst('36|Postnr.', $sprog_id))."</option>";                                  #Konto postnr.
		print "<option value = 'konto_bynavn'>".findtekst('440|Konto', $sprog_id)." ".strtolower(findtekst('910|Bynavn', $sprog_id))."</option>";                                  #Konto bynavn
		print "<option value = 'konto_land'>".findtekst('440|Konto', $sprog_id)." ".strtolower(findtekst('47|Land', $sprog_id))."</option>";                                       #Konto land
		print "<option value = 'konto_kontakt'>".findtekst('440|Konto', $sprog_id)." ".strtolower(findtekst('398|Kontakt', $sprog_id))."</option>";                                #Konto kontakt
		print "<option value = 'konto_cvrnr'>".findtekst('440|Konto', $sprog_id)." ".strtolower(findtekst('48|Cvr-nr.', $sprog_id))."</option>";                                   #Konto cvr nr.
		print "<option value = 'konto_valuta'>".findtekst('440|Konto', $sprog_id)." ".strtolower(findtekst('776|Valuta', $sprog_id))."</option>";                                  #Konto valuta
	}	
	if ($form_nr!=11) {
		print "<option value = 'ordre_firmanavn'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('28|Firmanavn', $sprog_id))."</option>";                             #Ordre firmanavn
		print "<option value = 'ordre_addr1'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('44|Adresse 1', $sprog_id))."</option>";                                 #Ordre adresse 1
		print "<option value = 'ordre_addr2'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('45|Adresse 2', $sprog_id))."</option>";                                 #Ordre adresse 2
		print "<option value = 'ordre_postnr'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('36|Postnr.', $sprog_id))."</option>";                                  #Ordre postnr.
		print "<option value = 'ordre_bynavn'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('910|Bynavn', $sprog_id))."</option>";                                  #Ordre bynavn
		print "<option value = 'ordre_land'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('47|Land', $sprog_id))."</option>";                                       #Ordre land
		print "<option value = 'ordre_kontakt'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('398|Kontakt', $sprog_id))."</option>";                                #Ordre kontakt
		print "<option value = 'ordre_cvrnr'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('48|Cvr-nr.', $sprog_id))."</option>";                                   #Ordre cvr nr.
	}
	if ($form_nr<6 || $form_nr==10 || $form_nr>=12) {
		print "<option value = 'ordre_ean'>".findtekst('605|Ordre', $sprog_id)." EAN</option>";                                                                                    #Ordre EAN
		print "<option value = 'ordre_felt_1'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('543|Felt', $sprog_id))." 1</option>";                                  #Ordre felt 1
		print "<option value = 'ordre_felt_2'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('543|Felt', $sprog_id))." 2</option>";                                  #Ordre felt 2
		print "<option value = 'ordre_felt_3'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('543|Felt', $sprog_id))." 3</option>";                                  #Ordre felt 3
		print "<option value = 'ordre_felt_4'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('543|Felt', $sprog_id))." 4</option>";                                  #Ordre felt 4
		print "<option value = 'ordre_felt_5'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('543|Felt', $sprog_id))." 5</option>";                                  #Ordre felt 5
		print "<option value = 'ordre_institution'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('55|Institution', $sprog_id))."</option>";                         #Ordre institution
		print "<option value = 'ordre_kundeordnr'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('2519|Kundeordrenummer', $sprog_id))."</option>";                   #Ordre kundeordrenummer
		print "<option value = 'ordre_lev_navn'>".findtekst('605|Ordre', $sprog_id)."_lev_navn</option>";                                                                          #Ordre 
		print "<option value = 'ordre_lev_addr1'>".findtekst('605|Ordre', $sprog_id)."_lev_addr1</option>";                                                                        #Ordre 
		print "<option value = 'ordre_lev_addr2'>".findtekst('605|Ordre', $sprog_id)."_lev_addr2</option>";                                                                        #Ordre 
		print "<option value = 'ordre_lev_postnr'>".findtekst('605|Ordre', $sprog_id)."_lev_postnr</option>";                                                                      #Ordre 
		print "<option value = 'ordre_lev_bynavn'>".findtekst('605|Ordre', $sprog_id)."_lev_bynavn</option>";                                                                      #Ordre 
		print "<option value = 'ordre_lev_kontakt'>".findtekst('605|Ordre', $sprog_id)."_lev_kontakt</option>";                                                                    #Ordre 
		print "<option value = 'ordre_levdate'>".findtekst('605|Ordre', $sprog_id)."_levdate</option>";                                                                            #Ordre 
		print "<option value = 'ordre_momssats'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('1095|Momssats', $sprog_id))."</option>";                             #Ordre momssats
		print "<option value = 'ordre_notes'>".findtekst('605|Ordre', $sprog_id)." ".findtekst('1888|noter', $sprog_id)."</option>";                                               #Ordre noter
		print "<option value = 'ordre_ordredate'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('881|Ordredato', $sprog_id))."</option>";                            #Ordre ordredato
		print "<option value = 'ordre_ordrenr'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('500|Ordrenr.', $sprog_id))."</option>";                               #Ordre ordrenr.
		print "<option value = 'ordre_projekt'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('553|Projekt', $sprog_id))."</option>";                                #Ordre projekt
		print "<option value = 'ordre_valuta'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('776|Valuta', $sprog_id))."</option>";                                  #Ordre valuta
	}	
	if ($form_nr==4 || $form_nr==5 || $form_nr==13) {
		print "<option value = 'ordre_fakturanr'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('828|Fakturanr.', $sprog_id))."</option>";                           #Ordre fakturanr.
		print "<option value = 'ordre_fakturadate'>".findtekst('605|Ordre', $sprog_id)." ".strtolower(findtekst('1094|Fakturadato', $sprog_id))."</option>";                       #Ordre fakturadato
	}	
	if ($form_nr==4) print "<option value = 'formular_forfaldsdato'>".findtekst('2520|Formular', $sprog_id)." ".strtolower(findtekst('1164|Forfaldsdato', $sprog_id))."</option>"; #Formular forfaldsdato
	print "<option value = 'formular_side'>".findtekst('2520|Formular', $sprog_id)." side</option>";
	print "<option value = 'formular_nextside'>".findtekst('2520|Formular', $sprog_id)." nextside</option>";
	print "<option value = 'formular_preside'>".findtekst('2520|Formular', $sprog_id)." preside</option>";
	print "<option value = 'formular_transportsum'>".findtekst('2520|Formular', $sprog_id)." ".strtolower(findtekst('2521|Transportsum', $sprog_id))."</option>";                  #Formular transportsum
	print "<option value = 'formular_betalingsid(9,5)'>".findtekst('2520|Formular', $sprog_id)." betalingsid(9,5)</option>";
	if ($form_nr<6 || $form_nr==10 || $form_nr>=12) {
		print "<option value = 'formular_moms'>".findtekst('2520|Formular', $sprog_id)." moms</option>";
		print "<option value = 'formular_momsgrundlag'>".findtekst('2520|Formular', $sprog_id)." momsgrundlag</option>";
	}
	print "<option value = 'formular_ialt'>".findtekst('2520|Formular', $sprog_id)." ialt</option>";
	if ($form_nr==3) {
		print "<option value = 'levering_lev_nr'>levering_lev_nr</option>";
		print "<option value = 'levering_salgsdate'>levering_salgsdate</option>";
		print "<option value = 'formular_grossWeight'>".findtekst('2520|Formular', $sprog_id)." bruttovægt</option>\n";
		print "<option value = 'formular_netWeight'>".findtekst('2520|Formular', $sprog_id)." nettovægt</option>\n";
	} 
	if ($form_nr>=6) {
		print "<option value = 'forfalden_sum'>forfalden_sum</option>";
		print "<option value = 'rykker_gebyr'>rykker_gebyr</option>";
	}
	print "<option>afdeling_note</option>";
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
	print "<tr><td></td><td></td><td align=center>".findtekst('2502|Linjeantal', $sprog_id)."</td>\n";
	print "<td align=center>Y</td>\n";
	print "<td align=center>".findtekst('2503|Linjeafst.', $sprog_id)."</td></tr>\n";
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
	print "<td align=center>".findtekst('1790|Højde', $sprog_id)."</td><td align=center>".findtekst('1786|Farve', $sprog_id)."</td>\n";
	print "<td align=center>".findtekst('2495|Just.', $sprog_id)."</td><td align=center>Font</td><td align=center>".findtekst('2497|Fed', $sprog_id)."</td>\n";
	print "<td align=center>".findtekst('1789|Kursiv', $sprog_id)."</td><td align=center>".findtekst('2504|Tekstlængde', $sprog_id)."</td></tr>\n";

$x=0;
	print "<tr>\n";
	print "<td><SELECT class='inputbox' NAME=beskrivelse[$x]>\n";
	if ($form_nr<6 || $form_nr==9 || ($form_nr>=12 && $form_nr<=14)) {
		print "<option value = 'posnr'>".findtekst('2178|Pos nr.', $sprog_id)."</option>\n";
		print "<option value = 'varenr'>".findtekst('917|Varenr.', $sprog_id)."</option>\n";
		print "<option value = 'lev_varenr'>".findtekst('952|Lev. varenr.', $sprog_id)."</option>\n";
		print "<option value = 'antal'>".findtekst('916|Antal', $sprog_id)."</option>\n";
		print "<option value = 'enhed'>".findtekst('945|Enhed', $sprog_id)."</option>\n";
		print "<option value = 'beskrivelse'>".findtekst('914|Beskrivelse', $sprog_id)."</option>\n";
		print "<option value = 'pris'>".findtekst('915|Pris', $sprog_id)."</option>\n";
		print "<option value = 'rabat'>".findtekst('428|Rabat', $sprog_id)."</option>\n";
		print "<option value = 'momssats'>".findtekst('1095|Momssats', $sprog_id)."</option>\n";
		if ($procentfakt) print "<option value = 'procent'>".findtekst('1481|Procent', $sprog_id)."</option>";
		print "<option value = 'linjemoms'>".findtekst('2505|Linjemoms', $sprog_id)."</option>";
		print "<option value = 'varemomssats'>".findtekst('2506|Varemomssats', $sprog_id)."</option>";
		print "<option value = 'linjesum'>".findtekst('2507|Linjesum', $sprog_id)."</option>\n";
		print "<option value = 'projekt'>".findtekst('553|Projekt', $sprog_id)."</option>\n";
		print "<option>procent</option>\n"; #20140709
		print "<option value = 'lokation'>".findtekst('2045|Lokation', $sprog_id)."</option>\n";
		print "<option value = 'trademark'>trademark</option>\n";
		if ($form_nr==3) {
			print "<option>lev_tidl_lev</option>\n";
			print "<option>lev_antal</option>\n";
			print "<option>lev_rest</option>\n";
			print "<option value = 'lokation'>".findtekst('2045|Lokation', $sprog_id)."</option>\n";
			print "<option>vare_note</option>\n";
		} 
		if ($form_nr==9) {
			print "<option>leveres</option>\n";
			print "<option value = 'lokation'>".findtekst('2045|Lokation', $sprog_id)."</option>\n";
			print "<option>lev_antal</option>\n";
			print "<option>lev_rest</option>\n";
			print "<option>Fri tekst</option>\n";
		} 
	} elseif ($form_nr==11) {
		print "<option value = 'beskrivelse'>".findtekst('914|Beskrivelse', $sprog_id)."</option>";
		print "<option value = 'dato'>".findtekst('438|Dato', $sprog_id)."</option>";
		print "<option value = 'debet'>".findtekst('1000|Debet', $sprog_id)."</option>";
		print "<option value = 'faktnr'>".findtekst('882|Fakt. nr.', $sprog_id)."</option>";
		print "<option value = 'forfaldsdato'>".findtekst('1164|Forfaldsdato', $sprog_id)."</option>";
		print "<option value = 'kredit'>".findtekst('1001|Kredit', $sprog_id)."</option>";
		print "<option value = 'saldo'>".findtekst('1073|Saldo', $sprog_id)."</option>";
	} else {
		print "<option value = 'dato'>".findtekst('438|Dato', $sprog_id)."</option>\n";
		print "<option value = 'faktnr'>".findtekst('882|Fakt. nr.', $sprog_id)."</option>\n";
		print "<option value = 'beskrivelse'>".findtekst('914|Beskrivelse', $sprog_id)."</option>\n";
		print "<option value = 'beløb'>".findtekst('934|Beløb', $sprog_id)."</option>\n";
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
	print "<tr><td></td><td></td><td align=cente>".findtekst('2515|Toplinjer', $sprog_id)."</td>";
	print "<td align=center>".findtekst('2516|Bundlinjer', $sprog_id)."</td>";
	print "<td align=center>".findtekst('2503|Linjeafst.', $sprog_id)."</td></tr>";
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
		print "<td align=center><span title = \"$span\">".findtekst('2495|Just.', $sprog_id)."</span></td><td align=center>Font</td>";
		$span=$span=findtekst('1788|1: Kun side 1\n!1: Alle foruden side 1\nS: Sidste side\n!S: Alle foruden sidste side\nA: Alle sider', $sprog_id);	#20210804
		print "<td align=center><span title = \"$span\">".findtekst('2496|Side', $sprog_id)."</span></td>";
		print "<td align=center>".findtekst('2497|Fed', $sprog_id)."</td><td align=center>".findtekst('1789|Kursiv', $sprog_id)."</td>";
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
	print "<td align=center>".findtekst('1790|Højde', $sprog_id)."</td><td align=center> ".findtekst('1786|Farve', $sprog_id)."</td>";
	print "<td align=center>".findtekst('2495|Just.', $sprog_id)."</td><td align=center>Font</td><td align=center> ".findtekst('2497|Fed', $sprog_id)."</td>";
	print "<td align=center>".findtekst('1789|Kursiv', $sprog_id)."</td><td align=center> ".findtekst('2504|Tekstlængde', $sprog_id)."</td></tr>";
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
	print "<option value = 'linjemoms'>".findtekst('2505|Linjemoms', $sprog_id)."</option>";
	print "<option value = 'varemomssats'>".findtekst('2506|Varemomssats', $sprog_id)."</option>";
	print "<option value = 'linjesum'>".findtekst('2507|Linjesum', $sprog_id)."</option>";
	print "<option value = 'projekt'>".findtekst('553|Projekt', $sprog_id)."</option>";
	print "</SELECT></td>";
	print "<input type=hidden style='text-align:right;width:40px;' name=ya[$x] value='0'>";
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
			print "<option value = 'linjemoms'>".findtekst('2505|Linjemoms', $sprog_id)."</option>";
			print "<option value = 'varemomssats'>".findtekst('2506|Varemomssats', $sprog_id)."</option>";
			print "<option value = 'linjesum'>".findtekst('2507|Linjesum', $sprog_id)."</option>";
			if ($form_nr==3) {
				print "<option>lev_tidl_lev</option>";
				print "<option>lev_antal</option>";
				print "<option>lev_rest</option>";
	 		} 
		}
		else {
			print "<option><option value = 'dato'>".findtekst('438|Dato', $sprog_id)."</option></option>";
			print "<option><option value = 'faktnr'>".findtekst('882|Fakt. nr.', $sprog_id)."</option></option>";
			print "<option value = 'beskrivelse'>".findtekst('914|Beskrivelse', $sprog_id)."</option>";
			print "<option value = 'beløb'>".findtekst('934|Beløb', $sprog_id)."</option>";
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
		print "<td align=center><span title = \"$span\">".findtekst('2495|Just.', $sprog_id)."</span></td><td align=center>Font</td>";
		$span=findtekst('1788|1: Kun side 1\n!1: Alle foruden side 1\nS: Sidste side\n!S: Alle foruden sidste side\nA: Alle sider', $sprog_id);	
		print "<td align=center><span title = \"$span\">".findtekst('2496|Side', $sprog_id)."</span></td>";
		print "<td align=center>".findtekst('2497|Fed', $sprog_id)."</td><td align=center>".findtekst('1789|Kursiv', $sprog_id)."</td>";
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
	print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">Bg.".findtekst('646|Navn', $sprog_id)."</button></a></td>\n";

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
		print "title=\"".findtekst('1779|Opret eller nedlæg sprog', $sprog_id)."\"><a href=formularkort.php?nyt_sprog=yes accesskey=\"s\">Bg.".findtekst('646|Navn', $sprog_id)."</a></span></td>\n";
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

function get_background_file($form_type, $sprog) {
    global $db_id;
    
    $sprog_prefix = ($sprog != 'Dansk') ? $sprog . "_" : "";
    $background_file = "../logolib/$db_id/{$sprog_prefix}{$form_type}_bg.pdf";
    
    // If background-specific file doesn't exist, try default
    if (!file_exists($background_file)) {
        $background_file = "../logolib/$db_id/{$form_type}_bg.pdf";
    }
    
    // If form-specific doesn't exist, try general background
    if (!file_exists($background_file)) {
        $background_file = "../logolib/$db_id/{$sprog_prefix}bg.pdf";
        if (!file_exists($background_file)) {
            $background_file = "../logolib/$db_id/bg.pdf";
        }
    }
    
    return file_exists($background_file) ? $background_file : null;
}