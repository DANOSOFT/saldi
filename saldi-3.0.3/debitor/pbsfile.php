<?php
@session_start();
$s_id=session_id();
// ------------debitor/pbsfile.php------- patch 3.0.2---2010-05-31------
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

$modulnr=5;
$title="PBS File";
$css="../css/standard.css";
$header="nix";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");

$afslut=if_isset($_GET['afslut']);
$id=if_isset($_GET['id']);
$dkdd=date("dmy");
$x=0;
$lnr=0;	
$delsystem="BS1"; # Ved test skal delsystem vaere KR9

if ($afslut) {
	$r=db_fetch_array(db_select("select max(id) as id from pbs_liste",__FILE__ . " linje " . __LINE__));
	if ($r['id'] == $id) $afslut=0;  # saa er den allerede afsluttet.
}	

PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
<html>\n
<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\">\n";
PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\" />";
PRINT "</head>";


print "<table width=100%><tbody>";
######## TOPLINJE #########	
if ($popup) $returside="../includes/luk.php";
else $returside="ordreliste.php?valg=pbs"; 

	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=$returside accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund>PBS</td>";
	print "<td width=\"10%\" $top_bund><br></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
######## TOPLINJE SLUT #########

if (!$id) {
	if ($r=db_fetch_array(db_select("select id from pbs_liste where afsendt = ''",__FILE__ . " linje " . __LINE__))) {
		$id=$r['id'];
	}	else {
		$r=db_fetch_array(db_select("select max(id) as id from pbs_liste",__FILE__ . " linje " . __LINE__));
		$id=$r['id']+1;
	}
}
if ($r=db_fetch_array(db_select("select afsendt from pbs_liste where id = '$id'",__FILE__ . " linje " . __LINE__))) {
	$afsendt=$r['afsendt'];
}
if (!$afsendt) {

/*
	if ($r=db_fetch_array(db_select("select * from pbs_liste where id = '$id'",__FILE__ . " linje " . __LINE__))) {
		$listedate=$r['liste_date'];	
		$afsendt=$r['afsendt'];	
	} else {
		$tmp=date('Y-m-d');
		db_modify("insert into pbs_liste (liste_date) values ('$tmp')",__FILE__ . " linje " . __LINE__);
	}
*/	
	$r=db_fetch_array(db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$cvrnr[0]=$r['cvrnr'];	
	$bank_reg[0]=$r['bank_reg'];	
	$bank_konto[0]=$r['bank_konto'];	
	$pbs_nr[0]=$r['pbs_nr'];	
	while(strlen($pbs_nr[0])<8) $pbs_nr[0]="0".$pbs_nr[0];
########## sektion start  - betalingsaftaler #############
	
	$x=0;
	$q=db_select("select * from adresser where pbs_nr='' and pbs = 'on' order by id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		if ($r['kontonr'] && $r['bank_reg'] && $r['cvrnr'] && $r['bank_reg'] && $r['bank_konto']) {
			$x++;
			$ny_pbs_aftale[$x]=$r['id'];
			$kontonr[$x]=$r['kontonr'];
			$cvrnr[$x]=$r['cvrnr'];
			$bank_reg[$x]=$r['bank_reg'];
			$bank_konto[$x]=$r['bank_konto'];
		}
	}
	$antal_nye=$x;

	if ($afslut) {
		for ($x=1;$x<=$antal_nye;$x++) {
			db_modify("update adresser set pbs_nr='000000000' where id = '$ny_pbs_aftale[$x]'",__FILE__ . " linje " . __LINE__); 
			db_modify("insert into pbs_kunder (konto_id,kontonr,pbs_nr) values ('$ny_pbs_aftale[$x]','$kontonr[$x]','')",__FILE__ . " linje " . __LINE__); 
		}
	}
	
	$leverance_id=$id;
	while(strlen($leverance_id)<10) $leverance_id="0".$leverance_id;
	$lnr=0;

	$lnr++;
	$linje[$lnr]="BS002".$cvrnr[0]."BS10605".$leverance_id.filler(19," ").$dkdd."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

	
	if ($antal_nye>0) opret_nye($antal_nye,$leverance_id,$dkdd,$cvrnr,$bank_reg,$bank_konto,$pbs_nr,$ny_pbs_aftale,$kontonr);

	$x=0;
	$q=db_select("select adresser.kontonr as ny_kontonr, adresser.pbs_nr as pbs_nr, pbs_kunder.kontonr as kontonr from adresser,pbs_kunder where adresser.id=pbs_kunder.konto_id and adresser.kontonr!=pbs_kunder.kontonr order by adresser.id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$x++;
		$ny_konto_nr[$x]=$r['ny_kontonr'];
		$kontonr[$x]=$r['kontonr'];
		$pbs_nr[$x]=$r['pbs_nr'];
	}
	$antal_rettes=$x;
	
	if ($antal_rettes>0) ret_exist($antal_rettes,$leverance_id,$dkdd,$cvrnr,$pbs_nr,$ny_kontonr,$kontonr);

	
	$x=0;
	$aftaler=array();
	$q=db_select("select konto_id from pbs_kunder order by konto_id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$x++;
		$aftaler[$x]=$r['konto_id'];
	}
	$x=0;
	$q=db_select("select * from adresser where pbs !='on' order by id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		if (in_array($r['id'],$aftaler)) {
			$x++;
			$ophort_aftale[$x]=$r['pbs_nr'];
			$kontonr[$x]=$r['kontonr'];
		}
	}
	$antal_stoppes=$x;
	$antal_stoppes=0;
	
	if ($antal_stoppes>0) stop_aftale($antal_stoppes,$leverance_id,$dkdd,$cvrnr,$pbs_nr,$kontonr);
	
	$antal=$antal_nye+$antal_rettes+$antal_stoppes;
	while(strlen($antal)<11) $antal="0".$antal;
	$sektioner=0;
	if ($antal_nye>0)$sektioner++;	
	if ($antal_rettes>0)$sektioner++;	
	if ($antal_stoppes>0)$sektioner++;	
	while(strlen($sektioner)<11) $sektioner="0".$sektioner;
	$lnr++;
	$linje[$lnr]="BS992".$cvrnr[0]."BS10605".$sektioner.$antal.filler(15,"0").filler(11,"0").filler(15,"0").filler(11,"0").filler(34,"0")."\n";
	if ($afslut && $antal>0) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
	elseif ($afslut) db_modify("delete from pbs_linjer where liste_id = '$id'",__FILE__ . " linje " . __LINE__);
	if ($antal==0) $lnr=0;
	

