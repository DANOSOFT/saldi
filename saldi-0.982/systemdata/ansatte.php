<?
// -----------------------------------/systemdata/ansatte.php--------patch0935-----------------------------
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
// Copyright (c) 2004-2005 ITz ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
include("../includes/db_query.php");

 if ($_GET)
 {
   $id = $_GET['id'];
   $returside= $_GET['returside'];
   $ordre_id = $_GET['ordre_id'];
   $fokus = $_GET['fokus'];
  $konto_id=$_GET['konto_id'];
 }

if ($HTTP_POST_VARS)
{
  $id=$HTTP_POST_VARS['id'];
  $konto_id=$HTTP_POST_VARS['konto_id'];
  $navn=trim($HTTP_POST_VARS['navn']);
  $initialer=trim($HTTP_POST_VARS['initialer']);
  $addr1=trim($HTTP_POST_VARS['addr1']);
  $addr2=trim($HTTP_POST_VARS['addr2']);
  $postnr=trim($HTTP_POST_VARS['postnr']);
  $bynavn=trim($HTTP_POST_VARS['bynavn']);
  $tlf=trim($HTTP_POST_VARS['tlf']);
  $fax=trim($HTTP_POST_VARS['fax']);
  $mobil=trim($HTTP_POST_VARS['mobil']);
  $privattlf=trim($HTTP_POST_VARS['privattlf']);
  $email=trim($HTTP_POST_VARS['email']);
  $cprnr=trim($HTTP_POST_VARS['cprnr']);
  $notes=trim($HTTP_POST_VARS['notes']);
  list($afd,$x)=split(":",$HTTP_POST_VARS['afd']);
  $afd=$afd*1;
  $ordre_id = $_GET['ordre_id'];
  $returside=$HTTP_POST_VARS['returside'];
  $fokus=$HTTP_POST_VARS['fokus'];

  if(!$betalingsdage){$betalingsdage=0;}
  if(!$kreditmax){$kreditmax=0;}
  if (($id==0)&&($navn))
  {
    $query = db_modify("insert into ansatte (navn, initialer, konto_id, addr1, addr2, postnr, bynavn, tlf, fax, privattlf, mobil, email, cprnr, notes, afd) values ('$navn', '$initialer', '$konto_id', '$addr1', '$addr2', '$postnr', '$bynavn', '$tlf', '$fax', '$privattlf', '$mobil', '$email', '$cprnr', '$notes', '$afd')");
    $query = db_select("select id from ansatte where konto_id = '$konto_id' and navn='$navn' order by id desc");
    $row = db_fetch_array($query);
    $id = $row[id];
  }
  elseif ($id > 0)
  {
    $query = db_modify("update ansatte set navn = '$navn', initialer='$initialer', konto_id = '$konto_id', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', email = '$email', tlf = '$tlf', fax = '$fax', privattlf= '$privattlf', mobil = '$mobil', cprnr = '$cprnr', notes = '$notes', afd = '$afd' where id = '$id'");
  }
}

$query = db_select("select firmanavn from adresser where id = '$konto_id'");
$row = db_fetch_array($query);

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=stamkort.php?returside=$returside&id=$konto_id&fokus=$fokus accesskey=T>Tilbage</a></small></td>";
print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>$row[firmanavn] - Ansatte</small></td>";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$konto_id accesskey=N>Ny</a><br></small></td>";
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
}
else
{
  $id=0;
}
$x=0;
$query = db_select("SELECT * FROM grupper WHERE art = 'AFD' ORDER BY kodenr");
while ($row = db_fetch_array($query)) {
  if (trim($row['kodenr'])==$afd) {$afdeling=$row['beskrivelse'];}
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
print "<input type=hidden name=ordre_id value='$ordre_id'>";
print "<input type=hidden name=returside value='$returside'>";
print "<input type=hidden name=fokus value='$fokus'>";


print "<td><font face=\"Helvetica, Arial, sans-serif\">Navn</td><td><br></td><td><input type=text size=25 name=navn value='$navn'></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">Initialer</td><td><br></td><td><input type=text size=6 name=initialer value='$initialer'></td>";
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
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Privat tlf</td><td><br></td><td><input type=text size=10 name=privattlf value='$privattlf'></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">Afdeling</td><td><br></td><td><SELECT NAME=afd>";
if ($afd) {print"<option>$afd:$afdeling</option>";}  
for ($x=1; $x<=$afd_antal; $x++) { 
    print "<option>$afd_nr[$x]:$afd_beskrivelse[$x]</option>";
  } 
  print "</SELECT></td></tr>";
print "<tr><td valign=top><font face=\"Helvetica, Arial, sans-serif\">Bem&aelig;rkning</td><td colspan=7><textarea name=\"notes\" rows=\"3\" cols=\"85\">$notes</textarea></td></tr>";
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
