<?php
// --------------------------------------------- includes/opdat_1.0.php-------lap 1.1.1 --------------------
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
// Copyright (c) 2004-2007 Danosoft ApS
// ----------------------------------------------------------------------
function opdat_1_0($under_nr, $lap_nr){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	$s_id=session_id();
	
	if ($lap_nr<2){
		transaktion("begin");
		$x=0;
/*		
		$query=db_select("SELECT id FROM formularer where formular = 6",__FILE__ . " linje " . __LINE__); 
		while ($row = db_fetch_array($query)) {$x++;}
		if ($x<=1) {
			 $fp=fopen("../importfiler/formular.txt","r");
			 if ($fp) {
				while (!feof($fp)) {
					list($formular, $art, $beskrivelse, $justering, $xa, $ya, $xb, $yb, $str, $color, $font, $fed, $kursiv, $side) = explode(chr(9), fgets($fp));
					if ($formular==6) {
						$justering=trim($justering); $form=trim($font); $fed=trim($fed); $kursiv=trim($kursiv); $side=trim($side);
						$xa= $xa*1; $ya= $ya*1; $xb= $xb*1; $yb=$yb*1; $str=$str*1; $color=$color*1;
						db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, justering, str, color, font, fed, kursiv, side) values ('$formular', '$art', '$beskrivelse', '$xa', '$ya', '$xb', '$yb', '$justering', '$str', '$color', '$font', '$fed', '$kursiv', '$side')",__FILE__ . " linje " . __LINE__); 
					}
				}
			}
			fclose($fp);
		}
*/		
		$query=db_select("SELECT id, box1 FROM grupper where art = 'DG'",__FILE__ . " linje " . __LINE__); 
		while ($row = db_fetch_array($query)) {
			if (strlen(trim($row['box1'])) ==1) {
				$box1='S'.trim($row['box1']);
				db_modify("UPDATE grupper set box1 = '$box1' where id = $row[id]",__FILE__ . " linje " . __LINE__);
			}
		}
		$query=db_select("SELECT id, box1 FROM grupper where art = 'KG'",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (strlen(trim($row['box1'])) ==1) {
				$box1='K'.trim($row['box1']);
				db_modify("UPDATE grupper set box1 = '$box1' where id = $row[id]",__FILE__ . " linje " . __LINE__);
			}
		}
		db_modify("ALTER TABLE kontoplan ADD genvej varchar",__FILE__ . " linje " . __LINE__);
		$x=0;
		$query=db_select("SELECT kodenr FROM grupper where art = 'LG' order by kodenr",__FILE__ . " linje " . __LINE__); 
		while ($row = db_fetch_array($query)) {
			$x++;
			$lagernr[$x]=$row[kodenr];
		}
		$lagerantal=$x;
		$x=0;
		$query=db_select("SELECT id FROM varer order by id",__FILE__ . " linje " . __LINE__); 
		while ($row = db_fetch_array($query)) {
			$x++;
			$vare_id[$x]=$row[id];
		}
		$vareantal=$x;
		for ($y=1; $y<=$lagerantal; $y++) {
			for ($x=1; $x<=$vareantal; $x++) {
				$z=0;
				$query=db_select("SELECT rest FROM batch_kob where vare_id=$vare_id[$x] and lager=$lagernr[$y]",__FILE__ . " linje " . __LINE__); 
				while ($row = db_fetch_array($query)) $z=$z+$row[rest];
				db_modify("UPDATE lagerstatus set beholdning=$z where vare_id = $x and lager = $y",__FILE__ . " linje " . __LINE__);
			}
		}
		db_modify("UPDATE grupper set box1 = '1.0.2' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion("commit"); 
	}
	if ($lap_nr<=6){
		transaktion("begin");
		db_modify("ALTER TABLE adresser ADD kontoansvarlig integer",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '1.0.7' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion("commit"); 
	}
	if ($lap_nr<=7){
		include("../includes/connect.php");
		include("../includes/online.php");
		$filnavn="../temp/$db.sql";
		$fp=fopen($filnavn,"w");
		fwrite($fp,"CREATE TABLE openpost (id serial NOT NULL, konto_id integer, konto_nr varchar, faktnr varchar, amount numeric, refnr integer, beskrivelse varchar, udlignet varchar, transdate date, kladde_id integer, bilag_id integer,forfaldsdate date,betal_id varchar, PRIMARY KEY (id));\n");
		fclose($fp);
echo "<br>export PGPASSWORD=$sqpass\npsql $db -h $sqhost -U $squser < $filnavn > ../temp/NULL\n<br>";		
		system("export PGPASSWORD=$sqpass\npsql $db -h $sqhost -U $squser < $filnavn > ../temp/NULL\n");
transaktion('begin');
		db_modify("ALTER TABLE openpost ADD udlign_id integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE openpost ADD udlign_date date",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE openpost SET udlign_id = '0'",__FILE__ . " linje " . __LINE__);
		include("../includes/autoudlign.php");
		autoudlign('0');
		db_modify("UPDATE grupper set box1 = '1.0.8' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
	}
	if ($lap_nr<=8){
		transaktion('begin');
		db_modify("ALTER TABLE grupper ADD box9 varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE grupper ADD box10 varchar",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE TABLE provision (id serial NOT NULL, gruppe_id integer, ansat_id integer, provision numeric, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '1.0.9' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
	}
	if ($lap_nr<=9){
		transaktion('begin');
		db_modify("ALTER TABLE varer ADD komplementaer varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD circulate integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD operation integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrelinjer ADD kostpris numeric",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrelinjer ADD samlevare varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE materialer ADD materialenr varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE materialer ADD tykkelse numeric",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE materialer ADD kgpris numeric",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE materialer ADD avance numeric",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE materialer ADD enhed varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE materialer ADD opdat_date date",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE materialer ADD opdat_time time",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ansatte ADD nummer integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ansatte ADD loen numeric",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ansatte ADD hold integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ansatte ADD lukket varchar",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE ansatte set lukket = ''",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan DROP md01",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan DROP md02",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan DROP md03",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan DROP md04",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan DROP md05",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan DROP md06",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan DROP md07",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan DROP md08",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan DROP md09",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan DROP md10",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan DROP md11",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan DROP md12",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan ADD saldo numeric",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan ADD overfor_til numeric",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE TABLE tidsreg (id serial NOT NULL, person integer, ordre integer, pnummer integer, operation integer, materiale integer, tykkelse numeric, laengde numeric, bredde numeric, antal_plader numeric,  gaa_hjem integer, tid integer, forbrugt_tid integer, opsummeret_tid integer, beregnet integer, pause integer, antal numeric,  faerdig integer, circ_time integer, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE TABLE tmpkassekl (id integer, lobenr integer, bilag varchar, transdate varchar, beskrivelse varchar, d_type varchar, debet varchar, k_type varchar, kredit varchar, faktura varchar, amount varchar, kladde_id integer, momsfri varchar, afd varchar)",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box9 = 'on' where box8 = 'on' and art = 'VG'",__FILE__ . " linje " . __LINE__);
		include("../includes/genberegn.php");
		$query=db_select("SELECT kodenr FROM grupper where art = 'RA' order by kodenr",__FILE__ . " linje " . __LINE__); 
		while ($row = db_fetch_array($query)) genberegn($row[kodenr]);	 
		db_modify("UPDATE grupper set box1 = '1.1.0' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
		# Husk opret.php.... (internt notat)
	}
	db_modify("UPDATE grupper set box1 ='$version' where art = 'VE'",__FILE__ . " linje " . __LINE__);
}
?>
