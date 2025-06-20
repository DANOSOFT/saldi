<?php
// ----------------------------- /debitor/serienummer.php-----------lap 1.1.0------------------------------
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

$batch_kob_id=$_GET['batch_kob_id'];
$linje_id=$_GET['linje_id'];

$title="serienummer";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/db_query.php");

if ($_POST['submit']) {
	$submit=trim($_POST['submit']);
	$vare_id=$_POST['vare_id'];
	$kred_linje_id=$_POST['kred_linje_id'];
	$ordre_id=$_POST['ordre_id'];
	$antal=$_POST['antal'];
	$leveres=$_POST['leveres'];
	$serienr=$_POST["serienr"];
	$sn_id=$_POST['sn_id'];
	$sn_antal=$_POST['sn_antal'];
	$sn_tjek=$_POST['sn_tjek'];
	$valg=$_POST['valg'];
	$art=trim($_POST['art']);

	if (!$sn_tjek) $sn_tjek=array();
	if ($_POST['status']<3)	{
		$y=0;
		if ($antal[$x]>0) {
			for ($x=1; $x<=$sn_antal; $x++) {
				if (trim($valg[$x])=="on") {
					$y++;
					db_modify("update serienr set salgslinje_id='$linje_id' where id=$sn_id[$x]");
				} elseif ($sn_id[$x]) db_modify("update serienr set salgslinje_id=0 where id=$sn_id[$x]");
			}
		} else {
			for ($x=1; $x<=$sn_antal; $x++) {
				if (trim($valg[$x])=="on") {
					$y++;
					db_modify("update serienr set salgslinje_id=$kred_linje_id*-1 where id=$sn_id[$x]");
				} elseif ($sn_id[$x]) db_modify("update serienr set salgslinje_id=$kred_linje_id where id=$sn_id[$x]");
			}
		}
	}
}
if ($submit=="Luk") print "<body onload=\"javascript:window.close();\">";

$antal=0;
$query = db_select("select * from ordrelinjer where id = '$linje_id'");
if ($row = db_fetch_array($query)) {
	$ordre_id=$row[ordre_id];
	$antal=$row[antal];
	$leveres=$row[leveres];
	$posnr=$row[posnr];
	$vare_id=$row[vare_id];
	$varenr=$row['varenr'];
	$kred_linje_id=$row[kred_linje_id];

	$query = db_select("select status, art from ordrer where id = '$ordre_id'");
	$row = db_fetch_array($query);
	$status=$row[status];
	$art=$row[art];
}

print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"1\" valign = \"top\" align=\"center\"><tbody>";
print "<tr><td align=center>$font<big><b>Posnr: $posnr - Varenr: $varenr</td></tr>";
print "<form name=ordre serienr.php?linje_id=$linje_id method=post>";
print "<tr><td><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\" align=\"center\" width=\"100%\"><tbody>";
print "<tr><td>$font<small><b>Serienr.</td><td align=right>$font<small><b>K&oslash;bsordre</td></tr>";
print "<tr><td colspan=2><hr></td></tr>";
if ($antal>0) {	
	$sn_antal=0;
	$query = db_select("select * from serienr where vare_id=$vare_id and batch_salg_id > 0 and salgslinje_id=$linje_id order by serienr");
	while ($row = db_fetch_array($query)) {
		$kobsordre=db_fetch_array(db_select("select ordre_id from ordrelinjer where id=$row[kobslinje_id]"));
		$kobsordre=$kobsordre[ordre_id];
		
		$sn_antal++;
		print "<tr><td>$row[serienr]</td><td onClick=\"window.open('../kreditor/ordre.php?id=$kobsordre')\"; align=right>$kobsordre</td></tr>";
	}
	if ($sn_antal-$antal<0)	{
		$gem=1;
		$query = db_select("select * from serienr where batch_kob_id=$batch_kob_id and vare_id=$vare_id and batch_salg_id < 1 and (salgslinje_id=0 or salgslinje_id=$linje_id) order by serienr");
		while ($row = db_fetch_array($query)) {
			if ($status < 3) {
				$sn_antal++;
				$tmp='';
				if ($row[salgslinje_id]>0) $tmp= "checked";
				print "<tr><td>$row[serienr]</td><td><input type=checkbox name=valg[$sn_antal] $tmp></td></tr> ";
				print "<input type=hidden name=sn_id[$sn_antal] value=$row[id]>";
				print "<input type=hidden name=serienr[$sn_antal] value='$row[serienr]'>";
			} else {
				if ($row[salgslinje_id]>0){print "<tr><td>$row[serienr]</td></tr>";}
			}
		}
	}
} else { #Varer tages retur - evt kreditnota;
	$sn_antal=0;
	$query = db_select("select * from serienr where salgslinje_id=$kred_linje_id or salgslinje_id=$kred_linje_id*-1 order by serienr");
	while ($row = db_fetch_array($query)) {
			$r2 =db_fetch_array(db_select("select id from serienr where salgslinje_id=$linje_id and serienr='$row[serienr]' order by id desc"));
			if (($status < 3)&&(!$r2[id])) {
			$sn_antal++;
			$tmp='';
			if ($row[salgslinje_id]<0) $tmp= "checked";
			print "<tr><td>$row[serienr]</td><td><input type=checkbox name=valg[$sn_antal] $tmp></td></tr> ";
			print "<input type=hidden name=sn_id[$sn_antal] value=$row[id]>";
			print "<input type=hidden name=serienr[$sn_antal] value='$row[serienr]'>";
		} else {
			if ($row[salgslinje_id]<0){print "<tr><td>$row[serienr]</td></tr>";}
		}
	}
}

print "</td></tr>";
print "<input type=hidden name=antal value='$antal'>";
print "<input type=hidden name=kred_linje_id value='$kred_linje_id'>";
print "<input type=hidden name=vare_id value='$vare_id'>";
print "<input type=hidden name=ordre_id value='$ordre_id'>";
print "<input type=hidden name=sn_antal value='$sn_antal'>";
print "<input type=hidden name=leveres value='$leveres'>";
print "<input type=hidden name=status value='$status'>";
print "<input type=hidden name=art value='$art'>";
print "</tbody></table>";
print "<tr><td align=center><input type=submit value=\"Luk\" name=\"submit\"></td></tr>";
print "</form> </tr>";
print "</td></tr></tbody></table>";
print "</form>";

?>
