<?php
// -------------------------- includes/opdat_2.0.php-------lap 2.1.7 ------2010.04.12---------------
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
function opdat_2_1($under_nr, $lap_nr){
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
		if ($tmp<'2.1.1') {
			echo "opdaterer hovedregnskab fra $tmp til ver 2.1.1<br>";
			db_modify("UPDATE regnskab set version = '2.1.1' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 2.1.$lap_nr til ver 2.1.1<br>";
		db_modify("ALTER TABLE varer ADD prisgruppe integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD tilbudgruppe integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD rabatgruppe integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD m_type varchar(10)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD m_rabat text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD m_antal text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE varer ADD folgevare text",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrelinjer ADD rabatgruppe integer",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrelinjer ADD m_rabat numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE ordrelinjer ADD folgevare integer",__FILE__ . " linje " . __LINE__);
		if ($db_type=="mysql") {
			db_modify("ALTER TABLE ordrer CHANGE betalt betalt text unsigned",__FILE__ . " linje " . __LINE__);
		} else {
			db_modify("ALTER TABLE ordrer RENAME column betalt TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrer ADD column betalt text",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrer set betalt = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrer DROP tmp",__FILE__ . " linje " . __LINE__);
		}
		db_modify("UPDATE grupper set box1 = '2.1.1' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.1.1' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"2"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.1.2') {
			echo "opdaterer hovedregnskab fra $tmp til ver 2.1.2<br>";
			db_modify("ALTER TABLE regnskab ADD lukkes date",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE regnskab ADD betalt_til date",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE regnskab set version = '2.1.2' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 2.1.$lap_nr til ver 2.1.2<br>";
		db_modify("ALTER TABLE varer ADD stregkode text",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '2.1.2' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.1.2' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"3"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.1.3') {
			echo "opdaterer hovedregnskab fra $tmp til ver 2.1.3<br>";
			db_modify("ALTER TABLE regnskab ADD logintekst text",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE regnskab set version = '2.1.3' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 2.1.$lap_nr til ver 2.1.3<br>";
		db_modify("CREATE TABLE budget (id serial NOT NULL,regnaar integer,md integer, kontonr numeric(15,0),amount numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '2.1.3' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.1.3' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"4"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.1.4') {
			echo "opdaterer hovedregnskab fra $tmp til ver 2.1.4<br>";
			db_modify("ALTER TABLE regnskab ADD column logintekst text",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE kundedata ADD column slettet varchar(2)",__FILE__ . " linje " . __LINE__);
			db_modify("update kundedata set slettet=''",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE regnskab set version = '2.1.4' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 2.1.$lap_nr til ver 2.1.4<br>";
		if ($db_type=="mysql") {
			db_modify("ALTER TABLE grupper CHANGE beskrivelse beskrivelse text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE kode kode text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE kodenr kodenr text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE art art text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE box1 box1 text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE box2 box2 text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE box3 box3 text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE box4 box4 text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE box5 box5 text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE box6 box6 text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE box7 box7 text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE box8 box8 text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE box9 box9 text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper CHANGE box10 box10 text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE formularer CHANGE beskrivelse beskrivelse text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE formularer CHANGE placering justering text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE formularer CHANGE font font text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE formularer CHANGE sprog sprog text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer CHANGE beskrivelse beskrivelse text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer CHANGE bogfort_af bogfort_af text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer CHANGE enhed ehed enhed unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer CHANGE hvem hvem text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer CHANGE lev_varenr lev_varenr text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer CHANGE oprettet_af oprettet_af text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer CHANGE serienr serienr text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer CHANGE tidspkt tidspkt text unsigned",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer CHANGE varenr varenr text unsigned",__FILE__ . " linje " . __LINE__);
		} else {
			db_modify("ALTER TABLE grupper RENAME column beskrivelse TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column beskrivelse text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set beskrivelse = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE grupper RENAME column kode TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column kode text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set kode = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE grupper RENAME column kodenr TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column kodenr text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set kodenr = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE grupper RENAME column art TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column art text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set art = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
			
			
			db_modify("ALTER TABLE grupper RENAME column box1 TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column box1 text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set box1 = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE grupper RENAME column box2 TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column box2 text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set box2 = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
		
			db_modify("ALTER TABLE grupper RENAME column box3 TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column box3 text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set box3 = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE grupper RENAME column box4 TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column box4 text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set box4 = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE grupper RENAME column box5 TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column box5 text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set box5 = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE grupper RENAME column box6 TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column box6 text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set box6 = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE grupper RENAME column box7 TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column box7 text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set box7 = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE grupper RENAME column box8 TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column box8 text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set box8 = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE grupper RENAME column box9 TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column box9 text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set box9 = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE grupper RENAME column box10 TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper ADD column box10 text",__FILE__ . " linje " . __LINE__);
			db_modify("update grupper set box10 = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE grupper DROP tmp",__FILE__ . " linje " . __LINE__);
		
			db_modify("ALTER TABLE formularer RENAME column beskrivelse TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE formularer ADD column beskrivelse text",__FILE__ . " linje " . __LINE__);
			db_modify("update formularer set beskrivelse = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE formularer DROP tmp",__FILE__ . " linje " . __LINE__);
			
#			db_modify("ALTER TABLE formularer RENAME column placering TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE formularer ADD column justering text",__FILE__ . " linje " . __LINE__);
			db_modify("update formularer set justering = placering",__FILE__ . " linje " . __LINE__);
#			db_modify("ALTER TABLE formularer DROP tmp",__FILE__ . " linje " . __LINE__);

			db_modify("ALTER TABLE formularer RENAME column font TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE formularer ADD column font text",__FILE__ . " linje " . __LINE__);
			db_modify("update formularer set font = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE formularer DROP tmp",__FILE__ . " linje " . __LINE__);

			db_modify("ALTER TABLE formularer RENAME column sprog TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE formularer ADD column sprog text",__FILE__ . " linje " . __LINE__);
			db_modify("update formularer set sprog = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE formularer DROP tmp",__FILE__ . " linje " . __LINE__);
		
			db_modify("ALTER TABLE ordrelinjer RENAME column beskrivelse TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer ADD column beskrivelse text",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrelinjer set beskrivelse = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE ordrelinjer RENAME column bogfort_af TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer ADD column bogfort_af text",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrelinjer set bogfort_af = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE ordrelinjer RENAME column enhed TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer ADD column enhed text",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrelinjer set enhed = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE ordrelinjer RENAME column hvem TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer ADD column hvem text",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrelinjer set hvem = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE ordrelinjer RENAME column lev_varenr TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer ADD column lev_varenr text",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrelinjer set lev_varenr = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE ordrelinjer RENAME column oprettet_af TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer ADD column oprettet_af text",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrelinjer set oprettet_af = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE ordrelinjer RENAME column serienr TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer ADD column serienr text",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrelinjer set serienr = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE ordrelinjer RENAME column tidspkt TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer ADD column tidspkt text",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrelinjer set tidspkt = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer DROP tmp",__FILE__ . " linje " . __LINE__);
			
			db_modify("ALTER TABLE ordrelinjer RENAME column varenr TO tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer ADD column varenr text",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrelinjer set varenr = tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrelinjer DROP tmp",__FILE__ . " linje " . __LINE__);
			db_modify("ALTER TABLE ordrer DROP tmp",__FILE__ . " linje " . __LINE__);

		}
		db_modify("UPDATE grupper set box1 = '2.1.4' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.1.4' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"5"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.1.5') {
			echo "opdaterer hovedregnskab fra $tmp til ver 2.1.5<br>";
			db_modify("UPDATE regnskab set version = '2.1.5' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 2.1.$lap_nr til ver 2.1.5<br>";
		db_modify("ALTER TABLE adresser ADD column lukket varchar(2)",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE adresser set lukket = ''",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '2.1.5' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.1.5' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"6"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.1.6') {
			echo "opdaterer hovedregnskab fra $tmp til ver 2.1.6<br>";
			db_modify("UPDATE regnskab set version = '2.1.6' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 2.1.$lap_nr til ver 2.1.6<br>";
		db_modify("ALTER TABLE ordrelinjer ADD column momssats numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '2.1.6' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.1.6' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"7"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.1.7') {
			echo "opdaterer hovedregnskab fra $tmp til ver 2.1.6<br>";
			db_modify("UPDATE regnskab set version = '2.1.7' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 2.1.$lap_nr til ver 2.1.7<br>";
		db_modify("ALTER TABLE ordrer ADD column restordre numeric(2,0)",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE ordrer set restordre = '0'",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '2.1.7' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.1.7' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"8"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.1.8') {
			echo "opdaterer hovedregnskab fra $tmp til ver 2.1.8<br>";
			db_modify("UPDATE regnskab set version = '2.1.8' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 2.1.$lap_nr til ver 2.1.8<br>";
		db_modify("CREATE TABLE rabat(id serial NOT NULL,rabat numeric(6,2),debitorart varchar(2),debitor int,vareart varchar(2),vare int,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '2.1.8' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '2.1.8' where db = '$db'",__FILE__ . " linje " . __LINE__);
	}
	if ($lap_nr<"9"){
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where id=1",__FILE__ . " linje " . __LINE__));
		$tmp=$r['version'];
		if ($tmp<'2.1.9') {
			echo "opdaterer hovedregnskab fra $tmp til ver 2.1.9<br>";
			db_modify("UPDATE regnskab set version = '2.1.9' where id = 1",__FILE__ . " linje " . __LINE__);
		}
		include("../includes/online.php");
		echo "opdaterer ver 2.1.$lap_nr til ver 3.0.0<br>";
		$x=0;
		$q=db_select("select * from formularer order by sprog,formular,art,xa,xb,ya,yb,id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$x++;
			$id[$x]=$r['id'];
			$sprog[$x]=$r['sprog'];
			$form[$x]=$r['form'];
			$ar[$x]=$r['ar'];
			$xa[$x]=$r['xa'];
			$xb[$x]=$r['xb'];
			$ya[$x]=$r['ya'];
			$yb[$x]=$r['yb'];
			$side[$x]=$r['side'];
			if ($sprog[$x]==$sprog[$x-1] && $form[$x]==$form[$x-1] && $ar[$x]==$ar[$x-1] && $xa[$x]==$xa[$x-1] && $xb[$x]==$xb[$x-1] && $ya[$x]==$ya[$x-1] && $yb[$x]==$yb[$x-1] && $side[$x]==$side[$x-1]) {
				db_modify("delete from formularer where id = $id[$x]");
			}
		}
		db_modify("ALTER TABLE adresser ADD column kategori text",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE grupper set box1 = '3.0.0' where art = 'VE'",__FILE__ . " linje " . __LINE__);
		include("../includes/connect.php");
		db_modify("UPDATE regnskab set version = '3.0.0' where db = '$db'",__FILE__ . " linje " . __LINE__);
			print "<BODY onLoad=\"JavaScript:window.open('../doc/nyt_i_3.0.0.html','','statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,location=1');\">";

	}
#	db_modify("UPDATE regnskab set version = '$version' where db = '$db'",__FILE__ . " linje " . __LINE__);
}
?>
