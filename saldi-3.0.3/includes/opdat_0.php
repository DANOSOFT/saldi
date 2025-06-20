<?php
// --------------------------------------------- includes/opdat_0.php-------lap 1.1.1 --------------------
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

function opdat_0($version, $dbver){
	global $db_id;
	global $s_id;
	global $backup;
	
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/db_query.php");
		
	$returside="../index/logud.php";

	$db=trim($db);

	if (!$backup) {

		system("pg_dump -h $sqhost -U $squser -W $sqpass -f '../temp/$db.sql' $db");

		print "<table align=center valign=center cellpadding=1 cellspacing=1 border=0	height=100% width=100%><tbody>";
		print "<tr><td>";
		print "<table align=center valign=center cellpadding=1 cellspacing=1 border=0	height=25% width=50%><tbody>";

		print "<tr><td align=center>$font Systemet opdateres . . . . . .<br></td></tr>";
		print "<tr><td align=center>$font Backup foretages . . . . . .<br></td></tr>";
		print "<tr><td align=center><br></td></tr>";
		print "<tr><td align=center>$font H&oslash;jreklik her: <a href='../temp/$db.sql'>$db.sql</a></td></tr>";
		print "<tr><td align=center>$font V&aelig;lg \"gem link som\" (eller \"save link as\")</td></tr>";
		print "<tr><td align=center>$font og gem backup'en et passende sted</td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td align=center><a href=../includes/opdat_0.php?version=$version&dbver=$dbver&backup=OK accesskey=F>$font Forts&aelig;t</a><br></td></tr>";

		print "</tbody></table>";
		print "</td></tr>";
		print "</tbody></table>";

		print "<br>";
	}

	if (!$dbver) {
		echo "Variablen dbver ikke sat - kontakt systemansvarlig!!";
		exit;
	}	
	
	if ($dbver<0.23) {
		transaktion("begin");
		db_modify("ALTER TABLE kladdeliste add bogforingsdate date");
		db_modify("UPDATE grupper set box1 = '0.23' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($dbver<0.24){
		transaktion("begin");
		$x=0;
		$maxval=0;
		$query = db_select("SELECT * FROM ordrelinjer");
		while($row = db_fetch_array($query))
		{
			$x++;
			$id[$x]=$row[id];
			$varenr[$x]=$row['varenr'];
			$text[$x]=$row['text'];
			$posnr[$x]=$row['posnr']*1;
			$pris[$x]=$row['pris']*1;
			$rabat[$x]=$row['rabat']*1;
			$lev_varenr[$x]=$row['lev_varenr'];
			$ordre_id[$x]=$row['ordre_id']*1;
			$serienr[$x]=$row['serienr'];
			$antal[$x]=$row['antal']*1;
			$bogf_konto[$x]=$row['bogf_konto']*1;
			if ($maxval<$id[$x]){$maxval=$id[$x];}
		}
		$linjeantal=$x;
		db_modify("DROP TABLE ordrelinjer");

		db_modify("CREATE TABLE ordrelinjer (id serial NOT NULL, varenr varchar, text r varchar, posnr smallint, pris numeric, rabat numeric, lev_varenr varchar, ordre_id integer, serienr varchar, antal numeric, bogf_konto integer, PRIMARY KEY (id))");
		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("INSERT INTO ordrelinjer (id, varenr, text, posnr, pris, rabat, lev_varenr, ordre_id, serienr, antal, bogf_konto) values ($id[$x], '$varenr[$x]', '$text[$x]', '$posnr[$x]', '$pris[$x]', '$rabat[$x]', '$lev_varenr[$x]', '$ordre_id[$x]', '$serienr[$x]', '$antal[$x]', '$bogf_konto[$x]')");
		}
		if ($maxval >= 1) {db_modify("select pg_catalog.setval('ordrelinjer_id_seq', $maxval, true)");}
		db_modify("CREATE TABLE batch_kob (id serial NOT NULL, kobsdate date, vare_id integer, ordre_id integer, pris numeric, antal integer, rest integer, PRIMARY KEY (id))");
		db_modify("CREATE TABLE batch_salg (id serial NOT NULL, salgsdate date, batch_kob_id integer, vare_id integer, ordre_id integer, pris numeric, antal integer, PRIMARY KEY (id))");
		db_modify("CREATE TABLE serienr (id serial NOT NULL, vare_id integer, kobsordre_id integer, salgsordre_id integer, serienr varchar, PRIMARY KEY (id))");
		db_modify("UPDATE grupper set box1 = '0.24' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($dbver<0.26) {
		transaktion("begin");
		db_modify("ALTER TABLE ordrelinjer ADD vare_id integer");
		db_modify("ALTER TABLE ordrer ADD kred_ord_id integer");
		db_modify("UPDATE grupper set box1 = '0.26' where art = 'VE'");
		transaktion("commit"); 
		 
	}
 
	if ($dbver<0.27){
		transaktion("begin");
		print "Opdaterer til ver. 0.27<br>";
		db_modify("ALTER TABLE varer ADD samlevare varchar");
		db_modify("ALTER TABLE varer ADD delvare varchar");
		db_modify("ALTER TABLE ordrer ADD lev_adr text");
		db_modify("CREATE TABLE styklister (id serial NOT NULL, vare_id integer, indgaar_i integer, antal integer, PRIMARY KEY (id))");
		db_modify("UPDATE grupper set box1 = '0.27' where art = 'VE'");
		transaktion("commit"); 
		
	}
	if ($dbver<0.29){
		transaktion("begin");
		print "Opdaterer til ver. 0.29<br>";
		db_modify("ALTER TABLE brugere ADD rettigheder varchar");
		db_modify("UPDATE brugere set rettigheder='1111111111'");
		db_modify("ALTER TABLE styklister ADD posnr integer");
		transaktion("commit");
		include("../includes/connect.php");
		$query = db_select("SELECT * FROM online");
		$row = db_fetch_array($query);
		if (!$row[rettigheder])	{db_modify("ALTER TABLE online ADD rettigheder varchar");}
		include("../includes/online.php");
		transaktion("begin");
		db_modify("UPDATE grupper set box1 = '0.29' where art = 'VE'");
		transaktion("commit"); 
		 
	} 
	if ($dbver<0.31){
		transaktion("begin");
	 print "Opdaterer til ver. 0.31<br>";
		db_modify("ALTER TABLE varer ADD enhed varchar");
		db_modify("ALTER TABLE varer ADD enhed2 varchar");
		db_modify("ALTER TABLE varer ADD forhold numeric");
		db_modify("ALTER TABLE ordrelinjer ADD enhed varchar");
		db_modify("CREATE TABLE enheder (id serial NOT NULL, betegnelse varchar, beskrivelse varchar, PRIMARY KEY (id))");
		db_modify("INSERT into enheder (betegnelse, beskrivelse) values ('m', 'meter')");
		db_modify("INSERT into enheder (betegnelse, beskrivelse) values ('m2', 'kvadratmeter')");
		db_modify("INSERT into enheder (betegnelse, beskrivelse) values ('m3', 'kubikmeter')");
		db_modify("INSERT into enheder (betegnelse, beskrivelse) values ('l', 'liter')");
		db_modify("INSERT into enheder (betegnelse, beskrivelse) values ('kg', 'kilogram')");
		db_modify("INSERT into enheder (betegnelse, beskrivelse) values ('stk', 'styk')");
		db_modify("CREATE TABLE materialer (id serial NOT NULL, beskrivelse varchar, densitet numeric, PRIMARY KEY (id))");
		db_modify("CREATE TABLE vare_lev (id serial NOT NULL, posnr integer, lev_id integer, vare_id integer, lev_varenr varchar, kostpris numeric, PRIMARY KEY (id))");
		$x=0;
		$query = db_select("SELECT * FROM varer where leverandor > 0");
		while($row = db_fetch_array($query)){
			$x++;
			$id[$x]=$row[id];
			$leverandor[$x]=$row[leverandor];
			$lev_varenr[$x]=$row['lev_varenr'];
			$kostpris[$x]=$row['kostpris'];
		}
		$linjeantal=$x;
		for ($x=1; $x<=$linjeantal; $x++){
			db_modify("INSERT into vare_lev (lev_id, vare_id, lev_varenr, kostpris) values ($leverandor[$x], $id[$x], '$lev_varenr[$x]', '$kostpris[$x]')");
		}
		db_modify("UPDATE grupper set box1 = '0.31' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($dbver<0.33){
		transaktion("begin");
	 print "Opdaterer til ver. 0.33<br>";
		db_modify("CREATE TABLE ansatte (id serial NOT NULL, adr_id integer, navn varchar, addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, tlf varchar, fax varchar, mobil varchar, email varchar, notes text, cprnr varchar, posnr integer, PRIMARY KEY (id))");
		$x=0;
		$query = db_select("SELECT * FROM adresser order by id");
		while($row = db_fetch_array($query)){
			if ($row[kontakt]){
				$x++;
				$adr_id[$x]=$row[id];
				$navn[$x]=$row['kontakt'];
			}
		}
		$linjeantal=$x;
		for ($x=1; $x<=$linjeantal; $x++){
			db_modify("INSERT into ansatte (adr_id, navn) values ($adr_id[$x], '$navn[$x]')");
		}
		db_modify("UPDATE grupper set box1 = '0.33' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($dbver<0.35){
		transaktion("begin");
		print "Opdaterer til ver. 0.35<br>";
		db_modify("ALTER TABLE ordrer DROP ordrenr");
		db_modify("ALTER TABLE ordrer ADD ordrenr integer");
		$x=0;
		$maxval=0;
		$query = db_select("SELECT id FROM ordrer order by id");
		while($row = db_fetch_array($query)){
			$x++;
			$id[$x]=$row[id];
			if ($maxval<$id[$x]){$maxval=$id[$x];}
		}
		$linjeantal=$x;
		for ($x=1; $x<=$linjeantal; $x++){
			db_modify("UPDATE ordrer set ordrenr = '$id[$x]' where id = $id[$x]");
		}
		if ($maxval >= 1) {db_modify("select pg_catalog.setval ('ordrer_id_seq', $maxval, true)");}
		db_modify("ALTER TABLE ordrelinjer ADD leveret integer");
		db_modify("ALTER TABLE batch_kob ADD linje_id integer");
		db_modify("ALTER TABLE batch_salg ADD linje_id integer");
		db_modify("UPDATE grupper set box1 = '0.35' where art = 'VE'");
		transaktion("commit"); 

	}
	if ($dbver<0.39){
		transaktion("begin");
		print "Opdaterer til ver. 0.39<br>";
		$x=0;
		$maxval=0;
		$query = db_select("SELECT * FROM ordrelinjer");
		while($row = db_fetch_array($query)){
			$x++;
			$id[$x]=$row[id];
			$antal[$x]=$row['antal']*1;
			$leveres[$x]=$row['leveret']*1;
			if ($maxval<$id[$x]){$maxval=$id[$x];}
		}
		$linjeantal=$x;

		db_modify("ALTER TABLE ordrelinjer DROP antal");
		db_modify("ALTER TABLE ordrelinjer DROP leveret");
		db_modify("ALTER TABLE ordrelinjer ADD antal numeric");
		db_modify("ALTER TABLE ordrelinjer ADD leveres numeric");

		for ($x=1; $x<=$linjeantal; $x++){
			db_modify("UPDATE ordrelinjer set antal='$antal[$x]', leveres='$leveres[$x]' where id=$id[$x]");
		}
		if ($maxval >= 1) {db_modify("select pg_catalog.setval ('ordrelinjer_id_seq', $maxval, true)");}

		$x=0;
		$maxval=0;
		$query = db_select("SELECT * FROM batch_kob");
		while($row = db_fetch_array($query)){
			$x++;
			$id[$x]=$row[id];
			$antal[$x]=$row['antal']*1;
			$rest[$x]=$row['rest']*1;
			if ($maxval<$id[$x]){$maxval=$id[$x];}
		}
		$linjeantal=$x;
		db_modify("ALTER TABLE batch_kob DROP antal");
		db_modify("ALTER TABLE batch_kob DROP rest");
		db_modify("ALTER TABLE batch_kob ADD antal numeric");
		db_modify("ALTER TABLE batch_kob ADD rest numeric");

		for ($x=1; $x<=$linjeantal; $x++){
			db_modify("UPDATE batch_kob set antal='$antal[$x]', rest='$rest[$x]' where id=$id[$x]");
		}
		if ($maxval >= 1) {db_modify("select pg_catalog.setval ('batch_kob_id_seq', $maxval, true)");}

		$x=0;
		$maxval=0;
		$query = db_select("SELECT * FROM batch_salg");
		while($row = db_fetch_array($query)){
			$x++;
			$id[$x]=$row[id];
			$antal[$x]=$row['antal']*1;
			if ($maxval<$id[$x]){$maxval=$id[$x];}
		}
		$linjeantal=$x;
		db_modify("ALTER TABLE batch_salg DROP antal");
		db_modify("ALTER TABLE batch_salg ADD antal numeric");

		for ($x=1; $x<=$linjeantal; $x++){
			db_modify("UPDATE batch_salg set antal='$antal[$x]' where id=$id[$x]");
		}
		if ($maxval >= 1) {db_modify("select pg_catalog.setval ('batch_salg_id_seq', $maxval, true)");}

		$x=0;
		$maxval=0;
		$query = db_select("SELECT * FROM varer");
		while($row = db_fetch_array($query)){
			$x++;
			$id[$x]=$row[id];
			$beholdning[$x]=$row['beholdning']*1;
			if ($maxval<$id[$x]){$maxval=$id[$x];}
		}
		$linjeantal=$x;
		db_modify("ALTER TABLE varer DROP beholdning");
		db_modify("ALTER TABLE varer ADD beholdning numeric");

		for ($x=1; $x<=$linjeantal; $x++){
			db_modify("UPDATE varer set beholdning='$beholdning[$x]' where id=$id[$x]");
		}
		if ($maxval >= 1) {db_modify("select pg_catalog.setval ('varer_id_seq', $maxval, true)");}

		$x=0;
		$maxval=0;
		$query = db_select("SELECT * FROM styklister");
		while($row = db_fetch_array($query)){
			$x++;
			$id[$x]=$row[id];
			$antal[$x]=$row['antal']*1;
			if ($maxval<$id[$x]){$maxval=$id[$x];}
		}
		$linjeantal=$x;
		
		db_modify("ALTER TABLE styklister DROP antal");
		db_modify("ALTER TABLE styklister ADD antal numeric");

		for ($x=1; $x<=$linjeantal; $x++){
			db_modify("UPDATE styklister set antal='$antal[$x]' where id=$id[$x]");
		}
		if ($maxval >= 1) {db_modify("select pg_catalog.setval ('styklister_id_seq', $maxval, true)");}

		db_modify("DROP TABLE serienr");
		db_modify("CREATE TABLE serienr (id serial NOT NULL, vare_id integer, kobslinje_id integer, salgslinje_id integer, batch_kob_id integer, batch_salg_id integer, serienr varchar, PRIMARY KEY (id))");
		db_modify("UPDATE grupper set box1 = '0.39' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($dbver<0.41){
		transaktion("begin");
		print "Opdaterer til ver. 0.41<br>";
		db_modify("ALTER TABLE adresser ADD bank_navn varchar");
		db_modify("ALTER TABLE adresser ADD bank_reg varchar");
		db_modify("ALTER TABLE adresser ADD bank_konto varchar");
		 
		db_modify("ALTER TABLE ordrer ADD kundeordnr varchar");
		db_modify("ALTER TABLE ordrer ADD	lev_navn varchar");
		db_modify("ALTER TABLE ordrer ADD	lev_addr1 varchar");
		db_modify("ALTER TABLE ordrer ADD	lev_addr2 varchar");
		db_modify("ALTER TABLE ordrer ADD	lev_postnr varchar");
		db_modify("ALTER TABLE ordrer ADD	lev_bynavn varchar");
		db_modify("ALTER TABLE ordrer ADD lev_kontakt varchar");
	
		db_modify("ALTER TABLE batch_salg ADD lev_nr integer");
	 
		$x=0;
		$maxval=0;
		$query = db_select("SELECT * FROM grupper");
		while($row = db_fetch_array($query)){
			$x++;
			$id[$x]=$row[id];
			$beskrivelse[$x]=$row['beskrivelse'];
			$kode[$x]=$row['kode'];
			$kodenr[$x]=$row['kodenr'];
			$art[$x]=$row['art'];
			$box1[$x]=$row['box1'];
			$box2[$x]=$row['box2'];
			$box3[$x]=$row['box3'];
			$box4[$x]=$row['box4'];
			$box5[$x]=$row['box5'];
			$box6[$x]=$row['box6'];
			$box7[$x]=$row['box7'];
			$box8[$x]=$row['box8'];
			if ($maxval<$id[$x]){$maxval=$id[$x];}
		}
		$linjeantal=$x;
		db_modify("DROP TABLE grupper");
		db_modify("CREATE TABLE grupper (id serial NOT NULL, beskrivelse varchar, kode varchar,	kodenr varchar, art varchar, box1 varchar, box2 varchar, box3 varchar, box4 varchar, box5 varchar, box6 varchar, box7 varchar, box8 varchar, PRIMARY KEY (id))");
		for ($x=1; $x<=$linjeantal; $x++){
			db_modify("INSERT INTO grupper (id, beskrivelse, kode, kodenr , art, box1, box2, box3, box4, box5, box6, box7, box8) values ($id[$x], '$beskrivelse[$x]', '$kode[$x]', '$kodenr[$x]' ,'$art[$x]', '$box1[$x]', '$box2[$x]', '$box3[$x]', '$box4[$x]','$box5[$x]', '$box6[$x]', '$box7[$x]', '$box8[$x]')");
		}
		if ($maxval >= 1) {db_modify("select pg_catalog.setval ('grupper_id_seq', $maxval, true)");}
		
		db_modify("INSERT INTO grupper (beskrivelse, kodenr , art, box1,	box3) values ('Tilbud','1' ,'PV', 'lpr', 'on')");
		db_modify("INSERT INTO grupper (beskrivelse, kodenr , art, box1,	box3) values ('Ordrebekr�telse','2' ,'PV', 'lpr', 'on')");
		db_modify("INSERT INTO grupper (beskrivelse, kodenr , art, box1,	box3) values ('Flgeseddel','3' ,'PV', 'lpr', 'on')");
		db_modify("INSERT INTO grupper (beskrivelse, kodenr , art, box1,	box3) values ('Faktura', '4' ,'PV', 'lpr', 'on')");
		db_modify("UPDATE grupper set box1 = '0.41' where art = 'VE'");
		transaktion("commit"); 
}
 if ($dbver<0.42){
		transaktion("begin");
		print "Opdaterer til ver. 0.42<br>";
		 $x=0;
		 $query = db_select("SELECT id, kontonr FROM adresser");
		while($row = db_fetch_array($query)){
			$x++;
			$id[$x]=$row[id];
			$kontonr[$x]=$row[kontonr];
		}
		$linjeantal=$x;
		db_modify("ALTER TABLE adresser DROP kontonr");
		db_modify("ALTER TABLE adresser ADD kontonr varchar");
		
		for ($x=1; $x<=$linjeantal; $x++){
			db_modify("UPDATE adresser set kontonr = '$kontonr[$x]' where id = $id[$x]");
		}
		db_modify("ALTER TABLE ansatte RENAME COLUMN adr_id TO konto_id");
		db_modify("UPDATE grupper set box1 = '0.42' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($dbver<0.421){
		transaktion("begin");
		db_modify("ALTER TABLE adresser ADD ean varchar");
		db_modify("ALTER TABLE adresser ADD institution varchar");
		db_modify("UPDATE grupper set box1 = '0.421' where art = 'VE'");
		transaktion("commit"); 
 }
	if ($dbver<0.422){
		transaktion("begin");
		db_modify("ALTER TABLE ordrer ADD ean varchar");
		db_modify("ALTER TABLE ordrer ADD institution varchar");
		db_modify("UPDATE grupper set box1 = '0.422' where art = 'VE'");
		transaktion("commit"); 
	}
		if ($dbver<0.431){
		transaktion("begin");
		db_modify("ALTER TABLE adresser ADD land varchar");
		db_modify("ALTER TABLE ordrer ADD land varchar");
		db_modify("UPDATE grupper set box1 = '0.431' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($dbver<0.432){
		transaktion("begin");
		db_modify("ALTER TABLE adresser ADD web varchar");
		db_modify("UPDATE grupper set box1 = '0.432' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($dbver<0.433){
		 transaktion("begin");
		db_modify("ALTER TABLE varer ADD min_lager numeric");
		db_modify("UPDATE grupper set box1 = '0.433' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($dbver<0.434){
		transaktion("begin");
		db_modify("ALTER TABLE varer ADD max_lager numeric");
		$x=0;
		$query = db_select("SELECT * FROM brugere");
		while($row = db_fetch_array($query))
		{
			$x++;
			$id[$x]=$row[id];
			$kode[$x]=md5(trim($row['kode']));
		}	
		$linjeantal=$x;
		db_modify("ALTER TABLE brugere DROP kode");
		db_modify("ALTER TABLE brugere ADD kode varchar");
		for ($x=1; $x<=$linjeantal; $x++)
		{
			db_modify("UPDATE brugere set kode = '$kode[$x]' where id = $id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.434' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($dbver<0.435){ 
		transaktion("begin");
		$x=0;
		$query = db_select("SELECT id, text FROM ordrelinjer");
		while($row = db_fetch_array($query))
		{
			$x++;
			$id[$x]=$row[id];
			$text[$x]=$row[text];
		}
		$linjeantal=$x;
		db_modify("ALTER TABLE ordrelinjer DROP text");
		db_modify("ALTER TABLE ordrelinjer ADD text text");
		
		for ($x=1; $x<=$linjeantal; $x++){
			db_modify("UPDATE ordrelinjer set text = '$text[$x]' where id = $id[$x]");
		}
	 db_modify("UPDATE grupper set box1 = '0.435' where art = 'VE'");
		transaktion("commit"); 
	 }
	if ($dbver<0.436){
		transaktion("begin");
		db_modify("ALTER TABLE kontoplan DROP jan");
		db_modify("ALTER TABLE kontoplan DROP feb");
		db_modify("ALTER TABLE kontoplan DROP mar");
		db_modify("ALTER TABLE kontoplan DROP apr");
		db_modify("ALTER TABLE kontoplan DROP jun");
		db_modify("ALTER TABLE kontoplan DROP jul");
		db_modify("ALTER TABLE kontoplan DROP aug");
		db_modify("ALTER TABLE kontoplan DROP sep");
		db_modify("ALTER TABLE kontoplan DROP okt");
		db_modify("ALTER TABLE kontoplan DROP nov");
		db_modify("ALTER TABLE kontoplan DROP dec");
		db_modify("ALTER TABLE kontoplan ADD md01 numeric");
		db_modify("ALTER TABLE kontoplan ADD md02 numeric");
		db_modify("ALTER TABLE kontoplan ADD md03 numeric");
		db_modify("ALTER TABLE kontoplan ADD md04 numeric");
		db_modify("ALTER TABLE kontoplan ADD md05 numeric");
		db_modify("ALTER TABLE kontoplan ADD md06 numeric");
		db_modify("ALTER TABLE kontoplan ADD md07 numeric");
		db_modify("ALTER TABLE kontoplan ADD md08 numeric");
		db_modify("ALTER TABLE kontoplan ADD md09 numeric");
		db_modify("ALTER TABLE kontoplan ADD md10 numeric");
		db_modify("ALTER TABLE kontoplan ADD md11 numeric");
		db_modify("ALTER TABLE kontoplan ADD md12 numeric");
		transaktion("commit"); 
		db_modify("UPDATE grupper set box1 = '0.436' where art = 'VE'");
 }
 if ($dbver<0.501){
		transaktion("begin");
		$x=0;
		$query = db_select("SELECT id, debet, kredit FROM kassekladde");
		while($row = db_fetch_array($query))
		{
			$x++;
			$id[$x]=$row[id];
			$debet[$x]=$row[debet];
			$kredit[$x]=$row[kredit];
			if (!$debet[$x]) {$debet[$x]='0';}
			if (!$kredit[$x]) {$kredit[$x]='0';}
			
		}
		$linjeantal=$x;
		db_modify("ALTER TABLE kassekladde DROP debet");
		db_modify("ALTER TABLE kassekladde DROP kredit");
		db_modify("ALTER TABLE kassekladde ADD debet numeric");
		db_modify("ALTER TABLE kassekladde ADD kredit numeric");
		
		for ($x=1; $x<=$linjeantal; $x++){
			db_modify("UPDATE kassekladde set debet = '$debet[$x]', kredit = '$kredit[$x]' where id = $id[$x]");
		}
	 	db_modify("UPDATE grupper set box1 = '0.501' where art = 'VE'");
 
		transaktion("commit"); 
 }
 if ($dbver<0.502){
	transaktion("begin");
	db_modify("ALTER TABLE ordrer ADD modtagelse integer");
#	include("../includes/genberegn.php");
	 $query = db_select("SELECT * FROM grupper where art='RA' order by kodenr");
#	 while($row = db_fetch_array($query)) {genberegn($row[kodenr]);}	
	 db_modify("UPDATE grupper set box1 = '0.502' where art = 'VE'");
	transaktion("commit"); 
 }
 if ($dbver<0.601){
	 transaktion("begin");
	 db_modify("ALTER TABLE kladdeliste ADD oprettet_af varchar");
	 db_modify("ALTER TABLE kladdeliste ADD bogfort_af varchar");
	 db_modify("ALTER TABLE kladdeliste ADD hvem varchar");
	 db_modify("ALTER TABLE kladdeliste ADD tidspkt varchar");
	 db_modify("UPDATE grupper set box1 = '0.601' where art = 'VE'");
	transaktion("commit"); 
 }
	if ($dbver<0.602){
	 transaktion("begin");
	 db_modify("ALTER TABLE ordrer ADD oprettet_af varchar");
	 db_modify("ALTER TABLE ordrer ADD bogfort_af varchar");
	 db_modify("ALTER TABLE ordrer ADD hvem varchar");
	 db_modify("ALTER TABLE ordrer ADD tidspkt varchar");
	 db_modify("UPDATE grupper set box1 = '0.602' where art = 'VE'");
	 transaktion("commit"); 
 }
	if ($dbver<0.603){
		transaktion("begin");
		$x=0;
		$query = db_select("SELECT * FROM adresser");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$firmanavn[$x]=addslashes(trim($row['firmanavn']));
			$addr1[$x]=addslashes(trim($row['addr1']));
			$addr2[$x]=addslashes(trim($row['addr2']));
			$postnr[$x]=addslashes(trim($row['postnr']));
			$bynavn[$x]=addslashes(trim($row['bynavn']));
			$kontakt[$x]=addslashes(trim($row['kontakt']));
			$tlf[$x]=addslashes(trim($row['tlf']));
			$fax[$x]=addslashes(trim($row['fax']));
			$email[$x]=addslashes(trim($row['email']));
			$notes[$x]=addslashes(trim($row['notes']));
			$cvrnr[$x]=addslashes(trim($row['cvrnr']));
			$art[$x]=addslashes(trim($row['art']));
			$bank_navn[$x]=addslashes(trim($row['bank_navn']));
			$bank_reg[$x]=addslashes(trim($row['bank_reg']));
			$bank_konto[$x]=addslashes(trim($row['bank_konto']));
			$kontonr[$x]=addslashes(trim($row['kontonr']));
			$ean[$x]=addslashes(trim($row['ean']));
			$institution[$x]=addslashes(trim($row['institution']));
			$land[$x]=addslashes(trim($row['land']));
			$web[$x]=addslashes(trim($row['web']));
		}
		$linjeantal=$x;
		
		db_modify("ALTER TABLE adresser DROP firmanavn");
		db_modify("ALTER TABLE adresser DROP addr1");
		db_modify("ALTER TABLE adresser DROP addr2");
		db_modify("ALTER TABLE adresser DROP postnr");
		db_modify("ALTER TABLE adresser DROP bynavn");
		db_modify("ALTER TABLE adresser DROP kontakt");
		db_modify("ALTER TABLE adresser DROP tlf");
		db_modify("ALTER TABLE adresser DROP fax");
		db_modify("ALTER TABLE adresser DROP email");
		db_modify("ALTER TABLE adresser DROP notes");
		db_modify("ALTER TABLE adresser DROP cvrnr");
		db_modify("ALTER TABLE adresser DROP art");
		db_modify("ALTER TABLE adresser DROP bank_navn");
		db_modify("ALTER TABLE adresser DROP bank_reg");
		db_modify("ALTER TABLE adresser DROP bank_konto");
		db_modify("ALTER TABLE adresser DROP kontonr");
		db_modify("ALTER TABLE adresser DROP ean");
		db_modify("ALTER TABLE adresser DROP institution");
		db_modify("ALTER TABLE adresser DROP land");
		db_modify("ALTER TABLE adresser DROP web");
 
		db_modify("ALTER TABLE adresser ADD firmanavn varchar");
		db_modify("ALTER TABLE adresser ADD addr1 varchar");
		db_modify("ALTER TABLE adresser ADD addr2 varchar");
		db_modify("ALTER TABLE adresser ADD postnr varchar");
		db_modify("ALTER TABLE adresser ADD bynavn varchar");
		db_modify("ALTER TABLE adresser ADD kontakt varchar");
		db_modify("ALTER TABLE adresser ADD tlf varchar");
		db_modify("ALTER TABLE adresser ADD fax varchar");
		db_modify("ALTER TABLE adresser ADD email varchar");
		db_modify("ALTER TABLE adresser ADD notes varchar");
		db_modify("ALTER TABLE adresser ADD cvrnr varchar");
		db_modify("ALTER TABLE adresser ADD art varchar");
		db_modify("ALTER TABLE adresser ADD bank_navn varchar");
		db_modify("ALTER TABLE adresser ADD bank_reg varchar");
		db_modify("ALTER TABLE adresser ADD bank_konto varchar");
		db_modify("ALTER TABLE adresser ADD kontonr varchar");
		db_modify("ALTER TABLE adresser ADD ean varchar");
		db_modify("ALTER TABLE adresser ADD institution varchar");
		db_modify("ALTER TABLE adresser ADD land varchar");
		db_modify("ALTER TABLE adresser ADD web varchar");

		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE adresser set firmanavn = '$firmanavn[$x]',	addr1 = '$addr1[$x]',	addr2 = '$addr2[$x]', postnr = '$postnr[$x]', bynavn = '$bynavn[$x]', kontakt = '$kontakt[$x]', tlf = '$tlf[$x]', fax = '$fax[$x]', email = '$email[$x]', notes = '$notes[$x]', cvrnr = '$cvrnr[$x]', art = '$art[$x]', bank_navn = '$bank_navn[$x]', bank_reg = '$bank_reg[$x]', bank_konto = '$bank_konto[$x]', kontonr = '$kontonr[$x]', ean = '$ean[$x]', institution = '$institution[$x]', land = '$land[$x]', web = '$web[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.603' where art = 'VE'");
		transaktion("commit"); 
 	}
 	if ($dbver<0.604) {
		transaktion("begin");
		
		$x=0;
		$query = db_select("SELECT * FROM ansatte");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$navn[$x]=addslashes(trim($row['navn']));
			$addr1[$x]=addslashes(trim($row['addr1']));
			$addr2[$x]=addslashes(trim($row['addr2']));
			$postnr[$x]=addslashes(trim($row['postnr']));
			$bynavn[$x]=addslashes(trim($row['bynavn']));
			$tlf[$x]=addslashes(trim($row['tlf']));
			$fax[$x]=addslashes(trim($row['fax']));
			$mobil[$x]=addslashes(trim($row['mobil']));
			$email[$x]=addslashes(trim($row['email']));
			$notes[$x]=addslashes(trim($row['notes']));
			$cprnr[$x]=addslashes(trim($row['cprnr']));
		}
		$linjeantal=$x;
 
		db_modify("ALTER TABLE ansatte DROP navn");
		db_modify("ALTER TABLE ansatte DROP addr1");
		db_modify("ALTER TABLE ansatte DROP addr2");
		db_modify("ALTER TABLE ansatte DROP postnr");
		db_modify("ALTER TABLE ansatte DROP bynavn");
		db_modify("ALTER TABLE ansatte DROP tlf");
		db_modify("ALTER TABLE ansatte DROP fax");
		db_modify("ALTER TABLE ansatte DROP mobil");
		db_modify("ALTER TABLE ansatte DROP email");
		db_modify("ALTER TABLE ansatte DROP notes");
		db_modify("ALTER TABLE ansatte DROP cprnr");
 
		db_modify("ALTER TABLE ansatte ADD navn varchar");
		db_modify("ALTER TABLE ansatte ADD addr1 varchar");
		db_modify("ALTER TABLE ansatte ADD addr2 varchar");
		db_modify("ALTER TABLE ansatte ADD postnr varchar");
		db_modify("ALTER TABLE ansatte ADD bynavn varchar");
		db_modify("ALTER TABLE ansatte ADD tlf varchar");
		db_modify("ALTER TABLE ansatte ADD fax varchar");
		db_modify("ALTER TABLE ansatte ADD mobil varchar");
		db_modify("ALTER TABLE ansatte ADD email varchar");
		db_modify("ALTER TABLE ansatte ADD notes varchar");
		db_modify("ALTER TABLE ansatte ADD cprnr varchar");

		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE ansatte set navn = '$navn[$x]', addr1 = '$addr1[$x]',	addr2 = '$addr2[$x]', postnr = '$postnr[$x]', bynavn = '$bynavn[$x]', tlf = '$tlf[$x]', fax = '$fax[$x]', mobil = '$mobil[$x]', email = '$email[$x]', notes = '$notes[$x]', cprnr = '$cprnr[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.604' where art = 'VE'");
		transaktion("commit"); 
 }
 if ($dbver<0.605) {
		$id=array();
		$brugernavn=array();
		$status=array();
		$rettigheder=array();
		$kode=array();
		
		transaktion("begin");
		$x=0;
		$query = db_select("SELECT * FROM brugere");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$brugernavn[$x]=addslashes(trim($row['brugernavn']));
			$status[$x]=addslashes(trim($row['status']));
			$rettigheder[$x]=addslashes(trim($row['rettigheder']));
			$kode[$x]=addslashes(trim($row['kode']));
		}
		$linjeantal=$x;
 
		db_modify("ALTER TABLE brugere DROP brugernavn");
		db_modify("ALTER TABLE brugere DROP status");
		db_modify("ALTER TABLE brugere DROP rettigheder");
		db_modify("ALTER TABLE brugere DROP kode");
 
		db_modify("ALTER TABLE brugere ADD brugernavn varchar");
		db_modify("ALTER TABLE brugere ADD status varchar");
		db_modify("ALTER TABLE brugere ADD rettigheder varchar");
		db_modify("ALTER TABLE brugere ADD kode varchar");

		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE brugere set brugernavn = '$brugernavn[$x]', status = '$status[$x]',	rettigheder = '$rettigheder[$x]', kode = '$kode[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.605' where art = 'VE'");
		transaktion("commit");  	
 	}
 	if ($dbver<0.606) {
		transaktion("begin");
	
		$x=0;
		$query = db_select("SELECT * FROM enheder");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$betegnelse[$x]=addslashes(trim($row['betegnelse']));
			$beskrivelse[$x]=addslashes(trim($row['beskrivelse']));
		}
		$linjeantal=$x;
 
		db_modify("ALTER TABLE enheder DROP betegnelse");
		db_modify("ALTER TABLE enheder DROP beskrivelse");
 
		db_modify("ALTER TABLE enheder ADD betegnelse varchar");
		db_modify("ALTER TABLE enheder ADD beskrivelse varchar");

		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE enheder set betegnelse = '$betegnelse[$x]', beskrivelse = '$beskrivelse[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.606' where art = 'VE'");
		transaktion("commit"); 
 	}
 	if ($dbver<0.607)	{
		transaktion("begin");
		
		$x=0;
		$query = db_select("SELECT * FROM grupper");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$beskrivelse[$x]=addslashes(trim($row['beskrivelse']));
			$kode[$x]=addslashes(trim($row['kode']));
			$kodenr[$x]=addslashes(trim($row['kodenr']));
			$art[$x]=addslashes(trim($row['art']));
			$box1[$x]=addslashes(trim($row['box1']));
			$box2[$x]=addslashes(trim($row['box2']));
			$box3[$x]=addslashes(trim($row['box3']));
			$box4[$x]=addslashes(trim($row['box4']));
			$box5[$x]=addslashes(trim($row['box5']));
			$box6[$x]=addslashes(trim($row['box6']));
			$box7[$x]=addslashes(trim($row['box7']));
			$box8[$x]=addslashes(trim($row['box8']));
		}
		$linjeantal=$x;
 
		db_modify("ALTER TABLE grupper DROP beskrivelse");
		db_modify("ALTER TABLE grupper DROP kode");
		db_modify("ALTER TABLE grupper DROP kodenr");
		db_modify("ALTER TABLE grupper DROP art");
		db_modify("ALTER TABLE grupper DROP box1");
		db_modify("ALTER TABLE grupper DROP box2");
		db_modify("ALTER TABLE grupper DROP box3");
		db_modify("ALTER TABLE grupper DROP box4");
		db_modify("ALTER TABLE grupper DROP box5");
		db_modify("ALTER TABLE grupper DROP box6");
		db_modify("ALTER TABLE grupper DROP box7");
		db_modify("ALTER TABLE grupper DROP box8");
 
		db_modify("ALTER TABLE grupper ADD beskrivelse varchar");
		db_modify("ALTER TABLE grupper ADD kode varchar");
		db_modify("ALTER TABLE grupper ADD kodenr varchar");
		db_modify("ALTER TABLE grupper ADD art varchar");
		db_modify("ALTER TABLE grupper ADD box1 varchar");
		db_modify("ALTER TABLE grupper ADD box2 varchar");
		db_modify("ALTER TABLE grupper ADD box3 varchar");
		db_modify("ALTER TABLE grupper ADD box4 varchar");
		db_modify("ALTER TABLE grupper ADD box5 varchar");
		db_modify("ALTER TABLE grupper ADD box6 varchar");
		db_modify("ALTER TABLE grupper ADD box7 varchar");
		db_modify("ALTER TABLE grupper ADD box8 varchar");

		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE grupper set beskrivelse = '$beskrivelse[$x]', kode = '$kode[$x]', kodenr = '$kodenr[$x]', art = '$art[$x]', box1 = '$box1[$x]', box2 = '$box2[$x]', box3 = '$box3[$x]', box4 = '$box4[$x]', box5 = '$box5[$x]', box6 = '$box6[$x]', box7 = '$box7[$x]', box8 = '$box8[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.607' where art = 'VE'");
		transaktion("commit"); 
 	}
 	if ($dbver<0.608) {
		transaktion("begin");
		
		$x=0;
		$query = db_select("SELECT * FROM kassekladde");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$beskrivelse[$x]=addslashes(trim($row['text']));
			$d_type[$x]=addslashes(trim($row['d_type']));
			$k_type[$x]=addslashes(trim($row['k_type']));
			$momsfri[$x]=addslashes(trim($row['momsfri']));
		 }
		$linjeantal=$x;
 
		db_modify("ALTER TABLE kassekladde DROP text");
		db_modify("ALTER TABLE kassekladde DROP d_type");
		db_modify("ALTER TABLE kassekladde DROP k_type");
		db_modify("ALTER TABLE kassekladde DROP momsfri");
 
		db_modify("ALTER TABLE kassekladde ADD beskrivelse varchar");
		db_modify("ALTER TABLE kassekladde ADD d_type varchar");
		db_modify("ALTER TABLE kassekladde ADD k_type varchar");
		db_modify("ALTER TABLE kassekladde ADD momsfri varchar");

		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE kassekladde set beskrivelse = '$beskrivelse[$x]', d_type = '$d_type[$x]', k_type = '$k_type[$x]', momsfri = '$momsfri[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.608' where art = 'VE'");
		transaktion("commit"); 
 	}
	if ($dbver<0.609) {
		transaktion("begin");
	
 		$x=0;
		$query = db_select("SELECT * FROM kontoplan");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$beskrivelse[$x]=addslashes(trim($row['beskrivelse']));
			$kontotype[$x]=addslashes(trim($row['kontotype']));
			$kontonr[$x]=addslashes(trim($row['kontonr']));
			$moms[$x]=addslashes(trim($row['moms']));
			$fra_kto[$x]=addslashes(trim($row['fra_kto']));
			$til_kto[$x]=addslashes(trim($row['til_kto']));
			$lukket[$x]=addslashes(trim($row['lukket']));
		}
		$linjeantal=$x;
 
		db_modify("ALTER TABLE kontoplan DROP beskrivelse");
		db_modify("ALTER TABLE kontoplan DROP kontotype");
		db_modify("ALTER TABLE kontoplan DROP kontonr");
		db_modify("ALTER TABLE kontoplan DROP moms");
		db_modify("ALTER TABLE kontoplan DROP fra_kto");
		db_modify("ALTER TABLE kontoplan DROP til_kto");
		db_modify("ALTER TABLE kontoplan DROP lukket");
 
		db_modify("ALTER TABLE kontoplan ADD beskrivelse varchar");
		db_modify("ALTER TABLE kontoplan ADD kontotype varchar");
		db_modify("ALTER TABLE kontoplan ADD kontonr varchar");
		db_modify("ALTER TABLE kontoplan ADD moms varchar");
		db_modify("ALTER TABLE kontoplan ADD fra_kto varchar");
		db_modify("ALTER TABLE kontoplan ADD til_kto varchar");
		db_modify("ALTER TABLE kontoplan ADD lukket varchar");

		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE kontoplan set beskrivelse = '$beskrivelse[$x]', kontotype = '$kontotype[$x]',	kontonr = '$kontonr[$x]', moms = '$moms[$x]', fra_kto = '$fra_kto[$x]', til_kto = '$til_kto[$x]', lukket = '$lukket[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.609' where art = 'VE'");
		transaktion("commit"); 
 	}
 	if ($dbver<0.610) {
		transaktion("begin");
	
		$x=0; 
		$query = db_select("SELECT * FROM materialer");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$beskrivelse[$x]=addslashes(trim($row['beskrivelse']));
		 }
		$linjeantal=$x;
 
		db_modify("ALTER TABLE materialer DROP beskrivelse");
 
		db_modify("ALTER TABLE materialer ADD beskrivelse varchar");
 
		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE materialer set beskrivelse = '$beskrivelse[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.610' where art = 'VE'");
		transaktion("commit"); 
 	}
	if ($dbver<0.611) {
		transaktion("begin");
	
		$x=0;
		$query = db_select("SELECT * FROM openpost");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$beskrivelse[$x]=addslashes(trim($row['text']));
			$konto_nr[$x]=addslashes(trim($row['konto_nr']));
			$faktnr[$x]=addslashes(trim($row['faktnr']));
			$udlignet[$x]=addslashes(trim($row['udlignet']));
		}
		$linjeantal=$x;
 
		db_modify("ALTER TABLE openpost DROP text");
		db_modify("ALTER TABLE openpost DROP konto_nr");
		db_modify("ALTER TABLE openpost DROP faktnr");
		db_modify("ALTER TABLE openpost DROP udlignet");
 
		db_modify("ALTER TABLE openpost ADD beskrivelse varchar");
		db_modify("ALTER TABLE openpost ADD konto_nr varchar");
		db_modify("ALTER TABLE openpost ADD faktnr varchar");
		db_modify("ALTER TABLE openpost ADD udlignet varchar");

		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE openpost set beskrivelse = '$beskrivelse[$x]', konto_nr = '$konto_nr[$x]', faktnr = '$faktnr[$x]', udlignet = '$udlignet[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.611' where art = 'VE'");
		transaktion("commit"); 
 	}
 	if ($dbver<0.612) {
		transaktion("begin");
		
		$x=0;
		$query = db_select("SELECT * FROM ordrelinjer");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$beskrivelse[$x]=addslashes(trim($row['text']));
			$varenr[$x]=addslashes(trim($row['varenr']));
			$enhed[$x]=addslashes(trim($row['enhed']));
			$lev_varenr[$x]=addslashes(trim($row['lev_varenr']));
			$serienr[$x]=addslashes(trim($row['serienr']));
		}
		$linjeantal=$x;
 
		db_modify("ALTER TABLE ordrelinjer DROP text");
		db_modify("ALTER TABLE ordrelinjer DROP varenr");
		db_modify("ALTER TABLE ordrelinjer DROP enhed");
		db_modify("ALTER TABLE ordrelinjer DROP lev_varenr");
		db_modify("ALTER TABLE ordrelinjer DROP serienr");
 
		db_modify("ALTER TABLE ordrelinjer ADD beskrivelse varchar");
		db_modify("ALTER TABLE ordrelinjer ADD varenr varchar");
		db_modify("ALTER TABLE ordrelinjer ADD enhed varchar");
		db_modify("ALTER TABLE ordrelinjer ADD lev_varenr varchar");
		db_modify("ALTER TABLE ordrelinjer ADD serienr varchar");

		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE ordrelinjer set beskrivelse = '$beskrivelse[$x]', varenr = '$varenr[$x]', enhed = '$enhed[$x]', lev_varenr = '$lev_varenr[$x]', serienr = '$serienr[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.612' where art = 'VE'");
		transaktion("commit"); 
 	}
	if ($dbver<0.613) {
		transaktion("begin");
		
		$x=0;
		$query = db_select("SELECT * FROM ordrer");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$firmanavn[$x]=addslashes(trim($row['firmanavn']));
			$addr1[$x]=addslashes(trim($row['addr1']));
			$addr2[$x]=addslashes(trim($row['addr2']));
			$postnr[$x]=addslashes(trim($row['postnr']));
			$bynavn[$x]=addslashes(trim($row['bynavn']));
			$kontakt[$x]=addslashes(trim($row['kontakt']));
			$cvrnr[$x]=addslashes(trim($row['cvrnr']));
			$art[$x]=addslashes(trim($row['art']));
			$kontonr[$x]=addslashes(trim($row['kontonr']));
			$ean[$x]=addslashes(trim($row['ean']));
			$institution[$x]=addslashes(trim($row['institution']));
			$land[$x]=addslashes(trim($row['land']));
			$ref[$x]=addslashes(trim($row['ref']));
			$fakturanr[$x]=addslashes(trim($row['fakturanr']));
			$kundeordnr[$x]=addslashes(trim($row['kundeordnr']));
			$lev_navn[$x]=addslashes(trim($row['lev_navn']));
			$lev_addr1[$x]=addslashes(trim($row['lev_addr1']));
			$lev_addr2[$x]=addslashes(trim($row['lev_addr2']));
			$lev_postnr[$x]=addslashes(trim($row['lev_postnr']));
			$lev_bynavn[$x]=addslashes(trim($row['lev_bynavn']));
			$lev_kontakt[$x]=addslashes(trim($row['lev_kontakt']));
		}
		$linjeantal=$x;
		
		db_modify("ALTER TABLE ordrer DROP firmanavn");
		db_modify("ALTER TABLE ordrer DROP addr1");
		db_modify("ALTER TABLE ordrer DROP addr2");
		db_modify("ALTER TABLE ordrer DROP postnr");
		db_modify("ALTER TABLE ordrer DROP bynavn");
		db_modify("ALTER TABLE ordrer DROP kontakt");
		db_modify("ALTER TABLE ordrer DROP cvrnr");
		db_modify("ALTER TABLE ordrer DROP art");
		db_modify("ALTER TABLE ordrer DROP kontonr");
		db_modify("ALTER TABLE ordrer DROP ean");
		db_modify("ALTER TABLE ordrer DROP institution");
		db_modify("ALTER TABLE ordrer DROP land");
		db_modify("ALTER TABLE ordrer DROP ref");
		db_modify("ALTER TABLE ordrer DROP fakturanr");
		db_modify("ALTER TABLE ordrer DROP kundeordnr");
		db_modify("ALTER TABLE ordrer DROP lev_navn");
		db_modify("ALTER TABLE ordrer DROP lev_addr1");
		db_modify("ALTER TABLE ordrer DROP lev_addr2");
		db_modify("ALTER TABLE ordrer DROP lev_postnr");
		db_modify("ALTER TABLE ordrer DROP lev_bynavn");
		db_modify("ALTER TABLE ordrer DROP lev_kontakt");
 
		db_modify("ALTER TABLE ordrer ADD firmanavn varchar");
		db_modify("ALTER TABLE ordrer ADD addr1 varchar");
		db_modify("ALTER TABLE ordrer ADD addr2 varchar");
		db_modify("ALTER TABLE ordrer ADD postnr varchar");
		db_modify("ALTER TABLE ordrer ADD bynavn varchar");
		db_modify("ALTER TABLE ordrer ADD kontakt varchar");
		db_modify("ALTER TABLE ordrer ADD cvrnr varchar");
		db_modify("ALTER TABLE ordrer ADD art varchar");
		db_modify("ALTER TABLE ordrer ADD kontonr varchar");
		db_modify("ALTER TABLE ordrer ADD ean varchar");
		db_modify("ALTER TABLE ordrer ADD institution varchar");
		db_modify("ALTER TABLE ordrer ADD land varchar");
		db_modify("ALTER TABLE ordrer ADD ref varchar");
		db_modify("ALTER TABLE ordrer ADD fakturanr varchar");
		db_modify("ALTER TABLE ordrer ADD kundeordnr varchar");
		db_modify("ALTER TABLE ordrer ADD lev_navn varchar");
		db_modify("ALTER TABLE ordrer ADD lev_addr1 varchar");
		db_modify("ALTER TABLE ordrer ADD lev_addr2 varchar");
		db_modify("ALTER TABLE ordrer ADD lev_postnr varchar");
		db_modify("ALTER TABLE ordrer ADD lev_bynavn varchar");
		db_modify("ALTER TABLE ordrer ADD lev_kontakt varchar");

		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE ordrer set firmanavn = '$firmanavn[$x]', addr1 = '$addr1[$x]',	addr2 = '$addr2[$x]', postnr = '$postnr[$x]', bynavn = '$bynavn[$x]', kontakt = '$kontakt[$x]', cvrnr = '$cvrnr[$x]', art = '$art[$x]', kontonr = '$kontonr[$x]', ean = '$ean[$x]', institution = '$institution[$x]', land = '$land[$x]', ref = '$ref[$x]', fakturanr = '$fakturanr[$x]', kundeordnr = '$kundeordnr[$x]', lev_navn = '$lev_navn[$x]',	lev_addr1 = '$lev_addr1[$x]',	lev_addr2 = '$lev_addr2[$x]', lev_postnr = '$lev_postnr[$x]', lev_bynavn = '$lev_bynavn[$x]', lev_kontakt = '$lev_kontakt[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.613' where art = 'VE'");
		transaktion("commit"); 
 	}
	if ($dbver<0.614) {
		transaktion("begin");
	
		$x=0; 
		$query = db_select("SELECT * FROM serienr");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$serienr[$x]=addslashes(trim($row['serienr']));
		 }
		$linjeantal=$x;
 
		db_modify("ALTER TABLE serienr DROP serienr");
 
		db_modify("ALTER TABLE serienr ADD serienr varchar");
 
		for ($x=1; $x<=$linjeantal; $x++)
		{
			db_modify("UPDATE serienr set serienr = '$serienr[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.614' where art = 'VE'");
		transaktion("commit"); 
 	}
	if ($dbver<0.615) {
		transaktion("begin");
		
		$x=0; 
		$query = db_select("SELECT * FROM transaktioner");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$beskrivelse[$x]=addslashes(trim($row['text']));
			$faktura[$x]=addslashes(trim($row['faktura']));
		 }
		$linjeantal=$x;
 
		db_modify("ALTER TABLE transaktioner DROP text");
		db_modify("ALTER TABLE transaktioner DROP faktura");
 
		db_modify("ALTER TABLE transaktioner ADD beskrivelse varchar");
		db_modify("ALTER TABLE transaktioner ADD faktura varchar");
 
		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE transaktioner set beskrivelse = '$beskrivelse[$x]', faktura = '$faktura[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.615' where art = 'VE'");
		transaktion("commit"); 
 	}
	if ($dbver<0.616) {
		transaktion("begin");
		
		$x=0; 
		$query = db_select("SELECT * FROM vare_lev");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$lev_varenr[$x]=addslashes(trim($row['lev_varenr']));
		 }
		$linjeantal=$x;
 
		db_modify("ALTER TABLE vare_lev DROP lev_varenr");
 
		db_modify("ALTER TABLE vare_lev ADD lev_varenr varchar");
 
		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE vare_lev set lev_varenr = '$lev_varenr[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.616' where art = 'VE'");
		transaktion("commit"); 
 	}
	 if ($dbver<0.617) {
		transaktion("begin");
	
		$x=0;
		$query = db_select("SELECT * FROM varer");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$beskrivelse[$x]=addslashes(trim($row['text']));
			$varenr[$x]=addslashes(trim($row['varenr']));
			$enhed[$x]=addslashes(trim($row['enhed']));
			$enhed2[$x]=addslashes(trim($row['enhed2']));
			$gruppe[$x]=addslashes(trim($row['gruppe']));
			$lukket[$x]=addslashes(trim($row['lukket']));
			$serienr[$x]=addslashes(trim($row['serienr']));
			$samlevare[$x]=addslashes(trim($row['samlevare']));
			$delvare[$x]=addslashes(trim($row['delvare']));
		}
		$linjeantal=$x;
 
		db_modify("ALTER TABLE varer DROP text");
		db_modify("ALTER TABLE varer DROP varenr");
		db_modify("ALTER TABLE varer DROP enhed");
		db_modify("ALTER TABLE varer DROP enhed2");
		db_modify("ALTER TABLE varer DROP gruppe");
		db_modify("ALTER TABLE varer DROP lukket");
		db_modify("ALTER TABLE varer DROP serienr");
		db_modify("ALTER TABLE varer DROP samlevare");
		db_modify("ALTER TABLE varer DROP delvare");
 
		db_modify("ALTER TABLE varer ADD beskrivelse varchar");
		db_modify("ALTER TABLE varer ADD varenr varchar");
		db_modify("ALTER TABLE varer ADD enhed varchar");
		db_modify("ALTER TABLE varer ADD enhed2 varchar");
		db_modify("ALTER TABLE varer ADD gruppe varchar");
		db_modify("ALTER TABLE varer ADD lukket varchar");
		db_modify("ALTER TABLE varer ADD serienr varchar");
		db_modify("ALTER TABLE varer ADD samlevare varchar");
		db_modify("ALTER TABLE varer ADD delvare varchar");

		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE varer set beskrivelse = '$beskrivelse[$x]',	varenr = '$varenr[$x]',	enhed = '$enhed[$x]', enhed2 = '$enhed2[$x]', gruppe = '$gruppe[$x]', lukket = '$lukket[$x]', serienr = '$serienr[$x]', samlevare = '$samlevare[$x]', delvare = '$delvare[$x]' where id =$id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.617' where art = 'VE'");
		transaktion("commit"); 
 	}
 	if ($dbver<0.618) {
		transaktion("begin");
		db_modify("delete FROM grupper where kodenr!='1' and art='PV' and box1='lpr' and box3='on'");
		db_modify("update grupper set beskrivelse='Output', box1='/usr/bin/lpr', box2='/usr/bin/ps2pdf' where kodenr='1' and art='PV' and box1='lpr' and box3='on'");
		transaktion("commit"); 
		db_modify("UPDATE grupper set box1 = '0.618' where art = 'VE'");
	}	
 	if ($dbver<0.73) {
		transaktion("begin");
		db_modify("update kontoplan set primo = 0 where regnskabsaar > 1 and kontotype != 'S'");
		transaktion("commit"); 
		db_modify("UPDATE grupper set box1 = '0.73' where art = 'VE'");
	}	
 	if ($dbver<0.82) {
		transaktion("begin");
		db_modify("ALTER TABLE ordrer ADD kostpris numeric");
		transaktion("commit"); 
		db_modify("UPDATE grupper set box1 = '0.82' where art = 'VE'");
	}	
	if ($dbver<0.83) {
		transaktion("begin");
		db_modify("CREATE TABLE reservation (linje_id integer, batch_kob_id integer, batch_salg_id integer, vare_id integer, antal numeric)");
#		db_modify("ALTER TABLE batch_kob ADD reserveret numeric");
		db_modify("UPDATE grupper set box1 = '0.83' where art = 'VE'");
		transaktion("commit"); 
	}	
	if ($dbver<0.91) {
		transaktion("begin");
		db_modify("CREATE TABLE formularer (id serial NOT NULL, formular integer, art integer, beskrivelse varchar, justering varchar, xa numeric, ya numeric, xb numeric, yb numeric, str numeric, color integer, font varchar, fed varchar, kursiv varchar, side varchar, PRIMARY KEY (id))");
		 $fp=fopen("../importfiler/formular.txt","r");
		 if ($fp) {
			while (!feof($fp)) {
				list($formular, $art, $beskrivelse, $xa, $ya, $xb, $yb, $justering, $str, $color, $font, $fed, $kursiv, $side) = split(chr(9), fgets($fp));
				if ($xa>0) {
					$justering=trim($justering); $form=trim($font); $fed=trim($fed); $kursiv=trim($kursiv); $side=trim($side); 
					$xa= $xa*1; $ya= $ya*1; $xb= $xb*1; $yb=$yb*1; $str=$str*1; $color=$color*1;
					db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, justering, str, color, font, fed, kursiv, side) values	('$formular', '$art', '$beskrivelse', '$xa', '$ya', '$xb', '$yb', '$justering', '$str', '$color', '$font', '$fed', '$kursiv', '$side')"); 
				}
			} 
			fclose($fp);
		}
		db_modify("ALTER TABLE brugere ADD ansat_id integer");
		db_modify("UPDATE grupper set box1 = '0.91' where art = 'VE'");
		transaktion("commit"); 
	}	

	if ($dbver<0.920) {
		transaktion("begin");
		db_modify("ALTER TABLE batch_kob ADD fakturadate date");
		$x=0;
		$query = db_select("SELECT id, ordre_id FROM batch_kob order by id");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$ordre_id[$x]=$row[ordre_id];
		}
		$linjeantal=$x;
		for ($x=1; $x<=$linjeantal; $x++) {
			if ($ordre_id[$x]) { 
				$query = db_select("SELECT id, fakturadate FROM ordrer where id = $ordre_id[$x]");
				$row = db_fetch_array($query);
				if ($row[fakturadate]) {db_modify("UPDATE batch_kob set fakturadate = '$row[fakturadate]' where id = $id[$x]");}
			}			 
		}
		db_modify("ALTER TABLE batch_salg ADD fakturadate date");
		$x=0;
		$query = db_select("SELECT id, ordre_id FROM batch_salg order by id");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$ordre_id[$x]=$row[ordre_id];
		}
		$linjeantal=$x;
		for ($x=1; $x<=$linjeantal; $x++) {
			if ($ordre_id[$x]) { 
				$query = db_select("SELECT id, fakturadate FROM ordrer where id = $ordre_id[$x]");
				$row = db_fetch_array($query);
				if ($row[fakturadate]) {db_modify("UPDATE batch_salg set fakturadate = '$row[fakturadate]' where id = $id[$x]");}
			}			 
			else {db_modify("delete FROM batch_salg where id=$id[$x]");}
		}
		db_modify("UPDATE grupper set box1 = '0.920' where art = 'VE'");
		transaktion("commit"); 
	}	
	if ($dbver<0.921) {
		transaktion("begin");
		db_modify("ALTER TABLE ordrelinjer ADD kred_linje_id integer");
		$x=0;
		$query = db_select("SELECT id FROM ordrer where art='DK' or art='KK'");
		while ($row = db_fetch_array($query)){
			$x++;
			$ordre_id[$x]=$row[id];
		}
		$ordreantal=$x;
		for ($x=1; $x<=$ordreantal; $x++) {
			$query = db_select("SELECT id, antal FROM ordrelinjer where ordre_id=$ordre_id[$x]");
			while ($row = db_fetch_array($query)){
				if ($row[antal]) {db_modify("UPDATE ordrelinjer set antal=$row[antal]*-1 where id = $row[id]");}
			} 
		}
		db_modify("UPDATE grupper set box1 = '0.921' where art = 'VE'");
		transaktion("commit"); 
	}	
	if ($dbver<0.934) {
		transaktion("begin");
#		db_modify("CREATE TABLE lagerlog (id serial NOT NULL, lagernr integer, date date, antal numeric, pris numeric, PRIMARY KEY (id))");
		db_modify("CREATE TABLE lagerstatus (id serial NOT NULL, lager integer, vare_id integer, beholdning numeric, PRIMARY KEY (id))");
		db_modify("ALTER TABLE ansatte ADD afd integer");
		db_modify("ALTER TABLE ansatte ADD privattlf varchar");
		db_modify("ALTER TABLE ansatte ADD initialer varchar");
		db_modify("ALTER TABLE kassekladde ADD afd integer");
		db_modify("ALTER TABLE transaktioner ADD afd integer");
		db_modify("ALTER TABLE reservation ADD lager integer");
		db_modify("ALTER TABLE batch_kob ADD lager integer");

		db_modify("UPDATE grupper set box1 = '0.934' where art = 'VE'");
		transaktion("commit"); 
	}	
	if ($dbver<0.935) {
		transaktion("begin");
		$x=0;
		$query = db_select("SELECT id, kontonr FROM kontoplan order by id");
		while($row = db_fetch_array($query)) {
			$x++;
			$id[$x]=$row[id];
			$kontonr[$x]=$row[kontonr]*1;
		}
		$linjeantal=$x;
		db_modify("ALTER TABLE kontoplan DROP kontonr");
		db_modify("ALTER TABLE kontoplan ADD kontonr integer");
		for ($x=1; $x<=$linjeantal; $x++) {
			db_modify("UPDATE kontoplan set kontonr = $kontonr[$x] where id = $id[$x]");
		}
		db_modify("UPDATE grupper set box1 = '0.935' where art = 'VE'");
		transaktion("commit"); 
	}	
	if ($dbver<0.936) {
		transaktion("begin");
		$x=0;
		$query = db_select("SELECT vare_id, rest, lager FROM batch_kob where rest != 0 order by vare_id");
		while($row = db_fetch_array($query)) {
			$x++;
			$vare_id[$x]=$row[vare_id];
			$lager[$x]=$row[lager]*1;
			$antal[$x]=$row[rest]*1;
		}
		$linjeantal=$x;
		db_modify("UPDATE lagerstatus set beholdning = 0 where beholdning != 0");
		for ($x=1; $x<=$linjeantal; $x++) {
			if ($row = db_fetch_array(db_select("SELECT gruppe FROM varer where id = $vare_id[$x]"))) {
				if ($row = db_fetch_array(db_select("SELECT id FROM grupper where art = 'VG' and kodenr = '$row[gruppe]' and box8 = 'on'"))) {
					if ($row = db_fetch_array(db_select("SELECT id, beholdning FROM lagerstatus where vare_id = $vare_id[$x] and lager = $lager[$x]"))) {
						db_modify("UPDATE lagerstatus set beholdning = $antal[$x]+$row[beholdning] where id = $row[id]");
					}
					else {db_modify("insert into lagerstatus (lager, vare_id, beholdning) values	('$lager[$x]', '$vare_id[$x]', '$antal[$x]')");}
				}
			}
			else {db_modify("delete FROM lagerstatus where vare_id=$vare_id[$x]");}
		} 
		db_modify("UPDATE batch_kob set lager = 0 where lager	!= 0");
		db_modify("UPDATE grupper set box1 = '0.936' where art = 'VE'");
		transaktion("commit"); 
	}	
	if ($dbver<0.937) {
		transaktion("begin");
		db_modify("ALTER TABLE ordrelinjer ADD momsfri varchar");
		db_modify("ALTER TABLE ordrer ADD moms numeric");
		db_modify("UPDATE grupper set box7 = 'on' where art = 'VG' and box5 = ''");
		db_modify("UPDATE grupper set box5 = '', box6 = '' where art = 'VG' and box7 != 'on'");
		db_modify("UPDATE grupper set box1 = '0.937' where art = 'VE'");
		transaktion("commit"); 
	}	
	if ($dbver<0.956) {
		transaktion("begin");
		db_modify("ALTER TABLE varer ADD provisionsfri VARCHAR");
		db_modify("UPDATE grupper set box1 = '0.956' where art = 'VE'");
		transaktion("commit"); 
	}	
	db_modify("UPDATE grupper set box1 = '1.0.0' where art = 'VE'");
}
?>
