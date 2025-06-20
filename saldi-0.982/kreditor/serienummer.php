<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Serienumre</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>
<?
// ----------------------------------------------------------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaetelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();

$linje_id=$_GET['linje_id'];
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

if ($HTTP_POST_VARS['submit'])
{
  $submit=trim($HTTP_POST_VARS['submit']);
  $antal=$HTTP_POST_VARS['antal'];
  $kred_linje_id=$HTTP_POST_VARS['kred_linje_id'];
  $vare_id=$HTTP_POST_VARS['vare_id'];
  $leveres=$HTTP_POST_VARS['leveres'];
  $serienr=$HTTP_POST_VARS["serienr"];
  $sn_id=$HTTP_POST_VARS['sn_id'];
  $sn_antal=$HTTP_POST_VARS['sn_antal'];
  $valg=$HTTP_POST_VARS['valg'];
  $art=trim($HTTP_POST_VARS['art']);

  if ($HTTP_POST_VARS['status']<3) {
    for ($x=1; $x<=$antal; $x++) {
      $serienr[$x]=trim($serienr[$x]);
      if ($serienr[$x]) {
        if ($sn_id[$x]){db_modify("update serienr set serienr='$serienr[$x]' where id=$sn_id[$x]");}
        else {
        db_modify("insert into serienr (kobslinje_id, salgslinje_id, serienr, batch_kob_id, batch_salg_id, vare_id) values ('$linje_id', '0', '$serienr[$x]', '0', '0', $vare_id)");}
      }
      elseif($sn_id[$x]){db_modify("delete from serienr where id=$sn_id[$x]");}
      $serienr[$x]="";
    }
    if ($antal<0) {
      $y=0;
      for ($x=1; $x<=$sn_antal; $x++) {
        if (trim($valg[$x])=="on") {
          $y--;
          if ($y>=$leveres) {db_modify("update serienr set kobslinje_id=-$kred_linje_id where id=$sn_id[$x]");}
        }
        elseif ($sn_id[$x]) {db_modify("update serienr set kobslinje_id=$kred_linje_id where id=$sn_id[$x]");}
      }
      if ($y<$leveres) {
        $leveres=$leveres*-1;
        print "<BODY onLoad=\"javascript:alert('Der kan ikke v&aelig;lges flere end $leveres !')\">";
      }
    }
  }
}
if ($submit=="Luk"){print "<body onload=\"javascript:window.close();\">";}

$antal=0;
$query = db_select("select * from ordrelinjer where id = '$linje_id'");
if ($row = db_fetch_array($query))
{
  $ordre_id=$row[ordre_id];
  $kred_linje_id=$row[kred_linje_id];
  $antal=$row[antal];
  $leveres=$row[leveres];
  $posnr=$row[posnr];
  $vare_id=$row[vare_id];
  $varenr=$row['varenr'];
  $query = db_select("select status, art from ordrer where id = '$ordre_id'");
  $row = db_fetch_array($query);
  $status=$row[status];
  $art=$row[art];
}

print "<form name=ordre serienr.php?linje_id=$linje_id method=post>";
print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\" align=\"center\"><tbody>";
print "<tr><td colspan=2 align=center>$font<small>Posnr: $posnr - Varenr: $varenr</td></tr>";
print "<tr><td colspan=2><hr></td></tr>";
if ($antal>0)
{
  $query = db_select("select * from serienr where kobslinje_id = '$linje_id' and batch_kob_id > 0 order by serienr");
  while ($row = db_fetch_array($query))
  {
    print "<tr><td colspan=2>$row[serienr]</td></tr>";
  }
  print "<tr><td colspan=2><hr></td></tr>";
  $sn_antal=0;
  $query = db_select("select * from serienr where kobslinje_id = '$linje_id' and batch_kob_id < 1 order by serienr");
  while ($row = db_fetch_array($query))
  {
    $sn_antal++;
    $sn_id[$sn_antal]=$row[id];
    $serienr[$sn_antal]=$row['serienr'];
  }
  for ($x=1; $x<=$leveres; $x++)
  {
    print "<tr><td colspan=2><input type=text size=40 name=serienr[$x] value=\"$serienr[$x]\"></td></tr>";
    print "<input type=hidden name=sn_id[$x] value='$sn_id[$x]'>";
  }
}
else
{  
  $sn_antal=0;  # Hvis kobslinje ID er negativ er serienummeret valgt til returnering.
  $query = db_select("select * from serienr where salgslinje_id<= 0 and (kobslinje_id =$kred_linje_id  or kobslinje_id =-$kred_linje_id) order by serienr");
  while ($row = db_fetch_array($query))
  {
    if ($row[batch_kob_id]>0) { #Hvis batch_kob_id er negativ er varen returneret.
      $sn_antal++;
      print "<tr><td>$row[serienr]</td><td><input type=checkbox name=valg[$sn_antal]"; 
      if ($row[kobslinje_id]<0){print " checked";}
      print "></td></tr>";
      print "<input type=hidden name=sn_id[$sn_antal] value=$row[id]>";
      print "<input type=hidden name=serienr[$sn_antal] value='$row[serienr]'>";
    }
    else {print "<tr><td>$row[serienr]</td></tr>";}
  }
}
print "<tr><td colspan=2><hr></td></tr>";
print "<input type=hidden name=antal value='$antal'>";
print "<input type=hidden name=kred_linje_id value='$kred_linje_id'>";
print "<input type=hidden name=vare_id value='$vare_id'>";
print "<input type=hidden name=sn_antal value='$sn_antal'>";
print "<input type=hidden name=leveres value='$leveres'>";
print "<input type=hidden name=status value='$status'>";
print "<input type=hidden name=art value='$art'>";
print "<tr>";
if (($status<3)&&($gem)){print "<td align=center><input type=submit value=\"Gem\" name=\"submit\"></td>";}
print "<td align=center><input type=submit value=\"Luk\" name=\"submit\"></td></tr>";
print "</form> </tr>";

print "</tbody></table>";
print "</form>";

?>
