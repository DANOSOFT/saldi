<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/reportFunc/showOpenPosts.php --- lap 4.1.1 --- 2025.06.27 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2023 - 2025 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20240207 PHR Accounts was not shown if all was alligned, evet if alligned after $todate.
// 20240411 PHR	'if (abs($y)' changed to 'if (abs($y) >= 0.01'
// 20240529	PHR Unalignet account with sum = 0 was not shown
// 20250527 PHR Fixed problem with small corrency diffs that listed alligned accounts at unequal

if (!function_exists('vis_aabne_poster')) {
function vis_aabne_poster($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart,$kun_debet,$kun_kredit) {
	global $baseCurrency,$bgcolor,$bgcolor5,$bruger_id;
	global $db;
	global $menu;
	global $sprog_id;

	if (isset($_GET['showPBS'])) $showPBS = $_GET['showPBS'];
	$qtxt= "select id from adresser where art = 'S' and pbs_nr > '0'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $usePBS=1;
	else $showPBS=$usePBS=0;
	
	if ($menu=='T') {
		$top_bund = "";
		$padding = "style='padding: 25px 20px 10px 20px;'";
	} else {
		$top_bund = (isset($top_bund) ? $top_bund : "");
		$padding = "";
	}
	$forfaldsum=$forfaldsum_plus8=$forfaldsum_plus30=$forfaldsum_plus60=$forfaldsum_plus90=$fromdate=$linjebg=$popup=$todate=NULL;
	
	
	if ($menu=='T') {
		print "<tr><td><div class='dataTablediv'><table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class='dataTable'><thead>\n";
		print "<tr><th>Kontonr.</th>";
		if ($usePBS) print "<th>PBS</th>";
		print "<th>".findtekst(360,$sprog_id)."</th><th align=right class='text-right'>>90</th><th align=right  class='text-right'>60-90</th><th align=right class='text-right'>30-60</th><th align=right class='text-right'>8-30</th><th align=right class='text-right'>0-8</th><th align=right class='text-right'>I alt</th><th align=right</th>";
		print "</thead><tbody>";
	} else {
		print "<tr><td><table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n";
		print "<tr><td>Kontonr.</th>";
		if ($usePBS) {
			if ($showPBS) {
				print "<th align=right title=\"Skjul PBS\"><a href=\"rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&showPBS=0\">PBS</a></th>";
			} else {
				print "<th align=right title=\"Vis PBS\"><a href=\"rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&showPBS=1\">PBS</a></th>";
			}	
		}
		print "<td>".findtekst(360,$sprog_id)."</td><td align=right>>90</td><td align=right>60-90</td><td align=right>30-60</td><td align=right>8-30</td><td align=right>0-8</td><td align=right>I alt</td><td></td>";
	}

	$currentdate=date("Y-m-d");
	if ($dato_fra && $dato_til) {
		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_til);
	}	elseif ($dato_fra && !$dato_til) {
		$todate=usdate($dato_fra);
	} else $todate = $currentdate;

	print "<form name=aabenpost action=rapport.php method=post>";

	if ($menu=='T') {
		print "";
	} else {
		print "<tr><td colspan=10><hr></td></tr>\n";
	}
		
	$x = 0;
	$op_id = array();
/*
	$qtxt = "select distinct(konto_id) as konto_id from openpost ";
  if (!$todate || $todate >= $currentdate) $qtxt.= "where udlignet = '0'"; #20200103
	else $qtxt.= "where transdate <= '$todate'"; #20240207
 	$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$op_id[$x]=$r['konto_id'];
		$x++;
	}
*/
	if ($todate < $currentdate) {
		$qtxt = "select konto_id, amount*valutakurs/100 as amount from openpost where transdate <= '$todate' order by konto_id";
	} else {
		$qtxt = "select konto_id, amount*valutakurs/100 as amount from openpost order by konto_id";
	}
	$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!in_array($r['konto_id'],$op_id)) {
			if (abs($r['amount']) > 0.01) {
				$op_amount[$x]+= $r['amount'];
			}
		} else {
			$op_id[$x]     = $r['konto_id'];
			$op_amount[$x]+= $r['amount'];
			$x++;
		}
	}
	$opAmount = $opId = array();
	$x = 0;
	for ($i=0;$i<count($op_id);$i++) {
		if (abs($op_amount) > 0.01) {
			$opId[$x] = $op_id[$i];
			$opAmount[$x] = $op_amount[$i];
			$x++;
		}
	}
	$qtxt = "select * from openpost where udlignet = '0'";
	$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!in_array($r['konto_id'],$opId)) {
			$opId[$x]=$r['konto_id'];
			$opAmount[$x]=0;
			$x++;
		}
	}


	if (is_numeric($konto_fra) && is_numeric($konto_til)) {
		$qtxt = "select * from adresser where kontonr >= '$konto_fra' and kontonr <= '$konto_til' and art = '$kontoart' order by ".nr_cast('kontonr')."";
	} elseif ($konto_fra && $konto_fra!='*') {
		$konto_fra=str_replace("*","%",$konto_fra);
		$tmp1=strtolower($konto_fra);
		$tmp2=strtoupper($konto_fra);
		$qtxt = "select * from adresser where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art = '$kontoart' order by firmanavn";
	}	else $qtxt = "select * from adresser where art = '$kontoart' order by firmanavn";
	$konto_id = $kontonr = array();
	$x=0;
	$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (in_array($r['id'],$opId)) {
			if (!$r['pbs_nr'] || $showPBS) {
			$x++;
			$konto_id[$x]=$r['id'];
			print "<input type=hidden name='konto_id[$x]' value='$konto_id[$x]'>";
			$kontonr[$x]=trim($r['kontonr']);
			$firmanavn[$x]=stripslashes($r['firmanavn']);
			$addr1[$x]=stripslashes($r['addr1']);
			$addr2[$x]=stripslashes($r['addr2']);
			$postnr[$x]=trim($r['postnr']);
			$bynavn[$x]=stripslashes($r['bynavn']);
			$email[$x]=trim($r['email']);
			$betalingsbet[$x]=trim($r['betalingsbet']);
			$betalingsdage[$x]=trim($r['betalingsdage']);
			$pbs[$x]=trim($r['pbs']);
			$pbs_nr[$x]=trim($r['pbs_nr']);
			($pbs[$x] && $pbs_nr[$x])?$pbs[$x]='&#10004;':$pbs[$x]=NULL;
		}}
	}
	$kontoantal=$x;	
	$sum=0;
	$kontrolsum=0;
	$udlign=NULL;
	for ($x=1; $x<=count($konto_id); $x++) {
		$amount=0;
		$accountAligned=1;
		$rykkerbelob=0;
		$forfalden=0;
		$forfalden_plus8=0;
		$forfalden_plus30=0;
		$forfalden_plus60=0;
		$forfalden_plus90=0;
		$kontrol=0;
		$y=0;
		$faktnr=array();
		$f=0;
		if ($kontoart=='D') $tmp="";
		else $tmp="desc";

		if ($todate != $currentdate) {
			$qtxt="select * from openpost where transdate<='$todate' and konto_id='$konto_id[$x]' order by faktnr,amount $tmp";
		} else $qtxt="select * from openpost where konto_id='$konto_id[$x]' order by faktnr,amount $tmp";
#cho __line__." $qtxt<br>",
		$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		$ks=0;
		while ($r=db_fetch_array($q)) {
			$aligned = $r['udlignet'];
			if (!$r['udlignet']) $accountAligned = 0;
      if ((float)$r['valutakurs'] && $r['valuta']!='-') {
				$kontrol+=afrund($r['amount']*$r['valutakurs']/100,2); //2012.03.30 afrunding rettet til 2 (Ørediff hos saldi_390) 
			} else {
				$kontrol+=afrund($r['amount'],2);
			}
			$ks+=$kontrol;
#			if ($r['udlignet']!=1 || ($r['transdate'] <= $todate && $r['udlign_date'] && $r['udlign_date'] > $todate)) {
/*
				if ($r['faktnr'] && !in_array($r['faktnr'],$faktnr)) {
					$f++;
					$faktnr[$f]=$r['faktnr'];
					$forfaldsdag=$r['forfaldsdate'];
				} 
				elseif (!$r['faktnr']) $forfaldsdag=$r['transdate'];
*/				
				($r['forfaldsdate'])?$forfaldsdag=$r['forfaldsdate']:$forfaldsdag=$r['transdate']; 
				
				$oid=$r['id'];

				$transdate=$r['transdate'];
				
				if ($r['valuta']) $valuta=$r['valuta']; // <- 2009.05.05
				else $valuta=$baseCurrency;
				if ($r['valutakurs']) $valutakurs=$r['valutakurs'];
				else $valutakurs=100;
#				$accountAligned="0";
				($valuta==$baseCurrency)?$amount=afrund($r['amount'],2):$amount=afrund($r['amount'],3); //2012.04.03 se saldi_
				if (!$forfaldsdag && $kontoart=='D' && $amount < 0) $forfaldsdag=$r['transdate'];
				elseif (!$forfaldsdag && $kontoart=='K' && $amount > 0) $forfaldsdag=$r['transdate'];
				elseif (!$forfaldsdag) $forfaldsdag=$r['forfaldsdate'];

				$amount*=$valutakurs/100;
				$fakt_utid=strtotime($transdate);
				$forf_utid=strtotime($forfaldsdag);
				$dage=afrund(($forf_utid-$fakt_utid)/86400,0);
				$forfaldsdag_plus8=usdate(forfaldsdag($transdate, 'netto',$dage+8));
				$forfaldsdag_plus30=usdate(forfaldsdag($transdate, 'netto',$dage+30));
				$forfaldsdag_plus60=usdate(forfaldsdag($transdate, 'netto',$dage+60));
				$forfaldsdag_plus90=usdate(forfaldsdag($transdate, 'netto',$dage+90));
				if ($forfaldsdag<$todate){$rykkerbelob=$rykkerbelob+$amount;}
				if (($forfaldsdag<$todate)&&($forfaldsdag_plus8>$todate)){
					$forfalden=$forfalden+$amount;
				}
				if (!$aligned && $forfaldsdag_plus8<=$todate && $forfaldsdag_plus30>$todate ) {
					$forfalden_plus8=$forfalden_plus8+$amount;
				}
				if (!$aligned && $forfaldsdag_plus30<=$todate && $forfaldsdag_plus60>$todate ){
					$forfalden_plus30=$forfalden_plus30+$amount;
				}
				if (!$aligned && $forfaldsdag_plus60<=$todate && $forfaldsdag_plus90>$todate ){
					$forfalden_plus60=$forfalden_plus60+$amount;
				}
				if (!$aligned && $forfaldsdag_plus90<=$todate){
					$forfalden_plus90=$forfalden_plus90+$amount;
				}
			$y=$y+$amount;
#			}
		}
		if ($kun_debet && $y<=0) {$accountAligned=1;$y=0;$kontrol=0;}  
		elseif ($kun_kredit && $y>=0) {$accountAligned=1;$y=0;$kontrol=0;}  
		$kontrol=afrund($kontrol,2);
		#		($y>0) ? $y=afrund($y,2) : $y=afrund($y,2);
		if (abs($y) >= 0.01 || ($todate == $currentdate && ($accountAligned=="0" || $kontrol)))	{	
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		
			$forfaldsum=$forfaldsum+$forfalden;
			$forfaldsum_plus8=$forfaldsum_plus8+$forfalden_plus8;
			$forfaldsum_plus30=$forfaldsum_plus30+$forfalden_plus30;
			$forfaldsum_plus60=$forfaldsum_plus60+$forfalden_plus60;
			$forfaldsum_plus90=$forfaldsum_plus90+$forfalden_plus90;
			$sum=$sum+$y;
			$kontrolsum+=$kontrol;
			print "<tr bgcolor=\"$linjebg\">";
			print "<td><a href=rapport.php?rapportart=accountChart&kilde=openpost&kto_fra=$konto_fra&kilde_kto_til=$konto_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$kontonr[$x]&konto_til=$kontonr[$x]&submit=ok>";
			print "<span title='Klik for detaljer'>$kontonr[$x]</span></a></td>";
			print "<td>$pbs[$x]</td>";
			print "<td>$firmanavn[$x]</td>";
			$forfalden_plus90=afrund($forfalden_plus90,2);
			$forfalden_plus60=afrund($forfalden_plus60,2);
			$forfalden_plus30=afrund($forfalden_plus30,2);
			$forfalden_plus8=afrund($forfalden_plus8,2);

			if (abs($forfalden_plus90) > 0) {
				$color="rgb(255, 0, 0)";
				$tmp=dkdecimal($forfalden_plus90,2);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			} else {
				$color="rgb(0, 0, 0)";
				print "<td align=right></td>";
			}
			if (abs($forfalden_plus60) > 0) {
				$color="rgb(255, 0, 0)";
				$tmp=dkdecimal($forfalden_plus60,2);
				print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			} else {
				$color="rgb(0, 0, 0)";
				print "<td align=right></td>";
			}
			if (abs($forfalden_plus30) > 0) {
				$color="rgb(255, 0, 0)";
				$tmp=dkdecimal($forfalden_plus30,2);
				print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			} else {
				$color="rgb(0, 0, 0)";
				print "<td align=right></td>";
			}
			if (abs($forfalden_plus8) > 0) {
				$color="rgb(255, 0, 0)";
				$tmp=dkdecimal($forfalden_plus8,2);
				print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			} else {
				$color="rgb(0, 0, 0)";
				print "<td align=right></td>";
			}
			if (abs($forfalden) > 0) {
				$color="rgb(255, 0, 0)";
				$tmp=dkdecimal($forfalden,2);
				print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			} else {
				$color="rgb(0, 0, 0)";
				print "<td align=right></td>";
			}
			if (afrund($kontrol,2)!=afrund($y,2)) {
				ret_openpost($konto_id[$x]);
				$tmp=dkdecimal($kontrol,2);
			} else $tmp=dkdecimal($y,2);
			if (abs($y)<0.01 && abs($kontrol)<0.01) {
				$udlign.=$konto_id[$x].",";
				print "<td align=right title=\"Klik her for at udligne &aring;bne poster\"><a href=\"rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&udlign=$konto_id[$x]\">$tmp</a></td>";
			}
			else {print "<td align=right>$tmp</td>";}
			if (($kontoudtog[$x]=='on')&&($kontoart=="D")) print "<td align=center><label class='checkContainerOrdreliste'><input type=checkbox name=kontoudtog[$x] checked><span class='checkmarkOrdreliste'></span></label>";
			elseif($kontoart=="D")  print "<td align=center><label class='checkContainerOrdreliste'><input type=checkbox name=kontoudtog[$x]><span class='checkmarkOrdreliste'></span></label>";
			print "</tr>\n";
		}
		print "<input type=hidden name=rykkerbelob[$x] value=$rykkerbelob>";
	}

	if (!isset ($forfaldsum_plus90)) $forfaldsum_plus90 = NULL;
	if (!isset ($forfaldsum_plus60)) $forfaldsum_plus60 = NULL;
	if (!isset ($forfaldsum_plus30)) $forfaldsum_plus30 = NULL;
	if (!isset ($forfaldsum_plus8)) $forfaldsum_plus8 = NULL;
	if (!isset ($forfaldsum)) $forfaldsum = NULL;

	$forfaldsum_plus90=afrund($forfaldsum_plus90,2);
	$forfaldsum_plus60=afrund($forfaldsum_plus60,2);
	$forfaldsum_plus30=afrund($forfaldsum_plus30,2);
	$forfaldsum_plus8=afrund($forfaldsum_plus8,2);

	if ($menu=='T') {
		print "</tbody><tfoot>";
		print "<tr><td colspan='2'><br></td><td><b>I alt</b></td>";
	} else {
		print "<tr><td colspan=10><hr></td></tr>\n";
		print "<tr><td colspan='2'><br></td><td><b>I alt</b></td>";
	}

	if ($forfaldsum_plus90 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus90,2);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfaldsum_plus60 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus60,2);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfaldsum_plus60 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus30,2);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfaldsum_plus30 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus8,2);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfaldsum != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum,2);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	$color="rgb(0, 0, 0)";
  ($sum<=$kontrolsum)?$tmp=dkdecimal($kontrolsum,2):$tmp=dkdecimal($sum,2);
	print "<td align=right><span style='color: $color;'>$tmp</span>";
	print "<td align=right></td>";
	print "<input type=hidden name=rapportart value=\"openpost\">";
	print "<input type=hidden name=dato_fra value=$dato_fra>";
	print "<input type=hidden name=dato_til value=$dato_til>";
	print "<input type=hidden name=konto_fra value=$konto_fra>";
	print "<input type=hidden name=konto_til value=$konto_til>";
	print "<input type=hidden name=kontoantal value=$kontoantal></td></tr>";

	if ($kontoart=='D') {
		$overlib4="<span class='CellComment'>".findtekst(242,$sprog_id)."</span>";
		print "<tr><td colspan='10' align='center' class='border-hr-top'><span title=\"Klik her for at maile kontoudtog til de modtagere som er afm&aelig;rket herover\">";
		print "<input type=submit value=\"Mail kontoudtog\" name=\"submit\"></span>&nbsp;&nbsp;";
		print "<span title='Klik her for at oprette rykker til de som er afm&aelig;rkede herover'>";
		print "<input type=submit value=\"Opret rykker\" name=\"submit\"></span>&nbsp;&nbsp;";
		if ($udlign) {
			$udlign=trim($udlign,"'");
			print "	<input type='button' onclick=\"location.href='rapport.php?rapportart=openpost&udlign=$udlign';\" title='Klik her for at udligne alle med saldoen' value='Udlign alle' />&nbsp;&nbsp;";
			print "<span class='CellWithComment'><input type=submit value=\"Ryk alle\" name=\"submit\"> $overlib4</span></td>"; 
		} else {
			print "<span class='CellWithComment'><input type=submit value=\"Ryk alle\" name=\"submit\"> $overlib4</span></td>";
		}
		print "</tr>\n";
	}
	print "</form>\n";

	if ($menu=='T') {
		print "</tfoot></table></div></tfoot></table>";
	} else {
		print "<tr><td colspan=10><hr></td></tr>\n";
		print "</tbody></table>";
	}

	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}

	
}} //endfunc vis_aabne_poster

?>
