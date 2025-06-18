<?php	
// ------------- debitor/massefakt.php ----- (modul nr 6)------ lap 2.0.9----2009-09-14-------
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

@session_start();
$s_id=session_id();

$skriv=NULL;

$modulnr=6;
$title="Massefakturering"; #Hvis title aendres skar bogfor.php ogsaa aendres.
$css="../css/standard.css";


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");

$valg=if_isset($_GET['valg']);

$r=db_fetch_array(db_select("select box2, box3 from grupper where art='MFAKT' and kodenr='1'",__FILE__ . " linje " . __LINE__));
$delfakturer=$r['box2'];
$levfrist=$r['box3'];

$tmp=date("U")-$levfrist*24*3600;
$dellevdate=date("Y-m-d",$tmp);	# hvis ordren er fra denne dato eller foer delleveres ordren.

#if ($levfrist) {
#	include("../includes/forfaldsdag.php");
#	$dd=date("Y-m-d");
#	$dellevdate=usdate(forfaldsdag($dd,"netto",-$levfrist));
#} elseif ($delfakturer) $dellevdate=date("Y-m-d");

#include("../debitor/levering.php");
#include("../debitor/bogfor.php");
$tekst=findtekst(154,$sprog_id);
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=\"../debitor/ordreliste.php?valg=$valg\" accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\"> $title</td>";
print "<td width=\"10%\" $top_bund align=\"right\"><br></td></tr>";
print "</tbody></table>";
print "</td></tr><td valign=top align=center>";

if ($r=db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__))) {
	$aarstart=str_replace(" ","",$r['box2'].$r['box1']);
	$aarslut=str_replace(" ","",$r['box4'].$r['box3']);
} 
$tmp=date("Ym");
if ($tmp<$aarstart || $tmp>$aarslut) {
	print "<BODY onLoad=\"javascript:alert('Dags dato ligger ikke i aktivt regnskabs&aring;r')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php?\">";
	exit;
}

$ordre_id=(if_isset($_GET['ordre_id']));
$ordre_antal=(if_isset($_GET['ordre_antal']));
$rest=(if_isset($_GET['rest']));
$rest_id=array(if_isset($_GET['rest_id']));
$skriv=(if_isset($_GET['skriv']));

if (!$ordre_id) $ordre_id=1;
if (!$ordre_antal) $ordre_antal=0;
if (!$rest) $rest=0;
if (!$rest_id) $rest_id=array();

$x=0;
while ($ordre_id) {
	if ($gl_id==$ordre_id) {
#		echo $ordre_id;
		exit;
	}
	$gl_id=$ordre_id;
	if ($delfakturer) $ordre_id=delfakturer($ordre_id,$valg);
	list($ordre_id,$leveres)=find_next($ordre_id,$valg);
	if ($ordre_id && $leveres) {
		$x++;
		$ordre_antal++;
		if ($skriv) $skriv=$skriv.",".$ordre_id;
		else $skriv=$ordre_id;
		momsupdat($ordre_id);
		levering($ordre_id,'on','on');
		bogfor($ordre_id);
	} elseif ($ordre_id) {
		$rest++;
		$rest_id[$rest]=$ordre_id;
	} 
	if ($x>=10) {
		if ($valg=='tilbud') $qtxt="select count(*) as antal from ordrer where status='0' and art='DO'";
		else $qtxt= "select count(*) as antal from ordrer where (status='1' or status='2') and art='DO'";
		if ($r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__))) echo "Vent - $r[antal] ordrer tilbage<br>"; 
		$tmp=explode($rest_id);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=massefakt.php?ordre_id=$ordre_id&ordre_antal=$ordre_antal&rest=$rest&rest_id=$tmp&skriv=$skriv\">";
		exit;
	}
}
include("../includes/genberegn.php");
genberegn($regnaar);

#if ($skriv) print "<BODY onLoad=\"JavaScript:window.open('../debitor/formularprint.php?id=-1&skriv=$skriv&formular=4,3&ordre_antal=$ordre_antal' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";			
if ($skriv) print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/formularprint.php?id=-1&skriv=$skriv&formular=4,3&ordre_antal=$ordre_antal\">";

