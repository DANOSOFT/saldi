
<?php
//          ___   _   _   ___  _     ___  _ _
//         / __| / \ | | |   \| |   |   \| / /
//         \__ \/ _ \| |_| |) | | _ | |) |  <
//         |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/restApiIncludes/CreateDebitor.php --- lap 4.0.5 --- 2022-03-09 ---
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
// Copyright (c) 2016-2022 saldi.dk aps
// ----------------------------------------------------------------------
// 20260911 Sawaneh Blank the literal "dummyvalue" Shoptech sends for empty address
//                     fields via strip_placeholder_value(); if_isset converted to ifset (JOB-115)

// 20260920 CDX/LH Preserve CVR/EAN and atomically allocate/create debtor and contact email.
function CreateDebitor() {
	global $db, $db_type, $db_modify_fejl;

	$log=fopen("../temp/$db/rest_api.log","a");
	fwrite($log,__line__." ". date("H:i:s") ." createDebitor\n");

	$addr1         = strip_placeholder_value(ifset($_GET, 'addr1'));
	$addr2         = strip_placeholder_value(ifset($_GET, 'addr2'));
	$afd           = ifset($_GET, 'afd')*1;
	$betalingsbet  = ifset($_GET, 'betalingsbet');
	$betalingsdage = ifset($_GET, 'betalingsdage');
	$bynavn        = strip_placeholder_value(ifset($_GET, 'bynavn'));
	$cvrnr         = strip_placeholder_value(ifset($_GET, 'cvr'));
	$ean           = strip_placeholder_value(ifset($_GET, 'ean'));
	$firmanavn     = strip_placeholder_value(ifset($_GET, 'firmanavn'));
	$efternavn     = ifset($_GET, 'efternavn');
	$fornavn       = ifset($_GET, 'fornavn');
	$gruppe        = ifset($_GET, 'gruppe');
	$kontakt       = strip_placeholder_value(ifset($_GET, 'kontakt'));
	$kontonr       = ifset($_GET, 'kontonr');
	$kundetype     = ifset($_GET, 'kundetype');
	$land          = strip_placeholder_value(ifset($_GET, 'land'));
	$lev_firmanavn = strip_placeholder_value(ifset($_GET, 'lev_firmanavn'));
	$lev_addr1     = strip_placeholder_value(ifset($_GET, 'lev_addr1'));
	$lev_addr2     = strip_placeholder_value(ifset($_GET, 'lev_addr2'));
	$lev_postnr    = strip_placeholder_value(ifset($_GET, 'lev_postnr'));
	$lev_bynavn    = strip_placeholder_value(ifset($_GET, 'lev_bynavn'));
	$lev_land      = strip_placeholder_value(ifset($_GET, 'lev_land'));
	$lev_tlf       = strip_placeholder_value(ifset($_GET, 'lev_tlf'));
	$lev_email     = strip_placeholder_value(ifset($_GET, 'lev_email'));
	$lev_kontakt   = strip_placeholder_value(ifset($_GET, 'lev_kontakt'));
	$minNo         = ifset($_GET, 'minNo');
	$maxNo         = ifset($_GET, 'maxNo');
	$postnr        = strip_placeholder_value(ifset($_GET, 'postnr'));
	$tlf           = ifset($_GET, 'tlf');
	$email         = ifset($_GET, 'email');
	$email_type    = ifset($_GET, 'email_type');
	if (!$email_type) $email_type = 'hoved';

	if (!transaktion('begin')) {
		fclose($log);
		return 'Failed to start debtor creation';
	}
	$finished = false;
	try {
		// Serialize allocation and creation together, including the first account.
		if ($db_type !== 'mysql' && $db_type !== 'mysqli') {
			db_modify('LOCK TABLE adresser IN SHARE ROW EXCLUSIVE MODE', __FILE__ . ' linje ' . __LINE__);
		} else {
			db_select('SELECT id FROM adresser ORDER BY id FOR UPDATE', __FILE__ . ' linje ' . __LINE__);
		}
		if ($db_modify_fejl) {
			return 'Failed to lock debtor account allocation';
		}
		if (!$kontonr) {
			require_once(__DIR__ . '/getNextAccountNo.php');
			$kontonr = getNextAccountNo('D');
			if (!is_int($kontonr)) {
				return $kontonr;
			}
		}
		$accountNo = (string)$kontonr;
		$kontonr = db_escape_string($accountNo);
		$qtxt = "SELECT id FROM adresser WHERE kontonr='$kontonr' AND art='D'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . ' linje ' . __LINE__));
		if ($r) {
			return "Account $accountNo allready exists";
		}
		$gruppe = db_escape_string((string)$gruppe);
		$betalingsbet = db_escape_string((string)$betalingsbet);
		$betalingsdage = db_escape_string((string)$betalingsdage);

		if (!$betalingsbet) $betalingsbet   = 'Netto';
		if (!$betalingsdage) $betalingsdage = 8;

		fwrite($log,__line__." ". date("H:i:s") ." kontonr $kontonr\n");
		$qtxt = "insert into adresser";
		$qtxt.= "(kontonr,firmanavn,addr1,addr2,";
		$qtxt.= "postnr,bynavn,land,cvrnr,ean,email,tlf,";
		$qtxt.= "gruppe,art,betalingsbet,betalingsdage,kontakt,";
		$qtxt.= "lev_firmanavn,lev_addr1,lev_addr2,";
		$qtxt.= "lev_postnr,lev_bynavn,lev_land,";
		$qtxt.= "lev_kontakt,lev_tlf,lev_email,lukket)";
		$qtxt.= " values ";
		$qtxt.="('$kontonr','".db_escape_string((string)$firmanavn)."','".db_escape_string((string)$addr1)."','".db_escape_string((string)$addr2)."',";
		$qtxt.="'".db_escape_string((string)$postnr)."','".db_escape_string((string)$bynavn)."','".db_escape_string((string)$land)."',";
		$qtxt.="'".db_escape_string((string)$cvrnr)."','".db_escape_string((string)$ean)."','".db_escape_string((string)$email)."','".db_escape_string((string)$tlf)."',";
		$qtxt.="'$gruppe','D','$betalingsbet','$betalingsdage','".db_escape_string((string)$kontakt)."',";
		$qtxt.="'".db_escape_string((string)$lev_firmanavn)."','".db_escape_string((string)$lev_addr1)."','".db_escape_string((string)$lev_addr2)."',";
		$qtxt.="'".db_escape_string((string)$lev_postnr)."','".db_escape_string((string)$lev_bynavn)."','".db_escape_string((string)$lev_land)."',";
		$qtxt.="'".db_escape_string((string)$lev_kontakt)."','".db_escape_string((string)$lev_tlf)."','".db_escape_string((string)$lev_email)."','')";
		fwrite($log,__line__." $qtxt\n");
		$qtxt=chk4utf8($qtxt);
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		if ($db_modify_fejl) {
			return 'Failed to create debtor';
		}
		$qtxt = "select id from adresser where kontonr='$kontonr' and art = 'D'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$id=$r ? $r['id'] : null;
		if (!$id) {
			return 'Failed to retrieve created debtor';
		}

		// Save email to kontakt_emails table
		if ($email && $id) {
			db_modify("INSERT INTO kontakt_emails (konto_id, email, email_type) VALUES ('$id', '".db_escape_string((string)$email)."', '".db_escape_string((string)$email_type)."')", __FILE__ . " linje " . __LINE__);
		}

		if ($db_modify_fejl) {
			return 'Failed to create debtor contact email';
		}
		$commitResult = transaktion('commit');
		$finished = true;
		if (!$commitResult || $db_modify_fejl) {
			return 'Failed to commit debtor creation';
		}
		return "$id,$accountNo";
	} finally {
		if (!$finished) {
			transaktion('rollback');
		}
		fclose($log);
	}
}
