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
// Bjorn added a CREATE TABLE for timereg_sessions and timereg_breaks on 29/05/2024
// Bjorn added "loen" to the table of timereg_sessions on 19-06-2024



$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='grupper' and column_name = 'fiscal_year'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER table grupper ADD column fiscal_year int DEFAULT 0";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "select id from grupper where fiscal_year >= 1";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$i = 0;
	$qtxt = "select kodenr from grupper where art = 'RA' order by id";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$fiscalYear[$i] = (int) $r['kodenr'];
		$i++;
	}
	if (isset($fiscalYear[0])) { //added by loe 29/11/2023
		transaktion('begin');
		$qtxt = "UPDATE grupper set fiscal_year = '$fiscalYear[0]' ";
		$qtxt .= "WHERE (art = 'SM' OR art = 'KM' OR art = 'EM' OR art = 'YM' OR art = 'MR' OR art = 'DG' ";
		$qtxt .= "OR art = 'KG' OR art = 'KM' OR art = 'VG' OR art = 'POS' OR art = 'OreDif')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		for ($i = 1; $i < count($fiscalYear); $i++) {
			$qtxt = "SELECT * FROM grupper WHERE ";
			$qtxt .= "((art = 'SM' OR art = 'KM'  OR art = 'EM' OR art = 'YM' OR art = 'MR' OR art = 'DG' OR art = 'KG' ";
			$qtxt .= "OR art = 'KM' OR art = 'VG' OR art = 'POS' OR art = 'OreDif') and fiscal_year = '$fiscalYear[0]')";
			$qtxt .= "ORDER BY art,kodenr";
			$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$qtxt = "INSERT INTO grupper (beskrivelse,kode,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,";
				$qtxt .= "box10,box11,box12,box13,box14,fiscal_year) values (";
				$qtxt .= "'" . db_escape_string($r['beskrivelse']) . "','" . db_escape_string($r['kode']) . "',";
				$qtxt .= "'" . db_escape_string($r['kodenr']) . "','" . db_escape_string($r['art']) . "',";
				$qtxt .= "'" . db_escape_string($r['box1']) . "','" . db_escape_string($r['box2']) . "',";
				$qtxt .= "'" . db_escape_string($r['box3']) . "','" . db_escape_string($r['box4']) . "',";
				$qtxt .= "'" . db_escape_string($r['box5']) . "','" . db_escape_string($r['box6']) . "',";
				$qtxt .= "'" . db_escape_string($r['box7']) . "','" . db_escape_string($r['box8']) . "',";
				$qtxt .= "'" . db_escape_string($r['box9']) . "','" . db_escape_string($r['box10']) . "',";
				$qtxt .= "'" . db_escape_string($r['box11']) . "','" . db_escape_string($r['box12']) . "',";
				$qtxt .= "'" . db_escape_string($r['box13']) . "','" . db_escape_string($r['box14']) . "','$fiscalYear[$i]')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}
		transaktion('commit');
	}
}

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
	$qtxt = "Select id from tekster where tekst_id = '666' and tekst like '%town%'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("update tekster set tekst = '' where tekst_id = '666'", __FILE__ . " linje " . __LINE__);
	}
	$qtxt = "Select id from tekster where tekst_id = '2133' and tekst like 'calender'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
	}

	function ensureTableAndColumns($db, $tableName, $expectedColumns, $renameColumns = []) {
		// Check if table exists
		$qtxt = "SELECT table_name FROM information_schema.tables WHERE (table_schema = '$db' or table_catalog='$db') and table_name='$tableName'";
		$tableExists = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	
		if (!$tableExists) {
			// Create table with expected columns and their types
			$columnsTxt = implode(", ", array_map(function($col, $type) { return "$col $type"; }, array_keys($expectedColumns), $expectedColumns));
			$qtxt = "CREATE TABLE $tableName ($columnsTxt)";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			// Fetch all columns of the table
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') and table_name='$tableName'";
			$result = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			$columns = [];
			while ($row = db_fetch_array($result)) {
				$columns[] = $row['column_name'];
			}
	
			// Rename columns if specified
			foreach ($renameColumns as $oldName => $newName) {
				if (in_array($oldName, $columns) && !in_array($newName, $columns)) {
					$qtxt = "ALTER TABLE $tableName RENAME COLUMN \"$oldName\" TO \"$newName\"";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					// Update the columns array to reflect the change
					$columns[array_search($oldName, $columns)] = $newName;
				}
			}
	
			// Check if all expected columns exist, if not, add them with their types
			foreach ($expectedColumns as $column => $type) {
				if (!in_array($column, $columns)) {
					$qtxt = "ALTER TABLE $tableName ADD COLUMN $column $type";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
			}
		}
	
		return true;
	}
	// easyUBL

	$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='digital_status'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER table ordrer ADD column digital_status character varying(255)",__FILE__ . " linje " . __LINE__);
	}
	$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='notifications'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$qtxt = "CREATE TABLE notifications (id SERIAL PRIMARY KEY, msg varchar(255), read_status int)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}

	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='variant_varer' and ";
	$qtxt.= "column_name='variant_text'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER table variant_varer ADD column variant_text character varying(25)",__FILE__ . " linje " . __LINE__);
		$qtxt = "SELECT variant_varer.id as id, variant_typer.beskrivelse as beskrivelse from variant_varer,variant_typer ";
		$qtxt.= "where variant_varer.variant_type = variant_typer.id order by variant_varer.id";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$qtxt = "UPDATE variant_varer set variant_text = '". db_escape_string($r['beskrivelse']) ."' where id = '$r[id]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'item_name' => 'varchar (255)', 'product_id' => 'INTEGER'];
	ensureTableAndColumns($db, 'rentalitems', $expectedColumns);
	
	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'order_id' => 'INTEGER', 'rt_from' => 'numeric(15,0)', 'rt_to' => 'numeric(15,0)', 'item_id' => 'INTEGER', 'cust_id' => 'INTEGER', "expiry_time" => 'TIMESTAMP'];
	ensureTableAndColumns($db, 'rentalperiod', $expectedColumns);
	
	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'item_id' => 'INTEGER', "rr_from" => 'numeric(15,0)', "rr_to" => "numeric(15,0)", 'comment' => 'varchar (255)'];
	$renameColumns = [
		"from" => "rr_from",
		"to" => "rr_to"
	];
	ensureTableAndColumns($db, 'rentalreserved', $expectedColumns, $renameColumns);

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'day' => 'INTEGER'];
	ensureTableAndColumns($db, 'rentalclosed', $expectedColumns);
	
	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'booking_format' => 'INTEGER', 'search_cust_name' => 'INTEGER', 'search_cust_number' => 'INTEGER', 'search_cust_tlf' => 'INTEGER', 'start_day' => 'INTEGER', 'deletion' => 'INTEGER', 'find_weeks' => 'INTEGER', 'end_day' => 'INTEGER', 'put_together' => 'INTEGER', 'pass' => 'varchar(255)', 'use_password' => 'INTEGER', 'invoice_date' => 'INTEGER'];
	ensureTableAndColumns($db, 'rentalsettings', $expectedColumns);

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'host' => 'varchar(255)', 'username' => 'varchar(255)', 'password' => 'varchar(255)'];
	ensureTableAndColumns($db, 'rentalmail', $expectedColumns);

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'product_id' => 'INTEGER', 'descript' => 'text', 'is_active' => 'smallint', 'choose_periods' => 'smallint', 'max' => 'INTEGER'];
	ensureTableAndColumns($db, 'rentalremote', $expectedColumns);

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'rentalremote_id' => 'INTEGER', 'amount' => 'INTEGER'];
	ensureTableAndColumns($db, 'rentalremoteperiods', $expectedColumns);

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'payment_intent_id' => 'varchar(255)', 'amount' => 'INTEGER', 'betalings_link' => 'varchar(255)', 'kontonr' => 'INTEGER', 'created_at' => 'TIMESTAMP'];
	ensureTableAndColumns($db, 'betalingslink', $expectedColumns);

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'apikey' => 'varchar(255)'];
	ensureTableAndColumns($db, 'rentalpayment', $expectedColumns);
	
	/*
	$sql = "CREATE TABLE IF NOT EXISTS outgoing_invoices (
        id SERIAL PRIMARY KEY,
        invoice_id INT,
        status varchar(255)
    )";
	db_modify($sql,__FILE__ . " linje " . __LINE__);

	$sql = "CREATE TABLE IF NOT EXISTS incoming_invoices (
		id SERIAL PRIMARY KEY,
		document_name varchar(255)
	)";
	db_modify($sql,__FILE__ . " linje " . __LINE__);

	$sql = "CREATE TABLE IF NOT EXISTS rentalsettings (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    )";
	db_modify($sql,__FILE__ . " linje " . __LINE__);

	$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalsettings' and column_name='use_password'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER table rentalsettings ADD column use_password int",__FILE__ . " linje " . __LINE__);
	}

	$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalsettings' and column_name='pass'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER table rentalsettings ADD column pass varchar(255)",__FILE__ . " linje " . __LINE__);
	}
*/


