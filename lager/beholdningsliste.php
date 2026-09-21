<?php
// --------------------------------------------------lager/beholdningsliste.php     patch 0.983----------
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
// 20260920 CDX/LUI Keep missing-warehouse reports authenticated and rendering read-only.

@session_start();
$s_id = session_id();

$modulnr = 9;
$title = "Beholdningsliste";
include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
require_once(__DIR__ . "/../includes/std_func.php");

$vis_lev = ifset($_GET, 'vis_lev', '');
$vis_kost = ifset($_GET, 'vis_kost', '');
$sort = ifset($_GET, 'sort', 'varenr');
if (!in_array($sort, ['varenr', 'beskrivelse', 'salgspris', 'beholdning'], true)) {
    $sort = 'varenr';
}
$lager = null;
$lagernavn = '';
$linjebg = '';
$x = (int)ifset($_GET, 'x', 0);
$id = (int)ifset($_GET, 'id', 0);
$fokus = htmlspecialchars((string)ifset($_GET, 'fokus', ''), ENT_QUOTES, 'UTF-8');

if (!isset($_GET["lager"])) {
    $escapedUser = db_escape_string($brugernavn);
    $row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$escapedUser'", __FILE__ . " linje " . __LINE__));
    if (!empty($row["ansat_id"])) {
        $row = db_fetch_array(db_select("select navn from ansatte where id = $row[ansat_id]", __FILE__ . " linje " . __LINE__));
        if (!empty($row["navn"])) {
            $ref = db_escape_string($row['navn']);
            if ($row = db_fetch_array(db_select("select afd from ansatte where navn = '$ref'", __FILE__ . " linje " . __LINE__))) {
                if ($row = db_fetch_array(db_select("select beskrivelse, kodenr from grupper where box1='$row[afd]' and art='LG'", __FILE__ . " linje " . __LINE__))) {
                    $lager = $row['kodenr'] * 1;
                    $lagernavn = $row['beskrivelse'];
                }
            }
        }
    }
} else {
    $lager = (int)$_GET["lager"];
}
if (!$lager) {
    print "<h1>Beholdningsliste</h1><p>Der er ikke valgt eller tilknyttet et lager. Vælg lager fra varerapporten, eller få din lagertilknytning opdateret.</p>";
    print "<p><a href='rapport.php'>Tilbage til varerapporter</a></p></body></html>";
    exit;
}
if ($lager > 0) {
    $warehouse = db_fetch_array(db_select("SELECT beskrivelse FROM grupper WHERE art='LG' AND kodenr=$lager", __FILE__ . " linje " . __LINE__));
    if (!$warehouse) {
        print "<h1>Beholdningsliste</h1><p>Det valgte lager findes ikke.</p></body></html>";
        exit;
    }
    $lagernavn = htmlspecialchars((string)$warehouse['beskrivelse'], ENT_QUOTES, 'UTF-8');
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>\n";
print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small>Beholdningsliste - $lagernavn</small></td>\n";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small></small></td>\n";
print "</tbody></table>\n";
print "</td></tr>\n";
print "<tr><td valign=\"top\">\n";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">\n";
print "<tbody>\n";

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
print "<tbody><tr>";
if ($vis_kost) {
    print "<tr><td colspan=8 align=center><a href=beholdningsliste.php?lager=$lager&sort=varenr&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id>$font<small>Udelad kostpriser</a></td></tr>";
} else {
    print "<tr><td colspan=4 align=center><a href=beholdningsliste.php?lager=$lager&sort=varenr&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis_kost=on>$font<small>Vis kostpriser</a></td></tr>";
}
print "<td><small><b>$font<a href=beholdningsliste.php?lager=$lager&sort=varenr&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis_kost=$vis_kost>Varenr</a></b></small></td>";
print "<td><small><b>$font<a href=beholdningsliste.php?lager=$lager&sort=beskrivelse&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beskrivelse</a></b></small></td>";
print "<td align=right><small><b>$font<a href=beholdningsliste.php?lager=$lager&sort=salgspris&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id>Salgspris</a></b></small></td>";
if ($vis_kost) {
    print "<td align=right><small><b>$font Kostpris</b></small></td>";
}
print "<td align=right><small><b>$font<a href=beholdningsliste.php?lager=$lager&sort=beholdning&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beh.</a></b></small></td>";
print "<td><br></td>";
#  print"<td><br></td><td><small><b>$fontKunde</b></small></td>";
print " </tr>\n";

if (!$sort) {
    $sort = "varenr";
}

$query = db_select("select * from varer where lukket != '1' order by $sort", __FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
    // A report must not repair stock records as a side effect of viewing them.
    // Keep absent amounts local to this rendering; inventory writes belong to
    // the stock adjustment and receipt workflows.
    $row['beholdning'] = $row['beholdning'] ?? 0;

    if ($linjebg != $bgcolor) {
        $linjebg = $bgcolor;
        $color = '#000000';
    } else {
        $linjebg = $bgcolor5;
        $color = '#000000';
    }
    print "<tr bgcolor=\"$linjebg\">";
    print "<td><small>$font $row[varenr]</a></small></td>";
    print "<td><small>$font $row[beskrivelse]<br></small></td>";
    $salgspris = dkdecimal($row["salgspris"]);
    print "<td align=right><small>$font $salgspris<br></small></td>";
    if ($vis_kost == 'on') {
        $query2 = db_select("select kostpris from vare_lev where vare_id = $row[id] order by posnr", __FILE__ . " linje " . __LINE__);
        $row2 = db_fetch_array($query2);
        $kostpris = dkdecimal($row2["kostpris"] ?? 0);
        print "<td align=right><small>$font $kostpris<br></small></td>";
    }
    $reserveret = 0;
    #    $linjetext="<span title= 'Der er $y i tilbud og $z i ordre '>";
    if ($lager >= 1) {
        $q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest>0 and lager=$lager", __FILE__ . " linje " . __LINE__);
        while ($r2 = db_fetch_array($q2)) {
            $q3 = db_select("select * from reservation where batch_kob_id=$r2[id]", __FILE__ . " linje " . __LINE__);
            while ($r3 = db_fetch_array($q3)) {
                $reserveret = $reserveret + $r3["antal"];
            }
        }
        $linjetext = "<span title= 'Reserveret: $reserveret'>";
        if ($r2 = db_fetch_array(db_select("select beholdning from lagerstatus where vare_id=$row[id] and lager=$lager", __FILE__ . " linje " . __LINE__))) {
            print "<td align=right>$linjetext<small>$font ".dkdecimal($r2['beholdning'])."</small></span></td>";
        } else {
            print "<td align=right>$linjetext<small>$font 0,00</small></span></td>";
        }
    } else {
        $q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest > 0", __FILE__ . " linje " . __LINE__);
        while ($r2 = db_fetch_array($q2)) {
            $q3 = db_select("select * from reservation where batch_kob_id=$r2[id]", __FILE__ . " linje " . __LINE__);
            while ($r3 = db_fetch_array($q3)) {
                $reserveret = $reserveret + $r3["antal"];
            }
        }
        $linjetext = "<span title= 'Reserveret: $reserveret'>";
        print "<td align=right>$linjetext<small>$font ".dkdecimal($row['beholdning'])."</small></span></td>";
    }
    print "</tr>\n";
}
print "</tbody></table></td></tr></tbody></table>";


/*





if (!$vis_lev) {
  $x=0;
#  $lagernavn[0]="Hovedlager";
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
*/
?>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>

</body>

</html>