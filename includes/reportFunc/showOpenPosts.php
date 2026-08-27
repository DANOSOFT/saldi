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
// 20260518 CL/PHR PBS-kolonne printes kun hvis $usePBS er sat. isset()-check tilføjet for $kontoudtog.
// 20260528 PHR Bottomline was overlooked 20260513
// 20260702 CX/PHR Build "Udlign alle" from unaligned openpost balance when showing all posts.
// 20260706 MJ Paginated and batched debtor open items report queries for large databases.
// 20260807 CL/NTR Gave the two variants of this table an id (visAabnePosterTableT / visAabnePosterTable) for future reference; padding for this grid comes from rapportfunc.php's #opGridWrapper.
// 20260809 Sawaneh Escaped account filter before it reaches SQL and when it is re-emitted into
//                  links/hidden fields. The 0-8 column now honours the same open-at-date rule as
//                  the other four aging columns.
// 20260812 Sawaneh Review: the firm-name search folds case with mb_strtolower/mb_strtoupper,
//                  so names containing ae, oe or aa match regardless of case.
// 20260824 CL/SZ Re-derive valutakurs from the valuta table when a foreign-
//                currency row has kurs=100 (uncaptured rate), instead of
//                treating it as 1:1 DKK parity - was producing DKK totals
//                wildly out of sync with kontokort/accountChart (SST-672)
// 20260824 CL/NTR Flush the grid header to the client (ob_flush + flush, draining php.ini's output_buffering) before the heavy count/page queries, so the table skeleton is visible while the SQL runs.
// 20260826 CL/NTR openpost_content flag is read once (GET or POST) and now also carried on the udlign links, so they skip the async shell like pagination/PBS already did. Firm-name filter uses !empty() again, matching the legacy truthiness check.
// 20260826 CL/NTR Re-derived valutakurs lookups (SST-672) are cached per request, keyed by currency + transdate, and the valuta query uses limit 1.
// 20260826 CL/NTR Count/page queries group openpost per account and apply the display loop's show-rule (unaligned post, net amount >= 0.01, kun_debet/kun_kredit sign) as a HAVING clause, so "Vis alle poster", past-date and kun_* pages are no longer cut from the unfiltered account superset and left (nearly) empty (MB-5).
// 20260812 Sawaneh Review: every report-wide colspan is derived from the column count, so the
//                  footer and pagination no longer span a phantom tenth column when there are no
//                  BS customers. The BS toggle carries the same view state as the pagination
//                  links, and showPBS is read from $_REQUEST and posted back.

if (!function_exists('openpost_account_filter')) {
/**
 * Normalizes the account filter of the open posts report into SQL-safe fragments.
 *
 * A numeric range is range-validated and cast to int. A firm-name search is escaped with
 * db_escape_string(), and the LIKE metacharacters the user typed (%, _ and \) are neutralized
 * before '*' is translated into '%'. Only '*' therefore acts as a wildcard, which is the
 * wildcard the report has always documented.
 *
 * @param string|null $konto_fra  Start of the account number range, or a firm-name search pattern.
 * @param string|null $konto_til  End of the account number range. Only used when both ends are numeric.
 * @param string      $kontoart   Address type: 'D' for debtors, 'K' for creditors.
 * @return array{
 *   where: string,  Predicate on adresser, safe to interpolate into a query.
 *   order: string,  Expression the accounts are sorted by.
 * }
 */
function openpost_account_filter($konto_fra, $konto_til, $kontoart) {
	$maxKontonr = 999999999999999; // widest value nr_cast() can convert on postgresql
	$artEscaped = db_escape_string((string)$kontoart);
	if (is_numeric($konto_fra) && is_numeric($konto_til)) {
		$range = array();
		foreach (array($konto_fra, $konto_til) as $number) {
			$number = (float)$number;
			if ($number > $maxKontonr) $number = $maxKontonr;
			elseif ($number < -$maxKontonr) $number = -$maxKontonr;
			$range[] = (int)$number;
		}
		list($fra, $til) = $range;
		return array(
			'where' => nr_cast('adresser.kontonr')." >= '$fra' and ".nr_cast('adresser.kontonr')." <= '$til' and adresser.art = '$artEscaped'",
			'order' => nr_cast('adresser.kontonr')
		);
	}
	if (!empty($konto_fra) && $konto_fra != '*') {
		$search = (string)$konto_fra;
		$pattern = array();
		// mb_ variants, not strtolower()/strtoupper(): those only fold ASCII, so a
		// search for 'aarhus bageri' would miss 'Aarhus Bageri' the moment the name
		// contains an æ, ø or å. db_escape_string() already requires mbstring.
		foreach (array($search, mb_strtolower($search, 'UTF-8'), mb_strtoupper($search, 'UTF-8')) as $variant) {
			$pattern[] = str_replace('*', '%', db_escape_string(addcslashes($variant, '\\%_')));
		}
		return array(
			'where' => "(adresser.firmanavn like '$pattern[0]' or lower(adresser.firmanavn) like '$pattern[1]' or upper(adresser.firmanavn) like '$pattern[2]') and adresser.art = '$artEscaped'",
			'order' => "adresser.firmanavn"
		);
	}
	return array(
		'where' => "adresser.art = '$artEscaped'",
		'order' => "adresser.firmanavn"
	);
}
}

