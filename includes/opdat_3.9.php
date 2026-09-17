<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------ includes/opdat_3.9.php-------rel. 4.0.0 ------2022-02-20---------------
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
// Copyright (c) 2019 - 2022 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20200220 PHR	Changed column condition to state in table mylabel as condition can't be used in MariaDB 

function opdat_3_9($majorNo, $subNo, $fixNo){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();

	$nextver='3.9.1';
	if ($fixNo<"1"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt = "ALTER regnskab add ADD column mysale varchar(2)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			$qtxt = "CREATE TABLE mylabel (id serial NOT NULL, account_id integer, page numeric(5,0), row numeric(5,0), ";
			$qtxt.= "col numeric(5,0), price numeric(15,3), description varchar (40), state varchar(10), barcode varchar (20), ";
			$qtxt.= "PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.9.2';
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
			$qtxt="ALTER TABLE mylabel ALTER COLUMN description TYPE varchar(40)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="ALTER TABLE adresser ADD COLUMN invoiced date";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			include ("../includes/addrOpdat.php");
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.9.3';
	if ($fixNo<"3"){
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
			$qtxt = "CREATE TABLE labels (id serial NOT NULL, account_id integer, labeltype varchar (10), labelname varchar (40), ";
			$qtxt.= "labeltext text, PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);

			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='labeltemplate' and column_name='id'";
			if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="DROP TABLE labeltemplate";
				db_modify($qtxt, __FILE__ . "linje" . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='adresser' and column_name='mysale'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="ALTER TABLE adresser ADD mysale varchar(2)";
				db_modify($qtxt, __FILE__ . "linje" . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='adresser' and column_name='invoiced'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="ALTER TABLE adresser ADD invoiced date";
				db_modify($qtxt, __FILE__ . "linje" . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='specialtype'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="ALTER TABLE varer ADD specialtype varchar(10)";
				db_modify($qtxt, __FILE__ . "linje" . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='queries' and column_name='id'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="CREATE TABLE queries ";
				$qtxt.="(id serial NOT NULL, query text, query_descrpition text, user_id integer, PRIMARY KEY (id))";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='returnings' and column_name='id'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="CREATE TABLE returnings (id serial NOT NULL, price numeric(15,3), kasse integer, PRIMARY KEY (id))";
				db_modify($qtxt, __FILE__ . "linje" . __LINE__);
			}
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.9.4';
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
			$qtxt = "CREATE TABLE stocklog (id serial NOT NULL, item_id integer, username varchar (10), initials varchar (10), ";
			$qtxt.= "correction numeric(15,3), reason text,logtime varchar (10), PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='labeltemplate' and column_name='id'";
			if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="DROP TABLE labeltemplate";
				db_modify($qtxt, __FILE__ . "linje" . __LINE__);
			}
			$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='labels' and column_name='id'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt = "CREATE TABLE labels (id serial NOT NULL, account_id integer, labeltype varchar (10), labelname varchar (40), ";
				$qtxt.= "labeltext text, PRIMARY KEY (id))";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$qtxt="update settings set var_name = 'confirmDescriptionChange' where var_name = 'confirmDiscriptionChange'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.9.5';
	if ($fixNo<"5"){
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
			$qtxt="ALTER TABLE mylabel ADD COLUMN hidden boolean";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="ALTER TABLE mylabel ADD COLUMN sold boolean";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE mylabel SET hidden = FALSE, sold = FALSE";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.9.6';
	if ($fixNo<"6"){
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
			$qtxt="ALTER TABLE varer ADD COLUMN netweightunit varchar(2)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="ALTER TABLE varer ADD COLUMN grossweightunit varchar(2)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="ALTER TABLE varer ADD COLUMN length numeric(15,3)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="ALTER TABLE varer ADD COLUMN width numeric(15,3)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="ALTER TABLE varer ADD COLUMN height numeric(15,3)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="ALTER TABLE varer ADD COLUMN address_id varchar(2)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="ALTER TABLE ordrer ADD COLUMN netweight numeric(15,3)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="ALTER TABLE ordrer ADD COLUMN grossweight numeric(15,3)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="ALTER TABLE ordrer ADD COLUMN phone varchar(15)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.9.7';
	if ($fixNo<"7"){
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
			echo "opdaterer regnskab til ver $nextver<br />";
			$qtxt = "CREATE TABLE rental (id serial NOT NULL,rt_item_id int, rt_name varchar(40), PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "CREATE TABLE rentalperiod (id serial NOT NULL, rt_id int, rt_cust_id int, rt_from numeric(15,0),";
			$qtxt.= "rt_to numeric(15,0), PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.9.8';
	if ($fixNo<"8"){
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
			echo "opdaterer regnskab til ver $nextver<br />";
			$qtxt="ALTER TABLE mylabel ADD COLUMN lastprint varchar(15)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE labels SET labeltype='sheet'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="SELECT id FROM labels WHERE labeltext like '%cols=1;%'";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				$qtxt="UPDATE labels SET labeltype='sheet' where id='$r[id]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$qtxt="UPDATE grupper SET box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.9.9';
	if ($fixNo<"9"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			$qtxt = "CREATE TABLE mysale (id serial NOT NULL,deb_id int, db varchar(20), email varchar(60), link text, PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			$qtxt = "CREATE TABLE voucher (id serial NOT NULL, item_id int, barcode numeric(15,0), PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "CREATE TABLE voucheruse (id serial NOT NULL, voucher_id int, order_id int, amount numeric(15,3), "; 
			$qtxt.= "vat numeric(15,3), PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);

			$qtxt = "ALTER TABLE ordrer ADD COLUMN report_number int default 0";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "ALTER TABLE ordrer ADD COLUMN settletime numeric(15,0) default 0";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					
			$qtxt = "ALTER TABLE transaktioner ADD COLUMN report_number int default 0";
			$qtxt="select box1 from grupper where art = 'POS' and kodenr = '1'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$kasse=$r['box1']*1;
			for ($x=1;$x<=$kasse;$x++) {
				$qtxt = "select logdate,logtime from transaktioner where beskrivelse like 'Kasseoptaelling%' ";
				$qtxt.= "and kontonr='0' and debet='0' and kredit='0' and kasse_nr='$x' order by id desc limit 1"; #20161116
				if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$logdate=$r['logdate'];
					$logtime=$r['logtime'];
				} else {
					$logdate=date("Y-m-d");
					$logtime=date('H:i');
				}
				$qtxt = "select id from ordrer where status='3' and art='PO'  and sum = '0' and moms = '0' ";
				$qtxt.= "and (fakturadate < '$logdate' or (fakturadate = '$logdate' and tidspkt < '$logtime'))";
					$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r=db_fetch_array($q)) {
					db_modify("update ordrer set status='4' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
				}	
			}
			$qtxt="UPDATE grupper SET box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='4.0.0';
	if ($majorNo < '4'){
	include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		if ($db!=$sqdb){
			include("../includes/online.php");
			$qtxt ="CREATE TABLE rentalitems (id serial NOT NULL,rt_item_id int,item_id int, qty numeric(15,0), unit varchar(1), ";
			$qtxt.="PRIMARY KEY (id) )";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$rtItemId=array();
			$qtxt = "select distinct (rt_item_id) as rt_item_id from rental order by rt_item_id";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			$x=0;
			while ($r=db_fetch_array($q)) {
				$rtItemId[$x] = $r['rt_item_id'];
				$x++;
			}
			$qtxt="ALTER TABLE ordrer ADD COLUMN consignmentid varchar(25)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			for ($x=0;$x<count($rtItemId);$x++) {
				$y = $x+1;
				$qtxt = "insert into rentalitems(rt_item_id,item_id,qty,unit) values ('$y','$rtItemId[$x]','1','d')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "update rental set rt_item_id = $y where rt_item_id = $rtItemId[$x]";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$qtxt="UPDATE grupper SET box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}

?>
