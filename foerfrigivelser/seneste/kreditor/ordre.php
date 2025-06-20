<?php
// -----kreditor/ordre.php----------Lap 1.9.3b------------08.04.2008----
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
$modulnr=7;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/dkdato.php");
include("../includes/usdate.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
$tidspkt=date("U");

if ($tjek=$_GET['tjek'])	{
	$query = db_select("select tidspkt, hvem from ordrer where status < 3 and id = $tjek and hvem != '$brugernavn'");
	if ($row = db_fetch_array($query))	{
		if ($tidspkt-($row['tidspkt'])<3600) {
			print "<BODY onLoad=\"javascript:alert('Ordren er i brug af $row[hvem]')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		}
		else {db_modify("update ordrer set hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$tjek'");}
	}
	else {db_modify("update ordrer set hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$tjek'");}
}

$id=$_GET['id'];
$vis=$_GET['vis'];
$sort=$_GET['sort'];
$fokus=$_GET['fokus'];
$submit=$_GET['funktion'];
if (!$id && $konto_id=$_GET['konto_id']) {$id = indset_konto($id, $konto_id);}
if (($kontakt=$_GET['kontakt'])&&($id)) {db_modify("update ordrer set kontakt='$kontakt' where id=$id");}

if ($_GET['varenr']) {
	$varenr=$_GET['varenr'];
	$linjenr=substr($fokus,4);
	if ($id) {
		$query = db_select("select konto_id, kontonr, status from ordrer where id = $id");
		$row = db_fetch_array($query);
		if ($row[status]>2) {
			print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
			exit;
		}
		$konto_id=$row[konto_id];
		$query = db_select("select posnr from ordrelinjer where ordre_id = '$id' order by posnr desc");
		if ($row = db_fetch_array($query)) {$posnr=$row[posnr]+1;}
		else {$posnr=1;}
	}
	else {$posnr=1;}
	
	$query = db_select("select * from varer where varenr = '$varenr'");
	if ($row = db_fetch_array($query)) {
		$vare_id=$row[id];
		$serienr=trim($row['serienr']);
		if (!$beskrivelse){$beskrivelse=addslashes($row[beskrivelse]);}
			if (!$enhed){$enhed=$row[enhed];}
			if (!$pris){$pris=$row[kostpris];}
			if (!$rabat){$rabat=$row[rabat];}
		}
		if (((!$pris)||(!$lev_varenr))&&($vare_id)&&($konto_id))	{
			$query = db_select("select * from vare_lev where vare_id = '$vare_id' and lev_id = '$konto_id'");
			if ($row = db_fetch_array($query)) {
				$pris=$row['kostpris'];
				$lev_varenr=$row['lev_varenr'];
			}
		}
		if (!$id) {$id = indset_konto($id, $konto_id);}
		$pris=$pris*1;
		if(!$antal) $antal=1;
		if (!$rabat) $rabat=0;
		
		$r=db_fetch_array(db_select("select valuta, ordredate from ordrer where id='$id'"));
		$valuta=$r['valuta'];
		$ordredate=$r['ordredate'];
		if ($valuta && $valuta!='DKK') {
			if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr and valuta.valdate <= '$ordredate' order by valuta.valdate desc"))) {
				$pris=$pris*100/$r['kurs'];
			} else {
				$tmp = dkdato($ordredate);
				print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
			}
		}
				
		if ($linjenr==0)
		if ($serienr) $antal=round($antal);
		if ($vare_id) {
			if ($r1 = db_fetch_array(db_select("select gruppe from varer where id = '$vare_id'"))) {
				if ($r2 = db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'"))) {$momsfri = $r2['box7'];}
			}	
			db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, pris, lev_varenr, serienr, antal, momsfri) values ('$id', '$posnr', '$varenr', '$vare_id', '$beskrivelse', '$enhed', '$pris', '$lev_varenr', '$serienr', '$antal', '$momsfri')");}
		}


	if ($_POST) {
		$fokus=$_POST['fokus'];
		$submit = $_POST['submit'];
		$id = $_POST['id'];
		$ordrenr = $_POST['ordrenr'];
		$kred_ord_id = $_POST['kred_ord_id'];
		$art = $_POST['art'];
		$konto_id = trim($_POST['konto_id']);
		$kontonr = trim($_POST['kontonr']);
		$firmanavn = addslashes(trim($_POST['firmanavn']));
		$addr1 = addslashes(trim($_POST['addr1']));
		$addr2 = addslashes(trim($_POST['addr2']));
		$postnr = trim($_POST['postnr']);
		$bynavn = addslashes(trim($_POST['bynavn']));
		$land = addslashes(trim($_POST['land']));
		$kontakt = addslashes(trim($_POST['kontakt']));
		$lev_navn = addslashes(trim($_POST['lev_navn']));
		$lev_addr1 = addslashes(trim($_POST['lev_addr1']));
		$lev_addr2 = addslashes(trim($_POST['lev_addr2']));
		$lev_postnr = $_POST['lev_postnr'];
		$lev_bynavn = addslashes(trim($_POST['lev_bynavn']));
		$lev_kontakt = addslashes(trim($_POST['lev_kontakt']));
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
		$fakturanr = addslashes(trim($_POST['fakturanr']));
		$momssats = trim($_POST['momssats']);
		$lev_varenr = $_POST['lev_varenr'];
		$serienr=$_POST['serienr'];

		 transaktion("begin");

		 if (strstr($submit,'Slet'))	{
				db_modify("delete from ordrelinjer where ordre_id=$id");
			db_modify("delete from ordrer where id=$id");
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php\">";
		}

		for ($x=0; $x<=$linjeantal;$x++) {
			$y="posn".$x;
			$posnr_ny[$x]=trim($_POST[$y]);
			$y="vare".$x;
			$varenr[$x]=trim($_POST[$y]);
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
			$beskrivelse[$x]=addslashes(trim($_POST[$y]));
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
			$query = db_select("select status from ordrer where id = $id");
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
			if ($kred_ord_id) {
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
			$query = db_select("select * from adresser where kontonr = '$kontonr'");
			if ($row = db_fetch_array($query)) {
				$konto_id=$row[id];
				$firmanavn=addslashes($row['firmanavn']);
				$addr1=addslashes($row['addr1']);
				$addr2=addslashes($row['addr2']);
				$postnr=$row['postnr'];
				$bynavn=addslashes($row['bynavn']);
				$land=addslashes($row['land']);
			 	$kontakt=addslashes($row['kontakt']);
				$betalingsdage=$row['betalingsdage'];
				$betalingsbet=$row['betalingsbet'];
				$cvrnr=$row['cvrnr'];
				$notes=addslashes($row['notes']);
				$gruppe=$row['gruppe'];
			}
			if ($gruppe) {
				$query = db_select("select box1, box3 from grupper where art='KG' and kodenr='$gruppe'");
				$row = db_fetch_array($query);
				if (substr($row[box1],0,1)=='K') {
	 				$tmp= substr($row[box1],1,1);
					$valuta=$r['box3'];
					$query = db_select("select box2 from grupper where art='KM' and kodenr = '$tmp'");
					$row = db_fetch_array($query);
					$momssats=trim($row[box2]);
				}
				elseif (substr($row[box1],0,1)=='E') $momssats='0.00';
				if (!$momssats) {
					print "<BODY onLoad=\"javascript:alert('Kreditorgrupper forkert opsat')\">";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
					exit;
				}
			}
		 }
		if ((!$id)&&($konto_id)&&($firmanavn)) {
			if ($row = db_fetch_array(db_select("select ordrenr from ordrer where art='KO' or art='KK' order by ordrenr desc"))) {$ordrenr=$row[ordrenr]+1;}
			else {$ordrenr=1;}
#			if ($row= db_fetch_array(db_select("select ansat_id from brugere where brugernavn='$brugernavn'"))) {
#				if ($row= db_fetch_array(db_select("select afd from ansatte where id='$row[ansat_id]'"))) {
#					if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'"))) {$lager_id=$row['kodenr'];}
#				}
#			}
#			if (!$lager_id) {$lager_id='0';}
			db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, lev_navn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt, betalingsdage, betalingsbet, cvrnr, notes, art, ordredate, momssats, status, ref, sum, lev_adr, hvem, tidspkt, valuta) values ($ordrenr, $konto_id, '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$lev_navn', '$lev_addr1', '$lev_addr2', '$lev_postnr', '$lev_bynavn', '$lev_kontakt', '$betalingsdage', '$betalingsbet', '$cvrnr', '$notes', '$art', '$ordredate', '$momssats', $status, '$ref', '$sum', '$lev_adr', '$brugernavn', '$tidspkt', '$valuta')");
			$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc");
			if ($row = db_fetch_array($query)) {$id=$row[id];}
		}
		elseif(($konto_id)&&($firmanavn)) {
			$sum=0;
			for($x=1; $x<=$linjeantal; $x++) {
				if (!$varenr[$x]) {$antal[$x]=0; $pris[$x]=0; $rabat[$x]=0;}
				elseif (($antal[$x]<0)&&($art!='KK')) {
					$query = db_select("select gruppe, beholdning from varer where varenr = '$varenr[$x]'");
					$row = db_fetch_array($query);
					if (!$row[beholdning]){$row[beholdning]=0;}
					if ($row[beholdning]-$antal[$x]<0) {
						$tmp=abs($antal[$x]);
						print "<BODY onLoad=\"javascript:alert('Du kan ikke returnere $antal[$x] n&aring;r lagerbeholdningen er $row[beholdning]! (Varenr: $varenr[$x])')\">";
						$bogfor=0;
					}
				}
				elseif (($art=='KK')&&($kred_ord_id)) { ###################	 Kreditnota ####################
					if (!$vare_id[$x]){$vare_id[$x]=find_vare_id($varenr[$x]);}
					$rest=0;
					$query = db_select("select id, rest, lager from batch_kob where vare_id = '$vare_id[$x]' and ordre_id = '$kred_ord_id'");
					while ($row = db_fetch_array($query)) {
						if ($row[rest]){$rest=$rest+$row[rest];}
						$lager[$x]=$row[lager];
					}
					$tmp=$leveres[$x]*-1;
					if (($rest<$tmp)&&($lager[$x]<='0')) {
						print "<BODY onLoad=\"javascript:alert('Du kan ikke returnere $tmp n&aring;r der er $rest tilbage fra ordre nr: $kred_ord_id! (Varenr: $varenr[$x])')\">";
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
					$query = db_select("select * from batch_kob where linje_id = $linje_id[$x]");
					if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke slette varelinje $posnr_ny[$x] da der &eacute;r solgt vare(r) fra denne batch')\">";}
					else {
						db_modify("delete from reservation where linje_id='$linje_id[$x]'");
						db_modify("delete from ordrelinjer where id='$linje_id[$x]'");
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
								$query = db_select("select * from serienr where kobslinje_id = '$linje_id[$x]' order by serienr");
								while ($row = db_fetch_array($query)){$sn_antal++;}
								if (($sn_antal>0)&&($antal[$x]<$sn_antal)) {
									 print "<BODY onLoad=\"javascript:alert('Posnr: $posnr_ny[$x] - $varenr[$x] Antal kan ikke v&aelig;re mindre end antal registrerede serienr!')\">";
									$antal[$x]=$sn_antal;
								}
								$query = db_select("select * from serienr where salgslinje_id = '$linje_id[$x]' order by serienr");
								while ($row = db_fetch_array($query)){$sn_antal--;}
								if (($sn_antal<0)&&($antal[$x]>$sn_antal)&&($art!='KK'))	{
									 print "<BODY onLoad=\"javascript:alert('Posnr: $posnr_ny[$x] - $varenr[$x] Antal kan ikke v&aelig;re st&oslash;rre end antal serienr!')\">";
									$antal[$x]=$sn_antal;
								}
								$query = db_select("select * from serienr where salgslinje_id = '$linje_id[$x]' order by serienr");
								while ($row = db_fetch_array($query)){$sn_antal++;}
								if (($sn_antal>0)&&($antal[$x]<$sn_antal)&&($art=='KK'))	{
									 print "<BODY onLoad=\"javascript:alert('Posnr: $posnr_ny[$x] - $varenr[$x] Antal kan ikke v&aelig;re mindre end antal serienr!')\">";
									 $antal[$x]=$sn_antal;
								}
							}
							$status=2;
								$reserveret[$x]=0;
								$query = db_select("select * from reservation where linje_id = $linje_id[$x] and batch_salg_id!=0");
								while ($row = db_fetch_array($query)){$reserveret[$x]=$reserveret[$x]+$row[antal];}
								if (($antal[$x]>=0)&&($antal[$x]<$reserveret[$x])) {
									print "<BODY onLoad=\"javascript:alert('Der er $reserveret[$x] resevationer p&aring; varenr $varenr[$x]: antal &aelig;ndret fra $antal[$x] til $reserveret[$x]!')\">";
									$antal[$x]=$reserveret[$x]; $submit="Gem"; $status=1;
								}
								$query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]'");
							while($row = db_fetch_array($query)){
								$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];
								$solgt[$x]=$solgt[$x]-$row[rest]; 
							}
							if ($posnr_ny[$x]=="-") {
								if ($tidl_lev[$x]!=0) $posnr_ny[$x]=0;
								elseif ($solgt[$x]!=0) $posnr_ny[$x]=0;
								elseif ($reserveret[$x]!=0) {
									$posnr_ny[$x]=$posnr[$x];
									print "<BODY onLoad=\"javascript:alert('Varenr: $varenr[$x] Der er reserveret varer fra denne varelinje - linjen kan ikke slettes!')\">";
								}	
								else {
									db_modify("delete from reservation where linje_id='$linje_id[$x]'");
									db_modify("delete from ordrelinjer where id='$linje_id[$x]'");
									$posnr_ny[$x]=1;
								}
								if (!$posnr_ny[$x]) {
									$r=db_fetch_array(db_select("select posnr from ordrelinjer where id = '$linje_id[$x]'"));
									$posnr_ny[$x]=$r['posnr'];
								}
							} elseif ($antal[$x]>0) {
								if ($antal[$x]<$solgt[$x]) {
									print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x]: antal &aelig;ndret fra $antal[$x] til $solgt[$x]!')\">";
									$antal[$x]=$solgt[$x]; $submit="Gem"; $status=1;
								}
								if ($antal[$x]<$tidl_lev[$x]) {
									print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x]: antal &aelig;ndret fra $antal[$x] til $tidl_lev[$x]!')\">";
									$antal[$x]=$tidl_lev[$x]; $submit="Gem"; $status=1;
								}
								if ($leveres[$x]>$antal[$x]-$tidl_lev[$x]) {
									$temp=$antal[$x]-$tidl_lev[$x];
									print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x]: c modtag &aelig;ndret fra $leveres[$x] til $temp!')\">";
									$leveres[$x]=$temp; $submit="Gem"; $status=1;
								}
								elseif ($leveres[$x]<0) {
									$temp=0;
									print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x]: d modtag &aelig;ndret fra $leveres[$x] til $tidl_lev[$x]!')\">";
									$leveres[$x]=$temp; $submit="Gem"; $status=1;
								}
							}
							else {
								$tidl_lev[$x]=0;
								$query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]'");
								while($row = db_fetch_array($query)){$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];}
								if ($antal[$x]>$tidl_lev[$x]) {
									print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x]: antal &aelig;ndret fra $antal[$x] til $tidl_lev[$x]!')\">";
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
							if ($antal[$x]!=$tidl_lev[$x]) {$status=1;}
						} elseif ($posnr_ny[$x]=="-") {
							db_modify("delete from ordrelinjer where id='$linje_id[$x]'");
							$posnr_ny[$x]=1;
						}
					}
					if (!$leveres[$x]){$leveres[$x]=0;}
					$sum=$sum+($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
					if ((!strpos($posnr_ny[$x], '+'))&&($id)) {
						$posnr_ny[$x]=round($posnr_ny[$x],0);
						if ($posnr_ny[$x]>=1) {db_modify("update ordrelinjer set posnr='$posnr_ny[$x]' where id='$linje_id[$x]'");}
						else print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte et - (minus) som pos nr for at slette en varelinje')\">";
					}
					if (($status<2)||(($antal[$x]>0)&&($status==2)&&($antal[$x]>=$tidl_lev[$x]))||(($antal[$x]<0)&&($status==2)&&($antal[$x]<=$tidl_lev[$x]))) {
						if ($serienr[$x]){$antal[$x]=round($antal[$x]);}
						db_modify("update ordrelinjer set beskrivelse='$beskrivelse[$x]', antal='$antal[$x]', leveres='$leveres[$x]', pris='$pris[$x]', rabat='$rabat[$x]' where id='$linje_id[$x]'");
					}
					if ((strpos($posnr_ny[$x], '+'))&&($id)) indsaet_linjer($id, $linje_id[$x], $posnr_ny[$x]);
				}
			}
			if (($posnr_ny[0]>0)&&(!strstr($submit,'Opslag'))) {
				if ($varenr[0]) {
					$tmp=strtoupper($varenr[0]);
					$query = db_select("SELECT * FROM varer WHERE upper(varenr) = '$tmp'");
					if ($row = db_fetch_array($query)) {
						$vare_id[0]=$row['id'];
						$varenr[0]=addslashes($row['varenr']);
							$serienr[0]=round($row['serienr']);
						if (!$beskrivelse[0]) $beskrivelse[0]=addslashes($row['beskrivelse']);
						if (!$enhed[0])$enhed[0]=addslashes($row['enhed']);
						if (!$rabat[0]) $rabat[0]=$row['rabat'];
						if (!$antal[0]) $antal[0]=1;
						if (!$rabat[0]) $rabat[0]=0;
						if (!$lev_varenr[0]) {
							if (!$konto_id) {
								$q = db_select("select * from adresser where kontonr = '$kontonr'");
								if ($r = db_fetch_array($q)) {$konto_id=$r['id'];}
							}
							$q = db_select("select * from vare_lev where vare_id = $vare_id[0] and lev_id = $konto_id");
							if ($r = db_fetch_array($q)) {
								if (!$pris[0]) $pris[0]=$r['kostpris'];
								$lev_varenr[0]=addslashes($r['lev_varenr']);
							}
						}
						if (!$pris[0]) $pris[0]=$row['kostpris'];
						$pris[0]=$pris[0]*1;
						if ($valuta && $valuta!='DKK') {
							if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr and valuta.valdate <= '$ordredate' order by valuta.valdate desc"))) {
								$pris[0]=$pris[0]*100/$r['kurs'];
							} else {
								$tmp = dkdato($ordredate);
								print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
							}
						}
						if ($serienr[0]){$antal[0]=round($antal[0]);}
						if ($r1 =	db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[0]'"))) {
							if ($r2 = db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'"))) {$momsfri[0] = $r2['box7'];}
						}	
						db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, serienr, lev_varenr, momsfri) values ('$id', '$posnr_ny[0]', '$varenr[0]', '$vare_id[0]', '$beskrivelse[0]', '$enhed[0]', '$antal[0]', '$pris[0]', '$rabat[0]', '$serienr[0]', '$lev_varenr[0]', '$momsfri[0]')");
					} else  {
						$submit='Opslag';
						$varenr[0]=$varenr[0]."*";
					}
				if ($status==2){$status=1;}
				}
				elseif ($beskrivelse[0]) {
					db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$id', '$posnr_ny[0]', '$beskrivelse[0]')");
					if ($status==2){$status=1;}
				}
			}
			 $query = db_select("select tidspkt from ordrer where id=$id and hvem='$brugernavn'");
			if ($row = db_fetch_array($query)) {
				if (strlen($levdate)<6){$opdat="update ordrer set firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', kontakt='$kontakt', lev_navn='$lev_navn',	lev_addr1='$lev_addr1',	lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', notes='$notes', art='$art', ordredate='$ordredate', status=$status, ref='$ref', fakturanr='$fakturanr', lev_adr='$lev_adr', hvem = '$brugernavn', tidspkt='$tidspkt', valuta='$valuta', projekt='$projekt' where id=$id";}
				else {$opdat="update ordrer set firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', kontakt='$kontakt', lev_navn='$lev_navn',	lev_addr1='$lev_addr1',	lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', notes='$notes', art='$art', ordredate='$ordredate', levdate='$levdate', status=$status, ref='$ref', fakturanr='$fakturanr', lev_adr='$lev_adr', hvem = '$brugernavn', tidspkt='$tidspkt', valuta='$valuta', projekt='$projekt' where id=$id";}
				db_modify($opdat);
			$r = db_fetch_array(db_select("select fakturanr from ordrer where id=$id"));
			}
			else {						 
				$query = db_select("select hvem from ordrer where id=$id");
				if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Ordren er overtaget af $row[hvem]')\">";}
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
			}	
		}
		if (($godkend=='on')&&($status==2)) {
			$opret_ny=0;
			for($x=1; $x<=$linjeantal; $x++) {
				if ($antal[$x]!=$tidl_lev[$x]) {$opret_ny=1;}
			}
			if ($opret_ny==1)	{
				$query = db_select("select hvem from ordrer where id=$id");
				
				db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, lev_navn,	lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt, betalingsdage, betalingsbet, cvrnr, notes, art, ordredate, momssats, status, ref, sum, lev_adr, valuta) values ($ordrenr, $konto_id, '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$lev_navn',	'$lev_addr1',	'$lev_addr2',	'$lev_postnr',	'$lev_bynavn', '$lev_kontakt', '$betalingsdage', '$betalingsbet', '$cvrnr', '$notes', '$art', '$ordredate', '$momssats', 1, '$ref', '$sum', '$lev_adr', '$valuta')");
				$query = db_select("select id from ordrer where ordrenr='$ordrenr' order by id desc");
				$row = db_fetch_array($query);
				$ny_id=$row[id];
				$ny_sum=0;
				for($x=1; $x<=$linjeantal; $x++) {
					if ($antal[$x]!=$tidl_lev[$x]) {
						$diff[$x]=$antal[$x]-$tidl_lev[$x];
						$antal[$x]=$tidl_lev[$x];
						if ($serienr[$x]){$antal[$x]=round($antal[$x]);}
						if ($r1 =	db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[$x]'"))) {
							if ($r2 = db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'"))) {$momsfri[$x] = $r2['box7'];}
						}	
						db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, serienr, lev_varenr, momsfri) values ('$ny_id', '$posnr_ny[$x]', '$varenr[$x]', '$vare_id[$x]', '$beskrivelse[$x]', '$enhed[$x]', '$diff[$x]', '$pris[$x]', '$rabat[$x]', '$serienr[$x]', '$lev_varenr[$x]', '$momsfri[$x]')");
						db_modify("update ordrelinjer set antal=$antal[$x] where id = $linje_id[$x]");
						$ny_sum=$ny_sum+$diff[$x]*($pris[$x]-$pris[$x]*$rabat[$x]/100);
					}
				}
				db_modify("update ordrer set sum=$ny_sum where id = $ny_id");
			}
		}
		if ((strstr($submit,'Kopi'))||(strstr($submit,'Kred'))) {
			if ($kred_ord_id) {db_modify("update ordrer set kred_ord_id='$kred_ord_id' where id='$id'");}
			for($x=1; $x<=$linjeantal; $x++) {
				if (!$vare_id[$x]) {
					$query = db_select("select id from varer where varenr = '$varenr[$x]'");
					if ($row = db_fetch_array($query)) {$vare_id[$x]=$row[id];}
				}
				if ((strstr($submit,'Kred'))&&($vare_id[$x])) {
					$antal[$x]=0;
					$query = db_select("select rest from batch_kob where vare_id = '$vare_id[$x]' and ordre_id = $kred_ord_id");
					while ($row = db_fetch_array($query)) {$antal[$x]=$antal[$x]-$row[rest];}
				}
				if ($serienr[$x]){$serienr[$x]="on";}
				if ($vare_id[$x]) {db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, serienr, lev_varenr, kred_linje_id) values ('$id', '$posnr_ny[$x]', '$varenr[$x]', '$vare_id[$x]', '$beskrivelse[$x]', '$enhed[$x]', $antal[$x], '$pris[$x]', '$rabat[$x]', '$serienr[$x]', '$lev_varenr[$x]', $linje_id[$x])");}
				else {db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse, enhed) values ('$id', '$posnr_ny[$x]', '$beskrivelse[$x]', '$enhed[$x]')");}
			}
		}
		$vis=1;
	transaktion("commit");
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
		if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr and valuta.valdate <= '$ordredate' order by valuta.valdate desc"))) {
			$valutakurs=$r['kurs'];
		} else {
			$valutakurs='';
		}
	} else $valutakurs=100; 
	if (!$valutakurs) {
		$tmp = dkdato($ordredate);
		print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
	} elseif(!fakturanummer) {print "<BODY onLoad=\"javascript:alert('Fakturanummer mangler')\">";}
		else	{
			db_modify("update ordrer set valutakurs = '$valutakurs' where id = '$id'");
			$query = db_select("select * from ordrelinjer where ordre_id = '$id'");
			if (!$row = db_fetch_array($query)) {Print "Du kan ikke fakturere uden ordrelinjer";}
			else {print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?id=$id\">";}
		}
	}
	if (((strstr($submit,'Modt'))||(strstr($submit,'Return')))&&($bogfor!=0)) {
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'");
		if (!$row = db_fetch_array($query)) {Print "Du kan ikke modtage uden ordrelinjer";}
		else {print "<meta http-equiv=\"refresh\" content=\"0;URL=modtag.php?id=$id\">";}
	}
	print "<meta http-equiv=\"refresh\" content=\"3600;URL=../includes/luk.php\">";
	ordreside($id);


