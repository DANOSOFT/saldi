<?php
// ------------------systemdata/kontoplan.php-----lap 1.1.4------12.12.07----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg

// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------

	@session_start();
	$s_id=session_id();
	$title="Kontoplan";
	$modulnr=1;
	$linjebg='';
	
	
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/settings.php");
	include("../includes/db_query.php");
	include("../includes/dkdecimal.php");
	

print "<div align=\"center\">";
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund align=\"left\">$font<small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
print "<td width=\"80%\" $top_bund align=\"center\">$font<small>Kontoplan</small></td>";
print "<td width=\"10%\" $top_bund align=\"right\">$font<small><a href=kontokort.php accesskey=N>Ny</a></small></td>";
print "</tbody></table>";
print "</td></tr>";
print "<tr><td valign=\"top\">";
print "<table cellpadding=\"0\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
print "<tbody>";
print "<tr>";
print "<td><b>$font Kontonr</b></td>";
print "<td><b>$font Beskrivelse</a></b></td>";
print "<td><b>$font Type</a></b></td>";
print "<td align=center><b>$font Moms</a></b></td>";
print "<td align=center><b>$font Saldo</a></b></td>";
print "<td align=center><b>$font Genvej</a></b></td>";
print "</tr>";

	if (!$regnaar) {$regnaar=1;} 
	$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr");
	while ($row = db_fetch_array($query)){
		if ($row['lukket']=='on') $beskrivelse="Lukket ! - ".stripslashes($row['beskrivelse']);
		else $beskrivelse=stripslashes($row['beskrivelse']);
		if ($linjebg!=$bgcolor) {$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5) {$linjebg=$bgcolor5; $color='#000000';}

		if ($row['kontotype']=='H') {$linjebg=$bgcolor4; $color='$000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><small><a href=kontokort.php?id=$row[id]><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[kontonr]</a><br></small></td>";
		print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$beskrivelse<br></small></td>";
		if ($row['kontotype']=='H') print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><br></small></td>";
		elseif ($row['kontotype']=='D') print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">Drift<br></small></td>";
		elseif ($row['kontotype']=='S') print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">Status<br></small></td>";
		elseif ($row['kontotype']=='Z') print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">Sum $row[fra_kto] - $row[til_kto]<br></small></td>";
		else print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">Sideskift<br></small></td>";
		print "<td align=center><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[moms]<br></small></td>";
		if (($row['kontotype']!='H')&&($row['kontotype']!='X'))print "<td align=right><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">".dkdecimal($row['saldo'])."<br></small></td>";
		else print "<td><br></td>";
		print "<td align=center><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[genvej]<br></small></td>";
		print "</tr>";
		if ($row['kontotype']=='H') {$linjebg=$bgcolor4; $color='#000000';}

	}

?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
