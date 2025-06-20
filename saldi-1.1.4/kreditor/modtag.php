<?php
	@session_start();
	$s_id=session_id();
// ----------------------------------kreditor/modtag.php-------patch 1.1.0------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaetelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
// 
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/usdate.php");
include("../includes/dkdecimal.php");
include("../includes/db_query.php");

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

$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'");
if ($row = db_fetch_array($query)) {
	$year=substr(str_replace(" ","",$row['box2']),-2);
	$aarstart=str_replace(" ","",$year.$row['box1']);
	$year=substr(str_replace(" ","",$row['box4']),-2);
	$aarslut=str_replace(" ","",$year.$row['box3']);
}

$query = db_select("select * from ordrer where id = '$id'");
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
list ($year, $month, $day) = split ('-', $row[levdate]);
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
	$query = db_select("select * from ordrelinjer where ordre_id = '$id'");
	while ($row = db_fetch_array($query)) {
		if (($row[posnr]>0)&&(strlen(trim(($row['varenr'])))>0)) {
			$x++;
			$linje_id[$x]=$row['id'];
			$kred_linje_id[$x]=$row['kred_linje_id'];
			$vare_id[$x]=$row[vare_id];
			$varenr[$x]=$row['varenr'];
			$vare_id[$x]=$row['vare_id'];
			$leveres[$x]=$row['leveres'];
			$pris[$x]=$row['pris']-($row['pris']*$row['rabat']/100);
			$serienr[$x]=trim($row['serienr']);
		}
	}
	$linjeantal=$x;
	for ($x=1; $x<=$linjeantal; $x++) {
		if (($leveres[$x]>0)&&($serienr[$x])&&($art!='KK')){
			$sn_antal[$x]=0; 
			$query = db_select("select * from serienr where kobslinje_id = '$linje_id[$x]' and batch_kob_id=0");
			while ($row = db_fetch_array($query)){
				$sn_antal[$x]=$sn_antal[$x]+1000;
				$y=$sn_antal[$x]+$x;
				$sn_id[$y]=$row[id];
			}
			if ($leveres[$x]>$sn_antal[$x]/1000){
				 print "<BODY onLoad=\"fejltekst('Serienumre ikke udfyldt')\">";
				exit;
			}
		}
		if (($leveres[$x]<0)&&($serienr[$x])){
			$sn_antal[$x]=0; 
			$query = db_select("select * from serienr where kobslinje_id = -$kred_linje_id[$x] and batch_salg_id<=0");
			while ($row = db_fetch_array($query)) {
				$sn_antal[$x]=$sn_antal[$x]+1000;
				$y=$sn_antal[$x]+$x;
				$sn_id[$y]=$row[id];
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
		$query = db_select("select id, gruppe, beholdning from varer where id='$vare_id[$x]'");
		$row = db_fetch_array($query);
#		$vare_id[$x]=$row[id];
		$gruppe[$x]=$row[gruppe];
		if ($row[beholdning]) $beholdning=$row[beholdning];
		else $beholdning=0;
		$beholdning=$beholdning+$leveres[$x];
		if (($vare_id[$x])&&($leveres[$x]!=0)) {
			$query = db_select("select * from grupper where art='VG' and kodenr='$gruppe[$x]'");
			$row = db_fetch_array($query);
			$box1=trim($row[box1]); $box2=trim($row[box2]); $box3=trim($row[box3]); $box4=trim($row[box4]); $box8=trim($row[box8]); $box9=trim($row[box9]);
			if ($box8!='on') { # Dvs varen er IKKE lagerfoert.
				db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id[$x]'");
					# lager fjernet fra nedenstaaende linje 030306 - PHR
				db_modify("insert into batch_kob(vare_id, linje_id, kobsdate, ordre_id, antal, pris) values ($vare_id[$x], $linje_id[$x], '$levdate', $id, $leveres[$x], '$pris[$x]')");
			} else { #hvis varen ER lagerfoert
				db_modify("update varer set beholdning=$beholdning where id=$vare_id[$x]");
				 if (!$lager) {
					if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'"))) {
						if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'"))) $lager=$row['kodenr'];
					}
					 $lager=$lager*1;
				}
				$query = db_select("select * from lagerstatus where vare_id='$vare_id[$x]' and lager='$lager'");
				if ($row = db_fetch_array($query)) db_modify("update lagerstatus set beholdning=$row[beholdning]+$leveres[$x] where id=$row[id]");
				else db_modify("insert into lagerstatus (vare_id, lager, beholdning) values ($vare_id[$x], $lager, $leveres[$x])");
				if ($box9=='on') {
					if ($leveres[$x]<0) returnering ($id, $linje_id[$x], $leveres[$x], $vare_id[$x],$pris[$x], $serienr[$x], $kred_linje_id[$x], $levdate);#Varereturnering
					else 	reservation($linje_id[$x], $leveres[$x], $vare_id[$x], $serienr[$x]);
				} else {
					db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id[$x]'");
					db_modify("insert into batch_kob(vare_id, linje_id, kobsdate, ordre_id, antal, pris) values ($vare_id[$x], $linje_id[$x], '$levdate', $id, $leveres[$x], '$pris[$x]')");
				}
			}
			db_modify("update ordrelinjer set leveres=0 where id = $linje_id[$x]");
		}
	}
	transaktion("commit");
#	 exit;
}