######################################################################################################################################

function ordreside($id) {

	global $art;
	global $font;
	global $bogfor;
	global $submit;
	global $brugernavn;

	print "<form name=ordre action=ordre.php method=post>";
	if ($id)	{
		$query = db_select("select * from ordrer where id = '$id'");
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
		$projekt=$row['projekt'];
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
		$momssats=$row[momssats];
		$status=$row[status];
		if (!$status){$status=0;}
		$art=$row['art'];

		$x=0;
		$query = db_select("select id, ordrenr from ordrer where kred_ord_id = '$id' and art ='KK'");
		while ($row2 = db_fetch_array($query)) {
			$x++;
			if ($x>1) {$krediteret=$krediteret.", ";}
			$krediteret=$krediteret."<a href=ordre.php?id=$row2[id]>$row2[ordrenr]</a>";
		}
	}

	if ((strstr($submit,'Kred'))||($art=='KK')) {
		$query = db_select("select ordrenr from ordrer where id = '$kred_ord_id'");
		$row2 = db_fetch_array($query);
		sidehoved($id, "ordreliste.php", "", "", "Leverand&oslash;r kreditnota $ordrenr (kreditering af ordre nr: <a href=ordre.php?id=$kred_ord_id>$row2[ordrenr]</a>)");
	}
	elseif ($krediteret) {sidehoved($id, "ordreliste.php", "", "", "Leverand&oslash;rordre $ordrenr (krediteret p&aring; KN nr: $krediteret)");}
	else {sidehoved($id, "ordreliste.php", "", "", "Leverand&oslash;rordre $ordrenr");}

	if (!$status){$status=0;}
	print "<input type=hidden name=ordrenr value=$ordrenr>";
	print "<input type=hidden name=status value=$status>";
	print "<input type=hidden name=id value=$id>";
	print "<input type=hidden name=art value=$art>";
	print "<input type=hidden name=momssats value=$momssats>";
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
		print "<tr><td width=100>$font<small><b>Kontonr</td><td width=100>$font<small>$kontonr</td></tr>\n";
		print "<tr><td>$font<small><b>Firmanavn</td><td>$font<small>$firmanavn</td></tr>\n";
		print "<tr><td>$font<small><b>Adresse</td><td>$font<small>$addr1</td></tr>\n";
		print "<tr><td>$font<small></td><td>$font<small>$addr2</td></tr>\n";
		print "<tr><td>$font<small><b>Postnr, by</td><td>$font<small>$postnr $bynavn</td></tr>\n";
		print "<tr><td>$font<small><b>Land</td><td>$font<small>$land</td></tr>\n";
		print "<tr><td>$font<small><b>Att.:</td><td>$font<small>$kontakt</td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
		print "<tr><td width=100>$font<small><b>Ordredato</td><td width=100>$font<small>$ordredato</td></tr>\n";
		print "<tr><td>$font<small><b>Lev. dato</td><td>$font<small>$levdato</td></tr>\n";
		print "<tr><td>$font<small><b>CVR.nr</td><td>$font<small>$cvrnr</td></tr>\n";
		print "<tr><td>$font<small><b>Betaling</td><td>$font<small>$betalingsbet&nbsp;+&nbsp;$betalingsdage</td>";
		print "<tr><td>$font<small><b>Vor ref.</td><td>$font<small>$ref</td></tr>\n";
		print "<tr><td>$font<small><b>Fakturanr</td><td>$font<small>$fakturanr</td></tr>\n";
		print "<tr><td>$font<small><b>Modtagelse</td><td>$font<small>$modtagelse</td></tr>\n";
		$tmp=dkdecimal($valutakurs);
		if ($valuta) print "<tr><td>$font<small><b>Valuta / Kurs</td><td>$font<small>$valuta / $tmp</td></tr>\n";
		if ($projekt) print "<tr><td>$font<small><b>Projekt</td><td>$font<small>$projekt</td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=33%><table cellpadding=0 cellspacing=0 border = 0 width=240>";
		print "<tr><td>$font<small><b>Leveringsadresse.</td></tr>\n";
		print "<tr><td>$font<small>Firmanavn</td><td colspan=2>$font<small>$lev_navn</td></tr>\n";
		print "<tr><td>$font<small>Adresse</td><td colspan=2>$font<small>$lev_addr1</td></tr>\n";
		print "<tr><td>$font<small></td><td colspan=2>$font<small>$lev_addr2</td></tr>\n";
		print "<tr><td>$font<small>Postnr, By</td><td>$font<small>$lev_postnr $lev_bynavn</td></tr>\n";
		print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small>$lev_kontakt</td></tr>\n";
#		print "<tr><td>$font<small>$lev_adr</td></tr>\n";
		print "</td></tr></tbody></table></td>";
		print "</td></tr><tr><td align=center colspan=3><table cellpadding=1 cellspacing=0 border=1 width=100%><tbody>";
		print "<tr><td colspan=7></td></tr><tr>";
		print "<td align=center>$font<small><b>pos</td><td align=center>$font<small><b>varenr</td><td align=center>$font<small><b>ant.</td><td align=center>$font<small><b>enhed</td><td align=center>$font<small><b>beskrivelse</td><td align=center>$font<small><b>pris</td><td align=center>$font<small><b>%</td><td align=center>$font<small><b>ialt</td><td align=center>$font<small><b>solgt</td>";
		print "</tr>\n";
		$x=0;
		if (!$ordre_id){$ordre_id=0;}
		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr");
		while ($row = db_fetch_array($query)) {
			if ($row[posnr]>0) {
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
				if ($vare_id[$x]) {
					$tmp = db_fetch_array(db_select("select gruppe from varer where id = $vare_id[$x]"));
					$tmp = db_fetch_array(db_select("select box9 from grupper where kodenr='$tmp[gruppe]' and art='VG'"));
					$box9[$x]=trim($tmp[box9]);
				}
			}
		}
		$linjeantal=$x;
		print "<input type=hidden name=linjeantal value=$x>";
		$totalrest=0;
		$sum=0;
		for ($x=1; $x<=$linjeantal; $x++) {
			if (!$vare_id[$x])	 {
				$query = db_select("select id from varer where varenr = '$varenr[$x]'");
				if ($row = db_fetch_array($query)) {$vare_id[$x]=$row[id];}
			}
			if (($varenr[$x])&&($vare_id[$x]))	{
				$rest[$x]=0;
				$query = db_select("select id, rest from batch_kob where linje_id = '$linje_id[$x]' and ordre_id = '$ordre_id' and vare_id = '$vare_id[$x]'");
				while ($row = db_fetch_array($query)) {$rest[$x]=$rest[$x]+$row[rest];}
				$solgt[$x]=$antal[$x]-$rest[$x];
				$totalrest=$totalrest+$rest[$x];
			 
				$ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				$sum=$sum+$ialt;
				 if ($momsfri[$x]!='on') {$momssum=$momssum+$ialt;}
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
			else {$antal[$x]=''; $dkpris=''; $dkrabat=''; $ialt='';}
			print "<tr>";
			print "<input type=hidden name=posn$x value=$posnr[$x]><td align=right>$font<small>$posnr[$x]</td>";
			print "<input type=hidden name=vare$x value=\"$varenr[$x]\"><td align=right>$font<small>$varenr[$x]</td>";
			print "<input type=hidden name=anta$x value=$antal[$x]><td align=right>$font<small>$dkantal[$x]</td>";
			print "<td align=right>$font<small>$enhed[$x]</td>";
			print "<input type=hidden name=beskrivelse$x value=\"$beskrivelse[$x]\"><td>$font<small>$beskrivelse[$x]</td>";
			print "<input type=hidden name=pris$x value=$dkpris><td align=right>$font<small>$dkpris</td>";
			print "<input type=hidden name=raba$x value=$dkrabat><td align=right>$font<small>$dkrabat</td>";
			print "<input type=hidden name=linje_id[$x] value=$linje_id[$x]>";
			print "<input type=hidden name=serienr[$x] value=$serienr[$x]>";
			print "<input type=hidden name=vare_id[$x] value=$vare_id[$x]>";
			print "<input type=hidden name=lev_varenr[$x] value=\"$lev_varenr[$x]\">";
			if (($ialt)&&($art=='KK')) {$ialt=$ialt*-1;}
			print "<td align=right>$font<small>".dkdecimal($ialt)."</td>";
			if ($box9[$x]=='on') {
				if ($art=='KK') {$solgt[$x]=$solgt[$x]*-1;}
				if ($serienr[$x]) {print "<td onClick=\"serienummer($linje_id[$x])\" align=right>$font<small><u>$solgt[$x]</u></td>";}
				else {print "<td align=right>$font<small>$solgt[$x]</td>";}
			}
			else {print "<td align=right>$font<small><br></td>";}

			print "</tr>\n";
		}
		if ($art=='KK') {
			$sum=$sum*-1;
			$momssum=$momssum*-1;
		}
		$moms=$momssum/100*$momssats;
		$moms=$moms+0.0001; #Ellers runder den ned istedet for op?
		$moms=round($moms,2);
		$ialt=dkdecimal($sum+$moms);
		$sum=dkdecimal($sum);
		$moms=dkdecimal($moms);
		print "<tr><td colspan=8></td></tr>\n";
		print "<tr><td colspan=8><table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" width=100%><tbody>";
		print "<tr>";
		print "<td align=center>$font<small>Ordresum</td><td align=center>$font<small>$sum</td>";
		print "<td align=center>$font<small>Moms</td><td align=center>$font<small>$moms</td>";
		print "<td align=center>$font<small>I alt</td><td align=right>$font<small>$ialt</td>";
		print "</tbody></table></td></tr>\n";
		print "<tr><td align=center colspan=9>";
		print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>";
		if ($art!='KK') {
			print "<td align=center><input type=submit value=\"&nbsp;Kopier&nbsp;\" name=\"submit\"></td>";
			print "<td align=center><input type=submit value=\"Krediter\" name=\"submit\"></td>";
		}
	}
	else { // Aabne ordrer herunder **************************************************
		print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\" valign = \"top\" width = 100><tbody>";
		$ordre_id=$row[id];
		
		print "<tr><td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100>";
		print "<tr><td witdh=200>$font<small>Kontonr.</td><td colspan=2>$font<small>";
		if (trim($kontonr)) {print "<input readonly=readonly size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\"></td></tr>\n";}
		else {print "<input type=text size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\"></td></tr>\n";}
		print "<tr><td>$font<small>Firmanavn</td><td colspan=2>$font<small><input type=text size=25 name=firmanavn onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$firmanavn\"></td></tr>\n";
		print "<tr><td>$font<small>Adresse</td><td colspan=2>$font<small><input type=text size=25 name=addr1 onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$addr1\"></td></tr>\n";
		print "<tr><td>$font<small></td><td colspan=2>$font<small><input type=text size=25 name=addr2 onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$addr2\"></td></tr>\n";
		print "<tr><td>$font<small>Postnr, by</td><td>$font<small><input type=text size=4 name=postnr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$postnr\"></td><td><input type=text size=19 name=bynavn onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$bynavn\"></td></tr>\n";
		print "<tr><td>$font<small>Land</td><td colspan=2>$font<small><input type=text size=25 name=land value=\"$land\"></td></tr>\n";
		print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small><input type=text size=25 name=kontakt onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontakt\"></td></tr>\n";
		print "</tbody></table></td>";
		print "<td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100>";
		print "<tr><td>$font<small>CVR.nr</td><td colspan=2>$font<small><input type=text size=23 name=cvrnr value=\"$cvrnr\"></td></tr>\n";
		print "<tr><td>$font<small>Ordredato</td><td>$font<small><input type=text size=10 name=ordredato value=\"$ordredato\"></td>";
		print "<td>$font<small>Lev. dato</td><td>$font<small><input type=text size=10 name=levdato value=\"$levdato\"></td></tr>\n";
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
			print "</SELECT></td>";
		} else print "<tr><td witdh=200></tr>";
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
		print "<td colspan=2><SELECT NAME=betalingsbet>";
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
			print "</SELECT>&nbsp;+<input type=text size=2 style=text-align:right	name=betalingsdage value=\"$betalingsdage\"></td>";
		}
		print "</tr>";

		print "<tr><td>$font<small>Vor ref.</td>";
		if (!$ref) {
			$row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'"));
			if ($row[ansat_id]) {
				$row = db_fetch_array(db_select("select navn from ansatte where id = $row[ansat_id]"));
				if ($row[navn]) {$ref=$row['navn'];}
			}
		}		
		print "<td colspan=3><SELECT NAME=ref value=\"$ref\">";
		print "<option>$ref</option>";
		$query = db_select("select id from adresser where art = 'S'");
		if ($row = db_fetch_array($query)) {
		 $query = db_select("select navn from ansatte where konto_id = $row[id] and lukket !='on' order by navn");
		 while ($row = db_fetch_array($query)) {print "<option> $row[navn]</option>";}
		}
		print "</SELECT>";

