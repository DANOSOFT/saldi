<?php

// -------------produktion/rapport.php-------lap 1.9.3a-----2008-04-16-------
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
// Copyright (c) 2004-2005 DANOSOFT ApS
// ----------------------------------------------------------------------


	@session_start();
	$s_id=session_id();
 
	$modulnr=12;
 
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/usdate.php");
	include("../includes/dkdato.php");
	include("../includes/dkdecimal.php");
#	include("../includes/db_query.php");

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head>
	<title>Kreditorrapport</title>
	<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">

</head><body text="#000000" bgcolor="#edede2" link="#000099" vlink="#990099" alink="#000099"><center>

<br>
<?php
if ($_POST['submit'])
{
	$submit=strtolower(trim($_POST['submit']));
	$rapportart=strtolower(trim($_POST['rapportart']));
	$maaned_fra=$_POST['maaned_fra'];
	$maaned_til=$_POST['maaned_til'];
	$md=$_POST['md'];
	list ($konto_fra, $firmanavn) = split(":", $_POST['konto_fra']);
	list ($konto_til, $firmanavn) = split(":", $_POST['konto_til']);
	list ($regnaar, $firmanavn)= split("-", $_POST['regnaar']);
	
	$konto_fra = trim($konto_fra);
	$konto_til = trim($konto_til);
	$firmanavn = trim($firmanavn);
	
	
}
else
{
	$rapportart=$_GET['rapportart'];
	$maaned_fra=$_GET['maaned_fra'];
	$maaned_til=$_GET['maaned_til'];
	$konto_fra=$_GET['konto_fra'];
	$konto_til=$_GET['konto_til'];
	$regnaar=$_GET['regnaar'];
}
$md[1]="januar"; $md[2]="februar"; $md[3]="marts"; $md[4]="april"; $md[5]="maj"; $md[6]="juni"; $md[7]="juli"; $md[8]="august"; $md[9]="september"; $md[10]="oktober"; $md[11]="november"; $md[12]="december";

if (strstr($rapportart, "ben post")) {$rapportart="openpost";}
if ($submit != 'ok') {$submit='forside';}
else {if ($rapportart){$submit=$rapportart;}}

