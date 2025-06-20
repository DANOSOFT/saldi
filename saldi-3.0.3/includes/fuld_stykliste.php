<?php

// -------------------------------------------------------------- includes/stykliste.php lap 1.1.0 ----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------

// $udskriv kan v�e 

function fuld_stykliste($id, $udskriv, $udvalg)
{
	$x=0;
	$query = db_select("select * from styklister where indgaar_i=$id order by posnr");
	while ($row = db_fetch_array($query)) {
		$x++;
		$vare_id[$x]=$row[vare_id];
		$antal[$x]=$row[antal];
	}
	for ($a=1; $a<=$x; $a++) {
		$query = db_select("select * from styklister where indgaar_i = $vare_id[$a]");
		while ($row = db_fetch_array($query)) {
			for ($c=1; $c<=$antal[$a]; $c++) {
				$x++;
				$vare_id[$x]=$row[vare_id];
				$antal[$x]=$row[antal];
			}
		}
	}

	if ($udskriv) {
		$query = db_select("select varenr, beskrivelse from varer where id=$id");
		$row = db_fetch_array($query);
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=80% align=center><tbody>";
		print "<tr><td colspan=6 align=center>$font<big><b>Fuld stykliste for Varenr <a href=varekort.php?id=$id>$row[varenr]</a>&nbsp;$row[beskrivelse]</td></tr>";
		print "<tr><td align=center>$font Varenr.</td><td align=center>$font Beskrivelse</td><td align=center>$font Kostpris</td><td align=center>$font Antal(Lager)</td><td align=center>$font Sum</td></tr>";
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
		if ($udvalg=='grundvare') $query = db_select("select * from varer where id=$v_id[$a] and samlevare!='on'"); 
		else $query = db_select("select * from varer where id=$v_id[$a]"); 
		$row = db_fetch_array($query);
		$varenr[$a]=$row['varenr'];
		if ($row[samlevare]!='on') {
			$sum=$row[kostpris]*$v_antal[$a];
			$ialt=$ialt+$sum;
			$x++;
			$vare_id[$x]=$row['id'];
			$antal[$x]=$v_antal[$a];
			if ($udskriv) {
				$pris=dkdecimal($row[kostpris]);
				$sum=dkdecimal($sum);
			}
		}
		else {$pris=' '; $sum=' ';}
		if (($udskriv)&&($varenr[$a])) print "<tr><td><a href=varekort.php?id=$v_id[$a]>$font $varenr[$a]</a></td><td>$font $row[beskrivelse]</td><td align=right>$font $pris</td><td align=right>$font $v_antal[$a]($row[beholdning])</td><td align=right>$font $sum</td></tr>";
	}
	if ($udskriv) {
		print "<tr><td colspan=5><br></td></tr><tr><td colspan=4>$font I alt</td></td><td align=right>$font ".dkdecimal($ialt)."</td></tr>";
		print "<tbody></table>";
	}
	$ialt=$ialt*1;
	db_modify("update varer set kostpris=$ialt where id=$id");
	if (!$udvalg) return $ialt;
	else return array($vare_id, $antal, $x);
}
?>
