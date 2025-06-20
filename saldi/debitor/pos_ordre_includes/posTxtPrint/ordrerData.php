<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/posTxtPrint/ordrerData.php ---------- lap 3.7.4----2019.05.08-------
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
// 07.05.2019 LN Get data from ordrer

	$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['konto_id'];
	$kontonr=$r['kontonr'];
	$kundenavn=$r['firmanavn'];
	$kundeaddr1=$r['addr1'];
	$kundepostnr=$r['postnr'];
	$kundeby=$r['bynavn'];
	$kundeordnr=$r['kundeordnr'];
	$fakturadate=$r['fakturadate'];
	$fakturanr=$r['fakturanr'];
	$betalingsbet=$r['betalingsbet'];
	$fakturadato=dkdato($r['fakturadate']);
	$sum=$r['sum'];
	$moms=$r['moms'];
	$momssats=$r['momssats'];
	$betaling=$r['felt_1'];
	$modtaget=$r['felt_2']*1;
	$betaling2=$r['felt_3'];
	$modtaget2=$r['felt_4']*1;
	$ref=$r['ref'];
	$kasse=$r['felt_5'];
	$tidspkt=$r['tidspkt'];
	$dkdato=dkdato(substr($tidspkt,0,10));
	$tid=substr($tidspkt,-5);
	$bordnr=$r['nr']*1;  #20140508
	$status=$r['status'];
	$temp = $r['id'];	

?>

