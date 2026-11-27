<?php
// ---- index/dashboardIncludes/dashData.php --- ver 5.1.1 --- 2026.11.17 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261008 SS The numbers behind Oversigt (Adam's prototype_dashboard_tema.html v5): week, month and fiscal year revenue with last year and a trend, the VAT period with its deadline, revenue per month and per day, customers per hour, revenue per item group, orders, active users and waiting suggestions.
//             Revenue is the sum of credit minus debit on the accounts between the dashboard's kontomin and kontomaks, as the old widgets counted it.
//             Everything from one daily query over the last and the current fiscal year.
// 20261009 SS The largest uninvoiced order (addendum 3 §7) and the three newest suggestions (§7a) for the small cards.
// 20261010 SS Key figures 1 and 3 of §7 (open point 9 settled): status 2 = delivered, not invoiced; levdate is the delivery date.
// 20261012 SS The headline counts every uninvoiced order (Adam): the 30-day window is gone.
// 20261101 CL/ASR Each item group also carries its revenue (fourth field), for the table that replaces the ring (prototype D).
// 20261101 CL/ASR The VAT KPI no longer sums up to 8 earlier periods for a trend line the page does not draw any more.
// 20261101 CL/ASR No active-user count and no suggestion list any more (both cards left the dashboard).
// 20261101 CL/ASR Every item group is returned as well ('all'), so the page can show them all when its row has room.
// 20261103 CL/ASR QA: the fiscal-year figure ends at the selected year's last day (an earlier year no longer counts later postings), the month chart runs over the year's own months (6 or 18 too, not always 12), and item groups with a negative revenue (credit notes) stay in the total and the list.
// 20261105 CL/LH Week, month, year to date and the VAT period run to today placed in the fiscal year shown (dash_fiscal_today()), as the old widgets did, not to the server's date.
//                An earlier year in the top bar showed 0 kr for this week and month and compared its whole year with the whole year before.
//                The month and week charts' figures-to-date cut-offs (toDate, dayToDate) are set here from the same day.
// 20261105 CL/LH The revenue figures count the Fra and Til accounts themselves (kontonr >= kontomin and <= kontomaks), as the old month and day charts and the VAT figure did.
//                The month chart left out account 0's postings in the range 0-2000, and Til kontonr. 1100 left account 1100 out of every revenue figure.
// 20261108 CL/ASR Revenue counts the tenant's net revenue accounts by default, read from its own chart (dash_chart_revenue_range(): the Nettoomsætning sum account, 1010-1390 in the standard chart), not 0-2000, which also took purchases and sales costs.
//                 A saved Fra/Til kontonr. wins, except the old dashboard's unchosen 0/2000 and 0/12000 (dash_revenue_accounts()).
//                 Both ends of the range count in every revenue figure (dash_account_sql()); the daily figures left them out.
// 20261108 CL/ASR An empty Fra or Til kontonr. means automatic, bound by bound (owner, 8 Nov 2026).
//                 An empty Fra takes the chart's from-account and an empty Til its net revenue sum account, 0 and 2000 when the chart has none.
//                 The drawer stores an empty field as '' instead of 0 (dash_account_input()).
//                 dash_revenue_accounts() also returns the chosen and the automatic bounds, for the drawer's placeholders.
// 20261113 CL/ASR The VAT figure reads the VAT report's range with vat_report_range() (includes/vatReportRange.php), the same row Finans > Rapporter > Momsangivelse uses.
//                 Without a range, or with bounds that are not account numbers, the figure is left out instead of summing the revenue accounts kontomin..kontomaks as VAT.
// 20261113 CL/ASR Without a previous fiscal year (no grupper RA row for regnaar - 1) the month figures have no last-year label or series ('lastLabel' and 'last' null), so the chart no longer offers "2026 mod 0".
//                 A week's last-year series is null too when the same week last year ends before this fiscal year starts (dash_week_comparable()): no fiscal year holds figures there.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
// 20261117 CL/ASR Integration: Omsætning for året for a fiscal year that has ended is the whole year against the whole year before it, as the owner's QA of 20261103 says (owner, 17 Nov 2026); week, month and the VAT period keep today placed in that year.

