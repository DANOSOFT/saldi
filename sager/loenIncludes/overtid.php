<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- sager/loenIncludes/overtid.php --- ver 5.1.1 --- 2026-11-13 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261113 CL/MMR SD-824 New overview of overtime hours and wage expenses per case, per month and per employee.

/**
 * Reads the approved wage slips in a period and adds up overtime hours and wage expenses.
 *
 * Overtime hours (t50pct/t100pct) come from the slip types that carry them in the wage settlement
 * (timer, akktimer, akkord), so an akkord settlement (akk_afr) that repeats its dyrtid hours is not counted twice.
 * Wage expenses are loen.sum of every approved slip except akktimer, the same rule as «Lønudgifter» on the case page.
 *
 * @param int    $sagId 0 for all cases, otherwise one case (sager.id).
 * @param string $fra   First date, Y-m-d.
 * @param string $til   Last date, Y-m-d.
 * @return array{
 *     sager: array<int, array{t50: float, t100: float, loen: float}>,
 *     maaneder: array<string, array{t50: float, t100: float, loen: float}>,
 *     ansatte: array<int, array{t50: float, t100: float}>
 * } Totals per case id, per month (YYYY-MM, only when $sagId > 0) and per employee id (only when $sagId > 0).
 */
function overtidData($sagId, $fra, $til) {
	$data = array('sager' => array(), 'maaneder' => array(), 'ansatte' => array());
	$sagId = (int)$sagId;
	$qtxt = "select sag_id, loendate, art, ansatte, t50pct, t100pct, sum from loen ";
	$qtxt.= "where sag_id > 0 and godkendt >= '1' and coalesce(afvist, '') = '' ";
	$qtxt.= "and loendate >= '" . db_escape_string($fra) . "' and loendate <= '" . db_escape_string($til) . "'";
	if ($sagId) {
		$qtxt.= " and sag_id = '$sagId'";
	}
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$id = (int)$r['sag_id'];
		$md = substr($r['loendate'], 0, 7);
		if (!isset($data['sager'][$id])) {
			$data['sager'][$id] = array('t50' => 0, 't100' => 0, 'loen' => 0);
		}
		if ($sagId && !isset($data['maaneder'][$md])) {
			$data['maaneder'][$md] = array('t50' => 0, 't100' => 0, 'loen' => 0);
		}
		if (in_array($r['art'], array('timer', 'akktimer', 'akkord'))) {
			$ansatte = explode(chr(9), (string)$r['ansatte']);
			$a50     = explode(chr(9), (string)$r['t50pct']);
			$a100    = explode(chr(9), (string)$r['t100pct']);
			$antal   = max(count($ansatte), count($a50), count($a100));
			for ($i = 0; $i < $antal; $i++) {
				$t50  = (float)ifset($a50, $i, 0);
				$t100 = (float)ifset($a100, $i, 0);
				$data['sager'][$id]['t50']  += $t50;
				$data['sager'][$id]['t100'] += $t100;
				if ($sagId) {
					$data['maaneder'][$md]['t50']  += $t50;
					$data['maaneder'][$md]['t100'] += $t100;
					$ansatId = (int)ifset($ansatte, $i, 0);
					if ($t50 || $t100) {
						if (!isset($data['ansatte'][$ansatId])) {
							$data['ansatte'][$ansatId] = array('t50' => 0, 't100' => 0);
						}
						$data['ansatte'][$ansatId]['t50']  += $t50;
						$data['ansatte'][$ansatId]['t100'] += $t100;
					}
				}
			}
		}
		if ($r['art'] != 'akktimer') {
			$data['sager'][$id]['loen'] += (float)$r['sum'];
			if ($sagId) {
				$data['maaneder'][$md]['loen'] += (float)$r['sum'];
			}
		}
	}
	ksort($data['maaneder']);
	return $data;
}

/**
 * Turns a dd-mm-yyyy date from the period form into Y-m-d.
 * Anything that is not a real calendar date (an array, text, 31-02-2026, 99-99-9999) gives $standard,
 * so a typing mistake shows the default period instead of a silent one-day period.
 *
 * @param mixed  $vaerdi   The request value.
 * @param string $standard Default date, Y-m-d.
 * @return string Date as Y-m-d.
 */
function overtidDato($vaerdi, $standard) {
	if (!is_string($vaerdi) || !preg_match('/^(\d{1,2})[-.\/](\d{1,2})[-.\/](\d{2}|\d{4})$/', trim($vaerdi), $m)) {
		return $standard;
	}
	$dag     = (int)$m[1];
	$maaned  = (int)$m[2];
	$aar     = (int)$m[3];
	if (strlen($m[3]) == 2) {
		$aar += 2000;
	}
	if (!checkdate($maaned, $dag, $aar)) {
		return $standard;
	}
	return sprintf('%04d-%02d-%02d', $aar, $maaned, $dag);
}

/**
 * Prints one table row of hours and amounts.
 *
 * @param string     $label First cell, already HTML-safe.
 * @param array      $row   Array with t50, t100 and optionally loen.
 * @param bool       $loen  Whether to print the wage expense column.
 * @param string     $style Extra style for the row.
 * @return void
 */
