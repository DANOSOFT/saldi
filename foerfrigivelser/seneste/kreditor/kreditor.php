<?php

// -----------kreditor/kreditor.php--- lap 1.9.2 ---- 27.03.2008 -----------
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

$modulnr=8;
$title="kreditorliste";

include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/db_query.php");

?>
<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
  <tr><td height = "25" align="center" valign="top">
    <table width="100%" align="center" border="0" cellspacing="2" cellpadding="0"><tbody>
      <td width="10%" <?php echo $top_bund ?>><?php echo $font ?><small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>
      <td width="80%" <?php echo $top_bund ?>><?php echo $font ?><small>Kreditorliste</small></td>
      <td width="10%" <?php echo $top_bund ?>><?php echo $font ?><small><a href=kreditorkort.php accesskey=N>Ny</a></small></td>
      </tbody></table>
  </td></tr>
 <tr><td valign="top">
<table cellpadding="1" cellspacing="1" border="0	" width="100%" valign = "top">
<tbody>
  <tr>
   <td><small><b><?php echo $font ?><a href=kreditor.php?sort=kontonr>Leverand&oslash;rnr</b></small></td>
   <td><small><b><?php echo $font ?><a href=kreditor.php?sort=firmanavn>Navn</a></b></small></td>
   <td><small><b><?php echo $font ?><a href=kreditor.php?sort=addr1>Adresse</a></b></small></td>
   <td><small><b><?php echo $font ?><a href=kreditor.php?sort=addr2>Adresse2</a></b></small></td>
   <td><small><b><?php echo $font ?><a href=kreditor.php?sort=postnr>Postnr</a></b></small></td>
   <td><small><b><?php echo $font ?><a href=kreditor.php?sort=bynavn>By</a></b></small></td>
   <td><small><b><?php echo $font ?><a href=kreditor.php?sort=kontakt>Kontaktperson</a></b></small></td>
   <td><small><b><?php echo $font ?><a href=kreditor.php?sort=tlf>Telefon</a></b></small></td>
  </tr>
  <?php

   $sort=isset($_GET['sort'])? $_GET['sort']:Null;
   if (!$sort) $sort = "firmanavn";

$query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'K' order by $sort");
while ($row = db_fetch_array($query))
{
  print "<tr>";
  print "<td><small>$font <a href=kreditorkort.php?id=$row[id]>$row[kontonr]</a><br></small></td>";
  print "<td><small>$font ".stripslashes(htmlentities($row['firmanavn']))."<br></small></td>";
  print "<td><small>$font ".stripslashes(htmlentities($row['addr1']))."<br></small></td>";
  print "<td><small>$font ".stripslashes(htmlentities($row['addr2']))."<br></small></td>";
  print "<td><small>$font ".stripslashes(htmlentities($row['postnr']))."<br></small></td>";
  print "<td><small>$font ".stripslashes(htmlentities($row['bynavn']))."<br></small></td>";
  print "<td><small>$font ".stripslashes(htmlentities($row['kontakt']))."<br></small></td>";
  print "<td><small>$font ".stripslashes(htmlentities($row['tlf']))."<br></small></td>";
  print "</tr>";
}
?>
</tbody>
</table>
  </td></tr>
</tbody></table>

</body></html>
