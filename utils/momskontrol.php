<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

#$logdatetime=array();
#$sum=array();
$bogfor=if_isset($_GET['bogfor']);
$vis=if_isset($_GET['vis']);
$email=if_isset($_GET['email']);

$x=0;
$total=0;
$subtotal=0;
$fejl=0;

if ($vis) {
	echo "ID $db_id<br>";
	if ($bogfor) echo "Bogfører $bogfor<br>";
	else echo "Bogfører ikke<br>";
}

if ($bogfor) transaktion('begin');

$q=db_select("select * from grupper where art='DG' order by kodenr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)){
	$debgrp[$x]=$r['kodenr'];
	$momskode[$x]=str_replace("S","",$r['box1']);
	$samlekonto[$x]=$r['box2'];
	$debvaluta=$r['box3'];
	$r2=db_fetch_array(db_select("select box1,box2 from grupper where art='SM' and kodenr='$momskode[$x]' order by kodenr",__FILE__ . " linje " . __LINE__));
	$momssats[$x]=$r2['box2'];
	$momskonto[$x]=$r2['box1'];
	if ($vis) echo "$x $r[beskrivelse]<br>Momskode: S$momskode[$x]<br>Samlekonto: $samlekonto[$x]<br>Momssats: $momssats[$x]<br>Momskonto: $momskonto[$x]<hr>";   
	$x++;
}
$q=db_select("select * from ordrer where (art='DO' or art = 'DK' or art = 'PO') and momssats > '0' and moms != sum*momssats/100 and fakturadate > '2012-01-20' and status >= '3' order by fakturanr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)){
	$ordrekurs=$r['valutakurs'];
	$sum=$r['sum'];
	$dkksum=$r['sum'];
	if ($ordrekurs && $ordrekurs!=100) $dkksum*=$ordrekurs/100;
	$sum=afrund($sum,2);
	$dkksum=afrund($dkksum,2);
	$moms=$r['moms'];
	$dkkmoms=$r['moms'];
# echo "M1 $r[valutakurs] $moms";
	if ($ordrekurs && $ordrekurs!=100) $dkkmoms*=$ordrekurs/100;
# echo " -> $moms<br>";
	$moms=afrund($moms,2);
	$dkkmoms=afrund($dkkmoms,2);
# echo " -> $moms<br>";
	$x=0;
	$moms_sum=0;
	$salgskonto=array();
	$q2=db_select ("select * from ordrelinjer where ordre_id='$r[id]' and vare_id > '0' order by bogf_konto",__FILE__ . " linje " . __LINE__);
	while ($r2=db_fetch_array($q2)){
		if ($r2['momsfri']!='on' && ($r2['momssats']==0 || $r2['momssats']==$r['momssats'])) {
			$tmp=($r2['pris']*$r2['antal']-($r2['pris']*$r2['antal']/100*$r2['rabat']));
#			if ($ordrekurs && $ordrekurs!=100) $tmp*=$ordrekurs/100;
			if ($r2['bogf_konto'] && !in_array($r2['bogf_konto'],$salgskonto)) {
				$salgskonto[$x]=$r2['bogf_konto'];
				$salgssum[$x]=$tmp;
				$x++;
			} elseif ($r2['bogf_konto']) $salgssum[$x]+=$tmp; 
			$moms_sum+=$tmp*$r2['momssats']/100;
		} elseif($r2['momsfri']!='on' && $r2['momssats']!='0' && $r2['momssats']!=$r['momssats']) {
			$tmp=($r2['pris']*$r2['antal']-($r2['pris']*$r2['antal']/100*$r2['rabat']));
#			if ($ordrekurs && $ordrekurs!=100) $tmp*=$ordrekurs/100;
			if ($r2['bogf_konto'] && !in_array($r2['bogf_konto'],$salgskonto)) {
				$salgskonto[$x]=$r2['bogf_konto'];
				$salgssum[$x]=$tmp;
				$x++;
			} elseif ($r2['bogf_konto']) $salgssum[$x]+=$tmp; 
			$moms_sum+=$tmp*$r2['momssats']/100;
		}
	}
# echo "M2 $r[valutakurs] $moms_sum";
	$dkkmoms_sum=$moms_sum;
	if ($ordrekurs && $ordrekurs!=100) $dkkmoms_sum*=$ordrekurs/100;
# echo " -> $dkkmoms_sum<br>";
	$dkkmoms_sum=afrund($dkkmoms_sum,2);
	$moms_sum=afrund($moms_sum,2);
# echo " -> $dkkmoms_sum<br>";

#echo "$vis A $fejl | $moms | $moms_sum<br>";
	if (abs($moms - $moms_sum)>0.1) {
#echo "B $fejl | $moms | $moms_sum<br>";
		$fejl++;
#cho "C $fejl | $moms | $moms_sum<br>";
#	if (abs($moms - $moms_sum)<0.5) echo "TJEK DEN HER !!!!!!!!!!!!!!!!!!!!<br>";
		if ($vis) echo "fakturanr ".$r['fakturanr']." | ID: ".$r['id']."<br>";
		if ($vis) echo "$r[moms] | $moms_sum<br>";
		$samle_id=NULL;
		$samleamount=NULL;
		$moms_id=NULL;
		$momsdebet=NULL;
		$momskredit=NULL;
		$q2=db_select ("select * from transaktioner where ordre_id='$r[id]'",__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)){
			for ($y=0;$y<count($samlekonto[$y]);$y++) {
				if ($samlekonto[$y]==$r2['kontonr']) {
					$samle_id=$r2['id'];
					$samledebet=$r2['debet'];
					$samlekredit=$r2['kredit'];
					$transdate=$r2['transdate'];
					$logtime=$r2['logtime'];
					$beskrivelse=addslashes($r2['beskrivelse']);
					$faktura=$r2['faktura'];
					$ansat=$r2['ansat'];
					$logdate=$r2['logdate'];
					$afd=$r2['afd'];
					$ordre_id=$r2['ordre_id'];
				}
				if ($momskonto[$y]==$r2['kontonr']) {
					$moms_id=$r2['id'];
					$momsdebet=$r2['debet'];
					$momskredit=$r2['kredit'];
				}
			}
		}
		if ($samle_id) {
			if ($samledebet+$moms_sum>0) {
			if ($vis) echo "$sum+$moms_sum";
				$totalsum=$sum+$moms_sum;
				$dkktotalsum=$dkksum+$dkkmoms_sum;
				if ($vis) echo "($faktura) update transaktioner set debet=$dkktotalsum where id = '$samle_id'<br>";
				if ($bogfor) db_modify("update transaktioner set debet=$dkktotalsum where id = '$samle_id'",__FILE__ . " linje " . __LINE__);
			}
			else {
				$dkktotalsum=($dkksum-$dkkmoms_sum)*-1;
				if ($vis) echo "update transaktioner set kredit=$dkktotalsum where id = '$samle_id'<br>";
				if ($bogfor) db_modify("update transaktioner set kredit=$dkktotalsum where id = '$samle_id'",__FILE__ . " linje " . __LINE__);
			}
			if ($moms_id) {
				if($dkkmoms_sum>0) {
					if ($vis) echo "update transaktioner set kredit=$dkkmoms_sum where id = '$moms_id' and kredit = '$momskredit'<br>";
					if ($bogfor) db_modify("update transaktioner set kredit=$dkkmoms_sum where id = '$moms_id' and kredit = '$momskredit'",__FILE__ . " linje " . __LINE__);
				}else {
					if ($vis) echo "update transaktioner set debet=$dkkmoms_sum where id = '$moms_id' and debet = '$momsdebet'<br>";
					if ($bogfor) db_modify("update transaktioner set debet=$dkkmoms_sum where id = '$moms_id' and debet = '$momsdebet'",__FILE__ . " linje " . __LINE__);
				}
			} else {
				$r3=db_fetch_array(db_select("select * from adresser where id='$r[konto_id]'",__FILE__ . " linje " . __LINE__));
				for ($y=0;$y<count($momskonto);$y++) {
#echo "$debgrp[$y]==$r3[gruppe]<br>";
					if ($debgrp[$y]==$r3['gruppe']) {
						if ($moms_sum>0) {
							$kredit=$dkkmoms_sum;
							$debet=0;
						} else {
							$debet=$dkkmoms_sum;
							$kredit=0;
						}
						if ($vis) echo "insert into transaktioner(kontonr,bilag,transdate,logtime,beskrivelse,debet,kredit,faktura,kladde_id,ansat,logdate,afd,ordre_id) values ('$momskonto[$y]','0','$transdate','$logtime','$beskrivelse','$debet','$kredit','$faktura','0','$ansat','$logdate','$afd','$ordre_id')<br>";#			echo "insert into transaktioner (konto_id,debet,kredit) values ('$r[konto_id]',$moms_sum,0)<br>"; 
						if ($bogfor) db_modify("insert into transaktioner(kontonr,bilag,transdate,logtime,beskrivelse,debet,kredit,faktura,kladde_id,ansat,logdate,afd,ordre_id) values ('$momskonto[$y]','0','$transdate','$logtime','$beskrivelse','$debet','$kredit','$faktura','0','$ansat','$logdate','$afd','$ordre_id')",__FILE__ . " linje " . __LINE__);
					}
				}
			}
			if ($vis || $bogfor) {
				$r4=db_fetch_array(db_select("select SUM(debet) as debet, SUM(kredit) as kredit from transaktioner where logdate='$logdate' and logtime='$logtime' and faktura='$faktura'",__FILE__ . " linje " . __LINE__));
				if (abs($r4['debet'] - $r4['kredit']) >= 0.01) {
					echo "FEJL i faktura $faktura $r4[debet] != $r4[kredit]<br>";
					exit;
				}
			}
			($moms_sum>=0)?$tmp="and amount >= 0":$tmp="and amount < 0"; 
#echo "select * from openpost where konto_id='$r[konto_id]' and transdate='$transdate' $tmp<br>";
			$r3=db_fetch_array(db_select("select * from openpost where konto_id='$r[konto_id]' and transdate='$transdate' $tmp",__FILE__ . " linje " . __LINE__));
			$oppid=$r3['id'];
			$oppkurs=$r3['valutakurs'];
			if ($oppkurs==$ordrekurs)	$tmp=$totalsum;
			elseif ($ordrekurs=='100' || !$ordrekurs) $tmp=$dkktotalsum;
			else {
				$tmp=$totalsum*$oppkurs/$ordrekurs;
			}
			if ($vis) echo "OK $ordrekurs $oppkurs update openpost set amount=$tmp where id = '$r3[id]'<br>";
			if ($oppid && $bogfor) db_modify("update openpost set amount=$tmp where id = '$oppid'",__FILE__ . " linje " . __LINE__);
			if ($vis) echo "update ordrer set moms='$moms_sum' where id = '$r[id]'<br>";
			if ($r['id'] && $bogfor) db_modify("update ordrer set moms='$moms_sum' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
		}
	}
}
if ($email && $fejl) {
	$message="$fejl moms fejl i regnskab $db\r\n";
	$headers='From: mailserver@webvisor.dk' . "\r\n";
	$headers.='Reply-To: info@webvisor.dk\r\n';
	$headers.='Content-type: text; charset=iso-8859-1' . "\r\n";
	$headers.='X-Mailer: PHP/' . phpversion(). "\r\n";
	$to = "fejl@saldi.dk";
	$subject = "$fejl moms fejl i regnskab $db";
	$subject=utf8_decode($subject);
	$message=utf8_decode($message);
	mail($to, $subject, $message, 	$headers);
}
if ($email) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php?id=$id\">\n";

#sleep(2600);
if ($bogfor) transaktion('commit');

?>
