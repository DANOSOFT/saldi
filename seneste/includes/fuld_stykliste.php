<?php
// ------- includes/fuld_stykliste.php lap 3.2.9 ------2012-06-20-----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

if (!function_exists('fuld_stykliste')) {
	function fuld_stykliste($id, $udskriv, $udvalg) {
	GLOBAL $charset;

	$x=0;
	$query = db_select("select * from styklister where indgaar_i='$id' order by posnr",__FILE__ . " linje " . __LINE__);
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
echo "$qtxt<br>";
		$query = db_select($qtxt,__FILE__ . " linje " . __LINE__); 
		$row = db_fetch_array($query);
		$varenr[$a]=htmlentities(stripslashes($row['varenr']),ENT_COMPAT,$charset);
		$beskrivelse[$a]=htmlentities(stripslashes($row['beskrivelse']),ENT_COMPAT,$charset);
		if ($row[samlevare]!='on') {
			$sum=$row['kostpris']*$v_antal[$a];
echo "sum $sum V $v_antal[$a]<br>";
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
echo "update varer set kostpris=$ialt where id='$id'<br>";
	db_modify("update varer set kostpris=$ialt where id='$id'",__FILE__ . " linje " . __LINE__);
	if (!$udvalg) return $ialt;
	else return array($vare_id, $antal, $x);
}}
?>
