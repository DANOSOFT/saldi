 
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
$editItemId   = if_isset($_GET['editItemId']);
$editRtName   = if_isset($_GET['editRtName']);
$addSubItemTo = if_isset($_POST['addSubItemTo']);
$nextRtId     = if_isset($_POST['nextRtId']);

echo "$addSubItemTo && $subItemId && $nextRtId<br>";
if (is_numeric($addSubItemTo) && $subItemId) {
	$qtxt="insert into rentalitems (rt_item_id,item_id,unit,qty) values ('$addSubItemTo','$subItemId','m','1')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	print "<meta http-equiv=\"refresh\" content=\"0;URL='rental.php?rtItemId=0'\">\n";
} elseif ($addSubItemTo == 'new' && $nextRtId) {
echo __line__." $addSubItemTo && $subItemId && $nextRtId<br>";
	
	$newRtName ='rental_' . $nextRtId;
	$qtxt = "insert into settings (var_name,var_grp,var_value,var_description,user_id) values ";
	$qtxt.= "('$newRtName','rental','". db_escape_string($subItemName) ."','Given header name for rental items','0')"; 
echo "$qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

$qtxt = "select varenr,beskrivelse from varer where id = '$subItemId'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$subItemNo   = $r['varenr'];
	$subItemName  = $r['beskrivelse'];
}

$x=0;
$rtNames = array();
$nextRtId = 0;
$qtxt = "select * from settings where var_name like 'rental_%' and var_grp = 'rental' order by var_value";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
echo "$r[id] | $r[var_name] | $r[var_value]<br>";
	$rtIds[$x]   = $r['id'];
	if ($rtIds[$x] >= $nextRtId) $nextRtId = $rtIds[$x]+1;
	$rtNos[$x]   = str_replace('rental_','',$r['var_name']);
	$rtNames[$x] = $r['var_value'];
	$x++;
}
if (count($rtNames)==0) {
	$qtxt = "insert into settings (var_name,var_grp,var_value,var_description,user_id) values ";
	$qtxt.= "('rental_0','rental','". db_escape_string($subItemName) ."','Given header name for rental items','0')"; 
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "select * from settings where var_name = 'rental_0' and var_grp = 'rental' order by var_value";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$rtIds[0]   = $r['id'];
		$rtNames[0] = $r['var_value'];
		$qtxt="insert into rentalitems (rt_item_id,item_id,unit,qty) values ('0','$subItemId','m','1')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL='rental.php?rtItemId=0'\">\n";
	}	
} else {
	$linebg=$bgcolor;
	print "<center><table>";
	print "<form name = 'rentalItem' action='rental.php?subItemId=$subItemId&page=rtSettings' method='post'>";
	print "<tr><td colspan='2' align='center'><input type = 'hidden' name = 'nextRtId' value = '$nextRtId'>";
	print "Tilknyt $subItemNo $subtemname til:</tr></td>";
	print "<tr><td><select name = 'addSubItemTo'>";
	for ($x=0;$x<count($rtIds);$x++) print "<option value = '$rtNos[$x]'>$rtNames[$x]</option>";
	print "<option value = 'new'>Opret ny</option>";
	print "</select></td>";
	print "<td><button style='widht:80px;' type='submit'>OK</button></td>";
	print "</tr>";
}
/*
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
*/
print "</form>";
/*
$qtxt = "select * from settings where var_name like 'rental_%' and var_grp = 'rental' order by var_name";
echo "$qtxt<br>";
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
	print "<a href = 'rental.php?rtItemId=$rtItemId&deleteItemId=$r[id]&page=rtSettings' onClick=\"return confirm('Slet $r[varenr]?')\">Slet</td>";
	print "</tr>";
}
print "<tr><td colspan ='5	' align='center'>";
print "<a href = 'rental.php?rtItemId=$rtItemId'><button type='button'>Tilbage til oversigt</button></td>";
print "</td></tr>";
*/
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
