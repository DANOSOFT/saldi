<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/modtag.php --- patch 4.1.1 --- 2025.03.22 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 2013.08.30 Fejl v. "interne shops" (Rotary) de der blev forsøgt kald til ikke eksisterende url.Søn 20130830
// 2013.10.01 Opdat_beholdning blev ikke åbnet v. webshop.
// 20141005 Div i forbindelse med omvendt betalingspligt, samt nogle generelle ændringer således at varereturnering nu bogføres
//	som negativt køb og ikke som salg.
// 20141005 Ændret + til - pga fejl ved netagiv modtagelse på samme lev ordre.
// 20150118 sætter kobsdate hvis ikke sat. Søg kobsdate.
// 20161022 PHR - tilretning iht flere afd pr lager. 20161022
// 20170123 PHR - Diverse i forhold til varianter
// 20181003 PHR - Lille udefinerbar rettelse
// 20221106 PHR - Various changes to fit php8 / MySQLi
// 20231025 PHR Added call to sync_shop_vare and log to stocklog.
// 20240626 PHR Added 'fiscal_year' in queries
// 20250207 PHR Corrected error in 'bogf_konto' as is used wrong account !

@session_start();
$s_id=session_id();

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
$art=$row['art'];
$kred_ord_id=$row['kred_ord_id'];
$ref=$row['ref'];
if ($row['status']>2) {
	print "<BODY onLoad=\"fejltekst('Hmmm - har du brugt browserens opdater eller tilbageknap???')\">";
	 #	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
} elseif (!$row['levdate']) {
	print "<BODY onLoad=\"fejltekst('Leveringsdato ikke udfyldt')\">";
	 #	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
}
elseif ($row['levdate']<$row['ordredate']) {
	print "<BODY onLoad=\"fejltekst('Leveringsdato er f&oslash;r ordredato')\">";
	 #	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
} else $fejl=0;

