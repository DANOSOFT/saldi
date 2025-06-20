<?php
// ----------systemdata/stamdata.php------lap 2.0.7-------2009-05-15-----
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
$css="../css/standard.css";

$title="Stamdata";
$modulnr=1;
 
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("top.php");

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>";

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
	$pbs_nr=trim($_POST['pbs_nr']);
	$fi_nr=trim($_POST['fi_nr']);
	if ($id==0) {
		$query = db_modify("insert into adresser (kontonr,firmanavn,addr1,addr2,postnr,bynavn,tlf,fax,cvrnr,art,bank_navn,bank_reg,bank_konto,email,pbs_nr,bank_fi) values ('$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$tlf','$fax','$cvrnr','S','$bank_navn','$bank_reg','$bank_konto','$email','$pbs_nr','$fi_nr')");
		$query = db_select("select id from adresser where art = 'S'");
		$row = db_fetch_array($query);
		$id = $row[id];
	}	elseif ($id > 0) {
		$query = db_modify("update adresser set kontonr = '$kontonr', firmanavn = '$firmanavn', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', tlf = '$tlf', fax = '$fax', cvrnr = '$cvrnr', bank_navn='$bank_navn', bank_reg='$bank_reg', bank_konto='$bank_konto', email='$email', notes = '$notes', pbs_nr='$pbs_nr', bank_fi='$fi_nr' where art = 'S'");
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
$pbs_nr=$row['pbs_nr']; 
$fi_nr=$row['bank_fi']; 
#	$id=0;


print "<form name=stamkort action=stamkort.php method=post>";
print "<tr><td><table><tbody>";
print "<input type=hidden name=id value='$id'><input type=hidden name=kontonr value=\"0\">";
print "<tr><td>Firmanavn</td><td><input type=text size=25 name=firmanavn value=\"$firmanavn\"></td></tr>";
print "<tr><td>Adresse</td><td><input type=text size=25 name=addr1 value=\"$addr1\"></td></tr>";
print "<tr><td>Adresse2</td><td><input type=text size=25 name=addr2 value=\"$addr2\"></td></tr>";
print "<tr><td>Postnr./by</td><td><input type=text size=3 name=postnr value=\"$postnr\"><input type=text size=19 name=bynavn value=\"$bynavn\"></td></tr>";
print "<tr><td>e-mail</td><td><input type=text size=25 name=email value=\"$email\"></td></tr>";
print "<tr><td>Bank</td><td><input type=text size=25 name=bank_navn value=\"$bank_navn\"></td></tr>";
print "</tbody></table></td><td><table><tbody>";
print "<tr><td>CVR-nr.</td><td><input type=text size=10 name=cvrnr value=\"$cvrnr\"></td></tr>";
print "<tr><td>Telefon</td><td><input type=text size=10 name=tlf value=\"$tlf\"></td></tr>";
print "<tr><td>Telefax</td><td><input type=text size=10 name=fax value=\"$fax\"></td></tr>";
print "<tr><td>PBS Kreditornr.</td><td><input type=text size=10 name=pbs_nr value=\"$pbs_nr\"></td></tr>";
print "<tr><td>FI Kreditornr.</td><td><input type=text size=10 name=fi_nr value=\"$fi_nr\"></td></tr>";
print "<td>Reg./konto</td><td><input type=text size=4 name=bank_reg value=\"$bank_reg\"><input type=text size=10 name=bank_konto value=\"$bank_konto\"></td></tr>";
print "<tbody></table></td></tr>";
if ($id) {
	print "<tr><td colspan=2><hr></td></tr>";
print "<tr><td colspan=2 align=center><table><tbody>";
	print "<tr><td> Pos. Kontakt</td><td> Lokalnr. / mobil</td><td> E-mail</td><td></td><td align=right><a href=\"ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id\">Ny medarbejder</a></td></tr>";
	print "<tr><td colspan=5><hr></td></tr>";
			
$taeller=0;
while ($taeller < 1) {			
	if ($vis_lukket!="checked") {
		$query = db_select("select * from ansatte where konto_id = '$id' and lukket != 'on' order by posnr");
	} elseif  ($vis_lukket=="checked") $query = db_select("select * from ansatte where konto_id = '$id' and lukket = 'on' order by posnr");
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
#		if ($x > 0) {print "<tr><td><br></td><td><br></td>";}
		print "<td><input type=text size=1 name=posnr[$x] value=\"$x\">&nbsp;<a href=\"ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id&id=$row[id]\">$row[navn]</a></td>";
		print "<td>$row[tlf] / $row[mobil]</td><td colspan=2>$row[email]</td></tr>";
		print "<input type=hidden name=ans_id[$x] value=$row[id]>";
	}
	if ($vis_lukket!="checked") print "<input type=hidden name=ans_ant value=$x>";
	else print "<input type=hidden name=lukket_ant value=$x>";
	$taeller++;
	if ($vis_lukket=='on') {
		$vis_lukket="checked";
		$taeller--;
		print "<tr><td colspan=5><hr></td></tr>";
	}	
	if ($taeller>0) {
		print "<tr><td colspan=5><hr></td></tr>";
		print "<tr><td> vis fratr&aring;dte&nbsp;<input type=checkbox name=vis_lukket $vis_lukket></td></tr>";
	}
}
print "<tbody></table></td></tr>";
}
print "<tr><td colspan=2><br></td></tr>";
print "<tr><td colspan=2 align=center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
