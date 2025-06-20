<?php #topkode_start
@session_start();
$s_id=session_id();

// ---------debitor/genfakturer.php-----patch 2.1.9--2010.05.04------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

$id=$_GET['id'];
$css="../css/standard.css";

if ($id==-1){	# Saa er der flere fakturaer
	$ordre_antal = $_GET['ordre_antal'];
	$ordreliste = $_GET['genfakt'];
	$ordre_id = explode(",",$ordreliste);
} else {
	$ordre_id[0]=$id;
	$ordre_antal=1;	
}

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/dkdecimal.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");

#include("../includes/db_query.php");
#include("levering.php");
#include("bogfor.php");
include("pbsfakt.php");
		
if ($_POST) {
	$ok=findtekst(80,$sprog_id);

	$afbryd=findtekst(81,$sprog_id);
	if ($afbryd==if_isset($_POST[$afbryd])) {
 		print "<BODY onLoad=\"javascript:alert('Genfakturering afbrudt')\">";
		print "<meta http-equiv=\"refresh\" content=\"1;URL=../includes/luk.php\">";
		exit;
	}	elseif ($ok==if_isset($_POST[$ok])) {	
		$org_nr=if_isset($_POST['org_nr']);
		$komplet=if_isset($_POST['komplet']);
		$fakt_dato=if_isset($_POST['fakt_dato']);
		$opdat_pris=if_isset($_POST['opdat_pris']);
		$slet_gfdato=if_isset($_POST['slet_gfdato']);
		
		for ($q=0; $q<$ordre_antal; $q++) {
			list($id,$pbs)=split(",",genfakt($ordre_id[$q],$org_nr,$fakt_dato,$opdat_pris,$slet_gfdato));
			if ($komplet) {
				levering($id,'on','on');
				bogfor($id,'on','on');	
			}
			if ($komplet && $pbs) {
#				echo "A PBS pbsfakt($id)";
				pbsfakt($id);
#				echo "B PBS pbsfakt($id)";
			} else {
				if ($q) $udskriv.=",$id";
				else $udskriv ="$id";
			}
		} 	
	}
	if ($udskriv && $komplet) print "<BODY onLoad=\"JavaScript:window.open('formularprint.php?id=-1&ordre_antal=$q&skriv=$udskriv&formular=4' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
	else print "<BODY onLoad=\"javascript:alert('Genfakturering udf&oslash;rt')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../includes/luk.php\">";	
} else {
	print "<form name=genfakturer action=genfakturer.php?id=$id&ordre_antal=$ordre_antal&genfakt=$ordreliste method=post>";
	print "<table border=0><tbody>";
	print "<tr><td>".findtekst(82,$sprog_id)."</td><td align=center>".findtekst(83,$sprog_id)."</td><td align=center>".findtekst(84,$sprog_id)."</td></tr>";
	print "<tr><td title='".findtekst(68,$sprog_id)."'>".findtekst(69,$sprog_id)."</td><td align=center><input type=radio name=org_nr value=1 title='".findtekst(70,$sprog_id)."' checked></td><td align=center><input type=radio name=org_nr value=0 title='".findtekst(71,$sprog_id)."'></td></tr>";
	print "<tr><td title='".findtekst(72,$sprog_id)."'>".findtekst(73,$sprog_id)."</td><td align=center><input type=radio name=komplet value=1 title='".findtekst(74,$sprog_id)."' checked></td><td align=center><input type=radio name=komplet value=0 title='".findtekst(75,$sprog_id)."'></td></tr>";
	print "<tr><td title='".findtekst(76,$sprog_id)."'>".findtekst(77,$sprog_id)."</td><td align=center><input type=radio name=fakt_dato value=1 title='".findtekst(78,$sprog_id)."' checked></td><td align=center	><input type=radio name=fakt_dato value=0 title='".findtekst(79,$sprog_id)."'></td></tr>";
	print "<tr><td title='".findtekst(85,$sprog_id)."'>".findtekst(86,$sprog_id)."</td><td align=center><input type=radio name=opdat_pris value=1 title='".findtekst(87,$sprog_id)."' checked></td><td align=center	><input type=radio name=opdat_pris value=0 title='".findtekst(88,$sprog_id)."'></td></tr>";
	print "<tr><td title='".findtekst(220,$sprog_id)."'>".findtekst(221,$sprog_id)."</td><td align=center><input type=radio name=slet_gfdato value=1 title='".findtekst(222,$sprog_id)."' checked></td><td align=center	><input type=radio name=slet_gfdato value=0 title='".findtekst(223,$sprog_id)."'></td></tr>";
	print "<tr><td colspan=3 align=center><input type=submit name=Ok value=".findtekst(80,$sprog_id).">&nbsp;<input type=submit name=Afbryd value=".findtekst(81,$sprog_id)."></td></tr>";
	print "</tbody></table>";
	print "</form>";
}
	
