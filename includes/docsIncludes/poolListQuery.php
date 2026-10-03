<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolListQuery.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ SD-719 Created: the pool list is sent in pages of 50, so what needs every document is done here, over the full set.
//                The match against the journal line (perfect / amount / date / combination), search, sort and the order of the groups.
//                The match rules are the ones docPool.php's renderFiles() used in the browser, including SD-718's combination search.
//                No database access: _docPoolData.php loads the rows and calls these.

if (!function_exists('poolListAmount')) {
	/**
	 * A pool amount as a number, read the way the list always read it (docPool.php's parseAmountToFloat()).
	 * "1.234,56" and "1,234.56" are both 1234.56; "1.234.56" (malformed) is 1234.56; "1000,5" is 1000.5.
	 *
	 * @param mixed $value
	 * @return float|null null when there is no number.
	 */
	function poolListAmount($value) {
		$s = trim((string)$value);
		if ($s === '') return null;
		if (substr_count($s, '.') > 1 && strpos($s, ',') === false) {
			$parts = explode('.', $s);
			$decimal = array_pop($parts);
			$s = implode('', $parts) . '.' . $decimal;
		}
		$lastComma = strrpos($s, ',');
		$lastDot = strrpos($s, '.');
		if ($lastComma !== false && $lastDot !== false && $lastDot > $lastComma) {
			$s = str_replace(',', '', $s);
		} elseif ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
			$s = str_replace(',', '.', str_replace('.', '', $s));
		} elseif ($lastComma !== false && $lastDot === false) {
			$s = preg_replace('/,/', '.', $s, 1);
		}
		// parseFloat() reads the leading number and ignores the rest
		if (!preg_match('/^\s*[-+]?(\d+\.?\d*|\.\d+)([eE][-+]?\d+)?/', $s, $m)) return null;
		return (float)$m[0];
	}
}

if (!function_exists('poolListDate')) {
	/**
	 * A date as Y-m-d: "2026-10-03 14:03:16" and "03-10-2026" both give "2026-10-03".
	 *
	 * @param mixed $value
	 * @return string|null
	 */
	function poolListDate($value) {
		$s = trim((string)$value);
		if ($s === '') return null;
		$s = explode(' ', $s)[0];
		$parts = explode('-', $s);
		if (count($parts) !== 3) return null;
		return strlen($parts[0]) === 4 ? $s : $parts[2] . '-' . $parts[1] . '-' . $parts[0];
	}
}

if (!function_exists('poolListMatches')) {
	/**
	 * Which documents match the journal line: amount and date (perfect), amount only, date only, or, when nothing matches
	 * on amount, documents that add up to the amount in pairs, then triplets, then quads.
	 *
	 * @param array<int, array{filename: string, amount: mixed, date: mixed}> $rows In list order (the combination found first depends on it).
	 * @param string|null $sum  The line's amount as shown in Danish format ("1.234,56").
	 * @param string|null $date The line's date, dd-mm-yyyy or yyyy-mm-dd.
	 * @return array{byFile: array<string, string>, perfect: array<int, string>, amount: array<int, string>, date: array<int, string>,
	 *         combination: array{files: array<int, string>, first: array{files: array<int, string>, amounts: array<int, float>}|null}}
	 */
	function poolListMatches(array $rows, $sum, $date) {
		$result = array('byFile' => array(), 'perfect' => array(), 'amount' => array(), 'date' => array(),
			'combination' => array('files' => array(), 'first' => null));
		// As the list read it: dots removed, the first comma as decimal point, then the leading number
		$sumText = preg_replace('/,/', '.', str_replace('.', '', (string)$sum), 1);
		$total = preg_match('/^\s*[-+]?(\d+\.?\d*|\.\d+)([eE][-+]?\d+)?/', $sumText, $m) ? (float)$m[0] : null;
		$hasAmount = $total !== null && $total != 0;
		$targetDate = poolListDate($date);
		if (!$hasAmount && $targetDate === null) return $result;

		$withAmounts = array();
		foreach ($rows as $row) {
			$file = (string)$row['filename'];
			$amount = poolListAmount($row['amount'] ?? '');
			$isDate = $targetDate !== null && poolListDate($row['date'] ?? '') === $targetDate;
			$isAmount = $hasAmount && $amount !== null && abs($amount - $total) < 0.01;
			if ($amount !== null && $amount > 0) $withAmounts[] = array('file' => $file, 'amount' => $amount);
			if ($isAmount && $isDate) {
				$result['perfect'][] = $file;
				$result['byFile'][$file] = 'perfect';
			} elseif ($isAmount) {
				$result['amount'][] = $file;
				$result['byFile'][$file] = 'amount';
			} elseif ($isDate) {
				$result['date'][] = $file;
				$result['byFile'][$file] = 'date';
			}
		}

		if (!$hasAmount || $result['perfect'] || $result['amount'] || count($withAmounts) < 2) return $result;

		// SD-718: øre and lookup tables, same combinations in the same order as the nested loops
		$totalCents = (int)round($total * 100);
		$docs = array_values(array_filter($withAmounts, function ($d) use ($totalCents) { return (int)round($d['amount'] * 100) <= $totalCents; }));
		$cents = array_map(function ($d) { return (int)round($d['amount'] * 100); }, $docs);
		$byCents = array();
		foreach ($cents as $idx => $c) $byCents[$c][] = $idx;
		$after = function ($c, $from) use ($byCents) {
			return isset($byCents[$c]) ? array_values(array_filter($byCents[$c], function ($idx) use ($from) { return $idx > $from; })) : array();
		};
		$groups = array();
		$n = count($docs);
		for ($i = 0; $i < $n; $i++) {
			foreach ($after($totalCents - $cents[$i], $i) as $j) $groups[] = array($i, $j);
		}
		if (!$groups && $n >= 3) {
			for ($i = 0; $i < $n; $i++) {
				for ($j = $i + 1; $j < $n; $j++) {
					foreach ($after($totalCents - $cents[$i] - $cents[$j], $j) as $k) $groups[] = array($i, $j, $k);
				}
			}
		}
		if (!$groups && $n >= 4) {
			$pairsBySum = array();
			for ($k = 0; $k < $n; $k++) {
				for ($l = $k + 1; $l < $n; $l++) {
					$s = $cents[$k] + $cents[$l];
					if ($s <= $totalCents) $pairsBySum[$s][] = array($k, $l);
				}
			}
			for ($i = 0; $i < $n; $i++) {
				for ($j = $i + 1; $j < $n; $j++) {
					$rest = $totalCents - $cents[$i] - $cents[$j];
					if (!isset($pairsBySum[$rest])) continue;
					foreach ($pairsBySum[$rest] as $kl) {
						if ($kl[0] > $j) $groups[] = array($i, $j, $kl[0], $kl[1]);
					}
				}
			}
		}
		foreach ($groups as $g => $idxs) {
			foreach ($idxs as $idx) {
				$file = $docs[$idx]['file'];
				if (!in_array($file, $result['combination']['files'], true)) $result['combination']['files'][] = $file;
				// A date match keeps its own group; it still counts in the combination
				if (!isset($result['byFile'][$file])) $result['byFile'][$file] = 'combination';
			}
			if ($g === 0) {
				$result['combination']['first'] = array(
					'files' => array_map(function ($idx) use ($docs) { return $docs[$idx]['file']; }, $idxs),
					'amounts' => array_map(function ($idx) use ($docs) { return $docs[$idx]['amount']; }, $idxs),
				);
			}
		}
		return $result;
	}
}

