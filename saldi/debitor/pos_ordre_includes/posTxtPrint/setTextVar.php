<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/posTxtPrint/setTextVar.php --- lap 3.8.9----2020.02.11-------
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
// Copyright (c) 2004-2020 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20190705 LN Handle txt variables
// 20200211 PHR	Corrected error in parameters 

    $sum+=$moms;
	if ($konto_id) {
		if (!$x) $indbetaling=$sum;
		$gl_saldo=dkdecimal($betaling2,2);
		$ny_saldo=dkdecimal($modtaget2,2);
	}
	if ($indbetaling) $retur=$modtaget-$indbetaling;
	if ($rvnr && $samlet_pris) $retur=$betalt-$samlet_pris;
	else $retur=$betalt-$sum;
	$dkkretur=dkdecimal($retur,2);
	while(strlen($dkkretur)<9){
		$dkkretur=" ".$dkkretur;
	}
	$betalt=dkdecimal($betalt,2);
	while(strlen($betalt)<9){
		$betalt=" ".$betalt;
	}
	while(strlen($betaling)<19){
		$betaling=$betaling." ";
	}
	while(strlen($betaling2)<19){
		$betaling2=$betaling2." ";
	}
	$dkkmodtaget=dkdecimal($modtaget,2);
	while(strlen($dkkmodtaget)<9){
		$dkkmodtaget=" ".$dkkmodtaget;
	}
	if ($modtaget2) {
		$dkkmodtaget2=dkdecimal($modtaget2,2);
		while(strlen($dkkmodtaget2)<9){
			$dkkmodtaget2=" ".$dkkmodtaget2;
		}
	}
	$dkksum=dkdecimal($sum,2);
	if ($rvnr && $samlet_pris) $dkksum=dkdecimal($samlet_pris,2);
	while(strlen($dkksum)<9){
		$dkksum=" ".$dkksum;
	}
	$dkkmoms=dkdecimal($moms,2);
	if ($rvnr && $samlet_pris) {
		if (abs($sum/5-$moms)<0.02) $dkkmoms=dkdecimal($samlet_pris/5,2);
	}
	while(strlen($dkkmoms)<9){
		$dkkmoms=" ".$dkkmoms;
	}

	$query = db_select("select var_value from settings where var_name='deactivateBonprint'", __FILE__ . " linje " . __LINE__);
	$disabledPrinter = db_fetch_array($query)['var_value'];
	if($doNotPrint == "copied") {
		printWarningMessage($doNotPrint);
		return true;
	} elseif ($disabledPrinter == false) {
		if($type == "xRapport") {
			$filnavn = "pos_ordre_includes/report/xRapport.php";
		} elseif ($type == "zRapport") {
			$filnavn = "pos_ordre_includes/report/zRapport.php";
		} else {
			$filnavn="pos_print/pos_print_".$db_id.".php";
		}
		if (file_exists("$filnavn")){
			include("$filnavn");
		}
		else include("pos_print/pos_print.php");
		fclose($fp);
	}


?>
