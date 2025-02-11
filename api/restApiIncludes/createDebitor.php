 
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

function CreateDebitor() {
	global $db;

	$log=fopen("../temp/$db/rest_api.log","a");
	fwrite($log,__line__." ". date("H:i:s") ." kontonr $kontonr\n");
	
	$addr1         = if_isset($_GET['addr1']);
	$addr2         = if_isset($_GET['addr2']);
	$afd           = if_isset($_GET['afd'])*1;
	$betalingsbet  = if_isset($_GET['betalingsbet']);
	$betalingsdage = if_isset($_GET['betalingsdage']);
	$bynavn        = if_isset($_GET['bynavn']);
	$cvr           = if_isset($_GET['cvr']);
	$firmanavn     = if_isset($_GET['firmanavn']);
	$efternavn     = if_isset($_GET['efternavn']);
	$fornavn       = if_isset($_GET['fornavn']);
	$gruppe        = if_isset($_GET['gruppe']);
	$kontakt       = if_isset($_GET['kontakt']);
	$kontonr       = if_isset($_GET['kontonr']);
	$kundetype     = if_isset($_GET['kundetype']);
	$land          = if_isset($_GET['land']);
	$lev_firmanavn = if_isset($_GET['lev_firmanavn']);
	$lev_addr1     = if_isset($_GET['lev_addr1']);
	$lev_addr2     = if_isset($_GET['lev_addr2']);
	$lev_postnr    = if_isset($_GET['lev_postnr']);
	$lev_bynavn    = if_isset($_GET['lev_bynavn']);
	$lev_land      = if_isset($_GET['lev_land']);
	$lev_tlf       = if_isset($_GET['lev_tlf']);
	$lev_email     = if_isset($_GET['lev_email']);
	$lev_kontakt   = if_isset($_GET['lev_kontakt']);
	$minNo         = if_isset($_GET['minNo']);
	$maxNo         = if_isset($_GET['maxNo']);
	$postnr        = if_isset($_GET['postnr']);
	$tlf           = if_isset($_GET['tlf']);
	
	if (!$kontonr) {
		include("restApiIncludes/getNextAccountNo.php");
		$kontonr = getNextAccountNo('D');
	} 
#	return ("$kontonr");
#	exit;
    $qtxt = "select id from adresser where kontonr='$kontonr' and art = 'D'";
    $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
    if ($id=$r['id']) {
        return ("Account $kontonr allready exists");
        exit;
    }

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
	$qtxt.="('$kontonr','".db_escape_string($firmanavn)."','".db_escape_string($addr1)."','".db_escape_string($addr2)."',";
	$qtxt.="'".db_escape_string($postnr)."','".db_escape_string($bynavn)."','".db_escape_string($land)."',";
	$qtxt.="'".db_escape_string($cvrnr)."','".db_escape_string($ean)."','".db_escape_string($email)."','".db_escape_string($tlf)."',";
	$qtxt.="'$gruppe','D','$betalingsbet','$betalingsdage','".db_escape_string($kontakt)."',";
	$qtxt.="'".db_escape_string($lev_firmanavn)."','".db_escape_string($lev_addr1)."','".db_escape_string($lev_addr2)."',";
	$qtxt.="'".db_escape_string($lev_postnr)."','".db_escape_string($lev_bynavn)."','".db_escape_string($lev_land)."',";
	$qtxt.="'".db_escape_string($lev_kontakt)."','".db_escape_string($lev_tlf)."','".db_escape_string($lev_email)."','')";
	fwrite($log,__line__." $qtxt\n");
	$qtxt=chk4utf8($qtxt);
    db_modify($qtxt,__FILE__ . " linje " . __LINE__);
   $qtxt = "select id from adresser where kontonr='$kontonr' and art = 'D'";
    $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	
	return ("$id,$kontonr");
}

?>
 
