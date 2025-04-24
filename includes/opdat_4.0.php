<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------ includes/opdat_4.0.php-----patch 4.0.8 ----2023-07-27--------------
//                           LICENSE
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20210828 LOE Added two tables for IP log
// 20210915 LOE Added sprog to regnskab table
// 20211009 PHR Some cleanup. + added language_id to online and scan_id to ordrer
// 20221004 MLH Added coloumns on_price_list, tier_price_multiplier, salgspris_multiplier, tier_price_method, tier_price_rounding, salgspris_method, salgspris_rounding to the products table
// 20240215 LOE Minor modification
if (!function_exists('opdat_4_0')) {
function opdat_4_0($majorNo, $subNo, $fixNo){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();

	$nextver='4.0.1';
	if ($fixNo<"1"){
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
			$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='rentalitems'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
				$qtxt ="CREATE TABLE rentalitems (id serial NOT NULL,rt_item_id int,item_id int, qty numeric(15,0), unit varchar(1), ";
				$qtxt.="item_name varchar(255), product_id int, PRIMARY KEY (id) )";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='variant_varer' and column_name='variant_id'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE variant_varer ADD column variant_id int",__FILE__ . " linje " . __LINE__);
				db_modify("UPDATE variant_varer set variant_id='0' where variant_id is NULL",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='variant_varer' and column_name='variant_kostpris'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE variant_varer ADD column variant_kostpris numeric(15,3)",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='variant_varer' and column_name='variant_salgspris'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE variant_varer ADD column variant_salgspris numeric(15,3)",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='variant_varer' and column_name='variant_vejlpris'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE variant_varer ADD column variant_vejlpris numeric(15,3)",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='shop_status'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER table ordrer ADD column shop_status int",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='shop_id'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER table ordrer ADD column shop_id int",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='ordrelinjer' and column_name='rental_id'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER table ordrelinjer ADD column rental_id int",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='4.0.2';
	if ($fixNo<"2"){
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
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='ordrelinjer' and column_name='discounttxt'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER table ordrelinjer ADD column discounttxt varchar(25)",__FILE__ . " linje " . __LINE__);
				$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='ordrelinjer' and column_name='comment'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER table ordrelinjer ADD column comment varchar(25)",__FILE__ . " linje " . __LINE__);
				$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			include("../includes/connect.php");
			$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	$nextver='4.0.3'; #20210828
	if ($fixNo<"3"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='settings' and column_name='var_grp'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE settings ADD column var_grp varchar(20)",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='settings' and column_name='user_id'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE settings ADD column user_id int",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='online' and column_name='language'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE online ADD column language_id int",__FILE__ . " linje " . __LINE__);
				$languages = "NULL".chr(9)."Dansk".chr(9)."English".chr(9)."Norsk";
				$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
				$qtxt.= "('languages','globals','$languages','Avalilable languages','0')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
				$qtxt.= "('languageId','globals','0','Active systemlanguage','0')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$qtxt = "UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='scan_id'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
				db_modify("ALTER TABLE ordrer ADD column scan_id numeric(15,0)",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='brugere' and column_name='language_id'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
				db_modify("ALTER TABLE brugere ADD column language_id int",__FILE__ . " linje " . __LINE__);
			}
			db_modify("DELETE FROM tekster WHERE tekst_id >= '800'",__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			include("../includes/connect.php");
			$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	$nextver='4.0.4'; #20210828
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
			$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='paperflow'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
				$qtxt ="CREATE TABLE paperflow (id serial NOT NULL,voucher_id int,upload_user_id int, upload_time varchar(10), ";
				$qtxt.="document varchar(40), insertion_user_id int, insertion_time varchar(10), lines int, ";
				$qtxt.="PRIMARY KEY (id))";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='due_date'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE ordrer ADD column due_date date",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='kontoplan' and column_name='system_account'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE kontoplan ADD column system_account boolean",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='kontoplan' and column_name='account_group'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE kontoplan ADD column account_group integer",__FILE__ . " linje " . __LINE__);
			}
			db_modify("DELETE FROM tekster WHERE tekst_id = '1465'",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE brugere set language_id='1'",__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			include("../includes/connect.php");
			$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	$nextver='4.0.5'; #20210828
	if ($fixNo<"5") {
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='regnskab' and column_name='global_id'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE regnskab ADD column global_id int default 0",__FILE__ . " linje " . __LINE__);
			}
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt = "UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='settings' and column_name='var_grp'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE settings ADD column var_grp varchar(20)",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='settings' and column_name='user_id'";
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE settings ADD column user_id int",__FILE__ . " linje " . __LINE__);
			}
			if (!function_exists('findtekst')) include("../includes/std_func.php");
			db_modify("DELETE FROM tekster WHERE tekst_id = '437'",__FILE__ . " linje " . __LINE__);
			if (strpos(findtekst(768,$sprog_id),"'")) { #remove after rel 4.05
				$qtxt="delete from tekster where tekst_id = '767' or tekst_id = '768'";
				db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
			}
			if (findtekst(644,1) == 'Advar ved for lav lagerbeholdning') {
				db_modify ("update tekster set tekst = '' where tekst_id = '644'",__FILE__ . " linje " . __LINE__); 
			}
			if (findtekst(390,$sprog_id)=="For at oprette en ny kategori skrives navnet p&aring; kategorien her") {
				db_modify("delete from tekster where tekst_id = '390'",__FILE__ . " linje " . __LINE__);
			}
			if (findtekst(644,1) == 'Advar ved for lav lagerbeholdning') {
				db_modify ("update tekster set tekst = '' where tekst_id = '644'",__FILE__ . " linje " . __LINE__); 
			}
			if (findtekst(671, 3) == 'blindtarm') db_modify("update tekster set tekst = '' where tekst_id = '671'",__FILE__ . " linje " . __LINE__);
			if (findtekst(795,1) == 'Aktivér Paperflow') db_modify ("update tekster set tekst = '' where tekst_id = '795'",__FILE__ . " linje " . __LINE__);
			if (findtekst(819,$sprog_id)==findtekst(689,$sprog_id)) {
				db_modify("delete from tekster where tekst_id >= '819' or tekst_id <= '825'",__FILE__ . " linje " . __LINE__);
			}
			if (findtekst(937, 1) == 'Retur') db_modify("update tekster set tekst = '' where tekst_id = '937'",__FILE__ . " linje " . __LINE__);
			if (findtekst(1001, 1) == 'Kredit') db_modify("update tekster set tekst = '' where tekst_id = '1001'",__FILE__ . " linje " . __LINE__);
			if (findtekst(1493, 1) == 'Kopi') db_modify("update tekster set tekst = '' where tekst_id = '1493'",__FILE__ . " linje " . __LINE__);

			$qtxt = "select id, var_value from settings where var_name = 'languages' order by id limit 1";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				db_modify("update settings set var_value = '$tmp' where var_name = 'languages'",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			include("../includes/connect.php");
			$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		$nextver='4.0.6'; #20210828
		if ($fixNo<"6") {
			include("../includes/connect.php");
			$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
			$tmp=$r['version'];
			if ($tmp<$nextver) {
				echo "opdaterer hovedregnskab til ver $nextver<br />";
				$qtxt = "SELECT character_maximum_length FROM information_schema.columns WHERE table_name='regnskab' and column_name='email'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($r['character_maximum_length'] < 50) {
					$qtxt = "ALTER TABLE regnskab ALTER column email TYPE varchar(60)";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt = "UPDATE regnskab set version = '$nextver' where id = '1'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			include("../includes/online.php");
			include("../includes/std_func.php");
			if ($db!=$sqdb)	{
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='rental'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "CREATE TABLE rental (id serial NOT NULL,rt_item_id int, rt_name varchar(40), PRIMARY KEY (id))";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='rentalitems'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt ="CREATE TABLE rentalitems (id serial NOT NULL,rt_item_id int,item_id int, qty numeric(15,0),  ";
					$qtxt.="unit varchar(1), item_name varchar(255), product_id int, PRIMARY KEY (id) )";
					$qtxt.="PRIMARY KEY (id) )";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='rental' and column_name='rt_no'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE rental ADD rt_no int";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='documents'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "CREATE TABLE documents (id serial NOT NULL,global_id int,filename text, filepath text, source varchar(20), ";
					$qtxt.= "source_id int, timestamp varchar(10), user_id int, PRIMARY KEY (id) )";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='varetilbud'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "CREATE TABLE varetilbud ";
					$qtxt.= "(id serial NOT NULL,vare_id integer,startdag numeric(15,0),slutdag numeric(15,0),starttid time,sluttid time,";
					$qtxt.= "ugedag integer,salgspris numeric(15,2),kostpris numeric(15,2),PRIMARY KEY (id))";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='adresser' and column_name = 'productlimit'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE adresser ADD COLUMN productlimit numeric(15,0)";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				db_modify ("update tekster set tekst = '' where tekst_id = '3'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '989'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1001'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1064'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1065'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1066'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1078'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1090'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1356'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1412'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1416'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1419'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1421'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1490'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1523'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1524'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1525'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1526'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1527'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1544'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1545'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1597'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1598'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1903'",__FILE__ . " linje " . __LINE__); 
				db_modify ("update tekster set tekst = '' where tekst_id = '1904'",__FILE__ . " linje " . __LINE__); 

				transaktion('begin');
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='mylabel' and ";
				$qtxt.= "column_name = 'sold' and data_type = 'boolean'";
				if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE mylabel RENAME COLUMN sold to oldsold";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "ALTER TABLE mylabel ADD COLUMN sold integer default 0";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "UPDATE mylabel SET sold = 1 where oldsold is TRUE";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "ALTER TABLE mylabel DROP COLUMN oldsold";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}	
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='mylabel' and ";
				$qtxt.= "column_name = 'created' and data_type = 'boolean'";
				if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE mylabel ADD COLUMN created varchar(15)";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "UPDATE mylabel SET created = lastprint";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "UPDATE mylabel SET created = '0' where created is NULL";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrelinjer' and ";
				$qtxt.= "column_name = 'barcode'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE ordrelinjer ADD COLUMN barcode varchar(20)";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				transaktion('commit');
				include("../includes/connect.php");
				$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		$nextver='4.0.7'; #20221004
		if ($fixNo<"7") {
			include("../includes/connect.php");
			$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
			$tmp=$r['version'];
			if ($tmp<$nextver) {
				echo "opdaterer hovedregnskab til ver $nextver<br />";
				$qtxt = "UPDATE regnskab set version = '$nextver' where id = '1'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			include("../includes/online.php");
			include("../includes/std_func.php");
			if ($db!=$sqdb)	{
				transaktion('begin'); 
				// tier_price
				$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name = 'tier_price_multiplier'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE varer ADD COLUMN tier_price_multiplier numeric(15,2) default 0";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name = 'tier_price_method'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE varer ADD COLUMN tier_price_method varchar(15) default 'percentage'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name = 'tier_price_rounding'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE varer ADD COLUMN tier_price_rounding varchar(15) default 'no_rounding'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				// salgspris
				$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name = 'salgspris_multiplier'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE varer ADD COLUMN salgspris_multiplier numeric(15,2) default 0";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name = 'salgspris_method'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE varer ADD COLUMN salgspris_method varchar(15) default 'percentage'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name = 'salgspris_rounding'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE varer ADD COLUMN salgspris_rounding varchar(15) default 'no_rounding'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				// retail_price
				$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name = 'retail_price_multiplier'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE varer ADD COLUMN retail_price_multiplier numeric(15,2) default 0";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name = 'retail_price_method'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE varer ADD COLUMN retail_price_method varchar(15) default 'percentage'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name = 'retail_price_rounding'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE varer ADD COLUMN retail_price_rounding varchar(15) default 'no_rounding'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				// on_price_list
				$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name = 'on_price_list'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE varer ADD COLUMN on_price_list integer default 1";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				transaktion('commit'); 
				include("../includes/connect.php");
				$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		$nextver='4.0.8'; #20221004
		if ($fixNo<"8") {
			include("../includes/connect.php");
			$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
			$tmp=$r['version'];
			if ($tmp<$nextver) {
				echo "opdaterer hovedregnskab til ver $nextver<br />";
				$qtxt = "UPDATE regnskab set version = '$nextver' where id = '1'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			include("../includes/online.php");
			include("../includes/std_func.php");
			if ($db!=$sqdb)	{
				transaktion('begin');
				$variantId = array();
				$qtxt = "SELECT data_type FROM information_schema.columns ";
				$qtxt.= "WHERE table_name='variant_varer' and column_name = 'variant_type' and data_type != 'integer'";
				if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$x=0;
					$qtxt = "select id, variant_type from variant_varer order by id";  
					$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$variantId[$x]   = $r['id'];
						$variantType[$x] = (int)trim($r['variant_type']);
						$x++;
					}
					$qtxt = "ALTER TABLE variant_varer RENAME COLUMN variant_type TO var_type_delete";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "ALTER TABLE variant_varer ADD COLUMN variant_type integer";

					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					for ($x=0;$x<count($variantId);$x++) {
						$qtxt = "update variant_varer set variant_type = '$variantType[$x]' where id = '$variantId[$x]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
				$vareId=array();
				$qtxt = "SELECT data_type FROM information_schema.columns ";
				$qtxt.= "WHERE table_name='varer' and column_name = 'gruppe' and data_type != 'integer'";
				if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$x=0;
					$qtxt = "select id, gruppe from varer order by id";
					$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$vareId[$x]   = $r['id'];
						$vareGruppe[$x] = (int)$r['gruppe'];
						$x++;
					}
					$qtxt = "ALTER TABLE varer RENAME COLUMN gruppe TO gruppe_delete";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "ALTER TABLE varer ADD COLUMN gruppe integer default 0";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					for ($x=0;$x<count($vareId);$x++) {
						$qtxt = "update varer set gruppe = '$vareGruppe[$x]' where id = '$vareId[$x]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
				$qtxt = "SELECT data_type FROM information_schema.columns ";
				$qtxt.= "WHERE table_name='grupper' and column_name = 'kodenr' and data_type != 'integer'";
				if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$x=0;
					$qtxt = "select id, kodenr from grupper order by id";
					$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$gruppeId[$x]   = $r['id'];
						$gruppeKode[$x] = trim($r['kodenr']);
						$gruppeKodeOld[$x] = $gruppeKode[$x];
						if (!is_numeric($gruppeKode[$x])){
							for ($i=0;$i<strlen($gruppeKode[$x]);$i++) {
								$tmp = substr($gruppeKode[$x],$i,1);
								if (!is_numeric($tmp)) {
									$gruppeKode[$x]=str_replace("$tmp","",$gruppeKode[$x]);
								}
							}
						}
						if (!$gruppeKode[$x]) $gruppeKode[$x] = 0;
						$x++;
					}
					$qtxt = "ALTER TABLE grupper RENAME COLUMN kodenr to kodenr_delete";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "ALTER TABLE grupper ADD COLUMN kodenr integer";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					for ($x=0;$x<count($gruppeId);$x++) {
						$qtxt = "update grupper set kodenr = '$gruppeKode[$x]' where id = '$gruppeId[$x]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='table_plan'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "CREATE TABLE table_plan (id serial NOT NULL, height int, width int, posx int, posy int, ";
					$qtxt.= "name varchar(25), tooltip varchar(40), type varchar(25), pageid int, PRIMARY KEY (id))";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='table_pages'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "CREATE TABLE table_pages (id serial NOT NULL, name varchar(40), PRIMARY KEY (id))";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT id FROM table_pages where name = 'Restaurant'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "insert into table_pages (name) values ('Restaurant')";
				}
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='settings' and column_name = 'group_id'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE settings ADD COLUMN group_id int";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='pos_buttons' and column_name = 'fontcolor'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE pos_buttons ADD COLUMN fontcolor character varying(6)";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='settings' and column_name='pos_id'";
				if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE settings ADD column pos_id int",__FILE__ . " linje " . __LINE__);
				}
#				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='vibrant_terms'";
#				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
#					$qtxt = "	";
#					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#				}
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='loen' and column_name='mentor'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					db_modify("ALTER TABLE loen ADD column mentor text",__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='loen' and column_name='mentor_rate'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					db_modify("ALTER TABLE loen ADD column mentor_rate numeric(15,3)",__FILE__ . " linje " . __LINE__);
				}
				///// 20240215
/*
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='grupper' and column_name = 'fiscal_year'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER table grupper ADD column fiscal_year int DEFAULT 0";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
*/				
				/////
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='pos_betalinger'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "CREATE TABLE pos_betalinger (id serial NOT NULL, ordre_id integer, betalingstype varchar(40), ";
					$qtxt.= "amount numeric(15,3), valuta varchar(3), valutakurs numeric(15,3), receipt_id varchar(75), ";
					$qtxt.= "PRIMARY KEY (id))";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='pos_betalinger' and column_name = 'receipt_id'";
				if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$qtxt = "ALTER TABLE pos_betalinger ADD COLUMN receipt_id varchar(75)";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				db_modify("delete from lagerstatus where beholdning is NULL",__FILE__ . " linje " . __LINE__);
				$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				transaktion('commit');
				include("../includes/connect.php");
				$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}

		$nextver='4.0.9'; #20221004
		if ($fixNo<"9") {
			include("../includes/connect.php");
			$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
			$tmp=$r['version'];
			if ($tmp<$nextver) {
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='settings' and column_name='var_grp'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table settings ADD column var_grp text", __FILE__ . " linje " . __LINE__);
				}
				echo "opdaterer hovedregnskab til ver $nextver<br />";
				$qtxt = "UPDATE regnskab set version = '$nextver' where id = '1'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			include("../includes/online.php");
			include("../includes/std_func.php");
			if ($db!=$sqdb)	{
				echo "opdaterer regnskab til ver $nextver<br />";
				transaktion('begin');
				$i = 0;
				$qtxt = "select id,m_rabat,m_antal from varer where m_rabat like '%000000%' or m_antal like '%000000%'";
				$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$mr = explode(';', $r['m_rabat']);
					$ma = explode(';', $r['m_antal']);
					$m_rabat = $m_antal = '';
					for ($i = 0; $i < count($mr); $i++) {
						if ($mr[$i] > 0) {
							if ($m_rabat) {
								$m_rabat . ';';
								$m_antal . ';';
							}
							$m_rabat .= $mr[$i] *= 1;
							$m_antal .= $ma[$i] *= 1;
						}
					}
					$qtxt = "update varer set m_rabat = '$m_rabat', m_antal = '$m_antal' where id = '$r[id]'";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				if (file_exists("../includes/stdFunc/findDueDate.php")) {
					include_once "../includes/stdFunc/findDueDate.php";
					$qtxt = "SELECT openpost.id,openpost.konto_id, ordrer.id as order_id FROM openpost,ordrer ";
					$qtxt.= "WHERE udlignet != '1' AND openpost.forfaldsdate IS NULL ";
					$qtxt.= "AND openpost.kladde_id = '0' AND openpost.konto_id > '0' AND openpost.beskrivelse like 'Lev. fakt.nr:%' ";
					$qtxt.= "AND ordrer.art = 'KO' AND openpost.faktnr = ordrer.fakturanr AND openpost.konto_id = ordrer.konto_id";
					$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$dueDate = findDueDate($r['order_id']);
						$qtxt = "update openpost set forfaldsdate = '$dueDate' where id = '$r[id]'";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					}
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='table_plan'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE table_plan (id serial NOT NULL, height int, width int, posx int, posy int, ";
					$qtxt .= "name varchar(25), tooltip varchar(40), type varchar(25), pageid int, PRIMARY KEY (id))";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='table_pages'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE table_pages (id serial NOT NULL, name varchar(40), PRIMARY KEY (id))";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT id FROM table_pages where name = 'Restaurant'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "insert into table_pages (name) values ('Restaurant')";
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='brugere' ";
				$qtxt .= "and column_name='email'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE brugere ADD column email text", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='brugere' ";
				$qtxt .= "and column_name='twofactor'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE brugere ADD column twofactor boolean default false", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='corrections' ";
				$qtxt .= "and column_name='report_number'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE corrections ADD column report_number integer default 0", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='deleted_order' ";
				$qtxt .= "and column_name='report_number'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE deleted_order ADD column report_number integer default 0", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='price_correction' ";
				$qtxt .= "and column_name='report_number'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE price_correction ADD column report_number integer default 0", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='returnings' ";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					$qtxt = "CREATE TABLE returnings (id serial NOT NULL, price numeric(15,3), kasse integer, PRIMARY KEY (id))";
					db_modify($qtxt, __FILE__ . "linje" . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='returnings' ";
				$qtxt .= "and column_name='report_number'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE returnings ADD column report_number integer default 0", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='pos_betalinger' ";
				$qtxt .= "and column_name='payment_id'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE pos_betalinger ADD column payment_id integer default 0", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='pos_events'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE pos_events (ev_id serial NOT NULL,ev_type varchar(20),ev_time varchar(12),";
					$qtxt .= "PRIMARY KEY (ev_id))";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns ";
				$qtxt.= "WHERE table_name='pos_events' and  column_name='cash_register_id' ";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE pos_events ADD column cash_register_id int";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='pos_events' and  column_name='employee_id' ";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE pos_events ADD column employee_id int";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='pos_events' and  column_name='product_id' ";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE pos_events ADD column product_id int";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='pos_events' and  column_name='ordre_id' ";
				if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE pos_events RENAME COLUMN ordre_id TO order_id";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='pos_events' and  column_name='order_id' ";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE pos_events ADD column order_id int";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='pos_events' and  column_name='file' ";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE pos_events ADD column file text";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='pos_events' and  column_name='line' ";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE pos_events ADD column line int";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='saf_t_codes'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE saf_t_codes (id serial NOT NULL, basic_id int, eng_name varchar(100), ";
					$qtxt .= "local_name varchar(100), PRIMARY KEY (id))";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					$qtxt = file_get_contents('../importfiler/saf_t_codes.sql');
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				} elseif (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "SELECT EXISTS (SELECT * FROM saf_t_codes) AS has_data";
					$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
					if ($r['has_data'] == 'f') {
						$qtxt = "DROP TABLE IF EXISTS saf_t_codes";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
						$qtxt = "CREATE TABLE saf_t_codes (id serial NOT NULL, basic_id int, eng_name varchar(100), ";
						$qtxt .= "local_name varchar(100), PRIMARY KEY (id))";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
						$qtxt = file_get_contents('../importfiler/saf_t_codes.sql');
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					}
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='kassekladde' and column_name='saldo'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table kassekladde ADD column saldo numeric(15,3)", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='labels'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE labels (id serial NOT NULL, account_id integer, labeltype varchar (10), labelname varchar (40), ";
					$qtxt .= "labeltext text, PRIMARY KEY (id))";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT id FROM labels";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "SELECT * FROM grupper WHERE art ='LABEL'";
					$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
					if ($r = db_fetch_array($q)) {
						$labeltext = db_escape_string($r['box1']);
						$qtxt = "insert into labels (account_id,labeltype,labelname,labeltext) values ";
						$qtxt .= "('0','sheet','Standard','$labeltext')";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					}
				}
				db_modify("delete from grupper where art = 'LABEL'", __FILE__ . " linje " . __LINE__);
				transaktion('commit');
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='grupper' and column_name = 'fiscal_year'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					transaktion('begin');
					$qtxt = "ALTER table grupper ADD column fiscal_year int DEFAULT 0";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$i = 0;
				$qtxt = "select kodenr from grupper where art = 'RA' order by id";
				$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$fiscalYear[$i] = (int) $r['kodenr'];
					$i++;
				}
				if (isset($fiscalYear[0])) { //added by loe 29/11/2023
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
				$qtxt = "SELECT id FROM grupper WHERE art = 'KG' and fiscal_year = '0'";
				if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$i = 0;
					$qtxt = "select kodenr from grupper where art = 'RA' order by kodenr";
					$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$fiscalYear[$i] = $r['kodenr'];
						$i++;
					}
					transaktion('begin');
					$qtxt = "update grupper set fiscal_year = '$fiscalYear[0]' where art = 'KG' and fiscal_year = '0'";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					for ($i = 1; $i < count($fiscalYear); $i++) {
						$qtxt = "SELECT * FROM grupper WHERE (art = 'KG' and fiscal_year = '$fiscalYear[0]')";
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
				transaktion('begin');
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='rentalitems'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE rentalitems (id serial NOT NULL,rt_item_id int,item_id int, qty numeric(15,0), unit varchar(1), ";
					$qtxt .= "item_name varchar(255), product_id int, PRIMARY KEY (id) )";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='rentalitems' and column_name='item_name'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table rentalitems ADD column item_name varchar(255)", __FILE__ . " linje " . __LINE__);
				}		
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='rentalitems' and column_name='product_id'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table rentalitems ADD column product_id int", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='rentalperiod'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE rentalperiod (id serial NOT NULL, rt_id int, rt_cust_id int, rt_from numeric(15,0),";
					$qtxt .= "rt_to numeric(15,0), item_id int, cust_id int, PRIMARY KEY (id))";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='rentalperiod' and column_name='item_id'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table rentalperiod ADD column item_id int", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='rentalperiod' and column_name='cust_id'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table rentalperiod ADD column cust_id int", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='rentalperiod' and column_name='rt_from'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table rentalperiod ADD column rt_from numeric(15,0)", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='rentalperiod' and column_name='rt_to'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table rt_to ADD column rt_to numeric(15,0)", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrelinjer' and ";
				$qtxt .= "(table_schema = '$db' or table_catalog='$db') and column_name = 'barcode'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE ordrelinjer ADD COLUMN barcode varchar(20)";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and ";
				$qtxt .= "(table_schema = '$db' or table_catalog='$db') and column_name = 'report_number'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE ordrer ADD COLUMN report_number int default 0";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and ";
				$qtxt .= "(table_schema = '$db' or table_catalog='$db') and column_name = 'settletime'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE ordrer ADD COLUMN settletime numeric(15,0) default 0";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='transaktioner' and ";
				$qtxt .= "(table_schema = '$db' or table_catalog='$db') and column_name = 'report_number'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE transaktioner ADD COLUMN report_number int default 0";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='transaktioner' and ";
				$qtxt .= "(table_schema = '$db' or table_catalog='$db') and column_name = 'pos'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE transaktioner ADD COLUMN pos smallint default 0";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE ";
				$qtxt.= "(table_schema = '$db' or table_catalog='$db') AND table_name='documents'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE documents (id serial NOT NULL,global_id int,filename text, filepath text, source varchar(20), ";
					$qtxt .= "source_id int, timestamp varchar(10), user_id int, PRIMARY KEY (id) )";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns ";
				$qtxt.= "WHERE (table_schema = '$db' or table_catalog='$db') AND table_name='varetilbud'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE varetilbud ";
					$qtxt .= "(id serial NOT NULL,vare_id integer,startdag numeric(15,0),slutdag numeric(15,0),starttid time,sluttid time,";
					$qtxt .= "ugedag integer,salgspris numeric(15,2),kostpris numeric(15,2),PRIMARY KEY (id))";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE ";
				$qtxt.= "(table_schema = '$db' or table_catalog='$db') AND table_name='adresser' and column_name = 'productlimit'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE adresser ADD COLUMN productlimit numeric(15,0)";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE ";
				$qtxt.= "(table_schema = '$db' or table_catalog='$db') and table_name='mylabel'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE mylabel (id serial NOT NULL, account_id integer, page integer, row integer, col integer, ";
					$qtxt .= "price numeric(15,3), description varchar (40), state varchar(10), barcode varchar (20), hidden boolean, ";
					$qtxt .= " sold integer, created varchar(15), lastprint varchar(15), PRIMARY KEY (id))";
					db_modify($qtxt, __FILE__ . "linje" . __LINE__);
					$qtxt = "CREATE TABLE if not exists labeltemplate (id serial NOT NULL, account_id integer, description varchar (40), ";
					$qtxt .= "labeltext text, PRIMARY KEY (id))";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE ";
				$qtxt.= "(table_schema = '$db' or table_catalog='$db') and table_name='voucheruse' and column_name = 'vat'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE voucheruse ADD COLUMN vat numeric(15,3)", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') and table_name='sager' and column_name = 'sagsnr'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE sager ADD COLUMN sagsnr varchar(15)", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE ";
				$qtxt.= "(table_schema = '$db' or table_catalog='$db') and table_name='noter' and column_name = 'sagsnr'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE noter ADD COLUMN sagsnr varchar(15)", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE ";
				$qtxt.= "(table_schema = '$db' or table_catalog='$db') and table_name='ordrelinjer' and column_name = 'barcode'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE ordrelinjer ADD COLUMN barcode varchar(20)", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE ";
				$qtxt.= "(table_schema = '$db' or table_catalog='$db') and table_name='adresser' and column_name='medlem'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table adresser ADD column medlem text", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '589' and tekst = 'Medarbejder'";
				if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '634' and tekst like '%dette felt%'";
				if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "Select id from tekster where sprog_id = '3' and tekst_id = '1065' and tekst = 'Post'";
				if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '637' and tekst like 'Hvis feltet%'";
				if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '638' and tekst like '%udtages fra kasse%'";
				if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '639' and tekst like 'Er dette felt afmærket%'";
				if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '641' and tekst like 'Kreditor%'";
				if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "Select id from tekster where sprog_id = '3' and tekst_id = '1065' and tekst = 'Post'";
				if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE ";
				$qtxt.= "(table_schema = '$db' or table_catalog='$db') and table_name='ordrelinjer' and column_name = 'barcode'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE ordrelinjer ADD COLUMN barcode varchar(20)", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE ";
				$qtxt.= "table_name='pos_betalinger' and column_name = 'receipt_id'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE pos_betalinger ADD COLUMN receipt_id varchar(75)";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE ";
				$qtxt.= "table_name='kontoplan' and column_name = 'map_to'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE kontoplan ADD COLUMN map_to numeric(15,0)";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "update openpost set valutakurs = '100', valuta = 'DKK' where valutakurs is NULL or valutakurs = '0'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='variant_varer' and ";
				$qtxt .= "column_name='variant_text'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					// Add the variant_text column if it doesn't exist
					db_modify("ALTER table variant_varer ADD column variant_text character varying(25)", __FILE__ . " linje " . __LINE__);
					// Fix the type mismatch by casting variant_type to integer
					$qtxt = "SELECT variant_varer.id as id, variant_typer.beskrivelse as beskrivelse from variant_varer,variant_typer ";
					$qtxt .= "where variant_varer.variant_type::integer = variant_typer.id order by variant_varer.id";
					$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$qtxt = "UPDATE variant_varer set variant_text = '" . db_escape_string($r['beskrivelse']) . "' where id = '$r[id]'";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					}
					// Optional: Fix the column type permanently (can be removed if you prefer to keep using the cast)
					db_modify("ALTER TABLE variant_varer ALTER COLUMN variant_type TYPE integer USING variant_type::integer", __FILE__ . " linje " . __LINE__);
				}

				$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				transaktion('commit');
				include("../includes/connect.php");
				$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		$nextver='4.1.0';
		if ($SubNo < '1'){
		include("../includes/connect.php");
			$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
			$tmp=$r['version'];
			if ($tmp<$nextver) {
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='regnskab' and column_name='sms'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table regnskab ADD column sms int", __FILE__ . " linje " . __LINE__);
				}
				$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			if ($db!=$sqdb){
				include("../includes/online.php");
				include('../includes/stdFunc/ensureTableAndColumns.php')
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

				$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'apikey' => 'varchar(255)', 'trade_conditions' => 'varchar(255)'];
				ensureTableAndColumns($db, 'rentalpayment', $expectedColumns);


				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='digital_status'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table ordrer ADD column digital_status character varying(255)", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='notifications'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE notifications (id SERIAL PRIMARY KEY, msg varchar(255), read_status int)";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='variant_varer' and ";
				$qtxt .= "column_name='variant_text'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table variant_varer ADD column variant_text character varying(25)", __FILE__ . " linje " . __LINE__);
					$qtxt = "SELECT variant_varer.id as id, variant_typer.beskrivelse as beskrivelse from variant_varer,variant_typer ";
					$qtxt .= "where variant_varer.variant_type::integer = variant_typer.id order by variant_varer.id";
					$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$qtxt = "UPDATE variant_varer set variant_text = '" . db_escape_string($r['beskrivelse']) . "' where id = '$r[id]'";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					}
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
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='kds_records'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE kds_records (id SERIAL PRIMARY KEY NOT NULL, data text NOT NULL, bumped boolean NOT NULL, timestamp integer NOT NULL, ";
					$qtxt.= "time_to_complete integer NOT NULL, rush boolean, last_undo boolean)";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='stockmovement'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE stockmovement (id SERIAL PRIMARY KEY NOT NULL, vareid integer NOT NULL, beholdning integer NOT NULL)";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='pos_buttons' and column_name='fontcolor' ";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "ALTER TABLE pos_buttons ADD COLUMN fontcolor character varying(6)";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='settings' and column_name='pos_id'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE settings ADD column pos_id int", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "update settings set pos_id = '0' where var_name = 'customerDisplay' and pos_id is NULL";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);

				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='volume_lager'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table varer ADD column volume_lager float default 1", __FILE__ . " linje " . __LINE__);
				}

				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='lev_land'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table ordrer ADD column lev_land VARCHAR(60)", __FILE__ . " linje " . __LINE__);
				}

				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='lev_email'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table ordrer ADD column lev_email VARCHAR(60)", __FILE__ . " linje " . __LINE__);
				}

				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='gls_label'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table ordrer ADD column gls_label bool default false", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and column_name='fedex_label'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER table ordrer ADD column fedex_label bool default false", __FILE__ . " linje " . __LINE__);
				}
# Access global db

				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='datables'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE datatables (id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL, tabel_id CHARACTER VARYING(10), ";
					$qtxt.= "column_setup TEXT, search_setup TEXT, filter_setup TEXT, rowcount INTEGER, offset INTEGER, sort TEXT)";
					db_modify($qtxt, __FILE__ . " line " . __LINE__);
				}
				$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='tutorials'";
				if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					$qtxt = "CREATE TABLE tutorials (id SERIAL PRIMARY KEY,	user_id INTEGER NOT NULL, tutorial_id CHARACTER VARYING(10),selector TEXT)";
					db_modify(, __FILE__ . " line " . __LINE__);
				}
				$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name='variant_varer' AND column_name='variant_type' AND data_type != 'character varying'";
				if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					// Column exists and is NOT character varying, so alter it
					db_modify("ALTER TABLE variant_varer ALTER COLUMN variant_type TYPE VARCHAR(100)", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='kds_records' ";
				$qtxt .= "and column_name='sort_timestamp'";
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE kds_records ADD column sort_timestamp integer default NULL", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "SELECT tekst FROM tekster WHERE tekst_id = 607 AND sprog_id = 1 AND tekst = 'Kredtor' ";
				if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("UPDATE tekster SET tekst = 'Kreditor' WHERE tekst_id = 607 AND sprog_id = 1", __FILE__ . " linje " . __LINE__);
				}
				$qtxt="UPDATE grupper SET box1='$nextver' where art = 'VE'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				include("../includes/connect.php");
				$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
	}
}
?>