function overtidRow($label, $row, $loen, $style = '') {
	print "<tr style='$style'><td>$label</td>";
	print "<td align='right'>" . dkdecimal($row['t50'], 2) . "</td>";
	print "<td align='right'>" . dkdecimal($row['t100'], 2) . "</td>";
	print "<td align='right'><b>" . dkdecimal($row['t50'] + $row['t100'], 2) . "</b></td>";
	if ($loen) {
		print "<td align='right'>" . dkdecimal($row['loen'], 2) . "</td>";
	}
	print "</tr>\n";
}

/**
 * Prints the links back to the list of all cases and to the case itself.
 *
 * @param int    $sagId The case (sager.id).
 * @param string $fraDk First date, dd-mm-yyyy.
 * @param string $tilDk Last date, dd-mm-yyyy.
 * @return void
 */
function overtidLinks($sagId, $fraDk, $tilDk) {
	global $sprog_id;

	$sagId = (int)$sagId;
	print "<p><a href='loen.php?funktion=overtid&amp;fra=" . urlencode($fraDk) . "&amp;til=" . urlencode($tilDk) . "'>" . findtekst('8419|Alle sager', $sprog_id) . "</a>";
	print " &nbsp;|&nbsp; <a href='sager.php?funktion=vis_sag&amp;sag_id=$sagId'>" . ucfirst(findtekst('904|til', $sprog_id)) . " " . lcfirst(findtekst('2792|Sag', $sprog_id)) . "</a></p>\n";
}

/**
 * Page «Overtid og løn pr. sag» (loen.php?funktion=overtid).
 * Without sag_id it lists every case with approved slips in the period; with sag_id it shows the case per month and per employee.
 *
 * @return void
 */
