<?php 
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----/lager/varekort_includes/showUnits.php----lap 3.9.5---2020-11-14	-----
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 2020.11.15 PHR - Created this file from unit section of ../varekort.php
// 20220817


print "<tr><td colspan=3><b>Enheder</b></td></tr>";
print "<tr><td width=34%>Enhed</td>";
print "<td width=33%><SELECT class='inputbox' NAME='enhed' style='width:80px'>";
print "<option>$enhed</option>";
$query = db_select("select betegnelse from enheder order by betegnelse",__FILE__ . " linje " . __LINE__);
$x=0;
while ($row = db_fetch_array($query)) {
	$betegnelse[$x]=$row['betegnelse'];
	$x++;
}
if ($enhed) print "<option></option>";
for ($x=0; $x<count($betegnelse); $x++) {
	if (isset($betegnelse[$x]) && $enhed!=$betegnelse[$x]) print "<option>$betegnelse[$x]</option>";
} 
print "</SELECT></td><td width=33%><br></td></tr>";
if (count($betegnelse)>1) {
	print "<tr><td>Alternativ enh.</td>";
	print "<td><SELECT style=\"width: 7em\" NAME=enhed2><option>$enhed2</option>";
	if ($enhed2) print "<option></option>";
	for ($x=0; $x<count($betegnelse); $x++) {
		if (isset($betegnelse[$x]) && $enhed2!=$betegnelse[$x]) print "<option>$betegnelse[$x]</option>";	
	}
	print "</SELECT></td></tr>";
	if ($forhold > 0) $x=dkdecimal($forhold,2);
	else $x='';
	if ($enhed != 0 && $enhed2) {
		print "<tr><td> $enhed2/$enhed</td><td width=100>";
		print "<input class='inputbox' type='text' style='text-align:right' style='width:80px'' name='forhold' ";
		print "value='$x' onchange='javascript:docChange = true;'></td></tr>";
	}
	if ($enhed && $indhold != 0) {
		print "<tr><td height='20%'>Indhold  ($enhed)</td><td><input class='inputbox' type='text' style=text-align:right  style='width:80px' name=indhold value=\"".dkdecimal($indhold,2)."\" onchange=\"javascript:docChange = true;\"></td></tr>";
		print "<tr><td height='20%'>Pris pr $enhed</td><td><input class='inputbox' type='text' readonly=readonly style=text-align:right  style='width:80px' value=\"".dkdecimal($salgspris/$indhold,2)."\" onchange=\"javascript:docChange = true;\"></td></tr>";
	}
}
print "<tr><td>Nettovægt</td><td>";
print "<input class='inputbox' type='text' style='text-align:right;width:80px' name='netWeight' ";
print "value=\"".dkdecimal($netWeight,3)."\" onchange=\"javascript:docChange = true;\"></td>";
print "<td align='center'><select name='netWeightUnit' style='text-align:right'>";
if ($netWeightUnit == 'g') print "<option value='g'>g</option><option value='kg'> kg</option>";
else print "<option value='kg'>kg</option><option value='g'>g</option>";
print "</option></td>";
print "</tr><tr>";
print "<td>Bruttovægt</td><td>";
print "<input class='inputbox' type='text' style='text-align:right;width:80px' name='grossWeight' ";
print "value=\"".dkdecimal($grossWeight,3)."\" onchange=\"javascript:docChange = true;\"></td>";
print "<td align='center'><select name='grossWeightUnit' style='text-align:right'>";
if ($grossWeightUnit == 'g') print "<option value='g'>g</option><option value='kg'> kg</option>";
else print "<option value='kg'>kg</option><option value='g'>g</option>";
print "</option></td>";
print "</tr><tr>";
print "<td>L x B x H</td><td>";
print "<input class='inputbox' type='text' style='text-align:right;width:35px' name='length' ";
print "value=\"".dkdecimal($length,0)."\" onchange=\"javascript:docChange = true;\">";
print "<input class='inputbox' type='text' style='text-align:right;width:35px' name='width' ";
print "value=\"".dkdecimal($width,0)."\" onchange=\"javascript:docChange = true;\">";
print "<input class='inputbox' type='text' style='text-align:right;width:35px' name='height' ";
print "value=\"".dkdecimal($height,0)."\" onchange=\"javascript:docChange = true;\">";
print "<td align='center'>". $length*$width*$height ." cm&sup3;</td>";
print "</tr>";

?>
