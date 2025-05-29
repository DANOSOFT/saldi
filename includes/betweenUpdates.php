<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- patch 4.0.9--- 2025.03.22
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// The content of this file must be moved to opdat_4.1 in section 4.1.1 when 4.1.1 is to be released.


#db_modify("ALTER TABLE brugere ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL", __FILE__ . " linje " . __LINE__);



// Check if the column already exists
$qtxt = "SELECT column_name 
         FROM information_schema.columns 
         WHERE table_name = 'brugere' AND column_name = 'ip_address'";

if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    // Column doesn't exist, so alter table
    if ($db_type == 'mysql' || $db_type == 'mysqli') {
        $alter = "ALTER TABLE brugere ADD COLUMN ip_address VARCHAR(45) NULL";   
    } else {
     $alter = "ALTER TABLE brugere ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45)";
	}
    if (!empty($alter)) {
        db_modify($alter, __FILE__ . " linje " . __LINE__);
    }
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


//...... pos functionality to kassekladde table..........
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='kassekladde' and column_name='pos'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	if ($db_type == 'mysql' || $db_type == 'mysqli') {
		db_modify("ALTER TABLE kassekladde ADD COLUMN pos INT DEFAULT 0", __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("ALTER TABLE kassekladde ADD COLUMN pos INTEGER DEFAULT 0", __FILE__ . " linje " . __LINE__);
	}
	
	$qtxt = "UPDATE kassekladde k1 
			 SET pos = (
				 SELECT COUNT(*) 
				 FROM kassekladde k2 
				 WHERE k2.kladde_id = k1.kladde_id 
				 AND k2.bilag = k1.bilag 
				 AND k2.transdate = k1.transdate 
				 AND k2.id <= k1.id
			 )";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='tmpkassekl' and column_name='pos'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE tmpkassekl ADD COLUMN pos INTEGER DEFAULT 0", __FILE__ . " linje " . __LINE__);

}

// easyUBL
/*
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='timereg_sessions'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE timereg_sessions (
		id SERIAL PRIMARY KEY NOT NULL,
		user_id integer NOT NULL,
		status character varying(15) NOT NULL,
		planned_start timestamp,
		planned_stop timestamp,
		actual_start timestamp NOT NULL,
		actual_stop timestamp,
		length integer,
		comment_start character varying(400),
		comment_stop character varying(400),
		godkendt boolean,
		loen numeric
		)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='timereg_breaks'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE timereg_breaks (
		id SERIAL PRIMARY KEY NOT NULL,
		session_id integer NOT NULL,
		t_start timestamp NOT NULL,
		t_stop timestamp,
		length integer)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
*/
?>
