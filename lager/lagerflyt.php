<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------------lager/lagerflyt.php-----------patch 5.0.0-------2026-06-03------
// LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2014-2026 Danosoft aps
// ----------------------------------------------------------------------

// 20170425 - total omskrivning
// 20260526 PHR Fixed bug. In rare cases qty was not removed from the giving stock but just added to the recieving stock
// 20260603 PHR Fixed: $nyt_antal[$x] → $nyt_antal (array-access på scalar gav kun første ciffer → commit fejlede ved antal ≥ 10)
/* 
Ved flytning af varer fra et lager et andet nedskrives rest for disse i det nødvendige antal batch_kob linjer for 
det lager der flyttes fra, 
der oprettes et tilsvarende antal batch_salg linjer uden ordre & linje_id og der oprettes en ny linje i batch_kob 
uden uden ordre & linje_id, med det flyttede antal som antal & rest, snitprisen for de flyttede varer samt nyt lager.
*/ 


@session_start();
$s_id=session_id();

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/db_query.php");

$input=if_isset($_GET['input']);
if ($input) list($lager, $vare_id)=explode(":", $input);
else {
	$lager=if_isset($_GET['lager']);
	$vare_id=if_isset($_GET['vare_id']);
  $variant_id = if_isset($_POST['variant_id'],'0');
}
if (!$variant_id) $variant_id = '0';

if (isset($_POST['opdater']) && $_POST['antal']>=1) {
	$opdater=$_POST['opdater'];
	$vare_id=$_POST['vare_id'];
  $lager=$_POST['lager'];
  $antal=$_POST['antal'];
  $max_antal=$_POST['max_antal'];
  $batch_kob_id=$_POST['batch_kob_id'];
  $batch_kob_antal=$_POST['batch_kob_antal'];
  $nyt_lager=$_POST['nyt_lager'];
  $variant_id = if_isset($_POST['variant_id'],'0');

transaktion("begin");  		
	if ($antal<=$max_antal && $antal>0 && $_POST['opdater']){
		$x=0;
		$sum=0;
		$reg = 0;
		$nyt_antal=$antal;
		$dd=date("Y-m-d");
		$_flyt_due_date = NULL;
		$_flyt_batch_no = NULL;
		$qtxt="select * from batch_kob where vare_id = '$vare_id' and lager = '$lager' and rest > '0' order by " . fefo_order_clause();
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
		if ((float)$nyt_antal){
				$bk_pris[$x]=(float)$r['pris'];
				$bk_rest[$x]=(float)$r['rest'];
				$bk_id[$x]=(int)$r['id'];
				// Carry earliest due_date and first batch_no for the new batch_kob record
				if ($r['due_date'] && ($_flyt_due_date === NULL || $r['due_date'] < $_flyt_due_date)) $_flyt_due_date = $r['due_date'];
				if ($_flyt_batch_no === NULL && $r['batch_no']) $_flyt_batch_no = $r['batch_no'];
				if ($nyt_antal>=$bk_rest[$x]) {
					$qtxt="update batch_kob set  rest = '0' where id='$bk_id[$x]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "insert into batch_salg ";
					$qtxt.= "(salgsdate,fakturadate,batch_kob_id,vare_id,linje_id,ordre_id,pris,antal,lev_nr,lager,variant_id)";
					$qtxt.= " values ";
					$qtxt.= "('$dd','$dd','$bk_id[$x]','$vare_id','0','0','$bk_pris[$x]','$bk_rest[$x]','1','$lager','$variant_id')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$reg-= $bk_rest[$x];
					$sum+= $bk_pris[$x]*$bk_rest[$x];
					$nyt_antal-=$bk_rest[$x];
				} else {
					$ny_rest=$bk_rest[$x]-$nyt_antal;
					$qtxt="update batch_kob set  rest = '$ny_rest' where id='$bk_id[$x]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "insert into batch_salg ";
					$qtxt.= "(salgsdate,fakturadate,batch_kob_id,vare_id,linje_id,ordre_id,pris,antal,lev_nr,lager,variant_id)";
					$qtxt.= " values ";
					$qtxt.= "('$dd','$dd','$bk_id[$x]','$vare_id','0','0','$bk_pris[$x]','$nyt_antal','1','$lager','$variant_id')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$reg-= $nyt_antal;
					$sum+=$bk_pris[$x]*$nyt_antal;
					$nyt_antal=0;
					$qtxt=NULL;
				}
				$x++;
			}
		}
		$stkpris = $sum/$antal;
		if (!$x) { #20260526
				$qtxt = "select kostpris from varer where id = '$vare_id'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$stkpris=(float)$r['kostpris'];
				$qtxt = "insert into batch_salg ";
				$qtxt.= "(salgsdate,fakturadate,batch_kob_id,vare_id,linje_id,ordre_id,pris,antal,lev_nr,lager,variant_id)";
				$qtxt.= " values ";
				$qtxt.= "('$dd','$dd','0','$vare_id','0','0','$stkpris','$antal','1','$lager','$variant_id')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$reg-= $antal;
				$sum = $antal*$stkpris;
		}
		$nyt_antal=$antal;
		$x=0;
/*
		$bk_id=array();
		$qtxt="select * from batch_kob where vare_id = '$vare_id' and kobsdate is NULL and antal='0' and lager = '$nyt_lager' and rest < '0' order by id";
		while ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			if ($nyt_antal){
				$bk_pris[$x]=$r['pris'];
				$bk_rest[$x]=$r['rest'];
				$bk_id[$x]=$r['id']*1;
				if ($nyt_antal >= $bk_rest[$x]) {
					if ($x+1==count($bk_id)) {
						$bk_antal[$x]=$bk_rest[$x]*-1;
						$qtxt="update batch_kob set kobsdate='$dd',fakturadate='$dd',pris='$stkpris',antal=$bk_antal[$x],rest='0' where id='$bk_id[$x]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$nyt_antal-=$bk_antal[$x];
					} else {
						$ny_rest=$nyt_antal+$bk_rest[$x];
						$qtxt="update batch_kob set kobsdate='$dd',fakturadate='$dd',pris='$stkpris',antal=$nyt_antal,rest='$ny_rest' where id='$bk_id[$x]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$nyt_antal=0;		
					}
				}
			}
		}
		*/
		if ($nyt_antal) {
			$_dd_sql = $_flyt_due_date ? "'" . pg_escape_string($_flyt_due_date) . "'" : "NULL";
			$_bn_sql = $_flyt_batch_no ? "'" . pg_escape_string($_flyt_batch_no) . "'" : "NULL";
			$qtxt="insert into batch_kob (kobsdate,fakturadate,vare_id,linje_id,ordre_id,pris,antal,lager,rest,variant_id,due_date,batch_no)";
			$qtxt.=" values ";
			$qtxt.="('$dd','$dd','$vare_id','0','0','$stkpris','$nyt_antal','$nyt_lager','$nyt_antal','$variant_id',$_dd_sql,$_bn_sql)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$reg+= $nyt_antal;
		$qtxt="select beholdning from lagerstatus where vare_id=$vare_id and lager=$lager and variant_id = '$variant_id'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$qtxt = "update lagerstatus set beholdning = $r[beholdning]-$antal ";
		$qtxt.= "where vare_id=$vare_id and lager=$lager and variant_id = '$variant_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="select beholdning from lagerstatus where vare_id=$vare_id and lager=$nyt_lager and variant_id = '$variant_id'";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if ($r=db_fetch_array($q)) {
			$qtxt = "update lagerstatus set beholdning = $r[beholdning]+$antal ";
			$qtxt.= "where vare_id=$vare_id and lager=$nyt_lager and variant_id = '$variant_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
		$qtxt = "insert into lagerstatus (vare_id, beholdning, lager,variant_id) ";
		$qtxt.= "values ('$vare_id', '$antal',$nyt_lager,'$variant_id')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	if ($reg) {
		alert("Der er sket en fejl ved flytning mellem lagre - Ring til Danosoft på tlf 4690 2208 for opklaring");
	} elseif ($antal > $max_antal) {
		alert("Du kan maks flytte ". dkdecimal($max_antal) ."");
	} else {
		alert("$antal flyttet fra lager $lager til lager $nyt_lager");
		transaktion('commit');
	}
}
$x=0;
$qtxt="select beskrivelse, kodenr from grupper where art='LG' order by kodenr";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
  $x++;
  $lagernavn[$x]=$r['beskrivelse'];       
  $lagernr[$x]=$r['kodenr'];
}
$lagerantal=$x;