if ($rest) {
	$x=0;
	$y=0;
	$varenr=array();
	$grupper=array();
	$q=db_select("select kodenr from grupper where art = 'VG' and box8='on'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$x++;
		$grupper[$x]=$r['kodenr'];
	}	
	for ($x=1;$x<=$rest;$x++) {
		$q=db_select("select * from ordrelinjer where ordre_id='$rest_id[$x]'",__FILE__ . " linje " . __LINE__);	
		while($r=db_fetch_array($q)){
			if (!in_array($r['varenr'],$varenr)) {
				$y++;
				$r2=db_fetch_array(db_select("select * from varer where id='$r[vare_id]'",__FILE__ . " linje " . __LINE__));
				$antal[$y]=$r['antal'];
				$varenr[$y]=$r2['varenr'];
				$beholdning[$y]=$r2['beholdning']*1;
				$gruppe[$y]=$r2['gruppe'];
				}
			for ($z=1;$z<=$y;$z++) { 
				if ($r['varenr']==$varenr[$z]) {
					if ($antal[$z]>$beholdning[$z] && in_array($gruppe[$z],$grupper)) {
						db_modify("update ordrelinjer set leveres='$beholdning[$z]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
						$beholdning[$z]=0;
					} elseif (in_array($gruppe[$z],$grupper)) $beholdning[$z]=$beholdning[$z]-$antal[$z];
				}
			}
		}
	}
#	print "<table border=1><tbody>";
#	print "<tr><td colspan=2>F�lgende ordrer kan ikke leveres</td></tr>";
#	print "<tr><td align=center>Ordrenr</td><td align=center>Ordredato</td></tr>";
#	for ($x=1;$x<=$rest;$x++) {
#		$r=db_fetch_array(db_select("select * from ordrer where id = '$rest_id[$x]'",__FILE__ . " linje " . __LINE__));	
#		print "<tr><td align=center><a href=\"ordre.php?id=$rest_id[$x]\" target=\"_blank\">$r[ordrenr]</a></td><td align=center>".dkdato($r['ordredate'])."</td></tr>";
#	}
#	print "</tbody><table>";
} #else {
	$dd=date("Y-m-d");
	$dkdd=date("d-m-Y");
	$ordreliste=NULL;
	$q=db_select("select * from ordrer where fakturadate='$dd' and art='DO'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!$ordreliste) $ordreliste="ordre_id = '$r[id]'";
		else $ordreliste=$ordreliste." or ordre_id = '$r[id]'";
	}
	print "<table border=1><tbody>";
	if ($ordreliste) {
		$x=0;
		$beskrivelse==array();
		$varenr=array();
		$antal=array();
		$q=db_select("select * from ordrelinjer where ($ordreliste) and vare_id > '0' order by beskrivelse",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$r2=db_fetch_array(db_select("select grupper.box8 as lagervare from varer, grupper where varer.id='$r[vare_id]' and grupper.art='VG' and grupper.kodenr = varer.gruppe",__FILE__ . " linje " . __LINE__));
			if ($r['antal']>0 && $r2['lagervare']) {
				if (!in_array($r['varenr'],$varenr)) {
				$x++;
				$beskrivelse[$x]=db_escape_string($r['beskrivelse']);
					$varenr[$x]=db_escape_string($r['varenr']);
					$antal[$x]=$r['antal']*1;
					$q2=db_select("select modtagelser.antal as antal from modtageliste,modtagelser where modtageliste.modtagdate='$dd' and modtageliste.modtaget='V' and modtagelser.liste_id=modtageliste.id and modtagelser.varenr='$varenr[$x]'",__FILE__ . " linje " . __LINE__);
					while($r2=db_fetch_array($q2)) $antal[$x]=$antal[$x]-$r['antal'];
				}
				else $antal[$x]=$antal[$x]+$r['antal'];
			}
		}
		if ($x>=1) {
			print "<tr><td colspan=3 align=center></td></tr>";
#			print "<tr><td colspan=3 align=center>Plukliste $dkdd</td></tr>";
#			print "<tr><td align=center>Varenr</td><td align=center>Beskrivelse</td><td align=center>Antal</td></tr>";
#			for ($y=1;$y<=$x;$y++) print "<tr><td>$varenr[$y]</td><td>$beskrivelse[$y]</td><td align=right>$antal[$y]</td></tr>";
		}
	} else	print "<tr><td>Ingen ordrer til levering</td></tr>";
	print "</tbody><table>";
#}
print "</tbody><table>";
#print "<meta http-equiv=\"refresh\" content=\"10;URL=../includes/luk.php?\">";
function delfakturer($ordre_id,$valg) {
	global $dellevdate;
	# Tjekker om der er noget der kan leveres. Hvis der er nedskrives antal til der der kan leveres og der oprettes en ny ordre med resten med samme ordrenr.
	$drop=0;
	$fakturer=0;
	$delfakturer=0;

	if ($valg=='tilbud') $qtxt="select id, ordredate from ordrer where status='0' and art='DO' and id > '$ordre_id' order by id";
	elseif ($valg=='ordrer') $qtxt="select id, ordredate from ordrer where (status='1' or status='2') and art='DO' and id > '$ordre_id' order by id";
	else return;
	if ($r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$ordre_id=$r['id'];
		$ordredate=$r['ordredate'];
		$x=0;
		$q=db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$x++;
			if (!$drop && $r['vare_id']) {
				$vare_id[$x]=$r['vare_id'];
				$antal[$x]=$r['antal'];
				$linje_id[$x]=$r['id'];
				if ($vare_id[$x]) {
					if (db_fetch_array(db_select("select id from batch_salg where linje_id=$linje_id[$x]",__FILE__ . " linje " . __LINE__))) $drop=1;
				else {
					$r2=db_fetch_array(db_select("select varer.beholdning as beholdning, grupper.box8 as lagervare from varer, grupper where varer.id=$vare_id[$x] and grupper.art='VG' and grupper.kodenr = varer.gruppe",__FILE__ . " linje " . __LINE__));
					$lagervare[$x]=$r2['lagervare'];
					$beholdning[$x]=$r2['beholdning'];
					if ($lagervare[$x] && $beholdning[$x]>0) $fakturer=1; #saa kan der faktureres.
					if ($lagervare[$x] && $beholdning[$x]<$antal[$x]) { #saa skal der delfaktureres
						$delfakturer=1;
						$ny_antal[$x]=$antal[$x]-$beholdning[$x];
					}elseif ($lagervare[$x]) $ny_antal[$x]=0; 
					else $ny_antal[$x]=0;
				}
			} else $ny_antal[$x]=0;
		}
	}
	if ($ordredate>$dellevdate) $delfakturer=0;
} else $drop=1;
if (!$drop && $fakturer && $delfakturer) {
	transaktion("begin");
	$r=db_fetch_array($q=db_select("select * from ordrer where id = '$ordre_id'",__FILE__ . " linje " . __LINE__));
	$ordrenr=db_escape_string($r['ordrenr']);
	$konto_id=db_escape_string($r['konto_id']);
	$kontonr=db_escape_string($r['kontonr']);
	$firmanavn=db_escape_string($r['firmanavn']);
	$addr1=db_escape_string($r['addr1']);
	$addr2=db_escape_string($r['addr2']);
	$postnr=db_escape_string($r['postnr']);
	$bynavn=db_escape_string($r['bynavn']);
	$land=db_escape_string($r['land']);
	$kontakt=db_escape_string($r['kontakt']);
	$kundeordnr=db_escape_string($r['kundeordnr']);
	$betalingsdage=db_escape_string($r['betalingsdage']);
	$betalingsbet=db_escape_string($r['betalingsbet']);
	$cvrnr=db_escape_string($r['cvrnr']);
	$ean=db_escape_string($r['ean']);
	$institution=db_escape_string($r['institution']);
	$notes=db_escape_string($r['notes']);
	$art=db_escape_string($r['art']);
	$ordredate=db_escape_string($r['ordredate']);
	$momssats=db_escape_string($r['momssats']);
	$ref=db_escape_string($r['ref']);
	$status=db_escape_string($r['status']);
	$lev_navn=db_escape_string($r['lev_navn']);
	$lev_addr1=db_escape_string($r['lev_addr1']);
	$lev_addr2=db_escape_string($r['lev_addr2']);
	$lev_postnr=db_escape_string($r['lev_postnr']);
	$lev_bynavn=db_escape_string($r['lev_bynavn']);
	$lev_kontakt=db_escape_string($r['lev_kontakt']);
	$valuta=db_escape_string($r['valuta']);
	$projekt=db_escape_string($r['projekt']);
	$sprog=db_escape_string($r['sprog']);

	$tidspkt=date("U");
	db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, kundeordnr, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, momssats, tidspkt, ref, status, lev_navn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt, valuta, projekt, sprog) values ('$ordrenr','$konto_id','$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$kundeordnr','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$notes','$art','$ordredate','$momssats','$tidspkt','$ref','$status','$lev_navn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt','$valuta','$projekt','$sprog')",__FILE__ . " linje " . __LINE__);
	$q = db_select("select max(id) as id from ordrer where ordrenr=$ordrenr and art='$art' and tidspkt='$tidspkt'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q); 
	$ny_id=$r['id'];
	$x=0;
	$sum=0;
	$ny_sum=0;
	$moms=0;
	$ny_moms=0;
	$q=db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		$posnr[$x]=$r['posnr']*1;
		$varenr[$x]=db_escape_string($r['varenr']);
		$vare_id[$x]=$r['vare_id']*1;
		$beskrivelse[$x]=db_escape_string($r['beskrivelse']);
		$enhed[$x]=$r['enhed'];
		$antal[$x]=$r['antal']*1;
		$pris[$x]=$r['pris']*1;
		$rabat[$x]=$r['rabat']*1;
		$serienr[$x]=$r['serienr'];
		$momsfri[$x]=$r['momsfri'];
		$samlevare[$x]=$r['samlevare'];
		$kostpris[$x]=$r['kostpris']*1;
			$antal[$x]=$antal[$x]-$ny_antal[$x];
			$linjesum=round(($antal[$x]*$pris[$x]-$antal[$x]*$pris[$x]*$rabat[$x]/100)+0.0001,3);
			$sum=$sum+$linjesum;
			if (!$momsfri[$x]) $moms=$moms+round($linjesum/100*$momssats+0.0001,3);
		if ($vare_id[$x]){
			db_modify("update ordrelinjer set antal=$antal[$x] where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__); # id fra 1. s�gning.
			if ($ny_antal[$x]) {
				$ny_linjesum=round(($ny_antal[$x]*$pris[$x]-$antal[$x]*$pris[$x]*$rabat[$x]/100)+0.0001,3);
				$ny_sum=$ny_sum+$ny_linjesum;
				if (!$momsfri[$x]) $ny_moms=$ny_moms+round($ny_linjesum/100*$momssats+0.0001,3);
				db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, serienr, momsfri, kostpris) values ('$ny_id','$posnr[$x]','$varenr[$x]','$vare_id[$x]','$beskrivelse[$x]','$enhed[$x]', $ny_antal[$x], '$pris[$x]','$rabat[$x]','$serienr[$x]','$momsfri[$x]','$kostpris[$x]')",__FILE__ . " linje " . __LINE__);
			}
		}	else {
			db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$ny_id','$posnr[$x]','$beskrivelse[$x]')",__FILE__ . " linje " . __LINE__);
		}
	}
	db_modify("update ordrer set tidspkt = '',hvem = '', moms='$moms', sum='$sum' where id ='$ordre_id'"); 
	db_modify("update ordrer set tidspkt = '',hvem = '', moms='$ny_moms', sum='$ny_sum' where id ='$ny_id'"); 

# exit;	
	transaktion("commit");
} 
if (!$drop) $ordre_id--; #eller bliver den ikke fundet i find_ordre
return $ordre_id;
}#endfunc delfakturer.

