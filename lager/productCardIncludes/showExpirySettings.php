<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/productCardIncludes/showExpirySettings.php --- patch 4.2.0 --- 2026-04-16 ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// Expiry date settings section on item card.
// Shows toggle for has_due_date and input for default_shelf_life_days.

$has_due_date_checked = ($has_due_date == 'on') ? 'checked' : '';
$shelf_life_display = ($has_due_date == 'on') ? '' : 'display:none;';
$shelf_life_val = ($default_shelf_life_days !== null && $default_shelf_life_days !== '') ? intval($default_shelf_life_days) : '';

print "<tr><td colspan='2'><hr></td></tr>\n";
print "<tr><td colspan='2'><b>".findtekst('5001|Udl&oslash;bsdato', $sprog_id)."</b></td></tr>\n";

print "<tr><td>".findtekst('5002|Varen har udl&oslash;bsdato', $sprog_id)."</td>\n";
print "<td><input type='checkbox' name='has_due_date' $has_due_date_checked ";
print "onchange=\"javascript:docChange=true; document.getElementById('shelf_life_row').style.display = this.checked ? '' : 'none';\">";
print "</td></tr>\n";

print "<tr id='shelf_life_row' style='$shelf_life_display'>\n";
print "<td>".findtekst('5003|Standard holdbarhed (dage)', $sprog_id)."</td>\n";
print "<td><input class='inputbox' type='number' min='1' style='width:80px;text-align:right;' ";
print "name='default_shelf_life_days' value='$shelf_life_val' onchange='javascript:docChange=true;'>";
print "</td></tr>\n";

// Show "View batches" button if item has ID (is saved)
if ($id && $stockItem) {
	print "<tr><td colspan='2'>\n";
	print "<a href='batch_oversigt.php?vare_id=$id'>\n";
	print "<button type='button' class='LightButton'>".findtekst('5004|Se batches', $sprog_id)."</button>\n";
	print "</a></td></tr>\n";
}
?>
