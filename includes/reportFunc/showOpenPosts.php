<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/reportFunc/showOpenPosts.php --- lap 5.0.0 --- 2026.05.28 ---
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
// Copyright (c) 2023 - 2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20240207 PHR Accounts was not shown if all was alligned, evet if alligned after $todate.
// 20240411 PHR	'if (abs($y)' changed to 'if (abs($y) >= 0.01'
// 20240529	PHR Unalignet account with sum = 0 was not shown
// 20250527 PHR Fixed problem with small corrency diffs that listed alligned accounts at unequal
// 20260507 CL/PHR Added $vis_alle parameter: false = only show udlignet != '1' (Vis åbne poster), true = show all (Vis alle poster).
// 20260513 PHR Columns were shifted when $usePBS was NULL
// 20260518 CL/PHR PBS-kolonne printes kun hvis $usePBS er sat. isset()-check tilføjet for $kontoudtog.
// 20260528 PHR Bottomline was overlooked 20260513

if (!function_exists('vis_aabne_poster')) {
function vis_aabne_poster($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart,$kun_debet,$kun_kredit,$vis_alle=false) {
	global $baseCurrency,$bgcolor,$bgcolor5,$bruger_id;
	global $db;
	global $menu;
	global $sprog_id;

	(isset($_GET['showPBS']))?$showPBS = $_GET['showPBS']:$showPBS=1;
	$qtxt= "select id from adresser where art = 'S' and pbs_nr > '0'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $usePBS=1;
	else {
		$showPBS = 0;
		$usePBS  = 0;
	}
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
		if ($usePBS) {
			$opColWidths = array(9, 7, 22, 8, 8, 8, 8, 8, 9, 13);
		} else {
			$opColWidths = array(10, 26, 8, 8, 8, 8, 8, 10, 14);
		}
		$opColgroupHtml = "<colgroup>";
		foreach ($opColWidths as $opColW) { $opColgroupHtml .= "<col style='width:{$opColW}%'>"; }
		$opColgroupHtml .= "</colgroup>";

		print "<style>
#opHeaderTitleTable { width:100%; table-layout:fixed; border-collapse:collapse; background-color:$bgcolor; }
#opHeaderTitleTable td { padding:6px 4px; border-bottom:2px solid #ddd; }
#opGridWrapper { flex:1 1 auto; min-height:0; overflow-y:auto; overflow-x:auto; width:100%; background-color:$bgcolor; padding:0 8px 68px 8px; box-sizing:border-box; }
#opGridTable { border-collapse:collapse; width:100%; table-layout:fixed; }
#opGridTable tfoot { background-color:$bgcolor; border-top:2px solid #ddd; }
#opGridTable tfoot tr, #opGridTable tfoot td { background-color:$bgcolor; }
</style>\n";

		// Column-title row sits in normal flow (flex:0 0 auto), outside the scrollable area —
		// same approach as the blue bar, so it can never scroll away regardless of where the
		// scrollable grid below it has scrolled to.
		print "<div style='flex:0 0 auto;padding:0 8px;box-sizing:border-box;background-color:$bgcolor;'>\n";
		print "<table id='opHeaderTitleTable' cellpadding=\"0\" cellspacing=\"0\" border=\"0\">$opColgroupHtml<tbody><tr>";
		print "<td>Kontonr.</td>";
		if ($usePBS) {
			if ($showPBS) {
				print "<td title='Skjul PBS kunder'><a href='rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&showPBS=0'>skjul BS</a></td>";
			} else {
				print "<td title='Vis PBS kunder'><a href='rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&showPBS=1'>vis BS</a></td>";
			}
		}
		print "<td>".findtekst(360,$sprog_id)."</td><td align=right>>90</td><td align=right>60-90</td><td align=right>30-60</td><td align=right>8-30</td><td align=right>0-8</td><td align=right>I alt</td><td></td>";
		print "</tr></tbody></table>";
		print "</div>\n";

		print "<div id='opGridWrapper'><table id='opGridTable' width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\">$opColgroupHtml<tbody>\n";
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
		
	$opAmount = $opId = array();
	$x = 0;
	if ($vis_alle) {
		$qtxt = "select distinct konto_id from openpost";
	} else {
		$qtxt = "select distinct konto_id from openpost where udlignet != '1'";
	}
	$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$opId[$x] = $r['konto_id'];
		$x++;
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

		$udlignetFilter = $vis_alle ? "" : " and (udlignet is null or udlignet != '1')";
		if ($todate != $currentdate) {
			$qtxt="select * from openpost where transdate<='$todate' and konto_id='$konto_id[$x]'$udlignetFilter order by faktnr,amount $tmp";
		} else $qtxt="select * from openpost where konto_id='$konto_id[$x]'$udlignetFilter order by faktnr,amount $tmp";
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
			print "<tr class='op-data-row' bgcolor=\"$linjebg\">";
			print "<td><a href=rapport.php?rapportart=accountChart&kilde=openpost&kto_fra=$konto_fra&kilde_kto_til=$konto_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$kontonr[$x]&konto_til=$kontonr[$x]&submit=ok>";
			print "<span title='Klik for detaljer'>$kontonr[$x]</span></a></td>";
			if ($usePBS) print "<td>$pbs[$x]</td>";
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
			if ((isset($kontoudtog[$x]) && $kontoudtog[$x]=='on') && ($kontoart=="D")) print "<td align=center><label class='checkContainerOrdreliste'><input type=checkbox name=kontoudtog[$x] checked><span class='checkmarkOrdreliste'></span></label>";
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

	($usePBS) ? $colspan = 2 : $colspan = 1 ;
	if ($menu=='T') {
		print "</tbody><tfoot>";
		print "<tr><td colspan='$colspan'><br></td><td><b>I alt</b></td>";
	} else {
		print "</tbody><tfoot>";
		print "<tr><td colspan=10><hr></td></tr>\n";
		print "<tr><td colspan='$colspan'><br></td><td><b>I alt</b></td>";
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
		$txt1 = lcfirst(findtekst('2767|Af', $sprog_id));
		$txt2 = findtekst('2125|Linjer pr. side', $sprog_id);
		print "<style>
#opPageFooterBar { position:fixed; left:0; right:0; bottom:0; width:100%; margin:0; z-index:1000; background-color:$bgcolor; border-top:1px solid #b8bec8; padding:1px 12px 10px 12px; display:flex; flex-direction:column; align-items:center; gap:1px; box-sizing:border-box; }
#opPageFooterBar #opFooterPagination { display:flex; align-items:center; justify-content:flex-end; gap:20px; flex-wrap:wrap; width:100%; line-height:1; }
#opPageFooterBar #opFooterActions { display:flex; align-items:center; justify-content:center; gap:8px; flex-wrap:wrap; width:100%; line-height:1; }
#opPageFooterBar #opFooterActions .opBulkLabel { font:12px/100% Arial, Helvetica, sans-serif; color:#555; margin-right:4px; }
#opPageFooterBar #opNavButtons { display:flex; align-items:center; gap:3px; }
#opPageFooterBar #opNavButtons button.navbutton { height:20px; width:20px; padding:0; display:inline-flex; align-items:center; justify-content:center; background:#f0f0f0; color:#000; border:1px solid #b8bec8; border-radius:4px; }
#opPageFooterBar #opNavButtons button.navbutton:not(:disabled) { cursor:pointer; }
#opPageFooterBar #opNavButtons button.navbutton:disabled { opacity:0.5; }
.opActionBtn { display:inline-block; font:11px/100% Arial, Helvetica, sans-serif; padding:.2em 1em .275em; color:#d9eef7; text-decoration:none; text-shadow:0 1px 1px rgba(0,0,0,.3); border:solid 1px #0076a3; border-radius:.5em; cursor:pointer;
	background:#0095cd; background:-webkit-gradient(linear,left top,left bottom,from(#00adee),to(#0078a5)); background:-moz-linear-gradient(top,#00adee,#0078a5); background:linear-gradient(top,#00adee,#0078a5);
	box-shadow:0 1px 2px rgba(0,0,0,.2); }
.opActionBtn:hover { background:#007ead; background:-webkit-gradient(linear,left top,left bottom,from(#0095cc),to(#00678e)); background:-moz-linear-gradient(top,#0095cc,#00678e); background:linear-gradient(top,#0095cc,#00678e); }
.opActionBtn:active { position:relative; top:1px; color:#80bed6; background:-webkit-gradient(linear,left top,left bottom,from(#0078a5),to(#00adee)); background:-moz-linear-gradient(top,#0078a5,#00adee); background:linear-gradient(top,#0078a5,#00adee); }
</style>\n";
		print "<div id='opPageFooterBar'>";
		print "<div id='opFooterPagination'>";
		print "<span id='opPageStatus'></span>";
		print "<span>$txt2 <select id='opPageSize'><option value='50'>50</option><option value='100'>100</option><option value='250'>250</option><option value='500'>500</option><option value='100000'>Alle</option></select></span>";
		print "<span id='opNavButtons'></span>";
		print "</div>";
		print "<div id='opFooterActions'>";
		print "<span class='opBulkLabel'><b>Bulk actions:</b></span>";
		print "<span title=\"Klik her for at maile kontoudtog til de modtagere som er afm&aelig;rket herover\">";
		print "<input type=submit class='opActionBtn' value=\"Mail kontoudtog\" name=\"submit\"></span>";
		print "<span title='Klik her for at oprette rykker til de som er afm&aelig;rkede herover'>";
		print "<input type=submit class='opActionBtn' value=\"Opret rykker\" name=\"submit\"></span>";
		if ($udlign) {
			$udlign=trim($udlign,"'");
			print "	<input type='button' class='opActionBtn' onclick=\"location.href='rapport.php?rapportart=openpost&udlign=$udlign';\" title='Klik her for at udligne alle med saldoen' value='Udlign alle' />";
			print "<input type=submit class='opActionBtn' title='Settles all accounts' value=\"Ryk alle\" name=\"submit\">";
		} else {
			print "<input type=submit class='opActionBtn' title='Settles all accounts' value=\"Ryk alle\" name=\"submit\">";
		}
		print "</div>";
		print "</div>\n";
		print "<script>(function(){
	var rows = Array.prototype.slice.call(document.querySelectorAll('tr.op-data-row'));
	var pageSize = 50, currentPage = 1;
	var prevIcon = '<svg xmlns=\"http://www.w3.org/2000/svg\" height=\"16px\" viewBox=\"0 -960 960 960\" width=\"16px\" fill=\"#000000\"><path d=\"M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z\"/></svg>';
	var nextIcon = '<svg xmlns=\"http://www.w3.org/2000/svg\" height=\"16px\" viewBox=\"0 -960 960 960\" width=\"16px\" fill=\"#000000\"><path d=\"M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z\"/></svg>';
	function render(){
		var total = rows.length;
		var totalPages = Math.max(1, Math.ceil(total / pageSize));
		if (currentPage > totalPages) currentPage = totalPages;
		var start = (currentPage - 1) * pageSize;
		var end = Math.min(total, start + pageSize);
		rows.forEach(function(row, i){ row.style.display = (i >= start && i < end) ? '' : 'none'; });
		var statusEl = document.getElementById('opPageStatus');
		if (statusEl) statusEl.textContent = total ? (start + 1) + '-' + end + ' " . $txt1 . " ' + total : '0 " . $txt1 . " 0';
		var nav = document.getElementById('opNavButtons');
		if (nav) {
			var html = '';
			html += \"<button type='button' class='navbutton' \" + (currentPage <= 1 ? 'disabled' : '') + \" onclick='opGoToPage(\" + (currentPage - 1) + \")'>\" + prevIcon + '</button>';
			var pageRange = 2;
			var startPage = Math.max(1, currentPage - pageRange);
			var endPage = Math.min(totalPages, currentPage + pageRange);
			if (startPage > 1) {
				html += \"<button type='button' class='navbutton' onclick='opGoToPage(1)'>1</button>\";
				if (startPage > 2) html += '<span>...</span>';
			}
			for (var p = startPage; p <= endPage; p++) {
				html += \"<button type='button' class='navbutton' style='\" + (p === currentPage ? 'text-decoration:underline;' : '') + \"' onclick='opGoToPage(\" + p + \")'>\" + p + '</button>';
			}
			if (endPage < totalPages) {
				if (endPage < totalPages - 1) html += '<span>...</span>';
				html += \"<button type='button' class='navbutton' onclick='opGoToPage(\" + totalPages + \")'>\" + totalPages + '</button>';
			}
			html += \"<button type='button' class='navbutton' \" + (currentPage >= totalPages ? 'disabled' : '') + \" onclick='opGoToPage(\" + (currentPage + 1) + \")'>\" + nextIcon + '</button>';
			nav.innerHTML = html;
		}
	}
	window.opGoToPage = function(p){ currentPage = Math.max(1, p); render(); };
	var sizeSel = document.getElementById('opPageSize');
	if (sizeSel) sizeSel.addEventListener('change', function(){ pageSize = parseInt(this.value, 10) || 50; currentPage = 1; render(); });
	render();
})();</script>\n";
	}
	print "</form>\n";

	if ($menu=='T') {
		print "</tfoot></table></div></tfoot></table>";
	} else {
		print "</tfoot></table>"; // <- #opGridWrapper stays open; closed later by openpost() after Rykkeroversigt
	}

	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}

	
}} //endfunc vis_aabne_poster

?>
