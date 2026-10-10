<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/serienummer.php --- ver 5.0.0 --- 2026-10-07 ---
/// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260123 PHR if (!$leveres && !$leveret) changed to if ($leveres == 0 && $leveret == 0) as it did not alert when value is 0.000
// 20261007 MJ SST-831 The 20260123 condition above had gone missing, so "Ingen varer er
//                  sat til levering" appeared every time the window opened, including
//                  with 1 in Modtag. Restored. The Gem button was guarded on $gem, which
//                  is never assigned, so it never rendered and only Luk was offered -
//                  Luk does save, but there was no way to tell. The ids that go into the
//                  statements below are cast where they arrive and serial numbers are
//                  escaped at each use. The return list and what Modtag counts are
//                  unchanged; both need the customer's credited rows established first.
@session_start();
$s_id=session_id();

$title="serienummer";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

// 20261007 MJ SST-831 The ids below are interpolated into the statements further down, so
// they are cast where they arrive rather than trusted. Serial numbers are text and are
// escaped at each use instead.
$linje_id=(int)ifset($_GET, 'linje_id', 0);

if ($_POST['submit']) {
  $submit=trim($_POST['submit']);
  $antal=$_POST['antal'];
  $kred_linje_id=(int)ifset($_POST, 'kred_linje_id', 0);
  $vare_id=(int)ifset($_POST, 'vare_id', 0);
  $leveres=$_POST['leveres'];
  $leveret=$_POST['leveret'];
  $serienr=$_POST["serienr"];
  $sn_id=$_POST['sn_id'];
  $sn_antal=$_POST['sn_antal'];
  $valg=$_POST['valg'];
  $art=trim($_POST['art']);


  if ($_POST['status']<3) {
    for ($x=1; $x<=$antal; $x++) {
      $serienr[$x]=trim($serienr[$x]);
      if ($serienr[$x]) {
       if ($sn_id[$x]){db_modify("update serienr set serienr='" . db_escape_string($serienr[$x]) . "' where id=" . (int)$sn_id[$x],__FILE__ . " linje " . __LINE__);}
        else {
        db_modify("insert into serienr (kobslinje_id, salgslinje_id, serienr, batch_kob_id, batch_salg_id, vare_id) values ('$linje_id', '0', '" . db_escape_string($serienr[$x]) . "', '0', '0', $vare_id)",__FILE__ . " linje " . __LINE__);}
      }
      elseif($sn_id[$x]) db_modify("delete from serienr where id=" . (int)$sn_id[$x],__FILE__ . " linje " . __LINE__);
      $serienr[$x]="";
    }
    if ($antal<0) {
      $y=0;
      for ($x=1; $x<=$sn_antal; $x++) {
        if (trim($valg[$x])=="on") {
        $y--;
          if ($y>=$leveres+$leveret) {
						if ($art=='KK') db_modify("update serienr set kobslinje_id=-$kred_linje_id where id=" . (int)$sn_id[$x],__FILE__ . " linje " . __LINE__);
						else db_modify("update serienr set salgslinje_id='$linje_id' where id=" . (int)$sn_id[$x],__FILE__ . " linje " . __LINE__);
					}
        } elseif ($sn_id[$x]) {
					if ($art=='KK') db_modify("update serienr set kobslinje_id=$kred_linje_id where id=" . (int)$sn_id[$x],__FILE__ . " linje " . __LINE__);
					else db_modify("update serienr set salgslinje_id='0' where id=" . (int)$sn_id[$x],__FILE__ . " linje " . __LINE__);

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
  $qtxt = "select * from serienr where kobslinje_id = '$linje_id' and batch_kob_id > 0 order by serienr";
  $q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
  while ($r = db_fetch_array($q)) {
    print "<tr><td colspan=2>$r[serienr]</td></tr>\n";
  }
  print "<tr><td colspan=2><hr></td></tr>\n";
  $sn_antal=0;
  $qtxt = "select * from serienr where kobslinje_id = '$linje_id' and batch_kob_id < 1 order by serienr";
  $q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
  while ($r = db_fetch_array($q)) {
    $sn_antal++;
    $sn_id[$sn_antal]=$r['id'];
    $serienr[$sn_antal]=$r['serienr'];
  }
  for ($x=1; $x<=$leveres+$leveret; $x++) {
    print "<tr><td colspan=2><input type=text size=40 name=serienr[$x] value=\"$serienr[$x]\"></td></tr>\n";
    print "<input type=hidden name=sn_id[$x] value='$sn_id[$x]'>";
  }
  // 20261007 MJ SST-831 Only when nothing is actually being received. The condition the
  // history line of 20260123 describes had gone missing, so the message appeared every
  // time the window opened - including with 1 in Modtag, which is what the customer hit.
  if ($leveres == 0 && $leveret == 0) {
    print "<BODY onLoad=\"javascript:alert('Ingen varer er sat til levering')\">";
  }

} else {
	$sn_antal=0;  # Hvis kobslinje ID er negativ er serienummeret valgt til returnering.
	if ($art=='KK') {  # Kreditnota
		$query = db_select("select * from serienr where kobslinje_id =$kred_linje_id  or kobslinje_id =-$kred_linje_id order by serienr",__FILE__ . " linje " . __LINE__);
	} else { # Negativ ordre.
		$query = db_select("select * from serienr where (salgslinje_id='$linje_id' or salgslinje_id<= '0') and kobslinje_id >'0' and vare_id = '$vare_id' order by serienr",__FILE__ . " linje " . __LINE__);
	}
	$solgt=0;
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
// 20261007 MJ SST-831 $gem was never assigned anywhere, so this button never appeared and
// the only way out of the window was Luk. Luk does save - both buttons post the same form
// and the save above runs either way - but with nothing labelled Gem the customer could
// not tell, and reported the entry as lost. Gem saves and leaves the window open.
if ($status<3){print "<td align=center><input type=submit value=\"" . findtekst('3|Gem', $sprog_id) . "\" name=\"submit\"></td>";}
print "<td align=center><input type=submit value=\"Luk\" name=\"submit\"></td></tr>
";
print "</form> </tr>
";

print "</tbody></table>";
print "</form>";

?>
