<?php
@session_start();
$s_id=session_id();
ini_set("display_errors", "0");

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/std_func.php");
	$x=0;
	$q=db_select("select distinct transdate from transaktioner order by transdate");
	while ($r=db_fetch_array($q)) {
	 $x++;
	 $transdate[$x]=$r['transdate'];
	 
	} 		
	$datoantal=$x;
	$fejlantal=0;
	for ($y=1;$y<=$datoantal;$y++) {
	$sum=array();
	$bilag=array();
		$x=0;
		$q=db_select("select debet, kredit, bilag, kladde_id from transaktioner where transdate='$transdate[$y]' order by bilag");
		while ($r=db_fetch_array($q)) {
			$bi_lag=$r['bilag']*1;
			if (!in_array($bi_lag,$bilag)) {
				if ($bilag[$x] && abs($sum[$x])>=0.01) {
					$fejlantal++;
					$fejlbilag[$fejlantal]=$bilag[$x];
					$fejlkladde[$fejlantal]=$kladde_id[$x];
					$fejlsum[$fejlantal]=$sum[$x];
					$fejldate[$fejlantal]=$transdate[$y];
				}
				$x++;
				$sum[$x]=$r['debet']-$r['kredit'];
				$bilag[$x]=$bi_lag;
				$kladde_id[$x]=$r['kladde_id'];
			} else {
				$sum[$x]=$sum[$x]+$r['debet']-$r['kredit'];
			}
		}
		if ($bilag[$x] && abs($sum[$x])>=0.01) {
			$fejlantal++;
			$fejlbilag[$fejlantal]=$bilag[$x];
			$fejlkladde[$fejlantal]=$kladde_id[$x];
			$fejlsum[$fejlantal]=$sum[$x];
			$fejldate[$fejlantal]=$transdate[$y];
		}
	
	}
#echo "ialt ".dkdecimal($total)."<br>";
	$bilag=array();	
	$sum=0;
	$y=0;
	print "<table colspan=5 border=3><tbody>";
	print  "<tr><td>X</td><td align=center>transdate</td><td align=center>Kassekladde</td><td align=center>bilag</td><td align=center>bel&oslash;b</td></tr>";	
	for($x=1;$x<=$fejlantal;$x++) {
			print "<tr><td>$x</td><td>".dkdato($fejldate[$x])."</td><td align=right>$fejlkladde[$x]</td><td align=right>$fejlbilag[$x]</td><td align=right>".dkdecimal($fejlsum[$x])."</td></tr>";
			$sum=$sum+$fejlsum[$x];	
		}
		print "<tr><td colspan=4>Kontrolsum</td><td align=right>".dkdecimal($sum)."</td></tr>";

?>