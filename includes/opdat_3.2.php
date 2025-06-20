<?php
// ------ includes/opdat_3.1.php-------lap 3.2.8 ------2012-02-19---------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2012 Danosoft ApS
// ----------------------------------------------------------------------
function opdat_3_2($under_nr, $lap_nr){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();

	$nextver='3.2.1';
	if ($lap_nr<"1"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		transaktion('begin');
		$q=db_select("select m_rabat from ordrelinjer",__FILE__ . " linje " . __LINE__);
		$fieldType = db_field_type($q,0);
		if ($fieldType != 'numeric') {
			if ($db_type=="mysql") {
				db_modify("ALTER TABLE ordrelinjer CHANGE m_rabat m_rabat numeric(15,3)",__FILE__ . " linje " . __LINE__);
			} else {
				db_modify("ALTER TABLE ordrelinjer ALTER column m_rabat TYPE numeric(15,3)",__FILE__ . " linje " . __LINE__);
			}
		}
		if ($db_type=="mysql") {
			db_modify("ALTER TABLE ordrelinjer CHANGE rabatart rabatart varchar(10)",__FILE__ . " linje " . __LINE__);
		} else {
			db_modify("ALTER TABLE ordrelinjer ALTER column rabatart TYPE varchar(10)",__FILE__ . " linje " . __LINE__);
		}
		echo "opdaterer til ver $nextver<br />";
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.2.2';
	if ($lap_nr<"2"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		transaktion('begin');
		if ($db_type=="mysql") {
			db_modify("CREATE TABLE IF NOT EXISTS ordretekster (id serial NOT NULL,tekst text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE TABLE IF NOT EXISTS navigator (bruger_id integer,session_id text,side text,returside text,konto_id integer,ordre_id integer,vare_id integer)",__FILE__ . " linje " . __LINE__);
		} else {
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='ordretekster'"))) {
				db_modify("CREATE TABLE ordretekster (id serial NOT NULL,tekst text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			}
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='navigator'"))) {
				db_modify("CREATE TABLE navigator (bruger_id integer,session_id text,side text,returside text,konto_id integer,ordre_id integer,vare_id integer)",__FILE__ . " linje " . __LINE__);
			}
		}
		$i = 0;
		$feltnavne=array();
		$q = db_select("select * from jobkort",__FILE__ . " linje " . __LINE__);
		while ($i < db_num_fields($q)) { 
			$feltnavne[$i] = db_field_name($q,$i); 
			$i++; 
		}
		if (!in_array('ordre_id',$feltnavne)) {
			db_modify("ALTER TABLE jobkort ADD ordre_id integer",__FILE__ . " linje " . __LINE__);
		}
		$i = 0;
		$feltnavne=array();
		$q = db_select("select * from adresser",__FILE__ . " linje " . __LINE__);
		while ($i < db_num_fields($q)) { 
			$feltnavne[$i] = db_field_name($q,$i); 
			$i++; 
		}
		if (!in_array('status',$feltnavne)) {
			db_modify("ALTER TABLE adresser ADD status text",__FILE__ . " linje " . __LINE__);
		}
		$id1=0;$cat_id=NULL;$cat_beskrivelse=NULL;
		$q=db_select("select id,box1 from grupper where art='DGCAT' order by id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if (!$id0) $id0=$r['id'];
			($cat_id)?$cat_id.=chr(9).$r['id']:$cat_id=$r['id'];
			($cat_beskrivelse)?$cat_beskrivelse.=chr(9).addslashes($r['box1']):$cat_beskrivelse=addslashes($r['box1']);
		}
		if ($id0) {
			db_modify("update grupper set beskrivelse='Div DebitorInfo',art='DebInfo',box1='$cat_id',box2='$cat_beskrivelse' where id = '$id0'",__FILE__ . " linje " . __LINE__);  
		}
		if ($db_type=="mysql") {
			db_modify("CREATE TABLE IF NOT EXISTS ordretekster (id serial NOT NULL,tekst text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE TABLE IF NOT EXISTS navigator (bruger_id integer,session_id text,side text,returside text,konto_id integer,ordre_id integer,vare_id integer)",__FILE__ . " linje " . __LINE__);
		} else {
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='ordretekster'"))) {
				db_modify("CREATE TABLE ordretekster (id serial NOT NULL,tekst text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			}
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='navigator'"))) {
				db_modify("CREATE TABLE navigator (bruger_id integer,session_id text,side text,returside text,konto_id integer,ordre_id integer,vare_id integer)",__FILE__ . " linje " . __LINE__);
			}
		}
		echo "opdaterer til ver $nextver<br />";
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.2.3';
	if ($lap_nr<"3"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		transaktion('begin');
		if ($db_type=="mysql") {
			db_modify("CREATE TABLE IF NOT EXISTS shop_adresser (id serial NOT NULL,saldi_id integer,shop_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE TABLE IF NOT EXISTS shop_varer (id serial NOT NULL,saldi_id integer,shop_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE TABLE IF NOT EXISTS shop_ordrer (id serial NOT NULL,saldi_id integer,shop_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE TABLE IF NOT EXISTS varianter (id serial NOT NULL,beskrivelse text,shop_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE TABLE IF NOT EXISTS variant_typer (id serial NOT NULL,variant_id integer,shop_id integer,beskrivelse text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE TABLE IF NOT EXISTS variant_varer (id serial NOT NULL,vare_id integer,variant_type text,variant_beholdning numeric(15,3),variant_stregkode text,lager integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		} else {
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='shop_adresser'"))) {
				db_modify("CREATE TABLE shop_adresser (id serial NOT NULL,saldi_id integer,shop_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			}
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='shop_varer'"))) {
				db_modify("CREATE TABLE shop_varer (id serial NOT NULL,saldi_id integer,shop_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			}
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='shop_ordrer'"))) {
				db_modify("CREATE TABLE shop_ordrer (id serial NOT NULL,saldi_id integer,shop_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			}
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='varianter'"))) {
				db_modify("CREATE TABLE varianter (id serial NOT NULL,beskrivelse text,shop_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			}
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='variant_typer'"))) {
				db_modify("CREATE TABLE variant_typer (id serial NOT NULL,variant_id integer,shop_id integer,beskrivelse text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			}
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='variant_varer'"))) {
				db_modify("CREATE TABLE variant_varer (id serial NOT NULL,vare_id integer,variant_type text,variant_beholdning numeric(15,3),variant_stregkode text,lager integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			}
		}
		db_modify("delete from grupper where art = 'DGCAT'",__FILE__ . " linje " . __LINE__);  
		$i = 0;
		$feltnavne=array();
		$q = db_select("select * from varer",__FILE__ . " linje " . __LINE__);
		while ($i < db_num_fields($q)) { 
			$feltnavne[$i] = db_field_name($q,$i); 
			$i++; 
		}
		if (!in_array('kategori',$feltnavne)) {
			db_modify("ALTER TABLE varer ADD kategori text",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE varer set kategori = ''",__FILE__ . " linje " . __LINE__);
		}
		if (!in_array('varianter',$feltnavne)) {
			db_modify("ALTER TABLE varer ADD varianter text",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE varer set varianter = ''",__FILE__ . " linje " . __LINE__);
		}
		if (!in_array('publiceret',$feltnavne)) {
			db_modify("ALTER TABLE varer ADD publiceret varchar(2)",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE varer set publiceret = '0'",__FILE__ . " linje " . __LINE__);
		}
		$i = 0;
		$feltnavne=array();
		$q = db_select("select * from ordrelinjer",__FILE__ . " linje " . __LINE__);
		while ($i < db_num_fields($q)) { 
			$feltnavne[$i] = db_field_name($q,$i); 
			$i++; 
		}
		if (!in_array('variant_id',$feltnavne)) {
			db_modify("ALTER TABLE ordrelinjer ADD variant_id text",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE ordrelinjer set variant_id = ''",__FILE__ . " linje " . __LINE__);
		} 
		echo "opdaterer til ver $nextver<br />";
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.2.4';
	if ($lap_nr<"4"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		transaktion('begin');
		$i = 0;
		$feltnavne=array();
		$q = db_select("select * from ordrelinjer",__FILE__ . " linje " . __LINE__);
		while ($i < db_num_fields($q)) { 
			$feltnavne[$i] = db_field_name($q,$i); 
			$i++; 
		}
		if (!in_array('variant_id',$feltnavne)) {
			db_modify("ALTER TABLE ordrelinjer ADD variant_id text",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE ordrelinjer set variant_id = ''",__FILE__ . " linje " . __LINE__);
		} 
		if (in_array('varianter',$feltnavne)) {
			db_modify("ALTER TABLE ordrelinjer drop column varianter",__FILE__ . " linje " . __LINE__);
		} 
#		echo "opdaterer til ver $nextver<br />";
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.2.5';
	if ($lap_nr<"5"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.2.6';
	if ($lap_nr<"6"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.2.7';
	if ($lap_nr<"7"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		print "<body onload=\"javascript:window.open('../utils/momskontrol.php?email=1', '', '');\">";
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.2.8';
	if ($lap_nr<"8"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		$feltnavne=array();
		$q = db_select("select * from transaktioner",__FILE__ . " linje " . __LINE__);
		while ($i < db_num_fields($q)) { 
			$feltnavne[$i] = db_field_name($q,$i); 
			$i++; 
		}
		if (!in_array('moms',$feltnavne)) {
			db_modify("ALTER TABLE transaktioner ADD moms numeric(15,3)",__FILE__ . " linje " . __LINE__);
		}
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
}

?>