########## sektion slut  - betalingsaftaler #############
	$x=0;
	if (!$antal_nye && !$antal_rettes && !$antal_stoppes) {
		$q=db_select("select pbs_ordrer.ordre_id,ordrer.konto_id from pbs_ordrer,ordrer where pbs_ordrer.liste_id = $id and ordrer.id=pbs_ordrer.ordre_id order by pbs_ordrer.id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)){
			$x++;
			$ordre_id[$x]=$r['ordre_id'];
			$konto_id[$x]=$r['konto_id'];
			$r2=db_fetch_array(db_select("select * from adresser where id='$konto_id[$x]'",__FILE__ . " linje " . __LINE__));
			$pbs_nr[$x]=$r2['pbs_nr'];
			$cvrnr[$x]=$r2['cvrnr'];
			$bank_reg[$x]=$r2['bank_reg'];
			$bank_konto[$x]=$r2['bank_konto'];
		}
	} elseif ($afslut) {
		$tmp=$id+1;
		echo "update pbs_ordrer set liste_id='$tmp' where liste_id='$id'<br>";
		db_modify("update pbs_ordrer set liste_id='$tmp' where liste_id='$id'");
}
	$antal_ordrer=$x;

	if ($antal_ordrer>0) inset_ordrer($antal_ordrer,$leverance_id,$dkdd,$ordre_id,$cvrnr,$bank_reg,$bank_konto,$pbs_nr,$ny_pbs_aftale,$kontonr);

	if ($afslut) {	
		$filnavn="../temp/".$db."/PBS_Leverance".$id.".txt";
		$fp=fopen("$filnavn","w");
		$q=db_select("select linje from pbs_linjer where liste_id = $id order by id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)){
			if ($charset=="UTF-8") $linje=utf8_decode($r['linje']);
			else $linje=$r['linje'];
#			$linje=$r['linje'];
			fwrite($fp,$linje);
		}
		fclose($fp);
		if ($r=db_fetch_array(db_select("select * from pbs_liste where id = '$id'",__FILE__ . " linje " . __LINE__))) {
			db_modify("update pbs_liste set afsendt='on' where id='$id'",__FILE__ . " linje " . __LINE__);
		} else {
			$listedate=date('Y-m-d');
			db_modify("insert into pbs_liste (liste_date, afsendt) values ('$listedate', 'on')");
		}
	}
}	else {
	$filnavn="../temp/".$db."/PBS_Leverance".$id.".txt";
	$fp=fopen("$filnavn","w");
	$q=db_select("select linje from pbs_linjer where liste_id = $id order by id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)){
			if ($charset=="UTF-8") $linje=utf8_decode($r['linje']);
			else $linje=$r['linje'];
#			$linje=$r['linje'];
			fwrite($fp,$linje);
		}
		fclose($fp);
}
for ($x=1;$x<=$lnr;$x++)	print "<tr><td>".str_replace(" ","&nbsp;",$linje[$x])."</td></tr>";
if ($afslut || $afsendt) print "<tr><td title=\"&Aring;bner PBS filen. H&oslash;jreklik for at gemme\" align=center> Klik her: <a href=\"$filnavn\" target=\"blank\">&Aring;ben PBSfil</a></td></tr>";
	