include_once(__DIR__ . '/../../includes/vatReportRange.php');

/**
 * The account condition of every revenue query on the dashboard: both ends of the range count. The old widgets mixed
 * strict bounds ('>' and '<': week, month and year) with '>=' and '<=' (the graphs), and so did dash_daily() and
 * dash_sum() here, so an account on an edge counted in one figure and not in the other.
 */
function dash_account_sql(int $min, int $max): string
{
	return "kontonr >= $min and kontonr <= $max";
}

/**
 * An account name made comparable: HTML entities decoded (importfiler/kontoplan2.txt says "NETTOOMS&AElig;TNING"),
 * Latin-1 bytes read as Latin-1 (older regnskaber are not UTF-8), lower case, every run of other characters than
 * letters and digits one space ("OMSÆTNING:" -> "omsætning", "Salgs- og driftsinntekter" -> "salgs og driftsinntekter").
 */
function dash_account_name(string $name): string
{
	if (!mb_check_encoding($name, 'UTF-8')) {
		$name = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');
	}
	$name = mb_strtolower(html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'UTF-8');
	return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name));
}

/**
 * Whether a sum account's name says net revenue, in Danish, Norwegian or English. Only whole names count, so
 * "Omsætningsaktiver i alt" (the balance sheet), "Andre driftsindtægter" and "Varesalg i alt" (goods only) do not.
 */
function dash_is_net_revenue_name(string $name): bool
{
	$n = dash_account_name($name);
	$patterns = array(
		// Nettoomsætning, OMSÆTNING I ALT, Total omsætning, Samlet omsætning, Omsætning (netto)
		'/^(sum |total |samlet )?(netto ?)?oms(æ|ae)tning(en)?( netto)?( i ?alt| total)?$/u',
		// Nettoomsetning, Sum omsetning, Samlet omsetning
		'/^(sum |total |samlet )?(netto ?)?omsetning(en)?( netto)?( i ?alt| totalt?)?$/u',
		// Sum driftsinntekter, Salgs- og driftsinntekter, Driftsinntekter totalt
		'/^(sum |total |samlet )?(salgs og )?(salgs|drifts)inntekt(er)?( i ?alt| totalt?)?$/u',
		// Net revenue, Net turnover, Revenue total, Sales revenue
		'/^(total )?(net )?(sales )?(revenue|turnover)( total)?$/u',
		// Net sales, Total sales, Total net sales (not a bare "Sales")
		'/^(total net|net|total) sales( total)?$/u',
	);
	foreach ($patterns as $p) {
		if (preg_match($p, $n)) {
			return true;
		}
	}
	return false;
}

/**
 * The tenant's net revenue accounts, read from its own chart: array(from, to), or null when none can be identified.
 *
 * Rule: the first sum account (kontotype 'Z', lowest number first) whose name says net revenue (dash_is_net_revenue_name())
 * and whose range holds operating accounts only, at least one 'D' (Drift) and no 'S' (Status), so a sum in the balance
 * sheet is never taken. The range runs from the sum account's fra_kto to its own number, both included, which is how
 * Saldi adds up a sum account (finans/regnskab.php, finans/rapport_includes/regnskab.php: fra_kto <= kontonr <= the sum
 * account). Evidence, every chart Saldi ships or has shipped:
 * - importfiler/kontoplan.txt, the chart of new regnskaber (includes/opretfunc.php reads it into kontoplan.fra_kto):
 *   1390 Nettoomsætning, Z from 1010. The old range 0-2000 also took the stock change 1410, Varekøb and Ydelseskøb
 *   1610-1740 and Salgsomkostninger 1830-1990.
 * - the same file before 2024 (git history): 1950 OMSÆTNING I ALT, Z from 1000, followed by Vareforbrug from 2000; the old
 *   range 0-2000 was made for this chart, so regnskaber created on it keep their figures.
 * - importfiler/kontoplan2.txt: 1999 NETTOOMSÆTNING, sum from 2 (the earlier 1890 "Varesalg i alt" holds goods only);
 *   importfiler/kontoplan3.txt: 11797 OMSÆTNING I ALT, total from 10000 (the balance sheet's 27000 OMSÆTNINGSAKTIVER I ALT
 *   does not match); includes/kontoplan.php (no longer loaded): 10990 Omsætning i alt, Z from 1000.
 * With no match the dashboard keeps the old default pair, 0-2000 when nothing is saved (dash_revenue_accounts()).
 *
 * @param array<int, array{kontonr: mixed, beskrivelse: mixed, kontotype: mixed, fra_kto: mixed}> $chart the accounts of the fiscal year
 * @return array{0: int, 1: int}|null
 */
