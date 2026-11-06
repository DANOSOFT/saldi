<?php
// --------------------------------------------------lager/beholdningsliste.php     ver 5.0.0 --- 20261027 ----------
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
// Copyright (c) 2004-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261027 LOE MB-99 opening the list no longer writes, the sort/lager input is whitelisted or cast, and
//                a user without a warehouse gets a picker instead of being logged out (SD-623).
// 20261027 LOE MB-99 (review) the cost-price switch only accepts its one value, and the balance column
//                sorts by the warehouse stock it shows.
// 20261027 CL/LH MB-99 (review) the warehouse balance is summed over the item's lagerstatus rows (one per
//                variant) in the sort and in the column, and the totals sort counts a missing balance as 0.

@session_start();
$s_id = session_id();

include("../includes/connect.php");
# MB-99 the document declares the charset the tenant actually stores, not a fixed ISO-8859-1
$charset = ($db_encode == "UTF8") ? "UTF-8" : "ISO-8859-1";
print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>SALDI - beholdningsliste</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\"></head><body>";

# $modulnr=9;

include("../includes/online.php");
# MB-99 the page now reads and formats input, so it needs if_isset() and findtekst() explicitly
include("../includes/std_func.php");
include("../includes/stdFunc/dkDecimal.php");
# include("../includes/db_query.php");

# MB-99 only the columns the list can sort by are accepted, so nothing from the query string
# reaches the ORDER BY
$sorteringer = array('varenr', 'beskrivelse', 'salgspris', 'beholdning');
$sort = if_isset($_GET, '', 'sort');
if (!in_array($sort, $sorteringer, true)) {
    $sort = 'varenr';
}
# MB-99 (review) the flag is a switch and is put back into the links below, so nothing else is accepted
$vis_kost = (if_isset($_GET, '', 'vis_kost') === 'on') ? 'on' : '';

# MB-99 the warehouse is a number and must exist, or every query below would take it as given
$lager = (int) if_isset($_GET, 0, 'lager');
$lagernavn = '';
if ($lager) {
    if ($row = db_fetch_array(db_select("select beskrivelse from grupper where art='LG' and kodenr='$lager'", __FILE__ . " linje " . __LINE__))) {
        $lagernavn = $row['beskrivelse'];
    } else {
        $lager = 0;
    }
}

