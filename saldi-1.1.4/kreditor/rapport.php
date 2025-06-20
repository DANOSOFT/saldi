<?php

// -----------------------------------/kreditor/rapport.php---lap 1.1.2--20070810-----------------
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
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------
	

@session_start();
$s_id=session_id();
$title='Kreditorrapport';

$modulnr=12;
 
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/usdate.php");
include("../includes/dkdato.php");
include("../includes/forfaldsdag.php");
include("../includes/dkdecimal.php");
include("../includes/autoudlign.php");
include("../includes/openpost.php");

if ($_POST['submit']) {
	$submit=strtolower(trim($_POST['submit']));
	$rapportart=strtolower(trim($_POST['rapportart']));
	$maaned_fra=$_POST['maaned_fra'];
	$maaned_til=$_POST['maaned_til'];
	$md=$_POST['md'];
	list ($konto_fra, $firmanavn) = split(":", $_POST['konto_fra']);
	list ($konto_til, $firmanavn) = split(":",	 $_POST['konto_til']);
	list ($regnaar, $firmanavn)= split("-", $_POST['regnaar']);
	
	$konto_fra = trim($konto_fra);
	$konto_til = trim($konto_til);
	$firmanavn = trim($firmanavn);
	
	
} else {
	$rapportart=$_GET['rapportart'];
	$maaned_fra=$_GET['maaned_fra'];
	$maaned_til=$_GET['maaned_til'];
	$konto_fra=$_GET['konto_fra'];
	$konto_til=$_GET['konto_til'];
	$regnaar=$_GET['regnaar'];
	$submit=$_GET['submit'];
	if ($udlign=$_GET['udlign']) autoudlign($udlign);

}
$md[1]="januar"; $md[2]="februar"; $md[3]="marts"; $md[4]="april"; $md[5]="maj"; $md[6]="juni"; $md[7]="juli"; $md[8]="august"; $md[9]="september"; $md[10]="oktober"; $md[11]="november"; $md[12]="december";

if (strstr($rapportart, "ben post")) {$rapportart="openpost";}
if ($submit != 'ok') {$submit='forside';}
else {if ($rapportart){$submit=$rapportart;}}

#echo "$submit($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart)";

