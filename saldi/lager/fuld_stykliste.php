<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SALDI - varekort</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"></head>
<?php

// ----------------------------------------------------------------------050306----------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------


@session_start();
$s_id=session_id();

$modulnr=9;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
# include("../includes/db_query.php");

$id=$_GET[id];  

$query = db_select("select * from styklister where indgaar_i=$id");
while ($row = db_fetch_array($query))
{      
  for ($c=1;$c<=$row[antal];$c++)
  {
    $x++;
    $s_vare_id[$x]=$row[vare_id];
  }
}  
$antal_s=$x;
$b=0;
$basisvare=array();  
for ($a=1; $a<=$antal_s; $a++)
{
  $query = db_select("select * from styklister where indgaar_i = $s_vare_id[$a]"); 
  while ($row = db_fetch_array($query))
  {
    for ($c=1;$c<=$row[antal];$c++)
    {
      $x++;
      $s_vare_id[$x]=$row[vare_id];
    }
  }
  if ($antal_s==$x) 
  {
    if (!in_array($s_vare_id[$a], $basisvare))
    {
      $b++;
      $basisvare[$b]=$s_vare_id[$a];
      $basisantal[$b]=1;
    }
    else
    {
      for ($c=1; $c<=$b; $c++)
      {
        if ($basisvare[$c]==$s_vare_id[$a]) {$basisantal[$c]=$basisantal[$c]+1;}
      }
    }
  }
  else {$antal_s=$x;}
}
$query = db_select("select varenr, beskrivelse from varer where id=$id");
$row = db_fetch_array($query);
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=80% align=center><tbody>";
print "<tr><td colspan=6 align=center>$font<big><b>Fuld stykliste for Varenr <a href=varekort.php?id=$id>$row[varenr]</a>&nbsp;$row[beskrivelse]</td></tr>";
print "<tr><td align=center>$font Varenr.</td><td align=center>$font Beskrivelse</td><td align=center>$font Kostpris</td><td align=center>$font Antal(Lager)</td><td align=center>$font Sum</td></tr>";

for ($c=1; $c<=$b; $c++)
{
  $query = db_select("select * from varer where id=$basisvare[$c]");
  $row = db_fetch_array($query);
  $query2 = db_select("select kostpris from vare_lev where vare_id=$row[id] order by posnr");
  $row2 = db_fetch_array($query2);
  $sum=$row2[kostpris]*$basisantal[$c];
  $ialt=$ialt+$sum;
  $pris=dkdecimal($row2[kostpris]);
  $sum=dkdecimal($sum);
  print "<tr><td><a href=varekort.php?id=$basisvare[$c]>$font $row[varenr]</a></td><td>$font $row[beskrivelse]</td><td align=right>$font $pris</td><td align=right>$font $basisantal[$c]($row[beholdning])</td><td align=right>$font $sum</td></tr>";
}
$ialt=dkdecimal($ialt);
print "<tr><td colspan=5><br></td></tr><tr><td colspan=4>$font I alt</td></td><td align=right>$font $ialt</td></tr>";
print "<tbody></table>";
?>