function genfakt($id,$org_nr,$fakt_dato,$opdat_pris,$slet_gfdato) {
	transaktion('begin');
	if ($r=db_fetch_array(db_select("select * from ordrer where id = $id",__FILE__ . " linje " . __LINE__))){
		$pbs=$r['pbs'];
		$firmanavn=addslashes($r['firmanavn']);
		$addr1=addslashes($r['addr1']);
		$addr2=addslashes($r['addr2']);
		$bynavn=addslashes($r['bynavn']);
		$land=addslashes($r['land']);
		$cvrnr=addslashes($r['cvrnr']);
		$ean=addslashes($r['ean']);
		$sprog=addslashes($r['sprog']);
		$valuta=addslashes($r['valuta']);
		$projekt=addslashes($r['projekt']);
		$institution=addslashes($r['institution']);
		$notes=addslashes($r['notes']);
		$ref=addslashes($r['ref']);
		$kontakt=addslashes($r['kontakt']);
		$kundeordnr=addslashes($r['kundeordnr']);
		$lev_navn=addslashes($r['lev_navn']);
		$lev_addr1=addslashes($r['lev_addr1']);
		$lev_addr2=addslashes($r['lev_addr2']);
		$lev_bynavn=addslashes($r['lev_bynavn']);
		$email=addslashes($r['email']);
		if ($r['nextfakt']) $tmp=$r['nextfakt'];
		else $tmp=date("Y-m-d");			
		$nextfakt=find_nextfakt($r['fakturadate'],$tmp);
		if ($fakt_dato) $fakturadate=$r['nextfakt'];
		else $fakturadate=date("Y-m-d");
		if ($org_nr) $ordrenr=$r['ordrenr'];
		else {
			$r2=db_fetch_array(db_select("select MAX(ordrenr) as ordrenr from ordrer where art='DO' or art='DK'",__FILE__ . " linje " . __LINE__));
			$ordrenr=$r2['ordrenr']+1;
		}
		db_modify("insert into ordrer (ordrenr, konto_id, kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,betalingsdage,betalingsbet,cvrnr,ean,institution,notes,art,ordredate,momssats,moms,ref,valuta,sprog,kontakt,kundeordnr,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,levdate,fakturadate,nextfakt,sum,status,projekt,email,mail_fakt,pbs) values 
				('$ordrenr','$r[konto_id]','$r[kontonr]','$firmanavn','$addr1','$addr2','$r[postnr]','$bynavn','$land','$r[betalingsdage]','$r[betalingsbet]','$cvrnr','$ean','$institution','$notes','$r[art]','$r[ordredate]','$r[momssats]','$r[moms]','$ref','$valuta','$sprog','$kontakt','$kundeordnr','$lev_navn','$lev_addr1','$lev_addr2','$r[lev_postnr]','$lev_bynavn','$fakturadate','$fakturadate','$nextfakt','$r[sum]','2','$projekt','$email','$r[mail_fakt]','$pbs')",__FILE__ . " linje " . __LINE__);
		$r2=db_fetch_array(db_select("select id from ordrer where ordrenr='$ordrenr' and nextfakt='$nextfakt' and (art='DO' or art='DK') order by id desc",__FILE__ . " linje " . __LINE__));
		$ny_id=$r2['id'];
		$sum=0;
		$q=db_select("select * from ordrelinjer where ordre_id = $id order by posnr",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$projekt=$r['projekt']*1;
			if ($r['vare_id']){
				if (!$opdat_pris) {
					$pris=$r['pris']*1;
					$kostpris=$r['kostpris']*1;
				} else {
					$r2=db_fetch_array(db_select("select salgspris,kostpris from varer where id='$r[vare_id]'",__FILE__ . " linje " . __LINE__));
					$pris=$r2['salgspris']*1;
					$kostpris=$r2['kostpris']*1;
					$sum=$sum+$r['antal']*$pris-($r['antal']*$pris*$r['rabat']/100);
				}
				db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, lev_varenr, momsfri, samlevare, kostpris, leveres, projekt) values ('$ny_id','$r[posnr]','$r[varenr]','$r[vare_id]','$r[beskrivelse]','$r[enhed]','$r[antal]','$pris','$r[rabat]','$r[lev_varenr]','$r[momsfri]','$r[samlevare]','$kostpris','$r[antal]','$projekt')",__FILE__ . " linje " . __LINE__);
			}	else {
				db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$ny_id','$r[posnr]','$r[beskrivelse]')",__FILE__ . " linje " . __LINE__);
			}
		}	
		if ($opdat_pris) db_modify("update ordrer set sum=$sum where id='$ny_id'",__FILE__ . " linje " . __LINE__);	
#echo "SLET : $slet_gfdato<br>";		
		if ($slet_gfdato) db_modify("update ordrer set nextfakt=NULL where id='$id'",__FILE__ . " linje " . __LINE__);	
	}
	transaktion('commit');
	$tmp=$ny_id.",".$pbs;
	return($tmp);
}	
########################################################################################


