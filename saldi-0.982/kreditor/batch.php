<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Batch</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>
<?
// ---------------------------------------------------kreditor/batch.php---patch 0.933----------------
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

$linje_id=$_GET['linje_id'];
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/dkdecimal.php");

if ($HTTP_POST_VARS['submit'])
{
  $submit=trim($HTTP_POST_VARS['submit']);
  $vare_id=$HTTP_POST_VARS['vare_id'];
  $leveres=$HTTP_POST_VARS['leveres'];
  $antal=$HTTP_POST_VARS['antal'];
  $tidl_lev=$HTTP_POST_VARS['tidl_lev'];
  $kred_linje_id=$HTTP_POST_VARS['kred_linje_id'];
  $batch_antal=$HTTP_POST_VARS['batch_antal'];
  $art=trim($HTTP_POST_VARS['art']);

  if ($HTTP_POST_VARS['status']<3) {
    for ($x=1; $x<=$batch_antal; $x++) {
      $temp="valg_".$x;
      $valg[$x]=$HTTP_POST_VARS[$temp]*1;
      $temp="batch_kob_id_".$x;
      $batch_kob_id[$x]=$HTTP_POST_VARS[$temp];
      $temp="ordrenr_".$x;
      $ordrenr[$x]=$HTTP_POST_VARS[$temp];
      $temp="batch_salg_id_".$x;
      $batch_salg_id[$x]=$HTTP_POST_VARS[$temp];
      $temp="res_linje_id_".$x;
      $res_linje_id[$x]=$HTTP_POST_VARS[$temp];
      $temp="rest_".$x;
      $rest[$x]=$HTTP_POST_VARS[$temp];

      if ((($antal>0)&&($tidl_lev<$antal))||(($antal<0)&&($tidl_lev>$antal))) { 
      if ($leveres > 0) {
        if ($valg[$x]>$rest[$x]) {$max_antal[$x]=$rest[$x];}
        else {$max_antal[$x]=$valg[$x];}
        if ($valg[$x] > $max_antal[$x])  {print "<BODY onLoad=\"javascript:alert('Ordrenr: $ordrenr[$x] - Der kan ikke v&aelig;lges flere end $max_antal[$x]!')\">";}
        $valgt_antal=$valgt_antal+$valg[$x];
        $rest_antal=$rest_antal+$rest[$x];
      }
/*      
      else {
        if ($valg[$x]<$rest[$x]) {$max_antal[$x]=$rest[$x];}
        else {$max_antal[$x]=$valg[$x];}
        if ($valg[$x] < $max_antal[$x])  {print "<BODY onLoad=\"javascript:alert('Ordrenr: $ordrenr[$x] - Der kan ikke v&aelig;lges fÃ¦rre end $max_antal[$x]!')\">";}
        $valgt_antal=$valgt_antal-$valg[$x];
        $rest_antal=$rest_antal-$rest[$x];
      }
    
    if ($leveres >= 0) {
      if ($leveres>$rest_antal) {$max_antal=$rest_antal;}
      else {$max_antal=$leveres;}
      if ($valgt_antal > $max_antal)  {print "<BODY onLoad=\"javascript:alert('Der kan ikke v&aelig;lges flere end $max_antal !')\">";}
      else {
         db_modify("delete from reservation where linje_id=$linje_id");
         $temp=$linje_id*-1;
         db_modify("delete from reservation where batch_salg_id=$temp");
         for ($x=1; $x<=$batch_antal; $x++)  {
           if (($valg[$x]>0)&&(!$res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_kob_id, antal) values  ($linje_id, $vare_id, $batch_kob_id[$x], $valg[$x])");}
           elseif (($valg[$x]>0)&&($res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_salg_id, antal) values  ($res_linje_id[$x], $vare_id, $temp, $valg[$x])");}
       } 
     }
    }  
    else {
*/
      if ($valg[1]==$batch_antal+1){
        db_modify("update ordrelinjer set kred_linje_id=-1 where id=$linje_id");
      }
      else {
        for ($x=1; $x<=$batch_antal; $x++)  {
          if ($valg[1]==$x){
            db_modify("update ordrelinjer set kred_linje_id=$kred_linje_id[$x] where id=$linje_id");
          }
        }
      }  
#    }  
  }}
  }
}
if ($submit=="Luk"){print "<body onload=\"javascript:window.close();\">";}

$leveres=0;
$query = db_select("select * from ordrelinjer where id = '$linje_id'");
if ($row = db_fetch_array($query))
{
  $antal=$row[antal];
  $leveres=$row[leveres];
  $posnr=$row[posnr];
  $vare_id=$row[vare_id];
  $varenr=$row['varenr'];
  $serienr=$row['serienr'];
  $query = db_select("select status, art, konto_id from ordrer where id = '$row[ordre_id]'");
  $row = db_fetch_array($query);
  $konto_id=$row[konto_id];
  $status=$row[status];
  $art=$row[art];
}
$tidl_lev=0;
$query = db_select("select * from batch_kob where linje_id = '$linje_id'");
while ($row = db_fetch_array($query))
{
  $tidl_lev=$tidl_lev+$row[antal];
}

