<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------------lager/lagerflyt.php-----------patch 3.5.7-------2017.04.25------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2017 saldi.dk aps
// ----------------------------------------------------------------------

// 20170425 - total omskrivning
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
}

#cho "O ".$_POST['opdater']."<br>";
#cho "P ".$_POST['antal']."<br>";
if (isset($_POST['opdater']) && $_POST['antal']>=1) {
	$opdater=$_POST['opdater'];
	$vare_id=$_POST['vare_id'];
  $lager=$_POST['lager'];
  $antal=$_POST['antal'];
  $max_antal=$_POST['max_antal'];
  $batch_kob_id=$_POST['batch_kob_id'];
  $batch_kob_antal=$_POST['batch_kob_antal'];
  $nyt_lager=$_POST['nyt_lager'];

transaktion("begin");  		
	if ($antal<=$max_antal && $antal>0 && $_POST['opdater']){
		$x=0;
		$sum=0;
		$nyt_antal=$antal;
		$dd=date("Y-m-d");
		$qtxt="select * from batch_kob where vare_id = '$vare_id' and lager = '$lager' and rest > '0' order by id";
#cho __line__." $qtxt<br>";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
#cho __line__." $nyt_antal<br>";
		if ($nyt_antal*1){
				$bk_pris[$x]=$r['pris'];
				$bk_rest[$x]=$r['rest'];
				$bk_id[$x]=$r['id']*1;
				if ($nyt_antal>=$bk_rest[$x]) {
					$qtxt="update batch_kob set  rest = '0' where id='$bk_id[$x]'";
#cho __line__." $qtxt<br>";					
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt="insert into batch_salg (salgsdate,fakturadate,batch_kob_id,vare_id,linje_id,ordre_id,pris,antal,lev_nr,lager)";
					$qtxt.=" values ";
					$qtxt.="('$dd','$dd','$bk_id[$x]','$vare_id','0','0','$bk_pris[$x]','$bk_rest[$x]','1','$lager')";
#cho __line__." $qtxt<br>";					
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$sum+=$bk_pris[$x]*$bk_rest[$x];
					$nyt_antal-=$bk_rest[$x];
				} else {
					$ny_rest=$bk_rest[$x]-$nyt_antal;
#cho "$ny_rest=$bk_rest[$x]-$nyt_antal<br>";
					$qtxt="update batch_kob set  rest = '$ny_rest' where id='$bk_id[$x]'";
#cho __line__." $qtxt<br>";					
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt="insert into batch_salg (salgsdate,fakturadate,batch_kob_id,vare_id,linje_id,ordre_id,pris,antal,lev_nr,lager)";
					$qtxt.=" values ";
					$qtxt.="('$dd','$dd','$bk_id[$x]','$vare_id','0','0','$bk_pris[$x]','$nyt_antal','1','$lager')";
#cho __line__." $qtxt<br>";					
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$sum+=$bk_pris[$x]*$nyt_antal;
					$nyt_antal=0;
					$qtxt=NULL;
#cho __line__." $nyt_antal<br>";
				}
				$x++;
			}
		}
		$stkpris=$sum/$antal;
		$x=0;
		$nyt_antal=$antal;
/*
		$bk_id=array();
		$qtxt="select * from batch_kob where vare_id = '$vare_id' and kobsdate is NULL and antal='0' and lager = '$nyt_lager' and rest < '0' order by id";
#cho __line__." $qtxt<br>";					
		while ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			if ($nyt_antal){
				$bk_pris[$x]=$r['pris'];
				$bk_rest[$x]=$r['rest'];
				$bk_id[$x]=$r['id']*1;
#cho __line__." $bk_id[$x] -> $bk_pris[$x] -> $bk_rest[$x]<br>";				
				if ($nyt_antal >= $bk_rest[$x]) {
					if ($x+1==count($bk_id)) {
						$bk_antal[$x]=$bk_rest[$x]*-1;
						$qtxt="update batch_kob set kobsdate='$dd',fakturadate='$dd',pris='$stkpris',antal=$bk_antal[$x],rest='0' where id='$bk_id[$x]'";
#cho __line__." $qtxt<br>";					
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$nyt_antal-=$bk_antal[$x];
					} else {
						$ny_rest=$nyt_antal+$bk_rest[$x];
						$qtxt="update batch_kob set kobsdate='$dd',fakturadate='$dd',pris='$stkpris',antal=$nyt_antal,rest='$ny_rest' where id='$bk_id[$x]'";
#cho __line__." $qtxt<br>";					
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$nyt_antal=0;		
					}
				}
			}
		}
		*/
		if ($nyt_antal) {
			$qtxt="insert into batch_kob (kobsdate,fakturadate,vare_id,linje_id,ordre_id,pris,antal,lager,rest)";
			$qtxt.=" values ";
			$qtxt.="('$dd','$dd','$vare_id','0','0','$stkpris','$nyt_antal','$nyt_lager','$nyt_antal')";
#cho __line__." $qtxt<br>";					
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$qtxt="select beholdning from lagerstatus where vare_id=$vare_id and lager=$lager";
#cho __line__." $qtxt<br>";					
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$qtxt="update lagerstatus set beholdning = $r[beholdning]-$antal where vare_id=$vare_id and lager=$lager";
#cho __line__." $qtxt<br>";					
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="select beholdning from lagerstatus where vare_id=$vare_id and lager=$nyt_lager";
#cho __line__." $qtxt<br>";					
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if ($r=db_fetch_array($q)) {
			$qtxt="update lagerstatus set beholdning = $r[beholdning]+$antal where vare_id=$vare_id and lager=$nyt_lager";
#cho __line__." $qtxt<br>";					
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
		$qtxt="insert into lagerstatus (vare_id, beholdning, lager) values ($vare_id, $antal,$nyt_lager)";
#cho __line__." $qtxt<br>";					
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	transaktion('commit');
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
#cho __line__." $qtxt<br>";
$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$varenr=$r['varenr'];
$qtxt="select beholdning from lagerstatus where vare_id=$vare_id and lager=$lager";
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
