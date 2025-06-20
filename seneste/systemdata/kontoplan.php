<?php
// ------------------systemdata/kontoplan.php-----lap 3.2.9-----2012-05-01----
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
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$title="Kontoplan";
$css="../css/standard.css";
$modulnr="0";
$linjebg='';
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

print "<div align=\"center\">";
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund align=\"left\"><a href=$returside accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\">Kontoplan</td>";
print "<td width=\"10%\" $top_bund align=\"right\"><a href=kontokort.php accesskey=N>Ny</a></td>";
print "</tbody></table>";
print "</td></tr>";
print "<tr><td valign=\"top\">";
print "<table cellpadding=\"0\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
print "<tbody>";
print "<tr>";
print "<td><b> Kontonr.</b></td>";
print "<td><b> Kontonavn</a></b></td>";
print "<td><b> Type</a></b></td>";
print "<td align=center><b> Moms</a></b></td>";
print "<td align=center><b> Saldo</a></b></td>";
print "<td align=center><b> Genvej</a></b></td>";
print "</tr>";

	if (!$regnaar) {$regnaar=1;} 
	$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		if ($row['lukket']=='on') $beskrivelse="Lukket ! - ".stripslashes($row['beskrivelse']);
		else $beskrivelse=stripslashes($row['beskrivelse']);
		if ($linjebg!=$bgcolor) {$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5) {$linjebg=$bgcolor5; $color='#000000';}
		if ($row['kontotype']=='H') {$linjebg=$bgcolor4; $color='$000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href=kontokort.php?id=$row[id]><span color=\"$color\">$row[kontonr]</a><br></span></td>";
		print "<td><span color=\"$color\">$beskrivelse<br></span></td>";
		if ($row['kontotype']=='H') print "<td><span color=\"$color\"><br></span></td>";
		elseif ($row['kontotype']=='D') print "<td><span color=\"$color\">Drift<br></span></td>";
		elseif ($row['kontotype']=='S') print "<td><span color=\"$color\">Status<br></span></td>";
		elseif ($row['kontotype']=='Z') print "<td><span color=\"$color\">Sum $row[fra_kto] - $row[til_kto]<br></span></td>";
		elseif ($row['kontotype']=='R') print "<td><span color=\"$color\">Resultat = $row[fra_kto]<br></span></td>";
		else print "<td><span color=\"$color\">Sideskift<br></span></td>";
		print "<td align=center><span color=\"$color\">$row[moms]<br></span></td>";
		if (($row['kontotype']!='H')&&($row['kontotype']!='X'))print "<td align=right><span color=\"$color\">".dkdecimal($row['saldo'])."<br></span></td>";
		else print "<td><br></td>";
		print "<td align=center><span color=\"$color\">$row[genvej]<br></span></td>";		
		print "</tr>";
		if ($row['kontotype']=='H') {$linjebg=$bgcolor4; $color='#000000';}
	
}

?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
