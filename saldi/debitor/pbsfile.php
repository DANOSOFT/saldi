<?php
@session_start();
$s_id=session_id();
// ------------debitor/pbsfile.php------- patch 3.4.1---2014-04-22------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------

// 23.08.2012 Tilretning til Leverandørservice 
// 25.08.2012 Tilfojet mulighed for at slette fakturaer fra listen.
// 24.10.2012 Tilføjet afmelding af leverandservice kunder. (funktion l_stop_aftale)
// 21.01.2014 Indsat else da der ikke må indsættes andre debittor oplysninger end aftalenummer i basisløsning  20140121
// 22.01.2014 Flyttet variabeltilpasning over if / else. Søg 20140122 
// 2014.02.07 PNS nr ændret fra '' til '000000000' 20140207
// 2014.04.22	Kunde slettes fra pbs_kunder ved afmelding #20140422
$modulnr=5;
$title="PBS File";
$css="../css/standard.css";
$header="nix";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");
include("../includes/var2str.php");

$afslut=if_isset($_GET['afslut']);
$id=if_isset($_GET['id']);
$slet_ordreid=if_isset($_GET['slet_ordreid']);
$usdd=date("ymd");
$dkdd=date("dmy");
$x=0;
$lnr=0;	
$delsystem="BS1"; # Ved test skal delsystem vaere KR9
#$debitorgruppe='00750';

#if ($afslut) { #remmet 2010-06-15 pga manglende mulighed for afslutning af id 4 regnskab 319. 
#	$r=db_fetch_array(db_select("select max(id) as id from pbs_liste where ",__FILE__ . " linje " . __LINE__));
#	if ($r['id'] == $id) $afslut=0;  # saa er den allerede afsluttet.
#}	
#echo "B $afslut<br>";

PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
<html>\n
<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\">\n";
PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\" />";
PRINT "</head>";

if ($slet_ordreid) db_modify("delete from pbs_ordrer where ordre_id='$slet_ordreid'",__FILE__ . " linje " . __LINE__);

print "<table width=\"100%\" border=\"0\"><tbody>";
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
	$lev_pbs=$r['pbs'];
	$debitorgruppe=$r['gruppe']*1;
	if (!$debitorgruppe) $debitorgruppe=1;
	if ($lev_pbs=='L') while(strlen($pbs_nr[0])<5) $pbs_nr[0]="0".$pbs_nr[0];
	else while(strlen($pbs_nr[0])<8) $pbs_nr[0]="0".$pbs_nr[0];
	while(strlen($debitorgruppe)<5) $debitorgruppe="0".$debitorgruppe;


	$lnr=0;
# Initialisering - kun leverandørservice
	if ($lev_pbs=='L') {
		$lnr++;
		$linje[$lnr]=filler(23,"0").filler(13," ")."40".$usdd.filler(6," ")."DAN".filler(9," ").$cvrnr[0]."X".filler(9," ")."\n";
		if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
	}


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
	
	if ($lev_pbs!='L') {
		$lnr++;
		$linje[$lnr]="BS002".$cvrnr[0]."BS10605".$leverance_id.filler(19," ").$dkdd."\n";
		$linjeoid[$lnr]=0;
		if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
	}
	
	if ($antal_nye>0) opret_nye($antal_nye,$leverance_id,$dkdd,$cvrnr,$bank_reg,$bank_konto,$pbs_nr,$ny_pbs_aftale,$kontonr);

	$x=0;
	$q=db_select("select adresser.kontonr as ny_kontonr, adresser.pbs_nr as pbs_nr, adresser.gruppe as gruppe, pbs_kunder.kontonr as kontonr from adresser,pbs_kunder where adresser.id=pbs_kunder.konto_id and adresser.kontonr!=pbs_kunder.kontonr order by adresser.id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$x++;
		$ny_konto_nr[$x]=$r['ny_kontonr'];
		$kontonr[$x]=$r['kontonr'];
		$pbs_nr[$x]=$r['pbs_nr'];
		$gruppe[$x]=$r['gruppe'];
	}
	$antal_rettes=$x;
	$antal_rettes=0;
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
			$konto_id[$x]=$r['id'];
			$slet_konto_id[$x]=$r['id'];
		}
	}
	$antal_stoppes=$x;