$submit($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart, 'K');
#############################################################################################################
function forside($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart)
{

	#global $connection;
	global $brugernavn;
	global $font;
	global $top_bund;
	global $md;

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal v�e numerisk
	$konto_fra=$konto_fra*1;
	$konto_til=$konto_til*1;

	if (!$regnaar)	{
		$query = db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'");
		$row = db_fetch_array($query);
		$regnaar = $row['regnskabsaar'];
	}
	$query = db_select("select * from grupper where art = 'RA' order by box2");
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$regnaar_id[$x]=$row[id];
		$regn_beskrivelse[$x]=$row['beskrivelse'];
		$start_md[$x]=$row['box1']*1;
		$start_aar[$x]=$row['box2']*1;
		$slut_md[$x]=$row['box3']*1;
		$slut_aar[$x]=$row['box4']*1;
		$regn_kode[$x]=$row[kodenr];
		if ($regnaar==$row[kodenr]){$aktiv=$x;}
	}
	$antal_regnaar=$x;

		$query = db_select("select * from adresser where art = 'K' order by kontonr");
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$konto_id[$x]=$row[id];
		$kontonr[$x]=$row['kontonr'];
		$firmanavn[$x]=$row['firmanavn'];
		if ($kontonr[$x]==$konto_fra){$konto_fra=$kontonr[$x]." : ".$firmanavn[$x];}
		if ($kontonr[$x]==$konto_til){$konto_til=$kontonr[$x]." : ".$firmanavn[$x];}
	}
	$antal_konti=$x;
	if (!$maaned_fra){$maaned_fra=$md[$start_md[$aktiv]];}
	if (!$maaned_til){$maaned_til=$md[$slut_md[$aktiv]];}
	if (!$konto_fra){$konto_fra=$kontonr[1]." : ".$firmanavn[1];}
	if (!$konto_til){$konto_til=$kontonr[$antal_konti]." : ".$firmanavn[$antal_konti];}

	print "<table cellpadding=\"1\" cellspacing=\"3\" border=\"0\" width=100% height=100% valign=\"top\"><tbody>";
	print "<tr><td height=\"8\" width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Kreditorrapport</small></td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tr><tr><td height=99%><br></td><td align=center>";
	print "<form name=regnskabsaar action=rapport.php method=post>";
	print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\">";
	print "<tbody>";
	print "<tr><td align=center colspan=4><h3>$font Rapporter</font><br></h3></td></tr>";
	print "<td><table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
	print "<tr><td>$font Regnskabs&aring;r</td><td width=100><select name=regnaar>";
	print "<option>$regnaar - $regn_beskrivelse[$aktiv]</option>";
	for ($x=1; $x<=$antal_regnaar;$x++)
	{
		if ($x!=$aktiv) {print "<option>$regn_kode[$x] - $regn_beskrivelse[$x]</option>";}
	}

	print "</td><td width=100 align=center><input type=submit value=\"Opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
	print "<form name=rapport action=rapport.php method=post>";

	print "<td>$font Rapport art</td><td><select name=rapportart>";
	if ($rapportart=="openpost") {print "<option>&Aring;ben post</option>";}
	elseif ($rapportart) {print "<option>$rapportart</option>";}
	if ($rapportart!="openpost") {print "<option>&Aring;ben post</option>";}
	if ($rapportart!="Kontokort") {print "<option>Kontokort</option>";}
	print "</td></tr>";

	print "<tr><td>$font Periode</td><td colspan=2><select name=maaned_fra>";
	print "<option>$start_aar[$aktiv] $maaned_fra</option>";
	for ($x=$start_md[$aktiv]; $x <= 12; $x++)
	{
		print "<option>$start_aar[$aktiv] $md[$x]</option>";
	}
	if (($start_md[$aktiv]>1)&&($slut_md[$aktiv]<12))
	{
		for ($x=1; $x<=$slut_md[$aktiv]; $x++)
		{
			print "<option>$slut_aar[$aktiv] $md[$x]</option>";
		}
	}
	print "</td>";
	print "<td colspan=2><select name=maaned_til>";
	print "<option>$slut_aar[$aktiv] $maaned_til</option>";
	for ($x=$start_md[$aktiv]; $x <= 12; $x++)
	{
		print "<option>$start_aar[$aktiv] $md[$x]</option>";
	}
	if (($start_md[$aktiv]>1)&&($slut_md[$aktiv]<12))
	{
		for ($x=1; $x<=$slut_md[$aktiv]; $x++)
		{
			print "<option>$slut_aar[$aktiv] $md[$x]</option>";
		}
	}
	print "</td></tr>";
	print "<tr><td>$font Konto (fra)</td><td colspan=4><select name=konto_fra>";
	print "<option>$konto_fra</option>";
	for ($x=1; $x<=$antal_konti; $x++)
	{
		print "<option>$kontonr[$x] : $firmanavn[$x]</option>";
	}
	print "</td></tr>";
	print "<tr><td>$font Konto (til)</td><td colspan=4><select name=konto_til>";
	print "<option>$konto_til</option>";
	for ($x=1; $x<=$antal_konti; $x++)
	{
		print "<option>$kontonr[$x] : $firmanavn[$x]</option>";
	}
	print "</td></tr>";
	print "<input type=hidden name=regnaar value=$regnaar>";
	print "<tr><td colspan=5 align=center><input type=submit value=\"  OK  \" name=\"submit\"></td></tr>";
	print "</form>";
	print "</tbody></table>";
	print "<td><br></td></tr>";
	print "</tbody></table>";
}
#############################################################################################################
function kontokort($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til)
{
#	global $connection;
	global $font;
	global $top_bund;
	global $md;
 
	$currentdate=date("Y-m-d");
	
	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

	if (strlen($maaned_fra)>2) {
		list ($x, $maaned_fra) = split(" ", $maaned_fra);
		list ($x, $maaned_til) = split(" ", $maaned_til);
		$luk= "<a accesskey=L href=\"rapport.php?rapportart=Kontokort&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til\">";
	}
	else $luk= "<a accesskey=L href=\"../includes/luk.php\">";

 # $maaned_fra=$maaned_fra*1;
 # $maaned_til=$maaned_til*1;

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);

	
	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	print "<tr><td colspan=\"8\" height=\"8\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>$luk Luk</a></small></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Kreditorrapport - kontokort</small></td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>"; #B slut
	print "</td></tr>";

	for ($x=1; $x<=12; $x++) {
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
		if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
		if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'");
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
		if ($slutdato<28) break;
	}

	$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
	#$regnslut = "2005-05-15";
#	print "<tr><td colspan=5>Firmanavn</td></tr>";
	$kontonr=array();
	$x=0;
	$query = db_select("select id from adresser where kontonr>='$konto_fra' and kontonr<='$konto_til' and art = 'K' order by kontonr");
	while ($row = db_fetch_array($query)) {
		$x++;
		$konto_id[$x]=$row[id];
	}
	$kto_id=array();
	$x=0;
	# finder alle konti med bevaegelser i den anfoerte periode eller aabne poster fra foer perioden
	$query = db_select("select konto_id, amount from openpost where transdate<='$regnslut' order by konto_nr");
	while ($row = db_fetch_array($query)) {
		if ((in_array(trim($row['konto_id']), $konto_id))&&(!in_array(trim($row['konto_id']), $kto_id))) {
			$x++;
			$kto_id[$x]=trim($row['konto_id']);
		}
	}
	$kontoantal=$x;
	