function find_next($ordre_id,$valg) {
# Finder n�ste godkendte �bne ordre hvor alt kan leveres, s�tter alt til levering og returnerer ordre_id

$leveres=1;
$dd=date("Y-m-d");
#$x=0;
if ($valg=='tilbud') $qtxt="select id from ordrer where status='0' and art='DO' and id > '$ordre_id' order by id";
elseif ($valg=='ordrer') $qtxt="select id from ordrer where (status='1' or status='2') and art='DO' and id > '$ordre_id' order by id";
else return;
if ($r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$ordre_id=$r['id'];
	$leveres=1;
	$q=db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by vare_id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!isset($i_ordre[$y])) {
			$i_ordre[$y]=0;
			$vare_id[$y]=0;
		} 
		if ($vare_id[$y] && $vare_id[$y]!=$r['vare_id']) {
			$r3=db_fetch_array(db_select("select varer.beholdning as beholdning, grupper.box8 as lagervare from varer, grupper where varer.id=$vare_id[$y] and grupper.art='VG' and grupper.kodenr = varer.gruppe",__FILE__ . " linje " . __LINE__));
			if ($r3['beholdning']<$i_ordre[$y]){
				if($r3['lagervare']) $leveres=0;
			}
			$y++;
			$linje_id[$y]=$r['id'];
			$i_ordre[$y]=0;
			$vare_id[$y]=$r['vare_id'];
			$beskrivelse[$y]=$r['beskrivelse'];
			$i_ordre[$y]=$i_ordre[$y]+$r['antal'];
			$q2=db_select("select antal from batch_salg where linje_id='$linje_id[$y]'",__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)) {
				$i_ordre[$y]=$i_ordre[$y]-$r2['antal'];
			}
			db_modify("update ordrelinjer set leveres='$i_ordre[$y]' where id='$linje_id[$y]'",__FILE__ . " linje " . __LINE__);
		}
	}
	if ($vare_id[$y]) {
		$r3=db_fetch_array(db_select("select varer.beholdning as beholdning, grupper.box8 as lagervare from varer, grupper where varer.id=$vare_id[$y] and grupper.art='VG' and grupper.kodenr = varer.gruppe",__FILE__ . " linje " . __LINE__));
		if ($r3['beholdning']<$i_ordre[$y]){
			if($r3['lagervare']) $leveres=0;
			db_modify("update ordrelinjer set leveres='0' where id='$linje_id[$y]'",__FILE__ . " linje " . __LINE__);
		}
	}
	if ($leveres) {
		db_modify("update ordrer set levdate='$dd', fakturadate='$dd', status='2' where id='$ordre_id'",__FILE__ . " linje " . __LINE__);
	}	
} else $ordre_id=0;
$tmp=array($ordre_id,$leveres);
return $tmp;
} #endfunc findnext

