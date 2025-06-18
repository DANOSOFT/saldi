<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/insertOpenPost.php --- ver 4.0.6 --- 2022.11.11 ---
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
//
// Copyright (c) 2003-2022 saldi.dk ApS
// ----------------------------------------------------------------------


function insertOpenPost($kladde_id) {
	
	global $db_type;
	
	$dColumn = $kColumn = NULL;
	
	
	$accountNo   = if_isset($_POST['accountNo']);
	$accountType = if_isset($_POST['accountType']);
	$addLine     = if_isset($_POST['addLine']);
	$currency    = if_isset($_POST['currency']);
	$insertId    = if_isset($_POST['insertId']);
	$invoiceNo   = if_isset($_POST['invoiceNo']);
	$openAamount = if_isset($_POST['openAamount']);
	$openPostId  = if_isset($_POST['openPostId']);
	$opptext     = if_isset($_POST['opptext']);

	$qtxt = "select * from kassekladde where id = $insertId";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$afd       = $r['afd'];
	$amount    = $r['amount'];
	$ansat     = $r['ansat'];
	$bilag     = $r['bilag'];
	$projekt   = $r['projekt'];
	$transdate = $r['transdate'];
	$text      = $r['beskrivelse'];
	$d_type    = $r['d_type'];
	$debet    = $r['debet'];
	$k_type    = $r['k_type'];
	$kredit    = $r['kredit'];
	
	$deleteAccount=$openSum=0;
	for ($x=0;$x<count($accountNo);$x++) {
		if (isset ($addLine[$x]) && $addLine[$x] == 'on') {
			$openSum+= afrund($openAamount[$x],2);
			$deleteAccount=1;
			if ($openAamount[$x] < 0) {
				$d_type = $accountType[$x];
				$debet = $accountNo[$x];
				$k_type = 'F';
				$kredit = '0';
			} else {
				$k_type = $accountType[$x];
				$kredit = $accountNo[$x];
				$d_type = 'F';
				$debet  = '0';
			}	
			if ($currency[$x] == 'DKK') $valuta = '0';
			else {
				$qtxt = "select kodenr from grupper where box1 = '$currency' and art = 'VK'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$valuta = $r['kodenr'];
			}
			$qtxt = "insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, ";
			$qtxt.= "faktura, amount, momsfri, afd, projekt, ansat, valuta, kladde_id,betal_id)";
			$qtxt.= "values ";
			$qtxt.= "('$bilag', '$transdate', '$opptext[$x]', '$d_type', '$debet', '$k_type', '$kredit', ";
			$qtxt.= "'$invoiceNo[$x]', '".abs($openAamount[$x])."', 'on', '$afd', '$projekt', '$ansat', '$valuta', ";
			$qtxt.= "'$kladde_id','')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	if ($deleteAccount) {
		$qt1 = "update kassekladde "; 
		if ($k_type == 'F') {
			$newAmount = $amount + $openSum;
			$qt1.= "set debet = '0' ";
		} else {
			$newAmount = $amount - $openSum;
			$qt1.= "set kredit = '0' ";
		}
		$qt1.= "where id = '$insertId'"; 
		$qtxt = "delete from tmpkassekl where id = '$insertId'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$newId = 0;
		if (abs($newAmount) >= 0.01) {
			$qtxt = "select max(id) as newid from kassekladde where kladde_id = '$kladde_id'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$newId = $r['newid']+1;
			$qtxt = "CREATE TEMPORARY TABLE temp_table AS SELECT * FROM kassekladde WHERE id='$insertId'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "UPDATE temp_table SET id='$newId' WHERE id='$insertId'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "INSERT INTO kassekladde SELECT * FROM temp_table";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			if ($db_type == 'postgresql') $qtxt = "DROP TABLE temp_table";
			else $qtxt = "DROP TEMPORARY TABLE temp_table";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} 
		db_modify($qt1,__FILE__ . " linje " . __LINE__);
		$qtxt = "update kassekladde set k_type = 'F', kredit = '0', d_type = 'F', debet = '0', ";
		$qtxt.= "amount = '$newAmount' where id = '$newId'"; 
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
?>
