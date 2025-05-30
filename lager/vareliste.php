<?
// --------------------------------------------------lager/vareliste.php     patch 0.971----------
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

print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>SALDI - varer</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
# include("../includes/db_query.php");

$vis_lev=$_GET['vis_lev'];
$sort = $_GET['sort'];
if (!$sort) {$sort = "varenr";}



print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small><a href=../includes/luk.php accesskey=T>Tilbage</a></small></td>\n";
print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small>Vareliste</small></td>\n";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small></small></td>\n";
print "</tbody></table>\n";
print "</td></tr>\n";
print "<tr><td valign=\"top\">\n";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">\n";
print "<tbody>\n";
if (!$vis_lev) {
  $x=0;
  $lagernavn[0]="Hovedlager";
  $query = db_select("select beskrivelse, kodenr from grupper where art='LG' order by kodenr");
  while ($row = db_fetch_array($query)) {
    $x++;
    $lagernavn[$x]=$row['beskrivelse'];       
  }
  $lagerantal=$x;
  $x=$x+6; #kolonneantal;
  print "<tr><td colspan=$x align=center><a href=varer.php?vis_lev=on&sort=$sort>$font<small>Vis lev. info</a></td></tr>";
}
else {print "<tr><td colspan=9 align=center><a href=varer.php?sort=$sort>$font<small>Udelad lev. info</a></td></tr>";}
print "</form>";
print "<tr>";
print "<td><small><b>$font<a href=varer.php?sort=varenr&vis_lev=$vis_lev>Varenr</b></small></td>\n";
print "<td><small><b>$font<a href=varer.php?sort=enhed&vis_lev=$vis_lev>Enhed</b></small></td>\n";
print "<td><small><b>$font<a href=varer.php?sort=beskrivelse&vis_lev=$vis_lev>Beskrivelse</a></b></small></td>\n";
if (!$vis_lev){
  if ($lagerantal>=1) {
    for ($x=0;$x<=$lagerantal; $x++) {
      print "<td align=right><small><b>$font<span title= '$lagernavn[$x]'>L $x</b></small></td>\n";
    }
  print "<td align=right><small><b>$font<a href=varer.php?sort=beholdning&vis_lev=$vis_lev>Ialt</a></b></small></td>\n";
  }
  else {
    print "<td align=right><small><b>$font<a href=varer.php?sort=beholdning&vis_lev=$vis_lev>Beholdn.</a></b></small></td>\n";
  }
}
print "<td align=right><small><b>$font<a href=varer.php?sort=salgspris&vis_lev=$vis_lev>Salgspris</a></b></small></td>\n";
if ($vis_lev) {
  print "<td align=right><small><b>$font Kostpris</b></small></td>\n";
  print "<td align=right><small><b>$font Beholdn.</b></small></td>\n";  
  print "<td>&nbsp;</td>\n";
  print "<td><small><b>$font Leverand&oslash;r</b></small></td>\n";
  print "<td><small><b>$font Lev. varenr</small></td>\n";
}
print "</tr>\n";

