<?php
// -----------------------------------/systemdata/ansatte.php--------lap 1.1.0----------------------------
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

$title="Personalekort";
$modulnr=1;

include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
include("../includes/db_query.php");

 if ($_GET) {
	$id = $_GET['id'];
	$returside= $_GET['returside'];
	$fokus = $_GET['fokus'];
	$konto_id=$_GET['konto_id'];
 }

if ($_POST) {
	$id=$_POST['id'];
	$konto_id=$_POST['konto_id'];
	$navn=trim($_POST['navn']);
	$initialer=trim($_POST['initialer']);
	$addr1=trim($_POST['addr1']);
	$addr2=trim($_POST['addr2']);
	$postnr=trim($_POST['postnr']);
	$bynavn=trim($_POST['bynavn']);
	$tlf=trim($_POST['tlf']);
	$fax=trim($_POST['fax']);
	$mobil=trim($_POST['mobil']);
	$privattlf=trim($_POST['privattlf']);
	$email=trim($_POST['email']);
	$cprnr=trim($_POST['cprnr']);
	$notes=trim($_POST['notes']);
	$lukket=trim($_POST['lukket']);
	list($afd,$x)=split(":",$_POST['afd']);
	$afd=$afd*1;
	$returside=$_POST['returside'];
	$fokus=$_POST['fokus'];
	$provision=$_POST['provision'];
	$provision_id=$_POST['provision_id'];
	$gruppe_id=$_POST['gruppe_id'];
	$pro_antal=$_POST['pro_antal'];

	if (($id==0)&&($navn)) {
		db_modify("insert into ansatte (navn, initialer, konto_id, addr1, addr2, postnr, bynavn, tlf, fax, privattlf, mobil, email, cprnr, notes, afd, lukket) values ('$navn', '$initialer', '$konto_id', '$addr1', '$addr2', '$postnr', '$bynavn', '$tlf', '$fax', '$privattlf', '$mobil', '$email', '$cprnr', '$notes', '$afd', '$lukket')");
		$query = db_select("select id from ansatte where konto_id = '$konto_id' and navn='$navn' order by id desc");
		$row = db_fetch_array($query);
		$id = $row[id];
	} elseif ($id > 0) {
		db_modify("update ansatte set navn = '$navn', initialer='$initialer', konto_id = '$konto_id', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', email = '$email', tlf = '$tlf', fax = '$fax', privattlf= '$privattlf', mobil = '$mobil', cprnr = '$cprnr', notes = '$notes', afd = '$afd', lukket = '$lukket' where id = '$id'");
	}
	for ($x=1; $x<=$pro_antal; $x++) { 
		if ($provision_id[$x]) {
			$provision[$x]=usdecimal($provision[$x]);
			db_modify("update provision set provision='$provision[$x]', gruppe_id='$gruppe_id[$x]' where id = '$provision_id[$x]'");
		} elseif ($provision[$x]) {
			$provision[$x]=usdecimal($provision[$x]);
			if (!$r= db_fetch_array(db_select("select id from provision where gruppe_id = '$gruppe_id[$x]' and ansat_id='$id'"))) {
				db_modify("insert into provision (provision, gruppe_id, ansat_id) values ('$provision[$x]', '$gruppe_id[$x]', '$id')");
			}
		}
	}
}
$query = db_select("select firmanavn from adresser where id = '$konto_id'");
$row = db_fetch_array($query);

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund<font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=stamkort.php?returside=$returside&id=$konto_id&fokus=$fokus accesskey=L>Luk</a></small></td>";
print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>$row[firmanavn] - Ansatte</small></td>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=ansatte.php?returside=$returside&fokus=$fokus&konto_id=$konto_id accesskey=N>Ny</a><br></small></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align = center valign = center>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";


if ($id > 0) {
	$query = db_select("select * from ansatte where id = '$id'");
	$row = db_fetch_array($query);
	$konto_id=$row['konto_id'];
	$navn=$row['navn'];
	$initialer = $row['initialer'];
	$addr1=$row['addr1'];
	$addr2=$row['addr2'];
	$postnr=$row['postnr'];
	$bynavn=$row['bynavn'];
	$email=$row['email'];
	$tlf=$row['tlf'];
	$fax=$row['fax'];
	$mobil=$row['mobil'];
	$privattlf=$row['privattlf'];
	$cprnr=$row['cprnr'];
	$notes=$row['notes'];
	$afd=$row['afd'];
	$lukket=$row['lukket'];
	
} else $id=0;

