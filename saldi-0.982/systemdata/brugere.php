<?

// -------------------------------------------------brugere.php--------------patch0.935----------
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
// Copyright (c) 2004-2005 ITz ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$modulnr=1;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("top.php");


print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

$ret_id=$_GET['ret_id'];
$slet_id=$_GET['slet_id'];

if ($HTTP_POST_VARS)
{
  $submit=$HTTP_POST_VARS[submit];
  $id=$HTTP_POST_VARS['id'];
  $tmp=$HTTP_POST_VARS['random'];
  $brugernavn=trim($HTTP_POST_VARS[$tmp]);
  $kode=trim($HTTP_POST_VARS['kode']);
  $medarbejder=trim($HTTP_POST_VARS['medarbejder']);
  $ansat_id=$HTTP_POST_VARS['ansat_id'];
  $kontoplan=$HTTP_POST_VARS['kontoplan'];
  $indstillinger=$HTTP_POST_VARS['indstillinger'];
  $kassekladde=$HTTP_POST_VARS['kassekladde'];
  $regnskab=$HTTP_POST_VARS['regnskab'];
  $finansrapport=$HTTP_POST_VARS['finansrapport'];
  $debitorordre=$HTTP_POST_VARS['debitorordre'];
  $debitorkonti=$HTTP_POST_VARS['debitorkonti'];
  $debitorrapport=$HTTP_POST_VARS['debitorrapport'];
  $kreditorordre=$HTTP_POST_VARS['kreditorordre'];
  $kreditorkonti=$HTTP_POST_VARS['kreditorkonti'];
  $kreditorrapport=$HTTP_POST_VARS['kreditorrapport'];
  $varer=$HTTP_POST_VARS['varer'];
  $enheder=$HTTP_POST_VARS['enheder'];
  $backup=$HTTP_POST_VARS['backup'];
  $produktionsordre=$HTTP_POST_VARS['produktionsordre'];
  $varerapport=$HTTP_POST_VARS['varerapport'];
  $a=0; $b=0; $c=0; $d=0; $e=0; $f=0; $g=0; $h=0; $i=0; $j=0; $k=0; $l=0; $m=0; $n=0; $o=0; $p=0;
  if ($kontoplan=='on'){$a=1;}
  if ($indstillinger=='on'){$b=1;}
  if ($kassekladde=='on'){$c=1;}
  if ($regnskab=='on'){$d=1;}
  if ($finansrapport=='on'){$e=1;}
  if ($debitorordre=='on'){$f=1;}
  if ($debitorkonti=='on'){$g=1;}
  if ($kreditorordre=='on'){$h=1;}
  if ($kreditorkonti=='on'){$i=1;}
  if ($varer=='on'){$j=1;}
  if ($enheder=='on'){$k=1;}
  if ($backup=='on'){$l=1;}
  if ($debitorrapport=='on'){$m=1;}
  if ($kreditorrapport=='on'){$n=1;}
  if ($produktionsordre=='on'){$o=1;}
  if ($varerapport=='on'){$p=1;}

  $rettigheder=$a.$b.$c.$d.$e.$f.$g.$h.$i.$j.$k.$l.$m.$n.$o.$p;
  $brugernavn=trim($brugernavn);

  if (($kode) && ($kode != '********************')) {$kode=md5($kode);}
  elseif($kode)
  {
    $query = db_select("select * from brugere where brugernavn = '$brugernavn'");
    if ($row = db_fetch_array($query))
    $kode=trim($row['kode']);
  }
  if (!$x=substr($medarbejder,0,1)) {$ansat_id[$x]=0;}
   
  if ((strstr($submit,'Tilf'))&&($brugernavn)) {
    $query = db_select("select id from brugere where brugernavn = '$brugernavn'");
    if ($row = db_fetch_array($query)) {print "<tr><td align=center>Der findes allerede en bruger med brugenavn: $brugernavn!</td></tr>";}
    else {
      if (!$regnaar) {$regnaar=1;}
      db_modify("insert into brugere (brugernavn, kode, rettigheder, regnskabsaar, ansat_id) values ('$brugernavn', '$kode', '$rettigheder', '$regnaar', $ansat_id[$x])");
    }
  }
  elseif ((strstr($submit,'Opdat'))&&($brugernavn)) {
    db_modify("update brugere set brugernavn='$brugernavn', kode='$kode', rettigheder='$rettigheder', ansat_id=$ansat_id[$x] where id=$id");
  }
  elseif (!$kode) {db_modify("delete from brugere where id = $id");}
}

print "<tr><td valign = top align=center><table><tbody>";
print "<form name=bruger action=brugere.php method=post>";
print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"70%\"><tbody>";

