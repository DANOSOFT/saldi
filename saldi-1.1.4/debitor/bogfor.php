<?php
@session_start();
$s_id=session_id();
// ------------debitor/bogfor.php-------------------- patch patch 1.1.4 ---06.03.2008------
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

$modulnr=5;
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/usdate.php");
include("../includes/usdecimal.php");

$id=$_GET['id'];

$query = db_select("select * from ordrer where id = $id");
$row = db_fetch_array($query);
$ordredate=$row['ordredate'];
$levdate=$row['levdate'];
$fakturadate=$row['fakturadate'];
$nextfakt=$row['nextfakt'];
$art=$row['art'];
$kred_ord_id=$row['kred_ord_id'];
$valuta=$row['valuta'];
	
	if ($row[status]!=2){
		print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	}
	$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'");
	if ($row = db_fetch_array($query)){
#		$year=substr(str_replace(" ","",$row['box2']),-2);#aendret 060308 - grundet mulighed for fakt i aar 2208
		$year=trim($row['box2']);
		$aarstart=str_replace(" ","",$year.$row['box1']);
#		$year=substr(str_replace(" ","",$row['box4']),-2);
		$year=trim($row['box2']);
		$aarslut=str_replace(" ","",$year.$row['box3']);
	}
	$query = db_select("select * from ordrer where id = '$id'");
	$row = db_fetch_array($query);
	if (!$fakturadate){
		 print "<meta http-equiv=\"refresh\" content=\"0;URL=fakturadato.php?id=$id&returside=bogfor.php\">";
		exit;
	}
	if (!$levdate){
		print "<BODY onLoad=\"javascript:alert('Leveringsdato SKAL udfyldes')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	}	
	if ($levdate<$ordredate){
		 print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
	 	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	 	exit;
	}
	if ($fakturadate<$levdate)	{
		print "<BODY onLoad=\"javascript:alert('Fakturadato er f&oslash;r leveringsdato')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	}	
	if (($nextfakt)&& ($nextfakt<=$fakturadate)){
		print "<BODY onLoad=\"javascript:alert('Genfaktureringsdato skal v&aelig;re efter fakturadato')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
 		exit;
	}
	list ($year, $month, $day) = split ('-', $fakturadate);
	$year=trim($year);
	$ym=$year.$month;
	if (($ym<$aarstart)||($ym>$aarslut))	{
		print "<BODY onLoad=\"javascript:alert('Fakturadato udenfor regnskabs&aring;r')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	}
	if ($valuta && $valuta!='DKK') {
		if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr and valuta.valdate <= '$ordredate' order by valuta.valdate"))) {
			$valutakurs=$r['kurs'];
		} else {
			$tmp = dkdato($ordredate);
			print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	}
	if ($fejl==0) {
 		transaktion("begin");
#		echo "bogf&oslash;rer nu!........";
		$fakturanr=1;
		$query = db_select("select fakturanr from ordrer where art = 'DO' or art = 'DK'");
		while ($row = db_fetch_array($query)){
			if ($fakturanr <= $row[fakturanr]) {$fakturanr = $row[fakturanr]+1;}
		}
		if ($fakturanr == 1) {
			$query = db_select("select box1 from grupper where art = 'RB' order by kodenr");
			if ($row = db_fetch_array($query)){$fakturanr=$row[box1]*1;}
		}
		if ($fakturanr < 1) $fakturanr = 1;	
		batch_kob($id, $art); 
		batch_salg($id);
		db_modify("update ordrer set status=3, fakturanr=$fakturanr where id=$id");
		$r = db_fetch_array($q = db_select("select box5 from grupper where art='DIV' and kodenr='2'"));
		if ($r[box5]=='on') bogfor($id);
#exit;			
		transaktion("commit");
	}
	print "<BODY onLoad=\"JavaScript:window.open('formularprint.php?id=$id&formular=4' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";

function batch_salg($id) {
	global $fakturadate; 
	global $valutakurs;
	
	$x=0;
	$query = db_select("select * from batch_salg where ordre_id = '$id'");
	while ($row = db_fetch_array($query)){
		$x++;
		$batch_id[$x]=$row[id];
		$vare_id[$x]=$row[vare_id];	
		$antal[$x]=$row[antal];
		$serienr[$x]=$row['serienr'];
		$batch_kob_id[$x]=$row[batch_kob_id];
		$batch_linje_id[$x]=$row[linje_id];
	}
	$linjeantal=$x;	
	

	for ($x=1; $x<=$linjeantal; $x++) {
		$kostpris=0;

		$query = db_select("select id, pris, rabat from ordrelinjer where id = $batch_linje_id[$x]");
		$row = db_fetch_array($query);
		$ordre_linje_id=$row['id'];
		$pris = $row['pris']-($row['pris']*$row['rabat']/100);
		if ($valutakurs) $pris=$pris*$valutakurs/100;

		db_modify("update batch_salg set pris=$pris, fakturadate='$fakturadate' where id=$batch_id[$x]"); 
		if ($batch_kob_id[$x]) {
			$query = db_select("select pris, ordre_id from batch_kob where id = $batch_kob_id[$x]");
			if ($row = db_fetch_array($query)) {
				$kostpris=$row['pris'];
				if ($row[ordre_id]) {
					$query = db_select("select status from ordrer where id = $row[ordre_id]");
					$row = db_fetch_array($query);
					if ($row[status]){$kobsstatus=$row[status];}
				}	
				else {$kobsstatus=0;}
			}
		}
#		else {#if ($batch_kob_id[$x]) 
	
		$query2 = db_select("select gruppe from varer where id = $vare_id[$x]");
		$row2 = db_fetch_array($query2);
		$gruppe=$row2['gruppe'];
		$query2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe'");
		$row2 = db_fetch_array($query2);
		$box1=trim($row2[box1]); $box2=trim($row2[box2]); $box3=trim($row2[box3]); $box4=trim($row2[box4]); $box8=trim($row2[box8]); $box9=trim($row2[box9]);
		db_modify("update ordrelinjer set bogf_konto=$box4 where id=$ordre_linje_id");
		if ($box9=='on'){ # box 9 betyder at der anvendes batch styring  
			if (!$batch_kob_id[$x]) { # saa er varen ikke paa lager, dvs at indkobsordren skal findes i tabellen reservation
				$query = db_select("select linje_id, lager from reservation where batch_salg_id = $batch_id[$x]");
				$row = db_fetch_array($query);
				$res_antal=$res_antal+$row[antal]; 
				$res_linje_id=$row[linje_id];
				$lager=$row[lager];
				$query = db_select("select ordre_id from ordrelinjer where id = $res_linje_id"); 
				$row = db_fetch_array($query);
				$kob_ordre_id = $row[ordre_id]; 
			# Hvis levering er sket i flere omgange vil der vaere flere batch_salg linjer paa samme kobs linje, derfor nedenstaende.	 
				if ($row = db_fetch_array(db_select("select id from batch_kob where linje_id=$res_linje_id and vare_id=$vare_id[$x] and ordre_id=$kob_ordre_id"))) {
					$batch_kob_id[$x]=$row[id];
				}
				else {
					 db_modify("insert into batch_kob (linje_id, vare_id, ordre_id, pris, lager) values ($res_linje_id, $vare_id[$x], $kob_ordre_id, $pris, $lager)"); #Antal indsaettes ikke - dette styres i "reservation"
					$row = db_fetch_array(db_select("select id from batch_kob where linje_id=$res_linje_id and vare_id=$vare_id[$x] and ordre_id=$kob_ordre_id"));
					$batch_kob_id[$x]=$row[id];
				} 
				db_modify("update reservation set batch_kob_id=$batch_kob_id[$x] where linje_id = $res_linje_id");
				db_modify("update batch_salg set batch_kob_id=$batch_kob_id[$x] where id=$batch_id[$x]");		
			}
			$row = db_fetch_array(db_select("select pris from batch_kob where id=$batch_kob_id[$x]")); # kostprisen findes..
			if ($row['pris']) $pris=$row['pris']; 
			if ($box1&&$box2) { #kostvaerdien flyttes fra "afgang varelager" til "varekob".- hvis der ikke bogfoeres direkte paa varekobs kontoen
				#	if ($valutakurs) $pris=$pris*100/$valutakurs;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$pris', 0, $id, $box2)");
				$pris=$pris*-1;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$pris', 0, $id, $box3)");
			}
		} elseif ($box8=='on') { # hvis box8 er 'on' er varen lagerført
			$row = db_fetch_array(db_select("select kostpris from varer where id=$vare_id[$x]"));
			if (!$row['kostpris']) $kostpris='0';
			else $kostpris=$row['kostpris'];
#			if ($valutakurs) $kostpris=$kostpris*100/$valutakurs;
			db_modify("update ordrelinjer set kostpris = $kostpris where id=$ordre_linje_id");
			if ($box1&&$box2) {
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$kostpris', 0, $id, $box2)");
				$kostpris=$kostpris*-1;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$kostpris', 0, $id, $box3)");
			}
		}
	}
}
####### batch_kob anvendes hvis der krediteres en vare som ikke er blevet solgt - og derfor betragtes som et varekoeb ####### 
function batch_kob($id, $art) 
{
	global $fakturadate; 
	global $valutakurs;
	
	$query = db_select("select * from batch_kob where ordre_id = '$id'");
	while ($row = db_fetch_array($query)){
		$x++;
		$batch_id=$row[id];
		$vare_id=$row[vare_id];
		$antal=$row[antal];
		$serienr=$row['serienr'];
		$batch_kob_id=$row[batch_kob_id]; 
		$query2 = db_select("select id, pris, rabat from ordrelinjer where id = $row[linje_id]");
		$row2 = db_fetch_array($query2);
		$ordre_linje_id=$row2[id];
		$pris = $row2[pris]-($row2[pris]*$row2[rabat]/100);
		if ($row[pris]) {$diff = $pris-$row[pris];}
		db_modify("update batch_kob set pris=$pris, fakturadate='$fakturadate' where id=$batch_id");
 		$query2 = db_select("select gruppe from varer where id = $vare_id");
		$row2 = db_fetch_array($query2);
		$gruppe=$row2['gruppe'];
		$query2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe'");
		$row2 = db_fetch_array($query2);
		$box1=trim($row2[box1]); $box2=trim($row2[box2]); $box3=trim($row2[box3]); $box4=trim($row2[box4]); $box8=trim($row2[box8]); $box9=trim($row2[box9]);
		db_modify("update ordrelinjer set bogf_konto=$box4 where id=$ordre_linje_id");
		if ($box9=='on'){
			$pris=$pris-$diff;
			if (!$pris){$pris=0;}
			if ($valutakurs) $pris=$pris*100/$valutakurs;
			db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1', $antal, $pris, 0, $id, $box3)");
			$pris=$pris*-1;
			db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1', $antal, $pris, 0, $id, $box2)");
		}
	}
}
function bogfor($id)
{

	$d_kontrol=0; 
	$k_kontrol=0;
	$logdate=date("Y-m-d");
	$logtime=date("H:i");
	$q = db_select("select box1, box2, box3, box4, box5 from grupper where art='RB'");
	if ($r = db_fetch_array($q)) {
		if (trim($r['box3'])=="on") $faktbill=1; 
		else {$faktbill=0;}
		if (trim($r['box4'])=="on") $modtbill=1; 
		else $modtbill=0;
		if (trim($r['box5'])=="on") {
			$no_faktbill=1;
			$faktbill=0;
		}	 
		else $no_faktbill=0;
	}
	$x=0;
	$q = db_select("select * from ordrer where id='$id'");
	if ($r = db_fetch_array($q)) {
		$art=$r['art'];
		$konto_id=$r['konto_id'];
		$kontonr=str_replace(" ","",$r['kontonr']);
		$firmanavn=trim($r['firmanavn']);
		$modtagelse=$r['modtagelse'];
		$transdate=($r['fakturadate']);
		$fakturanr=$r['fakturanr'];
		$ordrenr=$r['ordrenr'];
		$valuta=$r['valuta'];
		$projekt=$r['projekt']*1;
		$refnr;
		if ($r['moms']) {$moms=$r['moms'];}
		else {$moms=round($r['sum']*$r['momssats']/100,2);}
		$sum=$r['sum']+$moms;
		$ordreantal=$x;
		$r= db_fetch_array(db_select("select id, afd from ansatte where navn = '$r[ref]'"));
		$afd=$r['afd']*1;#sikkerhed for at 'afd' har en vaerdi 
		$ansat=$r['id']*1;
		if ($no_faktbill==1) $bilag='0';
		else $bilag=trim($fakturanr);
		if (substr($art,1,1)=='K') $beskrivelse ="Kreditnota - ".$fakturanr;
		else $beskrivelse ="Faktura - ".$fakturanr;
		if ($valuta && $valuta!='DKK') {
			if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr and valuta.valdate <= '$transdate' order by valuta.valdate"))) {
				$valutakurs=$r['kurs'];
			} else {
				$tmp = dkdato($ordredate);
				print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $transdate')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				exit;
			}
		}
		if ($valutakurs) $sum=$sum*$valutakurs/100; # Omregning til DKR.
		db_modify("insert into openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, kladde_id) values ('$konto_id', '$kontonr', '$fakturanr', '$sum', '$beskrivelse', '0', '$transdate', '0')");
		
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'"));
		$r = db_fetch_array(db_select("select box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'"));
		$kontonr=$r['box2']; # Kontonr ændres fra at være leverandørkontonr til finanskontonr

		if ($sum>0) {$debet=$sum; $kredit='0';}
		else {$debet='0'; $kredit=$sum*-1;}
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt', '$ansat', '$id')");
		$y=0;
		$bogf_konto = array();
		$q = db_select("select * from ordrelinjer where ordre_id=$id;");
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['bogf_konto'], $bogf_konto)) {
			$y++;
			$bogf_konto[$y]=$r['bogf_konto'];
				$pris[$y]=$r['pris']*$r['antal']-round(($r['pris']*$r['antal']*$r['rabat']/100),2);
			}
			else {
				for ($a=1; $a<=$y; $a++) {
					if ($bogf_konto[$a]==$r['bogf_konto']) {
						$pris[$a]=$pris[$a]+($r['pris']*$r['antal']-round(($r['pris']*$r['antal']*$r['rabat']/100),2));
					}
				}		 
			}
		}
		$ordrelinjer=$y;
		for ($y=1;$y<=$ordrelinjer;$y++) {
			if ($bogf_konto[$y]) {
				if ($pris[$y]>0) {$kredit=$pris[$y];$debet=0;}
				else {$kredit=0; $debet=$pris[$y]*-1;}
				if ($valutakurs) {$kredit=$kredit*$valutakurs/100;$debet=$debet*$valutakurs/100;} # Omregning til DKR.
				$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
				db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '$fakturanr', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt', '$ansat', '$id')");
			}
		}
		$query = db_select("select gruppe from adresser where id='$konto_id';");
		$row = db_fetch_array($query);
		$query = db_select("select box1 from grupper where art='DG' and kodenr='$row[gruppe]';");
		$row = db_fetch_array($query);
		$box1=substr(trim($row[box1]),1,1);
		$query = db_select("select box1 from grupper where art='SM' and kodenr='$box1'");
		$row = db_fetch_array($query);
		$box1=trim($row[box1]);
		if ($moms > 0) {$kredit=$moms; $debet='0';}
		else {$kredit='0'; $debet=$moms*-1;} 
		if ($valutakurs) {$kredit=$kredit*$valutakurs/100;$debet=$debet*$valutakurs/100;} # Omregning til DKR.
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$box1', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt', '$ansat', '$id')");
		db_modify("update ordrer set status=4, valutakurs=$valutakurs where id=$id");
		db_modify("delete from ordrelinjer where ordre_id=$id and posnr < 0");
	}
	$d_kontrol=round($d_kontrol,2);
	$k_kontrol=round($k_kontrol,2);
	if ($d_kontrol!=$k_kontrol) {
		echo "$d_kontrol!=$k_kontrol<br>";		
		print "<BODY onLoad=\"javascript:alert('Der er konstateret en uoverensstemmelse i posteringssummen, kontakt administrator')\">";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	} 
}
######################################################################################################################################
?>			
</body></html>