/*
function momsupdat($ordre_id){
	$linje_id=array();
	$linjesum=array();
	$projekt=array(); #nodlosninig til initiering af projektfelt hvis ikke sat.
	$momsgrundlag=0;
	$ordresum=0;
	$x=0;
# echo "select status, projekt, momssats from ordrer where id='$ordre_id' and status < '4'<br>";	
	if ($r=db_fetch_array(db_select("select status, projekt, momssats from ordrer where id='$ordre_id' and status < '4'",__FILE__ . " linje " . __LINE__))) {
		$momssats=$r['momssats']*1;
		$projekt[0]=$r['projekt']*1;
		$q=db_select("select * from ordrelinjer where ordre_id='$ordre_id'");
		while ($r=db_fetch_array($q)) {
			$x++;
			$linje_id[$x]=$r['id'];
			if ($r['bilag']>=0) {
				if ($projekt[0]) $projekt[$x]=$projekt[0];
				else $projekt[$x]=$r['projekt']*1;
				$linjesum[$x]=round(($r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100))+0.0001,2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
				$ordresum+=$linjesum[$x];
				if ($r['momsfri']!='on') {
					$momsgrundlag+=$linjesum[$x];
				} 
			}
		}
		$linjeantal=$x;
		$moms=round(($momsgrundlag*$momssats/100)+0.0001,2);
# echo "update ordrer set sum='$ordresum', moms='$moms' where id='$ordre_id'<br>";		
		db_modify("update ordrer set sum='$ordresum',moms='$moms' where id='$ordre_id'",__FILE__ . " linje " . __LINE__);
		for ($x=1;$x<=$linjeantal;$x++) {
# echo "update ordrelinjer set projekt=$projekt[$x] where id = $linje_id[$x]<br>";		
			db_modify("update ordrelinjer set projekt=$projekt[$x] where id = $linje_id[$x]");
			}
	}
}
*/
?>