<?php
// ----------debitor/ordre.php---------lap 3.0.1------2010-05-20-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.½
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$antal=array();$beskrivelse=array();$enhed=array();$pris=array();$varenr=array();
$fakturadate=NULL;$fakturadato=NULL;$firmanavn=NULL;$genfakt=NULL;$gl_id=NULL;$levdate=NULL;$modtaget=NULL;$nextfakt=NULL;$notes=NULL;
$reserveret=NULL;$sletslut=0;$sletstart=0;

# Javascript skalflyttes i selvstaendig fil.
?>
<script type="text/javascript">
	<!--
	var linje_id=0;
	var vare_id=0;
	var antal=0;
	function serienummer(linje_id,antal){
		window.open("serienummer.php?linje_id="+ linje_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
	}
	function batch(linje_id,antal){
		window.open("batch.php?linje_id="+ linje_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
	}
	function stykliste(vare_id){
		window.open("../lager/fuld_stykliste.php?id="+ vare_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
	}
	//-->
</script>
<script LANGUAGE="JavaScript" SRC="../javascript/overlib.js"></script>
<?php
$modulnr=5;
$title="Kundeordre";
$css="../css/standard.css";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/var2str.php");
include("../includes/ordrefunc.php");

print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";
$tidspkt=date("U");
	
$returside=if_isset($_GET['returside']);
if ($popup) $returside="../includes/luk.php";

if ($tjek=if_isset($_GET['tjek'])){
	$query = db_select("select tidspkt,hvem from ordrer where status < 3 and id = $tjek and hvem != '$brugernavn'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query))	{
		if ($tidspkt-($row['tidspkt'])<3600){
			print "<BODY onLoad=\"javascript:alert('Ordren er i brug af $row[hvem]')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
		}
		else {
		db_modify("update ordrer set hvem = '$brugernavn',tidspkt='$tidspkt' where id = '$tjek'",__FILE__ . " linje " . __LINE__);}
	}
}
	
$q = db_SELECT("select box4,box9 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__);
$r=db_fetch_array($q); 

// Get VAT settings from settings table
$vatPrivateCustomers = get_settings_value("vatPrivateCustomers", "ordre", "");
$vatBusinessCustomers = get_settings_value("vatBusinessCustomers", "ordre", "");

// Set default VAT behavior based on settings
$incl_moms = $vatPrivateCustomers; // Default to private customer setting
$hurtigfakt=$r['box4'];
$negativt_lager=$r['box9'];

$id=if_isset($_GET['id']);
$sort=if_isset($_GET['sort']);
$fokus=if_isset($_GET['fokus']);
$submit=if_isset($_GET['funktion']);
$vis_kost=if_isset($_GET['vis_kost']);
$bogfor=1;

if (isset($_GET['vis_lev_addr']) && $id) {
	if ($_GET['vis_lev_addr']) db_modify ("update ordrer set vis_lev_addr='on' where id='$id'");
	else db_modify ("update ordrer set vis_lev_addr='' where id='$id'");
		
}

if (($kontakt=if_isset($_GET['kontakt']))&&($id)) db_modify("update ordrer set kontakt='$kontakt' where id=$id",__FILE__ . " linje " . __LINE__);

if (isset($_GET['konto_id']) && is_numeric($_GET['konto_id'])) { # <- 2008.05.11
	$konto_id=$_GET['konto_id'];
	$query = db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$kontonr=$row['kontonr'];
		$firmanavn=addslashes($row['firmanavn']);
		$addr1=addslashes($row['addr1']);
		$addr2=addslashes($row['addr2']);
		$postnr=addslashes($row['postnr']);
		$bynavn=addslashes($row['bynavn']);
		$land=addslashes($row['land']);
		$betalingsdage=$row['betalingsdage'];
		$betalingsbet=$row['betalingsbet'];
		$cvrnr=addslashes($row['cvrnr']);
		$ean=addslashes($row['ean']);
		$institution=addslashes($row['institution']);
		$email=addslashes($row['email']);
		$mail_fakt=$row['mailfakt'];
		if ($row['pbs_nr']>0) {
			$pbs_nr=$row['pbs_nr'];
			$pbs='bs';
		}
		$kontakt=addslashes($row['kontakt']);
		$notes=addslashes($row['notes']);
		$gruppe=addslashes($row['gruppe']);
		$kontoansvarlig=addslashes($row['kontoansvarlig']);
		
		// Check customer type from kontotype field
		$qtxt = "SELECT kontotype FROM adresser WHERE id = '$konto_id'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$kontotype = if_isset($r, 0, 'kontotype');

		if ($kontotype == 'erhverv') {
			$incl_moms = $vatBusinessCustomers; // Use business customer VAT setting for business customers
		} else {
			$incl_moms = $vatPrivateCustomers; // Use private customer VAT setting for private customers
		}
	}
	if ($kontoansvarlig){
		$query = db_select("select navn from ansatte where id='$kontoansvarlig'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$ref=$row['navn'];
	} else {
		$row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
		if ($row[ansat_id]) {
			$row = db_fetch_array(db_select("select navn from ansatte where id = $row[ansat_id]",__FILE__ . " linje " . __LINE__));
			if ($row[navn]) {$ref=$row['navn'];}
		}
	}
	if ($gruppe){
		$r = db_fetch_array(db_select("select box1,box3,box4,box6 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
		$tmp= substr($r['box1'],1,1)*1;
		$rabatsats=$r['box6']*1;
		$formularsprog=$r['box4'];
		$valuta=$r['box3'];
		$r = db_fetch_array(db_select("select box2 from grupper where art='SM' and kodenr='$tmp'",__FILE__ . " linje " . __LINE__));
		$momssats=$r['box2']*1;
	} else {
			print "<BODY onLoad=\"javascript:alert('Debitoren er ikke tilknyttet en debitorgruppe')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?id=$konto_id&returside=../debitor/ordre.php&ordre_id=$id&fokus=$fokus?id=$id\">";
			exit;
	
	}
}
if ((!$id)&&($firmanavn)) {
	$ordrenr = get_next_order_number('DO');
	$ordredate=date("Y-m-d");
	db_modify("insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,betalingsdage,betalingsbet,cvrnr,ean,institution,email,mail_fakt,notes,art,ordredate,momssats,hvem,tidspkt,ref,valuta,sprog,kontakt,pbs,status,restordre) values ($ordrenr,'$konto_id','$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$email','$mail_fakt','$notes','DO','$ordredate','$momssats','$brugernavn','$tidspkt','$ref','$valuta','$formularsprog','$kontakt','$pbs','0','0')",__FILE__ . " linje " . __LINE__);
	$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {$id=$row[id];}
}
elseif($firmanavn) {
	$query = db_select("select tidspkt,firmanavn from ordrer where id=$id and hvem='$brugernavn'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		if (!$row['firmanavn']) { # <- 2009.05.13 Eller overskrives v. kontaktopslag.
			db_modify("update ordrer set kontonr='$kontonr',kundeordnr='$kundeordnr',firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',land='$land',lev_navn='$lev_navn',lev_addr1='$lev_addr1',	lev_addr2='$lev_addr2',lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_kontakt='$lev_kontakt',vis_lev_addr='$vis_lev_addr',felt_1='$felt_1',felt_2='$felt_2',felt_3='$felt_3',felt_4='$felt_4',felt_5='$felt_5',betalingsdage='$betalingsdage',betalingsbet='$betalingsbet',cvrnr='$cvrnr',ean='$ean',institution='$institution',email='$email',mail_fakt='$mail_fakt',udskriv_til='$udskriv_til',notes='$notes',hvem = '$brugernavn',tidspkt='$tidspkt',pbs='$pbs',restordre='$restordre' where id=$id",__FILE__ . " linje " . __LINE__);
	 	}
	} else {			
		$query = db_select("select hvem from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Ordren er overtaget af $row[hvem]')\">";}
		else {print "<BODY onLoad=\"javascript:alert('Du er blevet smidt af')\">";}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
	}	
}

if (isset($_GET['vare_id'])) {
	$vare_id[0]=$_GET['vare_id']*1;
	$query = db_select("select grupper.box6 as box6,ordrer.valuta as valuta,ordrer.ordredate as ordredate,ordrer.status as status from ordrer,adresser,grupper where ordrer.id='$id' and adresser.id=ordrer.konto_id and grupper.art='DG' and ".nr_cast("grupper.kodenr")."=adresser.gruppe",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	if ($row['status']>2) {
		print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
		exit;
	} else {
		if ($r=db_fetch_array(db_select("select varenr from varer where id = '$vare_id[0]'",__FILE__ . " linje " . __LINE__))) {
			$varenr[0]=$r['varenr'];
			$svar=opret_ordrelinje("$id","$varenr[0]","1","","","","$art","$momsfri[0]","$posnr_ny[$x]","0","$incl_moms");
		}
	}
}
if (isset($_POST['submit'])) {
	$fokus=if_isset($_POST['fokus']);
	$submit = $_POST['submit'];
	if (strstr($submit,"Faktur")) $submit="Fakturer";
	if (strstr($submit,"Del ordre")) $submit="del_ordre";
#	if ($submit=='Kredit&eacute;r') $sumbit='Fakturer';
	$id = $_POST['id'];
	$ordrenr = $_POST['ordrenr'];
	$kred_ord_id = $_POST['kred_ord_id'];
	$art = $_POST['art'];
	$konto_id = if_isset($_POST['konto_id'])*1;
	$kontonr = $_POST['kontonr']*1;
	$firmanavn = addslashes(trim($_POST['firmanavn']));
	$addr1 = addslashes(trim($_POST['addr1']));
	$addr2 = addslashes(trim($_POST['addr2']));
	$postnr = addslashes(trim($_POST['postnr']));
	$bynavn = addslashes(trim($_POST['bynavn']));
	$land = addslashes(trim($_POST['land']));
	$kontakt = addslashes(trim($_POST['kontakt']));
	$kundeordnr =	addslashes(trim($_POST['kundeordnr']));
	$lev_navn = addslashes(trim($_POST['lev_navn']));
	$lev_addr1 = addslashes(trim($_POST['lev_addr1']));
	$lev_addr2 = addslashes(trim($_POST['lev_addr2']));
	$lev_postnr = trim($_POST['lev_postnr']);
	$lev_bynavn = addslashes(trim($_POST['lev_bynavn']));
	$lev_kontakt = addslashes(trim($_POST['lev_kontakt']));
	$vis_lev_addr=$_POST['vis_lev_addr'];
	$felt_1 = addslashes(trim($_POST['felt_1']));
	$felt_2 = addslashes(trim($_POST['felt_2']));
	$felt_3 = addslashes(trim($_POST['felt_3']));
	$felt_4 = addslashes(trim($_POST['felt_4']));
	$felt_5 = addslashes(trim($_POST['felt_5']));
	$ordredate = usdate(if_isset($_POST['ordredato']));
	$levdato = trim($_POST['levdato']);
	$genfakt = trim(if_isset($_POST['genfakt']));
	$fakturadato = trim(if_isset($_POST['fakturadato']));
	$cvrnr = addslashes(trim($_POST['cvrnr']));
	$ean = addslashes(trim($_POST['ean']));
	$institution = addslashes(trim($_POST['institution']));
	$email = addslashes(trim($_POST['email']));
	$udskriv_til=$_POST['udskriv_til'];
	if (strpos($email,"@") && strpos($email,".") && strlen($email)>5 && $udskriv_til=='email') $mail_fakt = 'on';
	elseif($udskriv_til=='email')	{
		print "<BODY onLoad=\"javascript:alert('e-mail ikke gyldig\\nFaktura kan ikke sendes som e-mail')\">";
		$udskriv_til="PDF";	
	}
	if ($udskriv_til=='oioxml' && strlen($ean)!=13) {
		print "<BODY onLoad=\"javascript:alert('EAN-nr ikke gyldigt.\\nDer kan ikke udskrives til oioxml')\">";
		$udskriv_til="PDF";	
	}
	if ($udskriv_til=='PBS_FI') $pbs="FI";
	if ($udskriv_til=='PBS_BS') $pbs="BS";
	if ($udskriv_til=='oioxml') $oioxlm="on";
	$betalingsbet = $_POST['betalingsbet'];
	$betalingsdage = $_POST['betalingsdage']*1;
	$valuta = $_POST['valuta'];
	$ny_valuta = $_POST['ny_valuta'];
	$projekt = if_isset($_POST['projekt']);
	$formularsprog = if_isset($_POST['sprog']);
	$lev_adr = trim(if_isset($_POST['lev_adr']));
	$sum=if_isset($_POST['sum']);
	$linjeantal = $_POST['linjeantal'];
	$linje_id = $_POST['linje_id'];
	$kred_linje_id = if_isset($_POST['kred_linje_id']);
	$posnr = if_isset($_POST['posnr']);
	$status = $_POST['status'];
	$godkend = if_isset($_POST['godkend']);
	$restordre = if_isset($_POST['restordre']);
	($restordre)? $restordre="1":$restordre="0";
	$omdan_t_fakt = if_isset($_POST['omdan_t_fakt']);
	$kreditnota = if_isset($_POST['kreditnota']);
	$ref = trim($_POST['ref']);
	$fakturanr = trim(if_isset($_POST['fakturanr']));
	$momssats = trim($_POST['momssats']);
	$enhed = if_isset($_POST['enhed']);
	$vare_id = $_POST['vare_id'];
	$antal = if_isset($_POST['antal']);
	$serienr = if_isset($_POST['serienr']);
	$momsfri = if_isset($_POST['momsfri']);
	$tidl_lev = if_isset($_POST['tidl_lev']);
	
	if (strstr($submit,"Kred") && $status < 3) $submit="Fakturer";
	if (strstr($submit,'Modtag')) $submit="Lever";
	
	$r = db_fetch_array(db_select("select grupper.box6 as box6 from adresser,grupper where adresser.kontonr='$kontonr' and adresser.art='D' and grupper.art='DG' and ".nr_cast("grupper.kodenr")."=adresser.gruppe",__FILE__ . " linje " . __LINE__));
	$rabatsats=$r['box6']*1;
	if (strstr($submit,'Slet')) {
		db_modify("delete from ordrelinjer where ordre_id=$id",__FILE__ . " linje " . __LINE__);
		db_modify("delete from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
	}
	if ($ny_valuta!=$valuta && $status<3) {
		if ($ny_valuta!='DKK') {
			if ($r= db_fetch_array(db_select("select valuta.kurs from valuta,grupper where grupper.art='VK' and grupper.box1='$ny_valuta' and valuta.gruppe=".nr_cast("grupper.kodenr")." and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
				$valutakurs=$r['kurs']*1;
				if ($status<3) db_modify("update ordrer set valuta='$ny_valuta',valutakurs='$valutakurs' where id='$id'",__FILE__ . " linje " . __LINE__);
			} else {
				$tmp = dkdato($ordredate);
				print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
			}
		} else {
			$valutakurs = 100;
			db_modify("update ordrer set valuta='$ny_valuta',valutakurs='$valutakurs' where id='$id'",__FILE__ . " linje " . __LINE__);
		}
		$valuta=$ny_valuta;
	}



#	if ($submit=='OK') {
#		if ($genfakt && $genfakt!='-') db_modify("update ordrer set nextfakt='".usdate($genfakt)."' where id='$id'",__FILE__ . " linje " . __LINE__);
#		else db_modify("update ordrer set nextfakt=NULL where id='$id'",__FILE__ . " linje " . __LINE__);
#	}
	transaktion("begin");
	for ($x=0; $x<=$linjeantal;$x++) {
		$y="posn".$x;
		$posnr_ny[$x]=trim(if_isset($_POST[$y]))*1;
		$y="vare".$x;
		$varenr[$x]=addslashes(trim(if_isset($_POST[$y])));
		$y="dkan".$x;
		$dkantal[$x]=trim(if_isset($_POST[$y]));
		if ($dkantal[$x] || $dkantal[$x]=='0'){
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
		$beskrivelse[$x]=addslashes(trim(if_isset($_POST[$y])));
		$y="pris".$x;
		if ($x!=0||isset($_POST[$y])) {
			$pris[$x]=usdecimal($_POST[$y]);
			if ($incl_moms && !$momsfri[$x]) { 
				$pris[$x]=afrund(($pris[$x]/(100+$momssats)*100),3);
			}
		}
		$y="raba".$x;
		$rabat[$x]=usdecimal(if_isset($_POST[$y]));
		if (($x>0)&&(!$rabat[$x]))$rabat=0;
		$y="ialt".$x;
		$ialt[$x]=if_isset($_POST[$y]);
		if (($godkend == "on")&&($status==0)) {
			$leveres[$x]=$antal[$x];
			if (isset($linje_id[$x]) && $varenr[$x]) batch($linje_id[$x]);
		}
		if ((!$sletslut) && ($posnr_ny[$x]=="->")) $sletstart=$x;  
		if (($sletstart) && ($posnr_ny[$x]=="<-")) $sletslut=$x;
	}
#	if (($sletstart)&&($sletslut)&&($sletstart<$sletslut)) {
#		for ($x=$sletstart; $x<=$sletslut; $x++) $posnr_ny[$x]="-";
#	}
	if ($levdato) $levdate=usdate($levdato);
	if ($id) { # 2009.05.11 
		db_modify("update ordrer set email='$email',mail_fakt='$mail_fakt',pbs='$pbs',udskriv_til='$udskriv_til' where id='$id'",__FILE__ . " linje " . __LINE__);
		if ($genfakt && $genfakt!='-') db_modify("update ordrer set nextfakt='".usdate($genfakt)."' where id='$id'",__FILE__ . " linje " . __LINE__);
		elseif ($genfakt=='-') {
			db_modify("update ordrer set nextfakt=NULL where id='$id'",__FILE__ . " linje " . __LINE__);
			$genfakt=NULL;
		}
	}
	if ($fakturadato) $fakturadate=usdate($fakturadato);
	if (($konto_id)&&(!$ref)&&($status<3)) {
		print "<BODY onLoad=\"javascript:alert('Vor ref. SKAL udfyldes')\">";
	}
	$bogfor=1;
	if ($godkend == "on"||$omdan_t_fakt == "on"||($status==0&&$hurtigfakt=="on")) $status++;
	if ($status==1) {
		if ($levdato) $levdate=usdate($levdato);
		if (!$levdate) {
			if ($hurtigfakt!='on') {
				print "<BODY onLoad=\"javascript:alert('Leveringsdato sat til dags dato.')\">";
				$levdate=date("Y-m-d");
			} else $levdate=$ordredate;;
		}
		elseif ($levdate<$ordredate) {
			print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
			$status=0;
		}
	}
	if (strstr($submit,"Kred")) {	
	$art='DK';
	$query = db_select("select id from ordrer where kred_ord_id = $id",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$row[id]\">";
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
	elseif (!$art) $art='DO';
	if (strlen($ordredate)<6) $ordredate=date("Y-m-d");
	if (($kontonr&&!$firmanavn)||($kontonr&&$gl_id)) {
		$query = db_select("select * from adresser where kontonr = '$kontonr' and art='D'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
		$konto_id=$row['id'];
		$firmanavn=addslashes($row['firmanavn']);
		$addr1=addslashes($row['addr1']);
			$addr2=addslashes($row['addr2']);
			$postnr=addslashes($row['postnr']);
			$bynavn=addslashes($row['bynavn']);
			$land=addslashes($row['land']);
			$kontakt=addslashes($row['kontakt']);
			$betalingsdage=$row['betalingsdage'];
			$betalingsbet=$row['betalingsbet'];
			$cvrnr=$row['cvrnr'];
			$notes=addslashes($row['notes']);
			$email=$row['email'];
			$ean=$row['ean'];
			$institution=$row['institution'];
			$mail_fakt=$row['mailfakt'];
			$gruppe=$row['gruppe'];
			
			// Check customer type from kontotype field
			$qtxt = "SELECT kontotype FROM adresser WHERE id = '$konto_id'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$kontotype = if_isset($r, 0, 'kontotype');

			if ($kontotype == 'erhverv') {
				$incl_moms = $vatBusinessCustomers; // Use business customer VAT setting for business customers
			} else {
				$incl_moms = $vatPrivateCustomers; // Use private customer VAT setting for private customers
			}
			if ($gruppe) {
				$r = db_fetch_array(db_select("select box1,box3,box4,box6 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
				$tmp= substr($r['box1'],1,1);
				$std_rabat=$r['box6']*1;
				if (!$gl_id) {# valuta & sprog skal beholdes v. ordrekopiering.
					$formularsprog=$r['box4'];
			 		$valuta=$r['box3'];
				}
				$r = db_fetch_array(db_select("select box2 from grupper where art='SM' and kodenr='$tmp'",__FILE__ . " linje " . __LINE__));
				$momssats=$r['box2'];
				if (!$momssats) {
					print "<BODY onLoad=\"javascript:alert('Debitorgrupper forkert opsat')\">";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
					exit;
				}
			} else {
				print "<BODY onLoad=\"javascript:alert('Debitoren er ikke tilknyttet en debitorgruppe')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?id=$konto_id&returside=../debitor/ordre.php&ordre_id=$id&fokus=$fokus?id=$id\">";
				exit;
			}
		}
	}
	if ((!$id)&&($konto_id)&&($firmanavn)){
		$query = db_select("select ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $ordrenr=$row['ordrenr']+1;
		else $ordrenr=1;
		$qtext="insert into ordrer (ordrenr,konto_id,kontonr,kundeordnr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,betalingsdage,betalingsbet,cvrnr,ean,institution,email,mail_fakt,notes,art,ordredate,momssats,status,ref,lev_adr,valuta,projekt,sprog,pbs,restordre) values ($ordrenr,'$konto_id','$kontonr','$kundeordnr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$lev_navn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$email','$mail_fakt','$notes','$art','$ordredate','$momssats',$status,'$ref','$lev_adr','$valuta','$projekt[0]','$formularsprog','$pbs','0')";
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
		for($x=1; $x<=$linjeantal; $x++) {
			$vare_id[$x]=$vare_id[$x]*1;
			$r=db_fetch_array(db_select("select gruppe,beholdning from varer where id = $vare_id[$x]",__FILE__ . " linje " . __LINE__));
			$vare_grp[$x]=$r['gruppe'];
			$beholdning[$x]=$r['beholdning'];
			if (!$varenr[$x]) {$antal[$x]=0; $pris[$x]=0; $rabat[$x]=0;}
			if ((($antal[$x]>0)&&($leveres[$x]<0))||(($antal[$x]<0)&&($leveres[$x]>0))) {
				print "<BODY onLoad=\"javascript:alert('Der skal v&aelig;re samme fortegen i antal og l&eacute;ver! (Pos. $posnr_ny[$x] nulstillet)')\">";
				$leveres[$x]=0;
			} elseif ($vare_id[$x]) {
				if ($art=='DK') { # DK = Kreditnota
					if ($antal[$x]>0) {
						$antal[$x]=$antal[$x]*-1;
						print "<BODY onLoad=\"javascript:alert('Der kan ikke krediteres et negativt antal. Antal reguleret (Varenr: $varenr[$x])')\">";
					}					 
					$query = db_select("select antal from ordrelinjer where id = $kred_linje_id[$x] and vare_id=$vare_id[$x]",__FILE__ . " linje " . __LINE__); #Vare_id er med for ikke at taelle delvarer med v. samlevarer.
					$row = db_fetch_array($query);
					if ($antal[$x]+$row[antal]<0) {
						$antal[$x]=$row[antal]*-1;
						print "<BODY onLoad=\"javascript:alert('Der kan max krediteres $row[antal]. Antal reguleret (Varenr: $varenr[$x])')\">";
					}
				} elseif (($antal[$x]<0)&&($kred_linje_id[$x]>0)) {
					$query = db_select("select antal from ordrelinjer where id = $kred_linje_id[$x] and vare_id=$vare_id[$x]",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					if ($antal[$x]+$row[antal]<0) {
						$antal[$x]=$row[antal]*-1;
						print "<BODY onLoad=\"javascript:alert('Der kan max tages $row[antal] retur. Antal reguleret (Varenr: $varenr[$x])')\">";
					}
				} elseif ($antaldiff[$x]) {
					$svar=opret_ordrelinje($id,"$varenr[$x]","$antaldiff[$x]","$beskrivelse[$x]","$pris[$x]","$rabat[$x]","$art","$momsfri[$x]","$posnr_ny[$x]","$linje_id[$x]","$incl_moms");
				}
				if (!$negativt_lager && $leveres[$x]>$beholdning[$x] && (!$hurtigfakt || $submit=="Fakturer") && $leveres[$x]>$beholdning[$x] && $leveres[$x]>0 && 
						db_fetch_array(db_select("select id from grupper where kodenr='$vare_grp[$x]' and art='VG' and box8='on'",__FILE__ . " linje " . __LINE__))) { 
					if ($beholdning[$x]<=0) $leveres[$x]=0; 
					else $leveres[$x]=$beholdning[$x]*1;
					if ($hurtigfakt) $tekst="Lagerbeholdning:$beholdning[$x]. Der kan max leveres $leveres[$x] Pos nr. $posnr_ny[$x])";
					else $tekst="Lagerbeholdning:$beholdning[$x]. Der kan max leveres $leveres[$x]. Antal reguleret (Pos.nr: $posnr_ny[$x])";
					Print "<BODY onLoad=\"javascript:alert('$tekst')\">";
					if ($submit=="Fakturer") $submit="Gem";
				}	
				$tidl_lev[$x]=0;
				$query = db_select("select antal from batch_salg where linje_id = $linje_id[$x] and vare_id=$vare_id[$x]",__FILE__ . " linje " . __LINE__); #Vare_id er med for ikke at taelle delvarer med v. samlevarer.
				while ($row = db_fetch_array($query)) {$tidl_lev[$x]=$tidl_lev[$x]+$row['antal'];}
				if ((($tidl_lev[$x]<0)&&($antal[$x]>$tidl_lev[$x]))||(($tidl_lev[$x]>0)&&($antal[$x]<$tidl_lev[$x]))){
					$antal[$x]=$tidl_lev[$x];
					print "<BODY onLoad=\"javascript:alert('Der er allerede leveret $tidl_lev[$x]. Antal reguleret (Varenr: $varenr[$x])')\">";
				}	
				elseif ($antal>0) {
					if (($tidl_lev[$x]<$antal[$x])&&($status>1)) {
						if ($omdan_t_fakt == "on") {print "<BODY onLoad=\"javascript:alert('Du kan ikke fakturere f&oslash;r alt er leveret')\">";}
						$status=1;
					}
					$query = db_select("select antal from reservation where linje_id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
					while ($row = db_fetch_array($query)) {$reserveret[$x]=$reserveret[$x]+$row[antal];}
					if (($antal[$x]<$tidl_lev[$x]+$reserveret[$x])&&($antal[$x]>0)) {
						$diff=$tidl_lev[$x]+$reserveret[$x]-$antal[$x];
						while ($diff>0) {
							$query = db_select("select * from reservation where linje_id = $linje_id[$x] order by batch_kob_id desc",__FILE__ . " linje " . __LINE__);
							$row = db_fetch_array($query);
							if ($diff < $row[antal]) {
								$temp = $row[antal] - $diff;
								if ($row[batch_kob_id]) {db_modify("update reservation set antal = $temp where linje_id=$linje_id[$x] and batch_kob_id=$row[batch_kob_id] and antal=$row[antal] and vare_id=$row[vare_id]",__FILE__ . " linje " . __LINE__);}
							 	else {db_modify("update reservation set antal = $temp where linje_id=$linje_id[$x] and antal=$row[antal] and vare_id=$row[vare_id]",__FILE__ . " linje " . __LINE__);}
								$diff=0;															
							}	
							elseif ($diff >= $row[antal]) {
								if ($row[batch_kob_id]) {db_modify("delete from reservation where linje_id=$linje_id[$x] and batch_kob_id=$row[batch_kob_id] and antal=$row[antal] and vare_id=$row[vare_id]",__FILE__ . " linje " . __LINE__);}
								else {db_modify("delete from reservation where linje_id=$linje_id[$x] and antal=$row[antal] and vare_id=$row[vare_id]",__FILE__ . " linje " . __LINE__);}
								$diff=$diff - $row[antal];							
							} 
						} 
					}	
				} 
				if (!isset($modtaget[$x]))$modtaget[$x]=0;
				$query = db_select("select antal from batch_salg where linje_id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
				while ($row = db_fetch_array($query)) $modtaget[$x]=$modtaget[$x]+$row['antal'];
				if (($antal[$x]>$modtaget[$x])&&($modtaget[$x]<0)) {
					$antal[$x]=$modtaget[$x];
					print "<BODY onLoad=\"javascript:alert('Der er allerede modtaget $temp. Antal reguleret (Varenr: $varenr[$x])')\">";
				}	
			}
			if ($posnr_ny[$x]=='-') {
				if ($vare_id[$x]) {
					$query = db_select("select * from batch_kob where linje_id = $linje_id[$x] and ordre_id=$id and vare_id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
					if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke slette en varelinje n&aring;r der &eacute;r modtaget vare(r)')\">";}
					else {
						$query = db_select("select * from batch_salg where linje_id = $linje_id[$x] and ordre_id=$id and vare_id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
						if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke slette en varelinje n&aring;r der &eacute;r leveret vare(r)')\">";}
						else {
							db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
							db_modify("delete from reservation where linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
							db_modify("delete from reservation where batch_salg_id='-$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
						}
					}
				} else {db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);}
			 } elseif ((!strstr($submit,"Kopi"))&&(!strstr($submit,"Udskriv"))) {
				if ((!strpos($posnr_ny[$x],'+'))&&($id)) {
					$posnr_ny[$x]=afrund($posnr_ny[$x],0);
					if ($posnr_ny[$x]>=1) {db_modify("update ordrelinjer set posnr='$posnr_ny[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);}
					else print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte et - (minus) som pos nr for at slette en varelinje')\">";
				}
				if ($vare_id[$x] && $r=db_fetch_array(db_select("SELECT box6 FROM grupper WHERE kodenr='vare_grp[$x]'and art='VG'",__FILE__ . " linje " . __LINE__))) {
					if (($r['box6']!=NULL)&&($rabat[$x]>$r['box6'])) {
						$rabat[$x]=$r['box6'];
						print "<BODY onLoad=\"javascript:alert('Max rabat for varenummer: $varenr[$x] er $rabat[$x]%')\">";
					}
				}
				if (!$antal[$x]) $antal[$x]=0;
				$sum=$sum+($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				if (!$leveres[$x]) $leveres[$x]=0;
				if (!$rabat[$x]) $rabat[$x]='0';
				if ($projekt[0]) $projekt[$x]=$projekt[0];
				else $projekt[$x]=$projekt[$x]*1;
				
				db_modify("update ordrelinjer set varenr='$varenr[$x]',beskrivelse='$beskrivelse[$x]',antal='$antal[$x]',leveres='$leveres[$x]',pris='$pris[$x]',rabat='$rabat[$x]',projekt='$projekt[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
				if ((strpos($posnr_ny[$x],'+'))&&($id)) indsaet_linjer($id,$linje_id[$x],$posnr_ny[$x]);
			}
#			if (strlen($fakturadate)>5){db_modify("update ordrer set fakturadate='$fakturadate' where id=$id");}
		}
		if (($posnr_ny[0])&&(!strstr($submit,'Opslag'))) {
			if ($varenr[0]) {
				if (!$antal[0]) $antal[0]=1;
				$svar=opret_ordrelinje($id,$varenr[0],$antal[0],$beskrivelse[0],$pris[0],$rabat[0],$art,"$momsfri[0]","$posnr_ny[0]","0","$incl_moms");	
			}
		elseif ($beskrivelse[0]) {db_modify("insert into ordrelinjer (ordre_id,posnr,beskrivelse) values ('$id','$posnr_ny[0]','$beskrivelse[0]')",__FILE__ . " linje " . __LINE__);}
		}
		if ($id) { 
			$r = db_fetch_array(db_select("select tidspkt,hvem from ordrer where status < 3 and id = $id and hvem != '$brugernavn'",__FILE__ . " linje " . __LINE__));
			$tidspkt=trim($r['tidspkt']);
			if ($tidspkt)	{
				if ($tidspkt-($row['tidspkt'])<3600) {
					print "<BODY onLoad=\"javascript:alert('Orderen er overtaget af $row[hvem]')\">";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
				}
			} else {
				$tmp="";
				if (strlen($levdate)>6) $tmp=",levdate='$levdate'";
				if (strlen($fakturadate)>6) $tmp=$tmp.",fakturadate='$fakturadate'";
				if ($genfakt) $tmp=$tmp.",nextfakt='".usdate($genfakt)."'";
					$opdat="update ordrer set kontonr='$kontonr',kundeordnr='$kundeordnr',firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',land='$land',kontakt='$kontakt',lev_navn='$lev_navn',lev_addr1='$lev_addr1',lev_addr2='$lev_addr2',lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_kontakt='$lev_kontakt',vis_lev_addr='$vis_lev_addr',felt_1='$felt_1',felt_2='$felt_2',felt_3='$felt_3',felt_4='$felt_4',felt_5='$felt_5',betalingsdage='$betalingsdage',betalingsbet='$betalingsbet',cvrnr='$cvrnr',ean='$ean',institution='$institution',email='$email',mail_fakt='$mail_fakt',udskriv_til='$udskriv_til',notes='$notes',ordredate='$ordredate',status=$status,ref='$ref',fakturanr='$fakturanr',lev_adr='$lev_adr',hvem = '$brugernavn',tidspkt='$tidspkt',projekt='$projekt[0]',sprog='$formularsprog',pbs='$pbs',restordre='$restordre' $tmp where id=$id";
					db_modify($opdat,__FILE__ . " linje " . __LINE__);
			}
		}
	}
########################## KOPIER #################################	
	
if ((strstr($submit,'Kopi'))||(strstr($submit,'Kred')))	{
		if ((strstr($submit,'Kred'))&&($kred_ord_id)) db_modify("update ordrer set kred_ord_id='$kred_ord_id' where id='$id'",__FILE__ . " linje " . __LINE__);
		for($x=1; $x<=$linjeantal; $x++) {
			if (!$vare_id[$x] && $antal[$x] && $varenr[$x]) {
				$query = db_select("select id from varer where varenr = '$varenr[$x]'",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) {$vare_id[$x]=$row[id];}
			}
			if ($vare_id[$x]){
				(strstr($submit,'Kopi'))?$tmp=$antal[$x]*1:$tmp=$antal[$x]*-1;
				(strstr($submit,'Kred'))?$tmp2=$linje_id[$x]:$tmp2='0';
				$svar=opret_ordrelinje($id,"$varenr[$x]","$tmp","$beskrivelse[$x]","$pris[$x]","$rabat[$x]","$art","$momsfri[$x]","$posnr[$x]","$tmp2","$incl_moms");
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
	}
	transaktion("commit");
}
##########################UDSKRIFT#################################

	if (strstr($submit,"Udskriv")){
		if ($status>=3)  {
			$temp="aktura"; $formular=4; $ps_fil="formularprint.php";
		}
		elseif($status>=1) {
			$query = db_select("select lev_nr from batch_salg where ordre_id=$id and lev_nr=1",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {$formular=3; $ps_fil="udskriftsvalg.php";}
			else {$temp="rdrebek";	$formular=2; $ps_fil="formularprint.php";}
		 }
		else {$temp="ilbud"; $formular=1; $ps_fil="formularprint.php";}
		if($udskriv_til=="oioxml") {
			if($art=="DO") $oioxml='faktura';
			else $oioxml='kreditnota';
			if ($popup) print "<BODY onLoad=\"JavaScript:window.open('oioxml_dok.php?id=$id&doktype=$oioxml' ,'' ,'$jsvars');\">";
			else print "<meta http-equiv=\"refresh\" content=\"0;URL=oioxml_dok.php?id=$id&doktype=$oioxml\">"; 
		} else {
			$oioxml='';
		 	if ($popup) print "<BODY onLoad=\"JavaScript:window.open('$ps_fil?id=$id&formular=$formular' ,'' ,',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,location=1');\">";
			else print "<meta http-equiv=\"refresh\" content=\"0;URL=$ps_fil?id=$id&formular=$formular\">";
		}
	}	

##########################OPSLAG################################

	if ((strstr($submit,'Opslag'))||((strstr($submit,'Gem'))&&(!$id))) {
		if ((strstr($fokus,'kontonr'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'firmanavn'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'addr1'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'addr2'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'postnr'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'bynavn'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'vare'))&&($art!='DK')) vareopslag($art,$sort,'varenr',$id,$vis_kost,$ref,$varenr[0]);
		if (strstr($fokus,'besk') && $beskrivelse[0] && $art!='DK') vareopslag($art,$sort,'beskrivelse',$id,$vis_kost,$ref,$beskrivelse[0]);
#		if (strstr($fokus,'besk') && $art!='DK') tekstopslag($sort,$id);
		if ((strstr($fokus,'kontakt'))&&($id)) ansatopslag($sort,$fokus,$id,$vis,$kontakt);
		elseif (strstr($fokus,'kontakt')) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
	}

########################## del_ordre  - SKAL VAERE PLACERET FOER "FAKTURER" ################################
	if ($submit=='del_ordre') {
		$sum=0; $moms=0;
		$ny_sum=0; $ny_moms=0;
		transaktion("begin");
		$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		db_modify("insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,kundeordnr,betalingsdage,betalingsbet,cvrnr,ean,institution,notes,art,ordredate,momssats,tidspkt,ref,status,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,valuta,projekt,sprog,email,mail_fakt,pbs,restordre) values ('$r[ordrenr]','$r[konto_id]','$r[kontonr]','$r[firmanavn]','$r[addr1]','$r[addr2]','$r[postnr]','$r[bynavn]','$r[land]','$r[kontakt]','$r[kundeordnr]','$r[betalingsdage]','$r[betalingsbet]','$r[cvrnr]','$r[ean]','$r[institution]','$r[notes]','$r[art]','$r[ordredate]','$r[momssats]','$r[tidspkt]','$r[ref]','$r[status]','$r[lev_navn]','$r[lev_addr1]','$r[lev_addr2]','$r[lev_postnr]','$r[lev_bynavn]','$r[lev_kontakt]','$r[valuta]','$r[projekt]','$r[sprog]','$r[email]','$r[mail_fakt]','$r[pbs]','1')",__FILE__ . " linje " . __LINE__);
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
		print "<BODY onLoad=\"javascript:alert('Der er oprettet en ny ordre med samme ordrenr')\">";
		#$submit='Fakturer';
		transaktion("commit");
	}
########################## FAKTURER   - SKAL VAERE PLACERET EFTER "del_ordre" ################################
	if ($submit=='Fakturer') {
		$q=db_select("select * from ordrelinjer where ordre_id = '$id' and m_rabat > '0'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			
		}	
	
		if ($hurtigfakt=='on') {
			$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
			if (!$row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke fakturere uden ordrelinjer')\">";}
			else {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=levering.php?id=$id&hurtigfakt=on&mail_fakt=$mail_fakt&pbs=$pbs\">";}
		} elseif ($submit=='Fakturer'&&$bogfor!=0) {
				$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
			if (!$row = db_fetch_array($query)) {Print "Du kan ikke fakturere uden ordrelinjer";}
			else {
				if($udskriv_til=="oioxml") {
					if($art=="DO") $oioxml='faktura';
					else $oioxml='kreditnota';
				} else $oioxml='';
				print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?id=$id&mail_fakt=$mail_fakt&pbs=$pbs&oioxml=$oioxml\">";
			}
		}
	}
############################ LEVER ################################

	if ((strstr($submit,'Lev'))&&($bogfor!=0)) {
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		if (!$row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke levere uden ordrelinjer')\">";}
		else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=levering.php?id=$id\">";}
	}
	print "<meta http-equiv=\"refresh\" content=\"3600;URL=$returside\">";

###########################################################################

ordreside($id,$regnskab);


function ordreside($id,$regnskab)
{
	global $bgcolor;global $bgcolor5;global $bogfor;global $bruger_id;global $brugernavn;
	global $charset;
	global $db_encode;
	global $fokus;global $fakturadate;global $fakturadato;
	global $genfakt;
	global $hurtigfakt;
	global $sprog_id;global $sprog;global $submit;
	global $incl_moms;
	global $returside;
	global $oioxml;
	
	if (!$returside) {
		if ($popup) $returside="../includes/luk.php";
		else $returside="ordreliste.php";
	}
	$batchvare=NULL;$dbsum=NULL;$ko_ant=NULL;$levdato=NULL;$levdiff=NULL;$linjebg=NULL;$momsfri=NULL;$momssum=NULL;$reserveret=NULL;$tidl_lev=NULL;$y=NULL;
	
	if (!$id) $fokus='kontonr';
	print "<form name=ordre action=ordre.php?id=$id&returside=$returside method=post>";
	if ($id) {
		$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$konto_id = $row['konto_id']*1;
		$kontonr = stripslashes($row['kontonr']);
		$firmanavn = stripslashes($row['firmanavn']);
		$addr1 = stripslashes($row['addr1']);
		$addr2 = stripslashes($row['addr2']);
		$postnr = stripslashes($row['postnr']);
		$bynavn = stripslashes($row['bynavn']);
		$land = stripslashes($row['land']);
		$kontakt = stripslashes($row['kontakt']);
		$kundeordnr = stripslashes($row['kundeordnr']);
		$lev_navn = stripslashes($row['lev_navn']);
		$lev_addr1 = stripslashes($row['lev_addr1']);
		$lev_addr2 = stripslashes($row['lev_addr2']);
		$lev_postnr = stripslashes($row['lev_postnr']);
		$lev_bynavn = stripslashes($row['lev_bynavn']);
		$lev_kontakt = stripslashes($row['lev_kontakt']);
		$vis_lev_addr = $row['vis_lev_addr'];
		$felt_1 = stripslashes($row['felt_1']);
		$felt_2 = stripslashes($row['felt_2']);
		$felt_3 = stripslashes($row['felt_3']);
		$felt_4 = stripslashes($row['felt_4']);
		$felt_5 = stripslashes($row['felt_5']);
		$cvrnr = $row['cvrnr'];
		$ean = stripslashes($row['ean']);
		$institution = stripslashes($row['institution']);
		$email = stripslashes($row['email']);
		$mail_fakt = $row['mail_fakt'];
		$udskriv_til = $row['udskriv_til'];
		$betalingsbet = trim($row['betalingsbet']);
		$betalingsdage = $row['betalingsdage'];
		$valuta=$row['valuta'];
		$valutakurs=$row['valutakurs']*1;
		$projekt[0]=$row['projekt'];
		$formularsprog=$row['sprog'];
		$pbs=$row['pbs'];
		$ref = trim(stripslashes($row['ref']));
		$fakturanr = stripslashes($row['fakturanr']);
		$lev_adr = stripslashes($row['lev_adr']);
		$ordrenr=$row['ordrenr'];
		$kred_ord_id=$row['kred_ord_id'];
		$restordre=$row['restordre'];
		if($row['ordredate']) $ordredate=$row['ordredate'];
		else {$ordredate=date("y-m-d");}
		$ordredato=dkdato($ordredate);
		if ($row['levdate']) $levdato=dkdato($row['levdate']);
		if ($row['fakturadate']) {
			$fakturadate=$row['fakturadate'];			
			$fakturadato=dkdato($row['fakturadate']);
		}
		if($row['nextfakt']) $genfakt = dkdato($row['nextfakt']);
		$momssats=$row['momssats'];
		$status=$row['status'];
		if (!$status){$status=0;}
		$kontonr=$row['kontonr'];
		$art=$row['art'];
		$x=0;
		$krediteret='';
		$q=db_select("select art,pbs_nr from adresser where art = 'S' or id = '$konto_id'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['art']=='S') $lev_pbs_nr=$r['pbs_nr'];
			else $pbs_nr=$r['pbs_nr'];
		}
		$query = db_select("select id,ordrenr from ordrer where kred_ord_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($row2 = db_fetch_array($query)) {
			$x++;
			if ($x>1) {$krediteret=$krediteret.",";}
			$krediteret=$krediteret."<a href=ordre.php?id=$row2[id]>$row2[ordrenr]</a>";
		}
		if ($status<3) $fokus='vare0';
		else $fokus='';
	} else {
		$r=db_fetch_array(db_select("select ansatte.navn as ref from ansatte,brugere where ansatte.id = ".nr_cast("brugere.ansat_id")." and brugere.brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__));
		$ref=$r['ref'];
	}
	
######### pile ########## tilfoejet 20080210
		if ($status==0) $tmp="tilbud";
		elseif($status>=3) $tmp="faktura";
		else $tmp="ordrer";

#		echo "$status select box1 from grupper where art = 'OLV' and kodenr = '$bruger_id' and  kode='$tmp'<br>";
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
		sidehoved($id,"$returside","","","Kunde kreditnota $ordrenr (kreditering af ordre nr: <a href=ordre.php?id=$kred_ord_id>$row2[ordrenr]</a>)");
	}
	elseif ($krediteret) {sidehoved($id,"$returside","","","Kundeordre $ordrenr ( krediteret p&aring; KN nr: $krediteret )");}
	else {
		if ($status<1) $temp='Tilbud';
		elseif ($status<2) $temp='Ordrer';
		else $temp='Faktura';
		if ($returside=="ordreliste.php") sidehoved($id,"$returside?valg=$temp","","","Kundeordre $ordrenr - $temp");
		else sidehoved($id,"$returside","","","Kundeordre $ordrenr - $temp");
	}
	if (!$status)	$status=0;
	print "<input type=hidden name=ordrenr value=$ordrenr>";
	print "<input type=hidden name=status value=$status>";
	print "<input type=hidden name=id value=$id>";
	print "<input type=hidden name=art value=$art>";
	print "<input type=hidden name=kred_ord_id value=$kred_ord_id>";

	if ($status>=3) {
		print "<input type=hidden name=konto_id value=$konto_id>";
		print "<input type=hidden name=kontonr value=\"$kontonr\">";
		print "<input type=hidden name=firmanavn value=\"$firmanavn\">";
		print "<input type=hidden name=addr1 value=\"$addr1\">";
		print "<input type=hidden name=addr2 value=\"$addr2\">";
		print "<input type=hidden name=postnr value=\"$postnr\">";
		print "<input type=hidden name=bynavn value=\"$bynavn\">";
		print "<input type=hidden name=land value=\"$land\">";
		print "<input type=hidden name=kontakt value=\"$kontakt\">";
		print "<input type=hidden name=kundeordnr value=\"$kundeordnr\">";
		print "<input type=hidden name=lev_navn value=\"$lev_navn\">";
		print "<input type=hidden name=lev_addr1 value=\"$lev_addr1\">";
		print "<input type=hidden name=lev_addr2 value=\"$lev_addr2\">";
		print "<input type=hidden name=lev_postnr value=\"$lev_postnr\">";
		print "<input type=hidden name=lev_bynavn value=\"$lev_bynavn\">";
		print "<input type=hidden name=lev_kontakt value=\"$lev_kontakt\">";
		print "<input type=hidden name=levdato value=\"$levdato\">";
		print "<input type=hidden name=genfakt value=\"$genfakt\">";
		print "<input type=hidden name=cvrnr value=\"$cvrnr\">";
		print "<input type=hidden name=ean value=\"$ean\">";
		print "<input type=hidden name=institution value=\"$institution\">";
		print "<input type=hidden name=email value=\"$email\">";
#		print "<input type=hidden name=mail_fakt value=\"$mail_fakt\">";
		print "<input type=hidden name=betalingsbet value=\"$betalingsbet\">";
		print "<input type=hidden name=betalingsdage value=\"$betalingsdage\">";
		print "<input type=hidden name=momssats value=\"$momssats\">";
		print "<input type=hidden name=ref value=\"$ref\">";
		print "<input type=hidden name=fakturanr value=\"$fakturanr\">";
		print "<input type=hidden name=lev_adr value=\"$lev_adr\">";
		print "<input type=hidden name=valuta value=\"$valuta\">";
		print "<input type=hidden name=valutakurs value=\"$valutakurs\">";
		print "<input type=hidden name=projekt[0] value=\"$projekt[0]\">";
		print "<input type=hidden name=pbs value=\"$pbs\">";

		if ($mail_fakt) $mail_fakt="checked";
		
##### pile ########	tilfoejet 20080210	
		$alerttekst=findtekst(154,$sprog_id);
		$spantekst=findtekst(198,$sprog_id);
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\"><tbody>";
		if ($prev_id)	print "<tr><td width=50%><span title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$prev_id&returside=$returside','$alerttekst')\"><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td>";
		else print "<tr><td width=50%></td>";
		$spantekst=findtekst(199,$sprog_id);
		if ($next_id)	print "<td width=50% align=right><span title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$next_id&returside=$returside','$alerttekst')\"><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td></tr>";
		else print "<tr><td width=50%></td>";
		print "</tbody></tabel>";
##### pile ########		
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" valign = \"top\"><tbody>";
		$ordre_id=$id;
		print "<tr><td width=31%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
		print "<tr><td width=100><b>Kontonr</td><td width=100>$kontonr</td></tr>\n";
		print "<tr><td><b>Firmanavn</td><td>$firmanavn</td></tr>\n";
		print "<tr><td><b>Adresse</td><td>$addr1</td></tr>\n";
		print "<tr><td></td><td>$addr2</td></tr>\n";
		print "<tr><td><b>Postnr &amp; by</td><td>$postnr $bynavn</td></tr>\n";
		print "<tr><td><b>Land</td><td>$land</td></tr>\n";
		print "<tr><td><b>Att.:</td><td>$kontakt</td></tr>\n";
		print "<tr><td><b>Ordrenr.</td><td>$kundeordnr</td></tr>\n";
		print "<tr><td><b>CVR-nr.</td><td>$cvrnr</td></tr>\n";
		print "<tr><td><b>EAN-nr.</td><td>$ean</td></tr>\n";
		print "<tr><td><b>Institution</td><td>$institution</td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=38%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
		print "<tr><td><b>E-mail</td><td width=105><input class=\"inputbox\" type=text name=email size=15 value=\"$email\"></td></tr>\n";
#		print "<tr><td><b>Edskriv til</b></td>"
#		if ($email) 		
		print "<tr><td>Udskriv til</td>"; 
		if ($mail_fakt) $udskriv_til="email";
		if ($oioxml) $udskriv_til="oioxml";
		if ($lev_pbs_nr) {
			if ($pbs == "FI") $udskriv_til="PBS_FI";
			elseif ($pbs == "BS") $udskriv_til="PBS_BS";
		}
		if (!$udskriv_til) $udskriv_til="PDF"; 	
		print "<td><select class=\"inputbox\" NAME=udskriv_til>";
		print "<option>$udskriv_til</option>";
		if ($udskriv_til!="PDF") print "<option>PDF</option>";
		if ($udskriv_til!="email" && $email) print "<option>email</option>";
		if ($udskriv_til!="oioxml" && strlen($ean)==13) print "<option title=\"Kun ved fakturering/kreditering.\">oioxml</option>";
#		if ($lev_pbs_nr) {
#			if ($udskriv_til!="PBS_FI") print "<option>PBS_FI</option>";
#			if ($udskriv_til!="PBS_BS") print "<option title=\"Opkr&aelig;ves via PBS betalingsservice\">PBS_BS</option>";
#		}
		print "</SELECT></td></tr>";
/*		
		print "<tr><td><b>Fakt som mail</td><td><input class=\"inputbox\" type=checkbox name=mail_fakt $mail_fakt></td></tr>\n";
		if ($lev_pbs_nr) {
			if ($pbs == "FI") $pbs_fi='checked';
			elseif ($pbs == "BS") $pbs_bs='checked';
			$title="PBS udsender FI-indbetalingskort";
			if (!$pbs_bs) {
				print "<td colspan=2 title=\"$title\">Faktura via PBS (FI)</td><td title=\"$title\"><input class=\"inputbox\" type=checkbox name=pbs_fi $pbs_fi onchange=\"javascript:docChange = true;\"></td></tr>\n";
				if ($pbs_nr && !$pbs_fi) print "<tr>";
			}
			$title="Opkr&aelig;ves via PBS's betalingsservice";
			if ($pbs_nr && !$pbs_fi) print "<td colspan=2 title=\"$title\">Opkr&aelig;v via PBS (BS)</td><td title=\"$title\"><input class=\"inputbox\" type=checkbox name=pbs_bs \"$pbs_bs\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		} else print "</tr>";	
*/		
		print "<tr><td width=100><b>Ordredato</td><td width=100>$ordredato</td></tr>\n";
		print "<tr><td><b>Lev. dato</td><td>$levdato</td></tr>\n";
		print "<tr><td><b>Fakturadato</td><td>$fakturadato</td></tr>\n";
		print "<tr><td><b>Genfaktureres</td><td><input class=\"inputbox\" type=text name=genfakt size=7 value=\"$genfakt\"><input type=submit value=\"OK\" name=\"submit\"></td></tr>\n";
		print "<tr><td><b>Betaling</td><td>$betalingsbet&nbsp;+&nbsp;$betalingsdage</td>";
		print "<tr><td><b>Vor ref.</td><td>$ref</td></tr>\n";
		print "<tr><td><b>Fakturanr</td><td>$fakturanr</td></tr>\n";
		$tmp=dkdecimal($valutakurs);
		if ($valuta) print "<tr><td><b>Valuta / Kurs</td><td>$valuta / $tmp</td></tr>\n";
		if ($projekt[0]) print "<tr><td><b>Projekt</td><td>$projekt[0]</td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=31%><table cellpadding=0 cellspacing=0 border=0 width=\"100%\" valign=\"top\">";
		if ($vis_lev_addr) {
			print "<tr><td><b>Leveringsadresse</b><br />&nbsp;</td></tr>\n";
			print "<tr><td colspan=2><b><hr></b></tr>\n";
			print "<tr><td>Firmanavn</td><td colspan=2>$lev_navn</td></tr>\n";
			print "<tr><td>Adresse</td><td colspan=2>$lev_addr1</td></tr>\n";
			print "<tr><td></td><td colspan=2>$lev_addr2</td></tr>\n";
			print "<tr><td>Postnr. &amp; by</td><td>$lev_postnr $lev_bynavn</td></tr>\n";
			print "<tr><td>Att.:</td><td colspan=2>$lev_kontakt</td></tr>\n";
			print "<tr><td colspan=2><b><hr></b></tr>\n";
			print "<tr><td colspan=2><a href=ordre.php?id=$id&&returside=$returside&vis_lev_addr=0>Vis ekstrafelter</tr>\n";
		} else {
			print "<tr><td colspan=2><b>".findtekst(243,$sprog_id)."</b></tr>\n";
			print "<tr><td colspan=2><b><hr></b></tr>\n";
			if (findtekst(244,$sprog_id)) print "<tr><td><b>".findtekst(244,$sprog_id)."</b></td></td><td>$felt_1</td></tr>\n";
			if (findtekst(245,$sprog_id)) print "<tr><td><b>".findtekst(245,$sprog_id)."</b></td></td><td>$felt_2</td></tr>\n";
			if (findtekst(246,$sprog_id)) print "<tr><td><b>".findtekst(246,$sprog_id)."</b></td></td><td>$felt_3</td></tr>\n";
			if (findtekst(247,$sprog_id)) print "<tr><td><b>".findtekst(247,$sprog_id)."</b></td></td><td>$felt_4</td></tr>\n";
			if (findtekst(248,$sprog_id)) print "<tr><td><b>".findtekst(248,$sprog_id)."</b></td></td><td>$felt_5</td></tr>\n";
			print "<tr><td colspan=2><b><hr></b></tr>\n";
			print "<tr><td colspan=2><a href=ordre.php?id=$id&&returside=$returside&vis_lev_addr=1>Vis leveringsadresse</tr>\n";
		}
		print "</td></tr></tbody></table></td>";
		print "</td></tr><tr><td align=center colspan=3><table cellpadding=0 cellspacing=0 border=1 width=100%><tbody>";
		print "<tr><td colspan=7></td></tr><tr>";
		print "<td align=center><b>pos</td><td align=center><b>varenr</td><td align=center><b>ant.</td><td align=center><b>enhed</td><td align=center><b>beskrivelse</td><td align=center><b>pris</td><td align=center><b>%</td><td align=center><b>i alt</td>";
		if (db_fetch_array(db_select("select * from grupper where art = 'PRJ' order by kodenr",__FILE__ . " linje " . __LINE__))) {
			$vis_projekt='on';
		}
		if ($vis_projekt && !$projekt[0]) print "<td align=center title='Nummer herunder viser projektnummer hvis ordrelinjen er tilknyttet et projekt'><b>Proj.</b></td>";
		else print "<td></td>";
		print "</tr>\n";
		$x=0;
		if (!$ordre_id) $ordre_id=0;
		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query))	{
			if (($row['posnr']>0)&&($row['samlevare']<1)) {
				$x++;
				$linje_id[$x]=$row['id'];
				$vare_id[$x]=$row['vare_id'];
				$posnr[$x]=$row['posnr'];
				$varenr[$x]=stripslashes(htmlentities($row['varenr'],ENT_COMPAT,$charset));
				$lev_varenr[$x]=stripslashes(htmlentities($row['lev_varenr'],ENT_COMPAT,$charset));
				$beskrivelse[$x]=stripslashes(htmlentities($row['beskrivelse'],ENT_COMPAT,$charset));
				$enhed[$x]=stripslashes(htmlentities($row['enhed'],ENT_COMPAT,$charset));
				$pris[$x]=$row['pris'];
				$rabat[$x]=$row['rabat'];
				$antal[$x]=$row['antal'];
				$momsfri[$x]=$row['momsfri'];
				$varemomssats[$x]=$row['momssats'];
				if (!$momsfri[$x] && !$varemomssats[$x]) $varemomssats[$x]=$momssats;
				elseif ($varemomssats[$x] > $momssats) $varemomssats[$x]=$momssats;
				elseif ($momsfri[$x]) $varemomssats[$x]=0;
				$serienr[$x]=stripslashes(htmlentities($row['serienr'],ENT_COMPAT,$charset));
				$kostpris[$x]=$row['kostpris'];
				$projekt[$x]=$row['projekt']*1;
			}
		}	
		$linjeantal=$x;
		print "<input type=hidden name=linjeantal value=$x>";
		$totalrest=0;
		$sum=0;
		for ($x=1; $x<=$linjeantal; $x++) {
			if (!$vare_id[$x]) {
				$query = db_select("select id from varer where varenr = '$varenr[$x]'",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) {$vare_id[$x]=$row[id];}
			}
			if (($varenr[$x])&&($vare_id[$x])) {
				$query = db_select("select provisionsfri from varer where id = '$vare_id[$x]' and provisionsfri='on'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$provisionsfri[$x]=$row['provisionsfri'];
					$query = db_select("select * from batch_salg where linje_id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					$y=0;
					while ($row = db_fetch_array($query)) {
						$y=$y+10000;
						$z=$y+$x;
						$r2 = db_fetch_array(db_select("select antal,ordre_id,pris,fakturadate,linje_id from batch_kob where id = $row[batch_kob_id]",__FILE__ . " linje " . __LINE__));
						$kobs_ordre_id[$z]=$r2['ordre_id'];
						if ($kobs_ordre_id[$z]) {
						
							$r3=db_fetch_array(db_select("select valutakurs from ordrer where id = $kobs_ordre_id[$z]",__FILE__ . " linje " . __LINE__));
						if ($r3['valutakurs']) $kobs_valutakurs=$r3['valutakurs'];
						else $kobs_valutakurs=100;
							$r2=db_fetch_array(db_select("select pris from ordrelinjer where id = $r2[linje_id]",__FILE__ . " linje " . __LINE__));#}	
							$k_stk_ant[$z]=$row['antal'];
							if ($y>10000) $kostpris[$x]=$kostpris[$x]+$r2['pris']*$row['antal']*$kobs_valutakurs/100;
							else $kostpris[$x]=$r2['pris']*$row['antal']*$kobs_valutakurs/100;
							$kostpris[$z]=dkdecimal($r2['pris']*$kobs_valutakurs/100);
						}	else {
							$r2 = db_fetch_array(db_select("select kostpris from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
							$kostpris[$x]=$r2['kostpris']*$antal[$x];
						}
						if ($valutakurs && $valutakurs!=100) $kostpris[$x]=$kostpris[$x]*100/$valutakurs;
						if ($kobs_ordre_id[$z]) {
							$q3 = db_select("select ordrenr from ordrer where id = $kobs_ordre_id[$z]",__FILE__ . " linje " . __LINE__);
							$r3 = db_fetch_array($q3);
							$kobs_ordre_nr[$z]=$r3[ordrenr];
						}
					}
					if ($kobs_ordre_id[$z]) $ko_ant[$x]=$y/10000;
					$kostpris[$x]=$kostpris[$x]*1;
					if ($art=='DO') db_modify("update ordrelinjer set kostpris=$kostpris[$x] where id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
				$ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				if ($provisionsfri[$x]) {
					if ($art=='DO') $kostpris[$x]=$ialt;
				}
				if ($art=='DO') $db[$x]=$ialt-$kostpris[$x];
				else $db[$x]=-$ialt-$kostpris[$x];
				$ialt=afrund($ialt,3);
				if ($ialt!=0) {
					$dg[$x]=$db[$x]*100/$ialt;
					if ($art=='DO') $dk_dg[$x]=dkdecimal($dg[$x]);
					else $dk_dg[$x]=dkdecimal($dg[$x]*-1);
				}
				$dk_db[$x]=dkdecimal($db[$x]);
				$dk_kostpris[$x]=dkdecimal($kostpris[$x]);
				$sum=$sum+$ialt;
				$dkpris=dkdecimal($pris[$x]);
				$dkrabat=dkdecimal($rabat[$x]);
				if ($momsfri[$x]!='on') {
					$moms+=afrund($ialt*$varemomssats[$x]/100,2);
#					$momssum=$momssum+$ialt;
				  if($incl_moms)$dkpris=dkdecimal($pris[$x]+$pris[$x]*$varemomssats[$x]/100);
				}
				if ($antal[$x]) {
					if ($art=='DK') {$dkantal[$x]=dkdecimal($antal[$x]*-1);}
					else {$dkantal[$x]=dkdecimal($antal[$x]);}
					if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-1);}
					if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-2);}
				}
			}
			else {$antal[$x]=''; $dkpris=''; $dkrabat=''; $ialt='';}
			print "<tr bgcolor=\"$linjebg\">";
			print "<input type=hidden name=linje_id[$x] value=$linje_id[$x]>";
			print "<input type=hidden name=posn$x value=$posnr[$x]><td align=right>$posnr[$x]</td>";
			print "<input type=hidden name=vare$x value=\"$varenr[$x]\"><td>$varenr[$x]</td>";
			print "<input type=hidden name=dkan$x value=$dkantal[$x]><td align=right>$dkantal[$x]</td>";
			print "<input type=hidden name=enhed[$x] value=\"$enhed[$x]\"><td align=right>$enhed[$x]</td>";
			$title=var2str($beskrivelse[$x],$id);
			print "<input type=hidden name=beskrivelse$x value=\"$beskrivelse[$x]\"><td title=\"$title\">$beskrivelse[$x]</td>";
			print "<input type=hidden name=pris$x value=$dkpris><td align=right>$dkpris</td>";
			print "<input type=hidden name=raba$x value=$dkrabat><td align=right>$dkrabat</td>";
			print "<input type=hidden name=serienr[$x] value=\"$serienr[$x]\"";
			print "<input type=hidden name=vare_id[$x] value=$vare_id[$x]>";
			print "<input type=hidden name=lev_varenr[$x] value=\"$lev_varenr[$x]\">";
			$dbsum=$dbsum+$db[$x]; 
			if ($ialt) {
				if ($art=='DK') {
#					$ialt=$ialt*-1;
				}
				if ($varenr[$x]) {
					if ($incl_moms && !$momsfri[$x]) $tmp=dkdecimal($ialt+$ialt*$momssats/100);
					else $tmp=dkdecimal($ialt);
				} 
				print "<td align=right><span title= 'kostpris $dk_kostpris[$x] * db: $dk_db[$x] * dg: $dk_dg[$x]%'>".$tmp."</td>";
			}
			else print "<td><br></td>";
			print "<input type=hidden name=projekt[$x] value=\"$projekt[$x]\">";
			if ($vis_projekt && !$projekt[0]) {
				$r=db_fetch_array(db_select("select beskrivelse from grupper where art = 'PROJ' and kodenr='$projekt[$x]'",__FILE__ . " linje " . __LINE__));
				print "<td align=right title='$r[projekt]'>$projekt[$x]</td>";
			}
			if ($ko_ant[$x]>=1) {
				for ($y=1; $y<=$ko_ant[$x]; $y++) {
					$z=$y*10000+$x;
					$spantekst="K&oslash;bsordre&nbsp;$kobs_ordre_nr[$z] \n antal:&nbsp;$k_stk_ant[$z]&nbsp;&aacute;&nbsp;$kostpris[$z]";
					print "<td align=right onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$kobs_ordre_id[$z]','ordre' ,'left=10,top=10,width=800,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no');k_ordre.focus();\"onMouseOver=\"this.style.cursor = 'pointer'\"><span title='$spantekst'><img src=../ikoner/opslag.png></td>";
				}
			}
			else {print "<td><br></td>";}
			if ($serienr[$x]) {print "<td onClick=\"serienummer($linje_id[$x])\" onMouseOver=\"this.style.cursor = 'pointer'\" align=right><span title= 'Serienumre '><img alt=\"Serienummer\" src=../ikoner/serienr.png></td>";}
		}
#		$tmp=$momssum/100*$momssats; #ellers runder den ned ved v. 0,5 re ??
#		$moms=afrund($tmp,3);
		$kostpris[0]=$kostpris[0]*1;
		if ($submit=='del_ordre'||$submit=='Fakturer') db_modify("update ordrer set sum='$sum',kostpris='$kostpris[0]',moms='$moms' where id='$id'",__FILE__ . " linje " . __LINE__);
		if ($art=='DK') {
			$sum=$sum*-1;
			$momssum=$momssum*-1;		
		}
#		$tmp=$momssum/100*$momssats; #ellers runder den ned ved v. 0,5 ??
#		$moms=afrund($tmp,3);
		$ialt=$sum+$moms;
		print "<tr><td colspan=9><br></td></tr>\n";
		print "<tr><td colspan=7><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=100%><tbody>";
		print "<tr bgcolor=\"$bgcolor5\">";
		print "<td align=center>Nettosum</td><td align=center>".dkdecimal($sum)."</td>";
		print "<td align=center>D&aelig;kningsbidrag:&nbsp;".dkdecimal($dbsum)."</td>";
		if ($sum) $dg_sum=($dbsum*100/$sum);
		else $dg_sum=dkdecimal(0);
		print "<td align=center>D&aelig;kningsgrad:&nbsp;".dkdecimal($dg_sum)."%</td>";
		print "<td align=center>Moms</td><td align=center>".dkdecimal($moms)."</td>";
		print "<td align=center>I alt</td><td align=right>".dkdecimal($ialt)."</td>";
		print "</tbody></table></td></tr>\n";
		print "<tr><td align=center colspan=8>";
		print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>";
		if ($art!='DK') print "<td align=center><input type=submit value=\"&nbsp;Kopi&eacute;r&nbsp;\" name=\"submit\"></td>";
		if ($mail_fakt) $tmp="onclick=\"return confirm('Dokumentet sendes pr. mail til $email')\"";
		else $tmp="";
		 print "<td align=center><input type=submit value=\"&nbsp;Udskriv&nbsp;\" name=\"submit\" $tmp></td>";
		if (($art!='DK')&&(!$krediteret)) {
			$title="Klik her for at danne en kreditnota af denne faktura.Kreditnotaen kan redigeres inden bogf&oslash;ring.";
			print "<td align=\"center\" title=\"$title\"><input type=submit value=\"Kredit&eacute;r\" name=\"submit\"></td>";
		}
	}	else { ############################# ordren er ikke faktureret #################################
		#intiering af variabler
		$antal_ialt=0; #10.10.2007
		$leveres_ialt=0; #10.10.2007
		$tidl_lev_ialt=0; #10.10.2007

##### pile ########	tilfoejet 20080210	
		$alerttekst=findtekst(154,$sprog_id);
		$spantekst=findtekst(198,$sprog_id);
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\"><tbody>";
		if ($prev_id)	print "<tr><td width=50%><span title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$prev_id&returside=$returside','$alerttekst')\"><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td>";
		else print "<tr><td width=50%></td>";
		$spantekst=findtekst(199,$sprog_id);
		if ($next_id)	print "<td width=50% align=right><span title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$next_id&returside=$returside','$alerttekst')\"><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td></tr>";
		else print "<tr><td width=50%></td>";
		print "</tbody></tabel>";
##### pile ########		
		print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\"	valign = \"top\"><tbody>";
		$ordre_id=$id;
		print "<tr><td width=31%><table cellpadding=0 cellspacing=0 border=0>";
		print "<tr><td witdh=100>Kontonr.</td><td colspan=2>";
		if (trim($kontonr)) {print "<input class=\"inputbox\" readonly=readonly size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\"></td></tr>\n";}
		else {print "<input class=\"inputbox\" type=text size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\" onchange=\"javascript:docChange = true;\"></td></tr>\n";}
		print "<tr><td>Firmanavn</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=firmanavn onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$firmanavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Adresse</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=addr1 onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$addr1\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td></td><td colspan=2><input class=\"inputbox\" type=text size=25 name=addr2 onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$addr2\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Postnr. &amp; by</td><td><input class=\"inputbox\" type=text size=4 name=postnr onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$postnr\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=text size=19 name=bynavn onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Land</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=land onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$land\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Att.:</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=kontakt onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontakt\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Kunde_ordnr</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=kundeordnr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kundeordnr\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=38%><table cellpadding=0 cellspacing=0 border=0 width=250>";
		
		print "<tr><td>CVR-nr.</td><td><input class=\"inputbox\" type=text size=13 name=cvrnr value=\"$cvrnr\" onchange=\"javascript:docChange = true;\"></td>\n";
		print "<td>&nbsp;</td><td>EAN-nr.</td><td><input class=\"inputbox\" type=text size=13 name=ean value=\"$ean\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>E-mail</td><td><input class=\"inputbox\" type=text size=13 name=email value=\"$email\" onchange=\"javascript:docChange = true;\"></td>\n";
		print "<td></td><td>Institution</td><td colspan=2><input class=\"inputbox\" type=text size=13 name=institution value=\"$institution\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Udskriv til</td>"; 
		if ($mail_fakt) $udskriv_til="email";
		if ($oio_fakt) $udskriv_til="oioxml";
		if ($lev_pbs_nr) {
			if ($pbs == "FI") $udskriv_til="PBS_FI";
			elseif ($pbs == "BS") $udskriv_til="PBS_BS";
		}
		if (!$udskriv_til) $udskriv_til="PDF"; 	
		print "<td><select class=\"inputbox\" NAME=udskriv_til>";
		print "<option>$udskriv_til</option>";
		if ($udskriv_til!="PDF") print "<option>PDF</option>";
		if ($udskriv_til!="email") print "<option>email</option>";
		if ($udskriv_til!="oioxml") print "<option title=\"Kun ved fakturering/kreditering.\">oioxml</option>"; #PHR 20090803
		if ($lev_pbs_nr) {
			if ($udskriv_til!="PBS_FI") print "<option>PBS_FI</option>";
			$tmp=$pbs_nr*1;
			if ($tmp && $udskriv_til!="PBS_BS") print "<option title=\"Opkr&aelig;ves via PBS betalingsservice\">PBS_BS</option>";
		}
		print "</SELECT></td></tr>";
/*		
		print "<tr><td colspan=2>Send pr. mail&nbsp;</td><td><input class=\"inputbox\" type=checkbox name=mail_fakt onchange=\"javascript:docChange = true;\" $mail_fakt></td>\n";
		if ($lev_pbs_nr) {
			if ($pbs == "FI") $pbs_fi='checked';
			elseif ($pbs == "BS") $pbs_bs='checked';
			$title="PBS udsender FI indbetalingskort";
			if (!$pbs_bs) { #naeste linje ingen apostrof omkring $pbs_fi
				print "<td colspan=2 title=\"$title\">Faktura via PBS (FI)</td><td title=\"$title\"><input class=\"inputbox\" type=checkbox name=pbs_fi $pbs_fi onchange=\"javascript:docChange = true;\"></td></tr>\n";
				if ($pbs_nr && !$pbs_fi) print "<tr><td colspan=2><td>";
			}
			$title="Opkr&aelig;ves via PBS betalingsservice";
			if ($pbs_nr && !$pbs_fi) print "<td colspan=2 title=\"$title\">Opkr&aelig;v via PBS (BS)</td><td title=\"$title\"><input class=\"inputbox\" type=checkbox name=pbs_bs \"$pbs_bs\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		} else print "</tr>";	
*/		
		if (db_fetch_array(db_select("select distinct sprog from formularer where sprog != 'Dansk'",__FILE__ . " linje " . __LINE__))) {
			print "<tr><td><span title='Sprog som skal anvendes p&aring; tilbud,ordrer mm.'>Sprog</span></font></td>";
			print "<td><select class=\"inputbox\" NAME=sprog>";
			print "<option>$formularsprog</option>";
			$q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) print "<option>$r[sprog]</option>";
			print "</SELECT></td></tr>";
		} else print "</tr>";
		print "<tr><td colspan=5><hr></td><tr>";
		print "<tr><td width=20%>Ordredato</td><td colspan=2><input class=\"inputbox\" type=text size=13 name=ordredato value=\"$ordredato\" onchange=\"javascript:docChange = true;\"></td>\n";
		if ($hurtigfakt=='on') print "<td></td></tr>\n";
		else print "<td>Lev. dato</td><td colspan=2><input class=\"inputbox\" type=text size=13 name=levdato value=\"$levdato\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		if ($fakturadato||$status>0) {
			print "<tr><td>";
			if ($art=='DO') print "Fakt. dato"; 
			else print "KN. dato";
			print "</td><td colspan=2><input class=\"inputbox\" type=text size=13 name=fakturadato value=\"$fakturadato\" onchange=\"javascript:docChange = true;\"></td>\n";
			$tmp="Dette felt skal kun udfyldes,hvis der er tale om et abonnement eller \nlign som skal faktureres igen p&aring; et senere tidspunkt.\nSkriv datoen for n&aelig;ste fakturering";
			if ($art=='DO') print "<td width=20%><span title='$tmp'>Genfakt.</span></td><td colspan=2><input class=\"inputbox\" type=text size=13 name=genfakt value=\"$genfakt\" onchange=\"javascript:docChange = true;\"></td>\n";
		}
		print "</tr><tr><td>Betaling</td>";
		print "<td colspan=2><select class=\"inputbox\" NAME=betalingsbet>";
		print "<option>$betalingsbet</option>";
		if ($betalingsbet!='Forud') 	{print "<option>Forud</option>"; }
		if ($betalingsbet!='Kontant')	{print "<option>Kontant</option>"; }
		if ($betalingsbet!='Efterkrav')	{print "<option>Efterkrav</option>"; }
		if ($betalingsbet!='Netto'){print "<option>Netto</option>"; }
		if ($betalingsbet!='Lb. md.'){print "<option>Lb. md.</option>";}
		if (($betalingsbet=='Kontant')||($betalingsbet=='Efterkrav')||($betalingsbet=='Forud')) {$betalingsdage='';}
		elseif (!$betalingsdage) {$betalingsdage='Nul';}
		if ($betalingsdage)	{
			if ($betalingsdage=='Nul') {$betalingsdage=0;}
			print "</SELECT>+<input class=\"inputbox\" type=text size=1 style=text-align:right name=betalingsdage value=\"$betalingsdage\" onchange=\"javascript:docChange = true;\"></td>";
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
			print "<td>Valuta</td>";
			print "<td><select class=\"inputbox\" NAME=ny_valuta>";
			for ($x=0; $x<=$tmp; $x++) {
				if ($valuta!=$list[$x]) print "<option title=\"$beskriv[$x]\">$list[$x]</option>";
				else print "<option title=\"$beskriv[$x]\" selected=\"selected\">$list[$x]</option>";
			}
			print "</SELECT></td><td></td>";
		} else print "<tr><td colspan=2 width=200>";
		print "</tr>";
		$q = db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__);
		if ($r = db_fetch_array($q)) {
			$q2 = db_select("select navn from ansatte where konto_id = '$r[id]' and lukket != 'on' order by navn",__FILE__ . " linje " . __LINE__);
			$x=0;
			while ($r2 = db_fetch_array($q2)) {
				$x++;
				if ($x==1) {
					print "<tr><td>Vor ref.</td>";
					print "<td colspan=3><select class=\"inputbox\" NAME=ref>";
					print "<option>$ref</option>";
				}
				if ($ref!=$r2[navn]) print "<option> $r2[navn]</option>";
			}
			print "</SELECT>";
			if ($x) print "</td></tr>";
		}
		$list=array();
		$beskriv=array();
		$x=0;
		$q = db_select("select * from grupper where art = 'PRJ' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			$x++;
			$list[$x]=$r['kodenr'];
			$beskriv[$x]=$r['beskrivelse'];
		}
		$projektantal=$x;
		if ($x>0) {
			$vis_projekt='on';
			print "<td><span title= 'Hvis hele ordren skal registreres p&aring; et projekt,v&aelig;lges projektet her. Ellers anvendes projektfeltet p&aring; ordrelinjen';>Projekt</span></td>";
			print "<td><select class=\"inputbox\" NAME=projekt[0]>";
			for ($x=0; $x<=$projektantal; $x++) {
				if ($projekt[0]!=$list[$x]) print "<option title=\"$beskriv[$x]\">$list[$x]</option>";
				else print "<option title=\"$beskriv[$x]\" selected=\"selected\">$list[$x]</option>";
			}
			print "</SELECT></td></tr>";
		} else print "<tr><td colspan=2 width=200></tr>";
		
		if ($status==0&&$hurtigfakt!="on") print "<tr><td>Godkend</td><td><input class=\"inputbox\" type=checkbox name=godkend></td></tr>\n";
		elseif ($status<3&&$hurtigfakt!="on") {
			if ($restordre) $restordre="checked"; 
			else $restordre = "";
			print "<tr><td>Restordre</td><td><input class=\"inputbox\" type=checkbox name=restordre $restordre></td></tr>\n";
		}
		print "</tbody></table></td>";
		print "<td width=31%><table cellpadding=0 cellspacing=0 border=0 width=100% valign=\"top\">";
		if ($vis_lev_addr || !$kontonr) {
			print "<tr><td colspan=2 align=center><b>Leveringsadresse</b></td></tr>\n";
			print "<tr><td colspan=2><hr></b></tr>\n";
			print "<tr><td>Firmanavn</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=lev_navn value=\"$lev_navn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
			print "<tr><td>Adresse</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=lev_addr1 value=\"$lev_addr1\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
			print "<tr><td></td><td colspan=2><input class=\"inputbox\" type=text size=25 name=lev_addr2 value=\"$lev_addr2\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
			print "<tr><td>Postnr. &amp; by</td><td><input class=\"inputbox\" type=text size=4 name=lev_postnr value=\"$lev_postnr\"><input class=\"inputbox\" type=text size=18 name=lev_bynavn value=\"$lev_bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
			print "<tr><td>Att.:</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=lev_kontakt value=\"$lev_kontakt\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
			print "<tr><td colspan=2><hr><td></tr>\n";
			print "<tr><td colspan=2 align=right>Vis lev. adresse.<input type = \"checkbox\" name= \"vis_lev_addr\" checked><td></tr>\n";
			print "<input type=hidden name=\"felt_1\" size=\"25\" value=\"$felt_1\">";
			print "<input type=hidden name=\"felt_2\" size=\"25\" value=\"$felt_2\">";
			print "<input type=hidden name=\"felt_3\" size=\"25\" value=\"$felt_3\">";
			print "<input type=hidden name=\"felt_4\" size=\"25\" value=\"$felt_4\">";
			print "<input type=hidden name=\"felt_5\" size=\"25\" value=\"$felt_5\">";
		} else {
			print "<tr><td colspan=2 align=center><b>".findtekst(243,$sprog_id)."</b></tr>\n";
			print "<tr><td colspan=2><hr></b></tr>\n";
			if (substr(findtekst(244,$sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst(249,$sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(244,$sprog_id)."</span></td></td><td><input class=\"inputbox\" name=\"felt_1\" size=\"25\" value=\"$felt_1\"></td></tr>\n";
			if (substr(findtekst(245,$sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst(250,$sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(245,$sprog_id)."</span></td></td><td><input class=\"inputbox\" name=\"felt_2\" size=\"25\" value=\"$felt_2\"></td></tr>\n";
			if (substr(findtekst(246,$sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst(251,$sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(246,$sprog_id)."</span></td></td><td><input class=\"inputbox\" name=\"felt_3\" size=\"25\" value=\"$felt_3\"></td></tr>\n";
			if (substr(findtekst(247,$sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst(252,$sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(247,$sprog_id)."</span></td></td><td><input class=\"inputbox\" name=\"felt_4\" size=\"25\" value=\"$felt_4\"></td></tr>\n";
			if (substr(findtekst(248,$sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst(253,$sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(248,$sprog_id)."</span></td></td><td><input class=\"inputbox\" name=\"felt_5\" size=\"25\" value=\"$felt_5\"></td></tr>\n";
			print "<tr><td colspan=2><hr><td></tr>\n";
			print "<tr><td colspan=2 align=right>Vis lev. adresse.<input type = \"checkbox\" name= \"vis_lev_addr\"><td></tr>\n";
			print "<input type=hidden name=lev_navn value=\"$lev_navn\">";
			print "<input type=hidden name=lev_addr1 value=\"$lev_addr1\"><input type=hidden name=lev_addr2 value=\"$lev_addr2\">";
			print "<input type=hidden name=lev_postnr value=\"$lev_postnr\"><input type=hidden name=lev_bynavn value=\"$lev_bynavn\">";
			print "<input type=hidden name=lev_kontakt value=\"$lev_kontakt\">";
		}
#	 print "<tr><td><textarea style=\"font-family: helvetica,arial,sans-serif;\" name=lev_adr rows=5 cols=35>$lev_adr</textarea></td></tr>\n";
		print "</td></tr></tbody></table></td>";
		print "</td></tr><tr><td align=center colspan=3><table cellpadding=0 cellspacing=0><tbody>";
		$query = db_select("select notes from adresser where kontonr = '$kontonr'",__FILE__ . " linje " . __LINE__);
		if ($row2 = db_fetch_array($query)) {
			$notes=str_replace("\n","<br>",$row2['notes']);
			print "<tr><td colspan=9 witdh=100%> <span style='color: rgb(255,0,0);'>$notes</td></tr><tr><td colspan=9 witdh=100%><hr></td></tr>\n";
		}
		if ($kontonr) {
			print "<td align=center>pos</td><td align=center>varenr</td><td align=center>antal enhed</td><td align=center>beskrivelse</td><td align=center>pris</td><td align=center>%</td><td align=center>i alt</td>";
			if ($vis_projekt && !$projekt[0]) print "<td align=center>Proj.</td>";
			if ($status>1 && $hurtigfakt!='on')  {
				if ($art=='DO') $tmp="lev&egrave;r";
				else $tmp="modtag";
				print "<td colspan=2 align=center>$tmp</td><td></td>";
			}
			print "</tr>\n";
		}
		if (!$status) $status=0;
		print "<input type=hidden name=status value=$status>";
		print "<input type=hidden name=id value=$id>";

		$x=0;
		if (!$ordre_id) $ordre_id=0;
		$kostpris[0]=0;
		
#		db_modify("update ordrer set valutakurs='$valutakurs' where ordre_id = '$ordre_id'");
#		global $db;
		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (($row['posnr']>0)&&($row['samlevare']<1)) {  #Hvis "samlevare" er numerisk,indgaar varen i den ordrelinje,der refereres til - hvis "on" er varen en samlevare.
				$x++;
				$linje_id[$x]=$row['id'];
				$kred_linje_id[$x]=$row['kred_linje_id'];
				$posnr[$x]=$row['posnr'];
				$varenr[$x]=stripslashes(htmlentities(trim($row['varenr']),ENT_COMPAT,$charset));
				$beskrivelse[$x]=stripslashes(htmlentities(trim($row['beskrivelse']),ENT_COMPAT,$charset));
				$enhed[$x]=stripslashes(htmlentities(trim($row['enhed']),ENT_COMPAT,$charset));
				$pris[$x]=$row['pris'];
				$rabat[$x]=$row['rabat'];
				$antal[$x]=$row['antal'];
				$leveres[$x]=$row['leveres'];
				$vare_id[$x]=$row['vare_id'];
				$momsfri[$x]=$row['momsfri'];
				$rabatgruppe[$x]=$row['rabatgruppe'];
				$m_rabat[$x]=$row['m_rabat']*-1;
				$varemomssats[$x]=$row['momssats']*1;
				if (!$momsfri[$x] && !$varemomssats[$x]) $varemomssats[$x]=$momssats;
				elseif ($varemomssats[$x] > $momssats) $varemomssats[$x]=$momssats;
				elseif ($momsfri[$x]) $varemomssats[$x]=0;
				$serienr[$x]=stripslashes(htmlentities(trim($row['serienr']),ENT_COMPAT,$charset));
				$samlevare[$x]=$row['samlevare'];
				$projekt[$x]=$row['projekt']*1;
				if ($vare_id[$x]) {
					$q2 = db_select("select kostpris,provisionsfri from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
					$row2 = db_fetch_array($q2);
					if ($row2['provisionsfri']=='on') {
						$kostpris[0]=$kostpris[0]+$pris[$x];
						$db[$x]=0;
					}
					else {
						$kostpris[$x]=$row2['kostpris']*100/$valutakurs;
						$kostpris[0]=$kostpris[0]+$kostpris[$x];
						$db[$x]=$pris[$x]-$kostpris[$x];
					} 
					if ($pris[$x]!=0) $dg[$x]=$db[$x]*100/$pris[$x];
					else $dg[$x]=0;
					$dk_db[$x]=dkdecimal($db[$x]);
					$dk_dg[$x]=dkdecimal($dg[$x]);
				}
				if (($art=='DK')&&($antal[$x]<0)){$bogfor==0;}
			}
		}
		$linjeantal=$x;
		
		$sum=0;
		for ($x=1; $x<=$linjeantal; $x++) {
			if ($varenr[$x])	{
				$ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				$ialt=afrund($ialt,3);
				$sum=$sum+$ialt;
				$dkpris=dkdecimal($pris[$x]);
				$dkrabat=dkdecimal($rabat[$x]);
				if ($momsfri[$x]!='on') {
					$moms+=afrund($ialt*$varemomssats[$x]/100,2);
				  if($incl_moms)$dkpris=dkdecimal($pris[$x]+$pris[$x]*$varemomssats[$x]/100);
				}
				if ($antal[$x]) {
					if ($art=='DK') {$dkantal[$x]=dkdecimal($antal[$x]*-1);}
					else {$dkantal[$x]=dkdecimal($antal[$x]);}
					if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-1);}
					if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-2);}
				}
			}
			else {$antal[$x]=''; $dkpris=''; $dkrabat=''; $ialt='';}
			print "<input type=hidden name=linjeantal value=$linjeantal>";
			print "<input type=hidden name=momssats value=$momssats>";
			print "<input type=hidden name=linje_id[$x] value=$linje_id[$x]>";
			print "<input type=hidden name=kred_linje_id[$x] value=$kred_linje_id[$x]>";
			print "<input type=hidden name=vare_id[$x] value=$vare_id[$x]>";
			print "<input type=hidden name=antal[$x] value=$antal[$x]>";
			print "<input type=hidden name=serienr[$x] value=$serienr[$x]>";
			print "<input type=hidden name=momsfri[$x] value=$momsfri[$x]>";
			print "<tr>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=3 name=posn$x value=$x></td>";
			print "<td><input class=\"inputbox\" readonly=readonly size=12 name=vare$x onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$varenr[$x]\" onchange=\"javascript:docChange = true;\"></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=3 name=dkan$x value=$dkantal[$x]><input class=\"inputbox\" readonly=readonly size=3 value=\"$enhed[$x]\" onchange=\"javascript:docChange = true;\"></td>";
			$title=var2str($beskrivelse[$x],$id);
			print "<td title=\"$title\"><input class=\"inputbox\" type=text size=60 name=beskrivelse$x value=\"$beskrivelse[$x]\" onchange=\"javascript:docChange = true;\"></td>";
			print "<td><span title= 'db: $dk_db[$x] - dg: $dk_dg[$x]%'><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=10 name=pris$x value=\"$dkpris\" onchange=\"javascript:docChange = true;\"></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=4 name=raba$x value=\"$dkrabat\" onchange=\"javascript:docChange = true;\"></td>";
			if ($rabat[$x]) {$db[$x]=$db[$x]-($pris[$x]/100*$rabat[$x]);}
			$db[$x]=$db[$x]*$antal[$x];
			if ($ialt!=0) {
				$dg[$x]=$db[$x]*100/$ialt;
			}
			else {$dg[$x]=0;}
			$dbsum=$dbsum+$db[$x];
			$dk_db[$x]=dkdecimal($db[$x]);
			$dk_dg[$x]=dkdecimal($dg[$x]);
#	if ($ialt) {
				if ($art=='DK') $ialt=$ialt*-1;
				if ($varenr[$x]) {
					if ($incl_moms && !$momsfri[$x]) $tmp=dkdecimal($ialt+$ialt*$varemomssats[$x]/100);
					else $tmp=dkdecimal($ialt);
				} 
				else $tmp=NULL;
			print "<td align=right><span title= 'db: $dk_db[$x] - dg: $dk_dg[$x]%'><input class=\"inputbox\" readonly=\"readonly\" style=\"text-align:right\" size=10 value=\"$tmp\"></td>";
			if ($vis_projekt && !$projekt[0]) { 
				print "<td><select class=\"inputbox\" NAME=\"projekt[$x]\">";
				for ($a=0; $a<=$projektantal; $a++) {
					if ($projekt[$x]!=$list[$a]) print "<option  value=\"$list[$a]\" title=\"$beskriv[$a]\">$list[$a]</option>";
					else print "<option value=\"$list[$a]\" title=\"$beskriv[$a]\" selected=\"selected\">$list[$a]</option>";
				}
			}
#		 	}			
#			else print "<td></td>";
			if ($status>=1&&$hurtigfakt!='on') {
				if ($vare_id[$x]){
					$batch="?";
#					print "<td><span title= 'kostpris';>Projekt</span></td>";
					$tidl_lev[$x]=0;
					$query = db_select("select gruppe,beholdning from varer where id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					$beholdning[$x]=$row['beholdning'];
					$query = db_select("select box9 from grupper where art='VG' and kodenr='$row[gruppe]'",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					if ($row['box9']=='on'){$batchvare[$x]=1;}

					if ($antal[$x]>0) {
						$query = db_select("select * from batch_salg where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
						while($row = db_fetch_array($query)) {
							$y++;
							$batch='V';
							$tidl_lev[$x]=$tidl_lev[$x]+$row['antal'];
						}
						if ($batchvare[$x]) { 
							$z=0;							
							$query = db_select("select * from reservation where vare_id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
							while ($row = db_fetch_array($query))	{
							 if (($row[linje_id]==$linje_id[$x])||($row[batch_salg_id]==$linje_id[$x]*-1)) {
									$z=$z+$row[antal];
									$batch="V";
								}
								elseif ($row[batch_kob_id]<0) {$reserveret[$x]=$reserveret[$x]+$row[antal];}
								elseif ($row[batch_salg_id]==0) {$paavej[$x]=$paavej[$x]+$row[antal];}
							}
							if($z+$tidl_lev[$x]<$antal[$x]) {$batch="?";}
						}
						else {$batch="";}
						if (($tidl_lev[$x]<$antal[$x])||($batch=="?")) {$status=1;}
					}
					if ($antal[$x]<0) {
						$tidl_lev[$x]=0;
						if ($art=="DK"||$kred_linje_id[$x]>0||!$batchvare[$x]) { #20071004 (!$batchvare[$x] 20071102)
							$query = db_select("select * from batch_salg where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
							while($row = db_fetch_array($query)){$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];}
							if ($tidl_lev[$x]!=$antal[$x]) $status=1;
							if ($art=='DK' && $leveres[$x]>$tidl_lev[$x]+$antal[$x]) $leveres[$x]=$antal[$x]-$tidl_lev[$x];
							elseif ($leveres[$x]<$antal[$x]-$tidl_lev[$x]) $leveres[$x]=$antal[$x]-$tidl_lev[$x]; #20071004
						} elseif ($kred_linje_id[$x]<0) {
						#hvis $kred_linje_id[$x]<0 tages en vare retur som ikke er blevet solgt til kunden. Denne behandles derfor som et varekob.
						$query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]' and ordre_id=$id",__FILE__ . " linje " . __LINE__); #20071004
							while($row = db_fetch_array($query)) $tidl_lev[$x]=$tidl_lev[$x]-$row[antal];
							if ($antal[$x]>$tidl_lev[$x]+$leveres[$x]) $leveres[$x]=$antal[$x]+$tidl_lev[$x];
						}
						$query = db_select("select * from reservation where linje_id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
						if (($row = db_fetch_array($query))&&($beholdning[$x]>=0)) {
							if ($antal[$x]+$tidl_lev[$x]!=$row[antal]) db_modify ("update reservation set antal=$antal[$x]*-1 where linje_id=$linje_id[$x] and batch_salg_id=0",__FILE__ . " linje " . __LINE__);
						}
						elseif ($antal[$x]-$tidl_lev[$x]!=0) db_modify("insert into reservation (linje_id,vare_id,batch_salg_id,antal) values ($linje_id[$x],$vare_id[$x],0,$antal[$x]*-1)",__FILE__ . " linje " . __LINE__);
					} 
					elseif ($leveres[$x]+$tidl_lev[$x]>$antal[$x]) $leveres[$x]=$antal[$x]-$tidl_lev[$x];
					if ($art=='DK') $dklev[$x]=dkdecimal($leveres[$x]*-1);
					else $dklev[$x]=dkdecimal($leveres[$x]);
					if (substr($dklev[$x],-1)=='0'){$dklev[$x]=substr($dklev[$x],0,-1);}
					if (substr($dklev[$x],-1)=='0'){$dklev[$x]=substr($dklev[$x],0,-2);}
					if ($art=='DK') $dk_tidl_lev[$x]=dkdecimal($tidl_lev[$x]*-1);
					else $dk_tidl_lev[$x]=dkdecimal($tidl_lev[$x]);
					if (substr($dk_tidl_lev[$x],-1)=='0') $dk_tidl_lev[$x]=substr($dk_tidl_lev[$x],0,-1);
					if (substr($dk_tidl_lev[$x],-1)=='0') $dk_tidl_lev[$x]=substr($dk_tidl_lev[$x],0,-2);
					print "<input type=\"hidden\" name=tidl_lev[$x] value=\"$dk_tidl_lev[$x]\">";
					$temp=$beholdning[$x]-$reserveret[$x];
					$status=2;
					if (abs($antal[$x])!=abs($tidl_lev[$x])) {
						print "<td><span title= 'Beholdning: $beholdning[$x]'><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=2 name=leve$x value=\"$dklev[$x]\" onchange=\"javascript:docChange = true;\"></td>";
						print "<td>($dk_tidl_lev[$x])</td>";
						if ($batchvare[$x]) print "<td align=center onClick=\"batch($linje_id[$x])\"><span title= 'V&aelig;lg fra k&oslash;bsordre'><img alt=\"Serienummer\" src=../ikoner/serienr.png></td></td>";
						$levdiff=1;
					} else {
						print "<td><span title= 'Beholdning: $beholdning[$x]'><input class=\"inputbox\" readonly=readonly style=text-align:right size=2 name=leve$x value=\"$dklev[$x]\" onchange=\"javascript:docChange = true;\"></td>";
						print "<td>($dk_tidl_lev[$x])</td>";
					}
					db_modify("update ordrelinjer set leveret=$tidl_lev[$x] where id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
				}
			}
			if ($samlevare[$x]=='on') print "<td align=center onClick=\"stykliste($vare_id[$x])\"><span title= 'Vis stykliste'><img alt=\"Stykliste\" src=../ikoner/stykliste.png></td></td>";
			if ($m_rabat[$x] && !$rabatgruppe[$x]) {
				print "</tr><tr>";
				print "<td><input class=\"inputbox\" readonly=readonly style=\"text-align:right\" size=3 value=$x></td>";
				print "<td><input class=\"inputbox\" readonly=readonly size=12 value=\"\"></td>";
				print "<td><input class=\"inputbox\" readonly=readonly style=\"text-align:right\" size=3 value=$dkantal[$x]><input class=\"inputbox\" readonly=readonly size=3 value=\"$enhed[$x]\"></td>";
				$title=var2str($beskrivelse[$x],$id);
				print "<td title=\"$title\"><input class=\"inputbox\" readonly=readonly size=60 value=\"RABAT\"></td>";
				print "<td><input class=\"inputbox\" readonly=readonly style=\"text-align:right\" size=10 value=\"".dkdecimal($m_rabat[$x])."\"></td>";
				print "<td><input class=\"inputbox\" readonly=readonly style=\"text-align:right\" size=4 value=\"\" onchange=\"javascript:docChange = true;\"></td>";
				print "<td><input class=\"inputbox\" readonly=readonly style=\"text-align:right\" size=10 value=\"".dkdecimal($m_rabat[$x]*$antal[$x])."\"></td>";
			}
			print "</tr>\n";
			$antal_ialt=$antal_ialt+$antal[$x]; #10.10.2007
			$leveres_ialt=$leveres_ialt+abs($leveres[$x]); #abs tilfoejet 2009.01.26 grundet manglende lev_mulighed med ens antal positive og negative leveringer i ordre 98 i saldi_104
			$tidl_lev_ialt=$tidl_lev_ialt+$tidl_lev[$x]; #10.10.2007
		}
		if ($status>=1&&$bogfor!=0 && !$leveres_ialt && $tidl_lev_ialt && $antal_ialt != $tidl_lev_ialt) $del_ordre = 'on';
		else $del_ordre = '';
		if ($kontonr) {
			$x++;
			$posnr[0]=$linjeantal+1;
			print "<tr>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=3 name=posn0 value=$posnr[0]></td>";
			if ($art=='DK') {print "<td><input class=\"inputbox\" readonly=readonly size=12 name=vare0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";}
			else {print "<td><input class=\"inputbox\" type=\"text\" size=12 name=vare0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";}
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=3 name=anta0><input class=\"inputbox\" readonly=\"readonly\" size=3></td>";
			print "<td><input class=\"inputbox\" type=text size=60 name=beskrivelse0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=10 name=pris0></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=4 name=raba0></td>";
			print "<td><input class=\"inputbox\" readonly=\"readonly\" size=10></td>";
			print "<td></td>";
			print "</tr>\n";
			print "<input type=hidden size=3 name=sum value=$sum>";
#			$tmp=$momssum/100*$momssats; 
#			$moms=afrund($tmp,3);
			$moms=afrund($moms*1,3);
			$kostpris[0]=$kostpris[0]*1;
			db_modify("update ordrer set sum=$sum,kostpris=$kostpris[0],moms=$moms where id=$id",__FILE__ . " linje " . __LINE__);
			if ($art=='DK') {
				$sum=$sum*-1;
				$moms=$moms*-1;
			}
			$ialt=($sum+$moms);
			print "<tr><td colspan=7><table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" width=100%><tbody>";
			print "<tr>";
			print "<td align=center>Nettosum:&nbsp;".dkdecimal($sum)."</td>";
			$db=$dbsum;
			print "<td align=center>D&aelig;kningsbidrag:&nbsp;".dkdecimal($db)."</td>";
			if ($sum) {
				$dg_sum=($dbsum*100/$sum);}
			else {$dg_sum=dkdecimal(0);}
			print "<td align=center>D&aelig;kningsgrad:&nbsp;".dkdecimal($dg_sum)."%</td>";
			print "<td align=center>Moms:&nbsp;".dkdecimal($moms)."</td>";
			print "<td align=center>I alt:&nbsp;".dkdecimal($ialt)."</td>";
		}
		print "</tbody></table></td></tr>\n";
		print "<input type=\"hidden\" name=\"fokus\">";
		print "<tr><td align=center colspan=8>";
		print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tbody><tr>";
		if ($status < 3) {
			if ($levdiff) $status=1;
			if ($status<1) $width="33%";
			elseif ($sum!=0) $width="25%";
			if ($hurtigfakt=='on' && $fakturadato) print "<input type=hidden name=levdato value=$fakturadato>";
			print "<input type=hidden name=valutakurs value=$valutakurs>";
			print "<input type=hidden name=status value=$status>";
			print "<td align=center width=$width><input type=submit accesskey=\"g\" value=\"&nbsp;&nbsp;Gem&nbsp;&nbsp;\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";
			print "<td align=center width=$width><input type=submit accesskey=\"o\" value=\"Opslag\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";
			if ($status==1&&$bogfor!=0 && $hurtigfakt!='on' && $leveres_ialt) {
				if ($art== 'DO') print "<td align=center width=$width><input type=submit accesskey=\"l\" value=\"&nbsp;Lev&eacute;r&nbsp;\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";
				else print "<td align=center width=$width title=\"Klik her for at tage varer retur\"><input type=submit accesskey=\"l\" value=\"&nbsp;Modtag\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";
			}
			if (($status==2&&$bogfor!=0)||($status>0&&$hurtigfakt=='on')) {
				if ($art=='DO') {
					if ($mail_fakt) $tmp="onclick=\"return confirm('Faktura sendes pr. mail til $email')\"";
					else $tmp="";
					print "<td align=center width=$width><input type=submit accesskey=\"f\" value=\"Faktur&eacute;r\" name=\"submit\" $tmp></td>";
				} else {
					if ($mail_fakt) $tmp="onclick=\"return confirm('Kreditnota sendes pr. mail til $email')\"";
					else $tmp="";
					print "<td align=center width=$width><input type=submit accesskey=\"f\" value=\"Kredit&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";
				}
			} elseif ($del_ordre == 'on') {
				$txt="Klik her for at opdele ordren i 2.<br>Den ene vil indeholde ikke leverede varer<br>Den anden vil indeholde leverede varer"; 
				print "<td align=\"center\" width=\"$width\" >
					<span onmouseover=\"return overlib('$txt',WIDTH=800);\" onmouseout=\"return nd();\">	
					<input type=submit accesskey=\"f\" value=\"Del ordre\" name=\"submit\" onclick=\"javascript:docChange = false;\"></span></td>";
			}			
			if (($linjeantal>0)&&($art=='DO')) {
				if ($mail_fakt && $hurtigfakt && $status < 3) $tmp="onclick=\"return confirm('Ordrebekr&aelig;ftelse sendes pr mail til $email')\"";
				elseif ($mail_fakt && $status < 2) $tmp="onclick=\"return confirm('Ordrebekr&aelig;ftelse sendes pr. mail til $email')\"";
				else $tmp="";
				print "<td align=center width=$width><input type=submit value=\"&nbsp;Udskriv&nbsp;\" name=\"submit\" $tmp title=\"$tekst2\" onclick=\"javascript:docChange = false;\"></td>";
			}
			$tekst=findtekst(155,$sprog_id); $tekst2=findtekst(156,$sprog_id);
			if (($status<1 || $linjeantal==0) && $id) print "<td align=center><input type=submit value=\"&nbsp;&nbsp;Slet&nbsp;&nbsp;\" name=\"submit\" onclick=\"return confirm('$tekst')\" title=\"$tekst2\"></td>";
			print "</tbody></table></td></tr>\n";
			print "</form>";
			print "</tbody></table></td></tr></tbody></table></td></tr>\n";
			print "<tr><td></td></tr>\n";
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
					} elseif ($valuta) print "<BODY onLoad=\"javascript:alert('Ingen valutakurs for faktura $r[faktnr]')\">";	
					}
				elseif ($valuta!='DKK' && $r['valuta']!='DKK' && $r['valuta']!=$valuta) {
					$tmp==$r['amount']*$r['valuta']/100;
		 			$opp_amount=$tmp*100/$r['valutakurs'];
				}	else $opp_amount=$r['amount'];
				$tilgode=$tilgode+$opp_amount;
			}
			if ($kreditmax<$ialt+$tilgode) {
				$tmp=	dkdecimal(($ialt+$tilgode)-$kreditmax,2);
				print "<BODY onLoad=\"javascript:alert('Kreditmax overskrides med $valuta $tmp')\">";
			}
		}# end  if ($kreditmax....
	}# end else for (if ($status>=3)) 
} # end function ordreside
function find_vare_id ($varenr)
{
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
			$batch_kob_id[$x]=$row['id'];
			$kobsdate[$x]=$row['kobsdate'];
			$rest[$x]=$row['rest'];
			$reserveret[$x]=0;
#			$pris[$x]=$row[pris];
			$q2 = db_select("select ordrenr from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
			$r2 = db_fetch_array($q2);
			$ordrenr[$x]=$r2[ordrenr];
			if ($rest[$x]>=$lev_rest) {
				$valg[$x]=$lev_rest;
				$lev_rest=0;
			}	
			else {
				$valg[$x]=$rest[$x];
				$lev_rest=$lev_rest-$rest[$x];
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
			 if (($valg[$x]>0)&&(!$res_linje_id[$x])) {db_modify("insert into reservation (linje_id,vare_id,batch_kob_id,antal,lager) values ($linje_id,$vare_id,$batch_kob_id[$x],$valg[$x],$lager)",__FILE__ . " linje " . __LINE__);}
			 elseif (($valg[$x]>0)&&($res_linje_id[$x])) {db_modify("insert into reservation (linje_id,vare_id,batch_salg_id,antal,lager) values ($res_linje_id[$x],$vare_id,$temp,$valg[$x],$lager)",__FILE__ . " linje " . __LINE__);}
		 } 
	}	
}
*/
##############################################################################
function indsaet_linjer($ordre_id,$linje_id,$posnr)
 {
	$posnr = str_replace('+',':',$posnr); #jeg ved ikke hvorfor,men den vil ikke splitte med "+"
	list ($posnr,$antal) = split (':',$posnr);
	db_modify("update ordrelinjer set posnr='$posnr' where id='$linje_id'",__FILE__ . " linje " . __LINE__);
	for ($x=1; $x<=$antal; $x++) {
		db_modify("insert into ordrelinjer (posnr,ordre_id) values ('$posnr','$ordre_id')",__FILE__ . " linje " . __LINE__);
	}
}
##############################################################################
function find_nextfakt($fakturadate,$nextfakt) 
{
// Denne funktion finder diff mellem fakturadate & nextfakt,tillï¿œgger diff til nextfakt og returnerer denne vaerdi. Hvis baade 
// fakturadate og netffaxt er sidste dag i de respektive maaneder vaelges ogsï¿œ sidste dag i maaned i returvaerdien.

list($faktaar,$faktmd,$faktdag) = split("-",$fakturadate);
list($nextfaktaar,$nextfaktmd,$nextfaktdag) = split("-",$nextfakt);
	
if (!checkdate($faktmd,$faktdag,$faktaar)) {
	echo "Fakturadato er ikke en gyldig dato<br>";
	exit;
}
if (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) {
	echo "next Fakturadato er ikke en gyldig dato<br>";
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
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
<!--  -->
