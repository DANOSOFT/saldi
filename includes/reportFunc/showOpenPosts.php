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
// 20260826 Sawaneh Udlign alle-linket genbruger de allerede kodede filterværdier, så et tomt
//                  datofilter ikke giver en rawurlencode(null)-deprecation i php 8.
// 20260826 Sawaneh SD-140: aging-bucket filter, amount sort and in-report account search. The
//                  per-account bucket maths moved unchanged into openpost_account_aging(); when a
//                  filter or sort is active it runs as a streaming pre-pass over every matching
//                  account before the count and the pagination, so pages and counts stay right.

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

if (!function_exists('openpost_kontonr_range')) {
/**
 * Splits the kontonr value of the in-report account search into the konto_fra/konto_til pair the
 * report works with, using the same rules as the report front page: "fra:til" is a range, anything
 * non-numeric is a firm-name pattern where only konto_fra matters.
 *
 * @param string $kontonr  Raw search value.
 * @return array{
 *   0: string,       konto_fra.
 *   1: string|null,  konto_til, or null for a firm-name pattern.
 * }
 */
function openpost_kontonr_range($kontonr) {
	$konto_fra = trim((string)$kontonr);
	if (strpos($konto_fra, ':')) {
		list($konto_fra, $konto_til) = explode(':', $konto_fra, 2);
		$konto_fra = trim($konto_fra);
		$konto_til = trim($konto_til);
	} else {
		$konto_til = $konto_fra;
	}
	if (!is_numeric($konto_fra) || !is_numeric($konto_til)) $konto_til = NULL;
	return array($konto_fra, $konto_til);
}
}

if (!function_exists('openpost_aging_buckets')) {
/**
 * The five aging columns of the open posts report, keyed by the aging_bucket request value.
 *
 * @return array<string, array{
 *   key: string,    Index of the bucket in the array returned by openpost_account_aging().
 *   label: string,  Column header as rendered.
 * }>
 */
function openpost_aging_buckets() {
	return array(
		'over90' => array('key' => 'forfalden_plus90', 'label' => '>90'),
		'60-90'  => array('key' => 'forfalden_plus60', 'label' => '60-90'),
		'30-60'  => array('key' => 'forfalden_plus30', 'label' => '30-60'),
		'8-30'   => array('key' => 'forfalden_plus8',  'label' => '8-30'),
		'0-8'    => array('key' => 'forfalden',        'label' => '0-8')
	);
}
}

if (!function_exists('openpost_report_state')) {
/**
 * Reads the filter/sort state of the open posts report from the request and reduces it to the
 * whitelisted values. Anything else falls back to the default, so the returned values are safe to
 * interpolate into SQL, URLs and attributes.
 *
 * @return array{
 *   aging_bucket: string,  Key of openpost_aging_buckets(), or '' when unfiltered.
 *   order_by: string,      'amount', or '' for the default account order.
 *   order_dir: string,     'asc' or 'desc'.
 * }
 */
function openpost_report_state() {
	$bucket = if_isset($_REQUEST, '', 'aging_bucket');
	if (!is_string($bucket) || !array_key_exists($bucket, openpost_aging_buckets())) $bucket = '';
	$orderBy = (if_isset($_REQUEST, '', 'order_by') === 'amount') ? 'amount' : '';
	$orderDir = if_isset($_REQUEST, '', 'order_dir');
	$orderDir = (is_string($orderDir) && strtolower($orderDir) === 'asc') ? 'asc' : 'desc';
	return array('aging_bucket' => $bucket, 'order_by' => $orderBy, 'order_dir' => $orderDir);
}
}

if (!function_exists('openpost_state_url')) {
/**
 * Query string fragment carrying the report state, for appending to rapport.php links.
 *
 * @param array $state     As returned by openpost_report_state().
 * @param array $override  Keys of $state to replace in the fragment.
 * @return string  Fragment with a leading '&', or '' when the state is the default.
 */
function openpost_state_url($state, $override = array()) {
	$state = array_merge($state, $override);
	$url = '';
	if ($state['aging_bucket'] !== '') $url.= '&aging_bucket='.rawurlencode($state['aging_bucket']);
	if ($state['order_by'] !== '') $url.= '&order_by='.rawurlencode($state['order_by']).'&order_dir='.rawurlencode($state['order_dir']);
	return $url;
}
}

