<?php
// ------ includes/opdat_3.3.php-------lap 3.4.8 ------2015-01-02---------------
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 Danosoft ApS
// ----------------------------------------------------------------------

function opdat_3_4($under_nr, $lap_nr){
	$title= "opdat";
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();
	$nextver='3.4.1';
	if ($lap_nr<"1"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			transaktion('begin');
			db_modify("ALTER TABLE ansatte ADD password text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ansatte ADD overtid numeric(1,0)",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			transaktion('commit');
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.4.2';
	if ($lap_nr<"2"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			transaktion('begin');
			$q = db_select("select * from ansatte",__FILE__ . " linje " . __LINE__);
			while ($i < db_num_fields($q)) { 
				$feltnavne[$i] = db_field_name($q,$i); 
				$i++; 
			}
			if (!in_array('gruppe',$feltnavne)) {
				db_modify("ALTER TABLE ansatte ADD gruppe numeric(15,0)",__FILE__ . " linje " . __LINE__);
				db_modify("update ansatte set gruppe = '0'",__FILE__ . " linje " . __LINE__);
			}
			$q = db_select("select * from varer",__FILE__ . " linje " . __LINE__);
			while ($i < db_num_fields($q)) { 
				$feltnavne[$i] = db_field_name($q,$i); 
				$i++;
			}
			if (!in_array('indhold',$feltnavne)) db_modify("ALTER TABLE varer ADD indhold numeric(15,3)",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			transaktion('commit');
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.4.3';
	if ($lap_nr<"3"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			$q = db_select("select * from regnskab",__FILE__ . " linje " . __LINE__);
			while ($i < db_num_fields($q)) { 
				$feltnavne[$i] = db_field_name($q,$i); 
				$i++; 
			}
			if (!in_array('gruppe',$feltnavne)) {
				db_modify("ALTER TABLE regnskab ADD bilag numeric(1,0)",__FILE__ . " linje " . __LINE__);
				db_modify("UPDATE regnskab set bilag='0'",__FILE__ . " linje " . __LINE__);
			}
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version='$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			db_modify("UPDATE grupper set beskrivelse='Bilag og dokumenter',art='bilag' where art = 'FTP'",__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select box6 from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
			if ($r['box6']) $bilag=1;
			else $bilag=0;
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}	else $bilag=0;
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version='$nextver',bilag='$bilag' where db='$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.4.4';
	if ($lap_nr<"4"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			db_modify("UPDATE regnskab set version='$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			$q = db_select("select * from ordrelinjer",__FILE__ . " linje " . __LINE__);
				while ($i < db_num_fields($q)) { 
				$feltnavne[$i] = db_field_name($q,$i); 
				$i++;
			}
			if (!in_array('omvbet',$feltnavne)) {
				db_modify("ALTER TABLE ordrer ADD omvbet varchar(2)",__FILE__ . " linje " . __LINE__);
				db_modify("UPDATE ordrer set omvbet=''",__FILE__ . " linje " . __LINE__);
				db_modify("ALTER TABLE ordrelinjer ADD omvbet varchar(2)",__FILE__ . " linje " . __LINE__);
				db_modify("UPDATE ordrelinjer set omvbet=''",__FILE__ . " linje " . __LINE__);
				db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			}
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version='$nextver' where db='$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.4.5';
	if ($lap_nr<"5"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			db_modify("UPDATE regnskab set version='$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			$r=db_fetch_array(db_select("select email from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
			$email=$r['email'];
			include("../includes/ordrefunc.php");
			include("../includes/std_func.php");
			$q=db_select("select ordrelinjer.id ,ordrelinjer.vare_id, ordrelinjer.kostpris, ordrer.valutakurs from ordrelinjer,ordrer where ordrelinjer.ordre_id=ordrer.id and ordrer.status>='3' and ordrer.art = 'DO' and ordrer.fakturadate >= '2014-01-01' and ordrelinjer.vare_id != '0'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q))	{
				list($koordpr,$koordnr,$koordant,$koordid,$koordart)=explode(chr(9),find_kostpris($r['vare_id'],$r['id']));
				$kobs_ordre_pris=explode(",",$koordpr);
				$ko_ant=count($kobs_ordre_pris);
				$kostpris=0;
				for($y=0;$y<$ko_ant;$y++) {
				if ($r['valutakurs'] && $r['valutakurs']!=100) $kobs_ordre_pris[$y]*=100/$r['valutakurs'];
					$kostpris+=$kobs_ordre_pris[$y];
				}
				$kostpris/=$ko_ant;
				$kostpris=afrund($kostpris,3);		
				if ($kostpris!=$r['kostpris']) {
					db_modify("update ordrelinjer set kostpris='$kostpris' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
				}
			}
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version='$nextver',email='$email' where db='$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.4.6';
	if ($lap_nr<"6"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			db_modify("UPDATE regnskab set version='$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			transaktion('begin');
			if ($db_type=="mysql") {
				db_modify("CREATE TABLE IF NOT EXISTS pos_betalinger (id serial NOT NULL,ordre_id integer,betalingstype text,amount numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			} else {
				if (!db_fetch_array(db_select("select * from pg_tables where tablename='pos_betalinger'",__FILE__ . " linje " . __LINE__))) {
					db_modify("CREATE TABLE pos_betalinger (id serial NOT NULL,ordre_id integer,betalingstype text,amount numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
				}
			}
			$q=db_select("select id,felt_1,felt_2,felt_3,felt_4 from ordrer where art='PO' and status>='3'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q))	{
				if (is_numeric($r['felt_2']) && $r['felt_2']) {
					db_modify("insert into pos_betalinger(ordre_id,betalingstype,amount) values ('$r[id]','$r[felt_1]','$r[felt_2]')",__FILE__ . " linje " . __LINE__);
				}
				if (is_numeric($r['felt_4']) && $r['felt_4']) {
					db_modify("insert into pos_betalinger(ordre_id,betalingstype,amount) values ('$r[id]','$r[felt_3]','$r[felt_4]')",__FILE__ . " linje " . __LINE__);
				}
			}
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			transaktion('commit');
		}
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version='$nextver' where db='$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.4.7';
	if ($lap_nr<"7"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			db_modify("UPDATE regnskab set version='$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
				db_modify("CREATE INDEX batch_kob_kobsdate_idx ON batch_kob (kobsdate)",__FILE__ . " linje " . __LINE__);
				db_modify("CREATE INDEX batch_kob_antal_idx ON batch_kob (antal)",__FILE__ . " linje " . __LINE__);
				db_modify("CREATE INDEX batch_kob_vare_id_idx ON batch_kob (vare_id)",__FILE__ . " linje " . __LINE__);
				db_modify("CREATE INDEX batch_salg_salgsdate_idx ON batch_salg (salgsdate)",__FILE__ . " linje " . __LINE__);
				db_modify("CREATE INDEX batch_salg_antal_idx ON batch_salg (antal)",__FILE__ . " linje " . __LINE__);
				db_modify("CREATE INDEX batch_salg_vare_id_idx ON batch_salg (vare_id)",__FILE__ . " linje " . __LINE__);
				db_modify("CREATE INDEX transaktioner_transdate_idx ON transaktioner (transdate)",__FILE__ . " linje " . __LINE__);
				db_modify("CREATE INDEX transaktioner_kontonr_idx ON transaktioner (kontonr)",__FILE__ . " linje " . __LINE__);
				transaktion('begin');
			if ($db_type=="mysql") {
				db_modify("CREATE TABLE IF NOT EXISTS mappe (id serial NOT NULL,beskrivelse text,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
				db_modify("CREATE TABLE IF NOT EXISTS mappebilag (id serial NOT NULL,navn text,beskrivelse text,datotid text,hvem text,assign_to text,assign_id int4,filtype text,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			} else {
				if (!db_fetch_array(db_select("select * from pg_tables where tablename='mappe'",__FILE__ . " linje " . __LINE__))) {
					db_modify("CREATE TABLE mappe (id serial NOT NULL,beskrivelse text,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
				}
				if (!db_fetch_array(db_select("select * from pg_tables where tablename='mappebilag'",__FILE__ . " linje " . __LINE__))) {
					db_modify("CREATE TABLE mappebilag (id serial NOT NULL,navn text,beskrivelse text,datotid text,hvem text,assign_to text,assign_id int4,filtype text,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
				}
			}
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			transaktion('commit');
		}
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version='$nextver' where db='$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.4.8';
	if ($lap_nr<"8"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			db_modify("UPDATE regnskab set version='$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			transaktion('begin');
			db_modify("ALTER TABLE batch_salg ADD lager integer",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE lagerstatus ADD lok1 text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE lagerstatus ADD lok2 text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE lagerstatus ADD lok3 text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE lagerstatus ADD lok4 text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE lagerstatus ADD lok5 text",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			transaktion('commit');
		}
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version='$nextver' where db='$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.4.9';
	if ($lap_nr<"9"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			db_modify("UPDATE regnskab set version='$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			transaktion('begin');
			db_modify("ALTER TABLE ordrelinjer ADD saet integer",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			transaktion('commit');
		}
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version='$nextver' where db='$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.5.0';
	include("../includes/connect.php");
	$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
	$tmp=$r['version'];
	if ($tmp<$nextver) {
		db_modify("UPDATE regnskab set version='$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
	}
	include("../includes/online.php");
	if ($db!=$sqdb){
		transaktion('begin');
		if ($db_type=="mysql") {
			db_modify("CREATE TABLE IF NOT EXISTS ansatmappe (id serial NOT NULL,beskrivelse text,ans_id int4,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE TABLE IF NOT EXISTS ansatmappebilag (id serial NOT NULL,navn text,beskrivelse text,datotid text,hvem text,assign_to text,assign_id int4,filtype text,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE TABLE  IF NOT EXISTS kostpriser (id serial NOT NULL,vare_id integer,transdate date,kostpris numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		} else {
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='ansatmappe'",__FILE__ . " linje " . __LINE__))) {
				db_modify("CREATE TABLE ansatmappe (id serial NOT NULL,beskrivelse text,ans_id int4,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			}
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='ansatmappebilag'",__FILE__ . " linje " . __LINE__))) {
				db_modify("CREATE TABLE ansatmappebilag (id serial NOT NULL,navn text,beskrivelse text,datotid text,hvem text,assign_to text,assign_id int4,filtype text,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			}
			if (!db_fetch_array(db_select("select * from pg_tables where tablename='kostpriser'",__FILE__ . " linje " . __LINE__))) {
				db_modify("CREATE TABLE kostpriser (id serial NOT NULL,vare_id integer,transdate date,kostpris numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			}
		}
		db_modify("update batch_kob set kobsdate = fakturadate where kobsdate is NULL and fakturadate > '2014-01-01'",__FILE__ . " linje " . __LINE__);		
		$q=db_select("select linje_id from batch_salg,ordrelinjer where ordrelinjer.antal < 0 and batch_salg.antal > 0 and batch_salg.linje_id = ordrelinjer.id",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			db_modify("update batch_salg set antal=antal*-1 where linje_id='$r[linje_id]'",__FILE__ . " linje " . __LINE__);
		}
		$lgrp=array();
		$x=0;
		$q=db_select("select kodenr from grupper where art='VG' and box8='on'",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$lgrp[$x]=$r['kodenr']*1;
			$x++;	
		}
		$x=0;
		$kostpris=array();
		db_modify("delete from kostpriser",__FILE__ . " linje " . __LINE__);
		$q=db_select("select id,kostpris,gruppe from varer where lukket != 'on' order by id",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			if (in_array($r['gruppe'],$lgrp)) {
				$kostpris=$r['kostpris']*1;
				db_modify("insert into kostpriser(vare_id,kostpris,transdate)values('$r[id]','$kostpris','2015-01-01')",__FILE__ . " linje " . __LINE__);  
			}
		}
		db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
	}
	include("../includes/connect.php");
	db_modify("UPDATE regnskab set version='$nextver' where db='$db'",__FILE__ . " linje " . __LINE__);
}
?>
