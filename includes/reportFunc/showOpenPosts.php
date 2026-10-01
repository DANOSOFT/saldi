<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/reportFunc/showOpenPosts.php --- ver 5.0.0 --- 2026-10-01 ---
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
// Copyright (c) 2023-2026 Danosoft ApS
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
// 20260814 Sawaneh SST-717 Settle-all restored for accounts whose open remainder is offset
//                  by already-settled valutadiff rows (whole balance ~0 when all posts loaded).
// 20260817 Sawaneh Dated and debet/kredit views judge and display accounts by their open-post
//                  balance instead of the sum of all posts, so fully settled accounts (e.g. after
//                  currency-difference settlements) no longer show as owing; reminder amounts
//                  skip settled posts. Vis alle poster keeps the full sums.
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
// 20260828 Sawaneh SD-140: the SST-717/SST-730 open-balance rules (visY/visKontrol, reminder
//                  amounts skip settled posts) live in the shared helpers, so the filter/sort
//                  pre-pass judges accounts exactly like the rendered page.
// 20260915 CL/SZ SST-786: "Vis alle poster" only lifted the udlignet filter, pagination stayed in
//                force, so the customer could never see a totals-reconciling overview spanning more
//                than one page. Extracted vis_aabne_poster()'s account/post filter SQL into
//                openpost_account_query_parts() and added openpost_export_csv(), an "Eksporter CSV"
//                link next to the account search that streams every matching account across
//                batched queries, bypassing pagination while still bounding resource use.
// 20260915 CL/SZ SST-786 CodeRabbit fixes: initialized vis_aabne_poster()'s $currentdate (was
//                undefined, so an explicit dato_til of today could disagree with the CSV export on
//                which accounts/totals to show); added a konto_id tiebreaker to the export's batch
//                ordering (accountOrder alone isn't unique, so accounts could be skipped/repeated
//                across batches); quoted formula-leading kontonr/firmanavn values before writing
//                them to CSV (CWE-1236); documented vis_aabne_poster().
// 20260916 CL/SZ SST-786 review fixes (Lui): openpost_account_query_parts()'s HAVING predicates
//                used the kurs=100 placeholder rate as-is instead of the valuta-table-resolved rate
//                openpost_account_aging() uses (SST-672), so a foreign-currency account could be
//                dropped from both the report and the export's candidate set even though its real,
//                resolved balance would have passed openpost_account_visible() - any account with
//                such an ambiguous-rate row now always bypasses these predicates, deferring entirely
//                to the PHP-side resolution. openpost_export_csv() now also honours an active
//                aging-bucket filter (read straight from the request, like the display path), so a
//                bucket-filtered on-screen total matches the exported total - it did not before.
// 20260923 CL/NTR Mail kontoudtog/Opret rykker/Ryk alle only print when at least one account row
//                is on the page (formIndex > 0) - with none, posting back had no konto_id[] fields
//                and crashed count(null) in rapport.php.
// 20261001 CL/LAH "Vis alle" link next to the pagination: openpost_page_size=0 lists every matching
//                 account on one page (no LIMIT/OFFSET); "Vis 100 pr. side" switches back.
// 20261001 CDX/LAH Added not-yet-due balances, reconciled rounded totals and corrected aging colours and account counts.
//                  Preserved PBS toggle state and converted edited request lookups to ifset().
// 20261001 CL/LAH Grid layout: summary line, "Dage over forfald" group header, sticky column headers and a sticky action/paging bar.
//                 Select-all checkbox, firm name links to the customer card, PBS "(skjul)/(vis)" toggle and "Eksporter CSV" button.
//                 Ryk alle and Udlign alle ask for confirmation; 0,00 links keep the mode, page size and page.
//                 An empty result shows "Ingen", creditor rows keep their stripe across the checkbox column and the action bar spans the real column count.
// 20261001 CL/LAH Row totals: each post's DKK amount is rounded to 2 decimals before it joins its aging bucket, so the buckets, "I alt" and the kontokort figure agree; openpost_row_total() gives the screen and the CSV export the same total.
//                 The count/page SQL keeps every account with a 0, NULL or kurs=100 placeholder rate for the PHP rule, so it can never drop an account the report would list.
//                 Summary and account search are part of the sticky grid header; mode labels, PBS toggle titles and "Viser alle" use findtekst().
//                 The action form carries the account filter, mode, PBS and content flag, so a Mail kontoudtog/Opret rykker/Ryk alle post re-renders the same view.
//                 The kontonr link passes kilde_kto_fra/kilde_kto_til and the full report URL as returside.
// 20261001 CL/LAH Ryk alle's confirm uses a singular text for one ticked account.
// 20261001 CL/LAH Aging buckets take the control sum's per-post amount, so zero-rate and '-' currency posts also add up to the kontokort figure.
//                 vis_aabne_poster() can leave the footer to its caller, so openpost() prints it once after the rykker overview.

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
 * The six aging columns of the open posts report, keyed by the aging_bucket request value.
 *
 * @return array<string, array{
 *   key: string,    Index of the bucket in the array returned by openpost_account_aging().
 *   label: string,  Column header as rendered.
 * }>
 */
function openpost_aging_buckets() {
	global $sprog_id;
	return array(
		'over90' => array('key' => 'forfalden_plus90', 'label' => '>90'),
		'60-90'  => array('key' => 'forfalden_plus60', 'label' => '60-90'),
		'30-60'  => array('key' => 'forfalden_plus30', 'label' => '30-60'),
		'8-30'   => array('key' => 'forfalden_plus8',  'label' => '8-30'),
		'0-8'    => array('key' => 'forfalden',        'label' => '0-8'),
		'ikke'   => array('key' => 'ikke_forfalden',   'label' => findtekst('5500|Ikke forfalden', $sprog_id))
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
	$bucket = ifset($_REQUEST, 'aging_bucket', '');
	if (!is_string($bucket) || !array_key_exists($bucket, openpost_aging_buckets())) $bucket = '';
	$orderBy = (ifset($_REQUEST, 'order_by', '') === 'amount') ? 'amount' : '';
	$orderDir = ifset($_REQUEST, 'order_dir', '');
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
 * Splits the open posts of one account into the six rounded aging buckets of the report.
 *
 * Used by vis_aabne_poster(), the filtered/sorted pre-pass and the CSV export. Amounts
 * are converted with the row's valutakurs (re-derived from the
 * valuta table when a foreign-currency row carries the kurs=100 placeholder, SST-672), and a post
 * settled after $todate still counts as open when the report is run for a historical date.
 * Each post's converted amount is rounded to 2 decimals before it joins its bucket - the way the
 * kontokort rounds its rows - so the buckets add up to the per-post rounded openKontrol.
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
 *   ikke_forfalden: float,    Open amount due on or after the report date.
 *   bucketTotal: float,       Displayed open total, summed from the rounded buckets.
 *                             Equals openKontrol except for a post whose rate resolves to 0.
 *   y: float,                 Sum of all posts of the account.
 *   openY: float,             Sum of the open posts.
 *   kontrol: float,           Control sum of all posts, rounded per row.
 *   openKontrol: float,       Control sum of the open posts.
 *   rykkerbelob: float,       Sum of the open posts due before $todate.
 *   accountAligned: int,      1 when every post is settled at $todate.
 * }
 */
