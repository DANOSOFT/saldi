<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/vatReportRange.php --- ver 5.1.1 --- 2026-11-13 ---
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261113 CL/ASR Created: the account range of the VAT report (grupper art='MR', box1..box2) for one fiscal year.
//                 Finans > Rapporter > Momsangivelse and the dashboard's VAT figure both read it here, so they count the same accounts.

if (!function_exists('vat_report_range')) {
	/**
	 * The account range of the VAT report (Indstillinger > Moms, grupper art='MR') for a fiscal year.
	 *
	 * The row of $regnaar itself is used when there is one. Otherwise the row of the latest earlier fiscal year,
	 * and when every row belongs to a later year, the row of the latest year. Within one fiscal year the first
	 * row (lowest id) wins, and a row without a fiscal year counts as year 0. The chosen row is not skipped
	 * when its range is empty: an emptied range means the report is not set up, as before.
	 *
	 * @param int $regnaar Fiscal year (grupper art='RA' kodenr) the report or figure is for.
	 *
	 * @return array{
	 *   from: string,      First account of the range (box1), trimmed.
	 *   to: string,        Last account of the range (box2), trimmed.
	 *   fiscal_year: int,  Fiscal year of the row the range was read from.
	 * }|null  Null when there is no MR row, or when the chosen row's box1 or box2 is empty.
	 */
	function vat_report_range(int $regnaar): ?array
	{
		$byYear = array();
		$qtxt = "select box1, box2, fiscal_year from grupper where art = 'MR' order by id";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$year = (int) $r['fiscal_year'];
			if (!isset($byYear[$year])) {
				$byYear[$year] = $r;
			}
		}
		if (!$byYear) {
			return null;
		}

		// The highest year up to $regnaar is $regnaar itself when it has a row.
		$years = array_keys($byYear);
		$upTo = array_filter($years, function ($year) use ($regnaar) {
			return $year <= $regnaar;
		});
		$year = $upTo ? max($upTo) : max($years);

		$from = trim((string) $byYear[$year]['box1']);
		$to = trim((string) $byYear[$year]['box2']);
		if ($from === '' || $to === '') {
			return null;
		}
		return array('from' => $from, 'to' => $to, 'fiscal_year' => $year);
	}
}
