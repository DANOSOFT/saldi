<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------------debitor/sync_stamkort.php--------ver 5.0.0-------2026.10.23--------------
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
// Copyright (c) 2008-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20230123 PHR Replaced addslashes by db_escape_string
// 20261023 CL/MMR SST-887 Arrow up now also sets fornavn/efternavn for private customers.
//                 Only firmanavn was updated, so the card kept the old name and saving it restored that name.
//                 ordre_id and konto_id are cast to int before use in SQL.
// 20261023 CL/MMR SST-887 Moved the sync into syncStamkort() per the controller/view convention (review by Codex).
// 20261027 CL/LH SST-887 ordre_id and konto_id must be plain digit strings; anything else (an array such as
//                ordre_id[]=7, which (int) turned into 1) counts as a missing id. Raised by CodeRabbit on PR #851.

@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

// An id is a plain digit string. (int) would turn an array (ordre_id[]=7) into 1 and sync the wrong order,
// so anything else counts as a missing id: nothing is synced.
$ordre_id=ifset($_GET, 'ordre_id', '');
$ordre_id=(is_string($ordre_id) && ctype_digit($ordre_id)) ? (int)$ordre_id : 0;
$konto_id=ifset($_GET, 'konto_id', '');
$konto_id=(is_string($konto_id) && ctype_digit($konto_id)) ? (int)$konto_id : 0;
$retning=ifset($_GET, 'retning', '');

/**
 * Copies name, address and terms between an order and its debitor card.
 *
 * retning 'op' copies the order onto the card (adresser); anything else copies the card onto the
 * order. For a private card, 'op' also sets fornavn/efternavn from the order's name, because the card
 * shows those fields and rebuilds firmanavn from them on save (SST-887).
 *
 * @param int    $ordre_id ordrer.id
 * @param int    $konto_id adresser.id
 * @param string $retning  'op' (order to card) or 'ned' (card to order)
 * @return void
 */
function syncStamkort($ordre_id, $konto_id, $retning) {
	$ordre_id = (int)$ordre_id;
	$konto_id = (int)$konto_id;
	if ($retning=='op') $qtxt = "select * from ordrer where id='$ordre_id'";
	else $qtxt = "select * from adresser where id='$konto_id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$firmanavn=db_escape_string($r['firmanavn']);
	$addr1=db_escape_string($r['addr1']);
	$addr2=db_escape_string($r['addr2']);
	$postnr=db_escape_string($r['postnr']);
	$bynavn=db_escape_string($r['bynavn']);
	$land=db_escape_string($r['land']);
	$cvrnr=db_escape_string($r['cvrnr']);
	$betalingsbet=db_escape_string($r['betalingsbet']);
	$betalingsdage=$r['betalingsdage']*1;
	$email=db_escape_string($r['email']);
	$ean=db_escape_string($r['ean']);
	$institution=db_escape_string($r['institution']);
	if ($retning=='op') {
		// A private customer's card shows fornavn/efternavn and rebuilds firmanavn from them on save,
		// so they must follow the new name: last word is efternavn, the rest is fornavn.
		$navnefelter = '';
		$qtxt = "select kontotype from adresser where id='$konto_id'";
		$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r2 && $r2['kontotype'] == 'privat') {
			$navnedele = preg_split('/\s+/', trim((string)$r['firmanavn']), -1, PREG_SPLIT_NO_EMPTY);
			$efternavn = (count($navnedele) > 1) ? array_pop($navnedele) : '';
			$fornavn = implode(' ', $navnedele);
			$navnefelter = "fornavn='" . db_escape_string($fornavn) . "',efternavn='" . db_escape_string($efternavn) . "',";
		}
		$qtxt = "update adresser set $navnefelter";
		$qtxt.= "firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',land='$land',";
		$qtxt.= "cvrnr='$cvrnr',betalingsbet='$betalingsbet',betalingsdage='$betalingsdage',email='$email',ean='$ean',";
		$qtxt.= "institution='$institution' where id='$konto_id'";
	} else {
		$qtxt = "update ordrer set ";
		$qtxt.= "firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',land='$land',";
		$qtxt.= "cvrnr='$cvrnr',betalingsbet='$betalingsbet',betalingsdage='$betalingsdage',email='$email',ean='$ean',";
		$qtxt.= "institution='$institution' where id='$ordre_id'";
	}
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

if ($ordre_id && $konto_id) {
	syncStamkort($ordre_id, $konto_id, $retning);
}
print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$ordre_id\">";

?>
</body></html>
