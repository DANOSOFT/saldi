<?php

// -----------lager/rapport.php------------patch 2.0.56-------2009.02.27----
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
	$css="../css/standard.css";
 
	$title="Varerapport";
	$modulnr=12;
 
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	include("../includes/forfaldsdag.php");
#	include("../includes/db_query.php");

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

if (isset($_POST['submit']) && $_POST['submit']) {
	$submit=strtolower(trim($_POST['submit']));
	$varegruppe=trim($_POST['varegruppe']);
	$maaned_fra=$_POST['maaned_fra'];
	$maaned_til=$_POST['maaned_til'];
	$md=$_POST['md'];
	list ($varenr_fra, $firmanavn) = split(":", $_POST['varenr_fra']);
	list ($varenr_til, $firmanavn) = split(":", $_POST['varenr_til']);
	list ($regnaar, $firmanavn)= split("-", $_POST['regnaar']);
	
	$varenr_fra = trim($varenr_fra);
	$varenr_til = trim($varenr_til);
	$varenavn = trim($varenavn);
	
	
}
else
{
	$varegruppe=if_isset($_GET['varegruppe']);
	$maaned_fra=if_isset($_GET['maaned_fra']);
	$maaned_til=if_isset($_GET['maaned_til']);
	$varenr_fra=if_isset($_GET['varenr_fra']);
	$varenr_til=if_isset($_GET['varenr_til']);
	$regnaar=if_isset($_GET['regnaar']);
	$submit=if_isset($_GET['submit']);
}
$md[1]="januar"; $md[2]="februar"; $md[3]="marts"; $md[4]="april"; $md[5]="maj"; $md[6]="juni"; $md[7]="juli"; $md[8]="august"; $md[9]="september"; $md[10]="oktober"; $md[11]="november"; $md[12]="december";

#if (strstr($varegruppe, "ben post")) {$varegruppe="openpost";}
if ($submit != 'ok') {
	forside ($regnaar, $maaned_fra, $maaned_til, $varenr_fra, $varenr_til, $varegruppe);
}
else 
	varegruppe ($regnaar, $maaned_fra, $maaned_til, $varenr_fra, $varenr_til, $varegruppe);
#echo "$submit($regnaar, $maaned_fra, $maaned_til, $varenr_fra, $varenr_til, $varegruppe)";