if (!function_exists('openpost_account_aging')) {
/**
 * Splits the open posts of one account into the five aging buckets of the report.
 *
 * Moved unchanged out of vis_aabne_poster() so the filtered/sorted pre-pass and the rendered page
 * share one implementation: amounts are converted with the row's valutakurs (re-derived from the
 * valuta table when a foreign-currency row carries the kurs=100 placeholder, SST-672), and a post
 * settled after $todate still counts as open when the report is run for a historical date.
 *
 * @param array  $posts           Openpost rows of one account (amount, valuta, valutakurs, transdate,
 *                                forfaldsdate, udlignet, udlign_date).
 * @param string $todate          Report date, Y-m-d.
 * @param string $currentdate     Today, Y-m-d.
 * @param string $kontoart        'D' for debtors, 'K' for creditors.
 * @param array  $agingDateCache  Shared cache of the +8/+30/+60/+90 dates per transdate|dage.
 * @return array{
 *   forfalden: float,         Open amount due 0-8 days.
 *   forfalden_plus8: float,   Open amount due 8-30 days.
 *   forfalden_plus30: float,  Open amount due 30-60 days.
 *   forfalden_plus60: float,  Open amount due 60-90 days.
 *   forfalden_plus90: float,  Open amount due more than 90 days.
 *   y: float,                 Sum of all posts of the account.
 *   openY: float,             Sum of the open posts.
 *   kontrol: float,           Control sum of all posts, rounded per row.
 *   openKontrol: float,       Control sum of the open posts.
 *   rykkerbelob: float,       Sum of the posts due before $todate.
 *   accountAligned: int,      1 when every post is settled at $todate.
 * }
 */
function openpost_account_aging($posts, $todate, $currentdate, $kontoart, &$agingDateCache) {
	global $baseCurrency;
	static $kursCache = array();
	static $gruppeCache = array();
	$aging = array(
		'forfalden' => 0, 'forfalden_plus8' => 0, 'forfalden_plus30' => 0, 'forfalden_plus60' => 0, 'forfalden_plus90' => 0,
		'y' => 0, 'openY' => 0, 'kontrol' => 0, 'openKontrol' => 0, 'rykkerbelob' => 0, 'accountAligned' => 1
	);
	foreach ($posts as $r) {
		$aligned = $r['udlignet'];
		if ($todate != $currentdate && $r['udlignet'] == '1' && (!$r['udlign_date'] || $r['udlign_date'] > $todate)) {
			$aligned = 0;
		}
		if (!$aligned) $aging['accountAligned'] = 0;
		$valuta = ($r['valuta']) ? $r['valuta'] : $baseCurrency;
		$valutakurs = ($r['valutakurs']) ? $r['valutakurs'] : 100;
		if ($valuta != $baseCurrency && $valutakurs == 100) {
			$kursKey = $valuta.'|'.$r['transdate']; // 20260826 CL/NTR per-request cache: the same currency/date pair recurs across rows and accounts, so resolve it once
			if (!isset($kursCache[$kursKey])) {
				$kursCache[$kursKey] = 100;
				if (!isset($gruppeCache[$valuta])) {
					$qtxt = "select kodenr from grupper where box1 = '".db_escape_string($valuta)."' and art='VK'";
					$r3 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$gruppeCache[$valuta] = ($r3) ? $r3['kodenr'] : '';
				}
				if ($gruppeCache[$valuta]) {
					$qtxt = "select kurs from valuta where gruppe ='".db_escape_string($gruppeCache[$valuta])."' and valdate <= '".db_escape_string($r['transdate'])."' order by valdate desc limit 1";
					$r3 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					if ($r3 && $r3['kurs']) $kursCache[$kursKey] = $r3['kurs']*1;
				}
			}
			$valutakurs = $kursCache[$kursKey];
		}
		if ((float)$valutakurs && $r['valuta'] != '-') {
			$kontrolAmount = afrund($r['amount']*$valutakurs/100,2);
		} else {
			$kontrolAmount = afrund($r['amount'],2);
		}
		$aging['kontrol'] += $kontrolAmount;
		if (!$aligned) $aging['openKontrol'] += $kontrolAmount;
		$forfaldsdag = ($r['forfaldsdate']) ? $r['forfaldsdate'] : $r['transdate'];
		$transdate = $r['transdate'];
		$amount = ($valuta == $baseCurrency) ? afrund($r['amount'],2) : afrund($r['amount'],3);
		if (!$forfaldsdag && $kontoart == 'D' && $amount < 0) $forfaldsdag = $r['transdate'];
		elseif (!$forfaldsdag && $kontoart == 'K' && $amount > 0) $forfaldsdag = $r['transdate'];
		elseif (!$forfaldsdag) $forfaldsdag = $r['forfaldsdate'];
		$amount *= $valutakurs/100;
		$fakt_utid = strtotime($transdate);
		$forf_utid = strtotime($forfaldsdag);
		$dage = afrund(($forf_utid-$fakt_utid)/86400,0);
		$agingKey = $transdate . "|" . $dage;
		if (!isset($agingDateCache[$agingKey])) {
			$agingDateCache[$agingKey] = array(
				usdate(forfaldsdag($transdate, 'netto',$dage+8)),
				usdate(forfaldsdag($transdate, 'netto',$dage+30)),
				usdate(forfaldsdag($transdate, 'netto',$dage+60)),
				usdate(forfaldsdag($transdate, 'netto',$dage+90))
			);
		}
		list($forfaldsdag_plus8,$forfaldsdag_plus30,$forfaldsdag_plus60,$forfaldsdag_plus90) = $agingDateCache[$agingKey];
		if ($forfaldsdag < $todate) $aging['rykkerbelob'] += $amount;
		if (!$aligned && $forfaldsdag < $todate && $forfaldsdag_plus8 > $todate) $aging['forfalden'] += $amount;
		if (!$aligned && $forfaldsdag_plus8 <= $todate && $forfaldsdag_plus30 > $todate) $aging['forfalden_plus8'] += $amount;
		if (!$aligned && $forfaldsdag_plus30 <= $todate && $forfaldsdag_plus60 > $todate) $aging['forfalden_plus30'] += $amount;
		if (!$aligned && $forfaldsdag_plus60 <= $todate && $forfaldsdag_plus90 > $todate) $aging['forfalden_plus60'] += $amount;
		if (!$aligned && $forfaldsdag_plus90 <= $todate) $aging['forfalden_plus90'] += $amount;
		$aging['y'] += $amount;
		if (!$aligned) $aging['openY'] += $amount;
	}
	return $aging;
}
}

