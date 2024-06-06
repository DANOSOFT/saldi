 
<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------debitor/rentalIncludes/rtSettings.php---lap 4.0.0-----2021-02-20-----
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
// Copyright (c) 2021 Saldi.dk ApS
// ----------------------------------------------------------------------


$itemName = $subItemNo = $subItemName = $subItemQty = $subItemUnit = NULL;
$subItemUnits=array('i','h','d','w','m','y');
$subItemNames=array('minut(ter)','Time(r)','Dag(e)','Uge(r)','Måned(er)','År');
$deleteItemId = if_isset($_GET['deleteItemId']);	
$editItemId = if_isset($_GET['editItemId']);
$editRtName = if_isset($_GET['editRtName']);

$varname = 'rental_'.$rtItemId;
$qtxt = "select var_value from settings where var_name = '$varname' and var_grp ='rental'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $itemName = $r['var_value'];

$newSubItem = if_isset($_POST['newSubItem']);
$newQty = if_isset($_POST['newQty']);
$newUnit    = if_isset($_POST['newUnit']);
$newRtName    = if_isset($_POST['newRtName']);
if ($newQty && (!is_numeric($newQty) || $newQty < 1)) {
	if ($newQty < 0) $newQty=abs($newQty); 
	else $newQty = 1;
	alert ("Varighed skal være positivt heltal");
}
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
} elseif ($deleteItemId) {
	$qtxt = "delete from rentalitems  where id = '$deleteItemId'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
} elseif ($newRtName) {
	$varname = 'rental_'.$rtItemId;
	$qtxt = "update settings set var_value = '$newRtName' where var_name = '$varname' and var_grp ='rental'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$itemName = $newRtName;
} elseif ($editItemId) {
	if ($newQty && $newUnit) {
		$qtxt="update rentalitems set qty = '$newQty', unit = '$newUnit' where id = '$editItemId'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$editItemId = NULL;
	} else {
		$qtxt = "select rentalitems.item_id,varer.varenr,varer,beskrivelse,rentalitems.qty,rentalitems.unit from rentalitems,varer ";
		$qtxt.= "where rentalitems.id='$editItemId' and varer.id=rentalitems.item_id";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$subItemNo   = $r['varenr'];
			$subItemName = $r['beskrivelse'];
			$subItemQty  = $r['qty'];
			$subItemUnit = $r['unit'];
		} else $editItemId = $subItemNo = $subItemName = $subItemQty = $subItemUnit = NULL;
	}
}
if (!$subItemQty) $subItemQty = 1;
$linebg=$bgcolor;
print "<center><table>";
print "<form name = 'rentalItem' action='rental.php?rtItemId=$rtItemId&editItemId=$editItemId&page=rtSettings' method='post'>";
print "<tr><td colspan ='5' align='center'>";
print "Her håndteres varer som er omfattet af bookinger under overskriften<br>";
if ($editRtName) {
	print "<input type = 'text' style='width:500px;' name = 'newRtName' value = \"$itemName\">";
} else {
	print "<a href = 'rental.php?rtItemId=$rtItemId&editRtName=1&page=rtSettings'><big><b>$itemName</b></big><br></a>";
	print "</td></tr>";
	print "<tr><td>Varenummer</td><td>Beskrivelse</td><td>Varighed</td><td>Enheder</td></tr>"; 
	if ($editItemId) print "<tr><td>$subItemNo</td><td>$subItemName</td>";
	else print "<tr><td><input type = 'text' style='width:80px;' name = 'newSubItem' value = ''></td><td></td>";
	print "<td><input type = 'text' style='width:40px;' name = 'newQty' value = '$subItemQty' ></td>";
	print "<td><select name = 'newUnit'>";
	for ($x=0;$x<count($subItemUnits);$x++) {
		if ($subItemUnit == $subItemUnits[$x]) print "<option value = '$subItemUnits[$x]'>$subItemNames[$x]</option>";
	}
	for ($x=0;$x<count($subItemUnits);$x++) {
		if ($subItemUnit != $subItemUnits[$x]) print "<option value = '$subItemUnits[$x]'>$subItemNames[$x]</option>";
	}
	print "</select></td><td>";
}
if ($editItemId || $editRtName) print "<button style='widht:80px;' type='submit'>Opdater</button>";
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
	print "<td><a href = 'rental.php?rtItemId=$rtItemId&customerId=$customerId&editItemId=$r[id]&page=rtSettings'>Ret ";
	print "<a href = 'rental.php?rtItemId=$rtItemId&customerId=$customerId&deleteItemId=$r[id]&page=rtSettings' onClick=\"return confirm('Slet $r[varenr]?')\">Slet</td>";
	print "</tr>";
}
print "<tr><td colspan ='5	' align='center'>";
print "<a href = 'rental.php?rtItemId=$rtItemId&customerId=$customerId'><button type='button'>Tilbage til oversigt</button></td>";
print "</td></tr>";
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
