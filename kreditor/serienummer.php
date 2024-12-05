<?php
// -------------kreditor/serienummer.php----------lap 3.2.9-----2012-04-11----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();

$title="serienummer";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$linje_id=$_GET['linje_id'];

if ($_POST['submit']) {
  $submit=trim($_POST['submit']);
  $antal=$_POST['antal'];
  $kred_linje_id=$_POST['kred_linje_id'];
  $vare_id=$_POST['vare_id'];
  $leveres=$_POST['leveres'];
  $leveret=$_POST['leveret'];
  $serienr=$_POST["serienr"];
  $sn_id=$_POST['sn_id'];
  $sn_antal=$_POST['sn_antal'];
  $valg=$_POST['valg'];
  $art=trim($_POST['art']);

#cho "$leveres $leveret<br>";

  if ($_POST['status']<3) {
    for ($x=1; $x<=$antal; $x++) {
      $serienr[$x]=trim($serienr[$x]);
      if ($serienr[$x]) {
#echo  "update serienr set serienr='$serienr[$x]' where id=$sn_id[$x]<br>";
       if ($sn_id[$x]){db_modify("update serienr set serienr='$serienr[$x]' where id=$sn_id[$x]",__FILE__ . " linje " . __LINE__);}
        else {
        db_modify("insert into serienr (kobslinje_id, salgslinje_id, serienr, batch_kob_id, batch_salg_id, vare_id) values ('$linje_id', '0', '$serienr[$x]', '0', '0', $vare_id)",__FILE__ . " linje " . __LINE__);}
      }
      elseif($sn_id[$x]) db_modify("delete from serienr where id=$sn_id[$x]",__FILE__ . " linje " . __LINE__);
      $serienr[$x]="";
    }
    if ($antal<0) {
      $y=0;
      for ($x=1; $x<=$sn_antal; $x++) {
        if (trim($valg[$x])=="on") {
        $y--;
          if ($y>=$leveres+$leveret) {
						if ($art=='KK') db_modify("update serienr set kobslinje_id=-$kred_linje_id where id=$sn_id[$x]",__FILE__ . " linje " . __LINE__);
						else db_modify("update serienr set salgslinje_id='$linje_id' where id=$sn_id[$x]",__FILE__ . " linje " . __LINE__);
					}
        } elseif ($sn_id[$x]) {
					if ($art=='KK') db_modify("update serienr set kobslinje_id=$kred_linje_id where id=$sn_id[$x]",__FILE__ . " linje " . __LINE__);
					else db_modify("update serienr set salgslinje_id='0' where id=$sn_id[$x]",__FILE__ . " linje " . __LINE__);
					
				}
      }
# echo "$y && $y<$leveres+$leveret<br>";
    if ($y && $y<$leveres+$leveret) {
        $leveres=$leveres*-1;
        print "<BODY onLoad=\"javascript:alert('Der kan ikke v&aelig;lges flere end $leveres !')\">";
      }
    }
  }
}
if ($submit=="Luk") print "<body onload=\"javascript:window.close();\">";

