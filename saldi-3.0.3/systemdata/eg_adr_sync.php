<?php
// ----------------------------------------------------------------------
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

 include("../includes/connect.php");
 include("../includes/db_query.php");
 if ($_GET)
 {
   $id = $_GET['id'];
   $returside= $_GET['returside'];
   $ordre_id = $_GET['ordre_id'];
   $fokus = $_GET['fokus'];
  $konto_id=$_GET['konto_id'];
 }

if ($_POST)
{
  $id=$_POST['id'];
  $konto_id=$_POST['konto_id'];
  $navn=trim($_POST['navn']);
  $addr1=trim($_POST['addr1']);
  $addr2=trim($_POST['addr2']);
  $postnr=trim($_POST['postnr']);
  $bynavn=trim($_POST['bynavn']);
  $tlf=trim($_POST['tlf']);
  $fax=trim($_POST['fax']);
  $mobil=trim($_POST['mobil']);
  $email=trim($_POST['email']);
  $cprnr=trim($_POST['cprnr']);
  $notes=trim($_POST['notes']);
  $ordre_id = $_GET['ordre_id'];
  $returside=$_POST['returside'];
  $fokus=$_POST['fokus'];

  if(!$betalingsdage){$betalingsdage=0;}
  if(!$kreditmax){$kreditmax=0;}
  if (($id==0)&&($navn))
  {
    $query = db_modify("insert into ansatte (navn, konto_id, addr1, addr2, postnr, bynavn, tlf, fax, mobil, email, cprnr, notes) values ('$navn', '$konto_id', '$addr1', '$addr2', '$postnr', '$bynavn', '$tlf', '$fax', '$mobil', '$email', '$cprnr', '$notes')");
    $query = db_select("select id from ansatte where konto_id = '$konto_id' and navn='$navn' order by id desc");
    $row = db_fetch_array($query);
    $id = $row[id];
  }
  elseif ($id > 0)
  {
    $query = db_modify("update ansatte set navn = '$navn', konto_id = '$konto_id', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', email = '$email', tlf = '$tlf', fax = '$fax', mobil = '$mobil', cprnr = '$cprnr', notes = '$notes' where id = '$id'");
  }
}

$query = db_select("select firmanavn from adresser where id = '$konto_id'");
$row = db_fetch_array($query);

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<td width=\"25%\" bgcolor=\"#ffcc00\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=stamkort.php?returside=$returside&id=$konto_id&fokus=$fokus accesskey=L>Luk</a></small></td>";
print "<td width=\"50%\" bgcolor=\"#ffcc00\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>$row[firmanavn] - Ansatte</small></td>";
print "<td width=\"25%\" bgcolor=\"#ffcc00\" align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$konto_id accesskey=N>Ny</a><br></small></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align = center valign = center>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";


if ($id > 0)
{
  $query = db_select("select * from ansatte where id = '$id'");
  $row = db_fetch_array($query);
  $konto_id=$row['konto_id'];
  $navn=$row['navn'];
  $addr1=$row['addr1'];
  $addr2=$row['addr2'];
  $postnr=$row['postnr'];
  $bynavn=$row['bynavn'];
  $email=$row['email'];
  $tlf=$row['tlf'];
  $fax=$row['fax'];
  $mobil=$row['mobil'];
  $cprnr=$row['cprnr'];
  $notes=$row['notes'];
}
else
{
  $id=0;
}
print "<form name=ansatte action=ansatte.php method=post>";
print "<input type=hidden name=id value='$id'>";
print "<input type=hidden name=konto_id value='$konto_id'>";
print "<input type=hidden name=ordre_id value='$ordre_id'>";
print "<input type=hidden name=returside value='$returside'>";
print "<input type=hidden name=fokus value='$fokus'>";


print "<td><font face=\"Helvetica, Arial, sans-serif\">Navn</td><td><br></td><td><input type=text size=25 name=navn value='$navn'></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Adresse</td><td><br></td><td><input type=text size=25 name=addr1 value='$addr1'></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">Adresse2</td><td><br></td><td><input type=text size=25 name=addr2 value='$addr2'></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Postnr</td><td><br></td><td><input type=text size=6 name=postnr value='$postnr'></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">By</td><td><br></td><td><input type=text size=25 name=bynavn value='$bynavn'></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">E-mail</td><td><br></td><td><input type=text size=25 name=email value='$email'></td>";
print "<td><br></td>";
#print "<td><font face=\"Helvetica, Arial, sans-serif\">CVR. nr.</td><td><br></td><td><input type=text size=10 name=cprnr value='$cprnr'></td></tr>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">Mobil</td><td><br></td><td><input type=text size=10 name=mobil value='$mobil'></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Lokalnr.</td><td><br></td><td><input type=text size=10 name=tlf value='$tlf'></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">Lokal fax</td><td><br></td><td><input type=text size=10 name=fax value='$fax'></td></tr>";
print "<td><br></td>";
print "<tr><td valign=top><font face=\"Helvetica, Arial, sans-serif\">Bem&aelig;rkning</td><td colspan=7><textarea name=\"notes\" rows=\"3\" cols=\"85\">$notes</textarea></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td><br></td></tr>";
print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td>";
?>
</tbody>
</table>
</td></tr>
<tr><td align = "center" valign = "bottom">
    <table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
      <td width="100%" bgcolor="#ffcc00"><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Copyright (C) 2004 DANOSOFT ApS</small></td>
    </tbody></table>
</td></tr>
</tbody></table>
</body></html>