function openpost_account_aging($posts, $todate, $currentdate, $kontoart, &$agingDateCache) {
	global $baseCurrency;
	static $kursCache = array();
	static $gruppeCache = array();
	$aging = array(
		'forfalden' => 0, 'forfalden_plus8' => 0, 'forfalden_plus30' => 0, 'forfalden_plus60' => 0, 'forfalden_plus90' => 0,
		'ikke_forfalden' => 0, 'bucketTotal' => 0,
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
		// Buckets take the per-post-rounded DKK amount the control sum uses (incl. its zero-rate and
		// '-' currency handling), so a row's buckets always add up to the kontokort figure.
		$bucketAmount = $kontrolAmount;
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
		if (!$aligned && $forfaldsdag < $todate) $aging['rykkerbelob'] += $amount;
		if (!$aligned && $forfaldsdag < $todate && $forfaldsdag_plus8 > $todate) $aging['forfalden'] += $bucketAmount;
		if (!$aligned && $forfaldsdag_plus8 <= $todate && $forfaldsdag_plus30 > $todate) $aging['forfalden_plus8'] += $bucketAmount;
		if (!$aligned && $forfaldsdag_plus30 <= $todate && $forfaldsdag_plus60 > $todate) $aging['forfalden_plus30'] += $bucketAmount;
		if (!$aligned && $forfaldsdag_plus60 <= $todate && $forfaldsdag_plus90 > $todate) $aging['forfalden_plus60'] += $bucketAmount;
		if (!$aligned && $forfaldsdag_plus90 <= $todate) $aging['forfalden_plus90'] += $bucketAmount;
		if (!$aligned && $forfaldsdag >= $todate) $aging['ikke_forfalden'] += $bucketAmount;
		$aging['y'] += $amount;
		if (!$aligned) $aging['openY'] += $amount;
	}
	foreach (array('forfalden_plus90', 'forfalden_plus60', 'forfalden_plus30', 'forfalden_plus8', 'forfalden', 'ikke_forfalden') as $key) {
		$aging[$key] = afrund($aging[$key], 2);
		$aging['bucketTotal'] += $aging[$key];
	}
	$aging['bucketTotal'] = afrund($aging['bucketTotal'], 2);
	return $aging;
}
}

if (!function_exists('openpost_row_total')) {
/**
 * The "I alt" figure of one account row, shared by the screen and the CSV export.
 *
 * Open-post views show the sum of the rounded aging buckets. Vis alle poster keeps its historic
 * full sum: the per-post rounded control sum when it differs from the raw sum, else the raw sum.
 *
 * @param array $aging     Result of openpost_account_aging() after openpost_account_visible().
 * @param bool  $vis_alle  True when every post is shown (Vis alle poster).
 * @return float  Row total rounded to 2 decimals.
 */
function openpost_row_total($aging, $vis_alle) {
	if (!$vis_alle) return $aging['bucketTotal'];
	$total = (afrund($aging['visKontrol'],2) != afrund($aging['visY'],2)) ? $aging['visKontrol'] : $aging['visY'];
	return afrund($total, 2);
}
}

if (!function_exists('openpost_aging_cell')) {
/**
 * Renders an aging cell using its own balance and the debtor/creditor debt sign.
 *
 * @param float $amount    Rounded bucket amount.
 * @param string $kontoart 'D' for debtors, 'K' for creditors.
 * @param bool $overdue    Whether the bucket is overdue; not-yet-due debt stays neutral.
 * @param bool $showZero   Show 0.00 in totals; leave empty zero cells in account rows.
 * @param string $creditTitle Tooltip for credit balances (already translated), or '' for none.
 * @return string          HTML table cell; credit balances inherit the normal text colour.
 */
function openpost_aging_cell($amount, $kontoart, $overdue = true, $showZero = false, $creditTitle = '') {
	if (!$showZero && $amount == 0) return '<td align=right></td>';
	$debtAmount = ($kontoart == 'K') ? -$amount : $amount;
	$class = '';
	if ($debtAmount < 0) {
		$class = " class='op-credit'";
		if ($creditTitle !== '') $class.= " title='".htmlspecialchars($creditTitle, ENT_QUOTES)."'";
	}
	$style = ($overdue && $debtAmount > 0) ? " style='color: rgb(255, 0, 0);'" : '';
	return "<td align=right><span$class$style>".dkdecimal($amount, 2)."</span></td>";
}
}

if (!function_exists('openpost_account_visible')) {
/**
 * Applies the kun_debet/kun_kredit mode to an account's sums and tells whether the account is
 * listed at all. Same rule the rendering uses, shared with the pre-pass so the account count and
 * the pages only contain accounts that are actually listed.
 *
 * Vis alle poster keeps the historical full sums; every other view judges and shows the account
 * by what is actually open, so settled posts cannot drag a settled account back onto the list
 * (e.g. currency-difference groups that do not net to 0.00 in DKK).
 *
 * @param array  $aging        Result of openpost_account_aging(). visY/visKontrol are added: the
 *                             sums the view displays. All sums and accountAligned are zeroed when
 *                             the account falls outside the debet/kredit mode.
 * @param string $todate       Report date, Y-m-d.
 * @param string $currentdate  Today, Y-m-d.
 * @param string $kun_debet    'on' to list only accounts in debit.
 * @param string $kun_kredit   'on' to list only accounts in credit.
 * @param bool   $vis_alle     True when every post is shown (Vis alle poster).
 * @return bool
 */
function openpost_account_visible(&$aging, $todate, $currentdate, $kun_debet, $kun_kredit, $vis_alle = false) {
	if ($vis_alle) {
		$aging['visY'] = $aging['y'];
		$aging['visKontrol'] = $aging['kontrol'];
	} else {
		$aging['visY'] = $aging['openY'];
		$aging['visKontrol'] = $aging['openKontrol'];
	}
	// Sums of 2-3 decimal amounts times 3-decimal rates have at most 8 decimals; rounding there drops
	// the float residue, so these comparisons agree with the exact SQL in openpost_account_query_parts().
	$exactVisY = round($aging['visY'], 8);
	if (($kun_debet && $exactVisY <= 0) || ($kun_kredit && $exactVisY >= 0)) {
		$aging['accountAligned'] = 1;
		$aging['y'] = $aging['kontrol'] = $aging['openY'] = $aging['openKontrol'] = $aging['visY'] = $aging['visKontrol'] = 0;
	}
	$aging['kontrol'] = afrund($aging['kontrol'],2);
	$aging['visKontrol'] = afrund($aging['visKontrol'],2);
	if ($vis_alle) {
		return (abs(round($aging['y'], 8)) >= 0.01 || ($todate == $currentdate && ($aging['accountAligned'] == "0" || $aging['kontrol'])));
	}
	if ($todate == $currentdate) {
		return ($aging['accountAligned'] == "0" || abs($aging['visKontrol']) >= 0.01);
	}
	return (abs($aging['visKontrol']) >= 0.01);
}
}