function opret_nye ($antal_nye,$leverance_id,$dkdd,$cvrnr,$bank_reg,$bank_konto,$pbs_nr,$ny_pbs_aftale,$kontonr) {
	global $id;
	global $lnr;
	global $afslut;
	global $linje;
	
	$lnr++;
	$linje[$lnr]="BS012".$pbs_nr[0]."0120".filler(3,"0").filler(15,"0").filler(9," ").filler(6,"0")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

	for ($x=1;$x<=$antal_nye;$x++) {
		while(strlen($kontonr[$x])<15) $kontonr[$x]="0".$kontonr[$x];
		while(strlen($cvrnr[$x])<10) $cvrnr[$x]="0".$cvrnr[$x];
		while(strlen($bank_konto[$x])<10) $bank_konto[$x]="0".$bank_konto[$x];
		$lnr++;
		$linje[$lnr]="BS042".$pbs_nr[0]."0200".filler(3,"0")."00001".$kontonr[$x].filler(9,"0").filler(6,"0").filler(6,"0").$cvrnr[$x].filler(10," ").$bank_reg[$x].filler(4," ").$bank_konto[$x].filler(10," ")."0".filler(4,"0")."\n";
		if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
	
	}
	$lnr++;
	while(strlen($antal_nye)<11) $antal_nye="0".$antal_nye;
	$linje[$lnr]="BS092".$pbs_nr[0]."0120".filler(9," ").$antal_nye.filler(15,"0").filler(11,"0").filler(15," ").filler(11,"0").filler(39,"0")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

}

