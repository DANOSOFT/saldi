<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- patch 4.0.9--- 2024.03.30 --
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
// The content of this file must be moved to opdat_4.0 in section 4.1.0 when 4.1.0 is to be released.
	$chklst = $delete = array();
	$i=$x=0;
	$qtxt = "SELECT * FROM grupper WHERE ";
	$qtxt .= "art = 'SM' OR art = 'KM'  OR art = 'EM' OR art = 'YM' OR art = 'MR' OR art = 'DG' OR art = 'KG' ";
	$qtxt .= "OR art = 'KM' OR art = 'VG' OR art = 'POS' OR art = 'OreDif' order by id";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tmp = $r['art']."|".$r['kodenr']."|".$r['fiscal_year'];
		if (in_array($tmp,$chklst)) {
			$delete[$i] = $r['id'];
			$i++;
		} else {
			$chklst[$x] = $tmp;
			$x++;
		}
	}
	for ($i=0;$i<count($delete);$i++) {
		$qtxt = "delete from grupper where id = $delete[$i]";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	$qtxt = "update grupper set fiscal_year = 1 where ";
	$qtxt.= "(art = 'SM' OR art = 'KM'  OR art = 'EM' OR art = 'YM' OR art = 'MR' OR art = 'DG' OR art = 'KG' ";
	$qtxt.= "OR art = 'KM' OR art = 'VG' OR art = 'POS' OR art = 'OreDif') and fiscal_year is NULL";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);

	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='kassekladde' and column_name='saldo'";
	if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER table kassekladde ADD column saldo numeric(15,3)", __FILE__ . " linje " . __LINE__);
	}
	$qtxt = "Select id from tekster where tekst_id = '593' and (tekst = 'Lande' or tekst = 'Countries')";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("update tekster set tekst = '' where tekst_id = '593'", __FILE__ . " linje " . __LINE__);
	}

	$qtxt = "Select id from tekster where tekst_id = '534' and tekst like '%balance team'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("update tekster set tekst = '' where tekst_id = '534'", __FILE__ . " linje " . __LINE__);
	}

	$qtxt = "Select id from tekster where tekst_id = '636' and tekst like '%api klienten%'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("update tekster set tekst = '' where tekst_id = '636'", __FILE__ . " linje " . __LINE__);
	}
	$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='digital_status'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER table ordrer ADD column digital_status character varying(255)",__FILE__ . " linje " . __LINE__);
	}
	$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='notifications'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$qtxt = "CREATE TABLE notifications (id SERIAL PRIMARY KEY, msg varchar(255), read_status int)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}

	$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='kds_records'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$qtxt = "CREATE TABLE kds_records (
			id SERIAL PRIMARY KEY NOT NULL,
			data text NOT NULL,
			bumped boolean NOT NULL,
			timestamp integer NOT NULL,
			time_to_complete integer NOT NULL,
			rush boolean,
			last_undo boolean
			)";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}


?>
