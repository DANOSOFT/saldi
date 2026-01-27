<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- patch 4.0.7--- 2023.03.04 --
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// The content of this file must be moved to opdat_4.0 in section 4.0.8 when 4.0.8 is to be released.
$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='mylabel' and column_name='firstprint'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table mylabel ADD column firstprint varchar(15)",__FILE__ . " linje " . __LINE__);
	db_modify("UPDATE mylabel set firstprint = lastprint",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT id FROM labels";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
	$qtxt="SELECT * FROM grupper WHERE art ='LABEL'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$labeltext = db_escape_string($r['box1']);
		$qtxt = "insert into labels (account_id,labeltype,labelname,labeltext) values ";
		$qtxt.= "('0','sheet','Standard','$labeltext')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
db_modify ("delete from grupper where art = 'LABEL'",__FILE__ . " linje " . __LINE__);

$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='rentalperiod'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
	$qtxt ="CREATE TABLE rentalperiod (id serial NOT NULL,rt_from numeric(15,0),rt_to numeric(15,0), item_id int, cust_id int, order_id int,";
	$qtxt.=" PRIMARY KEY (id) )";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='rentalreserved'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
	$qtxt ='CREATE TABLE rentalreserved (id serial NOT NULL,item_id int,"from" numeric(15,0), "to" numeric(15,0), comment varchar(255),';
	$qtxt.=" PRIMARY KEY (id) )";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='rentalitems'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
	$qtxt ="CREATE TABLE rentalitems (id serial NOT NULL,item_name varchar(255),item_id int,";
	$qtxt.="product_id int, PRIMARY KEY (id) )";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='rentalsettings'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
	$qtxt ="CREATE TABLE rentalsettings (id serial NOT NULL,booking_format int, search_cust_name int, ";
	$qtxt.="search_cust_number int, search_cust_tlf int, start_day int, deletion int, find_weeks int, end_day int, PRIMARY KEY (id) )";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt="SELECT table_name FROM information_schema.columns WHERE table_name='rentalclosed'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
	$qtxt ="CREATE TABLE rentalclosed (id serial NOT NULL, day int, PRIMARY KEY (id) )";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalreserved' and column_name='comment'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalreserved ADD column comment varchar(255)",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalitems' and column_name='item_name'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalitems ADD column item_name varchar(255)",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalitems' and column_name='product_id'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalitems ADD column product_id int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalperiod' and column_name='item_id'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalperiod ADD column item_id int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalperiod' and column_name='cust_id'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalperiod ADD column cust_id int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalperiod' and column_name='rt_from'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalperiod ADD column rt_from numeric(15,0)",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalperiod' and column_name='rt_to'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table rentalperiod ADD column rt_to numeric(15,0)",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalperiod' and column_name='order_id'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalperiod ADD column order_id int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalsettings' and column_name='start_day'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalsettings ADD column start_day int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalsettings' and column_name='deletion'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalsettings ADD column deletion int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalsettings' and column_name='find_weeks'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalsettings ADD column find_weeks int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalsettings' and column_name='end_day'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalsettings ADD column end_day int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalsettings' and column_name='search_cust_tlf'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalsettings ADD column search_cust_tlf int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalsettings' and column_name='search_cust_name'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalsettings ADD column search_cust_name int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalsettings' and column_name='search_cust_number'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalsettings ADD column search_cust_number int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalsettings' and column_name='booking_format'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalsettings ADD column booking_format int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalsettings' and column_name='use_password'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalsettings ADD column use_password int",__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='rentalsettings' and column_name='pass'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
db_modify("ALTER table rentalsettings ADD column pass varchar(255)",__FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrelinjer' and ";
$qtxt.= "(table_schema = '$db' or table_catalog='$db') and column_name = 'barcode'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE ordrelinjer ADD COLUMN barcode varchar(20)";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and ";
$qtxt.= "(table_schema = '$db' or table_catalog='$db') and column_name = 'report_number'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE ordrer ADD COLUMN report_number int default 0";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' and ";
$qtxt.= "(table_schema = '$db' or table_catalog='$db') and column_name = 'settletime'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE ordrer ADD COLUMN settletime numeric(15,0) default 0";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='transaktioner' and ";
$qtxt.= "(table_schema = '$db' or table_catalog='$db') and column_name = 'report_number'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
$qtxt = "ALTER TABLE transaktioner ADD COLUMN report_number int default 0";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

$qtxt="SELECT table_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') AND table_name='documents'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
	$qtxt = "CREATE TABLE documents (id serial NOT NULL,global_id int,filename text, filepath text, source varchar(20), ";
	$qtxt.= "source_id int, timestamp varchar(10), user_id int, PRIMARY KEY (id) )";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt="SELECT table_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') AND table_name='varetilbud'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
	$qtxt = "CREATE TABLE varetilbud ";
	$qtxt.= "(id serial NOT NULL,vare_id integer,startdag numeric(15,0),slutdag numeric(15,0),starttid time,sluttid time,";
	$qtxt.= "ugedag integer,salgspris numeric(15,2),kostpris numeric(15,2),PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt="SELECT column_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') AND table_name='adresser' and column_name = 'productlimit'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
	$qtxt = "ALTER TABLE adresser ADD COLUMN productlimit numeric(15,0)";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}


$qtxt = "SELECT column_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') and table_name='mylabel'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE mylabel (id serial NOT NULL, account_id integer, page integer, row integer, col integer, ";
	$qtxt.= "price numeric(15,3), description varchar (40), state varchar(10), barcode varchar (20), hidden boolean, ";
	$qtxt.= " sold integer, created varchar(15), lastprint varchar(15), PRIMARY KEY (id))";
	db_modify($qtxt, __FILE__ . "linje" . __LINE__);
	$qtxt = "CREATE TABLE if not exists labeltemplate (id serial NOT NULL, account_id integer, description varchar (40), ";
	$qtxt.= "labeltext text, PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT column_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') and table_name='voucheruse' and column_name = 'vat'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER TABLE voucheruse ADD COLUMN vat numeric(15,3)",__FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') and table_name='sager' and column_name = 'sagsnr'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER TABLE sager ADD COLUMN sagsnr varchar(15)",__FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') and table_name='noter' and column_name = 'sagsnr'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER TABLE noter ADD COLUMN sagsnr varchar(15)",__FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT column_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') and table_name='ordrelinjer' and column_name = 'barcode'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE ordrelinjer ADD COLUMN barcode varchar(20)",__FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT column_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') and table_name='adresser' and column_name='medlem'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table adresser ADD column medlem text",__FILE__ . " linje " . __LINE__);
}

