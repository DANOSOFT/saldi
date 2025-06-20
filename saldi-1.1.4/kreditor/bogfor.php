<?php
@session_start();
$s_id=session_id();
// -------------------------------------kreditor/bogfor.php-------lap 1.1.2a--20070730-----------------------
/// LICENS
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
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------

if ((!$sqhost)||(!$dbuser)||(!$db)) {
	include("../includes/connect.php");
	 include("../includes/online.php");
}
include("../includes/usdate.php");
include("../includes/dkdecimal.php");
include("../includes/db_query.php");

$id=$_GET['id'];

$query = db_select("select levdate, status from ordrer where id = $id");
$row = db_fetch_array($query);
if ($row[status]>2){
	print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
}

$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'");
if ($row = db_fetch_array($query)){
	$year=substr(str_replace(" ","",$row['box2']),-2);
	$aarstart=str_replace(" ","",$year.$row['box1']);
	$year=substr(str_replace(" ","",$row['box4']),-2);
	$aarslut=str_replace(" ","",$year.$row['box3']);
}

$query = db_select("select * from ordrer where id = '$id'");
$row = db_fetch_array($query);
$art=$row[art];
$kred_ord_id=$row[kred_ord_id];
	
if (!$row[levdate]){
	print "<BODY onLoad=\"javascript:alert('Leveringsdato SKAL udfyldes')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
}
elseif (strlen(trim($row[fakturanr]))<1){
	print "<BODY onLoad=\"javascript:alert('Fakturanummer SKAL udfyldes')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
}
else	{
	$fejl=0;
	if ($row[levdate]<$row[ordredate]){
		print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	}
	$levdate=$row[levdate];
	list ($year, $month, $day) = split ('-', $row[levdate]);
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
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'");
		while ($row = db_fetch_array($query)){
			if (($row[posnr]>0)&&(strlen(trim(($row[varenr])))>0)){
				$x++;
				$linje_id[$x]=$row['id'];
				$kred_linje_id[$x]=$row['kred_linje_id'];
				$varenr[$x]=$row['varenr'];
				$antal[$x]=$row['antal'];
				$pris[$x]=$row[pris]-($row[pris]*$row[rabat]/100);
				$serienr[$x]=$row['serienr'];
			}
		}
		$linjeantal=$x;
		for ($x=1; $x<=$linjeantal; $x++) {
			$query = db_select("select id, gruppe from varer where varenr='$varenr[$x]'");
			$row = db_fetch_array($query);
			$vare_id[$x]=$row[id];
			$gruppe[$x]=$row[gruppe];
		}
		for ($x=1; $x<=$linjeantal; $x++) {
			if (($vare_id[$x])&&($antal[$x]!=0)) {
				$query = db_select("select * from grupper where art='VG' and kodenr='$gruppe[$x]'");
				$row = db_fetch_array($query);
				$vgbeskrivelse=$row['beskrivelse']; $box1=trim($row[box1]); $box2=trim($row[box2]); $box3=trim($row[box3]); $box4=trim($row[box4]); $box8=trim($row[box8]); $box9=trim($row[box9]);
				if (!$box3) {
					print "<BODY onLoad=\"javascript:alert('Der er ikke opsat kontonummer for varek&oslash;b pï¿½varegruppen: $vgbeskrivelse.')\">";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
					exit;
				}
				if ($box8!='on'){db_modify("update ordrelinjer set bogf_konto='$box3' where id='$linje_id[$x]'");}
				else {
					if ($box1) db_modify("update ordrelinjer set bogf_konto='$box1' where id='$linje_id[$x]'");
					else db_modify("update ordrelinjer set bogf_konto='$box3' where id='$linje_id[$x]'");
					if ($antal[$x]>0) {
						$query = db_select("select * from batch_kob where linje_id=$linje_id[$x]");
						if ($row = db_fetch_array($query)) {
							$batch_id=$row[id];
							if (($row[pris]!=0)&&($box1)) {
								$diff=$pris[$x]-$row[pris]*1;
								$batch_antal=$row[antal]*1;
								$batch_rest=$row[rest]*1;
								$tmp=$batch_antal-$batch_rest; #Indsat 300707 da beregning under db_modify kan give fejl?
								db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1', '$tmp', '$diff', 0, $id, $box3)");
								$diff=$diff*-1;
								db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1', '$tmp', '$diff', 0, $id, $box2)");
							}
							db_modify("update batch_kob set pris = '$pris[$x]', fakturadate='$levdate' where id=$batch_id");
							$q2 = db_select("select linje_id from batch_salg where batch_kob_id=$batch_id");
							while ($r2 = db_fetch_array($q2)) {
								db_modify("update ordrelinjer set kostpris = '$pris[$x]' where id=$r2[linje_id]");
							}
						}
					} else {
					 $query = db_select("select * from batch_kob where linje_id=$kred_linje_id[$x]");
						if ($row = db_fetch_array($query)) {
							$batch_id=$row[id];
							$diff=$pris[$x]-$row[pris];
							 db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1', $antal[$x], '$diff', 0, $id, $box1)");
							$diff=$diff*-1;
							db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1', $antal[$x], '$diff', 0, $id, $box3)");
						}
						$query = db_select("select * from batch_kob where vare_id=$vare_id[$x] and linje_id=$linje_id[$x]");
						while ($row = db_fetch_array($query)) {
							db_modify("update batch_kob set pris = '$pris[$x]', fakturadate='$levdate' where id=$row[id]");
							$q2 = db_select("select linje_id from batch_salg where batch_kob_id=$batch_id");
							while ($r2 = db_fetch_array($q2)) {
#echo "update ordrelinjer set kostpris = '$pris[$x]' where id=$r2[linje_id]<br>";	
#exit;							
								db_modify("update ordrelinjer set kostpris = '$pris[$x]' where id=$r2[linje_id]");
							}
						}
					}
				}
			}
		}
		$modtagelse=1;
		$query = db_select("select modtagelse from ordrer order by modtagelse");
		while ($row = db_fetch_array($query)) {	
			if ($row[modtagelse] >=$modtagelse) {$modtagelse = $row[modtagelse]+1;}
		}
		if ($modtagelse==1){
			$query = db_select("select box2 from grupper where art = 'RB'");
			if ($row = db_fetch_array($query))	{$modtagelse = $row[box2]*1;}
		}
		if ($modtagelse<1) $modtagelse=1; 
		db_modify("update ordrer set status=3, fakturadate='$levdate', modtagelse = $modtagelse where id=$id");
		$r = db_fetch_array($q = db_select("select box5 from grupper where art='DIV' and kodenr='2'"));
		if ($r[box5]=='on') bogfor($id);
#exit;
		transaktion("commit");
	}
}
print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";

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
		if ($r= db_fetch_array(db_select("select afd from ansatte where navn = '$r[ref]'"))) {$afd=$r['afd'];}
		$afd=$afd*1; #sikkerhed for at 'afd' har en vaerdi 
		 
		if ($no_faktbill==1) $bilag='0';
		else $bilag=trim($fakturanr);
		if (substr($art,1,1)=='K') $beskrivelse ="Lev. kn. - ".$fakturanr;
		else $beskrivelse ="Lev. fakt. - ".$fakturanr;
		db_modify("insert into openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, kladde_id) values ('$konto_id', '$kontonr', '$fakturanr', $sum*-1, '$beskrivelse', '0', '$transdate', '0')");
		
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'"));
		$r = db_fetch_array(db_select("select box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'"));
		$kontonr=$r['box2']; # Kontonr ændres fra at være leverandørkontonr til finanskontonr

		if ($sum>0) {$kredit=$sum; $debet='0';}
		else {$kredit='0'; $debet=$sum*-1;}
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		if ($sum) db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt) values ('$bilag', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt')");
		$q = db_select("select * from ordrelinjer where ordre_id=$id;");
		$y=0;
		$bogf_konto = array();
		while ($r = db_fetch_array($q)) {
			if (!in_array($r[bogf_konto], $bogf_konto)) {
			$y++;
			$bogf_konto[$y]=$r[bogf_konto];
				$pris[$y]=$r[pris]*$r[antal]-round(($r[pris]*$r[antal]*$r[rabat]/100),2);
			}
			else {
				for ($a=1; $a<=$y; $a++) {
					if ($bogf_konto[$a]==$r[bogf_konto]) {
						$pris[$a]=$pris[$a]+($r[pris]*$r[antal]-round(($r[pris]*$r[antal]*$r[rabat]/100),2));
					}
				}		 
			}
		}
		$ordrelinjer=$y;
		for ($y=1;$y<=$ordrelinjer;$y++) {
			if ($bogf_konto[$y]) {
				if ($pris[$y]>0) {$debet=$pris[$y];$kredit=0;}
				else {$debet=0; $kredit=$pris[$y]*-1;}
				$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
				if ($pris[$y]) db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt) values ('$bilag', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '$fakturanr', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt')");
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
		if ($moms > 0) {$debet=$moms; $kredit='0';}
		else {$debet='0'; $kredit=$moms*-1;} 
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		if ($moms) db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt) values ('$bilag', '$transdate', '$beskrivelse', '$box1', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt')");
		db_modify("update ordrer set status=4 where id=$id");
		db_modify("delete from ordrelinjer where ordre_id=$id and posnr < 0");
	}
	if ($d_kontrol!=$k_kontrol) {
		print "<BODY onLoad=\"javascript:alert('Der er konstateret en uoverensstemmelse i posteringssummen, kontakt administrator')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
		
	} 
}

?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
