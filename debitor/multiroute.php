<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------debitor/multiroute.php----------lap 3.6.7---2017-03-09----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2017 saldi.dk aps
// ----------------------------------------------------------------------------
// 2017.03.09 Tilføjet email & stoptid
// 2017.04.07 Tilrettet jf. mail fra Rasmus / Multiflash
// 2019.02.12 MSC - Rettet isset fejl
// 2019.02.18 MSC - Rettet topmenu design

@session_start();
$s_id=session_id();
$modulnr=12;
$title="Eksport til mulitroute";

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
include("../includes/db_query.php");


$slet=if_isset($_POST['slet']);
$saldi_nr=if_isset($_POST['saldi_nr']);
$shop_nr=if_isset($_POST['shop_nr']);
if ($popup) $luk="../includes/luk.php";
else $luk="rapport.php";

$x=0;
$y=0;

if ($slet) {
	if (file_exists("../temp/$db/multiroute.csv")) unlink ("../temp/$db/multiroute.csv");
}
if ($saldi_nr) {
	$qtxt="select * from ordrer where fakturanr='$saldi_nr'";
	$fakturanr=$saldi_nr;
}	elseif ($shop_nr) {
	$qtxt="select * from ordrer where kundeordnr='$shop_nr'";
	$fakturanr=$shop_nr;
}
if (!isset ($qtxt)) $qtxt = NULL;
if ($qtxt) {
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if (!isset ($r['ordre_id'])) $r['ordre_id'] = NULL;
	if (!isset ($r['tlf'])) $r['tlf'] = NULL;
	if (!isset ($r['ordrenr'])) $r['ordrenr'] = NULL;
	if (!isset ($r['konto_id'])) $r['konto_id'] = NULL;
	if (!isset ($r['kontonr'])) $r['kontonr'] = NULL;
	if (!isset ($r['email'])) $r['email'] = NULL;
	if (!isset ($r['land'])) $r['land'] = NULL;
	if (!isset ($r['kontakt'])) $r['kontakt'] = NULL;
	if (!isset ($r['lev_navn'])) $r['lev_navn'] = NULL;
	if (!isset ($r['firmanavn'])) $r['firmanavn'] = NULL;
	if (!isset ($r['lev_addr1'])) $r['lev_addr1'] = NULL;
	if (!isset ($r['addr1'])) $r['addr1'] = NULL;
	if (!isset ($r['addr2'])) $r['addr2'] = NULL;
	if (!isset ($r['lev_postnr'])) $r['lev_postnr'] = NULL;
	if (!isset ($r['postnr'])) $r['postnr'] = NULL;
	if (!isset ($r['lev_bynavn'])) $r['lev_bynavn'] = NULL;
	if (!isset ($r['bynavn'])) $r['bynavn'] = NULL;
	$ordre_id=$r['ordre_id'];
	$ordrenr=$r['ordrenr'];
	$kontonr=$r['kontonr'];
	$email=$r['email'];
	$land=$r['land'];
	$kontakt=$r['kontakt'];
	$tlf=$r['tlf'];
	
	($r['lev_navn'])?$navn=$r['lev_navn']:$navn=$r['firmanavn'];
	($r['lev_addr1'])?$vej=$r['lev_addr1']:$vej=$r['addr1'];
	if ($r['lev_addr1']) {
		$vej=$r['lev_addr1'];
		if ($r['lev_addr2']) $vej.=", ".$r['lev_addr2'];
	} else {
		$vej=$r['addr1'];
		if ($r['addr2']) $vej.=", ".$r['addr2'];
	}
	($r['lev_postnr'])?$postnr=$r['lev_postnr']:$postnr=$r['postnr'];
	if ($r['lev_postnr'] && !$r['lev_bynavn']) $bynavn=bynavn($postnr);
	else {
		($r['lev_bynavn'])?$bynavn=$r['lev_bynavn']:$bynavn=$r['bynavn'];
	}
#	$qtxt="select tlf from adresser where id='$konto_id'";
#echo $qtxt;
#	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#	$telefon=$r['tlf'];
	if (!$tlf) $tlf=$kontonr;

	if (!file_exists("../temp/$db/multiroute.csv")) {
		$fp=fopen("../temp/$db/multiroute.csv","w");
		$linje="Faktura nr;Navn;Vej;Post nr;By;Land;Brd.gr.;".utf8_decode('Lng.gr.').";Enh.;Start;Stop;Stoptid;Ress.;Res.Grp;Bemrk.;Kontakt;Tlf;Email;SMS";
		fwrite($fp,"$linje\n"); # Bredegrad;Længdegrad;Enheder;Start tidsvindue;Slut tidsvindue;Stop længde\n");
		fclose($fp);
	}
	$fp=fopen("../temp/$db/multiroute.csv","a");
	$linje=$fakturanr.";";
	$linje.=utf8_decode($navn).";";
	$linje.=utf8_decode($vej).";";
	$linje.=$postnr.";";
	$linje.=utf8_decode($bynavn).";";
	$linje.=$land.";";
	$linje.=";;;;;";
	$linje.="10";
	$linje.=";;;;";
	$linje.=utf8_decode($kontakt).";";
	$linje.=$tlf.";";
	$linje.=utf8_decode($email).";";
	$linje.=$tlf;
	fwrite($fp,$linje."\n"); 
	fclose($fp);	
}

