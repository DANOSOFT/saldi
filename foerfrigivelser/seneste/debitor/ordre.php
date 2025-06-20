<?php
// ----------------------------------debitor/ordrer---------lap 1.9.3b------2008-04-16------
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

# Javascript skalflyttes i selvstændig fil.
?>
<script LANGUAGE="JavaScript">
<!--
function SetFaktDate(dato)
{
	var name = prompt("Fakturadato sættes til", dato);
// var agree=confirm("Fakturadato sættes til dags dato \nTryk \"Cancel\" og gem får at skrive en anden dato.");
	if (agree)
		return dato ;
	else
    return false ;
}
// -->
</script>
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
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/usdate.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
$tidspkt=date("U");
	
if ($tjek=$_GET['tjek']){
	$query = db_select("select tidspkt, hvem from ordrer where status < 3 and id = $tjek and hvem != '$brugernavn'");
	if ($row = db_fetch_array($query))	{
		if ($tidspkt-($row['tidspkt'])<3600){
			print "<BODY onLoad=\"javascript:alert('Ordren er i brug af $row[hvem]')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		}
		else {
		db_modify("update ordrer set hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$tjek'");}
	}
}
	
$q = db_SELECT("select box4 from grupper where art = 'DIV' and kodenr = '2'");
$r=db_fetch_array($q); 
$hurtigfakt=$r['box4'];
	
	
$id=$_GET['id'];
$sort=$_GET['sort'];
$fokus=$_GET['fokus'];
$submit=$_GET['funktion'];
$vis_kost=$_GET['vis_kost'];
$bogfor=1;

if (($kontakt=$_GET['kontakt'])&&($id)) {db_modify("update ordrer set kontakt='$kontakt' where id=$id");}

if ($_GET['konto_id']){
	$konto_id=$_GET['konto_id'];
	$query = db_select("select * from adresser where id = '$konto_id'");
	if ($row = db_fetch_array($query)) {
		$kontonr=$row['kontonr'];
		$firmanavn=addslashes($row['firmanavn']);
		$addr1=addslashes($row['addr1']);
		$addr2=addslashes($row['addr2']);
		$postnr=$row['postnr'];
		$bynavn=addslashes($row['bynavn']);
		$land=addslashes($row['land']);
		$betalingsdage=$row['betalingsdage'];
		$betalingsbet=$row['betalingsbet'];
		$cvrnr=$row['cvrnr'];
		$ean=$row['ean'];
		$institution=$row['institution'];
		$kontakt=$row['kontakt'];
		$notes=$row['notes'];
		$gruppe=$row['gruppe'];
		$kontoansvarlig=addslashes($row['kontoansvarlig']);
	}
	if ($kontoansvarlig){
		$query = db_select("select navn from ansatte where id='$kontoansvarlig'");
		$row = db_fetch_array($query);
		$ref=$row['navn'];
	} else {
		$row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'"));
		if ($row[ansat_id]) {
			$row = db_fetch_array(db_select("select navn from ansatte where id = $row[ansat_id]"));
			if ($row[navn]) {$ref=$row['navn'];}
		}
	}
	if ($gruppe){
		$r = db_fetch_array(db_select("select box1, box3, box4, box6 from grupper where art='DG' and kodenr='$gruppe'"));
		$tmp= substr($r[box1],1,1);
		$rabatsats=$r['box6']*1;
		$sprog=$r['box4'];
		$valuta=$r['box3'];
		$r = db_fetch_array(db_select("select box2 from grupper where art='SM' and kodenr='$tmp'"));
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
	$query = db_select("select ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc");
	if ($row = db_fetch_array($query)) $ordrenr=$row[ordrenr]+1;
	else {$ordrenr=1;}
	$ordredate=date("Y-m-d");
	db_modify("insert into ordrer (ordrenr, konto_id, kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,betalingsdage,betalingsbet,cvrnr,ean,institution,notes,art,ordredate,momssats,hvem,tidspkt,ref,valuta,sprog,kontakt) values ($ordrenr,'$konto_id','$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$notes','DO','$ordredate','$momssats','$brugernavn','$tidspkt','$ref','$valuta','$sprog','$kontakt')");
	$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc");
	if ($row = db_fetch_array($query)) {$id=$row[id];}
}
elseif($firmanavn) {
	$query = db_select("select tidspkt from ordrer where id=$id and hvem='$brugernavn'");
	if ($row = db_fetch_array($query)) {
		db_modify("update ordrer set kontonr='$kontonr', kundeordnr='$kundeordnr', firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', lev_navn='$lev_navn', lev_addr1='$lev_addr1',	lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', ean='$ean', institution='$institution', notes='$notes', hvem = '$brugernavn', tidspkt='$tidspkt' where id=$id");
	} else {			
		$query = db_select("select hvem from ordrer where id=$id");
		if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Ordren er overtaget af $row[hvem]')\">";}
		else {print "<BODY onLoad=\"javascript:alert('Du er blevet smidt af')\">";}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	}	
}

if ($_GET['vare_id']) {
	$query = db_select("select grupper.box6 as box6, ordrer.valuta as valuta, ordrer.ordredate as ordredate, ordrer.status as status from ordrer, adresser, grupper where ordrer.id='$id' and adresser.id=ordrer.konto_id and grupper.art='DG' and grupper.kodenr=adresser.gruppe");
	$row = db_fetch_array($query);
	if ($row[status]>2) {
		print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
		exit;
	} else {
		$rabatsats=$row['box6'];
		$valuta=$row['valuta'];
		$ordredate=$row['ordredate'];
		$vare_id=$_GET['vare_id'];
		$linjenr=substr($fokus,4);
		$query = db_select("select posnr from ordrelinjer where ordre_id = '$id' order by posnr desc");
		if ($row = db_fetch_array($query)) $posnr=$row[posnr]+1;
		else $posnr=1;

		$query = db_select("select * from varer where id = '$vare_id'");
		if ($row = db_fetch_array($query)) {
			if (!$varenr){$varenr=trim($row['varenr']);}
			if (!$beskrivelse){$beskrivelse=addslashes(trim($row['beskrivelse']));}
			if (!$enhed){$enhed=trim($row['enhed']);}
			if (!$pris){$pris=$row[salgspris]*1;}
			$serienr=$row['serienr'];
			$samlevare=$row['samlevare'];
		}
		if ($r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$row[gruppe]'"))) {
			$momsfri = $r2['box7'];
			if (($r2[box6]!=NULL)&&($rabatsats>$r2['box6'])) $rabatsats=$r2['box6'];
		}
		if(!$antal) $antal=1;
		$rabatsats=$rabatsats*1;
		if ($valuta && $valuta!='DKK') {
			if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr and valuta.valdate <= '$ordredate' order by valuta.valdate decs"))) {
				$pris=round($pris*100/$r['kurs'],2);
			} else {
				$tmp = dkdato($ordredate);
				print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
			}
		}
		if ($linjenr==0) {
			db_modify("insert into ordrelinjer (vare_id, antal, ordre_id, posnr, varenr, beskrivelse, enhed, pris, rabat, serienr, momsfri, samlevare) values ('$vare_id', '$antal', '$id','$posnr','$varenr','$beskrivelse','$enhed','$pris','$rabatsats','$serienr','$momsfri','$samlevare')");
		}
	}
}
if ($_POST['submit']) {
	$fokus=$_POST['fokus'];
	$submit = $_POST['submit'];
	$id = $_POST['id'];
	$ordrenr = $_POST['ordrenr'];
	$kred_ord_id = $_POST['kred_ord_id'];
	$art = $_POST['art'];
	$konto_id = $_POST['konto_id']*1;
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
	$ordredate = usdate($_POST['ordredato']);
	$levdato = trim($_POST['levdato']);
	$genfakt = trim($_POST['genfakt']);
	$fakturadato = trim($_POST['fakturadato']);
	$cvrnr = addslashes(trim($_POST['cvrnr']));
	$ean = addslashes(trim($_POST['ean']));
	$institution = addslashes(trim($_POST['institution']));
	$betalingsbet = $_POST['betalingsbet'];
	$betalingsdage = $_POST['betalingsdage']*1;
	$valuta = $_POST['valuta'];
	$projekt = $_POST['projekt'];
	$sprog = $_POST['sprog'];
	$lev_adr = trim($_POST['lev_adr']);
	$sum=$_POST['sum'];
	$linjeantal = $_POST['linjeantal'];
	$linje_id = $_POST['linje_id'];
	$kred_linje_id = $_POST['kred_linje_id'];
	$posnr = $_POST['posnr'];
	$status = $_POST['status'];
	$godkend = $_POST['godkend'];
	$omdan_t_fakt = $_POST['omdan_t_fakt'];
	$kreditnota = $_POST['kreditnota'];
	$ref = trim($_POST['ref']);
	$fakturanr = trim($_POST['fakturanr']);
	$momssats = trim($_POST['momssats']);
	$enhed = $_POST['enhed'];
	$vare_id = $_POST['vare_id'];
	$serienr = $_POST['serienr'];
	$tidl_lev = $_POST['tidl_lev'];
	$r = db_fetch_array(db_select("select grupper.box6 as box6 from adresser, grupper where adresser.kontonr='$kontonr' and adresser.art='D' and grupper.art='DG' and grupper.kodenr=adresser.gruppe"));
	$rabatsats=$r['box6']*1;
	if (strstr($submit,'Slet')) {
		db_modify("delete from ordrelinjer where ordre_id=$id");
		db_modify("delete from ordrer where id=$id");
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	}
	transaktion("begin");
	for ($x=0; $x<=$linjeantal;$x++) {
		$y="posn".$x;
		$posnr_ny[$x]=trim($_POST[$y]);
		$y="vare".$x;
		$varenr[$x]=trim($_POST[$y]);
		$y="anta".$x;
		$antal[$x]=trim($_POST[$y]);
		if (($tidl_lev[$x]<0) && ($tidl_lev[$x] < $antal[$x])) $antal[$x]=$tidl_lev[$x];
		if ($antal[$x]){
			$antal[$x]=usdecimal($antal[$x]);
			if ($art=='DK') $antal[$x]=$antal[$x]*-1;
		}
		$y="leve".$x;
		if ($hurtigfakt=='on') $leveres[$x]=$antal[$x];
		else {
			$leveres[$x]=trim($_POST[$y]);
			if ($leveres[$x]){
			$leveres[$x]=usdecimal($leveres[$x]);
			if ($art=='DK') {$leveres[$x]=$leveres[$x]*-1;}
			}
		}
		$y="beskrivelse".$x;
		$beskrivelse[$x]=addslashes(trim($_POST[$y]));
		$y="pris".$x;
		if (($x!=0)||($_POST[$y])||($_POST[$y]=='0')) {$pris[$x]=usdecimal($_POST[$y]);}
		$y="raba".$x;
		$rabat[$x]=usdecimal($_POST[$y]);
		if (($x>0)&&(!$rabat[$x])){$rabat=0;}
		$y="ialt".$x;
		$ialt[$x]=$_POST[$y];
		if (($godkend == "on")&&($status==0)) {
			$leveres[$x]=$antal[$x];
			if ($linje_id[$x]) batch($linje_id[$x]);
		}
		if ((!$sletslut) && ($posnr_ny[$x]=="->")) $sletstart=$x;  
		if (($sletstart) && ($posnr_ny[$x]=="<-")) $sletslut=$x;
	}
	if (($sletstart)&&($sletslut)&&($sletstart<$sletslut)) {
		for ($x=$sletstart; $x<=$sletslut; $x++) $posnr_ny[$x]="-";
	}
	if ($levdato) $levdate=usdate($levdato);
	if (($genfakt)&&($genfakt!='-')) $nextfakt=usdate($genfakt);
	elseif ($genfakt=='-' && $id) db_modify("update ordrer set nextfakt=NULL where id='$id'");
	if ($fakturadato) $fakturadate=usdate($fakturadato);
		
	if (($konto_id)&&(!$ref)&&($status<3)) {
		print "<BODY onLoad=\"javascript:alert('Vor ref. SKAL udfyldes')\">";
	}
	$bogfor=1;
	if ($godkend == "on"||$omdan_t_fakt == "on"||($status==0&&$hurtigfakt=="on")) $status++;
	if ($status==1) {
		if ($levdato) {$levdate=usdate($levdato);}
		if (!$levdate) {
			if ($hurtigfakt!='on') print "<BODY onLoad=\"javascript:alert('Leveringsdato sat til dags dato.')\">";
			$levdate=date("Y-m-d");
		}
		elseif ($levdate<$ordredate) {
			print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
			$status=0;
		}
	}
	if (strstr($submit, "Kred")) {
	$art='DK';
	$query = db_select("select id from ordrer where kred_ord_id = $id");
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
		$query = db_select("select * from adresser where kontonr = '$kontonr' and art='D'");
		if ($row = db_fetch_array($query)) {
		$konto_id=$row['id'];
		$firmanavn=$row['firmanavn'];
		$addr1=$row['addr1'];
			$addr2=$row['addr2'];
			$postnr=$row['postnr'];
			$bynavn=$row['bynavn'];
			$land=$row['land'];
			$kontakt=$row['kontakt'];
			$betalingsdage=$row['betalingsdage'];
			$betalingsbet=$row['betalingsbet'];
			$cvrnr=$row['cvrnr'];
			$notes=$row['notes'];
			$gruppe=$row['gruppe'];
			if ($gruppe) {
				$r = db_fetch_array(db_select("select box1, box3, box4, box6 from grupper where art='DG' and kodenr='$gruppe'"));
				$tmp= substr($r[box1],1,1);
				$std_rabat=$r['box6']*1;
				if (!$gl_id) {# valuta & sprog skal beholdes v. ordrekopiering.
					$sprog=$r['box4'];
			 		$valuta=$r['box3'];
				}
				$r = db_fetch_array(db_select("select box2 from grupper where art='SM' and kodenr='$tmp'"));
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
	}
	if ((!$id)&&($konto_id)&&($firmanavn)){
		$query = db_select("select ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc");
		if ($row = db_fetch_array($query)) {$ordrenr=$row[ordrenr]+1;}
		else {$ordrenr=1;}
		$qtext="insert into ordrer (ordrenr, konto_id, kontonr, kundeordnr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, lev_navn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, momssats, status, ref, lev_adr, valuta, projekt, sprog) values ($ordrenr, '$konto_id','$kontonr','$kundeordnr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$lev_navn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$notes','$art','$ordredate','$momssats', $status, '$ref','$lev_adr','$valuta','$projekt','$sprog')";
		db_modify($qtext);
		$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc");
		if ($row = db_fetch_array($query)) {
			$id=$row[id];
			if ($gl_id) {
				$r=(db_fetch_array(db_select("select levdate, ordredate, fakturadate, nextfakt from ordrer where id='$gl_id'")));
				if ($r['nextfakt']) {
					$nextfakt=find_nextfakt($r['fakturadate'], $r['nextfakt']);
					db_modify("update ordrer set levdate='$r[nextfakt]',fakturadate='$r[nextfakt]',nextfakt='$nextfakt',ordredate='$r[ordredate]' where id = $id");
				}
			}
		}
	}
	elseif(($firmanavn)&&($status<3)) {
		$sum=0;
		for($x=1; $x<=$linjeantal; $x++) {
			if (!$varenr[$x]) {$antal[$x]=0; $pris[$x]=0; $rabat[$x]=0;}
			if ((($antal[$x]>0)&&($leveres[$x]<0))||(($antal[$x]<0)&&($leveres[$x]>0))) {
				print "<BODY onLoad=\"javascript:alert('Der skal v&aelig;re samme fortegen i antal og l&eacute;ver! (Pos. $posnr_ny[$x] nulstillet)')\">";
				$leveres[$x]=0;
			}
			elseif ($vare_id[$x]) {
				if ($art=='DK') {
					if ($antal[$x]>0) {
						$antal[$x]=$antal[$x]*-1;
						print "<BODY onLoad=\"javascript:alert('Der kan ikke krediteres et negativt antal. Antal reguleret (Varenr: $varenr[$x])')\">";
					}					 
					$query = db_select("select antal from ordrelinjer where id = $kred_linje_id[$x] and vare_id=$vare_id[$x]"); #Vare_id er med for ikke at taelle delvarer med v. samlevarer.
					$row = db_fetch_array($query);
					if ($antal[$x]+$row[antal]<0) {
						$antal[$xDKK]=$row[antal]*-1;
						print "<BODY onLoad=\"javascript:alert('Der kan max krediteres $row[antal]. Antal reguleret (Varenr: $varenr[$x])')\">";
					}
				} elseif (($antal[$x]<0)&&($kred_linje_id[$x]>0)) {
					$query = db_select("select antal from ordrelinjer where id = $kred_linje_id[$x] and vare_id=$vare_id[$x]");
					$row = db_fetch_array($query);
					if ($antal[$x]+$row[antal]<0) {
						$antal[$x]=$row[antal]*-1;
						print "<BODY onLoad=\"javascript:alert('Der kan max tages $row[antal] retur. Antal reguleret (Varenr: $varenr[$x])')\">";
					}
				}
				$tidl_lev[$x]=0;
				$query = db_select("select antal from batch_salg where linje_id = $linje_id[$x] and vare_id=$vare_id[$x]"); #Vare_id er med for ikke at taelle delvarer med v. samlevarer.
				while ($row = db_fetch_array($query)) {$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];}
				if ((($tidl_lev[$x]<0)&&($antal[$x]>$tidl_lev[$x]))||(($tidl_lev[$x]>0)&&($antal[$x]<$tidl_lev[$x]))){
					$antal[$x]=$tidl_lev[$x];
					print "<BODY onLoad=\"javascript:alert('Der er allerede leveret $tidl_lev[$x]. Antal reguleret (Varenr: $varenr[$x])')\">";
				}	
				elseif ($antal>0) {
					if (($tidl_lev[$x]<$antal[$x])&&($status>1)) {
						if ($omdan_t_fakt == "on") {print "<BODY onLoad=\"javascript:alert('Du kan ikke fakturere f&oslash;r alt er leveret')\">";}
						$status=1;
					}
					$query = db_select("select antal from reservation where linje_id = $linje_id[$x]");
					while ($row = db_fetch_array($query)) {$reserveret[$x]=$reserveret[$x]+$row[antal];}
					if (($antal[$x]<$tidl_lev[$x]+$reserveret[$x])&&($antal[$x]>0)) {
						$diff=$tidl_lev[$x]+$reserveret[$x]-$antal[$x];
						while ($diff>0) {
							$query = db_select("select * from reservation where linje_id = $linje_id[$x] order by batch_kob_id desc");
							$row = db_fetch_array($query);
							if ($diff < $row[antal]) {
								$temp = $row[antal] - $diff;
								if ($row[batch_kob_id]) {db_modify("update reservation set antal = $temp where linje_id=$linje_id[$x] and batch_kob_id=$row[batch_kob_id] and antal=$row[antal] and vare_id=$row[vare_id]");}
							 	else {db_modify("update reservation set antal = $temp where linje_id=$linje_id[$x] and antal=$row[antal] and vare_id=$row[vare_id]");}
								$diff=0;															
							}	
							elseif ($diff >= $row[antal]) {
								if ($row[batch_kob_id]) {db_modify("delete from reservation where linje_id=$linje_id[$x] and batch_kob_id=$row[batch_kob_id] and antal=$row[antal] and vare_id=$row[vare_id]");}
								else {db_modify("delete from reservation where linje_id=$linje_id[$x] and antal=$row[antal] and vare_id=$row[vare_id]");}
								$diff=$diff - $row[antal];							
							} 
						} 
					}	
				} 
				$query = db_select("select antal from batch_salg where linje_id = $linje_id[$x]");
				while ($row = db_fetch_array($query)) {$modtaget[$x]=$modtaget[$x]+$row[antal];}
				if (($antal[$x]>$modtaget[$x])&&($modtaget[$x]<0)) {
					$antal[$x]=$modtaget[$x];
					print "<BODY onLoad=\"javascript:alert('Der er allerede modtaget $temp. Antal reguleret (Varenr: $varenr[$x])')\">";
				}	
			}
			if ($posnr_ny[$x]=='-') {
				if ($vare_id[$x]) {
					$query = db_select("select * from batch_kob where linje_id = $linje_id[$x] and ordre_id=$id and vare_id = $vare_id[$x]");
					if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke slette en varelinje n&aring;r der &eacute;r modtaget vare(r)')\">";}
					else {
						$query = db_select("select * from batch_salg where linje_id = $linje_id[$x] and ordre_id=$id and vare_id = $vare_id[$x]");
						if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke slette en varelinje n&aring;r der &eacute;r leveret vare(r)')\">";}
						else {
							db_modify("delete from ordrelinjer where id='$linje_id[$x]'");
							db_modify("delete from reservation where linje_id='$linje_id[$x]'");
							db_modify("delete from reservation where batch_salg_id='-$linje_id[$x]'");
						}
					}
				} else {db_modify("delete from ordrelinjer where id='$linje_id[$x]'");}
			 } elseif ((!strstr($submit,"Kopi"))&&(!strstr($submit,"Udskriv"))) {
				if ((!strpos($posnr_ny[$x], '+'))&&($id)) {
					$posnr_ny[$x]=round($posnr_ny[$x],0);
					if ($posnr_ny[$x]>=1) {db_modify("update ordrelinjer set posnr='$posnr_ny[$x]' where id='$linje_id[$x]'");}
					else print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte et - (minus) som pos nr for at slette en varelinje')\">";
				}
				if (($vare_id[$x])&&($r=db_fetch_array(db_select("SELECT grupper.box6 as box6 FROM grupper, varer WHERE varer.id='$vare_id[$x]' and varer.gruppe=grupper.kodenr and grupper.art='VG'")))) {
					if (($r[box6]!=NULL)&&($rabat[$x]>$r['box6'])) {
						$rabat[$x]=$r['box6'];
						print "<BODY onLoad=\"javascript:alert('Max rabat for varenummer: $varenr[$x] er $rabat[$x]%')\">";
					}
				}
				if (!$antal[$x]){$antal[$x]=1;}
				$sum=$sum+($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				if (!$leveres[$x]){$leveres[$x]=0;}
				if (!$rabat[$x])$rabat[$x]='0';
				db_modify("update ordrelinjer set varenr='$varenr[$x]', beskrivelse='$beskrivelse[$x]', antal='$antal[$x]', leveres='$leveres[$x]', pris='$pris[$x]', rabat='$rabat[$x]' where id='$linje_id[$x]'");
				if ((strpos($posnr_ny[$x], '+'))&&($id)) indsaet_linjer($id, $linje_id[$x], $posnr_ny[$x]);
			}
#			if (strlen($fakturadate)>5){db_modify("update ordrer set fakturadate='$fakturadate' where id=$id");}
		}
		if (($posnr_ny[0])&&(!strstr($submit,'Opslag'))) {
			if ($varenr[0]) {
				$tmp=strtoupper($varenr[0]);
				$query = db_select("SELECT * FROM varer WHERE upper(varenr) = '$tmp'");
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
						if ($r2= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr and valuta.valdate <= '$ordredate' order by valuta.valdate desc"))) {
							$pris[0]=round($pris[0]*100/$r2['kurs'],2);
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
					db_modify("insert into ordrelinjer (vare_id, ordre_id, posnr, varenr, beskrivelse, enhed, antal, pris, rabat, serienr, momsfri, samlevare) values ($vare_id[0], '$id','$posnr_ny[0]','$varenr[0]','$beskrivelse[0]','$enhed[0]','$antal[0]','$pris[0]','$rabat[0]','$serienr[0]','$momsfri[0]','$samlevare[0]')");
				} else  {
					$submit='Opslag';
					$varenr[0]=$varenr[0]."*";
				}
			}
			elseif ($beskrivelse[0]) {db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$id','$posnr_ny[0]','$beskrivelse[0]')");}
		}
		if ($id) { 
			$query = db_select("select tidspkt, hvem from ordrer where status < 3 and id = $id and hvem != '$brugernavn'");
			if ($row = db_fetch_array($query)) {
				if ($tidspkt-($row['tidspkt'])<3600) {
					print "<BODY onLoad=\"javascript:alert('Orderen er overtaget af $row[hvem]')\">";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
				}
			} else {
				$tmp="";
				if (strlen($levdate)>6) $tmp=",levdate='$levdate'";
				if (strlen($fakturadate)>6) $tmp=$tmp.",fakturadate='$fakturadate'";
				if (strlen($nextfakt)>6) $tmp=$tmp.",nextfakt='$nextfakt'";
					$opdat="update ordrer set kontonr='$kontonr', kundeordnr='$kundeordnr', firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', kontakt='$kontakt', lev_navn='$lev_navn',	lev_addr1='$lev_addr1',	lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', ean='$ean', institution='$institution', notes='$notes', ordredate='$ordredate', status=$status, ref='$ref', fakturanr='$fakturanr', lev_adr='$lev_adr', hvem = '$brugernavn', tidspkt='$tidspkt', valuta='$valuta', projekt='$projekt', sprog='$sprog' $tmp where id=$id";
				db_modify($opdat);
			}
		}
	}
########################## KOPIER #################################	
	if ((strstr($submit,'Kopi'))||(strstr($submit,'Kred')))	{
		if ((strstr($submit,'Kred'))&&($kred_ord_id)) db_modify("update ordrer set kred_ord_id='$kred_ord_id' where id='$id'");
		for($x=1; $x<=$linjeantal; $x++) {
			if (!$vare_id[$x]) {
				$query = db_select("select id from varer where varenr = '$varenr[$x]'");
				if ($row = db_fetch_array($query)) {$vare_id[$x]=$row[id];}
			}
			if ($vare_id[$x]){
				if (strstr($submit,'Kopi')) {$tmp=$antal[$x];}
				else {$tmp=$antal[$x]*-1;}
				if ($r1=db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[$x]'"))) {
					if ($r2=db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'"))) {$momsfri[$x] = $r2['box7'];}
				}	
				$r3=db_fetch_array(db_select("select samlevare, kostpris from ordrelinjer where id = '$linje_id[$x]'"));
				$kostpris[$x]=$r3[kostpris]*1;
				db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, lev_varenr, serienr, kred_linje_id, momsfri, samlevare, kostpris) values ('$id','$posnr_ny[$x]','$varenr[$x]','$vare_id[$x]','$beskrivelse[$x]','$enhed[$x]', $tmp, '$pris[$x]','$rabat[$x]','$lev_varenr[$x]','$serienr[$x]','$linje_id[$x]','$momsfri[$x]','$r3[samlevare]','$kostpris[$x]')");
			}
			else {db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$id','$posnr_ny[$x]','$beskrivelse[$x]')");}
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
			$query = db_select("select lev_nr from batch_salg where ordre_id=$id and lev_nr=1");
			if ($row = db_fetch_array($query)) {$formular=3; $ps_fil="udskriftsvalg.php";}
			else {$temp="rdrebek";	$formular=2; $ps_fil="formularprint.php";}
		 }
		else {$temp="ilbud"; $formular=1; $ps_fil="formularprint.php";}
		print "<BODY onLoad=\"JavaScript:window.open('$ps_fil?id=$id&formular=$formular' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
	}	

##########################OPSLAG################################

	if ((strstr($submit,'Opslag'))||((strstr($submit,'Gem'))&&(!$id))) {
		if ((strstr($fokus,'kontonr'))&&(!$id)) {kontoopslag($sort, $fokus, $id, $kontonr);}
		if ((strstr($fokus,'firmanavn'))&&(!$id)) {kontoopslag($sort, $fokus, $id, $firmanavn);}
		if ((strstr($fokus,'addr1'))&&(!$id)) {kontoopslag($sort, $fokus, $id, $addr1);}
		if ((strstr($fokus,'addr2'))&&(!$id)) {kontoopslag($sort, $fokus, $id, $addr2);}
		if ((strstr($fokus,'postnr'))&&(!$id)) {kontoopslag($sort, $fokus, $id, $postnr);}
		if ((strstr($fokus,'bynavn'))&&(!$id)) {kontoopslag($sort, $fokus, $id, $bynavn);}
		if ((strstr($fokus,'vare'))&&($art!='DK')) {vareopslag($sort, 'varenr', $id, $vis_kost, $ref, $varenr[0]);}
		if ((strstr($fokus,'besk'))&&($art!='DK')) {vareopslag($sort, 'beskrivelse', $id, $vis_kost, $ref, $beskrivelse[0]);}
		if ((strstr($fokus,'kontakt'))&&($id)){ansatopslag($sort, $fokus, $id, $vis, $kontakt);}
		elseif (strstr($fokus,'kontakt')) {kontoopslag($sort, $fokus, $id, $kontakt);}
	}

########################## DELFAKTURER  - SKAL VAERE PLACERET FOER "FAKTURER" ################################
	if ($submit=='Delfakturer') {
		$sum=0; $moms=0;
		$ny_sum=0; $ny_moms=0;
		transaktion("begin");
		$r=db_fetch_array(db_select("select * from ordrer where id = '$id'"));
		db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, kundeordnr, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, momssats, tidspkt, ref, status, lev_navn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt, valuta, projekt, sprog) values ('$r[ordrenr]','$r[konto_id]','$r[kontonr]','$r[firmanavn]','$r[addr1]','$r[addr2]','$r[postnr]','$r[bynavn]','$r[land]','$r[kontakt]','$r[kundeordnr]','$r[betalingsdage]','$r[betalingsbet]','$r[cvrnr]','$r[ean]','$r[institution]','$r[notes]','$r[art]','$r[ordredate]','$r[momssats]','$r[tidspkt]','$r[ref]','$r[status]','$r[lev_navn]','$r[lev_addr1]','$r[lev_addr2]','$r[lev_postnr]','$r[lev_bynavn]','$r[lev_kontakt]','$r[valuta]','$r[projekt]','$r[sprog]')");
		$q = db_select("select id from ordrer where ordrenr=$ordrenr and art='$art' and tidspkt='$tidspkt' order by id desc");
		if ($r = db_fetch_array($q)) $ny_id=$r[id];
		for($x=1; $x<=$linjeantal; $x++) {
			if ($vare_id[$x]){
#				if ($r1=db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[$x]'"))) {
#					if ($r2=db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'"))) {$momsfri[$x] = $r2['box7'];}
#				}	
				$r3=db_fetch_array(db_select("select momsfri, leveret, samlevare, kostpris from ordrelinjer where id = '$linje_id[$x]'"));
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
						db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, lev_varenr, serienr, kred_linje_id, momsfri, samlevare) values ('$ny_id','$posnr_ny[$x]','$varenr[$x]','$vare_id[$x]','$beskrivelse[$x]','$enhed[$x]', $ny_antal, '$pris[$x]','$rabat[$x]','$lev_varenr[$x]','$serienr[$x]','$linje_id[$x]','$momsfri[$x]','$r3[samlevare]')");
						db_modify("update ordrelinjer set antal='$antal[$x]' where id=$linje_id[$x]");
					}
					else {
						db_modify("update ordrelinjer set ordre_id='$ny_id' where id=$linje_id[$x]");
					}
				}
			}
			else db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$ny_id','$posnr_ny[$x]','$beskrivelse[$x]')");
		}
		db_modify("update ordrer set sum = '$sum', moms = '$moms', status='2' where id='$id'");
		db_modify("update ordrer set sum = '$ny_sum', moms = '$ny_moms', hvem = '', tidspkt= '' where id='$ny_id'");

#exit;
		
		$submit='Fakturer';
		transaktion("commit");
	}
########################## FAKTURER   - SKAL VAERE PLACERET EFTER "DELFAKTURER" ################################
	if ($submit=='Fakturer'&&$hurtigfakt=='on') {
		
	
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'");
		if (!$row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke fakturere uden ordrelinjer')\">";}
		else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=levering.php?id=$id&hurtigfakt=on\">";}
	} elseif ($submit=='Fakturer'&&$bogfor!=0) {
			$query = db_select("select * from ordrelinjer where ordre_id = '$id'");
		if (!$row = db_fetch_array($query)) {Print "Du kan ikke fakturere uden ordrelinjer";}
		else {print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?id=$id\">";}
		#	else {bogfor($id);}
	}

############################ LEVER ################################

	if ((strstr($submit,'Lev'))&&($bogfor!=0)) {
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'");
		if (!$row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke levere uden ordrelinjer')\">";}
		else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=levering.php?id=$id\">";}
	}
	print "<meta http-equiv=\"refresh\" content=\"3600;URL=../includes/luk.php\">";

###########################################################################

ordreside($id, $regnskab);


function ordreside($id, $regnskab)
{
	global $bgcolor;
	global $bgcolor5;
	global $font;
	global $bogfor;
	global $brugernavn;
	global $fokus;
	global $fakturadate;
	global $hurtigfakt;

if (!$id) $fokus='kontonr';
else $fokus='vare0';

	print "<form name=ordre action=ordre.php method=post>";
	if ($id) {
		$query = db_select("select * from ordrer where id = '$id'");
		$row = db_fetch_array($query);
		$konto_id = $row['konto_id'];
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
		$betalingsbet = trim($row['betalingsbet']);
		$betalingsdage = $row['betalingsdage'];
#		$momssats = $momssats;
		$valuta=$row['valuta'];
		$valutakurs=$row['valutakurs'];
		$projekt=$row['projekt'];
		$sprog=$row['sprog'];
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
		$query = db_select("select id, ordrenr from ordrer where kred_ord_id = '$id'");
		while ($row2 = db_fetch_array($query)) {
			$x++;
			if ($x>1) {$krediteret=$krediteret.", ";}
			$krediteret=$krediteret."<a href=ordre.php?id=$row2[id]>$row2[ordrenr]</a>";
		}	
	}
	
	if ($art=='DK') { 
		$query = db_select("select ordrenr from ordrer where id = '$kred_ord_id'");
		$row2 = db_fetch_array($query);
		sidehoved($id, "ordreliste.php", "", "", "Kunde kreditnota $ordrenr (kreditering af ordre nr: <a href=ordre.php?id=$kred_ord_id>$row2[ordrenr]</a>)");
	}
	elseif ($krediteret) {sidehoved($id, "ordreliste.php", "", "", "Kundeordre $ordrenr ( krediteret p&aring; KN nr: $krediteret )");}
	else {
		if ($status<1) {$temp='Tilbud';}
		elseif ($status<2) {$temp='Ordre';}
		else {$temp='Faktura';}
		sidehoved($id, "ordreliste.php", "", "", "Kundeordre $ordrenr - $temp");
	}

	if (!$status){$status=0;}
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
		print "<input type=hidden name=betalingsbet value=\"$betalingsbet\">";
		print "<input type=hidden name=betalingsdage value=\"$betalingsdage\">";
		print "<input type=hidden name=momssats value=\"$momssats\">";
		print "<input type=hidden name=ref value=\"$ref\">";
		print "<input type=hidden name=fakturanr value=\"$fakturanr\">";
		print "<input type=hidden name=lev_adr value=\"$lev_adr\">";
		print "<input type=hidden name=valuta value=\"$valuta\">";
		
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" valign = \"top\"><tbody>";
		$ordre_id=$id;
		print "<tr><td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
		print "<tr><td width=100>$font<small><b>Kontonr</td><td width=100>$font<small>$kontonr</td></tr>\n";
		print "<tr><td>$font<small><b>Firmanavn</td><td>$font<small>$firmanavn</td></tr>\n";
		print "<tr><td>$font<small><b>Adresse</td><td>$font<small>$addr1</td></tr>\n";
		print "<tr><td>$font<small></td><td>$font<small>$addr2</td></tr>\n";
		print "<tr><td>$font<small><b>Postnr, by</td><td>$font<small>$postnr $bynavn</td></tr>\n";
		print "<tr><td>$font<small><b>Land</td><td>$font<small>$land</td></tr>\n";
		print "<tr><td>$font<small><b>Att.:</td><td>$font<small>$kontakt</td></tr>\n";
		print "<tr><td>$font<small><b>Ordre nr.</td><td>$font<small>$kundeordnr</td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
		print "<tr><td>$font<small><b>CVR.nr</td><td>$font<small>$cvrnr</td></tr>\n";
		print "<tr><td>$font<small><b>EAN.nr</td><td>$font<small>$ean</td></tr>\n";
		print "<tr><td>$font<small><b>Institution</td><td>$font<small>$institution</td></tr>\n";
		print "<tr><td width=100>$font<small><b>Ordredato</td><td width=100>$font<small>$ordredato</td></tr>\n";
		print "<tr><td>$font<small><b>Lev. dato</td><td>$font<small>$levdato</td></tr>\n";
		print "<tr><td>$font<small><b>Fakturadato</td><td>$font<small>$fakturadato</td></tr>\n";
		print "<tr><td>$font<small><b>Genfaktureres</td><td>$font<small>$genfakt</td></tr>\n";
		print "<tr><td>$font<small><b>Betaling</td><td>$font<small>$betalingsbet&nbsp;+&nbsp;$betalingsdage</td>";
		print "<tr><td>$font<small><b>Vor ref.</td><td>$font<small>$ref</td></tr>\n";
		print "<tr><td>$font<small><b>Fakturanr</td><td>$font<small>$fakturanr</td></tr>\n";
		$tmp=dkdecimal($valutakurs);
		if ($valuta) print "<tr><td>$font<small><b>Valuta / Kurs</td><td>$font<small>$valuta / $tmp</td></tr>\n";
		if ($projekt) print "<tr><td>$font<small><b>Projekt</td><td>$font<small>$projekt</td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=33%><table cellpadding=0 cellspacing=0 border = 0 width=240>";
		print "<tr><td>$font<small><b>Leveringsadresse</td></tr>\n";
		print "<tr><td>$font<small>Firmanavn</td><td colspan=2>$font<small>$lev_navn</td></tr>\n";
		print "<tr><td>$font<small>Adresse</td><td colspan=2>$font<small>$lev_addr1</td></tr>\n";
		print "<tr><td>$font<small></td><td colspan=2>$font<small>$lev_addr2</td></tr>\n";
		print "<tr><td>$font<small>Postnr, By</td><td>$font<small>$lev_postnr $lev_bynavn</td></tr>\n";
		print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small>$lev_kontakt</td></tr>\n";
		print "</td></tr></tbody></table></td>";
		print "</td></tr><tr><td align=center colspan=3><table cellpadding=0 cellspacing=0 border=1 width=100%><tbody>";
		print "<tr><td colspan=7></td></tr><tr>";
		print "<td align=center>$font<small><b>pos</td><td align=center>$font<small><b>varenr</td><td align=center>$font<small><b>ant.</td><td align=center>$font<small><b>enhed</td><td align=center>$font<small><b>beskrivelse</td><td align=center>$font<small><b>pris</td><td align=center>$font<small><b>%</td><td align=center>$font<small><b>i alt</td><td></td>";
		print "</tr>\n";
		$x=0;
		if (!$ordre_id) $ordre_id=0;
		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr");
		while ($row = db_fetch_array($query))	{
			if (($row[posnr]>0)&&($row[samlevare]<1)) {
				$x++;
				$linje_id[$x]=$row['id'];
				$vare_id[$x]=$row['vare_id'];
				$posnr[$x]=$row['posnr'];
				$varenr[$x]=stripslashes(htmlentities($row['varenr']));
				$lev_varenr[$x]=stripslashes(htmlentities($row['lev_varenr']));
				$beskrivelse[$x]=stripslashes(htmlentities($row['beskrivelse']));
				$enhed[$x]=stripslashes(htmlentities($row['enhed']));
				$pris[$x]=$row['pris'];
				$rabat[$x]=$row['rabat'];
				$antal[$x]=$row['antal'];
				$momsfri[$x]=$row['momsfri'];
				$serienr[$x]=stripslashes(htmlentities($row['serienr']));
				$kostpris[$x]=$row['kostpris'];
			}
		}	
		$linjeantal=$x;
		print "<input type=hidden name=linjeantal value=$x>";
		$totalrest=0;
		$sum=0;
		for ($x=1; $x<=$linjeantal; $x++) {
			if (!$vare_id[$x]) {
				$query = db_select("select id from varer where varenr = '$varenr[$x]'");
				if ($row = db_fetch_array($query)) {$vare_id[$x]=$row[id];}
			}
			if (($varenr[$x])&&($vare_id[$x])) {
				$query = db_select("select provisionsfri from varer where id = '$vare_id[$x]' and provisionsfri='on'");
				if ($row = db_fetch_array($query)) {$provisionsfri[$x]=$row['provisionsfri'];}
#				if (!$kostpris[$x]) {
					$query = db_select("select * from batch_salg where linje_id = '$linje_id[$x]'");
					$y=0;
					while ($row = db_fetch_array($query)) {
						$y=$y+10000;
						$z=$y+$x;
						$r2 = db_fetch_array(db_select("select antal, ordre_id, pris, fakturadate, linje_id from batch_kob where id = $row[batch_kob_id]"));
						$kobs_ordre_id[$z]=$r2['ordre_id'];
						if ($kobs_ordre_id[$z]) {
							if ($r2['fakturadate']<2000-01-01) {$r2=db_fetch_array(db_select("select pris from ordrelinjer where id = $r2[linje_id]"));}	
							$k_stk_ant[$z]=$row['antal'];
#							if (!$kostpris[$x]) $kostpris[$x]=$kostpris[$x]+$r2[pris]*$row[antal];
							if ($y>10000) $kostpris[$x]=$kostpris[$x]+$r2['pris']*$row['antal'];
							else $kostpris[$x]=$r2['pris']*$row['antal'];
							$kostpris[$z]=dkdecimal($r2['pris']);
						}
						else {
							$r2 = db_fetch_array(db_select("select kostpris from varer where id = '$vare_id[$x]'"));
							$kostpris[$x]=$r2['kostpris']*-$antal[$x];
						}
						if ($valutakurs && $valutakurs!=100) $kostpris[$x]=$kostpris[$x]*100/$valutakurs;
						if ($kobs_ordre_id[$z]) {
							$q3 = db_select("select ordrenr from ordrer where id = $kobs_ordre_id[$z]");
							$r3 = db_fetch_array($q3);
							$kobs_ordre_nr[$z]=$r3[ordrenr];
						}
					}
					if ($kobs_ordre_id[$z]) $ko_ant[$x]=$y/10000;
					$kostpris[$x]=$kostpris[$x]*1;	
					if ($art=='DO') db_modify("update ordrelinjer set kostpris=$kostpris[$x] where id = $linje_id[$x]");
#				}
				$ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				if ($provisionsfri[$x]) {
					if ($art=='DO') $kostpris[$x]=$ialt;
					else $kostpris[$x]=$ialt*-1;
				}
#				$db[$x]=$ialt-$kostpris[$x];
				if ($art=='DO') $db[$x]=$ialt-$kostpris[$x];
				else $db[$x]=-$ialt-$kostpris[$x];
				$ialt=round($ialt,2);
				if ($ialt!=0) {
					$dg[$x]=$db[$x]*100/$ialt;
					if ($art=='DO') $dk_dg[$x]=dkdecimal($dg[$x]);
					else $dk_dg[$x]=dkdecimal($dg[$x]*-1);
				}
				$dk_db[$x]=dkdecimal($db[$x]);
#				if ($art=='DO') $dk_db[$x]=dkdecimal($db[$x]);
#				else $dk_db[$x]=dkdecimal($db[$x]*-1);
				$dk_kostpris[$x]=dkdecimal($kostpris[$x]);
				 $sum=$sum+$ialt;
				 if ($momsfri[$x]!='on') {$momssum=$momssum+$ialt;}
				$dkpris=dkdecimal($pris[$x]);
				$dkrabat=dkdecimal($rabat[$x]);
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
			print "<input type=hidden name=posn$x value=$posnr[$x]><td align=right>$font<small>$posnr[$x]</td>";
			print "<input type=hidden name=vare$x value=\"$varenr[$x]\"><td>$font<small>$varenr[$x]</td>";
			print "<input type=hidden name=anta$x value=$dkantal[$x]><td align=right>$font<small>$dkantal[$x]</td>";
			print "<input type=hidden name=enhed[$x] value=\"$enhed[$x]\"><td align=right>$font<small>$enhed[$x]</td>";
			print "<input type=hidden name=beskrivelse$x value=\"$beskrivelse[$x]\"><td>$font<small>$beskrivelse[$x]</td>";
			print "<input type=hidden name=pris$x value=$dkpris><td align=right>$font<small>$dkpris</td>";
			print "<input type=hidden name=raba$x value=$dkrabat><td align=right>$font<small>$dkrabat</td>";
			print "<input type=hidden name=serienr[$x] value=\"$serienr[$x]\"";
			print "<input type=hidden name=vare_id[$x] value=$vare_id[$x]>";
			print "<input type=hidden name=lev_varenr[$x] value=\"$lev_varenr[$x]\">";
			$dbsum=$dbsum+$db[$x]; 
			if ($ialt) {
				if ($art=='DK') {$ialt=$ialt*-1;}
				print "<td align=right>$font<span title= 'kostpris $dk_kostpris[$x] * db: $dk_db[$x] * dg: $dk_dg[$x]%'><small>".dkdecimal($ialt)."</td>";
			}
			else {print "<td><br></td>";}
			if ($ko_ant[$x]>=1) {
				for ($y=1; $y<=$ko_ant[$x]; $y++) {
					$z=$y*10000+$x;
					$spantext="K&oslash;bsordre&nbsp;$kobs_ordre_nr[$z] \n antal:&nbsp;$k_stk_ant[$z]&nbsp;á&nbsp;$kostpris[$z]";
					print "<td align=right onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$kobs_ordre_id[$z]','ordre' ,'left=10,top=10,width=800,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no');k_ordre.focus();\"onMouseOver=\"this.style.cursor = 'pointer'\"><span title='$spantext'><img src=../ikoner/opslag.png></td>";
				}
			}
			else {print "<td><br></td>";}
			if ($serienr[$x]) {print "<td onClick=\"serienummer($linje_id[$x])\" onMouseOver=\"this.style.cursor = 'pointer'\" align=right>$font<small><span title= 'Serienumre '><img alt=\"Serienummer\" src=../ikoner/serienr.png></td>";}
#			else {print "<td><br></td>";}
		}
		$tmp=$momssum/100*$momssats+0.000001; #ellers runder den ned ved v. 0,5 re ??
		$moms=round($tmp,2);
		if ($submit=='Delfakturer'||$submit=='Fakturer') db_modify("update ordrer set sum=$sum, kostpris=$kostpris[0], moms=$moms where id=$id");
		if ($art=='DK') {
			$sum=$sum*-1;
			$momssum=$momssum*-1;		
		}
		$tmp=$momssum/100*$momssats+0.000001; #ellers runder den ned ved v. 0,5 ??
		$moms=round($tmp,2);
		$ialt=$sum+$moms;
		print "<tr><td colspan=9><br></td></tr>\n";
		print "<tr><td colspan=7><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=100%><tbody>";
		print "<tr bgcolor=\"$bgcolor5\">";
		print "<td align=center>$font<small>Ordresum</td><td align=center>$font<small>".dkdecimal($sum)."</td>";
#		$db=$dbsum;
#		if ($art=='DK') $dbsum=$dbsum*-1;
		print "<td align=center>$font<small>D&aelig;kningsbidrag:&nbsp;".dkdecimal($dbsum)."</td>";
		if ($sum) $dg_sum=($dbsum*100/$sum);
		else $dg_sum=dkdecimal(0);
		print "<td align=center>$font<small>D&aelig;kningsgrad:&nbsp;".dkdecimal($dg_sum)."%</td>";
		print "<td align=center>$font<small>Moms</td><td align=center>$font<small>".dkdecimal($moms)."</td>";
		print "<td align=center>$font<small>I alt</td><td align=right>$font<small>".dkdecimal($ialt)."</td>";
		print "</tbody></table></td></tr>\n";
		print "<tr><td align=center colspan=8>";
		print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>";
		if ($art!='DK') print "<td align=center><input type=submit value=\"&nbsp;Kopier&nbsp;\" name=\"submit\"></td>";
		if ( strlen("which ps2pdf")) { 
			print "<td align=center><input type=submit value=\"&nbsp;Udskriv&nbsp;\" name=\"submit\"></td>";
		} else { 
			print "<td align=center><input type=submit value=\"&nbsp;Udskriv&nbsp;\" name=\"submit\" disabled=\"disabled\"></td>";
		}
		if (($art!='DK')&&(!$krediteret)) print "<td align=center><input type=submit value=\"Krediter\" name=\"submit\"></td>";
	}
	else { ############################# ordren er ikke faktureret #################################
		#initiering af variabler
		$antal_ialt=0; #10.10.2007
		$leveres_ialt=0; #10.10.2007
		$tidl_lev_ialt=0; #10.10.2007


		print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\"	valign = \"top\"><tbody>";
#		if ($status>=2 && !$fakturadato) {$fakturadato=dkdato(date("Y-m-d"));}
		$ordre_id=$id;
		print "<tr><td width=30%><table cellpadding=0 cellspacing=0 border=0>";
		print "<tr><td witdh=100>$font<small>Kontonr.</td><td colspan=2>$font<small>";
		if (trim($kontonr)) {print "<input readonly=readonly size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\"></td></tr>\n";}
		else {print "<input type=text size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\"></td></tr>\n";}
		print "<tr><td>$font<small>Firmanavn</td><td colspan=2>$font<small><input type=text size=25 name=firmanavn onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$firmanavn\"></td></tr>\n";
		print "<tr><td>$font<small>Adresse</td><td colspan=2>$font<small><input type=text size=25 name=addr1 onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$addr1\"></td></tr>\n";
		print "<tr><td>$font<small></td><td colspan=2>$font<small><input type=text size=25 name=addr2 onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$addr2\"></td></tr>\n";
		print "<tr><td>$font<small>Postnr, By</td><td>$font<small><input type=text size=4 name=postnr onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$postnr\"></td>";
		print "<td><input type=text size=19 name=bynavn onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$bynavn\"></td></tr>\n";
		print "<tr><td>$font<small>Land</td><td colspan=2>$font<small><input type=text size=25 name=land onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$land\"></td></tr>\n";
		print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small><input type=text size=25 name=kontakt onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontakt\"></td></tr>\n";
		print "<tr><td>$font<small>Kunde_ordnr</td><td colspan=2>$font<small><input type=text size=25 name=kundeordnr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kundeordnr\"></td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=40%><table cellpadding=0 cellspacing=0 border=0	width=250>";
		print "<tr><td>$font<small>CVR.nr</td><td colspan=2>$font<small><input type=text size=12 name=cvrnr value=\"$cvrnr\"></td>\n";
		print "<td>$font<small>EAN.nr</td><td>$font<small><input type=text size=12 name=ean value=\"$ean\"></td></tr>\n";
		print "<tr><td>$font<small>Institution</td><td>$font<small><input type=text size=12 name=institution value=\"$institution\"></td>\n";
		if (db_fetch_array(db_select("select distinct sprog from formularer where sprog != 'Dansk'"))) {
			print "<td></td><td>$font<small><span title='Sprog som skal anvendes på tilbud, ordrer mm.'>Sprog</span></font></small></td>";
			print "<td><select NAME=sprog>";
			print "<option>$sprog</option>";
			$q=db_select("select distinct sprog from formularer order by sprog");
			while ($r=db_fetch_array($q)) print "<option>$r[sprog]</option>";
			print "</SELECT></td></tr>";
		} else print "</tr>";
		print "<tr><td colspan=5><hr></td><tr>";
		print "<tr><td width=20%>$font<small>Ordredato</td><td colspan=2>$font<small><input type=text size=12 name=ordredato value=\"$ordredato\"></td>\n";
		if ($hurtigfakt=='on') print "<td></td></tr>\n";
		else print "<td>$font<small>Lev. dato</td><td colspan=2>$font<small><input type=text size=12 name=levdato value=\"$levdato\"></td></tr>\n";
		if ($fakturadato||$status>0) {
			print "<tr><td>$font<small>";
			if ($art=='DO') print "Fakt. dato"; 
			else print "KN. dato";
			print "</td><td colspan=2>$font<small><input type=text size=12 name=fakturadato value=\"$fakturadato\"></td>\n";
			$tmp="Dette felt skal kun udfyldes, hvis der er tale om et abonnement eller \nlign som skal faktureres igen på et senere tidspunkt.\nSkriv datoen for n&aelig;ste fakturering";
			if ($art=='DO') print "<td width=20%>$font<small><span title='$tmp'>Genfakt.</span></td><td colspan=2>$font<small><input type=text size=12 name=genfakt value=\"$genfakt\"></td>\n";
		}
		$list=array();
		$beskriv=array();
		$list[0]='DKK';
		$x=0;
		$q = db_select("select * from grupper where art = 'VK'order by box1 ");
		while ($r = db_fetch_array($q)){
			$x++;
			$list[$x]=$r['box1'];
			$beskriv[$x]=$r['beskrivelse'];
		}
		$tmp=$x;
		if ($x>0) {
			$list[0]='DKK';
			$beskriv[0]='Danske kroner';
			print "<tr><td>$font<small>Valuta</td>";
			print "<td><SELECT NAME=valuta>";
			for ($x=0; $x<=$tmp; $x++) {
				if ($valuta!=$list[$x]) print "<option title=\"$beskriv[$x]\">$list[$x]</option>";
				else print "<option title=\"$beskriv[$x]\" selected=\"selected\">$list[$x]</option>";
			}
			print "</SELECT></td><td></td>";
		} else print "<tr><td colspan=2 witdh=200></tr>";
		$list=array();
		$beskriv=array();
		$x=0;
		$q = db_select("select * from grupper where art = 'PRJ' order by kodenr");
		while ($r = db_fetch_array($q)){
			$x++;
			$list[$x]=$r['kodenr'];
			$beskriv[$x]=$r['beskrivelse'];
		}
		$tmp=$x;
		if ($x>0) {
			print "<td>$font<small><span title= 'kostpris';>Projekt</span></td>";
			print "<td><SELECT NAME=projekt>";
			for ($x=0; $x<=$tmp; $x++) {
				if ($projekt!=$list[$x]) print "<option title=\"$beskriv[$x]\">$list[$x]</option>";
				else print "<option title=\"$beskriv[$x]\" selected=\"selected\">$list[$x]</option>";
			}
			print "</SELECT></td></tr>";
		} else print "<tr><td colspan=2 witdh=200></tr>";
		
		print "<tr><td>$font<small>Betaling</td>";
		print "<td colspan=3><SELECT NAME=betalingsbet>";
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
			print "</SELECT>&nbsp;+<input type=text size=2 style=text-align:right name=betalingsdage value=\"$betalingsdage\"></td>";
		}
		print "</tr>";
		print "<tr><td>$font<small>Vor ref.</td>";
		print "<td colspan=3><SELECT NAME=ref>";
		print "<option>$ref</option>";
		$q = db_select("select id from adresser where art = 'S'");
		if ($r = db_fetch_array($q)) {
			 $q2 = db_select("select navn from ansatte where konto_id = '$r[id]' and lukket != 'on' order by navn");
			 while ($r2 = db_fetch_array($q2)) {
			 	if ($ref!=$r2[navn]) print "<option> $r2[navn]</option>";
			 }
		}
		print "</SELECT>";
		if ($status==0&&$hurtigfakt!="on") print "<tr><td>$font<small>Godkend</td><td><input type=checkbox name=godkend></td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=30%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
		print "<tr><td colspan=2 align=center><b>$font<small>Leveringsadresse.</td></tr>\n";
		print "<tr><td colspan=2 align=center><hr></td></tr>\n";
		print "<tr><td>$font<small>Firmanavn</td><td colspan=2>$font<small><input type=text size=25 name=lev_navn value=\"$lev_navn\"></td></tr>\n";
		print "<tr><td>$font<small>Adresse</td><td colspan=2>$font<small><input type=text size=25 name=lev_addr1 value=\"$lev_addr1\"></td></tr>\n";
		print "<tr><td>$font<small></td><td colspan=2>$font<small><input type=text size=25 name=lev_addr2 value=\"$lev_addr2\"></td></tr>\n";
		print "<tr><td>$font<small>Postnr, By</td><td>$font<small><input type=text size=4 name=lev_postnr value=\"$lev_postnr\"><input type=text size=19 name=lev_bynavn value=\"$lev_bynavn\"></td></tr>\n";
		print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small><input type=text size=25 name=lev_kontakt value=\"$lev_kontakt\"></td></tr>\n";
		#	 print "<tr><td><textarea style=\"font-family: helvetica,arial,sans-serif;\" name=lev_adr rows=5 cols=35>$lev_adr</textarea></td></tr>\n";
		print "</td></tr></tbody></table></td>";
		print "</td></tr><tr><td align=center colspan=3><table cellpadding=0 cellspacing=0><tbody>";
		$query = db_select("select notes from adresser where kontonr = '$kontonr'");
		if ($row2 = db_fetch_array($query)) {
			print "<tr><td colspan=9 witdh=100% align=center>$font <span style='color: rgb(255, 0, 0);'>$row2[notes]</td></tr><tr><td colspan=9 witdh=100%><hr></td></tr>\n";
		}
		if ($kontonr) {
			print "<tr>";
			if ($status==1&&$hurtigfakt!='on')  {print "<td align=center>$font<small>pos</td><td align=center>$font<small>varenr</td><td align=center>$font<small>antal/enhed</td><td align=center>$font<small>beskrivelse</td><td align=center>$font<small>pris</td><td align=center>$font<small>%</td><td align=center>$font<small>i alt</td><td colspan=2 align=center>$font<small>lev&egrave;r</td><td></td>";} #<td align=center>$font<small>serienr</td>";
			else {print "<td align=center>$font<small>pos</td><td align=center>$font<small>varenr</td><td align=center>$font<small>antal/enhed</td><td align=center>$font<small>beskrivelse</td><td align=center>$font<small>pris</td><td align=center>$font<small>%</td><td align=center>$font<small>i alt</td>";}
			print "</tr>\n";
		}
		if (!$status) $status=0;
		print "<input type=hidden name=status value=$status>";
		print "<input type=hidden name=id value=$id>";

		$x=0;
		if (!$ordre_id) $ordre_id=0;
		$kostpris[0]=0;
		
		if ($valuta && $valuta!='DKK') {
			if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr and valuta.valdate <= '$ordredate' order by valuta.valdate desc"))) {
				$valutakurs=$r['kurs'];
			} else {
				$tmp = dkdato($ordredate);
				print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
			}
		} else $valutakurs = 100;
#		db_modify("update ordrer set valutakurs='$valutakurs' where ordre_id = '$ordre_id'");
		
		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr");
		while ($row = db_fetch_array($query)) {
			if (($row[posnr]>0)&&($row[samlevare]<1)) {  #Hvis "samlevare" er numerisk indgår varen i den ordrelinje der referes til - hvis "on" er varen en samle vare.
				$x++;
				$linje_id[$x]=$row['id'];
				$kred_linje_id[$x]=$row['kred_linje_id'];
				$posnr[$x]=$row['posnr'];
				$varenr[$x]=stripslashes(htmlentities(trim($row['varenr'])));
				$beskrivelse[$x]=stripslashes(htmlentities(trim($row['beskrivelse'])));
				$enhed[$x]=stripslashes(htmlentities(trim($row['enhed'])));
				$pris[$x]=$row['pris'];
				$rabat[$x]=$row['rabat'];
				$antal[$x]=$row['antal'];
				$leveres[$x]=$row['leveres'];
				$vare_id[$x]=$row['vare_id'];
				$momsfri[$x]=$row['momsfri'];
				$serienr[$x]=stripslashes(htmlentities(trim($row['serienr'])));
				$samlevare[$x]=$row['samlevare'];
				if ($vare_id[$x]) {
					$q2 = db_select("select kostpris, provisionsfri from varer where id = '$vare_id[$x]'");
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
					if ($pris[$x]!=0) {$dg[$x]=$db[$x]*100/$pris[$x];}
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
				$sum=$sum+$ialt;
				if ($momsfri[$x]!='on') {$momssum=$momssum+$ialt;}
				$dkpris=dkdecimal($pris[$x]);
				$dkrabat=dkdecimal($rabat[$x]);
				if ($antal[$x]) {
					if ($art=='DK') {$dkantal[$x]=dkdecimal($antal[$x]*-1);}
					else {$dkantal[$x]=dkdecimal($antal[$x]);}
					if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-1);}
					if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-2);}
				}
			}
			else {$antal[$x]=''; $dkpris=''; $dkrabat=''; $ialt='';}
			print "<input type=hidden name=linjeantal value=$linjeantal>";
			print "<input type=hidden name=linje_id[$x] value=$linje_id[$x]>";
			print "<input type=hidden name=kred_linje_id[$x] value=$kred_linje_id[$x]>";
			print "<input type=hidden name=vare_id[$x] value=$vare_id[$x]>";
			print "<input type=hidden name=serienr[$x] value=$serienr[$x]>";
			print "<tr>";
			print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=3 name=posn$x value=$x></td>";
			print "<td>$font<small><input readonly=readonly size=12 name=vare$x onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$varenr[$x]\"></td>";
			print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=3 name=anta$x value=$dkantal[$x]>&nbsp;$enhed[$x]</td>";
			print "<td>$font<small><input type=text size=60 name=beskrivelse$x value=\"$beskrivelse[$x]\"></td>";
			print "<td>$font<span title= 'db: $dk_db[$x] - dg: $dk_dg[$x]%'><small><input type=\"text\" style=\"text-align:right\" size=10 name=pris$x value=\"$dkpris\"></td>";
			print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=4 name=raba$x value=\"$dkrabat\"></td>";
			if ($rabat[$x]) {$db[$x]=$db[$x]-($pris[$x]/100*$rabat[$x]);}
			$db[$x]=$db[$x]*$antal[$x];
			if ($ialt!=0) {
				$dg[$x]=$db[$x]*100/$ialt;
			}
			else {$dg[$x]=0;}
			$dbsum=$dbsum+$db[$x];
			$dk_db[$x]=dkdecimal($db[$x]);
			$dk_dg[$x]=dkdecimal($dg[$x]);
			if ($ialt) {
				 if ($art=='DK') {$ialt=$ialt*-1;}
				 print "<td align=right>$font<span title= 'db: $dk_db[$x] - dg: $dk_dg[$x]%'><small>".dkdecimal($ialt)."</td>";
		 	}			
			else print "<td></td>";
			if ($status>=1&&$hurtigfakt!='on') {
				if ($vare_id[$x]){
					$batch="?";
					$tidl_lev[$x]=0;
					$query = db_select("select gruppe, beholdning from varer where id = $vare_id[$x]");
					$row = db_fetch_array($query);
					$beholdning[$x]=$row[beholdning];
					$query = db_select("select box9 from grupper where art='VG' and kodenr='$row[gruppe]'");
					$row = db_fetch_array($query);
					if ($row[box9]=='on'){$batchvare[$x]=1;}

					if ($antal[$x]>0) {
						$query = db_select("select * from batch_salg where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]");
						while($row = db_fetch_array($query)) {
							$y++;
							$batch='V';
							$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];
						}
						if ($batchvare[$x]) { 
							$z=0;							
							$query = db_select("select * from reservation where vare_id = $vare_id[$x]");
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
							$query = db_select("select * from batch_salg where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]");
							while($row = db_fetch_array($query)){$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];}
							if ($tidl_lev[$x]!=$antal[$x]) $status=1;
							if ($art=='DK' && $leveres[$x]>$tidl_lev[$x]+$antal[$x]) $leveres[$x]=$antal[$x]-$tidl_lev[$x];
							elseif ($leveres[$x]<$antal[$x]-$tidl_lev[$x]) $leveres[$x]=$antal[$x]-$tidl_lev[$x]; #20071004
						} elseif ($kred_linje_id[$x]<0) {
						#hvis $kred_linje_id[$x]<0 tages en vare retur som ikke er blevet solgt til kunden. Denne behandles derfor som et varekob.
						$query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]' and ordre_id=$id"); #20071004
							while($row = db_fetch_array($query)) $tidl_lev[$x]=$tidl_lev[$x]-$row[antal];
							if ($antal[$x]>$tidl_lev[$x]+$leveres[$x]) $leveres[$x]=$antal[$x]+$tidl_lev[$x];
						}
						$query = db_select("select * from reservation where linje_id = '$linje_id[$x]'");
						if (($row = db_fetch_array($query))&&($beholdning[$x]>=0)) {
							if ($antal[$x]+$tidl_lev[$x]!=$row[antal]) db_modify ("update reservation set antal=$antal[$x]*-1 where linje_id=$linje_id[$x] and batch_salg_id=0");
						}
						elseif ($antal[$x]-$tidl_lev[$x]!=0) db_modify("insert into reservation (linje_id, vare_id, batch_salg_id, antal) values ($linje_id[$x], $vare_id[$x], 0, $antal[$x]*-1)");
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
						print "<td>$font<span title= 'Beholdning: $beholdning[$x]'><small><input type=\"text\" style=\"text-align:right\" size=2 name=leve$x value=\"$dklev[$x]\"></td>";
						print "<td>$font<small>($dk_tidl_lev[$x])</td>";
						if ($batchvare[$x]) print "<td align=center onClick=\"batch($linje_id[$x])\">$font<span title= 'V&aelig;lg fra k&oslash;bsordre'><small><img alt=\"Serienummer\" src=../ikoner/serienr.png></td></td>";
						$levdiff=1;
					} else {
						print "<td>$font<span title= 'Beholdning: $beholdning[$x]'><small><input readonly=readonly style=text-align:right size=2 name=leve$x value=\"$dklev[$x]\"></td>";
						print "<td>$font<small>($dk_tidl_lev[$x])</td>";
					}
					db_modify("update ordrelinjer set leveret=$tidl_lev[$x] where id=$linje_id[$x]");
				}
			}
			if ($samlevare[$x]=='on') print "<td align=center onClick=\"stykliste($vare_id[$x])\">$font<span title= 'Vis stykliste'><small><img alt=\"Stykliste\" src=../ikoner/stykliste.png></td></td>";
			print "</tr>\n";
			$antal_ialt=$antal_ialt+$antal[$x]; #10.10.2007
			$leveres_ialt=$leveres_ialt+$leveres[$x]; #10.10.2007
			$tidl_lev_ialt=$tidl_lev_ialt+$tidl_lev[$x]; #10.10.2007
		}
		if ($status>=1&&$bogfor!=0 && !$leveres_ialt && $tidl_lev_ialt && $antal_ialt != $tidl_lev_ialt) $delfakturer = 'on';
		else $delfakturer = '';
		if ($kontonr) {
			$x++;
			$posnr[0]=$linjeantal+1;
			print "<tr>";
			print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=3 name=posn0 value=$posnr[0]></td>";
			if ($art=='DK') {print "<td>$font<small><input readonly=readonly size=12 name=vare0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";}
			else {print "<td>$font<small><input type=\"text\" size=12 name=vare0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";}
			print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=3 name=anta0></td>";
			print "<td>$font<small><input type=text size=60 name=beskrivelse0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
			print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=10 name=pris0></td>";
			print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=4 name=raba0></td>";
			print "<td>$font<small></td>";
			print "</tr>\n";
			print "<input type=hidden size=3 name=sum value=$sum>";
			$tmp=$momssum/100*$momssats+0.000001; #puuuh den er grim - men ellers runder den ned ved v. 0,5 re ??
			$moms=round($tmp,2);
			db_modify("update ordrer set sum=$sum, kostpris=$kostpris[0], moms=$moms where id=$id");
			if ($art=='DK') {
				$sum=$sum*-1;
				$moms=$moms*-1;
			}
			$ialt=($sum+$moms);
			print "<tr><td colspan=7><table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" width=100%><tbody>";
			print "<tr>";
			print "<td align=center>$font<small>Ordresum:&nbsp;".dkdecimal($sum)."</td>";
			$db=$dbsum;
			print "<td align=center>$font<small>D&aelig;kningsbidrag:&nbsp;".dkdecimal($db)."</td>";
			if ($sum) {
				$dg_sum=($dbsum*100/$sum);}
			else {$dg_sum=dkdecimal(0);}
			print "<td align=center>$font<small>D&aelig;kningsgrad:&nbsp;".dkdecimal($dg_sum)."%</td>";
			print "<td align=center>$font<small>Moms:&nbsp;".dkdecimal($moms)."</td>";
			print "<td align=center>$font<small>I alt:&nbsp;".dkdecimal($ialt)."</td>";
		}
		print "</tbody></table></td></tr>\n";
		print "<input type=\"hidden\" name=\"fokus\">";
		print "<tr><td align=center colspan=8>";
		print "<table width=100% border=\"1\" cellspacing=\"0\" cellpadding=\"1\"><tbody><tr>";
		if ($status < 3) {
			if ($levdiff) $status=1;
			if ($status<1) $width="33%";
			elseif ($sum!=0) $width="25%";
			if ($hurtigfakt=='on' && $fakturadato) print "<input type=hidden name=levdato value=$fakturadato>";
			print "<input type=hidden name=status value=$status>";
			print "<td align=center width=$width><input type=submit accesskey=\"g\" value=\"&nbsp;&nbsp;Gem&nbsp;&nbsp;\" name=\"submit\"></td>";
			print "<td align=center width=$width><input type=submit accesskey=\"o\" value=\"Opslag\" name=\"submit\"></td>";
			if ($status==1&&$bogfor!=0 && $hurtigfakt!='on' && $leveres_ialt) {print "<td align=center width=$width><input type=submit accesskey=\"l\" value=\"&nbsp;Lev&eacute;r&nbsp;\" name=\"submit\"></td>";} #10.10.2007
			if (($status==2&&$bogfor!=0)||($status>0&&$hurtigfakt=='on')) {
		#		$tmp=date("d-m-Y");
		#		if (!$fakturadate) print "<td align=center width=$width><input type=submit accesskey=\"f\" value=\"Fakturer\" name=\"submit\"  onClick=\"return SetFaktDate('$tmp')\"></td>";
		#		else 
				print "<td align=center width=$width><input type=submit accesskey=\"f\" value=\"Fakturer\" name=\"submit\"></td>";
			} elseif ($delfakturer == 'on') {
				print "<td align=center width=$width><input type=submit accesskey=\"f\" value=\"Delfakturer\" name=\"submit\"></td>";
		}
			if (($linjeantal>0)&&($art=='DO')) {
				if ( strlen("which ps2pdf")) { 
					print "<td align=center><input type=submit value=\"&nbsp;Udskriv&nbsp;\" name=\"submit\"></td>";
				} else { 
					print "<td align=center><input type=submit value=\"&nbsp;Udskriv&nbsp;\" name=\"submit\" disabled=\"disabled\"></td>";
				}
			}
			if ($linjeantal==0 && $id) {print "<td align=center><input type=submit value=\"&nbsp;&nbsp;Slet&nbsp;&nbsp;\" name=\"submit\"></td>";}
			print "</tbody></table></td></tr>\n";
			print "</form>";
			print "</tbody></table></td></tr></tbody></table></td></tr>\n";
			print "<tr><td></td></tr>\n";
		} # end if ($status < 3)
		
		if ($konto_id) $r=db_fetch_array(db_select("select kreditmax from adresser where id = '$konto_id'"));
		if ($kreditmax=$r['kreditmax']*1) {
		if ($valutakurs) $kreditmax=$kreditmax*100/$valutakurs;
			$q=db_select("select * from openpost where konto_id = '$konto_id' and udlignet='0'");
			$tilgode=0;	
			while($r=db_fetch_array($q)) {
				if (!$r['valuta']) $r['valuta']='DKK';
				if (!$r['valutakurs']) $r['valutakurs']=100;
				if ($valuta=='DKK' && $r['valuta']!='DKK') $opp_amount=$r['amount']*$r['valutakurs']/100;
				elseif ($valuta!='DKK' && $r['valuta']=='DKK') {
					if ($r3=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = grupper.kodenr and valuta.valdate <= '$r[transdate]' order by valuta.valdate desc"))) {
						$opp_amount=$r['amount']*100/$r3['kurs'];
					} else print "<BODY onLoad=\"javascript:alert('Ingen valutakurs for faktura $r[faktnr]')\">";	
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
function kontoopslag($sort, $fokus, $id, $find)
{
	global $font;
	global $bgcolor;
	global $bgcolor5;	

	if ($find) $find=str_replace("*","%",$find);
	else $find="%";
	if (substr($find,-1,1)!='%') $find=$find.'%';
	sidehoved($id, "ordre.php", "../debitor/debitorkort.php", $fokus, "Kundeordre $id - Kontoopslag");
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><small><b>$font<a href=ordre.php?sort=kontonr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Kundenr</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=firmanavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=addr1&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Adresse</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=addr2&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Adresse2</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=postnr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Postnr</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=bynavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>bynavn</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=land&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>land</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=kontakt&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Kontaktperson</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=tlf&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Telefon</b></small></td>";
	print" </tr>\n";

	$sort = $_GET['sort'];
	if (!$sort) {$sort = firmanavn;}
	if ($find) $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'D' and upper($fokus) like upper('$find') order by $sort");
	else $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'D' order by $sort");
	$fokus_id='id=fokus';
	while ($row = db_fetch_array($query)) {
		$kontonr=str_replace(" ","",$row['kontonr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><small>$font<a href=ordre.php?fokus=$fokus&id=$id&konto_id=$row[id] $fokus_id>$row[kontonr]</a></small></td>";
		$fokus_id='';
		print "<td><small>$font".stripslashes($row[firmanavn])."</small></td>";
		print "<td><small>$font".stripslashes($row[addr1])."</small></td>";
		print "<td><small>$font".stripslashes($row[addr2])."</small></td>";
		print "<td><small>$font".stripslashes($row[postnr])."</small></td>";
		print "<td><small>$font".stripslashes($row[bynavn])."</small></td>";
		print "<td><small>$font".stripslashes($row[land])."</small></td>";
		print "<td><small>$font".stripslashes($row[kontakt])."</small></td>";
		print "<td><small>$font".stripslashes($row[tlf])."</small></td>";
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	print "<BODY onLoad=\"javascript:document.getElementById('fokus').focus()\">";
	exit;
}
######################################################################################################################################
function ansatopslag($sort, $fokus, $id)
{
	global $font;
	global $bgcolor;
	global $bgcolor5;

	if (!$id) $id='0'; 
	$query = db_select("select konto_id from ordrer where id = $id");
	$row = db_fetch_array($query);
	$konto_id = $row[konto_id];
	
	$fokus=$fokus."&konto_id=".$konto_id;
	
	sidehoved($id, "ordre.php", "../debitor/ansatte.php", $fokus, "Debitorordre $id");
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><small><b>$font<a href=ordre.php?sort=navn&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=tlf&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Lokal</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=mobil&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Mobil</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=email&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>E-mail</b></small></td>";
	print" </tr>\n";


	$sort = $_GET['sort'];
	if (!$sort) {$sort = navn;}

	if (!$id) {exit;}
	$query = db_select("select * from ansatte where konto_id = $konto_id order by $sort");
	while ($row = db_fetch_array($query))
	{
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><small>$font<a href='ordre.php?fokus=$fokus&id=$id&kontakt=$row[navn]'>$row[navn]</a></small></td>";
		print "<td><small>$font $row[tlf]</small></td>";
		print "<td><small>$font $row[mobil]</small></td>";
		print "<td><small>$font $row[email]</small></td>";
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function vareopslag($sort, $fokus, $id, $vis_kost, $ref, $find)
{
	global $font;
	global $bgcolor;
	global $bgcolor5;
 
	if ($find) $find=str_replace("*","%",$find);

	sidehoved($id, "ordre.php", "../lager/varekort.php", $fokus, "Kundeordre $id - Vareopslag");

	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	if ($vis_kost) {print "<tr><td colspan=8 align=center><a href=ordre.php?sort=varenr&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id>$font<small>Udelad kostpriser</a></td></tr>";}
	else {print "<tr><td colspan=4 align=center><a href=ordre.php?sort=varenr&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis_kost=on>$font<small>Vis kostpriser</a></td></tr>";}
	print"<td><small><b>$font<a href=ordre.php?sort=varenr&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis_kost=$vis_kost>Varenr</a></b></small></td>";
	print"<td><small><b>$font Enhed</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=beskrivelse&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beskrivelse</a></b></small></td>";
	print"<td align=right><small><b>$font<a href=ordre.php?sort=salgspris&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id>Salgspris</a></b></small></td>";
	if ($vis_kost) {print"<td align=right><small><b>$font Kostpris</b></small></td>";}
	print"<td align=right><small><b>$font<a href=ordre.php?sort=beholdning&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beh.</a></b></small></td>";
	print"<td><br></td>";
	print" </tr>\n";

	if ($ref){
		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'"))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'"))) {$lager=$row['kodenr'];}
		}
	}
	$lager=$lager*1;
	if (!$sort) {$sort = varenr;}

	if ($find) $query = db_select("select * from varer where lukket != '1' and $fokus like '$find' order by $sort");
	else $query = db_select("select * from varer where lukket != '1' order by $sort");
	while ($row = db_fetch_array($query))
	{
		$query2 = db_select("select box8 from grupper where art='VG' and kodenr='$row[gruppe]'");
		$row2 =db_fetch_array($query2);
		if (($row2[box8]=='on')||($row[samlevare]=='on')){
			if (($row[beholdning]!='0')and(!$row[beholdning])){db_modify("update varer set beholdning='0' where id=$row[id]");}
		}
		elseif ($row[beholdning]){db_modify("update varer set beholdning='0' where id=$row[id]");}

		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><small>$font<a href=\"ordre.php?vare_id=$row[id]&fokus=$fokus&id=$id\">$row[varenr]</a></small></td>";	
		print "<td><small>$font$row[enhed]<br></small></td>";
		print "<td><small>$font$row[beskrivelse]<br></small></td>";
		$salgspris=dkdecimal($row[salgspris]);
		print "<td align=right><small>$font$salgspris<br></small></td>";
		if ($vis_kost=='on') {
			$query2 = db_select("select kostpris from vare_lev where vare_id = $row[id] order by posnr");
			$row2 = db_fetch_array($query2);
			$kostpris=dkdecimal($row2[kostpris]);
			print "<td align=right><small>$font$kostpris<br></small></td>";
		}
		$reserveret=0;
		if ($lager>=1){
			$q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest>0 and lager=$lager");
			while ($r2 = db_fetch_array($q2)) {
				$q3 = db_select("select * from reservation where batch_kob_id=$r2[id]");
				while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3[antal];}
			}
			$linjetext="<span title= 'Reserveret: $reserveret'>";
			if ($r2= db_fetch_array(db_select("select beholdning from lagerstatus where vare_id=$row[id] and lager=$lager"))) {
				print "<td align=right>$linjetext<small>$font $r2[beholdning]</small></span></td>";
			} 
		}
		else { 
			$q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest > 0");
			while ($r2 = db_fetch_array($q2)) {
				$q3 = db_select("select * from reservation where batch_kob_id=$r2[id]");
				while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3[antal];}
			}
			$linjetext="<span title= 'Reserveret: $reserveret'>";
			print "<td align=right>$linjetext<small>$font $row[beholdning]</small></span></td>";
		}
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function sidehoved($id, $returside, $kort, $fokus, $tekst)
{
global $bgcolor2; 
global $font;
global $top_bund;
global $color; 

	print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Kundeordre</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";
	print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
	print "<div align=\"center\">";

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	if ($returside != "ordre.php") {print "<td width=\"10%\" $top_bund>$font $color<small><a href=../includes/luk.php?tabel=ordrer&id=$id accesskey=L>Luk</a></small></td>";}
	else {print "<td width=\"10%\" $top_bund>$font $color<small><a href=ordre.php?id=$id accesskey=L>Luk</a></small></td>";}
	print "<td width=\"80%\" $top_bund>$font $color<small>$tekst</small></td>";
	if ($returside != "ordre.php") {print "<td width=\"10%\" $top_bund>$font $color<small><a href=ordre.php?returside=ordreliste.php accesskey=N>Ny</a></small></td>";}
	else {print "<td width=\"10%\" $top_bund>$font $color<small><a href=$kort?returside=../debitor/ordre.php&ordre_id=$id&fokus=$fokus accesskey=N>Ny</a></small></td>";}
	print "</tbody></table>";
	print "</td></tr>\n";
	print "<tr><td valign=\"top\" align=center>";
}

######################################################################################################################################
function find_vare_id ($varenr)
{
	$query = db_select("select id from varer where varenr = '$varenr'");
	if ($row = db_fetch_array($query)) {return $row[id];}
}

######################################################################################################################################
function find_konto_id ($kontonr) {
	$query = db_select("select id from adresser where kontonr = '$kontonr'");
	if ($row = db_fetch_array($query)) {return $row[id];}
}
######################################################################################################################################
function find_betalingsdage ($konto_idnr) {
	$query = db_select("select betalingsdage from adresser where id = '$konto_id'");
	if ($row = db_fetch_array($query)) {return $row[betalingsdage];}
}
###########################################################################################################################
function batch ($linje_id) {
	$leveres=0;
	$query = db_select("select * from ordrelinjer where id = '$linje_id'");
	if ($row = db_fetch_array($query)) {
		$antal=$row[antal];
		$leveres=$row[leveres];
		$posnr=$row[posnr];
		$vare_id=$row[vare_id];
		$varenr=$row['varenr'];
		$serienr=$row['serienr'];
		$query = db_select("select status, art, konto_id, ref from ordrer where id = '$row[ordre_id]'");
		$row = db_fetch_array($query);
		$konto_id=$row[konto_id];
		$status=$row[status];
		$art=$row[art];

		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$row[ref]'"))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'"))) {$lager=$row['kodenr']*1;}
		}
	}

	$query = db_select("select * from batch_salg where linje_id = $linje_id");
	while($row = db_fetch_array($query)) $leveres=$antal-$row[antal];

	if (($antal>=0)&&($art!="DK")&&($vare_id)){	
		$x=0;
		$rest=array();
		$lev_rest=$leveres;
		
		if (isset($lager)) $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 and lager = $lager order by kobsdate");
		else $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 order by kobsdate");
		while ($row = db_fetch_array($query)) {
			$x++;
			$batch_kob_id[$x]=$row['id'];
			$kobsdate[$x]=$row['kobsdate'];
			$rest[$x]=$row['rest'];
			$reserveret[$x]=0;
#			$pris[$x]=$row[pris];
			$q2 = db_select("select ordrenr from ordrer where id=$row[ordre_id]");
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
		 db_modify("delete from reservation where linje_id=$linje_id");
		 $temp=$linje_id*-1;
		 db_modify("delete from reservation where batch_salg_id=$temp");
		 for ($x=1; $x<=$batch_antal; $x++){
			 $lager=$lager*1;
			 if (($valg[$x]>0)&&(!$res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_kob_id, antal, lager) values ($linje_id, $vare_id, $batch_kob_id[$x], $valg[$x], $lager)");}
			 elseif (($valg[$x]>0)&&($res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_salg_id, antal, lager) values ($res_linje_id[$x], $vare_id, $temp, $valg[$x], $lager)");}
		 } 
	}	
}
##############################################################################
function indsaet_linjer($ordre_id, $linje_id, $posnr)
 {
	$posnr = str_replace('+',':',$posnr); #jeg ved ikke hvorfor, men den vil ikke splitte med "+"
	list ($posnr, $antal) = split (':', $posnr);
	db_modify("update ordrelinjer set posnr='$posnr' where id='$linje_id'");
	for ($x=1; $x<=$antal; $x++) {
		db_modify("insert into ordrelinjer (posnr, ordre_id) values ('$posnr','$ordre_id')");
	}
}
##############################################################################
function find_nextfakt($fakturadate, $nextfakt) 
{
// Denne funktion finder diff mellem fakturadate & nextfakt, tillægger diff til nextfakt og returnerer denne vaerdi. Hvis baade 
// fakturadate og netffaxt er sidste dag i de respektive maaneder vaelges også sidste dag i maanedes i returvaerdien.

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
