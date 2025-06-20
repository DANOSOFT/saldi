<?
// --------------------------------------------systemdata/stamdata.php------patch0935--------------------
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
include("../includes/db_query.php");
include("top.php");


print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

if ($HTTP_POST_VARS)
{
  $id=$HTTP_POST_VARS['id'];
  $kontonr=trim($HTTP_POST_VARS['kontonr']);
  $firmanavn=trim($HTTP_POST_VARS['firmanavn']);
  $addr1=trim($HTTP_POST_VARS['addr1']);
  $addr2=trim($HTTP_POST_VARS['addr2']);
  $postnr=trim($HTTP_POST_VARS['postnr']);
  $bynavn=trim($HTTP_POST_VARS['bynavn']);
  $kontakt=trim($HTTP_POST_VARS['kontakt']);
  $tlf=trim($HTTP_POST_VARS['tlf']);
  $fax=trim($HTTP_POST_VARS['fax']);
  $cvrnr=trim($HTTP_POST_VARS['cvrnr']);
  $ans_id=$HTTP_POST_VARS['ans_id'];
  $ans_ant=$HTTP_POST_VARS['ans_ant'];
  $posnr=$HTTP_POST_VARS['posnr'];
  $bank_navn=trim($HTTP_POST_VARS['bank_navn']);
  $bank_reg=trim($HTTP_POST_VARS['bank_reg']);
  $bank_konto=trim($HTTP_POST_VARS['bank_konto']);
  
  if ($id==0)
  {
    $query = db_modify("insert into adresser (kontonr, firmanavn, addr1, addr2, postnr, bynavn, tlf, fax, cvrnr, art, bank_navn, bank_reg, bank_konto) values ('$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$tlf', '$fax', '$cvrnr', 'S', '$bank_navn', '$bank_reg', '$bank_konto')");
    $query = db_select("select id from adresser where art = 'S'");
    $row = db_fetch_array($query);
    $id = $row[id];
  }
  elseif ($id > 0)
  {
    $query = db_modify("update adresser set kontonr = '$kontonr',  firmanavn = '$firmanavn', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', tlf = '$tlf', fax = '$fax', cvrnr = '$cvrnr', bank_navn='$bank_navn', bank_reg='$bank_reg', bank_konto='$bank_konto', notes = '$notes' where art = 'S'");
    for ($x=1; $x<=$ans_ant; $x++)
    {
      if (($posnr[$x])&&($ans_id[$x])){db_modify("update ansatte set posnr = '$posnr[$x]' where id = '$ans_id[$x]'");}
      elseif($ans_id[$x]){ db_modify("delete from ansatte  where id = '$ans_id[$x]'");}
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
#  $id=0;


print "<form name=stamkort action=stamkort.php method=post>";
print "<input type=hidden name=id value='$id'><input type=hidden name=kontonr value='0'>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Firmanavn</td><td><br></td><td><input type=text size=25 name=firmanavn value='$firmanavn'></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Adresse</td><td><br></td><td><input type=text size=25 name=addr1 value='$addr1'></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">Adresse2</td><td><br></td><td><input type=text size=25 name=addr2 value='$addr2'></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Postnr</td><td><br></td><td><input type=text size=6 name=postnr value='$postnr'></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">By</td><td><br></td><td><input type=text size=25 name=bynavn value='$bynavn'></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\"></td><td><br></td><td></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">CVR. nr.</td><td><br></td><td><input type=text size=10 name=cvrnr value='$cvrnr'></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Telefon</td><td><br></td><td><input type=text size=10 name=tlf value='$tlf'></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">Telefax</td><td><br></td><td><input type=text size=10 name=fax value='$fax'></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Bank</td><td><br></td><td><input type=text size=25 name=bank_navn value='$bank_navn'></td>";
print "<td><br></td>";
print "<td><font face=\"Helvetica, Arial, sans-serif\">Reg / konto</td><td><br></td><td><input type=text size=4 name=bank_reg value='$bank_reg'><input type=text size=9 name=bank_konto value='$bank_konto'></td></tr>";
if ($id)
{
print "<tr><td colspan=7><hr></td></tr>";
  print "<tr><td></td><td></td><td>$font Pos. Kontakt</td><td>$font Lokalnr. / Mobil</td><td>$font E-mail</td><td>$font <a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>Ny</a></td></tr>";
  $x=0;
  $query = db_select("select * from ansatte where konto_id = '$id' order by posnr");
  while ($row = db_fetch_array($query))
  {
    $x++;
    if ($x > 0) {print "<tr><td><br></td><td><br></td>";}
    print "<td><input type=text size=1 name=posnr[$x] value='$x'>$font<small>&nbsp;<a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id&id=$row[id]>$row[navn]</a></td>";
    print "<td>$font<small>$row[tlf] / $row[mobil]</td><td>$font<small>$row[email]</td></tr>";
    print "<input type=hidden name=ans_id[$x] value=$row[id]>";
}
print "<input type=hidden name=ans_ant value=$x>";

print "<tr><td><br></td></tr>";
}
print "<tr><td><br></td></tr>";
print "<tr><td><br></td></tr>";
print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td>";
?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