if (!function_exists('openpost_account_query_parts')) {
/**
 * Builds the SQL fragments that enumerate accounts and posts of the open posts report, shared by
 * the paginated on-screen report (vis_aabne_poster()) and the "export all" CSV
 * (openpost_export_csv()), so both operate over exactly the same account/post superset and a
 * filter never diverges between what a page shows and what the export reconciles to.
 *
 * @param string|null $konto_fra    Start of the account range, or a firm-name search pattern.
 * @param string|null $konto_til    End of the account range.
 * @param string      $kontoart     'D' for debtors, 'K' for creditors.
 * @param bool        $showPBS      False excludes accounts carrying a pbs_nr.
 * @param string      $todate       Report date, Y-m-d.
 * @param string      $currentdate  Today, Y-m-d.
 * @param bool        $vis_alle     True when settled posts are included too (Vis alle poster).
 * @param string      $kun_debet    'on' to keep only accounts in debit.
 * @param string      $kun_kredit   'on' to keep only accounts in credit.
 * @param string      $db_type      Active DB backend, from includes/connect.php.
 * @return array{
 *   accountWhere: string,   Predicate on adresser, safe to interpolate into a query.
 *   accountOrder: string,   Expression accounts are ordered by.
 *   postWhere: string,      Predicate on openpost rows, safe to interpolate into a query.
 *   accountGroup: string,   Subquery selecting the konto_id superset that passes accountHaving.
 *   accountSource: string,  accountGroup wrapped and aliased, ready to join against adresser.
 * }
 */
function openpost_account_query_parts($konto_fra, $konto_til, $kontoart, $showPBS, $todate, $currentdate, $vis_alle, $kun_debet, $kun_kredit, $db_type) {
	global $baseCurrency;
	$accountFilter = openpost_account_filter($konto_fra,$konto_til,$kontoart);
	$accountWhere = $accountFilter['where'];
	if (!$showPBS) $accountWhere.= " and (adresser.pbs_nr is NULL or adresser.pbs_nr = '' or adresser.pbs_nr = '0')";
	if ($vis_alle || $todate != $currentdate) {
		$postWhere = "1=1";
	} elseif ($db_type == 'postgresql') {
		$postWhere = "openpost.udlignet IS DISTINCT FROM '1'";
	} else {
		$postWhere = "(openpost.udlignet is NULL or openpost.udlignet != '1')";
	}
	$todateEsc = db_escape_string($todate);
	if ($todate != $currentdate) $postWhere = "openpost.transdate<='$todateEsc' and $postWhere";
	$baseCurrencyEsc = db_escape_string($baseCurrency);
	// The HAVING predicates below mirror openpost_account_aging()/openpost_account_visible() post by
	// post, so the SQL selects the accounts the report lists and the "Viser x-y af N" count matches
	// the rows. The one place SQL cannot follow PHP is the rate: PHP turns a stored rate that is
	// falsy to PHP (NULL, numeric 0, '0') into 100, keeps a '0.000' string as 0, and resolves a
	// foreign-currency kurs=100 placeholder from the valuta table (SST-672), where a resolved
	// '0.000' becomes 0 again. An account with any post whose stored rate is NULL, 0 or such a
	// placeholder is therefore always kept and left to openpost_account_visible(), so the SQL can
	// over-count but never drop an account the report would show.
	$isForeign = "coalesce(openpost.valuta,'') not in ('','$baseCurrencyEsc')";
	$ambiguousRate = "(openpost.valutakurs is null or openpost.valutakurs=0 or ($isForeign and openpost.valutakurs=100))";
	// Mirror afrund(), including its signed correction before rounding. PostgreSQL's
	// two-argument round requires numeric; mysqli uses its equivalent decimal cast.
	$sqlRound = function($expression, $decimals) use ($db_type) {
		$decimals = intval($decimals);
		$correction = ($decimals == 3) ? '0.00001' : '0.0001';
		$numericType = ($db_type == 'postgresql') ? 'numeric' : 'decimal(65,20)';
		return "round(cast(($expression) as $numericType) + (case when ($expression)>0 then $correction when ($expression)<0 then -$correction else 0 end), $decimals)";
	};
	$openAtDate = function($alias) use ($todate, $currentdate, $todateEsc) {
		$open = "($alias.udlignet is null or $alias.udlignet in ('','0'))";
		if ($todate != $currentdate) {
			$open = "($open or ($alias.udlignet='1' and ($alias.udlign_date is null or $alias.udlign_date>'$todateEsc')))";
		}
		return $open;
	};
	if (!$vis_alle && $todate == $currentdate && !$kun_debet && !$kun_kredit) {
		// Default view: only open posts are loaded and an account is listed when it has one, so no
		// amount/currency aggregate is needed on this paginated path.
		$accountGroup = "select openpost.konto_id from openpost where $postWhere group by openpost.konto_id";
		$accountGroup.= " having sum(case when ".$openAtDate('openpost')." then 1 else 0 end)>0";
	} else {
		// Per-post values are computed in derived tables ("offset 0" keeps PostgreSQL from inlining
		// them), so the rounding runs once per post rather than once per aggregate. A post with an
		// ambiguous rate gets a neutral 100 here; its account bypasses the predicates below anyway.
		$noInline = ($db_type == 'postgresql') ? " offset 0" : "";
		$posts = "select openpost.konto_id, openpost.amount, openpost.valuta, openpost.udlignet, openpost.udlign_date, ";
		$posts.= "(case when $ambiguousRate then 100 else openpost.valutakurs end) as kurs, ";
		$posts.= "(case when $ambiguousRate then 1 else 0 end) as ambiguous_rate ";
		$posts.= "from openpost where $postWhere$noInline";
		$nativeAmount = "(case when coalesce(pk.valuta,'') in ('','$baseCurrencyEsc') then ".$sqlRound('pk.amount', 2)." else ".$sqlRound('pk.amount', 3)." end)";
		$controlAmount = "(case when coalesce(pk.valuta,'')='-' then pk.amount else pk.amount*pk.kurs/100 end)";
		$values = "select pk.konto_id, pk.udlignet, pk.udlign_date, pk.ambiguous_rate, $nativeAmount*pk.kurs/100 as amt, $controlAmount as ctl from ($posts) pk$noInline";
		$controlRounded = $sqlRound('op.ctl', 2);
		$selectedAmount = ($vis_alle) ? "op.amt" : "(case when ".$openAtDate('op')." then op.amt else 0 end)";
		$selectedControl = ($vis_alle) ? $controlRounded : "(case when ".$openAtDate('op')." then $controlRounded else 0 end)";
		$controlSum = $sqlRound("sum($selectedControl)", 2);
		if ($vis_alle) {
			// Current date: the full net, an open post or a nonzero per-post-rounded control sum.
			$having = "abs(sum($selectedAmount))>=0.01";
			if ($todate == $currentdate) $having = "($having or sum(case when ".$openAtDate('op')." then 1 else 0 end)>0 or $controlSum<>0)";
		} elseif ($todate == $currentdate) {
			$having = "sum(case when ".$openAtDate('op')." then 1 else 0 end)>0";
		} else {
			// Historical open view: the balance still open at the report date, not settled posts.
			$having = "abs($controlSum)>=0.01";
		}
		$accountHaving = array("($having)");
		if ($kun_debet) $accountHaving[] = "sum($selectedAmount)>0";
		if ($kun_kredit) $accountHaving[] = "sum($selectedAmount)<0";
		// Only a post that counts in the view can make the PHP rule differ: settled posts add nothing
		// to the open views.
		$selectedAmbiguous = ($vis_alle) ? "op.ambiguous_rate" : "(case when ".$openAtDate('op')." then op.ambiguous_rate else 0 end)";
		$accountGroup = "select op.konto_id from ($values) op group by op.konto_id ";
		$accountGroup.= "having (max($selectedAmbiguous)=1 or (".implode(" and ", $accountHaving)."))";
	}
	return array(
		'accountWhere' => $accountWhere,
		'accountOrder' => $accountFilter['order'],
		'postWhere' => $postWhere,
		'accountGroup' => $accountGroup,
		'accountSource' => "($accountGroup) account_posts"
	);
}
}

if (!function_exists('openpost_csv_safe_field')) {
/**
 * Prefixes a value with a single quote when it begins with a character a spreadsheet would treat as
 * the start of a formula (=, +, -, @, tab or carriage return), so a crafted account number or firm
 * name is shown as literal text instead of evaluated when the export is opened in Excel/Sheets
 * (CWE-1236, CSV injection).
 *
 * @param string $value  Raw field value.
 * @return string  The value, quote-prefixed when formula-leading.
 */
function openpost_csv_safe_field($value) {
	$value = (string)$value;
	if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) return "'".$value;
	return $value;
}
}

