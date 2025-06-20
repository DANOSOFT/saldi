<?php
// ----------/lager/labelprint.php----------------lap 3.4.2---2014-06-03---
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
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// 
// 2014.06.17 Tilføjet pris pr. enhed på etiketter, hvis de er der. PHR - Danosoft. 20140617
// 2014.09.01 Tilføjet opsætning til Cognitive printer - Anvendes hvis det ikke er beskrivelse.

include("../includes/std_func.php");

$beskrivelse=$_GET['beskrivelse'];
$stregkode=$_GET['stregkode'];
$src=$_GET['src'];
$pris=$_GET['pris'];
$enhed=$_GET['enhed'];
$indhold=$_GET['indhold'];


print "<center>\n";
if ($beskrivelse) { #Dymo
  print "<table  border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<tr><td></td></tr>\n";
  print "<tr><td align=\"center\"><font face=\"verdana\" size=\"2\">$beskrivelse</font></td></tr>\n";
  print "<tr><td align=\"center\"><font face=\"verdana\" size=\"2\">Pris: ".dkdecimal($pris);
  if ($enhed && $indhold) {
	print " (".dkdecimal($pris/$indhold)."/$enhed)";
  }
  print "</font></td></tr>\n";
  print "<tr><td align=\"center\"><img style=\"border:0px solid;width:250px;height:30px;overflow:hidden;\" alt=\"\" src=\"$src\"></td></tr>\n";
  print "<tr><td align=\"center\"><font face=\"verdana\" size=\"2\">$stregkode</font></td></tr>\n";
} else { # Cognetive
  $dkkpris=dkdecimal($pris);
  $dkkpris=str_replace(',00',',-',$dkkpris);
  print "<table  border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<tr><td align=\"center\" rowspan=\"2\"><font face=\"verdana\" size=\"5\">$dkkpris</font></td>";
  print "<td width=\"6px\"></td><td align=\"center\"><img style=\"border:0px solid;width:150px;height:50px;\" alt=\"\" src=\"$src\"></td></tr>\n";
  print "<tr><td rowspan=\"2\"></td><td align=\"center\"><font face=\"verdana\" size=\"2\">$stregkode</font></td></tr>\n";
}
print "</tbody></table>\n";
print "<body onLoad=\"javascript:window.print();javascript:window.close();\">\n";
print " <br>\n";
?>