print "<tr><td colspan = 14 align=right>$font Backup &nbsp;</td><td colspan = 14 align=left>$font &nbsp;Debitorrapport</td></tr>";
print "<tr><td colspan = 13 align=right>$font Vareenh./mat. &nbsp;</td>"; print str_repeat("<td align=center>$font |</td>", 2); print "<td colspan=12>$font &nbsp;Kreditorrapport</td></tr>";
print "<tr><td colspan = 12 align=right>$font Varelager &nbsp;</td>"; print str_repeat("<td align=center width=1%>$font |</td>",4); print "<td colspan=11>$font &nbsp;Produktionsordrer</td></tr>";
print "<tr><td colspan = 11 align=right>$font Kreditorkonti &nbsp;</td>"; print str_repeat("<td align=center width=1%>$font |</td>", 6); print "<td colspan=10>$font &nbsp;Varerapport</tr>";
print "<tr><td colspan = 10 align=right>$font Kreditorordrer &nbsp;</td>"; print str_repeat("<td align=center width=1%>$font |</td>", 8); print "<td colspan=9>$font &nbsp;</td></tr>";
print "<tr><td colspan = 9 align=right>$font Debitorkonti &nbsp;</td>"; print str_repeat("<td align=center width=1%>$font |</td>", 9); print "</tr>";
print "<tr><td colspan = 8 align=right>$font Debitorordrer &nbsp;</td>"; print str_repeat("<td align=center width=1%>$font |</td>", 10); print "</tr>";
print "<tr><td colspan = 7 align=right>$font Finansrapport &nbsp;</td>"; print str_repeat("<td align=center width=1%>$font |</td>", 11); print "</tr>";
print "<tr><td colspan = 6 align=right>$font Regnskab &nbsp;</td>"; print str_repeat("<td align=center width=1%>$font |</td>", 12); print "</tr>";
print "<tr><td colspan = 5 align=right>$font Kassekladde &nbsp;</td>"; print str_repeat("<td align=center width=1%>$font |</td>", 13); print "</tr>";
print "<tr><td colspan = 4 align=right>$font Indstillinger &nbsp;</td>"; print str_repeat("<td align=center width=1%>$font |</td>", 14); print "</tr>";
print "<tr><td colspan = 3 align=right>$font Kontoplan &nbsp;</td>"; print str_repeat("<td align=center width=1%>$font |</td>", 15); print "</tr>";
print "<tr><td colspan = 2 align=right>$font &nbsp;</td>"; print str_repeat("<td align=center width=1%>$font |</td>", 16); print "</tr>";