#	$antal_stoppes=0;
# echo "stoppes $antal_stoppes $slet_konto_id[$x]<br>";
	
	if ($antal_stoppes>0 && $lev_pbs=='L') l_stop_aftale($antal_stoppes,$leverance_id,$dkdd,$cvrnr,$pbs_nr,$kontonr,$slet_konto_id);
	elseif ($antal_stoppes>0) l_stop_aftale($antal_stoppes,$leverance_id,$dkdd,$cvrnr,$pbs_nr,$kontonr,$slet_konto_id);
	
	$antal=$antal_nye+$antal_rettes+$antal_stoppes;
	while(strlen($antal)<11) $antal="0".$antal;
	while(strlen($bank_reg[$x])<4) $bank_reg[$x]="0".$bank_reg[$x];
	while(strlen($bank_konto[$x])<10) $bank_konto[$x]="0".$bank_konto[$x];
	$sektioner=0;
	if ($antal_nye>0)$sektioner++;
	if ($antal_rettes>0)$sektioner++;	
	if ($antal_stoppes>0)$sektioner++;	
	while(strlen($sektioner)<11) $sektioner="0".$sektioner;
	if ($lev_pbs!='L') {
		$lnr++;
		$linje[$lnr]="BS992".$cvrnr[0]."BS10605".$sektioner.$antal.filler(15,"0").filler(11,"0").filler(15,"0").filler(11,"0").filler(34,"0")."\n";
		if ($afslut && $antal>0) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
		elseif ($afslut) db_modify("delete from pbs_linjer where liste_id = '$id'",__FILE__ . " linje " . __LINE__);
		if ($antal==0) $lnr=0;
	}
	

########## sektion slut  - betalingsaftaler #############
	$x=0;
#	if ($lev_pbs=='L') $antal_nye=0;
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
#		echo "update pbs_ordrer set liste_id='$tmp' where liste_id='$id'<br>";
		db_modify("update pbs_ordrer set liste_id='$tmp' where liste_id='$id'");
	}
	$antal_ordrer=$x;

	if ($antal_ordrer>0) {
		if ($lev_pbs=='L') l_inset_ordrer($antal_ordrer,$leverance_id,$dkdd,$ordre_id,$cvrnr,$bank_reg,$bank_konto,$pbs_nr);
		else inset_ordrer($antal_ordrer,$leverance_id,$dkdd,$ordre_id,$cvrnr,$bank_reg,$bank_konto,$pbs_nr,$ny_pbs_aftale,$kontonr);
	}
	if ($afslut && ($antal_ordrer || $antal_nye || $antal_rettes || $antal_stoppes)) {	
		$filnavn="../temp/".$db."/PBS_Leverance".$id.".txt";
		$fp=fopen("$filnavn","w");
		$q=db_select("select linje from pbs_linjer where liste_id = $id order by id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)){
			if ($charset=="UTF-8") $linje=utf8_decode(stripslashes($r['linje']));
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
			if ($charset=="UTF-8") $linje=utf8_decode(stripslashes($r['linje']));
			else $linje=$r['linje'];
#			$linje=$r['linje'];
			fwrite($fp,$linje);
		}
		fclose($fp);
}
$tmp1="";$tmp2="";
if (!$afslut) for ($x=1;$x<=$lnr;$x++)	{
	if ($lev_pbs=='L') $tmp1=substr($linje[$x],0,3);
	else $tmp1=substr($linje[$x],0,5);
	if ($lev_pbs!='L' && $tmp1 != $tmp2 && $tmp1 == "BS022") {
		print "<tr><td></b><a href=pbsfile.php?id=$id&slet_ordreid=$linjeoid[$x]>Klik her for at slette nedenst&aring;ende ordre fra listen</b></a></td></tr>";
	}
	elseif ($lev_pbs=='L' && $tmp1 != $tmp2 && $tmp1 == "001") {
#		print "<tr><td><a href=pbsfile.php><b>Klik her for at slette nedenst&aring;ende ordre fra listen</b></a></td></tr>";
	}
	print "<tr><td>".str_replace(" ","&nbsp;",$linje[$x])."</td></tr>";
	$tmp2=substr($linje[$x],0,5);
}
if ($afslut || $afsendt) print "<tr><td title=\"Klik for at &aring;bne PBS filen. H&oslash;jreklik for at gemme.\" align=center> H&oslash;jreklik <b><a href=\"$filnavn\" target=\"blank\">her</a></b> for at gemme PBS filen</td></tr>";
	
