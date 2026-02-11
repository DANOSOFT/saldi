<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_4.1.php --- patch 4.1.0 --- 2026-02-10 ---
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
// Copyright (c) 2022-2026 Saldi.dk ApS
// ----------------------------------------------------------------------

if (!function_exists('opdat_4_1')) {
function opdat_4_1($majorNo, $subNo, $fixNo){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();

	$nextver='4.1.1';
	if ($fixNo<"1"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='brugere' ";
			$qtxt .= "and column_name='email'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE brugere ADD column email text", __FILE__ . " linje " . __LINE__);
			}

			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt = "UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			$qtxt = "ALTER TABLE datatables ALTER COLUMN tabel_id TYPE VARCHAR(20)";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);

			$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='brugere' ";
			$qtxt .= "and column_name='ip_address'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE brugere ADD column ip_address text", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='lev_email'";
			if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER table ordrer ADD column lev_email VARCHAR(60)", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='lev_land'";
			if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER table ordrer ADD column lev_land VARCHAR(60)", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='wolt_intergereted'";
			if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER table varer ADD column wolt_intergereted bool default FALSE", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='notesinternal'";
			if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER table varer ADD column notesinternal text", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='colli_webfragt'";
			if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER table varer ADD column colli_webfragt float DEFAULT 0", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='varenr_alias'";
			if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER table varer ADD column varenr_alias VARCHAR(255)", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='beskrivelse_alias'";
			if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER table varer ADD column beskrivelse_alias VARCHAR(255)", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='kassekladde' and column_name='pos'";
			if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE kassekladde ADD COLUMN pos INTEGER DEFAULT 0", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='tmpkassekl' and column_name='pos'";
			if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE tmpkassekl ADD COLUMN pos INTEGER DEFAULT 0", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='brugere' and column_name='tlf'";
			if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE brugere ADD COLUMN tlf VARCHAR(16) NULL", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '2401' and tekst like 'Varen t%'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("update tekster set tekst = '' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			}
			$qtxt = "Select id from tekster where tekst_id = '342' and tekst like 'balance team'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("update tekster set tekst = '' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			}
			$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '635' and tekst = 'Saldi url:'";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '1001' and tekst = 'Kredit'";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
			}
			db_modify("update grupper set box10 = 'B' where box10 = 'on' and art = 'DIV' and kodenr = '2'", __FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}}
?>
