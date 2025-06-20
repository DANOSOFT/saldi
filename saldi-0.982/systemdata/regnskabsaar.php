<?
// ----------------------------------------------------------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaetelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$aktiver=$_GET['aktiver'];

include("../includes/connect.php");
if (!$aktiver){include("../includes/online.php");}
include("top.php");
include("../includes/db_query.php");


if ($aktiver)
{
  db_modify("update online set regnskabsaar = '$aktiver' where session_id = '$s_id'");
  include("../includes/online.php");
  db_modify("update brugere set regnskabsaar = '$aktiver' where brugernavn = '$brugernavn'");
}

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=\"70%\"><tbody>";

?>
<tbody>
  <tr>
    <td width = 10%><b><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>">ID</b></td>
    <td width = 40%><b><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>">Beskrivelse</a></b></td>
    <td width = 10%><b><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>">Start md.</a></b></td>
    <td width = 10%><b><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>">Start &aring;r</a></b></td>
    <td width = 10%><b><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>">Slut md.</a></b></td>
    <td width = 10%><b><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>">Slut &aring;r</a></b></td>
    <td align = right><b><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>">Tillad bogf.</a></b></td>
    <td width = 10%><b><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>"><br></a></b></td>
  </tr>
  <?
$query = db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'");
$row = db_fetch_array($query);
$regnaar = $row['regnskabsaar'];

$x=0;
$query = db_select("select * from grupper where art = 'RA' order by box2");
while ($row = db_fetch_array($query))
{
  $x++;
  if ($bgcolor1!=$bgcolor){$bgcolor1=$bgcolor; $color='#000000';}
  elseif ($bgcolor1!=$bgcolor5){$bgcolor1=$bgcolor5; $color='#000000';}
  print "<tr bgcolor=\"$bgcolor1\">";
  print "<td><a href=regnskabskort.php?id=$row[id]><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[kodenr]</a><br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[beskrivelse]<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[box1]<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[box2]<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[box3]<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[box4]<br></small></td>";
  if (strstr($row[box5],'on')){print "<td align=center><font face=\"Helvetica, Arial, sans-serif\">V</td>";}
  else {print "<td align=center><font face=\"Helvetica, Arial, sans-serif\"><br></td>";}
  if ($row[kodenr]!=$regnaar){ print "<td><a href=regnskabsaar.php?aktiver=$row[kodenr]><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">S&aelig;t aktivt</a><br></small></td>";}
  else{ print "<td><font face=\"Helvetica, Arial, sans-serif\" color=#ff0000>Aktivt</td>";}
  print "</tr>";
}
if ($x<1) {print "<meta http-equiv=refresh content=0;url=regnskabskort.php>";}


?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
