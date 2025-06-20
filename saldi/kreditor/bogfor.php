<?php
@session_start();
$s_id=session_id();
// -------------------kreditor/bogfor.php-------lap 3.5.6----2015-04-22--
/// LICENS
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// -------------------------------------------------------------------------
//
// 20131108 Fejl v. fifo og varetilgang på varekøb.Søg #20131108
// 20140611 Indsat if($batch_id) da kostpriser i ordrelinjer ellers bliver ændret på alt som ikke har batch_kob_id v. negativt køb af vare som aldrig har været handlet.
// 20141005 Div i forbindelse med omvendt betalingspligt, samt nogle generelle ændringer således at varereturnering nu bogføres
//	som negativt køb og ikke som salg.
// 20141117 Fejl v. fifo og vareafgang v. negativt varekøb.Søg #20141117
// 20150422 Hvis samme vare købes til forskellige priser blev kostpris sat til sidste pris. Ændret så den nu sættes til snitprisen. Søg snitpris.

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$id=$_GET['id'];

$query = db_select("select levdate, status from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
if ($row[status]>2){
	print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
}

$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
if ($row = db_fetch_array($query)){
	$year=substr(str_replace(" ","",$row['box2']),-2);
	$aarstart=str_replace(" ","",$year.$row['box1']);
	$year=substr(str_replace(" ","",$row['box4']),-2);
	$aarslut=str_replace(" ","",$year.$row['box3']);
}

$r=db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
$fifo=$r['box6'];
$r=db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__));
$kostmetode=$r['box6'];

#cho "FIFO $fifo<br>";

