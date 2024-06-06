<?php 
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/lager/varekort_includes/confirmStockChange.php---------lap 3.9.4---2020-09-22	-----
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
// Copyright (c) 2020-2020 saldi.dk aps
// ----------------------------------------------------------------------
echo "XXXXXX";
print "<script Language=\"JavaScript\">\n";
print "	<!--\n";
print "	function Form_Validator(confirmStockChange) {\n";
print "		var alertsay = \"\"; \n";
print "		if (confirmStockChange.reason.value == \"\") {\n";
print "			alert(\"Årsag skal udfyldes.\");\n";
print "			confirmStockChange.reason.focus();\n";
print "			return (false);\n";
print "		}\n";
print "		if (confirmStockChange.initials.value == \"\") {\n";
print "			alert(\"Initialer skal udfyldes.\");\n";
print "			confirmStockChange.initials.focus();\n";
print "			return (false);\n";
print "		}\n";
print "	}\n";
print "-->\n";
print "</script>\n";
print "<form name='confirmStockChange' action='varekort.php?id=$id' method='post'></td></tr>";
include ("productCardIncludes/hiddenVars.php");
print "<input type='hidden' name='saveItem' value='on'>";
print "<input type='hidden' name='beholdning' value='$beholdning'>";
print "<input type='hidden' name='ny_beholdning' value='$ny_beholdning'>";
print "<center><table>";
print "<tr><td colspan='2' align='center'>";
print "Du er ved  at ændre lagerbeholdningen for varenr: $varenr fra $beholdning til $ny_beholdning<hr>";
print "</td></tr>";
print "<tr><td align = 'center' colspan='2'>";
print "Årsag til ændring<br>";
print "<textarea name=\"reason\" rows=\"3\" cols=\"60\"></textarea>";
print "</td></tr>";
print "<tr><td align = center colspan='2'>";
print "Initialer<br>";
print "<input style='width:150px;' type='text' name='initials'><hr>";
print "</td></tr>";
print "<tr><td align='center'>";
print "<input style='width:150px;' type=submit accesskey='o' value='OK' name='acceptStockChange'";
print "onclick=\"return Form_Validator(confirmStockChange)\">";
print "</td><td align = center>";
print "<input style='width:150px;' type=submit accesskey='a' value='Afbryd' name='cancelStockChange' ";
print "onclick=\"return confirm('Fortryd?')\">";
print "</td></tr>";
print "</form>";
exit;

?>
