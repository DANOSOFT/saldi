<?php

// -----------kreditor/kreditor.php--- lap 2.0.7 ---- 2009.05.18 -----------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------


@session_start();
$s_id=session_id();

$modulnr=8;
$title="kreditorliste";
$css="../css/standard.css";


include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
  <tr><td height = \"25\" align=\"center\" valign=\"top\">
    <table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>
      <td width=\"10%\" $top_bund><a href=$returside accesskey=L>Luk</a></td>
      <td width=\"80%\" $top_bund>Kreditorliste</td>
      <td width=\"10%\" $top_bund><a href=kreditorkort.php accesskey=N>Ny</a></td>
      </tbody></table>
  </td></tr>
 <tr><td valign=\"top\">
<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">
<tbody>
  <tr>
   <td><b><a href=kreditor.php?sort=kontonr>Leverand&oslash;rnr</b></td>
   <td><b><a href=kreditor.php?sort=firmanavn>Navn</a></b></td>
   <td><b><a href=kreditor.php?sort=addr1>Adresse</a></b></td>
   <td><b><a href=kreditor.php?sort=addr2>Adresse2</a></b></td>
   <td><b><a href=kreditor.php?sort=postnr>Postnr</a></b></td>
   <td><b><a href=kreditor.php?sort=bynavn>By</a></b></td>
   <td><b><a href=kreditor.php?sort=kontakt>Kontaktperson</a></b></td>
   <td><b><a href=kreditor.php?sort=tlf>Telefon</a></b></td>
  </tr>";


   $sort=isset($_GET['sort'])? $_GET['sort']:Null;
   if (!$sort) $sort = "firmanavn";

$query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'K' order by $sort",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query))
{
	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
	else {$linjebg=$bgcolor5; $color='#000000';}
	print "<tr bgcolor=\"$linjebg\">";
	if ($popup) { 
		$href="onClick=\"javascript:kreditorkort=window.open('kreditorkort.php?id=$row[id]&returside=../includes/luk.php','kreditorkort','".$jsvars."');kreditorkort.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\";";
		print "<td $href><u>$row[kontonr]</u><br></td>";
	} else 	print "<td> <a href=kreditorkort.php?id=$row[id]>$row[kontonr]</a><br></td>";
  print "<td> ".htmlentities($row['firmanavn'],ENT_COMPAT,$charset)."<br></td>";
  print "<td> ".htmlentities($row['addr1'],ENT_COMPAT,$charset)."<br></td>";
  print "<td> ".htmlentities($row['addr2'],ENT_COMPAT,$charset)."<br></td>";
  print "<td> ".htmlentities($row['postnr'],ENT_COMPAT,$charset)."<br></td>";
  print "<td> ".htmlentities($row['bynavn'],ENT_COMPAT,$charset)."<br></td>";
  print "<td> ".htmlentities($row['kontakt'],ENT_COMPAT,$charset)."<br></td>";
  print "<td> ".htmlentities($row['tlf'],ENT_COMPAT,$charset)."<br></td>";
  print "</tr>";
}
?>
</tbody>
</table>
  </td></tr>
</tbody></table>

</body></html>
