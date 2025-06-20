<?php
// ------ includes/opdat_3.5.php-------lap 3.6.2 ------2016-01-12---------------
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
// Copyright (c) 2004-2016 Danosoft ApS
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
#			db_modify("update varer set tilbudsdage='1,2,3,4,5,6,7' where special_price > '0'",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE adresser add column saldo numeric(15,3)",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE openpost add uxtid text",__FILE__ . " linje " . __LINE__);
			$q=db_select("select id,transdate from openpost",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				db_modify("update openpost set uxtid='".strtotime($r['transdate'])."' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			}
			db_modify("UPDATE grupper set box1='$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		}
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
}
?>