function dash_chart_revenue_range(array $chart): ?array
{
	$accounts = array();
	foreach ($chart as $a) {
		$accounts[] = array('nr' => (int) $a['kontonr'], 'type' => strtoupper(trim((string) $a['kontotype'])), 'name' => (string) $a['beskrivelse'], 'from' => (int) $a['fra_kto']);
	}
	usort($accounts, function ($a, $b) {
		return $a['nr'] <=> $b['nr'];
	});
	foreach ($accounts as $sum) {
		if ($sum['type'] !== 'Z' || $sum['from'] > $sum['nr'] || !dash_is_net_revenue_name($sum['name'])) {
			continue;
		}
		$types = array();
		foreach ($accounts as $a) {
			if ($a['nr'] >= $sum['from'] && $a['nr'] <= $sum['nr']) {
				$types[$a['type']] = true;
			}
		}
		if (isset($types['D']) && !isset($types['S'])) {
			return array($sum['from'], $sum['nr']);
		}
	}
	return null;
}

/**
 * The accounts of a fiscal year, for dash_chart_revenue_range().
 *
 * @return array<int, array{kontonr: mixed, beskrivelse: mixed, kontotype: mixed, fra_kto: mixed}>
 */
function dash_chart_rows(int $regnaar): array
{
	$rows = array();
	$q = db_select("select kontonr, beskrivelse, kontotype, fra_kto from kontoplan where regnskabsaar = $regnaar order by kontonr", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$rows[] = array('kontonr' => $r['kontonr'], 'beskrivelse' => $r['beskrivelse'], 'kontotype' => $r['kontotype'], 'fra_kto' => $r['fra_kto']);
	}
	return $rows;
}

/**
 * A Fra/Til kontonr. typed in Rediger oversigt, as it is stored: the digits without leading zeros, '' for an empty field
 * (automatic), or null when it is no account number (letters, a sign, a decimal point, more digits than kontoplan.kontonr
 * holds), which the page refuses.
 */
function dash_account_input(string $value): ?string
{
	$value = trim($value);
	if ($value === '') {
		return '';
	}
	return (ctype_digit($value) && strlen($value) <= 15) ? (string) (int) $value : null;
}

/**
 * A stored Fra/Til kontonr. (settings dashboard_values kontomin/kontomaks): the account number, or null for automatic.
 * Nothing stored and an empty value are automatic, and so is anything else than digits, which the drawer never stores.
 */
function dash_saved_account($value): ?int
{
	$value = trim((string) $value);
	return ctype_digit($value) ? (int) $value : null;
}

/**
 * The revenue accounts the dashboard counts, bound by bound (owner, 8 Nov 2026: "empty fields = automatic, from your chart
 * of accounts").
 *
 * Fra and Til kontonr. are each either chosen (a stored number) or automatic (nothing stored, or an empty field saved in
 * Rediger oversigt). An automatic Fra is the chart's from-account and an automatic Til the chart's net revenue sum account
 * (dash_chart_revenue_range(): 1010 and 1390 in the standard chart); with no such account in the chart they are 0 and 2000,
 * the old defaults. So an empty Fra with Til 2000 counts 1010-2000 on the standard chart, and Fra 1000 with an empty Til
 * counts 1000-1390.
 * The pairs the old dashboard wrote without anyone choosing them (index/dashboard.php before this page) are automatic too:
 * 0 and 2000 on every save of its settings form, and 0 and 12000 in its 2024 versions on any other POST to the page, such as
 * a language change. With no match in the chart such a pair stays the automatic range, so a 5-digit chart keeps its 0-12000.
 * A chosen pair is used as stored, even a reversed one (the page no longer saves one).
 *
 * @param mixed $savedMin the stored kontomin, null when nothing is stored
 * @param mixed $savedMax the stored kontomaks, null when nothing is stored
 * @param array{0: int, 1: int}|null $chartRange the chart's net revenue accounts (dash_chart_revenue_range())
 * @return array{from: int, to: int, chosenFrom: int|null, chosenTo: int|null, autoFrom: int, autoTo: int, autoSource: string}
 *         the range counted, the chosen bounds (null = automatic) and the automatic range with its source, 'chart' or 'default'
 */
