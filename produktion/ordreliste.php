<?php
  // -------------------------------------------------------Produktionsordreliste---------------050317----------
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
// Copyright (c) 2004-2005 DANOSOFT ApS
// ----------------------------------------------------------------------
  
@session_start();
$s_id=session_id();
$modulnr=7;
    
$valg=$_GET['valg'];
$sort = $_GET['sort'];
$nysort = $_GET['nysort'];
$tidspkt=date("U");

print "<meta http-equiv=\"refresh\" content=\"10;URL=ordreliste.php?sort=$sort&nysort=$nysort&valg=$valg\">";
  
if (!$valg) {$valg = "ordrer";}
if (!$sort) {$sort = "firmanavn";}
elseif ($nysort==$sort){$sort=$sort." desc";}
elseif ($nysort) {$sort=$nysort;}
 
 if ($valg!="faktura") {print "<meta http-equiv=\"refresh\" content=\"10;URL=ordreliste.php?sort=$sort&valg=$valg\">";}
    
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/dkdecimal.php");

print "<div align=center>";
print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>";
print "<tr><td height = 25 align=center valign=top>";
print "<table width=100% align=center border=0 cellspacing=0 cellpadding=0><tbody>";
print "<td width=25% bgcolor= $bgcolor2><font face=Helvetica, Arial, sans-serif color=#000066><small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";

print "<td width=50% bgcolor=$bgcolor2 align=center><table border=0  cellspacing=0 cellpadding=0><tbody>";
# print "<td witth=25% align=center>$font<small>Lev.</td>";
if ($valg=='tilbud') {print "<td width = 20% align=center bgcolor=$bgcolor>$font<small><a href=ordreliste.php?sort=$sort&valg=tilbud>Forslag</a></td>";}
else {print "<td width = 20% align=center>$font<small><a href=ordreliste.php?sort=$sort&valg=tilbud>Forslag</a></td>";}
if ($valg=='ordrer') {print "<td width = 20% align=center bgcolor=$bgcolor>$font<small><a href=ordreliste.php?sort=$sort&valg=ordrer>Ordrer</a></td>";}
else {print "<td width = 20% align=center>$font<small><a href=ordreliste.php?sort=$sort&valg=ordrer>Ordrer</a></td>";}
if ($valg=='faktura') {print "<td width = 20% align=center bgcolor=$bgcolor>$font<small><a href=ordreliste.php?sort=$sort&valg=faktura>Faktura</a></td>";}
else {print "<td width = 20% align=center>$font<small><a href=ordreliste.php?sort=$sort&valg=faktura>Afsluttede</a></td>";}

print "</tbody></table></td>";

# print "      <td width=50% bgcolor= $bgcolor2 align=center><font face=Helvetica, Arial, sans-serif color=#000066><small>Leverand&oslash;r ordrer</small></td>";
print "      <td width=25% bgcolor= $bgcolor2 align=right><font face=Helvetica, Arial, sans-serif color=#000066><small><a href=ordre.php?id=ny accesskey=N>Ny</a></small></td>";
print "     </tbody></table>";
print "  </td></tr>";
print " <tr><td align=center valign=top>";
print "<table cellpadding=1 cellspacing=1 border=0 width=95% valign = top>";
print "<tbody>";
print "  <tr>";
# print "  <td align=right width=50><small><b><font face=Helvetica, Arial, sans-serif><a href=ordreliste.php?sort=ordrenr>Ordrenr</b></small></td>";
#print "   <td width=100></td>";
#print "   <td><small><b><font face=Helvetica, Arial, sans-serif><a href=ordreliste.php?sort=ordredate>Ordredato</b></small></td>";
#print "   <td><small><b><font face=Helvetica, Arial, sans-serif><a href=ordreliste.php?sort=levdate>Levdato</b></small></td>";
#print "   <td><small><b><font face=Helvetica, Arial, sans-serif><a href=ordreliste.php?sort=kontonr>Leverand&oslash;rnr</b></small></td>";
#print "   <td><small><b><font face=Helvetica, Arial, sans-serif><a href=ordreliste.php?sort=firmanavn>Navn</a></b></small></td>";
#print "   <td align=right><small><b><font face=Helvetica, Arial, sans-serif><a href=ordreliste.php?sort=sum>Ordresum</a></b></small></td>";
#print "  </tr>";


print "   <td align=right width=50><small><b><font face=Helvetica, Arial, sans-serif><a href='ordreliste.php?nysort=ordrenr&sort=$sort&valg=$valg'>Ordrenr.</b></small></td>";
print "   <td width=100></td>";
print "<td><small><b><font face=Helvetica, Arial, sans-serif><a href='ordreliste.php?nysort=ordredate&sort=$sort&valg=$valg'>Ordredato</b></small></td>";
print "<td><small><b><font face=Helvetica, Arial, sans-serif><a href='ordreliste.php?nysort=levdate&sort=$sort&valg=$valg'>Levdato</b></small></td>";