function opret_nye ($antal_nye,$leverance_id,$dkdd,$cvrnr,$bank_reg,$bank_konto,$pbs_nr,$ny_pbs_aftale,$kontonr) {
	global $id;
	global $lnr;
	global $afslut;
	global $linje;
	global $debitorgruppe;
	global $lev_pbs;

	$lnr++;
	
	if ($lev_pbs=='L') $linje[$lnr]="001".$pbs_nr[0].filler(15,"0").filler(6,"0").filler(14,"0").filler(37," ")."\n";
	else $linje[$lnr]="BS012".$pbs_nr[0]."0120".filler(3,"0").filler(15,"0").filler(9," ").filler(6,"0")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

	for ($x=1;$x<=$antal_nye;$x++) {
		while(strlen($kontonr[$x])<15) $kontonr[$x]="0".$kontonr[$x];
		if ($lev_pbs=='L') while(strlen($cvrnr[$x])<8) $cvrnr[$x]="0".$cvrnr[$x];
		else while(strlen($cvrnr[$x])<10) $cvrnr[$x]="0".$cvrnr[$x];
		while(strlen($bank_reg[$x])<4) $bank_reg[$x]="0".$bank_reg[$x];
		while(strlen($bank_konto[$x])<10) $bank_konto[$x]="0".$bank_konto[$x];
		while(strlen($gruppe[$x])<5) $gruppe[$x]="0".$gruppe[$x];
		$lnr++;
		if ($lev_pbs=='L') $linje[$lnr]="510".$pbs_nr[0].$kontonr[$x].$bank_reg[$x].$bank_konto[$x].$cvrnr[$x].filler(35," ")."\n";
		else $linje[$lnr]="BS042".$pbs_nr[0]."0200".filler(3,"0").$debitorgruppe.$kontonr[$x].filler(9,"0").filler(6,"0").filler(6,"0").$cvrnr[$x].filler(10," ").$bank_reg[$x].filler(4," ").$bank_konto[$x].filler(10," ")."0".filler(4,"0")."\n";
		if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
	
	}
	if ($lev_pbs!='L') {
		$lnr++;
		while(strlen($antal_nye)<11) $antal_nye="0".$antal_nye;
		$linje[$lnr]="BS092".$pbs_nr[0]."0120".filler(9," ").$antal_nye.filler(15,"0").filler(11,"0").filler(15," ").filler(11,"0").filler(39,"0")."\n";
		if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
	}
}

function ret_exist ($antal_rettes,$leverance_id,$dkdd,$cvrnr,$pbs_nr,$ny_kontonr,$kontonr) {
	global $id;
	global $lnr;
	global $afslut;
	global $linje;
	global $debitorgruppe;
	
	$lnr++;
	$linje[$lnr]="BS012".$pbs_nr[0]."0125".filler(3,"0").filler(15,"0").filler(9," ").filler(6,"0")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

	for ($x=1;$x<=$antal_rettes;$x++) {
		while(strlen($kontonr[$x])<15) $kontonr[$x]="0".$kontonr[$x];
		while(strlen($ny_kontonr[$x])<15) $ny_kontonr[$x]="0".$ny_kontonr[$x];
		while(strlen($pbs_nr[$x])<9) $pbs_nr[$x]="0".$pbs_nr[$x];
		$lnr++;
		$linje[$lnr]="BS042".$pbs_nr[0]."0272".filler(3,"0").$debitorgruppe.$kontonr[$x].$pbs_nr[$x].$dkdd.filler(6,"0").$ny_kontonr[$x].filler(53," ")."\n";
		if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
	
	}
	while(strlen($antal_rettes)<11) $antal_rettes="0".$antal_rettes;
	$lnr++;
	$linje[$lnr]="BS092".$pbs_nr[0]."0125".filler(9," ").$antal_rettes.filler(15,"0").filler(11,"0").filler(15," ").filler(11,"0").filler(39,"0")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

}

