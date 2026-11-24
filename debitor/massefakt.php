<?php	
// ------------- debitor/massefakt.php ----- (modul nr 6)------ ver 5.1.1----2026-11-19-------
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
// Copyright (c) 2004-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261022 CL/MJ SST-876 Names the items the webshops would not take, at both exits: this page invoices ten orders and then hands over to itself for the next ten, and anything printed after that refresh is never written.
// ----------------------------------------------------------------------
// 20261028 CDX/MJ SST-876 Maintain file history metadata for the webshop synchronization review.
// 20261029 CDX/MJ SST-876 Match the header version to the mass-invoicing warning behavior.
// 20261105 CL/LH Bulk invoicing of more than ten orders goes on to the next batch instead of dying in explode(): the redirect joins the undeliverable orders with implode() and carries valg, and the next batch reads them back as order ids from index 1.
//                The ordre_id and ordre_antal URL values are cast to int.
// 20261117 CL/ASR delfakturer(): the remainder line of a partly delivered order keeps the line's rabatart, procent and omvbet.
//                 A discount in kr per unit became a percentage on the remainder invoice, and a reverse-charge line was charged VAT.
// 20261119 CL/ASR delfakturer() passes db_modify() its second argument when it writes the two orders' totals.
//                 PHP 8 stopped bulk invoicing with Delfakturer at the first order to split (ArgumentCountError), and the split was rolled back.
//                 The totals count a discount in kr, procent and reverse charge as momsupdat() does, and the remainder's discount is on its own quantity.
//                 A line's quantity is read as a decimal, so 2,5 units are no longer cut to 2 on the kept order.
//                 Each line's VAT is at its own rate as momsupdat() takes it, and both totals are rounded to whole øre as the order page writes them.
//                 With negative stock the order keeps none of the line and the remainder gets what was ordered, so no invoice line gets a negative quantity from the split.
//                 A negative line is never split.
//                 The remainder order keeps the order's reverse charge (omvbet), department (afd), PBS, e-mail, phone, extra fields and performed_by, as Del ordre copies them.
//                 Without omvbet a credit note of the remainder credited VAT the invoice never charged, and a remainder saved or invoiced on the order page was charged VAT.

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

$tmp=date("U") - (float)$levfrist * 24 * 3600;
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

$ordre_id=(int)($_GET['ordre_id'] ?? 0);
$ordre_antal=(int)($_GET['ordre_antal'] ?? 0);
# Orders that cannot be delivered, from index 1; each batch passes them on to the next as a comma-separated list.
$rest_id=array();
foreach (explode(",",(string)($_GET['rest_id'] ?? '')) as $tmp) {
	if ((int)$tmp) $rest_id[count($rest_id)+1]=(int)$tmp;
}
$rest=count($rest_id);
$skriv=(if_isset($_GET['skriv']));

