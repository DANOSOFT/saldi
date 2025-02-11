<?php
// -------- debitor/ansatte.php (modul nr. 6)----------lap 2.1.4 ----- 2010.03.26----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

#if (strpos($_SERVER['PHP_SELF'],"kunder.php")) print "<form name=\"ansatte\" action=\"kunder.php?funktion=ret_kunde_ansat&konto_id=$konto_id\" method=\"post\">\n";
#else print "<form name=ansatte action=\"ansatte.php\" method=\"post\">\n";
print "<input type=hidden name=ansat_id value=\"$ansat_id\">\n";
print "<input type=hidden name=konto_id value=\"$konto_id\">\n";
print "<input type=hidden name=ordre_id value=\"$ordre_id\">\n";
print "<input type=hidden name=returside value=\"$returside\">\n";
print "<input type=hidden name=fokus value=\"$fokus\">\n";

print "<div style=\"float:left; margin-right:70px; width:379px;\">\n";
print "<h3>Kontaktperson</h3>\n";
print "<div class=\"contentA\">\n";
print "<div class=\"row\"><div class=\"left\">Navn</div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"navn\" value=\"$navn\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "<div class=\"row\"><div class=\"left\">Adresse</div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"addr1\" value=\"$addr1\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "<div class=\"row\"><div class=\"left\">Adresse2</div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"addr2\" value=\"$addr2\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "<div class=\"row\"><div class=\"left\">Postnr. &amp; by</div><div class=\"right\"><input class=\"textSmall textIndent\" type=\"text\" name=\"postnr\" value=\"$postnr\"><input class=\"textMediumLarge textSpace textIndent\" type=\"text\" name=\"bynavn\" value=\"$bynavn\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "</div><!-- end of contentA -->\n";
print "</div><!-- end of left container -->\n";
print "<div style=\"float:left; width:379px;\">\n";
print "<h3>&nbsp;</h3>\n";
print "<div class=\"contentA\">\n";
print "<div class=\"row\"><div class=\"left\">E-mail</div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"email\" value=\"$email\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
#print "<td>CVR. nr.</td><td><br></td><td><input type=text size=10 name=cprnr value=\"$cprnr\"></td></tr>";
print "<div class=\"row\"><div class=\"left\">Mobil</div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"mobil\" value=\"$mobil\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "<div class=\"row\"><div class=\"left\">Lokalnr.</div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"tlf\" value=\"$tlf\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "<div class=\"row\"><div class=\"left\">Lokal fax</div><div class=\"right\"><input type=text class=\"text textIndent\" name=\"fax\" value=\"$fax\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "</div><!-- end of contentA -->\n";
print "</div><!-- end of right container -->\n";
print "<div style=\"float:left; width:828px;\">\n";
print "<div class=\"contentA\" style=\"width:808px;;\">\n";
print "<div class=\"row\"><div class=\"left\">Bem&aelig;rkning</div><div class=\"right\"><textarea style=\"min-width:681px;max-width:681px;\" class=\"textArea\" name=\"notes\" rows=\"3\" cols=\"76\">$notes</textarea></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "</div><!-- end of contentA -->\n";
print "</div><!-- end of full container -->\n";

#print "<td colspan=\"7\" align = \"center\"><input type=\"submit\" style=\"width:100px\" accesskey=\"g\" value=\"Gem\" name=\"submit\">&nbsp;&nbsp;&nbsp;<input type=\"submit\" style=\"width:100px\" accesskey=\"s\" value=\"Slet\" name=\"submit\"></td>\n";
?>
