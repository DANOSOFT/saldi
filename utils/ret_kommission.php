<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$x=0;
$qtxt = "select ordrelinjer.* from ordrelinjer,ordrer where ordrer.id = ordrelinjer.ordre_id and ordrer.fakturadate >= '2022-08-01' ";
$qtxt.= "and (varenr like 'kb%' or varenr like 'kn%')";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)) {
	$linjeId[$x]=$r['id'];
	$linjeVaId[$x]=$r['vare_id'];
	$vareNr[$x]=$r['varenr'];
	$pris[$x]=$r['pris'];
	if ($vareNr[$x] == 'kbfrh1326') echo "$vareNr[$x] $pris[$x]<br>"; 	
	$rabat[$x]==$r['rabat'];
	$mRabat[$x]==$r['m_rabat'];
	$x++; 
}
$y=0;
$qtxt = "select * from varer where kostpris > 0 and kostpris < 1 and (varenr like 'kb%' or varenr like 'kn%') order by varenr";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)) {
	if (in_array($r['id'],$linjeVaId)) {
		$vareId[$y]=$r['id'];
		$vareKost[$y]=$r['kostpris'];
		$y++; 
	}
}
for ($x=0;$x<count($linjeId);$x++) {
	for ($y=0;$y<count($vareId);$y++) {
		if ($vareId[$y] == $linjeVaId[$x]) {
			if ($vareNr[$x] == 'kbfrh1326') echo "$pris[$x]<br>"; 	
				$pris[$x]=$pris[$x]-($pris[$x] * $rabat[$x] / 100);
				if ($vareNr[$x] == 'kbfrh1326') echo "$pris[$x]<br>"; 	
				$pris[$x]=$pris[$x]-($pris[$x] * $mRabat[$x] / 100);
				if ($r['varenr'] == 'kbfrh1326') echo "$pris[$x]<br>"; 	
			$qtxt = "update ordrelinjer set kostpris = $pris[$x]*$vareKost[$y] where id = '$linjeId[$x]'";
			echo "$qtxt ($vareNr[$x])<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
}
