 
<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------debitor/rentalIncludes/rtItem.php---lap 3.9.2-----2020-10-24-----
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

$itemName = '';
$editItemId = if_isset($_GET['editItemId'])
$varname = 'rental_'.$rtItemId;
$qtxt = "select var_value from settings where var_name = '$varname' and var_grp ='rental'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $itemName = $r['var_value'];

$newSubItem = if_isset($_POST['newSubItem']);
$newQty = if_isset($_POST['newQty']);
$newUnit    = if_isset($_POST['newUnit']);
if ($newSubItem) {
	$qtxt = "select id, varenr from varer where lower(varenr) = '". strtolower($newSubItem) ."' and lukket != 'on'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$newSubItemId=$r['id'];
		$vNr=$r['varenr'];
		$qtxt = "select rt_item_id from rentalitems where item_id = '$r[id]'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$alerttxt="Varenr $vNr er ";
			$var_name = "rental_".$r['rt_item_id'];
			$qtxt = "select var_value from settings where var_name = '$varname' and var_grp ='rental'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $alerttxt.="brugt i booking $r[var_value]";
			else $alerttxt.="allerede i brug";
			alert($alerttxt);
		} else {
			$qtxt = "insert into rentalitems (rt_item_id,item_id,qty,unit) values ('$rtItemId','$newSubItemId','$newQty','$newUnit')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
} elseif ($editItemId) {
	$qtxt = "select rentalitems.item_id,varer.varenr,varer,beskrivelse,rentalitems.qty,rentalitems.unit from rentalitems,varer ";
	$qtxt.= "where rentalitems.id='$editItemId' and varer.id=rentalitems.item_id";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$subItemNo   = $r['varenr'];
		$subItemName = $r['beskrivelse'];
		$subItemQty  = $r['qty'];
		$subItemUnit = $r['unit'];
	} else $editItemId = $subItemNo = $subItemName = $subItemQty = $subItemUnit = NULL;
 
}

$linebg=$bgcolor;
print "<center><table>";
print "<form name = 'rentalItem' action='rental.php?rtItemId=$rtItemId&updateItemId=$editItemId&page=rtSettings' method='post'>";
print "<tr><td colspan ='4' align='center'>";
print "Her håndteres varer som er omfattet af bookinger under overskriften<br><big><b>$itemName</b></big><br>";
print "</td></tr>";

print "<tr><td>Varenummer</td><td>Beskrivelse</td><td>Varighed</td><td>Enheder</td></tr>"; 
if ($editItemId) print "<tr><td>$$subItemNo</td><td>$subItemName</td>";
print "<tr><td><input type = 'text' style='width:80px;' name = 'newSubItem' value = ''></td><td></td>";
print "<td><input type = 'text' style='width:40px;' name = 'newQty' value = '1'></td>";
print "<td><select name = 'newUnit'>";
print "<option value = 'i'>Minut(ter)</option>";
print "<option value = 'h'>Time(r)</option>";
print "<option value = 'd'>Dag(e)</option>";
print "<option value = 'w'>Uge(r)</option>";
print "<option value = 'm'>Måned(r)</option>";
print "<option value = 'y'>År</option>";
print "</select></td><br>";
print "<td>";
if ($modRt) print "<button style='widht:80px;' type='submit'>Opdater";
else print "<button style='widht:80px;' type='submit'>Opret</button>";
print "</td></tr>";
print "</form>";
$qtxt = "select rentalitems.id,varer.varenr,varer,beskrivelse,rentalitems.qty,rentalitems.unit from rentalitems,varer ";
$qtxt.= "where rentalitems.rt_item_id='$rtItemId' and varer.id=rentalitems.item_id order by varer.beskrivelse";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	($linebg == $bgcolor5)?$linebg = $bgcolor:$linebg = $bgcolor5;
	$unit = getUnit($r['qty'],$r['unit']);
	print "<tr bgcolor='$linebg'>";
	print "<td>$r[varenr]</td>";
	print "<td>$r[beskrivelse]</td>";
	print "<td>$r[qty]</td>";
	print "<td>$unit</td>";
	print "<td><a href = 'rental.php?rtItemId=$rtItemId&editItemId=$r[id]&page=rtSettings'>Ret ";
	print "<a href = 'rental.php?rtItemId=$rtItemId&deleteItemId=$r[id]&page=rtSettings'>Slet</td>";
	print "</tr>";
}
print "</table>";
exit;

function getUnit ($qty,$unit) {
	if ($unit == 'i' && $qty == 1) return 'minut';
	elseif ($unit == 'i') return 'minutter';
	elseif ($unit == 'h' && $qty == 1) return 'time';
	elseif ($unit == 'i') return 'timer';
	elseif ($unit == 'd' && $qty == 1) return 'dag';
	elseif ($unit == 'd') return 'dage';
	elseif ($unit == 'w' && $qty == 1) return 'uge';
	elseif ($unit == 'w') return 'uger';
	elseif ($unit == 'm' && $qty == 1) return 'måned';
	elseif ($unit == 'm') return 'måneder';
	elseif ($unit == 'y') return 'år';
	return '?';
}
?>
</body></html>
