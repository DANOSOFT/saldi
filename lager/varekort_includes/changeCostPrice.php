<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------lager/ret_varenr.php-------------patch 3.9.1 -- 20120611----------
// LICENS
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 2015.12.08 Tilføjet flet funtion til sammenlægning af varer.
// 2018.05.15 Kontrol mod flet af styklister og vares som indgår.
// 2020.06.11 PHR - handling of ' and " in description.


print "<table style='width:100%;vertical-align:center'><tbody>";
print "<tr><td><form name='none' action='varekort.php?id=$id' method='post'></td></tr>";
print "<tr><td align=center><hr width=50%></td></tr>";
print "<tr><td align=center><big>Du er ved at rette beskrivelse fra:<br><br><b>". $oldCost ."</b><br><br>til:<br><br><b>". $kostpris ."</b> !</big></td></tr>";
print "<tr><td align=center><br></td></tr>";
print "<tr><td align=center><big>Er det det du vil ?</big></td></tr>";
print "<tr><td align=center><br></td></tr>";
print "<tr><td align=center><input type='hidden' value='$id' name=\"id\">";
print "<tr><td align=center><input type='hidden' name='newDecsription'";
if (strpos($kostpris,"'")) print "value=\"$kostpris\"";
else print "value='$kostpris'";
print "></td></tr>";
print "<tr><td align=center><input type='submit' accesskey='j' style='width:100px;height:100px;' value='Ja' name=\"ChangeDescription\"> &nbsp; ";
print "<input type='submit' accesskey='n' style='width:100px;height:100px;' value='Nej' name=\"ChangeDescription\"></td></tr>";
print "</form>";

print "</tbody></table";
print "</td></tr>\n";
exit;

?>
