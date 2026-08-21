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
// 20260809 Sawaneh Escaped account filter before it reaches SQL and when it is re-emitted into
//                  links/hidden fields. The 0-8 column now honours the same open-at-date rule as
//                  the other four aging columns.
// 20260812 Sawaneh Review: the firm-name search folds case with mb_strtolower/mb_strtoupper,
//                  so names containing ae, oe or aa match regardless of case.
// 20260813 Sawaneh Aging columns filter the report, the total column sorts it and an in-report
//                  account search was added. Filter, sort and search are applied to the account
//                  totals before count and pagination, so paging stays correct.

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
	if ($konto_fra && $konto_fra != '*') {
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

if (!function_exists('openpost_aging_buckets')) {
/**
 * Describes the five aging columns the report renders, in the order they are printed.
 *
 * The array keys are the only values the aging_bucket request parameter accepts. Everything
 * else is dropped by openpost_report_state(), so a key can safely be put into a URL.
 *
 * @return array<string,array{
 *   field: string,  Key the bucket sum is returned under by openpost_account_totals().
 *   label: string,  Column header. A day interval, so it is the same in every language.
 * }>
 */
function openpost_aging_buckets() {
	return array(
		'over90' => array('field' => 'plus90',    'label' => '&gt;90'),
		'60_90'  => array('field' => 'plus60',    'label' => '60-90'),
		'30_60'  => array('field' => 'plus30',    'label' => '30-60'),
		'8_30'   => array('field' => 'plus8',     'label' => '8-30'),
		'0_8'    => array('field' => 'forfalden', 'label' => '0-8')
	);
}
}

if (!function_exists('openpost_report_state')) {
/**
 * Reads the filter, sort and search state of the open posts report off the request.
 *
 * Every value is whitelisted here and nowhere else: aging_bucket must be a key of
 * openpost_aging_buckets(), order_by must be 'amount' and order_dir must be 'asc' or 'desc'.
 * kontonr is the only free text and is only ever handed to openpost_account_filter(), which
 * escapes it, or emitted through rawurlencode()/htmlspecialchars().
 *
 * @return array{
 *   aging_bucket: string,  Bucket key the report is filtered by, or '' for no filter.
 *   order_by: string,      'amount' or '' for the report's own account order.
 *   order_dir: string,     'asc' or 'desc'. Only used when order_by is set.
 *   kontonr: string,       In-report account search, '' when the search field is empty.
 *   aggregate: bool,       True when the account totals must be built for the whole result
 *                          set before paging, i.e. when a bucket filter or a sort is active.
 * }
 */
function openpost_report_state() {
	$buckets = openpost_aging_buckets();
	$bucket = trim((string)if_isset($_REQUEST, '', 'aging_bucket'));
	if (!isset($buckets[$bucket])) $bucket = '';
	$orderBy = trim((string)if_isset($_REQUEST, '', 'order_by'));
	if ($orderBy != 'amount') $orderBy = '';
	$orderDir = strtolower(trim((string)if_isset($_REQUEST, '', 'order_dir')));
	if ($orderDir != 'asc' && $orderDir != 'desc') $orderDir = 'desc';
	$kontonr = trim((string)if_isset($_REQUEST, '', 'kontonr'));
	if (mb_strlen($kontonr, 'UTF-8') > 50) $kontonr = mb_substr($kontonr, 0, 50, 'UTF-8');
	return array(
		'aging_bucket' => $bucket,
		'order_by'     => $orderBy,
		'order_dir'    => $orderDir,
		'kontonr'      => $kontonr,
		'aggregate'    => ($bucket != '' || $orderBy != '')
	);
}
}

if (!function_exists('openpost_state_url')) {
/**
 * Renders the whitelisted report state as url parameters.
 *
 * @param array $state  State from openpost_report_state().
 * @param array $omit   Names to leave out, so a link can replace one part of the state.
 * @return string  Url encoded query fragment starting with '&', or '' when nothing is set.
 */
function openpost_state_url($state, $omit = array()) {
	$url = '';
	if ($state['aging_bucket'] != '' && !in_array('aging_bucket', $omit)) {
		$url.= '&aging_bucket=' . rawurlencode($state['aging_bucket']);
	}
	if ($state['order_by'] != '' && !in_array('order_by', $omit)) {
		$url.= '&order_by=' . rawurlencode($state['order_by']) . '&order_dir=' . rawurlencode($state['order_dir']);
	}
	if ($state['kontonr'] != '' && !in_array('kontonr', $omit)) {
		$url.= '&kontonr=' . rawurlencode($state['kontonr']);
	}
	return $url;
}
}

if (!function_exists('openpost_account_totals')) {
/**
 * Sums one account's open posts into the five aging buckets the report renders.
 *
 * This is the calculation the report has always used, moved out of the print loop so the
 * aggregate pass that filters, sorts and pages the accounts and the print loop that renders
 * the page cannot drift apart. Nothing here writes to the database - ret_openpost() is still
 * called by the print loop only, so the aggregate pass has no side effects.
 *
 * @param array  $posts           openpost rows of one account.
 * @param string $todate          Report date. Posts settled after this date count as open.
 * @param string $currentdate     Today, in Y-m-d.
 * @param string $kontoart        Address type: 'D' for debtors, 'K' for creditors.
 * @param string $baseCurrency    Currency the accounts are summed in.
 * @param mixed  $kun_debet       Set when only accounts in debit are wanted.
 * @param mixed  $kun_kredit      Set when only accounts in credit are wanted.
 * @param array  $agingDateCache  Due-date cache shared across accounts, by reference.
 * @return array{
 *   aligned: int,        0 when the account has at least one post that is open at $todate.
 *   rykkerbelob: float,  Amount that is past due at $todate, settled or not.
 *   forfalden: float,    0-8 days past due.
 *   plus8: float,        8-30 days past due.
 *   plus30: float,       30-60 days past due.
 *   plus60: float,       60-90 days past due.
 *   plus90: float,       More than 90 days past due.
 *   kontrol: float,      Sum of the posts converted with their own exchange rate.
 *   openKontrol: float,  Same, but only the posts that are open at $todate.
 *   y: float,            Account balance in base currency.
 *   openY: float,        Same, but only the posts that are open at $todate.
 *   mismatch: bool,      True when kontrol and y disagree, i.e. the account needs a rebuild.
 *   total: float,        The balance the report prints in the total column.
 *   visible: bool,       True when the report prints a line for this account.
 * }
 */
function openpost_account_totals($posts, $todate, $currentdate, $kontoart, $baseCurrency, $kun_debet, $kun_kredit, &$agingDateCache) {
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
	foreach ($posts as $r) {
		$aligned = $r['udlignet'];
		if ($todate != $currentdate && $r['udlignet'] == '1' && (!$r['udlign_date'] || $r['udlign_date'] > $todate)) {
			$aligned = 0;
		}
		if (!$aligned) $accountAligned = 0;
		if ((float)$r['valutakurs'] && $r['valuta']!='-') {
			$kontrolAmount=afrund($r['amount']*$r['valutakurs']/100,2); //2012.03.30 afrunding rettet til 2 (Ørediff hos saldi_390)
		} else {
			$kontrolAmount=afrund($r['amount'],2);
		}
		$kontrol+=$kontrolAmount;
		if (!$aligned) $openKontrol+=$kontrolAmount;
		($r['forfaldsdate'])?$forfaldsdag=$r['forfaldsdate']:$forfaldsdag=$r['transdate'];
		$transdate=$r['transdate'];
		if ($r['valuta']) $valuta=$r['valuta']; // <- 2009.05.05
		else $valuta=$baseCurrency;
		if ($r['valutakurs']) $valutakurs=$r['valutakurs'];
		else $valutakurs=100;
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
	}
	if ($kun_debet && $y<=0) {$accountAligned=1;$y=0;$kontrol=0;}
	elseif ($kun_kredit && $y>=0) {$accountAligned=1;$y=0;$kontrol=0;}
	$kontrol=afrund($kontrol,2);
	$mismatch=(afrund($kontrol,2)!=afrund($y,2));
	return array(
		'aligned'     => $accountAligned,
		'rykkerbelob' => $rykkerbelob,
		'forfalden'   => $forfalden,
		'plus8'       => $forfalden_plus8,
		'plus30'      => $forfalden_plus30,
		'plus60'      => $forfalden_plus60,
		'plus90'      => $forfalden_plus90,
		'kontrol'     => $kontrol,
		'openKontrol' => $openKontrol,
		'y'           => $y,
		'openY'       => $openY,
		'mismatch'    => $mismatch,
		'total'       => ($mismatch ? $kontrol : $y),
		'visible'     => (abs($y) >= 0.01 || ($todate == $currentdate && ($accountAligned=="0" || $kontrol)))
	);
}
}

if (!function_exists('openpost_bucket_is_set')) {
/**
 * Tells whether an account carries an amount in one aging bucket.
 *
 * The test is the one the print loop uses to decide whether the column is printed at all, so
 * a bucket filter shows exactly the accounts that have a number in that column.
 *
 * @param array  $totals  Totals from openpost_account_totals().
 * @param string $bucket  Bucket key from openpost_aging_buckets().
 * @return bool
 */
function openpost_bucket_is_set($totals, $bucket) {
	$buckets = openpost_aging_buckets();
	if (!isset($buckets[$bucket])) return true;
	$field = $buckets[$bucket]['field'];
	$value = $totals[$field];
	if ($field != 'forfalden') $value = afrund($value,2);
	return (abs($value) > 0);
}
}

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

	$state=openpost_report_state();
	if ($state['kontonr'] != '') {
		// The in-report search replaces the account range the report was opened with. It accepts
		// the same 'fra:til' notation as the account field on the report front page, and is handed
		// to the same openpost_account_filter() branching as that field.
		if (strpos($state['kontonr'],':')) {
			list($konto_fra,$konto_til)=explode(':',$state['kontonr'],2);
		} else {
			$konto_fra=$konto_til=$state['kontonr'];
		}
		$konto_fra=trim($konto_fra);
		$konto_til=trim($konto_til);
		if (!is_numeric($konto_fra)) $konto_til=NULL;
	}

	$dato_fraUrl=rawurlencode((string)$dato_fra);
	$dato_tilUrl=rawurlencode((string)$dato_til);
	$konto_fraUrl=rawurlencode((string)$konto_fra);
	$konto_tilUrl=rawurlencode((string)$konto_til);
	$dato_fraHtml=htmlspecialchars((string)$dato_fra,ENT_QUOTES);
	$dato_tilHtml=htmlspecialchars((string)$dato_til,ENT_QUOTES);
	$konto_fraHtml=htmlspecialchars((string)$konto_fra,ENT_QUOTES);
	$konto_tilHtml=htmlspecialchars((string)$konto_til,ENT_QUOTES);

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

	// One base url for every link the report prints, so the aging filter, the amount sort and the
	// in-report account search survive paging, the PBS toggle and the per account detail link.
	$openpostContentParam = isset($_GET['openpost_content']) ? '&openpost_content=1' : '';
	$reportBaseUrl="rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl&konto_fra=$konto_fraUrl&konto_til=$konto_tilUrl$openpostContentParam&openpost_page_size=$openpostPageSize";
	if ($vis_alle) $reportBaseUrl.="&vis_alle_poster=on";
	elseif ($kun_debet) $reportBaseUrl.="&kun_debet=on";
	elseif ($kun_kredit) $reportBaseUrl.="&kun_kredit=on";
	else $reportBaseUrl.="&vis_aabenpost=on";
	if (!$showPBS) $reportBaseUrl.="&showPBS=0";
	$stateUrl=openpost_state_url($state);
	$basePageUrl=$reportBaseUrl.$stateUrl;

	$bucketTitel=htmlspecialchars(findtekst(5056,$sprog_id),ENT_QUOTES);
	$rydTitel=htmlspecialchars(findtekst(5057,$sprog_id),ENT_QUOTES);
	$sorterTitel=htmlspecialchars(findtekst(5058,$sprog_id),ENT_QUOTES);
	$rydTekst=htmlspecialchars(findtekst(5059,$sprog_id),ENT_QUOTES);
	$soegTekst=htmlspecialchars(findtekst(913,$sprog_id),ENT_QUOTES);
	$kontoTekst=htmlspecialchars(findtekst(43,$sprog_id),ENT_QUOTES);
	$filtreretTekst=htmlspecialchars(findtekst(5060,$sprog_id),ENT_QUOTES);
	$agingBuckets=openpost_aging_buckets();
	$bucketHeader=array();
	foreach ($agingBuckets as $bucketKey => $bucket) {
		$bucketUrl=$reportBaseUrl.openpost_state_url($state,array('aging_bucket'));
		if ($state['aging_bucket'] == $bucketKey) {
			$bucketHeader[$bucketKey]="<a href=\"".htmlspecialchars($bucketUrl,ENT_QUOTES)."\" title=\"$rydTitel\"><b>".$bucket['label']." &times;</b></a>";
		} else {
			$bucketUrl.='&aging_bucket='.rawurlencode($bucketKey);
			$bucketHeader[$bucketKey]="<a href=\"".htmlspecialchars($bucketUrl,ENT_QUOTES)."\" title=\"$bucketTitel\">".$bucket['label']."</a>";
		}
	}
	$nextOrderDir=($state['order_by'] == 'amount' && $state['order_dir'] == 'desc') ? 'asc' : 'desc';
	$sortUrl=$reportBaseUrl.openpost_state_url($state,array('order_by')).'&order_by=amount&order_dir='.$nextOrderDir;
	$sortPil='';
	if ($state['order_by'] == 'amount') $sortPil=($state['order_dir'] == 'asc') ? ' &uarr;' : ' &darr;';
	$totalHeader="<a href=\"".htmlspecialchars($sortUrl,ENT_QUOTES)."\" title=\"$sorterTitel\">".findtekst(2373,$sprog_id)."$sortPil</a>";

	if ($menu=='T') {
		print "<tr><td><div class='dataTablediv'><table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class='dataTable'><thead>\n";
		print "<tr><th>Kontonr.</th>";
		if ($usePBS) print "<th>PBS</th>";
		print "<th>".findtekst(360,$sprog_id)."</th><th align=right class='text-right'>{$bucketHeader['over90']}</th><th align=right  class='text-right'>{$bucketHeader['60_90']}</th><th align=right class='text-right'>{$bucketHeader['30_60']}</th><th align=right class='text-right'>{$bucketHeader['8_30']}</th><th align=right class='text-right'>{$bucketHeader['0_8']}</th><th align=right class='text-right'>$totalHeader</th><th align=right</th>";
		print "</thead><tbody>";
	} else {
		print "<tr><td><table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n";
		print "<tr><td>Kontonr.</th>";
		if ($usePBS) {
			$pbsBaseUrl="rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl&konto_fra=$konto_fraUrl&konto_til=$konto_tilUrl$openpostContentParam$stateUrl";
			if ($showPBS) {
				print "<td title='Skjul PBS kunder'><a href='".htmlspecialchars($pbsBaseUrl."&showPBS=0",ENT_QUOTES)."'>skjul BS</a></td>";
			} else {
				print "<td title='Vis PBS kunder'><a href='".htmlspecialchars($pbsBaseUrl."&showPBS=1",ENT_QUOTES)."'>vis BS</a></td>";
			}
		}
		print "<td>".findtekst(360,$sprog_id)."</td><td align=right>{$bucketHeader['over90']}</td><td align=right>{$bucketHeader['60_90']}</td><td align=right>{$bucketHeader['30_60']}</td><td align=right>{$bucketHeader['8_30']}</td><td align=right>{$bucketHeader['0_8']}</td><td align=right>$totalHeader</td><td></td>";
	}

	// Filter line. The account search navigates from javascript instead of posting a form of its
	// own, because the report body already lives inside the 'aabenpost' form further down.
	$searchBaseUrl=$reportBaseUrl.openpost_state_url($state,array('kontonr'));
	$colspan = $usePBS ? 10 : 9;
	print "<tr><td colspan='$colspan' align='left' style='padding:4px 0;'>";
	print "<span>$kontoTekst:</span>&nbsp;";
	print "<input class='inputbox' type='text' id='openpostKontoSoegning' size='16' value=\"".htmlspecialchars($state['kontonr'],ENT_QUOTES)."\"";
	print " data-base=\"".htmlspecialchars($searchBaseUrl,ENT_QUOTES)."\"";
	print " onkeydown=\"if (event.key=='Enter') {event.preventDefault(); openpostKontoSoeg(); return false;}\">&nbsp;";
	print "<input type='button' value=\"$soegTekst\" onclick='openpostKontoSoeg();'>&nbsp;&nbsp;";
	print "<a href=\"".htmlspecialchars($reportBaseUrl,ENT_QUOTES)."\" title=\"$rydTitel\">$rydTekst</a>";
	if ($state['aging_bucket'] != '') print "&nbsp;&nbsp;<b>$filtreretTekst: ".$agingBuckets[$state['aging_bucket']]['label']."</b>";
	print "</td></tr>\n";
	print "<script>function openpostKontoSoeg() {var f=document.getElementById('openpostKontoSoegning'); window.location.href=f.getAttribute('data-base')+'&kontonr='+encodeURIComponent(f.value);}</script>\n";

	print "<form name=aabenpost action=rapport.php method=post>";

	if ($menu=='T') {
		print "";
	} else {
		print "<tr><td colspan=10><hr></td></tr>\n";
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
	$totalKontoantal=0;
	$agingDateCache=array();
	$accountSort=array();
	$filteredIds=array();
	$pageIds=array();
	$filteredSum=array('forfalden'=>0,'plus8'=>0,'plus30'=>0,'plus60'=>0,'plus90'=>0,'total'=>0);
	if ($state['aggregate']) {
		// A bucket filter or an amount sort works on the account totals, so those have to exist for
		// the whole result set before the page can be cut out of it. The totals are built here from
		// the few openpost columns the calculation needs, one account at a time, and the accounts
		// that survive filter and sort are then paged. Without filter or sort the report keeps its
		// original path below, where only the accounts of the requested page are ever read.
		$qtxt = "select openpost.konto_id, openpost.udlignet, openpost.udlign_date, openpost.transdate, ";
		$qtxt.= "openpost.forfaldsdate, openpost.amount, openpost.valuta, openpost.valutakurs, ";
		$qtxt.= "$accountOrder as account_sort from openpost ";
		if ($db_type == 'postgresql') $qtxt.= "cross join lateral (select * from adresser where id=openpost.konto_id and $accountWhere offset 0) adresser ";
		else $qtxt.= ", adresser ";
		$qtxt.= "where $postWhere";
		if ($db_type != 'postgresql') $qtxt.= " and openpost.konto_id=adresser.id and $accountWhere";
		$qtxt.= " order by account_sort, openpost.konto_id";
		// Only the amount the accounts are sorted by is kept per account, and the totals of the
		// filtered set are added up as they are found. Keeping every account's full totals would
		// cost megabytes on the large databases this report is paginated for.
		$collectAccount = function($accountId,$posts) use (&$accountSort,&$filteredIds,&$filteredSum,&$agingDateCache,$todate,$currentdate,$kontoart,$baseCurrency,$kun_debet,$kun_kredit,$state) {
			$totals=openpost_account_totals($posts,$todate,$currentdate,$kontoart,$baseCurrency,$kun_debet,$kun_kredit,$agingDateCache);
			if (!$totals['visible']) return;
			if ($state['aging_bucket'] != '' && !openpost_bucket_is_set($totals,$state['aging_bucket'])) return;
			$accountSort[$accountId]=$totals['total'];
			$filteredIds[]=$accountId;
			foreach (array('forfalden','plus8','plus30','plus60','plus90','total') as $field) {
				$filteredSum[$field]+=$totals[$field];
			}
		};
		$accountRows=array();
		$accountId=NULL;
		$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($accountId !== NULL && $r['konto_id'] != $accountId) {
				$collectAccount($accountId,$accountRows);
				$accountRows=array();
			}
			$accountId=$r['konto_id'];
			$accountRows[]=$r;
		}
		if ($accountId !== NULL) $collectAccount($accountId,$accountRows);
		if ($state['order_by'] == 'amount') {
			$orderSign=($state['order_dir'] == 'asc') ? 1 : -1;
			usort($filteredIds, function($a,$b) use ($accountSort,$orderSign) {
				return $orderSign*($accountSort[$a] <=> $accountSort[$b]);
			});
		}
		$totalKontoantal=count($filteredIds);
	} else {
		$qtxt = "select count(*) as account_count from (select distinct adresser.id from openpost ";
		if ($db_type == 'postgresql') $qtxt.= "cross join lateral (select id from adresser where id=openpost.konto_id and $accountWhere offset 0) adresser ";
		else $qtxt.= ", adresser ";
		$qtxt.= "where $postWhere";
		if ($db_type != 'postgresql') $qtxt.= " and openpost.konto_id=adresser.id and $accountWhere";
		$qtxt.= ") account_count";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $totalKontoantal=(int)$r['account_count'];
	}
	$totalPages=($totalKontoantal) ? ceil($totalKontoantal/$openpostPageSize) : 1;
	if ($openpostPage > $totalPages) {
		$openpostPage=$totalPages;
		$openpostOffset=($openpostPage-1)*$openpostPageSize;
	}
	$rowsByAccount=$metaByAccount=array();
	if ($state['aggregate']) {
		$pageIds=array_slice($filteredIds,$openpostOffset,$openpostPageSize);
		if ($pageIds) {
			$idList=implode(",",array_map('intval',$pageIds));
			$qtxt = "select adresser.id as account_id, adresser.kontonr as account_kontonr, adresser.firmanavn as account_firmanavn, ";
			$qtxt.= "adresser.addr1 as account_addr1, adresser.addr2 as account_addr2, adresser.postnr as account_postnr, ";
			$qtxt.= "adresser.bynavn as account_bynavn, adresser.email as account_email, adresser.betalingsbet as account_betalingsbet, ";
			$qtxt.= "adresser.betalingsdage as account_betalingsdage, adresser.pbs as account_pbs, adresser.pbs_nr as account_pbs_nr, ";
			$qtxt.= "openpost.* from openpost, adresser where openpost.konto_id=adresser.id and openpost.konto_id in ($idList) ";
			$qtxt.= "and $postWhere and $accountWhere ";
			$qtxt.= "order by openpost.konto_id, openpost.faktnr, openpost.amount $tmp";
			$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if (!isset($metaByAccount[$r['account_id']])) $metaByAccount[$r['account_id']]=$r;
				$rowsByAccount[$r['account_id']][]=$r;
			}
			// An account whose posts changed between the two queries would have no rows here.
			$pageIds=array_values(array_filter($pageIds, function($accountId) use ($rowsByAccount) {
				return isset($rowsByAccount[$accountId]);
			}));
		}
	} else {
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
		$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (!isset($metaByAccount[$r['account_id']])) {
				$metaByAccount[$r['account_id']]=$r;
				$pageIds[]=$r['account_id'];
			}
			$rowsByAccount[$r['account_id']][]=$r;
		}
	}
	$konto_id = $kontonr = array();
	$x=0;
	foreach ($pageIds as $accountId) {
		$r=$metaByAccount[$accountId];
		$x++;
		$accountIndex[$accountId]=$x;
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
		$accountPosts[$x]=$rowsByAccount[$accountId];
	}
	$pageAccountCount=$x;
	$kontoantal=$totalKontoantal;
	$sum=0;
	$kontrolsum=0;
	$udlign=NULL;
	$formIndex=0;
	$displayFirst=($kontoantal) ? $openpostOffset+1 : 0;
	$displayLast=min($kontoantal, $openpostOffset+$pageAccountCount);
	if ($kontoantal > $openpostPageSize) {
		$colspan = $usePBS ? 10 : 9;
		print "<tr><td colspan='$colspan' align='center'>";
		if ($openpostPage > 1) print "<a href=\"$basePageUrl&openpost_page=".($openpostPage-1)."\">Forrige</a>&nbsp;";
		print "Viser $displayFirst-$displayLast af $kontoantal";
		if ($openpostPage < $totalPages) print "&nbsp;<a href=\"$basePageUrl&openpost_page=".($openpostPage+1)."\">N&aelig;ste</a>";
		print "</td></tr>\n";
	}
	for ($x=1; $x<=$pageAccountCount; $x++) {
		// Same openpost_account_totals() the aggregate pass above uses, so filter, sort and print
		// can never disagree about what an account owes.
		$totals=openpost_account_totals($accountPosts[$x],$todate,$currentdate,$kontoart,$baseCurrency,$kun_debet,$kun_kredit,$agingDateCache);
		$accountAligned=$totals['aligned'];
		$rykkerbelob=$totals['rykkerbelob'];
		$forfalden=$totals['forfalden'];
		$forfalden_plus8=$totals['plus8'];
		$forfalden_plus30=$totals['plus30'];
		$forfalden_plus60=$totals['plus60'];
		$forfalden_plus90=$totals['plus90'];
		$kontrol=$totals['kontrol'];
		$openKontrol=$totals['openKontrol'];
		$y=$totals['y'];
		$openY=$totals['openY'];
		if ($totals['visible'])	{
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
			$detailUrl="rapport.php?rapportart=accountChart&kilde=openpost&kto_fra=$konto_fraUrl&kilde_kto_til=$konto_tilUrl&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl&konto_fra=$kontonrUrl&konto_til=$kontonrUrl&submit=ok$stateUrl";
			print "<td><a href=\"".htmlspecialchars($detailUrl,ENT_QUOTES)."\">";
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
				$rowUdlignUrl="rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl&konto_fra=$konto_fraUrl&konto_til=$konto_tilUrl$stateUrl&udlign=$konto_id[$x]";
				print "<td align=right title=\"Klik her for at udligne &aring;bne poster\"><a href=\"".htmlspecialchars($rowUdlignUrl,ENT_QUOTES)."\">$tmp</a></td>";
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
	print "<input type=hidden name=dato_fra value=\"$dato_fraHtml\">";
	print "<input type=hidden name=dato_til value=\"$dato_tilHtml\">";
	print "<input type=hidden name=konto_fra value=\"$konto_fraHtml\">";
	print "<input type=hidden name=konto_til value=\"$konto_tilHtml\">";
	print "<input type=hidden name=kontoantal value=$formIndex>";
	print "<input type=hidden name=openpost_page value=$openpostPage>";
	print "<input type=hidden name=openpost_page_size value=$openpostPageSize>";
	// Carry the whitelisted state through the actions that post the form back to rapport.php.
	if ($state['aging_bucket'] != '') print "<input type=hidden name=aging_bucket value=\"".htmlspecialchars($state['aging_bucket'],ENT_QUOTES)."\">";
	if ($state['order_by'] != '') {
		print "<input type=hidden name=order_by value=\"".htmlspecialchars($state['order_by'],ENT_QUOTES)."\">";
		print "<input type=hidden name=order_dir value=\"".htmlspecialchars($state['order_dir'],ENT_QUOTES)."\">";
	}
	if ($state['kontonr'] != '') print "<input type=hidden name=kontonr value=\"".htmlspecialchars($state['kontonr'],ENT_QUOTES)."\">";
	print "</td></tr>";

	// Second total line covering every account the filter and the sort selected, not just the page.
	if ($state['aggregate']) {
		print "<tr><td colspan='$colspan'><br></td><td><b>".htmlspecialchars(findtekst(5061,$sprog_id),ENT_QUOTES)." ($totalKontoantal)</b></td>";
		foreach (array('plus90','plus60','plus30','plus8','forfalden','total') as $field) {
			$tmp=afrund($filteredSum[$field],2);
			$color=($tmp != 0) ? "rgb(255, 0, 0)" : "rgb(0, 0, 0)";
			if ($field == 'total') $color="rgb(0, 0, 0)";
			print "<td align=right><span style='color: $color;'>".dkdecimal($tmp,2)."</span></td>";
		}
		print "<td align=right></td></tr>\n";
	}

	if ($kontoart=='D') {
		$overlib4="<span class='CellComment'>".findtekst(242,$sprog_id)."</span>";
		print "<tr><td colspan='10' align='center' class='border-hr-top'><span title=\"Klik her for at maile kontoudtog til de modtagere som er afm&aelig;rket herover\">";
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
				. $stateUrl
				. '&udlign=' . rawurlencode($udlign);
			print "	<input type='button' onclick=\"location.href='" . htmlspecialchars($udlignUrl, ENT_QUOTES) . "';\" title='Klik her for at udligne alle med saldoen' value='Udlign alle' />&nbsp;&nbsp;";
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