if (!function_exists('openpost_account_visible')) {
/**
 * Applies the kun_debet/kun_kredit mode to an account's sums and tells whether the account is
 * listed at all. Same rule the rendering has always used, shared with the pre-pass so the account
 * count and the pages only contain accounts that are actually listed.
 *
 * @param array  $aging        Result of openpost_account_aging(). y, kontrol and accountAligned are
 *                             zeroed here when the account falls outside the debet/kredit mode.
 * @param string $todate       Report date, Y-m-d.
 * @param string $currentdate  Today, Y-m-d.
 * @param string $kun_debet    'on' to list only accounts in debit.
 * @param string $kun_kredit   'on' to list only accounts in credit.
 * @return bool
 */
function openpost_account_visible(&$aging, $todate, $currentdate, $kun_debet, $kun_kredit) {
	if ($kun_debet && $aging['y'] <= 0) {$aging['accountAligned'] = 1; $aging['y'] = 0; $aging['kontrol'] = 0;}
	elseif ($kun_kredit && $aging['y'] >= 0) {$aging['accountAligned'] = 1; $aging['y'] = 0; $aging['kontrol'] = 0;}
	$aging['kontrol'] = afrund($aging['kontrol'],2);
	return (abs($aging['y']) >= 0.01 || ($todate == $currentdate && ($aging['accountAligned'] == "0" || $aging['kontrol'])));
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
	
	
	$dato_fraUrl=rawurlencode((string)$dato_fra);
	$dato_tilUrl=rawurlencode((string)$dato_til);
	$konto_fraUrl=rawurlencode((string)$konto_fra);
	$konto_tilUrl=rawurlencode((string)$konto_til);
	$dato_fraHtml=htmlspecialchars((string)$dato_fra,ENT_QUOTES);
	$dato_tilHtml=htmlspecialchars((string)$dato_til,ENT_QUOTES);
	$konto_fraHtml=htmlspecialchars((string)$konto_fra,ENT_QUOTES);
	$konto_tilHtml=htmlspecialchars((string)$konto_til,ENT_QUOTES);
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

	$state=openpost_report_state();
	$agingBucket=$state['aging_bucket'];
	$orderBy=$state['order_by'];
	$orderDir=$state['order_dir'];
	$buckets=openpost_aging_buckets();
	$stateUrl=openpost_state_url($state);
	// Carry the async shell's content flag on every link back into this report (PBS toggle,
	// pagination, udlign, the account search), so those requests render the report directly
	// instead of re-entering the shell in debitor/rapport.php. The shell accepts the flag from
	// GET or POST, so honour both.
	$openpostContentParam = (isset($_GET['openpost_content']) || isset($_POST['openpost_content'])) ? '&openpost_content=1' : '';
	$reportUrl="rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl&konto_fra=$konto_fraUrl&konto_til=$konto_tilUrl$openpostContentParam&openpost_page_size=$openpostPageSize";
	if ($vis_alle) $modeParam="vis_alle_poster";
	elseif ($kun_debet) $modeParam="kun_debet";
	elseif ($kun_kredit) $modeParam="kun_kredit";
	else $modeParam="vis_aabenpost";
	$reportUrl.="&$modeParam=on";
	if (!$showPBS) $reportUrl.="&showPBS=0";
	$basePageUrl=$reportUrl.$stateUrl;

	$filterTitle=htmlspecialchars(findtekst('5121|Vis kun konti med beløb i denne kolonne',$sprog_id),ENT_QUOTES);
	$clearTitle=htmlspecialchars(findtekst('5120|Ryd filter',$sprog_id),ENT_QUOTES);
	$sortTitle=htmlspecialchars(findtekst('5122|Sortér efter beløb',$sprog_id),ENT_QUOTES);
	$sortDescUrl=$reportUrl.openpost_state_url($state,array('order_by'=>($orderBy && $orderDir=='desc') ? '' : 'amount','order_dir'=>'desc'));
	$sortAscUrl=$reportUrl.openpost_state_url($state,array('order_by'=>($orderBy && $orderDir=='asc') ? '' : 'amount','order_dir'=>'asc'));
	$sortLinks=" <a href=\"$sortDescUrl\" title='$sortTitle'>".(($orderBy && $orderDir=='desc') ? '<b>&#9660;</b>' : '&#9660;')."</a>";
	$sortLinks.="<a href=\"$sortAscUrl\" title='$sortTitle'>".(($orderBy && $orderDir=='asc') ? '<b>&#9650;</b>' : '&#9650;')."</a>";
	$clearUrl=$reportUrl.openpost_state_url($state,array('aging_bucket'=>''));
	$headerCell=array();
	foreach ($buckets as $bucketId => $bucket) {
		if ($agingBucket == $bucketId) {
			$headerCell[$bucketId]="<b>$bucket[label]</b> <a href=\"$clearUrl\" title='$clearTitle'>&#10006;</a>$sortLinks";
		} else {
			$headerCell[$bucketId]="<a href=\"".$reportUrl.openpost_state_url($state,array('aging_bucket'=>$bucketId))."\" title='$filterTitle'>$bucket[label]</a>";
		}
	}
	$headerCell['total']="I alt".(($agingBucket) ? "" : $sortLinks);

	$searchValue=$konto_fraHtml;
	if ($konto_til !== NULL && $konto_til !== '' && $konto_til != $konto_fra && is_numeric($konto_fra) && is_numeric($konto_til)) $searchValue.=":$konto_tilHtml";
	$searchRow="<form method='get' action='rapport.php' style='display:inline;'>";
	$searchRow.="<input type='hidden' name='rapportart' value='openpost'><input type='hidden' name='submit' value='ok'>";
	$searchRow.="<input type='hidden' name='dato_fra' value=\"$dato_fraHtml\"><input type='hidden' name='dato_til' value=\"$dato_tilHtml\">";
	if ($openpostContentParam) $searchRow.="<input type='hidden' name='openpost_content' value='1'>";
	$searchRow.="<input type='hidden' name='openpost_page_size' value='$openpostPageSize'><input type='hidden' name='$modeParam' value='on'>";
	if (!$showPBS) $searchRow.="<input type='hidden' name='showPBS' value='0'>";
	if ($agingBucket) $searchRow.="<input type='hidden' name='aging_bucket' value='$agingBucket'>";
	if ($orderBy) $searchRow.="<input type='hidden' name='order_by' value='$orderBy'><input type='hidden' name='order_dir' value='$orderDir'>";
	$searchRow.="<input class='inputbox' type='text' name='kontonr' value=\"$searchValue\" style='width:180px;' title=\"".htmlspecialchars(findtekst('5123|Kontonr., interval (fra:til) eller firmanavn (* = jokertegn)',$sprog_id),ENT_QUOTES)."\"> ";
	$searchRow.="<input type='submit' value=\"".htmlspecialchars(findtekst(913,$sprog_id),ENT_QUOTES)."\"></form>";
	if ($agingBucket) {
		$searchRow.=" &nbsp; <b>".htmlspecialchars(findtekst('5124|Filter',$sprog_id),ENT_QUOTES).":</b> ".$buckets[$agingBucket]['label']." <a href=\"$clearUrl\">".htmlspecialchars(findtekst('5120|Ryd filter',$sprog_id),ENT_QUOTES)."</a>";
	}
	$headerColspan = $usePBS ? 10 : 9;

	if ($menu=='T') {
		print "<tr><td><div class='dataTablediv'><table id='visAabnePosterTableT' width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class='dataTable'><thead>\n";
		print "<tr><th>Kontonr.</th>";
		if ($usePBS) print "<th>PBS</th>";
		print "<th>".findtekst(360,$sprog_id)."</th><th align=right class='text-right'>$headerCell[over90]</th><th align=right  class='text-right'>{$headerCell['60-90']}</th><th align=right class='text-right'>{$headerCell['30-60']}</th><th align=right class='text-right'>{$headerCell['8-30']}</th><th align=right class='text-right'>{$headerCell['0-8']}</th><th align=right class='text-right'>$headerCell[total]</th><th align=right</th>";
		print "<tr><th colspan='$headerColspan' style='font-weight:normal;'>$searchRow</th></tr>";
		print "</thead><tbody>";
	} else {
		print "<tr><td><table id='visAabnePosterTable' width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n";
		print "<tr><td>Kontonr.</th>";
		if ($usePBS) {
			if ($showPBS) {
				print "<td title='Skjul PBS kunder'><a href='rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl&konto_fra=$konto_fraUrl&konto_til=$konto_tilUrl$openpostContentParam&showPBS=0$stateUrl'>skjul BS</a></td>";
			} else {
				print "<td title='Vis PBS kunder'><a href='rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl&konto_fra=$konto_fraUrl&konto_til=$konto_tilUrl$openpostContentParam&showPBS=1$stateUrl'>vis BS</a></td>";
			}
		}
		print "<td>".findtekst(360,$sprog_id)."</td><td align=right>$headerCell[over90]</td><td align=right>{$headerCell['60-90']}</td><td align=right>{$headerCell['30-60']}</td><td align=right>{$headerCell['8-30']}</td><td align=right>{$headerCell['0-8']}</td><td align=right>$headerCell[total]</td><td></td>";
		print "<tr><td colspan='$headerColspan'>$searchRow</td></tr>";
	}

	// Push the grid header out before the heavy count/page queries below, so the user sees
	// the empty table immediately while the SQL runs (ob_flush drains php.ini's output_buffering).
	if (ob_get_level() > 0) @ob_flush();
	flush();


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
	$accountGroup = "select openpost.konto_id from openpost where $postWhere group by openpost.konto_id$accountHaving";
	$accountSource = "($accountGroup) account_posts";
	$totalKontoantal=0;
	$agingDateCache=array();
	$pageAccountIds=NULL;
	if ($agingBucket || $orderBy) {
		// Filter/sort pre-pass: aggregate every matching account with the same bucket maths as the
		// rendering, then page the resulting account ids - filtering the rows of one page would
		// return wrong pages and counts. The account superset is the same $accountGroup the count
		// below uses, so a filtered page never shows an account the unfiltered report leaves out.
		$bucketKey=($agingBucket) ? $buckets[$agingBucket]['key'] : 'y';
		$sortedAccounts=array();
		$addAccount=function($accountId, $posts) use (&$sortedAccounts, &$agingDateCache, $bucketKey, $agingBucket, $todate, $currentdate, $kontoart, $kun_debet, $kun_kredit) {
			$aging=openpost_account_aging($posts, $todate, $currentdate, $kontoart, $agingDateCache);
			if (!openpost_account_visible($aging, $todate, $currentdate, $kun_debet, $kun_kredit)) return;
			$amount=afrund($aging[$bucketKey],2);
			if ($agingBucket && abs($amount) < 0.01) return;
			$sortedAccounts[]=array((int)$accountId, $amount, count($sortedAccounts));
		};
		$qtxt = "select openpost.konto_id, openpost.amount, openpost.valuta, openpost.valutakurs, openpost.transdate, ";
		$qtxt.= "openpost.forfaldsdate, openpost.udlignet, openpost.udlign_date, $accountOrder as account_sort from openpost ";
		if ($db_type == 'postgresql') $qtxt.= "cross join lateral (select id, kontonr, firmanavn from adresser where id=openpost.konto_id and $accountWhere offset 0) adresser ";
		else $qtxt.= ", adresser ";
		$qtxt.= "where $postWhere";
		if ($db_type != 'postgresql') $qtxt.= " and openpost.konto_id=adresser.id and $accountWhere";
		$qtxt.= " and openpost.konto_id in ($accountGroup)";
		$qtxt.= " order by account_sort, openpost.konto_id";
		$posts=array();
		$currentAccount=NULL;
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($currentAccount !== NULL && $r['konto_id'] != $currentAccount) {
				$addAccount($currentAccount, $posts);
				$posts=array();
			}
			$currentAccount=$r['konto_id'];
			$posts[]=$r;
		}
		if ($posts) $addAccount($currentAccount, $posts);
		if ($orderBy == 'amount') {
			$sign=($orderDir == 'asc') ? 1 : -1;
			usort($sortedAccounts, function($a, $b) use ($sign) {
				if ($a[1] == $b[1]) return $a[2] <=> $b[2];
				return ($a[1] < $b[1]) ? -$sign : $sign;
			});
		}
		$totalKontoantal=count($sortedAccounts);
	} else {
		$qtxt = "select count(*) as account_count from $accountSource ";
		if ($db_type == 'postgresql') $qtxt.= "cross join lateral (select id from adresser where id=account_posts.konto_id and $accountWhere offset 0) adresser";
		else $qtxt.= ", adresser where account_posts.konto_id=adresser.id and $accountWhere";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $totalKontoantal=(int)$r['account_count'];
	}
	$totalPages=($totalKontoantal) ? ceil($totalKontoantal/$openpostPageSize) : 1;
	if ($openpostPage > $totalPages) {
		$openpostPage=$totalPages;
		$openpostOffset=($openpostPage-1)*$openpostPageSize;
	}
	if ($agingBucket || $orderBy) {
		$pageAccountIds=array();
		foreach (array_slice($sortedAccounts, $openpostOffset, $openpostPageSize) as $i => $account) {
			$pageAccountIds[]=$account[0];
			$accountIndex[$account[0]]=$i+1;
		}
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
	// The pre-pass already picked (and ordered) the accounts of this page, so the page query only
	// has to fetch them - the sql limit/offset would page the unfiltered account order instead.
	// The postgresql variant joins adresser laterally and has no where clause of its own yet.
	if ($pageAccountIds !== NULL) {
		$qtxt.= ($db_type == 'postgresql') ? " where" : " and";
		$qtxt.= " adresser.id in (".implode(',', $pageAccountIds).")) account_page ";
	}
	else $qtxt.= " order by account_sort limit $openpostPageSize offset $openpostOffset) account_page ";
	$qtxt.= "join openpost on openpost.konto_id=account_page.account_id where $postWhere ";
	$qtxt.= "order by account_page.account_sort, openpost.konto_id, openpost.faktnr, openpost.amount $tmp";
	$konto_id = $kontonr = array();
	$x=0;
	$q=($pageAccountIds === array()) ? false : db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($q && ($r = db_fetch_array($q))) {
		if (!isset($accountIndex[$r['account_id']])) {
			$x++;
			$accountIndex[$r['account_id']]=$x;
		}
		$i=$accountIndex[$r['account_id']];
		if (!isset($konto_id[$i])) {
			$konto_id[$i]=$r['account_id'];
			$kontonr[$i]=trim($r['account_kontonr']);
			$firmanavn[$i]=stripslashes($r['account_firmanavn']);
			$addr1[$i]=stripslashes($r['account_addr1']);
			$addr2[$i]=stripslashes($r['account_addr2']);
			$postnr[$i]=trim($r['account_postnr']);
			$bynavn[$i]=stripslashes($r['account_bynavn']);
			$email[$i]=trim($r['account_email']);
			$betalingsbet[$i]=trim($r['account_betalingsbet']);
			$betalingsdage[$i]=trim($r['account_betalingsdage']);
			$pbs[$i]=trim($r['account_pbs']);
			$pbs_nr[$i]=trim($r['account_pbs_nr']);
			($pbs[$i] && $pbs_nr[$i])?$pbs[$i]='&#10004;':$pbs[$i]=NULL;
			$accountPosts[$i]=array();
		}
		$accountPosts[$i][]=$r;
	}
	$pageAccountCount=($pageAccountIds !== NULL) ? count($pageAccountIds) : $x;
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
		if (!isset($accountPosts[$x])) continue;
		$aging=openpost_account_aging($accountPosts[$x], $todate, $currentdate, $kontoart, $agingDateCache);
		if (openpost_account_visible($aging, $todate, $currentdate, $kun_debet, $kun_kredit)) {
			$accountAligned=$aging['accountAligned'];
			$rykkerbelob=$aging['rykkerbelob'];
			$forfalden=$aging['forfalden'];
			$forfalden_plus8=$aging['forfalden_plus8'];
			$forfalden_plus30=$aging['forfalden_plus30'];
			$forfalden_plus60=$aging['forfalden_plus60'];
			$forfalden_plus90=$aging['forfalden_plus90'];
			$kontrol=$aging['kontrol'];
			$openKontrol=$aging['openKontrol'];
			$y=$aging['y'];
			$openY=$aging['openY'];
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
			print "<td><a href=\"rapport.php?rapportart=accountChart&kilde=openpost&kto_fra=$konto_fraUrl&kilde_kto_til=$konto_tilUrl&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl&konto_fra=$kontonrUrl&konto_til=$kontonrUrl&submit=ok$stateUrl\">";
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
				print "<td align=right title=\"Klik her for at udligne &aring;bne poster\"><a href=\"rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl&konto_fra=$konto_fraUrl&konto_til=$konto_tilUrl$openpostContentParam$stateUrl&udlign=$konto_id[$x]\">$tmp</a></td>";
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
	print "<input type=hidden name=aging_bucket value=\"$agingBucket\">";
	print "<input type=hidden name=order_by value=\"$orderBy\">";
	print "<input type=hidden name=order_dir value=\"$orderDir\"></td></tr>";

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
				. '&dato_fra=' . $dato_fraUrl
				. '&dato_til=' . $dato_tilUrl
				. '&konto_fra=' . $konto_fraUrl
				. '&konto_til=' . $konto_tilUrl
				. ($vis_alle ? '&vis_alle_poster=on' : '&vis_aabenpost=on')
				. $openpostContentParam
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
