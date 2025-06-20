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
	global $regnaar;
	
	if ($lap_nr<2){
		transaktion("begin");
		$x=0;
		$query=db_select("SELECT id FROM formularer where formular = 6"); 
		while ($row = db_fetch_array($query)) {$x++;}
		if ($x<=1) {
			 $fp=fopen("../importfiler/formular.txt","r");
			 if ($fp) {
				while (!feof($fp)) {
					list($formular, $art, $beskrivelse, $placering, $xa, $ya, $xb, $yb, $str, $color, $font, $fed, $kursiv, $side) = split(chr(9), fgets($fp));
					if ($formular==6) {
						$placering=trim($placering); $form=trim($font); $fed=trim($fed); $kursiv=trim($kursiv); $side=trim($side);
						$xa= $xa*1; $ya= $ya*1; $xb= $xb*1; $yb=$yb*1; $str=$str*1; $color=$color*1;
						db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, placering, str, color, font, fed, kursiv, side) values ('$formular', '$art', '$beskrivelse', '$xa', '$ya', '$xb', '$yb', '$placering', '$str', '$color', '$font', '$fed', '$kursiv', '$side')"); 
					}
				}
			}
			fclose($fp);
		}
		$query=db_select("SELECT id, box1 FROM grupper where art = 'DG'"); 
		while ($row = db_fetch_array($query)) {
			if (strlen(trim($row['box1'])) ==1) {
				$box1='S'.trim($row['box1']);
				db_modify("UPDATE grupper set box1 = '$box1' where id = $row[id]");
			}
		}
		$query=db_select("SELECT id, box1 FROM grupper where art = 'KG'");
		while ($row = db_fetch_array($query)) {
			if (strlen(trim($row['box1'])) ==1) {
				$box1='K'.trim($row['box1']);
				db_modify("UPDATE grupper set box1 = '$box1' where id = $row[id]");
			}
		}
		db_modify("ALTER TABLE kontoplan ADD genvej varchar");
		$x=0;
		$query=db_select("SELECT kodenr FROM grupper where art = 'LG' order by kodenr"); 
		while ($row = db_fetch_array($query)) {
			$x++;
			$lagernr[$x]=$row[kodenr];
		}
		$lagerantal=$x;
		$x=0;
		$query=db_select("SELECT id FROM varer order by id"); 
		while ($row = db_fetch_array($query)) {
			$x++;
			$vare_id[$x]=$row[id];
		}
		$vareantal=$x;
		for ($y=1; $y<=$lagerantal; $y++) {
			for ($x=1; $x<=$vareantal; $x++) {
				$z=0;
				$query=db_select("SELECT rest FROM batch_kob where vare_id=$vare_id[$x] and lager=$lagernr[$y]"); 
				while ($row = db_fetch_array($query)) $z=$z+$row[rest];
				db_modify("UPDATE lagerstatus set beholdning=$z where vare_id = $x and lager = $y");
			}
		}
		db_modify("UPDATE grupper set box1 = '1.0.2' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($lap_nr<=6){
		transaktion("begin");
		db_modify("ALTER TABLE adresser ADD kontoansvarlig integer");
		db_modify("UPDATE grupper set box1 = '1.0.7' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($lap_nr<=7){
		transaktion('begin');
		db_modify("ALTER TABLE openpost ADD udlign_id integer");
		db_modify("ALTER TABLE openpost ADD udlign_date date");
		db_modify("UPDATE openpost SET udlign_id = '0'");
		include("../includes/autoudlign.php");
		autoudlign('0');
		db_modify("UPDATE grupper set box1 = '1.0.8' where art = 'VE'");
		transaktion('commit');
	}
	if ($lap_nr<=8){
		transaktion('begin');
		db_modify("ALTER TABLE grupper ADD box9 varchar");
		db_modify("ALTER TABLE grupper ADD box10 varchar");
		db_modify("CREATE TABLE provision (id serial NOT NULL, gruppe_id integer, ansat_id integer, provision numeric, PRIMARY KEY (id))");
		db_modify("UPDATE grupper set box1 = '1.0.9' where art = 'VE'");
		transaktion('commit');
	}
	if ($lap_nr<=9){
		transaktion('begin');
		db_modify("ALTER TABLE varer ADD komplementaer varchar");
		db_modify("ALTER TABLE varer ADD circulate integer");
		db_modify("ALTER TABLE varer ADD operation integer");
		db_modify("ALTER TABLE ordrelinjer ADD kostpris numeric");
		db_modify("ALTER TABLE ordrelinjer ADD samlevare varchar");
		db_modify("ALTER TABLE materialer ADD materialenr varchar");
		db_modify("ALTER TABLE materialer ADD tykkelse numeric");
		db_modify("ALTER TABLE materialer ADD kgpris numeric");
		db_modify("ALTER TABLE materialer ADD avance numeric");
		db_modify("ALTER TABLE materialer ADD enhed varchar");
		db_modify("ALTER TABLE materialer ADD opdat_date date");
		db_modify("ALTER TABLE materialer ADD opdat_time time");
		db_modify("ALTER TABLE ansatte ADD nummer integer");
		db_modify("ALTER TABLE ansatte ADD loen numeric");
		db_modify("ALTER TABLE ansatte ADD hold integer");
		db_modify("ALTER TABLE ansatte ADD lukket varchar");
		db_modify("UPDATE ansatte set lukket = ''");
		db_modify("ALTER TABLE kontoplan DROP md01");
		db_modify("ALTER TABLE kontoplan DROP md02");
		db_modify("ALTER TABLE kontoplan DROP md03");
		db_modify("ALTER TABLE kontoplan DROP md04");
		db_modify("ALTER TABLE kontoplan DROP md05");
		db_modify("ALTER TABLE kontoplan DROP md06");
		db_modify("ALTER TABLE kontoplan DROP md07");
		db_modify("ALTER TABLE kontoplan DROP md08");
		db_modify("ALTER TABLE kontoplan DROP md09");
		db_modify("ALTER TABLE kontoplan DROP md10");
		db_modify("ALTER TABLE kontoplan DROP md11");
		db_modify("ALTER TABLE kontoplan DROP md12");
		db_modify("ALTER TABLE kontoplan ADD saldo numeric");
		db_modify("ALTER TABLE kontoplan ADD overfor_til numeric");
		db_modify("CREATE TABLE tidsreg (id serial NOT NULL, person integer, ordre integer, pnummer integer, operation integer, materiale integer, tykkelse numeric, laengde numeric, bredde numeric, antal_plader numeric,  gaa_hjem integer, tid integer, forbrugt_tid integer, opsummeret_tid integer, beregnet integer, pause integer, antal numeric,  faerdig integer, circ_time integer, PRIMARY KEY (id))");
		db_modify("CREATE TABLE tmpkassekl (id integer, lobenr integer, bilag varchar, transdate varchar, beskrivelse varchar, d_type varchar, debet varchar, k_type varchar, kredit varchar, faktura varchar, amount varchar, kladde_id integer, momsfri varchar, afd varchar)");
		db_modify("UPDATE grupper set box9 = 'on' where box8 = 'on' and art = 'VG'");
		include("../includes/genberegn.php");
		$query=db_select("SELECT kodenr FROM grupper where art = 'RA' order by kodenr"); 
		while ($row = db_fetch_array($query)) genberegn($row[kodenr]);	 
		db_modify("UPDATE grupper set box1 = '1.1.0' where art = 'VE'");
		transaktion('commit');
		# Husk opret.php.... (internt notat)
	}
	db_modify("UPDATE grupper set box1 ='$version' where art = 'VE'");
}
?>
