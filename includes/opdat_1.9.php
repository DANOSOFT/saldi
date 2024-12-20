<?php
// -------------------------- includes/opdat_1.9.php-------lap 1.9.2a ------25.03.2008----------
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
// Copyright (c) 2004-2008 Danosoft ApS
// ----------------------------------------------------------------------
function opdat_1_9($under_nr, $lap_nr){
	global $version;
	global $regnaar;
	$s_id=session_id();

	if ($lap_nr<2){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		if ($r[version] < '1.1.2') {
			transaktion("begin"); 
			db_modify("UPDATE regnskab set version = '1.9.2' where id = 1",__FILE__ . " linje " . __LINE__);
			transaktion("commit");
		}
		include("../includes/online.php");
		$filnavn="../temp/$db.sql";
		$fp=fopen($filnavn,"w");
		if ($fp) {
			fwrite($fp,"drop table betalinger;\n");
			fwrite($fp,"drop table betalingsliste;\n");
			fwrite($fp,"ALTER TABLE adresser DROP bank_fi;\n");
			fwrite($fp,"ALTER TABLE adresser DROP swift;\n");
			fwrite($fp,"ALTER TABLE adresser DROP erh;\n");
			fwrite($fp,"ALTER TABLE openpost DROP bilag_id;\n");
			fwrite($fp,"ALTER TABLE kassekladde ADD ordre_id integer;\n");
		fclose($fp);
		system("export PGPASSWORD=$sqpass\npsql $db -h $sqhost -U $squser < $filnavn");
		}
		transaktion("begin");
		db_modify("ALTER TABLE adresser ADD bank_fi varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD swift varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD erh varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE openpost ADD bilag_id integer",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE TABLE betalingsliste (id serial NOT NULL, listedate date, udskriftsdate date, listenote varchar, bogfort varchar, oprettet_af varchar, bogfort_af varchar, hvem varchar, tidspkt varchar, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE TABLE betalinger (id serial NOT NULL, bet_type varchar, fra_kto varchar, egen_ref varchar, til_kto varchar, modt_navn varchar, belob varchar, betalingsdato varchar, valuta varchar,kort_ref varchar, kvittering varchar, ordre_id integer, bilag_id integer, liste_id integer, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		$x=0;
		$q=db_select("select * from openpost where udlignet=0 and amount<0 order by id",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$x++;
			$id[$x]=$r['id'];
			$konto_nr[$x]=$r['konto_nr'];
			$faktnr[$x]=$r['faktnr'];
			$refnr[$x]=$r['refnr'];
			$amount[$x]=$r['amount']*-1;
			$transdate[$x]=$r['transdate'];
			$kladde_id[$x]=$r['kladde_id'];
		} 
		$antal=$x;
		for($x=1;$x<=$antal;$x++) {
			if ($r=db_fetch_array(db_select("select id from kassekladde where k_type='K' and kredit='$konto_nr[$x]' and bilag='$refnr[$x]' and faktura='$faktnr[$x]' and kladde_id='$kladde_id[$x]' and transdate='$transdate[$x]' and amount='$amount[$x]' order by id",__FILE__ . " linje " . __LINE__))) {
				db_modify("update openpost set bilag_id = '$r[id]' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
			}	
			elseif ($r=db_fetch_array(db_select("select id from kassekladde where k_type='D' and kredit='$konto_nr[$x]' and bilag='$refnr[$x]' and faktura='$faktnr[$x]' and kladde_id='$kladde_id[$x]' and transdate='$transdate[$x]' and amount='$amount[$x]' order by id",__FILE__ . " linje " . __LINE__))) {
				db_modify("update openpost set bilag_id = '$r[id]' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
			}	
		}
		$x=0;
		$q=db_select("select * from openpost where udlignet=0 and amount>0 order by id",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$x++;			
			$id[$x]=$r['id'];
			$konto_nr[$x]=$r['konto_nr'];
			$faktnr[$x]=$r['faktnr'];
			$refnr[$x]=$r['refnr'];
			$amount[$x]=$r['amount'];
			$transdate[$x]=$r['transdate'];
			$kladde_id[$x]=$r['kladde_id'];
		} 
		$antal=$x;
		for($x=1;$x<=$antal;$x++) {
			if ($r=db_fetch_array(db_select("select id from kassekladde where d_type='K' and debet='$konto_nr[$x]' and bilag='$refnr[$x]' and faktura='$faktnr[$x]' and kladde_id='$kladde_id[$x]' and transdate='$transdate[$x]' and amount='$amount[$x]' order by id",__FILE__ . " linje " . __LINE__))) {
				db_modify("update openpost set bilag_id = '$r[id]' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
			}	
			elseif ($r=db_fetch_array(db_select("select id from kassekladde where d_type='D' and debet='$konto_nr[$x]' and bilag='$refnr[$x]' and faktura='$faktnr[$x]' and kladde_id='$kladde_id[$x]' and transdate='$transdate[$x]' and amount='$amount[$x]' order by id",__FILE__ . " linje " . __LINE__))) {
				db_modify("update openpost set bilag_id = '$r[id]' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
			}	
		}
		
		db_modify("UPDATE grupper set box1 = '1.9.2' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion("commit");
	}	
}
?>
