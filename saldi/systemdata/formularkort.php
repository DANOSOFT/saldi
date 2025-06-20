<?php
// -------systemdata/formularkort------------------- lap 3.6.1 -- 2016-01-11 --
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2016 DANOSOFT ApS
// ----------------------------------------------------------------------------

// 2012.09.06 Tilføjet mulighed for at vise momssats på ordrelinjer.
// 2013.02.12 Tilføjet linjemoms og varemomssats, søg linjemoms eller varemomssats 
// 2013.02.21 Tilføjet kontokort (formular 11)
// 2013.08.15 Tilføjet Indkøbsforslag, Rekvisision & Købsfaktura (formular 12,13,14)
// 2013.11.21 Mulighed for at tilføje navn på vedhæftet bilag i 'mail-tekst' for Tilbud,Ordrer og Fakture
// 2013.11.21 Div. rettelser i html. Indsat meta i head, så ÆØÅ vises korekt. Ensrettet font i top og bund. Ændret 'Logo' til 'Upload', og fjernet 'Slet logo' i bunden
// 2013.11.21 Nye tekster skrevet ind i tekster.csv (671,672) til bilag
// 2014.01.24 #1 Tilføjet *1 for at sikre at værdi er numerisk Søg 20140124
// 2014.07.09 PK - Indsat procent i ordrelinjer. Søg #20140709
// 2014.09.02 Phr -indsat 'and formular='$form_nr'' da gebyr bleve slette i alle formularer ved gemning af formular uden gebyr.
// 2015.01.17	Phr - Merget med version fra jan. 14 som var blevet overskrevet 2014.07.09.
// 20150331 CA  Topmenudesign tilføjet                             søg 20150331
// 20160111 PHR Tilføjet lev_varenr til ordrelinjer  søg 'lev_varenr'


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
	if ($nyt_sprog) {
		$nyt_sprog=if_isset($_POST['nyt_sprog']);
		$skabelon=if_isset($_POST['skabelon']);
		$handling=if_isset($_POST['gem']);
		if (!$nyt_sprog) {
			$handling=if_isset($_POST['gem']);
			if (!$handling) $handling=if_isset($_POST['slet']);
			if (!$handling) $handling=if_isset($_POST['fortryd']);
			if ($handling == 'slet') $nyt_sprog='slet';
#			echo "handlin	$handling<br>";	
#exit;	
		}
	}
	$formular=if_isset($_POST['formular']);
#	$form_tekst=if_isset($_POST['form_tekst']);
	$form_nr=if_isset($_POST['form_nr']);
	$formularsprog=db_escape_string(if_isset($_POST['sprog']));
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
		$str=$_POST['str'];
		$color=$_POST['color'];
		$form_font=$_POST['form_font'];
		$fed=$_POST['fed'];
		$justering=$_POST['justering'];
		$kursiv=$_POST['kursiv'];
		$side=$_POST['side'];
		$linjeantal=$_POST['linjeantal'];
		$gebyr=$_POST['gebyr'];
		$rentevnr=$_POST['rentevnr'];
		$rentesats=$_POST['rentesats'];
	}
	
	list($art_nr, $art_tekst)=explode(":", $art);
