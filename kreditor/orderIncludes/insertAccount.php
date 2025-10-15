<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/ordreIncludes/insertAccount.php----patch 4.0.8 ----2024-06-26--
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
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20230509 PHR php8
// 20230718 LOE Minor modification
// 20231207 PHR lock table while finding next ordrenr.
// 20240626 PHR Added 'fiscal_year' in queries

if (!function_exists('insertAccount')) {
function insertAccount($id, $konto_id) {
	global $addr1,$addr2,$art;
	global $betalingsbet,$betalingsdate,$brugernavn,$bynavn;
	global $cvrnr;
	global $gruppe;
	global $kred_ord_id;
	global $lager,$land;
	global $momssats;
	global $postnr,$regnaar;
	global $status,$sum;
	global $valuta,$omlev;
	$tidspkt=date("U");

	if (!$konto_id) {
		return 0;
		exit;
	}
	if (!$id)          $id          = 0;
	if (!$kred_ord_id) $kred_ord_id = 0;
	if (!$lager)       $lager       = 0;
	if (!$status)      $status      = 0;
	if (!$sum)         $sum         = 0;

	$qtxt = "select * from adresser where id = '$konto_id'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q))
	{
		$kontonr       = trim($r['kontonr']);
		$firmanavn     = db_escape_string(trim($r['firmanavn']));
		$addr1         = db_escape_string(trim($r['addr1']));
		$addr2         = db_escape_string(trim($r['addr2']));
		$postnr        = trim($r['postnr']);
		$bynavn        = db_escape_string(trim($r['bynavn']));
		$land          = db_escape_string(trim($r['land']));
		$betalingsdage = $r['betalingsdage'];
		$betalingsbet  = trim($r['betalingsbet']);
		$cvrnr         = trim($r['cvrnr']);
		$notes         = db_escape_string(trim($r['notes']));
		$gruppe        = trim($r['gruppe']);
		$mail_fakt     = db_escape_string(trim($r['mailfakt']));
		if ($email && $mail_fakt) $udskriv_til = "email";
		else $udskriv_til = "PDF";
	}
	if ($gruppe) {
		$qtxt = "select box1, box3,box9 from grupper where art='KG' and kodenr='$gruppe' and fiscal_year = '$regnaar'";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q);
			$valuta = trim($r['box3']);
			$omlev  = trim($r['box9']);
		if (substr($r['box1'],0,1)=='K') {
			$tmp  = (float)substr($r['box1'],1,1);
			$qtxt = "select box2 from grupper where art='KM' and kodenr = '$tmp' and fiscal_year = '$regnaar'";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			$r = db_fetch_array($q);
			$momssats = (float)trim($r['box2']);
		} elseif (substr($r['box1'],0,1)=='E') {
			$momssats='0.00';
		} elseif (substr($r['box1'],0,1)=='Y') { 
			$momssats='0.00';
		}
	} elseif ($konto_id) print "<BODY onLoad=\"javascript:alert('Kreditor er ikke tilknyttet en kreditorgruppe')\">";
	$momssats=(float)$momssats;
	if ((!$id)&&($firmanavn)) {
		transaktion('begin');
		$ordredate=date("Y-m-d");
		$ordrenr = get_next_order_number('KO');
		$qtxt = "insert into ordrer ";
		$qtxt.= "(ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,lev_navn,lev_addr1,";
		$qtxt.= "lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,betalingsdage,betalingsbet,cvrnr,notes,art,ordredate,";
		$qtxt.= "email,momssats,status,ref,lager,sum,hvem,tidspkt,valuta,kred_ord_id,omvbet)";
		$qtxt.= " values ";
		$qtxt.= "($ordrenr,$konto_id,'$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn',";
		$qtxt.= "'$land','$kontakt','$lev_navn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt',";
		$qtxt.= "'$betalingsdage','$betalingsbet','$cvrnr','$notes','$art','$ordredate','$email','$momssats',$status,";
		$qtxt.="'$brugernavn','$lager','$sum','$brugernavn','$tidspkt','$valuta','$kred_ord_id','$omlev')";
/*		
		$qtxt = "insert into ordrer ";
		$qtxt.= "(ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land,betalingsdage,  ";
		$qtxt.= "betalingsbet, cvrnr, notes, art, ordredate, momssats, status, hvem, tidspkt, valuta,omvbet, ";
		$qtxt.= " email, mail_fakt, udskriv_til,)";
		$qtxt.= " values ";
		$qtxt.= "($ordrenr, '$konto_id', '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', ";
		$qtxt.= "'$land', '$betalingsdage', '$betalingsbet', '$cvrnr', '$notes', '$art', '$ordredate', '$momssats',";
		$qtxt.= "'0', '$brugernavn', '$tidspkt', '$valuta','$omlev', '$email', '$mail_fakt', '$udskriv_til')";
*/
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		transaktion('commit');
		$qtxt = "select max(id) as id from ordrer where ordrenr = '$ordrenr' and konto_id = '$konto_id' and tidspkt = '$tidspkt'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$id=$r['id'];
		} else {
			alert("Something went wrong - call support");
			return '0';
			exit;
		}
	}
	elseif($firmanavn) {
		$q = db_select("select tidspkt from ordrer where id=$id and hvem='$brugernavn'",__FILE__ . " linje " . __LINE__);
		if ($r = db_fetch_array($q)) {
			$qtxt = "update ordrer set konto_id=$konto_id, kontonr='$kontonr', firmanavn='$firmanavn', ";
			$qtxt.= "addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', ";
			$qtxt.= "betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', ";
			$qtxt.= "momssats='$momssats', notes='$notes', hvem = '$brugernavn', tidspkt='$tidspkt', ";
			$qtxt.= "valuta='$valuta', email='$email', mail_fakt='$mail_fakt', udskriv_til='$udskriv_til' ";
			$qtxt.= "where id=$id";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		else {
			$q = db_select("select hvem from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
			if ($r = db_fetch_array($q)) {
				alert ('Ordren er overtaget af $r[hvem]');
			} else alert ('Du er blevet smidt af');
		}
	}
	return $id;
}}
?>

