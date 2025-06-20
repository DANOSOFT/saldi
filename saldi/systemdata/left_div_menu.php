<?php
// ------------systemdata/left_div_menu.php----lap 3.4.3---2014-05-23----
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
// // Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2019.02.25 MSC - Rettet isset fejl

if (!isset ($docubizz)) $docubizz = null;

print "<div class=\"leftmenu\">
	<div class=\"leftmenuhead link\">Systemdata</div>
	<ul>";
print "<li><a href=\"syssetup.php\"><b>&#9668; Til systemdata</b></a></li>\n";
print "<li><hr></li>\n";
print "<li><a href=\"diverse.php?sektion=kontoindstillinger\">Kontoindstillinger</a></li>\n";
print "<li><a href=\"diverse.php?sektion=provision\">Provisionsberegning</a></li>\n";
print "<li><a href=\"diverse.php?sektion=personlige_valg\">Personlige valg</a></li>\n";
print "<li><a href=\"diverse.php?sektion=ordre_valg\">Ordrerelaterede valg</a></li>\n";
print "<li><a href=\"diverse.php?sektion=vare_valg\">Varelaterede valg</a></li>\n";
print "<li><a href=\"diverse.php?sektion=prislister\">".findtekst(427,$sprog_id)."</a><!--tekst 427--></li>\n";
print "<li><a href=\"diverse.php?sektion=rykker_valg\">Rykkerrelaterede valg</a></li>\n";
print "<li><a href=\"diverse.php?sektion=div_valg\">Diverse valg</a></li>\n";
print "<li><a href=\"diverse.php?sektion=tjekliste\">Tjeklister</a></li>\n";
if ($docubizz) print "<li><a href=\"diverse.php?sektion=docubizz\">DocuBizz</a></li>\n";
print "<li><a href=\"diverse.php?sektion=bilag\">Bilagshåndtering</a></li>\n";
print "<li><a href=\"diverse.php?sektion=orediff\">".findtekst(170,$sprog_id)."</a><!--tekst 170--></li>\n";
print "<li><a href=\"diverse.php?sektion=massefakt\">".findtekst(200,$sprog_id)."</a><!--tekst 200--></li>\n";
if (file_exists("../debitor/pos_ordre.php")) print "<li><a href=\"diverse.php?sektion=pos_valg\">".findtekst(271,$sprog_id)."</a><!--tekst 271--></li>\n";
# print "<li><a href=diverse.php?sektion=email>Mail indstillinger</a></li>";
print "<li><a href=\"diverse.php?sektion=sprog\">Sprog</a></li>\n";
# print "<li><a href=diverse.php?sektion=kontoplan_io>Indl&aelig;s  / udl&aelig;s kontoplan</a></li>";
print "<li><a href=\"diverse.php?sektion=div_io\">Import &amp; eksport</a></li>\n";
	print "</ul>

	</div><!-- end of leftmenu -->";
/*
<div class=\"leftmenu\">
	<ul>
		<li><a href=\"#\">underlink 1</a></li>
		<li><a href=\"#\">underlink 2</a></li>
		<li><a href=\"#\">underlink 3</a></li>
		<li><a href=\"#\">underlink 4</a></li>
		<li><a href=\"#\">underlink 5</a></li>
	</ul>
	<hr> 
	<ul>
		<li><a href=\"#\">underlink 1</a></li>
		<li><a href=\"#\">underlink 2</a></li>
		<li><a href=\"#\">underlink 3</a></li>
		<li><a href=\"#\">underlink 4</a></li>
		<li><a href=\"#\">underlink 5</a></li>
	</ul>
	</div><!-- end of leftmenu -->"
*/
?>
