<?php
	@session_start();
	$s_id=session_id();
// -------kreditor/modtag.php-------patch 3.4.9-------2015.01.18-----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaetelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
//
// 2013.08.30 Fejl v. "interne shops" (Rotary) de der blev forsøgt kald til ikke eksisterende url.Søn 20130830
// 2013.10.01 Opdat_beholdning blev ikke åbnet v. webshop.
// 20141005 Div i forbindelse med omvendt betalingspligt, samt nogle generelle ændringer således at varereturnering nu bogføres
//	som negativt køb og ikke som salg.
// 20141005 Ændret + til - pga fejl ved netagiv modtagelse på samme lev ordre.
// 20150118 sætter kobsdate hvis ikke sat. Søg kobsdate.

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$id=$_GET['id'];
	
?>
<script language="JavaScript">
<!--
function fejltekst(tekst) {
	alert(tekst);
	window.location.replace("ordre.php?id=<?php echo $id?>");
}
-->
</script>
<?php

$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
if ($row = db_fetch_array($query)) {
	$year=substr(str_replace(" ","",$row['box2']),-2);
	$aarstart=str_replace(" ","",$year.$row['box1']);
	$year=substr(str_replace(" ","",$row['box4']),-2);
	$aarslut=str_replace(" ","",$year.$row['box3']);
}
$r=db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
$fifo=$r['box6'];

$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$art=$row[art];
$kred_ord_id=$row[kred_ord_id];
$lager=$row[lager];
$ref=$row[ref];
if ($row[status]>2) {
	print "<BODY onLoad=\"fejltekst('Hmmm - har du brugt browserens opdater eller tilbageknap???')\">";
	 #	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
} elseif (!$row[levdate]) {
	print "<BODY onLoad=\"fejltekst('Leveringsdato ikke udfyldt')\">";
	 #	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
}
elseif ($row[levdate]<$row[ordredate]) {
	print "<BODY onLoad=\"fejltekst('Leveringsdato er f&oslash;r ordredato')\">";
	 #	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
} else $fejl=0;

$levdate=$row[levdate];
list ($year, $month, $day) = explode ('-', $row[levdate]);
$year=substr($year,-2);
$ym=$year.$month;
if (($ym<$aarstart)||($ym>$aarslut)) {
	print "<BODY onLoad=\"fejltekst('Leveringsdato udenfor regnskabs&aring;r')\">";
	 #	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
}

