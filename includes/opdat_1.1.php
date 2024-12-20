<?php
// -------------------------- includes/opdat_1.1.php-------lap 2.1.0-----2009-11-12----------
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
function opdat_1_1($under_nr, $lap_nr){
	global $version;
	global $regnaar;
	$s_id=session_id();

	if ($lap_nr<1){
		include("../includes/connect.php");
		include("../includes/online.php");
		transaktion("begin");
		$x=0;
		$q1= db_select("select id, fra_kto from kontoplan where kontotype = 'D' and fra_kto!='' order by id",__FILE__ . " linje " . __LINE__);
		while ($r1=db_fetch_array($q1)) {
				$x++;
				$id[$x]=$r1['id'];
				$moms[$x]=str_replace('"','',$r1['fra_kto']);
		}
		$kontoantal=$x;
		for ($x=1; $x<=$kontoantal; $x++) {
			if ($moms[$x]) {
				db_modify("update kontoplan set moms='$moms[$x]', fra_kto = '' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
			} else { 
				db_modify("update kontoplan set fra_kto = '' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}	
		$x=0;
		$q1= db_select("select id, til_kto, kontonr from kontoplan where kontotype = 'Z' and fra_kto ='' and til_kto!='kontonr' order by id",__FILE__ . " linje " . __LINE__);
		while ($r1=db_fetch_array($q1)) {
			$x++;
			$id[$x]=$r1['id'];
			$kontonr[$x]=$r1['kontonr'];	
			$fra_kto[$x]=$r1['til_kto'];
		}
		$kontoantal=$x;
		for ($x=1; $x<=$kontoantal; $x++) {
			db_modify("update kontoplan set fra_kto='$fra_kto[$x]', til_kto='$kontonr[$x]'  where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
		}
		db_modify("UPDATE grupper set box1 = '1.1.1' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion("commit"); 
	}
#########################################################################################
	if ($lap_nr<2){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		if ($r[version] < '1.1.2') {
			transaktion("begin"); 
			db_modify("ALTER TABLE online ADD logdate date",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE online ADD logtime time",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE regnskab DROP dbpass",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE regnskab ADD version varchar",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE TABLE brugerdata (id serial NOT NULL, firmanavn varchar, addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, kontakt varchar, email varchar, cvrnr varchar, regnskab varchar, brugernavn varchar, kodeord varchar, kontrol_id varchar, aktiv int, logtime varchar, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("CREATE TABLE tabelinfo (id serial NOT NULL, tabelnavn varchar, feltnavn varchar, beskrivelse varchar, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'brugernavn')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'kode')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'status')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'regnskabsaar')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'rettigheder')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'regnskab')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'dbhost')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'dbuser')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'dbpass')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'db')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'version')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'session_id')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'brugernavn')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'db')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'dbuser')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'rettigheder')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'regnskabsaar')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'logdate')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'logtime')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'id')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'firmanavn')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'addr1')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'addr2')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'postnr')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'bynavn')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'kontakt')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'email')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'cvrnr')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'regnskab')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'brugernavn')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'kodeord')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'kontrol_id')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'aktiv')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'logdate')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'logtime')",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE regnskab set version = '1.1.2' where id = 1",__FILE__ . " linje " . __LINE__);
			transaktion("commit");
		}
		include("../includes/online.php");
		transaktion("begin");
		$x=0;
		$q1= db_select("select id, lukket from kontoplan order by id",__FILE__ . " linje " . __LINE__);
		while ($r1=db_fetch_array($q1)) {
			if ($row[lukket]!='on') {
				$x++;
				$id[$x]=$r1['id'];
			}
		}
		$kontoantal=$x;
		for ($x=1; $x<=$kontoantal; $x++) {
			db_modify("update kontoplan set lukket= '' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
		}	
		db_modify("ALTER TABLE ordrelinjer ADD leveret numeric",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE TABLE historik (id serial NOT NULL, konto_id int, kontakt_id int, ansat_id int, notat varchar, notedate date, kontaktet date, kontaktes date, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD oprettet date",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD kontaktet date",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE adresser ADD kontaktes date",__FILE__ . " linje " . __LINE__);

		db_modify("CREATE TABLE tabelinfo (id serial NOT NULL, tabelnavn varchar, feltnavn varchar, beskrivelse varchar, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'konto_id')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'kontakt_id')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'ansat_id')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'notat_id')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'notedate_id')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'firmanavn')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'addr1')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'addr2')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'postnr')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'bynavn')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'land')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'kontakt')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'tlf')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'fax')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'email')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'web')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'bank_navn')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'bank_reg')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'bank_konto')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'notes')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'rabat')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'momskonto')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'kreditmax')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'betalingsbet')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'betalingsdage')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'kontonr')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'cvrnr')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'ean')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'institution')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'art')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'gruppe')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'kontoansvarlig')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'oprettet')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'kontaktet')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('adresser' ,'kontaktes')",__FILE__ . " linje " . __LINE__);

		db_modify("UPDATE grupper set box1 = '1.1.2' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion("commit");
	}	
#########################################################################################
	if ($lap_nr<3){
		transaktion("begin");
		$x=0;
		$q=db_select("select id, debet, kredit from kassekladde order by id",__FILE__ . " linje " . __LINE__); 
		while ($r=db_fetch_array($q)) {
			$x++;
			$id[$x]=$r['id'];
			$debet[$x]=$r['debet'];
			$kredit[$x]=$r['kredit'];
		}
		$antal=$x;
		db_modify("ALTER TABLE kassekladde DROP debet",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kassekladde DROP kredit",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kassekladde ADD debet numeric",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kassekladde ADD kredit numeric",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kassekladde ADD projekt numeric",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kassekladde ADD valuta integer",__FILE__ . " linje " . __LINE__);

		for ($x=1; $x<=$antal; $x++) {
			if ($debet[$x] && $kredit[$x]) db_modify("update kassekladde set debet='$debet[$x]', kredit = '$kredit[$x]' where id=$id[$x]",__FILE__ . " linje " . __LINE__);
			elseif ($debet[$x]) db_modify("update kassekladde set debet='$debet[$x]' where id=$id[$x]",__FILE__ . " linje " . __LINE__);
			elseif ($kredit[$x]) db_modify("update kassekladde set kredit='$kredit[$x]' where id=$id[$x]",__FILE__ . " linje " . __LINE__);
		}
		db_modify("ALTER TABLE tabelinfo ADD type varchar",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type)values ('kassekladde', 'id serial NOT NULL', '', 'PRIMARY KEY')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'bilag', '', 'integer')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'transdate', '', 'date')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'beskrivelse', '', 'varchar')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'd_art', 'Debet art: kan vaere F=finans, D=debitor, K=kreditor', 'varchar')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'debet', '', 'numeric')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'k_type', 'Kredit type: kan vaere F=finans, D=debitor, K=kreditor', 'varchar')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'kredit', '', 'numeric')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'faktura', 'Evt fakturanummer knyttet til posteringen', 'varchar')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'amount', 'Beloeb i US formatering', 'numeric')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'kladde_id', 'Henviser til kladdeliste', 'integer')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'momsfri', '', 'varchar')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'afd', 'Afd ID henviser til grupper', 'integer')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'projekt', 'Projekt regnskab, henviser til kodenr i grupper hvor ART = proj', 'numeric')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, type) values ('kassekladde', 'valuta', 'Valuta', 'integer')",__FILE__ . " linje " . __LINE__);
	
		db_modify("UPDATE grupper set box1 = '1.1.3' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion("commit");
	}
