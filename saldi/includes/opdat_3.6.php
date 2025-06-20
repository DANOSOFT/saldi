<?php
///                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------ includes/opdat_3.6.php-------lap 3.7.0 ------2017-11-14---------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2017 saldi.dk ApS
// ----------------------------------------------------------------------
//

function opdat_3_6($under_nr, $lap_nr){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();
	$nextver='3.6.1';
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
			db_modify("ALTER TABLE kontoplan add column valuta integer",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE kontoplan add column valutakurs numeric(15,4)",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE kontoplan set valuta = '0', valutakurs ='100'",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.6.2';
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
			db_modify("ALTER TABLE varer add column fotonavn text",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.6.3';
	if ($lap_nr<"3"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			db_modify("ALTER TABLE ordrer add column kontakt_tlf text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE varer add column tilbudsdage text",__FILE__ . " linje " . __LINE__);
			db_modify("update varer set tilbudsdage='1,2,3,4,5,6,7' where special_price > '0'",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE adresser add column saldo numeric(15,3)",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE openpost add uxtid text",__FILE__ . " linje " . __LINE__);
			$q=db_select("select id,transdate from openpost",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				db_modify("update openpost set uxtid='".strtotime($r['transdate'])."' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			}
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}	
	$nextver='3.6.4';
	if ($lap_nr<"4"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			db_modify("CREATE TABLE varetilbud (id serial NOT NULL,vare_id integer,startdag numeric(15,0),slutdag numeric(15,0),starttid time,sluttid time,ugedag integer,salgspris numeric(15,2),kostpris numeric(15,2),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}	
	$nextver='3.6.5';
	if ($lap_nr<"5"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			db_modify("ALTER TABLE pos_betalinger add column valuta varchar(3)",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE pos_betalinger add column valutakurs numeric(15,3)",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE pos_betalinger set valuta='DKK',valutakurs='100'",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX batch_kob_id_idx ON batch_kob (id)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX batch_kob_linje_id_idx ON batch_kob (linje_id)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX batch_kob_fakturadate_idx ON batch_kob (fakturadate)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX batch_salg_fakturadate_idx ON batch_salg (fakturadate)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX ordrelinjer_id_idx ON ordrelinjer (id)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX ordrer_id_idx ON ordrer (id)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX varer_id_idx ON varer (id)",__FILE__ . " linje " . __LINE__);
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.6.6';
	if ($lap_nr<"6"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			if ($db_type == "postgresql") {
				db_modify("DROP INDEX CONCURRENTLY IF EXISTS varer_beskrivelse_idx",__FILE__ . " linje " . __LINE__);
				db_modify("CREATE INDEX varer_beskrivelse_idx ON varer (beskrivelse)",__FILE__ . " linje " . __LINE__);
			}
			db_modify("CREATE TABLE bilag_tjekskema (id serial NOT NULL,tjekskema_id integer,bilag_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.6.7';
	if ($lap_nr<"7"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			echo "opdaterer regnskab til ver $nextver<br />";
			db_modify("DELETE from tekster where tekst_id='166' and sprog_id='1'",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE from tekster where tekst_id='191' and sprog_id='1'",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE from tekster where tekst_id='213' and sprog_id='1'",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE from tekster where tekst_id='214' and sprog_id='1'",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box5='on;on' where box5='on' and  art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.6.8';
	if ($lap_nr<"8"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			echo "opdaterer regnskab til ver $nextver<br />";
			db_modify("ALTER TABLE ordrer add column lager integer",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.6.9';
	if ($lap_nr<"9"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			echo "opdaterer regnskab til ver $nextver<br />";
			db_modify("ALTER TABLE transaktioner add column land varchar(3)",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.7.0';
	include("../includes/connect.php");
	$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
	$tmp=$r['version'];
	if ($tmp<$nextver) {
		echo "opdaterer hovedregnskab til ver $nextver<br />";
		db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
	}
	include("../includes/online.php");
	if ($db!=$sqdb){
		echo "opdaterer regnskab til ver $nextver<br />";
		db_modify("ALTER TABLE shop_varer add column saldi_variant int",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE shop_varer add column shop_variant int",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE batch_kob add column variant_id int",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE batch_salg add column variant_id int",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE lagerstatus add column variant_id int",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE shop_varer set saldi_variant='0',shop_variant='0'",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE batch_kob set variant_id='0' where variant_id is NULL",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE batch_salg set variant_id='0' where variant_id is NULL",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE lagerstatus set variant_id='0' where variant_id is NULL",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
	}
	include("../includes/connect.php");
	db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
}

?>
