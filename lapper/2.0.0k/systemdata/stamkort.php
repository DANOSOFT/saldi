<?php
// -------------------------------------------systemdata/stamdata.php------lap 2.0.0k------2008-05-28-----
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$title="Stamdata";
$modulnr=1;
 
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/dkdecimal.php");
include("../includes/db_query.php");
include("top.php");


print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

if ($_POST) {
	$id=$_POST['id'];
	$kontonr=trim($_POST['kontonr']);
	$firmanavn=addslashes(trim($_POST['firmanavn']));
	$addr1=addslashes(trim($_POST['addr1']));
	$addr2=addslashes(trim($_POST['addr2']));
	$postnr=addslashes(trim($_POST['postnr']));
	$bynavn=addslashes(trim($_POST['bynavn']));
	$kontakt=addslashes(trim($_POST['kontakt']));
	$tlf=addslashes(trim($_POST['tlf']));
	$fax=addslashes(trim($_POST['fax']));
	$cvrnr=addslashes(trim($_POST['cvrnr']));
	$ans_id=$_POST['ans_id'];
	$ans_ant=$_POST['ans_ant'];
	$lukket_ant=$_POST['lukket_ant'];
	$posnr=$_POST['posnr'];
	$bank_navn=addslashes(trim($_POST['bank_navn']));
	$bank_reg=addslashes(trim($_POST['bank_reg']));
	$bank_konto=addslashes(trim($_POST['bank_konto']));
	$email=addslashes(trim($_POST['email']));
	$vis_lukket=trim($_POST['vis_lukket']);
	
	if ($id==0)
	{
		$query = db_modify("insert into adresser (kontonr, firmanavn, addr1, addr2, postnr, bynavn, tlf, fax, cvrnr, art, bank_navn, bank_reg, bank_konto, email) values ('$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$tlf', '$fax', '$cvrnr', 'S', '$bank_navn', '$bank_reg', '$bank_konto', '$email')");
		$query = db_select("select id from adresser where art = 'S'");
		$row = db_fetch_array($query);
		$id = $row[id];
	}
	elseif ($id > 0)
	{
		$query = db_modify("update adresser set kontonr = '$kontonr', firmanavn = '$firmanavn', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', tlf = '$tlf', fax = '$fax', cvrnr = '$cvrnr', bank_navn='$bank_navn', bank_reg='$bank_reg', bank_konto='$bank_konto', email='$email', notes = '$notes' where art = 'S'");
		for ($x=1; $x<=$ans_ant; $x++) {
			if (($posnr[$x])&&($ans_id[$x])){db_modify("update ansatte set posnr = '$posnr[$x]' where id = '$ans_id[$x]'");}
			elseif($ans_id[$x]){ db_modify("delete from ansatte where id = '$ans_id[$x]'");}
		}
		for ($x=1; $x<=$lukket_ant; $x++) {
			if (($posnr[$x])&&($ans_id[$x])){db_modify("update ansatte set posnr = '$posnr[$x]' where id = '$ans_id[$x]'");}
			elseif($ans_id[$x]){ db_modify("delete from ansatte where id = '$ans_id[$x]'");}
		}
	}
}

$query = db_select("select * from adresser where art = 'S'");
$row = db_fetch_array($query);
$id=$row[id];
$kontonr=$row['kontonr'];
$firmanavn=$row['firmanavn'];
$addr1=$row['addr1'];
$addr2=$row['addr2'];
$postnr=$row['postnr'];
$bynavn=$row['bynavn'];
#$kontakt=$row['kontakt'];
$tlf=$row['tlf'];
$fax=$row['fax'];
$cvrnr=$row['cvrnr'];
$bank_navn=$row['bank_navn'];
$bank_reg=$row['bank_reg'];
$bank_konto=$row['bank_konto'];
$email=$row['email'];
#	$id=0;


print "<form name=stamkort action=stamkort.php method=post>";
print "<input type=hidden name=id value='$id'><input type=hidden name=kontonr value=\"0\">";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Firmanavn</td><td><br></td><td><input type=text size=25 name=firmanavn value=\"$firmanavn\"></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Adresse</td><td><br></td><td><input type=text size=25 name=addr1 value=\"$addr1\"></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">Adresse2</td><td><br></td><td><input type=text size=25 name=addr2 value=\"$addr2\"></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Postnr</td><td><br></td><td><input type=text size=6 name=postnr value=\"$postnr\"></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">By</td><td><br></td><td><input type=text size=25 name=bynavn value=\"$bynavn\"></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">e-mail</td><td><br></td><td><input type=text size=25 name=email value=\"$email\"></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">CVR. nr.</td><td><br></td><td><input type=text size=10 name=cvrnr value=\"$cvrnr\"></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Telefon</td><td><br></td><td><input type=text size=10 name=tlf value=\"$tlf\"></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">Telefax</td><td><br></td><td><input type=text size=10 name=fax value=\"$fax\"></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Bank</td><td><br></td><td><input type=text size=25 name=bank_navn value=\"$bank_navn\"></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">Reg / konto</td><td><br></td><td><input type=text size=4 name=bank_reg value=\"$bank_reg\"><input type=text size=9 name=bank_konto value=\"$bank_konto\"></td></tr>";
if ($id) {
	$font=$font."<small>";
	print "<tr><td colspan=7><hr></td></tr>";
	print "<tr><td></td><td></td><td>$font Pos. Kontakt</td><td>$font Lokalnr. / Mobil</td><td>$font E-mail</td><td></td><td align=right>$font<a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>Ny medarbejder</a></td></tr>";
	print "<tr><td colspan=1></td><td colspan=5><hr></td></tr>";
			
$taeller=0;
while ($taeller < 1) {			
	if ($vis_lukket!="checked") {
		$query = db_select("select * from ansatte where konto_id = '$id' and lukket != 'on' order by posnr");
	} elseif  ($vis_lukket=="checked") $query = db_select("select * from ansatte where konto_id = '$id' and lukket = 'on' order by posnr");
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		if ($x > 0) {print "<tr><td><br></td><td><br></td>";}
		print "<td><input type=text size=1 name=posnr[$x] value=\"$x\">$font&nbsp;<a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id&id=$row[id]>$row[navn]</a></td>";
		print "<td>$font$row[tlf] / $row[mobil]</td><td>$font$row[email]</td><td align=right></td></tr>";
		print "<input type=hidden name=ans_id[$x] value=$row[id]>";
	}
	if ($vis_lukket!="checked") print "<input type=hidden name=ans_ant value=$x>";
	else print "<input type=hidden name=lukket_ant value=$x>";
	$taeller++;
	if ($vis_lukket=='on') {
		$vis_lukket="checked";
		$taeller--;
		print "<tr><td></td><td></td><td colspan=3><hr></td></tr>";
	}	
	if ($taeller>0) {
		print "<tr><td></td><td></td><td colspan=3><hr></td></tr>";
		print "<tr><td></td><td></td><td>$font vis fratrådte&nbsp;<input type=checkbox name=vis_lukket $vis_lukket></td></tr>";
	}
}

print "<tr><td><br></td></tr>";
}
print "<tr><td><br></td></tr>";
print "<tr><td><br></td></tr>";
print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdat&eacute;r\" name=\"submit\"></td>";
?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
