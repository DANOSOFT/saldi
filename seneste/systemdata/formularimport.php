<?php
	@session_start();
	$s_id=session_id();

// ----------/systemdata/formularimport.php-----lap 2.0.7b---2009-05-26-23:30--
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------

# SKAL OMSKRIVES TIL IMPORT AF SKABELONER og ikke overskrivning med standardskabeloner
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/formularimport.php");

if ($_POST) {
	if ($_POST['ok']) {
		db_modify("delete from formularer");
		formularimport("../importfiler/formular.txt");
		db_modify("update formularer set sprog = 'Dansk'");
		print "<div style=\"text-align: center;\">$font<small>Import succesfuld - vindue lukkes</small></font><br></div>";
		print "<meta http-equiv=\"refresh\" content=\"3;URL=../includes/luk.php\">";
		exit;
	}
	else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		exit;
	}
}
else {
	print "<form name=formularimport action=$_SERVER[PHP_SELF] method=\"post\">";
	print "<div style=\"text-align: center;\">$font<small>Dette vil overskrive alle eksisterende formularer med standardops&aelig;tningen<br>";
	print "<div style=\"text-align: center;\">$font og slette formularer med andet sprog end dansk<br>";
	print "Klik på [OK] for at fortsætte<br><br>";
	print "<input type=submit value=\"Afbryd\" name=\"afbryd\">&nbsp;&nbsp;<input type=submit value=\"OK\" name=\"ok\">";
	print "</small></font></div></form>";
}
?>
</tbody></table>
</body></html>