function stop_aftale ($antal_stoppes,$leverance_id,$dkdd,$cvrnr,$pbs_nr,$kontonr,$slet_konto_id) {
	global $id;
	global $lnr;
	global $afslut;
	global $linje;
	global $debitorgruppe;

	$lnr++;
	$linje[$lnr]="BS012".$pbs_nr[0]."0105".filler(3,"0").filler(15,"0").filler(9," ").filler(6,"0")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

	for ($x=1;$x<=$antal_stoppes;$x++) {
		while(strlen($kontonr[$x])<15) $kontonr[$x]="0".$kontonr[$x];
		while(strlen($pbs_nr[$x])<9) $pbs_nr[$x]="0".$pbs_nr[$x];
		$lnr++;
		$linje[$lnr]="BS042".$pbs_nr[0]."0253".filler(3,"0").$debitorgruppe.$kontonr[$x].$pbs_nr[$x].$dkdd."\n";
		if ($afslut) {
			db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
			db_modify("delete from pbs_kunder where konto_id=$slet_konto_id[$x]",__FILE__ . " linje " . __LINE__); #20140422
		}
	}
	$lnr++;
	while(strlen($antal_rettes)<11) $antal_rettes="0".$antal_rettes;
	$linje[$lnr]="BS092".$pbs_nr[0]."0105".filler(9," ").$antal_stoppes.filler(15,"0").filler(11,"0").filler(15," ").filler(11,"0").filler(39,"0")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

}

function l_stop_aftale ($antal_stoppes,$leverance_id,$dkdd,$cvrnr,$pbs_nr,$kontonr,$slet_konto_id) {
	global $id;
	global $lnr;
	global $afslut;
	global $linje;
	global $debitorgruppe;
	
	$lnr++;
	$linje[$lnr]="001".$pbs_nr[0].filler(15,"0").filler(6,"0").filler(14,"0").filler(37," ")."\n";
	for ($x=1;$x<=$antal_stoppes;$x++) {
		while(strlen($kontonr[$x])<15) $kontonr[$x]="0".$kontonr[$x];
		while(strlen($pbs_nr[$x])<5) $pbs_nr[$x]="0".$pbs_nr[$x];
		$lnr++;
		$linje[$lnr]="540".$pbs_nr[0].$kontonr[$x].filler(33,"0").filler(24," ")."\n";
		if ($afslut) {
			db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
			db_modify("delete from pbs_kunder where konto_id = '$slet_konto_id[$x]'",__FILE__ . " linje " . __LINE__); #20121023
		}
	}
	while(strlen($antal_stoppes)<7) $antal_stoppes="0".$antal_stoppes;
	$lnr++;
	$linje[$lnr]="999".$pbs_nr[0].filler(15,"9").$antal_stoppes.filler(13,"0").filler(13,"0").filler(13,"0").filler(24," ")."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
}