$levdate=$row['levdate'];
list ($year, $month, $day) = explode ('-', $row['levdate']);
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
			$variant_id[$x]=$row['variant_id'];
			if (!$variant_id[$x]) $variant_id[$x] = 0; 
			$lager[$x]=$row['lager']*1;
			if (!$lager[$x]) $lager[$x]=1;
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
		$qtxt="select id, gruppe, beholdning from varer where id='$vare_id[$x]'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$gruppe[$x]=$r['gruppe'];
		$vare_beholdning=$r['beholdning']+$leveres[$x];
		if ($variant_id[$x]) {
			$qtxt="select variant_beholdning from variant_varer where id = '$variant_id[$x]'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$variant_beholdning=$r['variant_beholdning']+$leveres[$x];
		}
		if (($vare_id[$x])&&($leveres[$x]!=0)) {
			$qtxt = "select * from grupper where art='VG' and kodenr='$gruppe[$x]' and fiscal_year = '$regnaar'";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			$r = db_fetch_array($q);
			$box1=trim($r['box1']); $box2=trim($r['box2']); $box3=trim($r['box3']); $box4=trim($r['box4']); $box8=trim($r['box8']); $box9=trim($r['box9']);
			if ($box8!='on') { # Dvs varen er IKKE lagerfoert.
				if (!$box3) {
					print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x] (Pos nr: $posnr[$x]) er ikke tilnykttet nogen varegruppe, modtagelse afbrudt')\">";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
					exit;
				}
				$qtxt = "update ordrelinjer set bogf_konto='$box3' where id='$linje_id[$x]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
# PHR - Pris fjernet 06.04.08 - Prisen skal ikke saettes ved modtagelse
				db_modify("insert into batch_kob(vare_id,variant_id,linje_id,kobsdate,ordre_id,antal,lager) values ('$vare_id[$x]','$variant_id[$x]','$linje_id[$x]','$levdate','$id','$leveres[$x]','$lager[$x]')",__FILE__ . " linje " . __LINE__);
			} else { #hvis varen ER lagerfoert
				db_modify("update varer set beholdning='$vare_beholdning' where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
				if ($variant_id[$x]) db_modify("update variant_varer set variant_beholdning='$variant_beholdning' where id='$variant_id[$x]'",__FILE__ . " linje " . __LINE__);
				 if (!$lager[$x]) { #20161022
					$r=db_fetch_array(db_select("select afd from ansatte where navn = '$ref'",__FILE__ . " linje " . __LINE__));
					if ($afd=$r['afd']) {
						$r=db_fetch_array(db_select("select box1 from grupper where kodenr='$afd' and art = 'AFD'",__FILE__ . " linje " . __LINE__));
						$lager[$x]=$r['box1'];
						if (!$lager[$x]) {
							$r=db_fetch_array(db_select("select kodenr from grupper where box1='$afd' and art='LG'",__FILE__ . " linje " . __LINE__));
							$lager[$x]=$r['kodenr'];
						}
					}
				}
				
				$lager[$x]*=1;
				$qtxt="select * from lagerstatus where vare_id='$vare_id[$x]' and variant_id='$variant_id[$x]'";
				($lager > 1)?$qtxt.=" and lager = '$lager[$x]'":$qtxt.=" and lager <= '1";
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				if ($r=db_fetch_array($q)) $qtxt="update lagerstatus set beholdning=$r[beholdning]+$leveres[$x] where id=$r[id]";
				else $qtxt="insert into lagerstatus (vare_id, variant_id, lager, beholdning) values ('$vare_id[$x]', '$variant_id[$x]', '$lager[$x]', '$leveres[$x]')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
				if ($box9=='on') {
					if ($leveres[$x]<0) returnering($id,$linje_id[$x],$leveres[$x],$vare_id[$x], $variant_id[$x],$pris[$x],$serienr[$x],$lager[$x],$kred_linje_id[$x],$levdate);#Varereturnering
					else 	reservation($linje_id[$x],$leveres[$x],$vare_id[$x],$serienr[$x],$lager[$x]);
				} else {
					$qtxt = "update ordrelinjer set bogf_konto='$box3' where id='$linje_id[$x]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#					if ($fifo) {
#						$rest=$leveres[$x]-($leveres[$x]-$beholdning);
#						if ($rest<0) $rest=0;
#						elseif ($rest>$leveres[$x])$rest=$leveres[$x];	
						db_modify("insert into batch_kob(vare_id,variant_id,linje_id,kobsdate,ordre_id,antal,rest,lager) values ('$vare_id[$x]','$variant_id[$x]','$linje_id[$x]','$levdate','$id','$leveres[$x]','$leveres[$x]','$lager[$x]')",__FILE__ . " linje " . __LINE__);
#					} else {
					#Pris fjernet fra nedenstaende 06.04.08 - Prisen skal ikke saettes ved modtagelse
#						db_modify("insert into batch_kob(vare_id, linje_id, kobsdate, ordre_id, antal,rest) values ($vare_id[$x],$linje_id[$x],'$levdate','$id','$leveres[$x]','$leveres[$x]')",__FILE__ . " linje " . __LINE__);
#					}
				}
			}
			db_modify("update ordrelinjer set leveres=0 where id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
			$qtxt="select id,var_value from settings where var_name = 'confirmStockChange' and var_grp = 'items'";
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="select ordrenr from ordrer where id = '$id'";
				($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$ordreNr=$r['ordrenr']:$ordreNr=NULL;
				$qtxt = "select ansatte.navn,ansatte.initialer from ansatte,brugere where brugere.id = '$bruger_id' ";
				$qtxt.= "and ansatte.id = brugere.ansat_id";
				if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
					$initials = db_escape_string(trim(substr($r['initialer'],0,10)));
				} else {
					$initials = '';
				}
				$userName = db_escape_string($brugernavn,0,25);
				$qtxt = "insert into stocklog (item_id,username,initials,correction,reason,logtime) values ";
				$qtxt.= "('$vare_id[$x]','$userName','$initials','$leveres[$x]','indkøbsordre $ordreNr','".date('U')."')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			sync_shop_vare($vare_id[$x],$variant_id[$x],$lager[$x]);
/*
			$qtxt = "select box2 from grupper where art = 'DIV' and kodenr = '5'";
echo "$qtxt<br>";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$shopurl=trim($r['box2']);
echo "$shopurl<br>";
			if (strlen($shopurl)>1) { #20131001
				$qtxt="select beholdning,publiceret from varer where id = '$vare_id[$x]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($r['publiceret']) {
					$shop_beholdning=$r['beholdning'];
					if ($variant_id[$x]) {
						$qtxt="select variant_beholdning from variant_varer where id = '$variant_id[$x]'";
						$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$shop_beholdning=$r['variant_beholdning'];
					}
					$qtxt="select shop_id from shop_varer where saldi_id = '$variant_id[$x]'";
					$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$shop_beholdning=$r['variant_beholdning'];
					$r=db_fetch_array(db_select("select sum(ordrelinjer.antal-ordrelinjer.leveret) as antal from ordrer,ordrelinjer where ordrelinjer.vare_id = '$vare_id[$x]' and ordrelinjer.variant_id = '$variant_id[$x]' and ordrelinjer.ordre_id = ordrer.id and (ordrer.art='DO' or ordrer.art='DK') and (ordrer.status='1' or ordrer.status='2') and ordrer.id!='$id'",__FILE__ . " linje " . __LINE__));
					$shop_beholdning-=$r['antal'];
					$r=db_fetch_array($q=db_select("select shop_id from shop_varer where saldi_id='$vare_id[$x]' and saldi_variant='$variant_id[$x]'",__FILE__ . " linje " . __LINE__));
					$shop_id=$r['shop_id'];
					$url=$shopurl."/opdat_beholdning.php?vare_id=$vare_id[$x]&shop_id=$shop_id&beholdning=$shop_beholdning";
					print "<body onload=\"javascript:window.open('$url','opdat:beholdning');\">";
				}
			}
*/
		}
	}
	for ($x=1; $x<=$linjeantal; $x++) {
		if ($leveres[$x]<0) { #returnering af vare
		$qtxt="select * from batch_kob where ordre_id='$id' and vare_id='$vare_id[$x]' and linje_id='$linje_id[$x]'";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) { 
				$rest=$r['rest'];	
				if ($rest) {
					$qtxt="select * from batch_kob where ordre_id != '$id' and vare_id = '$vare_id[$x]' and rest > '0' and linje_id != '$linje_id[$x]'";
					($lager > 1)?$qtxt.=" and lager = '$lager[$x]'":$qtxt.=" and lager <= '1";
					$qtxt.=" order by id";
					$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
					if ($r2['rest']+$rest>=0){
							$ny_rest=$r['rest']-$rest; #20141014
							$rest=0;
						}	else {
							$ny_rest=0;
							$rest=$rest+$r['rest'];
						}
						($r2['kobsdate'])?$kobsdate=$r2['kobsdate']:$kobsdate=$levdate; #20150118
						$qtxt="update batch_kob set kobsdate='$kobsdate',rest='$ny_rest' where id = '$r2[id]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						($r['kobsdate'])?$kobsdate=$r['kobsdate']:$kobsdate=$levdate; #20150118
						$qtxt="update batch_kob set kobsdate='$kobsdate',rest='$rest' where id = '$r[id]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
				if ($rest) {
					$qtxt="select * from batch_kob where ordre_id!='$id' and vare_id='$vare_id[$x]' and rest>'0' and linje_id!='$linje_id[$x]'";
					($lager > 1)?$qtxt.=" and lager = '$lager[$x]'":$qtxt.=" and lager <= '1";
					$qtxt.=" order by id";
					$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
						if ($rest) {
						if ($r2['rest']+$rest>=0){
							$ny_rest=$r2['rest']+$rest; #20141014

							$rest=0;
							}	else {
							$ny_rest=0;
							$rest=$rest+$r2['rest'];
						}
						($r2['kobsdate'])?$kobsdate=$r2['kobsdate']:$kobsdate=$levdate; #20150118
						$qtxt="update batch_kob set kobsdate='$kobsdate',rest='$ny_rest' where id = $r2[id]<br>";
#xit;
						db_modify("update batch_kob set kobsdate='$kobsdate',rest='$ny_rest' where id = $r2[id]",__FILE__ . " linje " . __LINE__);
						($r['kobsdate'])?$kobsdate=$r['kobsdate']:$kobsdate=$levdate; #20150118
						$qtxt="update batch_kob set kobsdate='$kobsdate',rest='$rest' where id = $r[id]";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}}
				}
			}
		} 
		elseif ($leveres[$x]>0) { #Modtagelse af vare
		# finder tidligere modtagelser af samme vare på samme linje i samme ordre.
		$q=db_select("select * from batch_kob where ordre_id='$id' and vare_id='$vare_id[$x]' and linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) { 
				$rest=$r['rest'];	
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
					$qtxt="select * from batch_kob where antal='0' and ordre_id='0' and vare_id='$vare_id[$x]' and rest<'0' and linje_id!='$linje_id[$x]'";
					($lager > 1)?$qtxt.=" and lager = '$lager[$x]'":$qtxt.=" and lager <= '1";
					$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
						if ($rest) {
						if ($r2['rest']+$rest>=0){
							$ny_rest=0;
							$ny_antal=abs($r2['rest']);
							$rest=$r2['rest']+$rest;
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
					$q2=db_select("select * from batch_kob where ordre_id!='0' and ordre_id!='$id' and vare_id='$vare_id[$x]' and rest<'0' and linje_id!='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
						if ($rest) {
							if ($r2['rest']+$rest>=0){
								$ny_rest=0;
								$rest=$r2['rest']+$rest;
							}	else {
								$ny_rest=$rest+$r2['rest'];
								$rest=0;
							}
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

function reservation($linje_id, $leveres, $vare_id, $serienr,$lager) {
	global $id;
	global $levdate;

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

function returnering ($id,$linje_id,$leveres,$vare_id, $variant_id,$pris, $serienr,$lager,$kred_linje_id, $levdate) {
	global $id;
	$rest=$leveres;

	$y=0;   

	if (!$kred_linje_id) {
		print "<BODY onLoad=\"fejltekst('Batch ikke valgt')\">";
		exit;
	}
	$query = db_select("select * from batch_kob where linje_id=$kred_linje_id",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$batch_kob_id=$row['id'];
		$batch_antal=$row['antal'];
		$batch_rest=$row['rest'];
		$batch_pris=$row['pris'];
		if ($batch_rest+$leveres>=0) {
			db_modify("update batch_kob set rest=$batch_rest+$leveres where id=$batch_kob_id",__FILE__ . " linje " . __LINE__);
			$rest=$rest-$batch_rest;
			if ($serienr) {
				db_modify("update serienr set batch_kob_id=-$batch_kob_id where batch_kob_id=$batch_kob_id and kobslinje_id=-$kred_linje_id",__FILE__ . " linje " . __LINE__);
			}
		}
	}
	db_modify("insert into batch_kob(linje_id, ordre_id, vare_id, variant_id, kobsdate,antal,rest,lager) values ('$linje_id', '$id', '$vare_id', '$variant_id','$levdate', '$leveres','0',$lager)",__FILE__ . " linje " . __LINE__);
}


#	print "<a href=ordre.php?id=$id accesskey=L>Luk</a>";
print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";

?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>