if (!function_exists('openpost_export_csv')) {
/**
 * Streams every account matching the open posts report's current filters as CSV, bypassing the
 * on-screen report's pagination (SST-786: "Vis alle poster" only lifted the udlignet filter, the
 * page size/LIMIT/OFFSET stayed in force, so the customer could never see a totals-reconciling
 * overview spanning more than one page).
 *
 * Accounts are still fetched in bounded batches (not one unbounded query) so a large tenant's
 * export doesn't hold the whole account/post set in memory at once - the same bounded-resource-use
 * concern the pagination in vis_aabne_poster() was originally added for. Each batch reuses
 * openpost_account_query_parts()/openpost_account_aging()/openpost_account_visible(), the exact
 * same filtering the paginated report applies, so the export never diverges from what the report
 * would show across all of its pages, and the trailing total row reconciles to the report's own
 * "I alt (viste)" footer. Also honours an active aging-bucket filter (read from the request via
 * openpost_report_state(), same as the display path), so a filtered on-screen total still matches
 * the export; row order is not re-sorted by amount even when the report is (see inline comment).
 *
 * Sends CSV headers and writes directly to php://output, then exits. Must be called before any
 * other output has been sent.
 *
 * @param string|null $dato_fra    Report period start, or null.
 * @param string|null $dato_til    Report date (to-date), or null for today.
 * @param string|null $konto_fra   Start of the account range, or a firm-name search pattern.
 * @param string|null $konto_til   End of the account range.
 * @param string      $kontoart    'D' for debtors, 'K' for creditors.
 * @param string      $kun_debet   'on' to keep only accounts in debit.
 * @param string      $kun_kredit  'on' to keep only accounts in credit.
 * @param bool        $vis_alle    True to include settled posts too (Vis alle poster).
 * @param bool        $showPBS     False excludes accounts carrying a pbs_nr.
 * @return void
 */
function openpost_export_csv($dato_fra, $dato_til, $konto_fra, $konto_til, $kontoart, $kun_debet, $kun_kredit, $vis_alle, $showPBS) {
	global $db_type, $sprog_id;
	$currentdate = date('Y-m-d');
	if ($dato_fra && $dato_til) $todate = usdate($dato_til);
	elseif ($dato_fra && !$dato_til) $todate = usdate($dato_fra);
	else $todate = $currentdate;

	// SST-786 (Lui's review): honour the report's active aging-bucket filter so the export's account
	// set and total agree with what's on screen - it's what the customer reconciles against. Reading
	// the state straight from the request (same as the display path) means the CSV link only has to
	// carry &aging_bucket=... and every caller (debitor and kreditor's rapport.php) gets this for
	// free. order_by/order_dir are deliberately NOT applied here: they only reorder rows within a
	// page, they never change which accounts or totals show, and re-sorting the export would mean
	// buffering every matching account in memory first - exactly what the batched export exists to
	// avoid on a large tenant.
	$state = openpost_report_state();
	$agingBucket = $state['aging_bucket'];
	$buckets = openpost_aging_buckets();
	$bucketKey = $agingBucket ? $buckets[$agingBucket]['key'] : null;

	$parts = openpost_account_query_parts($konto_fra, $konto_til, $kontoart, $showPBS, $todate, $currentdate, $vis_alle, $kun_debet, $kun_kredit, $db_type);
	$accountWhere = $parts['accountWhere'];
	$accountOrder = $parts['accountOrder'];
	$postWhere = $parts['postWhere'];
	$accountGroup = $parts['accountGroup'];

	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="aabne_poster_'.date('Y-m-d').'.csv"');
	$fp = fopen('php://output', 'w');
	$csvHeader = array_merge(array('Kontonr.', findtekst(360,$sprog_id)), array_column($buckets, 'label'), array(findtekst('5019|I alt', $sprog_id)));
	fputcsv($fp, $csvHeader, ';');

	$batchSize = 200;
	$batchOffset = 0;
	$agingDateCache = array();
	$bucketTotals = array_fill_keys(array_column($buckets, 'key'), 0);
	$sum = $kontrolsum = 0;

	do {
		$idQtxt = "select account_posts.konto_id, adresser.kontonr as account_kontonr, adresser.firmanavn as account_firmanavn ";
		$idQtxt.= "from ($accountGroup) account_posts ";
		if ($db_type == 'postgresql') $idQtxt.= "cross join lateral (select id, kontonr, firmanavn from adresser where id=account_posts.konto_id and $accountWhere offset 0) adresser ";
		else $idQtxt.= ", adresser where account_posts.konto_id=adresser.id and $accountWhere ";
		// $accountOrder (firmanavn, or nr_cast(kontonr) for a numeric range) is not unique, so a
		// bare LIMIT/OFFSET over it can skip or repeat accounts across batches when several share
		// the same order value - konto_id as a tiebreaker keeps the paging stable.
		$idQtxt.= "order by $accountOrder, account_posts.konto_id limit $batchSize offset $batchOffset";
		$batchIds = array();
		$accountInfo = array();
		$q = db_select($idQtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$batchIds[] = (int)$r['konto_id'];
			$accountInfo[$r['konto_id']] = $r;
		}
		if (!$batchIds) break;

		$postQtxt = "select openpost.* from openpost where openpost.konto_id in (".implode(',', $batchIds).") and $postWhere ";
		$postQtxt.= "order by openpost.konto_id, openpost.faktnr, openpost.amount";
		$accountRows = array();
		$qPosts = db_select($postQtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($qPosts)) $accountRows[$r['konto_id']][] = $r;

		foreach ($batchIds as $accountId) {
			$posts = isset($accountRows[$accountId]) ? $accountRows[$accountId] : array();
			$aging = openpost_account_aging($posts, $todate, $currentdate, $kontoart, $agingDateCache);
			if (!openpost_account_visible($aging, $todate, $currentdate, $kun_debet, $kun_kredit, $vis_alle)) continue;
			if ($agingBucket && abs(afrund($aging[$bucketKey],2)) < 0.01) continue;
			$info = $accountInfo[$accountId];
			$csvRow = array(
				openpost_csv_safe_field(trim($info['account_kontonr'])),
				openpost_csv_safe_field(stripslashes($info['account_firmanavn']))
			);
			foreach ($buckets as $bucket) {
				$csvRow[] = number_format($aging[$bucket['key']], 2, ',', '');
				$bucketTotals[$bucket['key']] += $aging[$bucket['key']];
			}
			$csvRow[] = number_format(openpost_row_total($aging, $vis_alle), 2, ',', '');
			fputcsv($fp, $csvRow, ';');
			$sum+= $aging['visY'];
			$kontrolsum+= $aging['visKontrol'];
		}
		$batchOffset+= $batchSize;
	} while (count($batchIds) == $batchSize);

	$csvTotal = array('', findtekst('5019|I alt', $sprog_id));
	foreach ($bucketTotals as $key => $amount) {
		$bucketTotals[$key] = afrund($amount, 2);
		$csvTotal[] = number_format($bucketTotals[$key], 2, ',', '');
	}
	$total = ($vis_alle) ? (($sum <= $kontrolsum) ? $kontrolsum : $sum) : array_sum($bucketTotals);
	$csvTotal[] = number_format(afrund($total, 2), 2, ',', '');
	fputcsv($fp, $csvTotal, ';');
	fclose($fp);
	exit;
}
}

