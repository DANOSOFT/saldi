<?php

// ----------- utils/ducubizzexport.php --- lap 2.0.9 ---- 2009.07.29 -----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

#include ("../online/stdfunc.php")

$filnavn="../temp/$db/kreditor.csv";
$fp=fopen("$filnavn","w");
if ($fp) {
	$q = db_select("SELECT 
				adresser.kontonr as kontonr,
				adresser.firmanavn as firmanavn,
				adresser.cvrnr as cvrnr,
				adresser.betalingsbet as betalingsbet,
				adresser.betalingsdage as betalingsdage,
				adresser.addr1 as addr1,
				adresser.addr2 as addr2,
				adresser.postnr as postnr,
				adresser.bynavn as bynavn,
				adresser.land as land,
				adresser.tlf as tlf,
				adresser.web as web,
				adresser.email as email,
				grupper.box1 as momsart,
				grupper.box3 as valuta
			from adresser, grupper where adresser.art = 'K' and adresser.gruppe=".nr_cast('grupper.kodenr')." and grupper.art='KG' order by adresser.kontonr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		if (is_numeric($r['cvrnr'])) $cvrnr="DK".$r['cvrnr'];
		else $cvrnr=$r['cvrnr'];
		if ($r['betalingsbet']=="Netto" || $r['betalingsbet']=="Lb. md") $betalingsdage = '+ '.$r['betalingsdage'];
		else $betalingsdage='';
		$aktiv='J';
		$spærret='N';	
		if (strpos($r['momsart'],'E')) $EUmoms="J";
		else $EUmoms="N";
		fwrite ($fp, "$r[kontonr]".chr(9)."$r[firmanavn]".chr(9)."".chr(9)."$cvrnr".chr(9)."$aktiv".chr(9)."$r[betalingsbet] $r[betalingsdage]".chr(9)."$r[addr1]".chr(9)."$r[addr2]".chr(9)."$r[postnr]".chr(9)."$r[bynavn]".chr(9)."$r[land]".chr(9)."$r[tlf]".chr(9)."$r[web]".chr(9)."$r[email]".chr(9)."$r[valuta]".chr(9)."$spærret".chr(9)."$EUmoms\n");
	}
	fclose($fp);
}

$filnavn="../temp/$db/projekter.csv";
$fp=fopen("$filnavn","w");
if ($fp) {
	$q = db_select("SELECT * from grupper where art = 'PRJ' order by kodenr",__FILE__ . " linje " . __LINE__); 
	while ($r = db_fetch_array($q)){
		fwrite ($fp, "$r[kodenr]".chr(9)."$r[beskrivelse]".chr(9)."J".chr(9)."".chr(9)."\n");
	}
	fclose($fp);
}
$filnavn="../temp/$db/kontoplan.csv";
$fp=fopen("$filnavn","w");
if ($fp) {
	$r=db_fetch_array(db_select("SELECT max(regnskabsaar) as regnaar from kontoplan",__FILE__ . " linje " . __LINE__));
	$regnaar=$r['regnaar'];
	$q = db_select("SELECT * from kontoplan where regnskabsaar='$regnaar' and (kontotype='D' or kontotype = 'S') order by kontonr",__FILE__ . " linje " . __LINE__); 
	while ($r = db_fetch_array($q)){
		if ($r['lukket']) $aktiv='N';
		else $aktiv='J';
		fwrite ($fp, "$r[kontonr]".chr(9)."$r[beskrivelse]".chr(9)."$aktiv".chr(9)."".chr(9)."$r[genvej]".chr(9)."$r[moms]\n");
	}
	fclose($fp);
}
$filnavn="../temp/$db/medarbejder.csv";
$fp=fopen("$filnavn","w");
if ($fp) {
	$r = db_fetch_array(db_select("SELECT id from adresser where art='S' order by kontonr",__FILE__ . " linje " . __LINE__));
	$id=$r['id']*1; 
	$q = db_select("SELECT * from ansatte where konto_id='$id' order by navn",__FILE__ . " linje " . __LINE__); 
	while ($r = db_fetch_array($q)){
		if ($r['lukket']) $aktiv="N";
		else $aktiv="J";
		fwrite ($fp, "$r[initialer]".chr(9)."$r[navn]".chr(9)."$aktiv\n");
	}
	fclose($fp);
}
$filnavn="../temp/$db/afdelinger.csv";
$fp=fopen("$filnavn","w");
if ($fp) {
	$q = db_select("SELECT * from grupper where art='AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		fwrite ($fp, "$r[kodenr]".chr(9)."$r[beskrivelse]".chr(9)."J\n");
	}
	fclose($fp);
}


$r = db_fetch_array(db_select("SELECT box5 from grupper where art='DocBiz'",__FILE__ . " linje " . __LINE__));
$upload_dir=$r['box5'];

$filnavn="../temp/$db/ftpscript";
$fp=fopen("$filnavn","w");
if ($fp) {
	if ($upload_dir) fwrite ($fp, "cd $upload_dir\n");	
	fwrite ($fp, "delete kreditor.csv\n");
	fwrite ($fp, " delete projekter.csv\n");
	fwrite ($fp, " delete kontoplan.csv\n");
	fwrite ($fp, " delete medarbejder.csv\n");
	fwrite ($fp, " delete afdelinger.csv\n");
	fwrite ($fp, " put kreditor.csv kreditor.csv\n");
	fwrite ($fp, " put projekter.csv projekter.csv\n");
	fwrite ($fp, " put kontoplan.csv kontoplan.csv\n");
	fwrite ($fp, " put medarbejder.csv medarbejder.csv\n");
	fwrite ($fp, " put afdelinger.csv afdelinger.csv\n");
	
	fwrite ($fp, " bye\n");
	fclose($fp);
}


?>