$qtxt="select varenr from varer where id=$vare_id";
$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$varenr=$r['varenr'];
$qtxt="select beholdning from lagerstatus where vare_id=$vare_id and lager=$lager and variant_id = '$variant_id'";
$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$max_antal=$r['beholdning'];
print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\" align=\"center\"><tbody>";
print "<tr><td align=center colspan=4><b>Flyt vare $varenr fra lager $lager</td></tr>";
print "<form name=ordre lagerflyt.php method=post>";
print "<tr><td align=\"center\"><b>Beh.</td><td>&nbsp;</td><td align=\"center\"><b>Antal</td><td align=\"center\"><b>Til lager</td></tr>";
print "<tr><td colspan=4><hr></td></tr>";
print "<tr><td align=\"right\">".dkdecimal($max_antal,2)."</td><td></td>";
print "<td align=\"center\"><input type=text size=\"2\" name=\"antal\" style=\"text-align:right\" value=\"0\"></td>";
print "<td align=\"center\"><SELECT NAME=nyt_lager>";
for ($x=1; $x<=$lagerantal; $x++) {
  if ($lagernr[$x] != $lager) print "<option value=\"$lagernr[$x]\">$lagernr[$x] : $lagernavn[$x]</option>";
}
print "</select></td></tr>";
print "<input type=hidden name='vare_id' value='$vare_id'>";
print "<input type=hidden name='lager' value='$lager'>";
print "<input type=hidden name='max_antal' value='$max_antal'>";
print "<tr><td colspan='4'><hr></td></tr>";
print "<tr><td colspan='4' align='center'><input style=\"width:100px;\" type=\"submit\" value=\"Opdater\" name=\"opdater\">&nbsp;&nbsp;<a href='varer.php'><input  style='width:100px;' type='button' Value='Luk'></a></td></tr>";  #<input type=submit value=\"Luk\" name=\"submit\">
print "</form></tr>";
print "</td></tr></tbody></table>";
?>
