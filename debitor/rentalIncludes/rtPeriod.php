 
<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------debitor/rentalIncludes/rtPeriod.php---lap 3.9.2-----2020-10-24-----
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
// Copyright (c) 2003-2020 Saldi.dk ApS
// ----------------------------------------------------------------------


print "<form name = 'rtPeriod' autocomplete='off' ";
print "action='rental.php?thisRtId=$thisRtId&rtItemId=$rtItemId&customerId=$customerId' method='post'>";
$qtxt="select id,kontonr,firmanavn from adresser where art= 'D' and lukket != 'on' order by firmanavn";
$x=0;
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__); 
while ($r=db_fetch_array($q)) {
	if ($customerId == $r['id']) $rtCustNo = $r['kontonr'];
	$custNo[$x]   = $r['kontonr'];
	$customer[$x] = $r['kontonr'] ." ".$r['firmanavn'];
	$x++;
}
print "<center>";
print "<input type='hidden' name='editRpId' value='$editRpId'>"; 
print "<table border='1' style='align:center'>"; 
print "<tr>";
# <td style='width:150px'>Kundenr</td><td><input type = 'text' name = 'rtPeriodCustomer'  value = '$rtCustNo'></td></tr>";
print "<td style='width:150px'>Kundenr</td>";
print "<td><select name = 'rtPeriodCustomer'>";
for ($x=0;$x<count($custNo);$x++) {
	if ($custNo[$x] && $custNo[$x] == $rtCustNo) print "<option value='$custNo[$x]'>$customer[$x]</option>";
}
for ($x=0;$x<count($custNo);$x++) {
	if ($custNo[$x] && $custNo[$x] != $rtCustNo) print "<option value='$custNo[$x]'>$customer[$x]</option>";
}

print "</select></td></tr>";
print "<tr><td>Periode fra</td>";
print "<td><input type = 'text' name = 'newRtFrom' value = '". date('d-m-Y',$rtPeriodFrom) ."'></td></tr>";
print "<tr><td>Periode til</td>";
print "<td><input type = 'text' name = 'newRtTo' value = '". date('d-m-Y',$rtPeriodTo) ."'></td></tr>";
print "<tr><td colspan = '2' style='text-align:center'>";
if ($editRpId) print "<button style='width:100px' name='save' type='submit'>Opdater</button> ";
else print "<button style='width:100px' name='save' type='submit'>Opret</button> ";
print "<input style='width:100px' name='cancel' type='submit' value = 'Afbryd'> ";
if ($editRpId) print "<input style='width:100px' name='delete' type='submit' value='Slet'>";

print "</table>";
print "</form>";
exit;
?>
</body></html>
