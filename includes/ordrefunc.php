<?php
//----------------- debitor/ordrefunc.php -----ver 3.2.8---- 2012.02.22 ----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2003-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

function levering($id,$hurtigfakt,$genfakt,$webservice) {
#cho "$id,$hurtigfakt,$genfakt,$webservice<br>";
# Denne funktion kontrollerer levering of kalder funktioner som registrerer salget i tabellerne varer,batch_salg og ect batch_kob

global $regnaar;
global $levdate;
global $lev_nr;
global $db;

$fp=fopen("../temp/ordrelev.log","a");
$q = db_select("select lev_nr from batch_salg where ordre_id = $id order by lev_nr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($lev_nr<=$r['lev_nr']){
		$lev_nr=$r['lev_nr']+1;
	}
}
if (!$lev_nr) {$lev_nr=1;}

$x=0;
$q=db_select("select id from ordrelinjer where ordre_id = '$id' and posnr > '0' order by posnr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$x++;
	db_modify("update ordrelinjer set posnr='$x' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
}
$query = db_select("select * from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
$row =db_fetch_array($query);
$ref=$row['ref'];
$levdate=$row['levdate'];
$fakturadate=$row['fakturadate'];
$art=$row['art'];
$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
if ($row =db_fetch_array($query)) {
#	$year=substr(str_replace(" ","",$row['box2']),-2); #aendret 060308 - grundet mulighed for fakt i aar 2208
	$year=trim($row['box2']);
	$aarstart=str_replace(" ","",$year.$row['box1']);
#	$year=substr(str_replace(" ","",$row['box4']),-2);
	$year=trim($row['box4']);
	$aarslut=str_replace(" ","",$year.$row['box3']);
}
if ($hurtigfakt && $fakturadate && $fakturadate != $levdate) {
	db_modify("update ordrer set levdate = fakturadate where id = $id",__FILE__ . " linje " . __LINE__);
#cho "update ordrer set levdate = fakturadate where id = $id<br>";
# exit;
	$levdate=$fakturadate;
}
$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
if ($fakturadate && !$r['levdate']){
	print "<BODY onLoad=\"javascript:alert('Leveringsdato SKAL udfyldes')\">";
#	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
} else {
	if (!$hurtigfakt && $r['levdate']<$r['ordredate']) {
		 print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
		 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		 exit;
	}
	list ($year, $month, $day) = explode ('-', $r['levdate']);
	$year=trim($year);
	$tmp=date("Y");
	if (!$hurtigfakt && $art!='PO' && !$webservice && !$genfakt && ($year<$tmp-10||$year>$tmp+10)) {
#cho "($art!='PO' && !$webservice && !$genfakt && ($y<$tmp-10||$y>$tmp+10))<br>";
		print "<BODY onLoad=\"javascript:alert('Tjek leveringsdato $levdate')\">";
		 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		 exit;
	}
	if ($hurtigfakt=='on' && !$fakturadate) {
		 print "<meta http-equiv=\"refresh\" content=\"0;URL=fakturadato.php?id=$id&returside=levering.php&hurtigfakt=on\">";
#		include("fakturadato.php");
#		fakturadato($id);
		exit;
	}
	if ($fejl==0){
		$fakturanr=1;
		$x=0;

#		$query = db_select("select * from ordrelinjer where ordre_id = '$id' and samlevare = 'on'",__FILE__ . " linje " . __LINE__);
#		while ($row =db_fetch_array($query)){
#cho "$x linje_id $row[id] kred_linje_id $row[kred_linje_id]<br>";
#			if ($row['leveres']!=0) samlevare($id,$art,$row['id'], $row['vare_id'], $row['leveres']);
#		}
#cho "select * from ordrelinjer where ordre_id = '$id'<br>";
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($row =db_fetch_array($query)){
			if (($row['posnr']>0)&&(strlen(trim(($row['varenr'])))>0)){
				$x++;
				$linje_id[$x]=$row['id'];
				$kred_linje_id[$x]=$row['kred_linje_id'];
#cho "$x kred_linje_id $kred_linje_id[$x]<br>";
				$vare_id[$x]=$row['vare_id'];
				$varenr[$x]=$row['varenr'];
				$antal[$x]=$row['antal'];
				$leveres[$x]=$row['leveres'];
				$pris[$x]=$row['pris'];
				$kostpris[$x]=$row['kostpris'];
				$rabat[$x]=$row['rabat'];
				$nettopris[$x]=$row['pris']-($row['pris']*$row['rabat']/100);
				$serienr[$x]=trim($row['serienr']);
				$posnr[$x]=$row['posnr'];
				$bogf_konto[$x]=$row['bogf_konto'];
				$variant_id[$x]=$row['variant_id'];
				if ($hurtigfakt=='on') $leveres[$x]=$antal[$x];
			}
		}
		$linjeantal=$x;
		for ($x=1; $x<=$linjeantal; $x++) {
			$tidl_lev=0;
			$query = db_select("select antal from batch_salg where linje_id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
			while ($row =db_fetch_array($query)) {
				$tidl_lev=$tidl_lev+$row['antal'];
			}
			if ($hurtigfakt=='on') $leveres[$x]=$antal[$x]-$tidl_lev;
			if (($antal[$x]>0)&&($antal[$x]<$leveres[$x]+$tidl_lev)) {
				print "<BODY onLoad=\"javascript:alert('Der er sat for meget til levering (pos nr. $posnr[$x])')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				exit;
			}
			if (($leveres[$x]>0)&&($serienr[$x])) {
				$sn_antal[$x]=0;
				$query = db_select("select * from serienr where salgslinje_id = '$linje_id[$x]' and batch_salg_id=0",__FILE__ . " linje " . __LINE__);
				while ($row =db_fetch_array($query)) $sn_antal[$x]++; 
			 if ($leveres[$x]!=$sn_antal[$x]) {
					 print "<BODY onLoad=\"javascript:alert('Der er sat $leveres[$x] til levering men valgt $sn_antal[$x] serienumre (pos nr: $posnr[$x])')\">";
					 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
					 exit;
				}
			}
			if (($leveres[$x]<0)&&($serienr[$x])) {
				$sn_antal[$x]=0;
				if ($art=='KO') $q = db_select("select * from serienr where salgslinje_id = $kred_linje_id[$x]*-1",__FILE__ . " linje " . __LINE__);
				else $q = db_select("select * from serienr where salgslinje_id <0 and vare_id=$vare_id[$x]",__FILE__ . " linje " . __LINE__);
				while ($row =db_fetch_array($q)) {
					db_modify("insert into serienr (vare_id,kobslinje_id,salgslinje_id,batch_kob_id,batch_salg_id,serienr) values ('$vare_id[$x]','$linje_id[$x]','0','0','0','$row[serienr]')",__FILE__ . " linje " . __LINE__);
					db_modify("update serienr set salgslinje_id=abs(salgslinje_id) where id = '$row[id]'",__FILE__ . " linje " . __LINE__);
					$sn_antal[$x]++;
				}
			 if ($leveres[$x]+$sn_antal[$x]!=0){
					$tmp=$leveres[$x]*-1;
					print "<BODY onLoad=\"javascript:alert('Der er sat $tmp til returnering men valgt $sn_antal[$x] serienumre (pos nr: $posnr[$x])')\">";
					 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
					 exit;
				}
			}
			if ($leveres[$x]<0 && $art == 'DK') {
				 $tidl_lev=0;
				 $query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]' and ordre_id=$id",__FILE__ . " linje " . __LINE__);
				 while($row = db_fetch_array($query)) $tidl_lev=$tidl_lev-$row['antal'];
				 if ($leveres[$x]>$tidl_lev+$antal[$x]) $leveres[$x]=$antal[$x]-$tidl_lev;
			}
		}
			for ($x=1; $x<=$linjeantal; $x++)	{
			$sn_start=0;
			$query = db_select("select * from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
			$row =db_fetch_array($query);
#			$kostpris[$x]=$row['kostpris'];
			$gruppe[$x]=$row['gruppe'];
			if ($row['beholdning']) {$beholdning[$x]=$row['beholdning'];}
			else $beholdning[$x]=0;
			$beholdning[$x]=$beholdning[$x]-$leveres[$x];
			if (trim($row['samlevare'])=='on') {
#				samlevare($id,$art,$linje_id[$x], $vare_id[$x], $leveres[$x])
#				for ($a=1; $a<=$leveres[$x]; $a++) samlevare($vare_id[$x], $linje_id[$x]);
			}
			if (!$gruppe[$x]) {
				print "<BODY onLoad=\"javascript:alert('Vare tilh&oslash;rer ikke nogen varegruppe - kontroller vare og indstillinger! (pos nr: $posnr[$x])')\">";
				if ($art=='PO') print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">";
				else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				exit;
			}
			if ($vare_id[$x] && $leveres[$x])  {
				linjeopdat($id, $gruppe[$x], $linje_id[$x], $beholdning[$x], $vare_id[$x], $leveres[$x], $pris[$x], $nettopris[$x], $rabat[$x], $row['samlevare'], $x, $posnr[$x], $serienr[$x], $kred_linje_id[$x],$bogf_konto[$x],$variant_id[$x]);
#				if (trim($row['samlevare'])=='on') {
#					$q2 = db_select("select * from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
#					while($r2 =db_fetch_array($q2)) 
#				}
			}
		}
	}
}
return("OK");
} #endfunc levering

#############################################################################################
function linjeopdat($id ,$gruppe, $linje_id, $beholdning, $vare_id, $antal, $pris, $nettopris, $rabat, $samlevare, $linje_nr, $posnr, $serienr, $kred_linje_id,$bogf_konto,$variant_id){

	# Denne funktion finder de kontonumre fra kontoplanen som de elkelte ordrelinjer skal bogføres på, og tilføjer dem på ordrelinjen 
	# Kaldes fra funktionen levering - 

#cho "$id - $linje_id - $kred_linje_id<br>";

	global $fp;
	global $levdate;
	global $fakturadate;
	global $sn_id;
	global $art;
	global $ref;
	global $lev_nr;

	$query = db_select("select * from grupper where art='VG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__); #VG = Varegruppe
	if ($row =db_fetch_array($query)){
		$box1=trim($row['box1']); $box2=trim($row['box2']); $box3=trim($row['box3']); $box4=trim($row['box4']); $box8=trim($row['box8']); $box9=trim($row['box9']);
	} else {
		$r=db_fetch_array(db_select("select posnr from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__));
		print "<BODY onLoad=\"javascript:alert('Varegruppe ikke opsat korrekt, pos nr $r[posnr]')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	}
	if (!$box3 || !$box4) { # box3 & box4 er kontonumre for varekøb og varesalg
		$fejltekst="Varegruppe $gruppe mangler kontonummer for varek&oslash;b og/eller varesalg (Indstillinger -> Varegrp)";
		print "<BODY onLoad=\"javascript:alert('$fejltekst')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	}
	if (($box8!='on')||($samlevare=='on')){ #box 8 angiver om vare(gruppen) er lagerført
		if($bogf_konto) $box4=$bogf_konto; # hvis funktionen kaldes med en bogføringskonto overruler denne box4

		db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		if ($art=='DK' || $antal < 0) {
			$tmp=$antal*-1;
			db_modify("insert into batch_kob(vare_id, linje_id, kobsdate, ordre_id, antal,rest) values ('$vare_id', '$linje_id', '$levdate', '$id','$tmp','$tmp')",__FILE__ . " linje " . __LINE__);
		} else db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr) values (0, '$vare_id', '$linje_id', '$levdate', '$id', '$antal', '$pris', '$lev_nr')",__FILE__ . " linje " . __LINE__);
	} else {
		if($bogf_konto) $box4=$bogf_konto; 
		db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		db_modify("update varer set beholdning=$beholdning where id='$vare_id'",__FILE__ . " linje " . __LINE__);
		if ($variant_id) db_modify("update variant_varer set variant_beholdning=variant_beholdning-$antal where id='$variant_id'",__FILE__ . " linje " . __LINE__);
#cho "box9 $box9<br>";
		if ($box9=='on') { # #box 9 angiver om vare(gruppen) er underlagt batchkontrol
			if ($antal<0) {krediter($id, $levdate, $beholdning, $vare_id, $antal*-1, $pris, $linje_id, $serienr, $kred_linje_id);}
			else {batch_salg_lev($id, $levdate, $fakturadate, $beholdning, $vare_id, $antal, $pris, $nettopris, $linje_id, $linje_n, $posnr, $serienr, $lager);}
		} else {
			if($bogf_konto) $box4=$bogf_konto; 
			db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		if ($art=='DK' || $antal < 0) {
				$tmp=$antal*-1;
				db_modify("insert into batch_kob(vare_id, linje_id, kobsdate, ordre_id, antal,rest) values ('$vare_id', '$linje_id', '$levdate', '$id','$tmp','$tmp')",__FILE__ . " linje " . __LINE__);
			} else db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr) values (0, '$vare_id', '$linje_id', '$levdate', '$id', '$antal', '$pris', '$lev_nr')",__FILE__ . " linje " . __LINE__);
		}
#		if ($box1 && $box2) bogfor_levering($id,$gruppe,$linje_id,$antal,$box1,$box2,$box3,$box4);
	}
#cho "select box2 from grupper where art = 'DIV' and kodenr = '5'<br>";
	$r=db_fetch_array(db_select("select box2 from grupper where art = 'DIV' and kodenr = '5' ",__FILE__ . " linje " . __LINE__));
	if ($shopurl=trim($r['box2'])) {
		$r=db_fetch_array(db_select("select beholdning,publiceret from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
#cho "publiceret $r[publiceret]<br>";
		if ($r['publiceret']) {
			$shop_beholdning=$r['beholdning'];
#cho "beholdning $shop_beholdning<br>";
			$r=db_fetch_array(db_select("select sum(ordrelinjer.antal-ordrelinjer.leveret) as antal from ordrer,ordrelinjer where ordrelinjer.vare_id = '$vare_id' and ordrelinjer.ordre_id = ordrer.id and (ordrer.art='DO' or ordrer.art='DK') and (ordrer.status='1' or ordrer.status='2') and ordrer.id!='$id'",__FILE__ . " linje " . __LINE__));
			$shop_beholdning-=$r['antal'];
#cho "select shop_id from shop_varer where saldi_id='$vare_id'<br>";
			$r=db_fetch_array($q=db_select("select shop_id from shop_varer where saldi_id='$vare_id'",__FILE__ . " linje " . __LINE__));
			$shop_id=$r['shop_id'];
#cho "shop id $shop_id<br>";
			$url=$shopurl."/opdat_beholdning.php?vare_id=$vare_id&shop_id=$shop_id&beholdning=$shop_beholdning";
	#		print "<BODY onLoad=\"javascript:alert('Beholdning: $beholdning')\">";	
			print "<body onload=\"javascript:window.open('$url','opdat:beholdning');\">";
		}
	}
#cho "select box2 from grupper where art = 'DIV' and kodenr = '5'<br>";
#exit;
} # endfunc linjeopdat

#############################################################################################

function bogfor_levering($id,$gruppe,$linje_id,$antal,$box1,$box2,$box3,$box4) {

# Denne funktion bruges ikke.....

	global $levdate;
	global $ref;
	$r=db_fetch_array(db_select("select * from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__));
	$amount=$r['kostpris']*$antal; # OBS antal maa ikke hentes fra ordrelinjer da alt ikke nodvendigvis leveres 
	$projekt=$r['projekt']*1;
	$ansat=$r['ansat']*1;
	$afd=0;
	$beskrivelse="Levering ordre id $id";

	$r=db_fetch_array(db_select("select beholdning from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
	$beholdning=$r['beholdning'];
	
	if ($beholdning<$antal) $antal=$beholdning; #lagerværdi flyttes til varekøb for den del af leveringen som ér på lager

	if ($amount>0) {
		$konto1=$box2;
		$konto2=$box4;
	}	else {
		$konto1=$box2;
		$konto2=$box4;
		$amount*-1;
	}
	$logdate=date("Y-m-d");
	$logtime=date("H:i");

#cho "insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0','$levdate','$beskrivelse','$konto1','--','0','$amount','0','$afd','$logdate','$logtime','$projekt','$ansat','$id')<br>";
	db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('0','$levdate','$beskrivelse','$konto1','--','0','$amount','0','$afd','$logdate','$logtime','$projekt','$ansat','$id')",__FILE__ . " linje " . __LINE__);
#cho "insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0','$levdate','$beskrivelse','$konto2','--','$amount','0','0','$afd','$logdate','$logtime','$projekt','$ansat','$id')<br>";
	db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('0','$levdate','$beskrivelse','$konto2','--','$amount','0','0','$afd','$logdate','$logtime','$projekt','$ansat','$id')",__FILE__ . " linje " . __LINE__);
} # endfunc bogfor_levering

function batch_salg_lev($id, $levdate, $fakturadate, $beholdning, $vare_id, $antal, $pris, $nettopris, $linje_id, $linje_nr, $posnr, $serienr, $lager){

	# Denne funktion bruges til ved levering af varer som er underlagt batchkontrol 
	# Kaldes fra funktionen linjeopdat... 


	global $sn_id;
	global $lev_nr;
	global $fp;

	$rest=$antal;
	$sn_start=0;
	$kobsbelob=0;
	$a=0;
	$res_sum=0;
	$res_linje_antal=0;

	if (!db_fetch_array(db_select("select * from reservation where linje_id = $linje_id",__FILE__ . " linje " . __LINE__))) batch($linje_id);  #Hvis der ikke manuelt er reserveret varer tages automatisk fra den ldste indkbsordre
	$query = db_select("select * from reservation where linje_id = $linje_id",__FILE__ . " linje " . __LINE__); #Finder reserverede varer som er koebt hjem
	while (($row =db_fetch_array($query))&&($res_sum<$antal)) {
		$x++;
		$batch_kob_id[$x]=$row['batch_kob_id'];
		$res_antal[$x]=$row['antal'];
		$res_sum=$res_sum+$row['antal'];
		$lager=$row['lager'];
		if ($res_sum>=$antal){  #Indsat 091106 for
			$diff[$x]=$res_sum-$antal;
			$res_antal[$x]=$res_antal[$x]-$diff[$x];
			$res_sum=$antal;
		}
	}
	$res_linje_antal=$x;
	$rest=$rest-$res_sum;

	if ($rest>0) {  #Hvis ikke alle varer er koebt hjem eller reserveret saaaa....
		if ($r=db_fetch_array(db_select("select * from reservation where batch_salg_id = $linje_id*-1 and antal = $rest",__FILE__ . " linje " . __LINE__))) { #Finder reserverede varer som er bestilt hos lev.
			#Hvis linjen eksisterer indsættes en linje i batch_salg 
			db_modify("insert into batch_salg(vare_id, linje_id, salgsdate, ordre_id, antal, lev_nr) values ($vare_id, $linje_id, '$levdate', $id, $rest, '$lev_nr')",__FILE__ . " linje " . __LINE__);
			$q2 = db_select("select id from batch_salg where vare_id=$vare_id and linje_id=$linje_id and salgsdate='$levdate' and ordre_id=$id and antal=$rest and	lev_nr='$lev_nr' order by id desc",__FILE__ . " linje " . __LINE__);
			$r2 =db_fetch_array($q2);
			$batch_salg_lev_id=$r2['id']; #Reservationen opdateres med ID fra batch salg   
			db_modify("update reservation set batch_salg_id='$batch_salg_lev_id' where batch_salg_id=$linje_id*-1",__FILE__ . " linje " . __LINE__);
			lagerstatus($vare_id, $lager, $rest); 
		}
		else {
			print "<BODY onLoad=\"javascript:alert('Reserveret antal stemmer ikke overens med antal til levering (pos nr: $posnr)')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	}
	else $rest=$antal;

	for ($x=1; $x<= $res_linje_antal; $x++) {
		$query = db_select("select * from batch_kob where id=$batch_kob_id[$x]",__FILE__ . " linje " . __LINE__);
		if ($row =db_fetch_array($query)) {
			$kob_antal=$row['antal'];
			$kob_rest=$row['rest'];
			$kob_ordre_id=$row['ordre_id'];
			$kob_pris=$row['pris'];
			$lager=$row['lager'];
			if (!$kob_pris) {$kob_pris='0';}
			$kob_rest=$kob_rest-$res_antal[$x];
#cho "A update batch_kob set rest=$kob_rest where id=$batch_kob_id[$x]<br>";
			db_modify("update batch_kob set rest=$kob_rest where id=$batch_kob_id[$x]",__FILE__ . " linje " . __LINE__);
			db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, lev_nr) values ($batch_kob_id[$x], $vare_id, $linje_id, '$levdate', $id, $res_antal[$x], '$lev_nr')",__FILE__ . " linje " . __LINE__);
			$query2 = db_select("select id from batch_salg where batch_kob_id=$batch_kob_id[$x] and vare_id=$vare_id and linje_id=$linje_id and salgsdate='$levdate' and ordre_id=$id and antal=$res_antal[$x] and	lev_nr='$lev_nr' order by id desc",__FILE__ . " linje " . __LINE__);
			$row2 =db_fetch_array($query2);
			if ($serienr) {db_modify("update serienr set batch_salg_id=$row2[id] where salgslinje_id=$linje_id",__FILE__ . " linje " . __LINE__);}
			db_modify("update ordrelinjer set leveres='0' where id='$linje_id'",__FILE__ . " linje " . __LINE__);
			if ($diff[$x]) db_modify("update reservation set antal='$diff[$x]' where linje_id='$linje_id' and vare_id='$vare_id' and batch_kob_id='$batch_kob_id[$x]'",__FILE__ . " linje " . __LINE__);
			else db_modify("delete from reservation where linje_id='$linje_id' and vare_id='$vare_id' and batch_kob_id='$batch_kob_id[$x]'",__FILE__ . " linje " . __LINE__);
			lagerstatus($vare_id, $lager, $rest);
			$rest=0;
		}	else {
			print "<BODY onLoad=\"javascript:alert('Hmm - Indkbsordre kan ikke findes - levering kan ikke foretages - Kontakt systemadministrator')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	}
} # endfunc batch_salg_lev
###############################################################
function lagerstatus ($vare_id, $lager, $antal) {
	global $ref;

	# Denne funktion bruges til regulering af lagerbeholdning i tilfælde hvor der er flere lagre 
	# Kaldes fra funktionen batch_salg_lev... 


	if (!$lager) {
		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'",__FILE__ . " linje " . __LINE__))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager=$row['kodenr'];}
		}
	}
	$lager=$lager*1;

	$query = db_select("select * from lagerstatus where vare_id='$vare_id' and lager='$lager'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$tmp=$row['beholdning']-$antal;
		db_modify("update lagerstatus set beholdning=$tmp where id=$row[id]",__FILE__ . " linje " . __LINE__);
	}
	else { db_modify("insert into lagerstatus (vare_id, lager, beholdning) values ($vare_id, $lager, -$antal)",__FILE__ . " linje " . __LINE__);}
}
###############################################################
function krediter($id, $levdate, $beholdning, $vare_id, $antal, $pris, $linje_id, $serienr, $kred_linje_id)
{
	global $sn_id;
	global $lev_nr;
	global $fp;

	$rest=$antal;
	$sn_start=0;
	$kobsbelob=0;
	$a=0;
	$res_sum=0;

	$query = db_select("select posnr, kred_linje_id from ordrelinjer where id=$linje_id",__FILE__ . " linje " . __LINE__);
	$row =db_fetch_array($query);
	$kred_linje_id=$row['kred_linje_id'];
	$posnr=$row['posnr'];

	$x=0;
	$q = db_select("select * from batch_salg where linje_id=$kred_linje_id",__FILE__ . " linje " . __LINE__);
	while ($r =db_fetch_array($q)) {
		$x++;
		$batch_kob_id[$x]=$r['batch_kob_id'];
		$batch_kob_antal[$x]=$r['antal'];
		if ($batch_kob_antal[$x]>$antal) $batch_kob_antal[$x]=$antal;
		if (!$batch_kob_id[$x]) {
			?>
				<script language="Javascript">
				<!--
				alert ("Der er observeret en uoverensstemmelse mellem mellem oprindelig ordre og denne (pos nr: <?php echo $posnr ?>)\nRapporter venligst til udviklingsteamet.  mail: fejl@saldi.dk")
				//-->
				</script>
			<?php
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	}
	db_modify("insert into batch_kob(vare_id, linje_id, kobsdate, ordre_id, antal, rest) values ($vare_id, $linje_id, '$levdate', $id, $antal, $antal)",__FILE__ . " linje " . __LINE__);
	$r=db_fetch_array(db_select("select max(id) as id from batch_kob where linje_id=$linje_id",__FILE__ . " linje " . __LINE__));
	$batch_kob_id=$r['id'];
	lagerstatus($vare_id, $lager,-$antal);
	if ($serienr || $serienr=='0') {
		$q = db_select("select * from serienr where salgslinje_id=-$kred_linje_id",__FILE__ . " linje " . __LINE__);
		while ($r =db_fetch_array($q)) {
			$serienr=$r['serienr'];
			db_modify("insert into serienr (kobslinje_id, vare_id, batch_kob_id, serienr, batch_salg_id, salgslinje_id) values ('$linje_id','$vare_id', $batch_kob_id, '$r[serienr]','0','0')",__FILE__ . " linje " . __LINE__);
		}
	}
} # endfunc krediter

###############################################################
function krediter_pos($id) {
	global $brugernavn;

	$r=db_fetch_array(db_select("select * from ordrer where id=($id)",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['konto_id']*1;
	$firmanavn=addslashes($r['firmanavn']);
	$addr1=addslashes($r['addr1']);
	$addr2=addslashes($r['addr2']);
	$postnr=addslashes($r['postnr']);
	$bynavn=addslashes($r['bynavn']);
	$land=addslashes($r['land']);
	$kontakt=addslashes($r['kontakt']);
	$email=addslashes($r['email']);
	$mail_fakt=addslashes($r['mail_fakt']);
	$udskriv_til=addslashes($r['udskriv_til']);
	$kundeordnr=addslashes($r['kundeordnr']);
	$lev_navn=addslashes($r['lev_navn']);
	$lev_addr1=addslashes($r['lev_addr1']);
	$lev_addr2=addslashes($r['lev_addr2']);
	$lev_postnr=addslashes($r['lev_postnr']);
	$lev_bynavn=addslashes($r['lev_bynavn']);
	$lev_kontakt=addslashes($r['lev_kontakt']);
	$ean=addslashes($r['ean']);
	$institution=addslashes($r['institution']);
	$betalingsbet=addslashes($r['betalingsbet']);
	$betalingsdage=addslashes($r['betalingsdage']);
	$kontonr=addslashes($r['kontonr']);
	$cvrnr=addslashes($r['cvrnr']);
	$art=addslashes($r['art']);
	$valuta=addslashes($r['valuta']);
	$valutakurs=$r['valutakurs']*1;
	$sprog=addslashes($r['sprog']);
	$ordredate=addslashes($r['ordredate']);
	$levdate=addslashes($r['levdate']);
	$fakturadate=addslashes($r['fakturadate']);
	$notes=addslashes($r['notes']);
	$ordrenr=$r['ordrenr']*1;
	$sum=$r['sum']*-1;
	$momssats=$r['momssats']*1;
#	$ref=addslashes($r['ref']);
	$fakturanr=$r['fakturanr']*1;
	$kred_ord_id=$r['kred_ord_id']*1;
	$lev_adr=addslashes($r['lev_adr']);
	$kostpris=$r['kostpris']*-1;
	$moms=$r['moms']*-1;
	$hvem=addslashes($r['hvem']);
	$tidspkt=addslashes($r['tidspkt']);
	$pbs=addslashes($r['pbs']);
	$mail=addslashes($r['mail']);
	$mail_cc=addslashes($r['mail_cc']);
	$mail_bcc=addslashes($r['mail_bcc']);
	$mail_subj=addslashes($r['mail_subj']);
	$mail_text=addslashes($r['mail_text']);
	$felt_1=addslashes($r['felt_1']);
	$felt_2=addslashes($r['felt_2']);
	$felt_3=addslashes($r['felt_3']);
	$felt_4=addslashes($r['felt_4']);
	$felt_5=addslashes($r['felt_5']);
	$vis_lev_addr=addslashes($r['vis_lev_addr']);
	$betalt=addslashes($r['betalt']);
	$projekt=addslashes($r['projekt']);

	db_modify("insert into ordrer
		(konto_id,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,email,mail_fakt,udskriv_til,kundeordnr,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,ean,institution,betalingsbet,betalingsdage,kontonr,cvrnr,art,valuta,valutakurs,sprog,ordredate,levdate,notes,ordrenr,sum,momssats,status,ref,kred_ord_id,lev_adr,kostpris,moms,hvem,tidspkt,pbs,mail,mail_cc,mail_bcc,mail_subj,mail_text,felt_1,felt_2,felt_3,felt_4,felt_5,vis_lev_addr,betalt,projekt)
		values
		('$konto_id','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$email','$mail_fakt','$udskriv_til','$kundeordnr','$lev_navn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt','$ean','$institution','$betalingsbet','$betalingsdage','$kontonr','$cvrnr','$art','$valuta','$valutakurs','$sprog','$ordredate','$levdate','$notes','$ordrenr','$sum','$momssats','1','$brugernavn','$kred_ord_id','$lev_adr','$kostpris','$moms','$hvem','$tidspkt','$pbs','$mail','$mail_cc','$mail_bcc','$mail_subj','$mail_text','$felt_1','$felt_2','$felt_3','$felt_4','$felt_5','$vis_lev_addr','$betalt','$projekt')
	",__FILE__ . " linje " . __LINE__);

	$r=db_fetch_array(db_select("select max(id) as id from ordrer where ref = '$brugernavn'",__FILE__ . " linje " . __LINE__));
	$ny_id=$r['id'];

	$q=db_select("select * from ordrelinjer where ordre_id='$id' and posnr >= '0' and varenr !='rabat'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$posnr=$r['posnr']*1;
		$pris=$r['pris']*1;
		$rabat=$r['rabat']*1;
		$vare_id=$r['vare_id']*1;
		$antal=$r['antal']*-1;
		$leveres=$r['leveres']*1;
		$leveret=$r['leveret']*1;
		$bogf_konto=$r['bogf_konto']*1;
		$kred_linje_id=$r['kred_linje_id']*1;
		$momsfri=addslashes($r['momsfri']);
		$kostpris=$r['kostpris']*1;
		$samlevare=addslashes($r['samlevare']);
		$rabatgruppe=$r['rabatgruppe']*1;
		$folgevare=$r['folgevare']*1;
		$m_rabat=$r['m_rabat']*1;
		$beskrivelse=addslashes($r['beskrivelse']);
		$bogfort_af=addslashes($r['bogfort_af']);
		$enhed=addslashes($r['enhed']);
		$hvem=addslashes($r['hvem']);
		$lev_varenr=addslashes($r['lev_varenr']);
		$oprettet_af=addslashes($r['oprettet_af']);
		$serienr=addslashes($r['serienr']);
		$tidspkt=addslashes($r['tidspkt']);
		$varenr=addslashes($r['varenr']);
		$momssats=$r['momssats']*1;
		$projekt=addslashes($r['projekt']);
		$kdo=addslashes($r['kdo']);
		$rabatart=addslashes($r['rabatart']);
		db_modify("insert into ordrelinjer
			(posnr,pris,rabat,ordre_id,vare_id,antal,leveres,leveret,bogf_konto,kred_linje_id,momsfri,kostpris,samlevare,rabatgruppe,folgevare,m_rabat,beskrivelse,bogfort_af,enhed,hvem,lev_varenr,oprettet_af,serienr,tidspkt,varenr,momssats,projekt,kdo,rabatart) 
			values 
			('$posnr','$pris','$rabat','$ny_id','$vare_id','$antal','$leveres','$leveret','$bogf_konto','$kred_linje_id','$momsfri','$kostpris','$samlevare','$rabatgruppe','$folgevare','$m_rabat','$beskrivelse','$bogfort_af','$enhed','$hvem','$lev_varenr','$oprettet_af','$serienr','$tidspkt','$varenr','$momssats','$projekt','$kdo','$rabatart')
		",__FILE__ . " linje " . __LINE__);
	}
	return($ny_id);
}

###############################################################
function batch ($linje_id)
{
	$lager='';

	$leveres=0;
	$query = db_select("select * from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$antal=$row['antal'];
		$leveres=$row['leveres'];
		$posnr=$row['posnr'];
		$vare_id=$row['vare_id'];
		$varenr=$row['varenr'];
		$serienr=$row['serienr'];
		$query = db_select("select status, art, konto_id, ref from ordrer where id = '$row[ordre_id]'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$konto_id=$row['konto_id'];
		$status=$row['status'];
		$art=$row['art'];

		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$row[ref]'",__FILE__ . " linje " . __LINE__))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager=$row['kodenr']*1;}
		}
	}

	$query = db_select("select * from batch_salg where linje_id = $linje_id",__FILE__ . " linje " . __LINE__);
	while($row = db_fetch_array($query)) $leveres=$antal-$row['antal'];

	if (($antal>=0)&&($art!="DK")){
		$x=0;
		$rest=array();
		$lev_rest=$leveres;
		if ($lager) $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 and lager = $lager order by kobsdate",__FILE__ . " linje " . __LINE__);
		else $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 order by kobsdate",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$x++;
			$batch_kob_id[$x]=$row['id'];
			$kobsdate[$x]=$row['kobsdate'];
			$rest[$x]=$row['rest'];
			$reserveret[$x]=0;
#			$pris[$x]=$row[pris];
			$q2 = db_select("select ordrenr from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
			$r2 = db_fetch_array($q2);
			$ordrenr[$x]=$r2[ordrenr];
			$q2 = db_select("select * from reservation where batch_kob_id=$row[id]",__FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				if ($r2['linje_id']!=$linje_id) {$reserveret[$x]=$reserveret[$x]+$r2['antal'];}
				else {
					$valg[$x]=$r2['antal'];
					$valgt.=$r2['antal'];
				}
			}
			$k_ordreantal=$x;
			if (!$valgt) {
				if ($rest[$x]>=$lev_rest) {
					$valg[$x]=$lev_rest;
					$lev_rest=0;
				}
				else {
					$valg[$x]=$rest[$x];
					$lev_rest=$lev_rest-$rest[$x];
				}
			}
		}
	$batch_antal=$x;
	}
	if ($lev_rest==0) {
		 db_modify("delete from reservation where linje_id=$linje_id",__FILE__ . " linje " . __LINE__);
		 $temp=$linje_id*-1;
		 db_modify("delete from reservation where batch_salg_id=$temp",__FILE__ . " linje " . __LINE__);
		 for ($x=1; $x<=$batch_antal; $x++){
			 $lager=$lager*1;
			 if (($valg[$x]>0)&&(!$res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_kob_id, antal, lager) values ($linje_id, $vare_id, $batch_kob_id[$x], $valg[$x], $lager)",__FILE__ . " linje " . __LINE__);}
			 elseif (($valg[$x]>0)&&($res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_salg_id, antal, lager) values ($res_linje_id[$x], $vare_id, $temp, $valg[$x], $lager)",__FILE__ . " linje " . __LINE__);}
		 }
	}
}
###############################################################
function samlevare($id,$art,$linje_id, $v_id, $leveres) {

	if ($art=='DO' || $art=='PO') {
		include ("../includes/fuld_stykliste.php");
		list($vare_id, $stk_antal, $antal) = fuld_stykliste($v_id, '', 'basisvarer');
		for ($x=1; $x<=$antal; $x++) {
#cho "select * from varer where id='$vare_id[$x]'<br>";
			if ($r=db_fetch_array(db_select("select * from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__))) {
				$stk_antal[$x]=$stk_antal[$x]*$leveres;
#cho "insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, samlevare, posnr) values ('$id', '$r[varenr]', '$vare_id[$x]', '$r[beskrivelse]', '$stk_antal[$x]', '$stk_antal[$x]', '0', '$linje_id', '100' )<br>";
				db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, samlevare, posnr) values ('$id', '$r[varenr]', '$vare_id[$x]', '$r[beskrivelse]', '$stk_antal[$x]', '$stk_antal[$x]', '0', '$linje_id', '100' )",__FILE__ . " linje " . __LINE__);
			}
		}
	} else {
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
#cho "insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, samlevare, posnr) 
#					values 
#				('$id', '$r[varenr]', '$r[vare_id]', '$r[beskrivelse]', '$ny_antal', '$ny_antal', 0, $linje_id, '$r[posnr]' )<br>";
				db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, samlevare, posnr) 
					values 
				('$id', '$r[varenr]', '$r[vare_id]', '$r[beskrivelse]', '$ny_antal', '$ny_antal', 0, $linje_id, '$r[posnr]' )",__FILE__ . " linje " . __LINE__);
			}
		}
	}
#exit;
}
###############################################################
function bogfor($id,$webservice) {
	global $regnaar;
	global $fakturadate;
	global $valutakurs;
	global $pbs;
	global $mail_fakt;
	global $db;
	global $brugernavn;

	$query = db_select("select * from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$konto_id=$row['konto_id'];
	$ordredate=$row['ordredate'];
	$levdate=$row['levdate'];
	$fakturadate=$row['fakturadate'];
	$nextfakt=$row['nextfakt'];
	$art=$row['art'];
	$kred_ord_id=$row['kred_ord_id'];
	$valuta=$row['valuta'];
	$art=$row['art'];
	$fakturanr=$row['fakturanr'];
	if ($art=='PO') {
		$sum=$row['sum'];
		$betaling=$row['felt_1'];
		$betaling2=$row['felt_3'];
		if ($row['betalingsbet']=='Kontant') $konto_id='0';
		$r=db_fetch_array(db_select("select box2 from grupper where art='OreDif'",__FILE__ . " linje " . __LINE__));
		$difkto=$r['box2'];
	}

	if ($row['status']>'2'){
		return("invoice allready created for order id $id");
	}
	$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);

	if ($row = db_fetch_array($query)){
		$year=trim($row['box2']);
		$aarstart=str_replace(" ","",$year.$row['box1']);
		$year=trim($row['box4']);
		$aarslut=str_replace(" ","",$year.$row['box3']);
	}
	$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);

	if (!$fakturadate){
		if ($webservice) {
			return("missing invoicedate for order $id");
		} else {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/fakturadato.php?id=$id&pbs=$pbs&mail_fakt=$mail_fakt&returside=bogfor.php\">";
			exit;
		}
	}
	if ($valuta && $valuta!='DKK') {
		if ($r= db_fetch_array(db_select("select valuta.kurs as kurs, grupper.box3 as difkto from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=".nr_cast("grupper.kodenr")." and valuta.valdate <= '$fakturadate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
			$valutakurs=$r['kurs']*1;
			$difkto=$r['difkto']*1;
			if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))) {
				if ($webservice) return("Kontonr $difkto (kursdiff) eksisterer ikke");
				else {
					return("Kontonr $difkto (kursdiff) eksisterer ikke");
				}
			}
		} else {
			$tmp = dkdato($fakturadate);
			return("Der er ikke nogen valutakurs for $valuta den $tmp (fakturadatoen).");
		}
	} else {
		$valuta='DKK';
		$valutakurs=100;
	}
	if (!$levdate){
		if ($webservice) return ("Missing deliverydate");
		else return ("Leveringsdato SKAL udfyldes");
	}
	if ($levdate<$ordredate){
		if ($webservice) return ("Deliverydate prior to orderdate");
		else return ("Leveringsdato er f&oslash;r ordredato");
	}

	if ($fakturadate<$levdate)	{
		if ($webservice) return ("Invoicedate prior to orderdate");
		else return ("Fakturadato er f&oslash;r leveringsdato");
	}

	if (($nextfakt)&& ($nextfakt<=$fakturadate)){
		if ($webservice) return ("Next_invoicedate prior to invoicedate");
		else return ("Genfaktureringsdato skal v&aelig;re efter fakturadato");
	}
	list ($year, $month, $day) = explode ('-', $fakturadate);
	$year=trim($year);
	$ym=$year.$month;

	if ($art!='PO' && !$webservice && ($ym<$aarstart || $ym>$aarslut))	{
		print "<BODY onLoad=\"javascript:alert('Fakturadato udenfor regnskabs&aring;r')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	}
	if ($valuta && $valuta!='DKK') {
		if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=".nr_cast("grupper.kodenr")." and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
			$valutakurs=$r['kurs'];
		} else {
			$tmp = dkdato($ordredate);
			return("Der er ikke nogen valutakurs for $valuta den $ordredate (ordredatoen)");
		}
	}
#cho "select * from ordrelinjer where pris != '0' and m_rabat != '0' and rabat = '0' and ordre_id='$id'<br>";
	if ($r=db_fetch_array(db_select("select * from ordrelinjer where pris != '0' and m_rabat != '0' and rabat = '0' and ordre_id='$id'",__FILE__ . " linje " . __LINE__))){
#cho "$r[beskrivelse] -- $r[bogf_konto]<br>";
		$rabatkontonr=$r['bogf_konto'];
		if ($r=db_fetch_array(db_select("select box2 from grupper where art = 'DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__))) {
			if ($rabatvareid=$r['box2']*1) {
				$r=db_fetch_array(db_select("select varenr from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__));
				$rabatvarenr=$r['varenr'];
			} # else $fejl="Manglende varenummer for rabat (Indstillinger -> Diverse -> Ordrerelaterede valg)";
		} # else $fejl="Manglende varenummer for rabat (Indstillinger -> Diverse -> Ordrerelaterede valg)";
	}
	if (!$fejl) {
 		transaktion("begin");
		if ($art!="PO") {
			$fakturanr=1;
			# select max kan ikke bruges da fakturanr felt ikke er numerisk;
			$q = db_select("select fakturanr from ordrer where art = 'DO' or art = 'DK'",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)){
				if ($fakturanr <= $r['fakturanr']*1) $fakturanr = $r['fakturanr']+1;
			}
			$r=db_fetch_array(db_select("select box1 from grupper where art = 'RB' and kodenr='1'",__FILE__ . " linje " . __LINE__));
			if ($fakturanr<$r['box1']) $fakturanr=$r['box1'];
			if ($fakturanr < 1) $fakturanr = 1;
			$ny_id=array();
			$x=0;
			$q=db_select("select * from ordrelinjer where pris != '0' and m_rabat != '0' and rabat = '0' and ordre_id='$id'",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
			$x++;
				$linje_id[$x]=$r['id']*1;
				$linje_m_rabat[$x]=$r['m_rabat'];
				$linje_pris[$x]=$r['pris'];
				$linje_rabatart[$x]=$r['rabatart'];
				$linje_varenr[$x]=$r['varenr'];
				$linje_posnr[$x]=$r['posnr'];
			} 
			$linjeantal=$x;
			for ($x=1;$x<=$linjeantal;$x++) {
				$ny_id[$x]=copy_row("ordrelinjer",$linje_id[$x]);
				$pris=$linje_m_rabat[$x];
				$pris*=-1;
				$rabatpct=afrund($linje_m_rabat[$x]*100/$linje_pris[$x],2);
				($linje_rabatart[$x]=='amount')?$beskrivelse=findtekst(466,$sprog_id):$beskrivelse=findtekst(467,$sprog_id);
				$beskrivelse=str_replace('$rabatpct',$rabatpct,$beskrivelse);
#cho "update ordrelinjer set posnr=posnr+0.1,varenr='$rabatvarenr',vare_id='$rabatvareid',pris='$pris',kostpris='0',m_rabat='0',beskrivelse='$beskrivelse',bogf_konto='$rabatkontonr',kdo='on' where id=$ny_id[$x]<br>";
#exit;
				db_modify("update ordrelinjer set posnr=posnr+0.1,varenr='$rabatvarenr',vare_id='$rabatvareid',pris='$pris',kostpris='0',m_rabat='0',beskrivelse='$beskrivelse',bogf_konto='$rabatkontonr',kdo='on' where id=$ny_id[$x]",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select * from ordrelinjer where id='$ny_id[$x]'",__FILE__ . " linje " . __LINE__));
#cho "$r[id],$r[ordre_id],$r[posnr],$r[varenr],$r[vare_id],$r[pris],$r[kostpris],$r[m_rabat],$r[beskrivelse],$r[bogf_konto]<br>--<br>";
			}
		} else {
			if ($difkto && $betaling=='Kontant' && !$betaling2) {
				$tmp=$sum;
				$sum=pos_afrund($sum);
				if ($afrunding=$sum-$tmp) {
					$linje_posnr[$x]+=0.1;
					db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, beskrivelse,projekt) values ('0','1', '$afrunding', 0, '$id', '$difkto','Afrunding','$projekt')",__FILE__ . " linje " . __LINE__);
					db_modify("update ordrer set sum = '$sum' where id = '$id'",__FILE__ . " linje " . __LINE__);
				}
			}
		}
		batch_kob($id, $art);
		batch_salg($id);
#cho "update ordrer set status=3, fakturanr=$fakturanr, valutakurs=$valutakurs where id=$id<br>";
		db_modify("update ordrer set status=3, fakturanr=$fakturanr, valutakurs=$valutakurs where id=$id",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select box5 from grupper where art='DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__));
		$straksbogfor=$r['box5'];
		$svar=momsupdat($id);
		if ($art=='PO' && !$konto_id) {
			$r = db_fetch_array(db_select("select box9 from grupper where art='POS' and kodenr='1'",__FILE__ . " linje " . __LINE__));
			$straksbogfor=$r['box9'];
		}
		if ($straksbogfor) $svar=bogfor_nu($id,$webservice);
		if ($svar != "OK") {
			return($svar);
			exit;
		} else {
			transaktion("commit");
		}
	} elseif (!$svar) $svar = $fejl;
	return($svar);
} #endfunc bogfor
#############################################################################################################################
function momsupdat($id) {
	global $db;
	global $brugernavn;
	$sum=0;
	$moms=0;
	$antal_diff_moms=0; #indfort 2011.03.23 grundet momsafvigelse paa 3 ore i faktura 30283 regnskab 329

#cho "select momssats from ordrer where id = $id<br>";
	$r=db_fetch_array(db_select("select momssats from ordrer where id = $id",__FILE__ . " linje " . __LINE__));
	$momssats=$r['momssats']*1;
#cho "momssats=$momssats<br>";
	$q=db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['rabatart']=='amount') $sum+=afrund(($r['pris']-$r['rabat'])*$r['antal'],2);
		else $sum+=afrund(($r['pris']-($r['pris']/100*$r['rabat']))*$r['antal'],2);
#cho "vare id $r[vare_id] momsfri $r[momsfri]<br>";
		if ($r['vare_id'] && $r['momsfri']!='on') {
			if ($r['momssats'] > 0 && $r['momssats'] < $momssats) $varemomssats=$r['momssats']; 
			else $varemomssats=$momssats;
			if ($varemomssats!=$momssats) {
			}$antal_diff_moms++;
			if ($r['rabatart']=='amount') $moms+=afrund(($r['pris']-$r['rabat'])*$r['antal']/100*$varemomssats,2);
			else $moms+=afrund(($r['pris']-($r['pris']/100*$r['rabat']))*$r['antal']/100*$varemomssats,2);
		} else if ($r['vare_id']) $antal_diff_moms++;
	}
	if (!$antal_diff_moms) $moms=afrund($sum/100*$momssats,2);
	$sum*=1; $moms*=1;
	db_modify("update ordrer set sum=$sum, moms=$moms where id = '$id'",__FILE__ . " linje " . __LINE__);
	return("OK");
}
###########################################################
function batch_salg($id) {
	global $fakturadate;
	global $valutakurs;
	global $version;

  $r=db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
  $fifo=$r['box6'];

	$x=0;
	$query = db_select("select * from batch_salg where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		$x++;
		$batch_id[$x]=$row['id'];
		$vare_id[$x]=$row['vare_id'];
		$antal[$x]=$row['antal'];
		$serienr[$x]=$row['serienr'];
		$batch_kob_id[$x]=$row['batch_kob_id'];
		$batch_linje_id[$x]=$row['linje_id'];
		# Indsat 20101129 - Der bliver undertiden oprettet batch_salg linjer uden tilhorende ordrelinje hvilket giver fejl. Aarsag skal findes.
		if (!db_fetch_array(db_select("select id from ordrelinjer where id = '$batch_linje_id[$x]'",__FILE__ . " linje " . __LINE__))) {
			db_modify("delete from batch_salg where id='$batch_id[$x]'",__FILE__ . " linje " . __LINE__);
			$x--;
		}
	}
	$linjeantal=$x;

	for ($x=1; $x<=$linjeantal; $x++) {
		$kostpris=0;

		$query = db_select("select id,pris,rabat,projekt,bogf_konto,kostpris from ordrelinjer where id = '$batch_linje_id[$x]'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$ordre_linje_id=$row['id'];
		$pris = $row['pris']-($row['pris']*$row['rabat']/100);
		$linjekostpris = $row['kostpris']*1;
		$projekt=$row['projekt'];
		$bogf_konto=$row['bogf_konto'];
		if ($valutakurs) {
			$pris=afrund($pris*$valutakurs/100,3);
			$linjekostpris=afrund($linjekostpris*$valutakurs/100,3);
		}
		db_modify("update batch_salg set pris=$pris, fakturadate='$fakturadate' where id='$batch_id[$x]'",__FILE__ . " linje " . __LINE__);
		if ($batch_kob_id[$x]) {
			$query = db_select("select pris, ordre_id from batch_kob where id = '$batch_kob_id[$x]'",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {
				$kostpris=$row['pris'];
				if ($row['ordre_id']) {
					$query = db_select("select status from ordrer where id = '$row[ordre_id]'",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					if ($row['status']){$kobsstatus=$row['status'];}
				}
				else {$kobsstatus=0;}
			}
		}

		$query2 = db_select("select gruppe from varer where id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$gruppe=$row2['gruppe'];
		$query2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$box1=trim($row2['box1']); $box2=trim($row2['box2']); $box3=trim($row2['box3']); $box4=trim($row2['box4']); $box8=trim($row2['box8']); $box9=trim($row2['box9']);
		if ($bogf_konto) $box4=$bogf_konto;
		if ($version < "3.0.6") $projekt=$projekt*1;
		db_modify("update ordrelinjer set bogf_konto='$box4',projekt='$projekt' where id='$ordre_linje_id'",__FILE__ . " linje " . __LINE__);
#cho"Fifo $fifo<br>";
		if ($fifo && !$box9) {
			$y=0;
			$mangler=$antal[$x];
			$kostsum=0;
#cho "select * from batch_kob where rest>'0' and vare_id='$vare_id[$x]' order by fakturadate,id<br>";
			$q=db_select("select * from batch_kob where rest>'0' and vare_id='$vare_id[$x]' order by fakturadate,id",__FILE__ . " linje " . __LINE__);
			while ($mangler && $r=db_fetch_array($q)) {
				$rest=$r['rest'];
				if ($mangler && $rest>=$mangler) {
					$kostsum+=$mangler*$r['pris'];
					$rest=$rest-$mangler;
					$mangler=0;
				} elseif ($mangler && $rest < $mangler) {
					$mangler=$mangler-$rest;
					$rest=0;
				}
#cho " update batch_kob set rest='$rest' where id='$r[id]'<br>";
				db_modify("update batch_kob set rest='$rest' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
			}
			if ($antal[$x]){
				$kostpris=$kostsum/$antal[$x];
				$kostpris*=1;
#cho "update ordrelinjer set kostpris='$kostpris' where id='$ordre_linje_id'<br>";
				db_modify("update ordrelinjer set kostpris='$kostpris' where id='$ordre_linje_id'",__FILE__ . " linje " . __LINE__);
			}
			if ($mangler) { #så bliver lagerbeholdningen negativ
			}
		}
#exit;
		if ($box9=='on'){ # box 9 betyder at der anvendes batch styring
			if (!$batch_kob_id[$x]) { # saa er varen ikke paa lager, dvs at indkobsordren skal findes i tabellen reservation
				$query = db_select("select linje_id, lager from reservation where batch_salg_id = '$batch_id[$x]'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$res_antal=$res_antal+$row['antal'];
				$res_linje_id=$row['linje_id'];
				$lager=$row['lager'];
				$r1 = db_fetch_array(db_select("select ordre_id, pris, rabat, projekt from ordrelinjer where id = '$res_linje_id'",__FILE__ . " linje " . __LINE__));
				$kob_ordre_id = $r1['ordre_id'];
				$projekt = $r1['projekt'];
				$r2 = db_fetch_array(db_select("select valutakurs from ordrer where id = '$kob_ordre_id'",__FILE__ . " linje " . __LINE__));
				$kostpris = ($r1['pris']-($r1['pris']*$r1['rabat']/100))*$r2['valutakurs']/100;
				db_modify("update ordrelinjer set kostpris = '$kostpris' where id='$ordre_linje_id'",__FILE__ . " linje " . __LINE__);
			# Hvis levering er sket i flere omgange vil der vaere flere batch_salg linjer paa samme kobs linje, derfor nedenstaende.
				if ($row = db_fetch_array(db_select("select id from batch_kob where linje_id='$res_linje_id' and vare_id='$vare_id[$x]' and ordre_id='$kob_ordre_id'",__FILE__ . " linje " . __LINE__))) {
					$batch_kob_id[$x]=$row['id'];
				} else {
					db_modify("insert into batch_kob (linje_id, vare_id, ordre_id, pris, lager) values ('$res_linje_id','$vare_id[$x]','$kob_ordre_id','$kostpris','$lager')",__FILE__ . " linje " . __LINE__); #Antal indsaettes ikke - dette styres i "reservation"
					$row = db_fetch_array(db_select("select id from batch_kob where linje_id='$res_linje_id' and vare_id='$vare_id[$x]' and ordre_id='$kob_ordre_id'",__FILE__ . " linje " . __LINE__));
					$batch_kob_id[$x]=$row['id'];
				}
				db_modify("update reservation set batch_kob_id='$batch_kob_id[$x]' where linje_id = '$res_linje_id'",__FILE__ . " linje " . __LINE__);
				db_modify("update batch_salg set batch_kob_id='$batch_kob_id[$x]' where id='$batch_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
			# Nedenstående er muligvis overflødig - skal testes.
			$row = db_fetch_array(db_select("select pris,fakturadate from batch_kob where id='$batch_kob_id[$x]'",__FILE__ . " linje " . __LINE__)); # kostprisen findes..
			if ($row['fakturadate']) $kostpris=$row['pris']*1; #Hvis fakturadatoen ikker er sat, er købsordren ikke bogført og kostprisen fra ordrelinjer anvendes.
			else $kostpris=$linjekostpris;
			if ($box1 && $box2 && $kostpris) { #kostvaerdien flyttes fra "afgang varelager" til "varekob".- hvis der ikke bogfoeres direkte paa varekobs kontoen
				#	if ($valutakurs) $pris=$pris*100/$valutakurs;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1','$antal[$x]', '$kostpris', 0, '$id', '$box2','$projekt')",__FILE__ . " linje " . __LINE__);
				$kostpris=$kostpris*-1;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1','$antal[$x]', '$kostpris', 0, '$id', '$box3','$projekt')",__FILE__ . " linje " . __LINE__);
			}
		} elseif ($box8=='on') { # hvis box8 er 'on' er varen lagerfoert
			if (!$fifo) {
				$row = db_fetch_array(db_select("select kostpris from varer where id=$vare_id[$x]",__FILE__ . " linje " . __LINE__));
				$kostpris=$row['kostpris']*1;
#			if ($valutakurs) $kostpris=$kostpris*100/$valutakurs;
				db_modify("update ordrelinjer set kostpris = $kostpris where id=$ordre_linje_id",__FILE__ . " linje " . __LINE__);
			}
			if ($box1 && $box2 && $kostpris) {
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1','$antal[$x]', '$kostpris', '0', '$id', '$box2','$projekt')",__FILE__ . " linje " . __LINE__);
				$kostpris=$kostpris*-1;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1','$antal[$x]', '$kostpris', '0', '$id', '$box3','$projekt')",__FILE__ . " linje " . __LINE__);
			}
		}
	}
} # endfunc batch_salg

####### batch_kob anvendes hvis der krediteres en vare som ikke er blevet solgt - og derfor betragtes som et varekoeb #######
function batch_kob($id, $art)
{
	global $fakturadate;
	global $valutakurs;

	$query = db_select("select * from batch_kob where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		$x++;
		$batch_id=$row['id'];
		$vare_id=$row['vare_id'];
		$antal=$row['antal'];
		$projekt=$row['projekt'];
		$serienr=$row['serienr'];
		$batch_kob_id=$row['batch_kob_id'];
		$query2 = db_select("select id, pris, rabat, projekt, bogf_konto from ordrelinjer where id = $row[linje_id]",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$ordre_linje_id=$row2['id'];
		$bogf_konto=$row2['bogf_konto'];
		$pris = $row2[pris]-($row2['pris']*$row2['rabat']/100);
		if ($row['pris']) {$diff = $pris-$row['pris'];}
		db_modify("update batch_kob set pris=$pris, fakturadate='$fakturadate' where id=$batch_id",__FILE__ . " linje " . __LINE__);
 		$query2 = db_select("select gruppe from varer where id = $vare_id",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$gruppe=$row2['gruppe'];
		$query2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$box1=trim($row2['box1']); $box2=trim($row2['box2']); $box3=trim($row2['box3']); $box4=trim($row2['box4']); $box8=trim($row2['box8']); $box9=trim($row2['box9']);
#cho "B update ordrelinjer set bogf_konto=$box4 where id=$ordre_linje_id<br>";
		if ($bogf_konto) $box4=$bogf_konto;
		db_modify("update ordrelinjer set bogf_konto=$box4 where id=$ordre_linje_id",__FILE__ . " linje " . __LINE__);
		if ($box9=='on' && $box1 && $box2){ # Batchkontrol og lagerværdi føres.
			$pris=$pris-$diff;
			$pris=$pris*1;
			if ($valutakurs && $pris) $pris=$pris*100/$valutakurs;
			db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1','$antal','$pris','0','$id','$box3','$projekt')",__FILE__ . " linje " . __LINE__);
			$pris=$pris*-1;
			db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1','$antal','$pris','0','$id','$box2','$projekt')",__FILE__ . " linje " . __LINE__);
		}
	}
} # endfunc batch_kob
###############################################################
function bogfor_indbetaling($id,$webservice) {
	include("../includes/genberegn.php");
	include("../includes/forfaldsdag.php");
	global $db;
	global $regnaar;
	global $valuta;
	global $valutakurs;
	global $difkto;
	global $title;
	global $kasse;

	$q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$art=$r['art'];
		$konto_id=$r['konto_id'];
		$kundekontonr=str_replace(" ","",$r['kontonr']);
		$firmanavn=trim($r['firmanavn']);
		$modtagelse=$r['modtagelse'];
		$transdate=($r['fakturadate']);
		$fakturanr=$r['fakturanr'];
		$ordrenr=$r['ordrenr'];
#cho "$firmanavn | $ordrenr<br>";
		$valuta=$r['valuta'];
		$kred_ord_id=$r['kred_ord_id'];
		if (!$valuta) $valuta='DKK';
		$projekt[0]=$r['projekt'];
		$betalingsbet=$r['betalingsbet'];
		$betalingsdage=$r['betalingsdage']*1;
		$betalt=$r['betalt']*1;
		$sum=$r['sum']+$moms;
		$betaling=$r['felt_1'];
		$modtaget=$r['felt_2'];
		$kasse=$r['felt_5'];
		$ansat='0';
		$beskrivelse="Indbetaling konto: $kundekontonr";

		$tmp=$sum*-1;
		db_modify("insert into openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr, valuta, valutakurs,projekt) values ('$konto_id', '$kundekontonr', '$fakturanr', '$tmp', '$beskrivelse', '$udlign', '$transdate', '0', '$id', '$valuta', '$valutakurs','$projekt[0]')",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select max(id) as id from openpost where konto_id = '$konto_id' and faktnr = '$fakturanr' and refnr='$id'",__FILE__ . " linje " . __LINE__));
		$openpost_id=$r['id'];
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$r = db_fetch_array(db_select("select beskrivelse, box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['box2']; # Kontonr aendres fra at vaere leverandoerkontonr til finanskontonr
		$tekst="Kontonummer for Debitorgruppe `$r[beskrivelse]` er ikke gyldigt";
		if (!$kontonr && $webservice) return($tekst);
		elseif(!$kontonr) print "<BODY onLoad=\"javascript:alert('$tekst')\">";

		$logdate=date("Y-m-d");
		$logtime=date("H:i");
		if ($sum) {
			if ($sum>0) {$kredit=$sum; $debet='0';}
			else {$kredit='0'; $debet=$sum*-1;}

			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			$debet=afrund($debet,2);
			$kredit=afrund($kredit,2);
			db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', 0, '$logdate', '$logtime', '0', '0', '$id')",__FILE__ . " linje " . __LINE__);
		}
			$tmparray=array();
			$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
			if ($betaling=='Kontant') {
				$tmparray=explode(chr(9),$r['box2']);
				$kontonr=$tmparray[$kasse-1];
			} else {
			$tmparray=explode(chr(9),$r['box3']);
			$afd=$tmparray[$kasse-1]*1;
			$tmparray=explode(chr(9),$r['box5']);
#			if ($betaling!='kontant' && $betaling!='konto' && $betalt) {
				$kortantal=$r['box4']*1;
				$korttyper=explode(chr(9),$r['box5']);
				$kortkonti=explode(chr(9),$r['box6']);
				for($x=0;$x<$kortantal;$x++) {
					if ($betaling==$korttyper[$x]) {
						$debet=afrund($modtaget,2);
						$kredit='0';
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
						db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
						if ($modtaget != $sum) {
							$debet=0;$kredit=0;
							($modtaget>$sum)?$debet=afrund($modtaget-$sum,2):$kredit=afrund($sum-$modtaget,2);
							$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
							db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
						}
						$sum=0;
					}
/*					if ($betaling2==$korttyper[$x]) {
						$debet=afrund($modtaget2,2);
						$kredit='0';
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
						$sum=$sum-$modtaget2;
#cho "A1 insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
						db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
					}
*/				}
			}
			if ($sum>0) {$debet=$sum; $kredit='0';}
			else {$debet='0'; $kredit=$sum*-1;}

			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			$debet=afrund($debet,2);
			$kredit=afrund($kredit,2);
#cho "C insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', '0', '$logdate', '$logtime', '0', '0', '$id')<br>";
 			if ($debet || $kredit) db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', '0', '$logdate', '$logtime', '0', '0', '$id')",__FILE__ . " linje " . __LINE__);
			db_modify("update ordrer set status=4, valutakurs=$valutakurs where id=$id",__FILE__ . " linje " . __LINE__);
	}
# exit;
	return('OK');
}

function bogfor_nu($id,$webservice)
{
	include("../includes/genberegn.php");
	include("../includes/forfaldsdag.php");
	global $db;
	global $regnaar;
	global $valuta;
	global $valutakurs;
	global $difkto;
	global $title;

/*
	$r = db_fetch_array(db_select("select box7 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
	$tjek_lagerdiff=$r['box7'];
	if ($tjek_lagerdiff) {
		include("../includes/genberegn.php");
		include("../includes/lagervaerdi.php");
		$pre_stockvalue=lagervaerdi($regnaar);
		$pre_finans=finanslager($regnaar);
		$pre_lagerdiff=$pre_finans-$pre_stockvalue;
#cho "$pre_lagerdiff=$pre_finans-$pre_stockvalue<br>";
	}
*/
#	print "<table><tbody>";
	$svar="OK";

	$regnaar=$regnaar*1;

	$d_kontrol=0;
	$k_kontrol=0;
	$logdate=date("Y-m-d");
	$logtime=date("H:i");
	$q = db_select("select box1, box2, box3, box4, box5 from grupper where art='RB'",__FILE__ . " linje " . __LINE__);
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
	$projekt=array();
	$idliste=array();
  if (is_numeric($id)) $tmp="id = '".$id."'";
	else {
	$idliste=explode(",",$id);
	$antal=count($idliste);
#cho " id er array<br>";
		$tmp="(id = '".$idliste[0]."'";
		for($x=1;$x<$antal;$x++) $tmp.=" or id = '".$idliste[$x]."'";
		$tmp.=")";
	} 
	$x=0;$moms=0;$sum=0;$modtaget=0;$modtaget2=0;
#cho "select * from ordrer where $tmp<br>";
	$q = db_select("select * from ordrer where $tmp",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$art=$r['art'];
		$konto_id=$r['konto_id'];
		$kontonr=str_replace(" ","",$r['kontonr']);
		$firmanavn=trim($r['firmanavn']);
		$modtagelse=$r['modtagelse'];
		$transdate=($r['fakturadate']);
		$fakturanr=$r['fakturanr'];
		$ordrenr=$r['ordrenr'];
#cho "$firmanavn | $ordrenr<br>";
		$valuta=$r['valuta'];
		$kred_ord_id=$r['kred_ord_id'];
		if (!$valuta) $valuta='DKK';
		$projekt[0]=$r['projekt'];
		$betalingsbet=$r['betalingsbet'];
		$betalingsdage=$r['betalingsdage']*1;
		$betalt=$r['betalt']*1;
		if ($art=='PO') {
			$betaling=$r['felt_1'];
			$modtaget+=$r['felt_2'];
			$betaling2=$r['felt_3'];
			$modtaget2+=$r['felt_4'];
			$kasse=$r['felt_5'];
			if ($betalingsbet=='Kontant') {
				$konto_id=0;
				$kontonr=NULL;
			}
		}
#		$refnr;
		$moms+=$r['moms']*1;
#		else {$moms=afrund($r['sum']*$r['momssats']/100,2);}
		$sum+=$r['sum']+$r['moms'];
		$ordreantal=$x;
		$forfaldsdate=usdate(forfaldsdag($r['fakturadate'], $betalingsbet, $betalingsdage));
		if ($art=='PO') $r2= db_fetch_array(db_select("select id, afd from ansatte where initialer = '$r[ref]'",__FILE__ . " linje " . __LINE__));
		else $r2= db_fetch_array(db_select("select id, afd from ansatte where navn = '$r[ref]'",__FILE__ . " linje " . __LINE__));
		$afd=$r2['afd']*1;#sikkerhed for at 'afd' har en vaerdi
		$ansat=$r2['id']*1;
		if ($no_faktbill==1) $bilag='0';
		else $bilag=trim($fakturanr);
		$udlign=0;
	}
#	if ($sum) {
/*
		if ($art=='PO' && $betalt >= $sum) {
			$kontonr=0;
			$konto_id=0;
		}
*/
		if ($art != 'PO') {
			$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id';",__FILE__ . " linje " . __LINE__));
			$debitorgruppe=$r['gruppe'];
			$r = db_fetch_array(db_select("select box1 from grupper where art='DG' and kodenr='$debitorgruppe';",__FILE__ . " linje " . __LINE__));
			$momskode=substr(trim($r['box1']),1,1);
			if ($moms && !$momskode) return("Debitorgruppe $debitorgruppe ikke tilnkyttet en momsgruppe"); 
		} else { #saa er det en kontantordre
#			global $kasse;
#			global $betaling;
#			global $betaling2;
#			global $modtaget;
#			global $modtaget2;
			$tmparray=array();
			$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
			$tmparray=explode(chr(9),$r['box7']);
			$momskode=$tmparray[$kasse-1];
		}

		if (substr($art,1,1)=='K') {
			$beskrivelse ="Kreditnota - ".$fakturanr;
			$r=db_fetch_array(db_select("select fakturanr,fakturadate from ordrer where id='$kred_ord_id'",__FILE__ . " linje " . __LINE__));
			$tmp=$sum*-1;
			if ($r2=db_fetch_array(db_select("select * from openpost  where konto_id='$konto_id' and amount='$tmp' and faktnr='$r[fakturanr]' and transdate='$r[fakturadate]' and udlignet != '1'",__FILE__ . " linje " . __LINE__))) {
				($transdate>$r2['transdate'])?$udlign_date=$transdate:$udlign_date=$r2['transdate'];
				$r2=db_fetch_array(db_select("select max(udlign_id) as udlign_id from openpost",__FILE__ . " linje " . __LINE__)); 
				$udlign_id=$r2['udlign_id']+1;
				db_modify("update openpost set udlignet='1',udlign_date='$udlign_date',udlign_id='$udlign_id' where konto_id='$konto_id' and amount='$tmp' and faktnr='$r[fakturanr]' and transdate='$r[fakturadate]'",__FILE__ . " linje " . __LINE__);
				$udlign=1;
			}
		} elseif ($art=='PO') {
			(is_numeric($id))?$beskrivelse="Bon - ".$fakturanr:$beskrivelse="Kontantsalg kasse - ".$kasse;
		} else $beskrivelse="Faktura - ".$fakturanr;
#cho "KONTONR $kontonr<br>";
		if ($kontonr)  {
				$tmp=$sum;
			if (db_fetch_array(db_select("select id from openpost where konto_id='$konto_id' and konto_nr='$kontonr' and faktnr='$fakturanr' and amount='$tmp' and beskrivelse='$beskrivelse' and udlignet='$udlign' and transdate='$transdate' and kladde_id='$udlign' and refnr='$id' and valuta='$valuta' and valutakurs='$valutakurs' and forfaldsdate='$forfaldsdate'",__FILE__ . " linje " . __LINE__))) {
				$tekst="Bogf&oslash;ring afbrudt - tjek kontrolspor";
				print "<BODY onLoad=\"javascript:alert('$tekst')\">";
				return($tekst);
			}
			if ($udlign && $udlign_id && $udlign_date) db_modify("insert into openpost (konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,udlign_id,udlign_date,transdate,kladde_id,refnr,valuta,valutakurs,forfaldsdate,projekt) values ('$konto_id','$kontonr','$fakturanr','$tmp','$beskrivelse','$udlign','$udlign_id','$udlign_date','$transdate','$udlign','$id','$valuta','$valutakurs','$forfaldsdate','$projekt[0]')",__FILE__ . " linje " . __LINE__);
			else db_modify("insert into openpost (konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,transdate,kladde_id,refnr,valuta,valutakurs,forfaldsdate,projekt) values ('$konto_id','$kontonr','$fakturanr','$tmp','$beskrivelse','$udlign','$transdate','$udlign','$id','$valuta','$valutakurs','$forfaldsdate','$projekt[0]')",__FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select max(id) as id from openpost where konto_id = '$konto_id' and faktnr = '$fakturanr' and refnr='$id'",__FILE__ . " linje " . __LINE__));
			$openpost_id=$r['id'];
#cho "select gruppe from adresser where id='$konto_id'<br>";
			$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
#cho "select beskrivelse, box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'<br>";
			$r = db_fetch_array(db_select("select beskrivelse, box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
			$kontonr=$r['box2']; # Kontonr aendres fra at vaere leverandoerkontonr til finanskontonr
#cho "KTO $kontonr<br>";
			$tekst="Kontonummer for Debitorgruppe `$r[beskrivelse]` er ikke gyldigt";
			if (!$kontonr && $webservice) return($tekst);
			elseif(!$kontonr) print "<BODY onLoad=\"javascript:alert('$tekst')\">";
/*
			if ($sum>0) {$debet=$sum; $kredit='0';}
			else {$debet='0'; $kredit=$sum*-1;}
			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.
			$sum=0;
			$debet=afrund($debet,2);
			$kredit=afrund($kredit,2);
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
#cho "insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
			db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
*/
		}
#cho "KONTONR $kontonr<br>";
#exit;
		if ($art=='PO' && $sum) { #saa er det en kontantordre (POS)
			$retur=$sum;
			$tmparray=array();
			$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
			if (!$kontonr) {
				$tmparray=explode(chr(9),$r['box2']);
				$kontonr=$tmparray[$kasse-1];
			} else
			$tmparray=explode(chr(9),$r['box3']);
			$afd=$tmparray[$kasse-1]*1;
			$tmparray=explode(chr(9),$r['box5']);
#			if ($betaling!='kontant' && $betaling!='konto' && $betalt) {
				$kortantal=$r['box4']*1;
				$korttyper=explode(chr(9),$r['box5']);
				$kortkonti=explode(chr(9),$r['box6']);
				for($x=0;$x<$kortantal;$x++) {
					if ($betaling==$korttyper[$x]) {
						$debet=afrund($modtaget,2);
						$kredit='0';
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
#cho "D $d_kontrol K $k_kontrol<br>";
						$retur=$retur-$modtaget;
						$sum=$sum-$modtaget;
						if (is_numeric($id)) {
#cho "A insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
							db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
						} else {
							db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '0', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '0')",__FILE__ . " linje " . __LINE__);
						}	
						db_modify("update kontoplan set saldo=saldo+'$debet' where kontonr='$kortkonti[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
					}
					if ($betaling2==$korttyper[$x]) {
						$debet=afrund($modtaget2,2);
						$kredit='0';
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
#cho "D $d_kontrol K $k_kontrol<br>";
						$sum=$sum-$modtaget2;
						if (is_numeric($id)) {
#cho "B insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
							db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
						} else {
#cho "B2 insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '0', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '0')<br>";
							db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '0', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '0')",__FILE__ . " linje " . __LINE__);
						}
						db_modify("update kontoplan set saldo=saldo+'$debet' where kontonr='$kortkonti[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
					}
				}
		}
#exit;
		if ($sum) {
			if ($sum>0) {$debet=$sum; $kredit='0';}
			else {$debet='0'; $kredit=$sum*-1;}

			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
#cho "D $d_kontrol K $k_kontrol<br>";
			$debet=afrund($debet,2);
			$kredit=afrund($kredit,2);
			if (is_numeric($id)) {
#cho "C insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
				db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
			} else {
				db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '0', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '0')",__FILE__ . " linje " . __LINE__);
			}
			$tmp=$debet-$kredit;
			db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$kontonr' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
		}
		if ($valutakurs) $maxdif=2; #Der tillades 2 oeres afrundingsdiff
		$p=0;
		$projektliste='';
		if (is_numeric($id)) $tmp="ordre_id = '".$id."'";
		else {
			$idliste=explode(",",$id);
			$antal=count($idliste);
			$tmp="(ordre_id = '".$idliste[0]."'";
			for($x=1;$x<$antal;$x++) $tmp.=" or ordre_id = '".$idliste[$x]."'";
			$tmp.=")";
		} 
#cho "select distinct(projekt) from ordrelinjer where $tmp and vare_id >'0'<br>";
		$q = db_select("select distinct(projekt) from ordrelinjer where $tmp and vare_id >'0'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if(trim($r['projekt'])) {
				$p++;
				$projekt[$p]=trim($r['projekt']);
#cho "$p P >$projekt[$p]<<br>";
				($projektliste)?$projektliste.="<br>".$projekt[$p]:$projektliste=$projekt[$p];
			}
		}
		($p)?$projektantal=$p:$projektantal=1;
		#cho "update openpost set projekt='$projektliste' where id='$openpost_id'<br>";
#if ($projektliste) #chon "update openpost set projekt='$projektliste' where id='$openpost_id'<br>";
		if ($projektliste) db_modify("update openpost set projekt='$projektliste' where id='$openpost_id'",__FILE__ . " linje " . __LINE__);
 
#		for ($p=1;$p<=$projektantal;$p++) 

		for ($t=1;$t<=2;$t++)	{
			for ($p=1;$p<=$projektantal;$p++) {
#cho "projektantal $projektantal<br>";
				$y=0;
				$tjek= array();
				$bogf_konto = array();
				if (is_numeric($id)) $tmp="ordre_id = '".$id."'";
				else {
					$idliste=explode(",",$id);
					$antal=count($idliste);
					$tmp="(ordre_id = '".$idliste[0]."'";
					for($x=1;$x<$antal;$x++) $tmp.=" or ordre_id = '".$idliste[$x]."'";
					$tmp.=")";
				} 
				if ($t==1) {
#cho "t=$t select * from ordrelinjer where $tmp and projekt='$projekt[$p]' and posnr>='0' order by bogf_konto<br>";
					$q = db_select("select * from ordrelinjer where $tmp and projekt='$projekt[$p]' and posnr>='0' order by bogf_konto",__FILE__ . " linje " . __LINE__);
				} else {
#cho "t=$t select * from ordrelinjer where $tmp and projekt='$projekt[$p]' and posnr<'0' order by bogf_konto<br>";
					$q = db_select("select * from ordrelinjer where $tmp and projekt='$projekt[$p]' and posnr<'0' order by bogf_konto",__FILE__ . " linje " . __LINE__);
				}
				while ($r = db_fetch_array($q)) {
#cho "$r[id],$r[ordre_id],$r[posnr],$r[varenr],$r[vare_id],$r[pris],$r[kostpris],$r[m_rabat],$r[beskrivelse],$r[bogf_konto]--<br>";
#cho "+++++++++++ Y $y >$r[bogf_konto]<, $bogf_konto[$y]<br>";
#cho "Q $r[pris]<br>";
					if ($valutakurs) $maxdif=$maxdif+2; #Og yderligere 2 pr ordrelinje.
					$tmp=$projekt[$p].":".$r['bogf_konto'];
					if (!in_array($r['bogf_konto'], $bogf_konto)) {

#cho "---------------- Y $y >$r[bogf_konto]<, $bogf_konto[$y]<br>";
						$y++;
#cho "$y Linje_id $r[id]<br>";
						$bogf_konto[$y]=$r['bogf_konto'];
#cho "0 pris y $y $r[pris]<br>";
						if ($r['rabatart']=='amount') {
#cho "$r[pris]*$r[antal]-($r[rabat]*$r[antal])<br>";
							$pris[$y]=$r['pris']*$r['antal']-($r['rabat']*$r['antal']);
#cho "Bogf $bogf_konto[$y]<br>";
#cho "1 pris y  $y $pris[$y]<br>";
						} else {
							$pris[$y]=$r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100);
#cho "$r[pris]*$r[antal]-($r[pris]*$r[antal]*$r[rabat]/100)<br>";
							$pris[$y]=afrund($pris[$y],2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104 -- 2011.02.07 ændret til 2 decimaler ordre_id 1325 saldi_329
						}
 #cho "2 pris y $pris[$y]<br>";
					}	else {
						for ($a=1; $a<=$y; $a++) {
							if ($bogf_konto[$a]==$r['bogf_konto']) {
								if ($r['rabatart']=='amount') {
									$pris[$a]+=$r['pris']*$r['antal']-($r['rabat']*$r['antal']);
								} else {
									$pris[$a]=$pris[$a]+($r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100));
								}
								$pris[$a]=afrund($pris[$a],2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104 -- 2011.02.07 ændret til 2 decimaler ordre_id 1325 saldi_329
#cho "pris A $pris[$a]<br>";
							}
						}
					}
				}
				$ordrelinjer=$y;
#cho "ordrelinjer $ordrelinjer<br>";
				if ($indbetaling) $ordrelinjer=0;
				for ($y=1;$y<=$ordrelinjer;$y++) {
#cho "Bogf_konto $bogf_konto[$y]<br>";
					if ($bogf_konto[$y] && $pris[$y]) {
						if ($pris[$y]>0) {$kredit=$pris[$y];$debet=0;}
						else {$kredit=0; $debet=$pris[$y]*-1;}
						if ($t==1 && $valutakurs) {$kredit=$kredit*$valutakurs/100;$debet=$debet*$valutakurs/100;} # Omregning til DKR.
						$kredit=afrund($kredit,3);$debet=afrund($debet,3);
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
						$debet=afrund($debet,2);
						$kredit=afrund($kredit,2);
						if (is_numeric($id)) {
#cho "insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '$fakturanr', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt[$p]', '$ansat', '$id')<br>";
							db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '$fakturanr', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt[$p]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
						} else {
#cho "insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '0', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt[$p]', '$ansat', '0')<br>";
							db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '0', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt[$p]', '$ansat', '0')",__FILE__ . " linje " . __LINE__);
						}
						$tmp=$debet-$kredit;
						db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$bogf_konto[$y]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
					} elseif ($pris[$y]) {
						$svar="Fejl i kontoopsætning";
						if (!$webservice) print "<BODY onLoad=\"javascript:alert('$svar')\">";
						else return("$svar");
						exit;
					}
				}
			}
		}
#exit;
		if ($momskode) {
			$query = db_select("select box1 from grupper where art='SM' and kodenr='$momskode'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$box1=trim($row['box1']);
			if ($moms > 0) {$kredit=$moms; $debet='0';}
			else {$kredit='0'; $debet=$moms*-1;}
			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.
			$kredit=afrund($kredit,3);$debet=afrund($debet,3);
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			$diff=afrund($d_kontrol-$k_kontrol,3);
			$absdiff=abs($diff);
			if ($moms && $valutakurs && $valutakurs!=100 && $absdiff>=0.01 && $absdiff<=0.05) {
				if ($debet > 0) {
					$debet=$debet+$diff;
					$d_kontrol=$d_kontrol+$diff;
				} elseif ($kredit > 0) {
					$kredit=$kredit+$diff;
					$k_kontrol=$k_kontrol+$diff;
				}
			}
			$moms=afrund($moms,2);
			if ($moms) {
				if (is_numeric($id)) {
					db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$box1', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
				} else {
					db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$box1', '0', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '0')",__FILE__ . " linje " . __LINE__);
				}
				$tmp=$debet-$kredit;
				db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$box1' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
			}
			$valutakurs=$valutakurs*1;
		}
		if (is_numeric($id)) $tmp="id = '".$id."'";
		else {
			$idliste=explode(",",$id);
			$antal=count($idliste);
			$tmp="(id = '".$idliste[0]."'";
			for($x=1;$x<$antal;$x++) $tmp.=" or id = '".$idliste[$x]."'";
			$tmp.=")";
		} 
		db_modify("update ordrer set status=4, valutakurs=$valutakurs where $tmp",__FILE__ . " linje " . __LINE__);
		if (is_numeric($id)) $tmp="ordre_id = '".$id."'";
		else {
			$idliste=explode(",",$id);
			$antal=count($idliste);
			$tmp="(ordre_id = '".$idliste[0]."'";
			for($x=1;$x<$antal;$x++) $tmp.=" or ordre_id = '".$idliste[$x]."'";
			$tmp.=")";
		} 
		db_modify("delete from ordrelinjer where $tmp and posnr < 0",__FILE__ . " linje " . __LINE__);
	$d_kontrol=afrund($d_kontrol,2);
	$k_kontrol=afrund($k_kontrol,2);

 #cho "D $d_kontrol K $k_kontrol<br>";

	if ($diff=afrund($d_kontrol-$k_kontrol,2)) {
		$debet=0; $kredit=0;
		if ($diff<0) $debet=$diff*-1;
		else $kredit=$diff;
		$debet=afrund($debet,2);
		$kredit=afrund($kredit,2);
		if ($valuta!='DKK' && abs($diff)<=$maxdif) { #Der maa max vaere en afvigelse paa 1 oere pr ordrelinje m fremmed valuta;
 		$r= db_fetch_array(db_select("select box3 as difkto from grupper where grupper.art='VK' and grupper.box1='$valuta'",__FILE__ . " linje " . __LINE__));
			$difkto=$r['difkto']*1;
			if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))) {
				return("Kontonr $difkto (kursdiff) eksisterer ikke");
			}
			if (is_numeric($id)) {
				db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$difkto', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
			} else {
				db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$difkto', '0', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '0')",__FILE__ . " linje " . __LINE__);
			}
			$tmp=$debet-$kredit;
			db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
		} elseif (abs($diff) < 0.05) {
			if ($r = db_fetch_array(db_select("select * from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__))) {
			  $oredifkto=$r['box2'];
			} else {
			  if ($webservice) return ('Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff');
			  else print "<BODY onLoad=\"javascript:alert('Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff')\">";
			}
			$r = db_fetch_array(db_select("select id from kontoplan where kontotype = 'D' and kontonr = '$oredifkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				if (is_numeric($id)) {
					db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$oredifkto', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
				} else {
					db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$oredifkto', '0', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '0')",__FILE__ . " linje " . __LINE__);
				}
				$tmp=$debet-$kredit;
				db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$oredifkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
			} else {
				if ($webservice) return ('Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff');
				else print "<BODY onLoad=\"javascript:alert('Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff')\">";
			}
		} else {
			$svar="Der er konstateret en uoverensstemmelse i posteringssummen, ordre $ordrenr, kontakt DANOSOFT p&aring; telefon 4690 2208' debet $debet != kredit $kredit";
			$message=$db." | Uoverensstemmelse i posteringssum: ordre_id=$id, d=$d_kontrol, k=$k_kontrol | ".__FILE__ . " linje " . __LINE__." | ".$brugernavn." ".date("Y-m-d H:i:s");
			$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'SALDI Fejl', $message, $headers);
			if (!$webservice) print "<BODY onLoad=\"javascript:alert('$svar')\">";
			else return("$svar");
			exit;
		}
	}
	if ($title != "Massefakturering" && !$webservice && $art !='PO') genberegn($regnaar);
/*
	if ($tjek_lagerdiff) {
		$post_stockvalue=lagervaerdi($regnaar);
		$post_finans=finanslager($regnaar);
		$post_lagerdiff=$post_finans-$post_stockvalue;
#cho "$post_lagerdiff=$post_finans-$post_stockvalue<br>";
		if (abs($pre_lagerdiff-$post_lagerdiff)>1) print "<BODY onLoad=\"javascript:alert('Lagerdiff ændret -Før $pre_lagerdiff Efter $post_lagerdiff')\">";
	} 
*/
	return($svar);
} # endfunc bogfor_nu
######################################################################################################################################
function kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt)
{
#cho "$art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt<br>";
#cho "fokus=$fokus<br>";
	if ($fokus=='kontonr') $find=$kontonr;
	if (strstr($fokus,'lev')) $find=$firmanavn;
	if ($fokus=='firmanavn') $find=$firmanavn;
	if ($fokus=='addr1') $find=$addr1;
	if ($fokus=='addr2') $find=$addr2;
	if ($fokus=='postnr') $find=$postnr;
	if ($fokus=='bynavn') $find=$bynavn;
	if ($fokus=='kontakt') $find=$kontakt;
	global $bgcolor;
	global $bgcolor5;
	global $land;

	
	if ($find) $find=str_replace("*","%",$find);
	else $find="%";
	if (substr($find,-1,1)!='%') $find=$find.'%';

		if($art=='DO'||$art=='DK') {
		sidehoved($id, "../debitor/ordre.php", "../debitor/debitorkort.php", $fokus, "Kundeordre $id - Kontoopslag");
		$href="ordre.php";
	} elseif ($art=='PO') {
		sidehoved($id, "../debitor/pos_ordre.php", "", $fokus, "POS ordre $id - Kontoopslag");
		$href="pos_ordre.php";
		$find="";
		$fokus="kontonr";
	}
#	sidehoved($id, "ordre.php", "../debitor/debitorkort.php", $fokus, "Kundeordre $id - Kontoopslag");
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign=\"top\">";
	print"<tbody><tr>";
	print"<td><b><a href=$href?sort=kontonr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Kundenr</b></td>";
	print"<td><b><a href=$href?sort=firmanavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></td>";
	print"<td><b><a href=$href?sort=addr1&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Adresse</b></td>";
	print"<td><b><a href=$href?sort=addr2&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Adresse2</b></td>";
	print"<td><b><a href=$href?sort=postnr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Postnr</b></td>";
	print"<td><b><a href=$href?sort=bynavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>bynavn</b></td>";
	print"<td><b><a href=$href?sort=land&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>land</b></td>";
	print"<td><b><a href=$href?sort=kontakt&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Kontaktperson</b></td>";
	print"<td><b><a href=$href?sort=tlf&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Telefon</b></td>";
	print" </tr>\n";
	if ($art=='PO')	{
	  print "<form NAME=\"kontoopslag\" action=\"pos_ordre.php?fokus=kontonr&id=$id\" method=\"post\">";
	  print "<tr><td><input name=\"kontonr\" size = \"4\"></td>";
	  print "<td><input  STYLE=\"width: 0.01em;height: 0.01em;\" type=submit name=\"Opdat\" value=\"\"></td></tr>";
	  print "</form>";
	}
	$sort = $_GET['sort'];
	if (!$sort) {$sort = "firmanavn";}
	(strstr($fokus,'lev_'))?$soeg='firmanavn':$soeg=$fokus;	
#cho "select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'D' and upper($soeg) like upper('$find') and lukket != 'on' order by $sort<br>";
	if ($find) $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'D' and upper($soeg) like upper('$find') and lukket != 'on' order by $sort",__FILE__ . " linje " . __LINE__);
	else $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'D' and lukket != 'on' order by $sort",__FILE__ . " linje " . __LINE__);
	$fokus_id='id=fokus';
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$kontonr=str_replace(" ","",$row['kontonr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href=$href?fokus=$fokus&id=$id&konto_id=$row[id] $fokus_id>$row[kontonr]</a></td>";
		$fokus_id='';
		print "<td>".stripslashes($row[firmanavn])."</td>";
		print "<td>".stripslashes($row[addr1])."</td>";
		print "<td>".stripslashes($row[addr2])."</td>";
		print "<td>".stripslashes($row[postnr])."</td>";
		print "<td>".stripslashes($row[bynavn])."</td>";
		print "<td>".stripslashes($row[land])."</td>";
		print "<td>".stripslashes($row[kontakt])."</td>";
		print "<td>".stripslashes($row[tlf])."</td>";
		print "</tr>\n";
	}
	if (!$x) {
		print "<tr><td colspan=9><hr></td></tr>";
		print "<tr><td>$kontonr</td><td>$firmanavn</td><td>$addr1</td><td>$addr2</td><td>$postnr</td><td>$bynavn</td><td>$land</td><td>$kontakt</td><td>$tlf</td></tr>";
		print "<tr><td colspan=9>Ovenst&aring;ende kunde er ikke oprettet. <a href=\"../debitor/debitorkort.php?kontonr=$kontonr&firmanavn=$firmanavn&addr1=$addr1&addr2=$addr2&postnr=$postnr&bynavn=$bynavn&land=$land&kontakt=$kontakt&tlf=$tlf&returside=../debitor/$href&ordre_id=&fokus=kontonr\">Klik her for at oprette denne kunde</a></td></tr>";
		print "<tr><td colspan=9><hr></td></tr>";
	}
	print "</tbody></table></td></tr></tbody></table>";
	if ($art=='PO')	print "<script language=\"javascript\">document.kontoopslag.kontonr.focus();</script>";
	else print "<BODY onLoad=\"javascript:document.getElementById('fokus').focus()\">";
	exit;
}
######################################################################################################################################
function ansatopslag($sort, $fokus, $id)
{
	global $bgcolor;
	global $bgcolor5;

	if (!$id) $id='0';
	$query = db_select("select konto_id from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$konto_id = $row[konto_id];

	$fokus=$fokus."&konto_id=".$konto_id;

	sidehoved($id, "ordre.php", "../debitor/ansatte.php", $fokus, "Debitorordre $id",__FILE__ . " linje " . __LINE__);
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><b><a href=ordre.php?sort=navn&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></td>";
	print"<td><b><a href=ordre.php?sort=tlf&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Lokal</b></td>";
	print"<td><b><a href=ordre.php?sort=mobil&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Mobil</b></td>";
	print"<td><b><a href=ordre.php?sort=email&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>E-mail</b></td>";
	print" </tr>\n";


	$sort = $_GET['sort'];
	if (!$sort) {$sort = navn;}

	if (!$id) {exit;}
	$query = db_select("select * from ansatte where konto_id = $konto_id order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query))
	{
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href='ordre.php?fokus=$fokus&id=$id&kontakt=$row[navn]'>$row[navn]</a></td>";
		print "<td> $row[tlf]</td>";
		print "<td> $row[mobil]</td>";
		print "<td> $row[email]</td>";
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function opret_ordrelinje($id,$varenr,$antal,$beskrivelse,$pris,$rabat_ny,$art,$momsfri,$posnr,$linje_id,$incl_moms,$kdo,$rabatart,$kopi) {
#cho "$id,$varenr,$antal,$beskrivelse,$pris,$rabat_ny,$art,$momsfri,$posnr,$linje_id,$incl_moms,$kdo,$rabatart,$kopi<br>";
#exit;

	if (!$id) return("missing ordre ID");

	global $regnaar;
	global $momssats;
	global $formularsprog;
	global $sprog_id;
	global $webservice;

/*
	$q = db_select("select * from ordrelinjer",__FILE__ . " linje " . __LINE__);
	while ($i < db_num_fields($q)) { 
		$feltnavne[$i] = db_field_name($q,$i); 
		$i++; 
	}
	if (!in_array('variant_id',$feltnavne)) {
		db_modify("ALTER TABLE ordrelinjer ADD variant_id text",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE ordrelinjer set variant_id = ''",__FILE__ . " linje " . __LINE__);
	} 
*/

	$dd=date("Y-m-d");
	$pris*=1;
	if ($pris && $pris > 99999999) {
		return("Ulovlig v&aelig;rdi i prisfelt");
	}
	if (!$regnaar) {
		$year=date("Y");
		$month=date("m");
		$del1="(box1<='$month' and box2<='$year' and box3>='$month' and box4>='$year')";
		$del2="(box1<='$month' and box2<='$year' and box3<'$month' and box4>'$year')";
		$del3="(box1>'$month' and box2<'$year' and box3>='$month' and box4>='$year')";
		if ($r=db_fetch_array(db_select("select kodenr from grupper where art='RA' and $del1 or $del2 or $del3",__FILE__ . " linje " . __LINE__))) {
			$regnaar=$r['kodenr']*1;
		} elseif ($r=db_fetch_array(db_select("select max(kodenr) as kodenr from grupper where art='RA'",__FILE__ . " linje " . __LINE__))) {
			$regnaar=$r['kodenr']*1;
		} else $regnaar=1;
	}

	$r=db_fetch_array(db_select("select ordrer.valutakurs as valutakurs,adresser.gruppe as debitorgruppe,adresser.rabatgruppe as debitorrabatgruppe from adresser,ordrer where ordrer.id='$id'and adresser.id=ordrer.konto_id",__FILE__ . " linje " . __LINE__));
	$debitorgruppe=$r['debitorgruppe']*1;
	$debitorrabatgruppe=$r['debitorrabatgruppe']*1;
	$valutakurs=$r['valutakurs']*1;

	$r=db_fetch_array(db_select("select box8 from grupper where kodenr='$debitorgruppe' and art = 'DG'",__FILE__ . " linje " . __LINE__));
	$b2b=$r['box8'];

	$varenr=addslashes($varenr);
	$varenr_low=strtolower($varenr);
	$varenr_up=strtoupper($varenr);

	if ($r=db_fetch_array(db_select("SELECT id,vare_id,variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr_up'",__FILE__ . " linje " . __LINE__))) {
		$vare_id=$r['vare_id'];
		$variant_type=$r['variant_type'];
		$variant_id=$r['id'];
	} else {
		$variant_id=0;
		$variant_type='';
	}
	if ($vare_id) $string="select * from varer where id='$vare_id'";
	elseif ($varenr) $string="select * from varer where lower(varenr) = '$varenr_low' or upper(varenr) = '$varenr_up' or varenr LIKE '$varenr' or lower(stregkode) = '$varenr_low' or upper(stregkode) = '$varenr_up' or stregkode LIKE '$varenr'";
	elseif ($id && $beskrivelse && $posnr) {
		db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,enhed,beskrivelse,antal,rabat,rabatart,m_rabat,pris,kostpris,momsfri,momssats,posnr,projekt,folgevare,rabatgruppe,bogf_konto,kred_linje_id,kdo,serienr,variant_id,leveres,samlevare) values ('$id','0','','','$beskrivelse','0','0','','0','0','0','','0','$posnr','0','0','0','0','0','','','0','0','')",__FILE__ . " linje " . __LINE__);
	} else return ("Manglende varenr eller beskrivelse");
	if ($r=db_fetch_array(db_select("$string",__FILE__ . " linje " . __LINE__))) {
		$vare_id=$r['id'];
#cho "ID $vare_id<br>";
		$varenr=addslashes($r['varenr']);
		$enhed=addslashes($r['enhed']);
		$folgevare=$r['folgevare']*1;
		$rabatgruppe=$r['rabatgruppe']*1;
		$varegruppe=$r['gruppe']*1;
		$samlevare=$r['samlevare'];
		$varerabatgruppe=$r['dvrg']*1;
		if (!$pris && $b2b) $pris=$r['tier_price']*1;
		$special_price=$r['special_price']*1;
		$serienr=$r['serienr'];
		$beholdning=($r['beholdning'])*1;
		list($m_antal,$temp)=explode(";",$r['m_antal']);
		$m_antal=$m_antal*1;
		if (!$beskrivelse) {
			$beskrivelse=addslashes(trim($r['beskrivelse']));
			if ($formularsprog) {
				$r2=db_fetch_array(db_select("select kodenr from grupper where art='VSPR' and box1 = '$formularsprog'",__FILE__ . " linje " . __LINE__));
				$kodenr=$r2['kodenr']*1;
				$r2=db_fetch_array(db_select("select tekst from varetekster where sprog_id='$kodenr' and vare_id='$vare_id'",__FILE__ . " linje " . __LINE__));
				if ($r2['tekst']) $beskrivelse=addslashes($r2['tekst']);
			}
		}
#		if (!$posnr && $art!='PO' && $r2=db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__))) {
		if (!$posnr && $r2=db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__))) {
			$posnr=$r2['posnr']+1;
		} elseif (!$posnr) $posnr=1;
		if (!$r2 = db_fetch_array(db_select("select box4,box6,box7 from grupper where art = 'VG' and kodenr = '$varegruppe'",__FILE__ . " linje " . __LINE__))) {
			$alerttekst=findtekst(320,$sprog_id)." $varenr ".findtekst(321,$sprog_id);
			# print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
			return ("$alerttekst");
		}
		$bogfkto = $r2['box4'];
		$momsfri = $r2['box7'];
		if (!$bogfkto) 	{
			$alerttekst=findtekst(319,$sprog_id)." ".$varegruppe."!";
			# print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
		  return ("$alerttekst");
		}
		if (($r2['box6']!=NULL)&&($rabatsats>$r2['box6'])) $rabatsats=$r2['box6'];
		if ($bogfkto && !$momsfri) {
			$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$bogfkto' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
			if ($tmp=trim($r2['moms'])) { # f.eks S3
				$tmp=substr($tmp,1); #f.eks 3
				$r2 = db_fetch_array(db_select("select box2 from grupper where art = 'SM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__));
				if ($r2['box2']) $varemomssats=$r2['box2']*1;
			}	else $varemomssats=$momssats;
		} else $varemomssats=0;
		if (!$pris) {
		  if ($special_price && $r['special_from_date'] <= $dd && $dd <= $r['special_to_date']) {
				$pris=$special_price;
				$kostpris=$r['campaign_cost']*1;
			} else {
				$pris=$r['salgspris']*1;
				$kostpris=$r['kostpris']*1;
			}
		}	elseif ($momsfri) {
			$kostpris=$r['kostpris']*1;
		} else {
			global $momssats;
			if ($incl_moms) $pris=$pris-($pris*$varemomssats/(100+$varemomssats));
			$kostpris=$r['kostpris']*1;
		}
	} elseif (!$kopi) return ("Varenr: $varenr eksisterer ikke");
	$vare_id*=1;
	$m_rabat=0;
	$rabat_ny*=1;
	if ($linje_id && $art=='DO') $tmp="id='$linje_id'";
	elseif ($art=='PO') $tmp= "vare_id = '$vare_id' and ordre_id='$id' and pris='$pris' and rabat='$rabat_ny' and variant_id='$variant_id'";
	if(((!$kopi && $linje_id && $art=='DO') || $art=='PO') && $r=db_fetch_array(db_select("select rabat,posnr,id,antal from ordrelinjer where $tmp",__FILE__ . " linje " . __LINE__))) {
		$antaldiff=$antal;
		$antal=$r['antal']+$antal;
	if ($antaldiff && $r['id']) {
#cho "update ordrelinjer set m_rabat='0', antal='$antal' where id = '$r[id]'<br>";
			db_modify("update ordrelinjer set m_rabat='0', antal=antal+$antaldiff where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			if ($samlevare == 'on') db_modify("update ordrelinjer set antal=antal/$r[antal]*$antal where samlevare = '$linje_id'",__FILE__ . " linje " . __LINE__);
#cho "1880 select sum(antal) as antal from ordrelinjer where vare_id='$vare_id' and pris='$pris' and rabat='0' and ordre_id='$id'<br>";
			$r2=db_fetch_array(db_select("select sum(antal) as antal from ordrelinjer where vare_id='$vare_id' and pris='$pris' and rabat='0' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
			$tmpantal=$r2['antal'];
#cho "$m_antal && $tmpantal >= $m_antal<br>";
			if ($m_antal && $tmpantal >= $m_antal) {
#cho "m_rabat($r[id],$vare_id,$r[posnr],$tmpantal,$id)<br>";
				m_rabat($r['id'],$vare_id,$r['posnr'],$tmpantal,$id);
			} else {
#cho "update ordrelinjer set m_rabat='0' where ordre_id = '$id' and vare_id = '$vare_id'<br>";
				db_modify("update ordrelinjer set m_rabat='0' where ordre_id = '$id' and vare_id = '$vare_id'",__FILE__ . " linje " . __LINE__);
			}
		} elseif ($art=='PO' && $r['id']) db_modify("delete from ordrelinjer where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
	} else {
		if ($kopi || $rabat_ny) $rabat=$rabat_ny;
		else {
#cho "$debitorrabatgruppe -- $varerabatgruppe<br>";
#			if (!$debitorrabatgruppe && !$varerabatgruppe) $varerabatgruppe=$varegruppe;
			if (!$debitorrabatgruppe && !db_fetch_array(db_select("select id from grupper where art='DRG'",__FILE__ . " linje " . __LINE__))){
				$debitorrabatgruppe=$debitorgruppe;
			}
			if (!$varerabatgruppe && !db_fetch_array(db_select("select id from grupper where art='DVRG'",__FILE__ . " linje " . __LINE__))){
				$varerabatgruppe=$varegruppe;
			}
#cho "select rabat,rabatart from rabat where vare='$varerabatgruppe' and debitor='$debitorrabatgruppe'<br>";
			$r2=db_fetch_array(db_select("select rabat,rabatart from rabat where vare='$varerabatgruppe' and debitor='$debitorrabatgruppe'",__FILE__ . " linje " . __LINE__));
			$rabat=$r2['rabat']*1;
			$rabatart=$r2['rabatart'];
		}
		($linje_id && $art=='DK')?$kred_linje_id=$linje_id:$kred_linje_id='0';
		if (!$varemomssats && $varemomssats!='0') {
			($momsfri)?$varemomssats!='0':$varemomssats=$momssats;
			$varemomssats=$varemomssats*1;
		}
		if ($valutakurs && $valutakurs!=100) {
			$pris=$pris*100/$valutakurs;
			$kostpris=$kostpris*100/$valutakurs;
		}
#cho "rabarart $rabatart<br>";
		if ($variant_type) {
			$varianter=explode(chr(9),$variant_type);
			for ($y=0;$y<count($varianter);$y++) {
				$r1=db_fetch_array(db_select("select variant_typer.beskrivelse as vt_besk,varianter.beskrivelse as var_besk from variant_typer,varianter where variant_typer.id = '$varianter[$y]' and variant_typer.variant_id=varianter.id",__FILE__ . " linje " . __LINE__));
				$beskrivelse.=", ".$r1['var_besk'].":".$r1['vt_besk'];
			}
		}
#cho "insert into ordrelinjer (ordre_id,vare_id,varenr,enhed,beskrivelse,antal,rabat,rabatart,m_rabat,pris,kostpris,momsfri,momssats,posnr,projekt,folgevare,rabatgruppe,bogf_konto,kred_linje_id,kdo,serienr,variant_id) values ('$id','$vare_id','$varenr','$enhed','$beskrivelse','$antal','$rabat','$rabatart','$m_rabat','$pris','$kostpris','$momsfri','$varemomssats','$posnr','0','$folgevare','$rabatgruppe','$bogfkto','$kred_linje_id','$kdo','$serienr','$variant_id')<br>";
# exit;
		($webservice)?$leveres=$antal:$leveres=0; 
		if ($id && is_numeric($posnr)) {
			if (($samlevare && !$antal) || $antal=='') $antal=1;
			$antal*=1;
			db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,enhed,beskrivelse,antal,rabat,rabatart,m_rabat,pris,kostpris,momsfri,momssats,posnr,projekt,folgevare,rabatgruppe,bogf_konto,kred_linje_id,kdo,serienr,variant_id,leveres,samlevare) values ('$id','$vare_id','$varenr','$enhed','$beskrivelse','$antal','$rabat','$rabatart','$m_rabat','$pris','$kostpris','$momsfri','$varemomssats','$posnr','0','$folgevare','$rabatgruppe','$bogfkto','$kred_linje_id','$kdo','$serienr','$variant_id','$leveres','$samlevare')",__FILE__ . " linje " . __LINE__);
			if ($samlevare && !$beholdning) {
				$r=db_fetch_array(db_select("select max(id) as id from ordrelinjer where vare_id='$vare_id' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
				samlevare($id,$art,$r['id'],$vare_id,$antal);
			}
		}
		# finder antal af varen på ordren.
#cho "select sum(antal) as antal from ordrelinjer where vare_id='$vare_id' and pris=$pris and ordre_id='$id<br>";
		$r=db_fetch_array(db_select("select sum(antal) as antal from ordrelinjer where vare_id='$vare_id' and pris=$pris and rabat='0' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
		$tmpantal=$r['antal'];
		if ($m_antal && $tmpantal >= $m_antal) {
			$r2=db_fetch_array(db_select("select max(id) as id from ordrelinjer where vare_id='$vare_id' and pris=$pris and rabat='0' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
			m_rabat($r2['id'],$vare_id,0,$tmpantal,$id);
		}	else {
#cho "update ordrelinjer set m_rabat='0' where ordre_id = '$id' and vare_id = '$vare_id'<br>";
			db_modify("update ordrelinjer set m_rabat='0' where ordre_id = '$id' and vare_id = '$vare_id'",__FILE__ . " linje " . __LINE__);
		}
	}
	$sum=$pris*$antal;
	return($sum);
#	$varenr=$next_varenr;
#	$antal=NULL;
} # endfunc opret_orderlinje
######################################################################################################################################
function m_rabat ($linje_id,$vare_id,$posnr,$antal,$ordre_id) {

# finder mængderabat på varen.
#cho "select m_type,m_rabat,m_antal,salgspris from varer where id = '$vare_id'<br>";
	$r=db_fetch_array(db_select("select m_type,m_rabat,m_antal,salgspris from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
	$m_antal=explode(";",$r['m_antal']);
	$m_rabat=explode(";",$r['m_rabat']);
	$m_type=$r['m_type'];
	$pris=$r['salgspris'];
	$x=0;

	while ($m_antal[$x+1] && $antal >= $m_antal[$x+1]) {
		$x++;
	}
	if ($m_type =='percent') $m_rabat[$x]=$pris*$m_rabat[$x]/100;

#cho "update ordrelinjer set m_rabat='$m_rabat[$x]',rabatart='$m_type' where ordre_id = '$ordre_id' and vare_id = '$vare_id'and pris='$pris' and rabat='0'<br>";
	db_modify("update ordrelinjer set m_rabat='$m_rabat[$x]',rabatart='$m_type' where ordre_id = '$ordre_id' and vare_id = '$vare_id'and pris='$pris' and rabat='0'",__FILE__ . " linje " . __LINE__);
}# endfunc m_rabat
######################################################################################################################################
function find_pris($varenr) {
	global $momssats;
	$dd=date("Y-m-d");
	if ($r=db_fetch_array(db_select("select * from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__))) {
		$vare_id=$r['id'];
		$special_price=$r['special_price']*1;
		if ($special_price && $r['special_from_date'] <= $dd && $dd <= $r['special_to_date']) {
			$pris=$r['special_price']*1;
			$kostpris=$r['campaign_cost']*1;
		} else {
			$pris=$r['salgspris']*1;
			$kostpris=$r['kostpris']*1;
		}
		$r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$momsfri = $r2['box7'];
		if (!$momsfri) $pris=$pris+$pris/100*$momssats;
	}
	return($pris);
}
######################################################################################################################################
function find_kostpris($vare_id,$linje_id) {
	$kostpris=NULL;;
	$kobs_ordre_nr=NULL;
	$k_stk_ant=NULL;
	$q = db_select("select provisionsfri from varer where id = '$vare_id' and provisionsfri='on'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$provisionsfri[$x]=$r['provisionsfri'];
#cho "select * from batch_salg where linje_id = '$linje_id'<br>";
	$q = db_select("select * from batch_salg where linje_id = '$linje_id'",__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($r = db_fetch_array($q)) {
		$x++;
		$batch_kob_id=$r['batch_kob_id']*1;
		$r2 = db_fetch_array(db_select("select antal,ordre_id,pris,fakturadate,linje_id from batch_kob where id = $batch_kob_id",__FILE__ . " linje " . __LINE__));
		$koid[$x]=$r2['ordre_id'];
		if ($koid[$x]) {
			$r3=db_fetch_array(db_select("select valutakurs from ordrer where id = $koid[$x]",__FILE__ . " linje " . __LINE__));
			if ($r3['valutakurs']) $kobs_valutakurs=$r3['valutakurs'];
			else $kobs_valutakurs=100;
			$r2=db_fetch_array(db_select("select pris from ordrelinjer where id = $r2[linje_id]",__FILE__ . " linje " . __LINE__));#}
			($k_stk_ant)?$k_stk_ant.=",".$r['antal']:$k_stk_ant=$r['antal'];
			($kostpris)?$kostpris.=",".$r2['pris']*$kobs_valutakurs/100:$kostpris=$r2['pris']*$kobs_valutakurs/100;
#			$kostpris[$x]=dkdecimal($r2['pris']*$kobs_valutakurs/100);
		} else {
			$r2 = db_fetch_array(db_select("select kostpris from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
			$kostpris=$r2['kostpris'];
		}
		if ($valutakurs && $valutakurs!=100) $kostpris=$kostpris*100/$valutakurs;
		if ($koid[$x]) {
			$q3 = db_select("select ordrenr,art from ordrer where id = $koid[$x]",__FILE__ . " linje " . __LINE__);
			$r3 = db_fetch_array($q3);
			($kobs_ordre_nr)?$kobs_ordre_nr.=','.$r3['ordrenr']:$kobs_ordre_nr=$r3['ordrenr'];
			($kobs_ordre_id)?$kobs_ordre_id.=','.$koid[$x]:$kobs_ordre_id=$koid[$x];
			($kobs_ordre_art)?$kobs_ordre_art.=','.$r3['art']:$kobs_ordre_art=$r3['art'];
		}
	}
	if (!$x) {
		$r2 = db_fetch_array(db_select("select kostpris from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
		$kostpris=$r2['kostpris'];
	}
	$tmp=$kostpris.chr(9).$kobs_ordre_nr.chr(9).$k_stk_ant.chr(9).$kobs_ordre_id.chr(9).$kobs_ordre_art;
	return($tmp);
}
######################################################################################################################################
function find_momssats($id,$kasse) {

	if ($id) $r=db_fetch_array(db_select("select momssats from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	elseif($kasse) {
		$tmp=array();
		$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
		$tmp=explode(chr(9),$r['box7']);
		$momsgrp=$tmp[$kasse-1]; # Kasseraekken starter med 1 og momsraekken med 0;
		$r = db_fetch_array(db_select("select box2 as momssats from grupper where art = 'SM' and kodenr = '$momsgrp'",__FILE__ . " linje " . __LINE__));
	}
	$momssats=$r['momssats']*1;
	return($momssats);
}
######################################################################################################################################
function grupperabat($antal,$rabatgruppe) {

	$r=db_fetch_array(db_select("select box1,box2,box3 from grupper where kodenr = '$rabatgruppe' and art = 'VRG'",__FILE__ . " linje " . __LINE__));
	$m_type=$r['box1'];
	$m_rabat=explode(";",$r['box2']);
	$m_antal=explode(";",$r['box3']);
	$x=0;

#	$r=db_fetch_array(db_select("select sum(antal) as antal from varer where rabatgruppe = '$rabatgruppe'",__FILE__ . " linje " . __LINE__));

	if ($antal>=$m_antal[$x]) {
		while ($m_antal[$x+1] && $antal >= $m_antal[$x+1]) {
			$x++;
		}
	} else $m_rabat[$x]=0;
#	if ($m_type =='percent') $m_rabat[$x]=$pris*$m_rabat[$x]/100;
	$m_rabat[$x]=$m_rabat[$x]*-1;
	return ($m_rabat[$x].";".$m_type);
} # endfunc grupperabat
######################################################################################################################################
function vareopslag($art,$sort,$fokus,$id,$vis_kost,$ref,$find)
{
	global $bgcolor;
	global $bgcolor5;

	$linjebg=NULL;

	if ($id && !$art) {
		$r=db_fetch_array(db_select("select art from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		$art=$r['art'];
	}

	if ($find) $find=str_replace("*","%",$find);
	if ($art=='PO' && !strpos($_SERVER['PHP_SELF'],'pos_ordre')) $art='DO';

	if($art=='DO'||$art=='DK') {
		sidehoved($id, "../debitor/ordre.php", "../lager/varekort.php", $fokus, "Kundeordre $id - Vareopslag");
		$href="ordre.php";
	} elseif ($art=='PO') {
#		print "<tr><td colspan=\"5\"><hr>";
		sidehoved($id, "../debitor/pos_ordre.php", "", $fokus, "POS ordre $id - Vareopslag");
#		print "<hr></td></tr>";
		$href="pos_ordre.php";
	}
	if ($art!='PO') {
		$listeantal=0;
		$q=db_select("select id,beskrivelse from grupper where art='PL' and box4='on' order by beskrivelse",__FILE__ . " linje " . __LINE__);
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
			print "</select></td><td><input type=\"submit\" name=\"prislist\" value=\"Vis\"></td>";
		}


		if ($vis_kost) {print "<td colspan=8 align=center><a href=$href?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id>Udelad kostpriser</a></td></tr>";}
		else {print "<td colspan=5 align=center><a href=$href?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=on>Vis kostpriser</a></td></tr>";}
	}
	print"<td><b><a href=$href?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost>Varenr</a></b></td>";
	print"<td><b> Enhed</b></td>";
	print"<td><b><a href=$href?sort=beskrivelse&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beskrivelse</a></b></td>";
	print"<td align=right><b><a href=$href?sort=salgspris&funktion=vareOpslag&fokus=$fokus&id=$id>Salgspris</a></b></td>";
	print"<td align=right><b><a href=$href?sort=beholdning&funktion=vareOpslag&fokus=$fokus&id=$id>Beholdning</a></b></td>";
	if ($vis_kost) {print"<td align=right><b> Kostpris</b></td>";}
#	if ($art!='PO') print"<td align=right><b><a href=$href?sort=beholdning&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beh.</a></b></td>";
	print"<td><br></td>";
	print" </tr>\n";

	if ($ref){
		$row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'",__FILE__ . " linje " . __LINE__));
		$row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__));
		$lager=$row['kodenr']*1;
	}
	if (!$sort) $sort = 'varenr';
	if ($find) $query = db_select("select * from varer where lukket != '1' and $fokus like '$find' order by $sort",__FILE__ . " linje " . __LINE__);
	else $query = db_select("select * from varer where lukket != '1' order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$query2 = db_select("select box8 from grupper where art='VG' and kodenr='$row[gruppe]'",__FILE__ . " linje " . __LINE__);
#		$row2 =db_fetch_array($query2);
#		if (($row2['box8']=='on')||($row['samlevare']=='on')){
#			if (($row['beholdning']!='0')and(!$row['beholdning'])){db_modify("update varer set beholdning='0' where id=$row[id]",__FILE__ . " linje " . __LINE__);}
#		}
#		elseif ($row['beholdning']){db_modify("update varer set beholdning='0' where id=$row[id]",__FILE__ . " linje " . __LINE__);}

		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if ($art=="PO") print "<td colspan=\"2\"><a href=\"$href?vare_id=$row[id]&fokus=$fokus&id=$id\"><INPUT TYPE=\"button\" STYLE=\"width: 2.5em;height: 2.5em;\"> $row[varenr]</a></td>";
		else print "<td><a href=\"$href?vare_id=$row[id]&fokus=$fokus&id=$id\">$row[varenr]</a></td>";
		print "<td>$row[enhed]<br></td>";
		print "<td><a href=\"$href?vare_id=$row[id]&fokus=$fokus&id=$id\">$row[beskrivelse]</a><br></td>";
		$salgspris=dkdecimal($row['salgspris']);
		print "<td align=right>$salgspris<br></td>";
		if ($vis_kost=='on') {
			$query2 = db_select("select kostpris from vare_lev where vare_id = $row[id] order by posnr",__FILE__ . " linje " . __LINE__);
			$row2 = db_fetch_array($query2);
			$kostpris=dkdecimal($row2['kostpris']);
			print "<td align=right>$kostpris<br></td>";
		}
		$reserveret=0;
		if ($lager>=1){
			$q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest>0 and lager=$lager",__FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				$q3 = db_select("select * from reservation where batch_kob_id=$r2[id]",__FILE__ . " linje " . __LINE__);
				while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3['antal'];}
			}
			$linjetext="<span title= 'Reserveret: $reserveret'>";
			if ($r2= db_fetch_array(db_select("select beholdning from lagerstatus where vare_id=$row[id] and lager=$lager"))) {
				print "<td align=right>$linjetext $r2[beholdning]</span></td>";
			}
		}
		else {
			$q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest > 0",__FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				$q3 = db_select("select * from reservation where batch_kob_id=$r2[id]",__FILE__ . " linje " . __LINE__);
				while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3['antal'];}
			}
			$linjetext="<span title= 'Reserveret: $reserveret'>";
			print "<td align=right>$linjetext ".dkdecimal($row[beholdning])."</span></td>";
		}
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function tekstopslag($sort, $id)
{
	global $bgcolor;
	global $bgcolor5;

	$linjebg=NULL;

	sidehoved($id, "ordre.php", "", $fokus, "Kundeordre $id - Vareopslag");

	print"<td width=100% align=\"center\"><table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" align=\"center\" valign = \"top\">";
	print"<tbody><tr>";

	$x=0;
	$q = db_select("select * from ordretekster order by tekst",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		print "<tr><td></td><td align=\"left\" title=\"".findtekst(491,$sprog_id)."\" style=\"width:800px;\"><!--tekst 491--><a href=ordre.php?id=$id&tekst_id=$r[id]>$r[tekst]</a><br></td>";
		print "<td title=\"".findtekst(492,$sprog_id)."\" align=\"center\"><!--tekst 492--><a href=\"ordre.php?id=$id&tekst_id=$r[id]&slet_tekst=$r[id]\" onclick=\"return confirm('".findtekst(493,$sprog_id)."')\"><!--Tekst 493--><img src=../ikoner/delete.png border=0></a></td>\n";
		print "</tr>";
	}
	print "<form name=\"ordre\" action=\"ordre.php?returside=$returside\" method=\"post\">\n";
	print "<input type=\"hidden\" name=\"id\" value='$id'>";
	print "<tr><td>Ny fast tekst</td><td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:800px;\" name=\"ny_linjetekst\"></td>\n";
	print "<td colspan=\"2\"><input type=\"submit\" accesskey=\"g\" value=\"Gem\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>\n";
	print "</tr>";
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function sidehoved($id, $returside, $kort, $fokus, $tekst)
{
	global $bgcolor2;
	global $top_bund;
	global $color;
	global $sprog_id;
	global $charset;
	#$returside; maa ikke vaere global

	($kort=="../lager/varekort.php")?$ny_id=$id:$ny_id=0;

	$alerttekst=findtekst(154,$sprog_id);
	print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Kundeordre</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\"></head>";
	print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
	print "<div align=\"center\">";

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\" colspan=\"6\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";

	if (!strstr($returside,"ordre.php")) print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$returside','$alerttekst')\" accesskey=L>Luk</a></td>";
	else print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$returside?id=$id','$alerttekst')\" accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund> $color$tekst</td>";
	print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$kort?returside=$returside&ordre_id=$ny_id&fokus=$fokus','$alerttekst')\" accesskey=N>Ny</a></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
	print "<tr><td valign=\"top\" align=center>";
}
######################################################################################################################################
if (!function_exists('pbsfakt')) {
	function pbsfakt($id) {

		if ($id && $id>0) {
			if ($r=db_fetch_array(db_select("select id from pbs_liste where afsendt = ''",__FILE__ . " linje " . __LINE__))) $liste_id = $r['id'];
			else {
				$liste_date=date("Y-m-d");
				$afsendt=NULL;
				db_modify("insert into pbs_liste (liste_date,afsendt) values ('$liste_date','$afsendt')",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select id from pbs_liste where afsendt = ''",__FILE__ . " linje " . __LINE__));
				$liste_id = $r['id'];
			}
			if (db_fetch_array(db_select("select id from pbs_ordrer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__))) {
				echo "Faktura nr $r[fakturanr] findes allerede i PBS liste<br>";
			}	else {
				$r=db_fetch_array(db_select("select fakturanr, konto_id from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
				$konto_id=$r['konto_id'];
				db_modify("insert into pbs_ordrer (liste_id,ordre_id) values ('$liste_id','$id')",__FILE__ . " linje " . __LINE__);
				echo "Faktura nr $r[fakturanr] tilf&oslash;jet til PBS liste<br>";	
			}
		}	
	}	
}
##################################################
function pos_afrund($sum) {
	$negativ=0;
	if ($sum<0) {
		$negativ=1;
		$sum*=-1;			
	}
	list($kr,$ore)=explode(".",$sum);
	$ore=substr($sum*100,-2);
	if ($ore<25) $ore=0;
	elseif ($ore>=25 && $ore<75) $ore=50;
	else {
		$kr++;
		$ore=0;
	}
	$sum=($kr*100+$ore)/100;
	if ($negativ) $sum*=-1;
	return($sum);
}



?>