# without a warehouse of its own the page follows the employee's department, as the menu does
if (!$lager && !empty($ansat_id)) {
    $row = db_fetch_array(db_select("select afd from ansatte where id = '$ansat_id'", __FILE__ . " linje " . __LINE__));
    if ($row['afd']) {
        $afd = db_escape_string($row['afd']);
        if ($row = db_fetch_array(db_select("select beskrivelse, kodenr from grupper where box1='$afd' and art='LG' order by kodenr", __FILE__ . " linje " . __LINE__))) {
            $lager = (int) $row['kodenr'];
            $lagernavn = $row['beskrivelse'];
        }
    }
}
# MB-99 a user without a warehouse link gets the warehouses to choose from: ending their session
# (SD-623) told them nothing and cost them the login
if (!$lager) {
    $lagre = array();
    $q = db_select("select beskrivelse, kodenr from grupper where art='LG' order by kodenr", __FILE__ . " linje " . __LINE__);
    while ($row = db_fetch_array($q)) {
        $lagre[] = $row;
    }
    if (!$lagre) {
        # MB-99 no warehouse is set up in this account: the list shows the totals instead of an
        # empty picker, which is what the page did before it had a choice of warehouses
        $lager = 0;
    } else {
        print "<center><h3>" . findtekst('980|Beholdning', $sprog_id) . "</h3>\n";
        print "<p>" . findtekst('586|Vælg', $sprog_id) . ":</p>\n<ul>\n";
        foreach ($lagre as $lagerrow) {
            print "<li><a href='beholdningsliste.php?lager=" . (int) $lagerrow['kodenr'] . "'>" . htmlspecialchars($lagerrow['beskrivelse'], ENT_QUOTES, $charset) . "</a></li>\n";
        }
        print "</ul></center>\n</body>\n</html>\n";
        exit;
    }
}
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>\n";
print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small>Beholdningsliste - " . htmlspecialchars($lagernavn, ENT_QUOTES, $charset) . "</small></td>\n";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small></small></td>\n";
print "</tbody></table>\n";
print "</td></tr>\n";
print "<tr><td valign=\"top\">\n";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">\n";
print "<tbody>\n";

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
print "<tbody><tr>";
# MB-99 the links keep the chosen warehouse, and no longer carry parameters the page never read
if ($vis_kost) {
    print "<tr><td colspan=8 align=center><a href='beholdningsliste.php?sort=$sort&amp;lager=$lager'>" . findtekst('1132|Skjul', $sprog_id) . " " . findtekst('950|Kostpris', $sprog_id) . "</a></td></tr>";
} else {
    print "<tr><td colspan=4 align=center><a href='beholdningsliste.php?sort=$sort&amp;lager=$lager&amp;vis_kost=on'>" . findtekst('560|Vis kostpriser', $sprog_id) . "</a></td></tr>";
}
print "<td><small><b>$font<a href='beholdningsliste.php?sort=varenr&amp;lager=$lager&amp;vis_kost=$vis_kost'>" . findtekst('917|Varenr.', $sprog_id) . "</a></b></small></td>";
print "<td><small><b>$font<a href='beholdningsliste.php?sort=beskrivelse&amp;lager=$lager&amp;vis_kost=$vis_kost'>" . findtekst('914|Beskrivelse', $sprog_id) . "</a></b></small></td>";
print "<td align=right><small><b>$font<a href='beholdningsliste.php?sort=salgspris&amp;lager=$lager&amp;vis_kost=$vis_kost'>" . findtekst('949|Salgspris', $sprog_id) . "</a></b></small></td>";
if ($vis_kost) {
    print "<td align=right><small><b>$font " . findtekst('950|Kostpris', $sprog_id) . "</b></small></td>";
}
print "<td align=right><small><b>$font<a href='beholdningsliste.php?sort=beholdning&amp;lager=$lager&amp;vis_kost=$vis_kost'>" . findtekst('980|Beholdning', $sprog_id) . "</a></b></small></td>";
print "<td><br></td>";
print " </tr>\n";

if (!$sort) {
    $sort = "varenr";
}

# MB-99 (review) the Beh. column shows the selected warehouse's stock, so the sort follows it; without a
# warehouse (a totals list) the item's own balance is still what the column shows. An item has one
# lagerstatus row per variant in a warehouse, so the stock is their sum (a bare subquery aborts the list
# with "more than one row"), and a missing row or balance sorts as the 0 the column prints
$orderby = $sort;
if ($sort === 'beholdning') {
    $orderby = ($lager >= 1) ? "coalesce((select sum(beholdning) from lagerstatus where vare_id = varer.id and lager = $lager), 0)" : 'coalesce(beholdning, 0)';
}
$linjebg = NULL;
$query = db_select("select * from varer where lukket != '1' order by $orderby", __FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
    # MB-99 nothing on a GET may write: an item without a balance is shown with 0, never repaired
    if ($row['beholdning'] === NULL || $row['beholdning'] === '') {
        $row['beholdning'] = 0;
    }

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
        $kostpris = dkdecimal($row2["kostpris"]);
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
        # MB-99 (review) the warehouse stock is the sum of the item's variant rows, the same figure the sort uses
        $r2 = db_fetch_array(db_select("select coalesce(sum(beholdning), 0) as beholdning from lagerstatus where vare_id=$row[id] and lager=$lager", __FILE__ . " linje " . __LINE__));
        print "<td align=right>$linjetext<small>$font ".dkdecimal($r2['beholdning'])."</small></span></td>";
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