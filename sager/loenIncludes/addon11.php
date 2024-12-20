<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- sager/loenIncludesaddon11.php --- lap 4.0.8 --- 2023-07-03 ---
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
// Copyright (c) 2003-2023 saldi.dk aps
// ----------------------------------------------------------------------
// 20230703 PHR changed $sum to $sum1 as in addon7.php

	$telt_tillaeg=1;
	$telt35 = $telt40 = $telt_47 = $telt60 = $telt70 = NULL;
	if (!$telt_antal) $telt_antal='0.47';
	if ($telt_antal=='0.47') $telt47 = "checked = 'checked'";
	if ($telt_antal=='0.7') $telt70 = "checked = 'checked'";
	$sum+=$telt_antal*$sum1;
	
	print "<tr>
		<td colspan=\"2\" class=\"tableSagerBorder\" align=\"center\">
		<input type=\"radio\" $readonly name=\"telt_antal\" value=\"0.47\" $telt47></td>
		<td colspan=\"3\" class=\"tableSagerBorder\" style=\"padding-left: 5px;\"><b>Telt tillæg 47%</b></td>
		<td align=\"right\" class=\"tableSagerBorder\" style=\"padding-right: 1px;\">";
		if ($telt47) print "<b>".dkdecimal($telt_antal*$sum1)."</b>";
		print "</td><td colspan=\"9\" class=\"tableSagerBorder\">&nbsp;</td>
	</tr>";
	/*
	<tr>
		<td colspan=\"2\" align=\"center\"><input type=\"radio\" $readonly name=\"telt_antal\" value=\"0.55\" $telt55></td>
		<td colspan=\"3\" style=\"padding-left: 5px;\"><b>Telt tillæg 55%</b></td><td align=\"right\">";
		if ($telt55) print "<b>".dkdecimal($telt_antal*$sum1)."</b>";
		print "</td><td colspan=\"9\">&nbsp;</td>
	</tr>
	*/
	print "
	<tr>
		<td colspan=\"2\" align=\"center\"><input type=\"radio\" $readonly name=\"telt_antal\" value=\"0.7\" $telt70></td>
		<td colspan=\"3\" style=\"padding-left: 5px;\"><b>Telt tillæg 70%</b></td><td align=\"right\">";
		if ($telt70) print "<b>".dkdecimal($telt_antal*$sum1)."</b>";
		print "</td><td colspan=\"9\">";
		print "<input type=\"hidden\" $readonly name=\"telt_id\" value=\"$telt_id\">
		<input type=\"hidden\" $readonly name=\"telt_pris\" value=\"$sum1\">";
		if ($gemt>=1) print "<input type=\"hidden\" $readonly name=\"telt_antal\" value=\"$telt_antal\"> <!--#20151116-->"; #20170920
		print "
		</td>
	</tr>
	<tr>
	<td colspan=\"2\"></td><td colspan=\"3\" style=\"padding-left: 5px;\"><b>Sum incl. telt tillæg</b></td>
	<td colspan=\"10\" align=\"right\" style=\"padding-right: 1px;\"><b>".dkdecimal($sum,2)."</b></td>
	</tr>";
?>