function ret_exist ($antal_rettes,$leverance_id,$dkdd,$cvrnr,$pbs_nr,$ny_kontonr,$kontonr) {
	global $id;
	global $lnr;
	global $afslut;
	global $linje;
	
	$lnr++;
	$linje[$lnr]="BS012".$pbs_nr[0]."0125".filler(3,"0").filler(15,"0").filler(9," ").filler(6,"0")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

	for ($x=1;$x<=$antal_rettes;$x++) {
		while(strlen($kontonr[$x])<15) $kontonr[$x]="0".$kontonr[$x];
		while(strlen($ny_kontonr[$x])<15) $ny_kontonr[$x]="0".$ny_kontonr[$x];
		while(strlen($pbs_nr[$x])<9) $pbs_nr[$x]="0".$pbs_nr[$x];
		$lnr++;
		$linje[$lnr]="BS042".$pbs_nr[0]."0272".filler(3,"0")."00001".$kontonr[$x].$pbs_nr[$x].$dkdd.filler(6,"0").$ny_kontonr[$x].filler(53," ")."\n";
		if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
	
	}
	while(strlen($antal_rettes)<11) $antal_rettes="0".$antal_rettes;
	$lnr++;
	$linje[$lnr]="BS092".$pbs_nr[0]."0125".filler(9," ").$antal_rettes.filler(15,"0").filler(11,"0").filler(15," ").filler(11,"0").filler(39,"0")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

}

function stop_aftale ($antal_stoppes,$leverance_id,$dkdd,$cvrnr,$pbs_nr,$kontonr) {
	global $id;
	global $lnr;
	global $afslut;
	global $linje;
	
	$lnr++;
	$linje[$lnr]="BS012".$pbs_nr[0]."0105".filler(3,"0").filler(15,"0").filler(9," ").filler(6,"0")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

	for ($x=1;$x<=$antal_stoppes;$x++) {
		while(strlen($kontonr[$x])<15) $kontonr[$x]="0".$kontonr[$x];
		while(strlen($pbs_nr[$x])<9) $pbs_nr[$x]="0".$pbs_nr[$x];
		$lnr++;
		$linje[$lnr]="BS042".$pbs_nr[0]."0253".filler(3,"0")."00001".$kontonr[$x].$pbs_nr[$x].$dkdd."\n";
		if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
	
	}
	$lnr++;
	while(strlen($antal_rettes)<11) $antal_rettes="0".$antal_rettes;
	$linje[$lnr]="BS092".$pbs_nr[0]."0105".filler(9," ").$antal_stoppes.filler(15,"0").filler(11,"0").filler(15," ").filler(11,"0").filler(39,"0")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

}

