<?php

// --------------systemdata/admin_brugere.php------lap 2.0.7----2009-05-19------
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
// ------------------------------------------------------------------------

@session_start();
$s_id=session_id();

$modulnr=1;
$title="Brugere";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\" height=\"25\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=../index/admin_menu.php accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\">Admin brugere</td>";
print "<td width=\"10%\" $top_bund align = \"right\"><br></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align = center valign = center>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

$ret_id=$_GET['ret_id'];
$slet_id=$_GET['slet_id'];

if ($_POST) {
	$submit=$_POST['submit'];
	$id=$_POST['id'];	
	$tmp=$_POST['random'];
	$brugernavn=trim($_POST[$tmp]);
	$kode=trim($_POST['kode']);
	$kode2=trim($_POST['kode2']);
	$brugernavn=trim($brugernavn);
	
	if ($kode && $kode != $kode2) {
			$alerttext="Adgangskoder er ikke ens";
			print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
			$kode=NULL;
			$ret_id=$id;
	}
	if (($kode) && (!strstr($kode,'**********'))) $kode=md5($kode);
	elseif($kode)	{
		$query = db_select("select * from brugere where id = '$id'");
		if ($row = db_fetch_array($query))
		$kode=trim($row['kode']);
	}
	if ((strstr($submit,'Tilf'))&&($brugernavn)&&($brugernavn!="-")) {
		$query = db_select("select id from brugere where brugernavn = '$brugernavn'");
		if ($row = db_fetch_array($query)) {
			$alerttext="Der findes allerede en bruger med brugenavn: $brugernavn!";
			print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
#			print "<tr><td align=center>Der findes allerede en bruger med brugenavn: $brugernavn!</td></tr>";
		}	else {
			db_modify("insert into brugere (brugernavn, kode) values ('$brugernavn', '$kode')");
		}
	}
	
	elseif ((strstr($submit,'Opdat'))&&($brugernavn)&&($brugernavn!="-")) {
		db_modify("update brugere set brugernavn='$brugernavn', kode='$kode' where id=$id");
	}
	elseif (($id)&&($brugernavn=="-")) {db_modify("delete from brugere where id = $id");}
}

print "<tr><td valign = top align=center>";
# print "<table border=><tbody>";
print "<form name=bruger action=admin_brugere.php method=post>";

print "<tr><td><br></td><td title='Klik p&aring; brugernavn for at &aelig;ndre password eller slette bruger'><b>Brugernavn</b></td></tr>";
print "<tr><td><br></td></tr>";
$query = db_select("select * from brugere order by brugernavn");
while ($row = db_fetch_array($query)) {
	if ($row['id']!=$ret_id) {
		print "<tr><td><br></td><td><a href=admin_brugere.php?ret_id=$row[id]>$row[brugernavn]</a></td></tr>";
	}
}
print "<tr><td><br></td></tr>";
if ($ret_id) {
	$query = db_select("select * from brugere where id = $ret_id");
	$row = db_fetch_array($query);
	print "<tr><td></td>";
	
	print "<input type=hidden name=id value=$row[id]>";
	print "<input type=hidden name=random value=$row[id]>";	#For at undgaa at browseren "husker" et forkert brugernavn.
	print "<td title='Skriv - (minus) som brugernavn for at slette bruger'><input type=text size=20 name=$row[id] value='$row[brugernavn]'></td>";
	print "</tr>";
	print "<tr><td>Adgangskode</td><td><input type=password size=20 name=kode value='********************'></td></tr>";
	print "<tr><td>Gentag kode</td><td><input type=password size=20 name=kode2 value='********************'></td></tr>";
	$x=0;
	print "<tr><td><br></td></tr>";
	print "<td colspan=12 align = center><input type=submit value=\"Opdat&eacute;r\" name=\"submit\"></td>";
} else {
	$tmp="navn".rand(100,999);				#For at undgaa at browseren "husker" et forkert brugernavn.
	print "<input type=hidden name=random value = $tmp>";
	print "<tr><td> Ny&nbsp;bruger</td>";
	print "<td><input type=text size=20 name=$tmp></td>";
	print "</tr>";
	print "<tr><td> Adgangskode</td><td><input type=password size=20 name=kode></td></tr>";
	print "<tr><td> Gentag kode</td><td><input type=password size=20 name=kode2></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td colspan=12 align = center><input type=submit value=\"Tilf&oslash;j\" name=\"submit\"></td>";
}
print "</tr>";
# print "</tbody></table></td></tr>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
