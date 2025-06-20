<?php
// -------------------------- includes/opdat_1.1.php-------lap 1.1.4 ------03.03.2008----------
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
function opdat_1_1($under_nr, $lap_nr){
	global $version;
	global $regnaar;
	$s_id=session_id();

	if ($lap_nr<1){
		transaktion("begin");
		$x=0;
		$q1= db_select("select id, fra_kto from kontoplan where kontotype = 'D' and fra_kto!='' order by id");
		while ($r1=db_fetch_array($q1)) {
				$x++;
				$id[$x]=$r1['id'];
				$moms[$x]=str_replace('"','',$r1['fra_kto']);
		}
		$kontoantal=$x;
		for ($x=1; $x<=$kontoantal; $x++) {
			if ($moms[$x]) {
				db_modify("update kontoplan set moms='$moms[$x]', fra_kto = '' where id='$id[$x]'");
			} else { 
				db_modify("update kontoplan set fra_kto = '' where id='$id[$x]'");
			}
		}	
		$x=0;
		$q1= db_select("select id, til_kto, kontonr from kontoplan where kontotype = 'Z' and fra_kto ='' and til_kto!='kontonr' order by id");
		while ($r1=db_fetch_array($q1)) {
			$x++;
			$id[$x]=$r1['id'];
			$kontonr[$x]=$r1['kontonr'];	
			$fra_kto[$x]=$r1['til_kto'];
		}
		$kontoantal=$x;
		for ($x=1; $x<=$kontoantal; $x++) {
			db_modify("update kontoplan set fra_kto='$fra_kto[$x]', til_kto='$kontonr[$x]'  where id='$id[$x]'");
		}
		db_modify("UPDATE grupper set box1 = '1.1.1' where art = 'VE'");
		transaktion("commit"); 
	}
	if ($lap_nr<2){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1"));
		if ($r[version] < '1.1.2') {
			transaktion("begin"); 
			db_modify("ALTER TABLE online ADD logdate date");
			db_modify("ALTER TABLE online ADD logtime time");
			db_modify("ALTER TABLE regnskab DROP dbpass");
			db_modify("ALTER TABLE regnskab ADD version varchar");
			db_modify("CREATE TABLE brugerdata (id serial NOT NULL, firmanavn varchar, addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, kontakt varchar, email varchar, cvrnr varchar, regnskab varchar, brugernavn varchar, kodeord varchar, kontrol_id varchar, aktiv int, logtime varchar, PRIMARY KEY (id))");
			db_modify("CREATE TABLE tabelinfo (id serial NOT NULL, tabelnavn varchar, feltnavn varchar, beskrivelse varchar, PRIMARY KEY (id))");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'brugernavn')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'kode')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'status')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'regnskabsaar')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'rettigheder')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'regnskab')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'dbhost')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'dbuser')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'dbpass')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'db')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'version')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'session_id')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'brugernavn')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'db')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'dbuser')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'rettigheder')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'regnskabsaar')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'logdate')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'logtime')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'id')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'firmanavn')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'addr1')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'addr2')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'postnr')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'bynavn')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'kontakt')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'email')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'cvrnr')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'regnskab')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'brugernavn')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'kodeord')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'kontrol_id')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'aktiv')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'logdate')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'logtime')");
			db_modify("UPDATE regnskab set version = '1.1.2' where id = 1");
			transaktion("commit");
		}
		include("../includes/online.php");
		transaktion("begin");
		$x=0;
		$q1= db_select("select id, lukket from kontoplan order by id");
		while ($r1=db_fetch_array($q1)) {
			if ($row[lukket]!='on') {
				$x++;
				$id[$x]=$r1['id'];
			}
		}
		$kontoantal=$x;
		for ($x=1; $x<=$kontoantal; $x++) {
			db_modify("update kontoplan set lukket= '' where id='$id[$x]'");
		}	
		db_modify("ALTER TABLE ordrelinjer ADD leveret numeric");
		db_modify("CREATE TABLE historik (id serial NOT NULL, konto_id int, kontakt_id int, ansat_id int, notat varchar, notedate date, PRIMARY KEY (id))");
		db_modify("ALTER TABLE adresser ADD oprettet date");
		db_modify("ALTER TABLE adresser ADD kontaktet date");
		db_modify("ALTER TABLE adresser ADD kontaktes date");

		db_modify("CREATE TABLE tabelinfo (id serial NOT NULL, tabelnavn varchar, feltnavn varchar, beskrivelse varchar, PRIMARY KEY (id))");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'konto_id')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'kontakt_id')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'ansat_id')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'notat_id')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'notedate_id')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'firmanavn')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'addr1')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'addr2')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'postnr')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'bynavn')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'land')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'kontakt')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'tlf')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'fax')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'email')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'web')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'bank_navn')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'bank_reg')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'bank_konto')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'notes')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'rabat')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'momskonto')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'kreditmax')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'betalingsbet')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'betalingsdage')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'kontonr')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'cvrnr')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'ean')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'institution')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'art')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'gruppe')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'kontoansvarlig')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'oprettet')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'kontaktet')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'kontaktes')");

		db_modify("UPDATE grupper set box1 = '1.1.2' where art = 'VE'");
		transaktion("commit");
	}	
	if ($lap_nr<3){
		transaktion("begin");
		$x=0;
		$q=db_select("select id, debet, kredit from kassekladde order by id"); 
		while ($r=db_fetch_array($q)) {
			$x++;
			$id[$x]=$r['id'];
			$debet[$x]=$r['debet'];
			$kredit[$x]=$r['kredit'];
		}
		$antal=$x;
		db_modify("ALTER TABLE kassekladde DROP debet");
		db_modify("ALTER TABLE kassekladde DROP kredit");
		db_modify("ALTER TABLE kassekladde ADD debet numeric");
		db_modify("ALTER TABLE kassekladde ADD kredit numeric");
		db_modify("ALTER TABLE kassekladde ADD projekt numeric");
		db_modify("ALTER TABLE kassekladde ADD valuta integer");

		for ($x=1; $x<=$antal; $x++) {
			if ($debet[$x] && $kredit[$x]) db_modify("update kassekladde set debet='$debet[$x]', kredit = '$kredit[$x]' where id=$id[$x]");
			elseif ($debet[$x]) db_modify("update kassekladde set debet='$debet[$x]' where id=$id[$x]");
			elseif ($kredit[$x]) db_modify("update kassekladde set kredit='$kredit[$x]' where id=$id[$x]");
		}
		db_modify("ALTER TABLE tabelinfo ADD type varchar");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type)values ('kassekladde', 'id serial NOT NULL', '', 'PRIMARY KEY')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'bilag', '', 'integer')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'transdate', '', 'date')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'beskrivelse', '', 'varchar')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'd_art', 'Debet art: kan vaere F=finans, D=debitor, K=kreditor', 'varchar')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'debet', '', 'numeric')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'k_type', 'Kredit type: kan vaere F=finans, D=debitor, K=kreditor', 'varchar')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'kredit', '', 'numeric')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'faktura', 'Evt fakturanummer knyttet til posteringen', 'varchar')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'amount', 'Beloeb i US formatering', 'numeric')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'kladde_id', 'Henviser til kladdeliste', 'integer')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'momsfri', '', 'varchar')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'afd', 'Afd ID henviser til grupper', 'integer')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'projekt', 'Projekt regnskab, henviser til kodenr i grupper hvor ART = proj', 'numeric')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'valuta', 'Valuta', 'integer')");
	
		db_modify("UPDATE grupper set box1 = '1.1.3' where art = 'VE'");
		transaktion("commit");
	}
	if ($lap_nr<4) {
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1"));
		if ($r['version']<'1.1.4') {
			transaktion("begin");
			db_modify("ALTER TABLE online DROP logdate");
			db_modify("ALTER TABLE online DROP logtime");
			db_modify("ALTER TABLE online ADD logtime varchar");
			db_modify("ALTER TABLE regnskab ADD sidst varchar");
			db_modify("ALTER TABLE regnskab ADD brugerantal numeric");
			db_modify("ALTER TABLE regnskab ADD posteringer numeric");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, art) values ('regnskab','sidst','varchar')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, art) values ('regnskab','brugerantal','numeric')");
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, art) values ('regnskab','posteringer','numeric')");
			db_modify("UPDATE online set logtime = '-'");
			db_modify("UPDATE regnskab set brugerantal = '0', posteringer = '0' version='1.1.4' where id > 1");
			transaktion("commit");
		}
		include("../includes/online.php");
		transaktion("begin");
		db_modify("ALTER TABLE tabelinfo RENAME type TO art");
		db_modify("ALTER TABLE kassekladde ADD ansat integer");
		db_modify("ALTER TABLE kassekladde ADD valutakurs numeric");
		db_modify("ALTER TABLE kassekladde ADD ordre_id integer");
		db_modify("ALTER TABLE tmpkassekl ADD ansat varchar");
		db_modify("ALTER TABLE tmpkassekl ADD projekt varchar");
		db_modify("ALTER TABLE tmpkassekl ADD valuta varchar");
		db_modify("ALTER TABLE ordrer ADD nextfakt date");
		db_modify("ALTER TABLE ordrer ADD betalt varchar");
		db_modify("ALTER TABLE ordrer ADD projekt varchar");
		db_modify("ALTER TABLE ordrer ADD valuta varchar");
		db_modify("ALTER TABLE ordrer ADD valutakurs numeric");
		db_modify("ALTER TABLE ordrer ADD sprog varchar");
		db_modify("ALTER TABLE transaktioner drop projekt_id");
		db_modify("ALTER TABLE transaktioner add ordre_id integer");
		db_modify("ALTER TABLE transaktioner add ansat integer");
		db_modify("ALTER TABLE transaktioner add projekt integer");
		db_modify("ALTER TABLE transaktioner add valuta integer");
		db_modify("ALTER TABLE transaktioner add valutakurs numeric");
		db_modify("ALTER TABLE formularer add sprog varchar");
		db_modify("update formularer set sprog = 'Dansk'");
		$q = db_select("select * from formularer where formular = '6'");
		while ($r=db_fetch_array($q)) {
			$xa=$r['xa']*1;$ya=$r['ya']*1; $xb=$r['xb']*1;$yb=$r['yb']*1;$str=$r['str']*1;$color=$r['color']*1;
			db_modify("insert into formularer (formular, art, beskrivelse, placering, xa, ya, xb, yb, str, color, font, fed, kursiv, side, sprog) values ('7', '$r[art]', '$r[beskrivelse]', '$r[placering]', '$xa', '$ya', '$xb', '$yb', '$str', '$color', '$r[font]', '$r[fed]', 	'$r[kursiv]', '$r[side]', '$r[sprog]')");
			db_modify("insert into formularer (formular, art, beskrivelse, placering, xa, ya, xb, yb, str, color, font, fed, kursiv, side, sprog) values ('8', '$r[art]', '$r[beskrivelse]', '$r[placering]', '$xa', '$ya', '$xb', '$yb', '$str', '$color', '$r[font]', '$r[fed]', 	'$r[kursiv]', '$r[side]', '$r[sprog]')");
		}
		$q = db_select("select * from grupper where art = 'DG'");
		while ($r=db_fetch_array($q)) db_modify("update grupper set box3='DKK',box4='Dansk',box5='$r[box4]',box6='$r[box5]',box7='$r[box6]' where id = '$r[id]'");
		$q = db_select("select * from grupper where art = 'KG'");
		while ($r=db_fetch_array($q)) db_modify("update grupper set box3='DKK',box4='Dansk',box5='$r[box4]',box6='$r[box5]',box7='$r[box6]' where id = '$r[id]'");

		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde', 'ansat', 'ansat ID henviser til tabel ansatte', 'integer')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('ordrer', 'projekt', 'projekt', 'varchar')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('ordrer', 'valuta', 'valuta', 'varchar')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('ordrer', 'valutakurs', 'valutakurs', 'numeric')");
		db_modify("CREATE TABLE valuta (id serial NOT NULL, gruppe integer, valdate date, kurs numeric, PRIMARY KEY (id))");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('valuta', 'id', 'id serial NOT NULL', 'PRIMARY KEY')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('valuta', 'gruppe', 'Modsvarer kodenr i GRUPPER hvor arg =VK', 'integer')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('valuta', 'valdate', 'Dato for kursændring', 'date')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('valuta', 'kurs', 'Aktuel valutakurs', 'numeric')");
		db_modify("UPDATE grupper set box1 = '1.1.4' where art = 'VE'");
		transaktion("commit");
		db_close($connection);
		$connection = db_connect ("host=$sqhost dbname=$sqdb user=$squser password=$sqpass");
		include("../includes/connect.php");
		transaktion("begin");
		$r=db_fetch_array(db_select("select * from online where session_id = '$s_id'"));
		$db	= trim($r['db']);
		$tmp=time("U");
		transaktion("begin");
		db_modify("UPDATE regnskab set version='1.1.4', sidst='$tmp' where db = '$db'");
		transaktion("commit");
		include("../includes/online.php");
	}
}
?>
