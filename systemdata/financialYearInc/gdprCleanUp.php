<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//
// --- systemdata/financialYearInc/gdprCleanUp.php --- ver 4.0.4 --- 2023-01-09 --
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------------

print "<FORM NAME = 'deleteAccount' ACTION = 'deleteAccounts.php' METHOD='post'>";
print "<TABLE>";
$showType = '';
for($i=0;$i<count($deleteAccount[$i]);$i++) {
	if ($deleteAccount[$i] == 1) {
		if ($showtype != $accountType[$i]) { 
			$showType = $accountType[$i];
			print "<tr>";
				print "<td collspan = '4' style = 'text-align:center;'>";
					print "$accountType[$i] to be deleted";
				print "</td>";
			print "</tr>";
		}
		print "<tr>";
			print "<td>$accountNo[$i]</td>";
			print "<td>$accountName[$i]</td>";
			print "<td><INPUT TYPE = 'checkbox' NAME = 'deleteAccount[$i]' 'checked'></td>";
		print "</tr>";	
	}
}
print "</TABLE>";
print "</FORM>";

/*
if ($deleteAccount[$i]) {
	$qtxt = "delete from adresser where id = $accountId[$i]";
#cho __line__." $qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "delete from historik where konto_id = $accountId[$i]";
#cho __line__." $qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "delete from ansatte where konto_id = $accountId[$i]";
#cho __line__." $qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "delete from jobkort where konto_id = $accountId[$i]";
#cho __line__." $qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
*/
?>