function dash_revenue_accounts($savedMin, $savedMax, ?array $chartRange): array
{
	$min = dash_saved_account($savedMin);
	$max = dash_saved_account($savedMax);
	$fallback = array(0, 2000);
	if ($min === 0 && ($max === 2000 || $max === 12000)) {
		$fallback = array($min, $max);
		$min = $max = null;
	}
	$auto = $chartRange ? array((int) $chartRange[0], (int) $chartRange[1]) : $fallback;
	return array(
		'from' => $min ?? $auto[0], 'to' => $max ?? $auto[1],
		'chosenFrom' => $min, 'chosenTo' => $max,
		'autoFrom' => $auto[0], 'autoTo' => $auto[1], 'autoSource' => $chartRange ? 'chart' : 'default',
	);
}

/**
 * Revenue per day (date => amount) between two dates on the revenue accounts $min to $max, both included.
 *
 * @return array<string, float>
 */
function dash_daily(string $from, string $to, int $min, int $max): array
{
	$out = array();
	$q = db_select("select transdate, sum(coalesce(kredit, 0) - coalesce(debet, 0)) as s from transaktioner where transdate >= '" . db_escape_string($from) . "' and transdate <= '" . db_escape_string($to) . "' and " . dash_account_sql($min, $max) . " group by transdate", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[substr((string) $r['transdate'], 0, 10)] = (float) $r['s'];
	}
	return $out;
}

/**
 * The sum of a daily map between two dates (inclusive).
 *
 * @param array<string, float> $daily
 */
function dash_range(array $daily, string $from, string $to): float
{
	$sum = 0.0;
	foreach ($daily as $d => $v) {
		if ($d >= $from && $d <= $to) {
			$sum += $v;
		}
	}
	return $sum;
}

function dash_sum(string $from, string $to, int $min, int $max): float
{
	$r = db_fetch_array(db_select("select sum(coalesce(kredit, 0) - coalesce(debet, 0)) as s from transaktioner where transdate >= '" . db_escape_string($from) . "' and transdate <= '" . db_escape_string($to) . "' and " . dash_account_sql($min, $max), __FILE__ . " linje " . __LINE__));
	return $r ? (float) $r['s'] : 0.0;
}

/**
 * Whether a week of Omsætning pr. dag can be compared with the same ISO week last year (Monday $lyMonday): always when the
 * previous fiscal year exists, else only when that week ends on or after the start of this fiscal year, the first one,
 * since before it no fiscal year holds figures.
 */
function dash_week_comparable(bool $hasLastFy, string $lyMonday, string $fyStart): bool
{
	return $hasLastFy || dash_add($lyMonday, '+6 days') >= $fyStart;
}

/**
 * Monday of an ISO week.
 */
function dash_week_monday(int $year, int $week): string
{
	$d = new DateTime();
	$d->setISODate($year, $week);
	return $d->format('Y-m-d');
}

function dash_add(string $date, string $mod): string
{
	return date('Y-m-d', strtotime($mod, strtotime($date)));
}

/**
 * Today placed in a fiscal year, as the old widgets (revenue_week.php, revenue_month.php, revenue_year.php) placed it:
 * today itself when the year holds it, else today's day and month in the year of its start, or the year after when that
 * date falls before the start (29 February becomes the 28th). A short year that ends before that date gives its last day.
 *
 * @param string $today   'Y-m-d'
 * @param string $fyStart the fiscal year's first day, 'Y-m-d'
 * @param string $fyEnd   the fiscal year's last day, 'Y-m-d'
 * @return string 'Y-m-d', between $fyStart and $fyEnd
 */
