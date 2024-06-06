<?php
@session_start();
$s_id=session_id();

include ('../includes/connect.php');
include ('../includes/online.php');

(isset($_GET['id']))?$id=$_GET['id']:$id=NULL;
if ($id) {
	$tmp='';
	for ($x=0;$x<strlen($id);$x=$x+2) {
		$tmp.=chr(hexdec(substr($id,$x,2)));
	}
	list($kontonr,$db,$ssl)=explode('@',$tmp);
	echo "kontonr: $kontonr<br>";
	echo "db: $db<br>";
	echo "url: https://$ssl.saldi.dk<br>";
} else {
	print "<html>";	
	print "<center>";
	print "Velkommen til mit salg";
	print "<br><br>";
	print "Email / brugernavn";
	print "<input type='text' name='username'><br><br>";
	print "Adgangskode";
	print "<input type='password' name='password'><br>";
	print "</html>";
	exit;
}
$db='develop_3';
$kontonr=1094;
$dateFrom='2020-04-01';
$dateTo='2020-04-30';
$lineColor=$bgcolor5;

$x=0;
$qtxt = "select batch_salg.fakturadate,batch_salg.ordre_id,batch_salg.antal,batch_salg.pris,ordrelinjer.kostpris,";
$qtxt.= "varer.kostpris as provision from varer,batch_salg,ordrelinjer where varer.varenr like 'kb%$kontonr' ";
$qtxt.= "and batch_salg.vare_id=varer.id and batch_salg.fakturadate>='$dateFrom' and batch_salg.fakturadate<='$dateTo' and ordrelinjer.id=batch_salg.linje_id";
#cho "$qtxt<br>";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$fakturadate[$x]=$r['fakturadate']; 
	$antal[$x]=$r['antal'];
	$pris[$x]=$r['pris'];
	$kostpris[$x]=$r['kostpris'];
	$provision[$x]=$kostpris[$x]*100/$pris[$x];
	$x++;
}

print "<center>";
print "<table style='width:300px'>";
print "<tr>";
print "<td>Periode</td>";
print "<td><input style='width:80px;text-align:center;' type='text' name='dateFrom' value='$dateFrom'></td>";
print "<td> - </td>";
print "<td><input style='width:80px;text-align:center;' type='text' name='dateTo' value='$dateTo'></td>";
print "</tr>";
print "</table><table style='width:300px'>";
print "<tr bgcolor='$lineColor'><td>Dato</td><td>Antal</td><td>Pris</td><td>Din del</td><td>%</td></tr>";
for ($x=0;$x<count($fakturadate);$x++) {
	($lineColor==$bgcolor)?$lineColor=$bgcolor5:$lineColor=$bgcolor;
	print "<tr bgcolor='$lineColor'>";
	print "<td>$fakturadate[$x]</td>";
	print "<td align='right'>". number_format($antal[$x],0,',','.') ."</td>";
	print "<td align='right'>". number_format($pris[$x],2,',','.') ."</td>"; 
	print "<td align='right'>". number_format($pris[$x]-$kostpris[$x],2,',','.') ."</td>"; 
	print "<td align='right'>$provision[$x]</td>";
	print "</tr>";
}
print "</table>";

?>