$x=0;
$query = db_select("SELECT * FROM grupper WHERE art = 'AFD' ORDER BY kodenr");
while ($row = db_fetch_array($query)) {
	if (trim($row['kodenr'])==$afd) $afdeling=$row['beskrivelse'];
	else {
		$x++;
		$afd_nr[$x]=$row['kodenr'];
		$afd_beskrivelse[$x]=$row['beskrivelse'];
	}
}
$afd_antal=$x;

print "<form name=ansatte action=ansatte.php method=post>";
print "<input type=hidden name=id value='$id'>";
print "<input type=hidden name=konto_id value='$konto_id'>";
print "<input type=hidden name=returside value='$returside'>";
print "<input type=hidden name=fokus value='$fokus'>";


print "<td>$font Navn</td><td><br></td><td><input type=text size=25 name=navn value='$navn'></td>";
print "<td><br></td>";
print "<td>$font Initialer</td><td><br></td><td><input type=text size=6 name=initialer value='$initialer'></td>";
print "<tr><td>$font Adresse</td><td><br></td><td><input type=text size=25 name=addr1 value='$addr1'></td>";
print "<td><br></td>";
print "<td>$font Adresse2</td><td><br></td><td><input type=text size=25 name=addr2 value='$addr2'></td></tr>";
print "<tr><td>$font Postnr</td><td><br></td><td><input type=text size=6 name=postnr value='$postnr'></td>";
print "<td><br></td>";
print "<td>$font By</td><td><br></td><td><input type=text size=25 name=bynavn value='$bynavn'></td></tr>";
print "<tr><td>$font E-mail</td><td><br></td><td><input type=text size=25 name=email value='$email'></td>";
print "<td><br></td>";
#print "<td>$font CVR. nr.</td><td><br></td><td><input type=text size=10 name=cprnr value='$cprnr'></td></tr>";
print "<td>$font Mobil</td><td><br></td><td><input type=text size=10 name=mobil value='$mobil'></td></tr>";
print "<tr><td>$font Lokalnr.</td><td><br></td><td><input type=text size=10 name=tlf value='$tlf'></td>";
print "<td><br></td>";
print "<td>$font Lokal fax</td><td><br></td><td><input type=text size=10 name=fax value='$fax'></td></tr>";
print "<tr><td>$font Privat tlf</td><td><br></td><td><input type=text size=10 name=privattlf value='$privattlf'></td>";
print "<td><br></td>";
print "<td>$font Provision:</td><td></td><td>$font --------------------</td></tr>";
print "<tr><td>$font Afdeling</td><td><br></td><td><SELECT NAME=afd>";
if ($afd) print"<option>$afd:$afdeling</option>";	
for ($x=1; $x<=$afd_antal; $x++) { 
	print "<option>$afd_nr[$x]:$afd_beskrivelse[$x]</option>";
}
print "</SELECT></td><td><br></td>";
if (!db_fetch_array(db_select("SELECT attrelid FROM pg_attribute where attname='gruppe_id'"))) {
	db_modify("CREATE TABLE provision (id serial NOT NULL, gruppe_id integer, ansat_id integer, provision numeric, PRIMARY KEY (id))");
}
$x=0;
$q1 = db_select("SELECT id, beskrivelse FROM grupper WHERE art='DG' order by beskrivelse");
while ($r1 = db_fetch_array($q1)) {
	$x++;
	$r2 = db_fetch_array(db_select("SELECT * from provision WHERE ansat_id = '$id' and gruppe_id = '$r1[id]'"));
	if ($x>1) print "<tr><td><br></td><td><br></td><td><br></td><td><br></td>";
	$provision=dkdecimal($r2['provision']);
	print "<td>$font $r1[beskrivelse]</td><td></td><td><input type=text style=text-align:right size=5 name=provision[$x] value='$provision'>%</td></tr>";	
	print "<input type=hidden name=gruppe_id[$x] value='$r1[id]'>";
	print "<input type=hidden name=provision_id[$x] value='$r2[id]'>";
}
print "<input type=hidden name=pro_antal value='$x'>";

print "<tr><td valign=top>$font Bem&aelig;rkning</td><td colspan=7><textarea name=\"notes\" rows=\"3\" cols=\"85\">$notes</textarea></td></tr>";
if ($lukket) $lukket="checked";
print "<tr><td valign=top>$font Fratr&aring;dt</td><td><input type=checkbox name=lukket $lukket></td></tr>";

print "<tr><td><br></td></tr>";
print "<tr><td><br></td></tr>";
print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td>";
?>
</tbody>
</table>
</td></tr>
<tr><td align = "center" valign = "bottom">
</tbody></table>
</body></html>
