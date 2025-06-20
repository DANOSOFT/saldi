<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SALDI - varekort</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"></head>
<?
// ----------------------------------------------------------------------050306----------
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
// Copyright (c) 2004-2005 ITz ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$modulnr=9;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
# include("../includes/db_query.php");

$id=$_GET[id];
$x=0;
$query = db_select("select * from styklister where indgaar_i=$id order by posnr");
while ($row = db_fetch_array($query))
{
  $x++;
  $vare_id[$x]=$row[vare_id];
  $antal[$x]=$row[antal];
  $posnr[$x]=$row[posnr];
}
$vareantal=$x;

$query = db_select("select varenr, beskrivelse from varer where id=$id");
$row = db_fetch_array($query);

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=80% align=center><tbody>";
print "<tr><td colspan=6 align=center>$font<big><b>Stykliste for Varenr <a href=varekort.php?id=$id>$row[varenr]</a>&nbsp;$row[beskrivelse]</td></tr>";
print "<tr><td align=center>$font Varenr:</td><td align=center>$font Beskrivelse</td><td align=center>$font Kostpris</td><td align=center>$font Antal</td><td align=center>$font Sum</td></tr>";

for ($x=1; $x<=$vareantal; $x++)
{
  $query = db_select("select * from varer where id=$vare_id[$x]");
  $row = db_fetch_array($query);
  $query2 = db_select("select kostpris from vare_lev where vare_id=$row[id] order by posnr");
  if ($row2 = db_fetch_array($query2))
  {
    $sum=$row2[kostpris]*$antal[$x];
    $ialt=$ialt+$sum;
    $pris=dkdecimal($row2[kostpris]);
  }
  else
  {
    $query2 = db_select("select kostpris from varer where id=$row[id]");
    $row2 = db_fetch_array($query2);
    $sum=$row2[kostpris]*$antal[$x];
    $ialt=$ialt+$sum;
    $pris=dkdecimal($row2[kostpris]);
  }
  $sum=dkdecimal($sum);
  print "<tr><td>$font<a href=varekort.php?id=$vare_id[$x]>$row[varenr]</a></td><td>$font $row[beskrivelse]</td><td align=right>$font $pris</td><td align=right>$font $antal[$x]</td><td align=right>$font $sum</td></tr>";
}
$ialt=dkdecimal($ialt);
print "<tr><td colspan=5><br></td></tr><tr><td colspan=4>$font I alt</td></td><td align=right>$font $ialt</td></tr>";
print "<tbody></table>";
?>