$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='timereg_sessions'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
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
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='timereg_breaks'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE timereg_breaks (
		id SERIAL PRIMARY KEY NOT NULL,
		session_id integer NOT NULL,
		t_start timestamp NOT NULL,
		t_stop timestamp,
		length integer)";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='wolt_intergereted'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table varer ADD column wolt_intergereted bool default FALSE",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='notesinternal'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table varer ADD column notesinternal text",__FILE__ . " linje " . __LINE__);
}
$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='colli_webfragt'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table varer ADD column colli_webfragt float DEFAULT 0",__FILE__ . " linje " . __LINE__);
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

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='stockmovement'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE stockmovement (
		id SERIAL PRIMARY KEY NOT NULL,
		vareid integer NOT NULL,
		beholdning integer NOT NULL
	)";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='pos_buttons' and column_name='fontcolor' ";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
	$qtxt = "ALTER TABLE pos_buttons ADD COLUMN fontcolor character varying(6)";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='settings' and column_name='pos_id'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE settings ADD column pos_id int",__FILE__ . " linje " . __LINE__);
}

$qtxt = "update settings set pos_id = '0' where var_name = 'customerDisplay' and pos_id is NULL";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='volume_lager'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table varer ADD column volume_lager float default 1",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='gls_label'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table ordrer ADD column gls_label bool default false",__FILE__ . " linje " . __LINE__);
}
$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='fedex_label'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table ordrer ADD column fedex_label bool default false",__FILE__ . " linje " . __LINE__);
}
$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='lev_land'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table ordrer ADD column lev_land VARCHAR(25)",__FILE__ . " linje " . __LINE__);
}
$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='lev_email'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table ordrer ADD column lev_email VARCHAR(50)",__FILE__ . " linje " . __LINE__);
}

?>