function overtid() {
	global $sag_rettigheder;
	global $sprog_id;

	if (!substr((string)$sag_rettigheder, 6, 1)) {
		print "<div class='content'><p>" . findtekst('8506|Du har ikke adgang til denne side.', $sprog_id) . "</p></div>";
		return;
	}

	$sagId  = (int)ifset($_GET, 'sag_id', 0);
	$fra = overtidDato(ifset($_GET, 'fra', ''), date('Y') . '-01-01');
	$til = overtidDato(ifset($_GET, 'til', ''), date('Y') . '-12-31');
	if ($fra > $til) {
		list($fra, $til) = array($til, $fra);
	}
	$fraDk = dkdato($fra);
	$tilDk = dkdato($til);

	$data = overtidData($sagId, $fra, $til);

	$sager = array();
	$sagIds = array_map('intval', array_keys($data['sager']));
	if ($sagId) {
		$sagIds[] = $sagId;
	}
	if ($sagIds) {
		$q = db_select("select id, sagsnr, firmanavn, udf_addr1, udf_postnr, udf_bynavn from sager where id in (" . implode(',', array_unique($sagIds)) . ")", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$by = trim($r['udf_postnr'] . ' ' . $r['udf_bynavn']);
			$sager[(int)$r['id']] = array(
				'nr'      => $r['sagsnr'],
				'kunde'   => (string)$r['firmanavn'],
				'adresse' => trim($r['udf_addr1'] . ($by ? ', ' . $by : ''), ', '),
			);
		}
	}

	$h50    = findtekst('8502|Overtid 50%', $sprog_id);
	$h100   = findtekst('8503|Overtid 100%', $sprog_id);
	$hIalt  = findtekst('8504|Overtid i alt', $sprog_id);
	$hLoen  = findtekst('2864|Lønudgifter', $sprog_id);
	$hTotal = findtekst('2373|I alt', $sprog_id);

	print "<div class='content'>\n";
	print "<h3>" . findtekst('8501|Overtid og løn pr. sag', $sprog_id);
	if ($sagId && isset($sager[$sagId])) {
		$sag = $sager[$sagId];
		print ": " . findtekst('2792|Sag', $sprog_id) . " " . htmlspecialchars($sag['nr'] . ' - ' . $sag['kunde'] . ' - ' . $sag['adresse'], ENT_QUOTES);
	}
	print "</h3>\n";

	print "<form method='get' action='loen.php'>\n";
	print "<input type='hidden' name='funktion' value='overtid'>\n";
	if ($sagId) {
		print "<input type='hidden' name='sag_id' value='$sagId'>\n";
	}
	print findtekst('899|Periode', $sprog_id) . ": ";
	print "<input type='text' name='fra' value='$fraDk' style='width:80px'> " . findtekst('904|til', $sprog_id) . " ";
	print "<input type='text' name='til' value='$tilDk' style='width:80px'> ";
	print "<input type='submit' class='button gray small' value='" . findtekst('1133|Vis', $sprog_id) . "'>\n";
	print "</form>\n";
	print "<p><i>" . findtekst('8505|Kun godkendte sedler tæller med. Overtid er timer med overtidstillæg.', $sprog_id) . "</i></p>\n";

	if (!$data['sager']) {
		print "<p>" . findtekst('8507|Ingen godkendte sedler i perioden.', $sprog_id) . "</p>\n";
		if ($sagId) {
			overtidLinks($sagId, $fraDk, $tilDk);
		}
		print "</div>\n";
		return;
	}

	$link = "loen.php?funktion=overtid&amp;fra=" . urlencode($fraDk) . "&amp;til=" . urlencode($tilDk);
	if (!$sagId) {
		$rows = array();
		foreach ($data['sager'] as $id => $row) {
			if (!$row['t50'] && !$row['t100'] && !$row['loen']) {
				continue;
			}
			$rows[$id] = $row;
		}
		uksort($rows, function ($a, $b) use ($sager) {
			return (float)ifset($sager, $a, array('nr' => 0))['nr'] <=> (float)ifset($sager, $b, array('nr' => 0))['nr'];
		});
		print "<table class='dataTable' cellspacing='0' cellpadding='3' border='0' style='min-width:700px'>\n";
		print "<tr><th align='left'>" . findtekst('2819|Sagsnr.', $sprog_id) . "</th><th align='left'>" . findtekst('35|Kunde', $sprog_id) . "</th><th align='left'>" . findtekst('140|Adresse', $sprog_id) . "</th>";
		print "<th align='right'>$h50</th><th align='right'>$h100</th><th align='right'>$hIalt</th><th align='right'>$hLoen</th></tr>\n";
		$total = array('t50' => 0, 't100' => 0, 'loen' => 0);
		$n = 0;
		foreach ($rows as $id => $row) {
			$nr      = isset($sager[$id]) ? htmlspecialchars((string)$sager[$id]['nr'], ENT_QUOTES) : $id;
			$kunde   = isset($sager[$id]) ? htmlspecialchars($sager[$id]['kunde'], ENT_QUOTES) : '';
			$adresse = isset($sager[$id]) ? htmlspecialchars($sager[$id]['adresse'], ENT_QUOTES) : '';
			$bg = ($n++ % 2) ? 'background-color:#f2f2f2;' : '';
			print "<tr style='$bg'><td><a href='$link&amp;sag_id=$id'>$nr</a></td><td>$kunde</td><td>$adresse</td>";
			print "<td align='right'>" . dkdecimal($row['t50'], 2) . "</td><td align='right'>" . dkdecimal($row['t100'], 2) . "</td>";
			print "<td align='right'><b>" . dkdecimal($row['t50'] + $row['t100'], 2) . "</b></td><td align='right'>" . dkdecimal($row['loen'], 2) . "</td></tr>\n";
			$total['t50']  += $row['t50'];
			$total['t100'] += $row['t100'];
			$total['loen'] += $row['loen'];
		}
		print "<tr style='border-top:1px solid #000;font-weight:bold'><td colspan='3'>$hTotal</td>";
		print "<td align='right'>" . dkdecimal($total['t50'], 2) . "</td><td align='right'>" . dkdecimal($total['t100'], 2) . "</td>";
		print "<td align='right'>" . dkdecimal($total['t50'] + $total['t100'], 2) . "</td><td align='right'>" . dkdecimal($total['loen'], 2) . "</td></tr>\n";
		print "</table>\n</div>\n";
		return;
	}

	// One case: per month, then per employee.
	print "<table class='dataTable' cellspacing='0' cellpadding='3' border='0' style='min-width:600px'>\n";
	print "<tr><th align='left'>" . findtekst('1217|Måned', $sprog_id) . "</th><th align='right'>$h50</th><th align='right'>$h100</th>";
	print "<th align='right'>$hIalt</th><th align='right'>$hLoen</th></tr>\n";
	foreach ($data['maaneder'] as $md => $row) {
		overtidRow(substr($md, 5, 2) . '-' . substr($md, 0, 4), $row, true);
	}
	overtidRow($hTotal, $data['sager'][$sagId], true, 'border-top:1px solid #000;font-weight:bold');
	print "</table>\n";

	if ($data['ansatte']) {
		$navne = array();
		$ids = array_map('intval', array_keys($data['ansatte']));
		$q = db_select("select id, navn from ansatte where id in (" . implode(',', $ids) . ")", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$navne[(int)$r['id']] = $r['navn'];
		}
		uksort($data['ansatte'], function ($a, $b) use ($navne) {
			return strcasecmp((string)ifset($navne, $a, ''), (string)ifset($navne, $b, ''));
		});
		print "<br><table class='dataTable' cellspacing='0' cellpadding='3' border='0' style='min-width:600px'>\n";
		print "<tr><th align='left'>" . findtekst('2966|Medarbejder', $sprog_id) . "</th><th align='right'>$h50</th><th align='right'>$h100</th><th align='right'>$hIalt</th></tr>\n";
		foreach ($data['ansatte'] as $ansatId => $row) {
			overtidRow(htmlspecialchars((string)ifset($navne, $ansatId, $ansatId), ENT_QUOTES), $row, false);
		}
		print "</table>\n";
	}
	overtidLinks($sagId, $fraDk, $tilDk);
	print "</div>\n";
}
