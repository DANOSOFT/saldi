<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------/systemdata/formular_indlaes_std.php---ver 3.6.1---2017-04-20--
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2017 saldi.dk ApS
// ----------------------------------------------------------------------
// 20130510, parameter 2 til formularimport
// 20160111, div større rettelser
// 20170420 rettet 'art' til 'formular' overalt
// 2019.02.21 MSC - Rettet topmenu

@session_start();
$s_id=session_id();
ob_start(); //Starter output buffering

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/std_func.php");
include("../includes/formularimport.php");

if ($menu=='T') {
	#	print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">\n";
		print "<div class=\"headerbtnLft\"></div>\n";
	#	print "<span class=\"headerTxt\">Systemsetup</span>\n";     
	#	print "<div class=\"headerbtnRght\"><!--<a href=\"index.php?page=../debitor/debitorkort.php;title=debitor\" class=\"button green small right\">Ny debitor</a>--></div>";       
		print "</div><!-- end of header -->";
		print "<div id=\"leftmenuholder\">";
		include_once 'left_menu.php';
		print "</div><!-- end of leftmenuholder -->\n";
		print "<div class=\"maincontentLargeHolder\">\n";
} else {
	print "";
}

$post_valg = $_POST["valg"];
if (isset($post_valg["tilbud"])) {
	$valg = "Tilbud";
} else if (isset($post_valg["ordrebekræftelse"])) {
	$valg = "Ordrebekræftelse";
} else if (isset($post_valg["følgeseddel"])) {
	$valg = "Følgeseddel";
} else if (isset($post_valg["faktura"])) {
	$valg = "Faktura";
} else if (isset($post_valg["kreditnota"])) {
	$valg = "Kreditnota";
} else if (isset($post_valg["rykker_1"])) {
	$valg = "Rykker_1";
} else if (isset($post_valg["rykker_2"])) {
	$valg = "Rykker_2";
} else if (isset($post_valg["rykker_3"])) {
	$valg = "Rykker_3";
} else if (isset($post_valg["plukliste"])) {
	$valg = "Plukliste";
} else if (isset($post_valg["kontokort"])) {
	$valg = "Kontokort";
} else if (isset($post_valg["indkøbsforslag"])) {
	$valg = "Indkøbsforslag";
} else if (isset($post_valg["rekvisition"])) {
	$valg = "Rekvisition";
} else if (isset($post_valg["købsfaktura"])) {
	$valg = "Købsfaktura";
} else if (isset($post_valg["alle"])) {
	$valg = "Alle";
}

if (isset($valg)) {
	if ($valg=='Alle') {
		db_modify("delete from formularer");
		formularimport("../importfiler/formular.txt",'');
		db_modify("update formularer set sprog = 'Dansk'");
	} else {
		if ($valg=='Tilbud') $formular=1;
		elseif ($valg=='Ordrebekræftelse') $formular=2;
		elseif ($valg=='Følgeseddel') {
			$formular=3;
		} elseif ($valg=='Faktura') $formular=4;
		elseif ($valg=='Kreditnota') $formular=5;
		elseif ($valg=='Rykker_1') $formular=6;
		elseif ($valg=='Rykker_2') $formular=7;
		elseif ($valg=='Rykker_3') $formular=8;
		elseif ($valg=='Plukliste') $formular=9;
		elseif ($valg=='Pos') $formular=10;
		elseif ($valg=='Kontokort') $formular=11;
		elseif ($valg=='Indkøbsforslag') $formular=12;
		elseif ($valg=='Rekvisition') $formular=13;
		elseif ($valg=='Købsfaktura') $formular=14;
		else $formular=NULL;
		if ($formular) {
			db_modify("delete from formularer where formular = '$formular'",__FILE__ . " linje " . __LINE__);
			formularimport("../importfiler/formular.txt",$formular);
			db_modify("update formularer set sprog = 'Dansk' where formular = '$formular'",__FILE__ . " linje " . __LINE__);
		}
	}
	print "<div style=\"text-align: center;\">$font<small>Overskrivning med standardformularer succesfuld - vinduet lukkes</small></font><br></div>";
	print "<meta http-equiv=\"refresh\" content=\"3;URL=../systemdata/formularkort.php\">";
	exit;
}
elseif(isset($_POST['afbryd']) && $_POST['afbryd'])  {
	print "<meta http-equiv=\"refresh\" content=\"3;URL=../systemdata/formularkort.php\">";
	exit;
} else {
	print "<form name=formularimport action=$_SERVER[PHP_SELF] method=\"post\">";
	print "<div style=\"text-align: center;\">$font Dette vil overskrive den valgte danske formular med standardopsætningen<br>";
	print "<div style=\"text-align: center;\">$font\"Alle\" overskriver alle formularer og sletter formularer på andre sprog end dansk<br>";
	print "<div style=\"text-align: center;\">$font og slette formularer på andre sprog end dansk.<br>";

	print "
	<input class='button red medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('81|Afbryd', $sprog_id)."\" name=\"afbryd\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('812|Tilbud', $sprog_id)."\" name=\"valg[tilbud]\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('575|Ordrebekræftelse', $sprog_id)."\" name=\"valg[ordrebekræftelse]\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('576|Følgeseddel', $sprog_id)."\" name=\"valg[følgeseddel]\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('643|Faktura', $sprog_id)."\" name=\"valg[faktura]\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('577|Kreditnota', $sprog_id)."\" name=\"valg[kreditnota]\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('578|Rykker', $sprog_id)." 1\" name=\"valg[rykker_1]\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('578|Rykker', $sprog_id)." 2\" name=\"valg[rykker_2]\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('578|Rykker', $sprog_id)." 3\" name=\"valg[rykker_3]\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('574|Plukliste', $sprog_id)."\" name=\"valg[plukliste]\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('133|Kontokort', $sprog_id)."\" name=\"valg[kontokort]\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('954|Indkøbsforslag', $sprog_id)."\" name=\"valg[indkøbsforslag]\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('579|Rekvisition', $sprog_id)."\" name=\"valg[rekvisition]\"><br><br>
	<input class='button blue medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('580|Købsfaktura', $sprog_id)."\" name=\"valg[købsfaktura]\"><br><br>
	<input class='button gray medium' type=\"submit\" style=\"width:150px\" value=\"".findtekst('2498|Alle', $sprog_id)."\" name=\"valg[alle]\"><br><br>";
	print "</small></font></div></form>";
}
?>
</tbody></table>
</body></html>