if (!$ordre_id) $ordre_id=1;

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
		$tmp=implode(",",$rest_id);
		# SST-876: this request invoiced up to ten orders and each one committed as it went, so
		# the webshops have been told by now. Reported before the refresh, because the refresh
		# hands the page over to the next ten orders and anything printed after it is lost.
		shopSyncAlertFailedItems();
		print "<meta http-equiv=\"refresh\" content=\"0;URL=massefakt.php?valg=".urlencode($valg)."&ordre_id=$ordre_id&ordre_antal=$ordre_antal&rest=$rest&rest_id=$tmp&skriv=$skriv\">";
		exit;
	}
}
# SST-876: the end of the run, for the orders invoiced since the last refresh.
shopSyncAlertFailedItems();
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
				$beholdning[$y]=(int)$r2['beholdning'];
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
					$antal[$x]=(int)$r['antal'];
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
					if ($lagervare[$x] && $antal[$x]>0 && $beholdning[$x]<$antal[$x]) { #saa skal der delfaktureres
						$delfakturer=1;
						# 20261119 the order keeps the stock on hand but never less than none: with negative stock the whole line goes to the remainder (it kept a negative quantity).
						# A negative line adds to the stock and is not split.
						$ny_antal[$x]=$antal[$x]-max((float)$beholdning[$x],0);
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
	# 20261119 the header fields Del ordre (debitor/ordre.php) also copies to its remainder: reverse charge, department, PBS, e-mail, phone, the extra fields and performed_by.
	# Without omvbet the order page drew no O/B box for the remainder, so its save and its credit note cleared the lines' reverse charge and charged VAT.
	# One array of quoted values: the line loop below uses $omvbet[$x] and $momsfri[$x], so a header field must not take such a name.
	$hovedkopi=array();
	foreach (array('omvbet','pbs','email','mail_fakt','phone','felt_1','felt_2','felt_3','felt_4','felt_5','performed_by') as $kolonne) {
		$hovedkopi[$kolonne]="'".db_escape_string((string)($r[$kolonne] ?? ''))."'";
	}
	$hovedkopi['afd']=is_numeric($r['afd'])?(int)$r['afd']:'NULL'; # an integer column: no department stays NULL, so levering() still looks it up from the order's ref

	$tidspkt=date("U");
	$qtxt="insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, kundeordnr, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, momssats, tidspkt, ref, status, lev_navn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt, valuta, projekt, sprog, ";
	$qtxt.=implode(", ",array_keys($hovedkopi)).") ";
	$qtxt.="values ('$ordrenr','$konto_id','$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$kundeordnr','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$notes','$art','$ordredate','$momssats','$tidspkt','$ref','$status','$lev_navn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt','$valuta','$projekt','$sprog',";
	$qtxt.=implode(",",$hovedkopi).")";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
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
		$posnr[$x]=(int)$r['posnr'];
		$varenr[$x]=db_escape_string($r['varenr']);
		$vare_id[$x]=(int)$r['vare_id'];
		$beskrivelse[$x]=db_escape_string($r['beskrivelse']);
		$enhed[$x]=$r['enhed'];
		$antal[$x]=(float)$r['antal']; # 20261119 a quantity of 2,5 (hours, kg) stays 2,5 (it was cut to 2)
		$pris[$x]=(float)$r['pris'];
		$rabat[$x]=(float)$r['rabat'];
		$serienr[$x]=$r['serienr'];
		$momsfri[$x]=$r['momsfri'];
		$samlevare[$x]=$r['samlevare'];
		$kostpris[$x]=(float)$r['kostpris'];
		$rabatart[$x]=db_escape_string($r['rabatart']);
		$procent[$x]=is_numeric($r['procent'])?(float)$r['procent']:100; # NULL is invoiced at 100 %
		$omvbet[$x]=db_escape_string($r['omvbet']);
		# 20261119 the line's VAT rate as momsupdat() takes it: a lower rate of its own, else the order's rate
		$linjemomssats=(float)$r['momssats'];
		if (($linjemomssats<=0 || $linjemomssats>=(float)$momssats) && $vare_id[$x]) $linjemomssats=(float)$momssats;
			$antal[$x]=$antal[$x]-$ny_antal[$x];
			# 20261119 the unit price after the discount (kr or %) and procent, and VAT except on a reverse-charge line, as momsupdat() counts them
			if ($rabatart[$x]=='amount') $nettopris=$pris[$x]-$rabat[$x];
			else $nettopris=$pris[$x]-$pris[$x]*$rabat[$x]/100;
			$nettopris*=$procent[$x]/100;
			$linjesum=afrund($antal[$x]*$nettopris,3);
			$sum=$sum+$linjesum;
			if (!$momsfri[$x] && !$omvbet[$x]) $moms=$moms+afrund($linjesum*$linjemomssats/100,3);
		if ($vare_id[$x]){
			db_modify("update ordrelinjer set antal=$antal[$x] where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__); # id fra 1. s�gning.
			if ($ny_antal[$x]) {
				$ny_linjesum=afrund($ny_antal[$x]*$nettopris,3);
				$ny_sum=$ny_sum+$ny_linjesum;
				if (!$momsfri[$x] && !$omvbet[$x]) $ny_moms=$ny_moms+afrund($ny_linjesum*$linjemomssats/100,3);
				db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, rabatart, procent, serienr, momsfri, omvbet, kostpris) values ('$ny_id','$posnr[$x]','$varenr[$x]','$vare_id[$x]','$beskrivelse[$x]','$enhed[$x]', $ny_antal[$x], '$pris[$x]','$rabat[$x]','$rabatart[$x]','$procent[$x]','$serienr[$x]','$momsfri[$x]','$omvbet[$x]','$kostpris[$x]')",__FILE__ . " linje " . __LINE__);
			}
		}	else {
			db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$ny_id','$posnr[$x]','$beskrivelse[$x]')",__FILE__ . " linje " . __LINE__);
		}
	}
	# 20261119 the totals in whole øre, as the order page writes them: momsupdat() keeps them on an order with mixed VAT, and they are the open item
	$sum=afrund($sum,2);
	$moms=afrund($moms,2);
	$ny_sum=afrund($ny_sum,2);
	$ny_moms=afrund($ny_moms,2);
	db_modify("update ordrer set tidspkt = '',hvem = '', moms='$moms', sum='$sum' where id ='$ordre_id'",__FILE__ . " linje " . __LINE__);
	db_modify("update ordrer set tidspkt = '',hvem = '', moms='$ny_moms', sum='$ny_sum' where id ='$ny_id'",__FILE__ . " linje " . __LINE__);

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
		$momssats=(float)$r['momssats'];
		$projekt[0]=(int)$r['projekt'];
		$q=db_select("select * from ordrelinjer where ordre_id='$ordre_id'");
		while ($r=db_fetch_array($q)) {
			$x++;
			$linje_id[$x]=$r['id'];
			if ($r['bilag']>=0) {
				if ($projekt[0]) $projekt[$x]=$projekt[0];
				else $projekt[$x]=(int)$r['projekt'];
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