$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$art=$row['art'];
$konto_id=$row['konto_id'];
$kred_ord_id=$row['kred_ord_id'];
$levdate=$row['levdate'];
$valuta=$row['valuta'];
$projekt[0]=$row['projekt'];
$cvrnr=$row['cvrnr'];
if ($valuta && $valuta!='DKK') {
	if ($r= db_fetch_array(db_select("select valuta.kurs as kurs, grupper.box3 as difkto from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=".nr_cast("grupper.kodenr",__FILE__ . " linje " . __LINE__)." and valuta.valdate <= '$levdate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
		$valutakurs=$r['kurs']*1;
		$difkto=$r['difkto']*1;
		if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))) {
			print "<BODY onLoad=\"javascript:alert('Kontonr $difkto (kursdiff) eksisterer ikke')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	} else {
		$tmp = dkdato($levdate);
		print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $tmp')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	}
} else {
	$valuta='DKK';
	$valutakurs=100;
}
	
if (!$row['levdate']){
	print "<BODY onLoad=\"javascript:alert('Leveringsdato SKAL udfyldes')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
} elseif (!trim($row['fakturanr'])){
	print "<BODY onLoad=\"javascript:alert('Fakturanummer SKAL udfyldes og m&aring; ikke v&aelig;re 0')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
} else {
	$fejl=0;
	if ($row['levdate']<$row['ordredate']){
		print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	}
	$levdate=$row['levdate'];
	list ($year, $month, $day) = explode ('-', $row['levdate']);
	$year=substr($year,-2);
	$ym=$year.$month;
	if (($ym<$aarstart)||($ym>$aarslut)){
		print "<BODY onLoad=\"javascript:alert('Leveringsdato udenfor regnskabs&aring;r')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	}
	if ($fejl==0){
		echo "bogf&oslash;rer nu!........";
		transaktion("begin");
		$x=0;
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)){
#cho "Ordrelinjer 	 $row[posnr], $row[beskrivelse], $row[antal],$row[pris]<br>";
			if (($row['posnr']>0)&&(strlen(trim(($row['varenr'])))>0)){
				$x++;
				$linje_id[$x]=$row['id'];
				$kred_linje_id[$x]=$row['kred_linje_id'];
				$varenr[$x]=$row['varenr'];
				$vare_id[$x]=$row['vare_id'];
				$antal[$x]=$row['antal'];
				if ($row['projekt']) $projekt[$x]=$row['projekt'];
				else $projekt[$x]=$projekt[0];
				$pris[$x]=$row['pris']-($row['pris']*$row['rabat']/100);
				if ($valutakurs) $dkpris[$x]=afrund(($pris[$x]*$valutakurs/100),3); # Omregning til DKK.		
				else $dkpris[$x]=$pris[$x];
				$serienr[$x]=$row['serienr'];
				$samlevare[$x]=$row['samlevare'];
			}
		}
		$linjeantal=$x;
		for ($x=1; $x<=$linjeantal; $x++) {
			$tot_antal[$x]=0;
			$tot_pris[$x]=0;
			$q=db_select("select * from ordrelinjer where ordre_id = '$id' and vare_id=$vare_id[$x]",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)){
				$tot_antal[$x]+=$r['antal'];
				$tot_pris[$x]+=$r['pris']*$r['antal'];
			}
			if ($tot_antal[$x]) $snitpris[$x]=$tot_pris[$x]/$tot_antal[$x];
			else $snitpris[$x]=$pris[$x];
			if ($valutakurs) $snitpris[$x]=afrund(($snitpris[$x]*$valutakurs/100),3); # Omregning til DKK.		
 		}
		for ($x=1; $x<=$linjeantal; $x++) {
			$query = db_select("select id, gruppe,beholdning,kostpris from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__); #rettet fra varenr til vare_id 20120612 grundet æøå problem i varenr.
			$row = db_fetch_array($query);
			$vare_id[$x]=$row['id'];
			$gruppe[$x]=$row['gruppe'];
			$beholdning[$x]=$row['beholdning'];
			$gl_kostpris[$x]=$row['kostpris'];
		}
		for ($x=1; $x<=$linjeantal; $x++) {
			if (($vare_id[$x])&&($antal[$x]!=0)) {
				$query = db_select("select * from grupper where art='VG' and kodenr='$gruppe[$x]'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$vgbeskrivelse=$row['beskrivelse']; $box1=trim($row['box1']); $box2=trim($row['box2']); $box3=trim($row['box3']); $box4=trim($row['box4']); $box8=trim($row['box8']); $box9=trim($row['box9']);
				$box11=trim($row['box11']);$box13=trim($row['box13']);
				if (!$box3) {
					print "<BODY onLoad=\"javascript:alert('Der er ikke opsat kontonummer for varek&oslash;b p&aring; varegruppen: $vgbeskrivelse.')\">";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
					exit;
				}
				$r=db_fetch_array(db_select("select box2 from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__));
				($r['box2'] >= '2015')?$aut_lager='on':$aut_lager=NULL;
				
				if ($aut_lager){
					$box1=$box3;
					$box2=$box4;
				} else {
					if (!$box1 && $box3) $box1=$box3; #20131108
					if (!$box2 && $box4) $box2=$box4; #20141117
				}
				if ($box11 && cvrnr_omr(cvrnr_land($cvrnr)) == "EU") $bf_kto=$box11; 
				elseif ($box13 && cvrnr_omr(cvrnr_land($cvrnr)) == "UD") $bf_kto=$box13;
				else $bf_kto=$box3;
#cho "cvr $cvrnr box11 $box11 box13 $box13 bf_kto $bf_kto<br>";
				if ($box8!='on'){
#cho "update ordrelinjer set bogf_konto='$bf_kto' where id='$linje_id[$x]'<br>";
					db_modify("update ordrelinjer set bogf_konto='$bf_kto' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					db_modify("update batch_kob set pris = '$dkpris[$x]', fakturadate='$levdate' where linje_id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
				} else {
					if ($box1) { #Box 1 er konto for lagertilgang,
#cho "update ordrelinjer set bogf_konto='$box1' where id='$linje_id[$x]'<br>";
						db_modify("update ordrelinjer set bogf_konto='$box1' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					} else {
						db_modify("update ordrelinjer set bogf_konto='$bf_kto' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					}
					if ($antal[$x]>0) {
#cho "A select * from batch_kob where linje_id=$linje_id[$x]<br>";
						$query = db_select("select * from batch_kob where linje_id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
						while ($row = db_fetch_array($query)) { # if ændret til while grundet fejl ved meotagelse af flere omgange på samme ordrelinje 2012.04.18 saldi_2 ordre id 4226
							$batch_id=$row['id']*1;
						# Herunder IS NOT indsat 20100811 da lgerværdi kun skal reguleres hvis varen er solgt (faktureret). 
#cho "B select linje_id from batch_salg where batch_kob_id=$batch_id and fakturadate is not NULL<br>";
						$q2 = db_select("select linje_id from batch_salg where batch_kob_id=$batch_id and fakturadate is not NULL",__FILE__ . " linje " . __LINE__);
							while ($r2 = db_fetch_array($q2)) { #Kun aktuel hvis batch_kontrol er aktiv.
#cho "C select id,vare_id,ordre_id,antal,kostpris from ordrelinjer where id='$r2[linje_id]'<br>";
								$r3=db_fetch_array(db_select("select id,vare_id,ordre_id,antal,kostpris from ordrelinjer where id='$r2[linje_id]'",__FILE__ . " linje " . __LINE__));
								if ($r3['antal']) {
									$kostpris=$r3['kostpris'];#/$r3['antal']; 
									$r3=db_fetch_array(db_select("select valutakurs from ordrer where id='$r3[ordre_id]'",__FILE__ . " linje " . __LINE__));
									if ($r3['valutakurs'] && $r3['valutakurs']!=100) $dk_kostpris=$kostpris*$r3['valutakurs']/100;
									else $dk_kostpris=$kostpris;
								} else $kostpris=0;
								if ($box1 && !$aut_lager) {
									$diff=afrund($dkpris[$x]-$dk_kostpris,2);
#cho __LINE__." Diff ".$diff."<br>";
									if ($diff) {
										$batch_antal=$row['antal']*1;
										$batch_rest=$row['rest']*1;
										$tmp=$batch_antal-$batch_rest;
										$qtxt="insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1', '$tmp', '$diff', 0, $id, $box3,'$projekt[$x]')";
#cho __LINE__." ".$qtxt."<br>";
										db_modify($qtxt,__FILE__ . " linje " . __LINE__);
										$diff=$diff*-1;
										$qtxt="insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1', '$tmp', '$diff', 0, $id, $box2,'$projekt[$x]')";
#cho __LINE__." ".$qtxt."<br>";
										db_modify($qtxt,__FILE__ . " linje " . __LINE__);
									}
								}
							}
#xit;
#cho "F update batch_kob set pris = '$dkpris[$x]', fakturadate='$levdate' where linje_id=$linje_id[$x]<br>";
							db_modify("update batch_kob set pris = '$dkpris[$x]', fakturadate='$levdate' where linje_id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
#cho "G select id from batch_kob where linje_id=$linje_id[$x]<br>";
							$q2 = db_select("select id from batch_kob where linje_id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
							while ($r2 = db_fetch_array($q2)) {
								$tmp=$r['id']*1;
									if ($tmp) {
#cho "H select linje_id from batch_salg where batch_kob_id=$tmp<br>";
									$q3 = db_select("select linje_id from batch_salg where batch_kob_id=$tmp",__FILE__ . " linje " . __LINE__);
									while ($r3 = db_fetch_array($q3)) {
#cho "I update ordrelinjer set kostpris = '$dkpris[$x]' where id=$r3[linje_id]<br>";
										db_modify("update ordrelinjer set kostpris = '$dkpris[$x]' where id=$r3[linje_id]",__FILE__ . " linje " . __LINE__);
									}
								}
							}
						}
						if ($fifo) {
								$bogf_beh=0;
							$q=db_select("select * from batch_kob where vare_id=$vare_id[$x] and linje_id!=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
							while ($r=db_fetch_array($q)){
								if ($r['fakturadate']) $bogf_beh+=$r['rest'];
							}
							if ($bogf_beh>0 && $kostmetode=='1') {
								echo "G $gl_kostpris[$x]<br>";
								$ny_kostpris=($bogf_beh*$gl_kostpris[$x]+$antal[$x]*$snitpris[$x])/($bogf_beh+$antal[$x]);
							} else $ny_kostpris=$snitpris[$x];
							#cho "select id from batch_kob where vare_id=$vare_id[$x] and fakturadate>'$levdate'<br>";
/*
							if (!db_fetch_array(db_select("select id from batch_kob where vare_id=$vare_id[$x] and fakturadate>'$levdate'",__FILE__ . " linje " . __LINE__))){
echo "update varer set kostpris='$ny_kostpris' where id='$vare_id[$x]'<br>";
								db_modify("update varer set kostpris='$ny_kostpris' where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
echo "update vare_lev set kostpris='$dkpris[$x]' where vare_id='$vare_id[$x]' and lev_id='$konto_id'<br>";
								db_modify("update vare_lev set kostpris='$dkpris[$x]' where vare_id='$vare_id[$x]' and lev_id='$konto_id'",__FILE__ . " linje " . __LINE__);
							}
*/							
							# Finder hvor mange som er leveret til kunder men ikke faktureret:	
							if (!$box9 && !$aut_lager) { # Skal ikke gøres hvis batchkontrol er slået til
								$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_salg where vare_id=$vare_id[$x] and fakturadate is NULL",__FILE__ . " linje " . __LINE__));
								if ($lev_ej_fakt[$x]=$r2['antal']) {
#cho __LINE__." $lev_ej_fakt[$x]<br>";
									if ($antal[$x]>($beholdning[$x]+$lev_ej_fakt[$x])) {
										$diff=($gl_kostpris[$x]-$snitpris[$x])*($antal[$x]-($beholdning[$x]+$lev_ej_fakt[$x]));
										if ($diff) {
											db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1', '1', '$diff', 0, $id, $box3,'$projekt[$x]')",__FILE__ . " linje " . __LINE__);
											$diff=$diff*-1;
											db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1', '1', '$diff', 0, $id, $box1,'$projekt[$x]')",__FILE__ . " linje " . __LINE__);
										}
									}	
								}
							}
						} elseif ($beholdning[$x]>0 && $kostmetode=='1') $ny_kostpris=($beholdning[$x]*$gl_kostpris[$x]+$antal[$x]*$snitpris[$x])/($beholdning[$x]+$antal[$x]);
						else $ny_kostpris=$snitpris[$x];
						if ($kostmetode) {
							if ($ny_kostpris!=$gl_kostpris[$x]) {
								$qtxt=NULL;	
								$r=db_fetch_array(db_select("select id,kostpris,transdate from kostpriser where id='$vare_id[$x]' order by transdate desc limit 1",__FILE__ . " linje " . __LINE__));
								if ($r['transdate'] != $levdate && $r['kostpris'] != $dkpris[$x]) $qtxt="insert into kostpriser (vare_id,kostpris,transdate) values ('$vare_id[$x]','$dkpris[$x]','$levdate')";
								elseif ($r['transdate'] == $levdate && $r['kostpris'] != $dkpris[$x]) $qtxt="update kostpriser set kostpris='$dkpris[$x]' where id = '$r[id]'";
								if ($qtxt) db_modify("$qtxt",__FILE__ . " linje " . __LINE__);
								db_modify("update varer set kostpris='$snitpris[$x]' where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
							}
						}	
						echo "update vare_lev set kostpris='$snitpris[$x]' where vare_id='$vare_id[$x]' and lev_id='$konto_id'<br>";
						db_modify("update vare_lev set kostpris='$snitpris[$x]' where vare_id='$vare_id[$x]' and lev_id='$konto_id'",__FILE__ . " linje " . __LINE__);
					} else {
						$kred_linje_id[$x]=$kred_linje_id[$x]*1; # patch 2.0.2a
						$query = db_select("select * from batch_kob where linje_id=$kred_linje_id[$x]",__FILE__ . " linje " . __LINE__);
						if ($row = db_fetch_array($query)) {
							$batch_id=$row['id']*1;
							$diff=$dkpris[$x]-$row['pris'];
							if ($diff && !$aut_lager) {
								db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1', $antal[$x], '$diff', 0, $id, $box3,'$projekt[$x]')",__FILE__ . " linje " . __LINE__);
								$diff=$diff*-1;
								db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1', $antal[$x], '$diff', 0, $id, $box1,'$projekt[$x]')",__FILE__ . " linje " . __LINE__);
							}
						}
						$batch_id=$batch_id*1;
						$query = db_select("select * from batch_kob where vare_id=$vare_id[$x] and linje_id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
						while ($row = db_fetch_array($query)) {
						db_modify("update batch_kob set pris = '$dkpris[$x]', fakturadate='$levdate' where id=$row[id]",__FILE__ . " linje " . __LINE__);
						if ($batch_id) { #20140611
							$q2 = db_select("select linje_id from batch_salg where batch_kob_id=$batch_id",__FILE__ . " linje " . __LINE__);
								while ($r2 = db_fetch_array($q2)) {
									db_modify("update ordrelinjer set kostpris = '$dkpris[$x]' where id=$r2[linje_id]",__FILE__ . " linje " . __LINE__);
								}
							} # endif ($batch_id)
						}
					} # endif & else ($antal[$x]>0)
				}
			}
		}
		$modtagelse=1;
		$query = db_select("select modtagelse from ordrer order by modtagelse",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {	
			if ($row['modtagelse'] >=$modtagelse) {$modtagelse = $row['modtagelse']+1;}
		}
		$row = db_fetch_array($query = db_select("select box2 from grupper where art = 'RB'",__FILE__ . " linje " . __LINE__));
		if ($modtagelse==1) $modtagelse = $row['box2']*1;
		if ($modtagelse<1) $modtagelse=1;
		
		db_modify("update ordrer set status=3, fakturadate='$levdate', modtagelse = '$modtagelse', valuta = '$valuta', valutakurs = '$valutakurs' where id=$id",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q = db_select("select box5 from grupper where art='DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__));
		if ($r['box5']=='on') bogfor($id);
		transaktion("commit");
	}
}
print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";

function bogfor($id) {
	global $regnaar;
	global $valuta;
	global $valutakurs;
	global $difkto;
	global $sprog_id;
	
	$d_kontrol=0; 
	$k_kontrol=0;
	
	$linjesum=0;
	$fakturasum=0;
	$momssum=0;
	
	$logdate=date("Y-m-d");
	$logtime=date("H:i");
	$q = db_select("select box1, box2, box4, box5 from grupper where art='RB'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		if (trim($r['box4'])=="on") $modtbill=1; 
		else $modtbill=0;
		if (trim($r['box5'])=="on") {
			$no_faktbill=1;
			$faktbill=0;
		}	 
		else $no_faktbill=0;
	}
	
	$x=0;
	$q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$art=$r['art'];
		$konto_id=$r['konto_id'];
		$lev_kontonr=str_replace(" ","",$r['kontonr']);
		$firmanavn=addslashes(trim($r['firmanavn']));
		$modtagelse=$r['modtagelse'];
		$transdate=($r['fakturadate']);
		$fakturanr=addslashes($r['fakturanr']);
		$ordrenr=$r['ordrenr'];
		$projekt[0]=$r['projekt'];
		$valuta=$r['valuta'];
		$valutakurs=$r['valutakurs']*1;
		$moms = $r['moms']*1;
		$momssats=$r['momssats']*1;
		$sum=$r['sum'];
		$omlev=$r['omvbet'];
		$ordreantal=$x;
		if ($r= db_fetch_array(db_select("select afd from ansatte where navn = '$r[ref]'",__FILE__ . " linje " . __LINE__))) {$afd=$r['afd'];}
		$afd=$afd*1; #sikkerhed for at 'afd' har en vaerdi 
		$ansat=$r['id']*1;
		if ($no_faktbill==1) $bilag='0';
		else $bilag=trim($fakturanr);
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$r = db_fetch_array(db_select("select box1,box2 from grupper where art = 'KG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['box2'];
		$box1=substr(trim($r['box1']),0,1);
		if ($box1 && ($box1!='E' || $box1!='Y')) $sum=$sum+$moms;	#moms tillaegges summen der ikke er eu moms.
########### OPENPOST	-> 	
		if (substr($art,1,1)=='K') $beskrivelse ="Lev. kn.nr: ".$fakturanr.", modt. nr ".$modtagelse;
		else $beskrivelse ="Lev. fakt.nr:".$fakturanr.", modt.nr: ".$modtagelse;
#cho "insert into openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr, valuta, valutakurs,projekt) values ('$konto_id', '$lev_kontonr', '$fakturanr', $sum*-1, '$beskrivelse', '0', '$transdate', '0', '$id', '$valuta', '$valutakurs','$projekt[0]')<br>";
		db_modify("insert into openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr, valuta, valutakurs,projekt) values ('$konto_id', '$lev_kontonr', '$fakturanr', $sum*-1, '$beskrivelse', '0', '$transdate', '0', '$id', '$valuta', '$valutakurs','$projekt[0]')",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select max(id) as id from openpost where konto_id = '$konto_id' and faktnr = '$fakturanr' and refnr='$id'",__FILE__ . " linje " . __LINE__));
		$openpost_id=$r['id'];
########### <- OPENPOST	
		$tekst=findtekst(157,$sprog_id);
		if ($kontonr) {
			$r = db_fetch_array(db_select("select id from kontoplan where kontonr='$kontonr' and regnskabsaar = '$regnaar' and lukket!='on'",__FILE__ . " linje " . __LINE__));
			if (!$r['id']) {
				print "<BODY onLoad=\"javascript:alert('$tekst')\">"; 
			exit;			
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
			}
		} else {
			print "<BODY onLoad=\"javascript:alert('$tekst')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
		if ($sum>0) {$kredit=$sum; $debet='0';}
		else {$kredit='0'; $debet=$sum*-1;}
		if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKK.		
		$debet=afrund($debet,2);
		$kredit=afrund($kredit,2);
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		$fakturasum+=$debet-$kredit;	
		if ($modtbill) $bilag=$modtagelse*1;
		else $bilag='0';
		if ($sum) {
#cho "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$kontonr','$fakturanr','$debet','$kredit','0',$afd,'$logdate','$logtime','$projekt[0]','$ansat','$id')<br>";
			db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$kontonr','$fakturanr','$debet','$kredit','0',$afd,'$logdate','$logtime','$projekt[0]','$ansat','$id')",__FILE__ . " linje " . __LINE__);
		}
		if ($valutakurs) $maxdif=2; #Der tillades 2 oeres afrundingsdiff 
		$p=0;
		$projektliste='';
		$q = db_select("select distinct(projekt) from ordrelinjer where ordre_id=$id and vare_id >	'0'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$p++;
			$projekt[$p]=$r['projekt'];
			($projektliste)?$projektliste.="<br>".$projekt[$p]:$projektliste=$projekt[$p];
		}
		$projektantal=$p;
		if ($projektantal) db_modify("update openpost set projekt='$projektliste' where id='$openpost_id'",__FILE__ . " linje " . __LINE__);
		
		for ($t=1;$t<=2;$t++)	{	
			for ($p=1;$p<=$projektantal;$p++) {	
				$y=0;
				$bogf_konto = array();
				if ($t==1) {
					$q = db_select("select * from ordrelinjer where ordre_id=$id and posnr>=0 and projekt='$projekt[$p]'",__FILE__ . " linje " . __LINE__);
				} else {
					$q = db_select("select * from ordrelinjer where ordre_id=$id and posnr<0 and projekt='$projekt[$p]'",__FILE__ . " linje " . __LINE__);
				}
				while ($r = db_fetch_array($q)) {
					if ($valutakurs) $maxdif=$maxdif+2; #Og yderligere 2 pr ordrelinje.
					if (!in_array($r['bogf_konto'],$bogf_konto)) {
						$y++;
						$bogf_konto[$y]=$r['bogf_konto'];
						$pos[$y]=$r['posnr'];						
						$pris[$y]=afrund($r['pris']*$r['antal']-$r['pris']*$r['antal']*$r['rabat']/100,2); #20110124 afrund dec aendret fra 3 til 2 saldi_205 ordre_id 997
					}
					else {
						for ($a=1; $a<=$y; $a++) {
							if ($bogf_konto[$a]==$r['bogf_konto']) {
								$tmp= afrund($r['pris']*$r['antal']-$r['pris']*$r['antal']*$r['rabat']/100,2);  #20110124 afrund dec aendret fra 3 til 2 saldi_205 ordre_id 997
								$pris[$a]+=$tmp;
						}
						}		 
					}
				}
				if ($projekt[0] && !$projekt[$p]) $projekt[$p]=$projekt[0];
				$ordrelinjer=$y;
				for ($y=1;$y<=$ordrelinjer;$y++) {
					if ($bogf_konto[$y]) {
						if ($pris[$y]>0) {$debet=$pris[$y];$kredit=0;}
						else {$debet=0; $kredit=$pris[$y]*-1;}	
						$tmp1=$kredit*$valutakurs/100;$tmp2=$debet*$valutakurs/100;					
						if ($t==1 && $valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKK.		
						$debet=afrund($debet,2);
						$kredit=afrund($kredit,2);
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
						$linjesum+=$debet-$kredit;
						if ($pris[$y]) db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$bogf_konto[$y]','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[$p]','$ansat','$id')",__FILE__ . " linje " . __LINE__);
					}
				}
			}
		}
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$r = db_fetch_array(db_select("select box1,box6 from grupper where art='KG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$box1=substr(trim($r['box1']),1,1);
		$smomsnr=substr(trim($r['box6']),1,1);
		if (!$box1)$moms=0;

#################### EU varekoeb moms ################
		if (substr(trim($r['box1']),0,1)=='E') {
		$r = db_fetch_array(db_select("select box1,box2,box3 from grupper where art='EM' and kodenr='$box1'",__FILE__ . " linje " . __LINE__));
			$kmomskto=trim($r['box3']); # Ser lidt forvirrende ud,men den er go nok - fordi koebsmomsen ligger i box 3 v. udenlandsmoms.
			$emomskto=$r['box1'];
			$moms=$sum/100*$r['box2']; #moms af varekoeb i udland beregnes
			if ($moms > 0) {$kredit=$moms; $debet='0';}
			else {$kredit='0'; $debet=$moms*-1;} 
			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKK.		
			$momssum+=$debet-$kredit;
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			if ($moms) {
				db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$emomskto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[$p]','$ansat','$id')",__FILE__ . " linje " . __LINE__);
			}

#################### EU ydelseskoeb moms ################
		} elseif (substr(trim($r['box1']),0,1)=='Y') {
			$r = db_fetch_array(db_select("select box1,box2,box3 from grupper where art='YM' and kodenr='$box1'",__FILE__ . " linje " . __LINE__));
			$kmomskto=trim($r['box3']); # Ser lidt forvirrende ud,men den er go nok - fordi koebsmomsen ligger i box 3 v. udenlandsmoms.
			$emomskto=$r['box1'];
			$moms=$sum/100*$r['box2']; #moms af varekoeb i udland beregnes
			if ($moms > 0) {$kredit=$moms; $debet='0';}
			else {$kredit='0'; $debet=$moms*-1;} 
			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKK.		
			$momssum+=$debet-$kredit;
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			if ($moms) {
				db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$emomskto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[$p]','$ansat','$id')",__FILE__ . " linje " . __LINE__);
			}
####################
		} else {
#cho "$omlev && $smomsnr<br>";
			if ($omlev && $smomsnr) {
#cho "select box1,box2,box3 from grupper where art='SM' and kodenr='$smomsnr'<br>";
			$r = db_fetch_array(db_select("select box1,box2,box3 from grupper where art='SM' and kodenr='$smomsnr'",__FILE__ . " linje " . __LINE__));
				$smomskto=$r['box1'];
#cho "SM $smomskto<br>";
				$smoms=0;
#cho "select * from ordrelinjer where ordre_id='$id' and omvbet='on' and momsfri!='on'<br>";
				$q=db_select("select * from ordrelinjer where ordre_id='$id' and omvbet='on' and momsfri!='on'",__FILE__ . " linje " . __LINE__);
				while($r=db_fetch_array($q)) {
					$linjesum=afrund($r['pris']*$r['antal']-$r['pris']*$r['antal']*$r['rabat']/100,2);
#cho "LS $linjesum<br>";					
					$smoms+=afrund($linjesum*$momssats/100,2);
#cho "MS $smoms<br>";					
				}
			}
			$moms+=$smoms;
#cho "Moms $moms<br>";
			if ($smoms > 0) {$kredit=$smoms; $debet='0';}
			else {$kredit='0'; $debet=$smoms*-1;} 
			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKK.		
			$kredit=afrund($kredit,3);$debet=afrund($debet,3);
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			if ($smoms) {
#cho "SM $smoms D $debet K $kredit<br>";
			$tmp=$beskrivelse." (Omvendt betaling)";	
#cho "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$tmp','$smomskto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id')<br>";			
				db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$tmp','$smomskto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id')",__FILE__ . " linje " . __LINE__);
			}
			$r = db_fetch_array(db_select("select box1 from grupper where art='KM' and kodenr='$box1'",__FILE__ . " linje " . __LINE__));
			$kmomskto=trim($r['box1']);
		}			
		if ($moms > 0) {$debet=$moms; $kredit='0';}
		else {$debet='0'; $kredit=$moms*-1;} 
		if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKK.		
		$kredit=afrund($kredit,3);$debet=afrund($debet,3);
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		$momssum+=$debet-$kredit;
		$moms=afrund($moms,2);
		if ($moms) {
#cho "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$kmomskto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id')<br>";			
			db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$kmomskto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id')",__FILE__ . " linje " . __LINE__);
		}
	
		db_modify("update ordrer set status=4,valutakurs=$valutakurs where id=$id",__FILE__ . " linje " . __LINE__);
		db_modify("delete from ordrelinjer where ordre_id=$id and posnr < 0",__FILE__ . " linje " . __LINE__);
	}
	$d_kontrol=afrund($d_kontrol,2);
	$k_kontrol=afrund($k_kontrol,2);
	if ($diff=afrund($d_kontrol-$k_kontrol,2)) {
		if ($valuta!='DKK' && abs($diff)<=$maxdif) { #Der maa max vaere en afvigelse paa 1 oere pr ordrelinje m fremmed valuta;
			$debet=0; $kredit=0;
			if ($diff<0) $debet=$diff*-1;
			else $kredit=$diff;
			$debet=afrund($debet,2);
			$kredit=afrund($kredit,2);
			db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$difkto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id')",__FILE__ . " linje " . __LINE__);

		} else {
			print "<BODY onLoad=\"javascript:alert('Der er konstateret en uoverensstemmelse i posteringssummen, kontakt DANOSOFT p&aring; telefon 4690 2208')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	} 
#print "<BODY onLoad=\"javascript:alert('xxxxxxxxxxxxxxxxxxxx')\">";
#xit;
#	genberegn($regnaar);
}

?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
