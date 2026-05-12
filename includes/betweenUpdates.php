<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- patch 5.0.0--- 2026.05.12
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// The content of this file must be moved to opdat_4.1 in section 4.1.1 when 4.1.1 is to be released.
// 20260429 LOE added leveret to formularer table
// 20260504 LOE added a separate table for managing delivery addresses and those in adresser are migrated to the new table and linked to the corresponding account.
// 20260504 NTR Fixed error on login due to missing regnskab's table
// 20260507 PHR Removed above as table regskab must not be created en sub bases
// 20260512 NTR Merged Live/POS into prod_test



$qtxt = "CREATE SEQUENCE IF NOT EXISTS regnskab_id_seq";
db_modify($qtxt, __FILE__ . " linje " . __LINE__);


$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'adresser' and  column_name = 'iban'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE adresser ADD iban varchar(40)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'adresser' and  column_name = 'swift'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE adresser ADD swift varchar(15)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT indexname FROM pg_indexes WHERE tablename = 'delivery_addresses' AND indexname = 'idx_delivery_addresses_account_id'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE INDEX idx_delivery_addresses_account_id ON delivery_addresses(account_id)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

//migrate all delivery addresses in adresser to delivery_addresses and link them to the corresponding account
// Only run migration once
$already_migrated = db_fetch_array(db_select(
    "SELECT var_value FROM settings WHERE var_name = 'delivery_addr_migrated' AND var_grp = 'system'",
    __FILE__ . " linje " . __LINE__
));
error_log("Delivery address migration already done: " . ($already_migrated ? 'yes' : 'no'));
if (!$already_migrated) {
    $qtxt = "SELECT id, lev_firmanavn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_land, lev_kontakt, lev_email 
             FROM adresser 
             WHERE (lev_firmanavn IS NOT NULL AND lev_firmanavn != '') 
                OR (lev_addr1 IS NOT NULL AND lev_addr1 != '')";

    if ($result = db_select($qtxt, __FILE__ . " linje " . __LINE__)) {
        while ($row = db_fetch_array($result)) {
            $account_id    = intval($row['id']);
            $company_name  = db_escape_string(trim($row['lev_firmanavn']));
            $address_line1 = db_escape_string(trim($row['lev_addr1']));
            $address_line2 = db_escape_string(trim($row['lev_addr2']));
            $postal_code   = db_escape_string(trim($row['lev_postnr']));
            $city          = db_escape_string(trim($row['lev_bynavn']));
            $country       = db_escape_string(trim($row['lev_land']));
            $contact_name  = db_escape_string(trim($row['lev_kontakt']));
            $email         = db_escape_string(trim($row['lev_email']));

            if (!$company_name && !($address_line1 || $address_line2)) continue;

            $qtxt_check = "SELECT id FROM delivery_addresses
                           WHERE account_id   = $account_id
                             AND company_name  = '$company_name'
                             AND address_line1 = '$address_line1'
                             AND postal_code   = '$postal_code'
                           LIMIT 1";

            if (!db_fetch_array(db_select($qtxt_check, __FILE__ . " linje " . __LINE__))) {
                db_modify("INSERT INTO delivery_addresses
                               (account_id, is_primary, description, company_name,
                                address_line1, address_line2, postal_code, city,
                                country, contact_name, email)
                           VALUES
                               ($account_id, TRUE, 'Primary Delivery Address', '$company_name',
                                '$address_line1', '$address_line2', '$postal_code', '$city',
                                '$country', '$contact_name', '$email')",
                    __FILE__ . " linje " . __LINE__
                );
            }
        }
    }

    // Mark migration as done so it never runs again
    db_modify(
        "INSERT INTO settings (var_name, var_grp, var_value, var_description)
         VALUES ('delivery_addr_migrated', 'system', 'yes', 'One-time migration of lev_* fields to delivery_addresses table')",
        __FILE__ . " linje " . __LINE__
    );
	error_log("Delivery address migration completed.");
}
############
error_log("continuation of other updates...................");
$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'batch_kob' and  column_name = 'due_date'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE batch_kob ADD due_date integer";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}


$qtxt = "update grupper set box8 = '' where art = 'DIV' and kodenr = '2' and box8 like 'ftp2.ebconnect.dk%'";
db_modify($qtxt, __FILE__ . " linje " . __LINE__);

$qtxt = "update varer set lukket = '0' where lukket is NULL";
db_modify($qtxt, __FILE__ . " linje " . __LINE__);