function inset_ordrer($antal_ordrer,$leverance_id,$dkdd,$ordre_id,$cvrnr,$bank_reg,$bank_konto,$pbs_nr,$ny_pbs_aftale,$kontonr) {
	global $id;
	global $lnr;
	global $afslut;
	global $linje;
	global $delsystem;
	global $charset;
	
	include("../includes/forfaldsdag.php");
	$r042sum=0;
	$r022lin=0;
	$r052lin=0;
	$lnr++;
#	$linje[$lnr]="BS002".$cvrnr[0]."BS10601".$leverance_id.filler(19," ").$dkdd."\n";
	$linje[$lnr]="BS002".$cvrnr[0].$delsystem."0601".$leverance_id.filler(19," ").$dkdd."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

	$lnr++;
	$linje[$lnr]="BS012".$pbs_nr[0]."0112".filler(5," ")."00001".filler(15,"0").filler(4," ")."00000000".$bank_reg[0].$bank_konto[0]."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

	for ($x=1;$x<=$antal_ordrer;$x++) {
		$r=db_fetch_array(db_select("select * from ordrer where id='$ordre_id[$x]'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['kontonr'];
		$firmanavn=$r['firmanavn'];
		$adresse=$r['addr1'];
		if ($r['addr2']) $adresse=$adresse.", ".$r['addr2'];
		$postnr=$r['postnr'];
		$ean=$r['ean'];
		$institution=$r['institution'];
		$sum=$r['sum'];
		$moms=$r['moms'];
		$belob=round(($r['sum']+$r['moms'])*100,0);
		$r042sum=$r042sum+$belob;
		$fakturadate=$r['fakturadate'];
		$betalingsbet=$r['betalingsbet'];
		$betalingsdage=$r['betalingsdage'];
		$pbs_art=$r['pbs'];
		if ($charset=="UTF-8") {
			$firmanavn=utf8_decode($firmanavn);
			$adresse=utf8_decode($adresse);
			$institution=utf8_decode($institution);
		}
		list($dd,$mm,$yy)=split("-",forfaldsdag($fakturadate, $betalingsbet, $betalingsdage));	
		$forfaldsdag=$dd.$mm.$yy;
		$r022lin++;
		while(strlen($kontonr)<15) $kontonr="0".$kontonr;
		while(strlen($pbs_nr[$x])<9) $pbs_nr[$x]="0".$pbs_nr[$x];
#		if ($pbs_art=='FI') {
			$lnr++;
			$linje[$lnr]="BS022".$pbs_nr[0]."0240"."00001"."00001".$kontonr.$pbs_nr[$x].$firmanavn."\n";
			if ($afslut) {
				if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
				db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
			}
			if ($ean) {
				$lnr++;
				$r022lin++;
				$linje[$lnr]="BS022".$pbs_nr[0]."0240"."00002"."00001".$kontonr.$pbs_nr[$x].$ean."\n";
				if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
				$linjenr="00003";
			} else $linjenr="00002";
			$lnr++;
			$r022lin++;
			$linje[$lnr]="BS022".$pbs_nr[0]."0240".$linjenr."00001".$kontonr.$pbs_nr[$x].$adresse."\n";
			if ($afslut) {
				if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
				db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
			}
			$r022lin++;
			while(strlen($postnr)<4) $postnr="0".$postnr;
			$lnr++;
			$linje[$lnr]="BS022".$pbs_nr[0]."0240"."00009"."00001".$kontonr.$pbs_nr[$x].filler(15," ").$postnr."\n";
			if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
#		}	
		if ($belob>0) $felt10="1";	
		elseif ($belob<0) {
			$felt10="2";
			$belob=$belob*-1;
		}	else $felt10="0";
		while(strlen($belob)<13) $belob="0".$belob;
		$lnr++;
		$linje[$lnr]="BS042".$pbs_nr[0]."0280"."00000"."00001".$kontonr.$pbs_nr[$x].$forfaldsdag.$felt10.$belob.filler(30," ")."00"."\n";
		if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
	
		$r052lin++;
		$recordnr="00001";
		$beskrivelse="Beskrivelse";
		$antal="Antal";
		$pris="Pris";
		$belob="Beløb";
		if ($charset=="UTF-8") {
			$belob=utf8_decode($belob);
			$beskrivelse=utf8_decode($beskrivelse);
		}
		while(strlen($recordnr)<5) $recordnr="0".$recordnr;
		while(strlen($beskrivelse)<35) $beskrivelse=$beskrivelse." ";
		while(strlen($antal)<5) $antal=" ".$antal;
		while(strlen($pris)<10) $pris=" ".$pris;
		while(strlen($belob)<10) $belob=" ".$belob;
		$lnr++;
		$linje[$lnr]="BS052".$pbs_nr[0]."0241".$recordnr."00001".$kontonr.$pbs_nr[$x]." ".$beskrivelse.$antal.$pris.$belob."\n";
		if ($afslut) {
			if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
			db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
		}
		$y=0;
		$q=db_select("select * from ordrelinjer where ordre_id='$ordre_id[$x]' order by posnr",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$y++;
			$r052lin++;
			$beskrivelse=$r['beskrivelse'];
			$antal=$r['antal']*1;
			$pris=dkdecimal($r['pris']);
			$belob=dkdecimal($r['pris']*$r['antal']);
			$recordnr++;
			if ($charset=="UTF-8") {
				$beskrivelse=utf8_decode($beskrivelse);
			}
			while(strlen($recordnr)<5) $recordnr="0".$recordnr;
			if (strlen($beskrivelse)>35) $beskrivelse=substr($beskrivelse,0,35);
			while(strlen($beskrivelse)<35) $beskrivelse=$beskrivelse." ";
			while(strlen($antal)<5) $antal=" ".$antal;
			while(strlen($pris)<10) $pris=" ".$pris;
			while(strlen($belob)<10) $belob=" ".$belob;
			$lnr++;
			$linje[$lnr]="BS052".$pbs_nr[0]."0241".$recordnr."00001".$kontonr.$pbs_nr[$x]." ".$beskrivelse.$antal.$pris.$belob."\n";
			if ($afslut) {
				if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
				db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
			}
		}		
		if ($sum) {
			$r052lin++;
			$recordnr++;
			$beskrivelse="Netto Beløb";
			$dksum=dkdecimal($sum);
			if ($charset=="UTF-8") {
				$beskrivelse=utf8_decode($beskrivelse);
			}
			while(strlen($recordnr)<5) $recordnr="0".$recordnr;
			while(strlen($beskrivelse)<50) $beskrivelse=$beskrivelse." ";
			while(strlen($dksum)<10) $dksum=" ".$dksum;
			$lnr++;
			$linje[$lnr]="BS052".$pbs_nr[0]."0241".$recordnr."00001".$kontonr.$pbs_nr[$x]." ".$beskrivelse.$dksum."\n";
			if ($afslut) {
				if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
				db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
			}
		}
		if ($moms) {
			$r052lin++;
			$recordnr++;
			$beskrivelse="Moms";
			$dkmoms=dkdecimal($moms);
			if ($charset=="UTF-8") {
				$beskrivelse=utf8_decode($beskrivelse);
			}
			while(strlen($recordnr)<5) $recordnr="0".$recordnr;
			while(strlen($beskrivelse)<50) $beskrivelse=$beskrivelse." ";
			while(strlen($dkmoms)<10) $dkmoms=" ".$dkmoms;
			$lnr++;
			$linje[$lnr]="BS052".$pbs_nr[0]."0241".$recordnr."00001".$kontonr.$pbs_nr[$x]." ".$beskrivelse.$dkmoms."\n";
			if ($afslut) {
				if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
				db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
		}
		}
		if ($sum || $moms) {
			$r052lin++;
			$recordnr++;
			$beskrivelse="Total Beløb";
			$ialt=dkdecimal($sum+$moms);
			if ($charset=="UTF-8") {
				$beskrivelse=utf8_decode($beskrivelse);
			}
			while(strlen($recordnr)<5) $recordnr="0".$recordnr;
			while(strlen($beskrivelse)<50) $beskrivelse=$beskrivelse." ";
			while(strlen($ialt)<10) $ialt=" ".$ialt;
			$lnr++;
			$linje[$lnr]="BS052".$pbs_nr[0]."0241".$recordnr."00001".$kontonr.$pbs_nr[$x]." ".$beskrivelse.$ialt."\n";
			if ($afslut) {
				if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
				db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
			}
		}
	}
	while(strlen($antal_ordrer)<11) $antal_ordrer="0".$antal_ordrer;
	while(strlen($r042sum)<15) $r042sum="0".$r042sum;
	while(strlen($r022lin)<11) $r022lin="0".$r022lin;
	while(strlen($r052lin)<11) $r052lin="0".$r052lin;
	$lnr++;
	$linje[$lnr]="BS092".$pbs_nr[0]."0112".filler(5,"0")."00001".filler(4," ").$antal_ordrer.$r042sum.$r052lin.filler(15," ").$r022lin."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

	$lnr++;
#	$linje[$lnr]="BS992".$cvrnr[0]."BS10601"."00000000001".$antal_ordrer.$r042sum.$r052lin.filler(15,"0").$r022lin.filler(34,"0")."\n";
	$linje[$lnr]="BS992".$cvrnr[0].$delsystem."0601"."00000000001".$antal_ordrer.$r042sum.$r052lin.filler(15,"0").$r022lin.filler(34,"0")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);


}

print "</tbody></table>";
function filler($antal,$tegn){
	$filler=$tegn;
	while(strlen($filler)<$antal) $filler=$filler.$tegn;
	return $filler;
}


######################################################################################################################################
?>
