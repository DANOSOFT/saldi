<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------------debitor/sync_stamkort.php--------lap 5.0.7-------2023.01.23--------------
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
// Copyright (c) 2008-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20230123 PHR Replaced addslashes by db_escape_string

@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$ordre_id=if_isset($_GET['ordre_id']);
$konto_id=if_isset($_GET['konto_id']);
$retning=if_isset($_GET['retning']);

if ($ordre_id && $konto_id) {
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
		$qtxt = "update adresser set ";
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
print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$ordre_id\">";

?>
</body></html>
