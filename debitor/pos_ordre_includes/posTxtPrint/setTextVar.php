<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/posTxtPrint/setTextVar.php --- lap 4.1.1 --- 2020.02.11 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2019-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20190705 LN Handle txt variables
// 20200211 PHR	Corrected error in parameters 
// 20240801 PHR - Replaced 'DKK' with $baseCurrency.

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
	if(isset($doNotPrint) && $doNotPrint == "copied") {
		printWarningMessage($doNotPrint);
		return true;
	} elseif ($disabledPrinter == false) {
		if($type == "xRapport") {
			$filnavn = "pos_ordre_includes/report/xRapport.php";
		} elseif ($type == "zRapport") {
			$filnavn = "pos_ordre_includes/report/zRapport.php";
		} else {
			$qtxt = "insert into pos_events (ev_type,ev_time,cash_register_id,employee_id,order_id,file,line) ";
			$qtxt.= "values ";
			$qtxt.= "('13012','". date('U') ."','$kasse','$bruger_id','$id','".__file__."','".__line__."')";
			db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
			if ($betaling == 'Kontant' || $betaling2 == 'Kontant' || abs($retur) > 0.01) {
				$qtxt = "insert into pos_events (ev_type,ev_time,cash_register_id,employee_id,order_id,file,line) ";
				$qtxt.= "values ";
				$qtxt.= "('13005','". date('U') ."','$kasse','$bruger_id','$id','".__file__."','".__line__."')";
				db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$filnavn="pos_print/pos_print_".$db_id.".php";
		}
		if (file_exists("$filnavn")){
			include("$filnavn");
		}
		else include("pos_print/pos_print.php");
		fclose($fp);
	}


?>
