<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------ includes/opdat_3.8.php-------lap 3.8.9 ------2020-03-08---------------
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
// Copyright (c) 2003-2020 saldi.dk ApS
// ----------------------------------------------------------------------

function opdat_3_8($under_nr, $lap_nr){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();

	$nextver='3.8.1';
	if ($lap_nr<"1"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="CREATE TABLE returnings (id serial NOT NULL, price numeric(15,3), kasse integer, PRIMARY KEY (id))";
			db_modify($qtxt, __FILE__ . "linje" . __LINE__);
			db_modify("ALTER TABLE proforma ALTER COLUMN price type numeric(15,3)", __FILE__ . "linje" . __LINE__);
			db_modify("ALTER TABLE deleted_order  ALTER COLUMN price type numeric(15,3)", __FILE__ . "linje" . __LINE__);
			db_modify("ALTER TABLE corrections ALTER COLUMN price type numeric(15,3)", __FILE__ . "linje" . __LINE__);
			db_modify("ALTER TABLE price_correction ALTER COLUMN price type numeric(15,3)", __FILE__ . "linje" . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"2"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			db_modify("ALTER TABLE tjekliste add pos integer", __FILE__ . "linje" . __LINE__);
			db_modify("update tjekliste set pos = id", __FILE__ . "linje" . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.8.3';
	if ($lap_nr<"3"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			db_modify("ALTER TABLE ordrer add copied boolean", __FILE__ . "linje" . __LINE__);
			db_modify("ALTER TABLE report alter column total type decimal(15,2)", __FILE__ . "linje" . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.8.4';
	if ($lap_nr<"4"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			db_modify("ALTER TABLE loen add hourType text", __FILE__ . "linje" . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.8.5';
	if ($lap_nr<"5"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			db_modify("CREATE INDEX ordrer_fakturadate_idx ON ordrer (fakturadate)",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX ordrer_felt_5_idx ON ordrer (felt_5)",__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.8.6';
	if ($lap_nr<"6"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			db_modify("ALTER TABLE varer add grossweight numeric(15,3)", __FILE__ . "linje" . __LINE__);
			db_modify("ALTER TABLE varer add netweight numeric(15,3)", __FILE__ . "linje" . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.8.7';
	if ($lap_nr<"7"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			db_modify("DROP TABLE drawer", __FILE__ . "linje" . __LINE__);
			db_modify("DROP TABLE corrections", __FILE__ . "linje" . __LINE__);
			db_modify("DROP TABLE price_correction", __FILE__ . "linje" . __LINE__);
			db_modify("CREATE TABLE drawer (id integer, openings integer)", __FILE__ . "linje" . __LINE__);
			db_modify("CREATE TABLE corrections (id integer, price numeric(15,3), kasse integer)", __FILE__ . "linje" . __LINE__);
			db_modify("CREATE TABLE price_correction (id integer, price numeric(15,3), kasse integer)", __FILE__ . "linje" . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.8.8';
	if ($lap_nr<"8"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			transaktion('begin');
			if ($db_type=="mysql" || $db_type=="mysqli") {
				db_modify("ALTER TABLE adresser ADD modtime TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", __FILE__ . "linje" . __LINE__);
				db_modify("ALTER TABLE batch_kob ADD modtime TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", __FILE__ . "linje" . __LINE__);
				db_modify("ALTER TABLE batch_salg ADD modtime TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", __FILE__ . "linje" . __LINE__);
				db_modify("ALTER TABLE varer ADD modtime TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", __FILE__ . "linje" . __LINE__);
			} else {
				$qtxt="SELECT lanname from pg_language WHERE lanname = 'plpgsql'";
				$r=db_fetch_array(db_select($qtxt, __FILE__ . "linje" . __LINE__));
				if (!$r['lanname']) {
				db_modify("CREATE language plpgsql", __FILE__ . "linje" . __LINE__);
				}
				db_modify("ALTER TABLE adresser ADD modtime TIMESTAMP DEFAULT now()", __FILE__ . "linje" . __LINE__);
				db_modify("ALTER TABLE batch_kob ADD modtime TIMESTAMP DEFAULT now()", __FILE__ . "linje" . __LINE__);
				db_modify("ALTER TABLE batch_salg ADD modtime TIMESTAMP DEFAULT now()", __FILE__ . "linje" . __LINE__);
				db_modify("ALTER TABLE varer ADD modtime TIMESTAMP DEFAULT now()", __FILE__ . "linje" . __LINE__);
				$qtxt = "CREATE OR REPLACE FUNCTION update_modtime_column() \n";
				$qtxt.= "RETURNS TRIGGER AS $$ ";
				$qtxt.= "BEGIN ";
				$qtxt.= "NEW.modtime = now(); "; 
				$qtxt.= "RETURN NEW; ";
				$qtxt.= "END; ";
				$qtxt.= "$$ language 'plpgsql';";
				pg_query($qtxt);
				$qtxt = "CREATE TRIGGER update_adresser_modtime BEFORE UPDATE ";
				$qtxt.= "ON adresser FOR EACH ROW EXECUTE PROCEDURE ";
				$qtxt.= "update_modtime_column(); ";
				pg_query($qtxt);
				$qtxt = "CREATE TRIGGER update_batch_kob_modtime BEFORE UPDATE ";
				$qtxt.= "ON batch_kob FOR EACH ROW EXECUTE PROCEDURE ";
				$qtxt.= "update_modtime_column(); ";
				pg_query($qtxt);
				$qtxt = "CREATE TRIGGER update_batch_salg_modtime BEFORE UPDATE ";
				$qtxt.= "ON batch_salg FOR EACH ROW EXECUTE PROCEDURE ";
				$qtxt.= "update_modtime_column(); ";
				pg_query($qtxt);
				$qtxt = "CREATE TRIGGER update_varer_modtime BEFORE UPDATE ";
				$qtxt.= "ON varer FOR EACH ROW EXECUTE PROCEDURE ";
				$qtxt.= "update_modtime_column(); ";
				pg_query($qtxt);
				$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			transaktion('commit');
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.8.9';
	if ($lap_nr<"9"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.9.0';
	include("../includes/connect.php");
	$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
	$tmp=$r['version'];
	if ($tmp<$nextver) {
		$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	include("../includes/online.php");
	if ($db!=$sqdb){
		$qtxt="ALTER TABLE adresser ADD mysale varchar(2)";
		db_modify($qtxt, __FILE__ . "linje" . __LINE__);
		$qtxt="ALTER TABLE varer ADD specialtype varchar(10)";
		db_modify($qtxt, __FILE__ . "linje" . __LINE__);
		$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	include("../includes/connect.php");
	$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

?>
