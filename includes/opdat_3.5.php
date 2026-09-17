<?php
// ------ includes/opdat_3.5.php-------lap 3.5.8 ------2015-08-19---------------
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
//
function opdat_3_5($under_nr, $lap_nr){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();
	$nextver='3.5.1';
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
			db_modify("CREATE INDEX pos_betalinger_ordre_id_idx ON pos_betalinger (ordre_id)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX pos_betalinger_betalingstype_idx ON pos_betalinger (betalingstype)",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE regulering add column lager integer",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer add column fast_db numeric(15,2)",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.5.2';
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
			db_modify("ALTER TABLE ordrer add column afd integer",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer add column afd integer",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer add column lager integer",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE FROM tekster where tekst_id = '677'",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.5.3';
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
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.5.4';
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
			db_modify("CREATE INDEX openpost_id_idx ON openpost (id)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX openpost_konto_id_idx ON openpost (konto_id)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX openpost_udlign_id_idx ON openpost (udlign_id)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX ordrer_art_idx ON ordrer (art)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX ordrer_ordrenr_idx ON ordrer (ordrenr)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX ordrer_betalt_idx ON ordrer (betalt)",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.5.5';
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
			db_modify("delete from tekster where id = '731'",__FILE__ . " linje " . __LINE__);
			db_modify("delete from tekster where id = '732'",__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
			$fifo=$r['box6'];
			if ($fifo) {
				if ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='5'",__FILE__ . " linje " . __LINE__))) {
					$id=$r['id'];
					db_modify("update grupper set box6='1' where id = '$id'",__FILE__ . " linje " . __LINE__);
				} else {
					db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13) values ('Div_valg','5','DIV','','','','','','1','','','','','','','')",__FILE__ . " linje " . __LINE__);
				}
			}
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.5.6';
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
			db_modify("ALTER TABLE ordrelinjer add column tilfravalg text",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.5.7';
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
			db_modify("ALTER TABLE sager add column planfraop text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE sager add column plantilop text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE sager add column planfraned text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE sager add column plantilned text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE opgaver add column opg_planfra text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE opgaver add column opg_plantil text",__FILE__ . " linje " . __LINE__);
			$i = 0;
			$feltnavne=array();
			$q = db_select("select * from ordrelinjer",__FILE__ . " linje " . __LINE__);
			while ($i < db_num_fields($q)) { 
				$feltnavne[$i] = db_field_name($q,$i); 
				$i++; 
			}
			if (!in_array('tilfravalg',$feltnavne)) {
				db_modify("ALTER TABLE ordrelinjer ADD tilfravalg text",__FILE__ . " linje " . __LINE__);
			}
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.5.8';
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
			db_modify("ALTER TABLE varer add column special_from_time time",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE varer add column special_to_time time",__FILE__ . " linje " . __LINE__);
			$i = 0;
			$feltnavne=array();
			$q = db_select("select * from ordrelinjer",__FILE__ . " linje " . __LINE__);
			while ($i < db_num_fields($q)) { 
				$feltnavne[$i] = db_field_name($q,$i); 
				$i++; 
			}
			if (!in_array('tilfravalg',$feltnavne)) {
				db_modify("ALTER TABLE ordrelinjer ADD tilfravalg text",__FILE__ . " linje " . __LINE__);
			}
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}	
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
}
?>
