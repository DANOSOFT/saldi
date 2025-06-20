<?php

// ----------debitor/ordre.php----------lap 3.5.0-----2015-01-23-------
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2015 DANOSOFT ApS
// ----------------------------------------------------------------------

// 2012-08-22 Tilrettet til NETS leverandørservice - søg 20120822
// 2012-12-13 Fejl i kostpris hvis køb er fordelt over flere ordrer. Søg 20121213
// 2013-02-27 Fejl i kostpris hvis køb er fordelt over flere ordrer. Søg 20130227
// 2013.03.20 Tilføjet mulighed for fravalg af logo på udskrift. Søg "PDF-tekst"
// 2013.05.06 Tilføjet kontrol for om det er tilføjet varenúmmer uden at gemme inden fakturering. Søg 20130506
// 2013.08.16 Projekt kommer ikke med ved kopiering af ordrer. Søg 20130816
// 2013.08.20 Div tekstændringer ved kreditnota.
// 2013.09.06 Varepris ved indsættelse af ny varelinje v. indtast af varenummer er nu '' istedet for 0.00. # søg 20130906
//		Bunder i ændring af funktionen opret_ordrelinje som nu ikke trækker pris fra varetabel hvis pris er sat til anden end '' 
// 2013.10.04 Indsat afrunding da fakturering ikke kunne foretages grindet diff på meget lille brøk (php fejl) Søg  20131004
// 2013.10.04 Addslashes erstattet med db_escape_string & stripslashes erstattet med HtmlEntities overalt. 
// 2013.10.17 Indsat opslag på ekstrafelter v. opreoprettelse m. kontonummer skrever i stedet for opslag.Søg 20131017
// 2014.01.12 Tilføjet individuelle mailenner/tekster og vedhæftning af bilag Søg mail_subject
// 2014.01.12 Tilføjet mulighed for at ændre debitor ved opslag søg 20140112
// 2014.01.12 Visning af kostpris forkert grundet ombytning af 100 & $valutakurs. Kun visning på ordre. Søg 20140116
// 2014.01.30 Omskrevet kostprisberegningen grundet risiko for fejl ved køb i en fremmed valuta og salg i en anden fremmed valuta, når 
//            salg gennemføres før bogføring af købsordre 
// 2014.03.24 Ændring på query til visning af notes fra adresser, så <tr> ikke vises med mindre der er note fra kundekort.Søg 20142403-1
// 2014.03.24 lavet variable til meta-tag og placeret det i head-tag. Se ordrefunc.php function sidehoved. Søg 20142403-2
// 2014.04.03	Momssats sættes til hvis ingen momsgruppe istedet for fejlmelding. 20140403
// 2014.04.14	PHR - Fjernet udkommentering af javascript.
// 2014.04.24	PHR - Rabat blev ikke fratrukket ved db beregning. 20140424
// 2014.04.24 PHR - Mail bilag felt skal kun vises på udvikling og ssl3.
// 2014.04.28 PHR - Formularsprog sættes før kald til opret_ordrelinje v. vareindsættelse fra opslag. Søg 20140428
// 2014.05.02 PK - Diverse html rettelser i faktura. Søg 20140502
// 2014.05.02 PK - Udkommenteret javascript er flyttet til 'top_header.php', 'top_header_sager.php' og 'online.php'
// 2014.05.05 PHR - sag_id,sagsnr,tilbudnr,datotid,nr&returside medtages ved kopiering og kreditering af ordre. Søg Sagsnr eller sag_id 
// 2014.05.05 PHR - Indsat $posnr,$varenr,$dkantal,$enhed,$dkpris,$dkprocent,$serienr,$varemomssats i kald til var2str så der kan anvendes variabler på ordrelinjer. Søg var2str
// 2014.05.07 PK - Tilføjet 'input type hidden' med sag_id, så det kommer med i submit. Søg 20140507-1
// 2014.05.07 PK - Indsat returside under slet, hvis der er sag_id: Søg 20140507-2
// 2014.05.15 PK - rettet colspan='10' til colspan='12' i Tabel 4.5.1, så det passer i bredden.
// 2014.07.16 PK - Tilbud bliver kopieret når det godkendes til ordrebekræftelse. Art i ordrer.php bliver lavet om til 'OT'. Søg 20140716
// 2014.07.30 PK - Oprettet en ny funktion 'opret_ordre_kopi' i ordrefunc.php. Søg 20140730
// 2014.08.26 PK - Kunde-kontakt kan vælges med dropdown eller skrives. Søg 20140826
// 2014.10.02 PHR - Tilføjet omvendt betalingspligt - Søg omvbet, omkunde og omvare.
// 2014.10.23 PHR - Fejl i kostprisberegning ved afsluttet ordre. Kostpris blev ganget med antal for at finde snitpris, men ikke divideret igen
// 2014.12.11 PHR- Negativt antal i kreditnotaer er nu tilladt.
// 2015.01.22 PHR - Tilføjet sætpriser -Søg saetpris & $saet.
// 2015.01.30 PHR - Rettet moms og sum sammentælling til 3 decimaler for større nøjagtighed. 20150130
// 2015.01.31 PHR - Mange rettelser til saetpriser.
// 2015.02.22 PHR	- Flere rettelser i forb. med sætpriser
// 2015.03.02 PHR	-	En ordre må ikke kunne godkendes hvis der ikke er sat debitor på. 
//				Samtidig er der ikke moms på priser når der ikke er debitor på selvom der er momssats på ordren. 20150302
// 2013.03.02 PHR - Afd blev nulsstillet ved ændring af debitor. : 20150302
// 2013.03.04 PHR - Fejl hvis intet ordre id : 20150304

@session_start();
$s_id=session_id();
$antal=array();$beskrivelse=array();$enhed=array();$ordreliste=array();$pris=array();$varenr=array();
$brugernavn=NULL;
$fakturadate=NULL;$fakturadato=NULL;$felt_1=NULL;$felt_2=NULL;$felt_3=NULL;$felt_4=NULL;$felt_5=NULL;$firmanavn=NULL;
$genfakt=NULL;$gl_id=NULL;$gruppe=NULL;
$konto_id=NULL;$kontonr=NULL;$kred_ord_id=NULL;$krediteret=NULL;
$lev_kontakt=NULL;$levdate=NULL;
$mail_falt=NULL;$modtaget=NULL;$moms=NULL;
$nextfakt=NULL;$notes=NULL;
$ordrenr=NULL;
$pbs=NULL;$prev_id=NULL;
$qtext=NULL;
$reserveret=NULL;
$sletslut=0;$sletstart=0;$status=NULL;
$valuta=NULL;$vis_lev_addr=NULL;

$modulnr=5;
$title="Kundeordre";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/var2str.php");
include("../includes/ordrefunc.php");
include("../includes/tid2decimal.php");

//print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>\n";
#print "<script type=\"text/javascript\" src=\"https://code.jquery.com/jquery-latest.min.js\"></script>\n";
#print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/arrowkey.js\"></script>\n";

$tidspkt=date("U");

#$id=if_isset($_GET['id']);
$id=if_isset($_GET['id']);
$funktion=if_isset($_GET['funktion']);
$sag_id=if_isset($_GET['sag_id']);
$konto_id=if_isset($_GET['konto_id']);

$q = db_SELECT("select box1,box4,box9,box12,box13 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__);
$r=db_fetch_array($q);
$incl_moms=$r['box1'];
$hurtigfakt=$r['box4'];
$negativt_lager=$r['box9'];
$procentfakt=$r['box12'];
list($default_procenttillag,$procentvare)=explode(chr(9),$r['box13']);
$default_procenttillag=str_replace(",",".",$default_procenttillag)*1;

$r=db_fetch_array(db_select("select box2 from grupper where art='OreDif'",__FILE__ . " linje " . __LINE__));
$difkto=$r['box2'];

$r=db_fetch_array(db_select("select box12 from grupper where art = 'POS' and kodenr = '2'",__FILE__ . "linje " . __LINE__));
$vis_saet=$r['box12'];

if (!$sag_id) {
	$id=if_isset($_GET['id']); 
	if (!$id) $id=if_isset($_GET['ordre_id']);
}
if ((!$id) && $funktion=='opret_ordre') {
	$id = opret_ordre($sag_id,$konto_id);
}
if ((!$id) && $funktion=='opret_ordre_kopi') { #20140730
	$id = opret_ordre_kopi($sag_id,$konto_id);
}

$returside=if_isset($_GET['returside']);
if ($sag_id) { // Returside sættes til 'sager' fra sager.php
#	$returside=urlencode("../sager/sager.php?funktion=vis_sag&amp;sag_id=$sag_id");
	$returside="../sager/sager.php?funktion=vis_sag&amp;sag_id=$sag_id";
}
if ($popup) $returside="../includes/luk.php";

if (($ret_tekst=if_isset($_GET['ret_tekst'])) && ($id=if_isset($_GET['id']))) tekstopslag($sort,$id);

if (($tekst_id=if_isset($_GET['tekst_id'])) && ($id=if_isset($_GET['id']))) {
	if ($slet_tekst=if_isset($_GET['slet_tekst'])) {
		db_modify("delete from ordretekster where id = '$slet_tekst'",__FILE__ . " linje " . __LINE__);
		header("location:ordre.php?id=$id&ret_tekst=$id"); exit();
	} elseif ($r=db_fetch_array(db_select("select tekst from ordretekster where id = '$tekst_id'",__FILE__ . " linje " . __LINE__))) {
#cho "insert into ordrelinjer (ordre_id,beskrivelse,posnr) values ('$id','".db_escape_string($r['tekst'])."','9999')<br>";
		db_modify("insert into ordrelinjer (ordre_id,beskrivelse,posnr) values ('$id','".db_escape_string($r['tekst'])."','9999')",__FILE__ . " linje " . __LINE__);
	}
}
if (($tekst_sag_id=if_isset($_GET['tekst_sag_id'])) && ($id=if_isset($_GET['id']))) {
	$r=db_fetch_array(db_select("select omfang from sager where id = '$tekst_sag_id'",__FILE__ . " linje " . __LINE__));
	db_modify("insert into ordrelinjer (ordre_id,beskrivelse,posnr) values ('$id','".db_escape_string($r['omfang'])."','9999')",__FILE__ . " linje " . __LINE__);
}
if ($ny_linjetekst=if_isset($_POST['ny_linjetekst'])) {
	$ny_linjetekst=db_escape_string($ny_linjetekst);	
	if (!$r=db_fetch_array(db_select("select id from ordretekster where tekst = '$ny_linjetekst'",__FILE__ . " linje " . __LINE__))){
		db_modify("insert into ordretekster (tekst) values ('$ny_linjetekst')",__FILE__ . " linje " . __LINE__);
	}
	if ($id=if_isset($_POST['id'])) {
#cho "insert into ordrelinjer (ordre_id,beskrivelse,posnr) values ('$id','$ny_linjetekst','9999'";
		db_modify("insert into ordrelinjer (ordre_id,beskrivelse,posnr) values ('$id','$ny_linjetekst','9999')",__FILE__ . " linje " . __LINE__);
	}
}
// Her hentes flere linjetekster til tilbud
if((isset($_POST['linjetekster']))&& ($id=if_isset($_POST['id']))) {
	foreach($_POST['linjetekster'] as $linjetekster){
		if($r=db_fetch_array(db_select("select tekst from ordretekster where id = '$linjetekster'",__FILE__ . " linje " . __LINE__))) {
			db_modify("insert into ordrelinjer (ordre_id,beskrivelse,posnr) values ('$id','".db_escape_string($r['tekst'])."','9999')",__FILE__ . " linje " . __LINE__);
		}
	} 
}
if ($tjek=if_isset($_GET['tjek'])){
	$query = db_select("select tidspkt,hvem from ordrer where status < 3 and id = $tjek and hvem != '$brugernavn' and hvem != ''",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query))	{
		if ($tidspkt-($row['tidspkt'])<3600 ){
			print "<BODY onLoad=\"javascript:alert('Ordren er i brug af $row[hvem]')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
		}
		else {
		db_modify("update ordrer set hvem = '$brugernavn',tidspkt='$tidspkt' where id = '$tjek'",__FILE__ . " linje " . __LINE__);}
	}
}
if (!$id) $id=if_isset($_GET['ordre_id']);
$sort=if_isset($_GET['sort']);
$fokus=if_isset($_GET['fokus']);
$submit=if_isset($_GET['funktion']);
$vis_kost=if_isset($_GET['vis_kost']);
if ($sort && $fokus && $submit=='vareOpslag') {
#	sidehoved($id,"ordre.php","","","Vareopslag");
	vareopslag($art,$sort,$fokus,$id,$vis_kost,$ref,0); 
} elseif ($sort && $fokus && $submit=='kontoOpslag') {
	sidehoved($id,"ordre.php","","","Kontoopslag");
	kontoopslag($art,$sort,$fokus,$id,$vis_kost,$ref,0); 
}
$bogfor=1;

if ($id) {
	$r=db_fetch_array(db_SELECT("select adresser.gruppe,ordrer.status,ordrer.sprog from ordrer,adresser where ordrer.id = '$id' and adresser.id=ordrer.konto_id",__FILE__ . " linje " . __LINE__));
	$status=$r['status']*1;
	$gruppe=$r['gruppe'];
	$formularsprog=$r['sprog']; #20140428
}
if(db_fetch_array(db_select("select id from grupper where art='DG' and kodenr='$gruppe' and box8='on'",__FILE__ . " linje " . __LINE__))) {
	$incl_moms=NULL; #hvis box8 er 'on' er det en b2b kunde og priser vises ex. moms
}

if (isset($_GET['vis_lev_addr']) && $id) {
	if ($_GET['vis_lev_addr']) db_modify ("update ordrer set vis_lev_addr='on' where id='$id'");
	else db_modify ("update ordrer set vis_lev_addr='' where id='$id'");

}
if (($kontakt=if_isset($_GET['kontakt']))&&($id)) db_modify("update ordrer set kontakt='$kontakt' where id=$id",__FILE__ . " linje " . __LINE__);