#		print "<tr><td witdh=200>$font<small>Vor ref.</td><td colspan=2>$font<small><input type=text size=23 name=ref value=\"$ref\"></td></tr>\n";
		if ($status==0){print "<tr><td>$font<small>Godkend</td><td><input type=checkbox name=godkend></td></tr>\n";}
		elseif ($status==1) {
			$query = db_select("select * from batch_kob where ordre_id=$id");
			if(db_fetch_array($query)){print "<tr><td>$font<small>Dan lev. fakt.</td><td><input type=checkbox name=godkend></td></tr>\n";}
			else {
				$query = db_select("select * from batch_salg where ordre_id=$id");
				if(db_fetch_array($query)){print "<tr><td>$font<small>Dan lev. fakt.</td><td><input type=checkbox name=godkend></td></tr>\n";}
			}
		}
#		elseif ($status==1){print "<tr><td>$font<small>Modtag</td><td><input type=checkbox name=modtag></td></tr>\n";}
		else{print "<tr><td witdh=200>$font<small>Fakturanr</td><td colspan=2>$font<small><input type=text size=23 name=fakturanr value=\"$fakturanr\"></td></tr>\n";}
		print "</tbody></table></td>";
		print "<td align=center width=33%><table cellpadding=0 cellspacing=0 width=250>";
		print "<tr><tdcolspan=2 >$font<small>Leveringsadresse.</td></tr>\n";
		print "<tr><td colspan=2 align=center><hr></td></tr>\n";
		print "<tr><td>$font<small>Firmanavn</td><td colspan=2>$font<small><input type=text size=25 name=lev_navn value=\"$lev_navn\"></td></tr>\n";
		print "<tr><td>$font<small>Adresse</td><td colspan=2>$font<small><input type=text size=25 name=lev_addr1 value=\"$lev_addr1\"></td></tr>\n";
		print "<tr><td>$font<small></td><td colspan=2>$font<small><input type=text size=25 name=lev_addr2 value=\"$lev_addr2\"></td></tr>\n";
		print "<tr><td>$font<small>Postnr, By</td><td>$font<small><input type=text size=4 name=lev_postnr value=\"$lev_postnr\"><input type=text size=19 name=lev_bynavn value=\"$lev_bynavn\"></td></tr>\n";
		print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small><input type=text size=25 name=lev_kontakt value=\"$lev_kontakt\"></td></tr>\n";
	#		print "<tr><td><textarea style=\"font-family: helvetica,arial,sans-serif;\" name=lev_adr rows=5 cols=35>$lev_adr</textarea></td></tr>\n";
		print "</td></tr></tbody></table></td>";
		print "</td></tr><tr><td align=center colspan=3><table cellpadding=1 cellspacing=0 width=100><tbody>";
		print "<tr>";
		if ($status==1) {
			print "<td align=center>$font<small>pos</td><td align=center>$font<small>varenr</td><td align=center>$font<small>Lev. vnr</td><td align=center>$font<small>antal</td><td align=center>$font<small>beskrivelse</td><td align=center>$font<small>pris</td><td align=center>$font<small>%</td><td align=center>$font<small>ialt</td>";
			if ($art=='KK') {print "<td colspan=2 align=center>$font<small>returner</td>";}
			else {print "<td align=center>$font<small>modt.</td>";}
		}
		else {print "<td align=center>$font<small>pos</td><td align=center>$font<small>varenr</td><td align=center>$font<small>Lev. vnr</td><td align=center>$font<small>antal/enhed</td><td align=center>$font<small>beskrivelse</td><td align=center>$font<small>pris</td><td align=center>$font<small>%</td><td align=center>$font<small>ialt</td>";}
		print "</tr>\n";