function dash_fiscal_today(string $today, string $fyStart, string $fyEnd): string
{
	if ($today >= $fyStart && $today <= $fyEnd) {
		return $today;
	}
	$m = (int) substr($today, 5, 2);
	$d = (int) substr($today, 8, 2);
	$y = (int) substr($fyStart, 0, 4);
	$t = sprintf('%04d-%02d-%02d', $y, $m, min($d, (int) date('t', mktime(0, 0, 0, $m, 1, $y))));
	if ($t < $fyStart) {
		$t = sprintf('%04d-%02d-%02d', $y + 1, $m, min($d, (int) date('t', mktime(0, 0, 0, $m, 1, $y + 1))));
	}
	return min($t, $fyEnd);
}

/**
 * The Danish VAT deadline of a period that ends on $end: monthly on the 25th of the next month, quarterly on the first
 * day of the third month after, half-yearly 1 September / 1 March.
 */
function dash_vat_deadline(string $period, string $end): string
{
	$y = (int) substr($end, 0, 4);
	$m = (int) substr($end, 5, 2);
	if ($period === 'month') {
		return date('Y-m-d', mktime(0, 0, 0, $m + 1, 25, $y));
	}
	if ($period === 'halfyear') {
		return $m <= 6 ? sprintf('%04d-09-01', $y) : sprintf('%04d-03-01', $y + 1);
	}
	return date('Y-m-d', mktime(0, 0, 0, $m + 3, 1, $y));
}

/**
 * Short month and day names in the user's language (1 Danish, 2 English, 3 Norwegian).
 *
 * @return array{months: array<int, string>, days: array<int, string>}
 */
