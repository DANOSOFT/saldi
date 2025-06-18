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

$fakturadate = '2024-01-05';

$i=0;
$total=0;
$subtotal=0;
$fejl=0;
$qtxt = "select distinct(felt_5) as kasse 
	from ordrer 
	where fakturadate = '$fakturadate' 
	and art = 'PO' 
	and status > '3' 
	order by felt_5";
echo "$qtxt<br>";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($r['kasse']) {
		$kasse[$i] = $r['kasse'];
		$i++;
	}
}

$i=0;
for($x=0;$x<count($kasse);$x++) { 
	$qtxt = "select distinct(box4) as box4 from grupper where art = 'VG' order by box4";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$vgAccount[$i] = $r['box4'];
		$accountSum[$i] = 0;
		$accountVat[$i] = 0;
		$accountVat[$i] = 0;
		$accountBox[$i] = $kasse[$x];
		$trSum[$i] = 0;
		$i++;
	}
	$vgAccount[$i] = '7950';
	$accountSum[$i] = 0;
	$accountVat[$i] = 0;
	$accountVat[$i] = 0;
	$accountBox[$i] = $kasse[$x];
	$trSum[$i] = 0;
	$i++;
	$vgAccount[$i] = '66100';
	$accountSum[$i] = 0;
	$accountVat[$i] = 0;
	$accountVat[$i] = 0;
	$accountBox[$i] = $kasse[$x];
	$trSum[$i] = 0;
	$i++;
}
for ($i=0;$i<count($vgAccount);$i++) {
	$qtxt = "select pris,antal,bogf_konto,ordrelinjer.momssats,momsfri,rabat,m_rabat,rabatart,vat_price
		from ordrer,ordrelinjer 
		where ordrelinjer.ordre_id = ordrer.id
		and ordrelinjer.bogf_konto = '$vgAccount[$i]' 
		and ordrer.fakturadate = '$fakturadate' 
		and ordrer.art = 'PO' 
		and ordrer.status > '3' 
		and ordrer.felt_5 = '$accountBox[$i]'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
	if ($r['rabat'] && $r['rabatart'] == 'amount') {
		$lineSum = $r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']);
	} elseif ($r['rabat']) {
		$lineSum = $r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100);
	} elseif ($r['m_rabat'] && $r['rabatart'] == 'amount') {
		$lineSum = $r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['m_rabat']);
	} else {
		$lineSum = $r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['m_rabat']/100);
	}
	if ($r['momsfri'] != 'on') $accountVat[$i]+= $lineSum*$r['momssats']/100;
	$accountSum[$i]+= $lineSum;
	}
}

for ($i=0;$i<count($vgAccount);$i++) {
	$qtxt = "select * from transaktioner where transdate = '$fakturadate' 
		and kasse_nr = '$accountBox[$i]' and kontonr = '$vgAccount[$i]' and beskrivelse like '%afslutning%' ";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$trSum[$i]+= $r['kredit'] - $r['debet'];
	}
}

$diff = 0;
for ($i=0;$i<count($vgAccount);$i++) {
	if ($i==0 || $accountBox[$i] != $accountBox[$i-1]) $diffsum = 0;
	$diff = $accountSum[$i] - $trSum[$i];
	$diffsum+=$diff;
	echo "Kasse $accountBox[$i] | ";	
	echo "Account $vgAccount[$i] | ";	
	echo "Sum $accountSum[$i] | ";	
	echo "Vat $accountVat[$i] | ";	
	echo "Tr $trSum[$i] | ";
	echo "Diff ". round($diff,2) ." | ";	
	if ($i+1 == count($vgAccount) || $accountBox[$i] != $accountBox[$i+1]) echo "Diffsum ". round($diffsum,2);
	echo "<br>";	
}

?>