$submit($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart);
#############################################################################################################
function forside($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart)
{

	#global $connection;
	global $brugernavn;
	global $font;
	global $md;

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal v�e numerisk
	$konto_fra=$konto_fra*1;
	$konto_til=$konto_til*1;

	print "$font <a accesskey=h href=\"../includes/luk.php\">Hovedmenu</a><br><br>";

	if (!$regnaar)
	{
		$query = db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'");
		$row = db_fetch_array($query);
		$regnaar = $row['regnskabsaar'];
	}
	$query = db_select("select * from grupper where art = 'RA' order by box2");
	$x=0;
	while ($row = db_fetch_array($query))
	{
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
	while ($row = db_fetch_array($query))
	{
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

	print "<td>$font Rapporttype</td><td><select name=rapportart>";
	if ($rapportart) {print "<option>$rapportart</option>";}
	if ($rapportart!="Kontokort") {print "<option>Kontokort</option>";}
	if ($rapportart!="Regnskab") {print "<option>&Aring;ben post</option>";}
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
	print "<tr><td colspan=5 align=center><input type=submit value=\" OK	\" name=\"submit\"></td></tr>";
	print "</form>";
	print "</tbody></table>";

}
# endfunc statistik
#############################################################################################################
function kontokort($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til)
{
#	global $connection;
	global $font;
	global $md;

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

	list ($x, $maaned_fra) = split(" ", $maaned_fra);
	list ($x, $maaned_til) = split(" ", $maaned_til);

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);

	print "$font <a accesskey=K href=\"rapport.php?rapportart=Kontokort&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til\"><small><small>Kontoudtog</small></small></a><br><br>";

	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
#	print "<tr><td colspan=\"4\"><big><big><big><span style=\"font-family: helvetica,arial,sans-serif;\">Kontokort</span></big></big></big></td>";

#	print "<td colspan=2 align=right><table style=\"text-align: left; width: 100%;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
#	print "<td><small><small><span style=\"font-family: helvetica,arial,sans-serif;\">Regnskabsaar</span></small></small></td>";
#	print "<td><small><small><span style=\"font-family: helvetica,arial,sans-serif;\">$regnaar</span></small></small></td></tr>";
#	print "<tr><td><small><small><span style=\"font-family: helvetica,arial,sans-serif;\">Periode</span></small></small></td>";

	## Finder start og slut paa regnskabsaar

#	print "<td><small><small><span style=\"font-family: helvetica,arial,sans-serif;\">$maaned_fra - $maaned_til</span></small></small></td></tr>";
#	print "</tbody></table></td></tr>";

	for ($x=1; $x<=12; $x++)
	{
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
		if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
		if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'");
	$row = db_fetch_array($query);
#	$regnaar=$row[kodenr];
	$startmaaned=$row[box1]*1;
	$startaar=$row[box2]*1;
	$slutmaaned=$row[box3]*1;
	$slutaar=$row[box4]*1;
	$slutdato=31;

	##

	
	if ($maaned_fra) {$startmaaned=$maaned_fra;}
	if ($maaned_til) {$slutmaaned=$maaned_til;}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
	
	while (!checkdate($slutmaaned,$slutdato,$slutaar))
	{
		$slutdato=$slutdato-1;
		if ($slutdato<28){break;}
	}

	$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

	
#	print "<tr><td colspan=5>Firmanavn</td></tr>";
	$kontonr=array();
	$x=0;
	$query = db_select("select	id from adresser where kontonr>='$konto_fra' and kontonr<='$konto_til' and art = 'K' order by kontonr");
	while ($row = db_fetch_array($query))
	{
		$x++;
		$konto_id[$x]=$row[id];
	}
	$kto_id=array();
	$x=0;
	# finder alle konti med bev�elser i den anfoerte periode eller aabne poster fra foer perioden
	$query = db_select("select konto_id, amount from openpost where (transdate>='$regnstart' and transdate<='$regnslut') or (transdate<'$regnstart' and udlignet=0)");
	while ($row = db_fetch_array($query))
	{
		if ((in_array(trim($row['konto_id']), $konto_id))&&(!in_array(trim($row['konto_id']), $kto_id)))
		{
			$x++;
			$kto_id[$x]=trim($row['konto_id']);
		}
	}
	$kontoantal=$x;
	
	 print "<tr><td colspan=6><hr></td></tr>";
#	print "<tr><td width=10%><small><small>Dato</td><td width=10%><small><small>Bilag</small></small></td><td width=50%><small><small>Tekst</small></small></td><td width=10% align=right><small><small>Debet</small></small></td><td width=10% align=right><small><small>Kredit</small></small></td><td width=10% align=right><small><small>Saldo</small></small></td></tr>";

	for ($x=1; $x<=$kontoantal; $x++)
	{
		$query = db_select("select	* from adresser where id=$kto_id[$x]");
		$row = db_fetch_array($query);
		print "<tr><td colspan=6><hr></td></tr>";
		print "<tr><td colspan=6><hr></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td colspan=3><small><small>$row[firmanavn]</small></small></td></tr>";
		print "<tr><td colspan=3><small><small>$row[addr1]</small></small></td></tr>";
		print "<tr><td colspan=3><small><small>$row[addr2]</small></small></td></tr>";
		print "<tr><td colspan=3><small><small>$row[postnr] $row[bynavn]</small></small></td><td colspan=3 align=right><small><small>Dato ".date('d-m-Y')."</small></small></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td width=10%><small><small>Dato</td><td width=10%><small><small>Faktura</small></small></td><td width=50%><small><small>Tekst</small></small></td><td width=10% align=right><small><small>Debet</small></small></td><td width=10% align=right><small><small>Kredit</small></small></td><td width=10% align=right><small><small>Saldo</small></small></td></tr>";
		print "<tr><td colspan=6><hr></td></tr>";
		
		$kontosum=0;
		$primo=0;
		$primoprint=0;
		$query = db_select("select * from openpost where konto_id=$kto_id[$x] and ((transdate>='$regnstart' and transdate<='$regnslut') or (transdate<'$regnstart' and udlignet=0)) order by transdate");
		while ($row = db_fetch_array($query))
		{
			 if ($row[transdate]<$regnstart) 
			 {
				 $primoprint=0;
				 $kontosum=$kontosum+$row[amount];
			 }
			 else
			 { 
				if ($primoprint==0)
				{
					$tmp=dkdecimal($kontosum);
					print "<tr><td></td><td></td><td><small><small>Primosaldo</small></small></td><td></td><td></td><td align=right><small><small>$tmp</small></small></td></tr>";
					$primoprint=1;
				}
				print "<tr><td><small><small>".dkdato($row[transdate])."</small></small></td><td><small><small>$row[faktnr]</small></small></td><td><small><small>$row[text]</small></small></td>";
				$tmp=dkdecimal($row[amount]);
				if ($tmp > 0) {print "<td align=right><small><small>$tmp</small></small></td><td></td>";}
				else {print "<td></td><td align=right><small><small>$tmp</small></small></td>";}
				$kontosum=$kontosum+$row[amount];
				$tmp=dkdecimal($kontosum);
				print "<td align=right><small><small>$tmp</small></small></td></tr>";
			}
		}
		if ($primoprint==0)
		{
			$tmp=dkdecimal($kontosum);
			print "<tr><td></td><td></td><td><small><small>Primosaldo</small></small></td><td></td><td></td><td align=right><small><small>$tmp</small></small></td></tr>";
		}
	}
	print "<tr><td colspan=6><hr></td></tr>";
	print "</tbody></table>";
}
#############################################################################################################
function openpost($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til)
{
	global $font;
	global $md;

#echo "$regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til";

	list ($x, $maaned_fra) = split(" ", $maaned_fra);
	list ($x, $maaned_til) = split(" ", $maaned_til);
	
	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);

	print "$font <a accesskey=A href=\"rapport.php?rapportart=openpost&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til\">&Aring;bne poster</a><br><br>";

	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";


	## Finder start og slut paa�regnskabsaar�


	for ($x=1; $x<=12; $x++)
	{
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'");
	$row = db_fetch_array($query);
#	$regnaar=$row[kodenr];
	$startmaaned=$row[box1]*1;
	$startaar=$row[box2]*1;
	$slutmaaned=$row[box3]*1;
	$slutaar=$row[box4]*1;
	$slutdato=31;

	##

	if ($maaned_fra) {$startmaaned=$maaned_fra;}
	if ($maaned_til) {$slutmaaned=$maaned_til;}

		while (!checkdate($slutmaaned,$slutdato,$slutaar))
	{
		$slutdato=$slutdato-1;
		if ($slutdato<28){break;}
	}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}

	$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

#	echo "$maaned_fra -$maaned_til<br>";

	print "<tr><td colspan=6><hr></td></tr>";

		$x=0;
	$query = db_select("select * from adresser where kontonr>='$konto_fra' and kontonr<='$konto_til' and art = 'K' order by firmanavn");
	while ($row = db_fetch_array($query))
	{
		$x++;
		$id[$x]=$row[id];
#		echo "$id[$x]<br>";
		$kontonr[$x]=trim($row['kontonr']);
		$firmanavn[$x]=trim($row['firmanavn']);
		$addr1[$x]=trim($row['addr1']);
		$addr2[$x]=trim($row['addr2']);
		$postnr[$x]=trim($row['postnr']);
		$bynavn[$x]=trim($row['bynavn']);
	}
	$kontoantal=$x;

	$sum=0;
	for ($x=1; $x<=$kontoantal; $x++)
	{
		$y=0;
		 $query = db_select("select * from openpost where konto_id=$id[$x] and udlignet = 0");
		while ($row = db_fetch_array($query)) {$y=$y+$row[amount];}
		if ($y!=0)			
		{	
			$sum=$sum+$y;
			print "<tr><td>$font<small><small>$kontonr[$x]</small></small></td>";
			print "<td colspan=3>$font<small><small>$firmanavn[$x]</small></small></td>";
			$tmp=dkdecimal($y);
			print "<td align=right>$font<small><small>$tmp</small></small></td></tr>";
		}
	}
	$tmp=dkdecimal($sum);
	print "<tr><td colspan=5><hr></td></tr>";
	print "<tr><td><br></td><td	colspan=3>I alt</td>";
	print "<td align=right>$font<small><small>$tmp</small></small></td></tr>";
	print "<tr><td colspan=5><hr></td></tr>";
 }

?>
</html>