function dash_names(int $sprogId): array
{
	$all = array(
		1 => array(array('Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'), array('Man', 'Tirs', 'Ons', 'Tors', 'Fre', 'Lør', 'Søn')),
		2 => array(array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'), array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun')),
		3 => array(array('Jan', 'Feb', 'Mar', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Des'), array('Man', 'Tirs', 'Ons', 'Tors', 'Fre', 'Lør', 'Søn')),
	);
	$n = isset($all[$sprogId]) ? $all[$sprogId] : $all[1];
	return array('months' => $n[0], 'days' => $n[1]);
}

/**
 * A KPI: value, change against last year in percent (null without a last year), trend points.
 *
 * @param array<int, float> $spark
 * @return array{value: float, last: float, delta: float|null, spark: array<int, float>}
 */
function dash_kpi(float $now, float $last, array $spark): array
{
	return array('value' => $now, 'last' => $last, 'delta' => $last != 0 ? ($now - $last) / abs($last) * 100 : null, 'spark' => array_values($spark));
}

/**
 * Everything the page and its script need.
 *
 * @param array<string, mixed> $c regnstart, regnslut, regnaar, kontomin, kontomaks (the revenue accounts, dash_revenue_accounts()),
 *                                sprog_id, bruger_id, vat (vat_registration()), today ('Y-m-d', the server's date when not given)
 * @return array<string, mixed>
 */
function dash_data(array $c): array
{
	global $regnaar;
	$today = date('Y-m-d', isset($c['today']) ? strtotime((string) $c['today']) : time());
	$min = (int) $c['kontomin'];
	$max = (int) $c['kontomaks'];
	$names = dash_names((int) $c['sprog_id']);
	$fyStart = (string) $c['regnstart'];
	$fyEnd = (string) $c['regnslut'];
	$lastFyStart = dash_add($fyStart, '-1 year');
	$lastFyEnd = dash_add($fyEnd, '-1 year');
	// Week, month, year to date and the VAT period run to today placed in the fiscal year shown, so an earlier or later
	// year chosen in the top bar shows its own figures. The charts have figures up to $dataTo: today, or that day in a
	// later year; an earlier year keeps its whole months and weeks.
	$t = dash_fiscal_today($today, $fyStart, $fyEnd);
	$dataTo = max($today, $t);
	$queryFrom = min($lastFyStart, dash_add($t, '-1 year -9 weeks'));
	$daily = dash_daily($queryFrom, $dataTo, $min, $max);
	// The fiscal years' names (grupper RA). Without a previous fiscal year there is nothing to compare the months with.
	$fyName = function (int $kodenr): ?string {
		$r = db_fetch_array(db_select("select beskrivelse from grupper where art = 'RA' and kodenr = $kodenr", __FILE__ . " linje " . __LINE__));
		return $r ? trim((string) $r['beskrivelse']) : null;
	};
	$nowName = $fyName((int) $regnaar) ?? (string) (int) $regnaar;
	$lastName = $fyName((int) $regnaar - 1);

	// Week: this ISO week to date against the same ISO week last year, trend = the last 8 weeks.
	$isoYear = (int) date('o', strtotime($t));
	$isoWeek = (int) date('W', strtotime($t));
	$monday = dash_week_monday($isoYear, $isoWeek);
	$sunday = dash_add($monday, '+6 days');
	$lyMonday = dash_week_monday($isoYear - 1, $isoWeek);
	$weekNow = dash_range($daily, $monday, $sunday);
	$weekLast = dash_range($daily, $lyMonday, dash_add($lyMonday, '+6 days'));
	$weekSpark = array();
	for ($i = 7; $i >= 0; $i--) {
		$m = dash_add($monday, "-$i weeks");
		$weekSpark[] = dash_range($daily, $m, dash_add($m, '+6 days'));
	}

	// Month: the 1st to today against the same days last year, trend = the last 8 months.
	$monthFrom = substr($t, 0, 8) . '01';
	$monthNow = dash_range($daily, $monthFrom, $t);
	$monthLast = dash_range($daily, dash_add($monthFrom, '-1 year'), dash_add($t, '-1 year'));
	$monthSpark = array();
	for ($i = 7; $i >= 0; $i--) {
		$f = date('Y-m-01', strtotime("-$i months", strtotime($monthFrom)));
		$monthSpark[] = dash_range($daily, $f, date('Y-m-t', strtotime($f)));
	}

	// Fiscal year to date against last fiscal year to the same date; trend = the fiscal months to date. A year that has
	// ended counts in whole, against the whole year before it; a later year chosen in the top bar counts to today's date
	// in that year.
	$yearTo = $today > $fyEnd ? $fyEnd : $t;
	$yearNow = dash_range($daily, $fyStart, $yearTo);
	$yearLast = dash_range($daily, $lastFyStart, dash_add($yearTo, '-1 year'));
	$labels = array();
	$mNow = array();
	$mLast = array();
	$startMonth = (int) substr($fyStart, 5, 2);
	$startYear = (int) substr($fyStart, 0, 4);
	// The year's own number of months (a first or changed fiscal year can be 6 or 18 months).
	$fyMonths = max(1, min(24, ((int) substr($fyEnd, 0, 4) - $startYear) * 12 + (int) substr($fyEnd, 5, 2) - $startMonth + 1));
	// Fiscal months begun by $dataTo: later months have no figure yet (no bar, "–" in the tooltip and the table).
	$monthsToDate = 0;
	for ($i = 0; $i < $fyMonths; $i++) {
		$f = date('Y-m-01', mktime(0, 0, 0, $startMonth + $i, 1, $startYear));
		if ($f <= $dataTo) {
			$monthsToDate++;
		}
		$labels[] = $names['months'][(int) substr($f, 5, 2) - 1];
		$mNow[] = dash_range($daily, $f, date('Y-m-t', strtotime($f)));
		$lf = dash_add($f, '-1 year');
		$mLast[] = dash_range($daily, $lf, date('Y-m-t', strtotime($lf)));
	}
	$yearSpark = array();
	foreach ($mNow as $i => $v) {
		$f = date('Y-m-01', mktime(0, 0, 0, $startMonth + $i, 1, $startYear));
		if ($f <= $yearTo) {
			$yearSpark[] = $v;
		}
	}
	$yearSpark = array_slice($yearSpark, -8);
	if (count($yearSpark) < 2) {
		$yearSpark = array(0.0, $yearNow);
	}

	// The week day by day: this week and the two before, each against the same ISO week last year.
	$weeks = array();
	for ($i = 0; $i < 3; $i++) {
		$d = new DateTime($monday);
		$d->modify("-$i weeks");
		$wy = (int) $d->format('o');
		$wn = (int) $d->format('W');
		$mon = $d->format('Y-m-d');
		$lyMon = dash_week_monday($wy - 1, $wn);
		$now = array();
		$last = array();
		for ($k = 0; $k < 7; $k++) {
			$now[] = (float) (isset($daily[dash_add($mon, "+$k days")]) ? $daily[dash_add($mon, "+$k days")] : 0);
			$last[] = (float) (isset($daily[dash_add($lyMon, "+$k days")]) ? $daily[dash_add($lyMon, "+$k days")] : 0);
		}
		$weeks[] = array('no' => $wn, 'year' => $wy, 'lastYear' => $wy - 1, 'now' => $now, 'last' => dash_week_comparable($lastName !== null, $lyMon, $fyStart) ? $last : null);
	}
	// Days of the first week begun by $dataTo: all seven in an earlier year, in today's week up to today's weekday.
	$daysToDate = $dataTo > $sunday ? 7 : (int) date('N', strtotime($dataTo));

	// VAT: the current period to date on the VAT report's account range (grupper MR), the range the VAT return uses.
	// Without a usable range the figure stays null and the page leaves the cell out: the revenue accounts are not VAT.
	$vat = null;
	if (!empty($c['vat']['registered'])) {
		$mr = vat_report_range((int) $regnaar);
		if ($mr !== null && ctype_digit($mr['from']) && ctype_digit($mr['to'])) {
			$vmin = (int) $mr['from'];
			$vmax = (int) $mr['to'];
			list($pFrom, $pTo) = vat_period_range((string) $c['vat']['period'], $t);
			$vNow = dash_sum($pFrom, $t, $vmin, $vmax);
			$vLast = dash_sum(dash_add($pFrom, '-1 year'), dash_add($t, '-1 year'), $vmin, $vmax);
			// No VAT trend: prototype D shows no sparklines, and each point cost a sum over transaktioner.
			$vat = dash_kpi($vNow, $vLast, array()) + array('from' => $pFrom, 'to' => $pTo, 'deadline' => dash_vat_deadline((string) $c['vat']['period'], $pTo));
		}
	}

	// Customers per hour and weekday, averaged per weekday, for the last 7, 30 and 90 days (orders by time of day).
	$heat = array('7' => array(), '30' => array(), '90' => array());
	$counts = array();
	$from90 = dash_add($today, '-89 days');
	$q = db_select("select ordredate, substr(tidspkt, 1, 2) as hr, count(*) as n from ordrer where ordredate >= '$from90' and ordredate <= '$today' and tidspkt is not null group by ordredate, substr(tidspkt, 1, 2)", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$hr = (int) $r['hr'];
		if ($hr < 0 || $hr > 23) {
			continue;
		}
		$counts[substr((string) $r['ordredate'], 0, 10)][$hr] = (int) $r['n'];
	}
	foreach (array(7, 30, 90) as $span) {
		$grid = array_fill(0, 7, array_fill(0, 24, 0.0));
		$dayCount = array_fill(0, 7, 0);
		for ($i = 0; $i < $span; $i++) {
			$d = dash_add($today, "-$i days");
			$wd = (int) date('N', strtotime($d)) - 1;
			$dayCount[$wd]++;
			if (isset($counts[$d])) {
				foreach ($counts[$d] as $hr => $n) {
					$grid[$wd][$hr] += $n;
				}
			}
		}
		foreach ($grid as $wd => $row) {
			foreach ($row as $hr => $v) {
				$grid[$wd][$hr] = $dayCount[$wd] ? round($v / $dayCount[$wd], 2) : 0;
			}
		}
		$heat[(string) $span] = $grid;
	}

	// Revenue per item group in the fiscal year: the five largest, the rest as one slice.
	$groups = array();
	$gTotal = 0.0;
	$q = db_select("select sum(OL.pris * OL.antal * (1 - coalesce(OL.rabat, 0) / 100)) as pris, V.gruppe as gruppe, G.beskrivelse from ordrelinjer OL join ordrer O on O.id = OL.ordre_id join varer V on V.varenr = OL.varenr join grupper G on G.art = 'VG' and G.fiscal_year = '" . (int) $regnaar . "' and G.kodenr = V.gruppe where O.fakturadate >= '$fyStart' and O.fakturadate <= '$fyEnd' and O.status = 4 and O.art not in ('KK', 'KO') group by V.gruppe, G.beskrivelse order by pris desc", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$v = (float) $r['pris'];
		if ($v == 0) {
			continue; // a negative group (more credited than sold) stays: it lowers the total
		}
		$groups[] = array(trim((string) $r['beskrivelse']), $v);
		$gTotal += $v;
	}
	$donut = array('items' => array(), 'all' => array(), 'total' => $gTotal, 'count' => count($groups));
	if ($gTotal > 0) {
		$top = array_slice($groups, 0, 5);
		$rest = array_slice($groups, 5);
		foreach ($top as $g) {
			$donut['items'][] = array($g[0], round($g[1] / $gTotal * 100, 1), false, round($g[1], 2));
		}
		foreach ($groups as $g) {
			$donut['all'][] = array($g[0], round($g[1] / $gTotal * 100, 1), false, round($g[1], 2));
		}
		if ($rest) {
			$sum = 0.0;
			foreach ($rest as $g) {
				$sum += $g[1];
			}
			$donut['items'][] = array(sprintf(findtekst('6964|Øvrige (%s grupper)', (int) $c['sprog_id']), count($rest)), round($sum / $gTotal * 100, 1), true, round($sum, 2));
		}
	}

	// Uninvoiced orders, all of them (Adam 2026-10-12: no 30-day window, so the key figures never exceed the headline).
	$quick = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '3' and box4 = 'on'", __FILE__ . " linje " . __LINE__));
	$o = db_fetch_array(db_select("select count(*) as n, coalesce(sum(\"sum\"), 0) as s from ordrer where " . ($quick ? 'status <= 3' : 'status = 2') . " and art = 'DO'", __FILE__ . " linje " . __LINE__));
	$orders = array('count' => $o ? (int) $o['n'] : 0, 'sum' => $o ? (float) $o['s'] : 0.0, 'largest' => null);
	$lo = db_fetch_array(db_select("select firmanavn, \"sum\" as s from ordrer where " . ($quick ? 'status <= 3' : 'status = 2') . " and art = 'DO' order by \"sum\" desc limit 1", __FILE__ . " linje " . __LINE__));
	if ($lo) {
		$orders['largest'] = array('name' => trim((string) $lo['firmanavn']), 'sum' => (float) $lo['s']);
	}
	// Delivered and not invoiced (status 2): ready to invoice now, and the ones delivered more than 14 days ago.
	$fourteen = dash_add($today, '-14 days');
	$r = db_fetch_array(db_select("select count(*) as n, coalesce(sum(\"sum\"), 0) as s from ordrer where status = 2 and art = 'DO'", __FILE__ . " linje " . __LINE__));
	$orders['ready'] = array('count' => $r ? (int) $r['n'] : 0, 'sum' => $r ? (float) $r['s'] : 0.0);
	$r = db_fetch_array(db_select("select count(*) as n, coalesce(sum(\"sum\"), 0) as s from ordrer where status = 2 and art = 'DO' and levdate < '$fourteen'", __FILE__ . " linje " . __LINE__));
	$orders['late'] = array('count' => $r ? (int) $r['n'] : 0, 'sum' => $r ? (float) $r['s'] : 0.0);

	return array(
		'weekkpi' => dash_kpi($weekNow, $weekLast, $weekSpark),
		'revmonth' => dash_kpi($monthNow, $monthLast, $monthSpark),
		'revyear' => dash_kpi($yearNow, $yearLast, $yearSpark),
		'vatcount' => $vat,
		'month' => array('labels' => $labels, 'now' => $mNow, 'last' => $lastName !== null ? $mLast : null, 'nowLabel' => $nowName, 'lastLabel' => $lastName, 'toDate' => $monthsToDate),
		'weeks' => array('list' => $weeks, 'dayToDate' => $daysToDate),
		'days' => $names['days'],
		'heat' => $heat,
		'donut' => $donut,
		'orders' => $orders,
	);
}