#if ($valg=='faktura') {print "   <td><small><b><font face=Helvetica, Arial, sans-serif><a href='ordreliste.php?nysort=fakturadate&sort=$sort&valg=$valg'>Lagerdato</b></small></td>";}
print "   <td><small><b><font face=Helvetica, Arial, sans-serif><a href='ordreliste.php?nysort=ansat&sort=$sort&valg=$valg'>Medarbejder</a></b></small></td>";
print "  </tr>";



  
  if ($valg=="tilbud")
  {
#  print "<tr><td colspan=9><hr></td></tr><tr><td colspan=9 align=center>$font Tilbud</td></tr><tr><td colspan=9><hr></td></tr>";
  $query = db_select("select * from ordrer where art = 'PO' and status < 1 order by $sort");
  while ($row =db_fetch_array($query))
  {
    $ordre="ordre".$row[id];
   if (($tidspkt-($row[tidspkt])>3600)||($row[hvem]==$brugernavn))
    {
      $javascript="onClick=\"javascript:$ordre=window.open('ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\"";
      $understreg='<span style="text-decoration: underline;">';
      $linjetext="";
    }
    else 
    {
      $javascript="onClick=\"javascript:$ordre.focus();\"";
      $understreg='';
      $linjetext="<span title= 'Ordre er l&aring;st af $row[hvem]'>";
    }
  print "<tr>";
    if ($row[art]=='DK'){print "<td align=right $javascript><small>$font (KN)&nbsp; $linjetext $understreg $row[ordrenr]</span><br></small></td>";}
    else {print "<td align=right $javascript><small>$font $linjetext $understreg $row[ordrenr]</span><br></small></td>";}
    print "<td></td>";
    $ordredato=dkdato($row[ordredate]);
    print "<td><small>$font $ordredato</a><br></small></td>";
#    $levdato=dkdato($row[levdate]);
#    print "<td><small>$font $levdato</a><br></small></td>";
#    print"<td></td>";
    print "<td><small>$font $row[kontonr]</a><br></small></td>";
    print "<td><small>$font $row[kontakt]<br></small></td>";
    if ($row[art]=='DK') {$sum=dkdecimal($row[sum])*-1;}
    else {$sum=dkdecimal($row[sum]);}
    print "<td align=right><small>$font $sum<br></small></td>";
    print "</tr>";
  }
  }
  elseif ($valg=='ordrer')
  {
#  print "<tr><td colspan=9><hr></td></tr><tr><td colspan=9 align=center>$font Godkendte ordrer</td></tr><tr><td colspan=9><hr></td></tr>";
  $query = db_select("select * from ordrer where  art = 'PO' and (status = 1 or status = 2) order by $sort");
  while ($row =db_fetch_array($query))
  {
     $ordre="ordre".$row[id];
   if (($tidspkt-($row[tidspkt])>3600)||($row[hvem]==$brugernavn))
    {
      $javascript="onClick=\"javascript:$ordre=window.open('ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\"";
      $understreg='<span style="text-decoration: underline;">';
      $linjetext="";
    }
    else 
    {
      $javascript='';
      $understreg='';
      $linjetext="<span title= 'Kladde er l&aring;st af $row[hvem]'>";
    }
    print "<tr>";
    if ($row[art]=='DK'){print "<td align=right $javascript><small>$font (KN)&nbsp; $linjetext $understreg $row[ordrenr]</span><br></small></td>";}
    else {print "<td align=right><small $javascript>$font $linjetext $understreg $row[ordrenr]</span><br></small></td>";}
    print "<td></td>";
    $ordredato=dkdato($row[ordredate]);
    print "<td><small>$font $ordredato</a><br></small></td>";
    $levdato=dkdato($row[levdate]);
    print "<td><small>$font $levdato</a><br></small></td>";
#    print"<td></td>";
    print "<td><small>$font $row[kontonr]</a><br></small></td>";
    print "<td><small>$font $row[firmanavn]<br></small></td>";
    if ($row[art]=='DK') {$sum=dkdecimal($row[sum])*-1;}
    else {$sum=dkdecimal($row[sum]);}
    print "<td align=right><small>$font $sum<br></small></td>";
    print "</tr>";
  }
  }
  else
  {
#  print "<tr><td colspan=9><hr></td></tr><tr><td colspan=9 align=center>$font Fakturerede ordrer</td></tr><tr><td colspan=9><hr></td></tr>";
  $understreg='<span style="text-decoration: underline;">';
  $query = db_select("select * from ordrer where art = 'PO' and status >= 3 order by $sort");
  while ($row =db_fetch_array($query))
  {
    $javascript="onClick=\"javascript:$ordre=window.open('ordre.php?&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\"";
    print "<tr>";
    if ($row[art]=='DK'){print "<td align=right $javascript><small>$font (KN)&nbsp; $understreg $row[ordrenr]</span><br></small></td>";}
    else {print "<td align=right $javascript><small>$font $understreg $row[ordrenr]</span><br></small></td>";}
    print "<td align=right><small>$font $row[fakturanr]</small></td>";
    print"<td></td>";
    $ordredato=dkdato($row[ordredate]);
    print "<td><small>$font $ordredato</a><br></small></td>";
    $levdato=dkdato($row[levdate]);
    print "<td><small>$font $levdato</a><br></small></td>";
    $faktdato=dkdato($row[fakturadate]);
    print "<td><small>$font $faktdato</a><br></small></td>";
    print "<td><small>$font $row[kontonr]</a><br></small></td>";
    print "<td><small>$font $row[firmanavn]<br></small></td>";
    if ($row[art]=='DK') {$sum=dkdecimal($row[sum])*-1;}
    else {$sum=dkdecimal($row[sum]);}
    print "<td align=right><small>$font $sum<br></small></td>";
    print "</tr>";
  }
}
 

 
  
  print "<tr><td colspan=9><hr></td></tr>";
?>
</tbody>
</table>
  </td></tr>
</tbody></table>

</body></html>