$qtxt = "Select id from tekster where sprog_id = '3' and tekst_id = '1065' and tekst = 'Post'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("update tekster set tekst = '' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
}

$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '121' ";
$qtxt.= "and tekst = 'Klik her for at rette i forskellige indstillinger for moms'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("update tekster set tekst = '' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
}
$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '123' and tekst = 'Klik her for momsrapport'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("update tekster set tekst = '' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT id FROM grupper WHERE art = 'bilag'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$qtxt = "insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7) values ";
	$qtxt.= "('Bilag og dokumenter','1','bilag','','','','','','on','')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

$qtxt = "update openpost set valutakurs = '100', valuta = 'DKK' where valutakurs is NULL or valutakurs = '0'";
#	cho "$qtxt<br>";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
/*
$qtxt = "select sum(amount*valutakurs/100) as amount, udlign_id from openpost ";
$qtxt.= "where valuta = 'DKK' and udlignet = '1' group by udlign_id";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if (abs($r['amount']) >= 0.01 && $r['udlign_id']) {
		$udlignId = $r['udlign_id'];
		$qtxt = "select konto_id from openpost where udlign_id = '$udlignId'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$kontoId=$r['konto_id'];
		$qtxt = "select sum(amount*valutakurs/100) as amount from openpost where konto_id = '$kontoId'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if (abs($r['amount']) >= 0.01) {
			$qtxt = "update openpost set udlignet = 0, udlign_id = 0 where konto_id = '$kontoId' and udlign_id = '$udlignId'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "update openpost set udlignet = 1, udlign_id = '$udlignId' where konto_id = '$kontoId' and udlign_id = '0'";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}

$qtxt = "select count(udlign_id) as numbers, udlign_id from openpost where udlignet = '1' group by udlign_id";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if ($r['numbers'] == 1) {
		$udlignId = $r['udlign_id'];
		$qtxt = "select konto_id from openpost where udlign_id = '$udlignId'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$kontoId=$r['konto_id'];
		$qtxt = "select sum(amount*valutakurs/100) as amount from openpost where ";
		$qtxt.= "udlign_id = '$udlignId' and konto_id = '$kontoId'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$amount  = $r['amount'];
		
		if (abs($amount) > 0.01) {
			$qtxt = "select id from openpost where konto_id = '$kontoId' and amount*valutakurs/100 = '". $amount*-1 ."'";
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)) && $id = $r['id']) {
				$qtxt = "update openpost set udlignet = 1, udlign_id = '$udlignId' where id = '$id'";
			} else {
				$qtxt = "update openpost set udlignet = 0, udlign_id = 0 where udlign_id = '$udlignId' and konto_id = '$kontoId'";
			}
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
}
#xit;
$qtxt = "select distinct(konto_id) from openpost where udlignet = '0' and valuta = 'DKK'";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$kontoId = $r['konto_id'];
	$qtxt = "select sum(amount*valutakurs/100) as amount from openpost ";
	$qtxt.= "where udlignet = '0' and valuta = 'DKK' and konto_id = '$kontoId'";
#if ($kontoId == '1423') echo "$qtxt<br>";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$amount  = $r['amount'];
#if ($kontoId == '1423') echo "$amount<br>";
	if (abs($amount) <= 0.01) {
		$qtxt = "select max(udlign_id) as udlign_id from openpost";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$udlignId = $r['udlign_id']+1;
		$qtxt = "update openpost set udlignet = 1, udlign_id = '$udlignId' where udlignet = '0' ";
		$qtxt.= "and valuta = 'DKK' and konto_id = '$kontoId'";
#if ($kontoId == '1423') echo "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
#if ($kontoId == '1423') exit;
}
#xit;
*/
?>
