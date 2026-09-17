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
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20260709 SZ Added Grid Framework sticky header to Multiroute report

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
include("../includes/topline_settings.php");


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
		$linje="Faktura nr;Navn;Vej;Post nr;By;Land;Brd.gr.;".mb_convert_encoding('Lng.gr.', 'ISO-8859-1', 'UTF-8').";Enh.;Start;Stop;Stoptid;Ress.;Res.Grp;Bemrk.;Kontakt;Tlf;Email;SMS";
		fwrite($fp,"$linje\n"); # Bredegrad;Længdegrad;Enheder;Start tidsvindue;Slut tidsvindue;Stop længde\n");
		fclose($fp);
	}
	$fp=fopen("../temp/$db/multiroute.csv","a");
	$linje=$fakturanr.";";
	$linje.=mb_convert_encoding($navn, 'ISO-8859-1', 'UTF-8').";";
	$linje.=mb_convert_encoding($vej, 'ISO-8859-1', 'UTF-8').";";
	$linje.=$postnr.";";
	$linje.=mb_convert_encoding($bynavn, 'ISO-8859-1', 'UTF-8').";";
	$linje.=$land.";";
	$linje.=";;;;;";
	$linje.="10";
	$linje.=";;;;";
	$linje.=mb_convert_encoding($kontakt, 'ISO-8859-1', 'UTF-8').";";
	$linje.=$tlf.";";
	$linje.=mb_convert_encoding($email, 'ISO-8859-1', 'UTF-8').";";
	$linje.=$tlf;
	fwrite($fp,$linje."\n"); 
	fclose($fp);	
}

if ($menu == 'S') {
	// Grid Framework header — printed here, BEFORE the outer <table> below opens, and NOT nested
	// inside any <td>. position:sticky only stays "stuck" while its containing block (effectively
	// its parent) is still in view — nesting it inside a <td> would give it a containing block only
	// as tall as the header bar itself (~40px), so it would lose its sticky effect and scroll away
	// almost immediately. Printing it as a top-level element before the table gives it the full page
	// as its containing block, so it actually remains visible for the whole scroll, matching the
	// ticket's "remains visible when the user scrolls down through the report" requirement. Mirrors
	// Finance -> Reports -> Balance (kontosaldo() in includes/rapportfunc.php) styling: icon back
	// button + $topStyle title bar + empty $topStyle 3rd cell, 10/80/10 layout — same three-segment
	// convention as saftCashRegister.php/salgsstat.php/finans/saft.php (kontosaldo() itself only has
	// 2 columns, kept 3 per explicit follow-up direction). The previous "include
	// ../includes/sidemenu.php" was a dead include (the file doesn't exist in this codebase) — it
	// silently failed and printed nothing, which is why this report had no header at all in S-mode.
	$tilbage_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
	print "<div style=\"position: sticky; top: 0; z-index: 100;\">";
	print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody><tr>";
	print "<td width='10%' align='left'><a href='$luk' accesskey=L style=\"text-decoration: none;\">";
	print "<button class='headerbtn' type='button' style='$buttonStyle; width: 100%; display: flex; align-items: center; gap: 5px; justify-content: flex-start; padding-left: 3px;' onMouseOver=\"this.style.cursor = 'pointer'\">";
	print "$tilbage_icon " . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>";
	print "<td width='80%' align='center' style='$topStyle'>$title</td>";
	print "<td width='10%' align='center' style='$topStyle'>&nbsp;</td>";
	print "</tr></tbody></table>";
	print "</div>";
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
	// Header already printed above, before this table opened — see comment there.
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
			print "<td style=\"border:1px solid $bgcolor2;\">".mb_convert_encoding($felt[$x], 'UTF-8', 'ISO-8859-1')."</td>";
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