if (!function_exists('vis_aabne_poster')) {
function vis_aabne_poster($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart,$kun_debet,$kun_kredit,$vis_alle=false) {
	global $baseCurrency,$bgcolor,$bgcolor5,$bruger_id;
	global $db;
	global $db_type;
	global $menu;
	global $sprog_id;

	// $_REQUEST, not $_GET: the report's own form posts back to rapport.php, and reading the
	// flag from GET alone made every action button ("Mail kontoudtog", "Ryk alle", ...) fall
	// back to showing BS customers again.
	$showPBS = (int)if_isset($_REQUEST, 1, 'showPBS');
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
	
	
	$dato_fraUrl=rawurlencode((string)$dato_fra);
	$dato_tilUrl=rawurlencode((string)$dato_til);
	$konto_fraUrl=rawurlencode((string)$konto_fra);
	$konto_tilUrl=rawurlencode((string)$konto_til);
	$dato_fraHtml=htmlspecialchars((string)$dato_fra,ENT_QUOTES);
	$dato_tilHtml=htmlspecialchars((string)$dato_til,ENT_QUOTES);
	$konto_fraHtml=htmlspecialchars((string)$konto_fra,ENT_QUOTES);
	$konto_tilHtml=htmlspecialchars((string)$konto_til,ENT_QUOTES);
	// Carry the async shell's content flag on every link back into this report (PBS toggle,
	// pagination, udlign), so those requests render the report directly instead of re-entering
	// the shell in debitor/rapport.php. The shell accepts the flag from GET or POST, so honour both.
	$openpostContentParam = (isset($_GET['openpost_content']) || isset($_POST['openpost_content'])) ? '&openpost_content=1' : '';

	$openpostPage=(int)if_isset($_REQUEST, 1, 'openpost_page');
	$openpostPageSize=(int)if_isset($_REQUEST, 100, 'openpost_page_size');
	if ($openpostPage < 1) $openpostPage=1;
	if ($openpostPageSize < 25) $openpostPageSize=25;
	elseif ($openpostPageSize > 500) $openpostPageSize=500;
	$openpostOffset=($openpostPage-1)*$openpostPageSize;

	// Every link back into the report has to carry the current view: the date and account
	// filter, which mode is showing and the page size. Built once, so the BS toggle in the
	// header cannot drop what the pagination links keep - toggling it used to reset the
	// report to the default mode and page size.
	$opStateParams = "rapportart=openpost&submit=ok&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl"
		. "&konto_fra=$konto_fraUrl&konto_til=$konto_tilUrl$openpostContentParam&openpost_page_size=$openpostPageSize";
	if ($vis_alle) $opStateParams.="&vis_alle_poster=on";
	elseif ($kun_debet) $opStateParams.="&kun_debet=on";
	elseif ($kun_kredit) $opStateParams.="&kun_kredit=on";
	else $opStateParams.="&vis_aabenpost=on";
	// Kontonr, PBS (only with BS customers), company name, the five aging columns, I alt and the
	// trailing kontoudtog cell. Every report-wide colspan is derived from this, so the footer,
	// action row and pagination cannot span a width the table does not have.
	$opColCount = $usePBS ? 10 : 9;

	if ($menu=='T') {
		print "<tr><td><div class='dataTablediv'><table id='visAabnePosterTableT' width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class='dataTable'><thead>\n";
		print "<tr><th>Kontonr.</th>";
		if ($usePBS) print "<th>PBS</th>";
		print "<th>".findtekst(360,$sprog_id)."</th><th align=right class='text-right'>>90</th><th align=right  class='text-right'>60-90</th><th align=right class='text-right'>30-60</th><th align=right class='text-right'>8-30</th><th align=right class='text-right'>0-8</th><th align=right class='text-right'>I alt</th><th align=right</th>";
		print "</thead><tbody>";
	} else {
		print "<tr><td><table id='visAabnePosterTable' width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n";
		print "<tr><td>Kontonr.</th>";
		if ($usePBS) {
			if ($showPBS) {
				print "<td title='Skjul PBS kunder'><a href='rapport.php?$opStateParams&showPBS=0'>skjul BS</a></td>";
			} else {
				print "<td title='Vis PBS kunder'><a href='rapport.php?$opStateParams&showPBS=1'>vis BS</a></td>";
			}
		}
		print "<td>".findtekst(360,$sprog_id)."</td><td align=right>>90</td><td align=right>60-90</td><td align=right>30-60</td><td align=right>8-30</td><td align=right>0-8</td><td align=right>I alt</td><td></td>";
	}
	// Push the grid header out before the heavy count/page queries below, so the user sees
	// the empty table immediately while the SQL runs (ob_flush drains php.ini's output_buffering).
	if (ob_get_level() > 0) @ob_flush();
	flush();

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
		print "<tr><td colspan='$opColCount'><hr></td></tr>\n";
	}

	$accountPosts=$accountIndex=array();
	$accountFilter = openpost_account_filter($konto_fra,$konto_til,$kontoart);
	$accountWhere = $accountFilter['where'];
	$accountOrder = $accountFilter['order'];
	if (!$showPBS) $accountWhere.= " and (adresser.pbs_nr is NULL or adresser.pbs_nr = '' or adresser.pbs_nr = '0')";
	if ($kontoart=='D') $tmp="";
	else $tmp="desc";
	if ($vis_alle || $todate != $currentdate) {
		$postWhere = "1=1";
	} elseif ($db_type == 'postgresql') {
		$postWhere = "openpost.udlignet IS DISTINCT FROM '1'";
	} else {
		$postWhere = "(openpost.udlignet is NULL or openpost.udlignet != '1')";
	}
	if ($todate != $currentdate) $postWhere = "openpost.transdate<='$todate' and $postWhere";
	// The display loop below only prints an account when its selected posts net to something
	// (abs($y) >= 0.01), contain an unaligned post, or (kun_debet/kun_kredit) fall on the wanted
	// side of zero. When showing all posts or a past date that hides most accounts, so the count
	// and page queries must apply the same rule - otherwise the pages are cut from the unfiltered
	// superset and come out (nearly) empty ("Viser 401-500 af 5548" with 3 rows). The amount is
	// converted like $kontrolAmount (valutakurs falls back to 100); the per-date re-derivation of
	// placeholder rates is not repeated here, which only moves sub-øre rounding residues.
	$baseAmount = "openpost.amount*(case when coalesce(openpost.valutakurs,0)=0 then 100 else openpost.valutakurs end)/100";
	$accountHaving = array();
	if ($vis_alle || $todate != $currentdate) {
		$having = "abs(sum($baseAmount)) >= 0.01";
		if ($todate == $currentdate) $having = "sum(case when openpost.udlignet is null or openpost.udlignet != '1' then 1 else 0 end) > 0 or $having";
		$accountHaving[] = "($having)";
	}
	if ($kun_debet) $accountHaving[] = "sum($baseAmount) > 0";
	elseif ($kun_kredit) $accountHaving[] = "sum($baseAmount) < 0";
	$accountHaving = $accountHaving ? " having ".implode(" and ", $accountHaving) : "";
	$accountSource = "(select openpost.konto_id from openpost where $postWhere group by openpost.konto_id$accountHaving) account_posts";
	$totalKontoantal=0;
	$qtxt = "select count(*) as account_count from $accountSource ";
	if ($db_type == 'postgresql') $qtxt.= "cross join lateral (select id from adresser where id=account_posts.konto_id and $accountWhere offset 0) adresser";
	else $qtxt.= ", adresser where account_posts.konto_id=adresser.id and $accountWhere";
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
	$qtxt.= "select adresser.id as account_id, adresser.kontonr as account_kontonr, adresser.firmanavn as account_firmanavn, ";
	$qtxt.= "adresser.addr1 as account_addr1, adresser.addr2 as account_addr2, adresser.postnr as account_postnr, ";
	$qtxt.= "adresser.bynavn as account_bynavn, adresser.email as account_email, adresser.betalingsbet as account_betalingsbet, ";
	$qtxt.= "adresser.betalingsdage as account_betalingsdage, adresser.pbs as account_pbs, adresser.pbs_nr as account_pbs_nr, ";
	$qtxt.= "$accountOrder as account_sort from $accountSource ";
	if ($db_type == 'postgresql') $qtxt.= "cross join lateral (select * from adresser where id=account_posts.konto_id and $accountWhere offset 0) adresser";
	else $qtxt.= ", adresser where account_posts.konto_id=adresser.id and $accountWhere";
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
	$basePageUrl="rapport.php?$opStateParams";
	if (!$showPBS) $basePageUrl.="&showPBS=0";
	if ($kontoantal > $openpostPageSize) {
		print "<tr><td colspan='$opColCount' align='center'>";
		if ($openpostPage > 1) print "<a href=\"$basePageUrl&openpost_page=".($openpostPage-1)."\">Forrige</a>&nbsp;";
		print "Viser $displayFirst-$displayLast af $kontoantal";
		if ($openpostPage < $totalPages) print "&nbsp;<a href=\"$basePageUrl&openpost_page=".($openpostPage+1)."\">N&aelig;ste</a>";
		print "</td></tr>\n";
	}
	$agingDateCache=array();
	$valutaGruppeCache=array();
	$kursCache=array();
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
		$openKontrol=0;
		$y=0;
		$openY=0;
		$faktnr=array();
		$f=0;
		$ks=0;
		foreach ($accountPosts[$x] as $r) {
			$aligned = $r['udlignet'];
			if ($todate != $currentdate && $r['udlignet'] == '1' && (!$r['udlign_date'] || $r['udlign_date'] > $todate)) {
				$aligned = 0;
			}
			if (!$aligned) $accountAligned = 0;
			if ($r['valuta']) $valuta=$r['valuta']; // <- 2009.05.05
			else $valuta=$baseCurrency;
			if ($r['valutakurs']) $valutakurs=$r['valutakurs'];
			else $valutakurs=100;
			if ($valuta!=$baseCurrency && $valutakurs==100) { // 20260824 CL/SZ kurs=100 on a foreign-currency row is the "no real rate captured" placeholder, not parity - re-derive it like accountChart.php/generalLedger.php do (SST-672)
				$kursKey=$valuta . "|" . $r['transdate']; // 20260826 CL/NTR per-request cache: the same currency/date pair recurs across rows and accounts, so resolve it once
				if (!isset($kursCache[$kursKey])) {
					if (!isset($valutaGruppeCache[$valuta])) {
						$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art='VK'",__FILE__ . " linje " . __LINE__));
						$valutaGruppeCache[$valuta]=$r3 ? $r3['kodenr'] : '';
					}
					$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutaGruppeCache[$valuta]' and valdate <= '$r[transdate]' order by valdate desc limit 1",__FILE__ . " linje " . __LINE__));
					$kursCache[$kursKey]=($r3 && $r3['kurs']) ? $r3['kurs']*1 : 0;
				}
				if ($kursCache[$kursKey]) $valutakurs=$kursCache[$kursKey];
			}
			if ((float)$valutakurs && $r['valuta']!='-') {
				$kontrolAmount=afrund($r['amount']*$valutakurs/100,2); //2012.03.30 afrunding rettet til 2 (Ørediff hos saldi_390)
			} else {
				$kontrolAmount=afrund($r['amount'],2);
			}
			$kontrol+=$kontrolAmount;
			if (!$aligned) $openKontrol+=$kontrolAmount;
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
				if (!$aligned && $forfaldsdag<$todate && $forfaldsdag_plus8>$todate) {
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
			if (!$aligned) $openY=$openY+$amount;
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
			$kontonrUrl=rawurlencode($kontonr[$x]);
			print "<td><a href=\"rapport.php?rapportart=accountChart&kilde=openpost&kto_fra=$konto_fraUrl&kilde_kto_til=$konto_tilUrl&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl&konto_fra=$kontonrUrl&konto_til=$kontonrUrl&submit=ok\">";
			print "<span title='Klik for detaljer'>".htmlspecialchars($kontonr[$x],ENT_QUOTES)."</span></a></td>";
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
			if ($accountAligned=="0" && abs($openY)<0.01 && abs($openKontrol)<0.01) {
				$udlign.=$konto_id[$x].",";
				print "<td align=right title=\"Klik her for at udligne &aring;bne poster\"><a href=\"rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl&konto_fra=$konto_fraUrl&konto_til=$konto_tilUrl$openpostContentParam&udlign=$konto_id[$x]\">$tmp</a></td>";
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
		print "<tr><td colspan='$opColCount'><hr></td></tr>\n";
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
	print "<input type=hidden name=dato_fra value=\"$dato_fraHtml\">";
	print "<input type=hidden name=dato_til value=\"$dato_tilHtml\">";
	print "<input type=hidden name=konto_fra value=\"$konto_fraHtml\">";
	print "<input type=hidden name=konto_til value=\"$konto_tilHtml\">";
	print "<input type=hidden name=kontoantal value=$formIndex>";
	print "<input type=hidden name=showPBS value=\"" . (int)$showPBS . "\">";
	print "<input type=hidden name=openpost_page value=$openpostPage>";
	print "<input type=hidden name=openpost_page_size value=$openpostPageSize></td></tr>";

	if ($kontoart=='D') {
		$overlib4="<span class='CellComment'>".findtekst(242,$sprog_id)."</span>";
		print "<tr><td colspan='$opColCount' align='center' class='border-hr-top'><span title=\"Klik her for at maile kontoudtog til de modtagere som er afm&aelig;rket herover\">";
		print "<input type=submit value=\"Mail kontoudtog\" name=\"submit\"></span>&nbsp;&nbsp;";
		print "<span title='Klik her for at oprette rykker til de som er afm&aelig;rkede herover'>";
		print "<input type=submit value=\"Opret rykker\" name=\"submit\"></span>&nbsp;&nbsp;";
		if ($udlign) {
			$udlign=trim($udlign,",'");
			// URL-encode the report filter values and escape the attribute so quotes in
			// request-supplied values cannot break out of the inline handler (XSS).
			$udlignUrl = 'rapport.php?submit=ok&rapportart=openpost'
				. '&dato_fra=' . rawurlencode($dato_fra)
				. '&dato_til=' . rawurlencode($dato_til)
				. '&konto_fra=' . rawurlencode($konto_fra)
				. '&konto_til=' . rawurlencode($konto_til)
				. ($vis_alle ? '&vis_alle_poster=on' : '&vis_aabenpost=on')
				. $openpostContentParam
				. '&udlign=' . rawurlencode($udlign);
			print "	<input type='button' onclick=\"location.href='" . htmlspecialchars($udlignUrl, ENT_QUOTES) . "';\" title='Klik her for at udligne alle med saldoen' value='Udlign alle' />&nbsp;&nbsp;";
			print "<span class='CellWithComment'><input type=submit value=\"Ryk alle\" name=\"submit\"> $overlib4</span></td>";
		} else {
			print "<span class='CellWithComment'><input type=submit value=\"Ryk alle\" name=\"submit\"> $overlib4</span></td>";
		}
		print "</tr>\n";
	}
	if ($kontoantal > $openpostPageSize) {
		print "<tr><td colspan='$opColCount' align='center' class='border-hr-top'>";
		if ($openpostPage > 1) print "<a href=\"$basePageUrl&openpost_page=".($openpostPage-1)."\">Forrige</a>&nbsp;";
		print "Side $openpostPage af $totalPages";
		if ($openpostPage < $totalPages) print "&nbsp;<a href=\"$basePageUrl&openpost_page=".($openpostPage+1)."\">N&aelig;ste</a>";
		print "</td></tr>\n";
	}
	print "</form>\n";

	if ($menu=='T') {
		print "</tfoot></table></div></tfoot></table>";
	} else {
		print "<tr><td colspan='$opColCount'><hr></td></tr>\n";
		print "</tbody></table>";
	}

	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}

	
}} //endfunc vis_aabne_poster

?>