if (!function_exists('vis_aabne_poster')) {
/**
 * Renders one page of the open posts report (Debitor/Kreditor -> Rapporter -> Åbne poster): the
 * account/PBS toggle header, the paginated account grid with aging-bucket columns, and the
 * "Mail kontoudtog"/"Opret rykker"/"Udlign alle" actions for the accounts on the current page.
 *
 * Account/post filtering is built by openpost_account_query_parts() (shared with
 * openpost_export_csv(), SST-786's CSV export of every page's accounts in one file) and rendered
 * per account via openpost_account_aging()/openpost_account_visible().
 *
 * @param string|null $dato_fra    Report period start, or null.
 * @param string|null $dato_til    Report date (to-date), or null for today.
 * @param string|null $konto_fra   Start of the account range, or a firm-name search pattern.
 * @param string|null $konto_til   End of the account range.
 * @param string      $rapportart  Report type, echoed into links back to rapport.php.
 * @param string      $kontoart    'D' for debtors, 'K' for creditors.
 * @param string      $kun_debet   'on' to keep only accounts in debit.
 * @param string      $kun_kredit  'on' to keep only accounts in credit.
 * @param bool        $vis_alle    True to include settled posts too (Vis alle poster).
 * @param bool        $rykkerAnchor True when the caller prints the rykker overview (#opRykkere) below
 *                                  the grid, so the summary line links to it.
 * @return void  Prints HTML directly.
 */
function vis_aabne_poster($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart,$kun_debet,$kun_kredit,$vis_alle=false,$rykkerAnchor=false,$printFooter=true) {
	global $baseCurrency,$bgcolor,$bgcolor5,$bruger_id,$buttonColor;
	global $db;
	global $db_type;
	global $menu;
	global $sprog_id;

	// GET for links, POST for the aabenpost form below, so an action re-renders the same view.
	$showPBS=(int)ifset($_GET, 'showPBS', ifset($_POST, 'showPBS', 1));
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
	$fromdate=$linjebg=$popup=$todate=NULL;
	// SST-786: $currentdate must be real, not NULL - openpost_export_csv() sets it to today's date
	// too, and the two must agree whenever $dato_til is today, or openpost_account_query_parts()
	// takes different branches (historical vs. current-date) for the report and its CSV export.
	$currentdate=date('Y-m-d');


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
	$openpostPage=(int)ifset($_REQUEST, 'openpost_page', 1);
	$openpostDefaultPageSize=100;
	$openpostPageSizeParam=(string)ifset($_REQUEST, 'openpost_page_size', $openpostDefaultPageSize);
	// openpost_page_size=0 is "Vis alle": every matching account on a single page, no LIMIT/OFFSET.
	$openpostShowAll=($openpostPageSizeParam === '0');
	$openpostPageSize=(int)$openpostPageSizeParam;
	if ($openpostPage < 1 || $openpostShowAll) $openpostPage=1;
	if ($openpostShowAll) $openpostPageSize=0;
	elseif ($openpostPageSize < 25) $openpostPageSize=25;
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
	$reportUrl="rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl&konto_fra=$konto_fraUrl&konto_til=$konto_tilUrl$openpostContentParam";
	if ($vis_alle) $modeParam="vis_alle_poster";
	elseif ($kun_debet) $modeParam="kun_debet";
	elseif ($kun_kredit) $modeParam="kun_kredit";
	else $modeParam="vis_aabenpost";
	$reportUrl.="&$modeParam=on";
	if (!$showPBS) $reportUrl.="&showPBS=0";
	$showAllUrl=$reportUrl."&openpost_page_size=0".$stateUrl;
	$pagedUrl=$reportUrl."&openpost_page_size=$openpostDefaultPageSize".$stateUrl;
	$reportUrl.="&openpost_page_size=$openpostPageSize";
	$basePageUrl=$reportUrl.$stateUrl;
	$pbsToggleUrl=htmlspecialchars(str_replace('&showPBS=0', '', $reportUrl).'&showPBS='.(($showPBS) ? '0' : '1').$stateUrl, ENT_QUOTES);
	// SST-786: exports every account matching the report's current filters (dato/konto range/mode/
	// showPBS), without pagination - an active aging-bucket filter still applies (openpost_export_csv()
	// re-reads it from the request), so the exported total matches what's on screen; the amount sort
	// is display-only and is not carried over (see openpost_export_csv()'s own comment on why).
	$csvUrl=$reportUrl."&openpost_csv=1";
	if ($agingBucket) $csvUrl.= "&aging_bucket=".rawurlencode($agingBucket);
	$csvLabel=htmlspecialchars(findtekst('5531|Eksporter CSV',$sprog_id),ENT_QUOTES);
	$csvTitle=htmlspecialchars(findtekst('5152|Eksporter alle konti under de valgte filtre til CSV, uden sideopdeling',$sprog_id),ENT_QUOTES);

	$filterTitle=htmlspecialchars(findtekst('5121|Vis kun konti med beløb i denne kolonne',$sprog_id),ENT_QUOTES);
	$clearTitle=htmlspecialchars(findtekst('5120|Ryd filter',$sprog_id),ENT_QUOTES);
	$sortTitle=htmlspecialchars(findtekst('5122|Sortér efter beløb',$sprog_id),ENT_QUOTES);
	$sortDescUrl=$reportUrl.openpost_state_url($state,array('order_by'=>($orderBy && $orderDir=='desc') ? '' : 'amount','order_dir'=>'desc'));
	$sortAscUrl=$reportUrl.openpost_state_url($state,array('order_by'=>($orderBy && $orderDir=='asc') ? '' : 'amount','order_dir'=>'asc'));
	$sortLinks=" <a class='op-filter' href=\"$sortDescUrl\" title='$sortTitle'>".(($orderBy && $orderDir=='desc') ? '<b>&#9660;</b>' : '&#9660;')."</a>";
	$sortLinks.="<a class='op-filter' href=\"$sortAscUrl\" title='$sortTitle'>".(($orderBy && $orderDir=='asc') ? '<b>&#9650;</b>' : '&#9650;')."</a>";
	$clearUrl=$reportUrl.openpost_state_url($state,array('aging_bucket'=>''));
	$headerCell=array();
	foreach ($buckets as $bucketId => $bucket) {
		$label=htmlspecialchars($bucket['label'],ENT_QUOTES);
		if ($agingBucket == $bucketId) {
			$headerCell[$bucketId]="<b>$label</b> <a class='op-filter' href=\"$clearUrl\" title='$clearTitle'>&#10006;</a>$sortLinks";
		} else {
			$headerCell[$bucketId]="<a class='op-filter' href=\"".$reportUrl.openpost_state_url($state,array('aging_bucket'=>$bucketId))."\" title='$filterTitle'>$label</a>";
		}
	}
	$headerCell['total']=findtekst('5019|I alt',$sprog_id).(($agingBucket) ? "" : $sortLinks);

	// One line that states what the grid shows: report date, account selection, mode, filter and sort.
	if ($vis_alle) $modeLabel=findtekst('5555|Alle poster',$sprog_id);
	elseif ($kun_debet) $modeLabel=findtekst('925|Kun konti i debet',$sprog_id);
	elseif ($kun_kredit) $modeLabel=findtekst('926|Kun konti i kredit',$sprog_id);
	else $modeLabel=findtekst('441|Åbne poster',$sprog_id);
	$modeLabel=htmlspecialchars($modeLabel,ENT_QUOTES);
	if (is_numeric($konto_fra) && is_numeric($konto_til)) {
		$accountsLabel=($konto_fra == $konto_til) ? $konto_fraHtml : "$konto_fraHtml&ndash;$konto_tilHtml";
	} elseif (!empty($konto_fra) && $konto_fra != '*') {
		$accountsLabel=htmlspecialchars(findtekst('5526|søgning',$sprog_id),ENT_QUOTES)." &lsquo;$konto_fraHtml&rsquo;";
	} else {
		$accountsLabel=htmlspecialchars(mb_strtolower(findtekst('2498|Alle',$sprog_id),'UTF-8'),ENT_QUOTES);
	}
	$summary=array();
	$summary[]=htmlspecialchars(findtekst('5525|Opgjort pr.',$sprog_id),ENT_QUOTES)." <b>".dkdato($todate)."</b>";
	$summary[]=htmlspecialchars(findtekst('117|Konti',$sprog_id),ENT_QUOTES).": <b>$accountsLabel</b>";
	$summary[]=htmlspecialchars(findtekst('813|Visning',$sprog_id),ENT_QUOTES).": <b>$modeLabel</b>";
	if ($agingBucket) {
		$summary[]=htmlspecialchars(findtekst('5124|Filter',$sprog_id),ENT_QUOTES).": <b>".htmlspecialchars($buckets[$agingBucket]['label'],ENT_QUOTES)."</b> <a class='op-link' href=\"$clearUrl\">".$clearTitle."</a>";
	}
	if ($orderBy) {
		$summary[]=htmlspecialchars(findtekst('5527|Sorteret efter beløb',$sprog_id),ENT_QUOTES)." ".(($orderDir == 'asc') ? '&uarr;' : '&darr;');
	}
	$summaryHtml="<div class='op-summary'><span>".implode(" &nbsp;&middot;&nbsp; ", $summary)."</span>";
	if ($rykkerAnchor) {
		$summaryHtml.="<a class='op-link' href='#opRykkere' onclick=\"var e=document.getElementById('opRykkere'); if (e) {e.scrollIntoView({behavior: 'smooth'}); return false;}\">";
		$summaryHtml.=htmlspecialchars(findtekst('5528|Gå til rykkere',$sprog_id),ENT_QUOTES)." &darr;</a>";
	}
	$summaryHtml.="</div>";

	// The account filter as one kontonr value ("fra:til", a number or a name pattern), the form the
	// in-report search and openpost_kontonr_range() use.
	$kontonrFilter=(string)$konto_fra;
	if ($konto_til !== NULL && $konto_til !== '' && $konto_til != $konto_fra && is_numeric($konto_fra) && is_numeric($konto_til)) $kontonrFilter.=":$konto_til";
	$kontonrFilterUrl=rawurlencode($kontonrFilter);
	$searchValue=htmlspecialchars($kontonrFilter,ENT_QUOTES);
	$searchRow="<form method='get' action='rapport.php' style='display:inline;'>";
	$searchRow.="<input type='hidden' name='rapportart' value='openpost'><input type='hidden' name='submit' value='ok'>";
	$searchRow.="<input type='hidden' name='dato_fra' value=\"$dato_fraHtml\"><input type='hidden' name='dato_til' value=\"$dato_tilHtml\">";
	if ($openpostContentParam) $searchRow.="<input type='hidden' name='openpost_content' value='1'>";
	$searchRow.="<input type='hidden' name='openpost_page_size' value='$openpostPageSize'><input type='hidden' name='$modeParam' value='on'>";
	if (!$showPBS) $searchRow.="<input type='hidden' name='showPBS' value='0'>";
	if ($agingBucket) $searchRow.="<input type='hidden' name='aging_bucket' value='$agingBucket'>";
	if ($orderBy) $searchRow.="<input type='hidden' name='order_by' value='$orderBy'><input type='hidden' name='order_dir' value='$orderDir'>";
	$searchRow.="<input class='inputbox' type='text' name='kontonr' value=\"$searchValue\" style='width:260px;'";
	$searchRow.=" placeholder=\"".htmlspecialchars(findtekst('5530|Kontonr., fra:til eller navn (* = joker)',$sprog_id),ENT_QUOTES)."\"";
	$searchRow.=" title=\"".htmlspecialchars(findtekst('5123|Kontonr., interval (fra:til) eller firmanavn (* = jokertegn)',$sprog_id),ENT_QUOTES)."\"> ";
	$searchRow.="<input type='submit' value=\"".htmlspecialchars(findtekst(913,$sprog_id),ENT_QUOTES)."\"></form>";
	$searchRow.=" &nbsp; <input type='button' value=\"$csvLabel\" title=\"$csvTitle\" onclick=\"location.href='".htmlspecialchars($csvUrl,ENT_QUOTES)."';\">";
	$headerColspan = $usePBS ? 11 : 10;

	// Header checkbox that ticks every account of the page for Mail kontoudtog/Opret rykker.
	$selectAllCell="";
	if ($kontoart=='D') {
		$selectAllTitle=htmlspecialchars(findtekst('5529|Vælg konti til kontoudtog/rykker',$sprog_id),ENT_QUOTES);
		$selectAllCell="<label class='checkContainerOrdreliste' title=\"$selectAllTitle\"><input type=checkbox onclick='opToggleAll(this);'><span class='checkmarkOrdreliste'></span></label>";
	}
	$leadColspan=($usePBS) ? 3 : 2;
	$daysOverdue=htmlspecialchars(findtekst('5523|Dage over forfald',$sprog_id),ENT_QUOTES);
	$groupRow="<tr class='op-group-row'><th colspan='$leadColspan'></th><th colspan='5' class='op-group'>$daysOverdue</th><th colspan='3'></th></tr>";
	if ($usePBS) {
		$pbsToggleLabel=htmlspecialchars(mb_strtolower(findtekst(($showPBS) ? '1132|Skjul' : '1133|Vis',$sprog_id),'UTF-8'),ENT_QUOTES);
		$pbsToggleTitle=htmlspecialchars(findtekst(($showPBS) ? '5556|Skjul PBS-kunder' : '5557|Vis PBS-kunder',$sprog_id),ENT_QUOTES);
		$pbsHeader="PBS <a class='op-filter' href='$pbsToggleUrl' title='$pbsToggleTitle'>($pbsToggleLabel)</a>";
	}
	$labelCells="<th align=left>Kontonr.</th>";
	if ($usePBS) $labelCells.="<th align=left>$pbsHeader</th>";
	$labelCells.="<th align=left>".findtekst(360,$sprog_id)."</th>";
	foreach (array_keys($buckets) as $bucketId) {
		$labelCells.="<th align=right>$headerCell[$bucketId]</th>";
	}
	$labelCells.="<th align=right>$headerCell[total]</th><th align=center>$selectAllCell</th>";

	$linkColor=($buttonColor) ? $buttonColor : '#114691';
	print "<style>
#visAabnePosterTable th,#visAabnePosterTable td,#visAabnePosterTableT th,#visAabnePosterTableT td{padding:2px 6px;}
#visAabnePosterTable td[align=right],#visAabnePosterTable th{white-space:nowrap;}
#visAabnePosterTable > thead{position:sticky;top:0;z-index:3;}
#visAabnePosterTable > thead th{background-color:$bgcolor;font-weight:normal;}
.op-head-row th{text-align:left;padding:0;}
#visAabnePosterTable > thead > tr:first-child > th{box-shadow:0 -8px 0 0 $bgcolor;}
.op-group-row th{font-size:90%;color:#555;padding-bottom:0;}
.op-group-row th.op-group{text-align:center;border-bottom:1px solid #aaa;}
.op-label-row th{border-bottom:1px solid #888;}
.op-summary{display:flex;justify-content:space-between;align-items:baseline;gap:16px;padding:0 6px 6px 6px;}
.op-search{padding:0 6px 6px 6px;}
a.op-filter,a.op-filter:link,a.op-filter:visited,a.op-link,a.op-link:link,a.op-link:visited{color:$linkColor;}
a.op-filter:hover,a.op-link:hover,a.op-name:hover{text-decoration:underline;}
a.op-name,a.op-name:link,a.op-name:visited{color:inherit;}
.op-credit{color:inherit;}
.op-total-row td{border-top:1px solid #888;}
.op-actionbar > td{position:sticky;bottom:0;z-index:3;background-color:$bgcolor;border-top:1px solid #aaa;padding:6px;box-shadow:0 8px 0 0 $bgcolor;}
.op-actionbar-inner{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
</style>\n";
	print "<script>
function opToggleAll(box) {
	document.querySelectorAll('input[name^=\"kontoudtog[\"]').forEach(function (c) { c.checked = box.checked; });
}
</script>\n";

	if ($menu=='T') {
		print "<tr><td><div class='dataTablediv'><table id='visAabnePosterTableT' width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class='dataTable'><thead>\n";
		print "<tr><th colspan='$headerColspan' style='font-weight:normal;'>$summaryHtml</th></tr>";
		print "<tr><th colspan='$headerColspan' style='font-weight:normal;'>$searchRow</th></tr>";
		print $groupRow;
		print "<tr class='op-label-row'>$labelCells</tr>";
		print "</thead><tbody>";
	} else {
		// Summary and search are rows of the sticky thead, so the whole header block (summary, search,
		// group and column labels) stays visible as one unit while #opGridWrapper scrolls.
		print "<table id='visAabnePosterTable' width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><thead>\n";
		print "<tr class='op-head-row'><th colspan='$headerColspan'>$summaryHtml</th></tr>";
		print "<tr class='op-head-row'><th colspan='$headerColspan'><div class='op-search'>$searchRow</div></th></tr>";
		print $groupRow;
		print "<tr class='op-label-row'>$labelCells</tr>";
		print "</thead><tbody>\n";
	}

	// Push the grid header out before the heavy count/page queries below, so the user sees
	// the empty table immediately while the SQL runs (ob_flush drains php.ini's output_buffering).
	if (ob_get_level() > 0) @ob_flush();
	flush();


	// A POST to rapport.php takes the account filter only from GET kontonr (without it openpost()
	// swaps in the stored DRV preferences) and openpost() takes the mode only from GET, so both ride on
	// the action URL; showPBS and the content flag are posted below.
	$formAction=htmlspecialchars("rapport.php?kontonr=$kontonrFilterUrl&$modeParam=on",ENT_QUOTES);
	print "<form name=aabenpost action=\"$formAction\" method=post>";

	$accountPosts=$accountIndex=array();
	if ($kontoart=='D') $tmp="";
	else $tmp="desc";
	// Same account/post filtering openpost_export_csv() applies for the "export all" CSV, so a
	// page of this report and the export always agree on which accounts qualify (SST-786).
	$parts = openpost_account_query_parts($konto_fra, $konto_til, $kontoart, $showPBS, $todate, $currentdate, $vis_alle, $kun_debet, $kun_kredit, $db_type);
	$accountWhere = $parts['accountWhere'];
	$accountOrder = $parts['accountOrder'];
	$postWhere = $parts['postWhere'];
	$accountGroup = $parts['accountGroup'];
	$accountSource = $parts['accountSource'];
	$totalKontoantal=0;
	$agingDateCache=array();
	$pageAccountIds=NULL;
	if ($agingBucket || $orderBy) {
		// Filter/sort pre-pass: aggregate every matching account with the same bucket maths as the
		// rendering, then page the resulting account ids - filtering the rows of one page would
		// return wrong pages and counts. The account superset is the same $accountGroup the count
		// below uses, so a filtered page never shows an account the unfiltered report leaves out.
		$bucketKey=($agingBucket) ? $buckets[$agingBucket]['key'] : 'visY';
		$sortedAccounts=array();
		$addAccount=function($accountId, $posts) use (&$sortedAccounts, &$agingDateCache, $bucketKey, $agingBucket, $todate, $currentdate, $kontoart, $kun_debet, $kun_kredit, $vis_alle) {
			$aging=openpost_account_aging($posts, $todate, $currentdate, $kontoart, $agingDateCache);
			if (!openpost_account_visible($aging, $todate, $currentdate, $kun_debet, $kun_kredit, $vis_alle)) return;
			$amount=afrund(($bucketKey == 'visY' && !$vis_alle) ? $aging['bucketTotal'] : $aging[$bucketKey],2);
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
	$totalPages=($totalKontoantal && !$openpostShowAll) ? ceil($totalKontoantal/$openpostPageSize) : 1;
	if ($openpostPage > $totalPages) {
		$openpostPage=$totalPages;
		$openpostOffset=($openpostPage-1)*$openpostPageSize;
	}
	if ($agingBucket || $orderBy) {
		$pageAccountIds=array();
		foreach (array_slice($sortedAccounts, $openpostOffset, ($openpostShowAll) ? NULL : $openpostPageSize) as $i => $account) {
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
	elseif ($openpostShowAll) $qtxt.= " order by account_sort, adresser.id) account_page ";
	else $qtxt.= " order by account_sort, adresser.id limit $openpostPageSize offset $openpostOffset) account_page ";
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
	$pageAging=array();
	foreach ($accountPosts as $i => $posts) {
		$aging=openpost_account_aging($posts, $todate, $currentdate, $kontoart, $agingDateCache);
		if (openpost_account_visible($aging, $todate, $currentdate, $kun_debet, $kun_kredit, $vis_alle)) {
			$pageAging[$i]=$aging;
		}
	}
	$renderedCount=count($pageAging);
	$allAccountsLoaded=($openpostShowAll || $totalKontoantal <= $openpostPageSize);
	$kontoantal=($allAccountsLoaded) ? $renderedCount : $totalKontoantal;
	$bucketTotals=array_fill_keys(array_column($buckets, 'key'), 0);
	$sum=0;
	$kontrolsum=0;
	$udlign=NULL;
	$formIndex=0;
	$displayFirst=($renderedCount) ? $openpostOffset+1 : 0;
	$displayLast=($renderedCount) ? $openpostOffset+$renderedCount : 0;
	// Only offer the paging toggle when the result is bigger than one default page.
	$showPagingBar=($totalKontoantal > (($openpostShowAll) ? $openpostDefaultPageSize : $openpostPageSize));
	if ($openpostShowAll) {
		$pageSizeToggle="<a class='op-link' href=\"$pagedUrl\">".htmlspecialchars(sprintf(findtekst('5521|Vis %s konti pr. side',$sprog_id),$openpostDefaultPageSize),ENT_QUOTES)."</a>";
	} else {
		$pageSizeToggle="<a class='op-link' href=\"$showAllUrl\">".htmlspecialchars(findtekst('5520|Vis alle konti på én side',$sprog_id),ENT_QUOTES)."</a>";
	}
	$pagingHtml="";
	if ($showPagingBar) {
		if ($openpostShowAll) {
			$pagingHtml=htmlspecialchars(sprintf(findtekst('5558|Viser alle %s konti',$sprog_id),$kontoantal),ENT_QUOTES);
		} else {
			if ($openpostPage > 1) $pagingHtml.="<a class='op-link' href=\"$basePageUrl&openpost_page=".($openpostPage-1)."\">Forrige</a> &nbsp;";
			$pagingHtml.="Viser $displayFirst-$displayLast af $kontoantal";
			if ($openpostPage < $totalPages) $pagingHtml.="&nbsp; <a class='op-link' href=\"$basePageUrl&openpost_page=".($openpostPage+1)."\">N&aelig;ste</a>";
		}
		$pagingHtml.=" &nbsp;&middot;&nbsp; $pageSizeToggle";
	}
	// The T layout keeps its paging line above the rows; the grid layout shows it in the sticky action bar.
	if ($showPagingBar && $menu=='T') {
		$colspan = $usePBS ? 11 : 10;
		print "<tr><td colspan='$colspan' align='center'>$pagingHtml</td></tr>\n";
	}
	// Links that act on this page (0,00 settlement, customer card return target) come back to it.
	$pageUrl=$basePageUrl."&openpost_page=$openpostPage";
	// Return target of the kontonr link. accountChart.php appends its returside unencoded to its
	// udlign_openpost.php links, after their own konto_fra/konto_til, so the filter travels as kontonr
	// and cannot override the account there.
	$chartReturnUrl=str_replace("&konto_fra=$konto_fraUrl&konto_til=$konto_tilUrl", "&kontonr=$kontonrFilterUrl", $pageUrl);
	$creditTitle=findtekst('1001|Kredit',$sprog_id);
	$cardPage=($kontoart=='K') ? 'kreditorkort.php' : 'debitorkort.php';
	$cardTitle=htmlspecialchars(findtekst(($kontoart=='K') ? '1184|Kreditorkort' : '356|Debitorkort',$sprog_id),ENT_QUOTES);
	for ($x=1; $x<=$pageAccountCount; $x++) {
		if (!isset($pageAging[$x])) continue;
		$aging=$pageAging[$x];
		$accountAligned=$aging['accountAligned'];
		$rykkerbelob=$aging['rykkerbelob'];
		$kontrol=$aging['kontrol'];
		$openKontrol=$aging['openKontrol'];
		$y=$aging['y'];
		$openY=$aging['openY'];
		$visY=$aging['visY'];
		$visKontrol=$aging['visKontrol'];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}

		foreach ($buckets as $bucket) {
			$bucketTotals[$bucket['key']]+=$aging[$bucket['key']];
		}
		$sum=$sum+$visY;
		$kontrolsum+=$visKontrol;
		$formIndex++;
		print "<tr bgcolor=\"$linjebg\">";
		print "<input type=hidden name='konto_id[$formIndex]' value='$konto_id[$x]'>";
		$kontonrUrl=rawurlencode($kontonr[$x]);
		$chartUrl="rapport.php?rapportart=accountChart&kilde=openpost&kilde_kto_fra=$konto_fraUrl&kilde_kto_til=$konto_tilUrl&dato_fra=$dato_fraUrl&dato_til=$dato_tilUrl";
		$chartUrl.="&konto_fra=$kontonrUrl&konto_til=$kontonrUrl&submit=ok$stateUrl&returside=".rawurlencode($chartReturnUrl);
		print "<td><a href=\"".htmlspecialchars($chartUrl,ENT_QUOTES)."\">";
		print "<span title='Klik for detaljer'>".htmlspecialchars($kontonr[$x],ENT_QUOTES)."</span></a></td>";
		if ($usePBS) print "<td>$pbs[$x]</td>";
		$cardUrl=htmlspecialchars("$cardPage?id=".(int)$konto_id[$x]."&returside=".rawurlencode($pageUrl),ENT_QUOTES);
		print "<td><a class='op-name' href=\"$cardUrl\" title=\"$cardTitle\">".htmlspecialchars($firmanavn[$x],ENT_QUOTES)."</a></td>";
		foreach ($buckets as $bucketId => $bucket) {
			print openpost_aging_cell($aging[$bucket['key']], $kontoart, $bucketId != 'ikke', false, $creditTitle);
		}
		// Keep the existing control correction tied to the raw balance, not to the
		// display-only rounding across buckets (which must never repair/book posts).
		if (afrund($visKontrol,2)!=afrund($visY,2)) ret_openpost($konto_id[$x]);
		$tmp=dkdecimal(openpost_row_total($aging, $vis_alle),2);
		# Valutadiff rows are booked as already settled, so the open remainder alone can
		# differ from zero while the whole account balances. When settled posts are loaded
		# too (Vis alle poster or a historical to-date), the whole balance decides as well.
		$allPostsLoaded = ($vis_alle || $todate != $currentdate);
		$canSettleAll = ($accountAligned=="0" && ((abs($openY)<0.01 && abs($openKontrol)<0.01)
			|| ($allPostsLoaded && abs($y)<0.01 && abs($kontrol)<0.01)));
		if ($canSettleAll) {
			$udlign.=$konto_id[$x].",";
			print "<td align=right title=\"Klik her for at udligne &aring;bne poster\"><a class='op-link' href=\"".htmlspecialchars("$pageUrl&udlign=".(int)$konto_id[$x],ENT_QUOTES)."\">$tmp</a></td>";
		}
		else {print "<td align=right>$tmp</td>";}
		if ((isset($kontoudtog[$x]) && $kontoudtog[$x]=='on') && ($kontoart=="D")) print "<td align=center><label class='checkContainerOrdreliste'><input type=checkbox name=kontoudtog[$formIndex] checked><span class='checkmarkOrdreliste'></span></label>";
		elseif($kontoart=="D")  print "<td align=center><label class='checkContainerOrdreliste'><input type=checkbox name=kontoudtog[$formIndex]><span class='checkmarkOrdreliste'></span></label>";
		else print "<td></td>"; // keeps the row stripe across the (empty) checkbox column
		print "</tr>\n";
		print "<input type=hidden name=rykkerbelob[$formIndex] value=$rykkerbelob>";
	}

	if (!$renderedCount) {
		print "<tr><td colspan='$headerColspan' align=center style='padding:6px;color:#555;'>".findtekst('2541|Ingen',$sprog_id)."</td></tr>\n";
	}
	foreach ($bucketTotals as $key => $amount) {
		$bucketTotals[$key]=afrund($amount,2);
	}

	// "I alt" only when every matching account is on this page; otherwise the sum covers this page.
	$totalLabel=($allAccountsLoaded) ? findtekst('5019|I alt',$sprog_id) : findtekst('5524|I alt (denne side)',$sprog_id);
	$totalLabel=htmlspecialchars($totalLabel,ENT_QUOTES);
	($usePBS) ? $colspan = 2 : $colspan = 1 ;
	if ($menu=='T') {
		print "</tbody><tfoot>";
		print "<tr><td colspan='$colspan'><br></td><td><b>$totalLabel</b></td>";
	} else {
		print "<tr class='op-total-row'><td colspan='$colspan'></td><td><b>$totalLabel</b></td>";
	}

	foreach ($buckets as $bucketId => $bucket) {
		print openpost_aging_cell($bucketTotals[$bucket['key']], $kontoart, $bucketId != 'ikke', true, $creditTitle);
	}
	$color="rgb(0, 0, 0)";
	$total=($vis_alle) ? (($sum <= $kontrolsum) ? $kontrolsum : $sum) : array_sum($bucketTotals);
	$tmp=dkdecimal(afrund($total,2),2);
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
	print "<input type=hidden name=order_dir value=\"$orderDir\">";
	if (!$showPBS) print "<input type=hidden name=showPBS value=0>";
	print "<input type=hidden name=openpost_content value=1></td></tr>";

	// The Mail kontoudtog/Opret rykker/Ryk alle buttons post back konto_id[] checkboxes from the
	// account rows above; with no matching accounts (formIndex still 0) no konto_id[] fields exist
	// to act on, so skip the buttons rather than submit an empty/missing konto_id. 20260923 CL/NTR
	$actionsHtml="";
	if ($kontoart=='D' && $formIndex > 0) {
		$jsonFlags=JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
		$actionsHtml.="<span title=\"Klik her for at maile kontoudtog til de modtagere som er afm&aelig;rket herover\">";
		$actionsHtml.="<input type=submit value=\"Mail kontoudtog\" name=\"submit\"></span>&nbsp;&nbsp;";
		$actionsHtml.="<span title='Klik her for at oprette rykker til de som er afm&aelig;rkede herover'>";
		$actionsHtml.="<input type=submit value=\"Opret rykker\" name=\"submit\"></span>&nbsp;&nbsp;";
		if ($udlign) {
			$udlign=trim($udlign,",'");
			// The filter values in $pageUrl are URL-encoded and the whole handler is attribute-escaped,
			// so quotes in request-supplied values cannot break out of it (XSS).
			$udlignUrl=$pageUrl.'&udlign='.rawurlencode($udlign);
			$udlignCount=count(explode(',',$udlign));
			if ($udlignCount == 1) $udlignConfirm=findtekst('5547|Udlign 1 konto med saldo 0,00?',$sprog_id);
			else $udlignConfirm=sprintf(findtekst('5543|Udlign %s konti med saldo 0,00?',$sprog_id),$udlignCount);
			$udlignTitle=htmlspecialchars(findtekst('5544|Udligner alle viste konti hvor saldoen er 0,00',$sprog_id),ENT_QUOTES);
			$udlignClick=htmlspecialchars("if (confirm(".json_encode($udlignConfirm,$jsonFlags).")) location.href=".json_encode($udlignUrl,$jsonFlags).";",ENT_QUOTES);
			$actionsHtml.="<input type='button' onclick=\"$udlignClick\" title=\"$udlignTitle\" value='Udlign alle'>&nbsp;&nbsp;";
		}
		// Ryk alle runs ny_rykker.php for every debtor unless ticked accounts with an overdue amount
		// exist (then only for those, like Opret rykker) - the confirm states which of the two happens.
		$rykAlleAll=findtekst('5537|Ryk alle kører rykkerkørslen for ALLE debitorer - ikke kun de viste eller afmærkede konti. Den vil:',$sprog_id);
		foreach (array('5538|udligne konti hvis åbne poster giver 0,00, og slette deres åbne rykkere', '5539|slette åbne rykkere for konti uden åbne poster', '5540|oprette rykkere for forfaldne poster, der endnu ikke er rykket for', '5541|bogføre rykkere hvis frist er overskredet, og oprette næste rykker') as $textId) {
			$rykAlleAll.="\n- ".findtekst($textId,$sprog_id);
		}
		$rykAlleContinue="\n\n".findtekst('1991|Fortsæt',$sprog_id)."?";
		$rykAlleAll.=$rykAlleContinue;
		$rykAlleSelected=findtekst('5542|Der er afmærket %s konti med forfaldent beløb, så Ryk alle opretter kun rykkere for dem (som Opret rykker).',$sprog_id).$rykAlleContinue;
		$rykAlleSelectedOne=findtekst('5559|Der er afmærket 1 konto med forfaldent beløb, så Ryk alle opretter kun en rykker for den (som Opret rykker).',$sprog_id).$rykAlleContinue;
		$rykAlleTitle=htmlspecialchars(findtekst('5545|Rykkerkørsel for alle debitorer - eller kun for de afmærkede konti med forfaldent beløb',$sprog_id),ENT_QUOTES);
		print "<script>
function opConfirmRykAlle() {
	var n = 0;
	document.querySelectorAll('input[name^=\"kontoudtog[\"]:checked').forEach(function (c) {
		var r = document.querySelector('input[name=\"rykkerbelob[' + c.name.slice(11, -1) + ']\"]');
		if (r && parseFloat(r.value) > 0) n++;
	});
	if (n == 1) return confirm(".json_encode($rykAlleSelectedOne,$jsonFlags).");
	return confirm(n ? ".json_encode($rykAlleSelected,$jsonFlags).".replace('%s', n) : ".json_encode($rykAlleAll,$jsonFlags).");
}
</script>";
		$actionsHtml.="<input type=submit value=\"Ryk alle\" name=\"submit\" title=\"$rykAlleTitle\" onclick=\"return opConfirmRykAlle();\">";
	}
	if ($menu=='T') {
		if ($actionsHtml) print "<tr><td colspan='$headerColspan' align='center' class='border-hr-top'>$actionsHtml</td></tr>\n";
		if ($showPagingBar) print "<tr><td colspan='$headerColspan' align='center' class='border-hr-top'>$pagingHtml</td></tr>\n";
	} elseif ($actionsHtml || $pagingHtml) {
		// Sticky at the bottom of #opGridWrapper, so the actions and paging stay reachable while scrolling.
		print "<tr class='op-actionbar'><td colspan='$headerColspan'><div class='op-actionbar-inner'><div>$actionsHtml</div><div>$pagingHtml</div></div></td></tr>\n";
	}
	print "</form>\n";

	if ($menu=='T') {
		print "</tfoot></table></div></td></tr>";
	} else {
		print "</tbody></table>";
	}

	// openpost() prints the rykker overview below the grid and the footer itself, at the very end.
	if ($printFooter) {
		if ($menu=='T') {
			include_once __DIR__ . '/../topmenu/footer.php';
		} else {
			include_once __DIR__ . '/../oldDesign/footer.php';
		}
	}

	
}} //endfunc vis_aabne_poster

?>