#	list($form_nr, $form_tekst)=explode(":", $formular);
	
	#tjekker om sprog_id er sat og hvis ikke, oprettes sprog_id
	if ($formularsprog && $formularsprog!='Dansk') {
		if ($r=db_fetch_array($q=db_select("select kodenr from grupper where art = 'VSPR' and box1='$formularsprog'",__FILE__ . " linje " . __LINE__))) {
			$sprog_id=$r['kodenr'];
		} else {
			$r=db_fetch_array($q=db_select("select max(kodenr) as kodenr from grupper where art = 'VSPR' ",__FILE__ . " linje " . __LINE__));
			$sprog_id=$r['kodenr']+1;
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1) values ('Formular og varesprog','$sprog_id','VSPR','$formularsprog')");
	}
	}
	
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
		$tmp=strtoupper($gebyr);
		if ($r1=db_fetch_array(db_select("select id,varenr from varer where upper(varenr) = '$tmp'",__FILE__ . " linje " . __LINE__))) { 
			$gebyr=$r1['varenr'];
			if ($r2=db_fetch_array(db_select("select id from formularer where beskrivelse ='GEBYR' and formular='$form_nr' and art=2 and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__))) {
				db_modify("update formularer set xb='$r1[id]' where id = $r2[id]",__FILE__ . " linje " . __LINE__);
			}	else {
					db_modify("insert into formularer (beskrivelse, formular, art, xb, sprog) values ('GEBYR', '$form_nr', '2', '$r1[id]', '$formularsprog')",__FILE__ . " linje " . __LINE__);
				}
		} else print "<BODY onLoad=\"javascript:alert('Varenummeret $gebyr findes ikke i varelisten')\">";
	} elseif (($submit=='Opdater')&&($form_nr>=6)&&($form_nr<=8)&&($art_nr==2)&&(!$gebyr)) db_modify("delete from formularer where beskrivelse = 'GEBYR' and formular='$form_nr' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__); #20140902
	if ($submit=='Opdater' && $form_nr>=6 && $form_nr<=8 && $art_nr==2 && $rentevnr) { #Rykkerrenter
		$tmp=strtoupper($rentevnr);
		$rentesats=usdecimal($rentesats);
		if ($r1=db_fetch_array(db_select("select id, varenr from varer where upper(varenr) = '$tmp'",__FILE__ . " linje " . __LINE__))) { 
			$rentevnr=$r['varenr'];
			if ($r2=db_fetch_array(db_select("select id from formularer where beskrivelse ='GEBYR' and formular='$form_nr' and art=2 and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__))) {
				db_modify("update formularer set yb='$r1[id]', str='$rentesats' where id = $r2[id]",__FILE__ . " linje " . __LINE__);
			}	else {
					db_modify("insert into formularer (beskrivelse, formular, art, yb, str, sprog) values ('GEBYR', '$form_nr', '2', '$r1[id]', '$rentesats', '$formularsprog')",__FILE__ . " linje " . __LINE__);
				}
		} else print "<BODY onLoad=\"javascript:alert('Varenummeret $gebyr findes ikke i varelisten')\">";
	} elseif (($submit=='Opdater')&&($form_nr==6)&&($art_nr==2)&&(!$gebyr)) db_modify("delete from formularer where beskrivelse = 'GEBYR' and formular='$form_nr' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__); #20140902
	if ($_POST['linjer']){
		transaktion('begin');
		for ($x=0; $x<=$linjeantal; $x++) {
			if ((trim($xa[$x])=='-')&&($id[$x])&&($beskrivelse[$x]!='LOGO')) {db_modify("delete from formularer where id =$id[$x] and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);}
			else {
				if ($beskrivelse[$x]=='LOGO' && !$id[$x] && $xa[$x] && $ya[$x]) {
					db_modify("insert into formularer (beskrivelse,formular,art,xa,ya,sprog) values ('$beskrivelse[$x]',$form_nr,$art_nr,$xa[$x],$ya[$x],'$formularsprog')",__FILE__ . " linje " . __LINE__);
				}
				if ($art==5 && $xa[$x]==2) {
					$beskrivelse[$x]=str_replace("\n","<br>",$beskrivelse[$x]); 
				}
				$beskrivelse[$x]=db_escape_string($beskrivelse[$x]);
				if ($ny_beskrivelse[$x]) {
					$beskrivelse[$x]=trim($beskrivelse[$x]." $".$ny_beskrivelse[$x].";");
				}
				$xa[$x]=$xa[$x]*1; $ya[$x]=$ya[$x]*1; $xb[$x]=$xb[$x]*1; $yb[$x]=$yb[$x]*1; $str[$x]=$str[$x]*1; $color[$x]=$color[$x]*1;
				if ($x==0||(!$id[$x] && (($art_nr==5) || $form_nr==10))) {
#echo "$ny_beskrivelse[$x]<br>";
					if ($xa[$x]>0) {
						if (($art!='1') && ($str[$x]<=1)) $str[$x]=10;
						db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('$beskrivelse[$x]', $form_nr, $art_nr, $xa[$x], $ya[$x], $xb[$x], $yb[$x], $str[$x], $color[$x], '$form_font[$x]', '$fed[$x]', '$kursiv[$x]', '$side[$x]', '$justering[$x]', '$formularsprog')",__FILE__ . " linje " . __LINE__);
					} elseif (substr($ny_beskrivelse[$x],0,10)=="kopier_alt") {
						$tmp=substr($ny_beskrivelse[$x],-1);
						kopier_alt($form_nr,$art_nr,$formularsprog,$tmp);
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
					db_modify("update formularer set beskrivelse='$beskrivelse[$x]', xa=$xa[$x], ya=$ya[$x], xb=$xb[$x], yb=$yb[$x], str=$str[$x], color=$color[$x], font='$form_font[$x]', fed='$fed[$x]', kursiv='$kursiv[$x]', side='$side[$x]', justering='$justering[$x]'	where id = $id[$x]",__FILE__ . " linje " . __LINE__);
				}
			} 
		}
		transaktion('commit');	 
	}
}
#}

if ($menu=='T') {  # 20150331 start
        include_once '../includes/top_header.php';
        include_once '../includes/top_menu.php';
        print "<div id=\"header\">\n";
        print "<div class=\"headerbtnLft\"></div>\n";
        print "</div><!-- end of header -->";
        print "<div id=\"leftmenuholder\">";
        include_once 'left_menu.php';
        print "</div><!-- end of leftmenuholder -->\n";
        print "<div class=\"maincontent\">\n";
        print "<table border=\"1\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\"><tbody>";
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
	print "<td width=\"12%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=$returside accesskey=\"l\">Luk</a></td>\n";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Formularkort</td>\n";
	print "<td width=\"6%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><span title=\"Opret eller nedl&aelig;g sprog\"><a href=formularkort.php?nyt_sprog=yes accesskey=\"s\">Sprog</a></span></td>\n";
	print "<td width=\"6%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><span title=\"Indl&aelig;s eller fjern fil\"><a href=logoupload.php?upload=yes accesskey=\"u\">Upload</a></span></td>\n";
	print "</tbody></table></td></tr>\n";
}
if ($nyt_sprog) sprog($nyt_sprog,$skabelon,$handling);
print "<tr><td align=center width=100%><table align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";

$formular=array("","Tilbud","Ordrebekr&aelig;ftelse","F&oslash;lgeseddel","Faktura","Kreditnota","Rykker_1","Rykker_2","Rykker_3","Plukliste","Pos","Kontokort","Indkøbsforslag","Rekvisition","Købsfaktura");

print "<tr><td colspan=\"10\" align=\"center\"><table><tbody>\n";
print "<form name=\"formularvalg\" action=\"$_SERVER[PHP_SELF]\" method=\"post\">\n";
print "<tr><td>Formular</td>\n";
print "<td><SELECT class=\"inputbox\" NAME=\"form_nr\">\n";
if ($form_nr) print "<option value=\"$form_nr\">$formular[$form_nr]</option>\n";
print "<option value=\"1\">Tilbud</option>\n";
print "<option value=\"9\">Plukliste</option>\n";
print "<option value=\"2\">Ordrebekr&aelig;ftelse</option>\n";
print "<option value=\"3\">F&oslash;lgeseddel</option>\n";
print "<option value=\"4\">Faktura</option>\n";
print "<option value=\"5\">Kreditnota</option>\n";
print "<option value=\"6\">Rykker_1</option>\n";
print "<option value=\"7\">Rykker_2</option>\n";
print "<option value=\"8\">Rykker_3</option>\n";
print "<option value=\"11\">Kontokort</option>";
print "<option value=\"12\">Indkøbsforslag</option>";
print "<option value=\"13\">Rekvisition</option>";
print "<option value=\"14\">Købsfaktura</option>";
# print "<option value=\"10\">Pos</option>";
print "</SELECT></td>\n";
print "<td>&nbsp;Art</td>\n";
print "<td><SELECT class=\"inputbox\" NAME=\"art\">\n";
if ($form_nr) print "<option value=\"$art\">$art_tekst</option>\n";
print "<option value=\"1:Linjer\">Linjer</option>\n";
print "<option value=\"2:Tekster\">Tekster</option>\n";
print "<option value=\"3:Ordrelinjer\">Ordrelinjer</option>\n";
print "<option value=\"4:Flyt center\">Flyt center</option>\n";
print "<option value=\"5:Mail tekst\">Mail tekst</option>\n";
print "</SELECT></td>\n";
print "<td>&nbsp;Sprog</td>\n";
print "<td><SELECT class=\"inputbox\" NAME=\"sprog\">\n";
if (!trim($formularsprog)) $formularsprog="Dansk";
print "<option value=\"".stripslashes($formularsprog)."\">".stripslashes($formularsprog)."</option>\n";
$q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($formularsprog!=$r['sprog']) print "<option value=\"".stripslashes($r['sprog'])."\">".stripslashes($r['sprog'])."</option>\n";
}
	print "</SELECT></td>\n";
print "<td><input type=\"submit\" accesskey=\"v\" value=\"V&aelig;lg\" name=\"formularvalg\"></td></tr>\n";
print "</form></tbody></table></td></tr>\n";
if ($form_nr==10) $art_nr=3;
print "<form name=\"linjer\" action=\"$_SERVER[PHP_SELF]?formular=$form_nr&amp;art=$art\" method=\"post\">\n";
	print "<input type=\"hidden\" name=\"form_nr\" value=\"$form_nr\">\n";
	print "<input type=\"hidden\" name=\"sprog\" value=\"$formularsprog\">\n";
	print "<input type=\"hidden\" name=\"art\" value=\"$art\">\n";

if ($art_nr==1) {
	print "<tr><td><br></td></tr>\n";
	print "<tr><td colspan=10 align=center> LOGO</td></tr>\n";
	print "<tr><td><br></td></tr>\n";
		
	print "<tr><td></td><td></td><td align=center>X</td><td align=center> Y</td></tr>\n";
	$x=1;
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse ='LOGO' and sprog = '$formularsprog'",__FILE__ . " linje " . __LINE__);
	$row=db_fetch_array($query);
	print "<tr>\n";
	print "<input type=\"hidden\" name=\"id[$x]\" value=\"$row[id]\"><input type=\"hidden\" name=\"beskrivelse[$x]\" value=\"LOGO\">\n";
	print "<td colspan=\"2\"></td><td align=\"center\"><input class=\"inputbox\" type=text style=text-align:right size=5 name=xa[$x] value=".round($row[xa],0).">\n";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=ya[$x] value=".round($row[ya],0).">";

	print "<tr><td><br></td></tr>";
	print "<tr><td colspan=6 align=center> Linjer</td></tr>";
	print "<tr><td><br></td></tr>";

	print "<tr><td colspan=2 align=center> Start</td>";
	print "<td colspan=2 align=center> Slut</td></tr>";
	print "<tr><td align=center>X</td><td align=center> Y</td><td align=center> X</td><td align=center> Y</td>";
	print "<td align=center> Bredde</td><td align=center> Farve</td></tr>";

	$x=0;
	print "<tr>";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xa[$x]>";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=ya[$x]>";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xb[$x]>";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=yb[$x]>";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=str[$x]>";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=color[$x]>";
	print "</tr>";
 
	$x=1;
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse !='LOGO' and sprog='$formularsprog' order by ya,xa,yb,xb",__FILE__ . " linje " . __LINE__);
	while ($row=db_fetch_array($query)){
		$x++; 
		print "<tr>";
		print "<input type=hidden name=id[$x] value=$row[id]>";
		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xa[$x] value=".round($row[xa],0).">";
		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=ya[$x] value=".round($row[ya],0).">"; 
		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xb[$x] value=".round($row[xb],0).">";
		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=yb[$x] value=".round($row[yb],0).">";
		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=str[$x] value=".round($row[str],0).">";
		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=color[$x] value=".round($row[color],0).">";
		print "</tr>";
	}	 
	$linjeantal=$x;
} elseif ($art_nr==2) {
	if ($form_nr>=6 && $form_nr<=9) {
		$gebyr='';$rentevnr='';
		$r=db_fetch_array(db_select("select xb,yb,str from formularer where beskrivelse ='GEBYR' and formular='$form_nr' and art='$art_nr' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__));
		$gebyr=$r['xb']*1;$rentevnr=$r['yb']*1;$rentesats=dkdecimal($r['str']);
		$r=db_fetch_array(db_select("select varenr from varer where id ='$gebyr'",__FILE__ . " linje " . __LINE__));
		$gebyr=$r['varenr'];
		print "<tr><td colspan=11 align=center title='Skriv det varenummer der skal bruges til rykkergebyr.'>Varenummer for rykkergebyr <input class=\"inputbox\" type=text size=15 name=gebyr value=$gebyr></td></tr>";
		$r=db_fetch_array(db_select("select varenr from varer where id ='$rentevnr'",__FILE__ . " linje " . __LINE__)); 
		$rentevnr=$r['varenr'];
		print "<tr><td colspan=11 align=center title='Skriv det varenummer og rentesatsen som bruges ved renteberegning. Rentesatsen g&aelig;lder pr p&aring;begyndt m&aring;ned'>Varenummer/sats for rente <input class=\"inputbox\" type=text size=15 name=rentevnr value=$rentevnr><input class=\"inputbox\" type=text size=1 name=rentesats value=$rentesats></td></tr>";
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
	drop_down(0,$form_nr,$art_nr,$formularsprog,"","","","","","","","","","","","","","");  
	
$tmp = db_escape_string($formularsprog);
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse != 'GEBYR' and sprog='$tmp' order by ya desc, xa",__FILE__ . " linje " . __LINE__);
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
	print "<tr><td colspan=2 align=center>Angiv blot det antal mm. der skal flyttes hhv. op og til h&oslash;jre</td></tr>";
	print "<tr><td colspan=2 align=center>Anvend negativt fortegn, hvis der skal rykkes ned eller til venstre</td></tr>";
	print "<tr><td colspan=2 align=center></td></tr>";
	print "<tr><td align=center>Op</td><td><input class=\"inputbox\" type=text style=text-align:right size=2 name=op></td><tr>";
	print "<tr><td align=center>H&oslash;jre</td><td><input class=\"inputbox\" type=text style=text-align:right size=2 name=hojre></td><tr>";
} elseif ($art_nr==5 && $form_nr!=3) {
	print "<tr><td><br></td></tr><tr><td align=\"center\" colspan=\"2\">".findtekst(215,$sprog_id)."</td></tr><tr><td><br></td></tr>\n";
	$q=db_select("select * from formularer where formular = '$form_nr' and art = '$art_nr' and sprog='$formularsprog' order by xa,id",__FILE__ . " linje " . __LINE__);
	($form_nr==1 || $form_nr==2 || $form_nr==4)?$i=3:$i=2; # 2013.11.21 Sætter $i til 3 hvis valg er Tilbud, Ordrer eller Faktura, ellers er $i = 2
	for ($x=1;$x<=$i;$x++) {
		$r=db_fetch_array($q);
		if ($r['xa']==1) {
			$subjekt=$r['beskrivelse'];
			$id1=$r['id'];
		} elseif ($r['xa']==2) {
			$mailtext=str_replace("<br>","\n",$r['beskrivelse']);
			$id2=$r['id']*1; #20140124
		} elseif ($r['xa']==3) { # 2013.11.21 Er kun med hvis $i er 3
			$bilagnavn=$r['beskrivelse'];
			$id3=$r['id'];
		}
		print "<input type=\"hidden\" name='id[$x]' value='$r[id]'>\n";
		print "<input type=\"hidden\" name='xa[$x]' value='$x'>\n";
		print "<input type=\"hidden\" name='form_nr' value='$form_nr'>\n";
		print "<input type=\"hidden\" name='art' value='$art'>\n";
		print "<input type=\"hidden\" name='sprog' value='$formularsprog'>\n";
	}
	# 2013.11.21 Har udkommenteret en overflødig slettefunktion der slettede alt som ikke havde $id1 og $id2 med samme $form_nr og $art_nr. 
	//db_modify("delete from formularer where formular = '$form_nr' and art = '$art_nr' and sprog='$formularsprog' and id!='$id1' and id!= '$id2'",__FILE__ . " linje " . __LINE__);
	print "<tr><td title=\"".findtekst(217,$sprog_id)."\">".findtekst(216,$sprog_id)."&nbsp;</td><td title=\"".findtekst(217,$sprog_id)."\"><input class=\"inputbox\" type=\"text\" size=\"40\" name=\"beskrivelse[1]\" value = \"$subjekt\"></td></tr>\n";
	print "<tr><td title=\"".findtekst(219,$sprog_id)."\" valign=\"top\">".findtekst(218,$sprog_id)."&nbsp;</td><td colspan=4  title=\"".findtekst(219,$sprog_id)."\"><textarea name=\"beskrivelse[2]\" rows=\"5\" cols=\"100\" onchange=\"javascript:docChange = true;\">$mailtext</textarea></td></tr>\n";
	if ($form_nr==1 || $form_nr==2 || $form_nr==4) print "<tr><td title=\"".findtekst(672,$sprog_id)."\">".findtekst(671,$sprog_id)."&nbsp;</td><td title=\"".findtekst(672,$sprog_id)."\"><input class=\"inputbox\" type=\"text\" size=\"40\" name=\"beskrivelse[3]\" value = \"$bilagnavn\"></td></tr>\n";
}
if (!$linjeantal) $linjeantal=$x;
print "<input type=hidden name=\"linjeantal\" value=$linjeantal>\n";
print "<tr><td colspan=11 align=\"center\"><hr></td></tr>\n";
if ($form_nr && $art) print "<tr><td colspan=\"11\" align=\"center\"><input type=\"submit\" accesskey=\"v\" value=\"Opdat&eacute;r\" name=\"linjer\"></td></tr>\n";
print "</tbody></table></td></tr></form>\n";

function sprog($nyt_sprog,$skabelon,$handling){

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
		print "<BODY onLoad=\"javascript:alert('$nyt_sprog er oprettet.')\">";
	}
} elseif ($skabelon && $handling=='slet') {
	db_modify("delete from formularer where sprog = '$skabelon'",__FILE__ . " linje " . __LINE__);
	db_modify("delete from grupper where art = 'VSPR' and box1 = '$skabelon'",__FILE__ . " linje " . __LINE__);
	 
} else {
	print "<form name=formularvalg action=$_SERVER[PHP_SELF]?nyt_sprog=yes method=\"post\">";
	print "<tr><td width=100% align=center><table border=0><tbody>"; # 20150331
	print "<tr><td>Skriv sprog der &oslash;nskes tilf&oslash;jet: </td><td><input class=\"inputbox\" type=tekst name=nyt_sprog size=15<td></tr>";
	print "<tr><td>V&aelig;lg formularskabelon</td>";
	print "<td><SELECT class=\"inputbox\" NAME=skabelon>";
	$q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) print "<option>$r[sprog]</option>";
	print "<option></option>";
	print "</SELECT></td><tr>";
	print "<tr><td colspan=2 align=center><input type=submit accesskey=\"g\" value=\"gem\" name=\"gem\">&nbsp;";
	print "<input type=submit accesskey=\"s\" value=\"slet\" name=\"slet\" onclick=\"return confirm('Slet det valgte sprog?')\">&nbsp;";
	print "<input type=submit accesskey=\"f\" value=\"fortryd\"name=\"fortryd\"></td></tr>";
	print "</tbody></table></td></tr>";
	exit;
}	

} # endfunc sprog

function drop_down($x,$form_nr,$art_nr,$formularsprog,$id,$beskrivelse,$xa,$xb,$ya,$yb,$str,$color,$justering,$font,$fed,$kursiv,$side){
	
	print "<tr>";
	print "<input type=hidden name=id[$x] value=$id>";
	print "<td><SELECT class=\"inputbox\" NAME=ny_beskrivelse[$x]>";
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
		print "<option value=\"adresser_firmanavn\">adresser_firmanavn</option>";
		print "<option value=\"adresser_addr1\">adresser_addr1</option>";
		print "<option value=\"adresser_addr2\">adresser_addr2</option>";
		print "<option value=\"adresser_postnr\">adresser_postnr</option>";
		print "<option value=\"adresser_bynavn\">adresser_bynavn</option>";
		print "<option value=\"adresser_land\">adresser_land</option>";
		print "<option value=\"adresser_kontakt\">adresser_kontakt</option>";
		print "<option value=\"adresser_cvrnr\">adresser_cvrnr</option>";
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
	if ($form_nr==4 || $form_nr==13) {
		print "<option>ordre_fakturanr</option>";
		print "<option>ordre_fakturadate</option>";
	}	
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
	} 
	if ($form_nr>=6) {
		print "<option>forfalden_sum</option>";
		print "<option>rykker_gebyr</option>";
	}	
	if ($form_nr>1 && $form_nr<6) print "<option value = \"kopier_alt|1\">Kopier alt fra tilbud</option>";
	if ($form_nr!=2 && $form_nr<6) print "<option value = \"kopier_alt|2\">Kopier alt fra ordrebrkræftelse</option>";
	if ($form_nr!=4 && $form_nr<6) print "<option value = \"kopier_alt|4\">Kopier alt fra faktura</option>";
	if ($form_nr<5) print "<option value = \"kopier_alt|5\">Kopier alt fra kreditnota</option>";
	print "</SELECT></td>";
	print "<td align=center><input class=\"inputbox\" type=text size=25 name=beskrivelse[$x] value=\"$beskrivelse\"></td>";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xa[$x] value=".round($xa,0)."></td>";
	if ($yb != "-") print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=ya[$x] value=".round($ya,0)."></td>";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=str[$x] value=".round($str,0)."></td>";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=color[$x] value=".round($color,0)."></td>";
	print "<td><SELECT class=\"inputbox\" NAME=justering[$x]>";
	print "<option>$justering</option>";
	print "<option>V</option>";
	print "<option>C</option>";
	print "<option>H</option>";
	print "</SELECT></td>";
	print "<td><SELECT class=\"inputbox\" NAME=form_font[$x]>";
	if ($font) print "<option>$font</option>";
	print "<option>Helvetica</option>";
	#			print "<option>Courier</option>";
	#			print "<option>Bookman</option>";
	print "<option>Times</option>";
	print "<option>Ocrbb12</option>";
	 print "</SELECT></td>";
	print "<td><SELECT class=\"inputbox\" NAME=side[$x]>";
	if ($side) print "<option>$side</option>";
	print "<option>A</option>";
	print "<option>1</option>";
	print "<option>!1</option>";
	print "<option>S</option>";
	print "<option>!S</option>";
	print "</SELECT></td>";
	if ($fed=='on') {$fed='checked';}
	print "<td align=center><input class=\"inputbox\" type=checkbox name=fed[$x] $fed></td>";
	if ($kursiv=='on') {$kursiv='checked';}
	print "<td align=center><input class=\"inputbox\" type=checkbox name=kursiv[$x] $kursiv></td>";
	print "</tr>";
} #endfunc drop_down		
##############################################################################################
function ordrelinjer($form_nr,$art_nr,$formularsprog){
	$x=1;
	print "<tr><td></td><td></td><td align=center>Linjeantal</td>\n";
	print "<td align=center>Y</td>\n";
	print "<td align=center>Linafs.</td></tr>\n";
	#		print "<td align=center>Understr.</td></tr>";
	$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse = 'generelt' and sprog='$formularsprog' order by xa",__FILE__ . " linje " . __LINE__);
	if (!$row=db_fetch_array($query)) {
		$query=db_modify ("insert into formularer (formular, art, beskrivelse, xa, ya, xb,sprog) values ($form_nr, $art_nr, 'generelt', 34, 185, 4,'$formularsprog')",__FILE__ . " linje " . __LINE__);
		$query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse = 'generelt' and sprog='$formularsprog' order by xa",__FILE__ . " linje " . __LINE__);
		$row=db_fetch_array($query);
	}
	print "<tr><td></td><td></td>\n";
	print "<input type=hidden name=id[$x] value=$row[id]>\n";
	print "<input type=hidden name=beskrivelse[$x] value=$row[beskrivelse]>\n";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xa[$x] value=".round($row['xa'],0)."></td>\n";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=ya[$x] value=".round($row['ya'],0)."></td>\n";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=3 name=xb[$x] value=".round($row['xb'],0)."></td></tr>\n";
	print "<tr><td>Beskrivelse</td>\n";
	print "<td align=center>X</td>\n";
	print "<td align=center>H&oslash;jde</td><td align=center> Farve</td>\n";
	print "<td align=center>Just.</td><td align=center>Font</td><td align=center> Fed</td>\n";
	print "<td align=center> Kursiv</td><td align=center> Tekstl&aelig;ngde</td></tr>\n";

$x=0;
	print "<tr>\n";
	print "<td><SELECT class=\"inputbox\" NAME=beskrivelse[$x]>\n";
	if ($form_nr<6 || $form_nr==9 || ($form_nr>=12 && $form_nr<=14)) {
		print "<option>posnr</option>\n";
		print "<option>varenr</option>\n";
		print "<option>lev_varenr</option>\n";
		print "<option>antal</option>\n";
		print "<option>enhed</option>\n";
		print "<option>beskrivelse</option>\n";
		print "<option>pris</option>\n";
		print "<option>rabat</option>\n";
		print "<option>momssats</option>\n";
		if ($procentfakt) print "<option>procent</option>";
		print "<option value=\"linjemoms\">moms</option>";
		print "<option value=\"varemomssats\">momssats</option>";
		print "<option>linjesum</option>\n";
		print "<option>projekt</option>\n";
		print "<option>procent</option>\n"; #20140709
		if ($form_nr==3) {
			print "<option>lev_tidl_lev</option>\n";
			print "<option>lev_antal</option>\n";
			print "<option>lev_rest</option>\n";
			print "<option>lokation</option>\n";
		} 
		if ($form_nr==9) {
			print "<option>leveres</option>\n";
			print "<option>lokation</option>\n";
			print "<option>Fri tekst</option>\n";
		} 
	} elseif ($form_nr==11) {
		print "<option>beskrivelse</option>";
		print "<option>dato</option>";
		print "<option>debet</option>";
		print "<option>faktnr</option>";
		print "<option>forfaldsdato</option>";
		print "<option>kredit</option>";
		print "<option>saldo</option>";
	} else {
		print "<option>dato</option>\n";
		print "<option>faktnr</option>\n";
		print "<option>beskrivelse</option>\n";
		print "<option>bel&oslash;b</option>\n";
	}
	print "</SELECT></td>\n";
		#		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xa[$x]></td>";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xa[$x]></td>\n";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=str[$x]></td>\n";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=color[$x]></td>\n";
	print "<td><SELECT class=\"inputbox\" NAME=justering[$x]>\n";
	print "<option>V</option>\n";
	print "<option>C</option>\n";
	print "<option>H</option>\n";
	print "</SELECT></td>\n";
	print "<td><SELECT class=\"inputbox\" NAME=form_font[$x]>\n";
	print "<option>Helvetica</option>\n";
	#	 print "<option>Courier</option>";
	#	 print "<option>Bookman</option>";
	print "<option>Times</option>\n";
	print "</SELECT></td>\n";
	print "<td align=center><input class=\"inputbox\" type=checkbox name=fed[$x]></td>\n";
	print "<td align=center><input class=\"inputbox\" type=checkbox name=kursiv[$x]></td>\n";
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
			print "<td><input class=\"inputbox\" type=text name=\"beskrivelse[$x]\" value=\"$row[beskrivelse]\"></td>\n";
		} else {
			print "<input type=hidden name=\"tabel[$x]\" value=\"\">\n";
			print "<td>$row[beskrivelse]</td>\n";
		}
		/*		
		print "<td><SELECT class=\"inputbox\" NAME=beskrivelse[$x]>";
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
			print "<option>bel&oslash;b</option>";
		}
		print "</SELECT></td>";
*/		
		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xa[$x] value=".round($row['xa'],0)."></td>\n";
		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=str[$x] value=".round($row['str'],0)."></td>\n";
		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=color[$x] value=".round($row['color'],0)."></td>\n";
		print "<td><SELECT class=\"inputbox\" NAME=justering[$x]>\n";
		print "<option>$row[justering]</option>\n";
		print "<option>V</option>\n";
		print "<option>C</option>\n";
		print "<option>H</option>\n";
		print "</SELECT></td>\n";
		print "<td><SELECT class=\"inputbox\" NAME=form_font[$x]>\n";
		print "<option>$row[font]</option>\n";
		print "<option>Helvetica</option>\n";
		print "<option>Times</option>\n";
		print "</SELECT></td>\n";
		if ($row['fed']=='on') {$row['fed']='checked';}
		print "<td align=center><input class=\"inputbox\" type=checkbox name=fed[$x] $row[fed]></td>\n";
		if ($row['kursiv']=='on') {$row['kursiv']='checked';}
		print "<td align=center><input class=\"inputbox\" type=checkbox name=kursiv[$x] $row[kursiv]></td>\n";
		if ($row['beskrivelse']=='beskrivelse'){print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xb[$x] value=".round($row['xb'],0)."></td>\n";}
		print "</tr>\n";
	}	 
	return($x);
} #endfunc ordrelinjer		
###############################################################################
function pos_linjer($form_nr,$art_nr,$formularsprog){
	$x=1;
	print "<tr><td></td><td></td><td align=cente>Toplinjer</td>";
	print "<td align=center>Bundlinjer</td>";
	print "<td align=center>Linafs.</td></tr>";
	#
if (!$r=db_fetch_array(db_select("select * from formularer where formular = '$form_nr' and art = '3' and beskrivelse = 'generelt' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__))) {
		$q=db_modify ("insert into formularer (formular, art, beskrivelse, sprog, xa, ya, xb) values ('$form_nr','3','generelt','$formularsprog','4','2',4)",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from formularer where formular = $form_nr and art = 3 and beskrivelse = 'generelt' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__));
	}
	$header=round($r['xa'],0);
	$footer=round($r['ya'],0);
	$linespace=round($r['xb'],0);
	print "<tr><td></td><td></td>\n";
	print "<input type=hidden name=id[$x] value=\"$r[id]\">\n";
	print "<input type=hidden name=beskrivelse[$x] value=\"$r[beskrivelse]\">\n";
	print "<input type=hidden name=form value=\"10\">\n";
	print "<input type=hidden name=art value=\"3\">\n";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xa[$x] value=\"$header\"></td>\n";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=ya[$x] value=\"$footer\"></td>\n";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xb[$x] value=\"$linespace\"></td></tr>\n";
	# hvis header eller footer er blevet reduceret slettes de overskydende linjer.
  db_modify("delete from formularer where formular = $form_nr and art = '3' and xb > $header and ya='1' and beskrivelse != 'generelt' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);
  db_modify("delete from formularer where formular = $form_nr and art = '3' and xb > $footer and ya='2' and beskrivelse != 'generelt' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);
	$x++;
	if ($header) {
	  print "<tr><td colspan=11><table><tbody>";
		print "<tr><td colspan=11><hr></td></tr>";
		print "<tr><td></td><td align=center>Tekst</td>";
		print "<td align=center>X</td>";
		print "<td align=center>H&oslash;jde</td><td align=center> Farve</td>";
		$span="Justering - H: H&oslash;jrestillet\n C: Centreret\n V: Venstrestillet";
		print "<td align=center><span title = \"$span\">Just.</span></td><td align=center>Font</td>";
		$span="1: Kun side 1\n!1: Alle foruden side 1\nS: Sidste side\n!S: Alle foruden sidste side\nA: Alle sider";	
		print "<td align=center><span title = \"$span\">Side</span></td>";
		print "<td align=center>Fed</td><td align=center>&nbsp;Kursiv</td>";
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
	print "<tr><td>Beskrivelse</td>";
	print "<td align=center>X</td>";
	print "<td align=center>H&oslash;jde</td><td align=center> Farve</td>";
	print "<td align=center>Just.</td><td align=center>Font</td><td align=center> Fed</td>";
	print "<td align=center> Kursiv</td><td align=center> Tekstl&aelig;ngde</td></tr>";
	#		print "<td align=center>Understr.</td></tr>";
	print "<tr>";
	print "<td><SELECT class=\"inputbox\" NAME=beskrivelse[$x]>";
		print "<option>posnr</option>";
		print "<option>varenr</option>";
		print "<option>antal</option>";
		print "<option>enhed</option>";
		print "<option>beskrivelse</option>";
		print "<option>pris</option>";
		print "<option>rabat</option>";
		print "<option value=\"linjemoms\">moms</option>";
		print "<option value=\"varemomssats\">momssats</option>";
		print "<option>linjesum</option>";
		print "<option>projekt</option>";
	print "</SELECT></td>";
	print "<input type=hidden style=text-align:right size=5 name=ya[$x] value=\"0\">";
	print "<td align=center><input class=\"inputbox\" type	=text style=text-align:right size=5 name=xa[$x]></td>";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=str[$x]></td>";
	print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=color[$x]></td>";
	print "<td><SELECT class=\"inputbox\" NAME=justering[$x]>";
	print "<option>V</option>";
	print "<option>C</option>";
	print "<option>H</option>";
	print "</SELECT></td>";
	print "<td><SELECT class=\"inputbox\" NAME=form_font[$x]>";
	print "<option>Helvetica</option>";
	#	 print "<option>Courier</option>";
	#	 print "<option>Bookman</option>";
	print "<option>Times</option>";
	print "</SELECT></td>";
	print "<td align=center><input class=\"inputbox\" type=checkbox name=fed[$x]></td>";
	print "<td align=center><input class=\"inputbox\" type=checkbox name=kursiv[$x]></td>";
	print "</tr>";

	$q=db_select("select * from formularer where formular = '$form_nr' and art = '$art_nr' and ya< '1' and beskrivelse != 'generelt' and sprog='$formularsprog' order by xa",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$x++;
		print "<tr>";
		print "<input type=hidden name=id[$x] value=$r[id]>";
		print "<td><SELECT class=\"inputbox\" NAME=beskrivelse[$x]>";
		print "<option>$r[beskrivelse]</option>";
		if ($form_nr<6 || $form_nr==10) {
			print "<option>posnr</option>";
			print "<option>varenr</option>";
			print "<option>antal</option>";
			print "<option>beskrivelse</option>";
			print "<option>pris</option>";
			print "<option>rabat</option>";
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
			print "<option>beskrivelse</option>";
			print "<option>bel&oslash;b</option>";
		}
		print "</SELECT></td>";
		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xa[$x] value=".round($r['xa'],0)."></td>";
		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=str[$x] value=".round($r['str'],0)."></td>";
		print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=color[$x] value=".round($r['color'],0)."></td>";
		print "<td><SELECT class=\"inputbox\" NAME=justering[$x]>";
		print "<option>$r[justering]</option>";
		print "<option>V</option>";
		print "<option>C</option>";
		print "<option>H</option>";
		print "</SELECT></td>";
		print "<td><SELECT class=\"inputbox\" NAME=form_font[$x]>";
		print "<option>$r[font]</option>";
		print "<option>Helvetica</option>";
		print "<option>Times</option>";
		print "</SELECT></td>";
		if ($r['fed']=='on') {$r['fed']='checked';}
		print "<td align=center><input class=\"inputbox\" type=checkbox name=fed[$x] $r[fed]></td>";
		if ($r['kursiv']=='on') {$r['kursiv']='checked';}
		print "<td align=center><input class=\"inputbox\" type=checkbox name=kursiv[$x] $r[kursiv]></td>";
		if ($r['beskrivelse']=='beskrivelse'){print "<td align=center><input class=\"inputbox\" type=text style=text-align:right size=5 name=xb[$x] value=".round($r['xb'],0)."></td>";}
		print "</tr>";
	}
	if ($footer) {
	$x++;
		print "<tr><td colspan=11><table><tbody>";
		print "<tr><td colspan=11><hr></td></tr>";
		print "<tr><td></td><td align=center>Tekst</td>";
		print "<td align=center>X</td>";
		print "<td align=center>H&oslash;jde</td><td align=center> Farve</td>";
		$span="Justering - H: H&oslash;jrestillet\n C: Centreret\n V: Venstrestillet";
		print "<td align=center><span title = \"$span\">Just.</span></td><td align=center>Font</td>";
		$span="1: Kun side 1\n!1: Alle foruden side 1\nS: Sidste side\n!S: Alle foruden sidste side\nA: Alle sider";	
		print "<td align=center><span title = \"$span\">Side</span></td>";
		print "<td align=center>Fed</td><td align=center>&nbsp;Kursiv</td>";
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
		if (! $menu=='T') print "<tr><td colspan=11><hr></td></tr>";  # 20150331
	 	print "</tbody></table></td></tr>";
		$x=$x+$footer;
	}
	return $x;
} #endfunc pos_linjer		
function kopier_alt($form_nr,$art_nr,$formularsprog,$kilde) {
	if ($form_nr&&$art_nr&&$formularsprog) {
		db_modify("delete from formularer where formular = '$form_nr' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);
		$q=db_select("select * from formularer where formular = '$kilde' and sprog='$formularsprog'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$xa=$r['xa']*1; $ya=$r['ya']*1; $xb=$r['xb']*1; $yb=$r['yb']*1;$str=$r['str']*1;$color=$r['color']*1;
# echo "insert into formularer(formular,art,beskrivelse,justering,xa,ya,xb,yb,str,color,font,fed,kursiv,side,sprog) values	('$form_nr','$art_nr','".db_escape_string($r['beskrivelse'])."','$r[justering]','$xa','$ya','$xb','$yb','$str','$color','$r[font]','$r[fed]','$r[kursiv]','$r[side]','$formularsprog')<br>";
			db_modify("insert into formularer(formular,art,beskrivelse,justering,xa,ya,xb,yb,str,color,font,fed,kursiv,side,sprog) values	('$form_nr','$r[art]','".db_escape_string($r['beskrivelse'])."','$r[justering]','$xa','$ya','$xb','$yb','$str','$color','$r[font]','$r[fed]','$r[kursiv]','$r[side]','$formularsprog')",__FILE__ . " linje " . __LINE__);
		}
#		print "<meta http-equiv=\"refresh\" content=\"10;URL=formularkort.php?formular=$form_nr&art=$art_nr&sprog=$formularsprog\">";

	}
}

print "<tr><td width='100%' height='2.5%' align='center' valign='bottom'>\n";		
print "  <table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>\n";
print "    <td width='14%' align='center' ".$top_bund.">&nbsp;</td>\n";
print "    <td width='24%' align='center' ".$top_bund.">&nbsp;</td>\n";
print "    <td width='24%' align='center' ".$top_bund." ;>";
print "			<a href=\"formular_indlaes_std.php\">Genindl&aelig;s standardformularer</a></td>\n";
print "    <td width='24%' ".$top_bund.">&nbsp;</td>\n";
if ($menu=='T') {  # 20150331 start bund
	print "<td width=\"7%\" ".$top_bund."><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><span ";
	print "title=\"Opret eller nedl&aelig;g sprog\"><a href=formularkort.php?nyt_sprog=yes accesskey=\"s\">Sprog</a></span></td>\n";
	print "<td width=\"7%\" ".$top_bund."><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><span ";
	print "title=\"Indl&aelig;s eller fjern baggrundsfil\"><a href=logoupload.php?upload=yes accesskey=\"u\">Baggrund</a></span></td>\n";
} else {
	print "    <td width='14%' ".$top_bund.">&nbsp;</td>\n";
} # 20150331 slut bund
print "    <!-- <td width='10%' ".$top_bund."> ";
print "onClick=\"javascript:window.open('logoslet.php', '','left=10,top=10,width=400,height=200,scrollbars=yes,resizable=yes,menubar=no,location=no')\" ";
print "onMouseOver=\"this.style.cursor = 'pointer'\" ><u>Slet logo</u></td> -->\n";
print "  </tbody></table>\n";
print "</td></tr>\n";
print "</tbody></table>\n";
if ($menu=='T') print "</div>\n</div>\n";  # 20150331
print "</body></html>\n";
