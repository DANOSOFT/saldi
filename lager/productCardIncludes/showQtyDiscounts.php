<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/productCardIncludes/showQtyDiscount.php --- lap 4.1.0 --- 2025-03-06 ---
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
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 20240211 PHR Remover decimal limit from $m_rabat_array and $m_antal_array
print "<tr><td><b>".findtekst(2041,$sprog_id)."</b></td>";
if ($special_price && $specialType=='percent') {
	print "<tr><td>".findtekst(2043,$sprog_id)."</td></tr>";
} else {
	print "<td align=\"right\"><SELECT class=\"inputbox\" NAME=m_type style=\"width: 4em\">";
	if ($m_type == 'amount') {
		print "<option value=\"amount\">kr</option>";
		print "<option value=\"percent\">%</option>";
	} else {
		print "<option value=\"percent\">%</option>";
		print "<option value=\"amount\">kr</option>";
	}
	print "</SELECT> pr</td>";
	print "<td> ".findtekst(2042,$sprog_id)."</td></tr>";
	for ($x=0;$x<count($m_antal_array);$x++) {
		if ($m_antal_array[$x]) {
			if ($incl_moms && $m_type!="percent") $m_rabat_array[$x]*=(100+$incl_moms)/100;
			list($a,$b) = explode('.',$m_rabat_array[$x]*1);
			$c = strlen ($b);
			list($a,$b) = explode('.',$m_antal_array[$x]*1);
			$d = strlen ($b);
			($rabatgruppe)? $inputtype="readonly=\"readonly\"":$inputtype="type=\"text\"";
			if ($m_rabat_array[$x] > 0 || $m_antal_array[$x] > 0) {
			print "<tr><td>".findtekst(2044,$sprog_id)."</td>";
			print "<td><input $inputtype size=\"5\" style=\"text-align:right\" name=\"m_rabat_array[$x]\" value=".dkdecimal($m_rabat_array[$x],$c)."></td><td><input $inputtype size=\"5\" style=\"text-align:right\" name=\"m_antal_array[$x]\" value=\"".dkdecimal($m_antal_array[$x],$d)."\"></td></tr>";
		}}
	}
#$x++;
	if (!$rabatgruppe) {
	if (!isset($m_rabat_array[$x])) $m_rabat_array[$x]='0';
	if (!isset($m_antal_array[$x])) $m_antal_array[$x]='0';
		list($a,$b) = explode('.',$m_rabat_array[$x]*1);
		$c = strlen ($b);
		list($a,$b) = explode('.',$m_antal_array[$x]*1);
		$d = strlen ($b);
		print "<tr><td>".findtekst(2044,$sprog_id)."</td>";
		print "<td><input class='inputbox' type='text' size='5' style='text-align:right' name='m_rabat_array[$x]'";
		print "value='".dkdecimal($m_rabat_array[$x],$c)."'></td>";
		print "<td><input class='inputbox' type='text' size='5' style=text-align:right name='m_antal_array[$x]'";
		print "value='".dkdecimal($m_antal_array[$x],$d)."'></td></tr>";
	}
}
?>
