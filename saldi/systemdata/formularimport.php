<?php
	@session_start();
	$s_id=session_id();

// --------------------------------/systemdata/formularimport.php-----lap 1.0.7----------------------------------------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

if ($_POST) {
	if ($_POST['ok']) {
		$fp=fopen("../importfiler/formular.txt","r");
		if ($fp) {
			db_modify("delete from formularer");
			while (!feof($fp)) {
				list($formular, $art, $beskrivelse, $placering, $xa, $ya, $xb, $yb, $str, $color, $font, $fed, $kursiv, $side) = split(chr(9), fgets($fp));
# echo "Formular $formular, Art $art, beskrivelse $beskrivelse, xa $xa, ya $ya, xb $xb, yb$yb, plac $placering, str $str, color $color, font $font, fed $fed, kursiv $kursiv, side $side<br>";				
				if ($xa>0) {
					$placering=trim($placering); $form=trim($font); $fed=trim($fed); $kursiv=trim($kursiv); $side=trim($side); 
					$xa= $xa*1; $ya= $ya*1; $xb= $xb*1; $yb=$yb*1; $str=$str*1; $color=$color*1;
					db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, placering, str, color, font, fed, kursiv, side) values	('$formular', '$art', '$beskrivelse', '$xa', '$ya', '$xb', '$yb', '$placering', '$str', '$color', '$font', '$fed', '$kursiv', '$side')"); 
				}
			}
			fclose($fp);
			print "<div style=\"text-align: center;\">$font<small>Import succesfuld - vindue lukkes</small></font><br></div>";
			print "<meta http-equiv=\"refresh\" content=\"3;URL=../includes/luk.php\">";
			exit;
		}
	}
	else {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	exit;
	}
}
else {
	print "<form name=formularimport action=$_SERVER[PHP_SELF] method=\"post\">";
	print "<div style=\"text-align: center;\">$font<small>Dette vil overskrive alle eksisterende formularer<br>";
	print "Klik på [OK] for at fortsætte<br><br>";
	print "<input type=submit value=\"Afbryd\" name=\"afbryd\">&nbsp;&nbsp;<input type=submit value=\"OK\" name=\"ok\">";
	print "</small></font></div></form>";
}
?>
</tbody></table>
</body></html>
