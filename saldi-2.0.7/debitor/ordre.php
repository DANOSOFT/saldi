<?php
// ----------debitor/ordre.php---------lap 2.0.7------2009-05-17--------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$antal=NULL;$beskrivelse=NULL;$enhed=NULL;
$fakturadate=NULL;$fakturadato=NULL;$firmanavn=NULL;$genfakt=NULL;$gl_id=NULL;$levdate=NULL;$modtaget=NULL;$nextfakt=NULL;$notes=NULL;
$pris=NULL;$reserveret=NULL;$sletslut=NULL;$sletstart=NULL;$varenr=NULL;

# Javascript skalflyttes i selvstaendig fil.
?>
<script type="text/javascript">
	<!--
	var linje_id=0;
	var vare_id=0;
	var antal=0;
	function serienummer(linje_id, antal){
		window.open("serienummer.php?linje_id="+ linje_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
	}
	function batch(linje_id, antal){
		window.open("batch.php?linje_id="+ linje_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
	}
	function stykliste(vare_id){
		window.open("../lager/fuld_stykliste.php?id="+ vare_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
	}
	//-->
</script>
<?php
$modulnr=5;
$title="Kundeordre";
$css="../css/standard.css";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/var2str.php");

print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";
$tidspkt=date("U");
	
$returside=if_isset($_GET['returside']);
if ($popup) $returside="../includes/luk.php";

if ($tjek=if_isset($_GET['tjek'])){
	$query = db_select("select tidspkt, hvem from ordrer where status < 3 and id = $tjek and hvem != '$brugernavn'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query))	{
		if ($tidspkt-($row['tidspkt'])<3600){
			print "<BODY onLoad=\"javascript:alert('Ordren er i brug af $row[hvem]')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
		}
		else {
		db_modify("update ordrer set hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$tjek'",__FILE__ . " linje " . __LINE__);}
	}
}
	
$q = db_SELECT("select box1, box4, box9 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__);
$r=db_fetch_array($q); 
$incl_moms=$r['box1'];
$hurtigfakt=$r['box4'];
$negativt_lager=$r['box9'];

$id=if_isset($_GET['id']);
$sort=if_isset($_GET['sort']);
$fokus=if_isset($_GET['fokus']);
$submit=if_isset($_GET['funktion']);
$vis_kost=if_isset($_GET['vis_kost']);
$bogfor=1;

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
		$r = db_fetch_array(db_select("select box1, box3, box4, box6 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
		$tmp= substr($r[box1],1,1);
		$rabatsats=$r['box6']*1;
		$fakt_sprog=$r['box4'];
		$valuta=$r['box3'];
		$r = db_fetch_array(db_select("select box2 from grupper where art='SM' and kodenr='$tmp'",__FILE__ . " linje " . __LINE__));
		$momssats=$r[box2];
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
if ((!$id)&&($firmanavn)) {
	$query = db_select("select ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) $ordrenr=$row[ordrenr]+1;
	else {$ordrenr=1;}
	$ordredate=date("Y-m-d");
	db_modify("insert into ordrer (ordrenr, konto_id, kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,betalingsdage,betalingsbet,cvrnr,ean,institution,email,mail_fakt,notes,art,ordredate,momssats,hvem,tidspkt,ref,valuta,sprog,kontakt,pbs) values ($ordrenr,'$konto_id','$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$email','$mail_fakt','$notes','DO','$ordredate','$momssats','$brugernavn','$tidspkt','$ref','$valuta','$fakt_sprog','$kontakt','$pbs')",__FILE__ . " linje " . __LINE__);
	$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {$id=$row[id];}
}
elseif($firmanavn) {
	$query = db_select("select tidspkt, firmanavn from ordrer where id=$id and hvem='$brugernavn'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		if (!$row['firmanavn']) { # <- 2009.05.13 Eller overskrives v. kontaktopslag.
			db_modify("update ordrer set kontonr='$kontonr', kundeordnr='$kundeordnr', firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', lev_navn='$lev_navn', lev_addr1='$lev_addr1',	lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', ean='$ean', institution='$institution',email='$email',mail_fakt='$mail_fakt', notes='$notes', hvem = '$brugernavn', tidspkt='$tidspkt',pbs='$pbs' where id=$id",__FILE__ . " linje " . __LINE__);
	 	}
	} else {			
		$query = db_select("select hvem from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Ordren er overtaget af $row[hvem]')\">";}
		else {print "<BODY onLoad=\"javascript:alert('Du er blevet smidt af')\">";}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
	}	
}

if (isset($_GET['vare_id'])) {
	$query = db_select("select grupper.box6 as box6, ordrer.valuta as valuta, ordrer.ordredate as ordredate, ordrer.status as status from ordrer, adresser, grupper where ordrer.id='$id' and adresser.id=ordrer.konto_id and grupper.art='DG' and ".nr_cast("grupper.kodenr")."=adresser.gruppe",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	if ($row['status']>2) {
		print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
		exit;
	} else {
		$rabatsats=$row['box6'];
		$valuta=$row['valuta'];
		$ordredate=$row['ordredate'];
		$vare_id=$_GET['vare_id'];
		$linjenr=substr($fokus,4);
		$query = db_select("select posnr from ordrelinjer where ordre_id = '$id' order by posnr desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $posnr=$row['posnr']+1;
		else $posnr=1;

		$query = db_select("select * from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			if (!$varenr){$varenr=trim($row['varenr']);}
			if (!$beskrivelse){$beskrivelse=addslashes(trim($row['beskrivelse']));}
			if (!$enhed){$enhed=trim($row['enhed']);}
			if (!$pris){$pris=$row['salgspris']*1;}
			$serienr=$row['serienr'];
			$samlevare=$row['samlevare'];
		}
		if ($r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$row[gruppe]'",__FILE__ . " linje " . __LINE__))) {
			$momsfri = $r2['box7'];
			if (($r2['box6']!=NULL)&&($rabatsats>$r2['box6'])) $rabatsats=$r2['box6'];
		}
		if(!$antal) $antal=1;
		$rabatsats=$rabatsats*1;
		if ($valuta && $valuta!='DKK') {
			if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=".nr_cast('grupper.kodenr')." and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
				$pris=round($pris*100/$r['kurs']+0.0001,3);
			} else {
				$tmp = dkdato($ordredate);
				print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
			}
		}
		if ($linjenr==0) {
			db_modify("insert into ordrelinjer (vare_id, antal, ordre_id, posnr, varenr, beskrivelse, enhed, pris, rabat, serienr, momsfri, samlevare) values ('$vare_id', '$antal', '$id','$posnr','$varenr','$beskrivelse','$enhed','$pris','$rabatsats','$serienr','$momsfri','$samlevare')",__FILE__ . " linje " . __LINE__);
		}
	}
}
if (isset($_POST['submit'])) {
	$fokus=if_isset($_POST['fokus']);
	$submit = $_POST['submit'];
	if (strstr($submit, "Faktur")) $submit="Fakturer";
	if (strstr($submit, "Delfaktur")) $submit="Fakturer";
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
	$ordredate = usdate(if_isset($_POST['ordredato']));
	$levdato = trim($_POST['levdato']);
	$genfakt = trim(if_isset($_POST['genfakt']));
	$fakturadato = trim(if_isset($_POST['fakturadato']));
	$cvrnr = addslashes(trim($_POST['cvrnr']));
	$ean = addslashes(trim($_POST['ean']));
	$institution = addslashes(trim($_POST['institution']));
	$email = addslashes(trim($_POST['email']));
	if (strpos($email,"@") && strpos($email,".") && strlen($email)>5) $mail_fakt = $_POST['mail_fakt'];
	elseif($_POST['mail_fakt'])	print "<BODY onLoad=\"javascript:alert('e-mail ikke gyldig')\">";
		$betalingsbet = $_POST['betalingsbet'];
	$betalingsdage = $_POST['betalingsdage']*1;
	$valuta = $_POST['valuta'];
	$projekt = if_isset($_POST['projekt']);
	$fakt_sprog = if_isset($_POST['sprog']);
	$lev_adr = trim(if_isset($_POST['lev_adr']));
	$sum=if_isset($_POST['sum']);
	$linjeantal = $_POST['linjeantal'];
	$linje_id = $_POST['linje_id'];
	$kred_linje_id = if_isset($_POST['kred_linje_id']);
	$posnr = if_isset($_POST['posnr']);
	$status = $_POST['status'];
	$godkend = if_isset($_POST['godkend']);
	$omdan_t_fakt = if_isset($_POST['omdan_t_fakt']);
	$kreditnota = if_isset($_POST['kreditnota']);
	$ref = trim($_POST['ref']);
	$fakturanr = trim(if_isset($_POST['fakturanr']));
	$momssats = trim($_POST['momssats']);
	if ($_POST['pbs_fi']) $pbs="FI";
	elseif ($_POST['pbs_bs']) $pbs="BS";
	$enhed = if_isset($_POST['enhed']);
	$vare_id = $_POST['vare_id'];
	$serienr = if_isset($_POST['serienr']);
	$momsfri = if_isset($_POST['momsfri']);
	$tidl_lev = if_isset($_POST['tidl_lev']);
	
	if (strstr($submit, "Kred") && $status < 3) $submit="Fakturer";
	if (strstr($submit,'Modtag')) $submit="Lever";
	
	$r = db_fetch_array(db_select("select grupper.box6 as box6 from adresser, grupper where adresser.kontonr='$kontonr' and adresser.art='D' and grupper.art='DG' and ".nr_cast("grupper.kodenr")."=adresser.gruppe",__FILE__ . " linje " . __LINE__));
	$rabatsats=$r['box6']*1;
	if (strstr($submit,'Slet')) {
		db_modify("delete from ordrelinjer where ordre_id=$id",__FILE__ . " linje " . __LINE__);
		db_modify("delete from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
	}
#	if ($submit=='OK') {
#		if ($genfakt && $genfakt!='-') db_modify("update ordrer set nextfakt='".usdate($genfakt)."' where id='$id'",__FILE__ . " linje " . __LINE__);
#		else db_modify("update ordrer set nextfakt=NULL where id='$id'",__FILE__ . " linje " . __LINE__);
#	}
	transaktion("begin");
	for ($x=0; $x<=$linjeantal;$x++) {
		$y="posn".$x;
		$posnr_ny[$x]=trim(if_isset($_POST[$y]));
		$y="vare".$x;
		$varenr[$x]=addslashes(trim(if_isset($_POST[$y])));
		$y="anta".$x;
		$antal[$x]=trim(if_isset($_POST[$y]));
		if ($antal[$x] || $antal[$x]=='0'){
			$antal[$x]=usdecimal($antal[$x]);
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
				$pris[$x]=round(($pris[$x]/(100+$momssats)*100)+0.0001,3);
			}
		}
		$y="raba".$x;
		$rabat[$x]=usdecimal(if_isset($_POST[$y]));
		if (($x>0)&&(!$rabat[$x]))$rabat=0;
		$y="ialt".$x;
		$ialt[$x]=if_isset($_POST[$y]);
		if (($godkend == "on")&&($status==0)) {
			$leveres[$x]=$antal[$x];
			if (isset($linje_id[$x])) batch($linje_id[$x]);
		}
		if ((!$sletslut) && ($posnr_ny[$x]=="->")) $sletstart=$x;  
		if (($sletstart) && ($posnr_ny[$x]=="<-")) $sletslut=$x;
	}
	if (($sletstart)&&($sletslut)&&($sletstart<$sletslut)) {
		for ($x=$sletstart; $x<=$sletslut; $x++) $posnr_ny[$x]="-";
	}
	if ($levdato) $levdate=usdate($levdato);
	if ($id) { # 2009.05.11 
		db_modify("update ordrer set email='$email', mail_fakt='$mail_fakt', pbs='$pbs' where id='$id'",__FILE__ . " linje " . __LINE__);
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
	if (strstr($submit, "Kred")) {	
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
	} elseif (strstr($submit, "Kopi")){
		$gl_id=$id;
		$id='';
		$status=0;
	}
	elseif (!$art) {$art='DO';}
	if (strlen($ordredate)<6){$ordredate=date("Y-m-d");}
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
			$mail_fakt=$row['mailfakt'];
			$gruppe=$row['gruppe'];
			if ($gruppe) {
				$r = db_fetch_array(db_select("select box1, box3, box4, box6 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
				$tmp= substr($r['box1'],1,1);
				$std_rabat=$r['box6']*1;
				if (!$gl_id) {# valuta & sprog skal beholdes v. ordrekopiering.
					$fakt_sprog=$r['box4'];
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
		$qtext="insert into ordrer (ordrenr, konto_id, kontonr, kundeordnr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, lev_navn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt, betalingsdage, betalingsbet, cvrnr, ean, institution, email, mail_fakt, notes, art, ordredate, momssats, status, ref, lev_adr, valuta, projekt, sprog,pbs) values ($ordrenr, '$konto_id','$kontonr','$kundeordnr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$lev_navn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$email','$mail_fakt','$notes','$art','$ordredate','$momssats', $status, '$ref','$lev_adr','$valuta','$projekt','$fakt_sprog','$pbs')";
		db_modify($qtext,__FILE__ . " linje " . __LINE__);
		$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$id=$row['id'];
			if ($gl_id) {
				$r=(db_fetch_array(db_select("select levdate, ordredate, fakturadate, nextfakt from ordrer where id='$gl_id'",__FILE__ . " linje " . __LINE__)));
				if ($r['nextfakt']) {
					$nextfakt=find_nextfakt($r['fakturadate'], $r['nextfakt']);
					db_modify("update ordrer set levdate='$r[nextfakt]',fakturadate='$r[nextfakt]',nextfakt='$nextfakt',ordredate='$r[ordredate]' where id = $id",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	elseif(($firmanavn)&&($status<3)) {
		$sum=0;
		for($x=1; $x<=$linjeantal; $x++) {
			$vare_id[$x]=$vare_id[$x]*1;
			$r=db_fetch_array(db_select("select gruppe, beholdning from varer where id = $vare_id[$x]",__FILE__ . " linje " . __LINE__));
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
				}
				if (!$negativt_lager && $leveres[$x]>$beholdning[$x] && $leveres[$x]>$beholdning[$x] && $leveres[$x]>0 && 
						db_fetch_array(db_select("select id from grupper where kodenr='$vare_grp[$x]' and art='VG' and box8='on'",__FILE__ . " linje " . __LINE__))) { 
					if ($beholdning[$x]<=0) $leveres[$x]=0; 
					else $leveres[$x]=$beholdning[$x]*1;
					print "<BODY onLoad=\"javascript:alert('Lagerbeholdning:$beholdning[$x]. Der kan max leveres $leveres[$x]. Antal reguleret (Pos.nr: $posnr_ny[$x])')\">";
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
				if ((!strpos($posnr_ny[$x], '+'))&&($id)) {
					$posnr_ny[$x]=round($posnr_ny[$x],0);
					if ($posnr_ny[$x]>=1) {db_modify("update ordrelinjer set posnr='$posnr_ny[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);}
					else print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte et - (minus) som pos nr for at slette en varelinje')\">";
				}
				if ($vare_id[$x] && $r=db_fetch_array(db_select("SELECT box6 FROM grupper WHERE kodenr='vare_grp[$x]'and art='VG'",__FILE__ . " linje " . __LINE__))) {
					if (($r['box6']!=NULL)&&($rabat[$x]>$r['box6'])) {
						$rabat[$x]=$r['box6'];
						print "<BODY onLoad=\"javascript:alert('Max rabat for varenummer: $varenr[$x] er $rabat[$x]%')\">";
					}
				}
				if (!$antal[$x]){$antal[$x]=1;}
				$sum=$sum+($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				if (!$leveres[$x]){$leveres[$x]=0;}
				if (!$rabat[$x])$rabat[$x]='0';
				db_modify("update ordrelinjer set varenr='$varenr[$x]', beskrivelse='$beskrivelse[$x]', antal='$antal[$x]', leveres='$leveres[$x]', pris='$pris[$x]', rabat='$rabat[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
				if ((strpos($posnr_ny[$x], '+'))&&($id)) indsaet_linjer($id, $linje_id[$x], $posnr_ny[$x]);
			}
#			if (strlen($fakturadate)>5){db_modify("update ordrer set fakturadate='$fakturadate' where id=$id");}
		}
		if (($posnr_ny[0])&&(!strstr($submit,'Opslag'))) {
			if ($varenr[0]) {
				$pris[0]=$pris[0]*1; #Indsat 20090219 da pris eler saettes til 0.
				$tmp=strtoupper($varenr[0]);
				$query = db_select("SELECT * FROM varer WHERE upper(varenr) = '$tmp'",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) {
					$varenr[0]=$row['varenr'];
					$vare_id[0]=$row['id'];
					$samlevare[0]=$row['samlevare'];
					if (!$beskrivelse[0]) $beskrivelse[0]=addslashes($row['beskrivelse']);
					if (!$enhed[0]) $enhed[0]=addslashes($row['enhed']);
					if (!$pris[0]) $pris[0]=$row['salgspris'];
					if (!$pris[0]) $pris[0]=0;
					if (!$rabat[0]) $rabat[0]=$row['rabat'];
					if(!$antal[0]) $antal[0]=1;
					$rabat[0]=$rabatsats;
					$serienr=$row['serienr'];
					if ($valuta && $valuta!='DKK') {
						if ($r2= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=".nr_cast('grupper.kodenr')." and valuta.valdate <= '$ordredate' order by valuta.valdate desc"))) {
							$pris[0]=round($pris[0]*100/$r2['kurs']+0.0001,3);
						} else {
							$tmp = dkdato($ordredate);
							print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
						}
					}
					if ($r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$row[gruppe]'"))) {
						$momsfri[0] = $r2['box7'];
						if (($r2['box6']!=NULL)&&($rabat[0] > $r2['box6'])) {
							$rabat[0]=$r2['box6'];
							print "<BODY onLoad=\"javascript:alert('Max rabat p&aring; varenr $varenr[0] er $rabat[0] %')\">";
						}
					}
					$rabat[0]=$rabat[0]*1;
					db_modify("insert into ordrelinjer (vare_id, ordre_id, posnr, varenr, beskrivelse, enhed, antal, pris, rabat, serienr, momsfri, samlevare) values ($vare_id[0], '$id','$posnr_ny[0]','$varenr[0]','$beskrivelse[0]','$enhed[0]','$antal[0]','$pris[0]','$rabat[0]','$serienr[0]','$momsfri[0]','$samlevare[0]')",__FILE__ . " linje " . __LINE__);
				} else  {
					$submit='Opslag';
					$varenr[0]=$varenr[0]."*";
				}
			}
			elseif ($beskrivelse[0]) {db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$id','$posnr_ny[0]','$beskrivelse[0]')",__FILE__ . " linje " . __LINE__);}
		}
		if ($id) { 
			$r = db_fetch_array(db_select("select tidspkt, hvem from ordrer where status < 3 and id = $id and hvem != '$brugernavn'",__FILE__ . " linje " . __LINE__));
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
					$opdat="update ordrer set kontonr='$kontonr', kundeordnr='$kundeordnr', firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', kontakt='$kontakt', lev_navn='$lev_navn',	lev_addr1='$lev_addr1',	lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', ean='$ean', institution='$institution', email='$email', mail_fakt='$mail_fakt', notes='$notes', ordredate='$ordredate', status=$status, ref='$ref', fakturanr='$fakturanr', lev_adr='$lev_adr', hvem = '$brugernavn', tidspkt='$tidspkt', valuta='$valuta', projekt='$projekt', sprog='$fakt_sprog', pbs='$pbs' $tmp where id=$id";
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
				if (strstr($submit,'Kopi')) {$tmp=$antal[$x]*1;}
				else {$tmp=$antal[$x]*-1;}
				if ($r1=db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__))) {
					if ($r2=db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'",__FILE__ . " linje " . __LINE__))) $momsfri[$x] = $r2['box7'];
				}	
				$r3=db_fetch_array(db_select("select samlevare, kostpris from ordrelinjer where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__));
				$kostpris[$x]=$r3['kostpris']*1;
				db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, serienr, kred_linje_id, momsfri, samlevare, kostpris) values ('$id','$posnr_ny[$x]','$varenr[$x]','$vare_id[$x]','$beskrivelse[$x]','$enhed[$x]', $tmp, '$pris[$x]','$rabat[$x]','$serienr[$x]','$linje_id[$x]','$momsfri[$x]','$r3[samlevare]','$kostpris[$x]')",__FILE__ . " linje " . __LINE__);
			}
			else {db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$id','$posnr_ny[$x]','$beskrivelse[$x]')",__FILE__ . " linje " . __LINE__);}
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
		print "<BODY onLoad=\"JavaScript:window.open('$ps_fil?id=$id&formular=$formular' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
	}	

##########################OPSLAG################################

	if ((strstr($submit,'Opslag'))||((strstr($submit,'Gem'))&&(!$id))) {
		if ((strstr($fokus,'kontonr'))&&(!$id)) kontoopslag($sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'firmanavn'))&&(!$id)) kontoopslag($sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'addr1'))&&(!$id)) kontoopslag($sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'addr2'))&&(!$id)) kontoopslag($sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'postnr'))&&(!$id)) kontoopslag($sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'bynavn'))&&(!$id)) kontoopslag($sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
		if ((strstr($fokus,'vare'))&&($art!='DK')) vareopslag($sort, 'varenr', $id, $vis_kost, $ref, $varenr[0]);
		if (strstr($fokus,'besk') && $beskrivelse[0] && $art!='DK') vareopslag($sort, 'beskrivelse', $id, $vis_kost, $ref, $beskrivelse[0]);
		if (strstr($fokus,'besk') && $art!='DK') tekstopslag($sort, $id);
		if ((strstr($fokus,'kontakt'))&&($id)) ansatopslag($sort, $fokus, $id, $vis, $kontakt);
		elseif (strstr($fokus,'kontakt')) kontoopslag($sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
	}

########################## DELFAKTURER  - SKAL VAERE PLACERET FOER "FAKTURER" ################################
	if ($submit=='Delfakturer') {
		$sum=0; $moms=0;
		$ny_sum=0; $ny_moms=0;
		transaktion("begin");
		$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, kundeordnr, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, momssats, tidspkt, ref, status, lev_navn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt, valuta, projekt, sprog, email, mail_fakt, pbs) values ('$r[ordrenr]','$r[konto_id]','$r[kontonr]','$r[firmanavn]','$r[addr1]','$r[addr2]','$r[postnr]','$r[bynavn]','$r[land]','$r[kontakt]','$r[kundeordnr]','$r[betalingsdage]','$r[betalingsbet]','$r[cvrnr]','$r[ean]','$r[institution]','$r[notes]','$r[art]','$r[ordredate]','$r[momssats]','$r[tidspkt]','$r[ref]','$r[status]','$r[lev_navn]','$r[lev_addr1]','$r[lev_addr2]','$r[lev_postnr]','$r[lev_bynavn]','$r[lev_kontakt]','$r[valuta]','$r[projekt]','$r[sprog]','$r[email]','$r[mail_fakt]','$r[pbs]')",__FILE__ . " linje " . __LINE__);
		$q = db_select("select id from ordrer where ordrenr=$ordrenr and art='$art' and tidspkt='$tidspkt' order by id desc",__FILE__ . " linje " . __LINE__);
		if ($r = db_fetch_array($q)) $ny_id=$r[id];
		for($x=1; $x<=$linjeantal; $x++) {
			if ($vare_id[$x]){
#				if ($r1=db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[$x]'"))) {
#					if ($r2=db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'"))) {$momsfri[$x] = $r2['box7'];}
#				}	
				$r3=db_fetch_array(db_select("select momsfri, leveret, samlevare, kostpris from ordrelinjer where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__));
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
						db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, lev_varenr, serienr, kred_linje_id, momsfri, samlevare) values ('$ny_id','$posnr_ny[$x]','$varenr[$x]','$vare_id[$x]','$beskrivelse[$x]','$enhed[$x]', $ny_antal, '$pris[$x]','$rabat[$x]','$lev_varenr[$x]','$serienr[$x]','$linje_id[$x]','$momsfri[$x]','$r3[samlevare]')",__FILE__ . " linje " . __LINE__);
						db_modify("update ordrelinjer set antal='$antal[$x]' where id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
					}
					else {
						db_modify("update ordrelinjer set ordre_id='$ny_id' where id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
					}
				}
			}
			else db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$ny_id','$posnr_ny[$x]','$beskrivelse[$x]')",__FILE__ . " linje " . __LINE__);
		}
		db_modify("update ordrer set sum = '$sum', moms = '$moms', status='2' where id='$id'",__FILE__ . " linje " . __LINE__);
		db_modify("update ordrer set sum = '$ny_sum', moms = '$ny_moms', hvem = '', tidspkt= '' where id='$ny_id'",__FILE__ . " linje " . __LINE__);

#exit;
		
		$submit='Fakturer';
		transaktion("commit");
	}
########################## FAKTURER   - SKAL VAERE PLACERET EFTER "DELFAKTURER" ################################
	if ($submit=='Fakturer'&&$hurtigfakt=='on') {
		
	
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		if (!$row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke fakturere uden ordrelinjer')\">";}
		else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=levering.php?id=$id&hurtigfakt=on&mail_fakt=$mail_fakt&pbs=$pbs\">";}
	} elseif ($submit=='Fakturer'&&$bogfor!=0) {
			$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		if (!$row = db_fetch_array($query)) {Print "Du kan ikke fakturere uden ordrelinjer";}
		else {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?id=$id&mail_fakt=$mail_fakt&pbs=$pbs\">";
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

ordreside($id, $regnskab);


function ordreside($id, $regnskab)
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
	
	if (!$returside) {
		if ($popup) $returside="../includes/luk.php";
		else $returside="ordreliste.php";
	}
	$batchvare=NULL;$dbsum=NULL;$ko_ant=NULL;$levdato=NULL;$levdiff=NULL;$linjebg=NULL;$momsfri=NULL;$momssum=NULL;$reserveret=NULL;$tidl_lev=NULL;$y=NULL;
	
	if (!$id) $fokus='kontonr';
	else $fokus='vare0';
######### pile ########## tilfoejet 20080210
	print "<form name=ordre action=ordre.php?id=$id&returside=$returside method=post>";
	if ($id) {
		$r=db_fetch_array(db_select("select box1 from grupper where art = 'OLV' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__));
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
		$cvrnr = $row['cvrnr'];
		$ean = stripslashes($row['ean']);
		$institution = stripslashes($row['institution']);
		$email = stripslashes($row['email']);
		$mail_fakt = $row['mail_fakt'];
		$betalingsbet = trim($row['betalingsbet']);
		$betalingsdage = $row['betalingsdage'];
		$valuta=$row['valuta'];
		$valutakurs=$row['valutakurs'];
		$projekt=$row['projekt'];
		$fakt_sprog=$row['sprog'];
		$pbs=$row['pbs'];
		$ref = trim(stripslashes($row['ref']));
		$fakturanr = stripslashes($row['fakturanr']);
		$lev_adr = stripslashes($row['lev_adr']);
		$ordrenr=$row['ordrenr'];
		$kred_ord_id=$row['kred_ord_id'];
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
		$q=db_select("select art, pbs_nr from adresser where art = 'S' or id = '$konto_id'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['art']=='S') $lev_pbs_nr=$r['pbs_nr'];
			else $pbs_nr=$r['pbs_nr'];
		}
		$query = db_select("select id, ordrenr from ordrer where kred_ord_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($row2 = db_fetch_array($query)) {
			$x++;
			if ($x>1) {$krediteret=$krediteret.", ";}
			$krediteret=$krediteret."<a href=ordre.php?id=$row2[id]>$row2[ordrenr]</a>";
		}	
	} else {
		$r=db_fetch_array(db_select("select ansatte.navn as ref from ansatte,brugere where ansatte.id = ".nr_cast("brugere.ansat_id")." and brugere.brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__));
		$ref=$r['ref'];
	}
	
	if ($art=='DK') { 
		$query = db_select("select ordrenr from ordrer where id = '$kred_ord_id'",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query);
		sidehoved($id, "$returside", "", "", "Kunde kreditnota $ordrenr (kreditering af ordre nr: <a href=ordre.php?id=$kred_ord_id>$row2[ordrenr]</a>)");
	}
	elseif ($krediteret) {sidehoved($id, "$returside", "", "", "Kundeordre $ordrenr ( krediteret p&aring; KN nr: $krediteret )");}
	else {
		if ($status<1) $temp='Tilbud';
		elseif ($status<2) $temp='Ordre';
		else $temp='Faktura';
		sidehoved($id, "$returside", "", "", "Kundeordre $ordrenr - $temp");
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
		print "<input type=hidden name=mail_fakt value=\"$mail_fakt\">";
		print "<input type=hidden name=betalingsbet value=\"$betalingsbet\">";
		print "<input type=hidden name=betalingsdage value=\"$betalingsdage\">";
		print "<input type=hidden name=momssats value=\"$momssats\">";
		print "<input type=hidden name=ref value=\"$ref\">";
		print "<input type=hidden name=fakturanr value=\"$fakturanr\">";
		print "<input type=hidden name=lev_adr value=\"$lev_adr\">";
		print "<input type=hidden name=valuta value=\"$valuta\">";
		print "<input type=hidden name=projekt value=\"$projekt\">";
		print "<input type=hidden name=pbs value=\"$pbs\">";

		if ($mail_fakt) $mail_fakt="checked";
		
##### pile ########	tilfoejet 20080210	
		$alerttekst=findtekst(154,$sprog_id);
		$spantekst=findtekst(198,$sprog_id);
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\"><tbody>";
		if ($prev_id)	print "<tr><td width=50%><span title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$prev_id&returside=$returside','$alerttekst')\"><img src=../ikoner/left.gif style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td>";
		else print "<tr><td width=50%></td>";
		$spantekst=findtekst(199,$sprog_id);
		if ($next_id)	print "<td width=50% align=right><span title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$next_id&returside=$returside','$alerttekst')\"><img src=../ikoner/right.gif style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td></tr>";
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
		print "<tr><td><b>E-mail</td><td width=105><input type=text name=email size=15 value=\"$email\"></td></tr>\n";
		print "<tr><td><b>Fakt som mail</td><td><input type=checkbox name=mail_fakt $mail_fakt></td></tr>\n";
		if ($lev_pbs_nr) {
			if ($pbs == "FI") $pbs_fi='checked';
			elseif ($pbs == "BS") $pbs_bs='checked';
			$title="PBS udsender FI-indbetalingskort";
			if (!$pbs_bs) {
				print "<td colspan=2 title=\"$title\">Faktura via PBS (FI)</td><td title=\"$title\"><input type=checkbox name=pbs_fi $pbs_fi onchange=\"javascript:docChange = true;\"></td></tr>\n";
				if ($pbs_nr && !$pbs_fi) print "<tr>";
			}
			$title="Opkr&aelig;ves via PBS's betalingsservice";
			if ($pbs_nr && !$pbs_fi) print "<td colspan=2 title=\"$title\">Opkr&aelig;v via PBS (BS)</td><td title=\"$title\"><input type=checkbox name=pbs_bs \"$pbs_bs\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		} else print "</tr>";	
		print "<tr><td width=100><b>Ordredato</td><td width=100><small$ordredato</td></tr>\n";
		print "<tr><td><b>Lev. dato</td><td>$levdato</td></tr>\n";
		print "<tr><td><b>Fakturadato</td><td>$fakturadato</td></tr>\n";
		print "<tr><td><b>Genfaktureres</td><td><input type=text name=genfakt size=7 value=\"$genfakt\"><input type=submit value=\"OK\" name=\"submit\"></td></tr>\n";
		print "<tr><td><b>Betaling</td><td>$betalingsbet&nbsp;+&nbsp;$betalingsdage</td>";
		print "<tr><td><b>Vor ref.</td><td>$ref</td></tr>\n";
		print "<tr><td><b>Fakturanr</td><td>$fakturanr</td></tr>\n";
		$tmp=dkdecimal($valutakurs);
		if ($valuta) print "<tr><td><b>Valuta / Kurs</td><td>$valuta / $tmp</td></tr>\n";
		if ($projekt) print "<tr><td><b>Projekt</td><td>$projekt</td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=31%><table cellpadding=0 cellspacing=0 border=0 width=\"100%\" valign=\"top\">";
		print "<tr><td><b>Leveringsadresse</b><br />&nbsp;</td></tr>\n";
		print "<tr><td>Firmanavn</td><td colspan=2>$lev_navn</td></tr>\n";
		print "<tr><td>Adresse</td><td colspan=2>$lev_addr1</td></tr>\n";
		print "<tr><td></td><td colspan=2>$lev_addr2</td></tr>\n";
		print "<tr><td>Postnr. &amp; by</td><td>$lev_postnr $lev_bynavn</td></tr>\n";
		print "<tr><td>Att.:</td><td colspan=2>$lev_kontakt</td></tr>\n";
		print "</td></tr></tbody></table></td>";
		print "</td></tr><tr><td align=center colspan=3><table cellpadding=0 cellspacing=0 border=1 width=100%><tbody>";
		print "<tr><td colspan=7></td></tr><tr>";
		print "<td align=center><b>pos</td><td align=center><b>varenr</td><td align=center><b>ant.</td><td align=center><b>enhed</td><td align=center><b>beskrivelse</td><td align=center><b>pris</td><td align=center><b>%</td><td align=center><b>i alt</td><td></td>";
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
				$serienr[$x]=stripslashes(htmlentities($row['serienr'],ENT_COMPAT,$charset));
				$kostpris[$x]=$row['kostpris'];
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
						$r2 = db_fetch_array(db_select("select antal, ordre_id, pris, fakturadate, linje_id from batch_kob where id = $row[batch_kob_id]",__FILE__ . " linje " . __LINE__));
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
				$ialt=round($ialt+0.0001,3);
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
					$momssum=$momssum+$ialt;
				  if($incl_moms)$dkpris=dkdecimal($pris[$x]+$pris[$x]*$momssats/100);
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
			print "<input type=hidden name=anta$x value=$dkantal[$x]><td align=right>$dkantal[$x]</td>";
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
					if ($incl_moms) $tmp=dkdecimal($ialt+$ialt*$momssats/100);
					else $tmp=dkdecimal($ialt);
				} 
				print "<td align=right><span title= 'kostpris $dk_kostpris[$x] * db: $dk_db[$x] * dg: $dk_dg[$x]%'>".$tmp."</td>";
			}
			else {print "<td><br></td>";}
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
		$tmp=$momssum/100*$momssats; #ellers runder den ned ved v. 0,5 re ??
		$moms=round($tmp+0.0001,3);
		$kostpris[0]=$kostpris[0]*1;
		if ($submit=='Delfakturer'||$submit=='Fakturer') db_modify("update ordrer set sum='$sum', kostpris='$kostpris[0]', moms='$moms' where id='$id'",__FILE__ . " linje " . __LINE__);
		if ($art=='DK') {
			$sum=$sum*-1;
			$momssum=$momssum*-1;		
		}
		$tmp=$momssum/100*$momssats; #ellers runder den ned ved v. 0,5 ??
		$moms=round($tmp+0.0001,3);
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
		if (($art!='DK')&&(!$krediteret)) print "<td align=center><input type=submit value=\"Kredit&eacute;r\" name=\"submit\"></td>";
	}
	else { ############################# ordren er ikke faktureret #################################
		#intiering af variabler
		$antal_ialt=0; #10.10.2007
		$leveres_ialt=0; #10.10.2007
		$tidl_lev_ialt=0; #10.10.2007

##### pile ########	tilfoejet 20080210	
		$alerttekst=findtekst(154,$sprog_id);
		$spantekst=findtekst(198,$sprog_id);
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\"><tbody>";
		if ($prev_id)	print "<tr><td width=50%><span title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$prev_id&returside=$returside','$alerttekst')\"><img src=../ikoner/left.gif style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td>";
		else print "<tr><td width=50%></td>";
		$spantekst=findtekst(199,$sprog_id);
		if ($next_id)	print "<td width=50% align=right><span title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$next_id&returside=$returside','$alerttekst')\"><img src=../ikoner/right.gif style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td></tr>";
		else print "<tr><td width=50%></td>";
		print "</tbody></tabel>";
##### pile ########		
		print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\"	valign = \"top\"><tbody>";
		$ordre_id=$id;
		print "<tr><td width=31%><table cellpadding=0 cellspacing=0 border=0>";
		print "<tr><td witdh=100>Kontonr.</td><td colspan=2>";
		if (trim($kontonr)) {print "<input readonly=readonly size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\"></td></tr>\n";}
		else {print "<input type=text size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\" onchange=\"javascript:docChange = true;\"></td></tr>\n";}
		print "<tr><td>Firmanavn</td><td colspan=2><input type=text size=25 name=firmanavn onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$firmanavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Adresse</td><td colspan=2><input type=text size=25 name=addr1 onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$addr1\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td></td><td colspan=2><input type=text size=25 name=addr2 onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$addr2\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Postnr. &amp; by</td><td><input type=text size=4 name=postnr onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$postnr\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input type=text size=19 name=bynavn onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Land</td><td colspan=2><input type=text size=25 name=land onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$land\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Att.:</td><td colspan=2><input type=text size=25 name=kontakt onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontakt\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Kunde_ordnr</td><td colspan=2><input type=text size=25 name=kundeordnr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kundeordnr\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=38%><table cellpadding=0 cellspacing=0 border=0 width=250>";
		
		print "<tr><td>CVR-nr.</td><td colspan=2><input type=text size=12 name=cvrnr value=\"$cvrnr\" onchange=\"javascript:docChange = true;\"></td>\n";
		print "<td>EAN-nr.</td><td><input type=text size=12 name=ean value=\"$ean\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>E-mail</td><td><input type=text size=12 name=email value=\"$email\" onchange=\"javascript:docChange = true;\"></td>\n";
		print "<td></td><td>Institution</td><td colspan=2><input type=text size=12 name=institution value=\"$institution\" onchange=\"javascript:docChange = true;\"></td>\n";
		if ($mail_fakt) $mail_fakt='checked';
		print "<tr><td colspan=2>Send pr. mail&nbsp;</td><td><input type=checkbox name=mail_fakt onchange=\"javascript:docChange = true;\" $mail_fakt></td>\n";
		if ($lev_pbs_nr) {
			if ($pbs == "FI") $pbs_fi='checked';
			elseif ($pbs == "BS") $pbs_bs='checked';
			$title="PBS udsender FI indbetalingskort";
			if (!$pbs_bs) { #naeste linje ingen apostrof omkring $pbs_fi
				print "<td colspan=2 title=\"$title\">Faktura via PBS (FI)</td><td title=\"$title\"><input type=checkbox name=pbs_fi $pbs_fi onchange=\"javascript:docChange = true;\"></td></tr>\n";
				if ($pbs_nr && !$pbs_fi) print "<tr><td colspan=2><td>";
			}
			$title="Opkr&aelig;ves via PBS betalingsservice";
			if ($pbs_nr && !$pbs_fi) print "<td colspan=2 title=\"$title\">Opkr&aelig;v via PBS (BS)</td><td title=\"$title\"><input type=checkbox name=pbs_bs \"$pbs_bs\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		} else print "</tr>";	
		if (db_fetch_array(db_select("select distinct sprog from formularer where sprog != 'Dansk'",__FILE__ . " linje " . __LINE__))) {
			print "<td></td><td><span title='Sprog som skal anvendes p&aring; tilbud, ordrer mm.'>Sprog</span></font></td>";
			print "<td><select NAME=sprog>";
			print "<option>$fakt_sprog</option>";
			$q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) print "<option>$r[sprog]</option>";
			print "</SELECT></td></tr>";
		} else print "</tr>";
		print "<tr><td colspan=5><hr></td><tr>";
		print "<tr><td width=20%>Ordredato</td><td colspan=2><input type=text size=12 name=ordredato value=\"$ordredato\" onchange=\"javascript:docChange = true;\"></td>\n";
		if ($hurtigfakt=='on') print "<td></td></tr>\n";
		else print "<td>Lev. dato</td><td colspan=2><input type=text size=12 name=levdato value=\"$levdato\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		if ($fakturadato||$status>0) {
			print "<tr><td>";
			if ($art=='DO') print "Fakt. dato"; 
			else print "KN. dato";
			print "</td><td colspan=2><input type=text size=12 name=fakturadato value=\"$fakturadato\" onchange=\"javascript:docChange = true;\"></td>\n";
			$tmp="Dette felt skal kun udfyldes, hvis der er tale om et abonnement eller \nlign som skal faktureres igen p&aring; et senere tidspunkt.\nSkriv datoen for n&aelig;ste fakturering";
			if ($art=='DO') print "<td width=20%><span title='$tmp'>Genfakt.</span></td><td colspan=2><input type=text size=12 name=genfakt value=\"$genfakt\" onchange=\"javascript:docChange = true;\"></td>\n";
		}
		print "</tr><tr><td>Betaling</td>";
		print "<td colspan=2><SELECT NAME=betalingsbet>";
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
			print "</SELECT>+<input type=text size=1 style=text-align:right name=betalingsdage value=\"$betalingsdage\" onchange=\"javascript:docChange = true;\"></td>";
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
			print "<td><SELECT NAME=valuta>";
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
					print "<td colspan=3><SELECT NAME=ref>";
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
		$tmp=$x;
		if ($x>0) {
			print "<td><span title= 'kostpris';>Projekt</span></td>";
			print "<td><SELECT NAME=projekt>";
			for ($x=0; $x<=$tmp; $x++) {
				if ($projekt!=$list[$x]) print "<option title=\"$beskriv[$x]\">$list[$x]</option>";
				else print "<option title=\"$beskriv[$x]\" selected=\"selected\">$list[$x]</option>";
			}
			print "</SELECT></td></tr>";
		} else print "<tr><td colspan=2 width=200></tr>";
		
		if ($status==0&&$hurtigfakt!="on") print "<tr><td>Godkend</td><td><input type=checkbox name=godkend></td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=31%><table cellpadding=0 cellspacing=0 border=0 width=100% valign=\"top\">";
		print "<tr><td colspan=2 align=center><b>Leveringsadresse</b><br />&nbsp;</td></tr>\n";
		print "<tr><td>Firmanavn</td><td colspan=2><input type=text size=25 name=lev_navn value=\"$lev_navn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Adresse</td><td colspan=2><input type=text size=25 name=lev_addr1 value=\"$lev_addr1\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td></td><td colspan=2><input type=text size=25 name=lev_addr2 value=\"$lev_addr2\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Postnr. &amp; by</td><td><input type=text size=4 name=lev_postnr value=\"$lev_postnr\"><input type=text size=19 name=lev_bynavn value=\"$lev_bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Att.:</td><td colspan=2><input type=text size=25 name=lev_kontakt value=\"$lev_kontakt\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		#	 print "<tr><td><textarea style=\"font-family: helvetica,arial,sans-serif;\" name=lev_adr rows=5 cols=35>$lev_adr</textarea></td></tr>\n";
		print "</td></tr></tbody></table></td>";
		print "</td></tr><tr><td align=center colspan=3><table cellpadding=0 cellspacing=0><tbody>";
		$query = db_select("select notes from adresser where kontonr = '$kontonr'",__FILE__ . " linje " . __LINE__);
		if ($row2 = db_fetch_array($query)) {
			print "<tr><td colspan=9 witdh=100% align=center> <span style='color: rgb(255, 0, 0);'>$row2[notes]</td></tr><tr><td colspan=9 witdh=100%><hr></td></tr>\n";
		}
		if ($kontonr) {
			if ($art=='DO') $tmp="lev&egrave;r";
			else $tmp="modtag";
			print "<tr>";
			if ($status==1&&$hurtigfakt!='on')  {print "<td align=center>pos</td><td align=center>varenr</td><td align=center>antal/enhed</td><td align=center>beskrivelse</td><td align=center>pris</td><td align=center>%</td><td align=center>i alt</td><td colspan=2 align=center>$tmp</td><td></td>";} #<td align=center>serienr</td>";
			else {print "<td align=center>pos</td><td align=center>varenr</td><td align=center>antal enhed</td><td align=center>beskrivelse</td><td align=center>pris</td><td align=center>%</td><td align=center>i alt</td>";}
			print "</tr>\n";
		}
		if (!$status) $status=0;
		print "<input type=hidden name=status value=$status>";
		print "<input type=hidden name=id value=$id>";

		$x=0;
		if (!$ordre_id) $ordre_id=0;
		$kostpris[0]=0;
		
		if ($valuta && $valuta!='DKK') {
			if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=".nr_cast("grupper.kodenr")." and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
				$valutakurs=$r['kurs'];
			} else {
				$tmp = dkdato($ordredate);
				print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
			}
		} else $valutakurs = 100;
#		db_modify("update ordrer set valutakurs='$valutakurs' where ordre_id = '$ordre_id'");
		
		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (($row['posnr']>0)&&($row['samlevare']<1)) {  #Hvis "samlevare" er numerisk, indgaar varen i den ordrelinje, der refereres til - hvis "on" er varen en samlevare.
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
				$serienr[$x]=stripslashes(htmlentities(trim($row['serienr']),ENT_COMPAT,$charset));
				$samlevare[$x]=$row['samlevare'];
				if ($vare_id[$x]) {
					$q2 = db_select("select kostpris, provisionsfri from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
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
				$ialt=round($ialt+0.0001,3);
				$sum=$sum+$ialt;
				$dkpris=dkdecimal($pris[$x]);
				$dkrabat=dkdecimal($rabat[$x]);
				if ($momsfri[$x]!='on') {
					$momssum=$momssum+$ialt;
				  if($incl_moms)$dkpris=dkdecimal($pris[$x]+$pris[$x]*$momssats/100);
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
			print "<input type=hidden name=serienr[$x] value=$serienr[$x]>";
			print "<input type=hidden name=momsfri[$x] value=$momsfri[$x]>";
			print "<tr>";
			print "<td><input type=\"text\" style=\"text-align:right\" size=3 name=posn$x value=$x></td>";
			print "<td><input readonly=readonly size=12 name=vare$x onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$varenr[$x]\" onchange=\"javascript:docChange = true;\"></td>";
			print "<td><input type=\"text\" style=\"text-align:right\" size=3 name=anta$x value=$dkantal[$x]><input readonly=readonly size=3 value=\"$enhed[$x]\" onchange=\"javascript:docChange = true;\"></td>";
			$title=var2str($beskrivelse[$x],$id);
			print "<td title=\"$title\"><input type=text size=60 name=beskrivelse$x value=\"$beskrivelse[$x]\" onchange=\"javascript:docChange = true;\"></td>";
			print "<td><span title= 'db: $dk_db[$x] - dg: $dk_dg[$x]%'><input type=\"text\" style=\"text-align:right\" size=10 name=pris$x value=\"$dkpris\" onchange=\"javascript:docChange = true;\"></td>";
			print "<td><input type=\"text\" style=\"text-align:right\" size=4 name=raba$x value=\"$dkrabat\" onchange=\"javascript:docChange = true;\"></td>";
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
					if ($incl_moms) $tmp=dkdecimal($ialt+$ialt*$momssats/100);
					else $tmp=dkdecimal($ialt);
				} 
				else $tmp=NULL;
				print "<td align=right><span title= 'db: $dk_db[$x] - dg: $dk_dg[$x]%'><input readonly=\"readonly\" style=\"text-align:right\" size=10 value=\"$tmp\"></td>";
#		 	}			
#			else print "<td></td>";
			if ($status>=1&&$hurtigfakt!='on') {
				if ($vare_id[$x]){
					$batch="?";
					$tidl_lev[$x]=0;
					$query = db_select("select gruppe, beholdning from varer where id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
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
						elseif ($antal[$x]-$tidl_lev[$x]!=0) db_modify("insert into reservation (linje_id, vare_id, batch_salg_id, antal) values ($linje_id[$x], $vare_id[$x], 0, $antal[$x]*-1)",__FILE__ . " linje " . __LINE__);
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
						print "<td><span title= 'Beholdning: $beholdning[$x]'><input type=\"text\" style=\"text-align:right\" size=2 name=leve$x value=\"$dklev[$x]\" onchange=\"javascript:docChange = true;\"></td>";
						print "<td>($dk_tidl_lev[$x])</td>";
						if ($batchvare[$x]) print "<td align=center onClick=\"batch($linje_id[$x])\"><span title= 'V&aelig;lg fra k&oslash;bsordre'><img alt=\"Serienummer\" src=../ikoner/serienr.png></td></td>";
						$levdiff=1;
					} else {
						print "<td><span title= 'Beholdning: $beholdning[$x]'><input readonly=readonly style=text-align:right size=2 name=leve$x value=\"$dklev[$x]\" onchange=\"javascript:docChange = true;\"></td>";
						print "<td>($dk_tidl_lev[$x])</td>";
					}
					db_modify("update ordrelinjer set leveret=$tidl_lev[$x] where id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
				}
			}
			if ($samlevare[$x]=='on') print "<td align=center onClick=\"stykliste($vare_id[$x])\"><span title= 'Vis stykliste'><img alt=\"Stykliste\" src=../ikoner/stykliste.png></td></td>";
			print "</tr>\n";
			$antal_ialt=$antal_ialt+$antal[$x]; #10.10.2007
			$leveres_ialt=$leveres_ialt+abs($leveres[$x]); #abs tilfoejet 2009.01.26 grundet manglende lev_mulighed med ens antal positive og negative leveringer i ordre 98 i saldi_104
			$tidl_lev_ialt=$tidl_lev_ialt+$tidl_lev[$x]; #10.10.2007
		}
		if ($status>=1&&$bogfor!=0 && !$leveres_ialt && $tidl_lev_ialt && $antal_ialt != $tidl_lev_ialt) $delfakturer = 'on';
		else $delfakturer = '';
		if ($kontonr) {
			$x++;
			$posnr[0]=$linjeantal+1;
			print "<tr>";
			print "<td><input type=\"text\" style=\"text-align:right\" size=3 name=posn0 value=$posnr[0]></td>";
			if ($art=='DK') {print "<td><input readonly=readonly size=12 name=vare0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";}
			else {print "<td><input type=\"text\" size=12 name=vare0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";}
			print "<td><input type=\"text\" style=\"text-align:right\" size=3 name=anta0><input readonly=\"readonly\" size=3></td>";
			print "<td><input type=text size=60 name=beskrivelse0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
			print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=pris0></td>";
			print "<td><input type=\"text\" style=\"text-align:right\" size=4 name=raba0></td>";
			print "<td><input readonly=\"readonly\" size=10></td>";
			print "<td></td>";
			print "</tr>\n";
			print "<input type=hidden size=3 name=sum value=$sum>";
			$tmp=$momssum/100*$momssats; #ellers runder den ned ved v. 0,5 re ??
			$moms=round($tmp+0.0001,3);
			$kostpris[0]=$kostpris[0]*1;
			db_modify("update ordrer set sum=$sum, kostpris=$kostpris[0], moms=$moms where id=$id",__FILE__ . " linje " . __LINE__);
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
			} elseif ($delfakturer == 'on') {
				print "<td align=center width=$width><input type=submit accesskey=\"f\" value=\"Delfaktur&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";
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
					if ($r3=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = ".nr_cast('grupper.kodenr')." and valuta.valdate <= '$r[transdate]' order by valuta.valdate desc"))) {
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
######################################################################################################################################
function kontoopslag($sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt)
{
	if ($fokus=='kontonr') $find=$kontonr; 
	if ($fokus=='firmanavn') $find=$firmanavn; 
	if ($fokus=='addr1') $find=$addr1; 
	if ($fokus=='addr2') $find=$addr2; 
	if ($fokus=='postnr') $find=$postnr; 
	if ($fokus=='bynavn') $find=$bynavn; 
	if ($fokus=='kontakt') $find=$kontakt; 
	global $bgcolor;
	global $bgcolor5;
	global $land;

	if ($find) $find=str_replace("*","%",$find);
	else $find="%";
	if (substr($find,-1,1)!='%') $find=$find.'%';
	sidehoved($id, "ordre.php", "../debitor/debitorkort.php", $fokus, "Kundeordre $id - Kontoopslag");
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign=\"top\">";
	print"<tbody><tr>";
	print"<td><b><a href=ordre.php?sort=kontonr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Kundenr</b></td>";
	print"<td><b><a href=ordre.php?sort=firmanavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></td>";
	print"<td><b><a href=ordre.php?sort=addr1&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Adresse</b></td>";
	print"<td><b><a href=ordre.php?sort=addr2&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Adresse2</b></td>";
	print"<td><b><a href=ordre.php?sort=postnr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Postnr</b></td>";
	print"<td><b><a href=ordre.php?sort=bynavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>bynavn</b></td>";
	print"<td><b><a href=ordre.php?sort=land&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>land</b></td>";
	print"<td><b><a href=ordre.php?sort=kontakt&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Kontaktperson</b></td>";
	print"<td><b><a href=ordre.php?sort=tlf&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Telefon</b></td>";
	print" </tr>\n";

	$sort = $_GET['sort'];
	if (!$sort) {$sort = firmanavn;}
	if ($find) $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'D' and upper($fokus) like upper('$find') order by $sort",__FILE__ . " linje " . __LINE__);
	else $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'D' order by $sort",__FILE__ . " linje " . __LINE__);
	$fokus_id='id=fokus';
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$kontonr=str_replace(" ","",$row['kontonr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href=ordre.php?fokus=$fokus&id=$id&konto_id=$row[id] $fokus_id>$row[kontonr]</a></td>";
		$fokus_id='';
		print "<td>".stripslashes($row[firmanavn])."</td>";
		print "<td>".stripslashes($row[addr1])."</td>";
		print "<td>".stripslashes($row[addr2])."</td>";
		print "<td>".stripslashes($row[postnr])."</td>";
		print "<td>".stripslashes($row[bynavn])."</td>";
		print "<td>".stripslashes($row[land])."</td>";
		print "<td>".stripslashes($row[kontakt])."</td>";
		print "<td>".stripslashes($row[tlf])."</td>";
		print "</tr>\n";
	}
	if (!$x) {
		print "<tr><td colspan=9><hr></td></tr>";
		print "<tr><td>$kontonr</td><td>$firmanavn</td><td>$addr1</td><td>$addr2</td><td>$postnr</td><td>$bynavn</td><td>$land</td><td>$kontakt</td><td>$tlf</td></tr>";
		print "<tr><td colspan=9>Ovenst&aring;ende kunde er ikke oprettet. <a href=\"../debitor/debitorkort.php?kontonr=$kontonr&firmanavn=$firmanavn&addr1=$addr1&addr2=$addr2&postnr=$postnr&bynavn=$bynavn&land=$land&kontakt=$kontakt&tlf=$tlf&returside=../debitor/ordre.php&ordre_id=&fokus=kontonr\">Klik her for at oprette denne kunde</a></td></tr>";
		print "<tr><td colspan=9><hr></td></tr>";
	}
	print "</tbody></table></td></tr></tbody></table>";
	print "<BODY onLoad=\"javascript:document.getElementById('fokus').focus()\">";
	exit;
}
######################################################################################################################################
function ansatopslag($sort, $fokus, $id)
{
	global $bgcolor;
	global $bgcolor5;

	if (!$id) $id='0'; 
	$query = db_select("select konto_id from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$konto_id = $row[konto_id];
	
	$fokus=$fokus."&konto_id=".$konto_id;
	
	sidehoved($id, "ordre.php", "../debitor/ansatte.php", $fokus, "Debitorordre $id",__FILE__ . " linje " . __LINE__);
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><b><a href=ordre.php?sort=navn&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></td>";
	print"<td><b><a href=ordre.php?sort=tlf&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Lokal</b></td>";
	print"<td><b><a href=ordre.php?sort=mobil&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Mobil</b></td>";
	print"<td><b><a href=ordre.php?sort=email&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>E-mail</b></td>";
	print" </tr>\n";


	$sort = $_GET['sort'];
	if (!$sort) {$sort = navn;}

	if (!$id) {exit;}
	$query = db_select("select * from ansatte where konto_id = $konto_id order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query))
	{
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href='ordre.php?fokus=$fokus&id=$id&kontakt=$row[navn]'>$row[navn]</a></td>";
		print "<td> $row[tlf]</td>";
		print "<td> $row[mobil]</td>";
		print "<td> $row[email]</td>";
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function vareopslag($sort, $fokus, $id, $vis_kost, $ref, $find)
{
	global $bgcolor;
	global $bgcolor5;
	
	$linjebg=NULL;
	
	if ($find) $find=str_replace("*","%",$find);
	
	sidehoved($id, "../debitor/ordre.php", "../lager/varekort.php", $fokus, "Kundeordre $id - Vareopslag");

	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	if ($vis_kost) {print "<tr><td colspan=8 align=center><a href=ordre.php?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id>Udelad kostpriser</a></td></tr>";}
	else {print "<tr><td colspan=4 align=center><a href=ordre.php?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=on>Vis kostpriser</a></td></tr>";}
	print"<td><b><a href=ordre.php?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost>Varenr</a></b></td>";
	print"<td><b> Enhed</b></td>";
	print"<td><b><a href=ordre.php?sort=beskrivelse&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beskrivelse</a></b></td>";
	print"<td align=right><b><a href=ordre.php?sort=salgspris&funktion=vareOpslag&fokus=$fokus&id=$id>Salgspris</a></b></td>";
	if ($vis_kost) {print"<td align=right><b> Kostpris</b></td>";}
	print"<td align=right><b><a href=ordre.php?sort=beholdning&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beh.</a></b></td>";
	print"<td><br></td>";
	print" </tr>\n";

	if ($ref){
		$row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'",__FILE__ . " linje " . __LINE__));
		$row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__)); 
		$lager=$row['kodenr']*1;
	}
	if (!$sort) $sort = 'varenr';

	if ($find) $query = db_select("select * from varer where lukket != '1' and $fokus like '$find' order by $sort",__FILE__ . " linje " . __LINE__);
	else $query = db_select("select * from varer where lukket != '1' order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$query2 = db_select("select box8 from grupper where art='VG' and kodenr='$row[gruppe]'",__FILE__ . " linje " . __LINE__);
		$row2 =db_fetch_array($query2);
		if (($row2['box8']=='on')||($row['samlevare']=='on')){
			if (($row['beholdning']!='0')and(!$row['beholdning'])){db_modify("update varer set beholdning='0' where id=$row[id]",__FILE__ . " linje " . __LINE__);}
		}
		elseif ($row['beholdning']){db_modify("update varer set beholdning='0' where id=$row[id]",__FILE__ . " linje " . __LINE__);}

		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href=\"ordre.php?vare_id=$row[id]&fokus=$fokus&id=$id\">$row[varenr]</a></td>";	
		print "<td>$row[enhed]<br></td>";
		print "<td>$row[beskrivelse]<br></td>";
		$salgspris=dkdecimal($row['salgspris']);
		print "<td align=right>$salgspris<br></td>";
		if ($vis_kost=='on') {
			$query2 = db_select("select kostpris from vare_lev where vare_id = $row[id] order by posnr",__FILE__ . " linje " . __LINE__);
			$row2 = db_fetch_array($query2);
			$kostpris=dkdecimal($row2['kostpris']);
			print "<td align=right>$kostpris<br></td>";
		}
		$reserveret=0;
		if ($lager>=1){
			$q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest>0 and lager=$lager",__FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				$q3 = db_select("select * from reservation where batch_kob_id=$r2[id]",__FILE__ . " linje " . __LINE__);
				while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3['antal'];}
			}
			$linjetext="<span title= 'Reserveret: $reserveret'>";
			if ($r2= db_fetch_array(db_select("select beholdning from lagerstatus where vare_id=$row[id] and lager=$lager"))) {
				print "<td align=right>$linjetext $r2[beholdning]</span></td>";
			} 
		}
		else { 
			$q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest > 0",__FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				$q3 = db_select("select * from reservation where batch_kob_id=$r2[id]",__FILE__ . " linje " . __LINE__);
				while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3['antal'];}
			}
			$linjetext="<span title= 'Reserveret: $reserveret'>";
			print "<td align=right>$linjetext $row[beholdning]</span></td>";
		}
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function tekstopslag($sort, $id)
{
	global $bgcolor;
	global $bgcolor5;
	
	$linjebg=NULL;
	
	sidehoved($id, "ordre.php", "", $fokus, "Kundeordre $id - Vareopslag");

	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	
	$q = db_select("select * from ordretekster order by tekst",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		print "<tr><td>$row[tekst]<br></td></tr>>";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function sidehoved($id, $returside, $kort, $fokus, $tekst)
{
global $bgcolor2; 
global $top_bund;
global $color; 
global $sprog_id;	
global $charset;
#global $returside; maa ikke vaere global 

	$alerttekst=findtekst(154,$sprog_id);
	print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Kundeordre</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\"></head>";
	print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
	print "<div align=\"center\">";

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	if ($returside != "ordre.php") {print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$returside?tabel=ordrer&id=$id','$alerttekst')\" accesskey=L>Luk</a></td>";}
	else {print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$returside?id=$id','$alerttekst')\" accesskey=L>Luk</a></td>";}
	print "<td width=\"80%\" $top_bund> $color$tekst</td>";
	print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$kort?returside=$returside&ordre_id=$id&fokus=$fokus','$alerttekst')\" accesskey=N>Ny</a></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
	print "<tr><td valign=\"top\" align=center>";
}

######################################################################################################################################
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
		$query = db_select("select status, art, konto_id, ref from ordrer where id = '$row[ordre_id]'",__FILE__ . " linje " . __LINE__);
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
			 if (($valg[$x]>0)&&(!$res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_kob_id, antal, lager) values ($linje_id, $vare_id, $batch_kob_id[$x], $valg[$x], $lager)",__FILE__ . " linje " . __LINE__);}
			 elseif (($valg[$x]>0)&&($res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_salg_id, antal, lager) values ($res_linje_id[$x], $vare_id, $temp, $valg[$x], $lager)",__FILE__ . " linje " . __LINE__);}
		 } 
	}	
}
##############################################################################
function indsaet_linjer($ordre_id, $linje_id, $posnr)
 {
	$posnr = str_replace('+',':',$posnr); #jeg ved ikke hvorfor, men den vil ikke splitte med "+"
	list ($posnr, $antal) = split (':', $posnr);
	db_modify("update ordrelinjer set posnr='$posnr' where id='$linje_id'",__FILE__ . " linje " . __LINE__);
	for ($x=1; $x<=$antal; $x++) {
		db_modify("insert into ordrelinjer (posnr, ordre_id) values ('$posnr','$ordre_id')",__FILE__ . " linje " . __LINE__);
	}
}
##############################################################################
function find_nextfakt($fakturadate, $nextfakt) 
{
// Denne funktion finder diff mellem fakturadate & nextfakt, tillaegger diff til nextfakt og returnerer denne vaerdi. Hvis baade 
// fakturadate og netffaxt er sidste dag i de respektive maaneder vaelges ogsaa sidste dag i maanedes i returvaerdien.

list($faktaar, $faktmd, $faktdag) = split("-", $fakturadate);
list($nextfaktaar, $nextfaktmd, $nextfaktdag) = split("-", $nextfakt);
	
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
while ($md_antal<0) {
	$aar_antal--;
	$md_antal=$md_antal+12;
}
if ($dagantal<0) {
	$dagantal=$dagantal+$faktmd_len;
	$md_antal--;
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
		while (!checkdate($nextfaktmd,$nextfaktdag,$faktaar)) {
			$nextfaktmd++;
			$nextfaktdag=$nextfaktdag-$faktmd_len;
		} 
	} else while (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) $nextfaktdag--;
}
$nextfakt=$nextfaktaar."-".$nextfaktmd."-".$nextfaktdag;

return($nextfakt);
}# endfunc find_nextfakt

?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
<script language="javascript">
document.ordre.<?php echo $fokus?>.focus();
</script>
<!--  -->
