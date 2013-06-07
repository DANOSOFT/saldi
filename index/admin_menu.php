<?php
// --index/admin_menu.php------lap 3.1.2------2011-01-30----------------
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
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------


@session_start();  # Skal angives oeverst i filen??!!
$s_id=session_id();
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$modulnr=100;

if ($db != $sqdb) {
	print "<BODY onLoad=\"javascript:alert('Hmm du har vist ikke noget at g&oslash;re her! Dit IP nummer, brugernavn og regnskab er registreret!')\">\n";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">\n";
	exit;
}

$q = db_select("select * from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
if ($bruger_id=$r['id']) {
	$rettigheder=$r['rettigheder'];
#	if (strstr($rettigheder,",")=='0') echo "NUL<br>";
	if (strstr($rettigheder,",")==false) {
		$rettigheder="on,on,on,*";
		db_modify("update brugere set rettigheder='$rettigheder' where id='$bruger_id'",__FILE__ . " linje " . __LINE__);
	}
	list($admin,$oprette,$slette,$tmp)=explode(",",$rettigheder,4);
	$adgang_til=explode(",",$tmp);
}
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>\n";
print "  <td $top_bund width=\"10%\">Ver $version</td>\n";
print "  <td $top_bund width=\"35%\">&nbsp;</td>\n";
print "  <td $top_bund width=\"10%\" align = \"center\"><a href=\"../http://saldi.dk/dok/komigang.html\" target=\"_blank\">Vejledning</a></td>\n";
print "<td $top_bund width=\"35%\">&nbsp;</td>";
print "<td $top_bund width=\"10%\" align = \"right\"><a href=\"logud.php\" accesskey=\"L\">Log ud</a></td>\n";
print "</tr></tbody></table></td></tr>\n<tr><td align=\"center\" valign=\"center\">\n";

$td=" align=\"center\" height=\"35\"";

print"<table width=\"20%\" align=\"center\" border=\"4\" cellspacing=\"5\" cellpadding=\"0\"><tbody>";
print"<tr>";
print"<td colspan=\"5\" height=\"35\" align=\"center\" background=\"../img/blaa2hvid_bg.gif\"><big<big><big><b>SALDI</b></big></big></big></td>";
print"</tr><tr>";
print"<td  height=\"35\" align=\"center\"><b><big>Administrationsmenu</big></b></td>";
print"</tr><tr>";
if ($admin || $oprette) print"<td $td $stor_knap_bg><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/opret.php\"><big>".findtekst(339,$sprog_id)."</big></td>";
else print "<td $td $stor_knap_bg><span style=\"color:#999;\"><big>".findtekst(339,$sprog_id)."</big></td>\n";
if ($revisorregnskab) {
#	print"</tr><tr>";
#	print"<td $td $stor_knap_bg><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/stdkontoplan.php\"><br></td>";
	print"</tr><tr>";
	print"<td $td $stor_knap_bg><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/vis_regnskaber.php\"><big>".findtekst(340,$sprog_id)."</big></td>";
	print"</tr><tr>";
	if ($admin || $slette) print"<td $td $stor_knap_bg><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/slet_regnskab.php\"><big>".findtekst(341,$sprog_id)."</big></td>";
	else print "<td $td $stor_knap_bg><span style=\"color:#999;\"><big>".findtekst(341,$sprog_id)."</big></td>\n";
	print"</tr><tr>";
	print"<td $td $stor_knap_bg><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/admin_brugere.php\"><big>Brugere</big></td>";
}
print"</tr>";
print"</tbody></table>";
print"</td></tr>";


print"<tr><td align=\"center\" valign=\"bottom\">";
print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print"<td align=\"left\" width=\"100%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000000\">&nbsp;Copyright&nbsp;&copy;&nbsp;2003-2011&nbsp;DANOSOFT&nbsp;ApS</td>";
print"</tbody></table>";
print"</td></tr>";
print"</tbody></table>";
print"</body></html>";

/*
  <tr><td align=\"center\" valign=\"bottom\">
    <table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
      <td width=\"25%\" $top_bund>Copyright (c) 2004-2008 DANOSOFT ApS</td>
      <td width=\"50%\" $top_bund align = \"center\"></td>
      <td width=\"25%\" $top_bund align = \"right\"></td>
    </tbody></table>
  </td></tr>
</tbody></table>
</body></html>
*/
?>
