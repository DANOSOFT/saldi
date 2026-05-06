<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_4.2.php --- patch 4.2.0 --- 2026-04-16 ---
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
// Expiry date handling (FEFO) - batch_kob: due_date, batch_no
//                                varer: has_due_date, default_shelf_life_days

if (!function_exists('opdat_4_2')) {
function opdat_4_2($majorNo, $subNo, $fixNo){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();

	$nextver='4.2.4';
	if ($fixNo<"4"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt = "UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			// --- batch_kob: add due_date column ---
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='batch_kob' AND column_name='due_date'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE batch_kob ADD COLUMN due_date DATE NULL", __FILE__ . " linje " . __LINE__);
			}

			// --- batch_kob: add batch_no column ---
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='batch_kob' AND column_name='batch_no'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE batch_kob ADD COLUMN batch_no VARCHAR(100) NULL", __FILE__ . " linje " . __LINE__);
			}

			// --- batch_kob: indexes for FEFO sorting ---
			$qtxt = "SELECT indexname FROM pg_indexes WHERE tablename='batch_kob' AND indexname='idx_batch_kob_due_date'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("CREATE INDEX idx_batch_kob_due_date ON batch_kob(due_date)", __FILE__ . " linje " . __LINE__);
			}
			$qtxt = "SELECT indexname FROM pg_indexes WHERE tablename='batch_kob' AND indexname='idx_batch_kob_vare_due'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("CREATE INDEX idx_batch_kob_vare_due ON batch_kob(vare_id, due_date)", __FILE__ . " linje " . __LINE__);
			}

			// --- varer: add has_due_date column ---
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' AND column_name='has_due_date'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE varer ADD COLUMN has_due_date BOOLEAN DEFAULT FALSE", __FILE__ . " linje " . __LINE__);
			}

			// --- varer: add default_shelf_life_days column ---
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' AND column_name='default_shelf_life_days'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE varer ADD COLUMN default_shelf_life_days INTEGER NULL", __FILE__ . " linje " . __LINE__);
			}

			// --- ordrelinjer: add batch_due_date for passing through goods receipt ---
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrelinjer' AND column_name='batch_due_date'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE ordrelinjer ADD COLUMN batch_due_date DATE NULL", __FILE__ . " linje " . __LINE__);
			}

			// --- ordrelinjer: add batch_batch_no for passing through goods receipt ---
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrelinjer' AND column_name='batch_batch_no'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE ordrelinjer ADD COLUMN batch_batch_no VARCHAR(100) NULL", __FILE__ . " linje " . __LINE__);
			}

			// --- Update version ---
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}}
?>
