<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------- includes/stykliste.php lap 3.6.7 ------2019-06-26-----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
// 20170215 PHR Tilføjet ',2' i samtlige 'dkdcimal'
// 20190626 PHT Added missing '__FILE__ . " linje " . __LINE__' to queries

if (!function_exists('stykliste')) {
function stykliste($id, $udskriv, $udvalg) {

	$ialt=$x=0;
	$query = db_select("select * from styklister where indgaar_i='$id' order by posnr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$vare_id[$x]=$row['vare_id'];
		$antal[$x]=$row['antal'];
		$posnr[$x]=$row['posnr'];
	}
	$vareantal=$x;

	$query = db_select("select varenr, beskrivelse from varer where id=$id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);

	if ($udskriv) {
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=80% align=center><tbody>";
		print "<tr><td colspan=6 align=center><big><b>Stykliste for <a href=varekort.php?id=$id>".htmlentities($row['varenr'],ENT_COMPAT,$charset)."</a></b></big></td></tr>";
		print "<tr><td align=center> Varenr:</td><td align=center> Beskrivelse</td><td align=center> Kostpris</td><td align=center> Antal</td><td align=center> Sum</td><td>Beholdning</td></tr>";
	}
	for ($x=1; $x<=$vareantal; $x++) {
		$query = db_select("select * from varer where id=$vare_id[$x]",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		#		$query2 = db_select("select kostpris from vare_lev where vare_id=$row[id] order by posnr");
#		if ($row2 = db_fetch_array($query2)) {
#			$sum=$row2['kostpris']*$antal[$x];
#			$ialt=$ialt+$sum;
#			$pris=dkdecimal($row2['kostpris'],2);
#		} else {
			$query2 = db_select("select kostpris from varer where id=$row[id]",__FILE__ . " linje " . __LINE__);
			$row2 = db_fetch_array($query2);
			$sum=$row2['kostpris']*$antal[$x];
			$ialt=$ialt+$sum;
			$pris=dkdecimal($row2['kostpris'],2);
#		}
		$sum=dkdecimal($sum,2);
	if ($udskriv) print "<tr><td>".htmlentities($row['varenr'],ENT_COMPAT,$charset)."</td><td>".htmlentities($row['beskrivelse'],ENT_COMPAT,$charset)."</td><td align=right> $pris</td><td align=right> ".dkdecimal($antal[$x],2)."</td><td align=right> $sum</td><td align=right>".dkdecimal($row['beholdning'])."</td></tr>";
	}
#	$ialt=dkdecimal($ialt,2);
	if ($udskriv) {
		print "<tr><td colspan=5><br></td></tr><tr><td colspan=4> I alt</td></td><td align=right>".dkdecimal($ialt,2)."</td></tr>";
		print "<tbody></table>";
	}
	return($ialt);
}}
?>