if (!strstr($fokus,'lev_') && isset($_GET['konto_id']) && is_numeric($_GET['konto_id'])) { # <- 2008.05.11
	$konto_id=$_GET['konto_id'];
	$query = db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$kontonr=$row['kontonr'];
		$firmanavn=db_escape_string($row['firmanavn']);
		$addr1=db_escape_string($row['addr1']);
		$addr2=db_escape_string($row['addr2']);
		$postnr=trim($row['postnr']);
		$bynavn = trim($row['bynavn']);
		if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
		$bynavn = db_escape_string($bynavn);
		$postnr=db_escape_string($postnr);
		$land=db_escape_string($row['land']);
		$betalingsdage=$row['betalingsdage'];
		$betalingsbet=$row['betalingsbet'];
		if ($vis_saet) {
			$betalingsdage=0;
			$betalingsbet='Kontant';
		}
		$cvrnr=db_escape_string($row['cvrnr']);
		$ean=db_escape_string($row['ean']);
		$institution=db_escape_string($row['institution']);
		$email=db_escape_string($row['email']);
		$mail_fakt=$row['mailfakt'];
		if ($row['pbs_nr']>0) {
			$pbs_nr=$row['pbs_nr'];
			$pbs='bs';
		}
		$kontakt=db_escape_string($row['kontakt']);
		$notes=db_escape_string($row['notes']);
		$gruppe=db_escape_string($row['gruppe']);
		$kontoansvarlig=db_escape_string($row['kontoansvarlig']);

		$lev_firmanavn=db_escape_string($row['lev_firmanavn']);
		$lev_addr1=db_escape_string($row['lev_addr1']);
		$lev_addr2=db_escape_string($row['lev_addr2']);
		$lev_postnr=trim($row['lev_postnr']);
		$lev_bynavn = trim($row['lev_bynavn']);
		if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
		$lev_bynavn = db_escape_string($lev_bynavn);
		$lev_postnr=db_escape_string($lev_postnr);
		$lev_land=db_escape_string($row['lev_land']);
		$lev_kontakt=db_escape_string($row['lev_kontakt']);

		(findtekst(244,$sprog_id) == findtekst(255,$sprog_id))?$felt_1=db_escape_string($row['felt_1']):$felt_1='';
		(findtekst(245,$sprog_id) == findtekst(256,$sprog_id))?$felt_2=db_escape_string($row['felt_2']):$felt_2='';
		(findtekst(246,$sprog_id) == findtekst(257,$sprog_id))?$felt_3=db_escape_string($row['felt_3']):$felt_3='';
		(findtekst(247,$sprog_id) == findtekst(258,$sprog_id))?$felt_4=db_escape_string($row['felt_4']):$felt_4='';
		(findtekst(248,$sprog_id) == findtekst(259,$sprog_id))?$felt_5=db_escape_string($row['felt_5']):$felt_5='';
	}
	if (!$afd && $id) { #20150302+04
		$r = db_fetch_array(db_select("select afd,ref from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$afd=$r['afd'];
	}

	if ($kontoansvarlig){
		$query = db_select("select navn,afd from ansatte where id='$kontoansvarlig'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$ansat_navn=$row['navn'];
		if (!$afd) $afd=$row['afd'];
	} else {
		$row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
		if ($row['ansat_id']) {
			$row = db_fetch_array(db_select("select navn,afd from ansatte where id = '$row[ansat_id]'",__FILE__ . " linje " . __LINE__));
			$ansat_navn=$row['navn'];
			if (!$afd) $afd=$row['afd'];
		}
	}
	if ($ansat_navn) $ref=$ansat_navn;
	$afd*=1;
	if ($gruppe){
		$r = db_fetch_array(db_select("select box1,box3,box4,box6,box8,box9 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
		$tmp= substr($r['box1'],1,1)*1;
		$rabatsats=$r['box6']*1;
		$formularsprog=$r['box4'];
		$valuta=$r['box3'];
		$b2b=$r['box8'];
		($r['box9'])?$omkunde='on':$omkunde='';
		$r = db_fetch_array(db_select("select box2 from grupper where art='SM' and kodenr='$tmp'",__FILE__ . " linje " . __LINE__));
		$momssats=$r['box2']*1;
	} elseif ($konto_id) {
			print "<BODY onLoad=\"javascript:alert('Debitoren er ikke tilknyttet en debitorgruppe')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?id=$konto_id&returside=../debitor/ordre.php&ordre_id=$id&fokus=$fokus?id=$id\">\n";
			exit;
	}
	if ($id) {
		$r=db_fetch_array(db_select("select konto_id from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		if ($konto_id && $r['konto_id']!=$konto_id) db_modify("update ordrer set konto_id='$konto_id',kontonr='$kontonr',kundeordnr='$kundeordnr',firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',land='$land',kontakt='$kontakt',lev_navn='$lev_navn',lev_addr1='$lev_addr1',lev_addr2='$lev_addr2',lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_kontakt='$lev_kontakt',vis_lev_addr='$vis_lev_addr',felt_1='$felt_1',felt_2='$felt_2',felt_3='$felt_3',felt_4='$felt_4',felt_5='$felt_5',betalingsdage='$betalingsdage',betalingsbet='$betalingsbet',cvrnr='$cvrnr',ean='$ean',momssats='$momssats',institution='$institution',email='$email',mail_fakt='$mail_fakt',udskriv_til='$udskriv_til',notes='$notes',tidspkt='$tidspkt',pbs='$pbs',afd='$afd',restordre='0' where id=$id",__FILE__ . " linje " . __LINE__); #20140112
	}
} elseif (strstr($fokus,'lev_') && isset($_GET['konto_id']) && is_numeric($_GET['konto_id']) && $id) { # <- 2011.03.29
	$konto_id=$_GET['konto_id'];
	$query = db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$lev_navn=db_escape_string($row['firmanavn']);
		$lev_addr1=db_escape_string($row['addr1']);
		$lev_addr2=db_escape_string($row['addr2']);
		$lev_postnr=trim($row['postnr']);
		$lev_bynavn = trim($row['bynavn']);
		if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
		$lev_bynavn = db_escape_string($lev_bynavn);
		$lev_postnr=db_escape_string($lev_postnr);
		$lev_kontakt=db_escape_string($row['kontakt']);
		db_modify("update ordrer set lev_navn='$lev_navn',lev_addr1='$lev_addr1',lev_addr2='$lev_addr2',lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_kontakt='$lev_kontakt' where id=$id",__FILE__ . " linje " . __LINE__);
	}
}
if ((!$id)&&($firmanavn)) {
	$r=db_fetch_array(db_select("select max(ordrenr) as ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc",__FILE__ . " linje " . __LINE__));
	$ordrenr=$r['ordrenr']+1;

	$ordredate=date("Y-m-d");
	($lev_firmanavn)?$vis_lev_addr='on':$vis_lev_addr='';	

	db_modify("insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,betalingsdage,betalingsbet,cvrnr,ean,institution,email,mail_fakt,notes,art,ordredate,momssats,hvem,tidspkt,ref,valuta,sprog,kontakt,pbs,afd,status,restordre,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,vis_lev_addr,felt_1,felt_2,felt_3,felt_4,felt_5,procenttillag,omvbet) values ($ordrenr,'$konto_id','$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$email','$mail_fakt','$notes','DO','$ordredate','$momssats','$brugernavn','$tidspkt','$ref','$valuta','$formularsprog','$kontakt','$pbs',$afd,'0','0','$lev_firmanavn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt','$vis_lev_addr','$felt_1','$felt_2','$felt_3','$felt_4','$felt_5','$default_procenttillag','$omkunde')",__FILE__ . " linje " . __LINE__);
	$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) $id=$row['id'];
} elseif($status<3 && $firmanavn) {
	$query = db_select("select tidspkt,firmanavn from ordrer where id=$id and hvem='$brugernavn'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		if (!$row['firmanavn']) { # <- 2009.05.13 Eller overskrives v. kontaktopslag.
			db_modify("update ordrer set kontonr='$kontonr',kundeordnr='$kundeordnr',firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',land='$land',lev_navn='$lev_navn',lev_addr1='$lev_addr1',lev_addr2='$lev_addr2',lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_kontakt='$lev_kontakt',vis_lev_addr='$vis_lev_addr',felt_1='$felt_1',felt_2='$felt_2',felt_3='$felt_3',felt_4='$felt_4',felt_5='$felt_5',betalingsdage='$betalingsdage',betalingsbet='$betalingsbet',cvrnr='$cvrnr',ean='$ean',momssats='$momssats',institution='$institution',email='$email',mail_fakt='$mail_fakt',udskriv_til='$udskriv_til',notes='$notes',hvem = '$brugernavn',tidspkt='$tidspkt',pbs='$pbs',afd='$afd',restordre='$restordre' where id=$id",__FILE__ . " linje " . __LINE__);
	 	}
	} else {
		$query = db_select("select hvem from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query) && $row['hvem']) {print "<BODY onLoad=\"javascript:alert('Ordren er overtaget af $row[hvem]')\">\n";}
		elseif ($row['hvem']) {
			print "<BODY onLoad=\"javascript:alert('Du er blevet smidt af')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
		}
	}
}
if ($id && $status<3 && isset($_GET['vare_id'])) {
	$vare_id[0]=$_GET['vare_id']*1;
	$query = db_select("select grupper.box6 as box6,ordrer.valuta as valuta,ordrer.ordredate as ordredate,ordrer.status as status from ordrer,adresser,grupper where ordrer.id='$id' and adresser.id=ordrer.konto_id and grupper.art='DG' and ".nr_cast("grupper.kodenr")."=adresser.gruppe",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	if ($row['status']>2) {
		print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">\n";
		exit;
	} else {
		if ($r=db_fetch_array(db_select("select id,varenr,samlevare,salgspris from varer where id = '$vare_id[0]'",__FILE__ . " linje " . __LINE__))) {
			$varenr[0]=$r['varenr'];
			gendan_saet($id);
			if ($vis_saet && $r['samlevare']) { 
				#opret_saet($id,$r['id'],$r['salgspris'],$momssats,1); 20150306
			} else {
#				gendan_saet($id);
#				$svar=opret_ordrelinje("$id","$vare_id[0]","$varenr[0]","1","","","","100","$art","$momsfri[0]","$posnr_ny[$x]","0","$incl_moms","","","0");
#				if (!is_numeric($svar)) print "<BODY onLoad=\"javascript:alert('$svar')\">";
#				else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">\n";
			}
		}
	}
}
$submit=if_isset($_POST['submit']);
if ($submit && $id = $_POST['id']) {
	$id = $_POST['id'];
	$sum=if_isset($_POST['sum']);
	$email = db_escape_string(trim($_POST['email']));
	$udskriv_til=$_POST['udskriv_til'];
	$mail_bilag=if_isset($_POST['mail_bilag']); # 20131122 Tilføjet 'mail_bilag'
	$genfakt=$_POST['genfakt'];
	$ean = db_escape_string(trim($_POST['ean']));
	if (strpos($email,"@") && strpos($email,".") && strlen($email)>5 && $udskriv_til=='email') $mail_fakt = 'on';
	elseif($udskriv_til=='email')	{
		print "<BODY onLoad=\"javascript:alert('e-mail ikke gyldig\\nFaktura kan ikke sendes som e-mail')\">\n";
		$udskriv_til="PDF";
	}
	if (($udskriv_til=='oioxml' || $udskriv_til=='oioubl') && strlen($ean)!=13) {
		print "<BODY onLoad=\"javascript:alert('EAN-nr. ikke gyldigt\\nIkke ".strlen($ean).", men 13 cifre i alt .\\nDer kan ikke udskrives til $udskriv_til.')\">\n";
		$udskriv_til="PDF";
	}
	if ($sum<0 && strstr($udskriv_til,'PBS')) {
	  $udskriv_til='PDF';
	  $pbs='';
	}
	if ($udskriv_til=='PBS_FI') $pbs="FI";
	if ($udskriv_til=='PBS_BS') $pbs="BS";
	if ($udskriv_til=='oioxml') $oioxlm="on";
	if ($udskriv_til=='oioubl') $oioubl="on";

	db_modify("update ordrer set email='$email',mail_fakt='$mail_fakt',pbs='$pbs',udskriv_til='$udskriv_til',mail_bilag='$mail_bilag' where id='$id'",__FILE__ . " linje " . __LINE__);
	if ($genfakt && $genfakt!='-') db_modify("update ordrer set nextfakt='".usdate($genfakt)."' where id='$id'",__FILE__ . " linje " . __LINE__);
	elseif ($genfakt=='-') {
		db_modify("update ordrer set nextfakt=NULL where id='$id'",__FILE__ . " linje " . __LINE__);
		$genfakt=NULL;
	}
}
	if (isset($_POST['opdat_mailtext'])) {
	$id = $_POST['id'];
	$mail_subj=db_escape_string(if_isset($_POST['mail_subj']));
	$mail_text=db_escape_string(str_replace("\n","<br>",if_isset($_POST['mail_text'])));
	db_modify("update ordrer set mail_subj='$mail_subj',mail_text='$mail_text' where id='$id'",__FILE__ . " linje " . __LINE__);
}

#cho "A Status $status<br>";

if ($submit) {
	$fokus=if_isset($_POST['fokus']);
#	$submit = $_POST['submit'];
	if (strstr($submit,"Faktur")) $submit="Fakturer";
	if (strstr($submit,"Del ordre")) $submit="del_ordre";
#	if ($submit=='Kredit&eacute;r') $sumbit='Fakturer';
	$ordrenr = $_POST['ordrenr'];
	$kred_ord_id = $_POST['kred_ord_id'];
	$art = $_POST['art'];
	$konto_id = if_isset($_POST['konto_id'])*1;
	$kontonr = $_POST['kontonr']*1;
	if ($id && $kontonr && !$konto_id) { #20150222
		$r=db_fetch_array(db_select("select id from adresser where kontonr = '$kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__));
		if ($konto_id=$r['id']) db_modify("update ordrer set konto_id='$r[id]' where id='$id'",__FILE__ . " linje " . __LINE__);
		else $kontonr='';
	}
	$firmanavn = db_escape_string(trim($_POST['firmanavn']));
	$addr1 = db_escape_string(trim($_POST['addr1']));
	$addr2 = db_escape_string(trim($_POST['addr2']));
	$postnr = trim($_POST['postnr']);
	$bynavn = trim($_POST['bynavn']);
	if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
	else $bynavn = db_escape_string($bynavn);
	$postnr = db_escape_string($postnr);
	$land = db_escape_string(trim($_POST['land']));
	$kontakt = db_escape_string(trim($_POST['kontakt']));
	$kundeordnr =	db_escape_string(trim($_POST['kundeordnr']));
	$lev_navn = db_escape_string(trim($_POST['lev_navn']));
	$lev_addr1 = db_escape_string(trim($_POST['lev_addr1']));
	$lev_addr2 = db_escape_string(trim($_POST['lev_addr2']));
	$lev_postnr = trim($_POST['lev_postnr']);
	$lev_bynavn = trim($_POST['lev_bynavn']);
	if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
	else $lev_bynavn = db_escape_string($lev_bynavn);
	$lev_kontakt = db_escape_string(trim($_POST['lev_kontakt']));
	$vis_lev_addr=if_isset($_POST['vis_lev_addr']);
	$felt_1 = db_escape_string(trim($_POST['felt_1']));
	$felt_2 = db_escape_string(trim($_POST['felt_2']));
	$felt_3 = db_escape_string(trim($_POST['felt_3']));
	$felt_4 = db_escape_string(trim($_POST['felt_4']));
	$felt_5 = db_escape_string(trim($_POST['felt_5']));
	if ($vis_saet) {
		$felt_2=usdecimal($felt_2,2);
		$felt_4=usdecimal($felt_4,2);
	}
	$ordredate = usdate(if_isset($_POST['ordredato']));
	$levdato = trim(if_isset($_POST['levdato']));
#	$genfakt = trim(if_isset($_POST['genfakt']));
	$fakturadato = trim(if_isset($_POST['fakturadato']));
	$cvrnr = db_escape_string(trim($_POST['cvrnr']));
	$procenttillag=usdecimal($procenttillag);
	$institution = db_escape_string(trim($_POST['institution']));
	$moms = if_isset($_POST['moms'])*1;
	$betalingsbet = $_POST['betalingsbet'];
	$betalingsdage = $_POST['betalingsdage']*1;
	$valuta = if_isset($_POST['valuta']);
	$ny_valuta = $_POST['ny_valuta'];
	$projekt = if_isset($_POST['projekt']);
	$formularsprog = if_isset($_POST['sprog']);
	$lev_adr = trim(if_isset($_POST['lev_adr']));
	$sum=if_isset($_POST['sum']);
	$linjeantal = $_POST['linjeantal'];
	$linje_id = $_POST['linje_id'];
	$kred_linje_id = if_isset($_POST['kred_linje_id']);
	$posnr = if_isset($_POST['posnr']);
	$sag_id = if_isset($_POST['sag_id']);
	$sagsnr = if_isset($_POST['sagsnr']);
	$tilbudnr = if_isset($_POST['tilbudnr']);
	$datotid = if_isset($_POST['datotid']);
	$nr = if_isset($_POST['nr']);
	$returside = if_isset($_POST['returside']);
	if ($status<3) $status = $_POST['status'];
#cho "B Status $status<br>";
	$godkend = if_isset($_POST['godkend']);
	$restordre = if_isset($_POST['restordre']);
	($restordre)? $restordre="1":$restordre="0";
	$omdan_t_fakt = if_isset($_POST['omdan_t_fakt']);
	$kreditnota = if_isset($_POST['kreditnota']);
	$ref = trim($_POST['ref']);
	$afd = $_POST['afd']*1;
	$fakturanr = trim(if_isset($_POST['fakturanr']));
#	$momssats = trim($_POST['momssats']);
	$momssats = usdecimal($_POST['momssats']);
	$procenttillag = usdecimal($_POST['procenttillag']);
	$mail_subj = db_escape_string(trim(if_isset($_POST['mail_subj'])));
	$mail_text=db_escape_string(str_replace("\n","<br>",if_isset($_POST['mail_text'])));
	$enhed = if_isset($_POST['enhed']);
	$folgevare = if_isset($_POST['folgevare']);
	$vare_id = $_POST['vare_id'];
	$antal = if_isset($_POST['antal']);
	$serienr = if_isset($_POST['serienr']);
	$samlevare = if_isset($_POST['samlevare']);
	$folgevare = if_isset($_POST['folgevare']);
	$momsfri = if_isset($_POST['momsfri']);
	$tidl_lev = if_isset($_POST['tidl_lev']);
	$kdo = if_isset($_POST['kdo']);
	$rabatart = if_isset($_POST['rabatart']);
	$varemomssats = $_POST['varemomssats'];
	$omkunde = $_POST['omkunde'];
	$omvbet = $_POST['omvbet'];
	$lev_varenr = $_POST['lev_varenr'];
	$saet = $_POST['saet'];
	$fast_db = $_POST['fast_db'];
	$samlet_pris=usdecimal(if_isset($_POST['samlet_pris']));
	$bruttosum=if_isset($_POST['bruttosum']);
	$ordresum=if_isset($_POST['ordresum']);
	if (strstr($submit,"Kred") && $status < 3) $submit="Fakturer";
	if (strstr($submit,'Modtag')) $submit="Lever";
	if ($art=='PO' && $status<3) {
		$art='DO';
		db_modify("update ordrer set art='DO' where id='$id'",__FILE__ . " linje " . __LINE__);
	}
	if (($godkend == "on") && ($status==0) && ($art=='DO' || $art == 'DK') && $sag_id) { # Kopi af original Tilbud. 20140716
		$r=db_fetch_array(db_select("select tilbudnr from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		//cho "tilbudnr: $r[tilbudnr]"; exit();
		
		$x=0;
		$q = db_select("select art from ordrer where tilbudnr = '$r[tilbudnr]'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$art_tjk[$x]=$r['art'];
			$x++;
		}
		//print_r ($art_tjk); exit();
		if (!$konto_id) { #20150302
			if ($incl_moms) $momssats=25;
			$status=0;
			$godkend=NULL;
		}
		
		if (!in_array("OT", $art_tjk)) {
		
		
			$r=db_fetch_array(db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
			
			if ($r['valutakurs']) {
				$valutakurs = "valutakurs,";
				$valutakurs_value = "'$r[valutakurs]',";
			} else {
				$valutakurs = NULL;
				$valutakurs_value = NULL;
			}
			if ($r['levdate']) {
				$levdate = "levdate,";
				$levdate_value = "'$r[levdate]',";
			} else {
				$levdate = NULL;
				$levdate_value = NULL;
			}
			if ($r['fakturadate']) {
				$fakturadate = "fakturadate,";
				$fakturadate_value = "'$r[fakturadate]',";
			} else {
				$fakturadate = NULL;
				$fakturadate_value = NULL;
			}
			if ($r['modtagelse']) {
				$modtagelse = "modtagelse,";
				$modtagelse_value = "'$r[motagelse]',";
			} else {
				$modtagelse = NULL;
				$modtagelse_value = NULL;
			}
			if ($r['kred_ord_id']) {
				$kred_ord_id = "kred_ord_id,";
				$kred_ord_id_value = "'$r[kred_ord_id]',";
			} else {
				$kred_ord_id = NULL;
				$kred_ord_id_value = NULL;
			}
			if ($r['nextfakt']) {
				$nextfakt = "nextfakt,";
				$nextfakt_value = "'$r[nextfakt]',";
			} else {
				$nextfakt = NULL;
				$nextfakt_value = NULL;
			}
			
			db_modify("insert into ordrer (konto_id,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,email,mail_fakt,udskriv_til,kundeordnr,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,ean,institution,betalingsbet,betalingsdage,kontonr,cvrnr,art,valuta,$valutakurs sprog,projekt,ordredate,$levdate $fakturadate notes,ordrenr,sum,momssats,status,ref,fakturanr,$modtagelse $kred_ord_id lev_adr,kostpris,moms,hvem,tidspkt,betalt,$nextfakt pbs,afd,mail,mail_cc,mail_bcc,mail_subj,mail_text,felt_1,felt_2,felt_3,felt_4,felt_5,vis_lev_addr,restordre,sag_id,tilbudnr,datotid,nr,returside,sagsnr,betalings_id,mail_bilag,dokument,procenttillag) values ('$r[konto_id]','$r[firmanavn]','$r[addr1]','$r[addr2]','$r[postnr]','$r[bynavn]','$r[land]','$r[kontakt]','$r[email]','$r[mail_fakt]','$r[udskriv_til]','$r[kundeordnr]','$r[lev_navn]','$r[lev_addr1]','$r[lev_addr2]','$r[lev_postnr]','$r[lev_bynavn]','$r[lev_kontakt]','$r[ean]','$r[institution]','$r[betalingsbet]','$r[betalingsdage]','$r[kontonr]','$r[cvrnr]','OT','$r[valuta]',$valutakurs_value '$r[sprog]','$r[projekt]','$r[ordredate]',$levdate_value $fakturadate_value '$r[notes]','$r[ordrenr]','$r[sum]','$r[momssats]','0','$r[ref]','$r[fakturanr]',$modtagelse_value $kred_ord_id_value '$r[lev_adr]','$r[kostpris]','$r[moms]','$r[hvem]','$r[tidspkt]','$r[betalt]',$nextfakt_value '$r[pbs]','$r[afd]','$r[mail]','$r[mail_cc]','$r[mail_bcc]','$r[mail_subj]','$r[mail_text]','$r[felt_1]','$r[felt_2]','$r[felt_3]','$r[felt_4]','$r[felt_5]','$r[vis_lev_addr]','$r[restordre]','$r[sag_id]','$r[tilbudnr]','$r[datotid]','$r[nr]','$r[returside]','$r[sagsnr]','$r[betalings_id]','$r[mail_bilag]','$r[dokument]','$r[procenttillag]')",__FILE__ . " linje " . __LINE__);
		
			$r=db_fetch_array(db_select("select max(id) as id from ordrer where sag_id = '$sag_id'",__FILE__ . " linje " . __LINE__));
			$ordre_id=$r['id'];
			//echo "ordrer_id: $ordre_id";exit();
			
			$x=0;
			$q=db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$x++;
				$ordrelinje_id[$x]=$r['id'];
				$varenr_OT[$x]=db_escape_string($r['varenr']);
				$beskrivelse_OT[$x]=db_escape_string(trim($r['beskrivelse']));
				$enhed_OT[$x]=db_escape_string($r['enhed']);
				$posnr_OT[$x]=$r['posnr'];
				$pris_OT[$x]=$r['pris'];
				$rabat_OT[$x]=$r['rabat'];
				$lev_varenr_OT[$x]=db_escape_string($r['lev_varenr']);
				//$ordre_id[$x]=$r['ordre_id'];
				$serienr_OT[$x]=db_escape_string($r['serienr']);
				$vare_id_OT[$x]=trim($r['vare_id']);
				$antal_OT[$x]=$r['antal'];
				$leveres_OT[$x]=$r['leveres'];
				$leveret_OT[$x]=$r['leveret'];
				$bogf_konto_OT[$x]=$r['bogf_konto'];
				$oprettet_af_OT[$x]=db_escape_string(trim($r['oprettet_af']));
				$bogfort_af_OT[$x]=db_escape_string(trim($r['bogfort_af']));
				$hvem_OT[$x]=db_escape_string($r['hvem']);
				$tidspkt_OT[$x]=db_escape_string($r['tidspkt']);
				$kred_linje_id_OT[$x]=$r['kred_linje_id'];
				$momsfri_OT[$x]=$r['momsfri'];
				$momssats_OT[$x]=$r['momssats'];
				$kostpris_OT[$x]=$r['kostpris'];
				$samlevare_OT[$x]=$r['samlevare'];
				$projekt_OT[$x]=db_escape_string($r['projekt']);
				$m_rabat_OT[$x]=$r['m_rabat'];
				$rabatgruppe_OT[$x]=$r['rabatgruppe'];
				$folgevare_OT[$x]=$r['folgevare'];
				$kdo_OT[$x]=$r['kdo'];
				$rabatart_OT[$x]=$r['rabatart'];
				$variant_id_OT[$x]=db_escape_string($r['variant_id']);
				$procent_OT[$x]=$r['procent'];
			}
			$ordrelinjeantal=$x;
			
			#cho "linjeantal: $ordrelinjeantal"; exit();
			for ($x=1;$x<=$ordrelinjeantal;$x++) {
				if ($ordrelinje_id[$x]) {
					//print_r($ordrelinje_id);exit();
					if ($pris_OT[$x] != NULL) {
						$pris_tbl[$x] = "pris,";
						$pris_value[$x] = "'$pris_OT[$x]',";
					} else {
						$pris_tbl[$x] = NULL;
						$pris_value[$x] = NULL;
					}
					if ($rabat_OT[$x] != NULL) {
						$rabat_tbl[$x] = "rabat,";
						$rabat_value[$x] = "'$rabat_OT[$x]',";
					} else {
						$rabat_tbl[$x] = NULL;
						$rabat_value[$x] = NULL;
					}
					if ($vare_id_OT[$x] != NULL) {
						$vare_id_tbl[$x] = "vare_id,";
						$vare_id_value[$x] = "'$vare_id_OT[$x]',";
					} else {
						$vare_id_tbl[$x] = NULL;
						$vare_id_value[$x] = NULL;
					}
					if ($antal_OT[$x] != NULL) {
						$antal_tbl[$x] = "antal,";
						$antal_value[$x] = "'$antal_OT[$x]',";
					} else {
						$antal_tbl[$x] = NULL;
						$antal_value[$x] = NULL;
					}
					if ($leveres_OT[$x] != NULL) {
						$leveres_tbl[$x] = "leveres,";
						$leveres_value[$x] = "'$leveres_OT[$x]',";
					} else {
						$leveres_tbl[$x] = NULL;
						$leveres_value[$x] = NULL;
					}
					if ($leveret_OT[$x] != NULL) {
						$leveret_tbl[$x] = "leveret,";
						$leveret_value[$x] = "'$leveret_OT[$x]',";
					} else {
						$leveret_tbl[$x] = NULL;
						$leveret_value[$x] = NULL;
					}
					if ($bogf_konto_OT[$x] != NULL) {
						$bogf_konto_tbl[$x] = "bogf_konto,";
						$bogf_konto_value[$x] = "'$bogf_konto_OT[$x]',";
					} else {
						$bogf_konto_tbl[$x] = NULL;
						$bogf_konto_value[$x] = NULL;
					}
					if ($kred_linje_id_OT[$x] != NULL) {
						$kred_linje_id_tbl[$x] = "kred_linje_id,";
						$kred_linje_id_value[$x] = "'$kred_linje_id_OT[$x]',";
					} else {
						$kred_linje_id_tbl[$x] = NULL;
						$kred_linje_id_value[$x] = NULL;
					}
					if ($momssats_OT[$x] != NULL) {
						$momssats_tbl[$x] = "momssats,";
						$momssats_value[$x] = "'$momssats_OT[$x]',";
					} else {
						$momssats_tbl[$x] = NULL;
						$momssats_value[$x] = NULL;
					}
					if ($kostpris_OT[$x] != NULL) {
						$kostpris_tbl[$x] = "kostpris,";
						$kostpris_value[$x] = "'$kostpris_OT[$x]',";
					} else {
						$kostpris_tbl[$x] = NULL;
						$kostpris_value[$x] = NULL;
					}
					if ($m_rabat_OT[$x] != NULL) {
						$m_rabat_tbl[$x] = "m_rabat,";
						$m_rabat_value[$x] = "'$m_rabat_OT[$x]',";
					} else {
						$m_rabat_tbl[$x] = NULL;
						$m_rabat_value[$x] = NULL;
					}
					if ($rabatgruppe_OT[$x] != NULL) {
						$rabatgruppe_tbl[$x] = "rabatgruppe,";
						$rabatgruppe_value[$x] = "'$rabatgruppe_OT[$x]',";
					} else {
						$rabatgruppe_tbl[$x] = NULL;
						$rabatgruppe_value[$x] = NULL;
					}
					if ($folgevare_OT[$x] != NULL) {
						$folgevare_tbl[$x] = "folgevare,";
						$folgevare_value[$x] = "'$folgevare_OT[$x]',";
					} else {
						$folgevare_tbl[$x] = NULL;
						$folgevare_value[$x] = NULL;
					}
						db_modify("insert into ordrelinjer (varenr,beskrivelse,enhed,posnr,$pris_tbl[$x] $rabat_tbl[$x] lev_varenr,ordre_id,serienr,$vare_id_tbl[$x] $antal_tbl[$x] $leveres_tbl[$x] $leveret_tbl[$x] $bogf_konto_tbl[$x] oprettet_af,bogfort_af,hvem,tidspkt,$kred_linje_id_tbl[$x] momsfri,$momssats_tbl[$x] $kostpris_tbl[$x] samlevare,projekt,$m_rabat_tbl[$x] $rabatgruppe_tbl[$x] $folgevare_tbl[$x] kdo,rabatart,variant_id,procent) values ('$varenr_OT[$x]','$beskrivelse_OT[$x]','$enhed_OT[$x]','$posnr_OT[$x]',$pris_value[$x] $rabat_value[$x] '$lev_varenr_OT[$x]','$ordre_id','$serienr_OT[$x]',$vare_id_value[$x] $antal_value[$x] $leveres_value[$x] $leveret_value[$x] $bogf_konto_value[$x] '$oprettet_af_OT[$x]','$bogfort_af_OT[$x]','$hvem_OT[$x]','$tidspkt_OT[$x]',$kred_linje_id_value[$x] '$momsfri_OT[$x]',$momssats_value[$x] $kostpris_value[$x] '$samlevare_OT[$x]','$projekt_OT[$x]',$m_rabat_value[$x] $rabatgruppe_value[$x] $folgevare_value[$x] '$kdo_OT[$x]','$rabatart_OT[$x]','$variant_id_OT[$x]','$procent_OT[$x]')",__FILE__ . " linje " . __LINE__);
						//db_modify("insert into ordrelinjer (varenr,beskrivelse,enhed,posnr,pris,rabat,lev_varenr,ordre_id,serienr,vare_id,antal,leveres,leveret,bogf_konto,oprettet_af,bogfort_af,hvem,tidspkt,kred_linje_id,momsfri,momssats,kostpris,samlevare,projekt,m_rabat,rabatgruppe,folgevare,kdo,rabatart,variant_id,procent) values ('$varenr[$x]','$beskrivelse[$x]','$enhed[$x]','$posnr[$x]','$pris[$x]','$rabat[$x]','$lev_varenr[$x]','$ordre_id','$serienr[$x]','$vare_id[$x]','$antal[$x]','$leveres[$x]','$leveret[$x]','$bogf_konto[$x]','$oprettet_af[$x]','$bogfort_af[$x]','$hvem[$x]','$tidspkt[$x]','$kred_linje_id[$x]','$momsfri[$x]','$momssats[$x]','$kostpris[$x]','$samlevare[$x]','$projekt[$x]','$m_rabat[$x]','$rabatgruppe[$x]','$folgevare[$x]','$kdo[$x]','$rabatart[$x]','$variant_id[$x]','$procent[$x]')",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	for ($x=0; $x<=$linjeantal;$x++) {
		$y="posn".$x;
		$posnr_ny[$x]=trim(if_isset($_POST[$y]));
		if ($posnr_ny[$x]!="-" && $posnr_ny[$x]!="->" && $posnr_ny[$x]!="<-" && !strpos($posnr_ny[$x],'+')) {
			if ($posnr_ny[$x]=='0') $posnr_ny[$x]="0,01";
			$posnr_ny[$x]=afrund((100*str_replace(",",".",$posnr_ny[$x])),0);
#			if ($x==0 && $posnr_ny[$x]) $posnr_ny[$x]*=100;  
		}
		$y="vare".$x;
		$varenr[$x]=db_escape_string(trim(if_isset($_POST[$y])));
		$y="dkan".$x;
		$dkantal[$x]=trim(if_isset($_POST[$y]));
		if ($dkantal[$x] || $dkantal[$x]=='0'){
			if ( strstr($dkantal[$x], ":") ) $dkantal[$x]=tid2decimal($dkantal[$x], "t");
			$tmp=usdecimal($dkantal[$x]);
			$antaldiff[$x]=$tmp-$antal[$x];
			$antal[$x]=usdecimal($dkantal[$x]);
			if ($art=='DK') $antal[$x]=$antal[$x]*-1;
			elseif (($tidl_lev[$x]<0) && ($tidl_lev[$x] < $antal[$x])) $antal[$x]=$tidl_lev[$x];
		} elseif(!$varenr[$x]) $vare_id[$x]=0;
		$y="leve".$x;
		if ($hurtigfakt=='on') $leveres[$x]=$antal[$x];
		else {
			$leveres[$x]=trim(if_isset($_POST[$y]));
			if ($leveres[$x]){
			$leveres[$x]=usdecimal($leveres[$x]);
			if ($art=='DK') {$leveres[$x]=$leveres[$x]*-1;}
			}
		}
		$y="beskrivelse".$x;
		$beskrivelse[$x]=db_escape_string(trim(if_isset($_POST[$y])));
		$y="pris".$x;
		if ($x!=0||(isset($_POST[$y]) && strlen($_POST[$y]))) {
			$pris[$x]=usdecimal($_POST[$y]);
			if ($incl_moms && !$momsfri[$x] && !$omvbet[$x]) {
				$pris[$x]=afrund(($pris[$x]/(100+$varemomssats[$x])*100),3);
			}
		}
		$y="raba".$x;
		$rabat[$x]=usdecimal(if_isset($_POST[$y]));
		if (($x>0)&&(!$rabat[$x]))$rabat=0;
		$y="proc".$x;
		$procent[$x]=usdecimal(if_isset($_POST[$y]));
		if (($x>0)&&(!$procent[$x]))$procent[$x]=100;
		$y="ialt".$x;
		$ialt[$x]=if_isset($_POST[$y]);
		if (($godkend == "on")&&($status==0)) {
			$leveres[$x]=$antal[$x];
			if (isset($linje_id[$x]) && $varenr[$x]) batch($linje_id[$x]);
		}
		if (!$sletslut && $posnr_ny[$x]=='->') $sletstart=$x;
		if ($sletstart && $posnr_ny[$x]=='<-') $sletslut=$x;
	}
	if (($sletstart)&&($sletslut)&&($sletstart<$sletslut)) {
		for ($x=$sletstart; $x<=$sletslut; $x++) $posnr_ny[$x]="-";
	}
}
if ($status<3 && $submit) {
	
	$r = db_fetch_array(db_select("select grupper.box6 as box6 from adresser,grupper where adresser.kontonr='$kontonr' and adresser.art='D' and grupper.art='DG' and ".nr_cast("grupper.kodenr")."=adresser.gruppe",__FILE__ . " linje " . __LINE__));
	$rabatsats=$r['box6']*1;
	if (strstr($submit,'Slet')) {
		db_modify("delete from ordrelinjer where ordre_id=$id",__FILE__ . " linje " . __LINE__);
		db_modify("delete from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
		if ($sag_id) { #20140507-2
			header("location:../sager/sager.php?funktion=vis_sag&sag_id=$sag_id");
		} else {
			if (!$returside) $returside=if_isset($_GET['returside']);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
			exit;
		}
	}
	if ($id && $ny_valuta!=$valuta && $status<3) {
		if ($ny_valuta!='DKK') {
			if ($r= db_fetch_array(db_select("select valuta.kurs from valuta,grupper where grupper.art='VK' and grupper.box1='$ny_valuta' and valuta.gruppe=".nr_cast("grupper.kodenr")." and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
				$valutakurs=$r['kurs']*1;
				if ($status<3) db_modify("update ordrer set valuta='$ny_valuta',valutakurs='$valutakurs' where id='$id'",__FILE__ . " linje " . __LINE__);
			} else {
				$tmp = dkdato($ordredate);
				print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">\n";
			}
		} else {
			$valutakurs = 100;
			db_modify("update ordrer set valuta='$ny_valuta',valutakurs='$valutakurs' where id='$id'",__FILE__ . " linje " . __LINE__);
		}
		$valuta=$ny_valuta;
	}
	transaktion("begin");
	if ($levdato) $levdate=usdate($levdato);
	if ($fakturadato) $fakturadate=usdate($fakturadato);
#	if (($konto_id)&&(!$ref)&&($status<3)) {
#		print "<BODY onLoad=\"javascript:alert('Vor ref. SKAL udfyldes')\">\n";
#	}
	$bogfor=1;
#cho "$godkend == \"on\"||$omdan_t_fakt == \"on\"||($status==0&&$hurtigfakt==\"on\"<br>";
	if ($godkend == "on"||$omdan_t_fakt == "on"||($status==0&&$hurtigfakt=="on")) $status++;
	if ($status==1) {
		if ($levdato) $levdate=usdate($levdato);
		if (!$levdate) {
			if ($hurtigfakt!='on') {
				print "<BODY onLoad=\"javascript:alert('Leveringsdato sat til dags dato.')\">\n";
				$levdate=date("Y-m-d");
			} else $levdate=$ordredate;;
		}
		elseif ($levdate<$ordredate) {
			print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">\n";
			$status=0;
		}
	}
	if (strstr($submit,"Kred")) {
		$art='DK';
		$query = db_select("select id from ordrer where kred_ord_id = $id",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$row[id]\">\n";
			exit;
		} elseif ($kred_ord_id) {
			$id='';
			$status=0;	
		} else {
			$kred_ord_id=$id;
			$id='';
			$status=0;
		}
	} elseif (strstr($submit,"Kopi")){
		$gl_id=$id;
		$id='';
		$status=0;
	}	elseif (!$art) $art='DO';
	if (strlen($ordredate)<6) $ordredate=date("Y-m-d");
	if (($kontonr&&!$firmanavn)||($kontonr&&$gl_id)) {
		$query = db_select("select * from adresser where kontonr = '$kontonr' and art='D'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
		$konto_id=$row['id'];
		$firmanavn=db_escape_string($row['firmanavn']);
		$addr1=db_escape_string($row['addr1']);
			$addr2=db_escape_string($row['addr2']);
			$postnr=db_escape_string($row['postnr']);
			$bynavn=db_escape_string($row['bynavn']);
			$land=db_escape_string($row['land']);
			$kontakt=db_escape_string($row['kontakt']);
			$betalingsdage=$row['betalingsdage'];
			$betalingsbet=$row['betalingsbet'];
			$cvrnr=$row['cvrnr'];
			$notes=db_escape_string($row['notes']);
			$email=$row['email'];
			$ean=$row['ean'];
			$institution=$row['institution'];
			$mail_fakt=$row['mailfakt'];
			$gruppe=$row['gruppe'];
			(findtekst(244,$sprog_id) == findtekst(255,$sprog_id))?$felt_1=db_escape_string($row['felt_1']):$felt_1=''; #20131017
			(findtekst(245,$sprog_id) == findtekst(256,$sprog_id))?$felt_2=db_escape_string($row['felt_2']):$felt_2='';
			(findtekst(246,$sprog_id) == findtekst(257,$sprog_id))?$felt_3=db_escape_string($row['felt_3']):$felt_3='';
			(findtekst(247,$sprog_id) == findtekst(258,$sprog_id))?$felt_4=db_escape_string($row['felt_4']):$felt_4='';
			(findtekst(248,$sprog_id) == findtekst(259,$sprog_id))?$felt_5=db_escape_string($row['felt_5']):$felt_5='';

			if ($gruppe) {
				$r = db_fetch_array(db_select("select box1,box3,box4,box6 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
				$tmp= substr($r['box1'],1,1);
				$std_rabat=$r['box6']*1;
				if (!$gl_id) {# valuta & sprog skal beholdes v. ordrekopiering.
					$formularsprog=$r['box4'];
			 		$valuta=$r['box3'];
				}
				if ($r=db_fetch_array(db_select("select box2 from grupper where art='SM' and kodenr='$tmp'",__FILE__ . " linje " . __LINE__))) { #20130227
					$momssats=$r['box2'];
				} elseif ($tmp) { #20140403 tilføjet if ($tmp)
					print "<BODY onLoad=\"javascript:alert('Debitorgrupper forkert opsat')\">\n";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">\n";
					exit;
				} else  $momssats=0; #20140403
			} else {
				print "<BODY onLoad=\"javascript:alert('Debitoren er ikke tilknyttet en debitorgruppe')\">\n";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?id=$konto_id&returside=../debitor/ordre.php&ordre_id=$id&fokus=$fokus?id=$id\">\n";
				exit;
			}
		}
	}
	if ((!$id)&&($konto_id)&&($firmanavn)){
		$query = db_select("select ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $ordrenr=$row['ordrenr']+1;
		else $ordrenr=1;
		$qtext="insert into ordrer (ordrenr,konto_id,kontonr,kundeordnr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,betalingsdage,betalingsbet,cvrnr,ean,institution,email,mail_fakt,notes,art,ordredate,momssats,status,ref,lev_adr,valuta,projekt,sprog,pbs,afd,restordre,felt_1,felt_2,felt_3,felt_4,felt_5) values ($ordrenr,'$konto_id','$kontonr','$kundeordnr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$lev_navn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$email','$mail_fakt','$notes','$art','$ordredate','$momssats',$status,'$ref','$lev_adr','$valuta','$masterprojekt','$formularsprog','$pbs','$afd','0','$felt_1','$felt_2','$felt_3','$felt_4','$felt_5')";#20131017 
		db_modify($qtext,__FILE__ . " linje " . __LINE__);
		$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$id=$row['id'];
			if ($gl_id) {
				$r=(db_fetch_array(db_select("select levdate,ordredate,fakturadate,nextfakt from ordrer where id='$gl_id'",__FILE__ . " linje " . __LINE__)));
				if ($r['nextfakt']) {
					$nextfakt=find_nextfakt($r['fakturadate'],$r['nextfakt']);
					db_modify("update ordrer set levdate='$r[nextfakt]',fakturadate='$r[nextfakt]',nextfakt='$nextfakt',ordredate='$r[ordredate]' where id = $id",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	elseif(($firmanavn)&&($status<3)) {
		$sum=0;
		for($x=1;$x<=$linjeantal;$x++) {
		#			$antal[$x]*=1;
			$vare_id[$x]*=1;
			$r=db_fetch_array(db_select("select gruppe,beholdning from varer where id = $vare_id[$x]",__FILE__ . " linje " . __LINE__));
			$vare_grp[$x]=$r['gruppe'];
			$beholdning[$x]=$r['beholdning'];
			if (!$varenr[$x]) {$antal[$x]=0; $pris[$x]=0; $rabat[$x]=0;}
			elseif ((($antal[$x]>=0)&&($leveres[$x]<0))||(($antal[$x]<=0)&&($leveres[$x]>0))) {
				print "<BODY onLoad=\"javascript:alert('Der skal v&aelig;re samme fortegen i antal og l&eacute;ver! (Position $posnr_ny[$x] nulstillet)')\">\n";
				$leveres[$x]=0;
			} elseif ($vare_id[$x]) {
				if ($art=='DK') { # DK = Kreditnota
#					if ($antal[$x]>0) {
#						$antal[$x]=$antal[$x]*-1;
#						print "<BODY onLoad=\"javascript:alert('Der kan ikke krediteres et negativt antal. Antal reguleret (Varenr: $varenr[$x])')\">\n";
#					}
					$kred_linje_id[$x]*=1;
					if (!$folgevare[$x] || $folgevare[$x]>0) {
						$r=db_fetch_array(db_select("select antal from ordrelinjer where id = $kred_linje_id[$x] and vare_id=$vare_id[$x]",__FILE__ . " linje " . __LINE__)); #Vare_id er med for ikke at taelle delvarer med v. samlevarer.
						if ($antal[$x]+$r['antal']<0) {
							$antal[$x]=$r['antal']*-1;
							print "<BODY onLoad=\"javascript:alert('Der kan h&oslash;jst krediteres ".dkdecimal($row[antal]).". Antal reguleret (Varenr: $varenr[$x])')\">\n";
						}
					}
					if ($antaldiff[$x]) db_modify("update ordrelinjer set antal=$antal[$x] where id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
				} elseif (($antal[$x]<0)&&($kred_linje_id[$x]>0)) {
					$query = db_select("select antal from ordrelinjer where id = $kred_linje_id[$x] and vare_id=$vare_id[$x]",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					if ($antal[$x]+$row['antal']<0) {
						$antal[$x]=$row['antal']*-1;
						print "<BODY onLoad=\"javascript:alert('Der kan h&oslash;jst tages $row[antal] retur. Antal reguleret (Varenr: $varenr[$x])')\">\n";
					}
				} elseif ($antaldiff[$x] && (!$samlevare[$x] || abs($antal[$x]))) {
					$svar=opret_ordrelinje($id,"$vare_id[$x]","$varenr[$x]","$antaldiff[$x]","$beskrivelse[$x]","$pris[$x]","$rabat[$x]","$procent[$x]","$art","$momsfri[$x]","$posnr_ny[$x]","$linje_id[$x]","$incl_moms","","$rabatart[$x]","0");
					if (!is_numeric($svar)) print "<BODY onLoad=\"javascript:alert('$svar')\">";
				}
				if (!$negativt_lager && $leveres[$x]>$beholdning[$x] && (!$hurtigfakt || $submit=="Fakturer") && $leveres[$x]>$beholdning[$x] && $leveres[$x]>0 &&
						db_fetch_array(db_select("select id from grupper where kodenr='$vare_grp[$x]' and art='VG' and box8='on'",__FILE__ . " linje " . __LINE__))) {
					if ($beholdning[$x]<=0) $leveres[$x]=0;
					else $leveres[$x]=$beholdning[$x]*1;
					$tmp=$posnr_ny[$x]/100;
					if ($hurtigfakt) {
						$tekst="Lagerbeholdning: ".dkdecimal($beholdning[$x]).". Der kan h&oslash;jest leveres $leveres[$x] fra linjen med pos.nr. $tmp)";
					} else{
						$tekst="Lagerbeholdning: ".dkdecimal($beholdning[$x]).". Der kan h&oslash;jest leveres $leveres[$x]. Antal reguleret (pos.nr. $tmp)";
					}
					Print "<BODY onLoad=\"javascript:alert('$tekst')\">\n";
					if ($submit=="Fakturer") $submit="Gem";
				}
				$tidl_lev[$x]=0;
				$query = db_select("select antal from batch_salg where linje_id = $linje_id[$x] and vare_id=$vare_id[$x]",__FILE__ . " linje " . __LINE__); #Vare_id er med for ikke at taelle delvarer med v. samlevarer.
				while ($row = db_fetch_array($query)) {$tidl_lev[$x]=$tidl_lev[$x]+$row['antal'];}
				if ((($tidl_lev[$x]<0)&&($antal[$x]>$tidl_lev[$x]))||(($tidl_lev[$x]>0)&&($antal[$x]<$tidl_lev[$x]))){
					$antal[$x]=$tidl_lev[$x];
					print "<BODY onLoad=\"javascript:alert('Der er allerede leveret $tidl_lev[$x]. Antal reguleret (varenr. $varenr[$x])')\">\n";
				}
				elseif ($antal>0) {
					if (($tidl_lev[$x]<$antal[$x])&&($status>1)) {
						if ($omdan_t_fakt == "on") {print "<BODY onLoad=\"javascript:alert('Du kan ikke fakturere, f&oslash;r alt er leveret')\">\n";}
						$status=1;
					}
					$query = db_select("select antal from reservation where linje_id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
					while ($row = db_fetch_array($query)) {$reserveret[$x]=$reserveret[$x]+$row['antal'];}
					if (($antal[$x]<$tidl_lev[$x]+$reserveret[$x])&&($antal[$x]>0)) {
						$diff=$tidl_lev[$x]+$reserveret[$x]-$antal[$x];
						while ($diff>0) {
							$query = db_select("select * from reservation where linje_id = $linje_id[$x] order by batch_kob_id desc",__FILE__ . " linje " . __LINE__);
							if ($row = db_fetch_array($query)) {
								if ($diff < $row['antal']) {
									$temp = $row['antal'] - $diff;
									if ($row['batch_kob_id']) $dbq="update reservation set antal = '$temp' where linje_id='$linje_id[$x]' and batch_kob_id='$row[batch_kob_id]' and antal='$row[antal]' and vare_id='$row[vare_id]'";
									else $dbq="update reservation set antal = '$temp' where linje_id='$linje_id[$x]' and antal='$row[antal]' and vare_id='$row[vare_id]'";
									db_modify("$dbq",__FILE__ . " linje " . __LINE__);
									$diff=0;
								} elseif ($diff >= $row['antal']) {

									if ($row['batch_kob_id']) $dbq="delete from reservation where linje_id='$linje_id[$x]' and batch_kob_id='$row[batch_kob_id]' and antal='$row[antal]' and vare_id='$row[vare_id]'";
									else $dbq="delete from reservation where linje_id='$linje_id[$x]' and antal='$row[antal]' and vare_id='$row[vare_id]'";
									db_modify("$dbq",__FILE__ . " linje " . __LINE__);
									$diff=$diff - $row['antal'];
								}
							} else $diff=0;
						}
					}
				}
				if (!isset($modtaget[$x]))$modtaget[$x]=0;
				$query = db_select("select antal from batch_salg where linje_id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
				while ($row = db_fetch_array($query)) $modtaget[$x]=$modtaget[$x]+$row['antal'];
				if (($antal[$x]>$modtaget[$x])&&($modtaget[$x]<0)) {
					$antal[$x]=$modtaget[$x];
					print "<BODY onLoad=\"javascript:alert('Der er allerede modtaget $temp. Antal reguleret (varenr. $varenr[$x])')\">\n";
				}
			}
			if (!is_numeric($posnr_ny[$x]) && $posnr_ny[$x]=='-') {
			if ($vare_id[$x]) {
					$query = db_select("select * from batch_kob where linje_id = $linje_id[$x] and ordre_id=$id and vare_id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
					if ($row = db_fetch_array($query)) print "<BODY onLoad=\"javascript:alert('Du kan ikke slette en varelinje, n&aring;r der &eacute;r modtaget en eller flere varer fra linjen.')\">\n";
					else {
						$query = db_select("select * from batch_salg where linje_id = $linje_id[$x] and ordre_id=$id and vare_id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
						if ($row = db_fetch_array($query)) print "<BODY onLoad=\"javascript:alert('Du kan ikke slette en varelinje, n&aring;r der &eacute;r leveret en eller flere varer fra linjen.')\">\n";
						elseif ($linje_id[$x]) {
							db_modify("delete from reservation where linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
							db_modify("delete from reservation where batch_salg_id='-$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
							db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
							if ($folgevare[$x]) {
								$tmp=$linje_id[$x]*-1;
								db_modify("delete from ordrelinjer where folgevare='$tmp' and ordre_id='$id'",__FILE__ . " linje " . __LINE__);
							}
							db_modify("delete from ordrelinjer where samlevare='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
							db_modify("update serienr set salgslinje_id = 0 where salgslinje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
						}
					}
				} elseif ($linje_id[$x]) {
					db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
				}
			} elseif ((!strstr($submit,"Kopi"))&&(!strstr($submit,"Udskriv"))&&(!strstr($submit,"Send"))) {
				if ((!strpos($posnr_ny[$x],'+'))&&($id)) {
					$posnr_ny[$x]=afrund($posnr_ny[$x],0);
					if ($posnr_ny[$x]>=1) {
#						if ($varenr[$x]=='R') $posnr_ny[$x]+=1000;
						db_modify("update ordrelinjer set posnr='$posnr_ny[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					} else print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte tegnet - (minus) i feltet 'Pos' for at slette en varelinje.')\">\n";
				}
#				if ($vare_id[$x] && $r=db_fetch_array(db_select("SELECT box6 FROM grupper WHERE kodenr='$vare_grp[$x]'and art='VG'",__FILE__ . " linje " . __LINE__))) {
#					if (($r['box6']!=NULL)&&($rabat[$x]>$r['box6'])) {
#						$rabat[$x]=$r['box6'];
#						print "<BODY onLoad=\"javascript:alert('H&oslash;jeste rabat for varenummer $varenr[$x] er $rabat[$x]%')\">\n";
#					}
#				}
				if ($linje_id[$x]) {
					if (!$antal[$x]) $antal[$x]=0;
					$sum=$sum+($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
					if (!$leveres[$x]) $leveres[$x]=0;
					if (!$rabat[$x]) $rabat[$x]='0';
					if ($projekt[0]) $projekt[$x]=$projekt[0];
					else $projekt[$x]=$projekt[$x];
					if ($saet[$x] || $varenr[$x]=='R') $qtxt="update ordrelinjer set leveres='$leveres[$x]' where id='$linje_id[$x]'";
					else $qtxt="update ordrelinjer set varenr='$varenr[$x]',beskrivelse='$beskrivelse[$x]',leveres='$leveres[$x]',pris='$pris[$x]',rabat='$rabat[$x]',procent='$procent[$x]',projekt='$projekt[$x]',kdo='$kdo[$x]',omvbet='$omvbet[$x]',saet='0',samlevare='$samlevare[$x]' where id='$linje_id[$x]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					if ($samlevare[$x]) {
						if ($antal[$x]) {
							$q=db_select("SELECT id,antal FROM ordrelinjer WHERE samlevare = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
							while($r=db_fetch_array($q)) {
								db_modify("update ordrelinjer set leveres=antal*$leveres[$x]/$antal[$x] where id='$r[id]'",__FILE__ . " linje " . __LINE__); 
							}
						} else db_modify("update ordrelinjer set leveres='0' where samlevare='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					}
				}
				if ((strpos($posnr_ny[$x],'+'))&&($id)) indsaet_linjer($id,$linje_id[$x],$posnr_ny[$x]);
			}
#			if (strlen($fakturadate)>5){db_modify("update ordrer set fakturadate='$fakturadate' where id=$id");}
		}
		if (($posnr_ny[0])&&(!strstr($submit,'Opslag'))) {
			if ($varenr[0]) {
				$samlevare[0]='';
				if ($vis_saet) {
					$r=db_fetch_array(db_select("SELECT id,samlevare,salgspris FROM varer WHERE varenr = '$varenr[0]'",__FILE__ . " linje " . __LINE__));
					$samlevare[0]=$r['samlevare'];
				}
				if ($vis_saet && $samlevare[0]) {
					$salgspris[0]=$r['salgspris']+$r['salgspris']*$momssats/100;
					opret_saet($id,$r['id'],$salgspris[0],$momssats,$antal[0]);
				} else {
					$svar=opret_ordrelinje($id,"",$varenr[0],$antal[0],$beskrivelse[0],$pris[0],$rabat[0],$procent[0],$art,"$momsfri[0]","$posnr_ny[0]","0","$incl_moms","","","0");
					if (!is_numeric($svar)) print "<BODY onLoad=\"javascript:alert('$svar')\">";
				}
			} elseif ($beskrivelse[0] && is_numeric($posnr_ny[0])) db_modify("insert into ordrelinjer (ordre_id,posnr,beskrivelse) values ('$id','$posnr_ny[0]','$beskrivelse[0]')",__FILE__ . " linje " . __LINE__);
		}
		if ($id) {
			$r = db_fetch_array(db_select("select tidspkt,hvem from ordrer where status < 3 and id = $id and hvem != '$brugernavn'",__FILE__ . " linje " . __LINE__));
			$tidspkt=trim($r['tidspkt']);
			if ($tidspkt && $row['hvem'])	{
				if ($tidspkt-($row['tidspkt'])<3600 && $row['hvem']) {
					print "<BODY onLoad=\"javascript:alert('Orderen er overtaget af $row[hvem]')\">\n";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
				}
			} else {
				$tmp="";
				if (strlen($levdate)>6) $tmp=",levdate='$levdate'";
				if (strlen($fakturadate)>6) $tmp=$tmp.",fakturadate='$fakturadate'";
				if ($genfakt) $tmp=$tmp.",nextfakt='".usdate($genfakt)."'";
				$afd*=1;
				$opdat="update ordrer set kontonr='$kontonr',kundeordnr='$kundeordnr',firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',land='$land',kontakt='$kontakt',lev_navn='$lev_navn',lev_addr1='$lev_addr1',lev_addr2='$lev_addr2',lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_kontakt='$lev_kontakt',vis_lev_addr='$vis_lev_addr',felt_1='$felt_1',felt_2='$felt_2',felt_3='$felt_3',felt_4='$felt_4',felt_5='$felt_5',betalingsdage='$betalingsdage',betalingsbet='$betalingsbet',cvrnr='$cvrnr',momssats='$momssats',procenttillag='$procenttillag',ean='$ean',institution='$institution',email='$email',mail_fakt='$mail_fakt',udskriv_til='$udskriv_til',notes='$notes',ordredate='$ordredate',status=$status,ref='$ref',fakturanr='$fakturanr',lev_adr='$lev_adr',hvem = '$brugernavn',tidspkt='$tidspkt',projekt='$projekt[0]',sprog='$formularsprog',pbs='$pbs',afd='$afd',restordre='$restordre',mail_subj='$mail_subj',mail_text='$mail_text' $tmp where id=$id";
				db_modify($opdat,__FILE__ . " linje " . __LINE__);
				if ($vis_saet) db_modify("update ordrer set felt_5 = '$afd' where id = '$id'",__FILE__ . " linje " . __LINE__);
			}
		}
	}
	if ($samlet_pris && $ordresum && $samlet_pris != $ordresum) {
		gendan_saet($id);
		$samlet_rabat=0;
		$r=db_fetch_array(db_select("select varenr from ordrelinjer where varenr='R' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
		$rvnr=$r['varenr'];
		if ($r=db_fetch_array(db_select("select id from varer where varenr='R'",__FILE__ . " linje " . __LINE__))) {
			$rvid=$r['id'];
			$rvnr='R';
			db_modify("delete from ordrelinjer where vare_id='$rvid' and ordre_id='$id'",__FILE__ . " linje " . __LINE__);
			$bruttosum=0;
			$bruttosaetsum=0;
#cho "select * from ordrelinjer where ordre_id = '$id' and varenr != 'R'<br>";
			$q=db_select("select * from ordrelinjer where ordre_id = '$id' and varenr != 'R'",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				$bruttosum+=afrund($r['antal']*($r['pris']+$r['pris']*$r['momssats']/100),3);
				if (!$r['saet']) $bruttosaetsum+=$r['antal']*($r['pris']+$r['pris']*$r['momssats']/100);
				elseif ($r['samlevare']) {
					list($tmp)=explode("|",$r['lev_varenr']);
					$bruttosaetsum+=$tmp;
				}	
			}
			$samlet_rabat=$bruttosum-$samlet_pris;
			($bruttosum)?$samlet_rabatpct=afrund(($samlet_rabat)*100/$bruttosum,3):$samlet_rabatpct=0;
		} else fejl($id,'Intet varenummer "R" til kontering af øredifferencer ved rabat');
			if ($bruttosaetsum==$samlet_pris)$samlet_rabatpct=0;
		if ($samlet_rabatpct) {
#cho "update ordrelinjer set rabat=$samlet_rabatpct where ordre_id = '$id'<br>";
#xit;
			db_modify("update ordrelinjer set rabat=$samlet_rabatpct where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	}
#xit;
/* 20150306
	if ($id) {
		$x=0;
		$q=db_select("select id,posnr from ordrelinjer where ordre_id = $id order by saet,posnr,id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$x++;
			db_modify("update ordrelinjer set posnr = '$x' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
		}
	}
*/	
# exit;
	transaktion("commit");
}
#xit;
#cho "Status $status<br>";
########################## KOPIER #################################
if ((strstr($submit,'Kopi'))||(strstr($submit,'Kred')))	{
#cho "tilbudnr: $tilbudnr<br>"; exit();
	if (strstr($submit,"Kred")) {
		$art='DK';
		$query = db_select("select id from ordrer where kred_ord_id = $id",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$row[id]\">\n";
			exit;
		} elseif ($kred_ord_id) {
			$id='';
			$status=0;	
		} else {
			$kred_ord_id=$id;
			$id='';
			$status=0;
		}
	} elseif (strstr($submit,"Kopi")){
		$gl_id=$id;
		$id='';
		$status=0;
	}
	if ((!$id)&&($konto_id)&&($firmanavn)){
		$query = db_select("select ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $ordrenr=$row['ordrenr']+1;
		else $ordrenr=1;
		(!$sag_id)?$tilbudnr*=1:$tilbudnr;
		$qtext="insert into ordrer (ordrenr,konto_id,kontonr,kundeordnr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,betalingsdage,betalingsbet,cvrnr,ean,institution,email,mail_fakt,notes,art,ordredate,momssats,status,ref,lev_adr,valuta,projekt,sprog,pbs,afd,restordre,procenttillag,sag_id,sagsnr,tilbudnr,datotid,nr,returside,omvbet) values ($ordrenr,'$konto_id','$kontonr','$kundeordnr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$lev_navn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$email','$mail_fakt','$notes','$art','$ordredate','$momssats',$status,'$ref','$lev_adr','$valuta','$projekt[0]','$formularsprog','$pbs','$afd','0','$procenttillag','$sag_id','$sagsnr','$tilbudnr','$datotid','$nr','$returside','$omkunde')";
		db_modify($qtext,__FILE__ . " linje " . __LINE__);
		$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$id=$row['id'];
			if ($gl_id) {
				$r=(db_fetch_array(db_select("select levdate,ordredate,fakturadate,nextfakt from ordrer where id='$gl_id'",__FILE__ . " linje " . __LINE__)));
				if ($r['nextfakt']) {
					$nextfakt=find_nextfakt($r['fakturadate'],$r['nextfakt']);
					db_modify("update ordrer set levdate='$r[nextfakt]',fakturadate='$r[nextfakt]',nextfakt='$nextfakt',ordredate='$r[ordredate]' where id = $id",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}

	if ((strstr($submit,'Kred'))&&($kred_ord_id)) db_modify("update ordrer set kred_ord_id='$kred_ord_id' where id='$id'",__FILE__ . " linje " . __LINE__);
	for($x=1; $x<=$linjeantal; $x++) {
		if (!$vare_id[$x] && $antal[$x] && $varenr[$x]) {
			$query = db_select("select id from varer where varenr = '$varenr[$x]'",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {$vare_id[$x]=$row['id'];}
		}
		if ($vare_id[$x]){
			(strstr($submit,'Kopi'))?$tmp=$antal[$x]*1:$tmp=$antal[$x]*-1;
			(strstr($submit,'Kred'))?$tmp2=$linje_id[$x]:$tmp2='0';
			if (!$momsfri[$x] && !$varemomssats[$x]) $varemomssats[$x]=$momssats;
			if ($varemomssats[$x] > $momssats) $varemomssats[$x]=$momssats;
			if ($momsfri[$x] || $omvbet[$x]) $varemomssats[$x]=0;
			if ($incl_moms) $pris[$x]+=$pris[$x]*$varemomssats[$x]/100;

			if ((!$kdo[$x] || strstr($submit,'Kred')) && (!$folgevare[$x] || $folgevare[$x]>=0)) {
#echo "$id,$vare_id[$x],$varenr[$x],$tmp,$beskrivelse[$x],$pris[$x],$rabat[$x],$procent[$x],$art,$momsfri[$x],$posnr[$x],$tmp2,$incl_moms,,$rabatart[$x],1,$saet[$x],$fast_db[$x],$lev_varenr[$x]<br>";
				$svar=opret_ordrelinje($id,"$vare_id[$x]","$varenr[$x]","$tmp","$beskrivelse[$x]","$pris[$x]","$rabat[$x]","$procent[$x]","$art","$momsfri[$x]","$posnr[$x]","$tmp2","","","$rabatart[$x]","1",$saet[$x],$fast_db[$x],$lev_varenr[$x]);
				if (!is_numeric($svar)) print "<BODY onLoad=\"javascript:alert('$svar')\">";
				elseif ($folgevare[$x] || $projekt[$x] || $fast_db[$x]) {
					if ($r=db_fetch_array(db_select("select max(id) as id from ordrelinjer where ordre_id='$id' and varenr = '$varenr[$x]'",__FILE__ . " linje " . __LINE__))) {
						if ($fast_db[$x]) db_modify("update ordrelinjer set fast_db = '$fast_db[$x]' where id = '$r[id]'");
						if ($folgevare[$x]) db_modify("update ordrelinjer set folgevare='$folgevare[$x]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
						if ($projekt[$x])	db_modify("update ordrelinjer set projekt='$projekt[$x]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__); #20130816
						if ($samlevare[$x])	db_modify("update ordrelinjer set samlevare='$samlevare[$x]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__); #20130816
					}
				}
			}
		/*
				if ($r1=db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__))) {
					if ($r2=db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'",__FILE__ . " linje " . __LINE__))) $momsfri[$x] = $r2['box7'];
				}
				$r3=db_fetch_array(db_select("select samlevare,kostpris from ordrelinjer where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__));
				$kostpris[$x]=$r3['kostpris']*1;
				db_modify("insert into ordrelinjer (ordre_id,posnr,varenr,vare_id,beskrivelse,enhed,antal,pris,rabat,serienr,kred_linje_id,momsfri,samlevare,kostpris,projekt) values
			('$id','$posnr_ny[$x]','$varenr[$x]','$vare_id[$x]','$beskrivelse[$x]','$enhed[$x]',$tmp,'$pris[$x]','$rabat[$x]','$serienr[$x]','$linje_id[$x]','$momsfri[$x]','$r3[samlevare]','$kostpris[$x]','$projekt[$x]')",__FILE__ . " linje " . __LINE__);
*/
		}
		else {db_modify("insert into ordrelinjer (ordre_id,posnr,beskrivelse) values ('$id','$posnr_ny[$x]','$beskrivelse[$x]')",__FILE__ . " linje " . __LINE__);}
	}
#xit;
}
##########################UDSKRIFT#################################

if ((strstr($submit,"Udskriv"))||(strstr($submit,"Send"))) {
	$lagervarer=if_isset($_POST['lagervarer']);
	if ($status>=3)  {
		$temp="aktura"; $formular=4; $ps_fil="formularprint.php";
	}
	elseif($status>=1) {
		if ($udskriv_til=='historik') {
			$temp="rdrebek";	$formular=2; $ps_fil="formularprint.php";
		}	else {
			if (db_fetch_array(db_select("select lev_nr from batch_salg where ordre_id=$id and lev_nr=1",__FILE__ . " linje " . __LINE__))) {
				$formular=3;
				$ps_fil="udskriftsvalg.php";
			} elseif (db_fetch_array(db_select("select leveres from ordrelinjer where ordre_id=$id and leveres>0",__FILE__ . " linje " . __LINE__))) {
				$formular=9;
				$ps_fil="udskriftsvalg.php";
			} else {$temp="rdrebek";	$formular=2; $ps_fil="formularprint.php";}
		}	
	} else {$temp="ilbud"; $formular=1; $ps_fil="formularprint.php";}
	if($udskriv_til=="oioubl") {
		if($art=="DO") $oioubl='faktura';
		else $oioubl='kreditnota';
		if ($popup) print "<BODY onLoad=\"JavaScript:window.open('oioubl_dok.php?id=$id&doktype=$oioubl' ,'' ,'$jsvars');\">\n";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=oioubl_dok.php?id=$id&doktype=$oioubl\">\n";
	} elseif($udskriv_til=="oioxml") {
		if($art=="DO") $oioxml='faktura';
		else $oioxml='kreditnota';
		if ($popup) print "<BODY onLoad=\"JavaScript:window.open('oioxml_dok.php?id=$id&doktype=$oioxml' ,'' ,'$jsvars');\">\n";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=oioxml_dok.php?id=$id&doktype=$oioxml\">\n";
	} elseif($udskriv_til=="edifakt") {
		if($art=="DO") $oioubl='faktura';
		else $oioxml='kreditnota';
		if ($popup) print "<BODY onLoad=\"JavaScript:window.open('oioxml_dok.php?id=$id&doktype=$oioxml' ,'' ,'$jsvars');\">\n";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=oioxml_dok.php?id=$id&doktype=$oioxml\">\n";
	} elseif (strstr($udskriv_til,'PBS')) {
			include("pbsfakt.php");
			pbsfakt($id);
	} else {
		$oioxml='';
		$oioubl='';
		$edifakt='';
#		if ($udskriv_til!='historik') $udskriv_til='';
	 	if ($popup) print "<BODY onLoad=\"JavaScript:window.open('$ps_fil?id=$id&formular=$formular&udskriv_til=$udskriv_til&lagervarer=$lagervarer' ,'' ,',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,location=1');\">\n";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=$ps_fil?id=$id&formular=$formular&udskriv_til=$udskriv_til&lagervarer=$lagervarer\">\n";
	}
}

##########################OPSLAG################################
	if ((strstr($submit,'Opslag'))||((strstr($submit,'Gem'))&&(!$id))) {
		if ((strstr($fokus,'kontonr'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		elseif ((strstr($fokus,'kontonr'))&&(!$status || $hurtigfakt)) {
			if(db_fetch_row(db_select("select id from batch_salg where ordre_id = $id",__FILE__ . " linje " . __LINE__))) {
				print "<BODY onLoad=\"javascript:alert('Der er leveret varer, modtager kan ikke ændres')\">\n";
			} else {
				kontoopslag($art,$sort,$fokus,$id,'','','','','','','');
			}
		}
		if ((strstr($fokus,'firmanavn'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'addr1'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'addr2'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'postnr'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'bynavn'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'vare'))&&($art!='DK')) vareopslag($art,$sort,'varenr',$id,$vis_kost,$ref,$varenr[0]);
		if (strstr($fokus,'besk') && $beskrivelse[0] && $art!='DK') vareopslag($art,$sort,'beskrivelse',$id,$vis_kost,$ref,$beskrivelse[0]);
		if (strstr($fokus,'besk')) tekstopslag($sort,$id);
		if ((strstr($fokus,'kontakt'))&&($id)) ansatopslag($sort,$fokus,$id,$vis,$kontakt);
		if ((strstr($fokus,'lev_navn'))&&($id)) kontoopslag("$art","$sort","$fokus","$id","$lev_navn",'','','','','');
		elseif (strstr($fokus,'kontakt')) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
	}
	elseif ($submit && !$kontonr && $id) {
		kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		exit;
	}
########################## del_ordre  - SKAL VAERE PLACERET FOER "FAKTURER" ################################
	if ($submit=='del_ordre') {
		$sum=0; $moms=0;
		$ny_sum=0; $ny_moms=0;
		transaktion("begin");
		$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		db_modify("insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,kundeordnr,betalingsdage,betalingsbet,cvrnr,ean,institution,notes,art,ordredate,momssats,tidspkt,ref,status,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,valuta,projekt,sprog,email,mail_fakt,pbs,afd,restordre,omvbet) values ('$r[ordrenr]','$r[konto_id]','$r[kontonr]','".db_escape_string($r['firmanavn'])."','".db_escape_string($r['addr1'])."','".db_escape_string($r['addr2'])."','".db_escape_string($r['postnr'])."','".db_escape_string($r['bynavn'])."','".db_escape_string($r['land'])."','".db_escape_string($r['kontakt'])."','".db_escape_string($r['kundeordnr'])."','$r[betalingsdage]','$r[betalingsbet]','".db_escape_string($r['cvrnr'])."','".db_escape_string($r['ean'])."','".db_escape_string($r['institution'])."','".db_escape_string($r['notes'])."','$r[art]','$r[ordredate]','$r[momssats]','$r[tidspkt]','".db_escape_string($r['ref'])."','$r[status]','".db_escape_string($r['lev_navn'])."','".db_escape_string($r['lev_addr1'])."','".db_escape_string($r['lev_addr2'])."','".db_escape_string($r['lev_postnr'])."','".db_escape_string($r['lev_bynavn'])."','".db_escape_string($r['lev_kontakt'])."','$r[valuta]','$r[projekt]','".db_escape_string($r['sprog'])."','".db_escape_string($r['email'])."','$r[mail_fakt]','$r[pbs]','$r[afd]','1','$r[omvbet]')",__FILE__ . " linje " . __LINE__);
		$q = db_select("select id from ordrer where ordrenr=$ordrenr and art='$art' and tidspkt='$tidspkt' order by id desc",__FILE__ . " linje " . __LINE__);
		if ($r = db_fetch_array($q)) $ny_id=$r[id];
		for($x=1; $x<=$linjeantal; $x++) {
			if ($vare_id[$x]){
#				if ($r1=db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[$x]'"))) {
#					if ($r2=db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'"))) {$momsfri[$x] = $r2['box7'];}
#				}
				$r3=db_fetch_array(db_select("select momsfri,leveret,samlevare,kostpris from ordrelinjer where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__));
				$ny_antal=$antal[$x]-$r3[leveret];
				$antal[$x]=$r3[leveret];
				$sum=$sum+$antal[$x]*$pris[$x];
				$ny_sum=$ny_sum+$ny_antal*$pris[$x];
				if ($r3[momsfri]!='on') {
					$moms=$moms+$antal[$x]*$pris[$x]/100*$momssats;
					$ny_moms=$ny_moms+$ny_antal*$pris[$x]/100*$momssats;
				}
				if ($ny_antal) {
					if ($antal[$x]) {
						db_modify("insert into ordrelinjer (ordre_id,posnr,varenr,vare_id,beskrivelse,enhed,antal,pris,rabat,lev_varenr,serienr,kred_linje_id,momsfri,samlevare,projekt) values ('$ny_id','$posnr_ny[$x]','$varenr[$x]','$vare_id[$x]','$beskrivelse[$x]','$enhed[$x]',$ny_antal,'$pris[$x]','$rabat[$x]','$lev_varenr[$x]','$serienr[$x]','$linje_id[$x]','$momsfri[$x]','$r3[samlevare]','$projekt[$x]')",__FILE__ . " linje " . __LINE__);
						db_modify("update ordrelinjer set antal='$antal[$x]' where id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
					}
					else {
						db_modify("update ordrelinjer set ordre_id='$ny_id' where id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
					}
				}
			}
			else db_modify("insert into ordrelinjer (ordre_id,posnr,beskrivelse) values ('$ny_id','$posnr_ny[$x]','$beskrivelse[$x]')",__FILE__ . " linje " . __LINE__);
		}
		db_modify("update ordrer set sum = '$sum',moms = '$moms',status='2' where id='$id'",__FILE__ . " linje " . __LINE__);
		db_modify("update ordrer set sum = '$ny_sum',moms = '$ny_moms',hvem = '',tidspkt= '' where id='$ny_id'",__FILE__ . " linje " . __LINE__);

#exit;
		print "<BODY onLoad=\"javascript:alert('Der er oprettet en ny ordre med samme ordrenr')\">\n";
		#$submit='Fakturer';
		transaktion("commit");
	}
########################## FAKTURER   - SKAL VAERE PLACERET EFTER "del_ordre" ################################
	if ($submit=='Fakturer' && $status<3) {
		if ($hurtigfakt=='on') {
			$row = db_fetch_array($query = db_select("select count(id) as linjeantal from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__));
	if (!$row['linjeantal']) print "<BODY onLoad=\"javascript:alert('Du kan ikke fakturere uden ordrelinjer')\">\n";
			elseif ($row['linjeantal']==$linjeantal) { #20130506
				for ($x=1;$x<=$linjeantal;$x++) {
					$tmp=$linje_id[$x]*-1;
					if ($linje_id[$x] && $leveres[$x] && $folgevare[$x]>0 && !in_array($tmp,$folgevare)) {
						if($r=db_fetch_array(db_select("select varenr from varer where id = '$folgevare[$x]'",__FILE__ . " linje " . __LINE__))) {
							opret_ordrelinje("$id","$folgevare[$x]","$r[varenr]","$antal[$x]","","","","100","$art","$momsfri[0]","$posnr_ny[$x]","0","$incl_moms","","","0");
							$r=db_fetch_array(db_select("select max(id) as id from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__));
							db_modify("update ordrelinjer set leveres='$leveres[$x]',folgevare='$tmp' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
						}
					}
				}
				print "<meta http-equiv=\"refresh\" content=\"0;URL=levering.php?id=$id&hurtigfakt=on&mail_fakt=$mail_fakt&pbs=$pbs\">\n";
			}
		} elseif ($submit=='Fakturer'&&$bogfor!=0) {
				$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
			if (!$row = db_fetch_array($query)) {Print "Du kan ikke fakturere uden ordrelinjer";}
			else {
				if($udskriv_til=="oioubl") {
					if($art=="DO") $oioubl='faktura';
					else $oioubl='kreditnota';
				} elseif($udskriv_til=="oioxml") {
					if($art=="DO") $oioxml='faktura';
					else $oioxml='kreditnota';
				} else {
					$oioxml='';
					$oioubl='';
				}
				print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?id=$id&mail_fakt=$mail_fakt&pbs=$pbs&oioxml=$oioxml&oioubl=$oioubl\">\n";
			}	
		}
	}
############################ LEVER ################################

	if (strstr($submit,'Lev') && $bogfor!=0 && $status<3) {
		$x=0;
		$q=db_select("select * from ordrelinjer where ordre_id = '$id' order by posnr,id",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$antal[$x]=$r['antal'];
			$leveres[$x]=$r['leveres'];
			$folgevare[$x]=$r['folgevare'];
			$vare_id[$x]=$r['vare_id'];
		}
		$q=db_select("select * from ordrelinjer where ordre_id = '$id' order by posnr,id",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$linje_id[$x]=$r['linje_id'];
			$posnr[$x]=$r['posnr'];
			$antal[$x]=$r['antal'];
			$leveres[$x]=$r['leveres'];
			$folgevare[$x]=$r['folgevare'];
			$vare_id[$x]=$r['vare_id'];
		}
		$linjeantal=$x;

		for ($x=1;$x<=$linjeantal;$x++) {
		$tmp=$linje_id[$x]*-1;
			if ($linje_id[$x] && $leveres[$x] && $folgevare[$x]>0 && !in_array($tmp,$folgevare)) {
				if($r=db_fetch_array(db_select("select varenr from varer where id = '$folgevare[$x]'",__FILE__ . " linje " . __LINE__))) {
					$svar=opret_ordrelinje("$id","$folgevare[$x]","$r[varenr]","$antal[$x]","","","","100","$art","$momsfri[0]","$posnr_ny[$x]","0","$incl_moms","","","0");
					$r=db_fetch_array(db_select("select max(id) as id from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__));
					db_modify("update ordrelinjer set leveres='$leveres[$x]',folgevare='$tmp' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
				}
			}
		}
		if (!$x) print "<BODY onLoad=\"javascript:alert('Du kan ikke levere uden ordrelinjer')\">\n";
		else {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=levering.php?id=$id\">\n";
		}
	}
	$meta_returside = "<meta http-equiv=\"refresh\" content=\"3600;URL=$returside\">\n"; #20142403-2
	//print "<meta http-equiv=\"refresh\" content=\"3600;URL=$returside\">\n";

###########################################################################
ordreside($id,$regnskab);

function ordreside($id,$regnskab) {
#	print "<!--Function ordreside start-->";
	global $art;
	global $bgcolor;global $bgcolor5;global $bogfor;global $bruger_id;global $brugernavn;global $bruttosum;global $bruttosaetsum;
	global $charset;
	global $db_encode;global $db_id;global $difkto;
	global $fokus;global $fakturadate;global $fakturadato;
	global $genfakt;
	global $hurtigfakt;
	global $incl_moms;
	global $oio;global $omkunde;global $ordresum;
	global $procentfakt;global $procenttillag;global $procentvare;
	global $returside;global $rvnr;
	global $samlet_pris;global $samlet_rabat;global $samlet_rabatpct;global $sprog_id;global $sprog;global $submit;
	global $varenr;$vis_projekt;global $vis_saet; #20150306 varenr
 
	$id*=1;

	if (!$rvnr) {
#cho "select varenr from ordrelinjer where varenr='R' and ordre_id='$id'<br>";	
		$r=db_fetch_array(db_select("select posnr,varenr from ordrelinjer where varenr='R' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
#cho $r['varenr'];
		$rvnr=$r['varenr'];
	}
	#cho "Rvnr $rvnr<br>";
	
	$r=db_fetch_array(db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$sag_id=$r['sag_id']*1;
	if ($sag_id) {
		$returside=urlencode("../sager/sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id");
	}
	if (!$returside) {
		if ($popup) $returside="../includes/luk.php";
		else $returside="ordreliste.php";
	}
	$addr1=NULL;$addr2=NULL;
	$batchvare=NULL;$betalingsbet=NULL;$betalingsdage=NULL;$bynavn=NULL;
	$debitorkort=NULL;$dbsum=NULL;$dkantal=NULL;
	$cvrnr=NULL;$ean=NULL;$email=NULL;
	$felt_1=NULL;$felt_2=NULL;$felt_3=NULL;$felt_4=NULL;$felt_5=NULL;$firmanavn=NULL;
	$institution=NULL;
	$ko_ant=array();$kontakt=NULL;$konto_id=NULL;$kontonr=NULL;$kostsum=NULL;$kred_ord_id=NULL;$krediteret=NULL;$kundeordnr=NULL;
	$land=NULL;$levdato=NULL;$levdiff=NULL;$lev_addr1=NULL;$lev_addr2=NULL;$lev_bynavn=NULL;$lev_kontakt=NULL;$lev_max=NULL;$lev_navn=NULL;$lev_postnr=NULL;$lev_pbs=NULL;$lev_pbs_nr=NULL;$linjebg=NULL;
	$mail_fakt=NULL;$momsfri=NULL;$momssats=NULL;$momssum=NULL;
	$oio_fakt=NULL;$ordredato=NULL;$ordrenr=NULL;
	$pbs_nr=NULL;$postnr=NULL;$prev_id=NULL;
	$reserveret=NULL;
	$status=NULL;
	$tidl_lev=NULL;
	$udskriv_til=NULL;
	$valutakurs=NULL;$vis_lev_addr=NULL;
	$y=NULL;
	if (!$id) $fokus='kontonr';
	if ($id) {
		$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$konto_id = $row['konto_id']*1;
		$kontonr = HtmlEntities($row['kontonr'],ENT_COMPAT,$charset);
		$firmanavn = HtmlEntities($row['firmanavn'],ENT_COMPAT,$charset);
		$addr1 = HtmlEntities($row['addr1'],ENT_COMPAT,$charset);
		$addr2 = HtmlEntities($row['addr2'],ENT_COMPAT,$charset);
		$postnr = HtmlEntities($row['postnr'],ENT_COMPAT,$charset);
		$bynavn = HtmlEntities($row['bynavn'],ENT_COMPAT,$charset);
		$land = HtmlEntities($row['land'],ENT_COMPAT,$charset);
		$kontakt = HtmlEntities($row['kontakt'],ENT_COMPAT,$charset);
		$kundeordnr = HtmlEntities($row['kundeordnr'],ENT_COMPAT,$charset);
		$lev_navn = HtmlEntities($row['lev_navn'],ENT_COMPAT,$charset);
		$lev_addr1 = HtmlEntities($row['lev_addr1'],ENT_COMPAT,$charset);
		$lev_addr2 = HtmlEntities($row['lev_addr2'],ENT_COMPAT,$charset);
		$lev_postnr = HtmlEntities($row['lev_postnr'],ENT_COMPAT,$charset);
		$lev_bynavn = HtmlEntities($row['lev_bynavn'],ENT_COMPAT,$charset);
		$lev_kontakt = HtmlEntities($row['lev_kontakt'],ENT_COMPAT,$charset);
		$vis_lev_addr = $row['vis_lev_addr'];
		$felt_1 = HtmlEntities($row['felt_1'],ENT_COMPAT,$charset);
		$felt_2 = HtmlEntities($row['felt_2'],ENT_COMPAT,$charset);
		$felt_3 = HtmlEntities($row['felt_3'],ENT_COMPAT,$charset);
		$felt_4 = HtmlEntities($row['felt_4'],ENT_COMPAT,$charset);
		$felt_5 = HtmlEntities($row['felt_5'],ENT_COMPAT,$charset);
		$cvrnr = $row['cvrnr'];
		$ean = HtmlEntities($row['ean'],ENT_COMPAT,$charset);
		$institution = HtmlEntities($row['institution'],ENT_COMPAT,$charset);
		$email = HtmlEntities($row['email'],ENT_COMPAT,$charset);
		$mail_fakt = $row['mail_fakt'];
		$udskriv_til = $row['udskriv_til'];
		$mail_bilag = $row['mail_bilag']; #20131122 tilføj $mail_bilag til visning
		$betalingsbet = trim($row['betalingsbet']);
		$betalingsdage = $row['betalingsdage'];
		$valuta=$row['valuta'];
		$valutakurs=$row['valutakurs']*1;
		if (!$valutakurs) $valutakurs=100;
		$projekt[0]=$row['projekt'];
		$formularsprog=$row['sprog'];
		$pbs=$row['pbs'];
		$afd=$row['afd'];
		$sum=$row['sum'];
		$moms=$row['moms'];
		$ref = trim(HtmlEntities($row['ref'],ENT_COMPAT,$charset));
		$fakturanr = HtmlEntities($row['fakturanr'],ENT_COMPAT,$charset);
		$lev_adr = HtmlEntities($row['lev_adr'],ENT_COMPAT,$charset);
		$ordrenr=$row['ordrenr'];
		$kred_ord_id=$row['kred_ord_id']*1;
		$restordre=$row['restordre'];
		if($row['ordredate']) $ordredate=$row['ordredate'];
		else {$ordredate=date("y-m-d");}
		$ordredato=dkdato($ordredate);
		if ($row['levdate']) $levdato=dkdato($row['levdate']);
		if ($row['fakturadate']) {
			$fakturadate=$row['fakturadate'];
			$fakturadato=dkdato($row['fakturadate']);
		}
		if ($vis_saet && !$$fakturadato) $fakturadato=date("d-m-Y");
		if($row['nextfakt']) $genfakt = dkdato($row['nextfakt']);
		$momssats=$row['momssats'];
		$procenttillag=$row['procenttillag']*1;
		$status=$row['status'];
		if (!$status){$status=0;}
		$kontonr=$row['kontonr'];
		$art=$row['art'];
		$mail_subj=HtmlEntities($row['mail_subj'],ENT_COMPAT,$charset);
		$mail_text=HtmlEntities(str_replace("<br>","\n",$row['mail_text']),ENT_COMPAT,$charset);
		$dokument=$row['dokument'];
		$sag_id=$row['sag_id']*1;
		$sagsnr=$row['sagsnr']*1;
		$tilbudnr=$row['tilbudnr'];
		$datotid=$row['datotid'];
		$nr=$row['nr']*1;
		if ($row['returside']) $returside=$row['returside'];
		($row['omvbet'])?$omkunde='on':$omkunde='';

		if ($brugernavn && !$ref) $ref=$brugernavn;
		
		$x=0;
		$krediteret='';
		$q=db_select("select art,pbs_nr,pbs from adresser where art = 'S' or id = '$konto_id'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['art']=='S') {
				$lev_pbs_nr=$r['pbs_nr'];
				$lev_pbs=$r['pbs'];
			} else $pbs_nr=$r['pbs_nr'];
		}
		$query = db_select("select id,ordrenr from ordrer where kred_ord_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($row2 = db_fetch_array($query)) {
			$x++;
			if ($x>1) {$krediteret=$krediteret.",";}
			$krediteret=$krediteret."<a href=\"ordre.php?id=$row2[id]\">$row2[ordrenr]</a>";
		}
		if ($status<3) $fokus='vare0';
		else $fokus='';
	} else {
		$r=db_fetch_array(db_select("select ansatte.navn as ref,ansatte.afd as afd from ansatte,brugere where ansatte.id = ".nr_cast("brugere.ansat_id")." and brugere.brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__));
		$ref=$r['ref'];
		$afd=$r['afd'];
	}
	$afd*=1;
	$afd_navn=NULL;
	if ($r=db_fetch_array(db_select("select beskrivelse from grupper where art = 'AFD' and kodenr = '$afd'",__FILE__ . " linje " . __LINE__))) {
		$afd_navn=$r['beskrivelse'];
	}
	($r=db_fetch_array(db_select("select * from grupper where ART = 'bilag' and (box6 ='on' or (box1 !='' and box2 !='' and box3 !=''))",__FILE__ . " linje " . __LINE__)))?$bilag=1:$bilag=0;
	if (db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '2' and box7='on'",__FILE__ . " linje " . __LINE__))) {
		$url="jobkort.php?returside=ordre.php&konto_id=$konto_id&ordre_id=$id";
		$jobkort="<a href=$url style=\"text-decoration:none\"><input type=\"button\" style=\"width:75px\" value=\"jobkort\" onClick=\"window.navigate('$url')\"></a>";
		$url="debitorkort.php?returside=ordre.php&konto_id=$konto_id&ordre_id=$id";
		$debitorkort="<a href=$url style=\"text-decoration:none\"><input type=\"button\" style=\"width:75px\" value=\"debitorkort\" onClick=\"window.navigate('$url')\"></a>";
	} else $jobkort=NULL;
	
#cho "procentfakt $procentfakt $default_procenttillag<br>";
	
	######### pile ########## tilfoejet 20080210
		if ($status==0) $tmp="tilbud";
		elseif($status>=3) $tmp="faktura";
		else $tmp="ordrer";

#cho "$status select box1 from grupper where art = 'OLV' and kodenr = '$bruger_id' and  kode='$tmp'<br>\n";
		
		$r=db_fetch_array(db_select("select box1 from grupper where art = 'OLV' and kodenr = '$bruger_id' and  kode='$tmp'",__FILE__ . " linje " . __LINE__));
		$ordreliste=explode(",",$r['box1']);
		$x=0; $next_id=0;
		while($ordreliste[$x]) {
			if ($ordreliste[$x]==$id) {
				if (isset($ordreliste[$x-1])) $prev_id=$ordreliste[$x-1];
				else $prev_id=NULL;
				if (isset($ordreliste[$x+1])) $next_id=$ordreliste[$x+1];
				else $next_id=NULL;
			}
			$x++;
		}
######### elip ##########
	if ($art=='DK') {
		$query = db_select("select ordrenr from ordrer where id = '$kred_ord_id'",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query);
		sidehoved($id,"$returside","","","Kunde kreditnota $ordrenr (kreditering af ordre nr: <a href=\"ordre.php?id=$kred_ord_id\">$row2[ordrenr]</a>)");
	}
	elseif ($krediteret) {sidehoved($id,"$returside","","","Kundeordre $ordrenr ( krediteret p&aring; KN nr: $krediteret )");}
	else {
		if ($status<1) $temp='Tilbud';
		elseif ($status<=2) $temp='Ordre';
		else $temp='Faktura';
		if ($returside=="ordreliste.php") sidehoved($id,"$returside?valg=$temp","","","Kundeordre $ordrenr - $temp");
		else sidehoved($id,"$returside","","","Kundeordre $ordrenr - $temp");
	}
	if (!$status)	$status=0;
	if ($status>=3) {
		print "<form name=\"ordre\" action=\"ordre.php?id=$id&amp;sag_id=$sag_id&amp;returside=$returside\" method=\"post\">\n"; 

		print "<input type=\"hidden\" name=\"ordrenr\" value=\"$ordrenr\">";
		print "<input type=\"hidden\" name=\"status\" value=\"$status\">";
		print "<input type=\"hidden\" name=\"id\" value=\"$id\">";
		print "<input type=\"hidden\" name=\"art\" value=\"$art\">";
		print "<input type=\"hidden\" name=\"kred_ord_id\" value=\"$kred_ord_id\">\n";
	
		print "<input type=\"hidden\" name=\"konto_id\" value=\"$konto_id\">";
		print "<input type=\"hidden\" name=\"kontonr\" value=\"$kontonr\">";
		print "<input type=\"hidden\" name=\"firmanavn\" value=\"$firmanavn\">";
		print "<input type=\"hidden\" name=\"addr1\" value=\"$addr1\">";
		print "<input type=\"hidden\" name=\"addr2\" value=\"$addr2\">";
		print "<input type=\"hidden\" name=\"postnr\" value=\"$postnr\">";
		print "<input type=\"hidden\" name=\"bynavn\" value=\"$bynavn\">";
		print "<input type=\"hidden\" name=\"land\" value=\"$land\">";
		print "<input type=\"hidden\" name=\"kontakt\" value=\"$kontakt\">";
		print "<input type=\"hidden\" name=\"kundeordnr\" value=\"$kundeordnr\">\n";
		print "<input type=\"hidden\" name=\"lev_navn\" value=\"$lev_navn\">";
		print "<input type=\"hidden\" name=\"lev_addr1\" value=\"$lev_addr1\">";
		print "<input type=\"hidden\" name=\"lev_addr2\" value=\"$lev_addr2\">";
		print "<input type=\"hidden\" name=\"lev_postnr\" value=\"$lev_postnr\">";
		print "<input type=\"hidden\" name=\"lev_bynavn\" value=\"$lev_bynavn\">";
		print "<input type=\"hidden\" name=\"lev_kontakt\" value=\"$lev_kontakt\">";
		print "<input type=\"hidden\" name=\"levdato\" value=\"$levdato\">\n";
		print "<input type=\"hidden\" name=\"genfakt\" value=\"$genfakt\">";
		print "<input type=\"hidden\" name=\"cvrnr\" value=\"$cvrnr\">";
		print "<input type=\"hidden\" name=\"ean\" value=\"$ean\">";
		print "<input type=\"hidden\" name=\"institution\" value=\"$institution\">";
		print "<input type=\"hidden\" name=\"email\" value=\"$email\">";
#		print "<input type=\"hidden\" name=\"mail_fakt\" value=\"$mail_fakt\">";
		print "<input type=\"hidden\" name=\"betalingsbet\" value=\"$betalingsbet\">";
		print "<input type=\"hidden\" name=\"betalingsdage\" value=\"$betalingsdage\">";
		print "<input type=\"hidden\" name=\"momssats\" value=\"".dkdecimal($momssats)."\">";
		print "<input type=\"hidden\" name=\"procenttillag\" value=\"".dkdecimal($procenttillag)."\">";
		print "<input type=\"hidden\" name=\"ref\" value=\"$ref\">";
		print "<input type=\"hidden\" name=\"fakturanr\" value=\"$fakturanr\">";
		print "<input type=\"hidden\" name=\"lev_adr\" value=\"$lev_adr\">";
		print "<input type=\"hidden\" name=\"valuta\" value=\"$valuta\">";
		print "<input type=\"hidden\" name=\"valutakurs\" value=\"$valutakurs\">";
		print "<input type=\"hidden\" name=\"projekt[0]\" value=\"$projekt[0]\">"; #20130816
		print "<input type=\"hidden\" name=\"sprog\" value=\"$formularsprog\">";
		print "<input type=\"hidden\" name=\"pbs\" value=\"$pbs\">";
		print "<input type=\"hidden\" name=\"afd\" value=\"$afd\">";
		print "<input type=\"hidden\" name=\"sum\" value=\"$sum\">";
		print "<input type=\"hidden\" name=\"sag_id\" value=\"$sag_id\">";
		print "<input type=\"hidden\" name=\"sagsnr\" value=\"$sagsnr\">";
		print "<input type=\"hidden\" name=\"tilbudnr\" value=\"$tilbudnr\">";
		print "<input type=\"hidden\" name=\"datotid\" value=\"$datotid\">";
		print "<input type=\"hidden\" name=\"nr\" value=\"$nr\">";
		print "<input type=\"hidden\" name=\"returside\" value=\"$returside\">";
		print "<input type=\"hidden\" name=\"omkunde\" value=\"$omkunde\">";

		if ($mail_fakt) $mail_fakt="checked";

##### pile ########	tilfoejet 20080210
		$alerttekst=findtekst(154,$sprog_id);
		$spantekst=findtekst(198,$sprog_id);
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\"><tbody>\n"; #Tabel 1 ->
		if ($prev_id)	print "<tr><td width=\"50%\" title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$prev_id&returside=$returside','$alerttekst')\"><img src=\"../ikoner/left.png\" style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td>\n";
		else print "<tr><td width=\"50%\"></td>\n";
		$spantekst=findtekst(199,$sprog_id);
		if ($next_id)	print "<td width=\"50%\" align=\"right\" title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$next_id&returside=$returside','$alerttekst')\"><img src=\"../ikoner/right.png\" style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td></tr>\n";
		else print "<tr><td width=\"50%\"></td>\n";
		print "</tbody></table>\n"; # <- Tabel 1
##### pile ########
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" valign = \"top\"><tbody>\n"; #Tabel 2 ->
		$ordre_id=$id;
		print "<tr><td width=\"31%\" valign=\"top\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\">\n"; #Tabel 2.1 ->
		print "<tr><td width=\"100\"><b>Kontonr</b></td><td width=\"100\">$kontonr</td></tr>\n";
		print "<tr><td><b>Firmanavn</b></td><td>$firmanavn</td></tr>\n";
		print "<tr><td><b>Adresse</b></td><td>$addr1</td></tr>\n";
		print "<tr><td></td><td>$addr2</td></tr>\n";
		print "<tr><td><b>Postnr &amp; by</b></td><td>$postnr $bynavn</td></tr>\n";
		print "<tr><td><b>Land</b></td><td>$land</td></tr>\n";
		print "<tr><td><b>Att.</b></td><td>$kontakt</td></tr>\n";
		print "<tr><td><b>Ordrenr.</b></td><td>$kundeordnr</td></tr>\n";
		print "<tr><td><b>CVR-nr.</b></td><td>$cvrnr</td></tr>\n";
		print "<tr><td><b>EAN-nr.</b></td><td>$ean</td></tr>\n";
		print "<tr><td><b>Institution</b></td><td>$institution</td></tr>\n";
		print "</tbody></table></td>\n"; #  <- Tabel 2.1 
		print "<td width=\"38%\" valign=\"top\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\">\n"; #Tabel 2.2 ->
		$alerttekst='Husk at opdatere ved at klikke p&aring  [OK] til højre for feltet du har ændret!';
		print "<tr><td><b>E-mail</b></td><td width=\"105\"><input class=\"inputbox\" type=\"text\" name=\"email\" style=\"width:130px\" value=\"$email\" onchange=\"javascript:alert('$alerttekst')\"></td></tr>\n";
#		print "<tr><td><b>Edskriv til</b></td>"
#		if ($email)
		print "<tr><td title=\"V&aelig;lg p&aring; hvilken m&aring;de dokumentet skal udskrives, gemmes eller sendes.\"><b>Udskriv til</b></td>\n";
		if ($mail_fakt) $udskriv_til="email";
		if ($oioxml) $udskriv_til="oioxml";
		if ($oioubl) $udskriv_til="oioubl";
		if ($lev_pbs_nr) {
			if ($pbs == "FI") $udskriv_til="PBS_FI";
			elseif ($pbs == "BS") $udskriv_til="PBS_BS";
		}
		if (!$udskriv_til) $udskriv_til="PDF";
		print "<td><select class=\"inputbox\" name=\"udskriv_til\" onchange=\"javascript:alert('$alerttekst')\">\n";
/*
		print "<option>$udskriv_til</option>\n";
		if ($udskriv_til!="PDF") print "<option>PDF</option>\n";
		if ($udskriv_til!="email" && $email) print "<option>email</option>\n";
		if ($udskriv_til!="oioxml" && strlen($ean)==13) print "<option title=\"Kun ved fakturering/kreditering.\">oioxml</option>\n";
		print "</SELECT></td></tr>\n";
*/

		if ($udskriv_til=="PBS_FI" && $lev_pbs!='B') print "<option value=\"PBS_FI\">PBS</option>\n";
		else print "<option>$udskriv_til</option>\n";
		if ($udskriv_til!="PDF") print "<option>PDF</option>\n";
		if ($udskriv_til!="PDF-tekst") print "<option title=\"Udskrives som PDF uden baggrund\">PDF-tekst</option>\n";
		if ($udskriv_til!="email") print "<option title=\"Sendes som PDF via e-mail\">email</option>\n";
		if ($udskriv_til!="oioxml") print "<option title=\"Kun ved fakturering/kreditering.\">oioxml</option>\n"; #PHR 20090803
		if ($udskriv_til!="oioubl") print "<option title=\"Kun ved fakturering/kreditering.\">oioubl</option>\n"; #PHR 20090803
		if ($udskriv_til!="edifakt") print "<option title=\"Kun ved fakturering/kreditering.\">edifakt</option>\n"; #20140201
		$tmp=$pbs_nr*1;
# 20120822	
		if ($lev_pbs_nr) {
			if ($tmp == 'L') {
				if ($pbs) print "<option value=\"PBS_FI\">PBS</option>\n";
			} else {
				if ($udskriv_til!="PBS_FI" && $lev_pbs!='B') print "<option value=\"PBS_FI\">PBS</option>\n";
				elseif ($tmp && $udskriv_til!="PBS_BS" && $lev_pbs=='B') print "<option title=\"Opkr&aelig;ves via PBS betalingsservice\">PBS_BS</option>\n";
			}
		}
		if ($udskriv_til!="historik" && db_fetch_array(db_select("select * from grupper where ART = 'bilag' and (box6 ='on' or (box1 !='' and box2 !='' and box3 !=''))",__FILE__ . " linje " . __LINE__))) {
			print "<option title=\"Gem en kopi og vedhæft kundens historik\">historik</option>\n";
		}
		print "</SELECT><input type=\"submit\" value=\"OK\" name=\"submit\"></td>\n";
/*
		print "<tr><td><b>Fakt som mail</td><td><input class=\"inputbox\" type=\"checkbox\" name=\"mail_fakt\" $mail_fakt></td></tr>\n";
		if ($lev_pbs_nr) {
			if ($pbs == "FI") $pbs_fi='checked';
			elseif ($pbs == "BS") $pbs_bs='checked';
			$title="PBS udsender FI-indbetalingskort";
			if (!$pbs_bs) {
				print "<td colspan=\"2\" title=\"$title\">Faktura via PBS (FI)</td><td title=\"$title\"><input class=\"inputbox\" type=\"checkbox\" name=\"pbs_fi\" $pbs_fi onchange=\"javascript:docChange = true;\"></td></tr>\n";
				if ($pbs_nr && !$pbs_fi) print "<tr>\n";
			}
			$title="Opkr&aelig;ves via PBS's betalingsservice";
			if ($pbs_nr && !$pbs_fi) print "<td colspan=\"2\" title=\"$title\">Opkr&aelig;v via PBS (BS)</td><td title=\"$title\"><input class=\"inputbox\" type=\"checkbox\" name=\"pbs_bs\" \"$pbs_bs\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		} else print "</tr>\n";
*/
		print "<tr><td width=\"100\"><b>Ordredato</b></td><td width=\"100\">$ordredato</td></tr>\n";
		print "<tr><td><b>Leveringsdato</b></td><td>$levdato</td></tr>\n";
		print "<tr><td><b>Fakturadato</b></td><td>$fakturadato</td></tr>\n";
		print "<tr><td><b>Genfaktureres</b></td><td><input class=\"inputbox\" type=\"text\" name=\"genfakt\" size=\"7\" value=\"$genfakt\"><input type=\"submit\" value=\"OK\" name=\"submit\"></td></tr>\n";
		print "<tr><td><b>Betaling</b></td><td>$betalingsbet&nbsp;+&nbsp;$betalingsdage</td>\n";
		print "<tr><td><b>Vor ref.</b></td><td>$ref &nbsp; $afd_navn</td></tr>\n";
		print "<tr><td><b>Fakturanr</b></td><td>$fakturanr</td></tr>\n";
		$tmp=dkdecimal($valutakurs);
		if ($valuta) print "<tr><td><b>Valuta / Kurs</b></td><td>$valuta / $tmp</td></tr>\n";
		if ($projekt[0]) print "<tr><td><b>Projekt</b></td><td>$projekt[0]</td></tr>\n";
		print "</tbody></table></td>\n"; # <- Tabel 2.2
		print "<td width=\"31%\" valign=\"top\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" valign=\"top\">\n"; #Tabel 2.3 ->
		if ($vis_lev_addr) {
			print "<tr><td><b>Leveringsadresse</b><br />&nbsp;</td><td align=\"center\">$jobkort $debitorkort</td></tr>\n";
			print "<tr><td colspan=\"2\"><b><hr></b></tr>\n";
			print "<tr><td><b>Firmanavn</b></td><td colspan=\"2\">$lev_navn</td></tr>\n";
			print "<tr><td valign=\"top\"><b>Adresse</b></td><td colspan=\"2\">$lev_addr1</td></tr>\n";
			print "<tr><td></td><td colspan=\"2\">$lev_addr2</td></tr>\n";
			print "<tr><td><b>Postnr. &amp; by</b></td><td>$lev_postnr $lev_bynavn</td></tr>\n";
			print "<tr><td><b>Att.</b></td><td colspan=\"2\">$lev_kontakt</td></tr>\n";
			print "<tr><td colspan=\"2\"><b><hr></b></tr>\n";
			print "<tr><td colspan=\"2\"><a href=\"ordre.php?id=$id&returside=$returside&vis_lev_addr=0\">Vis ekstrafelter</tr>\n";
		} else {
			print "<tr><td><b>".findtekst(243,$sprog_id)."</b></td><td align=\"center\">$jobkort $debitorkort</td></tr>\n";
			print "<tr><td colspan=\"2\"><b><hr></b></tr>\n";
			if ($vis_saet) {
				print "<tr><td><b>$felt_1</b></td><td align=\"right\">".dkdecimal($felt_2)."</td></tr>"; 
				print "<tr><td><b>$felt_3</b></td><td align=\"right\">".dkdecimal($felt_4)."</td></tr>"; 
			} else {
				if (findtekst(244,$sprog_id)) print "<tr><td><b>".findtekst(244,$sprog_id)."</b></td><td>$felt_1</td></tr>\n";
				if (findtekst(245,$sprog_id)) print "<tr><td><b>".findtekst(245,$sprog_id)."</b></td><td>$felt_2</td></tr>\n";
				if (findtekst(246,$sprog_id)) print "<tr><td><b>".findtekst(246,$sprog_id)."</b></td><td>$felt_3</td></tr>\n";
				if (findtekst(247,$sprog_id)) print "<tr><td><b>".findtekst(247,$sprog_id)."</b></td><td>$felt_4</td></tr>\n";
				if (findtekst(248,$sprog_id)) print "<tr><td><b>".findtekst(248,$sprog_id)."</b></td><td>$felt_5</td></tr>\n";
			}
			print "<tr><td colspan=\"2\"><b><hr></b></tr>\n";
			print "<tr><td colspan=\"2\"><a href=\"ordre.php?id=$id&returside=$returside&vis_lev_addr=1\">Vis leveringsadresse</td></tr>\n";
		}
		
		$lev_max=0;
		$q = db_select("select lev_nr from batch_salg where ordre_id = $id",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($r['lev_nr']>$lev_max) {$lev_max=$r['lev_nr'];}
		}
		if ($lev_max > 0) {
			print "<tr><td colspan=\"2\">&nbsp;</td></tr>\n";
			for ($levnr=1; $levnr<=$lev_max; $levnr++) {
				print "<tr><td colspan=\"2\"> <a href='udskriftsvalg.php?id=$id&valg=$levnr&formular=3'>F&oslash;lgeseddel $levnr</a></td></tr>\n";
			}
		}
		if (!$formularsprog) $formularsprog='Dansk';
		($art=='DO')?$form_nr=4:$form_nr=5;
		$q = db_select("select * from formularer where formular='$form_nr' and art='5' and lower(sprog)='".strtolower($formularsprog)."'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($r['xa']=='1') $std_subj=$r['beskrivelse'];
			elseif ($r['xa']=='2') $std_txt_title=$r['beskrivelse'];
			list($std_txt,$tmp)=explode("<br>",$std_txt_title);
			($mail_text)?$std_txt_title=$mail_text:$std_txt_title=str_replace("<br>","",$std_txt_title);
		}

		print "</tbody></table></td></tr>\n"; # -< Tabel 2.3
		if ($udskriv_til=='email') {
			print "<tr><td align=\"center\" colspan=\"3\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\"><tbody>\n"; #Tabel 2.4 ->
			print "<tr><td width=\"120px\">Mail emne</td><td><input class=\"inputbox\" type=\"text\" style=\"width:1000px;\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_subj\" placeholder=\"$std_subj\" value=\"$mail_subj\" onchange=\"javascript:docChange = true;\"></td>";
			if ($bilag) { 
				if ($dokument) print "<td title=\"klik her for at &aring;bne bilaget: $dokument\"><a href=\"../includes/bilag.php?kilde=ordrer&filnavn=$dokument&bilag_id=$id&bilag=$dokument&kilde_id=$id\"><img style=\"border: 0px solid\" alt=\"clip_m_papir\" src=\"../ikoner/paper.png\"></a></td>";
				else print "<td title=\"klik her for at vedh&aelig;fte et bilag\"><a href=\"../includes/bilag.php?kilde=ordrer&bilag_id=$id&bilag=$dokument&ny=ja&kilde_id=$id\"><img  style=\"border: 0px solid\" alt=\"clip\" src=\"../ikoner/clip.png\"></a></td>";
			}
			print "</tr><tr><td valign=\"top\">Mail tekst</td><td title=\"$std_txt_title\">";
			if ($mail_text) print "<textarea style=\"width:1000px;\" rows=\"2\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_text\" onchange=\"javascript:docChange = true;\">$mail_text</textarea>\n";
			else print "<input class=\"inputbox\" type=\"text\" style=\"width:1000px;\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_text\" placeholder=\"$std_txt\" value=\"$mail_text\" onchange=\"javascript:docChange = true;\">";
			print "</td><td><input type=\"submit\" value=\"OK\" name=\"opdat_mailtext\"></td></tr></tbody></table></td></tr>\n"; # <- Tabel 2.4
		}
		print "<tr><td align=\"center\" colspan=\"3\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"1\" width=\"100%\"><tbody>\n"; #Tabel 2.5 ->
		//print "<tr><td colspan=\"7\"></td></tr>\n<tr>\n"; # udkommenteret 20140502
		print "<td align=\"center\"><b>Pos.</b></td><td align=\"center\"><b>Varenr.</b></td><td align=\"center\"><b>Antal</b></td><td align=\"center\"><b>Enhed</b></td><td align=\"center\"><b>Beskrivelse</b></td><td align=\"center\"><b>Pris</b></td><td align=\"center\"><b>Rabat</b></td>\n";
#		print "<td align=\"center\"><b>Pos.</b></td><td align=\"center\"><b>Varenr.</b></td><td align=\"center\"><b>Antal</b></td><td align=\"center\"><b>Enhed</b></td><td align=\"center\"><b>Beskrivelse</b></td><td align=\"center\"><b>Pris</b></td><td align=\"center\"><b>Rabat</b></td>";
		if ($procentfakt) print "<td align=\"center\"><b>Procent</b></td>\n";
		print "<td align=\"center\"><b>I alt</b></td>\n";
		if (db_fetch_array(db_select("select * from grupper where art = 'PRJ' order by kodenr",__FILE__ . " linje " . __LINE__))) {
			$vis_projekt='on';
		}
		if ($vis_projekt && !$projekt[0]) print "<td align=\"center\" title=\"Projektnummer. Vises for ordrelinjer tilknyttet et projekt.\"><b>Proj.</b></td>\n";
#		else //print "<td></td>\n"; # udkommenteret 20140502
		if ($genfakt) print "<td align=\"center\" title=\"N&aring;r dette felt er afm&aelig;rket udelades ordrelinjen ved genfakturering.\"><b>kdo</b></td>\n";
		if ($omkunde) print "<td align=\"center\" title =\"Hvis feltet vises er kunden underlagt reglerne for omvendt betalingspligt. Er varen afmærkeret er der ikke opkrævet moms for den pågældende vare.\"><b>O/B</b></td>";
		print "</tr>\n";
		$x=0;
		$k_sum=0;
		if (!$ordre_id) $ordre_id=0;
		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by saet desc,samlevare,posnr,id",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query))	{
			if (($row['posnr']>0)) {
				$x++;
				$linje_id[$x]=$row['id'];
				$vare_id[$x]=$row['vare_id'];
				$posnr[$x]=$x;
				$varenr[$x]=HtmlEntities($row['varenr'],ENT_COMPAT,$charset);
				$lev_varenr[$x]=HtmlEntities($row['lev_varenr'],ENT_COMPAT,$charset);
				$beskrivelse[$x]=HtmlEntities($row['beskrivelse'],ENT_COMPAT,$charset);
				$enhed[$x]=HtmlEntities($row['enhed'],ENT_COMPAT,$charset);
				$pris[$x]=$row['pris']*1;
				$rabat[$x]=$row['rabat']*1;
				$rabatart[$x]=$row['rabatart'];
				$procent[$x]=$row['procent'];
				$antal[$x]=$row['antal'];
				$momsfri[$x]=$row['momsfri'];
				$varemomssats[$x]=$row['momssats'];
				$folgevare[$x]=$row['folgevare'];
				$saet[$x]=$row['saet'];
				$samlevare[$x]=$row['samlevare'];
				$fast_db[$x]=$row['fast_db'];
				($row['omvbet'])?$omvbet[$x]='checked':$omvbet[$x]=''; #omvendt betalingspligt
				if (!$varemomssats[$x] || $varemomssats[$x] > $momssats) $varemomssats[$x]=$momssats;
				if ($momsfri[$x] || $omvbet[$x]) $varemomssats[$x]=0;
				$serienr[$x]=HtmlEntities($row['serienr'],ENT_COMPAT,$charset);
				$kostpris[$x]=$row['kostpris'];
				$k_sum+=$kostpris[$x]*$antal[$x];
				$projekt[$x]=$row['projekt'];
				$omvbet[$x]=$row['omvbet'];
				$lev_varenr[$x]=$row['lev_varenr'];
				($row['kdo'])?$kdo[$x]='checked':$kdo[$x]='';
#/*
				if (!$vis_saet){
					if ($vare_id[$x]) {
						list($koordpr,$koordnr,$koordant,$koordid,$koordart)=explode(chr(9),find_kostpris($vare_id[$x],$linje_id[$x]));
						$kobs_ordre_pris=explode(",",$koordpr);
						$ko_ant[$x]=count($kobs_ordre_pris);
						$kobs_ordre_id=explode(",",$koordid);
						$kobs_ordre_antal=explode(",",$koordant);
						$kobs_ordre_art=explode(",",$koordart);
						$kostpris[$x]=0;
						for($y=0;$y<$ko_ant[$x];$y++) {
							$kostpris[$x]+=$kobs_ordre_pris[$y];
							if ($valutakurs && $valutakurs!=100) $kostpris[$x]*=100/$valutakurs;
						}
						$kostpris[$x]/=$ko_ant[$x]; #20141023
#cho "Kost3 $kostpris[$x]<br>";
						$kostsum[$x]=$kostpris[$x]*$antal[$x];
#cho "Kost4 $kostsum[$x]<br>";
					# db_modify("update ordrelinjer set kostpris='$kostpris[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
						$db[$x]=($pris[$x]-$kostpris[$x])*$antal[$x];
#cho "DB $db[$x]=($pris[$x]-$kostpris[$x])*$antal[$x]<br>";
						if ($pris[$x]!=0) $dg[$x]=$db[$x]*100/$pris[$x];
						else $dg[$x]=0;
						$dk_db[$x]=dkdecimal($db[$x]);
						$dk_dg[$x]=dkdecimal($dg[$x]);
#cho "$dk_db[$x] $dk_dg[$x]<br>";
					}
				}
				if (($art=='DK')&&($antal[$x]<0)) $bogfor==0;
				if ($serienr[$x]) {
					$serienumre[$x]=NULL;
					$q2 = db_select("select serienr from serienr where salgslinje_id='$linje_id[$x]' order by serienr",__FILE__ . " linje " . __LINE__);
					while ($r2 = db_fetch_array($q2)) ($serienumre[$x])?$serienumre[$x].=','.$r['serienr']:$serienumre[$x]=$r['serienr'];
				}
#*/
				if ($vis_saet && $linje_id[$x]) db_modify("update ordrelinjer set posnr='$x' where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
		$linjeantal=$x;
		print "<input type=\"hidden\" name=\"linjeantal\" value=\"$x\">\n";
		$totalrest=0;
		for ($x=1; $x<=$linjeantal; $x++) {
			if (!$vare_id[$x]) {
				$query = db_select("select id from varer where varenr = '$varenr[$x]'",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) {$vare_id[$x]=$row['id'];}
			}
			if (($varenr[$x])&&($vare_id[$x])) {
				$row = db_fetch_array(db_select("select gruppe,provisionsfri from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
				$provisionsfri[$x]=$row['provisionsfri'];
				$row = db_fetch_array(db_select("select box8,box9 from grupper where art='VG' and kodenr='$row[gruppe]'",__FILE__ . " linje " . __LINE__));
				($row['box8']=='on')?$lagervare=1:$lagervare=0;
				($row['box9']=='on')?$batchvare=1:$batchvare=0;
/*
if ($batchvare) {
					list($koordpr,$koordnr,$koordant,$koordid,$koordart)=explode(chr(9),find_kostpris($vare_id[$x],$linje_id[$x]));
#cho "$koordpr,$koordnr,$koordant,$koordid,$koordart<br>";
					$kobs_ordre_pris=explode(",",$koordpr);
					$kobs_ordre_nr=explode(",",$koordnr);
					$kobs_ordre_antal=explode(",",$koordant);
					$kobs_ordre_id=explode(",",$koordid);
					$kobs_ordre_art=explode(",",$koordart);
					$ko_ant[$x]=count($kobs_ordre_nr);
					$kostpris[$x]=0;
#					for($y=0;$y<$ko_ant[$x];$y++) $kostpris[$x]+=$kobs_ordre_pris[$y];
					#rettet 20120418 grundet fejl i kostpris v leverring af flere omgange på samme ordrelinje på købsordre
					#rettet yderligere 20121213 grundet ny fejl hvis køb er fordelt over flere købsordrer
					for($y=0;$y<$ko_ant[$x];$y++) $kostsum[$x]+=$kobs_ordre_pris[$y]*$kobs_ordre_antal[$y];
					($antal[$x])?$kostpris[$x]=$kostsum[$x]/$antal[$x]:$kostpris[$x]=0;

					} else 
*/
#					$kostsum[$x]=$kostpris[$x]*$antal[$x]; 
#	Nedenstaaende fjernet 20100709. Kostpris ikke må ændres efter bogføring den den bruges ved batch kontrol naar købsordre bogføres efter salgsordre.
#				if ($art=='DO') db_modify("update ordrelinjer set kostpris=$kostpris[$x] where id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
				if ($rabatart[$x]=='amount') $ialt=($pris[$x]-$rabat[$x])*$antal[$x];
				else $ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				if ($provisionsfri[$x]) {
					if ($art=='DO') $kostsum[$x]=$ialt;
				}
#				if ($valutakurs)$kostsum[$x]*=$valutakurs/100; #20140116
				$db[$x]=$ialt-$kostsum[$x];				
				$ialt=afrund($ialt,3);
				if ($ialt!=0) {
					$dg[$x]=$db[$x]*100/$ialt;
					$dk_dg[$x]=dkdecimal($dg[$x]);					
				}
				$dk_kostpris[$x]=dkdecimal($kostpris[$x]);
				if ($art=='DO') {
					$dk_db[$x]=dkdecimal($db[$x]);
					$dk_kostsum[$x]=dkdecimal($kostsum[$x]);
				}	else {
					$dk_db[$x]=dkdecimal($db[$x]*-1);
					$dk_kostsum[$x]=dkdecimal($kostsum[$x]*-1);
				}
#cho "$dk_kostpris[$x] $dk_kostsum[$x]<br>";
				#20100820				$sum=$sum+$ialt;
				$dkpris=dkdecimal($pris[$x]);
				($rabat[$x])?$dkrabat=dkdecimal($rabat[$x],3):$dkrabat=NULL;
				$dkprocent=dkdecimal($procent[$x]);
				if ($momsfri[$x]!='on' && !$omvbet[$x] ) {
#20100820					$moms+=afrund($ialt*$varemomssats[$x]/100,3); #20100525 aendret fra 2 til 3 decimaler grundet momsfejl saldi_2
#					$momssum=$momssum+$ialt;
					if($incl_moms) $dkpris=dkdecimal($pris[$x]+$pris[$x]*$varemomssats[$x]/100);
				}
				if ($antal[$x]) {
					if ($art=='DK') $dkantal[$x]=dkdecimal($antal[$x]*-1);
					else $dkantal[$x]=dkdecimal($antal[$x]);
					if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-1);}
					if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-2);}
				} 
#cho "$saet[$x] || $rvnr || $lev_varenr[$x]<br>";
				if ($saet[$x] || $rvnr || $lev_varenr[$x]) {
					$dkrabat=NULL;
					if ($lev_varenr[$x]) $dkpris=NULL;
				}
			}
			else {$antal[$x]='';$dkpris='';$dkrabat='';$dkprocent='';$ialt='';}
			$title=var2str($beskrivelse[$x],$id,$posnr[$x],$varenr[$x],$dkantal[$x],$enhed[$x],$dkpris,$dkprocent,$serienr[$x],$varemomssats[$x],$rabat[$x]);
			print "<tr bgcolor=\"$linjebg\">\n";
			print "<input type=\"hidden\" name=\"linje_id[$x]\" value=\"$linje_id[$x]\">\n";
			print "<input type=\"hidden\" name=\"posn$x\" value=\"$posnr[$x]\"><td align=\"right\">$posnr[$x]</td>\n";
			print "<input type=\"hidden\" name=\"vare$x\" value=\"$varenr[$x]\"><td>$varenr[$x]<br></td>\n";
			print "<input type=\"hidden\" name=\"dkan$x\" value=\"$dkantal[$x]\"><td align=\"right\">$dkantal[$x]<br></td>\n";
			print "<input type=\"hidden\" name=\"enhed[$x]\" value=\"$enhed[$x]\"><td align=\"right\">$enhed[$x]<br></td>\n";
			print "<input type=\"hidden\" name=\"beskrivelse$x\" value=\"$beskrivelse[$x]\"><td title=\"$title\">$beskrivelse[$x]&nbsp;</td>\n";
			print "<input type=\"hidden\" name=\"pris$x\" value=\"".dkdecimal($pris[$x],3)."\"><td align=\"right\" title=\"Kostpris $dk_kostpris[$x]\">$dkpris<br></td>\n";
#			print "<input type=\"hidden\" name=\"pris$x\" value=\"$dkpris\"><td align=\"right\">$dkpris<br></td>\n";
			print "<input type=\"hidden\" name=\"raba$x\" value=\"".dkdecimal($rabat[$x],3)."\"><td align=\"right\">$dkrabat<br></td>\n";
			print "<input type=\"hidden\" name=\"proc$x\" value=\"$dkprocent\">";
			if ($procentfakt) print "<td align=\"right\">$dkprocent<br></td>\n";
			print "<input type=\"hidden\" name=\"serienr[$x]\" value=\"$serienr[$x]\">\n";
			print "<input type=\"hidden\" name=\"vare_id[$x]\" value=\"$vare_id[$x]\">\n";
			print "<input type=\"hidden\" name=\"lev_varenr[$x]\" value=\"$lev_varenr[$x]\">\n";
			print "<input type=\"hidden\" name=\"kdo[$x]\" value=\"$kdo[$x]\">\n";
			print "<input type=\"hidden\" name=\"rabatart[$x]\" value=\"$rabatart[$x]\">\n";
			print "<input type=\"hidden\" name=\"momsfri[$x]\" value=\"$momsfri[$x]\">\n";
			print "<input type=\"hidden\" name=\"varemomssats[$x]\" value=\"$varemomssats[$x]\">\n";
			print "<input type=\"hidden\" name=\"samlevare[$x]\" value=\"$samlevare[$x]\">\n";
			print "<input type=\"hidden\" name=\"folgevare[$x]\" value=\"$folgevare[$x]\">\n";
			print "<input type=\"hidden\" name=\"omvbet[$x]\" value=\"$omvbet[$x]\">\n";
			print "<input type=\"hidden\" name=\"saet[$x]\" value=\"$saet[$x]\">\n";
			print "<input type=\"hidden\" name=\"fast_db[$x]\" value=\"$fast_db[$x]\">\n";
			if ($vis_saet) {
				#if ($art=='DO') 
				$dbsum=$sum-$k_sum;
				#else $dbsum=-$sum-$k_sum;
			} else $dbsum=$dbsum+$db[$x];
			if ($ialt) {
				if ($procentfakt) $ialt*=$procent[$x]/100;
				if ($varenr[$x]) {
					if ($incl_moms && !$momsfri[$x] && !$omvbet[$x]) $tmp=$ialt+$ialt*$momssats/100;
					else $tmp=$ialt;
					if ($vis_saet) {
						if ($saet[$x] || $varenr[$x]=='R') {
							if ($lev_varenr[$x]) list($tmp)=explode("|",$lev_varenr[$x],2);
							else $tmp='';
						} elseif ($rvnr) {
							$tmp=$antal[$x]*($pris[$x]+$pris[$x]*$momssats/100);
						}
					}
					if ($art=='DK') $tmp*=-1;
					$tmp=dkdecimal($tmp);
				}
				print "<td align=\"right\" title=\"Kostpris $dk_kostsum[$x] * db: $dk_db[$x] * dg: $dk_dg[$x]%\">".$tmp."</td>\n";
			
			} else print "<td>&nbsp;</td>\n";
			print "<input type=\"hidden\" name=\"projekt[$x]\" value=\"$projekt[$x]\">\n";
			if ($vis_projekt && !$projekt[0]) {
				$r=db_fetch_array(db_select("select beskrivelse from grupper where art = 'PROJ' and kodenr='$projekt[$x]'",__FILE__ . " linje " . __LINE__));
				print "<td align=\"right\" title=\"'$r[projekt]'\">$projekt[$x]</td>\n";
			} else //print "<td></td>"; # udkommenteret 20140502
			if ($kdo[$x]) $kdo[$x]="&radic;";
			if ($genfakt) print "<td align=\"center\">$kdo[$x]</td>";
			if ($omvbet[$x]) $omvbet[$x]="&radic;";
			if ($omkunde) print "<td align=\"center\">$omvbet[$x]</td>\n";
#cho "$kobs_ordre_id[0] && $art!='DK' && $ko_ant[$x]>=1<br>";
			if ($kobs_ordre_id[0] && $art!='DK' && $ko_ant[$x]>=1) {
				for ($y=0; $y<$ko_ant[$x]; $y++) {
					$spantekst="K&oslash;bsordre&nbsp;$kobs_ordre_nr[$y] \n antal:&nbsp;$kobs_ordre_antal[$y]&nbsp;&aacute;&nbsp;".dkdecimal($kobs_ordre_pris[$y]);
					if ($kobs_ordre_art[$y]=='KO') $link="../kreditor/ordre.php?id=$kobs_ordre_id[$y]";
					else $link="../debitor/ordre.php?id=$kobs_ordre_id[$y]";
					print "<td align=\"right\" onClick=\"javascript:k_ordre=window.open('$link','ordre' ,'left=10,top=10,width=800,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no');k_ordre.focus();\"onMouseOver=\"this.style.cursor = 'pointer'\" title=\"'$spantekst'\"><img src=\"../ikoner/opslag.png\"></td>\n";
				}
			}
			else //print "<td><br></td>\n"; # udkommenteret 20140502
			if ($serienr[$x]) {print "<td onClick=\"serienummer($linje_id[$x])\" onMouseOver=\"this.style.cursor = 'pointer'\" align=\"right\" title=\"Serienumre \"><img alt=\"Serienummer\" src=\"../ikoner/serienr.png\"></td>\n";}
		}
#		$tmp=$momssum/100*$momssats; #ellers runder den ned ved v. 0,5 re ??
#		$moms=afrund($tmp,3);
		$kostpris[0]=$kostpris[0]*1;
		if ($submit=='del_ordre'||$submit=='Fakturer') db_modify("update ordrer set sum='$sum',kostpris='$kostpris[0]',moms='$moms' where id='$id'",__FILE__ . " linje " . __LINE__);
		if ($art=='DK') {
			$sum=$sum*-1;
			$momssum=$momssum*-1;
			$moms=$moms*-1;
		}
		
#		$tmp=$momssum/100*$momssats; #ellers runder den ned ved v. 0,5 ??
#		$moms=afrund($tmp,3);
		$ialt=$sum+$moms;
		print "<tr><td colspan=\"11\"><br></td></tr>\n";
		print "<tr><td colspan=\"11\"><table border=\"1\" cellspacing=\"0\" cellpadding=\"1\" width=\"100%\"><tbody>\n"; #Tabel 2.5.1 ->
		print "<tr bgcolor=\"$bgcolor5\">\n";
#		print "<td align=\"center\">".dkdecimal($procenttillag)."% tillæg ".dkdecimal($tillag)." </td>\n";
		print "<td width=\"20%\" align=\"center\">Nettosum ".dkdecimal($sum)."</td>\n";
		print "<td width=\"20%\" align=\"center\" title=\"D&aelig;kningsbidrag:&nbsp;".dkdecimal($dbsum)."\">";
		if (!$vis_saet) print "D&aelig;kningsbidrag:&nbsp;".dkdecimal($dbsum);
		print "</td>\n";
		if ($sum) $dg_sum=($dbsum*100/$sum);
		else $dg_sum=dkdecimal(0);
		print "<td width=\"20%\" align=\"center\" title=\"D&aelig;kningsgrad:&nbsp;".dkdecimal($dg_sum)."%\">";
		if (!$vis_saet) print "D&aelig;kningsgrad:&nbsp;".dkdecimal($dg_sum);
		print "</td>\n";
		print "<td align=\"center\">Moms ".dkdecimal($moms)."</td>\n";
		print "<td align=\"center\" title=\"D&aelig;kningsgrad:&nbsp;".dkdecimal($dg_sum)."%\">I alt ".dkdecimal($ialt)."</td>\n";
		print "</tbody></table></td></tr>\n"; #<- Tabel 2.5.1
		print "<tr><td align=\"center\" colspan=\"11\">\n";
		print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>\n"; #Tabel 2.5.2 ->
		if ($art!='DK') print "<td align=\"center\"><input type=\"submit\" class=\"button gray medium\" value=\"&nbsp;Kopi&eacute;r&nbsp;\" name=\"submit\" title=\"Kopi&eacute;r til ny ordre med samme indhold.\"></td>\n";
		if ($mail_fakt) $tmp="value=\"&nbsp;Send&nbsp;\" onclick=\"return confirm('Dokumentet sendes pr. mail til $email')\" title=\"Send via e-mail med vedh&aelig;ftet PDF-fil. Anden form for behandling v&aelig;lges fra listen Udskriv til.\"";
		else $tmp="value=\"&nbsp;Udskriv&nbsp;\" title=\"&Aring;bn et PDF-dokument, som kan gemmes eller viderebehandles p&aring; anden vis.\"";
		print "<td align=\"center\"><input type=\"submit\" class=\"button gray medium\" name=\"submit\" $tmp></td>\n";
		if (($art!='DK')&&(!$krediteret)) {
			$title="Klik her for at oprette en kreditnota, som hel eller delvist krediterer denne faktura. Kreditnotaen oprettes som en kreditnotaordre, som kan redigeres inden bogf&oslash;ring. Eksempelvis hvis kun en enkelt faktureret vare skal krediteres.";
			print "<td align=\"center\" title=\"$title\"><input type=\"submit\" class=\"button gray medium\" value=\"Kredit&eacute;r\" name=\"submit\"></td>\n";
		}
		print "</tbody></table></td></tr>\n"; #<- Tabel 2.5.2
		print "</tbody></table></td></tr>\n"; #<- Tabel 2.5
		print "</tbody></table></td></tr>\n"; #<- Tabel 2
		print "</form>\n";

	} else { ############################# ordren er ikke faktureret #################################
		if (!$konto_id) { #20150302
			if ($incl_moms) $momssats=25;
			$status=0;
		}
		if ($rvnr || $vis_saet) {
			$x=1;
			$linje_id=array();
			$q=db_select("select id from ordrelinjer where ordre_id = '$id' and saet > 0 order by saet,samlevare,posnr,id",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				$linje_id[$x]=$r['id'];
				$x++;		
			}
			$q=db_select("select id,saet from ordrelinjer where ordre_id = '$id' and rabat > 0 order by saet,samlevare,posnr,id",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				if (!$r['saet']) {
					$linje_id[$x]=$r['id'];
					$x++;	
				}
			}
			$r=db_fetch_array(db_select("select id from ordrelinjer where ordre_id = '$id' and varenr='R'",__FILE__ . " linje " . __LINE__));
			if ($linje_id[$x]=$r['id']) {
				$x;
			}
			$q=db_select("select * from ordrelinjer where ordre_id = '$id' order by posnr",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				if (!$r['saet'] && $r['rabat']*1==0 && $r['varenr'] != 'R' ) { 
					$linje_id[$x]=$r['id'];
					$x++;		
				}
			}
			for ($x=1;$x<=count($linje_id);$x++) {
				if ($linje_id[$x]) db_modify("update ordrelinjer set posnr='$x' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
		print "<form name=\"ordre\" action=\"ordre.php?id=$id&amp;sag_id=$sag_id&amp;returside=$returside\" method=\"post\">\n"; 

		print "<input type=\"hidden\" name=\"ordrenr\" value=\"$ordrenr\">\n";
		print "<input type=\"hidden\" name=\"status\" value=\"$status\">\n";
		print "<input type=\"hidden\" name=\"id\" value=\"$id\">\n";
		print "<input type=\"hidden\" name=\"art\" value=\"$art\">\n";
		print "<input type=\"hidden\" name=\"kred_ord_id\" value=\"$kred_ord_id\">\n";
		print "<input type=\"hidden\" name=\"sag_id\" value=\"$sag_id\">\n"; #20140507-1
		if ($art=='OT') { // Når input fields er 'disabled' bliver de ikke opdateret, derfor tilføjes hidden fields #20140716
			print "<input type=\"hidden\" name=\"kontonr\" value=\"$kontonr\">\n";
			print "<input type=\"hidden\" name=\"ref\" value=\"$ref\">\n";
			print "<input type=\"hidden\" name=\"procenttillag\" value=\"".dkdecimal($procenttillag)."\">";
			
			print "<input type=\"hidden\" name=\"felt_1\" style=\"width:200px\" value=\"$felt_1\">\n";
			print "<input type=\"hidden\" name=\"felt_2\" style=\"width:200px\" value=\"$felt_2\">\n";
			print "<input type=\"hidden\" name=\"felt_3\" style=\"width:200px\" value=\"$felt_3\">\n";
			print "<input type=\"hidden\" name=\"felt_4\" style=\"width:200px\" value=\"$felt_4\">\n";
			print "<input type=\"hidden\" name=\"felt_5\" style=\"width:200px\" value=\"$felt_5\">\n";
			
			print "<input type=\"hidden\" name=\"lev_navn\" value=\"$lev_navn\">\n";
			print "<input type=\"hidden\" name=\"lev_addr1\" value=\"$lev_addr1\"><input type=\"hidden\" name=\"lev_addr2\" value=\"$lev_addr2\">\n";
			print "<input type=\"hidden\" name=\"lev_postnr\" value=\"$lev_postnr\"><input type=\"hidden\" name=\"lev_bynavn\" value=\"$lev_bynavn\">\n";
			print "<input type=\"hidden\" name=\"lev_kontakt\" value=\"$lev_kontakt\">\n";
		}
#cho "status $status<br>";
		#intiering af variabler
		$antal_ialt=0; #10.10.2007
		$leveres_ialt=0; #10.10.2007
		$tidl_lev_ialt=0; #10.10.2007
		$konto_id*=1;
#cho "art: $art<br>"; #cho "vis_lev_addr: $vis_lev_addr<br>"; #cho "ref: $ref";
		$r=db_fetch_array(db_select("select * from adresser where id=$konto_id",__FILE__ . " linje " . __LINE__));
		$k_firmanavn=HtmlEntities($r['firmanavn'],ENT_COMPAT,$charset);
		$k_addr1=HtmlEntities($r['addr1'],ENT_COMPAT,$charset);
		$k_addr2=HtmlEntities($r['addr2'],ENT_COMPAT,$charset);
		$k_postnr=HtmlEntities($r['postnr'],ENT_COMPAT,$charset);
		$k_bynavn=HtmlEntities($r['bynavn'],ENT_COMPAT,$charset);
		$k_land=HtmlEntities($r['land'],ENT_COMPAT,$charset);
		$k_cvrnr=HtmlEntities($r['cvrnr'],ENT_COMPAT,$charset);
		$k_betalingsbet=HtmlEntities($r['betalingsbet'],ENT_COMPAT,$charset);
		$k_betalingsdage=HtmlEntities($r['betalingsdage'],ENT_COMPAT,$charset);
		$k_email=HtmlEntities($r['email'],ENT_COMPAT,$charset);
		$k_ean=HtmlEntities($r['ean'],ENT_COMPAT,$charset);
		$k_institution=HtmlEntities($r['institution'],ENT_COMPAT,$charset);
		
		// Query til kunde kontakt
		$x=0; #20140826
		$q=db_select("select * from ansatte where konto_id='$konto_id' order by posnr",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$k_kontakt[$x]=htmlspecialchars($r['navn']);
			$x++;
		}

##### pile ########	tilfoejet 20080210
		$alerttekst=findtekst(154,$sprog_id);
		$spantekst=findtekst(198,$sprog_id);
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\"><tbody>\n"; #Tabel 3 ->

		if ($prev_id)	print "<tr><td width=\"50%\" title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$prev_id&returside=$returside','$alerttekst')\"><img src=\"../ikoner/left.png\" style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td>\n";
		else print "<tr><td width=\"50%\"></td>\n";
		$spantekst=findtekst(199,$sprog_id);
		if ($next_id)	print "<td width=\"50%\" align=\"right\" title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$next_id&returside=$returside','$alerttekst')\"><img src=\"../ikoner/right.png\" style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td></tr>\n";
		else print "<tr><td width=\"50%\"></td>\n";
		print "</tbody></table>\n"; # <- Tabel 3
##### pile ########
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"	valign = \"top\"><tbody>\n"; #Tabel 4 ->
		$ordre_id=$id;
		$ret=0;
		($art=='OT')?$disabled="disabled='disabled'":$disabled=NULL; #20140716
		print "<tr><td width=\"31%\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n"; #Tabel 4.1 ->
		print "<tr><td witdh=\"100\">Kontonr.</td><td colspan=\"2\">\n";
		if (trim($kontonr)) {
			if ($status<1) print "<input class=\"inputbox\" type=\"text\" style=\"width:200px;background-color:#ddd;\" name=\"kontonr\" readonly=\"readonly\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\" $disabled></td></tr>\n";
			else print "<input class=\"inputbox\" type=\"text\" readonly=\"readonly\" style=\"width:200px;background-color:#ddd;\" name=\"kontonr\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\"></td></tr>\n";
		}	else {print "<input class=\"inputbox\" type=\"text\" style=\"width:200px\" name=\"kontonr\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\" onchange=\"javascript:docChange = true;\"></td></tr>\n";}
		if ($firmanavn==$k_firmanavn) $tekstcolor="#444444";
		else {$tekstcolor="#ff0000";$ret=1;};
		print "<tr><td style=\"color:$tekstcolor;\" title=\"$k_firmanavn\">Firmanavn</td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:200px\" name=\"firmanavn\" onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$firmanavn\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		if ($addr1==$k_addr1 && $addr2==$k_addr2) $tekstcolor="#444444";
		else {$tekstcolor="#ff0000";$ret=1;};
		print "<tr><td style=\"color:$tekstcolor;\" title=\"$k_addr1,$k_addr2\">Adresse</td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:200px\" name=\"addr1\" onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$addr1\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		print "<tr><td></td><td colspan=\"2\" style=\"color:$tekstcolor;\" ><input class=\"inputbox\" type=\"text\" style=\"width:200px\" name=\"addr2\" onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$addr2\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		if ($postnr==$k_postnr) $tekstcolor="#444444";
		else {$tekstcolor="#ff0000";$ret=1;};
		print "<tr><td><span style=\"color:$tekstcolor;\" title=\"$k_postnr\">Postnr.</span> &amp; ";
		if ($bynavn==$k_bynavn) $tekstcolor="#444444";
		else {$tekstcolor="#ff0000";$ret=1;};
		print "<span style=\"color:$tekstcolor;\" title=\"$k_bynavn\">by</span></td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:45px;\" name=\"postnr\" onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$postnr\" onchange=\"javascript:docChange = true;\" $disabled><input class=\"inputbox\" type=\"text\" style=\"width:150px;margin-left:3px;\" name=\"bynavn\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$bynavn\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		if ($land==$k_land) $tekstcolor="#444444";
		else {$tekstcolor="#ff0000";$ret=1;};
		print "<tr><td style=\"color:$tekstcolor;\" title=\"$k_land\">Land</td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:200px\" name=\"land\" onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$land\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		if (!$sag_id) { #20140826
			print "<tr><td>Att.</td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:200px\" name=\"kontakt\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontakt\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		} else {
			print "<tr><td>Att.</td><td colspan=\"2\"><div class=\"ddbox\"><input class=\"inputbox ddtext\" type=\"text\" name=\"kontakt\" id=\"Textbox\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontakt\" onchange=\"javascript:docChange = true;DropDownIndexClear('DropDownExTextbox');\" $disabled>\n";
			print "<select name=\"DropDownExTextbox\" id=\"DropDownExTextbox\" tabindex=\"1000\" onchange=\"DropDownTextToBox(this,'Textbox');\" class=\"inputbox ddselect\" $disabled>\n";
			for ($y=0;$y<=count($k_kontakt);$y++) {
        print "<option value=\"$k_kontakt[$y]\">$k_kontakt[$y]</option>\n";
			}
			print "</select></div></td></tr>\n";
			
			print "<script language=\"javascript\" type=\"text/javascript\">
							DropDownIndexClear(\"DropDownExTextbox\");
						</script>\n";
		}
		print "<tr><td title=\"Kundens ordrenummer som refererence\">Kundeordre</td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:200px\" name=\"kundeordnr\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kundeordnr\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
#cho "$cvrnr!=$k_cvrnr<br>";
		if ($cvrnr!=$k_cvrnr || $ean!=$k_ean || $email!=$k_email || $institution!=$k_institution) $ret=1;
		if ($ret) {
			print "<tr><td></td><td align=\"center\"><a href=\"sync_stamkort.php?konto_id=$konto_id&ordre_id=$id&retning=op\"><img src=\"../ikoner/up.png\" title=\"Klik her for at synkronisere stamkort med informationer fra ordre\" style=\"border: 0px solid; width: 25px; height: 25px;\"></a></td>";
			print "<td align=\"center\"><a href=\"sync_stamkort.php?konto_id=$konto_id&ordre_id=$id&retning=ned\"><img src=\"../ikoner/down.png\" title=\"Klik her for at synkronisere ordre med informationer fra stamkort\" style=\"border: 0px solid; width: 25px; height: 25px;\"></a></td></tr>\n";
		}
		print "</tbody></table></td>\n\n"; # <- Tabel 4.1
		print "<td width=\"38%\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"250\">\n"; #Tabel 4.2 ->
		($cvrnr==$k_cvrnr)?$tekstcolor="#444444":$tekstcolor="#ff0000";
		print "<tr><td style=\"color:$tekstcolor;\" title=\"$k_cvrnr\">CVR-nr.</td><td><input class=\"inputbox\" type=\"text\" style=\"width:130px\" name=\"cvrnr\" value=\"$cvrnr\" onchange=\"javascript:docChange = true;\" $disabled></td>\n";
		($ean==$k_ean)?$tekstcolor="#444444":$tekstcolor="#ff0000";
		print "<td>&nbsp;</td><td style=\"color:$tekstcolor;\">EAN-nr.</td><td><input class=\"inputbox\" type=\"text\" style=\"width:130px\" name=\"ean\" value=\"$ean\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		($email==$k_email)?$tekstcolor="#444444":$tekstcolor="#ff0000";
		print "<tr><td style=\"color:$tekstcolor;\" title=\"$k_email\">E-mail</td><td><input class=\"inputbox\" type=\"text\" style=\"width:130px\" name=\"email\" value=\"$email\" onchange=\"javascript:docChange = true;\"></td>\n";
		($institution==$k_institution)?$tekstcolor="#444444":$tekstcolor="#ff0000";
		print "<td></td><td style=\"color:$tekstcolor;\" title=\"$k_institution\">Institution</td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:130px\" name=\"institution\" value=\"$institution\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		print "<tr><td>Udskriv&nbsp;til</td>\n";
		if (!$udskriv_til) {
			if ($mail_fakt) $udskriv_til="email";
			if ($oio_fakt) $udskriv_til="oioxml";
			if ($lev_pbs_nr) {
				if ($pbs == "FI") $udskriv_til="PBS_FI";
				elseif ($pbs == "BS") $udskriv_til="PBS_BS";
			}
		}
		if (!$udskriv_til) $udskriv_til="PDF";
		print "<td><select class=\"inputbox\" style=\"width:130px\" name=\"udskriv_til\">\n";
		if ($udskriv_til=="PBS_FI" && $lev_pbs!='B') print "<option value=\"PBS_FI\">PBS</option>\n";
		else print "<option>$udskriv_til</option>\n";
		if ($udskriv_til!="PDF") print "<option>PDF</option>\n";
		if ($udskriv_til!="PDF-tekst") print "<option title=\"Udskrives som PDF uden baggrund\">PDF-tekst</option>\n";
		if ($udskriv_til!="email") print "<option title=\"Sendes som PDF via e-mail\">email</option>\n";
		if ($udskriv_til!="oioxml") print "<option title=\"Kun ved fakturering/kreditering.\">oioxml</option>\n"; #PHR 20090803
		if ($udskriv_til!="oioubl") print "<option title=\"Kun ved fakturering/kreditering.\">oioubl</option>\n"; #PHR 20090803
		if ($udskriv_til!="edifakt") print "<option title=\"Kun ved fakturering/kreditering.\">edifakt</option>\n"; #PHR 20140201
		if ($udskriv_til!="historik" && db_fetch_array(db_select("select * from grupper where ART = 'bilag' and (box6='on' or (box1 !='' and box2 !='' and box3 !=''))",__FILE__ . " linje " . __LINE__))) {
			print "<option title=\"Gem en kopi og vedhæft kundens historik\">historik</option>\n";
		}
		$tmp=$pbs_nr*1;
# 20120822
		if ($lev_pbs_nr) {
			if ($lev_pbs == 'L') {
				if ($tmp) print "<option value=\"PBS_FI\">PBS</option>\n";
			} else {
				if ($udskriv_til!="PBS_FI" && $lev_pbs!='B') print "<option value=\"PBS_FI\">PBS</option>\n";
				elseif ($tmp && $udskriv_til!="PBS_BS" && $lev_pbs=='B') print "<option title=\"Opkr&aelig;ves via PBS betalingsservice\">PBS_BS</option>\n";
			}
		}
		print "</SELECT></td>\n";
		if ($status<='1' && $udskriv_til=="PBS_FI" && !$fakturadate) print "<BODY onLoad=\"javascript:alert('Der kan ikke udskrives til PBS hvis der ikke er angivet fakturadato')\">\n"; 

		print "<td>&nbsp;</td><td>Momssats</td><td><input class=\"inputbox\" style=\"text-align:right;width:40px\" type=\"text\" name=\"momssats\" value=\"".dkdecimal($momssats)."\" onchange=\"javascript:docChange = true;\" $disabled>%</td></tr>\n";
		/*
		print "<tr><td colspan=2>Send pr. mail&nbsp;</td><td><input class=\"inputbox\" type=\"checkbox\" name=\"mail_fakt\" onchange=\"javascript:docChange = true;\" $mail_fakt></td>\n";
		if ($lev_pbs_nr) {
			if ($pbs == "FI") $pbs_fi='checked';
			elseif ($pbs == "BS") $pbs_bs='checked';
			$title="PBS udsender FI indbetalingskort";
			if (!$pbs_bs) { #naeste linje ingen apostrof omkring $pbs_fi
				print "<td colspan=\"2\" title=\"$title\">Faktura via PBS (FI)</td><td title=\"$title\"><input class=\"inputbox\" type=\"checkbox\" name=\"pbs_fi\" $pbs_fi onchange=\"javascript:docChange = true;\"></td></tr>\n";
				if ($pbs_nr && !$pbs_fi) print "<tr><td colspan=\"2\"><td>\n";
			}
			$title="Opkr&aelig;ves via PBS betalingsservice";
			if ($pbs_nr && !$pbs_fi) print "<td colspan=\"2\" title=\"$title\">Opkr&aelig;v via PBS (BS)</td><td title=\"$title\"><input class=\"inputbox\" type=\"checkbox\" name=\"pbs_bs\" \"$pbs_bs\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		} else print "</tr>\n";
*/
		($mail_bilag=='on')?$checked="checked='checked'":$checked=NULL;
		if ($udskriv_til=="email" && (strpos($_SERVER['SERVER_NAME'],'ateway') || strpos($_SERVER['SERVER_NAME'],'sl3'))) print "<tr><td>Mail bilag</td><td><input type=\"checkbox\" name=\"mail_bilag\" $checked></td>"; #20131122 Checkbox til mail_bilag
		else print "<tr><td colspan=\"2\"><input type=\"hidden\" name=\"mail_bilag\" value=\"$mail_bilag\"></td>";
		if ($procentvare) print "<td>&nbsp;</td><td>Procenttillæg</td><td><input class=\"inputbox\" style=\"text-align:right;width:40px\" type=\"text\" name=\"procenttillag\" value=\"".dkdecimal($procenttillag)."\" onchange=\"javascript:docChange = true;\" $disabled>%</td></tr>\n";
		else print "</tr>\n";
		if (db_fetch_array(db_select("select distinct sprog from formularer where sprog != 'Dansk'",__FILE__ . " linje " . __LINE__))) {
			print "<tr><td title=\"Sprog som skal anvendes p&aring; dokumenter som tilbud, ordrer, fakturaer med videre.\">Sprog</span></td>\n";
			print "<td><select class=\"inputbox\" style=\"width:130px\" name=\"sprog\">\n";
			print "<option>$formularsprog</option>\n";
			$q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) print "<option>$r[sprog]</option>\n";
			print "</SELECT></td>";
		} else print "<tr><td colspan=\"2\"></td>";
		print "<tr><td colspan=\"5\"><hr></td></tr>\n";
		print "<tr><td width=\"20%\">Ordredato</td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:130px\" name=\"ordredato\" value=\"$ordredato\" onchange=\"javascript:docChange = true;\" $disabled></td>\n";
		if ($hurtigfakt=='on') print "<td></td></tr>\n";
		else {
			if ($art=='DK') print "<td title=\"Dato for returnering\">Modt.&nbsp;dato</td>";
			else print "<td title=\"Leveringsdato\">Lev.&nbsp;dato</td>";
			print "<td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:130px\" name=\"levdato\" value=\"$levdato\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		}
		if ($fakturadato||$status>0) {
			print "<tr><td ";
			if ($art!='DK') print "title=\"Fakturadato\">Fakt.&nbsp;dato";
			else print "title=\"Dato for kreditnota\">KN.&nbsp;dato";
			print "</td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:130px\" name=\"fakturadato\" value=\"$fakturadato\" onchange=\"javascript:docChange = true;\"></td>\n";
			$tmp="Genfaktureringsdato. Dette felt skal kun udfyldes, hvis der er tale om et abonnement eller \nlignende, som skal faktureres igen p&aring; et senere tidspunkt. \nSkriv datoen for n&aelig;ste fakturering";
			if ($art=='DO') print "<td width=\"20%\" title=\"$tmp\">Genfakt.</span></td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:130px\" name=\"genfakt\" value=\"$genfakt\" onchange=\"javascript:docChange = true;\"></td>\n";
		}
		print "<tr><td>Betaling</td>\n";
		if (!$betalingsbet) $betalingsbet="Netto";
		if ($art=='DK') {
			print "<td colspan=\"2\"><select class=\"inputbox\" style=\"width:130px\" name=\"betalingsbet\">\n";
			if ($betalingsbet=='Kontant')		print "<option>Kontant</option>\n";
			if ($betalingsbet=='Netto')			print "<option value='Netto'>Konto</option>\n";
			if ($betalingsbet!='Kontant')		print "<option>Kontant</option>\n";
			if ($betalingsbet!='Netto')			print "<option>Netto</option>\n";
			if ($betalingsbet=='Kontant'||$betalingsbet=='Efterkrav'||$betalingsbet=='Forud'||$betalingsbet=='Kreditkort') $betalingsdage='';
			else $betalingsdage=0;
			print "</SELECT></td>\n";
		} else {
			if (!$betalingsbet) $betalingsbet="Netto";
			print "<td colspan=\"2\"><select class=\"inputbox\" style=\"width:96px\" name=\"betalingsbet\" $disabled>\n";
			print "<option style=\"color: red !important;\">$betalingsbet</option>\n";
			if ($betalingsbet!='Forud')			print "<option>Forud</option>\n";
			if ($betalingsbet!='Kontant')		print "<option>Kontant</option>\n";
			if ($betalingsbet!='Kreditkort')print "<option>Kreditkort</option>\n";
			if ($betalingsbet!='Efterkrav')	print "<option>Efterkrav</option>\n";
			if ($betalingsbet!='Netto')			print "<option>Netto</option>\n";
			if ($betalingsbet!='Lb. md.') 	print "<option>Lb. md.</option>\n";
			if ($betalingsbet=='Kontant'||$betalingsbet=='Efterkrav'||$betalingsbet=='Forud'||$betalingsbet=='Kreditkort') $betalingsdage='';
			elseif (!$betalingsdage) $betalingsdage='Nul';
			if ($betalingsdage)	{
				if ($betalingsdage=='Nul') $betalingsdage=0;
				print "</SELECT>+<input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:25px\" name=\"betalingsdage\" value=\"$betalingsdage\" onchange=\"javascript:docChange = true;\" $disabled></td>\n";
			}
		}
		$list=array();
		$beskriv=array();
		$list[0]='DKK';
		$x=0;
		$q = db_select("select * from grupper where art = 'VK'order by box1 ",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			$x++;
			$list[$x]=$r['box1'];
			$beskriv[$x]=$r['beskrivelse'];
		}
		$tmp=$x;
		if ($x>0) {
			$list[0]='DKK';
			$beskriv[0]='Danske kroner';
			print "<td>Valuta</td>\n";
			print "<td><select class=\"inputbox\" NAME=\"ny_valuta\">\n";
			for ($x=0; $x<=$tmp; $x++) {
				if ($valuta!=$list[$x]) print "<option title=\"$beskriv[$x]\">$list[$x]</option>\n";
				else print "<option title=\"$beskriv[$x]\" selected=\"selected\">$list[$x]</option>\n";
			}
			print "</SELECT></td><td></td>\n";
		} else //print "<tr><td colspan=\"2\" width=\"200\">\n"; # udkommenteret 15052014
		print "</tr>\n";
		$r=db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
		$adr_id=$r['id']*1;
		$x=0;
		$ansat=array();
		$a_afd=array();
		$q=db_select("select navn,afd from ansatte where konto_id = '$adr_id' and lukket != 'on' order by navn",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$ansat[$x]=$r['navn'];
			$a_afd[$x]=$r['afd'];
			$x++;
		}
		if (!in_array($ref,$ansat)) {
			$r=db_fetch_array(db_select("select navn from ansatte,brugere where brugernavn='$ref' and ansatte.id=".nr_cast('brugere.ansat_id')."",__FILE__ . " linje " . __LINE__));
			if ($r['navn']) $ref=$r['navn'];
		}
		for ($x=0;$x<count($ansat);$x++) {
			if (!$x) {
				print "<tr><td>Vor ref.</td>\n";
				print "<td><select class=\"inputbox\" name=\"ref\" $disabled>\n";
				print "<option>$ref</option>\n";
			}
			if ($ref!=$ansat[$x]) print "<option> $ansat[$x]</option>\n";
		}
		print "</select>\n";
		$x=0;
		$afd_navn=array();
		$afd_nr[$x]=array();	
		$q = db_select("select * from grupper where art = 'AFD'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)){
			$afd_nr[$x]=$r['kodenr'];
			$afd_navn[$x]=$r['beskrivelse'];
			$x++;
		}
		if (count($afd_nr)>1) {
			print "</td><td></td>\n";
			Print "<td>Afd</td><td><select name=\"afd\">";
			for ($x=0;$x<count($afd_nr);$x++) {
				if ($afd_nr[$x]==$afd) print "<option value=\"$afd_nr[$x]\">$afd_nr[$x] $afd_navn[$x]</option>";
			} 
			for ($x=0;$x<count($afd_nr);$x++) {
				if ($afd_nr[$x]!=$afd) print "<option value=\"$afd_nr[$x]\">$afd_nr[$x] $afd_navn[$x]</option>";
			} 
			print "</select>";
		}
		print "</td></tr>\n";
		$list=array();
		$beskriv=array();
		$x=0;
		$q = db_select("select * from grupper where art = 'PRJ' and kodenr != '0' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			$x++;
			$list[$x]=$r['kodenr'];
			$beskriv[$x]=$r['beskrivelse'];
		}
		$projektantal=$x;
		if ($x>0) {
			$vis_projekt='on';
			print "<td title=\"Hvis hele ordren skal registreres p&aring; et projekt, v&aelig;lges projektet her. Ellers anvendes projektfeltet p&aring; ordrelinjen.\">Projekt</td>\n";
			print "<td><select class=\"inputbox\" name=\"projekt[0]\">\n";
			for ($x=0; $x<=$projektantal; $x++) {
				if ($projekt[0]!=$list[$x]) print "<option title=\"$beskriv[$x]\">$list[$x]</option>\n";
				else print "<option title=\"$beskriv[$x]\" selected=\"selected\">$list[$x]</option>\n";
			}
			print "</select></td></tr>\n";
		} else print "<tr><td colspan=\"2\" width=\"200\"></tr>\n";

		if ($status==0&&$hurtigfakt!="on") print "<tr><td>Godkend</td><td><input class=\"inputbox\" type=\"checkbox\" name=\"godkend\" $disabled></td></tr>\n";
		elseif ($status<3&&$hurtigfakt!="on") {
			if ($restordre) $restordre="checked";
			else $restordre = "";
			print "<tr><td>Restordre</td><td><input class=\"inputbox\" type=\"checkbox\" name=\"restordre\" $restordre></td>\n";
		}
		print "</tbody></table></td>\n"; # <- Tabel 4.2
		print "<td width=\"31%\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" valign=\"top\">\n"; # Tabel 4.3 ->
		if ($vis_lev_addr || !$kontonr) {
			print "<tr><td align=\"center\">$jobkort $debitorkort</td><td align=\"right\">Vis leveringsadresse <input type=\"checkbox\" name=\"vis_lev_addr\" checked=\"checked\"><td></tr>\n";
			print "<tr><td colspan=\"2\"><hr><td></tr>\n";
			print "<tr><td colspan=\"2\" align=\"center\"><b>Leveringsadresse</b></td></tr>\n";
			print "<tr><td colspan=\"2\"><hr></b></tr>\n";
			print "<tr><td>Firmanavn</td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:200px\" onfocus=\"document.forms[0].fokus.value=this.name;\" name=\"lev_navn\" value=\"$lev_navn\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
			print "<tr><td>Adresse</td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:200px\" onfocus=\"document.forms[0].fokus.value=this.name;\" name=\"lev_addr1\" value=\"$lev_addr1\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
			print "<tr><td></td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:200px\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"lev_addr2\" value=\"$lev_addr2\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
			print "<tr><td>Postnr. &amp; by</td><td><input class=\"inputbox\" type=\"text\" style=\"width:45px\" onfocus=\"document.forms[0].fokus.value=this.name;\" name=\"lev_postnr\" value=\"$lev_postnr\" $disabled><input class=\"inputbox\" type=\"text\" style=\"width:150px;margin-left:3px;\" name=\"lev_bynavn\" value=\"$lev_bynavn\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
			print "<tr><td>Att.</td><td colspan=\"2\"><input class=\"inputbox\" type=\"text\" style=\"width:200px\" onfocus=\"document.forms[0].fokus.value=this.name;\" name=\"lev_kontakt\" value=\"$lev_kontakt\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
			print "<input type=\"hidden\" name=\"felt_1\" style=\"width:200px\" value=\"$felt_1\">\n";
			print "<input type=\"hidden\" name=\"felt_2\" style=\"width:200px\" value=\"$felt_2\">\n";
			print "<input type=\"hidden\" name=\"felt_3\" style=\"width:200px\" value=\"$felt_3\">\n";
			print "<input type=\"hidden\" name=\"felt_4\" style=\"width:200px\" value=\"$felt_4\">\n";
			print "<input type=\"hidden\" name=\"felt_5\" style=\"width:200px\" value=\"$felt_5\">\n";
		} else {
			print "<tr><td align=\"center\">$jobkort $debitorkort</td><td align=\"right\">Vis leveringsadresse <input type=\"checkbox\" name=\"vis_lev_addr\"><td></tr>\n";
			print "<tr><td colspan=\"2\"><hr><td></tr>\n";
			print "<tr><td colspan=\"2\" align=\"center\"><b>".findtekst(243,$sprog_id)."</b></tr>\n";
			print "<tr><td colspan=\"2\"><hr></b></tr>\n";
			if ($vis_saet) {
				$felt_2*=1;
				$felt_4*=1;

				if ($status>=1) {
					if (!$felt_2) $felt_2=$sum+$moms;
					($felt_2<$sum+$moms)?$felt_4=$sum+$moms-$felt_2:$felt_4=0;
				}
				$felt_2=dkdecimal($felt_2,2);
				$felt_4=dkdecimal($felt_4,2);
				$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
				$kortantal=$r['box4']*1;
				$korttyper=explode(chr(9),$r['box5']);
				print "<tr><td><select name=\"felt_1\">";
				if (!$felt_1) $felt_1=$korttyper[0];
				if ($felt_1) print "<option value=\"$felt_1\">$felt_1</value>";
				if ($felt_1!='Kontant') print "<option value=\"Kontant\">Kontant</value>";
				for($x=0;$x<$kortantal;$x++) {
					if ($felt_1!=$korttyper[$x]) print "<option value=\"$korttyper[$x]\">$korttyper[$x]</value>";
				}
				print "</select></td><td><input class=\"inputbox\" type=\"text\" name=\"felt_2\" style=\"text-align:right;width:200px\" value=\"$felt_2\" $disabled></td></tr>";	
				print "<tr><td><select name=\"felt_3\">"; 
				if ($felt_3) print "<option value=\"$felt_3\">$felt_3</value>";
				if ($felt_3!='Kontant') print "<option value=\"Kontant\">Kontant</value>";
				for($x=0;$x<$kortantal;$x++) {
					if ($felt_3!=$korttyper[$x]) print "<option value=\"$korttyper[$x]\">$korttyper[$x]</value>";
				}
				print "</select></td><td><input class=\"inputbox\" type=\"text\" name=\"felt_4\" style=\"text-align:right;width:200px\" value=\"$felt_4\" $disabled></td></tr>";	
			} else {
				if (substr(findtekst(244,$sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst(249,$sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(244,$sprog_id)."</span></td><td><input class=\"inputbox\" type=\"text\" name=\"felt_1\" style=\"width:200px\" value=\"$felt_1\" $disabled></td></tr>\n";
				if (substr(findtekst(245,$sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst(250,$sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(245,$sprog_id)."</span></td><td><input class=\"inputbox\" type=\"text\" name=\"felt_2\" style=\"width:200px\" value=\"$felt_2\" $disabled></td></tr>\n";
				if (substr(findtekst(246,$sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst(251,$sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(246,$sprog_id)."</span></td><td><input class=\"inputbox\" type=\"text\" name=\"felt_3\" style=\"width:200px\" value=\"$felt_3\" $disabled></td></tr>\n";
				if (substr(findtekst(247,$sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst(252,$sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(247,$sprog_id)."</span></td><td><input class=\"inputbox\" type=\"text\" name=\"felt_4\" style=\"width:200px\" value=\"$felt_4\" $disabled></td></tr>\n";
				if (substr(findtekst(248,$sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst(253,$sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(248,$sprog_id)."</span></td><td><input class=\"inputbox\" type=\"text\" name=\"felt_5\" style=\"width:200px\" value=\"$felt_5\" $disabled></td></tr>\n";
			}
			print "<input type=\"hidden\" name=\"lev_navn\" value=\"$lev_navn\">\n";
			print "<input type=\"hidden\" name=\"lev_addr1\" value=\"$lev_addr1\"><input type=\"hidden\" name=\"lev_addr2\" value=\"$lev_addr2\">\n";
			print "<input type=\"hidden\" name=\"lev_postnr\" value=\"$lev_postnr\"><input type=\"hidden\" name=\"lev_bynavn\" value=\"$lev_bynavn\">\n";
			print "<input type=\"hidden\" name=\"lev_kontakt\" value=\"$lev_kontakt\">\n";
		}
		print "</td></tr></tbody></table></td></tr>\n"; #<- Tabel 4.3
		
		$row2 = db_fetch_array(db_select("select notes from adresser where kontonr = '$kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__)); #20142403-1
		$notes=str_replace("\n","<br>",$row2['notes']);
		if ($notes) print "<tr><td colspan=\"3\" witdh=\"100%\" style=\"color: rgb(255,0,0)\">$notes</td></tr>\n";
		/*
		$query = db_select("select notes from adresser where kontonr = '$kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__);
		if ($row2 = db_fetch_array($query) ) {
			$notes=str_replace("\n","<br>",$row2['notes']);
			print "<tr><td colspan=\"3\" witdh=\"100%\" style=\"color: rgb(255,0,0)\">$notes</td></tr>\n";
		}
		*/
		if ($udskriv_til=='email') {
			if (!$formularsprog) $formularsprog='Dansk';
			($status<1)?$form_nr=1:$form_nr=2;
			if ($art=='DK')$form_nr=5;
			$q = db_select("select * from formularer where formular='$form_nr' and art='5' and lower(sprog)='".strtolower($formularsprog)."'",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if ($r['xa']=='1') $std_subj=$r['beskrivelse'];
				elseif ($r['xa']=='2') $std_txt_title=$r['beskrivelse'];
			}
			if ($art!='DK')
			$q = db_select("select * from formularer where formular='4' and art='5' and lower(sprog)='".strtolower($formularsprog)."'",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if ($r['xa']=='1') $fak_subj=$r['beskrivelse'];
				elseif ($r['xa']=='2') $fak_text=str_replace("<br>","",$r['beskrivelse']);
			}
			$subj_title='';
			if (!$mail_subj && $art!='DK') $subj_title=HtmlEntities("Ved fakturering ændres emneteksten til:\n\n$fak_subj",ENT_COMPAT,$charset);
			$text_title='';
			if (!$mail_text && $art!='DK') $text_title=HtmlEntities("Ved fakturering ændres mailteksten til:\n\n$fak_text",ENT_COMPAT,$charset);
			list($std_txt,$tmp)=explode("<br>",$std_txt_title);
			($mail_text)?$std_txt_title=$mail_text:$std_txt_title=str_replace("<br>","",$std_txt_title);

			print "<tr><td align=\"center\" colspan=\"3\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\"><tbody>\n"; #Tabel 4.4 ->
			if (!$mail_subj && !$mail_text && $art!='DK') print "<tr><td></td><td colspan=\"1\" align=\"left\"><small>Nedenstående tekster ændres ved fakturering, hold musen over beskrivelsen til venstre for at se ændringen</small></td>";
			print "<tr><td width=\"120px\" title=\"$subj_title\">Mail emne</td><td title=\"$std_subj\"><input class=\"inputbox\" type=\"text\" style=\"width:1000px;\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_subj\" placeholder=\"$std_subj\" value=\"$mail_subj\" onchange=\"javascript:docChange = true;\"></td>";
			if ($bilag) { 
				if ($dokument) print "<td title=\"klik her for at &aring;bne bilaget: $dokument\"><a href=\"../includes/bilag.php?kilde=ordrer&filnavn=$dokument&bilag_id=$id&bilag=$dokument&kilde_id=$id\"><img style=\"border: 0px solid\" alt=\"clip_m_papir\" src=\"../ikoner/paper.png\"></a></td>";
				else print "<td title=\"klik her for at vedh&aelig;fte et bilag\"><a href=\"../includes/bilag.php?kilde=ordrer&bilag_id=$id&bilag=$dokument&ny=ja&kilde_id=$id\"><img  style=\"border: 0px solid\" alt=\"clip\" src=\"../ikoner/clip.png\"></a></td>";
			}
			print "</tr><tr><td valign=\"top\"  title=\"$text_title\">Mail tekst</td><td title=\"$std_txt_title\">";
			if ($mail_text) print "<textarea style=\"width:1000px;\" rows=\"2\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_text\" onchange=\"javascript:docChange = true;\">$mail_text</textarea>\n";
			else print "<input class=\"inputbox\" type=\"text\" style=\"width:1000px;\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_text\" placeholder=\"$std_txt\" value=\"$mail_text\" onchange=\"javascript:docChange = true;\">";
			print "</td></tr></tbody></table></td></tr>\n"; # <- Tabel 4.4	
		}
		print "<tr><td align=\"center\" colspan=\"3\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n"; # Tabel 4.5 ->
 		if ($kontonr) {
			print "<tr><td align=\"center\" title=\"Positionsnummer. R&aelig;kkef&oslash;lgen &aelig;ndres ved at overskrive positionsnumrene (1,5 hvis mellem 1 og 2). En enkelt linje slettes ved at skrive minustegn som positionsnummer.\">Pos.</td><td align=\"center\" title=\"Varenummer. Skriv hele varenumret eller klik p&aring; Opslag for at v&aelig;lge. Hvis du vil v&aelig;lge mellem varenumre startende med t, s&aring; skriv t* i feltet og klik p&aring; Opslag.\">Varenr.</td><td align=\"center\" title=\"Antal enheder. Timer og minutter kan angives med : som skilletegn. Eksempelvis 5:45 som bliver til 5,75.\">Antal</td><td align=\"center\">Enhed</td><td align=\"center\" title=\"Brug [Shift]+[Enter] for et indsætte et linjeskift i en beskrivelseslinje\">Beskrivelse</td><td align=\"center\">Pris</td><td align=\"center\">Rabat</td>";
			if ($procentfakt) print "<td align=\"center\">Procent</td>";
			print "<td align=\"center\">I alt</td>";
			if ($vis_projekt && !$projekt[0]) print "<td align=\"center\">Proj.</td>";
			if ($genfakt) print "<td align=\"center\" title=\"'Kun denne ordre'. Afm&aelig;rk dette felt hvis ordrelinjen ikke skal med ved genfakturering eller kopiering af ordren.\">kdo</td>\n";
			if ($status>=1 && $hurtigfakt!='on')  {
				if ($art!='DK') {
					$tmp="Lev&eacute;r";
					$tmp2="Indtastningsfeltet herunder er det antal, som leveres ved klik p&aring; Lev&eacute;r. Antallet i parantes er det, som allerede er leveret.";
				} else {
					$tmp="Modtag";
					$tmp2="Indtastningsfeltet herunder er det antal, som modtages ved klik p&aring; Modtag. Antallet i parantes er det, som allerede er modtaget.";
				}
				print "<td colspan=\"2\" align=\"center\" title=\"$tmp2\">$tmp</td>";
			}
		}
		if ($omkunde) print "<td title =\"Hvis feltet vises er kunden underlagt reglerne for omvendt betalingspligt. Er varen ligeledes omfattet vil feltet herunder være afmærket pr default og momsen vil være undertrykt for den pågældende vare.\">O/B</td>";
		print "</tr>\n";
		if (!$status) $status=0;
		print "<input type=\"hidden\" name=\"status\" value=\"$status\">";
		print "<input type=\"hidden\" name=\"id\" value=\"$id\">\n";

		$x=0;
		if (!$ordre_id) $ordre_id=0;
		$kostpris[0]=0;
		$blandet_moms=0;#tilfojet 20100923 grundet afrundingsfejl på ordre med rabat
		$lagervarer=0;
#		db_modify("update ordrelinjer set posnr=posnr+10  where varenr = '$svnr'");
#		global $db;
		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr",__FILE__ . " linje " . __LINE__);
#		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by saet desc,samlevare,posnr,id",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if ($row['posnr']>0 && !is_numeric($row['samlevare']) && $row['samlevare'] <1) {  #Hvis "samlevare" er numerisk,indgaar varen i den ordrelinje,der refereres til - hvis "on" er varen en samlevare.
				$x++;
				$linje_id[$x]=$row['id'];
				$kred_linje_id[$x]=$row['kred_linje_id'];
				$posnr[$x]=$row['posnr'];
				$varenr[$x]=HtmlEntities(trim($row['varenr']),ENT_COMPAT,$charset);
				$beskrivelse[$x]=HtmlEntities(trim($row['beskrivelse']),ENT_COMPAT,$charset);
				$enhed[$x]=HtmlEntities(trim($row['enhed']),ENT_COMPAT,$charset);
				$pris[$x]=$row['pris'];
				$rabat[$x]=$row['rabat']*1;
				$rabatart[$x]=$row['rabatart'];
				$procent[$x]=$row['procent']*1;
				$antal[$x]=$row['antal']*1;
				$leveres[$x]=$row['leveres'];				
				$vare_id[$x]=$row['vare_id'];
				$momsfri[$x]=$row['momsfri'];
				$rabatgruppe[$x]=$row['rabatgruppe'];
				$m_rabat[$x]=$row['m_rabat']*-1;
				$folgevare[$x]=$row['folgevare']*1;
				$varemomssats[$x]=$row['momssats']*1;
				$fast_db[$x]=$row['fast_db']*1;
				$saet[$x]=$row['saet'];
				$lev_varenr[$x]=$row['lev_varenr'];
				$kostpris[$x]=$row['kostpris'];
				$kostsum+=$kostpris[$x]*$antal[$x];
				($row['omvbet'])?$omvbet[$x]='checked':$omvbet[$x]='';
				if (!$momsfri[$x] && !$varemomssats[$x]) $varemomssats[$x]=$momssats;
				elseif ($varemomssats[$x] > $momssats) $varemomssats[$x]=$momssats;
				elseif ($momsfri[$x] || $omvbet[$x]) $varemomssats[$x]=0;
				$serienr[$x]=HtmlEntities(trim($row['serienr']),ENT_COMPAT,$charset);
				$samlevare[$x]=$row['samlevare'];
				$projekt[$x]=$row['projekt'];
				if ($row['kdo']) $kdo[$x]='checked';
				else $kdo[$x]='';
				if ($vare_id[$x]) {
					if ($fast_db[$x]) {
						$kostpris[$x]=$pris[$x]*$fast_db[$x];
/*
					} else {
						list($koordpr,$koordnr,$koordant,$koordid,$koordart)=explode(chr(9),find_kostpris($vare_id[$x],$linje_id[$x]));
						$kobs_ordre_pris=explode(",",$koordpr);
						$kobs_ordre_antal=explode(",",$koordant);
						$ko_ant[$x]=count($kobs_ordre_pris);
						$kostpris[$x]=0;
				#rettet 20120418 grundet fejl i kostpris v leverring af flere omgange på samme ordrelinje på købsordre
				#rettet yderligere 20121213 grundet ny fejl hvis køb er fordelt over flere købsordrer
						for($y=0;$y<$ko_ant[$x];$y++) $kostsum[$x]+=$kobs_ordre_pris[$y]*$kobs_ordre_antal[$y];
						($antal[$x])?$kostpris[$x]=$kostsum[$x]/$antal[$x]:$kostpris[$x]=0;
						if ($valutakurs) $kostpris[$x]*=100/$valutakurs; #20140116
*/
					}
/*					
#cho "update ordrelinjer set kostpris='$kostpris[$x]' where id='$linje_id[$x]'<br>";
				db_modify("update ordrelinjer set kostpris='$kostpris[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
*/
				if ($rabatart[$x]=='amount') $db[$x]=$pris[$x]-$rabat[$x]; #20140424 -= 
					else $db[$x]=$pris[$x]-($pris[$x]*$rabat[$x]/100); #20140424 -= 
					$db[$x]-=$kostpris[$x]; #20140424 -= 
#cho "$db[$x]=$pris[$x]-$kostpris[$x]<br>";
					if ($pris[$x]!=0) $dg[$x]=$db[$x]*100/$pris[$x];
					else $dg[$x]=0;
					$dk_db[$x]=dkdecimal($db[$x]);
					$dk_dg[$x]=dkdecimal($dg[$x]);
				} 				
				if (($art=='DK')&&($antal[$x]<0)) $bogfor==0;
				if ($serienr[$x]) {
					$serienumre[$x]=NULL;
					$q2 = db_select("select serienr from serienr where salgslinje_id='$linje_id[$x]' order by serienr",__FILE__ . " linje " . __LINE__);
					while ($r2 = db_fetch_array($q2)) ($serienumre[$x])?$serienumre[$x].=','.$r2['serienr']:$serienumre[$x]=$r2['serienr'];
				}
				if (!$lagervarer && $vare_id[$x]) {
					$r2=db_fetch_array(db_select("select grupper.box8 from varer,grupper where varer.id = '$vare_id[$x]' and grupper.art='VG' and grupper.kodenr=varer.gruppe",__FILE__ . " linje " . __LINE__));
					if ($r2['box8']) $lagervarer=1;
				}
			}
		}
		$linjeantal=$x;
#cho "Lagervarer $lagervarer<br>";
		$moms=0;
		$sum=0;
		$ny_pos=0;
		$saetnr=0;
		$saetpris=0;
		for ($x=1; $x<=$linjeantal; $x++) {
		if ($saet[$x]) {
			if ($saetnr && $saetnr!=$saet[$x]) { # tilføjer linjen for sætpris
				$r=db_fetch_array(db_select("select beskrivelse from ordrelinjer where saet = '$saetnr' and ordre_id='$id' and samlevare='on'",__FILE__ . " linje " . __LINE__));
				list($sum,$dbsum,$blandet_moms,$moms)=explode(chr(9),ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,'0','0','0','0',$linje_id[$x],'0','','',$r['beskrivelse'],'',$saetpris,$rabat[$x],'percent','100','1','0',$vare_id[$x],'','0','0',$momssats,'','on','','','','','','','','','','','0','','0',$saetnr));
				$saetpris=0;
			}
			if (!$saetpris)	print "<tr><td><br></td></tr>";
			$linjesum=$pris[$x]*$antal[$x];
			$linjesum-=$linjesum*$rabat[$x]/100;
			$linjesum+=$linjesum*$varemomssats[$x]/100;
			$saetpris+=afrund($linjesum,3);
			$saetnr=$saet[$x];
		} elseif ($saetnr) {
			$r=db_fetch_array(db_select("select beskrivelse from ordrelinjer where saet = '$saetnr' and ordre_id='$id' and samlevare='on'",__FILE__ . " linje " . __LINE__));
			list($sum,$dbsum,$blandet_moms,$moms)=explode(chr(9),ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,'0','0','0','0','0','0','','',$r['beskrivelse'],'',$saetpris,'0','percent','100','1','0','0','','0','0','0','','','','','','','','','','','','','0','','0',$saetnr));
			$saetpris=0;
			$saetnr=0;
			print "<tr><td><br></td></tr>";
		}
		if (!$folgevare[$x] || $folgevare[$x]>=0) {
			list($sum,$dbsum,$blandet_moms,$moms,$antal_ialt,$leveres_ialt,$tidl_lev_ialt,$tidl_lev[$x],$levdiff)=explode(chr(9),ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,$leveres_ialt,$tidl_lev_ialt,$levdiff,$projekt[0],$linje_id[$x],$kred_linje_id[$x],$posnr[$x],$varenr[$x],$beskrivelse[$x],$enhed[$x],$pris[$x],$rabat[$x],$rabatart[$x],$procent[$x],$antal[$x],$leveres[$x],$vare_id[$x],$momsfri[$x],$rabatgruppe[$x],$m_rabat[$x],$varemomssats[$x],$serienr[$x],$samlevare[$x],$folgevare[$x],$projekt[$x],$kdo[$x],$kobs_ordre_pris,$ko_ant[$x],$kostpris[$x],$db[$x],$dg[$x],$dk_db[$x],$dk_dg[$x],'0',$omvbet[$x],$saet[$x],$saetnr));
		}
		if ($samlevare[$x]=='on') {
			$q = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' and samlevare = '$linje_id[$x]' order by id",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$antal_ialt+=$r['antal'];
				if ($r['antal']>0) {
					$tmp=0;
					$q2 = db_select("select antal from batch_salg where linje_id = '$r[id]' and ordre_id='$id' and vare_id = '$r[vare_id]'",__FILE__ . " linje " . __LINE__);
					while($r2 = db_fetch_array($q2)) {
						$tmp=$tmp+$r2['antal'];
					}
					if ($art=='DK') $dkantal=dkdecimal($r['antal']*-1);
					else $dkantal=dkdecimal($r['antal']);
					if (substr($dkantal,-1)=='0') $dkantal=substr($dkantal,0,-1);
					if (substr($dkantal,-1)=='0') $dkantal=substr($dkantal,0,-2);
				}
				$tidl_lev_ialt+=$tmp;
				print "<tr><td></td><td>$r[varenr]</td><td align=\"right\">$dkantal</td><td>$r[enhed]</td><td>$r[beskrivelse]</td></tr>";
			}
		}
			print "<input type=\"hidden\" name=\"samlevare[$x]\" value=\"$samlevare[$x]\">\n";
			if ($folgevare[$x]>0) {
				$x_nr=0;
				$fv_linje_id=0;
				for ($i=1;$i<=$linjeantal;$i++) {
					$tmp=$linje_id[$x]*-1;
					if ($tmp==$folgevare[$i]) { 
						$x_nr=$i;
						$fv_linje_id=$linje_id[$i]*1;
						$fv_varenr=$varenr[$i];
						$fv_salgspris=$pris[$i];
						$fv_kostpris=$kostpris[$i];
						$fv_enhed=$enhed[$i];
						$fv_beskrivelse=$beskrivelse[$i];
						$fv_varemomssats=$varemomssats[$i];
						$fv_db=$fv_salgspris-$fv_kostpris;
						($fv_salgspris!=0)?$fv_dg=$fv_db*100/$fv_salgspris:$fv_dg=0;
					}
				}
				if (!$fv_linje_id) {
					global $regnaar;
					$r=db_fetch_array(db_select("select varer.varenr,varer.beskrivelse,varer.enhed,varer.salgspris,varer.kostpris,grupper.box4,grupper.box7 from varer,grupper where varer.id = '$folgevare[$x]' and grupper.art='VG' and grupper.kodenr=varer.gruppe",__FILE__ . " linje " . __LINE__));
					$fv_linje_id=0;
					$fv_varenr=$r['varenr'];
					$fv_salgspris=$r['salgspris'];
					$fv_kostpris=$r['kostpris'];
					$fv_enhed=$r['enhed'];
					$fv_beskrivelse=$r['beskrivelse'];
					$fv_db=$fv_salgspris-$fv_kostpris;
					($fv_salgspris!=0)?$fv_dg=$fv_db*100/$fv_salgspris:$fv_dg=0;
					$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$r[box4]' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
					if ($tmp=trim($r2['moms'])) { # f.eks S3
						$tmp=substr($tmp,1); #f.eks 3
						$r2 = db_fetch_array(db_select("select box2 from grupper where art = 'SM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__));
						if ($r2['box2']) $fv_varemomssats=$r2['box2']*1;
					}	else $fv_varemomssats=$momssats;
				}
				$fv_dk_db=dkdecimal($fv_db);
				$fv_dk_dg=dkdecimal($fv_dg);
				list($sum,$dbsum,$blandet_moms,$moms)=explode(chr(9),ordrelinjer($x_nr,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,$leveres_ialt,$tidl_lev_ialt,$levdiff,$projekt[0],$fv_linje_id,0,$x,$fv_varenr,$fv_beskrivelse,$fv_enhed,$fv_salgspris,0,'percent',$procent[$x],$antal[$x],$leveres[$x],$folgevare[$x],$fv_momsfri,0,0,$fv_varemomssats,0,0,0,$projekt[$x],$kdo[$x],0,0,$fv_kostpris,$fv_db,$fv_dg,$fv_dk_db,$fv_dk_dg,'1',$saet[$x],$saetnr));
			}
			print "<input type=\"hidden\" name=\"folgevare[$x]\" value=\"$folgevare[$x]\">\n";
			if ($saet[$x] && $x==$linjeantal && $saetpris) {
#			$r=db_fetch_array(db_select("select beskrivelse from varer where id = '$svid'",__FILE__ . " linje " . __LINE__));
			$r=db_fetch_array(db_select("select beskrivelse from ordrelinjer where saet = '$saetnr' and ordre_id='$id' and samlevare='on'",__FILE__ . " linje " . __LINE__));
#cho __LINE__."<br>";
			list($sum,$dbsum,$blandet_moms,$moms)=explode(chr(9),ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,'0','0','0','0','0','0','','',$r['beskrivelse'],'',$saetpris,'0','percent','100','1','0','0','','0','0','0','','','','','','','','','','','','','0','','0',$saetnr));
			$saetnr=0;
			}
		}
#		if ($r=db_fetch_array(db_select("select * from ordrelinjer where ordre_id = '$id' and varenr = 'R'",__FILE__ . " linje " . __LINE__))) {
#			$x++;
#			list($sum,$dbsum,$blandet_moms,$moms)=explode(chr(9),ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,'0','0','0','0',$r['id'] ,'0','99','R',$r['beskrivelse'],'',$r['pris'],$r['rabat'],'percent','100'  ,'1'   ,'0',$r['vare_id'],''      ,'0'          ,'0',    $momssats,'','','','','','','','','','','','','0','','0','0'));
#		} else
		if ($vis_saet && $samlet_rabat && $samlet_pris && $samlet_pris != $ordresum) {
			$x++;
			$diff=afrund($samlet_pris-($sum+$moms),3);
			$tmp=$sum+$moms;
			if ($samlet_rabat) {
				$r=db_fetch_array(db_select("select id,beskrivelse from varer where varenr = 'R'",__FILE__ . " linje " . __LINE__));
				opret_ordrelinje($id,$r['id'],'R',1,$r['beskrivelse'],$diff,20,100,'DO','','','0','','','','99','0');
				$r=db_fetch_array(db_select("select * from ordrelinjer where ordre_id = '$id' and varenr = 'R'",__FILE__ . " linje " . __LINE__));
				$vist_rabat=$samlet_pris-$bruttosaetsum."|".$samlet_pris;
				db_modify("update ordrelinjer set lev_varenr='$vist_rabat' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
				list($sum,$dbsum,$blandet_moms,$moms)=explode(chr(9),ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,'0','0','0','0',$r['id'] ,'0','99','R',$r['beskrivelse'],'',$r['pris'],$r['rabat'],'percent','100'  ,'1'   ,'0',$r['vare_id'],''      ,'0'          ,'0',    $momssats,'','','','','','','','','','','','','0','','0','0'));
			}
#		} elseif ($rvnr) {
#			$r=db_fetch_array(db_select("select id,beskrivelse,lev_varenr,pris from ordrelinjer where varenr = 'R' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
#			list($vist_rabat,$samlet_pris)=explode("|",$r['lev_varenr']);
#			$diff=$samlet_pris-afrund($sum+$moms,2);
#			if (afrund($diff,2)) {
##				db_modify("update ordrelinjer set pris=$ny_pris where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
#			}
		}

		print "<input type=\"hidden\" name=\"linjeantal\" value=\"$linjeantal\">\n";
		print "<input type=\"hidden\" name=\"lagervarer\" value=\"$lagervarer\">\n";
		if ($status>=1&&$bogfor!=0 && !$leveres_ialt && $tidl_lev_ialt && $antal_ialt != $tidl_lev_ialt) $del_ordre = 'on';
		else $del_ordre = '';
		if ($kontonr) {
			$x++;
			$posnr[0]=$linjeantal+1;
			if ($varenr[0] && isset($_GET['vare_id'])) $fokus="dkan0"; #20150306 + value i dkan0
			else $varenr[0]=NULL;
			if ($art != 'OT') { // ordrelinje til indtastning behøves ikke at vises ved 'Original tilbud' #20140716
				print "<tr>\n";
				print "<td valign=\"top\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"3\" name=\"posn0\" value=\"$posnr[0]\"></td>\n";
				if ($art=='DK') {print "<td valign=\"top\"><input class=\"inputbox\" readonly=\"readonly\" size=\"12\" name=\"vare0\" onfocus=\"document.forms[0].fokus.value=this.name;\"></td>\n";}
				else {print "<td valign=\"top\"><input class=\"inputbox\" type=\"text\" size=\"12\" name=\"vare0\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$varenr[0]\"></td>\n";}
				print "<td valign=\"top\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"4\" name=\"dkan0\"></td>\n";
				print "<td valign=\"top\"><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=\"readonly\" size=\"3\"></td>\n";
				//print "<td valign=\"top\"><input class=\"inputbox\" type=\"text\" size=\"58\" name=\"beskrivelse0\" onfocus=\"document.forms[0].fokus.value=this.name;\"></td>\n";
				print "<td valign=\"top\"><textarea class=\"autosize inputbox ordreText comment\" id=\"comment\" rows=\"1\" cols=\"58\" name=\"beskrivelse0\" onfocus=\"document.forms[0].fokus.value=this.name; var val=this.value; this.value=''; this.value= val;\"></textarea></td>\n"; #2013.11.27 Ændret til textarea, så hele texten vises #2013.11.29 indsat ny onfocus da chrome ikke satte curser efter tekst
				print "<td valign=\"top\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"10\" name=\"pris0\"></td>\n";
				print "<td valign=\"top\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"4\" name=\"raba0\">\n";
				if ($procentfakt) print "</td><td valign=\"top\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"4\" name=\"proc0\" value=\"100,00\"></td>\n";
				else print "<input type=\"hidden\" name=\"proc0\" value=\"100,00\"></td>\n";
				print "<td valign=\"top\"><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=\"readonly\" size=\"10\"></td>\n";
				if ($genfakt) print "<td title=\"Afm&aelig;rk dette felt hvis ordrelinjen ikke skal med ved genfakturering / kopiering.\"><input class=\"inputbox\" name=\"kdo[0]\" type=\"checkbox\"></td>\n";
				print "<td valign=\"top\" colspan=\"2\"><input type=\"button\" name=\"insert\" class=\"button white small bold\" value=\"B\" onClick=\"this.form.beskrivelse0.value=this.form.beskrivelse0.value.concat('<b></b>'); this.form.beskrivelse0.focus();\" title=\"Indsættes ved fed tekst. Sæt cursoren imellem <b> og </b>\n(F.eks. <b>Lorem ipsum</b>).\">\n"; #2013.11.29 Sætter fokus på felt ved clik
				print "<input type=\"button\" name=\"insert\" class=\"button white small italic\" value=\"I\" onClick=\"this.form.beskrivelse0.value=this.form.beskrivelse0.value.concat('<i></i>'); this.form.beskrivelse0.focus();\" title=\"Indsættes ved kursiv tekst. Sæt cursoren imellem <i> og </i>\n(F.eks. <i>Lorem ipsum</i>).\nKan også bruges til tom linje. Her insættes <i></i> uden tekst. \"></td>\n";
				print "</tr>\n";
			}
			if ($procenttillag) {
				$r=db_fetch_array(db_select("select beskrivelse from varer where varenr = '$procentvare'",__FILE__ . " linje " . __LINE__));
				$tillag=$sum*$procenttillag/100;
				$beskr=var2str($r['beskrivelse'],$id,$posnr[$x],$varenr[$x],$dkantal[$x],$enhed[$x],$dkpris,$dkprocent,$serienr[$x],$varemomssats[$x],$dkrabat[$x]);
				$beskr=str_replace('$procenttillæg;',dkdecimal($procenttillag),$beskr);
				print "<tr>\n";
				print "<td></td>\n";
				print "<td>$procentvare</td>\n";
				print "<td></td>\n";
				print "<td></td>\n";
				print "<td>$beskr</td>\n";
				print "<td></td>\n";
				print "<td></td>\n";
				print "<td></td>\n";
				print "<td align=right>".dkdecimal($tillag)."</td>\n";
				if ($art!='OT') print "<td></td>\n"; #20140716
				print "</tr>\n";
				$sum+=$tillag;
				$dbsum+=$tillag;
				$moms+=$tillag/100*$momssats;
			}
#cho "Sum $sum<br>";
			print "<input type=\"hidden\" name=\"sum\" value=\"$sum\">\n";
#			$tmp=$momssum/100*$momssats;
#			$moms=afrund($tmp,3);
#cho "$blandet_moms && !$incl_moms<br>";
			if (!$blandet_moms && !$incl_moms) $moms=$sum*$momssats/100; #tilfojet 20100923 grundet afrundingsfejl på ordre med rabat
			$moms=afrund($moms*1,3);
			$kostpris[0]=$kostpris[0]*1;
			db_modify("update ordrer set sum=$sum,kostpris=$kostpris[0],moms=$moms where id=$id",__FILE__ . " linje " . __LINE__);
			if ($art=='DK') {
				$sum=$sum*-1;
				$moms=$moms*-1;
			}
			$ialt=($sum+$moms);
			print "<tr><td colspan=\"12\"><table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\"><tbody>\n"; # Tabel 4.5.1 ->
			print "<tr>\n";
#			print "<td align=\"center\">".dkdecimal($procenttillag)."% tillæg:&nbsp;".dkdecimal($tillag)."</td>\n";
			print "<td align=\"center\">Nettosum:&nbsp;".dkdecimal($sum)."</td>\n";
			if ($vis_saet) $db=$sum-$kostsum;
			else $db=$dbsum;
#cho "$db : $kostsum<br>";
			print "<td width=\"20%\" align=\"center\"  title=\"DB: DKK ".dkdecimal($db*$valutakurs/100)."\">";
			if (!$vis_saet) print "D&aelig;kningsbidrag:&nbsp;".dkdecimal($db);
			print "</td>\n";
			if ($sum) $dg_sum=($db*100/$sum);
			else $dg_sum=dkdecimal(0);
			print "<td width=\"20%\" align=\"center\"  title=\"DG;".dkdecimal($dg_sum)."%\">";
			if (!$vis_saet) print "D&aelig;kningsgrad:&nbsp;".dkdecimal($dg_sum)."%";
			print "</td>\n";
			print "<td width=\"20%\" align=\"center\" align=\"center\">Moms:&nbsp;".dkdecimal($moms)."</td>\n";
			print "<td width=\"20%\" align=\"center\" align=\"center\" title=\"DG;".dkdecimal($dg_sum)."%\">I alt:";
			if ($vis_saet && $art=='DO') {
				print "<input type=\"hidden\" name=\"ordresum\" value=\"".afrund($ialt,2)."\">";
				print "<input style=\"width:100px;text-align:right\" type=\"text\" name=\"samlet_pris\" value=\"".dkdecimal($ialt)."\">";
			} else print dkdecimal($ialt);
			print "</td>\n";
		}
		print "</tbody></table></td></tr>\n"; # <- Tabel 4.5.1
		print "<input type=\"hidden\" name=\"fokus\">\n";
		print "<tr><td align=\"center\" colspan=\"12\">\n";
		print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tbody><tr>\n"; # Tabel 4.5.2 ->
		if ($status < 3) {
			if ($levdiff) $status=1;
			elseif ($status==1) $status++;
			//if ($status<1) $width="33%";
			//elseif ($sum!=0) $width="25%";
			if ($hurtigfakt=='on' && $fakturadato) print "<input type=\"hidden\" name=\"levdato\" value=\"$fakturadato\">\n";
			print "<input type=\"hidden\" name=\"valutakurs\" value=\"$valutakurs\">\n";
			print "<input type=\"hidden\" name=\"status\" value=\"$status\">\n"; 
			print "<td align=\"center\" width=$width><input type=\"submit\" class=\"button gray medium\" id=\"submit\" style=\"width:75px;\" accesskey=\"g\" value=\"Gem\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>\n";
			if ($art!='OT') { # Fjerner knappen opslag hvis art er = OT (original tilbud) #20140716
				print "<td align=\"center\" width=$width><input type=\"submit\" class=\"button blue medium\" style=\"width:75px;\" accesskey=\"o\" value=\"Opslag\" name=\"submit\" ";
				if ( $art == "DK" ) print "disabled=\"disabled\" ";
				print "onclick=\"javascript:docChange = false;\"></td>\n";
			}
			if ($status==1&&$bogfor!=0 && $hurtigfakt!='on' && $leveres_ialt) {
				if ($art== 'DO') print "<td align=\"center\" width=$width><input type=\"submit\" class=\"button gray medium\" style=\"width:75px;\" accesskey=\"l\" value=\"Lev&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>\n";
				else print "<td align=\"center\" width=$width title=\"Klik her for at tage varer retur\"><input type=\"submit\"  class=\"button gray medium\" style=\"width:75px;\" accesskey=\"l\" value=\"Modtag\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>\n";
			}
			if (($status==2&&$bogfor!=0)||($status>0&&$hurtigfakt=='on')) {
				if ($art!='DK') {
					if ($mail_fakt) $tmp="onclick=\"return confirm('Faktura sendes pr. mail til $email')\"";
					else $tmp="";
					print "<td align=\"center\" width=$width><input type=\"submit\" class=\"button gray medium\" style=\"width:75px;\" accesskey=\"f\" value=\"Faktur&eacute;r\" name=\"submit\" $tmp></td>\n";
				} else {
					if ($mail_fakt) $tmp="onclick=\"return confirm('Kreditnota sendes pr. mail til $email')\"";
					else $tmp="";
					print "<td align=\"center\" width=$width><input type=\"submit\" class=\"button gray medium\" style=\"width:75px;\" accesskey=\"f\" value=\"Kredit&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>\n";
				}
			} elseif ($del_ordre == 'on') {
				$txt="Klik her for at opdele ordren i 2.<br>Den ene vil indeholde ikke leverede varer<br>Den anden vil indeholde leverede varer";
				print "<td align=\"center\" width=\"$width\" >
					<span onmouseover=\"return overlib('$txt',WIDTH=800);\" onmouseout=\"return nd();\">
					<input type=\"submit\" class=\"button gray medium\" accesskey=\"f\" value=\"Del ordre\" name=\"submit\" style=\"width:75px;\" onclick=\"javascript:docChange = false;\"></span></td>\n";
			}
			if ($linjeantal>0 && $konto_id && ($art=='DO' || $art=='OT')) { # skal også med ved 'original tilbud' (OT) #20140716
				if ($mail_fakt && $status < 1) $tmp="onclick=\"return confirm('Tilbud sendes pr mail til $email')\"";
				elseif ($mail_fakt && $hurtigfakt && $status < 3) $tmp="onclick=\"return confirm('Ordrebekr&aelig;ftelse sendes pr mail til $email')\"";
				elseif ($mail_fakt && $status < 2) $tmp="onclick=\"return confirm('Ordrebekr&aelig;ftelse sendes pr. mail til $email')\"";
				else $tmp="";
				($udskriv_til=='email')?$value='Send':$value='Udskriv'; 
				print "<td align=\"center\" width=$width><input type=\"submit\" class=\"button gray medium\" style=\"width:75px;\" value=\"$value\" name=\"submit\" $tmp title=\"$tekst2\" onclick=\"javascript:docChange = false;\"></td>\n";
			}
			if ($status<3 && $vis_saet && $konto_id) print "<td align=\"center\" width=$width><input type=\"button\" class=\"button gray medium\" style=\"width:75px;\" value=\"Sæt\" name=\"ret_saet\" title=\"Klik her for at oprette eller rette i varesæt\" onclick=\"jacascript:window.location.href='saetpris.php?id=$id'\"></td>\n";

			$tekst=findtekst(155,$sprog_id); $tekst2=findtekst(156,$sprog_id); 
			if (($status<1 || $linjeantal==0) && ($art!='OT') && $id) print "<td align=\"center\"><input type=\"submit\" class=\"button rosy medium\" style=\"width:75px;\" value=\"Slet\" name=\"submit\" onclick=\"return confirm('$tekst')\" title=\"$tekst2\"></td>\n";
			if ($art=='OT' && $sag_id) print "<td align=\"center\"><a class=\"button gray medium mozMedium\" style=\"\" title=\"klik her for at kopiér tilbud til ny sag\" href=\"../sager/sager.php?funktion=kopi_ordre&amp;sag_id=$sag_id&amp;konto_id=$konto_id&amp;ordre_id=$id&amp;returside=ordre\">Kopiér</a></td>\n";
			print "</tbody></table></td></tr>\n"; # <- Tabel 4.5.2
			print "</form>\n";
			print "</tbody></table></td></tr>\n"; # <- Tabel 4.5
			//print "<tr><td></td></tr>\n";
		} # end if ($status < 3)
		if ($konto_id) $r=db_fetch_array(db_select("select kreditmax from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		if ($kreditmax=$r['kreditmax']*1) {
		if ($valutakurs) $kreditmax=$kreditmax*100/$valutakurs;
			$q=db_select("select * from openpost where konto_id = '$konto_id' and udlignet='0'",__FILE__ . " linje " . __LINE__);
			$tilgode=0;
			while($r=db_fetch_array($q)) {
				if (!$r['valuta']) $r['valuta']='DKK';
				if (!$r['valutakurs']) $r['valutakurs']=100;
				if ($valuta=='DKK' && $r['valuta']!='DKK') $opp_amount=$r['amount']*$r['valutakurs']/100;
				elseif ($valuta!='DKK' && $r['valuta']=='DKK') {
					if ($r3=db_fetch_array(db_select("select kurs from grupper,valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = ".nr_cast('grupper.kodenr')." and valuta.valdate <= '$r[transdate]' order by valuta.valdate desc"))) {
						$opp_amount=$r['amount']*100/$r3['kurs'];
					} elseif ($valuta) print "<BODY onLoad=\"javascript:alert('Ingen valutakurs for faktura $r[faktnr]')\">\n";
					}
				elseif ($valuta!='DKK' && $r['valuta']!='DKK' && $r['valuta']!=$valuta) {
					$tmp==$r['amount']*$r['valuta']/100;
		 			$opp_amount=$tmp*100/$r['valutakurs'];
				}	else $opp_amount=$r['amount'];
				$tilgode=$tilgode+$opp_amount;
			}
			if ($kreditmax<$ialt+$tilgode) {
				$tmp=	dkdecimal(($ialt+$tilgode)-$kreditmax,2);
				print "<BODY onLoad=\"javascript:alert('Kreditloft overskrides med $valuta $tmp')\">\n";
			}
		}# end  if ($kreditmax....
		print "</tbody></table></td></tr>\n"; # <- Tabel 4
		print "</form>\n"; # 
	}# end else for (if ($status>=3))
	
	# ADD LINK TO GLS!! 
	if ($db_id=='390') {
		print "<tr><td align=\"center\"><br>";
		print "<form name=\"glslabel_form\" action=\"../includes/gls.php\" target=\"_blank\" method=\"POST\">".
		"\n<input type=\"hidden\" name=\"txtAction\" value=\"70120\">".			//this is a must!
		"\n<input type=\"hidden\" name=\"txtConsigneeNo\" value=\"".$kontonr."\">".		//this is a must!
		"\n<input type=\"hidden\" name=\"txtWeight\" value=\"1\">".
		"\n<input type=\"hidden\" name=\"txtCountryNum\" value=\"208\">".		//country codes can be fund in source on GLS site.
		"\n<input type=\"hidden\" name=\"txtReferenceNo\" value=\"".$ordrenr." \">".
		"\n<input type=\"hidden\" name=\"txtConsigneeEmail\" value=\"".$email." \">";


		if(!empty($lev_navn)){
			print "\n<input type=\"hidden\" name=\"txtName1\" value=\"".$lev_navn."\">";
			print "\n<input type=\"hidden\" name=\"txtZipCodeDisplay\" value=\"".$lev_postnr."\">";
			print "\n<input type=\"hidden\" name=\"txtCity\" value=\"".$lev_bynavn."\">";
			print "\n<input type=\"hidden\" name=\"txtStreet\" value=\"".$lev_addr1."\">";
			print "\n<input type=\"hidden\" name=\"txtName2\" value=\"".$lev_addr2."\">";
			print "\n<input type=\"hidden\" name=\"txtContact\" value=\"".$lev_kontakt."\">";
		} else {
			print "\n<input type=\"hidden\" name=\"txtName1\" value=\"".$firmanavn."\">";
			print "\n<input type=\"hidden\" name=\"txtZipCodeDisplay\" value=\"".$postnr."\">";
			print "\n<input type=\"hidden\" name=\"txtCity\" value=\"".$bynavn."\">";
			print "\n<input type=\"hidden\" name=\"txtStreet\" value=\"".$addr1."\">";
			print "\n<input type=\"hidden\" name=\"txtName2\" value=\"".$addr2."\">";
			print "\n<input type=\"hidden\" name=\"txtContact\" value=\"".$kontakt."\">";
		}
		print "\n<input type=\"submit\" value=\"Send til GLS\">".
		"\n</form>"; 
		print "</td></tr>";
	}
print "<!--Function ordreside slut-->";
}

function ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,$leveres_ialt,$tidl_lev_ialt,$levdiff,$masterprojekt,$linje_id,$kred_linje_id,$posnr,$varenr,$beskrivelse,$enhed,$pris,$rabat,$rabatart,$procent,$antal,$leveres,$vare_id,$momsfri,$rabatgruppe,$m_rabat,$varemomssats,$serienr,$samlevare,$folgevare,$projekt,$kdo,$kobs_ordre_pris,$ko_ant,$kostpris,$db,$dg,$dk_db,$dk_dg,$readonly,$omvbet,$saet,$saetnr) {
	print "<!--function ordrelinjer start-->";
	global $art;
	global $genfakt;
	global $hurtigfakt;
	global $id;
	global $incl_moms;
	global $momssats;
	global $valuta;
	global $valutakurs;
	global $vis_projekt;
	global $status;
	global $ny_pos;
	global $procentfakt;
	global $omkunde;
	global $difkto;
	global $rvnr;

	$dkantal=0;$tidl_lev=0;
	
#	($rvnr)?$disabled='disabled':$disabled=NULL;
	if (!$samlevare) $ny_pos++;
	#lse cho "$beskrivelse $pris<br>";
#	if (!$ny_pos) $ny_pos=1;
	if ($readonly) $readonly="readonly=\"readonly\"";
	if ($varenr) {
		if ($rabatart=='amount') $ialt=($pris-$rabat)*$antal;
		else $ialt=($pris-($pris/100*$rabat))*$antal;
		if ($procentfakt) {
			$ialt*=$procent/100;
		} else $procent=100; 
		$ialt=afrund($ialt,3); # 20150130 rettet til 3 decimaler
		$sum+=$ialt;
		$dkpris=dkdecimal($pris,2);
		$dkrabat=dkdecimal($rabat,3);
		$dkprocent=dkdecimal($procent,2);
		if ($momsfri!='on') {
			$moms+=afrund($ialt*$varemomssats/100,3); # 20150130 rettet til 3 decimaler
			if ($varemomssats!=$momssats) $blandet_moms=1;#tilfojet 20100923 grundet afrundingsfejl på ordre med rabat
			if($incl_moms)$dkpris=dkdecimal($pris+$pris*$varemomssats/100);
		} else $blandet_moms=1;#tilfojet 20100923 grundet afrundingsfejl på ordre med rabat
		if ($antal) {
			if ($art=='DK') $dkantal=dkdecimal($antal*-1);
			else $dkantal=dkdecimal($antal);
			if (substr($dkantal,-1)=='0') $dkantal=substr($dkantal,0,-1);
			if (substr($dkantal,-1)=='0') $dkantal=substr($dkantal,0,-2);
		}
	}	else {$antal=0; $dkantal=''; $dkpris=''; $dkrabat=''; $ialt='';}
	($art=='OT' || $saetnr || ($rvnr && $rabat))?$disabled='disabled':$disabled=NULL; // Her disables inputfield hvis art er OT. #20140716
	print "<input type=\"hidden\" name=\"linje_id[$x]\" value=\"$linje_id\">\n";
	print "<input type=\"hidden\" name=\"kred_linje_id[$x]\" value=\"$kred_linje_id\">\n";
	print "<input type=\"hidden\" name=\"vare_id[$x]\" value=\"$vare_id\">\n";
	print "<input type=\"hidden\" name=\"antal[$x]\" value=\"$antal\">\n";
	print "<input type=\"hidden\" name=\"serienr[$x]\" value=\"$serienr\">\n";
	print "<input type=\"hidden\" name=\"momsfri[$x]\" value=\"$momsfri\">\n";
	print "<input type=\"hidden\" name=\"varemomssats[$x]\" value=\"$varemomssats\">\n";
	print "<input type=\"hidden\" name=\"proc$x\" value=\"$procent\">\n";
	print "<input type=\"hidden\" name=\"saet[$x]\" value=\"$saet\">\n";
	print "<input type=\"hidden\" name=\"samlevare[$x]\" value=\"$samlevare\">\n";
#	if ($art=='OT' || $saetnr || ($rvnr && $rabat)) { // Når input fields er 'disabled' bliver de ikke opdateret, derfor tilføjes hidden fields
		print "<input type=\"hidden\" name=\"posn$x\" value=\"$ny_pos\">\n";
		print "<input type=\"hidden\" name=\"vare$x\" value=\"$varenr\">\n";
		print "<input type=\"hidden\" name=\"dkan$x\" value=\"$dkantal\">\n";
		print "<input type=\"hidden\" name=\"beskrivelse$x\" value=\"$beskrivelse\">\n";
		print "<input type=\"hidden\" name=\"pris$x\" value=\"$dkpris\">\n";
		print "<input type=\"hidden\" name=\"raba$x\" value=\"$dkrabat\">\n";
		print "<input type=\"hidden\" name=\"proc$x\" value=\"$dkprocent\">\n";
#	}
	if ($saet && $samlevare) {
	#cho "x $beskrivelse $pris<br>";
#		print "<tr><td><input type=\"text\" name=\"posn$x\" value=\"$ny_pos\">\n";
#		print "<input type=\"text\" name=\"vare$x\" value=\"$varenr\"\n";
#		print "<input type=\"text\" name=\"dkan$x\" value=\"$dkantal\"\n";
#		print "<input type=\"text\" name=\"beskrivelse$x\" value=\"$beskrivelse\"\n</td></tr>";
	}	else {
		print "<tr>\n";
		print "<td valign=\"top\"><input class=\"inputbox\" type=\"text\" $readonly style=\"text-align:right\" size=\"3\" name=\"posn$x\" value=\"$ny_pos\" $disabled></td>\n";
		print "<td valign=\"top\"><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=\"readonly\" size=\"12\" name=\"vare$x\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$varenr\" onchange=\"javascript:docChange = true;\" $disabled></td>\n";
		print "<td valign=\"top\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" $readonly size=\"4\" name=\"dkan$x\" value=\"$dkantal\" $disabled></td>\n";
		print "<td valign=\"top\"><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=\"readonly\" size=\"3\" value=\"$enhed\" onchange=\"javascript:docChange = true;\" $disabled></td>\n";
		$title=var2str($beskrivelse,$id,$posnr,$varenr,$dkantal,$enhed,$dkpris,$dkprocent,$serienr,$varemomssats,$dkrabat);
		//print "<td valign=\"top\" title=\"$title\"><input class=\"inputbox\" type=\"text\" $readonly size=\"58\" name=\"beskrivelse$x\" value=\"$beskrivelse\" onchange=\"javascript:docChange = true;\"></td>\n";
		if (($rvnr && $varenr=='R') || ($saetnr && $samlevare)) $dis=$disabled;
		elseif ($saetnr || ($rvnr && $rabat)) $dis=NULL;
		else $dis=$disabled;
		print "<td valign=\"top\" title=\"$title\"><textarea class=\"autosize inputbox ordreText comment\" $readonly rows=\"1\" cols=\"58\" name=\"beskrivelse$x\" onchange=\"javascript:docChange = true;\" $dis>$beskrivelse</textarea></td>\n";
	}
	if ($saet) {
		print "<td><input type=\"hidden\" name=\"pris$x\" value=\"$dkpris\"></td><td>
			<input class=\"inputbox\" type=\"hidden\" name=\"raba$x\" value=\"$dkrabat\"></td>
			<input type=\"hidden\" name=\"posn$x\" value=\"$ny_pos\">\n";
	} elseif ($saetnr) {
		print "<td><input type=\"hidden\" name=\"pris$x\" value=\"".dkdecimal($pris)."\"></td><td><input class=\"inputbox\" type=\"hidden\" name=\"raba$x\" value=\"0\"></td>"; 
	} elseif (!$rvnr) {
		print "<td valign=\"top\" title=\"db: $dk_db - dg: $dk_dg%\"><input class=\"inputbox\" type=\"text\" $readonly style=\"text-align:right\" size=\"10\" name=\"pris$x\" value=\"$dkpris\" onchange=\"javascript:docChange = true;\" onfocus=\"if(this.value == '0,00') {this.value=''}\" onblur=\"if(this.value == ''){this.value ='0,00'}\" $disabled></td>\n"; #2013.11.29 Fjerner 0,00 ved fokus, og tilføjer 0,00 hvis feltet er tomt
		$title=$dkantal."*".dkdecimal(($rabat/100)*$pris)."% = ".dkdecimal($antal*($rabat/100)*$pris);
		print "<td valign=\"top\" title=\"$title\"><input class=\"inputbox\" type=\"text\" $readonly style=\"text-align:right\" size=\"4\" name=\"raba$x\" value=\"$dkrabat\" onchange=\"javascript:docChange = true;\" onfocus=\"if(this.value == '0,00') {this.value=''}\" onblur=\"if(this.value == ''){this.value ='0,00'}\" $disabled></td>\n";
	} else print "<td></td><td></td>";
		
	if ($procentfakt) {
		print "<td valign=\"top\" title=\"$title\"><input class=\"inputbox\" type=\"text\" $readonly style=\"text-align:right\" size=\"4\" name=\"proc$x\" value=\"$dkprocent\" onchange=\"javascript:docChange = true;\" $disabled></td>\n";
		$db=$db-((100-$procent)/100*$pris);
	}
	$db=$db*$antal;
	if ($ialt!=0) $dg=$db*100/$ialt;
	else $dg=0;
	$dbsum=$dbsum+$db;
	$dk_db=dkdecimal($db);
	$dk_dg=dkdecimal($dg);
	if ($art=='DK') $ialt=$ialt*-1;
	if ($varenr) {
		if ($rvnr) {
			$disabled='disabled';
			if ($incl_moms && !$momsfri) $tmp=dkdecimal($antal*($pris+$pris*$varemomssats/100));
			else $tmp=dkdecimal($pris);
		} else {
			if ($incl_moms && !$momsfri) $tmp=dkdecimal($ialt+$ialt*$varemomssats/100);
			else $tmp=dkdecimal($ialt);
		}
	}
	else $tmp=NULL;
	if ($saet) {
		print "<td></td>"; 
	} elseif ($saetnr || $varenr==$rvnr) {
		#cho __line__." $linje_id $beskrivelse $pris<br>";
		#cho "select lev_varenr from ordrelinjer where samlevare='on' and saet='$saetnr' and ordre_id='$id'<br>";
		if ($saetnr) $r=db_fetch_array(db_select("select lev_varenr from ordrelinjer where samlevare='on' and saet='$saetnr' and ordre_id='$id'"));
		else $r=db_fetch_array(db_select("select lev_varenr from ordrelinjer where varenr='$rvnr' and ordre_id='$id'"));
		list($lev_vnr)=explode("|",$r['lev_varenr']);	
		print "<td valign=\"top\" align=\"right\" title=\"db: $dk_db - dg: $dk_dg%\"><input class=\"inputbox\" type=\"text\" readonly=\"readonly\" style=\"background: none repeat scroll 0 0 #e4e4ee; text-align:right\" size=\"10\" value=\"".dkdecimal($lev_vnr)."\" disabled></td>\n";
	} else {
		print "<td valign=\"top\" align=\"right\" title=\"db: $dk_db - dg: $dk_dg%\"><input class=\"inputbox\" type=\"text\" readonly=\"readonly\" style=\"background: none repeat scroll 0 0 #e4e4ee; text-align:right\" size=\"10\" value=\"$tmp\" $disabled></td>\n";
	}
	if ($vis_projekt && !$masterprojekt) {
		print "<td><select class=\"inputbox\" name=\"projekt[$x]\">\n";
		$list=array();
		$beskriv=array();
		$z=0;
		$q = db_select("select * from grupper where art = 'PRJ' and kodenr != '0' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			$z++;
			$list[$z]=$r['kodenr'];
			$beskriv[$z]=$r['beskrivelse'];
		}
		for ($a=0; $a<=$z; $a++) {
			if ($projekt!=$list[$a]) print "<option  value=\"$list[$a]\" title=\"$beskriv[$a]\">$list[$a]</option>\n";
			else print "<option value=\"$list[$a]\" title=\"$beskriv[$a]\" selected=\"selected\">$list[$a]</option>\n";
		}
		print "</select></td>";
	}
	if ($genfakt) print "<td title=\"Afm&aelig;rk dette felt hvis ordrelinjen ikke skal med ved genfakturering / kopiering.\"><input class=\"inputbox\" name=\"kdo[$x]\" type=\"checkbox\" $kdo></td>\n";

#		 	}
#			else print "<td></td>";
	if ($status>=1&&$hurtigfakt!='on') {
		if ($vare_id || $varenr){
			$batch="?";
#					print "<td title=\"kostpris\">Projekt</span></td>\n";
			$tidl_lev=0;
			$query = db_select("select gruppe,beholdning from varer where id = $vare_id",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$beholdning=$row['beholdning'];
			$query = db_select("select box8,box9 from grupper where art='VG' and kodenr='$row[gruppe]'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			($row['box6']=='on')?$omvare=1:$omvare=0; # vare som er omfattet af omvendt betalingspligt 
			($row['box8']=='on')?$lagervare=1:$lagervare=0;
			($row['box9']=='on')?$batchvare=1:$batchvare=0;
			if ($antal) {
				$query = db_select("select * from batch_salg where linje_id = '$linje_id' and ordre_id=$id and vare_id = $vare_id",__FILE__ . " linje " . __LINE__);
				while($row = db_fetch_array($query)) {
					$y++;
					$batch='V';
					$tidl_lev=$tidl_lev+$row['antal'];
				}
				if ($batchvare) {
					$z=0;
					$query = db_select("select * from reservation where vare_id = $vare_id",__FILE__ . " linje " . __LINE__);
					while ($row = db_fetch_array($query))	{
					 if (($row['linje_id']==$linje_id)||($row['batch_salg_id']==$linje_id*-1)) {
							$z=$z+$row['antal'];
							$batch="V";
						}
						elseif ($row['batch_kob_id']<0) $reserveret=$reserveret+$row['antal'];
						elseif ($row['batch_salg_id']==0) $paavej=$paavej+$row['antal'];
					}
					if($z+$tidl_lev<$antal) $batch="?";
				}
				else $batch="";
				if (($tidl_lev<$antal)||($batch=="?")) $status=1;
			}
/*
			if ($antal<0) {
				$tidl_lev=0;
				$query = db_select("select * from batch_kob where linje_id = '$linje_id' and ordre_id=$id",__FILE__ . " linje " . __LINE__); #20071004
				while($row = db_fetch_array($query)) $tidl_lev=$tidl_lev-$row['antal'];
				if ($antal>$tidl_lev+$leveres) $leveres=$antal-$tidl_lev;
#							elseif ($antal>$tidl_lev+$leveres) $leveres=$antal+$tidl_lev;
				$query = db_select("select * from reservation where linje_id = '$linje_id'",__FILE__ . " linje " . __LINE__);
				if (($row = db_fetch_array($query))&&($beholdning>=0)) {
					if ($antal+$tidl_lev!=$row['antal']) db_modify ("update reservation set antal=$antal*-1 where linje_id=$linje_id and batch_salg_id=0",__FILE__ . " linje " . __LINE__);
				}
				elseif ($antal-$tidl_lev!=0) db_modify("insert into reservation (linje_id,vare_id,batch_salg_id,antal) values ($linje_id,$vare_id,0,$antal*-1)",__FILE__ . " linje " . __LINE__);
			}
			elseif ($leveres+$tidl_lev>$antal) $leveres=$antal-$tidl_lev;
*/
			if ($art=='DK') {
				$dklev=dkdecimal($leveres*-1);
				$dk_tidl_lev=dkdecimal($tidl_lev*-1);
				$lever_modtag="modtag";
			} else {
				$dklev=dkdecimal($leveres);
				$dk_tidl_lev=dkdecimal($tidl_lev);
				$lever_modtag="lever";
			}

			if (substr($dklev,-1)=='0') $dklev=substr($dklev,0,-1);
			if (substr($dklev,-1)=='0') $dklev=substr($dklev,0,-2);
			if (substr($dk_tidl_lev,-1)=='0') $dk_tidl_lev=substr($dk_tidl_lev,0,-1);
			if (substr($dk_tidl_lev,-1)=='0') $dk_tidl_lev=substr($dk_tidl_lev,0,-2);
			print "<input type=\"hidden\" name=tidl_lev[$x] value=\"$dk_tidl_lev\">\n";
			$temp=$beholdning-$reserveret;
			$status=2;
			$beholdning=$beholdning*1;
			$beholdning=dkdecimal($beholdning);
			if (substr($beholdning,-1)=='0') $beholdning=substr($beholdning,0,-1);
			if (substr($beholdning,-1)=='0') $beholdning=substr($beholdning,0,-2);
			if (!$lagervare) $beholdning="ikke lagerført";
			$tmp=afrund(abs($antal)-abs($tidl_lev),2); #20131004
			if ($samlevare && $saet) {
				echo "";
			} else {
				if ($tmp) {
					if (abs($antal)!=abs($tidl_lev)) {
						print "<td title=\"Lagerbeholdning: $beholdning. Mangler fortsat at ".$lever_modtag."e resten.\"><input class=\"inputbox\" $readonly type=\"text\" style=\"background: none repeat scroll 0 0 #ffa; text-align:right\" size=\"4\" name=\"leve$x\" value=\"$dklev\" onchange=\"javascript:docChange = true;\"></td>\n";
					} else {
						print "<td title=\"Lagerbeholdning: $beholdning. Intet ".$lever_modtag."et endnu.\"><input class=\"inputbox\" $readonly type=\"text\" style=\"text-align:right\" size=\"4\" name=\"leve$x\" value=\"$dklev\" onchange=\"javascript:docChange = true;\"></td>\n";
					}
					print "<td title=\"Tidligere ".$lever_modtag."et $dk_tidl_lev p&aring; denne ordre.\">($dk_tidl_lev)</td>\n";
					if ($batchvare && $antal>0) print "<td align=\"center\" onClick=\"batch($linje_id)\" title=\"V&aelig;lg fra k&oslash;bsordre\"><img alt=\"Serienummer\" src=\"../ikoner/serienr.png\"></td>\n";
					elseif ($serienr) print "<td align=\"center\" onClick=\"serienummer($linje_id)\" title=\"V&aelig;lg serienr\"><img alt=\"Serienummer\" src=\"../ikoner/serienr.png\"></td>\n";
					$levdiff=1;
				} else {
					if ($antal==$tidl_lev) $dklev=0;
					print "<td title=\"Lagerbeholdning: $beholdning. Alt ".$lever_modtag."et.\"><input class=\"inputbox\" type=\"text\" readonly=\"readonly\" style=\"background: none repeat scroll 0 0 #e4e4ee; text-align:right\" size=\"4\" name=\"leve$x\" value=\"$dklev\" onchange=\"javascript:docChange = true;\"></td>\n";
					print "<td title=\"Tidligere ".$lever_modtag."et $dk_tidl_lev p&aring; denne ordre.\">($dk_tidl_lev)</td>\n";
				}
				if ($linje_id && $leveret!=$tidl_lev) db_modify("update ordrelinjer set leveret=$tidl_lev where id=$linje_id",__FILE__ . " linje " . __LINE__);
			}
		}
	} elseif ($serienr) print "<td align=\"center\" onClick=\"serienummer($linje_id)\" title=\"V&aelig;lg serienr\"><img alt=\"Serienummer\" src=\"../ikoner/serienr.png\"></td>\n";
#			if ($samlevare=='on') print "<td align=\"center\" onClick=\"stykliste($vare_id)\" title=\"Vis stykliste\"><img alt=\"Stykliste\" src=\"../ikoner/stykliste.png\"></td>\n";
	if (!$rabat && $m_rabat && !$rabatgruppe) {
		print "</tr><tr>\n";
		print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"text-align:right\" size=\"3\" value=$x></td>\n";
		print "<td><input class=\"inputbox\" readonly=\"readonly\" size=\"12\" value=\"\"></td>\n";
		print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"text-align:right\" size=\"4\" value=$dkantal></td>\n";
		print "<td><input class=\"inputbox\" readonly=\"readonly\" size=\"3\" value=\"$enhed\"></td>\n";
		$rabatpct=afrund($m_rabat*100/$pris,2);
		($rabatart=='amount')?$rabattxt=findtekst(466,$sprog_id):$rabattxt=findtekst(467,$sprog_id);
		$rabattxt=str_replace('$rabatpct',$rabatpct,$rabattxt);
		$title=var2str($rabattxt,$id,$posnr,$varenr,$dkantal,$enhed,$dkpris,$dkprocent,$serienr[$x],$varemomssats,$dkrabat);
		print "<td title=\"$title\"><input class=\"inputbox\" readonly=\"readonly\" size=\"58\" value=\"$rabattxt\"></td>\n";
		if ($momsfri!='on') {
			$moms+=afrund($m_rabat*$antal*$varemomssats/100,2);
		  if ($varemomssats!=$momssats) $blandet_moms=1;#tilfojet 20100923 grundet afrundingsfejl på ordre med rabat
		} 
		if ($incl_moms) $m_rabat+=$m_rabat*$varemomssats/100;
		print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"text-align:right\" size=\"10\" value=\"".dkdecimal($m_rabat)."\"></td>\n";
		print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"text-align:right\" size=\"4\" value=\"\" onchange=\"javascript:docChange = true;\"></td>\n";
		print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"text-align:right\" size=\"10\" value=\"".dkdecimal($m_rabat*$antal)."\"></td>\n";
		$sum+=afrund($m_rabat*$antal,2);
	}
	if ($omkunde) print "<td valign=\"top\"><input class=\"inputbox\" type=\"checkbox\" style=\"background: none repeat scroll 0 0 #e4e4ee\" name=\"omvbet[$x]\" onchange=\"javascript:docChange = true;\" $omvbet></td>\n";

	print "</tr>\n";
	if ($readonly) {
		print "<input type=\"hidden\" name=\"posn$x\" value=\"$ny_pos\">\n";
		print "<input type=\"hidden\" name=\"vare$x\" value=\"$varenr\">\n";
		print "<input type=\"hidden\" name=\"dkan$x\" value=\"$dkantal\">\n";
		print "<input type=\"hidden\" name=\"beskrivelse$x\" value=\"$beskrivelse\">\n";
		print "<input type=\"hidden\" name=\"pris$x\" value=\"$dkpris\">\n";
		print "<input type=\"hidden\" name=\"raba$x\" value=\"$dkrabat\">\n";
	}
		$antal_ialt=$antal_ialt+$antal; #10.10.2007
	$leveres_ialt=$leveres_ialt+abs($leveres); #abs tilfoejet 2009.01.26 grundet manglende lev_mulighed med ens antal positive og negative leveringer i ordre 98 i saldi_104
	$tidl_lev_ialt=$tidl_lev_ialt+$tidl_lev; #10.10.2007
#cho "Sum $sum<br>"; 
	return($sum.chr(9).$dbsum.chr(9).$blandet_moms.chr(9).$moms.chr(9).$antal_ialt.chr(9).$leveres_ialt.chr(9).$tidl_lev_ialt.chr(9).$tidl_lev.chr(9).$levdiff);
	print "<!--function ordrelinjer slut-->";
} # endfunc ordrelinjer;

function find_vare_id ($varenr) {
	$query = db_select("select id from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {return $row[id];}
}

######################################################################################################################################
function find_konto_id ($kontonr) {
	$query = db_select("select id from adresser where kontonr = '$kontonr'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {return $row[id];}
}
######################################################################################################################################
function find_betalingsdage ($konto_idnr) {
	$query = db_select("select betalingsdage from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {return $row[betalingsdage];}
}
###########################################################################################################################
/*
function batch ($linje_id) {
	$leveres=0;
	$query = db_select("select * from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$antal=$row['antal'];
		$leveres=$row['leveres'];
		$posnr=$row['posnr'];
		$vare_id=$row['vare_id'];
		$varenr=$row['varenr'];
		$serienr=$row['serienr'];
		$query = db_select("select status,art,konto_id,ref from ordrer where id = '$row[ordre_id]'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$konto_id=$row['konto_id'];
		$status=$row['status'];
		$art=$row['art'];

		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$row[ref]'",__FILE__ . " linje " . __LINE__))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager=$row['kodenr']*1;}
		}
	}

	$query = db_select("select * from batch_salg where linje_id = $linje_id",__FILE__ . " linje " . __LINE__);
	while($row = db_fetch_array($query)) $leveres=$antal-$row[antal];

	if (($antal>=0)&&($art!="DK")&&($vare_id)){
		$x=0;
		$rest=array();
		$lev_rest=$leveres;

		if (isset($lager)) $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 and lager = $lager order by kobsdate",__FILE__ . " linje " . __LINE__);
		else $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 order by kobsdate",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$x++;
			$batch_kob_id=$row['id'];
			$kobsdate=$row['kobsdate'];
			$rest=$row['rest'];
			$reserveret=0;
#			$pris=$row[pris];
			$q2 = db_select("select ordrenr from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
			$r2 = db_fetch_array($q2);
			$ordrenr=$r2[ordrenr];
			if ($rest>=$lev_rest) {
				$valg=$lev_rest;
				$lev_rest=0;
			}
			else {
				$valg=$rest;
				$lev_rest=$lev_rest-$rest;
			}
		}
		$batch_antal=$x;
	}
	if ($lev_rest==0) {
		 db_modify("delete from reservation where linje_id=$linje_id",__FILE__ . " linje " . __LINE__);
		 $temp=$linje_id*-1;
		 db_modify("delete from reservation where batch_salg_id=$temp",__FILE__ . " linje " . __LINE__);
		 for ($x=1; $x<=$batch_antal; $x++){
			 $lager=$lager*1;
			 if (($valg>0)&&(!$res_linje_id)) {db_modify("insert into reservation (linje_id,vare_id,batch_kob_id,antal,lager) values ($linje_id,$vare_id,$batch_kob_id,$valg,$lager)",__FILE__ . " linje " . __LINE__);}
			 elseif (($valg>0)&&($res_linje_id)) {db_modify("insert into reservation (linje_id,vare_id,batch_salg_id,antal,lager) values ($res_linje_id,$vare_id,$temp,$valg,$lager)",__FILE__ . " linje " . __LINE__);}
		 }
	}
}
*/
##############################################################################
function indsaet_linjer($ordre_id,$linje_id,$posnr) {
	$posnr = str_replace('+',':',$posnr); #jeg ved ikke hvorfor,men den vil ikke splitte med "+"
	list ($posnr,$antal) = explode (':',$posnr);
	if (is_numeric($posnr) && is_numeric($antal)) {
		db_modify("update ordrelinjer set posnr='$posnr' where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		for ($x=1; $x<=$antal; $x++) {
			db_modify("insert into ordrelinjer (posnr,ordre_id) values ('$posnr','$ordre_id')",__FILE__ . " linje " . __LINE__);
		}
	}
}
##############################################################################
function find_nextfakt($fakturadate,$nextfakt)
{
// Denne funktion finder diff mellem fakturadate & nextfakt, tillaegger diff til nextfakt og returnerer denne vaerdi. Hvis baade
// fakturadate og netffaxt er sidste dag i de respektive maaneder vaelges ogsaa sidste dag i maaned i returvaerdien.

list($faktaar,$faktmd,$faktdag) = explode("-",$fakturadate);
list($nextfaktaar,$nextfaktmd,$nextfaktdag) = explode("-",$nextfakt);

if (!checkdate($faktmd,$faktdag,$faktaar)) {
	echo "Fakturadato er ikke en gyldig dato<br>";
	exit;
}
if (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) {
	echo "Genfaktureringsadato er ikke en gyldig dato<br>";
	exit;
}
$faktultimo=0;
$nextfaktultimo=0;
$tmp=$faktdag+1;
if (!checkdate($faktmd,$tmp,$faktaar)) $faktultimo=1; # hvis dagen efter fakturadag ikke findes fakureres ultimo"
$tmp=$nextfaktdag+1;
if (!checkdate($nextfaktmd,$tmp,$nextfaktaar)) $nextfaktultimo=1;
$faktmd_len=31;
while (!checkdate($faktmd,$faktmd_len,$faktaar)) $faktmd_len--; #finder antal dage i fakturamaaneden
$dagantal=$nextfaktdag-$faktdag;
$md_antal=$nextfaktmd-$faktmd;
$aar_antal=$nextfaktaar-$faktaar;
if ($dagantal<0) {
	$dagantal=$dagantal+$faktmd_len;
	$md_antal--;
}
while ($md_antal<0) {
	$aar_antal--;
	$md_antal=$md_antal+12;
}
$nextfaktaar=$nextfaktaar+$aar_antal;
$nextfaktmd=$nextfaktmd+$md_antal;
if ($nextfaktmd > 12) {
	$nextfaktaar++;
	$nextfaktmd=$nextfaktmd-12;
}
if ($faktultimo && $nextfaktultimo) {# fast faktura sidste dag i md.
	$nextfaktdag=31;
	if ($dagantal>27) $nextfaktmd++;
	while (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) $nextfaktdag--;
} else {
	$nextfaktdag=$nextfaktdag+$dagantal;
if ($nextfaktdag>$faktmd_len) {
		while (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) {
			$nextfaktmd++;
			if ($nextfaktmd > 12) {
				$nextfaktaar++;
				$nextfaktmd=1;
			}
			$nextfaktdag=$nextfaktdag-$faktmd_len;
		}
	} else while (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) $nextfaktdag--;
}
$nextfakt=$nextfaktaar."-".$nextfaktmd."-".$nextfaktdag;
return($nextfakt);

}# endfunc find_nextfakt							

if ($fokus) {
	?>
	<script language="javascript">
	document.ordre.<?php echo $fokus?>.focus();
	</script>
	<?php
}
?>
</tbody></table></body></html>
<!--  -->
