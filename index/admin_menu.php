<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ admin/admin_menu --------------- ver 4.0.4 --- 2021-09-16 ---
// LICENSE
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
//
// Copyright (c) 2003-2021 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20180131 
// 20210916 LOE translated some texts 

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
print "  <td $top_bund width=\"10%\" align = \"center\"><a href=\"../http://saldi.dk/dok/komigang.html\" target=\"_blank\">".findtekst(92, $sprog_id)."</a></td>\n";#Vejlednin 20210916
print "<td $top_bund width=\"35%\">&nbsp;</td>";
print "<td $top_bund width=\"10%\" align = \"right\"><a href=\"logud.php\" accesskey=\"L\">".findtekst(93, $sprog_id)."</a></td>\n"; #Log ud
print "</tr></tbody></table></td></tr>\n<tr><td align=\"center\" valign=\"center\">\n";

$td=" align=\"center\" height=\"35\"";

print"<table width=\"20%\" align=\"center\" border=\"4\" cellspacing=\"5\" cellpadding=\"0\"><tbody>";
print"<tr>";
print"<td colspan=\"5\" height=\"35\" align=\"center\" background=\"../img/blaa2hvid_bg.gif\"><big<big><big><b>SALDI</b></big></big></big></td>";
print"</tr><tr>";
print"<td  height=\"35\" align=\"center\"><b><big>Administration menu</big></b></td>";
print"</tr><tr>";
if ($admin || $oprette) print"<td $td $stor_knap_bg><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/opret.php\"><big>".findtekst(339,$sprog_id)."</big></td>";
else print "<td $td $stor_knap_bg><span style=\"color:#999;\"><big>".findtekst(339,$sprog_id)."</big></td>\n";
if ($revisorregnskab || $forhandlerregnskab) {
#	print"</tr><tr>";
#	print"<td $td $stor_knap_bg><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/stdkontoplan.php\"><br></td>";
	print "</tr><tr>";
	print "<td $td $stor_knap_bg><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/vis_regnskaber.php\"><big>".findtekst(340,$sprog_id)."</big></td>";
	print"</tr><tr>";
	if ($admin || $slette) print"<td $td $stor_knap_bg><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/slet_regnskab.php\"><big>".findtekst(341,$sprog_id)."</big></td>";
	else print "<td $td $stor_knap_bg><span style=\"color:#999;\"><big>".findtekst(341,$sprog_id)."</big></td>\n";
	print"</tr><tr>";
	print"<td $td $stor_knap_bg><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/admin_brugere.php\"><big>".findtekst(777, $sprog_id)."</big></td>"; #Brugere 20210916
	print"</tr><tr>";
	print"<td $td $stor_knap_bg><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/admin_settings.php\"><big>".findtekst(613, $sprog_id)."</big></td>"; #Indstillinger
	if (isset($mastername) && $mastername=="ROTARY") {
		print"</tr><tr>";
		print"<td $td $stor_knap_bg><a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../admin/bankfordeling.php\"><big>".findtekst(567, $sprog_id)."</big></td>";#Kortbetalinger
	}
}
print"</tr>";
print"</tbody></table>";
print"</td></tr>";


print "<tr><td align=\"center\" valign=\"bottom\">";
#print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<div class=top_bund><small>SALDI&nbsp;version&nbsp;$version&nbsp;-&nbsp;Copyright&nbsp;&copy;&nbsp;$copyright&nbsp;</small></div></td></tr>\n";
#print"<td align=\"left\" width=\"100%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000000\">&nbsp;Copyright&nbsp;&copy;&nbsp;2003-2011&nbsp;DANOSOFT&nbsp;ApS</td>";
#print "</tbody></table>";
print "</td></tr>";
print "</tbody></table>";
print "</body></html>";

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
