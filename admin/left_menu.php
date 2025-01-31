<?php
// ------admin/left_menu.php---lap 3.3.0--2013-01-05---11:14-----
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

print "<div class=\"leftmenu\">
<div class=\"leftmenuhead link\">Backup</div>
<ul>";
	print "<li><a href=\"syssetup.php?valg=moms\" accesskey=\"M\"></a></li>";
	print "<li><a href=\"syssetup.php?valg=debitor\" accesskey=\"D\"></a></li>";
	print "<li><a href=\"syssetup.php?valg=afdelinger\" accesskey=\"A\"></a></li>";
	print "<li><a href=\"projekter.php\" accesskey=\"P\"></a></li>";
	print "<li><a href=\"syssetup.php?valg=lagre\" accesskey=\"G\"></a></li>";
	print "<li><a href=\"syssetup.php?valg=varer\" accesskey=\"V\"></a></li>";
	print "<li><a href=\"rabatgrupper.php\" accesskey=\"V\"></a></li>";
	print "<li><a href=\"valuta.php\" accesskey=\"U\"></a></li>";
	print "<li><a href=\"brugere.php\" accesskey=\"B\"></a></li>";
	print "<li><a href=\"regnskabsaar.php\" accesskey=\"R\"></a></li>";
	print "<li><a href=\"stamkort.php\" accesskey=\"S\"></a></li>";
	print "<li><a href=\"formularkort.php?valg=formularer\" accesskey=\"F\"></a></li>";
	print "<li><a href=\"enheder.php\" accesskey=\"E\"></a></li>";
	print "<li><a href=\"diverse.php?valg=diverse\" accesskey=\"I\"></a></li>";
	print "</ul>

	</div><!-- end of leftmenu -->";
?>