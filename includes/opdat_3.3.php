<?php
// ------ includes/opdat_3.3.php-------lap 3.3.9 ------2014-01-07---------------
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
// Copyright (c) 2004-2014 Danosoft ApS
// ----------------------------------------------------------------------
function opdat_3_3($under_nr, $lap_nr){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();
	$nextver='3.3.1';
	if ($lap_nr<"1"){
		include("../includes/connect.php");
		include("../includes/online.php");
		$r=db_fetch_array(db_select("select email from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
		$email=$r['email'];
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("ALTER TABLE regnskab ADD email text",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		db_modify("UPDATE regnskab set email = '$email' where db = '$db'",__FILE__ . " linje " . __LINE__);
		include("../includes/online.php");
		transaktion('begin');
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.3.2';
	if ($lap_nr<"2"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		$x=0;
		$k_id=array();
		$q=db_select("select id from kladdeliste where bogfort='V' and bogforingsdate > '2013-06-01'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$k_id[$x]=$r['id'];
#cho "$x :: $k_id[$x]<br>";				
			$x++;
		}
		$x=0;
		$dbbf=0;
		while ($k_id[$x]) {
			$message=NULL;
			$logtime=NULL;
			$logdate=NULL;
#cho "select logdate,logtime from transaktioner where kladde_id='$k_id[$x]'<br>";
			$q=db_select("select logdate,logtime from transaktioner where kladde_id='$k_id[$x]'",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				if (!$logdate) {
					$logdate=$r['logdate'];
					$logtime=$r['logtime'];
				} elseif ($logdate!=$r['logdate'] || $logtime!=$r['logtime']) {
				#cho "$x :: Logdate	$logdate, logtime $logtime <--> $r[logdate] $r[logtime]<br>";				
					if (!$message) {
						$message=$db." | ".$qtext." | ".$spor." | ".$brugernavn." ".date("Y-m-d H:i:s")." | Dobbelt bogføring af kladde $k_id[$x]";
						$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
						mail('phr@danosoft.dk', 'Dobbelt bogføring', $message, $headers);
					}
					print "<BODY onLoad=\"javascript:alert('Der er konstateret dobbelt bogføring af kassekladde nr $k_id[$x]! \\\nKontakt venligst Danosoft på telefon 4690 2208')\">";
					$dbbf=1;
				}
			}
			$x++;
		}
		if ($dbbf==0) {
			transaktion('begin');
			db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			transaktion('commit');
			include("../includes/connect.php");
			db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
		} else {
			include("../includes/connect.php");
			return;
		}
	}
	$nextver='3.3.3';
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
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.3.4';
	if ($lap_nr<"4"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		if ($db!=$sqdb){
			include("../includes/online.php");
			transaktion('begin');
			$i = 0;
			$feltnavne=array();
			$q = db_select("select * from transaktioner",__FILE__ . " linje " . __LINE__);
			while ($i < db_num_fields($q)) { 
				$feltnavne[$i] = db_field_name($q,$i); 
				$i++; 
			}
			if (!in_array('kasse_nr',$feltnavne)) {
				db_modify("ALTER TABLE transaktioner ADD kasse_nr numeric(15,0) default '0'",__FILE__ . " linje " . __LINE__);
			}
			db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			transaktion('commit');
			include("../includes/connect.php");
		}
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.3.5';
	if ($lap_nr<"5"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		if ($db!=$sqdb){
			include("../includes/online.php");
			$x=0;
			$y=0;
			$sum[$y]=0;
			$q = db_select("select * from transaktioner where bilag > 0 order by bilag",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				$x++;
				$bilag[$x]=$r['bilag'];
				if ($x && $bilag[$x] != $bilag[$x-1]) {
					if (abs($sum[$y])>=1) {
					$y++; 
						$sum[$y]=0;
					}
				}
				if ($r['debet']>0) $sum[$y]+=$r['amount'];
				if ($r['kredit']>0) $sum[$y]-=$r['amount'];
			}
			if (count($sum)>1) {
				$message="Ubalance i regnskab $db";
				$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
				mail('phr@danosoft.dk', $message, $message, $headers);
			}		
			db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			include("../includes/connect.php");
		}
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.3.6';
	if ($lap_nr<"6"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		if ($db!=$sqdb){
			include("../includes/online.php");
			transaktion('begin');
			db_modify("ALTER TABLE regulering ADD variant_id integer default '0'",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			transaktion('commit');
			include("../includes/connect.php");
		}
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.3.7';
	if ($lap_nr<"7"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			db_modify("CREATE TABLE diverse (id serial NOT NULL,beskrivelse text,nr numeric(15,0),box1 text,box2 text,box3 text,box4 text,box5 text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		if ($db!=$sqdb){
			include("../includes/online.php");
			db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			include("../includes/connect.php");
		}
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.3.8';
	if ($lap_nr<"8"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		if ($db!=$sqdb){
			include("../includes/online.php");
			db_modify("ALTER TABLE brugere ADD tmp_kode text",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			include("../includes/connect.php");
		}
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.3.9';
	if ($lap_nr<"9"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id='1'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
			echo "opdaterer hovedregnskab til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = '1'",__FILE__ . " linje " . __LINE__);
		}
		if ($db!=$sqdb){
			include("../includes/online.php");
			db_modify("ALTER TABLE ordrer ADD dokument text",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			include("../includes/connect.php");
		}
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.4.0';
	include("../includes/connect.php");
	$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
	$tmp=$r['version'];
	if ($tmp<$nextver) {
		echo "opdaterer hovedregnskab til ver $nextver<br />";
		db_modify("UPDATE regnskab set version = '$nextver' where id = 1",__FILE__ . " linje " . __LINE__);
	}
	if ($db!=$sqdb){
		include("../includes/online.php");
		db_modify("ALTER TABLE ordrer ALTER COLUMN tilbudnr SET DATA TYPE text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD procenttillag numeric(15,3) default '0'",__FILE__ . " linje " . __LINE__);
		db_modify("alter table ordrer add column mail_bilag varchar(2)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrelinjer ADD procent numeric(15,3) default '100'",__FILE__ . " linje " . __LINE__);
		db_modify("alter table ordretekster add column sort numeric(15,0)",__FILE__ . " linje " . __LINE__);
		db_modify("alter table tjekskema add column man_trans text",__FILE__ . " linje " . __LINE__);
		db_modify("alter table tjekskema add column stillads_til text",__FILE__ . " linje " . __LINE__);
		db_modify("alter table tjekskema add column opg_navn text",__FILE__ . " linje " . __LINE__);
		db_modify("alter table tjekskema add column opg_beskrivelse text",__FILE__ . " linje " . __LINE__);
		db_modify("alter table tjekskema add column sjakid text",__FILE__ . " linje " . __LINE__);
		db_modify("alter table noter add column sagsnr text",__FILE__ . " linje " . __LINE__);
		db_modify("alter table loen add column sag_ref text",__FILE__ . " linje " . __LINE__);
	
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
	}
	db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
}	


?>