print "<tr><td>$font Navn:&nbsp;</td><td>$font Brugernavn</td></tr>";
$query = db_select("select * from brugere order by brugernavn");
while ($row = db_fetch_array($query))
{
  if ($row[id]!=$ret_id)
  {
    if ($row[ansat_id]) {$r2 = db_fetch_array(db_select("select initialer from ansatte where id = $row[ansat_id]"));}
    else {$r2[initialer]='';}
    print "<tr><td>$font $r2[initialer]&nbsp;</td><td><a href=brugere.php?ret_id=$row[id]>$font $row[brugernavn]</a></td>";
    for ($y=0; $y<=15; $y++)
    {
     if (substr($row[rettigheder],$y,1)==0) {print "<td></td>";}
     else {print "<td align=center>*</td>";}
    }
    print "</tr>";
  }
}
if ($ret_id)
{
  $query = db_select("select * from brugere where id = $ret_id");
  $row = db_fetch_array($query);
  print "<tr><td></td>";
  
  print "<input type=hidden name=id value=$row[id]>";
  print "<input type=hidden name=random value=$row[id]>";  #For at undgå at browseren "husker" et forkert brugernavn.
  print "<td>$font<input type=text size=20 name=$row[id] value='$row[brugernavn]'></td>";
  if (substr($row[rettigheder],0,1)==0) {print "<td>$font<input type=checkbox name=kontoplan></td>";}
  else {print "<td>$font<input type=checkbox name=kontoplan checked></td>";}
  if (substr($row[rettigheder],1,1)==0) {print "<td>$font<input type=checkbox name=indstillinger></td>";}
  else {print "<td>$font<input type=checkbox name=indstillinger checked></td>";}
  if (substr($row[rettigheder],2,1)==0) {print "<td>$font<input type=checkbox name=kassekladde></td>";}
  else {print "<td>$font<input type=checkbox name=kassekladde checked></td>";}
  if (substr($row[rettigheder],3,1)==0) {print "<td>$font<input type=checkbox name=regnskab></td>";}
  else {print "<td>$font<input type=checkbox name=regnskab checked></td>";}
  if (substr($row[rettigheder],4,1)==0) {print "<td>$font<input type=checkbox name=finansrapport></td>";}
  else {print "<td>$font<input type=checkbox name=finansrapport checked></td>";}
  if (substr($row[rettigheder],5,1)==0) {print "<td>$font<input type=checkbox name=debitorordre></td>";}
  else {print "<td>$font<input type=checkbox name=debitorordre checked></td>";}
  if (substr($row[rettigheder],6,1)==0) {print "<td>$font<input type=checkbox name=debitorkonti></td>";}
  else {print "<td>$font<input type=checkbox name=debitorkonti checked></td>";}
  if (substr($row[rettigheder],7,1)==0) {print "<td>$font<input type=checkbox name=kreditorordre></td>";}
  else {print "<td>$font<input type=checkbox name=kreditorordre checked></td>";}
  if (substr($row[rettigheder],8,1)==0) {print "<td>$font<input type=checkbox name=kreditorkonti></td>";}
  else {print "<td>$font<input type=checkbox name=kreditorkonti checked></td>";}
  if (substr($row[rettigheder],9,1)==0) {print "<td>$font<input type=checkbox name=varer></td>";}
  else {print "<td>$font<input type=checkbox name=varer checked></td>";}
  if (substr($row[rettigheder],10,1)==0) {print "<td>$font<input type=checkbox name=enheder></td>";}
  else {print "<td>$font<input type=checkbox name=enheder checked></td>";}
  if (substr($row[rettigheder],11,1)==0) {print "<td>$font<input type=checkbox name=backup></td>";}
  else {print "<td>$font<input type=checkbox name=backup checked></td>";}
  if (substr($row[rettigheder],12,1)==0) {print "<td>$font<input type=checkbox name=debitorrapport></td>";}
  else {print "<td>$font<input type=checkbox name=debitorrapport checked></td>";}
  if (substr($row[rettigheder],13,1)==0) {print "<td>$font<input type=checkbox name=kreditorrapport></td>";}
  else {print "<td>$font<input type=checkbox name=kreditorrapport checked></td>";}
  if (substr($row[rettigheder],14,1)==0) {print "<td>$font<input type=checkbox name=produktionsordre></td>";}
  else {print "<td>$font<input type=checkbox name=produktionsordre checked></td>";}
  if (substr($row[rettigheder],15,1)==0) {print "<td>$font<input type=checkbox name=varerapport></td>";}
  else {print "<td>$font<input type=checkbox name=varerapport checked></td>";}
  print "</tr>";
  print "<tr><td>$font Password</td><td>$font<input type=password size=20 name=kode value='********************'></td></tr>";
  $x=0;
  if ($r2 = db_fetch_array(db_select("select id from adresser where art = 'S'"))) {
    $q2 = db_select("select * from ansatte where konto_id = $r2[id] order by initialer");
    while ($r2 = db_fetch_array($q2)) {
      $x++;
      $ansat_id[$x]=$r2[id];
      $ansat_initialer[$x]=$r2['initialer'];
      if ($ansat_id[$x]== $row[ansat_id]) {$medarbejder=$x.":".$ansat_initialer[$x];}     
      print "<input type = hidden name=ansat_id[$x] value=$ansat_id[$x]>";
    }
  }
  $ansat_antal=$x;
  print "<tr><td> $font<small>Medarbejder</td>";
  print "<td><SELECT NAME=medarbejder>";
  print "<option>$medarbejder</option>";
  for ($x=1; $x<=$ansat_antal; $x++) { 
    print "<option>$x:$ansat_initialer[$x]</option>";
  } 
  if ($medarbejder) {print "<option></option>";}
  print "</SELECT></td></tr>";
  print "</tbody></table></td></tr>";
  print "<tr><td><br></td></tr>";
  print "<tr><td><br></td></tr>";
  print "<td colspan=12 align = center><input type=submit value=\"Opdater\" name=\"submit\"></td>";
}
else
{
  $tmp="navn".rand(100,999);        #For at undgaa at browseren "husker" et forkert brugernavn.
  print "<input type=hidden name=random value = $tmp>";
  print "<tr><td>$font Ny bruger</td>";
  print "<td>$font<input type=text size=20 name=$tmp></td>";
  print "<td>$font<input type=checkbox name=kontoplan></td>";
  print "<td>$font<input type=checkbox name=indstillinger></td>";
  print "<td>$font<input type=checkbox name=kassekladde></td>";
  print "<td>$font<input type=checkbox name=regnskab></td>";
  print "<td>$font<input type=checkbox name=finansrapport></td>";
  print "<td>$font<input type=checkbox name=debitorordre></td>";
  print "<td>$font<input type=checkbox name=debitorkonti></td>";
  print "<td>$font<input type=checkbox name=kreditorordre></td>";
  print "<td>$font<input type=checkbox name=kreditorkonti></td>";
  print "<td>$font<input type=checkbox name=varer></td>";
  print "<td>$font<input type=checkbox name=enheder></td>";
  print "<td>$font<input type=checkbox name=backup></td>";
  print "<td>$font<input type=checkbox name=debitorrapport></td>";
  print "<td>$font<input type=checkbox name=kreditorrapport></td>";
  print "<td>$font<input type=checkbox name=produktionsordre></td>";
  print "<td>$font<input type=checkbox name=varerapport></td>";
  print "</tr>";
  print "<tr><td>$font Password</td><td>$font<input type=password size=20 name=kode></td></tr>";
  print "</tbody></table></td></tr>";
  print "<tr><td><br></td></tr>";
  print "<tr><td><br></td></tr>";
  print "<td colspan=12 align = center><input type=submit value=\"Tilf&oslash;j\" name=\"submit\"></td>";
}
print "</tr>";
print "</tbody></table></td></tr>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
