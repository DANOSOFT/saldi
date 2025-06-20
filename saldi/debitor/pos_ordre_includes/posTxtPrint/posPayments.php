<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/posTxtPrint/posPayments.php ---------- lap 3.7.4----2019.05.08-------
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// 06.05.2019 LN Get data from pos_betalinger and update ordrer 

	$q=db_select("select * from pos_betalinger where ordre_id = '$id' order by id",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$betalingstype[$x]=$r['betalingstype'];
		$amount[$x]=$r['amount']*1;
		$valuta[$x]=$r['valuta'];
		$valutakurs[$x]=$r['valutakurs']*1;
		if (!$valuta) $valuta='DKK';
		if (!$valutakurs) $valutakurs=100;
		if ($valuta[$x]!='DKK') $fremmedvaluta=1;
		$x++;
	}
	for ($x=0;$x<count($amount);$x++) {
		if ($x==0) db_modify("update ordrer set felt_1='$betalingstype[$x]',felt_2='$amount[$x]' where id='$id'",__FILE__ . " linje " . __LINE__);
		elseif ($x==1) db_modify("update ordrer set felt_3='$betalingstype[$x]',felt_4='$amount[$x]' where id='$id'",__FILE__ . " linje " . __LINE__);
		$betalt+=$amount[$x];
		$dkkamount[$x]=dkdecimal($amount[$x],2);
		while(strlen($dkkamount[$x])<9) $dkkamount[$x]=" ".$dkkamount[$x];
		$valutaamount[$x]=dkdecimal($amount[$x]*100/$valutakurs[$x],2);
		if ($fremmedvaluta) {
			if ($valuta[$x]!='DKK') $betalingstype[$x].=" $valuta[$x] $valutaamount[$x]";
			else $betalingstype[$x].=" $valuta[$x]";
		}
		
		$betalingstype[$x] = iconv($FromCharset, $ToCharset,$betalingstype[$x]);
		while(strlen($betalingstype[$x])<19) $betalingstype[$x].=" ";
	}



?>