function find_nextfakt($fakturadate, $nextfakt) 
{
// Denne funktion finder diff mellem fakturadate & nextfakt, tillægger diff til nextfakt og returnerer denne vaerdi. Hvis baade 
// fakturadate og netffaxt er sidste dag i de respektive maaneder vaelges også sidste dag i maaned i returvaerdien.

list($faktaar, $faktmd, $faktdag) = split("-", $fakturadate);
list($nextfaktaar, $nextfaktmd, $nextfaktdag) = split("-", $nextfakt);
	
if (!checkdate($faktmd,$faktdag,$faktaar)) {
	echo "Fakturadato er ikke en gyldig dato<br>";
	exit;
}
if (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) {
	echo "next Fakturadato er ikke en gyldig dato<br>";
	exit;
}
$faktultimo=0;
$nextfaktultimo=0;
$tmp=$faktdag+1;
if (!checkdate($faktmd,$tmp,$faktaar)) $faktultimo=1; # hvis dagen efter fakturadag ikke findes fakureres ultimo"
$tmp=$nextfaktdag+1;
if (!checkdate($nextfaktmd,$tmp,$nextfaktaar)) $nextfaktultimo=1;
$faktmd_len=31;
while (!checkdate($faktmd,$faktmd_len,$faktaar)) $faktmd_len--; #finder antal dage i fakturamaaneden
$dagantal=$nextfaktdag-$faktdag;
$md_antal=$nextfaktmd-$faktmd;
$aar_antal=$nextfaktaar-$faktaar;
if ($dagantal<0) {
	$dagantal=$dagantal+$faktmd_len;
	$md_antal--;
}
while ($md_antal<0) {
	$aar_antal--;
	$md_antal=$md_antal+12;
}
$nextfaktaar=$nextfaktaar+$aar_antal;
$nextfaktmd=$nextfaktmd+$md_antal;
if ($nextfaktmd > 12) {
	$nextfaktaar++;
	$nextfaktmd=$nextfaktmd-12;
}
if ($faktultimo && $nextfaktultimo) {# fast faktura sidste dag i md.
	$nextfaktdag=31;
	if ($dagantal>27) $nextfaktmd++;
	while (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) $nextfaktdag--;
} else {
	$nextfaktdag=$nextfaktdag+$dagantal;
if ($nextfaktdag>$faktmd_len) {
		while (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) {
			$nextfaktmd++;
			if ($nextfaktmd > 12) {
				$nextfaktaar++;
				$nextfaktmd=1;
			} 
			$nextfaktdag=$nextfaktdag-$faktmd_len;
		} 
	} else while (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) $nextfaktdag--;
}
$nextfakt=$nextfaktaar."-".$nextfaktmd."-".$nextfaktdag;
return($nextfakt);
}# endfunc find_nextfakt
?>
