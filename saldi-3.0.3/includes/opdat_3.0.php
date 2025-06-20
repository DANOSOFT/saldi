<?php
// -------------------------- includes/opdat_2.0.php-------lap 3.0.3 ------2010.05.31---------------
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
		
		
		
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.0.3' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
}
?>
