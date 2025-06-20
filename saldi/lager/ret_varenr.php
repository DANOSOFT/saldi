<?php
// --------------------------------------------------lager/ret_varenr.php-------------patch 1.0.8----------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();

$title="Ret varenummer";
$modulnr=9;

include("../includes/connect.php");
include("../includes/online.php");

if (isset($_GET['id'])) $id = $_GET['id'];
elseif(isset($_POST['id'])) {
	$id = $_POST['id'];
	$varenr = $_POST['varenr'];
	$nyt_varenr = $_POST['nyt_varenr'];
}



print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small><a href=varekort.php?id=$id accesskey=T>Tilbage</a></small></td>";
print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small>$title</small></td>";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\"></td></tr>";
print "</tbody></table>";
print "</td></tr>\n";
print "<tr><td>\n";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=100% valign = \"center\" align = \"center\"><tbody>\n";

if (($nyt_varenr)&&($nyt_varenr!=$varenr)) {
	if ($r=db_fetch_array(db_select("select id from varer where varenr = '$nyt_varenr'"))) {
		print "<BODY onLoad=\"javascript:alert('Varenummer: $nyt_varenr er i brug, varenummer ikke &aelig;ndret')\">";
	}
	else {
		db_modify("update varer set varenr='$nyt_varenr' where id='$id'");
		$x=0;
		$q=db_select("select ordrelinjer.id as ordrelinje_id, ordrer.art as art, ordrer.ordrenr as ordrenr from ordrelinjer, ordrer where ordrer.status<3 and ordrelinjer.ordre_id = ordrer.id and ordrelinjer.vare_id = '$id'");
		while ($r=db_fetch_array($q)) {
			$x++;
			db_modify("update ordrelinjer set varenr='$nyt_varenr' where id='$r[ordrelinje_id]'");
			if ($x==1) echo "<tr><td><small>Varenummer rettet i f&oslash;lgende ordrer: $r[ordrenr]</small>";
			else echo "<small>, $r[ordrenr]</small>";
		}
		if ($x>=1)echo "</td></tr><tr><td><hr></td></tr>";
		print "<BODY onLoad=\"javascript:alert('Varenummer er rettet fra $varenr til $nyt_varenr')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id\">";

	}
}

if ($r=db_fetch_array(db_select("select varenr from varer where id = '$id'"))) $varenr=$r['varenr'];

print "<form name=ret_varenr action=ret_varenr.php method=post>"
;
print "<tr><td align=center>$font<small>Varenummer rettes i alle uafsluttede ordrer, tilbud, indk&oslash;bsforslag og indk&oslash;bsordrer</small></td></tr>";
print "<tr><td align=center>$font<small>Bem&aelig;rk at hvis der er brugere som er ved at redigere en ordre kan dette bevirke at varenummeret ikke &aelig;ndres</small></td></tr>";
print "<tr><td align=center>$font<small>i den p&aring;g&aelig;ldende ordre. Det anbefales derfor at tilse at &oslash;vrige brugere lukker alle ordrevinduer.</small></td></tr>";
print "<tr><td align=center>$font<small>&AElig;ndring af varenummer har ingen indflydelse p&aring; varestatestik eller andet, bortset fra at varen vil figurere</small></td></tr>";
print "<tr><td align=center>$font<small>med det gamle varenummer i ordrer som er afsluttet f&oslash;r &aelig;ndringsdatoen.</small></td></tr>";

print "<tr><td align=center><hr width=50%></td></tr>";
print "<tr><td align=center>$font<small>Ret varenummer $varenr til: <input type=text name=nyt_varenr  width=30 value=$varenr></small></td></tr>";
print "<input type=hidden name=id  width=30 value='$id'>";
print "<input type=hidden name=varenr  width=30 value='$varenr'>";
print "<tr><td align=center><input type=submit value=\"Ret\" name=\"submit\"></td></tr>";
print "</form>";

print "</tbody></table";
print "</td></tr>\n";
print "</tbody></table";




?>