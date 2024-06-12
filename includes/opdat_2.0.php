<?php
// -------------------------- includes/opdat_2.0.php-------lap 2.0.9b ------2009.07.28---------------
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
// Copyright (c) 2004-2009 Danosoft ApS
// ----------------------------------------------------------------------
function opdat_2_0($under_nr, $lap_nr){
	global $version;
	global $db;
	global $db_id;
	global $regnskab;
	global $regnaar;
	$s_id=session_id();
	
#	print "<body onLoad=\"javascript:alert('Systemet opdateres, det kan tage lidt tid')\">";
	if ($lap_nr<2){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		transaktion("begin"); 
		db_modify("UPDATE regnskab set version = '2.0.2' where id = 1",__FILE__ . " linje " . __LINE__);
		transaktion("commit");
		include("../includes/online.php");
		$filnavn="../temp/$db.sql";
		$fp=fopen($filnavn,"w");
		if ($fp) {
			fwrite($fp,"ALTER TABLE brugere ADD sprog_id integer;\n");
			fwrite($fp,"CREATE TABLE tekster (id serial NOT NULL, sprog_id integer, tekst_id integer, tekst varchar, PRIMARY KEY (id));\n");
			fwrite($fp,"CREATE TABLE jobkort (id serial NOT NULL, konto_id integer, kontonr varchar, firmanavn varchar, addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, kontakt varchar, tlf varchar, initdate date, oprettet_af varchar, startdate date, slutdate date, hvem varchar, tidspkt varchar, felt_1 varchar, felt_2 varchar, felt_3 varchar, felt_4 varchar, felt_5 varchar, felt_6 varchar, felt_7 varchar, felt_8 varchar, felt_9 varchar, felt_10 varchar, felt_11 varchar, PRIMARY KEY (id));\n");
			fwrite($fp,"CREATE TABLE jobkort_felter (id serial NOT NULL, job_id integer, art varchar, feltnr integer, subnr integer, feltnavn varchar, indhold varchar, PRIMARY KEY (id));\n");
			fclose($fp);
			system("export PGPASSWORD=$sqpass\npsql $db -h $sqhost -U $squser < $filnavn");
		} 	
		transaktion("begin"); 
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art) values ('Dansk', 'DA', '1', 'SPROG')",__FILE__ . " linje " . __LINE__);
		db_modify("update brugere set sprog_id = '1'",__FILE__ . " linje " . __LINE__);
/*		
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Dansk','1','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Vælg aktivt sprog','2','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Gem','3','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Nyt sprog','4','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Annuller','5','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Job nr.:','6','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Felt 1','7','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Felt 2','8','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Felt 3','9','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Felt 4','10','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Felt 5','11','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Felt 6','12','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Felt 7','13','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Felt 8','14','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Felt 9','15','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Felt 10','16','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Bemærkning 1','17','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Tabel felt 1','18','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Tabel felt 2','19','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Tabel felt 3','20','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Tabel felt 4','21','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Tabel felt 5','22','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Tabel felt 6','23','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Bemærkning 2','24','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Bemærkning 3','25','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Bemærkning 4','26','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Planlagt til uge:','27','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Firmanavn','28','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Jobkort','29','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Luk','30','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Nuværende tekst','31','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Ny tekst','32','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Skriv nyt tekstforslag','33','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Debitorliste','34','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Kunde','35','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Postnr','36','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Telefon','37','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Jobliste','38','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Ny','39','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Skriv 1 nummer for as søge på et enkelt nr. eller 2 numre adskilt af : (f.eks 23:44) for at søge i et interval','40','1')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into tekster (tekst,tekst_id,sprog_id) values ('Skriv en tekst eller en deltekst. Der kan anvendes * før og efter teksten','41','1')",__FILE__ . " linje " . __LINE__);
*/			
		db_modify("UPDATE grupper set box1 = '2.0.2' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion("commit");
		include("../includes/connect.php");
		transaktion("begin"); 
		db_modify("UPDATE regnskab set version = '2.0.2' where db = '$db'",__FILE__ . " linje " . __LINE__);
		transaktion("commit");
		include("../includes/online.php");
	}	
	if ($lap_nr<3){
		include("../includes/connect.php");
		$filnavn="../temp/$sqdb.sql";
		$fp=fopen($filnavn,"w");
		fwrite($fp,"CREATE TABLE tekster (id serial NOT NULL, sprog_id integer, tekst_id integer, tekst varchar, PRIMARY KEY (id));\n");
		fwrite($fp,"CREATE TABLE kundedata (id serial NOT NULL, firmanavn varchar, addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, kontakt varchar, email varchar, cvrnr varchar, regnskab varchar, brugernavn varchar, kodeord varchar, kontrol_id varchar, aktiv int, logtime varchar, PRIMARY KEY (id));\n");
		fwrite($fp,"ALTER TABLE kundedata ADD regnskab_id integer;\n");
		fclose($fp);
		system("export PGPASSWORD=$sqpass\npsql $sqdb -h $sqhost -U $squser < $filnavn > ../temp/NULL\n#rm $filnavn");
		db_modify("UPDATE regnskab set version = '2.0.3' where id = 1",__FILE__ . " linje " . __LINE__);
		$tmp=addslashes($regnskab);
		db_modify("UPDATE kundedata set regnskab_id = '$db_id' where regnskab = '$tmp'",__FILE__ . " linje " . __LINE__);
		include("../includes/online.php");
		$filnavn="../temp/$db.sql";
		$fp=fopen($filnavn,"w");
		if ($fp) {
			fwrite($fp,"ALTER TABLE kassekladde ADD forfaldsdate date;\n");
			fwrite($fp,"ALTER TABLE kassekladde ADD betal_id varchar;\n");
			fwrite($fp,"ALTER TABLE kassekladde ADD dokument varchar;\n");
			fwrite($fp,"ALTER TABLE tmpkassekl ADD forfaldsdate varchar;\n");
			fwrite($fp,"ALTER TABLE tmpkassekl ADD betal_id varchar;\n");
			fwrite($fp,"ALTER TABLE tmpkassekl ADD dokument varchar;\n");
			fwrite($fp,"ALTER TABLE openpost ADD forfaldsdate date;\n");
			fwrite($fp,"ALTER TABLE openpost ADD betal_id varchar;\n"); 
			fwrite($fp,"ALTER TABLE kontoplan ADD anvendelse varchar;\n"); #Om kontoen er en indtægts elle en udgiftskonto.
			fwrite($fp,"ALTER TABLE ordrer ADD email varchar;\n");
			fwrite($fp,"ALTER TABLE ordrer ADD mail_fakt varchar;\n");
			fwrite($fp,"ALTER TABLE historik ADD kontaktet date;\n");
			fwrite($fp,"ALTER TABLE historik ADD kontaktes date;\n");
			fclose($fp);
			system("export PGPASSWORD=$sqpass\npsql $db -h $sqhost -U $squser < $filnavn > ../temp/NULL\nrm $filnavn");
			db_modify("delete from grupper where art = 'USET'",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set beskrivelse = 'debitorgruppevisning', art = 'DGV' where art = 'HV'",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE grupper set box1 = '2.0.3' where art = 'VE'",__FILE__ . " linje " . __LINE__);
			include("../includes/connect.php");
			db_modify("UPDATE regnskab set version = '2.0.3' where db = '$db'",__FILE__ . " linje " . __LINE__);
			include("../includes/online.php");
		}
	}
	if ($lap_nr<4){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.0.4') db_modify("UPDATE regnskab set version = '2.0.4' where id = 1",__FILE__ . " linje " . __LINE__);
		include("../includes/online.php");
		$filnavn="../temp/$db.sql";
		$fp=fopen($filnavn,"w");
		echo "opdaterer ver 2.0.$lap_nr til ver 2.0.4<br>";
		db_modify("CREATE TABLE modtageliste (id serial NOT NULL, initdate date, modtagdate date, modtagnote varchar, modtaget varchar, init_af varchar, modtaget_af varchar, hvem varchar, tidspkt varchar, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE TABLE modtagelser (id serial NOT NULL, varenr varchar, beskrivelse varchar, leveres numeric, liste_id integer, lager numeric, ordre_id integer, vare_id integer, antal numeric, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box9 = 'on' where art = 'DIV'and kodenr = '2'",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '2.0.4' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.0.4' where db = '$db'",__FILE__ . " linje " . __LINE__);
		include("../includes/online.php");
	}
	if ($lap_nr<'5'){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.0.5') {
			echo "opdaterer hovedregnskab til ver 2.0.5<br>";
			db_modify("ALTER TABLE regnskab ADD brugerantal integer",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE regnskab set version = '2.0.5' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		db_modify("UPDATE regnskab set version = '2.0.5' where db = '$db'",__FILE__ . " linje " . __LINE__);
		include("../includes/online.php");
		echo "opdaterer ver 2.0.$lap_nr til ver 2.0.5<br>";
		db_modify("UPDATE grupper set box1 = '2.0.5' where art = 'VE'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<'6'){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.0.6') {
			echo "opdaterer hovedregnskab fra $tmp til ver 2.0.6<br>";
			db_modify("ALTER TABLE regnskab ADD posteret integer",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE regnskab ADD lukket varchar",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE regnskab ADD administrator varchar",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE regnskab set version = '2.0.6' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 2.0.$lap_nr til ver 2.0.6<br>";
		db_modify("CREATE TABLE ordretekster(id serial NOT NULL, tekst varchar, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '2.0.6' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.0.6' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<'7'){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.0.7') {
			echo "opdaterer hovedregnskab fra $tmp til ver 2.0.7<br>";
			db_modify("ALTER TABLE online ADD revisor boolean",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE regnskab set version = '2.0.7' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 2.0.$lap_nr til ver 2.0.7<br>";
		db_modify("CREATE TABLE pbs_kunder(id serial NOT NULL,konto_id integer,kontonr varchar(20), pbs_nr varchar(10),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE TABLE pbs_liste(id serial NOT NULL,liste_date date,afsendt varchar(8),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE TABLE pbs_ordrer(id serial NOT NULL,liste_id integer,ordre_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE TABLE pbs_linjer(id serial NOT NULL,liste_id integer,linje varchar(140),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD pbs varchar(2)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD mail varchar(2)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD mailfakt varchar(2)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER 	TABLE adresser ADD pbs varchar(2)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD pbs_nr varchar(10)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD pbs_date date",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE formularer ADD sprog varchar(250)",__FILE__ . " linje " . __LINE__);
		if (!$r=db_fetch_array(db_select("select * from grupper where art='DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__))) {
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('Div_valg','3','DIV','','','','','','','','','','')",__FILE__ . " linje " . __LINE__);
		} 
		$r=db_fetch_array(db_select("select * from grupper where art='DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
		db_modify("update grupper set box3='$r[box3]',box4='$r[box4]',box5='$r[box5]',box8='$r[box8]',box9='$r[box9]' where art = 'DIV' and kodenr = '3'");
#		db_modify("update grupper set box3='',box4='',box5='',box8='',box9='' where art = 'DIV' and kodenr = '2'");
		db_modify("UPDATE grupper set box1 = '2.0.7' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box2 = 'on' where art = 'USET'",__FILE__ . " linje " . __LINE__);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('Tilbud', 1, 5, 1, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('Hermed fremsendes tilbud', 1, 5, 2, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		$tmp='Ordrebekræftelse';
		if ($db_encode=="UTF8") $tmp=utf8_encode($tmp);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('$tmp', 2, 5, 1, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		$tmp='Hermed fremsendes ordrebekræftelse';
		if ($db_encode=="UTF8") $tmp=utf8_encode($tmp);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('$tmp', 2, 5, 2, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('Faktura', 4, 5, 1, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('Hermed fremsendes faktura', 4, 5, 2, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('Kreditnota', 5, 5, 1, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('Hermed fremsendes kreditnota', 5, 5, 2, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('Rykker', 6, 5, 1, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('Hermed fremsendes tilbud', 6, 5, 2, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('Rykker', 7, 5, 1, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('Hermed fremsendes tilbud', 7, 5, 2, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('Rykker', 8, 5, 1, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering, sprog) values ('Hermed fremsendes tilbud', 8, 5, 2, 0, 0, 0, 10, 0, '', '', '', '', '', 'Dansk')",__FILE__ . " linje " . __LINE__);
		/*		
		$x=0;
		$q=db_select("SELECT id,forfaldsdate from openpost where udlignet = '0' order by id",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array(q)) {
			if (!$r['forfaldadate']) {
				db_modify("UPDATE openpost set forfaldsdate = NULL where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			}
		}
*/		
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.0.7' where db = '$db'",__FILE__ . " linje " . __LINE__);
		print "<BODY onLoad=\"JavaScript:window.open('../doc/nyt_i_2.0.7.html','','statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,location=1');\">";
	}
	
	if ($lap_nr<"9"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.0.9') {
			echo "opdaterer hovedregnskab fra $tmp til ver 2.0.9<br>";
			db_modify("CREATE TABLE revisor(id serial NOT NULL,regnskabsaar integer,bruger_id integer,brugernavn varchar(500),db_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE regnskab set version = '2.0.9' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 2.0.$lap_nr til ver 2.0.8<br>";
		db_modify("ALTER TABLE historik ADD dokument varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD mail_cc varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD mail_bcc varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD mail_subj varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD mail_text varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD felt_1 varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD felt_2 varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD felt_3 varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD felt_4 varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD felt_5 varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD vis_lev_addr varchar(2)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD udskriv_til varchar(10)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrelinjer ADD projekt integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD felt_1 varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD felt_2 varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD felt_3 varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD felt_4 varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD felt_5 varchar(5000)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kontoplan ADD modkonto numeric",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '2.0.9' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.0.9' where db = '$db'",__FILE__ . " linje " . __LINE__);
print "<BODY onLoad=\"JavaScript:window.open('../doc/nyt_i_2.0.9.html','','statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,location=1');\">";
	}
	if ($under_nr<1){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		db_modify("UPDATE regnskab set version = '2.1.0' where id = 1",__FILE__ . " linje " . __LINE__);
		include("../includes/online.php");
		echo "opdaterer ver 2.0.$lap_nr til ver 2.1.0<br>";
		db_modify("ALTER TABLE varer ADD trademark text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD location text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD retail_price numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD special_price numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD campaign_cost numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD tier_price numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD open_colli_price numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD colli numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD outer_colli numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD outer_colli_price numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD special_from_date date",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD special_to_date date",__FILE__ . " linje " . __LINE__);
		
		db_modify("ALTER TABLE adresser ADD vis_lev_addr varchar(2)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD kontotype text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD fornavn text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD efternavn text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD lev_firmanavn text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD lev_fornavn text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD lev_efternavn text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD lev_addr1 text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD lev_addr2 text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD lev_postnr text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD lev_bynavn text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD lev_land text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD lev_kontakt text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD lev_tlf text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD lev_email text",__FILE__ . " linje " . __LINE__);
		
		db_modify("ALTER TABLE ordrer ADD betalt varchar(2)",__FILE__ . " linje " . __LINE__);
		
		db_modify("CREATE TABLE varetekster (id serial NOT NULL, sprog_id integer, vare_id integer, tekst text, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("update adresser set kontotype='erhverv'",__FILE__ . " linje " . __LINE__);
		db_modify("update ordrer set betalt = '' where art LIKE 'R%'",__FILE__ . " linje " . __LINE__);

		db_modify("UPDATE grupper set box1 = '2.1.0' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.1.0' where db = '$db'",__FILE__ . " linje " . __LINE__);
		print "<BODY onLoad=\"JavaScript:window.open('../doc/nyt_i_2.1.0.html','','statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,location=1');\">";
	}

#	db_modify("UPDATE regnskab set version = '$version' where db = '$db'",__FILE__ . " linje " . __LINE__);
}
?>
