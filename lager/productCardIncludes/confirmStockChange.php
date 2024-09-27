<?php 
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- /lager/varekort_includes/confirmStockChange.php --- lap 4.1.1 --- 2024-08-15	---
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
// Copyright (c) 2020-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20240509 PHR added alert on submit  to prevent duplicates in log
// 20240811 PHR	Languages

$txt647  = findtekst(647,$sprog_id); //Initialer
$txt903  = findtekst(903,$sprog_id); //fra
$txt904  = findtekst(903,$sprog_id); //til
$txt2119 = findtekst(2119,$sprog_id); //Årsag skal udfyldes
$txt2120 = findtekst(2120,$sprog_id); //Beholdning opdateres. Klik ikke flere gange!
$txt2121 = findtekst(2121,$sprog_id); //Initialer skal udfyldes
$txt2122 = findtekst(2122,$sprog_id); //Du er ved at ændre lagerbeholdningen for varenr
$txt2123 = findtekst(2123,$sprog_id); //Årsag til ændring

print "<script Language=\"JavaScript\">\n";
print "	<!--\n";
print "	function Form_Validator(confirmStockChange) {\n";
print "		var alertsay = \"\"; \n";
print "		if (confirmStockChange.reason.value == \"\") {\n";
print "			alert(\"$txt2119.\");\n";
print "			confirmStockChange.reason.focus();\n";
print "			return (false);\n";
print "		}\n";
print "		if (confirmStockChange.initials.value == \"\") {\n";
print "			alert(\"$txt2121.\");\n";
print "			confirmStockChange.initials.focus();\n";
print "			return (false);\n";
print "		} else {\n"; // 20240509 
print "			alert(\"$txt2119 = findtekst(2110,$sprog_id) //Årsag skal udfyldes
\");\n"; 
print "			return (true);\n";
print "		}";
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
print "$txt2122: $varenr $txt903 $beholdning $txt904 $ny_beholdning<hr>";
print "</td></tr>";
print "<tr><td align = 'center' colspan='2'>";
print "$txt2123<br>";
print "<textarea name=\"reason\" rows=\"3\" cols=\"60\"></textarea>";
print "</td></tr>";
print "<tr><td align = center colspan='2'>";
print "$txt647<br>";
print "<input style='width:150px;' type='text' name='initials'><hr>";
print "</td></tr>";
print "<tr><td align='center'>";
print "<input style='width:150px;' type=submit accesskey='o' value='OK' name='acceptStockChange' ";
print "onclick=\"return Form_Validator(confirmStockChange)\">";
print "</td><td align = center>";
print "<input style='width:150px;' type=submit accesskey='a' value='Afbryd' name='cancelStockChange' ";
print "onclick=\"return confirm('Fortryd?')\">";
print "</td></tr>";
print "</form>";
exit;

?>
