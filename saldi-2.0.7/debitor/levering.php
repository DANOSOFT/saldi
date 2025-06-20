<?php
	@session_start();
	$s_id=session_id();

// --------------debitor/levering.php--------lap 2.0.7--------2009.05.11--------------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

$id=NULL;	
if (isset($_GET['id'])) $id=($_GET['id']);

if ($id && $id>=1) { 
	$modulnr=5;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	include("../includes/fuld_stykliste.php");
	$hurtigfakt=if_isset($_GET['hurtigfakt']);
	$genfakt=if_isset($_GET['genfakt']);
	$pbs=if_isset($_GET['pbs']);
	$mail_fakt=if_isset($_GET['mail_fakt']);
	
	transaktion("begin");
	levering($id,$hurtigfakt,$genfakt);
	transaktion("commit");
	if ($hurtigfakt=='on') {
		db_modify("update ordrer set status=2 where id='$id'");
		print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?id=$id&genfakt=$genfakt&mail_fakt=$mail_fakt&pbs=$pbs\">";
	}
	else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
}

function levering($id,$hurtigfakt,$genfakt) {

global $regnaar;
global $levdate;
global $lev_nr;

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
$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
$row =db_fetch_array($query);
if (!$row[levdate]){
	print "<BODY onLoad=\"javascript:alert('Leveringsdato SKAL udfyldes')\">";
#	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
}
else {
	if ($row[levdate]<$row[ordredate]) {
		 print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
		 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		 exit;
	}
	list ($year, $month, $day) = split ('-', $row[levdate]);
	$year=trim($year);
	$ym=$year.$month;
	if (($ym<$aarstart)||($ym>$aarslut)){
		print "<BODY onLoad=\"javascript:alert('Leveringsdato uden for regnskabs&aring;r')\">";
		 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		 exit;
	}
	if ($hurtigfakt=='on'&&!$fakturadate) {
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
				$linje_id[$x]=$row[id];
				$kred_linje_id[$x]=$row[kred_linje_id];
				$vare_id[$x]=$row['vare_id'];
				$varenr[$x]=$row['varenr'];
				$antal[$x]=$row[antal];
				$leveres[$x]=$row[leveres];
				$pris[$x]=$row[pris];
				$rabat[$x]=$row[rabat];
				$nettopris[$x]=$row[pris]-($row[pris]*$row[rabat]/100);
				$serienr[$x]=trim($row['serienr']);
				$posnr[$x]=$row[posnr];
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
				 while($row = db_fetch_array($query)) $tidl_lev=$tidl_lev-$row[antal];
				 if ($leveres[$x]>$tidl_lev+$antal[$x]) $leveres[$x]=$antal[$x]-$tidl_lev;
			}
		}
		for ($x=1; $x<=$linjeantal; $x++)	{
			$sn_start=0;
			$query = db_select("select * from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
			$row =db_fetch_array($query);
			$kostpris[$x]=$row[kostpris];
			$gruppe[$x]=$row[gruppe];
			if ($row[beholdning]) {$beholdning[$x]=$row[beholdning];}
			else {$beholdning[$x]=0;}
			$beholdning[$x]=$beholdning[$x]-$leveres[$x];
#			if (trim($row['samlevare'])=='on') {
#				for ($a=1; $a<=$leveres[$x]; $a++) samlevare($vare_id[$x], $linje_id[$x]);
#			}
			if (!$gruppe[$x]) {
				print "<BODY onLoad=\"javascript:alert('Vare tilhrer ikke nogen varegruppe - kontroller vare og indstillinger! (pos nr: $posnr[$x])')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				exit;
			}
			if (($vare_id[$x])&&($leveres[$x]!=0)) {
				linjeopdat($id, $gruppe[$x], $linje_id[$x], $beholdning[$x], $vare_id[$x], $leveres[$x], $pris[$x], $nettopris[$x], $rabat[$x], $row['samlevare'], $x, $posnr[$x], $serienr[$x], $kred_linje_id[$x]);
			}
		}
	}
}
transaktion("commit");
} #endfunc bogfor

#############################################################################################

function linjeopdat($id ,$gruppe, $linje_id, $beholdning, $vare_id, $antal, $pris, $nettopris, $rabat, $samlevare, $linje_nr, $posnr, $serienr, $kred_linje_id){
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
		db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr) values (0, $vare_id, $linje_id, '$levdate', $id, $antal, '$pris', '$lev_nr')",__FILE__ . " linje " . __LINE__);
	}
	else {
		db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		db_modify("update varer set beholdning=$beholdning where id='$vare_id'",__FILE__ . " linje " . __LINE__);
		if ($box9=='on') {
			if ($antal<0) {krediter($id, $levdate, $beholdning, $vare_id, $antal*-1, $pris, $linje_id, $serienr, $kred_linje_id);} 
			else {batch_salg_lev($id, $levdate, $fakturadate, $beholdning, $vare_id, $antal, $pris, $nettopris, $linje_id, $linje_n, $posnr, $serienr, $lager);}
		} else {
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
		if ($row[linje_id]) {
			db_modify("insert into batch_salg(vare_id, linje_id, salgsdate, ordre_id, antal, lev_nr) values ($vare_id, $linje_id, '$levdate', $id, $rest, '$lev_nr')",__FILE__ . " linje " . __LINE__);
			$query = db_select("select id from batch_salg where vare_id=$vare_id and linje_id=$linje_id and salgsdate='$levdate' and ordre_id=$id and antal=$rest and	lev_nr='$lev_nr' order by id desc",__FILE__ . " linje " . __LINE__);
			$row =db_fetch_array($query);
			$batch_salg_lev_id=$row['id'];
			db_modify("update reservation set batch_salg_id=$batch_salg_id where batch_salg_id=$linje_id*-1",__FILE__ . " linje " . __LINE__);
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

	if ($kred_linje_id>0) { #if Indsat 071106 grundet fejl ved negativt vareantal pï¿½ordinï¿½ salgsordre.
		# Anvendes ved ved negativt vareantal på ordinær salgsordre - når varen tidligere har været solgt til kunden
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
			$batch_salg_id[$x]=$r3[id]; 
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
		$antal=$row[antal];
		$leveres=$row[leveres];
		$posnr=$row[posnr];
		$vare_id=$row[vare_id];
		$varenr=$row['varenr'];
		$serienr=$row['serienr'];
		$query = db_select("select status, art, konto_id, ref from ordrer where id = '$row[ordre_id]'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$konto_id=$row[konto_id];
		$status=$row[status];
		$art=$row[art];

		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$row[ref]'",__FILE__ . " linje " . __LINE__))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager=$row['kodenr']*1;}
		}
	}

	$query = db_select("select * from batch_salg where linje_id = $linje_id",__FILE__ . " linje " . __LINE__);
	while($row = db_fetch_array($query)) $leveres=$antal-$row[antal];

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

?>
</body></html>