function l_inset_ordrer($antal_ordrer,$leverance_id,$dkdd,$ordre_id,$cvrnr,$bank_reg,$bank_konto,$pbs_nr) {
	global $id;
	global $lnr;
	global $afslut;
	global $linje;
	global $delsystem;
	global $charset;
	global $lev_pbs;
	global $debitorgruppe;
	global $usdd;

	include("../includes/forfaldsdag.php");
	$r042sum=0;
	$r022lin=0;
	$r052lin=0;

	$forfaldsdage=array();
	$kontonumre=array();
	$fx=0;
	$kx=0;
	for ($x=1;$x<=$antal_ordrer;$x++) {
		$r=db_fetch_array(db_select("select * from ordrer where id='$ordre_id[$x]'",__FILE__ . " linje " . __LINE__));
		$fakturadate=$r['fakturadate'];
		$betalingsbet=$r['betalingsbet'];
		$betalingsdage=$r['betalingsdage'];
		list($dd,$mm,$yy)=explode("-",forfaldsdag($fakturadate, $betalingsbet, $betalingsdage));	
		$forfaldsdag=substr($yy,-2).$mm.$dd;
		while ($forfaldsdag<=$usdd) {
			$betalingsdage++;
			list($dd,$mm,$yy)=explode("-",forfaldsdag($fakturadate, $betalingsbet, $betalingsdage));	
			$forfaldsdag=substr($yy,-2).$mm.$dd;
		}	
		$ut=mktime(12,0,0,$mm,$dd,$yy);
	
		$dw=date("w",$ut);
		while ($dw==6||$dw==0) {
			$ut+=(3600*24);
			$dw=date("w",$ut);
		}  
		$forfaldsdag=date("ymd",$ut);
		if (!in_array($forfaldsdag,$forfaldsdage)) {
			$fx++;
			$forfaldsdage[$fx]=$forfaldsdag;
			$o_id[$fx]=$ordre_id[$x];
#cho "FF $forfaldsdage[$fx]<br>";
		} else {
			for ($y=1;$y<=$fx;$y++) {
				if ($forfaldsdage[$y]==$forfaldsdage[$fx]) $o_id[$fx].=",".$ordre_id[$x]; 
			}
		}
	}
	$fx=1;
	$k_id=array();
	while($forfaldsdage[$fx]){
#CHO "IOD $o_id[$fx]<br>";
		$tjek=array();
		$tjek=explode(",",$o_id[$fx]);
		$lnr++;
		$linje[$lnr]="001".$pbs_nr[0].filler(15,"0").$forfaldsdage[$fx].filler(14,"0").filler(37," ")."\n";
		if ($afslut) {
			if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
			db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
		}
		$x=0;
		$kontonr=array();
		$total[$fx]=0;
		
		$q=db_select("select * from ordrer order by konto_id",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			if (in_array($r['id'],$tjek)) {
#cho "A kontonr $r[kontonr]<br>";
				if (!in_array($r['kontonr'],$kontonr)) {
					$x++;
					$kontonr[$x]=$r['kontonr'];
					$belob[$x]=round(($r['sum']+$r['moms'])*100,0);
#cho "B kontonr $r[kontonr] : $belob[$x]<br>";
				} else {
					$belob[$x]+=round(($r['sum']+$r['moms'])*100,0);
#cho "C kontonr $r[kontonr] : $belob[$x]<br>";
				}
				$total[$fx]+=round(($r['sum']+$r['moms'])*100,0);
#cho "total $total[$fx]<br>";
			}
		}
		$kontoantal=0;		
		$x=1;
		while ($kontonr[$x]) {
			$kontoantal++;
			while(strlen($belob[$x])<11) $belob[$x]="0".$belob[$x];
			while(strlen($kontonr[$x])<15) $kontonr[$x]="0".$kontonr[$x];
			$lnr++;
			if ($belob[$x]>0) $linje[$lnr]="580".$pbs_nr[0].$kontonr[$x].filler(22,"0").$belob[$x].filler(24," ")."\n";
			else $linje[$lnr]="585".$pbs_nr[0].$kontonr[$x].filler(22,"0").$belob[$x].filler(24," ")."\n";
			if ($afslut) {
				if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
				db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
			}
			$x++;
		}
		$lnr++;
			while(strlen($kontoantal)<7) $kontoantal="0".$kontoantal;
			while(strlen($total[$fx])<13) $total[$fx]="0".$total[$fx];
		if ($total[$fx]>0) $linje[$lnr]="999".$pbs_nr[0].filler(15,"9")."$kontoantal".$total[$fx].filler(13,"0").filler(24," ")."\n";
		else {
			$total[$fx]=str_replace("-","0",$total[$fx]);
			$linje[$lnr]="999".$pbs_nr[0].filler(15,"9").$kontoantal.filler(13,"0").$total[$fx].filler(13,"0").filler(24," ")."\n";
		}
		if ($afslut) {
			if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
			db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
		}
		$fx++;
	}

/*	
	for ($x=1;$x<=$antal_ordrer;$x++) {
		$r=db_fetch_array(db_select("select * from ordrer where id='$ordre_id[$x]'",__FILE__ . " linje " . __LINE__));
		$fakturanr=$r['fakturanr'];
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
		$fakturadate=$r['fakturadate'];
		$betalingsbet=$r['betalingsbet'];
		$betalingsdage=$r['betalingsdage'];

		$udskriv_til=$r['udskriv_til']; # tilfoejet 20.03.2011 
		if ($lev_pbs=='B') {
			$firmanavn='';$adresse='';$postnr='';$ean='';$institution='';
		}
		if ($charset=="UTF-8") {
			$firmanavn=utf8_decode($firmanavn);
			$adresse=utf8_decode($adresse);
			$institution=utf8_decode($institution);
		}
		while(strlen($belob)<11) $belob="0".$belob;
		while(strlen($kontonr)<15) $kontonr="0".$kontonr;
		list($dd,$mm,$yy)=explode("-",forfaldsdag($fakturadate, $betalingsbet, $betalingsdage));	
		$yy=substr($yy,-2,2);
		$forfaldsdag=$yy.$mm.$dd;
		while ($forfaldsdag<=$usdd) {
			$betalingsdage++;
			list($dd,$mm,$yy)=explode("-",forfaldsdag($fakturadate, $betalingsbet, $betalingsdage));	
			$yy=substr($yy,-2,2);
			$forfaldsdag=$yy.$mm.$dd;
		}	
		$lnr++;
		$linje[$lnr]="001".$pbs_nr[0].filler(15,"0").$forfaldsdag.filler(14,"0").filler(37," ")."\n";
		if ($afslut) {
			if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
			db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
		}
		$lnr++;
		if ($belob>0) $linje[$lnr]="580".$pbs_nr[0].$kontonr.filler(22,"0").$belob.filler(24," ")."\n";
		else $linje[$lnr]="585".$pbs_nr[0].$kontonr.filler(22,"0").$belob.filler(24," ")."\n";
		if ($afslut) {
			if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
			db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
		}
		$lnr++;
		if ($belob>0) $linje[$lnr]="999".$pbs_nr[0].filler(15,"9")."000000100".$belob.filler(13,"0").filler(24," ")."\n";
		else $linje[$lnr]="999".$pbs_nr[0].filler(15,"9")."000000100".filler(13,"0").$belob.filler(13,"0").filler(24," ")."\n";
		if ($afslut) {
			if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
			db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
		}
	}
*/
}