/*		
		if ($valuta && $valuta!='DKK') {
			if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr and valuta.valdate <= '$ordredate' order by valuta.valdate desc"))) {
				$valutakurs=$r['kurs'];
			} else {
				$tmp = dkdato($ordredate);
				print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
			}
		} else $valutakurs = 100;
		db_modify("update ordrer set valutakurs='$valutakurs' where ordre_id = '$ordre_id'");
*/		
		if (!$ordre_id){$ordre_id=0;}
		$x=0;
		$query = db_select("select * from ordrelinjer where ordre_id = $ordre_id order by posnr");
		while ($row = db_fetch_array($query))	{
			if ($row[posnr]>0) {
				$x++;
				$linje_id[$x]=$row['id'];
				$kred_linje_id[$x]=$row[kred_linje_id];
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
				$serienr[$x]=stripslashes($row['serienr']);
		 }
		}
		$linjeantal=$x;
		print "<input type=hidden name=linjeantal value=$linjeantal>";
		$sum=0;
#		if ($status==1){$status=2;}
		for ($x=1; $x<=$linjeantal; $x++)	{
			if ($varenr[$x]) {
				$ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				$sum=$sum+$ialt;
				if ($momsfri[$x]!='on') {$momssum=$momssum+$ialt;}
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

			print "<input type=hidden name=linje_id[$x] value=$linje_id[$x]>";
			print "<input type=hidden name=kred_linje_id[$x] value=$kred_linje_id[$x]>";
			print "<input type=hidden name=serienr[$x] value='$serienr[$x]'>";
			print "<tr>";
			print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=3 name=posn$x value='$x'></td>";
			print "<td>$font<small><input readonly=readonly size=7 name=vare$x onfocus=\"document.forms[0].fokus.value=this.name;\" value='$varenr[$x]'></td>";
			print "<td>$font<small><input type=text size=7 name=lev_varenr$x value='$lev_varenr[$x]'></td>";
#			if (substr($dkantal[$x],-1,1)=="0"){$dkantal[$x]=(substr($dkantal[$x],0,strlen($antal[$x])-1));}
#			if (substr($dkantal[$x],-1,1)=="0"){$dkantal[$x]=(substr($dkantal[$x],0,strlen($antal[$x])-1));}
#			if (substr($dkantal[$x],-1,1)==","){$dkantal[$x]=(substr($dkantal[$x],0,strlen($antal[$x])-1));}
			print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=2 name=anta$x value='$dkantal[$x]'&nbsp;$enhed[$x]</td>";
			print "<td>$font<small><input type=text size=60 name=beskrivelse$x value=\"$beskrivelse[$x]\"></td>";
			print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=10 name=pris$x value='$dkpris'></td>";
			print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=4 name=raba$x value='$dkrabat'></td>";
			if ($antal[$x]) {
			 if ($art=='KK') {$ialt=$ialt*-1;}
				print "<td align=right>$font<small>".dkdecimal($ialt)."</td>";
			}	
			if ($status>=1) {
				if ($vare_id[$x]) {
					$row = db_fetch_array(db_select("select gruppe from varer where id = $vare_id[$x]"));
					$row = db_fetch_array(db_select("select box9 from grupper where kodenr = $row[gruppe] and art = 'VG'"));
					$box9[$x] = trim($row['box9']);
					$tidl_lev[$x]=0;
					if ($art=='KK') {$dklev[$x]=dkdecimal($leveres[$x]*-1);}
					else {$dklev[$x]=dkdecimal($leveres[$x]);}
					if (substr($dklev[$x],-1)=='0'){$dklev[$x]=substr($dklev[$x],0,-1);}
					if (substr($dklev[$x],-1)=='0'){$dklev[$x]=substr($dklev[$x],0,-2);}
						 print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=2 name=leve$x value='$dklev[$x]'></td>";
					if (($antal[$x]>=0)&&($art!='KK')) {
						$query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]");
						while($row = db_fetch_array($query)){$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];}
					 if ($tidl_lev[$x]<$antal[$x]){$status=1;}
						$temp=0;
						$query = db_select("select * from reservation where linje_id = $linje_id[$x] and batch_salg_id=0");
						if ($row = db_fetch_array($query)){
						 if ($antal[$x]-$tidl_lev[$x]!=$row[antal]) {db_modify("update reservation set antal=$antal[$x]-$tidl_lev[$x] where linje_id=$linje_id[$x] and batch_salg_id=0");} 
						} 
						elseif ($antal[$x]-$tidl_lev[$x]!=$row[antal]) {
							if (($antal[$x]>=0)&&($tidl_lev[$x]<0)) {
								print "<BODY onLoad=\"javascript:alert('Antal m&aring; ikke &aelig;ndres til positivt tal n� der er returneret varer (Pos nr. $posnr[$x])')\">";
								$antal[$x]=$tidl_lev[$x];
							}
							else db_modify("insert into reservation (linje_id, vare_id, batch_salg_id, antal) values	($linje_id[$x], $vare_id[$x], 0, $antal[$x]-$tidl_lev[$x])");
						}
					}
					if ($antal[$x]<0){
						$tidl_lev[$x]=0;
						$query = db_select("select antal from batch_kob where linje_id = '$linje_id[$x]'");
						while ($row = db_fetch_array($query)) {
							if ($art=='KK') {$tidl_lev[$x]=$tidl_lev[$x]-$row[antal];}
							else {$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];}
					 }
					}
					print "<td>$font<small>($tidl_lev[$x])</td>";
				}
			}
			if (($status>0)&&($serienr[$x])){print "<td onClick=\"serienummer($linje_id[$x])\"><input type=button value=\"Serienr.\" name=\"vis_snr$x\"></td>";}
			if (($antal[$x]<0)&&($art!='KK')&&($box9[$x]=='on')) {print "<td align=center onClick=\"batch($linje_id[$x])\">$font<span title= 'V&aelig;lg fra k&oslash;bsordre'><small><img alt=\"K&oslash;bsordre\" src=../ikoner/serienr.png></td></td>";}