#	 print "<tr><td colspan=8><hr></td></tr>";
#	print "<tr><td width=10%>$font <small>Dato</td><td width=10%>$font <small>Bilag</small></td><td width=50%>$font <small>Tekst</small></td><td width=10% align=right>$font <small>Debet</small></td><td width=10% align=right>$font <small>Kredit</small></td><td width=10% align=right>$font <small>Saldo</small></td></tr>";

	for ($x=1; $x<=$kontoantal; $x++) {
		$query = db_select("select	* from adresser where id=$kto_id[$x]");
		$row = db_fetch_array($query);
#		print "<tr><td colspan=8><hr></td></tr>";
		print "<tr><td colspan=8><hr></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td colspan=3>$font <small>$row[firmanavn]</small></td></tr>";
		print "<tr><td colspan=3>$font <small>$row[addr1]</small></td></tr>";
		print "<tr><td colspan=3>$font <small>$row[addr2]</small></td><td colspan=3 align=right>$font <small>Kontonr</small></td><td align=right>$font <small>$row[kontonr]</small></td></tr>";
		print "<tr><td colspan=3>$font <small>$row[postnr] $row[bynavn]</small></td><td colspan=3 align=right>$font <small>Dato</small></td><td align=right>$font <small>".date('d-m-Y')."</small></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td width=10%>$font <small>Dato</td><td width=5%>$font <small>Bilag</small></td><td width=5%>$font <small>Faktura</small></td><td width=40%>$font <small>Tekst</small></td><td>$font <small>Forfaldsdato</small></td><td width=10% align=right>$font <small>Debet</small></td><td width=10% align=right>$font <small>Kredit</small></td><td width=10% align=right>$font <small>Saldo</small></td></tr>";
		print "<tr><td colspan=8><hr></td></tr>";
		$betalingsbet=trim($row[betalingsbet]);
		$betalingsdage=$row[betalingsdage];
				
		$kontosum=0;
		$primo=0;
		$primoprint=0;
		$query = db_select("select * from openpost where konto_id=$kto_id[$x] and transdate<='$regnslut' order by transdate");
		while ($row = db_fetch_array($query)) {
			 if ($row[transdate]<$regnstart) {
				 $primoprint=0;
				 $kontosum=$kontosum+$row[amount];
			 } else { 
				if ($primoprint==0) {
					$tmp=dkdecimal($kontosum);
					print "<tr><td></td><td><td></td></td><td>$font <small>Primosaldo</small></td><td></td><td></td><td></td><td align=right>$font <small>$tmp</small></td></tr>";
					$primoprint=1;
				}
				print "<tr><td>$font <small>".dkdato($row[transdate])."</small></td><td>$font <small>$row[refnr]</small></td><td>$font <small>$row[faktnr]</small></td><td>$font <small>$row[text]</small></td>";
 #			 <td>$font <small>".forfaldsdag($row[transdate], $betalingsbet, $betalingsdage)."</small></td>";
				if ($row[amount] < 0) {$tmp=0-$row[amount];}
				else {$tmp=$row[amount];}
				$tmp=dkdecimal($tmp);
					$forfaldsdag=usdate(forfaldsdag($row[transdate], $betalingsbet, $betalingsdage));
				if (($row[udlignet]!='1')&&($forfaldsdag<$currentdate)){$stil="<span style='color: rgb(255, 0, 0);'>";}
				else {$stil="<span style='color: rgb(0, 0, 0);'>";}
				if ($row[amount] < 0) {	
					if (($row[udlignet]!='1')&&($forfaldsdag<$currentdate)) print "<td><span style='color: rgb(255, 0, 0);'>$font <small>".dkdato($forfaldsdag)."</small></td><td></td><td align=right><span style='color: rgb(255, 0, 0);'>$font <small><a href=\"../includes/udlign_openpost.php?post_id=$row[id]&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&retur=../kreditor/rapport.php\">$tmp</a></small></td>";
					else print "<td><span style='color: rgb(0, 0, 0);'>$font <small>".dkdato($forfaldsdag)."</small></td><td></td><td align=right><span style='color: rgb(0, 0, 0);'>$font <small>$tmp</small></td>";
					$forfaldsum=$forfaldsum+$row[amount];

#					print "<td></td><td align=right>$stil$font <small>$tmp</small><td></td></td>";
#					$forfaldsum=$forfaldsum+$row[amount];
				} else {print "</td><td><td align=right>$font <small>$tmp</small></td><td></td>";}
				$kontosum=$kontosum+$row[amount];
				$tmp=dkdecimal($kontosum);
				print "<td align=right>$font <small>$tmp</small></td></tr>";
			}
		}
		if ($primoprint==0) {
			$tmp=dkdecimal($kontosum);
			print "<tr><td></td><td></td><td></td><td>$font <small>Primosaldo</small></td><td></td><td></td><td></td><td align=right>$font <small>$tmp</small></td></tr>";
		}
	}
	print "<tr><td colspan=8><hr></td></tr>";
	print "</tbody></table>";
}
?>
</html>

