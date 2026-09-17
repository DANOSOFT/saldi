<?php
// -------------------------- includes/opdat_3.0.php-------lap 3.2.2 ------2011.08.23---------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2010 Danosoft ApS
// ----------------------------------------------------------------------
function opdat_3_0($under_nr, $lap_nr){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();

	if ($lap_nr<"3"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'3.0.3') {
			echo "opdaterer hovedregnskab fra $tmp til ver 3.0.3<br>";
			db_modify("UPDATE regnskab set version = '3.0.3' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 3.0.$lap_nr til ver 3.0.3<br>";
		db_modify("ALTER TABLE transaktioner ADD column hvem text",__FILE__ . " linje " . __LINE__);
		db_modify("update transaktioner set hvem = ''",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD column rabatgruppe integer",__FILE__ . " linje " . __LINE__);
		db_modify("update adresser set rabatgruppe = '0'",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '3.0.3' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select count(id) as id from rabat",__FILE__ . " linje " . __LINE__));
		if (!$r['id']) {
			$x=0;
			$q=db_select("select * from grupper where art = 'DG' and box6 > '0.00' order by kodenr",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				$x++;
				$dg[$x]=$r['kodenr'];
				$dgrabat[$x]=$r['box6'];
			}
			$dgantal=$x;
			for ($x=1;$x<=$dgantal;$x++) {
				$y=0;
				$q=db_select("select * from grupper where art = 'VG' order by kodenr",__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					$y++;
					$vg[$y]=$r['kodenr'];
					db_modify("insert into rabat (rabat,debitorart,debitor,vareart,vare) values ('".$dgrabat[$x]."','DG','$dg[$x]','VG','$vg[$y]')",__FILE__ . " linje " . __LINE__);
				}
			}
		}
		db_modify("UPDATE grupper set box1 = '3.0.3' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.0.3' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"5"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'3.0.5') {
			echo "opdaterer hovedregnskab fra $tmp til ver 3.0.5<br>";
			db_modify("UPDATE regnskab set version = '3.0.5' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		#klargører batch_kob til fifo.
		$linje_id=array();
		$x=0;
		$q=db_select("select * from batch_kob order by linje_id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if (!in_array($linje_id,$r['linje_id']) && $r['pris']*1 != 0) {
				$x++;
				$linje_id[$x]=$r['linje_id'];
				$pris[$x]=$r['pris'];
				$fakturadate[$x]=$r['fakturadate'];
			}
		}
		$b_antal=$x;
		for ($x=1;$x<=$b_antal;$x++) {
			if ($linje_id[$x] && $fakturadate[$x] && $pris[$x]) {
				$y=0;
				$q=db_select("select * from batch_kob where linje_id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					if ($r['pris'] != $pris[$x]) $y++;
				}
				if ($y) {
					db_modify("update batch_kob set pris = '$pris[$x]',fakturadate='$fakturadate[$x]' where linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
				}
			}
		}
		db_modify("UPDATE grupper set box1 = '3.0.5' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.0.5' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"6"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'3.0.6') {
			echo "opdaterer hovedregnskab fra $tmp til ver 3.0.6<br>";
			db_modify("UPDATE regnskab set version = '3.0.6' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		if ($db_type=="mysql") {
			db_modify("ALTER TABLE kassekladde CHANGE projekt projekt text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrer CHANGE projekt projekt text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer CHANGE projekt projekt text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE transaktioner CHANGE projekt projekt text",__FILE__ . " linje " . __LINE__);
		} else {
			db_modify("ALTER TABLE kassekladde RENAME column projekt TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE kassekladde ADD column projekt text",__FILE__ . " linje " . __LINE__);
			db_modify("update kassekladde set projekt = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE kassekladde DROP tmp",__FILE__ . " linje " . __LINE__);

			db_modify("ALTER TABLE ordrer RENAME column projekt TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrer ADD column projekt text",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrer set projekt = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrer DROP tmp",__FILE__ . " linje " . __LINE__);

			db_modify("ALTER TABLE ordrelinjer RENAME column projekt TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer ADD column projekt text",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrelinjer set projekt = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer DROP tmp",__FILE__ . " linje " . __LINE__);

			db_modify("ALTER TABLE transaktioner RENAME column projekt TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE transaktioner ADD column projekt text",__FILE__ . " linje " . __LINE__);
			db_modify("update transaktioner set projekt = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE transaktioner DROP tmp",__FILE__ . " linje " . __LINE__);
		}
		db_modify("ALTER TABLE openpost ADD column projekt text",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '3.0.6' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.0.6' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"7"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'3.0.7') {
			echo "opdaterer hovedregnskab fra $tmp til ver 3.0.7<br>";
			db_modify("UPDATE regnskab set version = '3.0.7' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		db_modify("CREATE TABLE pos_buttons (id serial NOT NULL,menu_id integer,col numeric(2,0),row numeric(2,0),colspan numeric(1,0),rowspan numeric(1,0),beskrivelse text,vare_id numeric(10,0),funktion numeric(1,0),color varchar(6),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '3.0.7' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.0.7' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"8"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'3.0.8') {
			echo "opdaterer hovedregnskab fra $tmp til ver 3.0.8<br>";
			db_modify("UPDATE regnskab set version = '3.0.8' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		$q = db_select("select id,sum,moms from ordrer where status = '3' order by id",__FILE__ . " linje " . __LINE__);
/*
		while ($r =db_fetch_array($q)) {
			$id=$r['id'];
			$sum=$r['sum'];
			$moms=$r['moms'];
			if ((!$sum && !$moms) || ($r=db_fetch_array(db_select("select * from transaktioner where ordre_id='$id'",__FILE__ . " linje " . __LINE__)))){
				db_modify("update ordrer set status=4 where id='$id'",__FILE__ . " linje " . __LINE__);
			}
		}
*/
		db_modify("update ordrer set status='4' where  status = '3' and (sum='0' or sum = NULL) and (moms='0' or moms = NULL)",__FILE__ . " linje " . __LINE__);
/*
		if (db_fetch_array(db_select("select id from grupper where art='DIV' and kodenr='3' and box5='on'",__FILE__ . " linje " . __LINE__))) {
			$x=0;
			$q = db_select("select * from ordrer where status = '4' and fakturadate > '2009-12-31' and art != 'R1' and art != 'R2' and art != 'R3' order by konto_id",__FILE__ . " linje " . __LINE__);
			while ($r =db_fetch_array($q)) {
				$x++;
				$id[$x]=$r['id'];
				$sum[$x]=$r['sum'];
				$moms[$x]=$r['moms'];
				$fakturadate[$x]=$r['fakturadate'];
				$fakturanr[$x]=$r['fakturanr'];
			}
			$antal=$x;
			for ($x=1;$x<=$antal;$x++) {
				if ($sum[$x] && !$r=db_fetch_array(db_select("select * from transaktioner where ordre_id='$id[$x]' or (transdate='$fakturadate[$x]' and faktura = '$fakturanr[$x]')",__FILE__ . " linje " . __LINE__))) {
					db_modify("update ordrer set status=3 where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
				}
			}
		}
*/
		db_modify("UPDATE grupper set box1 = '3.0.8' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.0.8' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"9"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'3.0.9') {
			echo "opdaterer hovedregnskab fra $tmp til ver 3.0.9<br>";
			db_modify("UPDATE regnskab set version = '3.0.9' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		db_modify("ALTER TABLE ordrelinjer ADD column kdo varchar(2)",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE ordrelinjer set kdo = ''",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '3.0.9' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.0.9' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	include("../includes/connect.php");
	$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
	$tmp=$r['version'];
	if ($tmp<'3.1.0') {
		echo "opdaterer hovedregnskab fra $tmp til ver 3.1.0<br>";
		db_modify("UPDATE regnskab set version = '3.1.0' where id = 1",__FILE__ . " linje " . __LINE__);
	}
	include("../includes/online.php");
	db_modify("CREATE TABLE regulering (id serial NOT NULL,vare_id integer,lager integer,bogfort bool,transdate date,logtime time,bogfort_af text, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("UPDATE grupper set box1 = '3.1.0' where art = 'VE'",__FILE__ . " linje " . __LINE__);
	include("../includes/connect.php");
	db_modify("UPDATE regnskab set version = '3.1.0' where db = '$db'",__FILE__ . " linje " . __LINE__);
}
?>
