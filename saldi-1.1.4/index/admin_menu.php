<?php
// -----------------------------------------------index/admin_menu.php-----lap 1.1.2_20070908-------------------
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
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------


@session_start();  # Skal angives oeverst i filen??!!
$s_id=session_id();
$revisorregnskab=1;
include("../includes/connect.php");
include("../includes/online.php");

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td  $top_bund width=\"10%\">$font <small>Ver $version</small></td>";
print "<td  $top_bund width=\"35%\"><small>&nbsp;</small></td>";
print "<td  $top_bund width=\"10%\" align = \"center\">$font <a href=komigang.html target=blank><small>Vejledning</small></td>\n";
print "<td  $top_bund width=\"35%\"><small>&nbsp;</small></td>";
print "<td  $top_bund width=\"10%\" align = \"right\">$font <a href=logud.php accesskey=L><small>Logud</small></td>\n";
print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"center\">\n";

print"<table width=\"20%\" align=\"center\" border=\"4\" cellspacing=\"5\" cellpadding=\"0\"><tbody>";
print"<tr>";
print"<td colspan=\"5\" height=\"35\" align=\"center\" background=\"../img/blaa2hvid_bg.gif\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><big><big><b>SALDI BETA 2.0</b></big></big></td>";
print"</tr><tr>";
print"<td  height=\"35\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><b>Administrations menu</b></td>";
print"</tr><tr>";
print"<td align=\"center\" height=\"35\" $stor_knap_bg><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/opret.php\">Opret regnskab</td>";
if ($revisorregnskab) {
#	print"</tr><tr>";
#	print"<td align=\"center\" height=\"35\" $stor_knap_bg><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/stdkontoplan.php\"><br></td>";
	print"</tr><tr>";
	print"<td align=\"center\" height=\"35\" $stor_knap_bg><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/vis_regnskaber.php\">Vis regnskaber</td>";
	print"</tr><tr>";
	print"<td align=\"center\" height=\"35\" $stor_knap_bg><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/slet_regnskab.php\">Slet regnskab</td>";
}
print"</tr>";
print"</tbody></table>";
print"</td></tr>";


print"<tr><td align=\"center\" valign=\"bottom\">";
print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print"<td align=\"left\" width=\"100%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000000\"><small><small>&nbsp;Copyright&nbsp;&copy;&nbsp;2003-2008&nbsp;DANOSOFT&nbsp;ApS</small></small></td>";
print"</tbody></table>";
print"</td></tr>";
print"</tbody></table>";
print"</body></html>";

/*
  <tr><td align=\"center\" valign=\"bottom\">
    <table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
      <td width=\"25%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Copyright (c) 2004-2008 DANOSOFT ApS</small></td>
      <td width=\"50%\" $top_bund align = \"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"></td>
      <td width=\"25%\" $top_bund align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"></td>
    </tbody></table>
  </td></tr>
</tbody></table>
</body></html>
*/
?>