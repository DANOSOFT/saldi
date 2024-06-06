<?php
$cNo[0]=0;
$cName[0]='DKK';
$v=1;
$q=db_select("select kodenr,box1 from grupper where art='VK' order by kodenr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)){
	$cNo[$v]   = $r['kodenr'];
	$cName[$v] = $r['box1'];
	$v++;
}
if (!$regnaar) {$regnaar=1;}
$i=0;
$qtxt = "select * from kontoplan where regnskabsaar='$regnaar' order by kontonr";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)){
	$accountId[$i]     = $r['id'];
	$accountNo[$i]     = $r['kontonr'];
	$accountName[$i]   = $r['beskrivelse'];
	$accountType[$i]   = $r['kontotype'];
	$shortCut[$i]      = $r['genvej'];
	$balance[$i]       = $r['saldo'];
	$countFrom[$i]     = $r['fra_kto'];
	$countTo[$i]       = $r['til_kto'];
	$vat[$i]           = $r['moms'];
	$currency[$i]      = $r['valuta'];
	$exchangeRate[$i] = $r['valutakurs'];
	$closed[$i]        = $r['lukket'];
	$currencyName[$i]  = 'DKK';
	if ($currency[$i] == '') {
		$currency[$i]=0;
		$exchangeRate[$i]=100;
		$qtxt = "update kontoplan set valuta='$currency[$i]',valutakurs='$exchangeRate[$i]' ";
		$qtxt.= "where id='$accountId[$i]'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	if ($currency[$i]) {
		for ($v = 1;$v<count($cNo);$v++) {
			if ($currency[$i] == $cNo[$v]) $currencyName[$i] = $cName[$v];
		}
	}
	$i++;
}
?>

