<?php
// ------------systemdata/left_div_menu.php-----patch 4.0.8 ----2023-07-22--
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