function reservation($linje_id, $leveres, $vare_id, $serienr)
{
	global $id;
	global $levdate;
	global $lager;

	$res_sum=0;
	$query = db_select("select antal from reservation where linje_id=$linje_id and batch_salg_id<0");
	while ($row = db_fetch_array($query)) {$res_sum=$res_sum+$row[antal];} 
	if ($leveres<$res_sum) {
		print "<BODY onLoad=\"fejltekst('Der er reserveret flere varer end der modtages - foretag proiritering')\">";
		exit;
	} 
	$res_sum=0;
	$y=0;
	$query = db_select("select batch_kob_id, antal, batch_salg_id from reservation where linje_id=$linje_id");
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
		db_modify("insert into batch_kob(linje_id, ordre_id, vare_id, kobsdate, antal, rest, lager) values ($linje_id, $id, $vare_id, '$levdate', $leveres, $rest, $lager)");
		$row = db_fetch_array(db_select("select id from batch_kob where linje_id=$linje_id and ordre_id=$id and kobsdate='$levdate' and	antal=$leveres and rest=$rest"));
		$batch_kob_id=$row[id];								
	} 
	else {
		db_modify("update batch_kob set kobsdate='$levdate', ordre_id=$id, vare_id=$vare_id, antal=$leveres, rest=$rest where id=$batch_kob_id");
	}
	db_modify("delete from reservation where batch_kob_id=$batch_kob_id and linje_id=$linje_id"); 
	$query = db_select("select batch_salg_id from reservation where linje_id=$linje_id and batch_salg_id<0");
	while ($row = db_fetch_array($query)) {
		db_modify("update reservation set linje_id=$row[batch_salg_id]*-1, batch_kob_id=$batch_kob_id where batch_salg_id=$row[batch_salg_id]"); 
	}
	db_modify("update reservation set batch_salg_id = 0 where batch_kob_id =$batch_kob_id"); 

	if ($serienr) {
		db_modify("update serienr set batch_kob_id=$batch_kob_id where kobslinje_id=$linje_id");
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
	$query = db_select("select * from batch_kob where linje_id=$kred_linje_id");
	while ($row = db_fetch_array($query)) {
		$batch_kob_id=$row[id];
		$batch_antal=$row[antal];
		$batch_rest=$row[rest];
		$batch_pris=$row['pris'];
		if ($batch_rest+$leveres>=0) {
			db_modify("update batch_kob set rest=$batch_rest+$leveres where id=$batch_kob_id");
			$rest=$rest-$batch_rest;
			if ($serienr) {
				db_modify("update serienr set batch_kob_id=-$batch_kob_id where batch_kob_id=$batch_kob_id and kobslinje_id=-$kred_linje_id");
			}
		}
	}
	db_modify("insert into batch_kob(linje_id, ordre_id, vare_id, kobsdate, antal, rest) values ($linje_id, $id, $vare_id, '$levdate', $leveres, 0)");
}


#	print "<a href=ordre.php?id=$id accesskey=L>Luk</a>";
print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";

?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
