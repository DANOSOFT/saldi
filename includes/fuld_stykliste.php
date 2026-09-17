<?php
// --- includes/fuld_stykliste.php lap 4.1.1 --- 2025-09-25 ---
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
// Copyright (c) 2004-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 2025.09-25 PHR PHP8

if (!function_exists('fuld_stykliste')) {
	function fuld_stykliste($id, $udskriv, $udvalg) {
	global $charset;

	$x=0;
	$qtxt = "select * from styklister where indgaar_i='$id' order by posnr";
	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$vare_id[$x]=$row['vare_id'];
		$antal[$x]=$row['antal'];
	}
	for ($a=1; $a<=$x; $a++) {
		$query = db_select("select * from styklister where indgaar_i = '$vare_id[$a]'",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			for ($c=1; $c<=$antal[$a]; $c++) {
				if (!in_array($row['vare_id'],$vare_id)) {
					$x++;
					$vare_id[$x]=$row['vare_id'];
					$antal[$x]=$row['antal'];
				} else {
#					return("vare_id $row[vare_id] er cirkulær");
#					exit;
				}
				if ($x>1000) {
					print "<BODY onLoad=\"javascript:alert('Fejl i stykliste eller stykliste indeholder over 1000 enheder')\">\n";
					exit;
				}
			}
		}
	}

	if ($udskriv) {
		$query = db_select("select varenr, beskrivelse from varer where id='$id'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=80% align=center><tbody>";
		print "<tr><td colspan=6 align=center><big><b>Fuld stykliste for <a href=varekort.php?id=$id>".htmlentities($row['varenr'],ENT_COMPAT,$charset)."</a></b></big></td></tr>";
		print "<tr><td align=center> Varenr.</td><td align=center> Beskrivelse</td><td align=center> Kostpris</td><td align=center> Antal(Lager)</td><td align=center> Sum</td></tr>";
	}
	$v_id=array();
	$v_antal=array();	
	$b=0;
	for ($a=1; $a<=$x; $a++) {
		if (in_array($vare_id[$a],$v_id)) {
			for ($c=1; $c<=$b; $c++){
				if ($v_id[$c]==$vare_id[$a]) $v_antal[$c]=$v_antal[$c]+$antal[$a];
			}
		} else {
			$b++;
			$v_id[$b]=$vare_id[$a];
			$v_antal[$b]=$antal[$a];
		}
	}
	$vare_id=array(); $antal=array(); # Tmmer arrays 
	$x=0;
	for ($a=1; $a<=$b; $a++) {
		if ($udvalg=='grundvare') $qtxt = "select * from varer where id='$v_id[$a]' and samlevare!='on'"; 
		else $qtxt = "select * from varer where id='$v_id[$a]'"; 
		$query = db_select($qtxt,__FILE__ . " linje " . __LINE__); 
		$row = db_fetch_array($query);
		$varenr[$a]=htmlentities(stripslashes($row['varenr']),ENT_COMPAT,$charset);
		$beskrivelse[$a]=htmlentities(stripslashes($row['beskrivelse']),ENT_COMPAT,$charset);
		if ($row['samlevare']!='on') {
			$sum=$row['kostpris']*$v_antal[$a];
			$ialt=$ialt+$sum;
			$x++;
			$vare_id[$x]=$row['id'];
			$antal[$x]=$v_antal[$a];
			if ($udskriv) {
				$pris=dkdecimal($row['kostpris']);
				$sum=dkdecimal($sum);
			}
		}
		else {$pris=' '; $sum=' ';}
		if (($udskriv)&&($varenr[$a])) print "<tr><td>$varenr[$a]</td><td>$beskrivelse[$a]</td><td align=right>$pris</td><td align=right>$v_antal[$a]($row[beholdning])</td><td align=right> $sum</td></tr>";
	}
	if ($udskriv) {
		print "<tr><td colspan=5><br></td></tr><tr><td colspan=4> I alt</td></td><td align=right> ".dkdecimal($ialt)."</td></tr>";
		print "<tbody></table>";
	}
	$ialt=$ialt*1;
	db_modify("update varer set kostpris=$ialt where id='$id'",__FILE__ . " linje " . __LINE__);
	if (!$udvalg) return $ialt;
	else return array($vare_id, $antal, $x);
}}
?>
