<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/reportFunc/showOpenPosts.php --- patch 5.0.0 --- 2026-07-06 ---
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
// Copyright (c) 2023-2026 Danosoft.ApS
// ----------------------------------------------------------------------
//
// 20240207 PHR Accounts was not shown if all was alligned, evet if alligned after $todate.
// 20240411 PHR	'if (abs($y)' changed to 'if (abs($y) >= 0.01'
// 20240529	PHR Unalignet account with sum = 0 was not shown
// 20250527 PHR Fixed problem with small corrency diffs that listed alligned accounts at unequal
// 20260507 CL/PHR Added $vis_alle parameter: false = only show udlignet != '1' (Vis åbne poster), true = show all (Vis alle poster).
// 20260513 PHR Columns were shifted when $usePBS was NULL
// 20260528 PHR Bottomline was overlooked 20260513
// 20260612 MJ Paginated and batched debtor open items report queries for large databases.
// 20260706 MJ Paginated and batched debtor open items report queries for large databases.

if (!function_exists('vis_aabne_poster')) {
function vis_aabne_poster($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart,$kun_debet,$kun_kredit,$vis_alle=false) {
	global $baseCurrency,$bgcolor,$bgcolor5,$bruger_id;
	global $db;
	global $db_type;
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
		print "<tr><td><table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n";
		print "<tr><td>Kontonr.</th>";
		if ($usePBS) {
			$openpostContentParam = isset($_GET['openpost_content']) ? '&openpost_content=1' : '';
			if ($showPBS) {
				print "<td title='Skjul PBS kunder'><a href='rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til$openpostContentParam&showPBS=0'>skjul BS</a></td>";
			} else {
				print "<td title='Vis PBS kunder'><a href='rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til$openpostContentParam&showPBS=1'>vis BS</a></td>";
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
	$openpostPage=(int)if_isset($_REQUEST, 1, 'openpost_page');
	$openpostPageSize=(int)if_isset($_REQUEST, 100, 'openpost_page_size');
	if ($openpostPage < 1) $openpostPage=1;
	if ($openpostPageSize < 25) $openpostPageSize=25;
	elseif ($openpostPageSize > 500) $openpostPageSize=500;
	$openpostOffset=($openpostPage-1)*$openpostPageSize;

	print "<form name=aabenpost action=rapport.php method=post>";

	if ($menu=='T') {
		print "";
	} else {
		print "<tr><td colspan=10><hr></td></tr>\n";
	}

	$accountPosts=$accountIndex=array();
	if (is_numeric($konto_fra) && is_numeric($konto_til)) {
		$accountWhere = nr_cast('adresser.kontonr')." >= '$konto_fra' and ".nr_cast('adresser.kontonr')." <= '$konto_til' and adresser.art = '$kontoart'";
		$accountOrder = nr_cast('adresser.kontonr');
	} elseif ($konto_fra && $konto_fra!='*') {
		$konto_fra=str_replace("*","%",$konto_fra);
		$tmp1=strtolower($konto_fra);
		$tmp2=strtoupper($konto_fra);
		$accountWhere = "(adresser.firmanavn like '$konto_fra' or lower(adresser.firmanavn) like '$tmp1' or upper(adresser.firmanavn) like '$tmp2') and adresser.art = '$kontoart'";
		$accountOrder = "adresser.firmanavn";
	} else {
		$accountWhere = "adresser.art = '$kontoart'";
		$accountOrder = "adresser.firmanavn";
	}
	if (!$showPBS) $accountWhere.= " and (adresser.pbs_nr is NULL or adresser.pbs_nr = '' or adresser.pbs_nr = '0')";
	if ($kontoart=='D') $tmp="";
	else $tmp="desc";
	if ($vis_alle) {
		$postWhere = "1=1";
	} elseif ($db_type == 'postgresql') {
		$postWhere = "openpost.udlignet IS DISTINCT FROM '1'";
	} else {
		$postWhere = "(openpost.udlignet is NULL or openpost.udlignet != '1')";
	}
	if ($todate != $currentdate) $postWhere = "openpost.transdate<='$todate' and $postWhere";
	$totalKontoantal=0;
	$qtxt = "select count(*) as account_count from (select distinct adresser.id from openpost ";
	if ($db_type == 'postgresql') $qtxt.= "cross join lateral (select id from adresser where id=openpost.konto_id and $accountWhere offset 0) adresser ";
	else $qtxt.= ", adresser ";
	$qtxt.= "where $postWhere";
	if ($db_type != 'postgresql') $qtxt.= " and openpost.konto_id=adresser.id and $accountWhere";
	$qtxt.= ") account_count";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $totalKontoantal=(int)$r['account_count'];
	$totalPages=($totalKontoantal) ? ceil($totalKontoantal/$openpostPageSize) : 1;
	if ($openpostPage > $totalPages) {
		$openpostPage=$totalPages;
		$openpostOffset=($openpostPage-1)*$openpostPageSize;
	}
	$qtxt = "select account_page.account_id, account_page.account_kontonr, account_page.account_firmanavn, ";
	$qtxt.= "account_page.account_addr1, account_page.account_addr2, account_page.account_postnr, account_page.account_bynavn, ";
	$qtxt.= "account_page.account_email, account_page.account_betalingsbet, account_page.account_betalingsdage, ";
	$qtxt.= "account_page.account_pbs, account_page.account_pbs_nr, openpost.* from (";
	$qtxt.= "select distinct adresser.id as account_id, adresser.kontonr as account_kontonr, adresser.firmanavn as account_firmanavn, ";
	$qtxt.= "adresser.addr1 as account_addr1, adresser.addr2 as account_addr2, adresser.postnr as account_postnr, ";
	$qtxt.= "adresser.bynavn as account_bynavn, adresser.email as account_email, adresser.betalingsbet as account_betalingsbet, ";
	$qtxt.= "adresser.betalingsdage as account_betalingsdage, adresser.pbs as account_pbs, adresser.pbs_nr as account_pbs_nr, ";
	$qtxt.= "$accountOrder as account_sort from openpost ";
	if ($db_type == 'postgresql') $qtxt.= "cross join lateral (select * from adresser where id=openpost.konto_id and $accountWhere offset 0) adresser ";
	else $qtxt.= ", adresser ";
	$qtxt.= "where $postWhere";
	if ($db_type != 'postgresql') $qtxt.= " and openpost.konto_id=adresser.id and $accountWhere";
	$qtxt.= " order by account_sort limit $openpostPageSize offset $openpostOffset) account_page ";
	$qtxt.= "join openpost on openpost.konto_id=account_page.account_id where $postWhere ";
	$qtxt.= "order by account_page.account_sort, openpost.konto_id, openpost.faktnr, openpost.amount $tmp";
	$konto_id = $kontonr = array();
	$x=0;
	$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!isset($accountIndex[$r['account_id']])) {
			$x++;
			$accountIndex[$r['account_id']]=$x;
			$konto_id[$x]=$r['account_id'];
			$kontonr[$x]=trim($r['account_kontonr']);
			$firmanavn[$x]=stripslashes($r['account_firmanavn']);
			$addr1[$x]=stripslashes($r['account_addr1']);
			$addr2[$x]=stripslashes($r['account_addr2']);
			$postnr[$x]=trim($r['account_postnr']);
			$bynavn[$x]=stripslashes($r['account_bynavn']);
			$email[$x]=trim($r['account_email']);
			$betalingsbet[$x]=trim($r['account_betalingsbet']);
			$betalingsdage[$x]=trim($r['account_betalingsdage']);
			$pbs[$x]=trim($r['account_pbs']);
			$pbs_nr[$x]=trim($r['account_pbs_nr']);
			($pbs[$x] && $pbs_nr[$x])?$pbs[$x]='&#10004;':$pbs[$x]=NULL;
			$accountPosts[$x]=array();
		}
		$accountPosts[$accountIndex[$r['account_id']]][]=$r;
	}
	$pageAccountCount=$x;
	$kontoantal=$totalKontoantal;
	$sum=0;
	$kontrolsum=0;
	$udlign=NULL;
	$formIndex=0;
	$displayFirst=($kontoantal) ? $openpostOffset+1 : 0;
	$displayLast=min($kontoantal, $openpostOffset+$pageAccountCount);
	$openpostContentParam = isset($_GET['openpost_content']) ? '&openpost_content=1' : '';
	$basePageUrl="rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til$openpostContentParam&openpost_page_size=$openpostPageSize";
	if ($vis_alle) $basePageUrl.="&vis_alle_poster=on";
	elseif ($kun_debet) $basePageUrl.="&kun_debet=on";
	elseif ($kun_kredit) $basePageUrl.="&kun_kredit=on";
	else $basePageUrl.="&vis_aabenpost=on";
	if (!$showPBS) $basePageUrl.="&showPBS=0";
	if ($kontoantal > $openpostPageSize) {
		$colspan = $usePBS ? 10 : 9;
		print "<tr><td colspan='$colspan' align='center'>";
		if ($openpostPage > 1) print "<a href=\"$basePageUrl&openpost_page=".($openpostPage-1)."\">Forrige</a>&nbsp;";
		print "Viser $displayFirst-$displayLast af $kontoantal";
		if ($openpostPage < $totalPages) print "&nbsp;<a href=\"$basePageUrl&openpost_page=".($openpostPage+1)."\">N&aelig;ste</a>";
		print "</td></tr>\n";
	}
	$agingDateCache=array();
	for ($x=1; $x<=$pageAccountCount; $x++) {
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
		$ks=0;
		foreach ($accountPosts[$x] as $r) {
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
				$agingKey=$transdate . "|" . $dage;
				if (!isset($agingDateCache[$agingKey])) {
					$agingDateCache[$agingKey]=array(
						usdate(forfaldsdag($transdate, 'netto',$dage+8)),
						usdate(forfaldsdag($transdate, 'netto',$dage+30)),
						usdate(forfaldsdag($transdate, 'netto',$dage+60)),
						usdate(forfaldsdag($transdate, 'netto',$dage+90))
					);
				}
				list($forfaldsdag_plus8,$forfaldsdag_plus30,$forfaldsdag_plus60,$forfaldsdag_plus90)=$agingDateCache[$agingKey];
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
			$formIndex++;
			print "<tr bgcolor=\"$linjebg\">";
			print "<input type=hidden name='konto_id[$formIndex]' value='$konto_id[$x]'>";
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
			if ((isset($kontoudtog[$x]) && $kontoudtog[$x]=='on') && ($kontoart=="D")) print "<td align=center><label class='checkContainerOrdreliste'><input type=checkbox name=kontoudtog[$formIndex] checked><span class='checkmarkOrdreliste'></span></label>";
			elseif($kontoart=="D")  print "<td align=center><label class='checkContainerOrdreliste'><input type=checkbox name=kontoudtog[$formIndex]><span class='checkmarkOrdreliste'></span></label>";
			print "</tr>\n";
			print "<input type=hidden name=rykkerbelob[$formIndex] value=$rykkerbelob>";
		}
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
		print "<tr><td colspan='$colspan'><br></td><td><b>I alt (viste)</b></td>";
	} else {
		print "<tr><td colspan=10><hr></td></tr>\n";
		print "<tr><td colspan='$colspan'><br></td><td><b>I alt (viste)</b></td>";
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
	print "<input type=hidden name=kontoantal value=$formIndex>";
	print "<input type=hidden name=openpost_page value=$openpostPage>";
	print "<input type=hidden name=openpost_page_size value=$openpostPageSize></td></tr>";

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
	if ($kontoantal > $openpostPageSize) {
		print "<tr><td colspan='10' align='center' class='border-hr-top'>";
		if ($openpostPage > 1) print "<a href=\"$basePageUrl&openpost_page=".($openpostPage-1)."\">Forrige</a>&nbsp;";
		print "Side $openpostPage af $totalPages";
		if ($openpostPage < $totalPages) print "&nbsp;<a href=\"$basePageUrl&openpost_page=".($openpostPage+1)."\">N&aelig;ste</a>";
		print "</td></tr>\n";
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
