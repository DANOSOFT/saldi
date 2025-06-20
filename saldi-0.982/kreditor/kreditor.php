<?

// ----------------------------------------------------------------------2006-03-27------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// SQL finans maa kun efter skriftelig aftale med ITz ApS anvendes som
// vaert for andre virksomheders regnskaber.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2006 ITz ApS
// ----------------------------------------------------------------------


@session_start();
$s_id=session_id();

$modulnr=8;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SALDI - Kreditorliste</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"></head>
<body bgcolor="#339999" link="#000000" vlink="#000000" alink="#000000" center="">
<div align="center">

<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
  <tr><td height = "25" align="center" valign="top">
    <table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
      <td width="25%" bgcolor="<? echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>"><small><a href=../includes/luk.php accesskey=T>Tilbage</a></small></td>
      <td width="50%" bgcolor="<? echo $bgcolor2 ?>" align="center"><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>"><small>Kreditorliste</small></td>
      <td width="25%" bgcolor="<? echo $bgcolor2 ?>" align="right"><font face="Helvetica, Arial, sans-serif" color="<? echo $bgcolor2 ?>"><small><a href=kreditorkort.php accesskey=N>Ny</a></small></td>
      </tbody></table>
  </td></tr>
 <tr><td valign="top">
<table cellpadding="1" cellspacing="1" border="0	" width="100%" valign = "top">
<tbody>
  <tr>
   <td><small><b><font face="Helvetica, Arial, sans-serif"><a href=kreditor.php?sort=kontonr>Leverand&oslash;rnr</b></small></td>
   <td><small><b><font face="Helvetica, Arial, sans-serif"><a href=kreditor.php?sort=firmanavn>Navn</a></b></small></td>
   <td><small><b><font face="Helvetica, Arial, sans-serif"><a href=kreditor.php?sort=addr1>Adresse</a></b></small></td>
   <td><small><b><font face="Helvetica, Arial, sans-serif"><a href=kreditor.php?sort=addr2>Adresse2</a></b></small></td>
   <td><small><b><font face="Helvetica, Arial, sans-serif"><a href=kreditor.php?sort=postnr>Postnr</a></b></small></td>
   <td><small><b><font face="Helvetica, Arial, sans-serif"><a href=kreditor.php?sort=bynavn>By</a></b></small></td>
   <td><small><b><font face="Helvetica, Arial, sans-serif"><a href=kreditor.php?sort=kontakt>Kontaktperson</a></b></small></td>
   <td><small><b><font face="Helvetica, Arial, sans-serif"><a href=kreditor.php?sort=tlf>Telefon</a></b></small></td>
  </tr>
  <?

   $sort = $_GET['sort'];
   if (!$sort) {$sort = firmanavn;}

$query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'K' order by $sort");
while ($row = db_fetch_array($query))
{
  print "<tr>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\"><a href=kreditorkort.php?id=$row[id]>$row[kontonr]</a><br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\">".stripslashes(htmlentities($row[firmanavn]))."<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\">".stripslashes(htmlentities($row[addr1]))."<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\">".stripslashes(htmlentities($row[addr2]))."<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\">".stripslashes(htmlentities($row[postnr]))."<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\">".stripslashes(htmlentities($row[bynavn]))."<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\">".stripslashes(htmlentities($row[kontakt]))."<br></small></td>";
  print "<td><small><font face=\"Helvetica, Arial, sans-serif\">".stripslashes(htmlentities($row[tlf]))."<br></small></td>";
  print "</tr>";
}
?>
</tbody>
</table>
  </td></tr>
</tbody></table>

</body></html>
