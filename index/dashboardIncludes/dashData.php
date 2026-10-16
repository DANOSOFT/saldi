<?php
// ---- index/dashboardIncludes/dashData.php --- lap 5.0.0 --- 2026.10.10 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20261008 Sawaneh The numbers behind Oversigt (Adam's prototype_dashboard_tema.html v5): week, month and fiscal year
//                  revenue with last year and a trend, the VAT period with its deadline, revenue per month and per day,
//                  customers per hour, revenue per item group, orders, active users and waiting suggestions. Revenue is
//                  the sum of credit minus debit on the accounts between the dashboard's kontomin and kontomaks, as the
//                  old widgets counted it. Everything from one daily query over the last and the current fiscal year.
// 20261009 Sawaneh The largest uninvoiced order (addendum 3 §7) and the three newest suggestions (§7a) for the small cards.
// 20261010 Sawaneh Key figures 1 and 3 of §7 (open point 9 settled): status 2 = delivered, not invoiced; levdate is the delivery date.
// 20261012 Sawaneh The headline counts every uninvoiced order (Adam): the 30-day window is gone.

/**
 * Revenue per day (date => amount) between two dates on the revenue accounts.
 *
 * @return array<string, float>
 */
function dash_daily(string $from, string $to, int $min, int $max): array
{
	$out = array();
	$q = db_select("select transdate, sum(coalesce(kredit, 0) - coalesce(debet, 0)) as s from transaktioner where transdate >= '" . db_escape_string($from) . "' and transdate <= '" . db_escape_string($to) . "' and kontonr > $min and kontonr < $max group by transdate", __FILE__ . " linje " . __LINE__);
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
	$r = db_fetch_array(db_select("select sum(coalesce(kredit, 0) - coalesce(debet, 0)) as s from transaktioner where transdate >= '" . db_escape_string($from) . "' and transdate <= '" . db_escape_string($to) . "' and kontonr >= $min and kontonr <= $max", __FILE__ . " linje " . __LINE__));
	return $r ? (float) $r['s'] : 0.0;
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
 * @param array<string, mixed> $c regnstart, regnslut, regnaar, kontomin, kontomaks, sprog_id, bruger_id, vat (vat_registration()), online (active users)
 * @return array<string, mixed>
 */
function dash_data(array $c): array
{
	global $regnaar;
	$today = date('Y-m-d');
	$min = (int) $c['kontomin'];
	$max = (int) $c['kontomaks'];
	$names = dash_names((int) $c['sprog_id']);
	$fyStart = (string) $c['regnstart'];
	$fyEnd = (string) $c['regnslut'];
	$lastFyStart = dash_add($fyStart, '-1 year');
	$lastFyEnd = dash_add($fyEnd, '-1 year');
	$queryFrom = min($lastFyStart, dash_add($today, '-1 year -9 weeks'));
	$daily = dash_daily($queryFrom, $today, $min, $max);

	// Week: this ISO week to date against the same ISO week last year, trend = the last 8 weeks.
	$isoYear = (int) date('o');
	$isoWeek = (int) date('W');
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
	$monthFrom = date('Y-m-01');
	$monthNow = dash_range($daily, $monthFrom, $today);
	$monthLast = dash_range($daily, dash_add($monthFrom, '-1 year'), dash_add($today, '-1 year'));
	$monthSpark = array();
	for ($i = 7; $i >= 0; $i--) {
		$f = date('Y-m-01', strtotime("-$i months", strtotime($monthFrom)));
		$monthSpark[] = dash_range($daily, $f, date('Y-m-t', strtotime($f)));
	}

	// Fiscal year to date against last fiscal year to the same date; trend = the fiscal months to date.
	$yearNow = dash_range($daily, $fyStart, $today);
	$yearLast = dash_range($daily, $lastFyStart, dash_add($today, '-1 year'));
	$labels = array();
	$mNow = array();
	$mLast = array();
	$startMonth = (int) substr($fyStart, 5, 2);
	$startYear = (int) substr($fyStart, 0, 4);
	for ($i = 0; $i < 12; $i++) {
		$f = date('Y-m-01', mktime(0, 0, 0, $startMonth + $i, 1, $startYear));
		$labels[] = $names['months'][(int) substr($f, 5, 2) - 1];
		$mNow[] = dash_range($daily, $f, date('Y-m-t', strtotime($f)));
		$lf = dash_add($f, '-1 year');
		$mLast[] = dash_range($daily, $lf, date('Y-m-t', strtotime($lf)));
	}
	$yearSpark = array();
	foreach ($mNow as $i => $v) {
		$f = date('Y-m-01', mktime(0, 0, 0, $startMonth + $i, 1, $startYear));
		if ($f <= $today) {
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
		$weeks[] = array('no' => $wn, 'year' => $wy, 'lastYear' => $wy - 1, 'now' => $now, 'last' => $last);
	}

	// VAT: the current period to date on the VAT report's account range (grupper MR), as the old widget counted it.
	$vat = null;
	if (!empty($c['vat']['registered'])) {
		$mr = db_fetch_array(db_select("select box1, box2 from grupper where art = 'MR' and fiscal_year = " . (int) $regnaar, __FILE__ . " linje " . __LINE__));
		$vmin = $mr ? (int) $mr['box1'] : $min;
		$vmax = $mr ? (int) $mr['box2'] : $max;
		list($pFrom, $pTo) = vat_period_range((string) $c['vat']['period'], $today);
		$vNow = dash_sum($pFrom, $today, $vmin, $vmax);
		$vLast = dash_sum(dash_add($pFrom, '-1 year'), dash_add($today, '-1 year'), $vmin, $vmax);
		$spark = array();
		for ($i = 7; $i >= 0; $i--) {
			list($sf, $st) = vat_period_range((string) $c['vat']['period'], dash_add($pFrom, "-$i months"));
			if ($i > 0 && $sf === $pFrom) {
				continue;
			}
			$spark[$sf] = dash_sum($sf, min($st, $today), $vmin, $vmax);
		}
		$vat = dash_kpi($vNow, $vLast, array_slice(array_values($spark), -8)) + array('from' => $pFrom, 'to' => $pTo, 'deadline' => dash_vat_deadline((string) $c['vat']['period'], $pTo));
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
		if ($v <= 0) {
			continue;
		}
		$groups[] = array(trim((string) $r['beskrivelse']), $v);
		$gTotal += $v;
	}
	$donut = array('items' => array(), 'total' => $gTotal, 'count' => count($groups));
	if ($gTotal > 0) {
		$top = array_slice($groups, 0, 5);
		$rest = array_slice($groups, 5);
		foreach ($top as $g) {
			$donut['items'][] = array($g[0], round($g[1] / $gTotal * 100, 1), false);
		}
		if ($rest) {
			$sum = 0.0;
			foreach ($rest as $g) {
				$sum += $g[1];
			}
			$donut['items'][] = array(sprintf(findtekst('6964|Øvrige (%s grupper)', (int) $c['sprog_id']), count($rest)), round($sum / $gTotal * 100, 1), true);
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

	// Suggestions waiting: unread notifications of the type suggestion.
	$suggest = 0;
	$suggestList = array();
	if (function_exists('notif_ready') && notif_ready() && function_exists('notif_list')) {
		foreach (notif_list((int) $c['bruger_id']) as $n) {
			if (!empty($n['unread']) && isset($n['type']) && $n['type'] === 'suggestion') {
				$suggest++;
				if (count($suggestList) < 3) {
					$suggestList[] = array('title' => (string) $n['title'], 'body' => (string) $n['body'], 'link' => (string) $n['link']);
				}
			}
		}
	}

	$fyLabel = function (int $kodenr): string {
		$r = db_fetch_array(db_select("select beskrivelse from grupper where art = 'RA' and kodenr = $kodenr", __FILE__ . " linje " . __LINE__));
		return $r ? trim((string) $r['beskrivelse']) : (string) $kodenr;
	};
	return array(
		'weekkpi' => dash_kpi($weekNow, $weekLast, $weekSpark),
		'revmonth' => dash_kpi($monthNow, $monthLast, $monthSpark),
		'revyear' => dash_kpi($yearNow, $yearLast, $yearSpark),
		'vatcount' => $vat,
		'month' => array('labels' => $labels, 'now' => $mNow, 'last' => $mLast, 'nowLabel' => $fyLabel((int) $regnaar), 'lastLabel' => $fyLabel((int) $regnaar - 1)),
		'weeks' => array('list' => $weeks),
		'days' => $names['days'],
		'heat' => $heat,
		'donut' => $donut,
		'orders' => $orders,
		'online' => (int) $c['online'],
		'suggest' => $suggest,
		'suggestList' => $suggestList,
	);
}
