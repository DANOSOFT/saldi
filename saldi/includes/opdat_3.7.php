<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------ includes/opdat_3.7.php-------lap 3.8.0 ------2019-07-04---------------
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
// Copyright (c) 2003-2019 saldi.dk ApS
// ----------------------------------------------------------------------
// 20190605 PHR Check for database type before check if table 'settings' exists
// 20190704 RG (Rune Grysbæk) Mysqli implementation 

function opdat_3_7($under_nr, $lap_nr){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();

	$nextver='3.7.1';
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
		}
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.7.2';
	if ($lap_nr<"2"){
		global $exec_path,$timezone;
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			$qtxt="CREATE TABLE settings (id serial NOT NULL, var_name text, var_value text, var_description text, PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			if (!isset($timezone)) $timezone='Europe/Copenhagen';
			$qtxt="insert into settings(var_name,var_value,var_description) values ('timezone','$timezone','')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db!=$sqdb){
#			include ("../includes/settings.php");
			transaktion('begin');
			$qtxt="CREATE TABLE settings ";
			$qtxt.="(id serial NOT NULL, var_name text, var_grp text, var_value text, var_description text, user_id integer, PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$fp=fopen("../includes/settings.php","r");
			$log=fopen("../temp/$db/opdat.log","w");
			while ($line=fgets($fp)) {
				$tmp=trim($line);
				if ($tmp && substr($tmp,0,1)=='$') {
					$tmp=substr($tmp,1);
					fwrite ($log,"$tmp\n");
					list ($name,$tmp)=explode("=",$tmp,2);
					fwrite ($log,"$name -> $tmp\n");
					$qu=0;
					$sq=0;
					$value='';
					$description='';
					for ($x=0;$x<strlen($tmp);$x++) {
						if (!$qu && substr($tmp,$x,1)=='"') $qu++;
						elseif ($qu == 1 && substr($tmp,$x-1,1) != '\\' && substr($tmp,$x,1) == '"') $qu++;
						elseif ($qu == 1) $value.=substr($tmp,$x,1); 
						if ($qu > 1) {
							if (!$sq &&  substr($tmp,$x,1)=='#') $sq++;
							elseif ($sq) $description.=substr($tmp,$x,1);
						}
					}
					fwrite ($log,"$name -> $value\n");
					$name=db_escape_string($name);
					$value=db_escape_string($value);
					fwrite ($log,"$name -> $value\n");
					$qtxt="insert into settings(var_name,var_grp,var_value,var_description,user_id) values ('$name','globals','$value','$description','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
			fclose($fp);
			fclose ($log);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			transaktion('commit');
		}
	}
	$nextver='3.7.3';
	if ($lap_nr<"3"){
		include("../includes/std_func.php");
		$txt="Saldi opdateres, vent et øjeblik";
#		print tekstboks($txt);
		global $exec_path,$timezone;
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
#			include ("../includes/settings.php");
#			transaktion('begin');
			$qtxt="CREATE TABLE misc_meta_data ";
			$qtxt.="(id serial NOT NULL, meta_name text, meta_grp text, meta_no integer,";
			$qtxt.=" meta_value text, meta_description text, user_id integer, PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);

			$r=db_fetch_array(db_select("select box4 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
			$kortantal=$r['box4']*1;
			$enabled=NULL;
			for ($x=0;$x<$kortantal;$x++) {
				($x==0)?$enabled='on':$enabled.=chr(9).'on';
			}
			if ($enabled) {
				$qtxt="insert into settings(var_name,var_grp,var_value,var_description,user_id) values ('card_enabled','Paycards','$enabled','Tab separedet list showing enabled paymentcards (Disabled cards are used to specify account# in API orders and returns from payment terminal)','0')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#			transaktion('commit');
		}	
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.7.4';
	if ($lap_nr<"4"){
		include("../includes/std_func.php");
		$txt="Saldi opdateres, vent et øjeblik";
//		print tekstboks($txt);
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			$qtxt="UPDATE regnskab set version = '$nextver' where id = '1'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
			transaktion('begin');
		if ($db!=$sqdb){
			$qtxt="ALTER TABLE varer add column gavekort varchar(2)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);

			$qtxt="CREATE TABLE gavekort ";
			$qtxt.="(id serial NOT NULL, gavekortnr numeric(15,0), PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			
			$qtxt="CREATE TABLE gavekortbrug ";
			$qtxt.="(id serial NOT NULL, gavekortid integer, saldo numeric(15,2), ordre_id integer, PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
			
			$qtxt="CREATE TABLE queries ";
			$qtxt.="(id serial NOT NULL, query text, query_descrpition text, user_id integer, PRIMARY KEY (id))";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);

			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			transaktion('commit');
		}	
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.7.5';
	if ($lap_nr<"5"){
		include("../includes/std_func.php");
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
			$qtxt="ALTER TABLE varer add column vat_price numeric(15,3)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="ALTER TABLE ordrelinjer add column vat_account numeric(15,0)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="ALTER TABLE ordrelinjer add column vat_price numeric(15,3)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			transaktion('commit');
		}	
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.7.6';
	if ($lap_nr<"6"){
		include("../includes/std_func.php");
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
			$i=0;
			$q = db_select("select * from ordrelinjer limit 1",__FILE__ . " linje " . __LINE__);
			while ($i < db_num_fields($q)) { 
				$feltnavne[$i] = db_field_name($q,$i); 
				$i++;
			}
			if (!in_array('omvbet',$feltnavne)) {
				db_modify("ALTER TABLE ordrer ADD omvbet varchar(2)",__FILE__ . " linje " . __LINE__);
				db_modify("UPDATE ordrer set omvbet='' where omvbet is NULL",__FILE__ . " linje " . __LINE__);
				db_modify("ALTER TABLE ordrelinjer ADD omvbet varchar(2)",__FILE__ . " linje " . __LINE__);
				db_modify("UPDATE ordrelinjer set omvbet='' where omvbet is NULL",__FILE__ . " linje " . __LINE__);
			}
			$qtxt=NULL;
			if ($db_type=="mysql" or $db_type=="mysqli") { #RG_mysqli
				$qtxt="CREATE TABLE IF NOT EXISTS pos_betalinger ";
				$qtxt.="(id serial NOT NULL,ordre_id integer,betalingstype text,amount numeric(15,3),PRIMARY KEY (id))";
				$qtxt="CREATE TABLE IF NOT EXISTS pos_betalinger ";
				$qtxt.="(id serial NOT NULL,ordre_id integer,betalingstype text,amount numeric(15,3),PRIMARY KEY (id))";
			} elseif (!db_fetch_array(db_select("select * from pg_tables where tablename='pos_betalinger'",__FILE__ . " linje " . __LINE__))) {
				$qtxt="CREATE TABLE pos_betalinger ";
				$qtxt.="(id serial NOT NULL,ordre_id integer,betalingstype text,amount numeric(15,3),PRIMARY KEY (id))";
			}
			if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt=NULL;
			if ($db_type=="mysql" or $db_type=="mysqli") { #RG_mysqli
				$qtxt="CREATE TABLE IF NOT EXISTS pos_betalinger ";
				$qtxt.="(id serial NOT NULL,ordre_id integer,betalingstype text,amount numeric(15,3),PRIMARY KEY (id))";
				$qtxt="CREATE TABLE IF NOT EXISTS kostpriser ";
				$qtxt.="(id serial NOT NULL,vare_id integer,transdate date,kostpris numeric(15,3),PRIMARY KEY (id))";
			} elseif (!db_fetch_array(db_select("select * from pg_tables where tablename='kostpriser'",__FILE__ . " linje " . __LINE__))) {
				$qtxt="CREATE TABLE kostpriser (id serial NOT NULL,vare_id integer,transdate date,kostpris numeric(15,3),PRIMARY KEY (id))";
			}
			if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$i=0;
			$q = db_select("select * from varer limit 1",__FILE__ . " linje " . __LINE__);
			while ($i < db_num_fields($q)) { 
				$feltnavne[$i] = db_field_name($q,$i); 
				$i++;
			}
			if (!in_array('montage',$feltnavne)) {
				db_modify("ALTER TABLE varer ADD montage numeric(15,3)",__FILE__ . " linje " . __LINE__);
				db_modify("UPDATE varer set montage='0' where montage is NULL",__FILE__ . " linje " . __LINE__);
				db_modify("ALTER TABLE varer ADD demontage numeric(15,3)",__FILE__ . " linje " . __LINE__);
				db_modify("UPDATE varer set demontage='0' where demontage is NULL",__FILE__ . " linje " . __LINE__);
			}
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
		}	
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.7.7';
	if ($lap_nr<"7"){
		include("../includes/std_func.php");
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
			db_modify("CREATE TABLE drawer (id serial NOT NULL, openings integer, PRIMARY KEY (id))", __FILE__ . "linje" . __LINE__);
			db_modify("CREATE TABLE proforma (id serial NOT NULL, price integer, count integer, PRIMARY KEY (id))", __FILE__ . "linje" . __LINE__);
			db_modify("CREATE TABLE deleted_order (id serial NOT NULL, price integer, kasse integer, PRIMARY KEY (id))", __FILE__ . "linje" . __LINE__);
			db_modify("CREATE TABLE corrections (id serial NOT NULL, price integer, kasse integer, PRIMARY KEY (id))", __FILE__ . "linje" . __LINE__);
			db_modify("CREATE TABLE report (id serial NOT NULL, date date, type text, description text, count integer, total float, report_number integer, PRIMARY KEY (id))", __FILE__ . "linje" . __LINE__);
			db_modify("CREATE TABLE price_correction (id serial NOT NULL, price integer, kasse integer, PRIMARY KEY (id))", __FILE__ . "linje" . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			include("../includes/connect.php");
			$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	$nextver='3.7.8';
	if ($lap_nr<"8"){
		include("../includes/std_func.php");
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
			db_modify("ALTER TABLE deleted_order add ordre_id integer", __FILE__ . "linje" . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			include("../includes/connect.php");
			$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	$nextver='3.7.9';
	if ($lap_nr<"9"){
		include("../includes/std_func.php");
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
    db_modify("ALTER TABLE varer add provision integer", __FILE__ . "linje" . __LINE__);
			$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			include("../includes/connect.php");
			$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	$nextver='3.8.0';
	include("../includes/std_func.php");
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
		db_modify("ALTER TABLE deleted_order alter column price type decimal(15,2)", __FILE__ . "linje" . __LINE__);
		$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		$qtxt="UPDATE regnskab set version = '$nextver' where db = '$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
?>
