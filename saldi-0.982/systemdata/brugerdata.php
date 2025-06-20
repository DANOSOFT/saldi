<?
// -------------------------------------------------brugerdata.php ------050317---------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public Licenser (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg, dog med med
// foelgende tilfoejelse:
//
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
include("../includes/db_query.php");

if ($aktiver)
{
  db_modify("update online set regnskabsaar = '$aktiver' where session_id = '$s_id'");
  include("../includes/online.php");
  db_modify("update brugere set regnskabsaar = '$aktiver' where brugernavn = '$brugernavn'");
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SALDI - Brugerindstillinger</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"></head>
<body bgcolor="#339999" link="#000000" vlink="#000000" alink="#000000" center="">
<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
  <tr><td align="center" valign="top">
    <table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody><td>
      <table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
      <td width="25%" bgcolor="<? echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><a href=../index/menu.php accesskey=T>Tilbage</a></small></td>
      <td width="50%" bgcolor="<? echo $bgcolor2 ?>" align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Brugerindstillinger for <? print $brugernavn." | ".$regnskab ?></small></td>
      <td width="25%" bgcolor="<? echo $bgcolor2 ?>" align = "right"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
      </tbody></table></td>
    </tbody></table>
  </td></tr>
  <tr><td align="center" valign="top">
<?

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=\"70%\"><tbody>";
print "<tr><td width = 50%><b>$font Beskrivelse</a></b></td>";
print "    <td width = 10%><b>$font Start md.</a></b></td>";
print "    <td width = 10%><b>$font Start &aring;r</a></b></td>";
print "    <td width = 10%><b>$font Slut md.</a></b></td>";
print "    <td width = 10%><b>$font Slut &aring;r</a></b></td>";
print "    <td align = right><b>$font Tillad bogf.</a></b></td>";
print "    <td width = 10%><b>$font <br></a></b></td>     </tr>";
$query = db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'");
$row = db_fetch_array($query);
$regnaar = $row['regnskabsaar'];

$query = db_select("select * from grupper where art = 'RA' order by box2");
while ($row = db_fetch_array($query))
{
  if ($bgcolor!=$bgcolor1){$bgcolor=$bgcolor1; $color='#000000';}
  elseif ($bgcolor!=$bgcolor3){$bgcolor=$bgcolor3; $color='#000000';}
  print "<tr bgcolor=\"$bgcolor\">";
#  print "<td><a href=regnskabskort.php?id=$row[id]><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[kodenr]</a><br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[beskrivelse]<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[box1]<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[box2]<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[box3]<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[box4]<br></small></td>";
  if (strstr($row[box5],'on')){print "<td align=center><font face=\"Helvetica, Arial, sans-serif\">V</td>";}
  else {print "<td align=center><font face=\"Helvetica, Arial, sans-serif\"><br></td>";}
  if ($row[kodenr]!=$regnaar){ print "<td><a href=regnskabsaar.php?aktiver=$row[kodenr]><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">S&aelig;t aktivt</a><br></small></td>";}
  else{print "<td align=center><font face=\"Helvetica, Arial, sans-serif\" color=#ff0000>Aktivt</td>";}
  print "</tr>";
}
PRINT "</tbody></table></td></tr>";

if ($HTTP_POST_VARS)
{
  $glkode=md5(trim($HTTP_POST_VARS['glkode']));
  $nykode1=md5(trim($HTTP_POST_VARS['nykode1']));
  $nykode2=md5(trim($HTTP_POST_VARS['nykode2']));

  if ($nykode1==$nykode2)
  {
    $query = db_select("select kode from brugere where brugernavn='$brugernavn'");
    $row = db_fetch_array($query);
    if (trim($row[kode])==$glkode){db_modify("update brugere set kode='$nykode1' where brugernavn='$brugernavn'");}
    else {print "<tr><td align=center>$font Der er tastet forkert v&aelig;rdi i \"Gl. password\"</td></tr>";}
  }
  else {print "<tr><td align=center>$font Der er tastet forskellige v&aelig;rdier i \"Nyt password\" & \"Bekr&aelig;ft nyt pw\"</td>>/tr>";}
}



print "<form name=brugerdata action=brugerdata.php method=post>";
print "<tr><td align=center valign=top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
print "<tr><td align=center colspan=2>$font<b>Skift password</b></td></tr>";
print "<tr><td>$font Gl. password</td><td><input type=password size=20 name=glkode></td></tr>";
print "<tr><td>$font Nyt password</td><td><input type=password size=20 name=nykode1></td></tr>";
print "<tr><td>$font Bekr&aelig;ft nyt pw.</td><td><input type=password size=20 name=nykode2></td></tr>";
print "<td colspan=2 align = center><input type=submit value=\"Ok\" name=\"submit\"></td>";
print "</form";
print "</tr></tbody></table></td></tr>";
print "</td></tr>";
?>
</tbody></table>
</body></html>
