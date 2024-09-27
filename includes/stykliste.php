<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------- includes/stykliste.php lap 4.1.1 ------2024-08-27-----------
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20170215 PHR Tilføjet ',2' i samtlige 'dkdcimal'
// 20190626 PHR Added missing '__FILE__ . " linje " . __LINE__' to queries
// 20240827 PHR Some improvements

if (!function_exists('stykliste')) {
function stykliste($id, $udskriv, $udvalg) {

	$ialt = $sum = $x = 0;
	$qtxt = "select * from styklister where indgaar_i = '$id' order by posnr";
echo __line__." $qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$vare_id[$x] = $r['vare_id'];
		$antal[$x]   = $r['antal'];
		$posnr[$x]   = $r['posnr'];
	}
	$vareantal=$x;

	$q = db_select("select varenr, beskrivelse from varer where id=$id",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);

	if ($udskriv) {
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=80% align=center><tbody>";
		print "<tr><td colspan=6 align=center><big><b>Stykliste for <a href=varekort.php?id=$id>".htmlentities($r['varenr'],ENT_COMPAT,$charset)."</a></b></big></td></tr>";
		print "<tr><td align=center> Varenr:</td><td align=center> Beskrivelse</td><td align=center> Kostpris</td><td align=center> Antal</td><td align=center>Sum</td><td>Beholdning</td></tr>";
	}
	for ($x=1; $x<=$vareantal; $x++) {
		$qtxt = "select * from varer where id='$vare_id[$x]'";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if ($r = db_fetch_array($q)) {
			$qtxt = "select kostpris from varer where id='$r[id]'";
			$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			$r2 = db_fetch_array($q2);
			$sum=$r2['kostpris']*$antal[$x];
			$ialt=$ialt+$sum;
			$pris=dkdecimal($r2['kostpris'],2);
		}
		$sum=dkdecimal($sum,2);
	if ($udskriv) print "<tr><td>".htmlentities($r['varenr'],ENT_COMPAT,$charset)."</td><td>".htmlentities($r['beskrivelse'],ENT_COMPAT,$charset)."</td><td align=right> $pris</td><td align=right> ".dkdecimal($antal[$x],2)."</td><td align=right> $sum</td><td align=right>".dkdecimal($r['beholdning'])."</td></tr>";
	}
#	$ialt=dkdecimal($ialt,2);
	if ($udskriv) {
		print "<tr><td colspan=5><br></td></tr><tr><td colspan=4> I alt</td></td><td align=right>".dkdecimal($ialt,2)."</td></tr>";
		print "<tbody></table>";
	}
	return($ialt);
}}
?>
