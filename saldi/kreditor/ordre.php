<?php
// -------------kreditor/ordre.php----------lap 3.5.5-----2015.04.15----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------

// 2012.08.14 søg 20120814
// 2013.06.18 Tilføjet udskrivning af indkøbsforslag, rekvisition & lev-faktura
// 2013.06.24 Rettet bug - Blank side ved kreditering...
// 2013.08.16 Rettet bug - Blank side ved kopiering... 20130816
// 2013.09.19 Alle forekomster af round ændret til afrund
// 2014.03.19 addslashes erstattet med db_escape_string
// 2014.10.05 Div i forbindelse med omvendt betalingspligt, samt nogle generelle ændringer således at varereturnering nu bogføres
//	som negativt køb og ikke som salg.
// 2014.11.04 Varemomssats indsættes ved oprettelse af ordrelinjer, søg varemomssats.
// 2014.11.07 Momsats var ikke sat, så vǘaremomssats kunne ikke sættes.
// 2015.02.09 Ved negativt lager var det ikke muligt at hjemkøbe mindre en det antal det manglede på lager.  20150209
// 2015.04.15	Omvbet på ordrelinje forsvandt ved gem # 201504015

@session_start();
$s_id=session_id();

?>
	<script type="text/javascript">
	<!--
	var linje_id=0;
	var antal=0;
	function serienummer(linje_id, antal) {
		window.open("serienummer.php?linje_id="+ linje_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
	}
	function batch(linje_id, antal) {
		window.open("batch.php?linje_id="+ linje_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
	}
//		 -->
	</script>

	<script type="text/javascript">
	<!--
	function fejltekst(tekst) {
		alert(tekst);
		window.location.replace("../includes/luk.php?");
	}
	-->
	</script>
<?php

$title="Kreditorordre";
$css="../css/standard.css";
$modulnr=7;
$batch=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$returside = if_isset($_GET['returside']);

#if ($popup) $returside="../includes/luk.php";
#elseif (!$returside) $returside="../kreditor/ordreliste.php";
if (!$returside || $returside=="ordreliste.php") $returside="../kreditor/ordreliste.php";

$tidspkt=date("U");
print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

if ($tjek=$_GET['tjek'])	{
	$query = db_select("select tidspkt, hvem from ordrer where status < 3 and id = $tjek and hvem != '$brugernavn'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query))	{
		if ($tidspkt-($row['tidspkt'])<3600) {
			print "<BODY onLoad=\"javascript:alert('Ordren er i brug af $row[hvem]')\">";
			if ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
			else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php\">";
		}
		else {db_modify("update ordrer set hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$tjek'",__FILE__ . " linje " . __LINE__);}
	}
	else {db_modify("update ordrer set hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$tjek'",__FILE__ . " linje " . __LINE__);}
}

$r=db_fetch_array(db_SELECT("select box4 from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
$hurtigfakt=$r['box4'];

$r=db_fetch_array(db_SELECT("select box9 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
$negativt_lager=$r['box9'];

$id=$_GET['id'];
$vis=$_GET['vis'];
$sort=$_GET['sort'];
$fokus=$_GET['fokus'];
$submit=$_GET['funktion'];
if (!$id && $konto_id=$_GET['konto_id']) $id = indset_konto($id, $konto_id);
if (($kontakt=$_GET['kontakt'])&&($id)) db_modify("update ordrer set kontakt='$kontakt' where id=$id",__FILE__ . " linje " . __LINE__);

if ($_GET['vare_id']) {
	$vare_id[0]=db_escape_string($_GET['vare_id']);
	$linjenr=substr($fokus,4);
	if ($id) {
		$query = db_select("select konto_id, kontonr, status,omvbet from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$omlev=$row['omvbet'];
#cho "$omlev=$row[omvbet]<br>";
		if ($row['status']>2) {
			print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
			exit;
		}
		$konto_id=$row['konto_id'];
		$query = db_select("select posnr from ordrelinjer where ordre_id = '$id' order by posnr desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $posnr[0]=$row['posnr']+1;
		else $posnr[0]=1;
	}
	else $posnr[0]=1;

	$query = db_select("select * from varer where id = '$vare_id[0]'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$varenr[0]=db_escape_string($row['varenr']);
		$serienr[0]=db_escape_string($row['serienr']);
		$samlevare[0]=db_escape_string($row['samlevare']);
		if (!$beskrivelse[0]) $beskrivelse[0]=db_escape_string($row['beskrivelse']);
		if (!$enhed[0]) $enhed[0]=$row['enhed'];
		if (!$pris[0]) $pris[0]=$row['kostpris'];
		if (!$rabat) $rabat=$row['rabat'];
	}
	if (((!$pris[0])||(!$lev_varenr[0]))&&($vare_id[0])&&($konto_id))	{
		$query = db_select("select * from vare_lev where vare_id = '$vare_id[0]' and lev_id = '$konto_id'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$pris[0]=$row['kostpris'];
			$lev_varenr[0]=$row['lev_varenr'];
		}
	}
	if (!$id) $id = indset_konto($id, $konto_id);
	$pris[0]=$pris[0]*1;
	if(!$antal[0]) $antal[0]=1;
	if (!$rabat) $rabat=0;

	$r=db_fetch_array(db_select("select momssats,valuta,ordredate from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$momssats=$r['momssats']; #20141107
	$valuta=$r['valuta'];
	$ordredate=$r['ordredate'];
	if ($valuta && $valuta!='DKK') {
		if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr::INT and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
			$pris[0]=$pris[0]*100/$r['kurs'];
		} else {
			$tmp = dkdato($ordredate);
			print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
		}
	}

	if ($linjenr==0) {
		if ($serienr[0]) $antal[0]=afrund($antal[0],0);
		if ($vare_id[0]) {
			if ($r1 = db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[0]'",__FILE__ . " linje " . __LINE__))) {
				$r2 = db_fetch_array(db_select("select box4,box6,box7,box8 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'",__FILE__ . " linje " . __LINE__));
				$bogfkto[0] = $r2['box4'];
				$omvare[0] = $r2['box6'];
				$momsfri[0] = $r2['box7'];
				$lagerfort[0] = $r2['box8'];
			}
			# if (($omvare[0]!=NULL)&&($rabatsats>$r2['box6'])) $rabatsats=$r2['box6'];
			if ($bogfkto[0] && !$momsfri[0]) {
				$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$bogfkto[0]' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
				if ($tmp=trim($r2['moms'])) { # f.eks S3
					$tmp=substr($tmp,1); #f.eks 3
					$r2 = db_fetch_array(db_select("select box2 from grupper where art = 'SM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__));
					if ($r2['box2']) $varemomssats[0]=$r2['box2']*1;
				}	else $varemomssats[0]=$momssats;
			} elseif (!$momsfri[0]) $varemomssats[0]=$momssats;
			else $varemomssats[0]=$momssats;
			if ($samlevare[0]) { 
				samlevare($id,$art,$vare_id[0],$antal[0]);
			} else {
				($omlev && $omvare[0])?$omvbet[0]='on':$omvbet[0]='';
				db_modify("insert into ordrelinjer (ordre_id,posnr,varenr,vare_id,beskrivelse,enhed,pris,lev_varenr,serienr,antal,momsfri,samlevare,omvbet,momssats) values ('$id','$posnr[0]','$varenr[0]','$vare_id[0]','$beskrivelse[0]','$enhed[0]','$pris[0]','$lev_varenr[0]','$serienr[0]','$antal[0]','$momsfri[0]','$samlevare[0]','$omvbet[0]','$varemomssats[0]')",__FILE__ . " linje " . __LINE__);
			}
		}
	}
}
	if (($_POST['status'] || $_POST['status']=='0') && ($_POST['status']<3 || strstr($_POST['submit'],"Kred") || strstr($_POST['submit'],"Kopi"))) { #20130816
 		$fokus=$_POST['fokus'];
		$submit = $_POST['submit'];
		$id = $_POST['id'];
		$ordrenr = $_POST['ordrenr'];
		$kred_ord_id = $_POST['kred_ord_id'];
		$art = $_POST['art'];
		$konto_id = trim($_POST['konto_id']);
		$kontonr = trim($_POST['kontonr']);
		$firmanavn = db_escape_string(trim($_POST['firmanavn']));
		$addr1 = db_escape_string(trim($_POST['addr1']));
		$addr2 = db_escape_string(trim($_POST['addr2']));
		$postnr = trim($_POST['postnr']);
		$bynavn = trim($_POST['bynavn']);
		if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
		else $bynavn = db_escape_string($bynavn);
		$land = db_escape_string(trim($_POST['land']));
		$kontakt = db_escape_string(trim($_POST['kontakt']));
		$lev_navn = db_escape_string(trim($_POST['lev_navn']));
		$lev_addr1 = db_escape_string(trim($_POST['lev_addr1']));
		$lev_addr2 = db_escape_string(trim($_POST['lev_addr2']));
		$lev_postnr = $_POST['lev_postnr'];
		$lev_bynavn = trim($_POST['lev_bynavn']);
		if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
		else $lev_bynavn = db_escape_string($lev_bynavn);
		$lev_kontakt = db_escape_string(trim($_POST['lev_kontakt']));
		$ordredate = usdate($_POST['ordredato']);
		$levdate = usdate(trim($_POST['levdato']));
		$cvrnr = trim($_POST['cvrnr']);
		$betalingsbet = $_POST['betalingsbet'];
		$betalingsdage = $_POST['betalingsdage']*1;
		$valuta = $_POST['valuta'];
		$projekt = $_POST['projekt'];
		$lev_adr = trim($_POST['lev_adr']);
		$sum=$_POST['sum'];
		$linjeantal = $_POST['linjeantal'];
		$linje_id = $_POST['linje_id'];
		$kred_linje_id = $_POST['kred_linje_id'];
		$vare_id = $_POST['vare_id'];
		$posnr = $_POST['posnr'];
		$status = $_POST['status'];
		$godkend = $_POST['godkend'];
		$kreditnota = $_POST['kreditnota'];
		$ref = trim($_POST['ref']);
		$fakturanr = db_escape_string(trim($_POST['fakturanr']));
		$momssats = $_POST['momssats']*1;
		$lev_varenr = $_POST['lev_varenr'];
		$momsfri = $_POST['momsfri'];
		$serienr=$_POST['serienr'];
		$omvbet=$_POST['omvbet'];
		$varemomssats=$_POST['$varemomssats']; #20141106

		if ($kred_ord_id) {
			$r=db_fetch_array(db_select("select ordrenr from ordrer where id = '$kred_ord_id'",__FILE__ . " linje " . __LINE__));
				$kred_ord_nr=$r['ordrenr'];
			}
		if ($valuta && $valuta!='DKK') {
				$ordredato = dkdato($ordredate);
			if ($r= db_fetch_array(db_select("select valuta.kurs as kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr::INT and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
				$valutakurs=$r['kurs']*1; #20120814 *1 + naeste linje tilfojet.
				if (!$valutakurs) print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredato')\">";
			} else {
				print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredato')\">";
			}
		} else $valutakurs=100;

  	if ($momssats && $konto_id) {
			$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
			$gruppe=$r['gruppe']*1;
			$r = db_fetch_array(db_select("select box1,box2,box9 from grupper where art = 'KG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
			$box1=substr(trim($r['box1']),0,1);
			(trim($r['box9']))?$omlev='on':$omlev='';
			if (!$box1 || $box1=='E') {
				$momssats=0;	# Erhvervelsesmoms beregnes automatisk ved bogforing.
				if ($box1) $tekst = "Erhvervelsesmoms beregnes automatisk ved bogf&oslash;ring.<br>";
				else $tekst = "Leverand&oslash;rgruppen er ikke tilknyttet en momsgruppe";
				print "<BODY onLoad=\"javascript:alert('$tekst')\">";
			} elseif (!$box1 || $box1=='Y') {
				$momssats=0;	# Ydelsesmoms beregnes automatisk ved bogforing.
				if ($box1) $tekst = "Ydelsesmoms beregnes automatisk ved bogf&oslash;ring.<br>";
				else $tekst = "Leverand&oslash;rgruppen er ikke tilknyttet en momsgruppe";
				print "<BODY onLoad=\"javascript:alert('$tekst')\">";
			}
		}

		transaktion("begin");

		 if (strstr($submit,'Slet'))	{
				db_modify("delete from ordrelinjer where ordre_id=$id",__FILE__ . " linje " . __LINE__);
			db_modify("delete from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php\">";
		}

		for ($x=0; $x<=$linjeantal;$x++) {
			$y="posn".$x;
			$posnr_ny[$x]=trim($_POST[$y]);
			if ($posnr_ny[$x]!="-" && $posnr_ny[$x]!="->" && $posnr_ny[$x]!="<-" && !strpos($posnr_ny[$x],'+')) $posnr_ny[$x]=afrund((100*str_replace(",",".",$posnr_ny[$x])),0);
			$y="vare".$x;
			$varenr[$x]=db_escape_string(trim($_POST[$y]));
			$y="anta".$x;
			$antal[$x]=$_POST[$y];
			if ($antal[$x]){
				$antal[$x]=usdecimal($antal[$x]);
				if ($art=='KK') {$antal[$x]=$antal[$x]*-1;}
			}
			$y="leve".$x;
			$leveres[$x]=trim($_POST[$y]);
			if ($leveres[$x]){
				$leveres[$x]=usdecimal($leveres[$x]);
				if ($art=='KK') {$leveres[$x]=$leveres[$x]*-1;}
			}
			$y="beskrivelse".$x;
			$beskrivelse[$x]=db_escape_string(trim($_POST[$y]));
			$y="pris".$x;
			if (($x!=0)||($_POST[$y])||($_POST[$y]=='0')) {$pris[$x]=usdecimal($_POST[$y]);}
			$y="raba".$x;
			$rabat[$x]=usdecimal($_POST[$y]);
			if (($x>0)&&(!$rabat[$x])){$rabat=0;}
			$y="ialt".$x;
			$ialt[$x]=$_POST[$y];
			if (($godkend == "on")&&($status==0)) {$leveres[$x]=$antal[$x];}
			if ((!$sletslut) && ($posnr_ny[$x]=="->")) $sletstart=$x;
			if (($sletstart) && ($posnr_ny[$x]=="<-")) $sletslut=$x;
			$projekt[$x]=$projekt[$x];
		}
		if (($sletstart)&&($sletslut)&&($sletstart<$sletslut)) {
			for ($x=$sletstart; $x<=$sletslut; $x++) {
				$posnr_ny[$x]="-";
			}
		}

		$bogfor=1;
		if (!$sum){$sum=0;}
		if (!$status){$status=0;}


		#Kontrol mod brug af browserens "tilbage" knap og mulighed for 2 x bogfring af samme ordre
		if ($id) {
			$query = db_select("select status from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {
				if ($row[status]!=$status) {
					print "Hmmm -a $row[status] - b $status har du brugt browserens tilbageknap?";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
					exit;
				}
			}
		}
		if (strstr($submit, "Kred")) {$art='KK';}
		if ((strstr($submit, "Kred"))||(strstr($submit, "Kopi"))) {
			if ($art!='KK') {
				$id='';
				$status=0;
			}
			else	{
				$kred_ord_id=$id;
				$id='';
				$status=0;
			}
		}
		elseif (!$art) {$art='KO';}
		if ($godkend == "on") {
			if ($status==0) {$status=1;}
			elseif ($status==1) {$status=2;}
		}
		if (strlen($ordredate)<6){$ordredate=date("Y-m-d");}
		if (($kontonr)&&(!$firmanavn)) {
			$query = db_select("select * from adresser where kontonr = '$kontonr' and art = 'K'",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {
				$konto_id=$row[id];
				$firmanavn=db_escape_string($row['firmanavn']);
				$addr1=db_escape_string($row['addr1']);
				$addr2=db_escape_string($row['addr2']);
				$postnr=$row['postnr'];
				$bynavn=db_escape_string($row['bynavn']);
				$land=db_escape_string($row['land']);
			 	$kontakt=db_escape_string($row['kontakt']);
				$betalingsdage=$row['betalingsdage'];
				$betalingsbet=$row['betalingsbet'];
				$cvrnr=$row['cvrnr'];
				$notes=db_escape_string($row['notes']);
				$gruppe=$row['gruppe'];
			}
			if ($gruppe) {
				$query = db_select("select box1,box3,box9 from grupper where art='KG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$omlev=$row['box9'];
#cho "omlev $omlev<br>";				
				if (substr($row['box1'],0,1)=='K') {
	 				$tmp= substr($row['box1'],1,1)*1;
					$valuta=$r['box3'];
					$query = db_select("select box2 from grupper where art='KM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					$momssats=trim($row['box2'])*1;
				} elseif (substr($row['box1'],0,1)=='E') {
					$momssats='0.00';
				} elseif (substr($row['box1'],0,1)=='Y') { 
					$momssats='0.00';
				}
#				if (!$momssats) {
#					print "<BODY onLoad=\"javascript:alert('Kreditorgrupper forkert opsat')\">";
#					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
#					exit;
#				}
			} else print "<BODY onLoad=\"javascript:alert('Kreditor ikke tilknyttet en kreditorgruppe')\">";
		}
		if (!$id&&!$konto_id&&!$firmanavn&&$varenr[0]) {

		$varenr[0]=strtoupper($varenr[0]);
		if ($r=db_fetch_array(db_select("SELECT variant_type,vare_id FROM variant_varer WHERE upper(variant_stregkode) = '$varenr[0]'",__FILE__ . " linje " . __LINE__))) {
			$vare_id[0]=$r['vare_id'];
			$variant_type[0]=$r['variant_type'];
		} else $variant_type[0]='';
		if ($variant_type[0] && $vare_id[0]) $string="select varer.id as vare_id, vare_lev.lev_id as konto_id, adresser.firmanavn as firmanavn from vare_lev,varer,adresser where varer.id = '$vare_id[0]' and vare_lev.vare_id = '$vare_id[0]' and adresser.id = vare_lev.lev_id order by vare_lev.posnr";
		else $string="select varer.id as vare_id, vare_lev.lev_id as konto_id, adresser.firmanavn as firmanavn from vare_lev,varer,adresser where (upper(varer.varenr) = '$varenr[0]' or upper(varer.stregkode) = '$varenr[0]') and vare_lev.vare_id = varer.id and adresser.id = vare_lev.lev_id order by vare_lev.posnr";

			$r=db_fetch_array(db_select($string,__FILE__ . " linje " . __LINE__));
			$konto_id=$r['konto_id'];
			$firmanavn=$r['firmanavn'];
			$id = indset_konto($id, $konto_id);
		}
		if ((!$id)&&($konto_id)&&($firmanavn)) {
		if ($row = db_fetch_array(db_select("select ordrenr from ordrer where art='KO' or art='KK' order by ordrenr desc",__FILE__ . " linje " . __LINE__))) {$ordrenr=$row[ordrenr]+1;}
			else {$ordrenr=1;}
#			if ($row= db_fetch_array(db_select("select ansat_id from brugere where brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__))) {
#				if ($row= db_fetch_array(db_select("select afd from ansatte where id='$row[ansat_id]'",__FILE__ . " linje " . __LINE__))) {
#					if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager_id=$row['kodenr'];}
#				}
#			}
#			if (!$lager_id) {$lager_id='0';}
			$kred_ord_id=$kred_ord_id*1;
			if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
			if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
			db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, lev_navn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt, betalingsdage, betalingsbet, cvrnr, notes, art, ordredate, momssats, status, ref, sum, lev_adr, hvem, tidspkt, valuta, kred_ord_id,omvbet) values ($ordrenr, $konto_id, '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$lev_navn', '$lev_addr1', '$lev_addr2', '$lev_postnr', '$lev_bynavn', '$lev_kontakt', '$betalingsdage', '$betalingsbet', '$cvrnr', '$notes', '$art', '$ordredate', '$momssats', $status, '$ref', '$sum', '$lev_adr', '$brugernavn', '$tidspkt', '$valuta', '$kred_ord_id','$omlev')",__FILE__ . " linje " . __LINE__);
			$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {$id=$row['id'];}
		}	elseif(($konto_id)&&($firmanavn)) {
			$sum=0;
			for($x=1; $x<=$linjeantal; $x++) {
				$antal[$x]=afrund($antal[$x],2);
				
				if (!$varenr[$x]) {$antal[$x]=0; $pris[$x]=0; $rabat[$x]=0;}
				elseif ($antal[$x]<0 && $art!='KK' && !$negativt_lager) {
					$query = db_select("select gruppe, beholdning from varer where varenr = '$varenr[$x]' or stregkode = '$varenr[$x]'",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					if (!$row['beholdning']){$row['beholdning']=0;}
					if ($row['beholdning']-$antal[$x]<0) {
						$tmp=abs($antal[$x]);
						list($a,$b)=explode(",",dkdecimal($row['beholdning']));
						if ($b*1) $a=$a.",".$b*1;
						print "<BODY onLoad=\"javascript:alert('Du kan ikke returnere $antal[$x] n&aring;r lagerbeholdningen er $a ! (Varenr: $varenr[$x])')\">";
						$bogfor=0;
					}
				}
				elseif (($art=='KK')&&($kred_ord_id)) { ###################	 Kreditnota ####################

					if (!$vare_id[$x]) $vare_id[$x]=find_vare_id($varenr[$x]);
					if (!$hurtigfakt && $vare_id[$x]) {
						$r = db_fetch_array(db_select("select grupper.box8, grupper.box9 from grupper,varer where varer.id = '$vare_id[$x]' and grupper.kodenr = varer.gruppe and grupper.art='VG'",__FILE__ . " linje " . __LINE__));
						$batch[$x]=$r['box9'];
						if ($r['box8'] == 'on') {
							$rest=0;
							if ($batch[$x]) $query = db_select("select id, rest, lager from batch_kob where vare_id = '$vare_id[$x]' and ordre_id = '$kred_ord_id'",__FILE__ . " linje " . __LINE__);
							else $query = db_select("select id, antal, lager from batch_kob where vare_id = '$vare_id[$x]' and ordre_id = '$kred_ord_id'",__FILE__ . " linje " . __LINE__);
							while ($row = db_fetch_array($query)) {
								if ($batch && $row['rest']) $rest=$rest+$row['rest'];
								else $rest=$rest+$row['antal'];
								$lager[$x]=$row['lager'];
							}
							$tmp=$leveres[$x]*-1;
							if (($rest<$tmp)&&($lager[$x]<='0')) {
								if ($batch[$x]) print "<BODY onLoad=\"javascript:alert('Du kan ikke returnere $tmp n&aring;r der er $rest tilbage fra ordre nr: $kred_ord_nr! (Varenr: $varenr[$x])')\">";
								else print "<BODY onLoad=\"javascript:alert('Du kan ikke returnere $tmp n&aring;r der er k&oslash;bt $rest på ordre nr: $kred_ord_nr! (Varenr: $varenr[$x])')\">";
								$bogfor=0;
							} elseif (!$negativt_lager) {
								$r = db_fetch_array(db_select("select beholdning from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
								if ($r['beholdning']<$tmp) {
									print "<BODY onLoad=\"javascript:alert('Du kan ikke returnere $tmp n&aring;r lagerbeholdningen er $r[beholdning]! (Varenr: $varenr[$x])')\">";
									$bogfor=0;
								}
							}
						}
					} elseif (!$vare_id[$x] && $varenr[$x]) { 
						print "<BODY onLoad=\"javascript:alert('Varenr: $varenr[$x] eksisterer ikke??')\">";
						$bogfor=0;
					}
					if ($antal[$x]>0) {
						print "<BODY onLoad=\"javascript:alert('Du kan ikke kreditere et negativt antal (Varenr: $varenr[$x])')\">";
						$antal[$x]=$antal[$x]*-1;
						$bogfor=0;
					}
				} ############################ Kreditnota slut ######################
				if (!$vare_id[$x]){$vare_id[$x]=find_vare_id($varenr[$x]);}
				if (($posnr_ny[$x]=="-")&&($status<1)) {
					$query = db_select("select * from batch_kob where linje_id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
					if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke slette varelinje $posnr_ny[$x] da der &eacute;r solgt vare(r) fra denne batch')\">";}
					else {
						db_modify("delete from reservation where linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
						db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					}
				}
				elseif (!strstr($submit,"Kopi")) {
					if (!$antal[$x]){$antal[$x]=1;}
#					if ($posnr_ny[$x]=="-") {$antal[$x]=0;}
					if ($status>0) {
						$tidl_lev[$x]=0;
						if ($vare_id[$x]) {
							if ($serienr[$x]) {
								$sn_antal=0;
								$query = db_select("select * from serienr where kobslinje_id = '$linje_id[$x]' order by serienr",__FILE__ . " linje " . __LINE__);
								while ($row = db_fetch_array($query)){$sn_antal++;}
								if (($sn_antal>0)&&($antal[$x]<$sn_antal)) {
									 print "<BODY onLoad=\"javascript:alert('Posnr: $posnr_ny[$x] - $varenr[$x] Antal kan ikke v&aelig;re mindre end antal registrerede serienr!')\">";
									$antal[$x]=$sn_antal;
								}
								$query = db_select("select * from serienr where salgslinje_id = '$linje_id[$x]' order by serienr",__FILE__ . " linje " . __LINE__);
								while ($row = db_fetch_array($query)){$sn_antal--;}
								if (($sn_antal<0)&&($antal[$x]>$sn_antal)&&($art!='KK'))	{
									 print "<BODY onLoad=\"javascript:alert('Posnr: $posnr_ny[$x] - $varenr[$x] Antal kan ikke v&aelig;re st&oslash;rre end antal serienr!')\">";
									$antal[$x]=$sn_antal;
								}
								$query = db_select("select * from serienr where salgslinje_id = '$linje_id[$x]' order by serienr",__FILE__ . " linje " . __LINE__);
								while ($row = db_fetch_array($query)){$sn_antal++;}
								if (($sn_antal>0)&&($antal[$x]<$sn_antal)&&($art=='KK'))	{
									 print "<BODY onLoad=\"javascript:alert('Posnr: $posnr_ny[$x] - $varenr[$x] Antal kan ikke v&aelig;re mindre end antal serienr!')\">";
									 $antal[$x]=$sn_antal;
								}
							}
							$status=2;
							$reserveret[$x]=0;
							$query = db_select("select * from reservation where linje_id = $linje_id[$x] and batch_salg_id!=0",__FILE__ . " linje " . __LINE__);
							while ($row = db_fetch_array($query))$reserveret[$x]=$reserveret[$x]+$row['antal'];
							$reserveret[$x]=afrund($reserveret[$x],2);
							if ($antal[$x]>=0 && $antal[$x]<$reserveret[$x]) {
								print "<BODY onLoad=\"javascript:alert('Der er $reserveret[$x] reservationer p&aring; varenr. $varenr[$x]: antal &aelig;ndret fra $antal[$x] til $reserveret[$x]!')\">";
								$antal[$x]=$reserveret[$x]; $submit="Gem"; $status=1;
							}
#cho __LINE__." select * from batch_kob where linje_id = '$linje_id[$x]'<br>";
							$tidl_lev[$x]=0;
							$query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
							while($row = db_fetch_array($query)){
								$tidl_lev[$x]+=$row['antal'];
#cho __LINE__." $tidl_lev[$x]<br>";
#cho __LINE__." $solgt[$x]<br>";
								$solgt[$x]=$solgt[$x]-$row['rest'];
#cho __LINE__." $solgt[$x]<br>";
							}
							$tidl_lev[$x]=afrund($tidl_lev[$x],2);
							if ($posnr_ny[$x]=="-") {
								if ($tidl_lev[$x]!=0) $posnr_ny[$x]=0;
								elseif ($solgt[$x]!=0) $posnr_ny[$x]=0;
								elseif ($reserveret[$x]!=0) {
									$posnr_ny[$x]=$posnr[$x];
									print "<BODY onLoad=\"javascript:alert('Varenr: $varenr[$x] Der er reserveret varer fra denne varelinje - linjen kan ikke slettes!')\">";
								}
								else {
									db_modify("delete from reservation where linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
									db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
									$posnr_ny[$x]=1;
								}
								if (!$posnr_ny[$x]) {
									$r=db_fetch_array(db_select("select posnr from ordrelinjer where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__));
									$posnr_ny[$x]=$r['posnr'];
								}
							} elseif ($antal[$x]>0) {
/* 20150209			if ($antal[$x]<$solgt[$x]) { 20150309
									print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x]: antal &aelig;ndret fra $antal[$x] til $solgt[$x]!')\">";
									$antal[$x]=$solgt[$x]; $submit="Gem"; $status=1;
								} */
								if ($antal[$x]<$tidl_lev[$x]) {
									print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x]: antal &aelig;ndret fra $antal[$x] til $tidl_lev[$x]!')\">";
									$antal[$x]=$tidl_lev[$x]; $submit="Gem"; $status=1;
								}
								if ($leveres[$x]>$antal[$x]-$tidl_lev[$x]) {
									$temp=$antal[$x]-$tidl_lev[$x];
									print "<BODY onLoad=\"javascript:alert('Varenr. $varenr[$x]: antal klar til modtagelse &aelig;ndret fra $leveres[$x] til $temp!')\">";
									$leveres[$x]=$temp; $submit="Gem"; $status=1;
								}
								elseif ($leveres[$x]<0) {
									$temp=0;
									print "<BODY onLoad=\"javascript:alert('Varenr. $varenr[$x]: modtag &aelig;ndret fra $leveres[$x] til $tidl_lev[$x]!')\">";
									$leveres[$x]=$temp; $submit="Gem"; $status=1;
								}
							} else {
								$tidl_lev[$x]=0;
								$query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
								while($row = db_fetch_array($query)) $tidl_lev[$x]+=$row['antal'];
#cho __LINE__." $tidl_lev[$x]<br>"; 								
								if ($antal[$x]>$tidl_lev[$x]) {
									print "<BODY onLoad=\"javascript:alert('Varenr. $varenr[$x]: antal &aelig;ndret fra $antal[$x] til $tidl_lev[$x]!')\">";
									$antal[$x]=$tidl_lev[$x]; $submit="Gem"; $status=1;
								}
								if ($leveres[$x]<$antal[$x]+$tidl_lev[$x]) {
									$tmp1=$leveres[$x]*-1;
									$tmp2=abs($antal[$x]+$tidl_lev[$x]);

									print "<BODY onLoad=\"javascript:alert('Posnr $posnr_ny[$x] :return&eacute;r &aelig;ndret fra $tmp1 til $tmp2!')\">";
									$leveres[$x]=$antal[$x]+$tidl_lev[$x]; $submit="Gem"; $status=1;
								}
								elseif ($leveres[$x] > 0) {
									$tmp1=$leveres[$x]*-1;
									$tmp2=0;
									print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x]: return&eacute;r &aelig;ndret fra $tmp1 til $tmp2!')\">";
									$leveres[$x]=0; $submit="Gem"; $status=1;
								}
							}
							if (afrund($antal[$x]-$tidl_lev[$x],2)) $status=1;
						} elseif ($posnr_ny[$x]=="-") {
							db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
							$posnr_ny[$x]=1;
						}
					}
					if (!$leveres[$x]){$leveres[$x]=0;}
					$sum=$sum+($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
					if ((!strpos($posnr_ny[$x], '+'))&&($id)) {
						$posnr_ny[$x]=afrund($posnr_ny[$x],0);
						if ($posnr_ny[$x]>=1) {db_modify("update ordrelinjer set posnr='$posnr_ny[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);}
						else print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte et - (minus) som pos nr for at slette en varelinje')\">";
					}
					if (($status<2)||(($antal[$x]>0)&&($status==2)&&($antal[$x]>=$tidl_lev[$x]))||(($antal[$x]<0)&&($status==2)&&($antal[$x]<=$tidl_lev[$x]))) {
						if ($serienr[$x]) $antal[$x]=afrund($antal[$x],0);
						if (! $tidl_lev[$x]) $tidl_lev[$x]=0;
						if ($omvbet[$x]) $omvbet[$x]='on';
						db_modify("update ordrelinjer set beskrivelse='$beskrivelse[$x]', antal='$antal[$x]', leveres='$leveres[$x]', leveret='$tidl_lev[$x]', pris='$pris[$x]', rabat='$rabat[$x]', projekt='$projekt[$x]',omvbet='$omvbet[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					} 
					if ($leveret[$x]!=$tidl_lev[$x]) db_modify("update ordrelinjer set leveret='$tidl_lev[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);

					if ((strpos($posnr_ny[$x], '+'))&&($id)) indsaet_linjer($id, $linje_id[$x], $posnr_ny[$x]);
				}
			}
			if (($posnr_ny[0]>0)&&(!strstr($submit,'Opslag'))) {
				if ($varenr[0]) {
					$varenr[0]=strtoupper($varenr[0]);
					if ($r=db_fetch_array(db_select("SELECT id,vare_id,variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr[0]'",__FILE__ . " linje " . __LINE__))) {
						$vare_id[0]=$r['vare_id'];
						$variant_type[0]=$r['variant_type'];
						$variant_id[0]=$r['id'];
					} else $variant_type[0]='';
					if ($variant_type[0] && $vare_id[0]) $string="SELECT * FROM varer WHERE id = '$vare_id[0]'";
					else $string="SELECT * FROM varer WHERE upper(varenr) = '$varenr[0]' or upper(stregkode) = '$varenr[0]'";
					if ($r0 = db_fetch_array(db_select("$string",__FILE__ . " linje " . __LINE__))) {
#						$variant_type[0]=$r0['variant'];
						$vare_id[0]=$r0['id'];
						$varenr[0]=db_escape_string($r0['varenr']);
						$serienr[0]=trim($r0['serienr']);
						$samlevare[0]=trim($r0['samlevare']);
						if (!$beskrivelse[0]) $beskrivelse[0]=db_escape_string($r0['beskrivelse']);
						if (!$enhed[0])$enhed[0]=db_escape_string($r0['enhed']);
						if (!$rabat[0]) $rabat[0]=$r0['rabat'];
						if (!$antal[0]) $antal[0]=1;
						if (!$rabat[0]) $rabat[0]=0;
						if (!$lev_varenr[0]) {
							if (!$konto_id) {
								if ($r1=db_fetch_array(db_select("select * from adresser where kontonr = '$kontonr' and art = 'K'",__FILE__ . " linje " . __LINE__))) {
									$konto_id=$r1['id'];
								}
							}
							if ($r1=db_fetch_array(db_select("select * from vare_lev where vare_id = '$vare_id[0]' and lev_id = '$konto_id'",__FILE__ . " linje " . __LINE__))) {
								if (!$pris[0]) $pris[0]=$r1['kostpris'];
								$lev_varenr[0]=db_escape_string($r1['lev_varenr']);
							}
						}
						if (!$pris[0]) $pris[0]=$r0['kostpris'];
						$pris[0]=$pris[0]*1;
						if ($valuta && $valuta!='DKK') {
							if ($r1=db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr::INT and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
								$pris[0]=$pris[0]*100/$r1['kurs'];
							} else {
								$tmp = dkdato($ordredate);
								print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
							}
						}
						if ($serienr[0]) $antal[0]=afrund($antal[0],0);
						if ($r1=db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[0]'",__FILE__ . " linje " . __LINE__))) {
							$r2=db_fetch_array(db_select("select box4,box6,box7,box8 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'",__FILE__ . " linje " . __LINE__));
							$bogfkto[0] = $r2['box4'];
							$omvare[0] = $r2['box6'];
							$momsfri[0] = $r2['box7'];
							$lagerfort[0] = $r2['box8'];
						}
						# if (($omvare[0]!=NULL)&&($rabatsats>$r2['box6'])) $rabatsats=$r2['box6'];
						if ($bogfkto[0] && !$momsfri[0]) {
							$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$bogfkto[0]' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
							if ($tmp=trim($r2['moms'])) { # f.eks S3
								$tmp=substr($tmp,1); #f.eks 3
								$r2 = db_fetch_array(db_select("select box2 from grupper where art = 'SM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__));
								if ($r2['box2']) $varemomssats[0]=$r2['box2']*1;
							}	else $varemomssats[0]=$momssats;
						} elseif (!$momsfri[0]) $varemomssats[0]=$momssats;
						else $varemomssats[0]=$momssats;
						
						if ($variant_type[0]) {
							$varianter=explode(chr(9),$variant_type[0]);
							for ($y=0;$y<count($varianter);$y++) {
								$r1=db_fetch_array(db_select("select variant_typer.beskrivelse as vt_besk,varianter.beskrivelse as var_besk from variant_typer,varianter where variant_typer.id = '$varianter[$y]' and variant_typer.variant_id=varianter.id",__FILE__ . " linje " . __LINE__));
								$beskrivelse[0].=", ".$r1['var_besk'].":".$r1['vt_besk'];
							}
						}
						if ($samlevare[0]) {
							samlevare($id,$art,$vare_id[0],$antal[0]);
						} else {
							($omlev && $omvare[0])?$omvbet[0]='on':$omvbet[0]=''; #20150415
echo "insert into ordrelinjer (ordre_id,posnr,varenr,vare_id,beskrivelse,enhed,antal,pris,rabat,serienr,lev_varenr,momsfri,variant_id,samlevare,omvbet,momssats) values ('$id','$posnr_ny[0]','$varenr[0]','$vare_id[0]','$beskrivelse[0]','$enhed[0]','$antal[0]','$pris[0]','$rabat[0]','$serienr[0]','$lev_varenr[0]','$momsfri[0]','$variant_id[0]','$samlevare[0]','$omvbet[0]',$varemomssats[0])<br>";							
							db_modify("insert into ordrelinjer (ordre_id,posnr,varenr,vare_id,beskrivelse,enhed,antal,pris,rabat,serienr,lev_varenr,momsfri,variant_id,samlevare,omvbet,momssats) values ('$id','$posnr_ny[0]','$varenr[0]','$vare_id[0]','$beskrivelse[0]','$enhed[0]','$antal[0]','$pris[0]','$rabat[0]','$serienr[0]','$lev_varenr[0]','$momsfri[0]','$variant_id[0]','$samlevare[0]','$omvbet[0]',$varemomssats[0])",__FILE__ . " linje " . __LINE__);
						}
					} else {
						$submit='Opslag';
#						$varenr[0]=$varenr[0]."*";
					}
				if ($status==2) $status=1;
				}
				elseif ($beskrivelse[0]) {
					db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$id', '$posnr_ny[0]', '$beskrivelse[0]')",__FILE__ . " linje " . __LINE__);
					if ($status==2){$status=1;}
				}
			}
			 $query = db_select("select tidspkt from ordrer where id=$id and hvem='$brugernavn'",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {
				if (strlen($levdate)<6){$opdat="update ordrer set firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', kontakt='$kontakt', lev_navn='$lev_navn',	lev_addr1='$lev_addr1',	lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr',momssats='$momssats', notes='$notes', art='$art', ordredate='$ordredate', status=$status, ref='$ref', fakturanr='$fakturanr', lev_adr='$lev_adr', hvem = '$brugernavn', tidspkt='$tidspkt', valuta='$valuta', valutakurs='$valutakurs', projekt='$projekt[0]' where id=$id";}
				else {$opdat="update ordrer set firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', kontakt='$kontakt', lev_navn='$lev_navn',	lev_addr1='$lev_addr1',	lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr',momssats='$momssats', notes='$notes', art='$art', ordredate='$ordredate', levdate='$levdate', status=$status, ref='$ref', fakturanr='$fakturanr', lev_adr='$lev_adr', hvem = '$brugernavn', tidspkt='$tidspkt', valuta='$valuta', valutakurs='$valutakurs', projekt='$projekt[0]' where id=$id";}
				db_modify($opdat,__FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select fakturanr from ordrer where id=$id",__FILE__ . " linje " . __LINE__));
			}
			else {
				$query = db_select("select hvem from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Ordren er overtaget af $row[hvem]')\">";}
				if ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
				else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php\">";
			}
		}
		if (($godkend=='on')&&($status==2)) {
			$opret_ny=0;
			for($x=1; $x<=$linjeantal; $x++) {
				if ($antal[$x]!=$tidl_lev[$x]) {$opret_ny=1;}
			}
			if ($opret_ny==1)	{
				$query = db_select("select hvem from ordrer where id=$id",__FILE__ . " linje " . __LINE__);

				db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, lev_navn,	lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt, betalingsdage, betalingsbet, cvrnr, notes, art, ordredate, momssats, status, ref, sum, lev_adr, valuta) values ($ordrenr, $konto_id, '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$lev_navn',	'$lev_addr1',	'$lev_addr2',	'$lev_postnr',	'$lev_bynavn', '$lev_kontakt', '$betalingsdage', '$betalingsbet', '$cvrnr', '$notes', '$art', '$ordredate', '$momssats', 1, '$ref', '$sum', '$lev_adr', '$valuta')",__FILE__ . " linje " . __LINE__);
				$query = db_select("select id from ordrer where ordrenr='$ordrenr' order by id desc",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$ny_id=$row[id];
				$ny_sum=0;
				for($x=1; $x<=$linjeantal; $x++) {
					if ($antal[$x]!=$tidl_lev[$x]) {
						$diff[$x]=$antal[$x]-$tidl_lev[$x];
						$antal[$x]=$tidl_lev[$x];
						if ($serienr[$x]) $antal[$x]=afrund($antal[$x],0);
						$r1 =	db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
						$r2 = db_fetch_array(db_select("select box6,box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'",__FILE__ . " linje " . __LINE__));
						$bogfkto[$x] = $r2['box4'];
						(trim($r2['box6']))?$omvare[$x]='on':$omvare[$x]='';
						$momsfri[$x] = $r2['box7'];
						$lagerfort[$x] = $r2['box8'];
						if ($bogfkto[$x] && !$momsfri[$x]) {
							$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$bogfkto[$x]' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
							if ($tmp=trim($r2['moms'])) { # f.eks S3
								$tmp=substr($tmp,1); #f.eks 3
								$r2 = db_fetch_array(db_select("select box2 from grupper where art = 'SM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__));
								if ($r2['box2']) $varemomssats[$x]=$r2['box2']*1;
							}	else $varemomssats[$x]=$momssats;
						} elseif (!$momsfri[$x]) $varemomssats[$x]=$momssats;
						else $varemomssats[$x]=$momssats;
				
						db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, serienr, lev_varenr, momsfri,projekt,momssats) values ('$ny_id', '$posnr_ny[$x]', '$varenr[$x]', '$vare_id[$x]', '$beskrivelse[$x]', '$enhed[$x]', '$diff[$x]', '$pris[$x]', '$rabat[$x]', '$serienr[$x]', '$lev_varenr[$x]','$momsfri[$x]','$projekt[$x]','$varemomssats[$x]')",__FILE__ . " linje " . __LINE__);
						db_modify("update ordrelinjer set antal=$antal[$x] where id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
						$ny_sum=$ny_sum+$diff[$x]*($pris[$x]-$pris[$x]*$rabat[$x]/100);
					}
				}
				db_modify("update ordrer set sum=$ny_sum where id = $ny_id",__FILE__ . " linje " . __LINE__);
			}
		}
		if ((strstr($submit,'Kopi'))||(strstr($submit,'Kred'))) {
#			if ($kred_ord_id) {
#				db_modify("update ordrer set kred_ord_id='$kred_ord_id' where id='$id'",__FILE__ . " linje " . __LINE__);}
			for($x=1; $x<=$linjeantal; $x++) {
#				$posnr[$x]=$x;	
				if (!$vare_id[$x] && $varenr[$x]) {
					$query = db_select("select id from varer where varenr = '$varenr[$x]' or stregkode = '$varenr[$x]'",__FILE__ . " linje " . __LINE__);
					if ($row = db_fetch_array($query)) $vare_id[$x]=$row['id'];
				}
				if (strstr($submit,'Kred')&&$vare_id[$x]&&!$hurtigfakt) {
					$antal[$x]=0;
					$query = db_select("select rest from batch_kob where vare_id = '$vare_id[$x]' and ordre_id = $kred_ord_id",__FILE__ . " linje " . __LINE__);
					while ($row = db_fetch_array($query)) $antal[$x]=$antal[$x]-$row['rest'];
				} elseif ($hurtigfakt && strstr($submit,'Kred')) $antal[$x]=$antal[$x]*-1;
				if ($serienr[$x]) $serienr[$x]="on";
				if ($varemomssats[$x]=='') $varemomssats[$x]=find_varemomssats($linje_id[$x]); #20141106
				if ($vare_id[$x]) {
					db_modify("insert into ordrelinjer (ordre_id,posnr,varenr,vare_id,beskrivelse,enhed,antal,pris,rabat,serienr,lev_varenr,momsfri,kred_linje_id,momssats) values ('$id','$x','$varenr[$x]','$vare_id[$x]','$beskrivelse[$x]','$enhed[$x]',$antal[$x],'$pris[$x]','$rabat[$x]','$serienr[$x]','$lev_varenr[$x]','$momsfri[$x]','$linje_id[$x]','$varemomssats[$x]')",__FILE__ . " linje " . __LINE__);
				} else {
					db_modify("insert into ordrelinjer (ordre_id,posnr,beskrivelse,enhed) values ('$id','$x','$beskrivelse[$x]','$enhed[$x]')",__FILE__ . " linje " . __LINE__);
				}
			}
		} 
		$vis=1;
	transaktion("commit");
	}
	if (isset($_POST['udskriv']) && $_POST['udskriv']=='Udskriv') {
		$id=if_isset($_POST['id']);
		$status=if_isset($_POST['status']);
		$ps_fil="formularprint.php";
		if ($status<=1) $formular=12;
		if ($status==2) $formular=13;
		if ($status>2) $formular=14;
		$udskriv_til='PDF';
	  print "<meta http-equiv=\"refresh\" content=\"0;URL=$ps_fil?id=$id&formular=$formular&udskriv_til=$udskriv_til\">\n";
	}
##########################OPSLAG################################

	if (strstr($submit,'Opslag')) {
		if ((strstr($fokus,'kontonr'))&&(!$id)) {kontoopslag($sort, $fokus, $id, $kontonr);}
		if ((strstr($fokus,'firmanavn'))&&(!$id)) {kontoopslag($sort, $fokus, $id, $firmanavn);}
		if ((strstr($fokus,'addr1'))&&(!$id)) {kontoopslag($sort, $fokus, $id, $addr1);}
		if ((strstr($fokus,'addr2'))&&(!$id)) {kontoopslag($sort, $fokus, $id, $addr2);}
		if ((strstr($fokus,'postnr'))&&(!$id)) {kontoopslag($sort, $fokus, $id, $postnr);}
		if ((strstr($fokus,'bynavn'))&&(!$id)) {kontoopslag($sort, $fokus, $id, $bynavn);}
		if ((strstr($fokus,'vare'))&&($art!='DK')) {vareopslag($sort, 'varenr', $id, $vis, $ref, $varenr[0]);}
		if ((strstr($fokus,'besk'))&&($art!='DK')) {vareopslag($sort, 'beskrivelse', $id, $vis, $ref, $beskrivelse[0]);}
		if (strstr($fokus,'kontakt')){ansatopslag($sort, $fokus, $id, $vis);}
	}

##########################BOGFOR################################

	if ((strstr($submit,'Bogf'))&&($bogfor!=0)&&($status==2)) {
	if ($valuta && $valuta!='DKK') {
		if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr::INT and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
			$valutakurs=$r['kurs'];
		} else {
			$valutakurs='';
		}
	} else $valutakurs=100;
	if (!$valutakurs) {
		$tmp = dkdato($ordredate);
		print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
	} elseif(!$fakturanr) print "<BODY onLoad=\"javascript:alert('Fakturanummer mangler')\">";
	else {
			db_modify("update ordrer set valutakurs = '$valutakurs' where id = '$id'",__FILE__ . " linje " . __LINE__);
			$linjeantal=0;
			$q = db_select("select id from ordrelinjer where ordre_id = '$id' order by posnr",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$linjeantal++;
				$linje_id[$linjeantal]=$r['id'];
			}
			for ($x=1;$x<=$linjeantal;$x++) {
				db_modify("update ordrelinjer set posnr = '$x' where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
			if (!$linjeantal) print "<BODY onLoad=\"javascript:alert('Du kan ikke fakturere uden ordrelinjer')\">";
			else print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?id=$id\">";
		}
	}
	if (((strstr($submit,'Modt'))||(strstr($submit,'Return')))&&($bogfor!=0)) {
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		if (!$row = db_fetch_array($query)) {Print "Du kan ikke modtage uden ordrelinjer";}
		else {print "<meta http-equiv=\"refresh\" content=\"0;URL=modtag.php?id=$id\">";}
	}
	if ($popup) print "<meta http-equiv=\"refresh\" content=\"3600;URL=../includes/luk.php\">";
	else print "<meta http-equiv=\"refresh\" content=\"3600;URL=ordreliste.php\">";
	ordreside($id);


######################################################################################################################################

function ordreside($id) {

	global $art;
	global $bogfor;
	global $fokus;
	global $submit;
	global $brugernavn;
	global $returside;

	$r=db_fetch_array(db_SELECT("select box4 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
	$hurtigfakt=$r['box4'];


	if (!$id) $fokus='kontonr';
	print "<form name=ordre action=ordre.php method=post>";
	if ($id)	{
		$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$kontonr = stripslashes($row['kontonr']);
		$konto_id = $row[konto_id];
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
		$cvrnr = stripslashes($row['cvrnr']);
		$ean = stripslashes($row['ean']);
		$institution = stripslashes($row['institution']);
		$betalingsbet = $row['betalingsbet'];
		$betalingsdage = $row['betalingsdage'];
		$valuta=$row['valuta'];
		$projekt[0]=$row['projekt'];
		$valutakurs=$row['valutakurs'];
		$modtagelse = $row['modtagelse'];
		$ref = trim(stripslashes($row['ref']));
		$fakturanr = stripslashes($row['fakturanr']);
		$lev_adr = stripslashes($row['lev_adr']);
		$ordrenr=$row['ordrenr'];
		$kred_ord_id=$row['kred_ord_id'];
		if($row['ordredate']) {$ordredato=dkdato($row['ordredate']);}
		else {$ordredato=date("d-m-y");}
		if ($row['levdate']) {$levdato=dkdato($row['levdate']);}
		$momssats=$row['momssats'];
		$status=$row['status'];
		if (!$status){$status=0;}
		$art=$row['art'];
		$omlev=$row['omvbet'];
		
		if (!$valuta) {
			$valuta='DKK';
			$valutakurs=100;
		}

		$x=0;
		$query = db_select("select id, ordrenr from ordrer where kred_ord_id = '$id' and art ='KK'",__FILE__ . " linje " . __LINE__);
		while ($row2 = db_fetch_array($query)) {
			$x++;
			if ($x>1) {$krediteret=$krediteret.", ";}
			$krediteret=$krediteret."<a href=ordre.php?id=$row2[id]>$row2[ordrenr]</a>";
		}
		if ($status<3) $fokus='vare0';
		else $fokus='';
	}

	if ((strstr($submit,'Kred'))||($art=='KK')) {
		$query = db_select("select ordrenr from ordrer where id = '$kred_ord_id'",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query);
		sidehoved($id, "$returside", "", "", "Leverand&oslash;r kreditnota $ordrenr (kreditering af ordre nr: <a href=ordre.php?id=$kred_ord_id>$row2[ordrenr]</a>)");
	}
	elseif ($krediteret) {sidehoved($id, "$returside", "", "", "Leverand&oslash;rordre $ordrenr (krediteret p&aring; KN nr: $krediteret)");}
	else {sidehoved($id, "$returside", "", "", "Leverand&oslash;rordre $ordrenr");}

	if (!$status){$status=0;}
	print "<input type=hidden name=ordrenr value=$ordrenr>";
	print "<input type=hidden name=status value=$status>";
	print "<input type=hidden name=id value=$id>";
	print "<input type=hidden name=art value=$art>";
#	print "<input type=hidden name=momssats value=$momssats>";
	print "<input type=hidden name=konto_id value=$konto_id>";
	print "<input type=hidden name=kred_ord_id value=$kred_ord_id>";
	if ($status>=3) {
#		print "<input type=hidden name=id value=$id>";
		print "<input type=hidden name=konto_id value=$konto_id>";
		print "<input type=hidden name=kontonr value=\"$kontonr\">";
		print "<input type=hidden name=firmanavn value=\"$firmanavn\">";
		print "<input type=hidden name=addr1 value=\"$addr1\">";
		print "<input type=hidden name=addr2 value=\"$addr2\">";
		print "<input type=hidden name=postnr value=\"$postnr\">";
		print "<input type=hidden name=bynavn value=\"$bynavn\">";
		print "<input type=hidden name=land value=\"$land\">";
		print "<input type=hidden name=kontakt value=\"$kontakt\">";
		print "<input type=hidden name=lev_navn value=\"$lev_navn\">";
		print "<input type=hidden name=lev_addr1 value=\"$lev_addr1\">";
		print "<input type=hidden name=lev_addr2 value=\"$lev_addr2\">";
		print "<input type=hidden name=lev_postnr value=\"$lev_postnr\">";
		print "<input type=hidden name=lev_bynavn value=\"$lev_bynavn\">";
		print "<input type=hidden name=lev_kontakt value=\"$lev_kontakt\">";
		print "<input type=hidden name=levdato value=\"$levdato\">";
		print "<input type=hidden name=cvrnr value=\"$cvrnr\">";
		print "<input type=hidden name=betalingsbet value=\"$betalingsbet\">";
		print "<input type=hidden name=betalingsdage value=\"$betalingsdage\">";
		print "<input type=hidden name=momssats value=\"$momssats\">";
		print "<input type=hidden name=ref value=\"$ref\">";
		print "<input type=hidden name=fakturanr value=\"$fakturanr\">";
		print "<input type=hidden name=modtagelse value=\"$modtagelse\">";
		print "<input type=hidden name=lev_adr value=\"$lev_adr\">";
		print "<input type=hidden name=valuta value=\"$valuta\">";

		print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\" valign = \"top\"><tbody>";
		$ordre_id=$id;
		print "<tr><td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
		print "<tr><td width=100><b>Kontonr</td><td width=100>$kontonr</td></tr>\n";
		print "<tr><td><b>Firmanavn</td><td>$firmanavn</td></tr>\n";
		print "<tr><td><b>Adresse</td><td>$addr1</td></tr>\n";
		print "<tr><td></td><td>$addr2</td></tr>\n";
		print "<tr><td><b>Postnr, by</td><td>$postnr $bynavn</td></tr>\n";
		print "<tr><td><b>Land</td><td>$land</td></tr>\n";
		print "<tr><td><b>Att.:</td><td>$kontakt</td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
		print "<tr><td width=100><b>Ordredato</td><td width=100>$ordredato</td></tr>\n";
		print "<tr><td><b>Lev. dato</td><td>$levdato</td></tr>\n";
		print "<tr><td><b>CVR-nr.</td><td>$cvrnr</td></tr>\n";
		print "<tr><td><b>Betaling</td><td>$betalingsbet&nbsp;+&nbsp;$betalingsdage</td>";
		print "<tr><td><b>Vor ref.</td><td>$ref</td></tr>\n";
		print "<tr><td><b>Fakturanr</td><td>$fakturanr</td></tr>\n";
		print "<tr><td><b>Modtagelse</td><td>$modtagelse</td></tr>\n";
		$tmp=dkdecimal($valutakurs);
		if ($valuta) print "<tr><td><b>Valuta / Kurs</td><td>$valuta / $tmp</td></tr>\n";
		if ($projekt[0]) print "<tr><td><b>Projekt</td><td>$projekt[0]</td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=33%><table cellpadding=0 cellspacing=0 border = 0 width=240>";
		print "<tr><td><b>Leveringsadresse.</td></tr>\n";
		print "<tr><td>Firmanavn</td><td colspan=2>$lev_navn</td></tr>\n";
		print "<tr><td>Adresse</td><td colspan=2>$lev_addr1</td></tr>\n";
		print "<tr><td></td><td colspan=2>$lev_addr2</td></tr>\n";
		print "<tr><td>Postnr, By</td><td>$lev_postnr $lev_bynavn</td></tr>\n";
		print "<tr><td>Att.:</td><td colspan=2>$lev_kontakt</td></tr>\n";
#		print "<tr><td>$lev_adr</td></tr>\n";
		print "</td></tr></tbody></table></td>";
		print "</td></tr><tr><td align=center colspan=3><table cellpadding=1 cellspacing=0 border=1 width=100%><tbody>";
		print "<tr><td colspan=7></td></tr><tr>";
#		print "<td align=center><b>pos</td><td align=center><b>varenr</td><td align=center><b>ant.</td><td align=center><b>enhed</td><td align=center><b>beskrivelse</td><td align=center><b>pris</td><td align=center><b>%</td><td align=center><b>ialt</td><td align=center><b>solgt</td>";
		print "<td align=center title='Position (ordrelinjenummer)'><b>Pos.</td><td align=center><b>Varenr.</td><td align=center><b>Antal</td><td align=center><b>Enhed</td><td align=center><b>Beskrivelse</td><td align=center><b>Pris</td><td align=center title='Rabat i procent'><b>%</td><td align=center><b>I alt</td>";
		if (db_fetch_array(db_select("select * from grupper where art = 'PRJ' order by kodenr",__FILE__ . " linje " . __LINE__))) {
			$vis_projekt='1';
		}
		if ($vis_projekt && !$projekt[0]) print "<td align=center title='Nummer herunder viser projektnummer, hvis ordrelinjen er tilknyttet et projekt'><b>proj.</b></td>";
		else print "<td></td>";
		if (!$hurtigfakt) print "<td align=\"center\"><b>solgt</b></td>";
		print "</tr>\n";
		$x=0;
		if (!$ordre_id){$ordre_id=0;}
		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if ($row['posnr']>0) {
				$x++;
				$linje_id[$x]=$row['id'];
				$vare_id[$x]=$row['vare_id'];
				$posnr[$x]=$row['posnr'];
				$varenr[$x]=stripslashes($row['varenr']);
				$lev_varenr[$x]=stripslashes($row['lev_varenr']);
				$beskrivelse[$x]=stripslashes($row['beskrivelse']);
				$enhed[$x]=stripslashes($row['enhed']);
				$pris[$x]=$row['pris'];
				$rabat[$x]=$row['rabat'];
				$antal[$x]=$row['antal'];
				$serienr[$x]=stripslashes($row['serienr']);
				$momsfri[$x]=$row['momsfri'];
				$varemomssats[$x]=$row['momssats']; #20141106
				$projekt[$x]=$row['projekt'];
				$variant[$x]=$row['variant_id'];
				$omvbet[$x]=$row['omvbet'];
				if ($vare_id[$x]) {
					$r = db_fetch_array(db_select("select gruppe from varer where id = $vare_id[$x]",__FILE__ . " linje " . __LINE__));
					$r = db_fetch_array(db_select("select box6,box9 from grupper where kodenr='$r[gruppe]' and art='VG'",__FILE__ . " linje " . __LINE__));
					$box9[$x]=trim($r['box9']);
					(trim($r['box6']))?$omvare[$x]='on':$omvare[$x]='';
				}
			}
		}
		$linjeantal=$x;
		print "<input type=hidden name=linjeantal value=$x>";
		$totalrest=0;
		$sum=0;
		for ($x=1; $x<=$linjeantal; $x++) {
			if (!$vare_id[$x] && $varenr[$x]) {
				$query = db_select("select id from varer where varenr = '$varenr[$x]' or stregkode = '$varenr[$x]'",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) $vare_id[$x]=$row['id'];
			}
			if (($varenr[$x])&&($vare_id[$x]))	{
				$rest[$x]=0;
				$query = db_select("select id, rest from batch_kob where linje_id = '$linje_id[$x]' and ordre_id = '$ordre_id' and vare_id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
				while ($row = db_fetch_array($query)) $rest[$x]=$rest[$x]+$row['rest'];
				$solgt[$x]=$antal[$x]-$rest[$x];
				$totalrest=$totalrest+$rest[$x];

				$ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				$ialt=afrund($ialt,2);
				$sum=$sum+$ialt;
				 if ($momsfri[$x]!='on' && !$omvbet[$x]) $momssum+=$ialt;
#				$ialt=dkdecimal($ialt);
				$dkpris=dkdecimal($pris[$x]);
				$dkrabat=dkdecimal($rabat[$x]);
				if ($antal[$x]) {
					if ($art=='KK') $dkantal[$x]=dkdecimal($antal[$x]*-1);
					else $dkantal[$x]=dkdecimal($antal[$x]);
					if (substr($dkantal[$x],-1)=='0') $dkantal[$x]=substr($dkantal[$x],0,-1);
					if (substr($dkantal[$x],-1)=='0') $dkantal[$x]=substr($dkantal[$x],0,-2);
				}
			}
			else {$antal[$x]=''; $dkpris=''; $dkrabat=''; $ialt='';}
			print "<tr>";
			print "<input type=hidden name=posn$x value=$posnr[$x]><td align=right>$posnr[$x]</td>";
			print "<input type=hidden name=vare$x value=\"$varenr[$x]\"><td align=right>$varenr[$x]</td>";
			print "<input type=hidden name=anta$x value=$dkantal[$x]><td align=right>$dkantal[$x]</td>";
			print "<td align=right>$enhed[$x]</td>";
			print "<input type=hidden name=beskrivelse$x value=\"$beskrivelse[$x]\"><td>$beskrivelse[$x]</td>";
			print "<input type=hidden name=pris$x value=$dkpris><td align=right>$dkpris</td>";
			print "<input type=hidden name=raba$x value=$dkrabat><td align=right>$dkrabat</td>";
			print "<input type=hidden name=linje_id[$x] value=$linje_id[$x]>";
			print "<input type=hidden name=serienr[$x] value=$serienr[$x]>";
			print "<input type=hidden name=vare_id[$x] value=$vare_id[$x]>";
			print "<input type=hidden name=lev_varenr[$x] value=\"$lev_varenr[$x]\">";
			print "<input type=hidden name=momsfri[$x] value=\"$momsfri[$x]\">";
			print "<input type=hidden name=omvbet[$x] value=\"$omvbet[$x]\">";#20150415
			print "<input type=hidden name=varemomssats[$x] value=\"$varemomssats[$x]\">"; #20141106
			if (($ialt)&&($art=='KK')) {$ialt=$ialt*-1;}
			print "<td align=right>".dkdecimal($ialt)."</td>";
			print "<input type=hidden name=projekt[$x] value=\"$projekt[$x]\">";
			if ($vis_projekt && !$projekt[0]) {
				$r=db_fetch_array(db_select("select beskrivelse from grupper where art = 'PROJ' and kodenr='$projekt[$x]'",__FILE__ . " linje " . __LINE__));
				print "<td align=right title='$r[projekt]'>$projekt[$x]</td>";
			}
			if ($box9[$x]=='on') {
				if ($art=='KK') $solgt[$x]=$solgt[$x]*-1;
				if ($serienr[$x]) print "<td onClick=\"serienummer($linje_id[$x])\" align=right><u>$solgt[$x]</u></td>";
				else print "<td align=right>$solgt[$x]</td>";
			} elseif ($serienr[$x])  print "<td onClick=\"serienummer($linje_id[$x])\" align=right><u>Snr</u></td>";
			else print "<td align=right><br></td>";

			print "</tr>\n";
		}
		if ($art=='KK') {
			$sum=$sum*-1;
			$momssum=$momssum*-1;
		}
		$moms=$momssum/100*$momssats;
		$moms=afrund($moms,3);
		$ialt=dkdecimal($sum+$moms);
		$sum=dkdecimal($sum);
		$moms=dkdecimal($moms);
		print "<tr><td colspan=8></td></tr>\n";
		print "<tr><td colspan=8><table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" width=100%><tbody>";
		print "<tr>";
		print "<td align=center>Ordresum</td><td align=center>$sum</td>";
		print "<td align=center>Moms</td><td align=center>$moms</td>";
		print "<td align=center>I alt</td><td align=right>$ialt</td>";
		print "</tbody></table></td></tr>\n";
		print "<tr><td align=center colspan=9>";
		print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>";
		if ($art!='KK') {
			print "<td align=center><span title=\"Kopi&eacute;r til ny ordre med samme indhold\"><input type=\"submit\" value=\"Kopi&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></span></td>";
			print "<td align=center><span title=\"Opretter en kreditnota med samme indhold. Kan redigeres inden endelig kreditering\"><input type=\"submit\" value=\"Kredit&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></span></td>";
			print "<td align=center><span title=\"Udskriver ordre til PDF\"><input type=\"submit\" value=\"Udskriv\" name=\"udskriv\" onclick=\"javascript:docChange = false;\"></span></td>";
		}
	}
	else { // Aabne ordrer herunder **************************************************
		print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\" valign = \"top\" width = 100><tbody>";
		$ordre_id=$row['id'];

		print "<tr><td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100>";
		print "<tr><td witdh=200>Kontonr.</td><td colspan=2>";
		if (trim($kontonr)) {print "<input class=\"inputbox\" readonly=readonly size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\"></td></tr>\n";}
		else {print "<input class=\"inputbox\" type=text size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\" onchange=\"javascript:docChange = true;\"></td></tr>\n";}
		print "<tr><td>Firmanavn</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=firmanavn onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$firmanavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Adresse</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=addr1 onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$addr1\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td></td><td colspan=2><input class=\"inputbox\" type=text size=25 name=addr2 onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$addr2\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Postnr, by</td><td><input class=\"inputbox\" type=text size=4 name=postnr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$postnr\" onchange=\"javascript:docChange = true;\"></td><td><input class=\"inputbox\" type=text size=19 name=bynavn onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Land</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=land value=\"$land\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Att.:</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=kontakt onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontakt\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100>";
		if (!$id) {
			print "<tr><td colspan=\"4\" width=\"100%\" align=\"center\" valign=\"top\"><span title=\"Klik her for at importere en elektronisk faktura af typen oioubl\"><a href=ublimport.php>Importer OIOUBL faktura</a></span></td></tr>";
			print "<tr><td colspan=\"4\" width=\"100%\"><hr width=\"90%\"></td></tr>";
		}
		print "<tr><td>CVR-nr.</td><td><input class=\"inputbox\" type=text size=15 name=cvrnr value=\"$cvrnr\" onchange=\"javascript:docChange = true;\"></td>";
		$dkmomssats=dkdecimal($momssats);
		print "<td>Momssats&nbsp;</td><td><input class=\"inputbox\" type=text style=text-align:right size=5 name=momssats value=\"$dkmomssats\" onchange=\"javascript:docChange = true;\">%</td></td></tr>\n";
		print "<tr><td>Ordredato</td><td><input class=\"inputbox\" type=text style=text-align:right size=10 name=ordredato value=\"$ordredato\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td>Lev.&nbsp;dato</td><td><input class=\"inputbox\" type=text style=text-align:right size=10 name=levdato value=\"$levdato\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		$list=array();
		$beskriv=array();
		$list[0]='DKK';
		$x=0;
		$q = db_select("select * from grupper where art = 'VK' order by box1 ",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			$x++;
			$list[$x]=$r['box1'];
			$beskriv[$x]=$r['beskrivelse'];
		}
		$tmp=$x;
		if ($x>0) {
			$list[0]='DKK';
			$beskriv[0]='Danske kroner';
			print "<tr><td>Valuta</td>";
			print "<td><select class=\"inputbox\" name=valuta>";
			for ($x=0; $x<=$tmp; $x++) {
				if ($valuta!=$list[$x]) print "<option title=\"$beskriv[$x]\" onchange=\"javascript:docChange = true;\">$list[$x]</option>";
				else print "<option title=\"$beskriv[$x]\" selected=\"selected\" onchange=\"javascript:docChange = true;\">$list[$x]</option>";
			}
			print "</SELECT></td>";
		} else print "<tr><td witdh=200></tr>";
		$list=array();
		$beskriv=array();
		$x=0;
		$q = db_select("select * from grupper where art = 'PRJ' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			$x++;
			$list[$x]=$r['kodenr'];
			$beskriv[$x]=$r['beskrivelse'];
		}
		$prj_antal=$x;
		if ($x>0) {
			$vis_projekt='1';
			print "<td><span title= 'kostpris';>Projekt</span></td>";
			print "<td><select class=\"inputbox\" name=projekt[0]>";
			for ($x=0; $x<=$prj_antal; $x++) {
				if ($projekt[0]!=$list[$x]) print "<option title=\"$beskriv[$x]\" onchange=\"javascript:docChange = true;\">$list[$x]</option>";
				else print "<option title=\"$beskriv[$x]\" selected=\"selected\" onchange=\"javascript:docChange = true;\">$list[$x]</option>";
			}
			print "</SELECT></td></tr>";
		} else print "<tr><td colspan=2 witdh=200></tr>";


		print "<tr><td>Betaling</td>";
		print "<td colspan=2><select class=\"inputbox\" name=betalingsbet>";
		print "<option>$betalingsbet</option>";
		if ($betalingsbet!='Forud') 	{print "<option>Forud</option>"; }
		if ($betalingsbet!='Kontant')	{print "<option>Kontant</option>"; }
		if ($betalingsbet!='Efterkrav')	{print "<option>Efterkrav</option>"; }
		if ($betalingsbet!='Netto'){print "<option>Netto</option>"; }
		if ($betalingsbet!='Lb. md.'){print "<option>Lb. md.</option>";}
		if (($betalingsbet=='Kontant')||($betalingsbet=='Efterkrav')||($betalingsbet=='Forud')) {$betalingsdage='';}
			elseif (!$betalingsdage) {$betalingsdage='Nul';}
		if ($betalingsdage) {
			if ($betalingsdage=='Nul') {$betalingsdage=0;}
			print "</SELECT>&nbsp;+<input class=\"inputbox\" type=text size=2 style=text-align:right	name=betalingsdage value=\"$betalingsdage\" onchange=\"javascript:docChange = true;\"></td>";
		}
		print "</tr>";

		if (!$ref) {
			$row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
			if ($row[ansat_id]) {
				$row = db_fetch_array(db_select("select navn from ansatte where id = $row[ansat_id]",__FILE__ . " linje " . __LINE__));
				if ($row[navn]) {$ref=$row['navn'];}
			}
		}
		$q = db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__);
		if ($r = db_fetch_array($q)) {
			$q2 = db_select("select navn from ansatte where konto_id = '$r[id]' and lukket != 'on' order by navn",__FILE__ . " linje " . __LINE__);
			$x=0;
			while ($r2 = db_fetch_array($q2)) {
				$x++;
				if ($x==1) {
					print "<tr><td>Vor ref.</td>";
					print "<td colspan=3><select class=\"inputbox\" name=ref>";
					if ($ref) print "<option>$ref</option>";
				}
				if ($ref!=$r2[navn]) print "<option> $r2[navn]</option>";
			}
			print "</SELECT>";
			if ($x) print "</td></tr>";
		}

		if ($status==0){print "<tr><td>Godkend</td><td><input class=\"inputbox\" type=checkbox name=godkend></td></tr>\n";}
#		elseif ($status==1) {
#			$query = db_select("select * from batch_kob where ordre_id=$id",__FILE__ . " linje " . __LINE__);
#			if(db_fetch_array($query)){print "<tr><td>Dan lev. fakt.</td><td><input class=\"inputbox\" type=checkbox name=godkend></td></tr>\n";}
#			else {
#				$query = db_select("select * from batch_salg where ordre_id=$id",__FILE__ . " linje " . __LINE__);
#				if(db_fetch_array($query)){print "<tr><td>Dan lev. fakt.</td><td><input class=\"inputbox\" type=checkbox name=godkend></td></tr>\n";}
#			}
#		}
#		elseif ($status==1){print "<tr><td>Modtag</td><td><input class=\"inputbox\" type=checkbox name=modtag></td></tr>\n";}
		else {
			print "<tr><td witdh=200>Fakturanr</td><td colspan=2><input class=\"inputbox\" type=text size=23 name=fakturanr value=\"$fakturanr\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		}
		print "</tbody></table></td>";
		print "<td align=center width=33%><table cellpadding=0 cellspacing=0 width='*'>";
		print "<tr><tdcolspan=2 >Leveringsadresse</td></tr>\n";
		print "<tr><td colspan=2 align=center><hr></td></tr>\n";
		print "<tr><td>Firmanavn</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=lev_navn value=\"$lev_navn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Adresse</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=lev_addr1 value=\"$lev_addr1\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td></td><td colspan=2><input class=\"inputbox\" type=text size=25 name=lev_addr2 value=\"$lev_addr2\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Postnr, By</td><td><input class=\"inputbox\" type=text size=4 name=lev_postnr value=\"$lev_postnr\" onchange=\"javascript:docChange = true;\"><input class=\"inputbox\" type=text size=19 name=lev_bynavn value=\"$lev_bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		print "<tr><td>Att.:</td><td colspan=2><input class=\"inputbox\" type=text size=25 name=lev_kontakt value=\"$lev_kontakt\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	#		print "<tr><td><textarea style=\"font-family: helvetica,arial,sans-serif;\" name=lev_adr rows=5 cols=35>$lev_adr</textarea></td></tr>\n";
		print "</td></tr></tbody></table></td>";
		print "</td></tr><tr><td align=center colspan=3><table cellpadding=1 cellspacing=0 width=100><tbody>";
		print "<tr>";
		if ($status==1) {
			print "<td align=center title='Position (ordrelinjenummer)'>Pos.</td><td align=center title='Varenummer'>Varenr.</td><td align=center title='Leverand&oslash;rens varenummer'>Lev.vnr.</td><td align=center>Antal</td><td align=center>Enhed</td><td align=center>Beskrivelse</td><td align=center>Pris</td><td align=center title='Rabat i %'>%</td><td align=center>I alt</td>";
			if ($vis_projekt && !$projekt[0]) print "<td align=center title='Nummer herunder viser projektnummer hvis ordrelinjen er tilknyttet et projekt'>Proj.</td>";
			if ($art=='KK') {print "<td colspan='2' align='center' title='Indtastningsfeltet herunder er det antal, som returneres ved klik p&aring; Return&aecute;r. Antallet i parantes er det, der allerede er returneret'>Return&eacute;r</td>";}
			else {print "<td colspan='2' align='center' title='Indtastningsfeltet herunder er det antal, som modtages ved klik p&aring; Modtag. Antallet i parantes er det, der allerede er modtaget.'>Modtag</td>";}
		}
		else {
			print "<td align=center title='Position (ordrelinjenummer)'>Pos.</td><td align=center title='Varenummer'>Varenr.</td><td align=center title='Leverand&oslash;rens varenummer'>Lev.vnr.</td><td align=center>Antal</td><td>Enhed</td><td align=center>Beskrivelse</td><td align=center>Pris</td><td align=center title='Rabat i %'>%</td><td align=center>I alt</td>";
			if ($vis_projekt && !$projekt[0]) print "<td align=center title='Nummer herunder viser projektnummer, hvis ordrelinjen er tilknyttet et projekt'>Proj.</td>";
			else print "<td></td>";
		}
#cho "OL $omlev<br>";
		if ($omlev) print "<td title =\"Hvis feltet vises er leverandøren underlagt reglerne for omvendt betalingspligt. Er varen ligeledes omfattet vil feltet herunder være afmærket pr default og momsen vil være undertrykt for den pågældende vare.\">O/B</td>";

		print "</tr>\n";
/*
		if ($valuta && $valuta!='DKK') {
			if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
				$valutakurs=$r['kurs'];
			} else {
				$tmp = dkdato($ordredate);
				print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\" onchange=\"javascript:docChange = true;\">";
			}
		} else $valutakurs = 100;
		db_modify("update ordrer set valutakurs='$valutakurs' where ordre_id = '$ordre_id'",__FILE__ . " linje " . __LINE__);
*/
		$ordre_id*=1;
		$x=0;
		$query = db_select("select * from ordrelinjer where ordre_id = $ordre_id order by posnr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query))	{
			if ($row['posnr']>0) {
				$x++;
				$linje_id[$x]=$row['id'];
				$kred_linje_id[$x]=$row['kred_linje_id'];
				$posnr[$x]=$row['posnr'];
				$varenr[$x]=stripslashes(trim($row['varenr']));
				$lev_varenr[$x]=stripslashes(trim($row['lev_varenr']));
				$beskrivelse[$x]=stripslashes(trim($row['beskrivelse']));
				$pris[$x]=$row['pris'];
				$rabat[$x]=$row['rabat'];
				$antal[$x]=$row['antal'];
				$leveres[$x]=$row['leveres'];
				$enhed[$x]=$row['enhed'];
				$vare_id[$x]=$row['vare_id'];
				$momsfri[$x]=$row['momsfri'];
				$projekt[$x]=$row['projekt'];
				$serienr[$x]=stripslashes($row['serienr']);
				$samlevare[$x]=$row['samlevare'];
				($row['omvbet'])?$omvbet[$x]='checked':$omvbet[$x]='';
		#cho "$row[omvbet] OB $omvbet[$x]<br>";
			}
		}
		$linjeantal=$x;
		print "<input type=hidden name=linjeantal value=$linjeantal>";
		$sum=0;
#		if ($status==1){$status=2;}
		for ($x=1; $x<=$linjeantal; $x++)	{
			if ($varenr[$x]) {
				$ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				$ialt=afrund($ialt,2);
				$sum=$sum+$ialt;
				if ($momsfri[$x]!='on' && !$omvbet[$x]) $momssum=$momssum+$ialt;
#				$ialt=dkdecimal($ialt);
				$dkpris=dkdecimal($pris[$x]);
				$dkrabat=dkdecimal($rabat[$x]);
				if ($antal[$x]) {
					if ($art=='KK') {$dkantal[$x]=dkdecimal($antal[$x]*-1);} 
					else {$dkantal[$x]=dkdecimal($antal[$x]);}
					if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-1);}
					if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-2);}
				}
			}
			else {$dkantal[$x]=''; $dkpris=''; $dkrabat=''; $ialt='';}
			print "<input type=\"hidden\" name=\"linje_id[$x]\" value=\"$linje_id[$x]\">";
			print "<input type=\"hidden\" name=\"vare_id[$x]\" value=\"$vare_id[$x]\">";
			print "<input type=\"hidden\" name=\"kred_linje_id[$x]\" value=\"$kred_linje_id[$x]\">";
			print "<input type=\"hidden\" name=\"serienr[$x]\" value=\"$serienr[$x]\">";
			print "<input type=\"hidden\" name=\"omvbet[$x]\" value=\"$omvbet[$x]\">";
			print "<tr>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=3 name=posn$x value='$x' onchange=\"javascript:docChange = true;\"></td>";
			print "<td title='Varenummer kan ikke &aelig;ndres. Opret i stedet en ny linje og slet denne linje ved at skrive et minustegn i Pos.-feltet til venstre. Flyt om p&aring; linjerne ved at angive nye numre i Pos.-feltet eventuelt som decimaltal.'><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=readonly size=7 name=vare$x onfocus=\"document.forms[0].fokus.value=this.name;\" value='$varenr[$x]'></td>";
			print "<td><input class=\"inputbox\" type=text size=7 name=lev_varenr$x value='$lev_varenr[$x]' onchange=\"javascript:docChange = true;\"></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=4 name=anta$x value='$dkantal[$x]' onchange=\"javascript:docChange = true;\"></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=readonly size=3 value=\"$enhed[$x]\"></td>";
			print "<td><input class=\"inputbox\" type=\"text\" size=58 name=beskrivelse$x value=\"$beskrivelse[$x]\" onchange=\"javascript:docChange = true;\"></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=10 name=pris$x value='$dkpris' onchange=\"javascript:docChange = true;\"></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=4 name=raba$x value='$dkrabat' onchange=\"javascript:docChange = true;\"></td>";
			if ($art=='KK') $ialt=$ialt*-1;
			if ($varenr[$x]) $tmp=dkdecimal($ialt);
			else $tmp=NULL;
			print "<td align=right><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee;text-align:right\" readonly=\"readonly\" size=10 value=\"$tmp\"></td>";
			if ($vis_projekt && !$projekt[0]) {
				print "<td><select class=\"inputbox\" NAME=projekt[$x]>";
				for ($a=0; $a<=$prj_antal; $a++) {
					if ($projekt[$x]!=$list[$a]) print "<option  value=\"$list[$a]\" title=\"$beskriv[$a]\">$list[$a]</option>";
					else print "<option value=\"$list[$a]\" title=\"$beskriv[$a]\" selected=\"selected\">$list[$a]</option>";
				}
				print "</option></td>";
		}
			if ($status>=1) {
				if ($vare_id[$x]) {
					$row = db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
					if (!$row[gruppe]) {
						print "<BODY onLoad=\"javascript:alert('Vare med varenummer $varenr[$x] er ikke tilknyttet en varegruppe (Pos nr. $posnr[$x])')\">";
						exit;
					} else {
						$row = db_fetch_array(db_select("select box9 from grupper where kodenr = '$row[gruppe]' and art = 'VG'",__FILE__ . " linje " . __LINE__));
						$box9[$x] = trim($row['box9']);
						$tidl_lev[$x]=0;
					}
					if ($art=='KK') {
						$dklev[$x]=dkdecimal($leveres[$x]*-1);
						$modtag_returner="returner";
					} else {
						$dklev[$x]=dkdecimal($leveres[$x]);
						$modtag_returner="modtag";
					}
					if (substr($dklev[$x],-1)=='0'){$dklev[$x]=substr($dklev[$x],0,-1);}
					if (substr($dklev[$x],-1)=='0'){$dklev[$x]=substr($dklev[$x],0,-2);}

					if (($antal[$x]>=0)&&($art!='KK')) {
						$query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
						while($row = db_fetch_array($query)) $tidl_lev[$x]=$tidl_lev[$x]+$row['antal'];
						if (afrund($antal[$x]-$tidl_lev[$x],2)) $status=1;
						$temp=0;
						$query = db_select("select * from reservation where linje_id = $linje_id[$x] and batch_salg_id=0",__FILE__ . " linje " . __LINE__);
						if ($row = db_fetch_array($query)){
						 if ($antal[$x]-$tidl_lev[$x]!=$row[antal]) {db_modify("update reservation set antal=$antal[$x]-$tidl_lev[$x] where linje_id=$linje_id[$x] and batch_salg_id=0",__FILE__ . " linje " . __LINE__);}
						}
						elseif ($antal[$x]-$tidl_lev[$x]!=$row['antal']) {
							if (($antal[$x]>=0)&&($tidl_lev[$x]<0)) {
								print "<BODY onLoad=\"javascript:alert('Antal m&aring; ikke &aelig;ndres til positivt tal, n&aring;r der er returneret varer (Pos nr. $posnr[$x])')\">";
								$antal[$x]=$tidl_lev[$x];
							}
							else db_modify("insert into reservation (linje_id, vare_id, batch_salg_id, antal) values	($linje_id[$x], $vare_id[$x], 0, $antal[$x]-$tidl_lev[$x])",__FILE__ . " linje " . __LINE__);
						}
					}

					if ($antal[$x]<0){
						$tidl_lev[$x]=0;
						$query = db_select("select antal from batch_kob where linje_id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
						while ($row = db_fetch_array($query)) {
							if ($art=='KK') {$tidl_lev[$x]=$tidl_lev[$x]-$row['antal'];}
							else {$tidl_lev[$x]=$tidl_lev[$x]+$row['antal'];}
					 	}
					}
					$dk_tidl_lev[$x] = dkdecimal($tidl_lev[$x]);
					if (substr($dk_tidl_lev[$x],-1)=='0') $dk_tidl_lev[$x]=substr($dk_tidl_lev[$x],0,-1);
					if (substr($dk_tidl_lev[$x],-1)=='0') $dk_tidl_lev[$x]=substr($dk_tidl_lev[$x],0,-2);
					if (afrund(abs($antal[$x])-abs($tidl_lev[$x]),3)!=0) {
						if (abs($antal[$x])!=abs($leveres[$x])) {
#							print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=4 name=leve$x value='$dklev[$x]' onchange=\"javascript:docChange = true;\"></td>";
							print "<td title=\"Mangler fortsat at ".$modtag_returner."e resten.\"><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #ffa; text-align:right\" size=\"4\" name=\"leve$x\" value=\"$dklev[$x]\" onchange=\"javascript:docChange = true;\"></td>\n";
						} else {
							print "<td title=\"Intet ".$modtag_returner."et endnu.\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"4\" name=\"leve$x\" value=\"$dklev[$x]\" onchange=\"javascript:docChange = true;\"></td>\n";
						}
					} else {
						print "<td title=\"Alt ".$modtag_returner."et.\"><input class=\"inputbox\" type=\"text\" readonly=\"readonly\" style=\"background: none repeat scroll 0 0 #e4e4ee; text-align:right\" size=\"4\" name=\"leve$x\" value=\"$dklev[$x]\" onchange=\"javascript:docChange = true;\"></td>\n";
					}
					print "<td>($dk_tidl_lev[$x])</td>";
				}
			}
			if (($status>0)&&($serienr[$x])){print "<td onClick=\"serienummer($linje_id[$x])\"><input type=button value=\"Serienr.\" name=\"vis_snr$x\" onchange=\"javascript:docChange = true;\"></td>";}
			if (($antal[$x]<0)&&($art!='KK')&&($box9[$x]=='on')) {print "<td align=center onClick=\"batch($linje_id[$x])\"><span title= 'V&aelig;lg fra k&oslash;bsordre'><img alt=\"K&oslash;bsordre\" src=../ikoner/serienr.png></td></td>";}

#print "<BODY onClick=\"JavaScript:window.open('batch.php?linje_id=$linje_id', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
#cho "OL2 $omlev<br>";
		if ($omlev) print "<td valign=\"top\"><input class=\"inputbox\" type=\"checkbox\" style=\"background: none repeat scroll 0 0 #e4e4ee\" name=\"omvbet[$x]\" onchange=\"javascript:docChange = true;\" $omvbet[$x]></td>\n";
		print "</tr>\n";
		}
		print "<tr>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=3 name=posn0 value=$x></td>";
		if ($art!='KK') {
			print "<td><input class=\"inputbox\" type=text size=7 name=vare0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
			print "<td><input class=\"inputbox\" type=text size=7 name=lev_v0></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=4 name=anta0></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=readonly size=3></td>";
		}
		else {
			print "<td><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=readonly size=7></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=readonly size=7></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=readonly size=2></td>";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=readonly size=3></td>";
		}
		if ($konto_id) print "<td><input class=\"inputbox\" type=text size=58 name=beskrivelse0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		else print "<td><input class=\"inputbox\" type=text size=58 name=beskrivelse0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=10 name=pris0></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=4 name=raba0></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=readonly size=10></td>";
#		if ($status==1) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=2 name=modt0></td>";}
		print "</tr>\n";
		print "<input type=hidden size=3 name=sum value=$sum>";
		$moms=$momssum/100*$momssats;
		if ($art=='KK') $moms=$moms-0.0001; #Ellers runder den op istedet for ned?
		else $moms=$moms+0.0001; #Ellers runder den ned istedet for op?
		$moms=afrund($moms,3);
		if ($id) {db_modify("update ordrer set sum='$sum', moms='$moms' where id='$id'",__FILE__ . " linje " . __LINE__);}
		if ($art=='KK') {
			$sum=$sum*-1;
			$moms=$moms*-1;
		}
		$ialt=$sum+$moms;
#		$sum=dkdecimal($sum);
#		$moms=dkdecimal($moms);
		print "<tr><td colspan=9><table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" width=100%><tbody>";
		print "<tr>";
		print "<td align=center>Ordresum</td><td align=center>".dkdecimal($sum)."</td>";
		print "<td align=center>Moms</td><td align=center>".dkdecimal($moms)."</td>";
		print "<td align=center>I alt</td><td align=right>".dkdecimal($ialt)."</td>";

		print "</tbody></table></td></tr>\n";
		print "<input type=\"hidden\" name=\"fokus\">";
		print "<tr><td align=center colspan=8>";
		print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tbody><tr>";
		print "<td align=center><input type=submit accesskey=\"g\" value=\"&nbsp;&nbsp;Gem&nbsp;&nbsp;\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";
		print "<td align=center><input type=submit accesskey=\"o\" value=\"Opslag\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";
		if (($status==1)&&($bogfor==1)) {
			if ($art=='KK') {print "<td align=center><input type=submit accesskey=\"m\" value=\"Return&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";}
			else {print "<td align=center><input type=submit accesskey=\"m\" value=\"Modtag\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";}
		}
		elseif ($status > 1 && $bogfor==1){print "<td align=center><input type=submit accesskey=\"b\" value=\"Bogf&oslash;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";}
		if (!$posnr[1] && $id) {print "<td align=center><input type=submit value=\"&nbsp;&nbsp;Slet&nbsp;&nbsp;\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";}
		elseif ($id && $art=='KO') print "<td align=center><span title=\"Udskriver ordre til PDF\"><input type=\"submit\" value=\"Udskriv\" name=\"udskriv\" onclick=\"javascript:docChange = false;\"></span></td>";
		print "<td align=center><span title=\"Klik her for at udskrive ordrelinjer til en tabulatorsepareret fil, som kan importeres i et regneark\"><input type=submit value=\"&nbsp;&nbsp;CSV&nbsp;&nbsp;\" name=\"submit\" onClick=\"javascript:ordre2csv=window.open('ordre2csv.php?id=$ordre_id','ordre2csv','scrollbars=1,resizable=1')\"></span></td>";
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
					if ($r3=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$r[transdate]' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
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
	}
	print "</tbody></table></td></tr>\n";
	print "</form>";
	print "</tbody></table></td></tr></tbody></table></td></tr>\n";
	print "<tr><td></td></tr>\n";
}# end function ordreside
######################################################################################################################################
function kontoopslag($sort, $fokus, $id, $find){

	global $bgcolor;
	global $bgcolor5;
	global $charset;

	if ($find) $find=str_replace("*","%",$find);

	sidehoved($id, "../kreditor/ordre.php", "../kreditor/kreditorkort.php", $fokus, "Leverand&oslash;rordre $id");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
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

	if ($find) $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'K' and $fokus like '$find' order by $sort",__FILE__ . " linje " . __LINE__);
	else $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'K' order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$kontonr=str_replace(" ","",$row['kontonr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href=ordre.php?fokus=$fokus&id=$id&konto_id=$row[id]>$row[kontonr]</a></td>";
		print "<td>".htmlentities(stripslashes($row[firmanavn]),ENT_COMPAT,$charset)."</td>";
		print "<td>".htmlentities(stripslashes( $row[addr1]),ENT_COMPAT,$charset)."</td>";
		print "<td>".htmlentities(stripslashes( $row[addr2]),ENT_COMPAT,$charset)."</td>";
		print "<td> $row[postnr]</td>";
		print "<td>".htmlentities(stripslashes( $row[bynavn]),ENT_COMPAT,$charset)."</td>";
		print "<td> ".htmlentities(stripslashes($row[land]),ENT_COMPAT,$charset)."</td>";
		print "<td>".htmlentities(stripslashes( $row[kontakt]),ENT_COMPAT,$charset)."</td>";
		print "<td> $row[tlf]</td>";
		print "</tr>\n";
	}

print "</tbody></table></td></tr></tbody></table>";
exit;
}
######################################################################################################################################
function ansatopslag($sort, $fokus, $id){

	global $bgcolor;
	global $bgcolor5;
	global $charset;

	sidehoved($id, "../kreditor/ordre.php", "../kreditor/kreditorkort.php", $fokus, "Leverand&oslash;rordre $id");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><b><a href=ordre.php?sort=navn&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></td>";
	print"<td><b><a href=ordre.php?sort=tlf&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Lokal</b></td>";
	print"<td><b><a href=ordre.php?sort=mobil&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Mobil</b></td>";
	print"<td><b><a href=ordre.php?sort=email&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>E-mail</b></td>";
	print" </tr>\n";


	$sort = $_GET['sort'];
	if (!$sort) $sort = "navn";
	if (!$id) $id = '0'; # <- 2009.05.10

	$query = db_select("select konto_id from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$konto_id = $row['konto_id']*1; # <- 2009.05.10

	$query = db_select("select * from ansatte where konto_id = $konto_id order by $sort",__FILE__ . " linje " . __LINE__); # <- 2009.05.10
	while ($row = db_fetch_array($query))	{
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href='ordre.php?fokus=$fokus&id=$id&kontakt=$row[navn]'>".htmlentities(stripslashes($row[navn]),ENT_COMPAT,$charset)."</a></td>";
		print "<td> $row[tlf]</td>";
		print "<td> $row[mobil]</td>";
		print "<td> $row[email]</td>";
		print "</tr>\n";
	}

print "</tbody></table></td></tr></tbody></table>";
exit;
}
######################################################################################################
function vareopslag($sort, $fokus, $id, $vis, $ref, $find) {
	global $konto_id;
	global $kontonr;
	global $bgcolor;
	global $bgcolor5;
	global $charset;

	if ($find) $find=str_replace("*","%",$find);

	if (!$konto_id) {
		if ((!$kontonr)&&($id))	{
			$query = db_select("select kontonr from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) $kontonr=trim($row[kontonr]);
		}
		if ($kontonr) {
			$query = db_select("select id from adresser where kontonr = '$kontonr' and art = 'K'",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) $konto_id=$row[id];
		}
	}

	sidehoved($id, "../kreditor/ordre.php", "../lager/varekort.php", "$fokus&leverandor=$konto_id", "Leverand&oslash;rordre $id");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	$listeantal=0;
	if ($id) {
		$q=db_select("select id,beskrivelse from grupper where art='PL' and box4='on' and box1='$konto_id' order by beskrivelse",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$listeantal++;
			$prisliste[$listeantal]=$r['id'];
			$listenavn[$listeantal]=$r['beskrivelse'];
		}
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\"><tbody><tr>";
		if ($listeantal) {
			print "<form name=\"prisliste\" action=\"../includes/prislister.php?start=0&ordre_id=$id&fokus=$fokus\" method=\"post\">";
			print "<td><select name=prisliste>";
			for($x=1;$x<=$listeantal;$x++) print "<option value=\"$prisliste[$x]\">$listenavn[$x]</option>";
			print "</select><input type=\"submit\" name=\"prislist\" value=\"Vis\"></td>";
		}
	}

	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><b><a href=ordre.php?sort=varenr&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis=$vis>Varenr</a></b></td>";
	print"<td><b> Enhed</b></td>";
	print"<td><b><a href=ordre.php?sort=beskrivelse&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis=$vis>Beskrivelse</a></b></td>";
	print"<td align=right><b><a href=ordre.php?sort=salgspris&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis=$vis>Salgspris</a></b></td>";
	print"<td align=right><b> Kostpris</b></td>";
	print"<td align=right><b> Beholdning&nbsp;</b></td>";
#	print"<td width=2%></td>";
	print"<td align><b> Leverand&oslash;r</b></td>";
	if ($kontonr)	{
		if ($vis) {print"<td align=right><a href=ordre.php?sort=$sort&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id><span title='Klik her for at vise alle varer fra alle leverand&oslash;rer'>Alle&nbsp;lev.</span></a></td>";}
		else {print"<td align=right><a href=ordre.php?sort=$sort&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis=1><span title='Klik her for kun at vise alle varer fra denne leverand&oslash;exit
			r'>Denne&nbsp;lev.</span></a></td>";}
	}
		print" </tr>\n";

	$sort = $_GET['sort'];
	if (!$sort) {$sort = varenr;}


	$vare_id=array();
	if (($vis)&&($konto_id)) {
		$temp=" and lev_id = ".$konto_id;
	}

	$y=0;
	$skjul_vare_id=array();
	$vis_vare_id=array();
	$query = db_select("select * from vare_lev",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$y++;
		if (!$konto_id || !$vis || $row['lev_id']==$konto_id || $row['lev_id']=='0') {
			$vis_vare_id[$y]=$row['vare_id'];
		}	else $skjul_vare_id[$y]=$row['vare_id'];
	}

	if ($ref){
		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'",__FILE__ . " linje " . __LINE__))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager=$row['kodenr'];}
		}
	}
	$lager=$lager*1;
	if (!$sort) {$sort = varenr;}

	if (!$kontonr){$x++;}
	elseif ($x>1) {print "<td colspan=9><hr></td>";}
	if ($find) {
		$query = db_select("select * from varer where lukket != '1' and $fokus like '$find' order by $sort",__FILE__ . " linje " . __LINE__);
	}
	else {
		$query = db_select("select * from varer where lukket != '1' order by $sort",__FILE__ . " linje " . __LINE__);
	}
	while ($row = db_fetch_array($query)) {
		$vare_id=$row['id'];
		if (($konto_id && !in_array($vare_id,$skjul_vare_id)) || in_array($vare_id,$vis_vare_id)) {
			$varenr=db_escape_string(trim($row['varenr']));
			$vist=0;
			$x=0;

			$query2 = db_select("select * from vare_lev where vare_id = $row[id] $temp",__FILE__ . " linje " . __LINE__);
			while ($row2 = db_fetch_array($query2)) {
				$x++;
				$y++;
				if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
				else {$linjebg=$bgcolor5; $color='#000000';}
				print "<tr bgcolor=\"$linjebg\">";
				print "<td><a href=\"ordre.php?vare_id=$vare_id&fokus=$fokus&konto_id=$row2[lev_id]&id=$id\">".htmlentities(stripslashes($varenr),ENT_COMPAT,$charset)."</a></td>";
				print "<td>$row[enhed]<br></td>";
				print "<td> $row[beskrivelse]<br></td>";
				$salgspris=dkdecimal($row[salgspris]);
				print "<td align=right> $salgspris<br></td>";
				$kostpris=dkdecimal($row2[kostpris]);
				print "<td align=right> $kostpris<br></td>";
				if ($lager>=1){
					$q2 = db_select("select * from batch_kob where vare_id=$vare_id and rest>0 and lager=$lager",__FILE__ . " linje " . __LINE__);
					while ($r2 = db_fetch_array($q2)) {
						$q3 = db_select("select * from reservation where batch_kob_id=$r2[id]",__FILE__ . " linje " . __LINE__);
						while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3[antal];}
					}
					$linjetext="<span title= 'Reserveret: $reserveret'>";
					if ($r2= db_fetch_array(db_select("select beholdning from lagerstatus where vare_id=$row[id] and lager=$lager",__FILE__ . " linje " . __LINE__))) {
						print "<td align=right>$linjetext $r2[beholdning] &nbsp;</span></td>";
					} else print "<td align=right>$linjetext 0 &nbsp;</span></td>";
				}
				else {print "<td align=right> $row[beholdning] &nbsp;</td>"; }
#			print "<td></td>";

				$levquery = db_select("select kontonr, firmanavn from adresser where id=$row2[lev_id]",__FILE__ . " linje " . __LINE__);
				if ($levrow = db_fetch_array($levquery)){print "<td> ".htmlentities(stripslashes($levrow[firmanavn]),ENT_COMPAT,$charset)."</td>";}
				else {print "<td></td>";}
				print "<td align=right><a href=\"../lager/varekort.php?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus&id=$row[id]\">Ret</a></td>";
				print "</tr>\n";
				$vist=1;
		}
#			if ($konto_id && $y==1) print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?vare_id=$vare_id&fokus=$fokus&konto_id=$row2[lev_id]&id=$id\">";
		}

		if ($kontonr && !$vist && $row['samlevare']!='on' && !in_array($vare_id,$skjul_vare_id)) {

#		if ((!in_array($row[id], $vare_id))&&($vist==0)&&($row['samlevare']!='on')&&($konto_id)) {
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			else {$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\">";
			print "<td><a href=\"ordre.php?vare_id=$vare_id&fokus=$fokus&id=$id\">$row[varenr]</a></td>";
			print "<td>$row[enhed]<br></td>";
			print "<td> ".htmlentities(stripslashes($row[beskrivelse]),ENT_COMPAT,$charset)."<br></td>";
			$salgspris=dkdecimal($row[salgspris]);
			print "<td align=right> $salgspris<br></td>";
			$kostpris=dkdecimal($row[kostpris]);
			print "<td align=right> $kostpris<br></td>";
			print "<td></td><td></td>";
			print "<td align=right><a href=\"../lager/varekort.php?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus&id=$row[id]\">Ret</a></td>";
			print "</tr>\n";
		}
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function sidehoved($id, $returside, $kort, $fokus, $tekst) {
	global $color;
	global $bgcolor2;
	global $sprog_id;
	global $top_bund;

	$alerttekst=findtekst(154,$sprog_id);
	print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Leverand&oslash;rordre</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";
	print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
	print "<div align=\"center\">";

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
#	if ($returside != "ordre.php") {print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$returside?tabel=ordrer&id=$id','$alerttekst')\" accesskey=L>Luk</a></td>";}
#	else {print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('ordre.php?id=$id','$alerttekst')\" accesskey=L>Luk</a></td>";}
	if ($kort) print "<td width=\"10%\" $top_bund> $color<a href=../kreditor/ordre.php?id=$id&fokus=$fokus accesskey=L>Luk</a></td>";
	else print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('../includes/luk.php?returside=$returside&tabel=ordrer&id=$id','$alerttekst')\" accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund> $color$tekst</td>";
	if (($kort!="../lager/varekort.php" && $returside != "ordre.php")&&($id)) {print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('ordre.php?returside=ordreliste.php','$alerttekst')\" accesskey=N>Ny</a></td>";}
	else if (($kort=="../lager/varekort.php" && $returside == "ordre.php")&&($id)) {print "<td width=\"10%\" $top_bund> $color<a href=\"$kort?returside=$returside&ordre_id=$id\" accesskey=N>Ny</a></td>";}
	elseif ($kort=="../kreditor/kreditorkort.php") {
		print "<td width=\"5%\"$top_bund onClick=\"javascript:kreditor_vis=window.open('kreditorvisning.php','kreditor_vis','scrollbars=1,resizable=1');kreditor_vis.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"> <span title='V&aelig;lg hvilke kreditorgrupper som vises i varelisten'><u>Visning</u></span></td>";
		print "<td width=\"5%\" $top_bund> $color<a href=\"javascript:confirmClose('$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus','$alerttekst')\" accesskey=N>Ny</a></td>";
	}
	elseif (($id)||($kort!="../lager/varekort.php")) {print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus','$alerttekst')\" accesskey=N>Ny</a></td>";}
	else {print "<td width=\"10%\" $top_bund><br></td>";}
	print "</tbody></table>";
	print "</td></tr>\n";
	print "<tr><td valign=\"top\" align=center>";
}
######################################################################################################################################
function indset_konto($id, $konto_id) {
	global $art;
	global $brugernavn;
	$tidspkt=date("U");

	$id=$id*1;
	$konto_id=$konto_id*1;
	$query = db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query))
	{
		$kontonr=trim($row['kontonr']);
		$firmanavn=db_escape_string(trim($row['firmanavn']));
		$addr1=db_escape_string(trim($row['addr1']));
		$addr2=db_escape_string(trim($row['addr2']));
		$postnr=trim($row['postnr']);
		$bynavn=db_escape_string(trim($row['bynavn']));
		$land=db_escape_string(trim($row['land']));
		$betalingsdage=$row['betalingsdage'];
		$betalingsbet=trim($row['betalingsbet']);
		$cvrnr=trim($row['cvrnr']);
		$notes=db_escape_string(trim($row['notes']));
		$gruppe=trim($row['gruppe']);
	}
	if ($gruppe) {
		$query = db_select("select box1, box3,box9 from grupper where art='KG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
			$valuta=trim($row['box3']);
			$omlev=trim($row['box9']);
		if (substr($row['box1'],0,1)=='K') {
			$tmp= substr($row['box1'],1,1)*1;
			$query = db_select("select box2 from grupper where art='KM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$momssats=trim($row['box2'])*1;
		} elseif (substr($row['box1'],0,1)=='E') {
			$momssats='0.00';
		} elseif (substr($row['box1'],0,1)=='Y') { 
			$momssats='0.00';
		}
	} else print "<BODY onLoad=\"javascript:alert('Kreditor ikke tilknyttet en kreditorgruppe')\">";
	$momssats=$momssats*1;
	if ((!$id)&&($firmanavn)) {
		$ordredate=date("Y-m-d");
		$query = db_select("select ordrenr from ordrer where art='KO' or art='KK' order by ordrenr desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {$ordrenr=$row['ordrenr']+1;}
		else {$ordrenr=1;}

		db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, betalingsdage, betalingsbet, cvrnr, notes, art, ordredate, momssats, status, hvem, tidspkt, valuta,omvbet) values ($ordrenr, '$konto_id', '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$betalingsdage', '$betalingsbet', '$cvrnr', '$notes', 'KO', '$ordredate', '$momssats', '0', '$brugernavn', '$tidspkt', '$valuta','$omlev')",__FILE__ . " linje " . __LINE__);
		$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {$id=$row[id];}
	}
	elseif($firmanavn) {
		$query = db_select("select tidspkt from ordrer where id=$id and hvem='$brugernavn'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			db_modify("update ordrer set konto_id=$konto_id, kontonr='$kontonr', firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr' momssats='$momssats', notes='$notes', hvem = '$brugernavn', tidspkt='$tidspkt', valuta='$valuta' where id=$id",__FILE__ . " linje " . __LINE__);
		}
		else {
			$query = db_select("select hvem from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"fejltekst('Ordren er overtaget af $row[hvem]')\">";}
			else {print "<BODY onLoad=\"fejltekst('Du er blevet smidt af')\">";}
		}
	}
	return $id;
}
######################################################################################################################################
function find_vare_id ($varenr) {
	if ($r=db_fetch_array(db_select("select id from varer where varenr = '$varenr' or stregkode = '$varenr'",__FILE__ . " linje " . __LINE__))) {
		return ($row['id']);
	}	else return (0);
}
######################################################################################################################################
function samlevare($id,$art,$v_id,$leveres) {
	if ($art=='KO') {
		include ("../includes/fuld_stykliste.php");
		list($vare_id,$stk_antal,$antal) = fuld_stykliste($v_id, '', 'basisvarer');
		for ($x=1; $x<=$antal; $x++) {
			if ($r=db_fetch_array(db_select("select * from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__))) {
				$stk_antal[$x]=$stk_antal[$x]*$leveres;
				db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, posnr) values ('$id', '$r[varenr]', '$vare_id[$x]', '$r[beskrivelse]', '$stk_antal[$x]', '$stk_antal[$x]', '$r[kostpris]','100' )",__FILE__ . " linje " . __LINE__);
			}
		}
	} 
/*
else {
#cho "select antal,posnr from ordrelinjer where id='$linje_id'<br>";
		$r=db_fetch_array(db_select("select antal,posnr,kred_linje_id from ordrelinjer where id='$linje_id'",__FILE__ . " linje " . __LINE__));
		$antal=$r['antal']*1;
		$posnr=$r['posnr']*1;
		$kred_linje_id=$r['kred_linje_id']*1;
#cho "$antal select id,antal from ordrelinjer where id='$kred_linje_id'<br>";
		if ($antal && $r=db_fetch_array(db_select("select id,antal from ordrelinjer where id='$kred_linje_id'",__FILE__ . " linje " . __LINE__))) {
			$org_antal=$r['antal'];
#cho "select * from ordrelinjer where samlevare='$r[id]'<br>";
			$q=db_select("select * from ordrelinjer where samlevare='$r[id]'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				$ny_antal=afrund($r['antal']*$org_antal/$antal,2);
#cho "insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, posnr) 
					values 
				('$id', '$r[varenr]', '$r[vare_id]', '$r[beskrivelse]', '$ny_antal', '$ny_antal', '$r[pris]', '$r[posnr]' )<br>";
				db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, posnr) 
					values 
				('$id', '$r[varenr]', '$r[vare_id]', '$r[beskrivelse]', '$ny_antal', '$ny_antal', '$r[pris]', '$r[posnr]' )",__FILE__ . " linje " . __LINE__);
			}
		}
	}
*/
#exit;
}
##############################################################################
function indsaet_linjer($ordre_id, $linje_id, $posnr) {
	$posnr = str_replace('+',':',$posnr); #jeg ved ikke hvorfor, men den vil ikke splitte med "+"
	list ($posnr, $antal) = explode (':', $posnr);
	db_modify("update ordrelinjer set posnr='$posnr' where id='$linje_id'",__FILE__ . " linje " . __LINE__);
	for ($x=1; $x<=$antal; $x++) {
		db_modify("insert into ordrelinjer (posnr, ordre_id) values ('$posnr', '$ordre_id')",__FILE__ . " linje " . __LINE__);
	}
}
if ($fokus) {
	print "<script language=\"javascript\">";
	print "document.ordre.$fokus.focus();";
	print "</script>";
}
?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
