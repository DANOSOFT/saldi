<?php
// --- /systemdata/formularimport.php-----patch 4.0.8 ----2023-07-22--------------
//                           LICENSE
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
// ----------------------------------------------------------------------
# SKAL OMSKRIVES TIL IMPORT AF SKABELONER og ikke overskrivning med standardskabeloner

@session_start();
$s_id=session_id();

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
	print "Klik p� [OK] for at forts�tte<br><br>";
	print "<input type=submit value=\"Afbryd\" name=\"afbryd\">&nbsp;&nbsp;<input type=submit value=\"OK\" name=\"ok\">";
	print "</small></font></div></form>";
}
?>
</tbody></table>
</body></html>
