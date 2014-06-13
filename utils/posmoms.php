<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

#$logdatetime=array();
#$sum=array();
$bogfor=if_isset($_GET['bogfor']);
$vis=if_isset($_GET['vis']);
$email=if_isset($_GET['email']);

$x=0;
$total=0;
$subtotal=0;
$fejl=0;

/*
if ($vis) {
	echo "ID $db_id<br>";
	if ($bogfor) echo "Bogfører $bogfor<br>";
	else echo "Bogfører ikke<br>";
}
*/

#if ($bogfor) transaktion('begin');
$x=0;
$l_ordresum=array();
$l_ordre_id=array();
$l_ordre_id[0]=0;
$q=db_select("select * from ordrelinjer where ordre_id>0 order by ordre_id",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)){
	if ($r['ordre_id']!=$l_ordre_id[$x]) {
		if ($l_ordre_id[$x]) {
#cho "L $l_ordre_id[$x] Sum: $l_ordresum[$x] Moms: $l_momssum[$x]<br>";
			$x++;
		}
		$l_ordre_id[$x]=$r['ordre_id'];
		$l_ordresum[$x]=0;
		$l_momssum[$x]=0;
		$l_momsfri[$x]=0;
	} 
	$linjesum=$r['antal']*($r['pris']-($r['pris']*$r['rabat']/100));
	$l_ordresum[$x]+=$linjesum;
	if ($r['momsfri']) $l_momsfri[$x]=1;
	else $l_momssum[$x]+=$linjesum*$r['momssats']/100;
}

$ordre_id=array();
$ordresum=array();
$momssum=array();
$x=0;
$q=db_select("select * from ordrer where art='PO' and status >= 3 order by id",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)){
	$bonnr[$x]=$r['fakturanr'];
	$bondate[$x]=$r['fakturadate'];
	$ordre_id[$x]=$r['id'];
	$ordresum[$x]=$r['sum'];
	$momssum[$x]=$r['moms'];
	$momssats[$x]=$r['momssats'];
#cho "O $ordre_id[$x] Sum: $ordresum[$x] Moms: $momssum[$x]<br>";
	$x++;
}

print "<table><tbody>";
print "<tr><td>Dato</td><td>Bon nr</td><td>Diff sum</td><td>Diff moms</td><td>Diff i alt</td></tr>";
$m_sum=0;
$s_sum=0;
for ($x=0;$x<count($ordre_id);$x++){
	for($y=0;$y<count($l_ordre_id);$y++){
		if ($ordre_id[$x]==$l_ordre_id[$y]){
			$s=afrund($ordresum[$x]-$l_ordresum[$y],2);
			if (!$l_momsfri[$y]) {
				$l_momssum[$y]=$l_ordresum[$y]*$momssats[$x]/100;
			}
			$m=afrund($momssum[$x]-$l_momssum[$y],2);
			if ($s||$m) {
				$s_sum+=$s;
				$m_sum+=$m;
				print "<tr><td>".dkdato($bondate[$x])."</td><td>$bonnr[$x]</td><td align=\"right\">".dkdecimal($s)."</td><td align=\"right\">".dkdecimal($m)."</td><td align=\"right\">".dkdecimal($s+$m)."</td><td></tr>";

			#cho "<Fejl på Nummer $ordrenr[$x]($ordre_id[$x]) Sumdiff=$s, Momsdiff=$m<br>Ordersum: $ordresum[$x] Ordrelinjesum: $l_ordresum[$y]<br>";  
			} #else echo "Ingen fejl på ID $ordre_id[$x]. Sumdiff=$s, Momsdiff=$m<br>";
		}
	}
}
print "<tr><td colspan=\"2\"><b>I alt</b></td><td align=\"right\"><b>".dkdecimal($s_sum)."</b></td><td align=\"right\"><b>".dkdecimal($m_sum)."</b></td><td align=\"right\"><b>".dkdecimal($s_sum+$m_sum)."</b></td><td></tr>";
print "</tbody></table>";

#if ($bogfor) transaktion('commit');

?>
