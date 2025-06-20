<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------lager/ret_varenr.php-------------patch 3.7.1 -- 20180518----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2018 saldi.dk aps
// ----------------------------------------------------------------------
// 2015.12.08 Tilføjet flet funtion til sammenlægning af varer.
// 2018.05.15 Kontrol mod flet af styklister og vares som indgår.

print "<table style='width:100%;vertical-align:center'><tbody>";
print "<tr><td><form name='none' action='varekort.php?id=$id' method='post'></td></tr>";
print "<tr><td align=center><hr width=50%></td></tr>";
print "<tr><td align=center><big>Du er ved at rette beskrivelse fra:<br><br><b> $oldDescription </b><br><br>til:<br><br><b> $beskrivelse[0]</b> !</big></td></tr>";
print "<tr><td align=center><br></td></tr>";
print "<tr><td align=center><big>Er det det du vil ?</big></td></tr>";
print "<tr><td align=center><br></td></tr>";
print "<tr><td align=center><input type='hidden' value='$id' name=\"id\">";
print "<tr><td align=center><input type='hidden' value='$beskrivelse[0]' name=\"newDecsription\">";
print "<tr><td align=center><input type='submit' accesskey='j' style='width:100px;height:100px;' value='Ja' name=\"ChangeDescription\"> &nbsp; ";
print "<input type='submit' accesskey='n' style='width:100px;height:100px;' value='Nej' name=\"ChangeDescription\"></td></tr>";
print "</form>";

print "</tbody></table";
print "</td></tr>\n";
exit;

?>