#########################################################################################
	if ($lap_nr<4) {
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		if ($r['version']<'1.1.4') {
			transaktion("begin");
			db_modify("ALTER TABLE online DROP logdate",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE online DROP logtime",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE online ADD logtime varchar",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE regnskab ADD sidst varchar",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE regnskab ADD brugerantal numeric",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE regnskab ADD posteringer numeric",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE tabelinfo ADD art varchar",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, art) values ('regnskab','sidst','varchar')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, art) values ('regnskab','brugerantal','numeric')",__FILE__ . " linje " . __LINE__);
			db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, art) values ('regnskab','posteringer','numeric')",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE online set logtime = '-'",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE regnskab set brugerantal = '0', posteringer = '0', version='1.1.4' where id > 1",__FILE__ . " linje " . __LINE__);
			transaktion("commit");
		}
		include("../includes/online.php");
		transaktion("begin");
		db_modify("ALTER TABLE tabelinfo RENAME type TO art",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kassekladde ADD ansat integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kassekladde ADD valutakurs numeric",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE kassekladde ADD ordre_id integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE tmpkassekl ADD ansat varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE tmpkassekl ADD projekt varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE tmpkassekl ADD valuta varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE tmpkassekl ADD valutakurs varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD nextfakt date",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD betalt varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD projekt varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD valuta varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD valutakurs numeric",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrer ADD sprog varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE transaktioner drop projekt_id",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE transaktioner add ordre_id integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE transaktioner add ansat integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE transaktioner add projekt integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE transaktioner add valuta integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE transaktioner add valutakurs numeric",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE formularer add sprog varchar",__FILE__ . " linje " . __LINE__);
		db_modify("update formularer set sprog = 'Dansk'",__FILE__ . " linje " . __LINE__);
		$q = db_select("select * from formularer where formular = '6'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$xa=$r['xa']*1;$ya=$r['ya']*1; $xb=$r['xb']*1;$yb=$r['yb']*1;$str=$r['str']*1;$color=$r['color']*1;
			db_modify("insert into formularer (formular, art, beskrivelse, justering, xa, ya, xb, yb, str, color, font, fed, kursiv, side, sprog) values ('7', '$r[art]', '$r[beskrivelse]', '$r[justering]', '$xa', '$ya', '$xb', '$yb', '$str', '$color', '$r[font]', '$r[fed]', 	'$r[kursiv]', '$r[side]', '$r[sprog]')",__FILE__ . " linje " . __LINE__);
			db_modify("insert into formularer (formular, art, beskrivelse, justering, xa, ya, xb, yb, str, color, font, fed, kursiv, side, sprog) values ('8', '$r[art]', '$r[beskrivelse]', '$r[justering]', '$xa', '$ya', '$xb', '$yb', '$str', '$color', '$r[font]', '$r[fed]', 	'$r[kursiv]', '$r[side]', '$r[sprog]')",__FILE__ . " linje " . __LINE__);
		}
		$q = db_select("select * from grupper where art = 'DG'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) db_modify("update grupper set box3='DKK',box4='Dansk',box5='$r[box4]',box6='$r[box5]',box7='$r[box6]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
		$q = db_select("select * from grupper where art = 'KG'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) db_modify("update grupper set box3='DKK',box4='Dansk',box5='$r[box4]',box6='$r[box5]',box7='$r[box6]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);

		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde', 'ansat', 'ansat ID henviser til tabel ansatte', 'integer')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('ordrer', 'projekt', 'projekt', 'varchar')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('ordrer', 'valuta', 'valuta', 'varchar')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('ordrer', 'valutakurs', 'valutakurs', 'numeric')",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE TABLE valuta (id serial NOT NULL, gruppe integer, valdate date, kurs numeric, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('valuta', 'id', 'id serial NOT NULL', 'PRIMARY KEY')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('valuta', 'gruppe', 'Modsvarer kodenr i GRUPPER hvor arg =VK', 'integer')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('valuta', 'valdate', 'Dato for kursændring', 'date')",__FILE__ . " linje " . __LINE__);
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('valuta', 'kurs', 'Aktuel valutakurs', 'numeric')",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '1.1.4' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion("commit");
		db_close($connection);
		$connection = db_connect ("$sqhost","$squser","$sqpass","$sqdb");
		include("../includes/connect.php");
		transaktion("begin");
		$r=db_fetch_array(db_select("select * from online where session_id = '$s_id'",__FILE__ . " linje " . __LINE__));
		$db	= trim($r['db']);
		$tmp=time("U");
		transaktion("begin");
		db_modify("UPDATE regnskab set version='1.1.4', sidst='$tmp' where db = '$db'",__FILE__ . " linje " . __LINE__);
		transaktion("commit");
		include("../includes/online.php");
	}
#########################################################################################
	if ($lap_nr<6) {
		transaktion("begin");
		db_modify("ALTER TABLE openpost ADD valuta varchar",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE openpost ADD valutakurs numeric",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '1.1.6' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		transaktion("commit");
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		if ($r['version']<'1.1.6') {
			transaktion("begin");
			db_modify("UPDATE regnskab set version='1.1.6' where id = 1",__FILE__ . " linje " . __LINE__);
			transaktion("commit");
			include("../includes/online.php");
		}
	}
}
?>
