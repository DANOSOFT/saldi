<?php
// ------ includes/opdat_3.1.php-------lap 3.1.7 ------2011-04-14---------------
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
// Copyright (c) 2004-2011 Danosoft ApS
// ----------------------------------------------------------------------
function opdat_3_1($under_nr, $lap_nr){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	global $db_type;
	$s_id=session_id();

	if ($lap_nr<"1"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'3.1.1') {
			echo "opdaterer hovedregnskab fra $tmp til ver 3.1.1<br />";
			db_modify("UPDATE regnskab set version = '3.1.1' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 3.1.$lap_nr til ver 3.1.1<br />";
		db_modify("ALTER TABLE regulering ADD column tidspkt text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE regulering ADD column optalt numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE regulering ADD column beholdning numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '3.1.1' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.1.1' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"2"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'3.1.2') {
			echo "opdaterer hovedregnskab fra $tmp til ver 3.1.2<br />";
			db_modify("UPDATE regnskab set version = '3.1.2' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 3.1.$lap_nr til ver 3.1.2<br />";
		db_modify("UPDATE grupper set box1 = '3.1.2' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.1.2' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"3"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'3.1.3') {
			echo "opdaterer hovedregnskab fra $tmp til ver 3.1.3<br />";
			db_modify("UPDATE regnskab set version = '3.1.3' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 3.1.$lap_nr til ver 3.1.3<br />";
		db_modify("ALTER TABLE grupper ADD column box11 text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE grupper ADD column box12 text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE grupper ADD column box13 text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE grupper ADD column box14 text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrelinjer ADD column rabatart varchar(6)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE rabat ADD column rabatart varchar(6)",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE rabat set rabatart = '%'",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '3.1.3' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.1.3' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"4"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'3.1.4') {
			echo "opdaterer hovedregnskab fra $tmp til ver 3.1.4<br />";
			db_modify("UPDATE regnskab set version = '3.1.4' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		$x=0;
		$q=db_select("select distinct(udlign_id) as udlign_id from openpost where udlignet='1' and udlign_date<transdate order by udlign_id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)){
			if ($r['udlign_id'] > 0) {
				$x++;
				$udlign_id[$x]=$r['udlign_id']*1;
			}
		}
		$x=0;
		$udlign_id=array();
		$q=db_select("select udlign_id,transdate,udlign_date,amount from openpost where udlign_id>'0' and udlignet='1' order by udlign_id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)){
			if (!in_array($r['udlign_id'],$udlign_id)) {
				$x++;
				$udlign_id[$x]=$r['udlign_id']*1;
				$udlign_date[$x]=$r['transdate'];
				$amount[$x]=$r['amount'];
			} else {
				if ($r['transdate']>$udlign_date[$x]) $udlign_date[$x]=$r['transdate'];
				$amount[$x]+=$r['amount'];
			}
		}
		for ($x=1;$x<=count($udlign_id);$x++) {
			$amount[$x]*=1;
			if ($amount[$x]) {
				$q=db_modify("update openpost set udlignet='0' where udlign_id='$udlign_id[$x]'",__FILE__ . " linje " . __LINE__);
			} else {
				$q=db_modify("update openpost set udlign_date='$udlign_date[$x]' where udlign_date!='$udlign_date[$x]' and udlign_id='$udlign_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
		echo "opdaterer ver 3.1.$lap_nr til ver 3.1.4<br />";
		db_modify("UPDATE grupper set box1 = '3.1.4' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.1.4' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"6"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'3.1.6') {
			echo "opdaterer hovedregnskab fra $tmp til ver 3.1.6<br />";
			db_modify("UPDATE regnskab set version = '3.1.6' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		db_modify("ALTER TABLE openpost ADD column projekt text",__FILE__ . " linje " . __LINE__);
		if (db_fetch_array(db_select("select id from grupper where art = 'PRJ'",__FILE__ . " linje " . __LINE__))) {
			$x=0;
			$id=array();
			$udlign_id=array();
			$q=db_select("select id,projekt from ordrer where status > 3 order by id",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)){
				$x++;
				$id[$x]=$r['id'];
				$projekt[$x]=$r['projekt'];
			}
			$antal=$x;
			for ($x=1;$x<=$antal;$x++) {
				if (!$projekt[$x]) {
					$q2=db_select("select distinct(projekt) as projekt from ordrelinjer where ordre_id='$id[$x]' order by projekt",__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)){
						if ($r2['projekt']) {
							($projekt[$x])?$projekt[$x].="<br>".$r2['projekt']:$projekt[$x]=$r2['projekt'];  
						}	
					}
				}
				if ($projekt[$x]) {
					$q=db_modify("update openpost set projekt='$projekt[$x]' where refnr='$id[$x]'",__FILE__ . " linje " . __LINE__);
				}
			}
			$x=0;
			$q=db_select("select * from kassekladde where projekt != '' order by id",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)){
				$x++;
				$id[$x]=$r['id'];
				$projekt[$x]=$r['projekt'];
			}
			$antal=$x;
			for ($x=1;$x<=$antal;$x++) {
				if ($projekt[$x]) $q=db_modify("update openpost set projekt='$projekt[$x]' where bilag_id='$id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
		echo "opdaterer ver 3.1.$lap_nr til ver 3.1.6<br />";
		db_modify("UPDATE grupper set box1 = '3.1.6' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.1.6' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"7"){
		$nextver='3.1.7';
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
#			echo "opdaterer hovedregnskab fra $tmp til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
#		echo "opdaterer ver 3.1.$lap_nr til ver $nextver<br />";
		$antal=0;
		$q=db_select("select * from openpost where udlign_date < transdate order by konto_id",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$antal++;
			$op_id[$antal]=$r['id'];
			$op_bilag[$antal]=$r['refnr'];
			$op_kladde_id[$antal]=$r['kladde_id'];
			$op_transdate[$antal]=$r['transdate'];
			$op_udlign_date[$antal]=$r['udlign_date'];
			$op_amount[$antal]=$r['amount'];
			$op_beskrivelse[$antal]=addslashes($r['beskrivelse']);
		}
		for($x=1;$x<=$antal;$x++) {
			if ($op_amount[$x]>0) {
				$qtekst="select id from transaktioner where beskrivelse='$op_beskrivelse[$x]' and transdate='$op_udlign_date[$x]' and debet='$op_amount[$x]' and bilag='$op_bilag[$x]' and kladde_id='$op_kladde_id[$x]'";
			}	else {
				$kredit=$op_amount[$x]*-1;
				$qtekst="select id from transaktioner where beskrivelse='$op_beskrivelse[$x]' and transdate='$op_udlign_date[$x]' and kredit='$kredit' and bilag='$op_bilag[$x]' and kladde_id='$op_kladde_id[$x]'";
			}
#echo "$qtekst<br>";
			if ($r=db_fetch_array(db_select("$qtekst",__FILE__ . " linje " . __LINE__))) {
				$qtekst="UPDATE openpost set transdate = '$op_udlign_date[$x]', udlign_date='$op_transdate[$x]' where id ='$op_id[$x]'";
#echo "$qtekst<br>";
				db_modify("$qtekst",__FILE__ . " linje " . __LINE__);
				$message=$db." | ".$brugernavn." ".date("Y-m-d H:i:s")." | $qtekst";
				$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
				mail('fejl@saldi.dk', 'SALDI Opdater transdate openpost', $message, $headers);
			}
		}
		$antal=0;
		$q=db_select("select distinct(udlign_id) as udlign_id from openpost where udlign_id > '0' and udlignet ='0'",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$antal++;
			$op_udlign_id[$antal]=$r['udlign_id'];
		}
		for($x=1;$x<=$antal;$x++) {
			$r=db_fetch_array(db_select("select sum(amount) as sum from openpost where udlign_id = '$op_udlign_id[$x]'",__FILE__ . " linje " . __LINE__));
			$sum=$r['sum']*1;
			if (!$sum) {
				$qtekst="UPDATE openpost set udlignet='1' where udlign_id ='$op_udlign_id[$x]'";
				db_modify("$qtekst",__FILE__ . " linje " . __LINE__);
				$message=$db." | ".$brugernavn." ".date("Y-m-d H:i:s")." | $qtekst";
				$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
				mail('fejl@saldi.dk', 'SALDI Opdater udlignet openpost', $message, $headers);
			}
		}
		db_modify("CREATE INDEX ordrelinjer_ordreid_idx ON ordrelinjer (ordre_id)",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"8"){
		$nextver='3.1.8';
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
#			echo "opdaterer hovedregnskab fra $tmp til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
#		echo "opdaterer ver 3.1.$lap_nr til ver $nextver<br />";
		db_modify("CREATE INDEX transaktioner_id_idx ON transaktioner (id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX ordrelinjer_vareid_idx ON ordrelinjer (vare_id)",__FILE__ . " linje " . __LINE__);
		db_modify("update grupper set  box9='on' where art = 'POS'",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"9"){
		$nextver='3.1.9';
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<$nextver) {
#			echo "opdaterer hovedregnskab fra $tmp til ver $nextver<br />";
			db_modify("UPDATE regnskab set version = '$nextver' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
#		echo "opdaterer ver 3.1.$lap_nr til ver $nextver<br />";
		# dvrg = DebitorVareRabatGruppe
		db_modify("ALTER TABLE varer ADD column dvrg integer",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	$nextver='3.2.0';
	include("../includes/connect.php");
	$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
	$tmp=$r['version'];
	if ($tmp<$nextver) {
		db_modify("UPDATE regnskab set version = '$nextver' where id = 1",__FILE__ . " linje " . __LINE__);
	}
	include("../includes/online.php");
#		echo "opdaterer ver 3.1.$lap_nr til ver $nextver<br />";
	# dvrg = DebitorVareRabatGruppe
	db_modify("update grupper set  box10='on' where art = 'POS'",__FILE__ . " linje " . __LINE__);
	db_modify("UPDATE grupper set box1 = '$nextver' where art = 'VE'",__FILE__ . " linje " . __LINE__);
	include("../includes/connect.php");
	db_modify("UPDATE regnskab set version = '$nextver' where db = '$db'",__FILE__ . " linje " . __LINE__);
}

?>