$antal=0;
$query = db_select("select * from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__);
if ($row = db_fetch_array($query)) {
  $ordre_id=$row['ordre_id'];
  $kred_linje_id=$row['kred_linje_id']*1;
  $antal=$row['antal'];
  $leveres=$row['leveres'];
#  $leveret=$row['leveret'];
  $posnr=$row['posnr'];
  $vare_id=$row['vare_id'];
  $varenr=$row['varenr'];
  $query = db_select("select status, art from ordrer where id = '$ordre_id'",__FILE__ . " linje " . __LINE__);
  $row = db_fetch_array($query);
  $status=$row['status'];
  $art=$row['art'];
}
$leveret=0;
$q = db_select("select * from batch_kob where linje_id = '$linje_id'",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) $leveret+=$r['antal'];

print "<form name=ordre serienr.php?linje_id=$linje_id method=post>";
print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\" align=\"center\"><tbody>";
print "<tr><td colspan=2 align=center>Posnr: $posnr - Varenr: $varenr</td></tr>
";
print "<tr><td colspan=2><hr></td></tr>
";
if ($antal>0) { 
  $query = db_select("select * from serienr where kobslinje_id = '$linje_id' and batch_kob_id > 0 order by serienr",__FILE__ . " linje " . __LINE__);
  while ($row = db_fetch_array($query)) {
    print "<tr><td colspan=2>$row[serienr]</td></tr>\n";
  }
  print "<tr><td colspan=2><hr></td></tr>\n";
  $sn_antal=0;
  $query = db_select("select * from serienr where kobslinje_id = '$linje_id' and batch_kob_id < 1 order by serienr",__FILE__ . " linje " . __LINE__);
  while ($row = db_fetch_array($query)) {
    $sn_antal++;
    $sn_id[$sn_antal]=$row['id'];
    $serienr[$sn_antal]=$row['serienr'];
  }
  for ($x=1; $x<=$leveres+$leveret; $x++) {
    print "<tr><td colspan=2><input type=text size=40 name=serienr[$x] value=\"$serienr[$x]\"></td></tr>\n";
    print "<input type=hidden name=sn_id[$x] value='$sn_id[$x]'>";
  }
	if (!$leveres && !$leveret) print "<BODY onLoad=\"javascript:alert('Ingen varer er sat til levering')\">";

} else {
	$sn_antal=0;  # Hvis kobslinje ID er negativ er serienummeret valgt til returnering.
	if ($art=='KK') {  # Kreditnota
#cho "art $art select * from serienr where salgslinje_id<= 0 and (kobslinje_id =$kred_linje_id  or kobslinje_id =-$kred_linje_id) order by serienr<br>"	;
		$query = db_select("select * from serienr where kobslinje_id =$kred_linje_id  or kobslinje_id =-$kred_linje_id order by serienr",__FILE__ . " linje " . __LINE__);
	} else { # Negativ ordre.
		$query = db_select("select * from serienr where (salgslinje_id='$linje_id' or salgslinje_id<= '0') and kobslinje_id >'0' and vare_id = '$vare_id' order by serienr",__FILE__ . " linje " . __LINE__);
	}
	$solgt=0;
#cho "$leveret $antal";
  while ($row = db_fetch_array($query)) {
		if ($art=='KK' && $row['salgslinje_id']>0) {
      print "<tr><td>$row[serienr]</td><td>solgt</td></tr>"; 
    } elseif ($row['batch_kob_id']>=0) { #Hvis batch_kob_id er negativ er varen returneret.
			$sn_antal++;
      print "<tr><td>$row[serienr]</td><td><input type=\"checkbox\" name=\"valg[$sn_antal]\""; 
      if ($row['kobslinje_id']<0 || $row['salgslinje_id']==$linje_id) {
				print " checked";
#echo "<br>$sn_antal>=abs($leveret)<br>";
				if ($sn_antal<=abs($leveret) || $leveret==$antal) {
					print " disabled></td></tr>\n";
					print "<input type=hidden name=valg[$sn_antal] value=\"on\">";
				} else print "></td></tr>\n";

			} else print "></td></tr>\n";
      print "<input type=hidden name=sn_id[$sn_antal] value=$row[id]>";
      print "<input type=hidden name=serienr[$sn_antal] value='$row[serienr]'>";
    }
    else print "<tr><td>$row[serienr]</td></tr>\n";
  }
} 
# if ($solgt&&!$sn_antal) print "<tr><td colspan=2>Alle varer fra krediteret ordre er solgt</td></tr>\n";
print "<tr><td colspan=2><hr></td></tr>\n";
print "<input type=hidden name=antal value='$antal'>";
print "<input type=hidden name=kred_linje_id value='$kred_linje_id'>";
print "<input type=hidden name=vare_id value='$vare_id'>";
print "<input type=hidden name=sn_antal value='$sn_antal'>";
print "<input type=hidden name=leveres value='$leveres'>";
print "<input type=hidden name=leveret value='$leveret'>";
print "<input type=hidden name=status value='$status'>";
print "<input type=hidden name=art value='$art'>";
print "<tr>";
if (($status<3)&&($gem)){print "<td align=center><input type=submit value=\"Gem\" name=\"submit\"></td>";}
print "<td align=center><input type=submit value=\"Luk\" name=\"submit\"></td></tr>
";
print "</form> </tr>
";

print "</tbody></table>";
print "</form>";

?>
