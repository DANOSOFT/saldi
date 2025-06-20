<?php
// #----------------- debitor/ordrefunc.php -----ver 3.0.3---- 2010.05.31 ----------
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

function levering($id,$hurtigfakt,$genfakt,$webservice) {

global $regnaar;
global $levdate;
global $lev_nr;
global $db;

$fp=fopen("../temp/ordrelev.log","a");
transaktion("begin");

$q = db_select("select lev_nr from batch_salg where ordre_id = $id order by lev_nr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($lev_nr<=$r['lev_nr']){
		$lev_nr=$r['lev_nr']+1;
	}
}
if (!$lev_nr) {$lev_nr=1;}
		
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
}
$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
if (!$r['levdate']){
	print "<BODY onLoad=\"javascript:alert('Leveringsdato SKAL udfyldes')\">";
#	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
} else {
	if ($r['levdate']<$r['ordredate']) {
		 print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
		 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		 exit;
	}
	list ($year, $month, $day) = split ('-', $r['levdate']);
	$year=trim($year);
	$ym=$year.$month;
	if (!$webservice && ($ym<$aarstart||$ym>$aarslut)) {
		print "<BODY onLoad=\"javascript:alert('Leveringsdato uden for regnskabs&aring;r')\">";
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

		$query = db_select("select * from ordrelinjer where ordre_id = '$id' and samlevare = 'on'",__FILE__ . " linje " . __LINE__);
		while ($row =db_fetch_array($query)){
			if ($row[leveres]!=0) samlevare($row[id], $row['vare_id'], $row['leveres']);
		}
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($row =db_fetch_array($query)){
			if (($row[posnr]>0)&&(strlen(trim(($row[varenr])))>0)){
				$x++;
				$linje_id[$x]=$row['id'];
				$kred_linje_id[$x]=$row['kred_linje_id'];
				$vare_id[$x]=$row['vare_id'];
				$varenr[$x]=$row['varenr'];
				$antal[$x]=$row['antal'];
				$leveres[$x]=$row['leveres'];
				$pris[$x]=$row['pris'];
				$rabat[$x]=$row['rabat'];
				$nettopris[$x]=$row['pris']-($row['pris']*$row['rabat']/100);
				$serienr[$x]=trim($row['serienr']);
				$posnr[$x]=$row['posnr'];
				$bogf_konto[$x]=$row['bogf_konto'];
				if ($hurtigfakt=='on') $leveres[$x]=$antal[$x];
			}
		}
		$linjeantal=$x;
		for ($x=1; $x<=$linjeantal; $x++) {
			$tidl_lev=0;
			$query = db_select("select antal from batch_salg where linje_id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
			while ($row =db_fetch_array($query)) {
				$tidl_lev=$tidl_lev+$row[antal];
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
				while ($row =db_fetch_array($query)) {$sn_antal[$x]=$sn_antal[$x]+1; }
			 if ($leveres[$x]!=$sn_antal[$x]) {
					 print "<BODY onLoad=\"javascript:alert('Der er sat $leveres[$x] til levering men valgt $sn_antal[$x] serienumre (pos nr: $posnr[$x])')\">";
					 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
					 exit;
				}
			}	
			if (($leveres[$x]<0)&&($serienr[$x])) {
				$sn_antal[$x]=0;
				$query = db_select("select * from serienr where salgslinje_id = $kred_linje_id[$x]*-1",__FILE__ . " linje " . __LINE__);
				while ($row =db_fetch_array($query)) {
					$sn_antal[$x]=$sn_antal[$x]+1;
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
			$kostpris[$x]=$row['kostpris'];
			$gruppe[$x]=$row['gruppe'];
			if ($row['beholdning']) {$beholdning[$x]=$row['beholdning'];}
			else $beholdning[$x]=0;
			$beholdning[$x]=$beholdning[$x]-$leveres[$x];
#			if (trim($row['samlevare'])=='on') {
#				for ($a=1; $a<=$leveres[$x]; $a++) samlevare($vare_id[$x], $linje_id[$x]);
#			}
			if (!$gruppe[$x]) {
				print "<BODY onLoad=\"javascript:alert('Vare tilh&oslash;rer ikke nogen varegruppe - kontroller vare og indstillinger! (pos nr: $posnr[$x])')\">";
				if ($art=='PO') print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">";
				else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				exit;
			}
			if ($vare_id[$x] && $leveres[$x])  {
				linjeopdat($id, $gruppe[$x], $linje_id[$x], $beholdning[$x], $vare_id[$x], $leveres[$x], $pris[$x], $nettopris[$x], $rabat[$x], $row['samlevare'], $x, $posnr[$x], $serienr[$x], $kred_linje_id[$x],$bogf_konto[$x]);
			}
		}
	}
}

transaktion("commit");
return("OK");

} #endfunc levering

#############################################################################################

function linjeopdat($id ,$gruppe, $linje_id, $beholdning, $vare_id, $antal, $pris, $nettopris, $rabat, $samlevare, $linje_nr, $posnr, $serienr, $kred_linje_id,$bogf_konto){
	
	global $fp;
	global $levdate;
	global $fakturadate;
	global $sn_id;
	global $art;
	global $ref;
	global $lev_nr;

	$query = db_select("select * from grupper where art='VG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
	if ($row =db_fetch_array($query)){
		$box1=trim($row[box1]); $box2=trim($row[box2]); $box3=trim($row[box3]); $box4=trim($row[box4]); $box8=trim($row[box8]); $box9=trim($row[box9]);
	} else {
		$r=db_fetch_array(db_select("select posnr from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__));
		print "<BODY onLoad=\"javascript:alert('Varegruppe ikke opsat korrekt, pos nr $r[posnr]')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	} 
	if (!$box3 || !$box4) {
		$fejltekst="Varegruppe $gruppe mangler kontonummer for varek&oslash;b og/eller varesalg (Indstillinger -> Varegrp)";
		print "<BODY onLoad=\"javascript:alert('$fejltekst')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	}			
	if (($box8!='on')||($samlevare=='on')){
		if($bogf_konto) $box4=$bogf_konto;
		db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr) values (0, $vare_id, $linje_id, '$levdate', $id, $antal, '$pris', '$lev_nr')",__FILE__ . " linje " . __LINE__);
	}	else {
		if($bogf_konto) $box4=$bogf_konto;
		db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		db_modify("update varer set beholdning=$beholdning where id='$vare_id'",__FILE__ . " linje " . __LINE__);
		if ($box9=='on') {
			if ($antal<0) {krediter($id, $levdate, $beholdning, $vare_id, $antal*-1, $pris, $linje_id, $serienr, $kred_linje_id);} 
			else {batch_salg_lev($id, $levdate, $fakturadate, $beholdning, $vare_id, $antal, $pris, $nettopris, $linje_id, $linje_n, $posnr, $serienr, $lager);}
		} else {
			if($bogf_konto) $box4=$bogf_konto;
			db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
			db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr) values (0, $vare_id, $linje_id, '$levdate', $id, $antal, '$pris', '$lev_nr')",__FILE__ . " linje " . __LINE__);
		}
	}
}
#####################

function batch_salg_lev($id, $levdate, $fakturadate, $beholdning, $vare_id, $antal, $pris, $nettopris, $linje_id, $linje_nr, $posnr, $serienr, $lager){
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
		$batch_kob_id[$x]=$row[batch_kob_id];
		$res_antal[$x]=$row[antal];
		$res_sum=$res_sum+$row[antal];
		$lager=$row[lager];
		if ($res_sum>=$antal){  #Indsat 091106 for 
			$diff[$x]=$res_sum-$antal;
			$res_antal[$x]=$res_antal[$x]-$diff[$x];
			$res_sum=$antal;
		}
	}
	$res_linje_antal=$x;
	$rest=$rest-$res_sum;

	if ($rest>0) {  #Hvis ikke alle varer er koebt hjem eller reserveret saaaa....	
		$query = db_select("select * from reservation where batch_salg_id = $linje_id*-1 and antal = $rest",__FILE__ . " linje " . __LINE__); #Finder reserverede varer som er bestilt hos lev.
		$row=db_fetch_array($query);
		if ($row['linje_id']) {
			db_modify("insert into batch_salg(vare_id, linje_id, salgsdate, ordre_id, antal, lev_nr) values ($vare_id, $linje_id, '$levdate', $id, $rest, '$lev_nr')",__FILE__ . " linje " . __LINE__);
			$query = db_select("select id from batch_salg where vare_id=$vare_id and linje_id=$linje_id and salgsdate='$levdate' and ordre_id=$id and antal=$rest and	lev_nr='$lev_nr' order by id desc",__FILE__ . " linje " . __LINE__);
			$row =db_fetch_array($query);
			$batch_salg_lev_id=$row['id']; 
#20090620 Rettet fra batch_salg_id til batch_salg_lev_id - kundeordre 1761 i saldi_2_20090620-1204.sdat
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
			$kob_antal=$row[antal];
			$kob_rest=$row[rest];
			$kob_ordre_id=$row[ordre_id];
			$kob_pris=$row[pris];
			$lager=$row[lager];
			if (!$kob_pris) {$kob_pris='0';}
			$kob_rest=$kob_rest-$res_antal[$x];
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
		}
		else {
			print "<BODY onLoad=\"javascript:alert('Hmm - Indkbsordre kan ikke findes - levering kan ikke foretages - Kontakt systemadministrator')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	}
}
###############################################################
function lagerstatus ($vare_id, $lager, $antal) {
	global $ref;

	if (!$lager) {
		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'",__FILE__ . " linje " . __LINE__))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager=$row['kodenr'];}
		}
	}
	$lager=$lager*1;
	
	$query = db_select("select * from lagerstatus where vare_id='$vare_id' and lager='$lager'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$tmp=$row[beholdning]-$antal;
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
	$kred_linje_id=$row[kred_linje_id];
	$posnr=$row[posnr];

	if ($kred_linje_id>0) { #if Indsat 071106 grundet fejl ved negativt vareantal p�ordin� salgsordre.
		# Anvendes ved ved negativt vareantal p� ordin�r salgsordre - n�r varen tidligere har v�ret solgt til kunden
		$x=0;
		$q = db_select("select * from batch_salg where linje_id=$kred_linje_id",__FILE__ . " linje " . __LINE__);
		while ($r =db_fetch_array($q)) {
			$x++;
			$batch_kob_id[$x]=$r[batch_kob_id];
			$batch_kob_antal[$x]=$r[antal];
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
			$q2 = db_select("select rest from batch_kob where id=$batch_kob_id[$x]",__FILE__ . " linje " . __LINE__);
			$r2 =db_fetch_array($q2);
			$kob_rest[$x]=$r2[rest]+$batch_kob_antal[$x];
			db_modify("update batch_kob set rest=$kob_rest[$x] where id=$batch_kob_id[$x]",__FILE__ . " linje " . __LINE__);
			lagerstatus($vare_id, $lager, -$batch_kob_antal[$x]);	
			db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, lev_nr) values ($batch_kob_id[$x], $vare_id, $linje_id, '$levdate', $id, -$batch_kob_antal[$x], '$lev_nr')",__FILE__ . " linje " . __LINE__); # Rettet til $antal fra $batch_kob_antal[$x] -- rettet tilbage 12.11.07 dat det ikke fungerer hvis antal != batch_kob_antal[$x]  .
			$q3 = db_select("select id from batch_salg where batch_kob_id=$batch_kob_id[$x] and vare_id=$vare_id and linje_id=$linje_id and salgsdate='$levdate' and ordre_id=$id and antal=-$batch_kob_antal[$x] and lev_nr='$lev_nr' order by id desc",__FILE__ . " linje " . __LINE__); #se ovenfor.
			$r3 =db_fetch_array($q3);
			$batch_salg_id[$x]=$r3['id']; 
			if ($serienr) {
				$q4 = db_select("select * from serienr where salgslinje_id=-$kred_linje_id",__FILE__ . " linje " . __LINE__);
				while ($r4 =db_fetch_array($q4)) {
					db_modify("insert into serienr (kobslinje_id, vare_id, batch_kob_id, serienr, batch_salg_id, salgslinje_id) values ($r4[kobslinje_id], $r4[vare_id], $r4[batch_kob_id], '$r4[serienr]', $batch_salg_id[$x], $linje_id)",__FILE__ . " linje " . __LINE__); 
					db_modify("update serienr set batch_salg_id=-$r4[batch_salg_id] where id=$r4[id]",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	} else {
		db_modify("update ordrelinjer set kred_linje_id = '-1' where id = $linje_id",__FILE__ . " linje " . __LINE__); #indsat 20071004
		db_modify("insert into batch_kob(vare_id, linje_id, kobsdate, ordre_id, antal, rest) values ($vare_id, $linje_id, '$levdate', $id, $antal, $antal)",__FILE__ . " linje " . __LINE__);
		if ($serienr) {
			$query = db_select("select * from serienr where salgslinje_id=-$kred_linje_id",__FILE__ . " linje " . __LINE__);
			while ($row =db_fetch_array($query)) {
				 db_modify("insert into serienr (kobslinje_id, vare_id, batch_kob_id, serienr, batch_salg_id, salgslinje_id) values ($row[kobslinje_id], $row[vare_id], $row[batch_kob_id], '$row[serienr]', $batch_salg_id, $linje_id)",__FILE__ . " linje " . __LINE__); 
				 db_modify("update serienr set batch_salg_id=-$row[batch_salg_id] where id=$row[id]",__FILE__ . " linje " . __LINE__);
			}
		}
	}
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
function samlevare($linje_id, $v_id, $leveres) 
{
	global $id;
	list($vare_id, $stk_antal, $antal) = fuld_stykliste($v_id, '', 'basisvarer');
	for ($x=1; $x<=$antal; $x++) {
		if ($r=db_fetch_array(db_select("select * from varer where id=$vare_id[$x]",__FILE__ . " linje " . __LINE__))) {
			$stk_antal[$x]=$stk_antal[$x]*$leveres;
			db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, samlevare, posnr) values ('$id', '$r[varenr]', '$vare_id[$x]', '$r[beskrivelse]', '$stk_antal[$x]', '$stk_antal[$x]', 0, $linje_id, '100' )",__FILE__ . " linje " . __LINE__);
		}
	}
}
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
	$ordredate=$row['ordredate'];
	$levdate=$row['levdate'];
	$fakturadate=$row['fakturadate'];
	$nextfakt=$row['nextfakt'];
	$art=$row['art'];
	$kred_ord_id=$row['kred_ord_id'];
	$valuta=$row['valuta'];
	$art=$row['art'];
	$fakturanr=$row['fakturanr'];
	
	if ($row['status']!=2){
		return("invoice allready created for order id $id"); 
#		print "<BODY onLoad=\"javascript:alert('Fakturerering er allerede udf&oslash;rt')\">";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
#		exit;
	}
	
	$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);

	if ($row = db_fetch_array($query)){
	#		$year=substr(str_replace(" ","",$row['box2']),-2);#aendret 060308 - grundet mulighed for fakt i aar 2208
		$year=trim($row['box2']);
		$aarstart=str_replace(" ","",$year.$row['box1']);
	#		$year=substr(str_replace(" ","",$row['box4']),-2);
		$year=trim($row['box4']);
		$aarslut=str_replace(" ","",$year.$row['box3']);
	}
	$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);

	if (!$fakturadate){
		return("missing invoicedate for order $id"); 
		#	print "<meta http-equiv=\"refresh\" content=\"0;URL=fakturadato.php?id=$id&pbs=$pbs&mail_fakt=$mail_fakt&returside=bogfor.php\">";
		#exit;
	}
	if ($valuta && $valuta!='DKK') {
		if ($r= db_fetch_array(db_select("select valuta.kurs as kurs, grupper.box3 as difkto from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=".nr_cast("grupper.kodenr")." and valuta.valdate <= '$fakturadate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
			$valutakurs=$r['kurs']*1;
			$difkto=$r['difkto']*1;
			if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))) {
				if ($webservice) return("Kontonr $difkto (kursdiff) eksisterer ikke");
				else {
					return("Kontonr $difkto (kursdiff) eksisterer ikke");
#					print "<BODY onLoad=\"javascript:alert('Kontonr $difkto (kursdiff) eksisterer ikke')\">";
#					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
#					exit;
				}
			}
		} else {
			$tmp = dkdato($fakturadate);
			return("Der er ikke nogen valutakurs for $valuta den $tmp (fakturadatoen).");
#			print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $tmp (fakturadatoen).')\">";
#			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
#			exit;
		}
	} else {
		$valuta='DKK';
		$valutakurs=100;
	}
	if (!$levdate){
		return ("Missing deliverydate");
#		print "<BODY onLoad=\"javascript:alert('Leveringsdato SKAL udfyldes')\">";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
#		exit;
	}	

	if ($levdate<$ordredate){
		return ("Deliverydate prior to orderdate");
#	 	print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
#	 	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
# 	exit;
	}

	if ($fakturadate<$levdate)	{
		return ("Invoicedate prior to orderdate");
#		print "<BODY onLoad=\"javascript:alert('Fakturadato er f&oslash;r leveringsdato')\">";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
#		exit;
	}	

	if (($nextfakt)&& ($nextfakt<=$fakturadate)){
		return ("Next_invoicedate prior to invoicedate");
#		print "<BODY onLoad=\"javascript:alert('Genfaktureringsdato skal v&aelig;re efter fakturadato')\">";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
# 	exit;
	}
	list ($year, $month, $day) = split ('-', $fakturadate);
	$year=trim($year);
	$ym=$year.$month;

	if (!$webservice && ($ym<$aarstart || $ym>$aarslut))	{
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
	if (!$fejl) {
 		transaktion("begin");
		if ($art!="PO") {
			$fakturanr=1;
			$query = db_select("select fakturanr from ordrer where art = 'DO' or art = 'DK'",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)){
				if ($fakturanr <= $row['fakturanr']) {$fakturanr = $row['fakturanr']+1;}
			}
			
			$r=db_fetch_array(db_select("select box1 from grupper where art = 'RB' and kodenr='1'",__FILE__ . " linje " . __LINE__));
			if ($fakturanr<$r['box1']) $fakturanr=$r['box1'];

			if ($fakturanr < 1) $fakturanr = 1;	
		}	
		batch_kob($id, $art); 
		batch_salg($id);
		db_modify("update ordrer set status=3, fakturanr=$fakturanr, valutakurs=$valutakurs where id=$id",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select box5 from grupper where art='DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__));

		$svar=momsupdat($id);
		if ($r['box5']=='on') $svar=bogfor_nu($id,$webservice);
		if ($svar != "OK") {
			return($svar);
			exit;
		} else transaktion("commit");
	}
	return($svar);
} #endfunc bogfor	

function momsupdat($id) {
	global $db;
	global $brugernavn;
	
	$r=db_fetch_array(db_select("select momssats from ordrer where id = $id",__FILE__ . " linje " . __LINE__));
	$momssats=$r['momssats']*1;
	$q=db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$sum=$sum+afrund(($r['pris']-($r['pris']/100*$r['rabat']))*$r['antal'],2);
		if ($r['vare_id'] && $r['momsfri']!='on')
			if ($r['momssats']) $varemomssats=$r['momssats'];
			else $varemomssats=$momssats;
			$moms=$moms+afrund(($r['pris']-($r['pris']/100*$r['rabat']))*$r['antal']/100*$varemomssats,2);
#
#		echo "$sum<br>"; 	
	}
#	$moms=afrund($sum/100*$momssats,2);
#echo "$moms<br>";	
	$sum*=1; $moms*=1; 
	db_modify("update ordrer set sum=$sum, moms=$moms where id = '$id'",__FILE__ . " linje " . __LINE__);
	return("OK");
}
function batch_salg($id) {
	global $fakturadate; 
	global $valutakurs;
	
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
	}
	$linjeantal=$x;	
	
	for ($x=1; $x<=$linjeantal; $x++) {
		$kostpris=0;

#echo "select id, pris, rabat, projekt, bogf_konto from ordrelinjer where id = $batch_linje_id[$x]<br>";
		$query = db_select("select id, pris, rabat, projekt, bogf_konto from ordrelinjer where id = $batch_linje_id[$x]",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$ordre_linje_id=$row['id'];
#echo "ordrelinjeid $ordre_linje_id<br>";
		$pris = $row['pris']-($row['pris']*$row['rabat']/100);
		$projekt=$row['projekt']*1;
		$bogf_konto=$row['bogf_konto'];
		if ($valutakurs) $pris=afrund($pris*$valutakurs/100,3);
		db_modify("update batch_salg set pris=$pris, fakturadate='$fakturadate' where id=$batch_id[$x]",__FILE__ . " linje " . __LINE__); 
		if ($batch_kob_id[$x]) {
			$query = db_select("select pris, ordre_id from batch_kob where id = $batch_kob_id[$x]",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {
				$kostpris=$row['pris'];
				if ($row['ordre_id']) {
					$query = db_select("select status from ordrer where id = $row[ordre_id]",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					if ($row['status']){$kobsstatus=$row['status'];}
				}	
				else {$kobsstatus=0;}
			}
		}
#		else {#if ($batch_kob_id[$x]) 
	
		$query2 = db_select("select gruppe from varer where id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$gruppe=$row2['gruppe'];
		$query2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$box1=trim($row2['box1']); $box2=trim($row2['box2']); $box3=trim($row2['box3']); $box4=trim($row2['box4']); $box8=trim($row2['box8']); $box9=trim($row2['box9']);
		if ($bogf_konto) $box4=$bogf_konto;
# echo "A update ordrelinjer set bogf_konto=$box4, projekt=$projekt where id=$ordre_linje_id<br>";
		db_modify("update ordrelinjer set bogf_konto=$box4, projekt=$projekt where id=$ordre_linje_id",__FILE__ . " linje " . __LINE__);
		if ($box9=='on'){ # box 9 betyder at der anvendes batch styring  
			if (!$batch_kob_id[$x]) { # saa er varen ikke paa lager, dvs at indkobsordren skal findes i tabellen reservation
				$query = db_select("select linje_id, lager from reservation where batch_salg_id = $batch_id[$x]",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$res_antal=$res_antal+$row['antal']; 
				$res_linje_id=$row['linje_id'];
				$lager=$row['lager'];
				$r1 = db_fetch_array(db_select("select ordre_id, pris, rabat, projekt from ordrelinjer where id = $res_linje_id",__FILE__ . " linje " . __LINE__)); 
				$kob_ordre_id = $r1['ordre_id'];
				$projekt = $r1['projekt'];
				$r2 = db_fetch_array(db_select("select valutakurs from ordrer where id = $kob_ordre_id",__FILE__ . " linje " . __LINE__));
				$kostpris = ($r1['pris']-($r1['pris']*$r1['rabat']/100))*$r2['valutakurs']/100;
				db_modify("update ordrelinjer set kostpris = $kostpris where id=$ordre_linje_id",__FILE__ . " linje " . __LINE__);
			# Hvis levering er sket i flere omgange vil der vaere flere batch_salg linjer paa samme kobs linje, derfor nedenstaende.	 
				if ($row = db_fetch_array(db_select("select id from batch_kob where linje_id=$res_linje_id and vare_id=$vare_id[$x] and ordre_id=$kob_ordre_id",__FILE__ . " linje " . __LINE__))) {
					$batch_kob_id[$x]=$row['id'];
				}
				else {
					db_modify("insert into batch_kob (linje_id, vare_id, ordre_id, pris, lager) values ($res_linje_id, $vare_id[$x], $kob_ordre_id, $pris, $lager)",__FILE__ . " linje " . __LINE__); #Antal indsaettes ikke - dette styres i "reservation"
					$row = db_fetch_array(db_select("select id from batch_kob where linje_id=$res_linje_id and vare_id=$vare_id[$x] and ordre_id=$kob_ordre_id",__FILE__ . " linje " . __LINE__));
					$batch_kob_id[$x]=$row['id'];
				} 
				db_modify("update reservation set batch_kob_id=$batch_kob_id[$x] where linje_id = $res_linje_id",__FILE__ . " linje " . __LINE__);
				db_modify("update batch_salg set batch_kob_id=$batch_kob_id[$x] where id=$batch_id[$x]",__FILE__ . " linje " . __LINE__);		
			}
			$row = db_fetch_array(db_select("select pris from batch_kob where id=$batch_kob_id[$x]",__FILE__ . " linje " . __LINE__)); # kostprisen findes..
			$pris=$row['pris']*1; 
			if ($box1 && $box2 && $pris) { #kostvaerdien flyttes fra "afgang varelager" til "varekob".- hvis der ikke bogfoeres direkte paa varekobs kontoen
				#	if ($valutakurs) $pris=$pris*100/$valutakurs;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1',$antal[$x], '$pris', 0, $id, $box2,'$projekt')",__FILE__ . " linje " . __LINE__);
				$pris=$pris*-1;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1',$antal[$x], '$pris', 0, $id, $box3,'$projekt')",__FILE__ . " linje " . __LINE__);
			}
		} elseif ($box8=='on') { # hvis box8 er 'on' er varen lagerfoert
			$row = db_fetch_array(db_select("select kostpris from varer where id=$vare_id[$x]",__FILE__ . " linje " . __LINE__));
			$kostpris=$row['kostpris']*1;
#			if ($valutakurs) $kostpris=$kostpris*100/$valutakurs;
			db_modify("update ordrelinjer set kostpris = $kostpris where id=$ordre_linje_id",__FILE__ . " linje " . __LINE__);
			if ($box1 && $box2 && $kostpris) {
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1',$antal[$x], '$kostpris', 0, $id, $box2,'$projekt')",__FILE__ . " linje " . __LINE__);
				$kostpris=$kostpris*-1;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1',$antal[$x], '$kostpris', 0, $id, $box3,'$projekt')",__FILE__ . " linje " . __LINE__);
			}
		}
	}
}
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
		$projekt=$row['projekt']*1;
		$serienr=$row['serienr'];
		$batch_kob_id=$row['batch_kob_id']; 
		$query2 = db_select("select id, pris, rabat, projekt bogf_konto from ordrelinjer where id = $row[linje_id]",__FILE__ . " linje " . __LINE__);
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
# echo "B update ordrelinjer set bogf_konto=$box4 where id=$ordre_linje_id<br>";
		if ($bogf_konto) $box4=$bogf_konto;
		db_modify("update ordrelinjer set bogf_konto=$box4 where id=$ordre_linje_id",__FILE__ . " linje " . __LINE__);
		if ($box9=='on'){
			$pris=$pris-$diff;
			$pris=$pris*1;
			if ($valutakurs && $pris) $pris=$pris*100/$valutakurs;
			db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1', $antal, $pris, 0, $id, $box3,'$projekt')",__FILE__ . " linje " . __LINE__);
			$pris=$pris*-1;
			db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1', $antal, $pris, 0, $id, $box2,'$projekt')",__FILE__ . " linje " . __LINE__);
		}
	}
}
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
#echo "$firmanavn | $ordrenr<br>";		
		$valuta=$r['valuta'];
		$kred_ord_id=$r['kred_ord_id'];
		if (!$valuta) $valuta='DKK';
		$projekt[0]=$r['projekt']*1;
		$betalingsbet=$r['betalingsbet'];
		$betalingsdage=$r['betalingsdage']*1;
		$betalt=$r['betalt']*1;
		$sum=$r['sum']+$moms;
		$betaling=$r['felt_1'];
		$kasse=$r['felt_5'];

		$ansat='0';

		$beskrivelse="Indbetaling konto: $kundekontonr";

		$tmp=$sum*-1;
		db_modify("insert into openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr, valuta, valutakurs) values ('$konto_id', '$kundekontonr', '$fakturanr', '$tmp', '$beskrivelse', '$udlign', '$transdate', '0', '$id', '$valuta', '$valutakurs')",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$r = db_fetch_array(db_select("select beskrivelse, box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['box2']; # Kontonr aendres fra at vaere leverandoerkontonr til finanskontonr
		$tekst="Kontonummer for Debitorgruppe `$r[beskrivelse]` er ikke gyldigt";
		if (!$kontonr && $webservice) return($tekst);
		elseif(!$kontonr) print "<BODY onLoad=\"javascript:alert('$tekst')\">";

		if ($sum) {
			if ($sum>0) {$kredit=$sum; $debet='0';}
			else {$kredit='0'; $debet=$sum*-1;}
			
			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.		
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			$debet=afrund($debet,2);
			$kredit=afrund($kredit,2);
			$logdate=date("Y-m-d");
			$logtime=date("H:i");
# echo "B insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', 0, '$logdate', '$logtime', '0', '0', '$id')<br>";
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
						$debet=afrund($sum,2);
						$kredit='0';
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
						$sum=0;
# echo "A insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
						db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
					} 
/*					if ($betaling2==$korttyper[$x]) {
						$debet=afrund($modtaget2,2);
						$kredit='0';
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
						$sum=$sum-$modtaget2;
echo "A1 insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
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
# echo "C insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', '0', '$logdate', '$logtime', '0', '0', '$id')<br>";
 			if ($debet || $kredit) db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', '0', '$logdate', '$logtime', '0', '0', '$id')",__FILE__ . " linje " . __LINE__);
			
			db_modify("update ordrer set status=4, valutakurs=$valutakurs where id=$id",__FILE__ . " linje " . __LINE__);

	}
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

#	print "<table><tbody>";
	$svar="OK";	


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
	$x=0;
	$q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$art=$r['art'];
		$konto_id=$r['konto_id'];
		$kontonr=str_replace(" ","",$r['kontonr']);
		$firmanavn=trim($r['firmanavn']);
		$modtagelse=$r['modtagelse'];
		$transdate=($r['fakturadate']);
		$fakturanr=$r['fakturanr'];
		$ordrenr=$r['ordrenr'];
#echo "$firmanavn | $ordrenr<br>";		
		$valuta=$r['valuta'];
		$kred_ord_id=$r['kred_ord_id'];
		if (!$valuta) $valuta='DKK';
		$projekt[0]=$r['projekt']*1;
		$betalingsbet=$r['betalingsbet'];
		$betalingsdage=$r['betalingsdage']*1;
		$betalt=$r['betalt']*1;
#		$refnr;
		$moms=$r['moms']*1;
#		else {$moms=afrund($r['sum']*$r['momssats']/100,2);}
		$sum=$r['sum']+$moms;
#echo "sum $r[sum] + $moms = $sum<br>";
#exit;		
		$ordreantal=$x;
		$forfaldsdate=usdate(forfaldsdag($r['fakturadate'], $betalingsbet, $betalingsdage));
		$r2= db_fetch_array(db_select("select id, afd from ansatte where navn = '$r[ref]'",__FILE__ . " linje " . __LINE__));
		$afd=$r2['afd']*1;#sikkerhed for at 'afd' har en vaerdi 
		$ansat=$r2['id']*1;
		if ($no_faktbill==1) $bilag='0';
		else $bilag=trim($fakturanr);
		$udlign=0;
		if (substr($art,1,1)=='K') {
			$beskrivelse ="Kreditnota - ".$fakturanr;
			$r=db_fetch_array(db_select("select fakturanr,fakturadate from ordrer where id='$kred_ord_id'",__FILE__ . " linje " . __LINE__));
			$tmp=$sum*-1;
			if (db_fetch_array(db_select("select * from openpost  where konto_id='$konto_id' and amount='$tmp' and faktnr='$r[fakturanr]' and transdate='$r[fakturadate]' and udlignet != '1'",__FILE__ . " linje " . __LINE__))) {
				db_modify("update openpost set udlignet = 1 where konto_id='$konto_id' and amount='$tmp' and faktnr='$r[fakturanr]' and transdate='$r[fakturadate]'");		
				$udlign=1;
			}
		} elseif ($art=='PO') {
			global $kasse;
			global $betaling;
			global $betaling2;
			global $modtaget;
			global $modtaget2;
#echo "$kasse | $betaling | $modtaget | $betaling2 | $modtaget2<br>";
			$beskrivelse ="Bon - ".$fakturanr;
		} else $beskrivelse ="Faktura - ".$fakturanr;
		if ($kontonr) {
#			if ($art=='PO') {
#				$tmp=$sum-$betalt;
#			} else 
$tmp=$sum;
# echo "insert into openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr, valuta, valutakurs, forfaldsdate) values ('$konto_id', '$kontonr', '$fakturanr', '$tmp', '$beskrivelse', '$udlign', '$transdate', '$udlign', '$id', '$valuta', '$valutakurs','$forfaldsdate')<br>";
			db_modify("insert into openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr, valuta, valutakurs, forfaldsdate) values ('$konto_id', '$kontonr', '$fakturanr', '$tmp', '$beskrivelse', '$udlign', '$transdate', '$udlign', '$id', '$valuta', '$valutakurs','$forfaldsdate')",__FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
			$r = db_fetch_array(db_select("select beskrivelse, box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
			$kontonr=$r['box2']; # Kontonr aendres fra at vaere leverandoerkontonr til finanskontonr
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
echo "insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
			db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
*/			
		}
		if ($art=='PO') { #saa er det en kontantordre (POS)
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
						$retur=$retur-$modtaget;
						$sum=$sum-$modtaget;
# echo "A insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
						db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
						db_modify("update kontoplan set saldo=saldo+'$debet' where kontonr='$kortkonti[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
					} 
					if ($betaling2==$korttyper[$x]) {
						$debet=afrund($modtaget2,2);
						$kredit='0';
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
						$sum=$sum-$modtaget2;
# echo "A1 insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
						db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kortkonti[$x]', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
						db_modify("update kontoplan set saldo=saldo+'$debet' where kontonr='$kortkonti[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
					}
				}
#			} elseif ($betaling=='konto') {
			
#			}
		}	
# echo "sum=$sum<br>";
		if ($sum) {
			if ($sum>0) {$debet=$sum; $kredit='0';}
			else {$debet='0'; $kredit=$sum*-1;}
			
			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.		
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			$debet=afrund($debet,2);
			$kredit=afrund($kredit,2);
# echo "B insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
			db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
			$tmp=$debet-$kredit;
			db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$kontonr' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
		}
		if ($valutakurs) $maxdif=2; #Der tillades 2 oeres afrundingsdiff 
		$p=0;
		$q = db_select("select distinct(projekt) from ordrelinjer where ordre_id=$id and vare_id >	'0'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$p++;
			$projekt[$p]=$r['projekt']*1;
		}
		$projektantal=$p;
		for ($t=1;$t<=2;$t++)	{
			for ($p=1;$p<=$projektantal;$p++) {	
				$y=0;
				$tjek= array();
				$bogf_konto = array();
				if ($t==1) {
#echo "select * from ordrelinjer where ordre_id='$id' and projekt='$projekt[$p]' and posnr>=0<br>";					
					$q = db_select("select * from ordrelinjer where ordre_id='$id' and projekt='$projekt[$p]' and posnr>=0",__FILE__ . " linje " . __LINE__);
				} else {
#echo "select * from ordrelinjer where ordre_id='$id' and projekt='$projekt[$p]' and posnr<0<br>";					
					$q = db_select("select * from ordrelinjer where ordre_id='$id' and projekt='$projekt[$p]' and posnr<0",__FILE__ . " linje " . __LINE__);
				}
				while ($r = db_fetch_array($q)) {
					if ($valutakurs) $maxdif=$maxdif+2; #Og yderligere 2 pr ordrelinje.
					$tmp=$projekt[$p].":".$r['bogf_konto'];
					if (!in_array($r['bogf_konto'], $bogf_konto)) {
						$y++;
						$bogf_konto[$y]=$r['bogf_konto'];
						$pris[$y]=$r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100);
						$pris[$y]=afrund($pris[$y],3); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
					}	else {
						for ($a=1; $a<=$y; $a++) {
							if ($bogf_konto[$a]==$r['bogf_konto']) {
								$pris[$a]=$pris[$a]+($r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100));
								$pris[$a]=afrund($pris[$a],3); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
							}
						}		 
					}
				}
				$ordrelinjer=$y;
				if ($indbetaling) $ordrelinjer=0;
#echo "ol $ordrelinjer<br>";				
				for ($y=1;$y<=$ordrelinjer;$y++) {
					if ($bogf_konto[$y]) {
						if ($pris[$y]>0) {$kredit=$pris[$y];$debet=0;}
						else {$kredit=0; $debet=$pris[$y]*-1;}
						if ($t==1 && $valutakurs) {$kredit=$kredit*$valutakurs/100;$debet=$debet*$valutakurs/100;} # Omregning til DKR.		
						$kredit=afrund($kredit,3);$debet=afrund($debet,3);
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
						$debet=afrund($debet,2);
						$kredit=afrund($kredit,2);
# echo "C insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '$fakturanr', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt[$p]', '$ansat', '$id')<br>";						
						db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '$fakturanr', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt[$p]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
						$tmp=$debet-$kredit;
						db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$bogf_konto[$y]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
					}
				}
			}
		}
		if ($art != 'PO') {
			$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id';",__FILE__ . " linje " . __LINE__));
			$r = db_fetch_array(db_select("select box1 from grupper where art='DG' and kodenr='$r[gruppe]';",__FILE__ . " linje " . __LINE__));
			$momskode=substr(trim($r['box1']),1,1);
		} else { #saa er det en kontantordre
			$tmparray=array();
			$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
			$tmparray=explode(chr(9),$r['box7']);
			$momskode=$tmparray[$kasse-1];
		}
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
#echo "moms $moms<br>";	
			$moms=afrund($moms,2);
#if ($moms) echo "D insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$box1', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";		
			if ($moms) {
				db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$box1', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
				$tmp=$debet-$kredit;
				db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$box1' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
			}
			$valutakurs=$valutakurs*1;
#echo "update ordrer set status=4, valutakurs=$valutakurs where id=$id<br>";		
			db_modify("update ordrer set status=4, valutakurs=$valutakurs where id=$id",__FILE__ . " linje " . __LINE__);
			db_modify("delete from ordrelinjer where ordre_id=$id and posnr < 0",__FILE__ . " linje " . __LINE__);
		}
	}
	$d_kontrol=afrund($d_kontrol,2);
	$k_kontrol=afrund($k_kontrol,2);
#echo "$d_kontrol $k_kontrol<br>";	
	if ($diff=afrund($d_kontrol-$k_kontrol)) {
			if ($valuta!='DKK' && abs($diff)<=$maxdif) { #Der maa max vaere en afvigelse paa 1 oere pr ordrelinje m fremmed valuta;
			$debet=0; $kredit=0;
			if ($diff<0) $debet=$diff*-1;
			else $kredit=$diff;
			$debet=afrund($debet,2);
			$kredit=afrund($kredit,2);
# echo "E insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$difkto', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
			db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$difkto', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
			$tmp=$debet-$kredit;
			db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
		} elseif (abs($diff) < 0.05) {
			$r = db_fetch_array(db_select("select * from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__));
			$oredifkto=$r['box2'];
			$r = db_fetch_array(db_select("select id from kontoplan where kontotype = 'D' and kontonr = '$oredifkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$oredifkto', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
				$tmp=$debet-$kredit;
				db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$oredifkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
			
			} else {
				if ($webservice) return ('Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff'); 
				else print "<BODY onLoad=\"javascript:alert('Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff')\">";
			}
		} else {
# echo "Id	$id<br>";
# echo "D	$d_kontrol K $k_kontrol<br>";	
			$message=$db." | Uoverensstemmelse i posteringssum: ordre_id=$id, d=$d_kontrol, k=$k_kontrol | ".__FILE__ . " linje " . __LINE__." | ".$brugernavn." ".date("Y-m-d H:i:s");
			$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'SALDI Fejl', $message, $headers);
			if (!$webservice) print "<BODY onLoad=\"javascript:alert('Der er konstateret en uoverensstemmelse i posteringssummen, ordre $ordrenr, kontakt DANOSOFT p&aring; telefon 4690 2208')\">";
			else return("Der er konstateret en uoverensstemmelse i posteringssummen, ordre $ordrenr, kontakt DANOSOFT p&aring; telefon 4690 2208' debet $debet != kredit $kredit");
#     	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	}

	if ($title != "Massefakturering" && !$webservice && $art !='PO') genberegn($regnaar);
	return($svar);
}
######################################################################################################################################
function kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt)
{
	if ($fokus=='kontonr') $find=$kontonr; 
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
	if ($find) $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'D' and upper($fokus) like upper('$find') and lukket != 'on' order by $sort",__FILE__ . " linje " . __LINE__);
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
function opret_ordrelinje($id,$varenr,$antal,$beskrivelse,$pris,$rabat_ny,$art,$momsfri,$posnr,$linje_id,$incl_moms) {
	
	global $regnaar;
	global $momsats;
	global $formularsprog;
	
	$dd=date("Y-m-d");
	$pris*=1;
	if ($pris && $pris > 99999999) {
		return("Ulovlig v&aelig;rdi i prisfelt");
	}
	$r=db_fetch_array(db_select("select ordrer.valutakurs as valutakurs,adresser.gruppe as debitorgruppe,adresser.rabatgruppe as debitorrabatgruppe from adresser,ordrer where ordrer.id='$id'and adresser.id=ordrer.konto_id",__FILE__ . " linje " . __LINE__));
	$debitorgruppe=$r['debitorgruppe']*1;
	$debitorrabatgruppe=$r['debitorrabatgruppe']*1;
	$valutakurs=$r['valutakurs']*1;
	
	$varenr=addslashes($varenr);
	$varenr_low=strtolower($varenr);
	$varenr_up=strtoupper($varenr);

	if ($r=db_fetch_array(db_select("select * from varer where lower(varenr) = '$varenr_low' or upper(varenr) = '$varenr_up' or varenr = '$varenr'",__FILE__ . " linje " . __LINE__))) {
		$vare_id=$r['id'];
		$varenr=addslashes($r['varenr']);
		$enhed=addslashes($r['enhed']);
		$folgevare=$r['folgevare']*1;
		$rabatgruppe=$r['rabatgruppe']*1;
		$varegruppe=$r['gruppe']*1;
		$special_price=$r['special_price']*1;
		list($m_antal,$temp)=split(";",$r['m_antal']);
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
		if (!$posnr && $art!='PO' && $r2=db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__))) {
			$posnr=$r2['posnr']+1;
		} elseif (!$posnr) $posnr=1;
		$r2 = db_fetch_array(db_select("select box4,box6,box7 from grupper where art = 'VG' and kodenr = '$varegruppe'",__FILE__ . " linje " . __LINE__));
		$bogfkto = $r2['box4'];
		$momsfri = $r2['box7'];
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
#			$pris=usdecimal($pris);
			$kostpris=$r['kostpris']*1;
		} else {
			global $momssats;
#			$pris=usdecimal($pris);
			if ($incl_moms) $pris=$pris-($pris*$varemomssats/(100+$varemomssats));
			$kostpris=$r['kostpris']*1;
		}
	} else return ("Varenr: $varenr eksisterer ikke");
	$m_rabat=0;
	$rabat_ny=$rabat_ny*1;
# echo "select rabat,posnr,id,antal from ordrelinjer where vare_id = '$vare_id' and ordre_id='$id' and beskrivelse='$beskrivelse' and pris=$pris and rabat=$rabat_ny<br>";
	if ($linje_id && $art=='DO') $tmp="id='$linje_id'";	
	elseif ($art=='PO') $tmp= "vare_id = '$vare_id' and ordre_id='$id' and pris='$pris' and rabat='$rabat_ny'";
	if((($linje_id && $art=='DO') || $art=='PO') && $r=db_fetch_array(db_select("select rabat,posnr,id,antal from ordrelinjer where $tmp",__FILE__ . " linje " . __LINE__))) {
		$antal=$r['antal']+$antal;
		if ($antal) {
			db_modify("update ordrelinjer set m_rabat='0', antal='$antal' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			if ($m_antal && $antal >= $m_antal) m_rabat($r['id'],$vare_id,$r['posnr'],$antal,id);
		}
		elseif ($art=='PO') db_modify("delete from ordrelinjer where id = '$r[id]'",__FILE__ . " linje " . __LINE__); 
	} else {
		if ($rabat_ny) $rabat=$rabat_ny;
		else {
			if (!$debitorrabatgruppe or !db_fetch_array(db_select("select id from grupper where art='DRG' and kodenr='$debitorrabatgruppe'",__FILE__ . " linje " . __LINE__))){
				$debitorrabatgruppe=$debitorgruppe;
			}
			$rabat=$r2['rabat']*1;
			$r2=db_fetch_array(db_select("select rabat from rabat where vare='$varegruppe' and debitor='$debitorrabatgruppe'",__FILE__ . " linje " . __LINE__));
			$rabat=$r2['rabat']*1;
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
		if (is_numeric($posnr)) db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,enhed,beskrivelse,antal,rabat,m_rabat,pris,kostpris,momsfri,momssats,posnr,projekt,folgevare,rabatgruppe,bogf_konto,kred_linje_id) values ('$id','$vare_id','$varenr','$enhed','$beskrivelse','$antal','$rabat','$m_rabat','$pris','$kostpris','$momsfri','$varemomssats','$posnr','0','$folgevare','$rabatgruppe','$bogfkto','$kred_linje_id')",__FILE__ . " linje " . __LINE__);
		if ($m_antal && $antal >= $m_antal) {
			$r2=db_fetch_array(db_select("select id from ordrelinjer where vare_id='$vare_id' and pris=$pris and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
			m_rabat($r2['id'],$vare_id,0,$antal,$id);
		}	
	}
	$sum=$pris*$antal;
	return($sum);
#	$varenr=$next_varenr;
#	$antal=NULL;
} # endfunc opret_orderlinje
######################################################################################################################################
function m_rabat($linje_id,$vare_id,$posnr,$antal,$ordre_id) {
	
	#	echo "XXX";
	
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
	db_modify("update ordrelinjer set m_rabat='$m_rabat[$x]' where id = '$linje_id'",__FILE__ . " linje " . __LINE__);
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
	
	if ($find) $find=str_replace("*","%",$find);
	
	if($art=='DO'||$art=='DK') {
		sidehoved($id, "../debitor/ordre.php", "../lager/varekort.php", $fokus, "Kundeordre $id - Vareopslag");
		$href="ordre.php";
	} elseif ($art=='PO') {
		sidehoved($id, "../debitor/pos_ordre.php", "", $fokus, "POS ordre $id - Vareopslag");
		$href="pos_ordre.php";
	}
	
	
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	if ($art!='PO') {
		if ($vis_kost) {print "<tr><td colspan=8 align=center><a href=$href?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id>Udelad kostpriser</a></td></tr>";}
		else {print "<tr><td colspan=4 align=center><a href=$href?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=on>Vis kostpriser</a></td></tr>";}
	}
	print"<td><b><a href=$href?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost>Varenr</a></b></td>";
	print"<td><b> Enhed</b></td>";
	print"<td><b><a href=$href?sort=beskrivelse&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beskrivelse</a></b></td>";
	print"<td align=right><b><a href=$href?sort=salgspris&funktion=vareOpslag&fokus=$fokus&id=$id>Salgspris</a></b></td>";
	print"<td align=right><b><a href=$href?sort=beholdning&funktion=vareOpslag&fokus=$fokus&id=$id>Beholdning</a></b></td>";
	if ($vis_kost) {print"<td align=right><b> Kostpris</b></td>";}
	if ($art!='PO') print"<td align=right><b><a href=$href?sort=beholdning&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beh.</a></b></td>";
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
		$row2 =db_fetch_array($query2);
		if (($row2['box8']=='on')||($row['samlevare']=='on')){
			if (($row['beholdning']!='0')and(!$row['beholdning'])){db_modify("update varer set beholdning='0' where id=$row[id]",__FILE__ . " linje " . __LINE__);}
		}
		elseif ($row['beholdning']){db_modify("update varer set beholdning='0' where id=$row[id]",__FILE__ . " linje " . __LINE__);}

		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if ($art=="PO") print "<td><a href=\"$href?vare_id=$row[id]&fokus=$fokus&id=$id\"><INPUT TYPE=\"button\" STYLE=\"width: 2.5em;height: 2.5em;\"> $row[varenr]</a></td>";
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

	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	
	$q = db_select("select * from ordretekster order by tekst",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		print "<tr><td>$row[tekst]<br></td></tr>>";
	}
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
#global $returside; maa ikke vaere global 

	$alerttekst=findtekst(154,$sprog_id);
	print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Kundeordre</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\"></head>";
	print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
	print "<div align=\"center\">";

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";

	if (!strstr($returside,"ordre.php")) {print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$returside','$alerttekst')\" accesskey=L>Luk</a></td>";}
	else {print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$returside?id=$id','$alerttekst')\" accesskey=L>Luk</a></td>";}
	print "<td width=\"80%\" $top_bund> $color$tekst</td>";
	print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$kort?returside=$returside&ordre_id=$id&fokus=$fokus','$alerttekst')\" accesskey=N>Ny</a></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
	print "<tr><td valign=\"top\" align=center>";
}
######################################################################################################################################
?>
