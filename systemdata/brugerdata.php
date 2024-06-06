<?php
//                         ___   _   _   __  _
//                        / __| / \ | | |  \| |
//                        \__ \/ _ \| |_| | | |
//                        |___/_/ \_|___|__/|_|
//
// -----------------systemdata/brugerdata.php ------ver 3.6.6-----2016.11.04----------
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2016 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Brugerdate";


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");

$aktiver=if_isset($_GET['aktiver']);
$popop=if_isset($_GET['popop']);
if (!$popop) {
	if ($popup) $popop="J";
	else $popop="N";
}

if ($aktiver) {
	include("../includes/connect.php");
	db_modify("update online set regnskabsaar = '$aktiver' where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
	include("../includes/online.php");
	db_modify("update brugere set regnskabsaar = '$aktiver' where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
}


if ($_POST) {
	$popup=$_POST['popup'];
	$glkode=trim($_POST['glkode']);
	$nykode1=trim($_POST['nykode1']);
	$nykode2=trim($_POST['nykode2']);
	db_modify("update grupper set box2='$popup' where art = 'USET' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__);
	if ($glkode1!=$nykode1) {
		if ($nykode1 && $nykode1==$nykode2 && $glkode) {
			$r=db_fetch_array(db_select("select kode from brugere where brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__));
			if ($r['kode']==md5($glkode) || $r['kode']==saldikrypt($bruger_id,$glkode)) {
				$nykode1=saldikrypt($bruger_id,$nykode1);
				db_modify("update brugere set kode='$nykode1' where brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__);
				print tekstboks('Adgangskode &aelig;ndret!');
			} elseif ($r['kode']) print tekstboks('Der er tastet forkert v&aelig;rdi i "Gl. adgangskode"');
		} else print print tekstboks('Der er tastet forskellige v&aelig;rdier i "Ny adgangskode" & "Bekr&aelig;ft ny kode"');
	}
}
if ($popop=='J') $returside="../includes/luk.php";
else $returside="../index/menu.php";

print "
<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>SALDI - Brugerindstillinger</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>
<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">
<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
	<tr><td align=\"center\" valign=\"top\">
		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><td>
			<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>
			<td width=\"10%\" $top_bund><a href=$returside accesskey=L>Luk</a></td>
			<td width=\"80%\" $top_bund>Brugerindstillinger for $brugernavn | $regnskab</td>
			<td width=\"10%\" $top_bund><br></td>
			</tbody></table></td>
		</tbody></table>
	</td></tr>
	<tr><td align=\"center\" valign=\"top\">";

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=\"70%\"><tbody>";
print "<tr><td width = 50%><b> Beskrivelse</a></b></td>";
print "		<td width = 10%><b> Start md.</a></b></td>";
print "		<td width = 10%><b> Start &aring;r</a></b></td>";
print "		<td width = 10%><b> Slut md.</a></b></td>";
print "		<td width = 10%><b> Slut &aring;r</a></b></td>";
print "		<td width = 10%><b> <br></a></b></td>		 </tr>";
$query = db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$regnaar = $row['regnskabsaar'];

$query = db_select("select * from grupper where art = 'RA' order by box2",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	if ($bgcolor!=$bgcolor1){$bgcolor=$bgcolor1; $color='#000000';}
	elseif ($bgcolor!=$bgcolor3){$bgcolor=$bgcolor3; $color='#000000';}
	print "<tr bgcolor=\"$bgcolor\">";
#	print "<td><a href=regnskabskort.php?id=$row[id]><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[kodenr]</a><br></td>";
	print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[beskrivelse]<br></td>";
	print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[box1]<br></td>";
	print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[box2]<br></td>";
	print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[box3]<br></td>";
	print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[box4]<br></td>";
	if (($row['kodenr']!=$regnaar)&&($row['box5']=='on')) {
		print "<td><a href=brugerdata.php?aktiver=$row[kodenr]&popop=$popop> S&aelig;t aktivt</a><br></td>";
	}
	elseif ($row['kodenr']!=$regnaar) print "<td> Lukket</td>";
	else print "<td><font color=#ff0000>Aktivt</font></td>";
	print "</tr>";
	print "</tr>";
}
print "</tbody></table></td></tr>";

$r=db_fetch_array(db_select("select box2 from grupper where art = 'USET' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__));

print "<form name=brugerdata action=brugerdata.php?popop=$popop method=post>";
print "<tr><td align=center valign=top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
print "<tr><td align=center colspan=2><b>Skift adgangskode</b></td></tr>";
print "<tr><td> Gl. adgangskode</td><td><input type=password size=20 name=glkode></td></tr>";
if (($brugernavn=='test')&&($db=='test')) {
	print "<tr><td><span title='Der kan ikke skrives i dette felt i demoversionen'> Ny adgangskode</span></td><td><input type=readonly size=20 name=nykode1 value=''></td></tr>";
} else print "<tr><td> Ny adgangskode</td><td><input type=password text size=20 name=nykode1></td></tr>";
print "<tr><td>Bekr&aelig;ft ny kode</td><td><input type=password size=20 name=nykode2></td></tr>";
if ($popup) $popup="checked";
print "<tr><td title='".$tekst=findtekst(207,$sprog_id)."'>".$tekst=findtekst(208,$sprog_id)."</td><td><input type=checkbox name=popup $popup></td></tr>";
print "<td colspan=2 align = center><input type=submit value=\"Ok\" name=\"submit\"></td>";
print "</form";
print "</tr></tbody></table></td></tr>";
print "</td></tr>";
?>
</tbody></table>
</body></html>