$query = db_select("select * from varer where lukket != '1' order by $sort");
while ($row = db_fetch_array($query))
{
  if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
  else {$linjebg=$bgcolor5; $color='#000000';}
  print "<tr bgcolor=\"$linjebg\">";
  print "<td><small>$font<a href=varekort.php?id=$row[id]>".htmlentities(stripslashes($row[varenr]))."</a><br></small></td>";
  print "<td><small>$font ".htmlentities(stripslashes($row[enhed]))."<br></small></td>";
  print "<td><small>$font ".htmlentities(stripslashes($row[beskrivelse]))."<br></small></td>";
#  if (!$row[beholdning]){$row[beholdning]=0;}
  if (!$vis_lev){
    if ($lagerantal>=1) { 
    for ($x=0;$x<=$lagerantal; $x++) {
        $r2=db_fetch_array(db_select("select lager, beholdning from lagerstatus where vare_id = $row[id] and lager = $x"));
        $y=$r2[beholdning];
#        if (!$y) {$y='0';} 
        print "<td align=center onClick=\"lagerflyt($row[id], $x)\">$font<span title= 'Flyt til andet lager'><a href><small>$y</small></a></td></td>";
      }
    }
    print "<td align=right>$linjetext<small>$font $row[beholdning]</small></span></td>";
  }
  $salgspris=dkdecimal($row[salgspris]);
  print "<td align=right><small>$font $salgspris<br></small></td>";
  if ($vis_lev==on) {
    $query2 = db_select("select kostpris, lev_id, lev_varenr from vare_lev where vare_id = $row[id] order by posnr");
    $row2 = db_fetch_array($query2);
    if ($row2[lev_id])
    {
      $lev_varenr=$row2['lev_varenr'];
      $levquery = db_select("select kontonr, firmanavn from adresser where id=$row2[lev_id]");
      $levrow = db_fetch_array($levquery);
      $kostpris=dkdecimal($row2[kostpris]);
    }
    elseif ($row[samlevare]=='on') {$kostpris=dkdecimal($row[kostpris]);}
    print "<td align=right><small>$font $kostpris</small></td>";
    $query2 = db_select("select box8 from grupper where art='VG' and kodenr='$row[gruppe]'");
    $row2 =db_fetch_array($query2);
    if (($row2[box8]=='on')||($row[samlevare]=='on'))
    {
       $ordre_id=array();
       $x=0;
       $query2 = db_select("select id from ordrer where status >= 1  and status < 3 and art = 'DO'");
       while ($row2 =db_fetch_array($query2))
       {
         $x++;
         $ordre_id[$x]=$row2[id];
       }
       $x=0;
       $query2 = db_select("select id, ordre_id, antal from ordrelinjer where vare_id = $row[id]");
       while ($row2 =db_fetch_array($query2))
       {
         if (in_array($row2[ordre_id],$ordre_id))
         {
           $x=$x+$row2[antal];   
           $query3 = db_select("select antal from batch_salg where linje_id = $row2[id]");
           while ($row3=db_fetch_array($query3)) {$x=$x-$row3[antal];}
         }
       }  
       $linjetext="<span title= 'Der er $x i ordre'>";
       print "<td align=right>$linjetext<small>$font $row[beholdning]</small></span></td>";    
       print "<td></td>";    
       print "<td><small>$font $levrow[kontonr] - ".htmlentities(stripslashes($levrow[firmanavn]))."</small></td>";
       print "<td><small>$font ".htmlentities(stripslashes($lev_varenr))."</small></td>";
    }
    else {print "<td></td>";}   
  }
  print "</tr>\n";
}
  print "<tr><td colspan=9><hr></td></tr>";
  $query = db_select("select id, varenr, beskrivelse, salgspris, kostpris from varer where lukket = '1' order by $sort");
  while ($row = db_fetch_array($query))
  {
    print "<tr>";
    print "<td><small>$font <a href=varekort.php?id=$row[id]>$row[varenr]</a><br></small></td>";
  print "<td><small>$font ".htmlentities(stripslashes($row[beskrivelse]))."<br></small></td>";
  $salgspris=dkdecimal($row[salgspris]);
  print "<td align=right><small>$font $salgspris<br></small></td>";
  $query2 = db_select("select kostpris, lev_id, lev_varenr from vare_lev where vare_id = $row[id] order by posnr");
  $row2 = db_fetch_array($query2);
  $kostpris=dkdecimal($row2[kostpris]);
  print "<td align=right><small>$font $kostpris<br></small></td>";
  print "<td align=right><small>$font $row[beholdning]<br></small></td>";
  print "<td>&nbsp;</td>";
  $levquery = db_select("select kontonr, firmanavn from adresser where id=$row2[lev_id]");
  $levrow = db_fetch_array($levquery);
  print "<td><small>$font $levrow[kontonr] - ".htmlentities(stripslashes($levrow[firmanavn]))."</small></td>";
  print "<td><small>$font ".htmlentities(stripslashes($row2[lev_varenr]))."</a><br></small></td>";
  print "</tr>";
}
?>
</tbody>
</table>
  </td></tr>
</tbody></table>

</body></html>
