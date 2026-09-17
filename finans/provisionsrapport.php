<?php

// ----------------finans/provisionsrapport.php--------------lap 3.0.6------2010.10.11----
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


@session_start();
$s_id=session_id();
$title="Provisionsrapport";
$modulnr=4;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");
 
if ($popup) $returside="../includes/luk.php";
else $returside="rapport.php";
 
$ansat_id=array();

if (isset ($_POST['submit'])) {
	list($startdato, $slutdato)=explode(":",$_POST['periode']);
}

$q = db_select("select * from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
if ($r = db_fetch_array($q)) {$rettigheder = trim($r['rettigheder']);}

$r = db_fetch_array(db_select("select ansat_id, rettigheder from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
if ($r[ansat_id]) {
	$ansat_id[1] = $r['ansat_id'];
	$ansat_antal=1;
}
$rettigheder=trim($r['rettigheder']);

if ((substr($rettigheder,4,1)!='1') && (!$ansat_id[1])) {
	$ansat_id[1]='0';
	print "<body onLoad=\"javascript:alert('Dit brugernavn: $brugernavn er ikke linket til en medarbejder.')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
	exit;
} elseif (substr($rettigheder,4,1)=='1') {
	if ($ansat_id[1]) $x=1;
	else $x=0;
	$q = db_select("SELECT ansatte.id as id FROM ansatte, provision WHERE ansatte.id=provision.ansat_id and ansatte.lukket!='on' and provision.provision>0 ORDER BY ansatte.navn",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!in_array($r[id], $ansat_id)) {
			$x++;
			$ansat_id[$x]=$r['id'];
		}
	}
	$ansat_antal=$x;
}
if ($ansat_antal>=1) udskriv ($ansat_antal, $ansat_id, $startdato, $slutdato);
else {
	print "<body onLoad=\"javascript:alert('Der er ikke angivet provisionssats for nogen ansatte.')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
	exit;
}

function udskriv ($ansat_antal, $ansat_id, $startdato, $slutdato)
{
	global $returside;
	
for($a=1; $a<=$ansat_antal; $a++) {	
	if ($r = db_fetch_array(db_select("select * from ansatte where id = '$ansat_id[$a]'",__FILE__ . " linje " . __LINE__))) {
		$ref=$r['navn'];
	}

	if ($r = db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '1'",__FILE__ . " linje " . __LINE__))) {
		$box1=$r['box1'];
		$box2=$r['box2'];
		$box3=$r['box3'];
		$box4=$r['box4'];
	}

	if ($box1=='ref') $personkilde="and ordrer.ref='$ref'";
	elseif ($box1=='kua') $personkilde="and adresser.kontoansvarlig = '$ansat_id[$a]'";
	else $personkilde="and (adresser.kontoansvarlig = '$ansat_id[$a]' or ((adresser.kontoansvarlig = NULL or adresser.kontoansvarlig = '0') and ordrer.ref='$ref'))";
	if ($box2=='kort') $kostkilde="kort";
	else $kostkilde="batch";
	if ($box4=='fak') $grundlag="fak";
	else $grundlag="bet";



	if ($a==1) {
#		$slutmaaned=date("m");
#		$slutaar=date("Y");
#		$default_slutdato=$box3."-".$slutmaaned."-".$slutaar;
		$default_startdato=predato($box3."-".date("m")."-".date("Y"));
		$default_slutdato=slutdato($default_startdato);
	
		if (!$startdato) {
			$startdato=$default_startdato;
			$slutdato=$default_slutdato;
		}

		print "<table width=100%  border=0 cellspacing=0 cellpadding=0><tbody>";
		print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

		print "<tr>\n";
		print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=$returside accesskey=L>Luk</a></td>";
		print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Provisionsrapport</td>";
		print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
		print "</tr>\n";
		print "<form name=provisionsrapport action=provisionsrapport.php method=post>\n";
		print "<tr><td colspan='3'><input type=submit value=\"Periode\" name=\"submit\"><select name=periode>";
		print"<option>$startdato : $slutdato</option>";
		$tmp=$default_startdato;
		$tmp2=$default_slutdato;
		for ($x=12; $x>=1; $x--) { 
			print "<option>$tmp : $tmp2</option>";
			$tmp=predato($tmp);
			$tmp2=slutdato($tmp);
		} 
		print "</select>&nbsp;";
		print "</form>\n";
		print "<tr><td colspan='3'><b>$ref</b></td></tr>";
# Kan ikke forstaa, hvorfor der ikke skal vaere en overskrift, hvis der blot er flere... 
	} else print "<tr><td colspan='3'><b>$ref</b></td></tr>";

	print "</tbody></table>";
	print "<tr><td valign=top>";
	print "<table width=100% align=center valign=top border=0 cellspacing=0 cellpadding=0><tbody>";
	print "<tr><td></td></tr>";
	print "<tr><td colspan=7><br></td></tr>";
	if ($grundlag=='bet')	print "<tr><td colspan=7> Betalte fakturaer i peroiden.</td><tr>";
	else print "<tr><td colspan=7> Fakturerede ordrer i perioden.</td><tr>";
	print "<tr><td colspan=7><br></td><tr>";
	if ($grundlag=='bet') print "<tr><td width=15%> Fakturadato</td><td> Betalingsdato</td><td align=right width=10%> Fakturanr</td><td align=right width=15%> Kostpris</td><td align=right width=15%> Salgspris</td><td align=right width=15%> D&aelig;knings-<br />bidrag</td><td align=right width=15%> Provision</td><tr>";
	else print "<tr><td width=15%> Fakturadato</td><td> Forfaldsdato</td><td align=right width=10%> Fakturanr</td><td align=right width=15%> Kostpris</td><td align=right width=15%> Salgspris</td><td align=right width=15%> D&aelig;knings-<br />bidrag</td><td align=right width=15%> Provision</td><tr>";
	print "<tr><td colspan=7><hr></td><tr>";
	$x=0;
	$faktliste=array();
	$startdate=usdate($startdato);
	$slutdate=usdate($slutdato);
	$sum=0;
	$kostsum=0;
	$pro_sum=0;

if ($grundlag=='bet') $q1 = db_select("SELECT ordrer.firmanavn as firmanavn, ordrer.fakturadate as faktdate, openpost.udlign_date as udlign_date, openpost.faktnr as faktnr, ordrer.id as ordre_id, grupper.box6 as box6, grupper.id as gruppe_id from adresser, openpost, ordrer, grupper where (ordrer.art='DO' or ordrer.art='DK') $personkilde and adresser.id=openpost.konto_id and adresser.gruppe=".nr_cast("grupper.kodenr")." and grupper.art='DG' and ordrer.fakturanr=openpost.faktnr and  openpost.udlign_date >= '$startdate' and openpost.udlign_date <= '$slutdate' and openpost.udlignet = '1' and openpost.faktnr>'0' order by openpost.udlign_date, openpost.faktnr",__FILE__ . " linje " . __LINE__);
else $q1 = db_select("SELECT ordrer.firmanavn as firmanavn, ordrer.fakturadate as faktdate, ordrer.fakturanr as faktnr, ordrer.id as ordre_id , ordrer.betalingsbet as betalingsbet, ordrer.betalingsdage, grupper.box6 as box6, grupper.id as gruppe_id from adresser, ordrer, grupper where (ordrer.art='DO' or ordrer.art='DK') and adresser.id=ordrer.konto_id and adresser.gruppe=".nr_cast("grupper.kodenr")." and grupper.art='DG' $personkilde and ordrer.status>=3 and ordrer.fakturadate >= '$startdate' and ordrer.fakturadate <= '$slutdate' order by ordrer.fakturadate, ordrer.fakturanr",__FILE__ . " linje " . __LINE__);
while ($r1 = db_fetch_array($q1)) {
		if (!in_array($r1['faktnr'], $faktliste)) {
			$x++;
			$pris[$x]=0;
			$kostpris[$x]=0;
			$faktliste[$x]=$r1['faktnr'];
			$firmanavn=str_replace(" ","&nbsp;",stripslashes($r1['firmanavn']));
			if ($r1[box6]!=NULL) $pro_procent[$x]=$r1['box6'];
			else $pro_procent[$x]=100;
			if (($ansat_id[$a])&&($r1[gruppe_id])&&($r2 = db_fetch_array(db_select("SELECT provision from provision where ansat_id='$ansat_id[$a]' and gruppe_id='$r1[gruppe_id]'",__FILE__ . " linje " . __LINE__)))) $provision=$r2['provision'];
			else $provision=0;
			if ($grundlag=='bet') list($tmp,$tmp2,$tmp3) = varelinjer($r1[ordre_id], $r1[faktdate], $r1[udlign_date], $provision, $r1['faktnr'], $r1['firmanavn'], $pro_procent[$x]) ;
			else list($tmp,$tmp2,$tmp3) = varelinjer($r1[ordre_id], $r1[faktdate], forfaldsdag($r1[faktdate],$r1[betalingsbet],$r1[betalingsdage]), $provision, $r1['faktnr'], $firmanavn, $pro_procent[$x]);
			$sum=$sum+$tmp;
			$kostsum=$kostsum+$tmp2;
			$pro_sum=$pro_sum+$tmp3;
		}
	}
	$tmp=$sum - $kostsum;
	$tmp2=$pro_sum/100*$pro_procent[$x];
	print "<tr><td colspan=7><hr></td></tr>";
	print "<tr><td colspan=3> I alt</td><td align=right>".dkdecimal($kostsum)."</td><td align=right>".dkdecimal($sum)."</td><td align=right>".dkdecimal($tmp)."</td><td align=right>".dkdecimal($tmp2)."</td></tr>";
	print "<tr><td colspan=7><hr></td></tr>";
	print "<tr><td colspan=7><br></td></tr>";
	if ($grundlag=='bet') {
		print "<tr><td colspan=7> Fakturaer som ikke er betalt</td><tr>";
		print "<tr><td colspan=7><br></td><tr>";
		print "<tr><td> Fakturadato</td><td> Forfaldsdato</td><td align=right>Fakturanr</td><td align=right> Kostpris</td><td align=right> Salgspris</td><td align=right> DB</td><td align=right> Provision</td><tr>";
		print "<tr><td colspan=7><hr></td><tr>";
		$sum=0;
		$kostsum=0;
		$pro_sum=0;
		$faktliste=array();
		$q1 = db_select("SELECT ordrer.firmanavn as firmanavn, ordrer.fakturadate as faktdate, openpost.faktnr as faktnr, ordrer.id as ordre_id, ordrer.betalingsbet as betalingsbet, ordrer.betalingsdage, grupper.box6 as box6, grupper.id as gruppe_id from adresser, openpost, ordrer, grupper where  (ordrer.art='DO' or ordrer.art='DK') and adresser.gruppe=".nr_cast("grupper.kodenr")." and grupper.art='DG' $personkilde and adresser.id=openpost.konto_id and ordrer.fakturanr=openpost.faktnr and ordrer.konto_id=openpost.konto_id and openpost.udlignet = '0' and openpost.faktnr>'0' order by openpost.transdate, openpost.faktnr",__FILE__ . " linje " . __LINE__);
		while ($r1 = db_fetch_array($q1)) {
			if (!in_array($r1['faktnr'], $faktliste)) {
				$x++;
				$pris[$x]=0;
				$kostpris[$x]=0;
				$faktliste[$x]=$r1['faktnr'];
				$firmanavn=str_replace(" ","&nbsp;",stripslashes($r1['firmanavn']));
				if ($r1[box6]!=NULL) $pro_procent[$x]=$r1['box6'];
				else $pro_procent[$x]=100;
				if (($ansat_id[$a])&&($r1[gruppe_id])&&($r2 = db_fetch_array(db_select("SELECT provision from provision where ansat_id='$ansat_id[$a]' and gruppe_id='$r1[gruppe_id]'",__FILE__ . " linje " . __LINE__)))) $provision=$r2['provision'];
				else $provision=0;
				list($tmp,$tmp2,$tmp3) = varelinjer($r1[ordre_id], $r1[faktdate], forfaldsdag($r1[faktdate],$r1[betalingsbet],$r1[betalingsdage]), $provision, $r1[faktnr], $firmanavn, $pro_procent[$x]);
				$sum=$sum+$tmp;
				$kostsum=$kostsum+$tmp2;
				$pro_sum=$pro_sum+$tmp3;
	}
		}
		$tmp=$sum - $kostsum;
		$tmp2=$pro_sum/100*$pro_procent[$x];
		print "<tr><td colspan=7><hr></td></tr>";
		print "<tr><td colspan=3> I alt</td><td align=right>".dkdecimal($kostsum)."</td><td align=right>".dkdecimal($sum)."</td><td align=right>".dkdecimal($tmp)."</td><td align=right>".dkdecimal($tmp2)."</td></tr>";
		print "<tr><td colspan=7><hr></td></tr>";
		print "<tr><td colspan=7><br></td></tr>";
		print "<tr><td colspan=7><br></td></tr>";
	}
}
print "</tr></tbody></table>";
print "</td></tr>";
print "</tbody></table>";
}# end function udskriv();$r1['faktnr']

function varelinjer($ordre_id, $faktdate, $udlign_date, $provision, $faktnr, $firmanavn, $pro_procent)
{
	global $kostkilde;

	$linje_id=array();
#	$q1 = db_select("SELECT DISTINCT ordrelinjer.id as linje_id, ordrelinjer.vare_id as vare_id, ordrelinjer.antal as antal, ordrelinjer.pris as pris, ordrelinjer.rabat as rabat, varer.kostpris as kostpris, varer.gruppe as gruppe, batch_salg.batch_kob_id as batch_kob_id from ordrelinjer, varer, batch_salg where ordrelinjer.ordre_id='$ordre_id' and varer.id = ordrelinjer.vare_id and batch_salg.linje_id=ordrelinjer.id",__FILE__ . " linje " . __LINE__);
	$q1 = db_select("SELECT DISTINCT ordrelinjer.id as linje_id, ordrelinjer.vare_id as vare_id, ordrelinjer.antal as antal, ordrelinjer.pris as pris, ordrelinjer.rabat as rabat, varer.kostpris as kostpris, varer.gruppe as gruppe from ordrelinjer, varer where ordrelinjer.ordre_id='$ordre_id' and varer.id = ordrelinjer.vare_id",__FILE__ . " linje " . __LINE__);
	$y=1000;
	while ($r1 = db_fetch_array($q1)) {
		if (!in_array($r1[linje_id], $linje_id)) {
			$y++;
			$linje_id[$y]=$r1['linje_id'];
			$pris[$y]=0;
			$kostpris[$y]=0;
			$pris[$y]=($r1['pris']-($r1['pris']/100*$r1['rabat']))*$r1['antal'] ;
			$pris[$x]=$pris[$x]+$pris[$y];
			if ($kostkilde=='kort') {
				$kostpris[$y]=$r1['kostpris']*$r1['antal'];
				$kostpris[$x]=$kostpris[$x]+$kostpris[$y];
			} else {
				$r2=db_fetch_array(db_select("SELECT box8 from grupper where art='VG' and kodenr = '$r1[gruppe]'",__FILE__ . " linje " . __LINE__));
				if ($r2[box8]=='on') {
					$q3=db_select("SELECT batch_salg.antal as antal, batch_kob.pris as kostpris from batch_kob, batch_salg where batch_salg.linje_id='$r1[linje_id]' and batch_kob.id=batch_salg.batch_kob_id",__FILE__ . " linje " . __LINE__);
					while ($r3=db_fetch_array($q3)) {
			#		$r3=db_fetch_array(db_select("SELECT pris as kostpris from batch_kob where id= '$r1[batch_kob_id]'",__FILE__ . " linje " . __LINE__));
						$kostpris[$y]=$r3['kostpris']*$r3['antal'];
# if ($faktnr==168) echo "168 - $pris[$y]=($r1[pris]-($r1[pris]/100*$r1[rabat]))*$r1[antal]  ---  $kostpris[$y]=$r3[kostpris]*$r3[antal]<br>";
# if ($faktnr==173) echo "173 - $pris[$y]=($r1[pris]-($r1[pris]/100*$r1[rabat]))*$r1[antal]  ---  $kostpris[$y]=$r3[kostpris]*$r3[antal]<br>";
# if ($faktnr==174) echo "174 - $pris[$y]=($r1[pris]-($r1[pris]/100*$r1[rabat]))*$r1[antal]  ---  $kostpris[$y]=$r3[kostpris]*$r3[antal]<br>";
						$kostpris[$x]=$kostpris[$x]+$kostpris[$y];
					}
				} else {
					$kostpris[$y]=$r1['kostpris']*$r1['antal'];
					$kostpris[$x]=$kostpris[$x]+$kostpris[$y];
				}
			}
		}
	}
	$tmp=$pris[$x] - $kostpris[$x];
	$tmp2=$tmp/100*$provision/100*$pro_procent;
	print "<tr><td>".dkdato($faktdate)."</td><td> ".dkdato($udlign_date)."</td>";
	print "<td align=right onClick=\"javascript:d_ordre=window.open('../debitor/ordre.php?id=$ordre_id','d_ordre','scrollbars=yes,resizable=yes,dependent=yes');d_ordre.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"><u><span title=\"$firmanavn\">$faktnr</span></u></td>";
	print "<td align=right>".dkdecimal($kostpris[$x])."</td><td align=right>".dkdecimal($pris[$x])."</td><td align=right>".dkdecimal($tmp)."</td><td align=right>".dkdecimal($tmp2)."</td></tr>";

	return array($pris[$x],$kostpris[$x],$tmp2);	
}

function predato($dato)
{
	list($dag, $md, $aar)=explode("-",$dato);
	if ($md==1) {
		$md=12;
		$aar=$aar-1;
	}
	else $md=$md-1;
	$dag=$dag*1;
	$md=$md*1;
	if($dag<10) $dag="0".$dag;
	if($md<10) $md="0".$md;
	$dato=$dag."-".$md."-".$aar;
	return $dato;
}
function slutdato($dato)
{
	list($dag, $md, $aar)=explode("-",$dato);
	if ($dag==1) {
		$dag=31;
		while (!checkdate($md,$dag,$aar)) {
			$dag=$dag-1;
			if ($dag<28) break;
		}
	} elseif($md==12) {
		$md=1;
		$aar=$aar+1;
		$dag=$dag-1;
	} else {
		$dag=$dag-1;
		$md=$md+1;
	}
	$dag=$dag*1;
	$md=$md*1;
	if($dag<10) $dag="0".$dag;
	if($md<10) $md="0".$md;
	$dato=$dag."-".$md."-".$aar;
	return $dato;
}

?>
