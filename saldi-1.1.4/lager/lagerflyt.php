<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Lagerflyt</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>
<?php
// ---------------------------------lager/lagerflyt.php-----------patch 0.936--------------------------
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

$input=$_GET['input'];
list($lager, $vare_id)=split(":", $input);

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

if ($_POST)
{
  $submit=trim($_POST['submit']);
  $vare_id=$_POST['vare_id'];
  $lager=$_POST['lager'];
  $antal=$_POST['antal'];
  $max_antal=$_POST['max_antal'];
  $batch_kob_id=$_POST['batch_kob_id'];
  $batch_kob_antal=$_POST['batch_kob_antal'];
  $nyt_lager=$_POST['nyt_lager'];

transaktion("begin");  
  for ($x=1; $x<=$batch_kob_antal; $x++) {  
    list($nyt_lager[$x], $dummy)=split(":",$nyt_lager[$x]);
    if (($antal[$x]<=$max_antal[$x])&&($antal[$x]>0)&&($submit=='Gem')){
      if ($row = db_fetch_array(db_select("select * from batch_kob where id = $batch_kob_id[$x]"))) {
        db_modify("update batch_kob set antal = $row[antal]-$antal[$x], rest = $row[rest]-$antal[$x] where id=$batch_kob_id[$x]");
        if ($r2 = db_fetch_array(db_select("select * from batch_kob where linje_id = $row[linje_id] and lager=$nyt_lager[$x]"))) { 
          db_modify("update batch_kob set antal = $r2[antal]+$antal[$x], rest = $r2[rest]+$antal[$x] where id=$r2[id]");
        } 
        else {
          db_modify("insert into batch_kob (kobsdate, fakturadate, vare_id, linje_id, ordre_id, pris, antal, rest, lager) values ('$row[kobsdate]', '$row[fakturadate]', '$row[vare_id]', '$row[linje_id]', '$row[ordre_id]', '$row[pris]', $antal[$x], $antal[$x], $nyt_lager[$x])");
        }
      }
      $row = db_fetch_array(db_select("select beholdning from lagerstatus where vare_id=$vare_id and lager=$lager"));
      db_modify("update lagerstatus set beholdning = $row[beholdning]-$antal[$x] where vare_id=$vare_id and lager=$lager");
      $query = db_select("select beholdning from lagerstatus where vare_id=$vare_id and lager=$nyt_lager[$x]");
      if ($row = db_fetch_array($query))
      {
         db_modify("update lagerstatus set beholdning = $row[beholdning]+$antal[$x] where vare_id=$vare_id and lager=$nyt_lager[$x]");
      }
      else {
         db_modify("insert into lagerstatus (vare_id, beholdning, lager) values ($vare_id, $antal[$x], $nyt_lager[$x])");
      } 
    }
  }
}
transaktion ("commit");
if ($submit=="Luk"){print "<body onload=\"javascript:window.close();\">";}

$antal[$x]=0;
$x=0;

$query = db_select("select * from batch_kob where vare_id=$vare_id and lager=$lager and rest>0");
while ($row = db_fetch_array($query))
{
  $x++;
  $batch_kob_id[$x]=$row[id];
  $max_antal[$x]=$row[rest];
  $kobs_ordre_id[$x]=$row[ordre_id];
  $fakturadate[$x]=$row['fakturadate'];
}
$batch_kob_antal=$x;

$x=0;
$lagernavn[0]="Hovedlager";
$lagernr[0]=0;
$query = db_select("select beskrivelse, kodenr from grupper where art='LG' order by kodenr");
while ($row = db_fetch_array($query)) {
  $x++;
  $lagernavn[$x]=$row['beskrivelse'];       
  $lagernr[$x]=$row['kodenr'];
}
$lagerantal=$x;

$row = db_fetch_array(db_select("select varenr from varer where id=$vare_id"));
$varenr=$row['varenr'];
print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\" align=\"center\"><tbody>";
print "<tr><td align=center colspan=3>$font<b>Flyt vare $varenr fra lager $lager</td></tr>";
print "<form name=ordre lagerflyt.php method=post>";
# print "<tr><td><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\" align=\"center\" width=\"100%\"><tbody>";
print "<tr><td>$font<small><b>Beh.</td><td>$font<small><b>Antal</td><td align=center>$font<small><b>Til lager</td></tr>";
print "<tr><td colspan=3><hr></td></tr>";
for ($y=1; $y<=$batch_kob_antal; $y++) {
  
  $row=db_fetch_array( db_select("select ordrenr from ordrer where id=$kobs_ordre_id[$y]"));
  $ordrenr[$y]=$row[ordrenr];
  if ($row=db_fetch_array(db_select("select linje_id from reservation where batch_kob_id=$batch_kob_id[$y]"))) {
    if ($row=db_fetch_array(db_select("select ordre_id from ordrelinjer where id=$row[linje_id]"))) {
      if ($row=db_fetch_array(db_select("select id, ordrenr from ordrer where id=$row[ordre_id]"))) {
        $res_ordre_id[$y]=$row[id];
        $res_ordrenr[$y]=$row[ordrenr];
      }
    }
  }
  print "<tr><td>$font<span title='K&oslash;bsordre: $ordrenr[$y]'><a href=../kreditor/ordre.php?id=$kobs_ordre_id[$y] target=blank> $max_antal[$y]</a></td>";
  if (($fakturadate[$y])&&(!$res_ordre_id[$y])) {
    print "<td><input type=text size=2 name=antal[$y] value='0'></td>";
    print "<td><SELECT NAME=nyt_lager[$y]>";
    for ($x=0; $x<=$lagerantal; $x++) {
      if ($lagernr[$x] != $lager) {print "<option>$lagernr[$x] : $lagernavn[$x]</option>";}
    }
    print "</td></tr>";
    print "<input type=hidden name=batch_kob_id[$y] value='$batch_kob_id[$y]'>";
    print "<input type=hidden name=max_antal[$y] value='$max_antal[$y]'>";
  }
  elseif ($res_ordre_id[$y]) {print "<td colspan=2>$font<small>Vareservation p&aring; ordrenr:&nbsp;</small><a href=../debitor/ordre.php?id=$res_ordre_id[$y] target=blank>$res_ordrenr[$y]</a></td>";}
  else {print "<td colspan=2>$font<small>Indk&oslash;bsordre ikke afsluttet !</small></td>";}
} 
print "<input type=hidden name=vare_id value='$vare_id'>";
print "<input type=hidden name=lager value='$lager'>";
print "<input type=hidden name=batch_kob_antal value='$batch_kob_antal'>";
print "<tr><td colspan=3><hr></td></tr>";
print "<tr><td align=center colspan=3><input type=submit value=\"Gem\" name=\"submit\">&nbsp;&nbsp;<input type=submit value=\"Luk\" name=\"submit\"></td></tr>";
print "</form> </tr>";
print "</td></tr></tbody></table>";
print "</form>";

?>
