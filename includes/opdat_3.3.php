<?php
// ------ includes/opdat_3.3.php-------lap 3.3.1 ------2013-07-05---------------
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
// Copyright (c) 2004-2013 Danosoft ApS
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
}
?>
