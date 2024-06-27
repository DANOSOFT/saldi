<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//
// --- systemdata/gdprInc/gdprOutput.php --- ver 4.0.4 --- 2023-01-09 --
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

print "<FORM NAME = 'deleteAccount' ACTION = 'gdpr.php' METHOD='post'>";
print "<TABLE>";
$showType = '';
$i2 = 0;
for($i=0;$i<count($accountId);$i++) {
	if ($deleteAccount[$i] == 1) {
		if ($showType != $accountType[$i]) { 
			$showType = $accountType[$i];
			print "<tr>";
				print "<td colspan = '4' style = 'text-align:center;'>";
					print "$accountType[$i] to be deleted";
				print "</td>";
			print "</tr>";
		}
		print "<tr>";
			print "<input TYPE ='hidden' name = 'accountId[$i2]' value = '$accountId[$i]'>";
			print "<td>$accountNo[$i]</td>";
			print "<td>$accountName[$i]</td>";
			print "<td><INPUT TYPE = 'checkbox' NAME = 'deleteAccount[$i2]' checked = 'checked'></td>";
		print "</tr>";	
		$i2++;
	}
}
print "<tr>";
	print "<td colspan = '4' style = 'text-align:right;'>";
		print "<INPUT TYPE = 'submit' NAME = 'deleteAccounts' value = 'Slet ovenstående'>";
	print "</td>";
print "</tr>";
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