##################################################################################################


print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"1\" valign = \"top\" align=\"center\"><tbody>";
print "<tr><td align=center>$font<b>Posnr: $posnr - Varenr: $varenr</td></tr>\n";
print "<form name=ordre batch.php?linje_id=$linje_id method=post>";
print "<tr><td><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\" align=\"center\" width=\"100%\"><tbody>";
if ($antal<0)  {  
  print "<tr><td>$font<small><b>Ordre nr.</td><td></td><td></td><td align = right>$font<small><b>Antal</td><td align = right>$font<small><b>V&aelig;lg</td></tr>\n";
  print "<tr><td colspan=5><hr></td></tr>\n";
  $query = db_select("select kred_linje_id from ordrelinjer where id=$linje_id");
  $row = db_fetch_array($query);
  $kred_linje_id=$row[kred_linje_id];
   
  $x=0;
  $query = db_select("select id, ordrenr from ordrer where konto_id=$konto_id and status>2 and art !='DK' and art !='KK'");
  while ($row = db_fetch_array($query)) {
    $x++;
    $kred_ordre_id[$x]=$row[id];
    $kred_ordrenr[$x]=$row[ordrenr];
  }  
  $id_antal=$x;
  $y=0; 
  for ($x=1; $x<=$id_antal; $x++) {
    $query = db_select("select id, antal from ordrelinjer where ordre_id=$kred_ordre_id[$x] and vare_id = $vare_id");
#    else {$query = db_select("select id, antal from ordrelinjer where vare_id = $vare_id");}
    while ($row = db_fetch_array($query)) {
      $rest=0;
      $q2 = db_select("select rest from batch_kob where linje_id=$row[id] and rest>0");
      while ($r2 = db_fetch_array($q2)) {$rest=$rest+$r2[rest];}
      if ($rest>0) { 
        $y++;
        print "<tr><td onClick=\"javascript:window.open('ordre.php?id=$kred_ordre_id[$x]','$kred_ordrenr[$x]','width=400,height=400,scrollbars=1,resizable=1,menubar=no,location=no');\";><a href>$font $kred_ordrenr[$x]</a></td><td></td><td><td align = right>$font $rest</td>";
  #    print "<td align = right><input type=text style=\"text-align:right\" size=3 name=valg_$x value=$valg[$x]></td></tr>\n";
        print "<td align = center><input type=radio name=valg_1 value=$y";
        if ($kred_linje_id==$row[id]) {print " checked='checked'></td></tr>\n";}
        else {print "></td></tr>\n";}
#        echo "ID $row[id]<br>";
        print "<input type=hidden name=kred_linje_id[$y] value=$row[id]>";
      }   
      $batch_antal=$y;
    }
  }
#  $y++;
#  print "<tr><td>$font Opret som indk&oslash;b</td><td></td><td></td><td></td><td align = center><input type=radio name=valg_1 value=$y";
#  if ($kred_linje_id==-1){print " checked='checked'></td></tr>\n"; }
#  else {print "></td></tr>\n";}
}

print "</td></tr>\n";
print "<input type=hidden name=tidl_lev value='$tidl_lev'>";
print "<input type=hidden name=antal value='$antal'>";
print "<input type=hidden name=leveres value='$leveres'>";
print "<input type=hidden name=rest value='$rest'>";
print "<input type=hidden name=vare_id value='$vare_id'>";
# print "<input type=hidden name=batch_kob_id value=$batch_kob_id>";
print "<input type=hidden name=batch_antal value='$batch_antal'>";
print "<input type=hidden name=status value='$status'>";
print "<input type=hidden name=art value='$art'>";
print "</tbody></table>";
print "<tr><td><table cellpadding=\"0\" cellspacing=\"0\" border=\"1\" valign = \"top\" align=\"center\" width=\"100%\"><tbody>";
if ($batch_antal==0) {
  if (($antal>0)&($leveres!=0)) {print "<tr><td collspan=3>$font Der skal som minumum oprettes en godkendt indk&oslash;bsordre f&oslash;r der kan v&aelig;lges batch</td></tr>\n";}
  elseif (($antal<0)&&($leveres!=0)) {print "<tr><td collspan=3>$font Der er ikke nogen på lager som kan returneres</td></tr>\n";}
  print "<td align=center><input type=submit value=\"Luk\" name=\"submit\"></td></tr>\n";
}
else {print "<td align=center width=50%><input type=submit value=\"Gem\" name=\"submit\"></td><td align=center width=50%><input type=submit value=\"Luk\" name=\"submit\"></td></tr>\n";
}
print "</tbody></table></td>";
print "</form> </tr>\n";
print "</td></tr>\n</tbody></table>";
print "</form>";

?>