#print "<BODY onClick=\"JavaScript:window.open('batch.php?linje_id=$linje_id', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";

			print "</tr>\n";
		}
		print "<tr>";
		print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=3 name=posn0 value=$x></td>";
		if ($art!='KK') {
			print "<td>$font<small><input type=text size=7 name=vare0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
			print "<td>$font<small><input type=text size=7 name=lev_v0></td>";
			print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=2 name=anta0></td>";
		}
		else {
			print "<td>$font<small><input readonly=readonly size=7></td>";
			print "<td>$font<small><input readonly=readonly size=7></td>";
			print "<td>$font<small><input readonly=readonly size=2></td>";
		}
		print "<td>$font<small><input type=text size=60 name=beskrivelse0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=10 name=pris0></td>";
		print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=4 name=raba0></td>";
		print "<td>$font<small></td>";
#		if ($status==1) {print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=2 name=modt0></td>";}
		print "</tr>\n";
		print "<input type=hidden size=3 name=sum value=$sum>";
		$moms=$momssum/100*$momssats;
		if ($art=='KK') $moms=$moms-0.0001; #Ellers runder den op istedet for ned?
		else $moms=$moms+0.0001; #Ellers runder den ned istedet for op?
		$moms=round($moms,2);
		if ($id) {db_modify("update ordrer set sum='$sum', moms='$moms' where id=$id");}
		if ($art=='KK') {
			$sum=$sum*-1;
			$moms=$moms*-1;
		}
		$ialt=$sum+$moms;