if (!function_exists('poolListSearch')) {
	/**
	 * The rows whose file name, subject, account, amount, date, invoice number or description contain the text (any case).
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @param string $text
	 * @return array<int, array<string, mixed>>
	 */
	function poolListSearch(array $rows, $text) {
		$needle = mb_strtolower(trim((string)$text), 'UTF-8');
		if ($needle === '') return $rows;
		return array_values(array_filter($rows, function ($row) use ($needle) {
			$hay = '';
			foreach (array('filename', 'subject', 'account', 'amount', 'date', 'invoiceNumber', 'description') as $key) {
				$hay .= ' ' . (string)($row[$key] ?? '');
			}
			return mb_strpos(mb_strtolower($hay, 'UTF-8'), $needle) !== false;
		}));
	}
}

if (!function_exists('poolListSort')) {
	/**
	 * Sorts by a column of the list. Equal values keep their order (usort is stable from PHP 8).
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @param string $field 'subject', 'amount', 'invoiceNumber' or 'date'; anything else leaves the order alone.
	 * @param bool $asc
	 * @return array<int, array<string, mixed>>
	 */
	function poolListSort(array $rows, $field, $asc) {
		if (!in_array($field, array('subject', 'amount', 'invoiceNumber', 'date'), true)) return $rows;
		$key = function ($row) use ($field) {
			if ($field === 'amount') return poolListAmount($row['amount'] ?? '') ?? 0.0;
			if ($field === 'date') {
				$t = strtotime((string)($row['date'] ?? ''));
				return $t === false ? 0 : $t;
			}
			return mb_strtolower((string)($row[$field] ?? ''), 'UTF-8');
		};
		usort($rows, function ($a, $b) use ($key, $asc) {
			$cmp = $key($a) <=> $key($b);
			return $asc ? $cmp : -$cmp;
		});
		return $rows;
	}
}

if (!function_exists('poolListOrder')) {
	/**
	 * The list's display order: perfect matches, amount matches, date matches, combinations, then the rest, each group in its own order.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @param array<string, string> $byFile From poolListMatches().
	 * @return array<int, array<string, mixed>>
	 */
	function poolListOrder(array $rows, array $byFile) {
		$rank = array('perfect' => 0, 'amount' => 1, 'date' => 2, 'combination' => 3);
		$groups = array(array(), array(), array(), array(), array());
		foreach ($rows as $row) {
			$type = $byFile[$row['filename']] ?? null;
			$groups[$type === null ? 4 : $rank[$type]][] = $row;
		}
		return array_merge($groups[0], $groups[1], $groups[2], $groups[3], $groups[4]);
	}
}
