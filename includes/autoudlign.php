<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------------includes/autoudlign.php-------lap 3.7.1------2018.02.07 ---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2018 saldi.dk aps
// ----------------------------------------------------------------------

function autoudlign($udlign) {
	$y=0;
	$v=0;
#	global $regnaar;
	global $dato_til;

#	if ($r = db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__))){
#		if ($maaned_til <= $r['box3']) $slut="31-".trim($maaned_til)."-".$slutaar=trim($r['box4']);
#		else $slut="31-".trim($maaned_til)."-".$slutaar=trim($r['box2']); 
#			
	if (!$dato_til) $periodeslut='2999-12-31';
	else $periodeslut=usdate($dato_til);
		
transaktion('begin');
		$udlign=(float)$udlign;
		if ($udlign>0) { #sker kun når kontoens saldo er 0
			db_modify("update openpost set udlignet='0' where udlignet is NULL and konto_id='$udlign'",__FILE__ . " linje " . __LINE__);
			db_modify("update openpost set udlign_id='0' where udlign_id is NULL and konto_id='$udlign'",__FILE__ . " linje " . __LINE__);
			$q1=db_select("SELECT MAX(udlign_id) as udlign_id from openpost",__FILE__ . " linje " . __LINE__);
			if ($r1=db_fetch_array($q1)) $y=$r1['udlign_id'];
			$tilfoj="konto_id=$udlign and udlignet!='1' and";
		}
		else $tilfoj="udlignet='1' and";

		$sum=0;
		for ($i=1;$i<=5;$i++) {
			$udligndate="1970-01-01";
			$kontrol=array();
			$x=0;
			$qtxt="SELECT id, faktnr,amount,konto_id,transdate,valutakurs from openpost where $tilfoj ";
			if ($i<=2) $qtxt.="faktnr != '' and transdate <= '$periodeslut' and udlign_id = '0' order by transdate";
			elseif ($i==3) $qtxt.="faktnr != '' and transdate <= '$periodeslut' and udlign_id = '0' order by transdate";
			elseif ($i==4) $qtxt.="faktnr = '' and transdate <= '$periodeslut' and udlign_id = '0' order by transdate";
			elseif ($i==5) $qtxt.="udlign_id = '0' and transdate <= '$periodeslut' order by transdate";
			$q1=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r1=db_fetch_array($q1)) {
				if(!in_array($r1['id'], $kontrol)) {
					$x++;
					$id[$x]=$r1['id'];
					$faktnr[$x]=$r1['faktnr'];
					$amount[$x]=$r1['amount']*-1;
					if ($r1['transdate']>$udligndate)$udligndate=$r1['transdate'];
					$sum+=$r1['amount']*$r1['valutakurs']/100;
					$konto_id[$x]=$r1['konto_id'];
				}
			}
			$faktantal=$x;
			if ($sum==0) {
				$y++;
				for ($x=1; $x<=$faktantal; $x++) {
					db_modify("update openpost set udlignet='1', udlign_id='$y', udlign_date='$udligndate' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
				}
			} else {
		
		for ($x=1; $x<=$faktantal; $x++) {
				if ($i==1) $qtxt="SELECT id, transdate, faktnr from openpost where $tilfoj id != '$id[$x]' and amount='$amount[$x]' and konto_id='$konto_id[$x]' and udlignet='1' and transdate <= '$periodeslut' and udlign_id = '0' order by transdate";
				elseif ($i==2) $qtxt="SELECT id, transdate from openpost where $tilfoj id != '$id[$x]' and amount='$amount[$x]' and konto_id='$konto_id[$x]' and  udlign_id = '0' and udlignet='1'  and transdate <= '$periodeslut' order by transdate";
				elseif ($i<=4) $qtxt="SELECT id, transdate from openpost where $tilfoj id != '$id[$x]' and amount='$amount[$x]' and konto_id='$konto_id[$x]' and  udlign_id = '0' and udlignet='1'  and transdate <= '$periodeslut' order by transdate";
				elseif ($i==5) $qtxt="SELECT id, transdate from openpost where $tilfoj id != '$id[$x]' and  udlign_id = '0' and transdate <= '$periodeslut'";
				$z=0;
				$q1=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				if (($r1=db_fetch_array($q1))&&(!in_array($r1['id'], $kontrol))) {
					$z++;
					$id2=$r1['id'];	
					$transdate=$r1['transdate'];	
				}
				if (($z==1)&&(!in_array($id[$x], $kontrol))&&(!in_array($id2, $kontrol))){
					$y++;
					$v++;
					$kontrol[$v]=$id[$x];
					$v++;
					$kontrol[$v]=$id2;
					db_modify("update openpost set udlignet='1', udlign_id='$y', udlign_date='$transdate' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
					db_modify("update openpost set udlignet='1', udlign_id='$y', udlign_date='$transdate' where id='$id2'",__FILE__ . " linje " . __LINE__);
				}
			}	
		}
		$y++;
		if ($udlign>0) { #Hvis der er nogen tilbage som ikke er blevet udlignet
			$q1=db_select("SELECT id, transdate from openpost where konto_id='$udlign' and udlignet = '0' and transdate <= '$periodeslut'",__FILE__ . " linje " . __LINE__);
			while ($r1=db_fetch_array($q1)) {
				db_modify("update openpost set udlignet='1', udlign_id='$y', udlign_date='$r1[transdate]' where id='$r1[id]'",__FILE__ . " linje " . __LINE__);
			}
		}
	}

#exit;
transaktion('commit');
}
?>
