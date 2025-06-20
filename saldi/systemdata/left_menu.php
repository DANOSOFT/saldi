<?php
// ----------------systemdata/left_menu.php -- lap 3.5.5 -- 2015-03-27 --
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// // Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20141030 CA  Ændret projekter til topmenu-design - søg 20141030
// 20150217 CA  Ændret varegrupper til topmenu-design - søg 20150217
// 20150217 CA  Tilføjet linjeskift i HTML-koden, så fejlsøgning lettes
// 20150313 CA  Ændret henvisning til rabatgrupper - søg 20150313
print "<div class=\"leftmenu\">";
	if (strpos($_SERVER['SCRIPT_NAME'],"kontoplan.php")) {
		print "<div class=\"leftmenuhead link\">Kontoplan</div>\n<ul>";
		print "<li><a href=\"kontokort.php\">Ny konto</a></li>\n";
		print "</ul>\n";
	} elseif (strpos($_SERVER['SCRIPT_NAME'],"kontokort.php")) {
		print "<div class=\"leftmenuhead link\">Kontolkort</div><ul>";
		print "<li><a href=\"kontoplan.php\">Kontoplan</a></li>\n";
		print "<li><a href=\"kontokort.php\">Ny konto</a></li>\n";
		print "</ul>\n";
	} else {
		print "<div class=\"leftmenuhead link\">Systemdata</div>
		<ul>";
			print "<li><a href=\"syssetup.php?valg=moms\" accesskey=\"M\">Moms</a></li>\n";
			print "<li><a href=\"syssetup.php?valg=debitor\" accesskey=\"D\">Debitor-/kreditorgrupper</a></li>\n";
			print "<li><a href=\"syssetup.php?valg=afdelinger\" accesskey=\"A\">Afdelinger</a></li>\n";
			print "<li><a href=\"syssetup.php?valg=projekter\" accesskey=\"P\">Projekter</a></li>\n"; # 20141030
#			print "<li><a href=\"projekter.php\" accesskey=\"P\">Projekter</a></li>\n";
			print "<li><a href=\"syssetup.php?valg=lagre\" accesskey=\"G\">Lagre</a></li>\n";
			print "<li><a href=\"syssetup.php?valg=varer\" accesskey=\"V\">Varegrupper</a></li>\n"; # 20150217
#			print "<li><a href=\"syssetup.php?valg=rabatgrupper\" accesskey=\"T\">Rabatgrupper</a></li>\n"; # 20150217 - ændret accesskey
			print "<li><a href=\"rabatgrupper.php\" accesskey=\"T\">Rabatgrp</a></li>\n"; # 20150313
#			print "<li><a href=\"rabatgrupper.php\" accesskey=\"V\">Rabatgrp</a></li>\n";
			print "<li><a href=\"valuta.php\" accesskey=\"U\">Valuta</a></li>\n";
#			print "<li><a href=\"valuta.php\" accesskey=\"U\">Valuta</a></li>\n";
			print "<li><a href=\"brugere.php\" accesskey=\"B\">Brugere</a></li>\n";
#			print "<li><a href=\"brugere.php\" accesskey=\"B\">Brugere</a></li>\n";
			print "<li><a href=\"regnskabsaar.php\" accesskey=\"R\">Regnskabs&aring;r</a></li>\n";
#			print "<li><a href=\"regnskabsaar.php\" accesskey=\"R\">Regnskabs&aring;r</a></li>\n";
			print "<li><a href=\"stamkort.php\" accesskey=\"S\">Stamdata</a></li>\n";
#			print "<li><a href=\"stamkort.php\" accesskey=\"S\">Stamdata</a></li>\n";
			print "<li><a href=\"formularkort.php?valg=formularer\" accesskey=\"F\">Formularer</a></li>\n";
#			print "<li><a href=\"syssetup.php?valg=formularer\" accesskey=\"F\">Formularer</a></li>\n";
#			print "<li><a href=\"enheder.php\" accesskey=\"E\">Enh/mat</a></li>\n";
			print "<li><a href=\"enheder.php\" accesskey=\"E\">Enheder/materialer</a></li>\n"; # 20150217 - skrevet fuldt ud
			print "<li><a href=\"diverse.php?valg=diverse\" accesskey=\"I\">Diverse</a></li>\n";
		print "</ul>";
	}
	print "</div><!-- end of leftmenu -->";
?>