print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
if ($menu=='T') {
	$leftbutton="<a class='button red small' title=\"Klik her for at komme til startsiden\" href=\"../debitor/rapport.php\" accesskey=\"L\">Luk</a>";
	$rightbutton=NULL;
	$vejledning=NULL;
	include("../includes/top_header.php");
	include("../includes/top_menu.php");
	print "<div id=\"header\"> 
	<div class=\"headerbtnLft\">$leftbutton</div>
	<span class=\"headerTxt\">Salgsstat</span>";     
	print "<div class=\"headerbtnRght\"></div>";       
	print "</div><!-- end of header -->";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<table class='dataTable2' cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>\n";
} elseif ($menu=='S') {
	include("../includes/sidemenu.php");
} else {
	print "<tr><td colspan=\"4\" height=\"8\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>";
	$tekst="Klik her for at lukke";
	print "<td width=\"10%\" $top_bund title='$tekst'><a href=\"$luk\" accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund>$title</td>";
	print "<td width=\"10%\" $top_bund></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
}
print "<tr><td width=\"100%\" align=center valign=top><table align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>";	
print "<form name=\"multiroute\" action=\"multiroute.php\" method=\"post\">";
print "<tr><td align=\"center\">Saldi Faktura nr</td><td align=\"center\">eller</td><td align=\"center\">Shop faktura nr</td></tr>";
print "<tr><td align=\"center\"><input type=\"text\" name=\"saldi_nr\"></td><td></td><td align=\"center\"><input type=\"text\" name=\"shop_nr\"></td></tr>";
print "<tr><td colspan=\"3\" align=\"center\"><input class='button gray small' type=\"submit\" name=\"soeg\" value=\"Søg\"></td></tr>";
print "</form>";
print "</tbody></table></td></tr>";
print "<tr><td width=\"100%\" align=center valign=top><table align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>";	
if (file_exists("../temp/$db/multiroute.csv")) {
	$fp=fopen("../temp/$db/multiroute.csv","r");
	while ($line=fgets($fp)) {
		$felt=explode(";",$line);
		print "<tr>";
		for ($x=0;$x<count($felt);$x++) {
			print "<td style=\"border:1px solid $bgcolor2;\">".utf8_encode($felt[$x])."</td>";
		}
		print "</tr>";
	}
	fclose($fp);
	print "<tr><td colspan=\"".count($felt)."\" align=\"center\"><a href=\"../temp/$db/multiroute.csv\">Multiroute.csv</a></td></tr>";
	print "<form name=\"multiroute\" action=\"multiroute.php\" method=\"post\">";
	print "<tr><td colspan=\"".count($felt)."\" align=\"center\"><input class='button rosy medium' type=\"submit\" name=\"slet\" value=\"Ryd liste\"></td></tr>";
	print "</form>";
}
print "</tbody></table></td></tr>";
?>
