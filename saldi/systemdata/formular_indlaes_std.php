<?php
	@session_start();
	$s_id=session_id();

// --------/systemdata/formular_indlaes_std.php---ver 3.6.1---2016-01-11--
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
// Copyright (c) 2004-2016 Danosoft ApS
// ----------------------------------------------------------------------
// 20130510, parameter 2 til formularimport
// 20160111, div større rettelser

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/formularimport.php");


if ($valg=$_POST['valg']) {
	if ($valg=='Alle') {
		db_modify("delete from formularer");
		formularimport("../importfiler/formular.txt",'');
		db_modify("update formularer set sprog = 'Dansk'");
	} else {
		if ($valg=='Tilbud') $art=1;
		elseif ($valg=='Ordrebekræftelse') $art=2;
		elseif ($valg=='Følgeseddel') $art=3;
		elseif ($valg=='Faktura') $art=4;
		elseif ($valg=='Kreditnota') $art=5;
		elseif ($valg=='Rykker_1') $art=6;
		elseif ($valg=='Rykker_2') $art=7;
		elseif ($valg=='Rykker_3') $art=8;
		elseif ($valg=='Plukliste') $art=9;
		elseif ($valg=='Pos') $art=10;
		elseif ($valg=='Kontokort') $art=11;
		elseif ($valg=='Indkøbsforslag') $art=12;
		elseif ($valg=='Rekvisition') $art=13;
		elseif ($valg=='Købsfaktura') $art=14;
		else $art=NULL;
		if ($art) {
			db_modify("delete from formularer where art = '$art'");
			formularimport("../importfiler/formular.txt",$art);
			db_modify("update formularer set sprog = 'Dansk' where art = '$art'");
		}
	}
	print "<div style=\"text-align: center;\">$font<small>Overskrivning med standardformularer succesfuld - vinduet lukkes</small></font><br></div>";
	print "<meta http-equiv=\"refresh\" content=\"3;URL=../systemdata/formularkort.php\">";
	exit;
}
elseif($_POST['afbryd'])  {
	print "<meta http-equiv=\"refresh\" content=\"3;URL=../systemdata/formularkort.php\">";
	exit;
} else {
	print "<form name=formularimport action=$_SERVER[PHP_SELF] method=\"post\">";
	print "<div style=\"text-align: center;\">$font<small>Dette vil overskrive den valgte danske formular med standardops&aelig;tningen<br>";
	print "<div style=\"text-align: center;\">$font<small>\"Alle\" overskriver alle formularer og sletter formularer på andre sprog end dansk<br>";
	print "<div style=\"text-align: center;\">$font og slette formularer p&aring; andre sprog end dansk.<br>";
	print "<input type=\"submit\" style=\"width:150px\" value=\"Afbryd\" name=\"afbryd\"><br><br>
		<input type=\"submit\" style=\"width:150px\" value=\"Tilbud\" name=\"valg\"><br><br>
		<input type=\"submit\" style=\"width:150px\" value=\"Ordrebekræftelse\" name=\"valg\"><br><br>
		<input type=\"submit\" style=\"width:150px\" value=\"Følgeseddel\" name=\"valg\"><br><br>
		<input type=\"submit\" style=\"width:150px\" value=\"Faktura\" name=\"valg\"><br><br>
	<input type=\"submit\" style=\"width:150px\" value=\"Kreditnota\" name=\"valg\"><br><br>
	<input type=\"submit\" style=\"width:150px\" value=\"Rykker_1\" name=\"valg\"><br><br>
	<input type=\"submit\" style=\"width:150px\" value=\"Rykker_2\" name=\"valg\"><br><br>
	<input type=\"submit\" style=\"width:150px\" value=\"Rykker_3\" name=\"valg\"><br><br>
	<input type=\"submit\" style=\"width:150px\" value=\"Plukliste\" name=\"valg\"><br><br>
	<input type=\"submit\" style=\"width:150px\" value=\"Kontokort\" name=\"valg\"><br><br>
	<input type=\"submit\" style=\"width:150px\" value=\"Indkøbsforslag\" name=\"valg\"><br><br>
	<input type=\"submit\" style=\"width:150px\" value=\"Rekvisition\" name=\"valg\"><br><br>
	<input type=\"submit\" style=\"width:150px\" value=\"Købsfaktura\" name=\"valg\"><br><br>
	<input type=\"submit\" style=\"width:150px\" value=\"Alle\" name=\"valg\"><br><br>";
	print "</small></font></div></form>";
}
?>
</tbody></table>
</body></html>
