<?php
	@session_start();
	$s_id=session_id();

// --------/systemdata/formular_indlaes_std.php---lap 3.2.9---2013-05-10--
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20130510, parameter 2 til formularimport

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/formularimport.php");

if ($_POST) {
	if ($_POST['overskriv']) {
		db_modify("delete from formularer");
		formularimport("../importfiler/formular.txt,''");
		db_modify("update formularer set sprog = 'Dansk'");
		print "<div style=\"text-align: center;\">$font<small>Overskrivning med standardformularer succesfuld - vinduet lukkes</small></font><br></div>";
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
	print "<div style=\"text-align: center;\">$font og slette formularer p&aring; andre sprog end dansk.<br>";
	print "Klik p&aring; [Overskriv] for at forts&aelig;tte.<br><br>";
	print "<input type=submit value=\"Afbryd\" name=\"afbryd\">&nbsp;&nbsp;<input type=submit value=\"Overskriv\" name=\"overskriv\">";
	print "</small></font></div></form>";
}
?>
</tbody></table>
</body></html>
