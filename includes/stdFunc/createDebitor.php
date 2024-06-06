<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//--- includes/stdFunc/createDebitor.php --- ver 4.0.8 --- 20230707 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2023 saldi.dk aps
// ----------------------------------------------------------------------
// This function finds the Vat account belonging to an account in ledger.
//
function create_debtor($kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$email,$tlf,$cvrnr,$grp,$ean,$betalingsbet,$betalingsdage,$kontakt) {
	if (!$kontonr) $kontonr=get_next_number('adresser','D');
	else {
		$qtxt="select id from adresser where kontonr='$kontonr' and art='D'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			alert("Kontonr $kontonr er ikke ledigt!");
			return(NULL);
			exit;
		}
	}
	if ($postnr && !$bynavn) $bynavn=bynavn($postnr); #20190423
	$betalingsdage = (int)$betalingsdage;

	$qtxt = "insert into adresser ";
	$qtxt.= "(kontonr,firmanavn,addr1,addr2,postnr,bynavn,email,tlf,cvrnr,ean,gruppe,kontakt,art,lukket,betalingsbet,betalingsdage)";
	$qtxt.=" values ";
	$qtxt.= "('".db_escape_string($kontonr)."','".db_escape_string($firmanavn)."','".db_escape_string($addr1)."',";
	$qtxt.= "'".db_escape_string($addr2)."','".db_escape_string($postnr)."','".db_escape_string($bynavn)."',";
	$qtxt.="'".db_escape_string($email)."','".db_escape_string($tlf)."','".db_escape_string($cvrnr)."','".db_escape_string($ean)."',";
	$qtxt.="'".db_escape_string($grp)."','".db_escape_string($kontakt)."','D','','$betalingsbet','$betalingsdage')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="select id from adresser where kontonr='".db_escape_string($kontonr)."' and art='D'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	return ($r['id']);

}
?>