$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'batch_kob' and  column_name = 'batch_no'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE batch_kob ADD batch_no varchar(100)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'batch_kob' and  column_name = 'due_date'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	if ($r[0] != 'date') {
		$qtxt = "ALTER TABLE batch_kob DROP column due_date";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
}
$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'batch_kob' and  column_name = 'due_date'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE batch_kob ADD due_date date";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'settings' and  column_name = 'group_id'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE settings ADD group_id integer";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'variant_varer' and  column_name = 'variant_salgspris'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE variant_varer ADD variant_salgspris numeric(15,3)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'variant_varer' and  column_name = 'variant_text'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE variant_varer ADD variant_text varchar(25)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
/*
$qtxt = "SELECT id FROM formularer WHERE formular = 9 AND art = '3' AND beskrivelse = 'leveret' AND sprog = 'Dansk' LIMIT 1";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $qtxt = "INSERT INTO formularer (formular, art, beskrivelse, justering, xa, ya, xb, yb, str, color, font, fed, kursiv, side, sprog)
             VALUES (9, '3', 'leveret', 'V', 163.000, 0.000, 0.000, 0.000, 9.000, 0, 'Helvetica', '', '', '0', 'Dansk')";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
*/
$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'kontoplan' and  column_name = 'map_to'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE kontoplan ADD column map_to numeric(15)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'tutorials'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE tutorials (id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL, tutorial_id varchar(10), selector TEXT)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}


$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'batch_kob' and  column_name = 'batch_no'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE batch_kob ADD batch_no varchar(100)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'settings' and  column_name = 'digital_status'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE settings ADD digital_status varchar(25)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'ordrer' and  column_name = 'digital_status'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE ordrer ADD digital_status varchar(25)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'settings' and  column_name = 'group_id'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE settings ADD group_id integer";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'variant_varer' and  column_name = 'variant_salgspris'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE variant_varer ADD variant_salgspris numeric(15,3)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'variant_varer' and  column_name = 'variant_text'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE variant_varer ADD variant_text varchar(25)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'kontoplan' and  column_name = 'map_to'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE kontoplan ADD column map_to numeric(15)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'tutorials'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE tutorials (id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL, tutorial_id varchar(10), selector TEXT)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

# Enables docs (bilag)
$qtxt = "update grupper set box6 = 'on' where art = 'bilag'";
db_modify($qtxt, __FILE__ . " linje " . __LINE__);

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'adresser' and column_name = 'kontonr' limit 1";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if ($r[0] == 'numeric') {
	$qtxt = "ALTER TABLE adresser ALTER column kontonr TYPE varchar(30)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'pbs_kunder' and column_name = 'kontonr' limit 1";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if ($r[0] == 'numeric' || $r[0] == 'integer') {
	$qtxt = "ALTER TABLE pbs_kunder ALTER column kontonr TYPE varchar(30)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT * FROM information_schema.columns WHERE table_name = 'ordrer' and column_name = 'kontonr' limit 1";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if ($r['data_type'] == 'numeric') {
	$qtxt = "ALTER TABLE ordrer ALTER column kontonr TYPE varchar(30)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "update grupper set box2 = '' where art = 'USET'";
db_modify($qtxt, __FILE__ . " linje " . __LINE__);

// Change the column type to VARCHAR(20)

$qtxt = "select id, ordredate from ordrer where art = ''";
#cho "$qtxt<br>";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if ($r['orderdate'] >= '2026-01-01') {
		$qtxt = "update orders set art = 'KO' where id = '$r[id]'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
}
$qtxt = "select id from settings where var_grp = 'colors' and var_value = '#' limit 1";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "delete from settings where var_grp = 'colors'";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'datatables' limit 1";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'datatables' and column_name = 'tabel_id'";
	$r=db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (strtolower($r['data_type']) != 'text') {
		$qtxt = "ALTER TABLE datatables ALTER COLUMN tabel_id TYPE TEXT";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'datatables' and column_name = 'date_range_meta'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$qtxt = "ALTER TABLE datatables ADD COLUMN date_range_meta TEXT";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'datatables' and column_name = 'rowcount'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$qtxt = "ALTER TABLE datatables ADD COLUMN rowcount INT";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'datatables' and column_name = 'offset'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$qtxt = "ALTER TABLE datatables ADD COLUMN \"offset\" INT";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'datatables' and column_name = 'sort'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$qtxt = "ALTER TABLE datatables ADD COLUMN sort TEXT";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
} else {
	$qtxt = "CREATE TABLE datatables (id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL, tabel_id TEXT, column_setup TEXT, search_setup TEXT, ";
	$qtxt.= "filter_setup TEXT, rowcount INTEGER, \"offset\" INTEGER, sort TEXT, date_range_meta TEXT)";
	db_modify($qtxt, __FILE__ . " line " . __LINE__);
}
db_modify("ALTER TABLE brugere ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL", __FILE__ . " linje " . __LINE__);
#db_modify("ALTER TABLE brugere ADD COLUMN IF NOT EXISTS twofactor TINYINT(1) DEFAULT 0", __FILE__ . " linje " . __LINE__);
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'brugere' and column_name = 'twofactor'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE brugere ADD COLUMN twofactor bool";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'brugere' and column_name = 'email'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE brugere ADD COLUMN email text";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

// Check if the column already exists
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'brugere' AND column_name = 'ip_address'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE brugere ADD COLUMN ip_address VARCHAR(45)";   
  db_modify($qtxt, __FILE__ . " linje " . __LINE__);
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

// havemøbelshoppen 
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='varenr_alias'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table varer ADD column varenr_alias VARCHAR(255)", __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='beskrivelse_alias'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table varer ADD column beskrivelse_alias VARCHAR(255)", __FILE__ . " linje " . __LINE__);
}
//////////////////////


