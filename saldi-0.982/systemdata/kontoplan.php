<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SALDI - Kontoplan</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"></head>
<div align="center">
<?
// ----------------------------------------------------------------------050423----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg

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
?>

<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
  <tr><td height = "25" align="center" valign="top">
    <table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
      <td width="25%" bgcolor="<? echo $bgcolor2 ?>" align="left"><font face="Helvetica, Arial, sans-serif"><small><a href=../includes/luk.php accesskey=T>Tilbage</a></small></td>
      <td width="50%" bgcolor="<? echo $bgcolor2 ?>" align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Kontoplan</small></td>
      <td width="25%" bgcolor="<? echo $bgcolor2 ?>" align="right"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><a href=kontokort.php accesskey=N>Ny</a></small></td>
      </tbody></table>
  </td></tr>
 <tr><td valign="top">
<table cellpadding="0" cellspacing="1" border="0" width="100%" valign = "top">
<tbody>
  <tr>
    <td><b><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>">Kontonr</b></td>
    <td><b><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>">Beskrivelse</a></b></td>
    <td><b><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>">Type</a></b></td>
    <td><b><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>">Moms</a></b></td>

  </tr>
  <?

  if (!$regnaar) {$regnaar=1;} 
  $query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr");
  while ($row = db_fetch_array($query))
  {

    if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
    elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}

    if ($row['kontotype']=='H') {$linjebg=$bgcolor4; $color='$000000';}
    print "<tr bgcolor=\"$linjebg\">";
    print "<td><small><a href=kontokort.php?id=$row[id]><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[kontonr]</a><br></small></td>";
    print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[beskrivelse]<br></small></td>";
    if ($row['kontotype']=='H'){print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><br></small></td>";}
    elseif ($row['kontotype']=='D'){print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">Drift<br></small></td>";}
    elseif ($row['kontotype']=='S'){print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">Status<br></small></td>";}
    elseif ($row['kontotype']=='Z'){print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">Sum $row[fra_kto] - $row[til_kto]<br></small></td>";}
    print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[moms]<br></small></td>";
    print "</tr>";
    if ($row[kontotype]=='H') {$linjebg=$bgcolor4; $color='#000000';}

  }

?>
</tbody>
</table>
  </td></tr>
</tbody></table>

</body></html>