#############################################################################################################
function forside($regnaar, $maaned_fra, $maaned_til, $varenr_fra, $varenr_til, $varegruppe)
{

	#global $connection;
	global $brugernavn;
	global $top_bund;
	global $md;
	global $returside;
	global $popup;
	global $jsvars;

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal v�e numerisk

	if (!$regnaar) {
		$query = db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$regnaar = $row['regnskabsaar'];
	}
	$query = db_select("select * from grupper where art = 'RA' order by box2",__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($row = db_fetch_array($query)){
		$x++;
		$regnaar_id[$x]=$row['id'];
		$regn_beskrivelse[$x]=$row['beskrivelse'];
		$start_md[$x]=$row['box1']*1;
		$start_aar[$x]=$row['box2']*1;
		$slut_md[$x]=$row['box3']*1;
		$slut_aar[$x]=$row['box4']*1;
		$regn_kode[$x]=$row['kodenr'];
		if ($regnaar==$row['kodenr']){$aktiv=$x;}
	}
	$antal_regnaar=$x;

		$query = db_select("select * from varer order by varenr",__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$vare_id[$x]=$row['id'];
		$varenr[$x]=$row['varenr'];
		$varenavn[$x]=stripslashes(substr($row['beskrivelse'],0,60));
		if ($varenr[$x]==$varenr_fra){$varenr_fra=$varenr[$x]." : ".$varenavn[$x];}
		if ($varenr[$x]==$varenr_til){$varenr_til=$varenr[$x]." : ".$varenavn[$x];}
	}
	$antal_konti=$x;
	if (!$maaned_fra){$maaned_fra=$md[$start_md[$aktiv]];}
	if (!$maaned_til){$maaned_til=$md[$slut_md[$aktiv]];}
	if (!$varenr_fra){$varenr_fra=$varenr[1]." : ".$varenavn[1];}
	if (!$varenr_til){$varenr_til=$varenr[$antal_konti]." : ".$varenavn[$antal_konti];}

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #A
	print "<tr>";
#	print "<table width=\"100%\" align=\"center\" border=\"10\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=$returside accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Finansrapport - forside</td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
#	print "</tbody></table>"; #B slut
	print "</tr><tr><td height=99%></td><td align=center>";
	print "<form name=regnskabsaar action=rapport.php method=post>";
	print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\">";
	print "<tbody>";
	print "<tr><td align=center colspan=4><h3> Rapporter</font><br></h3></td></tr>";
	print "<td><table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
	print "<tr><td> Regnskabs&aring;r</td><td width=100><select name=regnaar>";
	print "<option>$regnaar - $regn_beskrivelse[$aktiv]</option>";
	for ($x=1; $x<=$antal_regnaar;$x++) {
		if ($x!=$aktiv) {print "<option>$regn_kode[$x] - $regn_beskrivelse[$x]</option>";}
	}
	
	print "</td><td width=100 align=center><input type=submit value=\"Opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
	print "<form name=rapport action=rapport.php method=post>";
	print "<td> Varegruppe </td><td><select name=varegruppe>";
	if ($varegruppe) print "<option>$varegruppe</option>";
	if ($varegruppe!="0:Alle") print "<option>0:Alle</option>";
	$query = db_select("select * from grupper where art = 'VG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		if ($varegruppe!=$row['kodenr'].":".$row['beskrivelse']) {print "<option>$row[kodenr]:$row[beskrivelse]</option>";}
	}
	print "</td></tr>";

	print "<tr><td> Periode</td><td colspan=2><select name=maaned_fra>";
	print "<option>$start_aar[$aktiv] $maaned_fra</option>";
	for ($x=$start_md[$aktiv]; $x <= 12; $x++){
		print "<option>$start_aar[$aktiv] $md[$x]</option>";
	}
	if (($start_md[$aktiv]>1)&&($slut_md[$aktiv]<12))	{
		for ($x=1; $x<=$slut_md[$aktiv]; $x++) {
			print "<option>$slut_aar[$aktiv] $md[$x]</option>";
		}
	}
	print "</td>";
	print "<td colspan=2><select name=maaned_til>";
	print "<option>$slut_aar[$aktiv] $maaned_til</option>";
	for ($x=$start_md[$aktiv]; $x <= 12; $x++) {
		print "<option>$start_aar[$aktiv] $md[$x]</option>";
	}
	if (($start_md[$aktiv]>1)&&($slut_md[$aktiv]<12)) {
		for ($x=1; $x<=$slut_md[$aktiv]; $x++) {
			print "<option>$slut_aar[$aktiv] $md[$x]</option>";
		}
	}
	print "</td></tr>";
	print "<tr><td> Varenr (fra)</td><td colspan=4><select name=varenr_fra>";
	print "<option>$varenr_fra</option>";
	for ($x=1; $x<=$antal_konti; $x++) {
		print "<option>$varenr[$x] : $varenavn[$x]</option>";
	}
	print "</td></tr>";
	print "<tr><td> Varenr (til)</td><td colspan=4><select name=varenr_til>";
	print "<option>$varenr_til</option>";
	for ($x=1; $x<=$antal_konti; $x++) {
		print "<option>$varenr[$x] : $varenavn[$x]</option>";
	}
	print "</td></tr>";
	print "<input type=hidden name=regnaar value=$regnaar>";
	print "<tr><td colspan=5 align=center><input type=submit value=\"  OK  \" name=\"submit\"></td></tr>";
	print "</form>";
	print "</tbody></table>";
	print "<tr><td colspan=3 ALIGN=center><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>\n";
	if ($popup) print "<tr><td colspan=3 ALIGN=center onClick=\"javascript:lagerstatus=window.open('lagerstatus.php','lagerstatus','$jsvars');lagerstatus.focus();\"><span title='Se lagerstatus p&aring; vilk&aring;rlig dato'><input type=submit value=\"Lagerstatus\" name=\"submit\"></span></td></tr>";
	else print "<tr><td colspan=3 ALIGN=center><a href=lagerstatus.php><span title='Se lagerstatus p&aring; vilk&aring;rlig dato'><input type=submit value=\"Lagerstatus\" name=\"submit\"></span></a></td></tr>";
	print "</tbody></table>\n";
	print "</td></tr>";
	
}

##################################################################################################
function varegruppe($regnaar, $maaned_fra, $maaned_til, $varenr_fra, $varenr_til, $varegruppe)
{
#	global $connection;
	global $top_bund;
	global $md;
	global $returside;
	global $jsvars;

	list($gruppenr, $tmp)=split(":",$varegruppe); 

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk
	$currentdate=date("Y-m-d");
	if (strlen($maaned_fra)>2) {
		list ($x, $maaned_fra) = split(" ", $maaned_fra);
		list ($x, $maaned_til) = split(" ", $maaned_til);
		$luk= "<a accesskey=L href=\"rapport.php?varegruppe=$varegruppe&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&varenr_fra=$varenr_fra&varenr_til=$varenr_til\">";
	}
	else $luk= "<a accesskey=L href=\"$returside\">";

 # $maaned_fra=$maaned_fra*1;
 # $maaned_til=$maaned_til*1;

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);
	
	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	print "<tr><td colspan=\"8\" height=\"8\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$luk Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Rapport - varesalg</td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>"; #B slut
	print "</td></tr>";

	for ($x=1; $x<=12; $x++) {
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
		if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
		if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$startmaaned=$row[box1]*1;
	$startaar=$row[box2]*1;
	$slutmaaned=$row[box3]*1;
	$slutaar=$row[box4]*1;
	$slutdato=31;

	if ($maaned_fra) {$startmaaned=$maaned_fra;}
	if ($maaned_til) {$slutmaaned=$maaned_til;}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
	
	while (!checkdate($slutmaaned,$slutdato,$slutaar)) {
		$slutdato=$slutdato-1;
		if ($slutdato<28){break;}
	}

	$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
	#$regnslut = "2005-05-15";
#	print "<tr><td colspan=5>Firmanavn</td></tr>";
	$kontonr=array();
	$x=0;
	$tmp="";
	if ($gruppenr) $tmp="gruppe=$gruppenr and "; 
	$query = db_select("select id from varer where $tmp varenr>='$varenr_fra' and varenr<='$varenr_til' order by varenr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query))
	{
		$x++;
		$vare_id[$x]=$row[id];
	}
	$v_id=array();
	$x=0;
	# finder alle konti med bevaegelser i den anfoerte periode eller aabne poster fra foer perioden
	$query = db_select("select vare_id, pris from batch_salg where fakturadate>='$regnstart' and fakturadate<='$regnslut' order by vare_id",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query))
	{
		if ((in_array(trim($row['vare_id']), $vare_id))&&(!in_array(trim($row['vare_id']), $v_id)))
		{
			$x++;
			$v_id[$x]=trim($row['vare_id']);
		}
	}
	$query = db_select("select vare_id, pris from batch_kob where fakturadate>='$regnstart' and fakturadate<='$regnslut' order by vare_id",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query))
	{
		if ((in_array(trim($row['vare_id']), $vare_id))&&(!in_array(trim($row['vare_id']), $v_id)))
		{
			$x++;
			$v_id[$x]=trim($row['vare_id']);
		}
	}
	$vareantal=$x;
	
#	 print "<tr><td colspan=8><hr></td></tr>";
#	print "<tr><td width=10%> Dato</td><td width=10%> Bilag</td><td width=50%> Tekst</td><td width=10% align=right> Debet</td><td width=10% align=right> Kredit</td><td width=10% align=right> Saldo</td></tr>";

	for ($x=1; $x<=$vareantal; $x++)
	{
		$query = db_select("select * from varer where id=$v_id[$x]",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
#		print "<tr><td colspan=8><hr></td></tr>";
		print "<tr><td colspan=8><hr></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td colspan=3> $row[varenr]</td></tr>";
		print "<tr><td colspan=3> $row[enhed]</td></tr>";
		print "<tr><td colspan=3> $row[beskrivelse]</td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td width=12.5%> Dato</td><td align=right width=12.5%> Antal</td><td align=right width=12.5%> K&oslash;bsordre</td><td align=right width=12.5%> Salgsordre</td><td align=right width=12.5%> K&oslash;bspris</td><td align=right width=12.5%> Salgspris</td><td align=right width=12.5%> DB</td><td align=right width=12.5%> DG</td></tr>";
		print "<tr><td colspan=8><hr></td></tr>";

		$kontosum=0;
		$z=0;
		$kobsliste=array();
		$query = db_select("select * from batch_salg where vare_id=$v_id[$x] and fakturadate<='$regnslut' and fakturadate>='$regnstart' order by fakturadate",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if ($row[ordre_id]) {
				$q1 = db_select("select ordrenr from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
				$r1 = db_fetch_array($q1); 
			}
			if ($row[batch_kob_id]>0) {
				$z++;
				$q2 = db_select("select * from batch_kob where id=$row[batch_kob_id]",__FILE__ . " linje " . __LINE__);
				$r2 = db_fetch_array($q2); 
					if ($r2[ordre_id]) {
					$kobsliste[$z]="$r2[ordre_id]"; 
					$q3 = db_select("select ordrenr from ordrer where id=$r2[ordre_id]",__FILE__ . " linje " . __LINE__);
					$r3 = db_fetch_array($q3); 
				}
			}
			print "<tr><td> ".dkdato($row[fakturadate])."</td><td align=right> $row[antal]</td><td align=right onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$r2[ordre_id]','k_ordre','width=800,height=400,$jsvars')\"> <u>$r3[ordrenr]</u></td><td align=right onClick=\"javascript:k_ordre=window.open('../debitor/ordre.php?id=$row[ordre_id]','k_ordre','width=800,height=400,$jsvars')\"> <u>$r1[ordrenr]</u></td>";
			$kobspris=$r2[pris]*$row['antal'];	 
			$kobssum=$kobssum+$kobspris;
			$tmp=dkdecimal($kobspris);
			print "<td align=right> $tmp</td>";
			$salgspris=$row[pris]*$row['antal'];	 
			$salgssum=$salgssum+$salgspris;
			$tmp=dkdecimal($salgspris);
			print "<td align=right> $tmp</td>";
			$db=$salgspris-$kobspris;
			$tmp=dkdecimal($db);
			print "<td align=right> $tmp</td>";
			if ($salgspris!=0) $dg=$db*100/$salgspris;
			else $dg=0;
			$tmp=dkdecimal($dg);
			print "<td align=right> $tmp</td>";
			$antal=$antal+$row['antal'];
		}

		$query = db_select("select * from batch_kob where vare_id=$v_id[$x] and fakturadate<='$regnslut' and fakturadate>='$regnstart' order by fakturadate",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if ($row[ordre_id]) {
				$q1 = db_select("select ordrenr, art from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
				$r1 = db_fetch_array($q1); 
			}
			if ((strstr($r1['art'],'DK'))&&(!in_array($row['ordre_id'],$kobsliste))) {
			print "<tr><td> ".dkdato($row['fakturadate'])."(KN)</td><td align=right> $row[antal]</td><td align=right onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$r2[ordre_id]','k_ordre','$jsvars')\"> <u>$r2[ordrenr]</u></td><td align=right onClick=\"javascript:k_ordre=window.open('../debitor/ordre.php?id=$row[ordre_id]','k_ordre','$jsvars')\"> <u>$r1[ordrenr]</u></td>";
			$kobspris=$row['pris']*$row['antal'];	 
			$kobssum=$kobssum+$kobspris;
			$tmp=dkdecimal($kobspris);
			print "<td align=right> $tmp</td>";
			$salgspris=0;	 
			$salgssum=$salgssum+$salgspris;
			$tmp=dkdecimal($salgspris);
			print "<td align=right> $tmp</td>";
			$db=$salgspris-$kobspris;
			$tmp=dkdecimal($db);
			print "<td align=right> $tmp</td>";
			if ($salgspris!=0) {$dg=$db*100/$salgspris;}
			else {$dg=100;}
			$tmp=dkdecimal($dg);
			print "<td align=right> $tmp</td>";
			$antal=$antal+$row['antal'];
			}
		}

		print "<tr><td colspan=8><hr></td></tr>";
		print "<tr><td></td>";
		$tmp=dkdecimal($antal);
		print "<td align=right> $tmp</td><td></td><td></td>";
		$tmp=dkdecimal($kobssum);
		print "<td align=right> $tmp</td>";
		$tmp=dkdecimal($salgssum);
		print "<td align=right> $tmp</td>";
		$db=$salgssum-$kobssum;
		$tmp=dkdecimal($db);
		print "<td align=right> $tmp</td>";
		if ($salgssum!=0) {$dg=$db*100/$salgssum;}
		else {$dg=100;}
		$tmp=dkdecimal($dg);
		print "<td align=right> $tmp</td></tr>";

		$totalkob=$totalkob+$kobssum;
		$totalsalg=$totalsalg+$salgssum;

		$antal=0;
		$kobssum=0;
		$salgssum=0;

	}
	print "<tr><td colspan=8><hr></td></tr>";
	print "<tr><td></td>";
	print "<td align=right> </td><td></td><td></td>";
	$tmp=dkdecimal($totalkob);
	print "<td align=right> <b>$tmp</b></td>";
	$tmp=dkdecimal($totalsalg);
	print "<td align=right> <b>$tmp</b></td>";
	$db=$totalsalg-$totalkob;
	$tmp=dkdecimal($db);
	print "<td align=right> <b>$tmp</b></td>";
	if ($totalsalg!=0) {$dg=$db*100/$totalsalg;}
	else {$dg=100;}
	$tmp=dkdecimal($dg);
	print "<td align=right> <b>$tmp</b></td></tr>";

	print "<tr><td colspan=8><hr></td></tr>";

	print "</tbody></table>";
}
#############################################################################################################

?>
</html>