function inset_ordrer($antal_ordrer,$leverance_id,$dkdd,$ordre_id,$cvrnr,$bank_reg,$bank_konto,$pbs_nr,$ny_pbs_aftale,$kontonr) {
	global $id;
	global $lnr;
	global $afslut;
	global $linje;
	global $linjeoid;
	global $delsystem;
	global $charset;
	global $lev_pbs;
	global $debitorgruppe;
	
	include("../includes/forfaldsdag.php");
	$r042sum=0;
	$r022lin=0;
	$r052lin=0;

	$lnr++;
	$linje[$lnr]="BS002".$cvrnr[0].$delsystem."0601".$leverance_id.filler(19," ").$dkdd."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

	$lnr++;
	$linje[$lnr]="BS012".$pbs_nr[0]."0112".filler(5," ").$debitorgruppe.filler(15,"0").filler(4," ")."00000000".$bank_reg[0].$bank_konto[0]."\n";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
	for ($x=1;$x<=$antal_ordrer;$x++) {
		$r=db_fetch_array(db_select("select * from ordrer where id='$ordre_id[$x]'",__FILE__ . " linje " . __LINE__));
		$fakturanr=$r['fakturanr'];
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

		$udskriv_til=$r['udskriv_til']; # tilfoejet 20.03.2011 

		while(strlen($kontonr)<15) $kontonr="0".$kontonr; # 20140122 Denne + næste 3 linjer flyttet over nedenstående else 
		while(strlen($pbs_nr[$x])<9) $pbs_nr[$x]="0".$pbs_nr[$x];
		list($dd,$mm,$yy)=explode("-",forfaldsdag($fakturadate, $betalingsbet, $betalingsdage));	
		$forfaldsdag=$dd.$mm.$yy;

		if ($lev_pbs=='B') {
			$firmanavn='';$adresse='';$postnr='';$ean='';$institution='';
		} else { #else indsat 20140121
			if ($charset=="UTF-8") {
				$firmanavn=utf8_decode($firmanavn);
				$adresse=utf8_decode($adresse);
				$institution=utf8_decode($institution);
			}
			if ($udskriv_til=='PBS_FI') $pbs_nr[$x]='000000000'; # tilfoejet 20.03.2011 # rettet til '000000000' 20140207
			$r022lin++;
			$lnr++;
			$linje[$lnr]="BS022".$pbs_nr[0]."0240"."00001".$debitorgruppe.$kontonr.$pbs_nr[$x].addslashes($firmanavn)."\n";
			$linjeoid[$lnr]="$ordre_id[$x]";
			if ($afslut) {
				if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
				db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
			}
			if ($ean) {
				$lnr++;
				$r022lin++;
				$linje[$lnr]="BS022".$pbs_nr[0]."0240"."00002".$debitorgruppe.$kontonr.$pbs_nr[$x].$ean."\n";
				$linjeoid[$lnr]="$ordre_id[$x]";
				if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
				$linjenr="00003";
			} else $linjenr="00002";
			$lnr++;
			$r022lin++;
			$linje[$lnr]="BS022".$pbs_nr[0]."0240".$linjenr.$debitorgruppe.$kontonr.$pbs_nr[$x].$adresse."\n";
			$linjeoid[$lnr]="$ordre_id[$x]";
			if ($afslut) {
				if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
				db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
			}
			$r022lin++;
			while(strlen($postnr)<4) $postnr="0".$postnr;
			$lnr++;
			$linje[$lnr]="BS022".$pbs_nr[0]."0240"."00009".$debitorgruppe.$kontonr.$pbs_nr[$x].filler(15," ").$postnr."\n";
			$linjeoid[$lnr]="$ordre_id[$x]";
			if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
		}	
		if ($belob>0) $felt10="1";	
		elseif ($belob<0) {
			$felt10="2";
			$belob=$belob*-1;
		}	else $felt10="0";
		while(strlen($belob)<13) $belob="0".$belob;
		$lnr++;
		$linje[$lnr]="BS042".$pbs_nr[0]."0280"."00000".$debitorgruppe.$kontonr.$pbs_nr[$x].$forfaldsdag.$felt10.$belob.filler(30," ")."00"."\n";
		$linjeoid[$lnr]="$ordre_id[$x]";
		if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

		$r052lin++;
		$recordnr="00001";
		$beskrivelse="Fakturadato ".dkdato($fakturadate)."     Fakturanr: $fakturanr ";
		if ($charset=="UTF-8") {
			$belob=utf8_decode($belob);
			$beskrivelse=utf8_decode($beskrivelse);
		}
		while(strlen($beskrivelse)<65) $beskrivelse=$beskrivelse." ";
		$lnr++;
		$linje[$lnr]="BS052".$pbs_nr[0]."0241".$recordnr.$debitorgruppe.$kontonr.$pbs_nr[$x]." ".addslashes($beskrivelse)."\n";
		$linjeoid[$lnr]="$ordre_id[$x]";
		if ($afslut) {
			if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
			db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
		}

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
		$linje[$lnr]="BS052".$pbs_nr[0]."0241".$recordnr.$debitorgruppe.$kontonr.$pbs_nr[$x]." ".addslashes($beskrivelse).$antal.$pris.$belob."\n";
		$linjeoid[$lnr]="$ordre_id[$x]";
		if ($afslut) {
			if ($charset=="UTF-8") $linje[$lnr]=utf8_encode($linje[$lnr]);
			db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);
		}

		$y=0;
		$q=db_select("select * from ordrelinjer where ordre_id='$ordre_id[$x]' order by posnr",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$y++;
			$r052lin++;
			$beskrivelse=var2str($r['beskrivelse'],$ordre_id[$x]);
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
			$linje[$lnr]="BS052".$pbs_nr[0]."0241".$recordnr.$debitorgruppe.$kontonr.$pbs_nr[$x]." ".addslashes(addslashes($beskrivelse)).$antal.$pris.$belob."\n";
			$linjeoid[$lnr]="$ordre_id[$x]";
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
			$linje[$lnr]="BS052".$pbs_nr[0]."0241".$recordnr.$debitorgruppe.$kontonr.$pbs_nr[$x]." ".addslashes($beskrivelse).$dksum."\n";
			$linjeoid[$lnr]="$ordre_id[$x]";
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
			$linje[$lnr]="BS052".$pbs_nr[0]."0241".$recordnr.$debitorgruppe.$kontonr.$pbs_nr[$x]." ".addslashes($beskrivelse).$dkmoms."\n";
			$linjeoid[$lnr]="$ordre_id[$x]";
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
			$linjeoid[$lnr]="$ordre_id[$x]";
			$linje[$lnr]="BS052".$pbs_nr[0]."0241".$recordnr.$debitorgruppe.$kontonr.$pbs_nr[$x]." ".addslashes($beskrivelse).$ialt."\n";
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
	$linje[$lnr]="BS092".$pbs_nr[0]."0112".filler(5,"0").$debitorgruppe.filler(4," ").$antal_ordrer.$r042sum.$r052lin.filler(15," ").$r022lin."\n";
	$linjeoid[$lnr]="$ordre_id[$x]";
	if ($afslut) db_modify("insert into pbs_linjer (liste_id,linje) values ('$id','$linje[$lnr]')",__FILE__ . " linje " . __LINE__);

	$lnr++;
#	$linje[$lnr]="BS992".$cvrnr[0]."BS10601"."00000000001".$antal_ordrer.$r042sum.$r052lin.filler(15,"0").$r022lin.filler(34,"0")."\n";
	$linje[$lnr]="BS992".$cvrnr[0].$delsystem."0601"."00000000001".$antal_ordrer.$r042sum.$r052lin.filler(15,"0").$r022lin.filler(34,"0")."\n";
	$linjeoid[$lnr]="$ordre_id[$x]";
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