if ($fejl==0) {
	transaktion("begin");
	$x=0;
	$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if (($row['posnr']>0)&&(strlen(trim(($row['varenr'])))>0)) {
			$x++;
			$posnr[$x]=$row['posnr'];
			$linje_id[$x]=$row['id'];
			$kred_linje_id[$x]=$row['kred_linje_id']*1;
			$vare_id[$x]=$row['vare_id'];
			$varenr[$x]=$row['varenr'];
			$vare_id[$x]=$row['vare_id'];
			$leveres[$x]=$row['leveres'];
#			$pris[$x]=$row['pris']-($row['pris']*$row['rabat']/100);
			$serienr[$x]=trim($row['serienr']);
			$variant_id[$x]=trim($row['variant_id']);
		}
	}
	$linjeantal=$x;
	for ($x=1; $x<=$linjeantal; $x++) {
		if (($leveres[$x]>0)&&($serienr[$x])&&($art!='KK')){
			$sn_antal[$x]=0; 
			$query = db_select("select * from serienr where kobslinje_id = '$linje_id[$x]' and batch_kob_id=0",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)){
				$sn_antal[$x]=$sn_antal[$x]+1000;
				$y=$sn_antal[$x]+$x;
				$sn_id[$y]=$row['id'];
			}
			if ($leveres[$x]>$sn_antal[$x]/1000){
				 print "<BODY onLoad=\"fejltekst('Serienumre ikke udfyldt')\">";
				exit;
			}
		}
		if (($leveres[$x]<0)&&($serienr[$x])){
			$sn_antal[$x]=0; 
			if ($art=='KK') $query = db_select("select * from serienr where kobslinje_id = -$kred_linje_id[$x] and batch_salg_id<=0",__FILE__ . " linje " . __LINE__);
			else $query = db_select("select * from serienr where salgslinje_id = $linje_id[$x] and batch_salg_id<=0",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				$sn_antal[$x]=$sn_antal[$x]+1000;
				$y=$sn_antal[$x]+$x;
				$sn_id[$y]=$row['id'];
			}
			if ($leveres[$x]!=$sn_antal[$x]/-1000) {
				 print "<BODY onLoad=\"fejltekst('Serienumre ikke valgt')\">";
				 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				 exit;
			}
		}
	}
	for ($x=1; $x<=$linjeantal; $x++) {
		$sn_start=0;
		$query = db_select("select id, gruppe, beholdning from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
#		$vare_id[$x]=$row[id];
		$gruppe[$x]=$row['gruppe'];
		$beholdning=$row['beholdning']+$leveres[$x];
		if (($vare_id[$x])&&($leveres[$x]!=0)) {
			$query = db_select("select * from grupper where art='VG' and kodenr='$gruppe[$x]'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$box1=trim($row['box1']); $box2=trim($row['box2']); $box3=trim($row['box3']); $box4=trim($row['box4']); $box8=trim($row['box8']); $box9=trim($row['box9']);
#cho "box8 $box8<br>";
			if ($box8!='on') { # Dvs varen er IKKE lagerfoert.
				if (!$box4) {
					print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x] (Pos nr: $posnr[$x]) er ikke tilnykttet nogen varegruppe, modtagelse afbrudt')\">";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				}
				db_modify("update ordrelinjer set bogf_konto='$box4' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
# lager fjernet fra nedenstaaende linje 030306 - PHR - Pris fjernet 06.04.08 - Prisen skal ikke saettes ved modtagelse
				db_modify("insert into batch_kob(vare_id, linje_id, kobsdate, ordre_id, antal) values ($vare_id[$x], $linje_id[$x], '$levdate', $id, $leveres[$x])",__FILE__ . " linje " . __LINE__);
			} else { #hvis varen ER lagerfoert
				db_modify("update varer set beholdning='$beholdning' where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
				if ($variant_id[$x]) db_modify("update variant_varer set variant_beholdning=variant_beholdning+$leveres[$x] where id='$variant_id[$x]'",__FILE__ . " linje " . __LINE__);
				 if (!$lager) {
					if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'",__FILE__ . " linje " . __LINE__))) {
						if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) $lager=$row['kodenr'];
					}
					 $lager=$lager*1;
				}
				$query = db_select("select * from lagerstatus where vare_id='$vare_id[$x]' and lager='$lager'",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) db_modify("update lagerstatus set beholdning=$row[beholdning]+$leveres[$x] where id=$row[id]",__FILE__ . " linje " . __LINE__);
				else db_modify("insert into lagerstatus (vare_id, lager, beholdning) values ($vare_id[$x], $lager, $leveres[$x])",__FILE__ . " linje " . __LINE__);
				if ($box9=='on') {
					if ($leveres[$x]<0) returnering ($id, $linje_id[$x], $leveres[$x], $vare_id[$x],$pris[$x], $serienr[$x], $kred_linje_id[$x], $levdate);#Varereturnering
					else 	reservation($linje_id[$x], $leveres[$x], $vare_id[$x], $serienr[$x]);
				} else {
					db_modify("update ordrelinjer set bogf_konto='$box4' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
#					if ($fifo) {
#						$rest=$leveres[$x]-($leveres[$x]-$beholdning);
#						if ($rest<0) $rest=0;
#						elseif ($rest>$leveres[$x])$rest=$leveres[$x];	
#cho __LINE__." insert into batch_kob(vare_id,linje_id,kobsdate,ordre_id,antal,rest) values ($vare_id[$x],$linje_id[$x],'$levdate','$id','$leveres[$x]','$leveres[$x]')<br>";
						db_modify("insert into batch_kob(vare_id,linje_id,kobsdate,ordre_id,antal,rest) values ($vare_id[$x],$linje_id[$x],'$levdate','$id','$leveres[$x]','$leveres[$x]')",__FILE__ . " linje " . __LINE__);
#					} else {
					#Pris fjernet fra nedenstaende 06.04.08 - Prisen skal ikke saettes ved modtagelse
#cho "insert into batch_kob(vare_id, linje_id, kobsdate, ordre_id, antal,rest) values ($vare_id[$x],$linje_id[$x],'$levdate','$id','$leveres[$x]','$leveres[$x]')<br>";
#						db_modify("insert into batch_kob(vare_id, linje_id, kobsdate, ordre_id, antal,rest) values ($vare_id[$x],$linje_id[$x],'$levdate','$id','$leveres[$x]','$leveres[$x]')",__FILE__ . " linje " . __LINE__);
#					}
				}
			}
			db_modify("update ordrelinjer set leveres=0 where id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select box2 from grupper where art = 'DIV' and kodenr = '5' ",__FILE__ . " linje " . __LINE__));
			$shopurl=trim($r['box2']);
			if (strlen($shopurl)>1) { #20131001
				$r=db_fetch_array(db_select("select beholdning,publiceret from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
				if ($r['publiceret']) {
					$shop_beholdning=$r['beholdning'];
					$r=db_fetch_array(db_select("select sum(ordrelinjer.antal-ordrelinjer.leveret) as antal from ordrer,ordrelinjer where ordrelinjer.vare_id = '$vare_id[$x]' and ordrelinjer.ordre_id = ordrer.id and (ordrer.art='DO' or ordrer.art='DK') and (ordrer.status='1' or ordrer.status='2') and ordrer.id!='$id'",__FILE__ . " linje " . __LINE__));
					$shop_beholdning-=$r['antal'];
					$r=db_fetch_array($q=db_select("select shop_id from shop_varer where saldi_id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
					$shop_id=$r['shop_id'];
					$url=$shopurl."/opdat_beholdning.php?vare_id=$vare_id[$x]&shop_id=$shop_id&beholdning=$shop_beholdning";
					print "<body onload=\"javascript:window.open('$url','opdat:beholdning');\">";
				}
			}
		}
	}
	for ($x=1; $x<=$linjeantal; $x++) {
		if ($leveres[$x]<0) { #returnering af vare
#cho __LINE__." L $leveres[$x]<br>";
#cho __LINE__." select * from batch_kob where ordre_id='$id' and vare_id='$vare_id[$x]' and linje_id='$linje_id[$x]'<br>";
		$q=db_select("select * from batch_kob where ordre_id='$id' and vare_id='$vare_id[$x]' and linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) { 
				$rest=$r['rest'];	
#cho  __LINE__." Rest $rest<br>"; 
				if ($rest) {
#cho __LINE__." select * from batch_kob where ordre_id='$id' and vare_id='$vare_id[$x]' and rest>'0' and linje_id!='$linje_id[$x]'<br>";
					$q2=db_select("select * from batch_kob where ordre_id='$id' and vare_id='$vare_id[$x]' and rest>'0' and linje_id!='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
#cho __LINE__." $r2[rest]+$rest>=0<br>";
					if ($r2['rest']+$rest>=0){
							$ny_rest=$r['rest']-$rest; #20141014
							$rest=0;
						}	else {
							$ny_rest=0;
							$rest=$rest+$r['rest'];
						}
#cho __LINE__." update batch_kob set rest='$ny_rest' where id = $r2[id]<br>";
#cho __LINE__." update batch_kob set rest='$rest' where id = $r[id]<br>";
						($r2['kobsdate'])?$kobsdate=$r2['kobsdate']:$kobsdate=$levdate; #20150118
						db_modify("update batch_kob set kobsdate='$kobsdate',rest='$ny_rest' where id = $r2[id]",__FILE__ . " linje " . __LINE__);
						($r['kobsdate'])?$kobsdate=$r['kobsdate']:$kobsdate=$levdate; #20150118
						db_modify("update batch_kob set kobsdate='$kobsdate',rest='$rest' where id = $r[id]",__FILE__ . " linje " . __LINE__);
					}
				}
				if ($rest) {
#cho __LINE__." select * from batch_kob where ordre_id!='$id' and vare_id='$vare_id[$x]' and rest>'0' and linje_id!='$linje_id[$x]'<br>";
					$q2=db_select("select * from batch_kob where ordre_id!='$id' and vare_id='$vare_id[$x]' and rest>'0' and linje_id!='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
						if ($rest) {
#cho __LINE__." $r2[rest]+$rest>=0<br>";
						if ($r2['rest']+$rest>=0){
#cho __LINE__." $ny_rest=$r2[rest]+$rest<br>";
							$ny_rest=$r2['rest']-$rest; #20141014
							$rest=0;
							}	else {
							$ny_rest=0;
							$rest=$rest+$r2['rest'];
						}
#cho __LINE__." update batch_kob set rest='$ny_rest' where id = $r2[id]<br>";
#cho __LINE__." update batch_kob set rest='$rest' where id = $r[id]<br>";
						($r2['kobsdate'])?$kobsdate=$r2['kobsdate']:$kobsdate=$levdate; #20150118
						db_modify("update batch_kob set kobsdate='$kobsdate',rest='$ny_rest' where id = $r2[id]",__FILE__ . " linje " . __LINE__);
						($r['kobsdate'])?$kobsdate=$r['kobsdate']:$kobsdate=$levdate; #20150118
						db_modify("update batch_kob set kobsdate='$kobsdate',rest='$rest' where id = $r[id]",__FILE__ . " linje " . __LINE__);
					}}
				}
			}
		} 
		elseif ($leveres[$x]>0) { #Modtagelse af vare
		# finder tidligere modtagelser af samme vare på samme linje i samme ordre.
		$q=db_select("select * from batch_kob where ordre_id='$id' and vare_id='$vare_id[$x]' and linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) { 
				$rest=$r['rest'];	
#cho __LINE__." Rest $rest<br>";
				if ($rest) { #Finder andre linjer i samme ordre med samme vare hvor varen er solgt inden varenodtagelse. 
					$q2=db_select("select * from batch_kob where ordre_id='$id' and vare_id='$vare_id[$x]' and rest<'0' and linje_id!='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
						if ($rest+$r2['rest']>=0){
							$ny_rest=+$rest+$r2['rest'];
							$rest=0;
						}	else {
							$ny_rest=0;
							$rest=$rest+$r2['rest'];
						}
						($r2['kobsdate'])?$kobsdate=$r2['kobsdate']:$kobsdate=$levdate; #20150118
						db_modify("update batch_kob set kobsdate='$kobsdate',rest='$ny_rest' where id = $r2[id]",__FILE__ . " linje " . __LINE__);
						($r['kobsdate'])?$kobsdate=$r['kobsdate']:$kobsdate=$levdate; #20150118
						db_modify("update batch_kob set kobsdate='$kobsdate',rest='$rest' where id = $r[id]",__FILE__ . " linje " . __LINE__);
					}
				}
				if ($rest) {
#cho __LINE__." Rest $rest<br>";
					$q2=db_select("select * from batch_kob where antal='0' and ordre_id='0' and vare_id='$vare_id[$x]' and rest<'0' and linje_id!='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
						if ($rest) {
#cho __LINE__." $r2[rest]+$rest>=0<br>";
						if ($r2['rest']+$rest>=0){
							$ny_rest=0;
							$ny_antal=abs($r2['rest']);
#cho __LINE__." $rest=$r[rest]+$rest<br>";
							$rest=$r2['rest']+$rest;
#cho __LINE__." $ny_rest : $rest<br>";
#cho __LINE__." update batch_kob set rest='$ny_rest' where id = $r2[id]<br>";
#cho __LINE__." update batch_kob set rest='$rest' where id = $r[id]<br>";
						($r2['kobsdate'])?$kobsdate=$r2['kobsdate']:$kobsdate=$levdate; #20150118
						db_modify("update batch_kob set kobsdate='$kobsdate',antal='$ny_antal',ordre_id='$id',linje_id='$linje_id[$x]',rest='$ny_rest' where id = $r2[id]",__FILE__ . " linje " . __LINE__);
						($r['kobsdate'])?$kobsdate=$r['kobsdate']:$kobsdate=$levdate; #20150118
						db_modify("update batch_kob set kobsdate='$kobsdate',antal=antal-$ny_antal,rest='$rest' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
						}	
/*
						else {
							$ny_rest=$rest+$r2['rest'];
							$rest=0;
						}
*/						
					}}
				}
				if ($rest) {
#cho __LINE__." Rest $rest<br>";
					$q2=db_select("select * from batch_kob where ordre_id!='0' and ordre_id!='$id' and vare_id='$vare_id[$x]' and rest<'0' and linje_id!='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
						if ($rest) {
#cho __LINE__." $r2[rest]+$rest>=0<br>";
							if ($r2['rest']+$rest>=0){
								$ny_rest=0;
#cho __LINE__." $rest=$r[rest]+$rest<br>";
								$rest=$r2['rest']+$rest;
#cho __LINE__." $ny_rest : $rest<br>";
							}	else {
								$ny_rest=$rest+$r2['rest'];
								$rest=0;
							}
#cho __LINE__." update batch_kob set rest='$ny_rest' where id = $r2[id]<br>";
#cho __LINE__." update batch_kob set rest='$rest' where id = $r[id]<br>";
							($r2['kobsdate'])?$kobsdate=$r2['kobsdate']:$kobsdate=$levdate; #20150118
							db_modify("update batch_kob set kobsdate='$kobsdate',rest='$ny_rest' where id = $r2[id]",__FILE__ . " linje " . __LINE__);
							($r['kobsdate'])?$kobsdate=$r['kobsdate']:$kobsdate=$levdate; #20150118
							db_modify("update batch_kob set kobsdate='$kobsdate',rest='$rest' where id = $r[id]",__FILE__ . " linje " . __LINE__);
						}
					}
				}
			}
		}
#*/		
	}
	transaktion("commit");
#xit;
} #endif ($fejl==0);

function reservation($linje_id, $leveres, $vare_id, $serienr) {
	global $id;
	global $levdate;
	global $lager;

	$res_sum=0;
	$query = db_select("select antal from reservation where linje_id=$linje_id and batch_salg_id<0",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {$res_sum=$res_sum+$row['antal'];} 
	if ($leveres<$res_sum) {
		print "<BODY onLoad=\"fejltekst('Der er reserveret flere varer end der modtages - foretag proiritering')\">";
		exit;
	} 
	$res_sum=0;
	$y=0;
	$query = db_select("select batch_kob_id, antal, batch_salg_id from reservation where linje_id=$linje_id",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$batch_kob_id=$row[batch_kob_id];
		if ($row[batch_salg_id]>0) {
			$y++;
			$res_antal[$y]=$row[antal];
			$res_sum=$res_sum+$row[antal];
		}
	}
	$res_linje_antal=$y;
	$rest=$leveres-$res_sum;
	if (!$batch_kob_id) {
		db_modify("insert into batch_kob(linje_id, ordre_id, vare_id, kobsdate, antal, rest, lager) values ($linje_id, $id, $vare_id, '$levdate', $leveres, $rest, $lager)",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array(db_select("select id from batch_kob where linje_id=$linje_id and ordre_id=$id and kobsdate='$levdate' and	antal=$leveres and rest=$rest",__FILE__ . " linje " . __LINE__));
		$batch_kob_id=$row[id];								
	} 
	else {
		db_modify("update batch_kob set kobsdate='$levdate', ordre_id=$id, vare_id=$vare_id, antal=$leveres, rest=$rest where id=$batch_kob_id",__FILE__ . " linje " . __LINE__);
	}
	db_modify("delete from reservation where batch_kob_id=$batch_kob_id and linje_id=$linje_id",__FILE__ . " linje " . __LINE__); 
	$query = db_select("select batch_salg_id from reservation where linje_id=$linje_id and batch_salg_id<0",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		db_modify("update reservation set linje_id=$row[batch_salg_id]*-1, batch_kob_id=$batch_kob_id where batch_salg_id=$row[batch_salg_id]",__FILE__ . " linje " . __LINE__); 
	}
	db_modify("update reservation set batch_salg_id = 0 where batch_kob_id =$batch_kob_id",__FILE__ . " linje " . __LINE__); 

	if ($serienr) {
		db_modify("update serienr set batch_kob_id=$batch_kob_id where kobslinje_id=$linje_id",__FILE__ . " linje " . __LINE__);
	}
}

function returnering ($id, $linje_id, $leveres, $vare_id, $pris, $serienr, $kred_linje_id, $levdate)
{
	global $id;
	$rest=$leveres;

	$y=0;

	if (!$kred_linje_id) {
		print "<BODY onLoad=\"fejltekst('Batch ikke valgt')\">";
		exit;
	}
	$query = db_select("select * from batch_kob where linje_id=$kred_linje_id",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$batch_kob_id=$row[id];
		$batch_antal=$row[antal];
		$batch_rest=$row[rest];
		$batch_pris=$row['pris'];
		if ($batch_rest+$leveres>=0) {
			db_modify("update batch_kob set rest=$batch_rest+$leveres where id=$batch_kob_id",__FILE__ . " linje " . __LINE__);
			$rest=$rest-$batch_rest;
			if ($serienr) {
				db_modify("update serienr set batch_kob_id=-$batch_kob_id where batch_kob_id=$batch_kob_id and kobslinje_id=-$kred_linje_id",__FILE__ . " linje " . __LINE__);
			}
		}
	}
	db_modify("insert into batch_kob(linje_id, ordre_id, vare_id, kobsdate, antal, rest) values ($linje_id, $id, $vare_id, '$levdate', $leveres, 0)",__FILE__ . " linje " . __LINE__);
}


#	print "<a href=ordre.php?id=$id accesskey=L>Luk</a>";
print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";

?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