//...... pos functionality to kassekladde table..........
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

$qtxt = "select id from settings where var_name = 'flatpay_auth'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "select id from settings where var_name = 'flatpay_print'";
	if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		($db == 'pos_10' || $db == 'pos_62') ? $flatpay_print = 1: $flatpay_print = 0;
		$qtxt = "INSERT INTO settings(var_name, var_grp, var_value, var_description) VALUES ";
		($db == 'pos_10' || $db == 'pos_62') ? $qtxt.= "'1', ":
		$qtxt.= "('flatpay_print', 'POS', '$flatpay_print', 'If 1, Saldi will print the terminal receipt else it is printed by the termanal')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
}
$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '2401' and tekst like 'Varen t%'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("update tekster set tekst = '' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
}

$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '1001' and tekst = 'Kreditér'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("update tekster set tekst = '' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
}
$qtxt = "Select id from tekster where sprog_id = '3' and tekst_id = '1001' and tekst = 'Krediter'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("update tekster set tekst = '' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
}

$qtxt = "Select id from tekster where tekst_id = '342' and tekst like 'balance team'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("update tekster set tekst = '' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
}



// easyUBL
/*
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='timereg_sessions'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE timereg_sessions (
		id SERIAL PRIMARY KEY NOT NULL,
		user_id integer NOT NULL,
		status varchar(15) NOT NULL,
		planned_start timestamp,
		planned_stop timestamp,
		actual_start timestamp NOT NULL,
		actual_stop timestamp,
		length integer,
		comment_start varchar(400),
		comment_stop varchar(400),
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
$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '635' and tekst = 'Saldi url:'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
}
$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '1001' and tekst = 'Kredit'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
}
db_modify("update grupper set box10 = 'B' where box10 = 'on' and art = 'DIV' and kodenr = '2'", __FILE__ . " linje " . __LINE__);

$qtxt = "SELECT character_maximum_length FROM information_schema.columns WHERE table_name='ordrer' and column_name='phone'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if ($r && $r['character_maximum_length'] < 50) {
	db_modify("ALTER TABLE ordrer ALTER COLUMN phone TYPE VARCHAR(50)", __FILE__ . " linje " . __LINE__);
}

// Ensure pool_files table exists with all columns
$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'pool_files'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE pool_files (
		id serial NOT NULL,
		filename varchar(255) NOT NULL,
		subject text,
		account varchar(50),
		amount varchar(50),
		file_date varchar(50),
		invoice_number varchar(100),
		description text,
		currency varchar(10),
		updated timestamp DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE(filename)
	)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
} else {
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='pool_files' and column_name='invoice_number'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER TABLE pool_files ADD COLUMN invoice_number varchar(100)", __FILE__ . " linje " . __LINE__);
	}
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='pool_files' and column_name='description'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER TABLE pool_files ADD COLUMN description text", __FILE__ . " linje " . __LINE__);
	}
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='pool_files' and column_name='currency'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER TABLE pool_files ADD COLUMN currency varchar(10)", __FILE__ . " linje " . __LINE__);
	}
}
// Because of an earlier error, table pool_files may be created without autoincrement, This fix that.
$qtxt = "SELECT column_default, identity_generation, pg_get_serial_sequence('pool_files', 'id') AS seq ";
$qtxt.= "FROM information_schema.columns WHERE table_name = 'pool_files' AND column_name = 'id'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if (!$r['seq']) {
	$qtxt = "CREATE SEQUENCE IF NOT EXISTS pool_files_id_seq";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "UPDATE pool_files SET id = nextval('pool_files_id_seq') WHERE id IS NULL";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "SELECT setval('pool_files_id_seq', COALESCE((SELECT MAX(id) FROM pool_files), 1), true)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "ALTER TABLE pool_files ALTER COLUMN id SET NOT NULL";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "ALTER TABLE pool_files ALTER COLUMN id SET DEFAULT nextval('pool_files_id_seq')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

// Create kontakt_emails table for multiple emails per customer
$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_name='kontakt_emails'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE IF NOT EXISTS kontakt_emails (
		id SERIAL PRIMARY KEY,
		konto_id INTEGER NOT NULL,
		email VARCHAR(255) NOT NULL,
		email_type VARCHAR(50) DEFAULT ''
	)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);

	// Migrate existing emails from adresser to kontakt_emails
	$qtxt = "SELECT id, email FROM adresser WHERE art = 'D' AND email IS NOT NULL AND email != ''";
	$q_migrate = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r_migrate = db_fetch_array($q_migrate)) {
		$mig_email = db_escape_string(trim($r_migrate['email']));
		$mig_id = $r_migrate['id'];
		if ($mig_email) {
			db_modify("INSERT INTO kontakt_emails (konto_id, email, email_type) VALUES ('$mig_id', '$mig_email', 'hoved')", __FILE__ . " linje " . __LINE__);
		}
	}
}

// MobilePay: ensure webhook is registered for the current server
$q = db_select("SELECT var_value FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'client_id'", __FILE__ . " linje " . __LINE__);
$mp_client_id = db_fetch_array($q)['var_value'] ?? null;
if ($mp_client_id) {
	$q = db_select("SELECT var_value FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'client_secret'", __FILE__ . " linje " . __LINE__);
	$mp_client_secret = db_fetch_array($q)['var_value'];
	$q = db_select("SELECT var_value FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'subscriptionKey'", __FILE__ . " linje " . __LINE__);
	$mp_subscription = db_fetch_array($q)['var_value'];
	$q = db_select("SELECT var_value FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'MSN'", __FILE__ . " linje " . __LINE__);
	$mp_msn = db_fetch_array($q)['var_value'];

	$expected_url = 'https://' . $_SERVER['SERVER_NAME'] . '/pos/debitor/payments/mobilepay/webhook_recive.php?db=' . $db;

	// Get access token
	$ch = curl_init('https://api.vipps.no/accesstoken/get');
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		"Client_id: $mp_client_id",
		"Client_secret: $mp_client_secret",
		"Ocp-Apim-Subscription-Key: $mp_subscription",
		"Merchant-Serial-Number: $mp_msn",
		'Content-Length: 0'
	]);
	$token_resp = json_decode(curl_exec($ch), true);
	curl_close($ch);
	$mp_token = $token_resp['access_token'] ?? null;

	if ($mp_token) {
		$mp_headers = [
			"Authorization: Bearer $mp_token",
			"Ocp-Apim-Subscription-Key: $mp_subscription",
			"Merchant-Serial-Number: $mp_msn",
			'Content-Type: application/json'
		];

		// List registered webhooks
		$ch = curl_init('https://api.vipps.no/webhooks/v1/webhooks');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $mp_headers);
		$webhooks = json_decode(curl_exec($ch), true)['webhooks'] ?? [];
		curl_close($ch);

		$correct_webhook_exists = false;
		foreach ($webhooks as $wh) {
			if ($wh['url'] === $expected_url) {
				$correct_webhook_exists = true;
			} else {
				// Delete webhook pointing to a different URL for this db
				if (strpos($wh['url'], 'webhook_recive.php?db=' . $db) !== false) {
					$ch = curl_init('https://api.vipps.no/webhooks/v1/webhooks/' . $wh['id']);
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $mp_headers);
					curl_exec($ch);
					curl_close($ch);
				}
			}
		}

		if (!$correct_webhook_exists) {
			// Register webhook with correct URL
			$ch = curl_init('https://api.vipps.no/webhooks/v1/webhooks');
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $mp_headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
				'url' => $expected_url,
				'events' => ['epayments.payment.authorized.v1', 'user.checked-in.v1', 'epayments.payment.cancelled.v1', 'epayments.payment.aborted.v1', 'epayments.payment.expired.v1', 'epayments.payment.terminated.v1']
			]));
			$reg_resp = json_decode(curl_exec($ch), true);
			curl_close($ch);

			if (!empty($reg_resp['secret'])) {
				db_modify("DELETE FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'webhook_secret'", __FILE__ . " linje " . __LINE__);
				$new_secret = db_escape_string($reg_resp['secret']);
				db_modify("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('webhook_secret', 'mobilepay', '$new_secret', 'The secret that is generated for the webhook')", __FILE__ . " linje " . __LINE__);
			}
		}
	}
}
?>