#		$sum=dkdecimal($sum);
#		$moms=dkdecimal($moms);
		print "<tr><td colspan=7><table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" width=100%><tbody>";
		print "<tr>";
		print "<td align=center>$font<small>Ordresum</td><td align=center>$font<small>".dkdecimal($sum)."</td>";
		print "<td align=center>$font<small>Moms</td><td align=center>$font<small>".dkdecimal($moms)."</td>";
		print "<td align=center>$font<small>I alt</td><td align=right>$font<small>".dkdecimal($ialt)."</td>";

		print "</tbody></table></td></tr>\n";
		print "<input type=\"hidden\" name=\"fokus\">";
		print "<tr><td align=center colspan=8>";
		print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tbody><tr>";
		print "<td align=center><input type=submit accesskey=\"g\" value=\"&nbsp;&nbsp;Gem&nbsp;&nbsp;\" name=\"submit\"></td>";
		print "<td align=center><input type=submit accesskey=\"o\" value=\"Opslag\" name=\"submit\"></td>";
		if (($status==1)&&($bogfor==1)) {
			if ($art=='KK') {print "<td align=center><input type=submit accesskey=\"m\" value=\"Return&eacute;r\" name=\"submit\"></td>";}
			else {print "<td align=center><input type=submit accesskey=\"m\" value=\"Modtag\" name=\"submit\"></td>";}
		}
		elseif ($status > 1 && $bogfor==1){print "<td align=center><input type=submit accesskey=\"b\" value=\"Bogf&oslash;r\" name=\"submit\"></td>";}
		if (!$posnr[1] && $id) {print "<td align=center><input type=submit value=\"&nbsp;&nbsp;Slet&nbsp;&nbsp;\" name=\"submit\"></td>";}
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
	}
	print "</tbody></table></td></tr>\n";
	print "</form>";
	print "</tbody></table></td></tr></tbody></table></td></tr>\n";
	print "<tr><td></td></tr>\n";
}# end function ordreside
######################################################################################################################################
function kontoopslag($sort, $fokus, $id, $find){ 
	global $font;	
	global $bgcolor;
	global $bgcolor5;
	
	if ($find) $find=str_replace("*","%",$find);

	sidehoved($id, "ordre.php", "../kreditor/kreditorkort.php", $fokus, "Leverand&oslash;rordre $id");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
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

	if ($find) $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'K' and $fokus like '$find' order by $sort");
	else $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'K' order by $sort");
	while ($row = db_fetch_array($query)) {
		$kontonr=str_replace(" ","",$row['kontonr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><small>$font<a href=ordre.php?fokus=$fokus&id=$id&konto_id=$row[id]>$row[kontonr]</a></small></td>";
		print "<td><small>$font".htmlentities(stripslashes($row[firmanavn]))."</small></td>";
		print "<td><small>$font".htmlentities(stripslashes( $row[addr1]))."</small></td>";
		print "<td><small>$font".htmlentities(stripslashes( $row[addr2]))."</small></td>";
		print "<td><small>$font $row[postnr]</small></td>";
		print "<td><small>$font".htmlentities(stripslashes( $row[bynavn]))."</small></td>";
		print "<td><small>$font ".htmlentities(stripslashes($row[land]))."</small></td>";
		print "<td><small>$font".htmlentities(stripslashes( $row[kontakt]))."</small></td>";
		print "<td><small>$font $row[tlf]</small></td>";
		print "</tr>\n";
	}

print "</tbody></table></td></tr></tbody></table>";
exit;
}
######################################################################################################################################
function ansatopslag($sort, $fokus, $id){
	global $font;
	global $bgcolor;
	global $bgcolor5;

	sidehoved($id, "ordre.php", "../kreditor/kreditorkort.php", $fokus, "Leverand&oslash;rordre $id");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><small><b>$font<a href=ordre.php?sort=navn&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=tlf&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Lokal</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=mobil&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Mobil</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=email&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>E-mail</b></small></td>";
	print" </tr>\n";


	$sort = $_GET['sort'];
	if (!$sort) {$sort = navn;}

	$query = db_select("select konto_id from ordrer where id = $id");
	$row = db_fetch_array($query);

	$query = db_select("select * from ansatte where konto_id = $row[konto_id] order by $sort");
	while ($row = db_fetch_array($query))	{
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><small>$font<a href='ordre.php?fokus=$fokus&id=$id&kontakt=$row[navn]'>".htmlentities(stripslashes($row[navn]))."</a></small></td>";
		print "<td><small>$font $row[tlf]</small></td>";
		print "<td><small>$font $row[mobil]</small></td>";
		print "<td><small>$font $row[email]</small></td>";
		print "</tr>\n";
	}

print "</tbody></table></td></tr></tbody></table>";
exit;
}
######################################################################################################
function vareopslag($sort, $fokus, $id, $vis, $ref, $find) {
	global $kontonr;
	global $font;
	global $bgcolor;
	global $bgcolor5;

	if ($find) $find=str_replace("*","%",$find);

	if ((!$kontonr)&&($id))	{
		$query = db_select("select kontonr from ordrer where id = $id");
		if ($row = db_fetch_array($query)) {$kontonr=trim($row[kontonr]);}
	}
	if ($kontonr) {
		$query = db_select("select id from adresser where kontonr = '$kontonr'");
		if ($row = db_fetch_array($query)) {$leverandor=$row[id];}
	}

	sidehoved($id, "ordre.php", "../lager/varekort.php", "$fokus&leverandor=$leverandor", "Leverand&oslash;rordre $id");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><small><b>$font<a href=ordre.php?sort=varenr&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis=$vis>Varenr</a></b></small></td>";
	print"<td><small><b>$font Enhed</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=beskrivelse&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis=$vis>Beskrivelse</a></b></small></td>";
	print"<td align=right><small><b>$font<a href=ordre.php?sort=salgspris&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis=$vis>Salgspris</a></b></small></td>";
	print"<td align=right><small><b>$font Kostpris</b></small></td>";
	print"<td align=right><small><b>$font Beholdning&nbsp;</b></small></td>";
#	print"<td width=2%></td>";
	print"<td align><small><b>$font Leverand&oslash;r</b></small></td>";
	if ($kontonr)	{
		if ($vis) {print"<td align=right><small>$font<a href=ordre.php?sort=$sort&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id>Vis alle lev.</a></small></td>";}
		else {print"<td align=right><small>$font<a href=ordre.php?sort=$sort&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis=1>Vis aktuel lev.</a></small></td>";}
	}
		print" </tr>\n";

	$sort = $_GET['sort'];
	if (!$sort) {$sort = varenr;}


	$vare_id=array();
	if (($vis)&&($leverandor)) {
		$temp=" and lev_id = ".$leverandor;
	}
	
	$x=0;
	$query = db_select("select * from vare_lev");
	while ($row = db_fetch_array($query)) {
		$x++;
		$vare_id[$x]=$row[vare_id];
	}

	if ($ref){
		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'"))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'"))) {$lager=$row['kodenr'];}
		}
	}
	$lager=$lager*1;
	if (!$sort) {$sort = varenr;}


	if (!$kontonr){$x++;}
	elseif ($x>1) {print "<td colspan=9><hr></td>";}
	if ($find) {
		$query = db_select("select * from varer where lukket != '1' and $fokus like '$find' order by $sort");
	}
	else $query = db_select("select * from varer where lukket != '1' order by $sort");
	while ($row = db_fetch_array($query)) {
		$varenr=trim($row[varenr]);
		$vist=0;
		$query2 = db_select("select * from vare_lev where vare_id = $row[id] $temp");
		while ($row2 = db_fetch_array($query2)) {
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			else {$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\">";
			print "<td><small>$font<a href=\"ordre.php?varenr=$varenr&fokus=$fokus&konto_id=$row2[lev_id]&id=$id\">".htmlentities(stripslashes($varenr))."</a></small></td>";
			print "<td><small>$font$row[enhed]<br></small></td>";
			print "<td><small>$font $row[beskrivelse]<br></small></td>";
			$salgspris=dkdecimal($row[salgspris]);
			print "<td align=right><small>$font $salgspris<br></small></td>";
			$kostpris=dkdecimal($row2[kostpris]);
			print "<td align=right><small>$font $kostpris<br></small></td>";
			if ($lager>=1){
				$q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest>0 and lager=$lager");
				while ($r2 = db_fetch_array($q2)) {
					$q3 = db_select("select * from reservation where batch_kob_id=$r2[id]");
					while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3[antal];}
				}
				$linjetext="<span title= 'Reserveret: $reserveret'>";
				if ($r2= db_fetch_array(db_select("select beholdning from lagerstatus where vare_id=$row[id] and lager=$lager"))) {
					print "<td align=right>$linjetext<small>$font $r2[beholdning] &nbsp;</small></span></td>";
				} else print "<td align=right>$linjetext<small>$font 0 &nbsp;</small></span></td>";
			}
			else {print "<td align=right><small>$font $row[beholdning] &nbsp;</small></td>"; }
#			print "<td></td>";
			$levquery = db_select("select kontonr, firmanavn from adresser where id=$row2[lev_id]");
			if ($levrow = db_fetch_array($levquery)){print "<td><small>$font ".htmlentities(stripslashes($levrow[firmanavn]))."</small></td>";}
			else {print "<td></td>";}
			print "<td align=right><a href=\"../lager/varekort.php?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus&id=$row[id]\">$font<small>Ret</a></td>";
			print "</tr>\n";
			$vist=1;
		}
		
		if ((!in_array($row[id], $vare_id))&&($vist==0)&&($row['samlevare']!='on')&&($leverandor)) {
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			else {$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\">";
			print "<td><small>$font<a href=\"ordre.php?varenr=$varenr&fokus=$fokus&id=$id\">$row[varenr]</a></small></td>";
			print "<td><small>$font$row[enhed]<br></small></td>";
			print "<td><small>$font ".htmlentities(stripslashes($row[beskrivelse]))."<br></small></td>";
			$salgspris=dkdecimal($row[salgspris]);
			print "<td align=right><small>$font $salgspris<br></small></td>";
			$kostpris=dkdecimal($row[kostpris]);
			print "<td align=right><small>$font $kostpris<br></small></td>";
			print "<td></td><td></td>";
			print "<td align=right><a href=\"../lager/varekort.php?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus&id=$row[id]\">$font<small>Ret</a></td>";
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
	global $font;
	global $top_bund;

	print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Leverand&oslash;rordre</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";
	print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
	print "<div align=\"center\">";

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	if ($returside != "ordre.php") {print "<td width=\"10%\" $top_bund>$font $color<small><a href=../includes/luk.php?tabel=ordrer&id=$id accesskey=L>Luk</a></small></td>";}
	else {print "<td width=\"10%\" $top_bund>$font $color<small><a href=ordre.php?id=$id accesskey=L>Luk</a></small></td>";}
	print "<td width=\"80%\" $top_bund>$font $color<small>$tekst</small></td>";
	if (($returside != "ordre.php")&&($id)) {print "<td width=\"10%\" $top_bund>$font $color<small><a href=ordre.php?returside=ordreliste.php accesskey=N>Ny</a></small></td>";}
	elseif ($kort=="../kreditor/kreditorkort.php") {
		print "<td width=\"5%\"$top_bund onClick=\"javascript:kreditor_vis=window.open('kreditorvisning.php','kreditor_vis','scrollbars=1,resizable=1');kreditor_vis.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\">$font <small><span title='V&aelig;lg hvilke kreditorgrupper som vises i varelisten'><u>Visning</u></span></td>";		
		print "<td width=\"5%\" $top_bund>$font $color<small><a href=$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus accesskey=N>Ny</a></small></td>";
	}
	elseif (($id)||($kort!="../lager/varekort.php")) {print "<td width=\"10%\" $top_bund>$font $color<small><a href=$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus accesskey=N>Ny</a></small></td>";}
	else {print "<td width=\"10%\" $top_bund>$font $color</td>";}
	
	print "</tbody></table>";
	print "</td></tr>\n";
	print "<tr><td valign=\"top\" align=center>";
}
######################################################################################################################################
function indset_konto($id, $konto_id) {
	global $art;
	global $brugernavn;
	$tidspkt=date("U");

	$query = db_select("select * from adresser where id = '$konto_id'");
	if ($row = db_fetch_array($query))
	{
		$kontonr=trim($row['kontonr']);
		$firmanavn=addslashes(trim($row['firmanavn']));
		$addr1=addslashes(trim($row['addr1']));
		$addr2=addslashes(trim($row['addr2']));
		$postnr=trim($row['postnr']);
		$bynavn=addslashes(trim($row['bynavn']));
		$land=addslashes(trim($row['land']));
		$betalingsdage=$row['betalingsdage'];
		$betalingsbet=trim($row['betalingsbet']);
		$cvrnr=trim($row['cvrnr']);
		$notes=addslashes(trim($row['notes']));
		$gruppe=trim($row['gruppe']);
	}
	if ($gruppe) {
		$query = db_select("select box1, box3 from grupper where art='KG' and kodenr='$gruppe'");
		$row = db_fetch_array($query);
			$valuta=trim($row['box3']);
		if (substr($row[box1],0,1)=='K') {
			$tmp= substr($row[box1],1,1);
			$query = db_select("select box2 from grupper where art='KM' and kodenr = '$tmp'");
			$row = db_fetch_array($query);
			$momssats=trim($row['box2']);
		}
		elseif (substr($row[box1],0,1)=='E') $momssats='0.00';
	}
	if (!$momssats) {
		print "<BODY onLoad=\"javascript:alert('Kreditorgrupper forkert opsat')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
		exit;
	}
	if ((!$id)&&($firmanavn)) {
		$ordredate=date("Y-m-d");
		$query = db_select("select ordrenr from ordrer where art='KO' or art='KK' order by ordrenr desc");
		if ($row = db_fetch_array($query)) {$ordrenr=$row[ordrenr]+1;}
		else {$ordrenr=1;}
#		if ($row= db_fetch_array(db_select("select ansat_id from brugere where brugernavn='$brugernavn'"))) {
#			if ($row= db_fetch_array(db_select("select afd from ansatte where id='$row[ansat_id]'"))) {
#				if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'"))) {$lager_id=$row['kodenr'];}
#			}
#		}
#		if (!$lager_id) {$lager_id='0';}
		db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, betalingsdage, betalingsbet, cvrnr, notes, art, ordredate, momssats, status, hvem, tidspkt, valuta) values ($ordrenr, '$konto_id', '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$betalingsdage', '$betalingsbet', '$cvrnr', '$notes', 'KO', '$ordredate', '$momssats', '0', '$brugernavn', '$tidspkt', '$valuta')");
		$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc");
		if ($row = db_fetch_array($query)) {$id=$row[id];}
	}
	elseif($firmanavn) {
		$query = db_select("select tidspkt from ordrer where id=$id and hvem='$brugernavn'");
		if ($row = db_fetch_array($query)) {
			db_modify("update ordrer set konto_id=$konto_id, kontonr='$kontonr', firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', notes='$notes', hvem = '$brugernavn', tidspkt='$tidspkt', valuta='$valuta' where id=$id");
		}
		else {			
			$query = db_select("select hvem from ordrer where id=$id");
			if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"fejltekst('Ordren er overtaget af $row[hvem]')\">";}
			else {print "<BODY onLoad=\"fejltekst('Du er blevet smidt af')\">";}
		}	
	}
	return $id;
}
######################################################################################################################################
function find_vare_id ($varenr) {
	$query = db_select("select id from varer where varenr = '$varenr'");
	if ($row = db_fetch_array($query)) {return $row[id];}
}
##############################################################################
function indsaet_linjer($ordre_id, $linje_id, $posnr)
 {
	$posnr = str_replace('+',':',$posnr); #jeg ved ikke hvorfor, men den vil ikke splitte med "+"
	list ($posnr, $antal) = split (':', $posnr);
	db_modify("update ordrelinjer set posnr='$posnr' where id='$linje_id'");
	for ($x=1; $x<=$antal; $x++) {
		db_modify("insert into ordrelinjer (posnr, ordre_id) values ('$posnr', '$ordre_id')");
	}
}


?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
