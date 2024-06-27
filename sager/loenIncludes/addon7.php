<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- sager/loenIncludesaddon7.php --- lap 4.8.0 --- 2023-04-28 --- 19:55 ---
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

	$telt_tillaeg=1;
	$telt35=NULL;$telt40=NULL;$telt60=NULL;#$telt55=NULL;
	if (!$telt_antal) $telt_antal='0.35';
	if ($telt_antal=='0.35') $telt35='checked';
	if ($telt_antal=='0.4') $telt40='checked';
	//if ($telt_antal=='0.55') $telt55='checked';
	if ($telt_antal=='0.6') $telt60='checked';
	//else $telt35='checked';
	$sum+=$telt_antal*$sum1;
	#cho "telt35 $telt35<br>telt40 $telt40<br>telt55 $telt55<br>telt60 $telt60<br>";
#cho "<tr><td colspan='5'>update loen_enheder set pris_op=$sum1 WHERE loen_id = '$id' and vare_id='-2</td></tr>";
	#20140810 indsat linje herunder.
#	db_modify("update loen_enheder set pris_op=$sum1 WHERE loen_id = '$id' and vare_id='-2'",__FILE__ . " linje " . __LINE__);
	print "<tr>
		<td colspan=\"2\" class=\"tableSagerBorder\" align=\"center\"><input type=\"radio\" $readonly name=\"telt_antal\" value=\"0.35\" $telt35></td>
		<td colspan=\"3\" class=\"tableSagerBorder\" style=\"padding-left: 5px;\"><b>Telt tillæg 35%</b></td><td align=\"right\" class=\"tableSagerBorder\" style=\"padding-right: 1px;\">";
		if ($telt35) print "<b>".dkdecimal($telt_antal*$sum1)."</b>";
		print "</td><td colspan=\"9\" class=\"tableSagerBorder\">&nbsp;</td>
	</tr>
	<tr bgcolor=\"$bgcolor\">
		<td colspan=\"2\" align=\"center\"><input type=\"radio\" $readonly name=\"telt_antal\" value=\"0.4\" $telt40></td>
		<td colspan=\"3\" style=\"padding-left: 5px;\"><b>Telt tillæg 40%</b></td><td align=\"right\">";
		if ($telt40) print "<b>".dkdecimal($telt_antal*$sum1)."</b>";
		print "</td><td colspan=\"9\">&nbsp;</td>
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
		<td colspan=\"2\" align=\"center\"><input type=\"radio\" $readonly name=\"telt_antal\" value=\"0.6\" $telt60></td>
		<td colspan=\"3\" style=\"padding-left: 5px;\"><b>Telt tillæg 60%</b></td><td align=\"right\">";
		if ($telt60) print "<b>".dkdecimal($telt_antal*$sum1)."</b>";
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
